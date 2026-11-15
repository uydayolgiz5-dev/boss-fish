import { reactive } from 'vue';

const STORAGE = {
    token: 'token',
    expiresAt: 'auth_expires_at',
    role: 'auth_role',
    type: 'auth_type',
};

const state = reactive({
    user: null,
    token: localStorage.getItem(STORAGE.token),
});

function isSessionExpired() {
    const expiresAt = localStorage.getItem(STORAGE.expiresAt);
    if (! expiresAt) {
        return false;
    }

    return Date.now() >= new Date(expiresAt).getTime();
}

function persistSessionMeta(user, expiresAt) {
    const role = user?.role || null;
    const type = user?.type || null;

    if (role) {
        localStorage.setItem(STORAGE.role, role);
    } else {
        localStorage.removeItem(STORAGE.role);
    }

    if (type) {
        localStorage.setItem(STORAGE.type, type);
    } else {
        localStorage.removeItem(STORAGE.type);
    }

    if (expiresAt) {
        localStorage.setItem(STORAGE.expiresAt, expiresAt);
    } else {
        localStorage.removeItem(STORAGE.expiresAt);
    }
}

function applyToken(token) {
    state.token = token;
    if (token) {
        localStorage.setItem(STORAGE.token, token);
        window.axios.defaults.headers.common.Authorization = `Bearer ${token}`;
    } else {
        localStorage.removeItem(STORAGE.token);
        delete window.axios.defaults.headers.common.Authorization;
    }
}

export default {
    state,

    setAuth(user, token, expiresAt = null) {
        state.user = user;
        applyToken(token);
        persistSessionMeta(user, expiresAt);
    },

    clearAuth() {
        state.user = null;
        applyToken(null);
        persistSessionMeta(null, null);
    },

    hasValidSession() {
        return !!state.token && !isSessionExpired();
    },

    savedRole() {
        return localStorage.getItem(STORAGE.role);
    },

    async fetchUser() {
        if (!this.hasValidSession()) {
            this.clearAuth();
            return null;
        }

        try {
            const { data } = await window.axios.get('/auth/me');
            state.user = data;
            persistSessionMeta(data, localStorage.getItem(STORAGE.expiresAt));
            return data;
        } catch (e) {
            this.clearAuth();
            return null;
        }
    },

    async initAuth() {
        if (isSessionExpired()) {
            this.clearAuth();
            return null;
        }

        const token = localStorage.getItem(STORAGE.token);
        if (!token) {
            this.clearAuth();
            return null;
        }

        applyToken(token);
        return this.fetchUser();
    },

    isSuperadmin() {
        return state.user?.type === 'superadmin' || state.user?.role === 'owner';
    },

    isPlatformAdmin() {
        return state.user?.type === 'platform_admin' || state.user?.role === 'admin';
    },

    isSuperadminStaff() {
        return state.user?.type === 'superadmin_staff' || state.user?.role === 'venger';
    },

    isCompanyAdmin() {
        return state.user?.type === 'company_admin' || state.user?.role === 'director';
    },

    isEmployee() {
        return state.user?.type === 'employee' || state.user?.role === 'worker';
    },

    /** BOSS kabineti: asosiy superadmin yoki yordamchi admin */
    isOwner() {
        return this.isSuperadmin() || this.isPlatformAdmin();
    },

    isDirector() {
        return this.isCompanyAdmin();
    },

    isWorker() {
        return this.isEmployee();
    },

    isVenger() {
        return this.isSuperadminStaff();
    },

    can(section) {
        if (this.isSuperadmin() || this.isPlatformAdmin() || this.isCompanyAdmin()) {
            return true;
        }
        const perms = state.user?.permissions || [];
        return perms.includes(section);
    },

    canAny(sections = []) {
        return (sections || []).some((section) => this.can(section));
    },

    isAuthenticated() {
        return this.hasValidSession() && !!state.user;
    },

    employeeHome() {
        if (this.can('daily_reports') || this.can('main_reports') || this.can('fish_ops') || this.can('activities')) {
            return { name: 'director-fishery-ponds' };
        }
        if (this.can('warehouse')) return { name: 'director-warehouse' };
        if (this.can('finance')) return { name: 'director-finance-kirim' };
        if (this.can('finance_reports')) return { name: 'director-finance-overview' };
        if (this.can('farms') || this.can('dashboard')) return { name: 'director-home' };
        // Ruxsatsiz hodim — redirect loop emas, murojaat
        return { name: 'director-support' };
    },

    homeForRole(role = state.user?.role, type = state.user?.type) {
        const t = type || state.user?.type;
        if (t === 'superadmin' || role === 'owner') return { name: 'owner-cabinet' };
        if (t === 'platform_admin' || role === 'admin') return { name: 'owner-cabinet' };
        if (t === 'superadmin_staff' || role === 'venger') return { name: 'venger-cabinet' };
        if (t === 'company_admin' || role === 'director') return { name: 'director-home' };
        // Hodim — bosh sahifa emas, faqat o‘z vazifasi sahifasiga
        if (t === 'employee' || role === 'worker') return this.employeeHome();
        return { name: 'home' };
    },

    loginRouteForRole() {
        return { name: 'login' };
    },

    portalRouteForRole(role) {
        if (this.isAuthenticated()) {
            const t = state.user?.type;
            if (
                (role === 'owner' && this.isSuperadmin())
                || (role === 'admin' && this.isPlatformAdmin())
                || (role === 'director' && this.isCompanyAdmin())
                || (role === 'worker' && this.isEmployee())
                || (role === 'venger' && this.isSuperadminStaff())
            ) {
                return this.homeForRole(state.user?.role, t);
            }
        }

        return { name: 'login' };
    },
};
