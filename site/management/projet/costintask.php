<?php
/* Copyright (C) 2014-2016	Charlie BENKE	<charlie@patas-monkey.com>
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
 *	\file       	htdocs/management/costintask.php
 *	\ingroup    	taskproduct
 *	\brief      	Page of product associated to the task
 */

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");		// For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");	// For "custom" directory

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php";

dol_include_once ('/management/class/managementtask.class.php');

$langs->load('companies');
$langs->load('task');
$langs->load('management@management');
$langs->load('products');
if (! empty($conf->margin->enabled))
  $langs->load('margins');

$error=0;

$id=GETPOST('id','int');
$ref=GETPOST('ref','alpha');

$socid=GETPOST('socid','int');
$action=GETPOST('action','alpha');
$confirm=GETPOST('confirm','alpha');
$lineid=GETPOST('lineid','int');
$key=GETPOST('key');
$parent=GETPOST('parent');



$mine = $_REQUEST['mode']=='mine' ? 1 : 0;
//if (! $user->rights->projet->all->lire) $mine=1;	// Special for projects
$withproject=GETPOST('withproject','int');
$project_ref = GETPOST('project_ref','alpha');

// Security check
//$socid=0;
if ($user->societe_id > 0) $socid = $user->societe_id;
if (!$user->rights->projet->lire) accessforbidden();
//$result = restrictedArea($user, 'projet', $id, '', 'task'); // TODO ameliorer la verification


//print "withproject=".$withproject."<br>";

// Nombre de ligne pour choix de produit/service predefinis
$NBLINES=4;


$object = new Task($db);
$projectstatic = new Project($db);
$facturestatic = new Facture($db);

if ($id > 0 || ! empty($ref))
{
	if ($object->fetch($id,$ref) > 0)
	{
		if (empty($id))
			$id=$object->id;
		$projectstatic->fetch($object->fk_project);
		if (! empty($projectstatic->socid)) $projectstatic->fetch_thirdparty();
		//if (! empty($projectstatic->socid)) $projectstatic->societe->fetch($projectstatic->socid);
		$object->project = dol_clone($projectstatic);
	}
	else
	{
		dol_print_error($db);
	}

	if ($action != 'add')
	{
		$ret=$object->fetch($id, $ref);
		if ($ret == 0)
		{
			$langs->load("errors");
			setEventMessage($langs->trans('ErrorRecordNotFound'), 'errors');
			$error++;
		}
		else if ($ret < 0)
		{
			setEventMessage($object->error, 'errors');
			$error++;
		}
	}
}

// Retreive First Task ID of Project if withprojet is on to allow project prev next to work
if (! empty($project_ref) && ! empty($withproject))
{
	if ($projectstatic->fetch('', $project_ref) > 0)
	{
		$tasksarray=$object->getTasksArray(0, 0, $projectstatic->id, $socid, 0);
		if (count($tasksarray) > 0)
		{
			$id=$tasksarray[0]->id;
			$object->fetch($id);
		}
		else
		{
			header("Location: ".DOL_URL_ROOT.'/projet/tasks.php?id='.$projectstatic->id.(empty($mode)?'':'&mode='.$mode));
		}
	}
}

/*
 * Actions
 */

$parameters=array('socid'=>$socid);


/*
 * Actions
 */

if ($id || $ref)
{
	$ManagementTask = new ManagementTask($db);
	$ManagementTask->fetch($id, $ref);
	$ManagementTask->fetchMT($id, $ref);

}


if ($action == 'setbillingmode')
{	
	// on affecte le service associé à la tache
	$ManagementTask->setbillingmode(GETPOST('billingmode'));
	$ManagementTask->billingmode = GETPOST('billingmode');
	$action="";
}

if ($action == 'setproduct')
{	
	// on affecte le service associé à la tache
	$ManagementTask->setproduct( GETPOST('idprod'));
	$ManagementTask->fk_product = GETPOST('idprod');
	$action="";
}

if ($action == 'setaveragethm')
{	
	// on affecte le service associé à la tache
	$ManagementTask->setaveragethm( GETPOST('averagethm'));
	$ManagementTask->average_thm = GETPOST('averagethm');
	$action="";
}
/*
 * View
 */

// search products by keyword and/or categorie
if ($action == 'search')
{
	$sql = 'SELECT DISTINCT p.rowid, p.ref, p.label, p.price, p.fk_product_type as type,  p.pmp';
	$sql.= ' FROM '.MAIN_DB_PREFIX.'product as p';
	$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_product as cp ON p.rowid = cp.fk_product';
	$sql.= ' WHERE p.entity IN ('.getEntity("product", 1).')';
	if ($key != "")
	{
		$sql.= " AND (p.ref LIKE '%".$key."%'";
		$sql.= " OR p.label LIKE '%".$key."%')";
	}
	if ($conf->categorie->enabled && $parent != -1 and $parent)
	{
		$sql.= " AND cp.fk_categorie ='".$db->escape($parent)."'";
	}
	$sql.= " ORDER BY p.ref ASC";

	$resql = $db->query($sql);
}
//print $sql;

$productstatic = new Product($db);
$form = new Form($db);

llxHeader("","",$langs->trans("CardProduct".$product->type));

dol_htmloutput_mesg($mesg);


/*
 * View
 */


$form = new Form($db);
$formother = new FormOther($db);
$formfile = new FormFile($db);
$companystatic=new Societe($db);
$userstatic = new User($db);

$now=dol_now();

/*
 * Show object in view mode
 */

if (! empty($withproject))
{
	// Tabs for project
	$tab='tasks';
	$head=project_prepare_head($projectstatic);
	dol_fiche_head($head, $tab, $langs->trans("Project"),0,($projectstatic->public?'projectpub':'project'));

	$param=($mode=='mine'?'&mode=mine':'');
	$linkback = '<a href="'.DOL_URL_ROOT.'/projet/list.php">'.$langs->trans("BackToList").'</a>';
	
	if (DOL_VERSION >= "5.0.0") {
		
		$morehtmlref='<div class="refidno">';
		$morehtmlref.=$projectstatic->title;
		
		if ($projectstatic->thirdparty->id > 0)
			$morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . $projectstatic->thirdparty->getNomUrl(1, 'project');
		$morehtmlref.='</div>';
		
		// Define a complementary filter for search of next/prev ref.
		if (! $user->rights->projet->all->lire)
		{
			$objectsListId = $object->getProjectsAuthorizedForUser($user,0,0);
			$projectstatic->next_prev_filter=" rowid in (".(count($objectsListId)?join(',',array_keys($objectsListId)):'0').")";
		}
		dol_banner_tab($projectstatic, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

		print '<div class="fichecenter">';
		print '<div class="fichehalfleft">';
		print '<div class="underbanner clearboth"></div>';
	
		print '<table class="border" width="100%">';

		// Visibility
		print '<tr><td class="titlefield">'.$langs->trans("Visibility").'</td><td>';
		if ($projectstatic->public) print $langs->trans('SharedProject');
		else print $langs->trans('PrivateProject');
		print '</td></tr>';

		// Date start - end
		print '<tr><td>'.$langs->trans("DateStart").' - '.$langs->trans("DateEnd").'</td><td>';
		print dol_print_date($projectstatic->date_start,'day');
		$end=dol_print_date($projectstatic->date_end,'day');
		if ($end) print ' - '.$end;
		print '</td></tr>';

		// Budget
		print '<tr><td>'.$langs->trans("Budget").'</td><td>';
		if (strcmp($projectstatic->budget_amount, '')) print price($projectstatic->budget_amount,'',$langs,1,0,0,$conf->currency);
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
		print nl2br($projectstatic->description);
		print '</td></tr>';
		
		// Categories
		if($conf->categorie->enabled) {
		    print '<tr><td valign="middle">'.$langs->trans("Categories").'</td><td>';
		    print $form->showCategories($projectstatic->id,'project',1);
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
	
		// Ref
		print '<tr><td width="30%">';
		print $langs->trans("Ref");
		print '</td><td>';
		// Define a complementary filter for search of next/prev ref.
		if (! $user->rights->projet->all->lire)
		{
		    $projectsListId = $projectstatic->getProjectsAuthorizedForUser($user,$mine,0);
		    $projectstatic->next_prev_filter=" rowid in (".(count($projectsListId)?join(',',array_keys($projectsListId)):'0').")";
		}
		print $form->showrefnav($projectstatic,'project_ref','',1,'ref','ref','',$param.'&withproject=1');
		print '</td></tr>';
	
		// Project
		print '<tr><td>'.$langs->trans("Label").'</td><td>'.$projectstatic->title.'</td></tr>';
	
		// Company
		print '<tr><td>'.$langs->trans("Company").'</td><td>';
		if (! empty($projectstatic->societe->id)) print $projectstatic->societe->getNomUrl(1);
		else print '&nbsp;';
		print '</td>';
		print '</tr>';
	
		// Visibility
		print '<tr><td>'.$langs->trans("Visibility").'</td><td>';
		if ($projectstatic->public) print $langs->trans('SharedProject');
		else print $langs->trans('PrivateProject');
		print '</td></tr>';
	
		// Statut
		print '<tr><td>'.$langs->trans("Status").'</td><td>'.$projectstatic->getLibStatut(4).'</td></tr>';
	
		print '</table>';
	}
	dol_fiche_end();

}

$soc = new Societe($db);
$soc->fetch($object->socid);

$head = task_prepare_head($object);

dol_fiche_head($head, 'management', $langs->trans("Task"), 0, 'projecttask');


print '<table border=0 width="100%">';
print '<tr ><td width=48% valign=top>';
print '<table class="border" width="100%">';

$param=($withproject?'&withproject=1':'');
$linkback=$withproject?'<a href="'.DOL_URL_ROOT.'/projet/tasks.php?id='.$projectstatic->id.'">'.$langs->trans("BackToList").'</a>':'';

// Ref
print '<tr><td width="30%">';
print $langs->trans("Ref");
print '</td><td colspan="5" height=16px>';
if (! GETPOST('withproject') || empty($projectstatic->id))
{
	$projectsListId = $projectstatic->getProjectsAuthorizedForUser($user,$mine,1);
	$object->next_prev_filter=" fk_projet in (".$projectsListId.")";
}
else $object->next_prev_filter=" fk_projet = ".$projectstatic->id;

print $form->showrefnav($object,'ref',$linkback,1,'ref','ref','',$param);
print '</td></tr>';

// Label
print '<tr><td>'.$langs->trans("Label").'</td><td colspan="5">'.$object->label.'</td></tr>';


print '</table><br>';

print '<table class="border" width="100%">';
// Planned workload
print '<tr><td width=30% >'.$langs->trans("PlannedTaskWorkload").'</td><td width=20% align=right>';
print convertSecondToTime($object->planned_workload,'allhourmin');
// Statut
print '<td width=30% >'.$langs->trans("Statut").'</td><td align=left width=20% >';
print $object->getLibStatut(4);
print '</td></tr>';

// Duration effective
print '<tr><td>'.$langs->trans("DurationEffective").'</td><td align=right>';
print convertSecondToTime($object->duration_effective,'allhourmin');
$calculatedprogress = 0;
$EstimatedLeftDuration = $object->planned_workload - $object->duration_effective;
if ($object->planned_workload) {
	print '<td >'.$langs->trans("CalculatedLeftDuration").'</td><td align=right>';
	// et un petit calcul sympa sur le nombre d'heure restant
	$calculatedprogress = 100 * $object->duration_effective / $object->planned_workload;
	if ($object->progress)
		$EstimatedLeftDuration = $object->planned_workload * ($calculatedprogress / $object->progress) - $object->duration_effective;
	print convertSecondToTime($EstimatedLeftDuration, 'allhourmin');
}

print '</td></tr>';

// Declared progress
print '<tr><td>'.$langs->trans("ProgressDeclared").'</td><td align=right>';
print $object->progress.' %';
print '</td>';



// Calculated progress
//print '<tr><td>'.$langs->trans("ProgressCalculated").'</td><td >';
//print round($calculatedprogress ,2).' %';
//print '</td></tr>';

print '<td width="30%">'.$langs->trans("CalculatedTotalDuration").'</td><td align=right >';
// et un petit calcul sympa sur le nombre total
$PlannedLeftDuration = $object->duration_effective + $EstimatedLeftDuration;
print convertSecondToTime($PlannedLeftDuration , 'allhourmin');

print '</td></tr>';



// Project
if (empty($withproject))
{
	print '<tr><td>'.$langs->trans("Project").'</td><td>';
	print $projectstatic->getNomUrl(1);
	print '</td></tr>';

	// Third party
	print '<td>'.$langs->trans("ThirdParty").'</td><td>';
	if ($projectstatic->societe->id) 
	{	
		$projectstatic->fetch_thirdparty();
		print $projectstatic->societe->getNomUrl(1);
	}
	else print '&nbsp;';
	print '</td></tr>';
}
print '</table>';
/* Barre d'action			*/
print '<div class="tabsAction">';
if ($action == '' && $user->rights->management->showprice )
{	
	//
	print '<form>';
	print '<table width=100% border=0><tr>';
	print '<input type=hidden name=id value='.$id.'>';
	print '<input type=hidden name=action value="setbillingmode">';
	print '<input type=hidden name=withproject value='.$withproject.'>';
	
	print '<td width=30% align=left>';
	$ArrBillingMode=array($langs->trans("BillingTimePlanned"), $langs->trans("BillingTimeMade"), $langs->trans("BillingTimePassed"));
	print $langs->trans("BillingMode");
	print '</td><td align=left width=50% >';
	print $form->selectarray('billingmode', $ArrBillingMode, $ManagementTask->billingmode, 1); 
	print '<input type=submit value='.$langs->trans("Save").'></td>';
	print '<td width=20% align=right>';
	// on a le droit de transférer que si la tache est terminée et que le projet est associé à un tiers
	$objectelement="management_managementtask";
	if ($object->fk_statut == 3 && $user->rights->facture->creer ) 
	{
		if ($projectstatic->socid)
			print '<a class="butAction" href="'.DOL_URL_ROOT.'/compta/facture.php?action=create&amp;origin='.$objectelement.'&amp;originid='.$object->id.'&amp;socid='.$projectstatic->socid.'">'.$langs->trans("CreateTaskBill").'</a>';
		else
			print '<span class="butActionRefused" title="' . $langs->trans("NoAssociatedCustomersOnProject") . '">' . $langs->trans('CreateTaskBill') . '</span>';
		//print '<a class="butAction" href="'.DOL_URL_ROOT.'/factory/project/costintask.php?action=adjustcost&withproject=1&id='.$id.'">'.$langs->trans("TransfertToBill").'</a>';
	}
	print '</td>';
	print '</tr></table>';
	print '</form>';

}
print '</div>'; 


print '</td><td width=4%></td>';
print '<td width=48% valign=top>';
// affiche les infos financieres selon habilitation
if ($user->rights->management->showprice )
{
	print '<table class="border" width="100%">';

	$price =0;
	if ($ManagementTask->fk_product)
	{
		$productstatic->fetch($ManagementTask->fk_product);
		$price = $productstatic->price;
	}
	// service associé à la tache pour déterminer le cout horaire de facturation
	print '<tr><td width=30%><table class="nobordernopadding" width="100%"><tr><td>'.$langs->trans("ServiceAssociated").'</td>';
	if ($action != 'editproduct' && $object->statut == 0) 
		print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editproduct&amp;id='.$object->id.($withproject?'&withproject=1':'').'">'.img_edit($langs->trans('Modify'),1).'</a></td>';

	print '</tr></table></td><td width=20%>';
	if ($action == 'editproduct')
	{
		print '<form name="editproduct" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.($withproject?'&withproject=1':'').'" method="post">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="setproduct">';
		$form->select_produits($ManagementTask->fk_product, 'idprod', "1", $conf->product->limit_size, '', 1, 2, '', 1);
		print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
		print '</form>';
	}
	else
	{
		if ($ManagementTask->fk_product)
			print $productstatic->getNomUrl(1);
	}
	print '</td>';

	// estimated cost of sell
	print '<td width=30%>'.$langs->trans("SellPriceOfTask").'</td><td width=20% align=right><b>';
	if ($object->planned_workload) 
		print  price ( ($object->planned_workload/3600) * $price ,0,'',1,-1,2,'auto');
	else 
		print '';
	print '</b></td></tr>';
	$thmmoyenreel= $ManagementTask->get_thm();
	print '<tr><td width=30%><table class="nobordernopadding" width="100%"><tr><td>'.$langs->trans("PlannedTHM").'</td>';
	if ($action != 'editaveragethm' && $object->statut == 0) 
		print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editaveragethm&amp;id='.$object->id.($withproject?'&withproject=1':'').'">'.img_edit($langs->trans('Modify'),1).'</a></td>';
	print '</tr></table></td><td align=right width=20% >';
	if ($action == 'editaveragethm')
	{
		print '<form name="editaveragethm" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.($withproject?'&withproject=1':'').'" method="post">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="setaveragethm">';
		print '<input type="text" size=5 name="averagethm" value="'.price2num($ManagementTask->average_thm).'">';
		print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
		print '</form>';
	}
	else
	{
		print "<font color=blue>".price($ManagementTask->average_thm,0,'',1,-1,2,'auto')."</font>";;
	}

	print '</td>';
	print '<td width=30%>'.$langs->trans("RealTHM").'</td>';
	print "<td width=20% align=right><font color=red>".price($thmmoyenreel,0,'',1,-1,2,'auto')."</font>";
	print '</td></tr>';
	print '</table><br>';

	print '<table class="border" width="100%">';

	// il faut récupérer le thm moyen sur la saisie déjà réalisé :
	// = somme des heures * thm / somme des heures total de la tache

	// Declared cost of thm
	print '<tr><td width=30%>'.$langs->trans("DeclaredCostPriceOfTask").'</td>';
	print "<td align=right width=20%><font color=blue>".price(($object->planned_workload/3600) * $ManagementTask->average_thm,0,'',1,-1,2,'auto').'</font></td>'; // $object->thm de
	//print '</td><td>';
	print '<td width=30%>'.$langs->trans("DeclaredRealCostPriceOfTask").'</td>';
	print '<td align=right width=20%><font color=red>'.price(($object->planned_workload/3600) * $thmmoyenreel,0,'',1,-1,2,'auto').'</font>';
	print '</td></tr>';

	// estimated cost of thm
	print '<tr><td>'.$langs->trans("EstimatedCostPriceOfTask").'</td><td align=right>';
	print "<font color=blue>".price((($object->duration_effective)/3600) * $ManagementTask->average_thm,0,'',1,-1,2,'auto').'</font>'; // $object->thm de
	print '</td>';
	print '<td>'.$langs->trans("EstimatedRealCostPriceOfTask").'</td>';
	print '<td align=right>';
	print "<font color=red>".price((($object->duration_effective)/3600) * $thmmoyenreel,0,'',1,-1,2,'auto').'</font>'; // $object->thm de
	print '</td></tr>';

	// left cost of thm
	print '<tr><td>'.$langs->trans("LeftCostPriceOfTask").'</td><td align=right>';
	print "<font color=blue>".price((($EstimatedLeftDuration)/3600) * $ManagementTask->average_thm,0,'',1,-1,2,'auto').'</font>'; // $object->thm de
	print '</td>';
	print '<td>'.$langs->trans("LeftRealCostPriceOfTask").'</td>';
	print '<td align=right>';
	print "<font color=red>".price((($EstimatedLeftDuration)/3600) * $thmmoyenreel,0,'',1,-1,2,'auto').'</font>'; // $object->thm de
	print '</td></tr>';

	// TOTAL cost of thm
	print '<tr><td>'.$langs->trans("TotalCostPriceOfTask").'</td><td align=right>';
	print "<font color=blue>".price((($EstimatedLeftDuration+$object->duration_effective)/3600) * $ManagementTask->average_thm,0,'',1,-1,2,'auto').'</font>'; // $object->thm de
	print '</td>';
	print '<td>'.$langs->trans("TotalRealCostPriceOfTask").'</td>';
	print '<td align=right>';
	print "<font color=red>".price((($EstimatedLeftDuration+$object->duration_effective)/3600) * $thmmoyenreel,0,'',1,-1,2,'auto').'</font>'; // $object->thm de
	print '</td></tr>';

	print '</table>';
}
print '</td></tr>';
print '</table>';

dol_fiche_end();

/*
 *  List of time spent
*/
$sql = "SELECT u.lastname, u.firstname, t.fk_user, date_format(t.task_date,'%Y-%m') as moissaisie, sum(t.task_duration) as totalhrs, sum((t.task_duration/3600) * t.thm) as coutthm";
$sql .= " FROM ".MAIN_DB_PREFIX."projet_task_time as t";
$sql .= " , ".MAIN_DB_PREFIX."user as u";
$sql .= " WHERE t.fk_task =".$object->id;
$sql .= " AND t.fk_user = u.rowid";
$sql .= " GROUP BY u.lastname, u.firstname, t.fk_user, moissaisie";
$sql .= " ORDER BY u.lastname, u.firstname, moissaisie DESC";

$var=true;
$resql = $db->query($sql);
if ($resql)
{
	$num = $db->num_rows($resql);
	$i = 0;
	$tasks = array();
	while ($i < $num)
	{
		$row = $db->fetch_object($resql);
		$tasks[$i] = $row;
		$i++;
	}
	$db->free($resql);
}
else
{
	dol_print_error($db);
}

print_titre($langs->trans("TimePassed"));

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td width="250">'.$langs->trans("UserName").'</td>';
print '<td width="100">'.$langs->trans("YearMonthDate").'</td>';
print '<td width="100" align="right">'.$langs->trans("Duration").'</td>';
print '<td align="right">'.$langs->trans("THMCost").'</td>';
print '<td align="right">'.$langs->trans("UnitTHM").'</td>';
print "</tr>\n";

$total = 0;
foreach ($tasks as $task_time)
{
	$var=!$var;
	print "<tr ".$bc[$var].">";

	// User
	print '<td>';
		$userstatic->id         = $task_time->fk_user;
		$userstatic->lastname	= $task_time->lastname;
		$userstatic->firstname 	= $task_time->firstname;
		print $userstatic->getNomUrl(1);
	print '</td>';

	// Date
	print '<td>';
	print $task_time->moissaisie;
	print '</td>';

	// Time spent
	print '<td align="right">';
		print convertSecondToTime($task_time->totalhrs,'allhourmin');
	print '</td>';

	// cout total thm
	print '<td align="right">';
	print price($task_time->coutthm,0,'',1,-1,2,'auto');
	print '</td>';

	//  thm moyen de la personne
	print '<td align="right">';
	print price($task_time->coutthm / ($task_time->totalhrs/3600),0,'',1,-1,2,'auto');
	print '</td>';


	print "</tr>\n";
	$total += $task_time->totalhrs;
	$totalhtm += $task_time->coutthm;
}
print '<tr class="liste_total"><td colspan="2" class="liste_total">'.$langs->trans("Total").'</td>';
print '<td align="right" class="nowrap liste_total">'.convertSecondToTime($total,'allhourmin').'</td>';
print '<td align="right" class="nowrap liste_total">'.price($totalhtm,0,'',1,-1,2,'auto').'</td>';
if ($total != 0)
	print '<td align="right" class="nowrap liste_total">'.price($totalhtm / ($total/3600),0,'',1,-1,2,'auto').'</td>';
else
	print '<td align="right" class="nowrap liste_total"></td>';
	
print '</tr>';
print "</table>";


print '<br>';

print_titre($langs->trans("TimeBilled"));

// TODO list of billed time on project

$sql = "SELECT ptb.fk_facture, ptb.task_date, u.lastname, u.firstname, ptb.fk_user, ptb.fk_user,ptb.fk_user,sum(ptb.task_duration_billed) as totalhrs";
$sql .= " FROM ".MAIN_DB_PREFIX."projet_task_billed as ptb";
$sql .= " , ".MAIN_DB_PREFIX."user as u";
$sql .= " WHERE ptb.fk_task =".$object->id;
$sql .= " AND ptb.fk_user = u.rowid";
$sql .= " GROUP BY ptb.fk_facture, ptb.task_date, u.lastname, u.firstname, ptb.fk_user";
$sql .= " ORDER BY ptb.fk_facture, ptb.task_date, u.lastname, u.firstname DESC";

$var=true;
$resql = $db->query($sql);
if ($resql)
{
	$num = $db->num_rows($resql);
	$i = 0;
	$tasksbill = array();
	while ($i < $num)
	{
		$row = $db->fetch_object($resql);
		$tasksbill[$i] = $row;
		$i++;
	}
	$db->free($resql);
}
else
{
	dol_print_error($db);
}


print '<table class="noborder" width="300px">';
print '<tr class="liste_titre">';
print '<td width="250">'.$langs->trans("Invoice").'</td>';
print '<td width="100">'.$langs->trans("Date").'</td>';
//print '<td>'.$langs->trans("UserName").'</td>';
print '<td width="100" align="right">'.$langs->trans("Duration").'</td>';
print '<td align="right"></td>';
print "</tr>\n";

$total = 0;
foreach ($tasksbill as $task_bill)
{
	$var=!$var;
	print "<tr ".$bc[$var].">";

	// Facture
	print '<td>';
		$facturestatic->fetch($task_bill->fk_facture);
		print $facturestatic->getNomUrl(1);
	print '</td>';
	
	// Date
	print '<td>';
	print dol_print_date($facturestatic->date, 'day');
	print '</td>';

	// User
//	print '<td>';
//		$userstatic->id         = $task_bill->fk_user;
//		$userstatic->lastname	= $task_bill->lastname;
//		$userstatic->firstname 	= $task_bill->firstname;
//		print $userstatic->getNomUrl(1);
//	print '</td>';


	// Time spent
	print '<td align="right">';
	print convertSecondToTime($task_bill->totalhrs,'allhourmin');
	print '</td>';
	print "</tr>\n";
	$total += $task_bill->totalhrs;
}
print '<tr class="liste_total"><td colspan="2" class="liste_total">'.$langs->trans("Total").'</td>';
print '<td align="right" class="nowrap liste_total">'.convertSecondToTime($total,'allhourmin').'</td>';
print '<td align="right"></td>';

print '</tr>';
print "</table>";

// End of page
llxFooter();
$db->close();
?>