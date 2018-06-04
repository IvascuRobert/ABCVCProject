<?php
/* Copyright (C) 2010-2013	Regis Houssin		<regis.houssin@capnetworks.com>
 * Copyright (C) 2010-2011	Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2012-2013	Christophe Battarel	<christophe.battarel@altairis.fr>
 * Copyright (C) 2012       Cédric Salvador     <csalvador@gpcsolutions.fr>
 * Copyright (C) 2012-2014  Raphaël Doursenaud  <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2013		Florian Henry		<florian.henry@open-concept.pro>
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
 *
 * Need to have following variables defined:
 * $object (invoice, order, ...)
 * $conf
 * $langs
 * $dateSelector
 * $forceall (0 by default, 1 for supplier invoices/orders)
 * $element     (used to test $user->rights->$element->creer)
 * $permtoedit  (used to replace test $user->rights->$element->creer)
 * $senderissupplier (0 by default, 1 for supplier invoices/orders)
 * $inputalsopricewithtax (0 by default, 1 to also show column with unit price including tax)
 * $usemargins (0 to disable all margins columns, 1 to show according to margin setup)
 * $object_rights->creer initialized from = $object->getRights()
 * $disableedit, $disablemove, $disableremove
 * 
 * $type, $text, $description, $line
 */

global $forceall, $senderissupplier, $inputalsopricewithtax, $usemargins, $outputalsopricetotalwithtax;

$usemargins=0;
if (! empty($conf->margin->enabled) && ! empty($object->element) && in_array($object->element,array('facture','propal','commande'))) $usemargins=1;

if (empty($dateSelector)) $dateSelector=0;
if (empty($forceall)) $forceall=0;
if (empty($senderissupplier)) $senderissupplier=0;
if (empty($inputalsopricewithtax)) $inputalsopricewithtax=0;
if (empty($outputalsopricetotalwithtax)) $outputalsopricetotalwithtax=0;
if (empty($usemargins)) $usemargins=0;
?>
<?php $coldisplay=0; ?>
<!-- BEGIN PHP TEMPLATE objectline_view.tpl.php -->
<tr <?php echo 'id="row-'.$line->id.'" '.$bcdd[$var]; ?>>
	<?php if (! empty($conf->global->MAIN_VIEW_LINE_NUMBER)) { ?>
	<td class="linecolnum" align="center"><?php $coldisplay++; ?><?php echo ($i+1); ?></td>
	<?php } ?>
	<td class="linecoldescription"><?php $coldisplay++; ?><div id="line_<?php echo $line->id; ?>"></div>
	<?php 
	if (($line->info_bits & 2) == 2) {
	?>
		<a href="<?php echo DOL_URL_ROOT.'/comm/remx.php?id='.$this->socid; ?>">
		<?php
		$txt='';
		print img_object($langs->trans("ShowReduc"),'reduc').' ';
		if ($line->description == '(DEPOSIT)') $txt=$langs->trans("Deposit");
		//else $txt=$langs->trans("Discount");
		print $txt;
		?>
		</a>
		<?php
		if ($line->description)
		{
			if ($line->description == '(CREDIT_NOTE)' && $line->fk_remise_except > 0)
			{
				$discount=new DiscountAbsolute($this->db);
				$discount->fetch($line->fk_remise_except);
				echo ($txt?' - ':'').$langs->transnoentities("DiscountFromCreditNote",$discount->getNomUrl(0));
			}
			elseif ($line->description == '(DEPOSIT)' && $line->fk_remise_except > 0)
			{
				$discount=new DiscountAbsolute($this->db);
				$discount->fetch($line->fk_remise_except);
				echo ($txt?' - ':'').$langs->transnoentities("DiscountFromDeposit",$discount->getNomUrl(0));
				// Add date of deposit
				if (! empty($conf->global->INVOICE_ADD_DEPOSIT_DATE)) 
				    echo ' ('.dol_print_date($discount->datec).')';
			}
			else
			{
				echo ($txt?' - ':'').dol_htmlentitiesbr($line->description);
			}
		}
	}
	else
	{
		$format = $conf->global->MAIN_USE_HOURMIN_IN_DATE_RANGE?'dayhour':'day';
		
	    if ($line->fk_product > 0)
		{
			echo $form->textwithtooltip($text,$description,3,'','',$i,0,(!empty($line->fk_parent_line)?img_picto('', 'rightarrow'):''));
			
			// Show range
			echo get_date_range($line->date_start, $line->date_end, $format);

			// Add description in form
			if (! empty($conf->global->PRODUIT_DESC_IN_FORM))
			{
				print (! empty($line->description) && $line->description!=$line->product_label)?'<br>'.dol_htmlentitiesbr($line->description):'';
			}

		}
		else
		{

			if ($type==1) $text = img_object($langs->trans('Service'),'service');
			else $text = img_object($langs->trans('Product'),'product');

			if (! empty($line->label)) {
				$text.= ' <strong>'.$line->label.'</strong>';
				echo $form->textwithtooltip($text,dol_htmlentitiesbr($line->description),3,'','',$i,0,(!empty($line->fk_parent_line)?img_picto('', 'rightarrow'):''));
			} else {
				if (! empty($line->fk_parent_line)) echo img_picto('', 'rightarrow');
				echo $text.' '.dol_htmlentitiesbr($line->description);
			}

			// Show range
			echo get_date_range($line->date_start,$line->date_end, $format);
		}
	}
	?>
	</td>
	<?php if ($object->element == 'supplier_proposal') { ?>
		<td class="linecolrefsupplier" align="right"><?php echo $line->ref_fourn; ?></td>
	<?php } 
	// VAT Rate
	?>
	<td align="right" class="linecolvat nowrap"><?php $coldisplay++; ?><?php if($line->tva_tx == '0.000'){ echo ''; }else{ echo vatrate($line->tva_tx.($line->vat_src_code?(' ('.$line->vat_src_code.')'):''), '%', $line->info_bits); } ?></td>

	<td align="right" class="linecoluht nowrap"><?php $coldisplay++; ?><?php if($line->subprice == '0.00000000' ){echo '';}else{echo price($line->subprice);}  ?></td>
	
	<?php if (!empty($conf->multicurrency->enabled)) { ?>
	<td align="right" class="linecoluht_currency nowrap"><?php $coldisplay++; ?><?php echo price($line->multicurrency_subprice); ?></td>
	<?php } ?>
	
	<?php if ($inputalsopricewithtax) { ?>
	<td align="right" class="linecoluttc nowrap"><?php $coldisplay++; ?><?php echo (isset($line->pu_ttc)?price($line->pu_ttc):price($line->subprice)); ?></td>
	<?php } ?>

	<td align="right" class="linecolqty nowrap"><?php $coldisplay++; ?>
	<?php if ((($line->info_bits & 2) != 2) && $line->special_code != 3) {
			// I comment this because it shows info even when not required
			// for example always visible on invoice but must be visible only if stock module on and stock decrease option is on invoice validation and status is not validated
			// must also not be output for most entities (proposal, intervention, ...)
			//if($line->qty > $line->stock) print img_picto($langs->trans("StockTooLow"),"warning", 'style="vertical-align: bottom;"')." ";
			if($line->subprice == '0.00000000' ){ echo ''; } else { echo $line->qty; }  
		} else echo '&nbsp;';	?>
	</td>

	<?php
	if($conf->global->PRODUCT_USE_UNITS)
	{
		print '<td align="left" class="linecoluseunit nowrap">';
		$label = $line->getLabelOfUnit('short');
		if ($label !== '') {
			print $langs->trans($label);
		}
		print '</td>';
	}
	?>


	<?php if (!empty($line->remise_percent) && $line->special_code != 3) { ?>
	<td class="linecoldiscount" align="right"><?php
		$coldisplay++;
		include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
		echo dol_print_reduction($line->remise_percent,$langs);
	?></td>
	<?php } else { /* ?>
	<td class="linecoldiscount"><?php $coldisplay++; ?>&nbsp;</td>
	<?php */ }

	if ($this->situation_cycle_ref) {
		$coldisplay++;
		print '<td align="right" class="linecolcycleref nowrap">' . $line->situation_percent . '%</td>';
	}

  	if ($usemargins && ! empty($conf->margin->enabled) && empty($user->societe_id))
  	{
		$rounding = min($conf->global->MAIN_MAX_DECIMALS_UNIT,$conf->global->MAIN_MAX_DECIMALS_TOT);
  		?>
  		
  	<?php if (!empty($user->rights->margins->creer)) { ?>
  	<td align="right" class="linecolmargin1 nowrap margininfos"><?php $coldisplay++; ?><?php  echo price($line->pa_ht); ?></td>
  	<?php } ?>
  	<?php if (! empty($conf->global->DISPLAY_MARGIN_RATES) && $user->rights->margins->liretous) { ?>
  	  <td align="right" class="linecolmargin2 nowrap margininfos"><?php $coldisplay++; ?><?php  echo (($line->pa_ht == 0)?'n/a':price($line->marge_tx, null, null, null, null, $rounding).'%'); ?></td>
  	<?php }
    if (! empty($conf->global->DISPLAY_MARK_RATES) && $user->rights->margins->liretous) {?>
  	  <td align="right" class="linecolmargin2 nowrap margininfos"><?php $coldisplay++; ?><?php echo price($line->marque_tx, null, null, null, null, $rounding).'%'; ?></td>
    <?php }
  	}
  	?>



<?php if ($line->special_code == 3)	{ ?>
	<td align="right" class="linecoloption nowrap"><?php $coldisplay++; ?><?php echo $langs->trans('Option'); ?></td>
	<?php } else { ?>
	<td align="right" class="liencolht nowrap"><?php $coldisplay++; ?><?php if($line->total_ht == '0.00000000'){ echo ''; }else{ echo price($line->total_ht); } ?></td>



	<?php

	//***************************************************************************************************************
	//
	//	extraction poste history
	//
	//***************************************************************************************************************
	$poste_billed_infos = array();
	foreach ($projectBills as $id_poste => $projectBill) {
		foreach ($projectBill as $ref_fact => $info_fact) {
			if( ($info_fact['fact_id'] == $line->fk_facture) && ($info_fact['line_id'] == $line->rowid) ){
				$poste_billed_infos = $projectBills[$id_poste];
			}
		}
	}
	if(count($poste_billed_infos)>0){

		//!! sauf sousposte/sousousposte
		$infosdesc = explode(' ',$line->description);
		$inforefs = explode('.',$infosdesc[0]);
		if( count($inforefs)>3 ){
			$lineIsPoste = false;
		} else {
			$lineIsPoste = true;
		}

	} else {
		$lineIsPoste = false;
	}


	$sum_factured = 0;
	$progress_factured = 0;
	foreach ($poste_billed_infos as $ref_fact => $info_fact) {
		$sum_factured += (float)$info_fact['total_ht'];
		$progress_factured += (float)$info_fact['import_key'];
	}

	if($lineIsPoste){
		// ABCVC Progression actuelle
		//------------------------------------------------------------------------------------------------------------------------------------------------------
		?>
		<td align="right" class="liencolht nowrap"><?php $coldisplay++; ?><?php if($line->import_key == ''){ echo ''; }else{ echo $line->import_key.'%'; } ?></td>

		<?php 
		// ABCVC Progression precedente
		//------------------------------------------------------------------------------------------------------------------------------------------------------
		?>
		<td align="right" class="liencolht nowrap"><?php $coldisplay++; ?><?php echo $progress_factured.'%'; //if($progress_factured == 0){ echo ''; }else{ echo $progress_factured.'%'; }?></td>
		
	<?php } else { ?>

		<td align="right" class="liencolht nowrap"></td>
		<td align="right" class="liencolht nowrap"></td>
	<?php } ?>	

		<td align="right" class="nowrap">&nbsp;</td>
		<td align="right" class="nowrap">&nbsp;</td>

	<?php if (!empty($conf->multicurrency->enabled)) { ?>
		<td align="right" class="linecolutotalht_currency nowrap"><?php $coldisplay++; ?><?php echo price($line->multicurrency_total_ht); ?></td>
		<?php } ?>
	<?php } ?>
    <?php if ($outputalsopricetotalwithtax) { ?>
        <td align="right" class="liencolht nowrap"><?php $coldisplay++; ?><?php echo price($line->total_ttc); ?></td>
    <?php } ?>





	<?php 
	// td actions .........................................................................
	if ($this->statut == 0  && ($object_rights->creer)) { ?>

	<?php 
	if ($num > 1 && empty($conf->browser->phone) && ($this->situation_counter == 1 || !$this->situation_cycle_ref) && empty($disablemove)) { ?>
	<td align="center" class="linecolmove tdlineupdown"><?php $coldisplay++; ?>
		<?php if ($i > 0) { ?>
		<a class="lineupdown" href="<?php echo $_SERVER["PHP_SELF"].'?id='.$this->id.'&amp;action=up&amp;rowid='.$line->id; ?>">
		<?php echo img_up('default',0,'imgupforline'); ?>
		</a>
		<?php } ?>
		<?php if ($i < $num-1) { ?>
		<a class="lineupdown" href="<?php echo $_SERVER["PHP_SELF"].'?id='.$this->id.'&amp;action=down&amp;rowid='.$line->id; ?>">
		<?php echo img_down('default',0,'imgdownforline'); ?>
		</a>
		<?php } ?>
	</td>
    <?php } else { ?>
    <td align="center"<?php echo ((empty($conf->browser->phone) && empty($disablemove)) ?' class="linecolmove tdlineupdown"':' class="linecolmove"'); ?>><?php $coldisplay++; ?></td>
	<?php } ?>

<?php } else { ?>

	<td colspan="3"><?php $coldisplay=$coldisplay+3; ?></td>

<?php } ?>

<?php
//Line extrafield
if (!empty($extrafieldsline))
{
	print $line->showOptionals($extrafieldsline,'view',array('style'=>$bcdd[$var],'colspan'=>$coldisplay));
}
?>

</tr>
<!-- END PHP TEMPLATE objectline_view.tpl.php -->
