<template>
  <div class="venger-client-show venger-readable">
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
      <div class="panel">
        <div class="cred-grid">
          <div class="cred-item">
            <span>{{ $t('fullName') }}</span>
            <strong>{{ client.name }}</strong>
          </div>
          <div class="cred-item">
            <span>{{ $t('region') }}</span>
            <strong>{{ client.region || '—' }}</strong>
          </div>
          <div class="cred-item">
            <span>{{ $t('district') }}</span>
            <strong>{{ client.district || '—' }}</strong>
          </div>
          <div class="cred-item">
            <span>{{ $t('address') }}</span>
            <strong>{{ client.address || '—' }}</strong>
          </div>
          <div class="cred-item">
            <span>{{ $t('phone') }}</span>
            <strong class="mono">{{ formatPhone(client.phone) }}</strong>
          </div>
          <div v-if="client.phone2" class="cred-item">
            <span>{{ $t('phone2Optional') }}</span>
            <strong class="mono">{{ formatPhone(client.phone2) }}</strong>
          </div>
        </div>
        <p v-if="client.notes" class="field-hint">{{ client.notes }}</p>
      </div>

      <div class="panel">
        <div class="page-head" style="margin-top: 0">
          <div>
            <h2 style="margin: 0">{{ $t('vengerResultsTitle') }}</h2>
            <p style="margin: 0.35rem 0 0">{{ $t('vengerMultiResultHint') }}</p>
          </div>
        </div>

        <div class="venger-mlist">
          <article v-for="r in client.results || []" :key="`m-${r.id}`" class="venger-mcard">
            <strong class="venger-mcard__name">{{ r.title || $t('vengerPondUntitled') }}</strong>
            <p class="venger-mcard__line">{{ formatDate(r.stocked_on) }} → {{ formatDate(r.sold_on) }}</p>
            <p v-if="r.region || r.district" class="venger-mcard__line">
              {{ [r.region, r.district].filter(Boolean).join(' · ') }}
            </p>
            <p class="venger-mcard__line">
              <span class="vr-status" :class="r.status === 'final' ? 'is-final' : 'is-active'">{{ phaseLabel(r) }}</span>
              · {{ number(r.area_ha) }} ha
            </p>
            <p class="venger-mcard__line">{{ feedLabels(r) }}</p>
            <p class="venger-mcard__line">
              {{ $t('fcr') }}: {{ r.metrics?.fcr != null ? number(r.metrics.fcr, 2) : '—' }}
              · {{ r.metrics?.sazan?.biomass_gain_kg != null ? `${number(r.metrics.sazan.biomass_gain_kg)} kg` : (r.metrics?.weight_gain_kg != null ? `${number(r.metrics.weight_gain_kg)} kg` : '—') }}
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
                <th>{{ $t('period') }}</th>
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
                  <strong v-if="r.title" class="result-title">{{ r.title }}</strong>
                  <span>{{ formatDate(r.stocked_on) }} → {{ formatDate(r.sold_on) }}</span>
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
                <td>{{ r.metrics?.sazan?.biomass_gain_kg != null ? `${number(r.metrics.sazan.biomass_gain_kg)} kg` : (r.metrics?.weight_gain_kg != null ? `${number(r.metrics.weight_gain_kg)} kg` : '—') }}</td>
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
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import OwnerVengerNav from '../../components/OwnerVengerNav.vue';
import auth from '../../auth';
import { formatPhoneDisplay } from '../../utils/phone';
import { VENGER_FEED_TYPES, normalizeFeedType } from '../../utils/vengerFeeds';
import { t } from '../../i18n';
import { formatDisplayDate } from '../../utils/date';

export default {
  components: { OwnerVengerNav },
  setup() {
    const route = useRoute();
    const loading = ref(true);
    const error = ref('');
    const client = ref(null);
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

    function number(v, decimals = 0) {
      const n = Number(v);
      if (!Number.isFinite(n)) return '0';
      return decimals ? n.toLocaleString(undefined, { maximumFractionDigits: decimals, minimumFractionDigits: decimals }) : n.toLocaleString();
    }

    function formatPhone(phone) {
      return formatPhoneDisplay(phone || '');
    }

    function formatDate(value) {
      if (!value) return '—';
      const formatted = formatDisplayDate(value);
      return formatted || String(value);
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

    onMounted(load);

    return {
      loading, error, client, isOwnerView, backTo, newResultRoute,
      number, formatPhone, formatDate, phaseLabel, feedLabels, resultRoute, editRoute,
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
.result-title { display: block; margin-bottom: 0.15rem; }
.result-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  justify-content: flex-end;
}
.venger-mcard__actions .btn {
  min-height: 44px;
}
</style>
