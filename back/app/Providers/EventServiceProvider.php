<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Models\SensorReading;
use App\Observers\SensorReadingObserver;
use App\Models\Alert;
use App\Observers\AlertObserver;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        \App\Events\DeviceCommunicationReceived::class => [
            \App\Listeners\UpdateDeviceLastCommunication::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        SensorReading::observe(SensorReadingObserver::class);
        Alert::observe(AlertObserver::class);
    }
}
