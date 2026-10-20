<?php

namespace App\Http\Controllers;

use App\Models\ShootingRange;
use App\Models\ShootingRangePackage;
use App\Services\ShootingRangeSubscriptionService;
use Illuminate\Http\Request;

class ShootingRangePackageController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:period_months,value,valid_from,shooting_range'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50'],
            'show_archived' => ['nullable', 'boolean'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $sort = $validated['sort'] ?? 'valid_from';
        $direction = $validated['direction'] ?? 'desc';
        $perPage = (int) ($validated['per_page'] ?? 20);
        $showArchived = (bool) ($validated['show_archived'] ?? false);
        $today = now()->toDateString();

        $allPackages = ShootingRangePackage::query()
            ->select(['id', 'shooting_range_id', 'period_months', 'valid_from'])
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get();

        $activePackageIds = $allPackages
            ->filter(fn (ShootingRangePackage $package) => $package->valid_from->toDateString() <= $today)
            ->groupBy(fn (ShootingRangePackage $package) => $this->packageGroupKey($package))
            ->map(fn ($group) => $group->first()->id)
            ->values();

        $packageStatuses = $allPackages
            ->mapWithKeys(function (ShootingRangePackage $package) use ($activePackageIds, $today) {
                if ($package->valid_from->toDateString() > $today) {
                    return [$package->id => 'waiting'];
                }

                return [
                    $package->id => $activePackageIds->contains($package->id)
                        ? 'active'
                        : 'archived',
                ];
            });

        $query = ShootingRangePackage::query()->with('shootingRange');

        if (! $showArchived) {
            $query->where(function ($query) use ($activePackageIds, $today) {
                $query
                    ->whereIn('id', $activePackageIds)
                    ->orWhereDate('valid_from', '>', $today);
            });
        }

        foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $query->where(function ($query) use ($term) {
                $query
                    ->where('period_months', 'like', "%{$term}%")
                    ->orWhere('value', 'like', "%{$term}%")
                    ->orWhere('valid_from', 'like', "%{$term}%")
                    ->orWhereHas('shootingRange', function ($query) use ($term) {
                        $query
                            ->where('name', 'like', "%{$term}%")
                            ->orWhere('town', 'like', "%{$term}%")
                            ->orWhere('voivodeship', 'like', "%{$term}%");
                    });
            });
        }

        match ($sort) {
            'period_months' => $query->orderBy('period_months', $direction)->orderByDesc('valid_from')->orderByDesc('id'),
            'value' => $query->orderBy('value', $direction)->orderByDesc('valid_from')->orderByDesc('id'),
            'shooting_range' => $query
                ->leftJoin('shooting_ranges', 'shooting_range_packages.shooting_range_id', '=', 'shooting_ranges.id')
                ->select('shooting_range_packages.*')
                ->orderByRaw('CASE WHEN shooting_range_packages.shooting_range_id = 0 THEN 0 ELSE 1 END')
                ->orderBy('shooting_ranges.name', $direction)
                ->orderByDesc('shooting_range_packages.valid_from')
                ->orderByDesc('shooting_range_packages.id'),
            default => $query->orderBy('valid_from', $direction)->orderBy('id', $direction),
        };

        $packages = $query->paginate($perPage)->withQueryString();
        $shootingRanges = ShootingRange::orderBy('name')->get();

        return view('superAdmin.packages', compact(
            'packages',
            'shootingRanges',
            'packageStatuses',
            'search',
            'sort',
            'direction',
            'perPage',
            'showArchived'
        ));
    }

    public function store(Request $request, ShootingRangeSubscriptionService $subscriptions)
    {
        $validated = $request->validate([
            'shooting_range_id' => ['nullable', 'integer', 'min:0'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['nullable', 'in:standard,special,one_time,discount'],
            'period_months' => ['required', 'integer', 'min:1'],
            'bonus_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'value' => ['required', 'numeric', 'min:0'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'requires_superadmin_approval' => ['nullable', 'boolean'],
        ]);

        $shootingRangeId = (int) ($validated['shooting_range_id'] ?? 0);

        if ($shootingRangeId > 0 && ! ShootingRange::whereKey($shootingRangeId)->exists()) {
            return back()
                ->withErrors(['shooting_range_id' => 'Wybrana strzelnica nie istnieje.'])
                ->withInput();
        }

        $package = ShootingRangePackage::create([
            'shooting_range_id' => $shootingRangeId,
            'name' => $validated['name'] ?: $validated['period_months'].' mies.',
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'] ?? 'standard',
            'period_months' => $validated['period_months'],
            'bonus_months' => $validated['bonus_months'] ?? 0,
            'value' => $validated['value'],
            'price_gross' => $validated['value'],
            'vat_rate' => $validated['vat_rate'] ?? 23,
            'currency' => 'PLN',
            'valid_from' => $validated['valid_from'],
            'valid_to' => $validated['valid_to'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'is_public' => $request->boolean('is_public', true),
            'requires_superadmin_approval' => $request->boolean('requires_superadmin_approval'),
            'created_by_user_id' => $request->user()?->id,
        ]);

        $cancelledOrders = 0;

        if ($package->is_active && $package->valid_from->toDateString() <= now()->toDateString()) {
            ShootingRangePackage::query()
                ->where('id', '!=', $package->id)
                ->where('shooting_range_id', $package->shooting_range_id)
                ->where('period_months', $package->period_months)
                ->whereDate('valid_from', '<=', now()->toDateString())
                ->get()
                ->each(function (ShootingRangePackage $oldPackage) use (&$cancelledOrders, $subscriptions, $request): void {
                    $cancelledOrders += $subscriptions->cancelUnpaidOrdersForPackage($oldPackage, $request->user());
                });
        }

        return back()->with(
            'success',
            $cancelledOrders > 0
                ? "Dodano pakiet i anulowano oczekujące zamówienia ze starszych cenników: {$cancelledOrders}."
                : 'Dodano pakiet.'
        );
    }

    public function destroy(ShootingRangePackage $package, ShootingRangeSubscriptionService $subscriptions)
    {
        $cancelledOrders = $subscriptions->cancelUnpaidOrdersForPackage($package, request()->user());

        $package->delete();

        return back()->with(
            'success',
            $cancelledOrders > 0
                ? "Usunięto pakiet i anulowano oczekujące zamówienia: {$cancelledOrders}."
                : 'Usunięto pakiet.'
        );
    }

    private function packageGroupKey(ShootingRangePackage $package): string
    {
        return (int) $package->shooting_range_id.'-'.(int) $package->period_months;
    }
}
