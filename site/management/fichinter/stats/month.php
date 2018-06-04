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
 *	    \file       htdocs/fichinter/stats/month.php
 *      \ingroup    commande
 *		\brief      Page des stats commandes par mois
 *		\version    $Id: month.php,v 1.34 2011/08/03 00:46:39 eldy Exp $
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



// Check security access
if ($user->societe_id > 0)
{
  $action = '';
  $socid = $user->societe_id;
}

$year = isset($_GET["year"])?$_GET["year"]:date("Y",time());




/*
 * View
 */



llxHeader();

$title=$langs->trans("fichInterStatistics");
$dir=$conf->ficheinter->dir_output.'/temp';

$mesg = '<a href="month.php?year='.($year - 1).'&amp;mode='.$mode.'">'.img_previous().'</a> ';
$mesg.= $langs->trans("Year")." $year";
$mesg.= ' <a href="month.php?year='.($year + 1).'&amp;mode='.$mode.'">'.img_next().'</a>';
print_fiche_titre($title, $mesg);

dol_mkdir($dir);

$stats = new FichInterStats($db, $socid, $mode);


$data = $stats->getNbByMonth($year);


if (!$user->rights->societe->client->voir || $user->societe_id)
{
	$filename = $dir.'/fichintersnbinyear-'.$user->id.'-'.$year.'.png';
	$fileurl = DOL_URL_ROOT.'/viewimage.php?modulepart=fichinterstats&file=fichintersnbinyear-'.$user->id.'-'.$year.'.png';
}
else
{
	$filename = $dir.'/fichintersnbinyear-'.$year.'.png';
$fileurl = DOL_URL_ROOT.'/viewimage.php?modulepart=fichinterstats&file=fichintersnbinyear-'.$year.'.png';
}

$px1 = new DolGraph();
$mesg = $px1->isGraphKo();
if (! $mesg)
{
	$px1->SetData($data);
	$px1->SetMaxValue($px1->GetCeilMaxValue());
	$px1->SetMinValue($px1->GetFloorMinValue());
	$px1->SetWidth($WIDTH);
	$px1->SetHeight($HEIGHT);
	$px1->SetYLabel($langs->trans("NbOfOrders"));
	$px1->SetShading(3);
	$px1->SetHorizTickIncrement(1);
	$px1->SetPrecisionY(0);
	$px1->draw($filename);
}


$data = $stats->getAmountByMonth($year);

if (!$user->rights->societe->client->voir || $user->societe_id)
{
	$filename_amount = $dir.'/fichintersMntinyear-'.$user->id.'-'.$year.'.png';
	$fileurl_amount = DOL_URL_ROOT.'/viewimage.php?modulepart=fichinterstats&file=fichintersMntinyear-'.$user->id.'-'.$year.'.png';
}
else
{
	$filename_amount = $dir.'/fichintersMntinyear-'.$year.'.png';
	$fileurl_amount = DOL_URL_ROOT.'/viewimage.php?modulepart=fichinterstats&file=fichintersMntinyear-'.$year.'.png';
}

$px2 = new DolGraph();
$mesg = $px2->isGraphKo();
if (! $mesg)
{
	$px2->SetData($data);
	$px2->SetYLabel($langs->trans("AmountTotal"));
	$px2->SetMaxValue($px2->GetCeilMaxValue());
	$px2->SetMinValue($px2->GetFloorMinValue());
	$px2->SetWidth($WIDTH);
	$px2->SetHeight($HEIGHT);
	$px2->SetShading(3);
	$px2->SetHorizTickIncrement(1);
	$px2->SetPrecisionY(0);
	$px2->draw($filename_amount);
}
$res = $stats->getAverageByMonth($year);

$data = array();

for ($i = 1 ; $i < 13 ; $i++)
{
  $data[$i-1] = array(ucfirst(substr(dol_print_date(dol_mktime(12,0,0,$i,1,$year),"%b"),0,3)), $res[$i]);
}

if (!$user->rights->societe->client->voir || $user->societe_id)
{
	$filename_avg = $dir.'/fichintersaverage-'.$user->id.'-'.$year.'.png';
	$fileurl_avg = DOL_URL_ROOT.'/viewimage.php?modulepart=apercufichinter&file=fichintersaverage-'.$user->id.'-'.$year.'.png';
}
else
{
	$filename_avg = $dir.'/fichintersaverage-'.$year.'.png';
	$fileurl_avg = DOL_URL_ROOT.'/viewimage.php?modulepart=apercufichinter&file=fichintersaverage-'.$year.'.png';
}

$px3 = new DolGraph();
$mesg = $px3->isGraphKo();
if (! $mesg)
{
    $px3->SetData($data);
    $px3->SetYLabel($langs->trans("AmountAverage"));
    $px3->SetMaxValue($px3->GetCeilMaxValue());
    $px3->SetMinValue($px3->GetFloorMinValue());
    $px3->SetWidth($WIDTH);
    $px3->SetHeight($HEIGHT);
    $px3->SetShading(3);
	$px3->SetHorizTickIncrement(1);
	$px3->SetPrecisionY(0);
    $px3->draw($filename_avg);
}

print '<table class="border" width="100%">';
print '<tr><td align="center">'.$langs->trans("NumberOfOrdersByMonth").'</td>';
print '<td align="center">';
if ($mesg) { print $mesg; }
else {
	print $px1->show();
	print "<br>\n";
	print $px2->show();
	print "<br>\n";
	print $px3->show();
	print "<br>\n";

}

llxFooter('$Date: 2011/08/03 00:46:39 $ - $Revision: 1.34 $');
dol_include_once("/management/fichinter/class/fichinterstats.class.php");
?>