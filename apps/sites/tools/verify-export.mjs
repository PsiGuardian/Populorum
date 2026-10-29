import {readFile} from 'node:fs/promises';
import {verify,verifySnapshot} from '../lib/domain.mjs';
const path=process.argv[2];if(!path){console.error('Usage: node tools/verify-export.mjs chemin-export.json');process.exit(2)}
try{const s=JSON.parse(await readFile(path,'utf8'));const result=s.schemaVersion?await verifySnapshot(s):await verify(s.events,s.publicKey);console.log(JSON.stringify(result,null,2));process.exitCode=result.valid?0:1}catch(e){console.error(e.message);process.exitCode=1}
