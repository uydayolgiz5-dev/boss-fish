<template>
  <div
    class="app-root"
    :class="{
      'with-owner-nav': showSideNav,
      'crm-app': isCrmApp,
    }"
  >
    <LanguageSwitcher v-if="!isLandingHome && !isAuthLogin && !showSideNav" />

    <aside v-if="showSideNav" class="owner-sidebar" :aria-label="$t('system')">
      <div class="sidebar-top">
        <div class="brand brand--logo-only">
          <VengerCarpLogo v-if="isVengerArea" />
          <BrandLogo v-else :variant="logoVariant" compact />
        </div>
        <div class="sidebar-top__end">
          <CrmTopbar
            v-if="showSideNav && isMobileNav"
            class="crm-topbar--mobile-chrome"
            :title="sideNavTitle"
            :subtitle="sideNavSubtitle"
            :show-director-tools="isDirectorArea"
            :show-settings="isCompanyAdminUser && isDirectorArea"
          />
          <button
            class="btn btn-outline btn-sm sidebar-logout-mobile"
            type="button"
            :title="$t('logout')"
            @click="logout"
          >
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
              <path d="M16 17l5-5-5-5" />
              <path d="M21 12H9" />
            </svg>
            <span class="sidebar-logout-mobile__label">{{ $t('logout') }}</span>
          </button>
        </div>
      </div>

      <nav v-if="isOwnerArea">
        <router-link :to="{ name: 'owner-cabinet' }" :title="$t('dashboard')">
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
          </span>
          <span class="nav-label">{{ $t('dashboard') }}</span>
        </router-link>
        <router-link v-if="isRootSuperadmin" :to="{ name: 'owner-planner' }" :title="$t('ownerPlannerNav')">
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          </span>
          <span class="nav-label">{{ $t('ownerPlannerNav') }}</span>
        </router-link>
        <router-link :to="{ name: 'owner-companies' }" :title="$t('companies')">
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </span>
          <span class="nav-label">{{ $t('companies') }}</span>
        </router-link>
        <router-link v-if="isRootSuperadmin" :to="{ name: 'owner-proekt' }" :title="$t('ownerProektNav')">
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"/><path d="M7 7h.01M17 7h.01M17 17h.01M7 17h.01"/></svg>
          </span>
          <span class="nav-label">{{ $t('ownerProektNav') }}</span>
        </router-link>
        <router-link v-if="isRootSuperadmin" :to="{ name: 'owner-experiences' }" :title="$t('ownerExperienceNav')">
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><path d="M8 7h8M8 11h6"/></svg>
          </span>
          <span class="nav-label">{{ $t('ownerExperienceNav') }}</span>
        </router-link>
        <router-link
          :to="{ name: 'owner-closure-requests' }"
          :title="ownerPendingBadge
            ? $t('ownerPendingRequestsAria', { n: ownerPendingRequests })
            : $t('closureRequests')"
        >
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            <span v-if="ownerPendingBadge" class="nav-count-badge">{{ ownerPendingBadge }}</span>
          </span>
          <span class="nav-label">{{ $t('closureRequests') }}</span>
        </router-link>
        <router-link class="owner-nav-support" :to="{ name: 'owner-support' }" :title="$t('supportTitle')">
          <span class="nav-icon owner-nav-support__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z" />
            </svg>
          </span>
          <span class="nav-label">{{ $t('supportTitle') }}</span>
        </router-link>
        <router-link v-if="isRootSuperadmin" :to="{ name: 'owner-venger' }" :title="$t('ownerVengerSection')">
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12c0-2.2 1.8-4 4-4"/><circle cx="15" cy="10" r="1" fill="currentColor" stroke="none"/></svg>
          </span>
          <span class="nav-label">{{ $t('ownerVengerSection') }}</span>
        </router-link>
        <router-link v-if="isRootSuperadmin" :to="{ name: 'owner-settings' }" :title="$t('settings')">
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          </span>
          <span class="nav-label">{{ $t('settings') }}</span>
        </router-link>
      </nav>

      <nav v-else-if="isVengerArea">
        <router-link :to="{ name: 'venger-cabinet' }" :title="$t('vengerClientsTitle')">
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </span>
          <span class="nav-label">{{ $t('vengerClientsTitle') }}</span>
        </router-link>
        <router-link :to="{ name: 'venger-results-hub' }" :title="$t('vengerResultsHubTitle')">
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
          </span>
          <span class="nav-label">{{ $t('vengerResultsHubTitle') }}</span>
        </router-link>
        <router-link :to="{ name: 'venger-ponds' }" :title="$t('vengerPondsTitle')">
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12c2-3 4-4.5 6-4.5s4 1.5 6 4.5c2 3 4 4.5 6 4.5"/><path d="M2 17c2-3 4-4.5 6-4.5s4 1.5 6 4.5c2 3 4 4.5 6 4.5"/></svg>
          </span>
          <span class="nav-label">{{ $t('vengerPondsTitle') }}</span>
        </router-link>
        <router-link class="owner-nav-support" :to="{ name: 'venger-support' }" :title="$t('supportTitle')">
          <span class="nav-icon owner-nav-support__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 22 16.92z" />
            </svg>
          </span>
          <span class="nav-label">{{ $t('supportTitle') }}</span>
        </router-link>
      </nav>

      <nav v-else-if="isDirectorArea">
        <router-link
          v-if="canSection('dashboard') || canSection('farms')"
          :to="{ name: 'director-home' }"
          :title="$t('dashboard')"
        >
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
          </span>
          <span class="nav-label">{{ $t('dashboard') }}</span>
        </router-link>
        <router-link v-if="canSection('ponds')" :to="{ name: 'director-fishery-ponds' }" :title="$t('fishery')">
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12c2-3 4-4.5 6-4.5s4 1.5 6 4.5c2 3 4 4.5 6 4.5"/><path d="M2 17c2-3 4-4.5 6-4.5s4 1.5 6 4.5c2 3 4 4.5 6 4.5"/></svg>
          </span>
          <span class="nav-label">{{ $t('fishery') }}</span>
        </router-link>
        <router-link v-if="canSection('warehouse')" :to="{ name: 'director-warehouse' }" :title="$t('warehouse')">
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96 12 12.01l8.73-5.05M12 22.08V12"/></svg>
          </span>
          <span class="nav-label">{{ $t('warehouse') }}</span>
        </router-link>
        <router-link
          v-if="canSection('finance') || canSection('finance_reports')"
          :to="{ name: financeNavTarget }"
          :title="$t('finance')"
        >
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </span>
          <span class="nav-label">{{ $t('finance') }}</span>
        </router-link>
        <router-link v-if="canSection('employees')" :to="{ name: 'company-employees' }" :title="$t('companyEmployees')">
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </span>
          <span class="nav-label">{{ $t('companyEmployees') }}</span>
        </router-link>
        <router-link class="owner-nav-support" :to="{ name: 'director-support' }" :title="$t('supportTitle')">
          <span class="nav-icon owner-nav-support__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 22 16.92z" />
            </svg>
          </span>
          <span class="nav-label">{{ $t('supportTitle') }}</span>
        </router-link>
      </nav>

      <nav v-else-if="isWorkerArea">
        <router-link :to="{ name: 'worker-home' }" :title="$t('dashboard')">
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
          </span>
          <span class="nav-label">{{ $t('dashboard') }}</span>
        </router-link>
        <router-link :to="{ name: 'worker-daily' }" :title="$t('dailyReport')">
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          </span>
          <span class="nav-label">{{ $t('dailyReport') }}</span>
        </router-link>
        <router-link :to="{ name: 'worker-main-report' }" :title="$t('mainReport')">
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
          </span>
          <span class="nav-label">{{ $t('mainReport') }}</span>
        </router-link>
        <router-link class="owner-nav-support" :to="{ name: 'worker-support' }" :title="$t('supportTitle')">
          <span class="nav-icon owner-nav-support__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 22 16.92z" />
            </svg>
          </span>
          <span class="nav-label">{{ $t('supportTitle') }}</span>
        </router-link>
      </nav>

      <div class="sidebar-foot" v-if="user">
        <div class="user-chip">
          <strong>{{ user.name }}</strong>
          <span>{{ roleLabel }}</span>
        </div>
        <div class="sidebar-foot__actions">
          <router-link
            v-if="isCompanyAdminUser && isDirectorArea"
            class="btn btn-outline btn-sm sidebar-settings-desktop"
            :to="{ name: 'director-settings' }"
            :title="$t('settings')"
          >
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="12" r="3" />
              <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
            </svg>
            <span>{{ $t('settings') }}</span>
          </router-link>
          <button class="btn btn-outline btn-sm sidebar-logout-desktop" type="button" :title="$t('logout')" @click="logout">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
              <path d="M16 17l5-5-5-5" />
              <path d="M21 12H9" />
            </svg>
            <span>{{ $t('logout') }}</span>
          </button>
        </div>
      </div>
    </aside>

    <main :class="['main', { 'main-full': !showSideNav || isLandingHome, 'main-landing': isLandingHome, 'main--with-topbar': showSideNav, 'main--chat-dm': isDirectorChat }]">
      <CrmTopbar
        v-if="showSideNav && !isMobileNav"
        :title="sideNavTitle"
        :subtitle="sideNavSubtitle"
        :show-director-tools="isDirectorArea"
        :show-settings="isCompanyAdminUser && isDirectorArea"
      />
      <div :class="{ 'owner-portal-inner': showSideNav }">
        <router-view :key="viewKey" />
      </div>
    </main>

    <SupportFab v-if="!showSideNav && !isAuthLogin" />
  </div>
</template>

<script>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import auth from './auth';
import { i18nState, t } from './i18n';
import LanguageSwitcher from './components/LanguageSwitcher.vue';
import BrandLogo from './components/BrandLogo.vue';
import VengerCarpLogo from './components/VengerCarpLogo.vue';
import SupportFab from './components/SupportFab.vue';
import CrmTopbar from './components/CrmTopbar.vue';
import { getDirectorFarmId, setDirectorFarmId, useDirectorFarmId } from './utils/directorFarm';
import { getActingDirector, getActingDirectorId } from './utils/ownerContext';
import { themeState } from './utils/theme';
import { installMobileKeyboard } from './utils/mobileKeyboard';
import {
  ownerPendingRequestBadge,
  ownerPendingRequestCount,
  startOwnerRequestInbox,
  stopOwnerRequestInbox,
} from './utils/ownerRequestInbox';

export default {
  components: { LanguageSwitcher, BrandLogo, VengerCarpLogo, SupportFab, CrmTopbar },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const isMobileNav = ref(false);
    let mobileMq = null;
    let stopMobileKeyboard = null;

    function syncMobileNav() {
      isMobileNav.value = !!(mobileMq && mobileMq.matches);
    }

    function scrollActiveTabIntoView() {
      if (!isMobileNav.value) return;
      nextTick(() => {
        const active = document.querySelector('.owner-sidebar nav a.router-link-active');
        if (active && typeof active.scrollIntoView === 'function') {
          active.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
        }
      });
    }

    watch(() => route.fullPath, scrollActiveTabIntoView);
    const user = computed(() => auth.state.user);
    const isRootSuperadmin = computed(() => auth.isSuperadmin());
    const isOwnerActingDirector = computed(() => auth.isOwner() && !!getActingDirectorId());
    // Egasi «direktor sifatida» — direktor menyusi (owner-* sahifalaridan tashqari)
    const isDirectorArea = computed(() => {
      const onDirectorUi = !!route.meta.director
        || ['company-employees', 'company-roles', 'director-support'].includes(String(route.name || ''));
      if (!onDirectorUi) return false;
      if (auth.isDirector() || auth.isEmployee()) return true;
      if (isOwnerActingDirector.value && !String(route.name || '').startsWith('owner-')) return true;
      return false;
    });
    function canSection(section) {
      return auth.can(section);
    }
    const isCompanyAdminUser = computed(() => auth.isCompanyAdmin());
    const financeNavTarget = computed(() => {
      if (auth.can('finance') && auth.can('finance_reports')) return 'director-finance';
      if (auth.can('finance')) return 'director-finance-kirim';
      if (auth.can('finance_reports')) return 'director-finance-overview';
      return 'director-finance';
    });
    const isOwnerArea = computed(() => {
      if (!auth.isOwner()) return false;
      if (isDirectorArea.value) return false;
      return !!(route.meta.owner || route.meta.director);
    });
    const isVengerArea = computed(() => auth.isVenger() && !!route.meta.venger);
    const isWorkerArea = computed(() => auth.isWorker() && !!route.meta.worker);
    const showSideNav = computed(() => (
      isOwnerArea.value || isVengerArea.value || isDirectorArea.value || isWorkerArea.value
    ));
    const isLandingHome = computed(() => route.name === 'home' || route.name === 'tarif-contract');
    const isAuthLogin = computed(() => !!route.meta.guest && String(route.name || '').includes('login'));
    const isDirectorChat = computed(() => String(route.name || '') === 'director-support');
    // Landingdan tashqari hammasi — jiddiy minimal CRM
    const isCrmApp = computed(() => !isLandingHome.value);
    const locale = computed(() => i18nState.locale);
    const viewKey = computed(() => `${locale.value}|${String(route.name || '')}|${JSON.stringify(route.params || {})}`);
    const directorFarmId = useDirectorFarmId();

    async function syncDirectorFarmId() {
      // Direktor, hodim va BOSS (acting) bir xil farm kontekstida ishlaydi
      if (!auth.isDirector() && !auth.isEmployee() && !(auth.isOwner() && getActingDirectorId())) return;
      try {
        const { data } = await window.axios.get('/director/farms');
        const list = Array.isArray(data) ? data : [];
        const stored = getDirectorFarmId();
        const next = (stored && list.some((f) => String(f.id) === stored) ? stored : null)
          || (list[0] ? String(list[0].id) : null);
        setDirectorFarmId(next);
      } catch (e) {
        /* ignore */
      }
    }

    watch(isDirectorArea, (active) => {
      if (active) syncDirectorFarmId();
    }, { immediate: true });

    watch(isOwnerArea, (active) => {
      if (active) startOwnerRequestInbox();
      else stopOwnerRequestInbox();
    }, { immediate: true });

    onMounted(() => {
      if (typeof window !== 'undefined' && window.matchMedia) {
        mobileMq = window.matchMedia('(max-width: 900px)');
        syncMobileNav();
        if (mobileMq.addEventListener) mobileMq.addEventListener('change', syncMobileNav);
        else if (mobileMq.addListener) mobileMq.addListener(syncMobileNav);
      }
      if (isDirectorArea.value) syncDirectorFarmId();
      scrollActiveTabIntoView();
      stopMobileKeyboard = installMobileKeyboard();
    });

    onBeforeUnmount(() => {
      stopOwnerRequestInbox();
      if (typeof stopMobileKeyboard === 'function') stopMobileKeyboard();
      if (!mobileMq) return;
      if (mobileMq.removeEventListener) mobileMq.removeEventListener('change', syncMobileNav);
      else if (mobileMq.removeListener) mobileMq.removeListener(syncMobileNav);
    });
    const logoVariant = computed(() => (themeState.theme === 'dark' ? 'dark' : 'light'));

    const sideNavTitle = computed(() => {
      if (isVengerArea.value) return t('vengerCabinet');
      if (isDirectorArea.value) {
        if (isOwnerActingDirector.value) {
          const acting = getActingDirector();
          return acting?.name || t('directorCabinet');
        }
        return user.value?.name || t('directorCabinet');
      }
      if (isWorkerArea.value) return user.value?.name || t('workerCabinet');
      return t('ownerCabinet');
    });
    const sideNavSubtitle = computed(() => {
      if (isVengerArea.value) return t('vengerCabinetHint');
      if (isDirectorArea.value) {
        return auth.isEmployee() ? t('worker') : t('director');
      }
      if (isWorkerArea.value) return t('worker');
      if (auth.isPlatformAdmin()) return t('platformAdminHint');
      return t('fullControl');
    });
    const roleLabel = computed(() => {
      if (isVengerArea.value) return t('vengerStaff');
      if (isDirectorArea.value) {
        return auth.isEmployee() ? t('worker') : t('director');
      }
      if (isWorkerArea.value) return t('worker');
      if (auth.isPlatformAdmin()) return t('platformAdmin');
      return t('owner');
    });

    try {
      localStorage.removeItem('crm_sidebar_collapsed');
    } catch (e) { /* ignore */ }

    async function logout() {
      const role = auth.state.user?.role;
      try {
        await window.axios.post('/auth/logout');
      } catch (e) { /* ignore */ }
      auth.clearAuth();
      router.push(auth.loginRouteForRole(role));
    }

    return {
      user,
      canSection,
      isCompanyAdminUser,
      financeNavTarget,
      isRootSuperadmin,
      isOwnerArea,
      isVengerArea,
      isDirectorArea,
      isWorkerArea,
      showSideNav,
      isMobileNav,
      directorFarmId,
      isCrmApp,
      isLandingHome,
      isAuthLogin,
      isDirectorChat,
      locale,
      viewKey,
      logoVariant,
      sideNavTitle,
      sideNavSubtitle,
      roleLabel,
      ownerPendingRequests: ownerPendingRequestCount,
      ownerPendingBadge: ownerPendingRequestBadge,
      logout,
    };
  },
};
</script>
