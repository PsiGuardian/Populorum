<?php
declare(strict_types=1);
namespace App\Service;
final class ActionForms {
 public const TYPES=[
 'citizen.create'=>['Créer un personnage fictif','citizen',['name'=>'Nom inventé','territoryId'=>'Territoire']],
 'citizen.consent'=>['Participation nominative fictive','citizen',['consent'=>'checkbox:Je consens à publier les choix de mon personnage dans ma simulation']],
 'citizen.withdraw'=>['Retirer le consentement fictif','citizen',[]],
 'citizen.update'=>['Rectifier mon personnage','citizen',['name'=>'Nom inventé','territoryId'=>'Territoire']],
 'citizen.erase'=>['Effacer le nom fictif','citizen',[]],
 'proposal.create'=>['Exposer mon chemin','proposals',['title'=>'Titre','territoryId'=>'Territoire','theme'=>'Thème','tags'=>'Thèmes communs, séparés par des virgules','summary'=>'area:Explication en français courant','problem'=>'area:Problème','cause'=>'area:Cause principale supposée','consequence'=>'area:Conséquence','solution'=>'area:Solution à expérimenter','justification'=>'area:Justification','indicator'=>'Indicateur mesurable','forecast'=>'number:Variation attendue en % (hypothèse)','risk'=>'area:Risques anticipés','reversibility'=>'area:Conditions de retour arrière','source'=>'Source pédagogique']],
 'proposal.publish'=>['Publier le brouillon','proposals',['proposalId'=>'hidden']],
 'proposal.revise'=>['Faire évoluer mon chemin','proposals',['proposalId'=>'hidden','reason'=>'area:Motif de révision','solution'=>'area:Solution révisée','risk'=>'area:Risques réexaminés']],
 'territory.adapt'=>['Adapter un chemin à un autre territoire','territories',['proposalId'=>'hidden','territoryId'=>'Territoire','type'=>'choice:adaptation,exclusion','characteristic'=>'area:Caractéristique locale','reason'=>'area:Causes et conséquences locales','source'=>'Source pédagogique','alternative'=>'area:Solution alternative','review'=>'area:Conditions de réexamen']],
 'consultation.open'=>['Ouvrir une consultation fictive','consultations',['proposalId'=>'hidden','days'=>'number:Durée en jours, de 1 à 365','quorum'=>'number:Quorum, de 0 à 100 %']],
 'consultation.close'=>['Clôturer cette consultation','consultations',['consultationId'=>'hidden','reason'=>'area:Motif de clôture pédagogique']],
 'vote.cast'=>['Confirmer un vote fictif','consultations',['consultationId'=>'hidden','choice'=>'choice:pour,contre,abstention']],
 'experiment.create'=>['Définir un protocole','experiments',['consultationId'=>'hidden','objective'=>'area:Objectif','hypothesis'=>'area:Hypothèse','baseline'=>'number:Valeur initiale','unit'=>'Unité','start'=>'date:Début','end'=>'date:Fin','stop'=>'area:Critère d’arrêt','method'=>'area:Méthode de mesure','limitations'=>'area:Incertitudes et limites']],
 'observation.add'=>['Ajouter une observation simulée','experiments',['experimentId'=>'hidden','date'=>'date:Date du relevé','value'=>'number:Valeur observée','uncertainty'=>'number:Incertitude ±','source'=>'area:Source et méthode','external'=>'area:Facteurs externes','unexpected'=>'area:Effet imprévu']],
 'experiment.evaluate'=>['Produire le bilan','experiments',['experimentId'=>'hidden','summary'=>'area:Prévisions, observations, imprévus et limites','decision'=>'choice:continue,revise,stop']],
 'finance.add'=>['Ajouter une écriture fictive','transparency',['type'=>'choice:expense,contribution','label'=>'Libellé pédagogique','amount'=>'number:Montant en euros','receipt'=>'Référence du justificatif pédagogique']],
 'governance.propose'=>['Proposer une évolution','governance',['title'=>'Titre de l’évolution','reason'=>'area:Motifs','risk'=>'area:Risques et garanties','rollback'=>'area:Plan de retour arrière']],
 'governance.approve'=>['Enregistrer un avis indépendant','governance',['governanceId'=>'hidden']],
 'moderation.report'=>['Documenter un signalement','governance',['objectId'=>'Identifiant concerné','reason'=>'area:Faits, contexte et preuves']],
 'moderation.appeal'=>['Exercer un recours','governance',['caseId'=>'hidden','reason'=>'area:Motifs du recours et réexamen demandé']],
 'integrity.alert'=>['Signaler une anomalie','transparency',['reason'=>'area:Anomalie et méthode de reproduction']],
 'node.configure'=>['Configurer le relais virtuel','infrastructure',['nodeId'=>'hidden','bandwidth'=>'number:Plafond pédagogique en Ko/s (1–1024)','enabled'=>'checkbox:Autoriser le relais virtuel']],
 'node.sync'=>['Synchroniser une copie virtuelle','infrastructure',['nodeId'=>'hidden']],
 'resource.add'=>['Décrire une contribution fictive','citizen',['kind'=>'Type de contribution','description'=>'area:Ressource ou aide proposée']],
 ];
}
