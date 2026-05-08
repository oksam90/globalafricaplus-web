/**
 * Lazy loader + thin wrapper for the Smile Identity Hosted Web Integration.
 *
 * The SDK is a third-party script hosted on Smile's CDN. We inject it on
 * demand the first time `launchSmileSdk()` is called, then re-use the global
 * `SmileIdentity` constructor on subsequent calls.
 *
 * Sandbox CDN: https://cdn.smileidentity.com/inline/v1/js/script.min.js
 *
 * Pass a fresh web_token (from POST /api/v1/kyc/web-token) every time —
 * tokens are single-use.
 */

const SDK_URL = 'https://cdn.smileidentity.com/inline/v1/js/script.min.js';

let sdkPromise = null;

export function loadSmileSdk() {
    if (typeof window === 'undefined') return Promise.reject(new Error('SSR not supported'));
    if (window.SmileIdentity) return Promise.resolve(window.SmileIdentity);
    if (sdkPromise) return sdkPromise;

    sdkPromise = new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[src="${SDK_URL}"]`);
        const script = existing ?? document.createElement('script');

        if (!existing) {
            script.src = SDK_URL;
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        }

        const onLoad = () => {
            if (window.SmileIdentity) {
                resolve(window.SmileIdentity);
            } else {
                reject(new Error('Smile Identity SDK loaded but global constructor missing'));
            }
        };

        if (script.readyState === 'complete') {
            onLoad();
        } else {
            script.addEventListener('load', onLoad, { once: true });
            script.addEventListener('error', () => {
                sdkPromise = null;
                reject(new Error('Failed to load Smile Identity SDK'));
            }, { once: true });
        }
    });

    return sdkPromise;
}

/**
 * Open the hosted Smile Identity widget.
 *
 * @param {object} opts
 * @param {string} opts.token        - Single-use web token from /api/v1/kyc/web-token
 * @param {string} [opts.product]    - 'biometric_kyc' (default) | 'doc_verification' | 'authentication' | 'basic_kyc' | 'enhanced_kyc'
 * @param {string} [opts.environment]- 'sandbox' (default) | 'production'
 * @param {object} [opts.partnerDetails]
 * @param {function} [opts.onSuccess] - (result) => {}
 * @param {function} [opts.onError]   - (error) => {}
 * @param {function} [opts.onClose]   - () => {}
 * @returns {Promise<void>}
 */
export async function launchSmileSdk(opts) {
    if (!opts?.token) throw new Error('launchSmileSdk: token is required');

    const SmileIdentity = await loadSmileSdk();

    const env = opts.environment
        ?? import.meta.env.VITE_SMILE_ENVIRONMENT
        ?? 'sandbox';

    SmileIdentity({
        token:        opts.token,
        product:      opts.product ?? 'biometric_kyc',
        callback_url: opts.callbackUrl ?? '/api/v1/webhooks/smile-identity',
        environment:  env,
        partner_details: {
            partner_id:  import.meta.env.VITE_SMILE_PARTNER_ID ?? '',
            name:        opts.partnerDetails?.name        ?? 'Globalafrica+',
            logo_url:    opts.partnerDetails?.logoUrl     ?? `${window.location.origin}/brand/icon-light.svg`,
            policy_url:  opts.partnerDetails?.policyUrl   ?? `${window.location.origin}/confidentialite`,
            theme_color: opts.partnerDetails?.themeColor  ?? '#047857', // emerald-700
        },
        onSuccess: (result) => {
            try { opts.onSuccess?.(result); } catch (e) { console.error(e); }
        },
        onClose: () => {
            try { opts.onClose?.(); } catch (e) { console.error(e); }
        },
        onError: (error) => {
            try { opts.onError?.(error); } catch (e) { console.error(e); }
        },
    });
}

export function useSmileWebSdk() {
    return { loadSmileSdk, launchSmileSdk };
}
