<x-app-layout>
    <x-page-header title="Perfil" />

    <div class="py-10">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="p-6 sm:p-8 bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </div>

            <div class="p-6 sm:p-8 bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </div>

            <div class="p-6 sm:p-8 bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 shadow-sm rounded-2xl">
                <div class="max-w-xl">
                    <livewire:profile.delete-user-form />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
