<?php

namespace App\Services;

use App\Mail\ReservationPaymentNotificationMail;
use App\Models\Lane;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationPricePromotion;
use App\Models\ReservationPriceRule;
use App\Models\ShootingRange;
use App\Models\ShootingRangePaymentAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReservationPaymentService
{
    public function quote(
        ShootingRange $shootingRange,
        Lane $lane,
        Carbon $start,
        Carbon $end,
        int $positionsCount,
        bool $isDynamicShooting
    ): array {
        $rule = $this->matchingRule($shootingRange, $lane, $start, $end);

        if (! $rule) {
            throw ValidationException::withMessages([
                'payment' => 'Nie można wyliczyć ceny rezerwacji. Skontaktuj się ze strzelnicą.',
            ]);
        }

        $rate = $isDynamicShooting && $rule->dynamic_price_per_position_30min
            ? (float) $rule->dynamic_price_per_position_30min
            : (float) $rule->price_per_position_30min;

        $blocks = max(1, (int) ceil($start->diffInMinutes($end) / 30));
        $base = round($positionsCount * $blocks * $rate, 2);
        $promotion = $this->matchingPromotion($shootingRange, $lane, $start, $end);
        $discount = 0.0;

        if ($promotion) {
            $discount = $promotion->discount_type === ReservationPricePromotion::TYPE_PERCENT
                ? round($base * ((float) $promotion->discount_value / 100), 2)
                : (float) $promotion->discount_value;
        }

        $total = max(0, round($base - $discount, 2));

        if ($total <= 0) {
            throw ValidationException::withMessages([
                'payment' => 'Cena rezerwacji musi być większa od 0 zł.',
            ]);
        }

        return [
            'amount' => $total,
            'amount_minor' => (int) round($total * 100),
            'currency' => $rule->currency,
            'blocks' => $blocks,
            'rate' => $rate,
            'base' => $base,
            'discount' => $discount,
            'rule' => $rule,
            'promotion' => $promotion,
        ];
    }

    public function createOnlinePayment(Reservation $reservation, ShootingRangePaymentAccount $account): Payment
    {
        $reservation->loadMissing(['shootingRange', 'lane']);

        $payment = $reservation->payments()->create([
            'provider' => 'przelewy24',
            'method' => 'p24',
            'status' => Payment::STATUS_CREATED,
            'amount' => $reservation->payment_amount,
            'amount_minor' => (int) round((float) $reservation->payment_amount * 100),
            'currency' => $reservation->payment_currency,
            'description' => 'Rezerwacja '.$reservation->shootingRange?->name.' '.$reservation->start?->format('Y-m-d H:i'),
            'payer_email' => $reservation->customer_email,
            'payer_name' => $reservation->customer_name,
            'p24_session_id' => 'res-'.$reservation->id.'-'.Str::random(24),
            'expires_at' => $reservation->payment_expires_at,
        ]);

        $payment->update([
            'url_return' => route('reservation-payments.return', $reservation),
            'url_status' => route('payments.p24.status'),
        ]);

        return $payment;
    }

    public function markPaid(Reservation $reservation, Payment $payment): void
    {
        if (
            $reservation->payment_status === Reservation::PAYMENT_PAID
            && $payment->status === Payment::STATUS_PAID
        ) {
            return;
        }

        $reservation->update([
            'status' => Reservation::STATUS_APPROVED,
            'payment_status' => Reservation::PAYMENT_PAID,
            'paid_at' => now(),
            'approved_at' => $reservation->approved_at ?? now(),
        ]);

        $payment->update([
            'status' => Payment::STATUS_PAID,
            'verified_at' => now(),
        ]);

        $this->sendPaymentMail($reservation, ReservationPaymentNotificationMail::EVENT_CONFIRMED);
    }

    public function expirePendingPayments(): int
    {
        $reservations = Reservation::query()
            ->with(['payments', 'shootingRange', 'lane'])
            ->where('payment_status', Reservation::PAYMENT_PENDING)
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<=', now())
            ->get();

        foreach ($reservations as $reservation) {
            $reservation->update([
                'status' => Reservation::STATUS_PAYMENT_EXPIRED,
                'payment_status' => Reservation::PAYMENT_EXPIRED,
                'cancellation_reason' => 'Płatność nie została opłacona w wymaganym czasie.',
            ]);

            $reservation->payments()
                ->whereIn('status', [Payment::STATUS_CREATED, Payment::STATUS_REGISTERED, Payment::STATUS_PENDING])
                ->update([
                    'status' => Payment::STATUS_EXPIRED,
                    'failed_at' => now(),
                ]);

            $this->sendPaymentMail($reservation, ReservationPaymentNotificationMail::EVENT_EXPIRED);
        }

        return $reservations->count();
    }

    public function sendPaymentMail(Reservation $reservation, string $event, ?string $paymentUrl = null): void
    {
        if ($reservation->customer_email) {
            Mail::to($reservation->customer_email)
                ->send(new ReservationPaymentNotificationMail($reservation, $event, 'customer', $paymentUrl));
        }

        $reservation->loadMissing('shootingRange.employees');

        $reservation->shootingRange?->employees
            ->filter(fn ($user) => (bool) $user->pivot?->receives_reservation_notifications)
            ->each(function ($user) use ($reservation, $event, $paymentUrl): void {
                Mail::to($user->email)
                    ->send(new ReservationPaymentNotificationMail($reservation, $event, 'staff', $paymentUrl));
            });
    }

    private function matchingRule(
        ShootingRange $shootingRange,
        Lane $lane,
        Carbon $start,
        Carbon $end
    ): ?ReservationPriceRule {
        return $shootingRange->reservationPriceRules()
            ->where('is_active', true)
            ->where(function ($query) use ($lane) {
                $query->whereNull('lane_id')
                    ->orWhere('lane_id', $lane->id);
            })
            ->where(function ($query) use ($start) {
                $query->whereNull('valid_from')
                    ->orWhereDate('valid_from', '<=', $start);
            })
            ->where(function ($query) use ($start) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $start);
            })
            ->get()
            ->filter(fn (ReservationPriceRule $rule) => $this->ruleMatchesTime($rule, $start, $end))
            ->sortBy([
                fn (ReservationPriceRule $rule) => $rule->lane_id ? 0 : 1,
                fn (ReservationPriceRule $rule) => $rule->priority,
                fn (ReservationPriceRule $rule) => $rule->id,
            ])
            ->first();
    }

    private function matchingPromotion(
        ShootingRange $shootingRange,
        Lane $lane,
        Carbon $start,
        Carbon $end
    ): ?ReservationPricePromotion {
        return $shootingRange->reservationPricePromotions()
            ->where('is_active', true)
            ->where(function ($query) use ($lane) {
                $query->whereNull('lane_id')
                    ->orWhere('lane_id', $lane->id);
            })
            ->where(function ($query) use ($start) {
                $query->whereNull('valid_from')
                    ->orWhereDate('valid_from', '<=', $start);
            })
            ->where(function ($query) use ($start) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $start);
            })
            ->get()
            ->filter(fn (ReservationPricePromotion $promotion) => $this->ruleMatchesTime($promotion, $start, $end))
            ->sortBy([
                fn (ReservationPricePromotion $promotion) => $promotion->lane_id ? 0 : 1,
                fn (ReservationPricePromotion $promotion) => $promotion->priority,
                fn (ReservationPricePromotion $promotion) => $promotion->id,
            ])
            ->first();
    }

    private function ruleMatchesTime($rule, Carbon $start, Carbon $end): bool
    {
        $weekdays = collect($rule->weekdays ?? [])->map(fn ($day) => (int) $day);

        if ($weekdays->isNotEmpty() && ! $weekdays->contains((int) $start->isoWeekday())) {
            return false;
        }

        if ($rule->time_from && $start->format('H:i') < substr($rule->time_from, 0, 5)) {
            return false;
        }

        if ($rule->time_to && $end->format('H:i') > substr($rule->time_to, 0, 5)) {
            return false;
        }

        return true;
    }
}
