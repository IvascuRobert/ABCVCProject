<?php
/* Copyright (C) 2012-2016  Charlie BENKE		<charlie@patas-monkey.com>
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
 *	\file       /management/class/managementcontrat.class.php
 *	\ingroup    contrat
 *	\brief      File of class to manage contracts
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
require_once(DOL_DOCUMENT_ROOT ."/margin/lib/margins.lib.php");

/**
 *	Class to manage contracts
 */
class Managementcontrat extends Contrat
{
	
	/**
	 *      Load intervention linked to contract
	 *
	 *      @param	User	$user           Objet type
	 *      @return array 	$elements		array of linked elements
	 */
	function get_element_list($datedeb, $datefin)
	{
		$elements = array();
		
		$sql = '';
		$sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "fichinter WHERE fk_contrat=" . $this->id;
		if ($datedeb)
			$sql.= " AND dateo > '".$datedeb."'";
		if ($datefin)
			$sql.= " AND dateo < '".$datefin."'";
		if (! $sql) return -1;
		

		dol_syslog(get_class($this)."::get_element_list sql=" . $sql);
		$result = $this->db->query($sql);
		if ($result)
		{
			$nump = $this->db->num_rows($result);
			if ($nump)
			{
				$i = 0;
				while ($i < $nump)
				{
					$obj = $this->db->fetch_object($result);
					$elements[$i] = $obj->rowid;
					$i++;
				}
				$this->db->free($result);
				/* Return array */
				return $elements;
			}
		}
		else
		{
			dol_print_error($this->db);
		}
	}
	
	function addterm($datesubbegin, $datesubend, $cotisation, $note)
	{
		$sql="INSERT ". MAIN_DB_PREFIX . "contrat_term (datec, fk_contrat, datedeb, datefin, note) values ";
		$sql.= "( now()" ;
		$sql.= ", ".$this->id;
		$sql.= ", ".$this->db->idate($datesubbegin);
		$sql.= ", ".$this->db->idate($datesubend);
		$sql.= ", '".$note."'";
		$sql.=")";

		$resql = $this->db->query($sql);
		if (! $resql)
		{
			$this->error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::INSERT ".$this->error, LOG_ERR);
			return -1;
		}

		// Appel des triggers
		include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
		$interface=new Interfaces($this->db);
		$result=$interface->run_triggers('CONTRACT_ADD_TERM',$this,$user,$langs,$conf);
		if ($result < 0) { $error++; $this->errors=$interface->errors; }
		// Fin appel triggers

		return 1;
	}
	
	function updateTerm($rowid, $datesubbegin, $datesubend, $cotisation, $note)
	{
		$sql = "UPDATE ". MAIN_DB_PREFIX . "contrat_term ";
		$sql.= " set datedeb= ".$this->db->idate($datesubbegin);
		$sql.= " , datefin=".$this->db->idate($datesubend);
		$sql.= " , note='".$note."'";
		$sql.= " where rowid=".$rowid;

		$resql = $this->db->query($sql);
		if (! $resql)
		{
			$this->error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::UPDATE ".$this->error, LOG_ERR);
			return -1;
		}

		return 1;
	}
	
	//return nb of terms associated with the contract
	function nb_Term($rowid)
	{
		$sql = "SELECT count(*) as nb";
		$sql.= " FROM ".MAIN_DB_PREFIX."contrat_term WHERE fk_contrat=".$rowid;
		dol_syslog(get_class($this)."::get_element_list sql=" . $sql);
		$result = $this->db->query($sql);
		if ($result)
		{
			$obj = $this->db->fetch_object($result);
			return $obj->nb;
		}
		return "";
	}

	//return nb of fichinter associated with the contract
	function nb_Fichinter($rowid)
	{
		$sql = "SELECT count(*) as nb";
		$sql.= " FROM ".MAIN_DB_PREFIX."fichinter WHERE fk_contrat=".$rowid;
		dol_syslog(get_class($this)."::get_element_list sql=" . $sql);
		$result = $this->db->query($sql);
		if ($result)
		{
			$obj = $this->db->fetch_object($result);
			return $obj->nb;
		}
		return "";
	}

	// return an array of terms of contract
	function get_terms_list()
	{
		$terms = array();

		$sql = "SELECT rowid, datedeb, datefin, note, fk_status";
		$sql.= " FROM ".MAIN_DB_PREFIX."contrat_term WHERE fk_contrat=".$this->id;
		$sql.= " order by datedeb";

		dol_syslog(get_class($this)."::get_element_list sql=" . $sql);
		$result = $this->db->query($sql);
		if ($result)
		{
			$nump = $this->db->num_rows($result);
			if ($nump)
			{
				$i = 0;
				while ($i < $nump)
				{
					$obj = $this->db->fetch_object($result);
					$term= array();
					$term['rowid']=$obj->rowid;
					$term['datedeb']=$obj->datedeb;
					$term['dateend']=$obj->datefin;
					$term['note']=$obj->note;
					$term['fk_status']=$obj->fk_status;
					$terms[$i] = $term;
					
					$i++;
				}
				$this->db->free($result);
			}
		}
		else
		{
			dol_print_error($this->db);
		}
		/* Return array */
		return $terms;
	}

	function deleteTerm ($ligneid)
	{
		$sql = " delete from ".MAIN_DB_PREFIX."contrat_term ";
		$sql.= " WHERE fk_contrat=".$this->id;
		$sql.= " and rowid=".$ligneid;

		$result = $this->db->query($sql);
	}

	function validateTerm ($ligneid)
	{
		$sql = " UPDATE ".MAIN_DB_PREFIX."contrat_term ";
		$sql.= " SET fk_status=1";
		$sql.= " WHERE fk_contrat=".$this->id;
		$sql.= " and rowid=".$ligneid;
		$result = $this->db->query($sql);
	}

	function closeTerm ($ligneid)
	{
		// cloture du term
		$sql = " UPDATE ".MAIN_DB_PREFIX."contrat_term ";
		$sql.= " SET fk_status=2";
		$sql.= " WHERE fk_contrat=".$this->id;
		$sql.= " and rowid=".$ligneid;
		$result = $this->db->query($sql);
	}
}
?>