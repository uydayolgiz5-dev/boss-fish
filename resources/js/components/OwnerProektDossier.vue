<template>
  <div class="op-dossier">
    <header class="panel op-dossier__hero">
      <div
        class="op-dossier__hero-bg"
        style="background-image: url('/images/scenes/scene-ponds-aerial.png')"
        aria-hidden="true"
      />
      <div class="op-dossier__hero-body">
        <div>
          <p class="op-dossier__eyebrow">{{ $t('ownerProektDossierEyebrow') }}</p>
          <h2>{{ inputs.title || $t('ownerProektTitle') }}</h2>
          <p class="op-dossier__meta">
            {{ inputs.clientName || $t('ownerProektNoClient') }}
            · {{ inputs.region || '—' }}
            · {{ formatNumber(result.production.areaHa) }} {{ $t('ownerProektHa') }}
            · {{ modeLabel }}
          </p>
          <p v-if="inputs.pondNote" class="op-dossier__note">{{ inputs.pondNote }}</p>
        </div>
        <div class="op-dossier__actions">
          <button type="button" class="btn btn-primary" @click="$emit('edit')">
            {{ $t('ownerProektEditData') }}
          </button>
          <button type="button" class="btn btn-outline" @click="$emit('back')">
            {{ $t('ownerProektBackList') }}
          </button>
          <button type="button" class="btn btn-outline op-danger" @click="$emit('delete')">
            {{ $t('delete') }}
          </button>
        </div>
      </div>
    </header>

    <div class="op-dossier__kpis">
      <article>
        <span>{{ $t('ownerProektFinalHarvest') }}</span>
        <strong>{{ formatNumber(result.production.harvestKg) }} {{ $t('ownerProektKg') }}</strong>
        <em>{{ $t('ownerProektCapacityAutoShort', { kg: formatNumber(result.production.pondCapacityKg) }) }}</em>
      </article>
      <article>
        <span>{{ $t('ownerProektTotalCost') }}</span>
        <strong>{{ formatMoney(result.costs.total) }}</strong>
      </article>
      <article>
        <span>{{ $t('ownerProektRevenue') }}</span>
        <strong>{{ formatMoney(result.finance.revenue) }}</strong>
      </article>
      <article class="op-dossier__kpi-profit">
        <span>{{ $t('ownerProektProfit') }}</span>
        <strong :class="result.finance.profit >= 0 ? 'is-plus' : 'is-minus'">
          {{ formatMoney(result.finance.profit) }}
        </strong>
        <em :class="result.finance.marginPct >= 0 ? 'is-plus' : 'is-minus'">
          {{ $t('ownerProektMargin') }}: {{ result.finance.marginPct }}%
        </em>
      </article>
    </div>

    <!-- 1. Baliqlar — alohida, rasmlar bilan -->
    <section class="panel op-dossier__block">
      <div class="op-dossier__sec-head">
        <h3>{{ $t('ownerProektDossierFishTitle') }}</h3>
        <p>{{ $t('ownerProektDossierFishLead') }}</p>
      </div>
      <div class="op-dossier__fish">
        <article v-for="s in result.species" :key="s.key" class="op-dossier-fish">
          <img
            class="op-dossier-fish__img"
            :src="fishImg(s.key)"
            :alt="$t(s.labelKey)"
            width="120"
            height="72"
          >
          <div class="op-dossier-fish__body">
            <header>
              <strong>{{ $t(s.labelKey) }}</strong>
              <small v-if="s.usesFeed" class="op-feed-tag">FCR</small>
            </header>
            <dl>
              <div>
                <dt>{{ $t('ownerProektColCount') }}</dt>
                <dd>
                  {{ formatNumber(s.count, { decimals: 0 }) }}
                  → {{ formatNumber(s.harvestCount, { decimals: 0 }) }}
                </dd>
              </div>
              <div>
                <dt>{{ $t('ownerProektColStartG') }} → {{ $t('ownerProektColEndG') }}</dt>
                <dd>{{ formatNumber(s.startG, { decimals: 0 }) }} → {{ formatNumber(s.endG, { decimals: 0 }) }} g</dd>
              </div>
              <div>
                <dt>{{ $t('ownerProektColSurvival') }}</dt>
                <dd>{{ formatNumber(s.survivalPct, { decimals: 0 }) }}%</dd>
              </div>
              <div>
                <dt>{{ $t('ownerProektHarvest') }}</dt>
                <dd>{{ formatNumber(s.harvestKg) }} {{ $t('ownerProektKg') }}</dd>
              </div>
              <div>
                <dt>{{ buyPriceLabel }}</dt>
                <dd>{{ formatMoney(s.buyPriceEach) }}</dd>
              </div>
              <div>
                <dt>{{ $t('ownerProektFieldSellTovar') }}</dt>
                <dd>{{ formatMoney(s.sellPrice) }}</dd>
              </div>
              <div class="is-wide">
                <dt>{{ $t('ownerProektColRevenue') }}</dt>
                <dd>{{ formatMoney(s.revenue) }}</dd>
              </div>
            </dl>
          </div>
        </article>
        <p v-if="!result.species.length" class="muted">{{ $t('ownerProektWarnNoFish') }}</p>
      </div>

      <div class="op-dossier__facts">
        <div>
          <span>{{ $t('ownerProektDensity') }}</span>
          <strong>{{ formatNumber(result.production.densityPerHa, { decimals: 0 }) }} {{ $t('ownerProektPerHa') }}</strong>
        </div>
        <div>
          <span>{{ $t('ownerProektStartBiomass') }}</span>
          <strong>{{ formatNumber(result.production.startKg) }} {{ $t('ownerProektKg') }}</strong>
        </div>
        <div>
          <span>{{ $t('ownerProektCapacityAuto') }}</span>
          <strong>{{ formatNumber(result.production.pondCapacityKg) }} {{ $t('ownerProektKg') }}</strong>
        </div>
        <div>
          <span>{{ $t('ownerProektYieldHa') }}</span>
          <strong>{{ formatNumber(result.production.harvestDensityKgHa) }} {{ $t('ownerProektKg') }}/{{ $t('ownerProektHa') }}</strong>
        </div>
      </div>
    </section>

    <!-- 2. Yem va FCR -->
    <section class="panel op-dossier__block op-dossier__feed">
      <div class="op-dossier__sec-head">
        <h3>{{ $t('ownerProektDossierFeedTitle') }}</h3>
        <p>{{ $t('ownerProektDossierFeedLead', { fcr: result.fcr, mode: modeLabel }) }}</p>
      </div>
      <div class="op-dossier__feed-grid">
        <figure>
          <img src="/images/products/feed/extruded-floating-feed-4mm-35.png" alt="" width="160" height="160">
        </figure>
        <dl class="op-dl">
          <div>
            <dt>{{ $t('ownerProektAlgo3Title') }}</dt>
            <dd>{{ formatNumber(result.production.feedGainKg) }} {{ $t('ownerProektKg') }}</dd>
          </div>
          <div>
            <dt>FCR</dt>
            <dd>{{ result.fcr }}</dd>
          </div>
          <div>
            <dt>{{ $t('ownerProektAutoFeedLabel') }}</dt>
            <dd>{{ formatNumber(result.production.feedKg) }} {{ $t('ownerProektKg') }}</dd>
          </div>
          <div>
            <dt>{{ $t('ownerProektCostFeed') }}</dt>
            <dd>{{ formatMoney(result.costs.feed) }}</dd>
          </div>
        </dl>
        <p class="op-dossier__formula">
          {{ $t('ownerProektDossierFeedFormula', {
            gain: formatNumber(result.production.feedGainKg),
            fcr: result.fcr,
            feed: formatNumber(result.production.feedKg),
          }) }}
        </p>
      </div>
    </section>

    <!-- 3. Moliya — alohida -->
    <section class="panel op-dossier__block">
      <div class="op-dossier__sec-head">
        <h3>{{ $t('ownerProektDossierMoneyTitle') }}</h3>
        <p>{{ $t('ownerProektDossierMoneyLead') }}</p>
      </div>
      <div class="op-dossier__money">
        <ul class="op-costs">
          <li><span>{{ $t('ownerProektCostStock') }}</span><strong>{{ formatMoney(result.costs.stock) }}</strong></li>
          <li>
            <span>{{ $t('ownerProektCostFeed') }} ({{ formatNumber(result.costs.feedKg) }} {{ $t('ownerProektKg') }})</span>
            <strong>{{ formatMoney(result.costs.feed) }}</strong>
          </li>
          <li><span>{{ $t('ownerProektFieldLabor') }}</span><strong>{{ formatMoney(result.costs.labor) }}</strong></li>
          <li><span>{{ $t('ownerProektFieldElectric') }}</span><strong>{{ formatMoney(result.costs.electric) }}</strong></li>
          <li v-for="c in result.costs.custom" :key="c.id">
            <span>{{ c.name || $t('ownerProektCustomCostName') }}</span>
            <strong>{{ formatMoney(c.amount) }}</strong>
          </li>
          <li v-for="ex in result.costs.extras" :key="ex.id">
            <span>{{ extraLabel(ex) }} ({{ ex.pct }}%)</span>
            <strong>{{ formatMoney(ex.amount) }}</strong>
          </li>
          <li class="is-total">
            <span>{{ $t('ownerProektTotalCost') }}</span>
            <strong>{{ formatMoney(result.costs.total) }}</strong>
          </li>
          <li>
            <span>{{ $t('ownerProektRevenue') }}</span>
            <strong>{{ formatMoney(result.finance.revenue) }}</strong>
          </li>
          <li class="is-total">
            <span>{{ $t('ownerProektProfit') }}</span>
            <strong :class="result.finance.profit >= 0 ? 'is-plus' : 'is-minus'">
              {{ formatMoney(result.finance.profit) }}
            </strong>
          </li>
        </ul>
        <aside class="op-dossier__margin-card">
          <span>{{ $t('ownerProektMargin') }}</span>
          <strong :class="result.finance.marginPct >= 0 ? 'is-plus' : 'is-minus'">
            {{ result.finance.marginPct }}%
          </strong>
          <p>{{ $t('ownerProektMarginExplain', {
            profit: formatMoney(result.finance.profit),
            cost: formatMoney(result.costs.total),
          }) }}</p>
          <dl>
            <div>
              <dt>{{ $t('ownerProektCostPerKg') }}</dt>
              <dd>{{ formatMoney(result.costs.costPerKg) }}</dd>
            </div>
            <div v-if="inputs.mode === 'chavoq'">
              <dt>{{ $t('ownerProektCostPerFish') }}</dt>
              <dd>{{ formatMoney(result.costs.costPerFish) }}</dd>
            </div>
          </dl>
        </aside>
      </div>
    </section>

    <!-- 4. Algoritm + havza chizmasi -->
    <OwnerProektVisuals :inputs="inputs" :result="result" />

    <!-- 5. Investor ishonch bloki -->
    <OwnerProektInvestor :inputs="inputs" :result="result" :mode-label="modeLabel" />
  </div>
</template>

<script>
import { computed } from 'vue';
import { t } from '../i18n';
import { formatMoney, formatNumber } from '../utils/number';
import { PROEKT_FISH_IMG } from '../config/ownerProekt';
import OwnerProektInvestor from './OwnerProektInvestor.vue';
import OwnerProektVisuals from './OwnerProektVisuals.vue';

export default {
  name: 'OwnerProektDossier',
  components: { OwnerProektInvestor, OwnerProektVisuals },
  props: {
    inputs: { type: Object, required: true },
    result: { type: Object, required: true },
    modeLabel: { type: String, required: true },
  },
  emits: ['edit', 'back', 'delete'],
  setup(props) {
    const buyPriceLabel = computed(() => (
      props.inputs.mode === 'chavoq' ? t('ownerProektColBuyEach') : t('ownerProektColBuyKg')
    ));

    function fishImg(key) {
      return PROEKT_FISH_IMG[key] || '/images/fish/sazan.png';
    }

    function extraLabel(ex) {
      if (ex.label) return ex.label;
      if (ex.labelKey) return t(ex.labelKey);
      return t('ownerProektExtraName');
    }

    return { buyPriceLabel, fishImg, extraLabel, formatMoney, formatNumber };
  },
};
</script>
