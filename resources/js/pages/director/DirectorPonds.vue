<template>
  <DirectorShell>
    <div v-if="loading" class="panel empty">{{ $t('loading') }}</div>

    <div v-else-if="loadError" class="panel empty error">{{ loadError }}</div>

    <div v-else-if="!farms.length" class="panel empty">
      {{ $t('directorNoFarmYet') }}
    </div>

    <template v-else>
      <div class="dir-kpi-grid">
        <div class="dir-kpi">
          <span class="dir-kpi__label">{{ $t('ponds') }}</span>
          <strong class="dir-kpi__value">{{ formatNumber(stats.ponds) }}</strong>
          <span class="dir-kpi__meta">{{ $t('pieces') }}</span>
        </div>
        <div class="dir-kpi">
          <span class="dir-kpi__label">{{ $t('totalFishCount') }}</span>
          <strong class="dir-kpi__value">{{ formatNumber(stats.fish) }}</strong>
          <span class="dir-kpi__meta">{{ $t('pieces') }}</span>
        </div>
        <div class="dir-kpi dir-kpi--sales">
          <span class="dir-kpi__label">{{ $t('financeTotalSales') }}</span>
          <strong class="dir-kpi__value">{{ formatMoney(stats.sales) }}</strong>
        </div>
        <div class="dir-kpi dir-kpi--expense">
          <span class="dir-kpi__label">{{ $t('financeTotalExpenses') }}</span>
          <strong class="dir-kpi__value">{{ formatMoney(stats.expenses) }}</strong>
        </div>
        <div
          class="dir-kpi"
          :class="stats.profit >= 0 ? 'dir-kpi--profit' : 'dir-kpi--loss'"
        >
          <span class="dir-kpi__label">{{ $t('financeProfit') }}</span>
          <strong class="dir-kpi__value">{{ formatMoney(stats.profit) }}</strong>
        </div>
        <div class="dir-kpi">
          <span class="dir-kpi__label">{{ $t('warehouse') }}</span>
          <strong class="dir-kpi__value">{{ formatNumber(stats.warehouseItems) }}</strong>
          <span class="dir-kpi__meta">{{ $t('directorWarehouseSku') }}</span>
        </div>
      </div>

      <section class="panel dir-dash__panel">
        <div class="panel-head">
          <h2>{{ $t('directorAttentionTitle') }}</h2>
        </div>
        <ul v-if="visibleAlerts.length" class="dir-alert-list">
          <li
            v-for="alert in visibleAlerts"
            :key="alert.id"
            class="dir-alert"
            :class="`dir-alert--${alert.tone}`"
          >
            <div class="dir-alert__body">
              <strong>{{ alert.title }}</strong>
              <span class="dir-alert__detail" v-html="alert.detailHtml" />
            </div>
            <div class="dir-alert__actions">
              <button
                type="button"
                class="btn btn-outline btn-sm"
                @click="markAlertAsRead(alert)"
              >
                {{ $t('directorAlertMarkRead') }}
              </button>
              <button
                v-if="alert.action"
                type="button"
                class="btn btn-primary btn-sm"
                @click="runAlertAction(alert)"
              >
                {{ alert.actionLabel }}
              </button>
            </div>
          </li>
        </ul>
        <div v-else class="dir-dash__quiet">{{ $t('directorNoAlerts') }}</div>
      </section>

      <div class="farm-hub__grid">
        <button
          type="button"
          class="farm-hub-card farm-hub-card--fishery"
          :disabled="!primaryFarm"
          @click="goFarmSection('director-fishery-ponds')"
        >
          <span class="farm-hub-card__icon" aria-hidden="true">
            <HubSectionMark section="fishery" />
          </span>
          <span class="farm-hub-card__body">
            <strong>{{ $t('fishery') }}</strong>
          </span>
          <span class="farm-hub-card__arrow" aria-hidden="true">
            <svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg>
          </span>
        </button>

        <button
          type="button"
          class="farm-hub-card farm-hub-card--warehouse"
          :disabled="!primaryFarm"
          @click="goFarmSection('director-warehouse')"
        >
          <span class="farm-hub-card__icon" aria-hidden="true">
            <HubSectionMark section="warehouse" />
          </span>
          <span class="farm-hub-card__body">
            <strong>{{ $t('warehouse') }}</strong>
          </span>
          <span class="farm-hub-card__arrow" aria-hidden="true">
            <svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg>
          </span>
        </button>

        <button
          type="button"
          class="farm-hub-card farm-hub-card--finance"
          :disabled="!primaryFarm"
          @click="goFarmSection('director-finance')"
        >
          <span class="farm-hub-card__icon" aria-hidden="true">
            <HubSectionMark section="finance" />
          </span>
          <span class="farm-hub-card__body">
            <strong>{{ $t('finance') }}</strong>
          </span>
          <span class="farm-hub-card__arrow" aria-hidden="true">
            <svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg>
          </span>
        </button>

        <button
          type="button"
          class="farm-hub-card farm-hub-card--chat"
          @click="router.push({ name: 'director-support' })"
        >
          <span class="farm-hub-card__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>
          </span>
          <span class="farm-hub-card__body">
            <strong>{{ $t('bossChatTitle') }}</strong>
          </span>
          <span class="farm-hub-card__arrow" aria-hidden="true">
            <svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg>
          </span>
        </button>
      </div>

    </template>
  </DirectorShell>
</template>

<script>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import HubSectionMark from '../../components/HubSectionMark.vue';
import auth from '../../auth';
import { t } from '../../i18n';
import {
  alertIdentity,
  filterUnreadAlerts,
  markAlertRead,
} from '../../utils/directorAlerts';
import { formatAlertRichText } from '../../utils/alertRichText';
import { getDirectorFarmId, setDirectorFarmId } from '../../utils/directorFarm';
import { formatMoney, formatNumber } from '../../utils/number';

const LOW_STOCK_QTY = 10;

function alertDetailHtml(key, params = {}) {
  return formatAlertRichText(t(key, params));
}

export default {
  components: { DirectorShell, HubSectionMark },
  setup() {
    const router = useRouter();
    const farms = ref([]);
    const ponds = ref([]);
    const financeByFarm = ref({});
    const warehouseByFarm = ref({});
    const pendingByFarm = ref({});
    const bossMessages = ref([]);
    const loading = ref(true);
    const loadError = ref('');

    const primaryFarm = computed(() => {
      if (!farms.value.length) return null;
      const stored = getDirectorFarmId();
      const match = farms.value.find((f) => String(f.id) === String(stored));
      return match || farms.value[0];
    });

    const stats = computed(() => {
      const farmList = farms.value;
      const pondList = ponds.value;
      let expenses = 0;
      let sales = 0;
      let profit = 0;
      let warehouseItems = 0;

      farmList.forEach((farm) => {
        const fin = financeByFarm.value[farm.id];
        if (fin) {
          expenses += Number(fin.total_expenses) || 0;
          sales += Number(fin.total_sales) || 0;
          profit += Number(fin.profit) || 0;
        }
        const wh = warehouseByFarm.value[farm.id] || [];
        warehouseItems += wh.length;
      });

      const fish = pondList.reduce((sum, p) => {
        if (p.status === 'closed' || p.closed_at) return sum;
        return sum + (Number(p.current_stock) || 0);
      }, 0);

      const activePonds = pondList.filter((p) => p.status !== 'closed' && !p.closed_at).length;

      return {
        farms: farmList.length,
        ponds: activePonds || farmList.reduce((s, f) => s + (Number(f.ponds_count) || 0), 0),
        fish,
        expenses,
        sales,
        profit,
        warehouseItems,
      };
    });

    const readTick = ref(0);

    const alerts = computed(() => {
      const list = [];

      bossMessages.value.forEach((item) => {
        const count = Number(item.unread_count) || 1;
        list.push({
          type: 'boss-message',
          tone: 'warning',
          title: t('bossMessageTitle'),
          detailHtml: formatAlertRichText(
            count > 1
              ? t('bossChatUnreadMany', { n: count, text: item.body || '' })
              : (item.body || t('bossChatUnreadOne')),
          ),
          actionLabel: t('bossChatOpen'),
          farmId: null,
          itemId: item.itemId || item.id,
          bossMessageId: item.itemId || item.id,
          action: () => {
            router.push({ name: 'director-support' });
          },
        });
      });

      farms.value.forEach((farm) => {
        const pending = pendingByFarm.value[farm.id] || [];
        const waiting = pending.filter((r) => !r.status || r.status === 'pending');
        if (waiting.length) {
          list.push({
            type: 'pond-request',
            tone: 'warning',
            title: farm.name,
            detailHtml: alertDetailHtml('directorPendingPondsAlert', { count: waiting.length }),
            actionLabel: t('fishery'),
            farmId: farm.id,
            itemId: waiting.length,
            action: () => {
              setDirectorFarmId(farm.id);
              router.push({ name: 'director-fishery-ponds' });
            },
          });
        }

        const items = warehouseByFarm.value[farm.id] || [];
        items.forEach((item) => {
          const qty = Number(item.quantity) || 0;
          const isConsumable = item.category === 'feed' || item.category === 'medicine';
          if (isConsumable && qty <= LOW_STOCK_QTY) {
            list.push({
              type: 'stock',
              tone: qty <= 0 ? 'danger' : 'warning',
              title: farm.name,
              detailHtml: alertDetailHtml(
                qty <= 0 ? 'directorEmptyStockAlert' : 'directorLowStockAlert',
                { name: item.name, qty: formatNumber(qty) },
              ),
              actionLabel: t('warehouse'),
              farmId: farm.id,
              itemId: item.id,
              action: () => {
                setDirectorFarmId(farm.id);
                router.push({ name: 'director-warehouse' });
              },
            });
          }
        });
      });

      return list.slice(0, 8).map((alert) => ({
        ...alert,
        id: alertIdentity(alert),
      }));
    });

    const visibleAlerts = computed(() => {
      readTick.value;
      return filterUnreadAlerts(auth.state.user?.id, alerts.value);
    });

    function markAlertAsRead(alert) {
      markAlertRead(auth.state.user?.id, alert);
      if (alert?.type === 'boss-message') {
        window.axios.post('/director/boss-messages/read').catch(() => {});
        bossMessages.value = [];
      }
      readTick.value += 1;
    }

    function runAlertAction(alert) {
      markAlertAsRead(alert);
      if (typeof alert.action === 'function') alert.action();
    }

    async function loadFarmExtras(farmId) {
      const [finRes, whRes, reqRes] = await Promise.allSettled([
        window.axios.get(`/director/farms/${farmId}/finance/overview`),
        window.axios.get(`/director/farms/${farmId}/warehouse`),
        window.axios.get(`/director/farms/${farmId}/pond-create-requests`),
      ]);

      if (finRes.status === 'fulfilled') {
        financeByFarm.value = { ...financeByFarm.value, [farmId]: finRes.value.data };
      }
      if (whRes.status === 'fulfilled') {
        warehouseByFarm.value = { ...warehouseByFarm.value, [farmId]: whRes.value.data || [] };
      }
      if (reqRes.status === 'fulfilled') {
        const data = reqRes.value.data;
        pendingByFarm.value = {
          ...pendingByFarm.value,
          [farmId]: Array.isArray(data) ? data : (data?.data || []),
        };
      }
    }

    async function load() {
      loading.value = true;
      loadError.value = '';
      try {
        const [farmsRes, pondsRes] = await Promise.all([
          window.axios.get('/director/farms'),
          window.axios.get('/director/ponds'),
        ]);
        farms.value = farmsRes.data || [];
        ponds.value = pondsRes.data || [];

        if (farms.value.length === 1) {
          setDirectorFarmId(farms.value[0].id);
        } else if (farms.value.length && !getDirectorFarmId()) {
          setDirectorFarmId(farms.value[0].id);
        }

        const [, bossRes] = await Promise.all([
          Promise.all(farms.value.map((f) => loadFarmExtras(f.id))),
          window.axios.get('/director/boss-messages').catch(() => null),
        ]);
        bossMessages.value = bossRes?.data?.alerts || [];
      } catch (e) {
        farms.value = [];
        ponds.value = [];
        bossMessages.value = [];
        loadError.value = e.response?.data?.message || t('errorOccurred');
      } finally {
        loading.value = false;
      }
    }

    function openFarm(farm) {
      setDirectorFarmId(farm.id);
      router.push({ name: 'director-home' });
    }

    function goFarmSection(name) {
      const farm = primaryFarm.value;
      if (!farm) return;
      setDirectorFarmId(farm.id);
      router.push({ name });
    }

    onMounted(load);

    return {
      farms,
      loading,
      loadError,
      primaryFarm,
      stats,
      visibleAlerts,
      markAlertAsRead,
      runAlertAction,
      openFarm,
      goFarmSection,
      router,
      formatMoney,
      formatNumber,
    };
  },
};
</script>
