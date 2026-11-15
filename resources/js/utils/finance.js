export const FINANCE_CATEGORIES = ['feed', 'medicine', 'equipment', 'main', 'salary', 'utilities', 'contingent', 'other'];

export const FINANCE_WAREHOUSE_CATEGORIES = ['feed', 'medicine', 'equipment'];

export const FINANCE_ENTRY_CATEGORIES = ['main', 'salary', 'utilities', 'contingent'];

export const FINANCE_CATEGORY_ICONS = {
  feed: '🌾',
  medicine: '💊',
  equipment: '⚙️',
  main: '📦',
  salary: '👷',
  utilities: '💡',
  contingent: '❓',
  other: '📋',
  custom: '📁',
};

export function financeCategoryIcon(category) {
  const apiCategory = toApiCategory(category);
  if (apiCategory.startsWith('custom:')) {
    return FINANCE_CATEGORY_ICONS.custom;
  }
  return FINANCE_CATEGORY_ICONS[apiCategory] || '📋';
}

const FINANCE_CATEGORY_KEYS = {
  feed: 'financeCatFeed',
  medicine: 'financeCatMedicine',
  equipment: 'financeCatEquipment',
  main: 'financeCatMain',
  salary: 'financeCatSalary',
  utilities: 'financeCatUtilities',
  contingent: 'financeCatContingent',
  other: 'financeCatOther',
};

export function isCustomFinanceCategory(category) {
  return typeof category === 'string' && (category.startsWith('custom:') || category.startsWith('custom-'));
}

export function toApiCategory(category) {
  if (typeof category !== 'string') return category;
  if (category.startsWith('custom-')) {
    return `custom:${category.slice(7)}`;
  }
  return category;
}

export function toRouteCategory(category) {
  if (typeof category !== 'string') return category;
  if (category.startsWith('custom:')) {
    return `custom-${category.slice(7)}`;
  }
  return category;
}

export function financeCategoryLabel(category, translate, customSections = {}) {
  const apiCategory = toApiCategory(category);
  if (apiCategory.startsWith('custom:')) {
    const id = apiCategory.slice(7);
    const section = customSections[id] ?? customSections[Number(id)];
    return section?.name || section || apiCategory;
  }
  const key = FINANCE_CATEGORY_KEYS[apiCategory];
  return key && translate ? translate(key) : apiCategory;
}

export function financeCategoryHint(category, translate) {
  const apiCategory = toApiCategory(category);
  if (apiCategory.startsWith('custom:')) {
    return translate ? translate('financeCatCustomHint') : '';
  }
  const key = `${FINANCE_CATEGORY_KEYS[apiCategory]}Hint`;
  return translate && translate(key) !== key ? translate(key) : '';
}

export function financeCategoryPlaceholder(category, translate) {
  const apiCategory = toApiCategory(category);
  if (apiCategory.startsWith('custom:')) {
    return translate ? translate('financeExpenseNamePlaceholder_custom') : '';
  }
  const key = `financeExpenseNamePlaceholder_${apiCategory}`;
  if (translate && translate(key) !== key) {
    return translate(key);
  }
  return translate ? translate('financeExpenseNamePlaceholder') : '';
}

export function isWarehouseFinanceCategory(category) {
  return FINANCE_WAREHOUSE_CATEGORIES.includes(toApiCategory(category));
}

/** Moliyadan qo‘lda kiritiladigan bo‘limlar (ombor chiqarib tashlangan) */
export function isFinanceManualEntryCategory(category, customSectionIds = []) {
  const apiCategory = toApiCategory(category);
  if (isWarehouseFinanceCategory(apiCategory)) {
    return false;
  }
  if (FINANCE_ENTRY_CATEGORIES.includes(apiCategory)) {
    return true;
  }
  if (apiCategory.startsWith('custom:')) {
    const id = Number(apiCategory.slice(7));
    return Number.isFinite(id) && customSectionIds.includes(id);
  }
  return false;
}

export function isValidFinanceCategory(category, customSectionIds = []) {
  const apiCategory = toApiCategory(category);
  if (FINANCE_CATEGORIES.includes(apiCategory)) {
    return true;
  }
  if (apiCategory.startsWith('custom:')) {
    const id = Number(apiCategory.slice(7));
    return Number.isFinite(id) && customSectionIds.includes(id);
  }
  return false;
}
