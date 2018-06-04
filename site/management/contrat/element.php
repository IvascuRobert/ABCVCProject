<?php
/* Copyright (C) 2012-2016		Charlie BENKE		<charlie@patas-monkey.com>
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
 *      \file       management/contrat/element.php
 *      \ingroup    contrat
 *		\brief      Page of contrat referrers
 */
 

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");		// For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");	// For "custom" directory

require_once DOL_DOCUMENT_ROOT.'/core/lib/contract.lib.php';
require_once DOL_DOCUMENT_ROOT."/contrat/class/contrat.class.php";
require_once DOL_DOCUMENT_ROOT."/contact/class/contact.class.php";
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

dol_include_once("/management/class/managementfichinter.class.php");
dol_include_once("/management/core/lib/management.lib.php");
dol_include_once("/management/class/managementcontratterm.class.php");

$langs->load("contracts");
$langs->load("management@management");
if ($conf->ficheinter->enabled)	$langs->load("interventions");

$socid=GETPOST('socid','int');
$id=GETPOST('id','int');
$ref=GETPOST('ref','alpha');
$action=GETPOST('action','alpha');

if ($id == '' && $ref == '')
{
	dol_print_error('','Bad parameter');
	exit;
}

// Security check
$socid=0;
if ($user->societe_id > 0) $socid=$user->societe_id;
$result=restrictedArea($user,'contrat',$id);

if ($user->rights->ficheinter->creer && $action == 'addinter')
	Contract_Transfer_FichInter($db, $id);


/*
 *	View
 */

llxHeader("",$langs->trans("Referers"),"Contrat");

$form = new Form($db);

$userstatic=new User($db);

$objectterm = new Managementcontratterm($db);
$object		= new Contrat($db);

// attention le premier correspond l'ID de l'échéance (utilisé pour la facturation)
$result=$objectterm->fetch(0, $ref, $id );

// pour gérer la subtilité des échéances
//$tmpidterm=$object->id;
$object= new Contrat($db);
$object->id=$objectterm->fk_contrat;
$result=$object->fetch($id, $ref );
$ret=$object->fetch_thirdparty();
$head = contract_prepare_head($object);
//$object->id=$tmpidterm;

dol_fiche_head($head, 'referent', $langs->trans("Contract"), 0, 'contract');

$linkback = '<a href="'.DOL_URL_ROOT.'/contrat/list.php'.(! empty($socid)?'?socid='.$socid:'').'">'.$langs->trans("BackToList").'</a>';

if (DOL_VERSION >= "5.0.0") 
{
    $morehtmlref='';
    $morehtmlref.=$object->ref;

	$morehtmlref.='<div class="refidno">';
	// Ref customer
	$morehtmlref.=$form->editfieldkey("RefCustomer", 'ref_customer', $object->ref_customer, $object, 0, 'string', '', 0, 1);
	$morehtmlref.=$form->editfieldval("RefCustomer", 'ref_customer', $object->ref_customer, $object, 0, 'string', '', null, null, '', 1);
	// Ref supplier
	$morehtmlref.='<br>';
	$morehtmlref.=$form->editfieldkey("RefSupplier", 'ref_supplier', $object->ref_supplier, $object, 0, 'string', '', 0, 1);
	$morehtmlref.=$form->editfieldval("RefSupplier", 'ref_supplier', $object->ref_supplier, $object, 0, 'string', '', null, null, '', 1);
	// Thirdparty
    $morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . $object->thirdparty->getNomUrl(1);
    // Project
    if (! empty($conf->projet->enabled))
    {
    	require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';

        $langs->load("projects");
        $morehtmlref.='<br>'.$langs->trans('Project') . ' ';
        if ($user->rights->contrat->creer)
        {
            if ($action != 'classify')
                //$morehtmlref.='<a href="' . $_SERVER['PHP_SELF'] . '?action=classify&amp;id=' . $object->id . '">' . img_edit($langs->transnoentitiesnoconv('SetProject')) . '</a> : ';
                $morehtmlref.=' : ';
            	if ($action == 'classify') {
                    //$morehtmlref.=$form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->socid, $object->fk_project, 'projectid', 0, 0, 1, 1);
                    $morehtmlref.='<form method="post" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
                    $morehtmlref.='<input type="hidden" name="action" value="classin">';
                    $morehtmlref.='<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
                    $morehtmlref.=$formproject->select_projects($object->thirdparty->id, $object->fk_project, 'projectid', $maxlength, 0, 1, 0, 1, 0, 0, '', 1);
                    $morehtmlref.='<input type="submit" class="button valignmiddle" value="'.$langs->trans("Modify").'">';
                    $morehtmlref.='</form>';
                } else {
                    $morehtmlref.=$form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->thirdparty->id, $object->fk_project, 'none', 0, 0, 0, 1);
                }
        } else {
            if (! empty($object->fk_project)) {
                $proj = new Project($db);
                $proj->fetch($object->fk_project);
                $morehtmlref.='<a href="'.DOL_URL_ROOT.'/projet/card.php?id=' . $object->fk_project . '" title="' . $langs->trans('ShowProject') . '">';
                $morehtmlref.=$proj->ref;
                $morehtmlref.='</a>';
            } else {
                $morehtmlref.='';
            }
        }
    }
    $morehtmlref.='</div>';


    dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'none', $morehtmlref);

    print '<div class="underbanner clearboth"></div>';

}
else
{
	print '<table class="border" width="100%">';
	
	// Ref du contrat + dates
	print '<tr><td width="25%">'.$langs->trans("Ref").'</td><td colspan="3">';
	print $form->showrefnav($object, 'ref', $linkback, 1, 'ref', 'ref', '');
	print "</td></tr>";
	
	// Customer
	print "<tr><td>".$langs->trans("Customer")."</td>";
	print '<td colspan="3">'.$object->thirdparty->getNomUrl(1).'</td></tr>';
	
	// Ligne info remises tiers
	print '<tr><td>'.$langs->trans('Discount').'</td><td colspan="3">';
	if ($object->thirdparty->remise_client) print $langs->trans("CompanyHasRelativeDiscount",$object->thirdparty->remise_client);
	else print $langs->trans("CompanyHasNoRelativeDiscount");
	$absolute_discount=$object->thirdparty->getAvailableDiscounts();
	print '. ';
	if ($absolute_discount) print $langs->trans("CompanyHasAbsoluteDiscount",price($absolute_discount),$langs->trans("Currency".$conf->currency));
	else print $langs->trans("CompanyHasNoAbsoluteDiscount");
	print '.';
	print '</td></tr>';
	
	// Statut contrat
	print '<tr><td>'.$langs->trans("Status").'</td><td colspan="3">';
	
	if ($object->statut==1) 
		print $object->getLibStatut(4);
	else 
		print $object->getLibStatut(2);
	print "</td></tr>";
	
	// Projet
	if (! empty($conf->projet->enabled))
	{
		$langs->load("projects");
		print '<tr><td>';
		print '<table width="100%" class="nobordernopadding"><tr><td>';
		print $langs->trans("Project");
		print '</td>';
		if ($action != "classify" && $user->rights->projet->creer) print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=classify&amp;id='.$object->id.'">'.img_edit($langs->trans("SetProject")).'</a></td>';
		print '</tr></table>';
		print '</td><td colspan="3">';
		if ($action == "classify")
			$form->form_project($_SERVER['PHP_SELF'].'?id='.$object->id,$object->socid,$object->fk_project,"projectid");
		else
			$form->form_project($_SERVER['PHP_SELF'].'?id='.$object->id,$object->socid,$object->fk_project,"none");
		print "</td></tr>";
	}
	print "</table>";

}
//print '</div>';
	print '<br>';

/*
 * Referers types
 */

$title=$langs->trans("ListInterventionalLinkToContract");
$classname='Managementfichinter';
if ($conf->ficheinter->enabled)
{
	// on récupère la totalité des inters
	$allinterarray = $objectterm->get_element_list("", "");
	
	$termsarray = $objectterm->get_terms_list();

	if (count($termsarray ) ==0 && is_array($termsarray ))
	{
		$termsarray=array(0=>array(
		'rowid'=>0,
		'datedeb'=>'',
		'dateend'=>'',
		'note'=>$title,
		'fk_status'=>1));
	}

	foreach ($termsarray as $key => $value)
	{
		$idterm=$value['rowid'];
		if ($value['datedeb'])
			print_titre($value['note'].' du '.dol_print_date($value['datedeb'],'day').' au '.dol_print_date($value['dateend'],'day'));
		else
			print_titre($value['note']);
		print '<table class="noborder" width="100%">';
		print '<tr class="liste_titre">';
		print '<td width="100">'.$langs->trans("Ref").'</td>';
		print '<td width="100">'.$langs->trans("description").'</td>';
		print '<td width="100" align="center">'.$langs->trans("Date").'</td>';
		print '<td>'.$langs->trans("ThirdParty").'</td>';
		print '<td align="right" width="120">'.$langs->trans("Nb Hrs").'</td>';
		print '<td align="right" width="120">'.$langs->trans("AmountHT").'</td>';
		print '<td align="right" width="200">'.$langs->trans("Status").'</td>';
		print '</tr>';
		
		$elementarray = $objectterm->get_element_list($value['datedeb'], $value['dateend']);
		// suppression de la liste de toute les inters
		if (is_array($elementarray))
			$allinterarray = array_merge(array_diff($allinterarray, $elementarray ));

		$genBill = false;

		if (count($elementarray)>0 && is_array($elementarray))
		{
			$var=true;
			$total_ht = 0;
			$total_ttc = 0;
			$num=count($elementarray);
			
			for ($i = 0; $i < $num; $i++)
			{
				$element = new $classname($db);
				$element->fetch($elementarray[$i]);
				$element->fetch_thirdparty();
				$var=!$var;
				print "<tr $bc[$var]>";
	
				// Ref
				print '<td align="left">';
				print $element->getNomUrl(1);
				print "</td>\n";
	
				// Status
				print '<td align="left">'.$element->description.'</td>';
	
				// Date
				$date=$element->date;
				if (empty($date)) $date=$element->datep;
				if (empty($date)) $date=$element->dateo;
				if (empty($date)) $date=$element->date_contrat;
				print '<td align="center">'.dol_print_date($date,'day').'</td>';
	
				// Third party
				print '<td align="left">';
				if (is_object($element->client)) print $element->client->getNomUrl(1,'',48);
				print '</td>';
	
				// Durée
				print '<td align="right">'.(isset($element->duree)?convertSecondToTime($element->duree,'allhourmin'):'&nbsp;').'</td>';
	
				// Amount
				print '<td align="right">'.(isset($element->total_ht)?price($element->total_ht):'&nbsp;').'</td>';
	
				// Status
				print '<td align="right">'.$element->getLibStatut(5).'</td>';
				if ($element->statut > 2 )
					$genBill = true;
				print '</tr>';
	
				$total_hrs = $total_hrs + $element->duree;
				$total_ht = $total_ht + $element->total_ht;
			}
	
			print '<tr class="liste_total"><td colspan="4">'.$langs->trans("Number").': '.$i.'</td>';
			print '<td align="right" width="100">'.$langs->trans("TotalHrs").' : '.convertSecondToTime($total_hrs, 'allhourmin').'</td>';
			print '<td align="right" width="100">'.$langs->trans("TotalHT").' : '.price($total_ht).'</td>';
			print '<td>&nbsp;</td>';
			print '</tr>';
		}
		print "</table>";

		// si il y a des inters à facturer et 
		if ($user->rights->facture->creer ) 
		{
			print '<div class="tabsAction">';
			$objectelement="management_managementcontratterm";
			if( $genBill)
				print '<a class="butAction" href="'.DOL_URL_ROOT.'/compta/facture.php?action=create&amp;origin='.$objectelement.'&amp;originid='.$idterm.'&amp;socid='.$object->socid.'">'.$langs->trans("CreateBillPeriod").'</a>';
			else
				print '<a class="butActionRefused" href="#">'.$langs->trans("CreateBillPeriod").'</a>';
			
			print '</div>';
		}
		else
			print '<br>';
	}

	if (count($allinterarray ) >0 )
	{
		print_titre($langs->trans("InterOutofTerms"));
		// on traite les inters orphelines
		print '<table class="noborder" width="100%">';
		print '<tr class="liste_titre">';
		print '<td width="100">'.$langs->trans("Ref").'</td>';
		print '<td width="100">'.$langs->trans("description").'</td>';
		print '<td width="100" align="center">'.$langs->trans("Date").'</td>';
		print '<td>'.$langs->trans("ThirdParty").'</td>';
		print '<td align="right" width="120">'.$langs->trans("Nb Hrs").'</td>';
		print '<td align="right" width="120">'.$langs->trans("AmountHT").'</td>';
		print '<td align="right" width="200">'.$langs->trans("Status").'</td>';
		print '</tr>';
		$var=true;
		$total_ht = 0;
		$total_hrs=0;
		$total_ttc = 0;
		$num=count($allinterarray);
		for ($i = 0; $i < $num; $i++)
		{
			$element = new $classname($db);
			$element->fetch($allinterarray[$i]);
			$element->fetch_thirdparty();
			
			
			$var=!$var;
			print "<tr $bc[$var]>";

			// Ref
			print '<td align="left">';
			print $element->getNomUrl(1);
			print "</td>\n";

			// Status
			print '<td align="left">'.$element->description.'</td>';

			// Date
			$date=$element->date;
			if (empty($date)) $date=$element->datep;
			if (empty($date)) $date=$element->dateo;
			if (empty($date)) $date=$element->date_contrat;
			print '<td align="center">'.dol_print_date($date,'day').'</td>';

			// Third party
			print '<td align="left">';
			if (is_object($element->client)) print $element->client->getNomUrl(1,'',48);
			print '</td>';

			// Durée
			print '<td align="right">'.(isset($element->duree)?convertSecondToTime($element->duree, 'allhourmin'):'&nbsp;').'</td>';

			// Amount
			print '<td align="right">'.(isset($element->total_ht)?price($element->total_ht):'&nbsp;').'</td>';

			// Status
			print '<td align="right">'.$element->getLibStatut(5).'</td>';

			print '</tr>';

			$total_hrs = $total_hrs + $element->duree;
			$total_ht = $total_ht + $element->total_ht;
		}

		print '<tr class="liste_total"><td colspan="4">'.$langs->trans("Number").': '.$i.'</td>';
		print '<td align="right" width="100">'.($total_hrs ? $langs->trans("TotalHrs").' : '.convertSecondToTime($total_hrs, 'allhourmin'):'').'</td>';
		print '<td align="right" width="100">'.($total_ht?$langs->trans("TotalHT").' : '.price($total_ht):'').'</td>';
		print '<td>&nbsp;</td>';
		print '</tr>';

		print "</table>";
	}


	/*
	 * Barre d'action
	 */
	print '<div class="tabsAction">';

	if ($object->statut > 0)
	{
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&amp;action=addinter">'.$langs->trans("AddInter").'</a>';	
	}
	print '</div>';
}

llxFooter();
$db->close();
?>