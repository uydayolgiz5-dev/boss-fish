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
          <span>{{ $t('vengerClientsTitle') }}</span>
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
        <router-link class="ov-kpi ov-kpi--open" :to="{ name: 'owner-venger-ponds', query: { tab: 'open' } }">
          <span>{{ $t('vengerPondsTabOpen') }}</span>
          <strong>{{ number(stats.ponds_open) }}</strong>
        </router-link>
        <router-link class="ov-kpi ov-kpi--done" :to="{ name: 'owner-venger-ponds', query: { tab: 'final' } }">
          <span>{{ $t('vengerPondsTabDone') }}</span>
          <strong>{{ number(stats.ponds_final) }}</strong>
        </router-link>
        <router-link class="ov-kpi" :to="{ name: 'owner-venger-feed-plan' }">
          <span>{{ $t('vengerFeedPlanNav') }}</span>
          <strong>{{ $t('vengerFeedPlanCardAction') }}</strong>
        </router-link>
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
                <th class="num">{{ $t('vengerResultsTitle') }}</th>
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
                <td class="num">
                  {{ number(row.total) }}
                  <small class="ov-sub">{{ number(row.open) }} / {{ number(row.final) }}</small>
                </td>
                <td class="num">{{ number(row.fish_stocked) }}</td>
                <td class="num">{{ fmt(row.area_ha, 2) }} ha</td>
                <td class="num">{{ fmtTon(row.extruder_kg) }}</td>
                <td class="num">{{ fmtTon(row.granula_kg) }}</td>
                <td class="num"><strong>{{ fmtTon(row.feed_kg || ((row.extruder_kg || 0) + (row.granula_kg || 0))) }}</strong></td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="yearRows.length" class="ov-legend">
          {{ $t('vengerPondsTabOpen') }} / {{ $t('vengerPondsTabDone') }}
        </p>
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
      ponds_open: 0,
      ponds_final: 0,
      area_ha: 0,
      fish_stocked: 0,
    });

    const regionBars = computed(() => withPct(analytics.value.clients_by_region || []));
    const yearRows = computed(() => [...(analytics.value.results_by_year || [])].reverse());

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
.ov-kpi--open { border-color: rgba(13, 107, 63, 0.28); background: #f3faf6; }
.ov-kpi--done { border-color: rgba(21, 101, 192, 0.22); background: #f4f8fc; }
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
  min-width: 720px;
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
.ov-year-table .num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}
.ov-sub {
  display: block;
  margin-top: 0.15rem;
  color: #667085;
  font-size: 0.75rem;
}
.ov-legend {
  margin: 0.65rem 0 0;
  color: #667085;
  font-size: 0.8rem;
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
