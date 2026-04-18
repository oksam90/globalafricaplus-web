import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Send cookies (session + XSRF-TOKEN) with every request
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

// axios will automatically read the XSRF-TOKEN cookie set by Laravel
// and forward it as the X-XSRF-TOKEN header. This stays in sync even
// after session regeneration (login/register), unlike the static meta tag.

/**
 * Global response interceptor:
 * - 419 (CSRF mismatch): refresh cookie and retry the request once
 * - 401 (unauthenticated): clear auth state
 */
let isRefreshingCsrf = false;
let csrfQueue = [];

window.axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config;

        // Handle 419 — CSRF token expired. Retry once after refreshing the cookie.
        if (error.response?.status === 419 && !originalRequest._retried) {
            originalRequest._retried = true;

            if (!isRefreshingCsrf) {
                isRefreshingCsrf = true;
                try {
                    // A simple GET refreshes the XSRF-TOKEN cookie
                    await window.axios.get('/api/auth/me');
                } catch {
                    // ignore
                } finally {
                    isRefreshingCsrf = false;
                    csrfQueue.forEach((cb) => cb());
                    csrfQueue = [];
                }
                return window.axios(originalRequest);
            } else {
                // Queue subsequent 419s while refreshing
                return new Promise((resolve) => {
                    csrfQueue.push(() => resolve(window.axios(originalRequest)));
                });
            }
        }

        return Promise.reject(error);
    }
);
