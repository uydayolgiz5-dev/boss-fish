/**
 * BOSS FISH — Proekt kalkulyatori.
 * Har baliq: o‘z narxi + o‘z tiriklik %.
 * Yem: sazan + amur o‘sishiga FCR (tovar 1.8 / chavoq 1.5).
 */

import { regionNames } from '../data/uzbekistanDistricts';

export const PROEKT_REGIONS = regionNames();

export const FISH_KEYS = ['sazan', 'amur', 'tolstolob'];

/** Yem hisobiga kiradigan turlar (planktonxo‘r tolstolob — emas) */
export const FEED_FISH_KEYS = ['sazan', 'amur'];

export const FCR_BY_MODE = {
  tovar: 1.8,
  chavoq: 1.5,
};

export const FISH_META = {
  sazan: { labelKey: 'ownerProektFishSazan', usesFeed: true },
  amur: { labelKey: 'ownerProektFishAmur', usesFeed: true },
  tolstolob: { labelKey: 'ownerProektFishTolstolob', usesFeed: false },
};

export const DEFAULT_EXTRA_PCTS = [
  { id: 'medicine', labelKey: 'ownerProektCostMedicine', pct: 5 },
  { id: 'other', labelKey: 'ownerProektCostOtherPct', pct: 3 },
];

export function emptySpeciesRow() {
  return {
    count: 0,
    startG: 0,
    endG: 0,
    survivalPct: 100,
    /** Tovar: so‘m/kg (boshlang‘ich massa); chavoq: so‘m/dona */
    buyPriceEach: 0,
    /** Sotish: tovar ham, chavoq ham so‘m/kg */
    sellPrice: 0,
  };
}

export function emptyProektInputs() {
  return {
    title: '',
    clientName: '',
    region: PROEKT_REGIONS.includes('Toshkent viloyati')
      ? 'Toshkent viloyati'
      : PROEKT_REGIONS[0] || '',
    areaHa: 5,
    pondCapacityKg: 0,
    pondNote: '',
    mode: 'tovar',
    fish: {
      sazan: emptySpeciesRow(),
      amur: emptySpeciesRow(),
      tolstolob: emptySpeciesRow(),
    },
    feedPricePerKg: 0,
    laborCost: 0,
    electricCost: 0,
    layout: {
      aspect: 2.5,
      showInlet: true,
      showOutlet: true,
    },
    /** Nom bilan qo‘shiladigan aniq xarajatlar (uskuna, transport…) */
    customCosts: [],
    extras: DEFAULT_EXTRA_PCTS.map((x) => ({ ...x })),
  };
}

export function emptyCustomCost() {
  return { id: `cost_${Date.now()}`, name: '', amount: 0 };
}

function num(v) {
  const n = Number(v);
  return Number.isFinite(n) ? n : 0;
}

function roundMoney(n) {
  return Math.round(num(n));
}

function round2(n) {
  return Math.round(num(n) * 100) / 100;
}

export function fcrForMode(mode) {
  return mode === 'chavoq' ? FCR_BY_MODE.chavoq : FCR_BY_MODE.tovar;
}

export function normalizeInputs(raw = {}) {
  const base = emptyProektInputs();
  const mode = raw.mode === 'chavoq' ? 'chavoq' : 'tovar';

  // Eski saqlangan loyihalar (umumiy narx/tiriklik) → turlarga tarqatish
  const legacySurvival = raw.survivalPct != null ? num(raw.survivalPct) : null;
  const legacyBuy = raw.buyPriceEach != null ? num(raw.buyPriceEach) : null;
  const legacySell = raw.sellPrice != null ? num(raw.sellPrice) : null;

  const fish = {};
  for (const key of FISH_KEYS) {
    const row = raw.fish?.[key] || {};
    const blank = emptySpeciesRow();
    fish[key] = {
      count: Math.max(0, Math.round(num(row.count))),
      startG: Math.max(0, num(row.startG)),
      endG: Math.max(0, num(row.endG)),
      survivalPct: Math.min(
        100,
        Math.max(0, num(row.survivalPct ?? legacySurvival ?? blank.survivalPct)),
      ),
      buyPriceEach: Math.max(0, num(row.buyPriceEach ?? legacyBuy ?? 0)),
      sellPrice: Math.max(0, num(row.sellPrice ?? legacySell ?? 0)),
    };
  }

  let extras = Array.isArray(raw.extras) ? raw.extras : base.extras;
  extras = extras
    .map((e, i) => ({
      id: e.id || `extra_${i}`,
      labelKey: e.labelKey || null,
      label: e.label || '',
      pct: Math.max(0, num(e.pct)),
    }))
    .filter((e) => e.pct > 0 || e.label || e.labelKey);

  if (!extras.length) {
    extras = DEFAULT_EXTRA_PCTS.map((x) => ({ ...x }));
  }

  const customCosts = (Array.isArray(raw.customCosts) ? raw.customCosts : [])
    .map((c, i) => ({
      id: c.id || `cost_${i}`,
      name: String(c.name || '').trim(),
      amount: Math.max(0, num(c.amount)),
    }))
    .filter((c) => c.name || c.amount > 0);

  const layoutRaw = raw.layout && typeof raw.layout === 'object' ? raw.layout : {};
  const layout = {
    aspect: Math.max(1.1, num(layoutRaw.aspect ?? base.layout.aspect) || 2.5),
    showInlet: layoutRaw.showInlet !== false,
    showOutlet: layoutRaw.showOutlet !== false,
  };

  return {
    ...base,
    ...raw,
    mode,
    fish,
    extras,
    customCosts,
    layout,
    areaHa: Math.max(0, num(raw.areaHa ?? base.areaHa)),
    pondCapacityKg: Math.max(0, num(raw.pondCapacityKg)),
    feedPricePerKg: Math.max(0, num(raw.feedPricePerKg)),
    laborCost: Math.max(0, num(raw.laborCost)),
    electricCost: Math.max(0, num(raw.electricCost)),
  };
}

export function runProekt(rawInputs) {
  const inputs = normalizeInputs(rawInputs);
  const area = inputs.areaHa || 0;
  const fcr = fcrForMode(inputs.mode);

  const species = FISH_KEYS.map((key) => {
    const row = inputs.fish[key];
    const count = row.count;
    const survival = row.survivalPct / 100;
    const harvestCount = Math.round(count * survival);
    const startKg = (count * row.startG) / 1000;
    const harvestKg = (harvestCount * row.endG) / 1000;
    const gainKg = Math.max(0, (harvestCount * (row.endG - row.startG)) / 1000);
    // Tovar: chavoq xarid kg bo‘yicha (boshlang‘ich massa × narx); chavoq rejimi: dona
    const stockCost = inputs.mode === 'tovar'
      ? roundMoney(startKg * row.buyPriceEach)
      : roundMoney(count * row.buyPriceEach);
    // Sotish: tovar va chavoq — so‘m/kg
    const revenue = roundMoney(harvestKg * row.sellPrice);
    return {
      key,
      labelKey: FISH_META[key].labelKey,
      usesFeed: !!FISH_META[key].usesFeed,
      count,
      harvestCount,
      startG: row.startG,
      endG: row.endG,
      survivalPct: row.survivalPct,
      buyPriceEach: row.buyPriceEach,
      sellPrice: row.sellPrice,
      startKg: round2(startKg),
      harvestKg: round2(harvestKg),
      gainKg: round2(gainKg),
      stockCost,
      revenue,
      densityPerHa: area > 0 ? Math.round(count / area) : 0,
    };
  }).filter((s) => s.count > 0 || s.startG > 0 || s.endG > 0);

  const totalCount = species.reduce((s, x) => s + x.count, 0);
  const harvestCount = species.reduce((s, x) => s + x.harvestCount, 0);
  const startKg = round2(species.reduce((s, x) => s + x.startKg, 0));
  const harvestKg = round2(species.reduce((s, x) => s + x.harvestKg, 0));
  const densityPerHa = area > 0 ? Math.round(totalCount / area) : 0;
  const startDensityKgHa = area > 0 ? round2(startKg / area) : 0;
  const harvestDensityKgHa = area > 0 ? round2(harvestKg / area) : 0;

  const feedGainKg = round2(
    species
      .filter((s) => FEED_FISH_KEYS.includes(s.key))
      .reduce((sum, s) => sum + s.gainKg, 0),
  );
  const feedKg = round2(feedGainKg * fcr);

  const stockCost = roundMoney(species.reduce((s, x) => s + x.stockCost, 0));
  const feedCost = roundMoney(feedKg * inputs.feedPricePerKg);
  const laborCost = roundMoney(inputs.laborCost);
  const electricCost = roundMoney(inputs.electricCost);
  const customLines = (inputs.customCosts || []).map((c) => ({
    id: c.id,
    name: c.name || '',
    amount: roundMoney(c.amount),
  }));
  const customTotal = customLines.reduce((s, c) => s + c.amount, 0);
  const knownTotal = stockCost + feedCost + laborCost + electricCost + customTotal;

  const pctSum = inputs.extras.reduce((s, e) => s + num(e.pct), 0);
  const pctClamped = Math.min(pctSum, 95);
  const denom = 1 - pctClamped / 100;
  const totalCost = denom > 0 ? roundMoney(knownTotal / denom) : knownTotal;

  const extraLines = inputs.extras.map((e) => {
    const amount = roundMoney(totalCost * (num(e.pct) / 100));
    return {
      id: e.id,
      labelKey: e.labelKey || null,
      label: e.label || '',
      pct: num(e.pct),
      amount,
    };
  });
  const extrasTotal = extraLines.reduce((s, e) => s + e.amount, 0);

  const revenue = roundMoney(species.reduce((s, x) => s + x.revenue, 0));
  const profit = revenue - totalCost;
  /** Foyda marjasi — xarajatga nisbatan % (foyda ÷ jami xarajat) */
  const marginPct = totalCost > 0 ? Math.round((profit / totalCost) * 1000) / 10 : 0;
  const revenueMarginPct = revenue > 0 ? Math.round((profit / revenue) * 1000) / 10 : 0;
  const costPerKg = harvestKg > 0 ? Math.round(totalCost / harvestKg) : 0;
  const costPerFish = harvestCount > 0 ? Math.round(totalCost / harvestCount) : 0;

  /** Havza sig‘imi = yakuniy son × yakuniy vazn (kg) — qo‘lda so‘ralmaydi */
  const pondCapacityKg = harvestKg;

  const warnings = [];
  if (pctSum > 95) {
    warnings.push({ tone: 'warn', key: 'ownerProektWarnPctHigh' });
  }
  if (totalCount <= 0) {
    warnings.push({ tone: 'warn', key: 'ownerProektWarnNoFish' });
  }
  if (area <= 0) {
    warnings.push({ tone: 'warn', key: 'ownerProektWarnNoArea' });
  }

  return {
    generatedAt: new Date().toISOString(),
    mode: inputs.mode,
    fcr,
    species,
    production: {
      areaHa: area,
      totalCount,
      harvestCount,
      startKg,
      harvestKg,
      feedGainKg,
      feedKg,
      fcr,
      densityPerHa,
      startDensityKgHa,
      harvestDensityKgHa,
      pondCapacityKg,
      capacityOk: true,
    },
    costs: {
      stock: stockCost,
      feed: feedCost,
      feedKg,
      feedGainKg,
      fcr,
      labor: laborCost,
      electric: electricCost,
      custom: customLines,
      customTotal,
      knownTotal,
      extras: extraLines,
      extrasTotal,
      pctSum: pctClamped,
      total: totalCost,
      costPerKg,
      costPerFish,
    },
    finance: {
      revenue,
      profit,
      marginPct,
      revenueMarginPct,
      sellUnit: 'kg',
      buyUnit: inputs.mode === 'tovar' ? 'kg' : 'each',
    },
    warnings,
    summary: {
      areaHa: area,
      region: inputs.region,
      mode: inputs.mode,
      harvestKg,
      profit,
      marginPct,
      site_score: null,
    },
  };
}

export const PROEKT_FISH_IMG = {
  sazan: '/images/fish/sazan.png',
  amur: '/images/fish/oq_amur.png',
  tolstolob: '/images/fish/tolstolob.png',
};
