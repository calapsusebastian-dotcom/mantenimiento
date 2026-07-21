<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-sm text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 active:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
