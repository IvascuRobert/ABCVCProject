<?php
/* Copyright (C) 2005-2011 	Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2011 	Regis Houssin       <regis.houssin@capnetworks.com>
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
 *  \file       htdocs/management/core/triggers/interface_90_modManagement_Taskstatut.class.php
 *  \ingroup    core
 *  \brief      Fichier de demo de personalisation des actions du workflow
 *  \remarks    Son propre fichier d'actions peut etre cree par recopie de celui-ci:
 *              - Le nom du fichier doit etre: interface_99_modMymodule_Mytrigger.class.php
 *				                           ou: interface_99_all_Mytrigger.class.php
 *              - Le fichier doit rester stocke dans core/triggers
 *              - Le nom de la classe doit etre InterfaceMytrigger
 *              - Le nom de la propriete name doit etre Mytrigger
 */


/**
 *  Class of triggers for management module
 */
class InterfaceTaskstatut
{
	var $db;

	/**
	 *   Constructor
	 *
	 *   @param		DoliDB		$db      Database handler
	 */
	function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i','',get_class($this));
		$this->family = "dolibarr";
		$this->description = "Triggers of this module are empty functions. They have no effect. They are provided for tutorial purpose only.";
		$this->version = '3.9+2.3.0';            // 'development', 'experimental', 'dolibarr' or version
		$this->picto = 'technic';
	}

	/**
	 *   Return name of trigger file
	 *
	 *   @return     string      Name of trigger file
	 */
	function getName()
	{
		return $this->name;
	}

	/**
	 *   Return description of trigger file
	 *
	 *   @return     string      Description of trigger file
	 */
	function getDesc()
	{
		return $this->description;
	}

	/**
	 *   Return version of trigger file
	 *
	 *   @return     string      Version of trigger file
	 */
	function getVersion()
	{
		global $langs;
		$langs->load("admin");

		if ($this->version == 'development') return $langs->trans("Development");
		elseif ($this->version == 'experimental') return $langs->trans("Experimental");
		elseif ($this->version == 'dolibarr') return DOL_VERSION;
		elseif ($this->version) return $this->version;
		else return $langs->trans("Unknown");
	}

	/**
	 *	Function called when a Dolibarr business event is done.
	 *	All functions "run_trigger" are triggered if file is inside directory htdocs/core/triggers
	 *
	 *	@param	string		$action		Event action code
	 *	@param  Object		$object     Object
	 *	@param  User		$user       Object user
	 *	@param  Translate	$langs      Object langs
	 *	@param  conf		$conf       Object conf
	 *	@return int         			<0 if KO, 0 if no triggered ran, >0 if OK
	 */
	function runTrigger($action,$object,$user,$langs,$conf)
    {
		// Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action
		// Projects
		if (	 $action == 'BILL_CREATE')
		{
			// pour remettre le lien vers la fiche inter dans le bon sens
			dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
			
			$sql = "UPDATE ".MAIN_DB_PREFIX."element_element";
			$sql.= " SET sourcetype='fichinter'";
			$sql.= " WHERE sourcetype='management_managementfichinter'";
			$this->db->query($sql);

			// pour le contrat il y a plus de chose à faire
			$sql = "UPDATE ".MAIN_DB_PREFIX."element_element";
			$sql.= " SET sourcetype='contrat'";
			$sql.= ", fk_source=".$object->origin_id;
			$sql.= " WHERE sourcetype='management_managementcontratterm'";
			$sql.= " AND fk_target=".$object->id;
			$this->db->query($sql);

			// utile?
			$sql = "UPDATE ".MAIN_DB_PREFIX."element_element";
			$sql.= " SET sourcetype='task'";
			$sql.= " WHERE sourcetype='management_managementtask'";
			$this->db->query($sql);

			// pour gérer la mise à jour de la tache (passe à transmise)
			if($object->origin=='management_managementtask')
			{
				$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task";
				$sql.= " SET fk_statut=4";
				$sql.= " WHERE rowid=".$object->origin_id;
				//print $sql;
				$this->db->query($sql);
			}

			// pour gérer la mise à jour des tache du projet (passe à transmise et temps saisie)
			if($object->origin == 'management_managementproject')
			{
				// mise à jour des taches terminé à transmise
				$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task as pt";
				$sql.= " SET pt.fk_statut=4";
				$sql.= " WHERE pt.fk_statut=3";
				$sql.= " AND pt.fk_projet=".$object->origin_id;
				//print $sql;
				$this->db->query($sql);

				// mise à jour des temps des taches transmis pour avoir le numéro de facture
				$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task as pt, ".MAIN_DB_PREFIX."projet_task_billed as ptb";
				$sql.= " SET ptb.fk_facture=".$object->id;
				$sql.= " WHERE pt.rowid=ptb.fk_task";
				$sql.= " AND ptb.fk_facture = 0";
				$sql.= " AND pt.fk_projet=".$object->origin_id;
				//print $sql;
				$this->db->query($sql);

			}

			// pour gérer la mise à jour de l'intervention (passe à transmise)
			if($object->origin == 'management_managementcontratterm')
			{
				$sql = "UPDATE ".MAIN_DB_PREFIX."fichinter as fi";
				$sql.= " SET fi.fk_statut=2";	// facturé
				$sql.= " WHERE fi.fk_statut=4";	// cloturé
				$sql.= " AND fi.fk_contrat=".$object->origin_id;
				$this->db->query($sql);
			}
			
		}
		elseif ( $action == 'TASK_CREATE' || 
				 $action == 'TASK_TIMESPENT_CREATE' || 
				 $action == 'TASK_TIMESPENT_MODIFY' || 
				 $action == 'TASK_TIMESPENT_DELETE' )
		{
			// on gère le statut de la tache en fonction de l'avancement déclaré
			if ($object->progress == 0 && $object->planned_workload == 0 )
				$fk_statut=0;
			elseif ($object->progress == 0 && $object->planned_workload != 0 )
				$fk_statut=1;
			elseif ($object->progress == 100 )
				$fk_statut=3;
			else
				$fk_statut=2;

			$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task SET";
			$sql.= " fk_statut=".$fk_statut;
			$sql.= " WHERE rowid=".$object->id;
			$resql = $this->db->query($sql);

			dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);		
		}
		elseif ( $action == 'TASK_MODIFY')
		{
			// on gère le statut de la tache en fonction de l'avancement déclaré
			// uniquement si la tache n'a pas été déjà transféré
			if ($object->fk_statut !=4)
			{
				if ($object->progress == 0 && $object->planned_workload == 0 )
					$fk_statut=0;
				elseif ($object->progress == 0 && $object->planned_workload != 0 )
					$fk_statut=1;
				elseif ($object->progress == 100 )
					$fk_statut=3;
				else
					$fk_statut=2;

				$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task SET";
				$sql.= " fk_statut=".$fk_statut;
				$sql.= " WHERE rowid=".$object->id;
				$resql = $this->db->query($sql);
			}	
			dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
			
			if ($conf->global->MANAGEMENT_SENDMAIL_TASKMODIFY)
			{
				// Get destination user of mail
				$userid = GETPOST('userid', 'int');
				$sql_email = "SELECT email FROM ".MAIN_DB_PREFIX."user as u, ".MAIN_DB_PREFIX."projet_task as pt";
				$sql_email .= ", ".MAIN_DB_PREFIX."element_contact as ec, ".MAIN_DB_PREFIX."c_type_contact as ctc ";
				$sql_email .= " WHERE pt.rowid = ". $object->id;
				$sql_email .= " AND pt.rowid = ec.element_id";
				$sql_email .= " AND ec.fk_c_type_contact = ctc.rowid";
				$sql_email .= " AND ctc.source = 'internal'";
				$sql_email .= " AND ctc.element = 'project_task'";
				$sql_email .= " AND ec.fk_socpeople = u.rowid";
				
				$res_email = $this->db->query($sql_email);
				if ($res_email)
				{
					$num=$this->db->num_rows($res_email);
					while ($obj=$this->db->fetch_array($res_email)) 
					{
						if ($obj[0])
							$email.= $obj[0].","; 
					}
				}
	
				//Get information of project and task
				$sql_projecte = "SELECT p.ref as pref, p.title as ptitle, pt.ref as ptref, pt.label FROM ".MAIN_DB_PREFIX."projet p , ".MAIN_DB_PREFIX."projet_task pt";
				$sql_projecte .= " WHERE pt.rowid = ".$object->id." AND pt.fk_projet = p.rowid";
				$res_projecte = $this->db->query($sql_projecte);
				if ( $res_projecte ) 
				{
					$num=$this->db->num_rows($res_projecte);
					while ($obj=$this->db->fetch_array($res_projecte)) {
						$proj = $obj[0]." - ".$obj[1]; 
						$task = $obj[2]." - ".$obj[3]; 
					}
				}
	
				$file = "";
	
				$subject = $langs->trans("subject");
				$to = $bcc = $email;
				$from = ''.$conf->global->MAIN_INFO_SOCIETE_MAIL.' <'.$conf->global->MAIN_INFO_SOCIETE_MAIL.'>';
				$message = $langs->trans("textmail1").' <strong>'.$task .'</strong> '.$langs->trans("textmail2").' <strong>'.$proj.'</strong>';
	
				include_once(DOL_DOCUMENT_ROOT."/core/class/CMailFile.class.php");
				$mailfile = new CMailFile($subject,$to,$from,$message,array($file),'','','', '', 0, -1,'','');
				//var_dump($mailfile);
				$mailfile->sendfile();
				setEventMessage($langs->trans("missatgenotif").' '.$email, 'mesgs');
			}
		}
		elseif ( $action == 'PROJECT_CLOSE')
		{
			if ($conf->global->MANAGEMENT_SENDMAIL_PROJECTCLOSE)
			{
				if ($conf->global->MANAGEMENT_SENDMAIL_PROJECTCLOSE_INTERNAL)
				{
					$sql_email = "SELECT email FROM ".MAIN_DB_PREFIX."user as u, ".MAIN_DB_PREFIX."projet as p";
					$sql_email .= ", ".MAIN_DB_PREFIX."element_contact as ec, ".MAIN_DB_PREFIX."c_type_contact as ctc ";
					$sql_email .= " WHERE p.rowid = ". $object->id;
					$sql_email .= " AND p.rowid = ec.element_id";
					$sql_email .= " AND ec.fk_c_type_contact = ctc.rowid";
					$sql_email .= " AND ctc.source = 'internal'";
					$sql_email .= " AND ctc.element = 'project'";
					$sql_email .= " AND ec.fk_socpeople = u.rowid";
					
					$res_email = $this->db->query($sql_email);
					if ($res_email)
					{
						$num=$this->db->num_rows($res_email);
						while ($obj=$this->db->fetch_array($res_email)) 
						{
							if ($obj[0])
								$email.= $obj[0].","; 
						}
					}
				}
				if ($conf->global->MANAGEMENT_SENDMAIL_PROJECTCLOSE_EXTERNAL)
				{}
				
				$sql_projecte = "SELECT p.ref as pref, p.title as ptitle FROM ".MAIN_DB_PREFIX."projet p";
				$sql_projecte .= " WHERE p.rowid = ".$object->id;
				$res_projecte = $this->db->query($sql_projecte);
				if ( $res_projecte ) 
				{
					$obj=$this->db->fetch_array($res_projecte);
					$proj = $obj[0]." - ".$obj[1]; 
				}
				
				$file = "";
	
	
				$subject = $langs->trans("ProjectClose");
				$to = $email;
				$from = ''.$conf->global->MAIN_INFO_SOCIETE_MAIL.' <'.$conf->global->MAIN_INFO_SOCIETE_MAIL.'>';
				$message = $langs->trans("textmailProjectClose").' <strong>'.$proj.'</strong>';
	
				include_once(DOL_DOCUMENT_ROOT."/core/class/CMailFile.class.php");
				$mailfile = new CMailFile($subject,$to,$from,$message,array($file),'','','', '', 0, -1,'','');
				//var_dump($mailfile);
				$mailfile->sendfile();
				setEventMessage($langs->trans("missatgenotif").' '.$email, 'mesgs');
			}
		}
		
		if ($action == 'TASK_CREATE')
		{
			$defaultservicetask=$conf->global->MANAGEMENT_DEFAULTSERVICETASK;
			$defaultthmprice=$conf->global->MANAGEMENT_DEFAULTTHMPRICE;
			if ($defaultservicetask > 0 || $defaultthmprice > 0)
			{
				$sql = "UPDATE " . MAIN_DB_PREFIX . "projet_task";
				$sql.= " SET entity = entity";

				if ($defaultservicetask > 0)
					$sql.= " , fk_product = ".$defaultservicetask;
				if ($defaultthmprice > 0)
					$sql.= " , average_thm = ".$defaultthmprice;

				$sql.= " WHERE rowid = " . $object->id;
				$sql.= " AND entity = " . $conf->entity;
				$resql = $this->db->query($sql);
			}
		}
		
		return 0;
	}

}
?>
