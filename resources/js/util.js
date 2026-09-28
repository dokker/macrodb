export const TZ = 'Europe/Budapest';

export const MEAL_TYPES = { reggeli: 'Reggeli', 'ebéd': 'Ebéd', vacsora: 'Vacsora', snack: 'Snack' };

export const MICRO_LABELS = {
    fiber: ['Rost', 'g'],
    sugar: ['Cukor', 'g'],
    saturated_fat_g: ['Telített zsír', 'g'],
    sodium_mg: ['Nátrium', 'mg'],
    potassium_mg: ['Kálium', 'mg'],
    calcium_mg: ['Kalcium', 'mg'],
    iron_mg: ['Vas', 'mg'],
    magnesium_mg: ['Magnézium', 'mg'],
    vitamin_c_mg: ['C-vitamin', 'mg'],
    vitamin_d_ug: ['D-vitamin', 'µg'],
};

export const SOURCE_LABELS = { off: 'Open Food Facts', usda: 'USDA', custom: 'Saját' };

export const $ = (selector, root = document) => root.querySelector(selector);
export const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

export const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
));

export const num = (value, decimals = 0) => (value === null || value === undefined
    ? '–'
    : Number(value).toLocaleString('hu-HU', { maximumFractionDigits: decimals }));

export const todayStr = () => new Date().toLocaleDateString('sv-SE', { timeZone: TZ });

export function addDays(dateStr, days) {
    const date = new Date(`${dateStr}T12:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);

    return date.toISOString().slice(0, 10);
}

export function dayLabel(dateStr) {
    const today = todayStr();

    if (dateStr === today) return 'Ma';
    if (dateStr === addDays(today, -1)) return 'Tegnap';
    if (dateStr === addDays(today, 1)) return 'Holnap';

    return new Date(`${dateStr}T12:00:00Z`).toLocaleDateString('hu-HU', {
        weekday: 'long', month: 'long', day: 'numeric', timeZone: 'UTC',
    });
}

export const shortDayLabel = (dateStr) => new Date(`${dateStr}T12:00:00Z`).toLocaleDateString('hu-HU', {
    weekday: 'short', month: 'numeric', day: 'numeric', timeZone: 'UTC',
});

export const timeLabel = (iso) => new Date(iso).toLocaleTimeString('hu-HU', {
    hour: '2-digit', minute: '2-digit', timeZone: TZ,
});

/** Value for <input type="datetime-local"> in the browser's own timezone. */
export const toLocalInput = (date) => new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);

export function defaultMealType(date = new Date()) {
    const hour = Number(date.toLocaleTimeString('en-GB', { hour: '2-digit', hour12: false, timeZone: TZ }));

    if (hour < 10) return 'reggeli';
    if (hour < 15) return 'ebéd';
    if (hour < 18) return 'snack';

    return 'vacsora';
}

export const scaleNutrients = (per100g, grams) => ({
    kcal: per100g.kcal * grams / 100,
    protein: per100g.protein * grams / 100,
    carbs: per100g.carbs * grams / 100,
    fat: per100g.fat * grams / 100,
});

export const sumNutrients = (list) => list.reduce(
    (sum, n) => ({ kcal: sum.kcal + n.kcal, protein: sum.protein + n.protein, carbs: sum.carbs + n.carbs, fat: sum.fat + n.fat }),
    { kcal: 0, protein: 0, carbs: 0, fat: 0 },
);

export const debounce = (fn, ms) => {
    let timer;

    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), ms);
    };
};
