#!/usr/bin/env python3
"""Offline history regression tests; uses a disposable SQLite index only."""
from __future__ import annotations
from pathlib import Path
# Tests are separated from operational tools; import only the supported data tooling.
import sys
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "tools"))
import copy
import json
import sqlite3
import tempfile
from import_decp import SCHEMA, ensure_fts, ensure_history_schema, import_file, contract_key
from backfill_decp_history import backfill

def record(amount: int, date: str, changed: int | None = None):
    item={'nature':'Marché','id':'TEST-2024-001','acheteur':{'id':'21000000000011'},
        'codeCPV':'45233140','objet':'Réfection de la chaussée de démonstration',
        'montant':amount,'dateNotification':'2024-01-10','datePublicationDonnees':date,
        'lieuExecution':{'typeCode':'Code commune','code':'75056'}}
    if changed is not None:
        item['modifications']=[{'modification':{'id':'M1','montant':changed}}]
    return item

def write(p: Path, *items: dict):
    p.write_text(json.dumps({'marches':{'marche':items,'contrat-concession':[]}},ensure_ascii=False),encoding='utf-8')

def main():
    with tempfile.TemporaryDirectory(prefix='decp-history-') as d:
        d=Path(d); db=sqlite3.connect(d/'test.sqlite');db.executescript(SCHEMA);ensure_fts(db);ensure_history_schema(db)
        first=d/'decp-2024-01.json';second=d/'decp-2024-02.json'
        m1=record(1000,'2024-01-20',900);m2=record(1200,'2024-02-20',100)
        write(first,m1,copy.deepcopy(m1));write(second,m2)
        assert import_file(db,first)
        assert not import_file(db,first),'Idempotency broken'
        assert import_file(db,second)
        key=contract_key(m1,'market')
        assert db.execute('SELECT COUNT(*) FROM contracts').fetchone()[0]==1
        obs=db.execute('SELECT base_amount_cents,modification_amount_cents,occurrences FROM contract_publications WHERE contract_key=? ORDER BY source_sha',(key,)).fetchall()
        assert len(obs)==2 and sorted(x[0] for x in obs)==[100000,120000],obs
        assert sum(x[2] for x in obs)==3,obs
        # Test older imported data, no observations: backfill should preserve contracts and imports.
        db.execute('DELETE FROM contract_publications');db.commit()
        before=db.execute('SELECT COUNT(*) FROM imports').fetchone()[0]
        summary=backfill(db,d)
        assert summary['processed']==2 and summary['snapshots']==2,summary
        assert db.execute('SELECT COUNT(*) FROM imports').fetchone()[0]==before
        assert backfill(db,d)['skipped']==2
        assert db.execute('SELECT MIN(base_amount_cents),MAX(base_amount_cents) FROM contract_publications').fetchone()==(100000,120000)
        # Missing source file must not wipe already restored observations.
        db.execute('DELETE FROM contract_publications WHERE source_sha=(SELECT sha256 FROM imports WHERE filename=?)',('decp-2024-02.json',));db.commit()
        second.unlink()
        summary=backfill(db,d)
        assert summary['missing']==1,summary
        assert db.execute('SELECT COUNT(*) FROM contract_publications').fetchone()[0]==1
        print('OK — 2 fichiers, 1 contrat, montants déclarés 1 000 € → 1 200 €')
        print('OK — avenant non additionné, doublons dans le fichier comptés séparément')
        print('OK — réimport idempotent, backfill sur anciens fichiers et source manquante signalée')
        db.close()
if __name__=='__main__': main()
