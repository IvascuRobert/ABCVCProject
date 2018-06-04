<?php
/* Copyright (C) 2013-2017	Charlie BENKE	<charlie@patas-monkey.com>
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
 *	\file	   	htdocs/fichinter/rapport.php
 *	\ingroup		fichinter
 *	\brief	  	Page of fichinter repport
 */

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");		// For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");	// For "custom" directory

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/modules/fichinter/modules_fichinter.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/fichinter.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

if (! empty($conf->projet->enabled))
{
	require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
}

if ($conf->contrat->enabled)
{
	require_once DOL_DOCUMENT_ROOT."/core/class/html.formcontract.class.php";
	require_once DOL_DOCUMENT_ROOT."/contrat/class/contrat.class.php";
}

dol_include_once('/management/class/managementfichinter.class.php');

$langs->load('companies');
$langs->load('interventions');
$langs->load('bills');
$langs->load('management@management');

//$langs->load(' ');
//$langs->load('compta');
//$langs->load('bills');
//$langs->load('orders');
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

// Nombre de ligne pour choix de produit/service predefinis
$NBLINES=4;

// Security check
if (! empty($user->societe_id))	$socid=$user->societe_id;
$result = restrictedArea($user, 'ficheinter', $id, 'fichinter');

$object = new Managementfichinter($db);

// Load object
if ($id > 0 || ! empty($ref))
{
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
		else $object->fetch_thirdparty();
	}
}
else
{
	header('Location: '.DOL_URL_ROOT.'/fichinter/list.php');
	exit;
}

// Initialize technical object to manage hooks of thirdparties. Note that conf->hooks_modules contains array array
include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
$hookmanager=new HookManager($db);
$hookmanager->initHooks(array('fichintercard'));



/*
 * Actions
 */

$parameters=array('socid'=>$socid);

// Remove line
if ($action == 'confirm_deleteline' && $confirm == 'yes' && $user->rights->ficheinter->creer)
{
	$result = $object->deleteline($lineid);
	// reorder lines
	if ($result) $object->line_order(true);
	header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
	exit;
}

$fulldayevent=$_POST["fulldayevent"];
if ($action == 'setdatee')
{
	$datee=dol_mktime(
	$fulldayevent?'23':$_POST["fulldayendhour"],
	$fulldayevent?'59':$_POST["fulldayendmin"],
	$fulldayevent?'59':'0',
	$_POST["fulldayendmonth"],
	$_POST["fulldayendday"],
	$_POST["fulldayendyear"]);

	$object->fetch($id);
	$result=$object->set_datee($user,$datee);
	if ($result < 0) dol_print_error($db,$object->error);
}

if ($action == 'setdateo')
{
	$dateo=dol_mktime(
		$fulldayevent?'23':$_POST["fulldaystarthour"],
		$fulldayevent?'59':$_POST["fulldaystartmin"],
		$fulldayevent?'59':'0',
		$_POST["fulldaystartmonth"],
		$_POST["fulldaystartday"],
		$_POST["fulldaystartyear"]);

	$object->fetch($id);
	$result=$object->set_dateo($user,$dateo);
	if ($result < 0) dol_print_error($db,$object->error);
}


if ($action == 'setfulldayevent')
{	
	$object->fetch($id);
	$result=$object->set_fullday($user,$fulldayevent);
	if ($result < 0) dol_print_error($db,$object->error);
}

//Ajout d'une ligne produit dans l'intervention
else if ($action == "addline" && $user->rights->ficheinter->creer)
{
	$idprod=GETPOST('idprod', 'int');
	$product_desc = (GETPOST('product_desc')?GETPOST('product_desc'):(GETPOST('np_desc')?GETPOST('np_desc'):(GETPOST('dp_desc')?GETPOST('dp_desc'):'')));
	$price_ht = GETPOST('price_ht');
	$tva_tx = (GETPOST('tva_tx')?GETPOST('tva_tx'):0);

	if (empty($idprod) && GETPOST('type') < 0)
	{
		setEventMessage($langs->trans("ErrorFieldRequired",$langs->transnoentitiesnoconv("Type")), 'errors');
		$error++;
	}

	if (! $error && (GETPOST('qty') >= 0) && (! empty($product_desc) || ! empty($idprod)))
	{
		$pu_ht=0;
		$pu_ttc=0;
		$price_min=0;
		$price_base_type = (GETPOST('price_base_type', 'alpha')?GETPOST('price_base_type', 'alpha'):'HT');

		// Ecrase $pu par celui du produit
		// Ecrase $desc par celui du produit
		// Ecrase $txtva par celui du produit
		if (! empty($idprod))
		{
			$prod = new Product($db);
			$prod->fetch($idprod);

			$label = ((GETPOST('product_label') && GETPOST('product_label')!=$prod->label)?GETPOST('product_label'):'');

			// If prices fields are update
			if (GETPOST('usenewaddlineform'))
			{
				$pu_ht=price2num($price_ht, 'MU');
				$pu_ttc=price2num(GETPOST('price_ttc'), 'MU');
				$tva_npr=(preg_match('/\*/', $tva_tx)?1:0);
				$tva_tx=str_replace('*','', $tva_tx);
				$desc = $product_desc;
			}
			else
			{
				$tva_tx = get_default_tva($mysoc,$object->thirdparty,$prod->id);
				$tva_npr = get_default_npr($mysoc,$object->thirdparty,$prod->id);

				// On defini prix unitaire
				if (! empty($conf->global->PRODUIT_MULTIPRICES) && $object->thirdparty->price_level)
				{
					$pu_ht  = $prod->multiprices[$object->thirdparty->price_level];
					$pu_ttc = $prod->multiprices_ttc[$object->thirdparty->price_level];
					$price_min = $prod->multiprices_min[$object->thirdparty->price_level];
					$price_base_type = $prod->multiprices_base_type[$object->thirdparty->price_level];
				}
				else
				{
					$pu_ht = $prod->price;
					$pu_ttc = $prod->price_ttc;
					$price_min = $prod->price_min;
					$price_base_type = $prod->price_base_type;
				}

				// On reevalue prix selon taux tva car taux tva transaction peut etre different
				// de ceux du produit par defaut (par exemple si pays different entre vendeur et acheteur).
				if ($tva_tx != $prod->tva_tx)
				{
					if ($price_base_type != 'HT')
					{
						$pu_ht = price2num($pu_ttc / (1 + ($tva_tx/100)), 'MU');
					}
					else
					{
						$pu_ttc = price2num($pu_ht * (1 + ($tva_tx/100)), 'MU');
					}
				}

				$desc='';

				// Define output language
				if (! empty($conf->global->MAIN_MULTILANGS) && ! empty($conf->global->PRODUIT_TEXTS_IN_THIRDPARTY_LANGUAGE))
				{
					$outputlangs = $langs;
					$newlang='';
					if (empty($newlang) && GETPOST('lang_id')) $newlang=GETPOST('lang_id');
					if (empty($newlang)) $newlang=$object->thirdparty->default_lang;
					if (! empty($newlang))
					{
						$outputlangs = new Translate("",$conf);
						$outputlangs->setDefaultLang($newlang);
					}

					$desc = (! empty($prod->multilangs[$outputlangs->defaultlang]["description"])) ? $prod->multilangs[$outputlangs->defaultlang]["description"] : $prod->description;
				}
				else
				{
					$desc = $prod->description;
				}

				$desc=dol_concatdesc($desc,$product_desc);

				// Add custom code and origin country into description
				if (empty($conf->global->MAIN_PRODUCT_DISABLE_CUSTOMCOUNTRYCODE) && (! empty($prod->customcode) || ! empty($prod->country_code)))
				{
					$tmptxt='(';
					if (! empty($prod->customcode)) $tmptxt.=$langs->transnoentitiesnoconv("CustomCode").': '.$prod->customcode;
					if (! empty($prod->customcode) && ! empty($prod->country_code)) $tmptxt.=' - ';
					if (! empty($prod->country_code)) $tmptxt.=$langs->transnoentitiesnoconv("CountryOrigin").': '.getCountry($prod->country_code,0,$db,$langs,0);
					$tmptxt.=')';
					$desc= dol_concatdesc($desc, $tmptxt);
				}
			}

			$type = $prod->type;
		}
		else
		{
			$pu_ht		= price2num($price_ht, 'MU');
			$pu_ttc		= price2num(GETPOST('price_ttc'), 'MU');
			$tva_npr	= (preg_match('/\*/', $tva_tx)?1:0);
			$tva_tx		= str_replace('*', '', $tva_tx);
			$label		= (GETPOST('product_label')?GETPOST('product_label'):'');
			$desc		= $product_desc;
			$type		= GETPOST('type');
		}

		// Margin
		$fournprice=(GETPOST('fournprice')?GETPOST('fournprice'):'');
		$buyingprice=(GETPOST('buying_price')?GETPOST('buying_price'):'');

		// Local Taxes
		$localtax1_tx= get_localtax($tva_tx, 1, $object->client);
		$localtax2_tx= get_localtax($tva_tx, 2, $object->client);

		$info_bits=0;
		if ($tva_npr) $info_bits |= 0x01;

		if (! empty($price_min) && (price2num($pu_ht)*(1-price2num(GETPOST('remise_percent'))/100) < price2num($price_min)))
		{
			$mesg = $langs->trans("CantBeLessThanMinPrice",price2num($price_min,'MU').getCurrencySymbol($conf->currency));
			setEventMessage($mesg, 'errors');
		}
		else
		{
			// Insert line
			$result=$object->addlineRapport(
				$id,
				$desc,
				$pu_ht,
				(GETPOST('qty_predef')?GETPOST('qty_predef'):GETPOST('qty')),  // en 3.6 c'est qty, en 3.7 c'est qty_predef
				$tva_tx,
				$localtax1_tx,
				$localtax2_tx,
				$idprod,
				(GETPOST('remise_percent_predef')?GETPOST('remise_percent_predef'):GETPOST('remise_percent')), // en 3.9 predef a sauté ...
				$info_bits,
				$fk_remise_except,
				$price_base_type,
				$pu_ttc,
				$date_start,
				$date_end,
				$type,
				-1,
				0,
				GETPOST('fk_parent_line'),
				$fournprice,
				$buyingprice,
				$label
			);

			if ($result > 0)
			{
				unset($_POST['qty_predef']);
				unset($_POST['type']);
				unset($_POST['idprod']);
				unset($_POST['remise_percent_predef']);
				unset($_POST['price_ht']);
				unset($_POST['price_ttc']);
				unset($_POST['tva_tx']);
				unset($_POST['product_ref']);
				unset($_POST['product_label']);
				unset($_POST['product_desc']);
				unset($_POST['fournprice']);
				unset($_POST['buying_price']);

				// old method
				unset($_POST['np_desc']);
				unset($_POST['dp_desc']);
				
				$ret=$object->fetch($id);	// Reload to get new records
			}
			else
			{
				setEventMessage($object->error, 'errors');
			}
		}
	}
}

// Mise a jour d'une ligne dans la fiche d'intervention
else if ($action == 'updateligne' && $user->rights->ficheinter->creer && GETPOST('save') == $langs->trans("Save"))
{
	// Define info_bits
	$info_bits=0;
	if (preg_match('/\*/', GETPOST('tva_tx'))) $info_bits |= 0x01;

	// Clean parameters
	$description=dol_htmlcleanlastbr(GETPOST('product_desc'));

	// Define vat_rate
	$vat_rate=(GETPOST('tva_tx')?GETPOST('tva_tx'):0);
	$vat_rate=str_replace('*','',$vat_rate);
	$localtax1_rate=get_localtax($vat_rate,1,$object->client);
	$localtax2_rate=get_localtax($vat_rate,2,$object->client);
	$pu_ht=GETPOST('price_ht');

	// Add buying price
	$fournprice=(GETPOST('fournprice')?GETPOST('fournprice'):'');
	$buyingprice=(GETPOST('buying_price')?GETPOST('buying_price'):'');

	// Define special_code for special lines
	$special_code=0;
	if (! GETPOST('qty')) $special_code=3;

	// Check minimum price
	$productid = GETPOST('productid', 'int');
	if (! empty($productid))
	{
		$product = new Product($db);
		$res=$product->fetch($productid);

		$type=$product->type;

		$price_min = $product->price_min;
		if (! empty($conf->global->PRODUIT_MULTIPRICES) && ! empty($object->thirdparty->price_level))
			$price_min = $product->multiprices_min[$object->thirdparty->price_level];

		$label = ((GETPOST('update_label') && GETPOST('product_label')) ? GETPOST('product_label'):'');

		if ($price_min && (price2num($pu_ht)*(1-price2num(GETPOST('remise_percent'))/100) < price2num($price_min)))
		{
			setEventMessage($langs->trans("CantBeLessThanMinPrice", price2num($price_min,'MU')).getCurrencySymbol($conf->currency), 'errors');
			$error++;
		}
	}
	else
	{
		$type = GETPOST('type');
		$label = (GETPOST('product_label') ? GETPOST('product_label'):'');

		// Check parameters
		if (GETPOST('type') < 0) {
			setEventMessage($langs->trans("ErrorFieldRequired",$langs->transnoentitiesnoconv("Type")), 'errors');
			$error++;
		}
	}

	if (! $error)
	{
		$result = $object->updateline(
			GETPOST('lineid'),
			$description,
			$pu_ht,
			GETPOST('qty'),
			GETPOST('remise_percent'),
			$vat_rate,
			$localtax1_rate,
			$localtax2_rate,
			'HT',
			$info_bits,
			$date_start,
			$date_end,
			$type,
			GETPOST('fk_parent_line'),
			0,
			$fournprice,
			$buyingprice,
			$label,
			$special_code
		);
		
		if ($result >= 0)
		{
			$ret=$object->fetch($id);	// Reload to get new records
			if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE))
			{
				// Define output language
				$outputlangs = $langs;
				if (! empty($conf->global->MAIN_MULTILANGS))
				{
					$outputlangs = new Translate("",$conf);
					$newlang=(GETPOST('lang_id') ? GETPOST('lang_id') : $object->thirdparty->default_lang);
					$outputlangs->setDefaultLang($newlang);
				}

				$object->fetch_thirdparty();
				$object->fetch_lines();
				
				if (GETPOST('model','alpha'))
				{
					$object->setDocModel($user, GETPOST('model','alpha'));
				}
				
				// Define output language
				$outputlangs = $langs;
				$newlang='';
				if ($conf->global->MAIN_MULTILANGS && empty($newlang) && GETPOST('lang_id','alpha')) $newlang=GETPOST('lang_id','alpha');
				if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->thirdparty->default_lang;
				if (! empty($newlang))
				{
					$outputlangs = new Translate("",$conf);
					$outputlangs->setDefaultLang($newlang);
				}
				$result=fichinter_create($db, $object, GETPOST('model','alpha'), $outputlangs);
				if ($result <= 0)
				{
					dol_print_error($db,$result);
					exit;
				}

				//propale_pdf_create($db, $object, $object->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref, $hookmanager);
			}
			unset($_POST['qty']);
			unset($_POST['type']);
			unset($_POST['productid']);
			unset($_POST['remise_percent']);
			unset($_POST['price_ht']);
			unset($_POST['price_ttc']);
			unset($_POST['tva_tx']);
			unset($_POST['product_ref']);
			unset($_POST['product_label']);
			unset($_POST['product_desc']);
			unset($_POST['fournprice']);
			unset($_POST['buying_price']);
			
		}
		else
		{
			setEventMessage($object->error, 'errors');
		}
	}
}

else if ($action == 'updateligne' && $user->rights->ficheinter->creer && GETPOST('cancel') == $langs->trans('Cancel'))
{
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);   // Pour reaffichage de la fiche en cours d'edition
	exit;
}

// Classify partial closed
else if ($action == 'reopen' && $user->rights->ficheinter->creer)
{
	$object->fetch($id);
	$result=$object->setClosed(1);
	if ($result > 0)
	{
		header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
		exit;
	}
	else
	{
		$mesg='<div class="error">'.$object->error.'</div>';
	}
}

// Billed = 2

// Classify partial closed
else if ($action == 'classifypartialclosed' && $user->rights->ficheinter->creer)
{
	$object->fetch($id);
	$result=$object->setClosed(4);
	if ($result > 0)
	{
		header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
		exit;
	}
	else
		$mesg='<div class="error">'.$object->error.'</div>';
}
// Classify closed
else if ($action == 'classifyclosed' && $user->rights->ficheinter->creer)
{
	$object->fetch($id);
	$result=$object->setClosed(3);
	if ($result > 0)
	{
		header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
		exit;
	}
	else
		$mesg='<div class="error">'.$object->error.'</div>';
}

// Classify closed not to bill
else if ($action == 'classifyclosednottobill' && $user->rights->ficheinter->creer)
{
	$object->fetch($id);
	$result=$object->setClosed(5);
	if ($result > 0)
	{
		header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
		exit;
	}
	else
		$mesg='<div class="error">'.$object->error.'</div>';
}

/*
 * Ordonnancement des lignes
 */

else if ($action == 'up' && $user->rights->ficheinter->creer)
{
	$object->line_up(GETPOST('rowid'));

	if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE))
	{
		// Define output language
		$outputlangs = $langs;
		if (! empty($conf->global->MAIN_MULTILANGS))
		{
			$outputlangs = new Translate("",$conf);
			$newlang=(GETPOST('lang_id') ? GETPOST('lang_id') : $object->thirdparty->default_lang);
			$outputlangs->setDefaultLang($newlang);
		}
		$ret=$object->fetch($id);	// Reload to get new records
		//propale_pdf_create($db, $object, $object->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref, $hookmanager);
	}

	header('Location: '.$_SERVER["PHP_SELF"].'?id='.$id.'#'.GETPOST('rowid'));
	exit;
}

else if ($action == 'down' && $user->rights->ficheinter->creer)
{
	$object->line_down(GETPOST('rowid'));

	if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE))
	{
		// Define output language
		$outputlangs = $langs;
		if (! empty($conf->global->MAIN_MULTILANGS))
		{
			$outputlangs = new Translate("",$conf);
			$newlang=(GETPOST('lang_id') ? GETPOST('lang_id') : $object->thirdparty->default_lang);
			$outputlangs->setDefaultLang($newlang);
		}
		$ret=$object->fetch($id);	// Reload to get new records
		//propale_pdf_create($db, $object, $object->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref, $hookmanager);
	}

	header('Location: '.$_SERVER["PHP_SELF"].'?id='.$id.'#'.GETPOST('rowid'));
	exit;
}

/*
 * Add file in email form
 */
if (GETPOST('addfile','alpha'))
{
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	// Set tmp user directory TODO Use a dedicated directory for temp mails files
	$vardir=$conf->user->dir_output."/".$user->id;
	$upload_dir_tmp = $vardir.'/temp';
	
	dol_add_file_process($upload_dir_tmp,0,0);
	$action='presend';
}

/*
 * Remove file in email form
 */
if (GETPOST('removedfile','alpha'))
{
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	// Set tmp user directory
	$vardir=$conf->user->dir_output."/".$user->id;
	$upload_dir_tmp = $vardir.'/temp';
	
	// TODO Delete only files that was uploaded from email form
	dol_remove_file_process(GETPOST('removedfile','alpha'),0);
	$action='presend';
}

/*
 * Send mail
 */
if ($action == 'send' && ! GETPOST('cancel','alpha') && (empty($conf->global->MAIN_USE_ADVANCED_PERMS) || $user->rights->ficheinter->ficheinter_advance->send))
{
	$langs->load('mails');

	if ($object->fetch($id) > 0)
	{
		$object->fetch_thirdparty();

		if (GETPOST('sendto','alpha'))
		{
			// Le destinataire a ete fourni via le champ libre
			$sendto = GETPOST('sendto','alpha');
			$sendtoid = 0;
		}
		elseif (GETPOST('receiver','alpha') != '-1')
		{
			// Recipient was provided from combo list
			if (GETPOST('receiver','alpha') == 'thirdparty') // Id of third party
			{
				$sendto = $object->thirdparty->email;
				$sendtoid = 0;
			}
			else	// Id du contact
			{
				$sendto = $object->thirdparty->contact_get_property(GETPOST('receiver'),'email');
				$sendtoid = GETPOST('receiver','alpha');
			}
		}

		if (dol_strlen($sendto))
		{
			$langs->load("commercial");
			
			$from				= GETPOST('fromname','alpha') . ' <' . GETPOST('frommail','alpha') .'>';
			$replyto			= GETPOST('replytoname','alpha'). ' <' . GETPOST('replytomail','alpha').'>';
			$message			= GETPOST('message','alpha');
			$sendtocc			= GETPOST('sendtocc','alpha');
			$deliveryreceipt	= GETPOST('deliveryreceipt','alpha');
			
			if ($action == 'send')
			{
				if (strlen(GETPOST('subject','alphs'))) $subject = GETPOST('subject','alpha');
				else $subject = $langs->transnoentities('Intervention').' '.$object->ref;
				$actiontypecode='AC_FICH';
				$actionmsg = $langs->transnoentities('MailSentBy').' '.$from.' '.$langs->transnoentities('To').' '.$sendto.".\n";
				if ($message)
				{
					$actionmsg.=$langs->transnoentities('MailTopic').": ".$subject."\n";
					$actionmsg.=$langs->transnoentities('TextUsedInTheMessageBody').":\n";
					$actionmsg.=$message;
				}
				$actionmsg2=$langs->transnoentities('Action'.$actiontypecode);
			}
			
			// Create form object
			include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
			$formmail = new FormMail($db);
			
			$attachedfiles=$formmail->get_attached_files();
			$filepath = $attachedfiles['paths'];
			$filename = $attachedfiles['names'];
			$mimetype = $attachedfiles['mimes'];
			
			// Envoi de la fiche d'intervention
			require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
			$mailfile = new CMailFile($subject,$sendto,$from,$message,$filepath,$mimetype,$filename,$sendtocc,'',$deliveryreceipt);
			if ($mailfile->error)
			{
				$mesg='<div class="error">'.$mailfile->error.'</div>';
			}
			else
			{
				$result=$mailfile->sendfile();
				if ($result)
				{
					$mesg='<div class="ok">'.$langs->trans('MailSuccessfulySent',$mailfile->getValidAddress($from,2),$mailfile->getValidAddress($sendto,2)).'.</div>';

					$error=0;

					// Initialisation donnees
					$object->sendtoid		= $sendtoid;
					$object->actiontypecode	= $actiontypecode;
					$object->actionmsg 		= $actionmsg;
					$object->actionmsg2		= $actionmsg2;
					$object->fk_element		= $object->id;
					$object->elementtype	= $object->element;

					// Appel des triggers
					include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
					$interface=new Interfaces($db);
					$result=$interface->run_triggers('FICHINTER_SENTBYMAIL',$object,$user,$langs,$conf);
					if ($result < 0) { $error++; $this->errors=$interface->errors; }
					// Fin appel triggers

					if ($error)
					{
						dol_print_error($db);
					}
					else
					{
						// Redirect here
						// This avoid sending mail twice if going out and then back to page
						header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id.'&msg='.urlencode($mesg));
						exit;
					}
				}
				else
				{
					$langs->load("other");
					$mesg='<div class="error">';
					if ($mailfile->error)
					{
						$mesg.=$langs->trans('ErrorFailedToSendMail',$from,$sendto);
						$mesg.='<br>'.$mailfile->error;
					}
					else
					{
						$mesg.='No mail sent. Feature is disabled by option MAIN_DISABLE_ALL_MAILS';
					}
					$mesg.='</div>';
				}
			}
		}
		else
		{
			$langs->load("other");
			$mesg='<div class="error">'.$langs->trans('ErrorMailRecipientIsEmpty').' !</div>';
			dol_syslog('Recipient email is empty');
		}
	}
	else
	{
		$langs->load("other");
		$mesg='<div class="error">'.$langs->trans('ErrorFailedToReadEntity',$langs->trans("Intervention")).'</div>';
		dol_syslog('Impossible de lire les donnees de l\'intervention. Le fichier intervention n\'a peut-etre pas ete genere.');
	}

	$action='presend';
}


/*
 * View
 */

llxHeader(); 

$form = new Form($db);
$formother = new FormOther($db);
$formfile = new FormFile($db);
$companystatic=new Societe($db);

$now=dol_now();

/*
 * Show object in view mode
 */

$soc = new Societe($db);
$soc->fetch($object->socid);

$object->fetch_thirdparty();

$head = fichinter_prepare_head($object);

dol_fiche_head($head, 'Rapport', $langs->trans("InterventionCard"), 0, 'intervention');

$formconfirm='';


// Confirmation delete product/service line
if ($action == 'ask_deleteline')
{
	$formconfirm=$form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&lineid='.$lineid, $langs->trans('DeleteProductLine'), $langs->trans('ConfirmDeleteProductLine'), 'confirm_deleteline','',0,1);
}


if (! $formconfirm)
{
	$parameters=array('lineid'=>$lineid);
	$reshook=$hookmanager->executeHooks('formConfirm',$parameters,$object,$action);	// Note that $action and $object may have been modified by hook
	if (empty($reshook)) $formconfirm.=$hookmanager->resPrint;
	elseif ($reshook > 0) $formconfirm=$hookmanager->resPrint;


}
// Print form confirm
print $formconfirm;

$linkback = '<a href="'.DOL_URL_ROOT.'/fichinter/list.php'.(! empty($socid)?'?socid='.$socid:'').'">'.$langs->trans("BackToList").'</a>';

if (DOL_VERSION > "5.0.0")
{
	$morehtmlref='<div class="refidno">';

	// Thirdparty
	$morehtmlref.=$langs->trans('ThirdParty') . ' : ' . $object->thirdparty->getNomUrl(1);
	// Project
	if (! empty($conf->projet->enabled))
	{
		$langs->load("projects");
		require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
		$morehtmlref.='<br>'.$langs->trans('Project') . ' : ';
		if (! empty($object->fk_project)) {
			$proj = new Project($db);
			$proj->fetch($object->fk_project);
			$morehtmlref.='<a href="'.DOL_URL_ROOT.'/projet/card.php?id=' . $object->fk_project . '" title="' . $langs->trans('ShowProject') . '">';
			$morehtmlref.=$proj->ref;
			$morehtmlref.='</a>';
		}
	}
	$morehtmlref.='</div>';
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);
	//dol_fiche_end();
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border" width="100%">';

}
else
{
	print '<table class="border" width="100%">';
	
	// Ref
	print '<tr><td>'.$langs->trans('Ref').'</td><td colspan="5">';
	print $form->showrefnav($object, 'ref', $linkback, 1, 'ref', 'ref', '');
	print '</td></tr>';
	
	
	// Company
	print '<tr><td>'.$langs->trans('Company').'</td><td colspan="5">'.$soc->getNomUrl(1).'</td>';
	print '</tr>';

	// Project
	if (! empty($conf->projet->enabled))
	{
		$langs->load('projects');
		print '<tr>';
		print '<td>';
		print $langs->trans('Project');
		print '</td><td colspan="3">';
		$form->form_project($_SERVER['PHP_SELF'].'?id='.$object->id, $object->socid, $object->fk_project,'none');
		print '</td>';
		print '</tr>';
	}
	
	// Statut
	print '<tr><td height="10">'.$langs->trans('Status').'</td><td align="left" colspan="2">'.$object->getLibStatut(1).'</td></tr>';

}

if (empty($conf->global->FICHINTER_DISABLE_DETAILS))
{
	// Duration
	print '<tr><td>'.$langs->trans("TotalDuration").'</td>';
	print '<td colspan="3">'.convertSecondToTime($object->duree, 'all', $conf->global->MAIN_DURATION_OF_WORKDAY).'</td>';
	print '</tr>';
}

// Description (must be a textarea and not html must be allowed (used in list view)
print '<tr><td>'.$langs->trans("Description").'</td>';
print '<td colspan="3">'.$object->description.'</td>';
print '</tr>';


// Full day event
print '<tr><td width=25% ><table class="nobordernopadding" width="100%"><tr><td>'.$langs->trans("FullDayEvent").'</td>';
if ($action != 'editfulldayevent' && $object->statut == 0) print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editfulldayevent&amp;id='.$object->id.'">'.img_edit($langs->trans('Modify'),1).'</a></td>';
print '</tr></table></td><td colspan="3">';
if ($action == 'editfulldayevent')
{
	print '<form name="editfulldayevent" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="setfulldayevent">';
	print '<input type="checkbox" id="fulldayevent" value=1 '.($object->fulldayevent?' checked="checked"':'').' name="fulldayevent" >';
	print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
	print '</form>';

}
else
{
	print yn($object->fulldayevent);
}
print '</td></tr>';

// Date start
print '<tr><td><table class="nobordernopadding" width="100%"><tr><td>'.$langs->trans("DateStart").'</td>';
if ($action != 'editdateo') print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editdateo&amp;id='.$object->id.'">'.img_edit($langs->trans('Modify'),1).'</a></td>';
print '</tr></table></td><td colspan="3">';
if ($action == 'editdateo')
{
	print '<form name="editdateo" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="setdateo">';
	print $form->select_date($object->dateo,'fulldaystart',1,1,'',"fulldaystart");
	print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
	print '</form>';
}
else
{
	if ($object->fulldayevent==1)
		print dol_print_date($object->dateo,'day');
	else
		print dol_print_date($object->dateo,'dayhour');
}
print '</td></tr>';

// Date end
print '<tr><td><table class="nobordernopadding" width="100%"><tr><td>'.$langs->trans("DateEnd").'</td>';
if ($action != 'editdatee' ) print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editdatee&amp;id='.$object->id.'">'.img_edit($langs->trans('Modify'),1).'</a></td>';
print '</tr></table></td><td colspan="3">';
if ($action == 'editdatee')
{
	print '<form name="editdatee" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="setdatee">';
	print $form->select_date($object->datee,'fulldayend',1,1,'',"fulldayend");
	print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
	print '</form>';
}
else
{
	if ($object->fulldayevent==1)
		print dol_print_date($object->datee,'day');
	else
		print dol_print_date($object->datee,'dayhour');
}
print '</td></tr>';


// Contrat
if ($conf->contrat->enabled)
{
	$langs->load('contrat');
	print '<tr>';
	print '<td>';
	print $langs->trans('Contract');
	print '</td><td colspan="3">';
	if ($object->fk_contrat)
	{
		$contratstatic = new Contrat($db);
		$contratstatic->fetch($object->fk_contrat);
		//print '<a href="'.DOL_URL_ROOT.'/projet/fiche.php?id='.$selected.'">'.$projet->title.'</a>';
		print $contratstatic->getNomUrl(0,'',1);
	}
	else
		print "&nbsp;";

	print '</td>';
	print '</tr>';

}

// Other attributes
$parameters=array('colspan' => ' colspan="3"');
$reshook=$hookmanager->executeHooks('formObjectOptions',$parameters,$object,$action);	// Note that $action and $object may have been modified by hook
if (empty($reshook) && ! empty($extrafields->attribute_label))
{
	foreach($extrafields->attribute_label as $key=>$label)
	{
		$value=(isset($_POST["options_".$key])?$_POST["options_".$key]:$object->array_options["options_".$key]);
   		print '<tr><td';
   		if (! empty($extrafields->attribute_required[$key])) print ' class="fieldrequired"';
   		print '>'.$label.'</td><td colspan="3">';
		print $extrafields->showInputField($key,$value);
		print '</td></tr>'."\n";
	}
}

// Amount HT
print '<tr><td height="10">'.$langs->trans('AmountHT').'</td>';
print '<td align="right" nowrap><b>'.price($object->total_ht).'</b></td>';
print '<td>'.$langs->trans("Currency".$conf->currency).'</td>';
// Margin Infos
if (! empty($conf->margin->enabled)) {
	print '<td valign="top" width="50%" rowspan="4">';
	$object->displayMarginInfos();
	print '</td>';
}

print '</tr>';

// Amount VAT
print '<tr><td height="10">'.$langs->trans('AmountVAT').'</td>';
print '<td align="right" nowrap>'.price($object->total_tva).'</td>';
print '<td>'.$langs->trans("Currency".$conf->currency).'</td></tr>';

// Amount Local Taxes
if ($mysoc->localtax1_assuj=="1") //Localtax1
{
	print '<tr><td height="10">'.$langs->transcountry("AmountLT1",$mysoc->country_code).'</td>';
	print '<td align="right" nowrap>'.price($object->total_localtax1).'</td>';
	print '<td>'.$langs->trans("Currency".$conf->currency).'</td></tr>';
}
if ($mysoc->localtax2_assuj=="1") //Localtax2
{
	print '<tr><td height="10">'.$langs->transcountry("AmountLT2",$mysoc->country_code).'</td>';
	print '<td align="right" nowrap>'.price($object->total_localtax2).'</td>';
	print '<td>'.$langs->trans("Currency".$conf->currency).'</td></tr>';
}

// Amount TTC
print '<tr><td height="10">'.$langs->trans('AmountTTC').'</td>';
print '<td align="right" nowrap>'.price($object->total_ttc).'</td>';
print '<td>'.$langs->trans("Currency".$conf->currency).'</td></tr>';

print '</table>';
	

/*
 * Lines
 */

if (! empty($conf->use_javascript_ajax) && $object->statut == 0)
{
	include DOL_DOCUMENT_ROOT.'/core/tpl/ajaxrow.tpl.php';
}


// init th array
$object->lines = array();
// fill object lines
$result = $object->getLinesArray();

$numlines = count($object->lines);

if (! empty($conf->use_javascript_ajax) && $object->statut == 0)
{
	include DOL_DOCUMENT_ROOT.'/core/tpl/ajaxrow.tpl.php';
}

print '<table id="tablelines" class="noborder" width="100%">';
			print '	<form name="addproduct" id="addproduct" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.(($action != 'editline')?'#add':'#line_'.GETPOST('lineid')).'" method="POST">
			<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">
			<input type="hidden" name="action" value="'.(($action != 'editline')?'addline':'updateligne').'">
			<input type="hidden" name="mode" value="">
			<input type="hidden" name="id" value="'.$object->id.'">';
if (! empty($object->lines))
{
	// magouille les status ne collent pas 
	$object->element='ficheinter';
	if ($object->statut == 1)
		$object->statut = 0;	
	elseif  ($object->statut == 0)
		$object->statut = 1;		

	$ret=$object->printObjectLines($action,$mysoc,$soc,$lineid,0,$hookmanager);
 
	if ($object->statut == 1)
		$object->statut = 0;	
	elseif  ($object->statut == 0)
		$object->statut = 1;	
	$object->element='fichinter';
}
			print "</form>\n";
// Form to add new line only if fichinter validate

if ($object->statut == 1 && $user->rights->ficheinter->creer)
{
	if ($action != 'editline')
	{
		$var=true;

		// Add predefined products/services
		if (! empty($conf->product->enabled) || ! empty($conf->service->enabled))
		{
			print '	<form name="addproduct" id="addproduct" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.(($action != 'editline')?'#add':'#line_'.GETPOST('lineid')).'" method="POST">
			<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">
			<input type="hidden" name="action" value="'.(($action != 'editline')?'addline':'updateligne').'">
			<input type="hidden" name="mode" value="">
			<input type="hidden" name="id" value="'.$object->id.'">';
			print '<table id="tablelines" class="noborder noshadow" width="100%">';
			$var=!$var;
			$object->formAddObjectLine(0,$mysoc,$soc,$hookmanager);
			print "</table>\n";
			print "</form>\n";

			// on désactive la saisie libre (en javascript, pas le choix)
			print "<script>";
			print '$("#prod_entry_mode_free").hide();';
			print '$(\'label[for="select_type"]\').hide();';
			print '$("#select_type").hide();';
			print '$(\'input[name=prod_entry_mode][value="predef"]\').attr("checked", "checked");';
			print "</script>";
		}

		//$parameters=array();
		//$reshook=$hookmanager->executeHooks('formAddObjectLine',$parameters,$object,$action);	// Note that $action and $object may have been modified by hook
	}
}

print '</table>';

print '</div>';
print "\n";


/*
 * Boutons Actions
 */
	print '<div class="tabsAction">';

		// 0 = draft
		// 1 = Valided
		// 2 = Billed
		// 3 = Close
		// 4 = Partial Close

		// Send
		if ($object->statut > 0)
		{
			if (empty($conf->global->MAIN_USE_ADVANCED_PERMS) || $user->rights->ficheinter->ficheinter_advance->send)
			{
				print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=presend&amp;mode=init">'.$langs->trans('SendByMail').'</a>';
			}
			else print '<a class="butActionRefused" href="#">'.$langs->trans('SendByMail').'</a>';
		}
		if ($action != 'editline')
		{
			// Invoicing
			if (! empty($conf->facture->enabled) && $object->statut > 0)
			{
				$langs->load("bills");
				if ($object->statut > 2 )
				{
					$objectelement="management_managementfichinter";
					//$objectelement =$object->element;
					if ($user->rights->facture->creer) print '<a class="butAction" href="'.DOL_URL_ROOT.'/compta/facture.php?action=create&amp;origin='.$objectelement.'&amp;originid='.$object->id.'&amp;socid='.$object->socid.'">'.$langs->trans("CreateBill").'</a>';
					else print '<a class="butActionRefused" href="#" title="'.$langs->trans("NotEnoughPermissions").'">'.$langs->trans("CreateBill").'</a>';
				}
			}
			// partial Close
			if ($object->statut == 1  && $user->rights->ficheinter->creer )
			{
				print '<a class="butAction" href="rapport.php?id='.$object->id.'&action=classifypartialclosed"';
				print '>'.$langs->trans("PartialClose").'</a>';
			}
			//  Close
			if (($object->statut == 1 || $object->statut == 3) && $user->rights->ficheinter->creer )
			{
				print '<a class="butAction" href="rapport.php?id='.$object->id.'&action=classifyclosed"';
				print '>'.$langs->trans("Close").'</a>';
			}
			
			//  Close
			if (($object->statut == 1 || $object->statut == 3) && $user->rights->ficheinter->creer )
			{
				print '<a class="butAction" href="rapport.php?id='.$object->id.'&action=classifyclosednottobill"';
				print '>'.$langs->trans("CloseNotToBill").'</a>';
			}
			//  Reopen
			if (($object->statut > 1 ) && $user->rights->ficheinter->creer )
			{
				print '<a class="butAction" href="rapport.php?id='.$object->id.'&action=reopen"';
				print '>'.$langs->trans("Reopen").'</a>';
			}

		}
	print '</div>';
	print '<br>';
	
/*
 * Action presend
 */
if ($action == 'presend')
{
	$ref = dol_sanitizeFileName($object->ref);
	include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	$fileparams = dol_most_recent_file($conf->ficheinter->dir_output . '/' . $ref, preg_quote($ref,'/'));
	$file=$fileparams['fullname'];
	
	// Build document if it not exists
	if (! $file || ! is_readable($file))
	{
		// Define output language
		$outputlangs = $langs;
		$newlang='';
		if ($conf->global->MAIN_MULTILANGS && empty($newlang) && ! empty($_REQUEST['lang_id'])) $newlang=$_REQUEST['lang_id'];
		if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->thirdparty->default_lang;
		if (! empty($newlang))
		{
			$outputlangs = new Translate("",$conf);
			$outputlangs->setDefaultLang($newlang);
		}
		
		$result=fichinter_create($db, $object, GETPOST('model')?GETPOST('model'):$object->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref, $hookmanager);
		if ($result <= 0)
		{
			dol_print_error($db,$result);
			exit;
		}
		$fileparams = dol_most_recent_file($conf->ficheinter->dir_output . '/' . $ref, preg_quote($ref,'/'));
		$file=$fileparams['fullname'];
	}
	
	print '<br>';
	print_titre($langs->trans('SendInterventionByMail'));
	
	// Create form object
	include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
	$formmail = new FormMail($db);
	$formmail->fromtype = 'user';
	$formmail->fromid   = $user->id;
	$formmail->fromname = $user->getFullName($langs);
	$formmail->frommail = $user->email;
	$formmail->withfrom=1;
	$formmail->withto=(!GETPOST('sendto','alpha'))?1:GETPOST('sendto','alpha');
	$formmail->withtosocid=$societe->id;
	$formmail->withtocc=1;
	$formmail->withtoccsocid=0;
	$formmail->withtoccc=$conf->global->MAIN_EMAIL_USECCC;
	$formmail->withtocccsocid=0;
	$formmail->withtopic=$langs->trans('SendInterventionRef','__FICHINTERREF__');
	$formmail->withfile=2;
	$formmail->withbody=1;
	$formmail->withdeliveryreceipt=1;
	$formmail->withcancel=1;
	
	// Tableau des substitutions
	$formmail->substit['__FICHINTERREF__']=$object->ref;
	$formmail->substit['__SIGNATURE__']=$user->signature;
	$formmail->substit['__PERSONALIZED__']='';
	// Tableau des parametres complementaires
	$formmail->param['action']='send';
	$formmail->param['models']='fichinter_send';
	$formmail->param['fichinter_id']=$object->id;
	$formmail->param['returnurl']=$_SERVER["PHP_SELF"].'?id='.$object->id;
	
	// Init list of files
	if (GETPOST("mode")=='init')
	{
		$formmail->clear_attached_files();
		$formmail->add_attached_files($file,basename($file),dol_mimetype($file));
	}
	
	$formmail->show_form();
	
	print '<br>';
}

// End of page
llxFooter();
$db->close();
?>