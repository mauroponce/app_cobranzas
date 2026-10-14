const savedTheme = localStorage.getItem('app-cobranzas-theme');
if (savedTheme === 'light' || savedTheme === 'dark') document.documentElement.dataset.theme = savedTheme;

document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-theme-toggle]')) return;
    const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = nextTheme;
    localStorage.setItem('app-cobranzas-theme', nextTheme);
});

document.addEventListener('livewire:init', () => {
    const dashboard = document.querySelector('[data-collections-stream-url]');
    if (! dashboard || dashboard.dataset.collectionsStreamConnected) return;

    dashboard.dataset.collectionsStreamConnected = 'true';
    let revision = '';
    let source;

    const connect = () => {
        const url = new URL(dashboard.dataset.collectionsStreamUrl, window.location.origin);
        if (revision) url.searchParams.set('revision', revision);
        source = new EventSource(url);
        source.addEventListener('collections.changed', (event) => {
            revision = event.lastEventId || revision;
            const componentId = dashboard.getAttribute('wire:id');
            window.Livewire.find(componentId)?.$refresh();
            const toast = document.createElement('div');
            toast.className = 'alert alert-success fixed right-5 top-5 z-50 max-w-sm shadow-lg';
            toast.setAttribute('role', 'status');
            toast.textContent = 'Nueva declaración recibida. La tabla de cobranzas fue actualizada.';
            document.body.append(toast);
            window.setTimeout(() => toast.remove(), 5000);
        });
    };

    connect();
    window.addEventListener('beforeunload', () => source?.close(), { once: true });
});
