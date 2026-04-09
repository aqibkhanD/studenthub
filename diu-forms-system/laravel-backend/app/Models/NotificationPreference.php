<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = [
        'notifiable_id', 'notifiable_type',
        'event_type', 'channel', 'delivery',
    ];

    /**
     * Upsert a single preference — insert or update if it already exists.
     */
    public static function set(
        Model  $notifiable,
        string $eventType,
        string $channel,
        string $delivery
    ): void {
        self::updateOrCreate(
            [
                'notifiable_id'   => $notifiable->getKey(),
                'notifiable_type' => get_class($notifiable),
                'event_type'      => $eventType,
                'channel'         => $channel,
            ],
            ['delivery' => $delivery]
        );
    }
}
