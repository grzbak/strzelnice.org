<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\ShootingRange;
use App\Models\ShootingRangePackage;
use Illuminate\Http\Request;

class ShootingRangePackageController extends Controller
{
    public function index()
    {
        $packages = ShootingRangePackage::with('shootingRange')
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get();

        $shootingRanges = ShootingRange::orderBy('name')->get();

        $activePackageId = $packages->first()?->id;

        return view('superAdmin.packages', compact(
            'packages',
            'shootingRanges',
            'activePackageId'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'shooting_range_id' => ['nullable', 'integer', 'min:0'],
            'period_months' => ['required', 'integer', 'min:1'],
            'value' => ['required', 'numeric', 'min:0'],
            'valid_from' => ['required', 'date'],
        ]);

        $shootingRangeId = (int) ($validated['shooting_range_id'] ?? 0);

        if ($shootingRangeId > 0 && ! ShootingRange::whereKey($shootingRangeId)->exists()) {
            return back()
                ->withErrors(['shooting_range_id' => 'Wybrana strzelnica nie istnieje.'])
                ->withInput();
        }

        ShootingRangePackage::create([
            'shooting_range_id' => $shootingRangeId,
            'period_months' => $validated['period_months'],
            'value' => $validated['value'],
            'valid_from' => $validated['valid_from'],
        ]);

        return back()->with('success', 'Dodano pakiet.');
    }

    public function destroy(ShootingRangePackage $package)
    {
        $package->delete();

        return back()->with('success', 'Usunięto pakiet.');
    }
}
