#!/usr/bin/env python3
"""Inventory SQL execution sinks for manual review (not a security proof)."""
from __future__ import annotations
import argparse
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SINK = re.compile(
    r"->\s*(?:prepare|query|exec|executeStatement|executeQuery|fetchAssociative|"
    r"fetchAllAssociative|fetchOne|fetchFirstColumn|createQuery|createQueryBuilder)"
    r"\s*\(|\.\s*(?:execute|executemany|executescript)\s*\(",
    re.IGNORECASE,
)

def scan() -> list[dict]:
    rows = []
    for group in ("src", "tools", "migrations"):
        for path in sorted((ROOT / group).rglob("*")):
            if path.suffix not in (".php", ".py") or not path.is_file():
                continue
            for number, raw in enumerate(path.read_text(encoding="utf-8").splitlines(), 1):
                if SINK.search(raw):
                    rows.append({
                        "path": str(path.relative_to(ROOT)),
                        "line": number,
                        "source": raw.strip()[:240],
                        "dynamic_review": bool(re.search(r"\.\s*\$|\bf['\"]", raw)),
                    })
    return rows

def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--json", action="store_true")
    args = parser.parse_args()
    rows = scan()
    if args.json:
        print(json.dumps({"count": len(rows), "sinks": rows}, indent=2, ensure_ascii=False))
    else:
        print(f"{len(rows)} SQL execution points in {len({r['path'] for r in rows})} files")
        for row in rows:
            flag = " [REVIEW]" if row["dynamic_review"] else ""
            print(f"{row['path']}:{row['line']}{flag} {row['source']}")
        print("Static sink inventory only: review data flow and authorization separately.")

if __name__ == "__main__":
    main()
