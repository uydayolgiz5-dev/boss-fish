<template>
  <DirectorShell>
    <div class="page-head page-head--toolbar">
      <strong v-if="pond?.name" class="page-head__context">{{ pond.name }}</strong>
      <router-link class="btn btn-outline btn-sm" :to="backLink">{{ $t('back') }}</router-link>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>

    <template v-else>
      <div v-if="loadError" class="panel empty error">
        <strong>{{ loadError }}</strong>
        <p>
          <router-link class="btn btn-outline btn-sm" :to="{ name: 'director-fishery-ponds' }">
            {{ $t('fishery') }}
          </router-link>
        </p>
      </div>
      <template v-else>
      <div v-if="isArchived" class="panel archive-banner">
        <div class="panel-head">
          <h2>{{ $t('finalReportTitle') }}</h2>
        </div>
        <p v-if="closureMeta?.closed_on">
          <strong>{{ $t('closeDate') }}:</strong> {{ formatDate(closureMeta.closed_on) }}
        </p>
      </div>

      <template v-if="finalReport">
        <section class="panel report-hero report-hero--fsr">
          <div class="report-hero__label">{{ $t('closureFsr') }}</div>
          <div class="report-hero__value">
            {{ formatPercent(finalReport.totals?.fsr_percent) }}
          </div>
          <p class="report-hero__hint">{{ $t('closureFsrShortHint') }} · {{ $t('closureRateFormulaHint') }}</p>
        </section>

        <section v-if="finalReport.feed?.fcr != null" class="panel report-hero report-hero--fcr">
          <div class="report-hero__label">{{ $t('fcr') }}</div>
          <div class="report-hero__value">
            {{ formatNumber(finalReport.feed.fcr, { decimals: 2 }) }}
          </div>
          <p class="report-hero__hint">{{ $t('closureFcrHint') }}</p>
        </section>

        <section class="panel closure-final-overview">
          <div class="panel-head">
            <div>
              <h2>{{ $t('closureFinalOverviewTitle') }}</h2>
              <small>{{ $t('closureFinalOverviewHint') }}</small>
            </div>
            <span
              class="badge"
              :class="finalReport.totals?.data_complete ? 'active' : 'badge-warn'"
            >
              {{ finalReport.totals?.data_complete ? $t('closureDataComplete') : $t('closureDataIncomplete') }}
            </span>
          </div>

          <div class="closure-final-period">
            <div>
              <span>{{ $t('closureStartedOn') }}</span>
              <strong>{{ formatDate(finalReport.started_on) }}</strong>
            </div>
            <span class="closure-final-period-arrow" aria-hidden="true"><svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg></span>
            <div>
              <span>{{ $t('closureEndedOn') }}</span>
              <strong>{{ formatDate(finalReport.closed_on) }}</strong>
            </div>
            <small v-if="finalReport.duration_days != null">
              {{ $t('closureDurationDays', { n: formatInt(finalReport.duration_days) }) }}
            </small>
          </div>

          <div class="closure-final-kpis">
            <div class="closure-final-kpi">
              <span>{{ $t('closureStocked') }}</span>
              <strong>{{ formatInt(finalReport.totals?.stocked) }}</strong>
              <small>{{ $t('pieces') }}</small>
            </div>
            <div class="closure-final-kpi closure-final-kpi--sold">
              <span>{{ $t('closureHarvested') }}</span>
              <strong>{{ formatInt(finalReport.totals?.sold) }}</strong>
              <small>{{ $t('pieces') }}</small>
            </div>
            <div class="closure-final-kpi closure-final-kpi--loss">
              <span>{{ $t('closureTotalLoss') }}</span>
              <strong>{{ formatInt(finalReport.totals?.total_loss) }}</strong>
              <small>{{ $t('pieces') }}</small>
            </div>
            <div class="closure-final-kpi closure-final-kpi--gap">
              <span>{{ $t('closurePlanGap') }}</span>
              <strong>{{ formatInt(finalReport.totals?.not_harvested) }}</strong>
              <small>{{ $t('pieces') }}</small>
            </div>
            <div class="closure-final-kpi closure-final-kpi--lsr">
              <span>{{ $t('closureLsr') }}</span>
              <strong>{{ formatPercent(finalReport.totals?.lsr_percent) }}</strong>
              <small>{{ $t('closureLsrShortHint') }}</small>
            </div>
          </div>
        </section>

        <section class="panel closure-final-finance">
          <div class="panel-head">
            <div>
              <h2>{{ $t('closureFinanceTitle') }}</h2>
              <small>{{ $t('closureFinanceHint') }}</small>
            </div>
          </div>
          <div class="metric-row">
            <div class="metric metric-highlight">
              <span>{{ $t('closureSalesRevenue') }}</span>
              <strong>{{ formatMoney(finalReport.financial?.sales_revenue) }}</strong>
            </div>
            <div class="metric">
              <span>{{ $t('closureSoldMass') }}</span>
              <strong>{{ formatKgMass(finalReport.mass?.sold_kg) }} kg</strong>
            </div>
            <div class="metric">
              <span>{{ $t('closureLossMass') }}</span>
              <strong>{{ formatKgMass(finalReport.mass?.loss_kg) }} kg</strong>
            </div>
          </div>
        </section>

        <section class="panel closure-final-feed">
          <div class="panel-head">
            <div>
              <h2>{{ $t('closureFeedResultTitle') }}</h2>
              <small>{{ $t('closureFeedResultHint') }}</small>
            </div>
          </div>
          <div class="metric-row">
            <div class="metric metric-highlight">
              <span>{{ $t('totalFeedUsed') }}</span>
              <strong>{{ formatKg(finalReport.feed?.total_kg) }} kg</strong>
            </div>
          </div>
        </section>
      </template>

      <div v-if="!finalReport && (pondAreaHa != null || fishDensityRows.length)" class="report-mini-cards">
        <article v-if="pondAreaHa != null" class="report-mini-card">
          <span class="report-mini-card__label">{{ $t('reportPondArea') }}</span>
          <strong class="report-mini-card__value">
            {{ formatNumber(pondAreaHa, { decimals: 2 }) }}
            <span>{{ $t('hectare') }}</span>
          </strong>
        </article>
        <article v-if="fishDensityRows.length" class="report-mini-card report-mini-card--density">
          <span class="report-mini-card__label">{{ $t('reportFishDensity') }}</span>
          <ul class="report-mini-card__list">
            <li v-for="row in fishDensityRows" :key="row.fish_type">
              <span>{{ row.label }}</span>
              <strong>≈ {{ formatInt(row.density) }} <small>{{ $t('reportSazanDensityUnit') }}</small></strong>
            </li>
          </ul>
        </article>
      </div>

      <div v-if="!finalReport" class="kpi-row">
        <div class="kpi kpi-count">
          <span class="kpi-label">{{ $t('totalFishCount') }}</span>
          <strong class="kpi-value">{{ formatInt(totals.fish_count) }}</strong>
          <span class="kpi-unit">{{ $t('pieces') }}</span>
        </div>
        <div class="kpi kpi-mass">
          <span class="kpi-label">{{ $t('totalFishMass') }}</span>
          <strong class="kpi-value">{{ formatKgMass(totals.total_mass_kg) }}</strong>
          <span class="kpi-unit">kg</span>
        </div>
        <div class="kpi kpi-feed">
          <span class="kpi-label">{{ $t('totalFeedUsed') }}</span>
          <strong class="kpi-value">{{ formatKg(totals.feed_total_kg) }}</strong>
          <span class="kpi-unit">kg</span>
        </div>
      </div>

      <section
        v-if="!finalReport && sazan"
        class="panel report-hero report-hero--fcr"
        :class="{ 'report-hero--fcr-warn': fcrAboveNorm }"
      >
        <div class="report-hero__label">
          {{ $t('fcr') }}
          <span v-if="fcrAboveNorm" class="report-hero__badge">{{ $t('fcrNormBadge') }}</span>
        </div>
        <div class="report-hero__value">
          {{ sazan.fcr !== null ? formatNumber(sazan.fcr, { decimals: 2 }) : '—' }}
        </div>
        <p class="report-hero__hint">
          <template v-if="sazan.fcr === null">{{ $t('notEnoughData') }}</template>
          <template v-else-if="fcrAboveNorm">{{ $t('fcrNormHint') }}</template>
          <template v-else>{{ $t('fcrHint') }}</template>
        </p>
      </section>

      <div class="panel">
        <div class="panel-head">
          <h2>{{ $t('fishGrowthBoardTitle') }}</h2>
          <small>{{ $t('fishGrowthBoardHint') }}</small>
        </div>

        <div v-if="!fishGrowth.length" class="empty">
          <strong>{{ $t('noFishYet') }}</strong>
          <p>{{ $t('addFishFirst') }}</p>
        </div>

        <FishGrowthBoard v-else :rows="fishGrowth" />

        <div v-if="fishMoves.length" class="table-scroll" style="margin-top: 1rem">
          <table class="report-table">
            <thead>
              <tr>
                <th>{{ $t('date') }}</th>
                <th>{{ $t('operation') }}</th>
                <th>{{ $t('fishType') }}</th>
                <th>{{ $t('fishCount') }}</th>
                <th>{{ $t('totalFishMass') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="move in fishMoves" :key="move.id">
                <td>{{ formatDate(move.operated_at) }}</td>
                <td>{{ moveTypeLabel(move.type) }}</td>
                <td>{{ move.label }}</td>
                <td class="num">{{ formatInt(move.quantity) }} {{ $t('pieces') }}</td>
                <td class="num">{{ move.total_weight_kg != null ? `${formatKgMass(move.total_weight_kg)} kg` : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="!finalReport && feedConsumption?.series?.length" class="panel panel--feed-trend">
        <FeedConsumptionTrend :data="feedConsumption" />
      </div>

      <div v-else-if="!finalReport" class="panel">
        <div class="panel-head">
          <h2>{{ $t('feedSummary') }}</h2>
          <small>{{ $t('feedSummaryHint') }}</small>
        </div>

        <div class="metric-row">
          <div class="metric metric-highlight">
            <span>{{ $t('totalFeedUsed') }}</span>
            <strong>{{ formatKg(totals.feed_total_kg) }} kg</strong>
          </div>
        </div>

        <div v-if="feedDays.length" class="table-scroll" style="margin-top: 1rem">
          <table class="report-table">
            <thead>
              <tr>
                <th>{{ $t('date') }}</th>
                <th>{{ $t('feedName') }}</th>
                <th>{{ $t('feedAmountKg') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(day, idx) in feedDays" :key="`${day.date}-${idx}`">
                <td>{{ formatDate(day.date) }}</td>
                <td>{{ day.feed_name || '—' }}</td>
                <td class="num">{{ formatKg(day.feed_total_kg ?? day.feeding_kg) }} kg</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head">
          <h2>{{ $t('waterTestsSectionTitle') }}</h2>
          <small>{{ $t('waterTestsSectionHint') }}</small>
        </div>

        <div v-if="waterTests.length" class="wq-legend water-tests-legend" aria-hidden="true">
          <span class="wq-legend__item wq-legend__item--ok">{{ $t('waterLevelOk') }}</span>
          <span class="wq-legend__item wq-legend__item--caution">{{ $t('waterLevelCaution') }}</span>
          <span class="wq-legend__item wq-legend__item--danger">{{ $t('waterLevelDanger') }}</span>
        </div>

        <div v-if="!waterTests.length" class="empty">{{ $t('waterTestsEmpty') }}</div>

        <div v-else class="water-tests-list">
          <article
            v-for="test in waterTests"
            :key="test.id"
            class="water-test-card"
            :class="`water-test-card--${test.level}`"
          >
            <header class="water-test-card__head">
              <div>
                <strong>{{ formatDate(test.report_date) }}</strong>
                <span v-if="test.test_time" class="water-test-card__time">{{ test.test_time }}</span>
              </div>
              <span class="water-test-card__badge" :class="`water-test-card__badge--${test.level}`">
                {{ waterTestLevelLabel(test.level) }}
              </span>
            </header>
            <small class="water-test-card__by">{{ $t('performedBy') }}: {{ test.submitted_by || '—' }}</small>
            <dl class="water-test-card__metrics">
              <div
                v-if="test.do_mg_l != null"
                :class="`water-test-metric--${test.levels?.do_mg_l || 'ok'}`"
              >
                <dt>{{ $t('doLevel') }}</dt>
                <dd>{{ test.do_mg_l }} mg/l</dd>
              </div>
              <div
                v-if="test.ph_level != null"
                :class="`water-test-metric--${test.levels?.ph_level || 'ok'}`"
              >
                <dt>{{ $t('phLevel') }}</dt>
                <dd>{{ test.ph_level }}</dd>
              </div>
              <div
                v-if="test.ammonia_mg_l != null"
                :class="`water-test-metric--${test.levels?.ammonia_mg_l || 'ok'}`"
              >
                <dt>{{ $t('ammonia') }}</dt>
                <dd>{{ test.ammonia_mg_l }} mg/l</dd>
              </div>
              <div
                v-if="test.nitrite_mg_l != null"
                :class="`water-test-metric--${test.levels?.nitrite_mg_l || 'ok'}`"
              >
                <dt>{{ $t('nitrite') }}</dt>
                <dd>{{ test.nitrite_mg_l }} mg/l</dd>
              </div>
              <div
                v-if="test.water_transparency_cm != null"
                :class="`water-test-metric--${test.levels?.water_transparency_cm || 'ok'}`"
              >
                <dt>{{ $t('waterTransparency') }}</dt>
                <dd>{{ test.water_transparency_cm }} cm</dd>
              </div>
            </dl>

            <div v-if="test.level === 'ok'" class="water-test-card__diag water-test-card__diag--ok">
              {{ $t('waterActionOk') }}
            </div>
            <div v-else class="water-test-card__diag">
              <ul>
                <li v-for="(line, idx) in test.diagnoses" :key="`${test.id}-d-${idx}`">{{ line }}</li>
              </ul>
              <p v-if="test.action" class="water-test-card__action">
                <strong>{{ $t('waterActionLabel') }}:</strong> {{ test.action }}
              </p>
            </div>

            <router-link
              v-if="!isArchived"
              class="btn btn-outline btn-sm"
              :to="{ name: 'director-water-alert', params: { pondId, reportId: test.id } }"
            >
              {{ $t('alertReadDetails') }}
            </router-link>
          </article>
        </div>
      </div>

      <div v-if="closureAnalysis?.fish_types?.length" class="panel">
        <div class="panel-head">
          <h2>{{ $t('closureLossSummary') }}</h2>
        </div>
        <div class="table-scroll">
          <table class="report-table">
            <thead>
              <tr>
                <th>{{ $t('fishType') }}</th>
                <th>{{ $t('added') }}</th>
                <th>{{ $t('sold') }}</th>
                <th>{{ $t('closureLossBefore') }}</th>
                <th>{{ $t('closureLossAtApproval') }}</th>
                <th>{{ $t('closureTotalLoss') }}</th>
                <th>{{ $t('closurePlanGap') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in closureAnalysis.fish_types" :key="row.fish_type">
                <td>
                  <FishTypeBadge :type="row.fish_type">
                    <strong>{{ row.label }}</strong>
                  </FishTypeBadge>
                </td>
                <td class="num">{{ formatInt(row.added) }}</td>
                <td class="num">{{ formatInt(row.sold) }}</td>
                <td class="num">{{ formatInt(row.loss_before_closure ?? row.loss) }}</td>
                <td class="num">{{ formatInt(row.closure_loss ?? row.remaining) }}</td>
                <td class="num">{{ formatInt(row.loss) }}</td>
                <td class="num">{{ formatInt(row.not_harvested ?? (row.added - row.sold)) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="report-history-toggle-wrap report-history-toggle-wrap--activity">
        <button
          class="btn btn-outline btn-block-mobile report-history-toggle report-history-toggle--activity"
          type="button"
          :aria-expanded="showActivityHistory"
          @click="showActivityHistory = !showActivityHistory"
        >
          {{ showActivityHistory ? $t('reportHideActivityHistory') : $t('reportShowActivityHistory') }}
          <span v-if="activities.length" class="report-history-count report-history-count--activity">
            {{ activities.length }}
          </span>
        </button>
      </div>

      <div v-if="showActivityHistory" class="panel report-history-panel report-history-panel--activity">
        <div class="panel-head">
          <h2>{{ isArchived ? $t('previousResults') : $t('history') }}</h2>
          <small>{{ $t('historyHint') }}</small>
        </div>

        <div v-if="!activities.length" class="empty">{{ $t('noActivity') }}</div>

        <div v-else class="table-scroll report-history-scroll">
          <table class="report-table finance-table report-history-table report-history-table--activity">
            <thead>
              <tr>
                <th>{{ $t('date') }}</th>
                <th>{{ $t('operation') }}</th>
                <th>{{ $t('performedBy') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in activities" :key="item.id">
                <td class="activity-date">{{ formatDateTime(item.performed_at) }}</td>
                <td>
                  <span class="activity-type" :class="skillTone(item.action)">
                    {{ actionLabel(item.action) }}
                  </span>
                  <strong class="activity-title">{{ item.title }}</strong>
                </td>
                <td>{{ item.user?.name || $t('system') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      </template>
    </template>
  </DirectorShell>
</template>

<script>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import FishGrowthBoard from '../../components/FishGrowthBoard.vue';
import FeedConsumptionTrend from '../../components/FeedConsumptionTrend.vue';
import FishTypeBadge from '../../components/FishTypeBadge.vue';
import { t } from '../../i18n';
import {
  formatGrams,
  formatInt,
  formatKg,
  formatKgMass,
  formatMoney,
  formatNumber,
  kgToGrams,
} from '../../utils/number';
import {
  evaluateWaterQuality,
  formatWaterAction,
  formatWaterIssue,
} from '../../utils/waterQuality';
import { formatDisplayDate, formatDisplayDateTime } from '../../utils/date';

const HIDDEN_DETAIL_KEYS = new Set([
  'wheat_bran_kg',
  'fertilizer_kg',
  'water_temp',
  'mortality_count',
  'fish_condition',
]);

const DETAIL_ORDER = [
  'report_date',
  'feed_name',
  'feeding_kg',
  'medicine_usage',
  'fish_type',
  'quantity',
  'total_weight_kg',
  'average_weight_g',
  'average_weight_kg',
  'unit_price',
  'amount',
  'test_time',
  'do_mg_l',
  'ph_level',
  'ammonia_mg_l',
  'nitrite_mg_l',
  'water_transparency_cm',
  'region',
  'area_m2',
  'notes',
];

export default {
  components: { DirectorShell, FishGrowthBoard, FeedConsumptionTrend, FishTypeBadge },
  setup() {
    const route = useRoute();
    const pondId = computed(() => route.params.pondId);
    const pond = ref(null);
    const activities = ref([]);
    const totals = ref({});
    const sazan = ref(null);
    const fishList = ref([]);
    const fishGrowth = ref([]);
    const feedConsumption = ref(null);
    const waterTests = ref([]);
    const loading = ref(true);
    const loadError = ref('');
    const fishMoves = ref([]);
    const showActivityHistory = ref(true);
    const isArchived = ref(false);
    const closureMeta = ref(null);
    const closureAnalysis = ref(null);
    const finalReport = ref(null);

    const backLink = computed(() => {
      if (isArchived.value && pond.value?.farm_id) {
        return { name: 'director-fishery-ponds' };
      }
      return { name: 'director-fishery', params: { pondId: pondId.value } };
    });

    const feedDays = computed(() => feedConsumption.value?.series || []);

    // area_m2 maydoni aslida gektarda saqlanadi
    const pondAreaHa = computed(() => {
      const ha = Number(pond.value?.area_m2);
      return Number.isFinite(ha) && ha > 0 ? ha : null;
    });

    const fishDensityRows = computed(() => {
      if (pondAreaHa.value == null) return [];
      return fishList.value
        .map((item) => {
          const pieces = Number(item.added || item.count || 0);
          if (!Number.isFinite(pieces) || pieces <= 0) return null;
          return {
            fish_type: item.fish_type,
            label: item.label || t(fishTypeLabels[item.fish_type] || item.fish_type),
            density: Math.round(pieces / pondAreaHa.value),
          };
        })
        .filter(Boolean);
    });

    const FCR_NORM_MAX = 2.4;
    const fcrAboveNorm = computed(() => {
      const value = Number(sazan.value?.fcr);
      return Number.isFinite(value) && value > FCR_NORM_MAX;
    });

    const fishTotals = computed(() => fishList.value.reduce(
      (acc, row) => ({
        added: acc.added + (row.added || 0),
        removed: acc.removed + (row.removed || 0),
        count: acc.count + (row.count || 0),
      }),
      { added: 0, removed: 0, count: 0 },
    ));

    const labels = {
      report_date: 'reportDate',
      fish_type: 'fishType',
      quantity: 'fishCount',
      total_weight_kg: 'totalWeightKg',
      average_weight_kg: 'averageWeight',
      average_weight_g: 'averageWeight',
      unit_price: 'salePricePerKg',
      amount: 'amount',
      feed_name: 'feedName',
      water_temp: 'waterTemperature',
      ph_level: 'pH',
      feeding_kg: 'feed',
      wheat_bran_kg: 'wheatBranKg',
      fertilizer_kg: 'fertilizer',
      medicine_usage: 'dailyReportMedicineUsed',
      test_time: 'testTime',
      do_mg_l: 'doLevel',
      ammonia_mg_l: 'ammonia',
      nitrite_mg_l: 'nitrite',
      water_transparency_cm: 'waterTransparency',
      mortality_count: 'mortalityShort',
      fish_condition: 'fishCondition',
      region: 'region',
      area_m2: 'area',
      notes: 'notes',
    };

    const units = {
      average_weight_g: ' g',
      average_weight_kg: ' g',
      unit_price: t('currencySuffix'),
      total_weight_kg: ' kg',
      amount: t('currencySuffix'),
      water_temp: ' °C',
      feeding_kg: ' kg',
      wheat_bran_kg: ' kg',
      fertilizer_kg: ' kg',
      do_mg_l: ' mg/l',
      ammonia_mg_l: ' mg/l',
      nitrite_mg_l: ' mg/l',
      water_transparency_cm: ' cm',
      area_m2: ` ${t('hectare')}`,
    };

    const fishTypeLabels = {
      sazan: 'fishSazan',
      tolstolob: 'fishTolstolob',
      oq_amur: 'fishWhiteAmur',
      targil_tolstolob: 'fishBighead',
    };

    const actionKeys = {
      pond_created: 'actionPondCreated',
      daily_report: 'actionDailyReport',
      daily_report_updated: 'actionDailyReport',
      daily_report_deleted: 'actionDailyReport',
      main_report: 'actionMainReport',
      main_report_updated: 'actionMainReport',
      main_report_deleted: 'actionMainReport',
      fish_add: 'actionFishAdd',
      fish_add_updated: 'actionFishAdd',
      fish_sale: 'actionFishSale',
      fish_sale_updated: 'actionFishSale',
      fish_loss: 'actionFishLoss',
      fish_loss_updated: 'actionFishLoss',
    };

    function formatDetailValue(key, value) {
      if (key === 'fish_type') {
        return fishTypeLabels[value] ? t(fishTypeLabels[value]) : value;
      }

      if (key === 'quantity') {
        return `${formatInt(value)} ${t('pieces')}`;
      }

      if (key === 'average_weight_g') {
        return `${formatGrams(value)} g`;
      }

      if (key === 'average_weight_kg') {
        return `${formatGrams(kgToGrams(value))} g`;
      }

      if (key === 'total_weight_kg') {
        return `${formatKgMass(value)} kg`;
      }

      if (key === 'fish_weights' && Array.isArray(value)) {
        return value.map((row) => {
          const name = fishTypeLabels[row.fish_type] ? t(fishTypeLabels[row.fish_type]) : row.fish_type;
          const grams = row.average_weight_g ?? kgToGrams(row.average_weight_kg);
          return `${name}: ${formatGrams(grams)} g`;
        }).join(' · ');
      }

      if (['feeding_kg', 'wheat_bran_kg', 'fertilizer_kg'].includes(key)) {
        return `${formatKg(value)} kg`;
      }

      if (key === 'amount' || key === 'unit_price') {
        return `${formatInt(value)} so‘m`;
      }

      return `${value}${units[key] || ''}`;
    }

    function detailRows(item) {
      const details = { ...(item.details || {}) };
      const bran = Number(details.wheat_bran_kg || 0);
      if (bran > 0) {
        details.feeding_kg = Number(details.feeding_kg || 0) + bran;
      }
      const keys = Object.keys(details).filter(
        (key) => !HIDDEN_DETAIL_KEYS.has(key)
          && details[key] !== null
          && details[key] !== ''
          && details[key] !== undefined,
      );

      keys.sort((a, b) => {
        const ai = DETAIL_ORDER.indexOf(a);
        const bi = DETAIL_ORDER.indexOf(b);
        if (ai === -1 && bi === -1) return a.localeCompare(b);
        if (ai === -1) return 1;
        if (bi === -1) return -1;
        return ai - bi;
      });

      return keys.map((key) => {
        if (key === 'fish_weights' && Array.isArray(details[key])) {
          return {
            key,
            label: t('averageWeight'),
            value: formatDetailValue(key, details[key]),
          };
        }

        return {
          key,
          label: labels[key] ? t(labels[key]) : key,
          value: formatDetailValue(key, details[key]),
        };
      });
    }

    function roleLabel(role) {
      return ({ owner: t('owner'), director: t('director'), worker: t('worker') }[role] || role || t('system'));
    }

    function moveTypeLabel(type) {
      return ({
        add: t('actionFishAdd'),
        sale: t('actionFishSale'),
        loss: t('actionFishLoss'),
      }[type] || t('actionOther'));
    }

    function actionLabel(action) {
      if (!action) return t('actionOther');
      const key = actionKeys[action];
      if (key) return t(key);
      if (action.includes('daily')) return t('actionDailyReport');
      if (action.includes('main')) return t('actionMainReport');
      if (action.includes('fish_add')) return t('actionFishAdd');
      if (action.includes('sale')) return t('actionFishSale');
      if (action.includes('loss')) return t('actionFishLoss');
      return t('actionOther');
    }

    function skillTone(action) {
      if (!action) return 'tone-neutral';
      if (action.includes('daily')) return 'tone-daily';
      if (action.includes('main')) return 'tone-main';
      if (action.includes('fish_add') || action.includes('pond')) return 'tone-add';
      if (action.includes('sale')) return 'tone-sale';
      if (action.includes('loss') || action.includes('deleted')) return 'tone-loss';
      return 'tone-neutral';
    }

    function formatDate(value) {
      if (!value) return '—';
      const formatted = formatDisplayDate(value);
      return formatted || '—';
    }

    function formatDateTime(value) {
      if (!value) return '—';
      const formatted = formatDisplayDateTime(value);
      return formatted || '—';
    }

    function formatPercent(value) {
      const number = Number(value);
      return Number.isFinite(number) ? `${formatNumber(number, { decimals: 1 })}%` : '—';
    }

    function enrichWaterTests(list) {
      return (list || []).map((test) => {
        const assessment = evaluateWaterQuality({
          test_time: test?.test_time,
          do_mg_l: test?.do_mg_l,
          ph_level: test?.ph_level,
          ammonia_mg_l: test?.ammonia_mg_l,
          nitrite_mg_l: test?.nitrite_mg_l,
          water_transparency_cm: test?.water_transparency_cm,
        });
        const diagnoses = (assessment.issues || [])
          .map((issue) => formatWaterIssue(issue, t))
          .filter(Boolean);
        return {
          ...test,
          level: assessment.level || 'ok',
          levels: assessment.levels || {},
          issues: assessment.issues || [],
          diagnoses,
          action: formatWaterAction(assessment.primary_action_key, t),
        };
      });
    }

    function waterTestLevelLabel(level) {
      if (level === 'danger') return t('waterLevelDanger');
      if (level === 'caution') return t('waterLevelCaution');
      return t('waterLevelOk');
    }

    function applySummary(summaryRes) {
      const data = summaryRes.data || {};
      pond.value = data.pond;
      totals.value = data.totals || {};
      sazan.value = data.sazan;
      fishList.value = data.fish_list || data.fish_stock || [];
      fishMoves.value = data.fish_moves || [];
      fishGrowth.value = data.fish_growth || fishList.value.map((row) => ({
        fish_type: row.fish_type,
        label: row.label,
        count: row.count,
        added: row.added,
        removed: row.removed,
        mass_kg: row.mass_kg,
        initial_weight_g: row.initial_weight_g,
        current_weight_g: row.average_weight_g,
        gain_per_fish_g: null,
        growth_days: 0,
        daily_growth_per_fish_g: null,
        has_weighing: false,
        series: row.initial_weight_g != null ? [{
          date: row.initial_date || null,
          weight_g: row.initial_weight_g,
          source: 'stock_add',
        }].filter((item) => item.date) : [],
      }));
      feedConsumption.value = data.feed_consumption || null;
    }

    async function loadSummary() {
      try {
        const archiveRes = await window.axios.get(`/director/ponds/${pondId.value}/closure-archive`);
        isArchived.value = true;
        closureMeta.value = archiveRes.data.closure || null;
        const archive = archiveRes.data.archive || {};
        closureAnalysis.value = archive.closure_analysis || null;
        finalReport.value = archive.closure_final_report || null;
        return {
          data: {
            ...archive,
            pond: {
              ...(archive.pond || {}),
              ...(archiveRes.data.pond || {}),
            },
          },
        };
      } catch (archiveError) {
        if (archiveError.response?.status !== 404) {
          throw archiveError;
        }
        isArchived.value = false;
        closureMeta.value = null;
        closureAnalysis.value = null;
        finalReport.value = null;
        return window.axios.get(`/director/ponds/${pondId.value}/summary`);
      }
    }

    async function loadWaterTests(summaryData) {
      let rawWaterTests = summaryData?.water_tests || [];
      if (!rawWaterTests.length) {
        try {
          const mainRes = await window.axios.get(`/director/ponds/${pondId.value}/main-reports`);
          const reports = mainRes.data.reports?.data || mainRes.data.reports || [];
          rawWaterTests = (reports || [])
            .filter((r) => r.do_mg_l != null || r.ph_level != null || r.ammonia_mg_l != null
              || r.nitrite_mg_l != null || r.water_transparency_cm != null)
            .map((r) => ({
              id: r.id,
              report_date: String(r.report_date || '').slice(0, 10),
              test_time: r.test_time ? String(r.test_time).slice(0, 5) : null,
              do_mg_l: r.do_mg_l,
              ph_level: r.ph_level,
              ammonia_mg_l: r.ammonia_mg_l,
              nitrite_mg_l: r.nitrite_mg_l,
              water_transparency_cm: r.water_transparency_cm,
              submitted_by: r.user?.name || null,
            }));
        } catch (e) {
          /* ignore */
        }
      }
      waterTests.value = enrichWaterTests(rawWaterTests);
    }

    async function load() {
      loading.value = true;
      loadError.value = '';
      try {
        const [activityRes, summaryRes] = await Promise.allSettled([
          window.axios.get(`/director/ponds/${pondId.value}/activities`),
          loadSummary(),
        ]);

        if (summaryRes.status === 'fulfilled') {
          applySummary(summaryRes.value);
          await loadWaterTests(summaryRes.value.data);
        } else {
          const status = summaryRes.reason?.response?.status;
          loadError.value = status === 404
            ? t('pondReportNotFound')
            : (summaryRes.reason?.response?.data?.message || t('reportLoadError'));
        }

        if (activityRes.status === 'fulfilled') {
          const payload = activityRes.value.data;
          activities.value = (payload.data || payload || [])
            .slice()
            .sort((a, b) => (b.id || 0) - (a.id || 0));
        } else {
          activities.value = [];
        }
      } finally {
        loading.value = false;
      }
    }

    watch(pondId, load, { immediate: true });

    return {
      pondId,
      pond,
      activities,
      totals,
      sazan,
      fishList,
      fishGrowth,
      feedConsumption,
      waterTests,
      fishTotals,
      pondAreaHa,
      fishDensityRows,
      fcrAboveNorm,
      loading,
      loadError,
      fishMoves,
      feedDays,
      showActivityHistory,
      isArchived,
      closureMeta,
      closureAnalysis,
      finalReport,
      backLink,
      formatInt,
      formatNumber,
      formatGrams,
      formatKg,
      formatKgMass,
      formatMoney,
      formatPercent,
      detailRows,
      roleLabel,
      moveTypeLabel,
      actionLabel,
      skillTone,
      formatDate,
      formatDateTime,
      waterTestLevelLabel,
    };
  },
};
</script>
