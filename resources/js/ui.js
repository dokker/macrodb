import { $ } from './util.js';

/** Modal bottom sheet. Any element with data-close inside it (or a click on the backdrop) closes it. */
export function openSheet(html, { onClose = null } = {}) {
    const root = document.createElement('div');
    root.className = 'fixed inset-0 z-50 flex items-end justify-center sm:items-center';
    root.innerHTML = `
        <div data-close class="absolute inset-0 bg-black/45"></div>
        <div role="dialog" aria-modal="true" class="relative max-h-[90dvh] w-full max-w-lg overflow-y-auto rounded-t-3xl bg-surface p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] shadow-2xl sm:rounded-3xl">${html}</div>`;
    document.body.append(root);

    const onKey = (event) => event.key === 'Escape' && close();
    const close = () => {
        root.remove();
        document.removeEventListener('keydown', onKey);
        onClose?.();
    };

    document.addEventListener('keydown', onKey);
    root.addEventListener('click', (event) => event.target.closest('[data-close]') && close());

    return { el: root.lastElementChild, close };
}

export function toast(message, kind = 'info') {
    $('#toast')?.remove();

    const el = document.createElement('div');
    el.id = 'toast';
    el.setAttribute('role', 'status');
    el.className = `fixed inset-x-4 bottom-32 z-[60] mx-auto max-w-sm rounded-xl px-4 py-3 text-center text-sm font-medium shadow-lg ${kind === 'error' ? 'bg-protein text-white' : 'bg-ink text-bg'}`;
    el.textContent = message;
    document.body.append(el);
    setTimeout(() => el.remove(), 3500);
}

export function confirmSheet(message, okLabel = 'Törlés') {
    return new Promise((resolve) => {
        const sheet = openSheet(`
            <p class="mb-5 text-lg font-semibold">${message}</p>
            <div class="grid grid-cols-2 gap-3">
                <button class="btn-quiet" data-close>Mégse</button>
                <button class="btn-danger" data-ok>${okLabel}</button>
            </div>`, { onClose: () => resolve(false) });

        $('[data-ok]', sheet.el).addEventListener('click', () => {
            resolve(true);
            sheet.close();
        });
    });
}

export const spinner = (label = 'Betöltés…') => `<div class="flex items-center justify-center gap-3 py-16 text-muted"><span class="size-5 animate-spin rounded-full border-2 border-line border-t-brand"></span>${label}</div>`;

export const errorBox = (message) => `<div class="card my-6 p-5 text-center"><p class="mb-4 font-medium">${message}</p><button class="btn-quiet" data-retry>Újra</button></div>`;
