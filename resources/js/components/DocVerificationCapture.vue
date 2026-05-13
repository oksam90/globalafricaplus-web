<template>
    <div class="rounded-xl border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50/30 dark:bg-emerald-900/10 p-5 space-y-5">
        <header>
            <h3 class="font-bold text-slate-900 dark:text-slate-100">📄 Vérification par document — Mode REST</h3>
            <p class="text-sm text-slate-600 dark:text-slate-300 mt-1">
                Capturez votre selfie via votre webcam, puis téléchargez la photo de votre document.
                Tout est transmis chiffré à Smile Identity ; nous ne stockons ni l'image ni les liens signés.
            </p>
        </header>

        <!-- Step A — selfie via webcam -->
        <section class="space-y-3">
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">A. Selfie</h4>

            <div v-if="!selfieDataUrl" class="space-y-2">
                <div class="aspect-video bg-slate-900 dark:bg-black rounded-lg overflow-hidden relative">
                    <video ref="videoRef" autoplay playsinline muted class="w-full h-full object-cover"></video>
                    <div v-if="!cameraReady" class="absolute inset-0 flex items-center justify-center text-slate-400 text-sm">
                        {{ cameraError || 'Initialisation de la caméra…' }}
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button v-if="!cameraReady" @click="startCamera" type="button"
                        class="px-4 py-2 rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold">
                        🎥 Démarrer la caméra
                    </button>
                    <button v-else @click="captureSelfie" type="button"
                        class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">
                        📸 Prendre le selfie
                    </button>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Veillez à un bon éclairage, à regarder droit devant et à enlever tout accessoire (lunettes de soleil, masque).
                </p>
            </div>

            <div v-else class="space-y-2">
                <div class="rounded-lg overflow-hidden border border-emerald-300 dark:border-emerald-700 max-w-sm">
                    <img :src="selfieDataUrl" alt="selfie capturé" class="w-full" />
                </div>
                <button @click="resetSelfie" type="button"
                    class="text-xs font-semibold text-rose-600 hover:underline">
                    ✕ Reprendre le selfie
                </button>
            </div>
        </section>

        <!-- Step B — document upload -->
        <section class="space-y-3">
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">B. Photo du document</h4>

            <div v-if="!documentDataUrl">
                <label class="block">
                    <span class="sr-only">Photo du document</span>
                    <input type="file" accept="image/png,image/jpeg" capture="environment"
                        @change="onDocumentSelected"
                        class="block w-full text-sm text-slate-600 dark:text-slate-300
                               file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0
                               file:text-sm file:font-semibold file:bg-emerald-600 file:text-white
                               hover:file:bg-emerald-700 cursor-pointer" />
                </label>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Photo nette du recto, format JPG ou PNG, max 8 Mo. CNI, passeport ou permis de conduire.
                </p>
            </div>

            <div v-else class="space-y-2">
                <div class="rounded-lg overflow-hidden border border-emerald-300 dark:border-emerald-700 max-w-sm">
                    <img :src="documentDataUrl" alt="document capturé" class="w-full" />
                </div>
                <button @click="resetDocument" type="button"
                    class="text-xs font-semibold text-rose-600 hover:underline">
                    ✕ Changer le document
                </button>
            </div>
        </section>

        <!-- Identity context required by the API -->
        <section class="grid sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Pays</label>
                <select v-model="form.country" required
                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm">
                    <option v-for="c in countries" :key="c.code" :value="c.code">{{ c.flag }} {{ c.code }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">Type</label>
                <select v-model="form.id_type"
                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm">
                    <option value="NATIONAL_ID">CNI</option>
                    <option value="PASSPORT">Passeport</option>
                    <option value="DRIVERS_LICENSE">Permis de conduire</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1 text-slate-700 dark:text-slate-300">N° du document</label>
                <input v-model="form.id_number" required type="text" maxlength="64"
                    class="w-full px-3 py-2 rounded-md border border-slate-200 dark:border-slate-600 dark:bg-slate-900 text-sm" />
            </div>
        </section>

        <!-- Submission -->
        <div>
            <button @click="submit" type="button" :disabled="!canSubmit || submitting"
                class="w-full px-4 py-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold">
                {{ submitting ? 'Envoi sécurisé…' : '🔒 Soumettre la vérification' }}
            </button>
            <p v-if="error" class="mt-2 text-sm text-rose-600">{{ error }}</p>
            <p v-if="success" class="mt-2 text-sm text-emerald-700 dark:text-emerald-400">{{ success }}</p>
        </div>

        <canvas ref="canvasRef" class="hidden"></canvas>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onUnmounted } from 'vue';
import { useKycApi } from '../composables/useKycApi';

const props = defineProps({
    defaultCountry: { type: String, default: 'SN' },
    defaultIdType:  { type: String, default: 'NATIONAL_ID' },
});
const emit = defineEmits(['submitted', 'error']);

const kyc = useKycApi();

const videoRef  = ref(null);
const canvasRef = ref(null);
let stream      = null;

const cameraReady = ref(false);
const cameraError = ref('');
const selfieDataUrl   = ref(null);
const documentDataUrl = ref(null);
const submitting = ref(false);
const error      = ref('');
const success    = ref('');

const form = reactive({
    country:   props.defaultCountry,
    id_type:   props.defaultIdType,
    id_number: '',
});

const countries = [
    { code: 'SN', flag: '🇸🇳' }, { code: 'CI', flag: '🇨🇮' }, { code: 'ML', flag: '🇲🇱' },
    { code: 'BF', flag: '🇧🇫' }, { code: 'BJ', flag: '🇧🇯' }, { code: 'TG', flag: '🇹🇬' },
    { code: 'NE', flag: '🇳🇪' }, { code: 'NG', flag: '🇳🇬' }, { code: 'GH', flag: '🇬🇭' },
    { code: 'KE', flag: '🇰🇪' }, { code: 'FR', flag: '🇫🇷' },
];

const canSubmit = computed(() =>
    selfieDataUrl.value && documentDataUrl.value && form.id_number.trim().length >= 4
);

async function startCamera() {
    cameraError.value = '';
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
            audio: false,
        });
        if (videoRef.value) {
            videoRef.value.srcObject = stream;
            cameraReady.value = true;
        }
    } catch (e) {
        cameraError.value = e.name === 'NotAllowedError'
            ? 'Accès caméra refusé. Autorisez la caméra dans votre navigateur puis réessayez.'
            : 'Caméra indisponible : ' + e.message;
    }
}

function stopCamera() {
    if (stream) {
        stream.getTracks().forEach(t => t.stop());
        stream = null;
    }
    cameraReady.value = false;
}

function captureSelfie() {
    if (!videoRef.value || !canvasRef.value) return;
    const video = videoRef.value;
    const canvas = canvasRef.value;
    canvas.width  = video.videoWidth  || 640;
    canvas.height = video.videoHeight || 480;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    selfieDataUrl.value = canvas.toDataURL('image/jpeg', 0.9);
    stopCamera();
}

function resetSelfie() {
    selfieDataUrl.value = null;
    startCamera();
}

function onDocumentSelected(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    if (file.size > 8 * 1024 * 1024) {
        error.value = 'Fichier trop volumineux (max 8 Mo).';
        return;
    }
    const reader = new FileReader();
    reader.onload = () => { documentDataUrl.value = reader.result; };
    reader.onerror = () => { error.value = 'Lecture du fichier impossible.'; };
    reader.readAsDataURL(file);
}

function resetDocument() {
    documentDataUrl.value = null;
}

async function submit() {
    error.value = '';
    success.value = '';
    if (!canSubmit.value) {
        error.value = 'Selfie, document et numéro de pièce sont requis.';
        return;
    }
    submitting.value = true;
    try {
        // The SubmitKYCRequest already strips the `data:image/...;base64,` prefix
        // before validation, so we can pass the data URLs directly.
        const resp = await kyc.submitDocument({
            country:           form.country,
            id_type:           form.id_type,
            id_number:         form.id_number.trim(),
            selfie:            selfieDataUrl.value,
            id_document_front: documentDataUrl.value,
        });
        success.value = resp?.message || 'Vérification soumise — résultat attendu via callback.';
        emit('submitted', resp);
    } catch (e) {
        const body = e?.response?.data || {};
        error.value = body.message
            || Object.values(body.errors || {})[0]?.[0]
            || e.message
            || 'Échec de la soumission.';
        emit('error', e);
    } finally {
        submitting.value = false;
    }
}

onUnmounted(stopCamera);
</script>
