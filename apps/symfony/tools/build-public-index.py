#!/usr/bin/env python3
"""Rebuild the derived read-only public balances index from the archived DGFiP source."""
import gzip, hashlib, json, os, sqlite3, tempfile
from pathlib import Path

root = Path(__file__).resolve().parents[1]
source = root / 'data/research/municipal-balances-2025.json.gz'
target = root / 'data/research/public-balances-index.sqlite'
raw = source.read_bytes()
data = json.loads(gzip.decompress(raw))
handle, temporary = tempfile.mkstemp(prefix='public-balances-', suffix='.sqlite', dir=target.parent)
os.close(handle)
try:
    conn = sqlite3.connect(temporary)
    conn.execute('create table metadata (id integer primary key check(id=1), source_hash text not null, data text not null)')
    conn.execute('create table balances (siren text not null, account text not null, budget text not null, account_number integer not null, ordinal integer not null, payload text not null)')
    conn.execute('insert into metadata values (1,?,?)', (hashlib.sha256(raw).hexdigest(), json.dumps({k:v for k,v in data.items() if k != 'rows'}, ensure_ascii=False)))
    rows = []
    for i, row in enumerate(data['rows']):
        # Match PHP's existing numeric-string comparison, including stable equal-key order.
        assert isinstance(row['lbudg'], str) and str(row['compte']).isdigit()
        rows.append((row['siren'], row['compte'], row['lbudg'], int(row['compte']), i, json.dumps(row, ensure_ascii=False)))
    conn.executemany('insert into balances values (?,?,?,?,?,?)', rows)
    conn.execute('create index balances_city_order on balances(siren, budget, account_number, ordinal)')
    conn.commit()
    assert conn.execute('pragma integrity_check').fetchone()[0] == 'ok'
    conn.close()
    os.replace(temporary, target)
    os.chmod(target, 0o644)
    print(f'{len(rows)} lignes publiques indexées ; source conservée.')
finally:
    Path(temporary).unlink(missing_ok=True)
