<?php
/* Copyright (C) 2014-2016		Charlie BENKE		<charlie@patas-monkey.com>
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
 *  \file	   htdocs/management/fichinter/calendar.php
 *  \ingroup	agenda
 *  \brief	  Home page of calendar events

 */

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");		// For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");	// For "custom" directory


require_once(DOL_DOCUMENT_ROOT."/societe/class/societe.class.php");
require_once(DOL_DOCUMENT_ROOT."/contact/class/contact.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/date.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/agenda.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/fichinter.lib.php");

dol_include_once("/management/class/managementfichinter.class.php");

if ($conf->projet->enabled)
{
	require_once(DOL_DOCUMENT_ROOT."/core/lib/project.lib.php");
	require_once(DOL_DOCUMENT_ROOT."/core/class/html.formprojet.class.php");
	require_once(DOL_DOCUMENT_ROOT."/projet/class/project.class.php");
}
if ($conf->contrat->enabled)
{
	require_once(DOL_DOCUMENT_ROOT."/core/class/html.formcontract.class.php");
}
$langs->load("companies");
$langs->load("interventions");
$langs->load("agenda");
$langs->load("management@management");

$filter=GETPOST("filter");
$filtera = GETPOST("userasked","int")?GETPOST("userasked","int"):GETPOST("filtera","int");
$filtert = GETPOST("usertodo","int")?GETPOST("usertodo","int"):GETPOST("filtert","int");
$filterd = GETPOST("userdone","int")?GETPOST("userdone","int"):GETPOST("filterd","int");

if (GETPOST('showfilter')==1)
{
	$showFichInter= GETPOST('showFichInter','alpha');
	$showDetailInter= GETPOST('showDetailInter','int');
}
else
{
	$showFichInter= 1;
}


$sortfield = GETPOST("sortfield");
$sortorder = GETPOST("sortorder");
$page = GETPOST("page","int");
if ($page == -1) { $page = 0 ; }
$limit = $conf->liste_limit;
$offset = $limit * $page ;
if (! $sortorder) $sortorder="ASC";
if (! $sortfield) $sortfield="a.datec";

// Security check
$socid = GETPOST("socid","int",1);
if ($user->societe_id) $socid=$user->societe_id;

$result = restrictedArea($user, 'ficheinter', 0,'fichinter');

$canedit=1;
if (! $user->rights->management->readuser || $filter =='mine')  // If no permission to see all, we show only affected to me
{
	$filtera=$user->id;
	$filtert=$user->id;
	$filterd=$user->id;
}

$action=GETPOST('action','alpha');
//$year=GETPOST("year");
$year=GETPOST("year","int")?GETPOST("year","int"):date("Y");
$month=GETPOST("month","int")?GETPOST("month","int"):date("m");
$week=GETPOST("week","int")?GETPOST("week","int"):date("W");
$day=GETPOST("day","int")?GETPOST("day","int"):0;
$pid=GETPOST("projectid","int")?GETPOST("projectid","int"):0;
$cid=GETPOST("contractid","int")?GETPOST("contractid","int"):0;
$status=GETPOST("status");
$maxprint=GETPOST("maxprint");
if (GETPOST('viewcal'))  { $action='show_month'; $day=''; }												   		// View by month
if (GETPOST('viewweek')) { $action='show_week'; $week=($week?$week:date("W")); $day=($day?$day:date("d")); }  // View by week
if (GETPOST('viewday'))  { $action='show_day'; $day=($day?$day:date("d")); }								  // View by day

$langs->load("other");
$langs->load("commercial");

if (! isset($conf->global->AGENDA_MAX_EVENTS_DAY_VIEW)) $conf->global->AGENDA_MAX_EVENTS_DAY_VIEW=3;

/*
 * Actions
 */
if (GETPOST("viewlist"))
{
	$param='';
	foreach($_POST as $key => $val)
	{
		if ($key=='token') continue;
		$param.='&'.$key.'='.urlencode($val);
	}
	//print $param;
	header("Location: ".dol_buildpath('/fichinter/',1)."list.php?".$param);
	exit;
}

/*
 * View
 */

$help_url='EN:Module_Intervention_En|FR:Module_Intervention|ES:Modulo_Intervention_Es';
llxHeader('',$langs->trans("Interventions"),$help_url);

$form=new Form($db);
$companystatic=new Societe($db);
$contactstatic=new Contact($db);

$now=dol_now('tzref');

if (empty($action) || $action=='show_month')
{
	$prev = dol_get_prev_month($month, $year);
	$prev_year  = $prev['year'];
	$prev_month = $prev['month'];
	$next = dol_get_next_month($month, $year);
	$next_year  = $next['year'];
	$next_month = $next['month'];

	$max_day_in_prev_month = date("t",dol_mktime(0,0,0,$prev_month,1,$prev_year));  // Nb of days in previous month
	$max_day_in_month = date("t",dol_mktime(0,0,0,$month,1,$year));				 // Nb of days in next month
	// tmpday is a negative or null cursor to know how many days before the 1 to show on month view (if tmpday=0 we start on monday)
	$tmpday = -date("w",dol_mktime(0,0,0,$month,1,$year))+2;
	$tmpday+=((isset($conf->global->MAIN_START_WEEK)?$conf->global->MAIN_START_WEEK:1)-1);
	if ($tmpday >= 1) $tmpday -= 7;
	// Define firstdaytoshow and lastdaytoshow
	$firstdaytoshow=dol_mktime(0,0,0,$prev_month,$max_day_in_prev_month+$tmpday,$prev_year);
	$next_day=7-($max_day_in_month+1-$tmpday)%7;
	if ($next_day < 6) $next_day+=7;
	$lastdaytoshow=dol_mktime(0,0,0,$next_month,$next_day,$next_year);
}
if ($action=='show_week')
{
	$prev = dol_get_first_day_week($day, $month, $year);
	$prev_year  = $prev['prev_year'];
	$prev_month = $prev['prev_month'];
	$prev_day   = $prev['prev_day'];
	$first_day  = $prev['first_day'];

	$week = $prev['week'];

	$day =(int)$day;
	$next = dol_get_next_week($day, $week, $month, $year);
	$next_year  = $next['year'];
	$next_month = $next['month'];
	$next_day   = $next['day'];

	// Define firstdaytoshow and lastdaytoshow
	$firstdaytoshow=dol_mktime(0,0,0,$prev_month,$first_day,$prev_year);
	$lastdaytoshow=dol_mktime(0,0,0,$next_month,$next_day,$next_year);

	$max_day_in_month = date("t",dol_mktime(0,0,0,$month,1,$year));

	$tmpday = $first_day;
}
if ($action=='show_day')
{
	$prev = dol_get_prev_day($day, $month, $year);
	$prev_year  = $prev['year'];
	$prev_month = $prev['month'];
	$prev_day   = $prev['day'];
	$next = dol_get_next_day($day, $month, $year);
	$next_year  = $next['year'];
	$next_month = $next['month'];
	$next_day   = $next['day'];

	// Define firstdaytoshow and lastdaytoshow
	$firstdaytoshow=dol_mktime(0,0,0,$prev_month,$prev_day,$prev_year);
	$lastdaytoshow=dol_mktime(0,0,0,$next_month,$next_day,$next_year);
}


$title=$langs->trans("DoneAndToDoInters");
if ($status == 'done') $title=$langs->trans("DoneInters");
if ($status == 'todo') $title=$langs->trans("ToDoInters");

$param='';
if ($status)  $param="&status=".$status;
if ($filter)  $param.="&filter=".$filter;
if ($filtera) $param.="&filtera=".$filtera;
if ($filtert) $param.="&filtert=".$filtert;
if ($filterd) $param.="&filterd=".$filterd;
if ($socid)   $param.="&socid=".$socid;
if ($showbirthday) $param.="&showbirthday=1";
if ($pid)	 $param.="&projectid=".$pid;
if ($cid)	 $param.="&contractid=".$cid;
if (GETPOST("type"))   $param.="&type=".GETPOST("type");
if ($action == 'show_day' || $action == 'show_week') $param.='&action='.$action;
if ($maxprint) $param.="&maxprint=on";


// Show navigation bar
if (empty($action) || $action=='show_month')
{
	$nav ="<a href=\"?year=".$prev_year."&amp;month=".$prev_month."&amp;region=".$region.$param.'&amp;showFichInter='.$showFichInter.'&amp;showDetailInter='.$showDetailInter."\">".img_previous($langs->trans("Previous"))."</a>\n";
	$nav.=" <span id=\"month_name\">".dol_print_date(dol_mktime(0,0,0,$month,1,$year),"%b %Y");
	$nav.=" </span>\n";
	$nav.="<a href=\"?year=".$next_year."&amp;month=".$next_month."&amp;region=".$region.$param.'&amp;showFichInter='.$showFichInter.'&amp;showDetailInter='.$showDetailInter."\">".img_next($langs->trans("Next"))."</a>\n";
	$picto='calendar';
}
if ($action=='show_week')
{
	$nav ="<a href=\"?year=".$prev_year."&amp;month=".$prev_month."&amp;day=".$prev_day."&amp;region=".$region.$param.'&amp;showFichInter='.$showFichInter.'&amp;showDetailInter='.$showDetailInter."\">".img_previous($langs->trans("Previous"))."</a>\n";
	$nav.=" <span id=\"month_name\">".dol_print_date(dol_mktime(0,0,0,$month,1,$year),"%Y").", ".$langs->trans("Week")." ".$week;
	$nav.=" </span>\n";
	$nav.="<a href=\"?year=".$next_year."&amp;month=".$next_month."&amp;day=".$next_day."&amp;region=".$region.$param.'&amp;showFichInter='.$showFichInter.'&amp;showDetailInter='.$showDetailInter."\">".img_next($langs->trans("Next"))."</a>\n";
	$picto='calendarweek';
}
if ($action=='show_day')
{
	$nav ="<a href=\"?year=".$prev_year."&amp;month=".$prev_month."&amp;day=".$prev_day."&amp;region=".$region.$param.'&amp;showFichInter='.$showFichInter.'&amp;showDetailInter='.$showDetailInter."\">".img_previous($langs->trans("Previous"))."</a>\n";
	$nav.=" <span id=\"month_name\">".dol_print_date(dol_mktime(0,0,0,$month,$day,$year),"daytextshort");
	$nav.=" </span>\n";
	$nav.="<a href=\"?year=".$next_year."&amp;month=".$next_month."&amp;day=".$next_day."&amp;region=".$region.$param.'&amp;showFichInter='.$showFichInter.'&amp;showDetailInter='.$showDetailInter."\">".img_next($langs->trans("Next"))."</a>\n";
	$picto='calendarday';
}

// Must be after the nav definition
$param.='&year='.$year.'&month='.$month.($day?'&day='.$day:'');
//print 'x'.$param;

print_fiche_titre($langs->trans('Interventions'), $mesg);

//dol_fiche_head($head, 'card', $langs->trans('FichInters'), 0, $picto);
print_fichinter_filter($form,$canedit,$status,$year,$month,$day,$showDetailInter,$showFichInter,$filtera,$filtert,$filterd,$pid,$socid,$cid);
dol_fiche_end();

print_fiche_titre($title,$link.' &nbsp; &nbsp; '.$nav, '');
//print '<br>';

//print_fiche_titre($link,'','');


// Get event in an array
$FichInterArray=array();

if ($showFichInter)
{
	$sql = 'SELECT fi.rowid, fi.ref, fi.description,';
	$sql.= ' fi.dateo,';
	$sql.= ' fi.datee,';
	$sql.= ' fi.fulldayevent,';
	$sql.= ' fi.fk_user_author,';
	$sql.= ' fi.fk_user_valid,';
	$sql.= ' fi.fk_projet,';
	$sql.= ' fi.fk_soc,';
	$sql.= ' fi.fk_statut ';
	$sql.= ' FROM '.MAIN_DB_PREFIX.'fichinter as fi';
	$sql.= ', '.MAIN_DB_PREFIX.'user as u';
	$sql.= ' WHERE fi.fk_user_author = u.rowid';
	$sql.= ' AND u.entity in (0,'.$conf->entity.')';	// To limit to entity
	//$sql.= ' AND u.entity = '.$conf->entity;
	//if ($user->societe_id) $sql.= ' AND a.fk_soc = '.$user->societe_id; // To limit to external user company
	if ($pid) $sql.=" AND fi.fk_projet=".$db->escape($pid);
	if ($cid) $sql.=" AND fi.fk_contrat=".$db->escape($cid);
	if ($action == 'show_day')
	{
		$sql.= " AND (";
		$sql.= " (fi.dateo BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,$day,$year))."'";
		$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,$day,$year))."')";
		$sql.= " OR ";
		$sql.= " (fi.datee BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,$day,$year))."'";
		$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,$day,$year))."')";
		$sql.= " OR ";
		$sql.= " (fi.dateo < '".$db->idate(dol_mktime(0,0,0,$month,$day,$year))."'";
		$sql.= " AND fi.datee > '".$db->idate(dol_mktime(23,59,59,$month,$day,$year))."')";
		$sql.= ')';
	}
	else
	{
		// To limit array
		$sql.= " AND (";
		$sql.= " (fi.dateo BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,1,$year)-(60*60*24*7))."'";   // Start 7 days before
		$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,28,$year)+(60*60*24*10))."')";			// End 7 days after + 3 to go from 28 to 31
		$sql.= " OR ";
		$sql.= " (fi.datee BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,1,$year)-(60*60*24*7))."'";
		$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,28,$year)+(60*60*24*10))."')";
		$sql.= " OR ";
		$sql.= " (fi.dateo < '".$db->idate(dol_mktime(0,0,0,$month,1,$year)-(60*60*24*7))."'";
		$sql.= " AND fi.datee > '".$db->idate(dol_mktime(23,59,59,$month,28,$year)+(60*60*24*10))."')";
		$sql.= ')';
	}
	if ($filtera > 0 || $filtert > 0 || $filterd > 0)
	{
		$sql.= " AND (";
		if ($filtera > 0) $sql.= " fi.fk_user_author = ".$filtera;
		//if ($filtert > 0) $sql.= ($filtera>0?" OR ":"")." fi.fk_user_action = ".$filtert;
		if ($filterd > 0) $sql.= ($filtera>0||$filtert>0?" OR ":"")." fi.fk_user_valid = ".$filterd;
		$sql.= ")";
	}
	//if ($status == 'done') { $sql.= " AND (pt.progress = 100 OR (pt.progress = -1 AND pt.dateo <= '".$db->idate($now)."'))"; }
	//if ($status == 'todo') { $sql.= " AND ((pt.progress >= 0 AND pt.progress < 100) OR (pt.progress = -1 AND pt.datee > '".$db->idate($now)."'))"; }
	// Sort on date
	$sql.= ' ORDER BY dateo';
	//print $sql;
	
	dol_syslog("management/fichinter/calendar.php sql=".$sql, LOG_DEBUG);
	$resql=$db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);
		$i=0;
		while ($i < $num)
		{
			$obj = $db->fetch_object($resql);
	
			// Create a new object action
			$fichinter=new Fichinter($db);
			$fichinter->id=$obj->rowid;	
			$fichinter->ref=$obj->ref;	
			
			$fichinter->dateo=$db->jdate($obj->dateo);  
			$fichinter->datee=$db->jdate($obj->datee);
			$fichinter->type_code="FICHINTER";
			$fichinter->libelle=$obj->description;
			
			$fichinter->author = New User($db);
			$fichinter->usertodo = New User($db);
			$fichinter->userdone = New User($db);
			$fichinter->author->id=$obj->fk_user_author;
			$fichinter->usertodo->id=$obj->fk_user_action;
			$fichinter->userdone->id=$obj->fk_user_done;
			
			$fichinter->societe= New Societe($db);
			$fichinter->contact= New Contact($db);
			$fichinter->societe->id=$obj->fk_soc;
			$fichinter->contact->id=$obj->fk_contact;

	//		$fichinter->priority=$obj->priority;
			$fichinter->fulldayevent=$obj->fulldayevent;
	//		$fichinter->location=$obj->location;
			$fichinter->fk_statut=$obj->fk_statut;
			$fichinter->fk_projet=$obj->fk_projet;
	
			// Defined date_start_in_calendar and date_end_in_calendar property
			// They are date start and end of action but modified to not be outside calendar view.
			if ($fichinter->percentage <= 0)
			{
				$fichinter->date_start_in_calendar=$fichinter->dateo;
				if ($fichinter->datee != '' && $fichinter->datee >= $fichinter->dateo) 
				{	$fichinter->date_end_in_calendar=$fichinter->datee; }
				else 
				{	$fichinter->date_end_in_calendar=$fichinter->dateo; }
			}
			else
			{
				$fichinter->date_start_in_calendar=$fichinter->dateo;
				if ($fichinter->datee != '' && $fichinter->datee >= $fichinter->dateo) $fichinter->date_end_in_calendar=$fichinter->datee;
				else $fichinter->date_end_in_calendar=$fichinter->dateo;
			}
			// Define ponctual property
			if ($fichinter->date_start_in_calendar == $fichinter->date_end_in_calendar)
			{
				$fichinter->ponctuel=1;
			}
	
			// Check values
			if ($fichinter->date_end_in_calendar < $firstdaytoshow ||
			$fichinter->date_start_in_calendar > $lastdaytoshow)
			{
				// This record is out of visible range
			}
			else
			{
				if ($fichinter->date_start_in_calendar < $firstdaytoshow) $fichinter->date_start_in_calendar=$firstdaytoshow;
				if ($fichinter->date_end_in_calendar > $lastdaytoshow) $fichinter->date_end_in_calendar=$lastdaytoshow;
	
				// Add an entry in actionarray for each day
				$daycursor=$fichinter->date_start_in_calendar;
				$annee = date('Y',$daycursor);
				$mois = date('m',$daycursor);
				$jour = date('d',$daycursor);
	
				// Loop on each day covered by action to prepare an index to show on calendar
				$loop=true; $j=0;
				$daykey=dol_mktime(0,0,0,$mois,$jour,$annee);
				do
				{
					//if ($fichinter->id==408) print 'daykey='.$daykey.' '.$fichinter->datep.' '.$fichinter->datef.'<br>';
					$FichInterArray[$daykey][]=$fichinter;
					$j++;
	
					$daykey+=60*60*24;
					if ($daykey > $fichinter->date_end_in_calendar) $loop=false;
				}
				while ($loop);
				//var_dump($FichInterArray);
				//print 'Event '.$i.' id='.$fichinter->id.' (start='.dol_print_date($fichinter->dateo).'-end='.dol_print_date($fichinter->datee);
				//print ' startincalendar='.dol_print_date($fichinter->date_start_in_calendar).'-endincalendar='.dol_print_date($fichinter->date_end_in_calendar).') was added in '.$j.' different index key of array<br>';
			}
			$i++;
		}
	}
	else
	{
		dol_print_error($db);
	}
}

// affichage de l'agenda detail des interventions
if ($showDetailInter)
{
	$sql = 'SELECT fi.rowid, fi.ref, fid.description,';
	$sql.= ' fid.date,';
	$sql.= ' fi.fk_projet,';
	$sql.= ' fi.fk_soc,';
	$sql.= ' fid.duree';
	$sql.= ' FROM '.MAIN_DB_PREFIX.'fichinter as fi';
	$sql.= ', '.MAIN_DB_PREFIX.'fichinterdet as fid';
	$sql.= ', '.MAIN_DB_PREFIX.'user as u';
	$sql.= ' WHERE fi.fk_user_author = u.rowid';
	$sql.= ' AND fid.fk_fichinter = fi.rowid';
	$sql.= ' AND u.entity in (0,'.$conf->entity.')';	// To limit to entity
	//$sql.= ' AND u.entity = '.$conf->entity;
	//if ($user->societe_id) $sql.= ' AND a.fk_soc = '.$user->societe_id; // To limit to external user company
	if ($pid) $sql.=" AND fi.fk_projet=".$db->escape($pid);
	if ($cid) $sql.=" AND fi.fk_contrat=".$db->escape($cid);
	if ($action == 'show_day')
	{
		$sql.= " AND ";
		$sql.= " (fid.date BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,$day,$year))."'";
		$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,$day,$year))."')";
		
	}
	else
	{
		// To limit array
		$sql.= " AND ";
		$sql.= " (fid.date BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,1,$year)-(60*60*24*7))."'";   // Start 7 days before
		$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,28,$year)+(60*60*24*10))."')";			// End 7 days after + 3 to go from 28 to 31
	}
	if ($filtera > 0 || $filtert > 0 || $filterd > 0)
	{
		$sql.= " AND (";
		if ($filtera > 0) $sql.= " fi.fk_user_author = ".$filtera;
		if ($filterd > 0) $sql.= ($filtera>0||$filtert>0?" OR ":"")." fi.fk_user_valid = ".$filterd;
		$sql.= ")";
	}
	$sql.= ' ORDER BY date';

	dol_syslog("management/fichinter/calendar.php sql=".$sql, LOG_DEBUG);
	$resql=$db->query($sql);
	if ($resql)
	{
		
		$num = $db->num_rows($resql);
		$i=0;
		while ($i < $num)
		{

			$obj = $db->fetch_object($resql);
			// Create a new object action
			$fichinter=new Fichinter($db);
			$fichinter->id=$obj->rowid;	
			$fichinter->ref=$obj->ref;	
			$fichinter->dateo=$db->jdate($obj->date);
			
			$datee = new DateTime($obj->date);
			$datee->add(new DateInterval('PT'.($obj->duree).'S'));
			$fichinter->datee=$db->jdate($datee->format('Y-m-d H:i:s'));
			$fichinter->type_code="INTERDETAIL";
			$fichinter->libelle=$obj->description;
			$fichinter->author = new User($db);
			$fichinter->author->id=$obj->fk_user_author;
			$fichinter->fulldayevent=0;
			$fichinter->fk_statut=$obj->fk_statut;
			$fichinter->societe = new Societe($db);
			$fichinter->societe->id=$obj->fk_soc;
			$fichinter->fk_projet=$obj->fk_projet;
	
			// Defined date_start_in_calendar and date_end_in_calendar property
			// They are date start and end of action but modified to not be outside calendar view.
			if ($fichinter->percentage <= 0)
			{
				$fichinter->date_start_in_calendar=$fichinter->dateo;
				if ($fichinter->datee != '' && $fichinter->datee >= $fichinter->dateo) 
				{	$fichinter->date_end_in_calendar=$fichinter->datee; }
				else 
				{	$fichinter->date_end_in_calendar=$fichinter->dateo; }
			}
			else
			{
				$fichinter->date_start_in_calendar=$fichinter->dateo;
				if ($fichinter->datee != '' && $fichinter->datee >= $fichinter->dateo) $fichinter->date_end_in_calendar=$fichinter->datee;
				else $fichinter->date_end_in_calendar=$fichinter->dateo;
			}
			// Define ponctual property
			if ($fichinter->date_start_in_calendar == $fichinter->date_end_in_calendar)
			{
				$fichinter->ponctuel=1;
			}
			
	
			// Check values
			if ($fichinter->date_end_in_calendar < $firstdaytoshow ||
			$fichinter->date_start_in_calendar > $lastdaytoshow)
			{
				// This record is out of visible range
			}
			else
			{
				if ($fichinter->date_start_in_calendar < $firstdaytoshow) $fichinter->date_start_in_calendar=$firstdaytoshow;
				if ($fichinter->date_end_in_calendar > $lastdaytoshow) $fichinter->date_end_in_calendar=$lastdaytoshow;
	
				// Add an entry in actionarray for each day
				$daycursor=$fichinter->date_start_in_calendar;
				$annee = date('Y',$daycursor);
				$mois = date('m',$daycursor);
				$jour = date('d',$daycursor);
	
				// Loop on each day covered by action to prepare an index to show on calendar
				$loop=true; $j=0;
				$daykey=dol_mktime(0,0,0,$mois,$jour,$annee);
				do
				{
					$FichInterArray[$daykey][]=$fichinter;
					$j++;
	
					$daykey+=60*60*24;
					if ($daykey > $fichinter->date_end_in_calendar) $loop=false;
				}
				while ($loop);
			}
			$i++;
		}
	}
	else
	{
		dol_print_error($db);
	}
}

//var_dump($FichInterArray);

$maxnbofchar=18;
$cachethirdparties=array();
$cachecontacts=array();

// Define theme_datacolor array
$color_file = DOL_DOCUMENT_ROOT."/theme/".$conf->theme."/graph-color.php";
if (is_readable($color_file))
{
	include_once($color_file);
}
if (! is_array($theme_datacolor)) $theme_datacolor=array(array(120,130,150), array(200,160,180), array(190,190,220));


if (empty($action) || $action == 'show_month')	  // View by month
{
	$newparam=$param;   // newparam is for birthday links
	$newparam=preg_replace('/action=show_month&?/i','',$newparam);
	$newparam=preg_replace('/action=show_week&?/i','',$newparam);
	$newparam=preg_replace('/day=[0-9][0-9]&?/i','',$newparam);
	$newparam=preg_replace('/month=[0-9][0-9]&?/i','',$newparam);
	$newparam=preg_replace('/year=[0-9]+&?/i','',$newparam);
	echo '<table width="100%" class="nocellnopadd">';
	echo ' <tr class="liste_titre">';
	$i=0;
	while ($i < 7)
	{
		echo '  <td align="center">'.$langs->trans("Day".(($i+(isset($conf->global->MAIN_START_WEEK)?$conf->global->MAIN_START_WEEK:1)) % 7))."</td>\n";
		$i++;
	}
	echo " </tr>\n";

	// In loops, tmpday contains day nb in current month (can be zero or negative for days of previous month)
	//var_dump($FichInterArray);
	//print $tmpday;
	for($iter_week = 0; $iter_week < 6 ; $iter_week++)
	{
		echo " <tr>\n";
		for($iter_day = 0; $iter_day < 7; $iter_day++)
		{
			/* Show days before the beginning of the current month (previous month)  */
			if($tmpday <= 0)
			{
				$style='cal_other_month';
				echo '  <td class="'.$style.'" width="14%" valign="top"  nowrap="nowrap">';
				show_day_events ($db, $max_day_in_prev_month + $tmpday, $prev_month, $prev_year, $month, $style, $FichInterArray, $conf->global->AGENDA_MAX_EVENTS_DAY_VIEW, $maxnbofchar, $newparam);
				echo "  </td>\n";
			}
			/* Show days of the current month */
			elseif(($tmpday <= $max_day_in_month))
			{
				$curtime = dol_mktime (0, 0, 0, $month, $tmpday, $year);

				$style='cal_current_month';
				$today=0;
				$todayarray=dol_getdate($now,'fast');
				if ($todayarray['mday']==$tmpday && $todayarray['mon']==$month && $todayarray['year']==$year) $today=1;
				if ($today) $style='cal_today';

				echo '  <td class="'.$style.'" width="14%" valign="top"  nowrap="nowrap">';
				show_day_events($db, $tmpday, $month, $year, $month, $style, $FichInterArray, $conf->global->AGENDA_MAX_EVENTS_DAY_VIEW, $maxnbofchar, $newparam);
				echo "  </td>\n";
			}
			/* Show days after the current month (next month) */
			else
			{
				$style='cal_other_month';
				echo '  <td class="'.$style.'" width="14%" valign="top"  nowrap="nowrap">';
				show_day_events($db, $tmpday - $max_day_in_month, $next_month, $next_year, $month, $style, $FichInterArray, $conf->global->AGENDA_MAX_EVENTS_DAY_VIEW, $maxnbofchar, $newparam);
				echo "</td>\n";
			}
			$tmpday++;
		}
		echo " </tr>\n";
	}
	echo "</table>\n";
}
elseif ($action == 'show_week') // View by week
{
	$newparam=$param;   // newparam is for birthday links
	$newparam=preg_replace('/action=show_month&?/i','',$newparam);
	$newparam=preg_replace('/action=show_week&?/i','',$newparam);
	$newparam=preg_replace('/day=[0-9][0-9]&?/i','',$newparam);
	$newparam=preg_replace('/month=[0-9][0-9]&?/i','',$newparam);
	$newparam=preg_replace('/year=[0-9]+&?/i','',$newparam);
	echo '<table width="100%" class="nocellnopadd">';
	echo ' <tr class="liste_titre">';
	$i=0;
	while ($i < 7)
	{
		echo '  <td align="center">'.$langs->trans("Day".(($i+(isset($conf->global->MAIN_START_WEEK)?$conf->global->MAIN_START_WEEK:1)) % 7))."</td>\n";
		$i++;
	}
	echo " </tr>\n";

	// In loops, tmpday contains day nb in current month (can be zero or negative for days of previous month)
	//var_dump($taskarray);
	//print $tmpday;

	echo " <tr>\n";

	for($iter_day = 0; $iter_day < 7; $iter_day++)
	{
		if(($tmpday <= $max_day_in_month))
		{
			// Show days of the current week
			$curtime = dol_mktime (0, 0, 0, $month, $tmpday, $year);

			$style='cal_current_month';
			$today=0;
			$todayarray=dol_getdate($now,'fast');
			if ($todayarray['mday']==$tmpday && $todayarray['mon']==$month && $todayarray['year']==$year) $today=1;
			if ($today) $style='cal_today';

			echo '  <td class="'.$style.'" width="14%" valign="top"  nowrap="nowrap">';
			show_day_events($db, $tmpday, $month, $year, $month, $style, $FichInterArray, 0, $maxnbofchar, $newparam, 1, 300);
			echo "  </td>\n";
		}
		else
		{
			$style='cal_current_month';
			echo '  <td class="'.$style.'" width="14%" valign="top"  nowrap="nowrap">';
			show_day_events($db, $tmpday - $max_day_in_month, $next_month, $next_year, $month, $style, $FichInterArray, 0, $maxnbofchar, $newparam, 1, 300);
			echo "</td>\n";
		}
		$tmpday++;
	}
	echo " </tr>\n";

	echo "</table>\n";
}
else	// View by day
{
	$newparam=$param;   // newparam is for birthday links
	$newparam=preg_replace('/action=show_month&?/i','',$newparam);
	$newparam=preg_replace('/action=show_week&?/i','',$newparam);
	$newparam=preg_replace('/day=[0-9][0-9]&?/i','',$newparam);
	$newparam=preg_replace('/month=[0-9][0-9]&?/i','',$newparam);
	$newparam=preg_replace('/year=[0-9]+&?/i','',$newparam);
	// Code to show just one day
	$style='cal_current_month';
	$today=0;
	$todayarray=dol_getdate($now,'fast');
	if ($todayarray['mday']==$day && $todayarray['mon']==$month && $todayarray['year']==$year) $today=1;
	if ($today) $style='cal_today';

	$timestamp=dol_mktime(12,0,0,$month,$day,$year);
	$arraytimestamp=adodb_getdate(dol_mktime(12,0,0,$month,$day,$year));
	echo '<table width="100%" class="nocellnopadd">';
	echo ' <tr class="liste_titre">';
	echo '  <td align="center">'.$langs->trans("Day".$arraytimestamp['wday'])."</td>\n";
	echo " </tr>\n";
	echo " <tr>\n";
	echo '  <td class="'.$style.'" width="14%" valign="top"  nowrap="nowrap">';
	$maxnbofchar=80;
	show_day_events ($db, $day, $month, $year, $month, $style, $FichInterArray, 0, $maxnbofchar, $newparam, 1, 300);
	echo "</td>\n";
	echo " </tr>\n";
	echo '</table>';
}


llxFooter('$Date: 2011/07/31 22:23:20 $ - $Revision: 1.184 $');
$db->close();




/**
 * Show event of a particular day
 * @param   $db			  Database handler
 * @param   $day			 Day
 * @param   $month		   Month
 * @param   $year			Year
 * @param   $monthshown	  Current month shown in calendar view
 * @param   $style		   Style to use for this day
 * @param   $taskarray	  Array of events
 * @param   $maxPrint		Nb of actions to show each day on month view (0 means non limit)
 * @param   $maxnbofchar	 Nb of characters to show for event line
 * @param   $newparam		Parameters on current URL
 * @param   $showinfo		Add extended information (used by day view)
 * @param   $minheight	   Minimum height for each event. 60px by default.
 */
function show_day_events($db, $day, $month, $year, $monthshown, $style, &$FichInterArray, $maxPrint=0, $maxnbofchar=16, $newparam='', $showinfo=0, $minheight=60)
{
	global $user, $conf, $langs;
	global $filter, $filtera, $filtert, $filterd, $status;
	global $theme_datacolor;
	global $cachethirdparties, $cachecontacts;

	if ($_GET["maxprint"] == 'on') $maxPrint=0;   // Force to remove limits

	print '<div id="dayevent_'.sprintf("%04d",$year).sprintf("%02d",$month).sprintf("%02d",$day).'" class="dayevent">'."\n";
	$curtime = dol_mktime (0, 0, 0, $month, $day, $year);
	print '<table class="nobordernopadding" width="100%">';
	print '<tr style="background: #EEEEEE"><td align="left" nowrap="nowrap">';
	print '<a href="calendar.php?';
	print 'action=show_day&day='.str_pad($day, 2, "0", STR_PAD_LEFT).'&month='.str_pad($month, 2, "0", STR_PAD_LEFT).'&year='.$year;
	print $newparam;
	//.'&month='.$month.'&year='.$year;
	print '">';
	if ($showinfo) print dol_print_date($curtime,'daytext');
	else print dol_print_date($curtime,'%d');
	print '</a>';
	print '</td><td align="right" nowrap="nowrap">';
	/* report duration */
	$duration = get_events_duration_spent($day, '', $month, $year, $FichInterArray);
	if ($duration > 0)
		print ($duration ? convertSecondToTime($duration, 'allhourmin'):'');
	//var_dump($FichInterArray);

	if ($user->rights->management->readuser)
	{
		//$param='month='.$monthshown.'&year='.$year;
		$dateSel='&dateoyear='.sprintf("%04d",$year).'&dateomonth='.sprintf("%02d",$month).'&dateoday='.sprintf("%02d",$day);
	}	
	print '</td><td align="right" nowrap="nowrap">';
	if ($user->rights->management->readuser)
	{
		print '<a href="'.DOL_URL_ROOT.'/fichinter/'.(DOL_VERSION < "3.7.0"?"fiche":"card").'.php?action=create'.$dateSel.'&backtopage='.urlencode($_SERVER["PHP_SELF"].($newparam?'?'.$newparam:'')).'">';
		print img_picto($langs->trans("NewAction"),'edit_add.png');
		print '</a>';
	}
	print '</td></tr>';
	print '<tr height="'.$minheight.'"><td valign="top" colspan="2" nowrap="nowrap">';

	//$curtime = dol_mktime (0, 0, 0, $month, $day, $year);
	$i=0;

	foreach ($FichInterArray as $daykey => $notused)
	{
		$annee = date('Y',$daykey);
		$mois = date('m',$daykey);
		$jour = date('d',$daykey);
		if ($day==$jour && $month==$mois && $year==$annee)
		{
			foreach ($FichInterArray[$daykey] as $index => $fichinter)
			{
				//var_dump($FichInterArray[$daykey]);
				if ($i < $maxPrint || $maxPrint == 0)
				{
					$ponct=($fichinter->date_start_in_calendar == $fichinter->date_end_in_calendar);
					// Show rect of event
					$colorindex=0;
					if ($fichinter->author->id == $user->id || $fichinter->usertodo->id == $user->id || $fichinter->userdone->id == $user->id) $colorindex=1;
					if ($fichinter->type_code == 'BIRTHDAY') $colorindex=2;
					if ($fichinter->type_code == 'ICALEVENT') $color=$fichinter->icalcolor;
					else $color=sprintf("%02x%02x%02x",$theme_datacolor[$colorindex][0],$theme_datacolor[$colorindex][1],$theme_datacolor[$colorindex][2]);
					//print "x".$color;

					print '<div id="event_'.sprintf("%04d",$annee).sprintf("%02d",$mois).sprintf("%02d",$jour).'_'.$i.'" class="event">';
					print '<table class="cal_event" style="background: #'.$color.'; -moz-border-radius:4px; " width="100%"><tr>';
					print '<td nowrap="nowrap">';

					if ($fichinter->type_code != 'BIRTHDAY')
					{
						// Picto
						if (empty($fichinter->fulldayevent))
						{
							print $fichinter->getNomUrl(2).' ';
						}

						// Date
						if (empty($fichinter->fulldayevent))
						{
							//print '<strong>';
							$daterange='';

							// Show hours (start ... end)
							$tmpyearstart	= date('Y',$fichinter->date_start_in_calendar);
							$tmpmonthstart	= date('m',$fichinter->date_start_in_calendar);
							$tmpdaystart	= date('d',$fichinter->date_start_in_calendar);
							$tmpyearend		= date('Y',$fichinter->date_end_in_calendar);
							$tmpmonthend	= date('m',$fichinter->date_end_in_calendar);
							$tmpdayend	 	= date('d',$fichinter->date_end_in_calendar);
							// Hour start
							if ($tmpyearstart == $annee && $tmpmonthstart == $mois && $tmpdaystart == $jour)
							{
								$daterange.=dol_print_date($fichinter->date_start_in_calendar,'%H:%M');
								if ($fichinter->date_end_in_calendar && $fichinter->date_start_in_calendar != $fichinter->date_end_in_calendar)
								{
									if ($tmpyearstart == $tmpyearend && $tmpmonthstart == $tmpmonthend && $tmpdaystart == $tmpdayend)
									$daterange.='-';
									//else
									//print '...';
								}
							}
							if ($fichinter->date_end_in_calendar && $fichinter->date_start_in_calendar != $fichinter->date_end_in_calendar)
							{
								if ($tmpyearstart != $tmpyearend || $tmpmonthstart != $tmpmonthend || $tmpdaystart != $tmpdayend)
								{
									$daterange.='...';
								}
							}
							// Hour end
							if ($fichinter->date_end_in_calendar && $fichinter->date_start_in_calendar != $fichinter->date_end_in_calendar)
							{
								if ($tmpyearend == $annee && $tmpmonthend == $mois && $tmpdayend == $jour)
								$daterange.=dol_print_date($fichinter->date_end_in_calendar,'%H:%M');
							}
							//print $daterange;
							if ($fichinter->type_code != 'ICALEVENT')
							{
								$savlabel=$fichinter->libelle;
								$fichinter->libelle=$fichinter->ref." ".$daterange.'<br>';
								print getNomUrlTask($fichinter,0);
								$fichinter->libelle=$savlabel;
							}
							else
							{
								print $daterange;
							}
							//print '</strong> ';
							print "  ";
						}
						else
						{
							if ($fichinter->type_code != 'ICALEVENT')
							{
								$savlabel=$fichinter->libelle;
								$fichinter->libelle=$fichinter->ref.'<br>';
								print getNomUrlTask($fichinter,0);
								$fichinter->libelle=$savlabel;
							}
						   if ($showinfo)
						   {
								print $langs->trans("EventOnFullDay")."<br>\n";
						   }
						}

						// Show title
						print getNomUrlTask($fichinter,0,$maxnbofchar+10,'cal_event');

						// If action related to company / contact
						$linerelatedto='';$length=16;
						if (! empty($fichinter->socid) && ! empty($fichinter->contact->id)) $length=round($length/2);
						if (! empty($fichinter->socid) && $fichinter->socid > 0)
						{
							if (! is_object($cachethirdparties[(int) $fichinter->socid]))
							{
								$thirdparty=new Societe($db);
								$thirdparty->fetch((int) $fichinter->socid);
								$cachethirdparties[(int) $fichinter->socid]=$thirdparty;
							}
							else $thirdparty=$cachethirdparties[(int) $fichinter->socid];
							$linerelatedto.=$thirdparty->getNomUrl(1,'',$length);
						}
						if (! empty($fichinter->contact->id) && $fichinter->contact->id > 0)
						{
							if (! is_object($cachecontacts[$fichinter->contact->id]))
							{
								$contact=new Contact($db);
								$contact->fetch($fichinter->contact->id);
								$cachecontacts[$fichinter->contact->id]=$contact;
							}
							else $contact=$cachecontacts[$fichinter->contact->id];
							if ($linerelatedto) $linerelatedto.=' / ';
							$linerelatedto.=$contact->getNomUrl(1,'',$length);
						}
						if ($linerelatedto) print '<br>'.$linerelatedto;
					}
					
					if ($conf->projet->enabled && $fichinter->fk_projet)
					{
						// show projetstatic associé à la FI
						$projectstatic = new Project($db);
						$result=$projectstatic->fetch($fichinter->fk_projet);
						print "<br>".$projectstatic->getNomUrl(1);
					}


					// Show location
					if ($showinfo)
					{
						if ($fichinter->location)
						{
							print '<br>';
							print $langs->trans("Location").': '.$fichinter->location;
						}
					}

					print '</td>';
					// Status - Percent
					print '<td align="right" nowrap="nowrap">';
					//print $fichinter->getLibStatut(3);

					print '</td></tr></table>';
					print '</div>';
					$i++;
				}
				else
				{
					print '<a href="calendar.php?maxprint=on&month='.$monthshown.'&year='.$year;
					print ($status?'&status='.$status:'').($filter?'&filter='.$filter:'');
					print ($filtera?'&filtera='.$filtera:'').($filtert?'&filtert='.$filtert:'').($filterd?'&filterd='.$filterd:'');
					print '">'.img_picto("all","1downarrow_selected.png").' ...';
					print ' +'.(sizeof($taskarray[$daykey])-$maxPrint);
					print '</a>';
					break;
					//$ok=false;		// To avoid to show twice the link
				}
			}
			break;
		}
	}
	if (! $i) print '&nbsp;';
	print '</td></tr>';
	print '</table>';
	print '</div>'."\n";
}

function get_events_duration_spent($day, $week, $month, $year, $taskarray)
{
	$duration = 0;

	foreach ($taskarray as $daykey => $notused)
	{
		$annee = date('Y',$daykey);
		$semaine = date('W',$daykey);
		$mois = date('m',$daykey);
		$jour = date('d',$daykey);
		if ($day)
		{
			if ($day==$jour && $month==$mois && $year==$annee)
			{
				foreach ($taskarray[$daykey] as $index => $task)
				{
					$duration += $task->timespent_duration;
				}
			}
		}
		elseif ($week)
		{
			if ($week==$semaine && $year==$annee)
			{
				foreach ($taskarray[$daykey] as $index => $task)
				{
					$duration += $task->timespent_duration;
				}
			}
		}
		else
		{
			if ($month==$mois && $year==$annee)
			{
				foreach ($taskarray[$daykey] as $index => $task)
				{
					$duration += $task->timespent_duration;
				}
			}
		}
	}
	return $duration;
}


/**
 *		Renvoie nom clicable (avec eventuellement le picto)
 *	  Utilise $this->id, $this->code et $this->label
 * 		@param		withpicto		0=Pas de picto, 1=Inclut le picto dans le lien, 2=Picto seul
 *		@param		maxlength		Nombre de caracteres max dans libelle
 *		@param		classname		Force style class on a link
 * 		@param		option			''=Link to action,'birthday'=Link to contact
 *		@return		string			Chaine avec URL
 */
function getNomUrlTask($fichinter,$withpicto=0,$maxlength=0,$classname='')
{
	global $langs;

	
	$result='';
	$lien = '<a '.($classname?'class="'.$classname.'" ':'').'href="'.DOL_URL_ROOT.'/fichinter/'.(DOL_VERSION < "3.7.0"?"fiche":"card").'.php?id='.$fichinter->id.'">';
	$lienfin='</a>';
	//print $this->libelle;
	if ($withpicto == 2)
	{
		$libelle='';
		$libelleshort='';
	}
	else if (empty($fichinter->libelle))
	{
		$ref='';
		$libelle='';
		$libelleshort='';
	}
	else
	{
		$ref=$fichinter->ref;
		$libelle=$fichinter->libelle;
		$libelleshort=dol_trunc($libelle,$maxlength);
	}

	if ($withpicto)
	{
		//$libelle.=(($this->type_code && $libelle!=$langs->trans("Action".$this->type_code) && $langs->trans("Action".$this->type_code)!="Action".$this->type_code)?' ('.$langs->trans("Action".$this->type_code).')':'');
		$result.=$lien.img_object($langs->trans("ShowAction").': '.$libelle,'action').$lienfin;
	}
	if ($withpicto==1) $result.=' ';
	$result.=$lien.$libelleshort.$lienfin;
	return $result;
}


/**
 * Show filter form in agenda view
 * @param	   $form
 * @param 		$canedit
 * @param 		$status
 * @param 		$year
 * @param 		$month
 * @param 		$day
 * @param 		$showbirthday
 * @param 		$filtera
 * @param 		$filtert
 * @param 		$filterd
 * @param 		$pid
 * @param 		$socid
 */
function print_fichinter_filter($form,$canedit,$status,$year,$month,$day,$showDetailInter,$showFichInter,$filtera,$filtert,$filterd,$pid,$socid,$cid)
{
	global $conf,$langs, $db;
	// Filters
	print '<form name="listactionsfilter" action="'.$_SERVER["PHP_SELF"].'" method="POST">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="status" value="'.$status.'">';
	print '<input type="hidden" name="year" value="'.$year.'">';
	print '<input type="hidden" name="month" value="'.$month.'">';
	print '<input type="hidden" name="day" value="'.$day.'">';
	print '<table class="nobordernopadding" width="100%">';

	print '<tr><td nowrap="nowrap">';

	print '<table class="nobordernopadding">';

	if ($canedit)
	{
		print '<tr>';
		print '<td nowrap="nowrap">';
		print $langs->trans("FichInterAskedBy");
		print ' &nbsp;</td><td nowrap="nowrap">';
		print $form->select_users($filtera,'userasked',1,'',!$canedit);
		print '</td>';
		print '</tr>';

		print '<tr>';
		print '<td nowrap="nowrap">';
		print $langs->trans("or").' '.$langs->trans("FichInterDoneBy");
		print ' &nbsp;</td><td nowrap="nowrap">';
		print $form->select_users($filterd,'userdone',1,'',!$canedit);
		print '</td></tr>';
	}

	if ($conf->projet->enabled)
	{
		
		$formprojet=new FormProjets($db);
		print '<tr>';
		print '<td nowrap="nowrap">';
		print $langs->trans("Project").' &nbsp; ';
		print '</td><td nowrap="nowrap">';
		$formprojet->select_projects($socid?$socid:-1,$pid,'projectid');
		print '</td></tr>';
	}
	
	if ($conf->contrat->enabled)
	{
		$formcontrat=new FormContract($db);
		print '<tr>';
		print '<td nowrap="nowrap">';
		print $langs->trans("Contract").' &nbsp; ';
		print '</td><td nowrap="nowrap">';
		$formcontrat->select_contract($socid?$socid:-1,$cid,'contractid');
		print '</td></tr>';
	}


	print '</table>';
	print '</td>';

	// Buttons
	print '<td align="center" valign="middle" nowrap="nowrap">';
	print img_picto($langs->trans("ViewCal"),'object_calendar').' <input type="submit" class="button" style="width:120px" name="viewcal" value="'.$langs->trans("ViewCal").'">';
	print '<br>';
	print img_picto($langs->trans("ViewWeek"),'object_calendarweek').' <input type="submit" class="button" style="width:120px" name="viewweek" value="'.$langs->trans("ViewWeek").'">';
	print '<br>';
	print img_picto($langs->trans("ViewDay"),'object_calendarday').' <input type="submit" class="button" style="width:120px" name="viewday" value="'.$langs->trans("ViewDay").'">';
	print '<br>';
	print img_picto($langs->trans("ViewList"),'object_list').' <input type="submit" class="button" style="width:120px" name="viewlist" value="'.$langs->trans("ViewList").'">';
	print '</td>';
				
	// pour afficher les deux modes d'agenda
	print '<td align="left" valign="middle" nowrap="nowrap">';
	print '<input type="hidden" name="showfilter" value="1" >';
	print '<table>';
	print '<tr><td><input type="checkbox" id="showFichInter" name="showFichInter" value="1" '.(($showFichInter==1)?' checked="checked"':'').'> '.$langs->trans("AgendaShowFichInterEvents").'</td></tr>';
	print '<tr><td><input type="checkbox" id="showDetailInter" name="showDetailInter" value="1" '.(($showDetailInter==1)?' checked="checked"':'').'> '.$langs->trans("AgendaShowInterDetailEvents").'</td></tr>';
	print '</table>';
	print '</td>';
	print '</tr>';
	print '</table>';
	print '</form>';
}

?>