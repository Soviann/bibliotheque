#!/usr/bin/env python3
"""
Fusionne Bibliotheque.xlsx + nas-import.xlsx → merged-import.xlsx
Nettoie Livres.xlsx → clean-livres.xlsx
Enrichit les tomes achetés depuis Livres.xlsx dans le fichier fusionné.
"""

import os
import re
import sys
import unicodedata

import openpyxl

# --- Paths ---
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BIBLIO_PATH = os.path.join(BASE_DIR, "var", "Bibliotheque.xlsx")
NAS_PATH = os.path.join(BASE_DIR, "var", "nas-import.xlsx")
LIVRES_PATH = os.path.join(BASE_DIR, "var", "Livres.xlsx")
OUTPUT_MERGED = os.path.join(BASE_DIR, "var", "merged-import.xlsx")
OUTPUT_LIVRES = os.path.join(BASE_DIR, "var", "clean-livres.xlsx")

# --- Column mappings ---
# ImportExcelService expects: Titre(0), Buy?(1), Last bought(2), Current(3),
#                             Parution(4), Last dled(5), On NAS?(6), Parution terminée(7)
BIBLIO_HEADERS = [
    "Titre", "Buy?", "Last bought", "Current",
    "Parution", "Last dled", "On NAS ?", "Parution terminée",
]

# nas-import.xlsx columns → index in output
NAS_COL_MAP = {
    0: 0,  # Titre → Titre
    1: 1,  # Statut → Buy?
    2: 2,  # Dernier acheté → Last bought
    3: 3,  # Lu jusqu'à → Current
    4: 4,  # Nombre publié → Parution
    5: 5,  # Dernier téléchargé → Last dled
    6: 6,  # Sur NAS → On NAS ?
    7: 7,  # Parution terminée → Parution terminée
    # 8: Éditeur — not in ImportExcelService format, dropped
}

# Columns where Bibliotheque.xlsx takes priority on conflict
BIBLIO_PRIORITY_COLS = {1, 2, 3}  # Buy?, Last bought, Current
# Columns where nas-import.xlsx takes priority on conflict
NAS_PRIORITY_COLS = {5, 6}  # Last dled, On NAS ?
# Columns where we keep whichever is non-null (or Biblio if both set): 4, 7

SHEETS = ["BD", "Comics", "Mangas"]

# Tome extraction patterns (same as ImportBooksService)
TOME_PATTERNS = [
    r"^(.+?)\s*[-–]\s*[Tt]ome\s+(\d+)",
    r"^(.+?),\s*[Tt]ome\s+(\d+)",
    r"^(.+?)\s*[-–]\s*[Tt](\d+)\s",
    r"^(.+?)\s*[-–]\s*[Tt](\d+)$",
    r"^(.+?)\s+[Tt](\d+)\s*[-–:]",
    r"^(.+?)\s+[Tt](\d+)$",
    r"^(.+?),\s*[Tt](\d+)\s*[-–:]",
    r"^(.+?)\s*[-–]\s*n[oº°]?\s*(\d+)",
]

# Category → sheet type mapping
CATEGORY_SHEET_MAP = {
    "bd": "BD",
    "comics": "Comics",
    "manga": "Mangas",
    "livre": "Livre",
}


def normalize(s):
    """Normalize title for fuzzy matching (same logic as ComicSeriesRepository)."""
    if s is None:
        return ""
    s = str(s).strip().lower()
    s = unicodedata.normalize("NFD", s)
    s = "".join(c for c in s if unicodedata.category(c) != "Mn")
    s = re.sub(r"[-'\u2019.,!?():]+", "", s)
    s = re.sub(r"\s+", " ", s).strip()
    return s


def extract_series(title):
    """Extract series name and tome number from a book title."""
    for pat in TOME_PATTERNS:
        m = re.match(pat, title, re.UNICODE)
        if m:
            return m.group(1).strip(), int(m.group(2))
    return title.strip(), None


def detect_sheet_type(categories):
    """Determine which sheet a Livres.xlsx entry belongs to based on categories."""
    if not categories:
        return None
    cats = str(categories).lower()
    # Priority order: BD > Comics > Manga > Livre
    for keyword, sheet in [("bd", "BD"), ("comics", "Comics"), ("manga", "Mangas"), ("livre", "Livre")]:
        if keyword in cats:
            return sheet
    return None


def clean_title(title):
    """Remove trailing dots and spaces from a title."""
    return title.rstrip(". ") if title else title


def read_sheet_rows(ws, max_cols=8):
    """Read all data rows from a worksheet, returning list of lists."""
    rows = []
    for row in ws.iter_rows(min_row=2, values_only=True):
        title = row[0] if row else None
        if title is None:
            continue
        # Normalize to max_cols columns
        values = list(row[:max_cols])
        while len(values) < max_cols:
            values.append(None)
        # Ensure title is string, clean trailing dots/spaces
        values[0] = clean_title(str(values[0]).strip()) if values[0] is not None else None
        rows.append(values)
    return rows


def merge_rows(biblio_row, nas_row):
    """Merge two rows, taking the best value from each source."""
    merged = [None] * 8
    # Title: prefer Bibliotheque (original user data)
    merged[0] = biblio_row[0]

    for col in range(1, 8):
        bval = biblio_row[col] if col < len(biblio_row) else None
        nval = nas_row[col] if col < len(nas_row) else None

        if bval is not None and nval is not None:
            # Both have values — apply priority rules
            if col in BIBLIO_PRIORITY_COLS:
                merged[col] = bval
            elif col in NAS_PRIORITY_COLS:
                merged[col] = nval
            else:
                merged[col] = bval  # default: Biblio wins
        else:
            # Take whichever is non-null
            merged[col] = bval if bval is not None else nval

    return merged


def nas_row_to_output(nas_row):
    """Convert a nas-import row to the output format."""
    output = [None] * 8
    for nas_idx, out_idx in NAS_COL_MAP.items():
        if nas_idx < len(nas_row):
            output[out_idx] = nas_row[nas_idx]
    # Ensure title is string, clean trailing dots/spaces
    if output[0] is not None:
        output[0] = clean_title(str(output[0]).strip())
    return output


def parse_bought_value(val):
    """Parse 'Last bought' cell. Returns (specific_tomes: set|None, max_value: int|None, is_complete: bool)."""
    if val is None:
        return None, None, False
    s = str(val).strip().lower()
    if not s or s == "non":
        return None, None, False

    # "fini" or "fini N"
    m = re.match(r"fini\s*(\d+)?", s)
    if m:
        v = int(m.group(1)) if m.group(1) else None
        return None, v, True

    # "N+MHS"
    m = re.match(r"(\d+)\s*\+", s)
    if m:
        return None, int(m.group(1)), False

    # Comma-separated → specific tomes
    if "," in s:
        nums = {int(x.strip()) for x in s.split(",") if x.strip().isdigit() and int(x.strip()) > 0}
        return (nums if nums else None), (max(nums) if nums else None), False

    # Simple integer
    try:
        return None, int(float(s)), False
    except (ValueError, TypeError):
        return None, None, False


def format_specific_tomes(tomes):
    """Format a set of tome numbers as a CSV string."""
    return ", ".join(str(t) for t in sorted(tomes))


def main():
    # Verify all input files exist
    for path, name in [(BIBLIO_PATH, "Bibliotheque.xlsx"), (NAS_PATH, "nas-import.xlsx"), (LIVRES_PATH, "Livres.xlsx")]:
        if not os.path.exists(path):
            print(f"ERREUR : {name} introuvable → {path}")
            sys.exit(1)

    print("Chargement des fichiers sources...")
    wb_biblio = openpyxl.load_workbook(BIBLIO_PATH, read_only=True, data_only=True)
    wb_nas = openpyxl.load_workbook(NAS_PATH, read_only=True, data_only=True)
    wb_livres = openpyxl.load_workbook(LIVRES_PATH, read_only=True, data_only=True)

    # === Step 1: Extract bought tomes from Livres.xlsx ===
    print("\n--- Extraction des tomes achetés depuis Livres.xlsx ---")
    ws_livres = wb_livres[wb_livres.sheetnames[0]]

    # series_key → {sheet_type, max_tome, name, count}
    bought_tomes = {}
    for row in ws_livres.iter_rows(min_row=2, values_only=True):
        title = row[1]
        categories = row[5]
        if not title:
            continue
        series_name, tome_num = extract_series(str(title))
        sheet_type = detect_sheet_type(categories)
        key = normalize(series_name)

        if key not in bought_tomes:
            bought_tomes[key] = {
                "name": series_name,
                "sheet_type": sheet_type,
                "max_tome": tome_num,
                "specific_tomes": {tome_num} if tome_num else set(),
                "count": 1,
            }
        else:
            bought_tomes[key]["count"] += 1
            if tome_num is not None:
                bought_tomes[key]["specific_tomes"].add(tome_num)
                current_max = bought_tomes[key]["max_tome"]
                if current_max is None or tome_num > current_max:
                    bought_tomes[key]["max_tome"] = tome_num

    print(f"  {len(bought_tomes)} séries extraites ({sum(1 for v in bought_tomes.values() if v['max_tome'])} avec tomes)")

    # === Step 2: Merge Bibliotheque + NAS per sheet ===
    wb_out = openpyxl.Workbook()
    wb_out.remove(wb_out.active)

    stats = {}
    enrichment_stats = {"matched": 0, "updated_bought": 0}

    for sheet_name in SHEETS:
        print(f"\n--- Fusion onglet {sheet_name} ---")

        # Read both sources
        biblio_rows = read_sheet_rows(wb_biblio[sheet_name]) if sheet_name in wb_biblio.sheetnames else []
        nas_rows = []
        if sheet_name in wb_nas.sheetnames:
            ws_nas = wb_nas[sheet_name]
            for row in ws_nas.iter_rows(min_row=2, values_only=True):
                if row[0] is not None:
                    converted = nas_row_to_output(list(row))
                    nas_rows.append(converted)

        # Index by normalized title
        biblio_index = {}
        for row in biblio_rows:
            key = normalize(row[0])
            if key:
                biblio_index[key] = row

        nas_index = {}
        for row in nas_rows:
            key = normalize(row[0])
            if key:
                nas_index[key] = row

        # Merge
        merged_rows = []
        merged_keys = set()
        both_count = 0
        only_biblio = 0
        only_nas = 0

        # Process Bibliotheque rows first (preserving order)
        for row in biblio_rows:
            key = normalize(row[0])
            if not key or key in merged_keys:
                continue
            merged_keys.add(key)

            if key in nas_index:
                merged_rows.append(merge_rows(row, nas_index[key]))
                both_count += 1
            else:
                merged_rows.append(list(row[:8]))
                only_biblio += 1

        # Add NAS-only rows
        for row in nas_rows:
            key = normalize(row[0])
            if not key or key in merged_keys:
                continue
            merged_keys.add(key)
            merged_rows.append(row)
            only_nas += 1

        # Enrich with Livres.xlsx bought tomes
        enriched_in_sheet = 0
        merged_by_key = {normalize(r[0]): r for r in merged_rows}

        for key, info in bought_tomes.items():
            if info["sheet_type"] != sheet_name:
                continue
            if key not in merged_by_key:
                continue

            row = merged_by_key[key]
            enrichment_stats["matched"] += 1

            # Update "Last bought" (col 2) with specific tomes from Livres.xlsx
            if info["max_tome"] is not None:
                existing_specific, existing_max, existing_complete = parse_bought_value(row[2])

                if existing_complete:
                    # Already "fini" — all tomes bought, nothing to add
                    pass
                elif existing_specific is not None:
                    # Existing is a CSV list — merge the specific tomes
                    merged_tomes = existing_specific | info["specific_tomes"]
                    row[2] = format_specific_tomes(merged_tomes)
                    if merged_tomes != existing_specific:
                        enrichment_stats["updated_bought"] += 1
                        enriched_in_sheet += 1
                elif existing_max is not None:
                    # Existing is a single number (= all tomes 1..N bought)
                    # Add Livres.xlsx tomes that are beyond existing_max
                    extra = {t for t in info["specific_tomes"] if t > existing_max}
                    if extra:
                        # Convert to specific list: 1..existing_max + extras
                        all_tomes = set(range(1, existing_max + 1)) | extra
                        row[2] = format_specific_tomes(all_tomes)
                        enrichment_stats["updated_bought"] += 1
                        enriched_in_sheet += 1
                else:
                    # No existing value — set specific tomes from Livres.xlsx
                    row[2] = format_specific_tomes(info["specific_tomes"])
                    enrichment_stats["updated_bought"] += 1
                    enriched_in_sheet += 1

            # Set Buy? = "oui" if not already set
            if row[1] is None:
                row[1] = "oui"

        # Sort by title
        merged_rows.sort(key=lambda r: normalize(r[0]))

        # Write sheet
        ws_out = wb_out.create_sheet(title=sheet_name)
        ws_out.append(BIBLIO_HEADERS)
        for row in merged_rows:
            ws_out.append(row)

        total = len(merged_rows)
        stats[sheet_name] = {
            "total": total,
            "both": both_count,
            "only_biblio": only_biblio,
            "only_nas": only_nas,
            "enriched": enriched_in_sheet,
        }
        print(f"  {total} séries (commun: {both_count}, biblio seul: {only_biblio}, NAS seul: {only_nas}, enrichis Livres: {enriched_in_sheet})")

    # === Step 3: Copy Livre sheet from Bibliotheque ===
    print("\n--- Onglet Livre ---")
    livre_rows = read_sheet_rows(wb_biblio["Livre"]) if "Livre" in wb_biblio.sheetnames else []

    # Enrich Livre sheet with Livres.xlsx
    livre_by_key = {normalize(r[0]): r for r in livre_rows}
    enriched_livre = 0
    for key, info in bought_tomes.items():
        if info["sheet_type"] != "Livre":
            continue
        if key not in livre_by_key:
            continue
        row = livre_by_key[key]
        enrichment_stats["matched"] += 1
        if info["max_tome"] is not None:
            existing_specific, existing_max, existing_complete = parse_bought_value(row[2])
            if not existing_complete:
                if existing_specific is not None:
                    merged_tomes = existing_specific | info["specific_tomes"]
                    if merged_tomes != existing_specific:
                        row[2] = format_specific_tomes(merged_tomes)
                        enrichment_stats["updated_bought"] += 1
                        enriched_livre += 1
                elif existing_max is not None:
                    extra = {t for t in info["specific_tomes"] if t > existing_max}
                    if extra:
                        all_tomes = set(range(1, existing_max + 1)) | extra
                        row[2] = format_specific_tomes(all_tomes)
                        enrichment_stats["updated_bought"] += 1
                        enriched_livre += 1
                else:
                    row[2] = format_specific_tomes(info["specific_tomes"])
                    enrichment_stats["updated_bought"] += 1
                    enriched_livre += 1
        if row[1] is None:
            row[1] = "oui"

    livre_rows.sort(key=lambda r: normalize(r[0]))

    ws_livre = wb_out.create_sheet(title="Livre")
    ws_livre.append(BIBLIO_HEADERS)
    for row in livre_rows:
        ws_livre.append(row)

    stats["Livre"] = {"total": len(livre_rows), "enriched": enriched_livre}
    print(f"  {len(livre_rows)} séries (enrichis Livres: {enriched_livre})")

    # === Step 3b: Deduplicate cross-sheet entries ===
    print("\n--- Déduplication cross-onglets ---")
    # Build index of all titles across sheets
    sheet_priority = {"BD": 1, "Mangas": 2, "Comics": 3, "Livre": 4}
    all_entries = {}  # normalized_key -> list of (sheet_name, row_idx, has_data)

    for sheet_name in wb_out.sheetnames:
        ws = wb_out[sheet_name]
        for row_idx, row in enumerate(ws.iter_rows(min_row=2, values_only=False), start=2):
            title = str(row[0].value).strip() if row[0].value else ""
            key = normalize(title)
            if not key:
                continue
            has_data = any(cell.value is not None for cell in row[1:8])
            all_entries.setdefault(key, []).append({
                "sheet": sheet_name,
                "row_idx": row_idx,
                "has_data": has_data,
                "priority": sheet_priority.get(sheet_name, 99),
            })

    rows_to_delete = {}  # sheet_name -> list of row_idx
    cross_dupes = 0

    for key, entries in all_entries.items():
        sheets = set(e["sheet"] for e in entries)
        if len(sheets) <= 1:
            continue

        # Keep entry with data + best priority; merge data into keeper
        entries.sort(key=lambda e: (not e["has_data"], e["priority"]))
        keeper = entries[0]
        ws_keeper = wb_out[keeper["sheet"]]

        for other in entries[1:]:
            ws_other = wb_out[other["sheet"]]
            # Merge non-null values from other into keeper
            for col in range(2, 9):  # columns B-H
                if ws_keeper.cell(row=keeper["row_idx"], column=col).value is None:
                    other_val = ws_other.cell(row=other["row_idx"], column=col).value
                    if other_val is not None:
                        ws_keeper.cell(row=keeper["row_idx"], column=col).value = other_val

            rows_to_delete.setdefault(other["sheet"], []).append(other["row_idx"])
            cross_dupes += 1
            title = ws_other.cell(row=other["row_idx"], column=1).value
            print(f"  '{title}' [{other['sheet']}] fusionné dans [{keeper['sheet']}]")

    for sheet_name, indices in rows_to_delete.items():
        ws = wb_out[sheet_name]
        for row_idx in sorted(indices, reverse=True):
            ws.delete_rows(row_idx)

    if cross_dupes:
        print(f"  {cross_dupes} doublons cross-onglets fusionnés")
    else:
        print("  Aucun doublon cross-onglets")

    # Save merged file
    wb_out.save(OUTPUT_MERGED)
    print(f"\n✓ Fichier fusionné : {OUTPUT_MERGED}")

    # === Step 4: Clean Livres.xlsx ===
    print("\n--- Nettoyage Livres.xlsx ---")
    wb_clean = openpyxl.Workbook()
    ws_clean = wb_clean.active
    ws_clean.title = "Bibliothèque"

    clean_headers = ["Code-barres", "Titre", "Auteur", "Éditeur", "Couverture", "Catégories", "Description"]
    ws_clean.append(clean_headers)

    book_count = 0
    file_urls_cleaned = 0
    for row in wb_livres[wb_livres.sheetnames[0]].iter_rows(min_row=2, values_only=True):
        title = row[1]
        if not title:
            continue

        isbn = row[0]
        # Clean ISBN: remove .0 suffix from Excel
        if isbn is not None:
            isbn_str = str(isbn)
            if isbn_str.endswith(".0"):
                isbn_str = isbn_str[:-2]
            isbn = isbn_str

        author = row[2]
        publisher = row[3]
        cover = row[4]
        categories = row[5]
        description = row[6]

        # Replace file:// URLs with None
        if cover and str(cover).startswith("file://"):
            cover = None
            file_urls_cleaned += 1

        ws_clean.append([isbn, str(title), author, publisher, cover, categories, description])
        book_count += 1

    # Deduplicate by ISBN and normalized title
    seen_isbn = set()
    seen_title = set()
    rows_to_delete = []

    for row_idx, row in enumerate(ws_clean.iter_rows(min_row=2, values_only=False), start=2):
        isbn = str(row[0].value).strip() if row[0].value else ""
        title = str(row[1].value).strip() if row[1].value else ""
        title_key = normalize(title)

        is_dupe = False
        if isbn and isbn != "None" and isbn in seen_isbn:
            is_dupe = True
        elif title_key and title_key in seen_title:
            is_dupe = True

        if is_dupe:
            rows_to_delete.append(row_idx)
        else:
            if isbn and isbn != "None":
                seen_isbn.add(isbn)
            if title_key:
                seen_title.add(title_key)

    for row_idx in sorted(rows_to_delete, reverse=True):
        ws_clean.delete_rows(row_idx)

    dupes_removed = len(rows_to_delete)
    book_count -= dupes_removed

    wb_clean.save(OUTPUT_LIVRES)
    print(f"  {book_count} livres ({file_urls_cleaned} URLs file:// supprimées, {dupes_removed} doublons supprimés)")
    print(f"✓ Fichier nettoyé : {OUTPUT_LIVRES}")

    # Close source workbooks
    wb_biblio.close()
    wb_nas.close()
    wb_livres.close()

    # === Summary ===
    print("\n=== RÉSUMÉ ===")
    grand_total = sum(s["total"] for s in stats.values())
    print(f"Total séries dans merged-import.xlsx : {grand_total}")
    for sheet_name, s in stats.items():
        print(f"  {sheet_name}: {s['total']}")
    print(f"Enrichissements Livres.xlsx : {enrichment_stats['matched']} matchés, {enrichment_stats['updated_bought']} 'Last bought' mis à jour")
    print(f"clean-livres.xlsx : {book_count} livres")


if __name__ == "__main__":
    main()
