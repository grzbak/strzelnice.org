<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ShootingRangePaymentAccount;
use App\Models\SystemConfiguration;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Przelewy24Service
{
    public function register(Payment $payment): string
    {
        $configuration = $this->configuration();
        $this->ensureConfigured($configuration);
        $this->refreshTransactionSession($payment);

        $payload = [
            'merchantId' => (int) $configuration->p24_merchant_id,
            'posId' => (int) $configuration->p24_pos_id,
            'sessionId' => $payment->p24_session_id,
            'amount' => $payment->amount_minor,
            'currency' => $payment->currency,
            'description' => $payment->description,
            'email' => $payment->payer_email,
            'client' => $payment->payer_name,
            'country' => 'PL',
            'language' => 'pl',
            'urlReturn' => $payment->url_return,
            'urlStatus' => $payment->url_status,
        ];

        if ($configuration->p24_time_limit !== null) {
            $payload['timeLimit'] = min(99, max(0, (int) $configuration->p24_time_limit));
        }

        $payload['sign'] = $this->sign([
            'sessionId' => $payload['sessionId'],
            'merchantId' => $payload['merchantId'],
            'amount' => $payload['amount'],
            'currency' => $payload['currency'],
            'crc' => $configuration->p24_crc,
        ]);

        $response = Http::withBasicAuth((string) $configuration->p24_pos_id, (string) $configuration->p24_api_key)
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl($configuration).'/api/v1/transaction/register', $payload);

        $data = $response->json();

        $payment->update([
            'status' => Payment::STATUS_REGISTERED,
            'p24_token' => $data['data']['token'] ?? null,
            'registered_at' => now(),
            'expires_at' => isset($payload['timeLimit']) && $payload['timeLimit'] > 0
                ? now()->addMinutes($payload['timeLimit'])
                : null,
            'raw_register_response' => $data,
        ]);

        if (! $response->successful() || empty($data['data']['token'])) {
            throw ValidationException::withMessages([
                'payment' => 'Przelewy24 nie zwrocilo tokena transakcji.',
            ]);
        }

        return $this->baseUrl($configuration).'/trnRequest/'.$data['data']['token'];
    }

    public function registerForAccount(Payment $payment, ShootingRangePaymentAccount $account): string
    {
        $this->ensureAccountConfigured($account);
        $this->refreshTransactionSession($payment);

        $payload = [
            'merchantId' => (int) $account->p24_merchant_id,
            'posId' => (int) $account->p24_pos_id,
            'sessionId' => $payment->p24_session_id,
            'amount' => $payment->amount_minor,
            'currency' => $payment->currency,
            'description' => $payment->description,
            'email' => $payment->payer_email,
            'client' => $payment->payer_name,
            'country' => 'PL',
            'language' => 'pl',
            'urlReturn' => $payment->url_return,
            'urlStatus' => $payment->url_status,
            'timeLimit' => min(99, max(0, (int) $account->reservation_payment_time_limit_minutes)),
        ];

        $payload['sign'] = $this->sign([
            'sessionId' => $payload['sessionId'],
            'merchantId' => $payload['merchantId'],
            'amount' => $payload['amount'],
            'currency' => $payload['currency'],
            'crc' => $account->p24_crc,
        ]);

        $response = Http::withBasicAuth((string) $account->p24_pos_id, (string) $account->p24_api_key)
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrlForSandbox((bool) $account->p24_sandbox).'/api/v1/transaction/register', $payload);

        $data = $response->json();

        $payment->update([
            'status' => Payment::STATUS_REGISTERED,
            'p24_token' => $data['data']['token'] ?? null,
            'registered_at' => now(),
            'expires_at' => $payload['timeLimit'] > 0
                ? now()->addMinutes($payload['timeLimit'])
                : null,
            'raw_register_response' => $data,
        ]);

        if (! $response->successful() || empty($data['data']['token'])) {
            throw ValidationException::withMessages([
                'payment' => 'Przelewy24 nie zwróciło tokena transakcji.',
            ]);
        }

        return $this->baseUrlForSandbox((bool) $account->p24_sandbox).'/trnRequest/'.$data['data']['token'];
    }

    public function paymentUrlForAccount(Payment $payment, ShootingRangePaymentAccount $account): ?string
    {
        if (blank($payment->p24_token)) {
            return null;
        }

        return $this->baseUrlForSandbox((bool) $account->p24_sandbox).'/trnRequest/'.$payment->p24_token;
    }

    public function activePaymentUrlForAccount(Payment $payment, ShootingRangePaymentAccount $account): string
    {
        if (
            $payment->p24_token
            && (! $payment->expires_at || $payment->expires_at->isFuture())
            && $payment->status !== Payment::STATUS_PAID
        ) {
            return $this->paymentUrlForAccount($payment, $account)
                ?? $this->registerForAccount($payment, $account);
        }

        return $this->registerForAccount($payment, $account);
    }

    public function verify(Payment $payment, int $p24OrderId): bool
    {
        $configuration = $this->configuration();
        $this->ensureConfigured($configuration);

        $payload = [
            'merchantId' => (int) $configuration->p24_merchant_id,
            'posId' => (int) $configuration->p24_pos_id,
            'sessionId' => $payment->p24_session_id,
            'amount' => $payment->amount_minor,
            'currency' => $payment->currency,
            'orderId' => $p24OrderId,
        ];

        $payload['sign'] = $this->sign([
            'sessionId' => $payload['sessionId'],
            'orderId' => $payload['orderId'],
            'amount' => $payload['amount'],
            'currency' => $payload['currency'],
            'crc' => $configuration->p24_crc,
        ]);

        $response = Http::withBasicAuth((string) $configuration->p24_pos_id, (string) $configuration->p24_api_key)
            ->acceptJson()
            ->asJson()
            ->put($this->baseUrl($configuration).'/api/v1/transaction/verify', $payload);

        return $response->successful();
    }

    public function verifyForAccount(Payment $payment, ShootingRangePaymentAccount $account, int $p24OrderId): bool
    {
        $this->ensureAccountConfigured($account);

        $payload = [
            'merchantId' => (int) $account->p24_merchant_id,
            'posId' => (int) $account->p24_pos_id,
            'sessionId' => $payment->p24_session_id,
            'amount' => $payment->amount_minor,
            'currency' => $payment->currency,
            'orderId' => $p24OrderId,
        ];

        $payload['sign'] = $this->sign([
            'sessionId' => $payload['sessionId'],
            'orderId' => $payload['orderId'],
            'amount' => $payload['amount'],
            'currency' => $payload['currency'],
            'crc' => $account->p24_crc,
        ]);

        $response = Http::withBasicAuth((string) $account->p24_pos_id, (string) $account->p24_api_key)
            ->acceptJson()
            ->asJson()
            ->put($this->baseUrlForSandbox((bool) $account->p24_sandbox).'/api/v1/transaction/verify', $payload);

        return $response->successful();
    }

    public function refundForAccount(
        Payment $payment,
        ShootingRangePaymentAccount $account,
        ?int $amountMinor = null,
        ?string $description = null
    ): bool {
        $this->ensureAccountConfigured($account);

        if (! $payment->p24_order_id) {
            throw ValidationException::withMessages([
                'payment' => 'Nie można zlecić zwrotu, ponieważ płatność nie ma numeru transakcji Przelewy24.',
            ]);
        }

        $alreadyRefunded = (int) $payment->refunded_amount_minor;
        $remainingAmount = max(0, (int) $payment->amount_minor - $alreadyRefunded);
        $amountMinor ??= $remainingAmount;

        if ($amountMinor <= 0 || $amountMinor > $remainingAmount) {
            throw ValidationException::withMessages([
                'payment' => 'Kwota zwrotu jest nieprawidłowa albo płatność została już rozliczona.',
            ]);
        }
        $requestId = 'refund-'.$payment->id.'-'.Str::random(12);
        $refundsUuid = 'res-ref-'.$payment->id.'-'.Str::random(10);
        $description = Str::limit(
            $description ?: 'Zwrot płatności za rezerwację',
            35,
            ''
        );

        $payload = [
            'requestId' => $requestId,
            'refundsUuid' => $refundsUuid,
            'refunds' => [[
                'orderId' => (int) $payment->p24_order_id,
                'sessionId' => $payment->p24_session_id,
                'amount' => $amountMinor,
                'description' => $description,
            ]],
        ];

        $payment->update([
            'status' => Payment::STATUS_REFUND_PENDING,
            'refund_requested_amount_minor' => $amountMinor,
            'refund_request_id' => $requestId,
            'refunds_uuid' => $refundsUuid,
            'refund_error' => null,
        ]);

        $response = Http::withBasicAuth((string) $account->p24_pos_id, (string) $account->p24_api_key)
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrlForSandbox((bool) $account->p24_sandbox).'/api/v1/transaction/refund', $payload);

        $data = $response->json();

        if (! $response->successful()) {
            $payment->update([
                'status' => Payment::STATUS_REFUND_FAILED,
                'refund_error' => data_get($data, 'error')
                    ?? data_get($data, 'message')
                    ?? 'Przelewy24 odrzuciło dyspozycję zwrotu.',
                'raw_refund_response' => $data,
            ]);

            return false;
        }

        $refundedTotal = $alreadyRefunded + $amountMinor;
        $payment->update([
            'status' => $refundedTotal >= (int) $payment->amount_minor
                ? Payment::STATUS_REFUNDED
                : Payment::STATUS_PARTIALLY_REFUNDED,
            'refunded_amount_minor' => $refundedTotal,
            'refund_requested_amount_minor' => null,
            'refunded_at' => now(),
            'refund_error' => null,
            'raw_refund_response' => $data,
        ]);

        return true;
    }

    public function assertConfigured(): void
    {
        $this->ensureConfigured($this->configuration());
    }

    public function testAccess(array $data): array
    {
        $configuration = new SystemConfiguration([
            'p24_enabled' => (bool) ($data['p24_enabled'] ?? false),
            'p24_sandbox' => (bool) ($data['p24_sandbox'] ?? true),
            'p24_merchant_id' => $data['p24_merchant_id'] ?? null,
            'p24_pos_id' => $data['p24_pos_id'] ?? null,
            'p24_crc' => $data['p24_crc'] ?? null,
            'p24_api_key' => $data['p24_api_key'] ?? null,
        ]);

        $this->ensureConfigured($configuration);

        $response = Http::withBasicAuth((string) $configuration->p24_pos_id, (string) $configuration->p24_api_key)
            ->acceptJson()
            ->timeout(10)
            ->get($this->baseUrl($configuration).'/api/v1/testAccess');

        return [
            'ok' => $response->successful() && (bool) data_get($response->json(), 'data'),
            'status' => $response->status(),
            'message' => $this->testAccessMessage($response),
        ];
    }

    public function testAccountAccess(array $data): array
    {
        return $this->testAccess([
            'p24_enabled' => true,
            'p24_sandbox' => (bool) ($data['p24_sandbox'] ?? true),
            'p24_merchant_id' => $data['p24_merchant_id'] ?? null,
            'p24_pos_id' => $data['p24_pos_id'] ?? null,
            'p24_crc' => $data['p24_crc'] ?? null,
            'p24_api_key' => $data['p24_api_key'] ?? null,
        ]);
    }

    private function configuration(): ?SystemConfiguration
    {
        return SystemConfiguration::activeConfiguration();
    }

    private function refreshTransactionSession(Payment $payment): void
    {
        if ($payment->status === Payment::STATUS_PAID) {
            return;
        }

        if (! $payment->registered_at && ! $payment->p24_token) {
            return;
        }

        if ($payment->p24_token && (! $payment->expires_at || $payment->expires_at->isFuture())) {
            return;
        }

        $payment->forceFill([
            'p24_session_id' => $payment->package_order_id
                ? 'pkg-'.$payment->package_order_id.'-'.Str::random(24)
                : 'res-'.$payment->payable_id.'-'.Str::random(24),
            'p24_token' => null,
            'p24_order_id' => null,
            'p24_method' => null,
            'registered_at' => null,
            'expires_at' => null,
            'raw_register_response' => null,
        ])->save();
    }

    private function ensureConfigured(?SystemConfiguration $configuration): void
    {
        $missing = [];

        if (! $configuration) {
            $missing[] = 'aktywna konfiguracja systemu';
        } elseif (! $configuration->p24_enabled) {
            $missing[] = 'wlaczenie platnosci P24';
        }

        if ($configuration && blank($configuration->p24_merchant_id)) {
            $missing[] = 'Merchant ID';
        }

        if ($configuration && blank($configuration->p24_pos_id)) {
            $missing[] = 'POS ID';
        }

        if ($configuration && blank($configuration->p24_crc)) {
            $missing[] = 'CRC';
        }

        if ($configuration && blank($configuration->p24_api_key)) {
            $missing[] = 'API key';
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'payment' => 'Platnosci Przelewy24 nie sa skonfigurowane. Brakuje: '.implode(', ', $missing).'.',
            ]);
        }
    }

    private function ensureAccountConfigured(?ShootingRangePaymentAccount $account): void
    {
        $missing = [];

        if (! $account) {
            $missing[] = 'konto płatności strzelnicy';
        } elseif (! $account->p24_enabled) {
            $missing[] = 'włączenie płatności P24 dla strzelnicy';
        }

        if ($account && blank($account->p24_merchant_id)) {
            $missing[] = 'Merchant ID';
        }

        if ($account && blank($account->p24_pos_id)) {
            $missing[] = 'POS ID';
        }

        if ($account && blank($account->p24_crc)) {
            $missing[] = 'CRC';
        }

        if ($account && blank($account->p24_api_key)) {
            $missing[] = 'API key';
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'payment' => 'Płatności Przelewy24 dla strzelnicy nie są skonfigurowane. Brakuje: '.implode(', ', $missing).'.',
            ]);
        }
    }

    private function baseUrl(SystemConfiguration $configuration): string
    {
        return $this->baseUrlForSandbox((bool) $configuration->p24_sandbox);
    }

    private function baseUrlForSandbox(bool $sandbox): string
    {
        return $sandbox
            ? 'https://sandbox.przelewy24.pl'
            : 'https://secure.przelewy24.pl';
    }

    private function sign(array $data): string
    {
        return hash('sha384', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function testAccessMessage(Response $response): string
    {
        if ($response->successful() && (bool) data_get($response->json(), 'data')) {
            return 'Polaczenie z Przelewy24 dziala poprawnie.';
        }

        return match ($response->status()) {
            401 => 'Przelewy24 odrzucilo dane logowania. Sprawdz POS ID oraz API key.',
            400 => 'Przelewy24 odrzucilo zapytanie testowe. Sprawdz komplet danych.',
            default => 'Nie udalo sie potwierdzic polaczenia z Przelewy24. Status HTTP: '.$response->status().'.',
        };
    }
}
