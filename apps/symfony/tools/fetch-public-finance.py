import urllib.request,urllib.parse,json,hashlib,datetime
from pathlib import Path
root=Path(__file__).resolve().parents[1] / 'data' / 'research'
base='https://data.ofgl.fr/api/explore/v2.1/catalog/datasets/ofgl-base-communes-consolidee/records'
params={'where':"year(exer)=2025 AND com_code IN ('75056','13055','76351')",'limit':100,'offset':0}
rows=[];requests=[]
while True:
 url=base+'?'+urllib.parse.urlencode(params)
 with urllib.request.urlopen(url,timeout=30) as r: data=r.read()
 d=json.loads(data);rows+=d['results'];requests.append({'url':url,'sha256':hashlib.sha256(data).hexdigest()})
 if len(rows)>=d['total_count']: break
 params['offset']+=100
out={'publisher':'OFGL, calculs à partir des balances DGFiP','dataset':'ofgl-base-communes-consolidee','year':2025,'retrievedAt':'2026-10-30','unit':'EUR','source':'https://data.ofgl.fr/explore/dataset/ofgl-base-communes-consolidee/','coverage':'Agrégats consolidés des budgets communaux, pas de transactions ou factures individuelles ; Paris inclut son périmètre communal et départemental. Métropoles, régions, État et organismes sociaux non consolidés ici.','requests':requests,'rows':rows}
(root/'municipal-finance-2025.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n')
print('Agrégats reçus:',len(rows))
for code in ['75056','13055','76351']:
 print(code,[(r['agregat'],r['montant'],r['ptot']) for r in rows if r['com_code']==code and r['agregat'] in ['Dépenses totales','Frais de personnel','Charges financières',"Remboursements d'emprunts hors GAD",'Impôts et taxes']])
