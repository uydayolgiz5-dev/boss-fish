/**
 * Kundalik → Bugun/Ertaga pastidagi «Qilingan ishlar» jurnaliga yozish.
 */
export async function logOwnerCompletedWork(text, { color = 'mint' } = {}) {
  const line = String(text || '').trim();
  if (!line || !window.axios) return null;

  const { data } = await window.axios.post('/owner/planner/inbox/log-completed', {
    text: line.slice(0, 2000),
    color,
  });
  return data;
}
