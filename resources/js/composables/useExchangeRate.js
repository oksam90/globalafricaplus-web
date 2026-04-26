import { ref, readonly } from 'vue';

const PEG_EUR_XOF = 655.957;
const cache = new Map();

export function useExchangeRate() {
    const rate = ref(PEG_EUR_XOF);
    const loading = ref(false);
    const fetchedAt = ref(null);
    const source = ref('peg');

    async function fetchRate(from = 'EUR', to = 'XOF') {
        const key = `${from.toUpperCase()}-${to.toUpperCase()}`;
        const cached = cache.get(key);
        if (cached && Date.now() - cached.at < 6 * 60 * 60 * 1000) {
            rate.value = cached.rate;
            fetchedAt.value = cached.at;
            source.value = cached.source;
            return cached.rate;
        }

        loading.value = true;
        try {
            const url = (from.toUpperCase() === 'XOF' && to.toUpperCase() === 'EUR')
                ? '/api/exchange-rates/xof-eur'
                : `/api/exchange-rates/${from}/${to}`;
            const { data } = await window.axios.get(url);
            rate.value = parseFloat(data.rate);
            fetchedAt.value = Date.now();
            source.value = 'live';
            cache.set(key, { rate: rate.value, at: fetchedAt.value, source: 'live' });
            return rate.value;
        } catch {
            source.value = 'peg';
            return rate.value;
        } finally {
            loading.value = false;
        }
    }

    function convert(amount, from = 'EUR', to = 'XOF') {
        const n = parseFloat(amount) || 0;
        return n * rate.value;
    }

    return {
        rate: readonly(rate),
        loading: readonly(loading),
        fetchedAt: readonly(fetchedAt),
        source: readonly(source),
        fetchRate,
        convert,
    };
}
