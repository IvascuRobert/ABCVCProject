<?php
/* Copyright (C) 2013-2017		Charlie BENKE		<charlie@patas-monkey.com>
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
 *       \file       management/contrat/term.php
 *       \ingroup    management
 *       \brief      Page of a contract
 */

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");		// For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");	// For "custom" directory

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/contract.lib.php';
require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/modules/contract/modules_contract.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
if (! empty($conf->produit->enabled) || ! empty($conf->service->enabled))  
	require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
if (! empty($conf->propal->enabled))  
	require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
if (! empty($conf->projet->enabled)) 
{
	require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
}

dol_include_once ("/management/class/managementcontratterm.class.php");

$langs->load("contracts");
$langs->load("orders");
$langs->load("companies");
$langs->load("bills");
$langs->load("products");

$action=GETPOST('action','alpha');
$confirm=GETPOST('confirm','alpha');
$socid = GETPOST('socid','int');
$id = GETPOST('id','int');
$ref=GETPOST('ref','alpha');

$datecontrat='';

// Security check
if ($user->societe_id) $socid=$user->societe_id;
$result=restrictedArea($user,'contrat',$id);

$usehm=(! empty($conf->global->MAIN_USE_HOURMIN_IN_DATE_RANGE)?$conf->global->MAIN_USE_HOURMIN_IN_DATE_RANGE:0);

// Initialize technical object to manage hooks of thirdparties. Note that conf->hooks_modules contains array array
$hookmanager->initHooks(array('contractcard'));

$object = new Managementcontratterm($db);

$result=$object->fetch(0, $ref, $id);

/*
 * Actions
 */
if ($user->rights->contrat->creer && $action == 'validate')
{
	$object->validateTerm(GETPOST("line"));
}
else if ($user->rights->contrat->creer && $action == 'delete')
{
	$object->deleteTerm(GETPOST("line"));
}
else if ($user->rights->contrat->creer && $action == 'closeterm')
{
	$object->closeTerm(GETPOST("line"));
}
else if ($user->rights->contrat->creer && $action == 'updateterm')
{
	$error=0;

	// Subscription informations
	$datesubbegin=0;
	$datesubend=0;
	if ($_POST["debyear"] && $_POST["debmonth"] && $_POST["debday"])
		$datesubbegin=dol_mktime(0, 0, 0, $_POST["debmonth"], $_POST["debday"], $_POST["debyear"]);

	if ($_POST["endyear"] && $_POST["endmonth"] && $_POST["endday"])
		$datesubend=dol_mktime(0, 0, 0, $_POST["endmonth"], $_POST["endday"], $_POST["endyear"]);


	// Check parameters
	if (! $datesubbegin)
	{
		$error++;
		$langs->load("errors");
		$action='addnewterm';
	}
	if (! $datesubend)
	{
		$error++;
		$langs->load("errors");
		$action='addnewterm';
	}

	$note=GETPOST("note");

	if (! $error )
	{
		$db->begin();

		// Create subscription
		$crowid=$object->updateTerm(GETPOST("line"), $datesubbegin, $datesubend, $cotisation, $note);
		if ($crowid <= 0)
		{
			$error++;
			setEventMessages($object->error, $object->errors, 'errors');
		}

		if (! $error)
		{
			$db->commit();
			$action=='';
		}
		else
		{
			$db->rollback();
			$action = 'editterm';
		}
	}
	
}
else if ($user->rights->contrat->creer && $action == 'newterm' && ! $_POST["cancel"])
{
	$error=0;
	// Subscription informations
	$datesubbegin=0;
	$datesubend=0;
	if ($_POST["debyear"] && $_POST["debmonth"] && $_POST["debday"])
		$datesubbegin=dol_mktime(0, 0, 0, $_POST["debmonth"], $_POST["debday"], $_POST["debyear"]);

	if ($_POST["endyear"] && $_POST["endmonth"] && $_POST["endday"])
		$datesubend=dol_mktime(0, 0, 0, $_POST["endmonth"], $_POST["endday"], $_POST["endyear"]);


	// Check parameters
	if (! $datesubbegin)
	{
		$error++;
		$langs->load("errors");
		$errmsg=$langs->trans("NoDateDeb");
		$action='addnewterm';
	}
	if (! $datesubend)
	{
		$error++;
		$langs->load("errors");
		$action='addnewterm';
	}

	$note=GETPOST("note");

	if (! $error )
	{
		$db->begin();

		// Create subscription
		$crowid=$object->addterm($datesubbegin, $datesubend, $cotisation, $note);
		if ($crowid <= 0)
		{
			$error++;
			setEventMessages($object->error, $object->errors, 'errors');
		}

		if (! $error)
		{
			$db->commit();
			$action=='';
		}
		else
		{
			$db->rollback();
			$action = 'addnewterm';
		}
	}
}



/*
 * View
 */

llxHeader('',$langs->trans("ContractCard"),"Contrat");

$form = new Form($db);


/* *************************************************************************** */
/*                                                                             */
/* Mode vue et edition                                                         */
/*                                                                             */
/* *************************************************************************** */

$now=dol_now();

if ($id > 0 || ! empty($ref))
{
	//$result=$object->fetch($id,$ref);
	if ($result > 0)
	{
	    $result=$object->fetch_lines();
	}
	if ($result < 0)
	{
	    dol_print_error($db,$object->error);
	    exit;
	}

	dol_htmloutput_errors($mesg,'');

	$object->fetch_thirdparty();
	
	$nbofservices=count($object->lines);
	
	$author = new User($db);
	$author->fetch($object->user_author_id);
	
	$commercial_signature = new User($db);
	$commercial_signature->fetch($object->commercial_signature_id);
	
	$commercial_suivi = new User($db);
	$commercial_suivi->fetch($object->commercial_suivi_id);
		
	// pour gérer la subtilité des échéances
	$tmpidterm=$object->id;
	$object->id=$object->fk_contrat;
	$head = contract_prepare_head($object);
	$object->id=$tmpidterm;


	/*
	 *   View 
	 */


	dol_fiche_head($head, 'terms', $langs->trans("Contract"), 0, 'contract');

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
		} else 
		{
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

	// Ref du contrat
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

	// Date
	print '<tr><td>'.$langs->trans("Date").'</td>';
	print '<td colspan="3">'.dol_print_date($object->date_contrat,"dayhour")."</td></tr>\n";
	
	// Projet
	if (! empty($conf->projet->enabled))
	{
		$langs->load("projects");
		print '<tr><td>';
		print '<table width="100%" class="nobordernopadding"><tr><td>';
		print $langs->trans("Project");
		print '</td>';
		print '</tr></table>';
		print '</td><td colspan="3">';
		if ($action == "classify")
		{
		    $form->form_project($_SERVER['PHP_SELF'].'?id='.$object->id,$object->socid,$object->fk_project,"projectid");
		}
		else
		{
		    $form->form_project($_SERVER['PHP_SELF'].'?id='.$object->id,$object->socid,$object->fk_project,"none");
		}
		print "</td></tr>";
	}

	// Other attributes
	$parameters=array('colspan' => ' colspan="3"');
	$reshook=$hookmanager->executeHooks('formObjectOptions',$parameters,$object,$action);    // Note that $action and $object may have been modified by hook

	print "</table>";
}
	echo '<br>';

	// Line of terms
	print "<table width=100%>";
	print '<tr class="liste_titre">';

	// DateDeb
	print '<td align="left" width=80px>'.$langs->trans("DateBeginTerm")."</td>\n";
	print '<td align="left" width=80px>'.$langs->trans("DateEndTerm")."</td>\n";
	print '<td align="left" >'.$langs->trans("Description")."</td>\n";
	print '<td align="left" width=100px>'.$langs->trans("Status")."</td>\n";
	print '<td align="left" width=100px>'.$langs->trans("Action")."</td>\n";		
	print '</tr>';
	
	$termsarray = $object->get_terms_list();
	if (count($termsarray ) >0 )
	{
		$var=true;
		$num=count($termsarray);

		foreach ($termsarray as $key => $value)
		{
			$var=!$var;
			if(GETPOST('line')!=$value['rowid'])
				print "<tr $bc[$var]>";
			else
			{
				print "<tr bgcolor=#C0C0C0>";
				// récup des valeurs pour l'édition
				$datedebedit=$value['datedeb'];
				$datefinedit=$value['dateend'];
				if ($value[fk_statut]==4)
				 	$datenewterm=$datefinedit;
				$noteedit=$value['note'];
			}
			
			// DateDeb
			print '<td align="left">';
			print dol_print_date($value['datedeb'],'day');
			print "</td>\n";

			// DateEnd
			print '<td align="left">';
			print dol_print_date($value['dateend'],'day');
			print "</td>\n";

			// description
			print '<td align="left">'.$value['note'].'</td>';

			// Status
			$tmpstatut=$object->statut;
			$object->statut=$value['fk_status'];
			print '<td align="right">'.$object->getLibStatut(5).'</td>';
			$object->statut=$tmpstatut;

			// action on line
			print '<td align="left">';
			if ($action != "editterm")
			{
				switch ($value['fk_status'])
				{
					case 0:
						print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&line='.$value['rowid'].'&action=editterm">'.img_edit()."</a>";
						print '&nbsp;&nbsp;';
						print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&line='.$value['rowid'].'&action=validate">'.img_picto($langs->trans("StartTerm"),"play")."</a>";
						print '&nbsp;&nbsp;';
						print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&line='.$value['rowid'].'&action=delete">'.img_delete()."</a>";
						break;
					case 1:
						print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&line='.$value['rowid'].'&action=editterm">'.img_edit()."</a>";
						print '&nbsp;&nbsp;';
						print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&line='.$value['rowid'].'&action=closeterm">'.img_picto($langs->trans("CloseTerm"),"close")."</a>";
						break;
				}
			}
			print '</td>';
			if(GETPOST('line')==$value['rowid']) print "</font>";

			print '</tr>';
			
		}

		print '<tr class="liste_total"><td colspan="3">'.$langs->trans("Number").': '.$num.'</td>';
		print '<td colspan=2>&nbsp;</td>';
		print '</tr>';
	}
	print "</table>";


	/*
	 * Buttons
	 */

	if ($user->societe_id == 0)
	{
		// création de période uniquement sur les contrats validé
		if ($user->rights->contrat->creer && $action !="addnewterm" && $action != "editterm")
		{
			print '<div class="tabsAction">';

			if ($object->statut == 1) 
				print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=addnewterm">'.$langs->trans("AddNewTerm")."</a></div>";
			else 
				print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->trans("ContractOpenForAddTerm")).'">'.$langs->trans("AddNewTerm").'</a></div>';
			
			print "<br>\n";
			
			print '</div>';
			print '<br>';
		}
		print "</div>";
		print '<br>';
	}

	/*
	 * Add new subscription form
	 */
	if (($action == 'addnewterm' ) && $user->rights->contrat->creer)
	{
		print '<br>';

		print_fiche_titre($langs->trans("NewTerm"));

		// Define default choice to select
		$bankdirect=1;
		print "\n\n<!-- Form add subscription -->\n";
		print '<form name="cotisation" method="POST" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="newterm">';
		print '<input type="hidden" name="id" value="'.$id.'">';
		print "<table class='border' width='100%'>\n";
		
		$today=dol_now();
		if ($datenewterm=='')
			$datenewterm = $object->date_contrat;
		$termdefaultMode=$conf->global->CONTRAT_DEFAULTTERM_MODE;
		$termdefaultduration=$conf->global->CONTRAT_DEFAULTTERM_DURATION;
		if ($termdefaultMode)
		{
			// si il y a une échance terminé
			// on se positionne sur le premier jour du contrat
			$datefrom= $datenewterm ;
			// on cacul la date de fin
			switch($termdefaultMode)
			{
			case "DAY" :
				$dateto = strtotime($termdefaultduration .' days', $datefrom );
				break;
			case "WEEK" :
				$dateto = strtotime($termdefaultduration .' weeks', $datefrom );
				break;
			case "MONTH" :
				$dateto = strtotime($termdefaultduration .' months', $datefrom );
				break;
			case "QUARTER" :
				$dateto = strtotime(($termdefaultduration*3) .' months', $datefrom );
				break;
			case "SEMESTER" :
				$dateto = strtotime(($termdefaultduration*6) .' months', $datefrom );
				break;
			case "YEAR" :
				$dateto = strtotime($termdefaultduration .' years', $datefrom );
				break;

				break;
			}
			$labelterm=$langs->trans("Term")." ".$termdefaultduration." ".$langs->trans($termdefaultMode);
			//print dol_print_date(($datefrom!=-1?$datefrom:time()),"%Y");
		}
		else
		{
			$datefrom=-1;
			$dateto=-1;
			$labelterm=$langs->trans("Term");
		}
		
		$paymentdate=-1;

		// Date start subscription
		print '<tr><td width="30%" class="fieldrequired">'.$langs->trans("DateBeginTerm").'</td><td>';
		$form->select_date($datefrom,'deb','','','',"term",1,1);
		print "</td></tr>";
		
		// Date end subscription
		if (GETPOST('endday'))
		{
			$dateto=dol_mktime(0,0,0,GETPOST('endmonth'),GETPOST('endday'),GETPOST('endyear'));
		}
		print '<tr><td>'.$langs->trans("DateEndTerm").'</td><td>';
		$form->select_date($dateto,'end','','','',"term",1,1);
		print "</td></tr>";
		
		// Label
		print '<tr><td class="fieldrequired">'.$langs->trans("Label").'</td>';
		print '<td><input name="note" type="text" size="32" value="'.$labelterm.'"></td></tr>';
		print '</table>';
		
		print '<br>';
		
		print '<center>';
		print '<input type="submit" class="button" name="add" value="'.$langs->trans("AddTerm").'">';
		print ' &nbsp; &nbsp; ';
		print '<input type="submit" class="button" name="cancel" value="'.$langs->trans("Cancel").'">';
		print '</center>';
		
		print '</form>';
		
		print "\n<!-- End form subscription -->\n\n";
	}
	else if (($action == 'editterm' ) && $user->rights->contrat->creer)
	{
		print '<br>';

		print_fiche_titre($langs->trans("EditTerm"));

		// Define default choice to select
		$bankdirect=1;
		print "\n\n<!-- Form add subscription -->\n";
		print '<form name="cotisation" method="POST" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="updateterm">';
		print '<input type="hidden" name="id" value="'.$id.'">';
		print '<input type="hidden" name="line" value="'.GETPOST('line').'">';
		print "<table class='border' width='100%'>\n";
		
		$paymentdate=-1;

		// Date start subscription
		print '<tr><td width="30%" class="fieldrequired">'.$langs->trans("DateBeginTerm").'</td><td>';
		$form->select_date($datedebedit,'deb','','','',"term",1,1);
		print "</td></tr>";
		
		// Date end subscription
		print '<tr><td>'.$langs->trans("DateEndTerm").'</td><td>';
		$form->select_date($datefinedit,'end','','','',"term",1,1);
		print "</td></tr>";
		
		// Label
		print '<tr><td class="fieldrequired">'.$langs->trans("Label").'</td>';
		print '<td><input name="note" type="text" size="32" value="'.$noteedit.'" ></td></tr>';
		print '</table>';
		
		print '<br>';
		
		print '<center>';
		print '<input type="submit" class="button" name="add" value="'.$langs->trans("Save").'">';
		print ' &nbsp; &nbsp; ';
		print '<input type="submit" class="button" name="cancel" value="'.$langs->trans("Cancel").'">';
		print '</center>';
		
		print '</form>';
		
		print "\n<!-- End form subscription -->\n\n";
	}



	/*
	* Linked object block
	*/
	print '<table width="100%"><tr><td width="50%" valign="top">';
	if (DOL_VERSION >= "5.0.0")
	{
		// on feinte la fonction showlink
		$object->id = $object->fk_contrat;
		$object->element='contrat'; 
		//var_dump($object->id);
		$linktoelem = $form->showLinkToObjectBlock($object, null, array('contrat'));
		//$linktoelem = $form->showLinkToObjectBlock($object, null, array('management_managementcontratterm'));
		$somethingshown=$form->showLinkedObjectBlock($object, $linktoelem);
	}
	else
		$somethingshown=$object->showLinkedObjectBlock();

	print '</td><td valign="top" width="50%">';
	print '</td></tr></table>';
	print '</div>';
}

llxFooter();
$db->close();