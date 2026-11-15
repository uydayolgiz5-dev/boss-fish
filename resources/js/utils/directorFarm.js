import { computed, ref } from 'vue';

const STORAGE_KEY = 'director_farm_id';
const farmIdRef = ref(typeof localStorage !== 'undefined' ? localStorage.getItem(STORAGE_KEY) : null);

export function getDirectorFarmId() {
  return farmIdRef.value;
}

export function setDirectorFarmId(id) {
  const next = id == null || id === '' || id === 'null' ? null : String(id);
  try {
    if (next) localStorage.setItem(STORAGE_KEY, next);
    else localStorage.removeItem(STORAGE_KEY);
  } catch (e) {
    /* ignore */
  }
  farmIdRef.value = next;
  return next;
}

export function useDirectorFarmId() {
  return computed(() => farmIdRef.value);
}

/** Hodim/direktor sahifalari uchun farm id ni kafolatlaydi (stale localStorage ni ham tozalaydi). */
export async function ensureDirectorFarmId() {
  const current = getDirectorFarmId();
  try {
    const { data } = await window.axios.get('/director/farms');
    const list = Array.isArray(data) ? data : [];
    if (!list.length) {
      return setDirectorFarmId(null);
    }
    const ids = new Set(list.map((f) => String(f.id)));
    if (current && ids.has(String(current))) {
      return String(current);
    }
    return setDirectorFarmId(String(list[0].id));
  } catch (e) {
    return current || null;
  }
}

export function rememberFarmFromRoute(to) {
  if (to?.params?.farmId) {
    setDirectorFarmId(to.params.farmId);
  }
}
