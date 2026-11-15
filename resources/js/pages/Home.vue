<template>
  <div class="landing-page landing-page--clean">
    <header class="landing-site-header">
      <div class="landing-site-header__top">
        <a class="landing-site-header__phone" :href="`tel:${$t('homeContactPhone').replace(/\s+/g, '')}`">
          {{ $t('homeContactPhone') }}
        </a>
        <a href="#kabinet" class="landing-site-header__logo" aria-label="BOSS FISH" @click.prevent="goToSection('kabinet')">
          <BrandLogo variant="light" compact />
        </a>
        <div class="landing-site-header__actions">
          <router-link
            class="landing-site-header__cabinet"
            :to="authLink"
          >
            {{ auth.isAuthenticated() ? $t('siteHomeCabinet') : $t('login') }}
          </router-link>
          <LanguageSwitcher embedded />
        </div>
      </div>
      <nav class="landing-site-nav" :aria-label="$t('homeSectionsNavLabel')">
        <a
          v-for="item in mainNav"
          :key="item.id"
          :href="`#${item.id}`"
          class="landing-site-nav__link"
          :class="{ 'landing-site-nav__link--active': activeNav === item.id }"
          @click.prevent="goToSection(item.id)"
        >
          {{ $t(item.labelKey) }}
        </a>
      </nav>
    </header>

    <!-- 1. Hero -->
    <section id="kabinet" class="landing-band landing-band--white">
      <div class="landing-band__inner">
        <header class="landing-hero landing-hero--clean">
          <h1 class="landing-hero-brand">
            <BrandLogo variant="light" class="landing-logo landing-logo--hero" />
            <span class="visually-hidden">BOSS FISH</span>
          </h1>
          <p class="landing-tagline">{{ $t('siteTagline') }}</p>
        </header>
      </div>
    </section>

    <!-- 2. BOSS FISH BBT -->
    <section id="boss-fish" class="landing-band landing-band--muted" aria-labelledby="landing-bbt-title">
      <div class="landing-band__inner">
        <div class="landing-bbt">
          <header class="landing-bbt__head">
            <h2 id="landing-bbt-title">{{ $t('homeBbtTitle') }}</h2>
            <p>{{ $t('homeBbtLead') }}</p>
          </header>

          <div class="landing-bbt__layout">
            <div class="landing-bbt__cats" role="list">
              <button
                v-for="mod in bbtModules"
                :key="mod.id"
                type="button"
                role="listitem"
                class="landing-bbt-card"
                :class="{ 'landing-bbt-card--active': activeBbtId === mod.id }"
                @click="activeBbtId = mod.id"
              >
                <span class="landing-bbt-card__icon" aria-hidden="true" v-html="mod.icon"></span>
                <span class="landing-bbt-card__text">
                  <strong>{{ $t(mod.titleKey) }}</strong>
                  <small>{{ $t(mod.subtitleKey) }}</small>
                </span>
              </button>
            </div>

            <div class="landing-bbt__preview">
              <img
                :src="activeBbt.image"
                :alt="$t(activeBbt.titleKey)"
                loading="lazy"
              />
              <div class="landing-bbt__caption">
                <strong>{{ $t(activeBbt.titleKey) }}</strong>
                <small>{{ $t(activeBbt.subtitleKey) }}</small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 3. Hamkorliklar -->
    <section id="hamkorliklar" class="landing-band landing-band--white" aria-labelledby="landing-partners-title">
      <div class="landing-band__inner landing-partners-band">
        <header class="landing-partners-band__head">
          <h2 id="landing-partners-title">{{ $t('homePartnersSectionTitle') }}</h2>
          <p>{{ $t('homePartnersSectionLead') }}</p>
        </header>

        <div class="landing-partners-band__logos" :aria-label="$t('homePartners')">
          <div
            v-for="partner in landingPartners"
            :key="partner.name"
            class="landing-partners-band__logo"
          >
            <img :src="partner.logo" :alt="partner.name" />
          </div>
        </div>

        <div class="landing-partners-band__story">
          <article>
            <span class="landing-about-year">2023</span>
            <h3>{{ $t('homeAboutPartnershipTitle') }}</h3>
            <p>{{ $t('homeAboutPartnershipText') }}</p>
            <div class="landing-about-tags">
              <span>{{ $t('homeAboutTagFeed') }}</span>
              <span>{{ $t('homeAboutTagTechnology') }}</span>
              <span>{{ $t('homeAboutTagExperience') }}</span>
            </div>
          </article>
          <div class="landing-partners-band__photos">
            <img
              v-for="photo in partnershipPhotos.slice(0, 3)"
              :key="photo.src"
              :src="photo.src"
              :alt="$t(photo.altKey)"
              loading="lazy"
            />
          </div>
        </div>
      </div>
    </section>

    <!-- 4. Mahsulotlar (ommaviy katalog) -->
    <section id="mahsulotlar" class="landing-catalog" :aria-label="$t('homeNavProducts')">
      <section
        id="landing-products"
        class="landing-products"
        aria-labelledby="landing-products-title"
      >
        <header class="landing-products-head">
          <div>
            <span class="landing-products-kicker">{{ $t('homeProductsKicker') }}</span>
            <h3 id="landing-products-title">{{ $t('homeProductsTitle') }}</h3>
            <p>{{ $t('homeProductsLead') }}</p>
          </div>
          <div v-if="catalogFeeds.length > 1" class="landing-products-controls landing-products-controls--desktop">
            <button type="button" :aria-label="$t('homeProductsPrevious')" @click="scrollProducts(-1)">
              <svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2L4 6l4 4"/></svg>
            </button>
            <button type="button" :aria-label="$t('homeProductsNext')" @click="scrollProducts(1)">
              <svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 2l4 4-4 4"/></svg>
            </button>
          </div>
        </header>

        <div v-if="catalogLoading" class="landing-products-track">
          <article class="landing-medicine-empty">
            <div class="landing-medicine-empty-mark" aria-hidden="true"><span /></div>
            <div>
              <span class="landing-product-category">{{ $t('homeProductsKicker') }}</span>
              <h4>{{ $t('loading') }}</h4>
            </div>
          </article>
        </div>
        <div v-else-if="!catalogFeeds.length" class="landing-products-track">
          <article class="landing-medicine-empty">
            <div class="landing-medicine-empty-mark" aria-hidden="true"><span /></div>
            <div>
              <span class="landing-product-category">{{ $t('homeProductsKicker') }}</span>
              <h4>{{ $t('homeFeedsEmptyTitle') }}</h4>
              <p>{{ $t('homeFeedsEmptyText') }}</p>
            </div>
          </article>
        </div>
        <div v-else ref="productTrack" class="landing-products-track">
          <article
            v-for="(product, index) in catalogFeeds"
            :key="product.id"
            class="landing-product-card"
            :class="`landing-product-card--${product.theme || 'red'}`"
          >
            <div class="landing-product-visual">
              <span class="landing-product-number">{{ String(index + 1).padStart(2, '0') }}</span>
              <img v-if="product.image" :src="product.image" :alt="product.name" loading="lazy" />
            </div>
            <div class="landing-product-info">
              <div class="landing-product-meta">
                <span class="landing-product-category">{{ $t('homeProductCategoryFeed') }}</span>
                <strong class="landing-product-price">{{ product.price_label }}</strong>
              </div>
              <h4>{{ product.name }}</h4>
              <p v-if="product.slogan" class="landing-product-slogan">{{ product.slogan }}</p>
              <div v-if="product.specs?.length" class="landing-product-specs">
                <span v-for="spec in product.specs" :key="spec">{{ spec }}</span>
              </div>
              <p v-if="product.usage_text" class="landing-product-usage">{{ product.usage_text }}</p>
              <p v-if="product.description">{{ product.description }}</p>
            </div>
          </article>
        </div>
      </section>
    </section>

    <!-- 5. Tariflar -->
    <section id="narxlar" class="landing-band landing-band--muted" aria-labelledby="landing-pricing-title">
      <div class="landing-band__inner landing-pricing">
        <header class="landing-pricing__head">
          <h2 id="landing-pricing-title">{{ $t('homePricingTitle') }}</h2>
          <p>{{ $t('homePricingLead') }}</p>
        </header>

        <div class="landing-pricing__grid">
          <article
            v-for="plan in pricingPlans"
            :key="plan.id"
            class="landing-price-card"
            :class="{ 'landing-price-card--popular': plan.popular }"
          >
            <span v-if="plan.popular" class="landing-price-card__ribbon" aria-hidden="false">
              <span class="landing-price-card__ribbon-text">{{ $t('homePricingPopular') }}</span>
            </span>
            <h3>{{ $t(plan.nameKey) }}</h3>
            <p class="landing-price-card__summary">{{ $t(plan.summaryKey) }}</p>
            <div class="landing-price-card__price">
              <template v-if="plan.monthPriceKey">
                <strong>{{ $t(plan.monthPriceKey) }}</strong>
                <small>{{ $t('homePricingPerMonth') }}</small>
                <p class="landing-price-card__year">
                  <span>{{ $t('homePricingYearPay') }}</span>
                  <b>{{ $t(plan.priceKey) }}</b>
                  <em>{{ $t('homePricingYearSave') }}</em>
                </p>
              </template>
              <template v-else>
                <strong>{{ $t(plan.priceKey) }}</strong>
              </template>
            </div>
            <ul>
              <li v-for="featureKey in plan.featureKeys" :key="featureKey">
                {{ $t(featureKey) }}
              </li>
            </ul>
            <a
              class="landing-price-card__cta"
              :href="`tel:${$t('homeContactPhone').replace(/\s+/g, '')}`"
            >
              {{ $t(plan.ctaKey) }}
            </a>
          </article>
        </div>
      </div>
    </section>

    <!-- 6. Yakuniy shior (Alfapos uslubi) -->
    <section id="shior" class="landing-band landing-band--white" aria-labelledby="landing-closer-title">
      <div class="landing-band__inner landing-closer">
        <div class="landing-closer__copy">
          <h2 id="landing-closer-title">{{ $t('homeCloserTitle') }}</h2>
          <p class="landing-closer__tagline">{{ $t('homeCloserTagline') }}</p>
          <a
            class="landing-closer__cta"
            :href="`tel:${$t('homeContactPhone').replace(/\s+/g, '')}`"
          >
            {{ $t('homeContactPhone') }}
          </a>
        </div>
        <div class="landing-closer__media">
          <img
            class="landing-closer__image"
            src="/images/landing/closer-fishfarm.jpg"
            :alt="$t('homeCloserAlt')"
            width="700"
            height="525"
            loading="lazy"
            decoding="async"
          />
        </div>
      </div>
    </section>

    <section id="shartnoma" class="landing-band landing-band--muted" aria-labelledby="landing-contract-title">
      <div class="landing-band__inner landing-contract landing-contract--teaser">
        <h2 id="landing-contract-title">{{ $t('homeContractTitle') }}</h2>
        <p>{{ $t('homeContractLead') }}</p>
        <div class="landing-contract__actions">
          <router-link class="landing-contract__btn" :to="{ name: 'tarif-contract' }">
            {{ $t('homeContractRead') }}
          </router-link>
          <button class="landing-contract__btn landing-contract__btn--ghost" type="button" @click="downloadContract">
            {{ $t('homeContractDownload') }}
          </button>
        </div>
      </div>
    </section>

  </div>
</template>

<script>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import BrandLogo from '../components/BrandLogo.vue';
import auth from '../auth';
import LanguageSwitcher from '../components/LanguageSwitcher.vue';
import landingPartners from '../config/landingPartners.js';
import { i18nState } from '../i18n';
import { downloadTarifContract } from '../utils/downloadTarifContract';

const ICON_TECH = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19h16M7 19V9l5-5 5 5v10M10 19v-4h4v4"/></svg>';
const ICON_FEED = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3c-3 4-6 6.5-6 10a6 6 0 0012 0c0-3.5-3-6-6-10z"/><path d="M9.5 14.5c.5 1.5 1.5 2.5 2.5 2.5"/></svg>';
const ICON_MED = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="8" y="3" width="8" height="18" rx="2"/><path d="M8 8h8M12 11v6"/></svg>';
const ICON_EQUIP = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M5.6 18.4L7 17M17 7l1.4-1.4"/></svg>';
const ICON_GUIDE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h11a3 3 0 013 3v13H8a3 3 0 00-3 3V4z"/><path d="M8 4v16"/></svg>';
const ICON_WATER = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3s5 6 5 10a5 5 0 11-10 0c0-4 5-10 5-10z"/></svg>';

const bbtModules = [
  {
    id: 'technology',
    titleKey: 'homeBbtTechTitle',
    subtitleKey: 'homeBbtTechSubtitle',
    image: '/images/landing/pitch-modern-pond.jpg',
    icon: ICON_TECH,
  },
  {
    id: 'feed',
    titleKey: 'homeBbtFeedTitle',
    subtitleKey: 'homeBbtFeedSubtitle',
    image: '/images/landing/pitch-modern-app.jpg',
    icon: ICON_FEED,
  },
  {
    id: 'medicine',
    titleKey: 'homeBbtMedTitle',
    subtitleKey: 'homeBbtMedSubtitle',
    image: '/images/landing/pitch-modern-app.jpg',
    icon: ICON_MED,
  },
  {
    id: 'equipment',
    titleKey: 'homeBbtEquipTitle',
    subtitleKey: 'homeBbtEquipSubtitle',
    image: '/images/landing/pitch-modern-pond.jpg',
    icon: ICON_EQUIP,
  },
  {
    id: 'guides',
    titleKey: 'homeBbtGuideTitle',
    subtitleKey: 'homeBbtGuideSubtitle',
    image: '/images/landing/pitch-modern-app.jpg',
    icon: ICON_GUIDE,
  },
  {
    id: 'water',
    titleKey: 'homeBbtWaterTitle',
    subtitleKey: 'homeBbtWaterSubtitle',
    image: '/images/landing/pitch-modern-pond.jpg',
    icon: ICON_WATER,
  },
];

const partnershipPhotos = [
  { src: '/images/about/china-partnership-team.png', altKey: 'homeAboutAltTeam', captionKey: 'homeAboutCaptionPartnership' },
  { src: '/images/about/partnership-signing.png', altKey: 'homeAboutAltSigning', captionKey: 'homeAboutCaptionAgreement' },
  { src: '/images/about/nutriera-factory.png', altKey: 'homeAboutAltFactory', captionKey: 'homeAboutCaptionProduction' },
  { src: '/images/about/official-visit.png', altKey: 'homeAboutAltVisit', captionKey: 'homeAboutCaptionMeeting' },
  { src: '/images/about/agreement-team.png', altKey: 'homeAboutAltTeam', captionKey: 'homeAboutCaptionPartnership' },
];

export default {
  name: 'Home',
  components: { BrandLogo, LanguageSwitcher },
  setup() {
    const productTrack = ref(null);
    const catalogFeeds = ref([]);
    const catalogLoading = ref(true);
    const activeBbtId = ref(bbtModules[0].id);
    const activeBbt = computed(() => (
      bbtModules.find((m) => m.id === activeBbtId.value) || bbtModules[0]
    ));
    const mainNav = [
      { id: 'kabinet', labelKey: 'homeNavCabinet' },
      { id: 'boss-fish', labelKey: 'homeNavBossFish' },
      { id: 'hamkorliklar', labelKey: 'homeNavPartners' },
      { id: 'mahsulotlar', labelKey: 'homeNavProducts' },
      { id: 'narxlar', labelKey: 'homeNavPricing' },
      { id: 'shartnoma', labelKey: 'homeNavContract' },
    ];
    const pricingPlans = [
      {
        id: 'cabinet',
        nameKey: 'homePlanCabinetName',
        summaryKey: 'homePlanCabinetSummary',
        monthPriceKey: 'homePlanCabinetMonth',
        priceKey: 'homePlanCabinetPrice',
        ctaKey: 'homePricingChoose',
        popular: false,
        featureKeys: [
          'homePlanCabinetF1',
          'homePlanCabinetF2',
          'homePlanCabinetF3',
          'homePlanCabinetF4',
        ],
      },
      {
        id: 'control',
        nameKey: 'homePlanControlName',
        summaryKey: 'homePlanControlSummary',
        monthPriceKey: 'homePlanControlMonth',
        priceKey: 'homePlanControlPrice',
        ctaKey: 'homePricingChoose',
        popular: true,
        featureKeys: [
          'homePlanControlF1',
          'homePlanControlF2',
          'homePlanControlF3',
          'homePlanControlF4',
          'homePlanControlF5',
          'homePlanControlF6',
          'homePlanControlF7',
          'homePlanControlF8',
        ],
      },
      {
        id: 'individual',
        nameKey: 'homePlanIndividualName',
        summaryKey: 'homePlanIndividualSummary',
        monthPriceKey: null,
        priceKey: 'homePlanIndividualPrice',
        ctaKey: 'homePricingNegotiate',
        popular: false,
        featureKeys: [
          'homePlanIndividualF1',
          'homePlanIndividualF2',
          'homePlanIndividualF3',
          'homePlanIndividualF4',
          'homePlanIndividualF5',
        ],
      },
    ];
    const activeNav = ref('kabinet');
    let sectionObserver = null;

    function goToSection(id) {
      activeNav.value = id;
      const el = document.getElementById(id);
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    function downloadContract() {
      downloadTarifContract(i18nState.locale);
    }

    function observeSections() {
      if (typeof IntersectionObserver === 'undefined') return;
      sectionObserver?.disconnect();
      sectionObserver = new IntersectionObserver(
        (entries) => {
          const visible = entries
            .filter((e) => e.isIntersecting)
            .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
          if (visible?.target?.id) {
            activeNav.value = visible.target.id;
          }
        },
        { rootMargin: '-20% 0px -55% 0px', threshold: [0.15, 0.4, 0.7] },
      );
      mainNav.forEach((item) => {
        const el = document.getElementById(item.id);
        if (el) sectionObserver.observe(el);
      });
    }

    const authLink = computed(() => (
      auth.isAuthenticated() ? auth.homeForRole() : { name: 'login' }
    ));

    function scrollTrack(track, direction) {
      if (!track) return;
      const amount = track.clientWidth || 0;
      if (!amount) return;
      track.scrollBy({ left: direction * amount, behavior: 'smooth' });
    }

    function scrollProducts(direction) {
      scrollTrack(productTrack.value, direction);
    }

    async function loadCatalog() {
      catalogLoading.value = true;
      try {
        const { data } = await window.axios.get('/catalog/products');
        const list = data.products || [];
        catalogFeeds.value = list.filter((p) => p.type === 'feed');
      } catch (e) {
        catalogFeeds.value = [];
      } finally {
        catalogLoading.value = false;
      }
    }

    onMounted(() => {
      loadCatalog();
      observeSections();
    });

    onBeforeUnmount(() => {
      sectionObserver?.disconnect();
    });

    return {
      landingPartners,
      catalogFeeds,
      catalogLoading,
      bbtModules,
      activeBbtId,
      activeBbt,
      mainNav,
      activeNav,
      pricingPlans,
      goToSection,
      downloadContract,
      partnershipPhotos,
      productTrack,
      scrollProducts,
      auth,
      authLink,
    };
  },
};
</script>
