<template>
  <div class="op-visuals">
    <section class="panel op-form op-visuals__algo">
      <h2>{{ $t('ownerProektAlgoTitle') }}</h2>
      <p class="op-lead">{{ $t('ownerProektAlgoLead') }}</p>
      <ol class="op-algo">
        <li
          v-for="(step, i) in algoSteps"
          :key="step.key"
          class="op-algo__step"
          :class="`op-algo__step--${step.tone}`"
        >
          <span class="op-algo__n">{{ i + 1 }}</span>
          <div>
            <strong>{{ step.title }}</strong>
            <p>{{ step.text }}</p>
          </div>
        </li>
      </ol>
    </section>

    <section class="panel op-form op-visuals__pond">
      <div class="op-visuals__pond-head">
        <div>
          <h2>{{ $t('ownerProektPondMapTitle') }}</h2>
          <p class="op-lead">{{ $t('ownerProektPondMapLead') }}</p>
        </div>
        <div class="op-pond-controls">
          <label>
            <span>{{ $t('ownerProektPondAspect') }}</span>
            <select v-model.number="layout.aspect">
              <option :value="1.5">1.5× (kvadratga yaqin)</option>
              <option :value="2">2×</option>
              <option :value="2.5">2.5×</option>
              <option :value="3">3× (uzun)</option>
              <option :value="4">4× (juda uzun)</option>
            </select>
          </label>
          <label class="op-check-inline">
            <input v-model="layout.showInlet" type="checkbox">
            <span>{{ $t('ownerProektPondInlet') }}</span>
          </label>
          <label class="op-check-inline">
            <input v-model="layout.showOutlet" type="checkbox">
            <span>{{ $t('ownerProektPondOutlet') }}</span>
          </label>
        </div>
      </div>

      <div class="op-pond-stage">
        <svg
          class="op-pond-svg"
          :viewBox="`0 0 ${vb.w} ${vb.h}`"
          role="img"
          :aria-label="$t('ownerProektPondMapTitle')"
        >
          <!-- Berm / qirg‘oq -->
          <rect
            :x="berm"
            :y="berm"
            :width="vb.w - berm * 2"
            :height="vb.h - berm * 2"
            rx="18"
            class="op-pond-berm"
          />
          <!-- Suv -->
          <rect
            :x="water.x"
            :y="water.y"
            :width="water.w"
            :height="water.h"
            rx="12"
            class="op-pond-water"
          />
          <!-- Zonalar -->
          <g v-for="z in zones" :key="z.key">
            <rect
              :x="z.x"
              :y="z.y"
              :width="z.w"
              :height="z.h"
              :class="`op-pond-zone op-pond-zone--${z.key}`"
              rx="8"
            />
            <image
              v-if="z.img"
              :href="z.img"
              :x="z.x + z.w / 2 - 28"
              :y="z.y + z.h / 2 - 36"
              width="56"
              height="36"
              preserveAspectRatio="xMidYMid meet"
            />
            <text
              :x="z.x + z.w / 2"
              :y="z.y + z.h / 2 + 18"
              text-anchor="middle"
              class="op-pond-zone__label"
            >{{ z.label }}</text>
            <text
              :x="z.x + z.w / 2"
              :y="z.y + z.h / 2 + 36"
              text-anchor="middle"
              class="op-pond-zone__meta"
            >{{ z.meta }}</text>
          </g>

          <!-- Kirish / chiqish -->
          <g v-if="layout.showInlet" class="op-pond-io">
            <path :d="inletPath" class="op-pond-io__pipe" />
            <text :x="water.x - 8" :y="water.y + 22" text-anchor="end" class="op-pond-io__txt">
              {{ $t('ownerProektPondInlet') }}
            </text>
          </g>
          <g v-if="layout.showOutlet" class="op-pond-io">
            <path :d="outletPath" class="op-pond-io__pipe op-pond-io__pipe--out" />
            <text :x="water.x + water.w + 8" :y="water.y + water.h - 12" class="op-pond-io__txt">
              {{ $t('ownerProektPondOutlet') }}
            </text>
          </g>

          <!-- O‘lchamlar -->
          <text :x="vb.w / 2" :y="vb.h - 10" text-anchor="middle" class="op-pond-dim">
            {{ dimLabel }}
          </text>
        </svg>
      </div>

      <ul class="op-pond-legend">
        <li v-for="z in zones" :key="`lg-${z.key}`" :class="`is-${z.key}`">
          <img v-if="z.img" :src="z.img" :alt="z.label" width="40" height="26">
          <span>
            <strong>{{ z.label }}</strong>
            — {{ z.sharePct }}% · {{ z.meta }}
            <em v-if="z.usesFeed"> · FCR</em>
          </span>
        </li>
        <li v-if="!zones.length" class="muted">{{ $t('ownerProektWarnNoFish') }}</li>
      </ul>
    </section>
  </div>
</template>

<script>
import { computed, reactive, watch } from 'vue';
import { t } from '../i18n';
import { formatNumber } from '../utils/number';
import { FEED_FISH_KEYS, PROEKT_FISH_IMG, fcrForMode } from '../config/ownerProekt';

export default {
  name: 'OwnerProektVisuals',
  props: {
    inputs: { type: Object, required: true },
    result: { type: Object, required: true },
  },
  setup(props) {
    const layout = reactive({
      aspect: Number(props.inputs.layout?.aspect) || 2.5,
      showInlet: props.inputs.layout?.showInlet !== false,
      showOutlet: props.inputs.layout?.showOutlet !== false,
    });

    watch(
      layout,
      () => {
        props.inputs.layout = {
          aspect: layout.aspect,
          showInlet: layout.showInlet,
          showOutlet: layout.showOutlet,
        };
      },
      { deep: true },
    );

    watch(
      () => props.inputs.layout,
      (v) => {
        if (!v) return;
        if (v.aspect != null) layout.aspect = Number(v.aspect) || 2.5;
        if (v.showInlet != null) layout.showInlet = !!v.showInlet;
        if (v.showOutlet != null) layout.showOutlet = !!v.showOutlet;
      },
      { deep: true },
    );

    const berm = 28;
    const pad = 22;
    const vb = { w: 640, h: 320 };

    const water = computed(() => ({
      x: berm + pad,
      y: berm + pad,
      w: vb.w - (berm + pad) * 2,
      h: vb.h - (berm + pad) * 2 - 18,
    }));

    const dims = computed(() => {
      const areaM2 = Math.max(Number(props.inputs.areaHa) || 0, 0) * 10000;
      const aspect = Math.max(layout.aspect || 2.5, 1.1);
      const widthM = areaM2 > 0 ? Math.sqrt(areaM2 / aspect) : 0;
      const lengthM = widthM * aspect;
      return {
        lengthM: Math.round(lengthM),
        widthM: Math.round(widthM),
        areaHa: Number(props.inputs.areaHa) || 0,
      };
    });

    const dimLabel = computed(() => {
      if (!dims.value.areaHa) return t('ownerProektPondNeedArea');
      return t('ownerProektPondDims', {
        l: formatNumber(dims.value.lengthM, { decimals: 0 }),
        w: formatNumber(dims.value.widthM, { decimals: 0 }),
        ha: formatNumber(dims.value.areaHa),
      });
    });

    const zones = computed(() => {
      const species = props.result?.species || [];
      const total = species.reduce((s, x) => s + (x.count || 0), 0);
      if (!total) return [];
      const w = water.value;
      let x = w.x;
      return species.map((s) => {
        const share = (s.count || 0) / total;
        const zw = Math.max(w.w * share, share > 0 ? 36 : 0);
        const zone = {
          key: s.key,
          label: t(s.labelKey),
          img: PROEKT_FISH_IMG[s.key] || null,
          usesFeed: FEED_FISH_KEYS.includes(s.key),
          sharePct: Math.round(share * 100),
          meta: `${formatNumber(s.count, { decimals: 0 })} · ${formatNumber(s.harvestKg)} kg`,
          x,
          y: w.y + 6,
          w: zw - 4,
          h: w.h - 12,
        };
        x += zw;
        return zone;
      }).filter((z) => z.w > 0);
    });

    const inletPath = computed(() => {
      const w = water.value;
      return `M ${w.x - 36} ${w.y + 10} L ${w.x} ${w.y + 10} L ${w.x} ${w.y + 28}`;
    });

    const outletPath = computed(() => {
      const w = water.value;
      return `M ${w.x + w.w} ${w.y + w.h - 28} L ${w.x + w.w} ${w.y + w.h - 10} L ${w.x + w.w + 36} ${w.y + w.h - 10}`;
    });

    const algoSteps = computed(() => {
      const fcr = props.result?.fcr ?? fcrForMode(props.inputs.mode);
      const gain = formatNumber(props.result?.production?.feedGainKg || 0);
      const feed = formatNumber(props.result?.production?.feedKg || 0);
      return [
        {
          key: 'stock',
          tone: 'stock',
          title: t('ownerProektAlgo1Title'),
          text: t('ownerProektAlgo1Text'),
        },
        {
          key: 'survive',
          tone: 'survive',
          title: t('ownerProektAlgo2Title'),
          text: t('ownerProektAlgo2Text'),
        },
        {
          key: 'gain',
          tone: 'gain',
          title: t('ownerProektAlgo3Title'),
          text: t('ownerProektAlgo3Text', { gain }),
        },
        {
          key: 'feed',
          tone: 'feed',
          title: t('ownerProektAlgo4Title'),
          text: t('ownerProektAlgo4Text', { fcr, feed, gain }),
        },
        {
          key: 'cost',
          tone: 'cost',
          title: t('ownerProektAlgo5Title'),
          text: t('ownerProektAlgo5Text'),
        },
        {
          key: 'margin',
          tone: 'margin',
          title: t('ownerProektAlgo6Title'),
          text: t('ownerProektAlgo6Text', {
            margin: props.result?.finance?.marginPct ?? 0,
          }),
        },
      ];
    });

    return {
      layout,
      vb,
      berm,
      water,
      zones,
      dimLabel,
      inletPath,
      outletPath,
      algoSteps,
    };
  },
};
</script>
