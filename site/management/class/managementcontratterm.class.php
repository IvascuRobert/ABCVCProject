<?php
/* Copyright (C) 2014-2016	Charlie BENKE	<charlie@patas-moinkey.com>
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
require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';

dol_include_once ('/management/class/managementfichinter.class.php');
/**
 *	  \brief	  Class to manage tasks
 *	\remarks	Initialy built by build_class_from_table on 2008-09-10 12:41
 */
class Managementcontratterm extends Contrat
{
	public $element='management_managementcontratterm';
	public $table_element='contrat_term';
	public $fk_element='fk_contrat';

	var $datee;
	var $dateo;
	
	

	// on ajoute le total HT
	var $total_ht;
	var $total_ttc;
	var $total_tva;
	var $total_localtax1;
	var $total_localtax2;

	var $fk_product;
	var $fk_contrat;
	var $lines = array();




	/**
	 *  Constructor
	 *
	 *  @param	  DoliDB		$db	  Database handler
	 */
	function __construct($db)
	{
		$this->db = $db;
		
		// List of language codes for status
		$this->statuts[0]='Draft';
		$this->statuts[1]='Validated';
		$this->statuts[2]='Closed';
		$this->statuts[3]='Billed';
		$this->statuts_short[0]='Draft';
		$this->statuts_short[1]='Validated';
		$this->statuts_short[2]='Closed';
		$this->statuts_short[3]='Billed';
		$this->statuts_logo[0]='statut0';
		$this->statuts_logo[1]='statut1';
		$this->statuts_logo[2]='statut4';
		$this->statuts_logo[3]='statut6';

	}

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
		$sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "fichinter WHERE fk_contrat=" . $this->fk_contrat;
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
		$sql.= ", ".$this->fk_contrat;
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
		$sql.= " FROM ".MAIN_DB_PREFIX."contrat_term WHERE fk_contrat=".$this->fk_contrat;
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

	/**
	 *    Load a contract from database
	 *
	 *    @param	int		$id     Id of contract to load
	 *    @param	string	$ref	Ref
	 *    @return   int     		<0 if KO, id of contract if OK
	 */
	function fetch($id, $ref="", $fk_contract=0)
	{
		$sql = "SELECT ct.rowid, c.rowid as fk_contrat, c.statut, c.ref, c.fk_soc, c.mise_en_service as datemise,";
		$sql.= " c.ref_supplier, c.ref_customer, c.ref_ext,";
		$sql.= " c.fk_user_mise_en_service, c.date_contrat as datecontrat,";
		$sql.= " c.fk_user_author, c.fk_projet,";
		$sql.= " c.fk_commercial_signature, c.fk_commercial_suivi, ct.note,";
		$sql.= " c.note_private, c.note_public, c.model_pdf, c.extraparams,";
		$sql.= " sum(fi.total_ttc) as total_ttc, sum(fi.total_tva) as total_tva,";
		$sql.= " sum(fi.total_localtax1) as total_localtax1, sum(fi.total_localtax2) as total_localtax2,";
		$sql.= " sum(fi.total_ht) as total_ht";

		$sql.= " FROM ".MAIN_DB_PREFIX."contrat as c";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."contrat_term as ct ON c.rowid=ct.fk_contrat";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fichinter  as fi ON ( c.rowid=fi.fk_contrat";
		$sql.= " AND   fi.dateo >= ct.datedeb";
		$sql.= " AND   fi.dateo <= ct.datefin)";

		$sql.= " WHERE  c.entity IN (".getEntity('contract').")";
		if ($fk_contract > 0)
			$sql.= " AND 	c.rowid=".$fk_contract;
		elseif ($ref != "")
			$sql.= " AND 	c.ref='".$ref."'";
		else
			$sql.= " AND 	ct.rowid=".$id;
		
		$sql.= " GROUP BY c.rowid, c.statut, c.ref, c.fk_soc, c.mise_en_service,";
		$sql.= " c.ref_supplier, c.ref_customer, c.ref_ext,";
		$sql.= " c.fk_user_mise_en_service, c.date_contrat,";
		$sql.= " c.fk_user_author, c.fk_projet,";
		$sql.= " c.fk_commercial_signature, c.fk_commercial_suivi, ct.note";
//print $sql;
		dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql)
		{
			$result = $this->db->fetch_array($resql);

			if ($result)
			{
				$this->id						= $result["rowid"];
				$this->fk_contrat				= $result["fk_contrat"];

				$this->ref						= (!isset($result["ref"]) || !$result["ref"]) ? $result["rowid"] : $result["ref"];
				$this->ref_customer				= $result["ref_customer"];
				$this->ref_supplier				= $result["ref_supplier"];
				$this->ref_ext					= $result["ref_ext"];
				$this->statut					= $result["statut"];
				$this->mise_en_service			= $this->db->jdate($result["datemise"]);

				$this->date_contrat				= $this->db->jdate($result["datecontrat"]);
				$this->date_creation				= $this->db->jdate($result["datecontrat"]);

				$this->user_author_id			= $result["fk_user_author"];

				$this->commercial_signature_id	= $result["fk_commercial_signature"];
				$this->commercial_suivi_id		= $result["fk_commercial_suivi"];

				$this->note_private				= $result["note_private"];
				$this->note_public				= $result["note_public"];
				$this->modelpdf					= $result["model_pdf"];

				$this->fk_projet				= $result["fk_projet"]; // deprecated
				$this->fk_project				= $result["fk_projet"];

				$this->socid					= $result["fk_soc"];
				$this->fk_soc					= $result["fk_soc"];

				$this->extraparams				= (array) json_decode($result["extraparams"], true);

				$this->total_ht 		= $result["total_ht"];
				$this->total_ttc 		= $result["total_ttc"];
				$this->total_tva 		= $result["total_tva"];
				$this->total_localtax1 	= $result["total_localtax1"];
				$this->total_localtax2 	= $result["total_localtax2"];


				$this->db->free($resql);

				// Retreive all extrafield for thirdparty
				// fetch optionals attributes and labels
				require_once(DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php');
				$extrafields=new ExtraFields($this->db);
				$extralabels=$extrafields->fetch_name_optionals_label($this->table_element,true);
				$this->fetch_optionals($this->id,$extralabels);

				/*
				 * Lines
				*/

				$this->lines  = array();

				$result=$this->fetch_lines();
				if ($result < 0)
				{
					$this->error=$this->db->lasterror();
					return -3;
				}

				return $this->id;
			}
			else
			{
				dol_syslog(get_class($this)."::Fetch Erreur contrat non trouve");
				$this->error="Contract not found";
				return -2;
			}
		}
		else
		{
			dol_syslog(get_class($this)."::Fetch Erreur lecture contrat");
			$this->error=$this->db->error();
			return -1;
		}
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




	/**
	 *    Load a contract from database
	 *
	 *    @param	int		$id     Id of contract to load
	 *    @param	string	$ref	Ref
	 *    @return   int     		<0 if KO, id of contract if OK
	 */
	function fetchTerm($id)
	{
		$sql = "SELECT c.rowid, c.statut, c.ref, c.fk_soc, mise_en_service as datemise,";
		$sql.= " c.fk_user_mise_en_service, c.date_contrat as datecontrat,";
		$sql.= " c.fk_user_author,";
		$sql.= " c.fk_projet,";
		$sql.= " ct.rowid as contratermid,";
		$sql.= " c.fk_commercial_signature, c.fk_commercial_suivi,";
		$sql.= " c.note_private, c.note_public, c.extraparams";
		$sql.= " FROM ".MAIN_DB_PREFIX."contrat as c, ".MAIN_DB_PREFIX."contrat_term as ct";
		$sql.= " WHERE c.rowid=ct.fk_contrat";
		$sql.= " AND entity IN (".getEntity('contract').")";
		$sql.= " AND ct.rowid=".$id;
		
		
		dol_syslog(get_class($this)."::fetch sql=".$sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql)
		{
			$result = $this->db->fetch_array($resql);

			if ($result)
			{
				$this->id						= $result["contratermid"];
				$this->fk_contrat				= $result["rowid"];

				$this->ref						= (!isset($result["ref"]) || !$result["ref"]) ? $result["rowid"] : $result["ref"];
				$this->statut					= $result["statut"];
				$this->mise_en_service			= $this->db->jdate($result["datemise"]);
				$this->date_contrat				= $this->db->jdate($result["datecontrat"]);

				$this->user_author_id			= $result["fk_user_author"];

				$this->commercial_signature_id	= $result["fk_commercial_signature"];
				$this->commercial_suivi_id		= $result["fk_commercial_suivi"];

				$this->note						= $result["note_private"];	// deprecated
				$this->note_private				= $result["note_private"];
				$this->note_public				= $result["note_public"];

				$this->fk_projet				= $result["fk_projet"]; // deprecated
				$this->fk_project				= $result["fk_projet"];

				$this->socid					= $result["fk_soc"];
				$this->fk_soc					= $result["fk_soc"];

				$this->extraparams				= (array) json_decode($result["extraparams"], true);

				$this->db->free($resql);

				// Retreive all extrafield for thirdparty
				// fetch optionals attributes and labels
				require_once(DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php');
				$extrafields=new ExtraFields($this->db);
				$extralabels=$extrafields->fetch_name_optionals_label($this->table_element,true);
				$this->fetch_optionals($this->id,$extralabels);


				return $this->id;
			}
			else
			{
				dol_syslog(get_class($this)."::Fetch Erreur contrat non trouve");
				$this->error="Contract not found";
				return -2;
			}
		}
		else
		{
			dol_syslog(get_class($this)."::Fetch Erreur lecture contrat");
			$this->error=$this->db->error();
			return -1;
		}

	}

	/**
	 *	Renvoie nom clicable (avec eventuellement le picto)
	 *
	 *	@param	int		$withpicto		0=Pas de picto, 1=Inclut le picto dans le lien, 2=Picto seul
	 *	@param	int		$maxlength		Max length of ref
	 *	@return	string					Chaine avec URL
	 */
	function getNomUrl($withpicto=0,$maxlength=0)
	{
		global $langs;

		$result='';

		$lien = '<a href="'.dol_buildpath('/contrat/card.php?id='.$this->id, 1).'">';
		$lienfin='</a>';

		$picto='contract';

		$label=$langs->trans("ShowContract").': '.$this->ref;

		if ($withpicto) $result.=($lien.img_object($label,$picto).$lienfin);
		if ($withpicto && $withpicto != 2) $result.=' ';
		if ($withpicto != 2) $result.=$lien.($maxlength?dol_trunc($this->ref,$maxlength):$this->ref).$lienfin;
		return $result;
	}

	function fetch_lines()
	{
		global $conf;
		
		// on récupère les lignes d'interventions associé à l'échéance du contrat
		$this->lines=array();
		// pour faire le lien entre la facture et le projet
		
		// pour la mise à jour du trigger
		$this->origine_id=$this->id;
		
		// récupération de la plage de date l'échéance du contrat

		$sql = 'SELECT  l.rowid, l.fk_product, l.fk_parent_line, l.product_type, l.fk_fichinter, l.label as custom_label, l.description, l.price, l.qty, l.duree, l.tva_tx,';
		$sql.= ' l.localtax1_tx, l.localtax2_tx, l.fk_remise_except, l.remise_percent, l.subprice, l.fk_product_fournisseur_price as fk_fournprice, l.buy_price_ht as pa_ht, l.rang, l.info_bits, l.special_code,';
		$sql.= ' l.total_ht, l.total_ttc, l.total_tva, l.total_localtax1, l.total_localtax2, l.date_start, l.date_end,';
		$sql.= ' p.ref as product_ref, p.description as product_desc, p.fk_product_type, p.label as product_label';
		// $sql = 'SELECT rowid, description, duree, date, rang, total_ht, subprice';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'fichinter as fi';
		$sql.= ' , '.MAIN_DB_PREFIX.'fichinterdet as l';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON (p.rowid = l.fk_product)';
		$sql.= ' WHERE fi.fk_contrat ='.$this->fk_contrat;
		$sql.= ' AND l.fk_fichinter = fi.rowid';
		$sql.= ' AND fi.fk_statut = 4'; // que le détail des interventions cloturés
		if ($conf->global->FICHINTER_ONLY_REPORT_BILLED == 1)
			$sql.= ' AND l.fk_product > 0'; 
		
		dol_syslog(get_class($this)."::fetch_lines sql=".$sql);
		$resql=$this->db->query($sql);
		if ($resql)
		{
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num)
			{
				$objp = $this->db->fetch_object($resql);

				$line = new ManagementFichinterLigne($this->db);
				$line->id = $objp->rowid;
				$line->desc = $objp->description;
				if ($objp->duree)
					$line->qty = $objp->duree/3600;
				else
					$line->qty = $objp->qty;
				$line->subprice= $objp->subprice;
				$line->total_ht= $objp->total_ht;
				$line->total_ttc= $objp->total_ttc;
				$line->total_tva= $objp->total_tva;
				$line->fk_product       = $objp->fk_product;
				$line->tva_tx           = ($objp->tva_tx?$objp->tva_tx:0);
				$line->localtax1_tx     = ($objp->localtax1_tx?$objp->localtax1_tx:0);
				$line->localtax2_tx     = ($objp->localtax2_tx?$objp->localtax2_tx:0);;

				$line->ref				= $objp->product_ref;
				$line->product_label	= $objp->product_label;
				$line->product_desc		= $objp->product_desc;
				$marginInfos			= getMarginInfos($objp->subprice, $objp->remise_percent, ($objp->tva_tx?$objp->tva_tx:0), $objp->localtax1_tx, $objp->localtax2_tx, $line->fk_fournprice, $objp->pa_ht);
				$line->pa_ht 			= $marginInfos[0];
				$line->marge_tx			= $marginInfos[1];
				$line->marque_tx		= $marginInfos[2];

				$line->product_type= $objp->product_type;
				
				$line->datei	= $this->db->jdate($objp->date);
				$line->date_start = $this->db->jdate($objp->date_start);
				$line->date_end = $this->db->jdate($objp->date_end);
				$line->rang	= $objp->rang;
				//$line->product_type = 1;

				$this->lines[$i] = $line;

				$i++;
			}
			$this->db->free($resql);
			return $this->lines;
		}
		else
		{
			$this->error=$this->db->error();
			return -1;
		}

	}

	/**
	 *	Returns the label status
	 *
	 *	@param      int		$mode       0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto
	 *	@return     string      		Label
	 */
	function getLibStatut($mode=0)
	{
		return $this->LibStatut($this->statut,$mode);
	}

	/**
	 *	Returns the label of a statut
	 *
	 *	@param      int		$statut     id statut
	 *	@param      int		$mode       0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto
	 *	@return     string      		Label
	 */
	function LibStatut($statut, $mode=0)
	{
		global $langs;

		if ($mode == 0)	return $langs->trans($this->statuts[$statut]);
		if ($mode == 1)	return $langs->trans($this->statuts_short[$statut]);
		if ($mode == 2)	return img_picto($langs->trans($this->statuts_short[$statut]),$this->statuts_logo[$statut]).' '.$langs->trans($this->statuts_short[$statut]);
		if ($mode == 3)	return img_picto($langs->trans($this->statuts_short[$statut]),$this->statuts_logo[$statut]);
		if ($mode == 4)	return img_picto($langs->trans($this->statuts_short[$statut]),$this->statuts_logo[$statut]).' '.$langs->trans($this->statuts[$statut]);
		if ($mode == 5)	return '<span class="hideonsmartphone">'.$langs->trans($this->statuts_short[$statut]).' </span>'.img_picto($langs->trans($this->statuts_short[$statut]),$this->statuts_logo[$statut]);
	}
}
?>