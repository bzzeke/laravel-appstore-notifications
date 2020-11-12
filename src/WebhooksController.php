<?php

namespace Appvise\AppStoreNotifications;

use Illuminate\Http\Request;
use Appvise\AppStoreNotifications\Model\NotificationType;
use Appvise\AppStoreNotifications\Model\AppleNotification;
use Appvise\AppStoreNotifications\Exceptions\WebhookFailed;
use Appvise\AppStoreNotifications\Model\NotificationPayload;

class WebhooksController
{
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws WebhookFailed
     */
    public function __invoke(Request $request)
    {
        $jobConfigKey = NotificationType::{$request->input('notification_type')}();

        try {
            $this->determineValidRequest($request->input('password'), $request->input('bid'));
        } catch (WebhookFailed $e) {
            throw new \RuntimeException($e->getMessage());
        }

        AppleNotification::storeNotification($jobConfigKey, $request->input());

        $payload = NotificationPayload::createFromRequest($request);

        $jobClass = config("appstore-server-notifications.jobs.{$jobConfigKey}", null);

        if (is_null($jobClass)) {
            throw WebhookFailed::jobClassDoesNotExist($jobConfigKey);
        }

        $job = new $jobClass($payload);
        $isAsync = config("appstore-server-notifications.async", true);
        if ($isAsync) {
            dispatch($job);
        } else {
            dispatch_now($job);
        }

        return response()->json();
    }

    /**
     * @param string $password
     * @param string $bundleId
     * @return bool
     * @throws WebhookFailed
     */
    private function determineValidRequest(string $password, string $bundleId): bool
    {
        if (
            $password !== config('appstore-server-notifications.shared_secret') ||
            $bundleId !== config('appstore-server-notifications.bundle_id')
        ) {
            throw WebhookFailed::nonValidRequest();
        }

        return true;
    }
}
