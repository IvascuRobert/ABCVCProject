<?php
/* Copyright (C) 2003-2005	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2009-2012	Regis Houssin			<regis.houssin@capnetworks.com>
 * Copyright (C) 2010-2011	Juanjo Menent			<jmenent@2byte.es>
 * Copyright (C) 2012       Cedric Salvador			<csalvador@gpcsolutions.fr>
 * Copyright (C) 2013       Florian Henry		  	<florian.henry@open-concept.pro>
 * Copyright (C) 2015       Marcos García           <marcosgdf@gmail.com>
 * Copyright (C) 2016       Charlie Benke			<charlie@patas-monkey.com>
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
 *	\file       management/class/fichinter-rec.class.php
 *	\ingroup    facture
 *	\brief      Fichier de la classe des factures recurentes
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/notify.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once ('/management/class/managementfichinter.class.php');


/**
 *	Classe de gestion des factures recurrentes/Modeles
 */
class FichinterRec extends Managementfichinter
{
	public $element='fichinterrec';
	public $table_element='fichinter_rec';
	public $table_element_line='fichinter_rec';
	public $fk_element='fk_fichinter';

	var $number;
	var $date;
	var $amount;
	var $remise;
	var $tva;
	var $total;
	var $db_table;
	var $propalid;

	var $rang;
	var $special_code;

	var $usenewprice=0;

	/**
	 *	Constructor
	 *
	 * 	@param		DoliDB		$db		Database handler
	 */
	function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * 	Create a predefined invoice
	 *
	 * 	@param		User	$user		User object
	 * 	@param		int		$facid		Id of source invoice
	 *	@return		int					<0 if KO, id of invoice if OK
	 */
	function create($user, $fichinterid)
	{
		global $conf;

		$error=0;
		$now=dol_now();

		// Clean parameters
		$this->titre=trim($this->titre);
		$this->description=trim($this->description);


		$this->db->begin();

		// Charge fichinter modele
		$fichintsrc=new Managementfichinter($this->db);

		$result=$fichintsrc->fetch($fichinterid);
		$result=$fichintsrc->fetch_lines(1); // pour avoir toute les lignes
		

		if ($result > 0)
		{
			// On positionne en mode brouillon la facture
			$this->brouillon = 1;

			$sql = "INSERT INTO ".MAIN_DB_PREFIX."fichinter_rec (";
			$sql.= "titre";
			$sql.= ", fk_soc";
			$sql.= ", entity";
			$sql.= ", datec";
			$sql.= ", duree";
			$sql.= ", description";
			$sql.= ", note_private";
			$sql.= ", note_public";
			$sql.= ", fk_user_author";
			$sql.= ", fk_projet";
			$sql.= ", fk_contrat";
			$sql.= ", modelpdf";
			
			$sql.= ") VALUES (";
			$sql.= "'".$this->titre."'";
			$sql.= ", ".($this->socid >0 ? $this->socid : 'null');
			$sql.= ", ".$conf->entity;
			$sql.= ", '".$this->db->idate($now)."'";
			$sql.= ", ".(!empty($fichintsrc->duree)?$fichintsrc->duree:'0');
			$sql.= ", ".(!empty($this->description)?("'".$this->db->escape($this->description)."'"):"NULL");
			$sql.= ", ".(!empty($fichintsrc->note_private)?("'".$this->db->escape($fichintsrc->note_private)."'"):"NULL");
			$sql.= ", ".(!empty($fichintsrc->note_public)?("'".$this->db->escape($fichintsrc->note_public)."'"):"NULL");
			$sql.= ", '".$user->id."'";
			// si c'est la même société on conserve les liens vers le projet et le contrat
			if ($this->socid == $fichintsrc->socid)
			{
				$sql.= ", ".(! empty($fichintsrc->fk_project)?"'".$fichintsrc->fk_project."'":"null");
				$sql.= ", ".(! empty($fichintsrc->fk_contrat)?"'".$fichintsrc->fk_contrat."'":"null");
			}
			else
				$sql.= ", null, null";

			$sql.= ", ".(! empty($fichintsrc->modelpdf)?"'".$fichintsrc->modelpdf."'":"''");
			$sql.= ")";
			if ($this->db->query($sql))
			{
				$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);

				/*
				 * Lines
				 */
				$num=count($fichintsrc->lines);
				for ($i = 0; $i < $num; $i++)
				{
					//$result=$fichintlignesrc->fetch($fichintsrc->lines[$i]->id);

					//var_dump($fichintsrc->lines[$i]);
					$result_insert = $this->addline(
						$fichintsrc->lines[$i]->desc,
						$fichintsrc->lines[$i]->duree,
						$fichintsrc->lines[$i]->datei,
						$fichintsrc->lines[$i]->rang,
						$fichintsrc->lines[$i]->subprice,
						$fichintsrc->lines[$i]->qty,
						$fichintsrc->lines[$i]->tva_tx,
						$fichintsrc->lines[$i]->fk_product,
						$fichintsrc->lines[$i]->remise_percent,
						'HT',
						0,
						'',
						0,
						$fichintsrc->lines[$i]->product_type,
						$fichintsrc->lines[$i]->special_code,
						$fichintsrc->lines[$i]->label,
						$fichintsrc->lines[$i]->fk_unit
					);

					if ($result_insert < 0)
					{
						$error++;
					}
				}

				if ($error)
				{
					$this->db->rollback();
				}
				else
				{
					$this->db->commit();
					return $this->id;
				}
			}
			else
			{
				$this->error=$this->db->error().' sql='.$sql;
				$this->db->rollback();
				return -2;
			}
		}
		else
		{
			$this->db->rollback();
			return -1;
		}
	}


	/**
	 *	Recupere l'objet facture et ses lignes de factures
	 *
	 *	@param      int		$rowid       	Id of object to load
	 * 	@param		string	$ref			Reference of invoice
	 * 	@param		string	$ref_ext		External reference of invoice
	 * 	@param		int		$ref_int		Internal reference of other object
	 *	@return     int         			>0 if OK, <0 if KO, 0 if not found
	 */
	function fetch($rowid=0, $ref='', $ref_ext='', $ref_int='')
	{
		$sql = 'SELECT f.titre, f.fk_soc';
		$sql.= ', f.datec, f.duree, f.fk_projet, f.fk_contrat, f.description';
		$sql.= ', f.note_private, f.note_public, f.fk_user_author';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'fichinter_rec as f';
		if ($rowid >0 ) $sql.= ' WHERE f.rowid='.$rowid;
  		elseif ($ref) $sql.= " WHERE f.titre='".$this->db->escape($ref)."'";

		/* This field are not used for template invoice
		if ($ref_ext) $sql.= " AND f.ref_ext='".$this->db->escape($ref_ext)."'";
		if ($ref_int) $sql.= " AND f.ref_int='".$this->db->escape($ref_int)."'";
		*/
		
        dol_syslog(get_class($this)."::fetch rowid=".$rowid, LOG_DEBUG);
		$result = $this->db->query($sql);
		if ($result)
		{
			if ($this->db->num_rows($result))
			{
				$obj = $this->db->fetch_object($result);

				$this->id                     = $rowid;
				$this->titre                  = $obj->titre;
				$this->ref                    = $obj->titre;
				$this->description            = $obj->description;
				$this->datec                  = $obj->datec;
				$this->socid                  = $obj->fk_soc;
				$this->statut                 = 0;
				$this->fk_project             = $obj->fk_projet;
				$this->fk_contrat             = $obj->fk_contrat;
				$this->note_private           = $obj->note_private;
				$this->note_public            = $obj->note_public;
				$this->user_author            = $obj->fk_user_author;
				$this->modelpdf               = $obj->model_pdf;
				$this->rang					  = $obj->rang;
				$this->special_code			  = $obj->special_code;

				$this->brouillon = 1;

				/*
				 * Lines
				 */
				$result=$this->fetch_lines();
				if ($result < 0)
				{
					$this->error=$this->db->error();
					return -3;
				}
				return 1;
			}
			else
			{
				$this->error='Bill with id '.$rowid.' not found sql='.$sql;
				dol_syslog('Facture::Fetch Error '.$this->error, LOG_ERR);
				return -2;
			}
		}
		else
		{
			$this->error=$this->db->error();
			return -1;
		}
	}


	/**
	 *	Recupere les lignes de factures predefinies dans this->lines
	 *
	 *	@return     int         1 if OK, < 0 if KO
 	 */
	function fetch_lines()
	{
		$sql = 'SELECT l.rowid, l.fk_product, l.product_type, l.label as custom_label, l.description, l.price, l.qty, l.tva_tx, ';
		$sql.= ' l.remise, l.remise_percent, l.subprice, l.duree, ';
		$sql.= ' l.total_ht, l.total_tva, l.total_ttc,';
		$sql.= ' l.rang, l.special_code,';
		$sql.= ' l.fk_unit,';
		$sql.= ' p.ref as product_ref, p.fk_product_type as fk_product_type, p.label as product_label, p.description as product_desc';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'fichinterdet_rec as l';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON l.fk_product = p.rowid';
		$sql.= ' WHERE l.fk_fichinter = '.$this->id;

		dol_syslog('FichInter-rec::fetch_lines', LOG_DEBUG);
		$result = $this->db->query($sql);
		if ($result)
		{
			$num = $this->db->num_rows($result);
			$i = 0;
			while ($i < $num)
			{
				$objp = $this->db->fetch_object($result);
				$line = new FichinterLigne($this->db);

				$line->rowid	        = $objp->rowid;
				$line->label            = $objp->custom_label;		// Label line
				$line->desc             = $objp->description;		// Description line
				$line->product_type     = $objp->product_type;		// Type of line
				$line->product_ref      = $objp->product_ref;		// Ref product
				$line->libelle          = $objp->product_label;		// deprecated
				$line->product_label	= $objp->product_label;		// Label product
				$line->product_desc     = $objp->product_desc;		// Description product
				$line->fk_product_type  = $objp->fk_product_type;	// Type of product
				$line->qty              = $objp->qty;
				$line->duree            = $objp->duree;
				$line->datei            = $objp->date;
				$line->subprice         = $objp->subprice;
				$line->tva_tx           = $objp->tva_tx;
				$line->remise_percent   = $objp->remise_percent;
				$line->fk_remise_except = $objp->fk_remise_except;
				$line->fk_product       = $objp->fk_product;
				$line->date_start       = $objp->date_start;
				$line->date_end         = $objp->date_end;
				$line->date_start       = $objp->date_start;
				$line->date_end         = $objp->date_end;
				$line->info_bits        = $objp->info_bits;
				$line->total_ht         = $objp->total_ht;
				$line->total_tva        = $objp->total_tva;
				$line->total_ttc        = $objp->total_ttc;
				$line->code_ventilation = $objp->fk_code_ventilation;
				$line->rang 			= $objp->rang;
				$line->special_code 	= $objp->special_code;
				$line->fk_unit          = $objp->fk_unit;

				// Ne plus utiliser
				$line->price            = $objp->price;
				$line->remise           = $objp->remise;

				$this->lines[$i] = $line;

				$i++;
			}

			$this->db->free($result);
			return 1;
		}
		else
		{
			$this->error=$this->db->error();
			return -3;
		}
	}


	/**
	 * 	Delete template invoice
	 *
	 *	@param     	int		$rowid      	Id of invoice to delete. If empty, we delete current instance of invoice
	 *	@param		int		$notrigger		1=Does not execute triggers, 0= execute triggers
	 *	@param		int		$idwarehouse	Id warehouse to use for stock change.
	 *	@return		int						<0 if KO, >0 if OK
	 */
	function delete($rowid=0, $notrigger=0, $idwarehouse=-1)
	{
	    if (empty($rowid)) $rowid=$this->id;
	    
	    dol_syslog(get_class($this)."::delete rowid=".$rowid, LOG_DEBUG);
	    
        $error=0;
		$this->db->begin();
		
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."fichinterdet_rec WHERE fk_fichinter = ".$rowid;
		dol_syslog($sql);
		if ($this->db->query($sql))
		{
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."fichinter_rec WHERE rowid = ".$rowid;
			dol_syslog($sql);
			if (! $this->db->query($sql))
			{
				$this->error=$this->db->lasterror();
				$error=-1;
			}
		}
		else
		{
			$this->error=$this->db->lasterror();
			$error=-2;
		}
		
		if (! $error)
		{
		    $this->db->commit();
		    return 1;
		}
		else
		{
	        $this->db->rollback();
	        return $error;
		}
	}


	/**
	* 	Add a line to fichinter
	*
	*	@param    	string		$desc            	Description de la ligne
	*	@param    	integer		$duration           Durée
	*	@param    	timestamp	$date				Date
	*	@param      int			$rang               Position of line
		
	*	@param    	double		$pu_ht              Prix unitaire HT (> 0 even for credit note)
	*	@param    	double		$qty             	Quantite
	*	@param    	double		$txtva           	Taux de tva force, sinon -1
	*	@param    	int			$fk_product      	Id du produit/service predefini
	*	@param    	double		$remise_percent  	Pourcentage de remise de la ligne
	*	@param		string		$price_base_type	HT or TTC
	*	@param    	int			$info_bits			Bits de type de lignes
	*	@param    	int			$fk_remise_except	Id remise
	*	@param    	double		$pu_ttc             Prix unitaire TTC (> 0 even for credit note)
	*	@param		int			$type				Type of line (0=product, 1=service)
	*	@param		int			$special_code		Special code
	*	@param		string		$label				Label of the line
	*	@param		string		$fk_unit			Unit
	*	@return    	int             				<0 if KO, Id of line if OK
	*/
	function addline($desc, $duration, $datei, $rang=-1, $pu_ht=0, $qty=0, $txtva=0, $fk_product=0, $remise_percent=0, $price_base_type='HT', $info_bits=0, $fk_remise_except='', $pu_ttc=0, $type=0,  $special_code=0, $label='', $fk_unit=null)
	{
	    global $mysoc;
	    
		$fichinterid=$this->id;

		dol_syslog("FichinterRec::addline facid=$facid,desc=$desc,pu_ht=$pu_ht,qty=$qty,txtva=$txtva,fk_product=$fk_product,remise_percent=$remise_percent,date_start=$date_start,date_end=$date_end,ventil=$ventil,info_bits=$info_bits,fk_remise_except=$fk_remise_except,price_base_type=$price_base_type,pu_ttc=$pu_ttc,type=$type,fk_unit=$fk_unit", LOG_DEBUG);
		include_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';

		// Check parameters
		if ($type < 0) return -1;

		if ($this->brouillon)
		{
			// Clean parameters
			$remise_percent=price2num($remise_percent);
			$qty=price2num($qty);
			if (! $qty) $qty=1;
			if (! $info_bits) $info_bits=0;
			$pu_ht=price2num($pu_ht);
			$pu_ttc=price2num($pu_ttc);
			$txtva=price2num($txtva);

			if ($price_base_type=='HT')
			{
				$pu=$pu_ht;
			}
			else
			{
				$pu=$pu_ttc;
			}

			// Calcul du total TTC et de la TVA pour la ligne a partir de
			// qty, pu, remise_percent et txtva
			// TRES IMPORTANT: C'est au moment de l'insertion ligne qu'on doit stocker
			// la part ht, tva et ttc, et ce au niveau de la ligne qui a son propre taux tva.
			$tabprice=calcul_price_total($qty, $pu, $remise_percent, $txtva, 0, 0, 0, $price_base_type, $info_bits, $type, $mysoc);

			$total_ht  = $tabprice[0];
			$total_tva = $tabprice[1];
			$total_ttc = $tabprice[2];

			$product_type=$type;
			if ($fk_product)
			{
				$product=new Product($this->db);
				$result=$product->fetch($fk_product);
				$product_type=$product->type;
			}

			$sql = "INSERT INTO ".MAIN_DB_PREFIX."fichinterdet_rec (";
			$sql.= "fk_fichinter";
			$sql.= ", label";
			$sql.= ", description";
			$sql.= ", date";
			$sql.= ", duree";
			$sql.= ", price";
			$sql.= ", qty";
			$sql.= ", tva_tx";
			$sql.= ", fk_product";
			$sql.= ", product_type";
			$sql.= ", remise_percent";
			$sql.= ", subprice";
			$sql.= ", remise";
			$sql.= ", total_ht";
			$sql.= ", total_tva";
			$sql.= ", total_ttc";
			$sql.= ", rang";
			$sql.= ", special_code";
			$sql.= ", fk_unit";
			$sql.= ") VALUES (";
			$sql.= "'".$fichinterid."'";
			$sql.= ", ".(! empty($label)?"'".$this->db->escape($label)."'":"null");
			$sql.= ", ".(! empty($desc)?"'".$this->db->escape($desc)."'":"null");
			$sql.= ", ".(! empty($datei)?$this->db->idate($datei):"null");
			$sql.= ", ".$duration;
			$sql.= ", ".price2num($pu_ht);
			$sql.= ", ".(!empty($qty)? $qty :(!empty($duration)? $duration :"null"));
			$sql.= ", ".price2num($txtva);
			$sql.= ", ".(! empty($fk_product)? $fk_product :"null");
			$sql.= ", ".$product_type;
			$sql.= ", '".price2num($remise_percent)."'";
			$sql.= ", '".price2num($pu_ht)."'";
			$sql.= ", null";
			$sql.= ", '".price2num($total_ht)."'";
			$sql.= ", '".price2num($total_tva)."'";
			$sql.= ", '".price2num($total_ttc)."'";
			$sql.= ", ".$rang;
			$sql.= ", ".$special_code;
			$sql.= ", ".(! empty($fk_unit) ? $fk_unit :"null");
			$sql.= ")";

			dol_syslog(get_class($this)."::addline", LOG_DEBUG);
			if ($this->db->query($sql))
			{
				return 1;
			}
			else
			{
				$this->error=$this->db->lasterror();
				return -1;
			}
		}
	}


	/**
	 *	Rend la fichinter automatique
	 *
	 *	@param		User	$user		User object
	 *	@param		int		$freq		Freq
	 *	@param		string	$courant	Courant
	 *	@return		int					0 if OK, <0 if KO
	 */
	function set_auto($user, $freq, $courant)
	{
		if ($user->rights->fichinter->creer)
		{
			$sql = "UPDATE ".MAIN_DB_PREFIX."fichinter_rec ";
			$sql .= " SET frequency = '".$freq."', last_gen='".$courant."'";
			$sql .= " WHERE rowid = ".$this->id;

			$resql = $this->db->query($sql);

			if ($resql)
			{
				$this->frequency 	= $freq;
				$this->last_gen 	= $courant;
				return 0;
			}
			else
			{
				dol_print_error($this->db);
				return -1;
			}
		}
		else
		{
			return -2;
		}
	}

	/**
	 *	Return clicable name (with picto eventually)
	 *
	 * @param	int		$withpicto       Add picto into link
	 * @param  string	$option          Where point the link
	 * @param  int		$max             Maxlength of ref
	 * @param  int		$short           1=Return just URL
	 * @param  string   $moretitle       Add more text to title tooltip
	 * @return string 			         String with URL
	 */
	function getNomUrl($withpicto=0,$option='',$max=0,$short=0,$moretitle='')
	{
		global $langs;

		$result='';
        $label=$langs->trans("ShowInvoice").': '.$this->ref;
        
        $url = dol_buildpath('/management/fichinter/',1).'fiche-rec.php?fichinterid='.$this->id;
        
        if ($short) return $url;
        
		$picto='bill';
        
		$link = '<a href="'.$url.'" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
		$linkend='</a>';



        if ($withpicto) $result.=($link.img_object($label, $picto, 'class="classfortooltip"').$linkend);
		if ($withpicto && $withpicto != 2) $result.=' ';
		if ($withpicto != 2) $result.=$link.$this->ref.$linkend;
		return $result;
	}


	/**
	 *  Initialise an instance with random values.
	 *  Used to build previews or test instances.
	 *	id must be 0 if object instance is a specimen.
	 *
	 *	@param	string		$option		''=Create a specimen invoice with lines, 'nolines'=No lines
	 *  @return	void
	 */
	function initAsSpecimen($option='')
	{
		global $user,$langs,$conf;

		$now=dol_now();
		$arraynow=dol_getdate($now);
		$nownotime=dol_mktime(0, 0, 0, $arraynow['mon'], $arraynow['mday'], $arraynow['year']);

		parent::initAsSpecimen($option);

		$this->usenewprice = 1;
	}

	/**
	 * Function used to replace a thirdparty id with another one.
	 *
	 * @param DoliDB $db Database handler
	 * @param int $origin_id Old thirdparty id
	 * @param int $dest_id New thirdparty id
	 * @return bool
	 */
	public static function replaceThirdparty(DoliDB $db, $origin_id, $dest_id)
	{
		$tables = array(
			'fichinter_rec'
		);

		return CommonObject::commonReplaceThirdparty($db, $origin_id, $dest_id, $tables);
	}
}
