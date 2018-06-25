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
 * \file    abcvc.php
 * \ingroup abcvc
 * \brief   abcvc
 *
 * Put detailed description here.
 */
// Load Dolibarr environment
if (false === (@include '../main.inc.php')) {  // From htdocs directory
	require '../../../main.inc.php'; // From "custom" directory
}
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/core/modules/pdf_primes.php';
global $db, $langs, $user;


//config ABCVC
//---------------------------------------------------------
dol_include_once('/abcvc/class/abcvcConfig.class.php');
$objconfig = new abcvcConfig($db);
$configFull = $objconfig->getConfig($user);
/*var_dump($configFull);
exit();
array (size=1)
  'panier' => 
    object(stdClass)[129]
      public 'rowid' => string '1' (length=1)
      public 'label' => string 'panier' (length=6)
      public 'value' => string '9.19' (length=4)
      public 'active' => string '1' (length=1)
*/
$prix_panier = $configFull['panier']->value; // 9.88 par defaut




// Load translation files required by the page
$langs->load("abcvc@abcvc");

// Get parameters
$id=GETPOST('id','int');
$rowid = GETPOST('rowid', 'int');
$action = GETPOST('action', 'alpha');
$cancel = GETPOST('cancel','alpha');
$label = GETPOST('label','alpha');
$id_zone = GETPOST('id_zone','int');
$active = GETPOST('active',"int");
$description = GETPOST('description');
$perioduser=GETPOST('perioduser','int');
if($perioduser=="") $perioduser = 0;
$from = GETPOST('from','alpha');
$to = GETPOST('to','alpha');

// var_dump($_POST);
// var_dump($perioduser);










// FUNCTION TO TRANSFORM SECONDS IN hh:mm:ss
	function format_time($t,$f=':') {
		return sprintf("%02d%s%02d%", floor($t/3600), $f, ($t/60)%60, $f, $t%60);
	}
	// echo format_time(685); // 00:11:25


//tri / pagination
	$sortfield	= GETPOST('sortfield','alpha');
	$sortorder	= GETPOST('sortorder','alpha');
	$page		= GETPOST('page','int');
	if ($page == -1) { $page = 0 ; }
	$offset = $conf->liste_limit * $page ;
	$pageprev = $page - 1;
	$pagenext = $page + 1;
	if (! $sortorder) {  $sortorder="DESC"; }
	if (! $sortfield) {  $sortfield="d.lastname"; }


// Access control
	if ($user->socid > 0) {
		// External user
		accessforbidden();
	}

//ACTIONS
//--------------------------------------------------------

// Load object if id or ref is provided as parameter
	$objproj = new ProjectABCVC($db);
	$objtask = new TaskABCVC($db);
	$form = new Form($db);

	
	//var_dump($getalltime);


	// Default action
	if (empty($action) && empty($id) && empty($ref)) {
		$action='list';
	}

	//action cancel ??? fff retour en mode liste...
	if ( $cancel == "Annuler" ) {
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	// add
	if ($action == 'add') {
	}

	// update
	if ($action == 'update') {
	}

	// delete
	if ($action == 'delete') {
	}
	// Build doc
	if ($action == 'builddoc' && $user->rights->projet->creer)
	{
  		// var_dump($from,$to,$perioduser);
  		// exit;
		$objpdf = new pdf_primes($db);
		$objpdf->write_file($langs,$perioduser,$from,$to,$prix_panier);
	}





/*
 * VIEW
 *
 * Put here all code to build page
 */
	ob_start();
	// BOOTSTRAP 3 + css + js custom
	require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/abcvc_js_css.php';
    $header_abcvc = ob_get_clean();

	llxHeader($header_abcvc, $langs->trans('ABCVC - Présence'), '');

	$form = new Form($db);

/*
*
*
*	TRANSFORM DATES IN STRTOTIME AND WHEN WE INPUT A DATE GENERATE TABLE BY MONTH 
*
*/
	$ok_calendrier = true;
	if($from != "") // IF "FROM" IS EMPTY SHOW CURENT DATE
	{ 
		//CONVERT FRANCE DATE TO SQL DATE
	    $tmp_date0 = explode(' ',$from);
	    $tmp_date = explode('/',$tmp_date0[0]);
	    $new_from = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0];
		$stt_from = strtotime($new_from);
	} 
	else
	{
		$stt_from = time();
		//var_dump("from",$stt_from);
	} 

	if($to != "") // IF "TO" IS EMPTY SHOW CURENT DATE
	{ 
		//CONVERT FRANCE DATE TO SQL DATE	
		$tmp_date0 = explode(' ',$to);
	    $tmp_date = explode('/',$tmp_date0[0]);
	    $new_to = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0];
	    //var_dump($new_to);
		$stt_to = strtotime($new_to);
	} 
	else
	{
		$ok_calendrier = false;
	} 

?>
<style type="text/css" media="screen">
	.classfortooltip{
		cursor:help;
	}
</style>


<!-- FORM TO INPUT DATE  -->
<form method="POST" id="searchFormList" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
    <div class="panel panel-info filterable">
        <div class="panel-heading">
			<form name="selectperiod" method="POST" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
				<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">
				<input type="hidden" name="action" value="selectperiod">	
			<!-- Begin title 'Enter monthly time' -->
				<table class="notopnoleftnoright" style="margin-bottom: 6px;" border="0" width="100%">
					<tbody>
						<tr>
							<td class="nobordernopadding valignmiddle">
								<img src="/theme/eldy/img/title_generic.png" alt="" title="" class="hideonsmartphone valignmiddle" id="pictotitle" border="0">
								<div class="titre inline-block">Prezență colaborator</div>
							</td>
							<td class="nobordernopadding valignmiddle" align="right">
								<button type="submit" class="btn btn-warning" name="action" value="builddoc"><span class="glyphicon glyphicon-file"></span> Genereză document PDF</button>
							</td>
						</tr>
					</tbody>
				</table>
			<!-- End title -->

				<table class="" cellpadding="2" cellspacing="2">
					<tbody>
						<tr>
							<td width="170px">Perioadă</td>
							<td width="170px">
								<div class="input-group input-group-sm">
								  	<span class="input-group-addon" id="sizing-addon1">De la</span>
									<input  id="prime_from" name="from" value="<?php if($from != "") { echo  $from; }  ?>">

									<span class="input-group-addon" id="sizing-addon1">La</span>
									<input  id="prime_to" name="to" value="<?php if($to != "") { echo  $to; }  ?>">
								</div>
							</td>
							<td></td>
						</tr>
						<tr>
							<td>Selecteză colaborator</td>
								<?php $showempty=0;
									// attention le dernier paramétre n'est dispo que sur la 3.7 et le patch fournis
									$filteruser="";
									if ($user->admin == 0) 
									$filteruser=" AND (u.rowid = ".$user->id." OR fk_user=".$user->id.")";
								?>
							<td >
								<?php echo $form->select_dolusers($perioduser, 'perioduser', $showempty, '', 0,'', '', 0, 0, 0, $filteruser); //var_dump($perioduser); ?>	
								
							</td>
							<td >&nbsp;<input class="btn btn-primary btn-success" type=submit name="select" value="<?php echo $langs->trans("Afișează"); ?>"> </td>
						</tr>

					</tbody>
				</table>
				
			</form>
		</div>
	</div>
</form>

<?php if($ok_calendrier) :

//here we extract TIME TREE getTimeTree($userid,$from,$to)

	$getalltime = $objtask->getTimeTree($perioduser,$new_from,$new_to);
	//var_dump($getalltime);

	$getcontact = $objtask->getContact($perioduser);
	/*
		var_dump($getcontact);
		array (size=1)
		  0 => 
		    object(stdClass)[138]
		      public 'rowid' => string '2' (length=1)
		      public 'lastname' => string 'Wandebrouck' (length=11)
		      public 'firstname' => string 'Eric' (length=4)
	*/      

	//-----------------------------------------------------------------------
	//-----------------------------------------------------------------------
	//
	// 	calculs calendrier dates...
	//
	//-----------------------------------------------------------------------
	//-----------------------------------------------------------------------

	//windows @#!!
	setlocale(LC_ALL, 'french');
	//linussee
	//setlocale(LC_ALL, 'fr_FR');



	//SELECT ALL MONTHS BETWEEN "$FROM" AND "$TO"
	$start = $month = $stt_from;
	$end = $stt_to;
	$select_month = array(); // ALL THE MONTHS SELECTED
	if($stt_to == $stt_from) {
		$select_month[ date('n',$month) ] = utf8_encode( ucfirst(strftime('%B', $month)) ); //date("F", $month);
	} else {
		while($month <= $end) {	
		    // echo date('F', $month), PHP_EOL;
		    
		    $select_month[date('n',$month)] = utf8_encode( ucfirst(strftime('%B', $month)) ); //    date('F', $month);
		    $month = strtotime("+1 month", $month);
		}
	}
	//var_dump("CE TREBUIE AFISAT",$select_month);




	$year_selected = date('Y', $stt_from);
	$nbWeekYear = date('W', mktime(0,0,0,12,28,$year_selected) ); 
	//OK real nb
	//var_dump($nbWeekYear);

	//var_dump($stt_from,$stt_to);
	//var_dump($year_selected);
	//var_dump($select_month);








	function year2array($year) {
	    $res = $year >= 1970;
	    if ($res) {
	      // this line gets and sets same timezone, don't ask why :)
	      date_default_timezone_set(date_default_timezone_get());

	      $dt = strtotime("-1 day", strtotime("$year-01-01 00:00:00"));
	      $res = array();
	      $week = array_fill(1, 7, false);
	      $last_month = 1;
	      $w = 1;
	      do {
	        $dt = strtotime('+1 day', $dt);
	        $dta = getdate($dt);
	        $wday = $dta['wday'] == 0 ? 7 : $dta['wday'];
	        if (($dta['mon'] != $last_month) || ($wday == 1)) {
	          if ($week[1] || $week[7]) $res[$last_month][] = $week;
	          $week = array_fill(1, 7, false);
	          $last_month = $dta['mon'];
	          }
	        $week[$wday] = $dta['mday'];
	        }
	      while ($dta['year'] == $year);
	      }
	    return $res;
	}

	function month2table($month, $calendar_array) {
	    $ca = 'align="center"';
	    $res = "<table cellpadding=\"2\" cellspacing=\"1\" style=\"border:solid 1px #000000;font-family:tahoma;font-size:12px;background-color:#ababab\"><tr><td $ca>Mo</td><td $ca>Tu</td><td $ca>We</td><td $ca>Th</td><td $ca>Fr</td><td $ca>Sa</td><td $ca>Su</td></tr>";
	    foreach ($calendar_array[$month] as $month=>$week) {
	      $res .= '<tr>';
	      foreach ($week as $day) {
	        $res .= '<td align="right" width="20" bgcolor="#ffffff">' . ($day ? $day : '&nbsp;') . '</td>';
	        }
	      $res .= '</tr>';
	      }
	    $res .= '</table>';
	    return $res;
	}


	$year_array = year2array($year_selected);

	//OK temp tb compact
	//echo month2table(10, $year_array); // January

	// echo month2table(2, $year_array); // February
	// echo month2table(12, $year_array); // December

	//var_dump($year_array[1]);


?>
<div id="presencepage">

	<!-- TOP TABLE ( WITH NAME, TOTAL H, etc ...) -->
	<div id="container_header_presences_top">
		
	</div>


	<?php 
	//total periode
	//---------------------------

	$nbpanierPeriode = 0;
	$nbtrajetPeriode = 0;
	$nbtrajetGDPeriode = 0;

	$sumWorkPeriode = 0;
	$sumTravailPeriode = 0;
	$sumSAVPeriode = 0;
	$sumCongesPeriode = 0;
	$sumFeriePeriode = 0;
	$sumMaladiePeriode = 0;
	$sumRecupPeriode = 0;
	$sumEcolePeriode = 0;

	$trajets_periode = array();
	$trajetsGD_periode = array();


	//boucle mois
	//-----------------------------------------------------------------------
	//-----------------------------------------------------------------------
	for ($month=1; $month <=12 ; $month++) : 
		$nbpanierMonth = 0;
		$nbtrajetMonth = 0;
		$nbtrajetGDMonth = 0;

		$sumWorkMonth = 0;
		$sumTravailMonth = 0;
		$sumSAVMonth = 0;
		$sumCongesMonth = 0;
		$sumFerieMonth = 0;
		$sumMaladieMonth = 0;
		$sumRecupMonth = 0;
		$sumEcoleMonth = 0;

		$trajets_month = array();
		$trajetsGD_month = array();

		$month_potential_works_hours = 0;
		$sumSupMonth = 0;

		//FALSE GO TO NEXT MONTH
		if(!array_key_exists($month,$select_month) ){
			continue;
		}
		?>

		<table class="table table-bordexred" >
		  	<tbody>
				<tr>
			  		<td bgcolor="#0080ff" width="6%" rowspan = "2" align="center"><?php echo $select_month[$month].'<br />'.$year_selected; ?></td>
			  	</tr>
			  	<tr>
			<?php

			foreach ($year_array[$month] as $idx_week=>$week) : 
				$nbpanierWeek = 0;
				$nbtrajetWeek = 0;
				$nbtrajetGDWeek = 0;

				$ar_trajetGDWeek = array();

				$sumWorkWeek = 0;
				$sumTravailWeek = 0;
				$sumSAVWeek = 0;
				$sumCongesWeek = 0;
				$sumFerieWeek = 0;
				$sumMaladieWeek = 0;
				$sumRecupWeek = 0;
				$sumEcoleWeek = 0;

				$sumSupWeek = 0;	

				$weekWidth = "20%";
				if(count($year_array[$month])==5) $weekWidth = "18.6%"; //18.6%
				if(count($year_array[$month])==4) $weekWidth = "23.6%"; 
				if(count($year_array[$month])==6) $weekWidth = "15.26%";
				

				//working normal hours 7h/day of work
				$week_potential_works_hours = 0;
				?>
				
			  	<td bgcolor="#0080ff" width="<?php echo $weekWidth;?>"><!--SEMAINE <?php echo $idx_week+1; ?>-->
					<table class="table table-borxdered small table-hover">
						<tbody>
							<?php 
							//var_dump($week_potential_works_hours);


							//precalcul GD...
							//------------------------------------------
							foreach ($week as $idx_day=>$day) { 
								$ar_trajetGDWeek[$idx_day] = 0;
	  							$timespentday = $getalltime[$month][$day][0];
								if($timespentday->gd==1){
									$ar_trajetGDWeek[$idx_day] = 1;
								}
							}
							//var_dump( $ar_trajetGDWeek );
							/*
								array (size=7)
								  1 => int 1
								  2 => int 1
								  3 => int 1 -> 0
								  4 => int 0
								  5 => int 1 -> 0
								  6 => int 0
								  7 => int 0
							*/
							foreach ($ar_trajetGDWeek as $idx_day => $trajetGD) {
								if($idx_day>1){
									if( ($trajetGD==0) ){
										if( $ar_trajetGDWeek[$idx_day-1] == 1 ){
											$ar_trajetGDWeek[$idx_day-1] = 0;
										}
									}
								}
							}
							?> 




							<?php foreach ($week as $idx_day=>$day) : 
  								$sumWorkDay = 0;
  								$daydate = mktime(0,0,0,$month,$day,$year_selected);
 							?>
								<?php if(!$day): ?>
									<tr>
										<td bgcolor="#ccc" colspan="2" >&nbsp;</td>
									</tr>	
								<?php else: ?>		
									<tr>
										<td bgcolor="#cce6ff" width="40%" ><?php echo ucfirst(strftime('%A',$daydate));?> <div class="pull-right"><?php echo $day;?></div></td>
										<?php 

			  								//if not WE +7h
			  								if(date('N',$daydate) < 6) {
			  									$week_potential_works_hours +=7; 
			  								}
										?>

										<?php if(isset($getalltime[$month][$day])) : 
											// HEURES saisies + calcul panier/trajet,etc
											// -------------------------------------------------------------
	  										
	  										//TODO CAS MULTIPLE POSTE par jour...
	  										foreach ($getalltime[$month][$day] as $timespentday) {
												/*

												*/
	  											if( $timespentday->task_type == 0 ) $sumTravailWeek += ($timespentday->task_duration);	//Travail 
												if( $timespentday->task_type == 6 ) $sumSAVWeek += ($timespentday->task_duration);		//MES / SAV 
												if( $timespentday->task_type == 5 ) $sumEcoleWeek += ($timespentday->task_duration);	//Ecole												
												if( $timespentday->task_type == 1 ) $sumCongesWeek += ($timespentday->task_duration);	//Conges
												if( $timespentday->task_type == 2 ) $sumFerieWeek += ($timespentday->task_duration);	//Ferie
												if( $timespentday->task_type == 3 ) $sumMaladieWeek += ($timespentday->task_duration);	//Maladie
												if( $timespentday->task_type == 4 ) $sumRecupWeek += ($timespentday->task_duration);	//Recup


												//if( $timespentday->task_type == 0 )
	  												$sumWorkWeek += ($timespentday->task_duration);


	  											$sumWorkDay += ($timespentday->task_duration);
	  										}											

												//type timespent
												//----------------------------------------------------------
												if( $timespentday->task_type == 0 ) $bgcolor="bgcolor='#5cb85c'";	//Travail 
												if( $timespentday->task_type == 6 ) $bgcolor="bgcolor='#185a18'";	//MES / SAV 
												if( $timespentday->task_type == 1 ) $bgcolor="bgcolor='#ffbf00'";	//Conges
												if( $timespentday->task_type == 2 ) $bgcolor="bgcolor='#00ff80'";	//Ferie
												if( $timespentday->task_type == 3 ) $bgcolor="bgcolor='#8000ff'";	//Maladie
												if( $timespentday->task_type == 4 ) $bgcolor="bgcolor='#ff0000'";	//Recup
												if( $timespentday->task_type == 5 ) $bgcolor="bgcolor='#00ffbf'";	//Ecole
												?>
												<td <?php echo $bgcolor;?> >
			  										
				  										<div class="timespent_hours ">
				  											<?php 
				  											//heures - heures sup (7h/jour = 25200sec)
				  											if($sumWorkDay>25200){
				  												$sumSupDay = $sumWorkDay - 25200;				  												
				  												$sumWorkDay = 25200;
				  											} else {
				  												$sumSupDay = 0;
				  											}
				  											$sumSupWeek += $sumSupDay;	
				  												echo format_time($sumWorkDay,$f=':'); // t = seconds, f = separator 
				  												if($sumSupDay > 0) echo "<span class='small'> (+".format_time($sumSupDay).")</span>";
				  											?>
				  											
				  											<?php //Ecole : heures à exclure des calculs de panier et trajet
															if( ($timespentday->task_type == 0) || ($timespentday->task_type == 6) ): ?>

					  											<?php 
					  											//trajet
					  											//price($timespentday->zone_price)
					  											$label_trajet = "Drum (".$timespentday->zone."): <b>".price($timespentday->zone_price)." €</b>";
					  											?>
					  											<div class="timespent_trajet classfortooltip" title="<?php echo dol_escape_htmltag($label_trajet, 1);?>"">
					  												<?php if ($ar_trajetGDWeek[$idx_day]==1) :
					  													$nbtrajetGDWeek++;
					  													$trajetsGD_month[$timespentday->zone]+=$timespentday->zone_price;
					  												?>
					  													<i class="fa fa-moon-o" aria-hidden="true"></i>	
					  												<?php elseif ($timespentday->gd==0):
					  													$nbtrajetWeek++;
					  													$trajets_month[$timespentday->zone]+=$timespentday->zone_price;		  													
					  												?>
					  													<i class="fa fa-road" aria-hidden="true"></i>
					  												<?php endif;?>
					  											</div>
					  											
					  											<?php if( $timespentday->task_duration >= 18000 ) : 
						  											//panier > 5h  / 18000sec
						  											$nbpanierWeek++;
						  											$label_panier = "Pachet: <b>".price($prix_panier)." €</b>";
						  											?>
						  											<div class="timespent_panier classfortooltip" title="<?php echo dol_escape_htmltag($label_panier, 1);?>"><i class="fa fa-shopping-basket" aria-hidden="true"></i></div>
					  											<?php endif; ?>
					  										<?php endif; ?>	

				  										</div>

				  										<div class="timespent_projet ">
				  											<?php 
					  											$label = '';
														        if (! empty($timespentday->projet_ref))
														            $label .= '<b>Ref: </b> ' . $timespentday->projet_ref;
														        if (! empty($timespentday->projet_title))
														            $label .= '<br><b>Nume: </b> ' . $timespentday->projet_title;
														        
													        ?>
				  											<div class="pull-left small classfortooltip" title="<?php echo dol_escape_htmltag($label, 1);?>">
				  												<?php echo $timespentday->projet_ref;?>
				  											</div>
				  											<?php 
					  											$label = '';
														        if (! empty($timespentday->task_ref))
														            $label .= '<b>Ref: </b> ' . $timespentday->task_ref;
														        if (! empty($timespentday->task_title))
														            $label .= '<br><b>Nume: </b> ' . $timespentday->task_title;
														        
													        ?>		  											
				  											<div class="pull-right small classfortooltip" title="<?php echo dol_escape_htmltag($label, 1);?>">
					  										Poste: <?php echo $timespentday->task_ref;?>
					  										</div>
				  										</div>

			  										
												</td>
										<?php else: ?>		
											<td align="center"> - </td>
										<?php endif; ?>		
									</tr>
								<?php endif; ?>							
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php
					//var_dump($week_potential_works_hours);
					?>
				</td>

			<?php
				$nbpanierMonth += $nbpanierWeek; 
				//Nbr heures ("travail"+ "feries"+"maladie")
				$sumWorkMonth += $sumTravailWeek + $sumSAVWeek + $sumEcoleWeek + $sumMaladieWeek + $sumFerieWeek; //$sumWorkWeek;
				
				$sumTravailMonth += $sumTravailWeek;
				$sumSAVMonth += $sumSAVWeek; 
				$sumEcoleMonth += $sumEcoleWeek;
				$sumCongesMonth += $sumCongesWeek;
				$sumFerieMonth += $sumFerieWeek;
				$sumMaladieMonth += $sumMaladieWeek;
				$sumRecupMonth += $sumRecupWeek;

				$nbtrajetMonth += $nbtrajetWeek;
				$nbtrajetGDMonth += $nbtrajetGDWeek;

				$sumSupMonth += $sumSupWeek;

				$month_potential_works_hours += $week_potential_works_hours;
				$week_potential_works_hours = 0;

			endforeach; 

			
			//var_dump($trajets_month);
			//var_dump($trajetsGD_month);
			/*array (size=2)
			  'Z5' => float 15,12
			  'Z6 - GD' => float 136
			*/
			$ok_labelcost_trajets_month = '';
			if(count($trajets_month)>0){
				$ok_labelcost_trajets_month = ' ( ';
				$labelcost_trajets_month = array();
				foreach ($trajets_month as $label => $cost) {
					$labelcost_trajets_month[] = $label.': <b>'.price($cost).' €</b>';
				}
				$ok_labelcost_trajets_month .= implode(',  ',$labelcost_trajets_month);
				$ok_labelcost_trajets_month .= ' )';
			}
			
			$ok_labelcost_trajetsGD_month = '';
			if(count($trajetsGD_month)>0){
				$ok_labelcost_trajetsGD_month = ' ( ';
				$labelcost_trajetsGD_month = array();
				foreach ($trajetsGD_month as $label => $cost) {
					$labelcost_trajetsGD_month[] = $label.': <b>'.price($cost).' €</b>';
				}
				$ok_labelcost_trajetsGD_month .= implode(',  ',$labelcost_trajetsGD_month);
				$ok_labelcost_trajetsGD_month .= ' )';
			}
			?>
				</tr>
				
				<!-- total cumule mois -->
				<tr>
					<td bgcolor="#0080ff" align="center">TOTAL LUNĂ</td> 
			  		<td bgcolor="#0080ff" colspan="<?php echo count($year_array[$month]); ?>">

						<table class="table small" cellspacing="4" cellpadding="4">
						  	<tbody>
								<tr>
									<td>Total ore pe lună: 
										<?php 
										$month_potential_works_secondes = $month_potential_works_hours*60*60;
										if($sumWorkMonth>$month_potential_works_secondes){
											//$heuresSup_Month = $sumWorkMonth - $month_potential_works_secondes;
											$heuresDues_Month = 0;
										} else {
											//$heuresSup_Month = 0;
											$heuresDues_Month = $month_potential_works_secondes-$sumWorkMonth;
										}
										

										$matrice_work_Month[$month] = array(
											'potential_works'=>$month_potential_works_hours*60*60,
											'sumWorkMonth'=>$sumWorkMonth,

											'heuresSup_Month'=>$sumSupMonth,
											'heuresDues_Month'=>$heuresDues_Month

										);

										echo format_time($month_potential_works_secondes); 
										?> 
										( 
										Muncă : <?php echo format_time($sumWorkMonth); ?>, ore suplimentare: <?php echo format_time($sumSupMonth); ?>
										, ore de lucru: <?php echo format_time($heuresDues_Month); ?>
										)

									</td>
									<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#5cb85c;"><?php echo format_time($sumTravailMonth); ?></div></td>
									<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#185a18;"><?php echo format_time($sumSAVMonth); ?></div></td>
									<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#00ffbf"><?php echo format_time($sumEcoleMonth); ?></div></td>								
									<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#00ff80;"><?php echo format_time($sumFerieMonth); ?></div></td>
									<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#8000ff"><?php echo format_time($sumMaladieMonth); ?></div></td>								
									<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#ffbf00;"><?php echo format_time($sumCongesMonth); ?></div></td>
									<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#ff0000"><?php echo format_time($sumRecupMonth); ?></div></td>
								</tr>
								<tr>
									<td colspan="7"> 
										<i class="fa fa-shopping-basket" aria-hidden="true"></i> Total pachet: <?php echo $nbpanierMonth; ?> ( <b><?php echo price($nbpanierMonth*$prix_panier);?> €</b> )
										&nbsp;&nbsp;&nbsp;&nbsp;
										<i class="fa fa-road" aria-hidden="true"></i>  Total călătorii: <?php echo $nbtrajetMonth; ?> <?php echo $ok_labelcost_trajets_month;?> 
										&nbsp;&nbsp;&nbsp;&nbsp;
				  						<i class="fa fa-moon-o" aria-hidden="true"></i> Total călătorie pe deplasare mare: <?php echo $nbtrajetGDMonth; ?> <?php echo $ok_labelcost_trajetsGD_month;?> 
									</td>
								</tr>
							</tbody>
						</table>

				  		<?php 
							$sumWorkPeriode += $sumWorkMonth;
							$sumTravailPeriode += $sumTravailMonth;
							$sumSAVPeriode 	+= $sumSAVMonth;
							$sumCongesPeriode += $sumCongesMonth;
							$sumFeriePeriode += $sumFerieMonth;
							$sumMaladiePeriode += $sumMaladieMonth;
							$sumRecupPeriode += $sumRecupMonth;
							$sumEcolePeriode += $sumEcoleMonth;	

							$nbpanierPeriode += $nbpanierMonth;

							$nbtrajetPeriode += $nbtrajetMonth;
							$nbtrajetGDPeriode += $nbtrajetGDMonth;							

							foreach ($trajets_month as  $label => $cost) {
								$trajets_periode[$label]+=$cost;
							}
							foreach ($trajetsGD_month as $label => $cost) {
								$trajetsGD_periode[$label]+=$cost;
							}

				  		/*

						*/
						?>

			  		</td>
			  	</tr>			  	
			</tbody>
		</table>	

	<?php
	endfor;


	//var_dump($year_array);
	//exit();

	//-----------------------------------------------------------------------
	//-----------------------------------------------------------------------

		//var_dump($trajets_periode);
		//var_dump($trajetsGD_periode);
		$ok_labelcost_trajets_periode = '';
		if(count($trajets_periode)>0){
			$ok_labelcost_trajets_periode = ' ( ';
			$labelcost_trajets_periode = array();
			foreach ($trajets_periode as $label => $cost) {
				$labelcost_trajets_periode[] = $label.': <b>'.price($cost).' €</b>';
			}
			$ok_labelcost_trajets_periode .= implode(',  ',$labelcost_trajets_periode);
			$ok_labelcost_trajets_periode .= ' )';
		}
		
		$ok_labelcost_trajetsGD_periode = '';
		if(count($trajetsGD_periode)>0){
			$ok_labelcost_trajetsGD_periode = ' ( ';
			$labelcost_trajetsGD_periode = array();
			foreach ($trajetsGD_periode as $label => $cost) {
				$labelcost_trajetsGD_periode[] = $label.': <b>'.price($cost).' €</b>';
			}
			$ok_labelcost_trajetsGD_periode .= implode(',  ',$labelcost_trajetsGD_periode);
			$ok_labelcost_trajetsGD_periode .= ' )';
		}
	?>


	<div id="container_header_presences_bottom">
		<table class="table table-bordxered" id="header_presences" >
		  	<tbody>
				<tr>
			        <td colspan = "5" bgcolor="" align="center" >
				        <?php foreach ($getcontact as $contact ) {
				        	echo '<a target="_blank" href="/user/card.php?id='.$contact->rowid.'">'.$contact->firstname."&nbsp;".$contact->lastname.'</a>';
				        }
				        //pc.email, pc.thm, pc.salary, pc.weeklyhours  
				        ?> - <b><?php echo $contact->job;?></b> -
				        <i class="fa fa-phone" aria-hidden="true"></i> <?php echo $contact->user_mobile;?> - 
				        <i class="fa fa fa-envelope-o" aria-hidden="true"></i> <?php echo $contact->email;?> - 
						[ Salariu: <?php echo price($contact->salary);?>€, Ore de lucru pe săptămână: <?php echo (float)($contact->weeklyhours);?> ]
			    	</td>
			    </tr>
				<tr>
					<td with="80%" bgcolor="#cce6ff">Perioada de la <?php echo date('d/m/Y',$stt_from)." până la ".date('d/m/Y',$stt_to); ?></td>
					<td></td>
					<td with="18%" bgcolor="#cce6ff" rowspan="2">
						<?php 
						//var_dump($matrice_work_Month); 
						/*RTT [x]<br />
						ABCVC doit / Collab doit : 
						Nbr heures ("travail"+ "feries"+"maladie") - ("conges" + "recup")  annualisés (35h/Semaine) - de 51 à 52 semaines par an - 5 semaines de CP 
						---------------------------

						//var_dump($matrice_work_Month); 
						/*  10 => 
						    array (size=4)
						      'potential_works' => int 554400
						      'sumWorkMonth' => int 158400
						      'heuresSup_Month' => int 7200
						      'heuresDues_Month' => int 396000*/
						$matrice_work_Periode = array();
						foreach ($matrice_work_Month as $work_Month) {
							$matrice_work_Periode['potential_works'] += $work_Month['potential_works'];
							$matrice_work_Periode['sumWork'] += $work_Month['sumWorkMonth'];
							$matrice_work_Periode['heuresSup'] += $work_Month['heuresSup_Month'];
							$matrice_work_Periode['heuresDues'] += $work_Month['heuresDues_Month'];
						} 
						//var_dump($matrice_work_Periode);
						if($matrice_work_Periode['heuresDues']>0){
							$abcvc_doit = 0;
							$collaborateur_doit = $matrice_work_Periode['heuresDues'];
						} else {
							$abcvc_doit = $matrice_work_Periode['sumWork'] - $matrice_work_Periode['potential_works'];
							$collaborateur_doit = 0;
						}   
						?>
						<table class="table small" cellspacing="4" cellpadding="4">
						  	<tbody>
								<tr>
									<td>
										Total ore: <span class="pull-right"><?php echo format_time($matrice_work_Periode['potential_works']); ?></span> <br />
										Muncă: <span class="pull-right"><?php echo format_time($matrice_work_Periode['sumWork']); ?></span> <br />
										Ore suplimentare.: <span class="pull-right"><?php echo format_time($matrice_work_Periode['heuresSup']); ?></span> <br />
										Colaboratorul trebuie să realizeze: <span class="pull-right"><?php echo format_time($collaborateur_doit); ?></span> <br /> 
										<br/>
									</td>
								</tr>
							</tbody>
						</table>			

					</td>
				</tr>
				<tr>
					<td bgcolor="#cce6ff" >
						<table class="table small" cellspacing="4" cellpadding="4">
						  	<tbody>
								<tr>
									<td>Muncă: <?php echo format_time($sumWorkPeriode); ?> </td>
									<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#5cb85c;"><?php echo format_time($sumTravailPeriode); ?></div></td>
									<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#185a18;"><?php echo format_time($sumSAVPeriode); ?></div></td>
									<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#00ffbf"><?php echo format_time($sumEcolePeriode); ?></div></td>								
									<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#00ff80;"><?php echo format_time($sumFeriePeriode); ?></div></td>
									<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#8000ff"><?php echo format_time($sumMaladiePeriode); ?></div></td>								
									<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#ffbf00;"><?php echo format_time($sumCongesPeriode); ?></div></td>
									<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#ff0000"><?php echo format_time($sumRecupPeriode); ?></div></td>
								</tr>
								<tr>
									<td colspan="7"> 
										<i class="fa fa-shopping-basket" aria-hidden="true"></i> Total pachet: <?php echo $nbpanierPeriode; ?> ( <b><?php echo price($nbpanierPeriode*$prix_panier);?> €</b> )
										&nbsp;&nbsp;&nbsp;&nbsp;
										<i class="fa fa-road" aria-hidden="true"></i> Total călătorii: <?php echo $nbtrajetPeriode; ?> <?php echo $ok_labelcost_trajets_periode;?> 
										&nbsp;&nbsp;&nbsp;&nbsp;
				  						<i class="fa fa-moon-o" aria-hidden="true"></i> Total călătorie pe deplasare mare: <?php echo $nbtrajetGDPeriode; ?> <?php echo $ok_labelcost_trajetsGD_periode;?> 
									</td>
								</tr>
							</tbody>
						</table>
					</td>
					<td></td>
				</tr>
			</tbody>
		</table>		
	</div>



	<!-- BOTTOM TABLE ( LEGEND ) -->
	<table class="table small" cellspacing="4" cellpadding="4">
	  	<tbody>
			<tr>
				<td>Legende: </td>
				<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#5cb85c;">Muncă</div></td>
				<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#185a18;">MY/Serviciu</div></td>
				<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#00ffbf">Școală</div></td>								
				<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#00ff80;">Vacanță</div></td>
				<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#8000ff">Maladie</div></td>								
				<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#ffbf00;">Concediu</div></td>
				<td align="center"><div class="" style="color:#fff; padding:2px; background-color:#ff0000">Recupera</div></td>
			</tr>
		</tbody>
	</table>

</div>

<?php else : ?>

Selectează o perioadă și un colaborator pentru a consulta timpul de lucru.

<?php endif; ?>	

<?php
// End of page
llxFooter();

$db->close();

/*

2017 array full
******************************************************************************************************
array (size=12)
  1 => 
    array (size=6)
      0 => 
        array (size=7)
          1 => boolean false
          2 => boolean false
          3 => boolean false
          4 => boolean false
          5 => boolean false
          6 => boolean false
          7 => int 1
      1 => 
        array (size=7)
          1 => int 2
          2 => int 3
          3 => int 4
          4 => int 5
          5 => int 6
          6 => int 7
          7 => int 8
      2 => 
        array (size=7)
          1 => int 9
          2 => int 10
          3 => int 11
          4 => int 12
          5 => int 13
          6 => int 14
          7 => int 15
      3 => 
        array (size=7)
          1 => int 16
          2 => int 17
          3 => int 18
          4 => int 19
          5 => int 20
          6 => int 21
          7 => int 22
      4 => 
        array (size=7)
          1 => int 23
          2 => int 24
          3 => int 25
          4 => int 26
          5 => int 27
          6 => int 28
          7 => int 29
      5 => 
        array (size=7)
          1 => int 30
          2 => int 31
          3 => boolean false
          4 => boolean false
          5 => boolean false
          6 => boolean false
          7 => boolean false
  2 => 
    array (size=5)
      0 => 
        array (size=7)
          1 => boolean false
          2 => boolean false
          3 => int 1
          4 => int 2
          5 => int 3
          6 => int 4
          7 => int 5
      1 => 
        array (size=7)
          1 => int 6
          2 => int 7
          3 => int 8
          4 => int 9
          5 => int 10
          6 => int 11
          7 => int 12
      2 => 
        array (size=7)
          1 => int 13
          2 => int 14
          3 => int 15
          4 => int 16
          5 => int 17
          6 => int 18
          7 => int 19
      3 => 
        array (size=7)
          1 => int 20
          2 => int 21
          3 => int 22
          4 => int 23
          5 => int 24
          6 => int 25
          7 => int 26
      4 => 
        array (size=7)
          1 => int 27
          2 => int 28
          3 => boolean false
          4 => boolean false
          5 => boolean false
          6 => boolean false
          7 => boolean false
  3 => 
    array (size=5)
      0 => 
        array (size=7)
          1 => boolean false
          2 => boolean false
          3 => int 1
          4 => int 2
          5 => int 3
          6 => int 4
          7 => int 5
      1 => 
        array (size=7)
          1 => int 6
          2 => int 7
          3 => int 8
          4 => int 9
          5 => int 10
          6 => int 11
          7 => int 12
      2 => 
        array (size=7)
          1 => int 13
          2 => int 14
          3 => int 15
          4 => int 16
          5 => int 17
          6 => int 18
          7 => int 19
      3 => 
        array (size=7)
          1 => int 20
          2 => int 21
          3 => int 22
          4 => int 23
          5 => int 24
          6 => int 25
          7 => int 26
      4 => 
        array (size=7)
          1 => int 27
          2 => int 28
          3 => int 29
          4 => int 30
          5 => int 31
          6 => boolean false
          7 => boolean false
  4 => 
    array (size=5)
      0 => 
        array (size=7)
          1 => boolean false
          2 => boolean false
          3 => boolean false
          4 => boolean false
          5 => boolean false
          6 => int 1
          7 => int 2
      1 => 
        array (size=7)
          1 => int 3
          2 => int 4
          3 => int 5
          4 => int 6
          5 => int 7
          6 => int 8
          7 => int 9
      2 => 
        array (size=7)
          1 => int 10
          2 => int 11
          3 => int 12
          4 => int 13
          5 => int 14
          6 => int 15
          7 => int 16
      3 => 
        array (size=7)
          1 => int 17
          2 => int 18
          3 => int 19
          4 => int 20
          5 => int 21
          6 => int 22
          7 => int 23
      4 => 
        array (size=7)
          1 => int 24
          2 => int 25
          3 => int 26
          4 => int 27
          5 => int 28
          6 => int 29
          7 => int 30
  5 => 
    array (size=5)
      0 => 
        array (size=7)
          1 => int 1
          2 => int 2
          3 => int 3
          4 => int 4
          5 => int 5
          6 => int 6
          7 => int 7
      1 => 
        array (size=7)
          1 => int 8
          2 => int 9
          3 => int 10
          4 => int 11
          5 => int 12
          6 => int 13
          7 => int 14
      2 => 
        array (size=7)
          1 => int 15
          2 => int 16
          3 => int 17
          4 => int 18
          5 => int 19
          6 => int 20
          7 => int 21
      3 => 
        array (size=7)
          1 => int 22
          2 => int 23
          3 => int 24
          4 => int 25
          5 => int 26
          6 => int 27
          7 => int 28
      4 => 
        array (size=7)
          1 => int 29
          2 => int 30
          3 => int 31
          4 => boolean false
          5 => boolean false
          6 => boolean false
          7 => boolean false
  6 => 
    array (size=5)
      0 => 
        array (size=7)
          1 => boolean false
          2 => boolean false
          3 => boolean false
          4 => int 1
          5 => int 2
          6 => int 3
          7 => int 4
      1 => 
        array (size=7)
          1 => int 5
          2 => int 6
          3 => int 7
          4 => int 8
          5 => int 9
          6 => int 10
          7 => int 11
      2 => 
        array (size=7)
          1 => int 12
          2 => int 13
          3 => int 14
          4 => int 15
          5 => int 16
          6 => int 17
          7 => int 18
      3 => 
        array (size=7)
          1 => int 19
          2 => int 20
          3 => int 21
          4 => int 22
          5 => int 23
          6 => int 24
          7 => int 25
      4 => 
        array (size=7)
          1 => int 26
          2 => int 27
          3 => int 28
          4 => int 29
          5 => int 30
          6 => boolean false
          7 => boolean false
  7 => 
    array (size=6)
      0 => 
        array (size=7)
          1 => boolean false
          2 => boolean false
          3 => boolean false
          4 => boolean false
          5 => boolean false
          6 => int 1
          7 => int 2
      1 => 
        array (size=7)
          1 => int 3
          2 => int 4
          3 => int 5
          4 => int 6
          5 => int 7
          6 => int 8
          7 => int 9
      2 => 
        array (size=7)
          1 => int 10
          2 => int 11
          3 => int 12
          4 => int 13
          5 => int 14
          6 => int 15
          7 => int 16
      3 => 
        array (size=7)
          1 => int 17
          2 => int 18
          3 => int 19
          4 => int 20
          5 => int 21
          6 => int 22
          7 => int 23
      4 => 
        array (size=7)
          1 => int 24
          2 => int 25
          3 => int 26
          4 => int 27
          5 => int 28
          6 => int 29
          7 => int 30
      5 => 
        array (size=7)
          1 => int 31
          2 => boolean false
          3 => boolean false
          4 => boolean false
          5 => boolean false
          6 => boolean false
          7 => boolean false
  8 => 
    array (size=5)
      0 => 
        array (size=7)
          1 => boolean false
          2 => int 1
          3 => int 2
          4 => int 3
          5 => int 4
          6 => int 5
          7 => int 6
      1 => 
        array (size=7)
          1 => int 7
          2 => int 8
          3 => int 9
          4 => int 10
          5 => int 11
          6 => int 12
          7 => int 13
      2 => 
        array (size=7)
          1 => int 14
          2 => int 15
          3 => int 16
          4 => int 17
          5 => int 18
          6 => int 19
          7 => int 20
      3 => 
        array (size=7)
          1 => int 21
          2 => int 22
          3 => int 23
          4 => int 24
          5 => int 25
          6 => int 26
          7 => int 27
      4 => 
        array (size=7)
          1 => int 28
          2 => int 29
          3 => int 30
          4 => int 31
          5 => boolean false
          6 => boolean false
          7 => boolean false
  9 => 
    array (size=5)
      0 => 
        array (size=7)
          1 => boolean false
          2 => boolean false
          3 => boolean false
          4 => boolean false
          5 => int 1
          6 => int 2
          7 => int 3
      1 => 
        array (size=7)
          1 => int 4
          2 => int 5
          3 => int 6
          4 => int 7
          5 => int 8
          6 => int 9
          7 => int 10
      2 => 
        array (size=7)
          1 => int 11
          2 => int 12
          3 => int 13
          4 => int 14
          5 => int 15
          6 => int 16
          7 => int 17
      3 => 
        array (size=7)
          1 => int 18
          2 => int 19
          3 => int 20
          4 => int 21
          5 => int 22
          6 => int 23
          7 => int 24
      4 => 
        array (size=7)
          1 => int 25
          2 => int 26
          3 => int 27
          4 => int 28
          5 => int 29
          6 => int 30
          7 => boolean false
  10 => 
    array (size=6)
      0 => 
        array (size=7)
          1 => boolean false
          2 => boolean false
          3 => boolean false
          4 => boolean false
          5 => boolean false
          6 => boolean false
          7 => int 1
      1 => 
        array (size=7)
          1 => int 2
          2 => int 3
          3 => int 4
          4 => int 5
          5 => int 6
          6 => int 7
          7 => int 8
      2 => 
        array (size=7)
          1 => int 9
          2 => int 10
          3 => int 11
          4 => int 12
          5 => int 13
          6 => int 14
          7 => int 15
      3 => 
        array (size=7)
          1 => int 16
          2 => int 17
          3 => int 18
          4 => int 19
          5 => int 20
          6 => int 21
          7 => int 22
      4 => 
        array (size=7)
          1 => int 23
          2 => int 24
          3 => int 25
          4 => int 26
          5 => int 27
          6 => int 28
          7 => int 29
      5 => 
        array (size=7)
          1 => int 30
          2 => int 31
          3 => boolean false
          4 => boolean false
          5 => boolean false
          6 => boolean false
          7 => boolean false
  11 => 
    array (size=5)
      0 => 
        array (size=7)
          1 => boolean false
          2 => boolean false
          3 => int 1
          4 => int 2
          5 => int 3
          6 => int 4
          7 => int 5
      1 => 
        array (size=7)
          1 => int 6
          2 => int 7
          3 => int 8
          4 => int 9
          5 => int 10
          6 => int 11
          7 => int 12
      2 => 
        array (size=7)
          1 => int 13
          2 => int 14
          3 => int 15
          4 => int 16
          5 => int 17
          6 => int 18
          7 => int 19
      3 => 
        array (size=7)
          1 => int 20
          2 => int 21
          3 => int 22
          4 => int 23
          5 => int 24
          6 => int 25
          7 => int 26
      4 => 
        array (size=7)
          1 => int 27
          2 => int 28
          3 => int 29
          4 => int 30
          5 => boolean false
          6 => boolean false
          7 => boolean false
  12 => 
    array (size=5)
      0 => 
        array (size=7)
          1 => boolean false
          2 => boolean false
          3 => boolean false
          4 => boolean false
          5 => int 1
          6 => int 2
          7 => int 3
      1 => 
        array (size=7)
          1 => int 4
          2 => int 5
          3 => int 6
          4 => int 7
          5 => int 8
          6 => int 9
          7 => int 10
      2 => 
        array (size=7)
          1 => int 11
          2 => int 12
          3 => int 13
          4 => int 14
          5 => int 15
          6 => int 16
          7 => int 17
      3 => 
        array (size=7)
          1 => int 18
          2 => int 19
          3 => int 20
          4 => int 21
          5 => int 22
          6 => int 23
          7 => int 24
      4 => 
        array (size=7)
          1 => int 25
          2 => int 26
          3 => int 27
          4 => int 28
          5 => int 29
          6 => int 30
          7 => int 31


*/