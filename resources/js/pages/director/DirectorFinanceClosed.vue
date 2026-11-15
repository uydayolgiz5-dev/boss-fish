<template>
  <DirectorShell>
    <div class="page-head page-head--toolbar">
      <strong class="page-head__context">{{ $t('financeClosedPondsTitle') }}</strong>
      <router-link class="btn btn-outline btn-sm" :to="{ name: 'director-finance-overview' }">{{ $t('back') }}</router-link>
    </div>

    <div v-if="loading" class="panel empty">{{ $t('loading') }}</div>

    <template v-else>
      <div v-if="!closedPonds.length" class="panel empty">{{ $t('financeClosedEmpty') }}</div>

      <template v-else>
        <section class="panel">
          <div class="warehouse-pond-pick" role="radiogroup" :aria-label="$t('financeReportAsk')">
            <p class="warehouse-pond-pick__q">{{ $t('financeReportAsk') }}</p>

            <button
              type="button"
              role="radio"
              class="warehouse-pond-chip warehouse-pond-chip--all"
              :class="{ 'is-active': reportScope === 'farm' }"
              :aria-checked="reportScope === 'farm'"
              @click="selectPondContext('')"
            >
              <strong>{{ $t('financeReportAskAll') }}</strong>
              <small>{{ $t('financeClosedAllHint', { n: closedPonds.length }) }}</small>
            </button>

            <p class="warehouse-pond-pick__or">{{ $t('financeReportAskPond') }}</p>
            <div class="warehouse-pond-pick__list finance-section-stack">
              <button
                v-for="pond in closedPonds"
                :key="pond.id"
                type="button"
                role="radio"
                class="warehouse-pond-chip warehouse-pond-chip--all"
                :class="{ 'is-active': reportScope === 'pond' && filterPondId === String(pond.id) }"
                :aria-checked="reportScope === 'pond' && filterPondId === String(pond.id)"
                @click="selectPondContext(String(pond.id))"
              >
                <strong>{{ pond.name }}</strong>
              </button>
            </div>
          </div>
        </section>

        <template v-if="reportScope">
          <div class="panel pond-closed-banner">
            <p>{{ $t('financeLockedBanner') }}</p>
          </div>

          <section class="panel finance-report-kpis">
            <div class="finance-report-kpi">
              <span>{{ filterPondId ? $t('financePondExpenses') : $t('financeTotalExpenses') }}</span>
              <strong>{{ formatMoney(displayOverview.total_expenses) }}</strong>
            </div>
            <div class="finance-report-kpi">
              <span>{{ $t('financeTotalSales') }}</span>
              <strong>{{ formatMoney(displayOverview.total_sales) }}</strong>
            </div>
            <div class="finance-report-kpi" :class="displayOverview.profit >= 0 ? 'is-up' : 'is-down'">
              <span>{{ $t('financeProfit') }}</span>
              <strong>{{ formatMoney(displayOverview.profit) }}</strong>
            </div>
          </section>

          <section v-if="!filterPondId && closedPondFinanceRows.length" class="panel finance-pond-board">
            <div class="finance-pond-board__head">
              <h2>{{ $t('financeByPondTitle') }}</h2>
              <p>{{ $t('financeByPondHintShort') }}</p>
            </div>
            <div class="finance-pond-board__grid">
              <button
                v-for="row in closedPondFinanceRows"
                :key="row.pond_id"
                type="button"
                class="finance-pond-card"
                :class="row.profit >= 0 ? 'finance-pond-card--up' : 'finance-pond-card--down'"
                @click="selectPondContext(String(row.pond_id))"
              >
                <strong>{{ row.name }}</strong>
                <span>{{ $t('financeTotalExpenses') }}: {{ formatMoney(row.total_expenses) }}</span>
                <span>{{ $t('financeTotalSales') }}: {{ formatMoney(row.total_sales) }}</span>
                <em>{{ row.profit >= 0 ? $t('financeProfit') : $t('ownerDirectorInLoss') }}: {{ formatMoney(Math.abs(row.profit)) }}</em>
              </button>
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
                </ul>
              </div>
            </div>

            <div class="panel finance-visual-card">
              <div class="finance-visual-card__head">
                <h3>{{ $t('financeChartBalanceTitle') }}</h3>
              </div>
              <div class="finance-bars">
                <div class="finance-bar">
                  <strong>{{ formatMoney(displayOverview.total_expenses) }}</strong>
                  <div class="finance-bar__track">
                    <div class="finance-bar__fill finance-bar__fill--expense" :style="{ height: expenseBarH }" />
                  </div>
                  <span>{{ $t('financeTotalExpenses') }}</span>
                </div>
                <div class="finance-bar">
                  <strong>{{ formatMoney(displayOverview.total_sales) }}</strong>
                  <div class="finance-bar__track">
                    <div class="finance-bar__fill finance-bar__fill--sale" :style="{ height: saleBarH }" />
                  </div>
                  <span>{{ $t('financeTotalSales') }}</span>
                </div>
                <div class="finance-bar">
                  <strong>{{ formatMoney(displayOverview.profit) }}</strong>
                  <div class="finance-bar__track">
                    <div
                      class="finance-bar__fill"
                      :class="displayOverview.profit >= 0 ? 'finance-bar__fill--profit' : 'finance-bar__fill--loss'"
                      :style="{ height: profitBarH }"
                    />
                  </div>
                  <span>{{ $t('financeProfit') }}</span>
                </div>
              </div>
              <p class="finance-visual-note">{{ balanceStory }}</p>
            </div>
          </section>

          <section class="panel finance-simple-reports">
            <div class="finance-simple-step">
              <h2>{{ $t('financeSimpleReportsTitle') }}</h2>
            </div>

            <div class="finance-simple-report-grid">
              <component
                :is="filterPondId ? 'router-link' : 'div'"
                v-for="tab in categoryTabs"
                :key="`closed-report-${tab.routeKey}`"
                class="finance-simple-report-card"
                :class="[
                  tab.cardClass,
                  { 'finance-simple-report-card--warehouse': tab.warehouse },
                ]"
                v-bind="filterPondId ? {
                  to: {
                    name: 'director-finance-report',
                    params: { category: tab.routeKey },
                    query: { pond: filterPondId },
                  },
                } : {}"
              >
                <div class="finance-simple-report-card__top">
                  <strong>{{ tab.label }}</strong>
                </div>
                <span class="finance-simple-report-card__sum">{{ formatMoney(tab.total) }}</span>
                <div class="finance-simple-report-card__bar" aria-hidden="true">
                  <i :style="{ width: `${tab.share}%`, background: tab.color }" />
                </div>
                <small v-if="tab.warehouse">{{ $t('financeSimpleWarehouseBadge') }}</small>
              </component>
            </div>
          </section>
        </template>
      </template>
    </template>
  </DirectorShell>
</template>

<script>
import { computed, onMounted, ref } from 'vue';
import { useDirectorFarmId } from '../../utils/directorFarm';
import DirectorShell from '../../components/DirectorShell.vue';
import {
  FINANCE_CATEGORIES,
  financeCategoryHint,
  financeCategoryIcon,
  financeCategoryLabel as labelCategory,
  isWarehouseFinanceCategory,
  toRouteCategory,
} from '../../utils/finance';
import { formatMoney } from '../../utils/number';
import { t } from '../../i18n';

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
    const farmId = useDirectorFarmId();
    const loading = ref(true);
    const reportScope = ref('farm');
    const filterPondId = ref('');
    const ponds = ref([]);
    const farmOverview = ref(emptyOverview());
    const displayOverview = ref(emptyOverview());

    const closedPonds = computed(() => (ponds.value || []).filter((p) => p.is_closed));
    const closedPondFinanceRows = computed(() => (farmOverview.value.by_pond || [])
      .filter((row) => row.finance_locked || row.is_closed));

    const categoryTabs = computed(() => {
      const total = Math.max(Number(displayOverview.value.total_expenses) || 0, 0.0001);

      const fixed = FINANCE_CATEGORIES.map((category) => {
        const amount = Number(displayOverview.value.categories?.[category] || 0);
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
        };
      });

      const custom = (displayOverview.value.custom_sections || []).map((section) => {
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
      const total = Math.max(Number(displayOverview.value.total_expenses) || 0, 0.0001);
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
      const expense = Number(displayOverview.value.total_expenses) || 0;
      const sale = Number(displayOverview.value.total_sales) || 0;
      const base = expense + sale;
      if (base <= 0) return 0;
      return Math.round((expense / base) * 100);
    });

    const maxMoney = computed(() => Math.max(
      Number(displayOverview.value.total_expenses) || 0,
      Number(displayOverview.value.total_sales) || 0,
      Math.abs(Number(displayOverview.value.profit) || 0),
      1,
    ));

    function barH(value) {
      const n = Math.abs(Number(value) || 0);
      return `${Math.max((n / maxMoney.value) * 100, n > 0 ? 8 : 2)}%`;
    }

    const expenseBarH = computed(() => barH(displayOverview.value.total_expenses));
    const saleBarH = computed(() => barH(displayOverview.value.total_sales));
    const profitBarH = computed(() => barH(displayOverview.value.profit));

    const balanceStory = computed(() => {
      const profit = Number(displayOverview.value.profit) || 0;
      if (profit > 0) {
        return t('financeBalanceStoryProfit', { amount: formatMoney(profit) });
      }
      if (profit < 0) {
        return t('financeBalanceStoryLoss', { amount: formatMoney(Math.abs(profit)) });
      }
      return t('financeBalanceStoryZero');
    });

    async function loadFarmOverview() {
      const { data } = await window.axios.get(`/director/farms/${farmId.value}/finance/overview`);
      farmOverview.value = data;
      ponds.value = data.ponds || [];
      return data;
    }

    async function loadClosedAggregate(byPondRows) {
      const ids = byPondRows.map((row) => row.pond_id).filter(Boolean);
      if (!ids.length) {
        displayOverview.value = {
          ...emptyOverview(),
          by_pond: byPondRows,
        };
        return;
      }

      const responses = await Promise.all(
        ids.map((id) => window.axios.get(`/director/farms/${farmId.value}/finance/overview`, {
          params: { pond_id: id },
        })),
      );
      displayOverview.value = {
        ...mergeClosedOverviews(responses.map((res) => res.data), byPondRows),
        ponds: ponds.value,
      };
    }

    async function load() {
      loading.value = true;
      try {
        if (!farmId.value) {
          ponds.value = [];
          displayOverview.value = emptyOverview();
          return;
        }

        const farmData = await loadFarmOverview();
        const closedRows = (farmData.by_pond || []).filter((row) => row.finance_locked || row.is_closed);

        if (filterPondId.value) {
          const { data } = await window.axios.get(`/director/farms/${farmId.value}/finance/overview`, {
            params: { pond_id: filterPondId.value },
          });
          displayOverview.value = data;
        } else {
          await loadClosedAggregate(closedRows);
        }
      } catch (e) {
        ponds.value = [];
        displayOverview.value = emptyOverview();
      } finally {
        loading.value = false;
      }
    }

    async function selectPondContext(pondId) {
      if (pondId) {
        reportScope.value = 'pond';
        filterPondId.value = pondId;
      } else {
        reportScope.value = 'farm';
        filterPondId.value = '';
      }
      await load();
    }

    onMounted(load);

    return {
      loading,
      reportScope,
      filterPondId,
      closedPonds,
      closedPondFinanceRows,
      displayOverview,
      categoryTabs,
      expenseBreakdown,
      donutSegments,
      chartExpenseShare,
      expenseBarH,
      saleBarH,
      profitBarH,
      balanceStory,
      formatMoney,
      selectPondContext,
    };
  },
};
</script>
