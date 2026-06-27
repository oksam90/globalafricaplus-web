<template>
    <div class="rounded-xl border border-slate-100 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/40 p-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Convention d'investissement</span>
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full" :class="badge.class">
                    {{ badge.label }}
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Télécharger la convention (PDF, repli Word) -->
                <button v-if="canDownload" type="button" :disabled="busy.dl" @click="downloadContract"
                    class="text-xs font-semibold px-2.5 py-1.5 rounded-md border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-700 disabled:opacity-60">
                    📄 {{ busy.dl ? '…' : (hasPdf ? 'Convention (PDF)' : 'Convention (Word)') }}
                </button>

                <!-- Envoyer à la signature -->
                <button v-if="canSend" type="button" :disabled="busy.send" @click="sendForSignature"
                    class="text-xs font-semibold px-2.5 py-1.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white disabled:opacity-60">
                    ✍️ {{ busy.send ? 'Envoi…' : 'Envoyer à la signature' }}
                </button>

                <!-- Rafraîchir le statut (en attente de signature) -->
                <button v-if="statusLocal === 'sent'" type="button" :disabled="busy.refresh" @click="refreshStatus"
                    class="text-xs font-semibold px-2.5 py-1.5 rounded-md border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-700 disabled:opacity-60">
                    🔄 {{ busy.refresh ? '…' : 'Rafraîchir' }}
                </button>

                <!-- Télécharger la convention signée -->
                <button v-if="signedLocal" type="button" :disabled="busy.dlSigned" @click="downloadSigned"
                    class="text-xs font-semibold px-2.5 py-1.5 rounded-md bg-slate-800 hover:bg-slate-900 text-white disabled:opacity-60">
                    ✅ {{ busy.dlSigned ? '…' : 'Convention signée (PDF)' }}
                </button>
            </div>
        </div>

        <p v-if="statusLocal === 'sent'" class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
            Les deux parties ont reçu un email de signature. Cliquez sur « Rafraîchir » pour vérifier l'avancement.
        </p>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useToast } from '../composables/useToast';

const props = defineProps({
    investment: { type: Object, required: true },
});
const emit = defineEmits(['updated']);
const toast = useToast();

const statusLocal = ref(props.investment.contract_status || 'none');
const signedLocal = ref(!!props.investment.has_signed_contract);
const hasPdf = computed(() => !!props.investment.has_contract_pdf);

const busy = reactive({ dl: false, send: false, refresh: false, dlSigned: false });

const id = computed(() => props.investment.id);

const badge = computed(() => {
    const map = {
        none:      { label: 'En préparation',           class: 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300' },
        generated: { label: 'Prête',                    class: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' },
        sent:      { label: 'Envoyée à la signature',   class: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' },
        signed:    { label: 'Signée',                   class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' },
        failed:    { label: 'Échec — à renvoyer',       class: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300' },
    };
    return map[statusLocal.value] || map.none;
});

// On peut télécharger dès qu'une convention existe (ou à la demande, qui la génère).
const canDownload = computed(() => ['generated', 'sent', 'signed', 'failed'].includes(statusLocal.value) || props.investment.has_contract);
// On peut (r)envoyer tant que ce n'est pas déjà envoyé/signé.
const canSend = computed(() => !['sent', 'signed'].includes(statusLocal.value));

async function blobDownload(url, filename) {
    const res = await window.axios.get(url, { responseType: 'blob' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(new Blob([res.data]));
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(link.href);
}

async function downloadContract() {
    busy.dl = true;
    try {
        await blobDownload(`/api/investments/${id.value}/contract?format=pdf`, `Convention_${id.value}.${hasPdf.value ? 'pdf' : 'docx'}`);
    } catch {
        toast.error('Téléchargement de la convention impossible.');
    } finally {
        busy.dl = false;
    }
}

async function sendForSignature() {
    busy.send = true;
    try {
        const { data } = await window.axios.post(`/api/investments/${id.value}/contract/send`);
        statusLocal.value = data.contract_status || 'sent';
        toast.success('Convention envoyée à la signature des deux parties.');
        emit('updated', statusLocal.value);
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Envoi à la signature impossible.');
    } finally {
        busy.send = false;
    }
}

async function refreshStatus() {
    busy.refresh = true;
    try {
        const { data } = await window.axios.get(`/api/investments/${id.value}/contract/refresh`);
        statusLocal.value = data.contract_status || statusLocal.value;
        if (data.signed) {
            signedLocal.value = true;
            toast.success('Convention signée par les deux parties 🎉');
        } else {
            toast.info('Statut mis à jour. Signature toujours en attente.');
        }
        emit('updated', statusLocal.value);
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Mise à jour du statut impossible.');
    } finally {
        busy.refresh = false;
    }
}

async function downloadSigned() {
    busy.dlSigned = true;
    try {
        await blobDownload(`/api/investments/${id.value}/contract/signed`, `Convention_signee_${id.value}.pdf`);
    } catch {
        toast.error('Téléchargement de la convention signée impossible.');
    } finally {
        busy.dlSigned = false;
    }
}
</script>
