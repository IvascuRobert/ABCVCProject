<?php
/* Copyright (C) 2003      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (c) 2005      Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis@dolibarr.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
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
 *       \file       htdocs/commande/class/commandestats.class.php
 *       \ingroup    commandes
 *       \brief      Fichier de la classe de gestion des stats des commandes
 *       \version    $Id: commandestats.class.php,v 1.6 2011/07/31 22:23:15 eldy Exp $
 */
include_once DOL_DOCUMENT_ROOT . "/core/class/stats.class.php";
include_once DOL_DOCUMENT_ROOT . "/projet/class/project.class.php";


/**
 *       \class      CommandeStats
 *       \brief      Classe permettant la gestion des stats des commandes
 */
class ProjectStats extends Stats
{
	var $db ;

	var $socid;
    var $userid;

	var $table_element;
    var $from;
	var $field;
    var $where;


	/**
	 * Constructor
	 *
	 * @param 	$DB		   Database handler
	 * @param 	$socid	   Id third party for filter
	 * @param 	$mode	   Option   /// à virer
	 * @param   $userid    Id user for filter
	 * @return 	CommandeStats
	 */
	function ProjectStats($DB, $socid=0, $mode, $userid=0)
	{
		global $user, $conf;

		$this->db = $DB;

		$this->socid = $socid;
        $this->userid = $userid;

		$object=new Project($this->db);
		$this->from = MAIN_DB_PREFIX.$object->table_element." as p";
		$this->from.= ", ".MAIN_DB_PREFIX.$object->table_element."_task as pt";
		$this->from.= ", ".MAIN_DB_PREFIX."societe as s";

		$this->fromNb = MAIN_DB_PREFIX.$object->table_element." as p";
		$this->fromNb.= ", ".MAIN_DB_PREFIX."societe as s";
		
		$this->field='total_ht';
		
		$this->where.= " p.fk_statut > 0";
		$this->where.= " AND p.fk_soc = s.rowid AND s.entity = ".$conf->entity;

		if (!$user->rights->societe->client->voir && !$this->socid) $this->where .= " AND p.fk_soc = sc.fk_soc AND sc.fk_user = " .$user->id;
		if($this->socid)
		{
			$this->where .= " AND p.fk_soc = ".$this->socid;
		}
        if ($this->userid > 0) $this->where.=' AND p.fk_user_creat = '.$this->userid;
	}

	/**
	 *    \brief      Renvoie le nombre de projet par mois pour une annee donnee
	 *
	 */
	function getNbByMonth($year)
	{
		global $conf;
		global $user;

		$sql = "SELECT date_format(p.datec,'%m') as dc, count(*) as nb";
		$sql.= " FROM ".$this->fromNb;
		if (!$user->rights->societe->client->voir && !$this->socid) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
		$sql.= " WHERE date_format(p.datec,'%Y') = '".$year."'";
		$sql.= " AND ".$this->where;
		$sql.= " GROUP BY dc";
        $sql.= $this->db->order('dc','DESC');

		return $this->_getNbByMonth($year, $sql);
	}

	/**
	 * Renvoie le nombre de projet par annee
	 *
	 */
	function getNbByYear()
	{
		global $conf;
		global $user;

		$sql = "SELECT date_format(p.datec,'%Y') as dc, count(*), sum(c.".$this->field.")";
		$sql.= " FROM ".$this->fromNb;
		if (!$user->rights->societe->client->voir && !$this->socid) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
		$sql.= " WHERE ".$this->where;
		$sql.= " GROUP BY dc";
        $sql.= $this->db->order('dc','DESC');

		return $this->_getNbByYear($sql);
	}

	/**
	 * Renvoie le nombre de commande par mois pour une annee donnee
	 *
	 */
	function getAmountByMonth($year)
	{
		global $conf;
		global $user;

		$sql = "SELECT date_format(p.datec,'%m') as dm, sum(pt.".$this->field.")";
		$sql.= " FROM ".$this->from;
		if (!$user->rights->societe->client->voir && !$this->socid) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
		$sql.= " WHERE date_format(p.datec,'%Y') = '".$year."'";
		$sql.= " AND ".$this->where;
		$sql.= " AND p.rowid = pt.fk_projet";
		$sql.= " GROUP BY dm";
        $sql.= $this->db->order('dm','DESC');

		return $this->_getAmountByMonth($year, $sql);
	}

	/**
	 * Renvoie le nombre de commande par mois pour une annee donnee
	 *
	 */
	function getAverageByMonth($year)
	{
		global $conf;
		global $user;

		$sql = "SELECT date_format(p.datec,'%m') as dm, avg(pt.".$this->field.")";
		$sql.= " FROM ".$this->from;
		if (!$user->rights->societe->client->voir && !$this->socid) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
		$sql.= " WHERE date_format(p.datec,'%Y') = '".$year."'";
		$sql.= " AND ".$this->where;
		$sql.= " AND p.rowid = pt.fk_projet";
		$sql.= " GROUP BY dm";
        $sql.= $this->db->order('dm','DESC');

		return $this->_getAverageByMonth($year, $sql);
	}


	/**
	 *	\brief	Return nb, total and average
	 *	\return	array	Array of values
	 */
	function getAllByYear()
	{
		global $user;

		$sql = "SELECT date_format(p.datec,'%Y') as year, count(*) as nb, sum(pt.".$this->field.") as total,";
		$sql.= " avg(pt.".$this->field.") as avg";
		$sql.= " FROM ".$this->from;
		if (!$user->rights->societe->client->voir && !$this->socid) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
		$sql.= " WHERE ".$this->where;
		$sql.= " AND p.rowid = pt.fk_projet";
		$sql.= " GROUP BY year";
        $sql.= $this->db->order('year','DESC');

		return $this->_getAllByYear($sql);
	}
}
?>