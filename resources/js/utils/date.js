import { i18nState, localeTag } from '../i18n';

const UZ_MONTHS = [
  'yanvar',
  'fevral',
  'mart',
  'aprel',
  'may',
  'iyun',
  'iyul',
  'avgust',
  'sentabr',
  'oktabr',
  'noyabr',
  'dekabr',
];

function pad2(n) {
  return String(n).padStart(2, '0');
}

export function toDate(value) {
  if (!value) return null;
  if (value instanceof Date) {
    return Number.isNaN(value.getTime()) ? null : value;
  }
  const raw = String(value).trim();
  // Faqat sana (YYYY-MM-DD) — lokal yarim tun; to‘liq ISO (T / soat) — asl vaqt saqlansin
  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
    const [y, m, d] = raw.split('-').map(Number);
    return new Date(y, m - 1, d);
  }
  if (/^\d{2}\.\d{2}\.\d{4}$/.test(raw)) {
    const [d, m, y] = raw.split('.').map(Number);
    return new Date(y, m - 1, d);
  }
  const date = new Date(raw);
  return Number.isNaN(date.getTime()) ? null : date;
}

/** API / type=date uchun: YYYY-MM-DD */
export function toIsoDate(value = new Date()) {
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value)) {
    return value.slice(0, 10);
  }
  const date = value instanceof Date ? value : toDate(value);
  if (!date) {
    const now = new Date();
    return `${now.getFullYear()}-${pad2(now.getMonth() + 1)}-${pad2(now.getDate())}`;
  }
  return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
}

/** Ko‘rinish: 24.09.2026 — butun sayt bo‘ylab bitta stil */
export function formatDisplayDate(value) {
  const date = toDate(value);
  if (!date) return '';
  return `${pad2(date.getDate())}.${pad2(date.getMonth() + 1)}.${date.getFullYear()}`;
}

/** Ko‘rinish: 24.09.2026 14:30 — ISO da soat bo‘lsa, o‘sha soat (brauzer lokalida) */
export function formatDisplayDateTime(value) {
  const raw = typeof value === 'string' ? value.trim() : '';
  // Faqat sana — soatsiz
  if (raw && (/^\d{4}-\d{2}-\d{2}$/.test(raw) || /^\d{2}\.\d{2}\.\d{4}$/.test(raw))) {
    return formatDisplayDate(raw);
  }
  // Asia/Tashkent (+05:00) ISO: brauzer TZ ga bog‘lanmasdan, yozilgan soatni ko‘rsatamiz
  const tashkent = raw.match(
    /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::\d{2})?(?:\.\d+)?([+-]\d{2}:?\d{2}|Z)?$/i
  );
  if (tashkent) {
    const [, y, mo, d, hh, mi, tz] = tashkent;
    const offset = (tz || '').toUpperCase();
    if (!offset || offset === 'Z' || offset === '+05:00' || offset === '+0500') {
      return `${d}.${mo}.${y} ${hh}:${mi}`;
    }
  }
  const date = toDate(value);
  if (!date) return '';
  return `${formatDisplayDate(date)} ${pad2(date.getHours())}:${pad2(date.getMinutes())}`;
}

/** @deprecated — formatDisplayDate bilan bir xil (24.09.2026) */
export function formatUzLongDate(value) {
  return formatDisplayDate(value);
}

/** Masalan: 24.09 */
export function formatUzDayMonth(value) {
  const date = toDate(value);
  if (!date) return '';
  return `${pad2(date.getDate())}.${pad2(date.getMonth() + 1)}`;
}

/** Planner va boshqa joylar uchun alias */
export function formatPlannerDate(iso) {
  return formatDisplayDate(iso);
}

/** Eski kodlar uchun */
export function formatDotDate(value) {
  return formatDisplayDate(value);
}

export function formatLocaleLongDate(value) {
  const date = toDate(value);
  if (!date) return '';
  const loc = i18nState.locale;
  if (loc === 'uz') {
    return `${date.getDate()} ${UZ_MONTHS[date.getMonth()]} ${date.getFullYear()}`;
  }
  return date.toLocaleDateString(localeTag(loc), {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}
