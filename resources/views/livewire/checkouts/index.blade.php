<?php

use App\Enums\CheckoutCondition;
use App\Models\Equipment;
use App\Models\EquipmentCheckout;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Bitácora de equipo')] class extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public bool $showCheckoutModal = false;
    public string $equipment_id = '';
    public string $taken_by = '';
    public string $destination = '';
    public string $condition_out = 'bueno';

    public ?int $returningId = null;
    public string $condition_in = 'bueno';
    public string $return_notes = '';

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function canManage(): bool
    {
        return Gate::allows('manage-checkouts');
    }

    protected function rules(): array
    {
        return [
            'equipment_id' => ['required', 'exists:equipment,id'],
            'taken_by' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'condition_out' => ['required'],
        ];
    }

    public function openCheckoutModal(): void
    {
        Gate::authorize('manage-checkouts');

        $this->reset(['equipment_id', 'taken_by', 'destination']);
        $this->condition_out = 'bueno';
        $this->resetErrorBag();
        $this->showCheckoutModal = true;
    }

    public function checkout(): void
    {
        Gate::authorize('manage-checkouts');

        $data = $this->validate();

        $equipment = Equipment::findOrFail($data['equipment_id']);
        abort_if($equipment->isCheckedOut(), 403, 'Este equipo ya está afuera.');

        EquipmentCheckout::create([
            ...$data,
            'checked_out_by' => auth()->id(),
            'checked_out_at' => now(),
        ]);

        $this->showCheckoutModal = false;
    }

    public function openReturnModal(int $checkoutId): void
    {
        Gate::authorize('manage-checkouts');

        $this->returningId = $checkoutId;
        $this->condition_in = 'bueno';
        $this->return_notes = '';
        $this->resetErrorBag();
    }

    public function closeReturnModal(): void
    {
        $this->returningId = null;
    }

    public function confirmReturn(): void
    {
        Gate::authorize('manage-checkouts');

        $this->validate(['condition_in' => ['required']]);

        $checkout = EquipmentCheckout::findOrFail($this->returningId);

        $checkout->update([
            'condition_in' => $this->condition_in,
            'returned_by' => auth()->id(),
            'returned_at' => now(),
            'notes' => $this->return_notes ?: $checkout->notes,
        ]);

        $this->returningId = null;
    }

    public function with(): array
    {
        return [
            'checkouts' => EquipmentCheckout::query()
                ->with(['equipment', 'checkedOutBy', 'returnedBy'])
                ->when($this->statusFilter === 'out', fn ($q) => $q->whereNull('returned_at'))
                ->when($this->statusFilter === 'returned', fn ($q) => $q->whereNotNull('returned_at'))
                ->latest('checked_out_at')
                ->paginate(10),
            'availableEquipment' => Equipment::query()
                ->where('active', true)
                ->whereDoesntHave('checkouts', fn ($q) => $q->whereNull('returned_at'))
                ->orderBy('name')
                ->get(),
            'conditions' => CheckoutCondition::cases(),
            'outCount' => EquipmentCheckout::whereNull('returned_at')->count(),
            'canManage' => $this->canManage(),
        ];
    }
}; ?>

<div x-on:livewire:navigated.window="$wire.$refresh()">
    <x-page-header title="Bitácora de equipo">
        <x-slot:actions>
            @if ($canManage)
                <x-primary-button wire:click="openCheckoutModal">
                    <x-icon name="plus" class="w-4 h-4" /> {{ __('Registrar salida') }}
                </x-primary-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            <div class="flex flex-wrap items-center gap-3">
                <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    <option value="out">Afuera</option>
                    <option value="returned">Reintegrados</option>
                </select>

                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $outCount }} equipo(s) afuera ahora mismo</span>
            </div>

            <div class="bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl overflow-hidden">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50/60 dark:bg-gray-800/40">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Equipo</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Retirado por</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Destino</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Salida</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Regreso</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Estado</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($checkouts as $checkout)
                            <tr wire:key="checkout-{{ $checkout->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-5 py-3.5 text-sm text-gray-900 dark:text-gray-100 font-medium">{{ $checkout->equipment->name }}</td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300">{{ $checkout->taken_by }}</td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300">{{ $checkout->destination }}</td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $checkout->checked_out_at->format('d/m/Y H:i') }}
                                    <span class="block text-xs">
                                        <x-badge :color="$checkout->condition_out->color()">{{ $checkout->condition_out->label() }}</x-badge>
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300">
                                    @if ($checkout->returned_at)
                                        {{ $checkout->returned_at->format('d/m/Y H:i') }}
                                        <span class="block text-xs">
                                            <x-badge :color="$checkout->condition_in->color()">{{ $checkout->condition_in->label() }}</x-badge>
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-sm">
                                    @if ($checkout->returned_at)
                                        <x-badge color="green">Reintegrado</x-badge>
                                    @else
                                        <x-badge color="amber">Afuera</x-badge>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-sm text-right">
                                    @if (! $checkout->returned_at && $canManage)
                                        <button wire:click="openReturnModal({{ $checkout->id }})" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Registrar regreso</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No hay movimientos de equipo registrados todavía.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="p-4 border-t border-gray-100 dark:border-gray-800">
                    {{ $checkouts->links() }}
                </div>
            </div>
        </div>
    </div>

    @if ($showCheckoutModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="$set('showCheckoutModal', false)"></div>

            <div class="relative mb-6 bg-white dark:bg-gray-900 rounded-2xl overflow-hidden shadow-xl ring-1 ring-gray-900/5 dark:ring-white/10 sm:max-w-lg sm:mx-auto">
                <form wire:submit="checkout" class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-gray-100">Registrar salida de equipo</h2>

                    <div class="space-y-4 mt-5">
                        <div>
                            <x-input-label for="equipment_id" value="Equipo" />
                            <select id="equipment_id" wire:model="equipment_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Selecciona un equipo</option>
                                @foreach ($availableEquipment as $option)
                                    <option value="{{ $option->id }}">{{ $option->code }} — {{ $option->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('equipment_id')" class="mt-2" />
                            @if ($availableEquipment->isEmpty())
                                <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">No hay equipos disponibles — todos están afuera o inactivos.</p>
                            @endif
                        </div>

                        <div>
                            <x-input-label for="taken_by" value="¿Quién lo retira?" />
                            <x-text-input id="taken_by" wire:model="taken_by" class="block mt-1 w-full" placeholder="Nombre de la persona" />
                            <x-input-error :messages="$errors->get('taken_by')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="destination" value="¿Para dónde lo lleva?" />
                            <x-text-input id="destination" wire:model="destination" class="block mt-1 w-full" placeholder="Ej. Obra Nave 3, Cliente X..." />
                            <x-input-error :messages="$errors->get('destination')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="condition_out" value="¿En qué estado se entrega?" />
                            <select id="condition_out" wire:model="condition_out" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($conditions as $c)
                                    <option value="{{ $c->value }}">{{ $c->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-2">
                        <x-secondary-button type="button" wire:click="$set('showCheckoutModal', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Registrar salida</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($returningId)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="closeReturnModal"></div>

            <div class="relative mb-6 bg-white dark:bg-gray-900 rounded-2xl overflow-hidden shadow-xl ring-1 ring-gray-900/5 dark:ring-white/10 sm:max-w-lg sm:mx-auto">
                <form wire:submit="confirmReturn" class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-gray-100">Registrar regreso de equipo</h2>

                    <div class="space-y-4 mt-5">
                        <div>
                            <x-input-label for="condition_in" value="¿En qué estado se recibe?" />
                            <select id="condition_in" wire:model="condition_in" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($conditions as $c)
                                    <option value="{{ $c->value }}">{{ $c->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('condition_in')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="return_notes" value="Notas (opcional)" />
                            <textarea id="return_notes" wire:model="return_notes" rows="3" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Ej. Golpe leve en la carcasa, se reporta para revisión."></textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-2">
                        <x-secondary-button type="button" wire:click="closeReturnModal">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Registrar regreso</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
