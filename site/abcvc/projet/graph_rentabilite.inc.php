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
$nb_projets = count($projectsTree);
$data=array();
$legend=array();
$legend[]='Margine';
$legend[]='Costurile reale';
$legend[]='Prețul de vânzare';

foreach ($projectsTree as $project) {
	$data[]= array(
		$project->ref, 
		$project->marge, 
		$project->cost_calculated, 
		$project->pv
	);
}

$filenamenb = $dir."/rentabilite.png";
$fileurlnb = DOL_URL_ROOT.'/viewimage.php?modulepart=projet&amp;file=rentabilite.png';
?>

<table class="noborder nohover centpercent">
	<tbody>
		<tr class="liste_titre">
			<td >Statistică - Profitabilitate</td>
		</tr>
		<tr class="impair">
			<td class="">
			<?php 
				$px2 = new DolGraph();
				$mesg = $px2->isGraphKo();
				if (! $mesg) {
					$px2->SetData($data);
					$px2->SetLegend($legend);
					$px2->SetMaxValue($px2->GetCeilMaxValue());
					$px2->SetWidth($WIDTH);
					$px2->SetHeight($HEIGHT);
					$px2->SetShading(3);
					$px2->SetHorizTickIncrement(6);
					$px2->SetPrecisionY(0);
					$px2->SetTitle('Repartizarea proiectelor în funcție de profit');
					$px2->draw($filenamenb,$fileurlnb,0.4);
					echo $px2->show();
				}
			?>
			</td>
		</tr>
	</tbody>
</table>