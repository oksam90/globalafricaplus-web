<template>
    <div v-if="methods.length > 1" class="space-y-2">
        <label class="block text-sm font-semibold text-slate-800 dark:text-slate-200">
            {{ label }}
        </label>

        <div class="grid gap-2" :class="methods.length > 2 ? 'sm:grid-cols-3' : 'sm:grid-cols-2'">
            <button
                v-for="m in methods"
                :key="m.key"
                type="button"
                @click="select(m.key)"
                class="text-left px-3 py-2.5 rounded-lg border transition focus:outline-none focus:ring-2 focus:ring-emerald-400"
                :class="modelValue === m.key
                    ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/25 ring-1 ring-emerald-400'
                    : 'border-slate-200 dark:border-slate-600 hover:border-emerald-300 dark:hover:border-emerald-700'">
                <span class="flex items-center gap-2">
                    <span class="text-lg leading-none">{{ m.icon }}</span>
                    <span class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ m.label }}</span>
                    <span v-if="modelValue === m.key" class="ml-auto text-emerald-600 dark:text-emerald-400 text-xs font-black">✓</span>
                </span>
                <span class="block mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">{{ m.description }}</span>
                <span v-if="priceFor(m.key)" class="block mt-1 text-xs font-bold text-emerald-700 dark:text-emerald-400">
                    {{ priceFor(m.key) }}
                </span>
            </button>
        </div>
    </div>
</template>

<script setup>
/**
 * Sélecteur de moyen de paiement — mobile money (PawaPay) ou carte bancaire
 * (PayDunya). La liste vient du serveur (`/api/payment-methods`) pour qu'un
 * moyen puisse être désactivé sans redéploiement du front.
 *
 * `prices` (optionnel) : { [method]: 'texte à afficher' } — permet d'annoncer
 * le coût réel de chaque option (Montant Envoyé) directement sur la carte.
 */
import { onMounted, ref } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: 'Moyen de paiement' },
    prices: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['update:modelValue', 'loaded']);

const methods = ref([]);

function priceFor(key) {
    return props.prices?.[key] || '';
}

function select(key) {
    emit('update:modelValue', key);
}

onMounted(async () => {
    try {
        const { data } = await window.axios.get('/api/payment-methods');
        methods.value = data.methods || [];
        emit('loaded', data);
        if (!props.modelValue && data.default_method) {
            emit('update:modelValue', data.default_method);
        }
    } catch {
        // Repli silencieux : le serveur reste maître du moyen appliqué.
        methods.value = [];
    }
});
</script>
