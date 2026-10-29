import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {DatabaseSync} from 'node:sqlite';
import ts from 'typescript';
import * as domain from '../lib/domain.mjs';
async function harness(){
const sqlite=new DatabaseSync(':memory:');sqlite.exec(fs.readFileSync(new URL('../drizzle/0000_previous_professor_monster.sql',import.meta.url),'utf8'));
let user={userId:'qa-owner-a'};
const db = {
 prepare(sql) {
  return {
   bind(...args) {
    const q = sqlite.prepare(sql);
    return {
     async first() { return q.get(...args) || null; },
     async run() { const result = q.run(...args); return { meta: { changes: Number(result.changes) } }; }
    };
   }
  };
 }
};
const context=vm.createContext({Response,Request,URL,console,Date,JSON,Number,crypto});
const mocks=new Map();
function mock(name,exports){const m=new vm.SyntheticModule(Object.keys(exports),function(){for(const [k,v]of Object.entries(exports))this.setExport(k,v)},{context,identifier:name});mocks.set(name,m);return m;}
mock('cloudflare:workers',{env:{DB:db}});mock('../../../chatgpt-auth',{getChatGPTUser:async()=>user});mock('../../../../lib/domain.mjs',domain);
const source=ts.transpileModule(fs.readFileSync(new URL('../app/api/v1/state/route.ts',import.meta.url),'utf8'),{compilerOptions:{target:ts.ScriptTarget.ES2022,module:ts.ModuleKind.ESNext}}).outputText;
const mod=new vm.SourceTextModule(source,{context});await mod.link(spec=>{if(!mocks.has(spec))throw Error(spec);return mocks.get(spec)});await mod.evaluate();
const post=(action,revision=0,extra={})=>mod.namespace.POST(new Request('https://demo.test/api/v1/state',{method:'POST',headers:{'content-type':'application/json',origin:'https://demo.test',...extra},body:JSON.stringify({revision,action})}));
const action=(type,payload)=>({type,payload,operationId:crypto.randomUUID()});
return {get:()=>mod.namespace.GET(),post,action,setUser:u=>{user=u},sqlite};}
test('API : lecture sans connexion, sans écriture de workspace',async()=>{const h=await harness();h.setUser(null);const r=await h.get();assert.equal(r.status,200);const v=await r.json();assert.equal(v.authenticated,false);assert.equal(v.state.citizens.length,9);assert.equal(h.sqlite.prepare('SELECT count(*) AS n FROM workspaces').get().n,0)});
test('API : refus de toute mutation anonyme',async()=>{const h=await harness();h.setUser(null);const r=await h.post(h.action('citizen.consent',{citizenId:'u1',consent:true}));assert.equal(r.status,401)});
test('API : origine tierce refusée',async()=>{const h=await harness();const r=await h.post(h.action('citizen.consent',{citizenId:'u1',consent:true}),0,{origin:'https://evil.test'});assert.equal(r.status,403)});
test('API : JSON obligatoire',async()=>{const h=await harness();assert.equal((await h.post(h.action('citizen.consent',{}),0,{'content-type':'text/plain'})).status,415)});
test('API : persistance réelle SQLite et séparation de propriétaires',async()=>{const h=await harness();await h.get();const r=await h.post(h.action('citizen.consent',{citizenId:'u1',consent:true}));assert.equal(r.status,200);assert.equal((await (await h.get()).json()).state.citizens[0].consent,true);h.setUser({userId:'qa-owner-b'});const other=await(await h.get()).json();assert.equal(other.state.citizens[0].consent,false);assert.equal(other.revision,0)});
test('API : révision obsolète refusée sans écrasement',async()=>{const h=await harness();await h.post(h.action('citizen.consent',{citizenId:'u1',consent:true}));const r=await h.post(h.action('citizen.consent',{citizenId:'u2',consent:true}));assert.equal(r.status,409);const v=await(await h.get()).json();assert.equal(v.revision,1);assert.equal(v.state.citizens[1].consent,false)});
test('API : deux requêtes concurrentes, une seule écriture acceptée',async()=>{const h=await harness();await h.get();const actions=[h.action('citizen.consent',{citizenId:'u1',consent:true}),h.action('citizen.consent',{citizenId:'u2',consent:true})];const responses=await Promise.all(actions.map(a=>h.post(a)));assert.deepEqual(responses.map(r=>r.status).sort(),[200,409]);const v=await(await h.get()).json();assert.equal(v.revision,1);assert.equal(v.state.events.length,1);assert.equal((await domain.verifySnapshot(v.state)).valid,true)});
test('API : vote, changement, décompte et contrôle d’intégrité',async()=>{const h=await harness();await h.post(h.action('citizen.consent',{citizenId:'u1',consent:true}));const r=await h.post(h.action('vote.cast',{citizenId:'u1',consultationId:'c1',choice:'pour'}),1);assert.equal(r.status,200);let v=await r.json();assert.equal(v.state.consultations[0].tally.pour,2);assert.equal(v.state.keyPair,undefined);const changed=await h.post(h.action('vote.cast',{citizenId:'u1',consultationId:'c1',choice:'contre'}),2);v=await changed.json();assert.equal(v.state.consultations[0].tally.total,2);assert.equal(v.state.consultations[0].tally.contre,1);assert.equal((await domain.verifySnapshot(v.state)).valid,true)});
test('API : rejeu refusé à révision courante',async()=>{const h=await harness();const a=h.action('citizen.consent',{citizenId:'u1',consent:true});await h.post(a,0);const r=await h.post(a,1);assert.equal(r.status,409);assert.match((await r.json()).error,/rejeu/)});
test('API : données hostiles restent du texte, aucune requête SQL injectée',async()=>{const h=await harness();const name="<img src=x onerror=alert(1)> '; DROP TABLE workspaces; --";const r=await h.post(h.action('citizen.create',{name,territoryId:'t1'}));assert.equal(r.status,200);const v=await r.json();assert.equal(v.state.citizens.at(-1).name,name);assert.equal(h.sqlite.prepare('SELECT count(*) AS n FROM workspaces').get().n,1)});
test('API : exposition publique ne contient ni identifiant de connexion ni clé privée',async()=>{const h=await harness();await h.post(h.action('citizen.consent',{citizenId:'u1',consent:true}));const v=await(await h.get()).json();const json=JSON.stringify(v);assert.equal(json.includes('qa-owner-a'),false);assert.equal(json.includes('privateKey'),false);assert.equal(json.includes('"d":'),false)});
