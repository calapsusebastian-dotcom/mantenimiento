@props(['href', 'label' => 'Volver al listado'])

<a href="{{ $href }}"
    x-data
    @click.prevent="window.history.length > 1 ? window.history.back() : (window.location.href = $el.getAttribute('href'))"
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline']) }}>
    <x-icon name="arrow-left" class="w-4 h-4" /> {{ $label }}
</a>
