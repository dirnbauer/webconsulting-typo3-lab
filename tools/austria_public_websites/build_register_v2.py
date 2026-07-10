#!/usr/bin/env python3
"""Second-pass importer fixes based on the first live-source diagnostics.

This module reuses the normalized export model from ``build_register.py`` and
replaces only the source adapters whose live markup differs from the initial
assumptions:

* Statistik Austria uses DBF fields ``g_id`` / ``g_name`` and represents Vienna
  by 23 district geometries. The register converts these to the official 2,092
  municipalities by adding Wien (90001) and treating the districts separately.
* oesterreich.gv.at exposes its authority type catalogue inside Next.js data,
  not HTML ``option`` elements.
* Schulen-Online has four forms on the page; the school search is ``myform1``.
"""
from __future__ import annotations

import io
import json
import re
import sys
import time
import urllib.parse
import zipfile
from collections import defaultdict
from pathlib import Path
from typing import Any

import shapefile  # type: ignore
from bs4 import BeautifulSoup, Tag

import build_register as base


AUTHORITY_CLASSIFICATION: dict[str, tuple[str, str, str]] = {
    "Notrufnummern": ("emergency_contact", "service", "public"),
    "Servicenummern": ("public_service_contact", "service", "public"),
    "Arbeitsinspektion": ("labour_inspectorate", "federal", "public"),
    "Arbeitsmarktservice": ("employment_service", "federal", "public_law"),
    "Bezirkshauptmannschaft": ("district_authority", "district", "public"),
    "Bundesministerium": ("ministry", "federal", "public"),
    "Finanzamt": ("tax_authority", "federal", "public"),
    "Gemeindeamt/Magistrat": ("municipality_authority", "municipal", "public"),
    "Gericht": ("judiciary", "federal", "public"),
    "Landesregierung": ("state_authority", "state", "public"),
    "Polizei": ("police_authority", "federal", "public"),
    "Standesamt": ("registry_office", "municipal", "public"),
    "Verkehrsamt": ("transport_authority", "district", "public"),
    "Volksanwaltschaft": ("ombudsman", "federal", "public"),
    "Zollamt": ("customs_authority", "federal", "public"),
    "Agrarmarkt Austria": ("federal_agency", "federal", "public_law"),
    "Österreichische Nationalbibliothek": ("federal_cultural_institution", "federal", "public_law"),
    "Sozialversicherungsträger": ("social_insurance", "statutory", "public_law"),
    "Rechnungshof": ("audit_institution", "federal", "public"),
    "Bundesdenkmalamt": ("federal_agency", "federal", "public"),
    "Europäische und internationale Einrichtungen": ("international_public_body", "international", "public"),
    "Technisches Museum Wien": ("federal_cultural_institution", "federal", "public_law"),
}

# Legacy/direct routes still exposed by the official directory, although they
# are not offered in the current combobox.
LEGACY_AUTHORITY_TYPES: dict[str, str] = {
    "1": "Agrarmarkt Austria",
    "5": "Österreichische Nationalbibliothek",
    "14": "Sozialversicherungsträger",
    "18": "Rechnungshof",
    "27": "Bundesdenkmalamt",
    "30": "Europäische und internationale Einrichtungen",
    "34": "Technisches Museum Wien",
}


def municipality_rows() -> list[dict[str, Any]]:
    """Load the 2026 Statistik Austria shape source as 2,092 municipalities."""
    shape_url = (
        "https://www.statistik.gv.at/gs-open/GEODATA/ows?service=WFS&version=1.0.0"
        "&request=GetFeature&typeName=GEODATA:STATISTIK_AUSTRIA_GEM_20260101"
        "&outputFormat=SHAPE-ZIP&format_options=CHARSET:UTF-8"
    )
    base.log("Fetching 2026 municipality master from Statistik Austria")
    response = base.request("GET", shape_url)
    (base.RAW / "municipalities-shape.zip").write_bytes(response.content)
    extract_dir = base.RAW / "municipalities-shape"
    extract_dir.mkdir(exist_ok=True)
    with zipfile.ZipFile(io.BytesIO(response.content)) as archive:
        archive.extractall(extract_dir)
    shp = next(extract_dir.glob("*.shp"))
    reader = shapefile.Reader(str(shp), encoding="utf-8")
    fields = [field[0] for field in reader.fields[1:]]

    rows: list[dict[str, Any]] = []
    vienna_districts: list[dict[str, str]] = []
    for record in reader.iterRecords():
        props = dict(zip(fields, record, strict=False))
        code = base.clean(
            props.get("g_id")
            or props.get("G_ID")
            or props.get("ID")
            or props.get("GKZ")
        ).zfill(5)
        name = base.clean(
            props.get("g_name")
            or props.get("G_NAME")
            or props.get("NAME")
            or props.get("GEMNAME")
        )
        if not code or not name:
            continue
        if code.startswith("9"):
            vienna_districts.append({"district_code": code, "district_name": name})
            continue
        rows.append(_municipality_row(code, name, shape_url))

    # The geometry product represents Vienna by 23 municipal districts. For the
    # municipality master, Vienna itself is one municipality (official count 2,092).
    rows.append(_municipality_row("90001", "Wien", shape_url))
    rows = sorted({row["municipality_code"]: row for row in rows}.values(), key=lambda row: row["municipality_code"])

    base.write_csv(
        base.OUT / "vienna-municipal-districts.csv",
        [
            {
                "organization_id": f"AT-WIEN-BEZ-{item['district_code'][1:3]}",
                "official_id": item["district_code"],
                "name": item["district_name"],
                "organization_type": "municipal_district",
                "parent_organization_id": "AT-GEM-90001",
                "federal_state": "Wien",
                "source_id": "SRC-STATAT-GEM-2026",
                "source_url": base.SOURCE_URLS["municipalities"],
                "retrieved_at": base.NOW,
            }
            for item in sorted(vienna_districts, key=lambda item: item["district_code"])
        ],
    )
    base.log(f"Municipalities loaded: {len(rows)}; Vienna districts documented: {len(vienna_districts)}")
    if len(rows) != 2092:
        raise RuntimeError(f"Expected 2,092 municipalities, received {len(rows)}")
    return rows


def _municipality_row(code: str, name: str, source_record_url: str) -> dict[str, Any]:
    return {
        "organization_id": f"AT-GEM-{code}",
        "official_id": code,
        "name": name,
        "organization_type": "municipality",
        "organization_type_label": "Gemeinde",
        "administrative_level": "municipal",
        "federal_state": base.FEDERAL_STATES.get(code[:1], ""),
        "district": "",
        "municipality_code": code,
        "school_code": "",
        "parent_organization_id": "",
        "ownership_control": "public",
        "legal_form": "Gebietskörperschaft",
        "active": True,
        "source_id": "SRC-STATAT-GEM-2026",
        "source_url": base.SOURCE_URLS["municipalities"],
        "source_record_url": source_record_url,
        "source_as_of": "2026-01-01",
        "retrieved_at": base.NOW,
        "notes": "",
    }


def parse_authority_options(raw_html: str) -> dict[str, str]:
    """Extract the Next.js authority catalogue embedded in the overview HTML."""
    options: dict[str, str] = {}
    pattern = re.compile(
        r'\{\\"id\\":(?P<id>\d+),'
        r'\\"orgTypeIdList\\":.*?,'
        r'\\"title\\":\\"(?P<title>.*?)\\",'
        r'\\"shortkey\\":\\".*?\\",'
        r'\\"regional\\":(?:true|false),'
        r'\\"type\\":(?P<is_type>true|false)\}'
    )
    for match in pattern.finditer(raw_html):
        if match.group("is_type") != "true":
            continue
        options[match.group("id")] = bytes(match.group("title"), "utf-8").decode("unicode_escape")
    return options


def classify_org_type(label: str, name: str) -> tuple[str, str, str]:
    if label in AUTHORITY_CLASSIFICATION:
        return AUTHORITY_CLASSIFICATION[label]
    joined = f"{label} {name}".casefold()
    if "universität" in joined or "hochschule" in joined:
        return "higher_education", "federal", "public_or_private"
    if "kammer" in joined:
        return "chamber", "statutory", "public_law"
    if "fonds" in joined:
        return "public_fund", "unknown", "public_or_attached"
    if "agentur" in joined or "anstalt" in joined:
        return "public_agency", "unknown", "public_or_attached"
    return "public_body", "unknown", "public_or_attached"


def authority_rows() -> tuple[list[dict[str, Any]], list[dict[str, Any]], dict[str, str]]:
    base_url = base.SOURCE_URLS["authorities"]
    base.log("Reading official authority catalogue from oesterreich.gv.at")
    response = base.request("GET", base_url)
    raw_html = response.text
    (base.RAW / "oesterreich-orgsearch.html").write_text(raw_html, encoding="utf-8")
    type_map = parse_authority_options(raw_html)
    type_map.update({key: value for key, value in LEGACY_AUTHORITY_TYPES.items() if key not in type_map})
    if not type_map:
        raise RuntimeError("No authority types found in official catalogue")
    base.log(f"Authority types discovered: {len(type_map)}")

    # Preserve the municipality-picker source for a later, dedicated website
    # resolver. Its structure is independent from the authority result pages.
    try:
        picker_url = "https://www.oesterreich.gv.at/de/orgsearch/gemeindeauswahl/orgtypegroup/2"
        picker = base.request("GET", picker_url)
        (base.RAW / "oesterreich-municipality-picker.html").write_text(picker.text, encoding="utf-8")
    except Exception as exc:  # noqa: BLE001
        base.log(f"Municipality picker could not be retained: {exc}")

    organisations: list[dict[str, Any]] = []
    websites: list[dict[str, Any]] = []
    for type_id, label in sorted(type_map.items(), key=lambda item: int(item[0])):
        url = f"https://www.oesterreich.gv.at/de/orgsearch/orgtyp/{type_id}"
        try:
            response = base.request("GET", url, attempts=3)
        except Exception as exc:  # noqa: BLE001
            base.log(f"  authority type {type_id} ({label}) failed: {exc}")
            continue
        (base.RAW / f"oesterreich-orgtyp-{type_id}.html").write_text(response.text, encoding="utf-8")
        soup = BeautifulSoup(response.text, "lxml")
        headings = [
            heading
            for heading in soup.find_all("h2")
            if base.clean(heading.get_text(" ", strip=True)).casefold()
            not in {
                "abfrageergebnis",
                "information",
                "behördensuche",
                "behörden- / personenverzeichnisse",
                "behörden-/personenverzeichnisse",
                "seite nicht gefunden",
            }
        ]
        base.log(f"  authority type {type_id} ({label}): {len(headings)} entries")
        for heading in headings:
            name = base.clean(heading.get_text(" ", strip=True))
            nodes = base.section_siblings(heading)
            text, links = base.text_and_links(nodes, url)
            street, postal_code, locality = base.parse_address_from_text(text)
            emails = sorted(set(re.findall(r"[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}", text, re.I)))
            phones: list[str] = []
            for line in text.splitlines():
                if re.match(r"^(Telefon|Tel\.|T:?)", line, re.I):
                    phones.append(base.clean(re.sub(r"^(Telefon|Tel\.|T:?)\s*: ?", "", line, flags=re.I)))
            external_links: list[str] = []
            for link in links:
                host = base.domain_of(link)
                if not host:
                    continue
                if host in {"maps.google.com", "google.com", "route.bmk.gv.at", "oesterreich.gv.at"}:
                    continue
                if host.endswith("google.com"):
                    continue
                external_links.append(link)
            external_links = list(dict.fromkeys(external_links))

            org_type, level, ownership = classify_org_type(label, name)
            org_id = base.stable_id("AT-ORG", type_id, name, street, postal_code, locality)
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
                    "phone": "; ".join(phone for phone in phones if phone),
                    "source_id": "SRC-OEGV-ORGSEARCH",
                    "source_url": base_url,
                    "source_record_url": url,
                    "source_as_of": "",
                    "retrieved_at": base.NOW,
                    "notes": f"Behördentyp-ID {type_id}",
                }
            )
            for index, link in enumerate(external_links, start=1):
                websites.append(
                    {
                        "website_id": base.stable_id("AT-WEB", org_id, link),
                        "organization_id": org_id,
                        "url": link,
                        "domain": base.domain_of(link),
                        "presence_type": (
                            "own_domain"
                            if urllib.parse.urlsplit(link).path in {"", "/"}
                            else "website_or_subpage"
                        ),
                        "is_primary": index == 1,
                        "verification_status": "listed_by_official_directory",
                        "http_status": "",
                        "redirect_target": "",
                        "last_checked": base.NOW,
                        "source_id": "SRC-OEGV-ORGSEARCH",
                        "source_url": url,
                        "notes": "",
                    }
                )
        time.sleep(0.03)

    deduped: dict[tuple[str, str, str], dict[str, Any]] = {}
    id_alias: dict[str, str] = {}
    for row in organisations:
        key = (
            base.normalize_name(row["name"]),
            row.get("postal_code", ""),
            base.normalize_name(row.get("street_address", "")),
        )
        if key not in deduped:
            deduped[key] = row
        else:
            id_alias[row["organization_id"]] = deduped[key]["organization_id"]
            labels = set(
                filter(
                    None,
                    [deduped[key].get("organization_type_label", ""), row.get("organization_type_label", "")],
                )
            )
            deduped[key]["organization_type_label"] = "; ".join(sorted(labels))
    for website in websites:
        website["organization_id"] = id_alias.get(website["organization_id"], website["organization_id"])
    websites = list({(row["organization_id"], row["url"]): row for row in websites}.values())
    organisations = sorted(deduped.values(), key=lambda row: (row["organization_type"], row["name"]))
    websites = sorted(websites, key=lambda row: (row["organization_id"], not row["is_primary"], row["url"]))
    base.log(f"Authority organisations parsed: {len(organisations)}; websites: {len(websites)}")
    return organisations, websites, type_map


def school_search_post(session_local: Any, search_url: str) -> Any:
    """Submit the actual Schulen-Online search form (``myform1``)."""
    response = session_local.get(search_url, timeout=60)
    response.raise_for_status()
    (base.RAW / "schools-search-form.html").write_text(response.text, encoding="utf-8")
    soup = BeautifulSoup(response.text, "lxml")
    form = soup.find("form", id="myform1") or soup.find("form", attrs={"name": "myform1"})
    if not isinstance(form, Tag):
        raise RuntimeError("Schulen-Online form myform1 not found")
    data = base.form_hidden_values(form)
    form_id = "myform1"

    for input_tag in form.find_all("input", attrs={"name": True}):
        name = base.clean(input_tag.get("name"))
        input_type = base.clean(input_tag.get("type")).lower()
        if input_type in {"text", "search"}:
            data[name] = ""
    for select in form.find_all("select", attrs={"name": True}):
        name = base.clean(select.get("name"))
        value = base.choose_all_value(select)
        if name == "myform1:anz":
            value = "50"
        elif name == "myform1:sort":
            value = "0"
        data[name] = value

    data.update(
        {
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
        }
    )
    action = urllib.parse.urljoin(search_url, base.clean(form.get("action")) or search_url)
    result = session_local.post(action, data=data, timeout=90)
    result.raise_for_status()
    (base.RAW / "schools-search-result-first.html").write_text(result.text, encoding="utf-8")
    return result


def main() -> int:
    base.municipality_rows = municipality_rows
    base.authority_rows = authority_rows
    base.classify_org_type = classify_org_type
    base.school_search_post = school_search_post
    return base.main()


if __name__ == "__main__":
    sys.exit(main())
