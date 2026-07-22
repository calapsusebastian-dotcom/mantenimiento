<?php

use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\MaintenancePlan;
use App\Models\WorkOrder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Calendario de mantenimiento')] class extends Component
{
    public string $month = '';
    public array $scheduleDate = [];
    public string $selectedDay = '';
    public ?int $verifyingPlanId = null;
    public string $verifyNotes = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function openVerifyModal(int $planId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->verifyingPlanId = $planId;
        $this->verifyNotes = '';
    }

    public function closeVerifyModal(): void
    {
        $this->verifyingPlanId = null;
        $this->verifyNotes = '';
    }

    public function showDay(string $date): void
    {
        $this->selectedDay = $date;
    }

    public function closeDay(): void
    {
        $this->selectedDay = '';
    }

    public function scheduleFor(int $workOrderId): void
    {
        $date = $this->scheduleDate[$workOrderId] ?? null;

        if (! $date) {
            return;
        }

        $workOrder = WorkOrder::findOrFail($workOrderId);

        $user = auth()->user();
        abort_unless($user->isAdmin() || $workOrder->assigned_to === $user->id, 403);

        $workOrder->update(['scheduled_for' => $date]);

        $workOrder->logs()->create([
            'user_id' => $user->id,
            'comment' => 'Programada para el '.Carbon::parse($date)->format('d/m/Y').'.',
        ]);

        unset($this->scheduleDate[$workOrderId]);
    }

    /**
     * Manually confirm that a plan's currently due preventive check was
     * carried out, without waiting for the daily scheduled command. Only
     * valid for the plan's actual next_due_date — not a projected future
     * occurrence, which hasn't happened yet.
     */
    public function verifyPlan(int $planId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->validate(['verifyNotes' => ['required', 'string']], [], ['verifyNotes' => 'notas']);

        $plan = MaintenancePlan::findOrFail($planId);
        $user = auth()->user();
        $today = Carbon::today();
        $dueDate = $plan->next_due_date->copy();

        $workOrder = WorkOrder::create([
            'equipment_id' => $plan->equipment_id,
            'maintenance_plan_id' => $plan->id,
            'type' => WorkOrderType::Preventivo,
            'priority' => WorkOrderPriority::Media,
            'status' => WorkOrderStatus::Completada,
            'title' => 'Mantenimiento preventivo: '.$plan->name,
            'description' => $plan->checklist,
            'resolution_notes' => $this->verifyNotes,
            'scheduled_for' => $dueDate,
            'completed_at' => now(),
        ]);

        $workOrder->logs()->create([
            'user_id' => $user->id,
            'comment' => 'Verificación de mantenimiento preventivo confirmada desde el calendario: '.$this->verifyNotes,
        ]);

        $nextDue = $plan->next_due_date->copy();

        do {
            $nextDue = $nextDue->addDays($plan->frequency_days);
        } while ($nextDue->lte($today));

        $plan->update(['next_due_date' => $nextDue]);

        $this->closeVerifyModal();
    }

    public function previousMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m-d', $this->month.'-01')->subMonthNoOverflow()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m-d', $this->month.'-01')->addMonthNoOverflow()->format('Y-m');
    }

    public function goToday(): void
    {
        $this->month = now()->format('Y-m');
    }

    /**
     * Project every recurring occurrence of a maintenance plan that falls
     * within the visible date range, based on its frequency — not just the
     * single `next_due_date` column, which only ever holds the next one.
     *
     * @return array<int, Carbon>
     */
    private function projectPlanOccurrences(MaintenancePlan $plan, Carbon $gridStart, Carbon $gridEnd): array
    {
        if ($plan->frequency_days < 1) {
            return $plan->next_due_date->between($gridStart, $gridEnd) ? [$plan->next_due_date->copy()] : [];
        }

        $cursor = $plan->next_due_date->copy();

        if ($cursor->lt($gridStart)) {
            $daysBehind = $cursor->diffInDays($gridStart);
            $cursor->addDays(intdiv($daysBehind, $plan->frequency_days) * $plan->frequency_days);

            while ($cursor->lt($gridStart)) {
                $cursor->addDays($plan->frequency_days);
            }
        }

        $occurrences = [];
        $guard = 0;

        while ($cursor->lte($gridEnd) && $guard < 400) {
            $occurrences[] = $cursor->copy();
            $cursor->addDays($plan->frequency_days);
            $guard++;
        }

        return $occurrences;
    }

    public function with(): array
    {
        $user = auth()->user();

        $monthStart = Carbon::createFromFormat('Y-m-d', $this->month.'-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);

        $workOrderQuery = WorkOrder::with('equipment')
            ->whereNotNull('scheduled_for')
            ->whereBetween('scheduled_for', [$gridStart->toDateString(), $gridEnd->toDateString()]);

        if ($user->isOperador()) {
            $workOrderQuery->where('reported_by', $user->id);
        } elseif ($user->isTecnico()) {
            $workOrderQuery->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhereNull('assigned_to'));
        }

        $workOrdersByDay = $workOrderQuery->get()->groupBy(fn ($workOrder) => $workOrder->scheduled_for->format('Y-m-d'));

        $unscheduledQuery = WorkOrder::with('equipment')
            ->whereNull('scheduled_for')
            ->whereIn('status', [WorkOrderStatus::Pendiente, WorkOrderStatus::Asignada, WorkOrderStatus::EnProgreso]);

        if ($user->isOperador()) {
            $unscheduledQuery->where('reported_by', $user->id);
        } elseif ($user->isTecnico()) {
            $unscheduledQuery->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhereNull('assigned_to'));
        }

        $unscheduled = $unscheduledQuery->latest()->get();

        $plansByDay = collect();

        if ($user->isAdmin()) {
            $plansByDay = MaintenancePlan::with('equipment')
                ->where('active', true)
                ->where('next_due_date', '<=', $gridEnd)
                ->get()
                ->flatMap(function (MaintenancePlan $plan) use ($gridStart, $gridEnd) {
                    return collect($this->projectPlanOccurrences($plan, $gridStart, $gridEnd))
                        ->map(fn (Carbon $date) => ['date' => $date->format('Y-m-d'), 'plan' => $plan]);
                })
                ->groupBy('date')
                ->map(fn ($entries) => $entries->pluck('plan'));
        }

        $days = [];
        $cursor = $gridStart->copy();
        while ($cursor->lte($gridEnd)) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return [
            'weeks' => array_chunk($days, 7),
            'monthLabel' => $meses[(int) $monthStart->format('n')].' '.$monthStart->format('Y'),
            'monthStart' => $monthStart,
            'workOrdersByDay' => $workOrdersByDay,
            'plansByDay' => $plansByDay,
            'unscheduled' => $unscheduled,
            'isAdmin' => $user->isAdmin(),
            'userId' => $user->id,
        ];
    }
}; ?>

<div x-on:livewire:navigated.window="$wire.$refresh()">
    <x-page-header title="Calendario de mantenimiento">
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <button wire:click="previousMonth" class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800">
                    <x-icon name="arrow-left" class="w-4 h-4" />
                </button>
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100 w-32 text-center">{{ $monthLabel }}</span>
                <button wire:click="nextMonth" class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800">
                    <x-icon name="arrow-left" class="w-4 h-4 rotate-180" />
                </button>
                <x-secondary-button wire:click="goToday">Hoy</x-secondary-button>
            </div>
        </x-slot:actions>
    </x-page-header>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            @if ($unscheduled->isNotEmpty())
                <div class="bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                        <x-icon name="alert" class="w-4 h-4 text-amber-500" />
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Pendientes sin programar ({{ $unscheduled->count() }})</h3>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($unscheduled as $workOrder)
                            <li wire:key="unscheduled-{{ $workOrder->id }}" class="px-5 py-3 flex flex-wrap items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('work-orders.show', $workOrder) }}" wire:navigate class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                        #{{ $workOrder->id }} {{ $workOrder->title }}
                                    </a>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $workOrder->equipment->name }}</p>
                                </div>

                                <div class="flex items-center gap-2 flex-wrap">
                                    <x-badge :color="$workOrder->priority->color()">{{ $workOrder->priority->label() }}</x-badge>
                                    <x-badge :color="$workOrder->status->color()">{{ $workOrder->status->label() }}</x-badge>

                                    @if ($isAdmin || $workOrder->assigned_to === $userId)
                                        <input type="date" wire:model="scheduleDate.{{ $workOrder->id }}" class="text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <button wire:click="scheduleFor({{ $workOrder->id }})" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Programar</button>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Preventivo</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Correctivo</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-green-500"></span> Hecho (clic para ver o agregar una nota)</span>
                @if ($isAdmin)
                    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full border-2 border-indigo-500"></span> Vencimiento proyectado de un plan preventivo (según su frecuencia)</span>
                    <span class="inline-flex items-center gap-1.5"><x-icon name="check-circle" class="w-3.5 h-3.5 text-green-600 dark:text-green-400" /> Marcar verificación realizada (solo en la fecha real de vencimiento)</span>
                @endif
            </div>

            <div class="bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl overflow-hidden">
                <div class="grid grid-cols-7 bg-gray-50/60 dark:bg-gray-800/40 border-b border-gray-100 dark:border-gray-800">
                    @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $label)
                        <div class="px-2 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ $label }}</div>
                    @endforeach
                </div>

                <div class="grid grid-cols-7">
                    @foreach ($weeks as $week)
                        @foreach ($week as $day)
                            @php
                                $key = $day->format('Y-m-d');
                                $isCurrentMonth = $day->month === $monthStart->month;
                                $isToday = $day->isToday();
                                $dayWorkOrders = $workOrdersByDay->get($key, collect());
                                $dayPlans = $plansByDay->get($key, collect());
                            @endphp
                            <div wire:key="day-{{ $key }}" class="min-h-[7rem] p-1.5 border-b border-r border-gray-100 dark:border-gray-800 {{ $isCurrentMonth ? '' : 'bg-gray-50/50 dark:bg-gray-900/40' }}">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-medium {{ $isToday ? 'bg-indigo-600 text-white' : ($isCurrentMonth ? 'text-gray-700 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600') }}">
                                    {{ $day->format('j') }}
                                </span>

                                <div class="mt-1 space-y-1">
                                    @foreach ($dayWorkOrders->take(3) as $workOrder)
                                        @php $isDone = $workOrder->status->value === 'completada'; @endphp
                                        <a href="{{ route('work-orders.show', $workOrder) }}" wire:navigate
                                            title="{{ $workOrder->title }} — {{ $workOrder->equipment->name }}{{ $isDone ? ' (hecho — clic para agregar una nota)' : '' }}"
                                            class="flex items-center gap-1 truncate rounded px-1.5 py-0.5 text-xs font-medium hover:opacity-75 {{ $isDone ? 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400' : ($workOrder->type->value === 'preventivo' ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400' : 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400') }}">
                                            @if ($isDone)
                                                <x-icon name="check-circle" class="w-3 h-3 shrink-0" />
                                            @endif
                                            <span class="truncate {{ $isDone ? 'line-through' : '' }}">{{ $workOrder->equipment->name }}</span>
                                        </a>
                                    @endforeach

                                    @if ($isAdmin)
                                        @foreach ($dayPlans->take(3) as $plan)
                                            @php $isActualDueDate = $plan->next_due_date->format('Y-m-d') === $key; @endphp
                                            <div class="flex items-center gap-1 rounded border border-dashed border-indigo-300 dark:border-indigo-500/40 px-1.5 py-0.5">
                                                @if ($isActualDueDate)
                                                    <button wire:click="openVerifyModal({{ $plan->id }})"
                                                        title="Plan: {{ $plan->name }} — {{ $plan->equipment->name }} (clic para marcar como hecho)"
                                                        class="flex-1 min-w-0 truncate text-left text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                                        {{ $plan->equipment->name }}
                                                    </button>
                                                    <x-icon name="check-circle" class="w-3.5 h-3.5 shrink-0 text-green-600 dark:text-green-400" />
                                                @else
                                                    <a href="{{ route('maintenance-plans.index', ['edit' => $plan->id]) }}" wire:navigate
                                                        title="Plan: {{ $plan->name }} — {{ $plan->equipment->name }}"
                                                        class="flex-1 min-w-0 truncate text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                                        {{ $plan->equipment->name }}
                                                    </a>
                                                @endif
                                            </div>
                                        @endforeach
                                    @endif

                                    @php $shown = min($dayWorkOrders->count(), 3) + ($isAdmin ? min($dayPlans->count(), 3) : 0); @endphp
                                    @php $total = $dayWorkOrders->count() + ($isAdmin ? $dayPlans->count() : 0); @endphp
                                    @if ($total > $shown)
                                        <button wire:click="showDay('{{ $key }}')" class="w-full text-left text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline px-1.5">+{{ $total - $shown }} más</button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @if ($selectedDay)
        @php
            $modalDate = Carbon::createFromFormat('Y-m-d', $selectedDay);
            $modalWorkOrders = $workOrdersByDay->get($selectedDay, collect());
            $modalPlans = $isAdmin ? $plansByDay->get($selectedDay, collect()) : collect();
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="closeDay"></div>

            <div class="relative mb-6 bg-white dark:bg-gray-900 rounded-2xl overflow-hidden shadow-xl ring-1 ring-gray-900/5 dark:ring-white/10 sm:max-w-lg sm:mx-auto">
                <div class="p-6 sm:p-8">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $modalDate->format('d/m/Y') }}</h2>
                        <button wire:click="closeDay" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            <x-icon name="x-mark" class="w-5 h-5" />
                        </button>
                    </div>

                    <ul class="space-y-2 max-h-[28rem] overflow-y-auto">
                        @foreach ($modalWorkOrders as $workOrder)
                            @php $isDone = $workOrder->status->value === 'completada'; @endphp
                            <li>
                                <a href="{{ route('work-orders.show', $workOrder) }}" wire:navigate
                                    class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                                    @if ($isDone)
                                        <x-icon name="check-circle" class="w-4 h-4 shrink-0 text-green-600 dark:text-green-400" />
                                    @endif
                                    <span class="min-w-0 flex-1 {{ $isDone ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-900 dark:text-gray-100' }}">
                                        {{ $workOrder->equipment->name }}
                                        <span class="block text-xs text-gray-500 dark:text-gray-400 truncate">{{ $workOrder->title }}</span>
                                    </span>
                                    <x-badge :color="$workOrder->status->color()">{{ $workOrder->status->label() }}</x-badge>
                                </a>
                            </li>
                        @endforeach

                        @foreach ($modalPlans as $plan)
                            @php $modalIsActualDueDate = $plan->next_due_date->format('Y-m-d') === $selectedDay; @endphp
                            <li>
                                @if ($modalIsActualDueDate)
                                    <button wire:click="openVerifyModal({{ $plan->id }})"
                                        class="flex w-full items-center gap-2 rounded-lg border border-dashed border-indigo-300 dark:border-indigo-500/40 px-3 py-2 text-sm text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">
                                        <span class="min-w-0 flex-1 text-left">
                                            {{ $plan->equipment->name }}
                                            <span class="block text-xs opacity-75 truncate">Plan: {{ $plan->name }} · clic para marcar como hecho</span>
                                        </span>
                                        <x-icon name="check-circle" class="w-4 h-4 shrink-0" />
                                    </button>
                                @else
                                    <a href="{{ route('maintenance-plans.index', ['edit' => $plan->id]) }}" wire:navigate
                                        class="flex items-center gap-2 rounded-lg border border-dashed border-indigo-300 dark:border-indigo-500/40 px-3 py-2 text-sm text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10">
                                        <span class="min-w-0 flex-1">
                                            {{ $plan->equipment->name }}
                                            <span class="block text-xs opacity-75 truncate">Plan: {{ $plan->name }}</span>
                                        </span>
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-5 flex justify-end">
                        <x-secondary-button wire:click="closeDay">Cerrar</x-secondary-button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($verifyingPlanId)
        @php $verifyingPlan = \App\Models\MaintenancePlan::with('equipment')->find($verifyingPlanId); @endphp
        @if ($verifyingPlan)
            <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
                <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="closeVerifyModal"></div>

                <div class="relative mb-6 bg-white dark:bg-gray-900 rounded-2xl overflow-hidden shadow-xl ring-1 ring-gray-900/5 dark:ring-white/10 sm:max-w-lg sm:mx-auto">
                    <form wire:submit="verifyPlan({{ $verifyingPlan->id }})" class="p-6 sm:p-8">
                        <h2 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-gray-100">
                            Marcar mantenimiento como realizado
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ $verifyingPlan->name }} — {{ $verifyingPlan->equipment->name }}
                        </p>

                        <div class="mt-5">
                            <x-input-label for="verifyNotes" value="¿Qué se hizo?" />
                            <textarea id="verifyNotes" wire:model="verifyNotes" rows="4"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Ej. Se lubricaron los rodamientos y se revisaron las bandas, todo en buen estado."></textarea>
                            <x-input-error :messages="$errors->get('verifyNotes')" class="mt-2" />
                        </div>

                        <div class="mt-6 flex justify-end gap-3 pt-2">
                            <x-secondary-button type="button" wire:click="closeVerifyModal">Cancelar</x-secondary-button>
                            <x-primary-button type="submit">Marcar como realizado</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endif
</div>
