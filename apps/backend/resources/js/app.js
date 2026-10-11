const savedTheme = localStorage.getItem('app-cobranzas-theme');
if (savedTheme === 'light' || savedTheme === 'dark') document.documentElement.dataset.theme = savedTheme;

document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-theme-toggle]')) return;
    const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = nextTheme;
    localStorage.setItem('app-cobranzas-theme', nextTheme);
});
