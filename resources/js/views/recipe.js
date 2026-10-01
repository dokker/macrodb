import { api } from '../api.js';
import { icon } from '../icons.js';
import {
    confirmSheet, macroStats, openSheet, sheetHeader, toast,
} from '../ui.js';
import {
    $, $$, debounce, esc, num, scaleNutrients, sumNutrients,
} from '../util.js';

/**
 * Sheet for creating or editing (`recipe` given) a recipe from ingredients. Per-100 g values come from the ingredient
 * totals divided by the cooked weight, exactly as the backend does it; the preview here is only a courtesy.
 * The finished weight follows the sum of the ingredients until it is typed in by hand; clearing it restores that.
 */
export function openRecipeForm({ recipe = null, name = '', onSaved, onDeleted = null }) {
    const ingredients = (recipe?.ingredients ?? []).map((item) => ({ ...item }));
    let weightManual = recipe !== null && Math.abs(recipe.total_weight_g - recipe.ingredient_total_g) > 0.005;
    let matches = [];
    let searchNote = '';

    const sheet = openSheet(`
        ${sheetHeader(recipe ? 'Recept szerkesztése' : 'Új recept', 'A kész étel tömege alapból az összetevők összege. Ha főzéskor sok víz távozik, írd át a valós tömegre.')}
        <form class="space-y-3" novalidate>
            <div><label class="label" for="nr-name">Név</label><input id="nr-name" name="name" class="field mt-1.5" required value="${esc(name)}"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label" for="nr-weight">Kész étel tömege (g)</label><input id="nr-weight" name="total_weight_g" class="field mt-1.5" type="number" inputmode="decimal" min="1" step="any" placeholder="összetevők összege" value="${weightManual ? recipe.total_weight_g : ''}"></div>
                <div><label class="label" for="nr-portion">Alap adag (g)</label><input id="nr-portion" name="default_portion_g" class="field mt-1.5" type="number" inputmode="decimal" min="1" step="any" value="${recipe?.default_portion_g ?? ''}"></div>
            </div>
            <label class="tile flex items-center gap-3 px-4 py-3 text-sm font-medium"><input type="checkbox" name="is_dish" class="size-5 accent-brand" ${recipe?.is_dish ?? true ? 'checked' : ''}> Kész fogás (nem alapanyag)</label>
            <div>
                <label class="label" for="nr-search">Összetevők</label>
                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute inset-y-0 left-4 grid place-items-center text-muted">${icon('search', 'size-[18px]')}</span>
                    <input id="nr-search" class="field !rounded-full !pl-11" type="search" autocomplete="off" placeholder="Összetevő keresése">
                </div>
                <p class="mt-1 text-sm text-muted" data-search-note aria-live="polite"></p>
                <ul class="card mt-2 hidden divide-y divide-line overflow-hidden" data-matches></ul>
                <ul class="mt-2 divide-y divide-line" data-ingredients></ul>
            </div>
            <div data-preview aria-live="polite"></div>
            <p class="min-h-5 text-sm text-protein" data-error role="alert"></p>
            <div class="grid ${recipe && onDeleted ? 'grid-cols-[auto_1fr]' : ''} gap-3">
                ${recipe && onDeleted ? `<button type="button" class="btn-danger" data-delete>${icon('trash')} Törlés</button>` : ''}
                <button type="submit" class="btn-primary">Mentés</button>
            </div>
        </form>`);

    const form = $('form', sheet.el);
    const rawWeight = () => ingredients.reduce((sum, item) => sum + item.grams, 0);
    const weightInput = $('[name="total_weight_g"]', form);
    const weight = () => (weightManual ? Number(weightInput.value) : 0) || rawWeight();

    const refreshPreview = () => {
        if (!weightManual) {
            weightInput.value = rawWeight() > 0 ? String(Math.round(rawWeight() * 100) / 100) : '';
        }

        const total = sumNutrients(ingredients.map((item) => scaleNutrients(item.per_100g, item.grams)));

        const per100 = (value) => value * 100 / weight();

        $('[data-preview]', form).innerHTML = weight() > 0 && ingredients.length
            ? `<p class="label mb-2">100 g-ban (${num(weight())} g kész tömeg alapján)</p>${macroStats({
                kcal: per100(total.kcal), protein: per100(total.protein), carbs: per100(total.carbs), fat: per100(total.fat),
            })}`
            : '<p class="tile p-3 text-sm text-muted">Adj hozzá összetevőket és grammokat a 100 g-ra vetített értékekhez.</p>';
    };

    const drawIngredients = () => {
        const list = $('[data-ingredients]', form);

        list.innerHTML = ingredients.map((item, index) => `
            <li class="flex items-center gap-2 py-2">
                <span class="min-w-0 flex-1 truncate">${esc(item.name)}</span>
                <label class="sr-only" for="ri-${index}">${esc(item.name)} grammja</label>
                <input id="ri-${index}" data-grams="${index}" class="field !w-20 !px-3 !py-2 text-right font-semibold" type="number" inputmode="decimal" min="1" step="any" value="${item.grams || ''}">
                <button type="button" class="icon-btn border-0 text-muted" data-remove="${index}" aria-label="${esc(item.name)} eltávolítása">${icon('x', 'size-4')}</button>
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
            <li><button type="button" data-pick="${index}" class="flex w-full justify-between gap-3 px-4 py-2.5 text-left transition active:bg-soft">
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
    weightInput.addEventListener('input', () => {
        weightManual = weightInput.value !== '';
        refreshPreview();
    });
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

        if (weightManual && data.get('total_weight_g')) body.total_weight_g = data.get('total_weight_g');
        if (data.get('default_portion_g')) body.default_portion_g = data.get('default_portion_g');

        if (ingredients.some((item) => !(item.grams > 0))) {
            error.textContent = 'Minden összetevőhöz pozitív grammot adj meg.';

            return;
        }

        try {
            const saved = recipe
                ? await api(`/recipes/${recipe.id}`, { method: 'PUT', body: { ...body, portions: recipe.portions } })
                : await api('/recipes', { method: 'POST', body });
            sheet.close();
            toast(`${saved.name} elmentve`);
            onSaved(saved);
        } catch (failure) {
            error.textContent = failure.message;
        }
    });

    $('[data-delete]', form)?.addEventListener('click', async () => {
        if (!await confirmSheet(`Törlöd ezt: ${esc(recipe.name)}?`)) return;

        try {
            await api(`/recipes/${recipe.id}`, { method: 'DELETE' });
            sheet.close();
            toast('Recept törölve');
            onDeleted();
        } catch (failure) {
            $('[data-error]', form).textContent = failure.message;
        }
    });
}
