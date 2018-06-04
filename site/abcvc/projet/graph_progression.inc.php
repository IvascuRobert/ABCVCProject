<?php 


//var_dump($projectsTree);
//exit();
/*  0 => 
    object(stdClass)[140]
      public 'rowid' => string '18' (length=2)
      public 'ref' => string 'PJ1710-0012' (length=11)
      public 'fk_soc' => string '11' (length=2)
      public 'nom' => string 'ENTPE' (length=5)
      public 'name_alias' => string 'ECOLE NATIONALE DES TRAVAUX PUBLICS DE L'ETAT' (length=45)
      public 'code_client' => string 'CU1701-0004' (length=11)
      public 'fk_zones' => string '39' (length=2)
      public 'datec' => string '2017-10-12' (length=10)
      public 'title' => string 'RÉFECTION DES TOITURES TERRASSES DU LABORATOIRE' (length=48)
      public 'fk_statut' => string '1' (length=1)
      public 'chargesfixe' => string '2000.00000000' (length=13)
      public 'cost_calculated' => float 4884.13
      public 'cost' => float 38931.3
      public 'pv' => float 44318
      public 'marge' => float 39433.87
      public 'progress' => float 8.0434782608696
      public 'progress_estimated' => float 3.5326086956522
      public 'nb_postes' => int 23
      public 'lots' => 

*/

$WIDTH='100%'; //DolGraph::getDefaultGraphSizeForStats('width');
$HEIGHT=DolGraph::getDefaultGraphSizeForStats('height');

$dir=$conf->projet->dir_temp;
//var_dump($dir);
//exir();

$nb_projets = count($projectsTree);

$data=array();
$legend=array();
/*
    0    -> 79 vert
    80   -> 99 orange
    100  -> bleu
    101+ -> rouge
*/
$intervales = array(
	'0-79%'=>0,
	'80-99%'=>0,
	'100%'=>0,
	'101+%'=>0
);	
foreach ($projectsTree as $project) {
	
	//var_dump($project->progress);

	if($project->progress<80){
		$intervales['0-79%']++;
		//$progress_color = 'progress-bar-success';
	} elseif($project->progress<100){
		$intervales['80-99%']++;
		//$progress_color = 'progress-bar-warning';
	} elseif($project->progress ==100){
		$intervales['100%']++;
		//$progress_color = 'progress-bar-info';
	} else {
		$intervales['101+%']++;
		//$progress_color = 'progress-bar-danger';
	}
}
foreach ($intervales as $label => $intervale) {
	$data[]= array(
		$label,$intervale
	);
	//$legend[]=$label;	
}




$filenamenb = $dir."/avancements.png";
$fileurlnb = DOL_URL_ROOT.'/viewimage.php?modulepart=projet&amp;file=avancements.png';

//var_dump($data);
//var_dump($legend);
//var_dump($fileurlnb);
//exit(); 

?>      

		<table class="noborder nohover centpercent">
			<tbody>
				<tr class="liste_titre">
					<td >Statistiques - Avancements</td>
				</tr>
				<tr class="impair">
					<td class="">

					<?php 
						$px1 = new DolGraph();
						$mesg = $px1->isGraphKo();
						if (! $mesg) {
							$px1->SetData($data);

							$px1->SetLegend($legend);
							$px1->SetMaxValue($px1->GetCeilMaxValue());
							
							$px1->SetWidth($WIDTH);
							$px1->SetHeight($HEIGHT);

							//$px1->SetYLabel("NumberOfBills");
							$px1->SetShading(3);
							$px1->SetHorizTickIncrement(6);
							$px1->SetPrecisionY(0);
							///$px1->mode='depth';
							$px1->SetTitle('Répartition avancements par projets');

							$px1->draw($filenamenb,$fileurlnb,1);

							echo $px1->show();
						}
					?>

					</td>	
				</tr>
			</tbody>
		</table>

		</div>		

<?php