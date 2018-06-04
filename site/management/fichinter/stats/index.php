<?php
/* Copyright (C) 2012-2016	Charlie BENKE	<charlie@patas-monkey.com>
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
 *	    \file       htdocs/management/fichinter/stats/index.php
 *      \ingroup    commande
 *		\brief      Page with interventions statistics
 *		\version    $Id: index.php,v 1.39 2011/08/03 00:46:39 eldy Exp $
 */
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");		// For root directory
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php");	// For "custom" directory


require_once(DOL_DOCUMENT_ROOT."/fichinter/class/fichinter.class.php");
dol_include_once("/management/fichinter/class/fichinterstats.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/dolgraph.class.php");

$langs->load("companies");
$langs->load("interventions");

$WIDTH=500;
$HEIGHT=200;

$userid=GETPOST('userid'); if ($userid < 0) $userid=0;
$socid=GETPOST('socid'); if ($socid < 0) $socid=0;
// Security check
if ($user->societe_id > 0)
{
    $action = '';
    $socid = $user->societe_id;
}

$nowyear=strftime("%Y", dol_now());
$year = GETPOST('year')>0?GETPOST('year'):$nowyear;
//$startyear=$year-2;
$startyear=$year-1;
$endyear=$year;


/*
 * View
 */

$form=new Form($db);

llxHeader();

$title=$langs->trans("fichInterStatistics");
$dir=$conf->ficheinter->dir_output.'/temp';




print_fiche_titre($title, $mesg);

dol_mkdir($dir);

$stats = new FichInterStats($db, $socid, $mode, $userid);

// Build graphic number of object
$data = $stats->getNbByMonthWithPrevYear($endyear,$startyear);
//var_dump($data);

if (!$user->rights->societe->client->voir || $user->societe_id)
{
	$filenamenb = $dir.'/fichintersnbinyear-'.$user->id.'-'.$year.'.png';
	$fileurlnb = DOL_URL_ROOT.'/viewimage.php?modulepart=apercufichinter&file=fichintersnbinyear-'.$user->id.'-'.$year.'.png';    
}
else
{
$filenamenb = $dir.'/fichintersnbinyear-'.$year.'.png';
$fileurlnb = DOL_URL_ROOT.'/viewimage.php?modulepart=apercufichinter&file=fichintersnbinyear-'.$year.'.png';
}

$px1 = new DolGraph();
$mesg = $px1->isGraphKo();
if (! $mesg)
{
	
    $px1->SetData($data);
    $px1->SetPrecisionY(0);
    $i=$startyear;
    $legend=array();
    while ($i <= $endyear)
    {
        $legend[]=$i;
        $i++;
    }
    $px1->SetLegend($legend);
    $px1->SetMaxValue($px1->GetCeilMaxValue());
    $px1->SetMinValue(min(0,$px1->GetFloorMinValue()));
    $px1->SetWidth($WIDTH);
    $px1->SetHeight($HEIGHT);
    $px1->SetYLabel($langs->trans("NbOfFichInter"));
    $px1->SetShading(3);
    $px1->SetHorizTickIncrement(1);
    $px1->SetPrecisionY(0);
    $px1->mode='depth';
    $px1->SetTitle($langs->trans("NbOfFichIntersByMonth"));
    $px1->draw($filenamenb, $fileurlnb);
}


// Build graphic amount of object
$data = $stats->getAmountByMonthWithPrevYear($endyear,$startyear);
//var_dump($data);
// $data = array(array('Lib',val1,val2,val3),...)

if (!$user->rights->societe->client->voir || $user->societe_id)
{
    $filenameamount = $dir.'/fichintersamountinyear-'.$user->id.'-'.$year.'.png';
    if ($mode == 'customer') $fileurlamount = DOL_URL_ROOT.'/viewimage.php?modulepart=apercufichinter&file=fichintersamountinyear-'.$user->id.'-'.$year.'.png';
}
else
{
    $filenameamount = $dir.'/ordersamountinyear-'.$year.'.png';
    if ($mode == 'customer') $fileurlamount = DOL_URL_ROOT.'/viewimage.php?modulepart=apercufichinter&file=fichintersamountinyear-'.$year.'.png';
}

$px2 = new DolGraph();
$mesg = $px2->isGraphKo();
if (! $mesg)
{
    $px2->SetData($data);
    $i=$startyear;
    while ($i <= $endyear)
    {
        $legend[]=$i;
        $i++;
    }
    $px2->SetLegend($legend);
    $px2->SetMaxValue($px2->GetCeilMaxValue());
    $px2->SetMinValue(min(0,$px2->GetFloorMinValue()));
    $px2->SetWidth($WIDTH);
    $px2->SetHeight($HEIGHT);
    $px2->SetYLabel($langs->trans("AmountOfOrders"));
    $px2->SetShading(3);
    $px2->SetHorizTickIncrement(1);
    $px2->SetPrecisionY(0);
    $px2->mode='depth';
    $px2->SetTitle($langs->trans("AmountOfFichIntersByMonthHT"));

    $px2->draw($filenameamount);
}

print '<table class="notopnoleftnopadd" width="100%"><tr>';
print '<td align="center" valign="top">';

// Show filter box
print '<form name="stats" method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<table class="border" width="100%">';
print '<tr><td class="liste_titre" colspan="2">'.$langs->trans("Filter").'</td></tr>';
print '<tr><td>'.$langs->trans("ThirdParty").'</td><td>';
if ($mode == 'customer') $filter='s.client in (1,2,3)';
print $form->select_company($socid,'socid',$filter,1);
print '</td></tr>';
print '<tr><td>'.$langs->trans("User").'</td><td>';
print $form->select_users($userid,'userid',1);
print '</td></tr>';
print '<tr><td align="center" colspan="2"><input type="submit" name="submit" class="button" value="'.$langs->trans("Refresh").'"></td></tr>';
print '</table>';
print '</form>';
print '<br><br>';

// Show array
$data = $stats->getAllByYear();

print '<table class="border" width="100%">';
print '<tr height="24">';
print '<td align="center">'.$langs->trans("Year").'</td>';
print '<td align="center">'.$langs->trans("NbOfFichInters").'</td>';
print '<td align="center">'.$langs->trans("AmountTotal").'</td>';
print '<td align="center">'.$langs->trans("AmountAverage").'</td>';
print '</tr>';

$oldyear=0;
foreach ($data as $val)
{
    $year = $val['year'];
    while ($year && $oldyear > $year+1)
    {	// If we have empty year
        $oldyear--;
        print '<tr height="24">';
        print '<td align="center"><a href="month.php?year='.$oldyear.'&amp;mode='.$mode.'">'.$oldyear.'</a></td>';
        print '<td align="right">0</td>';
        print '<td align="right">0</td>';
        print '<td align="right">0</td>';
        print '</tr>';
    }
    print '<tr height="24">';
    print '<td align="center"><a href="month.php?year='.$year.'&amp;mode='.$mode.'">'.$year.'</a></td>';
    print '<td align="right">'.$val['nb'].'</td>';
    print '<td align="right">'.price(price2num($val['total'],'MT'),1).'</td>';
    print '<td align="right">'.price(price2num($val['avg'],'MT'),1).'</td>';
    print '</tr>';
    $oldyear=$year;
}

print '</table>';


print '</td>';
print '<td align="center" valign="top">';

// Show graphs
print '<table class="border" width="100%"><tr valign="top"><td align="center">';
if ($mesg) { print $mesg; }
else {
	print $px1->show();
	print "<br>\n";
	print $px2->show();
	print "<br>\n";
}
print '</td></tr></table>';

print '</td></tr></table>';

llxFooter('$Date: 2011/08/03 00:46:39 $ - $Revision: 1.39 $');
$db->close();
?>
