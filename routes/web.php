<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('dashboard', 'dashboard')->name('dashboard');

    Route::middleware('role:admin')->group(function () {
        Volt::route('equipos', 'equipment.index')->name('equipment.index');
        Volt::route('equipos/{equipment}', 'equipment.show')->name('equipment.show');
        Volt::route('planes-mantenimiento', 'maintenance-plans.index')->name('maintenance-plans.index');
        Volt::route('usuarios', 'users.index')->name('users.index');
    });

    Volt::route('ordenes', 'work-orders.index')->name('work-orders.index');
    Volt::route('ordenes/reportar', 'work-orders.report')->name('work-orders.report');
    Volt::route('ordenes/{workOrder}', 'work-orders.show')->name('work-orders.show');
    Volt::route('calendario', 'calendar.index')->name('calendar.index');
    Volt::route('bitacora', 'checkouts.index')->name('checkouts.index');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
