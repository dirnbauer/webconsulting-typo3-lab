"""Bring rich text of the Netresearch talk payload into the form TYPO3's RTE transformation stores.

Observed on lab-nr (TYPO3 14.3, first seed 2026-11-21): a rich text value that contains <br> is stored
with every <br> written as <br /> and with CRLF between its top-level block elements. Values without
<br> are stored unchanged. Applying the same rule to the payload makes the second seed run report 0
updates, and applying it to the matching de/zh/hu fragment values keeps memory key == stored English
text and translation == stored translation.

Usage:
  python3 -I packages/site_package/Build/Scripts/netresearch-talk-rte-form.py <worktree> --check <stored.jsonl>   # prove the rule on stored values
  python3 -I packages/site_package/Build/Scripts/netresearch-talk-rte-form.py <worktree> --apply                  # rewrite payload + fragments
"""
import json
import re
import sys
from pathlib import Path

BLOCK = re.compile(r'<(/?)(p|h[1-6]|ul|ol|li|blockquote|table|thead|tbody|tr|td|th|div|figure|pre)\b[^>]*>', re.I)
TOP_LEVEL_BLOCKS = {'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'blockquote', 'table', 'div', 'figure', 'pre'}


def stored_form(value: str) -> str:
    if not re.search(r'<br\s*/?>', value):
        return value
    value = re.sub(r'<br\s*/?>', '<br />', value)
    # CRLF between top-level blocks: after a closing top-level tag at depth 0 followed directly by an opening tag.
    out = []
    depth = 0
    pos = 0
    for m in BLOCK.finditer(value):
        closing, tag = m.group(1) == '/', m.group(2).lower()
        if tag not in TOP_LEVEL_BLOCKS:
            continue
        if not closing:
            if depth == 0 and m.start() > 0 and value[pos:m.start()] == '' and out and out[-1].endswith('>'):
                out.append('\r\n')
            depth += 1
        else:
            depth -= 1
        out.append(value[pos:m.end()])
        pos = m.end()
    out.append(value[pos:])
    return ''.join(out)


def rich_fields(record: dict) -> list:
    return [k for k, v in record.get('set', {}).items() if isinstance(v, str) and '<' in v and '>' in v]


def main() -> None:
    wt = Path(sys.argv[1])
    base = wt / 'packages/site_package/Resources/Private/Data'
    payload_files = sorted((base / 'Content/netresearch-talk').glob('*.payload.json'))
    if sys.argv[2] == '--check':
        stored = [json.loads(line) for line in open(sys.argv[3])]
        values = set()
        for f in payload_files:
            for r in json.loads(f.read_text())['records']:
                for k in rich_fields(r):
                    values.add(stored_form(r['set'][k]))
        bad = [s['uid'] for s in stored if s['value'] not in values]
        print('stored values reproduced:', len(stored) - len(bad), 'of', len(stored), 'missing:', bad)
        return
    changed = {}
    for f in payload_files:
        raw = f.read_text()
        data = json.loads(raw)
        n = 0
        for r in data['records']:
            for k in rich_fields(r):
                new = stored_form(r['set'][k])
                if new != r['set'][k]:
                    changed[r['set'][k]] = new
                    r['set'][k] = new
                    n += 1
        if n:
            indent = 2 if raw.startswith('{\n  "') else 1
            f.write_text(json.dumps(data, ensure_ascii=False, indent=indent) + '\n')
            print(f.name, n, 'field(s)')
    # Fragment keys carry t3://page?uid=N instead of {{page:...}}; compare with links normalised.
    link = re.compile(r'href="(\{\{page:[^}]*\}\}|t3://page\?uid=\d+)"')
    def norm(s: str) -> str:
        return link.sub('href="L"', s)
    wanted = {norm(old): (old, new) for old, new in changed.items()}
    for lang in ('de', 'zh', 'hu'):
        f = base / f'Translations/desiderio/{lang}.d/netresearch-talk.json'
        raw = f.read_text()
        doc = json.loads(raw)
        strings = {}
        n = 0
        for key, value in doc['strings'].items():
            if norm(key) in wanted:
                strings[stored_form(key)] = stored_form(value)
                n += 1
            else:
                strings[key] = value
        doc['strings'] = strings
        indent = 1 if raw.startswith('{\n "') else 2
        f.write_text(json.dumps(doc, ensure_ascii=False, indent=indent) + '\n')
        print(lang, n, 'key(s) of', len(wanted))


main()
