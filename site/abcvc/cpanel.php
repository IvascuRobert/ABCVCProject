<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) <year>  <name of author>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    mypage.php
 * \ingroup mymodule
 * \brief   Example PHP page.
 *
 * Put detailed description here.
 */


// Load Dolibarr environment
if (false === (@include '../main.inc.php')) {  // From htdocs directory
	require '../../../main.inc.php'; // From "custom" directory
}

global $db, $langs, $user;

//dol_include_once('/abcvc/class/abcvcMatiere.class.php');
//dol_include_once('/abcvc/class/abcvcConcentration.class.php');
//dol_include_once('/abcvc/class/comptepoids.class.php');
//$obj_comptepoid = new ComptePoids($db);


//$obj_matiere = new abcvcMatiere($db);
//$matieres_actives = $obj_matiere->getabcvc();
//$matieres_all = $obj_matiere->getabcvc(0);

// Get parameters
$rowid = GETPOST('rowid', 'int');
$id_client = GETPOST('id_client', 'int');
$action = GETPOST('action', 'alpha');
$cancel = GETPOST('cancel','alpha');
$mode = GETPOST('mode', 'alpha');


$id_poid = GETPOST('id_poid', 'int');


//tri / filtre / pagination
$search_nom=trim(GETPOST("search_nom"));
$search_datede=trim(GETPOST("search_datede"));
$search_datea=trim(GETPOST("search_datea"));

$limit = GETPOST("limit")?GETPOST("limit","int"):$conf->liste_limit;

$sortfield=GETPOST("sortfield",'alpha');
$sortorder=GETPOST("sortorder",'alpha');
$page=GETPOST("page",'int');
if (! $sortorder) $sortorder="DESC";
if (! $sortfield) $sortfield="m.date_mvt";
if ($page == -1) { $page = 0 ; }
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;


// Access control
if ($user->socid > 0) {
	// External user
	accessforbidden();
}





/*
 **************************************************************************************************************
 *
 * ACTIONS
 *
 **************************************************************************************************************
 */

if ($action == 'initALLClients') {
	$retour_calcul = $obj_comptepoid->initALLClients();
	var_dump($retour_calcul);
	exit();
}



if ($action == 'billallclient') {
	$filters = array(
		'search_nom' => $search_nom,
		'search_datede' => $search_datede,
		'search_datea' => $search_datea
	);

	$retour_calcul = $obj_comptepoid->billClient($filters);
	//var_dump($retour_calcul);
	//exit();
}


if ($action == 'billclient') {
	$filters = array(
		'id_client' => $id_client,
		'search_datede' => $search_datede,
		'search_datea' => $search_datea
	);	
	$retour_calcul = $obj_comptepoid->billClient($filters);
	//var_dump($retour_calcul);
	//exit();
}






/*
 **************************************************************************************************************
 *
 * vue
 *
 **************************************************************************************************************
 */
llxHeader('', $langs->trans('ABCVC - gestion'), '');

$form = new Form($db);





ob_start();
//**************************************************************************************************************
// injection CSS/JS contexte
//**************************************************************************************************************
?>

<link rel="stylesheet" href="/abcvc/css/font-awesome-4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="/abcvc/js/bootstrap-3.3.7/css/bootstrap.min.css">
<link rel="stylesheet" href="/abcvc/css/jquery-ui-timepicker-addon.css">


<script src="/abcvc/js/bootstrap-3.3.7/js/bootstrap.min.js" type="text/javascript" charset="utf-8" async defer></script>
<script src="/abcvc/js/jquery-ui-timepicker-addon.js" type="text/javascript" charset="utf-8" async defer></script>



<script type="text/javascript">

	var concentrations = null;

	var matieres_actives = <?php echo json_encode($matieres_actives);?>;

	var xid_client = '<?php echo $id_client;?>';
	
	$(document).ready(function(){

		$.timepicker.regional['fr'] = {
				timeOnlyTitle: 'Choisir une heure',
				timeText: 'Heure',
				hourText: 'Heures',
				minuteText: 'Minutes',
				secondText: 'Secondes',
				millisecText: 'Millisecondes',
				microsecText: 'Microsecondes',
				timezoneText: 'Fuseau horaire',
				currentText: 'Maintenant',
				closeText: 'Terminé',
				timeFormat: 'HH:mm',
				timeSuffix: '',
				amNames: ['AM', 'A'],
				pmNames: ['PM', 'P'],
				isRTL: false
			};
		$.timepicker.setDefaults($.timepicker.regional['fr']);


		//init dates
		$( "#search_datede" ).datepicker().on( "change", function() {
          $( "#search_datea" ).datepicker( "option", "minDate", getDate( this ) );
        });
		$( "#search_datea" ).datepicker().on( "change", function() {
          $( "#search_datede" ).datepicker( "option", "maxDate", getDate( this ) );
        });
		$('#search_nom, #search_datede, #search_datea').on( "keydown", function(event) { 
			if(event.which == 13) {
				document.formfilter.submit();
			}
		});	


		$('.billallclient').on('click',function(e){
			e.preventDefault();
			if(confirm('Confirmez-vous la facturation de tout les clients listés ci-dessous ?')){
				$('#form_action').val('billallclient');
				
				document.formfilter.submit();
			}
		});


		$('.billclient').on('click',function(e){
			e.preventDefault();
			if(confirm('Confirmez-vous la facturation de ce client ?')){
				var id_client = $(this).data('id-client');
				$('#form_action').val('billclient');
				$('#id_client').val(id_client);
				
				document.formfilter.submit();
			}
		});

	});
	//-------------------------------------------------------


    function getDate( element ) {
      var date;
      try {
        date = $.datepicker.parseDate( "dd/mm/yy", element.value );
      } catch( error ) {
        date = null;
      }
      return date;
    }

</script>

<style type="text/css" media="screen">
	.bloc_concentration_liste{
		font-size: 12px;
		font-style: italic;

	}
	.tb_detail{
		max-height: 500px;
	}

	.pagination {
		margin: 4px 0;
	}	

	.recalcul_poid, .modifier_poid{
		margin-right: 2px;

	}
</style>


<?php

	$filters = array(
		'search_nom' => $search_nom,
		'search_datede' => $search_datede,
		'search_datea' => $search_datea,

		'limit' => $limit,
		'offset' => $offset
	);
	//var_dump($filters);

	//$listeBLClients = $obj_comptepoid->getBLtoBill( $filters);
	//var_dump($listeBLClients);


	//TODO $pagination = $retour['pagination'];

	$param='';
	//if (! empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param.='&contextpage='.$contextpage;
	if ($limit > 0 && $limit != $conf->liste_limit) $param.='&limit='.$limit;
	if ($search_all != '') $param = "&amp;sall=".urlencode($search_all);
	if ($sall != '') $param .= "&amp;sall=".urlencode($sall);

	$param .= "&amp;id_client=".$id_client;

?>
[TODO] final compilation of relevants ABCVC data
<div id="id-center">
	<div class="fiche"> <!-- begin div class="fiche" -->

		<table summary="" class="centpercent notopnoleftnoright" style="margin-bottom: 2px;">
			<tbody>
				<tr>
					<td class="nobordernopadding widthpictotitle" valign="middle">
						<img src="/theme/eldy/img/title_project.png" alt="" title="" id="pictotitle" border="0">
					</td>
					<td class="nobordernopadding" valign="middle">
						<div class="titre">Projects Area</div>
					</td>
				</tr>
			</tbody>
		</table>

This view presents all projects (your user permissions grant you permission to view everything).<br><br>
	<div class="fichecenter">
	<div class="fichethirdleft">
	<form method="post" action="/core/search.php">
		<input name="token" value="de94cec1e92bf98d30a247de47b5bda8" type="hidden">
		<table class="noborder nohover centpercent">
			<tbody>

				<tr class="liste_titre">
					<td colspan="3">Search</td>
				</tr>

				<tr class="impair">
					<td class="nowrap">
						<label for="search_project">Project</label>
					</td>

					<td>
						<input class="flat inputsearch" name="search_project" id="search_project" size="18" type="text">
					</td>

					<td rowspan="1">
						<input value="Search" class="button" type="submit">
					</td>
				</tr>

			</tbody>
		</table>
	</form>
	<br>
	<table class="noborder nohover" width="100%">
		<tbody>
			<tr class="liste_titre">
				<td colspan="2">Statistics - Opportunities amount of open projects by status</td>
			</tr>

			<tr class="impair">
				<td colspan="2" align="center">
					<div id="stats" style="width: 360px; height: 180px; padding: 0px; position: relative;">
						<canvas class="flot-base" style="direction: ltr; position: absolute; left: 0px; top: 0px; width: 360px; height: 180px;" width="360" height="180">
						</canvas>

						<canvas class="flot-overlay" style="direction: ltr; position: absolute; left: 0px; top: 0px; width: 360px; height: 180px;" width="360" height="180">
						</canvas>
							<div class="legend">
								<div style="position: absolute; width: 85px; height: 142px; top: 5px; right: 5px; background-color: rgb(255, 255, 255); opacity: 0.85;"> 
								</div>
								<table style="position:absolute;top:5px;right:5px;;font-size:smaller;color:#545454">
									<tbody>
										<tr>
											<td class="legendColorBox">
												<div style="border:1px solid #ccc;padding:1px">
													<div style="width:4px;height:0;border:5px solid rgb(140,140,220);overflow:hidden"></div>
												</div>
											</td>
											<td class="legendLabel">Prospection</td>
										</tr>
										<tr>
											<td class="legendColorBox">
												<div style="border:1px solid #ccc;padding:1px">
													<div style="width:4px;height:0;border:5px solid rgb(190,120,120);overflow:hidden">
													</div>
												</div>
											</td>
											<td class="legendLabel">Qualification</td>
										</tr>
										<tr>
											<td class="legendColorBox">
												<div style="border:1px solid #ccc;padding:1px">
													<div style="width:4px;height:0;border:5px solid rgb(0,160,140);overflow:hidden">
													</div>
												</div>
											</td>
											<td class="legendLabel">Proposal</td>
										</tr>
										<tr>
											<td class="legendColorBox">
												<div style="border:1px solid #ccc;padding:1px">
													<div style="width:4px;height:0;border:5px solid rgb(190,190,100);overflow:hidden">
													</div>
												</div>
											</td>
											<td class="legendLabel">Negociation</td>
										</tr>
										<tr>
											<td class="legendColorBox">
												<div style="border:1px solid #ccc;padding:1px">
													<div style="width:4px;height:0;border:5px solid rgb(115,125,150);overflow:hidden">
													</div>
												</div>
											</td>
											<td class="legendLabel">Pending</td>
										</tr>
										<tr>
											<td class="legendColorBox">
												<div style="border:1px solid #ccc;padding:1px">
													<div style="width:4px;height:0;border:5px solid rgb(100,170,20);overflow:hidden">
													</div>
												</div>
											</td>
											<td class="legendLabel">Won</td>
										</tr>
											<tr>
												<td class="legendColorBox">
													<div style="border:1px solid #ccc;padding:1px">
														<div style="width:4px;height:0;border:5px solid rgb(250,190,30);overflow:hidden">
														</div>
													</div>
												</td>
												<td class="legendLabel">Lost</td>
											</tr>
									</tbody>
								</table>
							</div>
					</div>

			<script type="text/javascript">
			$(function () {
				var data = [{"label":"Prospection","data":0},
							{"label":"Qualification","data":0},
							{"label":"Proposal","data":0},
							{"label":"Negociation","data":0},
							{"label":"Pending","data":0},
							{"label":"Won","data":0},
							{"label":"Lost","data":0}];

				function plotWithOptions() {
					$.plot($("#stats"), data,
					{
						series: {
							pie: {
								show: true,
								radius: 0.8,
								label: {
									show: true,
									radius: 0.9,
									formatter: function(label, series) {
										var percent=Math.round(series.percent);
										var number=series.data[0][1];
										return '<div style="font-size:8pt;text-align:center;padding:2px;color:black;">'+number+'</div>';
									},
									background: {
										opacity: 0.0,
										color: '#000000'
									},
								}
							}
						},
						zoom: {
							interactive: true
						},
						pan: {
							interactive: true
						},colors: ["#8c8cdc","#be7878","#00a08c","#bebe64","#737d96","#64aa14","#fabe1e","#96877d","#558796","#968750","#965096"],legend: {show: true, position: 'ne' }
					});
				}
				plotWithOptions();
			});
			
			</script>
				</td>
			</tr>
			<tr class="liste_total">
				<td class="maxwidth200 tdoverflow">Opportunities total amount (Won/Lost excluded)</td>
				<td align="right">0.00 €</td>
			</tr>
			<tr class="liste_total">
				<td class="minwidth200 tdoverflow">
					<div class="inline-block">
						<div class="inline-block" style="padding: 0px; padding-right: 3px !important;">Opportunities weighted amount (Won/Lost excluded)</div>
						<div class="classfortooltip inline-block inline-block" style="padding: 0px; padding: 0px; padding-right: 3px !important;">
							<img src="/theme/eldy/img/info.png" alt="" title="" style="vertical-align: middle; cursor: help" border="0">
						</div>
					</div>
				</td>
				<td align="right">0.00 €</td>
			</tr>
		</tbody>
	</table>
		<br>
	<table class="noborder" width="100%">
		<tbody>
			<tr class="liste_titre">
				<th class="liste_titre">Projects Draft <span class="badge">1</span></th>
				<th class="liste_titre">Third party</th>
				<th class="liste_titre" align="right">Opportunity amount</th>
				<th class="liste_titre" align="right">Opportunity status</th>
				<th class="liste_titre" align="right">Tasks</th>
				<th class="liste_titre" align="right">Status</th>
			</tr>

			<tr class="impair">
				<td>
					<a href="/projet/card.php?id=3" class="classfortooltip">
						<img src="/theme/eldy/img/object_project.png" alt="" class="classfortooltip" border="0">
					</a> 
					<a href="/projet/card.php?id=3" class="classfortooltip">PJ1706-0003</a>
				</td>

				<td>
					<a href="/societe/soc.php?socid=13" class="classfortooltip">
						<img src="/theme/eldy/img/object_company.png" alt=" AQUAGED</div>" class="classfortooltip" border="0"></a> 
					<a href="/societe/soc.php?socid=13" class="classfortooltip">AQUAGED</a>
				</td>

				<td align="right">12.00 €</td>
				<td align="right">Qualification</td>
				<td align="right">0</td>
				<td align="right">
					<img src="/theme/eldy/img/statut0.png" alt="" title="Draft" border="0"></td>
			</tr>
			<tr class="liste_total">
				<td colspan="2">Total</td>
				<td class="liste_total" align="right">12.00 €</td>
				<td class="liste_total" align="right">
					<div class="inline-block">
						<div class="inline-block" style="padding: 0px; padding-right: 3px !important;">2.40 €</div>
						<div class="classfortooltip inline-block inline-block" style="padding: 0px; padding: 0px; padding-right: 3px !important;">
							<img src="/theme/eldy/img/info.png" alt="" title="" style="vertical-align: middle; cursor: help" border="0">
						</div>
					</div>
				</td>
				<td class="liste_total" align="right">0</td>
				<td class="liste_total"></td>
			</tr>
		</tbody>
	</table>
	</div>
		<div class="fichetwothirdright">
			<div class="ficheaddleft">
				<table class="noborder" width="100%">
					<tbody>
						<tr class="liste_titre">
							<th class="liste_titre">
								<a href="/projet/index.php?sortfield=s.nom&amp;sortorder=asc&amp;begin=&amp;">Open projects by thirdparties</a>
							</th>
							<th class="liste_titre" align="right">Nb of projects</th>
						</tr>
						<tr class="impair">
							<td class="nowrap">
								<a href="/societe/soc.php?socid=11" class="classfortooltip">
									<img src="/theme/eldy/img/object_company.png" alt=" ENTPE</div>" class="classfortooltip" border="0"></a> 
								<a href="/societe/soc.php?socid=11" class="classfortooltip">ENTPE</a>
							</td>
							<td align="right">
								<a href="/projet/list.php?socid=11&amp;search_status=1">1</a>
							</td>
						</tr>
						<tr class="pair">
							<td class="nowrap">
								<a href="/societe/soc.php?socid=2" class="classfortooltip">
									<img src="/theme/eldy/img/object_company.png" alt=" VILLE DE LYON</div>" class="classfortooltip" border="0"></a> 
								<a href="/societe/soc.php?socid=2" class="classfortooltip">VILLE DE LYON</a>
							</td>
							<td align="right">
								<a href="/projet/list.php?socid=2&amp;search_status=1">1</a>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
	</div> <!-- End div class="fiche" -->
</div>

<?php						
//**************************************************************************************************************
$output = ob_get_clean();
print $output;
// End of page
llxFooter();

$db->close();