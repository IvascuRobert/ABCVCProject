<?php
/* Copyright (C) 2010-2012	Regis Houssin		<regis.houssin@capnetworks.com>
 * Copyright (C) 2010		Laurent Destailleur	<eldy@users.sourceforge.net>
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
 * or see http://www.gnu.org/
 */

/**
 *	\file       htdocs/core/modules/project/mod_project_simple.php
 *	\ingroup    project
 *	\brief      File with class to manage the numbering module Simple for project references
 */

require_once DOL_DOCUMENT_ROOT .'/core/modules/projectAbcvc/task/modules_taskAbcvc.php';


/**
 * 	Class to manage the numbering module Simple for project references
 */
class mod_taskAbcvc_simple extends ModeleNumRefTask
{
	var $version='dolibarr';		// 'development', 'experimental', 'dolibarr'
	var $prefix='TK';
    var $error='';
	var $nom = "Simple";
	var $name = "Simple";


    /**
     *  Return description of numbering module
     *
     *  @return     string      Text with description
     */
    function info()
    {
    	global $langs;
      	return $langs->trans("SimpleNumRefModelDesc",$this->prefix);
    }


    /**
     *  Return an example of numbering module values
     *
     * 	@return     string      Example
     */
    function getExample()
    {
        return $this->prefix."0501-0001";
    }


    /**  Test si les numeros deja en vigueur dans la base ne provoquent pas de
     *   de conflits qui empechera cette numerotation de fonctionner.
     *
     *   @return     boolean     false si conflit, true si ok
     */
    function canBeActivated()
    {
    	global $conf,$langs,$db;

        $coyymm=''; $max='';

		$posindice=8;
		$sql = "SELECT MAX(CAST(SUBSTRING(task.ref FROM " . $posindice . ") AS SIGNED)) as max";
		$sql .= " FROM " . MAIN_DB_PREFIX . "projet_task AS task, ";
		$sql .= MAIN_DB_PREFIX . "projet AS project WHERE task.fk_projet=project.rowid";
		$sql .= " AND task.ref LIKE '" . $this->prefix . "____-%'";
		$sql .= " AND project.entity = " . $conf->entity;
        $resql=$db->query($sql);
        if ($resql)
        {
            $row = $db->fetch_row($resql);
            if ($row) { $coyymm = substr($row[0],0,6); $max=$row[0]; }
        }
        if (! $coyymm || preg_match('/'.$this->prefix.'[0-9][0-9][0-9][0-9]/i',$coyymm))
        {
            return true;
        }
        else
        {
			$langs->load("errors");
			$this->error=$langs->trans('ErrorNumRefModel',$max);
            return false;
        }
    }


   /**
	*  Return next value
	*
	*  @param   Societe	$objsoc		Object third party
	*  @param   Task	$object		Object Task
	*  @return	string				Value if OK, 0 if KO
	*/
    function getNextValue($objsoc,$object)
    {
		global $db,$conf;

		// D'abord on recupere la valeur max
		$posindice=8;
		$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM ".$posindice.") AS SIGNED)) as max";
		$sql.= " FROM ".MAIN_DB_PREFIX."abcvc_projet_task";
		$sql.= " WHERE ref like '".$this->prefix."____-%'";

		$resql=$db->query($sql);
		if ($resql)
		{
			$obj = $db->fetch_object($resql);
			if ($obj) $max = intval($obj->max);
			else $max=0;
		}
		else
		{
			dol_syslog("mod_task_simple::getNextValue", LOG_DEBUG);
			return -1;
		}

		$date=empty($object->date_c)?dol_now():$object->date_c;

		//$yymm = strftime("%y%m",time());
		$yymm = strftime("%y%m",$date);

		if ($max >= (pow(10, 4) - 1)) $num=$max+1;	// If counter > 9999, we do not format on 4 chars, we take number as it is
		else $num = sprintf("%04s",$max+1);

		dol_syslog("mod_task_simple::getNextValue return ".$this->prefix.$yymm."-".$num);
		return $this->prefix.$yymm."-".$num;
    }


    /**
     * 	Return next reference not yet used as a reference
     *
     *  @param	Societe	$objsoc     Object third party
     *  @param  Task	$object		Object task
     *  @return string      		Next not used reference
     */
    function task_get_num($objsoc=0,$object='')
    {
        return $this->getNextValue($objsoc,$object);
    }
}

