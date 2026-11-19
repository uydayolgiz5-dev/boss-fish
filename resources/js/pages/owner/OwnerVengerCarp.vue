<template>
  <div class="owner-venger venger-readable ov">
    <OwnerVengerNav />

    <div class="page-head">
      <div>
        <h1>{{ $t('ownerVengerAnalyticsTitle') }}</h1>
        <p class="muted">{{ $t('ownerVengerOverviewHint') }}</p>
      </div>
      <router-link class="btn btn-outline btn-sm" :to="{ name: 'owner-venger-staff' }">
        {{ $t('vengerStaff') }}
      </router-link>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="error" class="panel empty">{{ error }}</div>

    <template v-else>
      <section class="ov-kpis">
        <router-link class="ov-kpi" :to="{ name: 'owner-venger-clients' }">
          <span>{{ $t('vengerFeedPlanCompareClients') }}</span>
          <strong>{{ number(stats.clients_count) }}</strong>
        </router-link>
        <div class="ov-kpi">
          <span>{{ $t('ownerVengerTotalArea') }}</span>
          <strong>{{ fmt(stats.area_ha ?? analytics.totals?.area_ha, 2) }}</strong>
          <small>ha</small>
        </div>
        <div class="ov-kpi">
          <span>{{ $t('ownerVengerFishStocked') }}</span>
          <strong>{{ number(stats.fish_stocked ?? analytics.totals?.fish_stocked) }}</strong>
          <small>{{ $t('pieces') }}</small>
        </div>
      </section>

      <section class="panel ov-year-panel">
        <div class="ov-year-panel__head">
          <div>
            <h2>{{ $t('ownerVengerYearlyTitle') }}</h2>
            <p class="field-hint">{{ $t('ownerVengerYearlyHint') }}</p>
          </div>
        </div>

        <div v-if="!yearRows.length" class="empty">{{ $t('ownerVengerNoChartData') }}</div>
        <div v-else class="table-scroll">
          <table class="ov-year-table">
            <thead>
              <tr>
                <th>{{ $t('vengerFeedPlanCompareYear') }}</th>
                <th class="num">{{ $t('ownerVengerFishStocked') }}</th>
                <th class="num">{{ $t('ownerVengerTotalArea') }}</th>
                <th class="num">{{ $t('vengerFeedExtruder') }}</th>
                <th class="num">{{ $t('vengerFeedGranula') }}</th>
                <th class="num">{{ $t('ownerVengerTotalFeed') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in yearRows" :key="row.year">
                <td><strong>{{ row.year }}</strong></td>
                <td class="num">{{ number(row.fish_stocked) }}</td>
                <td class="num">{{ fmt(row.area_ha, 2) }} ha</td>
                <td class="num">{{ fmtTon(row.extruder_kg) }}</td>
                <td class="num">{{ fmtTon(row.granula_kg) }}</td>
                <td class="num"><strong>{{ fmtTon(rowFeedKg(row)) }}</strong></td>
              </tr>
            </tbody>
            <tfoot v-if="yearRows.length > 1">
              <tr>
                <td><strong>{{ $t('total') }}</strong></td>
                <td class="num"><strong>{{ number(yearTotals.fish_stocked) }}</strong></td>
                <td class="num"><strong>{{ fmt(yearTotals.area_ha, 2) }} ha</strong></td>
                <td class="num"><strong>{{ fmtTon(yearTotals.extruder_kg) }}</strong></td>
                <td class="num"><strong>{{ fmtTon(yearTotals.granula_kg) }}</strong></td>
                <td class="num"><strong>{{ fmtTon(yearTotals.feed_kg) }}</strong></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>

      <section class="panel ov-region-panel">
        <h2>{{ $t('ownerVengerClientsByRegion') }}</h2>
        <div v-if="!regionBars.length" class="empty">{{ $t('ownerVengerNoChartData') }}</div>
        <div v-else class="ov-hbar-list">
          <div v-for="row in regionBars" :key="row.region" class="ov-hbar">
            <div class="ov-hbar__label">
              <strong>{{ row.region }}</strong>
              <span>{{ number(row.count) }}</span>
            </div>
            <div class="ov-hbar__track">
              <div class="ov-hbar__fill" :style="{ width: row.pct + '%' }" />
            </div>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<script>
import { computed, onMounted, ref } from 'vue';
import OwnerVengerNav from '../../components/OwnerVengerNav.vue';
import { t } from '../../i18n';

function withPct(rows, key = 'count') {
  const max = Math.max(...rows.map((r) => Number(r[key] || 0)), 1);
  return rows.map((r) => ({
    ...r,
    pct: Math.max(Math.round((Number(r[key] || 0) / max) * 100), Number(r[key]) > 0 ? 6 : 0),
  }));
}

export default {
  components: { OwnerVengerNav },
  setup() {
    const loading = ref(true);
    const error = ref('');
    const analytics = ref({
      clients_by_region: [],
      results_by_year: [],
      totals: {},
    });
    const stats = ref({
      clients_count: 0,
      area_ha: 0,
      fish_stocked: 0,
    });

    const regionBars = computed(() => withPct(analytics.value.clients_by_region || []));
    const yearRows = computed(() => [...(analytics.value.results_by_year || [])].reverse());
    const yearTotals = computed(() => {
      const rows = analytics.value.results_by_year || [];
      return rows.reduce(
        (acc, row) => {
          acc.fish_stocked += Number(row.fish_stocked || 0);
          acc.area_ha += Number(row.area_ha || 0);
          acc.extruder_kg += Number(row.extruder_kg || 0);
          acc.granula_kg += Number(row.granula_kg || 0);
          acc.feed_kg += rowFeedKg(row);
          return acc;
        },
        { fish_stocked: 0, area_ha: 0, extruder_kg: 0, granula_kg: 0, feed_kg: 0 },
      );
    });

    function rowFeedKg(row) {
      const feed = Number(row?.feed_kg || 0);
      if (feed > 0) return feed;
      return Number(row?.extruder_kg || 0) + Number(row?.granula_kg || 0);
    }

    function number(value) {
      const n = Number(value || 0);
      return Number.isFinite(n) ? n.toLocaleString() : '0';
    }

    function fmt(v, decimals = 0) {
      const n = Number(v);
      if (!Number.isFinite(n)) return '0';
      return n.toLocaleString(undefined, {
        maximumFractionDigits: decimals,
        minimumFractionDigits: decimals > 0 ? Math.min(decimals, 2) : 0,
      });
    }

    function fmtTon(kg) {
      const n = Number(kg || 0);
      if (!Number.isFinite(n) || n <= 0) return '0 t';
      return `${fmt(n / 1000, 2)} t`;
    }

    async function load() {
      loading.value = true;
      error.value = '';
      try {
        const { data } = await window.axios.get('/owner/venger');
        stats.value = data.stats || stats.value;
        analytics.value = data.analytics || analytics.value;
      } catch (e) {
        error.value = e.response?.data?.message || t('errorGeneric');
      } finally {
        loading.value = false;
      }
    }

    onMounted(load);

    return {
      loading,
      error,
      stats,
      analytics,
      regionBars,
      yearRows,
      yearTotals,
      rowFeedKg,
      number,
      fmt,
      fmtTon,
    };
  },
};
</script>

<style scoped>
.muted { color: #5b6b60; margin: 0.25rem 0 0; }
.ov-kpis {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.75rem;
  margin-bottom: 1rem;
}
.ov-kpi {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  min-height: 5.25rem;
  padding: 0.9rem 1rem;
  border-radius: 14px;
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.12);
  text-decoration: none;
  color: inherit;
}
.ov-kpi span { font-size: 0.8rem; color: #5b6b60; font-weight: 650; }
.ov-kpi strong { font-size: 1.45rem; font-variant-numeric: tabular-nums; line-height: 1.15; }
.ov-kpi small { color: #667085; font-size: 0.78rem; }
.ov-year-panel,
.ov-region-panel {
  margin-bottom: 1rem;
}
.ov-year-panel h2,
.ov-region-panel h2 {
  margin: 0 0 0.35rem;
  font-size: 1.05rem;
}
.ov-year-panel__head { margin-bottom: 0.75rem; }
.ov-year-table {
  width: 100%;
  min-width: 640px;
  border-collapse: collapse;
  border: 1.5px solid rgba(15, 23, 42, 0.28);
}
.ov-year-table th,
.ov-year-table td {
  border: 1px solid rgba(15, 23, 42, 0.2);
  padding: 0.65rem 0.7rem;
  vertical-align: middle;
}
.ov-year-table thead th {
  background: #f3f4f6;
  font-size: 0.74rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.ov-year-table tfoot td {
  background: #f8faf9;
  border-top: 2px solid rgba(15, 23, 42, 0.28);
}
.ov-year-table .num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}
.ov-hbar-list { display: grid; gap: 0.65rem; }
.ov-hbar__label {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.25rem;
  font-size: 0.88rem;
}
.ov-hbar__track {
  height: 8px;
  border-radius: 999px;
  background: #e8eee9;
  overflow: hidden;
}
.ov-hbar__fill {
  height: 100%;
  border-radius: inherit;
  background: #0d6b3f;
}
@media (max-width: 900px) {
  .ov-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 560px) {
  .ov-kpis { grid-template-columns: 1fr; }
}
</style>
