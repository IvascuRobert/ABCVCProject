<?php
/* Copyright (C) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2013 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2010 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2010      François Legastelois <flegastelois@teclib.com>
 * Copyright (C) 2014-2016 Charlie BENKE		<charlie@patas-monkey.com>
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
 *	\file       /management/projet/reporttime.php
 *	\ingroup    projet
 *	\brief      show time used in project
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

$periodyear=GETPOST('periodyear','int');
if (!$periodyear)
	$periodyear=date('Y');

$periodmonth=GETPOST('periodmonth');
if ($periodmonth=="")
	$periodmonth=date('m');

$perioduser=GETPOST('perioduser','int');
if (!$perioduser)
	$perioduser=$user->id;

$displaymode=GETPOST('displaymode','int');
if (!$displaymode)
	$displaymode=0;

	
// récupération du nombre de jour dans le mois
$time = mktime(0, 0, 0, $periodmonth+1, 1, $periodyear); // premier jour du mois suivant
$time--; // Recule d'une seconde
$nbdaymonth=date('d', $time); // on récupère le dernier jour

$projectid='';
$projectid=isset($_GET["id"])?$_GET["id"]:$_POST["projectid"];

// Security check
$socid=0;
if ($user->societe_id > 0)
	$socid=$user->societe_id;
else
{
	$socid=GETPOST('socid');
	if ($socid == -1)
		$socid = "";
}
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


/*
 * View
 */


$title=$langs->trans("ProjetStatistics");


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
print '<td>'.$langs->trans("PeriodAAAAMMUser").'</td>';
print '<td>'.$formother->selectyear($periodyear,'periodyear',0,5,1).'&nbsp;'.$formother->select_month($periodmonth,'periodmonth',1).'</td>';
print '</tr >';

if ($user->societe_id == 0) 
{
	print '<tr>';
	print '<td >';
	print $langs->trans('Customer');
	print '</td>';
	print '<td>';
	$events = array();
	print $form->select_company($socid, 'socid', '', 1, 1, 0, $events);
	print '</td>';
	print '</tr>';
}
print '<tr >';
print '<td>'.$langs->trans("UserToDisplay").'</td>';
$showempty=0;
if($user->rights->management->readuser)
	$showempty=1;

// attention le dernier paramétre n'est dispo que sur la 3.7 et le patch fournis
if ($user->admin == 0) 
	$filteruser=" AND (u.rowid = ".$user->id." OR fk_user=".$user->id.")";
print '<td >'.$form->select_dolusers($perioduser,'perioduser',$showempty, '', 0,'', '', 0, 0, 0, $filteruser).'</td>';
print "</tr>\n";

print '<tr >';
print '<td>'.$langs->trans("DataToDisplay").'</td>';
$arraymode=array(	$langs->trans("TimePassed"), 
					$langs->trans("TimeCost"), 
					$langs->trans("TimeSell"), 
					$langs->trans("TimeBilled"), 
					$langs->trans("Margin"));
print '<td>'.$form->selectarray('displaymode', $arraymode, $displaymode).'</td>';

print '<td ><input type=submit name="select"></td>';
print "</tr>\n";
print "</table>";
print '</form>';

if ($periodmonth !="0")
{
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Project").' / '.$langs->trans("RefTask").'</td>';
	print '<td align="right">'.$langs->trans("Status").'</td>';
	print '<td colspan="32" align="right"></td>';
	print "</tr>\n";
	timesheetLines($j, 0, $tasksarray, $level, $projectsrole, $tasksrole, $mine, $perioduser);
	print "</table>";
}
else
{
	// vue annuel
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Project").' / '.$langs->trans("RefTask").'</td>';
	print '<td align="right">'.$langs->trans("Status").'</td>';
	$montharray = monthArray($langs);
	for ($month=1;$month<= 12 ;$month++)
		print '<td align=right>'.$montharray[$month].'</td>';

	print '<td align="right">'.$langs->trans("Total").'</td>';
	print "</tr>\n";
	timesheetYear($j, 0, $tasksarray, $level, $projectsrole, $tasksrole, $mine, $perioduser);
	print "</table>";	
}

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
	global $bc, $langs;
	global $form, $projectstatic, $taskstatic;
	global $periodyear, $periodmonth, $nbdaymonth, $displaymode ;

	$lastprojectid=0;

	$totalcol = array();
	$totalline = 0;

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
				$totalprojet = array();
				$var = !$var;
				$lastprojectid=$lines[$i]->fk_project;
				print "<tr bgcolor=orange>\n";
				
				print '<td colspan=2>';
				// Project
				$projectstatic->fetch($lines[$i]->fk_project);
				$projectstatic->ref=$lines[$i]->projectref;
				$projectstatic->public=$lines[$i]->public;
				$projectstatic->label=$langs->transnoentitiesnoconv("YourRole").': '.$projectsrole[$lines[$i]->fk_project];
				print $projectstatic->getNomUrl(1);
				print "&nbsp;".$projectstatic->title;
				//print " (".dol_print_date($projectstatic->date_start,'day')." - ".dol_print_date($projectstatic->date_end,'day').")";

				print '</td>';

				for ($day=1;$day <= $nbdaymonth ;$day++)
				{
					$curday=mktime(0, 0, 0, $periodmonth, $day, $periodyear);
					$bgcolor="";
					if (date('N', $curday) == 6 || date('N', $curday) == 7)
						$bgcolor=" bgcolor=grey ";
					print '<td '.$bgcolor.' align=right>';
					print substr($langs->trans(date('l', $curday)),0,1)." ".$day.'</td>';
				}
				print "<td align=right>".$langs->trans("Total")."</td>";
				print "</tr>\n";
			}

			print "<tr ".$bc[$var].">\n";
			// Ref
			print '<td>';
			$taskstatic->fetch($lines[$i]->id);
			$taskstatic->ref=$lines[$i]->ref;
			$taskstatic->label=$lines[$i]->label." (".dol_print_date($lines[$i]->date_start,'day')." - ".dol_print_date($lines[$i]->date_end,'day').')'	;

			print $taskstatic->getNomUrl(1,($showproject?'':'withproject'));
			print '</td>';

			// Progress
			print '<td align="right">';
			print $lines[$i]->progress.' %';
			print '</td>';
			$totalline = 0;
			for ($day=1;$day <= $nbdaymonth;$day++)
			{
				$curday=mktime(0, 0, 0, $periodmonth, $day, $periodyear);
				$bgcolor="";
				if (date('N', $curday) == 6 || date('N', $curday) == 7)
					$bgcolor=" bgcolor=grey ";
					
				$szvalue = fetchSumTimeSpent($taskstatic->id, $curday, $perioduser, $displaymode);
				
				$totalline+=$szvalue;
				$totalprojet[$day]+=$szvalue;
				if ($displaymode==0 || $displaymode==3)
					print '<td '.$bgcolor.' align=right>'.($szvalue ? convertSecondToTime($szvalue, 'allhourmin'):"").'</td>';
				else
					print '<td '.$bgcolor.' align=right>'.($szvalue ? price($szvalue):"").'</td>';
				// le nom du champs c'est à la fois le jour et l'id de la tache
			}
			// total line
			if ($displaymode==0 || $displaymode==3)
				print '<td align=right>'.($totalline ? convertSecondToTime($totalline, 'allhourmin'):"").'</td>';
			else
				print '<td align=right>'.($totalline ? price($totalline):"").'</td>';

			// Break on a new project
			if ($parent == 0 && $lines[$i+1]->fk_project != $lastprojectid)
			{
				print '<tr >';
				// Ref
				print '<td class="liste_total" colspan=2 align=right>Total '.$projectstatic->ref.'</td>';
				print '</td>';
				$totalline = 0;
				for ($day=1;$day <= $nbdaymonth;$day++)
				{
					$curday=mktime(0, 0, 0, $periodmonth, $day, $periodyear);
					$bgcolor="";
					if (date('N', $curday) == 6 || date('N', $curday) == 7)
						$bgcolor=" bgcolor=grey ";
					else
						$bgcolor=" class='liste_total' ";
					// on affiche le total du projet
					if ($displaymode==0 || $displaymode==3)
						print '<td '.$bgcolor.' align=right>'.($totalprojet[$day] ? convertSecondToTime($totalprojet[$day], 'allhourmin'):"").'</td>';
					else
						print '<td '.$bgcolor.' align=right>'.($totalprojet[$day] ? price($totalprojet[$day]):"").'</td>';
					$totalline+=$totalprojet[$day];
				}

				// total line
				if ($displaymode==0 || $displaymode==3)
					print '<td  align=right>'.($totalline ? convertSecondToTime($totalline, 'allhourmin'):"").'</td>';
				else
					print '<td class="liste_total"  align=right>'.($totalline ? price($totalline):"").'</td>';
				print "</tr>\n";
			}
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
function timesheetYear(&$inc, $parent, $lines, &$level, &$projectsrole, &$tasksrole, $mytask=0, $perioduser='')
{
	global  $bc, $langs;
	global $form, $projectstatic, $taskstatic;
	global $periodyear, $displaymode ;

	$lastprojectid=0;
	$totalcol = array();
	$totalline = 0;
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
				$totalprojet = array();
				$var = !$var;
				$lastprojectid=$lines[$i]->fk_project;
				print "<tr bgcolor=orange>\n";

				print '<td colspan=5>';
				// Project
				$projectstatic->fetch($lines[$i]->fk_project);
				$projectstatic->ref=$lines[$i]->projectref;
				$projectstatic->public=$lines[$i]->public;
				$projectstatic->label=$langs->transnoentitiesnoconv("YourRole").': '.$projectsrole[$lines[$i]->fk_project];
				print $projectstatic->getNomUrl(1);
				print "&nbsp;".$projectstatic->title;
				//print " (".dol_print_date($projectstatic->date_start,'day').($projectstatic->date_end?" - ".dol_print_date($projectstatic->date_end,'day'):'').")";
				print '</td>';
				print '<td colspan=13></td>';
				print "</tr>\n";
			}

			print "<tr ".$bc[$var].">\n";
			// Ref
			print '<td>';
			$taskstatic->id=$lines[$i]->id;
			$taskstatic->ref=$lines[$i]->ref;
			$taskstatic->label=$lines[$i]->label; //." (".dol_print_date($lines[$i]->date_start,'day')." - ".dol_print_date($lines[$i]->date_end,'day').')'	;
			//print $taskstatic->getNomUrl(1);
			print $taskstatic->getNomUrl(1,($showproject?'':'withproject'));
			print "&nbsp;".$lines[$i]->label;
			print '</td>';

			// Progress
			print '<td align="right">';
			print $lines[$i]->progress.' %';
			print '</td>';

			$totalline = 0;
			for ($month=1;$month<= 12 ;$month++)
			{
				$szvalue = fetchSumMonthTimeSpent($taskstatic->id, $month, $periodyear, $perioduser, $displaymode);
				$totalline+=$szvalue;
				$totalprojet[$month]+=$szvalue;
				if ($displaymode==0 || $displaymode==3)
					print '<td '.$bgcolor.' align=right>'.($szvalue ? convertSecondToTime($szvalue, 'allhourmin'):"").'</td>';
				else
					print '<td '.$bgcolor.' align=right>'.($szvalue ? price($szvalue):"").'</td>';
				// le nom du champs c'est à la fois le jour et l'id de la tache
			}
			if ($displaymode==0 || $displaymode==3)
				print '<td '.$bgcolor.' align=right>'.($totalline ? convertSecondToTime($totalline, 'allhourmin'):"").'</td>';
			else
				print '<td '.$bgcolor.' align=right>'.($totalline ? price($totalline):"").'</td>';


			// Break Total on a new project
			if ($parent == 0 && $lines[$i+1]->fk_project != $lastprojectid)
			{
				print '<tr class="liste_total" >';
				// Ref
				print '<td colspan=2 align=right><b>Total '.$projectstatic->ref.'</b></td>';
				print '</td>';
				$totalline = 0;
				for ($month=1;$month<= 12 ;$month++)
				{
					// on affiche le total du projet
					if ($displaymode==0 || $displaymode==3)
						print '<td align=right><b>'.($totalprojet[$month] ? convertSecondToTime($totalprojet[$month], 'allhourmin'):"").'</b></td>';
					else
						print '<td align=right><b>'.($totalprojet[$month] ? price($totalprojet[$month]):"").'</b></td>';
					$totalline+=$totalprojet[$month];
					$totalgen[$month]+=$totalprojet[$month];
				}
				
				if ($displaymode==0 || $displaymode==3)
					print '<td align=right><b>'.($totalline ? convertSecondToTime($totalline, 'allhourmin'):"").'</b></td>';
				else
					print '<td align=right><b>'.($totalline ? price($totalline):"").'</b></td>';

				print "</tr>\n";
			}

			$inc++;
			$level++;
			if ($lines[$i]->id) timesheetYear($inc, $lines[$i]->id, $lines, $level, $projectsrole, $tasksrole, $mytask, $perioduser);
			$level--;

		}
		else
		{
			//$level--;
		}

	}
	
	if ($level == 0)
	{
		print "<tr class='liste_total'>\n";
		print '<td colspan=2 align=right><b>Total</b></td>';
		print '</td>';
		$totalline = 0;
		for ($month=1;$month<= 12 ;$month++)
		{
			// on affiche le total du projet
			if ($displaymode==0 || $displaymode==3)
				print '<td  align=right>'.($totalgen[$month] ? convertSecondToTime($totalgen[$month], 'allhourmin'):"").'</td>';
			else
				print '<td  align=right>'.($totalgen[$month] ? price($totalgen[$month]):"").'</td>';

			$totalline+=$totalgen[$month];
		}
		// on affiche le total du projet
		if ($displaymode==0 || $displaymode==3)
			print '<td  align=right>'.($totalline ? convertSecondToTime($totalline, 'allhourmin'):"").'</td>';
		else
			print '<td align=right>'.($totalline ? price($totalline):"").'</td>';

		print "</tr>\n";
	}
	return $inc;
}
?>