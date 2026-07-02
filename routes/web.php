<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\MessageController;
// use App\Http\Controllers\EmployeeDashboardController;
// use App\Http\Controllers\ClientDashboardController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservationsController;
use App\Http\Controllers\ShootingRangeGalleryController;
use App\Http\Controllers\ShootingRangeInvitationController;
use App\Http\Controllers\ShootingRangePackageController;
use App\Http\Controllers\ShootingsController;
use App\Http\Controllers\SystemConfigurationController;
use Illuminate\Support\Facades\Route;

// -----------------------------------------------------------------------------
// STRONY PUBLICZNE
// -----------------------------------------------------------------------------
Route::get('/', [ShootingsController::class, 'index'])->name('index');
Route::get('/lista-strzelnic', [ShootingsController::class, 'listAll'])->name('shootings.list');
Route::get('/mapa-strzelnic', [ShootingsController::class, 'maps'])->name('maps'); // strzelnice na mapie
Route::get('/kontakt', [SystemConfigurationController::class, 'contact'])
    ->name('contact');
Route::post('/kontakt', [SystemConfigurationController::class, 'sendContact'])
    ->middleware('throttle:5,1')
    ->name('contact.send');
Route::get('/zaproszenia/{token}', [ShootingRangeInvitationController::class, 'accept'])
    ->name('shooting.invitations.accept');
// Route::get('/s/{endpoint}', [ShootingsController::class, 'publicShow'])
// ->name('shooting.public.show');

Route::get('/icon-preview', function () {
    return view('icon-preview');
});

// -----------------------------------------------------------------------------
// NOWA STRZELNICA (CREATE / STORE)
// -----------------------------------------------------------------------------
Route::get('/nowa-strzelnica', [ShootingsController::class, 'create'])->name('shootings.new');
Route::post('/nowa-strzelnica', [ShootingsController::class, 'store'])->name('shootings.store');

// -----------------------------------------------------------------------------
// WIZARD: DODAWANIE STRZELNICY (KROKI 1-3) + RESET/ANULUJ
// -----------------------------------------------------------------------------
Route::prefix('strzelnica/dodaj')->group(function () {

    // KROK 1
    Route::get('krok-1', [ShootingsController::class, 'step1'])->name('range.step1');
    Route::post('krok-1', [ShootingsController::class, 'postStep1'])->name('range.step1.post');

    // KROK 2 (middleware sprawdza, czy krok 1 został wykonany)
    Route::get('krok-2', [ShootingsController::class, 'step2'])
        ->middleware('range.step:2')
        ->name('range.step2');
    Route::post('krok-2', [ShootingsController::class, 'postStep2'])
        ->middleware('range.step:2')
        ->name('range.step2.post');

    // KROK 3
    Route::get('krok-3', [ShootingsController::class, 'step3'])
        ->middleware('range.step:3')
        ->name('range.step3');
    Route::post('zapisz', [ShootingsController::class, 'storeGuest'])
        ->middleware('range.step:3')
        ->name('range.store');

    // RESET FORMULARZA (rozpoczęcie nowego formularza)
    Route::post('reset', function () {
        session()->forget('shooting_range_form');

        return redirect()->route('range.step1')
            ->with('success', 'Rozpoczęto nowy formularz.');
    })->name('range.reset');

    // ANULUJ FORMULARZ (po prostu usuwa sesję i wraca do krok 1)
    Route::post('anuluj', function () {
        session()->forget('shooting_range_form');

        return redirect()->route('range.step1');
    })->name('range.cancel');
});

Route::get('/{endpoint}/galeria', [
    ShootingRangeGalleryController::class,
    'publicGallery',
])->name('shooting.gallery.public');

Route::middleware('auth')->group(function () {
    Route::get('/strzelnice/{shootingRange}/galeria', [
        ShootingRangeGalleryController::class,
        'index',
    ])->name('shooting.gallery.index');

    Route::post('/strzelnice/{shootingRange}/galeria/zdjecia', [
        ShootingRangeGalleryController::class,
        'store',
    ])->name('shooting.gallery.photos.store');

    Route::patch('/strzelnice/{shootingRange}/galeria/zdjecia/{photo}', [
        ShootingRangeGalleryController::class,
        'updatePhoto',
    ])->name('shooting.gallery.photos.update');

    Route::delete('/strzelnice/{shootingRange}/galeria/zdjecia/{photo}', [
        ShootingRangeGalleryController::class,
        'destroyPhoto',
    ])->name('shooting.gallery.photos.destroy');

    Route::patch('/strzelnice/{shootingRange}/galeria/ustawienia', [
        ShootingRangeGalleryController::class,
        'updateSettings',
    ])->name('shooting.gallery.settings.update');
});

// -----------------------------------------------------------------------------
// DASHBOARD (auth + verified + approved)
// -----------------------------------------------------------------------------
Route::middleware(['auth', 'verified', 'approved'])->group(function () {

    // PROFIL
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/rezerwacje', [ReservationsController::class, 'store'])
        ->name('rezerwacje.store');
    Route::get('/moje-rezerwacje', [ReservationsController::class, 'myReservations'])
        ->name('rezerwacje.mine');
    Route::get('/moje-rezerwacje/{reservation}/edytuj', [ReservationsController::class, 'editByCustomer'])
        ->name('rezerwacje.edit');
    Route::patch('/moje-rezerwacje/{reservation}', [ReservationsController::class, 'updateByCustomer'])
        ->name('rezerwacje.update');

    Route::middleware('role:superadmin,admin,pracownik')->group(function () {
        Route::get('/panel/rezerwacje', [ReservationsController::class, 'adminCalendar'])
            ->name('admin.reservations.calendar');
        Route::get('/panel/rezerwacje/archiwum', [ReservationsController::class, 'adminArchive'])
            ->name('admin.reservations.archive');
        Route::get('/panel/rezerwacje/uzytkownicy/szukaj', [ReservationsController::class, 'searchCustomers'])
            ->name('admin.reservations.customers.search');
        Route::post('/panel/rezerwacje', [ReservationsController::class, 'storeByStaff'])
            ->name('admin.reservations.store');
        Route::patch('/panel/rezerwacje/{reservation}', [ReservationsController::class, 'updateByStaff'])
            ->name('admin.reservations.update');
        Route::patch('/panel/rezerwacje/{reservation}/status', [ReservationsController::class, 'updateStatus'])
            ->name('admin.reservations.status');
    });

    // ROLE DASHBOARD: SUPERADMIN
    Route::middleware('role:superadmin')->group(function () {
        // Route::get('/superAdmin', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/superAdmin', function () {
            return redirect()->route('index');
        })->name('admin.dashboard');
        Route::get('/uzytkownicy', [AdminDashboardController::class, 'userList'])->name('admin.users');
        Route::patch('/strony/{page}/status', [PageController::class, 'toggleStatus'])
            ->name('admin.pages.toggle-status');
        Route::resource('/strony', PageController::class)
            ->except(['show'])
            ->parameters(['strony' => 'page'])
            ->names('admin.pages');
        Route::get('/konfiguracja-systemu', [SystemConfigurationController::class, 'index'])
            ->name('admin.system-configurations.index');
        Route::get('/konfiguracja-systemu/{systemConfiguration}', [SystemConfigurationController::class, 'show'])
            ->name('admin.system-configurations.show');
        Route::get('/konfiguracja-systemu/{systemConfiguration}/edytuj', [SystemConfigurationController::class, 'edit'])
            ->name('admin.system-configurations.edit');
        Route::patch('/konfiguracja-systemu/{systemConfiguration}', [SystemConfigurationController::class, 'update'])
            ->name('admin.system-configurations.update');
        Route::patch('/konfiguracja-systemu/{systemConfiguration}/przywroc', [SystemConfigurationController::class, 'restore'])
            ->name('admin.system-configurations.restore');
        Route::get('/strzelnice', [ShootingsController::class, 'listSuperAdmin'])->name('admin.shootings'); // dla niezalogowanych ->listAll

        Route::get('/strzelnice/dodaj', [ShootingsController::class, 'createSuperAdmin'])->name('admin.shootings.create');
        Route::post('/strzelnice', [ShootingsController::class, 'storeSuperAdmin'])->name('admin.shootings.store');
        Route::get('/strzelnice/{shootingRange}/edytuj', [ShootingsController::class, 'editSuperAdmin'])->name('admin.shootings.edit');
        Route::patch('/strzelnice/{shootingRange}', [ShootingsController::class, 'updateSuperAdmin'])->name('admin.shootings.update');

        Route::get('/strzelnice/{id}/pakiety', [ShootingsController::class, 'packages'])->name('admin.shootings.packages');
        Route::patch('/strzelnice/{shootingRange}/widocznosc', [ShootingsController::class, 'toggleVisibility'])
            ->name('admin.shootings.visibility');
        Route::delete('/strzelnice/{shootingRange}', [ShootingsController::class, 'destroyShootingRange'])
            ->name('admin.shootings.destroy');
        Route::post('/strzelnice/usun-zaznaczone', [ShootingsController::class, 'bulkDestroyShootingRanges'])
            ->name('admin.shootings.bulk-destroy');

        Route::get('/strzelnice/{shootingRange}/przedzialy', [ShootingsController::class, 'intervals'])
            ->name('admin.shootings.intervals');
        Route::post('/strzelnice/{shootingRange}/przedzialy', [ShootingsController::class, 'storeInterval'])
            ->name('admin.shootings.intervals.store');
        Route::get('/strzelnice/{shootingRange}/przedzialy/{interval}/edytuj', [ShootingsController::class, 'editInterval'])
            ->name('admin.shootings.intervals.edit');
        Route::patch('/strzelnice/{shootingRange}/przedzialy/{interval}', [ShootingsController::class, 'updateInterval'])
            ->name('admin.shootings.intervals.update');
        Route::delete('/strzelnice/{shootingRange}/przedzialy/{interval}', [ShootingsController::class, 'deleteInterval'])
            ->name('admin.shootings.intervals.destroy');
        Route::post('/strzelnice/import', [ShootingsController::class, 'import'])->name('shooting-ranges.import');

        Route::get('/pakiety', [ShootingRangePackageController::class, 'index'])->name('admin.packages.index');
        Route::post('/pakiety', [ShootingRangePackageController::class, 'store'])->name('admin.packages.store');
        Route::delete('/pakiety/{package}', [ShootingRangePackageController::class, 'destroy'])->name('admin.packages.destroy');

        Route::patch('/user/{id}/activate', [UserController::class, 'userActivate']);
        Route::patch('/user/{id}/deactivate', [UserController::class, 'userDeactivate']);
    });

    // ROLE DASHBOARD: ADMIN
    Route::middleware('role:admin')->group(function () {
        // Route::get('/admin', [EmployeeDashboardController::class, 'index'])->name('employee.dashboard');
        Route::get('/admin', function () {
            return redirect()->route('index');
        })->name('employee.dashboard');
        // EDYCJA STRZELNICY - dane podstawowe
        Route::get('/strzelnica/edytuj', [ShootingsController::class, 'edit'])->name('shooting.edit');
        Route::post('/strzelnica/zapisz/{shootingRange}', [ShootingsController::class, 'update'])->name('shooting.update');
        // EDYCJA STRZELNICY - godziny otwarcie
        Route::get('/strzelnica/edytuj-godziny', [ShootingsController::class, 'editTime'])->name('shooting.time.edit');
        Route::post('/strzelnica/zapisz-godziny/{shootingRange}', [ShootingsController::class, 'updateTime'])->name('shooting.time.update');
        // EDYCJA STRZELNICY - dni wolne
        Route::post('/shooting-ranges/{shootingRange}/holidays', [ShootingsController::class, 'addHoliday'])->name('shooting-ranges.holidays.store');
        Route::delete('/shooting-ranges/{shootingRange}/holidays/{holiday}', [ShootingsController::class, 'deleteHoliday'])->name('shooting-ranges.holidays.destroy');
        // EDYCJA STRZELNICY - osie
        Route::get('/osie/edytuj', [ShootingsController::class, 'editLanes'])->name('shooting.lanes.edit');
        Route::post('/osie/zapisz/{shootingRange}', [ShootingsController::class, 'updateLane'])->name('shooting.lanes.update');
        Route::delete('/osie/{id}', [ShootingsController::class, 'destroyLane'])->name('shooting.lanes.destroy');
        Route::post('/osie/edytuj', [ShootingsController::class, 'storeLane'])->name('shooting.lanes.store');
        // ZARZĄDZANIE REZERWACJAMI
        Route::get('/strzelnica/zaproszenia', [ShootingRangeInvitationController::class, 'index'])
            ->name('shooting.invitations.index');

        Route::post('/strzelnica/zaproszenia', [ShootingRangeInvitationController::class, 'store'])
            ->name('shooting.invitations.store');

        Route::post('/strzelnica/zaproszenia/import', [ShootingRangeInvitationController::class, 'import'])
            ->name('shooting.invitations.import');

        Route::patch('/strzelnica/zaproszenia/{invitation}/link', [ShootingRangeInvitationController::class, 'generateLink'])
            ->name('shooting.invitations.link');

        Route::post('/strzelnica/zaproszenia/{invitation}/send', [ShootingRangeInvitationController::class, 'send'])
            ->name('shooting.invitations.send');

        Route::patch('/strzelnica/zaproszenia/{invitation}/revoke', [ShootingRangeInvitationController::class, 'revoke'])
            ->name('shooting.invitations.revoke');

        Route::post('/strzelnica/zaproszenia/bulk', [ShootingRangeInvitationController::class, 'bulk'])
            ->name('shooting.invitations.bulk');

    });

    // ROLE DASHBOARD: PRACOWNIK
    Route::middleware('role:pracownik')->group(function () {
        Route::get('/pracownik', function () {
            return redirect()->route('admin.reservations.calendar');
        })->name('worker.dashboard');
        // Route::get('/admin', function () {
        //    return redirect()->route('index');
        // })->name('employee.dashboard');
        // Route::get('/strzelnica/edytuj', [ShootingsController::class, 'edit'])->name('shooting.edit');
    });

    // ROLE DASHBOARD: KLIENT
    Route::middleware('role:klient')->group(function () {
        // Route::get('/klient', [ClientDashboardController::class, 'index'])->name('client.dashboard');
        Route::get('/klient', function () {
            return redirect()->route('index');
        })->name('client.dashboard');
    });

    // WIADOMOŚCI
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
});

// -----------------------------------------------------------------------------
// DASHBOARD GŁÓWNY
// -----------------------------------------------------------------------------
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/rezerwacje/{id}/dostepnosc', [ReservationsController::class, 'availability'])
    ->whereNumber('id')
    ->name('rezerwacje.availability');

Route::get('/rezerwacje/{id}', [ReservationsController::class, 'callendar'])
    ->whereNumber('id')
    ->name('rezerwacje.calendar');

// -----------------------------------------------------------------------------
// AUTH
// -----------------------------------------------------------------------------
require __DIR__.'/auth.php';

Route::get('/{endpoint}', [PageController::class, 'showPublicOrShooting'])
    ->where('endpoint', '[a-z0-9_]+')
    ->name('shooting.public.show');
