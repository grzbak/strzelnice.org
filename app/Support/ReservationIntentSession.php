<?php

namespace App\Support;

use Illuminate\Http\Request;

class ReservationIntentSession
{
    private const KEY = 'reservation_intent';

    private const TTL_MINUTES = 30;

    public static function store(Request $request, array $intent): void
    {
        $request->session()->put(self::KEY, [
            'shooting_range_id' => (int) $intent['shooting_range_id'],
            'date' => $intent['date'],
            'lane_id' => isset($intent['lane_id']) ? (int) $intent['lane_id'] : null,
            'start_time' => $intent['start_time'] ?? null,
            'end_time' => $intent['end_time'] ?? null,
            'created_at' => now()->timestamp,
        ]);
    }

    public static function redirectUrl(Request $request): ?string
    {
        $intent = self::valid($request);

        if (! $intent) {
            return null;
        }

        $query = [
            'date' => $intent['date'],
        ];

        if (! empty($intent['lane_id']) && ! empty($intent['start_time'])) {
            $query['lane'] = $intent['lane_id'];
            $query['start'] = $intent['start_time'];
        }

        return route(
            'rezerwacje.calendar',
            ['id' => $intent['shooting_range_id']],
            false
        )
            .'?'.http_build_query($query);
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget(self::KEY);
    }

    public static function valid(Request $request): ?array
    {
        $intent = $request->session()->get(self::KEY);

        if (! is_array($intent) || empty($intent['created_at'])) {
            self::forget($request);

            return null;
        }

        if (now()->timestamp - (int) $intent['created_at'] > self::TTL_MINUTES * 60) {
            self::forget($request);

            return null;
        }

        return $intent;
    }
}
