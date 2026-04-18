import { defineStore } from 'pinia';

/**
 * Global UI state — navigation / loading indicators that need to live
 * above individual pages (so App.vue can render a top progress bar
 * while any route / data is resolving).
 */
export const useUiStore = defineStore('ui', {
    state: () => ({
        navigating: false,
        _navToken: 0,
    }),
    actions: {
        startNav() {
            this._navToken += 1;
            this.navigating = true;
        },
        endNav() {
            this._navToken = Math.max(0, this._navToken - 1);
            if (this._navToken === 0) this.navigating = false;
        },
    },
});
