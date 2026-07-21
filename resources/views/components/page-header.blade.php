@props(['title'])

<div class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800">
    <div class="px-4 sm:px-6 lg:px-8 py-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $title }}</h1>

        @isset($actions)
            <div class="flex items-center gap-3">{{ $actions }}</div>
        @endisset
    </div>
</div>
