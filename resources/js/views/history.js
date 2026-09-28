import { api } from '../api.js';
import { errorBox, spinner } from '../ui.js';
import {
    $, $$, addDays, esc, num, shortDayLabel, todayStr,
} from '../util.js';

let days = 14;

export async function renderHistory(view) {
    view.innerHTML = spinner();

    const today = todayStr();
    let list;

    try {
        list = await api(`/summary/range?from=${addDays(today, -(days - 1))}&to=${today}`);
    } catch (error) {
        view.innerHTML = errorBox(esc(error.message));
        $('[data-retry]', view).addEventListener('click', () => renderHistory(view));

        return;
    }

    const logged = list.filter((day) => day.meals.length > 0);
    const average = logged.length ? logged.reduce((sum, day) => sum + day.total.kcal, 0) / logged.length : null;
    const scaleMax = Math.max(1, ...list.map((day) => Math.max(day.total.kcal, day.target?.kcal ?? 0)));

    view.innerHTML = `
        <header class="mb-4">
            <h1 class="text-xl font-bold">Napló</h1>
            <div class="mt-3 flex gap-2" role="group" aria-label="Időszak">
                ${[7, 14, 30].map((n) => `<button class="chip" data-days="${n}" aria-pressed="${n === days}">${n} nap</button>`).join('')}
            </div>
        </header>
        <div class="card mb-4 p-4">
            <p class="label">Átlag a rögzített napokon</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">${average === null ? '–' : `${num(average)} kcal`}</p>
            <p class="text-sm text-muted">${logged.length} / ${list.length} napon van adat</p>
        </div>
        <ul class="space-y-2">
            ${[...list].reverse().map((day) => dayRow(day, scaleMax)).join('')}
        </ul>
        <form method="POST" action="/logout" class="mt-8 text-center">
            <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]').content}">
            <button class="text-sm text-muted underline">Kijelentkezés</button>
        </form>`;

    $$('[data-days]', view).forEach((button) => button.addEventListener('click', () => {
        days = Number(button.dataset.days);
        renderHistory(view);
    }));
}

function dayRow(day, scaleMax) {
    const pct = (day.total.kcal / scaleMax) * 100;
    const targetPct = day.target ? (day.target.kcal / scaleMax) * 100 : null;
    const over = day.target && day.total.kcal > day.target.kcal;
    const empty = day.meals.length === 0;

    return `
        <li>
            <a href="#/today?date=${day.date}" class="card block p-3 ${empty ? 'opacity-60' : ''}">
                <div class="mb-2 flex items-baseline justify-between">
                    <span class="font-medium capitalize">${shortDayLabel(day.date)}</span>
                    <span class="tabular-nums ${over ? 'font-semibold text-protein' : ''}">${empty ? '–' : `${num(day.total.kcal)} kcal`}</span>
                </div>
                <div class="relative h-2.5 overflow-hidden rounded-full bg-line">
                    <div class="h-full rounded-full ${over ? 'bg-protein' : 'bg-brand'}" style="width:${pct}%"></div>
                    ${targetPct === null ? '' : `<div class="absolute inset-y-0 w-0.5 bg-ink/60" style="left:${targetPct}%" title="Cél"></div>`}
                </div>
                ${empty ? '' : `<p class="mt-2 text-xs tabular-nums text-muted">F ${num(day.total.protein)} g · Sz ${num(day.total.carbs)} g · Zs ${num(day.total.fat)} g</p>`}
            </a>
        </li>`;
}
