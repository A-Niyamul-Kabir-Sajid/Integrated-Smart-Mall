"""Validate documentation links, module coverage, and preserved source integrity."""

from pathlib import Path
from urllib.parse import unquote, urlsplit
import hashlib
import json
import re
import sys


ROOT = Path(__file__).resolve().parents[1]
REPO = ROOT.parent
EXPECTED_MODULES = {
    "AccountsAccess", "MallShopDirectory", "ProductsCatalogue", "MapsNavigation",
    "PurchaseVerification", "ReviewsModeration", "FavouritesPreferences",
    "ChatbotRag", "AnalyticsReports", "ParkingFacilities", "ShopOwnerOperations",
    "MallOwnerAdmin", "SharedPlatform",
}


def main():
    errors = []
    checked_links = 0
    markdown = list(ROOT.rglob("*.md")) + [REPO / "AGENTS.md", REPO / "CLAUDE.md"]
    for path in markdown:
        text = path.read_text(encoding="utf-8-sig")
        # Ignore code examples; validate explicit inline Markdown file links.
        text = re.sub(r"```.*?```", "", text, flags=re.S)
        for raw in re.findall(r"\[[^\]\n]*\]\(([^)\n]+)\)", text):
            target = raw.strip().strip("<>")
            parsed = urlsplit(target)
            if parsed.scheme or target.startswith(("#", "//")):
                continue
            link = unquote(parsed.path)
            if not link:
                continue
            resolved = (REPO / link.lstrip("/") if link.startswith("/") else path.parent / link).resolve()
            checked_links += 1
            if not resolved.exists():
                errors.append(f"Broken link: {path.relative_to(REPO)} -> {raw}")

    present = {p.name for p in (ROOT / "modules").iterdir() if p.is_dir()}
    if present != EXPECTED_MODULES:
        errors.append(f"Module mismatch: missing={EXPECTED_MODULES - present}; extra={present - EXPECTED_MODULES}")
    for name in EXPECTED_MODULES:
        if not (ROOT / "modules" / name / "README.md").is_file():
            errors.append(f"Missing module guide: {name}")
        if not (REPO / "app" / "Modules" / name).is_dir():
            errors.append(f"Missing application module: {name}")

    inventory = json.loads((ROOT / "maintenance" / "source-inventory.json").read_text(encoding="utf-8"))
    preserved = 0
    duplicates = 0
    for record in inventory["files"]:
        path = ROOT / record["current_path"]
        if not path.is_file():
            errors.append(f"Missing preserved source: {record['path']}")
            continue
        content = path.read_bytes()
        if "decoded_sha256" in record:
            digest = hashlib.sha256(content.decode("utf-8-sig").encode("utf-8")).hexdigest()
            expected = record["decoded_sha256"]
            duplicates += 1
        else:
            digest = hashlib.sha256(content).hexdigest()
            expected = record["sha256"]
            preserved += 1
        if digest != expected:
            errors.append(f"Preserved source changed: {record['current_path']}")

    print(f"Checked {len(markdown)} Markdown files and {checked_links} local file links.")
    print(f"Module guides: {len(present)}; preserved source files: {preserved}; deduplicated inventories: {duplicates}.")
    for error in errors:
        print(error)
    print("PASS" if not errors else f"FAIL: {len(errors)} issue(s)")
    return 1 if errors else 0


if __name__ == "__main__":
    sys.exit(main())
