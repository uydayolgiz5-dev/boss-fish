<template>
  <section class="panel op-invest" aria-labelledby="op-invest-title">
    <header class="op-invest__head">
      <div>
        <p class="op-invest__eyebrow">{{ $t('ownerProektInvestEyebrow') }}</p>
        <h2 id="op-invest-title">{{ $t('ownerProektInvestTitle') }}</h2>
        <p class="op-invest__lead">{{ $t('ownerProektInvestLead') }}</p>
      </div>
      <div class="op-invest__badge" :class="toneClass">
        <span>{{ $t('ownerProektInvestVerdictLabel') }}</span>
        <strong>{{ verdictLabel }}</strong>
      </div>
    </header>

    <p class="op-invest__pitch">
      {{ pitch }}
    </p>

    <div class="op-invest__kpis">
      <article>
        <span>{{ $t('ownerProektInvestCapexLike') }}</span>
        <strong>{{ formatMoney(result.costs.total) }}</strong>
        <em>{{ $t('ownerProektInvestCapexHint') }}</em>
      </article>
      <article>
        <span>{{ $t('ownerProektRevenue') }}</span>
        <strong>{{ formatMoney(result.finance.revenue) }}</strong>
        <em>{{ modeLabel }}</em>
      </article>
      <article>
        <span>{{ $t('ownerProektProfit') }}</span>
        <strong :class="result.finance.profit >= 0 ? 'is-plus' : 'is-minus'">
          {{ formatMoney(result.finance.profit) }}
        </strong>
        <em>{{ $t('ownerProektInvestSeasonHint') }}</em>
      </article>
      <article>
        <span>{{ $t('ownerProektMargin') }}</span>
        <strong :class="roi >= 0 ? 'is-plus' : 'is-minus'">{{ result.finance.marginPct }}%</strong>
        <em>{{ $t('ownerProektMarginExplain', {
          profit: formatMoney(result.finance.profit),
          cost: formatMoney(result.costs.total),
        }) }}</em>
      </article>
    </div>

    <div class="op-invest__grid">
      <div class="op-invest__block">
        <h3>{{ $t('ownerProektInvestOpenTitle') }}</h3>
        <ul>
          <li>{{ $t('ownerProektInvestOpen1', { fcr: result.fcr }) }}</li>
          <li>{{ $t('ownerProektInvestOpen2') }}</li>
          <li>{{ $t('ownerProektInvestOpen3') }}</li>
          <li>{{ $t('ownerProektInvestOpen4') }}</li>
          <li>{{ $t('ownerProektInvestOpen5') }}</li>
        </ul>
      </div>

      <div class="op-invest__block">
        <h3>{{ $t('ownerProektInvestAssumptionsTitle') }}</h3>
        <ul class="op-invest__assumps">
          <li>
            <span>{{ $t('ownerProektFieldArea') }}</span>
            <strong>{{ formatNumber(result.production.areaHa) }} {{ $t('ownerProektHa') }}</strong>
          </li>
          <li>
            <span>{{ $t('ownerProektFieldMode') }}</span>
            <strong>{{ modeLabel }}</strong>
          </li>
          <li>
            <span>FCR</span>
            <strong>{{ result.fcr }} ({{ $t('ownerProektInvestFcrBasis') }})</strong>
          </li>
          <li>
            <span>{{ $t('ownerProektAutoFeedLabel') }}</span>
            <strong>{{ formatNumber(result.production.feedKg) }} {{ $t('ownerProektKg') }}</strong>
          </li>
          <li>
            <span>{{ $t('ownerProektFinalHarvest') }}</span>
            <strong>{{ formatNumber(result.production.harvestKg) }} {{ $t('ownerProektKg') }}</strong>
          </li>
          <li>
            <span>{{ $t('ownerProektInvestKnownShare') }}</span>
            <strong>{{ knownShare }}%</strong>
          </li>
        </ul>
      </div>
    </div>

    <div class="op-invest__block op-invest__risks">
      <h3>{{ $t('ownerProektInvestRiskTitle') }}</h3>
      <p class="op-invest__risk-lead">{{ $t('ownerProektInvestRiskLead') }}</p>
      <div class="op-invest__risk-grid">
        <article v-for="r in risks" :key="r.key">
          <strong>{{ r.title }}</strong>
          <p>{{ r.body }}</p>
          <span>{{ r.mitigation }}</span>
        </article>
      </div>
    </div>

    <div class="op-invest__trust">
      <h3>{{ $t('ownerProektInvestTrustTitle') }}</h3>
      <ol>
        <li>{{ $t('ownerProektInvestTrust1') }}</li>
        <li>{{ $t('ownerProektInvestTrust2') }}</li>
        <li>{{ $t('ownerProektInvestTrust3') }}</li>
        <li>{{ $t('ownerProektInvestTrust4') }}</li>
      </ol>
      <p class="op-invest__disclaimer">{{ $t('ownerProektInvestDisclaimer') }}</p>
    </div>
  </section>
</template>

<script>
import { computed } from 'vue';
import { t } from '../i18n';
import { formatMoney, formatNumber } from '../utils/number';

export default {
  name: 'OwnerProektInvestor',
  props: {
    inputs: { type: Object, required: true },
    result: { type: Object, required: true },
    modeLabel: { type: String, required: true },
  },
  setup(props) {
    /** Marja allaqachon xarajatga nisbatan hisoblangan */
    const roi = computed(() => Number(props.result?.finance?.marginPct || 0));

    const knownShare = computed(() => {
      const total = Number(props.result?.costs?.total || 0);
      const known = Number(props.result?.costs?.knownTotal || 0);
      if (total <= 0) return 0;
      return Math.round((known / total) * 1000) / 10;
    });

    const toneClass = computed(() => {
      if (props.result.warnings?.some((w) => w.tone === 'danger')) return 'is-warn';
      if (roi.value >= 25 && props.result.finance.profit > 0) return 'is-good';
      if (props.result.finance.profit > 0) return 'is-ok';
      return 'is-bad';
    });

    const verdictLabel = computed(() => {
      if (props.result.warnings?.some((w) => w.tone === 'danger')) {
        return t('ownerProektInvestVerdictRisk');
      }
      if (roi.value >= 25 && props.result.finance.profit > 0) {
        return t('ownerProektInvestVerdictStrong');
      }
      if (props.result.finance.profit > 0) {
        return t('ownerProektInvestVerdictOk');
      }
      return t('ownerProektInvestVerdictWeak');
    });

    const pitch = computed(() => {
      const title = (props.inputs.title || '').trim() || t('ownerProektTitle');
      const client = (props.inputs.clientName || '').trim();
      return t('ownerProektInvestPitch', {
        title,
        client: client || t('ownerProektInvestNoClient'),
        area: formatNumber(props.result.production.areaHa),
        mode: props.modeLabel,
        region: props.inputs.region || '—',
        harvest: formatNumber(props.result.production.harvestKg),
        profit: formatMoney(props.result.finance.profit),
        margin: props.result.finance.marginPct,
        roi: props.result.finance.marginPct,
      });
    });

    const risks = computed(() => [
      {
        key: 'bio',
        title: t('ownerProektRiskBioTitle'),
        body: t('ownerProektRiskBioBody'),
        mitigation: t('ownerProektRiskBioMit'),
      },
      {
        key: 'water',
        title: t('ownerProektRiskWaterTitle'),
        body: t('ownerProektRiskWaterBody'),
        mitigation: t('ownerProektRiskWaterMit'),
      },
      {
        key: 'market',
        title: t('ownerProektRiskMarketTitle'),
        body: t('ownerProektRiskMarketBody'),
        mitigation: t('ownerProektRiskMarketMit'),
      },
      {
        key: 'ops',
        title: t('ownerProektRiskOpsTitle'),
        body: t('ownerProektRiskOpsBody'),
        mitigation: t('ownerProektRiskOpsMit'),
      },
    ]);

    return {
      roi,
      knownShare,
      toneClass,
      verdictLabel,
      pitch,
      risks,
      formatMoney,
      formatNumber,
    };
  },
};
</script>
