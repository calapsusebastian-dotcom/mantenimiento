// Livewire's wire:navigate link-prefetch cache has a known, non-fatal race
// condition when two `wire:navigate` links point to the same destination on
// the same page (e.g. a sidebar link and a page-header button both going to
// "Reportar falla"). It throws inside a promise chain but never blocks the
// actual navigation, so we swallow just this one specific rejection.
window.addEventListener('unhandledrejection', (event) => {
    if (event.reason instanceof TypeError && event.reason.message === "Cannot set properties of undefined (setting 'html')") {
        event.preventDefault();
    }
});
