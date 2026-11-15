#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Fill remaining used ZH keys via Google gtx. Run repeatedly until done."""
import json
import pathlib
import random
import re
import time
import urllib.parse
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[1]
BATCH = 80


def parse_pairs(block):
    pairs = {}
    for m in re.finditer(r"^\s+(\w+):\s*'((?:\\'|[^'])*)'\s*,?\s*$", block, re.M):
        pairs[m.group(1)] = m.group(2).replace("\\'", "'")
    return pairs


def extract_locale_block(src, locale):
    m = re.search(rf'\n\s*{locale}:\s*\{{', src)
    if not m:
        return ''
    i = m.end() - 1
    depth = 0
    start = None
    for j in range(i, len(src)):
        if src[j] == '{':
            if depth == 0:
                start = j + 1
            depth += 1
        elif src[j] == '}':
            depth -= 1
            if depth == 0:
                return src[start:j]
    return ''


def extract_export(src, name):
    m = re.search(rf'export const {name} = \{{', src)
    if not m:
        return ''
    i = m.end() - 1
    depth = 0
    start = None
    for j in range(i, len(src)):
        if src[j] == '{':
            if depth == 0:
                start = j + 1
            depth += 1
        elif src[j] == '}':
            depth -= 1
            if depth == 0:
                return src[start:j]
    return ''


def is_latin_heavy(value):
    if not value:
        return False
    latin = len(re.findall(r'[a-zA-Z]', value))
    cyr = len(re.findall(r'[\u0400-\u04FF]', value))
    han = len(re.findall(r'[\u4e00-\u9fff]', value))
    return latin >= 4 and latin > cyr and latin > han


PLACEHOLDER = re.compile(r'\{[^}]+\}')


def protect(s):
    slots = []

    def repl(m):
        slots.append(m.group(0))
        return f'[{len(slots) - 1}]'

    return PLACEHOLDER.sub(repl, s), slots


def restore(s, slots):
    for i, v in enumerate(slots):
        s = s.replace(f'[{i}]', v)
    return s


def main():
    text = (ROOT / 'resources/js/i18n.js').read_text(encoding='utf-8')
    gaps = (ROOT / 'resources/js/i18n/locale-gaps.js').read_text(encoding='utf-8')
    zhj = json.loads((ROOT / 'scripts/i18n-zh.json').read_text(encoding='utf-8'))
    ruj = json.loads((ROOT / 'scripts/i18n-ru-gaps.json').read_text(encoding='utf-8'))
    cache_path = ROOT / 'scripts/.i18n-api-cache.json'
    api = json.loads(cache_path.read_text(encoding='utf-8')) if cache_path.exists() else {}

    uz = parse_pairs(extract_locale_block(text, 'uz'))
    ru = {
        **uz,
        **parse_pairs(extract_locale_block(text, 'ru')),
        **parse_pairs(extract_export(gaps, 'ruGaps')),
        **ruj,
    }

    used = set()
    for p in (ROOT / 'resources/js').rglob('*.vue'):
        s = p.read_text(encoding='utf-8')
        used |= set(re.findall(r"\$t\(\s*['\"](\w+)['\"]", s))
    used &= set(uz)

    def gtx(q, sl, tl, retries=4):
        cache = api.setdefault('gtx', {})
        key = f'{sl}|{tl}|{q}'
        if key in cache:
            return cache[key]
        url = 'https://translate.googleapis.com/translate_a/single?' + urllib.parse.urlencode({
            'client': 'gtx', 'sl': sl, 'tl': tl, 'dt': 't', 'q': q[:450],
        })
        last = None
        for attempt in range(retries):
            try:
                req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
                with urllib.request.urlopen(req, timeout=25) as r:
                    data = json.load(r)
                out = ''.join(part[0] for part in data[0] if part and part[0])
                cache[key] = out
                return out
            except Exception as e:
                last = e
                time.sleep(0.8 * (attempt + 1) + random.random())
        raise last

    need = [k for k in sorted(used) if is_latin_heavy(zhj.get(k, uz[k]))]
    print(f'need={len(need)} batch={BATCH}')
    ok = 0
    for i, k in enumerate(need[:BATCH]):
        src = ru.get(k, uz[k])
        sl = 'ru' if not is_latin_heavy(src) else 'uz'
        if sl == 'uz':
            src = uz[k]
        try:
            prot, slots = protect(src)
            out = restore(gtx(prot, sl, 'zh-CN'), slots)
            if out and re.search(r'[\u4e00-\u9fff]', out):
                zhj[k] = out
                ok += 1
        except Exception as e:
            print('stop', e)
            break
        if i % 20 == 0:
            print(f'  {i}/{min(BATCH, len(need))} ok={ok}')
        time.sleep(0.15)

    (ROOT / 'scripts/i18n-zh.json').write_text(
        json.dumps(zhj, ensure_ascii=False, indent=2, sort_keys=True) + '\n', encoding='utf-8'
    )
    cache_path.write_text(json.dumps(api, ensure_ascii=False), encoding='utf-8')
    left = sum(1 for k in used if is_latin_heavy(zhj.get(k, uz[k])))
    print(f'filled={ok} used_latin_zh_left={left}')


if __name__ == '__main__':
    main()
