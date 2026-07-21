<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Usuarios')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $roleFilter = '';

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = 'tecnico';

    public function mount(): void
    {
        Gate::authorize('manage-users');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$this->editingId],
            'password' => [$this->editingId ? 'nullable' : 'required', Password::defaults()],
            'role' => ['required'],
        ];
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->role->value;

        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if (! $data['password']) {
            unset($data['password']);
        }

        if ($this->editingId) {
            User::findOrFail($this->editingId)->update($data);
        } else {
            $data['email_verified_at'] = now();
            User::create($data);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        if ($id === auth()->id()) {
            return;
        }

        $user = User::findOrFail($id);
        $user->update(['active' => ! $user->active]);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'email', 'password']);
        $this->role = 'tecnico';
        $this->resetErrorBag();
    }

    public function with(): array
    {
        return [
            'users' => User::query()
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                ))
                ->when($this->roleFilter, fn ($q) => $q->where('role', $this->roleFilter))
                ->orderBy('name')
                ->paginate(10),
            'roles' => UserRole::cases(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Usuarios">
        <x-slot:actions>
            <x-primary-button wire:click="create">
                <x-icon name="plus" class="w-4 h-4" /> {{ __('Nuevo usuario') }}
            </x-primary-button>
        </x-slot:actions>
    </x-page-header>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por nombre o correo..."
                    class="w-full sm:max-w-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">

                <select wire:model.live="roleFilter" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos los roles</option>
                    @foreach ($roles as $r)
                        <option value="{{ $r->value }}">{{ $r->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl overflow-hidden">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50/60 dark:bg-gray-800/40">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Nombre</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Correo</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Rol</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Estado</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($users as $user)
                            <tr wire:key="user-{{ $user->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ ! $user->active ? 'opacity-60' : '' }}">
                                <td class="px-5 py-3.5 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $user->name }}
                                    @if ($user->id === auth()->id())
                                        <span class="text-xs text-gray-400">(tú)</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300">{{ $user->email }}</td>
                                <td class="px-5 py-3.5 text-sm">
                                    <x-badge color="blue">{{ $user->role->label() }}</x-badge>
                                </td>
                                <td class="px-5 py-3.5 text-sm">
                                    @if ($user->active)
                                        <x-badge color="green">Activo</x-badge>
                                    @else
                                        <x-badge color="slate">Inactivo</x-badge>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-sm text-right space-x-3">
                                    <button wire:click="edit({{ $user->id }})" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Editar</button>
                                    @if ($user->id !== auth()->id())
                                        @if ($user->active)
                                            <button wire:click="toggleActive({{ $user->id }})" wire:confirm="¿Inactivar a {{ $user->name }}? No podrá iniciar sesión." class="font-medium text-red-600 dark:text-red-400 hover:underline">Inactivar</button>
                                        @else
                                            <button wire:click="toggleActive({{ $user->id }})" class="font-medium text-green-600 dark:text-green-400 hover:underline">Activar</button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No hay usuarios registrados todavía.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="p-4 border-t border-gray-100 dark:border-gray-800">
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>

            <div class="relative mb-6 bg-white dark:bg-gray-900 rounded-2xl overflow-hidden shadow-xl ring-1 ring-gray-900/5 dark:ring-white/10 sm:max-w-lg sm:mx-auto">
                <form wire:submit="save" class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-gray-100">
                        {{ $editingId ? 'Editar usuario' : 'Nuevo usuario' }}
                    </h2>

                    <div class="space-y-4 mt-5">
                        <div>
                            <x-input-label for="name" value="Nombre" />
                            <x-text-input id="name" wire:model="name" class="block mt-1 w-full" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="email" value="Correo" />
                            <x-text-input id="email" type="email" wire:model="email" class="block mt-1 w-full" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="password" :value="$editingId ? 'Nueva contraseña (opcional)' : 'Contraseña'" />
                            <x-text-input id="password" type="password" wire:model="password" class="block mt-1 w-full" placeholder="{{ $editingId ? 'Dejar en blanco para no cambiarla' : '' }}" />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="role" value="Rol" />
                            <select id="role" wire:model="role" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($roles as $r)
                                    <option value="{{ $r->value }}">{{ $r->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('role')" class="mt-2" />
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
