<?php

namespace Appvise\AppStoreNotifications\Model;

use Illuminate\Database\Eloquent\Model;

class AppleNotification extends Model
{
    public $guarded = [];

    protected $casts = [
        'payload' => 'array',
    ];

    public static function storeNotification(string $notificationType, array $notificationPayload)
    {
        return self::create(
            [
                'type' => $notificationType,
                'payload' => $notificationPayload,
                'original_transaction_id' => self::getOriginalTransactionId($notificationPayload),
                'environment' => $notificationPayload['environment'],
            ]
        );
    }

    /**
     * Gets the original transaction id.
     * @param array $payload
     * @return string
     */
    public static function getOriginalTransactionId(array $payload): string
    {
        return $payload['unified_receipt']['latest_receipt_info'][0]['original_transaction_id'];
    }
}
