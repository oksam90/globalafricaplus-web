/**
 * Smile Identity ResultCode → human message + UX hint dictionary.
 * Spec § 7.2 + § 11 (test scenarios T-01..T-16).
 */

const CODES = {
    '0810': {
        tone: 'success',
        title: 'Vérification approuvée',
        message: 'Votre identité a été vérifiée avec succès.',
        retry: false,
    },
    '0811': {
        tone: 'warning',
        title: 'Approuvée provisoirement',
        message: 'Votre vérification est validée à titre provisoire — un agent va revoir votre dossier sous 48 h.',
        retry: false,
    },
    '0812': {
        tone: 'error',
        title: 'Vérification rejetée',
        message: 'Votre identité n\'a pas pu être vérifiée. Vérifiez les informations saisies ou réessayez avec un autre document.',
        retry: true,
    },
    '0814': {
        tone: 'info',
        title: 'Revue humaine en cours',
        message: 'Votre dossier est en cours d\'examen manuel. Vous serez notifié(e) du résultat sous 48 h.',
        retry: false,
    },
    '0913': {
        tone: 'error',
        title: 'Numéro d\'identité invalide',
        message: 'Le numéro d\'identité saisi est invalide. Vérifiez le format puis ressaisissez-le.',
        retry: true,
    },
    '0914': {
        tone: 'warning',
        title: 'ID non trouvé',
        message: 'Votre identité n\'a pas été trouvée auprès de l\'autorité gouvernementale. Essayez la vérification par document (Document Verification).',
        retry: true,
        suggest: 'document',
    },
    '0915': {
        tone: 'info',
        title: 'Autorité temporairement indisponible',
        message: 'L\'autorité d\'identité est temporairement indisponible. Une nouvelle tentative est programmée automatiquement.',
        retry: false,
    },
};

const FALLBACK = {
    tone: 'info',
    title: 'Statut inconnu',
    message: 'Code de résultat inattendu. Contactez le support si le problème persiste.',
    retry: false,
};

export function describeResultCode(code) {
    if (!code) return FALLBACK;
    return CODES[String(code)] ?? FALLBACK;
}

/**
 * AML risk_level → badge class & label.
 */
const RISK = {
    low:      { tone: 'success', label: 'Faible',  className: 'bg-emerald-100 text-emerald-700' },
    medium:   { tone: 'warning', label: 'Moyen',   className: 'bg-amber-100 text-amber-700' },
    high:     { tone: 'error',   label: 'Élevé',   className: 'bg-rose-100 text-rose-700' },
    critical: { tone: 'error',   label: 'Critique', className: 'bg-red-200 text-red-800 font-bold' },
};

export function describeRiskLevel(level) {
    return RISK[level] ?? RISK.low;
}

/**
 * Tier hierarchy helper used by the UI to compute progress / available
 * features. Mirrors RequireKYCLevel::RANK on the backend.
 */
export const TIER_RANK = { basic: 0, verified: 1, certified: 2 };

export function tierRank(level) {
    return TIER_RANK[level] ?? -1;
}
