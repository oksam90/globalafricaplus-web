<template>
    <div>
        <label class="block text-sm font-medium mb-1">{{ label }}</label>
        <slot />
        <p v-if="hint" class="text-xs text-slate-500 mt-1">{{ hint }}</p>
    </div>
</template>

<script setup>
defineProps({ label: String, hint: String });
</script>
