<template>
    <component :is="to ? 'router-link' : 'div'" :to="to || undefined"
        class="bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-2xl p-6 transition group"
        :class="to ? 'hover:border-emerald-200 dark:hover:border-emerald-500/60 hover:shadow-sm cursor-pointer' : ''">
        <div class="flex items-start justify-between">
            <div class="text-3xl">{{ icon }}</div>
            <span v-if="alert" class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500"></span>
            </span>
        </div>
        <h3 class="mt-3 font-bold text-lg text-slate-900 dark:text-slate-100">{{ title }}</h3>
        <p class="text-sm text-slate-600 dark:text-slate-300 mt-1.5">{{ text }}</p>
        <span v-if="cta" class="mt-4 inline-block text-sm font-semibold text-emerald-700 dark:text-emerald-400 group-hover:text-emerald-800 dark:group-hover:text-emerald-300">
            {{ cta }} →
        </span>
    </component>
</template>

<script setup>
defineProps({
    title: String,
    icon: String,
    text: String,
    cta: String,
    to: [String, Object],
    alert: Boolean,
});
</script>
