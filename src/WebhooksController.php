<?php

namespace Appvise\AppStoreNotifications;

use Illuminate\Http\Request;
use Appvise\AppStoreNotifications\Model\NotificationType;
use Appvise\AppStoreNotifications\Model\AppleNotification;
use Appvise\AppStoreNotifications\Exceptions\WebhookFailed;
use Appvise\AppStoreNotifications\Model\NotificationPayload;
use Illuminate\Support\Facades\Http;

class WebhooksController
{
    const PRODUCTION_VERIFY_URL = 'https://buy.itunes.apple.com/verifyReceipt';
    const SANDBOX_VERIFY_URL = 'https://sandbox.itunes.apple.com/verifyReceipt';
    const STATUS_IS_SANDBOX = 21007;

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws WebhookFailed
     */
    public function __invoke(Request $request)
    {
        $jobConfigKey = NotificationType::{$request->input('notification_type')}();

        try {
            $this->determineValidRequest($request->input('bid'));
        } catch (WebhookFailed $e) {
            throw new \RuntimeException($e->getMessage());
        }

        AppleNotification::storeNotification($jobConfigKey, $request->input());

        try {
            $this->verifyReceipt($request);
        } catch (WebhookFailed $e) {
            throw new \RuntimeException($e->getMessage());
        }

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
     * @param string $bundleId
     * @return bool
     * @throws WebhookFailed
     */
    private function determineValidRequest(string $bundleId): bool
    {
        if (
            $bundleId !== config('appstore-server-notifications.bundle_id')
        ) {
            throw WebhookFailed::nonValidRequest();
        }

        return true;
    }

    private function verifyReceipt($receipt): bool
    {
        $postData = [
            'receipt-data' => $receipt['unified_receipt']['latest_receipt'],
            'password' => config('appstore-server-notifications.shared_secret'),
        ];

        $response = Http::post(static::PRODUCTION_VERIFY_URL, $postData);
        $json = $response->json();

        if (!empty($json['status'])) {
            if ($json['status'] == static::STATUS_IS_SANDBOX) {
                $response = Http::post(static::SANDBOX_VERIFY_URL, $postData);
                $json = $response->json();
            }
        }

        if (!empty($json['status'])) {
            throw WebhookFailed::nonValidReceipt($json['status']);
        }

        return true;
    }
}
