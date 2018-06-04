<?php
/* Copyright (C) 2005      Patrick Rouillon     <patrick@rouillon.net>
 * Copyright (C) 2005-2009 Destailleur Laurent  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2011-2015 Philippe Grand       <philippe.grand@atoo-net.com>
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
 *       \file       htdocs/compta/facture/contact.php
 *       \ingroup    facture
 *       \brief      Onglet de gestion des contacts des factures
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/discount.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/invoice.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';

$langs->load("bills");
$langs->load("companies");

$id     = (GETPOST('id')?GETPOST('id','int'):GETPOST('facid','int'));  // For backward compatibility
$ref    = GETPOST('ref','alpha');
$lineid = GETPOST('lineid','int');
$socid  = GETPOST('socid','int');
$action = GETPOST('action','alpha');

// Security check
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'facture', $id);

$object = new Facture($db);


/*
 * Add a new contact
 */

if ($action == 'addcontact' && $user->rights->facture->creer)
{
	$result = $object->fetch($id);

    if ($result > 0 && $id > 0)
    {
    	$contactid = (GETPOST('userid') ? GETPOST('userid','int') : GETPOST('contactid','int'));
  		$result = $object->add_contact($contactid, $_POST["type"], $_POST["source"]);
    }

	if ($result >= 0)
	{
		header("Location: ".$_SERVER['PHP_SELF']."?id=".$object->id);
		exit;
	}
	else
	{
		if ($object->error == 'DB_ERROR_RECORD_ALREADY_EXISTS')
		{
			$langs->load("errors");
			setEventMessages($langs->trans("ErrorThisContactIsAlreadyDefinedAsThisType"), null, 'errors');
		}
		else
		{
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
}

// Toggle the status of a contact
else if ($action == 'swapstatut' && $user->rights->facture->creer)
{
	if ($object->fetch($id))
	{
	    $result=$object->swapContactStatus(GETPOST('ligne'));
	}
	else
	{
		dol_print_error($db);
	}
}

// Deletes a contact
else if ($action == 'deletecontact' && $user->rights->facture->creer)
{
	$object->fetch($id);
	$result = $object->delete_contact($lineid);

	if ($result >= 0)
	{
		header("Location: ".$_SERVER['PHP_SELF']."?id=".$object->id);
		exit;
	}
	else {
		dol_print_error($db);
	}
}


/*
 * View
 */

$title = $langs->trans('InvoiceCustomer') . " - " . $langs->trans('ContactsAddresses');
$helpurl = "EN:Customers_Invoices|FR:Factures_Clients|ES:Facturas_a_clientes";
llxHeader('', $title, $helpurl);

$form = new Form($db);
$formcompany = new FormCompany($db);
$contactstatic=new Contact($db);
$userstatic=new User($db);


/* *************************************************************************** */
/*                                                                             */
/* View and edit mode                                                         */
/*                                                                             */
/* *************************************************************************** */

if ($id > 0 || ! empty($ref))
{
	if ($object->fetch($id, $ref) > 0)
	{
		$object->fetch_thirdparty();

		$head = $object->abcvc_facture_prepare_head($object);

		$totalpaye = $object->getSommePaiement();
		
		dol_fiche_head($head, 'contact', $langs->trans('InvoiceCustomer'), 0, 'bill');

		// Invoice content
		
		$linkback = '<a href="' . DOL_URL_ROOT . SUPP_PATH.'/compta/facture/list.php' . (! empty($socid) ? '?socid=' . $socid : '') . '">' . $langs->trans("BackToList") . '</a>';
	
		$morehtmlref='<div class="refidno">';
		// Ref customer
		$morehtmlref.=$form->editfieldkey("RefCustomer", 'ref_client', $object->ref, $object, 0, 'string', '', 0, 1);
		$morehtmlref.=$form->editfieldval("RefCustomer", 'ref_client', $object->ref, $object, 0, 'string', '', null, null, '', 1);
		// Thirdparty
		$morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . $object->thirdparty->getNomUrl(1);
		// Project
	    if (! empty($conf->projet->enabled))
	    {
	        $langs->load("projects");
	        $morehtmlref.='<br>'.$langs->trans('Project') . ' ';
	        if ($user->rights->fournisseur->commande->creer)
	        {
	            if ($action != 'classify')
	                $morehtmlref.='<a href="' . $_SERVER['PHP_SELF'] . '?action=classify&amp;id=' . $object->id . '">' . img_edit($langs->transnoentitiesnoconv('SetProject')) . '</a> : ';
	                if ($action == 'classify') {
	                    //ABCVC OVERRIDE
	                    //
	                    //$morehtmlref.=$form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->socid, $object->fk_project, 'projectid', 0, 0, 1, 1);
	                    $morehtmlref.='<form method="post" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
	                    $morehtmlref.='<input type="hidden" name="action" value="modify_project">';
	                    // $morehtmlref.='<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	                    // $morehtmlref.=$formproject->select_projects((empty($conf->global->PROJECT_CAN_ALWAYS_LINK_TO_ALL_SUPPLIERS)?$object->socid:-1), $object->fk_project, 'projectid', $maxlength, 0, 1, 0, 1, 0, 0, '', 1);
	                    $morehtmlref.='<select name="update_project_poste">';
	                    $morehtmlref.='<option value="null_null">Libre</option>';
	                    $morehtmlref.= $formproject->select_post_afected_by_project_list((empty($conf->global->PROJECT_CAN_ALWAYS_LINK_TO_ALL_SUPPLIERS)?$societe->id:-1), $projectid);
	                    $morehtmlref.='</select>';
	                    $morehtmlref.='<input type="submit" class="button valignmiddle" value="'.$langs->trans("Modify").'">';
	                    $morehtmlref.='</form>';
	                } else {
	                    //ABCVC OVERRIDE
	                    // var_dump($object);
	                    $morehtmlref.=$form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->cond_reglement, $object->socid, $object->fk_project, 'none', 0, 0, 0, 1);
	                    if($object->project_name == '' && $object->label_poste == '')
	                    {
	                        $morehtmlref.='Cette facture n est attribuée à aucun poste ou projet';
	                    }else{
	                        $morehtmlref.=$object->project_name .', poste affecté: ' .  $object->label_poste;
	                    }
	                }
	        } else {
	            if (! empty($object->fk_project)) {
	                $proj = new Project($db);
	                $proj->fetch($object->fk_project);
	                $morehtmlref.='<a href="'.DOL_URL_ROOT.SUPP_PATH.'/projet/card.php?id=' . $object->fk_project . '" title="' . $langs->trans('ShowProject') . '">';
	                $morehtmlref.=$proj->ref;
	                $morehtmlref.='</a>';
	            } else {
	                $morehtmlref.='';
	            }
	        }
	    }
		$morehtmlref.='</div>';
	
		$object->totalpaye = $totalpaye;   // To give a chance to dol_banner_tab to use already paid amount to show correct status
	
		dol_banner_tab($object, 'ref', $linkback, 1, 'facnumber', 'ref', $morehtmlref, '', 0, '', '', 1);
		
		dol_fiche_end();

		print '<br>';

		// Contacts lines (modules that overwrite templates must declare this into descriptor)
		$dirtpls=array_merge($conf->modules_parts['tpl'],array('/core/tpl'));
		foreach($dirtpls as $reldir)
		{
		    $res=@include dol_buildpath($reldir.'/contacts.tpl.php');
		    if ($res) break;
		}

	}
	else
	{
		// Record not found
		print "ErrorRecordNotFound";
	}
}


llxFooter();
$db->close();
