<?php
/* Copyright (C) 2002-2003 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2013      Florian Henry	    <florian.henry@open-concept.pro>
 * Copyright (C) 2013      Juanjo Menent	    <jmenent@2byte.es>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
 * Copyright (C) 2012      Cedric Salvador      <csalvador@gpcsolutions.fr>
 * Copyright (C) 2015      Alexandre Spangaro   <aspangaro.dolibarr@gmail.com>
 * Copyright (C) 2016      Charlie Benke		<charlie@patas-monkey.com>
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
 *	\file       management/fichinter/fiche-rec.php
 *	\ingroup    facture
 *	\brief      Page to show predefined invoice
 */

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");		// For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");	// For "custom" directory

dol_include_once ('/management/class/fichinter-rec.class.php');
dol_include_once ('/management/class/managementfichinter.class.php');

require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

$langs->load('bills');
$langs->load('compta');
$langs->load('management@management');

// Security check
$id=(GETPOST('fichinterid','int')?GETPOST('fichinterid','int'):GETPOST('id','int'));
$action=GETPOST('action', 'alpha');
if ($user->societe_id) $socid=$user->societe_id;
$objecttype = 'fichinter_rec';
if ($action == "create" || $action == "add") $objecttype = '';
$result = restrictedArea($user, 'ficheinter', $id, $objecttype);

if ($page == -1)
{
	$page = 0 ;
}
$limit = GETPOST('limit')?GETPOST('limit','int'):$conf->liste_limit;
$offset = $limit * $page ;

if ($sortorder == "")
$sortorder="DESC";

if ($sortfield == "")
$sortfield="f.datec";

$object = new FichinterRec($db);



/*
 * Actions
 */


// Create predefined intervention
if ($action == 'add')
{
	if (! GETPOST('titre'))
	{
		setEventMessages($langs->transnoentities("ErrorFieldRequired",$langs->trans("Title")), null, 'errors');
		$action = "create";
		$error++;
	}

	if (! $error)
	{
		$object->titre			= GETPOST('titre', 'alpha');
		$object->description	= GETPOST('description', 'alpha');
		$object->socid			= GETPOST('socid', 'alpha');
		// gestion des fréquences et des échéances

		if ($object->create($user, $id) > 0)
		{
			$id = $object->id;
			$action = '';
		}
		else
		{
			setEventMessages($object->error, $object->errors, 'errors');
			$action = "create";
		}
	}
}
elseif($action == 'createfrommodel')
{
	$newinter = new Managementfichinter($db);
	
	// on récupère les enregistrements
	$object->fetch($id);
	

	// on transfert les données de l'un vers l'autre
	if ($object->socid > 0)
	{
		$newinter->socid=$object->socid;
		$newinter->fk_projet=$object->fk_projet;
		$newinter->fk_contrat=$object->fk_contrat;
	}
	else
		$newinter->socid=GETPOST("socid");
	
	$newinter->entity=$object->entity;
	$newinter->duree=$object->duree;
	
	$newinter->description=$object->description;
	$newinter->note_private=$object->note_private;
	$newinter->note_public=$object->note_public;
	
		
	// on créer un nouvelle intervention
	$extrafields = new ExtraFields($db);
	$extralabels = $extrafields->fetch_name_optionals_label($newinter->table_element);
	$array_options = $extrafields->getOptionalsFromPost($extralabels);
	$newinter->array_options = $array_options;

	$newfichinterid = $newinter->create($user);
	
	if ($newfichinterid > 0)
	{
		// on ajoute les lignes de détail ensuite
		foreach($object->lines as $ficheinterligne)
		{
			$newinter->addline($user, $newfichinterid, $ficheinterligne->desc, "", $ficheinterligne->duree, $array_options='');
		}
		
		//on redirige vers la fiche d'intervention nouvellement crée
		header('Location: '.DOL_URL_ROOT.'/fichinter/card.php?id='.$newfichinterid);
		exit;
	}
	else
	{
		setEventMessages($newinter->error, $newinter->errors, 'errors');
		$action='';
	}
}


// Delete
if ($action == 'delete' && $user->rights->ficheinter->supprimer)
{

	$object->fetch($id);
	$object->delete();
	$id = 0 ;
	header('Location: '.$_SERVER["PHP_SELF"]);
	exit;
}



/*
 *	View
 */

llxHeader('',$langs->trans("RepeatableInterventional"),'ch-fichinter.html#s-fac-fichinter-rec');

$form = new Form($db);
$companystatic = new Societe($db);
$contratstatic = new Contrat($db);

/*
 * Create mode
 */
if ($action == 'create')
{
	print load_fiche_titre($langs->trans("CreateRepeatableIntervention"),'','title_commercial.png');

	$object = new Fichinter($db);   // Source invoice
	//$object = new Managementfichinter($db);   // Source invoice


	if ($object->fetch($id) > 0)
	{
		print '<form action="fiche-rec.php" method="post">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="add">';
		print '<input type="hidden" name="fichinterid" value="'.$object->id.'">';

		dol_fiche_head();

		$rowspan=4;
		if (! empty($conf->projet->enabled) && $object->fk_project > 0) $rowspan++;
		if (! empty($conf->contrat->enabled) && $object->fk_contrat > 0) $rowspan++;
		
		print '<table class="border" width="100%">';

		$object->fetch_thirdparty();

		// Third party
		print '<tr><td>'.$langs->trans("Customer").'</td><td>';
		print $form->select_company($object->thirdparty->id,'socid','',1,1);

//		.$object->thirdparty->getNomUrl(1,'customer').
		print '</td><td>';
		print $langs->trans("Comment");
		print '</td></tr>';

		// Title
		print '<tr><td class="fieldrequired">'.$langs->trans("Title").'</td><td>';
		print '<input class="flat" type="text" name="titre" size="24" value="'.$_POST["titre"].'">';
		print '</td>';

		// Note
		print '<td rowspan="'.$rowspan.'" valign="top">';
		print '<textarea class="flat" name="description" wrap="soft" cols="60" rows="'.ROWS_4.'">'.$object->description.'</textarea>';
		print '</td></tr>';

		// Author
		print "<tr><td>".$langs->trans("Author")."</td><td>".$user->getFullName($langs)."</td></tr>";

		if (empty($conf->global->FICHINTER_DISABLE_DETAILS))
		{
			// Duration
			print '<tr><td>'.$langs->trans("TotalDuration").'</td>';
			print '<td colspan="3">'.convertSecondToTime($object->duration, 'all', $conf->global->MAIN_DURATION_OF_WORKDAY).'</td>';
			print '</tr>';
		}
		// Project
		if (! empty($conf->projet->enabled) && $object->fk_project > 0)
		{
			print "<tr><td>".$langs->trans("Project")."</td><td>";
			if ($object->fk_project > 0)
			{
				$project = new Project($db);
				$project->fetch($object->fk_project);
				print $project->title;
			}
			print "</td></tr>";
		}

		// Contrat
		if (! empty($conf->contrat->enabled) && $object->fk_contrat > 0)
		{
			print "<tr><td>".$langs->trans("Contract")."</td><td>";
			if ($object->fk_contrat > 0)
			{
				$contrat = new Contrat($db);
				$contrat->fetch($object->fk_contrat);
				print $contrat->getNomUrl(3);
			}
			print "</td></tr>";
		}

		/// frequency & duration


		print "</table>";

		print '<br>';

		$title = $langs->trans("ProductsAndServices");
		if (empty($conf->service->enabled))
			$title = $langs->trans("Products");
		else if (empty($conf->product->enabled))
			$title = $langs->trans("Services");

		print load_fiche_titre($title);

		/*
		 * Invoice lines
		 */
		print '<table class="notopnoleftnoright" width="100%">';
		print '<tr><td colspan="3">';

		$sql = 'SELECT l.*';
		$sql.= " FROM ".MAIN_DB_PREFIX."fichinterdet as l";
		$sql.= " WHERE l.fk_fichinter= ".$object->id;
		$sql.= " AND l.fk_product is null ";
		$sql.= " ORDER BY l.rang";

		$result = $db->query($sql);
		if ($result)
		{
			$num = $db->num_rows($result);
			$i = 0; $total = 0;

			echo '<table class="noborder" width="100%">';
			if ($num)
			{
				print '<tr class="liste_titre">';
				print '<td>'.$langs->trans("Description").'</td>';
				print '<td align="center">'.$langs->trans("Duration").'</td>';
				print "</tr>\n";
			}
			$var=true;
			while ($i < $num)
			{
				$objp = $db->fetch_object($result);
				$var=!$var;
				print "<tr ".$bc[$var].">";

				// Show product and description

				print '<td>';
				print '<a name="'.$objp->rowid.'"></a>'; // ancre pour retourner sur la ligne

				$text = img_object($langs->trans('Service'),'service');

				print $text.' '.nl2br($objp->description);

				// Qty
				print '<td align="center">'.convertSecondToTime($objp->duree).'</td>';


				print "</tr>";

				$i++;
			}

			$db->free($result);

		}
		else
		{
			print $db->error();
		}
		print "</table>";

		print '</td></tr>';

		print "</table>\n";

        dol_fiche_end();

		print '<div align="center"><input type="submit" class="button" value="'.$langs->trans("Create").'">';
        print '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	    print '<input type="button" class="button" value="' . $langs->trans("Cancel") . '" onClick="javascript:history.go(-1)">';
        print '</div>';
		print "</form>\n";
	}
	else
	{
		dol_print_error('',"Error, no invoice ".$object->id);
	}
}
elseif($action == 'selsocforcreatefrommodel')
{
	print load_fiche_titre($langs->trans("CreateRepeatableIntervention"),'','title_commercial.png');
	dol_fiche_head('');

	print '<form name="fichinter" action="'.$_SERVER['PHP_SELF'].'" method="POST">';
	print '<table class="border" width="100%">';
	print '<tr><td class="fieldrequired">'.$langs->trans("ThirdParty").'</td><td>';
	print $form->select_company('','socid','',1,1);
	print '</td></tr>';
	print '</table>';

	dol_fiche_end();

	print '<div class="center">';
	print '<input type="hidden" name="action" value="createfrommodel">';
	print '<input type="hidden" name="id" value="'.$id.'">';
	print '<input type="submit" class="button" value="'.$langs->trans("CreateDraftIntervention").'">';
	print '</div>';

	print '</form>';
}
else
{
	/*
	 * View mode
	 * 
	 */
	if ($id > 0)
	{
		if ($object->fetch($id) > 0)
		{
			$object->fetch_thirdparty();

			$author = new User($db);
			$author->fetch($object->user_author);

			$head=array();
			$h=0;
			$head[$h][0] = $_SERVER["PHP_SELF"].'?id='.$object->id;
			$head[$h][1] = $langs->trans("CardFichinter");
			$head[$h][2] = 'card';

			dol_fiche_head($head, 'card', $langs->trans("PredefinedInterventional"),0,'intervention');	// Add a div

			print '<table class="border" width="100%">';

			print '<tr><td width="25%">'.$langs->trans("Ref").'</td>';
			print '<td colspan="4">'.$object->titre.'</td>';

			print '<tr><td>'.$langs->trans("Customer").'</td>';
			print '<td colspan="3">';
			if ($object->socid > 0)
				print $object->thirdparty->getNomUrl(1,'customer');
			else
				print $langs->trans("None");
			print '</td></tr>';

			print "<tr><td>".$langs->trans("Author").'</td><td colspan="3">'.$author->getFullName($langs)."</td></tr>";

			if (empty($conf->global->FICHINTER_DISABLE_DETAILS))
			{
				// Duration
				print '<tr><td>'.$langs->trans("TotalDuration").'</td>';
				print '<td colspan="3">'.convertSecondToTime($object->duration, 'all', $conf->global->MAIN_DURATION_OF_WORKDAY).'</td>';
				print '</tr>';
			}
			
			// Project
			if (! empty($conf->projet->enabled) && $object->fk_project > 0)
			{
				print "<tr><td>".$langs->trans("Project")."</td><td>";
				if ($object->fk_project > 0)
				{
					$project = new Project($db);
					$project->fetch($object->fk_project);
					print $project->title;
				}
				print "</td></tr>";
			}
	
			// Contrat
			if (! empty($conf->contrat->enabled) && $object->fk_contrat > 0)
			{
				print "<tr><td>".$langs->trans("Contract")."</td><td>";
				if ($object->fk_contrat > 0)
				{
					$contrat = new Contrat($db);
					$contrat->fetch($object->fk_contrat);
					print $contrat->getNomUrl(3);
				}
				print "</td></tr>";
			}
			print '<tr><td>'.$langs->trans("Comment").'</td><td colspan="3">'.nl2br($object->description)."</td></tr>";

			print "</table>";

			print '</div>';

			/*
			 * Lines
			 */

			$title = $langs->trans("ProductsAndServices");
			if (empty($conf->service->enabled))
				$title = $langs->trans("Products");
			else if (empty($conf->product->enabled))
				$title = $langs->trans("Services");

			print load_fiche_titre($title);

			print '<table class="noborder" width="100%">';
			print '<tr class="liste_titre">';
			print '<td>'.$langs->trans("Description").'</td>';
			print '<td align="center">'.$langs->trans("Duration").'</td>';
			print '</tr>';

			$num = count($object->lines);
			$i = 0;
			$var=true;
			while ($i < $num)
			{
				$var=!$var;

				// Show product and description
				$type=(isset($object->lines[$i]->product_type)?$object->lines[$i]->product_type:$object->lines[$i]->fk_product_type);
				// Try to enhance type detection using date_start and date_end for free lines when type
				// was not saved.
				if (! empty($objp->date_start)) $type=1;
				if (! empty($objp->date_end)) $type=1;

				// Show line
				print "<tr ".$bc[$var].">";
				print '<td>';
				$text = img_object($langs->trans('Service'),'service');
				print $text.' '.nl2br($object->lines[$i]->desc);
				print '</td>';

				print '<td align="center">'.convertSecondToTime($object->lines[$i]->duree).'</td>';
				print "</tr>\n";
				$i++;
			}
			print '</table>';



			/**
			 * Barre d'actions
			 */
			print '<div class="tabsAction">';

			if ($user->rights->ficheinter->creer)
			{
				if ($object->socid > 0 ) 
					$actioncreate="";
				else
					$actioncreate="selsocfor";
				print'<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action='.$actioncreate.'createfrommodel&amp;socid='.$object->thirdparty->id.'&amp;id='.$object->id.'">'.$langs->trans("CreateFichInter").'</a></div>';
			}

			if ($user->rights->ficheinter->supprimer)
			{
				print '<div class="inline-block divButAction"><a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?action=delete&id='.$object->id.'">'.$langs->trans('Delete').'</a></div>';
			}

			print '</div>';
		}
		else
		{
			print $langs->trans("ErrorRecordNotFound");
		}
	}
	else
	{
		/*
		 *  List mode
		 */
		$sql = "SELECT s.nom as name, s.rowid as socid, f.rowid as fichinterid, f.titre, f.fk_contrat, f.duree, f.description";
		$sql.= " FROM ".MAIN_DB_PREFIX."fichinter_rec as f";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON f.fk_soc = s.rowid";
		$sql.= " WHERE f.entity = ".$conf->entity;
		if ($socid)	$sql .= " AND s.rowid = ".$socid;

		//$sql .= " ORDER BY $sortfield $sortorder, rowid DESC ";
		//	$sql .= $db->plimit($limit + 1,$offset);

		$resql = $db->query($sql);
		if ($resql)
		{
			$num = $db->num_rows($resql);
			print_barre_liste($langs->trans("RepeatableInterventional"),$page,$_SERVER['PHP_SELF'],"&socid=$socid",$sortfield,$sortorder,'',$num,'','title_commercial.png');

			print $langs->trans("ToCreateAPredefinedInterventional").'<br><br>';

			$i = 0;
			print '<table class="noborder" width="100%">';
			print '<tr class="liste_titre">';
			print_liste_field_titre($langs->trans("Title"));
			print_liste_field_titre($langs->trans("Company"),$_SERVER['PHP_SELF'],"s.nom","","", 'width="100px" align="left"',$sortfiled,$sortorder);
			print_liste_field_titre($langs->trans("Contract"),$_SERVER['PHP_SELF'],"f.fk_contrat","","",'width="100px" align="left"',$sortfiled,$sortorder);
			print_liste_field_titre($langs->trans("Duration"),'','','','','width="50px" align="right"');
			print_liste_field_titre($langs->trans("Description"),'','','','','align="left"');
			print "</tr>\n";

			if ($num > 0)
			{
				$var=true;
				while ($i < min($num,$limit))
				{
					$objp = $db->fetch_object($resql);
					$var=!$var;

					print "<tr ".$bc[$var].">";

					print '<td><a href="'.$_SERVER['PHP_SELF'].'?id='.$objp->fichinterid.'">'.img_object($langs->trans("ShowIntervention"),"intervention").' '.$objp->titre;
					print "</a></td>\n";
					if ($objp->socid)
					{
						$companystatic->id=$objp->socid;
						$companystatic->name=$objp->name;
						print '<td>'.$companystatic->getNomUrl(1,'customer').'</td>';
					}
					else
						print '<td>'.$langs->trans("None").'</td>';
					print '<td>';
					if ($objp->fk_contrat >0)
					{
						$contratstatic->fecth($objp->fk_contrat);
						print $contratstatic->getNomUrl(1);
					}
					print '</td>';
					
					print '<td align=right>'.convertSecondToTime($objp->duree).'</td>';
					print '<td align=left>'.dol_trunc($objp->description).'</td>';

					print "</tr>\n";
					$i++;
				}
			}
			else print '<tr '.$bc[false].'><td colspan="6">'.$langs->trans("NoneF").'</td></tr>';

			print "</table>";
			$db->free($resql);
		}
		else
		{
			dol_print_error($db);
		}
	}
}

llxFooter();
$db->close();
