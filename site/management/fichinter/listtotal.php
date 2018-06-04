<?php
/* Copyright (C) 2002-2003 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis@dolibarr.fr>
 * Copyright (C) 2011-2012 Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2012-2016 Charlie Benke     	<charlie@patas-monkey.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
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
 *	\file       management/fichinter/listtotal.php
 *	\brief      List of all interventions
 *	\ingroup    ficheinter
 */
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");		// For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");	// For "custom" directory

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';

// on enrichi la classe fichinter
dol_include_once("/management/class/managementfichinter.class.php"); 

require_once(DOL_DOCUMENT_ROOT."/contact/class/contact.class.php");
require_once(DOL_DOCUMENT_ROOT."/projet/class/project.class.php");
require_once DOL_DOCUMENT_ROOT."/contrat/class/contrat.class.php";
require_once(DOL_DOCUMENT_ROOT."/core/lib/date.lib.php");

$langs->load("companies");
$langs->load("interventions");
$langs->load("management@management");

$socid=GETPOST('socid','int');

// Security check
$fichinterid = GETPOST('id','int');
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'ficheinter', $fichinterid,'fichinter');

$sortfield = GETPOST('sortfield','alpha');
$sortorder = GETPOST('sortorder','alpha');
$page = GETPOST('page','int');
if ($page == -1) {
	$page = 0;
}
$offset = $conf->liste_limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

if (! $sortorder) $sortorder="DESC";
if (! $sortfield) $sortfield="f.dateo";
if ($page == -1) { $page = 0 ; }

$limit = $conf->liste_limit;
$offset = $limit * $page ;
$pageprev = $page - 1;
$pagenext = $page + 1;

$search_ref=GETPOST('search_ref','alpha');
$search_company=GETPOST('search_company','alpha');
$search_desc=GETPOST('search_desc','alpha');
$year=GETPOST("year");
$month=GETPOST("month");
$statut = $db->escape(GETPOST('statut'));

/*
 *	View
 */

llxHeader();

$formother = new FormOther($db);

$sql = "SELECT";
$sql.= " f.ref, f.rowid as fichid, f.fk_statut, f.fk_projet, f.fk_contrat, f.description,";
$sql.= " f.dateo, f.datee, f.datei, duree, f.total_ht, ";
$sql.= " s.nom, s.rowid as socid";
$sql.= " FROM (".MAIN_DB_PREFIX."societe as s";
if (!$user->rights->societe->client->voir && !$socid) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
$sql.= ", ".MAIN_DB_PREFIX."fichinter as f)";
//$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fichinterdet as fd ON fd.fk_fichinter = f.rowid";
$sql.= " WHERE f.fk_soc = s.rowid ";
$sql.= " AND f.entity = ".$conf->entity;
if ($month > 0)
{
    if ($year > 0 && empty($day))
    $sql.= " AND f.dateo BETWEEN '".$db->idate(dol_get_first_day($year,$month,false))."' AND '".$db->idate(dol_get_last_day($year,$month,false))."'";
    else if ($year > 0 && ! empty($day))
    $sql.= " AND f.dateo BETWEEN '".$db->idate(dol_mktime(0, 0, 0, $month, $day, $year))."' AND '".$db->idate(dol_mktime(23, 59, 59, $month, $day, $year))."'";
    else
    $sql.= " AND date_format(f.dateo, '%m') = '".$month."'";
}
else if ($year > 0)
{
	$sql.= " AND f.dateo BETWEEN '".$db->idate(dol_get_first_day($year,1,false))."' AND '".$db->idate(dol_get_last_day($year,12,false))."'";
}
if ($statut != '' && $statut != -1)
{
	$sql.= ' AND f.fk_statut IN ('.$statut.')';
}
if ($search_ref)     $sql .= " AND f.ref like '%".$db->escape($search_ref)."%'";
if ($search_company) $sql .= " AND s.nom like '%".$db->escape($search_company)."%'";
if ($search_desc)    $sql .= " AND (f.description like '%".$db->escape($search_desc)."%'"; // OR fd.description like '%".$db->escape($search_desc)."%')";
if (!$user->rights->societe->client->voir && !$socid) $sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
if ($socid)	$sql.= " AND s.rowid = " . $socid;
$sql.= " ORDER BY ".$sortfield." ".$sortorder;
$sql.= $db->plimit($limit+1, $offset);

$result=$db->query($sql);
if ($result)
{
	$num = $db->num_rows($result);

	$interventionstatic = new ManagementFichinter($db);
	$companystatic = new Societe($db);
	$projectstatic = new Project($db);
	$contratstatic = new Contrat($db);

	$nbcol=9;

	$urlparam="&amp;socid=$socid";
	print_barre_liste($langs->trans("ListOfGroupedInterventions"), $page, "listTotal.php",$urlparam,$sortfield,$sortorder,'',$num, '','title_commercial.png');

	print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
	print '<table class="noborder" width="100%">';

	print "<tr class=\"liste_titre\">";
	print_liste_field_titre($langs->trans("Ref"),$_SERVER["PHP_SELF"],"f.ref","",$urlparam,'width="15%"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Company"),$_SERVER["PHP_SELF"],"s.nom","",$urlparam,'',$sortfield,$sortorder);
	if (! empty($conf->projet->enabled))
	{
		print_liste_field_titre($langs->trans("Project"),$_SERVER["PHP_SELF"],"f.fk_projet","",$urlparam,'',$sortfield,$sortorder);
		$nbcol--;
	}
	if (! empty($conf->contrat->enabled))
	{
		print_liste_field_titre($langs->trans("Contract"),$_SERVER["PHP_SELF"],"f.fk_contrat","",$urlparam,'',$sortfield,$sortorder);
		$nbcol--;
	}
	print_liste_field_titre($langs->trans("Description"),$_SERVER["PHP_SELF"],"f.description","",$urlparam,'',$sortfield,$sortorder);
	
	print_liste_field_titre($langs->trans("Dateo"),$_SERVER["PHP_SELF"],"f.dateo","",$urlparam,'align="center"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Datee"),$_SERVER["PHP_SELF"],"f.datee","",$urlparam,'align="center"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Datei"),$_SERVER["PHP_SELF"],"f.datei","",$urlparam,'align="center"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Duration"),$_SERVER["PHP_SELF"],"","",$urlparam,' align="center"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("PerformedPriceHT"),$_SERVER["PHP_SELF"],"","",$urlparam,'colspan=3 align="center"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Status"),$_SERVER["PHP_SELF"],"f.fk_statut","",$urlparam,'align="right"',$sortfield,$sortorder);
	print "</tr>\n";

	print '<tr class="liste_titre">';
	print '<td class="liste_titre">';
	print '<input type="text" class="flat" name="search_ref" value="'.$search_ref.'" size="8">';
	print '</td><td class="liste_titre">';
	print '<input type="text" class="flat" name="search_company" value="'.$search_company.'" size="10"></td>';
	if (! empty($conf->projet->enabled))	print '<td class="liste_titre"></td>';
	if (! empty($conf->contrat->enabled))	print '<td class="liste_titre"></td>';
	print '<td class="liste_titre">';
	print '<input type="text" class="flat" name="search_desc" value="'.$search_desc.'" size="12">';
	print '</td>';
	print '<td class="liste_titre" align=center>';
	print '<input class="flat" type="text" size="1" maxlength="2" name="month" value="'.$month.'">';
	$syear = $year;
	$formother->select_year($syear,'year',1, 20, 5);
	print '</td>';	// filtre début
	print '<td class="liste_titre" align=center></td>';	// filtre fin
	print '<td class="liste_titre" align=center></td>';	// filtre fin
	print '<td class="liste_titre" align=center>'.$langs->trans("Planned").'</td>';
	print '<td class="liste_titre" align=center>'.$langs->trans("Service").'</td>';
	print '<td class="liste_titre" align=center>'.$langs->trans("Product").'</td>';
	print '<td class="liste_titre" align=center>'.$langs->trans("Total").'</td>';
	//print '<td  class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre" align="right">';
	$interventionstatic->selectStatut(array(0,1,2,3,4), $statut, 0, 'statut');
	print '<input class="liste_titre" type="image" src="'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/search.png" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
	print "</td></tr>\n";

	$var=True;
	$total = 0;
	$i = 0;
	while ($i < min($num, $limit))
	{
		$objp = $db->fetch_object($result);
		$var=!$var;
		print "<tr $bc[$var]>";
		print "<td>";
		$interventionstatic->id=$objp->fichid;
		$interventionstatic->ref=$objp->ref;
		print $interventionstatic->getNomUrl(1);
		//$dureemade=$interventionstatic->get_duree_inter_made();
		$productpriceused=$interventionstatic->get_price_inter_used(0);
		$servicepriceused=$interventionstatic->get_price_inter_used(1);
		print "</td>\n";
		print '<td>';
		$companystatic->nom=$objp->nom;
		$companystatic->id=$objp->socid;
		$companystatic->client=$objp->client;
		print $companystatic->getNomUrl(1,'',44);
		print '</td>';

		if (! empty($conf->projet->enabled))
		{
			print '<td>';
			if ($objp->fk_projet > 0)
			{
				$projectstatic->id=$objp->fk_projet;
				$projectstatic->fetch($objp->fk_projet);
				print $projectstatic->getNomUrl(1,'');
			}
			print '</td>';
		}
		if (! empty($conf->contrat->enabled))
		{
			print '<td>';
			if ($objp->fk_contrat > 0)
			{
				$contratstatic->id=$objp->fk_contrat;
				$contratstatic->fetch($objp->fk_contrat);
				print $contratstatic->getNomUrl(1,'',44);
			}
			print '</td>';
		}

		print '<td>'.dol_htmlentitiesbr(dol_trunc($objp->description,20)).'</td>';
		$dayhour=($objp->fulldayevent)?"day":"dayhour";
		print '<td align="left">'.dol_print_date($db->jdate($objp->dateo), $dayhour)."</td>\n";
		print '<td align="left">'.dol_print_date($db->jdate($objp->datee), $dayhour)."</td>\n";
		print '<td align="left">'.dol_print_date($db->jdate($objp->datei), "dayhour")."</td>\n";
		print '<td align="right">'.convertSecondToTime($objp->duree).'</td>';

		//print '<td align="right">'.$dureemade.'</td>';
		print '<td align="right">'.price($productpriceused).'</td>';
		print '<td align="right">'.price($servicepriceused).'</td>';
		print '<td align="right">'.price($objp->total_ht).'</td>';
		print '<td align="right">'.$interventionstatic->LibStatut($objp->fk_statut,1).' '.$interventionstatic->LibStatut($objp->fk_statut,3).'</td>';

		print "</tr>\n";

		$totalMnt += $objp->total_ht;
		$i++;
	}
	print '<tr class="liste_total"><td colspan="'.$nbcol.'" class="liste_total">'.$langs->trans("Total").'</td>';
	//print '<td align="right" nowrap="nowrap" class="liste_total">'.$total.'</td>';
	print '<td align="right" nowrap="nowrap" class="liste_total">'.price($totalMnt).'</td><td colspan=2>&nbsp;</td>';
	print '</tr>';

	print '</table>';
	print "</form>\n";
	$db->free($result);
}
else
	dol_print_error($db);


llxFooter();
$db->close();
?>