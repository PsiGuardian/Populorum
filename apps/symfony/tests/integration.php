<?php
declare(strict_types=1);
require dirname(__DIR__).'/tools/bootstrap.php';
use App\Service\SimulationEngine;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

$count=0;$failed=0;$results=[];
function check(bool $ok,string $label):void{global $count,$failed,$results;$count++;if(!$ok)$failed++;$results[]=($ok?'PASS':'FAIL').' — '.$label;}
function rejects(callable $fn,string $label):void{try{$fn();check(false,$label);}catch(DomainException){check(true,$label);}}
$engine=new SimulationEngine();$seed=json_decode(file_get_contents(dirname(__DIR__).'/data/scenario.json'),true,512,JSON_THROW_ON_ERROR);$seed['keyPair']=null;
$ready=$seed;foreach($ready['citizens'] as &$c)$c['consent']=true;unset($c);
$op=static fn()=>bin2hex(random_bytes(16));$apply=static fn(array $s,string $type,array $p)=>$engine->apply($s,$type,$p,$op());
try{
 check(array_column($seed['territories'],'name')===['Paris','Marseille','Le Havre'],'Exactement les trois villes demandées');
 check(array_column($seed['territories'],'code')===['75056','13055','76351'],'Codes communaux INSEE cohérents');
 check(count($seed['citizens'])===9,'Neuf personnages fictifs initiaux');
 check(count($seed['proposals'][0]['nodes'])===12,'Douze catégories causales');
 check($seed['proposals'][0]['nodes'][4]['kind']==='observation'&&$seed['proposals'][0]['nodes'][3]['kind']==='hypothesis','Observation et hypothèse distinctes');
 rejects(fn()=>$apply($seed,'vote.cast',['citizenId'=>'u1','consultationId'=>'c1','choice'=>'pour']),'Vote sans consentement refusé');
 rejects(fn()=>$apply($ready,'vote.cast',['citizenId'=>'u4','consultationId'=>'c1','choice'=>'pour']),'Vote hors territoire refusé');
 rejects(fn()=>$apply($ready,'vote.cast',['citizenId'=>'absent','consultationId'=>'c1','choice'=>'pour']),'Personnage inconnu refusé');
 foreach(['pour','contre','abstention'] as $choice){$s=$apply($ready,'vote.cast',['citizenId'=>'u1','consultationId'=>'c1','choice'=>$choice]);$mine=array_values(array_filter($s['votes'],fn($v)=>$v['citizenId']==='u1'));check($mine[0]['choice']===$choice,'Vote '.$choice.' enregistré');check($engine->verify($engine->publicState($s))['valid'],'Signature et état vérifiés pour '.$choice);}
 $s=$apply($ready,'vote.cast',['citizenId'=>'u1','consultationId'=>'c1','choice'=>'pour']);
 $s=$apply($s,'vote.cast',['citizenId'=>'u1','consultationId'=>'c1','choice'=>'contre']);$t=$engine->tally($s['consultations'][0],$s['votes']);check($t['total']===2&&$t['pour']===1&&$t['contre']===1,'Changement sans double comptage');check(!$t['adopted'],'Égalité non adoptée');
 rejects(fn()=>$apply($s,'vote.cast',['citizenId'=>'u1','consultationId'=>'c1','choice'=>'contre']),'Vote identique refusé');
 check(count($s['votes'][3]['history'])===1,'Historique de changement conservé');
 $public=$engine->publicState($s);check(!isset($public['keyPair'])&&!isset($public['operations']),'Clés privées et anti-rejeu exclus des exports');
 check(!str_contains(json_encode($public),'PRIVATE KEY'),'Aucune clé privée dans le JSON public');
 $bad=$public;$bad['votes'][0]['choice']='abstention';check(!$engine->verify($bad)['valid'],'Falsification de l’état détectée');
 $bad=$public;$bad['events'][1]['previous']='faux';check(!$engine->verify($bad)['valid'],'Rupture de chaîne détectée');
 $bad=$public;$bad['events'][0]['signature']='AAAA';check(!$engine->verify($bad)['valid'],'Fausse signature détectée');
 $bad=$s;$bad['votes'][0]['choice']='abstention';rejects(fn()=>$apply($bad,'citizen.consent',['citizenId'=>'u1','consent'=>false]),'Mutation d’un état corrompu interdite');
 $oid=$op();$s=$engine->apply($ready,'citizen.consent',['citizenId'=>'u1','consent'=>true],$oid);rejects(fn()=>$engine->apply($s,'citizen.consent',['citizenId'=>'u1','consent'=>true],$oid),'Rejeu refusé');
 rejects(fn()=>$apply($ready,'proposal.revise',['citizenId'=>'u1','proposalId'=>'p1','reason'=>'Nouvelle idée','solution'=>'Autre solution']),'Texte gelé non révisable');
 rejects(fn()=>$apply($ready,'proposal.revise',['citizenId'=>'u1','proposalId'=>'p2','reason'=>'Nouvelle idée','solution'=>'Autre solution']),'Révision par un autre auteur refusée');
 $s=$apply($ready,'proposal.revise',['citizenId'=>'u7','proposalId'=>'p3','reason'=>'Contexte réexaminé','solution'=>'Nouvelle solution locale']);check($s['proposals'][2]['version']===2&&$s['proposals'][2]['revisions'][0]['version']===1,'Version précédente conservée');check($engine->verify(json_decode(json_encode($s),true))['valid'],'Signature stable après sérialisation JSON');
 $s=$apply($ready,'consultation.open',['citizenId'=>'u7','proposalId'=>'p3','days'=>14,'quorum'=>50]);check($s['consultations'][2]['frozen']['solution']===$ready['proposals'][2]['solution'],'Texte figé à l’ouverture');check($s['consultations'][2]['eligibleIds']===['u7','u8','u9'],'Admissibilité locale figée');
 $incomplete=$ready;$incomplete['proposals'][2]['indicator']='';rejects(fn()=>$apply($incomplete,'consultation.open',['citizenId'=>'u7','proposalId'=>'p3','days'=>14,'quorum'=>50]),'Consultation incomplète refusée');
 $s=$apply($ready,'consultation.close',['citizenId'=>'u1','consultationId'=>'c1','reason'=>'Fin pédagogique']);rejects(fn()=>$apply($s,'vote.cast',['citizenId'=>'u1','consultationId'=>'c1','choice'=>'pour']),'Vote après clôture refusé');
 $c=$ready['consultations'][0];$c['eligibleIds']=[];check($engine->tally($c,[])['participation']===0,'Consultation vide sans division par zéro');
 rejects(fn()=>$apply($ready,'citizen.update',['citizenId'=>'u1','territoryId'=>'t2']),'Changement territorial différé durant vote ouvert');
 $s=$apply($ready,'citizen.create',['citizenId'=>'u1','name'=>'Personnage inédit fictif','territoryId'=>'t1']);check(count($s['citizens'])===10,'Création d’un personnage');rejects(fn()=>$apply($s,'citizen.create',['citizenId'=>'u1','name'=>'Personnage inédit fictif','territoryId'=>'t1']),'Personnage dupliqué refusé');
 $withdraw=$apply($ready,'citizen.withdraw',['citizenId'=>'u1']);check(!$withdraw['citizens'][0]['consent'],'Retrait de consentement enregistré');rejects(fn()=>$apply($withdraw,'vote.cast',['citizenId'=>'u1','consultationId'=>'c1','choice'=>'pour']),'Vote après retrait de consentement refusé');rejects(fn()=>$apply($ready,'citizen.update',['citizenId'=>'u1','name'=>$ready['citizens'][1]['name']]),'Rectification vers un nom fictif existant refusée');
 $s=$apply($ready,'citizen.erase',['citizenId'=>'u1']);check($s['citizens'][0]['name']==='Identité fictive effacée','Effacement du nom fictif');
 $s=$apply($ready,'observation.add',['citizenId'=>'u4','experimentId'=>'e1','date'=>'2026-10-30','value'=>100,'uncertainty'=>4,'source'=>'Source pédagogique documentée','external'=>'Météo simulée favorable','unexpected'=>'Report de trafic simulé']);check($s['experiments'][0]['observations'][1]['simulated'],'Observation explicitement simulée');check($s['experiments'][0]['observations'][1]['unexpected']==='Report de trafic simulé','Imprévu daté et conservé');
 rejects(fn()=>$apply($ready,'observation.add',['citizenId'=>'u1','experimentId'=>'e1']),'Observation par non-local refusée');
 rejects(fn()=>$apply($ready,'observation.add',['citizenId'=>'u4','experimentId'=>'e1','date'=>'2020-01-01','value'=>100,'uncertainty'=>4,'source'=>'Source pédagogique','external'=>'Facteur simulé']),'Observation hors période refusée');
 rejects(fn()=>$apply($ready,'observation.add',['citizenId'=>'u4','experimentId'=>'e1','date'=>'2026-10-30','value'=>100,'uncertainty'=>4,'source'=>'','external'=>'Facteur simulé']),'Observation sans source refusée');
 $s=$apply($ready,'finance.add',['citizenId'=>'u1','type'=>'expense','amount'=>'12.34','label'=>'Dépense pédagogique','receipt'=>'SIM-JUSTIF-001']);check($s['finance'][2]['cents']===1234,'Montant financier en centimes');rejects(fn()=>$apply($ready,'finance.add',['citizenId'=>'u1','type'=>'expense','amount'=>'12.345','label'=>'Dépense pédagogique','receipt'=>'SIM-JUSTIF-001']),'Montant à trois décimales refusé');
 $s=$apply($ready,'moderation.report',['citizenId'=>'u1','objectId'=>'u2','reason'=>'Faits pédagogiques à examiner']);check(!array_filter($s['citizens'],fn($u)=>$u['suspended']),'Signalement sans sanction automatique');$s=$apply($s,'moderation.appeal',['citizenId'=>'u2','caseId'=>$s['cases'][0]['id'],'reason'=>'Demande motivée de réexamen']);check(count($s['appeals'])===1,'Recours enregistré');
 $s=$apply($ready,'governance.propose',['citizenId'=>'u1','title'=>'Évolution pédagogique','reason'=>'Garanties mieux documentées','risk'=>'Risque à examiner','rollback'=>'Retour à la version initiale']);$gid=$s['governance'][0]['id'];rejects(fn()=>$apply($s,'governance.approve',['citizenId'=>'u1','governanceId'=>$gid]),'Auto-approbation interdite');$s=$apply($s,'governance.approve',['citizenId'=>'u2','governanceId'=>$gid]);$s=$apply($s,'governance.approve',['citizenId'=>'u3','governanceId'=>$gid]);check($s['governance'][0]['status']==='reviewed','Deux avis indépendants');check($s['consultations']===$ready['consultations'],'Gouvernance sans mutation rétroactive des règles');
 rejects(fn()=>$apply($ready,'node.sync',['citizenId'=>'u1','nodeId'=>'node1']),'Relais arrêté ne synchronise pas');$s=$apply($ready,'node.configure',['citizenId'=>'u1','nodeId'=>'node1','bandwidth'=>100,'enabled'=>true]);$s=$apply($s,'node.sync',['citizenId'=>'u1','nodeId'=>'node1']);check($s['nodes'][0]['checkpoint']['publicOnly'],'Point de contrôle public virtuel');

 // Vrais gestionnaires Symfony, vrais formulaires CSRF et véritable SQLite via Doctrine.
 $temp=dirname(__DIR__).'/var/test-'.bin2hex(random_bytes(5)).'.sqlite';copy(dirname(__DIR__).'/data/populorum-demo.sqlite',$temp);$_SERVER['DATABASE_URL']=$_ENV['DATABASE_URL']='sqlite:///'.$temp;
 $kernel=new App\Kernel('demo',false);$session=new Session(new MockArraySessionStorage());
 $request=function(string $path,string $method='GET',array $params=[],array $cookies=[])use($kernel,$session):Response{$r=Request::create('https://demo.example'.$path,$method,$params,$cookies,[],['HTTPS'=>'on','HTTP_HOST'=>'demo.example']);$r->setSession($session);$res=$kernel->handle($r);$kernel->terminate($r,$res);return $res;};
 foreach(['/','/territories','/proposals','/proposals?id=p1','/consultations','/experiments','/convergence','/memory','/transparency','/infrastructure','/governance','/citizen','/charter','/charter?locale=en_GB','/verify'] as $path){$r=$request($path);check($r->getStatusCode()===200,'Symfony/Twig rend '.$path);if($r->getStatusCode()!==200)file_put_contents(dirname(__DIR__).'/var/failed-page.html',$r->getContent());}
 $r=$request('/api/v1/state');$api=json_decode($r->getContent(),true);check($r->getStatusCode()===200&&count($api['state']['territories'])===3,'API PHP lit SQLite via Doctrine');check(!str_contains($r->getContent(),'PRIVATE KEY'),'API n’expose pas de clé privée');
 $r=$request('/_profiler/');check($r->getStatusCode()===404,'Profiler refusé sans session Guardian');
 $r=$request('/_wdt/faux');check($r->getStatusCode()===404,'Toolbar refusée sans session Guardian');
 $r=$request('/');check(!$r->headers->has('X-Debug-Token')&&!str_contains($r->getContent(),'sf-toolbar'),'Pas de collecte ni toolbar pour le visiteur');
 $r=$request('/demo-profiler-shield-populorum');check($r->getStatusCode()===200&&str_contains($r->getContent(),'name="username"'),'Portail réel du bundle installé');
 preg_match('/name="_csrf" value="([^"]+)"/',$r->getContent(),$m);$cookies=[];foreach($r->headers->getCookies() as $cookie)$cookies[$cookie->getName()]=$cookie->getValue();check(isset($m[1]),'CSRF de connexion Guardian présent');
 $r=$request('/demo-profiler-shield-populorum','POST',['action'=>'login','username'=>'demo','password'=>App\Command\DemoSetupCommand::PUBLIC_PASSWORD,'_csrf'=>$m[1]??''],$cookies);foreach($r->headers->getCookies() as $cookie)$cookies[$cookie->getName()]=$cookie->getValue();check($r->isRedirect(),'Connexion Guardian publique fonctionnelle');
 $r=$request('/','GET',[],$cookies);check($r->getStatusCode()===200&&$r->headers->has('X-Debug-Token'),'Collecte Symfony autorisée après connexion Guardian');
 $token=$r->headers->get('X-Debug-Token');$r=$request('/_profiler/','GET',[],$cookies);check(in_array($r->getStatusCode(),[200,302]),'Index du profiler accessible après connexion');
 if($token){$r=$request('/_profiler/'.$token,'GET',[],$cookies);check($r->getStatusCode()===200,'Rapport natif du profiler consultable');}
 $r=$request('/demo-profiler-shield-populorum','POST',['action'=>'password_change','new_password'=>'autre'],$cookies);check($r->getStatusCode()===403,'Mot de passe partagé non modifiable à distance');
 $r=$request('/demo-profiler-shield-populorum','POST',['action'=>'settings'],$cookies);check($r->getStatusCode()===403,'Règles du bundle non modifiables par le compte public');
 $r=$request('/demo-profiler-shield-populorum','POST',['action'=>'login','username'=>'operator','password'=>'faux'],$cookies);check($r->getStatusCode()===403,'Connexion web de l’opérateur privée interdite sur la démo');
 $r=$request('/_profiler/phpinfo','GET',[],$cookies);check($r->getStatusCode()===404,'PHPInfo fermé même au compte de démo');
 $r=$request('/persona','POST',['citizen'=>'u4','_token'=>'faux']);check($r->getStatusCode()===403,'Changement de personnage sans CSRF refusé');
 $r=$request('/action/citizen.consent');check($r->getStatusCode()===200,'Formulaire Symfony natif accessible');preg_match_all('/<input[^>]+name="simulation_action\[([^\]]+)\]"[^>]*value="([^"]*)"/',$r->getContent(),$matches,PREG_SET_ORDER);$fields=[];foreach($matches as $match)$fields[$match[1]]=html_entity_decode($match[2],ENT_QUOTES);$fields['consent']='1';$r=$request('/action/citizen.consent','POST',['simulation_action'=>$fields]);check($r->isRedirect(),'Formulaire CSRF validé et état persisté');$r=$request('/api/v1/state');$api=json_decode($r->getContent(),true);check($api['state']['citizens'][0]['consent']===true,'Consentement relu depuis SQLite');
 $r=$request('/action/citizen.consent','POST',['simulation_action'=>$fields]);check($r->getStatusCode()===409,'Soumission périmée refusée par verrouillage optimiste');
 $r=$request('/action/citizen.consent','POST',['simulation_action'=>array_merge($fields,['_token'=>'faux'])]);check($r->getStatusCode()===400,'Formulaire au CSRF invalide refusé');
 $r=$request('/export/proofs');check($r->getStatusCode()===200&&str_contains($r->headers->get('Content-Disposition'),'attachment'),'Export de preuve téléchargeable');$export=json_decode($r->getContent(),true);check($engine->verify($export)['valid'],'Export public vérifiable après persistance réelle');
 $db=new PDO('sqlite:'.$temp);check((int)$db->query('SELECT COUNT(*) FROM municipalities')->fetchColumn()===3,'Aucune ville supplémentaire créée par les parcours');check($db->query('PRAGMA integrity_check')->fetchColumn()==='ok','Intégrité SQLite valide');
 $collector=new App\DataCollector\PublicDemoRequestCollector();$secret='SENTINELLE-NE-PAS-EXPOSER';$_ENV['APP_SECRET']=$secret;$_SERVER['SYMFONY_DOTENV_VARS']='APP_SECRET';$req=Request::create('https://demo.example/?token='.$secret,'POST',['_token'=>$secret],['session'=>$secret],[],['APP_SECRET'=>$secret]);$res=new Response();$res->headers->setCookie(new Symfony\Component\HttpFoundation\Cookie('session',$secret));$collector->collect($req,$res);$collector->lateCollect();check(!str_contains(serialize($collector),$secret),'Collecteur public expurge clés, cookies, corps et jetons');
 $kernel->shutdown();unset($db);unlink($temp);
}catch(Throwable $e){check(false,'Exception inattendue : '.$e::class.' — '.$e->getMessage());file_put_contents(dirname(__DIR__).'/var/test-exception.txt',(string)$e);}
file_put_contents(dirname(__DIR__).'/var/test-results.txt',implode("\n",$results)."\n$count contrôles, $failed échec(s).\n");echo implode("\n",$results)."\n$count contrôles, $failed échec(s).\n";exit($failed?1:0);
