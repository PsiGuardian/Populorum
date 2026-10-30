<?php

declare(strict_types=1);

require dirname(__DIR__) . '/tools/bootstrap.php';

use App\Service\CharterRenderer;
use App\Service\SimulationEngine;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

$_SERVER['POPULORUM_SCENARIO'] = 'reference';
$checks = 0;
$failures = 0;
$check = static function (bool $passed, string $label) use (
    &$checks,
    &$failures,
): void {
    ++$checks;
    $failures += $passed ? 0 : 1;
    echo ($passed ? 'PASS' : 'FAIL') . ' — ' . $label . "\n";
};
$rejects = static function (callable $action, string $label) use (
    $check,
): void {
    try {
        $action();
        $check(false, $label);
    } catch (DomainException) {
        $check(true, $label);
    }
};
$engine = new SimulationEngine();
$fixture = json_decode(
    file_get_contents(dirname(__DIR__) . '/data/reference-scenario.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
$fixture['keyPair'] = null;
$apply = static fn (
    array $state,
    string $action,
    array $payload,
): array => $engine->apply(
    $state,
    $action,
    $payload,
    bin2hex(random_bytes(16)),
);

// Un ancien événement contenant un nom est réellement signé dans le format précédent.
$legacyName = 'SENTINELLE-ANCIEN-NOM-FICTIF';
$legacy = $apply($fixture, 'citizen.update', [
    'citizenId' => 'u1',
    'name' => $legacyName,
]);
$event = $legacy['events'][0];
$event['payload']['changes']['name'] = $legacyName;
unset($event['hash'], $event['signature']);
$hash = $engine->digest($event);
openssl_sign(
    $hash,
    $signature,
    $legacy['keyPair']['privateKey'],
    OPENSSL_ALGO_SHA256,
);
$legacy['events'][0] = $event + [
    'hash' => $hash,
    'signature' => base64_encode($signature),
];
$check(
    $engine->verify($legacy)['valid'],
    'Ancien format signé réellement valide avant effacement',
);
$erased = $apply($legacy, 'citizen.erase', ['citizenId' => 'u1']);
$public = $engine->publicState($erased);
$check(
    !str_contains(json_encode($public), $legacyName),
    'Ancien nom supprimé du profil et du journal public',
);
$check(
    $public['events'][0]['hash'] === $hash,
    'Empreinte originale conservée après expurgation',
);
$check(
    isset($public['events'][0]['redaction']),
    'Expurgation ancienne explicitement attestée',
);
$check(
    $engine->verify($public)['valid'],
    'Export expurgé et signatures vérifiables',
);
$corrupted = $public;
$corrupted['events'][0]['redaction']['reason'] = 'Attestation falsifiée';
$check(
    !$engine->verify($corrupted)['valid'],
    'Falsification de l’attestation refusée',
);
$corrupted = $public;
$corrupted['events'][0]['payload']['changes']['citizenId'] = 'u9';
$check(
    !$engine->verify($corrupted)['valid'],
    'Falsification d’un événement expurgé refusée',
);
$corrupted = $public;
unset($corrupted['events'][0]['redaction']);
$check(
    !$engine->verify($corrupted)['valid'],
    'Suppression de l’attestation refusée',
);
$check(
    $engine->verify(json_decode(json_encode($public), true))['valid'],
    'Expurgation stable après sérialisation',
);

$draft = $apply($fixture, 'proposal.create', [
    'citizenId' => 'u7',
    'territoryId' => 't3',
    'title' => 'Chemin fictif à compléter',
]);
$id = end($draft['proposals'])['id'];
$rejects(
    fn () => $apply($draft, 'proposal.publish', [
        'citizenId' => 'u7',
        'proposalId' => $id,
    ]),
    'Brouillon incomplet non publiable',
);
$draft = $apply($draft, 'proposal.revise', [
    'citizenId' => 'u7',
    'proposalId' => $id,
    'reason' => 'Avancement fictif',
    'cause' => 'Hypothèse fictive nouvelle',
]);
$check(
    end($draft['proposals'])['status'] === 'draft',
    'Réviser un brouillon ne le publie pas',
);
$check(
    end($draft['proposals'])['revisions'][0]['snapshot']['cause'] === '',
    'Version incomplète conservée',
);

$extended = $apply($fixture, 'proposal.node', [
    'citizenId' => 'u7',
    'proposalId' => 'p3',
    'category' => 'Délai observé',
    'content' => 'Délai fictif de trois mois',
    'kind' => 'observation',
]);
$check(
    count($extended['proposals'][2]['nodes']) === 13,
    'Catégorie complémentaire ajoutée par une action dédiée',
);
$extraId = end($extended['proposals'][2]['nodes'])['id'];
$extended = $apply($extended, 'proposal.edge', [
    'citizenId' => 'u7',
    'proposalId' => 'p3',
    'from' => $extraId,
    'to' => 'n0',
    'relation' => 'observé après',
]);
$check(
    count($extended['proposals'][2]['nodes']) === 13
        && count($extended['proposals'][2]['edges']) === 3,
    'Relation ajoutée sans perdre les catégories',
);
$rejects(
    fn () => $apply($fixture, 'proposal.edge', [
        'citizenId' => 'u7',
        'proposalId' => 'p3',
        'from' => 'n0',
        'to' => 'absent',
        'relation' => 'cause de',
    ]),
    'Relation vers un élément absent refusée',
);
$rejects(
    fn () => $apply($fixture, 'proposal.edge', [
        'citizenId' => 'u7',
        'proposalId' => 'p3',
        'from' => 'n0',
        'to' => 'n1',
        'relation' => 'cause de',
    ]),
    'Cycle causal avec la relation existante refusé',
);
$rejects(
    fn () => $apply($fixture, 'proposal.node', [
        'citizenId' => 'u1',
        'proposalId' => 'p1',
        'category' => 'Contexte',
        'content' => 'Contexte fictif',
    ]),
    'Ajout causal interdit pendant le vote',
);
$rejects(
    fn () => $apply($fixture, 'proposal.edge', [
        'citizenId' => 'u1',
        'proposalId' => 'p3',
        'from' => 'n0',
        'to' => 'n2',
        'relation' => 'observé après',
    ]),
    'Relation par un autre auteur refusée',
);
$revised = $apply($fixture, 'proposal.revise', [
    'citizenId' => 'u7',
    'proposalId' => 'p3',
    'reason' => 'Source fictive révisée',
    'forecast' => 15,
    'sources' => [
        [
            'label' => 'Source pédagogique révisée',
            'date' => '2026-10-29',
            'url' => 'https://example.org/source',
        ],
    ],
]);
$check(
    $revised['proposals'][2]['forecast'] === 15
        && $revised['proposals'][2]['sources'][0]['url'] ===
            'https://example.org/source',
    'Prévision et sources révisables',
);
$check(
    $revised['proposals'][2]['revisions'][0]['snapshot']['sources'] ===
        $fixture['proposals'][2]['sources'],
    'Sources précédentes conservées dans la version',
);

$renderer = new CharterRenderer();
$html = $renderer->render(
    "# Titre\n\nUn **paragraphe**\nsur deux lignes.\n\n- Premier\n- Second\n\n<script>alert(1)</script>\n\n[piège](javascript:alert(1))",
);
$check(
    str_contains($html, '<h2>Titre</h2>')
        && str_contains($html, '<strong>paragraphe</strong>'),
    'Titres et emphases Markdown structurés',
);
$check(
    substr_count($html, '<li>') === 2,
    'Listes sémantiques au lieu de paragraphes par ligne',
);
$check(
    !str_contains($html, '<script>')
        && !str_contains($html, 'href="javascript:'),
    'HTML et liens actifs non sûrs exclus du Markdown',
);

$database =
    dirname(__DIR__) .
    '/var/correction-' .
    bin2hex(random_bytes(6)) .
    '.sqlite';
copy(dirname(__DIR__) . '/data/populorum-demo.sqlite', $database);
$_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = 'sqlite:///' . $database;
$kernel = new App\Kernel('demo', false);
$session = new Session(new MockArraySessionStorage());
$request = static function (
    string $path,
    string $method = 'GET',
    array $parameters = [],
) use ($kernel, $session) {
    $request = Request::create(
        'https://demo.example' . $path,
        $method,
        $parameters,
    );
    $request->setSession($session);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
};
// Lit les champs réellement rendus, y compris collections et jetons cachés.
$formFields = static function (string $html): array {
    $document = new DOMDocument();
    @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($document);
    $parts = [];
    foreach ($xpath->query('//input[@name] | //textarea[@name] | //select[@name]') as $field) {
        $name = $field->getAttribute('name');
        if (!str_starts_with($name, 'simulation_action[')) {
            continue;
        }
        if ($field->tagName === 'select') {
            $selected = $xpath->query('option[@selected]', $field)->item(0) ?? $xpath->query('option', $field)->item(0);
            $value = $selected?->getAttribute('value') ?? '';
        } elseif ($field->tagName === 'textarea') {
            $value = $field->textContent;
        } else {
            $value = $field->getAttribute('value');
        }
        $parts[] = rawurlencode($name) . '=' . rawurlencode($value);
    }
    parse_str(implode('&', $parts), $parameters);

    return $parameters['simulation_action'];
};

try {
    foreach (
        glob(dirname(__DIR__, 3) . '/docs/translations/*', GLOB_ONLYDIR) as $directory
    ) {
        $locale = basename($directory);
        $response = $request('/charter?locale=' . $locale);
        $check(
            $response->getStatusCode() === 200
                && str_contains($response->getContent(), 'class="charter-text"'),
            'Charte rendue : ' . $locale,
        );
    }
    $response = $request('/charter');
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
    $xpath = new DOMXPath($dom);
    $check(
        $xpath->query(
            '//article[contains(@class,"charter-text")]//h3[starts-with(normalize-space(.),"TITRE")]',
        )->length === 7,
        'Sept titres structurés dans la charte française',
    );
    $check(
        $xpath->query('//article[contains(@class,"charter-text")]//ol/li')
            ->length === 12,
        'Les douze éléments de l’article 9 sont une liste',
    );
    $check(
        $xpath->query(
            '//article[contains(@class,"charter-text")]//h4[starts-with(normalize-space(.),"Article")]',
        )->length === 24,
        'Les vingt-quatre articles restent présents',
    );

    // Une soumission réelle avec CSRF doit pouvoir enregistrer un brouillon vide de contenu.
    $response = $request('/action/proposal.create');
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
    $fields['title'] = 'Brouillon créé par formulaire natif';
    $fields['territoryId'] = 't1';
    foreach (range(0, 11) as $index) {
        $fields['kind' . $index] = 'hypothesis';
    }
    $response = $request('/action/proposal.create', 'POST', [
        'simulation_action' => $fields,
    ]);
    $check(
        $response->isRedirect(),
        'Brouillon incomplet enregistré par Symfony Form avec CSRF',
    );

    // Prépare une révision après le vote puis lit le véritable export HTTP.
    $service = $kernel->getContainer()->get('doctrine')->getConnection();
    $owner = $session->get('simulation_owner');
    $workspace = json_decode(
        $service->fetchOne('SELECT state FROM simulations WHERE id = ?', [
            $owner,
        ]),
        true,
    );
    $workspace = $apply($workspace, 'proposal.revise', [
        'citizenId' => 'u4',
        'proposalId' => 'p2',
        'reason' => 'Révision postérieure fictive',
        'solution' => 'NOUVELLE-SOLUTION-HORS-PROTOCOLE',
    ]);
    $service->executeStatement(
        'UPDATE simulations SET state = ?, revision = revision + 1 WHERE id = ?',
        [json_encode($workspace), $owner],
    );
    $response = $request('/export/experiment?id=e1');
    $export = json_decode($response->getContent(), true);
    $check(
        $response->getStatusCode() === 200
            && $export['proposal']['solution'] ===
                $fixture['consultations'][1]['frozen']['solution'],
        'Export HTTP rattaché à la solution réellement consultée',
    );
    $check(
        $export['proposalVersion'] === 1,
        'Export HTTP identifie la version de référence',
    );
    $response = $request('/action/proposal.revise?proposalId=p3');
    $check(
        $response->getStatusCode() === 200
            && str_contains(
                $response->getContent(),
                'simulation_action[problem]',
            )
            && str_contains(
                $response->getContent(),
                'simulation_action[sources][0][label]',
            ),
        'Formulaire de révision expose problème et sources existantes',
    );
    $response = $request('/action/proposal.edge?proposalId=p3');
    $check(
        $response->getStatusCode() === 200
            && str_contains(
                $response->getContent(),
                'name="simulation_action[from]"',
            ),
        'Formulaire de relations accessible sans JavaScript',
    );
    $session->set('citizen_id', 'u7');
    $fields = $formFields($request('/action/proposal.node?proposalId=p3')->getContent());
    $fields['category'] = 'Délai complémentaire';
    $fields['content'] = 'Trois mois fictifs pour observer';
    $fields['kind'] = 'hypothesis';
    $fields['reason'] = 'Compléter le raisonnement fictif';
    $check($request('/action/proposal.node', 'POST', ['simulation_action' => $fields])->isRedirect(), 'Ajout d’une catégorie par formulaire CSRF natif');
    $fields = $formFields($request('/action/proposal.revise?proposalId=p3')->getContent());
    $check(isset($fields['node12']), 'Révision HTTP conserve le treizième élément');
    $fields['problem'] = 'Problème réexaminé par formulaire';
    $fields['cause'] = 'Cause réexaminée par formulaire';
    $fields['reason'] = 'Révision complète fictive';
    $revisionResponse = $request('/action/proposal.revise', 'POST', ['simulation_action' => $fields]);
    if (!$revisionResponse->isRedirect()) {
        file_put_contents(dirname(__DIR__) . '/var/revision-failed.html', $revisionResponse->getContent());
    }
    $check($revisionResponse->isRedirect(), 'Révision complète et sources enregistrées par formulaire natif');
    $state = json_decode($request('/api/v1/state')->getContent(), true)['state'];
    $check($state['proposals'][2]['problem'] === $fields['problem'] && count($state['proposals'][2]['nodes']) === 13 && $engine->verify($state)['valid'], 'Révision HTTP préserve les catégories et l’intégrité signée');
    $fields = $formFields($request('/action/proposal.source?proposalId=p3')->getContent());
    $fields['label'] = 'Nouvelle source pédagogique fictive';
    $fields['date'] = '2026-10-29';
    $fields['url'] = 'https://example.org/nouvelle-source';
    $fields['reason'] = 'Documenter la nouvelle hypothèse';
    $check($request('/action/proposal.source', 'POST', ['simulation_action' => $fields])->isRedirect(), 'Ajout de source par formulaire natif sans JavaScript');
} finally {
    $kernel->shutdown();
    unlink($database);
}
echo "$checks contrôles, $failures échec(s).\n";
exit($failures > 0 ? 1 : 0);
