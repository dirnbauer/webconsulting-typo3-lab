#!/usr/bin/env python3
"""Build a versioned register of Austrian public-sector organisations and websites.

Sources used by this first importer:
- Statistik Austria WFS: all municipalities and official municipality codes
- oesterreich.gv.at Behördensuche: authorities/institutions and their homepages
- Schulen-Online: schools, school codes, operators, supervisory bodies and homepages

The script deliberately stores organisations and websites separately. A school or a
municipality may have no own domain, several domains, or only a profile below a parent
portal. Every row therefore carries source and verification metadata.
"""
from __future__ import annotations

import csv
import hashlib
import html
import io
import json
import re
import sqlite3
import sys
import time
import unicodedata
import urllib.parse
import zipfile
from collections import Counter, defaultdict
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Iterable

import requests
from bs4 import BeautifulSoup, Tag

OUT = Path("out")
OUT.mkdir(parents=True, exist_ok=True)
RAW = OUT / "raw"
RAW.mkdir(parents=True, exist_ok=True)

NOW = datetime.now(timezone.utc).replace(microsecond=0).isoformat()
UA = (
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/126.0 Safari/537.36 "
    "AustriaPublicWebsiteRegister/0.1"
)

FEDERAL_STATES = {
    "1": "Burgenland",
    "2": "Kärnten",
    "3": "Niederösterreich",
    "4": "Oberösterreich",
    "5": "Salzburg",
    "6": "Steiermark",
    "7": "Tirol",
    "8": "Vorarlberg",
    "9": "Wien",
}

SOURCE_URLS = {
    "municipalities": "https://data.statistik.gv.at/web/meta.jsp?dataset=OGDEXT_GEM_1",
    "municipalities_wfs": (
        "https://www.statistik.at/gs-open/GEODATA/ows?service=WFS&version=1.0.0"
        "&request=GetFeature&typeName=GEODATA:STATISTIK_AUSTRIA_GEM_20260101"
        "&outputFormat=application/json"
    ),
    "authorities": "https://www.oesterreich.gv.at/de/orgsearch",
    "schools": "https://www.schulen-online.at/sol/oeff_suche_schulen.jsf",
}

session = requests.Session()
session.headers.update({"User-Agent": UA, "Accept-Language": "de-AT,de;q=0.9,en;q=0.5"})


def log(message: str) -> None:
    print(f"[{datetime.now().strftime('%H:%M:%S')}] {message}", flush=True)


def clean(value: Any) -> str:
    if value is None:
        return ""
    value = html.unescape(str(value)).replace("\u00a0", " ")
    return re.sub(r"\s+", " ", value).strip()


def normalize_name(value: str) -> str:
    value = clean(value).casefold()
    value = unicodedata.normalize("NFKD", value)
    value = "".join(c for c in value if not unicodedata.combining(c))
    value = value.replace("st.", "sankt").replace("st ", "sankt ")
    value = value.replace("gemeinde", " ").replace("stadt", " ").replace("markt", " ")
    value = re.sub(r"[^a-z0-9]+", " ", value)
    return re.sub(r"\s+", " ", value).strip()


def stable_id(prefix: str, *parts: str) -> str:
    raw = "|".join(clean(p).casefold() for p in parts)
    return f"{prefix}-{hashlib.sha1(raw.encode('utf-8')).hexdigest()[:12].upper()}"


def canonical_url(url: str, base: str = "") -> str:
    url = clean(url).strip(".,;()[]<>\"'")
    if not url:
        return ""
    if base:
        url = urllib.parse.urljoin(base, url)
    if url.startswith("www."):
        url = "https://" + url
    parsed = urllib.parse.urlsplit(url)
    if parsed.scheme not in {"http", "https"} or not parsed.netloc:
        return ""
    host = parsed.netloc.lower().split("@")[(-1)]
    if host.endswith(":80") and parsed.scheme == "http":
        host = host[:-3]
    if host.endswith(":443") and parsed.scheme == "https":
        host = host[:-4]
    path = re.sub(r"/{2,}", "/", parsed.path or "/")
    return urllib.parse.urlunsplit((parsed.scheme.lower(), host, path, parsed.query, ""))


def domain_of(url: str) -> str:
    try:
        return urllib.parse.urlsplit(url).netloc.lower().removeprefix("www.")
    except Exception:
        return ""


def request(method: str, url: str, *, attempts: int = 4, **kwargs: Any) -> requests.Response:
    last: Exception | None = None
    for attempt in range(1, attempts + 1):
        try:
            response = session.request(method, url, timeout=60, **kwargs)
            if response.status_code in {429, 500, 502, 503, 504} and attempt < attempts:
                time.sleep(attempt * 2)
                continue
            response.raise_for_status()
            return response
        except Exception as exc:  # noqa: BLE001
            last = exc
            if attempt < attempts:
                time.sleep(attempt * 2)
    raise RuntimeError(f"Request failed after {attempts} attempts: {url}: {last}")


def write_csv(path: Path, rows: list[dict[str, Any]], fieldnames: list[str] | None = None) -> None:
    if fieldnames is None:
        fieldnames = []
        seen: set[str] = set()
        for row in rows:
            for key in row:
                if key not in seen:
                    seen.add(key)
                    fieldnames.append(key)
    with path.open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(rows)


def municipality_rows() -> list[dict[str, Any]]:
    """Fetch all current municipalities from Statistik Austria."""
    url = SOURCE_URLS["municipalities_wfs"]
    log("Fetching municipalities from Statistik Austria WFS")
    response = request("GET", url, headers={"Accept": "application/json"})
    (RAW / "municipalities-response.bin").write_bytes(response.content)
    rows: list[dict[str, Any]] = []

    try:
        data = response.json()
        features = data.get("features", [])
        for feature in features:
            props = feature.get("properties") or {}
            code = clean(
                props.get("ID")
                or props.get("id")
                or props.get("GEMNR")
                or props.get("g_id")
                or props.get("GKZ")
            )
            name = clean(props.get("NAME") or props.get("name") or props.get("GEMNAME"))
            code = re.sub(r"\.0$", "", code).zfill(5)
            if code and name:
                rows.append(
                    {
                        "organization_id": f"AT-GEM-{code}",
                        "official_id": code,
                        "name": name,
                        "organization_type": "municipality",
                        "organization_type_label": "Gemeinde",
                        "administrative_level": "municipal",
                        "federal_state": FEDERAL_STATES.get(code[:1], ""),
                        "district": "",
                        "municipality_code": code,
                        "school_code": "",
                        "parent_organization_id": "",
                        "ownership_control": "public",
                        "legal_form": "Gebietskörperschaft",
                        "active": True,
                        "source_id": "SRC-STATAT-GEM-2026",
                        "source_url": SOURCE_URLS["municipalities"],
                        "source_record_url": url,
                        "source_as_of": "2026-01-01",
                        "retrieved_at": NOW,
                        "notes": "",
                    }
                )
    except Exception as exc:  # noqa: BLE001
        log(f"GeoJSON parsing failed ({exc}); trying SHAPE-ZIP fallback")

    if not rows:
        # GeoServer fallback. pyshp is installed by the workflow.
        import shapefile  # type: ignore

        shape_url = url.replace("application/json", "SHAPE-ZIP") + "&format_options=CHARSET:UTF-8"
        response = request("GET", shape_url)
        archive = zipfile.ZipFile(io.BytesIO(response.content))
        extract_dir = RAW / "municipalities-shape"
        extract_dir.mkdir(exist_ok=True)
        archive.extractall(extract_dir)
        shp = next(extract_dir.glob("*.shp"))
        reader = shapefile.Reader(str(shp), encoding="utf-8")
        fields = [f[0] for f in reader.fields[1:]]
        for record in reader.iterRecords():
            props = dict(zip(fields, record, strict=False))
            code = clean(props.get("ID") or props.get("id") or props.get("GKZ")).zfill(5)
            name = clean(props.get("NAME") or props.get("name"))
            if code and name:
                rows.append(
                    {
                        "organization_id": f"AT-GEM-{code}",
                        "official_id": code,
                        "name": name,
                        "organization_type": "municipality",
                        "organization_type_label": "Gemeinde",
                        "administrative_level": "municipal",
                        "federal_state": FEDERAL_STATES.get(code[:1], ""),
                        "district": "",
                        "municipality_code": code,
                        "school_code": "",
                        "parent_organization_id": "",
                        "ownership_control": "public",
                        "legal_form": "Gebietskörperschaft",
                        "active": True,
                        "source_id": "SRC-STATAT-GEM-2026",
                        "source_url": SOURCE_URLS["municipalities"],
                        "source_record_url": shape_url,
                        "source_as_of": "2026-01-01",
                        "retrieved_at": NOW,
                        "notes": "",
                    }
                )

    unique = {row["municipality_code"]: row for row in rows}
    rows = sorted(unique.values(), key=lambda row: row["municipality_code"])
    log(f"Municipalities fetched: {len(rows)}")
    if len(rows) < 2000:
        raise RuntimeError(f"Municipality source returned only {len(rows)} rows")
    return rows


def section_siblings(heading: Tag) -> list[Tag]:
    nodes: list[Tag] = []
    for sibling in heading.next_siblings:
        if isinstance(sibling, Tag) and sibling.name == "h2":
            break
        if isinstance(sibling, Tag):
            nodes.append(sibling)
    return nodes


def text_and_links(nodes: Iterable[Tag], base_url: str) -> tuple[str, list[str]]:
    texts: list[str] = []
    links: list[str] = []
    for node in nodes:
        texts.extend(clean(s) for s in node.stripped_strings if clean(s))
        for anchor in node.find_all("a", href=True):
            url = canonical_url(anchor.get("href", ""), base_url)
            if url:
                links.append(url)
    text = "\n".join(texts)
    for match in re.findall(r"https?://[^\s<>\"]+", text):
        url = canonical_url(match)
        if url:
            links.append(url)
    return text, list(dict.fromkeys(links))


def parse_address_from_text(text: str) -> tuple[str, str, str]:
    lines = [clean(line) for line in text.splitlines() if clean(line)]
    street = ""
    postal_code = ""
    locality = ""
    for idx, line in enumerate(lines):
        match = re.match(r"^(\d{4})\s+(.+)$", line)
        if match:
            postal_code = match.group(1)
            locality = match.group(2)
            if idx:
                candidate = lines[idx - 1]
                if not re.match(r"^(Telefon|Fax|E-Mail|Homepage|Auf Karte|Routenplaner)", candidate, re.I):
                    street = candidate
            break
    return street, postal_code, locality


def classify_org_type(label: str, name: str) -> tuple[str, str, str]:
    joined = f"{label} {name}".casefold()
    if "bundesministerium" in joined or "bundeskanzleramt" in joined:
        return "ministry", "federal", "public"
    if "gemeinde" in joined or "magistrat" in joined:
        return "municipality_authority", "municipal", "public"
    if "bezirkshaupt" in joined:
        return "district_authority", "district", "public"
    if "bildungsdirektion" in joined:
        return "education_authority", "state", "public"
    if "landesregierung" in joined or "amt der" in joined and "landesregierung" in name.casefold():
        return "state_authority", "state", "public"
    if "gericht" in joined or "staatsanwaltschaft" in joined:
        return "judiciary", "federal", "public"
    if "polizei" in joined:
        return "police_authority", "federal", "public"
    if "universität" in joined or "hochschule" in joined:
        return "higher_education", "federal", "public_or_private"
    if "kammer" in joined:
        return "chamber", "statutory", "public_law"
    if "versicherung" in joined or "krankenkasse" in joined:
        return "social_insurance", "statutory", "public_law"
    return "public_body", "unknown", "public_or_attached"


def authority_rows() -> tuple[list[dict[str, Any]], list[dict[str, Any]], dict[str, str]]:
    """Crawl all authority-type result pages exposed by oesterreich.gv.at."""
    base = SOURCE_URLS["authorities"]
    log("Discovering authority types on oesterreich.gv.at")
    response = request("GET", base)
    (RAW / "oesterreich-orgsearch.html").write_text(response.text, encoding="utf-8")
    soup = BeautifulSoup(response.text, "lxml")

    type_map: dict[str, str] = {}
    for option in soup.find_all("option"):
        value = clean(option.get("value"))
        label = clean(option.get_text(" ", strip=True))
        match = re.search(r"(?:orgtyp/)?(\d+)$", value)
        if match and label:
            type_map[match.group(1)] = label

    if not type_map:
        # The public form is sometimes hydrated client-side. Probe a bounded range;
        # empty/error pages are skipped and the raw responses are retained.
        type_ids = [str(i) for i in range(1, 81)]
    else:
        type_ids = sorted(type_map, key=lambda x: int(x))
    log(f"Authority type candidates: {len(type_ids)}")

    organisations: list[dict[str, Any]] = []
    websites: list[dict[str, Any]] = []
    consecutive_empty = 0

    for type_id in type_ids:
        url = f"https://www.oesterreich.gv.at/de/orgsearch/orgtyp/{type_id}"
        try:
            response = request("GET", url, attempts=2)
        except Exception as exc:  # noqa: BLE001
            log(f"  type {type_id}: request failed: {exc}")
            continue
        soup = BeautifulSoup(response.text, "lxml")
        headings = [
            h for h in soup.find_all("h2")
            if clean(h.get_text(" ", strip=True)).casefold()
            not in {"behörden- / personenverzeichnisse", "behörden-/personenverzeichnisse"}
        ]
        # Result pages have h2 entries. Exclude generic headings.
        headings = [
            h for h in headings
            if clean(h.get_text(" ", strip=True)).casefold()
            not in {"abfrageergebnis", "information", "behördensuche"}
        ]
        if not headings:
            consecutive_empty += 1
            if not type_map and consecutive_empty >= 25 and int(type_id) > 30:
                break
            continue
        consecutive_empty = 0
        label = type_map.get(type_id, f"Behördentyp {type_id}")
        (RAW / f"oesterreich-orgtyp-{type_id}.html").write_text(response.text, encoding="utf-8")
        log(f"  type {type_id} ({label}): {len(headings)} entries")

        for heading in headings:
            name = clean(heading.get_text(" ", strip=True))
            nodes = section_siblings(heading)
            text, links = text_and_links(nodes, url)
            street, postal_code, locality = parse_address_from_text(text)
            emails = sorted(set(re.findall(r"[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}", text, re.I)))
            phones = []
            for line in text.splitlines():
                if re.match(r"^(Telefon|Tel\.|T:?)", line, re.I):
                    phones.append(clean(re.sub(r"^(Telefon|Tel\.|T:?)\s*: ?", "", line, flags=re.I)))
            external_links: list[str] = []
            for link in links:
                host = domain_of(link)
                if not host:
                    continue
                if host in {"maps.google.com", "google.com", "route.bmk.gv.at", "www.oesterreich.gv.at", "oesterreich.gv.at"}:
                    continue
                if host.endswith("google.com"):
                    continue
                external_links.append(link)
            external_links = list(dict.fromkeys(external_links))

            org_type, level, ownership = classify_org_type(label, name)
            org_id = stable_id("AT-ORG", type_id, name, street, postal_code, locality)
            organisations.append(
                {
                    "organization_id": org_id,
                    "official_id": "",
                    "name": name,
                    "organization_type": org_type,
                    "organization_type_label": label,
                    "administrative_level": level,
                    "federal_state": "",
                    "district": "",
                    "municipality_code": "",
                    "school_code": "",
                    "parent_organization_id": "",
                    "ownership_control": ownership,
                    "legal_form": "",
                    "active": True,
                    "street_address": street,
                    "postal_code": postal_code,
                    "locality": locality,
                    "email": "; ".join(emails),
                    "phone": "; ".join(p for p in phones if p),
                    "source_id": "SRC-OEGV-ORGSEARCH",
                    "source_url": base,
                    "source_record_url": url,
                    "source_as_of": "",
                    "retrieved_at": NOW,
                    "notes": f"Behördentyp-ID {type_id}",
                }
            )
            for index, link in enumerate(external_links, start=1):
                websites.append(
                    {
                        "website_id": stable_id("AT-WEB", org_id, link),
                        "organization_id": org_id,
                        "url": link,
                        "domain": domain_of(link),
                        "presence_type": "own_domain" if "/" == urllib.parse.urlsplit(link).path else "website_or_subpage",
                        "is_primary": index == 1,
                        "verification_status": "listed_by_official_directory",
                        "http_status": "",
                        "redirect_target": "",
                        "last_checked": NOW,
                        "source_id": "SRC-OEGV-ORGSEARCH",
                        "source_url": url,
                        "notes": "",
                    }
                )
        time.sleep(0.05)

    # Deduplicate exact organisation entries across overlapping types.
    deduped: dict[tuple[str, str, str], dict[str, Any]] = {}
    id_alias: dict[str, str] = {}
    for row in organisations:
        key = (normalize_name(row["name"]), row.get("postal_code", ""), normalize_name(row.get("street_address", "")))
        if key not in deduped:
            deduped[key] = row
        else:
            id_alias[row["organization_id"]] = deduped[key]["organization_id"]
            labels = set(filter(None, [deduped[key].get("organization_type_label", ""), row.get("organization_type_label", "")]))
            deduped[key]["organization_type_label"] = "; ".join(sorted(labels))
    for web in websites:
        web["organization_id"] = id_alias.get(web["organization_id"], web["organization_id"])
    web_dedup = {(w["organization_id"], w["url"]): w for w in websites}
    organisations = sorted(deduped.values(), key=lambda r: (r["organization_type"], r["name"]))
    websites = sorted(web_dedup.values(), key=lambda r: (r["organization_id"], not r["is_primary"], r["url"]))
    log(f"Authority organisations parsed: {len(organisations)}; websites: {len(websites)}")
    return organisations, websites, type_map


def form_hidden_values(form: Tag) -> dict[str, str]:
    values: dict[str, str] = {}
    for input_tag in form.find_all("input", attrs={"name": True}):
        input_type = clean(input_tag.get("type")).lower()
        if input_type in {"submit", "button", "image", "file"}:
            continue
        name = clean(input_tag.get("name"))
        if input_type in {"checkbox", "radio"} and not input_tag.has_attr("checked"):
            continue
        values[name] = clean(input_tag.get("value"))
    return values


def choose_all_value(select: Tag) -> str:
    options = select.find_all("option")
    for option in options:
        label = clean(option.get_text(" ", strip=True)).casefold()
        if "alle" in label or label in {"", "< alle >"}:
            return clean(option.get("value"))
    return clean(options[0].get("value")) if options else ""


def school_search_post(session_local: requests.Session, search_url: str) -> requests.Response:
    response = session_local.get(search_url, timeout=60)
    response.raise_for_status()
    (RAW / "schools-search-form.html").write_text(response.text, encoding="utf-8")
    soup = BeautifulSoup(response.text, "lxml")
    form = soup.find("form")
    if not isinstance(form, Tag):
        raise RuntimeError("No school search form found")
    data = form_hidden_values(form)
    form_id = clean(form.get("id") or form.get("name") or "myform1")

    for input_tag in form.find_all("input", attrs={"name": True}):
        name = clean(input_tag.get("name"))
        input_type = clean(input_tag.get("type")).lower()
        if input_type in {"text", "search"}:
            data[name] = ""
    for select in form.find_all("select", attrs={"name": True}):
        name = clean(select.get("name"))
        value = choose_all_value(select)
        lname = name.casefold()
        if lname.endswith(":anz") or lname.endswith("anz"):
            available = [clean(o.get("value")) for o in select.find_all("option")]
            value = "50" if "50" in available else (available[-1] if available else value)
        elif lname.endswith(":sort") or lname.endswith("sort"):
            value = "0"
        data[name] = value

    submit = form.find(["input", "button"], attrs={"name": True}, string=re.compile("Suchen", re.I))
    if not submit:
        submit = form.find("input", attrs={"type": "submit", "name": True})
    if isinstance(submit, Tag):
        data[clean(submit.get("name"))] = clean(submit.get("value") or submit.get_text(" ", strip=True) or "Suchen")
    data.setdefault(f"{form_id}_SUBMIT", "1")

    # Known current/legacy MyFaces field values; only add them if matching names exist
    # or if the form uses the historical myform1 id.
    known = {
        f"{form_id}:skz": "",
        f"{form_id}:bez": "",
        f"{form_id}:schulart": "UNDEFINED",
        f"{form_id}:art": "",
        f"{form_id}:plz": "",
        f"{form_id}:ort": "",
        f"{form_id}:strasse": "",
        f"{form_id}:bundesland": "-1",
        f"{form_id}:bezirke": "-1",
        f"{form_id}:sort": "0",
        f"{form_id}:anz": "50",
    }
    existing_names = {clean(tag.get("name")) for tag in form.find_all(attrs={"name": True})}
    for key, value in known.items():
        if key in existing_names or form_id == "myform1":
            data[key] = value

    action = urllib.parse.urljoin(search_url, clean(form.get("action")) or search_url)
    result = session_local.post(action, data=data, timeout=90)
    result.raise_for_status()
    (RAW / "schools-search-result-first.html").write_text(result.text, encoding="utf-8")
    return result


def extract_view_state(soup: BeautifulSoup) -> tuple[str, str]:
    view = soup.find("input", attrs={"name": "javax.faces.ViewState"})
    return ("javax.faces.ViewState", clean(view.get("value"))) if isinstance(view, Tag) else ("", "")


def extract_jsf_target(anchor: Tag) -> tuple[str, str]:
    onclick = clean(anchor.get("onclick"))
    # MyFaces commonly emits submitForm('form','component') or
    # oamSubmitForm('form','component'). Capture the first two quoted args.
    quoted = re.findall(r"['\"]([^'\"]+)['\"]", onclick)
    if len(quoted) >= 2:
        return quoted[0], quoted[1]
    href = clean(anchor.get("href"))
    match = re.search(r"_idcl=([^&'\"]+)", href)
    if match:
        form = clean(anchor.find_parent("form").get("id")) if anchor.find_parent("form") else "j_id_20"
        return form, urllib.parse.unquote(match.group(1))
    return "", ""


def school_result_items(soup: BeautifulSoup) -> list[dict[str, str]]:
    items: list[dict[str, str]] = []
    for div in soup.select("div.skz"):
        anchor = div.find("a")
        if not isinstance(anchor, Tag):
            continue
        skz = clean(anchor.get_text(" ", strip=True) or div.get_text(" ", strip=True))
        form_id, target = extract_jsf_target(anchor)
        parent = div.parent if isinstance(div.parent, Tag) else div
        title_node = parent.select_one(".titel, .title, .bez, .schulbez")
        address_node = parent.select_one(".adresse, .address")
        all_text = [clean(x) for x in parent.stripped_strings if clean(x)]
        title = clean(title_node.get_text(" ", strip=True)) if title_node else ""
        address = clean(address_node.get_text(" ", strip=True)) if address_node else ""
        # Fall back to the text immediately following the school code.
        if not title:
            rest = [x for x in all_text if x != skz and x.casefold() not in {"detail", "details"}]
            title = rest[0] if rest else ""
            address = rest[1] if len(rest) > 1 else address
        items.append({"school_code": skz, "title": title, "address": address, "form_id": form_id, "target": target})
    return items


def pairs_from_box(box: Tag, selector: str) -> list[str]:
    container = box.select_one(selector)
    if not isinstance(container, Tag):
        return []
    direct = [clean(child.get_text(" ", strip=True)) for child in container.find_all(recursive=False) if clean(child.get_text(" ", strip=True))]
    if len(direct) >= 2:
        return direct
    return [clean(x) for x in container.stripped_strings if clean(x)]


def parse_school_detail(soup: BeautifulSoup, fallback: dict[str, str]) -> dict[str, str]:
    row = {
        "school_code": fallback.get("school_code", ""),
        "name": fallback.get("title", ""),
        "address": fallback.get("address", ""),
        "public_private": "",
        "school_operator": "",
        "supervisory_authority": "",
        "school_types": "",
        "day_care": "",
        "phone": "",
        "fax": "",
        "email_administration": "",
        "email_pedagogy": "",
        "homepage": "",
    }
    boxes = soup.select("div.anzeigefeld")
    start = 1 if len(boxes) >= 3 else 0
    if len(boxes) >= start + 2:
        basic = boxes[start]
        contact = boxes[start + 1]
        left = pairs_from_box(basic, ".anzeigefeld_links")
        right = pairs_from_box(basic, ".anzeigefeld_rechts")
        cleft = pairs_from_box(contact, ".anzeigefeld_links")
        cright = pairs_from_box(contact, ".anzeigefeld_rechts")
        # Historical page markup alternates label/value elements.
        values_left = left[1::2] if len(left) >= 6 else left
        values_right = right[1::2] if len(right) >= 8 else right
        values_cleft = cleft[1::2] if len(cleft) >= 4 else cleft
        values_cright = cright[1::2] if len(cright) >= 4 else cright
        if len(values_left) >= 3:
            row["school_code"], row["name"], row["address"] = values_left[:3]
        if len(values_right) >= 1:
            row["public_private"] = values_right[0]
        if len(values_right) >= 2:
            row["school_operator"] = values_right[1]
        if len(values_right) >= 3:
            row["supervisory_authority"] = values_right[2]
        if len(values_right) >= 4:
            row["school_types"] = values_right[3]
        if len(values_right) >= 5:
            row["day_care"] = values_right[4]
        if len(values_cleft) >= 1:
            row["phone"] = values_cleft[0]
        if len(values_cleft) >= 2:
            row["fax"] = values_cleft[1]
        if len(values_cright) >= 1:
            row["email_administration"] = values_cright[0]
        if len(values_cright) >= 2:
            row["email_pedagogy"] = values_cright[1]
        if len(values_cright) >= 3:
            row["homepage"] = values_cright[2]

    full_text = "\n".join(clean(x) for x in soup.stripped_strings if clean(x))
    known_labels = {
        "Schulkennzahl": "school_code",
        "Titel": "name",
        "Adresse": "address",
        "öffentlich/privat": "public_private",
        "Schulerhalter": "school_operator",
        "Schulaufsichtsbehörde": "supervisory_authority",
        "Schulart(en)": "school_types",
        "Schulische Tagesbetreuung": "day_care",
        "Telefon": "phone",
        "Fax": "fax",
        "E-Mail Verwaltung": "email_administration",
        "E-Mail Pädagogik": "email_pedagogy",
        "Homepage": "homepage",
    }
    # Generic label/value fallback by neighbouring elements.
    for label, key in known_labels.items():
        if row.get(key):
            continue
        node = soup.find(string=lambda s: clean(s) == label)
        if node:
            parent = node.parent if isinstance(node.parent, Tag) else None
            candidate = parent.find_next() if parent else None
            for _ in range(4):
                if not isinstance(candidate, Tag):
                    break
                value = clean(candidate.get_text(" ", strip=True))
                if value and value != label and value not in known_labels:
                    row[key] = value
                    break
                candidate = candidate.find_next()

    # Explicitly prefer links following the Homepage label.
    home_label = soup.find(string=lambda s: clean(s) == "Homepage")
    if home_label:
        parent = home_label.parent if isinstance(home_label.parent, Tag) else None
        anchor = parent.find_next("a", href=True) if parent else None
        if isinstance(anchor, Tag):
            row["homepage"] = canonical_url(anchor.get("href", ""), SOURCE_URLS["schools"]) or clean(anchor.get_text(" ", strip=True))
    if not row["homepage"]:
        urls = re.findall(r"https?://[^\s<>\"]+", full_text)
        for url in urls:
            host = domain_of(url)
            if host and "schulen-online.at" not in host:
                row["homepage"] = canonical_url(url)
                break
    row["homepage"] = canonical_url(row["homepage"]) or clean(row["homepage"])
    return {key: clean(value) for key, value in row.items()}


def jsf_post(session_local: requests.Session, url: str, soup: BeautifulSoup, form_id: str, target: str) -> requests.Response:
    form = soup.find("form", id=form_id) or soup.find("form", attrs={"name": form_id}) or soup.find("form")
    data = form_hidden_values(form) if isinstance(form, Tag) else {}
    view_name, view_value = extract_view_state(soup)
    if view_name:
        data[view_name] = view_value
    form_id = form_id or (clean(form.get("id")) if isinstance(form, Tag) else "j_id_20") or "j_id_20"
    data[f"{form_id}_SUBMIT"] = "1"
    data[f"{form_id}:_idcl"] = target
    response = session_local.post(url, data=data, timeout=90)
    response.raise_for_status()
    return response


def school_rows() -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    """Scrape the national school directory, including each listed homepage."""
    search_url = SOURCE_URLS["schools"]
    log("Submitting Schulen-Online national search")
    local = requests.Session()
    local.headers.update(session.headers)
    response = school_search_post(local, search_url)
    page = 0
    seen_codes: set[str] = set()
    records: list[dict[str, Any]] = []
    websites: list[dict[str, Any]] = []

    while page < 250:
        page += 1
        result_soup = BeautifulSoup(response.text, "lxml")
        items = school_result_items(result_soup)
        if not items:
            log(f"School page {page}: no result items; stopping")
            break
        new_items = [item for item in items if item["school_code"] not in seen_codes]
        if not new_items:
            log(f"School page {page}: no new school codes; stopping")
            break
        log(f"School page {page}: {len(new_items)} schools")

        current_soup = result_soup
        for item_index, item in enumerate(new_items, start=1):
            detail = item.copy()
            try:
                if item["target"]:
                    detail_response = jsf_post(local, search_url, current_soup, item["form_id"] or "j_id_20", item["target"])
                    current_soup = BeautifulSoup(detail_response.text, "lxml")
                    detail = parse_school_detail(current_soup, item)
                else:
                    detail = parse_school_detail(result_soup, item)
            except Exception as exc:  # noqa: BLE001
                detail = parse_school_detail(result_soup, item)
                detail["scrape_error"] = clean(exc)

            code = re.sub(r"\D", "", detail.get("school_code", "") or item["school_code"])
            code = code or clean(item["school_code"])
            if code in seen_codes:
                continue
            seen_codes.add(code)
            address = detail.get("address", "")
            postal_match = re.search(r"\b(\d{4})\b", address)
            postal = postal_match.group(1) if postal_match else ""
            state = ""
            if code and code[0] in FEDERAL_STATES:
                state = FEDERAL_STATES[code[0]]
            public_private = detail.get("public_private", "")
            ownership = "private" if "privat" in public_private.casefold() else "public"
            org_id = f"AT-SCH-{code}" if code else stable_id("AT-SCH", detail.get("name", ""), address)
            records.append(
                {
                    "organization_id": org_id,
                    "official_id": code,
                    "name": detail.get("name", "") or item.get("title", ""),
                    "organization_type": "school",
                    "organization_type_label": detail.get("school_types", "") or "Schule",
                    "administrative_level": "education",
                    "federal_state": state,
                    "district": "",
                    "municipality_code": "",
                    "school_code": code,
                    "parent_organization_id": "",
                    "ownership_control": ownership,
                    "legal_form": "",
                    "street_address": address,
                    "postal_code": postal,
                    "locality": "",
                    "email": "; ".join(filter(None, [detail.get("email_administration", ""), detail.get("email_pedagogy", "")])),
                    "phone": detail.get("phone", ""),
                    "fax": detail.get("fax", ""),
                    "school_operator": detail.get("school_operator", ""),
                    "supervisory_authority": detail.get("supervisory_authority", ""),
                    "public_private": public_private,
                    "day_care": detail.get("day_care", ""),
                    "active": True,
                    "source_id": "SRC-BMB-SCHULENONLINE",
                    "source_url": search_url,
                    "source_record_url": search_url,
                    "source_as_of": "",
                    "retrieved_at": NOW,
                    "notes": detail.get("scrape_error", ""),
                }
            )
            homepage = canonical_url(detail.get("homepage", ""))
            if homepage:
                websites.append(
                    {
                        "website_id": stable_id("AT-WEB", org_id, homepage),
                        "organization_id": org_id,
                        "url": homepage,
                        "domain": domain_of(homepage),
                        "presence_type": "own_domain" if urllib.parse.urlsplit(homepage).path in {"", "/"} else "website_or_subpage",
                        "is_primary": True,
                        "verification_status": "listed_by_official_directory",
                        "http_status": "",
                        "redirect_target": "",
                        "last_checked": NOW,
                        "source_id": "SRC-BMB-SCHULENONLINE",
                        "source_url": search_url,
                        "notes": "",
                    }
                )
            if item_index % 10 == 0:
                time.sleep(0.03)

        # Persist progress so a partially successful run remains useful.
        write_csv(OUT / "schools-progress.csv", records)

        # Locate the forward link on the current stateful response first, then the
        # original result page. Its target is normally j_id_20:next.
        next_anchor: Tag | None = None
        for candidate_soup in (current_soup, result_soup):
            for anchor in candidate_soup.find_all("a"):
                label = clean(anchor.get_text(" ", strip=True)).casefold()
                aid = clean(anchor.get("id") or anchor.get("name")).casefold()
                if "vorwärts" in label or label in {"weiter", "next", ">"} or aid.endswith("next"):
                    next_anchor = anchor
                    current_soup = candidate_soup
                    break
            if next_anchor:
                break
        if not next_anchor:
            log("No school next-page link found; stopping")
            break
        next_form, next_target = extract_jsf_target(next_anchor)
        if not next_target:
            next_form = next_form or "j_id_20"
            next_target = f"{next_form}:next"
        try:
            response = jsf_post(local, search_url, current_soup, next_form or "j_id_20", next_target)
        except Exception as exc:  # noqa: BLE001
            log(f"School pagination failed after page {page}: {exc}")
            break

    log(f"Schools parsed: {len(records)}; school websites: {len(websites)}")
    return records, websites


def merge_municipality_websites(
    municipalities: list[dict[str, Any]],
    authorities: list[dict[str, Any]],
    websites: list[dict[str, Any]],
) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    """Attach authority-directory websites to municipality master rows by name."""
    municipality_by_name: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for row in municipalities:
        municipality_by_name[normalize_name(row["name"])].append(row)

    web_by_org: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for web in websites:
        web_by_org[web["organization_id"]].append(web)

    consumed_orgs: set[str] = set()
    new_websites: list[dict[str, Any]] = []
    for org in authorities:
        if org.get("organization_type") != "municipality_authority":
            continue
        key = normalize_name(org["name"])
        candidates = municipality_by_name.get(key, [])
        if len(candidates) != 1:
            # Try stripping common municipal-office prefixes/suffixes.
            reduced = re.sub(r"\b(stadtgemeinde|marktgemeinde|gemeindeamt|magistrat|gemeinde)\b", " ", org["name"], flags=re.I)
            candidates = municipality_by_name.get(normalize_name(reduced), [])
        if len(candidates) != 1:
            continue
        municipality = candidates[0]
        consumed_orgs.add(org["organization_id"])
        for field in ("street_address", "postal_code", "locality", "email", "phone"):
            if org.get(field) and not municipality.get(field):
                municipality[field] = org[field]
        municipality["notes"] = clean(f"{municipality.get('notes', '')}; Kontaktdaten/Website aus oesterreich.gv.at").strip("; ")
        for web in web_by_org.get(org["organization_id"], []):
            copy = dict(web)
            copy["organization_id"] = municipality["organization_id"]
            copy["website_id"] = stable_id("AT-WEB", copy["organization_id"], copy["url"])
            new_websites.append(copy)

    remaining_authorities = [row for row in authorities if row["organization_id"] not in consumed_orgs]
    return remaining_authorities, new_websites


def build_sqlite(
    organisations: list[dict[str, Any]],
    websites: list[dict[str, Any]],
    sources: list[dict[str, Any]],
) -> None:
    db = OUT / "austria-public-websites.sqlite"
    if db.exists():
        db.unlink()
    connection = sqlite3.connect(db)
    try:
        connection.execute(
            """
            CREATE TABLE organizations (
                organization_id TEXT PRIMARY KEY,
                official_id TEXT,
                name TEXT NOT NULL,
                organization_type TEXT,
                organization_type_label TEXT,
                administrative_level TEXT,
                federal_state TEXT,
                district TEXT,
                municipality_code TEXT,
                school_code TEXT,
                parent_organization_id TEXT,
                ownership_control TEXT,
                legal_form TEXT,
                active INTEGER,
                street_address TEXT,
                postal_code TEXT,
                locality TEXT,
                email TEXT,
                phone TEXT,
                fax TEXT,
                school_operator TEXT,
                supervisory_authority TEXT,
                public_private TEXT,
                day_care TEXT,
                source_id TEXT,
                source_url TEXT,
                source_record_url TEXT,
                source_as_of TEXT,
                retrieved_at TEXT,
                notes TEXT
            )
            """
        )
        connection.execute(
            """
            CREATE TABLE websites (
                website_id TEXT PRIMARY KEY,
                organization_id TEXT NOT NULL,
                url TEXT NOT NULL,
                domain TEXT,
                presence_type TEXT,
                is_primary INTEGER,
                verification_status TEXT,
                http_status TEXT,
                redirect_target TEXT,
                last_checked TEXT,
                source_id TEXT,
                source_url TEXT,
                notes TEXT,
                FOREIGN KEY (organization_id) REFERENCES organizations(organization_id)
            )
            """
        )
        connection.execute(
            """
            CREATE TABLE sources (
                source_id TEXT PRIMARY KEY,
                source_name TEXT,
                publisher TEXT,
                source_url TEXT,
                scope TEXT,
                as_of_date TEXT,
                retrieved_at TEXT,
                license TEXT,
                notes TEXT
            )
            """
        )
        org_columns = [row[1] for row in connection.execute("PRAGMA table_info(organizations)")]
        web_columns = [row[1] for row in connection.execute("PRAGMA table_info(websites)")]
        src_columns = [row[1] for row in connection.execute("PRAGMA table_info(sources)")]
        connection.executemany(
            f"INSERT INTO organizations ({','.join(org_columns)}) VALUES ({','.join('?' for _ in org_columns)})",
            [[int(bool(row.get(c))) if c == "active" else row.get(c, "") for c in org_columns] for row in organisations],
        )
        connection.executemany(
            f"INSERT INTO websites ({','.join(web_columns)}) VALUES ({','.join('?' for _ in web_columns)})",
            [[int(bool(row.get(c))) if c == "is_primary" else row.get(c, "") for c in web_columns] for row in websites],
        )
        connection.executemany(
            f"INSERT INTO sources ({','.join(src_columns)}) VALUES ({','.join('?' for _ in src_columns)})",
            [[row.get(c, "") for c in src_columns] for row in sources],
        )
        connection.executescript(
            """
            CREATE INDEX idx_organizations_type ON organizations(organization_type);
            CREATE INDEX idx_organizations_state ON organizations(federal_state);
            CREATE INDEX idx_organizations_municipality_code ON organizations(municipality_code);
            CREATE INDEX idx_organizations_school_code ON organizations(school_code);
            CREATE UNIQUE INDEX idx_websites_org_url ON websites(organization_id, url);
            CREATE INDEX idx_websites_domain ON websites(domain);
            """
        )
        connection.commit()
    finally:
        connection.close()


def main() -> int:
    municipalities: list[dict[str, Any]] = []
    authority_orgs: list[dict[str, Any]] = []
    authority_websites: list[dict[str, Any]] = []
    schools: list[dict[str, Any]] = []
    school_websites: list[dict[str, Any]] = []
    errors: list[str] = []

    try:
        municipalities = municipality_rows()
    except Exception as exc:  # noqa: BLE001
        errors.append(f"municipalities: {exc}")
        log(errors[-1])

    type_map: dict[str, str] = {}
    try:
        authority_orgs, authority_websites, type_map = authority_rows()
    except Exception as exc:  # noqa: BLE001
        errors.append(f"authorities: {exc}")
        log(errors[-1])

    try:
        schools, school_websites = school_rows()
    except Exception as exc:  # noqa: BLE001
        errors.append(f"schools: {exc}")
        log(errors[-1])

    authority_orgs, municipality_websites = merge_municipality_websites(
        municipalities, authority_orgs, authority_websites
    )
    consumed_authority_ids = {w["organization_id"] for w in municipality_websites}
    # municipality_websites already point to municipality IDs; retain all authority
    # websites belonging to still-existing authority rows.
    authority_ids = {row["organization_id"] for row in authority_orgs}
    authority_websites = [w for w in authority_websites if w["organization_id"] in authority_ids]

    organisations = municipalities + authority_orgs + schools
    # Deduplicate organisation IDs, keeping the richest row.
    org_by_id: dict[str, dict[str, Any]] = {}
    for row in organisations:
        current = org_by_id.get(row["organization_id"])
        if current is None or sum(bool(v) for v in row.values()) > sum(bool(v) for v in current.values()):
            org_by_id[row["organization_id"]] = row
    organisations = sorted(org_by_id.values(), key=lambda r: (r.get("organization_type", ""), r.get("name", "")))

    websites = authority_websites + municipality_websites + school_websites
    valid_org_ids = set(org_by_id)
    websites = [w for w in websites if w["organization_id"] in valid_org_ids and w.get("url")]
    website_by_key = {(w["organization_id"], w["url"]): w for w in websites}
    websites = sorted(website_by_key.values(), key=lambda r: (r["organization_id"], not r.get("is_primary", False), r["url"]))

    sources = [
        {
            "source_id": "SRC-STATAT-GEM-2026",
            "source_name": "Gliederung Österreichs in Gemeinden – Gemeinden 2026-01-01",
            "publisher": "Statistik Austria",
            "source_url": SOURCE_URLS["municipalities"],
            "scope": "Amtliche Gemeindecodes und Gemeindenamen",
            "as_of_date": "2026-01-01",
            "retrieved_at": NOW,
            "license": "CC BY 4.0",
            "notes": "WFS-Attributbeschreibung: ID=Gemeindecode; NAME=Gemeindename",
        },
        {
            "source_id": "SRC-OEGV-ORGSEARCH",
            "source_name": "Behördensuche",
            "publisher": "oesterreich.gv.at / Bundeskanzleramt",
            "source_url": SOURCE_URLS["authorities"],
            "scope": "Post- und Internetadressen von Behörden und Institutionen",
            "as_of_date": "",
            "retrieved_at": NOW,
            "license": "",
            "notes": "Behördentypen automatisiert aus den öffentlich zugänglichen Ergebnislisten",
        },
        {
            "source_id": "SRC-BMB-SCHULENONLINE",
            "source_name": "Schulen-Online – Suche nach Schulen",
            "publisher": "Bundesministerium für Bildung",
            "source_url": SOURCE_URLS["schools"],
            "scope": "Schulkennzahl, Titel, Adresse, Träger, Aufsicht, Schulart und Homepage",
            "as_of_date": "",
            "retrieved_at": NOW,
            "license": "",
            "notes": "Öffentlich zugängliches nationales Schulverzeichnis",
        },
    ]

    write_csv(OUT / "organizations.csv", organisations)
    write_csv(OUT / "websites.csv", websites)
    write_csv(OUT / "sources.csv", sources)
    write_csv(OUT / "municipalities.csv", municipalities)
    write_csv(OUT / "schools.csv", schools)
    ministries = [row for row in organisations if row.get("organization_type") == "ministry"]
    write_csv(OUT / "ministries.csv", ministries)
    build_sqlite(organisations, websites, sources)

    by_type = Counter(row.get("organization_type", "unknown") for row in organisations)
    coverage = {
        "generated_at": NOW,
        "organizations_total": len(organisations),
        "websites_total": len(websites),
        "domains_unique": len({w["domain"] for w in websites if w.get("domain")}),
        "organizations_by_type": dict(sorted(by_type.items())),
        "municipalities": len(municipalities),
        "municipalities_with_website": len({w["organization_id"] for w in municipality_websites}),
        "schools": len(schools),
        "schools_with_website": len({w["organization_id"] for w in school_websites}),
        "ministries": len(ministries),
        "authority_type_map": type_map,
        "errors": errors,
        "scope_note": (
            "A versioned best-effort register. Literal completeness cannot be guaranteed because "
            "domains, subdomains, organisational structures and directory data change continuously."
        ),
    }
    (OUT / "coverage.json").write_text(json.dumps(coverage, ensure_ascii=False, indent=2), encoding="utf-8")

    readme = f"""# Österreichisches Register öffentlicher Webauftritte\n\nGenerated: {NOW}\n\n## Inhalt\n\n- `organizations.csv`: Organisationen mit stabilen amtlichen Kennziffern, soweit verfügbar\n- `websites.csv`: getrennte Webauftritte, Domains, Unterseiten und Prüfstatus\n- `municipalities.csv`: amtliche Gemeinden 2026\n- `schools.csv`: Schulen aus Schulen-Online\n- `ministries.csv`: aktuelle Bundesministerien aus der Behördensuche\n- `sources.csv`: Quellenkatalog\n- `austria-public-websites.sqlite`: normalisierte SQLite-Datenbank\n- `coverage.json`: Abdeckung und Importfehler\n\n## Abdeckung dieses Laufs\n\n- Organisationen: **{len(organisations)}**\n- Webauftritte: **{len(websites)}**\n- Gemeinden: **{len(municipalities)}**\n- Schulen: **{len(schools)}**\n- Ministerien: **{len(ministries)}**\n\n## Modell\n\nOrganisation und Website sind getrennt. Eine Organisation kann keine, eine oder mehrere\nWebpräsenzen besitzen. Eine URL kann eine eigene Domain, eine Subdomain oder nur eine\nUnterseite eines übergeordneten Portals sein. Der Quellen- und Prüfstatus bleibt je URL erhalten.\n\n## Wichtiger Hinweis\n\nDas Register ist auf Aktualisierbarkeit ausgelegt. Eine Behauptung absoluter Vollständigkeit\nwäre bei laufend wechselnden Domains, organisatorischen Änderungen und nur teilweise offenen\nVerzeichnissen unseriös. Fehlende Webadressen bleiben deshalb sichtbar und werden nicht geraten.\n"""
    (OUT / "README.md").write_text(readme, encoding="utf-8")

    log(json.dumps(coverage, ensure_ascii=False))
    # Keep the workflow successful when at least the municipality master loaded;
    # partial school/authority results remain useful and diagnostics are uploaded.
    return 0 if municipalities else 1


if __name__ == "__main__":
    sys.exit(main())
