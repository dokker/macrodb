import { api } from '../api.js';
import { openSheet, toast } from '../ui.js';
import {
    $, $$, debounce, esc, num, scaleNutrients, sumNutrients,
} from '../util.js';

/**
 * Sheet for creating a recipe from ingredients. Per-100 g values come from the ingredient totals divided by the
 * cooked weight, exactly as the backend does it; the preview here is only a courtesy.
 */
export function openRecipeForm({ name = '', onCreated }) {
    const ingredients = [];
    let matches = [];
    let searchNote = '';

    const sheet = openSheet(`
        <h2 class="mb-1 text-lg font-bold">Új recept</h2>
        <p class="mb-4 text-sm text-muted">A 100 g-ra vetített értékek az összetevők összegéből számolódnak. Ha főzéskor sok víz távozik, add meg a kész étel tömegét is.</p>
        <form class="space-y-3" novalidate>
            <div><label class="label" for="nr-name">Név</label><input id="nr-name" name="name" class="field mt-1" required value="${esc(name)}"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label" for="nr-weight">Kész étel tömege (g)</label><input id="nr-weight" name="total_weight_g" class="field mt-1" type="number" inputmode="decimal" min="1" step="any" placeholder="összetevők összege"></div>
                <div><label class="label" for="nr-portion">Alap adag (g)</label><input id="nr-portion" name="default_portion_g" class="field mt-1" type="number" inputmode="decimal" min="1" step="any"></div>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_dish" class="size-5"> Kész fogás (nem alapanyag)</label>
            <div>
                <label class="label" for="nr-search">Összetevők</label>
                <input id="nr-search" class="field mt-1" type="search" autocomplete="off" placeholder="Összetevő keresése">
                <p class="mt-1 text-sm text-muted" data-search-note aria-live="polite"></p>
                <ul class="card mt-2 hidden divide-y divide-line overflow-hidden" data-matches></ul>
                <ul class="mt-2 divide-y divide-line" data-ingredients></ul>
            </div>
            <p class="rounded-xl bg-bg p-3 text-sm tabular-nums text-muted" data-preview aria-live="polite"></p>
            <p class="h-5 text-sm text-protein" data-error role="alert"></p>
            <div class="grid grid-cols-2 gap-3">
                <button type="button" class="btn-quiet" data-close>Mégse</button>
                <button type="submit" class="btn-primary">Mentés</button>
            </div>
        </form>`);

    const form = $('form', sheet.el);
    const rawWeight = () => ingredients.reduce((sum, item) => sum + item.grams, 0);
    const weight = () => Number($('[name="total_weight_g"]', form).value) || rawWeight();

    const refreshPreview = () => {
        const total = sumNutrients(ingredients.map((item) => scaleNutrients(item.per_100g, item.grams)));

        $('[data-preview]', form).textContent = weight() > 0 && ingredients.length
            ? `100 g-ban: ${num(total.kcal * 100 / weight())} kcal · F ${num(total.protein * 100 / weight(), 1)} · Sz ${num(total.carbs * 100 / weight(), 1)} · Zs ${num(total.fat * 100 / weight(), 1)} g (${num(weight())} g alapján)`
            : 'Adj hozzá összetevőket és grammokat a 100 g-ra vetített értékekhez.';
    };

    const drawIngredients = () => {
        const list = $('[data-ingredients]', form);

        list.innerHTML = ingredients.map((item, index) => `
            <li class="flex items-center gap-2 py-2">
                <span class="min-w-0 flex-1 truncate">${esc(item.name)}</span>
                <label class="sr-only" for="ri-${index}">${esc(item.name)} grammja</label>
                <input id="ri-${index}" data-grams="${index}" class="field !w-24 !px-3 !py-2 text-right" type="number" inputmode="decimal" min="1" step="any" value="${item.grams || ''}">
                <button type="button" class="btn-quiet !min-h-10 !px-3" data-remove="${index}" aria-label="${esc(item.name)} eltávolítása">✕</button>
            </li>`).join('');

        $$('[data-grams]', list).forEach((input) => input.addEventListener('input', () => {
            ingredients[Number(input.dataset.grams)].grams = Number(input.value) || 0;
            refreshPreview();
        }));
        $$('[data-remove]', list).forEach((button) => button.addEventListener('click', () => {
            ingredients.splice(Number(button.dataset.remove), 1);
            drawIngredients();
        }));
        refreshPreview();
    };

    const drawMatches = () => {
        const list = $('[data-matches]', form);

        $('[data-search-note]', form).textContent = searchNote;
        list.classList.toggle('hidden', matches.length === 0);
        list.innerHTML = matches.map((food, index) => `
            <li><button type="button" data-pick="${index}" class="flex w-full justify-between gap-3 px-4 py-2 text-left">
                <span class="truncate">${esc(food.name)}</span>
                <span class="shrink-0 text-sm tabular-nums text-muted">${num(food.per_100g.kcal)} kcal</span>
            </button></li>`).join('');

        $$('[data-pick]', list).forEach((button) => button.addEventListener('click', () => {
            const food = matches[Number(button.dataset.pick)];

            ingredients.push({ food_id: food.id, name: food.name, per_100g: food.per_100g, grams: 0 });
            matches = [];
            $('#nr-search', form).value = '';
            drawMatches();
            drawIngredients();
            $(`[data-grams="${ingredients.length - 1}"]`, form).focus();
        }));
    };

    const search = debounce(async (text) => {
        searchNote = '';

        if (!text) {
            matches = [];

            return drawMatches();
        }

        try {
            const found = await api(`/foods?q=${encodeURIComponent(text)}&limit=30`);
            matches = found.filter((food) => food.type !== 'recipe').slice(0, 8);
            searchNote = matches.length ? '' : `Nincs alapanyag erre: „${text}”.`;
        } catch (error) {
            matches = [];
            searchNote = error.message;
        }

        drawMatches();
    }, 250);

    $('#nr-search', form).addEventListener('keydown', (event) => event.key === 'Enter' && event.preventDefault());
    $('#nr-search', form).addEventListener('input', (event) => search(event.target.value.trim()));
    $('[name="total_weight_g"]', form).addEventListener('input', refreshPreview);
    drawIngredients();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const error = $('[data-error]', form);
        const data = new FormData(form);
        const body = {
            name: data.get('name'),
            is_dish: data.has('is_dish'),
            ingredients: ingredients.map(({ food_id, grams }) => ({ food_id, grams })),
        };

        if (data.get('total_weight_g')) body.total_weight_g = data.get('total_weight_g');
        if (data.get('default_portion_g')) body.default_portion_g = data.get('default_portion_g');

        if (ingredients.some((item) => !(item.grams > 0))) {
            error.textContent = 'Minden összetevőhöz pozitív grammot adj meg.';

            return;
        }

        try {
            const recipe = await api('/recipes', { method: 'POST', body });
            sheet.close();
            toast(`${recipe.name} elmentve`);
            onCreated(recipe);
        } catch (failure) {
            error.textContent = failure.message;
        }
    });
}
