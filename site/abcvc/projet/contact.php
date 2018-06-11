<?php
/* Copyright (C) 2010      Regis Houssin       <regis.houssin@capnetworks.com>
 * Copyright (C) 2012-2015 Laurent Destailleur <eldy@users.sourceforge.net>
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
 *       \file       htdocs/projet/contact.php
 *       \ingroup    project
 *       \brief      Onglet de gestion des contacts du projet
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';

$langs->load("projects");
$langs->load("companies");

$id     = GETPOST('id','int');
$ref    = GETPOST('ref','alpha');
$lineid = GETPOST('lineid','int');
$socid  = GETPOST('socid','int');
$action = GETPOST('action','alpha');

$mine   = GETPOST('mode')=='mine' ? 1 : 0;
//if (! $user->rights->projet->all->lire) $mine=1;	// Special for projects

$object = new ProjectABCVC($db);

include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';  // Must be include, not include_once

// Security check
$socid=0;
//if ($user->societe_id > 0) $socid = $user->societe_id;    // For external user, no check is done on company because readability is managed by public status of project and assignement.
//
//$result = restrictedArea($user, 'projet', $id,'projet&project');


/*
 * Actions
 */

// Add new contact
if ($action == 'addcontact' && $user->rights->projet->creer)
{
	$result = 0;
	$result = $object->fetch($id);

    if ($result > 0 && $id > 0)
    {
  		$contactid = (GETPOST('userid') ? GETPOST('userid','int') : GETPOST('contactid','int'));
  		$result = $object->add_contact($contactid, $_POST["type"], $_POST["source"]);
    }

	if ($result >= 0)
	{
		header("Location: ".$_SERVER['PHP_SELF']."?id=".$object->id);
		exit;
	}
	else
	{
		if ($object->error == 'DB_ERROR_RECORD_ALREADY_EXISTS')
		{
			$langs->load("errors");
			setEventMessages($langs->trans("ErrorThisContactIsAlreadyDefinedAsThisType"), null, 'errors');
		}
		else
		{
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
}

// bascule du statut d'un contact
if ($action == 'swapstatut' && $user->rights->projet->creer)
{
	if ($object->fetch($id))
	{
	    $result=$object->swapContactStatus(GETPOST('ligne','int'));
	}
	else
	{
		dol_print_error($db);
	}
}

// Efface un contact
if (($action == 'deleteline' || $action == 'deletecontact') && $user->rights->projet->creer)
{
	$object->fetch($id);
	$result = $object->delete_contact(GETPOST("lineid"));

	if ($result >= 0)
	{
		header("Location: contact.php?id=".$object->id);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}


/*
 * View
 */


$title=$langs->trans("ProjectContact").' - '.$object->ref.' '.$object->name;
if (! empty($conf->global->MAIN_HTML_TITLE) && preg_match('/projectnameonly/',$conf->global->MAIN_HTML_TITLE) && $object->name) $title=$object->ref.' '.$object->name.' - '.$langs->trans("ProjectContact");
$help_url="EN:Module_Projects|FR:Module_Projets|ES:M&oacute;dulo_Proyectos";
llxHeader('', $title, $help_url);


// BOOTSTRAP 3 + css + js custom
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/abcvc_js_css.php';


/*ABCVC HEADER */ 
echo $object->getABCVCHeader($object->id, 'contact');

$form = new Form($db);
$formcompany= new FormCompany($db);
$contactstatic=new Contact($db);
$userstatic=new User($db);


/* *************************************************************************** */
/*                                                                             */
/* Mode vue et edition                                                         */
/*                                                                             */
/* *************************************************************************** */

if ($id > 0 || ! empty($ref))
{
	// To verify role of users
	//$userAccess = $object->restrictedProjectArea($user,'read');
	$userWrite  = $object->restrictedProjectArea($user,'write');
	//$userDelete = $object->restrictedProjectArea($user,'delete');
	//print "userAccess=".$userAccess." userWrite=".$userWrite." userDelete=".$userDelete;


	$head = project_prepare_head($object);
	//dol_fiche_head($head, 'contact', $langs->trans("Project"), 0, ($object->public?'projectpub':'project'));
    ?>
        <div class="container-fluid">
            <div class="row">
                <div class="col-xs-12">
                
                    <?php    
                        $linkback = ''; //<a href="'.DOL_URL_ROOT.SUPP_PATH.'/projet/list.php">'.$langs->trans("BackToList").'</a>';
                        
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



<!--******************************************************************************************************
// 
// 							GENERATE TABLE WITH USERS WHICH PARTICIPATE ON TASKS 
//
//	getProjectTree($id_project, $user, $full ) 
// 	$full	0 - TO SHOW ALL informations about contacts 
//			1 - to show just rowid
//	
// 
//******************************************************************************************************-->
<?php $projectTree = $object->getProjectTree($id, $user,0); ?>
<div class="container-fluid">
    <div class="row">
        <div class="col-xs-12">
			<table class="table table-hover display responsive no-wrap display" width="100%" cellspacing="0" id="table_contacts">
				<thead>
					<tr>
						<th>Denumire Task</th>
						<th>Tip</th>
						<th>Colaboratori</th>
					</tr>
				</thead>

				<tfoot>
					<tr>
						<th>Denumire Task</th>
						<th>Tip</th>
						<th>Colaboratori</th>
					</tr>
		        </tfoot>

				<tbody>
					<?php foreach ($projectTree['tree'] as $key => $lot) : ?>
						<?php foreach ($lot->categories as $key => $categorie) : ?>
							<?php foreach ($categorie->postes as $key => $poste) : ?>
									<tr>
										<td><?php echo $poste->ref; ?>&nbsp;<?php echo $poste->label; ?></td>
										<td>
											<?php foreach ($poste->contacts_executive as $contact_executive): ?>
												<?php  $contact_executive['code']; echo ('Pilote(s) tâche'); ?><br>
											<?php endforeach ?>
											<br>
											<?php foreach ($poste->contacts_contributor as $contact_contributor): ?>
												<?php  $contact_contributor['code']; echo ('Intervenant(s)'); ?><br>
											<?php endforeach ?>
										</td>
										<td>
											<?php foreach ($poste->contacts_executive as $contact_executive): 
											//var_dump($contact_executive);
											?>
												<a href="/user/card.php?id=<?php echo $contact_executive['id'];?>">
												<?php echo $contact_executive['lastname'];  ?>&nbsp;<?php echo $contact_executive['firstname'];  ?><br>
												</a>
											<?php endforeach ?>
											<br>
											<?php foreach ($poste->contacts_contributor as $contact_contributor): ?>
												<a href="/user/card.php?id=<?php echo $contact_contributor['id'];?>">
												<?php echo $contact_contributor['lastname'];  ?>&nbsp;<?php echo $contact_contributor['firstname'];  ?><br>
												</a>
											<?php endforeach ?>
										</td>
									</tr>
								
								<?php /*foreach ($poste->subpostes as $key => $subposte) : ?>
										<tr>
											<td><?php echo $subposte->ref; ?>&nbsp;<?php echo $subposte->label; ?></td>
											<td>
												<?php foreach ($subposte->contacts_executive as $contact_executive): ?>
													<?php  $contact_executive['code']; echo ('Pilote(s) tâche'); ?><br>
												<?php endforeach ?>
												<br>
												<?php foreach ($subposte->contacts_contributor as $contact_contributor): ?>
													<?php  $contact_contributor['code']; echo ('Intervenant(s)'); ?><br>
												<?php endforeach ?>
											</td>
											<td>
												<?php foreach ($subposte->contacts_executive as $contact_executive): ?>
													<?php echo $contact_executive['lastname'];  ?>&nbsp;<?php echo $contact_executive['firstname'];  ?><br>
												<?php endforeach ?>
												<br>
												<?php foreach ($subposte->contacts_contributor as $contact_contributor): ?>
													<?php echo $contact_contributor['lastname'];  ?>&nbsp;<?php echo $contact_contributor['firstname'];  ?><br>
												<?php endforeach ?>
											</td>
										</tr>
								
									<?php foreach ($subposte->subsubpostes as $key => $subsubposte) : ?>
											<tr>
												<td><?php echo $subsubposte->ref; ?>&nbsp;<?php echo $subsubposte->label; ?></td>
												<td>
													<?php foreach ($subsubposte->contacts_executive as $contact_executive): ?>
														<?php  $contact_executive['code']; echo ('Pilote(s) tâche'); ?><br>
													<?php endforeach ?>
													<br>
													<?php foreach ($subsubposte->contacts_contributor as $contact_contributor): ?>
														<?php  $contact_contributor['code']; echo ('Intervenant(s)'); ?><br>
													<?php endforeach ?>
												</td>
												<td>
													<?php foreach ($subsubposte->contacts_executive as $contact_executive): ?>
														<?php echo $contact_executive['lastname'];  ?>&nbsp;<?php echo $contact_executive['firstname'];  ?><br>
													<?php endforeach ?>
													<br>
													<?php foreach ($subsubposte->contacts_contributor as $contact_contributor): ?>
														<?php echo $contact_contributor['lastname'];  ?>&nbsp;<?php echo $contact_contributor['firstname'];  ?><br>
													<?php endforeach ?>
												</td>
											</tr>
									<?php endforeach; ?>
								<?php endforeach;*/ ?>
							<?php endforeach; ?>
						<?php endforeach; ?>
					<?php endforeach; ?>			
				</tbody>
			</table> 
        </div>
    </div>
</div>
<?php //var_dump($projectTree); ?>

<?php /*

<!-- BEGIN PHP TEMPLATE CONTACTS -->
	<div class="div-table-responsive">
	<div class="tagtable centpercent noborder allwidth">
	<?php $projectTree = $object->getProjectTree($id, $user);
			var_dump($projectTree); ?>
		
		<?php $var=true; ?>

		<?php

		$arrayofsource=array('internal','external');	// Show both link to user and thirdparties contacts
		//var_dump($object);
		foreach($arrayofsource as $source) {

			$tmpobject=$object;
			if ($object->element == 'projectabcvc' && is_object($objectsrc)) $tmpobject=$objectsrc;

			$tab = $tmpobject->liste_contact(4,$source,0,'TASKEXECUTIVE');
			$num=count($tab);

			$i = 0;
			while ($i < $num) {
				$var = !$var;
				;
		?>

		<form class="tagtr <?php echo $var?"pair":"impair"; ?>">
			<div class="tagtd" align="left">
				<?php if ($tab[$i]['source']=='internal') echo $langs->trans("User"); ?>
				<?php if ($tab[$i]['source']=='external') echo $langs->trans("ThirdPartyContact"); ?>
			</div>
			<div class="tagtd" align="left">
				<?php
				if ($tab[$i]['socid'] > 0)
				{
					$companystatic->fetch($tab[$i]['socid']);
					echo $companystatic->getNomUrl(1);
				}
				if ($tab[$i]['socid'] < 0)
				{
					echo $conf->global->MAIN_INFO_SOCIETE_NOM;
				}
				if (! $tab[$i]['socid'])
				{
					echo '&nbsp;';
				}
				?>
			</div>
			<div class="tagtd">
				<?php
				$statusofcontact = $tab[$i]['status'];

				if ($tab[$i]['source']=='internal')
				{
					$userstatic->id=$tab[$i]['id'];
					$userstatic->lastname=$tab[$i]['lastname'];
					$userstatic->firstname=$tab[$i]['firstname'];
					echo $userstatic->getNomUrl(1);
				}
				if ($tab[$i]['source']=='external')
				{
					$contactstatic->id=$tab[$i]['id'];
					$contactstatic->lastname=$tab[$i]['lastname'];
					$contactstatic->firstname=$tab[$i]['firstname'];
					echo $contactstatic->getNomUrl(1);
				}
				?>
			</div>
			<div class="tagtd"><?php echo $tab[$i]['libelle']; ?></div>
			<div class="tagtd" align="center">
				<?php //if ($object->statut >= 0) echo '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&amp;action=swapstatut&amp;ligne='.$tab[$i]['rowid'].'">'; ?>
				<?php
				if ($tab[$i]['source']=='internal')
				{
					$userstatic->id=$tab[$i]['id'];
					$userstatic->lastname=$tab[$i]['lastname'];
					$userstatic->firstname=$tab[$i]['firstname'];
					echo $userstatic->LibStatut($tab[$i]['statuscontact'],3);
				}
				if ($tab[$i]['source']=='external')
				{
					$contactstatic->id=$tab[$i]['id'];
					$contactstatic->lastname=$tab[$i]['lastname'];
					$contactstatic->firstname=$tab[$i]['firstname'];
					echo $contactstatic->LibStatut($tab[$i]['statuscontact'],3);
				}
				?>
				<?php //if ($object->statut >= 0) echo '</a>'; ?>
			</div>
			<div class="tagtd nowrap" align="right">
				<?php if ($permission) { ?>
					&nbsp;<a href="<?php echo $_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=deletecontact&amp;lineid='.$tab[$i]['rowid']; ?>"><?php echo img_delete(); ?></a>
				<?php } ?>
			</div>
		</form>

	<?php $i++; ?>
	<?php } //var_dump($tmpobject);} ?>

	</div>
	</div>

    <?php            
  */  
   
}



llxFooter();

$db->close();