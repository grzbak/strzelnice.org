<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShootingRangeInterval;
use Illuminate\Http\Request;

class IntervalController extends Controller
{
    public function index()
    {
        return ShootingRangeInterval::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'shooting_range_id' => 'required',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if (ShootingRangeInterval::hasOverlap(
            $request->start_date,
            $request->end_date,
            $request->shooting_range_id
        )) {
            return response()->json([
                'error' => 'Zakres dat nachodzi na istniejący',
            ], 422);
        }

        $interval = ShootingRangeInterval::create($request->all());

        return response()->json($interval);
    }

    public function monthly(Request $request)
    {
        return ShootingRangeInterval::monthlyReport(
            $request->year,
            $request->month
        );
    }

    public function yearly(Request $request)
    {
        return ShootingRangeInterval::yearlyReport(
            $request->year
        );
    }
}
