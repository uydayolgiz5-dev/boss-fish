import { getTarifContract } from '../config/tarifContract';

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;');
}

export function downloadTarifContract(locale) {
  const c = getTarifContract(locale);
  const rows = c.s4Rows.map((row) => (
    `<tr>${row.map((cell) => `<td>${escapeHtml(cell)}</td>`).join('')}</tr>`
  )).join('');
  const exec = c.s2Exec.map((item) => `<li>${escapeHtml(item)}</li>`).join('');
  const client = c.s2Client.map((item) => `<li>${escapeHtml(item)}</li>`).join('');
  const unused = c.s3Items.map((item) => `<li>${escapeHtml(item)}</li>`).join('');
  const rules = c.s5Items.map((item) => `<li>${escapeHtml(item)}</li>`).join('');

  const html = `<!DOCTYPE html>
<html lang="${locale === 'ru' ? 'ru' : 'uz'}">
<head>
<meta charset="utf-8">
<title>${escapeHtml(c.title)} — BOSS FISH</title>
<style>
  body { margin: 0; background: #f3f4f1; color: #122018; font: 16px/1.55 Georgia, "Times New Roman", serif; }
  .sheet { max-width: 760px; margin: 24px auto; padding: 40px 44px 48px; background: #fffef8; border: 1px solid #e4e0d4; }
  .kicker { letter-spacing: .16em; text-transform: uppercase; font: 700 11px/1.2 system-ui, sans-serif; color: #1f7a3d; }
  h1 { margin: .35rem 0 .2rem; font: 800 28px/1.2 system-ui, sans-serif; }
  .sub { margin: 0 0 1.4rem; color: #5b6b62; }
  h2 { margin: 1.5rem 0 .55rem; font: 750 17px/1.3 system-ui, sans-serif; color: #0b1f16; }
  p { margin: .4rem 0; }
  ul { margin: .4rem 0 .7rem; padding-left: 1.15rem; }
  table { width: 100%; border-collapse: collapse; margin: .7rem 0; font-family: system-ui, sans-serif; }
  th, td { border: 1px solid #d9e0d8; padding: .55rem .7rem; text-align: left; }
  th { background: #eef6ef; }
  .cols { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  .box { padding: .85rem 1rem; background: #f7faf6; border: 1px solid #dce8dc; }
  .warn { background: #fff7ed; border-color: #f3d5b0; }
  .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.6rem; }
  .line { margin-top: 2.2rem; border-top: 1px solid #1a1a1a; padding-top: .4rem; font-size: .85rem; }
  @media (max-width: 640px) { .cols, .sign { grid-template-columns: 1fr; } .sheet { margin: 0; padding: 24px; } }
  @media print { body { background: #fff; } .sheet { border: 0; margin: 0; max-width: none; } }
</style>
</head>
<body>
<article class="sheet">
  <div class="kicker">${escapeHtml(c.kicker)}</div>
  <h1>${escapeHtml(c.title)}</h1>
  <p class="sub">${escapeHtml(c.subtitle)}</p>
  <h2>${escapeHtml(c.s1Title)}</h2>
  <p>${escapeHtml(c.s1Standard)}</p>
  <p>${escapeHtml(c.s1Premium)}</p>
  <p><strong>${escapeHtml(c.s1Use)}</strong></p>
  <h2>${escapeHtml(c.s2Title)}</h2>
  <div class="cols">
    <div class="box"><p><strong>${escapeHtml(c.s2ExecTitle)}</strong></p><ul>${exec}</ul></div>
    <div class="box"><p><strong>${escapeHtml(c.s2ClientTitle)}</strong></p><ul>${client}</ul></div>
  </div>
  <p>${escapeHtml(c.s2Note)}</p>
  <h2>${escapeHtml(c.s3Title)}</h2>
  <div class="box warn">
    <p>${escapeHtml(c.s3Lead)}</p>
    <ul>${unused}</ul>
    <p><strong>${escapeHtml(c.s3Foot)}</strong></p>
  </div>
  <h2>${escapeHtml(c.s4Title)}</h2>
  <table>
    <thead><tr>${c.s4Head.map((h) => `<th>${escapeHtml(h)}</th>`).join('')}</tr></thead>
    <tbody>${rows}</tbody>
  </table>
  <p>${escapeHtml(c.s4Pay)}</p>
  <h2>${escapeHtml(c.s5Title)}</h2>
  <ul>${rules}</ul>
  <p>${escapeHtml(c.signAck)}</p>
  <div class="sign">
    <div><div class="line">${escapeHtml(c.signExec)} · ${escapeHtml(c.stamp)}</div></div>
    <div><div class="line">${escapeHtml(c.signClient)} · ${escapeHtml(c.signDate)}</div></div>
  </div>
</article>
</body>
</html>`;

  const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = c.fileName;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}
