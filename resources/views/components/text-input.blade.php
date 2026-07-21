@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm focus:border-indigo-500 dark:focus:border-indigo-500 focus:ring-indigo-500 dark:focus:ring-indigo-500 disabled:opacity-50 text-sm']) }}>
