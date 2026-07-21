<?php

use App\Enums\WorkOrderStatus;
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

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
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

<div>
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
                @if ($isAdmin)
                    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full border-2 border-indigo-500"></span> Vencimiento proyectado de un plan preventivo (según su frecuencia)</span>
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
                                        <a href="{{ route('work-orders.show', $workOrder) }}" wire:navigate
                                            title="{{ $workOrder->title }} — {{ $workOrder->equipment->name }}"
                                            class="block truncate rounded px-1.5 py-0.5 text-xs font-medium {{ $workOrder->type->value === 'preventivo' ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400' : 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' }} hover:opacity-75">
                                            {{ $workOrder->title }}
                                        </a>
                                    @endforeach

                                    @if ($isAdmin)
                                        @foreach ($dayPlans->take(3) as $plan)
                                            <div title="Plan: {{ $plan->name }} — {{ $plan->equipment->name }}"
                                                class="block truncate rounded px-1.5 py-0.5 text-xs font-medium border border-dashed border-indigo-300 text-indigo-600 dark:border-indigo-500/40 dark:text-indigo-400">
                                                {{ $plan->equipment->name }}
                                            </div>
                                        @endforeach
                                    @endif

                                    @php $shown = min($dayWorkOrders->count(), 3) + ($isAdmin ? min($dayPlans->count(), 3) : 0); @endphp
                                    @php $total = $dayWorkOrders->count() + ($isAdmin ? $dayPlans->count() : 0); @endphp
                                    @if ($total > $shown)
                                        <div class="text-xs text-gray-400 dark:text-gray-500 px-1.5">+{{ $total - $shown }} más</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
