<template>
  <div class="venger-client-show venger-readable vcs">
    <OwnerVengerNav v-if="isOwnerView" />
    <div class="page-head">
      <div>
        <h1>{{ client?.name || $t('vengerClient') }}</h1>
        <p>{{ $t('vengerClientDiagramHint') }}</p>
      </div>
      <div class="page-head-actions">
        <router-link class="btn btn-outline btn-sm" :to="backTo">{{ $t('back') }}</router-link>
        <router-link
          v-if="client"
          class="btn btn-primary btn-sm"
          :to="newResultRoute"
        >
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
          {{ $t('vengerNewResult') }}
        </router-link>
      </div>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="error" class="panel empty">{{ error }}</div>

    <template v-else-if="client">
      <!-- Hero: identity + diagram rings -->
      <section class="panel vcs-hero">
        <div class="vcs-hero__top">
          <div>
            <p class="vcs-kicker">{{ $t('vengerClientSummary') }}</p>
            <h2 class="vcs-name">{{ client.name }}</h2>
            <p class="vcs-place">
              {{ [client.region, client.district].filter(Boolean).join(' · ') || '—' }}
            </p>
            <p class="vcs-phones mono">
              {{ formatPhone(client.phone) }}
              <template v-if="client.phone2"> · {{ formatPhone(client.phone2) }}</template>
            </p>
          </div>
          <button class="btn btn-outline btn-sm" type="button" @click="showAreaEdit = !showAreaEdit">
            {{ $t('vengerClientEditArea') }}
          </button>
        </div>

        <div class="vcs-rings">
          <div class="vcs-ring vcs-ring--area">
            <svg viewBox="0 0 120 120" aria-hidden="true">
              <circle class="vcs-ring__track" cx="60" cy="60" r="48" />
              <circle class="vcs-ring__val" cx="60" cy="60" r="48" :style="ringStyle(summary.pond_area_ha, ringMax.area)" />
            </svg>
            <div class="vcs-ring__label">
              <strong>{{ fmtAreaShort(summary.pond_area_ha) }}</strong>
              <span>{{ $t('vengerPondAreaHa') }}</span>
            </div>
          </div>
          <div class="vcs-ring vcs-ring--feed">
            <svg viewBox="0 0 120 120" aria-hidden="true">
              <circle class="vcs-ring__track" cx="60" cy="60" r="48" />
              <circle class="vcs-ring__val" cx="60" cy="60" r="48" :style="ringStyle(summary.result_feed_kg, ringMax.feed)" />
            </svg>
            <div class="vcs-ring__label">
              <strong>{{ fmtTon(summary.result_feed_kg) }}</strong>
              <span>{{ $t('ownerVengerResultFeed') }}</span>
            </div>
          </div>
          <div class="vcs-ring vcs-ring--gain">
            <svg viewBox="0 0 120 120" aria-hidden="true">
              <circle class="vcs-ring__track" cx="60" cy="60" r="48" />
              <circle class="vcs-ring__val" cx="60" cy="60" r="48" :style="ringStyle(summary.weight_gain_kg, ringMax.gain)" />
            </svg>
            <div class="vcs-ring__label">
              <strong>{{ fmtTon(summary.weight_gain_kg) }}</strong>
              <span>{{ $t('vengerTotalGain') }}</span>
            </div>
          </div>
          <div class="vcs-ring vcs-ring--fcr">
            <svg viewBox="0 0 120 120" aria-hidden="true">
              <circle class="vcs-ring__track" cx="60" cy="60" r="48" />
              <circle class="vcs-ring__val" cx="60" cy="60" r="48" :style="fcrRingStyle(summary.avg_fcr)" />
            </svg>
            <div class="vcs-ring__label">
              <strong>{{ summary.avg_fcr != null ? number(summary.avg_fcr, 2) : '—' }}</strong>
              <span>{{ $t('vengerAvgFcr') }}</span>
            </div>
          </div>
        </div>

        <div v-if="showAreaEdit" class="vcs-area-edit">
          <label>{{ $t('vengerPondAreaHa') }}</label>
          <div class="vcs-area-edit__row">
            <FormattedNumberInput v-model="areaDraft" :max-decimals="3" />
            <button class="btn btn-primary btn-sm" type="button" :disabled="savingArea" @click="saveArea">
              {{ savingArea ? $t('saving') : $t('save') }}
            </button>
          </div>
          <p v-if="areaError" class="error">{{ areaError }}</p>
          <p v-else class="field-hint">{{ $t('vengerPondAreaHaHint') }}</p>
        </div>
      </section>

      <!-- Season algorithm cards -->
      <section class="vcs-seasons">
        <div class="vcs-seasons__head">
          <h2>{{ $t('vengerResultsTitle') }}</h2>
          <p>{{ $t('vengerClientDiagramHint') }}</p>
        </div>

        <p v-if="!(client.results || []).length" class="panel empty">{{ $t('noVengerResults') }}</p>

        <article
          v-for="(r, idx) in client.results || []"
          :key="r.id"
          class="vcs-algo"
          :style="{ '--i': idx }"
        >
          <header class="vcs-algo__head">
            <div class="vcs-algo__year">{{ seasonLabel(r) }}</div>
            <span class="vr-status" :class="r.status === 'final' ? 'is-final' : 'is-active'">
              {{ phaseLabel(r) }}
            </span>
            <span v-if="r.title" class="vcs-algo__title">{{ r.title }}</span>
          </header>

          <!-- Main flow: area → feed → gain → FCR -->
          <div class="vcs-flow">
            <div class="vcs-node vcs-node--circle">
              <span>{{ $t('vengerAlgoArea') }}</span>
              <strong>{{ number(r.area_ha, 2) }}</strong>
              <em>ha</em>
            </div>
            <div class="vcs-arrow" aria-hidden="true"><i /><b /></div>

            <div class="vcs-node vcs-node--pill vcs-node--feed">
              <span>{{ $t('ownerVengerResultFeed') }}</span>
              <strong>{{ fmtTon(resultFeed(r)) }}</strong>
              <em>{{ feedSplit(r) }}</em>
            </div>
            <div class="vcs-arrow" aria-hidden="true"><i /><b /></div>

            <div class="vcs-node vcs-node--pill vcs-node--gain">
              <span>{{ $t('vengerAlgoGain') }}</span>
              <strong>{{ fmtTon(gainKg(r)) }}</strong>
              <em>kg → t</em>
            </div>
            <div class="vcs-arrow" aria-hidden="true"><i /><b /></div>

            <div class="vcs-node vcs-node--circle vcs-node--fcr" :class="fcrTone(r)">
              <span>{{ $t('vengerAlgoFcr') }}</span>
              <strong>{{ r.metrics?.fcr != null ? number(r.metrics.fcr, 2) : '—' }}</strong>
              <em>yem / o‘sish</em>
            </div>
          </div>

          <!-- Plan vs result bars -->
          <div class="vcs-compare">
            <div class="vcs-compare__head">
              <strong>{{ $t('vengerAlgoPlanVsResult') }}</strong>
              <span>{{ $t('ownerVengerResultFeedHint') }}</span>
            </div>
            <div class="vcs-compare__row">
              <span class="is-plan">{{ $t('ownerVengerPlanFeed') }}</span>
              <div class="vcs-bar">
                <i class="is-plan" :style="{ width: barPct(planFeed(r), r) + '%' }" />
              </div>
              <b>{{ fmtTon(planFeed(r)) }}</b>
            </div>
            <div class="vcs-compare__row">
              <span class="is-result">{{ $t('ownerVengerResultFeed') }}</span>
              <div class="vcs-bar">
                <i class="is-result" :style="{ width: barPct(resultFeed(r), r) + '%' }" />
              </div>
              <b>{{ fmtTon(resultFeed(r)) }}</b>
            </div>
            <div v-if="r.plan" class="vcs-plan-bits">
              <span>{{ $t('vengerFeedExtruder') }}: {{ fmtTon(r.plan.plan_extruder_kg) }}</span>
              <span>{{ $t('vengerFeedGranula') }}: {{ fmtTon(r.plan.plan_granula_kg) }}</span>
              <span v-if="r.plan.fish_stocked">{{ $t('ownerVengerFishStocked') }}: {{ number(r.plan.fish_stocked) }}</span>
            </div>
          </div>

          <footer class="vcs-algo__actions">
            <router-link class="btn btn-primary btn-sm" :to="resultRoute(r.id)">{{ $t('vengerGoodNewsBtn') }}</router-link>
            <router-link class="btn btn-outline btn-sm" :to="resultRoute(r.id)">{{ $t('viewResult') }}</router-link>
            <router-link
              class="btn btn-outline btn-sm"
              :to="editRoute(r.id)"
            >
              {{ r.status !== 'final' ? $t('vengerContinueResult') : $t('edit') }}
            </router-link>
          </footer>
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

const RING = 2 * Math.PI * 48;

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
    const showAreaEdit = ref(false);
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

    const summary = computed(() => {
      const s = client.value?.summary;
      if (s) return s;
      return {
        pond_area_ha: client.value?.pond_area_ha ?? 0,
        result_feed_kg: 0,
        plan_feed_kg: 0,
        avg_fcr: null,
        weight_gain_kg: 0,
        results_count: 0,
      };
    });

    const ringMax = computed(() => ({
      area: Math.max(Number(summary.value.pond_area_ha || 0), 10),
      feed: Math.max(Number(summary.value.result_feed_kg || 0), Number(summary.value.plan_feed_kg || 0), 1000),
      gain: Math.max(Number(summary.value.weight_gain_kg || 0), 1000),
    }));

    watch(client, (c) => {
      areaDraft.value = c?.pond_area_ha != null && c.pond_area_ha !== '' ? String(c.pond_area_ha) : '';
      if (c && (c.pond_area_ha == null || Number(c.pond_area_ha) <= 0)) {
        showAreaEdit.value = true;
      }
    });

    function number(v, decimals = 0) {
      const n = Number(v);
      if (!Number.isFinite(n)) return '0';
      return decimals
        ? n.toLocaleString(undefined, { maximumFractionDigits: decimals, minimumFractionDigits: Math.min(decimals, 2) })
        : n.toLocaleString();
    }

    function fmtAreaShort(v) {
      const n = Number(v);
      if (!Number.isFinite(n) || n <= 0) return '—';
      return `${number(n, 2)} ha`;
    }

    function fmtTon(kg) {
      const n = Number(kg || 0);
      if (!Number.isFinite(n) || n <= 0) return '0 t';
      return `${number(n / 1000, 2)} t`;
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
      if (e <= 0 && g <= 0) {
        const feeds = (r.feeds || []).filter((f) => f?.type && Number(f.kg) > 0);
        if (!feeds.length) return '—';
        return feeds.map((f) => {
          const type = normalizeFeedType(f.type) || f.type;
          const meta = VENGER_FEED_TYPES.find((x) => x.id === type);
          return meta ? t(meta.labelKey) : type;
        }).join(' + ');
      }
      const parts = [];
      if (e > 0) parts.push(`${t('vengerFeedExtruder')} ${fmtTon(e)}`);
      if (g > 0) parts.push(`${t('vengerFeedGranula')} ${fmtTon(g)}`);
      return parts.join(' · ');
    }

    function barPct(kg, r) {
      const max = Math.max(planFeed(r), resultFeed(r), 1);
      const n = Number(kg || 0);
      if (!Number.isFinite(n) || n <= 0) return 0;
      return Math.max(Math.round((n / max) * 100), 4);
    }

    function ringStyle(value, max) {
      const n = Math.max(Number(value || 0), 0);
      const m = Math.max(Number(max || 1), 1);
      const pct = Math.min(n / m, 1);
      const offset = RING * (1 - pct);
      return {
        strokeDasharray: `${RING} ${RING}`,
        strokeDashoffset: offset,
      };
    }

    function fcrRingStyle(fcr) {
      // Past FCR yaxshi: 1.0 = to‘liq, 3.0 = bo‘sh
      const n = Number(fcr);
      if (!Number.isFinite(n) || n <= 0) {
        return { strokeDasharray: `${RING} ${RING}`, strokeDashoffset: RING };
      }
      const score = Math.max(0, Math.min(1, (3 - n) / 2));
      return {
        strokeDasharray: `${RING} ${RING}`,
        strokeDashoffset: RING * (1 - score),
      };
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
            ...(data.summary || {}),
            pond_area_ha: data.pond_area_ha,
          },
          feed_plans: c.feed_plans,
        };
        showAreaEdit.value = false;
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
      ringMax,
      areaDraft,
      savingArea,
      areaError,
      showAreaEdit,
      isOwnerView,
      backTo,
      newResultRoute,
      number,
      fmtAreaShort,
      fmtTon,
      formatPhone,
      seasonLabel,
      phaseLabel,
      resultFeed,
      planFeed,
      gainKg,
      feedSplit,
      barPct,
      ringStyle,
      fcrRingStyle,
      fcrTone,
      resultRoute,
      editRoute,
      saveArea,
    };
  },
};
</script>

<style scoped>
.vr-status {
  display: inline-flex;
  padding: 0.22rem 0.6rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
}
.vr-status.is-active { background: #fff3d9; color: #8a5a00; }
.vr-status.is-final { background: #dcecff; color: #1458a0; }

.vcs-hero {
  margin-bottom: 1.1rem;
  background:
    radial-gradient(900px 240px at 10% 0%, rgba(13, 107, 63, 0.1), transparent 55%),
    radial-gradient(700px 200px at 100% 0%, rgba(217, 119, 6, 0.08), transparent 50%),
    #fff;
}
.vcs-hero__top {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.85rem;
  margin-bottom: 1rem;
}
.vcs-kicker {
  margin: 0 0 0.25rem;
  font-size: 0.72rem;
  font-weight: 750;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #667085;
}
.vcs-name { margin: 0; font-size: 1.55rem; line-height: 1.15; }
.vcs-place { margin: 0.35rem 0 0; color: #5b6b60; }
.vcs-phones { margin: 0.35rem 0 0; color: #0f172a; font-weight: 650; }

.vcs-rings {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.75rem;
}
.vcs-ring {
  position: relative;
  display: grid;
  place-items: center;
  min-height: 8.5rem;
  padding: 0.4rem;
}
.vcs-ring svg {
  width: 7.2rem;
  height: 7.2rem;
  transform: rotate(-90deg);
}
.vcs-ring__track {
  fill: none;
  stroke: rgba(15, 23, 42, 0.08);
  stroke-width: 9;
}
.vcs-ring__val {
  fill: none;
  stroke-width: 9;
  stroke-linecap: round;
  transition: stroke-dashoffset 0.7s ease;
}
.vcs-ring--area .vcs-ring__val { stroke: #0d6b3f; }
.vcs-ring--feed .vcs-ring__val { stroke: #d97706; }
.vcs-ring--gain .vcs-ring__val { stroke: #2563eb; }
.vcs-ring--fcr .vcs-ring__val { stroke: #7c3aed; }
.vcs-ring__label {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  pointer-events: none;
  padding: 1.4rem;
}
.vcs-ring__label strong {
  font-size: 1.05rem;
  font-variant-numeric: tabular-nums;
  line-height: 1.1;
}
.vcs-ring__label span {
  margin-top: 0.2rem;
  font-size: 0.68rem;
  font-weight: 700;
  color: #667085;
  line-height: 1.2;
  max-width: 5.8rem;
}

.vcs-area-edit {
  margin-top: 1rem;
  padding-top: 0.9rem;
  border-top: 1px dashed rgba(15, 23, 42, 0.12);
}
.vcs-area-edit label { display: block; font-weight: 700; margin-bottom: 0.35rem; }
.vcs-area-edit__row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  max-width: 22rem;
}

.vcs-seasons { display: grid; gap: 1rem; }
.vcs-seasons__head h2 { margin: 0 0 0.25rem; font-size: 1.1rem; }
.vcs-seasons__head p { margin: 0 0 0.35rem; color: #5b6b60; font-size: 0.9rem; }

.vcs-algo {
  padding: 1.15rem 1.15rem 1.2rem;
  border-radius: 22px;
  border: 1px solid rgba(15, 23, 42, 0.1);
  background:
    linear-gradient(180deg, #fcfdfc 0%, #f4f7f5 100%);
  animation: vcs-in 0.45s ease both;
  animation-delay: calc(var(--i, 0) * 0.06s);
}
@keyframes vcs-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: none; }
}
.vcs-algo__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.55rem 0.75rem;
  margin-bottom: 1rem;
}
.vcs-algo__year {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 6.2rem;
  padding: 0.45rem 0.9rem;
  border-radius: 999px;
  background: radial-gradient(circle at 30% 30%, #1a8f55, #0d6b3f 72%);
  color: #fff;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  letter-spacing: 0.02em;
}
.vcs-algo__title { color: #667085; font-size: 0.9rem; }

.vcs-flow {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem 0.2rem;
  margin-bottom: 1rem;
}
.vcs-node {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: 0.12rem;
  background: #fff;
  border: 1.5px solid rgba(15, 23, 42, 0.12);
  box-shadow: 0 1px 0 rgba(15, 23, 42, 0.04);
  padding: 0.55rem 0.85rem;
}
.vcs-node span {
  font-size: 0.66rem;
  font-weight: 750;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #64748b;
}
.vcs-node strong {
  font-size: 1.12rem;
  font-variant-numeric: tabular-nums;
  line-height: 1.1;
}
.vcs-node em {
  font-style: normal;
  font-size: 0.7rem;
  color: #667085;
  max-width: 8.5rem;
  line-height: 1.25;
}
.vcs-node--circle {
  width: 6.4rem;
  height: 6.4rem;
  border-radius: 50%;
  flex-shrink: 0;
}
.vcs-node--pill {
  min-width: 8.2rem;
  min-height: 4.2rem;
  border-radius: 999px;
  padding: 0.6rem 1.1rem;
}
.vcs-node--feed {
  background: #fffaf3;
  border-color: rgba(217, 119, 6, 0.35);
}
.vcs-node--gain {
  background: #f3f7ff;
  border-color: rgba(37, 99, 235, 0.28);
}
.vcs-node--fcr.is-good {
  background: #f3faf6;
  border-color: rgba(13, 107, 63, 0.4);
}
.vcs-node--fcr.is-ok {
  background: #fffaf3;
  border-color: rgba(217, 119, 6, 0.35);
}
.vcs-node--fcr.is-warn {
  background: #fff5f5;
  border-color: rgba(220, 38, 38, 0.3);
}

.vcs-arrow {
  width: 1.55rem;
  height: 2px;
  position: relative;
  flex-shrink: 0;
  align-self: center;
}
.vcs-arrow i {
  display: block;
  width: 100%;
  height: 2px;
  background: #94a3b8;
  border-radius: 2px;
}
.vcs-arrow b {
  position: absolute;
  right: -1px;
  top: 50%;
  transform: translateY(-50%);
  width: 0;
  height: 0;
  border-top: 5px solid transparent;
  border-bottom: 5px solid transparent;
  border-left: 7px solid #94a3b8;
}

.vcs-compare {
  padding: 0.85rem 0.95rem;
  border-radius: 16px;
  border: 1.5px dashed rgba(15, 23, 42, 0.14);
  background: rgba(255, 255, 255, 0.72);
  margin-bottom: 0.9rem;
}
.vcs-compare__head {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem 0.75rem;
  align-items: baseline;
  margin-bottom: 0.65rem;
}
.vcs-compare__head strong { font-size: 0.92rem; }
.vcs-compare__head span { font-size: 0.78rem; color: #9a6a3a; }
.vcs-compare__row {
  display: grid;
  grid-template-columns: minmax(7.5rem, 10.5rem) 1fr auto;
  gap: 0.55rem;
  align-items: center;
  margin-bottom: 0.4rem;
  font-size: 0.82rem;
}
.vcs-compare__row span.is-plan { color: #0d6b3f; font-weight: 700; }
.vcs-compare__row span.is-result { color: #b45309; font-weight: 700; }
.vcs-compare__row b { font-variant-numeric: tabular-nums; min-width: 4.2rem; text-align: right; }
.vcs-bar {
  height: 10px;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.08);
  overflow: hidden;
}
.vcs-bar i {
  display: block;
  height: 100%;
  border-radius: inherit;
}
.vcs-bar i.is-plan { background: #0d6b3f; }
.vcs-bar i.is-result { background: #d97706; }
.vcs-plan-bits {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem 0.85rem;
  margin-top: 0.45rem;
  font-size: 0.78rem;
  color: #5b6b60;
}

.vcs-algo__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

@media (max-width: 980px) {
  .vcs-rings { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 820px) {
  .vcs-flow { flex-direction: column; align-items: stretch; }
  .vcs-arrow {
    width: 2px;
    height: 1.2rem;
    align-self: center;
  }
  .vcs-arrow i { width: 2px; height: 100%; }
  .vcs-arrow b {
    right: auto;
    left: 50%;
    top: auto;
    bottom: -1px;
    transform: translateX(-50%);
    border-left: 5px solid transparent;
    border-right: 5px solid transparent;
    border-top: 7px solid #94a3b8;
    border-bottom: 0;
  }
  .vcs-node--circle,
  .vcs-node--pill {
    width: 100%;
    max-width: 22rem;
    align-self: center;
    border-radius: 18px;
    height: auto;
    min-height: 4.2rem;
  }
  .vcs-compare__row {
    grid-template-columns: 1fr;
    gap: 0.25rem;
  }
  .vcs-compare__row b { text-align: left; }
}
@media (max-width: 520px) {
  .vcs-rings { grid-template-columns: 1fr 1fr; }
}
</style>
