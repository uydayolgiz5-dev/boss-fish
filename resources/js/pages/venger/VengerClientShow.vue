<template>
  <div class="venger-client-show venger-readable vcs">
    <OwnerVengerNav v-if="isOwnerView" />
    <div class="page-head">
      <div>
        <h1>{{ client?.name || $t('vengerClient') }}</h1>
        <p>{{ $t('vengerClientWorkHint') }}</p>
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
      <section class="panel vcs-hero">
        <div class="vcs-hero__top">
          <div>
            <p class="vcs-kicker">{{ $t('vengerClientSummary') }}</p>
            <h2 class="vcs-name">{{ client.name }}</h2>
            <p class="vcs-place">
              {{ [client.region, client.district].filter(Boolean).join(' · ') || '—' }}
            </p>
          </div>
          <div class="vcs-hero__area">
            <span>{{ $t('vengerPondAreaHa') }}</span>
            <strong>{{ fmtArea(client.pond_area_ha) }}</strong>
          </div>
        </div>

        <div class="vcs-facts">
          <div class="vcs-fact">
            <span>{{ $t('phone') }}</span>
            <strong class="mono">{{ formatPhone(client.phone) }}</strong>
          </div>
          <div v-if="client.phone2" class="vcs-fact">
            <span>{{ $t('phone2Optional') }}</span>
            <strong class="mono">{{ formatPhone(client.phone2) }}</strong>
          </div>
          <div class="vcs-fact">
            <span>{{ $t('address') }}</span>
            <strong>{{ client.address || '—' }}</strong>
          </div>
          <div class="vcs-fact">
            <span>{{ $t('vengerClientResultsCount') }}</span>
            <strong>{{ number(client.results_count ?? (client.results || []).length) }}</strong>
          </div>
          <div v-if="client.created_by?.name" class="vcs-fact">
            <span>{{ $t('vengerStaff') }}</span>
            <strong>{{ client.created_by.name }}</strong>
          </div>
        </div>

        <p v-if="client.notes" class="vcs-notes">{{ client.notes }}</p>

        <div class="vcs-area-edit">
          <label>{{ $t('vengerPondAreaHa') }}</label>
          <div class="vcs-area-edit__row">
            <FormattedNumberInput v-model="areaDraft" :max-decimals="3" />
            <button class="btn btn-outline btn-sm" type="button" :disabled="savingArea" @click="saveArea">
              {{ savingArea ? $t('saving') : $t('save') }}
            </button>
          </div>
          <p v-if="areaError" class="error">{{ areaError }}</p>
          <p v-else class="field-hint">{{ $t('vengerPondAreaHaHint') }}</p>
        </div>
      </section>

      <div class="panel">
        <div class="page-head" style="margin-top: 0">
          <div>
            <h2 style="margin: 0">{{ $t('vengerResultsTitle') }}</h2>
            <p style="margin: 0.35rem 0 0">{{ $t('vengerMultiResultHint') }}</p>
          </div>
        </div>

        <div class="venger-mlist">
          <article v-for="r in client.results || []" :key="`m-${r.id}`" class="venger-mcard">
            <strong class="venger-mcard__name">{{ seasonLabel(r) }}</strong>
            <p v-if="r.title" class="venger-mcard__line">{{ r.title }}</p>
            <p class="venger-mcard__line">
              <span class="vr-status" :class="r.status === 'final' ? 'is-final' : 'is-active'">{{ phaseLabel(r) }}</span>
              · {{ number(r.area_ha) }} ha
            </p>
            <p class="venger-mcard__line">{{ feedLabels(r) }}</p>
            <p class="venger-mcard__line">
              {{ $t('fcr') }}: {{ r.metrics?.fcr != null ? number(r.metrics.fcr, 2) : '—' }}
              · {{ gainLabel(r) }}
            </p>
            <div class="venger-mcard__actions">
              <router-link class="btn btn-primary btn-sm" :to="resultRoute(r.id)">{{ $t('vengerGoodNewsBtn') }}</router-link>
              <router-link class="btn btn-outline btn-sm" :to="resultRoute(r.id)">{{ $t('viewResult') }}</router-link>
              <router-link
                v-if="r.status !== 'final'"
                class="btn btn-primary btn-sm"
                :to="editRoute(r.id)"
              >
                {{ $t('vengerContinueResult') }}
              </router-link>
              <router-link
                v-else
                class="btn btn-outline btn-sm"
                :to="editRoute(r.id)"
              >
                {{ $t('edit') }}
              </router-link>
            </div>
          </article>
          <p v-if="!(client.results || []).length" class="empty">{{ $t('noVengerResults') }}</p>
        </div>
        <div class="table-scroll venger-desktop-table">
          <table>
            <thead>
              <tr>
                <th>{{ $t('vengerSeasonPeriod') }}</th>
                <th>{{ $t('status') }}</th>
                <th>{{ $t('areaHa') }}</th>
                <th>{{ $t('vengerFeedTypesShort') }}</th>
                <th>{{ $t('feedAmountKg') }}</th>
                <th>{{ $t('fcr') }}</th>
                <th>{{ $t('weightGain') }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in client.results || []" :key="r.id">
                <td>
                  <strong class="result-title">{{ seasonLabel(r) }}</strong>
                  <span v-if="r.title" class="result-sub">{{ r.title }}</span>
                </td>
                <td>
                  <span class="vr-status" :class="r.status === 'final' ? 'is-final' : 'is-active'">
                    {{ phaseLabel(r) }}
                  </span>
                </td>
                <td>{{ number(r.area_ha) }}</td>
                <td>{{ feedLabels(r) }}</td>
                <td>{{ number(r.feed_kg) }} kg</td>
                <td>{{ r.metrics?.fcr != null ? number(r.metrics.fcr, 2) : '—' }}</td>
                <td>{{ gainLabel(r) }}</td>
                <td class="result-actions">
                  <router-link class="btn btn-primary btn-sm" :to="resultRoute(r.id)">
                    {{ $t('vengerGoodNewsBtn') }}
                  </router-link>
                  <router-link class="btn btn-outline btn-sm" :to="resultRoute(r.id)">
                    {{ $t('viewResult') }}
                  </router-link>
                  <router-link
                    v-if="r.status !== 'final'"
                    class="btn btn-primary btn-sm"
                    :to="editRoute(r.id)"
                  >
                    {{ $t('vengerContinueResult') }}
                  </router-link>
                  <router-link
                    v-else
                    class="btn btn-outline btn-sm"
                    :to="editRoute(r.id)"
                  >
                    {{ $t('edit') }}
                  </router-link>
                </td>
              </tr>
              <tr v-if="!(client.results || []).length">
                <td colspan="8" class="empty">{{ $t('noVengerResults') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
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

    watch(client, (c) => {
      areaDraft.value = c?.pond_area_ha != null && c.pond_area_ha !== '' ? String(c.pond_area_ha) : '';
    });

    function number(v, decimals = 0) {
      const n = Number(v);
      if (!Number.isFinite(n)) return '0';
      return decimals
        ? n.toLocaleString(undefined, { maximumFractionDigits: decimals, minimumFractionDigits: decimals })
        : n.toLocaleString();
    }

    function fmtArea(v) {
      const n = Number(v);
      if (!Number.isFinite(n) || n <= 0) return '—';
      return `${n.toLocaleString(undefined, { maximumFractionDigits: 3 })} ha`;
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

    function feedLabels(r) {
      const feeds = (r.feeds || []).filter((f) => f?.type && Number(f.kg) > 0);
      if (!feeds.length) return '—';
      return feeds.map((f) => {
        const type = normalizeFeedType(f.type) || f.type;
        const meta = VENGER_FEED_TYPES.find((x) => x.id === type);
        const label = meta ? t(meta.labelKey) : (f.label || type);
        const kg = Number(f.kg);
        return Number.isFinite(kg) ? `${label} ${kg} kg` : label;
      }).join(', ');
    }

    function gainLabel(r) {
      if (r.metrics?.sazan?.biomass_gain_kg != null) {
        return `${number(r.metrics.sazan.biomass_gain_kg)} kg`;
      }
      if (r.metrics?.weight_gain_kg != null) {
        return `${number(r.metrics.weight_gain_kg)} kg`;
      }
      return '—';
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
      areaDraft,
      savingArea,
      areaError,
      isOwnerView,
      backTo,
      newResultRoute,
      number,
      fmtArea,
      formatPhone,
      seasonLabel,
      phaseLabel,
      feedLabels,
      gainLabel,
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
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
}
.vr-status.is-active { background: #fff3d9; color: #8a5a00; }
.vr-status.is-final { background: #dcecff; color: #1458a0; }
.result-title { display: block; margin-bottom: 0.15rem; font-variant-numeric: tabular-nums; }
.result-sub { display: block; color: #667085; font-size: 0.82rem; }
.result-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  justify-content: flex-end;
}
.venger-mcard__actions .btn {
  min-height: 44px;
}

.vcs-hero {
  margin-bottom: 1rem;
  background:
    radial-gradient(900px 220px at 0% 0%, rgba(13, 107, 63, 0.08), transparent 55%),
    #fff;
}
.vcs-hero__top {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}
.vcs-kicker {
  margin: 0 0 0.25rem;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #667085;
}
.vcs-name {
  margin: 0;
  font-size: 1.45rem;
  line-height: 1.2;
}
.vcs-place {
  margin: 0.35rem 0 0;
  color: #5b6b60;
}
.vcs-hero__area {
  min-width: 9rem;
  padding: 0.85rem 1rem;
  border-radius: 16px;
  background: #f3faf6;
  border: 1px solid rgba(13, 107, 63, 0.22);
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}
.vcs-hero__area span {
  font-size: 0.75rem;
  font-weight: 700;
  color: #5b6b60;
}
.vcs-hero__area strong {
  font-size: 1.35rem;
  font-variant-numeric: tabular-nums;
  color: #0d6b3f;
}
.vcs-facts {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.65rem;
}
.vcs-fact {
  padding: 0.75rem 0.85rem;
  border-radius: 12px;
  background: #f8faf9;
  border: 1px solid rgba(15, 23, 42, 0.08);
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.vcs-fact span {
  font-size: 0.72rem;
  font-weight: 700;
  color: #667085;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.vcs-fact strong {
  font-size: 0.98rem;
  line-height: 1.25;
  word-break: break-word;
}
.vcs-notes {
  margin: 0.85rem 0 0;
  color: #5b6b60;
  font-size: 0.9rem;
}
.vcs-area-edit {
  margin-top: 1rem;
  padding-top: 0.9rem;
  border-top: 1px dashed rgba(15, 23, 42, 0.12);
}
.vcs-area-edit label {
  display: block;
  font-weight: 700;
  margin-bottom: 0.35rem;
}
.vcs-area-edit__row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  max-width: 22rem;
}

@media (max-width: 900px) {
  .vcs-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 560px) {
  .vcs-facts { grid-template-columns: 1fr; }
  .vcs-hero__area { width: 100%; }
}
</style>
