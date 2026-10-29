import {readFile} from 'node:fs/promises';
const file=process.argv[2];if(!file){console.error('Usage: node tools/recount.mjs consultation.json');process.exit(2)}
const s=JSON.parse(await readFile(file,'utf8'));
const seen=new Set(),counts={pour:0,contre:0,abstention:0};
for(const vote of s.votes){if(seen.has(vote.citizenId))throw Error('Identité active dupliquée');if(!s.eligibleIds.includes(vote.citizenId))throw Error('Identité non admissible');if(!(vote.choice in counts))throw Error('Choix non reconnu');seen.add(vote.citizenId);counts[vote.choice]++;}
const total=seen.size,expressed=counts.pour+counts.contre,participation=s.eligibleIds.length?total/s.eligibleIds.length*100:0;
console.log(JSON.stringify({...counts,total,expressed,eligible:s.eligibleIds.length,participation,quorumReached:participation>=s.rules.quorum,adopted:participation>=s.rules.quorum&&expressed>0&&counts.pour>counts.contre},null,2));
