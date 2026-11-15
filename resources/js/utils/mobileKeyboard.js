const SKIP_INPUT_TYPES = new Set([
  'button',
  'submit',
  'reset',
  'checkbox',
  'radio',
  'file',
  'hidden',
  'range',
  'color',
  'image',
]);

function isKeyboardField(el) {
  if (!el || el.nodeType !== 1) return false;
  if (el.isContentEditable || el.closest?.('[contenteditable="true"]')) return true;
  const tag = el.tagName;
  if (tag === 'TEXTAREA' || tag === 'SELECT') return true;
  if (tag !== 'INPUT') return false;
  return !SKIP_INPUT_TYPES.has(String(el.type || 'text').toLowerCase());
}

function isChatComposerField(el) {
  return !!(el && el.closest?.('[data-chat-lock], .boss-chat'));
}

function keyboardCoverPx() {
  const vv = window.visualViewport;
  if (!vv) return 0;
  return Math.max(0, Math.round(window.innerHeight - vv.height - vv.offsetTop));
}

function fallbackInset() {
  const h = window.visualViewport?.height || window.innerHeight || 0;
  return Math.round(Math.min(Math.max(h * 0.38, 220), 380));
}

function applyKeyboardState() {
  if (typeof document === 'undefined') return;
  const fieldOpen = isKeyboardField(document.activeElement);
  const cover = keyboardCoverPx();
  const open = fieldOpen || cover > 40;
  let inset = cover;
  if (fieldOpen && inset < 120) {
    inset = Math.max(inset, fallbackInset());
  }
  if (!open) {
    inset = 0;
  }

  const chatOpen = open && isChatComposerField(document.activeElement);
  document.body.classList.toggle('is-keyboard-open', open);
  document.body.classList.toggle('is-chat-keyboard', chatOpen);
  document.documentElement.style.setProperty('--keyboard-inset', `${inset}px`);
  const vv = window.visualViewport;
  if (vv) {
    document.documentElement.style.setProperty('--vv-height', `${Math.round(vv.height)}px`);
    document.documentElement.style.setProperty('--vv-offset-top', `${Math.round(vv.offsetTop)}px`);
  }
}

function scrollFocusedField() {
  const el = document.activeElement;
  if (!isKeyboardField(el) || typeof el.scrollIntoView !== 'function') return;
  // Instagram uslubi: chat composer sahifani siljitmasin — faqat thread scroll qiladi
  if (isChatComposerField(el)) return;

  const vv = window.visualViewport;
  const rect = el.getBoundingClientRect();
  const top = vv ? vv.offsetTop + 16 : 16;
  const bottom = vv ? vv.offsetTop + vv.height - 28 : window.innerHeight - 28;
  if (rect.top >= top && rect.bottom <= bottom) return;

  el.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'smooth' });
}

export function installMobileKeyboard() {
  if (typeof window === 'undefined') return () => {};

  let blurTimer = null;
  let scrollTimer = null;

  const sync = () => {
    applyKeyboardState();
    if (scrollTimer) clearTimeout(scrollTimer);
    scrollTimer = window.setTimeout(scrollFocusedField, 60);
  };

  const onFocusIn = (event) => {
    if (!isKeyboardField(event.target)) return;
    if (blurTimer) {
      clearTimeout(blurTimer);
      blurTimer = null;
    }
    applyKeyboardState();
    if (scrollTimer) clearTimeout(scrollTimer);
    scrollTimer = window.setTimeout(scrollFocusedField, 280);
    window.setTimeout(scrollFocusedField, 520);
  };

  const onFocusOut = () => {
    blurTimer = window.setTimeout(() => {
      applyKeyboardState();
    }, 140);
  };

  window.addEventListener('focusin', onFocusIn, true);
  window.addEventListener('focusout', onFocusOut, true);
  window.addEventListener('resize', sync);
  window.addEventListener('orientationchange', sync);

  const vv = window.visualViewport;
  if (vv) {
    vv.addEventListener('resize', sync);
    vv.addEventListener('scroll', sync);
  }

  applyKeyboardState();

  return () => {
    window.removeEventListener('focusin', onFocusIn, true);
    window.removeEventListener('focusout', onFocusOut, true);
    window.removeEventListener('resize', sync);
    window.removeEventListener('orientationchange', sync);
    if (vv) {
      vv.removeEventListener('resize', sync);
      vv.removeEventListener('scroll', sync);
    }
    if (blurTimer) clearTimeout(blurTimer);
    if (scrollTimer) clearTimeout(scrollTimer);
    document.body.classList.remove('is-keyboard-open');
    document.body.classList.remove('is-chat-keyboard');
    document.documentElement.style.removeProperty('--keyboard-inset');
    document.documentElement.style.removeProperty('--vv-height');
    document.documentElement.style.removeProperty('--vv-offset-top');
  };
}
