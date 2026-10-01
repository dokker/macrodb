import { api } from '../api.js';
import { icon } from '../icons.js';
import {
    errorBox, macroDots, pageHeader, spinner,
} from '../ui.js';
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
    const withTarget = logged.filter((day) => day.target);
    const onTarget = withTarget.filter((day) => day.total.kcal <= day.target.kcal).length;
    const scaleMax = Math.max(1, ...list.map((day) => Math.max(day.total.kcal, day.target?.kcal ?? 0)));

    const stats = [
        ['flame', average === null ? '–' : num(average), 'Átlag kcal'],
        ['chart', `${logged.length}/${list.length}`, 'Nap adattal'],
        ['target', withTarget.length ? `${onTarget}/${withTarget.length}` : '–', 'Célon belül'],
    ];

    view.innerHTML = `
        ${pageHeader({ title: 'Napló', subtitle: 'Napi összesítések, a rögzített étkezésekből számolva.' })}
        <div class="no-scrollbar -mx-5 flex gap-2 overflow-x-auto px-5" role="group" aria-label="Időszak">
            ${[7, 14, 30].map((n) => `<button class="chip" data-days="${n}" aria-pressed="${n === days}">${n} nap</button>`).join('')}
        </div>
        <div class="mt-4 grid grid-cols-3 gap-2">
            ${stats.map(([name, value, label]) => `
                <div class="tile p-3">
                    <span class="text-muted">${icon(name, 'size-[18px]')}</span>
                    <p class="mt-3 text-lg font-semibold leading-none tabular-nums">${value}</p>
                    <p class="mt-1 text-xs text-muted">${label}</p>
                </div>`).join('')}
        </div>
        <h2 class="section-title mb-2 mt-8">Napok</h2>
        <ul class="card divide-y divide-line overflow-hidden">
            ${[...list].reverse().map((day) => dayRow(day, scaleMax)).join('')}
        </ul>
        <form method="POST" action="/logout" class="mt-8">
            <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]').content}">
            <button class="flex w-full items-center justify-center gap-2 py-3 text-sm font-medium text-muted">${icon('logout', 'size-4')} Kijelentkezés</button>
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
            <a href="#/today?date=${day.date}" class="block px-4 py-3 transition active:bg-soft">
                <div class="flex items-baseline justify-between gap-3">
                    <span class="text-[15px] font-medium capitalize ${empty ? 'text-muted' : ''}">${shortDayLabel(day.date)}</span>
                    <span class="text-sm tabular-nums ${over ? 'font-semibold text-protein' : empty ? 'text-muted' : 'font-medium'}">${empty ? 'nincs adat' : `${num(day.total.kcal)} kcal`}</span>
                </div>
                ${empty ? '' : `
                <div class="relative mt-2 h-1.5 overflow-hidden rounded-full bg-soft">
                    <div class="h-full rounded-full ${over ? 'bg-protein' : 'bg-brand'}" style="width:${pct}%"></div>
                    ${targetPct === null ? '' : `<div class="absolute inset-y-0 w-0.5 bg-ink/50" style="left:${targetPct}%" title="Cél"></div>`}
                </div>
                <p class="mt-2 flex flex-wrap gap-x-3 text-xs tabular-nums text-muted">${macroDots(day.total)}</p>`}
            </a>
        </li>`;
}
