import { defineStore } from 'pinia';

const ACTIVE_ROLE_KEY = 'africaplus.activeRole';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        bootstrapped: false,
        loading: false,
        error: null,
        activeRole: localStorage.getItem(ACTIVE_ROLE_KEY) || null,
        subscription: { plan_slug: 'free', has_active: false, is_refundable: false },
        kyc: { level: 'none', is_verified: false, has_pending: false, session_status: null, completion: 0 },
    }),
    getters: {
        isAuthenticated: (s) => !!s.user,
        roles: (s) => (s.user?.roles || []).map((r) => r.slug),
        roleObjects: (s) => s.user?.roles || [],
        hasRole: (s) => (slug) => (s.user?.roles || []).some((r) => r.slug === slug),
        roleProfiles: (s) => s.user?.role_profiles || [],
        profileFor: (s) => (slug) => {
            return (s.user?.role_profiles || []).find((p) => p.role?.slug === slug) || null;
        },
        completionFor: (s) => (slug) => {
            const p = (s.user?.role_profiles || []).find((p) => p.role?.slug === slug);
            return p?.completion ?? 0;
        },
        /** Subscription helpers */
        planSlug: (s) => s.subscription?.plan_slug || 'free',
        hasActiveSubscription: (s) => !!s.subscription?.has_active,
        isFreePlan: (s) => (s.subscription?.plan_slug || 'free') === 'free',
        /** KYC helpers */
        isKycVerified: (s) => !!s.kyc?.is_verified,
        kycLevel: (s) => s.kyc?.level || 'none',
        needsKyc: (s) => !s.kyc?.is_verified,
        /** Global completion = average across role profiles */
        globalCompletion: (s) => {
            const profiles = s.user?.role_profiles || [];
            if (!profiles.length) return 0;
            const sum = profiles.reduce((acc, p) => acc + (p.completion || 0), 0);
            return Math.round(sum / profiles.length);
        },
    },
    actions: {
        setUser(user) {
            this.user = user;
            if (user) {
                const userRoles = (user.roles || []).map((r) => r.slug);
                // Prefer backend value, fallback to local storage, fallback to first role
                const candidate =
                    user.active_role_slug ||
                    this.activeRole ||
                    userRoles[0] ||
                    null;
                if (candidate && userRoles.includes(candidate)) {
                    this.activeRole = candidate;
                    localStorage.setItem(ACTIVE_ROLE_KEY, candidate);
                } else {
                    this.activeRole = null;
                    localStorage.removeItem(ACTIVE_ROLE_KEY);
                }
            } else {
                this.activeRole = null;
                localStorage.removeItem(ACTIVE_ROLE_KEY);
            }
        },
        async fetchUser() {
            // Guard against concurrent calls (avoids duplicate requests on navigation)
            if (this._fetching) return this._fetching;
            this._fetching = (async () => {
                try {
                    const { data } = await window.axios.get('/api/auth/me');
                    this.setUser(data.user ?? null);
                    if (data.subscription) {
                        this.subscription = data.subscription;
                    } else {
                        this.subscription = { plan_slug: 'free', has_active: false, is_refundable: false };
                    }
                    if (data.kyc) {
                        this.kyc = data.kyc;
                    } else {
                        this.kyc = { level: 'none', is_verified: false, has_pending: false, session_status: null, completion: 0 };
                    }
                } catch {
                    this.setUser(null);
                    this.subscription = { plan_slug: 'free', has_active: false, is_refundable: false };
                    this.kyc = { level: 'none', is_verified: false, has_pending: false, session_status: null, completion: 0 };
                } finally {
                    this.bootstrapped = true;
                    this._fetching = null;
                }
            })();
            return this._fetching;
        },
        async login(payload) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await window.axios.post('/api/auth/login', payload);
                this.setUser(data.user);
                return true;
            } catch (e) {
                this.error = e?.response?.data?.message || 'Connexion impossible';
                return false;
            } finally {
                this.loading = false;
            }
        },
        async register(payload) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await window.axios.post('/api/auth/register', payload);
                this.setUser(data.user);
                return true;
            } catch (e) {
                this.error =
                    e?.response?.data?.message ||
                    Object.values(e?.response?.data?.errors || {})[0]?.[0] ||
                    "Inscription impossible";
                return false;
            } finally {
                this.loading = false;
            }
        },
        async logout() {
            try {
                await window.axios.post('/api/auth/logout');
            } catch {
                // Ignore 419/401 errors — session may already be invalid
            } finally {
                this.setUser(null);
                this.bootstrapped = false;
                // After logout, Laravel regenerated the CSRF token.
                // Re-fetch it by hitting the sanctum/csrf-cookie endpoint
                // or simply refresh the XSRF-TOKEN cookie via a GET request.
                try {
                    await window.axios.get('/api/auth/me');
                } catch {
                    // Expected: returns null user, but refreshes CSRF cookie
                }
                this.bootstrapped = true;
            }
        },
        async switchRole(slug) {
            if (!this.hasRole(slug)) return false;
            this.activeRole = slug;
            localStorage.setItem(ACTIVE_ROLE_KEY, slug);
            try {
                const { data } = await window.axios.post('/api/me/active-role', { slug });
                this.setUser(data.user);
                return true;
            } catch {
                return false;
            }
        },
        async addRole(slug) {
            try {
                const { data } = await window.axios.post('/api/me/roles', { slug });
                this.setUser(data.user);
                return true;
            } catch (e) {
                this.error = e?.response?.data?.message || 'Impossible d’ajouter ce rôle.';
                return false;
            }
        },
        async removeRole(slug) {
            try {
                const { data } = await window.axios.delete(`/api/me/roles/${slug}`);
                this.setUser(data.user);
                return true;
            } catch (e) {
                this.error = e?.response?.data?.message || 'Impossible de retirer ce rôle.';
                return false;
            }
        },
        async updateRoleProfile(slug, payload) {
            const { data } = await window.axios.put(`/api/me/profiles/${slug}`, payload);
            // refresh user to pick up updated profiles
            await this.fetchUser();
            return data.profile;
        },
        async loadRoleProfile(slug) {
            const { data } = await window.axios.get(`/api/me/profiles/${slug}`);
            return data;
        },
    },
});
