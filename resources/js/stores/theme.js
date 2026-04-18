import { defineStore } from 'pinia';
import { ref, watch } from 'vue';

export const useThemeStore = defineStore('theme', () => {
    const saved = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    // 'light' | 'dark' | 'system'
    const mode = ref(saved || 'system');

    const isDark = ref(
        saved === 'dark' || (!saved && prefersDark)
    );

    function applyTheme() {
        const dark = mode.value === 'dark' || (mode.value === 'system' && prefersDark);
        isDark.value = dark;
        document.documentElement.classList.toggle('dark', dark);
        localStorage.setItem('theme', mode.value);
    }

    function toggle() {
        mode.value = isDark.value ? 'light' : 'dark';
        applyTheme();
    }

    function setMode(m) {
        mode.value = m;
        applyTheme();
    }

    // Listen for OS theme changes
    window.matchMedia('(prefers-color-scheme: dark)')
        .addEventListener('change', () => {
            if (mode.value === 'system') applyTheme();
        });

    // Apply on init
    applyTheme();

    return { mode, isDark, toggle, setMode };
});
