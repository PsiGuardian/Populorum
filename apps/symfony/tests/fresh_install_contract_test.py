#!/usr/bin/env python3
"""Ensure clean alpha distributions cannot regress to an upgrade-first guide."""
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
REPO=ROOT.parents[1]
master=(REPO/'LIRE_AVANT_INSTALLATION.md').read_text(encoding='utf-8')
assert 'v0.12.1-clean' in master
assert 'installation neuve' in master.lower()
assert '## 9. Diagnostic d\'une installation neuve' in master
assert '## 9. Mettre à jour' not in master
assert 'Sauvegarder l\'état existant' not in master
assert 'python3 tools/decp_pipeline.py download' in master
assert 'python3 tools/decp_pipeline.py publish --site-stopped' in master

localized=list((ROOT/'docs/translations').glob('*/INSTALLATION.md'))
assert len(localized)==28, f'28 localized installation guides expected, found {len(localized)}'
for path in localized:
    text=path.read_text(encoding='utf-8')
    headings=[line for line in text.splitlines() if line.startswith('## ')]
    assert len(headings)==4, (str(path),headings)
    assert 'composer install' in text and 'doctrine:migrations:migrate' in text
    assert 'LIRE_AVANT_INSTALLATION.md' in text
print('OK — 28 fresh-install guides, canonical version and verified public source commands.')
