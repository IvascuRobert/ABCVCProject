<?php
/* Copyright (C) 2001-2006  Rodolphe Quiedeville    <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012  Regis Houssin           <regis.houssin@capnetworks.com>
 * Copyright (C) 2013       Cédric Salvador         <csalvador@gpcsolutions.fr>
 * Copyright (C) 2015       Raphaël Doursenaud      <rdoursenaud@gpcsolutions.fr>
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
 *  \file       htdocs/product/reassort.php
 *  \ingroup    produit
 *  \brief      Page to list stocks
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';

$langs->load("products");
$langs->load("stocks");

// Security check
if ($user->societe_id) $socid=$user->societe_id;
$result=restrictedArea($user,'produit|service');


$action=GETPOST('action','alpha');
$sref=GETPOST("sref");
$snom=GETPOST("snom");
$sall=GETPOST("sall");
$type=GETPOST("type","int");
$sbarcode=GETPOST("sbarcode");
$catid=GETPOST('catid','int');
$toolowstock=GETPOST('toolowstock');
$tosell = GETPOST("tosell");
$tobuy = GETPOST("tobuy");
$fourn_id = GETPOST("fourn_id",'int');

$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
$page = GETPOST("page",'int');
if (! $sortfield) $sortfield="p.ref";
if (! $sortorder) $sortorder="ASC";
$limit = GETPOST('limit')?GETPOST('limit','int'):$conf->liste_limit;
$offset = $limit * $page ;

// Load sale and categ filters
$search_sale = GETPOST("search_sale");
$search_categ = GETPOST("search_categ");

// Get object canvas (By default, this is not defined, so standard usage of dolibarr)
$canvas=GETPOST("canvas");
$objcanvas=null;
if (! empty($canvas))
{
	require_once DOL_DOCUMENT_ROOT.'/core/class/canvas.class.php';
	$objcanvas = new Canvas($db,$action);
	$objcanvas->getCanvas('product','list',$canvas);
}

if (! empty($_POST["button_removefilter_x"]))
{
    $sref="";
    $snom="";
    $sall="";
    $search_sale="";
    $search_categ="";
    $type="";
    $catid='';
    $toolowstock='';
}

// Define virtualdiffersfromphysical
$virtualdiffersfromphysical=0;
if (! empty($conf->global->STOCK_CALCULATE_ON_SHIPMENT) || ! empty($conf->global->STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER))
{
    $virtualdiffersfromphysical=1;		// According to increase/decrease stock options, virtual and physical stock may differs.
}



/*
 * Actions
 */

// None



/*
 * View
 */

$helpurl='EN:Module_Stocks_En|FR:Module_Stock|ES:M&oacute;dulo_Stocks';

$form=new Form($db);
$htmlother=new FormOther($db);

$title=$langs->trans("ProductsAndServices");

$sql = 'SELECT p.rowid, p.ref, p.label, p.barcode, p.price, p.price_ttc, p.price_base_type, p.entity,';
$sql.= ' p.fk_product_type, p.tms as datem,';
$sql.= ' p.duration, p.tosell as statut, p.tobuy, p.seuil_stock_alerte, p.desiredstock,';
$sql.= ' SUM(s.reel) as stock_physique';
$sql.= ' FROM '.MAIN_DB_PREFIX.'product as p';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_stock as s on p.rowid = s.fk_product';
// We'll need this table joined to the select in order to filter by categ
if ($search_categ) $sql.= ", ".MAIN_DB_PREFIX."categorie_product as cp";
$sql.= " WHERE p.entity IN (".getEntity('product', 1).")";
if ($search_categ) $sql.= " AND p.rowid = cp.fk_product";	// Join for the needed table to filter by categ
if ($sall)
{
	$sql.= " AND (p.ref LIKE '%".$db->escape($sall)."%' OR p.label LIKE '%".$db->escape($sall)."%' OR p.description LIKE '%".$db->escape($sall)."%' OR p.note LIKE '%".$db->escape($sall)."%')";
}
// if the type is not 1, we show all products (type = 0,2,3)
if (dol_strlen($type))
{
    if ($type==1)
    {
        $sql.= " AND p.fk_product_type = '1'";
    }
    else
    {
        $sql.= " AND p.fk_product_type <> '1'";
    }
}
if ($sref)     $sql.= " AND p.ref LIKE '%".$sref."%'";
if ($sbarcode) $sql.= " AND p.barcode LIKE '%".$sbarcode."%'";
if ($snom)     $sql.= " AND p.label LIKE '%".$db->escape($snom)."%'";
if (! empty($tosell))
{
	$sql.= " AND p.tosell = ".$tosell;
}
if (! empty($tobuy))
{
    $sql.= " AND p.tobuy = ".$tobuy;
}
if (! empty($canvas))
{
    $sql.= " AND p.canvas = '".$db->escape($canvas)."'";
}
if($catid)
{
	$sql.= " AND cp.fk_categorie = ".$catid;
}
if ($fourn_id > 0)
{
	$sql.= " AND p.rowid = pf.fk_product AND pf.fk_soc = ".$fourn_id;
}
// Insert categ filter
if ($search_categ)
{
	$sql .= " AND cp.fk_categorie = ".$db->escape($search_categ);
}
$sql.= " GROUP BY p.rowid, p.ref, p.label, p.barcode, p.price, p.price_ttc, p.price_base_type, p.entity,";
$sql.= " p.fk_product_type, p.tms, p.duration, p.tosell, p.tobuy, p.seuil_stock_alerte, p.desiredstock";
if ($toolowstock) $sql.= " HAVING SUM(".$db->ifsql('s.reel IS NULL', '0', 's.reel').") < p.seuil_stock_alerte";    // Not used yet
$sql.= $db->order($sortfield,$sortorder);
$sql.= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);

if ($resql)
{
	$num = $db->num_rows($resql);

	$i = 0;

	if ($num == 1 && ($sall or $snom or $sref))
	{
		$objp = $db->fetch_object($resql);
		header("Location: card.php?id=$objp->rowid");
		exit;
	}

	if (isset($type))
	{
		if ($type==1) { $texte = $langs->trans("Services"); }
		else { $texte = $langs->trans("Products"); }
	} else {
		$texte = $langs->trans("ProductsAndServices");
	}
	$texte.=' ('.$langs->trans("Stocks").')';


	llxHeader("", $texte, $helpurl);

	if ($sref || $snom || $sall || GETPOST('search'))
	{
		print_barre_liste($texte, $page, $_SERVER["PHP_SELF"], "&sref=".$sref."&snom=".$snom."&amp;sall=".$sall."&amp;tosell=".$tosell."&amp;tobuy=".$tobuy.(!empty($search_categ) ? '&amp;search_categ='.$search_categ : '').(!empty($toolowstock) ? '&amp;toolowstock='.$toolowstock : ''), $sortfield, $sortorder,'',$num, 0, 'title_products');
	}
	else
	{
		print_barre_liste($texte, $page, $_SERVER["PHP_SELF"], "&sref=$sref&snom=$snom&fourn_id=$fourn_id".(isset($type)?"&amp;type=$type":"").(!empty($search_categ) ? '&amp;search_categ='.$search_categ : '').(!empty($toolowstock) ? '&amp;toolowstock='.$toolowstock : ''), $sortfield, $sortorder,'',$num, 0, 'title_products');
	}

	if (! empty($catid))
	{
		print "<div id='ways'>";
		$c = new Categorie($db);
		$c->fetch($catid);
		$ways = $c->print_all_ways(' &gt; ','product/reassort.php');
		print " &gt; ".$ways[0]."<br>\n";
		print "</div><br>";
	}

	print '<form action="'. $_SERVER["PHP_SELF"] .'" method="post" name="formulaire">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
	print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
	print '<input type="hidden" name="type" value="'.$type.'">';

	// Filter on categories
 	$moreforfilter='';
	if (! empty($conf->categorie->enabled))
	{
	 	$moreforfilter.='<div class="divsearchfield">';
	 	$moreforfilter.=$langs->trans('Categories'). ': ';
		$moreforfilter.=$htmlother->select_categories(Categorie::TYPE_PRODUCT,$search_categ,'search_categ');
	 	$moreforfilter.='</div>';
	}
	
	$moreforfilter.='<div class="divsearchfield">';
	$moreforfilter.=$langs->trans("StockTooLow").' <input type="checkbox" name="toolowstock" value="1"'.($toolowstock?' checked':'').'>';
	$moreforfilter.='</div>';
    
    if (! empty($moreforfilter))
    {
        print '<div class="liste_titre liste_titre_bydiv centpercent">';
        print $moreforfilter;
        $parameters=array();
        $reshook=$hookmanager->executeHooks('printFieldPreListTitle',$parameters);    // Note that $action and $object may have been modified by hook
        print $hookmanager->resPrint;
        print '</div>';
    }
	
	$param='';
	if ($tosell)	$param.="&tosell=".$tosell;
	if ($tobuy)		$param.="&tobuy=".$tobuy;
	if ($type)		$param.="&type=".$type;
	if ($fourn_id)	$param.="&fourn_id=".$fourn_id;
	if ($snom)		$param.="&snom=".$snom;
	if ($sref)		$param.="&sref=".$sref;
    
	$formProduct = new FormProduct($db);
	$formProduct->loadWarehouses();
	$warehouses_list = $formProduct->cache_warehouses;
	$nb_warehouse = count($warehouses_list);
	$colspan_warehouse = 1;
	if (! empty($conf->global->STOCK_DETAIL_ON_WAREHOUSE)) { $colspan_warehouse = $nb_warehouse > 1 ? $nb_warehouse+1 : 1; }
	
	print '<table class="tagtable liste'.($moreforfilter?" listwithfilterbefore":"").'">';
	
	// Lignes des titres
	print "<tr class=\"liste_titre\">";
	print_liste_field_titre($langs->trans("Ref"), $_SERVER["PHP_SELF"], "p.ref",$param,"","",$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Label"), $_SERVER["PHP_SELF"], "p.label",$param,"","",$sortfield,$sortorder);
	if (! empty($conf->service->enabled) && $type == 1) print_liste_field_titre($langs->trans("Duration"), $_SERVER["PHP_SELF"], "p.duration",$param,"",'align="center"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("StockLimit"), $_SERVER["PHP_SELF"], "p.seuil_stock_alerte",$param,"",'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("DesiredStock"), $_SERVER["PHP_SELF"], "p.desiredstock",$param,"",'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("PhysicalStock"), $_SERVER["PHP_SELF"], "stock_physique",$param,"",'align="right"',$sortfield,$sortorder);
	// Details per warehouse
	if (! empty($conf->global->STOCK_DETAIL_ON_WAREHOUSE))	// TODO This should be moved into the selection of fields on page product/list (page product/stock will be removed and replaced with product/list with its own context)
	{
		if ($nb_warehouse>1) {
			foreach($warehouses_list as &$wh) {
				print_liste_field_titre($wh['label'], '', '','','','align="right"');
			}
			
		}
	} 
	if ($virtualdiffersfromphysical) print_liste_field_titre($langs->trans("VirtualStock"),$_SERVER["PHP_SELF"], "",$param,"",'align="right"',$sortfield,$sortorder);
	print_liste_field_titre('');
	print_liste_field_titre($langs->trans("Status").' ('.$langs->trans("Sell").')',$_SERVER["PHP_SELF"], "p.tosell",$param,"",'align="right"',$sortfield,$sortorder);
    print_liste_field_titre($langs->trans("Status").' ('.$langs->trans("Buy").')',$_SERVER["PHP_SELF"], "p.tobuy",$param,"",'align="right"',$sortfield,$sortorder);
	print "</tr>\n";

	// Lignes des champs de filtre
	print '<tr class="liste_titre">';
	print '<td class="liste_titre">';
	print '<input class="flat" type="text" name="sref" size="6" value="'.$sref.'">';
	print '</td>';
	print '<td class="liste_titre">';
	print '<input class="flat" type="text" name="snom" size="8" value="'.$snom.'">';
	print '</td>';
	// Duration
	if (! empty($conf->service->enabled) && $type == 1)
	{
		print '<td class="liste_titre">';
		print '&nbsp;';
		print '</td>';
	}
	// Stock limit
	print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre" align="right">&nbsp;</td>';
	print '<td class="liste_titre">&nbsp;</td>';
	if ($virtualdiffersfromphysical) print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre" colspan="'.$colspan_warehouse.'">&nbsp;</td>';
	print '<td class="liste_titre" align="right">';
   	$searchpitco=$form->showFilterAndCheckAddButtons(0);
   	print $searchpitco;
	print '</td>';
	print '</tr>';

	$var=True;
	while ($i < min($num,$limit))
	{
		$objp = $db->fetch_object($resql);

		$var=!$var;
		print '<tr '.$bc[$var].'><td class="nowrap">';
		
		$product=new Product($db);
		$product->fetch($objp->rowid);
		$product->load_stock();
		
		print $product->getNomUrl(1,'',16);
		//if ($objp->stock_theorique < $objp->seuil_stock_alerte) print ' '.img_warning($langs->trans("StockTooLow"));
		print '</td>';
		print '<td>'.$product->label.'</td>';

		if (! empty($conf->service->enabled) && $type == 1)
		{
			print '<td align="center">';
			if (preg_match('/([0-9]+)y/i',$objp->duration,$regs)) print $regs[1].' '.$langs->trans("DurationYear");
			elseif (preg_match('/([0-9]+)m/i',$objp->duration,$regs)) print $regs[1].' '.$langs->trans("DurationMonth");
			elseif (preg_match('/([0-9]+)d/i',$objp->duration,$regs)) print $regs[1].' '.$langs->trans("DurationDay");
			else print $objp->duration;
			print '</td>';
		}
		//print '<td align="right">'.$objp->stock_theorique.'</td>';
		print '<td align="right">'.$objp->seuil_stock_alerte.'</td>';
		print '<td align="right">'.$objp->desiredstock.'</td>';
		// Real stock
		print '<td align="right">';
        if ($objp->seuil_stock_alerte != '' && ($objp->stock_physique < $objp->seuil_stock_alerte)) print img_warning($langs->trans("StockTooLow")).' ';
		print $objp->stock_physique;
		print '</td>';
		
		// Details per warehouse
		if (! empty($conf->global->STOCK_DETAIL_ON_WAREHOUSE))	// TODO This should be moved into the selection of fields on page product/list (page product/stock will be removed and replaced with product/list with its own context)
		{
			if($nb_warehouse>1) {
				foreach($warehouses_list as &$wh) {
					
					print '<td align="right">';
					print empty($product->stock_warehouse[$wh['id']]->real) ? '0' : $product->stock_warehouse[$wh['id']]->real;
					print '</td>';
				}
			}			
		} 
		
			
		
		// Virtual stock
		if ($virtualdiffersfromphysical)
		{
	    		print '<td align="right">';
			if ($objp->seuil_stock_alerte != '' && ($product->stock_theorique < $objp->seuil_stock_alerte)) print img_warning($langs->trans("StockTooLow")).' ';
	    		print $product->stock_theorique;
	    		print '</td>';
		}
		print '<td align="right"><a href="'.DOL_URL_ROOT.'/product/stock/mouvement.php?idproduct='.$product->id.'">'.$langs->trans("Movements").'</a></td>';
		print '<td align="right" class="nowrap">'.$product->LibStatut($objp->statut,5,0).'</td>';
        	print '<td align="right" class="nowrap">'.$product->LibStatut($objp->tobuy,5,1).'</td>';
		print "</tr>\n";
		$i++;
	}

	print "</table>";
	print '</form>';

	if ($num > $conf->liste_limit)
	{
		if ($sref || $snom || $sall || GETPOST('search'))
		{
	  		print_barre_liste('', $page, "reassort.php", "&sref=".$sref."&snom=".$snom."&amp;sall=".$sall."&amp;tosell=".$tosell."&amp;tobuy=".$tobuy.(!empty($search_categ) ? '&amp;search_categ='.$search_categ : '').(!empty($toolowstock) ? '&amp;toolowstock='.$toolowstock : ''), $sortfield, $sortorder,'',$num, 0, '');
		}
		else
		{
	  		print_barre_liste('', $page, "reassort.php", "&sref=$sref&snom=$snom&fourn_id=$fourn_id".(isset($type)?"&amp;type=$type":"")."&amp;tosell=".$tosell."&amp;tobuy=".$tobuy.(!empty($search_categ) ? '&amp;search_categ='.$search_categ : '').(!empty($toolowstock) ? '&amp;toolowstock='.$toolowstock : ''), $sortfield, $sortorder,'',$num, 0, '');
		}
	}

	$db->free($resql);

}
else
{
	dol_print_error($db);
}


llxFooter();
$db->close();
