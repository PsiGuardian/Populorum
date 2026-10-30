#!/usr/bin/env python3
"""Add isolated PHP-process measurements to a benchmark-pages.php JSON report."""
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
database = root / 'var' / ('perf-' + secrets.token_hex(8) + '.sqlite')
shutil.copyfile(root / 'data/populorum-demo.sqlite', database)

def call(path):
    start = time.perf_counter_ns()
    result = subprocess.run([args.php, str(root / 'tools/benchmark-pages.php'), '--child=' + base64.b64encode(path.encode()).decode(), '--database=' + str(database)], cwd=root, capture_output=True, text=True, check=True)
    elapsed = (time.perf_counter_ns() - start) / 1e6
    return elapsed, json.loads(result.stdout)

def stats(values):
    values = sorted(values)
    return {'samples':len(values), 'medianMs':round(values[len(values)//2], 3), 'p95Ms':round(values[math.ceil(len(values)*.95)-1], 3), 'minMs':round(min(values),3), 'maxMs':round(max(values),3)}

try:
    call('/')  # Create the same fictional fixture; no setup cost mixed into per-page samples.
    for page in report['pages']:
        rows = [call(page['path']) for _ in range(max(1, min(args.samples, 10))) ]
        page['freshProcess'] = stats([elapsed for elapsed,_ in rows])
        page['freshHandle'] = stats([row['handleMs'] for _,row in rows])
        page['freshStatuses'] = sorted(set(row['status'] for _,row in rows))
    report['scope'] = 'Local séquentiel demo/debug=0. Chaud : HttpKernel réutilisé. Processus frais : nouveau PHP avec caches Symfony déjà compilés, durée murale du processus incluse. Exclut HTTP, navigateur, concurrence et PHP-FPM. Phases inclusives : ne pas les additionner.'
    report_path.write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
    print(str(len(report['pages'])) + ' routes avec processus PHP frais mesurées.')
finally:
    for path in [database, Path(str(database)+'-wal'), Path(str(database)+'-shm')]:
        path.unlink(missing_ok=True)
