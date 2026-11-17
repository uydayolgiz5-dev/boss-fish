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

function applyKeyboardState() {
  if (typeof document === 'undefined') return;
  const active = document.activeElement;
  const fieldOpen = isKeyboardField(active);
  const cover = keyboardCoverPx();
  const open = fieldOpen || cover > 80;
  const chatOpen = open && isChatComposerField(active);

  // Forma: faqat haqiqiy cover — katta fallback inset sakratadi
  let inset = 0;
  if (chatOpen) {
    inset = cover > 40 ? cover : Math.round(Math.min(Math.max((window.innerHeight || 0) * 0.38, 220), 380));
  } else if (open && cover > 40) {
    inset = cover;
  }

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
  if (isChatComposerField(el)) return;

  const vv = window.visualViewport;
  const rect = el.getBoundingClientRect();
  const top = vv ? vv.offsetTop + 12 : 12;
  const bottom = vv ? vv.offsetTop + vv.height - 24 : window.innerHeight - 24;
  if (rect.top >= top && rect.bottom <= bottom) return;

  // center/smooth — iOS da tepaga-pastga sakraydi
  el.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'auto' });
}

export function installMobileKeyboard() {
  if (typeof window === 'undefined') return () => {};

  let blurTimer = null;
  let scrollTimer = null;
  let syncTimer = null;

  const sync = () => {
    if (syncTimer) clearTimeout(syncTimer);
    syncTimer = window.setTimeout(() => {
      applyKeyboardState();
    }, 50);
  };

  const onFocusIn = (event) => {
    if (!isKeyboardField(event.target)) return;
    if (blurTimer) {
      clearTimeout(blurTimer);
      blurTimer = null;
    }
    applyKeyboardState();
    if (scrollTimer) clearTimeout(scrollTimer);
    // Bir marta, klaviatura ochilgach — qayta-qayta scroll qilmaymiz
    scrollTimer = window.setTimeout(scrollFocusedField, 320);
  };

  const onFocusOut = () => {
    blurTimer = window.setTimeout(() => {
      applyKeyboardState();
    }, 160);
  };

  window.addEventListener('focusin', onFocusIn, true);
  window.addEventListener('focusout', onFocusOut, true);
  window.addEventListener('resize', sync);
  window.addEventListener('orientationchange', sync);

  const vv = window.visualViewport;
  if (vv) {
    // scroll — iOS viewport surganda inset o‘zgarib sakraydi; faqat resize
    vv.addEventListener('resize', sync);
  }

  applyKeyboardState();

  return () => {
    window.removeEventListener('focusin', onFocusIn, true);
    window.removeEventListener('focusout', onFocusOut, true);
    window.removeEventListener('resize', sync);
    window.removeEventListener('orientationchange', sync);
    if (vv) {
      vv.removeEventListener('resize', sync);
    }
    if (blurTimer) clearTimeout(blurTimer);
    if (scrollTimer) clearTimeout(scrollTimer);
    if (syncTimer) clearTimeout(syncTimer);
    document.body.classList.remove('is-keyboard-open');
    document.body.classList.remove('is-chat-keyboard');
    document.documentElement.style.removeProperty('--keyboard-inset');
    document.documentElement.style.removeProperty('--vv-height');
    document.documentElement.style.removeProperty('--vv-offset-top');
  };
}
