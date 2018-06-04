<?php
/* Copyright (C) 2004      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2005 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *      \file       htdocs/compta/facture/info.php
 *      \ingroup    facture
 *		\brief      Page des informations d'une facture
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/discount.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/invoice.lib.php';

$langs->load("companies");
$langs->load("bills");

$id = GETPOST("facid","int");
$ref=GETPOST("ref",'alpha');


/*
 * View
 */

$title = $langs->trans('InvoiceCustomer') . " - " . $langs->trans('Info');
$helpurl = "EN:Customers_Invoices|FR:Factures_Clients|ES:Facturas_a_clientes";
llxHeader('', $title, $helpurl);

$object = new Facture($db);
$object->fetch($id, $ref);
$object->fetch_thirdparty();

$object->info($object->id);

$head = $object->abcvc_facture_prepare_head($object);
dol_fiche_head($head, 'info', $langs->trans("InvoiceCustomer"), 0, 'bill');

$totalpaye = $object->getSommePaiement();

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

print '<br>';

print '<table width="100%"><tr><td>';
dol_print_object_info($object);
print '</td></tr></table>';

print '</div>';

dol_fiche_end();

llxFooter();
$db->close();
