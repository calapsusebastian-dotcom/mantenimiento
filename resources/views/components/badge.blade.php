@props(['color' => 'slate'])

@php
$classes = match ($color) {
    'green' => 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-400/10 dark:text-green-400 dark:ring-green-400/20',
    'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-400 dark:ring-amber-400/20',
    'red' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-400 dark:ring-red-400/20',
    'blue' => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-400/10 dark:text-blue-400 dark:ring-blue-400/20',
    'slate' => 'bg-slate-50 text-slate-600 ring-slate-500/20 dark:bg-slate-400/10 dark:text-slate-300 dark:ring-slate-400/20',
    default => 'bg-gray-50 text-gray-600 ring-gray-500/20 dark:bg-gray-400/10 dark:text-gray-300 dark:ring-gray-400/20',
};
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ring-1 ring-inset {$classes}"]) }}>
    {{ $slot }}
</span>
