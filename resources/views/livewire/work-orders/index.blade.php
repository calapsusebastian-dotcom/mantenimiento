<?php

use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Órdenes de trabajo')] class extends Component
{
    use WithPagination;

    public string $statusFilter = '';
    public string $typeFilter = '';
    public string $priorityFilter = '';
    public bool $onlyMine = false;

    public array $assignSelection = [];

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPriorityFilter(): void
    {
        $this->resetPage();
    }

    public function updatingOnlyMine(): void
    {
        $this->resetPage();
    }

    public function assign(int $workOrderId): void
    {
        Gate::authorize('assign-work-orders');

        $technicianId = $this->assignSelection[$workOrderId] ?? null;

        if (! $technicianId) {
            return;
        }

        $workOrder = WorkOrder::findOrFail($workOrderId);
        $workOrder->update([
            'assigned_to' => $technicianId,
            'status' => WorkOrderStatus::Asignada,
        ]);

        $workOrder->logs()->create([
            'user_id' => auth()->id(),
            'comment' => 'Orden asignada a '.User::find($technicianId)->name.'.',
        ]);
    }

    public function take(int $workOrderId): void
    {
        $user = auth()->user();
        abort_unless($user->isTecnico(), 403);

        $workOrder = WorkOrder::findOrFail($workOrderId);
        abort_if($workOrder->assigned_to !== null, 403, 'Esta orden ya fue asignada.');

        $workOrder->update([
            'assigned_to' => $user->id,
            'status' => WorkOrderStatus::Asignada,
        ]);

        $workOrder->logs()->create([
            'user_id' => $user->id,
            'comment' => 'El técnico tomó esta orden de trabajo.',
        ]);
    }

    public function cancel(int $workOrderId): void
    {
        Gate::authorize('assign-work-orders');

        WorkOrder::findOrFail($workOrderId)->update(['status' => WorkOrderStatus::Cancelada]);
    }

    public function with(): array
    {
        $user = auth()->user();

        $query = WorkOrder::with(['equipment', 'technician', 'reporter']);

        if ($user->isOperador()) {
            $query->where('reported_by', $user->id);
        } elseif ($user->isTecnico()) {
            if ($this->onlyMine) {
                $query->where('assigned_to', $user->id);
            } else {
                $query->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)->orWhereNull('assigned_to');
                });
            }
        }

        $query->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->priorityFilter, fn ($q) => $q->where('priority', $this->priorityFilter));

        return [
            'workOrders' => $query->latest()->paginate(10),
            'statuses' => WorkOrderStatus::cases(),
            'types' => WorkOrderType::cases(),
            'priorities' => WorkOrderPriority::cases(),
            'technicians' => $user->isAdmin() ? User::where('role', UserRole::Tecnico)->orderBy('name')->get() : collect(),
            'isAdmin' => $user->isAdmin(),
            'isTecnico' => $user->isTecnico(),
        ];
    }
}; ?>

<div x-on:livewire:navigated.window="$wire.$refresh()">
    <x-page-header title="Órdenes de trabajo">
        <x-slot:actions>
            <a href="{{ route('work-orders.report') }}" wire:navigate
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-sm text-white shadow-sm hover:bg-indigo-500 transition ease-in-out duration-150">
                <x-icon name="plus" class="w-4 h-4" /> {{ __('Reportar falla') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            <div class="flex flex-col sm:flex-row gap-3 flex-wrap">
                <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos los estados</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>

                <select wire:model.live="typeFilter" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos los tipos</option>
                    @foreach ($types as $t)
                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                    @endforeach
                </select>

                <select wire:model.live="priorityFilter" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Toda prioridad</option>
                    @foreach ($priorities as $p)
                        <option value="{{ $p->value }}">{{ $p->label() }}</option>
                    @endforeach
                </select>

                @if ($isTecnico)
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 px-1">
                        <input type="checkbox" wire:model.live="onlyMine" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        Solo mis órdenes
                    </label>
                @endif
            </div>

            <div class="bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl overflow-hidden">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50/60 dark:bg-gray-800/40">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Orden</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Equipo</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Tipo</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Prioridad</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Estado</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Técnico</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($workOrders as $workOrder)
                            <tr wire:key="wo-{{ $workOrder->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-5 py-3.5 text-sm">
                                    <a href="{{ route('work-orders.show', $workOrder) }}" wire:navigate class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                                        #{{ $workOrder->id }} {{ $workOrder->title }}
                                    </a>
                                    @if ($workOrder->isOverdue())
                                        <span class="block text-xs text-red-500">Vencida</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300">{{ $workOrder->equipment->name }}</td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300">{{ $workOrder->type->label() }}</td>
                                <td class="px-5 py-3.5 text-sm"><x-badge :color="$workOrder->priority->color()">{{ $workOrder->priority->label() }}</x-badge></td>
                                <td class="px-5 py-3.5 text-sm"><x-badge :color="$workOrder->status->color()">{{ $workOrder->status->label() }}</x-badge></td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $workOrder->technician?->name ?? '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-sm text-right">
                                    @if ($isAdmin && ! $workOrder->assigned_to)
                                        <div class="flex items-center justify-end gap-2">
                                            <select wire:model="assignSelection.{{ $workOrder->id }}" class="text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="">Técnico...</option>
                                                @foreach ($technicians as $tech)
                                                    <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                                                @endforeach
                                            </select>
                                            <button wire:click="assign({{ $workOrder->id }})" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Asignar</button>
                                        </div>
                                    @elseif ($isTecnico && ! $workOrder->assigned_to)
                                        <button wire:click="take({{ $workOrder->id }})" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Tomar orden</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No hay órdenes de trabajo con estos filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="p-4 border-t border-gray-100 dark:border-gray-800">
                    {{ $workOrders->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
