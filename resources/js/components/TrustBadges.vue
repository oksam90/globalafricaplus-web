<template>
    <div v-if="badges" class="flex flex-wrap gap-1.5" :class="sizeClasses.wrap">
        <span
            v-for="b in items"
            :key="b.key"
            :title="b.tooltip"
            class="inline-flex items-center gap-1 rounded-full font-semibold border transition"
            :class="[
                sizeClasses.chip,
                b.ok
                    ? 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-700/50'
                    : 'bg-slate-100 text-slate-500 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700',
            ]">
            <span aria-hidden="true">{{ b.ok ? '✓' : '○' }}</span>
            <span v-if="showLabels">{{ b.label }}</span>
        </span>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { stageLabel } from '../utils/projectStages';

const props = defineProps({
    /**
     * Objet renvoyé par l'API sur project.trust_badges :
     *   { legal_formalization: bool|null, bank_account: bool,
     *     stage: 'idea'|'mvp'|'launch'|'scaling', stage_info: bool, stage_docs: bool }
     * - `null` = relation user non chargée → on cache le badge plutôt que de le marquer faux.
     */
    badges: { type: Object, required: true },
    /** 'sm' (card) | 'md' (sidebar / fiche détail) */
    size: { type: String, default: 'sm' },
    /** Masquer les libellés et n'afficher que les icônes. */
    showLabels: { type: Boolean, default: true },
});

const sizeClasses = computed(() => ({
    sm: {
        wrap: '',
        chip: 'text-[10px] px-1.5 py-0.5',
    },
    md: {
        wrap: '',
        chip: 'text-xs px-2.5 py-1',
    },
}[props.size] || { wrap: '', chip: 'text-[10px] px-1.5 py-0.5' }));

const items = computed(() => {
    const out = [];
    if (props.badges?.legal_formalization !== null && props.badges?.legal_formalization !== undefined) {
        out.push({
            key: 'legal',
            label: 'Statut juridique',
            ok: !!props.badges.legal_formalization,
            tooltip: props.badges.legal_formalization
                ? 'Statut juridique, RCCM et n° fiscal renseignés par le porteur.'
                : 'Le porteur n\'a pas complété son statut juridique, RCCM ou n° fiscal.',
        });
    }
    out.push({
        key: 'bank',
        label: 'Compte vérifié',
        ok: !!props.badges?.bank_account,
        tooltip: props.badges?.bank_account
            ? 'Compte bancaire de réception (séquestre) entièrement renseigné.'
            : 'Le compte bancaire de réception n\'est pas complet — le décaissement automatique des jalons est désactivé.',
    });

    // ── Badges propres au stade du projet ──
    const sLabel = stageLabel(props.badges?.stage);
    if (sLabel) {
        if (props.badges?.stage_info !== undefined) {
            out.push({
                key: 'stage_info',
                label: `Informations (${sLabel})`,
                ok: !!props.badges.stage_info,
                tooltip: props.badges.stage_info
                    ? `Informations à fournir — stade « ${sLabel} » : toutes les informations demandées sont renseignées.`
                    : `Informations à fournir — stade « ${sLabel} » : informations incomplètes.`,
            });
        }
        if (props.badges?.stage_docs !== undefined) {
            out.push({
                key: 'stage_docs',
                label: `Documents (${sLabel})`,
                ok: !!props.badges.stage_docs,
                tooltip: props.badges.stage_docs
                    ? `Documents requis — stade « ${sLabel} » : tous les documents demandés (pitch deck inclus) sont fournis.`
                    : `Documents requis — stade « ${sLabel} » : documents incomplets (pitch deck inclus).`,
            });
        }
    }
    return out;
});
</script>
