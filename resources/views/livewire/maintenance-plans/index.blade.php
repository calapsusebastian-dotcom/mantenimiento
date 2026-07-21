<?php

use App\Models\Equipment;
use App\Models\MaintenancePlan;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Planes de mantenimiento preventivo')] class extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $equipment_id = '';
    public string $name = '';
    public string $checklist = '';
    public string $frequency_days = '30';
    public string $next_due_date = '';
    public bool $active = true;

    public function mount(): void
    {
        Gate::authorize('manage-maintenance-plans');
        $this->next_due_date = now()->format('Y-m-d');
    }

    protected function rules(): array
    {
        return [
            'equipment_id' => ['required', 'exists:equipment,id'],
            'name' => ['required', 'string', 'max:255'],
            'checklist' => ['nullable', 'string'],
            'frequency_days' => ['required', 'integer', 'min:1'],
            'next_due_date' => ['required', 'date'],
            'active' => ['boolean'],
        ];
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $plan = MaintenancePlan::findOrFail($id);

        $this->editingId = $plan->id;
        $this->equipment_id = (string) $plan->equipment_id;
        $this->name = $plan->name;
        $this->checklist = (string) $plan->checklist;
        $this->frequency_days = (string) $plan->frequency_days;
        $this->next_due_date = $plan->next_due_date->format('Y-m-d');
        $this->active = $plan->active;

        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            MaintenancePlan::findOrFail($this->editingId)->update($data);
        } else {
            $data['created_by'] = auth()->id();
            MaintenancePlan::create($data);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        MaintenancePlan::findOrFail($id)->delete();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'equipment_id', 'name', 'checklist']);
        $this->frequency_days = '30';
        $this->next_due_date = now()->format('Y-m-d');
        $this->active = true;
        $this->resetErrorBag();
    }

    public function with(): array
    {
        return [
            'plans' => MaintenancePlan::with('equipment')->latest()->paginate(10),
            'equipmentOptions' => Equipment::query()
                ->where(fn ($q) => $q->where('active', true)->when($this->equipment_id, fn ($q2) => $q2->orWhere('id', $this->equipment_id)))
                ->orderBy('name')
                ->get(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Planes de mantenimiento preventivo">
        <x-slot:actions>
            <x-primary-button wire:click="create">
                <x-icon name="plus" class="w-4 h-4" /> {{ __('Nuevo plan') }}
            </x-primary-button>
        </x-slot:actions>
    </x-page-header>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            <div class="bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl overflow-hidden">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50/60 dark:bg-gray-800/40">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Plan</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Equipo</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Frecuencia</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Próximo vencimiento</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Estado</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($plans as $plan)
                            <tr wire:key="plan-{{ $plan->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-5 py-3.5 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $plan->name }}</td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300">{{ $plan->equipment->name }}</td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300">Cada {{ $plan->frequency_days }} días</td>
                                <td class="px-5 py-3.5 text-sm">
                                    <span class="{{ $plan->isDue() ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-600 dark:text-gray-300' }}">
                                        {{ $plan->next_due_date->format('d/m/Y') }}
                                    </span>
                                    @if ($plan->isDue())
                                        <span class="block text-xs text-red-500">Vencido</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-sm">
                                    @if ($plan->active)
                                        <x-badge color="green">Activo</x-badge>
                                    @else
                                        <x-badge color="slate">Inactivo</x-badge>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-sm text-right space-x-3">
                                    <button wire:click="edit({{ $plan->id }})" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Editar</button>
                                    <button wire:click="delete({{ $plan->id }})" wire:confirm="¿Eliminar este plan de mantenimiento?" class="font-medium text-red-600 dark:text-red-400 hover:underline">Eliminar</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No hay planes de mantenimiento creados todavía.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="p-4 border-t border-gray-100 dark:border-gray-800">
                    {{ $plans->links() }}
                </div>
            </div>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Las órdenes de trabajo preventivas se generan automáticamente todos los días a partir de estos planes cuando llega la fecha de vencimiento
                (comando <code class="px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-xs">maintenance:generate-work-orders</code>).
            </p>
        </div>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>

            <div class="relative mb-6 bg-white dark:bg-gray-900 rounded-2xl overflow-hidden shadow-xl ring-1 ring-gray-900/5 dark:ring-white/10 sm:max-w-2xl sm:mx-auto">
        <form wire:submit="save" class="p-6 sm:p-8">
            <h2 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-gray-100">
                {{ $editingId ? 'Editar plan' : 'Nuevo plan de mantenimiento' }}
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-5">
                <div class="sm:col-span-2">
                    <x-input-label for="equipment_id" value="Equipo" />
                    <select id="equipment_id" wire:model="equipment_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Selecciona un equipo</option>
                        @foreach ($equipmentOptions as $option)
                            <option value="{{ $option->id }}">{{ $option->code }} — {{ $option->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('equipment_id')" class="mt-2" />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="name" value="Nombre del plan" />
                    <x-text-input id="name" wire:model="name" class="block mt-1 w-full" placeholder="Ej. Lubricación mensual, revisión eléctrica trimestral..." />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="frequency_days" value="Frecuencia (días)" />
                    <x-text-input id="frequency_days" type="number" min="1" wire:model="frequency_days" class="block mt-1 w-full" />
                    <x-input-error :messages="$errors->get('frequency_days')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="next_due_date" value="Próximo vencimiento" />
                    <x-text-input id="next_due_date" type="date" wire:model="next_due_date" class="block mt-1 w-full" />
                    <x-input-error :messages="$errors->get('next_due_date')" class="mt-2" />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="checklist" value="Checklist / instrucciones" />
                    <textarea id="checklist" wire:model="checklist" rows="4" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                </div>

                <div class="sm:col-span-2">
                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="active" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="ms-2 text-sm text-gray-700 dark:text-gray-300">Plan activo</span>
                    </label>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3 pt-2">
                <x-secondary-button type="button" wire:click="$set('showModal', false)">Cancelar</x-secondary-button>
                <x-primary-button type="submit">Guardar</x-primary-button>
            </div>
        </form>
            </div>
        </div>
    @endif
</div>
