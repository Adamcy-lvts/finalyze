<?php

namespace App\Providers;

use App\Listeners\CreditSignupBonus;
use App\Listeners\LogUserRegistered;
use App\Listeners\NotifyAdminsOfNewUserSignup;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            CreditSignupBonus::class,
            NotifyAdminsOfNewUserSignup::class,
            LogUserRegistered::class,
        ],
    ];
}
