<?php
/* Copyright (C) 2003-2007 Rodolphe Quiedeville  <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2016 Laurent Destailleur   <eldy@users.sourceforge.net>
 * Copyright (C) 2005      Marc Barilley / Ocebo <marc@ocebo.com>
 * Copyright (C) 2005-2011 Regis Houssin         <regis.houssin@capnetworks.com>
 * Copyright (C) 2013      Cédric Salvador       <csalvador@gpcsolutions.fr>
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
 *	\file       htdocs/compta/facture/document.php
 *	\ingroup    facture
 *	\brief      Page for attached files on invoices
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/discount.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/invoice.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

$langs->load('propal');
$langs->load('compta');
$langs->load('other');
$langs->load("bills");
$langs->load('companies');


$id=(GETPOST('id','int')?GETPOST('id','int'):GETPOST('facid','int'));  // For backward compatibility
$ref=GETPOST('ref','alpha');
$socid=GETPOST('socid','int');
$action=GETPOST('action','alpha');
$confirm=GETPOST('confirm', 'alpha');

// Security check
if ($user->societe_id)
{
	$action='';
	$socid = $user->societe_id;
}
$result=restrictedArea($user,'facture',$id,'');

// Get parameters
$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
$page = GETPOST("page",'int');
if ($page == -1) { $page = 0; }
$offset = $conf->liste_limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortorder) $sortorder="ASC";
if (! $sortfield) $sortfield="name";

$object = new Facture($db);
if ($object->fetch($id))
{
	$object->fetch_thirdparty();
	$upload_dir = $conf->facture->dir_output . "/" . dol_sanitizeFileName($object->ref);
}

/*
 * Actions
 */
include_once DOL_DOCUMENT_ROOT . '/core/actions_linkedfiles.inc.php';


/*
 * View
 */

$title = $langs->trans('InvoiceCustomer') . " - " . $langs->trans('Documents');
$helpurl = "EN:Customers_Invoices|FR:Factures_Clients|ES:Facturas_a_clientes";
llxHeader('', $title, $helpurl);

$form = new Form($db);

if ($id > 0 || ! empty($ref))
{
	if ($object->fetch($id,$ref) > 0)
	{
		$object->fetch_thirdparty();

		$upload_dir = $conf->facture->dir_output.'/'.dol_sanitizeFileName($object->ref);

		$head = $object->abcvc_facture_prepare_head($object);
		dol_fiche_head($head, 'documents', $langs->trans('InvoiceCustomer'), 0, 'bill');

    	$totalpaye = $object->getSommePaiement();
		
		// Construit liste des fichiers
		$filearray=dol_dir_list($upload_dir,"files",0,'','(\.meta|_preview\.png)$',$sortfield,(strtolower($sortorder)=='desc'?SORT_DESC:SORT_ASC),1);
		$totalsize=0;
		foreach($filearray as $key => $file)
		{
			$totalsize+=$file['size'];
		}

	
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
	
	    dol_banner_tab($object, 'ref', $linkback, 1, 'facnumber', 'ref', $morehtmlref, '', 0);

		print '<div class="fichecenter">';
		print '<div class="underbanner clearboth"></div>';
	    
		print '<table class="border" width="100%">';

		print '<tr><td class="titlefield">'.$langs->trans("NbOfAttachedFiles").'</td><td colspan="3">'.count($filearray).'</td></tr>';
		print '<tr><td>'.$langs->trans("TotalSizeOfAttachedFiles").'</td><td colspan="3">'.$totalsize.' '.$langs->trans("bytes").'</td></tr>';
		print "</table>\n";

		print "</div>\n";

		dol_fiche_end();

		$modulepart = 'facture';
		$permission = $user->rights->facture->creer;
		$permtoedit = $user->rights->facture->creer;
		$param = '&id=' . $object->id;
		include_once DOL_DOCUMENT_ROOT . '/core/tpl/document_actions_post_headers.tpl.php';
	}
	else
	{
		dol_print_error($db);
	}
}
else
{
	print $langs->trans("ErrorUnknown");
}

$db->close();

llxFooter();
