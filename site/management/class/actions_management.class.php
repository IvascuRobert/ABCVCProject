<?php
/* Copyright (C) 2015-2017		Charlie Benke	<charlie@patas-monkey.com>
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
 * 	\file       htdocs/management/class/actions_management.class.php
 * 	\ingroup    extrodt
 * 	\brief      Fichier de la classe des actions/hooks de management (pour les agendas partagés)
 */
 
class ActionsManagement // extends CommonObject 
{
	/** Overloading the doActions function : replacing the parent's function with the one below 
	 *  @param      parameters  meta datas of the hook (context, etc...) 
	 *  @param      object             the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...) 
	 *  @param      action             current action (if set). Generally create or edit or null 
	 *  @return       void 
	 */
	 

// METTRE A JOUR POUR FAIRE FoncTIONNER L'AGENDA COMMUN pour les fiches inters (ou revoir process)
	 
	function getCalendarEvents($parameters, $object, $action) 
	{
		global $conf, $langs, $db;
		global $month, $day, $year;
		global $firstdaytoshow, $lastdaytoshow;
		$this->results['eventarray'] =array();
if (false)
{
		if ($conf->ficheinter->enabled)
		{
			// add fichtinter
			$sql = 'SELECT fi.rowid, fi.ref, fi.description,';
			$sql.= ' fi.dateo,';
			$sql.= ' fi.datee,';
			$sql.= ' fi.fulldayevent,';
			$sql.= ' fi.fk_user_author,';
			$sql.= ' fi.fk_user_valid,';
			$sql.= ' fi.fk_projet,';
			$sql.= ' fi.fk_soc,';
			$sql.= ' fi.fk_statut ';
			$sql.= ' FROM '.MAIN_DB_PREFIX.'fichinter as fi';
			$sql.= ', '.MAIN_DB_PREFIX.'user as u';
			$sql.= ' WHERE fi.fk_user_author = u.rowid';
			$sql.= ' AND u.entity in (0,'.$conf->entity.')';	// To limit to entity
			//$sql.= ' AND u.entity = '.$conf->entity;
			//if ($user->societe_id) $sql.= ' AND a.fk_soc = '.$user->societe_id; // To limit to external user company
			if ($pid) $sql.=" AND fi.fk_projet=".$db->escape($pid);
			if ($cid) $sql.=" AND fi.fk_contrat=".$db->escape($cid);
			if ($action == 'show_day')
			{
				$sql.= " AND (";
				$sql.= " (fi.dateo BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,$day,$year))."'";
				$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,$day,$year))."')";
				$sql.= " OR ";
				$sql.= " (fi.datee BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,$day,$year))."'";
				$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,$day,$year))."')";
				$sql.= " OR ";
				$sql.= " (fi.dateo < '".$db->idate(dol_mktime(0,0,0,$month,$day,$year))."'";
				$sql.= " AND fi.datee > '".$db->idate(dol_mktime(23,59,59,$month,$day,$year))."')";
				$sql.= ')';
			}
			else
			{
				// To limit array
				$sql.= " AND (";
				$sql.= " (fi.dateo BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,1,$year)-(60*60*24*7))."'";   // Start 7 days before
				$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,28,$year)+(60*60*24*10))."')";			// End 7 days after + 3 to go from 28 to 31
				$sql.= " OR ";
				$sql.= " (fi.datee BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,1,$year)-(60*60*24*7))."'";
				$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,28,$year)+(60*60*24*10))."')";
				$sql.= " OR ";
				$sql.= " (fi.dateo < '".$db->idate(dol_mktime(0,0,0,$month,1,$year)-(60*60*24*7))."'";
				$sql.= " AND fi.datee > '".$db->idate(dol_mktime(23,59,59,$month,28,$year)+(60*60*24*10))."')";
				$sql.= ')';
			}
			if ($filtera > 0 || $filtert > 0 || $filterd > 0)
			{
				$sql.= " AND (";
				if ($filtera > 0) $sql.= " fi.fk_user_author = ".$filtera;
				//if ($filtert > 0) $sql.= ($filtera>0?" OR ":"")." fi.fk_user_action = ".$filtert;
				if ($filterd > 0) $sql.= ($filtera>0||$filtert>0?" OR ":"")." fi.fk_user_valid = ".$filterd;
				$sql.= ")";
			}
			$fichinterstatut=GETPOST("fichinterstatut");
			if ($fichinterstatut=="")
				$fichinterstatut=-1;
			if ($fichinterstatut >=0)
				$sql.= " AND fi.fk_statut = ".GETPOST("fichinterstatut");
			// Sort on date
			$sql.= ' ORDER BY dateo';

			dol_syslog("management/fichinter/calendar.php sql=".$sql, LOG_DEBUG);
			$resql=$db->query($sql);
			if ($resql)
			{
				$num = $db->num_rows($resql);
				$i=0;
				while ($i < $num)
				{
					$obj = $db->fetch_object($resql);

					// Create a new object action
					dol_include_once("/management/class/managementfichinter.class.php");
					$fichinter=new Fichinter($db);
					$fichinter->id=$obj->rowid;	
					$fichinter->ref=$obj->ref;	
					
					$fichinter->dateo=$db->jdate($obj->dateo);  
					$fichinter->datee=$db->jdate($obj->datee);
					$fichinter->type_code="FICHINTER";
					$fichinter->libelle=$obj->description;
					
					$fichinter->author = New User($db);
					$fichinter->usertodo = New User($db);
					$fichinter->userdone = New User($db);
					$fichinter->author->id=$obj->fk_user_author;
					$fichinter->usertodo->id=$obj->fk_user_action;
					$fichinter->userdone->id=$obj->fk_user_done;
					
					$fichinter->userassigned=array(fk_user_author);
					
					$fichinter->societe= New Societe($db);
					$fichinter->contact= New Contact($db);
					$fichinter->societe->id=$obj->fk_soc;
					$fichinter->contact->id=$obj->fk_contact;

					$fichinter->fulldayevent=$obj->fulldayevent;
					$fichinter->fk_statut=$obj->fk_statut;
					$fichinter->fk_projet=$obj->fk_projet;
			
					// Defined date_start_in_calendar and date_end_in_calendar property
					// They are date start and end of action but modified to not be outside calendar view.
					$fichinter->date_start_in_calendar=$fichinter->dateo;
					if ($fichinter->datee != '' && $fichinter->datee >= $fichinter->dateo) 
						$fichinter->date_end_in_calendar=$fichinter->datee;
					else
						$fichinter->date_end_in_calendar=$fichinter->dateo;
		
					// Define ponctual property
					if ($fichinter->date_start_in_calendar == $fichinter->date_end_in_calendar)
					{
						$fichinter->ponctuel=1;
					}
			
					$fichinter->type_code='AC_OTH_AUTO';
					$fichinter->icalname='fichinter';
					$fichinter->icalcolor='#C0C0C0';
			
					// Check values
					if ($fichinter->date_end_in_calendar < $firstdaytoshow ||
					$fichinter->date_start_in_calendar > $lastdaytoshow)
					{
						// This record is out of visible range
					}
					else
					{
						if ($fichinter->date_start_in_calendar < $firstdaytoshow)
							$fichinter->date_start_in_calendar=$firstdaytoshow;
						if ($fichinter->date_end_in_calendar > $lastdaytoshow)
							$fichinter->date_end_in_calendar=$lastdaytoshow;
			
						// Add an entry in actionarray for each day
						$daycursor=$fichinter->date_start_in_calendar;
						$annee = date('Y',$daycursor);
						$mois = date('m',$daycursor);
						$jour = date('d',$daycursor);
			
						// Loop on each day covered by action to prepare an index to show on calendar
						$loop=true; $j=0;
						$daykey=dol_mktime(0,0,0,$mois,$jour,$annee);
						do
						{
							//print 'daykey='.$daykey.' '.$fichinter->datep.' '.$fichinter->datef.'<br>';
							$FichInterArray[$daykey][]=$fichinter;
							$j++;
			
							$daykey+=60*60*24;
							if ($daykey > $fichinter->date_end_in_calendar) $loop=false;
						}
						while ($loop);
						$this->results['eventarray'] = $this->results['eventarray'] + $FichInterArray;
					}
					$i++;
				}
			}


			// add fichinterDET
			$sql = 'SELECT fi.rowid, fi.ref, fi.description,';
			$sql.= ' fi.dateo,';
			$sql.= ' fi.datee,';
			$sql.= ' fi.fulldayevent,';
			$sql.= ' fi.fk_user_author,';
			$sql.= ' fi.fk_user_valid,';
			$sql.= ' fi.fk_projet,';
			$sql.= ' fi.fk_soc,';
			$sql.= ' fi.fk_statut ';
			$sql.= ' FROM '.MAIN_DB_PREFIX.'fichinter as fi';
			$sql.= ' , '.MAIN_DB_PREFIX.'fichinter as fid';
			$sql.= ', '.MAIN_DB_PREFIX.'user as u';
			$sql.= ' WHERE fi.fk_user_author = u.rowid';
			$sql.= ' AND u.entity in (0,'.$conf->entity.')';	// To limit to entity
			//$sql.= ' AND u.entity = '.$conf->entity;
			//if ($user->societe_id) $sql.= ' AND a.fk_soc = '.$user->societe_id; // To limit to external user company
			if ($pid) $sql.=" AND fi.fk_projet=".$db->escape($pid);
			if ($cid) $sql.=" AND fi.fk_contrat=".$db->escape($cid);
			if ($action == 'show_day')
			{
				$sql.= " AND (";
				$sql.= " (fi.dateo BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,$day,$year))."'";
				$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,$day,$year))."')";
				$sql.= " OR ";
				$sql.= " (fi.datee BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,$day,$year))."'";
				$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,$day,$year))."')";
				$sql.= " OR ";
				$sql.= " (fi.dateo < '".$db->idate(dol_mktime(0,0,0,$month,$day,$year))."'";
				$sql.= " AND fi.datee > '".$db->idate(dol_mktime(23,59,59,$month,$day,$year))."')";
				$sql.= ')';
			}
			else
			{
				// To limit array
				$sql.= " AND (";
				$sql.= " (fi.dateo BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,1,$year)-(60*60*24*7))."'";   // Start 7 days before
				$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,28,$year)+(60*60*24*10))."')";			// End 7 days after + 3 to go from 28 to 31
				$sql.= " OR ";
				$sql.= " (fi.datee BETWEEN '".$db->idate(dol_mktime(0,0,0,$month,1,$year)-(60*60*24*7))."'";
				$sql.= " AND '".$db->idate(dol_mktime(23,59,59,$month,28,$year)+(60*60*24*10))."')";
				$sql.= " OR ";
				$sql.= " (fi.dateo < '".$db->idate(dol_mktime(0,0,0,$month,1,$year)-(60*60*24*7))."'";
				$sql.= " AND fi.datee > '".$db->idate(dol_mktime(23,59,59,$month,28,$year)+(60*60*24*10))."')";
				$sql.= ')';
			}
			if ($filtera > 0 || $filtert > 0 || $filterd > 0)
			{
				$sql.= " AND (";
				if ($filtera > 0) $sql.= " fi.fk_user_author = ".$filtera;
				//if ($filtert > 0) $sql.= ($filtera>0?" OR ":"")." fi.fk_user_action = ".$filtert;
				if ($filterd > 0) $sql.= ($filtera>0||$filtert>0?" OR ":"")." fi.fk_user_valid = ".$filterd;
				$sql.= ")";
			}
			$fichinterstatut=GETPOST("fichinterstatut");
			if ($fichinterstatut=="")
				$fichinterstatut=-1;
			if ($fichinterstatut >=0)
				$sql.= " AND fi.fk_statut = ".GETPOST("fichinterstatut");
			// Sort on date
			$sql.= ' ORDER BY dateo';

			dol_syslog("management/fichinterdet/calendar.php sql=".$sql, LOG_DEBUG);
			$resql=$db->query($sql);
			if ($resql)
			{
				$num = $db->num_rows($resql);
				$i=0;
				while ($i < $num)
				{
					$obj = $db->fetch_object($resql);

					// Create a new object action
					dol_include_once("/management/class/managementfichinter.class.php");
					$fichinter=new Fichinter($db);
					$fichinter->id=$obj->rowid;	
					$fichinter->ref=$obj->ref;	
					
					$fichinter->dateo=$db->jdate($obj->dateo);  
					$fichinter->datee=$db->jdate($obj->datee);
					$fichinter->type_code="FICHINTERDET";
					$fichinter->libelle=$obj->description;
					
					$fichinter->author = New User($db);
					$fichinter->usertodo = New User($db);
					$fichinter->userdone = New User($db);
					$fichinter->author->id=$obj->fk_user_author;
					$fichinter->usertodo->id=$obj->fk_user_action;
					$fichinter->userdone->id=$obj->fk_user_done;
					
					$fichinter->userassigned=array(fk_user_author);
					
					$fichinter->societe= New Societe($db);
					$fichinter->contact= New Contact($db);
					$fichinter->societe->id=$obj->fk_soc;
					$fichinter->contact->id=$obj->fk_contact;

					$fichinter->fulldayevent=$obj->fulldayevent;
					$fichinter->fk_statut=$obj->fk_statut;
					$fichinter->fk_projet=$obj->fk_projet;
			
					// Defined date_start_in_calendar and date_end_in_calendar property
					// They are date start and end of action but modified to not be outside calendar view.
					$fichinter->date_start_in_calendar=$fichinter->dateo;
					if ($fichinter->datee != '' && $fichinter->datee >= $fichinter->dateo) 
						$fichinter->date_end_in_calendar=$fichinter->datee;
					else
						$fichinter->date_end_in_calendar=$fichinter->dateo;
		
					// Define ponctual property
					if ($fichinter->date_start_in_calendar == $fichinter->date_end_in_calendar)
					{
						$fichinter->ponctuel=1;
					}
			
					$fichinter->type_code='AC_OTH_AUTO';
					$fichinter->icalname='fichinterdet';
					$fichinter->icalcolor='#B3B3B3';
			
					// Check values
					if ($fichinter->date_end_in_calendar < $firstdaytoshow ||
					$fichinter->date_start_in_calendar > $lastdaytoshow)
					{
						// This record is out of visible range
					}
					else
					{
						if ($fichinter->date_start_in_calendar < $firstdaytoshow)
							$fichinter->date_start_in_calendar=$firstdaytoshow;
						if ($fichinter->date_end_in_calendar > $lastdaytoshow)
							$fichinter->date_end_in_calendar=$lastdaytoshow;
			
						// Add an entry in actionarray for each day
						$daycursor=$fichinter->date_start_in_calendar;
						$annee = date('Y',$daycursor);
						$mois = date('m',$daycursor);
						$jour = date('d',$daycursor);
			
						// Loop on each day covered by action to prepare an index to show on calendar
						$loop=true; $j=0;
						$daykey=dol_mktime(0,0,0,$mois,$jour,$annee);
						do
						{
							//print 'daykey='.$daykey.' '.$fichinter->datep.' '.$fichinter->datef.'<br>';
							$FichInterArray[$daykey][]=$fichinter;
							$j++;
			
							$daykey+=60*60*24;
							if ($daykey > $fichinter->date_end_in_calendar) $loop=false;
						}
						while ($loop);
						$this->results['eventarray'] = $this->results['eventarray'] + $FichInterArray;
					}
					$i++;
				}
			}


		}
}
		return 0;
	}


// à virer???
	function searchAgendaFrom($parameters, $object, $action) 
	{
if (false)
{
		global $conf, $langs, $db;
		$langs->load('management@management');
		$langs->load('interventions');
		$langs->load('projects');
		require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
		$form = new Form($db);
		if ($conf->ficheinter->enabled)
		{
			print '<tr class="check_fichinter" ><td>'.$langs->trans("FichinterStatut").' &nbsp; </td><td>';
			$arrayfichinterstatut = array (
				'0'=>$langs->trans('Draft'),
				'1'=>$langs->trans('Validated'),
				'3'=>$langs->trans('StatusInterPartialClosed'),
				'4'=>$langs->trans('StatusInterClosed'),
				'2'=>$langs->trans('StatusInterInvoiced'),
				'5'=>$langs->trans('StatusInterClosedNotToBill'),
			);
			print $form->selectarray("fichinterstatut", $arrayfichinterstatut, GETPOST("fichinterstatut"), 1);
			print '<td></tr>';
		}

		if ($conf->projet->enabled)
		{
			print '<tr class="check_task" ><td>'.$langs->trans("TaskStatut").' &nbsp; </td>';
			print '</tr>';

		}
		print '<input type=hidden name=display value="'.GETPOST("display").'">';
}
		return 0;
	}


// à virer?
	function addCalendarChoice($parameters, $object, $action) 
	{
if (false)
{
		global $db, $langs, $conf;

		$langs->load('management@management');

		if ($conf->ficheinter->enabled)
		{
			print '<div clear class="nowrap float"><input type="checkbox" id="check_fichinter" name="check_fichinter"> '.$langs->trans("AgendaShowFichinter").' &nbsp; </div>';
			print '<div clear class="nowrap float"><input type="checkbox" id="check_fichinterdet" name="check_fichinterdet"> '.$langs->trans("AgendaShowFichinterDet").' &nbsp; </div>';
		}
		if ($conf->projet->enabled)
		{
			print '<div class="nowrap float"><input type="checkbox" id="check_task" name="check_task"> '.$langs->trans("AgendaShowTask").' &nbsp; </div>';
			//print '<div class="nowrap float"><input type="checkbox" id="check_taskpassed" name="check_taskpassed"> '.$langs->trans("AgendaShowTaskPassied").' &nbsp; </div>';
		}
		print '<script type="text/javascript">' . "\n";
		print 'jQuery(document).ready(function () {' . "\n";
		print 'jQuery("#check_fichinter").click(function() { jQuery(".family_ext'.md5('fichinter').'").toggle(); });' . "\n";
		print 'jQuery("#check_fichinterdet").click(function() { jQuery(".family_ext'.md5('fichinterdet').'").toggle(); });' . "\n";

		print 'jQuery("#check_task").click(function() { jQuery(".check_task").toggle(); });' . "\n";
		print 'jQuery("#check_taskpassed").click(function() { jQuery(".check_taskpassed").toggle(); });' . "\n";

		// si on arrive par le lien, on active et on désactive le lien par défaut
		if (GETPOST("display") == "fichinter" || GETPOST("display") == "fichinterdet" || GETPOST('fichinterstatut') >=0)
		{
			if (GETPOST("display") == "fichinter")
				print 'jQuery("#check_fichinter").attr("checked", true);'. "\n";
			if (GETPOST("display") == "fichinterdet")
				print 'jQuery("#check_fichinterdet").attr("checked", true);'. "\n";

		}
		else
		{
			// on désactive ce que l'on ne souhaite pas afficher
			if (GETPOST("display") != "fichinter")
				print 'jQuery(".family_ext'.md5('fichinter').'").toggle(); ' . "\n";
			if (GETPOST("display") != "fichinterdet")
				print 'jQuery(".family_ext'.md5('fichinterdet').'").toggle(); ' . "\n";
				
		}
		if (GETPOST("display") == "task")
		{
			print 'jQuery("#check_task").attr("checked", true);'. "\n";
			print 'jQuery("#check_taskpassed").attr("checked", true);'. "\n";
		}
		else
		{
			print 'jQuery(".check_task").toggle();' . "\n";
			print 'jQuery(".check_taskpassed").toggle();' . "\n";
		}
		// on rend désactivable les actions
		print 'jQuery("#check_mytasks").removeAttr("disabled");'. "\n";
		print 'jQuery("#check_mytasks").click(function() { jQuery(".check_mytasks").toggle(); });' . "\n";
		// si on a affiché un calendrier on les désactive par défaut
		if (GETPOST("display"))
			print 'jQuery("#check_mytasks").removeAttr("checked");' . "\n";
		print '});' . "\n";
		print '</script>' . "\n";
}
		return 0;
	}


	function addMoreActionsButtons($parameters, $object, $action) 
	{
		global $conf, $langs, $db;
		global $user;

		if($object->element  == 'fichinter' )
		{
			if ($object->statut == 0 && $user->rights->ficheinter->creer && (count($object->lines) > 0 ))
			{
				print '<div class="inline-block divButAction"><a class="butAction" href="'.dol_buildpath('/management/fichinter/',1).'fiche-rec.php?id='.$object->id.'&action=create"';
				print '>'.$langs->trans("ChangeIntoRepeatableInterventional").'</a></div>';
			}
		}
		

		if($object->element  == 'propal' && $conf->global->MANAGEMENT_GENERATE_PROJECT_FROM_PROPOSAL)
		{
			// que pour propales acceptée
			if ($object->statut == 2 && $user->rights->projet->creer && (count($object->lines) > 0 ))
			{
				print '<div class="inline-block divButAction"><a class="butAction" ';
				print 'href="'.$_SERVER['PHP_SELF'] . '?id=' . $object->id . '&amp;action=createproject"';
				print '>'.$langs->trans("CreateProjectFromProposal").'</a></div>';
			}
		}
		
	}

// ca ne fonctionne pas à enlever
	function doActions($parameters, $object, $action) 
	{
		global $conf, $langs, $db, $user;

		if($object->element  == 'fichinter' )
		{
			$object->statuts[4]='StatusInterPartialClosed';
			$object->statuts[3]='StatusInterClosed';

			$object->statuts_short[4]='StatusInterPartialClosed';
			$object->statuts_short[3]='StatusInterClosed';

			$object->statuts_logo[4]='statut3';
			$object->statuts_logo[3]='statut4';
			$object->statuts_logo[2]='statut6';
		}


if ($conf->global->MANAGEMENT_GENERATE_PROJECT_FROM_PROPOSAL)
{		
		if ($object->element == 'propal' && $action=="createproject")
		{
			require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
			require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
			$projectstatic = new Project($db);

			$defaultref='';
			$modele = empty($conf->global->PROJECT_ADDON)?'mod_project_simple':$conf->global->PROJECT_ADDON;
			
			// Search template files
			$file=''; $classname=''; $filefound=0;
			$dirmodels=array_merge(array('/'),(array) $conf->modules_parts['models']);
			foreach($dirmodels as $reldir)
			{
				$file=dol_buildpath($reldir."core/modules/project/".$modele.'.php',0);
				if (file_exists($file))
				{
					$filefound=1;
					$classname = $modele;
					break;
				}
			}
			
			if ($filefound)
			{
			    $result=dol_include_once($reldir."core/modules/project/".$modele.'.php');
			    $modProject = new $classname;
			
			    $defaultref = $modProject->getNextValue($object->thirdparty, $projectstatic);
			}
			
			if ($object->fk_project == "" )
			{
				$projectstatic->ref             = $defaultref;
				$projectstatic->title           = $object->ref ; // Do not use 'alpha' here, we want field as it is
				$projectstatic->socid           = $object->socid;
				$projectstatic->description		= $object->ref_client; // Do not use 'alpha' here, we want field as it is
				$projectstatic->public			= 0;
				$projectstatic->datec=dol_now();
				$projectstatic->date_start		=$object->datep;
				$projectstatic->date_end		=$object->date_livraison;
				$projectstatic->statut			= $conf->global->MANAGEMENT_DEFAULT_STATUT_NEW_PROJET;
				$projectstatic->note_public		= $object->note_public; // Do not use 'alpha' here, we want field as it is
				$projectstatic->note_private	= $object->note_private; // Do not use 'alpha' here, we want field as it is

				$projectid= $projectstatic->create($user);
				if ($projectid > 0)
				{
					// on affecte le projet à la propal
					$object->setProject($projectid);
				}
				else
		        {
		            dol_print_error($db);
		            exit;
		        }

				// récup des contacts au niveau de la propal? on propage comment au tache???
			}
			else
				$projectid = $object->fk_project;

			// on boucle sur la création des taches
			foreach ($object->lines as $line)
			{
				// si c'est du service
				if ($line->product_type == 1)
				{
					$task = new Task($db);
		
					$defaultref='';
					$obj = empty($conf->global->PROJECT_TASK_ADDON)?'mod_task_simple':$conf->global->PROJECT_TASK_ADDON;
					if (! empty($conf->global->PROJECT_TASK_ADDON) && is_readable(DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.".php"))
					{
						require_once DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.'.php';
						$modTask = new $obj;
						$defaultref = $modTask->getNextValue($object->thirdparty, null);
						// on remplace le TK par la ref du service
						$defaultref = str_replace( "TK", $line->product_ref, $defaultref);
					}

					$task->fk_project = $projectid;
					$task->ref = $defaultref;
					$task->label = ($line->label ? $line->label : $line->product_label);
					$task->description = $line->desc;

					// détermination de la quantité d'heure
					$task->planned_workload = $line->qty*3600;
					$task->fk_task_parent 	= 0;
					$task->date_c 			= dol_now();
					$task->date_start 		= $line->date_start;
					$task->date_end 		= $line->date_end;
					$task->progress 		= 0;

					$taskid = $task->create($user);
					// si cela merdouille pour les tests
					if ($taskid <0 )
						var_dump($task->error);
				}
			}

			if (! $error)
			{
				header("Location: ".dol_buildpath("/projet/",1)."tasks.php?id=".$projectid);
				exit;
			}
		}}
	}
}