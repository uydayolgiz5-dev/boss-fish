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
          <small>{{ stats.latest_plan_year ? `${stats.latest_plan_year}` : $t('ownerVengerFromFeedPlan') }}</small>
        </div>
        <div class="ov-kpi ov-kpi--result">
          <span>{{ $t('ownerVengerResultFeed') }}</span>
          <strong>{{ fmtTon(stats.result_feed_kg ?? analytics.totals?.result_feed_kg) }}</strong>
          <small>{{ stats.latest_plan_year ? `${stats.latest_plan_year}` : $t('ownerVengerFromResults') }}</small>
        </div>
      </section>

      <section class="panel ov-flow-panel">
        <div class="ov-flow-panel__head">
          <h2>{{ $t('ownerVengerYearlyTitle') }}</h2>
          <p class="field-hint">{{ $t('ownerVengerYearlyHint') }}</p>
        </div>

        <div v-if="!yearRows.length" class="empty">{{ $t('ownerVengerNoChartData') }}</div>

        <div v-else class="ov-timeline">
          <article v-for="(row, idx) in yearRows" :key="row.year" class="ov-year-block">
            <div class="ov-scheme" :style="{ '--i': idx }">
              <!-- Start: circular year -->
              <div class="ov-node ov-node--start">
                <strong>{{ row.year }}</strong>
                <span>{{ $t('vengerFeedPlanCompareYear') }}</span>
              </div>

              <div class="ov-link" aria-hidden="true">
                <span class="ov-link__line" />
                <span class="ov-link__tip" />
              </div>

              <!-- Capsule steps -->
              <div class="ov-node ov-node--pill">
                <span>{{ $t('vengerFeedPlanCompareClients') }}</span>
                <strong>{{ number(row.clients_count) }}</strong>
              </div>

              <div class="ov-link" aria-hidden="true">
                <span class="ov-link__line" />
                <span class="ov-link__tip" />
              </div>

              <div class="ov-node ov-node--pill">
                <span>{{ $t('ownerVengerTotalArea') }}</span>
                <strong>{{ fmt(row.area_ha, 2) }} <em>ha</em></strong>
              </div>

              <div class="ov-link" aria-hidden="true">
                <span class="ov-link__line" />
                <span class="ov-link__tip" />
              </div>

              <div class="ov-node ov-node--pill ov-node--focus">
                <span>{{ $t('ownerVengerFishStocked') }}</span>
                <strong>{{ number(row.fish_stocked) }} <em>{{ $t('pieces') }}</em></strong>
              </div>

              <div class="ov-link" aria-hidden="true">
                <span class="ov-link__line" />
                <span class="ov-link__tip" />
              </div>

              <!-- Fork: plan feed composition -->
              <div class="ov-fork">
                <div class="ov-fork__rail" aria-hidden="true" />
                <div class="ov-fork__branches">
                  <div class="ov-node ov-node--pill ov-node--plan">
                    <span>{{ $t('vengerFeedExtruder') }}</span>
                    <strong>{{ fmtTon(row.plan_extruder_kg) }}</strong>
                  </div>
                  <div class="ov-node ov-node--pill ov-node--plan">
                    <span>{{ $t('vengerFeedGranula') }}</span>
                    <strong>{{ fmtTon(row.plan_granula_kg) }}</strong>
                  </div>
                </div>
                <div class="ov-fork__join" aria-hidden="true">
                  <span class="ov-fork__join-line" />
                  <span class="ov-link__tip" />
                </div>
              </div>

              <div class="ov-node ov-node--pill ov-node--plan-end">
                <span>{{ $t('ownerVengerPlanFeed') }}</span>
                <strong>{{ fmtTon(row.plan_feed_kg) }}</strong>
              </div>
            </div>

            <!-- Separate result branch (not mixed) -->
            <div class="ov-side">
              <div class="ov-side__cap">{{ $t('ownerVengerResultFeed') }}</div>
              <p class="ov-side__hint">{{ $t('ownerVengerResultFeedHint') }}</p>
              <div class="ov-side__nodes">
                <div class="ov-node ov-node--oval ov-node--result">
                  <span>{{ $t('vengerFeedExtruder') }}</span>
                  <strong>{{ fmtTon(row.result_extruder_kg) }}</strong>
                </div>
                <div class="ov-side__plus" aria-hidden="true">+</div>
                <div class="ov-node ov-node--oval ov-node--result">
                  <span>{{ $t('vengerFeedGranula') }}</span>
                  <strong>{{ fmtTon(row.result_granula_kg) }}</strong>
                </div>
                <div class="ov-side__eq" aria-hidden="true">=</div>
                <div class="ov-node ov-node--oval ov-node--result-end">
                  <span>{{ $t('total') }}</span>
                  <strong>{{ fmtTon(row.result_feed_kg) }}</strong>
                </div>
              </div>
              <div class="ov-bars">
                <div class="ov-bars__row">
                  <span>{{ $t('ownerVengerPlanFeed') }}</span>
                  <div class="ov-bars__track">
                    <i class="ov-bars__fill ov-bars__fill--plan" :style="{ width: barPct(row.plan_feed_kg, row) + '%' }" />
                  </div>
                  <b>{{ fmtTon(row.plan_feed_kg) }}</b>
                </div>
                <div class="ov-bars__row">
                  <span>{{ $t('ownerVengerResultFeed') }}</span>
                  <div class="ov-bars__track">
                    <i class="ov-bars__fill ov-bars__fill--result" :style="{ width: barPct(row.result_feed_kg, row) + '%' }" />
                  </div>
                  <b>{{ fmtTon(row.result_feed_kg) }}</b>
                </div>
              </div>
            </div>

            <div v-if="idx < yearRows.length - 1" class="ov-year-next" aria-hidden="true">
              <span class="ov-year-next__line" />
              <span class="ov-year-next__tip" />
            </div>
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

.ov-timeline { display: grid; gap: 1.25rem; }
.ov-year-block {
  padding: 1.15rem 1.1rem 1.2rem;
  border-radius: 22px;
  border: 1px solid rgba(15, 23, 42, 0.1);
  background:
    radial-gradient(1200px 280px at 0% 0%, rgba(13, 107, 63, 0.07), transparent 55%),
    linear-gradient(180deg, #fcfdfc 0%, #f3f6f4 100%);
  animation: ov-in 0.45s ease both;
  animation-delay: calc(var(--i, 0) * 0.05s);
}
@keyframes ov-in {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: none; }
}

/* ===== Algorithm scheme ===== */
.ov-scheme {
  --line: #94a3b8;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.15rem 0.2rem;
  margin-bottom: 1rem;
}

.ov-node {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: 0.15rem;
  padding: 0.55rem 1rem;
  background: #fff;
  border: 1.5px solid rgba(15, 23, 42, 0.14);
  box-shadow: 0 1px 0 rgba(15, 23, 42, 0.04);
}
.ov-node span {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #64748b;
  line-height: 1.2;
}
.ov-node strong {
  font-size: 1.05rem;
  font-variant-numeric: tabular-nums;
  line-height: 1.15;
  color: #0f172a;
}
.ov-node em {
  font-style: normal;
  font-size: 0.72rem;
  font-weight: 650;
  color: #64748b;
}

/* Dumaloq — yil (start) */
.ov-node--start {
  width: 5.4rem;
  height: 5.4rem;
  border-radius: 50%;
  background: radial-gradient(circle at 35% 30%, #1a8f55, #0d6b3f 70%);
  border-color: #0a5a34;
  color: #fff;
  flex-shrink: 0;
}
.ov-node--start span { color: rgba(255, 255, 255, 0.75); font-size: 0.62rem; }
.ov-node--start strong { color: #fff; font-size: 1.25rem; }

/* Uzunchoq kapsulalar — jarayon */
.ov-node--pill {
  min-width: 7.2rem;
  min-height: 3.6rem;
  border-radius: 999px;
  padding: 0.55rem 1.15rem;
}
.ov-node--focus {
  border-color: rgba(13, 107, 63, 0.45);
  background: #f0faf4;
}
.ov-node--plan {
  border-color: rgba(13, 107, 63, 0.3);
  background: #f3faf6;
}
.ov-node--plan-end {
  min-width: 8.2rem;
  border-radius: 999px;
  background: #0d6b3f;
  border-color: #0a5a34;
}
.ov-node--plan-end span { color: rgba(255, 255, 255, 0.78); }
.ov-node--plan-end strong { color: #fff; }

/* Dumaloq oval — natija tarmog‘i */
.ov-node--oval {
  min-width: 6.5rem;
  min-height: 3.4rem;
  border-radius: 50% / 45%;
  padding: 0.5rem 1rem;
}
.ov-node--result {
  border-color: rgba(217, 119, 6, 0.4);
  background: #fffaf3;
}
.ov-node--result span { color: #b45309; }
.ov-node--result-end {
  border-color: #b45309;
  background: linear-gradient(180deg, #f59e0b, #d97706);
}
.ov-node--result-end span { color: rgba(255, 255, 255, 0.85); }
.ov-node--result-end strong { color: #fff; }

/* Connectors */
.ov-link {
  display: flex;
  align-items: center;
  width: 1.6rem;
  flex-shrink: 0;
  position: relative;
}
.ov-link__line {
  display: block;
  width: 100%;
  height: 2px;
  background: var(--line);
  border-radius: 2px;
}
.ov-link__tip {
  position: absolute;
  right: -1px;
  width: 0;
  height: 0;
  border-top: 5px solid transparent;
  border-bottom: 5px solid transparent;
  border-left: 7px solid var(--line);
}

.ov-fork {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
  margin: 0 0.15rem;
}
.ov-fork__branches {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.45rem;
}
.ov-fork__join {
  position: relative;
  width: 1.6rem;
  height: 1rem;
  display: flex;
  align-items: center;
}
.ov-fork__join-line {
  display: block;
  width: 100%;
  height: 2px;
  background: var(--line);
}
.ov-fork__join .ov-link__tip {
  right: -1px;
}

/* Natija — alohida tarmoq */
.ov-side {
  margin-top: 0.25rem;
  padding: 0.9rem 1rem;
  border-radius: 18px;
  border: 1.5px dashed rgba(217, 119, 6, 0.45);
  background: rgba(255, 250, 243, 0.7);
}
.ov-side__cap {
  font-weight: 780;
  color: #9a3412;
  margin-bottom: 0.2rem;
}
.ov-side__hint {
  margin: 0 0 0.7rem;
  font-size: 0.78rem;
  color: #9a6a3a;
}
.ov-side__nodes {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem 0.55rem;
  margin-bottom: 0.85rem;
}
.ov-side__plus,
.ov-side__eq {
  font-weight: 800;
  color: #b45309;
  font-size: 1.05rem;
}

.ov-bars { display: grid; gap: 0.4rem; }
.ov-bars__row {
  display: grid;
  grid-template-columns: minmax(7rem, 10rem) 1fr auto;
  gap: 0.55rem;
  align-items: center;
  font-size: 0.8rem;
}
.ov-bars__row span { color: #5b4632; font-weight: 650; }
.ov-bars__row b { font-variant-numeric: tabular-nums; min-width: 4.2rem; text-align: right; }
.ov-bars__track {
  height: 9px;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.08);
  overflow: hidden;
}
.ov-bars__fill {
  display: block;
  height: 100%;
  border-radius: inherit;
}
.ov-bars__fill--plan { background: #0d6b3f; }
.ov-bars__fill--result { background: #d97706; }

.ov-year-next {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin: 0.85rem auto 0;
  height: 1.6rem;
  width: 2px;
  position: relative;
}
.ov-year-next__line {
  flex: 1;
  width: 2px;
  background: #94a3b8;
  border-radius: 2px;
}
.ov-year-next__tip {
  width: 0;
  height: 0;
  border-left: 6px solid transparent;
  border-right: 6px solid transparent;
  border-top: 8px solid #94a3b8;
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
@media (max-width: 900px) {
  .ov-scheme {
    flex-direction: column;
    align-items: stretch;
  }
  .ov-link {
    width: auto;
    height: 1.35rem;
    justify-content: center;
    align-self: center;
  }
  .ov-link__line {
    width: 2px;
    height: 100%;
  }
  .ov-link__tip {
    right: auto;
    bottom: -1px;
    top: auto;
    border-left: 5px solid transparent;
    border-right: 5px solid transparent;
    border-top: 7px solid var(--line);
    border-bottom: 0;
  }
  .ov-node--pill,
  .ov-node--plan-end,
  .ov-node--start {
    width: 100%;
    max-width: 22rem;
    align-self: center;
  }
  .ov-node--start {
    width: 5.4rem;
    height: 5.4rem;
  }
  .ov-fork { width: 100%; }
  .ov-fork__join {
    width: auto;
    height: 1.35rem;
  }
  .ov-fork__join-line {
    width: 2px;
    height: 100%;
    margin: 0 auto;
  }
  .ov-fork__join .ov-link__tip {
    left: 50%;
    right: auto;
    bottom: -1px;
    transform: translateX(-50%);
    border-left: 5px solid transparent;
    border-right: 5px solid transparent;
    border-top: 7px solid var(--line);
  }
  .ov-bars__row {
    grid-template-columns: 1fr;
    gap: 0.25rem;
  }
  .ov-bars__row b { text-align: left; }
}
@media (max-width: 720px) {
  .ov-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 480px) {
  .ov-kpis { grid-template-columns: 1fr; }
}
</style>
