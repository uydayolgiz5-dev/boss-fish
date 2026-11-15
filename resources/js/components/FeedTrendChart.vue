<template>
  <div class="feed-trend-chart">
    <svg
      class="feed-trend-chart__svg"
      :viewBox="`0 0 ${width} ${height}`"
      preserveAspectRatio="none"
      role="img"
      :aria-label="$t('feedTrendChartAria')"
    >
      <defs>
        <linearGradient :id="fillId" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" :stop-color="strokeColor" stop-opacity="0.22" />
          <stop offset="100%" :stop-color="strokeColor" stop-opacity="0.02" />
        </linearGradient>
      </defs>

      <line
        v-for="(tick, index) in gridTicks"
        :key="`grid-${index}`"
        :x1="padX"
        :y1="tick.y"
        :x2="width - padX"
        :y2="tick.y"
        class="feed-trend-chart__grid"
      />

      <path
        v-if="areaPath"
        :d="areaPath"
        class="feed-trend-chart__area"
        :fill="`url(#${fillId})`"
      />

      <path
        v-if="linePath"
        :d="linePath"
        class="feed-trend-chart__line"
        :stroke="strokeColor"
        fill="none"
      />

      <circle
        v-for="(point, index) in visibleDots"
        :key="`dot-${index}`"
        :cx="point.x"
        :cy="point.y"
        :r="point.r"
        class="feed-trend-chart__dot"
        :class="{ 'feed-trend-chart__dot--edge': point.edge }"
        :stroke="strokeColor"
      />
    </svg>

    <div class="feed-trend-chart__compare">
      <div class="feed-trend-chart__side">
        <span class="feed-trend-chart__marker-label">{{ $t('feedTrendStart') }}</span>
        <strong>{{ formatKg(firstDaily) }} kg</strong>
        <small v-if="firstDate">{{ formatDisplayDate(firstDate) }}</small>
      </div>
      <span class="feed-trend-chart__arrow" aria-hidden="true">→</span>
      <div class="feed-trend-chart__side feed-trend-chart__side--end">
        <span class="feed-trend-chart__marker-label">{{ $t('feedTrendNow') }}</span>
        <strong>{{ formatKg(latestDaily) }} kg</strong>
        <small v-if="lastDate">{{ formatDisplayDate(lastDate) }}</small>
      </div>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue';
import { formatKg } from '../utils/number';
import { formatDisplayDate } from '../utils/date';

const WIDTH = 360;
const HEIGHT = 132;
const PAD_X = 14;
const PAD_Y = 18;

/** Catmull-Rom → cubic Bezier (yumshoq trading chiziq) */
function smoothLinePath(points) {
  if (points.length < 2) return '';
  if (points.length === 2) {
    return `M ${points[0].x.toFixed(2)} ${points[0].y.toFixed(2)} L ${points[1].x.toFixed(2)} ${points[1].y.toFixed(2)}`;
  }

  let d = `M ${points[0].x.toFixed(2)} ${points[0].y.toFixed(2)}`;
  for (let i = 0; i < points.length - 1; i += 1) {
    const p0 = points[i - 1] || points[i];
    const p1 = points[i];
    const p2 = points[i + 1];
    const p3 = points[i + 2] || p2;
    const cp1x = p1.x + (p2.x - p0.x) / 6;
    const cp1y = p1.y + (p2.y - p0.y) / 6;
    const cp2x = p2.x - (p3.x - p1.x) / 6;
    const cp2y = p2.y - (p3.y - p1.y) / 6;
    d += ` C ${cp1x.toFixed(2)} ${cp1y.toFixed(2)}, ${cp2x.toFixed(2)} ${cp2y.toFixed(2)}, ${p2.x.toFixed(2)} ${p2.y.toFixed(2)}`;
  }
  return d;
}

export default {
  props: {
    series: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
  },
  setup(props) {
    const width = WIDTH;
    const height = HEIGHT;
    const padX = PAD_X;
    const padY = PAD_Y;
    const fillId = `feed-fill-${Math.random().toString(36).slice(2, 9)}`;

    const chartSeries = computed(() => (
      (props.series || []).filter((row) => row.date && row.feed_total_kg != null)
    ));

    const firstDaily = computed(() => chartSeries.value[0]?.feed_total_kg ?? 0);
    const latestDaily = computed(() => (
      props.summary?.latest_daily_kg ?? chartSeries.value[chartSeries.value.length - 1]?.feed_total_kg ?? 0
    ));
    const firstDate = computed(() => props.summary?.first_date ?? chartSeries.value[0]?.date ?? null);
    const lastDate = computed(() => props.summary?.last_date ?? chartSeries.value[chartSeries.value.length - 1]?.date ?? null);

    const strokeColor = computed(() => {
      const direction = props.summary?.trend_direction;
      if (direction === 'up') return '#1f7a3d';
      if (direction === 'down') return '#c2410c';
      return '#475569';
    });

    // Teng taqsim: har bir yozuv = bir qadam (sana oralig‘iga bog‘lanmaydi)
    const plottedPoints = computed(() => {
      const rows = chartSeries.value;
      if (!rows.length) return [];

      const values = rows.map((row) => Number(row.feed_total_kg));
      const minV = Math.min(...values);
      const maxV = Math.max(...values);
      const vRange = Math.max(maxV - minV, maxV * 0.12, 1);
      const vBase = minV - vRange * 0.1;
      const vCeil = maxV + vRange * 0.08;
      const span = Math.max(vCeil - vBase, 1);
      const innerW = width - padX * 2;
      const innerH = height - padY * 2;
      const step = rows.length === 1 ? 0 : innerW / (rows.length - 1);

      return rows.map((row, index) => {
        const x = rows.length === 1 ? padX + innerW / 2 : padX + step * index;
        const y = height - padY - ((Number(row.feed_total_kg) - vBase) / span) * innerH;
        return { x, y, index, ...row };
      });
    });

    const linePath = computed(() => smoothLinePath(plottedPoints.value));

    const areaPath = computed(() => {
      const points = plottedPoints.value;
      if (points.length < 2 || !linePath.value) return '';
      const baseline = height - padY;
      const first = points[0];
      const last = points[points.length - 1];
      return `${linePath.value} L ${last.x.toFixed(2)} ${baseline} L ${first.x.toFixed(2)} ${baseline} Z`;
    });

    // O‘zgarishlar ko‘paysa nuqtalar zichlashadi, lekin radiusi kichrayadi
    const visibleDots = computed(() => {
      const points = plottedPoints.value;
      const n = points.length;
      if (!n) return [];

      const rMid = Math.max(1.6, Math.min(3.2, 26 / Math.sqrt(n)));
      const rEdge = Math.max(2.6, Math.min(4.2, rMid + 1.1));

      // Ko‘p nuqta bo‘lsa — faqat muhimlari (boshi, oxiri, sezilarli sakrash)
      if (n > 14) {
        const picked = new Set([0, n - 1]);
        for (let i = 1; i < n - 1; i += 1) {
          const prev = Number(points[i - 1].feed_total_kg);
          const curr = Number(points[i].feed_total_kg);
          const next = Number(points[i + 1].feed_total_kg);
          const jump = Math.abs(curr - prev);
          const turn = Math.abs((curr - prev) - (next - curr));
          if (jump >= Math.max(prev, 1) * 0.18 || turn >= Math.max(curr, 1) * 0.22) {
            picked.add(i);
          }
        }
        // Zichlik: kamida har ~n/8 qadamda bitta nuqta
        const stride = Math.max(1, Math.ceil(n / 8));
        for (let i = 0; i < n; i += stride) picked.add(i);

        return [...picked].sort((a, b) => a - b).map((i) => ({
          ...points[i],
          r: i === 0 || i === n - 1 ? rEdge : rMid,
          edge: i === 0 || i === n - 1,
        }));
      }

      return points.map((point, i) => ({
        ...point,
        r: i === 0 || i === n - 1 ? rEdge : rMid,
        edge: i === 0 || i === n - 1,
      }));
    });

    const gridTicks = computed(() => {
      const top = padY;
      const mid = height / 2;
      const bottom = height - padY;
      return [{ y: top }, { y: mid }, { y: bottom }];
    });

    return {
      width,
      height,
      padX,
      fillId,
      firstDaily,
      latestDaily,
      firstDate,
      lastDate,
      strokeColor,
      plottedPoints,
      visibleDots,
      linePath,
      areaPath,
      gridTicks,
      formatKg,
      formatDisplayDate,
    };
  },
};
</script>
