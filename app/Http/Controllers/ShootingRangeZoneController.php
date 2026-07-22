<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ShootingRangeZoneController extends Controller
{
    public function show(): View
    {
        return view('shooting-range-zone');
    }
}
