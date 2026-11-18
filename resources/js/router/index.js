import { createRouter, createWebHistory } from 'vue-router';
import auth from '../auth';
import { setDirectorFarmId } from '../utils/directorFarm';
import { getActingDirectorId } from '../utils/ownerContext';

import Home from '../pages/Home.vue';
import Guide from '../pages/Guide.vue';
import TarifContract from '../pages/TarifContract.vue';
import PublicLogin from '../pages/PublicLogin.vue';
import OwnerCabinet from '../pages/owner/OwnerCabinet.vue';
import OwnerDirectorHub from '../pages/owner/OwnerDirectorHub.vue';
import OwnerCompanies from '../pages/owner/OwnerCompanies.vue';
import CompanyEmployees from '../pages/company/CompanyEmployees.vue';
import DirectorSettings from '../pages/director/DirectorSettings.vue';
import DirectorAuditJournal from '../pages/director/DirectorAuditJournal.vue';
import OwnerSecurity from '../pages/owner/OwnerSecurity.vue';
import OwnerSettings from '../pages/owner/OwnerSettings.vue';
import OwnerVengerCarp from '../pages/owner/OwnerVengerCarp.vue';
import OwnerVengerFeedPlan from '../pages/owner/OwnerVengerFeedPlan.vue';
import OwnerVengerStaff from '../pages/owner/OwnerVengerStaff.vue';
import OwnerPlanner from '../pages/owner/OwnerPlanner.vue';
import OwnerExperiences from '../pages/owner/OwnerExperiences.vue';
import OwnerProekt from '../pages/owner/OwnerProekt.vue';
import OwnerClosureRequests from '../pages/owner/OwnerClosureRequests.vue';
import SupportContact from '../pages/SupportContact.vue';
import DirectorPonds from '../pages/director/DirectorPonds.vue';
import DirectorFisheryPonds from '../pages/director/DirectorFisheryPonds.vue';
import DirectorFishery from '../pages/director/DirectorFishery.vue';
import DirectorFinance from '../pages/director/DirectorFinance.vue';
import DirectorFinanceKirim from '../pages/director/DirectorFinanceKirim.vue';
import DirectorFinanceReports from '../pages/director/DirectorFinanceReports.vue';
import DirectorFinanceReport from '../pages/director/DirectorFinanceReport.vue';
import DirectorFinanceEntry from '../pages/director/DirectorFinanceEntry.vue';
import DirectorDailyReport from '../pages/director/DirectorDailyReport.vue';
import DirectorMainReport from '../pages/director/DirectorMainReport.vue';
import DirectorWaterAlert from '../pages/director/DirectorWaterAlert.vue';
import DirectorAddFish from '../pages/director/DirectorAddFish.vue';
import DirectorSaleLoss from '../pages/director/DirectorSaleLoss.vue';
import DirectorClosePond from '../pages/director/DirectorClosePond.vue';
import DirectorActivities from '../pages/director/DirectorActivities.vue';
import DirectorWarehouse from '../pages/director/DirectorWarehouse.vue';
import DirectorWarehouseIntake from '../pages/director/DirectorWarehouseIntake.vue';
import DirectorWarehouseStock from '../pages/director/DirectorWarehouseStock.vue';
import DirectorLegacyPondRedirect from '../pages/director/DirectorLegacyPondRedirect.vue';
import WorkerHome from '../pages/WorkerHome.vue';
import WorkerDailyReport from '../pages/worker/WorkerDailyReport.vue';
import WorkerMainReport from '../pages/worker/WorkerMainReport.vue';
import VengerCabinet from '../pages/venger/VengerCabinet.vue';
import VengerResultsHub from '../pages/venger/VengerResultsHub.vue';
import VengerPondsList from '../pages/venger/VengerPondsList.vue';
import VengerClientShow from '../pages/venger/VengerClientShow.vue';
import VengerResultForm from '../pages/venger/VengerResultForm.vue';
import VengerResultShow from '../pages/venger/VengerResultShow.vue';

const routes = [
    { path: '/', name: 'home', component: Home, meta: { scene: 'home', landing: true } },
    { path: '/asosiy-oyna', redirect: { name: 'home' } },
    { path: '/qollanma', name: 'site-guide', component: Guide, meta: { scene: 'home' } },
    { path: '/shartnoma', name: 'tarif-contract', component: TarifContract, meta: { scene: 'home', landing: true } },

    { path: '/kirish', name: 'login', component: PublicLogin, meta: { guest: true, scene: 'directorLogin' } },
    { path: '/direktor/kirish', name: 'director-login', redirect: { name: 'login' } },
    { path: '/direktor/royxat', redirect: { name: 'login' } },
    { path: '/hodim/kirish', name: 'worker-login', redirect: { name: 'login' } },
    { path: '/hodim/royxat', redirect: { name: 'login' } },

    { path: '/egasi/kirish', name: 'owner-login', redirect: { name: 'login' } },
    { path: '/venger/kirish', name: 'venger-login', redirect: { name: 'login' } },
    { path: '/venger', name: 'venger-cabinet', component: VengerCabinet, meta: { auth: true, venger: true } },
    { path: '/venger/natijalar', name: 'venger-results-hub', component: VengerResultsHub, meta: { auth: true, venger: true } },
    { path: '/venger/havzalar', name: 'venger-ponds', component: VengerPondsList, meta: { auth: true, venger: true } },
    { path: '/venger/mijoz/:clientId', name: 'venger-client', component: VengerClientShow, meta: { auth: true, venger: true } },
    { path: '/venger/mijoz/:clientId/natija', name: 'venger-result-new', component: VengerResultForm, meta: { auth: true, venger: true } },
    { path: '/venger/natija/:resultId', name: 'venger-result', component: VengerResultShow, meta: { auth: true, venger: true } },
    { path: '/venger/natija/:resultId/tahrir', name: 'venger-result-edit', component: VengerResultForm, meta: { auth: true, venger: true } },

    { path: '/egasi', name: 'owner-cabinet', component: OwnerCabinet, meta: { auth: true, owner: true } },
    { path: '/egasi/direktor/:directorId', name: 'owner-director-hub', component: OwnerDirectorHub, meta: { auth: true, owner: true, director: true, directorHub: true } },
    { path: '/egasi/kompaniyalar', name: 'owner-companies', component: OwnerCompanies, meta: { auth: true, owner: true } },
    { path: '/egasi/mijozlar', redirect: { name: 'owner-companies' } },
    { path: '/egasi/direktorlar', redirect: { name: 'owner-companies' } },
    { path: '/egasi/clients', redirect: { name: 'owner-companies' } },
    { path: '/egasi/hodimlar', redirect: { name: 'owner-venger-staff' } },
    { path: '/egasi/yordamchilar', redirect: { name: 'owner-venger-staff' } },
    { path: '/direktor/hodimlar', name: 'company-employees', component: CompanyEmployees, meta: { auth: true, director: true, permission: 'employees' } },
    { path: '/direktor/sozlamalar', name: 'director-settings', component: DirectorSettings, meta: { auth: true, director: true } },
    { path: '/direktor/profil', redirect: { name: 'director-settings' } },
    { path: '/direktor/jurnal', name: 'director-audit', component: DirectorAuditJournal, meta: { auth: true, director: true } },
    { path: '/direktor/rollar', name: 'company-roles', redirect: { name: 'company-employees' } },
    { path: '/egasi/arizalar', name: 'owner-closure-requests', component: OwnerClosureRequests, meta: { auth: true, owner: true } },
    // Ommaviy murojaat (landing). Kabinetlar o‘z yo‘liga ega — bitta /murojaat hammaga chiqmasin.
    { path: '/murojaat', name: 'support-contact', component: SupportContact, meta: { scene: 'home', support: true, publicSupport: true } },
    { path: '/egasi/murojaat', name: 'owner-support', component: SupportContact, meta: { auth: true, owner: true, support: true } },
    { path: '/venger/murojaat', name: 'venger-support', component: SupportContact, meta: { auth: true, venger: true, support: true } },
    { path: '/egasi/ratsion', redirect: { name: 'owner-cabinet' } },
    { path: '/egasi/ratsion/:pathMatch(.*)*', redirect: { name: 'owner-cabinet' } },
    { path: '/egasi/asosiy-oyna', redirect: { name: 'owner-cabinet' } },
    { path: '/egasi/asosiy-oyna/:pathMatch(.*)*', redirect: { name: 'owner-cabinet' } },
    { path: '/egasi/kundalik', name: 'owner-planner', component: OwnerPlanner, meta: { auth: true, owner: true, rootSuperadmin: true } },
    { path: '/egasi/proekt', name: 'owner-proekt', component: OwnerProekt, meta: { auth: true, owner: true, rootSuperadmin: true, scene: 'ownerPlanner' } },
    { path: '/egasi/tajriba', name: 'owner-experiences', component: OwnerExperiences, meta: { auth: true, owner: true, rootSuperadmin: true } },
    { path: '/egasi/tajriba/:folderId', name: 'owner-experience-folder', component: OwnerExperiences, meta: { auth: true, owner: true, rootSuperadmin: true } },
    { path: '/egasi/venger-carp', name: 'owner-venger', component: OwnerVengerCarp, meta: { auth: true, owner: true, rootSuperadmin: true } },
    { path: '/egasi/venger-carp/hodimlar', name: 'owner-venger-staff', component: OwnerVengerStaff, meta: { auth: true, owner: true, rootSuperadmin: true } },
    { path: '/egasi/venger-carp/yem-reja', name: 'owner-venger-feed-plan', component: OwnerVengerFeedPlan, meta: { auth: true, owner: true, rootSuperadmin: true } },
    { path: '/egasi/venger-carp/mijozlar', name: 'owner-venger-clients', component: VengerCabinet, meta: { auth: true, owner: true, rootSuperadmin: true } },
    { path: '/egasi/venger-carp/natijalar', name: 'owner-venger-ponds', component: VengerPondsList, meta: { auth: true, owner: true, rootSuperadmin: true } },
    { path: '/egasi/venger-carp/havzalar', redirect: { name: 'owner-venger-ponds' } },
    { path: '/egasi/venger-carp/mijoz/:clientId', name: 'owner-venger-client', component: VengerClientShow, meta: { auth: true, owner: true, rootSuperadmin: true } },
    { path: '/egasi/venger-carp/mijoz/:clientId/natija', name: 'owner-venger-result-new', component: VengerResultForm, meta: { auth: true, owner: true, rootSuperadmin: true } },
    { path: '/egasi/venger-carp/natija/:resultId', name: 'owner-venger-result', component: VengerResultShow, meta: { auth: true, owner: true, rootSuperadmin: true } },
    { path: '/egasi/venger-carp/natija/:resultId/tahrir', name: 'owner-venger-result-edit', component: VengerResultForm, meta: { auth: true, owner: true, rootSuperadmin: true } },
    { path: '/egasi/sozlamalar', name: 'owner-settings', component: OwnerSettings, meta: { auth: true, owner: true, rootSuperadmin: true } },
    { path: '/egasi/xavfsizlik', name: 'owner-security', redirect: { name: 'owner-settings' } },

    { path: '/direktor', name: 'director-home', component: DirectorPonds, meta: { auth: true, director: true, directorHub: true, scene: 'ponds', permissions: ['dashboard', 'farms'] } },
    { path: '/direktor/hojalik', name: 'director-farm-hub', redirect: { name: 'director-home' } },
    { path: '/direktor/baliqchilik', name: 'director-fishery-ponds', component: DirectorFisheryPonds, meta: { auth: true, director: true, directorHub: true, scene: 'ponds', permission: 'ponds' } },
    { path: '/direktor/moliya', name: 'director-finance', component: DirectorFinance, meta: { auth: true, director: true, directorHub: true, scene: 'finance', permissions: ['finance', 'finance_reports'] } },
    { path: '/direktor/moliya/xarajat', name: 'director-finance-kirim', component: DirectorFinanceKirim, meta: { auth: true, director: true, directorHub: true, scene: 'finance', permission: 'finance' } },
    { path: '/direktor/moliya/kirim', redirect: { name: 'director-finance-kirim' } },
    { path: '/direktor/moliya/hisobot', name: 'director-finance-overview', component: DirectorFinanceReports, meta: { auth: true, director: true, directorHub: true, scene: 'finance', permission: 'finance_reports' } },
    { path: '/direktor/moliya/hisobot/yopilgan', name: 'director-finance-closed', redirect: { name: 'director-finance-overview', query: { tab: 'closed' } } },
    { path: '/direktor/moliya/hisobot/:category', name: 'director-finance-report', component: DirectorFinanceReport, meta: { auth: true, director: true, directorHub: true, scene: 'finance', permission: 'finance_reports' } },
    { path: '/direktor/moliya/xisobot/:category', redirect: (to) => ({ name: 'director-finance-report', params: { category: to.params.category } }) },
    {
      path: '/direktor/moliya/kiritish/:category',
      name: 'director-finance-entry',
      component: DirectorFinanceEntry,
      meta: { auth: true, director: true, directorHub: true, scene: 'finance', permission: 'finance' },
      beforeEnter: (to) => {
        const cat = String(to.params.category || '');
        if (['feed', 'medicine', 'equipment'].includes(cat)) {
          return { name: 'director-warehouse-intake' };
        }
        return true;
      },
    },
    {
      path: '/direktor/moliya/xarajat/:category',
      redirect: (to) => {
        const cat = String(to.params.category || '');
        if (['feed', 'medicine', 'equipment'].includes(cat)) {
          return { name: 'director-warehouse-intake' };
        }
        return { name: 'director-finance-entry', params: { category: to.params.category } };
      },
    },
    { path: '/direktor/ombor', name: 'director-warehouse', component: DirectorWarehouse, meta: { auth: true, director: true, directorHub: true, permission: 'warehouse' } },
    { path: '/direktor/ombor/kirim', name: 'director-warehouse-intake', component: DirectorWarehouseIntake, meta: { auth: true, director: true, permission: 'warehouse' } },
    { path: '/direktor/ombor/zaxira', name: 'director-warehouse-stock', component: DirectorWarehouseStock, meta: { auth: true, director: true, permission: 'warehouse' } },
    { path: '/direktor/murojaat', name: 'director-support', component: SupportContact, meta: { auth: true, director: true, support: true } },
    { path: '/direktor/boss-xabar', name: 'director-boss-chat', redirect: { name: 'director-support' } },

    // Eski ichma-ich URL → yangi flat bo‘limlar
    {
        path: '/direktor/hojalik/:farmId',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-home' };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/baliqchilik',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-fishery-ponds' };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/moliya',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-finance' };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/moliya/xarajat',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-finance-kirim' };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/moliya/kirim',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-finance-kirim' };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/moliya/hisobot',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-finance-overview' };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/moliya/xisobot/:category',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-finance-report', params: { category: to.params.category } };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/moliya/kiritish/:category',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-finance-entry', params: { category: to.params.category } };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/moliya/xarajat/:category',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-finance-entry', params: { category: to.params.category } };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/ombor',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-warehouse' };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/ombor/kirim',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-warehouse-intake' };
        },
    },
    {
        path: '/direktor/hojalik/:farmId/ombor/zaxira',
        redirect: (to) => {
            setDirectorFarmId(to.params.farmId);
            return { name: 'director-warehouse-stock' };
        },
    },

    { path: '/direktor/hovuz/:pondId/baliqchilik', name: 'director-fishery', component: DirectorFishery, meta: { auth: true, director: true, directorHub: true, scene: 'fishery', permission: 'ponds' } },
    { path: '/direktor/hovuz/:pondId/kunlik-hisobot', name: 'director-daily', component: DirectorDailyReport, meta: { auth: true, director: true, permission: 'daily_reports' } },
    { path: '/direktor/hovuz/:pondId/asosiy-hisobot', name: 'director-main-report', component: DirectorMainReport, meta: { auth: true, director: true, permission: 'main_reports' } },
    { path: '/direktor/hovuz/:pondId/suv-ogohlantirish/:reportId', name: 'director-water-alert', component: DirectorWaterAlert, meta: { auth: true, director: true, permission: 'main_reports' } },
    { path: '/direktor/hovuz/:pondId/baliq-qoshish', name: 'director-add-fish', component: DirectorAddFish, meta: { auth: true, director: true, permission: 'fish_ops' } },
    { path: '/direktor/hovuz/:pondId/sotish-poterya', name: 'director-sale-loss', component: DirectorSaleLoss, meta: { auth: true, director: true, permission: 'fish_ops' } },
    { path: '/direktor/hovuz/:pondId/havzani-yopish', name: 'director-close-pond', component: DirectorClosePond, meta: { auth: true, director: true, permission: 'pond_close' } },
    { path: '/direktor/hovuz/:pondId/hisobotlar', name: 'director-activities', component: DirectorActivities, meta: { auth: true, director: true, permission: 'activities' } },

    { path: '/direktor/havza/:pondId', name: 'director-legacy-pond', component: DirectorLegacyPondRedirect, meta: { auth: true, director: true } },
    { path: '/direktor/havza/:pondId/baliqchilik', redirect: (to) => ({ name: 'director-fishery', params: { pondId: to.params.pondId } }) },
    { path: '/direktor/havza/:pondId/moliya', component: DirectorLegacyPondRedirect, meta: { auth: true, director: true } },
    { path: '/direktor/havza/:pondId/ombor', component: DirectorLegacyPondRedirect, meta: { auth: true, director: true } },
    { path: '/direktor/havza/:pondId/ombor/kirim', component: DirectorLegacyPondRedirect, meta: { auth: true, director: true } },
    { path: '/direktor/havza/:pondId/ombor/zaxira', component: DirectorLegacyPondRedirect, meta: { auth: true, director: true } },
    { path: '/direktor/havza/:pondId/kunlik-hisobot', redirect: (to) => ({ name: 'director-daily', params: { pondId: to.params.pondId } }) },
    { path: '/direktor/havza/:pondId/asosiy-hisobot', redirect: (to) => ({ name: 'director-main-report', params: { pondId: to.params.pondId } }) },
    { path: '/direktor/havza/:pondId/baliq-qoshish', redirect: (to) => ({ name: 'director-add-fish', params: { pondId: to.params.pondId } }) },
    { path: '/direktor/havza/:pondId/sotish-poterya', redirect: (to) => ({ name: 'director-sale-loss', params: { pondId: to.params.pondId } }) },
    { path: '/direktor/havza/:pondId/hisobotlar', redirect: (to) => ({ name: 'director-activities', params: { pondId: to.params.pondId } }) },

    { path: '/hodim', name: 'worker-home', component: WorkerHome, meta: { auth: true, worker: true } },
    { path: '/hodim/kunlik-hisobot', name: 'worker-daily', component: WorkerDailyReport, meta: { auth: true, worker: true } },
    { path: '/hodim/asosiy-hisobot', name: 'worker-main-report', component: WorkerMainReport, meta: { auth: true, worker: true } },
    { path: '/hodim/murojaat', name: 'worker-support', component: SupportContact, meta: { auth: true, worker: true, support: true } },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to, from, next) => {
    if (auth.hasValidSession() && !auth.state.user) {
        await auth.fetchUser();
    }

    // Landing (`/` / bossfish.uz) always stays public — even when logged in.
    // Kabinetga kirish alohida login / sidebar orqali.

    if (to.meta.auth && !auth.isAuthenticated()) {
        if (auth.hasValidSession()) {
            auth.clearAuth();
        }
        return next({ name: 'login' });
    }

    const neededPerms = to.meta.permissions
        || (to.meta.permission ? [to.meta.permission] : null);
    if (neededPerms && !auth.canAny(neededPerms)) {
        return next(auth.homeForRole());
    }

    if (to.meta.guest && auth.isAuthenticated()) {
        // Boshqa rol loginiga kirmasin — o‘z kabinetiga
        return next(auth.homeForRole());
    }

    // Ommaviy /murojaat — tizimga kirgan foydalanuvchini o‘z kabinet murojaatiga yo‘naltirish
    if (to.name === 'support-contact' && auth.isAuthenticated()) {
        if (auth.isOwner()) return next({ name: 'owner-support' });
        if (auth.isVenger()) return next({ name: 'venger-support' });
        if (auth.isDirector() || auth.isEmployee()) return next({ name: 'director-support' });
        if (auth.isWorker()) return next({ name: 'worker-support' });
    }

    if (to.meta.guest && auth.hasValidSession() && !auth.state.user) {
        await auth.fetchUser();
        if (auth.isAuthenticated()) {
            return next(auth.homeForRole());
        }
        // fetchUser muvaffaqiyatsiz — toza login formasi
    }

    if (to.meta.owner && !auth.isOwner()) {
        return next({ name: 'home' });
    }

    if (to.meta.rootSuperadmin && !auth.isSuperadmin()) {
        return next({ name: 'owner-cabinet' });
    }

    if (to.meta.venger && !auth.isVenger()) {
        return next({ name: 'home' });
    }

    if (to.meta.director && !auth.isDirector() && !auth.isOwner() && !auth.isEmployee()) {
        return next({ name: 'home' });
    }

    // Profil va jurnal — faqat kompaniya admini (egasi BOSS profilini o‘zgartirmasin)
    if (['director-settings', 'director-audit'].includes(String(to.name || '')) && !auth.isCompanyAdmin()) {
        return next(auth.homeForRole());
    }

    // Egasi odatda /direktor ga kirmaydi; lekin «direktor sifatida» rejimida ruxsat.
    if (auth.isOwner() && to.name === 'director-home' && !getActingDirectorId()) {
        return next({ name: 'owner-cabinet' });
    }

    // Legacy /hodim — ruxsatsiz API o‘rniga direktor marshrutlariga (job role bilan)
    if (to.meta.worker) {
        if (!auth.isWorker()) {
            return next({ name: 'home' });
        }
        return next(auth.employeeHome());
    }

    next();
});

export default router;
