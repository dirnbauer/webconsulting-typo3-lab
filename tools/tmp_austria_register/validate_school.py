#!/usr/bin/env python3
from __future__ import annotations

import json
import re
import urllib.parse
from pathlib import Path

import requests
from bs4 import BeautifulSoup, Tag

OUT = Path("validation-out")
OUT.mkdir(exist_ok=True)
URL = "https://www.schulen-online.at/sol/oeff_suche_schulen.jsf"

s = requests.Session()
s.headers.update({
    "User-Agent": "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/126 Safari/537.36",
    "Accept-Language": "de-AT,de;q=0.9,en;q=0.5",
})

r = s.get(URL, timeout=60)
r.raise_for_status()
(OUT / "01-form.html").write_text(r.text, encoding="utf-8")
soup = BeautifulSoup(r.text, "lxml")
form = soup.find("form", id="myform1")
assert isinstance(form, Tag), "myform1 missing"
view = form.find("input", attrs={"name": "javax.faces.ViewState"})
assert isinstance(view, Tag), "view state missing"

data = {
    "myform1:skz": "",
    "myform1:bez": "",
    "myform1:schulart": "UNDEFINED",
    "myform1:art": "",
    "myform1:plz": "",
    "myform1:ort": "",
    "myform1:strasse": "",
    "myform1:bundesland": "-1",
    "myform1:bezirke": "-1",
    "myform1:sort": "0",
    "myform1:anz": "50",
    "myform1:j_id_1x": "Suchen",
    "myform1_SUBMIT": "1",
    "javax.faces.ViewState": str(view.get("value", "")),
}
action = urllib.parse.urljoin(URL, str(form.get("action") or URL))
r = s.post(action, data=data, headers={"Referer": URL}, timeout=90)
r.raise_for_status()
(OUT / "02-results.html").write_text(r.text, encoding="utf-8")n = BeautifulSoup(r.text, "lxml")
items = n.select("div.skz")
info: dict[str, object] = {
    "status": r.status_code,
    "url": r.url,
    "cookies": list(s.cookies.get_dict()),
    "skz_count": len(items),
    "forms": [(f.get("id"), f.get("name"), f.get("action")) for f in n.find_all("form")],
    "first_items": [],
}
for div in items[:5]:
    a = div.find("a")
    info["first_items"].append({
        "text": " ".join(div.stripped_strings),
        "onclick": a.get("onclick") if isinstance(a, Tag) else "",
        "href": a.get("href") if isinstance(a, Tag) else "",
        "parent_text": " | ".join(list(div.parent.stripped_strings)[:10]) if isinstance(div.parent, Tag) else "",
    })

if items:
    a = items[0].find("a")
    assert isinstance(a, Tag)
    onclick = str(a.get("onclick") or "")
    quoted = re.findall(r"['\"]([^'\"]+)['\"]", onclick)
    target = quoted[1] if len(quoted) >= 2 else ""
    form_id = quoted[0] if len(quoted) >= 2 else "j_id_20"
    view = n.find("input", attrs={"name": "javax.faces.ViewState"})
    detail_data = {
        f"{form_id}_SUBMIT": "1",
        "javax.faces.ViewState": str(view.get("value", "")) if isinstance(view, Tag) else "",
        f"{form_id}:_idcl": target,
    }
    detail = s.post(URL, data=detail_data, headers={"Referer": r.url}, timeout=90)
    detail.raise_for_status()
    (OUT / "03-detail.html").write_text(detail.text, encoding="utf-8")
    ds = BeautifulSoup(detail.text, "lxml")
    info["detail"] = {
        "target": target,
        "form_id": form_id,
        "anzeigefeld_count": len(ds.select("div.anzeigefeld")),
        "boxes": [list(box.stripped_strings) for box in ds.select("div.anzeigefeld")],
        "links": [(" ".join(a.stripped_strings), a.get("href")) for a in ds.find_all("a", href=True)],
    }

(OUT / "summary.json").write_text(json.dumps(info, ensure_ascii=False, indent=2), encoding="utf-8")
print(json.dumps(info, ensure_ascii=False, indent=2))
