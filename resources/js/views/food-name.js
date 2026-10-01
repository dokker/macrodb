import { api } from '../api.js';
import { icon } from '../icons.js';
import { openSheet, sheetHeader, toast } from '../ui.js';
import { $, esc } from '../util.js';

/**
 * Sheet for the user's own name of a food (`{ id, name, original_name }`), shown in every list and in the log.
 * The source name stays untouched, so "Eredeti név" can restore it. `onSaved` gets the updated food resource.
 */
export function renameFood(food, onSaved) {
    const sheet = openSheet(`
        ${sheetHeader('Átnevezés', food.original_name ? `Eredeti: ${esc(food.original_name)}` : 'Így jelenik meg a listákban és a naplóban')}
        <form data-rename>
            <label class="label" for="display-name">Név</label>
            <input id="display-name" class="field mb-2 mt-1.5" autocomplete="off" maxlength="255" value="${esc(food.name)}" required>
            <p class="mb-4 min-h-5 text-sm text-protein" data-error></p>
            <div class="grid ${food.original_name ? 'grid-cols-[auto_1fr]' : ''} gap-3">
                ${food.original_name ? `<button type="button" class="btn-quiet" data-reset>${icon('undo', 'size-4')} Eredeti név</button>` : ''}
                <button class="btn-primary">Mentés</button>
            </div>
        </form>`);

    const save = async (displayName) => {
        try {
            const saved = await api(`/foods/${food.id}`, { method: 'PATCH', body: { display_name: displayName } });
            sheet.close();
            toast(saved.original_name ? 'Átnevezve' : 'Visszaállítva az eredeti névre');
            onSaved(saved);
        } catch (error) {
            $('[data-error]', sheet.el).textContent = error.message;
        }
    };

    $('#display-name', sheet.el).select();
    $('[data-rename]', sheet.el).addEventListener('submit', (event) => {
        event.preventDefault();
        save($('#display-name', sheet.el).value.trim());
    });
    $('[data-reset]', sheet.el)?.addEventListener('click', () => save(null));
}
