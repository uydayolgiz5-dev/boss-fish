/** Bosh sahifa tariflari — kompaniya obunasi bilan bir xil */
export const PRICING_PLANS = [
  {
    id: 'cabinet',
    nameKey: 'homePlanCabinetName',
    summaryKey: 'homePlanCabinetSummary',
    monthPrice: 135000,
    yearPrice: 1299000,
    customPrice: false,
  },
  {
    id: 'control',
    nameKey: 'homePlanControlName',
    summaryKey: 'homePlanControlSummary',
    monthPrice: 450000,
    yearPrice: 4320000,
    customPrice: false,
  },
  {
    id: 'individual',
    nameKey: 'homePlanIndividualName',
    summaryKey: 'homePlanIndividualSummary',
    monthPrice: null,
    yearPrice: null,
    customPrice: true,
  },
];

export const PERIOD_DAYS = { month: 30, year: 365 };
export const EXPIRING_SOON_DAYS = 10;

export function formatPlanPrice(plan, period, locale = 'uz') {
  if (!plan || plan.customPrice) return null;
  const amount = period === 'month' ? plan.monthPrice : plan.yearPrice;
  if (amount == null) return null;
  return new Intl.NumberFormat(locale === 'ru' ? 'ru-RU' : 'uz-UZ').format(amount);
}

/** @param {string} period @param {string|Date|null} fromIso — mavjud muddat (kelajak) bo‘lsa shundan qo‘shiladi */
export function previewEndDate(period, fromIso = null) {
  const days = PERIOD_DAYS[period] || 365;
  const now = new Date();
  let base = now;
  if (fromIso) {
    const from = new Date(fromIso);
    if (!Number.isNaN(from.getTime()) && from.getTime() > now.getTime()) {
      base = from;
    }
  }
  const d = new Date(base.getTime());
  d.setDate(d.getDate() + days);
  return d.toISOString().slice(0, 10);
}

export function periodDays(period) {
  return PERIOD_DAYS[period] || 365;
}
