#!/usr/bin/env python3
"""Fresh PHP measurements for scaled reports; sequential processes, disposable SQLite only."""
import argparse, base64, json, math, secrets, shutil, subprocess, time
from pathlib import Path
parser = argparse.ArgumentParser()
parser.add_argument('--php', default='php')
parser.add_argument('--report', required=True)
parser.add_argument('--samples', type=int, default=3)
args = parser.parse_args()
root = Path(__file__).resolve().parents[1]
report_path = Path(args.report)
report = json.loads(report_path.read_text())
factor = int(report.get('scale', 1))
neighbors = int(report.get('neighbors', 0))
database = root / 'var' / ('perf-' + secrets.token_hex(8) + '.sqlite')
shutil.copyfile(root / 'data/populorum-demo.sqlite', database)
setup = r'''
require 'tools/bootstrap.php'; require 'tools/scale-fixture.php';
$_SERVER['POPULORUM_SCENARIO'] = '';
$c = Doctrine\DBAL\DriverManager::getConnection(['driver'=>'pdo_sqlite','path'=>$argv[1]]);
$d = new App\Service\DemoDatabase($c, getcwd(), new App\Service\RelationalProjection($c));
$w = $d->workspace('performance-lab');
if ((int)$argv[2] > 1) {$d->save('performance-lab', 0, performanceScaleFixture($w['state'], (int)$argv[2]));}
for ($n=1; $n<=(int)$argv[3]; ++$n) {$d->workspace('neighbor-'.$n);}
$c->close();
'''
def stats(values):
    values = sorted(values)
    return {'samples':len(values), 'medianMs':round(values[len(values)//2],3), 'p95Ms':round(values[math.ceil(len(values)*.95)-1],3), 'minMs':round(min(values),3), 'maxMs':round(max(values),3)}
try:
    subprocess.run([args.php, '-r', setup, str(database), str(factor), str(neighbors)], cwd=root, check=True, capture_output=True)
    # All main sections and read/detail APIs; action GETs covered by warm tests.
    for page in report['pages']:
        if page['label'].startswith('Action : '): continue
        rows = []
        for _ in range(max(1, min(args.samples, 10))):
            start = time.perf_counter_ns()
            result = subprocess.run([args.php, 'tools/benchmark-pages.php', '--child='+base64.b64encode(page['path'].encode()).decode(), '--database='+str(database)], cwd=root, capture_output=True, text=True, check=True)
            rows.append(((time.perf_counter_ns()-start)/1e6, json.loads(result.stdout)))
        page['freshProcess'] = stats([elapsed for elapsed, _ in rows])
        page['freshHandle'] = stats([row['handleMs'] for _, row in rows])
        page['freshStatuses'] = sorted(set(row['status'] for _,row in rows))
        report_path.write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
    print(f'{factor}× : processus frais mesurés.', flush=True)
finally:
    for path in [database,Path(str(database)+'-wal'),Path(str(database)+'-shm')]:path.unlink(missing_ok=True)
