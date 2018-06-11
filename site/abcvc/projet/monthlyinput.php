<?php
/* Copyright (C) 2005		Rodolphe Quiedeville 	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2013	Laurent Destailleur  	<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2010	Regis Houssin        	<regis.houssin@capnetworks.com>
 * Copyright (C) 2010     	François Legastelois 	<flegastelois@teclib.com>
 * Copyright (C) 2014-2017	charlie BENKE			<charlie@patas-monkey.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       /management/projet/listtime.php
 *	\ingroup    projet
 *	\brief      List activities of tasks
 */

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");		// For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");	// For "custom" directory

require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/html.formprojet.class.php';

dol_include_once ('/management/core/lib/management.lib.php');

dol_include_once ('/user/class/usergroup.class.php');


$langs->load('management@management');

$action=GETPOST('action');
$mode=GETPOST("mode");
$id=GETPOST('id','int');

$periodyear=GETPOST('periodyear','int');
if (!$periodyear)
	$periodyear=date('Y');

$periodmonth=GETPOST('periodmonth','int');
if (!$periodmonth)
	$periodmonth=date('m');

$perioduser=GETPOST('perioduser','int');
//user logué par défaut
if (!$perioduser) $perioduser=$user->id;

//var_dump('user:'.$perioduser);
//var_dump($user);

$objusergroup = new UserGroup($db);
$usergroups = $objusergroup->listGroupsForUser($perioduser);
//var_dump($usergroups);

if(array_key_exists(6,$usergroups)){
	$collaborateur = true;
} else {
	$collaborateur = false;
}
//var_dump($collaborateur);



// //$projectsListId
// $projectsListId=GETPOST('projectid','int');
// var_dump('projectsListId:'.$projectsListId);

$projectid='';
$projectid=isset($_GET["id"])?$_GET["id"]:$_POST["projectid"];
//var_dump('projectid:'.$projectid);

//var_dump($_POST);
//exit();

//$test_timestamp = 1506902400;
//var_dump(date('d/m/Y H:i:s',$test_timestamp));
//exit();



// récupération du nombre de jour dans le mois
$time = mktime(0, 0, 0, $periodmonth+1, 1, $periodyear); // premier jour du mois suivant
$time--; // Recule d'une seconde
$nbdaymonth=date('d', $time); // on récupère le max jour
//var_dump( $nbdaymonth );
//exit();

$date_sql_de = date('Y-m-d H:i:s',mktime(0, 0, 0, $periodmonth, 1, $periodyear) );
$date_sql_a = date('Y-m-d H:i:s',$time);
// var_dump( $date_sql_de );
// var_dump( $date_sql_a );



// Security check
$socid=0;
if ($user->societe_id > 0) $socid=$user->societe_id;
//$result = restrictedArea($user, 'projet', $projectid);

$form=new Form($db);
$formprojets=new FormProjets($db);
$formother = new FormOther($db);
$projectstatic=new ProjectABCVC($db);
$project = new ProjectABCVC($db);
$taskstatic = new TaskABCVC($db);

//$projectsListId = $projectstatic->getProjectsAuthorizedForUser($user,0,1);  
// Return all project i have permission on. I want my tasks and some of my task may be on a public projet that is not my project

if( ($projectid!='') && ($projectid!=0) ){
	$tasksarray=$taskstatic->getTasksArray(0,0,$projectid,$socid,0);   
	//user obj de perioduser !!
	$userobj = new User($db);
	$userobj->fetch($perioduser);
	//var_dump($user_period); 
	$projectsrole=$taskstatic->getUserRolesForProjectsOrTasks($userobj,0,$projectid,0);
	$tasksrole=$taskstatic->getUserRolesForProjectsOrTasks(0,$userobj,$projectid,0);
}
//var_dump($projectsrole);
//var_dump($tasksrole);
/*array (size=1)
  35 => string 'TASKEXECUTIVE' (length=13)
*/


/*
 * Actions
 */
//obsolete...
if ($action == 'xxaddtime' && $user->rights->projet->creer)
{
	// on boucle sur les lignes de taches 
	setsheetLines($j, 0, $tasksarray, $level, $projectsrole, $tasksrole, $perioduser);
	
	setEventMessage($langs->trans("RecordSaved"));
	
	$action ="";
	// Redirect to avoid submit twice on back
	header('Location: '.$_SERVER["PHP_SELF"].'?id='.$projectid.($mode?'&mode='.$mode:''));
	exit;
}


if ($action == 'ajax_add_time' ){

	$timespent = array(
		'fk_task' => GETPOST('fk_task','int'),
		'task_date' => GETPOST('task_date','int'),
		'task_datehour' => GETPOST('task_datehour','int'),
		'heure_de' => GETPOST('heure_de'),
		'task_date_withhour' => 0,
		'task_duration' => GETPOST('task_duration','int'),
		'task_type' => GETPOST('task_type','int'),
		'fk_user' => GETPOST('fk_user','int'),
		'thm' => 0,
		'note' => GETPOST('note'),
		'mode' => GETPOST('mode'),
		'timespentid' => GETPOST('timespentid')
	);
	//var_dump($timespent);
	//exit();
	
	$return_add = $taskstatic->addTimeSpent($timespent);

	//$return_add = $object->create_lot($user,$timespent);
	if($return_add>0){
		$statut = 'ok';
	} else {
		$statut = 'ko';
	}

	$return = array(
		'statut'=>$statut,
		'message'=>$return_add
	);
	echo json_encode($return);
	exit;
}

if ($action == 'ajax_del_time' ){

	$timespent = array(
		'timespentid' => GETPOST('timespentid')
	);
	//var_dump($timespent);
	//exit();
	
	$return_add = $taskstatic->kdelTimeSpent($timespent);

	//$return_add = $object->create_lot($user,$timespent);
	if($return_add>0){
		$statut = 'ok';
	} else {
		$statut = 'ko';
	}

	$return = array(
		'statut'=>$statut,
		'message'=>$return_add
	);
	echo json_encode($return);
	exit;
}



/*
 * View
 */


$title=$langs->trans("TimeSpentPeriod");

llxHeader("",$title,"");


//print_barre_liste($title, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $num);
dol_htmloutput_mesg($mesg);


// BOOTSTRAP 3 + css + js custom
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/abcvc_js_css.php';?>

<!-- header -->
<div class="panel panel-info filterable">
    <div class="panel-heading">
		<form name="selectperiod" method="POST" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
			<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">
			<input type="hidden" name="action" value="selectperiod">
			<?php
			print_barre_liste($title, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $num);
			?>

			<div class="row">
				<div class="col-sm-2">
					Perioada
				</div>
				<div class="col-sm-10">
					<?php echo $formother->selectyear($periodyear,'periodyear'); echo $formother->select_month($periodmonth,'periodmonth'); ?>
					<div id="lblBlurWeekEnd" class="pull-right">
						<a href="#" onclick="return false;">
							<?php 
							//echo img_picto("ShowHideWeekendColumns","edit_add"); 
							echo $langs->trans("ShowHideWeekend"); 
							?>
						</a>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-sm-2">
					Colaborator
				</div>
				<div class="col-sm-2">
					
					<?php if(!$collaborateur): ?>
						<?php 
							$showempty=0;
							// attention le dernier paramétre n'est dispo que sur la 3.7 et le patch fournis
							$filteruser="";
							//if ($user->admin == 0) $filteruser=" AND (u.rowid = ".$user->id." OR fk_user=".$user->id.")";
						?>
						<?php echo $form->select_dolusers($perioduser, 'perioduser', $showempty, '', 0,'', '', 0, 0, 0, $filteruser); ?>

					<?php else: ?>
						
						<?php echo $user->firstname.' '.strtoupper($user->lastname); ?>

					<?php endif; ?>	

				</div>
			</div>
			<div class="row">
				<div class="col-sm-2">
					Proiect
				</div>
				<div class="col-sm-8">
					<?php echo $formprojets->select_projects_list_collaborateurs(-1,$projectid,'projectid',24,0,1,1); ?>
				</div>
				<div class="col-sm-2">
					<div class="pull-right">
						<input type=submit name="select" class="btn btn-default pull-right" value="Afișează">
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-sm-12">
				<?php if( ($perioduser=='') || ($projectid=='') || ($projectid=='0') ):?>
					Selectează o perioadă, un colaborator și un proiect pentru a seta programul
				<?php else:?>
					&nbsp;
				<?php endif;?>
				</div>
			</div>

		</form>
	</div>
</div>	


<form name="addtime" method="POST" action="<?php echo $_SERVER["PHP_SELF"]; ?>?id=<?php echo $project->id; ?>">
	<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">
	<input type="hidden" name="action" value="addtime">
	<input type="hidden" name="mode" value="<?php echo $mode; ?>">

	<input type="hidden" name="periodyear" value="<?php echo $periodyear; ?>">
	<input type="hidden" name="periodmonth" value="<?php echo $periodmonth; ?>">
	<?php  /*
	<input type="hidden" name="perioduser" value="<?php echo $perioduser; ?>">
	*/ ?>

	<table class="noborder table small table-hover" width="100%">
		<tr class="liste_titre">
			<td width="20%">
				Proiect / Ref. sarcină
				<div class="type_time_legend">
					<table class="small" cellspacing="4" cellpadding="4">
					  	<tbody>
							<tr>
								<td >Legendă: </td>
								<td ><div class="" style="color:#fff; padding:2px; background-color:#5cb85c;">Muncă</div></td>
								<td ><div class="" style="color:#fff; padding:2px; background-color:#185a18;">MY/Serviciu</div></td>
								
								<td ><div class="" style="color:#fff; padding:2px; background-color:#00ffbf">Școală</td>								
								
								<td ><div class="" style="color:#fff; padding:2px; background-color:#00ff80;">Vacanță</td>
								<td ><div class="" style="color:#fff; padding:2px; background-color:#8000ff">Boală</td>								
								<td ><div class="" style="color:#fff; padding:2px; background-color:#ffbf00;">Concediu</div></td>
								<td ><div class="" style="color:#fff; padding:2px; background-color:#ff0000">Recupera</td>
							</tr>
						</tbody>
					</table>
				</div>				
			</td>
			<td colspan="<?php echo $nbdaymonth;?>" align="right">
				<?php echo $langs->trans("TimeSpent"); 
				//	&nbsp;
				//<input type="submit" name="save" class="btn btn-success" value="Enregistrer">
				?>
			</td>
		</tr>
		<?php
		if( ($projectid!='') && ($projectid!=0) ){
			//timesheetLines($j, 0, $tasksarray, $level, $projectsrole, $tasksrole, $mine, $perioduser);

			//recup structure poste/timespent periode/user
			$timeSpentProjet = $projectstatic->getTimespentProjectsTree($projectid, $perioduser, $date_sql_de, $date_sql_a);
			//
			
			//recup structure timespent / jours
			$projectstatic->fetch($projectid);
			//var_dump($projectstatic);
			//exit();
			$projectstatic->loadTimeSpentAll($date_sql_de, $date_sql_a, 0, $perioduser);	
						
			//var_dump($projectstatic->weekWorkLoad);
			//var_dump($projectstatic->weekWorkLoadPerTask);
			//exit;
			//var_dump($projectstatic->weekNotePerTask);	

			/*
			array (size=1)
			  1507075200 => 
			    array (size=1)
			      35 => 
			        array (size=9)
			          'date_debut' => string '2017-10-04 00:00:00' (length=19)
			          'debut' => string '00:00' (length=5)
			          'debut_sec' => int 0
			          'date_fin' => string '2017-10-04 07:00:00' (length=19)
			          'fin' => string '07:00' (length=5)
			          'fin_sec' => int 25200
			          'duration_sec' => string '25200' (length=5)
			          'type' => string '0' (length=1)
			          'note' => string 'blahblah' (length=8)

			*/
		
			//header tableau timespent / jours du mois en cours
			//---------------------------------------------------------------
			?>
			<tr>
				<td >
					<?php
					echo $projectstatic->getKNomUrl(1);
					?>
				</td>
				<?php
					for ($day=1;$day <= $nbdaymonth ;$day++) {
						$curday=mktime(0, 0, 0, $periodmonth, $day, $periodyear);
						$bgcolor="";

						if (date('N', $curday) == 6 || date('N', $curday) == 7)	{
							?>
							<td width=1px class=weekendtoshow bgcolor=grey ></td>
							<?php
							$bgcolor=" class=weekendtohide bgcolor=grey ";
						}
						?>
						<td <?php echo $bgcolor; ?> align=center>
						<?php
							echo substr($langs->trans(date('l', $curday)),0,1)." ".$day;
						?>
						</td>
						<?php
					}
				?>
			</tr>

			<?php
			//ligne poste..
			//---------------------------------------------------------------
			?>

			<?php foreach ($timeSpentProjet as $poste) : 

				/*
				      public 'rowid' => string '35' (length=2)
				      public 'ref' => string '1.1.1' (length=5)
				      public 'fk_categorie' => string '2' (length=1)
				      public 'fk_task_parent' => string '0' (length=1)
				      public 'label' => string 'Poste 1' (length=7)
				      public 'datec' => string '2017-08-02 20:00:32' (length=19)
				      public 'fk_user_creat' => string '2' (length=1)
				      public 'fk_statut' => string '0' (length=1)
				      public 'planned_workload' => string '360611.99971151' (length=15)
				      public 'progress' => string '85' (length=2)
				      public 'cost' => string '10.00000000' (length=11)
				      public 'progress_estimated' => string '0' (length=1)
				      public 'fact_fourn' => string '' (length=0)
				      public 'poste_pv' => string '0.00000000' (length=10)
				*/

			?>
				
			<tr>

				<td >
					<?php 
					// 
					$taskstatic->id=$poste->rowid;
					?>
					<b><?php echo $poste->ref .' '.$poste->label;?></b>
				</td>

				<?php 
				for ($day=1;$day <= $nbdaymonth ;$day++) {
					$curday=mktime(0, 0, 0, $periodmonth, $day, $periodyear);

					$timespent = $projectstatic->weekWorkLoadPerTask[$curday][$poste->rowid];
					$dayWorkLoad = $timespent['duration_sec'];
					// var_dump($curday);
					//var_dump($timespent);
					// var_dump($dayWorkLoad);
					// exit();

		  			$dayNote = $projectstatic->weekNotePerTask[$curday][$poste->rowid];

					$bgcolor="";

					if (date('N', $curday) == 6 || date('N', $curday) == 7) { ?>
						<td width=1px class="weekendtoshow" bgcolor="grey" ></td>
						<?php $bgcolor=" class='weekendtohide' bgcolor='grey' ";
					}
					?>
					<td <?php echo $bgcolor; ?> align=center>
						<?php //USER EST BIEN AFFECTE AU POSTE ?  ?>
						<?php if( in_array($taskstatic->id,array_keys($tasksrole)) || $user->rights->abcvc->timespentadmin == 1 ): 

						if(is_null($timespent)){
							$mode = 'insert';
						} else {
							$mode = 'update';
						}
						?>
								
							<div class="timespent_per_task_day" id="div-hrs-<?php echo $poste->rowid.'-'.$day; ?>"
								data-task_id="<?php echo $poste->rowid; ?>" 
								data-day="<?php echo $day; ?>"
								data-task_ref="<?php echo $poste->ref;?>" 
								data-task_label="<?php echo $poste->label;?>"

								data-date_start="<?php echo $curday;?>"
								data-debut="<?php echo $timespent['debut'];?>"
								data-debut_sec="<?php echo $timespent['debut_sec'];?>"

								data-fin="<?php echo $timespent['fin'];?>"
								data-fin_sec="<?php echo $timespent['fin_sec'];?>"

								data-duration_sec="<?php echo $timespent['duration_sec'];?>"

								data-type="<?php echo $timespent['type'];?>"

								data-user="<?php echo $perioduser;?>"

								data-mode="<?php echo $mode;?>"

								data-timespentid="<?php echo $timespent['timespentid'];?>" 
								
								>
		
								<?php 
								$heure_debut = $timespent['debut'];
								if($heure_debut==":"){
									$heures_debut_fin = '';
								} else {
									$hours = floor( ($timespent['debut_sec']+$timespent['duration_sec']) / 3600);
									$minutes = floor(( ($timespent['debut_sec']+$timespent['duration_sec']) / 60) % 60);
									$heure_fin = $hours.":".$minutes;

									$heures_debut_fin = $heure_debut.'-'.$heure_fin;
								}
								//$heures_debut_fin = '9:00-10:00';
								?>
								<input type=text 
								id="hrs-<?php echo $poste->rowid.'-'.$day; ?>" 
								name="hrs-<?php echo $poste->rowid.'-'.$day; ?>"
								size=1 style="font-size:0.7em">
								<?php /*
								<input type="hidden" name="hrsdebutfin-<?php echo $poste->rowid.'-'.$day; ?>"	value="<?php echo $heures_debut_fin;?>" >

								<input type=text id="hrs-<?php echo $poste->rowid.'-'.$day; ?>" name="hrs-<?php echo $poste->rowid.'-'.$day; ?>"
			                     <?php
								 	if ($timespent['duration_sec'] !=0)
									echo ' value="'.round($timespent['duration_sec']/3600,2).'"';
								 ?>
								 size=1 style="font-size:0.7em">

								
								//var_dump( strtotime('2017-09-11 00:00:00') );
								//echo 'da';
								//if($dayWorkLoad) {
								//	echo convertSecondToTime($dayWorkLoad);
								//} else {
								//	echo '-';
								//} */

								?>

							</div>

						<?php else: ?>	
						-
						<?php endif; ?>

					</td>
					<?php
				}
				?>

			</tr>	

			<tr>
				<td >
					<div class="row">
						<div class="col-xs-2">
							Estimat: 
						</div>
						<div class="col-xs-10">
							<div class="progress" >
								<?php 
								if($poste->progress_estimated<80){
									$progress_color = 'progress-bar-success';
								} elseif($poste->progress_estimated<100){
									$progress_color = 'progress-bar-warning';
								} elseif($poste->progress_estimated ==100){
									$progress_color = 'progress-bar-info';
								} else {
									$progress_color = 'progress-bar-danger';
								}
								?>
							  	<div class="progress-bar <?php echo $progress_color;?>" role="progressbar" aria-valuenow="<?php echo $poste->progress_estimated; ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo ($poste->progress_estimated<=100)?$poste->progress_estimated:'100'; ?>%;">
							    	<?php echo $poste->progress_estimated; ?>%
							  	</div>
							</div>
						</div>	
					</div>	
					<div class="row">
						<div class="col-xs-2">
							Real: 
						</div>
						<div class="col-xs-10">
							<div class="progress" >
								<?php 
								if($poste->progress<80){
									$progress_color = 'progress-bar-success';
								} elseif($poste->progress<100){
									$progress_color = 'progress-bar-warning';
								} elseif($poste->progress ==100){
									$progress_color = 'progress-bar-info';
								} else {
									$progress_color = 'progress-bar-danger';
								}
								?>
					  			<div class="progress-bar <?php echo $progress_color;?>" role="progressbar" aria-valuenow="<?php echo $poste->progress; ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo ($poste->progress<=100)?$poste->progress:'100'; ?>%;">
					    			<?php echo $poste->progress; ?>%
					  			</div>
							</div>
						</div>
					</div>		
				</td>

				<?php
				//loop jours mois / infos timespent effectue 
				for ($day=1;$day <= $nbdaymonth;$day++) {
					$curday=mktime(0, 0, 0, $periodmonth, $day, $periodyear);

					$timespent = $projectstatic->weekWorkLoadPerTask[$curday][$poste->rowid];

					if(!is_null($timespent)){
						//var_dump( $timespent );
						$dayWorkLoad = $timespent['duration_sec'];
		  				$dayNote = $projectstatic->weekNotePerTask[$curday][$poste->rowid];

						$bgcolor="";
						if (date('N', $curday) == 6 || date('N', $curday) == 7) {?>
							<td width="1px" class="weekendtoshow" bgcolor="grey" ></td>
							<?php $bgcolor=" class='weekendtohide' bgcolor='grey' ";
							$tohide = "class='weekendtohide'";
						} else {
							$tohide = '';
						}
						
						//var_dump( $timespent['type'] );
						//exit();

						//type timespent
						//----------------------------------------------------------
						if( $timespent['type'] == 0 ) $bgcolor="bgcolor='#5cb85c'";	//Travail 
						if( $timespent['type'] == 6 ) $bgcolor="bgcolor='#185a18'";	//MES / SAV 
						if( $timespent['type'] == 1 ) $bgcolor="bgcolor='#ffbf00'";	//Conges
						if( $timespent['type'] == 2 ) $bgcolor="bgcolor='#00ff80'";	//Ferie
						if( $timespent['type'] == 3 ) $bgcolor="bgcolor='#8000ff'";	//Maladie
						if( $timespent['type'] == 4 ) $bgcolor="bgcolor='#ff0000'";	//Recup
						if( $timespent['type'] == 5 ) $bgcolor="bgcolor='#00ffbf'";	//Ecole
							
						?>


					
						<?php 
						/*
						<td ><div class="" style="color:#fff; padding:2px; background-color:#5cb85c;">Travail</div></td>
						<td ><div class="" style="color:#fff; padding:2px; background-color:#ffbf00;">Conges</div></td>
						<td ><div class="" style="color:#fff; padding:2px; background-color:#00ff80;">Ferie</td>
						<td ><div class="" style="color:#fff; padding:2px; background-color:#8000ff">Maladie</td>
						<td ><div class="" style="color:#fff; padding:2px; background-color:#ff0000">Recup</td>
						<td ><div class="" style="color:#fff; padding:2px; background-color:#00ffbf">Ecole</td>
						*/
						?>

						<td <?php echo $tohide; ?> <?php echo $bgcolor; ?> valign=top align=center>
							<?php 
			  				//print_r($dayWorkLoad);
							if($dayWorkLoad) :?>
								<div class="infos_task">
									<?php echo convertSecondToTime($dayWorkLoad); ?>
								
									<?php if($dayNote) :?>
										<a title="<?php echo $dayNote; ?>"><i class="fa fa-commenting-o" aria-hidden="true"></i></a>
									<?php endif; ?>
									<br />
									<textarea style="display:none" id="txt-<?php echo $taskstatic->id; ?>-<?php echo $day; ?>"><?php if ($dayNote)echo $dayNote;?></textarea>
									<br />

									<a href="#" class="bt_removetimespent" data-timespentid="<?php echo $timespent['timespentid'];?>" title="Suppresion de ce temps affecté"><i class="fa fa-trash-o" style="color:#d9534f;" aria-hidden="true"></i></a>
								</div>	
							<?php endif; ?>
								
						</td>		
					<?php
					} else {

						$bgcolor="";
						if (date('N', $curday) == 6 || date('N', $curday) == 7) { ?>
							<td width="1px" class="weekendtoshow" bgcolor="grey" ></td>
							<?php $bgcolor=" class='weekendtohide' bgcolor='grey' ";
						}
						?>
						<td <?php echo $bgcolor; ?> valign=top align=center>
						</td>
					<?php	
					}
					?>
				<?php
				}
				?>
			</tr>				

			<?php endforeach; ?>		

		<?php
		}
		?>
	</table>
</form>

<?php 

//var_dump($timeSpentProjet);

?>

<!-- pour activer ou non le week-end -->
<script language="javascript">
	$(document).ready(function() {
		$(".weekendtohide").toggle();
		$("#lblBlurWeekEnd").click(function() {
			$(".weekendtohide").toggle();
			$(".weekendtoshow").toggle();
		});
	});
</script>






<div class="modal fade" id="timespent_Modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
	    <div class="modal-header">
        	<h4 class="modal-title" id="myModalLabel" style="position: relative;">
				Timp petrecut
        		<div id="qui_jour_tempspasse"> 
        			<span class="label_task" id="jour_tempspasse">00/00/0000</span> - <span class="label_task" id="qui_tempspasse"> - </span>
        		</div>
	        	<div class="" id="timespent_header_code">
	       			<div class="ref_task"></div>
	       			<div class="label_task"></div>
	       		</div>
        	</h4>
	    </div>
		<div class="modal-body">

			<div class="row form-horizontal">

				<div class="col-md-12">				

					<div class="form-group">
						<label class="col-sm-3 control-label" for="edit_tempspasse">Timp petrecut *</label>
						<div class="col-sm-9">

							<div class="input-group">
							  <span class="input-group-addon"><i class="fa fa-clock-o" aria-hidden="true"></i></span>
							  <input type="text" id="edit_tempspasse_de" class="required col-xs-2" required="" value="">
							  <input type="text" id="edit_tempspasse_a" class="required col-xs-2" required="" value="">

							  <spam id="preview_tempspasse"></spam>
							</div>						

						</div>	
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label">Tip *</label>
						<div class="col-sm-9">
							<select name="type_tempspasse" class="form-control required" id="edit_type_tempspasse"  required="">
								<option value="0">Muncă</option>
								<option value="6">MY/Serviciu</option>
								<option value="5">Școală</option>
								<option value="1">Concediu plătit</option>
								<option value="3">Boală</option>
								<option value="2">Concediu</option>
								<option value="4">Recuperare</option>
							</select>
						</div>
					</div>	
					<div class="form-group">
						<label class="col-sm-3 control-label" for="edit_desc_tempspasse">Comentarii</label>
						<div class="col-sm-9">
							<textarea class="form-control" name="desc_tempspasse" id="edit_desc_tempspasse"></textarea>
						</div>	
					</div>

				</div>
			</div>


		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-success" id="bt_save_timespent">Înregistreză</button>
			<button type="button" class="btn btn-default" data-dismiss="modal">Închide</button>
		</div>
	</div>
  </div>
</div>  



<?php

llxFooter();

$db->close();

?>