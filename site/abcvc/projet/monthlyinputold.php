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

// //$projectsListId
// $projectsListId=GETPOST('projectid','int');
// var_dump('projectsListId:'.$projectsListId);

$projectid='';
$projectid=isset($_GET["id"])?$_GET["id"]:$_POST["projectid"];
//var_dump('projectid:'.$projectid);


//var_dump($_POST);
//exit();


// récupération du nombre de jour dans le mois
$time = mktime(0, 0, 0, $periodmonth+1, 1, $periodyear); // premier jour du mois suivant
$time--; // Recule d'une seconde
$nbdaymonth=date('d', $time); // on récupère le dernier jour

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

	$projectsrole=$taskstatic->getUserRolesForProjectsOrTasks($user,0,$projectid,0);
	$tasksrole=$taskstatic->getUserRolesForProjectsOrTasks(0,$user,$projectid,0);
}
//var_dump($projectsrole);
var_dump($tasksrole);




/*
 * Actions
 */

if ($action == 'addtime' && $user->rights->projet->creer)
{
	// on boucle sur les lignes de taches 
	setsheetLines($j, 0, $tasksarray, $level, $projectsrole, $tasksrole, $perioduser);
	
	setEventMessage($langs->trans("RecordSaved"));
	
	$action ="";
	// Redirect to avoid submit twice on back
	header('Location: '.$_SERVER["PHP_SELF"].'?id='.$projectid.($mode?'&mode='.$mode:''));
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


    <div class="panel panel-primary filterable">
        <div class="panel-heading">
			<form name="selectperiod" method="POST" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
				<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">
				<input type="hidden" name="action" value="selectperiod">
				<?php
				print_barre_liste($title, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $num);
				?>

				<div class="row">
					<div class="col-sm-2">
						Période
					</div>
					<div class="col-sm-10">
						<?php echo $formother->selectyear($periodyear,'periodyear'); echo $formother->select_month($periodmonth,'periodmonth'); ?>
						<div id="lblBlurWeekEnd" class="pull-right">
							<a href="#" onclick="return false;"><?php 
							//echo img_picto("ShowHideWeekendColumns","edit_add"); 
							echo $langs->trans("ShowHideWeekend"); 
							?></a>
						</div>
					</div>
				</div>

				<div class="row">
					<div class="col-sm-2">
						Collaborateur
					</div>
					<div class="col-sm-6">
						<?php 
							$showempty=0;
							// attention le dernier paramétre n'est dispo que sur la 3.7 et le patch fournis
							$filteruser="";
							//if ($user->admin == 0) $filteruser=" AND (u.rowid = ".$user->id." OR fk_user=".$user->id.")";
						?>
						
						<?php echo $form->select_dolusers($perioduser, 'perioduser', $showempty, '', 0,'', '', 0, 0, 0, $filteruser); ?>
					</div>
					<div class="col-sm-4">
						&nbsp;
					</div>
				</div>


				<div class="row">
					<div class="col-sm-2">
						Projet
					</div>
					<div class="col-sm-6">
						<?php echo $formprojets->select_projects_list(-1,$projectid,'projectid',24,0,1,1); ?>
					</div>
					<div class="col-sm-4">
						<div class="pull-right">
							<input type=submit name="select" class="btn btn-success pull-right" value="Afficher">
						</div>
					</div>
				</div>

				<div class="row">
					<div class="col-sm-12">
					<?php if( ($perioduser=='') || ($projectid=='') || ($projectid=='0') ):?>
						Séléctionner une période, un collaborateur et un projet afin de saisir les temps passés
					<?php else:?>
						&nbsp;
					<?php endif;?>
					</div>
				</div>

			</form>

		</div>
	</div>	


<hr />


<form name="addtime" method="POST" action="<?php echo $_SERVER["PHP_SELF"]; ?>?id=<?php echo $project->id; ?>">
	<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">
	<input type="hidden" name="action" value="addtime">
	<input type="hidden" name="mode" value="<?php echo $mode; ?>">

	<input type="hidden" name="periodyear" value="<?php echo $periodyear; ?>">
	<input type="hidden" name="periodmonth" value="<?php echo $periodmonth; ?>">
	<input type="hidden" name="perioduser" value="<?php echo $perioduser; ?>">

	<table class="noborder" width="100%">
		<tr class="liste_titre">
			<td><?php echo $langs->trans("Project");?> / <?php echo $langs->trans("RefTask"); ?></td>
			<td align="right"><?php echo $langs->trans("Status"); ?></td>
			<td colspan="31" align="right"><?php echo $langs->trans("TimeSpent"); ?>
			&nbsp;
			<input type=submit name=save></td>
		</tr>
		<?php
		echo "\n";

		if( ($projectid!='') && ($projectid!=0) ){
			timesheetLines($j, 0, $tasksarray, $level, $projectsrole, $tasksrole, $mine, $perioduser);

			//recup structure poste/timespent periode/user
			//$timeSpentProjet = $projectstatic->getTimespentProjectsTree($projectid, $perioduser, $date_sql_de, $date_sql_a);
			//
		}
		?>
	</table>
</form>
	

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
        		Temps passes
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
						<label class="col-sm-3 control-label" for="edit_tempspasse">Temps passe *</label>
						<div class="col-sm-9">

							<div class="input-group">
							  <span class="input-group-addon"><i class="fa fa-clock-o" aria-hidden="true"></i></span>
							  <input type="text" id="edit_tempspasse_de" class="required col-xs-2" required="" >
							  <input type="text" id="edit_tempspasse_a" class="required col-xs-2" required="" >

							  <spam id="preview_tempspasse"></spam>
							</div>						

						</div>	
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label">Type *</label>
						<div class="col-sm-9">
							<select name="type_tempspasse" class="form-control required" id="edit_type_tempspasse"  required="">
								<option value="0">Travail</option>
								<option value="9">Ecole</option>

								<option value="5">Congés Payés</option>
								<option value="1">Maladie</option>
								<option value="2">Absence</option>

								<option value="7">Feries</option>
								<option value="8">Recup</option>

							</select>
						</div>
					</div>		
					<?php /*
									<td rowspan="2">LEGEND</td>
									<td bgcolor="#ff4000">PANNIERS</td>
									<td bgcolor="#ff00ff">TRAJETS</td>
									<td bgcolor="#00ffff">PANNIERS + TRAJETS</td>
									<td bgcolor="#ffff00">GRAND DEPLACEMENT</td>
									<td bgcolor="#ffbf00">CONGES</td>

									<td bgcolor="#00ff80">FERIE</td>
									<td bgcolor="#8000ff">MALADIE</td>
									<td bgcolor="#ff0000">Recup</td>
									<td bgcolor="#00ffbf">ECOLE</td>

									<select id="type" class="flat type" name="type"><option class="optiongrey" value="-1">&nbsp;</option>
									<option value="1">Maladie</option>
									<option value="2">Absence</option>
									<option value="5">Congés Payés</option>
									</select>


					*/ ?>


					<div class="form-group">
						<label class="col-sm-3 control-label" for="edit_desc_tempspasse">Commentaires</label>
						<div class="col-sm-9">
							<textarea class="form-control" name="desc_tempspasse" id="edit_desc_tempspasse"></textarea>
						</div>	
					</div>

				</div>
			</div>


		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-primary" id="bt_timespent">Enregistrer</button>
			<button type="button" class="btn btn-danger" data-dismiss="modal">Fermer</button>
		</div>
	</div>
  </div>
</div>  



<?php

llxFooter();

$db->close();


/**
 * Output a task line
 *
 * @param	string	   	&$inc			?
 * @param   string		$parent			?
 * @param   Object		$lines			?
 * @param   int			&$level			?
 * @param   string		&$projectsrole	?
 * @param   string		&$tasksrole		?
 * @param   int			$mytask			0 or 1 to enable only if task is a task i am affected to
 * @return  $inc
 */
function timesheetLines(&$inc, $parent, $lines, &$level, &$projectsrole, &$tasksrole, $mytask=0, $perioduser='')
{
	global $user, $bc, $langs, $conf;
	global $form, $formother, $projectstatic, $taskstatic;
	global $periodyear, $periodmonth, $nbdaymonth ;
	global $date_sql_a, $date_sql_de ;

	// détermination du mode de saisie
	$nbhoursonquarterday=$conf->global->MANAGEMENT_NBHOURS_ON_QUARTER_DAY;


	$lastprojectid=0;

	$var=true;

	//var_dump($lines);
	//exit();

	$numlines=count($lines);
	for ($i = 0 ; $i < $numlines ; $i++)
	{

		//var_dump($parent);
		//var_dump($lines[$i]->fk_parent);
		if ($parent == 0) $level = 0;

		if ($lines[$i]->fk_parent == $parent)
			{
				// Break on a new project
				// ------------------------------------------------------------------------
				if ($parent == 0 && $lines[$i]->fk_project != $lastprojectid)
					{
						$var = !$var;
						$lastprojectid=$lines[$i]->fk_project;
						
						$curdayprojet=mktime(0, 0, 0, $periodmonth, 1, $periodyear);
						//var_dump( date('d/m/Y',$curdayprojet) );

						// Load time spent from table projet_task_time for the project into this->weekWorkLoad and this->weekWorkLoadPerTask for all days of a week
						// CUMUL tous intervenants
						$projectstatic->id=$lastprojectid;
						$projectstatic->loadTimeSpentAll($date_sql_de, $date_sql_a, 0, $user->id);	
						
						var_dump($projectstatic->weekWorkLoad);
						var_dump($projectstatic->weekWorkLoadPerTask);
						var_dump($projectstatic->weekNotePerTask);	


						/*<script type="text/javascript">
							var weekWorkLoadPerTask = <?php echo json_encode($projectstatic->weekWorkLoadPerTask); ?>
						</script>*/
						?>


						<tr <?php echo $bc[$var]; ?> >
							<?php
							echo "\n";
							?>
							<td colspan=2>
								<?php
								// Project
								$projectstatic->id=$lines[$i]->fk_project;
								$projectstatic->ref=$lines[$i]->projectref;
								$projectstatic->public=$lines[$i]->public;
								$projectstatic->label=$langs->transnoentitiesnoconv("YourRole"); echo $projectsrole[$lines[$i]->fk_project]; ?>
								<?php
								echo $projectstatic->getNomUrl(1);
								?>
							</td>
							<?php
								for ($day=1;$day <= $nbdaymonth ;$day++)
								{
									$curday=mktime(0, 0, 0, $periodmonth, $day, $periodyear);
									$bgcolor="";


									if (date('N', $curday) == 6 || date('N', $curday) == 7)
									{
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
						echo "\n";
					}
					?>

				<tr <?php echo $bc[$var]; ?> >
					<?php echo "\n";

					// Ref
					?>
					<td>
						<a href="#" onclick="$('.detailligne<?php echo $i; ?>').toggle();"><?php echo img_picto("",'edit_add'); ?></a>
						&nbsp;

						<?php 
						$taskstatic->id=$lines[$i]->id;
						
						if ($conf->global->MANAGEMENT_DISPLAY_TASKLABEL_INSTEAD_TASKREF == 1)
						{
							$taskstatic->label=$lines[$i]->ref." (".dol_print_date($lines[$i]->date_start,'day')." - ".dol_print_date($lines[$i]->date_end,'day').')'	;
							$taskstatic->ref=$lines[$i]->label;
						}
						else
						{
							$taskstatic->ref=$lines[$i]->ref;
							$taskstatic->label=$lines[$i]->label." (".dol_print_date($lines[$i]->date_start,'day')." - ".dol_print_date($lines[$i]->date_end,'day').')'	;
						}
						
						//print $taskstatic->getNomUrl(1);
						echo $taskstatic->getNomUrl(1,($showproject?'':'withproject'));
						?>
					</td>
					<?php
					// Progress
					?>
					<td align="right">
						<?php
						//echo $formother->select_percent((GETPOST('progress-'.$taskstatic->id)?GETPOST('progress-'.$taskstatic->id):$lines[$i]->progress),'progress-'.$taskstatic->id);
						echo $lines[$i]->progress." %";
						?>
					</td>
					<?php

					for ($day=1;$day <= $nbdaymonth;$day++)
					{
						$curday=mktime(0, 0, 0, $periodmonth, $day, $periodyear);
						$bgcolor="";


					 	$dayWorkLoad = $projectstatic->weekWorkLoadPerTask[$curday][$lines[$i]->id];
		  				$dayNote = $projectstatic->weekNotePerTask[$curday][$lines[$i]->id];

		  				//var_dump($dayWorkLoad);
		  				//var_dump($dayNote);

						if (date('N', $curday) == 6 || date('N', $curday) == 7) 
						{
							?>
							<td width=1px class="weekendtoshow" bgcolor="grey" ></td>
							<?php
							$bgcolor=" class='weekendtohide' bgcolor='grey' ";
						}
						//CELLULE JOUR/TASK
						//------------------------------------------------------------------------
						?>
						<td <?php echo $bgcolor; ?> align="center">
							<?php
							$timespent=Abcvc_fetchSumTimeSpent($taskstatic->id, $curday, $perioduser);
							//var_dump($timespent);
							/*
								array (size=8)
								  'date_debut' => string '2017-09-11 08:00:00' (length=19)
								  'debut' => string '08:00' (length=5)
								  'debut_sec' => int 28800
								  'date_fin' => string '2017-09-11 22:00:00' (length=19)
								  'fin' => string '22:00' (length=5)
								  'fin_sec' => int 79200
								  'duration_sec' => 50400
								  'type' => string '0' (length=1)

								array (size=8)
								  'date_debut' => null
								  'debut' => string ':' (length=1)
								  'debut_sec' => int 0
								  'date_fin' => string '1970-01-01 01:00:00' (length=19)
								  'fin' => string '01:00' (length=5)
								  'fin_sec' => int 3600
								  'duration_sec' => null
								  'type' => int 0
							*/
							
								// le nom du champs c'est à la fois le jour et l'id de la tache
								
								//$taskstatic->ref=$lines[$i]->ref;
								?>
								
								<?php if( in_array($taskstatic->id,array_keys($tasksrole)) ): ?>
								
								<div class="timespent_per_task_day" id="div-hrs-<?php echo $taskstatic->id.'-'.$day; ?>"
									data-task_id="<?php echo $taskstatic->id; ?>" 
									data-day="<?php echo $day; ?>"
									data-task_ref="<?php echo $lines[$i]->ref;?>" 
									data-task_label="<?php echo $lines[$i]->label;?>"

									data-date_start="<?php echo $curday;?>"
									data-debut="<?php echo $timespent['debut'];?>"
									data-debut_sec="<?php echo $timespent['debut_sec'];?>"

									data-fin="<?php echo $timespent['fin'];?>"
									data-fin_sec="<?php echo $timespent['fin_sec'];?>"

									data-duration_sec="<?php echo $timespent['duration_sec'];?>"

									data-type="<?php echo $timespent['type'];?>"
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
									?>
									<input type="hidden" name="hrsdebutfin-<?php echo $taskstatic->id.'-'.$day; ?>"	value="<?php echo $heures_debut_fin;?>" >



									<input type=text id="hrs-<?php echo $taskstatic->id.'-'.$day; ?>" name="hrs-<?php echo $taskstatic->id.'-'.$day; ?>"
				                     <?php
									 	if ($timespent['duration_sec'] !=0)
										echo ' value="'.round($timespent['duration_sec']/3600,2).'"';
									 ?>
									 size=1 style="font-size:0.7em">

									<?php if($dayNote && $lines[$i]->fk_parent == 0) :?>
										&nbsp<a title="<?php echo $dayNote; ?>"><i class="fa fa-commenting-o" aria-hidden="true"></i></a>
									<?php endif; ?>


									<br />
									
									<?php
									//var_dump( strtotime('2017-09-11 00:00:00') );
									//echo 'da';
									//if($dayWorkLoad) {
									//	echo convertSecondToTime($dayWorkLoad);
									//} else {
									//	echo '-';
									//}
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
				<?php
					echo "\n";
				?>
				<tr style='display:none' class='detailligne<?php echo $i; ?>'>
					<td colspan=2>
						<?php
						// infos tache
						if ($conf->global->MANAGEMENT_DISPLAY_TASKLABEL_INSTEAD_TASKREF == 1)
							{
							echo ' ';
							echo $langs->trans("Ref"); ?> : <?php echo $lines[$i]->ref; ?>
							<?php
							}
						else echo ' ';
						echo $langs->trans("Label") ?>: <?php echo $lines[$i]->label; ?>
						<br><?php echo $langs->trans("TaskStart"); ?> : <?php echo dol_print_date($lines[$i]->date_start,'day'); ?> - <?php echo $langs->trans("TaskEnd"); ?> : <?php echo dol_print_date($lines[$i]->date_end,'day'); ?>
						<br><?php echo $langs->trans("Planned"); ?> : <?php echo convertSecondToTime($lines[$i]->planned_workload,'allhourmin'); ?> - <?php echo $langs->trans("YetMade"); ?> : <?php echo convertSecondToTime($lines[$i]->duration_effective,'allhourmin') ; ?>
						<br />
						[progression bar]
					</td>
					<?php
					// infos sup de tache/jour textarea + ....
					for ($day=1;$day <= $nbdaymonth;$day++)
					{
						$curday=mktime(0, 0, 0, $periodmonth, $day, $periodyear);
						$bgcolor="";
						if (date('N', $curday) == 6 || date('N', $curday) == 7) 
						{
							?>
							<td width="1px" class="weekendtoshow" bgcolor="grey" ></td>
							<?php
							$bgcolor=" class='weekendtohide' bgcolor='grey' ";
						}
						?>
						<td <?php echo $bgcolor; ?> valign=top align=center>
						<?php 

						 	$dayWorkLoad = $projectstatic->weekWorkLoadPerTask[$curday][$lines[$i]->id];
			  				$dayNote = $projectstatic->weekNotePerTask[$curday][$lines[$i]->id];

							if($dayWorkLoad) :?>
								<div class="infos_task">
									<?php echo convertSecondToTime($dayWorkLoad); ?>
								</div>	
							<?php endif;


							//$textInDay=Abcvc_fetchTextTimeSpent($taskstatic->id, $curday, $perioduser);
							/*
							?>
								<a href=# onclick="$('#txt-<?php echo $taskstatic->id."-".$day;?>').toggle();" >
								<?php
								if ($textInDay != '')
									{
									?>
									<font color=red><b>X</b></font> <!-- //..img_info(); -->
									<?php
									}
								else
									echo 'X';// ..img_info();
									?>

								</a><br>
							*/ ?>	

							<textarea style="display:none" id="txt-<?php echo $taskstatic->id; ?>-<?php echo $day; ?>" name="txt-<?php echo $taskstatic->id; ?>-<?php echo $day; ?>"><?php	if ($dayNote)	echo $dayNote;?></textarea>
						</td>
						<?php
					}
					?>
				</tr>
				<?php
				echo "\n";
				$inc++;
				$level++;
				if ($lines[$i]->id) timesheetLines($inc, $lines[$i]->id, $lines, $level, $projectsrole, $tasksrole, $mytask, $perioduser);
				$level--;
			}
		else
		{
			//$level--;
		}
	}

	return $inc;
}


/**
 * Output a task line
 *
 * @param	string	   	&$inc			?
 * @param   string		$parent			?
 * @param   Object		$lines			?
 * @param   int			&$level			?
 * @param   string		&$projectsrole	?
 * @param   string		&$tasksrole		?
 * @param   int			$mytask			0 or 1 to enable only if task is a task i am affected to
 * @return  $inc
 */
function setsheetLines(&$inc, $parent, $lines, &$level, &$projectsrole, &$tasksrole, $perioduser)
{
	global $db, $bc, $langs, $user;
	global $form, $projectstatic, $taskstatic;
	global $periodyear, $periodmonth, $nbdaymonth ;

	$lastprojectid=0;

	$numlines=count($lines);
	for ($i = 0 ; $i < $numlines ; $i++)
	{
		if ($parent == 0) $level = 0;

		if ($lines[$i]->fk_parent == $parent)
		{
			// Break on a new project
			if ($parent == 0 && $lines[$i]->fk_project != $lastprojectid)
				$lastprojectid=$lines[$i]->fk_project;

			// on se positionne sur la tache associé à la ligne
			$taskstatic->fetch($lines[$i]->id);

			for ($day=1;$day <= $nbdaymonth;$day++)
			{
				$durationsuppr=0;
				$curday=mktime(0, 0, 0, $periodmonth, $day, $periodyear);
				// dans le doute on supprime les enregs si ils existent
				$db->begin();

				// on totalise les durées que l'on va supprimer
				$sql = "SELECT sum(task_duration) as totduration FROM ".MAIN_DB_PREFIX."abcvc_projet_task_time";
				$sql.= " WHERE fk_task = ".$taskstatic->id;
				$sql.= " AND task_date ='".$db->idate($curday)."'";
				$sql.= " AND fk_user = ".$perioduser;

				$resql=$db->query($sql);
				if ($resql)
				{
					if ($db->num_rows($resql))
					{
						$obj = $db->fetch_object($resql);
						$durationsuppr= ($obj->totduration ? $obj->totduration:0);
					}
					$db->free($resql);
				}
				if ($durationsuppr != 0)
				{
					// on supprime les lignes du mois
					$sql = "DELETE FROM ".MAIN_DB_PREFIX."abcvc_projet_task_time";
					$sql.= " WHERE fk_task = ".$taskstatic->id;
					$sql.= " AND task_date ='".$db->idate($curday)."'";
					$sql.= " AND fk_user = ".$perioduser;
					dol_syslog("timesheet.php::setsheetLines sql=".$sql);
					$resql = $db->query($sql);
	
					$sql = "UPDATE ".MAIN_DB_PREFIX."abcvc_projet_task";
					$sql.= " SET duration_effective = duration_effective - ".$durationsuppr;
					$sql.= " WHERE rowid = ".$taskstatic->id;
					dol_syslog("timesheet.php::setsheetLines sql=".$sql);
					$resql = $db->query($sql);
				}
				// si il y a des choses de saisie dans le champs
				if (GETPOST ('hrs-'.$taskstatic->id.'-'.$day))
				{
					//print "x=".GETPOST ('hrs-'.$taskstatic->id.'-'.$day).'<br>';
					// on alimente les infos pour la saisie
					
					$taskstatic->timespent_date=$curday;
					
					//avec heures ...
					$hrsdebutfin = GETPOST ('hrsdebutfin-'.$taskstatic->id.'-'.$day);
					$hrsdebutfin = explode('-',$hrsdebutfin);
					$hrsdebut = $hrsdebutfin[0];
					$hrsdebut = explode(':',$hrsdebut);
					$debut_hour = $hrsdebut[0];
					$debut_min = $hrsdebut[1];

					//$hrsfin = $hrsdebutfin[1];
					//$fin_hour = explode(':',$hrsfin);

					$curday_with_correct_hours=mktime($debut_hour, 0, 0, $periodmonth, $day, $periodyear);
					


					$taskstatic->timespent_datehour=$curday_with_correct_hours;

					$timespent=GETPOST ('hrs-'.$taskstatic->id.'-'.$day);
					if ($timespent=='') $timespent=0;
					$taskstatic->timespent_duration=$timespent*3600;
					
					$taskstatic->timespent_fk_user=$perioduser;
					$taskstatic->timespent_note=GETPOST ('txt-'.$taskstatic->id.'-'.$day);

//var_dump($taskstatic);
//exit();

					$taskstatic->addTimeSpent($user, $notrigger=0);
				}
				$db->commit();
			}

			$sql = "UPDATE ".MAIN_DB_PREFIX."abcvc_projet_task";
			$sql.= " SET progress = " . GETPOST ('progress-'.$taskstatic->id);
			$sql.= " WHERE rowid = ".$taskstatic->id;  
			dol_syslog("timesheet.php::setsheetLines sql=".$sql);
			$resql = $db->query($sql);

			$inc++;
			$level++;
			if ($lines[$i]->id) setsheetLines($inc, $lines[$i]->id, $lines, $level, $projectsrole, $tasksrole, $perioduser);
			$level--;
		}
		else
		{
			//$level--;
		}
	}

	return $inc;
}


/**
 *  return time passed on a task for a day and a user
 *
 *  @param	int		$fk_task	project task id
 *  @param	date	$curday 	current day
 *  @param	int		$fk_user 	user id
 *  @return int					duration in seconds
 */
function Abcvc_fetchSumTimeSpent($fk_task, $curday, $fk_user=0, $displaymode=0)
{
	global $db;

	if ($displaymode==0)
	{
		$sql = "SELECT sum(t.task_duration) as total, task_datehour, task_type, note";
		$sql.= " FROM ".MAIN_DB_PREFIX."abcvc_projet_task_time as t";
		$sql.= " WHERE 1=1";
	}
	elseif ($displaymode==1)
	{
		$sql = "SELECT sum((t.task_duration/3600)*t.thm) as total";
		$sql.= " FROM ".MAIN_DB_PREFIX."abcvc_projet_task_time as t";
		$sql.= " WHERE 1=1";
	}
	elseif ($displaymode==2)
	{
		$sql = "SELECT sum((t.task_duration/3600)*p.price) as total";
		$sql.= " FROM ".MAIN_DB_PREFIX."abcvc_projet_task_time as t, ".MAIN_DB_PREFIX."abcvc_projet_task as pt, ".MAIN_DB_PREFIX."product as p" ;
		$sql.= " WHERE pt.rowid = t.fk_task";
		$sql.= " AND pt.fk_product = p.rowid";
	}
	elseif ($displaymode==3)
	{
		$sql = "SELECT sum((t.task_duration/3600)*(p.price-t.thm)) as total";
		$sql.= " FROM ".MAIN_DB_PREFIX."abcvc_projet_task_time as t, ".MAIN_DB_PREFIX."abcvc_projet_task as pt, ".MAIN_DB_PREFIX."product as p" ;
		$sql.= " WHERE pt.rowid = t.fk_task";
		$sql.= " AND pt.fk_product = p.rowid";
	}

	$sql.= " AND t.fk_task= ".$fk_task;
	if ($curday)
		$sql.= " AND t.task_date ='".$db->idate($curday)."'";

	if ($fk_user > 0)
		$sql.= " AND t.fk_user= ".$fk_user;

	dol_syslog("Kmanagement.lib::fetchSumTimeSpent sql=".$sql, LOG_DEBUG);
	$resql=$db->query($sql);
	if ($resql)
	{
		if ($db->num_rows($resql))
		{
			$obj = $db->fetch_object($resql);
			$duration= $obj->total;
			$task_datehour= $obj->task_datehour;
			$task_type= $obj->task_type;
			if(is_null($task_type)) $task_type = 0;

			$H = explode(' ',$task_datehour);
			$H2 = explode(':',$H[1]);
			$debut = $H2[0].':'.$H2[1];
			$debut_sec = $H2[1]*60 + ($H2[0]*60*60);

			$task_datehourfin = date('Y-m-d H:i:s', strtotime($task_datehour) + $duration);
			$H = explode(' ',$task_datehourfin);
			$H2 = explode(':',$H[1]);
			$fin = $H2[0].':'.$H2[1];
			$fin_sec = $H2[1]*60 + ($H2[0]*60*60);

			$note= $obj->note;
		}
		$db->free($resql);

		return array(
			'date_debut'=>$task_datehour,
			'debut'=>$debut,
			'debut_sec'=>$debut_sec,

			'date_fin'=>$task_datehourfin,
			'fin'=>$fin,
			'fin_sec'=>$fin_sec,

			'duration_sec'=>$duration,
			'type'=>$task_type,
			'note'=>$task_note
			);
	}
	return array(
			'date_debut'=>0,
			'debut'=>0,
			'debut_sec'=>$debut_sec,

			'date_fin'=>0,
			'fin'=>0,
			'fin_sec'=>0,

			'duration_sec'=>0,
			'type'=>0,
			'note'=>0
			);
}


/**
 *  return time passed on a task for a day and a user
 *
 *  @param	int		$fk_task	project task id
 *  @param	date	$curday 	current day
 *  @param	int		$fk_user 	user id
 *  @return int					duration in seconds
 */
function Abcvc_fetchTextTimeSpent($fk_task, $curday, $fk_user)
{
	global $db;
	$note="";
	$sql = "SELECT t.note";
	$sql.= " FROM ".MAIN_DB_PREFIX."abcvc_projet_task_time as t";
	$sql.= " WHERE t.fk_task= ".$fk_task;
	$sql.= " AND t.task_date ='".$db->idate($curday)."'";
	$sql.= " AND t.fk_user= ".$fk_user;

	dol_syslog("management.lib::fetchTextTimeSpent sql=".$sql, LOG_DEBUG);
	$resql=$db->query($sql);
	if ($resql)
	{
		if ($db->num_rows($resql))
		{
			$num = $db->num_rows($resql);
			$i = 0;
			// Loop on each record found, so each couple (project id, task id)
			while ($i < $num)
			{
				$obj = $db->fetch_object($resql);
				if ($obj->note)
					$note.= $obj->note."\n";
				$i++;
			}
		}
		$db->free($resql);
		return $note;
	}
	return 0;
}






/*
task / line
-----------------------------------------------------------------
array (size=5)
  0 => 
    object(TaskABCVC)[138]
      public 'element' => string 'projectabcvc_task' (length=17)
      public 'table_element' => string 'abcvc_projet_task' (length=17)
      public 'fk_element' => string 'fk_task' (length=7)
      public 'picto' => string 'task' (length=4)
      protected 'childtables' => 
        array (size=1)
          0 => string 'abcvc_projet_task_time' (length=22)
      public 'fk_task_parent' => string '0' (length=1)
      public 'label' => string 'Poste 1' (length=7)
      public 'description' => string 'test' (length=4)
      public 'duration_effective' => null
      public 'planned_workload' => string '36600' (length=5)
      public 'date_c' => null
      public 'date_start' => int 1500927300
      public 'date_end' => int 1500927300
      public 'progress' => string '0' (length=1)
      public 'fk_statut' => string '0' (length=1)
      public 'priority' => null
      public 'fk_user_creat' => null
      public 'fk_user_valid' => null
      public 'rang' => string '0' (length=1)
      public 'timespent_min_date' => null
      public 'timespent_max_date' => null
      public 'timespent_total_duration' => null
      public 'timespent_total_amount' => null
      public 'timespent_nblinesnull' => null
      public 'timespent_nblines' => null
      public 'timespent_id' => null
      public 'timespent_duration' => null
      public 'timespent_old_duration' => null
      public 'timespent_date' => null
      public 'timespent_datehour' => null
      public 'timespent_withhour' => null
      public 'timespent_fk_user' => null
      public 'timespent_note' => null
      public 'oldcopy' => null
      
      public 'id' => string '17' (length=2)
      public 'error' => null
      public 'errors' => 
        array (size=0)
          empty
      public 'import_key' => null
      public 'array_options' => 
        array (size=0)
          empty
      public 'linkedObjectsIds' => null
      public 'linkedObjects' => null
      protected 'table_ref_field' => string '' (length=0)
      public 'context' => 
        array (size=0)
          empty
      public 'canvas' => null
      public 'project' => null
      public 'fk_project' => string '13' (length=2)
      public 'projet' => null
      public 'contact' => null
      public 'contact_id' => null
      public 'thirdparty' => null
      public 'user' => null
      public 'origin' => null
      public 'origin_id' => null
      public 'ref' => string '1.1.1' (length=5)
      public 'ref_previous' => null
      public 'ref_next' => null
      public 'ref_ext' => null
      public 'table_element_line' => null
      public 'statut' => null
      public 'country' => null
      public 'country_id' => null
      public 'country_code' => null
      public 'barcode_type' => null
      public 'barcode_type_code' => null
      public 'barcode_type_label' => null
      public 'barcode_type_coder' => null
      public 'mode_reglement_id' => null
      public 'cond_reglement_id' => null
      public 'cond_reglement' => null
      public 'fk_delivery_address' => null
      public 'shipping_method_id' => null
      public 'modelpdf' => null
      public 'fk_account' => null
      public 'note_public' => null
      public 'note_private' => null
      public 'note' => null
      public 'total_ht' => null
      public 'total_tva' => null
      public 'total_localtax1' => null
      public 'total_localtax2' => null
      public 'total_ttc' => null
      public 'lines' => null
      public 'fk_incoterms' => null
      public 'libelle_incoterms' => null
      public 'location_incoterms' => null
      public 'name' => null
      public 'lastname' => null
      public 'firstname' => null
      public 'civility_id' => null
      public 'projectref' => string 'PJ1707-0008' (length=11)
      public 'projectlabel' => string 'Ktestprojet' (length=11)
      public 'projectstatus' => string '1' (length=1)
      public 'fk_parent' => string '0' (length=1)
      public 'duration' => string '-72000' (length=6)
      public 'public' => string '1' (length=1)
      public 'thirdparty_id' => string '18' (length=2)
      public 'thirdparty_name' => string 'AD ARNAUD DEMOLITION' (length=20)



*/














?>