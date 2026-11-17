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

  // Forma: inset faqat o‘zgaruvchi (scroll-margin) — kartalarga padding YO‘Q
  let inset = 0;
  if (chatOpen) {
    inset = cover > 40
      ? cover
      : Math.round(Math.min(Math.max((window.innerHeight || 0) * 0.38, 220), 380));
  } else if (open && cover > 40) {
    inset = cover;
  } else if (open) {
    // DevTools/klaviatura o‘lchami aniq bo‘lmasa — pastki nav yashirinadi
    inset = 0;
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

/** Fokuslangan maydonni klaviatura ustida ko‘rinadigan qilib suradi (kartani cho‘zmaydi). */
function revealFocusedField() {
  const el = document.activeElement;
  if (!isKeyboardField(el) || isChatComposerField(el)) return;

  const vv = window.visualViewport;
  const rect = el.getBoundingClientRect();
  const headerPad = 72;
  const bottomPad = 28;
  const viewTop = vv ? vv.offsetTop + headerPad : headerPad;
  const viewBottom = vv ? vv.offsetTop + vv.height - bottomPad : window.innerHeight - bottomPad;

  if (rect.top >= viewTop && rect.bottom <= viewBottom) return;

  // Maydonni visual viewport o‘rtasiga yaqinlashtiramiz
  const targetY = viewTop + (viewBottom - viewTop) * 0.35;
  const delta = rect.top - targetY;
  if (Math.abs(delta) < 8) return;

  window.scrollBy({ top: delta, left: 0, behavior: 'auto' });
}

export function installMobileKeyboard() {
  if (typeof window === 'undefined') return () => {};

  let blurTimer = null;
  let syncTimer = null;
  let revealTimer = null;

  const sync = () => {
    if (syncTimer) clearTimeout(syncTimer);
    syncTimer = window.setTimeout(() => {
      applyKeyboardState();
      if (isKeyboardField(document.activeElement) && !isChatComposerField(document.activeElement)) {
        revealFocusedField();
      }
    }, 40);
  };

  const onFocusIn = (event) => {
    if (!isKeyboardField(event.target)) return;
    if (blurTimer) {
      clearTimeout(blurTimer);
      blurTimer = null;
    }
    applyKeyboardState();
    if (revealTimer) clearTimeout(revealTimer);
    // Klaviatura ochilgach bir-ikki marta — cho‘zilmasdan yuqoriga
    revealTimer = window.setTimeout(revealFocusedField, 80);
    window.setTimeout(revealFocusedField, 280);
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
    if (syncTimer) clearTimeout(syncTimer);
    if (revealTimer) clearTimeout(revealTimer);
    document.body.classList.remove('is-keyboard-open');
    document.body.classList.remove('is-chat-keyboard');
    document.documentElement.style.removeProperty('--keyboard-inset');
    document.documentElement.style.removeProperty('--vv-height');
    document.documentElement.style.removeProperty('--vv-offset-top');
  };
}
