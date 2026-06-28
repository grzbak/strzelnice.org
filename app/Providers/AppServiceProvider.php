<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        Event::listen(MessageSending::class, function (MessageSending $event): void {
            $headers = $event->message->getHeaders();

            if (! $headers->has('Content-Language')) {
                $headers->addTextHeader('Content-Language', 'pl-PL');
            }
        });
    }
}
