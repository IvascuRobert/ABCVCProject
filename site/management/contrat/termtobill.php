<?php
/* Copyright (C) 2001-2004  Rodolphe Quiedeville 	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2010  Laurent Destailleur  	<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012  Regis Houssin        	<regis.houssin@capnetworks.com>
 * Copyright (C) 2013       Cédric Salvador      	<csalvador@gpcsolutions.fr>
 * Copyright (C) 2014-2016	Charlie BENKE			<charlie@patas-monkey.com>
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
 *       \file       htdocs/managenent/contrat/termtobill.php
 *       \ingroup    contrat
 *       \brief      Page liste des contrats
 */

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");		// For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");	// For "custom" directory


require_once (DOL_DOCUMENT_ROOT."/contrat/class/contrat.class.php");

$langs->load("contracts");
$langs->load("products");
$langs->load("companies");
$langs->load("management@management");

$sortfield=GETPOST('sortfield','alpha');
$sortorder=GETPOST('sortorder','alpha');
$page=GETPOST('page','int');
if ($page == -1) { $page = 0 ; }
$limit = $conf->liste_limit;
$offset = $limit * $page ;

$search_nom=GETPOST('search_nom');
$search_contract=GETPOST('search_contract');
$sall=GETPOST('sall');
$statut=GETPOST('statut')?GETPOST('statut'):1;
$socid=GETPOST('socid');

if (! $sortfield) $sortfield="c.rowid";
if (! $sortorder) $sortorder="DESC";

// Security check
$id=GETPOST('id','int');
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'contrat', $id);


/*
 * View
 */

$now=dol_now();

llxHeader();

$sql = 'SELECT';
$sql.= " c.rowid as cid, c.ref, c.datec, c.date_contrat, c.statut,";
$sql.= " s.nom, s.rowid as socid, ct.datedeb, ct.datefin, ";
$sql.= ' SUM(total_ttc) as totalttc';

$sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
if (!$user->rights->societe->client->voir && !$socid) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
$sql.= ", ".MAIN_DB_PREFIX."contrat as c";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."contratdet as cd ON c.rowid = cd.fk_contrat";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."contrat_term as ct ON c.rowid = ct.fk_contrat";
$sql.= " WHERE c.fk_soc = s.rowid ";
$sql.= " AND c.entity = ".$conf->entity;
$sql.= " AND cd.statut = 4";
$sql.= " AND ct.fk_status = 1";

// on filtre sur les échéances arrivant à terme


if ($socid) $sql.= " AND s.rowid = ".$socid;
if (!$user->rights->societe->client->voir && !$socid) $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
if ($search_nom) {
    $sql .= natural_search('s.nom', $search_nom);
}
if ($search_contract) {
    $sql .= natural_search(array('c.rowid', 'c.ref'), $search_contract);
}
if ($sall) {
    $sql .= natural_search(array('s.nom', 'cd.label', 'cd.description'), $sall);
}
$sql.= " GROUP BY c.rowid, c.ref, c.datec, c.date_contrat, c.statut,";
$sql.= " s.nom, s.rowid";
$sql.= " ORDER BY $sortfield $sortorder";


$resql=$db->query($sql);
if ($resql)
{
    $num = $db->num_rows($resql);
    $i = 0;
$object = new Contrat($db);

    print_barre_liste($langs->trans("ListOfContractsInTerm"), $page, $_SERVER["PHP_SELF"], '&search_contract='.$search_contract.'&search_nom='.$search_nom, $sortfield, $sortorder,'',$num);

    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<table class="liste" width="100%">';

    print '<tr class="liste_titre">';
    $param='&amp;search_contract='.$search_contract;
    $param.='&amp;search_nom='.$search_nom;
    print_liste_field_titre($langs->trans("Ref"), $_SERVER["PHP_SELF"], "c.rowid","","$param",'',$sortfield,$sortorder);
    print_liste_field_titre($langs->trans("Company"), $_SERVER["PHP_SELF"], "s.nom","","$param",'',$sortfield,$sortorder);
    //print_liste_field_titre($langs->trans("DateCreation"), $_SERVER["PHP_SELF"], "c.datec","","$param",'align="center"',$sortfield,$sortorder);
    print_liste_field_titre($langs->trans("DateContract"), $_SERVER["PHP_SELF"], "c.date_contrat","","$param",'align="center"',$sortfield,$sortorder);
    print_liste_field_titre($langs->trans("DateBeginTerm"), $_SERVER["PHP_SELF"], "ct.datedeb","","$param",'align="center"',$sortfield,$sortorder);
    print_liste_field_titre($langs->trans("DateEndTerm"), $_SERVER["PHP_SELF"], "ct.datefin","","$param",'align="center"',$sortfield,$sortorder);
    print_liste_field_titre($langs->trans("Totalttc"), $_SERVER["PHP_SELF"], "Totalttc","","$param",'align="center"',$sortfield,$sortorder);
//    print '<td class="liste_titre">'.$langs->trans("ToBill").'</td>';
//    print '<td class="liste_titre" align=center>'.$langs->trans("RenewTerm").'</td>';
    print "</tr>\n";

    print '<tr class="liste_titre">';
    print '<td class="liste_titre">';
    print '<input type="text" class="flat" size="3" name="search_contract" value="'.$search_contract.'">';
    print '</td>';
    print '<td class="liste_titre">';
    print '<input type="text" class="flat" size="24" name="search_nom" value="'.$search_nom.'">';
    print '</td>';
    print '<td class="liste_titre">&nbsp;</td>';
    print '<td class="liste_titre">&nbsp;</td>';
    print '<td class="liste_titre">&nbsp;</td>';
    //print '<td class="liste_titre">&nbsp;</td>';
    print '<td class="liste_titre" align="right"><input class="liste_titre" type="image" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
    print "</td>";
    print "</tr>\n";
    

    $var=true;
    while ($i < min($num,$limit))
    {
        $obj = $db->fetch_object($resql);
        $object->fetch($obj->cid);
        $var=!$var;
        print '<tr '.$bc[$var].'>';
        print '<td class="nowrap">'.$object->getNomUrl(1);
        if ($obj->nb_late) print img_warning($langs->trans("Late"));
        print '</td>';
        print '<td><a href="../comm/fiche.php?socid='.$obj->socid.'">'.img_object($langs->trans("ShowCompany"),"company").' '.$obj->nom.'</a></td>';
        print '<td align="center">'.dol_print_date($db->jdate($obj->date_contrat)).'</td>';
        print '<td align="center">'.dol_print_date($db->jdate($obj->datedeb)).'</td>';
        print '<td align="center">'.dol_print_date($db->jdate($obj->datefin)).'</td>';
        print '<td align="center">'.price($obj->totalttc).'</td>';
//        print '<td align="center"><input type=checkbox></td>';
//        print '<td align="center"><input type=checkbox></td>';
        print "</tr>\n";
        $i++;
    }
    $db->free($resql);

    print "</table>";
    
//	print '<div class="tabsAction">';
//	if ($user->rights->facture->creer) 
//		print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=valid">'.$langs->trans("Validate").'</a></div>';
//	else 
//		print '<div class="inline-block divButAction"><a class="butActionRefused" href="#" title="'.$langs->trans("NotEnoughPermissions").'">'.$langs->trans("Validate").'</a></div>';
//	
//    print '</div></form>';
}
else
{
    dol_print_error($db);
}

llxFooter();
$db->close();
