/**
 * Currency helpers shared across the SPA.
 *
 * Audit 2026-05 — single source of truth for the EUR ↔ XOF/XAF peg, replacing
 * the previously duplicated `const EUR_TO_XOF = 655.957` literals scattered
 * across pages. The runtime live rate is fetched via `useExchangeRate`; this
 * module only ships the treaty-fixed CFA peg as a fallback.
 */

export const EUR_TO_XOF = 655.957; // BCEAO/BEAC peg, fixed by treaty.

const xofFmt = new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    maximumFractionDigits: 0,
});

/**
 * Convert an EUR amount to XOF using the fixed peg, rounded to whole CFA.
 */
export function eurToXof(eur) {
    const n = parseFloat(eur);
    if (!Number.isFinite(n)) return 0;
    return Math.round(n * EUR_TO_XOF);
}

/**
 * Format a number as XOF (no decimals, French locale).
 */
export function formatXof(amount) {
    return xofFmt.format(amount || 0);
}

/**
 * One-shot convenience: takes EUR and returns the formatted XOF string.
 */
export function eurAsXof(eur) {
    return formatXof(eurToXof(eur));
}

/**
 * Format any (amount, currency) pair using French locale + sane defaults.
 */
export function formatMoney(amount, currency = 'EUR') {
    const cur = (currency || 'EUR').toUpperCase();
    const n = parseFloat(amount) || 0;
    try {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: cur,
            maximumFractionDigits: cur === 'XOF' || cur === 'XAF' || cur === 'JPY' ? 0 : 2,
        }).format(n);
    } catch {
        return `${n} ${cur}`;
    }
}
