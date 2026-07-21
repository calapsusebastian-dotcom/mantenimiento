<?php

use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Equipment;
use App\Models\WorkOrder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Reportar falla')] class extends Component
{
    public string $equipment_id = '';
    public string $title = '';
    public string $description = '';
    public string $priority = 'media';

    public bool $submitted = false;

    protected function rules(): array
    {
        return [
            'equipment_id' => ['required', 'exists:equipment,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        $workOrder = WorkOrder::create([
            ...$data,
            'type' => WorkOrderType::Correctivo,
            'status' => WorkOrderStatus::Pendiente,
            'reported_by' => auth()->id(),
        ]);

        $this->redirectRoute('work-orders.show', $workOrder, navigate: true);
    }

    public function with(): array
    {
        return [
            'equipmentOptions' => Equipment::where('active', true)->orderBy('name')->get(),
            'priorities' => WorkOrderPriority::cases(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Reportar una falla" />

    <div class="py-10">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl p-6 sm:p-8">
                <div class="flex items-start gap-3 mb-6 p-3 rounded-xl bg-amber-50 dark:bg-amber-500/10">
                    <x-icon name="alert" class="w-5 h-5 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                    <p class="text-sm text-amber-800 dark:text-amber-300">
                        Describe la falla que detectaste en un equipo o maquinaria del parque. Se creará una orden de trabajo correctiva
                        y el equipo de mantenimiento la atenderá según su prioridad.
                    </p>
                </div>

                <form wire:submit="save" class="space-y-5">
                    <div>
                        <x-input-label for="equipment_id" value="Equipo con la falla" />
                        <select id="equipment_id" wire:model="equipment_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecciona un equipo</option>
                            @foreach ($equipmentOptions as $option)
                                <option value="{{ $option->id }}">{{ $option->code }} — {{ $option->name }} @if($option->location) ({{ $option->location }}) @endif</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('equipment_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="title" value="Resumen de la falla" />
                        <x-text-input id="title" wire:model="title" placeholder="Ej. Ruido anormal en el motor" />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Descripción detallada" />
                        <textarea id="description" wire:model="description" rows="4" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="¿Qué observaste? ¿Desde cuándo ocurre?"></textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="priority" value="Prioridad" />
                        <select id="priority" wire:model="priority" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($priorities as $p)
                                <option value="{{ $p->value }}">{{ $p->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                    </div>

                    <div class="flex justify-end pt-2">
                        <x-primary-button type="submit">
                            {{ __('Enviar reporte') }}
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
