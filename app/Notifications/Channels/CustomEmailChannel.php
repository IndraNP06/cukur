<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;

class CustomEmailChannel
{
    public function send($notifiable, Notification $notification)
    {
        if (method_exists($notification, 'toCustomEmail')) {
            return $notification->toCustomEmail($notifiable);
        }
    }
}
