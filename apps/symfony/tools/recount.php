<?php

declare(strict_types=1);

// Vérificateur autonome : aucune dépendance au moteur applicatif ni à FamilySuffrage.
if (!isset($argv[1])) {
    fwrite(STDERR, "Usage : php tools/recount.php consultation.json\n");
    exit(2);
}
$c = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$individual = ['pour' => 0, 'contre' => 0, 'abstention' => 0];
$seen = [];
$personal = [];
foreach ($c['votes'] as $v) {
    if (
        isset($seen[$v['citizenId']])
        || !in_array($v['citizenId'], $c['eligibleIds'], true)
        || !isset($individual[$v['choice']])
        || $v['consultationId'] !== $c['id']
    ) {
        throw new RuntimeException('Prise de position invalide ou dupliquée.');
    }
    $seen[$v['citizenId']] = true;
    $personal[$v['citizenId']] = $v['choice'];
    ++$individual[$v['choice']];
}
$unit = $c['rules']['votingUnit'] ?? 'individual';
if (!in_array($unit, ['individual', 'family'], true)) {
    throw new RuntimeException('Unité inconnue.');
}
$counts = $individual;
$eligible = count($c['eligibleIds']);
$families = [];
if ($unit === 'family') {
    if (($c['rules']['familyAggregation'] ?? '') !== 'family-majority-1') {
        throw new RuntimeException('Règle familiale inconnue.');
    }
    $counts = ['pour' => 0, 'contre' => 0, 'abstention' => 0];
    $eligible = count($c['eligibleFamilies'] ?? []);
    $attached = [];
    foreach ($c['eligibleFamilies'] ?? [] as $familyId => $members) {
        if (!$members) {
            throw new RuntimeException('Famille vide.');
        }
        $local = ['pour' => 0, 'contre' => 0, 'abstention' => 0];
        foreach ($members as $id) {
            if (
                isset($attached[$id])
                || !in_array($id, $c['eligibleIds'], true)
            ) {
                throw new RuntimeException('Rattachement dupliqué ou inadmissible.');
            }
            $attached[$id] = true;
            if (isset($personal[$id])) {
                ++$local[$personal[$id]];
            }
        }
        $choice = array_sum($local)
            ? ($local['pour'] > $local['contre']
                ? 'pour'
                : ($local['contre'] > $local['pour']
                    ? 'contre'
                    : 'abstention'))
            : null;
        $families[$familyId] = $local + ['choice' => $choice];
        if ($choice !== null) {
            ++$counts[$choice];
        }
    }
}
$total = array_sum($counts);
$expressed = $counts['pour'] + $counts['contre'];
$participation = $eligible ? ($total / $eligible) * 100 : 0;
echo json_encode(
    $counts + [
        'unit' => $unit,
        'total' => $total,
        'eligible' => $eligible,
        'expressed' => $expressed,
        'participation' => $participation,
        'adopted' => ($c['status'] ?? '') !== 'cancelled'
            && $participation >= $c['rules']['quorum']
            && $expressed > 0
            && $counts['pour'] > $counts['contre'],
        'individual' => $individual + ['total' => count($seen)],
        'families' => $families,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
) . "\n";
