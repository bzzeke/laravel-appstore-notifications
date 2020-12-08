<?php

namespace Appvise\AppStoreNotifications;

use Illuminate\Support\Facades\Http;
use Appvise\AppStoreNotifications\Exceptions\ReceiptVerificationFailed;
use Appvise\AppStoreNotifications\Model\Receipt;

class ReceiptHandler
{
    const PRODUCTION_VERIFY_URL = 'https://buy.itunes.apple.com/verifyReceipt';
    const SANDBOX_VERIFY_URL = 'https://sandbox.itunes.apple.com/verifyReceipt';
    const STATUS_IS_SANDBOX = 21007;

    /**
     * Create receipt from ID (latest_receipt)
     */
    public static function verify($id)
    {
        $postData = [
            'receipt-data' => $id,
            'exclude-old-transactions' => true,
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
            throw ReceiptVerificationFailed::nonValidReceipt($json['status']);
        }

        return Receipt::createFromArray($json['latest_receipt_info'][0]);
    }
}
