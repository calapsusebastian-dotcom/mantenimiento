<?php

use App\Enums\EquipmentStatus;
use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Detalle de orden de trabajo')] class extends Component
{
    public WorkOrder $workOrder;

    public string $comment = '';
    public string $resolution_notes = '';
    public bool $showCompleteForm = false;

    public function mount(WorkOrder $workOrder): void
    {
        $user = auth()->user();

        abort_unless(
            $user->isAdmin()
                || $workOrder->reported_by === $user->id
                || $workOrder->assigned_to === $user->id
                || ($user->isTecnico() && $workOrder->assigned_to === null),
            403,
            'No tienes acceso a esta orden de trabajo.'
        );

        $this->workOrder = $workOrder;
    }

    public function take(): void
    {
        $user = auth()->user();
        abort_unless($user->isTecnico(), 403);
        abort_if($this->workOrder->assigned_to !== null, 403, 'Esta orden ya fue asignada.');

        $this->workOrder->update([
            'assigned_to' => $user->id,
            'status' => WorkOrderStatus::Asignada,
        ]);

        $this->workOrder->logs()->create([
            'user_id' => $user->id,
            'comment' => 'El técnico tomó esta orden de trabajo.',
        ]);

        $this->workOrder->refresh();
    }

    public function start(): void
    {
        Gate::authorize('update-work-order', $this->workOrder);

        $this->workOrder->update([
            'status' => WorkOrderStatus::EnProgreso,
            'started_at' => now(),
        ]);

        if ($this->workOrder->equipment->status === EquipmentStatus::Operativo) {
            $this->workOrder->equipment->update(['status' => EquipmentStatus::EnMantenimiento]);
        }

        $this->workOrder->logs()->create([
            'user_id' => auth()->id(),
            'comment' => 'Se inició el trabajo sobre el equipo.',
        ]);

        $this->workOrder->refresh();
    }

    public function completeForm(): void
    {
        $this->showCompleteForm = true;
    }

    public function complete(): void
    {
        Gate::authorize('update-work-order', $this->workOrder);

        $this->validate([
            'resolution_notes' => ['required', 'string'],
        ]);

        $this->workOrder->update([
            'status' => WorkOrderStatus::Completada,
            'completed_at' => now(),
            'resolution_notes' => $this->resolution_notes,
        ]);

        $this->workOrder->logs()->create([
            'user_id' => auth()->id(),
            'comment' => 'Orden completada: '.$this->resolution_notes,
        ]);

        if ($plan = $this->workOrder->maintenancePlan) {
            $today = now()->startOfDay();
            $nextDue = $plan->next_due_date->copy();

            do {
                $nextDue = $nextDue->addDays($plan->frequency_days);
            } while ($nextDue->lte($today));

            $plan->update(['next_due_date' => $nextDue]);
        }

        $equipment = $this->workOrder->equipment;
        $hasOpenOrders = $equipment->workOrders()
            ->whereNot('id', $this->workOrder->id)
            ->whereIn('status', [WorkOrderStatus::Pendiente, WorkOrderStatus::Asignada, WorkOrderStatus::EnProgreso])
            ->exists();

        if (! $hasOpenOrders && $equipment->status !== EquipmentStatus::Operativo) {
            $equipment->update(['status' => EquipmentStatus::Operativo]);
        }

        $this->showCompleteForm = false;
        $this->resolution_notes = '';
        $this->workOrder->refresh();
    }

    public function cancel(): void
    {
        Gate::authorize('assign-work-orders');

        $this->workOrder->update(['status' => WorkOrderStatus::Cancelada]);

        $this->workOrder->logs()->create([
            'user_id' => auth()->id(),
            'comment' => 'Orden cancelada.',
        ]);

        $this->workOrder->refresh();
    }

    public function addComment(): void
    {
        $this->validate(['comment' => ['required', 'string']]);

        $this->workOrder->logs()->create([
            'user_id' => auth()->id(),
            'comment' => $this->comment,
        ]);

        $this->comment = '';
        $this->workOrder->refresh();
    }

    public function with(): array
    {
        $user = auth()->user();

        return [
            'canManage' => $user->isAdmin() || $this->workOrder->assigned_to === $user->id,
            'isAdmin' => $user->isAdmin(),
            'isTecnico' => $user->isTecnico(),
        ];
    }
}; ?>

<div x-on:livewire:navigated.window="$wire.$refresh()">
    <x-page-header :title="'Orden #'.$workOrder->id.' — '.$workOrder->title">
        <x-slot:actions>
            <x-back-link :href="route('work-orders.index')" />
        </x-slot:actions>
    </x-page-header>

    <div class="py-10">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl p-6 sm:p-8">
                <div class="flex flex-wrap gap-2 mb-4">
                    <x-badge :color="$workOrder->type->color()">{{ $workOrder->type->label() }}</x-badge>
                    <x-badge :color="$workOrder->priority->color()">{{ $workOrder->priority->label() }}</x-badge>
                    <x-badge :color="$workOrder->status->color()">{{ $workOrder->status->label() }}</x-badge>
                    @if ($workOrder->isOverdue())
                        <x-badge color="red">Vencida</x-badge>
                    @endif
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Equipo</dt>
                        <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ $workOrder->equipment->name }} ({{ $workOrder->equipment->code }})</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Ubicación</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $workOrder->equipment->location ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Reportado por</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $workOrder->reporter?->name ?? 'Sistema (preventivo)' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Técnico asignado</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $workOrder->technician?->name ?? 'Sin asignar' }}</dd>
                    </div>
                    @if ($workOrder->scheduled_for)
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Programada para</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $workOrder->scheduled_for->format('d/m/Y') }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($workOrder->description)
                    <div class="mt-4">
                        <dt class="text-gray-500 dark:text-gray-400 text-sm">Descripción</dt>
                        <dd class="text-gray-900 dark:text-gray-100 mt-1">{{ $workOrder->description }}</dd>
                    </div>
                @endif

                @if ($workOrder->resolution_notes)
                    <div class="mt-4 bg-green-50 dark:bg-green-500/10 rounded-xl p-4">
                        <dt class="text-green-700 dark:text-green-400 text-sm font-medium">Notas de resolución</dt>
                        <dd class="text-green-900 dark:text-green-200 mt-1 text-sm">{{ $workOrder->resolution_notes }}</dd>
                    </div>
                @endif

                <div class="mt-6 flex flex-wrap gap-3">
                    @if ($isTecnico && ! $workOrder->assigned_to)
                        <x-primary-button wire:click="take">Tomar orden</x-primary-button>
                    @endif

                    @if ($canManage && in_array($workOrder->status, [\App\Enums\WorkOrderStatus::Pendiente, \App\Enums\WorkOrderStatus::Asignada]))
                        <x-primary-button wire:click="start">Iniciar trabajo</x-primary-button>
                    @endif

                    @if ($canManage && $workOrder->status === \App\Enums\WorkOrderStatus::EnProgreso && ! $showCompleteForm)
                        <x-primary-button wire:click="completeForm">Marcar como completada</x-primary-button>
                    @endif

                    @if ($isAdmin && ! in_array($workOrder->status, [\App\Enums\WorkOrderStatus::Completada, \App\Enums\WorkOrderStatus::Cancelada]))
                        <x-danger-button wire:click="cancel" wire:confirm="¿Cancelar esta orden de trabajo?">Cancelar orden</x-danger-button>
                    @endif
                </div>

                @if ($showCompleteForm)
                    <form wire:submit="complete" class="mt-6 border-t border-gray-100 dark:border-gray-800 pt-5">
                        <x-input-label for="resolution_notes" value="¿Cómo se resolvió?" />
                        <textarea id="resolution_notes" wire:model="resolution_notes" rows="3" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        <x-input-error :messages="$errors->get('resolution_notes')" class="mt-2" />
                        <div class="mt-3 flex gap-3">
                            <x-primary-button type="submit">Guardar y completar</x-primary-button>
                            <x-secondary-button type="button" wire:click="$set('showCompleteForm', false)">Cancelar</x-secondary-button>
                        </div>
                    </form>
                @endif
            </div>

            <div class="bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl p-6 sm:p-8">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Bitácora</h3>

                <form wire:submit="addComment" class="flex gap-3 mb-6">
                    <x-text-input wire:model="comment" class="flex-1" placeholder="Agregar un comentario..." />
                    <x-input-error :messages="$errors->get('comment')" class="mt-2" />
                    <x-secondary-button type="submit">Comentar</x-secondary-button>
                </form>

                <ul class="space-y-4">
                    @forelse ($workOrder->logs as $log)
                        <li class="text-sm border-l-2 border-gray-200 dark:border-gray-700 pl-3">
                            <p class="text-gray-900 dark:text-gray-100">{{ $log->comment }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ $log->user?->name ?? 'Sistema' }} · {{ $log->created_at->format('d/m/Y H:i') }}
                            </p>
                        </li>
                    @empty
                        <li class="text-sm text-gray-500 dark:text-gray-400">Sin actividad todavía.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
