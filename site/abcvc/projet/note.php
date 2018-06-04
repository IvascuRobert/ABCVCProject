<?php
/* Copyright (C) 2010 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2012 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.https://9gag.com/gag/av7ZW2Z
 */

/**
 *	\file       htdocs/projet/note.php
 *	\ingroup    project
 *	\brief      Fiche d'information sur un projet
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/lib/project.lib.php';

$langs->load('projects');

$action=GETPOST('action');
$id = GETPOST('id','int');
$ref= GETPOST('ref');

$mine = $_REQUEST['mode']=='mine' ? 1 : 0;
//if (! $user->rights->projet->all->lire) $mine=1;	// Special for projects

$object = new ProjectABCVC($db);

include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';  // Must be include, not include_once

// Security check
$socid=0;
//if ($user->societe_id > 0) $socid = $user->societe_id;    // For external user, no check is done on company because readability is managed by public status of project and assignement.
//$result = restrictedArea($user, 'projet', $id,'projet&project');

$permissionnote=$user->rights->projet->creer;	// Used by the include of actions_setnotes.inc.php


/*
 * Actions
 */

include DOL_DOCUMENT_ROOT.'/core/actions_setnotes.inc.php';	// Must be include, not includ_once


/*
 * View
 */

$title=$langs->trans("Project").' - '.$langs->trans("Note").' - '.$object->ref.' '.$object->name;
if (! empty($conf->global->MAIN_HTML_TITLE) && preg_match('/projectnameonly/',$conf->global->MAIN_HTML_TITLE) && $object->name) $title=$object->ref.' '.$object->name.' - '.$langs->trans("Note");
$help_url="EN:Module_Projects|FR:Module_Projets|ES:M&oacute;dulo_Proyectos";
llxHeader("",$title,$help_url);

// BOOTSTRAP 3 + css + js custom
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/abcvc_js_css.php';

/*ABCVC HEADER */ 
echo $object->getABCVCHeader($object->id, 'note');

$form = new Form($db);
$userstatic=new User($db);

$now=dol_now();

if ($id > 0 || ! empty($ref))
{
	// To verify role of users
	//$userAccess = $object->restrictedProjectArea($user,'read');
	$userWrite  = $object->restrictedProjectArea($user,'write');
	//$userDelete = $object->restrictedProjectArea($user,'delete');
	//print "userAccess=".$userAccess." userWrite=".$userWrite." userDelete=".$userDelete;

	$head = project_prepare_head($object);
	//dol_fiche_head($head, 'notes', $langs->trans('Project'), 0, ($object->public?'projectpub':'project'));

	
    ?>
        <div class="container-fluid">
            <div class="row">
                <div class="col-xs-12">
                
                    <?php    
                        $linkback = '';//<a href="'.DOL_URL_ROOT.SUPP_PATH.'/projet/list.php">'.$langs->trans("BackToList").'</a>';
                        
                        $morehtmlref='<div class="refidno">';
                        // Title
                        $morehtmlref.=$object->title;
                        // Thirdparty
                        if ($object->thirdparty->id > 0) 
                        {
                            $morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . $object->thirdparty->getNomUrl(1, 'project');
                        }
                        $morehtmlref.='</div>';
                        
                        // Define a complementary filter for search of next/prev ref.
                        if (! $user->rights->projet->all->lire)
                        {
                            $objectsListId = $object->getProjectsAuthorizedForUser($user,0,0);
                            $object->next_prev_filter=" rowid in (".(count($objectsListId)?join(',',array_keys($objectsListId)):'0').")";
                        }
                        
                        $object->dol_banner_tab($object, 'ref', $linkback, 0, 'ref', 'ref', $morehtmlref);
                    ?>
                    <hr />
                </div>
            </div>
        </div>

       

    <?php  
    //[TODO discuss opportunity for global project tree description overview here] 
	
	
	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';
	
	$cssclass="titlefield";
    // $cssclass must be defined by caller. For example cssclass='fieldtitle"
    $module = $object->element;
    $note_public = 'note_public';
    $note_private = 'note_private';

    $colwidth=(isset($colwidth)?$colwidth:(empty($cssclass)?'25':''));

    $permission=(isset($permission)?$permission:(isset($user->rights->$module->creer)?$user->rights->$module->creer:0));    // If already defined by caller page
    $moreparam=(isset($moreparam)?$moreparam:'');
    $value_public=$object->note_public;
    $value_private=$object->note_private;
    if (! empty($conf->global->MAIN_AUTO_TIMESTAMP_IN_PUBLIC_NOTES))
    {
        $stringtoadd=dol_print_date(dol_now(), 'dayhour').' '.$user->getFullName($langs).' --';
        if (GETPOST('action') == 'edit'.$note_public)
        {
            $value_public=dol_concatdesc($value_public, ($value_public?"\n":"")."-- ".$stringtoadd);
            if (dol_textishtml($value_public)) $value_public.="<br>\n";
            else $value_public.="\n";
        }
    }
    if (! empty($conf->global->MAIN_AUTO_TIMESTAMP_IN_PRIVATE_NOTES))
    {
        $stringtoadd=dol_print_date(dol_now(), 'dayhour').' '.$user->getFullName($langs).' --';
        if (GETPOST('action') == 'edit'.$note_private)
        {
            $value_private=dol_concatdesc($value_private, ($value_private?"\n":"")."-- ".$stringtoadd);
            if (dol_textishtml($value_private)) $value_private.="<br>\n";
            else $value_private.="\n";
        }
    }

    // Special cases
    if ($module == 'propal')                 { $permission=$user->rights->propale->creer;}
    elseif ($module == 'supplier_proposal')  { $permission=$user->rights->supplier_proposal->creer;}
    elseif ($module == 'fichinter')          { $permission=$user->rights->ficheinter->creer;}
    elseif ($module == 'projectabcvc')       { $permission=$user->rights->projet->creer;}
    elseif ($module == 'projectabcvc_task')  { $permission=$user->rights->projet->creer;}
    elseif ($module == 'invoice_supplier')   { $permission=$user->rights->fournisseur->facture->creer;}
    elseif ($module == 'order_supplier')     { $permission=$user->rights->fournisseur->commande->creer;}
    elseif ($module == 'societe')            { $permission=$user->rights->societe->creer;}
    elseif ($module == 'contact')            { $permission=$user->rights->societe->creer;}
    elseif ($module == 'shipping')           { $permission=$user->rights->expedition->creer;}
    elseif ($module == 'product')            { $permission=$user->rights->produit->creer;}
    //else dol_print_error('','Bad value '.$module.' for param module');

    if (! empty($conf->global->FCKEDITOR_ENABLE_SOCIETE)) $typeofdata='ckeditor:dolibarr_notes:100%:200::1:12:95%'; // Rem: This var is for all notes, not only thirdparties note.
    else $typeofdata='textarea:12:95%';

    ?>

    <!-- BEGIN PHP TEMPLATE NOTES -->
    <div class="tagtable border table-border centpercent">
    <?php if ($module != 'product') {   // No public note yet on products ?>
        <div class="tagtr table-border-row">
            <div class="tagtd tdtop table-key-border-col<?php echo (empty($cssclass)?'':' '.$cssclass); ?>"<?php echo ($colwidth ? ' style="width: '.$colwidth.'%"' : ''); ?>><?php echo $form->editfieldkey("NotePublic", $note_public, $value_public, $object, $permission, $typeofdata, $moreparam, '', 0); ?></div>
            <div class="tagtd table-val-border-col"><?php echo $form->editfieldval("NotePublic", $note_public, $value_public, $object, $permission, $typeofdata, '', null, null, $moreparam, 1); ?></div>
        </div>
    <?php } ?>
    <?php if (empty($user->societe_id)) { ?>
        <div class="tagtr table-border-row">
            <div class="tagtd tdtop table-key-border-col<?php echo (empty($cssclass)?'':' '.$cssclass); ?>"<?php echo ($colwidth ? ' style="width: '.$colwidth.'%"' : ''); ?>><?php echo $form->editfieldkey("NotePrivate", $note_private, $value_private, $object, $permission, $typeofdata, $moreparam, '', 0); ?></div>
            <div class="tagtd table-val-border-col"><?php echo $form->editfieldval("NotePrivate", $note_private, $value_private, $object, $permission, $typeofdata, '', null, null, $moreparam, 1); ?></div>
        </div>
    <?php } ?>
    </div>
    <!-- END PHP TEMPLATE NOTES-->
    	<?php 
    	print '</div>';
    	
    	print '<div class="clearboth"></div>';
    	
    	dol_fiche_end();
}
//var_dump($object);
llxFooter();

$db->close();
