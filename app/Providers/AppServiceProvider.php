<?php

namespace App\Providers;

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Solo el administrador gestiona el catálogo de equipos y los planes de mantenimiento preventivo.
        Gate::define('manage-equipment', fn (User $user) => $user->isAdmin());
        Gate::define('manage-maintenance-plans', fn (User $user) => $user->isAdmin());
        Gate::define('manage-users', fn (User $user) => $user->isAdmin());

        // Administrador y técnicos asignan órdenes de trabajo a técnicos.
        Gate::define('assign-work-orders', fn (User $user) => $user->isAdmin());

        // Cualquier usuario autenticado puede reportar una falla (correctivo).
        Gate::define('report-failure', fn (User $user) => true);

        // Una orden la puede actualizar el administrador o el técnico al que fue asignada.
        Gate::define('update-work-order', fn (User $user, WorkOrder $workOrder) => $user->isAdmin()
            || $workOrder->assigned_to === $user->id
        );
    }
}
