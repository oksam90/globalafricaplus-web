import { ref, readonly } from 'vue';

/**
 * Wraps the Smile Identity-backed `/api/v1/kyc/*` endpoints (Sprint 2).
 *
 * Each method returns the raw response payload to the caller so the wizard
 * can decide what to do; we only mutate `status` / `history` after fetches
 * that affect them so the cache stays fresh between steps.
 */
export function useKycApi() {
    const status = ref(null);
    const history = ref({ data: [], current_page: 1, last_page: 1 });
    const loading = ref(false);
    const submitting = ref(false);

    async function fetchStatus() {
        loading.value = true;
        try {
            const { data } = await window.axios.get('/api/v1/kyc/status');
            status.value = data;
            return data;
        } finally {
            loading.value = false;
        }
    }

    async function fetchHistory(page = 1) {
        const { data } = await window.axios.get('/api/v1/kyc/history', { params: { page } });
        history.value = data;
        return data;
    }

    async function submitBasic(payload) {
        submitting.value = true;
        try {
            const { data } = await window.axios.post('/api/v1/kyc/basic', payload);
            return data;
        } finally {
            submitting.value = false;
            await fetchStatus();
        }
    }

    async function submitBiometric(payload) {
        submitting.value = true;
        try {
            const { data } = await window.axios.post('/api/v1/kyc/biometric', payload);
            return data;
        } finally {
            submitting.value = false;
            await fetchStatus();
        }
    }

    async function submitDocument(payload) {
        submitting.value = true;
        try {
            const { data } = await window.axios.post('/api/v1/kyc/document', payload);
            return data;
        } finally {
            submitting.value = false;
            await fetchStatus();
        }
    }

    async function submitAML(payload) {
        submitting.value = true;
        try {
            const { data } = await window.axios.post('/api/v1/kyc/aml', payload);
            return data;
        } finally {
            submitting.value = false;
            await fetchStatus();
        }
    }

    async function fetchWebToken(product = 'biometric_kyc') {
        const { data } = await window.axios.post('/api/v1/kyc/web-token', { product });
        return data;
    }

    return {
        status: readonly(status),
        history: readonly(history),
        loading: readonly(loading),
        submitting: readonly(submitting),
        fetchStatus,
        fetchHistory,
        submitBasic,
        submitBiometric,
        submitDocument,
        submitAML,
        fetchWebToken,
    };
}
