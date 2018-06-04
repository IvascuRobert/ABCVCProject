<?php
/* 
 * Copyright (C) 2012-2016		Charlie BENKE 		<charlie@patas-monkey.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
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
 *  \file	   htdocs/management/projet/tasks/index.php
 *  \ingroup	agenda
 *  \brief	  Home page of calendar tasks and consomated time
 */
 
$res =0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");		// For root directory
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php");	// For "custom" directory

require_once DOL_DOCUMENT_ROOT."/projet/class/project.class.php";
require_once DOL_DOCUMENT_ROOT."/projet/class/task.class.php";
require_once DOL_DOCUMENT_ROOT."/societe/class/societe.class.php";
require_once DOL_DOCUMENT_ROOT."/contact/class/contact.class.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/date.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/agenda.lib.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/project.lib.php";
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';

$langs->load("projects");
$langs->load("companies");

$filter=GETPOST("filter");
$filtera = GETPOST("userasked","int")?GETPOST("userasked","int"):GETPOST("filtera","int");
$filtert = GETPOST("usertodo","int")?GETPOST("usertodo","int"):GETPOST("filtert","int");
$filterd = GETPOST("userdone","int")?GETPOST("userdone","int"):GETPOST("filterd","int");

if (GETPOST('showFilter') ==1)
{
	$showTaskProjet= GETPOST('showTaskProjet','int');
	$showTimeUse= GETPOST('showTimeUse','int');
}
else
{
	$showTaskProjet= 1;
	$showTimeUse= 0;
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
$result = restrictedArea($user, 'projet', 0, '', 'tasks');

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
$status=GETPOST("status");
$maxprint=GETPOST("maxprint");
if (GETPOST('viewcal'))  { $action='show_month'; $day=''; }												   // View by month
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
    header("Location: ".DOL_URL_ROOT.'/projet/tasks/list.php?'.$param);
    exit;
}

/*
 * View
 */

$help_url='EN:Module_Projet_En|FR:Module_Projet|ES:Modulo_Projet_Es';
llxHeader('',$langs->trans("Projet"), $help_url);

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


$title=$langs->trans("DoneAndToDoTasks");
if ($status == 'done') $title=$langs->trans("DoneActions");
if ($status == 'todo') $title=$langs->trans("ToDoActions");

$param='';
if ($status)  $param="&status=".$status;
if ($filter)  $param.="&filter=".$filter;
if ($filtera) $param.="&filtera=".$filtera;
if ($filtert) $param.="&filtert=".$filtert;
if ($filterd) $param.="&filterd=".$filterd;
if ($socid)   $param.="&socid=".$socid;
if ($showbirthday) $param.="&showbirthday=1";
if ($pid)	 $param.="&projectid=".$pid;
if (GETPOST("type"))   $param.="&type=".GETPOST("type");
if ($action == 'show_day' || $action == 'show_week') $param.='&action='.$action;
if ($maxprint) $param.="&maxprint=on";

// Show navigation bar
if (empty($action) || $action=='show_month')
{
	
	$nav ='<a href="?year='.$prev_year.'&amp;month='.$prev_month.'&amp;region='.$region.$param.'&amp;showTaskProjet='.$showTaskProjet.'&amp;showTimeUse='.$showTimeUse.'">'.img_previous($langs->trans("Previous")).'</a>';
	$nav.=' <span id="month_name">'.dol_print_date(dol_mktime(0,0,0,$month,1,$year),"%b %Y").'</span>';
	$nav.='<a href="?year='.$next_year.'&amp;month='.$next_month.'&amp;region='.$region.$param.'&amp;showTaskProjet='.$showTaskProjet.'&amp;showTimeUse='.$showTimeUse.'">'.img_next($langs->trans("Next")).'</a>';
	$picto='calendar';
}
if ($action=='show_week')
{
	$nav ="<a href=\"?year=".$prev_year."&amp;month=".$prev_month."&amp;day=".$prev_day."&amp;region=".$region.$param.'&amp;showTaskProjet='.$showTaskProjet.'&amp;showTimeUse='.$showTimeUse."\">".img_previous($langs->trans("Previous"))."</a>\n";
	$nav.=" <span id=\"month_name\">".dol_print_date(dol_mktime(0,0,0,$month,1,$year),"%Y").", ".$langs->trans("Week")." ".$week;
	$nav.=" </span>\n";
	$nav.="<a href=\"?year=".$next_year."&amp;month=".$next_month."&amp;day=".$next_day."&amp;region=".$region.$param.'&amp;showTaskProjet='.$showTaskProjet.'&amp;showTimeUse='.$showTimeUse."\">".img_next($langs->trans("Next"))."</a>\n";
	$picto='calendarweek';
}
if ($action=='show_day')
{
	$nav ="<a href=\"?year=".$prev_year."&amp;month=".$prev_month."&amp;day=".$prev_day."&amp;region=".$region.$param.'&amp;showTaskProjet='.$showTaskProjet.'&amp;showTimeUse='.$showTimeUse."\">".img_previous($langs->trans("Previous"))."</a>\n";
	$nav.=" <span id=\"month_name\">".dol_print_date(dol_mktime(0,0,0,$month,$day,$year),"daytextshort");
	$nav.=" </span>\n";
	$nav.="<a href=\"?year=".$next_year."&amp;month=".$next_month."&amp;day=".$next_day."&amp;region=".$region.$param.'&amp;showTaskProjet='.$showTaskProjet.'&amp;showTimeUse='.$showTimeUse."\">".img_next($langs->trans("Next"))."</a>\n";
	$picto='calendarday';
}

// Must be after the nav definition
$param.='&year='.$year.'&month='.$month.($day?'&day='.$day:'');


$head = calendars_prepare_head('');

$title=$langs->trans("Calendar")." / ".$langs->trans("Tasks");
print_fiche_titre($title);
print_tasks_filter($form,$canedit,$status,$year,$month,$day,$showTaskProjet,$showTimeUse,$filtera,$filtert,$filterd,$pid,$socid);
dol_fiche_end();
print_fiche_titre($title,$link.' &nbsp; &nbsp; '.$nav, '');


// Get event in an array
$taskarray=array();

if ($showTaskProjet)
{
	$sql = 'SELECT pt.rowid, pt.label,';
	$sql.= ' pt.dateo,';
	$sql.= ' pt.datee,';
	//$sql.= ' a.datea,';
	//$sql.= ' a.datea2,';
	$sql.= ' pt.progress,';
	$sql.= ' pt.fk_user_creat,';
	$sql.= ' pt.fk_user_valid,';
	$sql.= ' pt.priority, ';
	$sql.= ' pt.fk_projet, ';
	$sql.= ' pt.fk_statut, ';
	$sql.= ' pt.planned_workload, ';
	$sql.= ' p.fk_soc' ; 
	$sql.= ' FROM '.MAIN_DB_PREFIX.'projet_task as pt';
	$sql.= ', '.MAIN_DB_PREFIX.'projet as p';
	$sql.= ', '.MAIN_DB_PREFIX.'user as u';
	$sql.= ' WHERE pt.fk_projet = p.rowid';
	$sql.= ' and pt.fk_user_creat = u.rowid';
	$sql.= ' AND u.entity in (0,'.$conf->entity.')';	// To limit to entity
	//$sql.= ' AND u.entity = '.$conf->entity;
	//if ($user->societe_id) $sql.= ' AND a.fk_soc = '.$user->societe_id; // To limit to external user company
	if ($pid) $sql.=" AND pt.fk_projet=".$db->escape($pid);
	if ($action == 'show_day')
	{
		$sql.= " AND (";
		$sql.= " (pt.dateo BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,$day,$year))."'";
		$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,$day,$year))."')";
		$sql.= " OR ";
		$sql.= " (pt.datee BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,$day,$year))."'";
		$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,$day,$year))."')";
		$sql.= " OR ";
		$sql.= " (pt.dateo < '".$db->idate(dol_mktime(0,0,0,$month,$day,$year))."'";
		$sql.= " AND pt.datee > '".$db->idate(dol_mktime(23,59,59,$month,$day,$year))."')";
		$sql.= ')';
	}
	else
	{
		// To limit array
		$sql.= " AND (";
		$sql.= " (pt.dateo BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,1,$year)-(60*60*24*7))."'";   // Start 7 days before
		$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,28,$year)+(60*60*24*10))."')";			// End 7 days after + 3 to go from 28 to 31
		$sql.= " OR ";
		$sql.= " (pt.datee BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,1,$year)-(60*60*24*7))."'";
		$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,28,$year)+(60*60*24*10))."')";
		$sql.= " OR ";
		$sql.= " (pt.dateo < '".$db->idate(dol_mktime(0,0,0,$month,1,$year)-(60*60*24*7))."'";
		$sql.= " AND pt.datee > '".$db->idate(dol_mktime(23,59,59,$month,28,$year)+(60*60*24*10))."')";
		$sql.= ')';
	}
	if ($filtera > 0 || $filtert > 0 || $filterd > 0)
	{
		$sql.= " AND (";
		if ($filtera > 0) $sql.= " pt.fk_user_creat = ".$filtera;
	
		if ($filterd > 0) $sql.= ($filtera>0||$filtert>0?" OR ":"")." pt.fk_user_valid = ".$filterd;
		$sql.= ")";
	}
	if ($status == 'done') { $sql.= " AND (pt.progress = 100 OR (pt.progress = -1 AND pt.dateo <= '".$db->idate($now)."'))"; }
	if ($status == 'todo') { $sql.= " AND ((pt.progress >= 0 AND pt.progress < 100) OR (pt.progress = -1 AND pt.datee > '".$db->idate($now)."'))"; }
	// Sort on date
	$sql.= ' ORDER BY dateo';
	//print $sql;
	
	dol_syslog("projet/tasks/calendar.php sql=".$sql, LOG_DEBUG);
	$resql=$db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);
		//print "num=".$num;
		$i=0;
		while ($i < $num)
		{
			$obj = $db->fetch_object($resql);
	
			// Create a new object action
			$task=new Task($db);
			$task->id=$obj->rowid;
			$task->datep=$db->jdate($obj->dateo);	  // datep and datef are GMT date
			$task->datef=$db->jdate($obj->datee);
			$task->type_code="TASK";
			$task->libelle=$obj->label;
			$task->percentage=$obj->progress;
			$task->author = new User($db);
			$task->author->id=$obj->fk_user_creat;
			$task->usertodo = new User($db);
			$task->usertodo->id=$obj->fk_user_action;
			$task->userdone = new User($db);
			$task->userdone->id=$obj->fk_user_done;
			$task->planned_workload=$obj->planned_workload;
			$task->priority=$obj->priority;
			$task->fulldayevent=$obj->fulldayevent;
			$task->location=$obj->location;
			$task->fk_statut=$obj->fk_statut;
			$task->societe = new Societe($db);
			$task->societe->id=$obj->fk_soc;
			$task->contact = new Contact($db);
			$task->contact->id=$obj->fk_contact;
			$task->fk_projet=$obj->fk_projet;
	
			// Defined date_start_in_calendar and date_end_in_calendar property
			// They are date start and end of action but modified to not be outside calendar view.
			if ($task->percentage <= 0)
			{
				$task->date_start_in_calendar=$task->datep;
				if ($task->datef != '' && $task->datef >= $task->datep) 
				{	$task->date_end_in_calendar=$task->datef; }
				else 
				{	$task->date_end_in_calendar=$task->datep; }
			}
			else
			{
				$task->date_start_in_calendar=$task->datep;
				if ($task->datef != '' && $task->datef >= $task->datep) $task->date_end_in_calendar=$task->datef;
				else $task->date_end_in_calendar=$task->datep;
			}
			// Define ponctual property
			if ($task->date_start_in_calendar == $task->date_end_in_calendar)
			{
				$task->ponctuel=1;
			}
	
			// Check values
			if ($task->date_end_in_calendar < $firstdaytoshow ||
			$task->date_start_in_calendar > $lastdaytoshow)
			{
				// This record is out of visible range
			}
			else
			{
				if ($task->date_start_in_calendar < $firstdaytoshow) $task->date_start_in_calendar=$firstdaytoshow;
				if ($task->date_end_in_calendar > $lastdaytoshow) $task->date_end_in_calendar=$lastdaytoshow;
	
				// Add an entry in actionarray for each day
				$daycursor=$task->date_start_in_calendar;
				$annee = date('Y',$daycursor);
				$mois = date('m',$daycursor);
				$jour = date('d',$daycursor);
	
				// Loop on each day covered by action to prepare an index to show on calendar
				$loop=true; $j=0;
				$daykey=dol_mktime(0,0,0,$mois,$jour,$annee);
				do
				{
					//if ($task->id==408) print 'daykey='.$daykey.' '.$task->datep.' '.$task->datef.'<br>';
					$taskarray[$daykey][]=$task;
					$j++;
	
					$daykey+=60*60*24;
					if ($daykey > $task->date_end_in_calendar) $loop=false;
				}
				while ($loop);
	
				//print 'Event '.$i.' id='.$task->id.' (start='.dol_print_date($task->datep).'-end='.dol_print_date($task->datef);
				//print ' startincalendar='.dol_print_date($task->date_start_in_calendar).'-endincalendar='.dol_print_date($task->date_end_in_calendar).') was added in '.$j.' different index key of array<br>';
			}
			$i++;
		}
	}
	else
	{
		dol_print_error($db);
	}
}


// affichage du temps consommé
if ($showTimeUse)
{
	$sql = 'SELECT pt.rowid, pt.label, ptt.note,';
	$sql.= ' ptt.task_date,';
	$sql.= ' ptt.task_duration,';
	$sql.= ' ptt.fk_user, ';
	$sql.= ' pt.fk_projet, ';
	$sql.= ' pt.fk_statut, ';
	$sql.= ' p.fk_soc' ; 
	$sql.= ' FROM '.MAIN_DB_PREFIX.'projet_task as pt';
	$sql.= ', '.MAIN_DB_PREFIX.'projet as p';
	$sql.= ', '.MAIN_DB_PREFIX.'projet_task_time as ptt';
	$sql.= ', '.MAIN_DB_PREFIX.'user as u';
	$sql.= ' WHERE pt.fk_projet = p.rowid';
	$sql.= ' AND ptt.fk_task = pt.rowid';
	$sql.= ' and ptt.fk_user = u.rowid';
	$sql.= ' AND u.entity in (0,'.$conf->entity.')';	// To limit to entity
	//$sql.= ' AND u.entity = '.$conf->entity;
	//if ($user->societe_id) $sql.= ' AND a.fk_soc = '.$user->societe_id; // To limit to external user company
	if ($pid) $sql.=" AND pt.fk_projet=".$db->escape($pid);
	if ($action == 'show_day')
	{
		$sql.= " AND ";
		$sql.= " (ptt.task_date BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,$day,$year))."'";
		$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,$day,$year))."')";
	}
	else
	{
		// To limit array
		$sql.= " AND ";
		$sql.= " (ptt.task_date BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,1,$year)-(60*60*24*7))."'";   // Start 7 days before
		$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,28,$year)+(60*60*24*10))."')";			// End 7 days after + 3 to go from 28 to 31		$sql.= ')';
	}
	if ($filtera > 0 )
	{
		$sql.= " AND ";
		if ($filtera > 0) $sql.= " ptt.fk_user = ".$filtera;
	}
	// Sort on date
	$sql.= ' ORDER BY task_date';
	//print $sql;
	
	dol_syslog("projet/tasks/calendar.php 2 sql=".$sql, LOG_DEBUG);
	$resql=$db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);
		//print "num=".$num;
		$i=0;
		while ($i < $num)
		{
			$obj = $db->fetch_object($resql);
	
			// Create a new object action
			$task=new Task($db);
			$task->id=$obj->rowid;
			$task->datep=$db->jdate($obj->task_date);	  // datep and datef are GMT date
			
			$task->datef=$db->jdate($obj->task_date);
			$task->type_code="TIMES";
			$task->libelle=($obj->task_duration/3600).'H : '.$obj->note." ";
			$task->timespent_duration=$obj->task_duration/3600;
			$task->author = new User($db);
			$task->author->id=$obj->fk_user;
			$task->fulldayevent=1;
			$task->societe = new Societe($db);
			$task->societe->id=$obj->fk_soc;
			$task->fk_statut=5;
			$task->fk_projet=$obj->fk_projet;
	
			// Defined date_start_in_calendar and date_end_in_calendar property
			// They are date start and end of action but modified to not be outside calendar view.
			if ($task->percentage <= 0)
			{
				$task->date_start_in_calendar=$task->datep;
				if ($task->datef != '' && $task->datef >= $task->datep) 
				{	$task->date_end_in_calendar=$task->datef; }
				else 
				{	$task->date_end_in_calendar=$task->datep; }
			}
			else
			{
				$task->date_start_in_calendar=$task->datep;
				if ($task->datef != '' && $task->datef >= $task->datep) $task->date_end_in_calendar=$task->datef;
				else $task->date_end_in_calendar=$task->datep;
			}
			// Define ponctual property
			if ($task->date_start_in_calendar == $task->date_end_in_calendar)
			{
				$task->ponctuel=1;
			}
	
			// Check values
			if ($task->date_end_in_calendar < $firstdaytoshow ||
			$task->date_start_in_calendar > $lastdaytoshow)
			{
				// This record is out of visible range
			}
			else
			{
				if ($task->date_start_in_calendar < $firstdaytoshow) $task->date_start_in_calendar=$firstdaytoshow;
				if ($task->date_end_in_calendar > $lastdaytoshow) $task->date_end_in_calendar=$lastdaytoshow;
	
				// Add an entry in actionarray for each day
				$daycursor=$task->date_start_in_calendar;
				$annee = date('Y',$daycursor);
				$mois = date('m',$daycursor);
				$jour = date('d',$daycursor);
	
				// Loop on each day covered by action to prepare an index to show on calendar
				$loop=true; $j=0;
				$daykey=dol_mktime(0,0,0,$mois,$jour,$annee);
				do
				{
					//if ($task->id==408) print 'daykey='.$daykey.' '.$task->datep.' '.$task->datef.'<br>';
					$taskarray[$daykey][]=$task;
					$j++;
	
					$daykey+=60*60*24;
					if ($daykey > $task->date_end_in_calendar) $loop=false;
				}
				while ($loop);
	
				//print 'Event '.$i.' id='.$task->id.' (start='.dol_print_date($task->datep).'-end='.dol_print_date($task->datef);
				//print ' startincalendar='.dol_print_date($task->date_start_in_calendar).'-endincalendar='.dol_print_date($task->date_end_in_calendar).') was added in '.$j.' different index key of array<br>';
			}
			$i++;
		}
	}
	else
	{
		dol_print_error($db);
		
	}
}

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
	$DurationPlanned=get_events_planned_workload('', '', $month, $year, $taskarray);
	$DurationSpent=get_events_duration_spent('', '', $month, $year, $taskarray);
	$newparam=$param;   // newparam is for birthday links
	$newparam=preg_replace('/action=show_month&?/i','',$newparam);
	$newparam=preg_replace('/action=show_week&?/i','',$newparam);
	$newparam=preg_replace('/day=[0-9][0-9]&?/i','',$newparam);
	$newparam=preg_replace('/month=[0-9][0-9]&?/i','',$newparam);
	$newparam=preg_replace('/year=[0-9]+&?/i','',$newparam);
	echo '<table width="100%" class="nocellnopadd">';
//	echo ' <tr >';
//	echo '<td colspan=2>'.$langs->trans("TimePlannedTot").$DurationPlanned."H</td>";
//	echo '<td colspan=2>'.$langs->trans("TimeSpentTot").$DurationSpent."H</td>";
//	echo '<td colspan=2>'.$langs->trans("TimeTotGap").($DurationPlanned-$DurationSpent)."H</td>";
//	echo ' </tr>';
	echo ' <tr class="liste_titre">';
	$i=0;
	while ($i < 7)
	{
		echo '  <td align="center">'.$langs->trans("Day".(($i+(isset($conf->global->MAIN_START_WEEK)?$conf->global->MAIN_START_WEEK:1)) % 7)).'</td>';
		$i++;
	}
	echo ' </tr>';

	// In loops, tmpday contains day nb in current month (can be zero or negative for days of previous month)
	//var_dump($taskarray);
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
				show_day_events ($db, $max_day_in_prev_month + $tmpday, $prev_month, $prev_year, $month, $style, $taskarray, $conf->global->AGENDA_MAX_EVENTS_DAY_VIEW, $maxnbofchar, $newparam);
				echo '  </td>';
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
				show_day_events($db, $tmpday, $month, $year, $month, $style, $taskarray, $conf->global->AGENDA_MAX_EVENTS_DAY_VIEW, $maxnbofchar, $newparam);
				echo "  </td>\n";
			}
			/* Show days after the current month (next month) */
			else
			{
				$style='cal_other_month';
				echo '  <td class="'.$style.'" width="14%" valign="top"  nowrap="nowrap">';
				show_day_events($db, $tmpday - $max_day_in_month, $next_month, $next_year, $month, $style, $taskarray, $conf->global->AGENDA_MAX_EVENTS_DAY_VIEW, $maxnbofchar, $newparam);
				echo "</td>\n";
			}
			$tmpday++;
		}
		echo ' </tr>';
	}
	echo '</table>';
}
elseif ($action == 'show_week') // View by week
{	$DurationPlanned=get_events_planned_workload('', $week, $month, $year, $taskarray);
	$DurationSpent=get_events_duration_spent('', $week, $month, $year, $taskarray);

	$newparam=$param;   // newparam is for birthday links
	$newparam=preg_replace('/action=show_month&?/i','',$newparam);
	$newparam=preg_replace('/action=show_week&?/i','',$newparam);
	$newparam=preg_replace('/day=[0-9][0-9]&?/i','',$newparam);
	$newparam=preg_replace('/month=[0-9][0-9]&?/i','',$newparam);
	$newparam=preg_replace('/year=[0-9]+&?/i','',$newparam);
	echo '<table width="100%" class="nocellnopadd">';
	echo ' <tr >';
//	echo '<td colspan=2>'.$langs->trans("TimePlannedTot").$DurationPlanned."H</td>";
//	echo '<td colspan=2>'.$langs->trans("TimeSpentTot").$DurationSpent."H</td>";
//	echo '<td colspan=3>'.$langs->trans("TimeTotGap").($DurationPlanned-$DurationSpent)."H</td>";
	echo ' </tr>';
	echo ' <tr class="liste_titre">';
	$i=0;
	while ($i < 7)
	{
		echo '  <td align="center">'.$langs->trans("Day".(($i+(isset($conf->global->MAIN_START_WEEK)?$conf->global->MAIN_START_WEEK:1)) % 7))."</td>\n";
		$i++;
	}
	echo ' </tr>';

	// In loops, tmpday contains day nb in current month (can be zero or negative for days of previous month)
	//var_dump($taskarray);
	//print $tmpday;

	echo ' <tr>';

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
			show_day_events($db, $tmpday, $month, $year, $month, $style, $taskarray, 0, $maxnbofchar, $newparam, 1, 300);
			echo '  </td>';
		}
		else
		{
			$style='cal_current_month';
			echo '  <td class="'.$style.'" width="14%" valign="top"  nowrap="nowrap">';
			show_day_events($db, $tmpday - $max_day_in_month, $next_month, $next_year, $month, $style, $taskarray, 0, $maxnbofchar, $newparam, 1, 300);
			echo '</td>';
		}
		$tmpday++;
	}
	echo '</tr>';
	echo '</table>';
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
	echo ' </tr>';
	echo ' <tr>';
	echo '  <td class="'.$style.'" width="14%" valign="top"  nowrap="nowrap">';
	$maxnbofchar=80;
	show_day_events ($db, $day, $month, $year, $month, $style, $taskarray, 0, $maxnbofchar, $newparam, 1, 300);
	echo '</td>';
	echo ' </tr>';
	echo '</table>';
}


$db->close();

/* TODO Export
print '
<a href="" id="actionagenda_ical_link"><img src="'.DOL_URL_ROOT.'/theme/common/ical.gif" border="0"/></a>
<a href="" id="actionagenda_vcal_link"><img src="'.DOL_URL_ROOT.'/theme/common/vcal.gif" border="0"/></a>
<a href="" id="actionagenda_rss_link"><img src="'.DOL_URL_ROOT.'/theme/common/rss.gif"  border="0"/></a>

<script>
$("#actionagenda_rss_link").attr("href","/public/agenda/agendaexport.php?format=rss&type=ActionAgenda&exportkey=dolibarr&token="+getToken()+"&status="+getStatus()+"&userasked="+getUserasked()+"&usertodo="+getUsertodo()+"&userdone="+getUserdone()+"&year="+getYear()+"&month="+getMonth()+"&day="+getDay()+"&showbirthday="+getShowbirthday()+"&action="+getAction()+"&projectid="+getProjectid()+"");
$("#actionagenda_ical_link").attr("href","/public/agenda/agendaexport.php?format=ical&type=ActionAgenda&exportkey=dolibarr&token="+getToken()+"&status="+getStatus()+"&userasked="+getUserasked()+"&usertodo="+getUsertodo()+"&userdone="+getUserdone()+"&year="+getYear()+"&month="+getMonth()+"&day="+getDay()+"&showbirthday="+getShowbirthday()+"&action="+getAction()+"&projectid="+getProjectid()+"");
$("#actionagenda_vcal_link").attr("href","/public/agenda/agendaexport.php?format=vcal&type=ActionAgenda&exportkey=dolibarr&token="+getToken()+"&status="+getStatus()+"&userasked="+getUserasked()+"&usertodo="+getUsertodo()+"&userdone="+getUserdone()+"&year="+getYear()+"&month="+getMonth()+"&day="+getDay()+"&showbirthday="+getShowbirthday()+"&action="+getAction()+"&projectid="+getProjectid()+"");
</script>
';
*/

llxFooter('$Date: 2011/07/31 22:23:20 $ - $Revision: 1.184 $');


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

function get_events_planned_workload($day, $week, $month, $year, $taskarray)
{
	$duration = 0;

	foreach ($taskarray as $daykey => $notused)
	{
		$annee = date('Y',$daykey);
		$mois = date('m',$daykey);
		$semaine = date('W',$daykey);
		$jour = date('d',$daykey);
		if ($day)
		{
			if ($day==$jour && $month==$mois && $year==$annee)
			{
				foreach ($taskarray[$daykey] as $index => $task)
				{	
					if ($task->datef!='')
						$datefin= $task->datef;
					else
						$datefin= 0;
					if (date("Ymd",$task->datep) == date("Ymd",$datefin))
						$duration += $task->planned_workload;
				}
			}
		}
		elseif ($week)
		{
			if ($week==$semaine && $month==$mois && $year==$annee)
			{
				foreach ($taskarray[$daykey] as $index => $task)
				{	
					if ($task->datef!='')
						$datefin= $task->datef;
					else
						$datefin= 0;
					if (date("Ymd",$task->datep) == date("Ymd",$datefin))
						$duration += $task->planned_workload;
				}
			}
		}
		else
		{
			if ($month==$mois && $year==$annee)
			{
				foreach ($taskarray[$daykey] as $index => $task)
				{	if ($task->datef!='')
						$datefin= $task->datef;
					else
						$datefin= 0;
					if (date("Ymd",$task->datep) == date("Ymd",$datefin))

						$duration += $task->planned_workload;
				}
			}
		}

	}
	return $duration;
}

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
function show_day_events($db, $day, $month, $year, $monthshown, $style, &$taskarray, $maxPrint=0, $maxnbofchar=16, $newparam='', $showinfo=0, $minheight=60)
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
	print '<a href="'.DOL_URL_ROOT.'/projet/tasks/calendar.php?';
	print 'action=show_day&day='.str_pad($day, 2, "0", STR_PAD_LEFT).'&month='.str_pad($month, 2, "0", STR_PAD_LEFT).'&year='.$year;
	print $newparam;
	//.'&month='.$month.'&year='.$year;
	print '">';
	if ($showinfo) print dol_print_date($curtime,'daytext');
	else print dol_print_date($curtime,'%d');
	print '</a>';
	print '</td><td align="center" nowrap="nowrap">';
	/* report duration */
//	$duration = get_events_planned_workload($day, '', $month, $year, $taskarray);
//	if ($duration > 0)
//		print ($duration ? convertSecondToTime($duration, 'allhourmin'):'');
	print '</td><td align="center" nowrap="nowrap">';
	/* report duration */
//	$duration = get_events_duration_spent($day, '', $month, $year, $taskarray);
//	if ($duration > 0)
//		print ($duration ? convertSecondToTime($duration, 'allhourmin'):'');
	print '</td><td align="right" nowrap="nowrap">';
	if ($user->rights->management->readuser)
	{
		$param='month='.$monthshown.'&year='.$year;
		$dateSel='&dateoyear='.sprintf("%04d",$year).'&dateomonth='.sprintf("%02d",$month).'&dateoday='.sprintf("%02d",$day);
		print '<a href="'.DOL_URL_ROOT.'/projet/tasks.php?action=create'.$dateSel.'&backtopage='.urlencode($_SERVER["PHP_SELF"].($newparam?'?'.$newparam:'')).'">';
		print img_picto($langs->trans("NewAction"),'edit_add.png');
		print '</a>';
	}
	print '</td></tr>';
	print '<tr height="'.$minheight.'"><td valign="top" colspan="4" nowrap="nowrap">';

	//$curtime = dol_mktime (0, 0, 0, $month, $day, $year);
	$i=0;

	foreach ($taskarray as $daykey => $notused)
	{
		$annee = date('Y',$daykey);
		$mois = date('m',$daykey);
		$jour = date('d',$daykey);
		if ($day==$jour && $month==$mois && $year==$annee)
		{
			foreach ($taskarray[$daykey] as $index => $task)
			{
				if ($i < $maxPrint || $maxPrint == 0)
				{
					$ponct=($task->date_start_in_calendar == $task->date_end_in_calendar);
					// Show rect of event
					$colorindex=0;
					if ($task->author->id == $user->id || $task->usertodo->id == $user->id || $task->userdone->id == $user->id) $colorindex=1;
					if ($task->type_code == 'TIMES') $colorindex=2;
					if ($fichinter->type_code == 'ICALEVENT') $color=$fichinter->icalcolor;
					else $color=sprintf("%02x%02x%02x",$theme_datacolor[$colorindex][0],$theme_datacolor[$colorindex][1],$theme_datacolor[$colorindex][2]);
					//print "x".$color;

					print '<div id="event_'.sprintf("%04d",$annee).sprintf("%02d",$mois).sprintf("%02d",$jour).'_'.$i.'" class="event">';
					print '<table class="cal_event" style="background: #'.$color.'; -moz-border-radius:4px; " width="100%"><tr>';
					print '<td nowrap="nowrap">';

					// Date
					if (empty($task->fulldayevent))
					{
						//print '<strong>';
						$daterange='';

						// Show hours (start ... end)
						$tmpyearstart  = date('Y',$task->date_start_in_calendar);
						$tmpmonthstart = date('m',$task->date_start_in_calendar);
						$tmpdaystart   = date('d',$task->date_start_in_calendar);
						$tmpyearend	= date('Y',$task->date_end_in_calendar);
						$tmpmonthend   = date('m',$task->date_end_in_calendar);
						$tmpdayend	 = date('d',$task->date_end_in_calendar);
						// Hour start
						if ($tmpyearstart == $annee && $tmpmonthstart == $mois && $tmpdaystart == $jour)
						{
							$daterange.=dol_print_date($task->date_start_in_calendar,'%H:%M');
							if ($task->date_end_in_calendar && $task->date_start_in_calendar != $task->date_end_in_calendar)
							{
								if ($tmpyearstart == $tmpyearend && $tmpmonthstart == $tmpmonthend && $tmpdaystart == $tmpdayend)
								$daterange.='-';
								//else
								//print '...';
							}
						}
						if ($task->date_end_in_calendar && $task->date_start_in_calendar != $task->date_end_in_calendar)
						{
							if ($tmpyearstart != $tmpyearend || $tmpmonthstart != $tmpmonthend || $tmpdaystart != $tmpdayend)
							{
								$daterange.='...';
							}
						}
						// Hour end
						if ($task->date_end_in_calendar && $task->date_start_in_calendar != $task->date_end_in_calendar)
						{
							if ($tmpyearend == $annee && $tmpmonthend == $mois && $tmpdayend == $jour)
							$daterange.=dol_print_date($task->date_end_in_calendar,'%H:%M');
						}
						//print $daterange;
						if ($task->type_code != 'ICALEVENT')
						{
							$savlabel=$task->libelle;
							$task->libelle=$daterange;
							print getNomUrlTask($task,0);
							$task->libelle=$savlabel;
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
						if ($task->type_code != 'TIMES')
						{
							if ($showinfo)
								print $langs->trans("EventOnFullDay")."<br>\n";
						}
					}

					// Show title
					print getNomUrlTask($task,0,$maxnbofchar,'cal_event');

					// If action related to company / contact
					$linerelatedto='';$length=16;
					if (! empty($task->societe->id) && ! empty($task->contact->id)) $length=round($length/2);
					if (! empty($task->societe->id) && $task->societe->id > 0)
					{
						if (! is_object($cachethirdparties[$task->societe->id]))
						{
							$thirdparty=new Societe($db);
							$thirdparty->fetch($task->societe->id);
							$cachethirdparties[$task->societe->id]=$thirdparty;
						}
						else $thirdparty=$cachethirdparties[$task->societe->id];
						$linerelatedto.=$thirdparty->getNomUrl(1,'',$length);
					}
					if (! empty($task->contact->id) && $task->contact->id > 0)
					{
						if (! is_object($cachecontacts[$task->contact->id]))
						{
							$contact=new Contact($db);
							$contact->fetch($task->contact->id);
							$cachecontacts[$task->contact->id]=$contact;
						}
						else $contact=$cachecontacts[$task->contact->id];
						if ($linerelatedto) $linerelatedto.=' / ';
						$linerelatedto.=$contact->getNomUrl(1,'',$length);
					}
					if ($linerelatedto) print '<br>'.$linerelatedto;

					
					// show project 
					$projectstatic = new Project($db);
					$result=$projectstatic->fetch($task->fk_projet);
					print "<br>".$projectstatic->getNomUrl(1);


					// Show location
					if ($showinfo)
					{
						if ($task->location)
						{
							print '<br>';
							print $langs->trans("Location").': '.$task->location;
						}
					}

					print '</td>';
					// Status - Percent
					print '<td align="right" nowrap="nowrap">';
					print $task->getLibStatut(3);

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
/**
 *		Renvoie nom clicable (avec eventuellement le picto)
 *	  Utilise $this->id, $this->code et $this->label
 * 		@param		withpicto		0=Pas de picto, 1=Inclut le picto dans le lien, 2=Picto seul
 *		@param		maxlength		Nombre de caracteres max dans libelle
 *		@param		classname		Force style class on a link
 * 		@param		option			''=Link to action,'birthday'=Link to contact
 *		@return		string			Chaine avec URL
 */
function getNomUrlTask($task, $withpicto=0, $maxlength=0, $classname='')
{
	global $langs;

	
	$result='';
	if($task->type_code=="TIMES")
		$lien = '<a '.($classname?'class="'.$classname.'" ':'').'href="'.DOL_URL_ROOT.'/projet/tasks/time.php?id='.$task->id.'&withproject=1">';
	else
		$lien = '<a '.($classname?'class="'.$classname.'" ':'').'href="'.DOL_URL_ROOT.'/projet/tasks/task.php?id='.$task->id.'">';
	$lienfin='</a>';
	//print $this->libelle;
	if ($withpicto == 2)
	{
		$libelle='';
		$libelleshort='';
	}
	else if (empty($task->libelle))
	{
		$libelle='';
		$libelleshort='';
	}
	else
	{
		$libelle=$task->libelle;
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
function print_tasks_filter($form,$canedit,$status,$year,$month,$day,$showTaskProjet,$showTimeUse,$filtera,$filtert,$filterd,$pid,$socid)
{
	global $conf,$langs, $db;

	$formproject=new FormProjets($db);
	
	// Filters
	if ($canedit || $conf->projet->enabled)
	{
		print '<form name="listactionsfilter" action="'.$_SERVER["PHP_SELF"].'" method="POST">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="status" value="'.$status.'">';
		print '<input type="hidden" name="year" value="'.$year.'">';
		print '<input type="hidden" name="month" value="'.$month.'">';
		print '<input type="hidden" name="day" value="'.$day.'">';
		print '<input type="hidden" name="showbirthday" value="'.$showbirthday.'">';
		print '<table class="nobordernopadding" width="100%">';
		if ($canedit || $conf->projet->enabled)
		{
			print '<tr><td nowrap="nowrap">';

			print '<table class="nobordernopadding">';

			if ($canedit)
			{
				print '<tr>';
				print '<td nowrap="nowrap">';
				print $langs->trans("TasksAskedBy");
				print ' &nbsp;</td><td nowrap="nowrap">';
				print $form->select_users($filtera,'userasked',1,'',!$canedit);
				print '</td>';
				print '</tr>';

//				print '<tr>';
//				print '<td nowrap="nowrap">';
//				print $langs->trans("or").' '.$langs->trans("ActionsToDoBy");
//				print ' &nbsp;</td><td nowrap="nowrap">';
//				print $form->select_users($filtert,'usertodo',1,'',!$canedit);
//				print '</td></tr>';

				print '<tr>';
				print '<td nowrap="nowrap">';
				print $langs->trans("or").' '.$langs->trans("TasksDoneBy");
				print ' &nbsp;</td><td nowrap="nowrap">';
				print $form->select_users($filterd,'userdone',1,'',!$canedit);
				print '</td></tr>';
			}

			if ($conf->projet->enabled)
			{
				print '<tr>';
				print '<td nowrap="nowrap">';
				print $langs->trans("Project").' &nbsp; ';
				print '</td><td nowrap="nowrap">';
				$formproject->select_projects($socid?$socid:-1,$pid,'projectid');
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
			print '<input type="hidden" id="showFilter" name="showFilter" value="1" >';
			print '<table>';
			print '<tr><td><input type="checkbox" id="showTaskProjet" name="showTaskProjet" value="1" '.(($showTaskProjet==1)?' checked="checked"':'').'> '.$langs->trans("AgendaShowTaskProjet").'</td></tr>';
			print '<tr><td><input type="checkbox" id="showTimeUse" name="showTimeUse" value="1" '.(($showTimeUse==1)?' checked="checked"':'').'> '.$langs->trans("AgendaShowTimeUse").'</td></tr>';
			print '</table>';
			print '</td>';
			print '</tr>';
		}
		print '</table>';
		print '</form>';
	}
}

?>