<?php
$p = dirname(__DIR__);
$autoload = $p . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "vendor/autoload.php absent : installez les dépendances Symfony.\n");
    exit(2);
}
require $autoload;
spl_autoload_register(static function (string $cl) use ($p): void {
    if (str_starts_with($cl,'App\\')) {
        $f=$p.'/src/'.str_replace('\\','/',substr($cl,4)).'.php';
        if (is_file($f)) require $f;
    }
}, true, true);
$geo=new App\Service\GeographyCatalog($p);
$pub=new App\Service\PublicBudget($p);
$catalog=new App\Service\BudgetCatalog($p,$pub,$geo);
$nat=$catalog->national();
$placeholder=['city'=>['code'=>'FR','name'=>'France : données nationales non importées'],'rows'=>[],'amounts'=>[],'population'=>null,'totalCents'=>null];
$explorer=(new App\Service\BudgetExplorer())->build([$placeholder],'FR','overview','amount_desc','');
$trace=(new App\Service\BudgetTrace($p))->build([$placeholder],'FR','overview','all','');
$twig=new Twig\Environment(new Twig\Loader\FilesystemLoader($p.'/templates'));
$twig->addFunction(new Twig\TwigFunction('civic_path',static fn ():string=>'/'));
$twig->addFunction(new Twig\TwigFunction('csrf_token',static fn ():string=>'fixture'));
$args=['actor'=>['name'=>'Personnage fictif','preferences'=>[]], 'labels'=>['budget'=>'Budget et priorités'], 'guardianPath'=>'/guardian', 'view'=>'budget','selectedCommune'=>null,'selectedPilotId'=>null,'geoCoverage'=>$geo->coverage(),'geoRecent'=>[],
'budgetExplorer'=>$explorer, 'budgetTrace'=>$trace, 'budgetCoverage'=>$catalog->coverage(), 'nationalBudget'=>$nat,
'explorerScope'=>'FR', 'budgetSource'=>$pub->dataset(), 'budgetCities'=>[], 'budgetScenarios'=>[], 'savingsPercent'=>0, 'otherResourcesCents'=>0,
'budgetTreeEndpoint'=>'/finances-publiques/arbre/noeuds'];
$out=$twig->render('pages/budget.html.twig',$args);
if (!str_contains($out,'Où va l’argent public ?') || !str_contains($out,'France') || str_contains($out,'Tester une hypothèse de réduction')) throw new RuntimeException('Rendu non conforme');
echo 'OK : budget FR sans exemple des 3 pilotes injecté, HTML '.strlen($out).' octets'.PHP_EOL;
$args['selectedCommune']=['name'=>'Passa','code'=>'66134'];
$args['explorerScope']='66134';
$c=$catalog->city('66134');
$args['budgetExplorer']=(new App\Service\BudgetExplorer())->build([$c], '66134','overview','amount_desc','');
$args['budgetTrace']=(new App\Service\BudgetTrace($p))->build([$c], '66134','overview','all','');
$out=$twig->render('pages/budget.html.twig',$args);
if (!str_contains($out,'Passa') || str_contains($out,'Tester une hypothèse de réduction')) throw new RuntimeException('Rendu Passa incorrect');
echo 'OK : commune sans budget importé, HTML '.strlen($out).' octets'.PHP_EOL;
