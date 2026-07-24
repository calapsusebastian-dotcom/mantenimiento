<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div x-data="{ sidebarOpen: false }">
    <!-- Mobile top bar -->
    <div class="sticky top-0 z-30 flex items-center justify-between gap-4 bg-white/90 dark:bg-gray-900/90 backdrop-blur border-b border-gray-200 dark:border-gray-800 px-4 py-3 lg:hidden">
        <button @click="sidebarOpen = true" class="-ml-2 p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800">
            <x-icon name="menu" class="w-6 h-6" />
        </button>
        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2 font-semibold text-gray-900 dark:text-gray-100">
            <div class="h-7 w-7 rounded-lg bg-indigo-600 flex items-center justify-center shrink-0">
                <x-icon name="wrench" class="w-4 h-4 text-white" />
            </div>
            Parque Industrial
        </a>
        <div class="w-6"></div>
    </div>

    <!-- Mobile overlay -->
    <div x-show="sidebarOpen" x-cloak x-transition.opacity @click="sidebarOpen = false" class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden"></div>

    <!-- Sidebar -->
    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 transition-transform duration-200 ease-in-out lg:translate-x-0"
        :class="sidebarOpen ? 'translate-x-0' : ''"
    >
        <div class="flex items-center gap-2 px-6 h-16 border-b border-gray-200 dark:border-gray-800 shrink-0">
            <div class="h-8 w-8 rounded-lg bg-indigo-600 flex items-center justify-center shrink-0">
                <x-icon name="wrench" class="w-5 h-5 text-white" />
            </div>
            <span class="font-semibold text-gray-900 dark:text-gray-100 tracking-tight">Parque Industrial</span>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-6 space-y-1">
            @php
                $navLink = function (string $routeName, string $icon, string $label) {
                    $active = request()->routeIs($routeName);
                    $classes = $active
                        ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400'
                        : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800';
                    $iconClasses = $active ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400 dark:text-gray-500';

                    return compact('active', 'classes', 'iconClasses');
                };
            @endphp

            @php($l = $navLink('dashboard', 'home', 'Dashboard'))
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ $l['classes'] }}">
                <x-icon name="home" class="w-5 h-5 {{ $l['iconClasses'] }}" />
                {{ __('Dashboard') }}
            </a>

            @if (auth()->user()->isAdmin())
                @php($l = $navLink('equipment.index', 'box', 'Equipos'))
                <a href="{{ route('equipment.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ $l['classes'] }}">
                    <x-icon name="box" class="w-5 h-5 {{ $l['iconClasses'] }}" />
                    {{ __('Equipos') }}
                </a>

                @php($l = $navLink('maintenance-plans.index', 'calendar', 'Planes preventivos'))
                <a href="{{ route('maintenance-plans.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ $l['classes'] }}">
                    <x-icon name="calendar" class="w-5 h-5 {{ $l['iconClasses'] }}" />
                    {{ __('Planes preventivos') }}
                </a>

                @php($l = $navLink('users.index', 'users', 'Usuarios'))
                <a href="{{ route('users.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ $l['classes'] }}">
                    <x-icon name="users" class="w-5 h-5 {{ $l['iconClasses'] }}" />
                    {{ __('Usuarios') }}
                </a>
            @endif

            @php($l = $navLink('work-orders.index', 'clipboard', 'Órdenes de trabajo'))
            <a href="{{ route('work-orders.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ $l['classes'] }}">
                <x-icon name="clipboard" class="w-5 h-5 {{ $l['iconClasses'] }}" />
                {{ __('Órdenes de trabajo') }}
            </a>

            @php($l = $navLink('calendar.index', 'calendar', 'Calendario'))
            <a href="{{ route('calendar.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ $l['classes'] }}">
                <x-icon name="calendar" class="w-5 h-5 {{ $l['iconClasses'] }}" />
                {{ __('Calendario') }}
            </a>

            @php($l = $navLink('checkouts.index', 'logbook', 'Bitácora de equipo'))
            <a href="{{ route('checkouts.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ $l['classes'] }}">
                <x-icon name="logbook" class="w-5 h-5 {{ $l['iconClasses'] }}" />
                {{ __('Bitácora de equipo') }}
            </a>

            @php($l = $navLink('work-orders.report', 'alert', 'Reportar falla'))
            <a href="{{ route('work-orders.report') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ $l['classes'] }}">
                <x-icon name="alert" class="w-5 h-5 {{ $l['iconClasses'] }}" />
                {{ __('Reportar falla') }}
            </a>
        </nav>

        <div class="border-t border-gray-200 dark:border-gray-800 p-3 space-y-1 shrink-0">
            <div class="flex items-center gap-3 rounded-lg px-3 py-2">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">
                    {{ Illuminate\Support\Str::of(auth()->user()->name)->substr(0, 1)->upper() }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-gray-900 dark:text-gray-100" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></span>
                    <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ auth()->user()->role->label() }}</span>
                </span>
            </div>

            <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                {{ __('Perfil') }}
            </a>

            <button wire:click="logout" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                <x-icon name="logout" class="w-5 h-5 text-gray-400" />
                {{ __('Cerrar sesión') }}
            </button>
        </div>
    </aside>
</div>
