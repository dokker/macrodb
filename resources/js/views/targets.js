import { api } from '../api.js';
import { confirmSheet, errorBox, spinner, toast } from '../ui.js';
import { $, $$, esc, num, todayStr } from '../util.js';

const FIELDS = [
    ['kcal', 'Kalória', 'kcal', '1'],
    ['protein', 'Fehérje', 'g', 'any'],
    ['carbs', 'Szénhidrát', 'g', 'any'],
    ['fat', 'Zsír', 'g', 'any'],
];

export async function renderTargets(view) {
    view.innerHTML = spinner();

    let data;

    try {
        data = await api('/targets');
    } catch (error) {
        view.innerHTML = errorBox(esc(error.message));
        $('[data-retry]', view).addEventListener('click', () => renderTargets(view));

        return;
    }

    const draw = () => {
        const prefill = data.current ?? {};

        view.innerHTML = `
            <header class="mb-4">
                <a href="#/history" class="text-sm text-muted">‹ Napló</a>
                <h1 class="text-xl font-bold">Napi célok</h1>
                <p class="text-sm text-muted">A megadott naptól érvényes, a következő beállításig.</p>
            </header>
            <form class="card space-y-4 p-4" novalidate>
                <div class="grid grid-cols-2 gap-3">
                    ${FIELDS.map(([name, label, unit, step]) => `
                        <div>
                            <label class="label" for="t-${name}">${label} (${unit})</label>
                            <input id="t-${name}" name="${name}" class="field mt-1" type="number" inputmode="decimal" min="0" step="${step}" required value="${prefill[name] ?? ''}">
                        </div>`).join('')}
                </div>
                <div>
                    <label class="label" for="t-valid_from">Érvényes ettől</label>
                    <input id="t-valid_from" name="valid_from" class="field mt-1" type="date" required value="${todayStr()}">
                </div>
                <p class="rounded-xl bg-bg p-3 text-sm tabular-nums text-muted" data-check aria-live="polite"></p>
                <p class="h-5 text-sm text-protein" data-error role="alert"></p>
                <button class="btn-primary w-full" type="submit">Mentés</button>
            </form>
            <section class="mt-6" aria-label="Korábbi beállítások">
                <h2 class="label mb-2">Beállítások</h2>
                ${data.history.length === 0 ? '<p class="text-muted">Még nincs beállított cél.</p>' : `<ul class="card divide-y divide-line overflow-hidden">${data.history.map(historyRow).join('')}</ul>`}
            </section>`;

        const form = $('form', view);
        const check = () => {
            const value = (name) => Number($(`[name="${name}"]`, form).value) || 0;
            const fromMacros = 4 * value('protein') + 4 * value('carbs') + 9 * value('fat');
            const kcal = value('kcal');

            $('[data-check]', form).textContent = fromMacros === 0
                ? 'A makrókból számolt energia itt jelenik meg.'
                : `A makrókból számolt energia: ${num(fromMacros)} kcal${kcal ? ` (${fromMacros - kcal >= 0 ? '+' : '−'}${num(Math.abs(fromMacros - kcal))} a kalóriacélhoz képest)` : ''}`;
        };

        form.addEventListener('input', check);
        check();

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            try {
                await api('/targets', { method: 'POST', body: Object.fromEntries(new FormData(form)) });
                toast('Célok mentve');
                data = await api('/targets');
                draw();
            } catch (error) {
                $('[data-error]', form).textContent = error.message;
            }
        });

        $$('[data-load]', view).forEach((button) => button.addEventListener('click', () => {
            const target = data.history.find((row) => row.id === Number(button.dataset.load));

            FIELDS.forEach(([name]) => { $(`[name="${name}"]`, form).value = target[name]; });
            $('[name="valid_from"]', form).value = target.valid_from;
            check();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }));

        $$('[data-delete]', view).forEach((button) => button.addEventListener('click', async () => {
            if (!(await confirmSheet('Törlöd ezt a beállítást?'))) return;

            try {
                await api(`/targets/${button.dataset.delete}`, { method: 'DELETE' });
                data = await api('/targets');
                draw();
            } catch (error) {
                toast(error.message, 'error');
            }
        }));
    };

    draw();
}

function historyRow(target) {
    return `
        <li class="flex items-center gap-2 pr-2">
            <button data-load="${target.id}" class="min-w-0 flex-1 px-4 py-3 text-left" aria-label="${target.valid_from} beállítás betöltése">
                <span class="block font-medium">${target.valid_from}</span>
                <span class="text-sm tabular-nums text-muted">${num(target.kcal)} kcal · F ${num(target.protein)} · Sz ${num(target.carbs)} · Zs ${num(target.fat)} g</span>
            </button>
            <button data-delete="${target.id}" class="btn-quiet !min-h-10 !px-3" aria-label="${target.valid_from} beállítás törlése">✕</button>
        </li>`;
}
