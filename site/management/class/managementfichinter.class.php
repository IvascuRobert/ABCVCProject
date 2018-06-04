<?php
/* Copyright (C) 2002-2003	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2010	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012	Regis Houssin			<regis@dolibarr.fr>
 * Copyright (C) 2011		Juanjo Menent			<jmenent@2byte.es>
 * Copyright (C) 2012-2016	Charlie BENKE			<charlie@patas-monkey.com>
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
 * 	\file       htdocs/fichinter/class/fichinter.class.php
 * 	\ingroup    ficheinter
 * 	\brief      Fichier de la classe des gestion des fiches interventions
 */
require_once DOL_DOCUMENT_ROOT .'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT .'/margin/lib/margins.lib.php';
require_once DOL_DOCUMENT_ROOT .'/fichinter/class/fichinter.class.php';

/**
 *	Classe des gestion des fiches interventions
 */
class Managementfichinter extends Fichinter
{
	public $element='management_managementfichinter';
	var $datee;
	var $dateo;

	// on ajoute le total HT
	var $total_ht;
	var $total_ttc;
	var $total_tva;
	var $total_localtax1;
	var $total_localtax2;

	// pour gerer les deux type d'infos sur l'agenda
	var $type_code;

	/**
	 *	Constructor
	 *
	 *  @param	DoliDB	$db		Database handler
	 */
	function __construct($db)
	{
		$this->db = $db;
		$this->products = array();
		$this->fk_project = 0;
		$this->fk_contrat = 0;
		$this->statut = 0;

		//status dans l'ordre de l'intervention
		$this->statuts[0]='Draft';
		$this->statuts[1]='Validated';
		$this->statuts[4]='StatusInterPartialClosed';
		$this->statuts[3]='StatusInterClosed';
		$this->statuts[2]='StatusInterInvoiced';
		$this->statuts[5]='StatusInterClosedNotToBill';

		$this->statuts_short[0]='Draft';
		$this->statuts_short[1]='Validated';
		$this->statuts_short[4]='StatusInterPartialClosed';
		$this->statuts_short[3]='StatusInterClosed';
		$this->statuts_short[2]='StatusInterInvoiced';
		$this->statuts_short[5]='StatusInterClosedNotToBill';

		$this->statuts_logo[0]='statut0';
		$this->statuts_logo[1]='statut1';
		$this->statuts_logo[4]='statut3';
		$this->statuts_logo[3]='statut4';
		$this->statuts_logo[2]='statut6';
		$this->statuts_logo[5]='statut5';

		$this->author = new stdClass();
		$this->usermod = new stdClass();
		$this->usertodo = new stdClass();
		$this->userdone = new stdClass();
		$this->societe = new stdClass();
		$this->contact = new stdClass();
	}

	/**
	 *	Returns the label of a statut
	 *
	 *	@param      int		$statut     id statut
	 *	@param      int		$mode       0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto
	 *	@return     string      		Label
	 */
	function LibStatut($statut,$mode=0)
	{

		global $langs;

		if ($mode == 0)
			return $langs->trans($this->statuts[$statut]);

		if ($mode == 1)
			return $langs->trans($this->statuts_short[$statut]);

		if ($mode == 2)
			return img_picto($langs->trans($this->statuts_short[$statut]), $this->statuts_logo[$statut]).' '.$langs->trans($this->statuts_short[$statut]);

		if ($mode == 3)
			return img_picto($langs->trans($this->statuts_short[$statut]), $this->statuts_logo[$statut]);

		if ($mode == 4)
			return img_picto($langs->trans($this->statuts_short[$statut]),$this->statuts_logo[$statut]).' '.$langs->trans($this->statuts[$statut]);

		if ($mode == 5)
			return '<span class="hideonsmartphone">'.$langs->trans($this->statuts_short[$statut]).' </span>'.img_picto($langs->trans($this->statuts_short[$statut]),$this->statuts_logo[$statut]);

	}


	/**
	 *	Create an intervention into data base
	 *
	 *  @param		User	$user 		Objet user that make creation
     *	@param		int		$notrigger	Disable all triggers
	 *	@return		int		<0 if KO, >0 if OK
	 */
	function create($user, $notrigger=0)
	{
		global $conf, $user, $langs;

		dol_syslog(get_class($this)."::create ref=".$this->ref);

		// Check parameters
		if (! empty($this->ref))	// We check that ref is not already used
		{
			$result=self::isExistingObject($this->element, 0, $this->ref);	// Check ref is not yet used
			if ($result > 0)
			{
				$this->error='ErrorRefAlreadyExists';
				dol_syslog(get_class($this)."::create ".$this->error,LOG_WARNING);
				$this->db->rollback();
				return -1;
			}
		}
		if (! is_numeric($this->duree)) $this->duree = 0;

		if ($this->socid <= 0)
		{
			$this->error='ErrorBadParameterForFunc';
			dol_syslog(get_class($this)."::create ".$this->error,LOG_ERR);
			return -1;
		}

		$soc = new Societe($this->db);
		$result=$soc->fetch($this->socid);

		$now=dol_now();

		$this->db->begin();

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."fichinter (";
		$sql.= "fk_soc";
		$sql.= ", datec";
		$sql.= ", ref";
		$sql.= ", entity";
		$sql.= ", fk_user_author";
		$sql.= ", description";
		$sql.= ", model_pdf";
		$sql.= ", datee";
		$sql.= ", dateo";
		$sql.= ", fulldayevent";
		$sql.= ", fk_projet";
		$sql.= ", fk_contrat";
		$sql.= ", fk_statut";
		$sql.= ", note_private";
		$sql.= ", note_public";
		$sql.= ") ";
		$sql.= " VALUES (";
		$sql.= $this->socid;
		$sql.= ", '".$this->db->idate($now)."'";
		$sql.= ", '".$this->ref."'";
		$sql.= ", ".$conf->entity;
		$sql.= ", ".$user->id;
		$sql.= ", ".($this->description?"'".$this->db->escape($this->description)."'":"null");
		$sql.= ", '".$this->modelpdf."'";
		$sql.= ", ".($this->datee!=''?"'".$this->db->idate($this->datee)."'":'null');
		$sql.= ", ".($this->dateo!=''?"'".$this->db->idate($this->dateo)."'":'null');
		$sql.= ", ".($this->fulldayevent!=''?"'".$this->fulldayevent."'":'0');
		$sql.= ", ".($this->fk_project ? $this->fk_project : 0);
		$sql.= ", ".($this->fk_contrat ? $this->fk_contrat : 0);
		$sql.= ", ".$this->statut;
		$sql.= ", ".($this->note_private?"'".$this->db->escape($this->note_private)."'":"null");
		$sql.= ", ".($this->note_public?"'".$this->db->escape($this->note_public)."'":"null");
		$sql.= ")";

		dol_syslog(get_class($this)."::create sql=".$sql, LOG_DEBUG);
		$result=$this->db->query($sql);
		if ($result)
		{
			$this->id=$this->db->last_insert_id(MAIN_DB_PREFIX."fichinter");

			if ($this->id)
			{
				$this->ref='(PROV'.$this->id.')';
				$sql = 'UPDATE '.MAIN_DB_PREFIX."fichinter SET ref='".$this->ref."' WHERE rowid=".$this->id;

				dol_syslog(get_class($this)."::create sql=".$sql);
				$resql=$this->db->query($sql);
				if (! $resql) $error++;
			}
			// Add linked object
			if (! $error && $this->origin && $this->origin_id)
			{
				$ret = $this->add_object_linked();
				if (! $ret)	dol_print_error($this->db);
			}


            if (! $notrigger)
            {
			// Appel des triggers
			include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
			$interface=new Interfaces($this->db);
				$result=$interface->run_triggers('FICHINTER_CREATE',$this,$user,$langs,$conf);
			if ($result < 0) {
				$error++; $this->errors=$interface->errors;
			}
            }

			if (! $error)
			{
				$this->db->commit();
				return $this->id;
			}
			else
			{
				$this->db->rollback();
				$this->error=join(',',$this->errors);
				dol_syslog(get_class($this)."::create ".$this->error,LOG_ERR);
				return -1;
			}
		}
		else
		{
			$this->error=$this->db->error();
			dol_syslog(get_class($this)."::create ".$this->error, LOG_ERR);
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 *	Fetch a intervention
	 *
	 *	@param		int		$rowid		Id of intervention
	 *	@param		string	$ref		Ref of intervention
	 *	@return		int					<0 if KO, >0 if OK
	 */
	function fetch($rowid,$ref='')
	{
		$sql = "SELECT f.rowid, f.ref, f.description, f.fk_soc, f.fk_statut,";
		$sql.= " fk_contrat, f.datec, f.datee, f.dateo, f.datei,";
		$sql.= " f.date_valid as datev, f.total_ttc, f.total_tva, f.total_localtax1, f.total_localtax2,";
		$sql.= " f.tms as datem, f.fulldayevent, f.total_ht,";
		$sql.= " f.duree, f.fk_projet, f.note_public, f.note_private, f.model_pdf, f.extraparams";
		$sql.= " FROM ".MAIN_DB_PREFIX."fichinter as f";
		if ($ref) $sql.= " WHERE f.ref='".$this->db->escape($ref)."'";
		else $sql.= " WHERE f.rowid=".$rowid;

		dol_syslog(get_class($this)."::fetch sql=".$sql, LOG_DEBUG);
		$resql=$this->db->query($sql);
		if ($resql)
		{
			if ($this->db->num_rows($resql))
			{
				$obj = $this->db->fetch_object($resql);

				$this->id           = $obj->rowid;
				$this->ref          = $obj->ref;
				$this->description  = $obj->description;
				$this->socid        = $obj->fk_soc;
				$this->statut       = $obj->fk_statut;
				$this->duree        = $obj->duree;
				$this->dateo		= $this->db->jdate($obj->dateo);
				$this->datee		= $this->db->jdate($obj->datee);
				$this->datec        = $this->db->jdate($obj->datec);
				$this->datev        = $this->db->jdate($obj->datev);
				$this->datem        = $this->db->jdate($obj->datem);
				$this->datei        = $this->db->jdate($obj->datei);
				$this->fk_project   = $obj->fk_projet;
				$this->fk_contrat   = $obj->fk_contrat;
				$this->note_public  = $obj->note_public;
				$this->note_private = $obj->note_private;
				$this->modelpdf     = $obj->model_pdf;
				$this->fulldayevent = $obj->fulldayevent;
				$this->total_ht 		= $obj->total_ht;
				$this->total_ttc 		= $obj->total_ttc;
				$this->total_tva 		= $obj->total_tva;
				$this->total_localtax1 	= $obj->total_localtax1;
				$this->total_localtax2 	= $obj->total_localtax2;

				$this->extraparams	= (array) json_decode($obj->extraparams, true);

				if ($this->statut == 0) $this->brouillon = 1;

				require_once(DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php');
				$extrafields=new ExtraFields($this->db);
				$extralabels=$extrafields->fetch_name_optionals_label($this->table_element,true);
				$this->fetch_optionals($this->id,$extralabels);

				/*
				 * Lines
				*/
				$result=$this->fetch_lines();
				if ($result < 0)
					return -3;

				$this->db->free($resql);
				return 1;
			}
		}
		else
		{
			$this->error=$this->db->error();
			dol_syslog(get_class($this)."::fetch ".$this->error,LOG_ERR);
			return -1;
		}
	}

	/**
	 * 	Set intervention as closed
	 *
	 *  @return int     <0 si ko, >0 si ok
	 */
	function setClosed($status=3)
	{
		global $conf;

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'fichinter SET fk_statut = '.$status;
		$sql.= ' , datei = now()';
		$sql.= ' WHERE rowid = '.$this->id;
		$sql.= " AND entity = ".$conf->entity;
		$sql.= " AND fk_statut >= 1";

		if ($this->db->query($sql))
			return 1;
		else {
			dol_print_error($this->db);
			return -1;
		}
	}

	function selectStatut($statustohow, $selected='', $short=0, $hmlname='status')
	{
		global $langs;
		
		print '<select class="flat" name="'.$hmlname.'">';
		print '<option value="-1" '.($selected == ''?' selected="selected"':'').'>&nbsp;</option>';

		foreach($statustohow as $key)
		{
			print '<option value="'.$key.'"'.(($selected == $key && $selected !='') ?' selected="selected"':'').'>';
			print $this->LibStatut($key,$short);
			print '</option>';
		}
		print '</select>';
	}

	/**
	 *	Defines a delivery date of intervention
	 *
	 *	@param      User	$user				Object user who define
	 *	@param      date	$date_delivery   	date of delivery
	 *	@return     int							<0 if ko, >0 if ok
	 */
	function set_datee($user, $datee)
	{
		global $conf;

		if ($user->rights->ficheinter->creer)
		{
			$sql = "UPDATE ".MAIN_DB_PREFIX."fichinter ";
			$sql.= " SET datee = ".($datee?$this->db->idate($datee): 'null');
			$sql.= " WHERE rowid = ".$this->id;
			$sql.= " AND entity = ".$conf->entity;

			if ($this->db->query($sql))
			{
				$this->datee= $datee;
				return 1;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog("Fichinter::set_datee Erreur SQL");
				return -1;
			}
		}
	}
	
	function set_dateo($user, $dateo)
	{
		global $conf;

		if ($user->rights->ficheinter->creer)
		{
			$sql = "UPDATE ".MAIN_DB_PREFIX."fichinter ";
			$sql.= " SET dateo = ".($dateo?$this->db->idate($dateo): 'null');
			$sql.= " WHERE rowid = ".$this->id;
			$sql.= " AND entity = ".$conf->entity;

			if ($this->db->query($sql))
			{
				$this->dateo = $dateo;
				return 1;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog("Fichinter::set_dateo Erreur SQL");
				return -1;
			}
		}
	}
	
	function set_fullday($user, $fullday)
	{
		global $conf;

		if ($user->rights->ficheinter->creer)
		{
			if (!$fullday) $fullday=0;
			$sql = "UPDATE ".MAIN_DB_PREFIX."fichinter ";
			$sql.= " SET fulldayevent = ".$fullday;
			$sql.= " WHERE rowid = ".$this->id;
			$sql.= " AND entity = ".$conf->entity;
			$sql.= " AND fk_statut = 0";

			if ($this->db->query($sql))
			{
				$this->fulldayevent= $fullday;
				return 1;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog("Fichinter::set_datee Erreur SQL");
				return -1;
			}
		}
	}
	
	/**
	 *	Define the label of the intervention
	 *
	 *	@param      User	$user			Object user who modify
	 *	@param      string	$description    description
	 *	@return     int						<0 if ko, >0 if ok
	 */
	function set_description($user, $description)
	{
		global $conf;

		if ($user->rights->ficheinter->creer)
		{
			$sql = "UPDATE ".MAIN_DB_PREFIX."fichinter ";
			$sql.= " SET description = '".$this->db->escape($description)."'";
			$sql.= " WHERE rowid = ".$this->id;
			$sql.= " AND entity = ".$conf->entity;
			//$sql.= " AND fk_statut = 0";

			if ($this->db->query($sql))
			{
				$this->description = $description;
				return 1;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog("Fichinter::set_description Erreur SQL");
				return -1;
			}
		}
	}

	/**
	 *	Adding a line of intervention into data base
	 *
	 *  @param      user	$user					User that do the action
	 *	@param    	int		$fichinterid			Id of intervention
	 *	@param    	string	$desc					Line description
	 *	@param      date	$date_intervention  	Intervention date
	 *	@param      int		$duration            	Intervention duration
	 *	@return    	int             				>0 if ok, <0 if ko
	 */
	function addline($user,$fichinterid, $desc, $date_intervention, $duration, $subprice=0, $total_ht=0)
	{
		dol_syslog("Fichinter::Addline $fichinterid, $desc, $date_intervention, $duration, $subprice, $total_ht ");

		if ($this->statut == 0)
		{
			$this->db->begin();

			// Insertion ligne
			$line=new ManagementFichinterLigne($this->db);

			$line->fk_fichinter = $fichinterid;
			$line->desc         = $desc;
			$line->datei        = $date_intervention;
			$line->duration     = $duration;
			$line->qty		    = $duration;
			$line->subprice     = $subprice;
			if ($subprice!=0 && $duration!=0)
				$line->total_ht     = $subprice*$duration;

			$result=$line->insert($user);
			if ($result > 0)
			{
				$this->db->commit();
				return 1;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog("Error sql=$sql, error=".$this->error, LOG_ERR);

				$this->db->rollback();
				return -1;
			}
		}
	}

	/**
	 *	Add an order line into database (linked to product/service or not)
	 *
	 *	@param      int				$fichinterid      	Id of line
	 *	@param      string			$desc            	Description of line
	 *	@param      double			$pu_ht    	        Unit price (without tax)
	 *	@param      double			$qty             	Quantite
	 *	@param      double			$txtva           	Taux de tva force, sinon -1
	 *	@param      double			$txlocaltax1		Local tax 1 rate
	 *	@param      double			$txlocaltax2		Local tax 2 rate
	 *	@param      int				$fk_product      	Id du produit/service predefini
	 *	@param      double			$remise_percent  	Pourcentage de remise de la ligne
	 *	@param      int				$info_bits			Bits de type de lignes
	 *	@param      int				$fk_remise_except	Id remise
	 *	@param      string			$price_base_type	HT or TTC
	 *	@param      double			$pu_ttc    		    Prix unitaire TTC
	 *	@param      timestamp		$date_start       	Start date of the line - Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
	 *	@param      timestamp		$date_end         	End date of the line - Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
	 *	@param      int				$type				Type of line (0=product, 1=service)
	 *	@param      int				$rang             	Position of line
	 *	@param		int				$special_code		Special code (also used by externals modules!)
	 *	@param		int				$fk_parent_line		Parent line
	 *  @param		int				$fk_fournprice		Id supplier price
	 *  @param		int				$pa_ht				Buying price (without tax)
	 *  @param		string			$label				Label
	 *	@return     int             					>0 if OK, <0 if KO
	 *
	 *	@see        add_product
	 *
	 *	Les parametres sont deja cense etre juste et avec valeurs finales a l'appel
	 *	de cette methode. Aussi, pour le taux tva, il doit deja avoir ete defini
	 *	par l'appelant par la methode get_default_tva(societe_vendeuse,societe_acheteuse,produit)
	 *	et le desc doit deja avoir la bonne valeur (a l'appelant de gerer le multilangue)
	 */
	function addlineRapport($fichinterid, $desc, $pu_ht, $qty, $txtva, $txlocaltax1=0, $txlocaltax2=0, $fk_product=0, $remise_percent=0, $info_bits=0, $fk_remise_except=0, $price_base_type='HT', $pu_ttc=0, $date_start='', $date_end='', $type=0, $rang=-1, $special_code=0, $fk_parent_line=0, $fk_fournprice=null, $pa_ht=0, $label='')
	{
		dol_syslog(get_class($this)."::addline fichinterid=$fichinterid, desc=$desc, pu_ht=$pu_ht, qty=$qty, txtva=$txtva, fk_product=$fk_product, remise_percent=$remise_percent, info_bits=$info_bits, fk_remise_except=$fk_remise_except, price_base_type=$price_base_type, pu_ttc=$pu_ttc, date_start=$date_start, date_end=$date_end, type=$type", LOG_DEBUG);
		//print"::addline fichinterid=$fichinterid, desc=$desc, pu_ht=$pu_ht, qty=$qty, txtva=$txtva, fk_product=$fk_product, remise_percent=$remise_percent, info_bits=$info_bits, fk_remise_except=$fk_remise_except, price_base_type=$price_base_type, pu_ttc=$pu_ttc, date_start=$date_start, date_end=$date_end, type=$type";
		include_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
		
		// Clean parameters
		if (empty($remise_percent)) $remise_percent=0;
		if (empty($qty)) $qty=0;
		if (empty($info_bits)) $info_bits=0;
		if (empty($rang)) $rang=0;
		if (empty($txtva)) $txtva=0;
		if (empty($txlocaltax1)) $txlocaltax1=0;
		if (empty($txlocaltax2)) $txlocaltax2=0;
		if (empty($fk_parent_line) || $fk_parent_line < 0) $fk_parent_line=0;
		
		$remise_percent=price2num($remise_percent);
		$qty=price2num($qty);
		$pu_ht=price2num($pu_ht);
		$pu_ttc=price2num($pu_ttc);
		$pa_ht=price2num($pa_ht);
		$txtva = price2num($txtva);
		$txlocaltax1 = price2num($txlocaltax1);
		$txlocaltax2 = price2num($txlocaltax2);
		if ($price_base_type=='HT')
		{
			$pu=$pu_ht;
		}
		else
		{
			$pu=$pu_ttc;
		}
		$label=trim($label);
		$desc=trim($desc);
	
		// Check parameters
		if ($type < 0) return -1;
		
		// lance l'ajout
		$this->db->begin();
	
		// Calcul du total TTC et de la TVA pour la ligne a partir de
		// qty, pu, remise_percent et txtva
		// TRES IMPORTANT: C'est au moment de l'insertion ligne qu'on doit stocker
		// la part ht, tva et ttc, et ce au niveau de la ligne qui a son propre taux tva.
		$tabprice = calcul_price_total($qty, $pu, $remise_percent, $txtva, $txlocaltax1, $txlocaltax2, 0, $price_base_type, $info_bits, $type);
		//var_dump($tabprice);
		$total_ht  = $tabprice[0];
		$total_tva = $tabprice[1];
		$total_ttc = $tabprice[2];
		$total_localtax1 = $tabprice[9];
		$total_localtax2 = $tabprice[10];
		
		// Rang to use
		$rangtouse = $rang;
		if ($rangtouse == -1)
		{
			$rangmax = $this->line_max($fk_parent_line);
			$rangtouse = $rangmax + 1;
		}
	
		// TODO A virer
		// Anciens indicateurs: $price, $remise (a ne plus utiliser)
		$price = $pu;
		$remise = 0;
		if ($remise_percent > 0)
		{
			$remise = round(($pu * $remise_percent / 100), 2);
			$price = $pu - $remise;
		}
	
		// Insert line
		$this->line=new ManagementFichinterLigne($this->db);

		$this->line->fk_fichinter=$fichinterid;
		$this->line->label=$label;
		$this->line->desc=$desc;
		$this->line->qty=$qty;
		$this->line->tva_tx=$txtva;
		$this->line->localtax1_tx=$txlocaltax1;
		$this->line->localtax2_tx=$txlocaltax2;
		$this->line->fk_product=$fk_product;
		$this->line->fk_remise_except=$fk_remise_except;
		$this->line->remise_percent=$remise_percent;
		$this->line->subprice=$pu_ht;
		$this->line->rang=$rangtouse;
		$this->line->info_bits=$info_bits;
		$this->line->total_ht=$total_ht;
		$this->line->total_tva=$total_tva;
		$this->line->total_localtax1=$total_localtax1;
		$this->line->total_localtax2=$total_localtax2;
		$this->line->total_ttc=$total_ttc;
		$this->line->product_type=$type;
		$this->line->special_code=$special_code;
		$this->line->fk_parent_line=$fk_parent_line;

		$this->line->date_start=$date_start;
		$this->line->date_end=$date_end;

		// infos marge
		$this->line->fk_fournprice = $fk_fournprice;
		$this->line->pa_ht = $pa_ht;

		// TODO Ne plus utiliser
		$this->line->price=$price;
		$this->line->remise=$remise;

		$result=$this->line->insertRapport();
		if ($result > 0)
		{
			// Reorder if child line
			if (! empty($fk_parent_line)) $this->line_order(true,'DESC');

			$this->db->commit();
			return $this->line->rowid;
		}
		else
		{
			$this->error=$this->line->error;
			dol_syslog(get_class($this)."::addline error=".$this->error, LOG_ERR);
			$this->db->rollback();
			return -2;
		}
	}

	/**
	 *	Load array lines
	 *
	 *	@return		int		<0 if Ko,	>0 if OK
	 */
	function fetch_lines($sall=0)
	{
		global $conf;
		$sql = 'SELECT l.rowid, l.fk_product, l.fk_parent_line, l.product_type, l.fk_fichinter, l.label as custom_label, l.description, l.price, l.qty, l.duree, l.tva_tx,';
		$sql.= ' l.localtax1_tx, l.localtax2_tx, l.fk_remise_except, l.remise_percent, l.subprice, l.fk_product_fournisseur_price as fk_fournprice, l.buy_price_ht as pa_ht, l.rang, l.info_bits, l.special_code,';
		$sql.= ' l.total_ht, l.total_ttc, l.total_tva, l.total_localtax1, l.total_localtax2, l.date_start, l.date_end, l.date as datei, l.fk_unit,';
		$sql.= ' p.ref as product_ref, p.description as product_desc, p.fk_product_type, p.label as product_label';
		// $sql = 'SELECT rowid, description, duree, date, rang, total_ht, subprice';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'fichinterdet l';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON (p.rowid = l.fk_product)';
		$sql.= ' WHERE fk_fichinter = '.$this->id;
		// on ne prend que les lignes du rapport d'inter
		if ($conf->global->FICHINTER_ONLY_REPORT_BILLED == 1 && $sall==0)
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
				$line->fk_fichinter		= $this->id;
				$line->id 				= $objp->rowid;
				$line->rowid 			= $objp->rowid;
				$line->desc 			= $objp->description;
				if ($objp->duree)
					$line->qty 			= $objp->duree/3600;
				else
					$line->qty 			= $objp->qty;
				$line->duree 			= $objp->duree;
				if ($objp->subprice)
					$line->subprice		= $objp->subprice;
				else 
					$line->subprice		= 0;

				$line->total_ht			= $objp->total_ht;
				$line->total_ttc		= $objp->total_ttc;
				$line->total_tva		= $objp->total_tva;
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

				$line->product_type		= $objp->product_type;

				$line->datei			= $this->db->jdate($objp->datei);
				$line->date_start 		= $this->db->jdate($objp->date_start);
				$line->date_end 		= $this->db->jdate($objp->date_end);
				$line->rang				= $objp->rang;
				$line->fk_unit			= $objp->fk_unit;
				//$line->product_type = 1;

				$this->lines[$i] = $line;
				$i++;
			}
			$this->db->free($resql);

			return 1;
		}
		else
		{
			$this->error=$this->db->error();
			return -1;
		}
	}
	/**
	 * 	Return an array of order lines
	 *
	 * @return	array		Lines of order
	 */
	function getLinesArray()
	{
	    $lines = array();

		$sql = 'SELECT l.rowid, l.fk_product, l.product_type, l.label as custom_label, l.description, l.price, l.qty, l.tva_tx, ';
		$sql.= ' l.fk_remise_except, l.remise_percent, l.subprice, l.info_bits, l.rang, l.special_code, l.fk_parent_line,';
		$sql.= ' l.total_ht, l.total_tva, l.total_ttc, l.fk_product_fournisseur_price as fk_fournprice, l.buy_price_ht as pa_ht, l.localtax1_tx, l.localtax2_tx,';
		$sql.= ' l.date_start, l.date_end,';
		$sql.= ' p.label as product_label, p.ref, p.fk_product_type, p.rowid as prodid, ';
		$sql.= ' p.description as product_desc, p.stock as stock_reel';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'fichinterdet as l';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON l.fk_product=p.rowid';
		$sql.= ' WHERE l.fk_fichinter = '.$this->id;
		$sql.= ' AND l.fk_product is not null';
		$sql.= ' AND date is null ';
		$sql.= ' ORDER BY l.rang ASC, l.rowid';
		
		$resql = $this->db->query($sql);
		if ($resql)
		{
		    $num = $this->db->num_rows($resql);
		    $i = 0;
		    while ($i < $num)
		    {
		        $obj = $this->db->fetch_object($resql);
				$this->lines[$i]=new ManagementFichinterLigne($this->db);
				
				$this->lines[$i]->id				= $obj->rowid;
				$this->lines[$i]->label 			= $obj->custom_label;
				$this->lines[$i]->description 		= $obj->description;
				$this->lines[$i]->fk_product		= $obj->fk_product;
				$this->lines[$i]->ref				= $obj->ref;
				$this->lines[$i]->product_label		= $obj->product_label;
				$this->lines[$i]->product_desc		= $obj->product_desc;
				$this->lines[$i]->fk_product_type	= $obj->fk_product_type;
				$this->lines[$i]->product_type		= $obj->product_type;
				$this->lines[$i]->qty				= $obj->qty;
				$this->lines[$i]->subprice			= $obj->subprice;
				$this->lines[$i]->fk_remise_except 	= $obj->fk_remise_except;
				$this->lines[$i]->remise_percent	= $obj->remise_percent;
				$this->lines[$i]->tva_tx			= $obj->tva_tx;
				$this->lines[$i]->info_bits			= $obj->info_bits;
				$this->lines[$i]->total_ht			= $obj->total_ht;
				$this->lines[$i]->total_tva			= $obj->total_tva;
				$this->lines[$i]->total_ttc			= $obj->total_ttc;
				$this->lines[$i]->fk_parent_line	= $obj->fk_parent_line;
				$this->lines[$i]->special_code		= $obj->special_code;
				$this->lines[$i]->stock				= $obj->stock_reel;
				$this->lines[$i]->rang				= $obj->rang;
				$this->lines[$i]->date_start		= $this->db->jdate($obj->date_start);
				$this->lines[$i]->date_end			= $this->db->jdate($obj->date_end);
				$this->lines[$i]->fk_fournprice		= $obj->fk_fournprice;
				$marginInfos						= getMarginInfos($obj->subprice, $obj->remise_percent, $obj->tva_tx, $obj->localtax1_tx, $obj->localtax2_tx, $this->lines[$i]->fk_fournprice, $obj->pa_ht);
				$this->lines[$i]->pa_ht				= $marginInfos[0];
				$this->lines[$i]->marge_tx			= $marginInfos[1];
				$this->lines[$i]->marque_tx			= $marginInfos[2];

				$i++;
			}
			$this->db->free($resql);
			return 1;
		}
		else
		{
			$this->error=$this->db->error();
			dol_syslog("Error sql=$sql, error=".$this->error,LOG_ERR);
			return -1;
		}
	}
	

	/**
	 *  Update a line in database
	 *
	 *  @param    	int				$rowid            	Id of line to update
	 *  @param    	string			$desc             	Description de la ligne
	 *  @param    	double			$pu               	Prix unitaire
	 *  @param    	double			$qty              	Quantity
	 *  @param    	double			$remise_percent   	Pourcentage de remise de la ligne
	 *  @param    	double			$txtva           	Taux TVA
	 * 	@param		double			$txlocaltax1		Local tax 1 rate
	 *  @param		double			$txlocaltax2		Local tax 2 rate
	 *  @param    	string			$price_base_type	HT or TTC
	 *  @param    	int				$info_bits        	Miscellaneous informations on line
	 *  @param    	timestamp		$date_start        	Start date of the line
	 *  @param    	timestamp		$date_end          	End date of the line
	 * 	@param		int				$type				Type of line (0=product, 1=service)
	 * 	@param		int				$fk_parent_line		Id of parent line (0 in most cases, used by modules adding sublevels into lines).
	 * 	@param		int				$skip_update_total	Keep fields total_xxx to 0 (used for special lines by some modules)
	 *  @param		int				$fk_fournprice		Id of origin supplier price
	 *  @param		int				$pa_ht				Price (without tax) of product when it was bought
	 *  @param		string			$label				Label
	 *  @param		int				$special_code		Special code (also used by externals modules!)
	 *  @return   	int              					< 0 if KO, > 0 if OK
	 */

	function updateline($rowid, $desc, $pu, $qty, $remise_percent, $txtva, $txlocaltax1=0,$txlocaltax2=0, $price_base_type='HT', $info_bits=0, $date_start='', $date_end='', $type=0, $fk_parent_line=0, $skip_update_total=0, $fk_fournprice=null, $pa_ht=0, $label='', $special_code=0)
    {
        global $conf;

		dol_syslog(get_class($this)."::updateline $rowid, $desc, $pu, $qty, $remise_percent, $txtva, $txlocaltax1, $txlocaltax2, $price_base_type, $info_bits, $date_start, $date_end, $type");
		include_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';

		$this->db->begin();
		
		// Clean parameters
		if (empty($qty)) $qty=0;
		if (empty($info_bits)) $info_bits=0;
		if (empty($txtva)) $txtva=0;
		if (empty($txlocaltax1)) $txlocaltax1=0;
		if (empty($txlocaltax2)) $txlocaltax2=0;
		if (empty($remise)) $remise=0;
		if (empty($remise_percent)) $remise_percent=0;
		if (empty($special_code) || $special_code == 3) $special_code=0;
		$remise_percent=price2num($remise_percent);
		$qty=price2num($qty);
		$pu = price2num($pu);
		$pa_ht=price2num($pa_ht);
		$txtva=price2num($txtva);
		$txlocaltax1=price2num($txlocaltax1);
		$txlocaltax2=price2num($txlocaltax2);

		// Calcul du total TTC et de la TVA pour la ligne a partir de
		// qty, pu, remise_percent et txtva
		// TRES IMPORTANT: C'est au moment de l'insertion ligne qu'on doit stocker
		// la part ht, tva et ttc, et ce au niveau de la ligne qui a son propre taux tva.
		$tabprice=calcul_price_total($qty, $pu, $remise_percent, $txtva, $txlocaltax1, $txlocaltax2, 0, $price_base_type, $info_bits, $type);
		$total_ht  = $tabprice[0];
		$total_tva = $tabprice[1];
		$total_ttc = $tabprice[2];
		$total_localtax1 = $tabprice[9];
		$total_localtax2 = $tabprice[10];

		// Anciens indicateurs: $price, $subprice, $remise (a ne plus utiliser)
		$price = $pu;
		$subprice = $pu;
		$remise = 0;
		if ($remise_percent > 0)
		{
			$remise = round(($pu * $remise_percent / 100),2);
			$price = ($pu - $remise);
		}

		// Update line
		$this->line=new ManagementFichinterLigne($this->db);

		// Stock previous line records
		$staticline=new ManagementFichinterLigne($this->db);
		$staticline->fetch($rowid);
		$fichinterid=$staticline->fk_fichinter;
		$this->line->oldline = $staticline;

		// Reorder if fk_parent_line change
		if (! empty($fk_parent_line) && ! empty($staticline->fk_parent_line) && $fk_parent_line != $staticline->fk_parent_line)
		{
			$rangmax = $this->line_max($fk_parent_line);
			$this->line->rang = $rangmax + 1;
		}
		
		$this->line->rowid=$rowid;
		$this->line->label=$label;
		$this->line->desc=$desc;
		$this->line->qty=$qty;
		$this->line->tva_tx=$txtva;
		$this->line->localtax1_tx=$txlocaltax1;
		$this->line->localtax2_tx=$txlocaltax2;
		$this->line->remise_percent=$remise_percent;
		$this->line->subprice=$subprice;
		$this->line->info_bits=$info_bits;
		$this->line->special_code=$special_code;
		$this->line->total_ht=$total_ht;
		$this->line->total_tva=$total_tva;
		$this->line->total_localtax1=$total_localtax1;
		$this->line->total_localtax2=$total_localtax2;
		$this->line->total_ttc=$total_ttc;
		$this->line->date_start=$date_start;
		$this->line->date_end=$date_end;
		$this->line->product_type=$type;
		$this->line->fk_parent_line=$fk_parent_line;
		$this->line->skip_update_total=$skip_update_total;

		// infos marge
		$this->line->fk_fournprice = $fk_fournprice;
		$this->line->pa_ht = $pa_ht;

		// TODO deprecated
		$this->line->price=$price;
		$this->line->remise=$remise;

		$result=$this->line->updateRapport();
		if ($result > 0)
		{
			// Reorder if child line
			if (! empty($fk_parent_line)) $this->line_order(true,'DESC');
	
			$this->line->fk_fichinter = $fichinterid;
			// Mise a jour info denormalisees
			$this->line->update_total();
			
			$this->db->commit();
			return $result;
		}
		else
		{
			$this->error=$this->db->lasterror();
			$this->errors=array($this->db->lasterror());
			$this->db->rollback();
			dol_syslog(get_class($this)."::updateline Error=".$this->error, LOG_ERR);
			return -1;
		}

	}

	/**
	 *  Delete an order line
	 *
	 *  @param      int		$lineid		Id of line to delete
	 *  @return     int        		 	>0 if OK, 0 if nothing to do, <0 if KO
	 */
    function deleteline($lineid)
    {
		global $user;

		$this->db->begin();

		$sql = "SELECT fk_product, qty";
		$sql.= " FROM ".MAIN_DB_PREFIX."fichinterdet";
		$sql.= " WHERE rowid = ".$lineid;

		$result = $this->db->query($sql);
		if ($result)
		{
			$obj = $this->db->fetch_object($result);

			if ($obj)
			{
				$product = new Product($this->db);
				$product->id = $obj->fk_product;

				// Delete line
				$line = new ManagementFichinterLigne($this->db);

				// For triggers
				$line->fetch($lineid);

				if ($line->deleteline($user) > 0)
				{
					$this->db->commit();
					return 1;
				}
				else
				{
					$this->db->rollback();
					$this->error=$this->db->lasterror();
					return -1;
				}
			}
			else
			{
				$this->db->rollback();
				return 0;
			}
		}
		else
		{
			$this->db->rollback();
			$this->error=$this->db->lasterror();
			return -1;
		}
	}
	
	function get_duree_inter_made()
	{

		$sql = "SELECT sum(qty) as totinter";
		$sql.= " FROM ".MAIN_DB_PREFIX."fichinterdet WHERE fk_fichinter=".$this->id;
		$sql.= " AND fk_product is not null AND product_type=1"; // seulement le temps
		//print $sql;
		dol_syslog(get_class($this)."::get_duree_inter_made sql=" . $sql);
		$result = $this->db->query($sql);
		if ($result)
		{
			$obj = $this->db->fetch_object($result);
			return $obj->totinter;
		}
		return 0;
	}
	
	
	function get_price_inter_used($producttype)
	{

		$sql = "SELECT sum(total_ht*qty) as totinter";
		$sql.= " FROM ".MAIN_DB_PREFIX."fichinterdet WHERE fk_fichinter=".$this->id;
		$sql.= " AND fk_product is not null AND product_type=".$producttype;
		
		dol_syslog(get_class($this)."::get_price_inter_used sql=" . $sql);
		$result = $this->db->query($sql);
		if ($result)
		{
			$obj = $this->db->fetch_object($result);
			return $obj->totinter;
		}
		return 0;
	}
	
	function __formAddObjectLine($dateSelector,$seller,$buyer)
	{
		global $conf,$user,$langs,$object,$hookmanager;
		global $form,$bcnd,$var;

		//Line extrafield
		require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		$extrafieldsline = new ExtraFields($this->db);
		$extralabelslines=$extrafieldsline->fetch_name_optionals_label($this->table_element_line);
		
		// Output template part (modules that overwrite templates must declare this into descriptor)
        // Use global variables + $dateSelector + $seller and $buyer
		$tpl = dol_buildpath('management/tpl/objectline_create.tpl.php');
		$res=include $tpl; 

    }

	function __printObjectLines($action, $seller, $buyer, $selected=0, $dateSelector=0)
	{
		global $conf, $hookmanager, $langs, $user;
		// TODO We should not use global var for this !
		global $inputalsopricewithtax, $usemargins, $disableedit, $disablemove, $disableremove, $outputalsopricetotalwithtax;

		// Define usemargins
		$usemargins=0;
		if (! empty($conf->margin->enabled) ) $usemargins=1;

		print '<tr class="liste_titre nodrag nodrop">';

		if (! empty($conf->global->MAIN_VIEW_LINE_NUMBER)) print '<td class="linecolnum" align="center" width="5">&nbsp;</td>';

		// Description
		print '<td class="linecoldescription">'.$langs->trans('Description').'</td>';

		if ($this->element == 'supplier_proposal')
		{
			print '<td class="linerefsupplier" align="right"><span id="title_fourn_ref">'.$langs->trans("SupplierProposalRefFourn").'</span></td>';
		}

		// VAT
		print '<td class="linecolvat" align="right" width="80">'.$langs->trans('VAT').'</td>';

		// Price HT
		print '<td class="linecoluht" align="right" width="80">'.$langs->trans('PriceUHT').'</td>';

		// Multicurrency
		if (!empty($conf->multicurrency->enabled)) print '<td class="linecoluht_currency" align="right" width="80">'.$langs->trans('PriceUHTCurrency', $this->multicurrency_code).'</td>';

		if ($inputalsopricewithtax) print '<td align="right" width="80">'.$langs->trans('PriceUTTC').'</td>';

		// Qty
		print '<td class="linecolqty" align="right">'.$langs->trans('Qty').'</td>';

		if($conf->global->PRODUCT_USE_UNITS)
		{
			print '<td class="linecoluseunit" align="left">'.$langs->trans('Unit').'</td>';
		}

		// Reduction short
		print '<td class="linecoldiscount" align="right">'.$langs->trans('ReductionShort').'</td>';

		if ($this->situation_cycle_ref) {
			print '<td class="linecolcycleref" align="right">' . $langs->trans('Progress') . '</td>';
		}

		if ($usemargins && ! empty($conf->margin->enabled) && empty($user->societe_id))
		{
			if (!empty($user->rights->margins->creer))
			{
				if ($conf->global->MARGIN_TYPE == "1")
					print '<td class="linecolmargin1 margininfos" align="right" width="80">'.$langs->trans('BuyingPrice').'</td>';
				else
					print '<td class="linecolmargin1 margininfos" align="right" width="80">'.$langs->trans('CostPrice').'</td>';	
			}
			
			if (! empty($conf->global->DISPLAY_MARGIN_RATES) && $user->rights->margins->liretous)
				print '<td class="linecolmargin2 margininfos" align="right" width="50">'.$langs->trans('MarginRate').'</td>';
			if (! empty($conf->global->DISPLAY_MARK_RATES) && $user->rights->margins->liretous)
				print '<td class="linecolmargin2 margininfos" align="right" width="50">'.$langs->trans('MarkRate').'</td>';
		}

		// Total HT
		print '<td class="linecolht" align="right">'.$langs->trans('TotalHTShort').'</td>';

		// Multicurrency
		if (!empty($conf->multicurrency->enabled)) print '<td class="linecoltotalht_currency" align="right">'.$langs->trans('TotalHTShortCurrency', $this->multicurrency_code).'</td>';

        if ($outputalsopricetotalwithtax) print '<td align="right" width="80">'.$langs->trans('TotalTTCShort').'</td>';

		print '<td class="linecoledit"></td>';  // No width to allow autodim

		print '<td class="linecoldelete" width="10"></td>';

		print '<td class="linecolmove" width="10"></td>';

		print "</tr>\n";

		$num = count($this->lines);
		$var = true;
		$i	 = 0;

		//Line extrafield
		require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		$extrafieldsline = new ExtraFields($this->db);
		$extralabelslines=$extrafieldsline->fetch_name_optionals_label($this->table_element_line);

		foreach ($this->lines as $line)
		{
			//Line extrafield
			$line->fetch_optionals($line->id,$extralabelslines);

			$var=!$var;

			//if (is_object($hookmanager) && (($line->product_type == 9 && ! empty($line->special_code)) || ! empty($line->fk_parent_line)))
            if (is_object($hookmanager))   // Old code is commented on preceding line.
			{
				if (empty($line->fk_parent_line))
				{
					$parameters = array('line'=>$line,'var'=>$var,'num'=>$num,'i'=>$i,'dateSelector'=>$dateSelector,'seller'=>$seller,'buyer'=>$buyer,'selected'=>$selected, 'extrafieldsline'=>$extrafieldsline);
                    $reshook = $hookmanager->executeHooks('printObjectLine', $parameters, $this, $action);    // Note that $action and $object may have been modified by some hooks
				}
				else
				{
					$parameters = array('line'=>$line,'var'=>$var,'num'=>$num,'i'=>$i,'dateSelector'=>$dateSelector,'seller'=>$seller,'buyer'=>$buyer,'selected'=>$selected, 'extrafieldsline'=>$extrafieldsline);
                    $reshook = $hookmanager->executeHooks('printObjectSubLine', $parameters, $this, $action);    // Note that $action and $object may have been modified by some hooks
				}
			}
            if (empty($reshook))
			{
				$this->printObjectLine($action,$line,$var,$num,$i,$dateSelector,$seller,$buyer,$selected,$extrafieldsline);
			}

			$i++;
		}
	}

	/**
	 *	Return HTML content of a detail line
	 *	TODO Move this into an output class file (htmlline.class.php)
	 *
	 *	@param	string		$action				GET/POST action
	 *	@param CommonObjectLine $line		       	Selected object line to output
	 *	@param  string	    $var               	Is it a an odd line (true)
	 *	@param  int		    $num               	Number of line (0)
	 *	@param  int		    $i					I
	 *	@param  int		    $dateSelector      	1=Show also date range input fields
	 *	@param  string	    $seller            	Object of seller third party
	 *	@param  string	    $buyer             	Object of buyer third party
	 *	@param	int			$selected		   	Object line selected
	 *  @param  int			$extrafieldsline	Object of extrafield line attribute
	 *	@return	void
	 */
	function __printObjectLine($action,$line,$var,$num,$i,$dateSelector,$seller,$buyer,$selected=0,$extrafieldsline=0)
	{
		global $conf,$langs,$user,$object,$hookmanager;
		global $form,$bc,$bcdd;
		global $object_rights, $disableedit, $disablemove;   // TODO We should not use global var for this !

		$object_rights = $this->getRights();

		$element=$this->element;

		$text=''; $description=''; $type=0;

		// Show product and description
		$type=(! empty($line->product_type)?$line->product_type:$line->fk_product_type);
		// Try to enhance type detection using date_start and date_end for free lines where type was not saved.
		if (! empty($line->date_start)) $type=1; // deprecated
		if (! empty($line->date_end)) $type=1; // deprecated

		// Ligne en mode visu
		if ($action != 'editline' || $selected != $line->id)
		{
			// Product
			if ($line->fk_product > 0)
			{
				$product_static = new Product($this->db);
				$product_static->fetch($line->fk_product);

                $product_static->ref = $line->ref; //can change ref in hook
                $product_static->label = $line->label; //can change label in hook
				$text=$product_static->getNomUrl(1);

				// Define output language and label
				if (! empty($conf->global->MAIN_MULTILANGS))
				{
					if (! is_object($this->thirdparty))
					{
						dol_print_error('','Error: Method printObjectLine was called on an object and object->fetch_thirdparty was not done before');
						return;
					}

					$prod = new Product($this->db);
					$prod->fetch($line->fk_product);

					$outputlangs = $langs;
					$newlang='';
					if (empty($newlang) && GETPOST('lang_id')) $newlang=GETPOST('lang_id');
					if (! empty($conf->global->PRODUIT_TEXTS_IN_THIRDPARTY_LANGUAGE) && empty($newlang)) $newlang=$this->thirdparty->default_lang;		// For language to language of customer
					if (! empty($newlang))
					{
						$outputlangs = new Translate("",$conf);
						$outputlangs->setDefaultLang($newlang);
					}

					$label = (! empty($prod->multilangs[$outputlangs->defaultlang]["label"])) ? $prod->multilangs[$outputlangs->defaultlang]["label"] : $line->product_label;
				}
				else
				{
					$label = $line->product_label;
				}

				$text.= ' - '.(! empty($line->label)?$line->label:$label);
				$description.=(! empty($conf->global->PRODUIT_DESC_IN_FORM)?'':dol_htmlentitiesbr($line->description));	// Description is what to show on popup. We shown nothing if already into desc.
			}

			$line->pu_ttc = price2num($line->subprice * (1 + ($line->tva_tx/100)), 'MU');

			// Output template part (modules that overwrite templates must declare this into descriptor)
			// Use global variables + $dateSelector + $seller and $buyer
			$dirtpls=array_merge($conf->modules_parts['tpl'],array('/core/tpl'));
			foreach($dirtpls as $reldir)
			{
				$tpl = dol_buildpath($reldir.'/objectline_view.tpl.php');
				if (empty($conf->file->strict_mode)) {
					$res=@include $tpl;
				} else {
					$res=include $tpl; // for debug
				}
				if ($res) break;
			}
		}

		// Ligne en mode update
		if ($this->statut == 0 && $action == 'editline' && $selected == $line->id)
		{
			$label = (! empty($line->label) ? $line->label : (($line->fk_product > 0) ? $line->product_label : ''));
			if (! empty($conf->global->MAIN_HTML5_PLACEHOLDER)) $placeholder=' placeholder="'.$langs->trans("Label").'"';
			else $placeholder=' title="'.$langs->trans("Label").'"';

			$line->pu_ttc = price2num($line->subprice * (1 + ($line->tva_tx/100)), 'MU');

			// Output template part (modules that overwrite templates must declare this into descriptor)
			// Use global variables + $dateSelector + $seller and $buyer
			$tpl = dol_buildpath('/management/tpl/objectline_edit.tpl.php');
			$res=include $tpl; // for debug

		}
	}


}

/**
 *	\class      FichinterLigne
 *	\brief      Classe permettant la gestion des lignes d'intervention
 */
class ManagementFichinterLigne  extends CommonObjectLine
{
	var $db;
	var $error;

	public $element='fichinterdet';
	public $table_element='fichinterdet';

	// From llx_fichinterdet
	var $rowid;
	var $fk_fichinter;
	var $desc;          	// Description ligne
	var $datei;           // Date intervention
	var $duration;        // Duree de l'intervention
	var $rang = 0;
	/// 
	var $date; 
	var $total_ht=0;	//montant total de l'intervention
	var $subprice=0;	//Prix unitaire de l'intervention
	var $localtax1_tx; 		// Local tax 1
	var $localtax2_tx; 		// Local tax 2
	var $product_type = 0;	// Type 0 = product, 1 = Service
	var $qty;				// Quantity (example 2)
	var $tva_tx;			// VAT Rate for product/service (example 19.6)
	var $remise_percent;	// % for line discount (example 20%)
	var $fk_remise_except;
	var $fk_fournprice;
	var $pa_ht;
	var $marge_tx;
	var $marque_tx;
	var $info_bits = 0;		// Bit 0: 	0 si TVA normal - 1 si TVA NPR
						    // Bit 1:	0 ligne normale - 1 si ligne de remise fixe
	var $special_code = 0;
	var $total_tva;			// Total TVA  de la ligne toute quantite et incluant la remise ligne
	var $total_localtax1;   // Total local tax 1 for the line
	var $total_localtax2;   // Total local tax 2 for the line
	var $total_ttc;			// Total TTC de la ligne toute quantite et incluant la remise ligne

	// From llx_product
	var $ref;				// deprecated
	var $libelle;			// deprecated
	var $product_ref;
	var $product_label; 	// Label produit
	var $product_desc;  	// Description produit

	// Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
	// Start and end date of the line
	var $date_start;
	var $date_end;

	var $skip_update_total; // Skip update price total for special lines

	/**
	 *	Constructor
	 *
	 *	@param	DoliDB	$db		Database handler
	 */
	function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 *	Retrieve the line of intervention
	 *
	 *	@param  int		$rowid		Line id
	 *	@return	int					<0 if KO, >0 if OK
	 */
	function fetch($rowid)
	{
		$sql = 'SELECT ft.rowid, ft.fk_fichinter, ft.description, ft.duree, ft.rang,';
		$sql.= ' ft.date as datei, total_ht, subprice';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'fichinterdet as ft';
		$sql.= ' WHERE ft.rowid = '.$rowid;

		dol_syslog("FichinterLigne::fetch sql=".$sql);
		$result = $this->db->query($sql);
		if ($result)
		{
			$objp = $this->db->fetch_object($result);
			$this->rowid          	= $objp->rowid;
			$this->fk_fichinter   	= $objp->fk_fichinter;
			$this->datei			= $this->db->jdate($objp->datei);
			$this->total_ht			= price2num($objp->total_ht);
			$this->subprice			= price2num($objp->subprice);
			$this->desc           	= $objp->description;
			$this->duration       	= $objp->duree;
			$this->rang           	= $objp->rang;

			$this->db->free($result);
			return 1;
		}
		else
		{
			$this->error=$this->db->error().' sql='.$sql;
			dol_print_error($this->db,$this->error, LOG_ERR);
			return -1;
		}
	}


	/**
	 *	Insert the line into database
	 *
	 *	@param		User	$user 		Objet user that make creation
     *	@param		int		$notrigger	Disable all triggers
	 *	@return		int		<0 if ko, >0 if ok
	 */
	function insert($user, $notrigger=0)
	{
		global $langs,$conf;
		
		dol_syslog("FichinterLigne::insert rang=".$this->rang);

		$this->db->begin();

		$rangToUse=$this->rang;
		if ($rangToUse == -1)
		{
			// Recupere rang max de la ligne d'intervention dans $rangmax
			$sql = 'SELECT max(rang) as max FROM '.MAIN_DB_PREFIX.'fichinterdet';
			$sql.= ' WHERE fk_fichinter ='.$this->fk_fichinter;
			$resql = $this->db->query($sql);
			if ($resql)
			{
				$obj = $this->db->fetch_object($resql);
				$rangToUse = $obj->max + 1;
			}
			else
			{
				dol_print_error($this->db);
				$this->db->rollback();
				return -1;
			}
		}

		// Insertion dans base de la ligne
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'fichinterdet';
		$sql.= ' (fk_fichinter, description, date, duree, qty, rang, total_ht, subprice)';
		$sql.= " VALUES (".$this->fk_fichinter.",";
		$sql.= " '".$this->db->escape($this->desc)."',";
		$sql.= " ".(! empty($this->datei)?$this->db->idate($this->datei):"null").',';
		$sql.= " ".$this->duration.", ".$this->duration.",";
		$sql.= ' '.$rangToUse.",";
		if ($this->subprice!=0)
		{
			if ($this->duration!=0)
				$sql.= ' '.price2num($this->subprice*$this->duration).",";
			else
				$sql.= " 0,";
			$sql.= ' '.price2num($this->subprice);
		}
		else
		{
				$sql.= " 0, 0";
		}
		$sql.= ')';
//print $sql;
		dol_syslog("FichinterLigne::insert sql=".$sql);
		$resql=$this->db->query($sql);
		if ($resql)
		{
			$result=$this->update_total();
			if ($result > 0)
			{
				$this->rang=$rangToUse;

				if (! $notrigger)
				{
					// Appel des triggers
					include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
					$interface=new Interfaces($this->db);
					$resulttrigger=$interface->run_triggers('LINEFICHINTER_CREATE',$this,$user,$langs,$conf);
					if ($resulttrigger < 0) {
						$error++; $this->errors=$interface->errors;
					}
					// Fin appel triggers
				}
			}
			
			if (!$error) {
				$this->db->commit();
				return $result;
			}
			else
			{
				$this->db->rollback();
				return -1;
			}
		}
		else
		{
			$this->error=$this->db->error()." sql=".$sql;
			dol_syslog("FichinterLigne::insert Error ".$this->error, LOG_ERR);
			$this->db->rollback();
			return -1;
		}
	}


	/**
	 *	Insert line into database
	 *
	 *	@param      int		$notrigger		1 = disable triggers
	 *	@return		int						<0 if KO, >0 if OK
	 */
	function insertRapport($notrigger=0)
	{
		global $langs, $conf, $user;

		$error=0;

		dol_syslog("OrderLine::insert rang=".$this->rang);

		// Clean parameters
		if (empty($this->tva_tx)) $this->tva_tx=0;
		if (empty($this->localtax1_tx)) $this->localtax1_tx=0;
		if (empty($this->localtax2_tx)) $this->localtax2_tx=0;
		if (empty($this->total_localtax1)) $this->total_localtax1=0;
		if (empty($this->total_localtax2)) $this->total_localtax2=0;
		if (empty($this->rang)) $this->rang=0;
		if (empty($this->remise)) $this->remise=0;
		if (empty($this->remise_percent)) $this->remise_percent=0;
		if (empty($this->info_bits)) $this->info_bits=0;
		if (empty($this->special_code)) $this->special_code=0;
		if (empty($this->fk_parent_line)) $this->fk_parent_line=0;

		if (empty($this->pa_ht)) $this->pa_ht=0;

		// si prix d'achat non renseigne et utilise pour calcul des marges alors prix achat = prix vente
		if ($this->pa_ht == 0) {
			if ($this->subprice > 0 && (isset($conf->global->ForceBuyingPriceIfNull) && $conf->global->ForceBuyingPriceIfNull == 1))
				$this->pa_ht = $this->subprice * (1 - $this->remise_percent / 100);
		}

		// Check parameters
		if ($this->product_type < 0) return -1;

		$this->db->begin();

		// Insertion dans base de la ligne
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'fichinterdet';
		$sql.= ' (fk_fichinter, fk_parent_line, label, description, qty, tva_tx, localtax1_tx, localtax2_tx,';
		$sql.= ' fk_product, product_type, remise_percent, subprice, price, remise, fk_remise_except,';
		$sql.= ' special_code, rang, fk_product_fournisseur_price, buy_price_ht,';
		$sql.= ' info_bits, total_ht, total_tva, total_localtax1, total_localtax2, total_ttc, date_start, date_end)';
		$sql.= " VALUES (".$this->fk_fichinter.",";
		$sql.= " ".($this->fk_parent_line>0?"'".$this->fk_parent_line."'":"null").",";
		$sql.= " ".(! empty($this->label)?"'".$this->db->escape($this->label)."'":"null").",";
		$sql.= " '".$this->db->escape($this->desc)."',";
		$sql.= " '".price2num($this->qty)."',";
		$sql.= " '".price2num($this->tva_tx)."',";
		$sql.= " '".price2num($this->localtax1_tx)."',";
		$sql.= " '".price2num($this->localtax2_tx)."',";
		$sql.= ' '.(! empty($this->fk_product)?$this->fk_product:"null").',';
		$sql.= " '".$this->product_type."',";
		$sql.= " '".price2num($this->remise_percent)."',";
		$sql.= " ".($this->subprice!=''?"'".price2num($this->subprice)."'":"null").",";
		$sql.= " ".($this->price!=''?"'".price2num($this->price)."'":"null").",";
		$sql.= " '".price2num($this->remise)."',";
		$sql.= ' '.(! empty($this->fk_remise_except)?$this->fk_remise_except:"null").',';
		$sql.= ' '.$this->special_code.',';
		$sql.= ' '.$this->rang.',';
		$sql.= ' '.(! empty($this->fk_fournprice)?$this->fk_fournprice:"null").',';
		$sql.= ' '.price2num($this->pa_ht).',';
		$sql.= " '".$this->info_bits."',";
		$sql.= " '".price2num($this->total_ht)."',";
		$sql.= " '".price2num($this->total_tva)."',";
		$sql.= " '".price2num($this->total_localtax1)."',";
		$sql.= " '".price2num($this->total_localtax2)."',";
		$sql.= " '".price2num($this->total_ttc)."',";
		$sql.= " ".(! empty($this->date_start)?"'".$this->db->idate($this->date_start)."'":"null").',';
		$sql.= " ".(! empty($this->date_end)?"'".$this->db->idate($this->date_end)."'":"null");
		$sql.= ')';

		dol_syslog(get_class($this)."::insert sql=".$sql, LOG_DEBUG);
		$resql=$this->db->query($sql);
		if ($resql)
		{
			$this->rowid=$this->db->last_insert_id(MAIN_DB_PREFIX.'fichinterdet');
			
			if (! $notrigger)
			{
				// Appel des triggers
				include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
				$interface=new Interfaces($this->db);
				$result=$interface->run_triggers('LINEFICHINTER_INSERT',$this,$user,$langs,$conf);
				if ($result < 0) { $error++; $this->errors=$interface->errors; }
				// Fin appel triggers
			}
			
			$this->db->commit();
			$result=$this->update_total();
			return 1;
		}
		else
		{
			$this->error=$this->db->error();
			dol_syslog(get_class($this)."::insert Error ".$this->error, LOG_ERR);
			$this->db->rollback();
			return -2;
		}
	}


	/**
	 *	Update intervention into database
	 *
	 *	@param		User	$user 		Objet user that make creation
     *	@param		int		$notrigger	Disable all triggers
	 *	@return		int		<0 if ko, >0 if ok
	 */
	function update($user,$notrigger=0)
	{
		global $langs,$conf;

		$this->db->begin();

		// Mise a jour ligne en base
		$sql = "UPDATE ".MAIN_DB_PREFIX."fichinterdet SET";
		$sql.= " description='".$this->db->escape($this->desc)."'";
		$sql.= ",date=".$this->db->idate($this->datei);
		$sql.= ",duree=".$this->duration;
		$sql.= ",rang='".$this->rang."'";
		if ($this->subprice!=0 && $this->duration!=0)
		{
			$sql.= ",total_ht=".price2num($this->subprice*$this->duration);
			$sql.= ",subprice=".price2num($this->subprice);
		}
		else
		{
			$sql.= ",total_ht=0";
			$sql.= ",subprice=0";
		}
		$sql.= " WHERE rowid = ".$this->rowid;

		dol_syslog("FichinterLigne::update sql=".$sql);
		$resql=$this->db->query($sql);
		if ($resql)
		{
			$result=$this->update_total();
			if ($result > 0)
			{

				if (! $notrigger)
				{
					// Appel des triggers
					include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
					$interface=new Interfaces($this->db);
					$resulttrigger=$interface->run_triggers('LINEFICHINTER_UPDATE',$this,$user,$langs,$conf);
					if ($resulttrigger < 0) {
						$error++; $this->errors=$interface->errors;
					}
					// Fin appel triggers
				}
			}

			if (!$error)
			{
				$this->db->commit();
				return $result;
			}
			else
			{
				$this->error=$this->db->lasterror();
				dol_syslog("FichinterLigne::update Error ".$this->error, LOG_ERR);
				$this->db->rollback();
				return -1;
			}
		}
		else
		{
			$this->error=$this->db->lasterror();
			dol_syslog("FichinterLigne::update Error ".$this->error, LOG_ERR);
			$this->db->rollback();
			return -1;
		}
	}

    /**
     *	Update the line object into db
     *
	 *	@param      int		$notrigger		1 = disable triggers
     *	@return		int		<0 si ko, >0 si ok
     */
	function updateRapport($notrigger=0)
	{
		global $conf,$langs,$user;

		$error=0;

		// Clean parameters
		if (empty($this->tva_tx)) $this->tva_tx=0;
		if (empty($this->localtax1_tx)) $this->localtax1_tx=0;
		if (empty($this->localtax2_tx)) $this->localtax2_tx=0;
		if (empty($this->qty)) $this->qty=0;
		if (empty($this->total_localtax1)) $this->total_localtax1=0;
		if (empty($this->total_localtax2)) $this->total_localtax2=0;
		if (empty($this->marque_tx)) $this->marque_tx=0;
		if (empty($this->marge_tx)) $this->marge_tx=0;
		if (empty($this->remise)) $this->remise=0;
		if (empty($this->remise_percent)) $this->remise_percent=0;
		if (empty($this->info_bits)) $this->info_bits=0;
        if (empty($this->special_code)) $this->special_code=0;
		if (empty($this->product_type)) $this->product_type=0;
		if (empty($this->fk_parent_line)) $this->fk_parent_line=0;
		if (empty($this->pa_ht)) $this->pa_ht=0;

		// si prix d'achat non renseigné et utilisé pour calcul des marges alors prix achat = prix vente
		if ($this->pa_ht == 0) {
			if ($this->subprice > 0 && (isset($conf->global->ForceBuyingPriceIfNull) && $conf->global->ForceBuyingPriceIfNull == 1))
				$this->pa_ht = $this->subprice * (1 - $this->remise_percent / 100);
		}

		$this->db->begin();

		// Mise a jour ligne en base
		$sql = "UPDATE ".MAIN_DB_PREFIX."fichinterdet SET";
		$sql.= " description='".$this->db->escape($this->desc)."'";
		$sql.= " , label=".(! empty($this->label)?"'".$this->db->escape($this->label)."'":"null");
		$sql.= " , tva_tx=".price2num($this->tva_tx);
		$sql.= " , localtax1_tx=".price2num($this->localtax1_tx);
		$sql.= " , localtax2_tx=".price2num($this->localtax2_tx);
		$sql.= " , qty=".price2num($this->qty);
		$sql.= " , subprice=".price2num($this->subprice)."";
		$sql.= " , remise_percent=".price2num($this->remise_percent)."";
		$sql.= " , price=".price2num($this->price)."";					// TODO A virer
		$sql.= " , remise=".price2num($this->remise)."";				// TODO A virer
		if (empty($this->skip_update_total))
		{
			$sql.= " , total_ht=".price2num($this->total_ht)."";
			$sql.= " , total_tva=".price2num($this->total_tva)."";
			$sql.= " , total_ttc=".price2num($this->total_ttc)."";
			$sql.= " , total_localtax1=".price2num($this->total_localtax1);
			$sql.= " , total_localtax2=".price2num($this->total_localtax2);
		}
		$sql.= " , fk_product_fournisseur_price=".(! empty($this->fk_fournprice)?$this->fk_fournprice:"null");
		$sql.= " , buy_price_ht='".price2num($this->pa_ht)."'";
		$sql.= " , info_bits=".$this->info_bits;
        $sql.= " , special_code=".$this->special_code;
		$sql.= " , date_start=".(! empty($this->date_start)?"'".$this->db->idate($this->date_start)."'":"null");
		$sql.= " , date_end=".(! empty($this->date_end)?"'".$this->db->idate($this->date_end)."'":"null");
		$sql.= " , product_type=".$this->product_type;
		$sql.= " , fk_parent_line=".(! empty($this->fk_parent_line)?$this->fk_parent_line:"null");
		if (! empty($this->rang)) $sql.= ", rang=".$this->rang;
		$sql.= " WHERE rowid = ".$this->rowid;

		dol_syslog(get_class($this)."::update sql=".$sql, LOG_DEBUG);
		$resql=$this->db->query($sql);
		if ($resql)
		{
			if (! $notrigger)
			{
				// Appel des triggers
				include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
				$interface=new Interfaces($this->db);
				$result = $interface->run_triggers('LINEFICHINTER_UPDATE',$this,$user,$langs,$conf);
				if ($result < 0) { $error++; $this->errors=$interface->errors; }
				// Fin appel triggers
			}
			
			$this->db->commit();
			$result=$this->update_total();
			return 1;
		}
		else
		{
			$this->error=$this->db->error();
			dol_syslog(get_class($this)."::update Error ".$this->error, LOG_ERR);
			$this->db->rollback();
			return -2;
		}
	}


	/**
	 *	Update total duration into llx_fichinter
	 *
	 *	@return		int		<0 si ko, >0 si ok
	 */
	function update_total()
	{
		global $conf;

		$this->db->begin();

		$sql = "SELECT SUM(duree) as total_duration, SUM(total_ht) as total_ht, SUM(total_ttc) as total_ttc, SUM(total_tva) as total_tva";
		$sql.= " , SUM(total_localtax1) as total_localtax1, SUM(total_localtax2) as total_localtax2";
		$sql.= " FROM ".MAIN_DB_PREFIX."fichinterdet";
		$sql.= " WHERE fk_fichinter=".$this->fk_fichinter;

		dol_syslog("FichinterLigne::update_total sql=".$sql);
		$resql=$this->db->query($sql);
		if ($resql)
		{
			$obj=$this->db->fetch_object($resql);

			$total_duration = (!empty($obj->total_duration)?$obj->total_duration:0);
			$total_ht = (!empty($obj->total_ht)?$obj->total_ht:0);
			$total_ttc = (!empty($obj->total_ttc)?$obj->total_ttc:0);
			$total_tva = (!empty($obj->total_tva)?$obj->total_tva:0);
			$total_localtax1 = (!empty($obj->total_localtax1)?$obj->total_localtax1:0);
			$total_localtax2 = (!empty($obj->total_localtax2)?$obj->total_localtax2:0);

			$sql = "UPDATE ".MAIN_DB_PREFIX."fichinter";
			$sql.= " SET duree = ".$total_duration;
			$sql.= " , total_ht = ".$total_ht;
			$sql.= " , total_ttc = ".$total_ttc;
			$sql.= " , total_tva = ".$total_tva;

			$sql.= " WHERE rowid = ".$this->fk_fichinter;
			$sql.= " AND entity = ".$conf->entity;

			dol_syslog("FichinterLigne::update_total sql=".$sql);
			$resql=$this->db->query($sql);
			if ($resql)
			{
				$this->db->commit();
				return 1;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog("FichinterLigne::update_total Error ".$this->error, LOG_ERR);
				$this->db->rollback();
				return -2;
			}
		}
		else
		{
			$this->error=$this->db->error();
			dol_syslog("FichinterLigne::update Error ".$this->error, LOG_ERR);
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 *	Delete a intervention line
	 *
	 *	@param		User	$user 		Objet user that make creation
     *	@param		int		$notrigger	Disable all triggers
	 *	@return     int		>0 if ok, <0 if ko
	 */
	function deleteline($user,$notrigger=0)
	{
		global $langs,$conf;
		
		if ($this->statut == 0)
		{
			dol_syslog(get_class($this)."::deleteline lineid=".$this->rowid);
			$this->db->begin();

			$sql = "DELETE FROM ".MAIN_DB_PREFIX."fichinterdet WHERE rowid = ".$this->rowid;
			$resql = $this->db->query($sql);
			dol_syslog(get_class($this)."::deleteline sql=".$sql);

			if ($resql)
			{
				$result = $this->update_total();
				if ($result > 0)
				{
					$this->db->commit();

					if (! $notrigger)
					{
						// Appel des triggers
						include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
						$interface=new Interfaces($this->db);
						$resulttrigger=$interface->run_triggers('LINEFICHINTER_DELETE',$this,$user,$langs,$conf);
						if ($resulttrigger < 0) {
							$error++; $this->errors=$interface->errors;
						}
						// Fin appel triggers
					}

					return $result;
				}
				else
				{
					$this->db->rollback();
					return -1;
				}
			}
			else
			{
				$this->error=$this->db->error()." sql=".$sql;
				dol_syslog(get_class($this)."::deleteline Error ".$this->error, LOG_ERR);
				$this->db->rollback();
				return -1;
			}
		}
		else
		{
			return -2;
		}
	}
}
?>