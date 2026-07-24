<?php

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Equipos')] class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $activeFilter = 'active';

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $code = '';
    public string $name = '';
    public string $category = '';
    public string $brand = '';
    public string $model = '';
    public string $serial_number = '';
    public string $location = '';
    public string $status = 'operativo';
    public string $purchase_date = '';
    public string $notes = '';
    public $hojaVida = null;
    public ?string $existingHojaVidaName = null;
    public bool $removeHojaVida = false;

    public function mount(): void
    {
        Gate::authorize('manage-equipment');

        if ($equipmentId = request()->integer('edit')) {
            Equipment::whereKey($equipmentId)->exists() && $this->edit($equipmentId);
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingActiveFilter(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:equipment,code,'.$this->editingId],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required'],
            'purchase_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'hojaVida' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $equipment = Equipment::findOrFail($id);

        $this->editingId = $equipment->id;
        $this->code = $equipment->code;
        $this->name = $equipment->name;
        $this->category = (string) $equipment->category;
        $this->brand = (string) $equipment->brand;
        $this->model = (string) $equipment->model;
        $this->serial_number = (string) $equipment->serial_number;
        $this->location = (string) $equipment->location;
        $this->status = $equipment->status->value;
        $this->purchase_date = $equipment->purchase_date?->format('Y-m-d') ?? '';
        $this->notes = (string) $equipment->notes;
        $this->existingHojaVidaName = $equipment->hoja_vida_name;
        $this->removeHojaVida = false;

        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate();
        $data['purchase_date'] = $data['purchase_date'] ?: null;

        $hojaVida = $data['hojaVida'] ?? null;
        unset($data['hojaVida']);

        $equipment = $this->editingId ? Equipment::findOrFail($this->editingId) : null;

        if ($hojaVida) {
            if ($equipment?->hoja_vida_path) {
                Storage::disk('local')->delete($equipment->hoja_vida_path);
            }

            $data['hoja_vida_path'] = $hojaVida->store('hojas-vida', 'local');
            $data['hoja_vida_name'] = $hojaVida->getClientOriginalName();
        } elseif ($this->removeHojaVida && $equipment?->hoja_vida_path) {
            Storage::disk('local')->delete($equipment->hoja_vida_path);
            $data['hoja_vida_path'] = null;
            $data['hoja_vida_name'] = null;
        }

        if ($equipment) {
            $equipment->update($data);
        } else {
            $data['created_by'] = auth()->id();
            Equipment::create($data);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function downloadHojaVida(int $id)
    {
        $equipment = Equipment::findOrFail($id);

        abort_unless($equipment->hoja_vida_path && Storage::disk('local')->exists($equipment->hoja_vida_path), 404);

        return Storage::disk('local')->download($equipment->hoja_vida_path, $equipment->hoja_vida_name ?? 'hoja-de-vida.pdf');
    }

    public function toggleActive(int $id): void
    {
        $equipment = Equipment::findOrFail($id);
        $equipment->update(['active' => ! $equipment->active]);
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId', 'code', 'name', 'category', 'brand', 'model',
            'serial_number', 'location', 'purchase_date', 'notes',
            'hojaVida', 'existingHojaVidaName', 'removeHojaVida',
        ]);
        $this->status = 'operativo';
        $this->resetErrorBag();
    }

    public function with(): array
    {
        return [
            'equipments' => Equipment::query()
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%")
                    ->orWhere('location', 'like', "%{$this->search}%")
                ))
                ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
                ->when($this->activeFilter === 'active', fn ($q) => $q->where('active', true))
                ->when($this->activeFilter === 'inactive', fn ($q) => $q->where('active', false))
                ->latest()
                ->paginate(10),
            'statuses' => EquipmentStatus::cases(),
        ];
    }
}; ?>

<div x-on:livewire:navigated.window="$wire.$refresh()">
    <x-page-header title="Equipos y maquinaria">
        <x-slot:actions>
            <x-primary-button wire:click="create">
                <x-icon name="plus" class="w-4 h-4" /> {{ __('Nuevo equipo') }}
            </x-primary-button>
        </x-slot:actions>
    </x-page-header>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por código, nombre o área..."
                    class="w-full sm:max-w-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">

                <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos los estados</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>

                <select wire:model.live="activeFilter" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="active">Solo activos</option>
                    <option value="inactive">Solo inactivos</option>
                    <option value="all">Todos</option>
                </select>
            </div>

            <div class="bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl overflow-hidden">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50/60 dark:bg-gray-800/40">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Código</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Nombre</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Área</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Estado</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($equipments as $equipment)
                            <tr wire:key="equipment-{{ $equipment->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ ! $equipment->active ? 'opacity-60' : '' }}">
                                <td class="px-5 py-3.5 text-sm font-mono text-gray-500 dark:text-gray-400">{{ $equipment->code }}</td>
                                <td class="px-5 py-3.5 text-sm text-gray-900 dark:text-gray-100">
                                    <a href="{{ route('equipment.show', $equipment) }}" wire:navigate class="font-medium hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline">{{ $equipment->name }}</a>
                                    @if ($equipment->category)
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $equipment->category }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300">{{ $equipment->location ?: '—' }}</td>
                                <td class="px-5 py-3.5 text-sm space-x-1.5">
                                    <x-badge :color="$equipment->status->color()">{{ $equipment->status->label() }}</x-badge>
                                    @unless ($equipment->active)
                                        <x-badge color="slate">Inactivo</x-badge>
                                    @endunless
                                </td>
                                <td class="px-5 py-3.5 text-sm text-right space-x-3">
                                    @if ($equipment->hoja_vida_path)
                                        <button wire:click="downloadHojaVida({{ $equipment->id }})" title="Descargar hoja de vida" class="font-medium text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline">Hoja de vida</button>
                                    @endif
                                    <button wire:click="edit({{ $equipment->id }})" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Editar</button>
                                    @if ($equipment->active)
                                        <button wire:click="toggleActive({{ $equipment->id }})" wire:confirm="¿Inactivar este equipo? Ya no aparecerá disponible para nuevas órdenes o planes." class="font-medium text-red-600 dark:text-red-400 hover:underline">Inactivar</button>
                                    @else
                                        <button wire:click="toggleActive({{ $equipment->id }})" class="font-medium text-green-600 dark:text-green-400 hover:underline">Activar</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No hay equipos registrados todavía.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="p-4 border-t border-gray-100 dark:border-gray-800">
                    {{ $equipments->links() }}
                </div>
            </div>
        </div>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>

            <div class="relative mb-6 bg-white dark:bg-gray-900 rounded-2xl overflow-hidden shadow-xl ring-1 ring-gray-900/5 dark:ring-white/10 sm:max-w-2xl sm:mx-auto">
        <form wire:submit="save" class="p-6 sm:p-8">
            <h2 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-gray-100">
                {{ $editingId ? 'Editar equipo' : 'Nuevo equipo' }}
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-5">
                <div>
                    <x-input-label for="code" value="Código" />
                    <x-text-input id="code" wire:model="code" class="block mt-1 w-full" />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="name" value="Nombre" />
                    <x-text-input id="name" wire:model="name" class="block mt-1 w-full" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="category" value="Categoría" />
                    <x-text-input id="category" wire:model="category" class="block mt-1 w-full" placeholder="Ej. Maquinaria pesada, HVAC, eléctrico..." />
                </div>

                <div>
                    <x-input-label for="location" value="Área" />
                    <x-text-input id="location" wire:model="location" class="block mt-1 w-full" placeholder="Ej. Nave 1, Subestación, Cuarto de bombas..." />
                </div>

                <div>
                    <x-input-label for="brand" value="Marca" />
                    <x-text-input id="brand" wire:model="brand" class="block mt-1 w-full" />
                </div>

                <div>
                    <x-input-label for="model" value="Modelo" />
                    <x-text-input id="model" wire:model="model" class="block mt-1 w-full" />
                </div>

                <div>
                    <x-input-label for="serial_number" value="N° de serie" />
                    <x-text-input id="serial_number" wire:model="serial_number" class="block mt-1 w-full" />
                </div>

                <div>
                    <x-input-label for="purchase_date" value="Fecha de compra" />
                    <x-text-input id="purchase_date" type="date" wire:model="purchase_date" class="block mt-1 w-full" />
                </div>

                <div>
                    <x-input-label for="status" value="Estado" />
                    <select id="status" wire:model="status" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($statuses as $s)
                            <option value="{{ $s->value }}">{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="notes" value="Notas" />
                    <textarea id="notes" wire:model="notes" rows="3" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="hojaVida" value="Hoja de vida (PDF)" />

                    @if ($existingHojaVidaName && ! $removeHojaVida)
                        <div class="flex items-center gap-3 mt-1 mb-2 p-2.5 rounded-lg bg-gray-50 dark:bg-gray-800 text-sm">
                            <x-icon name="clipboard" class="w-4 h-4 shrink-0 text-gray-400" />
                            <span class="flex-1 min-w-0 truncate text-gray-700 dark:text-gray-300">{{ $existingHojaVidaName }}</span>
                            <button type="button" wire:click="$set('removeHojaVida', true)" class="shrink-0 text-xs font-medium text-red-600 dark:text-red-400 hover:underline">Quitar</button>
                        </div>
                    @endif

                    <input id="hojaVida" type="file" wire:model="hojaVida" accept="application/pdf"
                        class="block w-full text-sm text-gray-600 dark:text-gray-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 dark:file:bg-indigo-500/10 dark:file:text-indigo-400 hover:file:bg-indigo-100">
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Solo PDF, máximo 10 MB.</p>
                    <div wire:loading wire:target="hojaVida" class="text-xs text-indigo-500 mt-1">Subiendo archivo...</div>
                    <x-input-error :messages="$errors->get('hojaVida')" class="mt-2" />
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
