<template>
  <article class="feed-trend-card">
    <header class="feed-trend-card__head">
      <div class="feed-trend-card__meta">
        <strong>{{ $t('feedConsumptionTrendTitle') }}</strong>
        <span>
          {{ formatInt(summary.days_with_feed) }} {{ $t('days') }} ·
          {{ $t('feedCumulativeShort') }} {{ formatKg(summary.total_kg) }} kg
        </span>
      </div>
      <span class="feed-trend-badge" :class="`feed-trend-badge--${summary.trend_direction || 'stable'}`">
        {{ trendBadgeLabel }}
      </span>
    </header>

    <FeedTrendChart :series="series" :summary="summary" />

    <p class="feed-trend-card__avg">
      {{ $t('feedTrendAvgLine', { kg: formatKg(summary.average_daily_kg) }) }}
      <span v-if="trendExplain"> · {{ trendExplain }}</span>
    </p>

    <div class="feed-trend-days">
      <div class="feed-trend-days__head">
        <span>{{ $t('feedDailyBreakdown') }}</span>
        <small>{{ $t('feedDailyBreakdownHint') }}</small>
      </div>

      <ul class="feed-trend-list">
        <li v-for="row in reversedSeries" :key="row.date" class="feed-trend-list__row">
          <div class="feed-trend-list__date">
            <strong>{{ formatDisplayDate(row.date) }}</strong>
            <small v-if="row.feed_name">{{ row.feed_name }}</small>
          </div>
          <div class="feed-trend-list__amount">
            <strong>{{ formatKg(row.feed_total_kg) }} kg</strong>
            <span
              v-if="row.delta_kg != null"
              class="feed-trend-delta"
              :class="deltaClass(row.delta_kg)"
            >
              {{ formatDelta(row) }}
            </span>
            <span v-else class="feed-trend-delta feed-trend-delta--flat">—</span>
          </div>
        </li>
      </ul>
    </div>
  </article>
</template>

<script>
import { computed } from 'vue';
import FeedTrendChart from './FeedTrendChart.vue';
import { formatInt, formatKg, formatNumber } from '../utils/number';
import { formatDisplayDate } from '../utils/date';
import { t } from '../i18n';

export default {
  components: { FeedTrendChart },
  props: {
    data: { type: Object, required: true },
  },
  setup(props) {
    const series = computed(() => props.data?.series || []);
    const summary = computed(() => props.data?.summary || {});
    const reversedSeries = computed(() => [...series.value].reverse());

    const trendBadgeLabel = computed(() => {
      const direction = summary.value.trend_direction;
      if (direction === 'up') return t('feedTrendUp');
      if (direction === 'down') return t('feedTrendDown');
      return t('feedTrendStable');
    });

    const trendExplain = computed(() => {
      const direction = summary.value.trend_direction;
      const percent = summary.value.trend_percent;
      if (percent == null) return '';
      const abs = Math.abs(Number(percent));
      if (direction === 'up') return t('feedTrendChangeUp', { percent: abs });
      if (direction === 'down') return t('feedTrendChangeDown', { percent: abs });
      return t('feedTrendChangeFlat');
    });

    function deltaClass(delta) {
      if (delta > 0) return 'feed-trend-delta--up';
      if (delta < 0) return 'feed-trend-delta--down';
      return 'feed-trend-delta--flat';
    }

    function formatDelta(row) {
      const sign = row.delta_kg > 0 ? '+' : '';
      return `${sign}${formatNumber(row.delta_kg, { decimals: 1 })} kg`;
    }

    return {
      series,
      summary,
      reversedSeries,
      trendBadgeLabel,
      trendExplain,
      formatInt,
      formatKg,
      formatDisplayDate,
      deltaClass,
      formatDelta,
    };
  },
};
</script>
