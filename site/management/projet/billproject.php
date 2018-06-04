<?php
/* Copyright (C) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2013 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2010 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2010      François Legastelois <flegastelois@teclib.com>
 * Copyright (C) 2014-2016 Charlie BENKE		<charlie@patas-monkey.co>
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

dol_include_once ('/management/class/managementtask.class.php');
dol_include_once ('/management/class/managementproject.class.php');

require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

dol_include_once ('/management/core/lib/management.lib.php');

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
if (! empty($object->socid)) $object->fetch_thirdparty();

if(!$projectid)
	$projectid=$object->id;

// Security check
$socid=0;
if ($user->societe_id > 0) $socid=$user->societe_id;
$result = restrictedArea($user, 'projet', $projectid);

$form=new Form($db);
$formother = new FormOther($db);
$managementprojectstatic = new ManagementProject($db);

$taskstatic = new Task($db);
$tasksarray=$taskstatic->getTasksArray(0, 0, $projectid, $socid, 0);    // We want to see all task of project i am allowed to see, not only mine. Later only mine will be editable later.

$projectsrole=$taskstatic->getUserRolesForProjectsOrTasks($user, 0, $projectid, 0);
$tasksrole=$taskstatic->getUserRolesForProjectsOrTasks(0, $user, $projectid, 0);
$taskstatic = new ManagementTask($db);

/*
 * Actions
 */

if ($action == 'gobill')
{
	
	$transfertarray=Array();
	// on récupère ce que l'on souhaite facturer
	//boucle sur les lignes et récupération du nombre d'heures saisies
	$numlines=count($tasksarray);
	for ($i = 0 ; $i < $numlines ; $i++)
	{

		// si il y a à facturer
		$tobill = GETPOST('tobill-'.$i);
		if($tobill)
		{
			// on récupère ce qu'il y a facturer
			$tasksarray[$i]->tobill = $tobill;
			$transfertarray[] = $tasksarray[$i];

		}
	}
	
	// on alimente la base 
	$managementprojectstatic->fetch($projectid);
	$managementprojectstatic->addbilledline($transfertarray);
	
	//exit;
	// on redirige ensuite vers la facturation
	$objectelement="management_managementproject";
	header("Location: ".DOL_URL_ROOT.'/compta/facture.php?action=create&origin='.$objectelement.'&originid='.$projectid.'&socid='.$managementprojectstatic->socid);
}

/*
 * View
 */


$form = new Form($db);

$title=$langs->trans("TimeSpentStat");

llxHeader("",$title,"");


if ($object->societe->id > 0)  $result=$object->societe->fetch($object->societe->id);


$head = project_prepare_head($object);
dol_fiche_head($head, "billproject", $langs->trans("Project"), 0, ($object->public?'projectpub':'project'));

$linkback = '<a href="'.DOL_URL_ROOT.'/projet/list.php">'.$langs->trans("BackToList").'</a>';

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
	
	// Date start
	print '<tr><td>'.$langs->trans("DateStart").'</td><td>';
	print dol_print_date($object->date_start,'day');
	if ($object->date_start)
	{
		$yeardatestart= date("Y",$object->date_start);
		$monthdatestart= date("m",$object->date_start);
	}
	else
	{
		$yeardatestart= date("Y");
		$monthdatestart= '01';
	}
	print '</td></tr>';
	// Date end
	print '<tr><td>'.$langs->trans("DateEnd").'</td><td>';
	print dol_print_date($object->date_end,'day');
	if ($object->date_end)
	{
		$yeardateend= date("Y",$object->date_end);
		$monthdateend= date("m",$object->date_end);
	}
	else
	{
		$yeardateend= date("Y");
		$monthdateend= date("m");
	}
	print '</td></tr>';
	print '</table>';
}

	print "<br>";
//$projectsListId = $projectstatic->getProjectsAuthorizedForUser($user,$mine,1);
//var_dump($tasksarray);
//var_dump($projectsrole);
//var_dump($taskrole);

dol_htmloutput_mesg($mesg);


$transfertarray=Array();

print '<form name="selectperiod" method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$projectid.'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="gobill">';
print '<table class="border" width="100%">';
print '<tr class="liste_titre">';
print '<td width=200px colspan=2>'.$langs->trans("Project").' / '.$langs->trans("RefTask").'</td>';
print '<td align="right" width=100px>';
if ($yeardatestart ==$yeardateend)
	print $yeardatestart;
print '</td>';
$montharray = monthArray($langs);
for( $year=intval($yeardatestart);$year <= intval($yeardateend);$year++)
{
	$monthstart=intval($monthdatestart);
	$monthend=intval($monthdateend);
	
	if ($yeardatestart != $yeardateend && $year != intval($yeardatestart))
		$monthstart=1;
	if ($yeardatestart != $yeardateend && $year != intval($yeardateend))
		$monthend=12;
		
	for ($month=intval($monthstart);$month <= $monthend ;$month++)
	{
		print '<td align=right width=70px>';
		print $montharray[$month];
		if ($yeardatestart!=$yeardateend)
			print " ".$year;
		print '</td>';
	}
}
print '<td align="right" width=100px>'.$langs->trans("Total").'</td>';
print '<td align="right" width=100px>'.$langs->trans("Billeable").'</td>';
print "</tr>\n";
timesheetYear($j, 0, $tasksarray, $level, $projectsrole, $tasksrole, $mine,$perioduser);
print "</table>";	

// si il y a des choses à facturer
print '<div class="tabsAction">';
//$object->fetch($id,$ref);
// on a le droit de transférer que si la tache est terminée
$objectelement="management_managementproject";
if ($user->rights->facture->creer) 
{
	if ($object->socid)
		print '<input type=submit name="'.$langs->trans("BillTimeProject").'" value="'.$langs->trans("BillTimeProject").'">';
	else
		print '<a class="butActionRefused" href="#" title="' . $langs->trans("NoAssociatedCustomersOnProject") . '">' . $langs->trans("BillTimeProject") . '</a>';

}
print '</div>'; 

print '</form>';
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
function timesheetYear(&$inc, $parent, $lines, &$level, &$projectsrole, &$tasksrole, $mytask=0, $perioduser='')
{
	global  $bc, $langs;
	global $form,  $taskstatic;
	global $yeardatestart, $yeardateend, $monthdatestart, $monthdateend,  $displaymode ;

	global $transfertarray;

	$lastprojectid=0;
	$totalcol = array();
	$totalline = 0;
	$totaltobill=0;
	$var=false;

	$numlines=count($lines);
	for ($i = 0 ; $i < $numlines ; $i++)
	{
		$var=!$var;
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
			print '<td >';
			$taskstatic->fetch($lines[$i]->id);
			$taskstatic->fetchMT($lines[$i]->id);
			$taskstatic->label=$lines[$i]->label." (".dol_print_date($lines[$i]->date_start,'day')." - ".dol_print_date($lines[$i]->date_end,'day').')';
			//print $taskstatic->getNomUrl(1);
			print $taskstatic->getNomUrl(1,($showproject?'':'withproject'));
			print '</td>';
			print '<td >';
			print $lines[$i]->label;
			print '</td>';

			// Progress
			print '<td align="left">';
			print $langs->trans("Planned").' : '.convertSecondToTime($taskstatic->planned_workload, 'allhourmin');
			$totalduration+=$taskstatic->planned_workload;

			print '</td>';

			$totalline = 0;
			
			for( $year=intval($yeardatestart);$year <= intval($yeardateend);$year++)
			{
				$monthstart=intval($monthdatestart);
				$monthend=intval($monthdateend);
				
				if ($yeardatestart != $yeardateend && $year != intval($yeardatestart))
					$monthstart=1;
				if ($yeardatestart != $yeardateend && $year != intval($yeardateend))
					$monthend=12;
					
				for ($month=intval($monthstart);$month <= $monthend ;$month++)
				{
					$szvalue = fetchSumMonthTimeSpent($taskstatic->id, $month, $year, $perioduser, $displaymode);
					$totalline+=$szvalue;
					$totalprojet[$month][$year]+=$szvalue;
					print '<td align=right>'.($szvalue ? convertSecondToTime($szvalue, 'allhourmin'):"").'</td>';
					// le nom du champs c'est à la fois le jour et l'id de la tache

				}
			}
			print '<td align=right>'.($totalline ? convertSecondToTime($totalline, 'allhourmin'):"").'</td>';
			print '<td align=right rowspan=2 valign=bottom>';
			// on récupère le temps facturé sur la tache
			$yetbilled = fetchSumTimeBilled($taskstatic->id);
			$TimeToBill=0;
			switch($taskstatic->billingmode)
			{
				case 0:	// temps prévue
					if ($taskstatic->fk_statut == 3)
						$TimeToBill=$lines[$i]->planned_workload;
					break;
				case 1:	// temps passé terminé
					if ($taskstatic->fk_statut == 3)
						$TimeToBill=$lines[$i]->duration;
					break;
				case 2:	// temps passé en cours pas encore facturé
					$TimeToBill=$lines[$i]->duration - $yetbilled;
					break;
			}

			$totaltobill+=$TimeToBill;
			// uniquement si le mode de facturation est définie sur la tache
			if ($taskstatic->billingmode != "" && $taskstatic->billingmode != "-1" )
			{
				// pour les mode de facturation prévue et réalisé il faut que la tache soit terminé
				if (($taskstatic->billingmode== 0 || $taskstatic->billingmode == 1 && $taskstatic->fk_statut == 3 ) || $taskstatic->billingmode== 2)
				print '<input type=text size=3 name="tobill-'.$i.'" value="'.($TimeToBill ? $TimeToBill/3600:"").'">';
			}
			print '</td>';
			print '</tr>';
			print "<tr ".$bc[$var].">\n";
			// additionnal task info
			print '<td width=100px align=right>'.$lines[$i]->progress.' % </td><td width=100px align=right>'.$taskstatic->getLibStatut(5).'</td>';
			
			if ($taskstatic->billingmode != "" && $taskstatic->billingmode != "-1" )
			{
				print '<td align=right>';
				$ArrBillingMode=array($langs->trans("BillingTimePlanned"), $langs->trans("BillingTimeMade"), $langs->trans("BillingTimePassed"));
				print $langs->trans("BilledOn")." ".$ArrBillingMode[$taskstatic->billingmode];
				print '</td>';
			}
			else
			{
				print '<td align=center><b>';
				print $langs->trans("NoBilledModeSet");
				print '</b></td>';
			}
			$totallinebilled = 0;
			
			for( $year=intval($yeardatestart);$year <= intval($yeardateend);$year++)
			{
				$monthstart=intval($monthdatestart);
				$monthend=intval($monthdateend);
				
				if ($yeardatestart != $yeardateend && $year != intval($yeardatestart))
					$monthstart=1;
				if ($yeardatestart != $yeardateend && $year != intval($yeardateend))
					$monthend=12;
					
				for ($month=intval($monthstart);$month <= $monthend ;$month++)
				{
					$szvalue = fetchSumTimeBilled($taskstatic->id, $month, $year);
					$totallinebilled+=$szvalue;
					$totalprojetbilled[$month][$year]+=$szvalue;
						print '<td align=right>'.($szvalue ? convertSecondToTime($szvalue, 'allhourmin'):"").'</td>';
				}
			}
			print '<td align=right>'.($totallinebilled ? convertSecondToTime($totallinebilled, 'allhourmin'):"").'</td>';		
			print '</tr>';
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
		print '<td align=right colspan=2><b>'.$langs->trans("Total").'</b></td>';
		print '<td align=left><b>'.$langs->trans("Planned").' : '.convertSecondToTime($totalduration, 'allhourmin').'</b></td>';
		print '</td>';
		$totalline = 0;
		for( $year=intval($yeardatestart);$year <= intval($yeardateend);$year++)
		{
			$monthstart=intval($monthdatestart);
			$monthend=intval($monthdateend);
			
			if ($yeardatestart != $yeardateend && $year != intval($yeardatestart))
				$monthstart=1;
			if ($yeardatestart != $yeardateend && $year != intval($yeardateend))
				$monthend=12;
				
			for ($month=intval($monthstart);$month <= $monthend ;$month++)
			{
				print '<td align=right>'.($totalprojet[$month][$year] ? convertSecondToTime($totalprojet[$month][$year], 'allhourmin'):"").'</td>';
				$totalline+=$totalprojet[$month][$year];
			}
		}

		// on affiche le total du projet
		print '<td align=right>'.($totalline ? convertSecondToTime($totalline, 'allhourmin'):"").'</td>';
		print '<td align=right>'.($totaltobill ? convertSecondToTime($totaltobill, 'allhourmin'):"").'</td>';
		print "</tr>\n";

		print "<tr class='liste_total'>\n";
		print '<td align=right colspan=3><b>'.$langs->trans("YetBilled").'</b></td>';
		
		$totalline = 0;
		for( $year=intval($yeardatestart);$year <= intval($yeardateend);$year++)
		{
			$monthstart=intval($monthdatestart);
			$monthend=intval($monthdateend);
			
			if ($yeardatestart != $yeardateend && $year != intval($yeardatestart))
				$monthstart=1;
			if ($yeardatestart != $yeardateend && $year != intval($yeardateend))
				$monthend=12;
				
			for ($month=intval($monthstart);$month <= $monthend ;$month++)
			{
				// on affiche le total du projet
				print '<td align=right>'.($totalprojetbilled[$month][$year] ? convertSecondToTime($totalprojetbilled[$month][$year], 'allhourmin'):"").'</td>';
				$totalline+=$totalprojetbilled[$month][$year];
			}
		}
		// on affiche le total du projet
		print '<td align=right>'.($totalline ? convertSecondToTime($totalline, 'allhourmin'):"").'</td>';
		print '<td align=right></td>';
		print "</tr>\n";

	}
	return $inc;
}
?>