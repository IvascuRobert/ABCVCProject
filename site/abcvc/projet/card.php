<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2016 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
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
 *	\file       htdocs/projet/card.php
 *	\ingroup    projet
 *	\brief      Project card
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/task.class.php';
//require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/modules/project/modules_project.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';


// var_dump($_POST);
// exit;

$langs->load("projects");
$langs->load('companies');

$id=GETPOST('id','int');
$ref=GETPOST('ref','alpha');
$action=GETPOST('action','alpha');
$backtopage=GETPOST('backtopage','alpha');
$cancel=GETPOST('cancel','alpha');
$status=GETPOST('status','int');
$opp_status=GETPOST('opp_status','int');
$opp_percent=price2num(GETPOST('opp_percent','alpha'));
$actionajax=GETPOST('actionajax','alpha');

//var_dump($id);
//fexit();
if ($id == '' && $ref == '' && ($action != "create" && $action != "add" && $action != "update" && ! $_POST["cancel"] && $actionajax=='' ) ) accessforbidden();

$mine = GETPOST('mode')=='mine' ? 1 : 0;
//if (! $user->rights->projet->all->lire) $mine=1;	// Special for projects

// Initialize technical object to manage hooks of thirdparties. Note that conf->hooks_modules contains array array
$hookmanager->initHooks(array('projectcard','globalcard'));

$object = new ProjectABCVC($db);
$extrafields = new ExtraFields($db);
$objtask = new TaskABCVC($db);
$vat_list = $object->get_vat();

// Load object
//include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';  // Can't use generic include because when creating a project, ref is defined and we dont want error if fetch fails from ref.
if ($id > 0 || ! empty($ref))
{
    $ret = $object->fetch($id,$ref);	// If we create project, ref may be defined into POST but record does not yet exists into database
    if ($ret > 0) {
        $object->fetch_thirdparty();
        $id=$object->id;
    }
}
// Security check
$socid=GETPOST('socid');
//if ($user->societe_id > 0) $socid = $user->societe_id;    // For external user, no check is done on company because readability is managed by public status of project and assignement.
//$result = restrictedArea($user, 'projet', $object->id,'projet&project');
//
//TODO 
//fix rights
//
// fetch optionals attributes and labels
$extralabels=$extrafields->fetch_name_optionals_label($object->table_element);

$date_start=dol_mktime(0,0,0,GETPOST('projectstartmonth','int'),GETPOST('projectstartday','int'),GETPOST('projectstartyear','int'));
$date_end=dol_mktime(0,0,0,GETPOST('projectendmonth','int'),GETPOST('projectendday','int'),GETPOST('projectendyear','int'));


//******************************************************************************************************
// 
// Actions
// 
//******************************************************************************************************


$parameters=array('id'=>$socid, 'objcanvas'=>$objcanvas);
$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{

	//----------------------------------------------------------------
	//ajax call
	//----------------------------------------------------------------
	// /abcvc/projet/card.php?actionajax=ajax_add_lot&id=x
	if($actionajax=="ajax_add_lot"){
		$label=GETPOST('label','alpha');
		$code=GETPOST('code','alpha');
		$description=GETPOST('description','');

		$id_projet=GETPOST('id_projet','int');

		$row = array(
			'id_projet'=>$id_projet,
			'label'=>$label,
			'code'=>$code,
			'description'=>$description
		);

		$message = $object->create_lot($user,$row);
		if(is_numeric($message)){
			$statut = 'ok';
		} else {
			$statut = 'ko';
		}

		$return = array(
			'statut'=>$statut,
			'message'=>$message
		);
		echo json_encode($return);
		exit;
	}

	// /abcvc/projet/card.php?actionajax=ajax_add_category&id=x
	if($actionajax=="ajax_add_category"){
		$label=GETPOST('label','alpha');
		$code=GETPOST('code','alpha');
		$lot=GETPOST('lot','int');
		$description=GETPOST('description','');
		$id_projet=GETPOST('id_projet','int');

		$row = array(
			'id_projet'=>$id_projet,
			'label'=>$label,
			'code'=>$code,
			'id_lot'=>$lot,
			'description'=>$description
		);

		$message = $object->create_category($user,$row);
		if(is_numeric($message)){
			$statut = 'ok';
		} else {
			$statut = 'ko';
		}

		$return = array(
			'statut'=>$statut,
			'message'=>$message
		);
		echo json_encode($return);
		exit;
	}

	// /abcvc/projet/card.php?actionajax=ajax_add_poste&id=x
	if($actionajax == "ajax_add_poste") {
		$id_projet = GETPOST('id_projet', 'int');
		$label = GETPOST('label','alpha');
		$code_poste = GETPOST('code_poste','alpha');
		$description = GETPOST('description','');
		$id_category = GETPOST('id_category', 'int');
		$start_date = GETPOST('start_date', 'string');
		$end_date = GETPOST('end_date', 'string');
		$plannedworkload_poste = GETPOST( 'plannedworkload_poste', 'int' );
		$declared_progress = GETPOST('declared_progress', 'int');
		if($declared_progress=='') $declared_progress = 0;
		$executive = GETPOST('executive','array');
		$contributor = GETPOST('contributor','array');
		$id_projet = GETPOST('id_projet','int');
		$id_zone = GETPOST('id_zone','int');
		$price = GETPOST('price','alpha');
		$estimated_progress = GETPOST('estimated_progress','int');
		if($estimated_progress=='') $estimated_progress = 0;
		$add_factfourn = GETPOST('add_factfourn','array');
		$cost_mo = GETPOST('cost_mo','alpha');
		$row = array(
			'id_projet' => $id_projet,
			'label' => $label,
			'code_poste' => $code_poste,
			'id_category' => $id_category,
			'description' => $description,
			'start_date' => $start_date,
            'end_date' => $end_date,
            'plannedworkload_poste' => $plannedworkload_poste,
            'declared_progress' => $declared_progress,
            'executive' => $executive,
            'contributor' => $contributor,
            'id_zone' => $id_zone,
            'price' => $price,
            'estimated_progress' => $estimated_progress,
            'add_factfourn' => $add_factfourn,
            'cost_mo' => $cost_mo
		);

		$message = $object->create_poste($user,$row);
		if(is_numeric($message)){
			$statut = 'ok';
		} else {
			$statut = 'ko';
		}

		$return = array(
			'statut'=>$statut,
			'message'=>$message
		);
		echo json_encode($return);
		exit;
	}

	// /abcvc/projet/card.php?actionajax=ajax_add_poste&id=x
	if($actionajax == "ajax_add_subposte") {

		$code = GETPOST('code','alpha');
		$label = GETPOST('label','alpha');
		$child = GETPOST('child','alpha');
		$start_date = GETPOST('start_date','alpha');
		$end_date = GETPOST('end_date','alpha');
		$planned_work_h = GETPOST('planned_work_h','alpha');
		$planned_work_m = GETPOST('planned_work_m','alpha');
		$declared_progress = GETPOST('declared_progress','int');
		if($declared_progress=='') $declared_progress = 0;
		$description = GETPOST('description','');
		$id_projet=GETPOST('id_projet','int');
		$executive = GETPOST('executive','alpha');
		$contributor = GETPOST('contributor','alpha');
		$price = GETPOST('price','alpha');
		$sousposte_add_unite = GETPOST('sousposte_add_unite','alpha');
		$sousposte_select_unite = GETPOST('sousposte_select_unite','alpha');
		
		$estimated_progress = GETPOST('estimated_progress','int');
		if($estimated_progress=='') $estimated_progress = 0;
		
		$id_zone = GETPOST('id_zone','int');
		$add_factfourn = GETPOST ('add_factfourn','array');

		$row = array(
			'code'=>$code,
			'label'=>$label,
			'child'=>$child,
			'start_date'=>$start_date,
			'end_date'=>$end_date,
			'planned_workload'=>0,//$planned_work_h*3600+$planned_work_m*60,
			'declared_progress'=>$declared_progress,
			'description'=>$description,
			'id_projet'=>$id_projet,
			'executive' => $executive,
            'contributor' => $contributor,
            'price' => $price,
            'estimated_progress' => $estimated_progress,
            'id_zone' => $id_zone,
            'add_factfourn' => $add_factfourn,
            'sousposte_add_unite' => $sousposte_add_unite,
            'sousposte_select_unite' => $sousposte_select_unite
		);
		
		// var_dump($row);
		// exit();

		$message = $object->create_subposte($user,$row);
		if(is_numeric($message)){
			$statut = 'ok';
		} else {
			$statut = 'ko';
		}

		$return = array(
			'statut'=>$statut,
			'message'=>$message
		);
		echo json_encode($return);
		exit;
	}

	// /abcvc/projet/card.php?actionajax=ajax_add_sub_sub_poste&id=x
	if($actionajax == "ajax_add_subsubposte") {

		$code = GETPOST('code','alpha');
		$label = GETPOST('label','alpha');
		$child = GETPOST('child','alpha');
		$start_date = GETPOST('start_date','alpha');
		$end_date = GETPOST('end_date','alpha');
		$planned_work_h = GETPOST('planned_work_h','alpha');
		$planned_work_m = GETPOST('planned_work_m','alpha');
		$declared_progress = GETPOST('declared_progress','int');
		if($declared_progress=='') $declared_progress = 0;
		$description = GETPOST('description','');
		$id_projet=GETPOST('id_projet','int');
		$executive = GETPOST('executive','alpha');
		$contributor = GETPOST('contributor','alpha');
		$price = GETPOST('price','alpha');
		$estimated_progress = GETPOST('estimated_progress','int');
		if($estimated_progress=='') $estimated_progress = 0;
		$id_zone = GETPOST('id_zone','int');
		$add_factfourn = GETPOST ('add_factfourn','array');
		$soussousposte_add_unite = GETPOST('soussousposte_add_unite','alpha');
		$soussousposte_select_unite = GETPOST('soussousposte_select_unite','alpha');

		$row = array(
			'code'=>$code,
			'label'=>$label,
			'child'=>$child,
			'start_date'=>$start_date,
			'end_date'=>$end_date,
			'planned_workload'=>0,//$planned_work_h*3600+$planned_work_m*60,
			'declared_progress'=>$declared_progress,
			'description'=>$description,
			'id_projet'=>$id_projet,
			'executive' => $executive,
            'contributor' => $contributor,
            'price' => $price,
            'estimated_progress' => $estimated_progress,
            'id_zone' => $id_zone,
            'add_factfourn' => $add_factfourn,
            'soussousposte_add_unite' => $soussousposte_add_unite,
            'soussousposte_select_unite' => $soussousposte_select_unite
		);
		
		//var_dump($row);
		//exit();

		$message = $object->create_subsubposte($user,$row);
		if(is_numeric($message)){
			$statut = 'ok';
		} else {
			$statut = 'ko';
		}

		$return = array(
			'statut'=>$statut,
			'message'=>$message
		);
		echo json_encode($return);
		exit;
	}


	// /abcvc/projet/card.php?actionajax=ajax_edit_lot&id=x
	if($actionajax=="ajax_edit_lot"){
		//var_dump($_POST);
		$label=GETPOST('label','alpha');
		$ref=GETPOST('ref','alpha');
		$description=GETPOST('description','');
		$id_projet=GETPOST('id_projet','int');
		$id_lot=GETPOST('id_lot','int');
		
		$row = array(
			'label'=>$label,			
			'ref'=>$ref,
			'description'=>$description,
			'id_projet'=>$id_projet,
			'id'=>$id_lot
		);
		//var_dump($row);
		//exit();
		
		$message = $object->update_lot($user,$row);
		if(is_numeric($message)){
			$statut = 'ok';
		} else {
			$statut = 'ko';
		}

		$return = array(
			'statut'=>$statut,
			'message'=>$message
		);
		echo json_encode($return);
		exit;
	}

	// /abcvc/projet/card.php?actionajax=ajax_edit_lot&id=x	
	if($actionajax=="ajax_edit_category"){

		$label=GETPOST('label','alpha');
		$ref=GETPOST('ref','alpha');
		$description=GETPOST('description','');
		$id_projet=GETPOST('id_projet','int');
		$id_lot=GETPOST('id_lot','int');
		
		$row = array(
			'label'=>$label,			
			'ref'=>$ref,
			'description'=>$description,
			'id_projet'=>$id_projet,
			'id'=>$id_lot
		);
		
		$message = $object->update_category($user,$row);
		if(is_numeric($message)){
			$statut = 'ok';
		} else {
			$statut = 'ko';
		}

		$return = array(
			'statut'=>$statut,
			'message'=>$message
		);
		echo json_encode($return);
		exit;
	}

	// /abcvc/projet/card.php?actionajax=ajax_edit_poste&id=x
	if($actionajax == "ajax_edit_poste") {
		$id_projet = GETPOST('id_projet', 'int');
		$id_poste = GETPOST('id_poste', 'int');
		$label = GETPOST('label','alpha');
		$code_poste = GETPOST('code_poste','alpha');
		$description = GETPOST('description','');
		$id_category = GETPOST('id_category', 'int');
		$start_date = GETPOST('start_date', 'string');
		$end_date = GETPOST('end_date', 'string');
		$planned_work_h = GETPOST( 'planned_work_h', 'int' );
		$declared_progress = GETPOST('declared_progress', 'int');
		if($declared_progress=='') $declared_progress = 0;

		$id_poste = GETPOST('id_poste','int');
		$contacts_executive = GETPOST('contacts_executive','array');
		$contacts_contributor = GETPOST('contacts_contributor','array');
		$contacts_executive_todelete = GETPOST('contacts_executive_todelete','array');
		$contacts_contributor_todelete = GETPOST('contacts_contributor_todelete','array');
		$progress_estimated = GETPOST('progress_estimated','int');
		if($progress_estimated=='') $progress_estimated = 0;

		$zone = GETPOST('zone','int');
		$poste_price = GETPOST('poste_price','alpha');
		$poste_tx_tva = GETPOST('tx_tva', 'int');
		$factfourn = GETPOST('factfourn','array');
		$factfourn_todelete = GETPOST('factfourn_todelete','array');

		$poste_pv = GETPOST('poste_pv','alpha');

		$row = array(
			'id_projet' => $id_projet,
			'id_poste' => $id_poste,
			'label' => $label,
			'code_poste' => $code_poste,
			'id_category' => $id_category,
			'description' => $description,
			'start_date' => $start_date,
            'end_date' => $end_date,
            'planned_work_h' => $planned_work_h,
            'declared_progress' => $declared_progress,
            'contacts_executive' => $contacts_executive,
            'contacts_contributor' => $contacts_contributor,
            'contacts_executive_todelete' => $contacts_executive_todelete,
            'contacts_contributor_todelete' => $contacts_contributor_todelete,
            'progress_estimated' => $progress_estimated,
            'zone' => $zone,
            'poste_price' => $poste_price,
            'tx_tva' => $poste_tx_tva,
            'factfourn' => $factfourn,
            'factfourn_todelete' => $factfourn_todelete,
            'poste_pv' => $poste_pv
		);

		$message = $object->update_poste($user,$row);
		if(is_numeric($message)){
			$statut = 'ok';
		} else {
			$statut = 'ko';
		}

		$return = array(
			'statut'=>$statut,
			'message'=>$message
		);
		echo json_encode($return);
		exit;
	}

	// /abcvc/projet/card.php?actionajax=ajax_add_poste&id=x
	// 
	if($actionajax == "ajax_edit_subposte") {

		$code = GETPOST('code','alpha');
		$label = GETPOST('label','alpha');
		$child = GETPOST('child','alpha');
		$start_date = GETPOST('start_date','alpha');
		$end_date = GETPOST('end_date','alpha');
		$planned_work_h = GETPOST('planned_work_h','alpha');
		$planned_work_m = GETPOST('planned_work_m','alpha');
		$declared_progress = GETPOST('declared_progress','int');
		if($declared_progress=='') $declared_progress = 0;
		$description = GETPOST('description','');
		$id_projet=GETPOST('id_projet','int');
		$id_subposte=GETPOST('rowid','int');
		$contacts_executive = GETPOST('contacts_executive','array');
		$contacts_contributor = GETPOST('contacts_contributor','array');
		$contacts_executive_todelete = GETPOST('contacts_executive_todelete','array');
		$contacts_contributor_todelete = GETPOST('contacts_contributor_todelete','array');
		$progress_estimated = GETPOST('progress_estimated','int');
		if($progress_estimated=='') $progress_estimated = 0;
		$subposte_price = GETPOST('subposte_price','alpha');
		$factfourn = GETPOST('factfourn','array');
		$factfourn_todelete = GETPOST('factfourn_todelete','array');
		$poste_pv = GETPOST('poste_pv','alpha');
		$sousposte_edit_select_unite = GETPOST('sousposte_edit_select_unite','alpha');
		$sousposte_edit_unite = GETPOST('sousposte_edit_unite','alpha');

		$row = array(
			'code'=>$code,
			'label'=>$label,
			'child'=>$child,
			'start_date'=>$start_date,
			'end_date'=>$end_date,
			'planned_workload'=>$planned_work_h*3600+$planned_work_m*60,
			'declared_progress'=>$declared_progress,
			'description'=>$description,
			'id_projet'=>$id_projet,
			'rowid'=>$id_subposte,
			'contacts_executive'=>$contacts_executive,
			'contacts_contributor'=>$contacts_contributor,
			'contacts_executive_todelete' => $contacts_executive_todelete,
            'contacts_contributor_todelete' => $contacts_contributor_todelete,
            'progress_estimated' => $progress_estimated,
            'subposte_price' => $subposte_price,
            'factfourn' => $factfourn,
            'factfourn_todelete' => $factfourn_todelete,
            'poste_pv' => $poste_pv,
            'sousposte_edit_select_unite' => $sousposte_edit_select_unite,
            'sousposte_edit_unite' => $sousposte_edit_unite
		);
		$message = $object->update_subposte($user,$row);
		if(is_numeric($message)){
			$statut = 'ok';
		} else {
			$statut = 'ko';
		}

		$return = array(
			'statut'=>$statut,
			'message'=>$message
		);
		echo json_encode($return);
		exit;
	}

	// /abcvc/projet/card.php?actionajax=ajax_add_poste&id=x
	// 
	if($actionajax == "ajax_edit_subsubposte") {

		$code = GETPOST('code','alpha');
		$label = GETPOST('label','alpha');
		$child = GETPOST('child','alpha');
		$start_date = GETPOST('start_date','alpha');
		$end_date = GETPOST('end_date','alpha');
		$planned_work_h = GETPOST('planned_work_h','alpha');
		$planned_work_m = GETPOST('planned_work_m','alpha');
		$declared_progress = GETPOST('declared_progress','int');
		if($declared_progress=='') $declared_progress = 0;
		$description = GETPOST('description','');
		$id_projet=GETPOST('id_projet','int');
		$id_subsubposte=GETPOST('rowid','int');
		$contacts_executive = GETPOST('contacts_executive','array');
		$contacts_contributor = GETPOST('contacts_contributor','array');
		$contacts_executive_todelete = GETPOST('contacts_executive_todelete','array');
		$contacts_contributor_todelete = GETPOST('contacts_contributor_todelete','array');
		$progress_estimated = GETPOST('progress_estimated','int');
		if($progress_estimated=='') $progress_estimated = 0;
		$subsubposte_price = GETPOST('subsubposte_price','alpha');
		$factfourn = GETPOST('factfourn','array');
		$factfourn_todelete = GETPOST('factfourn_todelete','array');
		$poste_pv = GETPOST('poste_pv','alpha');
		$soussousposte_edit_select_unite = GETPOST('soussousposte_edit_select_unite','alpha');
		$soussousposte_edit_unite = GETPOST('soussousposte_edit_unite','alpha');

		$row = array(
			'code'=>$code,
			'label'=>$label,
			'child'=>$child,
			'start_date'=>$start_date,
			'end_date'=>$end_date,
			'planned_workload'=>$planned_work_h*3600+$planned_work_m*60,
			'declared_progress'=>$declared_progress,
			'description'=>$description,
			'id_projet'=>$id_projet,
			'rowid'=>$id_subsubposte,
			'contacts_executive'=>$contacts_executive,
			'contacts_contributor'=>$contacts_contributor,
			'contacts_executive_todelete' => $contacts_executive_todelete,
            'contacts_contributor_todelete' => $contacts_contributor_todelete,
            'progress_estimated' => $progress_estimated,
            'subsubposte_price' => $subsubposte_price,
            'factfourn' => $factfourn,
            'factfourn_todelete' => $factfourn_todelete,
            'poste_pv' => $poste_pv,
            'soussousposte_edit_select_unite' => $soussousposte_edit_select_unite,
            'soussousposte_edit_unite' => $soussousposte_edit_unite
		);
		
		$message = $object->update_subsubposte($user,$row);
		if(is_numeric($message)){
			$statut = 'ok';
		} else {
			$statut = 'ko';
		}

		$return = array(
			'statut'=>$statut,
			'message'=>$message
		);
		echo json_encode($return);
		exit;
	}



	// /abcvc/projet/card.php?actionajax=ajax_edit_lot&id=x
	if($actionajax=="ajax_delete_lot"){
		$id_projet=GETPOST('id_projet','int');
		$id_lot=GETPOST('id_lot','int');
		
		$row = array(
			'id_projet'=>$id_projet,
			'id_lot'=>$id_lot
		);
		
		$message = $object->delete_lot($user,$row);
		if(is_numeric($message)){
			$statut = 'ok';
		} else {
			$statut = 'ko';
		}

		$return = array(
			'statut'=>$statut,
			'message'=>$message
		);
		echo json_encode($return);
		exit;
	}

	if($actionajax=="ajax_delete_category"){
		$id_projet=GETPOST('id_projet','int');
		$id_category=GETPOST('id_category','int');
		
		$row = array(
			'id_projet'=>$id_projet,
			'id_category'=>$id_category
		);
		
		$message = $object->delete_category($user,$row);
		if(is_numeric($message)){
			$statut = 'ok';
		} else {
			$statut = 'ko';
		}

		$return = array(
			'statut'=>$statut,
			'message'=>$message
		);
		echo json_encode($return);
		exit;
	}

	if($actionajax=="ajax_delete_poste"){
		$id_projet=GETPOST('id_projet','int');
		$id_poste=GETPOST('id_poste','int');
		
		$row = array(
			'id_projet'=>$id_projet,
			'id_poste'=>$id_poste
		);
		//var_dump($row);
		//exit();
		
		$message = $object->delete_poste($user,$row);
		if(is_numeric($message)){
			$statut = 'ok';
		} else {
			$statut = 'ko';
		}

		$return = array(
			'statut'=>$statut,
			'message'=>$message
		);
		echo json_encode($return);
		exit;
	}

	if($actionajax=="ajax_delete_subposte"){
		$id_projet=GETPOST('id_projet','int');
		$id_subposte=GETPOST('id_subposte','int');
		
		$row = array(
			'id_projet'=>$id_projet,
			'id_subposte'=>$id_subposte
		);
		//var_dump($row);
		//exit();
		
		$message = $object->delete_subposte($user,$row);
		if(is_numeric($message)){
			$statut = 'ok';
		} else {
			$statut = 'ko';
		}

		$return = array(
			'statut'=>$statut,
			'message'=>$message
		);
		echo json_encode($return);
		exit;
	}

	if($actionajax=="ajax_delete_subsubposte"){
		$id_projet=GETPOST('id_projet','int');
		$id_subsubposte=GETPOST('id_subsubposte','int');
		
		$row = array(
			'id_projet'=>$id_projet,
			'id_subsubposte'=>$id_subsubposte
		);
		//var_dump($row);
		//exit();
		
		$message = $object->delete_subsubposte($user,$row);
		if(is_numeric($message)){
			$statut = 'ok';
		} else {
			$statut = 'ko';
		}

		$return = array(
			'statut'=>$statut,
			'message'=>$message
		);
		echo json_encode($return);
		exit;
	}



	// Cancel
	if ($cancel)
	{
		if (GETPOST("comefromclone")==1)
		{
		    $result=$object->delete($user);
		    if ($result > 0)
		    {
		        header("Location: index.php");
		        exit;
		    }
		    else
		    {
		        dol_syslog($object->error,LOG_DEBUG);
			    setEventMessages($langs->trans("CantRemoveProject"), null, 'errors');
		    }
		}
		if ($backtopage)
		{
	    	header("Location: ".$backtopage);
	    	exit;
		}

		$action = '';
	}

	if ($action == 'add' && $user->rights->projet->creer)
	{

		//var_dump($_POST);
		//exit();

	    $error=0;
	    if (empty($_POST["ref"]))
	    {
		    setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("Ref")), null, 'errors');
	        $error++;
	    }
	    if (empty($_POST["title"]))
	    {
		    setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("Label")), null, 'errors');
	        $error++;
	    }

	    if (GETPOST('opp_amount') != '' && ! (GETPOST('opp_status') > 0))
	    {
	        $error++;
	        setEventMessages($langs->trans("ErrorOppStatusRequiredIfAmount"), null, 'errors');
	    }
	    
	    if (! $error)
	    {
	        $error=0;

	        $db->begin();

	        $object->ref             = GETPOST('ref','alpha');
	        $object->title           = GETPOST('title'); // Do not use 'alpha' here, we want field as it is
	        $object->socid           = GETPOST('socid','int');
	        $object->description     = GETPOST('description'); // Do not use 'alpha' here, we want field as it is
	        $object->public          = GETPOST('public','alpha');
	        $object->opp_amount      = price2num(GETPOST('opp_amount'));
	        $object->budget_amount   = price2num(GETPOST('budget_amount'));
	        $object->datec=dol_now();
	        $object->date_start=$date_start;
	        $object->date_end=$date_end;
	        $object->statuts         = $status;
	        $object->opp_status      = $opp_status;
	        $object->opp_percent     = $opp_percent; 
	        $object->fk_zones     = GETPOST('id_zone','int');
	        $object->address     = GETPOST('id_address','alpha');
	        $object->postal_code     = GETPOST('id_postalcode','alpha');
	        $object->city     = GETPOST('id_city','alpha');
	        $object->chargesfixe     = price2num(GETPOST('id_chargesfixe'));

	        // Fill array 'array_options' with data from add form
	        $ret = $extrafields->setOptionalsFromPost($extralabels,$object);
			if ($ret < 0) $error++;

	        $result = $object->create($user);
	        if (! $error && $result > 0)
	        {
	            // Add myself as project leader
	            $result = $object->add_contact($user->id, 'PROJECTLEADER', 'internal');
	            if ($result < 0)
	            {
	                $langs->load("errors");
		            setEventMessages($langs->trans($object->error), null, 'errors');
	                $error++;
	            }
	        }
	        else
	        {
	            $langs->load("errors");
		        setEventMessages($langs->trans($object->error), null, 'errors');
	            $error++;
	        }
	        if (! $error && !empty($object->id) > 0)
	        {
	        	// Category association
	        	$categories = GETPOST('categories');
	        	$result=$object->setCategories($categories);
	        	if ($result<0) {
		        	$langs->load("errors");
		        	setEventMessages($object->error, $object->errors, 'errors');
		        	$error++;
	        	}
	        }

	        if (! $error)
	        {
	            $db->commit();

        		if ($backtopage)
				{
			    	header("Location: ".$backtopage.'&projectid='.$object->id);
			    	exit;
				}
				else
				{
	            	//header("Location:card.php?id=".$object->id);
	            	header("Location:list.php");
	            	exit;
				}
	        }
	        else
	        {
	            $db->rollback();

	            $action = 'create';
	        }
	    }
	    else
	    {
	        $action = 'create';
	    }
	}

	if ($action == 'update' && ! $_POST["cancel"] && $user->rights->projet->creer)
	{
	    $error=0;

	    if (empty($ref))
	    {
	        $error++;
	        //$_GET["id"]=$_POST["id"]; // We return on the project card
		    setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("Ref")), null, 'errors');
	    }
	    if (empty($_POST["title"]))
	    {
	        $error++;
	        //$_GET["id"]=$_POST["id"]; // We return on the project card
		    setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("Label")), null, 'errors');
	    }

	    $db->begin();

	    if (! $error)
	    {
			$object->oldcopy = clone $object;

			$old_start_date = $object->date_start;

	        $object->ref          = GETPOST('ref','alpha');
	        $object->title        = GETPOST('title'); // Do not use 'alpha' here, we want field as it is
	        $object->socid        = GETPOST('socid','int');
	        $object->description  = GETPOST('description');	// Do not use 'alpha' here, we want field as it is
	        $object->public       = GETPOST('public','alpha');
	        $object->date_start   = empty($_POST["projectstart"])?'':$date_start;
	        $object->date_end     = empty($_POST["projectend"])?'':$date_end;
	        if (isset($_POST['opp_amount']))    $object->opp_amount   = price2num(GETPOST('opp_amount'));
	        if (isset($_POST['budget_amount'])) $object->budget_amount= price2num(GETPOST('budget_amount'));
	        if (isset($_POST['opp_status']))    $object->opp_status   = $opp_status;
	        if (isset($_POST['opp_percent']))   $object->opp_percent  = $opp_percent;
	        $object->fk_zones     = GETPOST('id_zone','int');
	        $object->address      = GETPOST('id_address','alpha');
	        $object->postal_code  = GETPOST('id_postalcode','alpha');
	        $object->city         = GETPOST('id_city','alpha');
	        $object->chargesfixe  = price2num(GETPOST('id_chargesfixe'));

	        // Fill array 'array_options' with data from add form
	        $ret = $extrafields->setOptionalsFromPost($extralabels,$object);
			if ($ret < 0) $error++;
	    }

		if ($object->opp_amount && ($object->opp_status <= 0))
	    {
	       	$error++;
	    	setEventMessages($langs->trans("ErrorOppStatusRequiredIfAmount"), null, 'errors');
	    }

	    if (! $error)
	    {
	    	$result=$object->update($user);
	    	if ($result < 0)
	    	{
	    	    $error++;
	    	    if ($result == -4) setEventMessages($langs->trans("ErrorRefAlreadyExists"), null, 'errors');
		        else setEventMessages($object->error, $object->errors, 'errors');
	    	}else {
	    		// Category association
	    		$categories = GETPOST('categories');
	    		$result=$object->setCategories($categories);
	    		if ($result < 0)
	    		{
	    			$error++;
	    			setEventMessages($object->error, $object->errors, 'errors');
	    		}
	    	}
	    }

	    if (! $error)
	    {
	    	if (GETPOST("reportdate") && ($object->date_start!=$old_start_date))
	    	{
	    		$result=$object->shiftTaskDate($old_start_date);
	    		if ($result < 0)
	    		{
	    			$error++;
				    setEventMessages($langs->trans("ErrorShiftTaskDate").':'.$object->error, $object->errors, 'errors');
	    		}
	    	}
	    }

		// Check if we must change status
	    if (GETPOST('closeproject'))
	    {
	        $resclose = $object->setClose($user);
	        if ($resclose < 0)
	        {
	            $error++;
			    setEventMessages($langs->trans("FailedToCloseProject").':'.$object->error, $object->errors, 'errors');
	        }
	    }
	    
	    
	    if ($error)
	    {
			$db->rollback();
	    	$action='edit';
	    }
	    else
		{
	    	$db->commit();

			if (GETPOST('socid','int') > 0) $object->fetch_thirdparty(GETPOST('socid','int'));
			else unset($object->thirdparty);
	    }
	    
	}

	// Build doc
	if ($action == 'builddoc' && $user->rights->projet->creer)
	{
		// Save last template used to generate document
		if (GETPOST('model')) $object->setDocModel($user, GETPOST('model','alpha'));

	    $outputlangs = $langs;
	    if (GETPOST('lang_id'))
	    {
	        $outputlangs = new Translate("",$conf);
	        $outputlangs->setDefaultLang(GETPOST('lang_id'));
	    }
	    $result= $object->generateDocument($object->modelpdf, $outputlangs);
	    if ($result <= 0)
	    {
	        setEventMessages($object->error, $object->errors, 'errors');
	        $action='';
	    }
	}

	// Delete file in doc form
	if ($action == 'remove_file' && $user->rights->projet->creer)
	{
	    if ($object->id > 0)
	    {
			require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

			$langs->load("other");
			$upload_dir = $conf->projet->dir_output;
			$file = $upload_dir . '/' . GETPOST('file');
			$ret = dol_delete_file($file, 0, 0, 0, $object);
			if ($ret)
				setEventMessages($langs->trans("FileWasRemoved", GETPOST('file')), null, 'mesgs');
			else
				setEventMessages($langs->trans("ErrorFailToDeleteFile", GETPOST('file')), null, 'errors');
			$action = '';
	    }
	}


	if ($action == 'confirm_validate' && GETPOST('confirm') == 'yes')
	{
	    $result = $object->setValid($user);
	    if ($result <= 0)
	    {
	        setEventMessages($object->error, $object->errors, 'errors');
	    }
	}

	if ($action == 'confirm_close' && GETPOST('confirm') == 'yes')
	{
	    $result = $object->setClose($user);
	    if ($result <= 0)
	    {
	        setEventMessages($object->error, $object->errors, 'errors');
	    }
	}

	if ($action == 'confirm_reopen' && GETPOST('confirm') == 'yes')
	{
	    $result = $object->setValid($user);
	    if ($result <= 0)
	    {
	        setEventMessages($object->error, $object->errors, 'errors');
	    }
	}

	if ($action == 'confirm_delete' && GETPOST("confirm") == "yes" && $user->rights->projet->Șterge)
	{
	    $object->fetch($id);
	    $result=$object->delete($user);
	    if ($result > 0)
	    {
	        setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
	    	header("Location: index.php");
	        exit;
	    }
	    else
	    {
	        dol_syslog($object->error,LOG_DEBUG);
	        setEventMessages($object->error, $object->errors, 'errors');
	    }
	}

	if ($action == 'confirm_clone' && $user->rights->projet->creer && GETPOST('confirm') == 'yes')
	{
	    $clone_contacts=GETPOST('clone_contacts')?1:0;
	    $clone_tasks=GETPOST('clone_tasks')?1:0;
		$clone_project_files = GETPOST('clone_project_files') ? 1 : 0;
		$clone_task_files = GETPOST('clone_task_files') ? 1 : 0;
	    $clone_notes=GETPOST('clone_notes')?1:0;
	    $move_date=GETPOST('move_date')?1:0;
	    $clone_thirdparty=GETPOST('socid','int')?GETPOST('socid','int'):0;

	    $result=$object->createFromClone($object->id,$clone_contacts,$clone_tasks,$clone_project_files,$clone_task_files,$clone_notes,$move_date,0,$clone_thirdparty);
	    if ($result <= 0)
	    {
	        setEventMessages($object->error, $object->errors, 'errors');
	    }
	    else
	    {
	        // Load new object
	    	$newobject=new Project($db);
	    	$newobject->fetch($result);
	    	$newobject->fetch_optionals();
	    	$newobject->fetch_thirdparty();	// Load new object
	    	$object=$newobject;
	    	$action='edit';
	    	$comefromclone=true;
	    }
	}
}






//******************************************************************************************************
// 
// VIEWS
// 
//******************************************************************************************************

$form = new Form($db);
$formfile = new FormFile($db);
$formproject = new FormProjets($db);
$userstatic = new User($db);
$title=$langs->trans("Project").' - '.$object->ref.($object->thirdparty->name?' - '.$object->thirdparty->name:'').($object->title?' - '.$object->title:'');
if (! empty($conf->global->MAIN_HTML_TITLE) && preg_match('/projectnameonly/',$conf->global->MAIN_HTML_TITLE)) $title=$object->ref.($object->thirdparty->name?' - '.$object->thirdparty->name:'').($object->title?' - '.$object->title:'');
$help_url="EN:Module_Projects|FR:Module_Projets|ES:M&oacute;dulo_Proyectos";

llxHeader("",$title,$help_url);

// var static id_projet use by contextual JS
?>
	<script type="text/javascript" charset="utf-8">
		
		var id_projet = '<?php echo $id;?>';

	</script>
<?php

// BOOTSTRAP 3 + css + js custom
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/abcvc_js_css.php';

/*
ABCVC HEADER
*/
echo $object->getABCVCHeader($object->id, 'card');

//FUNCTION TO GET ALL SITES 
$allsites = $objtask->getAllsites(); 
//FUNCTION TO GET ALL ZONES
$allzones = $objtask->getAllzones();

if ($action == 'create' && $user->rights->projet->creer)
{
    /* *****************************************************************************************************************************************
     *
     * Create
     * 
    ***************************************************************************************************************************************** */
	$thirdparty=new Societe($db);
	if ($socid > 0) $thirdparty->fetch($socid);
	?>    
	<style type="text/css" media="screen">
		td div.titre {
			color: #444;
		}	
	</style>
	<form class="form-horizontal" action="<?php echo $_SERVER["PHP_SELF"];?>" method="POST">
		<div class="panel panel-info">
			<!-- Default panel contents -->
			<div class="panel-heading">
				<h3 class="panel-title">
					Proiect nou
					<div class="pull-right">
						<input type="submit" class="btn btn-primary btn-link btn-lg" value="<?php echo $langs->trans("Crează");?>">
						<?php
							if (! empty($backtopage)) {
								print ' &nbsp; &nbsp; ';
								print '<input type="submit" class="btn btn-link btn-lg" name="cancel" value="'.$langs->trans("Cancel").'">';
							}
							else {
								print ' &nbsp; &nbsp; ';
								print '<input type="button" class="btn btn-link btn-lg" value="' . $langs->trans("Cancel") . '" onClick="javascript:history.go(-1)">';
							}
						?>
					</div>
				</h3>
			</div>
			<div class="panel-body">
				<p>CEVA DESCIRERE</p>
			</div>
			<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken'];?>">
			<input type="hidden" name="action" value="add">
			<input type="hidden" name="backtopage" value="<?php echo $backtopage;?>">
			<div class="container-fluid abcvc_view">
				<div class="row">
					<div class="col-md-6 col-xs-12">
						<div class="form-group">
							<label for="labelEdit" class="col-sm-2 control-label">	
								<?php echo $langs->trans("Label");?>
							</label>
							<div class="col-sm-10">
								<input type="text" name="title" value="<?php echo GETPOST("title");?>" class="form-control">
							</div>
						</div>
						<div class="form-group">    
							<label for="refEdit" class="col-sm-2 control-label">
								<?php echo $langs->trans("Ref");?>
							</label>
							<div class="col-sm-10">
							<?php
								$defaultref='';
								//$modele = empty($conf->global->PROJECT_ADDON)?'mod_projectAbcvc_simple':$conf->global->PROJECT_ADDON;
								$modele = 'mod_projectAbcvc_simple';
								// Search template files
								$file=''; $classname=''; $filefound=0;
								$dirmodels=array_merge(array('/'),(array) $conf->modules_parts['models']);
								//var_dump($dirmodels);
								foreach($dirmodels as $reldir) {
									$file=dol_buildpath($reldir."core/modules/projectAbcvc/".$modele.'.php',0);
									//var_dump($reldir."core/modules/projectAbcvc/".$modele.'.php');
									if (file_exists($file)) {
										$filefound=1;
										$classname = $modele;
										break;
									}
								}
								//var_dump($filefound);
								//exit;
								if ($filefound) {
									$result = dol_include_once($reldir."core/modules/projectAbcvc/".$modele.'.php');
									$modProject = new $classname;
									//var_dump($classname);
									//exit;
									$defaultref = $modProject->getNextValue($thirdparty,$object);
								}
								if (is_numeric($defaultref) && $defaultref <= 0) $defaultref='';
								// Ref
								$suggestedref=($_POST["ref"]?$_POST["ref"]:$defaultref);
								//$suggestedref=$object->ref;
								?>	
								<input size="20" name="ref" value="<?php echo $suggestedref;?>" class="form-kcontrol readonly" readonly>
								<?php 
									echo $form->textwithpicto('', $langs->trans("YouCanCompleteRef", $suggestedref)); 
								?>
							</div>	
						</div>
						<?php
						// Thirdparty
						if ($conf->societe->enabled) { ?>
							<div class="form-group">
								<label for="thirdCreate" class="col-sm-2 control-label">
									<?php echo $langs->trans("ThirdParty");?>
								</label>
								<div class="col-sm-10">
									<?php
									// function select_thirdparty_list($selected='',$htmlname='socid',$filter='',$showempty='', $showtype=0, $forcecombo=0, $events=array(), $filterkey='', $outputmode=0, $limit=0, $morecss='minwidth100', $moreparam='')
									$filteronlist='s.client in (1,3)'; // TO SEE client CUSTOMERS 
									if (! empty($conf->global->PROJECT_FILTER_FOR_THIRDPARTY_LIST)) $filteronlist=$conf->global->PROJECT_FILTER_FOR_THIRDPARTY_LIST;
									$text=$form->select_thirdparty_list(GETPOST('socid','int'), 'socid', $filteronlist, 'None', 1, 0, array(), '', 0, 0, 'minwidth300');
									if (empty($conf->global->PROJECT_CAN_ALWAYS_LINK_TO_ALL_SUPPLIERS) && empty($conf->dol_use_jmobile)) {
										$texthelp=$langs->trans("IfNeedToUseOhterObjectKeepEmpty");
										echo $form->textwithtooltip($text.' '.img_help(), $texthelp, 1, 0, '', '', 2);
									} else {
										echo $text; ?>
											<small id="">
												<a  href="<?php echo DOL_URL_ROOT.'/societe/soc.php?action=create&backtopage='.urlencode($_SERVER["PHP_SELF"].'?action=create');?>">
													<?php echo $langs->trans("AddThirdParty"); ?>
												</a>
											</small>
									<?php
									}
									?>
								</div>
							</div>
						<?php   	
						}
						?>

						<?php  if ($status != '') : ?>
						<div class="form-group">
							<label class="col-sm-2 control-label">
								<?php echo $langs->trans("Status");?>
							</label>
							<div class="col-sm-10">
								<input type="hidden" class="form-control" id="statusCreate" name="status" value="<?php echo $status;?>" >
								<?php echo $object->LibStatut($status, 4);?>		        					
							</div>	
						</div>
						<?php  endif; ?>

						<div class="form-group">
							<label for="id_address" class="col-sm-2 control-label">
								<?php  echo $langs->trans("Address");?>
							</label>
							<div class="col-sm-10">
								<input type="text" name="id_address" class="form-control">
							</div>
						</div>

						<div class="form-group">
							<label for="id_postalcode" class="col-sm-2 control-label">
								<?php  echo $langs->trans("Cod postal");?>
							</label>
							<div class="col-sm-10">
								<input type="text" name="id_postalcode" class="form-control">
							</div>
						</div>

						<div class="form-group">
							<label for="id_city" class="col-sm-2 control-label">
								<?php  echo $langs->trans("Oraș");?>
							</label>
							<div class="col-sm-10">
								<input type="text" name="id_city" class="form-control">
							</div>
						</div>

					</div>
					
					<div class="col-md-6 col-xs-12">	

						<div class="form-group">
							<label for="descEdit" class="col-sm-2 control-label">
								<?php echo $langs->trans("Description");?>
							</label>
							<div class="col-sm-10">
								<textarea name="description" wrap="soft" rows="8" class="form-control"><?php echo GETPOST("description");?></textarea>						    		
							</div>	
						</div>

						<div class="form-group">
							<label for="zones" class="col-sm-2 control-label">
								<?php echo $langs->trans("Șantier");?>
							</label>
							<div class="col-sm-10">
								<select name="id_zone">
									<?php foreach ($allzones as $zone): ?>
										<option value="<?php echo $zone->rowid; ?>"><?php echo $zone->label.' ('.$zone->kilometers.')'; ?></option>
									<?php endforeach; ?>
								</select> 
							</div>
						</div>


						<div class="form-group">
							<label for="zones" class="col-sm-2 control-label">Cheltuieli fixe</label>
							<div class="col-sm-10">
								<div class="input-group"> 
									<span class="input-group-addon">€</span>
									<input type="text" name="id_chargesfixe" class="form-control currency"> 
								</div>	
							</div>
						</div>
					</div>
				</div>

			</div>	
		</div>
	</form>
	<?php
	// Change probability from status
	print '<script type="text/javascript" language="javascript">
		jQuery(document).ready(function() {
			function change_percent()
			{
				var element = jQuery("#opp_status option:selected");
				var defaultpercent = element.attr("defaultpercent");
				/*if (jQuery("#opp_percent_not_set").val() == "") */
				jQuery("#opp_percent").val(defaultpercent);
			}
			/*init_myfunc();*/
			jQuery("#opp_status").change(function() {
				change_percent();
			});
		});
		</script>';
}
elseif ($object->id > 0) 
{

/* ******************************************************************************************************************************
 * Show or edit
 ****************************************************************************************************************************** */
    
	    $res=$object->fetch_optionals($object->id,$extralabels);

	    // To verify role of users
	    $userAccess = $object->restrictedProjectArea($user,'read');
	    $userWrite  = $object->restrictedProjectArea($user,'write');
	    $userDelete = $object->restrictedProjectArea($user,'delete');
	    //print "userAccess=".$userAccess." userWrite=".$userWrite." userDelete=".$userDelete;


	    // Confirmation validation
	    if ($action == 'validate') {
	        print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ValidateProject'), $langs->trans('ConfirmValidateProject'), 'confirm_validate','',0,1);
	    }
	    
	    // Confirmation close
	    if ($action == 'close') {
	        print $form->formconfirm($_SERVER["PHP_SELF"]."?id=".$object->id,$langs->trans("CloseAProject"),$langs->trans("ConfirmCloseAProject"),"confirm_close",'','',1);
	    }
	    
	    // Confirmation reopen
	    if ($action == 'reopen') {
	        print $form->formconfirm($_SERVER["PHP_SELF"]."?id=".$object->id,$langs->trans("ReOpenAProject"),$langs->trans("ConfirmReOpenAProject"),"confirm_reopen",'','',1);
	    }
	   
	    // Confirmation delete
	    if ($action == 'delete') {
	        $text=$langs->trans("ConfirmDeleteAProject");
	        $task=new TaskABCVC($db);
	        $taskarray=$task->getTasksArray(0,0,$object->id,0,0);
	        $nboftask=count($taskarray);
	        if ($nboftask) $text.='<br>'.img_warning().' '.$langs->trans("ThisWillAlsoRemoveTasks",$nboftask);
	        print $form->formconfirm($_SERVER["PHP_SELF"]."?id=".$object->id,$langs->trans("DeleteAProject"),$text,"confirm_delete",'','',1);
	    }

	    // Clone confirmation
	    if ($action == 'clone') {
	        $formquestion=array(
	    		'text' => $langs->trans("ConfirmClone"),
				array('type' => 'other','name' => 'socid','label' => $langs->trans("SelectThirdParty"),'value' => $form->select_company(GETPOST('socid', 'int')>0?GETPOST('socid', 'int'):$object->socid, 'socid', '', "None", 0, 0, null, 0, 'minwidth200')),
	            array('type' => 'checkbox', 'name' => 'clone_contacts',		'label' => $langs->trans("CloneContacts"), 			'value' => true),
	            array('type' => 'checkbox', 'name' => 'clone_tasks',   		'label' => $langs->trans("CloneTasks"), 			'value' => true),
	        	array('type' => 'checkbox', 'name' => 'move_date',   		'label' => $langs->trans("CloneMoveDate"), 			'value' => true),
	            array('type' => 'checkbox', 'name' => 'clone_notes',   		'label' => $langs->trans("CloneNotes"), 			'value' => true),
	        	array('type' => 'checkbox', 'name' => 'clone_project_files','label' => $langs->trans("CloneProjectFiles"),	    'value' => false),
	        	array('type' => 'checkbox', 'name' => 'clone_task_files',	'label' => $langs->trans("CloneTaskFiles"),         'value' => false)
	        );
	        print $form->formconfirm($_SERVER["PHP_SELF"]."?id=".$object->id, $langs->trans("CloneProject"), $langs->trans("ConfirmCloneProject"), "confirm_clone", $formquestion, '', 1, 300, 590);
	    }
	    print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST" class="form-horizontal">';
	    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	    print '<input type="hidden" name="action" value="update">';
	    print '<input type="hidden" name="id" value="'.$object->id.'">';
	    print '<input type="hidden" name="comefromclone" value="'.$comefromclone.'">';
	    $head=project_prepare_head($object);
		//dol_fiche_head($head, 'project', $langs->trans("Project"),0,($object->public?'projectpub':'project'));
		
        /****************************************************************************************************************** 

        EDIT Project

        *******************************************************************************************************************/
	    if ($action == 'edit' && $userWrite > 0) { ?>
			<div class="panel panel-info">
				<div class="panel-heading">
					<h3 class="panel-title">
						<?php echo load_fiche_titre($langs->trans("EditProject"), '', 'title_project'); ?>
					</h3>
				</div>
				<div class="panel-body">
					<div class="container-fluid abcvc_view">
						<div class="row">
							<div class="col-md-6 col-xs-12">
								<div class="form-group">
									<label for="labelEdit" class="col-sm-2 control-label">	
										<?php echo $langs->trans("Label");?>
									</label>
									<div class="col-sm-10">
										<input type="text" name="title" value="<?php echo $object->title;?>" class="form-control">
									</div>
								</div>
								<div class="form-group">    
									<label for="refEdit" class="col-sm-2 control-label">
										<?php echo $langs->trans("Ref");?>
									</label>
									<div class="col-sm-10">
										<?php 
											$suggestedref=$object->ref;
										?>	
										<input size="20" name="ref" value="<?php echo $suggestedref;?>" class="form-kcontrol readonly" readonly>
										<?php 
											echo $form->textwithpicto('', $langs->trans("YouCanCompleteRef", $suggestedref)); 
										?>
									</div>	
								</div>
								<?php
								// Thirdparty
								if ($conf->societe->enabled) { ?>
									<div class="form-group">
										<label for="thirdCreate" class="col-sm-2 control-label">
											<?php echo $langs->trans("ThirdParty");?>
										</label>
										<div class="col-sm-10">
										<?php
										$filteronlist='';
										if (! empty($conf->global->PROJECT_FILTER_FOR_THIRDPARTY_LIST)) $filteronlist=$conf->global->PROJECT_FILTER_FOR_THIRDPARTY_LIST;
										$text=$form->select_thirdparty_list($object->thirdparty->id, 'socid', $filteronlist, 'None', 1, 0, array(), '', 0, 0, 'minwidth300');
										if (empty($conf->global->PROJECT_CAN_ALWAYS_LINK_TO_ALL_SUPPLIERS) && empty($conf->dol_use_jmobile)) {
											$texthelp=$langs->trans("IfNeedToUseOhterObjectKeepEmpty");
											echo $form->textwithtooltip($text.' '.img_help(), $texthelp, 1, 0, '', '', 2);
										} else {
											echo $text; ?>
												<small id="">
													<a  href="<?php echo DOL_URL_ROOT.'/societe/soc.php?action=create&backtopage='.urlencode($_SERVER["PHP_SELF"].'?action=create');?>">
														<?php echo $langs->trans("AddThirdParty"); ?>
													</a>
												</small>
										<?php
										}
										?>
										</div>
									</div>
								<?php   	
								}
								?>
								<div class="form-group">
									<label for="id_address" class="col-sm-2 control-label">
										<?php echo $langs->trans("Address");?>
									</label>
									<div class="col-sm-10">
										<input type="text" name="id_address" value="<?php echo $object->address; ?>" class="form-control">
									</div>
								</div>
								<div class="form-group">
									<label for="id_postalcode" class="col-sm-2 control-label">
										<?php  echo $langs->trans("Cod postal");?>
									</label>
									<div class="col-sm-10">
										<input type="text" name="id_postalcode" value="<?php echo $object->postal_code; ?>" class="form-control">
									</div>
								</div>
								<div class="form-group">
									<label for="id_city" class="col-sm-2 control-label">
										<?php  echo $langs->trans("Oraș");?>
									</label>
									<div class="col-sm-10">
										<input type="text" name="id_city" value="<?php echo $object->city; ?>" class="form-control">
									</div>
								</div>
							</div>
							<div class="col-md-6 col-xs-12">
								<div class="form-group">
									<label for="descEdit" class="col-sm-2 control-label">
										<?php echo $langs->trans("Description");?>
									</label>
									<div class="col-sm-10">
										<textarea name="description" wrap="soft" rows="8" class="form-control"><?php echo $object->description;?></textarea>
									</div>	
								</div>
								<div class="form-group">
									<label for="zones" class="col-sm-2 control-label">
										<?php echo $langs->trans("Șantier");?>
									</label>
									<div class="col-sm-10">
										<select name="id_zone">
											<?php foreach ($allzones as $zone): ?>
												<option value="<?php echo $zone->rowid; ?>" <?php echo ($zone->rowid == $object->fk_zones)?'selected=""':''; ?> ><?php echo $zone->label.' ('.$zone->kilometers.')'; ?></option>
											<?php endforeach; ?>
										</select> 
									</div>
								</div>
								<div class="form-group">
									<label for="zones" class="col-sm-2 control-label">Cheltuieli fixe</label>
									<div class="col-sm-10">
										<div class="input-group"> 
											<span class="input-group-addon">€</span>
											<input name="id_chargesfixe" type="text" class="form-control currency" value="<?php echo price($object->chargesfixe); ?>">
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
<?php	   	} else {
	        /****************************************************************************************************************** 
				
	        VIEW Project

	        *******************************************************************************************************************/
	        ?>
				<div class="container-fluid">
					<div class="row">
						<div class="col-md-12 col-xs-12">
						    <?php    
						        $linkback = '';//<a href="/abcvc/projet/index.php?idmenu=88&mainmenu=abcvc&leftmenu=">'.$langs->trans("BackToList").'</a>';
						        $morehtmlref='<div class="refidno">';
						        // Title
						        $morehtmlref.=$object->title;
						        // Thirdparty
						        if ($object->thirdparty->id > 0) 
						        {
						            $morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . $object->thirdparty->getNomUrl(1, 'project');
						        }
						        $morehtmlref.='</div>';
						        // Define a complementary filter for search of next/prev ref.
						        if (! $user->rights->projet->all->lire)
						        {
						            $objectsListId = $object->getProjectsAuthorizedForUser($user,0,0);
						            $object->next_prev_filter=" rowid in (".(count($objectsListId)?join(',',array_keys($objectsListId)):'0').")";
						        }
							    $object->dol_banner_tab($object, 'ref', $linkback, 0, 'ref', 'ref', $morehtmlref);
						    ?>
						    <hr/>
					    </div>
					</div>
				</div>
				<div class="container-fluid abcvc_view">
					<div class="row">
						<div class="col-md-4 col-xs-12">
							<div class="form-group"> 
								<label for="id_address" class=" control-label">
									<?php  echo $langs->trans("Address"); ?>
								</label><br />
								<?php 
									echo $object->address;
								?>
							</div>
							<div class="form-group"> 
								<label for="id_postalcode" class=" control-label">
									<?php  echo $langs->trans("Cod postal"); ?>
								</label><br />
								<?php 
									echo $object->postal_code;
								?>
							</div>
							<div class="form-group"> 
								<label for="id_city" class=" control-label">
									<?php  echo $langs->trans("Oraș"); ?>
								</label><br />
								<?php 
									echo $object->city;
								?>
							</div>	
						</div>
						<div class="col-md-4 col-xs-12">
							<div class="form-group"> 
								<label for="cardLabel" class=" control-label">
									<?php echo $langs->trans("Description"); ?>
								</label><br />
								<?php 
									print nl2br($object->description);
								?>
							</div>	
							<div class="form-group"> 
								<label for="zones" class=" control-label">
									<?php echo $langs->trans("Șantier"); ?>
								</label><br />
									<?php foreach ($allzones as $zone): ?>
								    	<?php echo ($zone->rowid == $object->fk_zones)?  $zone->label.' ('.$zone->kilometers.')':''; ?>
								    <?php endforeach; ?>
							</div>
							<div class="form-group">
						    	<label for="zones" class=" control-label">Cheltuieli fixe</label><br />
						    	<?php 
									echo price($object->chargesfixe)." €";
								?>
						    </div>

						</div>
						<div class="col-md-4 col-xs-12">
							<?php 
							if ($action != 'presend') {
							// print '<div class="fichecenter"><div class="fichehalfleft">';
								//print '<a name="builddoc"></a>'; // ancre
								$filename=dol_sanitizeFileName($object->ref);
								$filedir = $conf->projet->dir_output . "/" . dol_sanitizeFileName($object->ref);
								$urlsource=$_SERVER["PHP_SELF"]."?id=".$object->id;
								$genallowed=($user->rights->projet->lire && $userAccess > 0);
								$delallowed=($user->rights->projet->creer && $userWrite > 0);
								$var=true;
								print $formfile->showdocuments('project',$filename,$filedir,$urlsource,$genallowed,$delallowed,$object->modelpdf);
							}
							?>
						</div>
					</div>
				</div>
				<hr/>
			<?php
			/****************************************************************************************************************

	        
			LOTS/CATEG POSTES/SUBPOSTES


	        *******************************************************************************************************************/
			$projectTree = $object->getProjectTree($id, $user);
			// var_dump($projectTree);
			?>
			<div class="row">
				<div class="col-xs-12 btn-group">
					<!-- LOTS/categ ETC -->
					<button class="btn btn-default"  id="bt_new_lot" data-nb_lot="<?php echo $projectTree['stats']['lots']; ?>">Adaugă Lot</button>
					<?php if($projectTree['stats']['lots']>0) :?>
						<button class="btn btn-default"  data-toggle="modal" data-target="#categoryModal">Adaugă Categorie</button>
						<?php if($projectTree['stats']['categories']>0) :?>
							<button class="btn btn-default"  data-toggle="modal" data-target="#posteModal">Adaugă Task</button>
							<?php if($projectTree['stats']['postes']>0) :?>
								<button class="btn btn-default"  data-toggle="modal" data-target="#subposteModal">Adaugă Subtask</button>
								<?php if($projectTree['stats']['subpostes']>0) :?>
								<button class="btn btn-default"  data-toggle="modal" data-target="#subsubposteModal">Adaugă Subsubtask</button>
								<?php endif; ?>
							<?php endif; ?>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
			<hr/>
			<!--/****************************************************************************************************************


			THE TREE


	        *******************************************************************************************************************/-->

<?php
/* *****************************************************************************************************************************************
     *
     * PROJECT TREE 
     * 
    ***************************************************************************************************************************************** */
?>
	
	<table class="table">
		<?php 	$total = 0; 
				$total_calculated = 0;
				$total_mo = 0; 
				$total_fact = 0; 
				$total_vente = 0;
				$total_marge = 0; ?>
		<thead>
			<tr>
				<th>Cod unic - Denumire</th>
				<th width="10%" align="right" style="text-align: right;">Cost estimat</th>
				<th width="10%" align="right" style="text-align: right;">Cost real</th>
				<th width="10%" align="right" style="text-align: right;">Vânzare</th>
				<th width="10%" align="right" style="text-align: right;">Margine</th>
				<th width="15%"  style="text-align: right;">Evoluție estimată</th>
				<th width="15%"  style="text-align: right;">Evoluție reală</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $projectTree['tree'] as $key => $lot) :
						$total += price2num($lot->cost); 
						$total_calculated += price2num($lot->cost_calculated);
						//$total_fact += price2num($lot->cost_lot);
						$total_marge += price2num($lot->marge);
						$total_vente += price2num($lot->pv_lot);?>
					<tr class="tr_lot">
						<td>
							<a 
								href = "#" 
								data-toggle = "tooltip" 
								data-placement = "auto" 
								title = "Descriere:<br /><?php echo htmlspecialchars($lot->description); ?>"
								class = "link_edit_lot" 
								data-id = "<?php echo $lot->rowid; ?>" 
								data-label = "<?php echo $lot->label; ?>" 
								data-desc = "<?php echo htmlspecialchars($lot->description); ?>" 
								data-ref = "<?php echo $lot->ref; ?>" 
								data-category_child = "<?php echo count($lot->categories); ?>"
								data-nbchild = "<?php echo $lot->nb_child; ?>"> 
							<b><?php echo $lot->ref;?>. <?php echo $lot->label; ?></b></a>
						</td>
						<td align="right"> <?php echo price($lot->cost); ?>€</td>
						<td align="right"><b> <?php echo price($lot->cost_calculated); ?>€</b></td>
						<td align="right"><?php echo price($lot->pv_lot); ?>€</td>
						<td align="right"><?php 
							if( $lot->marge>=0){
								echo price( $lot->marge).'€'; 
							} else {
								echo '<span style="color:red;">'.price( $lot->marge).'€</span>'; 
							}
							?>
						</td>
						<td align="right">
							--
						</td>
						<td align="right">
							--
						</td>
					</tr>
					<?php foreach ( $lot->categories as $key => $categorie) : ?>
						<tr class="tr_categorie">
							<td>
								<a 
								href = "#"
								data-toggle = "tooltip" 
								data-placement = "auto" 
								title = "Descriere:<br /><?php echo htmlspecialchars($categorie->description); ?>"	
								class = "link_edit_category" 
								data-id = "<?php echo $categorie->rowid; ?>" 
								data-label = "<?php echo $categorie->label; ?>" 
								data-desc = "<?php echo  htmlspecialchars($categorie->description); ?>" 
								data-ref = "<?php echo $categorie->ref; ?>"
								data-poste_child = "<?php echo count($categorie->postes); ?>"
								data-nbchild = "<?php echo $categorie->nb_child; ?>">
								<?php echo $categorie->ref; ?>. <?php echo $categorie->label; ?></a>		
							</td>
							<td align="right"> <?php echo price($categorie->cost); ?>€</td>
							<td align="right"><b> <?php echo price($categorie->cost_calculated); ?>€</b></td>
							<td align="right"><?php echo price($categorie->pv_categorie); ?>€</td>
							<td align="right"><?php 
								if( $categorie->marge_categorie>=0){
									echo price( $categorie->marge_categorie).'€'; 
								} else {
									echo '<span style="color:red;">'.price( $categorie->marge_categorie).'€</span>'; 
								}
								?>
							</td>
							<td align="right">--</td>
							<td align="right">--</td>
						</tr>
						<?php foreach ( $categorie->postes as $key => $poste) : ?>	
							<tr class="tr_poste">
								<td>
									<a 
										href = "#"
										data-toggle = "tooltip" 
										data-placement = "auto" 
										title = "Descriere:<br /><?php echo htmlspecialchars($poste->description); ?>"
										class = "link_edit_poste" 
										data-id = "<?php echo $poste->rowid; ?>" 
										data-ref = "<?php echo $poste->ref; ?>"
										data-category = "<?php echo $poste->fk_categorie; ?>"
										data-label = "<?php echo $poste->label; ?>" 
										data-desc = "<?php echo  htmlspecialchars($poste->description); ?>" 
										data-startdate = "<?php echo ($poste->dateo == '0000-00-00 00:00:00' || $poste->dateo == null)?'':date("d/m/Y",strtotime($poste->dateo)) ; ?>" 
										data-enddate = "<?php echo ($poste->datee == '0000-00-00 00:00:00' || $poste->datee == null)?'':date("d/m/Y",strtotime($poste->datee)) ; ?>"
										data-plannedworkload = "<?php echo $poste->planned_workload;?>"
										data-calculatedworkload = "<?php echo $poste->calculated_workload;?>"
										data-contacts_executive = "<?php echo implode(',',$poste->contacts_executive); ?>"
										data-contacts_contributor = "<?php echo implode(',',$poste->contacts_contributor); ?>"
										data-zone = "<?php echo $poste->fk_zone; ?>"
										data-progress_estimated = "<?php echo $poste->progress_estimated; ?>"
										data-progress="<?php echo $poste->progress; ?>"
										data-factfourn = "<?php echo $poste->fact_fourn;?>"
										data-subposte_child = "<?php echo count($poste->subpostes); ?>"
										data-cost_final = "<?php echo $poste->cost_final ?>"
										data-cost_fourn = "<?php echo $poste->cost_fourn ?>"
										data-cost_mo = "<?php echo $poste->cost_mo ?>"
										data-cost_mo_calculated = "<?php echo $poste->cost_mo_calculated ?>"
										data-cost = "<?php echo $poste->cost ?>"
										data-poste_pv = "<?php echo $poste->poste_pv ?>"
										data-tx_tva = "<?php echo $poste->tx_tva ?>"
										data-nbchild = "<?php echo $poste->nb_child; ?>">
										<?php echo $poste->ref; ?>. <?php echo $poste->label; ?></a>				
								</td>
								<td align="right"><?php echo price($poste->cost); ?>€</td>						
								<td align="right">
									<b> <?php echo price($poste->cost_final); ?>€</b>
								</td>
								<td align="right"><?php echo price($poste->poste_pv); ?>€</td>
								<td align="right"><?php 
									$marge = $poste->poste_pv - $poste->cost_final;
									if($marge>=0){
										echo price($marge).'€'; 
									} else {
										echo '<span style="color:red;">'.price($marge).'€</span>'; 
									}
									?>
								</td>
								<td align="right">
									<div class="progress">
										<?php 
										if($poste->progress_estimated<80){
											$progress_color = 'progress-bar-success';
										} elseif($poste->progress_estimated<100){
											$progress_color = 'progress-bar-warning';
										} elseif($poste->progress_estimated ==100){
											$progress_color = 'progress-bar-info';
										} else {
											$progress_color = 'progress-bar-danger';
										}
										?>
										<div class="progress-bar <?php echo $progress_color;?>" role="progressbar" aria-valuenow="<?php echo $poste->progress_estimated; ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo ($poste->progress_estimated<=100)?$poste->progress_estimated:'100'; ?>%;">
											<?php echo $poste->progress_estimated; ?>%
										</div>
									</div>
								</td>
								<td align="right">
									<div class="progress">
										<?php 
										if($poste->progress<80){
											$progress_color = 'progress-bar-success';
										} elseif($poste->progress<100){
											$progress_color = 'progress-bar-warning';
										} elseif($poste->progress ==100){
											$progress_color = 'progress-bar-info';
										} else {
											$progress_color = 'progress-bar-danger';
										}
										?>
										<div class="progress-bar <?php echo $progress_color;?>" role="progressbar" aria-valuenow="<?php echo $poste->progress; ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo ($poste->progress<=100)?$poste->progress:'100'; ?>%;">
											<?php echo $poste->progress; ?>%
										</div>
									</div>
								</td>
							</tr>
							<?php 
								// !!! somme subposte/subsubposte PV <= $poste->poste_pv !!!
								// --------------------------------------------------------------
								//$sum_subpostes_pv = 0;
								$sum_allsubs_pv = 0;
								foreach ( $poste->subpostes as $key => $subposte) {
									/*if($poste->rowid == 1141){
										var_dump('subposte => '.$subposte->ref." => ".$subposte->poste_pv); 	 
										//exit();
									}*/
									//$sum_subpostes_pv += $subposte->poste_pv;
									//$sum_allsubs_pv += $subposte->poste_pv;
									$sum_subsubs_pv = 0;
									foreach ( $subposte->subsubpostes as $key2 => $subsubposte) {
										/*if($poste->rowid == 1141){
											var_dump('subsubposte => '. $subsubposte->ref." => ".$subsubposte->poste_pv); 	 
											//exit();
										}*/
										$sum_subsubs_pv += $subsubposte->poste_pv;
									}

									//...
									if($sum_subsubs_pv==0){
										$sum_allsubs_pv += $subposte->poste_pv;
									} else {
										$sum_allsubs_pv += $sum_subsubs_pv;
									}
								}
								$delta_sum_pv = round(($sum_allsubs_pv - $poste->poste_pv),2);
								//0.05 ecart accepte...
								if( ($delta_sum_pv == 0) || ( ($delta_sum_pv > -0.05) && ($delta_sum_pv < 0.05) ) ){
									$info_pv_detail = 'equ';
									$info_subpv_detail = 'equ';
									
								} elseif( $delta_sum_pv > 0.05 ){
									$info_pv_detail = 'sup';
									$info_subpv_detail = 'sup';
								} elseif( $delta_sum_pv < -0.05 ){
									$info_pv_detail = 'inf';
									$info_subpv_detail = 'inf';
								}
								/*if($poste->rowid == 1141){
									
									var_dump( $sum_allsubs_pv." ? ".$poste->poste_pv); // 596.01 ? 596
									var_dump( $delta_sum_pv." => ".$info_pv_detail); 	 // 0.01 => inf

									var_dump( 'sum_allsubs_pv:'.$sum_allsubs_pv); 
									//exit();
								}*/
							?>	
							<?php foreach ( $poste->subpostes as $key => $subposte) : ?>
								<tr class="tr_sposte">
									<td>
									<a 
									href = "#" 
									class = "link_edit_subposte"
									data-toggle = "tooltip" 
									data-placement = "auto" 
									title = "Descriere:<br /><?php echo htmlspecialchars($subposte->description); ?>"
									data-id = "<?php echo $subposte->rowid; ?>" 
									data-ref = "<?php echo $subposte->ref; ?>"
									data-taskparent = "<?php echo $subposte->fk_task_parent; ?>" 
									data-label = "<?php echo $subposte->label; ?>"
									data-desc = "<?php echo htmlspecialchars($subposte->description); ?>"
									data-datec = "<?php echo $subposte->datec; ?>"
									data-usercreat = "<?php echo $subposte->fk_user_creat; ?>"
									data-status = "<?php echo $subposte->fk_statut; ?>"
									data-dateo = "<?php echo ($subposte->dateo == '0000-00-00 00:00:00' || $subposte->dateo == null)?'':date("d/m/Y",strtotime($subposte->dateo)) ; ?>"
									data-datee = "<?php echo ($subposte->datee == '0000-00-00 00:00:00' || $subposte->datee == null)?'':date("d/m/Y",strtotime($subposte->datee)) ; ?>"
									data-plannedworkload = "<?php echo $subposte->planned_workload; ?>"
									data-prog = "<?php echo $subposte->progress; ?>"
									data-subposte_contacts_executive = "<?php echo implode(',',$poste->contacts_executive); ?>"
									data-subposte_contacts_contributor = "<?php echo implode(',',$poste->contacts_contributor); ?>"
									data-id_zone="<?php echo $poste->fk_zone; ?>"	

									data-progress_estimated = "<?php echo $poste->progress_estimated; ?>"
									data-factfourn = "<?php echo $subposte->fact_fourn;?>"
									data-subsubposte_child = "<?php echo count($subposte->subsubpostes); ?>"
									
									data-cost_final = "<?php echo $subposte->cost_final ?>"
									data-cost_mo = "<?php echo $subposte->cost_mo ?>"
									data-cost_fourn = "<?php echo $subposte->cost_fourn ?>"
									data-cost = "<?php echo $subposte->cost ?>"
									data-poste_pv = "<?php echo $subposte->poste_pv ?>"
									data-nbchild = "<?php echo $subposte->nb_child; ?>"
									data-unite = "<?php echo $subposte->unite; ?>"
									data-quantite = "<?php echo $subposte->quantite; ?>"
									>
									<?php 
										// !!! somme subposte/subsubposte PV <= $poste->poste_pv !!!
										
									?>
									<?php echo $subposte->ref; ?>. <?php echo $subposte->label; ?></a>					
									</td>
									<td align="right"><i> <?php echo price($subposte->cost); ?>€</i></td>
									<td align="right"><i> <?php echo price($subposte->cost_final); ?>€</i></td>

									<td align="right">
										<?php if($info_pv_detail == 'sup'):?>
											<i><span style="color:red;"><i class="fa fa-exclamation-triangle" aria-hidden="true" title="Attention, somme des PV manuel est superieure au PV poste"></i> <?php echo price($subposte->poste_pv); ?>€</span></i>

										<?php elseif($info_pv_detail == 'inf'):?>
											<i><span style="color:#f0ad4e;"><i class="fa fa-exclamation-triangle" aria-hidden="true" title="Attention, somme des PV manuel est inferieure au PV poste"></i> <?php echo price($subposte->poste_pv); ?>€</span></i>

										<?php else: ?>
											<i><?php echo price($subposte->poste_pv); ?>€</i>
										<?php endif; ?>
									</td>
									<td align="right"><i><?php 
										//echo $info_pv_detail;
										$marge = $subposte->poste_pv - $subposte->cost_final;
										if( ($marge>=0) && ($info_pv_detail == 'equ') ){
											echo price($marge).'€'; 
										} else {
											echo '<span style="color:red;">'.price($marge).'€</span>'; 
										}

									?></i></td>

									<td align="right">--</td>
									<td align="right">--</td>
								</tr>
								<?php 
									// !!! somme subposte/subsubposte PV <= $subposte->poste_pv !!!
									// --------------------------------------------------------------
									$sum_subsubpostes_pv = 0;
									//$info_subpv_detail = 'equ';
									/*foreach ( $subposte->subsubpostes as $key => $subsubposte ) {
										if($poste->rowid == 1122){
											var_dump( $subsubposte->ref." => ".$subsubposte->poste_pv); 	 
											//exit();
										}								
										$sum_subsubpostes_pv += round($subsubposte->poste_pv,2);
									}
									unset($subsubposte);

									if($sum_subsubpostes_pv!=0){
										$delta_sum_subpv = round(($sum_subsubpostes_pv - $subposte->poste_pv),2);
										//0.05 ecart accepte...
										if( ($delta_sum_subpv == 0) || ( ($delta_sum_subpv > -0.05) && ($delta_sum_subpv < 0.05) ) ){
											$info_subpv_detail = 'equ';
										} elseif( $delta_sum_subpv > 0.05 ){
											$info_subpv_detail = 'sup';
										} elseif( $delta_sum_subpv < -0.05 ){
											$info_subpv_detail = 'inf';
										} 
									} else {
										$delta_sum_pv = 0;
										$info_pv_detail = 'equ';
									} 								

									if($poste->rowid == 1122){
										var_dump( $sum_subsubpostes_pv." ? ".$subposte->poste_pv); // 596.01 ? 596
										var_dump( $delta_sum_subpv." => ".$info_subpv_detail); 	 // 0.01 => inf
										//exit();
									}*/							

								?>
								<?php foreach ( $subposte->subsubpostes as $key => $subsubposte) : ?>
									<tr class="tr_ssposte">
										<td>
										<a 
										href = "#" 
										class = "link_edit_subsubposte"
										data-toggle = "tooltip" 
										data-placement = "auto" 
										title = "Descriere:<br /><?php echo htmlspecialchars($subsubposte->description); ?>"
										data-id = "<?php echo $subsubposte->rowid; ?>" 
										data-ref = "<?php echo $subsubposte->ref; ?>"
										data-taskparent = "<?php echo $subsubposte->fk_task_parent; ?>" 
										data-label = "<?php echo $subsubposte->label; ?>"
										data-desc = "<?php echo  htmlspecialchars($subsubposte->description); ?>"
										data-datec = "<?php echo $subsubposte->datec; ?>"
										data-usercreat = "<?php echo $subsubposte->fk_user_creat; ?>"
										data-status = "<?php echo $subsubposte->fk_statut; ?>"
										data-dateo = "<?php echo ($subsubposte->dateo == '0000-00-00 00:00:00'  || $subsubposte->dateo == null)?'':date("d/m/Y",strtotime($subsubposte->dateo)) ; ?>"
										data-datee = "<?php echo ($subsubposte->datee == '0000-00-00 00:00:00' || $subsubposte->datee == null)?'':date("d/m/Y",strtotime($subsubposte->datee)) ; ?>"
										data-plannedworkload = "<?php echo $subsubposte->planned_workload; ?>"
										data-prog = "<?php echo $subsubposte->progress; ?>"												
										data-subsubposte_contacts_executive = "<?php echo implode(',',$poste->contacts_executive); ?>"
										data-subsubposte_contacts_contributor = "<?php echo implode(',',$poste->contacts_contributor); ?>"
										data-subsubposte_price = "<?php echo $subsubposte->cost ; ?>"
										data-id_zone="<?php echo $poste->fk_zone; ?>"			
										data-progress_estimated = "<?php echo $poste->progress_estimated; ?>"
										data-factfourn = "<?php echo $subsubposte->fact_fourn;?>"
										data-cost_final = "<?php echo $subsubposte->cost_final ?>"
										data-cost_mo = "<?php echo $subsubposte->cost_mo ?>"
										data-cost_fourn = "<?php echo $subsubposte->cost_fourn ?>"
										data-cost = "<?php echo $subsubposte->cost ?>"
										data-poste_pv = "<?php echo $subsubposte->poste_pv ?>"
										data-unite = "<?php echo $subsubposte->unite; ?>"
										data-quantite = "<?php echo $subsubposte->quantite; ?>"
										>
										<?php echo $subsubposte->ref; ?>. <?php echo $subsubposte->label; ?></a>
										</td>
										<td align="right"><i> <?php echo price($subsubposte->cost); ?>€</i></td>
										<td align="right"><i> <?php echo price($subsubposte->cost_final); ?>€</i></td>

										<td align="right">
											<?php if($info_subpv_detail == 'sup'):?>
												<i><span style="color:red;"><i class="fa fa-exclamation-triangle" aria-hidden="true" title="Attention, somme des PV manuel est superieure au PV sous-poste"></i> <?php echo price($subsubposte->poste_pv); ?>€</span></i>

											<?php elseif($info_subpv_detail == 'inf'):?>
												<i><span style="color:#f0ad4e;"><i class="fa fa-exclamation-triangle" aria-hidden="true" title="Attention, somme des PV manuel est inferieure au PV sous-poste"></i> <?php echo price($subsubposte->poste_pv); ?>€</span></i>

											<?php else: ?>
												<i><?php echo price($subsubposte->poste_pv); ?>€</i>
											<?php endif; ?>
										</td>
										<td align="right"><i><?php 
											$marge = $subsubposte->poste_pv - $subsubposte->cost_final;
											if( ($marge>=0) && ($info_subpv_detail == 'equ') ){
												echo price($marge).'€'; 
											} else {
												echo '<span style="color:red;">'.price($marge).'€</span>'; 
											}

										?></i></td>
										<td align="right">--</td>
										<td align="right">--</td>
									</tr>
								<?php endforeach; ?>
							<?php endforeach; ?>
						<?php endforeach; ?>
					<?php endforeach; ?>
				<?php endforeach;  ?>
			<?php 
			//injection charges fixes projet
			$total = $total + $object->chargesfixe;
			$total_calculated = $total_calculated + $object->chargesfixe;
			$total_marge = $total_vente - $total_calculated;
			?>
			<tr>
		        <td ><i> Cheltuielile fixe ale proiectului </i></td>
		        <td  align="right"><b><?php echo price($object->chargesfixe)?>€</b></td>
		        <td  align="right"><b><?php echo price($object->chargesfixe)?>€</b></td>
		        <td colspan="4"></td>
		    </tr>    
			<tr>
		        <td ><b> Total </b></td>
		        <td align="right"><b><?php echo price($total)?>€</b></td>
		        <td align="right"><b><?php echo price($total_calculated)?>€</b></td>
		        <td align="right"><b><?php echo price($total_vente)?>€</b></td>
		        <td align="right"><?php 
					//$marge_total = $total_vente-$total;
					if($total_marge>0){
						echo '<b>'.price($total_marge).'€</b>'; 
					} else {
						echo '<span style="color:red;">'.price($total_marge).'€</span>'; 
					}
				?></td>
		        <td  align="right"></td>
				<td  align="right"></td>
		    </tr>
		</tbody>
	</table>
<?php
}
?>
<?php
	    //dol_fiche_end();
		if ($action == 'edit' && $userWrite > 0) {
		    print '<div align="center">';
	    	print '<input name="update" class="btn btn-success" type="submit" value="'.$langs->trans("Modify").'">&nbsp; &nbsp; &nbsp;';
	    	print '<input type="submit" class="btn btn-default" name="cancel" value="'.$langs->trans("Cancel").'">';
	    	print '</div>';
		}
	    print '</form>';
	    // OBSOLETE Change probability from status
	    if (! empty($conf->use_javascript_ajax) && ! empty($conf->global->PROJECT_USE_OPPORTUNITIES)) {

	        $defaultcheckedwhenoppclose=1;
	        if (empty($conf->global->PROJECT_HIDE_TASKS)) $defaultcheckedwhenoppclose=0;
	        
	        print '<!-- Javascript to manage opportunity status change -->';
	        print '<script type="text/javascript" language="javascript">
	            jQuery(document).ready(function() {
	            	function change_percent()
	            	{
	                    var element = jQuery("#opp_status option:selected");
	                    var defaultpercent = element.attr("defaultpercent");
	                    var defaultcloseproject = '.$defaultcheckedwhenoppclose.';
	                    var elemcode = element.attr("elemcode");
	                    var oldpercent = \''.dol_escape_js($object->opp_percent).'\';

	                    console.log("We select "+elemcode);
	                    if (elemcode == \'LOST\') defaultcloseproject = 1;
	                    jQuery("#divtocloseproject").show();
	                    if (defaultcloseproject) jQuery("#inputcloseproject").prop("checked", true);
	                    else jQuery("#inputcloseproject").prop("checked", false);
	                        
	                    /* Make close project visible or not */
	                    if (elemcode == \'WON\' || elemcode == \'LOST\') 
	                    {
	                        jQuery("#divtocloseproject").show();
	                    }
	                    else
	                    {
	                        jQuery("#divtocloseproject").hide();
	                    }
	                        
	                    /* Change percent of default percent of new status is higher */
	                    if (parseFloat(jQuery("#opp_percent").val()) != parseFloat(defaultpercent))
	                    {
	                        if (jQuery("#opp_percent").val() != \'\' && oldpercent != \'\') jQuery("#oldopppercent").text(\' - '.dol_escape_js($langs->transnoentities("PreviousValue")).': \'+oldpercent+\' %\');
	                        jQuery("#opp_percent").val(defaultpercent);
	                    }
	            	}

	            	jQuery("#opp_status").change(function() {
	            		change_percent();
	            	});
	        });
	        </script>';


	    }

    /*
     * OBSOLETE Boutons actions
     */
	    print '<div class="tabsAction">';
	    $parameters = array();
	    $reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action);  // Note that $action and $object may have been
		                                                                                          		// modified by hook
	    if (empty($reshook)) {

		    if ($action != "edit" ) {
	        	// Create event
	        	if ($conf->agenda->enabled && ! empty($conf->global->MAIN_ADD_EVENT_ON_ELEMENT_CARD)) {			
	        		// Add hidden condition because this is not a
	            	// "workflow" action so should appears somewhere else on
	            	// page.
	            	print '<div class="inline-block divButAction"><a class="butAction" href="'.DOL_URL_ROOT.'/comm/action/card.php?action=create&amp;origin=' . $object->element . '&amp;originid=' . $object->id . '&amp;socid=' . $object->socid . '&amp;projectid=' . $object->id . '">' . $langs->trans("AddAction") . '</a></div>';
	        	}
				// Modify
		        if ($object->statut != 2 && $user->rights->projet->creer) {
		            if ($userWrite > 0) {
		                //print '<div class="inline-block divButAction"><a class="butAction" href="card.php?id='.$object->id.'&amp;action=edit">'.$langs->trans("Modify").'</a></div>';
		            }
		            else {
		                //print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="'.$langs->trans("NotOwnerOfProject").'">'.$langs->trans('Modify').'</a></div>';
		            }
		        }
		        // Close 
		        if ($object->statut == 1 && $user->rights->projet->creer){
		        
		            if ($userWrite > 0) {
		            
		                //print '<div class="inline-block divButAction"><a class="butAction" href="card.php?id='.$object->id.'&amp;action=close">'.$langs->trans("Close").'</a></div>';
		            }
		            else {
		            
		                //print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="'.$langs->trans("NotOwnerOfProject").'">'.$langs->trans('Close').'</a></div>';
		            }
		        }
		        // Reopen
		        if ($object->statut == 2 && $user->rights->projet->creer) {
		  
		            if ($userWrite > 0) {		            
		                print '<div class="inline-block divButAction" style="margin-right:50%;"><a class="btn btn-primary" href="card.php?id='.$object->id.'&amp;action=reopen">'.$langs->trans("ReOpen").'</a></div>';
		            }
		            else {
		                print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="'.$langs->trans("NotOwnerOfProject").'">'.$langs->trans('ReOpen').'</a></div>';
		            }
		        }
		        // Add button to create objects from project
		        if (! empty($conf->global->PROJECT_SHOW_CREATE_OBJECT_BUTTON)) {

		            if (! empty($conf->propal->enabled) && $user->rights->propal->creer) {
		            
		                $langs->load("propal");
		                print '<div class="inline-block divButAction"><a class="butAction" href="'.DOL_URL_ROOT.'/comm/propal/card.php?action=create&projectid='.$object->id.'&socid='.$object->socid.'">'.$langs->trans("AddProp").'</a></div>';
		            
		            }

		            if (! empty($conf->commande->enabled) && $user->rights->commande->creer) {
		      
		                $langs->load("orders");
		                print '<div class="inline-block divButAction"><a class="butAction" href="'.DOL_URL_ROOT.'/commande/card.php?action=create&projectid='.$object->id.'&socid='.$object->socid.'">'.$langs->trans("CreateOrder").'</a></div>';
		            }

		            if (! empty($conf->facture->enabled) && $user->rights->facture->creer) {
		            
		                $langs->load("bills");
		                print '<div class="inline-block divButAction"><a class="butAction" href="'.DOL_URL_ROOT.'/compta/facture.php?action=create&projectid='.$object->id.'&socid='.$object->socid.'">'.$langs->trans("CreateBill").'</a></div>';
		            }

		            if (! empty($conf->supplier_proposal->enabled) && $user->rights->supplier_proposal->creer) {
		            
		                $langs->load("supplier_proposal");
		                print '<div class="inline-block divButAction"><a class="butAction" href="'.DOL_URL_ROOT.'/supplier_proposal/card.php?action=create&projectid='.$object->id.'&socid='.$object->socid.'">'.$langs->trans("AddSupplierProposal").'</a></div>';
		            }

		            if (! empty($conf->supplier_order->enabled) && $user->rights->fournisseur->commande->creer) {
		            
		                $langs->load("suppliers");
		                print '<div class="inline-block divButAction"><a class="butAction" href="'.DOL_URL_ROOT.'/fourn/commande/card.php?action=create&projectid='.$object->id.'&socid='.$object->socid.'">'.$langs->trans("AddSupplierOrder").'</a></div>';
		            }

		            if (! empty($conf->supplier_invoice->enabled) && $user->rights->fournisseur->facture->creer) {
		            
		                $langs->load("suppliers");
		                print '<div class="inline-block divButAction"><a class="butAction" href="'.DOL_URL_ROOT.'/fourn/facture/card.php?action=create&projectid='.$object->id.'&socid='.$object->socid.'">'.$langs->trans("AddSupplierInvoice").'</a></div>';
		            }

		            if (! empty($conf->ficheinter->enabled) && $user->rights->ficheinter->creer) {
		            
		                $langs->load("interventions");
		                print '<div class="inline-block divButAction"><a class="butAction" href="'.DOL_URL_ROOT.'/fichinter/card.php?action=create&projectid='.$object->id.'&socid='.$object->socid.'">'.$langs->trans("AddIntervention").'</a></div>';
		            }

		            if (! empty($conf->contrat->enabled) && $user->rights->contrat->creer) {
		            
		                $langs->load("contracts");
		                print '<div class="inline-block divButAction"><a class="butAction" href="'.DOL_URL_ROOT.'/contrat/card.php?action=create&projectid='.$object->id.'&socid='.$object->socid.'">'.$langs->trans("AddContract").'</a></div>';
		            }

		            if (! empty($conf->expensereport->enabled) && $user->rights->expensereport->creer) {
		            

		                $langs->load("trips");
		                print '<div class="inline-block divButAction"><a class="butAction" href="'.DOL_URL_ROOT.'/expensereport/card.php?action=create&projectid='.$object->id.'&socid='.$object->socid.'">'.$langs->trans("AddTrip").'</a></div>';
		            }
		            
		            if (! empty($conf->don->enabled) && $user->rights->don->creer) {
		            
		                $langs->load("donations");
		                print '<div class="inline-block divButAction"><a class="butAction" href="'.DOL_URL_ROOT.'/don/card.php?action=create&projectid='.$object->id.'&socid='.$object->socid.'">'.$langs->trans("AddDonation").'</a></div>';
		            }

		        }

		        // Clone
		        if ($user->rights->projet->creer) {
		    
		            if ($userWrite > 0) {
		            	//print '<div class="inline-block divButAction"><a class="butAction" href="card.php?id='.$object->id.'&action=clone">'.$langs->trans('ToClone').'</a></div>';
		            }
		            
		            else {
		                //print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="'.$langs->trans("NotOwnerOfProject").'">'.$langs->trans('ToClone').'</a></div>';
		            }
		        }

		        // Delete
		        if ($user->rights->projet->Șterge || ($object->statut == 0 && $user->rights->projet->creer)) {
		        
		            if ($userDelete > 0 || ($object->statut == 0 && $user->rights->projet->creer)) {
		            	//print '<div class="inline-block divButAction"><a class="butActionDelete" href="card.php?id='.$object->id.'&amp;action=delete">'.$langs->trans("Delete").'</a></div>';
		            }
		           
		            else {
		            	//print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="'.$langs->trans("NotOwnerOfProject").'">'.$langs->trans('Delete').'</a></div>';
		            }
		        }
	         }
	    }
	    print '</div>';
	    // Hook to add more things on page
	    $parameters=array();
	    $reshook=$hookmanager->executeHooks('mainCardTabAddMore',$parameters,$object,$action); // Note that $action and $object may have been modified by hook
}
else {
    print $langs->trans("RecordNotFound");
}

//**************************************************************************************************************
// FUNCTION TO TAKE CONTACTS,FACT FOURN, COST AND ZONES FROM llx_socpeople llx_abcvc_zones etc
//**************************************************************************************************************

	$allcontacts = $objtask->getAllcontacts();
	$allfactfourNONaffected = $objtask->getAllfactfournNONaffected($object->id);
	$allfactfourn = $objtask->getAllfactfourn();
	$alltasks = $objtask->getTasksCostsByProject($object->id);
	$allunites = $object->getAllUnites();
	// var_dump($allunites);
	// exit;
	//var_dump($alltasks);
	//exit();
	//var_dump($allcontacts);
	//exit();
	//var_dump($allzones);
	//exit();
	//var_dump($allfactfourNONaffected);
	//exit();
	//var_dump($allfactfourn);
	//exit();
	// var_dump($allsites);
	// exit();

	/*
	$objtask->id=71;
	$test=$objtask->add_contact(13, "TASKEXECUTIVE");
	Joo Fabrice
	Descos Henri
	*/
	//$objtask->id=86;
	//$test=$objtask->liste_contact(4,'external',0);
	//var_dump($test);
?>
<?php						
//**************************************************************************************************************
// MODALS bootstrap
//**************************************************************************************************************
?>

<!--
//**************************************************************************************************************
//
//
// MODAL New Lot
// 
// 
//**************************************************************************************************************
-->
<div class="modal fade" id="lotModal">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4>Adaugă Lot</h4>
			</div>
			
			<div class="modal-body">
				<form class="form-horizontal">	
					
				
				
					<div class="form-group">
						<label class="col-sm-3 control-label" for="code_lot">Ref *</label>
						<div class="col-sm-9">
							<input type="number" name="code_lot" id="code_lot" class="form-control required" required="" min="1" step="1">
						</div>	
					</div>


					<div class="form-group">
						<label class="col-sm-3 control-label" for="label_lot">Nume *</label>
						<div class="col-sm-9">
							<input type="text" name="label_lot" id="label_lot" class="form-control required" required="">
						</div>	
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label" for="description_lot">Descriere</label>
						<div class="col-sm-9">
							<textarea class="form-control" name="description_lot" id="description_lot" rows="8"></textarea>
						</div>	
					</div>
				</form>
			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-success" id="bt_save_lot">Înregistreză</button>
				<button type="button" class="btn btn-default" data-dismiss="modal">Închide</button>
			</div>
		</div>
	</div>
</div>


<!--
//**************************************************************************************************************
//
//
// MODAL New Category
// 
// 
//**************************************************************************************************************
-->
<div class="modal fade" id="categoryModal">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4>Adaugă Categorie</h4>
			</div>
			
			<div class="modal-body">
				<form class="form-horizontal">	

					
					<input type="hidden"  id="code_category">
					

					<div class="form-group">
						<label class="col-sm-3 control-label">Asociază Lot *</label>
						<div class="col-sm-9">
							<select name="lot_category" class="form-control required" id="lot_category"  required="">
								<option value="">Alege un Lot existent</option>
								<?php
									/*
									  'tree' => 
									    array (size=1)
									      0 => 
									        object(stdClass)[134]
									          public 'rowid' => string '1' (length=1)
									          public 'ref' => string '1' (length=1)
									          public 'fk_projet' => string '1' (length=1)
									          public 'label' => string 'lot1' (length=4)
									          public 'description' => string 'test' (length=4)
									          public 'datec' => string '2017-07-08 13:09:28' (length=19)
									          public 'ordering' => string '0' (length=1)
									          public 'fk_user_creat' => string '2' (length=1)
									          public 'fk_statut' => string '1' (length=1)
								    */
								?>          

	          					<?php foreach ($projectTree['tree'] as $key => $lot) : ?>

	          						<option value="<?php echo $lot->rowid ;?>" data-code_lot="<?php echo $lot->ref; ?>" data-nb_categorie="<?php echo count($lot->categories); ?>"><?php echo $lot->ref.' - '.$lot->label ;?></option>

	          					<?php endforeach; ?>

							</select>
						</div>
					</div>					

					<div class="form-group">
						<label class="col-sm-3 control-label" for="label_category">Nume *</label>
						<div class="col-sm-9">
							<input type="text" name="label_category" id="label_category" class="form-control required" required="">
						</div>	
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label">Descriere</label>
						<div class="col-sm-9">
							<textarea class="form-control" name="description_category" id="description_category" rows="8"></textarea>
						</div>	
					</div>

				</form>

			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-success" id="bt_save_category">Înregistreză</button>
				<button type="button" class="btn btn-default" data-dismiss="modal">Închide</button>
			</div>
		</div>
	</div>
</div>


<!--
//**************************************************************************************************************
//
//
// MODAL New Poste
// 
// 
//**************************************************************************************************************
-->
<div class="modal fade" id="posteModal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4>Adaugă Task</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal">	
					<input type="hidden" id="code_poste">
					
					<div class="row">
						<div class="col-md-12">
							<div class="form-group">
								<!--
								<label class="col-sm-2 control-label" for="edit_label_poste">Nom *</label>
								-->
								<div class="col-sm-12">
									<input type="text" name="label_poste" id="label_poste" placeholder="Nume" class="form-control required" required="">
								</div>	
							</div>
						</div>
					</div>


					<div class="row">
						<div class="col-md-6">

							<div class="form-group">
								<label class="col-sm-3 control-label">Categorie *</label>
								<div class="col-sm-9">
									
									<select name="id_category" class="form-control required" id="id_category"  required="">
										<option value="">Alege o Categorie existentă</option>
				          					<?php foreach ($projectTree['tree'] as $key => $lot) : ?>
				          						<?php foreach ($lot->categories as $key => $categorie) : ?>
													<option value="<?php echo $categorie->rowid; ?>" data-code_lot="<?php echo $lot->ref; ?>" data-code_categorie="<?php echo $categorie->ref; ?>" data-nb_poste="<?php echo count($categorie->postes); ?>"><?php echo $lot->ref.' - '.$lot->label; ?> -> <?php echo $categorie->ref.' - '.$categorie->label ;?>  </option>
												<?php endforeach; ?>
				          					<?php endforeach; ?>
									</select>

								</div>
							</div>	
							<div class="form-group">
								<label class="col-sm-3 control-label">Pilote(s) </label>

								<div class="col-sm-9">
									<div class="row">
									<select class="js-example-basic-multiple col-sm-12" id="poste_add_executive" multiple="multiple">
			          					<?php foreach ($allcontacts as $key => $contact) : ?>
											<option value="<?php echo $contact->rowid; ?>"><?php echo $contact->lastname; ?>&nbsp;<?php echo $contact->firstname ?></option>
			          					<?php endforeach; ?>
									</select>
									</div>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">Intervenant(s) </label>
								<div class="col-sm-9">
									<div class="row">
									<select class="js-example-basic-multiple col-sm-12" id="poste_add_contributor" multiple="multiple">
											<?php foreach ($allcontacts as $key => $contact) : ?>
												<option value="<?php echo $contact->rowid; ?>"><?php echo $contact->lastname; ?>&nbsp;<?php echo $contact->firstname ?></option>
					          				<?php endforeach; ?>
									</select>
									</div>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label" for="" required="">Data</label>
								<div class="col-sm-9">
									<div class="input-group">
										<input class="col-sm-6" id="startDate_poste" placeholder="De la" value="">
										<input class="col-sm-6" id="endDate_poste" placeholder="Pănă la" value="">
									</div>
								</div>	
							</div>	
							<div class="form-group">
								<label class="col-sm-3 control-label" for="planned_work_poste" required="">Durată sarcină * </label>
								<div class="col-sm-9">  
									<div>&nbsp;&nbsp;Ore</div>
									<input class="col-sm-4" id="planned_work_poste_h" value="">
								</div>
							</div>	
						</div>

						<div class="col-md-6">

							<p class="">
								Înregistreză acest Task pentru a activa mecanismul de preț

								<input type="hidden" id="poste_add_price_main" value="0">
								<input type="hidden" id="poste_add_factfourn" value="">
								<input type="hidden" id="poste_price" value="0" > 
							</p>
						</div>
					</div>
					<div class="row">
						<div class="col-md-12">
							<div class="form-group">
								<label class="col-sm-2 control-label">Descriere</label>
								<div class="col-sm-10">
									<textarea class="form-control" name="desc_poste" id="desc_poste" rows="3"></textarea>
								</div>	
							</div>
						</div>	
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-success" id="bt_save_poste">Înregistreză</button>
				<button type="button" class="btn btn-default" data-dismiss="modal">Închide</button>
			</div>
		</div>
	</div>
</div>


<!--
//**************************************************************************************************************
//
//
// MODAL New Sous Poste
// 
// 
//**************************************************************************************************************
-->
<div class="modal fade" id="subposteModal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4>Adaugă Subtask</h4>
			</div>
			
			<div class="modal-body">
				<form class="form-horizontal">	

				<div class="row">

					<div class="col-md-6">

						<input type="hidden" id="code_subposte">
							
						<div class="form-group">
							<label class="col-sm-3 control-label" for="label_subposte">Nume *</label>
							<div class="col-sm-9">
								<input type="text" name="label_subposte" id="label_subposte" class="form-control required" required="">
							</div>	
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label">Task *</label>
							<div class="col-sm-9">
								<select name="child_poste" class="form-control required" id="child_subposte"  required="">
									<option value="">Asociază unui task existent</option>
			          					<?php foreach ($projectTree['tree'] as $key => $lot) : ?>
			          						<?php foreach ($lot->categories as $key => $categorie) : ?>
											   <?php foreach ($categorie->postes as $key => $poste) : ?>

													<option value="<?php echo $poste->rowid ;?>" data-id_zone="<?php echo $poste->fk_zone; ?>" data-code_lot="<?php echo $lot->ref; ?>" data-code_categorie="<?php echo $categorie->ref; ?>" data-code_poste="<?php echo $poste->ref; ?>" data-nb_subposte="<?php echo count($poste->subpostes); ?>"
														data-contacts_executive="<?php echo implode(',',$poste->contacts_executive); ?>"
														data-contacts_contributor="<?php echo implode(',',$poste->contacts_contributor); ?>"
													><?php echo $lot->ref.' - '.$lot->label; ?>--><?php echo $categorie->ref.' - '.$categorie->label; ?>--><?php echo $poste->ref.' - '.$poste->label; ?></option>

												<?php endforeach; ?>
											<?php endforeach; ?>
			          					<?php endforeach; ?>
								</select>
							</div>
						</div>	

						<div class="form-group">
							<label class="col-sm-3 control-label">Descriere</label>
							<div class="col-sm-9">
								<textarea class="form-control" name="desc_subposte" id="desc_subposte" rows="3"></textarea>
							</div>	
						</div>

					</div>

					<div class="col-md-6">
					    <div class="input-group">
						    <span class="input-group-addon">Cantite/[U.M.]</span>
						    <input type="number" placeholder="Quantité" id="sousposte_add_unite" name="sousposte_add_unite" required="required" class="form-control" value="0">
						    <!-- insert this line -->
						    <span class="input-group-addon" style="width:0px; padding-left:0px; padding-right:0px; border:none;"></span>
						    <select name="sousposte_select_unite" id="sousposte_select_unite" class="form-control" >
						        <option value="">[U.M.]</option>
				      			<?php  foreach($allunites as $unite): ?>
				        			<option value="<?php print $unite->short_label ?>"><?php print $unite->short_label; ?></option>
				        		<?php endforeach; ?>
			        		</select>
						</div>
					</div>	
				</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-success" id="bt_save_subposte">Înregistreză</button>
				<button type="button" class="btn btn-default" data-dismiss="modal">Închide</button>
			</div>
		</div>
	</div>
</div>


<!--
//**************************************************************************************************************
//
//
// MODAL New Sous-Sous Poste
// 
// 
//**************************************************************************************************************
-->
<div class="modal fade" id="subsubposteModal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4>Adaugă Subsubtask</h4>
			</div>
			
			<div class="modal-body">
				<form class="form-horizontal">	

				<div class="row">

					<div class="col-md-6">

						<input type="hidden" id="code_subsubposte">

						<div class="form-group">
							<label class="col-sm-3 control-label" for="label_subsubposte">Nume *</label>
							<div class="col-sm-9">
								<input type="text" name="label_subsubposte" id="label_subsubposte" class="form-control required" required="">
							</div>	
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label">Subtask *</label>
							<div class="col-sm-9">
								<select name="child_subposte" class="form-control required" id="child_subsubposte"  required="">
									<option value="">Asociază unui Subtask</option>
			          					<?php foreach ($projectTree['tree'] as $key => $lot) : ?>
			          						<?php foreach ($lot->categories as $key => $categorie) : ?>
											   <?php foreach ($categorie->postes as $key => $poste) : ?>			
													<?php foreach ($poste->subpostes as $key => $subposte) : ?>		
													<option value="<?php echo $subposte->rowid; ?>" data-id_zone="<?php echo $poste->fk_zone; ?>" data-code_lot="<?php echo $lot->ref; ?>" data-code_categorie="<?php echo $categorie->ref; ?>" data-code_poste="<?php echo $poste->ref; ?>" data-code_subposte="<?php echo $subposte->ref; ?>" data-nb_subsubposte="<?php echo count($subposte->subsubpostes); ?>"
														data-contacts_executive="<?php echo implode(',',$poste->contacts_executive); ?>"
														data-contacts_contributor="<?php echo implode(',',$poste->contacts_contributor); ?>"
														><?php echo $lot->ref.' - '.$lot->label; ?>--><?php echo $categorie->ref.' - '.$categorie->label; ?>--><?php echo $poste->ref.' - '.$poste->label; ?>--><?php echo $subposte->ref.' - '.$subposte->label; ?></option>
													<?php endforeach; ?>
												<?php endforeach; ?>
											<?php endforeach; ?>
			          					<?php endforeach; ?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">Descriere</label>
							<div class="col-sm-9">
								<textarea class="form-control" name="desc_subsubposte" id="desc_subsubposte" rows="3"></textarea>
							</div>	
						</div>	

					</div>
					<div class="col-md-6">
						<div class="input-group">
						    <span class="input-group-addon">Cantite/[U.M.]</span>
						    <input type="number" placeholder="Quantité" id="soussousposte_add_unite" name="soussousposte_add_unite" required="required" class="form-control" value="0">
						    <!-- insert this line -->
						    <span class="input-group-addon" style="width:0px; padding-left:0px; padding-right:0px; border:none;"></span>
						  
						    <select name="soussousposte_select_unite" id="soussousposte_select_unite" class="form-control" >
						        <option value="">[U.M.]</option>
				      			<?php  foreach($allunites as $unite): ?>
				        			<option value="<?php print $unite->short_label ?>"><?php print $unite->short_label; ?></option>
				        		<?php endforeach; ?>
			        		</select>
						</div>
					</div>	
				</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-success" id="bt_save_subsubposte">Înregistreză</button>
				<button type="button" class="btn btn-default" data-dismiss="modal">Închide</button>
			</div>
		</div>
	</div>
</div>


<!--
//**************************************************************************************************************
//
//
// MODAL Edit Lot
// 
// 
//**************************************************************************************************************
-->
<div class="modal fade" id="edit_lot_Modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
  <div class="modal-dialog " role="document">
    <div class="modal-content">
	    <div class="modal-header">
	       	<h4 class="modal-title" id="myModalLabel">Editează Lot
	       			<div class="pull-right" id="edit_header_lot_code">
	       					Ref : <span></span>
	       			</div>
	       	</h4>
	       
	    </div>
		<div class="modal-body">
			<form class="form-horizontal">
					<input type="hidden" id="edit_id_lot">	
					
					<input type="hidden" id="edit_ref_lot">
					
					<div class="form-group">
						<label class="col-sm-3 control-label" for="label_lot">Nume *</label>
						<div class="col-sm-9">
							<input type="text" name="label_lot" id="edit_label_lot" class="form-control required" required="">
						</div>	
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label">Descriere</label>
						<div class="col-sm-9">
							<textarea class="form-control" name="description_lot" id="edit_description_lot" rows="8"></textarea>
						</div>	
					</div>
			</form>
		</div>
      	<div class="modal-footer">
			<button type="button" class="btn btn-success" id="bt_edit_lot">Înregistreză</button>
			<button type="button" class="btn btn-default" data-dismiss="modal">Închide</button>
			<button type="button" class="btn btn-danger pull-left" id="bt_delete_lot">Șterge</button>
		</div>
    </div>
  </div>
</div>


<!--
//**************************************************************************************************************
//
//
// MODAL Edit Category
// 
// 
//**************************************************************************************************************
-->							
<div class="modal fade" id="edit_category_Modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
  <div class="modal-dialog " role="document">
    <div class="modal-content">
	    <div class="modal-header">
	        
	        	<h4 class="modal-title" id="myModalLabel">Editează categorie
	        		<div class="pull-right" id="edit_header_category_code">
	       					Ref : <span></span>
	       			</div>
	        	</h4>
	    </div>
		<div class="modal-body">
			<form class="form-horizontal">
				<input type="hidden" id="edit_id_category">	
					
					<input type="hidden" id="edit_ref_category">
		
					<div class="form-group">
						<label class="col-sm-3 control-label" for="label_category">Nume *</label>
						<div class="col-sm-9">
							<input type="text" name="label_lot" id="edit_label_category" class="form-control required" required="">
						</div>	
					</div>

					<div class="form-group">
						<label class="col-sm-3 control-label">Descriere</label>
						<div class="col-sm-9">
							<textarea class="form-control" name="description_category" id="edit_description_category" rows="8"></textarea>
						</div>	
					</div>
			</form>
		</div>
      	<div class="modal-footer">
			<button type="button" class="btn btn-success" id="bt_edit_category">Înregistreză</button>
			<button type="button" class="btn btn-default" data-dismiss="modal">Închide</button>
			<button type="button" class="btn btn-danger pull-left" id="bt_delete_category">Șterge</button>
		</div>
    </div>
  </div>
</div>


<!--
//**************************************************************************************************************
//
//
// MODAL Edit Postes *******************************************************************************************
// 
// 
//**************************************************************************************************************
-->
<div class="modal fade" id="posteModal_edit">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4>Editează Task
					<div class="pull-right" id="edit_header_poste_code">
	       					Ref : <span></span>
	       			</div>
				</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal">	
				<input type="hidden" id="edit_id_poste">	
				<input type="hidden" id="edit_code_poste">

				<div class="row">
					<div class="col-md-12">
						<div class="form-group">
							<!--
							<label class="col-sm-2 control-label" for="edit_label_poste">Nom *</label>
							-->
							<div class="col-sm-12">
								<input type="text" name="edit_label_poste" id="edit_label_poste" class="form-control required" required="">
							</div>	
						</div>
					</div>
				</div>
				
				<div class="row">
					<div class="col-md-6">

						<div class="form-group">
							<label class="col-sm-3 control-label">Categorie *</label>
							<div class="col-sm-9">
								<select name="edit_id_category_poste" class="form-control required" id="edit_id_category_poste"  required="" disabled="">
									<option value="">Select an existing category</option>
			          					<?php foreach ($projectTree['tree'] as $key => $lot) : ?>
			          						<?php foreach ($lot->categories as $key => $categ) : ?>
												<option value="<?php echo $categ->rowid ;?>">LOT: <?php echo $lot->label; ?> --> Category: <?php echo $categ->ref.' - '.$categ->label ;?>  </option>
											<?php endforeach; ?>
			          					<?php endforeach; ?>
								</select>
							</div>
						</div>	

						<div class="form-group">
							<label class="col-sm-3 control-label">Pilote(s) </label>
							<input type="hidden" id="poste_edit_executive_initial">
							<div class="col-sm-9">
								<div class="row">
								<select class="js-example-basic-multiple col-sm-12" id="poste_edit_executive" multiple="multiple">
								  	<?php foreach ($allcontacts as $key => $contact) : ?>
										<option value="<?php echo $contact->rowid; ?>"><?php echo $contact->lastname; ?>&nbsp;<?php echo $contact->firstname ?></option>
		          					<?php endforeach; ?>
								</select>
								</div>
							</div>
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label">Intervenant(s) </label>
							<input type="hidden" id="poste_edit_contributor_initial">
							<div class="col-sm-9">
								<div class="row">
								<select class="js-example-basic-multiple col-sm-12" id="poste_edit_contributor" multiple="multiple">
								 	<?php foreach ($allcontacts as $key => $contact) : ?>
										<option value="<?php echo $contact->rowid; ?>"><?php echo $contact->lastname; ?>&nbsp;<?php echo $contact->firstname ?></option>
		          					<?php endforeach; ?>
								</select>
								</div>
							</div>
						</div>

						<div class="form-group ">
							<label class="col-sm-3 control-label" for="" required="">Data</label>
							<div class="col-sm-9">
								<div class="input-group">
								<input class="col-sm-6" id="edit_start_date_poste" placeholder="De la" value="">
								<input class="col-sm-6" id="edit_end_date_poste" placeholder="Pănă la" value="">
								</div>
							</div>	
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label" for="edit_planned_work_h_poste" required="">Durată sarcină *</label>
							<div class="col-sm-4">  
								<div>&nbsp;&nbsp;Ore</div>
								<input class="col-sm-8" id="edit_planned_work_h_poste" value="">
							</div>

							<div class="col-sm-4">  
								<div>&nbsp;Ore reale</div>
								<input class="col-sm-8" id="edit_calculated_work_h_poste" disabled="true" value="0">
							</div>							
						</div>	

					</div>

					<div class="col-md-6">

						<div class="form-group">
							<label class="col-sm-3 control-label">Cost estimat</label>
							<div class="col-sm-9">
								<div class="input-group">
							         <div class="input-group-addon">€</div>
							      <input type="text" class="form-control" id="edit_poste_price" >
							    </div><!-- /input-group -->
							</div>	
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label">TVA</label>
							<div class="col-sm-9">
								<select name="edit_poste_tva" class="form-control required" id="edit_poste_tva"  required="">
									<option value="">Selecteză TVA</option>
			          				<?php foreach ($vat_list as $key => $note) : ?>
										<option value="<?php echo $key; ?>" selected="<?php echo($key == 20 ? "selected" : ""); ?>"><?= $key; ?> % - <?= $note; ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						
						<div class="form-group">
							<label class="col-sm-3 control-label">Cheltuieli Salariale</label>
							<div class="col-sm-4">
								<div>&nbsp;&nbsp;Estimat</div>
								<div class="input-group">
							         <div class="input-group-addon">€</div>
							      <input type="text" class="form-control" id="edit_poste_cost_mo" disabled="true">
							    </div>
							</div>	

							<div class="col-sm-4">
								<div>&nbsp;&nbsp;Calculat</div>
								<div class="input-group">
							         <div class="input-group-addon">€</div>
							      <input type="text" class="form-control" id="edit_poste_cost_mo_calculated" disabled="true">
							    </div>
							</div>							
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label">Factura furnizorilor în legătură</label>
								<input type="hidden" id="poste_edit_factfourn_initial">							
								<input type="hidden" id="poste_edit_factfourn_activat" value="<?php echo implode(',',$allfactfourNONaffected);?>">
							<div class="col-sm-9">
							<?php //var_dump($allfactfournaffected); ?>
								<div class="row">
									<select class="js-example-basic-multiple col-sm-12" id="poste_edit_factfourn" multiple="multiple">
										<?php foreach ($allfactfourn as $key => $factfourn) : ?>
											<option value="<?php echo $factfourn->rowid; ?>" <?php echo (!in_array($factfourn->rowid,$allfactfourNONaffected))?' disabled="true" ':'' ?> ><?php echo $factfourn->ref; ?>&nbsp;<?php echo $factfourn->nom; ?></option>		          					
			          					<?php endforeach; ?>
									</select>

									<div class="col-sm-12">
										<div class="input-group">
									         <div class="input-group-addon">€</div>
									      <input type="text" class="form-control" id="edit_poste_cost_fourn" disabled="true">
									    </div>
									</div>

								</div>


							</div>
						</div>

						<div class="row">
							<div class="col-sm-12">
								<table class="table table-small">
								<thead>
										<tr>
											<th width="30%">Cost estimat</th>
											<th width="30%">Cost calculat</th>
											<th>Vânzare</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td ><span id="TD_edit_poste_price">0</span> €</td>
											<td ><span id="TD_edit_poste_price_calculated">0</span> €</td>
											<td >
												<div class="input-group">
											        <div class="input-group-addon">€</div>
											      	<input type="text" class="form-control" id="edit_poste_pv" >
											    </div>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>	

					</div>	
				</div>

				<br />

				<div class="row">
					<div class="col-md-12">
						<div class="form-group">
							<label class="col-sm-3 control-label" for="edit_estimated_progress_poste" required="">Evoluție estimată</label>
							<div class="col-sm-8">
								<div class="progress">
								  	<div id="edit_estimated_progress_poste" class="progress-bar " role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
								    	<span>0</span>%
								  	</div>
								</div>

							</div>
						</div>						
						<br />
						<div class="form-group">
							<label class="col-sm-3 control-label" for="edit_declared_progress_poste" required="">Evoluție reală</label>
							<div class="col-sm-8">
								<input id="edit_declared_progress_poste" data-slider-value="0">
							</div>
						</div>

					</div>
				</div>
				
				<br />
				<div class="row">
					<div class="col-md-12">
						<div class="form-group">
							<label class="col-sm-2 control-label">Descriere</label>
							<div class="col-sm-10">
								<textarea class="form-control" name="edit_description_poste" id="edit_description_poste" rows="3"></textarea>
							</div>	
						</div>
					</div>
				</div>

				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-success" id="bt_edit_poste">Înregistreză</button>
				<button type="button" class="btn btn-default" data-dismiss="modal">Închide</button>
				<button type="button" class="btn btn-danger pull-left" id="bt_delete_poste">Șterge</button>
			</div>
		</div>
	</div>
</div>



<!--
//**************************************************************************************************************
//
//
// MODAL Edit SUBPOSTE
// 
// 
//**************************************************************************************************************
-->							
<div class="modal fade" id="edit_subposte_Modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
	    <div class="modal-header">
        	<h4 class="modal-title" id="myModalLabel">Editează Subtask
        		<div class="pull-right" id="edit_header_subposte_code">
       					Ref : <span></span>
       			</div>
        	</h4>
	    </div>
		<div class="modal-body">
				<form class="form-horizontal">	
				<div class="row">
					<div class="col-md-6">
						<input type="hidden" id="edit_id_subposte">	
						<input type="hidden" id="edit_code_subposte">
						<div class="form-group">
							<label class="col-sm-3 control-label" for="label_subposte">Nume *</label>
							<div class="col-sm-9">
								<input type="text" name="label_subposte" id="edit_label_subposte" class="form-control required" required="">
							</div>	
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">Task asociat *</label>
							<div class="col-sm-9">
								<select name="child_subposte" class="form-control required" id="edit_child_subposte"  required="" disabled="">
									<option value="">Asociază unui Task</option>
		          					<?php foreach ($projectTree['tree'] as $key => $lot) : ?>
		          						<?php foreach ($lot->categories as $key => $categorie) : ?>
										   <?php foreach ($categorie->postes as $key => $poste) : ?>			
												<option name="child_subposte" value="<?php echo $poste->rowid ;?>"><?php echo $lot->label; ?>--><?php echo $categorie->label; ?>--><?php echo $poste->label; ?></option>
											<?php endforeach; ?>
										<?php endforeach; ?>
		          					<?php endforeach; ?>
								</select>
							</div>
						</div>	

						<div class="form-group">
							<label class="col-sm-3 control-label">Pilote(s) </label>
							<input type="hidden" id="subposte_edit_executive_initial">
							<div class="col-sm-9">
								<div class="row">
								<select class="js-example-basic-multiple col-sm-12" id="subposte_edit_executive" multiple="multiple" readonly="true" disabled="true">
								  	<?php foreach ($allcontacts as $key => $contact) : ?>
										<option value="<?php echo $contact->rowid; ?>"><?php echo $contact->lastname; ?>&nbsp;<?php echo $contact->firstname ?></option>
		          					<?php endforeach; ?>
								</select>
								</div>
							</div>
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label">Intervenant(s) </label>
							<input type="hidden" id="subposte_edit_contributor_initial">						
							<div class="col-sm-9">
								<div class="row">
								<select class="js-example-basic-multiple col-sm-12" id="subposte_edit_contributor" multiple="multiple" readonly="true" disabled="true">
								  	<?php foreach ($allcontacts as $key => $contact) : ?>
										<option value="<?php echo $contact->rowid; ?>"><?php echo $contact->lastname; ?>&nbsp;<?php echo $contact->firstname ?></option>
		          					<?php endforeach; ?>
								</select>
								</div>
							</div>
						</div>

						<div class="form-group ">
							<label class="col-sm-3 control-label" for="" required="" >Data</label>
							<div class="col-sm-9">
								<div class="input-group">
								<input class="col-sm-6" name id="edit_startDate_subposte" placeholder="De la" value="" >
								<input class="col-sm-6" id="edit_endDate_subposte" placeholder="Pănă la" value="" >
								</div>
							</div>	
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label" for="plannedWork_subposte" required="">Durată sarcină *</label>
							<div class="col-sm-4">
								<div>&nbsp;&nbsp;Ore</div>
								<input class="col-sm-8" disabled="true" id="edit_subposte_charge_preveu">
							</div> 
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-group">
							<label class="col-sm-3 control-label">Cost estimat</label>
							<div class="col-sm-9">
								<div class="input-group">
							        <div class="input-group-addon">€</div>
							      	<input type="text" class="form-control" id="edit_subposte_price" disabled="true">
							    </div><!-- /input-group -->
							</div>	
						</div>
						
						<div class="form-group">
							<label class="col-sm-3 control-label">Cheltuieli Salariale</label>
							<div class="col-sm-9">
								<div class="input-group">
							        <div class="input-group-addon">€</div>
							      	<input type="text" class="form-control" id="edit_subposte_cost_mo" disabled="true">
							    </div>
							</div>	
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label">Factura furnizorilor în legătură</label>
							<input type="hidden" id="sousposte_edit_factfourn_initial">							
							<input type="hidden" id="sousposte_edit_factfourn_activat" value="<?php echo implode(',',$allfactfourNONaffected);?>">
							<div class="col-sm-9">
							<?php //var_dump($allfactfournaffected); ?>
								<div class="row">
								<select class="js-example-basic-multiple col-sm-12" id="sousposte_edit_factfourn" multiple="multiple" disabled="true">
									<?php foreach ($allfactfourn as $key => $factfourn) : ?>
										<option value="<?php echo $factfourn->rowid; ?>" <?php echo (!in_array($factfourn->rowid,$allfactfourNONaffected))?' disabled="true" ':'' ?> ><?php echo $factfourn->ref; ?>&nbsp;<?php echo $factfourn->nom; ?></option>		          					
		          					<?php endforeach; ?>
								</select>
								<div class="col-sm-12">
									<div class="input-group">
								         <div class="input-group-addon">€</div>
								      <input type="text" class="form-control" id="edit_subposte_cost_fourn" disabled="true">
								    </div>
								</div>								
								</div>
							</div>
						</div>


						<div class="row">
							<div class="col-sm-3">
							</div>
							<div class="col-sm-12">
								<table class="table table-small">
								<thead>
										<tr>
											<th width="40%">Cantitate/[U.M.]</th>
											<th width="30%">Cost total</th>
											<th width="30%">Vănzare</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td>
											    <div class="input-group">
												    <input placeholder="Quantité" id="sousposte_edit_unite" name="sousposte_edit_unite" class="form-control" >
												    <!-- insert this line -->												  
												    <select name="sousposte_edit_select_unite" id="sousposte_edit_select_unite" class="form-control" >
												        <option value="">[U.M.]</option>
										      			<?php  foreach($allunites as $unite): ?>
										        			<option value="<?php print $unite->short_label ?>"><?php print $unite->short_label; ?></option>
										        		<?php endforeach; ?>
									        		</select>
												</div>
											</td>
											<td ><span id="TD_edit_subposte_price">0</span> €</td>
											<td >
												<div class="input-group">
											        <div class="input-group-addon">€</div>
											      	<input type="text" class="form-control" id="edit_subposte_pv" >
											    </div>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>	


					</div>
				</div>	
				<div class="row">
					<div class="col-md-12">
					</div>	
				</div>

				<div class="row">
					<div class="col-md-12">
						<div class="form-group">
							<label class="col-sm-2 control-label">Descriere</label>
							<div class="col-sm-10">
								<textarea class="form-control" name="desc_subposte" id="edit_desc_subposte" rows="3"></textarea>
							</div>	
						</div>	
					</div>	
				</div>						
				</form>
		</div>		
		<div class="modal-footer">
			<button type="button" class="btn btn-success" id="bt_edit_subposte">Înregistreză</button>
			<button type="button" class="btn btn-default" data-dismiss="modal">Închide</button>
			<button type="button" class="btn btn-danger pull-left" id="bt_delete_subposte">Șterge</button>
		</div>
		</div>
	</div>
  </div>
</div>


<!--
//**************************************************************************************************************
//
//
// MODAL Edit SUBSUBPOSTE
// 
// 
//**************************************************************************************************************
-->							
<div class="modal fade" id="edit_subsubposte_Modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
	    <div class="modal-header">
	        <!-- <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button> -->
	        	<h4 class="modal-title" id="myModalLabel">Editează Subsubtask
	        	<div class="pull-right" id="edit_header_subsubposte_code">
	       					Ref : <span></span>
	       			</div>
	        	</h4>
	    </div>
		<div class="modal-body">
			<form class="form-horizontal">	
				<div class="row">
					<div class="col-md-6">				
						<input type="hidden" id="edit_id_subsubposte">	

						<div class="form-group">
							<label class="col-sm-3 control-label" for="label_subsubposte">Nume *</label>
							<div class="col-sm-9">
								<input type="text" name="label_subsubposte" id="edit_label_subsubposte" class="form-control required" required="">
							</div>	
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label">Subtask *</label>
							<div class="col-sm-9">
								<select name="child_subsubposte" class="form-control required" id="edit_child_subsubposte"  required="" disabled="">
									<option value="">Asociză unui Sutask existent</option>
			          					<?php foreach ($projectTree['tree'] as $key => $lot) : ?>
			          						<?php foreach ($lot->categories as $key => $categorie) : ?>
											   <?php foreach ($categorie->postes as $key => $poste) : ?>	
											   		<?php foreach ($poste->subpostes as $key => $subposte) : ?>		
													<option name="child_subsubposte" value="<?php echo $subposte->rowid ;?>"><?php echo $lot->label; ?>--><?php echo $categorie->label; ?>--><?php echo $poste->label; ?>--><?php echo $subposte->label; ?></option>
													<?php endforeach; ?>
												<?php endforeach; ?>
											<?php endforeach; ?>
			          					<?php endforeach; ?>
								</select>
							</div>
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label">Pilote(s) </label>
							<input type="hidden" id="subsubposte_edit_executive_initial">
							<div class="col-sm-9">
								<div class="row">
									<select class="js-example-basic-multiple col-sm-12" id="subsubposte_edit_executive" multiple="multiple" readonly="true" disabled="true">
									  	<?php foreach ($allcontacts as $key => $contact) : ?>
											<option value="<?php echo $contact->rowid; ?>"><?php echo $contact->lastname; ?>&nbsp;<?php echo $contact->firstname ?></option>
			          					<?php endforeach; ?>
									</select>
								</div>	
							</div>
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label">Intervenant(s) </label>
							<input type="hidden" id="subsubposte_edit_contributor_initial">
							<div class="col-sm-9">
								<div class="row">
								<select class="js-example-basic-multiple col-sm-12" id="subsubposte_edit_contributor" multiple="multiple" readonly="true" disabled="true">
								  	<?php foreach ($allcontacts as $key => $contact) : ?>
										<option value="<?php echo $contact->rowid; ?>"><?php echo $contact->lastname; ?>&nbsp;<?php echo $contact->firstname ?></option>
		          					<?php endforeach; ?>
								</select>
								</div>
							</div>
						</div>


						<div class="form-group ">
							<label class="col-sm-3 control-label" for="" required="">Data</label>
							<div class="col-sm-9">
								<div class="input-group">
								<input class="col-sm-6" id="edit_startDate_subsubposte" placeholder="De la" value="<?php // echo date("d/m/Y H:i:s"); ?>">
								<input class="col-sm-6" id="edit_endDate_subsubposte" placeholder="Pănă la" value="<?php // echo date("d/m/Y"); ?>">
								</div>
							</div>	
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label" for="plannedWork_subsubposte" required="">Durată sarcină * </label>
							<div class="col-sm-4">  
								<div>&nbsp;&nbsp;Ore</div>
								<input class="col-sm-8" disabled="true" disabled="true" id="edit_subsubposte_charge_preveu">
							</div> 

						</div>	
					</div>
					<div class="col-md-6">



						<div class="form-group">
							<label class="col-sm-3 control-label">Cost estimat</label>
							<div class="col-sm-9">
								<div class="input-group">
							        <div class="input-group-addon">€</div>
							      	<input type="text" class="form-control" id="edit_subsubposte_price" disabled="true">
							    </div><!-- /input-group -->
							</div>	
						</div>
						
						<div class="form-group">
							<label class="col-sm-3 control-label">Cheltuieli Salariale</label>
							<div class="col-sm-9">
								<div class="input-group">
							        <div class="input-group-addon">€</div>
							      	<input type="text" class="form-control" id="edit_subsubposte_cost_mo" disabled="true">
							    </div>
							</div>	
						</div>

						<div class="form-group">
							<label class="col-sm-3 control-label">Factura furnizorilor în legătură</label>
							<input type="hidden" id="soussousposte_edit_factfourn_initial">							
							<input type="hidden" id="soussousposte_edit_factfourn_activat" value="<?php echo implode(',',$allfactfourNONaffected);?>">
							<div class="col-sm-9">
							<?php //var_dump($allfactfournaffected); ?>
								<div class="row">
								<select class="js-example-basic-multiple col-sm-12" id="soussousposte_edit_factfourn" multiple="multiple" disabled="true">
									<?php foreach ($allfactfourn as $key => $factfourn) : ?>
										<option value="<?php echo $factfourn->rowid; ?>" <?php echo (!in_array($factfourn->rowid,$allfactfourNONaffected))?' disabled="true" ':'' ?> ><?php echo $factfourn->ref; ?>&nbsp;<?php echo $factfourn->nom; ?></option>		          					
		          					<?php endforeach; ?>
								</select>
								<div class="col-sm-12">
									<div class="input-group">
								         <div class="input-group-addon">€</div>
								      <input type="text" class="form-control" id="edit_subsubposte_cost_fourn" disabled="true">
								    </div>
								</div>								
								</div>
							</div>
						</div>


						<div class="row">
							<div class="col-sm-3">
							</div>
							<div class="col-sm-12">
								<table class="table table-small">
								<thead>
										<tr>
											<th width="20%">Cantitate/[U.M.]</th>
											<th width="30%">Cost total</th>
											<th width="40%">Vănzare</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td>
												<!-- <div class="form-group">
											      	<input placeholder="Unité" type="text" class="form-control" id="soussousposte_edit_unite" name="soussousposte_edit_unite" >
											    </div> -->
											    <div class="input-group">
												    <input placeholder="Quantité" id="soussousposte_edit_unite" name="soussousposte_edit_unite" class="form-control" >
												    <!-- insert this line -->												  
												    <select name="soussousposte_edit_select_unite" id="soussousposte_edit_select_unite" class="form-control" >
												        <option value="">[U.M.]</option>
										      			<?php  foreach($allunites as $unite): ?>
										        			<option value="<?php print $unite->short_label ?>"><?php print $unite->short_label; ?></option>
										        		<?php endforeach; ?>
									        		</select>
												</div>
											</td>
											<td ><span id="TD_edit_subsubposte_price">0</span> €</td>
											<td >
												<div class="input-group">
											        <div class="input-group-addon">€</div>
											      	<input type="text" class="form-control" id="edit_subsubposte_pv" >
											    </div>												
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>	

					</div>	
				</div>

				<div class="row">
					<div class="col-md-12">

					</div>
				</div>

				<div class="row">
					<div class="col-md-12">
						<div class="form-group">
							<label class="col-sm-2 control-label">Descriere</label>
							<div class="col-sm-10">
								<textarea class="form-control" name="desc_subsubposte" id="edit_desc_subsubposte" rows="3"></textarea>
							</div>	
						</div>
					</div>
				</div>
			</form>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-success" id="bt_edit_subsubposte">Înregistreză</button>
			<button type="button" class="btn btn-default" data-dismiss="modal">Închide</button>
			<button type="button" class="btn btn-danger pull-left" id="bt_delete_subsubposte">Șterge</button>
		</div>
	</div>
  </div>
</div>





<?php						
//**************************************************************************************************************
// var_dump($projectTree);
// var_dump($object);
llxFooter();

$db->close();
