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
$legend[]='Marge';
$legend[]='Coûts rééls';
$legend[]='Prix vente';

foreach ($projectsTree as $project) {
	$data[]= array(
		$project->ref, 
		$project->marge, 
		$project->cost_calculated, 
		$project->pv
	);	
	
	//var_dump($project->progress);

}




$filenamenb = $dir."/rentabilite.png";
$fileurlnb = DOL_URL_ROOT.'/viewimage.php?modulepart=projet&amp;file=rentabilite.png';

//var_dump($data);
//var_dump($legend);
//var_dump($fileurlnb);
//exit(); 

?>      

		<table class="noborder nohover centpercent">
			<tbody>
				<tr class="liste_titre">
					<td >Statistiques - rentabilités</td>
				</tr>
				<tr class="impair">
					<td class="">
<?php 
/*
			<!-- Build using jflot -->
			<div class="dolgraphtitle" align="center">Rentabilité par projets</div><div id="placeholder_rentabilite_png" style="height: 200px; padding: 0px; position: relative;" class="dolgraph"><canvas class="flot-base" style="direction: ltr; position: absolute; left: 0px; top: 0px; width: 624px; height: 200px;" width="624" height="200"></canvas><div class="flot-text" style="position: absolute; top: 0px; left: 0px; bottom: 0px; right: 0px; font-size: smaller; color: rgb(84, 84, 84);"><div class="flot-x-axis flot-x1-axis xAxis x1Axis" style="position: absolute; top: 0px; left: 0px; bottom: 0px; right: 0px; display: block;"><div style="position: absolute; max-width: 208px; top: 181px; left: 54px; text-align: center;" class="flot-tick-label tickLabel">PJ1710-0012</div><div style="position: absolute; max-width: 208px; top: 181px; left: 249px; text-align: center;" class="flot-tick-label tickLabel">ref_test_abcvc</div><div style="position: absolute; max-width: 208px; top: 181px; left: 448px; text-align: center;" class="flot-tick-label tickLabel">PJ1707-0008</div></div><div class="flot-y-axis flot-y1-axis yAxis y1Axis" style="position: absolute; top: 0px; left: 0px; bottom: 0px; right: 0px; display: block;"><div style="position: absolute; top: 168px; left: 30px; text-align: right;" class="flot-tick-label tickLabel">0</div><div style="position: absolute; top: 134px; left: 2px; text-align: right;" class="flot-tick-label tickLabel">10000</div><div style="position: absolute; top: 101px; left: 2px; text-align: right;" class="flot-tick-label tickLabel">20000</div><div style="position: absolute; top: 67px; left: 2px; text-align: right;" class="flot-tick-label tickLabel">30000</div><div style="position: absolute; top: 34px; left: 2px; text-align: right;" class="flot-tick-label tickLabel">40000</div><div style="position: absolute; top: 1px; left: 2px; text-align: right;" class="flot-tick-label tickLabel">50000</div></div></div><canvas class="flot-overlay" style="direction: ltr; position: absolute; left: 0px; top: 0px; width: 624px; height: 200px;" width="624" height="200"></canvas><div class="legend"><div style="position: absolute; width: 82px; height: 62px; top: 14px; right: 45px; background-color: rgb(255, 255, 255); opacity: 0.85;"> </div><table style="position:absolute;top:14px;right:45px;;font-size:smaller;color:#545454"><tbody><tr><td class="legendColorBox"><div style="border:1px solid #ccc;padding:1px"><div style="width:4px;height:0;border:5px solid #8c8cdc;overflow:hidden"></div></div></td><td class="legendLabel">Prix vente</td></tr><tr><td class="legendColorBox"><div style="border:1px solid #ccc;padding:1px"><div style="width:4px;height:0;border:5px solid #be7878;overflow:hidden"></div></div></td><td class="legendLabel">Coûts rééls</td></tr><tr><td class="legendColorBox"><div style="border:1px solid #ccc;padding:1px"><div style="width:4px;height:0;border:5px solid #00a08c;overflow:hidden"></div></div></td><td class="legendLabel">Marge</td></tr></tbody></table></div></div>
			<script id="rentabilite_png">
			$(function () {
			var d0 = [];
			d0.push([0, 44318]);
			d0.push([1, 0]);
			d0.push([2, 0]);
			var d1 = [];
			d1.push([0, 4884.13]);
			d1.push([1, 0]);
			d1.push([2, 0]);
			var d2 = [];
			d2.push([0, 39433.87]);
			d2.push([1, 0]);
			d2.push([2, 0]);


			function showTooltip_rentabilite_png(x, y, contents) {
				$('<div id="tooltip_rentabilite_png">' + contents + '</div>').css({
					position: 'absolute',
					display: 'none',
					top: y + 5,
					left: x + 5,
					border: '1px solid #ddd',
					padding: '2px',
					'background-color': '#ffe',
					width: 200,
					opacity: 0.80
				}).appendTo("body").fadeIn(20);
			}

			var previousPoint = null;
			$("#placeholder_rentabilite_png").bind("plothover", function (event, pos, item) {
				$("#x").text(pos.x.toFixed(2));
				$("#y").text(pos.y.toFixed(2));

				if (item) {
					if (previousPoint != item.dataIndex) {
						previousPoint = item.dataIndex;

						$("#tooltip").remove();
						var x = item.datapoint[0].toFixed(2);
						var y = item.datapoint[1].toFixed(2);
						var z = item.series.xaxis.ticks[item.dataIndex].label;
						
							showTooltip_rentabilite_png(item.pageX, item.pageY, item.series.label + "<br>" + z + " => " + y);
						
					}
				}
				else {
					$("#tooltip_rentabilite_png").remove();
					previousPoint = null;
				}
			});
			var stack = null, steps = false;

			function plotWithOptions_rentabilite_png() {
			$.plot($("#placeholder_rentabilite_png"), [ 
			{ bars: { show: true, align: "left", barWidth: 0.5 }, color: "#8c8cdc", label: "Prix vente", data: d0 }, 
			{ bars: { show: true, align: "center", barWidth: 0.5 }, color: "#be7878", label: "Coûts rééls", data: d1 }, 
			{ bars: { show: true, align: "right", barWidth: 0.5 }, color: "#00a08c", label: "Marge", data: d2 }
			 ], { series: { stack: stack, lines: { fill: false, steps: steps }, bars: { barWidth: 0.6 } }
			, xaxis: { ticks: [
			 [0, "PJ1710-0012"], 
			 [1, "ref_test_abcvc"], 
			 [2, "PJ1707-0008"]] }
			, yaxis: { min: 0, max: 50000 }
			, grid: { hoverable: true, backgroundColor: { colors: ["#ffffff", "#ffffff"] } }
			});
			}
			plotWithOptions_rentabilite_png();
			});
			</script>
*/ ?>
	

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
							
							$px2->SetTitle('Rentabilité par projets');

							$px2->draw($filenamenb,$fileurlnb,0.4);

							echo $px2->show();
						}
				
					?>

					</td>	
				</tr>
			</tbody>
		</table>		

		</div>
<?php