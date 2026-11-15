import { computed, ref } from 'vue';

export const ownerPendingRequestCount = ref(0);

export const ownerPendingRequestBadge = computed(() => {
  const n = Number(ownerPendingRequestCount.value || 0);
  if (n <= 0) return '';
  return n > 9 ? '9+' : String(n);
});

let timer = null;
let inflight = false;
let onVisible = null;

export function setOwnerPendingRequestCount(n) {
  ownerPendingRequestCount.value = Math.max(0, Number(n) || 0);
}

export async function refreshOwnerRequestInbox() {
  if (inflight || typeof window === 'undefined' || !window.axios) return;
  inflight = true;
  try {
    const { data } = await window.axios.get('/owner/requests-summary');
    setOwnerPendingRequestCount(data?.pending_count);
  } catch (e) {
    /* owner emas yoki tarmoq */
  } finally {
    inflight = false;
  }
}

export function startOwnerRequestInbox() {
  if (typeof window === 'undefined') return;
  refreshOwnerRequestInbox();
  stopOwnerRequestInbox();
  timer = window.setInterval(refreshOwnerRequestInbox, 20000);
  onVisible = () => {
    if (document.visibilityState === 'visible') refreshOwnerRequestInbox();
  };
  document.addEventListener('visibilitychange', onVisible);
}

export function stopOwnerRequestInbox() {
  if (timer) {
    clearInterval(timer);
    timer = null;
  }
  if (onVisible && typeof document !== 'undefined') {
    document.removeEventListener('visibilitychange', onVisible);
    onVisible = null;
  }
}
