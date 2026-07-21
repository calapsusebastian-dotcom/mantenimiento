<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center gap-1.5 px-4 py-2 bg-red-600 border border-transparent rounded-lg font-semibold text-sm text-white shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 active:bg-red-700 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
