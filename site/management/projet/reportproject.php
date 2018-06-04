<?php
/* Copyright (C) 2005     	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2013	Laurent Destailleur 	<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2010	Regis Houssin       	<regis.houssin@capnetworks.com>
 * Copyright (C) 2010     	François Legastelois	<flegastelois@teclib.com>
 * Copyright (C) 2014-2016	Charlie BENKE			<charlie@patas-monkey.com>
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
dol_include_once ('/management/class/managementtask.class.php');

$langs->load('management@management');

$action=GETPOST('action');
$mode=GETPOST("mode");


$periodyear=GETPOST('periodyear','int');
if (!$periodyear)
	$periodyear=date('Y');

$periodmonth=GETPOST('periodmonth','int');
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
$projectid=GETPOST("id");
$projectref=GETPOST("ref");

$object = new Project($db);
$result = $object->fetch($projectid, $projectref);

if(!$projectid)
	$projectid=$object->id;

// Security check
$socid=0;
if ($user->societe_id > 0) $socid=$user->societe_id;
$result = restrictedArea($user, 'projet', $projectid);

$form=new Form($db);
$formother = new FormOther($db);
$projectstatic = new Project($db);
$taskstatic = new Task($db);


$tasksarray=$taskstatic->getTasksArray(0,0,$projectid,$socid,0);    // We want to see all task of project i am allowed to see, not only mine. Later only mine will be editable later.
$projectsrole=$taskstatic->getUserRolesForProjectsOrTasks($user,0,$projectid,0);
$tasksrole=$taskstatic->getUserRolesForProjectsOrTasks(0,$user,$projectid,0);


/*
 * Actions
 */


/*
 * View
 */


$form = new Form($db);

$title=$langs->trans("TimeSpentStat");

llxHeader("",$title,"");


if ($object->societe->id > 0)  $result=$object->societe->fetch($object->societe->id);


$head = project_prepare_head($object);
dol_fiche_head($head, 'management', $langs->trans("projet"), 0, 'project');
$linkback = '<a href="'.DOL_URL_ROOT.'/projet/liste.php">'.$langs->trans("BackToList").'</a>';

if (DOL_VERSION >= "5.0.0") {
	
	$morehtmlref='<div class="refidno">';
	$morehtmlref.=$object->title;
	
	if ($object->thirdparty->id > 0)
		$morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . $object->thirdparty->getNomUrl(1, 'project');
	$morehtmlref.='</div>';
	
	// Define a complementary filter for search of next/prev ref.
	if (! $user->rights->projet->all->lire)
	{
		$objectsListId = $object->getProjectsAuthorizedForUser($user,0,0);
		$object->next_prev_filter=" rowid in (".(count($objectsListId)?join(',',array_keys($objectsListId)):'0').")";
	}
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);
	
	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<div class="underbanner clearboth"></div>';
	

	print '<table class="border" width="100%">';
	
	// Visibility
	print '<tr><td class="titlefield">'.$langs->trans("Visibility").'</td><td>';
	if ($object->public) print $langs->trans('SharedProject');
	else print $langs->trans('PrivateProject');
	print '</td></tr>';
	
	if (! empty($conf->global->PROJECT_USE_OPPORTUNITIES))
	{
	    // Opportunity status
	    print '<tr><td>'.$langs->trans("OpportunityStatus").'</td><td>';
	    $code = dol_getIdFromCode($db, $object->opp_status, 'c_lead_status', 'rowid', 'code');
	    if ($code) print $langs->trans("OppStatus".$code);
	    print '</td></tr>';
	
	    // Opportunity percent
	    print '<tr><td>'.$langs->trans("OpportunityProbability").'</td><td>';
	    if (strcmp($object->opp_percent,'')) print price($object->opp_percent,'',$langs,1,0).' %';
	    print '</td></tr>';
	
	    // Opportunity Amount
	    print '<tr><td>'.$langs->trans("OpportunityAmount").'</td><td>';
	    if (strcmp($object->opp_amount,'')) print price($object->opp_amount,'',$langs,1,0,0,$conf->currency);
	    print '</td></tr>';
	}
	
	// Date start - end
	print '<tr><td>'.$langs->trans("DateStart").' - '.$langs->trans("DateEnd").'</td><td>';
	print dol_print_date($object->date_start,'day');
	$end=dol_print_date($object->date_end,'day');
	if ($end) print ' - '.$end;
	print '</td></tr>';
	
	// Budget
	print '<tr><td>'.$langs->trans("Budget").'</td><td>';
	if (strcmp($object->budget_amount, '')) print price($object->budget_amount,'',$langs,1,0,0,$conf->currency);
	print '</td></tr>';
	
	// Other attributes
	$cols = 2;
	include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_view.tpl.php';
	
	print '</table>';
	
	print '</div>';
	print '<div class="fichehalfright">';
	print '<div class="ficheaddleft">';
	print '<div class="underbanner clearboth"></div>';
	
	print '<table class="border" width="100%">';
	
	// Description
	print '<td class="titlefield tdtop">'.$langs->trans("Description").'</td><td>';
	print nl2br($object->description);
	print '</td></tr>';
	
	// Categories
	if($conf->categorie->enabled) {
	    print '<tr><td valign="middle">'.$langs->trans("Categories").'</td><td>';
	    print $form->showCategories($object->id,'project',1);
	    print "</td></tr>";
	}
	
	print '</table>';
	
	print '</div>';
	print '</div>';
	print '</div>';
	
	print '<div class="clearboth"></div>';

}
else
{
	print '<table class="border" width="100%">';
	$urlparam =($periodyear ? "&periodyear=".$periodyear:''). "&periodmonth=".$periodmonth.($perioduser ? "&perioduser=".$perioduser:'').($displaymode ? "&displaymode=".$displaymode:'');
	
	print '<tr><td width="30%">'.$langs->trans("Ref").'</td><td>';
	// Define a complementary filter for search of next/prev ref.
	if (! $user->rights->projet->all->lire)
	{
	    $projectsListId = $object->getProjectsAuthorizedForUser($user,$mine,0);
	    $object->next_prev_filter=" rowid in (".(count($projectsListId)?join(',',array_keys($projectsListId)):'0').")";
	}
	print $form->showrefnav($object, 'ref', $linkback, 1, 'ref', 'ref','', $urlparam);
	print '</td></tr>';
	
	print '<tr><td>'.$langs->trans("Label").'</td><td>'.$object->title.'</td></tr>';
	
	print '<tr><td>'.$langs->trans("ThirdParty").'</td><td>';
	if (! empty($object->societe->id)) print $object->societe->getNomUrl(1);
	else print '&nbsp;';
	print '</td></tr>';
	
	// Visibility
	print '<tr><td>'.$langs->trans("Visibility").'</td><td>';
	if ($object->public) print $langs->trans('SharedProject');
	else print $langs->trans('PrivateProject');
	print '</td></tr>';
	
	// Statut
	print '<tr><td>'.$langs->trans("Status").'</td><td>'.$object->getLibStatut(4).'</td></tr>';
	
	print '</table>';
}
print "<br>";


	$Taskstatic = new Task($db);	
	$tasksarray=$Taskstatic->getTasksArray(0, 0, $id, "", 0);    // We want to see all task of project i am allowed to see, not only mine. Later only mine will be editable later.

	$langs->load("management@management");
	print '<br><div class="fichecenter">';
	
	print_fiche_titre($langs->trans("TaskBudget").' : '.count($tasksarray),'','');
	print '<br>';
	print '<table class="border" width=100% >';
	print '<tr class="liste_titre">';
	print '<td class="liste_titre" width=100px align="left">'.$langs->trans("Tasks").'</td>';
	print '<td class="liste_titre" width=80px align="center">'.$langs->trans("PlannedTime").'</td>';
	print '<td class="liste_titre" width=100px align="center">'.$langs->trans("MntPlanned").'</td>';
	print '<td class="liste_titre" width=80px align="center">'.$langs->trans("ConsumedTime").'</td>';
	print '<td class="liste_titre" width=100px align="center">'.$langs->trans("MntConsumed").'</td>';
	print '<td class="liste_titre" width=80px align="center">'.$langs->trans("LeftTime").'</td>';
	print '<td class="liste_titre" width=100px align="center">'.$langs->trans("MntLeft").'</td>';
	print '<td class="liste_titre" width=80px align="center">'.$langs->trans("TotalTime").'</td>';
	print '<td class="liste_titre" width=100px align="center">'.$langs->trans("MntTotal").'</td>';
	print '</tr>';

	// on boucle sur les lignes de tache pour afficher les infos
	foreach($tasksarray as $taskinfo)
	{
		$ManagementTask = new ManagementTask($db);
//		$ManagementTask->fetch($id, $ref);
		$ManagementTask->id = $taskinfo->id;
		$ManagementTask->fetchMT($taskinfo->id);

		// si pas de thm estimé on prend celui estimé
		$thm = $ManagementTask->get_thm();

		if ($thm == 0)
		{
			$thm = $ManagementTask->average_thm;
		}
//		var_dump($taskinfo);
		print '<tr >';
		print '<td align="left">'.$taskinfo->getNomUrl(1).'</td>';
		print '<td align="center">';
		print convertSecondToTime($taskinfo->planned_workload,'allhourmin');
		$totplanned_workload+=$taskinfo->planned_workload;
		print '</td>';
		// le prévue correspond au thm estimé, pas au réel
		$MntPlanned= ($taskinfo->planned_workload/3600) * $ManagementTask->average_thm;
		print '<td align="right">'.price($MntPlanned).'</td>';
		$totMntPlanned += $MntPlanned;	
		print '<td align="center">';
		print convertSecondToTime($taskinfo->duration,'allhourmin');
		$totduration += $taskinfo->duration;
		print '</td>';
		$MntConsumed= ($taskinfo->duration/3600) * $thm;
		print '<td align="right">'.price($MntConsumed).'</td>';
		$totMntConsumed += $MntConsumed;
	
		print '<td align="center">';
		$calculatedprogress = 0;
		$EstimatedLeftDuration = $taskinfo->planned_workload ;
		if ($taskinfo->duration>0) {
			$EstimatedLeftDuration = $taskinfo->planned_workload - $taskinfo->duration;
			// et un petit calcul sympa sur le nombre d'heure restant
			$calculatedprogress = 100 * $taskinfo->duration / $taskinfo->planned_workload;
			if ($taskinfo->progress)
				$EstimatedLeftDuration = $taskinfo->planned_workload * ($calculatedprogress / $taskinfo->progress) - $taskinfo->duration;
		}
		print convertSecondToTime($EstimatedLeftDuration, 'allhourmin');
		$totEstimatedLeftDuration += $EstimatedLeftDuration;
		
		print '</td>';
		$MntLeft = round(($EstimatedLeftDuration/3600) * $thm,2);
		$totMntLeft += $MntLeft;
		print '<td align="right">'.price($MntLeft,2).'</td>';
	
		print '<td align="center">';
		$PlannedTotalDuration = $taskinfo->duration + $EstimatedLeftDuration;
		$totPlannedTotalDuration  += $PlannedTotalDuration ;
	
		print convertSecondToTime($PlannedTotalDuration , 'allhourmin');
		print '</td>';
		$MntTotal = round(($PlannedTotalDuration/3600) * $thm,2);
		print '<td align="right">'.price($MntTotal,2).'</td>';
		$totMntTotal += $MntTotal ;
		print '</tr>';
			
	}

	print '<tr class="liste_total">';
	print '<td align="left">'.$langs->trans("Total").'</td>';

	print '<td align="center">'.convertSecondToTime($totplanned_workload, 'allhourmin').'</td>';
	print '<td align="right">'.price($totMntPlanned).'</td>';
	print '<td align="center">'.convertSecondToTime($totduration, 'allhourmin').'</td>';
	print '<td align="right">'.price($totMntConsumed).'</td>';
	print '<td align="center">'.convertSecondToTime($totEstimatedLeftDuration, 'allhourmin').'</td>';
	print '<td align="right">'.price($totMntLeft).'</td>';
	print '<td align="center">'.convertSecondToTime($totPlannedTotalDuration, 'allhourmin').'</td>';

	print '<td align="right"><b>'.price($totMntTotal).'</b></td>';
	
	print '</tr>';

	print '</table>';
	print '</div>';

//$projectsListId = $projectstatic->getProjectsAuthorizedForUser($user,$mine,1);
//var_dump($tasksarray);
//var_dump($projectsrole);
//var_dump($taskrole);

dol_htmloutput_mesg($mesg);

// 
print "<br>";
print '<form name="selectperiod" method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$projectid.'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="selectperiod">';

print '<table  width="50%" >';
print '<tr >';
print '<td>'.$langs->trans("PeriodAAAAMMUser").'</td>';

print '<td>'.$formother->selectyear($periodyear,'periodyear',0,5,1).'&nbsp;'.$formother->select_month($periodmonth,'periodmonth',1).'</td>';

$showempty=0;
if($user->rights->management->readuser)
	$showempty=1;

// attention le dernier paramétre n'est dispo que sur la 3.7 et le patch fournis
//print '<td>'.$form->select_dolusers($perioduser,'perioduser',$showempty, '', 0,'', '', 0, 0, 0, " AND (u.rowid = ".$user->id." OR ug.fk_user=".$user->id.')').'</td>';
print '<td>'.$form->select_dolusers($perioduser,'perioduser',$showempty, '', 0,'', '', 0, 0, 0, " AND u.rowid = ".$user->id).'</td>';
print "</tr>\n";

print '<tr >';
print '<td>'.$langs->trans("DataToDisplay").'</td>';
$arraymode=array($langs->trans("TimePassed"), $langs->trans("TimeCost"), $langs->trans("TimeSell"), $langs->trans("Margin"));
print '<td>'.$form->selectarray('displaymode', $arraymode, $displaymode).'</td>';

print '<td ><input type=submit name="select"></td>';
print "</tr>\n";
print "</table>";
print '</form>';

$transfertarray=Array();

if ($periodmonth !="0")	// vue mensuel
{
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Project").' / '.$langs->trans("RefTask").'</td>';
	print '<td align="right">'.$langs->trans("Status").'</td>';

	for ($day=1;$day <= $nbdaymonth ;$day++)
	{
		$curday=mktime(0, 0, 0, $periodmonth, $day, $periodyear);
		print '<td align=right>';
		print substr($langs->trans(date('l', $curday)),0,1)." ".$day.'</td>';
	}
	print "<td align=right>".$langs->trans("Total")."</td>";
	print "</tr>\n";

	timesheetLines($j, 0, $tasksarray, $level, $projectsrole, $tasksrole, $mine,$perioduser);
	print "</table>";
}
else	// vue annuel
{
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Project").' / '.$langs->trans("RefTask").'</td>';
	print '<td align="right">'.$langs->trans("Status").'</td>';
	$montharray = monthArray($langs);
	for ($month=1;$month<= 12 ;$month++)
		print '<td align=right>'.$montharray[$month].'</td>';

	print '<td align="right">'.$langs->trans("Total").'</td>';
	print "</tr>\n";
	timesheetYear($j, 0, $tasksarray, $level, $projectsrole, $tasksrole, $mine,$perioduser);
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

	global $transfertarray;

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
			}

			print "<tr ".$bc[$var].">\n";
			// Ref
			print '<td>';
			$taskstatic->fetch($lines[$i]->id);
			$taskstatic->label=$lines[$i]->label." (".dol_print_date($lines[$i]->date_start,'day')." - ".dol_print_date($lines[$i]->date_end,'day').')'	;
			print $taskstatic->getNomUrl(1,($showproject?'':'withproject'));
			print '</td>';
			// Progress
			print '<td align="right">';
			print $lines[$i]->progress.'% ';
			// si transférable en facturation on conserve dans un tableau
			if ($taskstatic->fk_statut == 3)
				$transfertarray[] = $lines[$i]->id;
			print $taskstatic->getLibStatut(3);
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
				if ($displaymode==0)
					print '<td '.$bgcolor.' align=right>'.($szvalue ? convertSecondToTime($szvalue, 'allhourmin'):"").'</td>';
				else
					print '<td '.$bgcolor.' align=right>'.($szvalue ? price($szvalue):"").'</td>';
				// le nom du champs c'est à la fois le jour et l'id de la tache
			}
			// total line
			if ($displaymode==0)
				print '<td  align=right>'.($totalline ? convertSecondToTime($totalline, 'allhourmin'):"").'</td>';
			else
				print '<td  align=right>'.($totalline ? price($totalline):"").'</td>';


			// Break on a new project
			if ($parent == 0 && $lines[$i+1]->fk_project != $lastprojectid)
			{
				print "<tr class='liste_total'>\n";
				print '<td colspan=2 align=right><b>Total</b></td>';
				print '</td>';
				$totalline = 0;
				for ($day=1;$day <= $nbdaymonth;$day++)
				{
					$curday=mktime(0, 0, 0, $periodmonth, $day, $periodyear);
					$bgcolor="";
					if (date('N', $curday) == 6 || date('N', $curday) == 7)
						$bgcolor=" bgcolor=grey ";
					// on affiche le total du projet
					if ($displaymode==0)
						print '<td '.$bgcolor.' align=right><b>'.($totalprojet[$day] ? convertSecondToTime($totalprojet[$day], 'allhourmin'):"").'</b></td>';
					else
						print '<td '.$bgcolor.' align=right><b>'.($totalprojet[$day] ? price($totalprojet[$day]):"").'</b></td>';
					$totalline+=$totalprojet[$day];
				}
				
				// total line
				if ($displaymode==0)
					print '<td  align=right><b>'.($totalline ? convertSecondToTime($totalline, 'allhourmin'):"").'</b></td>';
				else
					print '<td  align=right><b>'.($totalline ? price($totalline):"").'</b></td>';
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

	global $transfertarray;

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
			}

			print "<tr ".$bc[$var].">\n";
			// Ref
			print '<td>';
			$taskstatic->fetch($lines[$i]->id);
			$taskstatic->label=$lines[$i]->label." (".dol_print_date($lines[$i]->date_start,'day')." - ".dol_print_date($lines[$i]->date_end,'day').')'	;
			//print $taskstatic->getNomUrl(1);
			print $taskstatic->getNomUrl(1,($showproject?'':'withproject'));
			print '</td>';

			// Progress
			print '<td align="right">';
			print $lines[$i]->progress.'% ';
			print $taskstatic->getLibStatut(3);
			
			if ($taskstatic->fk_statut == 3)
				$transfertarray[] = $lines[$i]->id;

			print '</td>';

			$totalline = 0;
			for ($month=1;$month<= 12 ;$month++)
			{
				$szvalue = fetchSumMonthTimeSpent($taskstatic->id, $month, $periodyear, $perioduser, $displaymode);
				$totalline+=$szvalue;
				$totalprojet[$month]+=$szvalue;
				if ($displaymode==0)
					print '<td align=right>'.($szvalue ? convertSecondToTime($szvalue, 'allhourmin'):"").'</td>';
				else
					print '<td align=right>'.($szvalue ? price($szvalue):"").'</td>';
				// le nom du champs c'est à la fois le jour et l'id de la tache
			}
			if ($displaymode==0)
				print '<td align=right>'.($totalline ? convertSecondToTime($totalline, 'allhourmin'):"").'</td>';
			else
				print '<td align=right>'.($totalline ? price($totalline):"").'</td>';


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
			if ($displaymode==0)
				print '<td align=right>'.($totalgen[$month] ? convertSecondToTime($totalgen[$month], 'allhourmin'):"").'</td>';
			else
				print '<td align=right>'.($totalgen[$month] ? price($totalgen[$month]):"").'</td>';

			$totalline+=$totalgen[$month];
		}
		// on affiche le total du projet
		if ($displaymode==0)
			print '<td align=right>'.($totalline ? convertSecondToTime($totalline, 'allhourmin'):"").'</td>';
		else
			print '<td align=right>'.($totalline ? price($totalline):"").'</td>';

		print "</tr>\n";
	}
	return $inc;
}
?>