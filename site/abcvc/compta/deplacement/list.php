<?php
/* Copyright (C) 2003		Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2012	Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2004		Eric Seigne          <eric.seigne@ryxeo.com>
 * Copyright (C) 2005-2011	Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2012		Juanjo Menent        <jmenent@2byte.es>
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
 *  \file       htdocs/compta/deplacement/list.php
 *  \brief      Page to list trips and expenses
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/compta/tva/class/tva.class.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/compta/deplacement/class/deplacement.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

$langs->load("companies");
$langs->load("users");
$langs->load("trips");

// Security check
$socid = GETPOST('socid','int');
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'deplacement','','');

$search_ref=GETPOST('search_ref','int');
$search_name=GETPOST('search_name','alpha');
$search_company=GETPOST('search_company','alpha');
// $search_amount=GETPOST('search_amount','alpha');
$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
$page = GETPOST("page",'int');
$limit = GETPOST('limit')?GETPOST('limit','int'):$conf->liste_limit;
if ($page == -1) { $page = 0; }
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortorder) $sortorder="DESC";
if (! $sortfield) $sortfield="d.dated";

$year=GETPOST("year");
$month=GETPOST("month");

if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter")) // Both test are required to be compatible with all browsers
{
	$search_ref="";
	$search_name="";
	$search_company="";
	// $search_amount="";
	$year="";
	$month="";
}

/*
 * View
 */

$formother = new FormOther($db);
$tripandexpense_static=new Deplacement($db);
$userstatic = new User($db);

$childids = $user->getAllChildIds();
$childids[]=$user->id;

llxHeader();

$sql = "SELECT s.nom, d.fk_user, s.rowid as socid,";				// Ou
$sql.= " d.rowid, d.type, d.dated as dd, d.km,";		// Comment
$sql.= " d.fk_statut,";
$sql.= " u.lastname, u.firstname";							// Qui
$sql.= " FROM ".MAIN_DB_PREFIX."user as u";
$sql.= ", ".MAIN_DB_PREFIX."deplacement as d";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON d.fk_soc = s.rowid";
if (!$user->rights->societe->client->voir && !$socid) $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON s.rowid = sc.fk_soc";
$sql.= " WHERE d.fk_user = u.rowid";
$sql.= " AND d.entity = ".$conf->entity;
if (empty($user->rights->deplacement->readall) && empty($user->rights->deplacement->lire_tous)) $sql.=' AND d.fk_user IN ('.join(',',$childids).')';
if (!$user->rights->societe->client->voir && !$socid) $sql.= " AND (sc.fk_user = " .$user->id." OR d.fk_soc IS NULL) ";
if ($socid) $sql.= " AND s.rowid = ".$socid;

if ($search_ref)		$sql.=" AND d.rowid=".$search_ref;
if ($search_name)
{
    $sql .= natural_search('u.lastname', $search_name);
}
if ($search_company)
{
    $sql .= natural_search('s.nom', $search_company);
}
// if ($search_amount)		$sql.=" AND d.km='".$db->escape(price2num(trim($search_amount)))."'";
if ($month > 0)
{
    if ($year > 0 && empty($day))
    $sql.= " AND d.dated BETWEEN '".$db->idate(dol_get_first_day($year,$month,false))."' AND '".$db->idate(dol_get_last_day($year,$month,false))."'";
    else if ($year > 0 && ! empty($day))
    $sql.= " AND d.dated BETWEEN '".$db->idate(dol_mktime(0, 0, 0, $month, $day, $year))."' AND '".$db->idate(dol_mktime(23, 59, 59, $month, $day, $year))."'";
    else
    $sql.= " AND date_format(d.dated, '%m') = '".$month."'";
}
else if ($year > 0)
{
	$sql.= " AND d.dated BETWEEN '".$db->idate(dol_get_first_day($year,1,false))."' AND '".$db->idate(dol_get_last_day($year,12,false))."'";
}

$sql.= $db->order($sortfield,$sortorder);
$sql.= $db->plimit($limit + 1, $offset);

//print $sql;
$resql=$db->query($sql);
if ($resql)
{
    $num = $db->num_rows($resql);

    print_barre_liste($langs->trans("ListOfFees"), $page, $_SERVER["PHP_SELF"],"&socid=$socid",$sortfield,$sortorder,'',$num);

    $i = 0;
    print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
    print '<table class="noborder" width="100%">';
    print "<tr class=\"liste_titre\">";
    print_liste_field_titre($langs->trans("Ref"),$_SERVER["PHP_SELF"],"d.rowid","","&socid=$socid",'',$sortfield,$sortorder);
    print_liste_field_titre($langs->trans("Type"),$_SERVER["PHP_SELF"],"d.type","","&socid=$socid",'',$sortfield,$sortorder);
    print_liste_field_titre($langs->trans("Date"),$_SERVER["PHP_SELF"],"d.dated","","&socid=$socid",'align="center"',$sortfield,$sortorder);
    print_liste_field_titre($langs->trans("Person"),$_SERVER["PHP_SELF"],"u.lastname","","&socid=$socid",'',$sortfield,$sortorder);
    print_liste_field_titre($langs->trans("Company"),$_SERVER["PHP_SELF"],"s.nom","","&socid=$socid",'',$sortfield,$sortorder);
    print_liste_field_titre($langs->trans("FeesKilometersOrAmout"),$_SERVER["PHP_SELF"],"d.km","","&socid=$socid",'align="right"',$sortfield,$sortorder);
    print_liste_field_titre('');
    print "</tr>\n";

    // Filters lines
    print '<tr class="liste_titre">';
    print '<td class="liste_titre">';
    print '<input class="flat" size="4" type="text" name="search_ref" value="'.$search_ref.'">';
    print '</td>';
    print '<td class="liste_titre">';
    print '&nbsp;';
    print '</td>';
    print '<td class="liste_titre" align="center">';
    if (! empty($conf->global->MAIN_LIST_FILTER_ON_DAY)) print '<input class="flat" type="text" size="1" maxlength="2" name="day" value="'.$day.'">';
    print '<input class="flat" type="text" size="1" maxlength="2" name="month" value="'.$month.'">';
    $formother->select_year($year?$year:-1,'year',1, 20, 5);
    print '</td>';
    print '<td class="liste_titre">';
    print '<input class="flat" size="10" type="text" name="search_name" value="'.$search_name.'">';
    print '</td>';
    print '<td class="liste_titre">';
    print '<input class="flat" size="10" type="text" name="search_company" value="'.$search_company.'">';
    print '</td>';
    print '<td class="liste_titre" align="right">';
    // print '<input class="flat" size="10" type="text" name="search_amount" value="'.$search_amount.'">';
    print '</td>';
    print '<td class="liste_titre" align="right">';
    $searchpitco=$form->showFilterAndCheckAddButtons(0);
    print $searchpitco;
    print '</td>';
    print "</tr>\n";

    $var=true;
    while ($i < min($num,$limit))
    {
        $obj = $db->fetch_object($resql);

        $soc = new Societe($db);
        if ($obj->socid) $soc->fetch($obj->socid);

        $var=!$var;
        print '<tr '.$bc[$var].'>';
        // Id
        print '<td><a href="card.php?id='.$obj->rowid.'">'.img_object($langs->trans("ShowTrip"),"trip").' '.$obj->rowid.'</a></td>';
        // Type
        print '<td>'.$langs->trans($obj->type).'</td>';
        // Date
        print '<td align="center">'.dol_print_date($db->jdate($obj->dd),'day').'</td>';
        // User
        print '<td>';
        $userstatic->id = $obj->fk_user;
        $userstatic->lastname = $obj->lastname;
        $userstatic->firstname = $obj->firstname;
        print $userstatic->getNomUrl(1);
        print '</td>';

        if ($obj->socid) print '<td>'.$soc->getNomUrl(1).'</td>';
        else print '<td>&nbsp;</td>';

        print '<td align="right">'.$obj->km.'</td>';

        $tripandexpense_static->statut=$obj->fk_statut;
        print '<td align="right">'.$tripandexpense_static->getLibStatut(5).'</td>';
        print "</tr>\n";

        $i++;
    }

    print "</table>";
    print "</form>\n";
    $db->free($resql);
}
else
{
    dol_print_error($db);
}

llxFooter();

$db->close();
