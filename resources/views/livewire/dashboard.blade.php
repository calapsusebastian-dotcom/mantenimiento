<?php

use App\Enums\EquipmentStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Equipment;
use App\Models\MaintenancePlan;
use App\Models\WorkOrder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Dashboard')] class extends Component
{
    public function with(): array
    {
        $user = auth()->user();

        $openStatuses = [WorkOrderStatus::Pendiente, WorkOrderStatus::Asignada, WorkOrderStatus::EnProgreso];

        if ($user->isAdmin()) {
            return [
                'role' => 'admin',
                'equipmentTotal' => Equipment::count(),
                'equipmentByStatus' => Equipment::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
                'plansDue' => MaintenancePlan::where('active', true)->where('next_due_date', '<=', Carbon::today())->count(),
                'pending' => WorkOrder::where('status', WorkOrderStatus::Pendiente)->count(),
                'inProgress' => WorkOrder::where('status', WorkOrderStatus::EnProgreso)->count(),
                'overdue' => WorkOrder::whereIn('status', $openStatuses)->whereNotNull('scheduled_for')->where('scheduled_for', '<', Carbon::today())->count(),
                'completedThisMonth' => WorkOrder::where('status', WorkOrderStatus::Completada)->whereMonth('completed_at', now()->month)->whereYear('completed_at', now()->year)->count(),
                'recent' => WorkOrder::with('equipment')->latest()->take(8)->get(),
            ];
        }

        if ($user->isTecnico()) {
            return [
                'role' => 'tecnico',
                'myOpen' => WorkOrder::where('assigned_to', $user->id)->whereIn('status', $openStatuses)->count(),
                'unassigned' => WorkOrder::whereNull('assigned_to')->whereIn('status', [WorkOrderStatus::Pendiente])->count(),
                'completedThisMonth' => WorkOrder::where('assigned_to', $user->id)->where('status', WorkOrderStatus::Completada)->whereMonth('completed_at', now()->month)->whereYear('completed_at', now()->year)->count(),
                'recent' => WorkOrder::with('equipment')
                    ->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhereNull('assigned_to'))
                    ->latest()->take(8)->get(),
            ];
        }

        return [
            'role' => 'operador',
            'myTotal' => WorkOrder::where('reported_by', $user->id)->count(),
            'myOpen' => WorkOrder::where('reported_by', $user->id)->whereIn('status', $openStatuses)->count(),
            'myResolved' => WorkOrder::where('reported_by', $user->id)->where('status', WorkOrderStatus::Completada)->count(),
            'recent' => WorkOrder::with('equipment')->where('reported_by', $user->id)->latest()->take(8)->get(),
        ];
    }
}; ?>

<div x-on:livewire:navigated.window="$wire.$refresh()">
    <x-page-header title="Dashboard" />

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            @php
                $stat = fn (string $label, $value, string $icon, string $tint) => compact('label', 'value', 'icon', 'tint');

                $tints = [
                    'indigo' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400',
                    'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
                    'red' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
                    'green' => 'bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-400',
                ];
            @endphp

            @if ($role === 'admin')
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ([
                        $stat('Equipos registrados', $equipmentTotal, 'box', 'indigo'),
                        $stat('Órdenes pendientes', $pending, 'clipboard', 'indigo'),
                        $stat('En progreso', $inProgress, 'clock', 'amber'),
                        $stat('Vencidas', $overdue, 'alert', 'red'),
                        $stat('Completadas este mes', $completedThisMonth, 'check-circle', 'green'),
                        $stat('Planes preventivos vencidos', $plansDue, 'calendar', 'amber'),
                    ] as $card)
                        <div class="flex items-center gap-4 bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl p-5">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $tints[$card['tint']] }}">
                                <x-icon :name="$card['icon']" class="w-5 h-5" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $card['label'] }}</p>
                                <p class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $card['value'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $equipmentByStatus['en_mantenimiento'] ?? 0 }} equipos en mantenimiento ·
                    {{ $equipmentByStatus['fuera_servicio'] ?? 0 }} fuera de servicio
                </p>

                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    <a href="{{ route('equipment.index') }}" wire:navigate class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Gestionar equipos →</a>
                    <a href="{{ route('maintenance-plans.index') }}" wire:navigate class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Planes preventivos →</a>
                    <a href="{{ route('work-orders.index') }}" wire:navigate class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Todas las órdenes →</a>
                </div>
            @elseif ($role === 'tecnico')
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    @foreach ([
                        $stat('Mis órdenes abiertas', $myOpen, 'clipboard', 'indigo'),
                        $stat('Sin asignar disponibles', $unassigned, 'alert', 'amber'),
                        $stat('Completadas este mes', $completedThisMonth, 'check-circle', 'green'),
                    ] as $card)
                        <div class="flex items-center gap-4 bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl p-5">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $tints[$card['tint']] }}">
                                <x-icon :name="$card['icon']" class="w-5 h-5" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $card['label'] }}</p>
                                <p class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $card['value'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <a href="{{ route('work-orders.index') }}" wire:navigate class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Ver mis órdenes de trabajo →</a>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    @foreach ([
                        $stat('Reportes enviados', $myTotal, 'clipboard', 'indigo'),
                        $stat('En proceso', $myOpen, 'clock', 'amber'),
                        $stat('Resueltos', $myResolved, 'check-circle', 'green'),
                    ] as $card)
                        <div class="flex items-center gap-4 bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl p-5">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $tints[$card['tint']] }}">
                                <x-icon :name="$card['icon']" class="w-5 h-5" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $card['label'] }}</p>
                                <p class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $card['value'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <a href="{{ route('work-orders.report') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                    <x-icon name="plus" class="w-4 h-4" /> Reportar una nueva falla
                </a>
            @endif

            <div class="bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Órdenes recientes</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($recent as $workOrder)
                            <tr wire:key="recent-{{ $workOrder->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-5 py-3.5 text-sm">
                                    <a href="{{ route('work-orders.show', $workOrder) }}" wire:navigate class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                        #{{ $workOrder->id }} {{ $workOrder->title }}
                                    </a>
                                </td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300">{{ $workOrder->equipment->name }}</td>
                                <td class="px-5 py-3.5 text-sm"><x-badge :color="$workOrder->status->color()">{{ $workOrder->status->label() }}</x-badge></td>
                                <td class="px-5 py-3.5 text-sm text-gray-400 dark:text-gray-500 text-right">{{ $workOrder->created_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400" colspan="4">Todavía no hay órdenes de trabajo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
