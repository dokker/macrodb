import { api } from '../api.js';
import { MEAL_ICONS, icon } from '../icons.js';
import {
    MACROS, confirmSheet, errorBox, macroDots, openSheet, sheetHeader, spinner, toast,
} from '../ui.js';
import {
    $, $$, MEAL_TYPES, MICRO_LABELS, addDays, dayLabel, esc, num, timeLabel, todayStr,
} from '../util.js';

const RING_RADIUS = 42;
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

    const longDate = new Date(`${date}T12:00:00Z`).toLocaleDateString('hu-HU', {
        month: 'long', day: 'numeric', weekday: 'long', timeZone: 'UTC',
    });

    view.innerHTML = `
        <header class="mb-5 flex items-end justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-medium text-muted first-letter:uppercase">${longDate}</p>
                <h1 class="text-[28px] font-bold capitalize leading-tight tracking-tight">${dayLabel(date)}</h1>
            </div>
            <nav class="flex shrink-0 gap-2" aria-label="Napok">
                <a href="#/today?date=${addDays(date, -1)}" class="icon-btn" aria-label="Előző nap">${icon('left')}</a>
                <a href="#/today?date=${addDays(date, 1)}" class="icon-btn" aria-label="Következő nap">${icon('right')}</a>
            </nav>
        </header>
        ${date === todayStr() ? '' : '<a href="#/today" class="chip mb-4">Ugrás a mai napra</a>'}
        ${energyHero(summary)}
        ${macroTiles(summary)}
        <section class="mt-8" aria-labelledby="meals-title">
            <div class="mb-1 flex items-baseline justify-between">
                <h2 id="meals-title" class="section-title">Étkezések</h2>
                ${summary.meals.length ? `<a href="#/add?date=${date}" class="text-sm font-semibold text-brand">+ Hozzáadás</a>` : ''}
            </div>
            ${mealsList(summary, date)}
        </section>
        ${microsCard(summary.total)}`;

    $$('[data-item]', view).forEach((row) => row.addEventListener('click', () => {
        const item = summary.meals.flatMap((meal) => meal.items).find((candidate) => candidate.id === Number(row.dataset.item));
        editItem(item, () => renderToday(view, params));
    }));
}

/** The loud card: eaten energy against the target, a progress ring and the macro energy split. */
function energyHero(summary) {
    const { total, target, deviation } = summary;
    const eaten = total.kcal;
    const progress = target ? Math.min(eaten / target.kcal, 1) : 0;
    const over = target && eaten > target.kcal;

    const status = !target
        ? 'kcal · <a href="#/targets" class="font-semibold text-white underline underline-offset-2">cél beállítása</a>'
        : `/ ${num(target.kcal)} kcal · ${over
            ? `<span class="font-semibold text-protein">${num(deviation.kcal)} a cél fölött</span>`
            : `még ${num(-deviation.kcal)}`}`;

    return `
        <section class="hero p-5" aria-label="Napi energia">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="flex items-center gap-1.5 text-sm font-medium text-white/70">${icon('flame', 'size-4')} Napi energia</p>
                    <p class="mt-2.5 text-[40px] font-bold leading-none tracking-tight tabular-nums">${num(eaten)}</p>
                    <p class="mt-2 text-sm tabular-nums text-white/70">${status}</p>
                </div>
                ${target ? `
                <div class="relative size-20 shrink-0">
                    <svg viewBox="0 0 100 100" class="size-full -rotate-90" role="img" aria-label="A cél ${Math.round((eaten / target.kcal) * 100)} százaléka">
                        <circle cx="50" cy="50" r="${RING_RADIUS}" fill="none" stroke="rgb(255 255 255 / .14)" stroke-width="9"/>
                        <circle cx="50" cy="50" r="${RING_RADIUS}" fill="none" stroke="${over ? 'var(--protein)' : '#ffffff'}" stroke-width="9" stroke-linecap="round"
                            stroke-dasharray="${RING_LENGTH}" stroke-dashoffset="${RING_LENGTH * (1 - progress)}"/>
                    </svg>
                    <span class="absolute inset-0 grid place-items-center text-base font-semibold tabular-nums">${Math.round((eaten / target.kcal) * 100)}%</span>
                </div>` : ''}
            </div>
            ${energySplit(total)}
        </section>`;
}

/** Share of the eaten energy coming from protein, carbs and fat (4/4/9 kcal per gram). */
function energySplit(total) {
    const kcal = { protein: total.protein * 4, carbs: total.carbs * 4, fat: total.fat * 9 };
    const sum = kcal.protein + kcal.carbs + kcal.fat;

    if (sum <= 0) {
        return '';
    }

    return `
        <div class="mt-4 border-t border-white/10 pt-3.5">
            <div class="flex h-2 gap-1 overflow-hidden rounded-full" aria-hidden="true">
                ${MACROS.map((macro) => `<div class="${macro.dot} rounded-full" style="width:${(kcal[macro.key] / sum) * 100}%"></div>`).join('')}
            </div>
            <p class="mt-2.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-white/70">
                ${MACROS.map((macro) => `<span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full ${macro.dot}"></span>${macro.label} ${Math.round((kcal[macro.key] / sum) * 100)}%</span>`).join('')}
            </p>
        </div>`;
}

function macroTiles({ total, target }) {
    return `
        <div class="mt-3 grid grid-cols-3 gap-2">
            ${MACROS.map((macro) => {
                const goal = target?.[macro.key];
                const pct = goal ? Math.min(100, (total[macro.key] / goal) * 100) : 0;

                return `
                    <div class="tile p-3">
                        <span class="badge size-8 ${macro.tint} ${macro.text} text-xs font-bold">${macro.short}</span>
                        <p class="mt-3 whitespace-nowrap text-lg font-semibold leading-none tabular-nums">${num(total[macro.key])}<span class="text-xs font-normal text-muted">${goal ? ` / ${num(goal)}` : ''} g</span></p>
                        <p class="mt-1 truncate text-xs text-muted">${macro.label}</p>
                        <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-line" ${goal ? `role="progressbar" aria-valuenow="${Math.round(pct)}" aria-valuemin="0" aria-valuemax="100" aria-label="${macro.label}"` : ''}>
                            <div class="h-full rounded-full ${macro.dot}" style="width:${pct}%"></div>
                        </div>
                    </div>`;
            }).join('')}
        </div>`;
}

function mealsList(summary, date) {
    if (summary.meals.length === 0) {
        const ways = [
            ['search', 'Keress rá', 'Pl. „zab”, „tojás”, „rántott hús”', ''],
            ['barcode', 'Olvasd be a vonalkódot', 'Bolti termékeknél a leggyorsabb', '&open=scan'],
            ['camera', 'Fotózd le a tányért', 'Piszkozatot kapsz, mentés előtt javítható', '&open=photo'],
        ];

        return `
            <div class="tile mt-3 p-5">
                <p class="font-semibold">Még nincs rögzített étkezés</p>
                <p class="mt-1 text-sm text-muted">Háromféleképpen vihetsz fel ételt:</p>
                <ul class="mt-3 divide-y divide-line">
                    ${ways.map(([name, title, text, open]) => `
                        <li><a href="#/add?date=${date}${open}" class="flex items-center gap-3 py-3">
                            <span class="text-muted">${icon(name)}</span>
                            <span class="min-w-0 flex-1"><span class="block text-sm font-medium">${title}</span><span class="block text-xs text-muted">${text}</span></span>
                            <span class="text-muted">${icon('right', 'size-4')}</span>
                        </a></li>`).join('')}
                </ul>
            </div>`;
    }

    return summary.meals.map((meal) => `
        <article class="mt-4">
            <header class="flex items-center gap-3 py-2">
                <span class="badge bg-brand text-brand-ink">${icon(MEAL_ICONS[meal.meal_type] ?? 'bowl')}</span>
                <div class="min-w-0 flex-1">
                    <h3 class="font-semibold">${MEAL_TYPES[meal.meal_type] ?? esc(meal.meal_type)}</h3>
                    <p class="text-xs text-muted">${timeLabel(meal.eaten_at)} · ${meal.items.length} tétel</p>
                </div>
                <p class="shrink-0 font-semibold tabular-nums">${num(meal.total.kcal)} <span class="text-xs font-normal text-muted">kcal</span></p>
            </header>
            <ul class="card divide-y divide-line overflow-hidden">
                ${meal.items.map((item) => `
                    <li>
                        <button data-item="${item.id}" class="flex w-full items-center gap-3 px-4 py-3 text-left transition active:bg-soft" aria-label="${esc(item.name)} szerkesztése">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[15px] font-medium">${esc(item.name)}</span>
                                <span class="mt-0.5 flex flex-wrap gap-x-2.5 text-xs tabular-nums text-muted">${num(item.grams, 1)} g ${macroDots(item.nutrients)}</span>
                            </span>
                            <span class="shrink-0 text-sm font-medium tabular-nums">${num(item.nutrients.kcal)} kcal</span>
                            <span class="shrink-0 text-muted">${icon('right', 'size-4')}</span>
                        </button>
                    </li>`).join('')}
            </ul>
            ${meal.note ? `<p class="mt-2 px-1 text-sm text-muted">${esc(meal.note)}</p>` : ''}
        </article>`).join('');
}

function microsCard(total) {
    const values = { fiber: total.fiber, sugar: total.sugar, ...total.micros };
    const rows = Object.entries(MICRO_LABELS).map(([key, [label, unit]]) => `
        <div class="flex justify-between py-2.5 text-sm"><dt>${label}</dt><dd class="tabular-nums ${values[key] === null ? 'text-muted' : 'font-medium'}">${values[key] === null ? '–' : `${num(values[key], 1)} ${unit}`}</dd></div>`).join('');

    return `
        <details class="tile group mt-8 px-4 py-1">
            <summary class="flex cursor-pointer list-none items-center gap-3 py-3 font-semibold [&::-webkit-details-marker]:hidden">
                <span class="text-muted">${icon('info')}</span>
                <span class="flex-1">Részletes tápanyagok</span>
                <span class="text-muted transition group-open:rotate-90">${icon('right', 'size-4')}</span>
            </summary>
            <dl class="divide-y divide-line border-t border-line">${rows}</dl>
            <p class="pb-3 pt-2 text-xs text-muted">A „–” azt jelenti, hogy nem minden étel adata ismert, ezért az összeg nem megbízható.</p>
        </details>`;
}

function editItem(item, refresh) {
    const sheet = openSheet(`
        ${sheetHeader(esc(item.name), `${num(item.grams, 1)} g · ${num(item.nutrients.kcal)} kcal`)}
        <label class="label" for="edit-grams">Mennyiség (gramm)</label>
        <input id="edit-grams" class="field mb-2 mt-1.5 text-lg font-semibold" type="number" inputmode="decimal" min="1" step="any" value="${item.grams}">
        <p class="mb-4 min-h-5 text-sm text-protein" data-error></p>
        <div class="grid grid-cols-[auto_1fr] gap-3">
            <button class="btn-danger" data-delete aria-label="Tétel törlése">${icon('trash')} Törlés</button>
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
