<template>
    <div>
        <label class="block text-sm font-medium mb-1 text-slate-800 dark:text-slate-200">{{ label }}</label>
        <slot />
        <p v-if="hint" class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ hint }}</p>
    </div>
</template>

<script setup>
defineProps({ label: String, hint: String });
</script>
