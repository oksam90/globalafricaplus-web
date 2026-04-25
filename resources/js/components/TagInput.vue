<template>
    <div class="flex flex-wrap items-center gap-1.5 p-1.5 rounded-md border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 focus-within:border-emerald-400">
        <span v-for="(tag, i) in tags" :key="tag + i"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-50 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300 text-xs font-medium">
            {{ tag }}
            <button type="button" @click="remove(i)" class="hover:text-rose-600 dark:hover:text-rose-400">×</button>
        </span>
        <input v-model="buffer" type="text" :placeholder="placeholder"
            class="flex-1 min-w-[120px] px-1 py-1 text-sm outline-none bg-transparent text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500"
            @keydown.enter.prevent="commit"
            @keydown.comma.prevent="commit"
            @keydown.backspace="onBackspace" />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Ajouter…' },
});
const emit = defineEmits(['update:modelValue']);

const tags = computed(() => props.modelValue || []);
const buffer = ref('');

function commit() {
    const v = buffer.value.trim();
    if (!v) return;
    if (tags.value.includes(v)) { buffer.value = ''; return; }
    emit('update:modelValue', [...tags.value, v]);
    buffer.value = '';
}
function remove(i) {
    const next = [...tags.value];
    next.splice(i, 1);
    emit('update:modelValue', next);
}
function onBackspace() {
    if (buffer.value === '' && tags.value.length) {
        remove(tags.value.length - 1);
    }
}
</script>
