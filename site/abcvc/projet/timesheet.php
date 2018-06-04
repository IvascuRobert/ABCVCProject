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

dol_include_once ('/management/core/lib/management.lib.php');

$langs->load('management@management');

$action=GETPOST('action');

$periodyear=GETPOST('periodyear','int');
if (!$periodyear)
	$periodyear=date('Y');

$periodweek=GETPOST('periodweek','int');
if (!$periodweek)
	$periodweek=date('W');

$perioduser=GETPOST('perioduser','int');
if (!$perioduser)
	$perioduser=$user->id;
	
// récupération du nombre de jour dans le mois
$time = mktime(0, 0, 0, $periodmonth+1, 1, $periodyear); // premier jour du mois suivant
$time--; // Recule d'une seconde
$nbdaymonth=date('d', $time); // on récupère le dernier jour


// Security check
$socid=0;
if ($user->societe_id > 0) $socid=$user->societe_id;
$result = restrictedArea($user, 'projet', 0);

$form=new Form($db);
$formother = new FormOther($db);
$projectstatic=new ProjectABCVC($db);
$project = new ProjectABCVC($db);
$taskstatic = new TaskABCVC($db);

$projectsListId = $projectstatic->getProjectsAuthorizedForUser($user,0,1);  // Return all project i have permission on. I want my tasks and some of my task may be on a public projet that is not my project
$tasksarray=$taskstatic->getTasksArray(0,0,$projectsListId,$socid,0);    // We want to see all task of project i am allowed to see, not only mine. Later only mine will be editable later.
$projectsrole=$taskstatic->getUserRolesForProjectsOrTasks($user,0,$projectsListId,0);
$tasksrole=$taskstatic->getUserRolesForProjectsOrTasks(0,$user,$projectsListId,0);


/*
 * Actions
 */

if ($action == 'addtime' && $user->rights->projet->creer)
{
	// on boucle sur les lignes de taches 
	setsheetLines($j, 0, $tasksarray, $level, $projectsrole, $tasksrole, $perioduser);

	setEventMessage($langs->trans("RecordSaved"));

	$action ="";
	header('Location: '.$_SERVER["PHP_SELF"].'?periodyear='.$periodyear.'&periodweek='.$periodweek.'&perioduser='.$perioduser);
	exit;
}

/*
 * View
 */


$title=$langs->trans("TimeSpentPeriodbyWeek");

llxHeader("",$title,"");

//$projectsListId = $projectstatic->getProjectsAuthorizedForUser($user,$mine,1);
//var_dump($tasksarray);
//var_dump($projectsrole);
//var_dump($taskrole);

//print_barre_liste($title, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $num);
dol_htmloutput_mesg($mesg);

// BOOTSTRAP 3 + css + js custom
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/abcvc_js_css.php';?>


	<form method="POST" id="searchFormList" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
    	<div class="panel panel-primary filterable">
           					<div class="panel-heading">			


				<form name="selectperiod" method="POST" action="<?php echo $_SERVER["PHP_SELF"];?>?id=<?php echo $project->id; ?>">
					<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">
					<input type="hidden" name="action" value="selectperiod">

					<?php print_barre_liste($title, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $num);?>

					<table  width="45%">
						<tr >
							<td><?php echo $langs->trans("PeriodAAAAMM"); ?></td>
							<td><?php echo $formother->selectyear($periodyear,'periodyear'); echo select_week($periodweek,'periodweek'); ?></td>
						</tr>
						<?php
						echo "\n";
						?>
						<tr >
							<td><?php echo $langs->trans("UserToDisplay"); ?></td>
							<?php
							$showempty=0;
							// attention le dernier paramétre n'est dispo que sur la 3.7 et le patch fournis
							$filteruser="";
							if ($user->admin == 0) 
								$filteruser=" AND (u.rowid = ".$user->id." OR fk_user=".$user->id.")";
							?>

							<td ><?php echo $form->select_dolusers($perioduser, 'perioduser', $showempty, '', 0,'', '', 0, 0, 0, $filteruser); ?></td>
							<td ><input type=submit name="select" value="<?php echo $langs->trans("Select"); ?>"></td>
							</tr>
							<?php
							echo "\n";
							?>
					</table>
				</form>


				<form name="addtime" method="POST" action="<?php echo $_SERVER["PHP_SELF"];?>?id=<?php echo $project->id;?>">
					<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">
					<input type="hidden" name="action" value="addtime">
					<input type="hidden" name="mode" value="<?php echo $mode; ?>">

					<input type="hidden" name="periodyear" value="<?php echo $periodyear; ?>'">
					<input type="hidden" name="periodweek" value="<?php echo $periodweek; ?>">
					<input type="hidden" name="perioduser" value="<?php echo $perioduser; ?>">
							</div>	
					<table class="noborder" width="100%">
						<tr class="liste_titre">
							<td><?php echo $langs->trans("Project"); ?> / <?php echo $langs->trans("RefTask"); ?></td>
							<td align="right"><?php echo $langs->trans("Status"); ?></td>
							<td colspan="31" align="right"><?php echo $langs->trans("TimeSpent"); ?>
							&nbsp;
							<input type=submit name=save></td>
						</tr>
						<?php
						echo "\n";

						timesheetLines($j, 0, $tasksarray, $level, $projectsrole, $tasksrole, $mine, $perioduser);
						?>
					</table>
		</div>
				</form>

	</form>
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
	global $periodyear, $periodweek;

	$lastprojectid=0;

	// détermination du mode de saisie
	$nbhoursonquarterday=$conf->global->MANAGEMENT_NBHOURS_ON_QUARTER_DAY;

	$var=true;

	$timeStampPremierJanvier = strtotime($periodyear . '-01-01');
	$jourPremierJanvier = date('w', $timeStampPremierJanvier);
	
	//-- recherche du N° de semaine du 1er janvier -------------------
	$numSemainePremierJanvier = date('W', $timeStampPremierJanvier);
	
	//-- nombre à ajouter en fonction du numéro précédent ------------
	$decallage = ($numSemainePremierJanvier == 1) ? $periodweek - 1 : $periodweek;
	//-- timestamp du jour dans la semaine recherchée ----------------
	$timeStampDate = strtotime('+' . $decallage . ' weeks', $timeStampPremierJanvier);
	//-- recherche du lundi de la semaine en fonction de la ligne précédente ---------
	$jour[0] = ($jourPremierJanvier == 1) ?  $timeStampDate : strtotime('last monday', $timeStampDate);
	for ($i=1;$i<=6;$i++)
		$jour[$i] = strtotime(date("Y-m-d", strtotime(date('d-m-Y', $jour[0]))) . " +".$i." day"); // ajouter 1 jour au lundi

	$numlines=count($lines);
	for ($i = 0 ; $i < $numlines ; $i++)
	{
    //		var_dump($lines);
		if ($parent == 0) $level = 0;

		if ($lines[$i]->fk_parent == $parent)
		{
			// Break on a new project
			if ($parent == 0 && $lines[$i]->fk_project != $lastprojectid)
			{
				$var = !$var;
				$lastprojectid=$lines[$i]->fk_project;
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
				$projectstatic->label=$langs->transnoentitiesnoconv("YourRole").': '.$projectsrole[$lines[$i]->fk_project];
				echo $projectstatic->getNomUrl(1);
				?>
				</td>
				<?php
				for ($day=0;$day <= 6;$day++)
				{
					$bgcolor="";
					$curday=$jour[$day];

					if (date('N', $curday) == 6 || date('N', $curday) == 7)
						$bgcolor=" bgcolor=grey ";

					?>
					<td <?php echo $bgcolor; ?> align=center>
					<?php
					//print $colday."=".$jour[$colday];
					echo $langs->trans(date('l', date($curday)))."<br>".date("Y-m-d",$curday);
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
			<?php
			echo "\n";

			// Ref
			?>			  
			<td><a href=# onclick="$('.detailligne<?php echo $i; ?>').toggle();" ><?php echo img_picto("","edit_add"); ?></a>
			&nbsp;
			<?php
			$taskstatic->id=$lines[$i]->id;
			$taskstatic->ref=$lines[$i]->ref;
			$taskstatic->label=$lines[$i]->label." (".dol_print_date($lines[$i]->date_start,'day')." - ".dol_print_date($lines[$i]->date_end,'day').')'	;
			//print $taskstatic->getNomUrl(1);
			echo $taskstatic->getNomUrl(1,($showproject?'':'withproject'));
			?>
			</td>

			<?php
			// Progress
			?>
			<td align="right">
			<?php
			echo $formother->select_percent((GETPOST('progress-'.$taskstatic->id)?GETPOST('progress-'.$taskstatic->id):$lines[$i]->progress),'progress-'.$taskstatic->id);
			?>
			</td>


			<?php
			for ($day=0;$day <= 6 ;$day++)
			{
				$bgcolor="";

				$curday=$jour[$day];

				if (date('N', $curday) == 6 || date('N', $curday) == 7) 
					$bgcolor="  bgcolor=grey ";
				?>

				<td <?php echo $bgcolor; ?> align=center>
				<?php
				$timespent=fetchSumTimeSpent($taskstatic->id, $curday, $perioduser);
				if ($nbhoursonquarterday > 0 
					&&  ($timespent == 0 ||
					 	(($timespent/3600) % $nbhoursonquarterday == 0)))
				{
					?>
					<select name="hrs-<?php echo $taskstatic->id; ?>-<?php echo ($day); ?>">
					<option value="0"></option>
					<?php
					for ($hrs=1;$hrs <= 4;$hrs++)
					{
						?>
						<option value="<?php echo $hrs * $nbhoursonquarterday; ?>"
						<?php
						if (($timespent/3600) == $hrs * $nbhoursonquarterday ) 
						{
							$bselected = true;
							echo ' selected ';
						}
						?>
						>
						<?php
						echo $langs->trans("Quarterday".$hrs);
						?>
						</option>
						<?php
					}
					?>
					</select>
					<?php
				}
				else
				{	
					// le nom du champs c'est à la fois le jour et l'id de la tache
					?>
					<input type=text id="inputday" size=1 style="font-size:0.7em" name="hrs-<?php echo $taskstatic->id; ?>-<?php echo $day; ?>"
					<?php
					

						echo ($timespent !=0)?" value='".round($timespent/3600,2)."'":' '; ?> >
					<?php
				}
				?>
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
			echo ' ';
			echo $langs->trans("Label"); ?> : <?php echo $lines[$i]->label; ?>
			<br>
			<?php
			//echo ' ';
			echo $langs->trans("TaskStart"); ?> : <?php echo dol_print_date($lines[$i]->date_start,'day'); ?> - <?php echo $langs->trans("TaskEnd"); ?> : <?php echo dol_print_date($lines[$i]->date_end,'day'); ?>
			<br>
			<?php
			echo $langs->trans("Planned"); ?> : <?php echo convertSecondToTime($lines[$i]->planned_workload,'allhourmin'); ?> - <?php echo $langs->trans("YetMade"); ?> : <?php echo convertSecondToTime($lines[$i]->duration_effective,'allhourmin'); ?> 
			</td>
			<?php

			// popup pour détail saisie
			for ($day = 0;$day <= 6 ;$day++)
			{
				$curday=$jour[$day];
				
				$bgcolor="";
				if (date('N', $curday) == 6 || date('N', $curday) == 7) 
					$bgcolor="  bgcolor=grey ";
				?>
				<td <?php echo $bgcolor; ?> valign=top align=center>
				<?php
				$textInDay=fetchTextTimeSpent($taskstatic->id, $curday, $perioduser);
				
				?>
				<textarea id="txt-<?php echo $taskstatic->id; ?>-<?php echo $day; ?>" name="txt-<?php echo $taskstatic->id; ?>-<?php echo $day; ?>">
				<?php
				if ($textInDay)
					echo $textInDay;
				?>
				</textarea>
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
	global $periodyear, $periodweek;

	$lastprojectid=0;

	$var=true;

	$numlines=count($lines);
	for ($i = 0 ; $i < $numlines ; $i++)
	{
		if ($parent == 0) $level = 0;

		if ($lines[$i]->fk_parent == $parent)
		{
			// Break on a new project
			if ($parent == 0 && $lines[$i]->fk_project != $lastprojectid)
			{
				$var = !$var;
				$lastprojectid=$lines[$i]->fk_project;
			}

			// on se positionne sur la tache associé à la ligne
			$taskstatic->fetch($lines[$i]->id);

			$timeStampPremierJanvier = strtotime($periodyear . '-01-01');
			$jourPremierJanvier = date('w', $timeStampPremierJanvier);
			
			//-- recherche du N° de semaine du 1er janvier -------------------
			$numSemainePremierJanvier = date('W', $timeStampPremierJanvier);
			
			//-- nombre à ajouter en fonction du numéro précédent ------------
			$decallage = ($numSemainePremierJanvier == 1) ? $periodweek - 1 : $periodweek;
			//-- timestamp du jour dans la semaine recherchée ----------------
			$timeStampDate = strtotime('+' . $decallage . ' weeks', $timeStampPremierJanvier);
			//-- recherche du lundi de la semaine en fonction de la ligne précédente ---------
			$jour[0] = ($jourPremierJanvier == 1) ?  $timeStampDate : strtotime('last monday', $timeStampDate);

			for ($day=0; $day <= 6; $day++)
			{
				$jour[$day] = strtotime(date("Y-m-d", strtotime(date('d-m-Y', $jour[0]))) . " +".$day." day"); // ajouter 1 jour si décalage

				$durationsuppr=0;
				$curday=$jour[$day];

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
						$durationsuppr= ($obj->totduration?$obj->totduration:0);
					}
					$db->free($resql);
				}

				// on supprime les lignes du mois
				$sql = "DELETE FROM ".MAIN_DB_PREFIX."abcvc_projet_task_time";
				$sql.= " WHERE fk_task = ".$taskstatic->id;
				$sql.= " AND task_date ='".$db->idate($curday)."'";
				$sql.= " AND fk_user = ".$perioduser;
				dol_syslog("timesheet.php::setsheetLines sql=".$sql);
				$resql = $db->query($sql);

				$sql = "UPDATE ".MAIN_DB_PREFIX."abcvc_projet_task";
				$sql.= " SET duration_effective = duration_effective - ".$durationsuppr;
				$sql.= " , progress = " . GETPOST ('progress-'.$taskstatic->id);
				$sql.= " WHERE rowid = ".$taskstatic->id;

				dol_syslog("timesheet.php::setsheetLines sql=".$sql);
				$resql = $db->query($sql);

				// si il y a des choses de saisie dans le champs
				if (GETPOST ('hrs-'.$taskstatic->id.'-'.$day))
				{
					//print "x=".GETPOST ('hrs-'.$taskstatic->id.'-'.$day).'<br>';
					// on alimente les infos pour la saisie
					$taskstatic->timespent_date=$curday;
					$taskstatic->timespent_datehour=$curday;
					$timespent=GETPOST ('hrs-'.$taskstatic->id.'-'.$day);
					if ($timespent=='') $timespent=0;
					$taskstatic->timespent_duration=$timespent*3600;
					$taskstatic->timespent_fk_user=$perioduser;
					$taskstatic->timespent_note=GETPOST ('txt-'.$taskstatic->id.'-'.$day);
					$taskstatic->addTimeSpent($user, $notrigger=0);
				}
				$db->commit();
			}

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
 *      Return HTML combo list of week
 *
 *      @param  string      $selected          Preselected value
 *      @param  string      $htmlname          Name of HTML select object
 *      @param  int         $useempty          Show empty in list
 *      @param  int         $longlabel         Show long label
 *      @return string
 */
function select_week($selected='',$htmlname='weekid',$useempty=0)
{
	global $langs;

	require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
	$select_week = '<select class="flat" name="'.$htmlname.'" id="'.$htmlname.'">';
	if ($useempty)
	{
		$select_week .= '<option value="0">&nbsp;</option>';
	}
	for( $week=1; $week < 54; $week++)
	{
		$selectedoption="";
		if ($selected == $week)
			$selectedoption=" selected ";
		$select_week .= '<option value="'.$week.'" '.$selectedoption.'>';
		$select_week .= $week;
		$select_week .= '</option>';
	}
	$select_week .= '</select>';
	return $select_week;
}
