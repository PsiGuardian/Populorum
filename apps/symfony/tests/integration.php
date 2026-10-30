<?php

declare(strict_types=1);
require dirname(__DIR__) . '/tools/bootstrap.php';
use App\Service\SimulationEngine;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

$_SERVER['POPULORUM_SCENARIO'] = 'reference';
$assertionCount = 0;
$failureCount = 0;
$results = [];
function check(bool $passed, string $label): void
{
    global $assertionCount, $failureCount, $results;
    ++$assertionCount;
    if (!$passed) {
        ++$failureCount;
    }
    $results[] = ($passed ? 'PASS' : 'FAIL') . ' — ' . $label;
}
function rejects(callable $operation, string $label): void
{
    try {
        $operation();
        check(false, $label);
    } catch (DomainException) {
        check(true, $label);
    }
}
$engine = new SimulationEngine();
$initialState = json_decode(
    file_get_contents(dirname(__DIR__) . '/data/reference-scenario.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
$initialState['keyPair'] = null;
$participatingState = $initialState;
foreach ($participatingState['citizens'] as &$c) {
    $c['consent'] = true;
}
unset($c);
$newOperationId = static fn () => bin2hex(random_bytes(16));
$applyAction = static fn (array $state, string $type, array $payload) => $engine->apply($state, $type, $payload, $newOperationId());
try {
    check(
        array_column($initialState['territories'], 'name') === ['Paris', 'Marseille', 'Le Havre'],
        'Exactement les trois villes demandées',
    );
    check(
        array_column($initialState['territories'], 'code') === ['75056', '13055', '76351'],
        'Codes communaux INSEE cohérents',
    );
    check(count($initialState['citizens']) === 9, 'Neuf personnages fictifs initiaux');
    check(count($initialState['proposals'][0]['nodes']) === 12, 'Douze catégories causales');
    check(
        $initialState['proposals'][0]['nodes'][4]['kind'] === 'observation'
            && $initialState['proposals'][0]['nodes'][3]['kind'] === 'hypothesis',
        'Observation et hypothèse distinctes',
    );
    rejects(
        fn () => $applyAction($initialState, 'vote.cast', [
            'citizenId' => 'u1',
            'consultationId' => 'c1',
            'choice' => 'pour',
        ]),
        'Vote sans consentement refusé',
    );
    rejects(
        fn () => $applyAction($participatingState, 'vote.cast', [
            'citizenId' => 'u4',
            'consultationId' => 'c1',
            'choice' => 'pour',
        ]),
        'Vote hors territoire refusé',
    );
    rejects(
        fn () => $applyAction($participatingState, 'vote.cast', [
            'citizenId' => 'absent',
            'consultationId' => 'c1',
            'choice' => 'pour',
        ]),
        'Personnage inconnu refusé',
    );
    foreach (['pour', 'contre', 'abstention'] as $choice) {
        $state = $applyAction($participatingState, 'vote.cast', [
            'citizenId' => 'u1',
            'consultationId' => 'c1',
            'choice' => $choice,
        ]);
        $mine = array_values(array_filter($state['votes'], fn ($v) => $v['citizenId'] === 'u1'));
        check($mine[0]['choice'] === $choice, 'Vote ' . $choice . ' enregistré');
        check(
            $engine->verify($engine->publicState($state))['valid'],
            'Signature et état vérifiés pour ' . $choice,
        );
    }
    $state = $applyAction($participatingState, 'vote.cast', ['citizenId' => 'u1', 'consultationId' => 'c1', 'choice' => 'pour']);
    $state = $applyAction($state, 'vote.cast', ['citizenId' => 'u1', 'consultationId' => 'c1', 'choice' => 'contre']);
    $tally = $engine->tally($state['consultations'][0], $state['votes']);
    check($tally['total'] === 2 && $tally['pour'] === 1 && $tally['contre'] === 1, 'Changement sans double comptage');
    check(!$tally['adopted'], 'Égalité non adoptée');
    rejects(
        fn () => $applyAction($state, 'vote.cast', [
            'citizenId' => 'u1',
            'consultationId' => 'c1',
            'choice' => 'contre',
        ]),
        'Vote identique refusé',
    );
    check(count($state['votes'][3]['history']) === 1, 'Historique de changement conservé');
    $publicState = $engine->publicState($state);
    check(
        !isset($publicState['keyPair']) && !isset($publicState['operations']),
        'Clés privées et anti-rejeu exclus des exports',
    );
    check(!str_contains(json_encode($publicState), 'PRIVATE KEY'), 'Aucune clé privée dans le JSON public');
    $corruptedState = $publicState;
    $corruptedState['votes'][0]['choice'] = 'abstention';
    check(!$engine->verify($corruptedState)['valid'], 'Falsification de l’état détectée');
    $corruptedState = $publicState;
    $corruptedState['events'][1]['previous'] = 'faux';
    check(!$engine->verify($corruptedState)['valid'], 'Rupture de chaîne détectée');
    $corruptedState = $publicState;
    $corruptedState['events'][0]['signature'] = 'AAAA';
    check(!$engine->verify($corruptedState)['valid'], 'Fausse signature détectée');
    $corruptedState = $state;
    $corruptedState['votes'][0]['choice'] = 'abstention';
    rejects(
        fn () => $applyAction($corruptedState, 'citizen.consent', ['citizenId' => 'u1', 'consent' => false]),
        'Mutation d’un état corrompu interdite',
    );
    $operationId = $newOperationId();
    $state = $engine->apply($participatingState, 'citizen.consent', ['citizenId' => 'u1', 'consent' => true], $operationId);
    rejects(
        fn () => $engine->apply($state, 'citizen.consent', ['citizenId' => 'u1', 'consent' => true], $operationId),
        'Rejeu refusé',
    );
    rejects(
        fn () => $applyAction($participatingState, 'proposal.revise', [
            'citizenId' => 'u1',
            'proposalId' => 'p1',
            'reason' => 'Nouvelle idée',
            'solution' => 'Autre solution',
        ]),
        'Texte gelé non révisable',
    );
    rejects(
        fn () => $applyAction($participatingState, 'proposal.revise', [
            'citizenId' => 'u1',
            'proposalId' => 'p2',
            'reason' => 'Nouvelle idée',
            'solution' => 'Autre solution',
        ]),
        'Révision par un autre auteur refusée',
    );
    $state = $applyAction($participatingState, 'proposal.revise', [
        'citizenId' => 'u7',
        'proposalId' => 'p3',
        'reason' => 'Contexte réexaminé',
        'solution' => 'Nouvelle solution locale',
    ]);
    check(
        $state['proposals'][2]['version'] === 2 && $state['proposals'][2]['revisions'][0]['version'] === 1,
        'Version précédente conservée',
    );
    check(
        $engine->verify(json_decode(json_encode($state), true))['valid'],
        'Signature stable après sérialisation JSON',
    );
    $state = $applyAction($participatingState, 'consultation.open', [
        'citizenId' => 'u7',
        'proposalId' => 'p3',
        'days' => 14,
        'quorum' => 50,
    ]);
    check(
        $state['consultations'][2]['frozen']['solution'] === $participatingState['proposals'][2]['solution'],
        'Texte figé à l’ouverture',
    );
    check($state['consultations'][2]['eligibleIds'] === ['u7', 'u8', 'u9'], 'Admissibilité locale figée');
    $incomplete = $participatingState;
    $incomplete['proposals'][2]['indicator'] = '';
    rejects(
        fn () => $applyAction($incomplete, 'consultation.open', [
            'citizenId' => 'u7',
            'proposalId' => 'p3',
            'days' => 14,
            'quorum' => 50,
        ]),
        'Consultation incomplète refusée',
    );
    $state = $applyAction($participatingState, 'consultation.close', [
        'citizenId' => 'u1',
        'consultationId' => 'c1',
        'reason' => 'Fin pédagogique',
    ]);
    rejects(
        fn () => $applyAction($state, 'vote.cast', ['citizenId' => 'u1', 'consultationId' => 'c1', 'choice' => 'pour']),
        'Vote après clôture refusé',
    );
    $c = $participatingState['consultations'][0];
    $c['eligibleIds'] = [];
    check($engine->tally($c, [])['participation'] === 0, 'Consultation vide sans division par zéro');
    rejects(
        fn () => $applyAction($participatingState, 'citizen.update', ['citizenId' => 'u1', 'territoryId' => 't2']),
        'Changement territorial différé durant vote ouvert',
    );
    $state = $applyAction($participatingState, 'citizen.create', [
        'citizenId' => 'u1',
        'name' => 'Personnage inédit fictif',
        'territoryId' => 't1',
    ]);
    check(count($state['citizens']) === 10, 'Création d’un personnage');
    rejects(
        fn () => $applyAction($state, 'citizen.create', [
            'citizenId' => 'u1',
            'name' => 'Personnage inédit fictif',
            'territoryId' => 't1',
        ]),
        'Personnage dupliqué refusé',
    );
    $withdraw = $applyAction($participatingState, 'citizen.withdraw', ['citizenId' => 'u1']);
    check(!$withdraw['citizens'][0]['consent'], 'Retrait de consentement enregistré');
    rejects(
        fn () => $applyAction($withdraw, 'vote.cast', [
            'citizenId' => 'u1',
            'consultationId' => 'c1',
            'choice' => 'pour',
        ]),
        'Vote après retrait de consentement refusé',
    );
    rejects(
        fn () => $applyAction($participatingState, 'citizen.update', [
            'citizenId' => 'u1',
            'name' => $participatingState['citizens'][1]['name'],
        ]),
        'Rectification vers un nom fictif existant refusée',
    );
    $state = $applyAction($participatingState, 'citizen.erase', ['citizenId' => 'u1']);
    check($state['citizens'][0]['name'] === 'Identité fictive effacée', 'Effacement du nom fictif');
    $state = $applyAction($participatingState, 'observation.add', [
        'citizenId' => 'u4',
        'experimentId' => 'e1',
        'date' => '2026-10-30',
        'value' => 100,
        'uncertainty' => 4,
        'source' => 'Source pédagogique documentée',
        'external' => 'Météo simulée favorable',
        'unexpected' => 'Report de trafic simulé',
    ]);
    check($state['experiments'][0]['observations'][1]['simulated'], 'Observation explicitement simulée');
    check(
        $state['experiments'][0]['observations'][1]['unexpected'] === 'Report de trafic simulé',
        'Imprévu daté et conservé',
    );
    rejects(
        fn () => $applyAction($participatingState, 'observation.add', ['citizenId' => 'u1', 'experimentId' => 'e1']),
        'Observation par non-local refusée',
    );
    rejects(
        fn () => $applyAction($participatingState, 'observation.add', [
            'citizenId' => 'u4',
            'experimentId' => 'e1',
            'date' => '2020-01-01',
            'value' => 100,
            'uncertainty' => 4,
            'source' => 'Source pédagogique',
            'external' => 'Facteur simulé',
        ]),
        'Observation hors période refusée',
    );
    rejects(
        fn () => $applyAction($participatingState, 'observation.add', [
            'citizenId' => 'u4',
            'experimentId' => 'e1',
            'date' => '2026-10-30',
            'value' => 100,
            'uncertainty' => 4,
            'source' => '',
            'external' => 'Facteur simulé',
        ]),
        'Observation sans source refusée',
    );
    $state = $applyAction($participatingState, 'finance.add', [
        'citizenId' => 'u1',
        'type' => 'expense',
        'amount' => '12.34',
        'label' => 'Dépense pédagogique',
        'receipt' => 'SIM-JUSTIF-001',
    ]);
    check($state['finance'][2]['cents'] === 1234, 'Montant financier en centimes');
    rejects(
        fn () => $applyAction($participatingState, 'finance.add', [
            'citizenId' => 'u1',
            'type' => 'expense',
            'amount' => '12.345',
            'label' => 'Dépense pédagogique',
            'receipt' => 'SIM-JUSTIF-001',
        ]),
        'Montant à trois décimales refusé',
    );
    $state = $applyAction($participatingState, 'moderation.report', [
        'citizenId' => 'u1',
        'objectId' => 'u2',
        'reason' => 'Faits pédagogiques à examiner',
    ]);
    check(!array_filter($state['citizens'], fn ($u) => $u['suspended']), 'Signalement sans sanction automatique');
    $state = $applyAction($state, 'moderation.appeal', [
        'citizenId' => 'u2',
        'caseId' => $state['cases'][0]['id'],
        'reason' => 'Demande motivée de réexamen',
    ]);
    check(count($state['appeals']) === 1, 'Recours enregistré');
    $state = $applyAction($participatingState, 'governance.propose', [
        'citizenId' => 'u1',
        'title' => 'Évolution pédagogique',
        'reason' => 'Garanties mieux documentées',
        'risk' => 'Risque à examiner',
        'rollback' => 'Retour à la version initiale',
    ]);
    $gid = $state['governance'][0]['id'];
    rejects(
        fn () => $applyAction($state, 'governance.approve', ['citizenId' => 'u1', 'governanceId' => $gid]),
        'Auto-approbation interdite',
    );
    $state = $applyAction($state, 'governance.approve', ['citizenId' => 'u2', 'governanceId' => $gid]);
    $state = $applyAction($state, 'governance.approve', ['citizenId' => 'u3', 'governanceId' => $gid]);
    check($state['governance'][0]['status'] === 'reviewed', 'Deux avis indépendants');
    check(
        $state['consultations'] === $participatingState['consultations'],
        'Gouvernance sans mutation rétroactive des règles',
    );
    rejects(
        fn () => $applyAction($participatingState, 'node.sync', ['citizenId' => 'u1', 'nodeId' => 'node1']),
        'Relais arrêté ne synchronise pas',
    );
    $state = $applyAction($participatingState, 'node.configure', [
        'citizenId' => 'u1',
        'nodeId' => 'node1',
        'bandwidth' => 100,
        'enabled' => true,
    ]);
    $state = $applyAction($state, 'node.sync', ['citizenId' => 'u1', 'nodeId' => 'node1']);
    check($state['nodes'][0]['checkpoint']['publicOnly'], 'Point de contrôle public virtuel');

    // Vrais gestionnaires Symfony, vrais formulaires CSRF et véritable SQLite via Doctrine.
    $databasePath = dirname(__DIR__) . '/var/test-' . bin2hex(random_bytes(5)) . '.sqlite';
    copy(dirname(__DIR__) . '/data/populorum-demo.sqlite', $databasePath);
    $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = 'sqlite:///' . $databasePath;
    $kernel = new App\Kernel('demo', false);
    $session = new Session(new MockArraySessionStorage());
    $request = function (string $path, string $method = 'GET', array $params = [], array $cookies = []) use (
        $kernel,
        $session,
    ): Response {
        $httpRequest = Request::create(
            'https://demo.example' . $path,
            $method,
            $params,
            $cookies,
            [],
            ['HTTPS' => 'on', 'HTTP_HOST' => 'demo.example'],
        );
        $httpRequest->setSession($session);
        $httpResponse = $kernel->handle($httpRequest);
        $kernel->terminate($httpRequest, $httpResponse);

        return $httpResponse;
    };
    foreach (
        [
            '/',
            '/territories',
            '/proposals',
            '/proposals?id=p1',
            '/consultations',
            '/experiments',
            '/convergence',
            '/memory',
            '/transparency',
            '/infrastructure',
            '/governance',
            '/citizen',
            '/charter',
            '/charter?locale=en_GB',
            '/verify',
        ] as $path
    ) {
        $response = $request($path);
        check($response->getStatusCode() === 200, 'Symfony/Twig rend ' . $path);
        if ($response->getStatusCode() !== 200) {
            file_put_contents(dirname(__DIR__) . '/var/failed-page.html', $response->getContent());
        }
    }
    // Le filtre doit garder la fiche attendue, pas seulement retourner une page HTTP 200.
    foreach (['t1' => 'Paris', 't2' => 'Marseille', 't3' => 'Le Havre'] as $territoryId => $cityName) {
        $response = $request('/territories?territory=' . $territoryId);
        check(
            preg_match('/<h2>\s*' . preg_quote($cityName, '/') . '\s*<\/h2>/', $response->getContent()) === 1,
            'Le filtre territorial conserve la fiche de ' . $cityName,
        );
    }

    $response = $request('/api/v1/state');
    $apiResponse = json_decode($response->getContent(), true);
    check(
        $response->getStatusCode() === 200 && count($apiResponse['state']['territories']) === 3,
        'API PHP lit SQLite via Doctrine',
    );
    check(!str_contains($response->getContent(), 'PRIVATE KEY'), 'API n’expose pas de clé privée');
    $response = $request('/_profiler/');
    check($response->getStatusCode() === 404, 'Profiler refusé sans session Guardian');
    $response = $request('/_wdt/faux');
    check($response->getStatusCode() === 404, 'Toolbar refusée sans session Guardian');
    $response = $request('/');
    check(
        !$response->headers->has('X-Debug-Token') && !str_contains($response->getContent(), 'sf-toolbar'),
        'Pas de collecte ni toolbar pour le visiteur',
    );
    $response = $request('/demo-profiler-shield-populorum');
    check(
        $response->getStatusCode() === 200 && str_contains($response->getContent(), 'name="username"'),
        'Portail réel du bundle installé',
    );
    preg_match('/name="_csrf" value="([^"]+)"/', $response->getContent(), $loginCsrfMatch);
    $cookies = [];
    foreach ($response->headers->getCookies() as $cookie) {
        $cookies[$cookie->getName()] = $cookie->getValue();
    }
    check(isset($loginCsrfMatch[1]), 'CSRF de connexion Guardian présent');
    $response = $request(
        '/demo-profiler-shield-populorum',
        'POST',
        [
            'action' => 'login',
            'username' => 'demo',
            'password' => App\Command\DemoSetupCommand::PUBLIC_PASSWORD,
            '_csrf' => $loginCsrfMatch[1] ?? '',
        ],
        $cookies,
    );
    foreach ($response->headers->getCookies() as $cookie) {
        $cookies[$cookie->getName()] = $cookie->getValue();
    }
    check($response->isRedirect(), 'Connexion Guardian publique fonctionnelle');
    $response = $request('/', 'GET', [], $cookies);
    check(
        $response->getStatusCode() === 200 && $response->headers->has('X-Debug-Token'),
        'Collecte Symfony autorisée après connexion Guardian',
    );
    $token = $response->headers->get('X-Debug-Token');
    $response = $request('/_profiler/', 'GET', [], $cookies);
    check(in_array($response->getStatusCode(), [200, 302]), 'Index du profiler accessible après connexion');
    if ($token) {
        $response = $request('/_profiler/' . $token, 'GET', [], $cookies);
        check($response->getStatusCode() === 200, 'Rapport natif du profiler consultable');
    }
    $response = $request(
        '/demo-profiler-shield-populorum',
        'POST',
        ['action' => 'password_change', 'new_password' => 'autre'],
        $cookies,
    );
    check($response->getStatusCode() === 403, 'Mot de passe partagé non modifiable à distance');
    $response = $request('/demo-profiler-shield-populorum', 'POST', ['action' => 'settings'], $cookies);
    check($response->getStatusCode() === 403, 'Règles du bundle non modifiables par le compte public');
    $response = $request(
        '/demo-profiler-shield-populorum',
        'POST',
        ['action' => 'login', 'username' => 'operator', 'password' => 'faux'],
        $cookies,
    );
    check($response->getStatusCode() === 403, 'Connexion web de l’opérateur privée interdite sur la démo');
    $response = $request('/_profiler/phpinfo', 'GET', [], $cookies);
    check($response->getStatusCode() === 404, 'PHPInfo fermé même au compte de démo');
    $response = $request('/persona', 'POST', ['citizen' => 'u4', '_token' => 'faux']);
    check($response->getStatusCode() === 403, 'Changement de personnage sans CSRF refusé');
    $response = $request('/action/citizen.consent');
    check($response->getStatusCode() === 200, 'Formulaire Symfony natif accessible');
    preg_match_all(
        '/<input[^>]+name="simulation_action\[([^\]]+)\]"[^>]*value="([^"]*)"/',
        $response->getContent(),
        $matches,
        PREG_SET_ORDER,
    );
    $fields = [];
    foreach ($matches as $match) {
        $fields[$match[1]] = html_entity_decode($match[2], ENT_QUOTES);
    }
    $fields['consent'] = '1';
    $response = $request('/action/citizen.consent', 'POST', ['simulation_action' => $fields]);
    check($response->isRedirect(), 'Formulaire CSRF validé et état persisté');
    $response = $request('/api/v1/state');
    $apiResponse = json_decode($response->getContent(), true);
    check($apiResponse['state']['citizens'][0]['consent'] === true, 'Consentement relu depuis SQLite');
    $response = $request('/action/citizen.consent', 'POST', ['simulation_action' => $fields]);
    check($response->getStatusCode() === 409, 'Soumission périmée refusée par verrouillage optimiste');
    $response = $request('/action/citizen.consent', 'POST', [
        'simulation_action' => array_merge($fields, ['_token' => 'faux']),
    ]);
    check($response->getStatusCode() === 400, 'Formulaire au CSRF invalide refusé');
    $response = $request('/export/proofs');
    check(
        $response->getStatusCode() === 200 && str_contains($response->headers->get('Content-Disposition'), 'attachment'),
        'Export de preuve téléchargeable',
    );
    $export = json_decode($response->getContent(), true);
    check($engine->verify($export)['valid'], 'Export public vérifiable après persistance réelle');
    $sqlite = new PDO('sqlite:' . $databasePath);
    check(
        (int) $sqlite->query('SELECT COUNT(*) FROM municipalities')->fetchColumn() === 3,
        'Aucune ville supplémentaire créée par les parcours',
    );
    check($sqlite->query('PRAGMA integrity_check')->fetchColumn() === 'ok', 'Intégrité SQLite valide');
    $collector = new App\DataCollector\PublicDemoRequestCollector();
    $secret = 'SENTINELLE-NE-PAS-EXPOSER';
    $_ENV['APP_SECRET'] = $secret;
    $_SERVER['SYMFONY_DOTENV_VARS'] = 'APP_SECRET';
    $sensitiveRequest = Request::create(
        'https://demo.example/?token=' . $secret,
        'POST',
        ['_token' => $secret],
        ['session' => $secret],
        [],
        ['APP_SECRET' => $secret],
    );
    $sensitiveResponse = new Response();
    $sensitiveResponse->headers->setCookie(new Symfony\Component\HttpFoundation\Cookie('session', $secret));
    $collector->collect($sensitiveRequest, $sensitiveResponse);
    $collector->lateCollect();
    check(
        !str_contains(serialize($collector), $secret),
        'Collecteur public expurge clés, cookies, corps et jetons',
    );
    $kernel->shutdown();
    unset($sqlite);
    unlink($databasePath);
} catch (Throwable $e) {
    check(false, 'Exception inattendue : ' . $e::class . ' — ' . $e->getMessage());
    file_put_contents(dirname(__DIR__) . '/var/test-exception.txt', (string) $e);
}
file_put_contents(
    dirname(__DIR__) . '/var/test-results.txt',
    implode("\n", $results) . "\n$assertionCount contrôles, $failureCount échec(s).\n",
);
echo implode("\n", $results) . "\n$assertionCount contrôles, $failureCount échec(s).\n";
exit($failureCount ? 1 : 0);
