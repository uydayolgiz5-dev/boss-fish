<template>
  <header class="crm-topbar">
    <div class="crm-topbar__left">
      <span class="crm-topbar__title">{{ title }}</span>
      <span v-if="subtitle" class="crm-topbar__subtitle">{{ subtitle }}</span>
    </div>

    <div class="crm-topbar__right">
      <div
        v-if="accessInfo"
        class="crm-topbar-access"
        :class="`crm-topbar-access--${accessInfo.tone}`"
        :title="accessInfo.untilLabel || undefined"
      >
        <span class="crm-topbar-access__days">{{ accessInfo.daysLabel }}</span>
        <span v-if="accessInfo.untilLabel" class="crm-topbar-access__until">{{ accessInfo.untilLabel }}</span>
      </div>

      <div v-if="showAlerts" class="crm-topbar-alerts" ref="alertsRoot">
        <button
          type="button"
          class="crm-topbar-bell"
          :class="{ 'crm-topbar-bell--hot': unreadAlerts.length }"
          :aria-expanded="alertsOpen ? 'true' : 'false'"
          :aria-label="$t('directorAttentionTitle')"
          @click="toggleAlerts"
        >
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
          </svg>
          <span v-if="unreadAlerts.length" class="crm-topbar-bell__badge">{{ unreadAlerts.length > 9 ? '9+' : unreadAlerts.length }}</span>
        </button>
      </div>

      <Teleport to="body">
        <div
          v-if="showAlerts && alertsOpen"
          class="crm-topbar-alerts__layer"
          ref="panelRoot"
        >
          <button class="crm-topbar-alerts__scrim" type="button" :aria-label="$t('close')" @click="alertsOpen = false" />
          <div class="crm-topbar-alerts__panel" role="dialog" :aria-label="$t('directorAttentionTitle')">
            <div class="crm-topbar-alerts__head">
              <strong>{{ $t('directorAttentionTitle') }}</strong>
              <button
                v-if="unreadAlerts.length"
                type="button"
                class="crm-topbar-alerts__mark-all"
                @click="markAllRead"
              >
                {{ $t('directorAlertsMarkAllRead') }}
              </button>
            </div>
            <ul v-if="unreadAlerts.length" class="crm-topbar-alerts__list">
              <li
                v-for="alert in unreadAlerts"
                :key="alert.id"
                :class="`crm-topbar-alert crm-topbar-alert--${alert.tone}`"
              >
                <div class="crm-topbar-alert__body">
                  <strong>{{ alert.title }}</strong>
                  <span class="crm-topbar-alert__detail" v-html="alert.detailHtml" />
                </div>
                <div class="crm-topbar-alert__actions">
                  <button
                    type="button"
                    class="btn btn-outline btn-sm"
                    @click="markRead(alert)"
                  >
                    {{ $t('directorAlertMarkRead') }}
                  </button>
                  <button
                    v-if="alert.route"
                    type="button"
                    class="btn btn-primary btn-sm"
                    @click="goAlert(alert)"
                  >
                    {{ alert.actionLabel || $t('alertReadDetails') }}
                  </button>
                </div>
              </li>
            </ul>
            <div v-else class="crm-topbar-alerts__empty">{{ $t('directorNoAlerts') }}</div>
          </div>
        </div>
      </Teleport>

      <router-link
        v-if="showSettings"
        class="crm-topbar-settings"
        :to="{ name: 'director-settings' }"
        :title="$t('settings')"
        :aria-label="$t('settings')"
      >
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="3" />
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
        </svg>
        <span class="crm-topbar-settings__label">{{ $t('settings') }}</span>
      </router-link>

      <LanguageSwitcher embedded />
    </div>
  </header>
</template>

<script>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import auth from '../auth';
import { t } from '../i18n';
import { formatUzLongDate } from '../utils/date';
import {
  alertIdentity,
  filterUnreadAlerts,
  markAlertRead,
} from '../utils/directorAlerts';
import { formatAlertRichText } from '../utils/alertRichText';
import { setDirectorFarmId } from '../utils/directorFarm';
import { formatNumber } from '../utils/number';
import LanguageSwitcher from './LanguageSwitcher.vue';

function alertDetailHtml(key, params = {}) {
  return formatAlertRichText(t(key, params));
}

const LOW_STOCK_QTY = 10;

export default {
  components: { LanguageSwitcher },
  props: {
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    showDirectorTools: { type: Boolean, default: false },
    showSettings: { type: Boolean, default: false },
  },
  setup(props) {
    const router = useRouter();
    const alertsOpen = ref(false);
    const alertsRoot = ref(null);
    const panelRoot = ref(null);
    const alerts = ref([]);
    const loadingAlerts = ref(false);
    const readTick = ref(0);
    const userId = computed(() => auth.state.user?.id);

    // To‘lov muddati olib tashlandi — faqat superadmin active/passive
    const accessInfo = computed(() => null);

    const showAlerts = computed(() => props.showDirectorTools);

    const unreadAlerts = computed(() => {
      readTick.value;
      return filterUnreadAlerts(userId.value, alerts.value);
    });

    async function loadAlerts() {
      if (!props.showDirectorTools) {
        alerts.value = [];
        return;
      }
      loadingAlerts.value = true;
      const list = [];
      try {
        const { data: farms } = await window.axios.get('/director/farms');
        const farmList = Array.isArray(farms) ? farms : [];
        const [, waterRes, workRes, closureRes, bossRes] = await Promise.all([
          Promise.all(farmList.map(async (farm) => {
            const [whRes, reqRes] = await Promise.allSettled([
              window.axios.get(`/director/farms/${farm.id}/warehouse`),
              window.axios.get(`/director/farms/${farm.id}/pond-create-requests`),
            ]);
            if (whRes.status === 'fulfilled') {
              (whRes.value.data || []).forEach((item) => {
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
                    route: { name: 'director-warehouse' },
                  });
                }
              });
            }
            if (reqRes.status === 'fulfilled') {
              const raw = reqRes.value.data;
              const pending = (Array.isArray(raw) ? raw : (raw?.data || []))
                .filter((r) => !r.status || r.status === 'pending');
              if (pending.length) {
                list.push({
                  type: 'pond-request',
                  tone: 'warning',
                  title: farm.name,
                  detailHtml: alertDetailHtml('directorPendingPondsAlert', { count: pending.length }),
                  actionLabel: t('fishery'),
                  farmId: farm.id,
                  itemId: pending.length,
                  route: { name: 'director-fishery-ponds' },
                });
              }
            }
          })),
          window.axios.get('/director/water-quality-alerts').catch(() => null),
          window.axios.get('/director/work-reminders').catch(() => null),
          window.axios.get('/director/closure-notifications').catch(() => null),
          window.axios.get('/director/boss-messages').catch(() => null),
        ]);

        const bossAlerts = bossRes?.data?.alerts || [];
        bossAlerts.forEach((item) => {
          const count = Number(item.unread_count) || 1;
          list.unshift({
            type: 'boss-message',
            tone: item.tone || 'warning',
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
            route: { name: 'director-support' },
          });
        });

        const closureAlerts = closureRes?.data?.alerts || [];
        closureAlerts.forEach((item) => {
          const pondName = item.pond_name || '';
          const approved = item.status === 'approved';
          list.push({
            type: item.type || 'closure',
            tone: item.tone || (approved ? 'success' : 'warning'),
            title: approved ? t('closureApproved') : t('closureRejected'),
            detailHtml: alertDetailHtml(
              approved ? 'closureApprovedAlert' : 'closureRejectedAlert',
              { pond: pondName },
            ),
            actionLabel: t('closePond'),
            farmId: null,
            itemId: item.id,
            route: item.pond_id
              ? { name: 'director-close-pond', params: { pondId: item.pond_id } }
              : { name: 'director-fishery-ponds' },
          });
        });

        const waterAlerts = waterRes?.data?.alerts || [];
        waterAlerts.forEach((item) => {
          const pondLabel = item.pondName || item.farmName || '';
          const isDanger = item.level === 'danger' || item.tone === 'danger';
          list.push({
            type: 'water-quality',
            tone: item.tone || (isDanger ? 'danger' : 'warning'),
            title: t(isDanger ? 'waterAlertTitleDanger' : 'waterAlertTitleCaution'),
            detailHtml: alertDetailHtml(
              pondLabel ? 'waterAlertShortDetailPond' : 'waterAlertShortDetail',
              { pond: pondLabel },
            ),
            actionLabel: t('alertReadDetails'),
            farmId: item.farmId,
            itemId: item.reportId || item.itemId,
            route: item.pondId && item.reportId
              ? {
                name: 'director-water-alert',
                params: { pondId: item.pondId, reportId: item.reportId },
              }
              : { name: 'director-fishery-ponds' },
          });
        });

        const workTitles = {
          'feed-reminder': 'workReminderFeedTitle',
          'water-test-reminder': 'workReminderWaterTitle',
          'weigh-reminder': 'workReminderWeighTitle',
          'fcr-high': 'fcrNormAlertTitle',
          'feed-sharp-change': 'feedSharpChangeAlertTitle',
          'survival-low': 'survivalLowAlertTitle',
        };
        const workAlerts = workRes?.data?.alerts || [];
        workAlerts.forEach((item) => {
          const routeName = item.route || 'director-fishery-ponds';
          let actionLabel = t('mainReport');
          if (item.type === 'feed-reminder') actionLabel = t('dailyReport');
          if (item.type === 'fcr-high' || item.type === 'feed-sharp-change' || item.type === 'survival-low') {
            actionLabel = t('reports');
          }
          const titleKey = workTitles[item.type];
          const route = item.pondId
            ? (
              item.type === 'water-quality' && item.reportId
                ? { name: 'director-water-alert', params: { pondId: item.pondId, reportId: item.reportId } }
                : { name: routeName, params: { pondId: item.pondId } }
            )
            : { name: 'director-fishery-ponds' };
          list.push({
            type: item.type,
            tone: item.tone || 'warning',
            title: titleKey
              ? t(titleKey)
              : (item.pondName || item.farmName || t('workReminderTitle')),
            detailHtml: alertDetailHtml(item.message_key, item.message_params || {}),
            actionLabel,
            farmId: item.farmId,
            itemId: item.itemId,
            route,
          });
        });
      } catch (e) {
        /* ignore */
      }

      alerts.value = list.slice(0, 15).map((alert) => ({
        ...alert,
        id: alertIdentity(alert),
      }));
      loadingAlerts.value = false;
    }

    function markBossMessageRead(alert) {
      if (alert?.type !== 'boss-message') return;
      window.axios.post('/director/boss-messages/read').catch(() => {});
    }

    function markRead(alert) {
      markAlertRead(userId.value, alert);
      markBossMessageRead(alert);
      readTick.value += 1;
    }

    function markAllRead() {
      unreadAlerts.value.forEach((alert) => {
        markAlertRead(userId.value, alert);
        markBossMessageRead(alert);
      });
      readTick.value += 1;
    }

    function toggleAlerts() {
      alertsOpen.value = !alertsOpen.value;
      if (alertsOpen.value) loadAlerts();
    }

    function goAlert(alert) {
      markRead(alert);
      alertsOpen.value = false;
      if (alert.farmId) setDirectorFarmId(alert.farmId);
      if (alert.route) router.push(alert.route);
    }

    function onDocClick(event) {
      const inBell = alertsRoot.value?.contains(event.target);
      const inPanel = panelRoot.value?.contains(event.target);
      if (!inBell && !inPanel) alertsOpen.value = false;
    }

    function onKeydown(event) {
      if (event.key === 'Escape') alertsOpen.value = false;
    }

    let pollTimer = null;

    function startAlertPoll() {
      stopAlertPoll();
      if (!props.showDirectorTools) return;
      loadAlerts();
      pollTimer = window.setInterval(loadAlerts, 20000);
    }

    function stopAlertPoll() {
      if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
      }
    }

    watch(() => props.showDirectorTools, (on) => {
      if (on) startAlertPoll();
      else {
        stopAlertPoll();
        alerts.value = [];
      }
    }, { immediate: true });

    onMounted(() => {
      document.addEventListener('click', onDocClick);
      document.addEventListener('keydown', onKeydown);
    });

    onBeforeUnmount(() => {
      stopAlertPoll();
      document.removeEventListener('click', onDocClick);
      document.removeEventListener('keydown', onKeydown);
    });

    return {
      accessInfo,
      showAlerts,
      unreadAlerts,
      alertsOpen,
      alertsRoot,
      panelRoot,
      loadingAlerts,
      toggleAlerts,
      goAlert,
      markRead,
      markAllRead,
    };
  },
};
</script>
