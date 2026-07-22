<?php

use App\Models\Equipment;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Historial de equipo')] class extends Component
{
    public Equipment $equipment;

    public function mount(Equipment $equipment): void
    {
        Gate::authorize('manage-equipment');

        $this->equipment = $equipment;
    }

    public function with(): array
    {
        $workOrders = $this->equipment->workOrders()->with(['technician', 'reporter'])->latest()->get();

        return [
            'workOrders' => $workOrders,
            'plans' => $this->equipment->maintenancePlans()->latest()->get(),
            'completedCount' => $workOrders->where('status', \App\Enums\WorkOrderStatus::Completada)->count(),
            'openCount' => $workOrders->whereIn('status', [
                \App\Enums\WorkOrderStatus::Pendiente,
                \App\Enums\WorkOrderStatus::Asignada,
                \App\Enums\WorkOrderStatus::EnProgreso,
            ])->count(),
        ];
    }
}; ?>

<div x-on:livewire:navigated.window="$wire.$refresh()">
    <x-page-header :title="$equipment->name">
        <x-slot:actions>
            <a href="{{ route('equipment.index', ['edit' => $equipment->id]) }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                Editar equipo
            </a>
            <x-back-link :href="route('equipment.index')" />
        </x-slot:actions>
    </x-page-header>

    <div class="py-10">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl p-6 sm:p-8">
                <div class="flex flex-wrap items-center gap-2 mb-5">
                    <x-badge :color="$equipment->status->color()">{{ $equipment->status->label() }}</x-badge>
                    @unless ($equipment->active)
                        <x-badge color="slate">Inactivo</x-badge>
                    @endunless
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Código</dt>
                        <dd class="text-gray-900 dark:text-gray-100 font-mono">{{ $equipment->code }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Área</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $equipment->location ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Categoría</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $equipment->category ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Marca / Modelo</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ trim(($equipment->brand ?: '').' '.($equipment->model ?: '')) ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">N° de serie</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $equipment->serial_number ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Fecha de compra</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $equipment->purchase_date?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                </dl>

                @if ($equipment->notes)
                    <div class="mt-5 pt-5 border-t border-gray-100 dark:border-gray-800">
                        <dt class="text-gray-500 dark:text-gray-400 text-sm">Notas</dt>
                        <dd class="text-gray-900 dark:text-gray-100 mt-1 text-sm">{{ $equipment->notes }}</dd>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Órdenes totales</p>
                    <p class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $workOrders->count() }}</p>
                </div>
                <div class="bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Abiertas</p>
                    <p class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $openCount }}</p>
                </div>
                <div class="bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Completadas</p>
                    <p class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $completedCount }}</p>
                </div>
            </div>

            @if ($plans->isNotEmpty())
                <div class="bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Planes de mantenimiento preventivo</h3>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($plans as $plan)
                            <li wire:key="plan-{{ $plan->id }}" class="px-5 py-3 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <a href="{{ route('maintenance-plans.index', ['edit' => $plan->id]) }}" wire:navigate class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                        {{ $plan->name }}
                                    </a>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Cada {{ $plan->frequency_days }} días · próximo: {{ $plan->next_due_date->format('d/m/Y') }}</p>
                                </div>
                                <x-badge :color="$plan->active ? 'green' : 'slate'">{{ $plan->active ? 'Activo' : 'Inactivo' }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Historial de órdenes de trabajo</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50/60 dark:bg-gray-800/40">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Orden</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Tipo</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Estado</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Técnico</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($workOrders as $workOrder)
                            <tr wire:key="wo-{{ $workOrder->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-5 py-3.5 text-sm">
                                    <a href="{{ route('work-orders.show', $workOrder) }}" wire:navigate class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                        #{{ $workOrder->id }} {{ $workOrder->title }}
                                    </a>
                                </td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300">{{ $workOrder->type->label() }}</td>
                                <td class="px-5 py-3.5 text-sm"><x-badge :color="$workOrder->status->color()">{{ $workOrder->status->label() }}</x-badge></td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300">{{ $workOrder->technician?->name ?? '—' }}</td>
                                <td class="px-5 py-3.5 text-sm text-gray-400 dark:text-gray-500">{{ $workOrder->created_at->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Este equipo todavía no tiene órdenes de trabajo registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
