<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class LogAuthenticationActivity
{
    /**
     * Handle login and logout events.
     */
    public function handle(Login|Logout $event): void
    {
        $eventName = $event instanceof Login ? 'login' : 'logout';

        activity('auth')
            ->causedBy($event->user)
            ->event($eventName)
            ->log($event instanceof Login ? '":causer.name" logged in' : '":causer.name" logged out');
    }
}
