import { reactive } from 'vue';

const STORAGE_KEY = 'boss_fish_theme';
const THEMES = ['light', 'dark'];

export const themeState = reactive({
  /** @type {'light'|'dark'} */
  theme: 'light',
});

/** @returns {'light'|'dark'} */
export function getStoredTheme() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (THEMES.includes(raw)) return raw;
  } catch (e) { /* ignore */ }
  return 'light';
}

/** @param {'light'|'dark'} theme */
export function applyTheme(theme) {
  const next = THEMES.includes(theme) ? theme : 'light';
  themeState.theme = next;
  if (typeof document !== 'undefined') {
    document.documentElement.setAttribute('data-theme', next);
    document.documentElement.style.colorScheme = next;
    if (document.body) {
      document.body.setAttribute('data-theme', next);
    }
  }
  try {
    localStorage.setItem(STORAGE_KEY, next);
  } catch (e) { /* ignore */ }
  return next;
}

export function initTheme() {
  return applyTheme(getStoredTheme());
}

export function setTheme(theme) {
  return applyTheme(theme);
}

export function isDarkTheme() {
  return themeState.theme === 'dark';
}
