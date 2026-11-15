/** Escapes HTML, then turns [pond]/[warn]/[danger]/[ok]/[em] markers into colored spans. */
export function formatAlertRichText(raw) {
  if (raw == null || raw === '') return '';

  const esc = (value) => String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

  const source = String(raw);
  const re = /\[(pond|warn|danger|ok|em)\]([\s\S]*?)\[\/\1\]/g;
  let out = '';
  let last = 0;
  let match;

  while ((match = re.exec(source)) !== null) {
    out += esc(source.slice(last, match.index));
    out += `<span class="crm-alert-hl crm-alert-hl--${match[1]}">${esc(match[2])}</span>`;
    last = match.index + match[0].length;
  }

  out += esc(source.slice(last));
  return out;
}
