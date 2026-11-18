<template>
  <div class="owner-venger venger-readable">
    <OwnerVengerNav />

    <div class="page-head">
      <div>
        <h1>{{ $t('ownerVengerAnalyticsTitle') }}</h1>
      </div>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="error" class="panel empty">{{ error }}</div>

    <template v-else>
      <section class="ov-section">
        <div class="ov-cards">
          <router-link class="ov-card ov-card--link" :to="{ name: 'owner-venger-clients' }">
            <span>{{ $t('vengerClientsTitle') }}</span>
            <strong>{{ number(stats.clients_count) }}</strong>
          </router-link>
          <router-link class="ov-card ov-card--link" :to="{ name: 'owner-venger-feed-plan' }">
            <span>{{ $t('vengerFeedPlanNav') }}</span>
            <strong>{{ $t('vengerFeedPlanCardAction') }}</strong>
          </router-link>
          <router-link class="ov-card ov-card--link ov-card--open" :to="{ name: 'owner-venger-ponds', query: { tab: 'open' } }">
            <span>{{ $t('vengerPondsTabOpen') }}</span>
            <strong>{{ number(stats.ponds_open) }}</strong>
          </router-link>
          <router-link class="ov-card ov-card--link ov-card--done" :to="{ name: 'owner-venger-ponds', query: { tab: 'final' } }">
            <span>{{ $t('vengerPondsTabDone') }}</span>
            <strong>{{ number(stats.ponds_final) }}</strong>
          </router-link>
          <router-link class="ov-card ov-card--link ov-card--rate" :to="{ name: 'owner-venger-results' }">
            <span>{{ $t('ownerVengerCompletionRate') }}</span>
            <strong>{{ avg.completion_rate != null ? `${fmt(avg.completion_rate, 1)}%` : '—' }}</strong>
          </router-link>
        </div>
      </section>

      <!-- 2-qator: o‘rtacha ko‘rsatkichlar — 2 ustun × 2 qator -->
      <section class="ov-section">
        <div class="ov-cards">
          <div class="ov-card">
            <span>{{ $t('ownerVengerAvgFcr') }}</span>
            <strong>{{ avg.fcr != null ? fmt(avg.fcr, 2) : '—' }}</strong>
          </div>
          <div class="ov-card">
            <span>{{ $t('ownerVengerAvgYield') }}</span>
            <strong>{{ avg.yield_per_ha_kg != null ? `${fmt(avg.yield_per_ha_kg, 0)}` : '—' }}</strong>
            <small>kg/ha</small>
          </div>
          <div class="ov-card">
            <span>{{ $t('ownerVengerAvgArea') }}</span>
            <strong>{{ avg.area_ha != null ? fmt(avg.area_ha, 2) : '—' }}</strong>
            <small>ha</small>
          </div>
          <div class="ov-card">
            <span>{{ $t('regions') }}</span>
            <strong>{{ number(analytics.totals?.regions_count) }}</strong>
          </div>
        </div>
      </section>

      <!-- Diagrammalar — har doim 2 ustun -->
      <div class="ov-pair">
        <section class="panel ov-panel">
          <div class="ov-panel__head">
            <h2>{{ $t('ownerVengerClientsByRegion') }}</h2>
          </div>
          <div v-if="!regionBars.length" class="empty">{{ $t('ownerVengerNoChartData') }}</div>
          <div v-else class="ov-hbar-list">
            <div v-for="row in regionBars" :key="row.region" class="ov-hbar">
              <div class="ov-hbar__label">
                <strong>{{ row.region }}</strong>
                <span>{{ number(row.count) }}</span>
              </div>
              <div class="ov-hbar__track">
                <div class="ov-hbar__fill ov-hbar__fill--region" :style="{ width: row.pct + '%' }" />
              </div>
            </div>
          </div>
        </section>

        <section class="panel ov-panel">
          <div class="ov-panel__head">
            <h2>{{ $t('ownerVengerResultsByYear') }}</h2>
          </div>
          <div v-if="!yearBars.length" class="empty">{{ $t('ownerVengerNoChartData') }}</div>
          <div v-else class="ov-year-chart">
            <div v-for="y in yearBars" :key="y.year" class="ov-year">
              <div class="ov-year__bars">
                <div class="ov-year__col" :title="$t('vengerPondsTabDone')">
                  <div class="ov-year__fill ov-year__fill--final" :style="{ height: y.finalPct + '%' }" />
                </div>
                <div class="ov-year__col" :title="$t('vengerPondsTabOpen')">
                  <div class="ov-year__fill ov-year__fill--open" :style="{ height: y.openPct + '%' }" />
                </div>
              </div>
              <strong>{{ y.year }}</strong>
              <small>{{ number(y.total) }}</small>
            </div>
          </div>
          <div v-if="yearBars.length" class="ov-year-legend">
            <span><i class="dot final" /> {{ $t('vengerPondsTabDone') }}</span>
            <span><i class="dot open" /> {{ $t('vengerPondsTabOpen') }}</span>
          </div>
        </section>
      </div>

      <div class="ov-pair">
        <section class="panel ov-panel">
          <div class="ov-panel__head ov-panel__head--row">
            <h2>{{ $t('ownerVengerTopAreas') }}</h2>
            <div class="ov-toggle" role="group" :aria-label="$t('ownerVengerTopAreas')">
              <button
                type="button"
                class="ov-toggle__btn"
                :class="{ 'is-active': geoMode === 'region' }"
                @click="geoMode = 'region'"
              >
                {{ $t('ownerVengerGeoRegion') }}
              </button>
              <button
                type="button"
                class="ov-toggle__btn"
                :class="{ 'is-active': geoMode === 'district' }"
                @click="geoMode = 'district'"
              >
                {{ $t('ownerVengerGeoDistrict') }}
              </button>
            </div>
          </div>
          <div v-if="!topAreaBars.length" class="empty">{{ $t('ownerVengerNoChartData') }}</div>
          <div v-else class="ov-hbar-list">
            <div
              v-for="row in topAreaBars"
              :key="geoMode === 'region' ? row.region : `${row.region}-${row.district}`"
              class="ov-hbar"
            >
              <div class="ov-hbar__label">
                <strong>{{ geoMode === 'region' ? row.region : row.district }}</strong>
                <span>{{ number(row.count) }}</span>
              </div>
              <em v-if="geoMode === 'district'" class="ov-hbar__sub">{{ row.region }}</em>
              <div class="ov-hbar__track">
                <div
                  class="ov-hbar__fill"
                  :class="geoMode === 'region' ? 'ov-hbar__fill--region' : 'ov-hbar__fill--district'"
                  :style="{ width: row.pct + '%' }"
                />
              </div>
            </div>
          </div>
        </section>

        <section class="panel ov-panel">
          <div class="ov-panel__head">
            <h2>{{ $t('ownerVengerFeedMix') }}</h2>
          </div>
          <div v-if="!feedMix.length" class="empty">{{ $t('ownerVengerNoChartData') }}</div>
          <div v-else class="ov-feed">
            <div v-for="f in feedMix" :key="f.type" class="ov-feed__item" :class="`is-${f.type}`">
              <div class="ov-feed__top">
                <strong>{{ f.label }}</strong>
                <em>{{ f.pct }}%</em>
              </div>
              <span>{{ fmt(f.kg, 0) }} kg</span>
              <div class="ov-feed__bar"><div :style="{ width: f.pct + '%' }" /></div>
            </div>
          </div>
          <div class="ov-cards ov-cards--tight">
            <div class="ov-card ov-card--mini">
              <span>{{ $t('vengerFeedExtruder') }}</span>
              <strong>{{ fmt(analytics.totals?.extruder_kg, 0) }} kg</strong>
            </div>
            <div class="ov-card ov-card--mini">
              <span>{{ $t('vengerFeedGranula') }}</span>
              <strong>{{ fmt(analytics.totals?.granula_kg, 0) }} kg</strong>
            </div>
            <div class="ov-card ov-card--mini ov-card--span">
              <span>{{ $t('ownerVengerTotalFeed') }}</span>
              <strong>{{ fmt(analytics.totals?.feed_kg, 0) }} kg</strong>
            </div>
          </div>
        </section>
      </div>

      <!-- Hodimlar + faollik — 2 ustun -->
      <div class="ov-pair">
        <section class="panel ov-panel">
          <div class="ov-panel__head ov-panel__head--row">
            <h2>{{ $t('ownerVengerStaffWork') }}</h2>
            <router-link class="btn btn-outline btn-sm" :to="{ name: 'owner-venger-staff' }">
              {{ $t('vengerStaff') }}
            </router-link>
          </div>
          <div class="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>{{ $t('name') }}</th>
                  <th>{{ $t('vengerClientsTitle') }}</th>
                  <th>{{ $t('vengerPondsTabOpen') }}</th>
                  <th>{{ $t('vengerPondsTabDone') }}</th>
                  <th>{{ $t('status') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="s in staff" :key="s.id">
                  <td>
                    <strong>{{ s.name }}</strong>
                    <div class="mono muted">{{ s.login || s.email || '—' }}</div>
                  </td>
                  <td>{{ number(s.clients_count) }}</td>
                  <td>{{ number(s.open_count) }}</td>
                  <td>{{ number(s.final_count) }}</td>
                  <td>
                    <span class="badge" :class="s.is_active ? 'active' : 'inactive'">
                      {{ s.is_active ? $t('active') : $t('inactive') }}
                    </span>
                  </td>
                </tr>
                <tr v-if="!staff.length">
                  <td colspan="5" class="empty">{{ $t('noVengerStaff') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="panel ov-panel">
          <div class="ov-panel__head ov-panel__head--row">
            <h2>{{ $t('ownerVengerActivity') }}</h2>
            <router-link class="btn btn-outline btn-sm" :to="{ name: 'owner-venger-clients' }">
              {{ $t('vengerClientsTitle') }}
            </router-link>
          </div>
          <div class="ov-activity">
            <article v-for="c in recentClients" :key="`c-${c.id}`" class="ov-activity__row">
              <div>
                <strong>{{ c.name }}</strong>
                <p>
                  {{ [c.region, c.district].filter(Boolean).join(' · ') || '—' }}
                  <span v-if="c.staff"> · {{ c.staff.name }}</span>
                </p>
              </div>
              <div class="ov-activity__meta">
                <span class="badge inactive">{{ number(c.results_count) }}</span>
                <router-link class="btn btn-outline btn-sm" :to="{ name: 'owner-venger-client', params: { clientId: c.id } }">
                  {{ $t('open') }}
                </router-link>
              </div>
            </article>
            <article v-for="a in activity" :key="`a-${a.id}`" class="ov-activity__row">
              <div>
                <strong>{{ a.title }}</strong>
                <p>
                  {{ a.client?.name || '—' }}
                  <span v-if="a.staff"> · {{ a.staff.name }}</span>
                </p>
              </div>
              <div class="ov-activity__meta">
                <span class="badge" :class="a.status === 'final' ? 'active' : 'inactive'">
                  {{ a.status === 'final' ? $t('vengerPondConfirmed') : $t('vengerPondsTabOpen') }}
                </span>
                <router-link class="btn btn-outline btn-sm" :to="{ name: 'owner-venger-result', params: { resultId: a.id } }">
                  {{ $t('open') }}
                </router-link>
              </div>
            </article>
            <div v-if="!recentClients.length && !activity.length" class="empty">{{ $t('ownerVengerActivityEmpty') }}</div>
          </div>
        </section>
      </div>
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
    const staff = ref([]);
    const activity = ref([]);
    const recentClients = ref([]);
    const analytics = ref({
      clients_by_region: [],
      clients_by_district: [],
      results_by_year: [],
      feed_types: [],
      averages: {},
      totals: {},
    });
    const stats = ref({
      staff_count: 0,
      active_count: 0,
      clients_count: 0,
      ponds_open: 0,
      ponds_final: 0,
      ponds_total: 0,
    });
    const geoMode = ref('region');

    const avg = computed(() => analytics.value.averages || {});
    const regionBars = computed(() => withPct(analytics.value.clients_by_region || []));
    const topAreaBars = computed(() => {
      if (geoMode.value === 'district') {
        return withPct(analytics.value.clients_by_district || []);
      }
      return withPct(analytics.value.clients_by_region || []);
    });
    const yearBars = computed(() => {
      const rows = analytics.value.results_by_year || [];
      const max = Math.max(...rows.map((r) => Number(r.total || 0)), 1);
      return rows.map((r) => ({
        ...r,
        openPct: Math.max(Math.round((Number(r.open || 0) / max) * 100), r.open > 0 ? 8 : 0),
        finalPct: Math.max(Math.round((Number(r.final || 0) / max) * 100), r.final > 0 ? 8 : 0),
      }));
    });
    const feedMix = computed(() => {
      const rows = (analytics.value.feed_types || []).filter((f) => Number(f.kg || 0) > 0);
      const total = rows.reduce((s, r) => s + Number(r.kg || 0), 0) || 1;
      return rows.map((r) => ({
        ...r,
        pct: Math.round((Number(r.kg || 0) / total) * 100),
      }));
    });

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

    async function load() {
      loading.value = true;
      error.value = '';
      try {
        const { data } = await window.axios.get('/owner/venger');
        staff.value = data.staff || [];
        activity.value = data.recent_activity || [];
        recentClients.value = data.recent_clients || [];
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
      loading, error, staff, activity, recentClients, stats, analytics, avg,
      geoMode, regionBars, topAreaBars, yearBars, feedMix,
      number, fmt,
    };
  },
};
</script>

<style scoped>
.ov-section {
  margin-bottom: 1rem;
}

/* Standart: 2 ustunli kartalar */
.ov-cards {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
}
.ov-cards--tight {
  margin-top: 0.85rem;
}
.ov-card {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.2rem;
  min-height: 5.5rem;
  padding: 1rem 1.05rem;
  border-radius: 16px;
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
  color: inherit;
  text-decoration: none;
}
.ov-card--link {
  transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}
.ov-card--link:hover {
  transform: translateY(-2px);
  border-color: rgba(13, 107, 63, 0.28);
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
}
.ov-card span {
  font-size: 0.82rem;
  font-weight: 700;
  color: #3a5548;
}
.ov-card strong {
  font-size: clamp(1.45rem, 2.8vw, 1.85rem);
  font-weight: 850;
  letter-spacing: -0.02em;
  color: #0f172a;
  line-height: 1.1;
}
.ov-card small {
  margin-top: auto;
  font-size: 0.78rem;
  font-weight: 650;
  color: #475569;
}
.ov-card--open { background: linear-gradient(160deg, #fff7ed 0%, #fff 72%); }
.ov-card--open strong { color: #9a3412; }
.ov-card--done { background: linear-gradient(160deg, #ecfdf5 0%, #fff 72%); }
.ov-card--done strong { color: #047857; }
.ov-card--rate { background: linear-gradient(160deg, #eff6ff 0%, #fff 72%); }
.ov-card--rate strong { color: #1d4ed8; }
.ov-card--mini {
  min-height: 0;
  padding: 0.7rem 0.8rem;
}
.ov-card--mini strong { font-size: 1.05rem; }
.ov-card--span { grid-column: 1 / -1; }

/* Juft panellar — 2 ustun */
.ov-pair {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.85rem;
  margin-bottom: 1rem;
  align-items: start;
}
.ov-panel__head {
  margin-bottom: 0.85rem;
}
.ov-panel__head h2 {
  margin: 0 0 0.2rem;
  font-size: 1.05rem;
  font-weight: 850;
  color: #0f172a;
}
.ov-panel__head p {
  margin: 0;
  color: #475569;
  font-size: 0.84rem;
}
.ov-panel__head--row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.65rem;
  align-items: flex-start;
}

.ov-toggle {
  display: inline-flex;
  gap: 0.2rem;
  padding: 0.2rem;
  border-radius: 10px;
  background: #eef2f0;
}
.ov-toggle__btn {
  border: 0;
  background: transparent;
  color: #3d5a4a;
  font: inherit;
  font-size: 0.82rem;
  font-weight: 650;
  padding: 0.35rem 0.75rem;
  border-radius: 8px;
  cursor: pointer;
}
.ov-toggle__btn.is-active {
  background: #0d6b3f;
  color: #fff;
}

.ov-hbar-list { display: grid; gap: 0.55rem; }
.ov-hbar__label {
  display: flex;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.2rem;
  font-size: 0.88rem;
}
.ov-hbar__sub {
  display: block;
  margin: -0.1rem 0 0.2rem;
  font-style: normal;
  font-size: 0.72rem;
  color: #5b7266;
  font-weight: 600;
}
.ov-hbar__track {
  height: 10px;
  border-radius: 999px;
  background: #e8efea;
  overflow: hidden;
}
.ov-hbar__fill {
  height: 100%;
  border-radius: 999px;
  min-width: 4px;
}
.ov-hbar__fill--region { background: linear-gradient(90deg, #0d8a52, #1fd18a); }
.ov-hbar__fill--district { background: linear-gradient(90deg, #1a6fb5, #5eb0ef); }

.ov-year-chart {
  display: flex;
  align-items: flex-end;
  gap: 0.85rem;
  min-height: 180px;
  padding: 0.5rem 0.25rem 0;
  overflow-x: auto;
}
.ov-year {
  display: grid;
  justify-items: center;
  gap: 0.25rem;
  min-width: 52px;
}
.ov-year__bars {
  display: flex;
  align-items: flex-end;
  gap: 4px;
  height: 140px;
}
.ov-year__col {
  width: 16px;
  height: 100%;
  border-radius: 8px;
  background: #eef3ef;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
}
.ov-year__fill {
  width: 100%;
  border-radius: 8px 8px 0 0;
  min-height: 4px;
}
.ov-year__fill--final { background: linear-gradient(180deg, #2fd887, #0d6b3f); }
.ov-year__fill--open { background: linear-gradient(180deg, #f0b429, #c47a12); }
.ov-year strong { font-size: 0.82rem; }
.ov-year small { color: #5b7266; font-weight: 700; }
.ov-year-legend {
  display: flex;
  gap: 1rem;
  margin-top: 0.65rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: #5b7266;
}
.ov-year-legend .dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 3px;
  margin-right: 0.3rem;
}
.ov-year-legend .dot.final { background: #0d6b3f; }
.ov-year-legend .dot.open { background: #c47a12; }

.ov-feed { display: grid; gap: 0.65rem; }
.ov-feed__item {
  padding: 0.7rem 0.8rem;
  border-radius: 12px;
  background: #f8faf9;
  border: 1px solid rgba(15, 23, 42, 0.06);
}
.ov-feed__top {
  display: flex;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.15rem;
}
.ov-feed__top em {
  font-style: normal;
  font-size: 0.78rem;
  font-weight: 700;
  color: #5b7266;
}
.ov-feed__item > span {
  font-size: 1.15rem;
  font-weight: 800;
}
.ov-feed__bar {
  margin-top: 0.4rem;
  height: 8px;
  border-radius: 999px;
  background: #e8efea;
  overflow: hidden;
}
.ov-feed__bar > div { height: 100%; border-radius: 999px; }
.ov-feed__item.is-extruder .ov-feed__bar > div {
  background: linear-gradient(90deg, #f0b429, #c47a12);
}
.ov-feed__item.is-granula .ov-feed__bar > div {
  background: linear-gradient(90deg, #1a6fb5, #5eb0ef);
}

.muted { color: #5b7266; font-size: 0.78rem; margin-top: 0.15rem; }

.ov-activity { display: grid; gap: 0.55rem; }
.ov-activity__row {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  align-items: center;
  padding: 0.7rem 0.75rem;
  border-radius: 12px;
  background: #f8faf9;
  border: 1px solid rgba(15, 23, 42, 0.06);
}
.ov-activity__row p { margin: 0.15rem 0 0; font-size: 0.84rem; color: #5b7266; }
.ov-activity__meta { display: grid; gap: 0.35rem; justify-items: end; }

@media (max-width: 900px) {
  .ov-pair { grid-template-columns: 1fr; }
}
@media (max-width: 560px) {
  .ov-cards { grid-template-columns: 1fr; }
  .ov-card--span { grid-column: auto; }
}
</style>
