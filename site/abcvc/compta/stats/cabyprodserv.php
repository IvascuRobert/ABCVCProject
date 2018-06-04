<?php
/* Copyright (C) 2013      Antoine Iauch	   <aiauch@gpcsolutions.fr>
 * Copyright (C) 2013-2016 Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) 2015      Raphaël Doursenaud  <rdoursenaud@gpcsolutions.fr>
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
 *	   \file		htdocs/compta/stats/cabyprodserv.php
 *	   \brief	   Page reporting TO by Products & Services
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/report.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/tax.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

$langs->load("products");
$langs->load("categories");
$langs->load("errors");

// Security pack (data & check)
$socid = GETPOST('socid','int');

if ($user->societe_id > 0) $socid = $user->societe_id;
if (! empty($conf->comptabilite->enabled)) $result=restrictedArea($user,'compta','','','resultat');
if (! empty($conf->accounting->enabled)) $result=restrictedArea($user,'accounting','','','comptarapport');

// Define modecompta ('CREANCES-DETTES' or 'RECETTES-DEPENSES')
$modecompta = $conf->global->ACCOUNTING_MODE;
if (GETPOST("modecompta")) $modecompta=GETPOST("modecompta");

$sortorder=isset($_GET["sortorder"])?$_GET["sortorder"]:$_POST["sortorder"];
$sortfield=isset($_GET["sortfield"])?$_GET["sortfield"]:$_POST["sortfield"];
if (! $sortorder) $sortorder="asc";
if (! $sortfield) $sortfield="ref";

// Category
$selected_cat = (int) GETPOST('search_categ', 'int');
$subcat = false;
if (GETPOST('subcat', 'alpha') === 'yes') {
	$subcat = true;
}
// product/service
$selected_type = GETPOST('search_type', 'int');
if ($selected_type =='') $selected_type = -1;

// Date range
$year=GETPOST("year");
$month=GETPOST("month");
$date_startyear = GETPOST("date_startyear");
$date_startmonth = GETPOST("date_startmonth");
$date_startday = GETPOST("date_startday");
$date_endyear = GETPOST("date_endyear");
$date_endmonth = GETPOST("date_endmonth");
$date_endday = GETPOST("date_endday");
if (empty($year))
{
	$year_current = strftime("%Y",dol_now());
	$month_current = strftime("%m",dol_now());
	$year_start = $year_current;
} else {
	$year_current = $year;
	$month_current = strftime("%m",dol_now());
	$year_start = $year;
}
$date_start=dol_mktime(0,0,0,$_REQUEST["date_startmonth"],$_REQUEST["date_startday"],$_REQUEST["date_startyear"]);
$date_end=dol_mktime(23,59,59,$_REQUEST["date_endmonth"],$_REQUEST["date_endday"],$_REQUEST["date_endyear"]);
// Quarter
if (empty($date_start) || empty($date_end)) // We define date_start and date_end
{
	$q=GETPOST("q")?GETPOST("q"):0;
	if ($q==0)
	{
		// We define date_start and date_end
		$month_start=GETPOST("month")?GETPOST("month"):($conf->global->SOCIETE_FISCAL_MONTH_START?($conf->global->SOCIETE_FISCAL_MONTH_START):1);
		$year_end=$year_start;
		$month_end=$month_start;
		if (! GETPOST("month"))	// If month not forced
		{
			if (! GETPOST('year') && $month_start > $month_current)
			{
				$year_start--;
				$year_end--;
			}
			$month_end=$month_start-1;
			if ($month_end < 1) $month_end=12;
			else $year_end++;
		}
		$date_start=dol_get_first_day($year_start,$month_start,false); $date_end=dol_get_last_day($year_end,$month_end,false);
	}
	if ($q==1) { $date_start=dol_get_first_day($year_start,1,false); $date_end=dol_get_last_day($year_start,3,false); }
	if ($q==2) { $date_start=dol_get_first_day($year_start,4,false); $date_end=dol_get_last_day($year_start,6,false); }
	if ($q==3) { $date_start=dol_get_first_day($year_start,7,false); $date_end=dol_get_last_day($year_start,9,false); }
	if ($q==4) { $date_start=dol_get_first_day($year_start,10,false); $date_end=dol_get_last_day($year_start,12,false); }
} else {
	// TODO We define q
}

$commonparams=array();
$commonparams['modecompta']=$modecompta;
$commonparams['sortorder'] = $sortorder;
$commonparams['sortfield'] = $sortfield;

$headerparams = array();
$headerparams['date_startyear'] = $date_startyear;
$headerparams['date_startmonth'] = $date_startmonth;
$headerparams['date_startday'] = $date_startday;
$headerparams['date_endyear'] = $date_endyear;
$headerparams['date_endmonth'] = $date_endmonth;
$headerparams['date_endday'] = $date_endday;
$headerparams['q'] = $q;

$tableparams = array();
$tableparams['search_categ'] = $selected_cat;
$tableparams['search_type'] = $selected_type;
$tableparams['subcat'] = ($subcat === true)?'yes':'';

// Adding common parameters
$allparams = array_merge($commonparams, $headerparams, $tableparams);
$headerparams = array_merge($commonparams, $headerparams);
$tableparams = array_merge($commonparams, $tableparams);

foreach($allparams as $key => $value) {
	$paramslink .= '&' . $key . '=' . $value;
}


/*
 * View
 */
llxHeader();
$form=new Form($db);
$formother = new FormOther($db);

// Show report header
$nom=$langs->trans("SalesTurnover").', '.$langs->trans("ByProductsAndServices");

if ($modecompta=="CREANCES-DETTES") {
	$calcmode=$langs->trans("CalcModeDebt");
	$calcmode.='<br>('.$langs->trans("SeeReportInInputOutputMode",'<a href="'.$_SERVER["PHP_SELF"].'?year='.$year.'&modecompta=RECETTES-DEPENSES">','</a>').')';

	$period=$form->select_date($date_start,'date_start',0,0,0,'',1,0,1).' - '.$form->select_date($date_end,'date_end',0,0,0,'',1,0,1);

	$description=$langs->trans("RulesCADue");
	if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) {
		$description.= $langs->trans("DepositsAreNotIncluded");
	} else {
		$description.= $langs->trans("DepositsAreIncluded");
	}

	$builddate=time();
} else {
	$calcmode=$langs->trans("CalcModeEngagement");
	$calcmode.='<br>('.$langs->trans("SeeReportInDueDebtMode",'<a href="'.$_SERVER["PHP_SELF"].'?year='.$year.'&modecompta=CREANCES-DETTES">','</a>').')';

	$period=$form->select_date($date_start,'date_start',0,0,0,'',1,0,1).' - '.$form->select_date($date_end,'date_end',0,0,0,'',1,0,1);

	$description=$langs->trans("RulesCAIn");
	$description.= $langs->trans("DepositsAreIncluded");

	$builddate=time();
}

report_header($nom,$nomlink,$period,$periodlink,$description,$builddate,$exportlink,$tableparams,$calcmode);

if (! empty($conf->accounting->enabled))
{
    print info_admin($langs->trans("WarningReportNotReliable"), 0, 0, 1);
}


// SQL request
$catotal=0;
$catotal_ht=0;
$qtytotal=0;

if ($modecompta == 'CREANCES-DETTES')
{
	$sql = "SELECT DISTINCT p.rowid as rowid, p.ref as ref, p.label as label, p.fk_product_type as product_type,";
	$sql.= " SUM(l.total_ht) as amount, SUM(l.total_ttc) as amount_ttc,";
	$sql.= " SUM(CASE WHEN f.type = 2 THEN -l.qty ELSE l.qty END) as qty";
	$sql.= " FROM ".MAIN_DB_PREFIX."facture as f, ".MAIN_DB_PREFIX."facturedet as l, ".MAIN_DB_PREFIX."product as p";
	if ($selected_cat === -2)	// Without any category
	{
		$sql.= " LEFT OUTER JOIN ".MAIN_DB_PREFIX."categorie_product as cp ON p.rowid = cp.fk_product";
	}
	else if ($selected_cat) 	// Into a specific category
	{
		$sql.= ", ".MAIN_DB_PREFIX."categorie as c, ".MAIN_DB_PREFIX."categorie_product as cp";
	}
	$sql.= " WHERE l.fk_product = p.rowid";
	$sql.= " AND l.fk_facture = f.rowid";
	$sql.= " AND f.fk_statut in (1,2)";
	if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) {
		$sql.= " AND f.type IN (0,1,2,5)";
	} else {
	$sql.= " AND f.type IN (0,1,2,3,5)";
	}
	if ($date_start && $date_end) {
		$sql.= " AND f.datef >= '".$db->idate($date_start)."' AND f.datef <= '".$db->idate($date_end)."'";
	}
	if ($selected_type >=0)
	{
		$sql.= " AND l.product_type = ".$selected_type;
	}
	if ($selected_cat === -2)	// Without any category
	{
		$sql.=" AND cp.fk_product is null";
	}
	else if ($selected_cat) {	// Into a specific category
		$sql.= " AND (c.rowid = ".$selected_cat;
		if ($subcat) $sql.=" OR c.fk_parent = " . $selected_cat;
		$sql.= ")";
		$sql.= " AND cp.fk_categorie = c.rowid AND cp.fk_product = p.rowid";
	}
	$sql.= " AND f.entity = ".$conf->entity;
	$sql.= " GROUP BY p.rowid, p.ref, p.label, p.fk_product_type";
	$sql.= $db->order($sortfield,$sortorder);

	dol_syslog("cabyprodserv", LOG_DEBUG);
	$result = $db->query($sql);
	if ($result) {
		$num = $db->num_rows($result);
		$i=0;
		while ($i < $num) {
			$obj = $db->fetch_object($result);
			$amount_ht[$obj->rowid] = $obj->amount;
			$amount[$obj->rowid] = $obj->amount_ttc;
			$qty[$obj->rowid] = $obj->qty;
			$name[$obj->rowid] = $obj->ref . '&nbsp;-&nbsp;' . $obj->label;
			$type[$obj->rowid] = $obj->product_type;
			$catotal_ht+=$obj->amount;
			$catotal+=$obj->amount_ttc;
			$qtytotal+=$obj->qty;
			$i++;
		}
	} else {
		dol_print_error($db);
	}

	// Show Array
	$i=0;
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	// Extra parameters management
	foreach($headerparams as $key => $value)
	{
		print '<input type="hidden" name="'.$key.'" value="'.$value.'">';
	}

    $moreforfilter='';

    print '<div class="div-table-responsive">';
    print '<table class="tagtable liste'.($moreforfilter?" listwithfilterbefore":"").'">'."\n";
    
	// Category filter
	print '<tr class="liste_titre">';
	print '<td>';
	print $langs->trans("Category") . ': ' . $formother->select_categories(Categorie::TYPE_PRODUCT, $selected_cat, 'search_categ', true);
	print ' ';
	print $langs->trans("SubCats") . '? ';
	print '<input type="checkbox" name="subcat" value="yes"';
	if ($subcat) {
		print ' checked';
	}
	print '>';
    // type filter (produit/service)
    print ' ';
    print $langs->trans("Type"). ': ';
    $form->select_type_of_lines(isset($selected_type)?$selected_type:-1,'search_type',1,1,1);
    print '</td>';
	
    print '<td colspan="5" align="right">';
	print '<input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'"  value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
	print '</td></tr>';
	
	// Array header
	print "<tr class=\"liste_titre\">";
	print_liste_field_titre(
		$langs->trans("Product"),
		$_SERVER["PHP_SELF"],
		"ref",
		"",
		$paramslink,
		"",
		$sortfield,
		$sortorder
	);
	print_liste_field_titre(
		$langs->trans('Quantity'),
		$_SERVER["PHP_SELF"],
		"qty",
		"",
		$paramslink,
		'align="right"',
		$sortfield,
		$sortorder
	);
	print_liste_field_titre(
		$langs->trans("Percentage"),
		$_SERVER["PHP_SELF"],
		"qty",
		"",
		$paramslink,
		'align="right"',
		$sortfield,
		$sortorder
	);
	print_liste_field_titre(
		$langs->trans('AmountHT'),
		$_SERVER["PHP_SELF"],
		"amount",
		"",
		$paramslink,
		'align="right"',
		$sortfield,
		$sortorder
	);
	print_liste_field_titre(
		$langs->trans("AmountTTC"),
		$_SERVER["PHP_SELF"],
		"amount_ttc",
		"",
		$paramslink,
		'align="right"',
		$sortfield,
		$sortorder
	);
	print_liste_field_titre(
		$langs->trans("Percentage"),
		$_SERVER["PHP_SELF"],
		"amount_ttc",
		"",
		$paramslink,
		'align="right"',
		$sortfield,
		$sortorder
	);
	print "</tr>\n";

	// Array Data
	$var=true;

	if (count($name)) {
		foreach($name as $key=>$value) {
			$var=!$var;
			print "<tr ".$bc[$var].">";

			// Product
			$fullname=$name[$key];
			if ($key >= 0) {
				$linkname='<a href="'.DOL_URL_ROOT.'/product/card.php?id='.$key.'">'.img_object($langs->trans("ShowProduct"),$type[$key]==0?'product':'service').' '.$fullname.'</a>';
			} else {
				$linkname=$langs->trans("PaymentsNotLinkedToProduct");
			}

			print "<td>".$linkname."</td>\n";
			
			// Quantity
			print '<td align="right">';
			print $qty[$key];
			print '</td>';
			
			// Percent;
			print '<td align="right">'.($qtytotal > 0 ? round(100 * $qty[$key] / $qtytotal, 2).'%' : '&nbsp;').'</td>';
	
			// Amount w/o VAT
			print '<td align="right">';
			/*if ($key > 0) {
				print '<a href="'.DOL_URL_ROOT.SUPP_PATH.'/compta/facture/list.php?productid='.$key.'">';
			} else {
				print '<a href="#">';
			}*/
			print price($amount_ht[$key]);
			//print '</a>';
			print '</td>';
	
			// Amount with VAT
			print '<td align="right">';
			/*if ($key > 0) {
				print '<a href="'.DOL_URL_ROOT.SUPP_PATH.'/compta/facture/list.php?productid='.$key.'">';
			} else {
				print '<a href="#">';
			}*/
			print price($amount[$key]);
			//print '</a>';
			print '</td>';
	
			// Percent;
			print '<td align="right">'.($catotal > 0 ? round(100 * $amount[$key] / $catotal, 2).'%' : '&nbsp;').'</td>';
	
			// TODO: statistics?
	
			print "</tr>\n";
			$i++;
		}

		// Total
		print '<tr class="liste_total">';
		print '<td>'.$langs->trans("Total").'</td>';
		print '<td align="right">'.price($qtytotal).'</td>';
		print '<td>&nbsp;</td>';
		print '<td align="right">'.price($catotal_ht).'</td>';
		print '<td align="right">'.price($catotal).'</td>';
		print '<td>&nbsp;</td>';
		print '</tr>';

		$db->free($result);
	}
	print "</table>";
	print '</div>';
	
	print '</form>';
} else {
	// $modecompta != 'CREANCES-DETTES'
	// "Calculation of part of each product for accountancy in this mode is not possible. When a partial payment (for example 5 euros) is done on an
	// invoice with 2 product (product A for 10 euros and product B for 20 euros), what is part of paiment for product A and part of paiment for product B ?
	// Because there is no way to know this, this report is not relevant.
	print '<br>'.$langs->trans("TurnoverPerProductInCommitmentAccountingNotRelevant") . '<br>';
}

llxFooter();
$db->close();
