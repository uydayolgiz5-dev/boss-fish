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
        <div class="ov-kpi ov-kpi--plan">
          <span>{{ $t('ownerVengerPlanFeed') }}</span>
          <strong>{{ fmtTon(stats.plan_feed_kg ?? analytics.totals?.plan_feed_kg) }}</strong>
          <small>{{ $t('ownerVengerFromFeedPlan') }}</small>
        </div>
        <div class="ov-kpi ov-kpi--result">
          <span>{{ $t('ownerVengerResultFeed') }}</span>
          <strong>{{ fmtTon(stats.result_feed_kg ?? analytics.totals?.result_feed_kg) }}</strong>
          <small>{{ $t('ownerVengerFromResults') }}</small>
        </div>
      </section>

      <section class="panel ov-flow-panel">
        <div class="ov-flow-panel__head">
          <h2>{{ $t('ownerVengerYearlyTitle') }}</h2>
          <p class="field-hint">{{ $t('ownerVengerYearlyHint') }}</p>
        </div>

        <div v-if="!yearRows.length" class="empty">{{ $t('ownerVengerNoChartData') }}</div>

        <div v-else class="ov-flow">
          <article v-for="(row, idx) in yearRows" :key="row.year" class="ov-year">
            <div class="ov-year__badge">{{ row.year }}</div>

            <div class="ov-algo">
              <div class="ov-algo__step">
                <span>{{ $t('vengerFeedPlanCompareClients') }}</span>
                <strong>{{ number(row.clients_count) }}</strong>
              </div>
              <div class="ov-algo__arrow" aria-hidden="true">→</div>
              <div class="ov-algo__step">
                <span>{{ $t('ownerVengerTotalArea') }}</span>
                <strong>{{ fmt(row.area_ha, 2) }} <small>ha</small></strong>
              </div>
              <div class="ov-algo__arrow" aria-hidden="true">→</div>
              <div class="ov-algo__step ov-algo__step--focus">
                <span>{{ $t('ownerVengerFishStocked') }}</span>
                <strong>{{ number(row.fish_stocked) }} <small>{{ $t('pieces') }}</small></strong>
              </div>
            </div>

            <div class="ov-algo ov-algo--feed">
              <div class="ov-algo__step ov-algo__step--plan">
                <span>{{ $t('vengerFeedExtruder') }}</span>
                <strong>{{ fmtTon(row.plan_extruder_kg) }}</strong>
              </div>
              <div class="ov-algo__plus" aria-hidden="true">+</div>
              <div class="ov-algo__step ov-algo__step--plan">
                <span>{{ $t('vengerFeedGranula') }}</span>
                <strong>{{ fmtTon(row.plan_granula_kg) }}</strong>
              </div>
              <div class="ov-algo__arrow" aria-hidden="true">=</div>
              <div class="ov-algo__step ov-algo__step--plan-total">
                <span>{{ $t('ownerVengerPlanFeed') }}</span>
                <strong>{{ fmtTon(row.plan_feed_kg) }}</strong>
              </div>
            </div>

            <div class="ov-result-line">
              <div class="ov-result-line__label">
                <span>{{ $t('ownerVengerResultFeed') }}</span>
                <em>{{ $t('ownerVengerResultFeedHint') }}</em>
              </div>
              <div class="ov-result-line__vals">
                <span>{{ $t('vengerFeedExtruder') }}: {{ fmtTon(row.result_extruder_kg) }}</span>
                <span>{{ $t('vengerFeedGranula') }}: {{ fmtTon(row.result_granula_kg) }}</span>
                <strong>{{ fmtTon(row.result_feed_kg) }}</strong>
              </div>
              <div class="ov-compare">
                <div class="ov-compare__track">
                  <div
                    class="ov-compare__plan"
                    :style="{ width: barPct(row.plan_feed_kg, row) + '%' }"
                    :title="$t('ownerVengerPlanFeed')"
                  />
                  <div
                    class="ov-compare__result"
                    :style="{ width: barPct(row.result_feed_kg, row) + '%' }"
                    :title="$t('ownerVengerResultFeed')"
                  />
                </div>
              </div>
            </div>

            <div v-if="idx < yearRows.length - 1" class="ov-flow__next" aria-hidden="true">↓</div>
          </article>
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
      years: [],
      totals: {},
    });
    const stats = ref({
      clients_count: 0,
      area_ha: 0,
      fish_stocked: 0,
      plan_feed_kg: 0,
      result_feed_kg: 0,
    });

    const regionBars = computed(() => withPct(analytics.value.clients_by_region || []));
    const yearRows = computed(() => [...(analytics.value.years || [])].reverse());

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

    function barPct(kg, row) {
      const max = Math.max(Number(row?.plan_feed_kg || 0), Number(row?.result_feed_kg || 0), 1);
      const n = Number(kg || 0);
      if (!Number.isFinite(n) || n <= 0) return 0;
      return Math.max(Math.round((n / max) * 100), 4);
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
      barPct,
    };
  },
};
</script>

<style scoped>
.muted { color: #5b6b60; margin: 0.25rem 0 0; }
.ov-kpis {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
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
.ov-kpi span { font-size: 0.78rem; color: #5b6b60; font-weight: 650; }
.ov-kpi strong { font-size: 1.35rem; font-variant-numeric: tabular-nums; line-height: 1.15; }
.ov-kpi small { color: #667085; font-size: 0.74rem; }
.ov-kpi--plan { border-color: rgba(13, 107, 63, 0.28); background: #f3faf6; }
.ov-kpi--result { border-color: rgba(180, 83, 9, 0.28); background: #fffaf3; }

.ov-flow-panel,
.ov-region-panel { margin-bottom: 1rem; }
.ov-flow-panel h2,
.ov-region-panel h2 {
  margin: 0 0 0.35rem;
  font-size: 1.05rem;
}
.ov-flow-panel__head { margin-bottom: 1rem; }

.ov-flow { display: grid; gap: 0.35rem; }
.ov-year {
  position: relative;
  padding: 1rem 1.05rem 1.1rem;
  border: 1.5px solid rgba(15, 23, 42, 0.14);
  border-radius: 16px;
  background: linear-gradient(180deg, #fbfcfb 0%, #f4f7f5 100%);
}
.ov-year__badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 4.2rem;
  margin-bottom: 0.85rem;
  padding: 0.3rem 0.7rem;
  border-radius: 999px;
  background: #0d6b3f;
  color: #fff;
  font-weight: 750;
  font-variant-numeric: tabular-nums;
  letter-spacing: 0.02em;
}

.ov-algo {
  display: flex;
  flex-wrap: wrap;
  align-items: stretch;
  gap: 0.45rem;
  margin-bottom: 0.75rem;
}
.ov-algo--feed { margin-bottom: 0.85rem; }
.ov-algo__step {
  flex: 1 1 7.5rem;
  min-width: 7rem;
  padding: 0.7rem 0.8rem;
  border-radius: 12px;
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.12);
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}
.ov-algo__step span {
  font-size: 0.72rem;
  color: #5b6b60;
  font-weight: 650;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.ov-algo__step strong {
  font-size: 1.12rem;
  font-variant-numeric: tabular-nums;
  line-height: 1.2;
}
.ov-algo__step small {
  font-size: 0.72rem;
  font-weight: 600;
  color: #667085;
}
.ov-algo__step--focus {
  border-color: rgba(13, 107, 63, 0.35);
  box-shadow: inset 0 0 0 1px rgba(13, 107, 63, 0.08);
}
.ov-algo__step--plan { background: #f3faf6; border-color: rgba(13, 107, 63, 0.2); }
.ov-algo__step--plan-total {
  background: #0d6b3f;
  border-color: #0d6b3f;
  color: #fff;
}
.ov-algo__step--plan-total span { color: rgba(255, 255, 255, 0.78); }
.ov-algo__step--plan-total strong { color: #fff; }
.ov-algo__arrow,
.ov-algo__plus {
  align-self: center;
  color: #667085;
  font-weight: 700;
  font-size: 1.05rem;
  padding: 0 0.1rem;
}

.ov-result-line {
  padding: 0.75rem 0.85rem;
  border-radius: 12px;
  border: 1px dashed rgba(180, 83, 9, 0.4);
  background: #fffaf3;
}
.ov-result-line__label {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.45rem 0.75rem;
  margin-bottom: 0.45rem;
}
.ov-result-line__label span {
  font-weight: 750;
  color: #9a3412;
}
.ov-result-line__label em {
  font-style: normal;
  font-size: 0.78rem;
  color: #9a6a3a;
}
.ov-result-line__vals {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem 1rem;
  align-items: baseline;
  font-variant-numeric: tabular-nums;
  font-size: 0.9rem;
  color: #5b4632;
  margin-bottom: 0.55rem;
}
.ov-result-line__vals strong {
  margin-left: auto;
  font-size: 1.05rem;
  color: #9a3412;
}
.ov-compare__track {
  display: grid;
  gap: 0.28rem;
}
.ov-compare__plan,
.ov-compare__result {
  height: 8px;
  border-radius: 999px;
  max-width: 100%;
}
.ov-compare__plan { background: #0d6b3f; }
.ov-compare__result { background: #d97706; }

.ov-flow__next {
  text-align: center;
  color: #94a3b8;
  font-size: 1.2rem;
  line-height: 1;
  margin: 0.15rem 0;
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

@media (max-width: 1100px) {
  .ov-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 720px) {
  .ov-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .ov-algo__arrow { display: none; }
  .ov-result-line__vals strong { margin-left: 0; }
}
@media (max-width: 480px) {
  .ov-kpis { grid-template-columns: 1fr; }
}
</style>
