/**
 * Global toast notifications — singleton store + tiny composable API.
 *
 * Replaces `alert()` calls across the SPA with non-blocking, accessible
 * notifications. Mount `<ToastContainer />` once in App.vue and call
 * `useToast()` from anywhere.
 *
 * Usage:
 *   import { useToast } from '@/composables/useToast';
 *   const toast = useToast();
 *   toast.success('Profil enregistré.');
 *   toast.error('Connexion impossible.');
 *   toast.info('Téléversement en cours…');
 *   toast.warn('Votre KYC expire dans 7 jours.');
 *
 * Options on any call:
 *   toast.error('Détail technique', { duration: 8000 })
 *   toast.success('Tâche planifiée', { duration: 0 })  // 0 = sticky (no auto-dismiss)
 *
 * Async helper:
 *   await toast.promise(myAxiosCall(), {
 *     loading: 'Sauvegarde…',
 *     success: 'Sauvegardé.',
 *     error:   (e) => e?.response?.data?.message || 'Erreur.',
 *   });
 */
import { reactive } from 'vue';

let nextId = 1;

const state = reactive({
    toasts: [], // [{ id, tone, message, duration }]
});

function dismiss(id) {
    const idx = state.toasts.findIndex((t) => t.id === id);
    if (idx !== -1) state.toasts.splice(idx, 1);
}

function show(message, tone = 'info', { duration } = {}) {
    if (!message) return null;

    // Default durations: success 4s, info 4s, warn 6s, error 6s. 0 = sticky.
    const defaultDuration = tone === 'error' || tone === 'warn' ? 6000 : 4000;
    const id = nextId++;
    const toast = {
        id,
        tone,
        message: String(message),
        duration: duration ?? defaultDuration,
    };
    state.toasts.push(toast);

    if (toast.duration > 0) {
        setTimeout(() => dismiss(id), toast.duration);
    }
    return id;
}

async function promise(p, { loading = 'Chargement…', success = 'Terminé.', error = 'Erreur.' } = {}) {
    const loadingId = show(loading, 'info', { duration: 0 });
    try {
        const result = await p;
        dismiss(loadingId);
        const msg = typeof success === 'function' ? success(result) : success;
        show(msg, 'success');
        return result;
    } catch (e) {
        dismiss(loadingId);
        const msg = typeof error === 'function' ? error(e) : error;
        show(msg, 'error');
        throw e;
    }
}

const api = {
    success: (msg, opts) => show(msg, 'success', opts),
    error:   (msg, opts) => show(msg, 'error', opts),
    info:    (msg, opts) => show(msg, 'info', opts),
    warn:    (msg, opts) => show(msg, 'warn', opts),
    show,
    dismiss,
    promise,
    /** Read-only proxy for the container component. */
    get toasts() { return state.toasts; },
};

export function useToast() { return api; }
