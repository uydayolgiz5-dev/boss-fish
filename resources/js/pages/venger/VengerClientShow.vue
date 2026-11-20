<template>
  <div class="venger-client-show venger-readable vcd">
    <OwnerVengerNav v-if="isOwnerView" />

    <div class="page-head">
      <div>
        <h1>{{ client?.name || $t('vengerClient') }}</h1>
        <p v-if="client" class="vcd-sub">
          {{ placeLine }}
          <template v-if="client.phone"> · {{ formatPhone(client.phone) }}</template>
          <template v-if="client.phone2"> · {{ formatPhone(client.phone2) }}</template>
        </p>
        <p v-else>{{ $t('vengerClientWorkHint') }}</p>
      </div>
      <div class="page-head-actions">
        <router-link class="btn btn-outline btn-sm" :to="backTo">{{ $t('back') }}</router-link>
        <router-link v-if="client" class="btn btn-primary btn-sm" :to="newResultRoute">
          {{ $t('vengerNewResult') }}
        </router-link>
      </div>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="error" class="panel empty">{{ error }}</div>

    <template v-else-if="client">
      <!-- Portfolio KPIs — aquaManager / Aquanetix style -->
      <section class="vcd-kpis">
        <div class="vcd-kpi">
          <span>{{ $t('vengerPondAreaHa') }}</span>
          <strong>{{ fmtHa(summary.pond_area_ha) }}</strong>
        </div>
        <div class="vcd-kpi">
          <span>{{ $t('ownerVengerPlanFeed') }}</span>
          <strong>{{ fmtTon(summary.plan_feed_kg) }}</strong>
        </div>
        <div class="vcd-kpi">
          <span>{{ $t('ownerVengerResultFeed') }}</span>
          <strong>{{ fmtTon(summary.result_feed_kg) }}</strong>
        </div>
        <div class="vcd-kpi vcd-kpi--focus">
          <span>{{ $t('vengerAvgFcr') }}</span>
          <strong>{{ summary.avg_fcr != null ? number(summary.avg_fcr, 2) : '—' }}</strong>
        </div>
      </section>

      <p v-if="!areaOk" class="vcd-area-nudge panel">
        <span>{{ $t('vengerPondAreaHaHint') }}</span>
        <span class="vcd-area-nudge__row">
          <FormattedNumberInput v-model="areaDraft" :max-decimals="3" />
          <button class="btn btn-primary btn-sm" type="button" :disabled="savingArea" @click="saveArea">
            {{ savingArea ? $t('saving') : $t('save') }}
          </button>
        </span>
        <span v-if="areaError" class="error">{{ areaError }}</span>
      </p>

      <!-- Production cycles -->
      <section class="vcd-cycles">
        <div class="vcd-cycles__head">
          <h2>{{ $t('vengerResultsTitle') }}</h2>
          <p>{{ $t('vengerClientBoardHint') }}</p>
        </div>

        <p v-if="!(client.results || []).length" class="panel empty">{{ $t('noVengerResults') }}</p>

        <article v-for="r in client.results || []" :key="r.id" class="vcd-cycle">
          <div class="vcd-cycle__meta">
            <div class="vcd-season">{{ seasonLabel(r) }}</div>
            <span class="vcd-badge" :class="r.status === 'final' ? 'is-final' : 'is-open'">
              {{ phaseLabel(r) }}
            </span>
            <p v-if="r.title" class="vcd-cycle__title">{{ r.title }}</p>
          </div>

          <div class="vcd-metrics">
            <div>
              <span>{{ $t('areaHa') }}</span>
              <strong>{{ number(r.area_ha, 2) }} <small>ha</small></strong>
            </div>
            <div>
              <span>{{ $t('ownerVengerResultFeed') }}</span>
              <strong>{{ fmtTon(resultFeed(r)) }}</strong>
              <em v-if="feedSplit(r)">{{ feedSplit(r) }}</em>
            </div>
            <div>
              <span>{{ $t('weightGain') }}</span>
              <strong>{{ fmtKg(gainKg(r)) }}</strong>
            </div>
            <div class="vcd-metrics__fcr" :class="fcrTone(r)">
              <span>{{ $t('fcr') }}</span>
              <strong>{{ r.metrics?.fcr != null ? number(r.metrics.fcr, 2) : '—' }}</strong>
              <em v-if="r.metrics?.fcr != null">{{ $t('vengerFcrMeaning', { fcr: number(r.metrics.fcr, 2) }) }}</em>
            </div>
          </div>

          <div v-if="planFeed(r) > 0 || resultFeed(r) > 0" class="vcd-vs">
            <div class="vcd-vs__row">
              <span>{{ $t('ownerVengerPlanFeed') }}</span>
              <div class="vcd-vs__track"><i class="is-plan" :style="{ width: barPct(planFeed(r), r) + '%' }" /></div>
              <b>{{ fmtTon(planFeed(r)) }}</b>
            </div>
            <div class="vcd-vs__row">
              <span>{{ $t('ownerVengerResultFeed') }}</span>
              <div class="vcd-vs__track"><i class="is-result" :style="{ width: barPct(resultFeed(r), r) + '%' }" /></div>
              <b>{{ fmtTon(resultFeed(r)) }}</b>
            </div>
          </div>

          <div class="vcd-cycle__actions">
            <router-link class="btn btn-primary btn-sm" :to="resultRoute(r.id)">{{ $t('vengerGoodNewsBtn') }}</router-link>
            <router-link class="btn btn-outline btn-sm" :to="resultRoute(r.id)">{{ $t('viewResult') }}</router-link>
            <router-link class="btn btn-outline btn-sm" :to="editRoute(r.id)">
              {{ r.status !== 'final' ? $t('vengerContinueResult') : $t('edit') }}
            </router-link>
          </div>
        </article>
      </section>
    </template>
  </div>
</template>

<script>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import FormattedNumberInput from '../../components/FormattedNumberInput.vue';
import OwnerVengerNav from '../../components/OwnerVengerNav.vue';
import auth from '../../auth';
import { formatPhoneDisplay } from '../../utils/phone';
import { VENGER_FEED_TYPES, normalizeFeedType } from '../../utils/vengerFeeds';
import { t } from '../../i18n';

export default {
  components: { FormattedNumberInput, OwnerVengerNav },
  setup() {
    const route = useRoute();
    const loading = ref(true);
    const error = ref('');
    const client = ref(null);
    const areaDraft = ref('');
    const savingArea = ref(false);
    const areaError = ref('');
    const isOwnerView = computed(() => auth.isOwner());
    const apiBase = computed(() => (auth.isOwner() ? '/owner/venger' : '/venger'));

    const backTo = computed(() => {
      if (auth.isOwner()) {
        return route.query.from === 'results'
          ? { name: 'owner-venger-ponds' }
          : { name: 'owner-venger-clients' };
      }
      return route.query.from === 'results'
        ? { name: 'venger-results-hub' }
        : { name: 'venger-cabinet' };
    });

    const newResultRoute = computed(() => (
      auth.isOwner()
        ? { name: 'owner-venger-result-new', params: { clientId: route.params.clientId } }
        : { name: 'venger-result-new', params: { clientId: route.params.clientId } }
    ));

    const placeLine = computed(() => {
      const c = client.value;
      if (!c) return '';
      return [c.region, c.district].filter(Boolean).join(' · ') || (c.address || '');
    });

    const summary = computed(() => client.value?.summary || {
      pond_area_ha: client.value?.pond_area_ha ?? 0,
      result_feed_kg: 0,
      weight_gain_kg: 0,
      avg_fcr: null,
    });

    const areaOk = computed(() => Number(summary.value.pond_area_ha) > 0);

    watch(client, (c) => {
      areaDraft.value = c?.pond_area_ha != null && c.pond_area_ha !== '' ? String(c.pond_area_ha) : '';
    });

    function number(v, decimals = 0) {
      const n = Number(v);
      if (!Number.isFinite(n)) return '0';
      return n.toLocaleString(undefined, {
        maximumFractionDigits: decimals,
        minimumFractionDigits: decimals > 0 ? Math.min(decimals, 2) : 0,
      });
    }

    function fmtHa(v) {
      const n = Number(v);
      if (!Number.isFinite(n) || n <= 0) return '—';
      return `${number(n, 2)} ha`;
    }

    function fmtTon(kg) {
      const n = Number(kg || 0);
      if (!Number.isFinite(n) || n <= 0) return '0 t';
      return `${number(n / 1000, 2)} t`;
    }

    function fmtKg(kg) {
      const n = Number(kg || 0);
      if (!Number.isFinite(n) || n <= 0) return '—';
      return `${number(n)} kg`;
    }

    function formatPhone(phone) {
      return formatPhoneDisplay(phone || '');
    }

    function yearOf(value) {
      if (!value) return null;
      const d = new Date(value);
      return Number.isNaN(d.getTime()) ? null : d.getFullYear();
    }

    function seasonLabel(r) {
      if (r?.season) return r.season;
      const start = yearOf(r?.stocked_on) || yearOf(r?.sold_on);
      if (!start) return '—';
      return `${start}/${start + 1}`;
    }

    function phaseLabel(r) {
      if (r.status === 'final') return t('vengerPondConfirmed');
      if (r.metrics?.phase === 'mid') return t('vengerPondInProgress');
      return t('vengerPondStarted');
    }

    function resultFeed(r) {
      const n = Number(r?.result_feed_kg ?? r?.feed_kg ?? 0);
      return Number.isFinite(n) ? n : 0;
    }

    function planFeed(r) {
      const n = Number(r?.plan?.plan_feed_kg ?? 0);
      return Number.isFinite(n) ? n : 0;
    }

    function gainKg(r) {
      const n = Number(r?.metrics?.sazan?.biomass_gain_kg ?? r?.metrics?.weight_gain_kg ?? 0);
      return Number.isFinite(n) ? n : 0;
    }

    function feedSplit(r) {
      const e = Number(r?.result_extruder_kg || 0);
      const g = Number(r?.result_granula_kg || 0);
      if (e > 0 || g > 0) {
        const parts = [];
        if (e > 0) parts.push(`${t('vengerFeedExtruder')} ${fmtTon(e)}`);
        if (g > 0) parts.push(`${t('vengerFeedGranula')} ${fmtTon(g)}`);
        return parts.join(' · ');
      }
      const feeds = (r.feeds || []).filter((f) => f?.type && Number(f.kg) > 0);
      if (!feeds.length) return '';
      return feeds.map((f) => {
        const type = normalizeFeedType(f.type) || f.type;
        const meta = VENGER_FEED_TYPES.find((x) => x.id === type);
        return meta ? t(meta.labelKey) : type;
      }).join(' · ');
    }

    function barPct(kg, r) {
      const max = Math.max(planFeed(r), resultFeed(r), 1);
      const n = Number(kg || 0);
      if (!Number.isFinite(n) || n <= 0) return 0;
      return Math.max(Math.round((n / max) * 100), 3);
    }

    function fcrTone(r) {
      const f = Number(r?.metrics?.fcr);
      if (!Number.isFinite(f)) return '';
      if (f <= 1.5) return 'is-good';
      if (f <= 2.2) return 'is-ok';
      return 'is-warn';
    }

    function resultRoute(id) {
      return auth.isOwner()
        ? { name: 'owner-venger-result', params: { resultId: id } }
        : { name: 'venger-result', params: { resultId: id } };
    }

    function editRoute(id) {
      return auth.isOwner()
        ? { name: 'owner-venger-result-edit', params: { resultId: id } }
        : { name: 'venger-result-edit', params: { resultId: id } };
    }

    async function load() {
      loading.value = true;
      error.value = '';
      try {
        const { data } = await window.axios.get(`${apiBase.value}/clients/${route.params.clientId}`);
        client.value = data;
      } catch (e) {
        error.value = e.response?.data?.message || t('errorGeneric');
      } finally {
        loading.value = false;
      }
    }

    async function saveArea() {
      areaError.value = '';
      const pondArea = Number(areaDraft.value);
      if (!Number.isFinite(pondArea) || pondArea <= 0) {
        areaError.value = t('vengerPondAreaRequired');
        return;
      }
      const c = client.value;
      if (!c) return;
      savingArea.value = true;
      try {
        const { data } = await window.axios.put(`${apiBase.value}/clients/${c.id}`, {
          first_name: c.first_name || c.name?.split(/\s+/)[0] || c.name,
          last_name: c.last_name || c.name?.split(/\s+/).slice(1).join(' ') || '-',
          address: c.address || null,
          region: c.region,
          district: c.district,
          pond_area_ha: pondArea,
          phone: c.phone,
          phone2: c.phone2 || null,
          notes: c.notes || null,
        });
        client.value = {
          ...c,
          ...data,
          results: c.results,
          summary: {
            ...(c.summary || {}),
            pond_area_ha: data.pond_area_ha,
          },
          feed_plans: c.feed_plans,
        };
      } catch (e) {
        const errors = e.response?.data?.errors;
        areaError.value = errors
          ? Object.values(errors).flat().join(' ')
          : (e.response?.data?.message || t('errorGeneric'));
      } finally {
        savingArea.value = false;
      }
    }

    onMounted(load);

    return {
      loading,
      error,
      client,
      summary,
      areaOk,
      areaDraft,
      savingArea,
      areaError,
      isOwnerView,
      placeLine,
      backTo,
      newResultRoute,
      number,
      fmtHa,
      fmtTon,
      fmtKg,
      formatPhone,
      seasonLabel,
      phaseLabel,
      resultFeed,
      planFeed,
      gainKg,
      feedSplit,
      barPct,
      fcrTone,
      resultRoute,
      editRoute,
      saveArea,
    };
  },
};
</script>

<style scoped>
.vcd-sub {
  margin: 0.3rem 0 0;
  color: #5b6b60;
}

/* Top KPIs — sparse, like aquaManager portfolio strip */
.vcd-kpis {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.75rem;
  margin-bottom: 1rem;
}
/* area · plan feed · result feed · FCR */
.vcd-kpi {
  padding: 1rem 1.05rem;
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.1);
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.vcd-kpi span {
  font-size: 0.78rem;
  font-weight: 650;
  color: #667085;
}
.vcd-kpi strong {
  font-size: 1.45rem;
  font-variant-numeric: tabular-nums;
  line-height: 1.1;
  letter-spacing: -0.02em;
}
.vcd-kpi--focus {
  border-color: rgba(13, 107, 63, 0.28);
  background: #f6fbf8;
}
.vcd-kpi--focus strong { color: #0d6b3f; }

.vcd-area-nudge {
  display: grid;
  gap: 0.5rem;
  margin-bottom: 1rem;
  padding: 0.85rem 1rem;
}
.vcd-area-nudge__row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  max-width: 22rem;
}

.vcd-cycles { display: grid; gap: 0.85rem; }
.vcd-cycles__head h2 {
  margin: 0 0 0.2rem;
  font-size: 1.05rem;
}
.vcd-cycles__head p {
  margin: 0 0 0.4rem;
  color: #667085;
  font-size: 0.88rem;
}

/* One production cycle card */
.vcd-cycle {
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.1);
  border-radius: 14px;
  padding: 1.1rem 1.15rem 1.15rem;
}
.vcd-cycle__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 0.75rem;
  margin-bottom: 0.95rem;
}
.vcd-season {
  font-size: 1.15rem;
  font-weight: 780;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.02em;
}
.vcd-badge {
  display: inline-flex;
  padding: 0.18rem 0.55rem;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
}
.vcd-badge.is-open { background: #fff3d9; color: #8a5a00; }
.vcd-badge.is-final { background: #e8f5ee; color: #0d6b3f; }
.vcd-cycle__title {
  margin: 0;
  width: 100%;
  color: #667085;
  font-size: 0.88rem;
}

.vcd-metrics {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.65rem;
  margin-bottom: 0.85rem;
}
.vcd-metrics > div {
  padding: 0.7rem 0.8rem;
  border-radius: 10px;
  background: #f7f8f7;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  min-height: 4.6rem;
}
.vcd-metrics span {
  font-size: 0.72rem;
  font-weight: 650;
  color: #667085;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.vcd-metrics strong {
  font-size: 1.15rem;
  font-variant-numeric: tabular-nums;
  line-height: 1.15;
}
.vcd-metrics small {
  font-size: 0.78rem;
  font-weight: 650;
  color: #667085;
}
.vcd-metrics em {
  font-style: normal;
  font-size: 0.75rem;
  color: #667085;
  line-height: 1.3;
}
.vcd-metrics__fcr.is-good { background: #eef8f2; }
.vcd-metrics__fcr.is-ok { background: #fff8ee; }
.vcd-metrics__fcr.is-warn { background: #fff1f1; }
.vcd-metrics__fcr.is-good strong { color: #0d6b3f; }

/* Plan vs actual — one quiet comparison */
.vcd-vs {
  display: grid;
  gap: 0.35rem;
  margin-bottom: 0.9rem;
  padding-top: 0.15rem;
}
.vcd-vs__row {
  display: grid;
  grid-template-columns: 9.5rem 1fr 4.5rem;
  gap: 0.55rem;
  align-items: center;
  font-size: 0.8rem;
}
.vcd-vs__row span { color: #5b6b60; font-weight: 650; }
.vcd-vs__row b {
  text-align: right;
  font-variant-numeric: tabular-nums;
}
.vcd-vs__track {
  height: 7px;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.07);
  overflow: hidden;
}
.vcd-vs__track i {
  display: block;
  height: 100%;
  border-radius: inherit;
}
.vcd-vs__track i.is-plan { background: #0d6b3f; }
.vcd-vs__track i.is-result { background: #b45309; }

.vcd-cycle__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  padding-top: 0.15rem;
}

@media (max-width: 900px) {
  .vcd-kpis,
  .vcd-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 560px) {
  .vcd-kpis,
  .vcd-metrics { grid-template-columns: 1fr; }
  .vcd-vs__row {
    grid-template-columns: 1fr;
    gap: 0.2rem;
  }
  .vcd-vs__row b { text-align: left; }
}
</style>
