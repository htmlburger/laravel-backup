<?php

namespace Spatie\Backup\Notifications;

use Spatie\Backup\Events\BackupHasFailed;
use Illuminate\Notifications\Notification;
use Spatie\Backup\Events\CleanupHasFailed;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\CleanupWasSuccessful;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use Spatie\Backup\Exceptions\NotificationCouldNotBeSent;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class EventHandler
{
    /** @var \Illuminate\Contracts\Config\Repository */
    protected $config;

    public function __construct(Repository $config)
    {
        $this->config = $config;
    }

    public function subscribe(Dispatcher $events)
    {
        $events->listen($this->allBackupEventClasses(), function ($event) {
            $notifiable = $this->determineNotifiable();

            $notification = $this->determineNotification($event);
            NotificationFacade::send($notifiable, $notification);
        });
    }

    protected function determineNotifiable()
    {
        $notifiable = $this->config->get('backup.notifications.notifiable');

        if (is_callable($notifiable)) {
            return call_user_func($notifiable);
        }

        return app($notifiable);
    }

    protected function determineNotification($event): Notification
    {
        $eventName = class_basename($event);

        $notificationClass = collect($this->config->get('backup.notifications.notifications'))
            ->keys()
            ->first(function ($notificationClass) use ($eventName) {
                $notificationName = class_basename($notificationClass);

                return $notificationName === $eventName;
            });

        if (! $notificationClass) {
            throw NotificationCouldNotBeSent::noNotifcationClassForEvent($event);
        }

        return app($notificationClass)->setEvent($event);
    }

    protected function allBackupEventClasses(): array
    {
        return [
            BackupHasFailed::class,
            BackupWasSuccessful::class,
            CleanupHasFailed::class,
            CleanupWasSuccessful::class,
            HealthyBackupWasFound::class,
            UnhealthyBackupWasFound::class,
        ];
    }
}
