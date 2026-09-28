import { renderAdd, cartCount, openCart, subscribeCart } from './views/add.js';
import { renderHistory } from './views/history.js';
import { renderTargets } from './views/targets.js';
import { renderToday } from './views/today.js';
import { $ } from './util.js';

const view = $('#view');

const TABS = [
    { route: 'today', label: 'Ma', icon: '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2" stroke-linecap="round"/>' },
    { route: 'add', label: 'Hozzáadás', icon: '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8" stroke-linecap="round"/>' },
    { route: 'history', label: 'Napló', icon: '<path d="M5 19V9M12 19V5M19 19v-7" stroke-linecap="round"/>' },
];

const VIEWS = {
    today: renderToday, add: renderAdd, history: renderHistory, targets: renderTargets,
};

function parseHash() {
    const [path, query = ''] = location.hash.replace(/^#\//, '').split('?');

    return { route: VIEWS[path] ? path : 'today', params: new URLSearchParams(query) };
}

function drawTabs(active) {
    $('#tabs').innerHTML = `<ul class="mx-auto grid max-w-lg grid-cols-3">${TABS.map((tab) => `
        <li><a href="#/${tab.route}" class="flex min-h-16 flex-col items-center justify-center gap-1 text-xs font-medium ${tab.route === active ? 'text-brand' : 'text-muted'}" ${tab.route === active ? 'aria-current="page"' : ''}>
            <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">${tab.icon}</svg>${tab.label}
        </a></li>`).join('')}</ul>`;
}

function drawCartBar() {
    const bar = $('#cart-bar');
    const count = cartCount();

    if (count === 0) {
        bar.innerHTML = '';

        return;
    }

    bar.innerHTML = `<div class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-20 px-4">
        <button class="btn-primary mx-auto flex w-full max-w-lg justify-between shadow-lg" data-open-cart><span>Tálca · ${count} tétel</span><span>Megnyitás ›</span></button>
    </div>`;
    $('[data-open-cart]', bar).addEventListener('click', openCart);
}

function route() {
    const { route: name, params } = parseHash();
    drawTabs(name === 'targets' ? 'history' : name);
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
