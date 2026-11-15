<template>
  <DirectorShell>
    <div class="finance-report-top">
      <button
        v-if="reportScope"
        class="finance-back"
        type="button"
        @click="clearSelection"
      >
        ← {{ $t('back') }}
      </button>
      <router-link
        v-else
        class="finance-back"
        :to="{ name: 'director-finance' }"
      >
        ← {{ $t('back') }}
      </router-link>
      <div v-if="reportScope" class="finance-report-export">
        <button
          class="btn btn-primary btn-sm"
          type="button"
          :disabled="exportingExcel"
          @click="exportExcel"
        >
          {{ exportingExcel ? $t('exportingExcel') : $t('exportExcel') }}
        </button>
      </div>
    </div>

    <div v-if="loading && !booted" class="panel empty">{{ $t('loading') }}</div>

    <template v-else>
      <template v-if="!reportScope">
        <div class="pond-season-tabs" role="tablist">
          <button
            type="button"
            role="tab"
            class="pond-season-tab"
            :class="{ 'pond-season-tab--active': seasonTab === 'active' }"
            :aria-selected="seasonTab === 'active'"
            @click="setSeasonTab('active')"
          >
            {{ $t('pondsActiveTab') }}
            <em>{{ openPonds.length }}</em>
          </button>
          <button
            type="button"
            role="tab"
            class="pond-season-tab"
            :class="{ 'pond-season-tab--active': seasonTab === 'closed' }"
            :aria-selected="seasonTab === 'closed'"
            @click="setSeasonTab('closed')"
          >
            {{ $t('pondsClosedTab') }}
            <em>{{ closedPonds.length }}</em>
          </button>
        </div>

        <div
          class="warehouse-pond-pick finance-expense-quiz finance-report-quiz"
          role="radiogroup"
          :aria-label="$t('financeReportQuizQuestion')"
        >
          <p class="finance-report-quiz__q">{{ $t('financeReportQuizQuestion') }}</p>
          <p class="finance-report-quiz__hint">
            {{ seasonTab === 'active' ? $t('financeReportQuizHint') : $t('financeReportsClosedHint') }}
          </p>

          <template v-if="seasonTab === 'active'">
            <button
              type="button"
              role="radio"
              class="warehouse-pond-chip warehouse-pond-chip--all finance-expense-quiz__answer"
              :aria-checked="false"
              @click="selectPondContext('')"
            >
              <strong>{{ $t('financeReportAskAll') }}</strong>
              <small>{{ $t('financeReportAskAllHint') }}</small>
            </button>

            <template v-if="openPonds.length">
              <p class="warehouse-pond-pick__or">{{ $t('financeReportAskPond') }}</p>
              <div class="warehouse-pond-pick__list finance-section-stack">
                <button
                  v-for="pond in openPonds"
                  :key="`open-${pond.id}`"
                  type="button"
                  role="radio"
                  class="warehouse-pond-chip warehouse-pond-chip--all"
                  :aria-checked="false"
                  @click="selectPondContext(String(pond.id))"
                >
                  <strong>{{ pond.name }}</strong>
                  <small>{{ $t('financeReportQuizPondHint') }}</small>
                </button>
              </div>
            </template>
            <p v-else class="finance-expense-quiz__empty">{{ $t('noActivePondsYet') }}</p>
          </template>

          <template v-else>
            <button
              v-if="closedPonds.length"
              type="button"
              role="radio"
              class="warehouse-pond-chip warehouse-pond-chip--all finance-expense-quiz__answer"
              :aria-checked="false"
              @click="selectPondContext('')"
            >
              <strong>{{ $t('financeClosedAll') }}</strong>
              <small>{{ $t('financeClosedAllHint', { n: closedPonds.length }) }}</small>
            </button>

            <template v-if="closedPonds.length">
              <p class="warehouse-pond-pick__or">{{ $t('financeReportAskPond') }}</p>
              <div class="warehouse-pond-pick__list finance-section-stack">
                <button
                  v-for="pond in closedPonds"
                  :key="`closed-${pond.id}`"
                  type="button"
                  role="radio"
                  class="warehouse-pond-chip warehouse-pond-chip--all"
                  :aria-checked="false"
                  @click="selectPondContext(String(pond.id))"
                >
                  <strong>{{ pond.name }}</strong>
                  <small>{{ $t('pondClosedBadge') }} · {{ $t('financeReportQuizPondHint') }}</small>
                </button>
              </div>
            </template>
            <p v-else class="finance-expense-quiz__empty">{{ $t('financeClosedEmpty') }}</p>
          </template>
        </div>
      </template>

      <template v-else>
        <header class="finance-report-hero">
          <div>
            <p class="finance-report-hero__eyebrow">
              {{ seasonTab === 'closed' ? $t('pondsClosedTab') : $t('pondsActiveTab') }}
            </p>
            <h2>{{ selectedTitle }}</h2>
            <p v-if="selectedSubtitle" class="finance-report-hero__sub">{{ selectedSubtitle }}</p>
          </div>
          <span
            class="finance-report-hero__profit"
            :class="overview.profit >= 0 ? 'is-up' : 'is-down'"
          >
            {{ overview.profit >= 0 ? $t('financeProfit') : $t('ownerDirectorInLoss') }}
            <strong>{{ formatMoney(Math.abs(overview.profit || 0)) }}</strong>
          </span>
        </header>

        <div v-if="seasonTab === 'closed'" class="finance-locked-note">
          {{ $t('financeLockedBanner') }}
        </div>

        <div v-if="loading" class="panel empty">{{ $t('loading') }}</div>

        <template v-else>
          <section class="finance-report-kpis" aria-label="KPI">
            <div class="finance-report-kpi">
              <span>{{ filterPondId ? $t('financePondExpenses') : $t('financeTotalExpenses') }}</span>
              <strong>{{ formatMoney(overview.total_expenses) }}</strong>
            </div>
            <div class="finance-report-kpi">
              <span>{{ $t('financeTotalSales') }}</span>
              <strong>{{ formatMoney(overview.total_sales) }}</strong>
            </div>
            <div class="finance-report-kpi" :class="overview.profit >= 0 ? 'is-up' : 'is-down'">
              <span>{{ $t('financeProfit') }}</span>
              <strong>{{ formatMoney(overview.profit) }}</strong>
            </div>
          </section>

          <section class="finance-visuals">
            <div class="panel finance-visual-card">
              <div class="finance-visual-card__head">
                <h3>{{ $t('financeChartMixTitle') }}</h3>
              </div>
              <div class="finance-donut-wrap">
                <svg class="finance-donut" viewBox="0 0 120 120" role="img" :aria-label="$t('financeChartMixTitle')">
                  <circle cx="60" cy="60" r="40" fill="none" stroke="#e8eee9" stroke-width="16" />
                  <circle
                    v-for="(seg, i) in donutSegments"
                    :key="`donut-${i}`"
                    cx="60"
                    cy="60"
                    r="40"
                    fill="none"
                    :stroke="seg.color"
                    stroke-width="16"
                    stroke-linecap="butt"
                    :stroke-dasharray="seg.dash"
                    :stroke-dashoffset="seg.offset"
                    transform="rotate(-90 60 60)"
                  />
                  <text x="60" y="56" text-anchor="middle" font-size="11" font-weight="800" fill="#0b1f16">
                    {{ chartExpenseShare }}%
                  </text>
                  <text x="60" y="70" text-anchor="middle" font-size="7.5" font-weight="650" fill="#5a7164">
                    {{ $t('financeChartExpenseShare') }}
                  </text>
                </svg>
                <ul class="finance-donut-legend">
                  <li v-for="row in expenseBreakdown" :key="row.key">
                    <i :style="{ background: row.color }" />
                    <span>{{ row.label }}</span>
                    <strong>{{ formatMoney(row.total) }}</strong>
                    <em>{{ row.percent }}%</em>
                  </li>
                  <li v-if="!expenseBreakdown.length" class="finance-donut-legend__empty">
                    {{ $t('noData') }}
                  </li>
                </ul>
              </div>
            </div>

            <div class="panel finance-visual-card">
              <div class="finance-visual-card__head">
                <h3>{{ $t('financeChartBalanceTitle') }}</h3>
              </div>
              <div class="finance-bars">
                <div class="finance-bar">
                  <strong>{{ formatMoney(overview.total_expenses) }}</strong>
                  <div class="finance-bar__track">
                    <div class="finance-bar__fill finance-bar__fill--expense" :style="{ height: expenseBarH }" />
                  </div>
                  <span>{{ $t('financeTotalExpenses') }}</span>
                </div>
                <div class="finance-bar">
                  <strong>{{ formatMoney(overview.total_sales) }}</strong>
                  <div class="finance-bar__track">
                    <div class="finance-bar__fill finance-bar__fill--sale" :style="{ height: saleBarH }" />
                  </div>
                  <span>{{ $t('financeTotalSales') }}</span>
                </div>
                <div class="finance-bar">
                  <strong>{{ formatMoney(overview.profit) }}</strong>
                  <div class="finance-bar__track">
                    <div
                      class="finance-bar__fill"
                      :class="overview.profit >= 0 ? 'finance-bar__fill--profit' : 'finance-bar__fill--loss'"
                      :style="{ height: profitBarH }"
                    />
                  </div>
                  <span>{{ $t('financeProfit') }}</span>
                </div>
              </div>
              <p class="finance-visual-note">{{ balanceStory }}</p>
            </div>
          </section>

          <section class="finance-simple-reports">
            <div class="finance-simple-step">
              <h2>{{ $t('financeSimpleReportsTitle') }}</h2>
              <p>{{ $t('financeByPondHintShort') }}</p>
            </div>

            <div class="finance-simple-report-grid">
              <router-link
                v-for="tab in categoryTabs"
                :key="`report-${tab.routeKey}`"
                class="finance-simple-report-card"
                :class="[
                  tab.cardClass,
                  { 'finance-simple-report-card--warehouse': tab.warehouse },
                ]"
                :to="{
                  name: 'director-finance-report',
                  params: { category: tab.routeKey },
                  query: filterPondId ? { pond: filterPondId } : {},
                }"
              >
                <div class="finance-simple-report-card__top">
                  <strong>{{ tab.label }}</strong>
                  <button
                    v-if="canEditFinance && tab.section"
                    class="finance-section-delete"
                    type="button"
                    :title="$t('delete')"
                    @click.prevent.stop="removeSection(tab.section)"
                  >
                    ×
                  </button>
                </div>
                <span class="finance-simple-report-card__sum">{{ formatMoney(tab.total) }}</span>
                <div class="finance-simple-report-card__bar" aria-hidden="true">
                  <i :style="{ width: `${tab.share}%`, background: tab.color }" />
                </div>
                <small v-if="tab.warehouse">{{ $t('financeSimpleWarehouseBadge') }}</small>
              </router-link>
            </div>
          </section>
        </template>
      </template>
    </template>
  </DirectorShell>
</template>

<script>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { ensureDirectorFarmId, useDirectorFarmId } from '../../utils/directorFarm';
import DirectorShell from '../../components/DirectorShell.vue';
import auth from '../../auth';
import { showSaved } from '../../flash';
import {
  FINANCE_CATEGORIES,
  financeCategoryHint,
  financeCategoryIcon,
  financeCategoryLabel as labelCategory,
  isWarehouseFinanceCategory,
  toRouteCategory,
} from '../../utils/finance';
import { formatMoney } from '../../utils/number';
import {
  downloadExcelWorkbook,
  excelBlankRow,
  excelCell,
  excelDateStamp,
  excelMoney,
} from '../../utils/exportExcel';
import { formatDisplayDate, toIsoDate } from '../../utils/date';
import { t } from '../../i18n';

function emptyOverview() {
  return {
    categories: {},
    custom_sections: [],
    total_expenses: 0,
    total_sales: 0,
    profit: 0,
    by_pond: [],
    ponds: [],
  };
}

function mergeClosedOverviews(pondOverviews, byPondRows) {
  const categories = {};
  const sectionMap = new Map();
  let expenses = 0;
  let sales = 0;

  pondOverviews.forEach((item) => {
    expenses += Number(item.total_expenses) || 0;
    sales += Number(item.total_sales) || 0;
    Object.entries(item.categories || {}).forEach(([key, value]) => {
      categories[key] = (categories[key] || 0) + (Number(value) || 0);
    });
    (item.custom_sections || []).forEach((section) => {
      const id = String(section.id);
      const prev = sectionMap.get(id);
      if (prev) {
        prev.total = (Number(prev.total) || 0) + (Number(section.total) || 0);
      } else {
        sectionMap.set(id, { ...section, total: Number(section.total) || 0 });
      }
    });
  });

  if (!pondOverviews.length && byPondRows.length) {
    expenses = byPondRows.reduce((sum, row) => sum + (Number(row.total_expenses) || 0), 0);
    sales = byPondRows.reduce((sum, row) => sum + (Number(row.total_sales) || 0), 0);
  }

  return {
    categories,
    custom_sections: [...sectionMap.values()],
    total_expenses: Math.round(expenses * 100) / 100,
    total_sales: Math.round(sales * 100) / 100,
    profit: Math.round((sales - expenses) * 100) / 100,
    by_pond: byPondRows,
  };
}

export default {
  components: { DirectorShell },
  setup() {
    const route = useRoute();
    const farmId = useDirectorFarmId();
    const canEditFinance = computed(() => auth.can('finance'));
    const loading = ref(true);
    const seasonTab = ref(route.query.tab === 'closed' ? 'closed' : 'active');
    const reportScope = ref(null);
    const filterPondId = ref('');
    const ponds = ref([]);
    const farmOverview = ref(emptyOverview());
    const overview = ref(emptyOverview());
    const exportingExcel = ref(false);

    const pondFinanceRows = computed(() => farmOverview.value.by_pond || []);
    const openPonds = computed(() => (ponds.value || []).filter((p) => !p.is_closed));
    const closedPonds = computed(() => (ponds.value || []).filter((p) => p.is_closed));
    const closedPondFinanceRows = computed(() => pondFinanceRows.value.filter((row) => row.finance_locked || row.is_closed));
    const openPondFinanceRows = computed(() => pondFinanceRows.value.filter((row) => !row.finance_locked && !row.is_closed));

    // Umumiy = butun xo‘jalik (ombor kirimi + umumiy xarajatlar). Kollar yig‘indisi emas.
    const farmTotals = computed(() => ({
      total_expenses: Number(farmOverview.value.total_expenses) || 0,
      total_sales: Number(farmOverview.value.total_sales) || 0,
      profit: Number(farmOverview.value.profit) || 0,
    }));

    const closedTotals = computed(() => {
      const rows = closedPondFinanceRows.value;
      const expenses = rows.reduce((sum, row) => sum + (Number(row.total_expenses) || 0), 0);
      const sales = rows.reduce((sum, row) => sum + (Number(row.total_sales) || 0), 0);
      return {
        total_expenses: expenses,
        total_sales: sales,
        profit: sales - expenses,
      };
    });

    const CATEGORY_COLORS = {
      feed: '#2e7d32',
      medicine: '#c62828',
      equipment: '#455a64',
      main: '#1565c0',
      salary: '#00838f',
      utilities: '#ef6c00',
      contingent: '#6a1b9a',
      other: '#757575',
      custom: '#6a1b9a',
    };

    const categoryTabs = computed(() => {
      const total = Math.max(Number(overview.value.total_expenses) || 0, 0.0001);

      const fixed = FINANCE_CATEGORIES.map((category) => {
        const amount = Number(overview.value.categories?.[category] || 0);
        return {
          routeKey: category,
          label: labelCategory(category, t),
          icon: financeCategoryIcon(category),
          total: amount,
          share: Math.round((amount / total) * 100),
          color: CATEGORY_COLORS[category],
          hint: financeCategoryHint(category, t),
          warehouse: isWarehouseFinanceCategory(category),
          cardClass: `finance-simple-report-card--${category}`,
          section: null,
        };
      });

      const custom = (overview.value.custom_sections || []).map((section) => {
        const amount = Number(section.total || 0);
        return {
          routeKey: toRouteCategory(section.category),
          label: section.name,
          icon: financeCategoryIcon(section.category),
          total: amount,
          share: Math.round((amount / total) * 100),
          color: CATEGORY_COLORS.custom,
          hint: t('financeCatCustomHint'),
          warehouse: false,
          cardClass: 'finance-simple-report-card--custom',
          section,
        };
      });

      return [...fixed, ...custom];
    });

    const expenseBreakdown = computed(() => (
      categoryTabs.value
        .filter((row) => row.total > 0)
        .map((row) => ({
          key: row.routeKey,
          label: row.label,
          total: row.total,
          percent: row.share,
          color: row.color,
        }))
    ));

    const donutSegments = computed(() => {
      const circ = 2 * Math.PI * 40;
      const total = Math.max(Number(overview.value.total_expenses) || 0, 0.0001);
      let cursor = 0;
      const rows = expenseBreakdown.value.length
        ? expenseBreakdown.value
        : [{ total: 1, color: '#e8eee9' }];

      return rows.map((row) => {
        const len = (Number(row.total) / total) * circ;
        const seg = {
          color: row.color,
          dash: `${len} ${circ - len}`,
          offset: -cursor,
        };
        cursor += len;
        return seg;
      });
    });

    const chartExpenseShare = computed(() => {
      const expense = Number(overview.value.total_expenses) || 0;
      const sale = Number(overview.value.total_sales) || 0;
      const base = expense + sale;
      if (base <= 0) return 0;
      return Math.round((expense / base) * 100);
    });

    const maxMoney = computed(() => Math.max(
      Number(overview.value.total_expenses) || 0,
      Number(overview.value.total_sales) || 0,
      Math.abs(Number(overview.value.profit) || 0),
      1,
    ));

    function barH(value) {
      const n = Math.abs(Number(value) || 0);
      return `${Math.max((n / maxMoney.value) * 100, n > 0 ? 8 : 2)}%`;
    }

    const expenseBarH = computed(() => barH(overview.value.total_expenses));
    const saleBarH = computed(() => barH(overview.value.total_sales));
    const profitBarH = computed(() => barH(overview.value.profit));

    const balanceStory = computed(() => {
      const profit = Number(overview.value.profit) || 0;
      if (profit > 0) {
        return t('financeBalanceStoryProfit', { amount: formatMoney(profit) });
      }
      if (profit < 0) {
        return t('financeBalanceStoryLoss', { amount: formatMoney(Math.abs(profit)) });
      }
      return t('financeBalanceStoryZero');
    });

    const booted = ref(false);

    function pondMeta(pond) {
      const place = pond.district || pond.region || '';
      const area = pond.area_m2 != null ? `${pond.area_m2} ${t('hectare')}` : '';
      return [place, area].filter(Boolean).join(' · ') || t('financeByPondHintShort');
    }

    function pondRow(pondId) {
      return pondFinanceRows.value.find((row) => String(row.pond_id) === String(pondId));
    }

    function profitMoneyLine(profit) {
      const value = Number(profit) || 0;
      const label = value >= 0 ? t('financeProfit') : t('ownerDirectorInLoss');
      return `${label}: ${formatMoney(Math.abs(value))}`;
    }

    function pondMoneyLine(pondId) {
      const row = pondRow(pondId);
      if (!row) return `${t('financeProfit')}: —`;
      return profitMoneyLine(row.profit);
    }

    function pondProfitClass(pondId) {
      const row = pondRow(pondId);
      if (!row) return '';
      return Number(row.profit) >= 0 ? 'is-up' : 'is-down';
    }

    async function loadFarmBase() {
      const id = await ensureDirectorFarmId();
      if (!id) {
        ponds.value = [];
        farmOverview.value = emptyOverview();
        return null;
      }
      const { data } = await window.axios.get(`/director/farms/${id}/finance/overview`);
      farmOverview.value = data;
      ponds.value = data.ponds || [];
      return id;
    }

    async function loadClosedAggregate(byPondRows) {
      const id = farmId.value;
      const ids = byPondRows.map((row) => row.pond_id).filter(Boolean);
      if (!id || !ids.length) {
        overview.value = {
          ...emptyOverview(),
          by_pond: byPondRows,
          ponds: ponds.value,
        };
        return;
      }
      const responses = await Promise.all(
        ids.map((pondId) => window.axios.get(`/director/farms/${id}/finance/overview`, {
          params: { pond_id: pondId },
        })),
      );
      overview.value = {
        ...mergeClosedOverviews(responses.map((res) => res.data), byPondRows),
        ponds: ponds.value,
      };
    }

    async function loadSelection() {
      const id = farmId.value || await ensureDirectorFarmId();
      if (!id) {
        overview.value = emptyOverview();
        return;
      }

      if (filterPondId.value) {
        const { data } = await window.axios.get(`/director/farms/${id}/finance/overview`, {
          params: { pond_id: filterPondId.value },
        });
        overview.value = data;
        return;
      }

      if (seasonTab.value === 'closed') {
        await loadClosedAggregate(closedPondFinanceRows.value);
        return;
      }

      // To‘liq xo‘jalik: ombor + umumiy xarajatlar API dan keladi
      overview.value = { ...farmOverview.value };
    }

    async function load() {
      if (!booted.value) loading.value = true;
      try {
        await loadFarmBase();
        if (reportScope.value) {
          await loadSelection();
        }
      } catch (e) {
        ponds.value = [];
        overview.value = emptyOverview();
      } finally {
        loading.value = false;
        booted.value = true;
      }
    }

    const selectedTitle = computed(() => {
      if (reportScope.value !== 'pond') {
        return seasonTab.value === 'closed'
          ? t('financeClosedAll')
          : t('financeReportAskAll');
      }
      const pond = (ponds.value || []).find((p) => String(p.id) === String(filterPondId.value));
      return pond?.name || t('financeReportAskPond');
    });

    const selectedSubtitle = computed(() => {
      if (reportScope.value !== 'pond') {
        return seasonTab.value === 'closed'
          ? t('financeClosedAllHint', { n: closedPonds.value.length })
          : t('financeReportAskAllHint');
      }
      const pond = (ponds.value || []).find((p) => String(p.id) === String(filterPondId.value));
      return pond ? pondMeta(pond) : '';
    });

    function setSeasonTab(next) {
      if (seasonTab.value === next) return;
      seasonTab.value = next;
      reportScope.value = null;
      filterPondId.value = '';
      overview.value = emptyOverview();
    }

    function clearSelection() {
      reportScope.value = null;
      filterPondId.value = '';
      overview.value = emptyOverview();
    }

    async function selectPondContext(pondId) {
      if (pondId) {
        reportScope.value = 'pond';
        filterPondId.value = pondId;
      } else {
        reportScope.value = 'farm';
        filterPondId.value = '';
      }
      loading.value = true;
      try {
        if (!farmOverview.value.ponds?.length && !ponds.value.length) {
          await loadFarmBase();
        }
        await loadSelection();
      } finally {
        loading.value = false;
      }
    }

    async function removeSection(section) {
      if (!confirm(t('financeConfirmDeleteSection', { name: section.name }))) return;
      try {
        await window.axios.delete(`/director/farms/${farmId.value}/finance/sections/${section.id}`);
        showSaved(t('saved'));
        await load();
      } catch (e) {
        alert(e.response?.data?.message || t('noData'));
      }
    }

    function pondNameById(pondId) {
      if (!pondId) return t('financeScopeAllPondsShort');
      const pond = (ponds.value || []).find((p) => String(p.id) === String(pondId));
      return pond?.name || `#${pondId}`;
    }

    function categoryLabelForExport(category) {
      const custom = {};
      (overview.value.custom_sections || []).forEach((section) => {
        if (section?.id != null) custom[section.id] = section.name;
        if (section?.category) custom[section.category] = section.name;
      });
      return labelCategory(category, t, custom);
    }

    async function fetchExpenseRows() {
      const id = farmId.value;
      if (!id) return [];
      const params = {};
      if (filterPondId.value) {
        params.pond_id = filterPondId.value;
      }
      const { data } = await window.axios.get(`/director/farms/${id}/finance/expenses`, { params });
      let rows = Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : []);

      if (!filterPondId.value && seasonTab.value === 'closed') {
        const closedIds = new Set(closedPonds.value.map((p) => String(p.id)));
        rows = rows.filter((row) => row.pond_id && closedIds.has(String(row.pond_id)));
      } else if (!filterPondId.value && seasonTab.value === 'active') {
        const openIds = new Set(openPonds.value.map((p) => String(p.id)));
        rows = rows.filter((row) => !row.pond_id || openIds.has(String(row.pond_id)));
      }

      return rows;
    }

    async function exportExcel() {
      if (!reportScope.value || exportingExcel.value) return;
      exportingExcel.value = true;
      try {
        const expenseRowsRaw = await fetchExpenseRows();
        const stamp = excelDateStamp();
        const title = selectedTitle.value || t('financeReportsScopeTitle');
        const seasonLabel = seasonTab.value === 'closed' ? t('pondsClosedTab') : t('pondsActiveTab');

        const expenseRows = [...expenseRowsRaw].sort((a, b) => {
          const da = toIsoDate(a.expense_date);
          const db = toIsoDate(b.expense_date);
          if (da !== db) return da.localeCompare(db);
          const ca = categoryLabelForExport(a.category);
          const cb = categoryLabelForExport(b.category);
          if (ca !== cb) return ca.localeCompare(cb, 'uz');
          return String(a.title || '').localeCompare(String(b.title || ''), 'uz');
        });

        // Bitta manba: xarajatlar ro‘yxati — umumiy / bo‘lim / havza bir xil chiqadi
        const expenseTotal = excelMoney(
          expenseRows.reduce((sum, row) => sum + (Number(row.amount) || 0), 0),
        );

        const categoryMap = new Map();
        expenseRows.forEach((row) => {
          const label = categoryLabelForExport(row.category);
          categoryMap.set(label, (categoryMap.get(label) || 0) + (Number(row.amount) || 0));
        });
        const categoriesSorted = [...categoryMap.entries()]
          .map(([label, total]) => ({
            label,
            total: excelMoney(total),
            percent: expenseTotal > 0 ? excelMoney((total / expenseTotal) * 100) : 0,
          }))
          .sort((a, b) => b.total - a.total);

        const salesByPond = new Map();
        (overview.value.by_pond || []).forEach((row) => {
          if (row?.pond_id != null) {
            salesByPond.set(String(row.pond_id), excelMoney(row.total_sales));
          }
        });
        // Agar bitta havza tanlangan bo‘lsa, overview sotuvi shu havzaniki
        if (filterPondId.value && !salesByPond.has(String(filterPondId.value))) {
          salesByPond.set(String(filterPondId.value), excelMoney(overview.value.total_sales));
        }

        const pondExpenseMap = new Map();
        expenseRows.forEach((row) => {
          const key = row.pond_id ? String(row.pond_id) : 'farm';
          const name = row.pond_id
            ? (row.pond?.name || pondNameById(row.pond_id))
            : t('financeExcelFarmScope');
          const prev = pondExpenseMap.get(key) || { key, name, expenses: 0, sales: 0 };
          prev.expenses += Number(row.amount) || 0;
          if (row.pond_id) {
            prev.sales = salesByPond.get(String(row.pond_id)) || 0;
          }
          pondExpenseMap.set(key, prev);
        });
        // Sotuvi bor, xarajati yo‘q havzalar ham chiqsin
        salesByPond.forEach((sales, pondId) => {
          if (!pondExpenseMap.has(pondId)) {
            pondExpenseMap.set(pondId, {
              key: pondId,
              name: pondNameById(pondId),
              expenses: 0,
              sales,
            });
          }
        });

        const pondSource = [...pondExpenseMap.values()]
          .map((row) => ({
            ...row,
            expenses: excelMoney(row.expenses),
            sales: excelMoney(row.sales),
            profit: excelMoney(row.sales - row.expenses),
          }))
          .sort((a, b) => {
            if (a.key === 'farm') return 1;
            if (b.key === 'farm') return -1;
            return String(a.name).localeCompare(String(b.name), 'uz');
          });

        const salesTotal = excelMoney(
          filterPondId.value
            ? (salesByPond.get(String(filterPondId.value)) || excelMoney(overview.value.total_sales))
            : pondSource.reduce((sum, row) => sum + (row.key === 'farm' ? 0 : row.sales), 0),
        );
        // Umumiy sotuv: overview bilan moslash (takrorlanmasin)
        const salesForSummary = filterPondId.value
          ? salesTotal
          : excelMoney(overview.value.total_sales);
        const profit = excelMoney(salesForSummary - expenseTotal);

        const gap = [];

        const reportRows = [
          [excelCell(t('financeExcelTitle'), 'title', 4)],
          gap,
          [excelCell(t('financeExcelGeneratedAt'), 'metaLabel'), excelCell(stamp, 'metaValue', 3)],
          [excelCell(t('financeExcelSeason'), 'metaLabel'), excelCell(seasonLabel, 'metaValue', 3)],
          [excelCell(t('financeExcelScope'), 'metaLabel'), excelCell(title, 'metaValue', 3)],
          [excelCell(t('financeExcelNoteConsistent'), 'metaValue', 4)],
          gap,

          [excelCell(`1. ${t('financeExcelSheetSummary')}`, 'section', 4)],
          [
            excelCell(t('financeExcelMetric'), 'header'),
            excelCell(t('financeExcelAmount'), 'header'),
          ],
          [
            excelCell(t('financeTotalExpenses'), 'text'),
            excelCell(expenseTotal, 'money'),
          ],
          [
            excelCell(t('financeTotalSales'), 'text'),
            excelCell(salesForSummary, 'money'),
          ],
          [
            excelCell(t('financeExcelProfitLoss'), 'total'),
            excelCell(profit, 'total'),
          ],
          gap,

          [excelCell(`2. ${t('financeExcelSheetCategories')}`, 'section', 4)],
          [
            excelCell('№', 'header'),
            excelCell(t('financeExcelCategory'), 'header'),
            excelCell(t('financeExcelAmount'), 'header'),
            excelCell(t('financeExcelShare'), 'header'),
          ],
          ...categoriesSorted.map((row, idx) => [
            excelCell(idx + 1, 'text'),
            excelCell(row.label, 'text'),
            excelCell(row.total, 'money'),
            excelCell(row.percent, 'money'),
          ]),
          [
            excelCell('', 'total'),
            excelCell(t('all'), 'total'),
            excelCell(expenseTotal, 'total'),
            excelCell(categoriesSorted.length ? 100 : 0, 'total'),
          ],
          gap,

          [excelCell(`3. ${t('financeExcelSheetPonds')}`, 'section', 4)],
          [
            excelCell('№', 'header'),
            excelCell(t('pond'), 'header'),
            excelCell(t('financeTotalExpenses'), 'header'),
            excelCell(t('financeTotalSales'), 'header'),
            excelCell(t('financeExcelProfitLoss'), 'header'),
          ],
          ...pondSource.map((row, idx) => [
            excelCell(idx + 1, 'text'),
            excelCell(row.name, 'text'),
            excelCell(row.expenses, 'money'),
            excelCell(row.sales, 'money'),
            excelCell(row.profit, 'money'),
          ]),
          ...(pondSource.length ? [[
            excelCell('', 'total'),
            excelCell(t('all'), 'total'),
            excelCell(excelMoney(pondSource.reduce((s, r) => s + r.expenses, 0)), 'total'),
            excelCell(salesForSummary, 'total'),
            excelCell(profit, 'total'),
          ]] : [[excelCell(t('noData'), 'text', 4)]]),
        ];

        const detailRows = [
          [excelCell(`${t('financeExcelTitle')} — ${t('financeExcelSheetExpenses')}`, 'title', 6)],
          gap,
          [excelCell(t('financeExcelScope'), 'metaLabel'), excelCell(title, 'metaValue', 5)],
          [excelCell(t('financeExcelGeneratedAt'), 'metaLabel'), excelCell(stamp, 'metaValue', 5)],
          gap,
          [
            excelCell('№', 'header'),
            excelCell(t('date'), 'header'),
            excelCell(t('financeExcelCategory'), 'header'),
            excelCell(t('financeExpenseName'), 'header'),
            excelCell(t('pond'), 'header'),
            excelCell(t('financeExcelAmount'), 'header'),
            excelCell(t('notes'), 'header'),
          ],
          ...(expenseRows.length
            ? expenseRows.map((row, idx) => [
              excelCell(idx + 1, 'text'),
              excelCell(formatDisplayDate(row.expense_date), 'text'),
              excelCell(categoryLabelForExport(row.category), 'text'),
              excelCell(row.title || '—', 'text'),
              excelCell(
                row.pond_id
                  ? (row.pond?.name || pondNameById(row.pond_id))
                  : t('financeExcelFarmScope'),
                'text',
              ),
              excelCell(excelMoney(row.amount), 'money'),
              excelCell(row.description || '', 'text'),
            ])
            : [[excelCell(t('noData'), 'text', 6)]]),
        ];

        if (expenseRows.length) {
          detailRows.push([
            excelCell('', 'total'),
            excelCell('', 'total'),
            excelCell('', 'total'),
            excelCell('', 'total'),
            excelCell(t('all'), 'total'),
            excelCell(expenseTotal, 'total'),
            excelCell('', 'total'),
          ]);
        }

        const fileBase = [
          'moliya-hisobot',
          seasonTab.value === 'closed' ? 'yopiq' : 'ochiq',
          filterPondId.value ? `havza-${filterPondId.value}` : 'umumiy',
          stamp,
        ].join('-');

        downloadExcelWorkbook(fileBase, [
          {
            name: t('financeExcelSheetSummary'),
            columns: [40, 150, 120, 120, 120],
            rows: reportRows,
          },
          {
            name: t('financeExcelSheetExpenses'),
            columns: [40, 95, 130, 170, 130, 120, 180],
            freezeRow: 5,
            rows: detailRows,
          },
        ]);
      } catch (e) {
        window.alert(e.response?.data?.message || t('errorOccurred'));
      } finally {
        exportingExcel.value = false;
      }
    }

    onMounted(load);

    return {
      farmId,
      canEditFinance,
      loading,
      booted,
      exportingExcel,
      seasonTab,
      overview,
      ponds,
      openPonds,
      closedPonds,
      farmTotals,
      closedTotals,
      reportScope,
      filterPondId,
      selectedTitle,
      selectedSubtitle,
      categoryTabs,
      expenseBreakdown,
      donutSegments,
      chartExpenseShare,
      expenseBarH,
      saleBarH,
      profitBarH,
      balanceStory,
      formatMoney,
      exportExcel,
      pondMeta,
      profitMoneyLine,
      pondMoneyLine,
      pondProfitClass,
      setSeasonTab,
      clearSelection,
      selectPondContext,
      removeSection,
    };
  },
};
</script>

<style scoped>
.finance-report-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  margin: 0 0 0.75rem;
}

.finance-report-export {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  align-items: center;
}

.finance-back {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.35rem 0.15rem;
  border: 0;
  background: transparent;
  color: #0f766e;
  font: inherit;
  font-size: 0.9rem;
  font-weight: 750;
  text-decoration: none;
  cursor: pointer;
}

.finance-list {
  display: grid;
  gap: 0.55rem;
}

.finance-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  padding: 0.85rem 0.9rem;
  border: 1px solid rgba(15, 23, 42, 0.1);
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
  text-align: left;
  font: inherit;
  color: inherit;
  cursor: pointer;
}

.finance-row--all {
  background: linear-gradient(145deg, #f0fdf4, #fff);
  border-color: rgba(15, 118, 110, 0.22);
}

.finance-row__main {
  min-width: 0;
  flex: 1;
  display: grid;
  gap: 0.2rem;
}

.finance-row__title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
}

.finance-row__main strong {
  font-size: 1rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.25;
}

.finance-row__main small {
  color: #64748b;
  font-size: 0.8rem;
  line-height: 1.3;
}

.finance-row__main span {
  color: #334155;
  font-size: 0.88rem;
  font-weight: 700;
}

.finance-row__main span.is-up { color: #047857; }
.finance-row__main span.is-down { color: #b91c1c; }

.finance-row__title mark {
  display: inline-flex;
  align-items: center;
  padding: 0.1rem 0.45rem;
  border-radius: 999px;
  background: #e2e8f0;
  color: #475569;
  font-size: 0.68rem;
  font-weight: 800;
  font-style: normal;
}

.finance-row__arrow {
  flex-shrink: 0;
  font-style: normal;
  font-size: 1.35rem;
  font-weight: 500;
  color: #94a3b8;
  line-height: 1;
}

.finance-report-hero {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.85rem 1.25rem;
  margin: 0.15rem 0 1rem;
  padding: 1rem 1.1rem;
  border-radius: 18px;
  background:
    linear-gradient(145deg, rgba(240, 253, 250, 0.95), rgba(255, 255, 255, 0.98));
  border: 1px solid rgba(15, 118, 110, 0.14);
}

.finance-report-hero__eyebrow {
  margin: 0 0 0.25rem;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #0f766e;
}

.finance-report-hero h2 {
  margin: 0;
  font-size: 1.35rem;
  line-height: 1.2;
  color: #0f172a;
}

.finance-report-hero__sub {
  margin: 0.35rem 0 0;
  color: #64748b;
  font-size: 0.9rem;
}

.finance-report-hero__profit {
  display: grid;
  gap: 0.15rem;
  min-width: 9rem;
  padding: 0.65rem 0.8rem;
  border-radius: 14px;
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.08);
  font-size: 0.75rem;
  font-weight: 750;
  color: #64748b;
  text-align: right;
}

.finance-report-hero__profit strong {
  font-size: 1.05rem;
  font-weight: 850;
}

.finance-report-hero__profit.is-up strong { color: #047857; }
.finance-report-hero__profit.is-down strong { color: #b91c1c; }

.finance-locked-note {
  margin: 0 0 0.9rem;
  padding: 0.7rem 0.9rem;
  border-radius: 12px;
  background: #fffbeb;
  border: 1px solid #fcd34d;
  color: #92400e;
  font-size: 0.88rem;
  font-weight: 650;
  line-height: 1.4;
}

.finance-report-kpis {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.7rem;
  margin-bottom: 1rem;
}

.finance-report-kpi {
  padding: 0.95rem 1rem;
  border-radius: 16px;
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
}

.finance-report-kpi span {
  display: block;
  margin-bottom: 0.3rem;
  font-size: 0.74rem;
  font-weight: 750;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.finance-report-kpi strong {
  font-size: 1.15rem;
  font-weight: 850;
  color: #0f172a;
  word-break: break-word;
}

.finance-report-kpi.is-up strong { color: #047857; }
.finance-report-kpi.is-down strong { color: #b91c1c; }

.finance-visuals {
  display: grid;
  grid-template-columns: 1.15fr 1fr;
  gap: 0.85rem;
  margin-bottom: 1rem;
}

.finance-visual-card__head {
  margin-bottom: 0.75rem;
}

.finance-visual-card__head h3 {
  margin: 0 0 0.2rem;
  font-size: 1.05rem;
}

.finance-donut-wrap {
  display: grid;
  grid-template-columns: 140px 1fr;
  gap: 0.85rem;
  align-items: center;
}

.finance-donut {
  width: 140px;
  height: 140px;
}

.finance-donut-legend {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.45rem;
}

.finance-donut-legend li {
  display: grid;
  grid-template-columns: 10px 1fr auto auto;
  gap: 0.45rem;
  align-items: center;
  font-size: 0.82rem;
}

.finance-donut-legend i {
  width: 10px;
  height: 10px;
  border-radius: 3px;
}

.finance-donut-legend strong { font-weight: 800; }

.finance-donut-legend em {
  font-style: normal;
  color: #5a7164;
  font-weight: 700;
  min-width: 2.4rem;
  text-align: right;
}

.finance-bars {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.65rem;
  align-items: end;
  min-height: 160px;
}

.finance-bar {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
  text-align: center;
}

.finance-bar strong {
  font-size: 0.82rem;
  font-weight: 800;
}

.finance-bar span {
  font-size: 0.72rem;
  font-weight: 700;
  color: #5a7164;
}

.finance-bar__track {
  width: 100%;
  max-width: 52px;
  height: 110px;
  border-radius: 12px;
  background: #eef4f0;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
}

.finance-bar__fill {
  width: 100%;
  border-radius: 12px 12px 0 0;
  min-height: 4px;
  transition: height 0.35s ease;
}

.finance-bar__fill--expense { background: linear-gradient(180deg, #ef5350, #c62828); }
.finance-bar__fill--sale { background: linear-gradient(180deg, #64b5f6, #1565c0); }
.finance-bar__fill--profit { background: linear-gradient(180deg, #66bb6a, #2e7d32); }
.finance-bar__fill--loss { background: linear-gradient(180deg, #ffb74d, #ef6c00); }

.finance-visual-note {
  margin: 0.85rem 0 0;
  padding: 0.6rem 0.75rem;
  border-radius: 12px;
  background: #f4faf6;
  font-size: 0.86rem;
  font-weight: 650;
  color: #0b1f16;
  line-height: 1.4;
}

.finance-simple-reports {
  margin-bottom: 1rem;
  padding: 1rem 1.05rem 1.1rem;
  border-radius: 18px;
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.08);
}

.finance-simple-step {
  margin-bottom: 0.95rem;
}

.finance-simple-step h2 {
  margin: 0 0 0.25rem;
  font-size: 1.12rem;
}

.finance-simple-step p {
  margin: 0;
  color: #64748b;
  font-size: 0.88rem;
}

.finance-simple-report-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(168px, 1fr));
  gap: 0.7rem;
}

.finance-donut-legend__empty {
  grid-column: 1 / -1;
  color: #94a3b8;
  font-weight: 650;
}

.finance-simple-report-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.9rem 0.95rem;
  border-radius: 14px;
  text-decoration: none;
  color: inherit;
  border: 1px solid rgba(10, 60, 40, 0.1);
  background: #f7faf8;
}

.finance-simple-report-card__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.35rem;
}

.finance-simple-report-card__top strong { font-size: 0.98rem; }

.finance-simple-report-card__sum {
  font-size: 1.05rem;
  font-weight: 850;
  color: #065c36;
}

.finance-simple-report-card__bar {
  height: 6px;
  border-radius: 999px;
  background: rgba(10, 60, 40, 0.08);
  overflow: hidden;
  margin: 0.15rem 0 0.1rem;
}

.finance-simple-report-card__bar i {
  display: block;
  height: 100%;
  border-radius: inherit;
  max-width: 100%;
}

.finance-simple-report-card small {
  color: #5a7164;
  font-size: 0.78rem;
  font-weight: 650;
}

.finance-simple-report-card--feed { border-left: 4px solid #2e7d32; background: linear-gradient(160deg, #e8f5e9, #fff); }
.finance-simple-report-card--medicine { border-left: 4px solid #c62828; background: linear-gradient(160deg, #fce4ec, #fff); }
.finance-simple-report-card--main { border-left: 4px solid #1565c0; background: linear-gradient(160deg, #e3f2fd, #fff); }
.finance-simple-report-card--salary { border-left: 4px solid #00838f; background: linear-gradient(160deg, #e0f7fa, #fff); }
.finance-simple-report-card--utilities { border-left: 4px solid #ef6c00; background: linear-gradient(160deg, #fff3e0, #fff); }
.finance-simple-report-card--contingent { border-left: 4px solid #6a1b9a; background: linear-gradient(160deg, #f3e5f5, #fff); }
.finance-simple-report-card--other { border-left: 4px solid #ef6c00; background: linear-gradient(160deg, #fff3e0, #fff); }
.finance-simple-report-card--custom { border-left: 4px solid #6a1b9a; background: linear-gradient(160deg, #f3e5f5, #fff); }
.finance-simple-report-card--warehouse small { color: #0d8a52; }

.finance-closed-entry {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.85rem;
  margin-top: 1rem;
  padding: 0.85rem 1rem;
  border: 1px dashed rgba(15, 23, 42, 0.18);
  border-radius: 14px;
  background: #f7f8f7;
  color: inherit;
  text-decoration: none;
}

.finance-closed-entry strong {
  display: block;
  font-size: 0.98rem;
}

.finance-closed-entry small {
  display: block;
  margin-top: 0.2rem;
  color: #5a7164;
  font-size: 0.84rem;
  line-height: 1.35;
}

.finance-closed-entry em {
  flex-shrink: 0;
  font-style: normal;
  font-size: 0.84rem;
  font-weight: 800;
  color: #128a4a;
}

@media (max-width: 860px) {
  .finance-visuals { grid-template-columns: 1fr; }
  .finance-donut-wrap { grid-template-columns: 120px 1fr; }
}

@media (max-width: 720px) {
  .finance-report-kpis { grid-template-columns: 1fr; }
  .finance-donut-wrap { grid-template-columns: 1fr; justify-items: center; }
  .finance-report-hero__profit { width: 100%; text-align: left; }
  .finance-row { padding: 0.75rem 0.8rem; }
}
</style>
