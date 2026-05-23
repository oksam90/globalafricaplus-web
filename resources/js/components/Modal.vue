<template>
    <Teleport to="body">
        <Transition name="ga-modal" appear>
            <div v-if="modelValue"
                ref="overlayRef"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
                @click.self="onBackdrop"
                @keydown.esc.stop="onEscape">
                <div
                    ref="dialogRef"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="title ? `${dialogId}-title` : null"
                    tabindex="-1"
                    class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full flex flex-col max-h-[90vh]"
                    :class="sizeClass"
                    @keydown.tab="onTab">
                    <!-- Header — sticky, always visible -->
                    <header v-if="title || $slots.header"
                        class="flex items-center justify-between p-5 border-b border-slate-100 dark:border-slate-700 shrink-0">
                        <slot name="header">
                            <h2 :id="`${dialogId}-title`" class="text-lg font-bold text-slate-900 dark:text-slate-100">
                                {{ title }}
                            </h2>
                        </slot>
                        <button v-if="!hideClose"
                            ref="closeRef"
                            type="button"
                            @click="close"
                            :aria-label="closeLabel"
                            class="text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 text-2xl leading-none px-2 -mr-2">
                            &times;
                        </button>
                    </header>

                    <!-- Body — scrolls when content overflows -->
                    <div class="flex-1 overflow-y-auto p-5">
                        <slot />
                    </div>

                    <!-- Footer — sticky, optional -->
                    <footer v-if="$slots.footer"
                        class="p-4 border-t border-slate-100 dark:border-slate-700 flex items-center justify-end gap-2 shrink-0 bg-white dark:bg-slate-800 rounded-b-2xl">
                        <slot name="footer" :close="close" />
                    </footer>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    title: { type: String, default: '' },
    /** sm | md | lg | xl | 2xl | 3xl */
    size: { type: String, default: 'md' },
    closeOnBackdrop: { type: Boolean, default: true },
    closeOnEscape: { type: Boolean, default: true },
    hideClose: { type: Boolean, default: false },
    closeLabel: { type: String, default: 'Fermer' },
    /** CSS selector OR ref of the element to focus when the modal opens.
        Defaults to the first focusable element inside the dialog. */
    initialFocus: { type: [String, Object], default: null },
});

const emit = defineEmits(['update:modelValue', 'close', 'open']);

const overlayRef = ref(null);
const dialogRef  = ref(null);
const closeRef   = ref(null);
const dialogId   = `ga-modal-${Math.random().toString(36).slice(2, 8)}`;
let previouslyFocused = null;

const sizeClass = computed(() => ({
    sm:  'max-w-sm',
    md:  'max-w-md',
    lg:  'max-w-lg',
    xl:  'max-w-xl',
    '2xl': 'max-w-2xl',
    '3xl': 'max-w-3xl',
}[props.size] || 'max-w-md'));

function close() {
    emit('update:modelValue', false);
    emit('close');
}

function onBackdrop() {
    if (props.closeOnBackdrop) close();
}

function onEscape(e) {
    if (props.closeOnEscape) {
        e.preventDefault();
        close();
    }
}

/** Focus-trap: cycle Tab focus within the dialog. */
function getFocusable() {
    if (!dialogRef.value) return [];
    return Array.from(
        dialogRef.value.querySelectorAll(
            'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), [tabindex]:not([tabindex="-1"])',
        ),
    ).filter((el) => el.offsetParent !== null);
}

function onTab(e) {
    const focusables = getFocusable();
    if (!focusables.length) return;

    const first = focusables[0];
    const last  = focusables[focusables.length - 1];

    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
}

async function focusInitial() {
    await nextTick();
    if (!dialogRef.value) return;

    // 1. Explicit initialFocus prop
    if (props.initialFocus) {
        let target = null;
        if (typeof props.initialFocus === 'string') {
            target = dialogRef.value.querySelector(props.initialFocus);
        } else if (props.initialFocus?.value) {
            target = props.initialFocus.value;
        } else if (props.initialFocus instanceof HTMLElement) {
            target = props.initialFocus;
        }
        if (target?.focus) { target.focus(); return; }
    }

    // 2. First focusable element in the dialog body (skip the × close button)
    const focusables = getFocusable().filter((el) => el !== closeRef.value);
    if (focusables.length) {
        focusables[0].focus();
        return;
    }

    // 3. Fallback: focus the dialog itself
    dialogRef.value.focus();
}

watch(() => props.modelValue, async (open) => {
    if (open) {
        previouslyFocused = document.activeElement;
        // Body scroll-lock — avoids the page jumping behind the modal.
        document.body.style.overflow = 'hidden';
        await focusInitial();
        emit('open');
    } else {
        document.body.style.overflow = '';
        // Restore focus to whatever opened the modal (button, link…) so
        // keyboard users don't lose their place.
        if (previouslyFocused?.focus) {
            previouslyFocused.focus();
        }
        previouslyFocused = null;
    }
}, { immediate: true });
</script>

<style scoped>
.ga-modal-enter-active, .ga-modal-leave-active { transition: opacity 180ms ease; }
.ga-modal-enter-from, .ga-modal-leave-to { opacity: 0; }
.ga-modal-enter-active > div[role="dialog"],
.ga-modal-leave-active > div[role="dialog"] {
    transition: transform 200ms ease, opacity 200ms ease;
}
.ga-modal-enter-from > div[role="dialog"],
.ga-modal-leave-to > div[role="dialog"] {
    transform: scale(0.97) translateY(8px);
    opacity: 0;
}
</style>
