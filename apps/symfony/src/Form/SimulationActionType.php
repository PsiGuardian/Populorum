<?php
declare(strict_types=1);
namespace App\Form;
use App\Service\{ActionForms,SimulationEngine};
use Symfony\Component\Form\{AbstractType,FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\{TextType,TextareaType,ChoiceType,CheckboxType,NumberType,HiddenType};
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
final class SimulationActionType extends AbstractType {
 public function configureOptions(OptionsResolver $resolver):void{$resolver->setDefaults(['data_class'=>null,'csrf_protection'=>true,'action_type'=>'','territories'=>[]]);$resolver->setAllowedValues('action_type',array_keys(ActionForms::TYPES));}
 public function buildForm(FormBuilderInterface $b,array $options):void{
  foreach(ActionForms::TYPES[$options['action_type']][2] as $name=>$spec){$parts=explode(':',$spec,2);$kind=count($parts)>1?$parts[0]:'text';$label=end($parts);$type=TextType::class;$o=['label'=>$label,'constraints'=>[new Assert\NotBlank(),new Assert\Length(max:3000)]];
   if($name==='territoryId'){$type=ChoiceType::class;$o['choices']=array_column($options['territories'],'id','name');}
   elseif($spec==='hidden'){$type=HiddenType::class;$o['label']=false;}
   elseif($kind==='area'){$type=TextareaType::class;$o['attr']=['rows'=>3];}
   elseif($kind==='number'){$type=NumberType::class;$o['constraints']=[new Assert\NotNull()];$o['scale']=2;$o['html5']=true;}
   elseif($kind==='date'){$o['attr']=['placeholder'=>'AAAA-MM-JJ','pattern'=>'[0-9]{4}-[0-9]{2}-[0-9]{2}'];$o['constraints'][]=new Assert\Date();}
   elseif($kind==='choice'){$type=ChoiceType::class;$values=explode(',',$label);$o['choices']=array_combine($values,$values);$o['label']=$name==='choice'?'Mon choix fictif':ucfirst($name);}
   elseif($kind==='checkbox'){$type=CheckboxType::class;$o['required']=$name==='consent';$o['constraints']=$name==='consent'?[new Assert\IsTrue()]:[];}
   $b->add($name,$type,$o);
  }
  if(in_array($options['action_type'],['proposal.create','proposal.revise'],true))foreach(SimulationEngine::CATEGORIES as $i=>$label){$b->add('node'.$i,TextareaType::class,['label'=>($i+1).'. '.$label,'constraints'=>[new Assert\NotBlank(),new Assert\Length(max:3000)],'attr'=>['rows'=>2]]);$b->add('kind'.$i,ChoiceType::class,['label'=>'Nature de cet élément','choices'=>['Hypothèse / prévision'=>'hypothesis','Observation simulée sourcée'=>'observation']]);}
  $b->add('revision',HiddenType::class,['constraints'=>[new Assert\Regex('/^\d+$/D')]])->add('operationId',HiddenType::class,['constraints'=>[new Assert\Length(min:16,max:100)]]);
 }
}
