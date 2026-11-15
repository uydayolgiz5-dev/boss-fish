const W = 1080;
const H = 1350;

function loadImage(src) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => resolve(img);
    img.onerror = reject;
    img.src = src;
  });
}

function roundRect(ctx, x, y, w, h, r) {
  const radius = Math.min(r, w / 2, h / 2);
  ctx.beginPath();
  ctx.moveTo(x + radius, y);
  ctx.arcTo(x + w, y, x + w, y + h, radius);
  ctx.arcTo(x + w, y + h, x, y + h, radius);
  ctx.arcTo(x, y + h, x, y, radius);
  ctx.arcTo(x, y, x + w, y, radius);
  ctx.closePath();
}

function fillRound(ctx, x, y, w, h, r, fill) {
  roundRect(ctx, x, y, w, h, r);
  ctx.fillStyle = fill;
  ctx.fill();
}

function drawContain(ctx, img, x, y, w, h) {
  if (!img) return;
  const ir = img.width / Math.max(img.height, 1);
  const box = w / Math.max(h, 1);
  let dw = w;
  let dh = h;
  if (ir > box) {
    dw = w;
    dh = w / ir;
  } else {
    dh = h;
    dw = h * ir;
  }
  const dx = x + (w - dw) / 2;
  const dy = y + (h - dh) / 2;
  ctx.drawImage(img, dx, dy, dw, dh);
}

function drawProductFrame(ctx, img, x, y, w, h, radius = 22) {
  ctx.save();
  roundRect(ctx, x, y, w, h, radius);
  ctx.clip();
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(x, y, w, h);
  drawContain(ctx, img, x, y, w, h);
  ctx.restore();
}

function paintBackground(ctx) {
  const g = ctx.createLinearGradient(0, 0, W, H);
  g.addColorStop(0, '#04291c');
  g.addColorStop(0.5, '#075c38');
  g.addColorStop(1, '#0a6b40');
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, W, H);
  const glow = ctx.createRadialGradient(980, 40, 20, 980, 40, 520);
  glow.addColorStop(0, 'rgba(47, 180, 110, 0.32)');
  glow.addColorStop(1, 'rgba(47, 180, 110, 0)');
  ctx.fillStyle = glow;
  ctx.fillRect(0, 0, W, H);
}

function drawLogoCard(ctx, logo, x, y, height) {
  const padX = 16;
  const padY = 10;
  const imgH = height - padY * 2;
  const imgW = logo ? (logo.width / logo.height) * imgH : 160;
  const boxW = imgW + padX * 2;
  fillRound(ctx, x, y, boxW, height, 18, '#ffffff');
  if (logo) ctx.drawImage(logo, x + padX, y + padY, imgW, imgH);
  return boxW;
}

function drawHeader(ctx, logo, part) {
  const boxW = drawLogoCard(ctx, logo, 48, 40, 96);
  fillRound(ctx, W - 48 - 110, 58, 110, 44, 22, 'rgba(255,255,255,0.14)');
  ctx.fillStyle = '#fff';
  ctx.font = '800 20px Inter, system-ui, sans-serif';
  ctx.textAlign = 'center';
  ctx.fillText(part, W - 48 - 55, 87);
  ctx.textAlign = 'left';
  return 40 + 96 + 28;
}

function drawFooter(ctx, logo, phone, cta) {
  ctx.strokeStyle = 'rgba(255,255,255,0.16)';
  ctx.beginPath();
  ctx.moveTo(48, H - 86);
  ctx.lineTo(W - 48, H - 86);
  ctx.stroke();
  drawLogoCard(ctx, logo, 48, H - 74, 48);
  ctx.fillStyle = '#fff';
  ctx.font = '800 20px Inter, system-ui, sans-serif';
  ctx.fillText('Venger Carp', 200, H - 46);
  ctx.fillStyle = '#d4efe0';
  ctx.font = '650 16px Inter, system-ui, sans-serif';
  ctx.fillText(`${cta} · ${phone}`, 200, H - 24);
}

function kpi(ctx, x, y, w, h, label, value, accent) {
  fillRound(ctx, x, y, w, h, 16, accent ? 'rgba(47, 216, 135, 0.18)' : 'rgba(255,255,255,0.10)');
  ctx.fillStyle = '#d4efe0';
  ctx.font = '750 14px Inter, system-ui, sans-serif';
  ctx.fillText(label, x + 16, y + Math.min(26, h * 0.38));
  ctx.fillStyle = '#fff';
  ctx.font = `800 ${h < 70 ? 22 : 30}px Inter, system-ui, sans-serif`;
  ctx.fillText(value, x + 16, y + h - 14);
}

function drawPoster1(ctx, logo, feed, d) {
  paintBackground(ctx);
  let y = drawHeader(ctx, logo, d.part1);

  ctx.fillStyle = '#c8f5d8';
  ctx.font = '800 15px Inter, system-ui, sans-serif';
  ctx.fillText(d.kicker.toUpperCase(), 48, y);
  y += 36;

  ctx.fillStyle = '#fff';
  ctx.font = '900 38px Inter, system-ui, sans-serif';
  ctx.fillText(d.brandTitle, 48, y);
  y += 18;

  const productH = 380;
  drawProductFrame(ctx, feed, 48, y, W - 96, productH, 24);
  fillRound(ctx, 68, y + productH - 58, 520, 42, 12, 'rgba(5, 56, 37, 0.88)');
  ctx.fillStyle = '#fff';
  ctx.font = '800 18px Inter, system-ui, sans-serif';
  ctx.fillText(d.productLabel, 84, y + productH - 30);
  y += productH + 18;

  ctx.fillStyle = 'rgba(255,255,255,0.92)';
  ctx.font = '700 22px Inter, system-ui, sans-serif';
  ctx.fillText(d.client, 48, y);
  y += 26;
  if (d.place) {
    ctx.fillStyle = '#d3efe0';
    ctx.font = '650 16px Inter, system-ui, sans-serif';
    ctx.fillText(d.place, 48, y);
    y += 24;
  }

  const chips = d.chips || [];
  let cx = 48;
  ctx.font = '750 16px Inter, system-ui, sans-serif';
  chips.forEach((c) => {
    const cw = ctx.measureText(c).width + 24;
    fillRound(ctx, cx, y, cw, 32, 10, 'rgba(255,255,255,0.12)');
    ctx.fillStyle = '#fff';
    ctx.fillText(c, cx + 12, y + 22);
    cx += cw + 8;
  });
  y += 48;

  const g = ctx.createLinearGradient(48, y, 520, y + 118);
  g.addColorStop(0, '#0b3d6e');
  g.addColorStop(1, '#1a6fb5');
  fillRound(ctx, 48, y, 520, 118, 18, g);
  ctx.fillStyle = 'rgba(255,255,255,0.85)';
  ctx.font = '800 14px Inter, system-ui, sans-serif';
  ctx.fillText('FCR', 70, y + 32);
  ctx.fillStyle = '#fff';
  ctx.font = '900 56px Inter, system-ui, sans-serif';
  ctx.fillText(d.fcr, 66, y + 86);
  ctx.fillStyle = 'rgba(255,255,255,0.9)';
  ctx.font = '700 15px Inter, system-ui, sans-serif';
  ctx.fillText(d.fcrFormula, 240, y + 78);

  kpi(ctx, 584, y, 448, 54, d.feedLabel, `${d.feedKg} kg`);
  kpi(ctx, 584, y + 64, 448, 54, d.gainLabel, `+${d.gainKg} kg`, true);
  y += 134;

  const gap = 14;
  const kw = (W - 96 - gap) / 2;
  kpi(ctx, 48, y, kw, 72, d.stockLabel, `${d.stockedKg} kg`);
  kpi(ctx, 48 + kw + gap, y, kw, 72, d.endLabel, `${d.endKg} kg`);

  drawFooter(ctx, logo, d.phone, d.cta);
}

function wrapText(ctx, text, x, y, maxW, lineH) {
  const words = String(text || '').split(' ');
  let line = '';
  let yy = y;
  words.forEach((word, i) => {
    const test = line ? `${line} ${word}` : word;
    if (ctx.measureText(test).width > maxW && line) {
      ctx.fillText(line, x, yy);
      line = word;
      yy += lineH;
    } else {
      line = test;
    }
    if (i === words.length - 1) ctx.fillText(line, x, yy);
  });
}

function drawPoster2(ctx, logo, feed, d) {
  paintBackground(ctx);
  let y = drawHeader(ctx, logo, d.part2);

  ctx.fillStyle = '#c8f5d8';
  ctx.font = '800 16px Inter, system-ui, sans-serif';
  ctx.fillText(d.kicker.toUpperCase(), 48, y);
  y += 44;
  ctx.fillStyle = '#fff';
  ctx.font = '900 38px Inter, system-ui, sans-serif';
  ctx.fillText(d.growthTitle, 48, y);
  y += 40;
  ctx.fillStyle = 'rgba(255,255,255,0.92)';
  ctx.font = '700 22px Inter, system-ui, sans-serif';
  ctx.fillText(d.client, 48, y);
  y += 28;

  fillRound(ctx, 48, y, W - 96, 110, 18, 'rgba(255,255,255,0.10)');
  ctx.fillStyle = '#d4efe0';
  ctx.font = '700 14px Inter, system-ui, sans-serif';
  ctx.fillText(d.startGLabel, 72, y + 32);
  ctx.fillText(d.nowGLabel, 430, y + 32);
  ctx.fillStyle = '#fff';
  ctx.font = '900 36px Inter, system-ui, sans-serif';
  ctx.fillText(`${d.avgFrom} g`, 72, y + 78);
  ctx.fillStyle = '#9af0bf';
  ctx.fillText('→', 300, y + 74);
  ctx.fillStyle = '#fff';
  ctx.fillText(`${d.avgTo} g`, 430, y + 78);
  if (d.mult) {
    fillRound(ctx, 820, y + 28, 160, 54, 12, '#f0b429');
    ctx.fillStyle = '#3d2a00';
    ctx.font = '900 28px Inter, system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(`×${d.mult}`, 900, y + 64);
    ctx.textAlign = 'left';
  }
  y += 128;

  const stats = d.stats.filter((s) => s.value);
  const cols = 2;
  const sw = (W - 96 - 12) / 2;
  stats.forEach((s, i) => {
    const col = i % cols;
    const row = Math.floor(i / cols);
    const x = 48 + col * (sw + 12);
    const sy = y + row * 78;
    fillRound(ctx, x, sy, sw, 68, 14, 'rgba(255,255,255,0.10)');
    ctx.fillStyle = '#d4efe0';
    ctx.font = '750 13px Inter, system-ui, sans-serif';
    ctx.fillText(s.label, x + 14, sy + 24);
    ctx.fillStyle = '#fff';
    ctx.font = '800 24px Inter, system-ui, sans-serif';
    ctx.fillText(s.value, x + 14, sy + 52);
  });
  y += Math.ceil(stats.length / 2) * 78 + 10;

  (d.fishLines || []).forEach((line) => {
    fillRound(ctx, 48, y, W - 96, 48, 12, 'rgba(255,255,255,0.08)');
    ctx.fillStyle = '#fff';
    ctx.font = '800 18px Inter, system-ui, sans-serif';
    ctx.fillText(line.name, 64, y + 32);
    ctx.font = '650 17px Inter, system-ui, sans-serif';
    ctx.fillText(line.detail, 280, y + 32);
    if (line.loss) {
      ctx.fillStyle = '#ffb4a8';
      ctx.font = '800 17px Inter, system-ui, sans-serif';
      ctx.textAlign = 'right';
      ctx.fillText(line.loss, W - 64, y + 32);
      ctx.textAlign = 'left';
    }
    y += 56;
  });

  const bannerY = H - 86 - 18 - 250;
  drawProductFrame(ctx, feed, 48, bannerY, W - 96, 250, 20);
  fillRound(ctx, 68, bannerY + 16, 560, 64, 12, 'rgba(5, 56, 37, 0.9)');
  ctx.fillStyle = '#fff';
  ctx.font = '800 20px Inter, system-ui, sans-serif';
  ctx.fillText(d.productLabel, 84, bannerY + 42);
  ctx.font = '650 15px Inter, system-ui, sans-serif';
  ctx.fillStyle = '#d4efe0';
  ctx.fillText(d.feedSummary || '', 84, bannerY + 64);

  drawFooter(ctx, logo, d.phone, d.cta);
}

export async function renderVengerPosters(d) {
  const [logo, feed] = await Promise.all([
    loadImage('/images/venger-carp-logo.png').catch(() => null),
    loadImage('/images/venger-carp-feed.jpg').catch(() => null),
  ]);

  const c1 = document.createElement('canvas');
  c1.width = W;
  c1.height = H;
  drawPoster1(c1.getContext('2d'), logo, feed, d);

  const c2 = document.createElement('canvas');
  c2.width = W;
  c2.height = H;
  drawPoster2(c2.getContext('2d'), logo, feed, d);

  return [c1.toDataURL('image/png'), c2.toDataURL('image/png')];
}

/**
 * Good News — Venger Carp brend uslubi:
 * keng bo‘limlar, oq kartochkalar, jadval chiziqlari, mahsulot ranglari.
 */
const GN_W = 1080;
/** Dastlabki balandlik — chizib bo‘lgach kontentga qirqiladi */
const GN_H_MAX = 3600;

// Brend palitra (Venger yashil + oltin aksent)
const V = {
  deep: '#02150e',
  forest: '#043525',
  green: '#0a6b40',
  leaf: '#128a52',
  mint: '#2fb46e',
  soft: '#7ddea8',
  cream: '#eef8f1',
  card: '#ffffff',
  cardAlt: '#f4fbf7',
  line: '#c5e6d4',
  lineStrong: '#0a6b40',
  ink: '#143528',
  mute: '#4d6b5c',
  white: '#ffffff',
  gold: '#d4af37',
  goldSoft: '#f0d78c',
  /** FCR — plakatda ajralib turadigan issiq rang */
  fcrDeep: '#7c1d6f',
  fcrMid: '#c41e6a',
  fcrHot: '#ff5a36',
  fcrGlow: '#ffe56a',
};

function fitText(ctx, text, maxW) {
  let t = String(text ?? '—');
  if (!t) t = '—';
  if (ctx.measureText(t).width <= maxW) return t;
  while (t.length > 2 && ctx.measureText(`${t}…`).width > maxW) t = t.slice(0, -1);
  return `${t}…`;
}

function wrapLines(ctx, text, maxW, maxLines = 4) {
  const words = String(text || '').split(/\s+/).filter(Boolean);
  const lines = [];
  let cur = '';
  words.forEach((w) => {
    const next = cur ? `${cur} ${w}` : w;
    if (ctx.measureText(next).width <= maxW) cur = next;
    else {
      if (cur) lines.push(cur);
      cur = w;
    }
  });
  if (cur) lines.push(cur);
  if (lines.length <= maxLines) return lines;
  const clipped = lines.slice(0, maxLines);
  clipped[maxLines - 1] = fitText(ctx, clipped[maxLines - 1], maxW);
  return clipped;
}

function strokePanel(ctx, x, y, w, h, r = 18) {
  roundRect(ctx, x, y, w, h, r);
  ctx.strokeStyle = 'rgba(10,107,64,0.55)';
  ctx.lineWidth = 2.4;
  ctx.stroke();
  // ichki yumshoq yorug‘lik
  roundRect(ctx, x + 1.5, y + 1.5, w - 3, h - 3, Math.max(r - 1, 4));
  ctx.strokeStyle = 'rgba(255,255,255,0.72)';
  ctx.lineWidth = 1.2;
  ctx.stroke();
}

function fillPanel(ctx, x, y, w, h, r = 18, fill = V.card) {
  ctx.save();
  ctx.shadowColor = 'rgba(0,0,0,0.28)';
  ctx.shadowBlur = 18;
  ctx.shadowOffsetY = 8;
  roundRect(ctx, x, y, w, h, r);
  ctx.fillStyle = fill;
  ctx.fill();
  ctx.restore();

  // yuqoridan pastga biroz gradient kartochka
  const g = ctx.createLinearGradient(x, y, x, y + h);
  g.addColorStop(0, fill);
  let end = fill;
  if (fill === V.card || fill === '#ffffff') end = V.cardAlt;
  else if (fill === '#fffef8') end = '#f7f3e6';
  g.addColorStop(1, end);
  fillRound(ctx, x, y, w, h, r, g);
  strokePanel(ctx, x, y, w, h, r);
}

function drawAccentBar(ctx, x, y, w, accent = false) {
  const g = ctx.createLinearGradient(x, y, x + w, y);
  if (accent) {
    g.addColorStop(0, V.gold);
    g.addColorStop(0.5, V.goldSoft);
    g.addColorStop(1, V.gold);
  } else {
    g.addColorStop(0, V.mint);
    g.addColorStop(0.5, V.soft);
    g.addColorStop(1, V.mint);
  }
  fillRound(ctx, x, y, w, 6, 3, g);
  // markazda romb
  const cx = x + w / 2;
  const cy = y + 3;
  ctx.fillStyle = accent ? '#fff6d0' : '#ffffff';
  ctx.beginPath();
  ctx.moveTo(cx, cy - 4);
  ctx.lineTo(cx + 4, cy);
  ctx.lineTo(cx, cy + 4);
  ctx.lineTo(cx - 4, cy);
  ctx.closePath();
  ctx.fill();
}

/** Oltin burchak bezagi */
function drawCornerOrnament(ctx, x, y, size, rot = 0) {
  ctx.save();
  ctx.translate(x, y);
  ctx.rotate(rot);
  ctx.strokeStyle = 'rgba(240,215,140,0.85)';
  ctx.lineWidth = 2.2;
  ctx.lineCap = 'round';
  ctx.beginPath();
  ctx.moveTo(0, size);
  ctx.lineTo(0, 0);
  ctx.lineTo(size, 0);
  ctx.stroke();
  ctx.beginPath();
  ctx.moveTo(6, size - 4);
  ctx.lineTo(6, 6);
  ctx.lineTo(size - 4, 6);
  ctx.strokeStyle = 'rgba(125,222,168,0.7)';
  ctx.lineWidth = 1.4;
  ctx.stroke();
  // burchakda yulduzcha
  ctx.fillStyle = V.goldSoft;
  ctx.beginPath();
  ctx.arc(0, 0, 3.5, 0, Math.PI * 2);
  ctx.fill();
  ctx.restore();
}

/** Yulduz / uchqun */
function drawSpark(ctx, x, y, r = 5, color = V.goldSoft) {
  ctx.save();
  ctx.fillStyle = color;
  ctx.beginPath();
  for (let i = 0; i < 8; i += 1) {
    const a = (i * Math.PI) / 4;
    const rr = i % 2 === 0 ? r : r * 0.38;
    const px = x + Math.cos(a) * rr;
    const py = y + Math.sin(a) * rr;
    if (i === 0) ctx.moveTo(px, py);
    else ctx.lineTo(px, py);
  }
  ctx.closePath();
  ctx.fill();
  ctx.restore();
}

/** Bo‘limlar orasidagi ornament chiziq */
function drawOrnamentDivider(ctx, x, w, y) {
  const mid = x + w / 2;
  const g = ctx.createLinearGradient(x, y, x + w, y);
  g.addColorStop(0, 'rgba(212,175,55,0)');
  g.addColorStop(0.25, 'rgba(212,175,55,0.55)');
  g.addColorStop(0.5, 'rgba(125,222,168,0.8)');
  g.addColorStop(0.75, 'rgba(212,175,55,0.55)');
  g.addColorStop(1, 'rgba(212,175,55,0)');
  ctx.strokeStyle = g;
  ctx.lineWidth = 1.6;
  ctx.beginPath();
  ctx.moveTo(x + 40, y);
  ctx.lineTo(mid - 18, y);
  ctx.moveTo(mid + 18, y);
  ctx.lineTo(x + w - 40, y);
  ctx.stroke();
  // markaz romb
  ctx.fillStyle = V.goldSoft;
  ctx.beginPath();
  ctx.moveTo(mid, y - 7);
  ctx.lineTo(mid + 7, y);
  ctx.lineTo(mid, y + 7);
  ctx.lineTo(mid - 7, y);
  ctx.closePath();
  ctx.fill();
  ctx.strokeStyle = 'rgba(10,107,64,0.5)';
  ctx.lineWidth = 1;
  ctx.stroke();
  drawSpark(ctx, mid - 22, y, 4, 'rgba(240,215,140,0.9)');
  drawSpark(ctx, mid + 22, y, 4, 'rgba(125,222,168,0.9)');
}

/** Sarlavha yonidagi gul bezak */
function drawTitleFlourish(ctx, cx, cy, dir = 1) {
  ctx.save();
  ctx.strokeStyle = 'rgba(240,215,140,0.9)';
  ctx.fillStyle = 'rgba(240,215,140,0.9)';
  ctx.lineWidth = 2;
  ctx.lineCap = 'round';
  ctx.beginPath();
  ctx.moveTo(cx, cy);
  ctx.quadraticCurveTo(cx + dir * 28, cy - 14, cx + dir * 56, cy - 2);
  ctx.stroke();
  ctx.beginPath();
  ctx.moveTo(cx, cy);
  ctx.quadraticCurveTo(cx + dir * 24, cy + 12, cx + dir * 50, cy + 4);
  ctx.stroke();
  drawSpark(ctx, cx + dir * 8, cy - 10, 5);
  drawSpark(ctx, cx + dir * 42, cy - 6, 3.5, V.soft);
  ctx.restore();
}

function drawSectionEyebrow(ctx, text, x, y) {
  const label = String(text || '').toUpperCase();
  ctx.font = '800 16px Inter, system-ui, sans-serif';
  const tw = ctx.measureText(label).width;
  const pw = tw + 36;
  // shisha-kapsula + yon chiziq
  fillRound(ctx, x, y - 16, pw, 34, 17, 'rgba(255,255,255,0.16)');
  roundRect(ctx, x, y - 16, pw, 34, 17);
  ctx.strokeStyle = 'rgba(240,215,140,0.55)';
  ctx.lineWidth = 1.6;
  ctx.stroke();
  fillRound(ctx, x + 10, y - 4, 10, 10, 5, V.gold);
  fillRound(ctx, x + 12, y - 2, 6, 6, 3, V.goldSoft);
  ctx.textAlign = 'left';
  ctx.fillStyle = V.cream;
  ctx.fillText(label, x + 28, y + 6);
  // o‘ngga cho‘zilgan ornament chiziq
  const lx = x + pw + 12;
  const g = ctx.createLinearGradient(lx, y, lx + 160, y);
  g.addColorStop(0, 'rgba(125,222,168,0.7)');
  g.addColorStop(1, 'rgba(125,222,168,0)');
  ctx.strokeStyle = g;
  ctx.lineWidth = 2;
  ctx.beginPath();
  ctx.moveTo(lx, y);
  ctx.lineTo(lx + 160, y);
  ctx.stroke();
  drawSpark(ctx, lx + 8, y, 3.5, V.goldSoft);
}

/** Kartochkalar orasidagi bog‘lovchi — gradient ko‘prik + o‘q */
function drawCardLink(ctx, x1, x2, midY) {
  const left = x1 - 2;
  const right = x2 + 2;
  const w = right - left;
  if (w < 12) return;

  const h = 12;
  const g = ctx.createLinearGradient(left, midY, right, midY);
  g.addColorStop(0, V.mint);
  g.addColorStop(0.5, V.goldSoft);
  g.addColorStop(1, V.mint);
  fillRound(ctx, left, midY - h / 2, w, h, 6, g);

  ctx.fillStyle = '#ffffff';
  ctx.beginPath();
  ctx.moveTo(right - 3, midY);
  ctx.lineTo(right - 12, midY - 5);
  ctx.lineTo(right - 12, midY + 5);
  ctx.closePath();
  ctx.fill();
}

/** Kartochka burchak qavs lari */
function drawCardCorners(ctx, x, y, w, h, accent = false) {
  const s = 16;
  const c = accent ? 'rgba(212,175,55,0.85)' : 'rgba(47,180,110,0.7)';
  ctx.strokeStyle = c;
  ctx.lineWidth = 2.2;
  ctx.lineCap = 'round';
  // TL
  ctx.beginPath();
  ctx.moveTo(x + 10, y + 10 + s);
  ctx.lineTo(x + 10, y + 10);
  ctx.lineTo(x + 10 + s, y + 10);
  ctx.stroke();
  // TR
  ctx.beginPath();
  ctx.moveTo(x + w - 10 - s, y + 10);
  ctx.lineTo(x + w - 10, y + 10);
  ctx.lineTo(x + w - 10, y + 10 + s);
  ctx.stroke();
  // BL
  ctx.beginPath();
  ctx.moveTo(x + 10, y + h - 10 - s);
  ctx.lineTo(x + 10, y + h - 10);
  ctx.lineTo(x + 10 + s, y + h - 10);
  ctx.stroke();
  // BR
  ctx.beginPath();
  ctx.moveTo(x + w - 10 - s, y + h - 10);
  ctx.lineTo(x + w - 10, y + h - 10);
  ctx.lineTo(x + w - 10, y + h - 10 - s);
  ctx.stroke();
}

/** FCR kartochka — boshqa kartalardan keskin ajraladi */
function drawFcrStatCard(ctx, x, y, w, h, item) {
  ctx.save();
  ctx.shadowColor = 'rgba(196, 30, 106, 0.45)';
  ctx.shadowBlur = 22;
  ctx.shadowOffsetY = 8;
  roundRect(ctx, x, y, w, h, 20);
  const bg = ctx.createLinearGradient(x, y, x + w, y + h);
  bg.addColorStop(0, V.fcrDeep);
  bg.addColorStop(0.48, V.fcrMid);
  bg.addColorStop(1, V.fcrHot);
  ctx.fillStyle = bg;
  ctx.fill();
  ctx.restore();

  ctx.strokeStyle = 'rgba(255, 229, 106, 0.55)';
  ctx.lineWidth = 2;
  roundRect(ctx, x + 1, y + 1, w - 2, h - 2, 19);
  ctx.stroke();

  // yuqori oltin chiziq
  const bar = ctx.createLinearGradient(x + 16, y + 12, x + w - 16, y + 12);
  bar.addColorStop(0, V.fcrGlow);
  bar.addColorStop(0.5, '#fff6d0');
  bar.addColorStop(1, V.fcrGlow);
  fillRound(ctx, x + 16, y + 12, w - 32, 6, 3, bar);

  drawSpark(ctx, x + w - 28, y + 34, 7, V.fcrGlow);

  ctx.textAlign = 'left';
  ctx.fillStyle = V.fcrGlow;
  ctx.font = '900 16px Inter, system-ui, sans-serif';
  ctx.fillText(fitText(ctx, (item.label || 'FCR').toUpperCase(), w - 36), x + 18, y + 46);

  ctx.fillStyle = '#ffffff';
  ctx.font = '900 42px Inter, system-ui, sans-serif';
  ctx.fillText(fitText(ctx, item.value || '—', w - 36), x + 18, y + 98);

  if (item.sub) {
    ctx.fillStyle = 'rgba(255,255,255,0.9)';
    ctx.font = '700 13px Inter, system-ui, sans-serif';
    const subLines = wrapLines(ctx, item.sub, w - 36, 2);
    subLines.forEach((line, i) => {
      ctx.fillText(line, x + 18, y + h - 20 - (subLines.length - 1 - i) * 17);
    });
  }
}

/** Alohida kartochka — yumshoq soya + gradient aksent */
function drawStatCard(ctx, x, y, w, h, item) {
  if (item.hot || item.tone === 'fcr') {
    drawFcrStatCard(ctx, x, y, w, h, item);
    return;
  }

  fillPanel(ctx, x, y, w, h, 20, V.card);
  drawAccentBar(ctx, x + 16, y + 12, w - 32, !!item.accent);
  drawCardCorners(ctx, x, y, w, h, !!item.accent);

  if (item.accent) {
    drawSpark(ctx, x + w - 28, y + 34, 6, V.gold);
  }

  ctx.textAlign = 'left';
  ctx.fillStyle = V.mute;
  ctx.font = '800 15px Inter, system-ui, sans-serif';
  ctx.fillText(fitText(ctx, item.label || '', w - 36), x + 18, y + 44);

  ctx.fillStyle = item.accent ? V.green : V.ink;
  ctx.font = item.accent
    ? '900 34px Inter, system-ui, sans-serif'
    : '900 30px Inter, system-ui, sans-serif';
  ctx.fillText(fitText(ctx, item.value || '—', w - 36), x + 18, y + 90);

  if (item.sub) {
    ctx.fillStyle = V.mute;
    ctx.font = '700 14px Inter, system-ui, sans-serif';
    const subLines = wrapLines(ctx, item.sub, w - 36, 2);
    subLines.forEach((line, i) => {
      ctx.fillText(line, x + 18, y + h - 22 - (subLines.length - 1 - i) * 18);
    });
  }
}

/**
 * Mavzu sarlavhasi + chetli kartochkalar + orada kichik bog‘lovchilar.
 */
function drawStatRow(ctx, x, y, totalW, gap, title, items, cardH = 136) {
  let yy = y;
  if (title) {
    drawSectionEyebrow(ctx, title, x, yy + 16);
    yy += 42;
  }
  const n = Math.max(items.length, 1);
  // bog‘lovchi uchun yetarli oraliq
  const linkGap = Math.max(gap, 34);
  const cw = (totalW - linkGap * (n - 1)) / n;
  items.forEach((item, i) => {
    drawStatCard(ctx, x + i * (cw + linkGap), yy, cw, cardH, item);
  });
  // bog‘lovchilar kartochkalardan KEYIN — ustida ko‘rinsin
  for (let i = 0; i < n - 1; i += 1) {
    const left = x + i * (cw + linkGap) + cw;
    const right = left + linkGap;
    drawCardLink(ctx, left + 2, right - 2, yy + cardH / 2);
  }
  return (yy - y) + cardH;
}

/** @returns {number} kontent pastki chegarasi (px) */
function drawGoodNewsPoster(ctx, logo, bag, fish, qrTg, qrIg, d, canvasH) {
  const g = ctx.createLinearGradient(0, 0, GN_W * 0.2, canvasH);
  g.addColorStop(0, V.deep);
  g.addColorStop(0.28, V.forest);
  g.addColorStop(0.62, V.green);
  g.addColorStop(1, '#0f8a52');
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, GN_W, canvasH);

  // yuqori nurlanish
  const glow = ctx.createRadialGradient(GN_W * 0.5, 40, 8, GN_W * 0.5, 140, 620);
  glow.addColorStop(0, 'rgba(240,215,140,0.28)');
  glow.addColorStop(0.35, 'rgba(125,222,168,0.38)');
  glow.addColorStop(1, 'rgba(125,222,168,0)');
  ctx.fillStyle = glow;
  ctx.fillRect(0, 0, GN_W, 720);

  // yon nurlanishlar
  const sideGlow = ctx.createRadialGradient(0, canvasH * 0.45, 10, 0, canvasH * 0.45, 420);
  sideGlow.addColorStop(0, 'rgba(47,180,110,0.22)');
  sideGlow.addColorStop(1, 'rgba(47,180,110,0)');
  ctx.fillStyle = sideGlow;
  ctx.fillRect(0, 0, 480, canvasH);

  // to‘lqin naqshlari
  ctx.save();
  ctx.globalAlpha = 0.14;
  for (let i = 0; i < 6; i += 1) {
    ctx.strokeStyle = i % 2 ? V.goldSoft : V.soft;
    ctx.lineWidth = 14 - i;
    ctx.beginPath();
    ctx.moveTo(-40, 180 + i * 120);
    ctx.quadraticCurveTo(GN_W * 0.35, 120 + i * 95, GN_W * 0.7, 220 + i * 105);
    ctx.quadraticCurveTo(GN_W * 0.9, 280 + i * 85, GN_W + 40, 200 + i * 115);
    ctx.stroke();
  }
  ctx.restore();

  // uchqunlar / yulduzchalar (fon bezagi)
  const sparks = [
    [70, 120, 5], [180, 70, 3.5], [980, 90, 5], [1020, 200, 3],
    [40, 520, 4], [1040, 680, 4.5], [90, 980, 3.5], [1000, 1100, 4],
    [60, 1450, 3], [1020, 1600, 5], [80, 1900, 4], [1010, 2100, 3.5],
    [50, 2400, 4], [1030, 2600, 3], [70, 2900, 4.5], [1000, 3100, 3.5],
  ];
  sparks.forEach(([sx, sy, sr], i) => {
    drawSpark(ctx, sx, sy, sr, i % 2 ? 'rgba(240,215,140,0.55)' : 'rgba(125,222,168,0.45)');
  });

  const pad = 32;
  const inner = GN_W - pad * 2;
  const gap = 36;
  let y = 28;

  if (logo) {
    const lh = 60;
    const lw = (logo.width / Math.max(logo.height, 1)) * lh;
    ctx.save();
    ctx.shadowColor = 'rgba(0,0,0,0.25)';
    ctx.shadowBlur = 12;
    ctx.shadowOffsetY = 4;
    fillRound(ctx, pad, y, lw + 20, lh + 14, 14, V.white);
    ctx.restore();
    roundRect(ctx, pad, y, lw + 20, lh + 14, 14);
    ctx.strokeStyle = 'rgba(212,175,55,0.7)';
    ctx.lineWidth = 2.4;
    ctx.stroke();
    drawSpark(ctx, pad + lw + 14, y + 8, 5, V.gold);
    ctx.drawImage(logo, pad + 10, y + 7, lw, lh);
  }

  // Bitta katta sarlavha — soya + gul bezak + oltin chiziq
  ctx.textAlign = 'center';
  const title = String(d.title || 'VENGER CARP yemi natijasi');
  ctx.font = '900 44px Inter, system-ui, sans-serif';
  const titleText = fitText(ctx, title, inner - 160);
  const titleW = ctx.measureText(titleText).width;
  drawTitleFlourish(ctx, GN_W / 2 - titleW / 2 - 18, y + 42, -1);
  drawTitleFlourish(ctx, GN_W / 2 + titleW / 2 + 18, y + 42, 1);
  ctx.fillStyle = 'rgba(0,0,0,0.28)';
  ctx.fillText(titleText, GN_W / 2 + 2, y + 54);
  ctx.fillStyle = V.white;
  ctx.fillText(titleText, GN_W / 2, y + 52);
  const ug = ctx.createLinearGradient((GN_W - titleW) / 2, 0, (GN_W + titleW) / 2, 0);
  ug.addColorStop(0, 'rgba(212,175,55,0)');
  ug.addColorStop(0.2, V.gold);
  ug.addColorStop(0.8, V.goldSoft);
  ug.addColorStop(1, 'rgba(212,175,55,0)');
  fillRound(ctx, (GN_W - Math.min(titleW, 360)) / 2, y + 62, Math.min(titleW, 360), 4, 2, ug);
  drawSpark(ctx, GN_W / 2, y + 64, 4, V.goldSoft);
  y += 72;

  if (d.lead) {
    ctx.font = '700 18px Inter, system-ui, sans-serif';
    const lead = String(d.lead);
    const pw = Math.min(ctx.measureText(lead).width + 56, inner);
    const lx = (GN_W - pw) / 2;
    fillRound(ctx, lx, y, pw, 40, 20, 'rgba(255,255,255,0.16)');
    roundRect(ctx, lx, y, pw, 40, 20);
    ctx.strokeStyle = 'rgba(240,215,140,0.55)';
    ctx.lineWidth = 1.6;
    ctx.stroke();
    fillRound(ctx, lx + 12, y + 14, 12, 12, 6, V.gold);
    fillRound(ctx, lx + 14, y + 16, 8, 8, 4, V.goldSoft);
    ctx.fillStyle = V.cream;
    ctx.fillText(fitText(ctx, lead, pw - 48), GN_W / 2 + 6, y + 27);
    y += 56;
  }

  // Mijoz — oldingi ishlagan ixcham blok (ism + telefon keng, joy|maydon)
  const client = d.client || {};
  const clientH = 220;
  fillPanel(ctx, pad, y, inner, clientH, 22, V.card);
  // chap oltin lenta
  const ribbon = ctx.createLinearGradient(pad, y, pad + 10, y);
  ribbon.addColorStop(0, V.gold);
  ribbon.addColorStop(1, V.goldSoft);
  fillRound(ctx, pad, y + 18, 8, clientH - 36, 4, ribbon);
  drawAccentBar(ctx, pad + 20, y + 14, 72, true);
  drawCardCorners(ctx, pad, y, inner, clientH, true);

  ctx.textAlign = 'left';
  ctx.fillStyle = V.green;
  ctx.font = '800 15px Inter, system-ui, sans-serif';
  ctx.fillText(String(d.clientTitle || 'Mijoz').toUpperCase(), pad + 22, y + 36);

  ctx.fillStyle = V.ink;
  ctx.font = '900 34px Inter, system-ui, sans-serif';
  ctx.fillText(fitText(ctx, client.name || '—', inner - 52), pad + 22, y + 78);

  ctx.fillStyle = V.mute;
  ctx.font = '700 14px Inter, system-ui, sans-serif';
  ctx.fillText(client.phoneLabel || 'Telefon', pad + 22, y + 108);
  ctx.fillStyle = V.green;
  ctx.font = '900 28px Inter, system-ui, sans-serif';
  ctx.fillText(fitText(ctx, client.phone || '—', inner - 52), pad + 22, y + 140);

  ctx.strokeStyle = V.line;
  ctx.lineWidth = 2;
  ctx.beginPath();
  ctx.moveTo(pad + 20, y + 156);
  ctx.lineTo(pad + inner - 20, y + 156);
  ctx.stroke();

  const half = inner / 2;
  [
    { l: client.placeLabel || 'Joylashuv', v: client.place, x: pad },
    { l: client.areaLabel || 'Maydon', v: client.area, x: pad + half },
  ].forEach((c, i) => {
    if (i > 0) {
      ctx.beginPath();
      ctx.moveTo(c.x, y + 166);
      ctx.lineTo(c.x, y + clientH - 14);
      ctx.strokeStyle = V.line;
      ctx.lineWidth = 1.8;
      ctx.stroke();
    }
    ctx.fillStyle = V.mute;
    ctx.font = '700 13px Inter, system-ui, sans-serif';
    ctx.fillText(c.l, c.x + 20, y + 180);
    ctx.fillStyle = V.ink;
    ctx.font = '800 20px Inter, system-ui, sans-serif';
    ctx.fillText(fitText(ctx, c.v || '—', half - 44), c.x + 20, y + 206);
  });
  y += clientH + 18;
  drawOrnamentDivider(ctx, pad, inner, y + 4);
  y += 22;

  // Havza + baliq
  const pond = d.pond || {};
  const species = d.species || {};
  const density = d.density || {};
  drawSectionEyebrow(ctx, d.pondTitle || 'Havza va baliq', pad, y + 16);
  y += 44;
  const pondH = 150;
  const colW = (inner - gap * 2) / 3;

  drawStatCard(ctx, pad, y, colW, pondH, {
    label: pond.label || 'Maydon',
    value: pond.value || '—',
    accent: true,
  });

  const mx = pad + colW + gap;
  fillPanel(ctx, mx, y, colW, pondH, 20, V.card);
  drawAccentBar(ctx, mx + 16, y + 12, colW - 32, false);
  const fishBox = 64;
  const fx = mx + 16;
  const fy = y + 40;
  fillRound(ctx, fx, fy, fishBox, fishBox, 14, '#0b1f16');
  roundRect(ctx, fx, fy, fishBox, fishBox, 14);
  ctx.strokeStyle = 'rgba(125,222,168,0.55)';
  ctx.lineWidth = 1.5;
  ctx.stroke();
  if (fish) drawContain(ctx, fish, fx + 4, fy + 4, fishBox - 8, fishBox - 8);
  ctx.textAlign = 'left';
  ctx.fillStyle = V.mute;
  ctx.font = '800 14px Inter, system-ui, sans-serif';
  ctx.fillText(fitText(ctx, species.title || 'Baliq turi', colW - fishBox - 40), fx + fishBox + 12, y + 54);
  ctx.fillStyle = V.green;
  ctx.font = '900 28px Inter, system-ui, sans-serif';
  ctx.fillText(fitText(ctx, species.name || 'Sazan', colW - fishBox - 40), fx + fishBox + 12, y + 88);
  ctx.fillStyle = V.ink;
  ctx.font = '800 16px Inter, system-ui, sans-serif';
  ctx.fillText(fitText(ctx, species.count || '', colW - 32), mx + 16, y + pondH - 18);

  drawStatCard(ctx, pad + (colW + gap) * 2, y, colW, pondH, {
    label: density.label || 'Zichlik',
    value: density.value || '—',
    sub: density.hint || '1 gektarga',
    accent: true,
  });
  // bog‘lovchilar oxirida — ustida ko‘rinsin
  drawCardLink(ctx, pad + colW + 2, mx - 2, y + pondH / 2);
  drawCardLink(ctx, mx + colW + 2, pad + (colW + gap) * 2 - 2, y + pondH / 2);
  y += pondH + 16;
  drawOrnamentDivider(ctx, pad, inner, y + 4);
  y += 22;

  const dates = d.dates || {};
  y += drawStatRow(ctx, pad, y, inner, gap, d.periodTitle || 'Davr', [
    { label: dates.stockedLabel || 'Solingan sana', value: dates.stocked },
    { label: dates.soldLabel || 'Sotilgan sana', value: dates.sold },
    { label: dates.daysLabel || 'Boqilgan kunlar', value: dates.days, accent: true },
  ], 140) + 14;
  drawOrnamentDivider(ctx, pad, inner, y + 4);
  y += 22;

  const weights = d.weights || {};
  y += drawStatRow(ctx, pad, y, inner, gap, d.weightTitle || 'Vazn o‘zgarishi', [
    { label: weights.startLabel || 'Boshlang‘ich vazn', value: weights.start },
    { label: weights.endLabel || 'Yakuniy vazn', value: weights.end, accent: true },
    { label: weights.perFishLabel || '1 baliq o‘sishi', value: weights.perFish, accent: true },
  ], 140) + 14;
  drawOrnamentDivider(ctx, pad, inner, y + 4);
  y += 22;

  const totals = d.totals || {};
  y += drawStatRow(ctx, pad, y, inner, gap, d.feedGainTitle || 'Yem · o‘sish · FCR', [
    { label: totals.feedLabel || 'Ishlatilgan umumiy yem', value: totals.feed, sub: totals.feedTypes || '' },
    { label: totals.gainLabel || 'Umumiy o‘sish', value: totals.gain, sub: totals.gainTip || '', accent: true },
    {
      label: totals.fcrLabel || 'FCR',
      value: totals.fcr || '—',
      sub: totals.fcrTip || '',
      hot: true,
      tone: 'fcr',
    },
  ], 148) + 14;
  drawOrnamentDivider(ctx, pad, inner, y + 4);
  y += 22;

  // Mazmun — katta shrift, keng oralik
  const paragraphs = Array.isArray(d.explainParagraphs)
    ? d.explainParagraphs.filter(Boolean)
    : (d.explain ? [d.explain] : []);
  if (paragraphs.length) {
    ctx.font = '700 20px Inter, system-ui, sans-serif';
    const wrapped = [];
    paragraphs.forEach((p, pi) => {
      const lines = wrapLines(ctx, p, inner - 48, 8);
      lines.forEach((line) => wrapped.push(line));
      if (pi < paragraphs.length - 1) wrapped.push('');
    });
    const boxH = 64 + wrapped.length * 30 + 20;
    fillPanel(ctx, pad, y, inner, boxH, 20, '#fffef8');
    drawAccentBar(ctx, pad + 18, y + 16, 80, true);
    drawCardCorners(ctx, pad, y, inner, boxH, true);
    drawSpark(ctx, pad + inner - 30, y + 30, 6, V.gold);
    ctx.textAlign = 'left';
    ctx.fillStyle = V.green;
    ctx.font = '800 18px Inter, system-ui, sans-serif';
    ctx.fillText((d.explainTitle || 'Mazmun').toUpperCase(), pad + 20, y + 48);
    ctx.fillStyle = V.ink;
    ctx.font = '700 20px Inter, system-ui, sans-serif';
    let ty = y + 82;
    wrapped.forEach((line) => {
      if (line === '') {
        ty += 12;
        return;
      }
      ctx.fillText(line, pad + 24, ty);
      ty += 30;
    });
    y += boxH + 16;
    drawOrnamentDivider(ctx, pad, inner, y + 4);
    y += 22;
  }

  // Mahsulot + vizitka (telefon tagida kichik QR lar)
  const product = d.product || {};
  const infoH = 268;
  const bagW = 400;
  if (bag) {
    ctx.save();
    ctx.shadowColor = 'rgba(0,0,0,0.28)';
    ctx.shadowBlur = 16;
    ctx.shadowOffsetY = 6;
    drawProductFrame(ctx, bag, pad, y, bagW, infoH, 18);
    ctx.restore();
    roundRect(ctx, pad, y, bagW, infoH, 18);
    ctx.strokeStyle = 'rgba(212,175,55,0.7)';
    ctx.lineWidth = 2.8;
    ctx.stroke();
    drawCardCorners(ctx, pad, y, bagW, infoH, true);
  } else {
    fillRound(ctx, pad, y, bagW, infoH, 18, 'rgba(0,0,0,0.2)');
  }

  const midX = pad + bagW + 16;
  const midW = inner - bagW - 16;
  fillPanel(ctx, midX, y, midW, infoH, 18, V.card);
  drawAccentBar(ctx, midX + 16, y + 14, 68, true);
  drawCardCorners(ctx, midX, y, midW, infoH, true);

  ctx.textAlign = 'left';
  ctx.fillStyle = V.green;
  ctx.font = '800 15px Inter, system-ui, sans-serif';
  ctx.fillText((product.title || 'Venger Carp yemi').toUpperCase(), midX + 18, y + 40);

  const prodRows = [
    { name: product.extruder || 'Suzuvchi ekstruder yem', size: product.extruderSizes || '', color: '#1a3a6e' },
    { name: product.granula || 'Granula omuxta yem', size: product.granulaSizes || '', color: '#b01c28' },
  ];
  prodRows.forEach((row, i) => {
    const ry = y + 54 + i * 32;
    ctx.beginPath();
    ctx.arc(midX + 24, ry + 2, 7, 0, Math.PI * 2);
    ctx.fillStyle = row.color;
    ctx.fill();
    ctx.fillStyle = V.ink;
    ctx.font = '800 14px Inter, system-ui, sans-serif';
    ctx.fillText(fitText(ctx, row.name, midW - 48), midX + 40, ry + 6);
    if (row.size) {
      ctx.fillStyle = V.mute;
      ctx.font = '700 12px Inter, system-ui, sans-serif';
      ctx.fillText(fitText(ctx, `${row.size} · ${product.pack || '25 kg'}`, midW - 48), midX + 40, ry + 20);
    }
  });

  ctx.strokeStyle = V.line;
  ctx.lineWidth = 1.8;
  ctx.beginPath();
  ctx.moveTo(midX + 18, y + 124);
  ctx.lineTo(midX + midW - 18, y + 124);
  ctx.stroke();

  ctx.fillStyle = V.ink;
  ctx.font = '900 17px Inter, system-ui, sans-serif';
  ctx.fillText(fitText(ctx, product.phone || d.phone || '', midW - 36), midX + 18, y + 148);
  ctx.fillStyle = V.mute;
  ctx.font = '650 12px Inter, system-ui, sans-serif';
  ctx.fillText(fitText(ctx, product.address || '', midW - 36), midX + 18, y + 168);

  // Telefon tagida ikkita kichik QR
  const qrSize = 72;
  const qrGap = 10;
  const qrY = y + 180;
  const qrItems = [
    { img: qrTg, bg: '#12151a' },
    { img: qrIg, bg: '#ffffff' },
  ];
  qrItems.forEach((q, i) => {
    const qx = midX + 18 + i * (qrSize + qrGap);
    fillRound(ctx, qx, qrY, qrSize, qrSize, 10, q.bg);
    roundRect(ctx, qx, qrY, qrSize, qrSize, 10);
    ctx.strokeStyle = i === 0 ? 'rgba(125,222,168,0.55)' : 'rgba(212,175,55,0.55)';
    ctx.lineWidth = 1.8;
    ctx.stroke();
    if (q.img) drawContain(ctx, q.img, qx + 4, qrY + 4, qrSize - 8, qrSize - 8);
  });

  y += infoH + 24;

  // Footer — kontent oxiriga yopishtiriladi (bo‘sh joy qolmaydi)
  const fg = ctx.createLinearGradient(pad, 0, GN_W - pad, 0);
  fg.addColorStop(0, 'rgba(212,175,55,0)');
  fg.addColorStop(0.2, 'rgba(212,175,55,0.55)');
  fg.addColorStop(0.5, 'rgba(125,222,168,0.7)');
  fg.addColorStop(0.8, 'rgba(212,175,55,0.55)');
  fg.addColorStop(1, 'rgba(212,175,55,0)');
  ctx.strokeStyle = fg;
  ctx.lineWidth = 2;
  ctx.beginPath();
  ctx.moveTo(pad, y);
  ctx.lineTo(GN_W - pad, y);
  ctx.stroke();
  ctx.textAlign = 'left';
  ctx.fillStyle = V.cream;
  ctx.font = '800 16px Inter, system-ui, sans-serif';
  ctx.fillText(d.slogan || 'Venger Carp', pad, y + 28);
  ctx.textAlign = 'right';
  ctx.fillStyle = V.soft;
  ctx.font = '700 15px Inter, system-ui, sans-serif';
  ctx.fillText(product.trust || d.phone || '', GN_W - pad, y + 28);
  ctx.textAlign = 'left';

  return y + 48;
}

export async function renderVengerGoodNews(d) {
  const bust = 'v=33';
  const [logo, bag, fish, qrTg, qrIg] = await Promise.all([
    loadImage(`/images/venger-carp-logo-clean.jpg?${bust}`).catch(() => loadImage(`/images/venger-carp-logo.png?${bust}`).catch(() => null)),
    loadImage(`/images/venger-bags-duo.jpg?${bust}`).catch(() => loadImage(`/images/venger-bag-extruder.jpg?${bust}`).catch(() => null)),
    loadImage(`/images/fish/sazan.png?${bust}`).catch(() => null),
    loadImage(`/images/venger-qr-telegram.jpg?${bust}`).catch(() => null),
    loadImage(`/images/venger-qr-instagram.png?${bust}`).catch(() => null),
  ]);

  const draft = document.createElement('canvas');
  draft.width = GN_W;
  draft.height = GN_H_MAX;
  const usedH = drawGoodNewsPoster(draft.getContext('2d'), logo, bag, fish, qrTg, qrIg, d, GN_H_MAX);
  const finalH = Math.min(Math.max(Math.ceil(usedH), 1600), GN_H_MAX);

  const canvas = document.createElement('canvas');
  canvas.width = GN_W;
  canvas.height = finalH;
  const fctx = canvas.getContext('2d');
  fctx.drawImage(draft, 0, 0, GN_W, finalH, 0, 0, GN_W, finalH);
  // premium ramka + burchak ornamentlari
  roundRect(fctx, 8, 8, GN_W - 16, finalH - 16, 30);
  fctx.strokeStyle = 'rgba(212,175,55,0.55)';
  fctx.lineWidth = 3;
  fctx.stroke();
  roundRect(fctx, 14, 14, GN_W - 28, finalH - 28, 26);
  fctx.strokeStyle = 'rgba(125,222,168,0.35)';
  fctx.lineWidth = 1.8;
  fctx.stroke();
  const cs = 34;
  drawCornerOrnament(fctx, 22, 22, cs, 0);
  drawCornerOrnament(fctx, GN_W - 22, 22, cs, Math.PI / 2);
  drawCornerOrnament(fctx, GN_W - 22, finalH - 22, cs, Math.PI);
  drawCornerOrnament(fctx, 22, finalH - 22, cs, -Math.PI / 2);
  drawSpark(fctx, GN_W / 2, 22, 6, V.goldSoft);
  drawSpark(fctx, GN_W / 2, finalH - 22, 6, V.goldSoft);
  return canvas.toDataURL('image/png');
}
