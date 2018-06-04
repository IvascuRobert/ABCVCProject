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

require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

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
if (!$perioduser)
	$perioduser=$user->id;
	
// récupération du nombre de jour dans le mois
$time = mktime(0, 0, 0, $periodmonth+1, 1, $periodyear); // premier jour du mois suivant
$time--; // Recule d'une seconde
$nbdaymonth=date('d', $time); // on récupère le dernier jour

$projectid='';
$projectid=isset($_GET["id"])?$_GET["id"]:$_POST["projectid"];

// Security check
$socid=0;
if ($user->societe_id > 0) $socid=$user->societe_id;
$result = restrictedArea($user, 'projet', $projectid);

$form=new Form($db);
$formother = new FormOther($db);
$projectstatic=new Project($db);
$project = new Project($db);
$taskstatic = new Task($db);

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
	// Redirect to avoid submit twice on back
	header('Location: '.$_SERVER["PHP_SELF"].'?id='.$projectid.($mode?'&mode='.$mode:''));
	exit;
}

/*
 * View
 */


$title=$langs->trans("TimeSpentPeriod");

llxHeader("",$title,"");

//$projectsListId = $projectstatic->getProjectsAuthorizedForUser($user,$mine,1);
//var_dump($tasksarray);
//var_dump($projectsrole);
//var_dump($taskrole);

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $num);
dol_htmloutput_mesg($mesg);

// 

print '<form name="selectperiod" method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$project->id.'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="selectperiod">';

print '<table  >';
print '<tr >';
print '<td width=170px>'.$langs->trans("PeriodAAAAMM").'</td>';
print '<td width=100px>'.$formother->selectyear($periodyear,'periodyear').$formother->select_month($periodmonth,'periodmonth').'</td>';
print '<td width=180px><div id="lblBlurWeekEnd">'.img_picto("ShowHideWeekendColumns","edit_add").$langs->trans("ShowHideWeekend").'</div></td>';
print "</tr>\n";
print '<tr >';
print '<td>'.$langs->trans("UserToDisplay").'</td>';
$showempty=0;
// attention le dernier paramétre n'est dispo que sur la 3.7 et le patch fournis
$filteruser="";
if ($user->admin == 0) 
	$filteruser=" AND (u.rowid = ".$user->id." OR fk_user=".$user->id.")";
print '<td >'.$form->select_dolusers($perioduser, 'perioduser', $showempty, '', 0,'', '', 0, 0, 0, $filteruser).'</td>';
print '<td ><input type=submit name="select" value="'.$langs->trans("Select").'"></td>';
print "</tr>\n";
print "</table>";
print '</form>';


print '<form name="addtime" method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$project->id.'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="addtime">';
print '<input type="hidden" name="mode" value="'.$mode.'">';

print '<input type="hidden" name="periodyear" value="'.$periodyear.'">';
print '<input type="hidden" name="periodmonth" value="'.$periodmonth.'">';
print '<input type="hidden" name="perioduser" value="'.$perioduser.'">';

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Project").' / '.$langs->trans("RefTask").'</td>';
print '<td align="right">'.$langs->trans("Status").'</td>';
print '<td colspan="31" align="right">'.$langs->trans("TimeSpent").'&nbsp;<input type=submit name=save></td>';
print "</tr>\n";

timesheetLines($j, 0, $tasksarray, $level, $projectsrole, $tasksrole, $mine, $perioduser);

print "</table>";
print '</form>';
// pour activer ou non le week-end
print '<script language="javascript">';
print '$(document).ready(function() {';
print '$(".weekendtohide").toggle();';
print '	$("#lblBlurWeekEnd").click(function() {';
print '		$(".weekendtohide").toggle();';
print '		$(".weekendtoshow").toggle();';
print '	});';
print '});';
print '</script>';

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

	// détermination du mode de saisie
	$nbhoursonquarterday=$conf->global->MANAGEMENT_NBHOURS_ON_QUARTER_DAY;


	$lastprojectid=0;

	$var=true;

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
				print "<tr ".$bc[$var].">\n";
				
				print '<td colspan=2>';
				// Project
				$projectstatic->id=$lines[$i]->fk_project;
				$projectstatic->ref=$lines[$i]->projectref;
				$projectstatic->public=$lines[$i]->public;
				$projectstatic->label=$langs->transnoentitiesnoconv("YourRole").': '.$projectsrole[$lines[$i]->fk_project];
				print $projectstatic->getNomUrl(1);

				print '</td>';
				
				for ($day=1;$day <= $nbdaymonth ;$day++)
				{
					$curday=mktime(0, 0, 0, $periodmonth, $day, $periodyear);
					$bgcolor="";
					if (date('N', $curday) == 6 || date('N', $curday) == 7)
					{
						print '<td width=1px class=weekendtoshow bgcolor=grey ></td>';
						$bgcolor=" class=weekendtohide bgcolor=grey ";
					}
					print '<td '.$bgcolor.' align=center>';
					print substr($langs->trans(date('l', $curday)),0,1)." ".$day.'</td>';
				}
				print "</tr>\n";
			}

			print "<tr ".$bc[$var].">\n";

			// Ref
			print "<td><a href=# onclick=\"$('.detailligne".$i."').toggle();\" >".img_picto("","edit_add")."</a>&nbsp;";
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
			print $taskstatic->getNomUrl(1,($showproject?'':'withproject'));
			print '</td>';

			// Progress
			print '<td align="right">';
			print $formother->select_percent((GETPOST('progress-'.$taskstatic->id)?GETPOST('progress-'.$taskstatic->id):$lines[$i]->progress),'progress-'.$taskstatic->id);
			print '</td>';

			for ($day=1;$day <= $nbdaymonth;$day++)
			{
				$curday=mktime(0, 0, 0, $periodmonth, $day, $periodyear);
				$bgcolor="";
				if (date('N', $curday) == 6 || date('N', $curday) == 7) 
				{
					print '<td width=1px class=weekendtoshow bgcolor=grey ></td>';
					$bgcolor=" class=weekendtohide bgcolor=grey ";
				}
				print '<td '.$bgcolor.' align=center>';
				$timespent=fetchSumTimeSpent($taskstatic->id, $curday, $perioduser);

				if ($nbhoursonquarterday > 0 
					&&  ($timespent == 0 ||
					 	(($timespent/3600) % $nbhoursonquarterday == 0)))
				{
					print '<select style="font-size:0.9em"  name="hrs-'.$taskstatic->id.'-'.$day.'">';
					print '<option value="0"></option>';

					for ($hrs=1;$hrs <= 4;$hrs++)
					{
						print '<option value="'.$hrs * $nbhoursonquarterday.'"';
						if (($timespent/3600) == $hrs * $nbhoursonquarterday ) {
							$bselected = true;
							print ' selected ';
						}
						print '>';
						print $langs->trans("Quarterday".$hrs);
						print '</option>';
					}
					print '</select>';
				}
				else
				{
					// le nom du champs c'est à la fois le jour et l'id de la tache
					print '<input type=text id="inputday" name="hrs-'.$taskstatic->id.'-'.$day.'"'; 
					if ($timespent !=0)
						print ' value="'.round($timespent/3600,2).'"';
					print ' size=1 style="font-size:0.7em">';
				}
				print '</td>';
			}

			print "</tr>\n";
			print "<tr style='display:none' class='detailligne".$i."'>";
			print '<td colspan=2>';
			if ($conf->global->MANAGEMENT_DISPLAY_TASKLABEL_INSTEAD_TASKREF == 1)
				print " ".$langs->trans("Ref")." : ".$lines[$i]->ref;
			else
				print " ".$langs->trans("Label")." : ".$lines[$i]->label;
			print "<br>".$langs->trans("TaskStart")." : ".dol_print_date($lines[$i]->date_start,'day')." - ".$langs->trans("TaskEnd")." : ".dol_print_date($lines[$i]->date_end,'day');
			print "<br>".$langs->trans("Planned")." : ".convertSecondToTime($lines[$i]->planned_workload,'allhourmin')." - ".$langs->trans("YetMade")." : ".convertSecondToTime($lines[$i]->duration_effective,'allhourmin') ;
			print '</td>';
			// popup pour détail saisie
			for ($day=1;$day <= $nbdaymonth;$day++)
			{
				$curday=mktime(0, 0, 0, $periodmonth, $day, $periodyear);
				$bgcolor="";
				if (date('N', $curday) == 6 || date('N', $curday) == 7) 
				{
					print '<td width=1px class=weekendtoshow bgcolor=grey ></td>';
					$bgcolor=" class=weekendtohide bgcolor=grey ";
				}
				print '<td '.$bgcolor.' valign=top align=center>';
				$textInDay=fetchTextTimeSpent($taskstatic->id, $curday, $perioduser);
				print "<a href=# onclick=\"$('#txt-".$taskstatic->id."-".$day."').toggle();\" >";
				if ($textInDay != '')
					print "<font color=red><b>X</b></font>";// ..img_info();
				else
					print "X";// ..img_info();
				print '</a><br>';
				print '<textarea style="display:none" id="txt-'.$taskstatic->id.'-'.$day.'" name="txt-'.$taskstatic->id.'-'.$day.'">'; 
				if ($textInDay)
					print $textInDay;
				print ' </textarea>';
				print '</td>';
			}
			print "</tr>\n";
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
				$sql = "SELECT sum(task_duration) as totduration FROM ".MAIN_DB_PREFIX."projet_task_time";
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
					$sql = "DELETE FROM ".MAIN_DB_PREFIX."projet_task_time";
					$sql.= " WHERE fk_task = ".$taskstatic->id;
					$sql.= " AND task_date ='".$db->idate($curday)."'";
					$sql.= " AND fk_user = ".$perioduser;
					dol_syslog("timesheet.php::setsheetLines sql=".$sql);
					$resql = $db->query($sql);
	
					$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task";
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

			$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task";
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
?>