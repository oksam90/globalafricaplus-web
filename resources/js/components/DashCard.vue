<template>
    <component :is="to ? 'router-link' : 'div'" :to="to || undefined"
        class="bg-white border border-slate-100 rounded-2xl p-6 transition group"
        :class="to ? 'hover:border-emerald-200 hover:shadow-sm cursor-pointer' : ''">
        <div class="flex items-start justify-between">
            <div class="text-3xl">{{ icon }}</div>
            <span v-if="alert" class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500"></span>
            </span>
        </div>
        <h3 class="mt-3 font-bold text-lg">{{ title }}</h3>
        <p class="text-sm text-slate-600 mt-1.5">{{ text }}</p>
        <span v-if="cta" class="mt-4 inline-block text-sm font-semibold text-emerald-700 group-hover:text-emerald-800">
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
