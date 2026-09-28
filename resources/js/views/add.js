import { api } from '../api.js';
import { shrinkImage, startScanner } from '../scanner.js';
import { errorBox, openSheet, spinner, toast } from '../ui.js';
import {
    $, $$, MEAL_TYPES, SOURCE_LABELS, debounce, defaultMealType, esc, num, scaleNutrients, sumNutrients, todayStr, toLocalInput,
} from '../util.js';

const STORAGE_KEY = 'macrodb.cart';

/** The tray (cart) survives navigation and reloads until it is saved. */
const cart = (() => {
    try {
        return { items: [], mealType: null, eatenAt: null, ...JSON.parse(sessionStorage.getItem(STORAGE_KEY) ?? '{}') };
    } catch {
        return { items: [], mealType: null, eatenAt: null };
    }
})();

const persist = () => {
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
    } catch {
        // Storage may be unavailable; the tray then lives in memory only.
    }
};

const cartTotals = () => sumNutrients(cart.items.map((item) => scaleNutrients(item.per_100g, item.grams)));

let results = [];
let query = '';
let onCartChange = () => {};

export const cartCount = () => cart.items.length;
export const subscribeCart = (fn) => { onCartChange = fn; };
const changed = () => { persist(); onCartChange(); };

export function renderAdd(view, params) {
    const date = params.get('date');

    if (date && !cart.eatenAt) {
        cart.eatenAt = date === todayStr() ? null : `${date}T12:00`;
    }

    view.innerHTML = `
        <header class="mb-4"><h1 class="text-xl font-bold">Étel hozzáadása</h1></header>
        <div class="flex gap-2">
            <label class="sr-only" for="search">Keresés</label>
            <input id="search" class="field" type="search" enterkeyhint="search" autocomplete="off" placeholder="Pl. zab, tojás, rántott hús" value="${esc(query)}">
            <button class="btn-quiet !px-3" data-scan aria-label="Vonalkód beolvasása">${icon('barcode')}</button>
            <button class="btn-quiet !px-3" data-photo aria-label="Fotó alapú felismerés">${icon('camera')}</button>
        </div>
        <section id="results" class="mt-4" aria-live="polite"></section>`;

    const input = $('#search', view);
    const search = debounce(runSearch, 250);
    input.addEventListener('input', () => search(input.value.trim()));
    $('[data-scan]', view).addEventListener('click', openScanner);
    $('[data-photo]', view).addEventListener('click', openPhoto);

    renderResults();

    if (!query) {
        input.focus();
    }
}

async function runSearch(text) {
    query = text;

    if (!text) {
        results = [];

        return renderResults();
    }

    const box = $('#results');
    box.innerHTML = spinner('Keresés…');

    try {
        results = await api(`/foods?q=${encodeURIComponent(text)}&limit=20`);
    } catch (error) {
        box.innerHTML = errorBox(esc(error.message));
        $('[data-retry]', box).addEventListener('click', () => runSearch(text));

        return;
    }

    if (query === text) {
        renderResults();
    }
}

function renderResults() {
    const box = $('#results');

    if (!box) return;

    if (!query) {
        box.innerHTML = '<p class="py-10 text-center text-muted">Kezdj el gépelni, vagy használd a vonalkód- és fotógombot.</p>';

        return;
    }

    const rows = results.map((food, index) => `
        <li>
            <button data-pick="${index}" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left">
                <span class="min-w-0">
                    <span class="block truncate font-medium">${esc(food.name)}</span>
                    <span class="block truncate text-sm text-muted">${[food.brand, food.type === 'recipe' ? 'Fogás' : SOURCE_LABELS[food.source], food.aliases[0]].filter(Boolean).map(esc).join(' · ')}</span>
                </span>
                <span class="shrink-0 text-right text-sm tabular-nums text-muted">${num(food.per_100g.kcal)} kcal<br>/100 g</span>
            </button>
        </li>`).join('');

    box.innerHTML = `
        ${results.length ? `<ul class="card divide-y divide-line overflow-hidden">${rows}</ul>` : `<p class="py-6 text-center text-muted">Nincs találat erre: „${esc(query)}”.</p>`}
        <button class="btn-quiet mt-4 w-full" data-new>+ Új étel felvétele</button>`;

    $$('[data-pick]', box).forEach((button) => button.addEventListener('click', () => pickAmount(results[Number(button.dataset.pick)], { sourceText: query })));
    $('[data-new]', box).addEventListener('click', () => newFood({ name: query }));
}

/** Choose grams for a food or recipe, then put it on the tray. */
function pickAmount(food, { sourceText, inputMethod = 'szöveg' }) {
    const portions = [...(food.portions ?? [])];

    if (food.default_portion_g && !portions.some((portion) => portion.grams === food.default_portion_g)) {
        portions.unshift({ label: 'Alap adag', grams: food.default_portion_g });
    }

    const sheet = openSheet(`
        <h2 class="text-lg font-bold">${esc(food.name)}</h2>
        <p class="mb-4 text-sm text-muted">${[food.brand, `${num(food.per_100g.kcal)} kcal / 100 g`].filter(Boolean).map(esc).join(' · ')}</p>
        ${portions.length ? `<div class="mb-4 flex flex-wrap gap-2" role="group" aria-label="Adagok">${portions.map((portion) => `<button class="chip" data-grams="${portion.grams}">${esc(portion.label)} · ${num(portion.grams)} g</button>`).join('')}</div>` : ''}
        <label class="label" for="amount">Mennyiség (gramm)</label>
        <input id="amount" class="field mb-3 mt-1" type="number" inputmode="decimal" min="1" step="any" value="${food.default_portion_g ?? 100}">
        <p class="mb-5 text-sm tabular-nums text-muted" data-preview></p>
        <div class="grid grid-cols-2 gap-3">
            <button class="btn-quiet" data-close>Mégse</button>
            <button class="btn-primary" data-add>Hozzáadás</button>
        </div>`);

    const amount = $('#amount', sheet.el);
    const update = () => {
        const n = scaleNutrients(food.per_100g, Number(amount.value) || 0);
        $('[data-preview]', sheet.el).textContent = `${num(n.kcal)} kcal · F ${num(n.protein, 1)} g · Sz ${num(n.carbs, 1)} g · Zs ${num(n.fat, 1)} g`;
    };

    amount.addEventListener('input', update);
    $$('[data-grams]', sheet.el).forEach((chip) => chip.addEventListener('click', () => {
        amount.value = chip.dataset.grams;
        update();
    }));
    update();

    $('[data-add]', sheet.el).addEventListener('click', () => {
        const grams = Number(amount.value);

        if (!(grams > 0)) {
            amount.focus();

            return;
        }

        addToCart(food, grams, sourceText, inputMethod);
        sheet.close();
        toast(`${food.name} hozzáadva`);
    });
}

function addToCart(food, grams, sourceText, inputMethod) {
    cart.items.push({
        food_id: food.type === 'recipe' ? null : food.id,
        recipe_id: food.type === 'recipe' ? food.id : null,
        name: food.name,
        grams,
        per_100g: food.per_100g,
        source_text: sourceText || food.name,
        input_method: inputMethod,
        alternatives: [],
    });
    changed();

    return cart.items[cart.items.length - 1];
}

function newFood(prefill = {}) {
    const sheet = openSheet(`
        <h2 class="mb-1 text-lg font-bold">Új étel</h2>
        <p class="mb-4 text-sm text-muted">Tápértékek 100 g-ra (folyadéknál 100 ml-re).</p>
        <form class="space-y-3" novalidate>
            <div><label class="label" for="nf-name">Név</label><input id="nf-name" name="name" class="field mt-1" required value="${esc(prefill.name ?? '')}"></div>
            <div class="grid grid-cols-2 gap-3">
                ${[['kcal', 'Kalória (kcal)'], ['protein', 'Fehérje (g)'], ['carbs', 'Szénhidrát (g)'], ['fat', 'Zsír (g)']].map(([name, label]) => `<div><label class="label" for="nf-${name}">${label}</label><input id="nf-${name}" name="${name}" class="field mt-1" type="number" inputmode="decimal" min="0" step="any" required></div>`).join('')}
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label" for="nf-barcode">Vonalkód</label><input id="nf-barcode" name="barcode" class="field mt-1" inputmode="numeric" value="${esc(prefill.barcode ?? '')}"></div>
                <div><label class="label" for="nf-serving">Adag (g)</label><input id="nf-serving" name="serving_size_g" class="field mt-1" type="number" inputmode="decimal" min="1" step="any"></div>
            </div>
            <p class="h-5 text-sm text-protein" data-error></p>
            <div class="grid grid-cols-2 gap-3">
                <button type="button" class="btn-quiet" data-close>Mégse</button>
                <button type="submit" class="btn-primary">Mentés</button>
            </div>
        </form>`);

    $('form', sheet.el).addEventListener('submit', async (event) => {
        event.preventDefault();

        const body = Object.fromEntries([...new FormData(event.target)].filter(([, value]) => value !== ''));

        try {
            const food = await api('/foods', { method: 'POST', body });
            sheet.close();
            pickAmount(food, { sourceText: food.name, inputMethod: prefill.barcode ? 'vonalkód' : 'szöveg' });
        } catch (error) {
            $('[data-error]', sheet.el).textContent = error.message;
        }
    });
}

function openScanner() {
    let stop = null;
    const sheet = openSheet(`
        <h2 class="mb-3 text-lg font-bold">Vonalkód</h2>
        <div id="reader" class="mb-3 min-h-16 overflow-hidden rounded-xl bg-bg"></div>
        <p class="mb-3 text-sm text-muted" data-status>Kamera indítása…</p>
        <form class="flex gap-2" data-manual>
            <label class="sr-only" for="code">Vonalkód beírása</label>
            <input id="code" class="field" inputmode="numeric" pattern="[0-9]*" placeholder="Vagy írd be a számot">
            <button class="btn-primary">Keresés</button>
        </form>
        <button class="btn-quiet mt-3 w-full" data-close>Mégse</button>`, { onClose: () => stop?.() });

    const lookup = async (code) => {
        $('[data-status]', sheet.el).textContent = `Keresés: ${code}…`;
        await stop?.();
        stop = null;

        try {
            const food = await api(`/foods/barcode/${encodeURIComponent(code)}`);
            sheet.close();
            pickAmount(food, { sourceText: food.name, inputMethod: 'vonalkód' });
        } catch (error) {
            sheet.close();

            if (error.status === 404) {
                toast(`Nincs találat a vonalkódra (${code}). Vidd fel kézzel.`, 'error');
                newFood({ barcode: code });
            } else {
                toast(error.message, 'error');
            }
        }
    };

    $('[data-manual]', sheet.el).addEventListener('submit', (event) => {
        event.preventDefault();
        const code = $('#code', sheet.el).value.trim();

        if (code) lookup(code);
    });

    startScanner('reader', lookup)
        .then((stopper) => {
            stop = stopper;
            $('[data-status]', sheet.el).textContent = 'Tartsd a vonalkódot a keret elé.';
        })
        .catch(() => {
            $('#reader', sheet.el).remove();
            $('[data-status]', sheet.el).textContent = 'A kamera nem érhető el (engedély vagy HTTPS hiányzik). Írd be a számot.';
        });
}

function openPhoto() {
    const sheet = openSheet(`
        <h2 class="mb-1 text-lg font-bold">Fotó alapú felvitel</h2>
        <p class="mb-4 text-sm text-muted">A felismerés csak piszkozatot ad, a grammokat és a találatokat átnézheted mentés előtt.</p>
        <label class="label" for="photo-note">Megjegyzés (opcionális)</label>
        <input id="photo-note" class="field mb-4 mt-1" maxlength="500" placeholder="Pl. fél adag, olajban sütve">
        <label class="btn-primary w-full" for="photo-file">Fotó készítése vagy kiválasztása</label>
        <input id="photo-file" class="sr-only" type="file" accept="image/*" capture="environment">
        <p class="mt-3 h-5 text-sm text-protein" data-error></p>
        <button class="btn-quiet mt-1 w-full" data-close>Mégse</button>`);

    $('#photo-file', sheet.el).addEventListener('change', async (event) => {
        const file = event.target.files[0];

        if (!file) return;

        const note = $('#photo-note', sheet.el).value.trim();
        sheet.el.innerHTML = spinner('Felismerés folyamatban…');

        try {
            const form = new FormData();
            form.append('image', await shrinkImage(file));

            if (note) form.append('note', note);

            const draft = await api('/meals/photo', { method: 'POST', form });
            sheet.close();
            applyDraft(draft.items);
        } catch (error) {
            sheet.close();
            toast(error.message, 'error');
        }
    });
}

function applyDraft(items) {
    const missing = [];

    items.forEach((item) => {
        if (item.match) {
            addToCart(item.match, item.grams, item.source_text, 'fotó').alternatives = item.alternatives;
        } else {
            missing.push(item.detected.name_hu);
        }
    });

    if (missing.length) {
        toast(`Nem találtam párost: ${missing.join(', ')}. Keresd meg kézzel.`, 'error');
        query = missing[0];
        runSearch(query).then(() => { const input = $('#search'); if (input) input.value = query; });
    } else if (items.length === 0) {
        toast('Nem ismertem fel ételt a képen.', 'error');
    }

    if (items.length > missing.length) {
        openCart();
    }
}

export function openCart() {
    if (!cart.eatenAt) cart.eatenAt = toLocalInput(new Date());
    if (!cart.mealType) cart.mealType = defaultMealType();

    const sheet = openSheet('<div data-body></div>');

    const draw = () => {
        const body = $('[data-body]', sheet.el);

        if (cart.items.length === 0) {
            body.innerHTML = '<p class="py-8 text-center text-muted">A tálca üres.</p><button class="btn-quiet w-full" data-close>Bezár</button>';

            return;
        }

        body.innerHTML = `
            <h2 class="mb-3 text-lg font-bold">Tálca</h2>
            <ul class="divide-y divide-line">
                ${cart.items.map((item, index) => `
                    <li class="flex items-center gap-3 py-3">
                        <div class="min-w-0 flex-1"><p class="truncate font-medium">${esc(item.name)}</p><p class="text-sm tabular-nums text-muted" data-line="${index}"></p>${swapSelect(item, index)}</div>
                        <label class="sr-only" for="g-${index}">${esc(item.name)} grammja</label>
                        <input id="g-${index}" data-grams="${index}" class="field !w-24 !px-3 !py-2 text-right" type="number" inputmode="decimal" min="1" step="any" value="${item.grams}">
                        <button class="btn-quiet !min-h-10 !px-3" data-remove="${index}" aria-label="${esc(item.name)} eltávolítása">✕</button>
                    </li>`).join('')}
            </ul>
            <p class="my-3 rounded-xl bg-bg p-3 text-sm font-medium tabular-nums" data-total></p>
            <div class="mb-3 flex flex-wrap gap-2" role="group" aria-label="Étkezés típusa">
                ${Object.entries(MEAL_TYPES).map(([value, label]) => `<button class="chip" data-type="${value}" aria-pressed="${cart.mealType === value}">${label}</button>`).join('')}
            </div>
            <label class="label" for="eaten-at">Időpont</label>
            <input id="eaten-at" class="field mb-2 mt-1" type="datetime-local" value="${cart.eatenAt}">
            <p class="mb-3 h-5 text-sm text-protein" data-error></p>
            <div class="grid grid-cols-2 gap-3">
                <button class="btn-quiet" data-close>Vissza</button>
                <button class="btn-primary" data-save>Mentés</button>
            </div>`;

        const refreshTotals = () => {
            cart.items.forEach((item, index) => {
                const n = scaleNutrients(item.per_100g, item.grams);
                $(`[data-line="${index}"]`, body).textContent = `${num(n.kcal)} kcal · F ${num(n.protein, 1)} · Sz ${num(n.carbs, 1)} · Zs ${num(n.fat, 1)}`;
            });
            const total = cartTotals();
            $('[data-total]', body).textContent = `Összesen: ${num(total.kcal)} kcal · F ${num(total.protein, 1)} g · Sz ${num(total.carbs, 1)} g · Zs ${num(total.fat, 1)} g`;
        };

        refreshTotals();

        $$('[data-grams]', body).forEach((input) => input.addEventListener('input', () => {
            cart.items[Number(input.dataset.grams)].grams = Number(input.value) || 0;
            persist();
            refreshTotals();
            onCartChange();
        }));
        $$('[data-swap]', body).forEach((select) => select.addEventListener('change', () => {
            swapMatch(cart.items[Number(select.dataset.swap)], Number(select.value));
            changed();
            draw();
        }));
        $$('[data-remove]', body).forEach((button) => button.addEventListener('click', () => {
            cart.items.splice(Number(button.dataset.remove), 1);
            changed();
            draw();
        }));
        $$('[data-type]', body).forEach((chip) => chip.addEventListener('click', () => {
            cart.mealType = chip.dataset.type;
            persist();
            $$('[data-type]', body).forEach((other) => other.setAttribute('aria-pressed', String(other === chip)));
        }));
        $('#eaten-at', body).addEventListener('change', (event) => {
            cart.eatenAt = event.target.value;
            persist();
        });
        $('[data-save]', body).addEventListener('click', () => save(sheet, body));
    };

    draw();
}

function swapSelect(item, index) {
    if (!item.alternatives?.length) return '';

    return `<label class="sr-only" for="swap-${index}">${esc(item.name)} cseréje</label>
        <select id="swap-${index}" data-swap="${index}" class="mt-1 max-w-full rounded-lg border border-line bg-bg px-2 py-1 text-sm">
            <option value="-1">Nem ez? Csere…</option>
            ${item.alternatives.map((alternative, i) => `<option value="${i}">${esc(alternative.name)} (${num(alternative.per_100g.kcal)} kcal/100 g)</option>`).join('')}
        </select>`;
}

/** Swaps a photo match for one of its alternatives, keeping the estimated grams; the old match becomes an alternative. */
function swapMatch(item, index) {
    if (index < 0) return;

    const [chosen] = item.alternatives.splice(index, 1);

    item.alternatives.push({
        type: item.recipe_id ? 'recipe' : 'food', id: item.recipe_id ?? item.food_id, name: item.name, per_100g: item.per_100g,
    });
    item.food_id = chosen.type === 'recipe' ? null : chosen.id;
    item.recipe_id = chosen.type === 'recipe' ? chosen.id : null;
    item.name = chosen.name;
    item.per_100g = chosen.per_100g;
}

async function save(sheet, body) {
    const error = $('[data-error]', body);

    if (cart.items.some((item) => !(item.grams > 0))) {
        error.textContent = 'Minden tételhez pozitív grammot adj meg.';

        return;
    }

    const eatenAt = new Date(cart.eatenAt);

    if (Number.isNaN(eatenAt.getTime())) {
        error.textContent = 'Adj meg érvényes időpontot.';

        return;
    }

    try {
        await api('/meals', {
            method: 'POST',
            body: {
                eaten_at: eatenAt.toISOString(),
                meal_type: cart.mealType,
                items: cart.items.map((item) => ({
                    food_id: item.food_id,
                    recipe_id: item.recipe_id,
                    grams: item.grams,
                    source_text: item.source_text,
                    input_method: item.input_method,
                })),
            },
        });
    } catch (failure) {
        error.textContent = failure.message;

        return;
    }

    const day = eatenAt.toLocaleDateString('sv-SE', { timeZone: 'Europe/Budapest' });
    cart.items = [];
    cart.eatenAt = null;
    cart.mealType = null;
    query = '';
    results = [];
    changed();
    sheet.close();
    toast('Étkezés mentve');
    location.hash = `#/today?date=${day}`;
}

function icon(name) {
    const paths = {
        barcode: '<path d="M4 6v12M8 6v12M12 6v12M16 6v12M20 6v12" stroke-linecap="round"/><path d="M6 6v12M14 6v12M18 6v12" stroke-width="3" opacity=".0"/>',
        camera: '<path d="M4 8h3l2-3h6l2 3h3v11H4z" stroke-linejoin="round"/><circle cx="12" cy="13" r="3.5"/>',
    };

    return `<svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">${paths[name]}</svg>`;
}
