<?php
/* Copyright (C) 2004-2014 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2016      Frédéric France      <frederic.france@free.fr>
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
 *	    \file       htdocs/compta/paiement_charge.php
 *		\ingroup    tax
 *		\brief      Page to add payment of a tax
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/compta/sociales/class/chargesociales.class.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/compta/sociales/class/paymentsocialcontribution.class.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/compta/bank/class/account.class.php';

$langs->load("bills");

$chid=GETPOST("id", 'int');
$action=GETPOST('action', 'alpha');
$amounts = array();

// Security check
$socid=0;
if ($user->societe_id > 0)
{
	$socid = $user->societe_id;
}


/*
 * Actions
 */

if ($action == 'add_payment' || ($action == 'confirm_paiement' && $confirm=='yes'))
{
	$error=0;

	if ($_POST["cancel"])
	{
		$loc = DOL_URL_ROOT.SUPP_PATH.'/compta/sociales/card.php?id='.$chid;
		header("Location: ".$loc);
		exit;
	}

	$datepaye = dol_mktime(12, 0, 0, $_POST["remonth"], $_POST["reday"], $_POST["reyear"]);

	if (! $_POST["paiementtype"] > 0)
	{
		$mesg = $langs->trans("ErrorFieldRequired",$langs->transnoentities("PaymentMode"));
		$error++;
	}
	if ($datepaye == '')
	{
		$mesg = $langs->trans("ErrorFieldRequired",$langs->transnoentities("Date"));
		$error++;
	}
    if (! empty($conf->banque->enabled) && ! $_POST["accountid"] > 0)
    {
        $mesg = $langs->trans("ErrorFieldRequired",$langs->transnoentities("AccountToCredit"));
        $error++;
    }

	if (! $error)
	{
		$paymentid = 0;

		// Read possible payments
		foreach ($_POST as $key => $value)
		{
			if (substr($key,0,7) == 'amount_')
			{
				$other_chid = substr($key,7);
				$amounts[$other_chid] = price2num($_POST[$key]);
			}
		}

        if (count($amounts) <= 0)
        {
            $error++;
            $errmsg='ErrorNoPaymentDefined';
        }

        if (! $error)
        {
    		$db->begin();

    		// Create a line of payments
    		$paiement = new PaymentSocialContribution($db);
    		$paiement->chid         = $chid;
    		$paiement->datepaye     = $datepaye;
    		$paiement->amounts      = $amounts;   // Tableau de montant
    		$paiement->paiementtype = $_POST["paiementtype"];
    		$paiement->num_paiement = $_POST["num_paiement"];
    		$paiement->note         = $_POST["note"];

    		if (! $error)
    		{
    		    $paymentid = $paiement->create($user, (GETPOST('closepaidcontrib')=='on'?1:0));
                if ($paymentid < 0)
                {
                    $errmsg=$paiement->error;
                    $error++;
                }
    		}

            if (! $error)
            {
                $result=$paiement->addPaymentToBank($user,'payment_sc','(SocialContributionPayment)',$_POST['accountid'],'','');
                if (! $result > 0)
                {
                    $errmsg=$paiement->error;
                    $error++;
                }
            }

    	    if (! $error)
            {
                $db->commit();
                $loc = DOL_URL_ROOT.SUPP_PATH.'/compta/sociales/card.php?id='.$chid;
                header('Location: '.$loc);
                exit;
            }
            else
            {
                $db->rollback();
            }
        }
	}

	$_GET["action"]='create';
}


/*
 * View
 */

llxHeader();

$form=new Form($db);


// Formulaire de creation d'un paiement de charge
if ($action == 'create')
{

	$charge = new ChargeSociales($db);
	$charge->fetch($chid);
    $charge->accountid=$charge->fk_account?$charge->fk_account:$charge->accountid;
    $charge->paiementtype=$charge->mode_reglement_id?$charge->mode_reglement_id:$charge->paiementtype;

	$total = $charge->amount;

	print load_fiche_titre($langs->trans("DoPayment"));
	print "<br>\n";

	if ($mesg)
	{
		print "<div class=\"error\">$mesg</div>";
	}

	print '<form name="add_payment" action="'.$_SERVER['PHP_SELF'].'" method="post">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="id" value="'.$chid.'">';
	print '<input type="hidden" name="chid" value="'.$chid.'">';
	print '<input type="hidden" name="action" value="add_payment">';

	dol_fiche_head('', '');

	print '<table class="border" width="100%">';

	print "<tr class=\"liste_titre\"><td colspan=\"2\">".$langs->trans("SocialContribution")."</td></tr>";

	print '<tr><td>'.$langs->trans("Ref").'</td><td><a href="'.DOL_URL_ROOT.SUPP_PATH.'/compta/sociales/card.php?id='.$chid.'">'.$chid.'</a></td></tr>';
	print '<tr><td>'.$langs->trans("Type")."</td><td>".$charge->type_libelle."</td></tr>\n";
	print '<tr><td>'.$langs->trans("Period")."</td><td>".dol_print_date($charge->periode,'day')."</td></tr>\n";
	print '<tr><td>'.$langs->trans("Label").'</td><td>'.$charge->lib."</td></tr>\n";
	print '<tr><td>'.$langs->trans("DateDue")."</td><td>".dol_print_date($charge->date_ech,'day')."</td></tr>\n";
	print '<tr><td>'.$langs->trans("Amount")."</td><td>".price($charge->amount,0,$outputlangs,1,-1,-1,$conf->currency).'</td></tr>';

	$sql = "SELECT sum(p.amount) as total";
	$sql.= " FROM ".MAIN_DB_PREFIX."paiementcharge as p";
	$sql.= " WHERE p.fk_charge = ".$chid;
	$resql = $db->query($sql);
	if ($resql)
	{
		$obj=$db->fetch_object($resql);
		$sumpaid = $obj->total;
		$db->free();
	}
	print '<tr><td>'.$langs->trans("AlreadyPaid").'</td><td>'.price($sumpaid,0,$outputlangs,1,-1,-1,$conf->currency).'</td></tr>';
	print '<tr><td class="tdtop">'.$langs->trans("RemainderToPay").'</td><td>'.price($total-$sumpaid,0,$outputlangs,1,-1,-1,$conf->currency).'</td></tr>';

	print '<tr class="liste_titre">';
	print "<td colspan=\"2\">".$langs->trans("Payment").'</td>';
	print '</tr>';

	print '<tr><td class="fieldrequired">'.$langs->trans("Date").'</td><td>';
	$datepaye = dol_mktime(12, 0, 0, $_POST["remonth"], $_POST["reday"], $_POST["reyear"]);
	$datepayment=empty($conf->global->MAIN_AUTOFILL_DATE)?(empty($_POST["remonth"])?-1:$datepaye):0;
	$form->select_date($datepayment,'','','','',"add_payment",1,1);
	print "</td>";
	print '</tr>';

	print '<tr><td class="fieldrequired">'.$langs->trans("PaymentMode").'</td><td>';
	$form->select_types_paiements(isset($_POST["paiementtype"])?$_POST["paiementtype"]:$charge->paiementtype, "paiementtype");
	print "</td>\n";
	print '</tr>';

	print '<tr>';
	print '<td class="fieldrequired">'.$langs->trans('AccountToDebit').'</td>';
	print '<td>';
	$form->select_comptes(isset($_POST["accountid"])?$_POST["accountid"]:$charge->accountid, "accountid", 0, '',1);  // Show opend bank account list
	print '</td></tr>';

	// Number
	print '<tr><td>'.$langs->trans('Numero');
	print ' <em>('.$langs->trans("ChequeOrTransferNumber").')</em>';
	print '</td>';
	print '<td><input name="num_paiement" type="text" value="'.GETPOST('num_paiement').'"></td></tr>'."\n";

	print '<tr>';
	print '<td class="tdtop">'.$langs->trans("Comments").'</td>';
	print '<td class="tdtop"><textarea name="note" wrap="soft" cols="60" rows="'.ROWS_3.'"></textarea></td>';
	print '</tr>';

	print '</table>';

	dol_fiche_end();

	/*
 	 * Autres charges impayees
	 */
	$num = 1;
	$i = 0;

	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	//print '<td>'.$langs->trans("SocialContribution").'</td>';
	print '<td align="left">'.$langs->trans("DateDue").'</td>';
	print '<td align="right">'.$langs->trans("Amount").'</td>';
	print '<td align="right">'.$langs->trans("AlreadyPaid").'</td>';
	print '<td align="right">'.$langs->trans("RemainderToPay").'</td>';
	print '<td align="center">'.$langs->trans("Amount").'</td>';
	print "</tr>\n";

	$var=true;
	$total=0;
	$totalrecu=0;

	while ($i < $num)
	{
		$objp = $charge;

		$var=!$var;

		print "<tr ".$bc[$var].">";

		if ($objp->date_ech > 0)
		{
			print "<td align=\"left\">".dol_print_date($objp->date_ech,'day')."</td>\n";
		}
		else
		{
			print "<td align=\"center\"><b>!!!</b></td>\n";
		}

		print '<td align="right">'.price($objp->amount)."</td>";

		print '<td align="right">'.price($sumpaid)."</td>";

		print '<td align="right">'.price($objp->amount - $sumpaid)."</td>";

		print '<td align="center">';
		if ($sumpaid < $objp->amount)
		{
			$namef = "amount_".$objp->id;
			print '<input type="text" size="8" name="'.$namef.'">';
		}
		else
		{
			print '-';
		}
		print "</td>";

		print "</tr>\n";
		$total+=$objp->total;
		$total_ttc+=$objp->total_ttc;
		$totalrecu+=$objp->am;
		$i++;
	}
	if ($i > 1)
	{
		// Print total
		print "<tr ".$bc[!$var].">";
		print '<td colspan="2" align="left">'.$langs->trans("Total").':</td>';
		print "<td align=\"right\"><b>".price($total_ttc)."</b></td>";
		print "<td align=\"right\"><b>".price($totalrecu)."</b></td>";
		print "<td align=\"right\"><b>".price($total_ttc - $totalrecu)."</b></td>";
		print '<td align="center">&nbsp;</td>';
		print "</tr>\n";
	}

	print "</table>";

	// Bouton Save payment
	print '<br><div class="center"><input type="checkbox" checked name="closepaidcontrib"> '.$langs->trans("ClosePaidContributionsAutomatically");
	print '<br><input type="submit" class="button" name="save" value="'.$langs->trans('ToMakePayment').'">';
	print '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	print '<input type="submit" class="button" name="cancel" value="'.$langs->trans("Cancel").'">';
	print '</div>';

	print "</form>\n";
}

llxFooter();
$db->close();