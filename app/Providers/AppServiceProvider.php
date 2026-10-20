<?php

namespace App\Providers;

use App\Models\SystemConfiguration;
use Carbon\Carbon;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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

        $appUrl = (string) config('app.url');

        if (str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }

        Event::listen(MessageSending::class, function (MessageSending $event): void {
            $headers = $event->message->getHeaders();

            if (! $headers->has('Content-Language')) {
                $headers->addTextHeader('Content-Language', 'pl-PL');
            }
        });

        View::composer('*', function ($view): void {
            $view->with(
                'activeSystemConfiguration',
                Schema::hasTable('system_configurations')
                    ? SystemConfiguration::query()
                        ->active()
                        ->latest('id')
                        ->first()
                    : null
            );
        });
    }
}
