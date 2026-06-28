<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class ApplicationLocaleTest extends TestCase
{
    public function test_application_uses_polish_locale_timezone_and_email_language(): void
    {
        $this->assertSame('pl', config('app.locale'));
        $this->assertSame('pl', config('app.fallback_locale'));
        $this->assertSame('pl_PL', config('app.faker_locale'));
        $this->assertSame('Europe/Warsaw', config('app.timezone'));
        $this->assertSame('Europe/Warsaw', date_default_timezone_get());
        $this->assertSame('pl', Carbon::getLocale());

        $message = new Email;

        Event::dispatch(new MessageSending($message));

        $this->assertSame(
            'pl-PL',
            $message->getHeaders()
                ->get('Content-Language')
                ->getBodyAsString()
        );
    }
}
