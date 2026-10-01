import { api } from '../api.js';
import { shrinkImage, startScanner } from '../scanner.js';
import { renameFood } from './food-name.js';
import { openRecipeForm } from './recipe.js';
import { MEAL_ICONS, icon } from '../icons.js';
import {
    confirmSheet, errorBox, macroDots, macroStats, openSheet, pageHeader, sheetHeader, spinner, toast,
} from '../ui.js';
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
export const cartKcal = () => cartTotals().kcal;
export const subscribeCart = (fn) => { onCartChange = fn; };
const changed = () => { persist(); onCartChange(); };

export function renderAdd(view, params) {
    const date = params.get('date');

    if (date && !cart.eatenAt) {
        cart.eatenAt = date === todayStr() ? null : `${date}T12:00`;
    }

    view.innerHTML = `
        ${pageHeader({ title: 'Hozzáadás', subtitle: 'Keress, olvass be vonalkódot, vagy fotózd le, amit eszel.' })}
        <div class="relative">
            <label class="sr-only" for="search">Keresés</label>
            <span class="pointer-events-none absolute inset-y-0 left-4 grid place-items-center text-muted">${icon('search')}</span>
            <input id="search" class="field !rounded-full !pl-12" type="search" enterkeyhint="search" autocomplete="off" placeholder="Pl. zab, tojás, rántott hús" value="${esc(query)}">
        </div>
        <div class="mt-3 grid grid-cols-2 gap-2">
            <button class="tile flex items-center gap-3 p-3 text-left transition active:scale-[.98]" data-scan>
                <span class="badge bg-brand text-brand-ink">${icon('barcode')}</span>
                <span class="min-w-0"><span class="block text-sm font-semibold">Vonalkód</span><span class="block text-xs text-muted">Bolti termék</span></span>
            </button>
            <button class="tile flex items-center gap-3 p-3 text-left transition active:scale-[.98]" data-photo>
                <span class="badge bg-brand text-brand-ink">${icon('camera')}</span>
                <span class="min-w-0"><span class="block text-sm font-semibold">Fotó</span><span class="block text-xs text-muted">Felismerés képről</span></span>
            </button>
        </div>
        <section id="results" class="mt-6" aria-live="polite"></section>`;

    const input = $('#search', view);
    const search = debounce(runSearch, 250);
    input.addEventListener('input', () => search(input.value.trim()));
    $('[data-scan]', view).addEventListener('click', openScanner);
    $('[data-photo]', view).addEventListener('click', openPhoto);

    renderResults();

    const open = params.get('open');

    if (open) {
        params.delete('open');
        history.replaceState(null, '', `#/add${params.size ? `?${params}` : ''}`);
    }

    if (open === 'scan') {
        openScanner();
    } else if (open === 'photo') {
        openPhoto();
    } else if (!query) {
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

    const createRows = `
        <h2 class="section-title mb-2 ${query ? 'mt-8' : ''}">Saját felvitel</h2>
        <ul class="card divide-y divide-line overflow-hidden">
            <li><button class="flex w-full items-center gap-3 px-4 py-3 text-left transition active:bg-soft" data-new>
                <span class="badge bg-soft text-ink">${icon('leaf', 'size-[18px]')}</span>
                <span class="min-w-0 flex-1"><span class="block text-[15px] font-medium">Új étel</span><span class="block text-xs text-muted">Tápértékek 100 g-ra, akár vonalkóddal</span></span>
                <span class="text-muted">${icon('right', 'size-4')}</span>
            </button></li>
            <li><button class="flex w-full items-center gap-3 px-4 py-3 text-left transition active:bg-soft" data-new-recipe>
                <span class="badge bg-soft text-ink">${icon('bowl', 'size-[18px]')}</span>
                <span class="min-w-0 flex-1"><span class="block text-[15px] font-medium">Új recept</span><span class="block text-xs text-muted">Összetevőkből, a kész étel tömegével</span></span>
                <span class="text-muted">${icon('right', 'size-4')}</span>
            </button></li>
        </ul>`;

    const rows = results.map((food, index) => `
        <li>
            <button data-pick="${index}" class="flex w-full items-center gap-3 px-4 py-3 text-left transition active:bg-soft">
                <span class="badge ${food.type === 'recipe' ? 'bg-brand/10 text-brand' : 'bg-soft text-ink'}">${icon(food.type === 'recipe' ? 'bowl' : 'leaf', 'size-[18px]')}</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-[15px] font-medium">${esc(food.name)}</span>
                    <span class="block truncate text-xs text-muted">${[food.brand, food.type === 'recipe' ? 'Fogás' : SOURCE_LABELS[food.source], food.aliases[0]].filter(Boolean).map(esc).join(' · ')}</span>
                </span>
                <span class="shrink-0 text-right text-sm font-medium tabular-nums">${num(food.per_100g.kcal)} kcal<span class="block text-xs font-normal text-muted">/100 g</span></span>
            </button>
        </li>`).join('');

    box.innerHTML = !query ? createRows : `
        ${results.length
            ? `<p class="section-title mb-2">Találatok <span class="font-normal text-muted">${results.length}</span></p><ul class="card divide-y divide-line overflow-hidden">${rows}</ul>`
            : `<div class="tile p-6 text-center"><p class="font-semibold">Nincs találat erre: „${esc(query)}”</p><p class="mt-1 text-sm text-muted">Próbálj rövidebb szót, vagy vidd fel lent.</p></div>`}
        ${createRows}`;

    $$('[data-pick]', box).forEach((button) => button.addEventListener('click', () => pickAmount(results[Number(button.dataset.pick)], { sourceText: query })));
    $('[data-new]', box).addEventListener('click', () => newFood({ name: query }));
    $('[data-new-recipe]', box).addEventListener('click', () => openRecipeForm({
        name: query,
        onSaved: (recipe) => pickAmount(recipe, { sourceText: recipe.name }),
    }));
}

/** Choose grams for a food or recipe, then put it on the tray. */
function pickAmount(food, { sourceText, inputMethod = 'szöveg' }) {
    const portions = [...(food.portions ?? [])];

    if (food.default_portion_g && !portions.some((portion) => portion.grams === food.default_portion_g)) {
        portions.unshift({ label: 'Alap adag', grams: food.default_portion_g });
    }

    const sheet = openSheet(`
        ${sheetHeader(esc(food.name), [food.brand, `${num(food.per_100g.kcal)} kcal / 100 g`].filter(Boolean).map(esc).join(' · '))}
        ${portions.length ? `<div class="no-scrollbar -mx-5 mb-4 flex gap-2 overflow-x-auto px-5" role="group" aria-label="Adagok">${portions.map((portion) => `<button class="chip" data-grams="${portion.grams}">${esc(portion.label)} · ${num(portion.grams)} g</button>`).join('')}</div>` : ''}
        <label class="label" for="amount">Mennyiség (gramm)</label>
        <input id="amount" class="field mb-3 mt-1.5 text-lg font-semibold" type="number" inputmode="decimal" min="1" step="any" value="${food.default_portion_g ?? 100}">
        <div class="mb-5" data-preview aria-live="polite"></div>
        <button class="btn-primary w-full" data-add>${icon('plus')} Tálcára</button>
        ${food.type === 'recipe' ? '' : `<div class="mt-3 flex justify-center gap-1">
            <button class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-muted" data-rename>${icon('pencil', 'size-4')} Átnevezés</button>
            <button class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-muted" data-aliases></button>
        </div>`}`);

    const amount = $('#amount', sheet.el);
    const aliasButton = $('[data-aliases]', sheet.el);
    const renderAliasButton = () => {
        aliasButton.innerHTML = `${icon('search', 'size-4')} Keresőnevek${food.aliases?.length ? ` · ${food.aliases.length}` : ''}`;
    };

    $('[data-rename]', sheet.el)?.addEventListener('click', () => renameFood(food, (saved) => {
        food.name = saved.name;
        food.original_name = saved.original_name;
        $('h2', sheet.el).textContent = food.name;
        renderResults();
    }));

    if (aliasButton) {
        renderAliasButton();
        aliasButton.addEventListener('click', () => editAliases(food, () => {
            renderAliasButton();
            renderResults();
        }));
    }

    const update = () => {
        $('[data-preview]', sheet.el).innerHTML = macroStats(scaleNutrients(food.per_100g, Number(amount.value) || 0));
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

/** Add, rename and delete the search names of a food; `onChange` runs after every saved change. */
function editAliases(food, onChange) {
    let aliases = [];
    let editing = null;

    const sheet = openSheet(`
        ${sheetHeader('Keresőnevek', `${esc(food.name)} · a keresés ezeken a neveken is megtalálja`)}
        <ul class="mb-5 divide-y divide-line" data-list aria-live="polite">${spinner()}</ul>
        <form class="flex gap-2" data-new-alias>
            <label class="sr-only" for="alias-name">Új keresőnév</label>
            <input id="alias-name" class="field" autocomplete="off" maxlength="255" placeholder="Új keresőnév, pl. zabpehely" required>
            <button class="btn-primary shrink-0" aria-label="Keresőnév hozzáadása">${icon('plus')}</button>
        </form>`);

    const list = $('[data-list]', sheet.el);

    const saved = (next) => {
        aliases = next.sort((a, b) => a.name.localeCompare(b.name, 'hu'));
        food.aliases = aliases.map((alias) => alias.name);
        editing = null;
        render();
        onChange();
    };

    const render = () => {
        list.innerHTML = aliases.length ? aliases.map((alias) => (editing === alias.id ? `
            <li><form class="flex items-center gap-2 py-2" data-rename="${alias.id}">
                <label class="sr-only" for="alias-${alias.id}">Keresőnév</label>
                <input id="alias-${alias.id}" class="field" autocomplete="off" maxlength="255" value="${esc(alias.name)}" required>
                <button class="btn-primary shrink-0">Mentés</button>
                <button type="button" class="icon-btn" data-cancel aria-label="Mégse">${icon('x', 'size-4')}</button>
            </form></li>` : `
            <li class="flex items-center gap-2 py-2">
                <span class="min-w-0 flex-1 truncate text-[15px]">${esc(alias.name)}${alias.lang === 'hu' ? '' : ` <span class="text-xs text-muted">${esc(alias.lang)}</span>`}</span>
                <button class="icon-btn" data-edit="${alias.id}" aria-label="${esc(alias.name)} átnevezése">${icon('pencil', 'size-4')}</button>
                <button class="icon-btn text-muted" data-remove="${alias.id}" aria-label="${esc(alias.name)} törlése">${icon('trash', 'size-4')}</button>
            </li>`)).join('') : '<li class="py-3 text-sm text-muted">Még nincs keresőneve.</li>';

        $$('[data-edit]', list).forEach((button) => button.addEventListener('click', () => {
            editing = Number(button.dataset.edit);
            render();
            $(`#alias-${editing}`, list)?.select();
        }));

        $('[data-cancel]', list)?.addEventListener('click', () => {
            editing = null;
            render();
        });

        $('[data-rename]', list)?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const id = Number(event.currentTarget.dataset.rename);
            const name = $(`#alias-${id}`, list).value.trim();

            try {
                const alias = await api(`/foods/${food.id}/aliases/${id}`, { method: 'PATCH', body: { name } });
                saved(aliases.map((current) => (current.id === id ? alias : current)));
                toast('Keresőnév átnevezve');
            } catch (error) {
                toast(error.message, 'error');
            }
        });

        $$('[data-remove]', list).forEach((button) => button.addEventListener('click', async () => {
            const id = Number(button.dataset.remove);
            const alias = aliases.find((current) => current.id === id);

            if (!await confirmSheet(`Törlöd ezt a keresőnevet: ${esc(alias.name)}?`)) return;

            try {
                await api(`/foods/${food.id}/aliases/${id}`, { method: 'DELETE' });
                saved(aliases.filter((current) => current.id !== id));
                toast('Keresőnév törölve');
            } catch (error) {
                toast(error.message, 'error');
            }
        }));
    };

    const load = async () => {
        list.innerHTML = spinner();

        try {
            aliases = await api(`/foods/${food.id}/aliases`);
            render();
        } catch (error) {
            list.innerHTML = `<li>${errorBox(esc(error.message))}</li>`;
            $('[data-retry]', list).addEventListener('click', load);
        }
    };

    $('[data-new-alias]', sheet.el).addEventListener('submit', async (event) => {
        event.preventDefault();
        const input = $('#alias-name', sheet.el);
        const name = input.value.trim();

        if (!name) return;

        try {
            const alias = await api(`/foods/${food.id}/aliases`, { method: 'POST', body: { name } });
            input.value = '';

            if (aliases.some((current) => current.id === alias.id)) {
                toast('Ez a keresőnév már megvan');
            } else {
                saved([...aliases, alias]);
                toast('Keresőnév hozzáadva');
            }
        } catch (error) {
            toast(error.message, 'error');
        }
    });

    load();
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

const NUTRIENT_FIELDS = ['kcal', 'protein', 'carbs', 'fat'];

/**
 * Sheet for a new food. The values can be typed per 100 g or for one portion; in portion mode they are converted to
 * per 100 g before saving, and the portion weight becomes the food's default portion.
 */
function newFood(prefill = {}) {
    let perPortion = false;
    const sheet = openSheet(`
        ${sheetHeader('Új étel', 'Tápértékek 100 g-ra vagy egy adagra (folyadéknál ml).')}
        <form class="space-y-3" novalidate>
            <div><label class="label" for="nf-name">Név</label><input id="nf-name" name="name" class="field mt-1.5" required value="${esc(prefill.name ?? '')}"></div>
            <div>
                <p class="label mb-1.5">Az értékek vonatkoznak</p>
                <div class="flex gap-2" role="group" aria-label="Az értékek vonatkoznak">
                    <button type="button" class="chip" data-basis="100g" aria-pressed="true">100 g-ra</button>
                    <button type="button" class="chip" data-basis="portion" aria-pressed="false">Egy adagra</button>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                ${[['kcal', 'Kalória (kcal)'], ['protein', 'Fehérje (g)'], ['carbs', 'Szénhidrát (g)'], ['fat', 'Zsír (g)']].map(([name, label]) => `<div><label class="label" for="nf-${name}">${label}</label><input id="nf-${name}" name="${name}" class="field mt-1.5" type="number" inputmode="decimal" min="0" step="any" required></div>`).join('')}
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label" for="nf-barcode">Vonalkód</label><input id="nf-barcode" name="barcode" class="field mt-1.5" inputmode="numeric" value="${esc(prefill.barcode ?? '')}"></div>
                <div><label class="label" for="nf-serving" data-serving-label>Alap adag (g)</label><input id="nf-serving" name="serving_size_g" class="field mt-1.5" type="number" inputmode="decimal" min="1" step="any"></div>
            </div>
            <p class="text-sm text-muted" data-hint></p>
            <p class="min-h-5 text-sm text-protein" data-error></p>
            <button type="submit" class="btn-primary w-full">Mentés</button>
        </form>`);

    const form = $('form', sheet.el);
    const serving = () => Number(form.serving_size_g.value) || 0;

    const refresh = () => {
        $('[data-serving-label]', sheet.el).textContent = perPortion ? 'Adag tömege (g)' : 'Alap adag (g)';

        if (!perPortion) {
            $('[data-hint]', sheet.el).textContent = 'Az alap adag csak előtölti a mennyiséget hozzáadáskor.';
        } else if (serving() > 0 && form.kcal.value !== '') {
            $('[data-hint]', sheet.el).textContent = `100 g-ban: ${num(Number(form.kcal.value) * 100 / serving())} kcal`;
        } else {
            $('[data-hint]', sheet.el).textContent = 'Add meg az adag tömegét, ebből számoljuk a 100 g-ra jutó értéket.';
        }
    };

    $$('[data-basis]', sheet.el).forEach((chip) => chip.addEventListener('click', () => {
        perPortion = chip.dataset.basis === 'portion';
        $$('[data-basis]', sheet.el).forEach((other) => other.setAttribute('aria-pressed', String(other === chip)));
        refresh();
    }));
    form.addEventListener('input', refresh);
    refresh();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const body = Object.fromEntries([...new FormData(event.target)].filter(([, value]) => value !== ''));

        if (perPortion) {
            if (serving() <= 0) {
                $('[data-error]', sheet.el).textContent = 'Add meg az adag tömegét grammban.';
                return;
            }

            NUTRIENT_FIELDS.filter((name) => name in body).forEach((name) => {
                body[name] = Math.round(Number(body[name]) * 10000 / serving()) / 100;
            });
        }

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
        ${sheetHeader('Vonalkód')}
        <div id="reader" class="mb-3 min-h-16 overflow-hidden rounded-2xl bg-soft"></div>
        <p class="mb-3 text-sm text-muted" data-status>Kamera indítása…</p>
        <form class="flex gap-2" data-manual>
            <label class="sr-only" for="code">Vonalkód beírása</label>
            <input id="code" class="field" inputmode="numeric" pattern="[0-9]*" placeholder="Vagy írd be a számot">
            <button class="btn-primary">Keresés</button>
        </form>`, { onClose: () => stop?.() });

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
        ${sheetHeader('Fotó alapú felvitel')}
        <ul class="tile mb-5 space-y-2.5 p-4 text-sm">
            <li class="flex gap-2.5"><span class="text-brand">${icon('sparkle', 'size-[18px]')}</span>A modell megnevezi az ételeket és grammot becsül.</li>
            <li class="flex gap-2.5"><span class="text-brand">${icon('scale', 'size-[18px]')}</span>A makrókat a saját adatbázis számolja.</li>
            <li class="flex gap-2.5"><span class="text-brand">${icon('pencil', 'size-[18px]')}</span>Piszkozat: mentés előtt minden javítható.</li>
        </ul>
        <label class="label" for="photo-note">Megjegyzés (opcionális)</label>
        <input id="photo-note" class="field mb-4 mt-1.5" maxlength="500" placeholder="Pl. fél adag, olajban sütve">
        <label class="btn-primary w-full" for="photo-file">${icon('camera')} Fotó készítése vagy kiválasztása</label>
        <input id="photo-file" class="sr-only" type="file" accept="image/*" capture="environment">
        <p class="mt-3 min-h-5 text-sm text-protein" data-error></p>`);

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
            body.innerHTML = `${sheetHeader('Tálca')}<p class="tile p-6 text-center text-muted">A tálca üres.</p>`;

            return;
        }

        body.innerHTML = `
            ${sheetHeader('Tálca', `${cart.items.length} tétel · a grammok itt még javíthatók`)}
            <ul class="card divide-y divide-line overflow-hidden">
                ${cart.items.map((item, index) => `
                    <li class="flex items-center gap-3 py-3 pl-4 pr-2">
                        <div class="min-w-0 flex-1"><p class="truncate text-[15px] font-medium">${esc(item.name)}</p><p class="mt-0.5 flex flex-wrap gap-x-2.5 text-xs tabular-nums text-muted" data-line="${index}"></p>${swapSelect(item, index)}</div>
                        <label class="sr-only" for="g-${index}">${esc(item.name)} grammja</label>
                        <input id="g-${index}" data-grams="${index}" class="field !w-20 !px-3 !py-2 text-right font-semibold" type="number" inputmode="decimal" min="1" step="any" value="${item.grams}">
                        <button class="icon-btn border-0 text-muted" data-remove="${index}" aria-label="${esc(item.name)} eltávolítása">${icon('x', 'size-4')}</button>
                    </li>`).join('')}
            </ul>
            <div class="my-4" data-total aria-live="polite"></div>
            <p class="label mb-2">Étkezés</p>
            <div class="no-scrollbar -mx-5 mb-4 flex gap-2 overflow-x-auto px-5" role="group" aria-label="Étkezés típusa">
                ${Object.entries(MEAL_TYPES).map(([value, label]) => `<button class="chip" data-type="${value}" aria-pressed="${cart.mealType === value}">${icon(MEAL_ICONS[value], 'size-4')}${label}</button>`).join('')}
            </div>
            <label class="label" for="eaten-at">Időpont</label>
            <input id="eaten-at" class="field mb-2 mt-1.5" type="datetime-local" value="${cart.eatenAt}">
            <p class="mb-3 min-h-5 text-sm text-protein" data-error></p>
            <button class="btn-primary w-full" data-save>Étkezés mentése</button>`;

        const refreshTotals = () => {
            cart.items.forEach((item, index) => {
                const n = scaleNutrients(item.per_100g, item.grams);
                $(`[data-line="${index}"]`, body).innerHTML = `<span class="font-medium text-ink">${num(n.kcal)} kcal</span> ${macroDots(n, 1)}`;
            });
            $('[data-total]', body).innerHTML = macroStats(cartTotals());
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
        <select id="swap-${index}" data-swap="${index}" class="mt-1.5 max-w-full rounded-lg border border-line bg-soft px-2 py-1 text-xs">
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
