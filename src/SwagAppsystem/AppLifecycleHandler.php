<?php declare(strict_types=1);

namespace App\SwagAppsystem;

use Vin\ShopwareSdk\Data\Webhook\Event\Event;

interface AppLifecycleHandler
{
    public function appInstalled(Event $event): void;

    public function appUpdated(Event $event): void;

    public function appActivated(Event $event): void;

    public function appDeactivated(Event $event): void;

    public function appDeleted(Event $event): void;
}
