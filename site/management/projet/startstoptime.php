<?php
/* Copyright (C) 2005      	Rodolphe Quiedeville 	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2013 	Laurent Destailleur  	<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2010 	Regis Houssin        	<regis.houssin@capnetworks.com>
 * Copyright (C) 2010      	François Legastelois 	<flegastelois@teclib.com>
 * Copyright (C) 2014-2016	charlie BENKE			<charlie@patas-monkey.com>
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
 *	\file       /management/projet/startstoptime.php
 *	\ingroup    projet
 *	\brief      startstoptime
 */

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");		// For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");	// For "custom" directory

require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

dol_include_once ('/management/core/lib/management.lib.php');

$langs->load('management@management');

$action=GETPOST('action');
$mode=GETPOST("mode");
$id=GETPOST('id','int');

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
if ($action == 'stoptime' && $user->rights->projet->creer)
{
	// on regarde si on est pas sur une pause ou un restart
	if (GETPOST("stoptime")) // arret simple
	{
		stoptime( GETPOST("chronoid"), GETPOST("note"), $perioduser);
		setEventMessage($langs->trans("TimeStopped"));
	}
	elseif (GETPOST("pausetime")) // pause
	{
		pausetime( GETPOST("chronoid"), GETPOST("note"), $perioduser);
		setEventMessage($langs->trans("TimePaused"));
	}
	elseif (GETPOST("restarttime")) // restart
	{
		restarttime( GETPOST("chronoid"), GETPOST("note"), $perioduser);
		setEventMessage($langs->trans("TimeRestarted"));
	}

	
	$action ="";
	// Redirect to avoid submit twice on back
	header('Location: '.$_SERVER["PHP_SELF"].'?perioduser='.$perioduser);
	exit;
}

if ($action == 'starttime' && $user->rights->projet->creer)
{
	if (GETPOST("starttime"))
	{
		// on démarre la tache	 (chronoid est vide mais au cas où...)
		starttime(GETPOST("taskid"), GETPOST("chronoid"), GETPOST("note"), $perioduser);
	
		setEventMessage($langs->trans("TimeStarted"));
		
		$action ="";
		
		// Redirect to avoid submit twice on back
		header('Location: '.$_SERVER["PHP_SELF"].'?perioduser='.$perioduser);
		exit;
	}	
	elseif (GETPOST("addone")) // ajouter un
	{
		addtime( GETPOST("taskid"),GETPOST("chronoid"), GETPOST("note"), $perioduser, $conf->global->MANAGEMENT_ADD_TIME_ONE);
		setEventMessage($langs->trans("TimeAdded"));
	}
	elseif (GETPOST("addtwo")) // ajouter deux
	{
		addtime(GETPOST("taskid"), GETPOST("chronoid"), GETPOST("note"), $perioduser, $conf->global->MANAGEMENT_ADD_TIME_TWO);
		setEventMessage($langs->trans("TimeAdded"));
	}
}


/*
 * View
 */


$title=$langs->trans("StartStopTime");


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

print '<table  width="33%">';
print '<tr >';
print '<td>'.$langs->trans("PeriodUser").'</td>';

// on oblige à choisir un user quoi qu'il arrive
$showempty=0;

// attention le dernier paramétre n'est dispo que sur la 3.7 et le patch fournis
print '<td>'.$form->select_dolusers($perioduser,'perioduser',$showempty, '', 0,'', '', 0, 0, 0, " AND (u.rowid = ".$user->id." OR fk_user=".$user->id.')').'</td>';

print '<td align=left><input type=submit name="changeUser" value="'.$langs->trans("ChangeUser").'"></td>';
print "</tr>\n";
print "</table>";
print '</form>';

// Gestion de l'arret automatique
stoppausedtime();


print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Project").' / '.$langs->trans("RefTask").'</td>';
print '<td width=120px align="right">'.$langs->trans("Status").'</td>';
print '<td width=120px align="right">'.$langs->trans("Planned").'</td>';
print '<td width=120px align="right">'.$langs->trans("YetMade").'</td>';
print '<td width=150px align="center">'.$langs->trans("YouMade").'</td>';
print '<td width=400px align="left" >'.$langs->trans("Note").'</td>';


print "</tr>\n";

startstoptime($j, 0, $tasksarray, $level, $projectsrole, $tasksrole, $mine,$perioduser);

print "</table>";

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
function startstoptime(&$inc, $parent, $lines, &$level, &$projectsrole, &$tasksrole, $mytask=0, $perioduser='')
{
	global $user, $bc, $langs, $db, $conf;
	global $form, $projectstatic, $taskstatic;

	$lastprojectid=0;

	$totalcol = array();
	$totalline = 0;

	$numlines=count($lines);
	for ($i = 0 ; $i < $numlines ; $i++)
	{
		$var=true;
		if ($parent == 0) $level = 0;

		if ($lines[$i]->fk_parent == $parent)
		{

			// Break on a new project
			if ($parent == 0 && $lines[$i]->fk_project != $lastprojectid)
			{
				$totalprojet = array();
				$lastprojectid=$lines[$i]->fk_project;
				print "<tr bgcolor=orange>\n";
				
				print '<td width=30%>';
				// Project
				$projectstatic->fetch($lines[$i]->fk_project);
				$projectstatic->ref=$lines[$i]->projectref;
				$projectstatic->public=$lines[$i]->public;
				//$projectstatic->label=$langs->transnoentitiesnoconv("YourRole").': '.$projectsrole[$lines[$i]->fk_project];
				print $projectstatic->getNomUrl(1);
				print " (".dol_print_date($projectstatic->date_start,'day').($projectstatic->date_end?" - ".dol_print_date($projectstatic->date_end,'day'):"").")";

				print '</td>';
				print '<td colspan=6>'.$projectstatic->title.'</td>';
				print "</tr>\n";
			}

			$chronoid=0;

			// déjà un chrono de lancé pour cet utilisateur sur cette tache?
			$sql = "SELECT rowid, date_start, date_pause, task_duration, now() as currentdate";
			$sql .= " FROM ".MAIN_DB_PREFIX."projet_task_time as t";
			$sql .= " WHERE t.fk_task =".$lines[$i]->id;
			$sql .= " And t.fk_user =".$perioduser;
			$sql .= " And t.date_start is not null";
			$sql .= " And t.date_end is null";

			$resql = $db->query($sql);
			if ($resql)
			{
				if ($db->num_rows($resql))
				{
					$obj = $db->fetch_object($resql);
					$chronoid		= $obj->rowid;
					$date_start		= $obj->date_start;
					$date_pause		= $obj->date_pause;
					$currentdate	= $obj->currentdate;
					$task_duration	= $obj->task_duration;
				}
				$db->free($resql);
			}

			print "<tr ".($chronoid ? ($date_pause?"bgcolor=#FFAF5A":"bgcolor=#A0E6B4"):"").">\n";
			// Ref
			print '<td valign=top>';

			$taskstatic->id=$lines[$i]->id;
			$taskstatic->ref=$lines[$i]->ref;
			//$taskstatic->label=$lines[$i]->label;
			$taskstatic->timespent_note="";
			//print $taskstatic->getNomUrl(1);
			print $taskstatic->getNomUrl(1,($showproject?'':'withproject'))." (".dol_print_date($lines[$i]->date_start,'day').($lines[$i]->date_end? " - ".dol_print_date($lines[$i]->date_end,'day'):'').')';
			print '<br>'.$lines[$i]->label;
			print '</td>';

			// Progress / Statut
			print '<td valign=top align="right">';
			print $lines[$i]->progress.' %';
			print '</td>';

			// planned time
			print '<td valign=top align="right">';
			print convertSecondToTime($lines[$i]->planned_workload, 'allhourmin');
			print '</td>';

			// already made by all
			print '<td valign=top align="right">';
			print convertSecondToTime(gettimemade_task($lines[$i]->id), 'allhourmin');
			print '</td>';

			// you made
			print '<td valign=top align="right">';
			print convertSecondToTime(gettimemade_task($lines[$i]->id, $perioduser), 'allhourmin');
			print '</td>';

			// saisie d'une heure à la volée
			// desc
			print '<td valign=top align="left" >';
			print '<form name="taskselect" method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$project->id.'">';
			print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
			print '<input type="hidden" name="action" value="'.($chronoid?'stoptime':'starttime').'">';
			print '<input type=hidden name=taskid value="'.$lines[$i]->id.'">';
			print '<input type=hidden name=chronoid value="'.$chronoid.'">';
			print '<input type=hidden name=perioduser value="'.$perioduser.'">';
			
			print '<table><tr><td valign=top>';
			if (!$chronoid)
			{
				$addTimeOne=$conf->global->MANAGEMENT_ADD_TIME_ONE;
				$addTimeTwo=$conf->global->MANAGEMENT_ADD_TIME_TWO;
				if ($addTimeOne > 0)
				{
					print '<input type=submit name=addone value='.$langs->trans("Add")."&nbsp;".convertSecondToTime($addTimeOne).'>';
					print '<br><br>';
				}
				if ($addTimeTwo > 0)
					print '<input type=submit name=addtwo value='.$langs->trans("Add")."&nbsp;".convertSecondToTime($addTimeTwo).'>';
			}	
			$taskstatic->fetchTimeSpent($chronoid);
			print '</td><td>';
			print '<textarea name=note cols=60 rows=3>'.$taskstatic->timespent_note.'</textarea>';
			print '</td>';
			
			// start / stop / pause
			print '<td align="left" valign=top>';
			if ($chronoid)
			{
				// si en pause
				if ($date_pause)
				{
					print '<input type=submit name=restarttime value='.$langs->trans("Restart").'><br>';
					print convertSecondToTime($task_duration, 'allhourmin').'<br>';
					print '<input type=submit name=stoptime value='.$langs->trans("Stop").'>';
				}
				else
				{
					print '<input type=submit name=pausetime value='.$langs->trans("Pause").'><br>';
					$nbsec= strtotime($currentdate) - strtotime($date_start);
					//print "==".$nbsec."=/=".$task_duration."==".($nbsec+$task_duration).'<br>';
					$nbsec= $nbsec+$task_duration ;
					print convertSecondToTime($nbsec, 'allhourmin').'<br>';
					print '<input type=submit name=stoptime value='.$langs->trans("Stop").'>';
				}
			}
			else
				print '<input type=submit name=starttime value='.$langs->trans("Start").'>';
			print '</td></tr></table>';
			print '</form >';
			print '</td></tr>';

			$inc++;
			$level++;
			if ($lines[$i]->id) startstoptime($inc, $lines[$i]->id, $lines, $level, $projectsrole, $tasksrole, $mytask, $perioduser);
			$level--;
		}
		else
		{
			//$level--;
		}

	}

	return $inc;
}

function gettimemade_task($fk_task, $fkuser=0)
{
	global $db;
	
	$totalhrs=0;
	$sql = "SELECT sum(t.task_duration) as totalhrs";
	$sql .= " FROM ".MAIN_DB_PREFIX."projet_task_time as t";
	$sql .= " WHERE t.fk_task =".$fk_task;
	if ($fkuser )
		$sql .= " And t.fk_user =".$fkuser;

	$resql = $db->query($sql);
	if ($resql)
	{
		if ($db->num_rows($resql))
		{
			$obj = $db->fetch_object($resql);
			$totalhrs	= $obj->totalhrs;
		}
		$db->free($resql);
	}
	return $totalhrs;
}

function getchronoid($fk_task, $fkuser)
{
	global $db;
	
	$totalhrs=0;
	$sql = "SELECT rowid, date_start";
	$sql .= " FROM ".MAIN_DB_PREFIX."projet_task_time as t";
	$sql .= " WHERE t.fk_task =".$fk_task;
	$sql .= " And t.fk_user =".$fkuser;
	$sql .= " And t.task_duration is null";
	$sql .= " And t.date_stop is null";

	$resql = $db->query($sql);
	if ($resql)
	{
		if ($db->num_rows($resql))
		{
			$obj = $db->fetch_object($resql);
			$totalhrs	= $obj->totalhrs;
		}
		$db->free($resql);
	}
	return $totalhrs;
}
?>