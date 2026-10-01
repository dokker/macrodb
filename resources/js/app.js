import { icon } from './icons.js';
import { renderAdd, cartCount, cartKcal, openCart, subscribeCart } from './views/add.js';
import { renderHistory } from './views/history.js';
import { renderRecipes } from './views/recipes.js';
import { renderTargets } from './views/targets.js';
import { renderToday } from './views/today.js';
import { $, num } from './util.js';

const view = $('#view');

/** The middle slot is the raised "add" button; the other four are regular tabs. */
const TABS = [
    { route: 'today', label: 'Ma', icon: 'home' },
    { route: 'history', label: 'Napló', icon: 'chart' },
    { route: 'add', label: 'Hozzáadás', icon: 'plus' },
    { route: 'recipes', label: 'Receptek', icon: 'book' },
    { route: 'targets', label: 'Célok', icon: 'target' },
];

const VIEWS = {
    today: renderToday, add: renderAdd, history: renderHistory, targets: renderTargets, recipes: renderRecipes,
};

function parseHash() {
    const [path, query = ''] = location.hash.replace(/^#\//, '').split('?');

    return { route: VIEWS[path] ? path : 'today', params: new URLSearchParams(query) };
}

function drawTabs(active) {
    $('#tabs').innerHTML = `<ul class="mx-auto grid max-w-lg grid-cols-5 items-center">${TABS.map((tab) => {
        const current = tab.route === active ? 'aria-current="page"' : '';

        if (tab.route === 'add') {
            return `<li class="flex justify-center"><a href="#/add" ${current} aria-label="${tab.label}"
                class="grid size-13 place-items-center rounded-full bg-brand text-brand-ink shadow-lg shadow-brand/30 transition active:scale-95">${icon('plus', 'size-6')}</a></li>`;
        }

        return `<li><a href="#/${tab.route}" ${current}
            class="flex min-h-16 flex-col items-center justify-center gap-1 text-[11px] ${tab.route === active ? 'font-semibold text-ink' : 'font-medium text-muted'}">
            ${icon(tab.icon, 'size-[22px]')}${tab.label}
        </a></li>`;
    }).join('')}</ul>`;
}

function drawCartBar() {
    const bar = $('#cart-bar');
    const count = cartCount();

    if (count === 0) {
        bar.innerHTML = '';

        return;
    }

    bar.innerHTML = `<div class="fixed inset-x-0 bottom-[calc(4.75rem+env(safe-area-inset-bottom))] z-20 px-4">
        <button class="mx-auto flex w-full max-w-lg items-center gap-3 rounded-2xl bg-primary py-2.5 pl-2.5 pr-4 text-left text-primary-ink shadow-xl shadow-black/20" data-open-cart>
            <span class="badge bg-brand text-brand-ink">${icon('basket')}</span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold">Tálca · ${count} tétel</span>
                <span class="block text-xs tabular-nums opacity-70">${num(cartKcal())} kcal · koppints a mentéshez</span>
            </span>
            ${icon('right')}
        </button>
    </div>`;
    $('[data-open-cart]', bar).addEventListener('click', openCart);
}

function route() {
    const { route: name, params } = parseHash();
    drawTabs(name);
    VIEWS[name](view, params);
    window.scrollTo(0, 0);
}

subscribeCart(drawCartBar);
window.addEventListener('hashchange', route);
route();
drawCartBar();

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}
