<?php
/* Copyright (C) 2001-2003 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (c) 2004-2008 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin		<regis@dolibarr.fr>
 * Copyright (C) 2012-2016	Charlie BENKE		<charlie@patas-monkey.com>
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
 *		\file	   htdocs/commande/stats/month.php
 *	  \ingroup	commande
 *		\brief	  Page des stats commandes par mois
 *		\version	$Id: month.php,v 1.34 2011/08/03 00:46:39 eldy Exp $
 */
$res =0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");		// For root directory
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php");	// For "custom" directory

require_once(DOL_DOCUMENT_ROOT."/projet/class/project.class.php");
dol_include_once("/management/projet/class/projectstats.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/dolgraph.class.php");

$GRAPHWIDTH=500;
$GRAPHHEIGHT=200;

// Check security access
if ($user->societe_id > 0)
{
  $action = '';
  $socid = $user->societe_id;
}
if (!$user->rights->projet->lire) accessforbidden();

$year = isset($_GET["year"])?$_GET["year"]:date("Y",time());

$mode='customer';
if (isset($_GET["mode"])) $mode=$_GET["mode"];



/*
 * View
 */

llxHeader();

if ($mode == 'customer')
{
	$title=$langs->trans("ProjetStatistics");
	$dir=$conf->commande->dir_temp;
}
if ($mode == 'supplier')
{
	$title=$langs->trans("OrdersStatisticsSuppliers");
	$dir=$conf->project->dir_output.'/project/temp';
}

$mesg = '<a href="month.php?year='.($year - 1).'&amp;mode='.$mode.'">'.img_previous().'</a> ';
$mesg.= $langs->trans("Year")." $year";
$mesg.= ' <a href="month.php?year='.($year + 1).'&amp;mode='.$mode.'">'.img_next().'</a>';
print_fiche_titre($title, $mesg);

dol_mkdir($dir);

$stats = new ProjectStats($db, $socid, $mode);


$data = $stats->getNbByMonth($year);

if (!$user->rights->societe->client->voir || $user->societe_id)
{
	$filename = $dir.'/projetnb-'.$user->id.'-'.$year.'.png';
	if ($mode == 'customer') $fileurlnb = DOL_URL_ROOT.'/viewimage.php?modulepart=graph_projet&file=projetnb-'.$user->id.'-'.$year.'.png';	
}
else
{
	$filename = $dir.'/projetnb-'.$year.'.png';
	if ($mode == 'customer') $fileurlnb = DOL_URL_ROOT.'/viewimage.php?modulepart=graph_projet&file=projetnb-'.$year.'.png';	
}

$px1 = new DolGraph();
$mesg = $px1->isGraphKo();
if (! $mesg)
{
	$px1->SetData($data);
	$px1->SetMaxValue($px1->GetCeilMaxValue());
	$px1->SetMinValue($px1->GetFloorMinValue());
	$px1->SetWidth($GRAPHWIDTH);
	$px1->SetHeight($GRAPHHEIGHT);
	$px1->SetYLabel($langs->trans("NbOfProjects"));
	$px1->SetShading(3);
	$px1->SetHorizTickIncrement(1);
	$px1->SetPrecisionY(0);
	$px1->draw($filenamenb, $fileurlnb);
}


$data = $stats->getAmountByMonth($year);

if (!$user->rights->societe->client->voir || $user->societe_id)
{
	$filename_amount = $dir.'/projetamount-'.$user->id.'-'.$year.'.png';
	if ($mode == 'customer') $fileurl_amount = DOL_URL_ROOT.'/viewimage.php?modulepart=graph_projet&file=projetamount-'.$user->id.'-'.$year.'.png';
}
else
{
	$filename_amount = $dir.'/projetamount-'.$year.'.png';
	if ($mode == 'customer') $fileurl_amount = DOL_URL_ROOT.'/viewimage.php?modulepart=graph_projet&file=projetamount-'.$year.'.png';
}

$px2 = new DolGraph();
$mesg = $px2->isGraphKo();
if (! $mesg)
{
	$px2->SetData($data);
	$px2->SetYLabel($langs->trans("AmountTotal"));
	$px2->SetMaxValue($px2->GetCeilMaxValue());
	$px2->SetMinValue($px2->GetFloorMinValue());
	$px2->SetWidth($GRAPHWIDTH);
	$px2->SetHeight($GRAPHHEIGHT);
	$px2->SetShading(3);
	$px2->SetHorizTickIncrement(1);
	$px2->SetPrecisionY(0);
	$px2->draw($filename_amount, $fileurl_amount);
}


$data = $stats->getAverageByMonth($year);


for ($i = 1 ; $i < 13 ; $i++)
{
  $data[$i-1] = array(ucfirst(substr(dol_print_date(dol_mktime(12,0,0,$i,1,$year),"%b"),0,3)), $res[$i]);
}

if (!$user->rights->societe->client->voir || $user->societe_id)
{
	$filename_avg = $dir.'/projetaverage-'.$user->id.'-'.$year.'.png';
	if ($mode == 'customer') $fileurl_avg = DOL_URL_ROOT.'/viewimage.php?modulepart=orderstats&file=projetaverage-'.$user->id.'-'.$year.'.png';
	
}
else
{
	$filename_avg = $dir.'/projetaverage-'.$year.'.png';
	if ($mode == 'customer') $fileurl_avg = DOL_URL_ROOT.'/viewimage.php?modulepart=orderstats&file=projetaverage-'.$year.'.png';
	
}

$px3 = new DolGraph();
$mesg = $px3->isGraphKo();
if (! $mesg)
{
	$px3->SetData($data);
	$px3->SetYLabel($langs->trans("AmountAverage"));
	$px3->SetMaxValue($px3->GetCeilMaxValue());
	$px3->SetMinValue($px3->GetFloorMinValue());
	$px3->SetWidth($GRAPHWIDTH);
	$px3->SetHeight($GRAPHHEIGHT);
	$px3->SetShading(3);
	$px3->SetHorizTickIncrement(1);
	$px3->SetPrecisionY(0);
	$px3->draw($filename_avg, $fileurl_avg);
}

print '<table class="border" width="100%">';
print '<tr><td align="center">'.$langs->trans("NbOfProjectByMonth").'</td>';
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
print '</td></tr></table>';

llxFooter('$Date: 2011/08/03 00:46:39 $ - $Revision: 1.34 $');
$db->close();
?>