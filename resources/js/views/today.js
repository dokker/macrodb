import { api } from '../api.js';
import { confirmSheet, errorBox, openSheet, spinner, toast } from '../ui.js';
import {
    $, $$, MEAL_TYPES, MICRO_LABELS, addDays, dayLabel, esc, num, timeLabel, todayStr,
} from '../util.js';

const RING_RADIUS = 52;
const RING_LENGTH = 2 * Math.PI * RING_RADIUS;

export async function renderToday(view, params) {
    const date = params.get('date') || todayStr();
    view.innerHTML = spinner();

    let summary;

    try {
        summary = await api(`/summary/daily?date=${date}`);
    } catch (error) {
        view.innerHTML = errorBox(esc(error.message));
        $('[data-retry]', view).addEventListener('click', () => renderToday(view, params));

        return;
    }

    view.innerHTML = `
        <header class="mb-4 flex items-center justify-between">
            <a href="#/today?date=${addDays(date, -1)}" class="btn-quiet !px-3" aria-label="Előző nap">‹</a>
            <div class="text-center">
                <h1 class="text-xl font-bold capitalize">${dayLabel(date)}</h1>
                <p class="text-sm text-muted">${date}</p>
            </div>
            <a href="#/today?date=${addDays(date, 1)}" class="btn-quiet !px-3" aria-label="Következő nap">›</a>
        </header>
        ${totalsCard(summary)}
        <section class="mt-6" aria-label="Étkezések">${mealsList(summary, date)}</section>
        ${microsCard(summary.total)}
        <a href="#/add?date=${date}" class="btn-primary mt-6 w-full">+ Étkezés rögzítése</a>`;

    $$('[data-item]', view).forEach((row) => row.addEventListener('click', () => {
        const item = summary.meals.flatMap((meal) => meal.items).find((candidate) => candidate.id === Number(row.dataset.item));
        editItem(item, () => renderToday(view, params));
    }));
}

function totalsCard(summary) {
    const { total, target, deviation } = summary;
    const eaten = total.kcal;
    const progress = target ? Math.min(eaten / target.kcal, 1) : 0;
    const over = target && eaten > target.kcal;

    const status = !target
        ? '<p class="text-sm text-muted">Nincs napi célérték beállítva.</p>'
        : over
            ? `<p class="font-semibold text-protein">${num(deviation.kcal)} kcal a cél fölött</p>`
            : `<p class="font-semibold">Még ${num(-deviation.kcal)} kcal</p><p class="text-sm text-muted">a ${num(target.kcal)} kcal célból</p>`;

    return `
        <section class="card p-5" aria-label="Napi összesítő">
            <div class="flex items-center gap-5">
                <div class="relative size-32 shrink-0">
                    <svg viewBox="0 0 120 120" class="size-full -rotate-90" role="img" aria-label="${num(eaten)} kcal elfogyasztva">
                        <circle cx="60" cy="60" r="${RING_RADIUS}" fill="none" stroke="var(--line)" stroke-width="10"/>
                        <circle cx="60" cy="60" r="${RING_RADIUS}" fill="none" stroke="${over ? 'var(--protein)' : 'var(--brand)'}" stroke-width="10" stroke-linecap="round"
                            stroke-dasharray="${RING_LENGTH}" stroke-dashoffset="${RING_LENGTH * (1 - progress)}"/>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-2xl font-bold tabular-nums">${num(eaten)}</span>
                        <span class="text-xs text-muted">kcal</span>
                    </div>
                </div>
                <div>${status}</div>
            </div>
            <div class="mt-5 space-y-3">
                ${macroBar('Fehérje', 'bg-protein', total.protein, target?.protein)}
                ${macroBar('Szénhidrát', 'bg-carbs', total.carbs, target?.carbs)}
                ${macroBar('Zsír', 'bg-fat', total.fat, target?.fat)}
            </div>
        </section>`;
}

function macroBar(label, barClass, value, goal) {
    const pct = goal ? Math.min(100, (value / goal) * 100) : 0;

    return `
        <div>
            <div class="mb-1 flex justify-between text-sm">
                <span class="font-medium">${label}</span>
                <span class="tabular-nums text-muted">${num(value)}${goal ? ` / ${num(goal)}` : ''} g</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-line" ${goal ? `role="progressbar" aria-valuenow="${Math.round(pct)}" aria-valuemin="0" aria-valuemax="100" aria-label="${label}"` : ''}>
                <div class="h-full rounded-full ${barClass}" style="width:${goal ? pct : 0}%"></div>
            </div>
        </div>`;
}

function mealsList(summary, date) {
    if (summary.meals.length === 0) {
        return `<div class="card p-8 text-center"><p class="text-lg font-semibold">Még nincs rögzített étkezés</p><p class="mt-1 text-muted">Keress egy ételt, olvass be egy vonalkódot, vagy fotózd le a tányért.</p></div>`;
    }

    return summary.meals.map((meal) => `
        <article class="card mb-3 overflow-hidden">
            <header class="flex items-baseline justify-between px-4 pt-4">
                <h2 class="font-bold">${MEAL_TYPES[meal.meal_type] ?? esc(meal.meal_type)} <span class="ml-1 text-sm font-normal text-muted">${timeLabel(meal.eaten_at)}</span></h2>
                <span class="font-semibold tabular-nums">${num(meal.total.kcal)} kcal</span>
            </header>
            <ul class="mt-2 divide-y divide-line">
                ${meal.items.map((item) => `
                    <li>
                        <button data-item="${item.id}" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left" aria-label="${esc(item.name)} szerkesztése">
                            <span class="min-w-0"><span class="block truncate font-medium">${esc(item.name)}</span><span class="text-sm text-muted">${num(item.grams, 1)} g</span></span>
                            <span class="shrink-0 text-sm tabular-nums text-muted">${num(item.nutrients.kcal)} kcal</span>
                        </button>
                    </li>`).join('')}
            </ul>
            ${meal.note ? `<p class="px-4 pb-3 text-sm text-muted">${esc(meal.note)}</p>` : ''}
        </article>`).join('');
}

function microsCard(total) {
    const values = { fiber: total.fiber, sugar: total.sugar, ...total.micros };
    const rows = Object.entries(MICRO_LABELS).map(([key, [label, unit]]) => `
        <div class="flex justify-between py-2 text-sm"><dt>${label}</dt><dd class="tabular-nums ${values[key] === null ? 'text-muted' : ''}">${values[key] === null ? '–' : `${num(values[key], 1)} ${unit}`}</dd></div>`).join('');

    return `
        <details class="card mt-6 px-4 py-3">
            <summary class="cursor-pointer py-1 font-semibold">Részletes tápanyagok</summary>
            <dl class="mt-2 divide-y divide-line">${rows}</dl>
            <p class="mt-3 text-xs text-muted">A „–” azt jelenti, hogy nem minden étel adata ismert, ezért az összeg nem megbízható.</p>
        </details>`;
}

function editItem(item, refresh) {
    const sheet = openSheet(`
        <h2 class="mb-1 text-lg font-bold">${esc(item.name)}</h2>
        <p class="mb-4 text-sm text-muted">Módosítsd a mennyiséget grammban.</p>
        <label class="label" for="edit-grams">Gramm</label>
        <input id="edit-grams" class="field mb-2 mt-1" type="number" inputmode="decimal" min="1" step="any" value="${item.grams}">
        <p class="mb-4 h-5 text-sm text-protein" data-error></p>
        <div class="grid grid-cols-3 gap-3">
            <button class="btn-quiet" data-close>Mégse</button>
            <button class="btn-danger" data-delete>Törlés</button>
            <button class="btn-primary" data-save>Mentés</button>
        </div>`);

    $('[data-save]', sheet.el).addEventListener('click', async () => {
        const grams = Number($('#edit-grams', sheet.el).value);

        if (!(grams > 0)) {
            $('[data-error]', sheet.el).textContent = 'Adj meg pozitív mennyiséget.';

            return;
        }

        try {
            await api(`/meal-items/${item.id}`, { method: 'PATCH', body: { grams } });
            sheet.close();
            refresh();
        } catch (error) {
            $('[data-error]', sheet.el).textContent = error.message;
        }
    });

    $('[data-delete]', sheet.el).addEventListener('click', async () => {
        sheet.close();

        if (await confirmSheet(`Törlöd ezt: ${esc(item.name)}?`)) {
            try {
                await api(`/meal-items/${item.id}`, { method: 'DELETE' });
                refresh();
            } catch (error) {
                toast(error.message, 'error');
            }
        }
    });
}
