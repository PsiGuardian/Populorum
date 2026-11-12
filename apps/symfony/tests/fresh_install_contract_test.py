#!/usr/bin/env python3
"""Ensure clean alpha distributions cannot regress to an upgrade-first guide."""
from pathlib import Path
import re

ROOT=Path(__file__).resolve().parents[1]
REPO=ROOT.parents[1]
master=(REPO/'LIRE_AVANT_INSTALLATION.md').read_text(encoding='utf-8')
assert 'v0.13.3-clean' in master
assert 'installation neuve' in master.lower()
assert '## 9. Diagnostic d\'une installation neuve' in master
assert '## 9. Mettre à jour' not in master
assert 'Sauvegarder l\'état existant' not in master
assert 'python3 tools/decp_pipeline.py download' in master
assert 'python3 tools/decp_pipeline.py publish --site-stopped' in master
# Regression: the DECP publication must be the sole command in its copyable
# block. It must not be joined to the distinct SSMSI DATA_URL assignment.
blocks=re.findall(r'```bash\n(.*?)\n```', master, flags=re.S)
publish=[b for b in blocks if 'tools/decp_pipeline.py publish' in b]
assert publish==['python3 tools/decp_pipeline.py publish --site-stopped'], publish
status=[b for b in blocks if 'tools/decp_pipeline.py status' in b]
assert status==['python3 tools/decp_pipeline.py status'], status
ssmsi=[b for b in blocks if 'SSMSI="data/research/ssmsi-national-2025.sqlite"' in b]
assert len(ssmsi)==1 and 'DATA_URL=' in ssmsi[0], 'SSMSI block must remain separate'
assert 'cache:clear' not in ssmsi[0], 'Fresh installation must not clear legacy cache'

localized=list((ROOT/'docs/translations').glob('*/INSTALLATION.md'))
assert len(localized)==28, f'28 localized installation guides expected, found {len(localized)}'
for path in localized:
    text=path.read_text(encoding='utf-8')
    headings=[line for line in text.splitlines() if line.startswith('## ')]
    assert len(headings)==4, (str(path),headings)
    assert 'composer install' in text and 'doctrine:migrations:migrate' in text
    assert 'LIRE_AVANT_INSTALLATION.md' in text
print('OK — 28 fresh-install guides, canonical version and verified public source commands.')
