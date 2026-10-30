import urllib.request,urllib.parse,json,hashlib,gzip
from pathlib import Path
base='https://opendatamef.outscale-euw2.opendatasoft.com/api/explore/v2.1/catalog/datasets/balances-comptables-des-communes-en-2025/exports/json'
where="siren in ('217500016','211300553','217603513')"
u=base+'?'+urllib.parse.urlencode({'where':where,'limit':-1})
try:
 with urllib.request.urlopen(u,timeout=45) as r:b=r.read()
 rows=json.loads(b)
 out={'publisher':'DGFiP','source':u,'retrievedAt':'2026-10-30','sha256':hashlib.sha256(b).hexdigest(),'coverage':'Balances comptables annuelles, pas un journal de mandats ou de factures. Colonnes budgétaires et non budgétaires distinctes. Ne pas additionner tous les comptes.', 'rows':rows}
 p=Path(__file__).resolve().parents[1] / 'data' / 'research' / 'municipal-balances-2025.json.gz';p.write_bytes(gzip.compress(json.dumps(out,ensure_ascii=False).encode()))
 print('Lignes DGFiP',len(rows));print({s:sum(r['siren']==s for r in rows) for s in ['217500016','211300553','217603513']})
except Exception as e:print(type(e).__name__,str(e)[:300])
