<?php
/* Copyright (C) 2014-2016	Charlie BENKE	<charlie@patas-monkey.com>
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
 *	  \file	   management/class/managementprojet.class.php
 *	  \ingroup	management
 *	  \brief	pour gérer la transfert en facturation du projet
 */

require_once DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php";
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';


/**
 *	  \brief	  Class to manage tasks
 *	\remarks	Initialy built by build_class_from_table on 2008-09-10 12:41
 */
class Managementproject extends Project
{
	public $element='management_managementproject';
	var $fk_product;
	var $fk_project;


	/**
	 *  Constructor
	 *
	 *  @param	  DoliDB		$db	  Database handler
	 */
	function __construct($db)
	{
		$this->db = $db;
		$this->statuts_short = array(0 => 'Draft', 1 => 'Opened', 2 => 'Closed');
		$this->statuts_long = array(0 => 'Draft', 1 => 'Opened', 2 => 'Closed');
	}

	function addbilledline($lineBilledArray)
	{
		global $user;
		// on boucle sur les lignes
		foreach ($lineBilledArray as $lineBilled)
		{
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."projet_task_billed";
			$sql.= " (fk_task, task_date, task_duration_billed, fk_user)" ;
			$sql.= " VALUES ";
			$sql.= " (".$lineBilled->id.", now(), ".($lineBilled->tobill*3600).", ".$user->id.")" ;
			
			$resql = $this->db->query($sql);
			print $sql."<br>";
		}
		//Ajoute les lignes dans la table
	}
	

	function fetch_lines()
	{

		// on récupère les taches terminées du projet
		$this->lines=array();
		// pour faire le lien entre la facture et le projet
		$this->fk_project=$this->id;
		// pour la mise à jour du trigger
		$this->origine_id=$this->id;

		global $langs;
		global $object; // pour savoir si on a déjà crée la facture ou pas
		
		// on ne récupère que les lignes à facturer qui ne sont pas encore associé à une facture
		$sql = "SELECT pt.average_thm, ptb.task_duration_billed, pt.fk_product, pt.dateo, pt.datee, ";
		$sql.= " pt.ref, pt.label, pt.duration_effective, pt.average_thm, pt.planned_workload";
		$sql.= " FROM ".MAIN_DB_PREFIX."projet_task as pt, ".MAIN_DB_PREFIX."projet as p," ;
		$sql.= " ".MAIN_DB_PREFIX."projet_task_billed AS ptb";
		$sql.= " WHERE pt.rowid = ptb.fk_task ";
		$sql.= " AND pt.fk_projet=p.rowid";
		if ($object->id)
			$sql.= " AND ptb.fk_facture = ".$object->id;
		else
			$sql.= " AND ptb.fk_facture = 0";

		$sql.= " AND p.rowid = ".$this->id;

		dol_syslog(get_class($this)."::fetch_lines sql=".$sql, LOG_DEBUG);
		$resql=$this->db->query($sql);

		if ($resql)
		{
			$nump = $this->db->num_rows($resql);
			
			if ($nump)
			{
				$i = 0;
				while ($i < $nump)
				{
					$obj = $this->db->fetch_object($resql);

					$line = new ManagementProjectLigne($this->db);
					$line->average_thm	= $obj->average_thm;
					$line->fk_product	= $obj->fk_product;
					if ($obj->fk_product)
					{
						$productstatic = new Product($this->db);
						$productstatic->fetch($obj->fk_product);
						$line->ref				= $productstatic->ref;
						$line->fk_product_type	= $productstatic->type;
						$line->product_label	= $productstatic->label;
						$line->tva_tx 			= $productstatic->tva_tx;
						$line->subprice			= $productstatic->price;
					}
					$line->date_start	= $this->db->jdate($obj->dateo);
					$line->date_end		= $this->db->jdate($obj->datee);

					$line->desc			= $obj->ref. " - ".$obj->label;
					$line->qty			= round($obj->task_duration_billed/3600,2);
					$line->pa_ht 		= (($obj->duration_effective/3600)*$obj->average_thm)/($obj->task_duration_billed/3600);

					$this->lines[] = $line;

					$i++;
				}
			}
			$this->db->free($resql);
		}
		else
		{
			$this->error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::fetch_line ".$this->error, LOG_ERR);

			return -1;
		}
		return $this->lines;
	}
}

class ManagementProjectLigne // extends CommonObject
{
	var $db;
	var $error;
	var $average_thm;
	var $fk_product;
	var $ref;
	var $fk_product_type;
	var $product_label;
	var $tva_tx;
	var $subprice;
	var $date_start;
	var $date_end;
	var $desc;
	var $qty;
	var $pa_ht;

	/**
	 *	Constructor
	 *
	 *	@param	DoliDB	$db		Database handler
	 */
	function __construct($db)
	{
		$this->db = $db;
	}	
	
}
?>
