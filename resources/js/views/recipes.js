import { api } from '../api.js';
import { icon } from '../icons.js';
import { errorBox, pageHeader, spinner } from '../ui.js';
import { $, $$, esc, num } from '../util.js';
import { openRecipeForm } from './recipe.js';

export async function renderRecipes(view) {
    view.innerHTML = spinner();

    let recipes;

    try {
        recipes = await api('/recipes');
    } catch (error) {
        view.innerHTML = errorBox(esc(error.message));
        $('[data-retry]', view).addEventListener('click', () => renderRecipes(view));

        return;
    }

    const reload = () => renderRecipes(view);

    view.innerHTML = `
        ${pageHeader({
            title: 'Receptek',
            subtitle: 'Saját receptek és kész fogások. A 100 g-ra vetített érték a kész étel tömegéből jön.',
            actions: `<button class="btn-primary !min-h-10 !px-4 text-sm" data-new>${icon('plus', 'size-4')} Új</button>`,
        })}
        ${recipes.length === 0
            ? `<div class="tile p-6 text-center"><p class="font-semibold">Még nincs recept</p><p class="mt-1 text-sm text-muted">Rakd össze egy fogás összetevőit, és a makrókat a backend számolja.</p></div>`
            : `<p class="section-title mb-2">Összes <span class="font-normal text-muted">${recipes.length}</span></p>
            <ul class="card divide-y divide-line overflow-hidden">${recipes.map((recipe, index) => `
                <li><button data-edit="${index}" class="flex w-full items-center gap-3 px-4 py-3 text-left transition active:bg-soft">
                    <span class="badge bg-soft text-ink">${icon(recipe.is_dish ? 'bowl' : 'leaf', 'size-[18px]')}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-[15px] font-medium">${esc(recipe.name)}</span>
                        <span class="block text-xs text-muted">${recipe.ingredients.length} összetevő · kész: ${num(recipe.total_weight_g)} g</span>
                    </span>
                    <span class="shrink-0 text-right text-sm font-medium tabular-nums">${num(recipe.per_100g.kcal)} kcal<span class="block text-xs font-normal text-muted">/100 g</span></span>
                    <span class="shrink-0 text-muted">${icon('right', 'size-4')}</span>
                </button></li>`).join('')}</ul>`}`;

    $('[data-new]', view).addEventListener('click', () => openRecipeForm({ onSaved: reload }));
    $$('[data-edit]', view).forEach((button) => button.addEventListener('click', () => openRecipeForm({
        recipe: recipes[Number(button.dataset.edit)],
        onSaved: reload,
        onDeleted: reload,
    })));
}
