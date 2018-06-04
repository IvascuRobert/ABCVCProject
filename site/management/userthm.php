<?php
/* Copyright (C) 2002-2006 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2002-2003 Jean-Louis Bergamo   <jlb@j1b.org>
 * Copyright (C) 2004-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2004      Eric Seigne          <eric.seigne@ryxeo.com>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2005      Lionel Cousteix      <etm_ltd@tiscali.co.uk>
 * Copyright (C) 2011      Herve Prot           <herve.prot@symeos.com>
 * Copyright (C) 2012      Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2013      Florian Henry        <florian.henry@open-concept.pro>
 * Copyright (C) 2013-2014 Alexandre Spangaro   <alexandre.spangaro@gmail.com>
 * Copyright (C) 2014-2016	Charlie BENKE		<charlie@patas-monkey.com>
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
 *       \file       /management/userthm.php
 *       \brief      Tab of user for thm input
 */

$res=@include("../main.inc.php");                    // For root directory
if (! $res && file_exists($_SERVER['DOCUMENT_ROOT']."/main.inc.php"))
    $res=@include($_SERVER['DOCUMENT_ROOT']."/main.inc.php"); // Use on dev env only
if (! $res) $res=@include("../../main.inc.php");        // For "custom" directory


require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
dol_include_once ('/management/core/lib/management.lib.php');


$id			= GETPOST('id','int');
$action		= GETPOST('action','alpha');
$confirm	= GETPOST('confirm','alpha');
$subaction	= GETPOST('subaction','alpha');
$group		= GETPOST("group","int",3);

// Define value to know what current user can do on users
$canadduser=(! empty($user->admin) || $user->rights->user->user->creer);
$canreaduser=(! empty($user->admin) || $user->rights->user->user->lire);
$canedituser=(! empty($user->admin) || $user->rights->user->user->creer);
$candisableuser=(! empty($user->admin) || $user->rights->user->user->supprimer);
$canreadgroup=$canreaduser;
$caneditgroup=$canedituser;
if (! empty($conf->global->MAIN_USE_ADVANCED_PERMS))
{
    $canreadgroup=(! empty($user->admin) || $user->rights->user->group_advance->read);
    $caneditgroup=(! empty($user->admin) || $user->rights->user->group_advance->write);
}
// Define value to know what current user can do on properties of edited user
if ($id)
{
    // $user est le user qui edite, $id est l'id de l'utilisateur edite
    $caneditfield=((($user->id == $id) && $user->rights->user->self->creer)
    || (($user->id != $id) && $user->rights->user->user->creer));
    $caneditpassword=((($user->id == $id) && $user->rights->user->self->password)
    || (($user->id != $id) && $user->rights->user->user->password));
}

// Security check
$socid=0;
if ($user->societe_id > 0) $socid = $user->societe_id;
$feature2='user';
if ($user->id == $id) { $feature2=''; $canreaduser=1; } // A user can always read its own card
if (!$canreaduser) {
	$result = restrictedArea($user, 'user', $id, '&user', $feature2);
}
if ($user->id <> $id && ! $canreaduser) accessforbidden();

$langs->load("users");
$langs->load("companies");
$langs->load("ldap");

$object = new User($db);

$object->fetch($id);

/**
 * Actions
 */

if ($action == 'setthm' )
{
	set_thm($id, GETPOST('thmvalue'));
	$object->thm=GETPOST('thmvalue');
}



/*
 * View
 */

$form = new Form($db);
$formother=new FormOther($db);

llxHeader('',$langs->trans("UserCard"));


/* ************************************************************************** */
/*                                                                            */
/* View and edition                                                            */
/*                                                                            */
/* ************************************************************************** */

if ($id > 0)
{

	if ($res < 0) { dol_print_error($db,$object->error); exit; }
		$res=$object->fetch_optionals($object->id,$extralabels);
	
	
	// Show tabs
	$head = user_prepare_head($object);
	
	$title = $langs->trans("User");
	dol_fiche_head($head, 'management', $title, 0, 'user');
	
	/*
	* Fiche en mode visu
	*/

		$rowspan=17;

		print '<table class="border" width="100%">';

		// Ref
		print '<tr><td width="25%" valign="top">'.$langs->trans("Ref").'</td>';
		print '<td colspan="3">';
		print $form->showrefnav($object,'id','',$user->rights->user->user->lire || $user->admin);
		print '</td>';
		print '</tr>'."\n";

		if (isset($conf->file->main_authentication) && preg_match('/openid/',$conf->file->main_authentication) && ! empty($conf->global->MAIN_OPENIDURL_PERUSER)) $rowspan++;
		if (! empty($conf->societe->enabled)) $rowspan++;
		if (! empty($conf->adherent->enabled)) $rowspan++;
		if (! empty($conf->skype->enabled)) $rowspan++;
		$rowspan = $rowspan+3;
		if (! empty($conf->agenda->enabled)) $rowspan++;

		// Lastname
		print '<tr><td valign="top">'.$langs->trans("Lastname").'</td>';
		print '<td colspan="2">'.$object->lastname.'</td>';

		// Photo
		print '<td align="center" valign="middle" width="25%" rowspan="'.$rowspan.'">';
		print $form->showphoto('userphoto',$object,100);
		print '</td>';

		print '</tr>'."\n";

		// Firstname
		print '<tr><td valign="top">'.$langs->trans("Firstname").'</td>';
		print '<td colspan="2">'.$object->firstname.'</td>';
		print '</tr>'."\n";

		// Position/Job
		print '<tr><td valign="top">'.$langs->trans("PostOrFunction").'</td>';
		print '<td colspan="2">'.$object->job.'</td>';
		print '</tr>'."\n";


	// THM
	print '<tr><td><table class="nobordernopadding" width="100%"><tr><td><B>'.$langs->trans("THM").'</b></td>';
	if ($action != 'editthm') print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editthm&amp;id='.$object->id.'">'.img_edit($langs->trans('Modify'),1).'</a></td>';
	print '</tr></table></td><td colspan="2">';
	if ($action == 'editthm')
	{
		print '<form name="editstock" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="setthm">';
		print '<input type="text" name="thmvalue" value="'.$object->thm.'">';
		print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
		print '</form>';
	}
	else
	{
		print ($object->thm!=''?price($object->thm,'',$langs,1,-1,-1,$conf->currency):'');
	}
	print '</td></tr>';


		
		// Status
		print '<tr><td valign="top">'.$langs->trans("Status").'</td>';
		print '<td colspan="2">';
		print $object->getLibStatut(4);
		print '</td>';
		print '</tr>'."\n";
		
		print '<tr><td valign="top">'.$langs->trans("LastConnexion").'</td>';
		print '<td colspan="2">'.dol_print_date($object->datelastlogin,"dayhour").'</td>';
		print "</tr>\n";
		
		print '<tr><td valign="top">'.$langs->trans("PreviousConnexion").'</td>';
		print '<td colspan="2">'.dol_print_date($object->datepreviouslogin,"dayhour").'</td>';
		print "</tr>\n";
		
		
		print "</table>\n";
		
		print "</div>\n";
		
		print "</div>\n";
		print "<br>\n";



}



llxFooter();
$db->close();
