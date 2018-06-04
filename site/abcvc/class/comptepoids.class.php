<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) <year>  <name of author>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    class/myclass.class.php
 * \ingroup mymodule
 * \brief   Example CRUD (Create/Read/Update/Delete) class.
 *
 * Put detailed description here.
 */

/** Includes */
//require_once DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php";
require_once DOL_DOCUMENT_ROOT."/societe/class/societe.class.php";
//require_once DOL_DOCUMENT_ROOT."/product/class/product.class.php";

require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';

/**
 * Put your class' description here
 */
class ComptePoids  extends CommonObject
{

    /** @var DoliDb Database handler */
	public $db;
    /** @var string Error code or message */
	public $error;
    /** @var array Several error codes or messages */
	public $errors = array();
    /** @var string Id to identify managed object */
	//public $element='myelement';
    /** @var string Name of table without prefix where object is stored */
	//public $table_element='mytable';
    /** @var int An example ID */
	
	public $rowid;

	public $ref;
	public $ref_ext;

	public $fk_soc;
	public $date_creation;
	public $type;
	public $ref_type;
	public $id_type;
	public $description;
	public $structure;

	public $mvt;

	//pour pdf génération
	public $id_client;
	public $datePDFde_sql;
	public $datePDFa_sql;
	public $datePDFde;
	public $datePDFa;


	/*
		CREATE TABLE `llx_abcvc_comptepoid` (
		  `rowid` int(11) NOT NULL AUTO_INCREMENT,
		  `ref` varchar(30) NOT NULL,
		  `ref_ext` varchar(255) DEFAULT NULL,
		  `fk_soc` int(11) NOT NULL,
		  `date_creation` datetime DEFAULT NULL,
		  `type` varchar(32) DEFAULT NULL,
		  `ref_type` varchar(32) NOT NULL,
		  `id_type` int(11) NOT NULL,
		  `description` varchar(255) DEFAULT NULL,
		  `structure` mediumtext,


	*/


	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		return 1;
	}

	/**
	 * Create object into database
	 *
	 * @param User $user User that create
	 * @param int $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int <0 if KO, Id of created object if OK
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf, $langs;
		$error = 0;

		// Clean parameters
		if (isset($this->description)) {
			$this->description = trim($this->description);
		}


		// Insert request
		$sql ="
		INSERT INTO llx_abcvc_comptepoid (
		ref,
		fk_soc,
		date_creation,
		type,
		ref_type,
		id_type,
		description,
		structure ";
		$sql.= ") VALUES (";
		$sql.= " '" . $this->ref . "',";
		$sql.= " '" . $this->fk_soc . "',";
		$sql.= " '" . $this->date_creation . "',";
		$sql.= " '" . $this->type . "',";
		$sql.= " '" . $this->ref_type . "',";
		$sql.= " '" . $this->id_type . "',";
		$sql.= " '" . $this->db->escape($this->description) . "',";
		$sql.= " '" . $this->structure . "'";
		$sql.= ")";	



		$this->db->begin();

		dol_syslog(__METHOD__ . " sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (! $resql) {
			$error ++;
			$this->errors[] = "Error " . $this->db->lasterror();
		}

		if (! $error) {
			$this->rowid = $this->db->last_insert_id(MAIN_DB_PREFIX . "abcvc_comptepoid");

				//enr matieres
				foreach ($this->mvt as $id_matiere => $qty) {
					$sql ="
					INSERT INTO llx_abcvc_comptepoid_mvt (
					id_comptepoid,
					fk_soc,
					date_mvt,
					ref_matiere,
					qty)
					VALUES (
					".$this->rowid.",
					".$this->fk_soc.",
					'".$this->date_creation."',
					".$id_matiere.",
					'".$qty."');";
					//var_dump($sql);
					$result = $this->db->query($sql);
				}

			if (! $notrigger) {
				//// Call triggers
				//include_once DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php";
				//$interface=new Interfaces($this->db);
				//$result=$interface->run_triggers('MYOBJECT_CREATE',$this,$user,$langs,$conf);
				//if ($result < 0) { $error++; $this->errors=$interface->errors; }
				//// End call triggers
			}
		}

		// Commit or rollback
		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(__METHOD__ . " " . $errmsg, LOG_ERR);
				$this->error.=($this->error ? ', ' . $errmsg : $errmsg);
			}
			$this->db->rollback();

			return -1 * $error;
		} else {

			$this->db->commit();

			return $this->rowid;
		}
	}

	/**
	 * Load object in memory from database
	 *
	 * @param int $id Id object
	 * @return int <0 if KO, >0 if OK
	 */
	public function fetch($id)	{

		global $langs;
		$sql = "SELECT";
		$sql.= " t.rowid,";

		$sql.= " t.ref,";
		$sql.= " t.ref_ext,";

		$sql.= " t.fk_soc,";
		$sql.= " t.date_creation,";
		$sql.= " t.type,";
		$sql.= " t.ref_type,";
		$sql.= " t.id_type,";
		$sql.= " t.description,";
		$sql.= " t.structure";

		$sql.= " FROM " . MAIN_DB_PREFIX . "abcvc_comptepoid as t";
		$sql.= " WHERE t.rowid = " . $id;

		dol_syslog(__METHOD__ . " sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);

				$this->rowid = $obj->rowid;

				$this->ref = $obj->ref;
				$this->ref_ext = $obj->ref_ext;

				$this->fk_soc = $obj->fk_soc;
				$this->date_creation = $obj->date_creation;
				$this->type = $obj->type;
				$this->ref_type = $obj->ref_type;
				$this->id_type = $obj->id_type;
				$this->description = $obj->description;
				$this->structure = json_decode($obj->structure);


				$mouvements = array();
				$sql = " SELECT m.*";
				$sql.= " FROM ".MAIN_DB_PREFIX."abcvc_comptepoid_mvt as m";
				$sql.= " WHERE m.id_comptepoid = ".(int)$obj->rowid;
				$resultmvt = $this->db->query($sql);
				if ($resultmvt) {
					$num_mvt = $this->db->num_rows($resultmvt);
					$j = 0;	
					while ($j < $num_mvt) {
						$objmvt = $this->db->fetch_object($resultmvt);					
						$mouvements[] = $objmvt;
						$j++;
					}
				}	
				$this->mvt = $mouvements;

			}
			$this->db->free($resql);

			return 1;
		} else {
			$this->error = "Error " . $this->db->lasterror();
			dol_syslog(__METHOD__ . " " . $this->error, LOG_ERR);

			return -1;
		}
	}

	/**
	 * Update object into database
	 *
	 * @param User $user User that modify
	 * @param int $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int <0 if KO, >0 if OK
	 */
	public function obsolete_update($user = 0, $notrigger = 0)
	{
		global $conf, $langs;
		$error = 0;

		// Clean parameters
		if (isset($this->label)) {
			$this->label = trim($this->label);
		}
		if (isset($this->description)) {
			$this->description = trim($this->description);
		}

		//structure déja json_encodé ?
		//if (isset($this->structure)) {
		//	$this->structure = json_encode($this->description);
		//}

		// Check parameters
		// Put here code to add control on parameters values
		// Update request
		$sql = "UPDATE " . MAIN_DB_PREFIX . "abcvc_concentrations SET";
		$sql.= " label=" . (isset($this->label) ? "'" . $this->db->escape($this->label) . "'" : "null") . ",";
		$sql.= " active=" . (isset($this->active) ? "'" . $this->db->escape($this->active) . "'" : "0") . ",";
		$sql.= " structure=" . (isset($this->structure) ? "'" . $this->db->escape($this->structure) . "'" : "0") . ",";
		$sql.= " description=" . (isset($this->description) ? "'" . $this->db->escape($this->description) . "'" : "null") . "";

		$sql.= " WHERE rowid=" . $this->rowid;

		$this->db->begin();

		dol_syslog(__METHOD__ . " sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (! $resql) {
			$error ++;
			$this->errors[] = "Error " . $this->db->lasterror();
		}

		if (! $error) {
			if (! $notrigger) {
				// Uncomment this and change MYOBJECT to your own tag if you
				// want this action call a trigger.
				//// Call triggers
				//include_once DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php";
				//$interface=new Interfaces($this->db);
				//$result=$interface->run_triggers('MYOBJECT_MODIFY',$this,$user,$langs,$conf);
				//if ($result < 0) { $error++; $this->errors=$interface->errors; }
				//// End call triggers
			}
		}

		//synchro concentration -> catégories
		$synchro = $this->synchroCategories('update');
		if (! $synchro) {
			$error ++;
			$this->errors[] = "Error " . $this->db->lasterror();
		}


		// Commit or rollback
		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(__METHOD__ . " " . $errmsg, LOG_ERR);
				$this->error.=($this->error ? ', ' . $errmsg : $errmsg);
			}
			$this->db->rollback();

			return -1 * $error;
		} else {

			$this->db->commit();

			return 1;
		}
	}

	/**
	 * Delete object in database
	 *
	 * @param User $user User that delete
	 * @param int $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int <0 if KO, >0 if OK
	 */
	public function delete($user, $notrigger = 0)
	{
		global $conf, $langs;
		$error = 0;

		$this->db->begin();

		if (! $error) {
			if (! $notrigger) {
				// Uncomment this and change MYOBJECT to your own tag if you
				// want this action call a trigger.
				//// Call triggers
				//include_once DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php";
				//$interface=new Interfaces($this->db);
				//$result=$interface->run_triggers('MYOBJECT_DELETE',$this,$user,$langs,$conf);
				//if ($result < 0) { $error++; $this->errors=$interface->errors; }
				//// End call triggers
			}
		}

		if (! $error) {

			$sql = "DELETE FROM " . MAIN_DB_PREFIX . "abcvc_comptepoid_mvt";
			$sql.= " WHERE id_comptepoid=" . $this->rowid;

			dol_syslog(__METHOD__ . " sql=" . $sql);
			$resql = $this->db->query($sql);
			if (! $resql) {
				$error ++;
				$this->errors[] = "Error " . $this->db->lasterror();
			}

			$sql = "DELETE FROM " . MAIN_DB_PREFIX . "abcvc_comptepoid";
			$sql.= " WHERE rowid=" . $this->rowid;

			dol_syslog(__METHOD__ . " sql=" . $sql);
			$resql = $this->db->query($sql);
			if (! $resql) {
				$error ++;
				$this->errors[] = "Error " . $this->db->lasterror();
			}
		}



		// Commit or rollback
		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(__METHOD__ . " " . $errmsg, LOG_ERR);
				$this->error.=($this->error ? ', ' . $errmsg : $errmsg);
			}
			$this->db->rollback();

			return -1 * $error;
		} else {

			$this->db->commit();

			return 1;
		}
	}












































	/**
	 * recup mouvements poids full + filters
	 *
	 * @return array mvts
	 */
	public function fetchAll($id_client=0, $filters=array()) {

		$search_nom = @$filters['search_nom'];
		$search_datede = @$filters['search_datede'];
		$search_datea = @$filters['search_datea'];

		$search_order = @$filters['search_order'];


		if($search_datede!=''){
			$tmp_date = explode('/',$search_datede);
			$search_datede = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' 00:00:00';
		}
		if($search_datea!=''){
			$tmp_date = explode('/',$search_datea);
			$search_datea = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' 23:59:59';
		}		
		//var_dump($filters);

		$sql = " SELECT c.*, s.nom as nom_client, s.code_client";
		$sql.= " FROM ".MAIN_DB_PREFIX."abcvc_comptepoid as c";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s on(s.rowid = c.fk_soc)";
		$sql.= " WHERE 1=1";


		if($id_client!=0){
			$sql.= " AND s.rowid = ".(int)$id_client;
		}

		if($search_nom!=''){
			$sql .= " AND (c.ref_type LIKE '%".$search_nom."%')";
		}
		
		if($search_datede!=''){
			$sql .= " AND (c.date_creation >= '".$search_datede."')";
		}
		if($search_datea!=''){
			$sql .= " AND (c.date_creation <= '".$search_datea."')";
		}

		if( empty($search_order) ){
			$sql .= " ORDER BY c.date_creation DESC";
		} else {
			$sql .= " ORDER BY c.date_creation ASC";
		}	

		//var_dump($sql);

		$result = $this->db->query($sql);

		$comptes_poids =  array();
		if ($result) {
			
			$nbtotalofrecords = $this->db->num_rows($result);

			if($filters['limit']!=0) {
				$sql.= $this->db->plimit($filters['limit']+1, $filters['offset']);
			}	
			$result = $this->db->query($sql);
			$num = $this->db->num_rows($result);

			$i = 0;	

			while ($i < $num) {
				$objp = $this->db->fetch_object($result);
				
				$mouvements = array();

				$sql = " SELECT m.*";
				$sql.= " FROM ".MAIN_DB_PREFIX."abcvc_comptepoid_mvt as m";
				$sql.= " WHERE m.id_comptepoid = ".(int)$objp->rowid;
				$resultmvt = $this->db->query($sql);
				if ($resultmvt) {
					$num_mvt = $this->db->num_rows($resultmvt);
					$j = 0;	
					while ($j < $num_mvt) {
						$objmvt = $this->db->fetch_object($resultmvt);					
						$mouvements[] = $objmvt;
						$j++;
					}
				}	
				$objp->mvt = $mouvements;

				$comptes_poids[]=$objp;

				$i++;				
			}
		}		


		//pagination effectué sur sql...
		//------------------------------------------------
		$pagination = array(
			'nbtotalofrecords'=>$nbtotalofrecords,
			'num'=>$num
		);
		//var_dump($pagination);

		$retour = array(
			'pagination'=>$pagination,
			'comptes_poids'=>$comptes_poids
		);


		return $retour;
	}

	/**
	 * recup solde poids client + filters
	 *
	 * @return array solde
	 */
	public function soldePoids($id_client=0, $filters=array(), $soldes_prec = false) {

		//extraction par abcvc
		$obj_matiere = new abcvcMatiere($this->db);
		$matieres_actives = $obj_matiere->getabcvc();
		//var_dump($matieres_actives);
		/* array (size=4)
			  1 => string 'Or' (length=2)
			  2 => string 'Argent' (length=6)
			  3 => string 'Palladium' (length=9)
			  4 => string 'Cuivre' (length=6)
		*/	 


		$search_nom = @$filters['search_nom'];
		$search_datede = @$filters['search_datede'];
		$search_datea = @$filters['search_datea'];

		if( $soldes_prec ){
			/*if($search_datede!=''){
				$tmp_date = explode('/',$search_datede);
				$search_datede = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' 00:00:00';
			}*/

			if($search_datede!=''){
				$tmp_date = explode('/',$search_datede);
				$search_datede = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' 00:00:00';
			}


			if($search_datea!=''){
				$tmp_date0 = explode(' ',$search_datea);
				$tmp_date = explode('/',$tmp_date0[0]);
				
				$search_datea = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' 23:59:59'; //.' '.$tmp_date0[1];

				$search_prec_datea = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' 00:00:00'; //.' '.$tmp_date0[1];
			}

		} else {
			//-----------------------------------------------------------------------------------
			if($search_datede!=''){
				$tmp_date = explode('/',$search_datede);
				$search_datede = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' 00:00:00';
			}

			if($search_datea!=''){
				$tmp_date0 = explode(' ',$search_datea);

				//heures ?
				if( count($tmp_date0)>1 ){
					$tmp_date = explode('/',$tmp_date0[0]);
					$search_datea = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' '.$tmp_date0[1];
				} else {
					$tmp_date = explode('/',$tmp_date0[0]);
					$search_datea = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' 23:59:59';
				}

			}
		}	


		// tous clients	  
		if( $id_client==0 ){

			$mouvements_clients = array();

			$sql0 = " 
			SELECT m.*,
			s.nom, s.code_client, s.code_fournisseur
			FROM ".MAIN_DB_PREFIX."abcvc_comptepoid_mvt as m
			LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (s.rowid = m.fk_soc)
			WHERE 1=1 ";

			if($search_nom!=''){
				$sql0 .= " AND (s.nom LIKE '%".$search_nom."%')";
			}
			
			if($search_datede!=''){
				$sql0 .= " AND (m.date_mvt >= '".$search_datede."')";
			}
			if($search_datea!=''){
				$sql0 .= " AND (m.date_mvt <= '".$search_datea."')";
			}			


			$sql0 .=  "
			ORDER BY s.nom asc, m.date_mvt asc"; //ORDER BY m.date_mvt desc "; //ORDER BY s.nom asc
			//var_dump($sql0);

			$resultmvt = $this->db->query($sql0);

			if ($resultmvt) {
				
				//$num_mvt = $this->db->num_rows($resultmvt);
				//$nbtotalofrecords = $num_mvt;
				//$sql0.= $order;
				//$sql0.= $this->db->plimit($filters['limit']+1, $filters['offset']);
				//$resultmvt = $this->db->query($sql0);

				$num_mvt = $this->db->num_rows($resultmvt);

				$j = 0;	
				while ($j < $num_mvt) {
					$objmvt = $this->db->fetch_object($resultmvt);

					$mouvements_clients[$objmvt->fk_soc][] = $objmvt;
					$j++;
				}
			}	
			//var_dump($mouvements_clients);
			//exit();

			foreach ($mouvements_clients as $id_client => $mouvements) {

				$dernier_mvt = 0;
				$nom_client = null;
				$code_client = null;
				$id_client = null;


				$solde_abcvc = array();
				foreach ($matieres_actives as $id_matiere => $matiere_actives) {
					$solde_abcvc[$id_matiere] = array(
						'debit'=>0,
						'credit'=>0
					);
				}	

				foreach ($mouvements as $mouvement) {

					if( strtotime($mouvement->date_mvt) > $dernier_mvt) $dernier_mvt = $mouvement->date_mvt;

					$nom_client = $mouvement->nom;
					$code_client = $mouvement->code_client;
					$code_fournisseur = $mouvement->code_fournisseur;
					$id_client = $mouvement->fk_soc;

					if( $mouvement->qty<0 ){	
						$solde_abcvc[$mouvement->ref_matiere]['debit'] += $mouvement->qty;
					} else {
						$solde_abcvc[$mouvement->ref_matiere]['credit'] += $mouvement->qty;
					}	
				}

				//cumul final debit/credit
				foreach ($solde_abcvc as $id_matiere => &$mvt) {
					//$debit = number_format($mvt['debit'],2);
					//$credit = number_format($mvt['credit'],2); raaaaaaaaaaaaaaa

					$debit = $mvt['debit'];
					$credit = $mvt['credit'];
					/*
					  2 => 
					    array (size=2)
					      'debit' => float -24.19
					      'credit' => float 24.19
					*/      
					$solde = number_format(floatval($credit) + floatval($debit),2);
					$solde = str_replace(',','',$solde );
					if($solde=='0.00'){
						$mvt['credit'] = 0;
						$mvt['debit'] = 0;

					} elseif ($solde>0){
						$mvt['credit'] = empty($solde)?0:$solde;
						$mvt['debit'] = 0;
					} else {
						$mvt['credit'] = 0;
						$mvt['debit'] = empty($solde)?0:$solde;				
					}
				}
				unset($mvt);

				$soldePoids[$id_client] = array(
					'id_client' => $id_client,
					'soldes'=>$solde_abcvc,
					'nom_client'=>$nom_client,
					'code_client'=>$code_client,
					'code_fournisseur'=>$code_fournisseur,					
					'date_solde'=>$date_solde,
					'maj'=>$dernier_mvt
				);

			}	

		//client spécifique	
		} else {

			$mouvements = array();

			$sql = " 
			SELECT m.*,
			s.nom, s.code_client, s.code_fournisseur
			FROM ".MAIN_DB_PREFIX."abcvc_comptepoid_mvt as m
			LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (s.rowid = m.fk_soc)
			WHERE m.fk_soc = ".(int)$id_client;

			//avoir un vrai solde final pour les pdf
			if( !$soldes_prec ){
				if($search_datede!=''){
					$sql .= " AND (m.date_mvt >= '".$search_datede."')";
				}
			}	

			if($search_datea!=''){
				$sql .= " AND (m.date_mvt <= '".$search_datea."')";
			}			
			$sql .= "			
			ORDER BY m.date_mvt asc
			"; 
			//var_dump($sql);
			//exit();


			$dernier_mvt = 0;
			$nom_client = null;
			$code_client = null;
			$id_client = null;

			$resultmvt = $this->db->query($sql);
			if ($resultmvt) {
				$num_mvt = $this->db->num_rows($resultmvt);
				$j = 0;	
				while ($j < $num_mvt) {
					$objmvt = $this->db->fetch_object($resultmvt);

					if( strtotime($objmvt->date_mvt) > $dernier_mvt) $dernier_mvt = $objmvt->date_mvt;

					if(is_null($nom_client)) $nom_client = $objmvt->nom;
					if(is_null($code_client)) $code_client = $objmvt->code_client;

					$code_fournisseur = $objmvt->code_fournisseur;

					if(is_null($id_client)) $id_client = $objmvt->fk_soc;

					$mouvements[] = $objmvt;
					$j++;
				}
			}	
	 
			$solde_abcvc = array();
			foreach ($matieres_actives as $id_matiere => $matiere_actives) {
				$solde_abcvc[$id_matiere] = array(
					'debit'=>0,
					'credit'=>0
				);
			}	
			//var_dump($mouvements);
			//exit();

			foreach ($mouvements as $mouvement) {
				if( $mouvement->qty<0 ){	
					$solde_abcvc[$mouvement->ref_matiere]['debit'] += (float)$mouvement->qty;
				} else {
					$solde_abcvc[$mouvement->ref_matiere]['credit'] += (float)$mouvement->qty;
				}	
			}
			//var_dump($solde_abcvc);
			//exit();

			//cumul final debit/credit
			foreach ($solde_abcvc as $id_matiere => &$mvt) {

				$debit = $mvt['debit'];
				$credit = $mvt['credit'];
				/*
				  2 => 
				    array (size=2)
				      'debit' => float -24.19
				      'credit' => float 24.19
				*/      
				$solde = number_format(floatval($credit) + floatval($debit),2);
				$solde = str_replace(',','',$solde );
				//var_dump('credit: '.$credit);
				//var_dump('debit: '.$debit);
				//var_dump('solde: '.$solde);

				if($solde=='0.00'){
					$mvt['credit'] = 0;
					$mvt['debit'] = 0;

				} elseif ($solde>0){
					$mvt['credit'] = empty($solde)?0:$solde;
					$mvt['debit'] = 0;
				} else {
					$mvt['credit'] = 0;
					$mvt['debit'] = empty($solde)?0:$solde;				
				}
			}
			unset($mvt);




			//solde précédent
			//------------------------------------------------------------------------------
			$soldes_precedent = array();
			if( $soldes_prec ){

				foreach ($matieres_actives as $id_matiere => $matiere_actives) {
					$soldes_precedent[$id_matiere] = array(
						'debit'=>0,
						'credit'=>0
					);
				}	

				//if($search_datede!=''){
					$mouvements = array();
					$sql = " 
					SELECT m.*,
					s.nom, s.code_client
					FROM ".MAIN_DB_PREFIX."abcvc_comptepoid_mvt as m
					LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (s.rowid = m.fk_soc)
					WHERE m.fk_soc = ".(int)$id_client;

					if($search_datede!=''){
						$sql .= " AND (m.date_mvt < '".$search_datede."')";
					}
					
					if($search_datea!=''){
						$sql .= " AND (m.date_mvt < '".$search_prec_datea."')";
					}	
		
					$sql .= "			
					ORDER BY m.date_mvt desc
					"; 

					//var_dump($sql);
					//exit();
					
					$resultmvt = $this->db->query($sql);
					if ($resultmvt) {
						$num_mvt = $this->db->num_rows($resultmvt);
						$j = 0;	
						while ($j < $num_mvt) {
							$objmvt = $this->db->fetch_object($resultmvt);
							$mouvements[] = $objmvt;
							$j++;
						}
					}	
		 			foreach ($mouvements as $mouvement) {
						if( $mouvement->qty<0 ){	
							$soldes_precedent[$mouvement->ref_matiere]['debit'] += (float)$mouvement->qty;
						} else {
							$soldes_precedent[$mouvement->ref_matiere]['credit'] += (float)$mouvement->qty;
						}	
					}
					//cumul final debit/credit
					foreach ($soldes_precedent as $id_matiere => &$mvt) {
						$debit = $mvt['debit'];
						$credit = $mvt['credit'];

						$solde = number_format(floatval($credit) + floatval($debit),2);
						$solde = str_replace(',','',$solde );
						if($solde=='0.00'){
							$mvt['credit'] = 0;
							$mvt['debit'] = 0;

						} elseif ($solde>0){
							$mvt['credit'] = empty($solde)?0:$solde;
							$mvt['debit'] = 0;
						} else {
							$mvt['credit'] = 0;
							$mvt['debit'] = empty($solde)?0:$solde;				
						}
					}
					unset($mvt);
				//}

			}



			$soldePoids[$id_client] = array(
				'id_client' => $id_client,
				'soldes'=>$solde_abcvc,
				'soldes_precedent'=>$soldes_precedent,
				'nom_client'=>$nom_client,
				'code_client'=>$code_client,
				'code_fournisseur'=>$code_fournisseur,	
				'date_solde'=>$date_solde,
				'maj'=>$dernier_mvt,
				'date_de'=>$search_datede,
				'date_a'=>$search_datea
			);

		}	  
		//var_dump($soldePoids);
		//exit();


		//pagination effectué sur objet final...
		//------------------------------------------------
		$nbtotalofrecords = count($soldePoids);
		if($nbtotalofrecords>0){
			//tous
			if($filters['limit']==0) {
				$soldePoids_filtered = $soldePoids;
			} else {
				$soldePoids_filtered = array_slice($soldePoids,$filters['offset'],$filters['limit']+1);
			}

		} else {
			$soldePoids_filtered = array();
		}

		$num_mvt = count($soldePoids_filtered);

		$pagination = array(
			'nbtotalofrecords'=>$nbtotalofrecords,
			'num'=>$num_mvt
		);
		//var_dump($pagination);

		$retour = array(
			'pagination'=>$pagination,
			'soldePoids'=>$soldePoids_filtered
		);

		return $retour;
	}







	/**
	 * calcul poids produits 
	 *
	 * @return int <0 if KO, Id of created object if OK
	 */
	public function calculPoids($id_client=0, $type='facture', $id_type=0, $doc='bl', $id_doc_line=0) {

		//extraction par abcvc
		$obj_matiere = new abcvcMatiere($this->db);
		//$matieres_actives = $obj_matiere->getabcvc();
		$matieres_actives = $obj_matiere->getabcvc(0);

		$solde_abcvc = array();
		foreach ($matieres_actives as $id_matiere => $matiere_actives) {
			$solde_abcvc[$id_matiere] = array(
				'debit'=>0,
				'credit'=>0
			);
		}	

		//recup infos client
		$obj_client = new Societe($this->db);
		$obj_client->fetch($id_client);
		/*var_dump($obj_client);
		exit();		
		public 'array_options' => 
		    array (size=1)
		      'options_perte' => string '3.0000' (length=6)
		*/


		//récup arbo catégories full..
		$categories = array();
		$sql = "
		SELECT c.rowid , c.fk_parent , c.label
		FROM llx_categorie as c 
		WHERE c.type = 0 "; //produit

		$result = $this->db->query($sql);
		if ($result) {

			$num = $this->db->num_rows($result);
			$i = 0;
			while ($i < $num)	{
				$objcat = $this->db->fetch_object($result);
				$categories[$objcat->rowid] = $objcat;
				$i++;
			}
		}		
		/*
		var_dump($categories);
		exit();
		array (size=11)
		  1 => 
		    object(stdClass)[121]
		      public 'rowid' => string '1' (length=1)
		      public 'fk_parent' => string '0' (length=1)
		      public 'label' => string 'Or Jaune 18 carats' (length=18)
		*/      


		//déclenché par facture (obsolete trigger désactivé)
		//----------------------------
		if( ($type=='facture') && ($id_type!=0) ){

			//TODO si facture a un statut particulier seulement ?
			//info facture

			$sql = 'SELECT l.rowid, l.facnumber, l.datec';
			$sql.= ' FROM '.MAIN_DB_PREFIX.'facture as l';
			$sql.= ' WHERE l.rowid = '.$id_type;
			$result = $this->db->query($sql);
			if ($result) {
				$objfacture = $this->db->fetch_object($result);
			}


			$flag_abcvc_mvt = false;

			$sql = 'SELECT l.rowid, l.fk_facture, l.fk_product, l.qty, l.product_type, ';
			$sql.= ' p.ref as product_ref, p.label as product_label, e.perte';

			$sql.= ' FROM '.MAIN_DB_PREFIX.'facturedet as l';
			$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON l.fk_product = p.rowid';
			$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields as e ON e.fk_object = p.rowid';

			$sql.= ' WHERE l.fk_facture = '.$id_type;
			$sql.= ' AND l.product_type = 0';	//produit slt
			$sql.= ' ORDER BY l.rang, l.rowid';
			//var_dump($sql);

			$produits_raw = array();

			dol_syslog(get_class($this)."::calculatePoids(facture - id:".$id_type." )", LOG_DEBUG);
			$result = $this->db->query($sql);
			if ($result) {

				$num = $this->db->num_rows($result);
				$i = 0;
				while ($i < $num) {
					$objproduit = $this->db->fetch_object($result);
					//var_dump($objproduit);
					//recup catégorie
					$sql = "
					SELECT ct.fk_categorie, c.label, c.rowid 
					FROM llx_categorie_product as ct, llx_categorie as c 
					WHERE ct.fk_categorie = c.rowid AND ct.fk_product = ".$objproduit->fk_product." AND c.type = 0 ";
					$resultcat = $this->db->query($sql);
					//var_dump($sql);
					if ($resultcat) {
						$objcat = $this->db->fetch_object($resultcat);
						//var_dump($objcat);
						/*object(stdClass)[134]
						  public 'fk_categorie' => string '22' (length=2)
						  public 'label' => string 'testniv3' (length=8)
						  public 'rowid' => string '22' (length=2)*/

						//a une catégorie racine?  
						if(!is_null($objcat)){
							//var_dump($categories[$objcat->fk_categorie]);

							if($categories[$objcat->fk_categorie]->fk_parent==0){
								$categorie_abcvc = $categories[$objcat->fk_categorie];
							} else {
								// a une catégorie **racine** métaux précieux ???
								$cat_parent = $categories[$objcat->fk_categorie]->fk_parent;
								while ( $cat_parent != 0) {
									//var_dump($cat_parent);
									$categorie_abcvc = $categories[$cat_parent];
									$cat_parent = $categories[$cat_parent]->fk_parent;

								}
							}
							//var_dump($categorie_abcvc);
							/*	exit();
								object(stdClass)[121]
								  public 'rowid' => string '1' (length=1)
								  public 'fk_parent' => string '0' (length=1)
								  public 'label' => string 'Or Jaune 18 carats' (length=18)
							*/ 
							/*if($objproduit->rowid==451){
								exit();
							}*/
							$sql = "
							SELECT c.* 
							FROM llx_abcvc_concentrations as c 
							WHERE c.id_categorie = ".$categorie_abcvc->rowid." AND c.active = 1 ";
							$resultconcent = $this->db->query($sql);
							//var_dump($sql);
							if ($resultconcent) {
								$objconcent = $this->db->fetch_object($resultconcent);
								//var_dump($objconcent);
								//ok concentration?  
								if(!is_null($objconcent)){
									$objproduit->concentration_label = $objconcent->label;
									$objproduit->concentration_structure = json_decode($objconcent->structure);

									$perte_client = @$obj_client->array_options['options_perte']; //3.0000

									//calcul perte finale
									if(!is_null($perte_client)){
										//perte client
										$objproduit->concentration_perte = (float)$perte_client;

									} elseif(!is_null($objproduit->perte)){
										//perte produit
										$objproduit->concentration_perte = (float)$objproduit->perte;

									} else {
										//perte standard global (5%)
										$objproduit->concentration_perte = 5;		
									}
									
									//huu la concentration a *vraiement* une concentration de abcvc enregistré!!	
									if(!is_null($objproduit->concentration_structure)){
										//calcul répartition matière ...	              
										foreach ($objproduit->concentration_structure as $concentration_matiere) {

											$qty_matiere = number_format( $objproduit->qty * ($concentration_matiere->montant / 1000) * (1+ ($objproduit->concentration_perte/100) ),2);
											$concentration_matiere->qty_matiere = $qty_matiere;

											$detail_calcul = $matieres_actives[$concentration_matiere->id_matiere].': '.$qty_matiere.' ( ';
											$detail_calcul .= "$objproduit->qty*($concentration_matiere->montant/1000)*".(1+($objproduit->concentration_perte/100))." )";
											$concentration_matiere->detail = $detail_calcul;

										}

										$produits_raw[]=$objproduit;
									}	
									/*	var_dump($objproduit);
										exit();
										object(stdClass)[122]
										  public 'rowid' => string '450' (length=3)
										  public 'fk_facture' => string '186' (length=3)
										  public 'fk_product' => string '10' (length=2)
										  public 'qty' => string '80.48' (length=5)
										  public 'product_type' => string '0' (length=1)
										  public 'product_ref' => string 'PR-0004' (length=7)
										  public 'product_label' => string 'Fil 150 OJ 750' (length=14)
										  public 'concentration_label' => string 'Or Jaune 18 carats' (length=18)
										  public 'concentration_structure' => 
										    array (size=3)
										      0 => 
										        object(stdClass)[137]
										          public 'id_matiere' => string '1' (length=1)
										          public 'montant' => string '751' (length=3)
										          public 'detail' => string '80.48 * (751 / 1000) * (1+(5/100) )' (length=35)
										          public 'qty_matiere' => float 63.462504
										      1 => 
										        object(stdClass)[138]
										          public 'id_matiere' => string '2' (length=1)
										          public 'montant' => string '130' (length=3)
										          public 'detail' => string '80.48 * (130 / 1000) * (1+(5/100) )' (length=35)
										          public 'qty_matiere' => float 10.98552
										      2 => 
										        object(stdClass)[139]
										          public 'id_matiere' => string '4' (length=1)
										          public 'montant' => string '130' (length=3)
										          public 'detail' => string '80.48 * (130 / 1000) * (1+(5/100) )' (length=35)
										          public 'qty_matiere' => float 10.98552
										  public 'concentration_perte' => int 5
									*/


								}
							}	

						}  

					}

					$i++;	
				}


				$mouvements_abcvc_details = array();
				//compil mvt métaux
				foreach ($produits_raw as $produit_raw) {
					$flag_abcvc_mvt = true;
					foreach ($produit_raw->concentration_structure as $concentration) {
						$solde_abcvc[$concentration->id_matiere]['debit'] += $concentration->qty_matiere;
						$mouvements_abcvc_details[$produit_raw->product_ref][] = $concentration->detail;
					}
				}

				//mvt métaux ?
				if( $flag_abcvc_mvt ){

					//source mvt déja intégrée ?
					$sql = "
					SELECT l.rowid
					FROM llx_abcvc_comptepoid as l
					WHERE l.type = 'facture' and l.id_type = ".$id_type;
					$result = $this->db->query($sql);
					$id_comptepoid = null;
					if ($result) {
						$objcomptepoid = $this->db->fetch_object($result);
						$id_comptepoid = $objcomptepoid->rowid;
					}

					//insertion
					if(is_null($id_comptepoid)){
						//insertion abcvc_comptepoid	
						$sql ="
						INSERT INTO llx_abcvc_comptepoid (
						ref,
						fk_soc,
						date_creation,
						type,
						ref_type,
						id_type,
						description,
						structure
						) VALUES (
						'a',
						".$id_client.",
						'".$objfacture->datec."',
						'facture',
						'".$objfacture->facnumber."',
						".$objfacture->rowid.",
						'',
						'".json_encode($mouvements_abcvc_details)."' );";
						$result = $this->db->query($sql);

						$id_poid = $this->db->last_insert_id(MAIN_DB_PREFIX . "abcvc_comptepoid");

						//enr matieres
						foreach ($solde_abcvc as $id_matiere => $mvt_abcvc) {
							$qty=0;
							//mvt?
							if( ($mvt_abcvc['debit']!=0) || ($mvt_abcvc['credit']!=0) ){
								//debit/credit ?
								if( $mvt_abcvc['debit']!=0 ){
									$qty-=$mvt_abcvc['debit'];
								} else {
									$qty+=$mvt_abcvc['credit'];
								}

								$sql ="
								INSERT INTO llx_abcvc_comptepoid_mvt (
								id_comptepoid,
								fk_soc,
								date_mvt,
								ref_matiere,
								qty)
								VALUES (
								".$id_poid.",
								".$id_client.",
								'".$objfacture->datec."',
								".$id_matiere.",
								'".$qty."');";
								//var_dump($sql);
								$result = $this->db->query($sql);
							} 

						}


					//update	
					} else {
						$sql ="
						UPDATE llx_abcvc_comptepoid
						SET
						date_creation = '".$objfacture->datec."',
						structure = '".json_encode($mouvements_abcvc_details)."'
						WHERE rowid = ".$id_comptepoid;
						$result = $this->db->query($sql);

						//del ancien mvt
						$sql ="
						DELETE FROM llx_abcvc_comptepoid_mvt
						WHERE 
						id_comptepoid = ".$id_comptepoid;
						$result = $this->db->query($sql);


						foreach ($solde_abcvc as $id_matiere => $mvt_abcvc) {
							$qty=0;
							//mvt?
							if( ($mvt_abcvc['debit']!=0) || ($mvt_abcvc['credit']!=0) ){
								//debit/credit ?
								if( $mvt_abcvc['debit']!=0 ){
									$qty-=$mvt_abcvc['debit'];
								} else {
									$qty+=$mvt_abcvc['credit'];
								}

								$sql ="
								INSERT INTO llx_abcvc_comptepoid_mvt (
								id_comptepoid,
								fk_soc,
								date_mvt,
								ref_matiere,
								qty)
								VALUES (
								".$id_comptepoid.",
								".$id_client.",
								'".$objfacture->datec."',
								".$id_matiere.",
								'".$qty."');";
								//var_dump($sql);
								$result = $this->db->query($sql);

							} 

						}

					}

				}

			}

		}
		
		//déclenché par expedition
		//----------------------------
		if( ($type=='expedition') && ($id_type!=0) ){

			//info BL
			$sql = 'SELECT l.rowid, l.ref, l.date_valid';
			$sql.= ' FROM '.MAIN_DB_PREFIX.'expedition as l';
			$sql.= ' WHERE l.rowid = '.$id_type;
			$result = $this->db->query($sql);
			if ($result) {
				$objexpedition = $this->db->fetch_object($result);
			}
			//var_dump($objexpedition);

			$flag_abcvc_mvt = false;

			$sql = 'SELECT ed.rowid as line_id, ed.qty, ed.fk_origin_line, cd.fk_product, ';
			$sql.= ' p.ref as product_ref, p.label as product_label, e.perte, pe.poids';

			$sql.= ' FROM '.MAIN_DB_PREFIX.'expeditiondet as ed';

			$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'commandedet as cd ON ed.fk_origin_line = cd.rowid';			
			$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON cd.fk_product = p.rowid';
			$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields as e ON e.fk_object = p.rowid';

			$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'expeditiondet_extrafields as pe ON pe.fk_object = ed.rowid';

			$sql.= ' WHERE ed.fk_expedition = '.$id_type;
			$sql.= ' AND cd.product_type = 0';	//produit slt
			$sql.= ' ORDER BY cd.rang, ed.fk_origin_line';
			//var_dump($sql);

			$produits_raw = array();

			dol_syslog(get_class($this)."::calculatePoids(expedition - id:".$id_type." )", LOG_DEBUG);
			$result = $this->db->query($sql);
			if ($result) {

				$num = $this->db->num_rows($result);
				$i = 0;
				while ($i < $num) {
				
					$objproduit = $this->db->fetch_object($result);

					//poids saisis ?
					if($objproduit->poids != 0){

						//recup catégorie
						$sql = "
						SELECT ct.fk_categorie, c.label, c.rowid 
						FROM llx_categorie_product as ct, llx_categorie as c 
						WHERE ct.fk_categorie = c.rowid AND ct.fk_product = ".$objproduit->fk_product." AND c.type = 0 ";
						$resultcat = $this->db->query($sql);
						//var_dump($sql);
						if ($resultcat) {
							$objcat = $this->db->fetch_object($resultcat);
							//var_dump($objcat);
							/*object(stdClass)[134]
							  public 'fk_categorie' => string '22' (length=2)
							  public 'label' => string 'testniv3' (length=8)
							  public 'rowid' => string '22' (length=2)*/

							//a une catégorie racine?  
							if(!is_null($objcat)){

								if($categories[$objcat->fk_categorie]->fk_parent==0){
									$categorie_abcvc = $categories[$objcat->fk_categorie];
								} else {
									// a une catégorie **racine** métaux précieux ???
									$cat_parent = $categories[$objcat->fk_categorie]->fk_parent;
									while ( $cat_parent != 0) {
										//var_dump($cat_parent);
										$categorie_abcvc = $categories[$cat_parent];
										$cat_parent = $categories[$cat_parent]->fk_parent;
									}
								}
								//var_dump($categorie_abcvc);

								//recup concentration éventuelle
								$sql = "
								SELECT c.* 
								FROM llx_abcvc_concentrations as c 
								WHERE c.id_categorie = ".$categorie_abcvc->rowid." AND c.active = 1 ";
								$resultconcent = $this->db->query($sql);
								//var_dump($sql);
								if ($resultconcent) {
									$objconcent = $this->db->fetch_object($resultconcent);
									//var_dump($objconcent);
									//ok concentration? 
									if(!is_null($objconcent)){
										$objproduit->concentration_label = $objconcent->label;
										$objproduit->concentration_structure = json_decode($objconcent->structure);

										$perte_client = @$obj_client->array_options['options_perte']; //3.0000

										//calcul perte finale
										if(!is_null($perte_client)){
											//perte client
											$objproduit->concentration_perte = (float)$perte_client;

										} elseif(!is_null($objproduit->perte)){
											//perte produit
											$objproduit->concentration_perte = (float)$objproduit->perte;

										} else {
											//perte standard global (5%)
											$objproduit->concentration_perte = 5;		
										}

										//huu la concentration a *vraiement* une concentration de abcvc enregistré!!	
										if(!is_null($objproduit->concentration_structure)){
											//calcul répartition matière ...	              
											foreach ($objproduit->concentration_structure as $concentration_matiere) {

												$qty_matiere = number_format( $objproduit->poids * ($concentration_matiere->montant / 1000) * (1+ ($objproduit->concentration_perte/100) ),2);
												$concentration_matiere->qty_matiere = $qty_matiere;

												$detail_calcul = $matieres_actives[$concentration_matiere->id_matiere].': '.$qty_matiere.' ( ';
												$detail_calcul .= "$objproduit->poids*($concentration_matiere->montant/1000)*".(1+($objproduit->concentration_perte/100))." )";
												$concentration_matiere->detail = $detail_calcul;

											}

											$produits_raw[]=$objproduit;
										}

									}						
								}	

							}	
						}

					}	

					$i++;	
				}


				$mouvements_abcvc_details = array();
				//compil mvt métaux
				foreach ($produits_raw as $produit_raw) {
					$flag_abcvc_mvt = true;
					foreach ($produit_raw->concentration_structure as $concentration) {
						if($concentration->qty_matiere>0){
							$solde_abcvc[$concentration->id_matiere]['debit'] += abs($concentration->qty_matiere);
						} else {
							$solde_abcvc[$concentration->id_matiere]['credit'] += abs($concentration->qty_matiere);
						}
						$mouvements_abcvc_details[$produit_raw->product_ref][] = $concentration->detail;
					}
				}

				//var_dump($solde_abcvc);
				//exit();
				/* 
				array (size=5)
				  1 => 
				    array (size=2)
				      'debit' => float 1.69
				      'credit' => int 0
				  2 => 
				    array (size=2)
				      'debit' => float 0.29
				      'credit' => float 24.48
				  3 => 
				    array (size=2)
				      'debit' => int 0
				      'credit' => int 0
				  4 => 
				    array (size=2)
				      'debit' => float 0.29
				      'credit' => int 0
				  5 => 
				    array (size=2)
				      'debit' => int 0
				      'credit' => int 0
				*/
				//mvt métaux ?
				if( $flag_abcvc_mvt ){

					//source mvt déja intégrée ?
					$sql = "
					SELECT l.rowid
					FROM llx_abcvc_comptepoid as l
					WHERE l.type = 'expedition' and l.id_type = ".$id_type;
					$result = $this->db->query($sql);
					$id_comptepoid = null;
					if ($result) {
						$objcomptepoid = $this->db->fetch_object($result);
						$id_comptepoid = $objcomptepoid->rowid;
					}
					/*
					var_dump($id_comptepoid);
					var_dump($solde_abcvc);
					array (size=3)
					  1 => 
					    array (size=2)
					      'debit' => int 0
					      'credit' => int 0
					  2 => 
					    array (size=2)
					      'debit' => float 19.69
					      'credit' => float 19.69
					  3 => 
					    array (size=2)
					      'debit' => int 0
					      'credit' => int 0 
					exit();					
					*/
				
					//insertion
					if(is_null($id_comptepoid)){
						//insertion abcvc_comptepoid	
							$sql ="
							INSERT INTO llx_abcvc_comptepoid (
							ref,
							fk_soc,
							date_creation,
							type,
							ref_type,
							id_type,
							description,
							structure
							) VALUES (
							'a',
							".$id_client.",
							'".$objexpedition->date_valid."',
							'expedition',
							'".$objexpedition->ref."',
							".$objexpedition->rowid.",
							'',
							'".json_encode($mouvements_abcvc_details)."' );";
							$result = $this->db->query($sql);

						$id_poid = $this->db->last_insert_id(MAIN_DB_PREFIX . "abcvc_comptepoid");



						//enr matieres
						foreach ($solde_abcvc as $id_matiere => $mvt_abcvc) {
							$qty=0;
							//mvt?
							

							if( ($mvt_abcvc['debit']!=0) || ($mvt_abcvc['credit']!=0) ){

								//cas solde nul ...prrf waaaaaaaaaaaaaaaaaaaaaaaaa
								if( number_format($mvt_abcvc['credit'],2) - number_format($mvt_abcvc['debit'],2) ==0){
									$qty = 0;
								} else {

									//debit/credit ?
									if( $mvt_abcvc['debit']!=0 ){
										$qty-=$mvt_abcvc['debit'];
									} else {
										$qty+=$mvt_abcvc['credit'];
									}

								}	

								$sql ="
								INSERT INTO llx_abcvc_comptepoid_mvt (
								id_comptepoid,
								fk_soc,
								date_mvt,
								ref_matiere,
								qty)
								VALUES (
								".$id_poid.",
								".$id_client.",
								'".$objexpedition->date_valid."',
								".$id_matiere.",
								'".$qty."');";
								//var_dump($sql);
								$result = $this->db->query($sql);
							} 

						}


					//update	
					} else {
						$sql ="
						UPDATE llx_abcvc_comptepoid
						SET
						date_creation = '".$objexpedition->date_valid."',
						structure = '".json_encode($mouvements_abcvc_details)."'
						WHERE rowid = ".$id_comptepoid;
						$result = $this->db->query($sql);

						//del ancien mvt
						$sql ="
						DELETE FROM llx_abcvc_comptepoid_mvt
						WHERE 
						id_comptepoid = ".$id_comptepoid;
						$result = $this->db->query($sql);

						foreach ($solde_abcvc as $id_matiere => $mvt_abcvc) {
							$qty=0;
							

							//mvt?
							if( ($mvt_abcvc['debit']!=0) || ($mvt_abcvc['credit']!=0) ){
								
								$qty = $mvt_abcvc['credit'] - $mvt_abcvc['debit'];

								/*
								//cas solde nul ...prrf waaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
								if( number_format($mvt_abcvc['credit'],2) - number_format($mvt_abcvc['debit'],2) == 0){
									$qty = 0;
								} else {

									//debit/credit ?
									if( $mvt_abcvc['debit']!=0 ){
										$qty-=$mvt_abcvc['debit'];
									} else {
										$qty+=$mvt_abcvc['credit'];
									}

								}	*/

								$sql ="
								INSERT INTO llx_abcvc_comptepoid_mvt (
								id_comptepoid,
								fk_soc,
								date_mvt,
								ref_matiere,
								qty)
								VALUES (
								".$id_comptepoid.",
								".$id_client.",
								'".$objexpedition->date_valid."',
								".$id_matiere.",
								'".$qty."');";
								//var_dump($sql);
								$result = $this->db->query($sql);

							} 

						}
				

					}

				}

			}	

		}
/*

WAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
array (size=4)
  1 => 
    array (size=2)
      'debit' => float 50.79
      'credit' => int 0
  2 => 
    array (size=2)
      'debit' => float 15.77
      'credit' => float 15.77
  3 => 
    array (size=2)
      'debit' => int 0
      'credit' => int 0
  4 => 
    array (size=2)
      'debit' => float 6.62
      'credit' => int 0

1639:int 1

1640:float 50.79

1641:int 0

1642:float -50.79

1639:int 2

1640:float 15.77

1641:float 15.77

1642:float 0

1639:int 4

1640:float 6.62

1641:int 0

1642:float -6.62

*/


		return true;

		//var_dump($produits_raw);
		//var_dump($solde_abcvc);
		//exit();
	}





	/**
	 * calcul poids produit 
	 *
	 * @return false if KO, structure object if OK
	 */
	public function calculPoidsProduit($id_client=0, $id_produit=0, $qty=0, $doc='commande', $id_doc_line=0) {

		//extraction abcvc
		$obj_matiere = new abcvcMatiere($this->db);
		$matieres_actives = $obj_matiere->getabcvc();

		$solde_abcvc = array();
		foreach ($matieres_actives as $id_matiere => $matiere_actives) {
			$solde_abcvc[$id_matiere] = array(
				'debit'=>0,
				'credit'=>0
			);
		}	

		//recup infos client
		$obj_client = new Societe($this->db);
		$obj_client->fetch($id_client);
		/*var_dump($obj_client);
		exit();		
		public 'array_options' => 
		    array (size=1)
		      'options_perte' => string '3.0000' (length=6)
		*/


		//récup arbo catégories full..
		$categories = array();
		$sql = "
		SELECT c.rowid , c.fk_parent , c.label
		FROM llx_categorie as c 
		WHERE c.type = 0 "; //produit

		$result = $this->db->query($sql);
		if ($result) {
			$num = $this->db->num_rows($result);
			$i = 0;
			while ($i < $num)	{
				$objcat = $this->db->fetch_object($result);
				$categories[$objcat->rowid] = $objcat;
				$i++;
			}
		}	


		//categorie(s) produit
		$sql = "
		SELECT ct.fk_categorie, c.label, c.rowid 
		FROM llx_categorie_product as ct, llx_categorie as c 
		WHERE ct.fk_categorie = c.rowid AND ct.fk_product = ".$id_produit." AND c.type = 0 ";
		$resultcat = $this->db->query($sql);
		//var_dump($sql);

		if ($resultcat) {
			$objcat = $this->db->fetch_object($resultcat);

			//a une catégorie racine?  
			if(!is_null($objcat)){
				//var_dump($categories[$objcat->fk_categorie]);

				if($categories[$objcat->fk_categorie]->fk_parent==0){
					$categorie_abcvc = $categories[$objcat->fk_categorie];
				} else {
					// a une catégorie **racine** métaux précieux ???
					$cat_parent = $categories[$objcat->fk_categorie]->fk_parent;
					while ( $cat_parent != 0) {
						//var_dump($cat_parent);
						$categorie_abcvc = $categories[$cat_parent];
						$cat_parent = $categories[$cat_parent]->fk_parent;

					}
				}
				//recup concentration categorie racine
				$sql = "
				SELECT c.* 
				FROM llx_abcvc_concentrations as c 
				WHERE c.id_categorie = ".$categorie_abcvc->rowid." AND c.active = 1 ";
				$resultconcent = $this->db->query($sql);
				//var_dump($sql);
				if ($resultconcent) {
					$objconcent = $this->db->fetch_object($resultconcent);
					//var_dump($objconcent);
					//ok concentration?  
					if(!is_null($objconcent)){
						$concentration_label = $objconcent->label;
						$concentration_structure = json_decode($objconcent->structure);

						$perte_client = @$obj_client->array_options['options_perte']; //3.0000

						//recup pertes produit éventuel
						$sql = "SELECT rowid, perte FROM llx_product_extrafields WHERE fk_object = ".$id_produit;
						$resultperte = $this->db->query($sql);
						$objperte = $this->db->fetch_object($resultperte);

						//recup poids saisi
						//commande
						if($doc == "commande" ){
							$sql = "SELECT rowid, poids FROM llx_commandedet_extrafields WHERE fk_object = ".$id_doc_line;
						//BL	
						} else {
							$sql = "SELECT rowid, poids FROM llx_expeditiondet_extrafields WHERE fk_object = ".$id_doc_line;
						}
						$resultpoidsaisi = $this->db->query($sql);
						$objpoidsaisi = $this->db->fetch_object($resultpoidsaisi);

						//aucun poid saisi pour ce produit...
						if(is_null($objpoidsaisi)){
							return false;
						}
						$poidsaisi = $objpoidsaisi->poids;
						//var_dump($objpoidsaisi);
						//exit();

						// !!!!! poids négatif = crédit !!!!!!
						//if($poidsaisi<=0){
						//	return false;							
						//}
						/*
							var_dump($objpoidsaisi);
							object(stdClass)[156]
							  public 'rowid' => string '1' (length=1)
							  public 'poids' => string '3.4500' (length=6)
						*/  

						//calcul perte finale
						if(!is_null($perte_client)){
							//perte client
							$concentration_perte = (float)$perte_client;

						} elseif(!is_null(@$objperte->perte)){
							//perte produit
							$concentration_perte = (float)$objperte->perte;

						} else {
							//perte standard global (5%)
							$concentration_perte = 5;		
						}
						
						//huu la concentration a *vraiement* une concentration de abcvc enregistré!!	
						if(!is_null($concentration_structure)){
							//calcul répartition matière ...	              
							foreach ($concentration_structure as $concentration_matiere) {

								if( array_key_exists($concentration_matiere->id_matiere,$matieres_actives) ) {

									$qty_matiere = number_format( ($poidsaisi) * ($concentration_matiere->montant / 1000) * (1+ ($concentration_perte/100) ),2);
									$concentration_matiere->qty_matiere = $qty_matiere;

									if($qty_matiere<0){
										$type = 'credit';
										$detail_calcul = $matieres_actives[$concentration_matiere->id_matiere].': +'.abs($qty_matiere);//.' ( ';
									} else {
										$type = 'debit';
										$detail_calcul = $matieres_actives[$concentration_matiere->id_matiere].': '.$qty_matiere;//.' ( ';
									}	
									//$detail_calcul .= "($poidsaisi*$qty g)*($concentration_matiere->montant/1000)*".(1+($concentration_perte/100))." )";
									$concentration_matiere->detail = $detail_calcul;
								}

							}

							return array(
								'type' => $type,
								'label' => $concentration_label,
								'perte' => number_format( ($concentration_perte),2)." %",
								'poids' => number_format( ($poidsaisi),2)." g", //" (".number_format( ($poidsaisi),2)."g)",
								'structure' => $concentration_structure
							);	

						}
					}
				}
			}
		}			

		return false;
	}






	/**
	 * calcul BL à facturer / clients 
	 *
	 * @return false if KO, structure object if OK
	 */
	public function getBLtoBill($filters) {

		if($filters['search_datede']!=''){
			$date1 = explode('/',$filters['search_datede']);
			$filters['search_datede'] = $date1[2].'-'.$date1[1].'-'.$date1[0].' 00:00:00';
		}	
		if($filters['search_datea']!=''){
			$date1 = explode('/',$filters['search_datea']);
			$filters['search_datea'] = $date1[2].'-'.$date1[1].'-'.$date1[0].' 23:59:59';
		}

		//liste BL validé DB
		$listeBL = array();
		$sql = " 
		SELECT m.rowid, m.ref, m.fk_soc, m.fk_statut, m.date_creation ,
		el.fk_source as id_commande, c.ref as ref_commande, s.rowid as id_client, s.nom as nom_client, s.code_client, s.mode_reglement, s.cond_reglement

		FROM ".MAIN_DB_PREFIX."expedition as m
		LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (s.rowid = m.fk_soc)
		LEFT JOIN ".MAIN_DB_PREFIX."element_element as el ON (el.fk_target = m.rowid AND el.targettype = 'shipping')
		LEFT JOIN ".MAIN_DB_PREFIX."commande as c ON (c.rowid = el.fk_source)
		WHERE m.fk_statut >= 1 ";
		if($filters['search_nom']!=''){
			$sql .= " AND s.nom LIKE '%".$filters['search_nom']."%'";
		}	
		if($filters['search_datede']!=''){
			$sql .= " AND (m.date_creation >= '".$filters['search_datede']."')";
		}
		if($filters['search_datea']!=''){
			$sql .= " AND (m.date_creation <= '".$filters['search_datea']."')";
		}			
		$sql .= " ORDER BY m.date_creation desc "; 
		$result = $this->db->query($sql);
		if ($result) {
			$num = $this->db->num_rows($result);
			$j = 0;	
			while ($j < $num) {
				$objBL = $this->db->fetch_object($result);
				$listeBL[] = $objBL;
				$j++;
			}
		}	
		//var_dump($sql);
		//var_dump($listeBL);
		//exit();

		//déja facturé ?... (présent dans le detail d'une facture)
		$liste_facturedet = array();
		$sql = " 
		SELECT rowid, fk_facture, description 
		FROM ".MAIN_DB_PREFIX."facturedet 
		WHERE fk_product is null and product_type = 0";
		$result = $this->db->query($sql);
		if ($result) {
			$num = $this->db->num_rows($result);
			$j = 0;	
			while ($j < $num) {
				$objBL = $this->db->fetch_object($result);
				$liste_facturedet[] = $objBL;
				$j++;
			}
		}
		//ex  BL: SH1703-0002 du 12/03/2017 (ref Commande: CO1703-0003)
		foreach ($listeBL as $key => $BL) {
			foreach ($liste_facturedet as $facturedet) {
				$search = 'BL: '.$BL->ref;
				if( strpos($facturedet->description,$search)!==false ){
					unset($listeBL[$key]);
				}	
			}
		}


		//injection frais de livraison éventuel
		foreach ($listeBL as $key => $BL) {

			$objexp = new Expedition($this->db);
			$objexp->fetch($BL->rowid);
			$objexp->fetch_delivery_methods();

			$BL->shipping_method_id = $objexp->shipping_method_id;
			$BL->shipping_label = trim($objexp->meths[$BL->shipping_method_id]);
			$BL->shipping_cost = number_format( floatval( @$objexp->array_options['options_fraisexp'] ) ,2);

			/*if ( ($shipping_method_id>0) && ($shipping_cost>0) ) {
			{"rowid":"12","ref":"SH1704-0007","fk_soc":"105","fk_statut":"1","date_creation":"2017-03-29 19:36:36","id_commande":"7","ref_commande":"CO1703-0006","shipping_method_id":"11","shipping_label":"Lettre Max suivi 500g","shipping_cost":"5.50",
			*/
		}		


		//récuperations produits "expedié"
		foreach ($listeBL as $BL) {

			$sql = "
			SELECT ed.rowid as line_id, ed.qty, ed.fk_origin_line, 
			cd.fk_product, cd.tva_tx, cd.price,
			p.ref as product_ref, p.label as product_label, e.perte
			FROM ".MAIN_DB_PREFIX."expeditiondet as ed
			LEFT JOIN ".MAIN_DB_PREFIX."commandedet as cd ON ed.fk_origin_line = cd.rowid			
			LEFT JOIN ".MAIN_DB_PREFIX."product as p ON cd.fk_product = p.rowid
			LEFT JOIN ".MAIN_DB_PREFIX."product_extrafields as e ON e.fk_object = p.rowid
			WHERE ed.fk_expedition = ".$BL->rowid."
			AND cd.product_type = 0	
			ORDER BY cd.rang, ed.fk_origin_line";
			//var_dump($sql);
			//exit();
			$produits_raw = array();

			$result = $this->db->query($sql);
			if ($result) {
				$num = $this->db->num_rows($result);
				$i = 0;
				while ($i < $num) {
					$objproduit = $this->db->fetch_object($result);
					$produits_raw[]=$objproduit;
					$i++;	
				}
			}
			$BL->produits = $produits_raw;
			
			//calcul prix réél expédiés
			$totalHT = 0;
			$totalTVA= 0;
			$totalTTC= 0;
			foreach ($produits_raw as $produit_raw) {
				$totalHT +=  $produit_raw->price * $produit_raw->qty ;
				$totalTVA += ($produit_raw->price * $produit_raw->qty) * ($produit_raw->tva_tx/100) ;
				$totalTTC += ($produit_raw->price * $produit_raw->qty) + ( ($produit_raw->price * $produit_raw->qty) * ($produit_raw->tva_tx/100) ) ;
			}


			// + frais expedition ?
			if ( ($BL->shipping_method_id>0) && ($BL->shipping_cost>0) ) {
				
				$totalHT += $BL->shipping_cost;
				$totalTVA += $BL->shipping_cost*0.2;
				$totalTTC += $BL->shipping_cost*1.2;
			}

			$BL->total = array(
				'totalHT' => price2num($totalHT),
				'totalTVA' =>  price2num($totalTVA),
				'totalTTC' =>  price2num($totalTTC)
			);

		}

		//regrouper par clients
		$listeBLClients = array();
		foreach ($listeBL as $BL) {
			$listeBLClients[$BL->id_client][]=$BL;
		}

		//var_dump($listeBLClients);
		//exit();



		return $listeBLClients;
	}






	/**
	 * facturer tt les clients / filter
	 *
	 * @return false if KO, structure object if OK
	 */
	public function billClient($filters) {

		$listeBLClients = $this->getBLtoBill($filters);
		//var_dump($listeBLClients);
		//var_dump($filters);
		//exit();
	

		//client ciblé ?
		if ( isset($filters['id_client']) && (@$filters['id_client']>0) ) {
			$this->createClientFacture( $filters['id_client'], $listeBLClients[$filters['id_client']], $filters['search_datea']);
		} else {
			//loop clients
			foreach ($listeBLClients as $id_client => $BLs) {
				$this->createClientFacture($id_client, $BLs, $filters['search_datea']);
			}
		}	

		return true;
	}



	/**
	 * facturer un clients / filter
	 *
	 * @return false if KO, structure object if OK
	 */
	public function createClientFacture($id_client, $BLs = null, $datea) {

		global $conf, $user, $langs;

		//var_dump($id_client);
		//var_dump($BLs);
		//exit();

		/*
		array (size=2)
		  0 => 
		    object(stdClass)[126]
		      public 'rowid' => string '6' (length=1)
		      public 'ref' => string 'SH1703-0004' (length=11)
		      public 'fk_soc' => string '105' (length=3)
		      public 'fk_statut' => string '1' (length=1)
		      public 'date_creation' => string '2017-03-25 11:34:41' (length=19)
		      public 'id_commande' => string '6' (length=1)
		      public 'ref_commande' => string 'CO1703-0005' (length=11)
		      public 'id_client' => string '105' (length=3)
		      public 'nom_client' => string 'LAOLINE' (length=7)
		      public 'code_client' => string 'CL00092' (length=7)

      			public 'mode_reglement' => string '2' (length=1)
        		public 'cond_reglement' => string '2' (length=1)		      
		      public 'produits' => 
		        array (size=3)
		          0 => 
		            object(stdClass)[129]
		              public 'line_id' => string '10' (length=2)
		              public 'qty' => string '1' (length=1)
		              public 'fk_origin_line' => string '8' (length=1)
		              public 'fk_product' => string '10' (length=2)
		              public 'tva_tx' => string '20.000' (length=6)
		              public 'price' => string '0.3' (length=3)
		              public 'product_ref' => string 'PR-0004' (length=7)
		              public 'product_label' => string 'Fil 150 OJ 750' (length=14)
		              public 'perte' => null

		      public 'total' => 
		        array (size=3)
		          'totalHT' => string '60.60' (length=5)
		          'totalTVA' => string '12.12' (length=5)
		          'totalTTC' => string '72.72' (length=5)

		*/
		if($datea!=''){
			$date1 = explode('/',$datea);
			$date_facture = strtotime($date1[2].'-'.$date1[1].'-'.$date1[0]);
		} else {
			$date_facture = time();
		}



		$facture = new Facture($this->db);

		$facture->type 			    = 0; //Standard invoice
		$facture->socid 		    = $id_client;
		$facture->date              = $date_facture; //date('Y-m-d H:i:s');
		$facture->note_private      = 'Généré automatiquement';

		$facture->modelpdf          = 'humbert';


		$facture->cond_reglement_id = $BLs[0]->cond_reglement; //2;//1-a reception 2-30jours
		$facture->mode_reglement_id = $BLs[0]->mode_reglement; //7; // cheque
		$facture->remise_absolue    = null;
		$facture->remise_percent    = null;

		$facture->lines		    	= array();

		foreach ($BLs as $i => $BL) {
			$line = new FactureLigne($this->db);

			$date0 = explode(' ',$BL->date_creation);
			$bl_desc='BL: '.$BL->ref.' du '.date('d/m/Y',strtotime($date0[0])).' (ref commande: '.$BL->ref_commande.')';

			$tva_tx = 20;

			$line->fk_product		= null;
			$line->label			= null;
			$line->desc				= $bl_desc;
			$line->libelle			= null;

			$line->subprice			= floatval($BL->total['totalHT']);
			$line->total_ht			= floatval($BL->total['totalHT']);
			$line->total_tva		= floatval($BL->total['totalTVA']);
			$line->total_ttc		= floatval($BL->total['totalTTC']);
			$line->tva_tx			= $tva_tx;
			$line->localtax1_tx		= 0;
			$line->localtax2_tx		= 0;
			$line->qty				= 1;
			$line->fk_remise_except	= null;
			$line->remise_percent	= 0;

			$line->info_bits		= 0;
			$line->product_type		= 0;
			$line->rang				= $i+1;
			$line->special_code		= 0;
			$line->fk_parent_line	= null;
			$line->fk_unit			= null;


			$facture->lines[$i] = $line;
		}

		//var_dump($facture);
		//exit();


		//hmmm
		//$datelim = strtotime("+1 month", strtotime($facture->date));
		$datelim = 0;

		//abracadapouf
		$facture->create($user,0,$datelim);
		//abracadapouf bis
		$facture->validate($user);

		//pdf
		$facture->generateDocument($facture->modelpdf,$langs );

	}




	/**
	 *  Create a document onto disk according to template module.
	 *
	 *	@param	int		$id_client			id_client.
	 *	@return int        					<0 if KO, >0 if OK
	 */
	public function generateDocument($id_client)	{
		//$modele, $outputlangs='', $hidedetails=0, $hidedesc=0, $hideref=0
		global $conf,$langs;

		$langs->load("bills");
		$modele = 'comptepoid';
		$modelpath = "abcvc/core/tpl/";

		$result=$this->commonGenerateDocument($modelpath, $modele, $langs, 0, 0, 0);

		return $result;
	}

















































	/**
	 * recalculer les poids de *toutes* les factures
	 *
	 * @return int New id of clone
	 */
	public function calculALLfactures() {

		//purge les poids de type facture
			$sql = "SELECT";
			$sql.= " t.rowid";
			$sql.= " FROM " . MAIN_DB_PREFIX . "abcvc_comptepoid as t";
			$sql.= " WHERE t.type = 'facture'";
			$resql = $this->db->query($sql);

			$ids_poid = array();
			if ($resql) {
				$num_mvt = $this->db->num_rows($resql);
				$j = 0;	
				while ($j < $num_mvt) {
					$obj = $this->db->fetch_object($resql);					
					$ids_poid[] = $obj->rowid;
					$j++;
				}
			}


			$sql = "DELETE FROM " . MAIN_DB_PREFIX . "abcvc_comptepoid_mvt";
			$sql.= " WHERE id_comptepoid IN (" . implode(',',$ids_poid) ." )";
			$resql = $this->db->query($sql);

			$sql = "DELETE FROM " . MAIN_DB_PREFIX . "abcvc_comptepoid";
			$sql.= " WHERE rowid IN (" . implode(',',$ids_poid) ." )";
			$resql = $this->db->query($sql);


		//recup ttes factures/clients	
			$sql = "SELECT";
			$sql.= " t.rowid, t.fk_soc";
			$sql.= " FROM " . MAIN_DB_PREFIX . "facture as t";
			$sql.= " WHERE t.fk_statut <> 0"; //0->brouillon
			$resql = $this->db->query($sql);

			$factures = array();
			if ($resql) {
				$num_mvt = $this->db->num_rows($resql);
				$j = 0;	
				while ($j < $num_mvt) {
					$obj = $this->db->fetch_object($resql);					
					$factures[$obj->rowid] = $obj->fk_soc;
					$j++;
				}
			}

		//pour chaque facture...	
		foreach ($factures as $id_facture => $id_client) {
			$this->calculPoids($id_client, 'facture', $id_facture);
		}	



		return $factures;	

	}



	/**
	 * insertion mvt mannuel tout client
	 *
	 * @return int New id of clone
	 */
	public function initALLClients() {

		//recup clients mvt existant
			$sql = "SELECT distinct t.fk_soc";
			$sql.= " FROM " . MAIN_DB_PREFIX . "abcvc_comptepoid as t";
			//$sql.= " WHERE t.type = 'facture'";
			$resql = $this->db->query($sql);

			$ids_client = array();
			if ($resql) {
				$num_mvt = $this->db->num_rows($resql);
				$j = 0;	
				while ($j < $num_mvt) {
					$obj = $this->db->fetch_object($resql);					
					$ids_client[] = $obj->fk_soc;
					$j++;
				}
			}
			//var_dump( $ids_client );



			$sql = "SELECT distinct t.rowid";
			$sql.= " FROM " . MAIN_DB_PREFIX . "societe as t";
			$sql.= " WHERE t.client=1 and t.rowid NOT IN (".implode(',',$ids_client).")";
			$resql = $this->db->query($sql);
			$ids_client = array();
			if ($resql) {
				$num_mvt = $this->db->num_rows($resql);
				$j = 0;	
				while ($j < $num_mvt) {
					$obj = $this->db->fetch_object($resql);					
					$ids_client[] = $obj->rowid;
					$j++;
				}
			}
			var_dump( $ids_client ); //128
			//exit();


			$dateinit = date('Y-m-d H:i:s'); 
			foreach ($ids_client as $id_client) {

				$sql ="
				INSERT INTO llx_abcvc_comptepoid (
				ref,
				fk_soc,
				date_creation,
				type,
				ref_type,
				id_type,
				description,
				structure
				) VALUES (
				'a',
				".$id_client.",
				'".$dateinit."',
				'manuel',
				'manuel',
				0,
				'Initialisation compte poids',
				'[]' );";
				$result = $this->db->query($sql);
				$id_poid = $this->db->last_insert_id(MAIN_DB_PREFIX . "abcvc_comptepoid");

				$sql ="
				INSERT INTO llx_abcvc_comptepoid_mvt (
				id_comptepoid,
				fk_soc,
				date_mvt,
				ref_matiere,
				qty)
				VALUES (
				".$id_poid.",
				".$id_client.",
				'".$dateinit."',
				1,
				0);";
				//var_dump($sql);
				$result = $this->db->query($sql);

			}	


		return $factures;	

	}









/*
DROP TABLE IF EXISTS llx_abcvc_comptepoid;
CREATE TABLE llx_abcvc_comptepoid (
  rowid int(11) NOT NULL AUTO_INCREMENT,
  ref varchar(30) NOT NULL,
  ref_ext varchar(255) DEFAULT NULL,

  fk_soc` int(11) NOT NULL,

  `date_creation` datetime DEFAULT NULL,
  `type` varchar(32) DEFAULT NULL,   
  `description` varchar(255) DEFAULT NULL,  

  `structure` mediumtext,

  PRIMARY KEY (`rowid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS `llx_abcvc_comptepoid_mvt`;
CREATE TABLE `llx_abcvc_comptepoid_mvt` (
  `rowid` int(11) NOT NULL AUTO_INCREMENT,
  `ref_cp` int(11) NOT NULL,

  `date_mvt` datetime DEFAULT NULL,
  
  `ref_matiere` int(11) NOT NULL,
  `qty` double DEFAULT NULL,

  PRIMARY KEY (`rowid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

 */






/*

*/


}
