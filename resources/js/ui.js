import { icon } from './icons.js';
import { $, num } from './util.js';

/** Modal bottom sheet. Any element with data-close inside it (or a click on the backdrop) closes it. */
export function openSheet(html, { onClose = null } = {}) {
    const root = document.createElement('div');
    root.className = 'fixed inset-0 z-50 flex items-end justify-center sm:items-center';
    root.innerHTML = `
        <div data-close class="absolute inset-0 bg-black/40 backdrop-blur-[2px]"></div>
        <div role="dialog" aria-modal="true" class="relative max-h-[92dvh] w-full max-w-lg overflow-y-auto rounded-t-[28px] bg-surface px-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] pt-3 shadow-2xl sm:rounded-[28px]">
            <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-line sm:hidden" aria-hidden="true"></div>
            ${html}
        </div>`;
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

/** Title row of a sheet with a round close button. */
export const sheetHeader = (title, subtitle = '') => `
    <div class="mb-5 flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-xl font-bold tracking-tight">${title}</h2>
            ${subtitle ? `<p class="mt-1 text-sm text-muted">${subtitle}</p>` : ''}
        </div>
        <button type="button" class="icon-btn -mr-1 border-0 bg-soft" data-close aria-label="Bezárás">${icon('x', 'size-4')}</button>
    </div>`;

/** Top of a page: large title, optional subtitle, back link and right-hand actions. */
export const pageHeader = ({ title, subtitle = '', back = null, actions = '' }) => `
    <header class="mb-6">
        ${back ? `<a href="${back.href}" class="-ml-1 mb-3 inline-flex items-center gap-1 text-sm font-medium text-muted">${icon('back', 'size-4')} ${back.label}</a>` : ''}
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-[28px] font-bold leading-tight tracking-tight">${title}</h1>
                ${subtitle ? `<p class="mt-1 text-[15px] text-muted">${subtitle}</p>` : ''}
            </div>
            ${actions ? `<div class="flex shrink-0 items-center gap-2 pt-1">${actions}</div>` : ''}
        </div>
    </header>`;

export const MACROS = [
    { key: 'protein', label: 'Fehérje', short: 'F', dot: 'bg-protein', text: 'text-protein', tint: 'bg-protein/15' },
    { key: 'carbs', label: 'Szénhidrát', short: 'Sz', dot: 'bg-carbs', text: 'text-carbs', tint: 'bg-carbs/15' },
    { key: 'fat', label: 'Zsír', short: 'Zs', dot: 'bg-fat', text: 'text-fat', tint: 'bg-fat/15' },
];

/** Compact macro line with coloured dots: "● F 12 g  ● Sz 30 g  ● Zs 5 g". */
export const macroDots = (n, decimals = 0) => MACROS.map((macro) => `
    <span class="inline-flex items-center gap-1 whitespace-nowrap"><span class="size-1.5 rounded-full ${macro.dot}" aria-hidden="true"></span><span class="sr-only">${macro.label}</span><span aria-hidden="true">${macro.short}</span> ${num(n[macro.key], decimals)} g</span>`).join('');

/** Four small stat boxes (kcal + macros) for previews inside sheets. */
export const macroStats = (n) => `
    <div class="grid grid-cols-4 gap-2">
        <div class="tile px-2 py-2.5 text-center"><p class="font-semibold tabular-nums">${num(n.kcal)}</p><p class="text-[11px] text-muted">kcal</p></div>
        ${MACROS.map((macro) => `
            <div class="tile px-2 py-2.5 text-center">
                <p class="font-semibold tabular-nums">${num(n[macro.key], 1)}<span class="text-xs font-normal text-muted"> g</span></p>
                <p class="inline-flex items-center gap-1 text-[11px] text-muted"><span class="size-1.5 rounded-full ${macro.dot}"></span>${macro.label}</p>
            </div>`).join('')}
    </div>`;

export function toast(message, kind = 'info') {
    $('#toast')?.remove();

    const el = document.createElement('div');
    el.id = 'toast';
    el.setAttribute('role', 'status');
    el.className = `fixed inset-x-4 bottom-36 z-[60] mx-auto max-w-sm rounded-2xl px-4 py-3 text-center text-sm font-medium shadow-xl ${kind === 'error' ? 'bg-protein text-white' : 'bg-primary text-primary-ink'}`;
    el.textContent = message;
    document.body.append(el);
    setTimeout(() => el.remove(), 3500);
}

export function confirmSheet(message, okLabel = 'Törlés') {
    return new Promise((resolve) => {
        const sheet = openSheet(`
            <p class="mb-6 text-lg font-semibold">${message}</p>
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

export const errorBox = (message) => `<div class="tile my-6 p-6 text-center"><p class="mb-4 font-medium">${message}</p><button class="btn-quiet" data-retry>Újra</button></div>`;
