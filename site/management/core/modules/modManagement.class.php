<?php
/* Copyright (C) 2012-2017 Charlie BENKE     <charlie@patas-monkey.com>
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
 *  \defgroup   projet     Module management
 *	\brief      Module to enhance dolibarr management modules (projet, contract, fichinter) 
 *  \file       management/core/modules/modManagement.class.php
 *	\ingroup    projet
 *	\brief      Fichier de description et activation du module management
 */
 
include_once DOL_DOCUMENT_ROOT ."/core/modules/DolibarrModules.class.php";

/**
*	\class	modManagement
 *	\brief	Classe de description et activation du module Projet
 */
class modmanagement extends DolibarrModules
{
	/**
	*   Constructor. Define names, constants, directories, boxes, permissions
	*
	*   @param      DoliDB		$db      Database handler
	*/
	function __construct($db)
	{
		global $conf, $langs;
		
		$langs->load('management@management');
		
		$this->db = $db;
		$this->numero = 160400;

		$this->editor_name = "<b>Patas-Monkey</b>";
		$this->editor_web = "http://www.patas-monkey.com";

		$this->family = "projects";

		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i','',get_class($this));
		$this->description = $langs->trans("InfoModuleManagement");
		
		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
		$this->version = $this->getLocalVersion();

		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->special = 0;
		$this->picto = $this->name.'@'.$this->name;

		// Data directories to create when module is enabled
		$this->dirs = array("/management/temp");

		// Config pages
		$this->config_page_url = array("admin.php@".$this->name);

		// Dependancies
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array($this->name.'@'.$this->name);

		// modules parts
		$this->module_parts = array( 
		'hooks' => array('agenda','contacttpl','propalcard','interventioncard'),	// used for agenda display		
		'triggers' => 1);

		// Constants
		$this->const = array();
		$r=0;

		// Boxes
		$this->boxes = array();
		$r=0;
		$this->boxes[$r][1] = "box_projet.php@management";
		$r++;
		$this->boxes[$r][1] = "box_task.php@management";
		$r++;
		
		// Permissions
		$this->rights = array();
		$this->rights_class = $this->name;

		$r=0;

		$this->rights[$r][0] = 1600401; // id de la permission
		$this->rights[$r][1] = "Lire les projets et taches (partagés ou dont je suis contact)"; // libelle de la permission
		$this->rights[$r][2] = 'r'; // type de la permission (deprecie a ce jour)
		$this->rights[$r][3] = 1; // La permission est-elle une permission par defaut
		$this->rights[$r][4] = 'lire';

		$r++;
		$this->rights[$r][0] = 1600402; // id de la permission
		$this->rights[$r][1] = "Creer/modifier les projets et taches (partagés ou dont je suis contact)"; // libelle de la permission
		$this->rights[$r][2] = 'w'; // type de la permission (deprecie a ce jour)
		$this->rights[$r][3] = 1; // La permission est-elle une permission par defaut
		$this->rights[$r][4] = 'creer';

		$r++;
		$this->rights[$r][0] = 1600405; // id de la permission
		$this->rights[$r][1] = "Saisir des temps"; // libelle de la permission
		$this->rights[$r][2] = 'S'; // type de la permission (deprecie a ce jour)
		$this->rights[$r][3] = 1; // La permission est-elle une permission par defaut
		$this->rights[$r][4] = 'saisir';

		$r++;
		$this->rights[$r][0] = 1600404; // id de la permission
		$this->rights[$r][1] = "Gérer l'aspect financier des temps"; // libelle de la permission
		$this->rights[$r][2] = 'p'; // type de la permission (deprecie a ce jour)
		$this->rights[$r][3] = 1; // La permission est-elle une permission par defaut
		$this->rights[$r][4] = 'showprice';

		$r++;
		$this->rights[$r][0] = 1600403; // id de la permission
		$this->rights[$r][1] = "visualiser tous les utilisateurs sur projet"; // libelle de la permission
		$this->rights[$r][2] = 'w'; // type de la permission (deprecie a ce jour)
		$this->rights[$r][3] = 1; // La permission est-elle une permission par defaut
		$this->rights[$r][4] = 'readuser';
		
		// Additionnal Left-Menu
		// PROJECT
		$r=0;
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=project,fk_leftmenu=projects',
					'type'=>'left',
					'titre'=>'ProjetStatistics',
					'mainmenu'=>'',
					'leftmenu'=>'',
					'url'=>'/management/projet/reporttime.php',
					'langs'=>'management@management',
					'position'=>100,
					'enabled'=>'$user->rights->projet->export',
					'perms'=>'1',
					'target'=>'',
					'user'=>2);
		$r++;
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=project,fk_leftmenu=projects',
					'type'=>'left',
					'titre'=>'TimeSheet',
					'mainmenu'=>'',
					'leftmenu'=>'',
					'url'=>'/management/projet/listtime.php',
					'langs'=>'management@management',
					'position'=>100,
					'enabled'=>'$user->rights->management->saisir',
					'perms'=>'1',
					'target'=>'',
					'user'=>2);
		$r++;
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=project,fk_leftmenu=projects',
					'type'=>'left',
					'titre'=>'TimeSheetWeek',
					'mainmenu'=>'',
					'leftmenu'=>'',
					'url'=>'/management/projet/listtimeweek.php',
					'langs'=>'management@management',
					'position'=>100,
					'enabled'=>'$user->rights->management->saisir',
					'perms'=>'1',
					'target'=>'',
					'user'=>2);
		$r++;
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=project,fk_leftmenu=projects',
					'type'=>'left',
					'titre'=>'StartStopTime',
					'mainmenu'=>'',
					'leftmenu'=>'',
					'url'=>'/management/projet/startstoptime.php',
					'langs'=>'management@management',
					'position'=>100,
					'enabled'=>'$user->rights->management->saisir',
					'perms'=>'1',
					'target'=>'',
					'user'=>2);

		// fich inter
		// Additionnal Left-Menu
		$r++;

		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=commercial,fk_leftmenu=ficheinter',
					'type'=>'left',
					'titre'=>'Statistisques',
					'mainmenu'=>'',
					'leftmenu'=>'',
					'url'=>'/management/fichinter/stats/index.php',
					'langs'=>'management@management',
					'position'=>100,
					'enabled'=>'1',
					'perms'=>'1',
					'target'=>'',
					'user'=>2);
		$r++;
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=commercial,fk_leftmenu=ficheinter',
					'type'=>'left',
					'titre'=>'FichInterModelList',
					'mainmenu'=>'',
					'leftmenu'=>'',
					'url'=>'/management/fichinter/fiche-rec.php',
					'langs'=>'management@management',
					'position'=>100,
					'enabled'=>'1',
					'perms'=>'1',
					'target'=>'',
					'user'=>2);
		$r++;
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=commercial,fk_leftmenu=ficheinter',
					'type'=>'left',
					'titre'=>'ListTotal',
					'mainmenu'=>'',
					'leftmenu'=>'',
					'url'=>'/management/fichinter/listtotal.php',
					'langs'=>'management@management',
					'position'=>100,
					'enabled'=>'1',
					'perms'=>'1',
					'target'=>'',
					'user'=>2);
		///// Contrat additionnal menu
		$r++;
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=commercial,fk_leftmenu=contracts',
					'type'=>'left',
					'titre'=>'TermsToBills',
					'mainmenu'=>'',
					'leftmenu'=>'',
					'url'=>'/management/contrat/termtobill.php',
					'langs'=>'management@management',
					'position'=>100,
					'enabled'=>'1',
					'perms'=>'1',
					'target'=>'',
					'user'=>2);

		$r++;
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=project,fk_leftmenu=projects',
			'type'=>'left',
			'titre'=>'Agenda',
			'mainmenu'=>'',
			'leftmenu'=>'',
			'url'=>'/management/projet/tasks/calendar.php',
			'langs'=>'management@management',
			'position'=>100,
			'enabled'=>'$user->rights->projet->lire',
			'perms'=>'1',
			'target'=>'',
			'user'=>2);

		$r++;
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=commercial,fk_leftmenu=ficheinter',
				'type'=>'left',
				'titre'=>'Agenda',
				'mainmenu'=>'',
				'leftmenu'=>'',
				'url'=>'/management/fichinter/calendar.php',
				'langs'=>'management@management',
				'position'=>100,
				'enabled'=>'1',
				'perms'=>'1',
				'target'=>'',
				'user'=>2);	



		// additional tabs
		$managementArray = array(
			'project:+billproject:BillProject:@management:/management/projet/billproject.php?id=__ID__',
			'project:+management:Management:@management:/management/projet/reportproject.php?id=__ID__',
			'task:+management:Management:@management:/management/projet/costintask.php?id=__ID__&withproject=1',
			'intervention:+Rapport:Rapport:@fichinter:/management/fichinter/rapport.php?id=__ID__',
			'contract:+referent:Referents:@contracts:/management/contrat/element.php?id=__ID__',
			'contract:+terms:Terms:@contracts:/management/contrat/terms.php?id=__ID__'
		);

		// on ajout l'acces au thm pour les versions plus ancienne que la 3.7
		if (DOL_VERSION < "3.7.0")
		{
			$userArray = array(
			'user:+management:Management:@management:'.dol_buildpath('/management/',1).'userthm.php?id=__ID__'	
			);
			$this->tabs = array_merge($userArray, $managementArray);
		}
		else
			$this->tabs = $managementArray;
	}


	/**
	 *		Function called when module is enabled.
	 *		The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *		It also creates data directories
	 *
     *      @param      string	$options    Options when enabling module ('', 'noboxes')
	 *      @return     int             	1 if OK, 0 if KO
	 */
	function init($options='')
	{
		global $conf;

		// Permissions
		$this->remove($options);

		$result=$this->load_tables();

		$sql = array();

		return $this->_init($sql,$options);

	}

    /**
	 *		Function called when module is disabled.
	 *      Remove from database constants, boxes and permissions from Dolibarr database.
	 *		Data directories are not deleted
	 *
     *      @param      string	$options    Options when enabling module ('', 'noboxes')
	 *      @return     int             	1 if OK, 0 if KO
     */
    function remove($options='')
    {
		$sql = array();

		return $this->_remove($sql,$options);
    }

	/**
	 *		Create tables, keys and data required by module
	 * 		Files llx_table1.sql, llx_table1.key.sql llx_data.sql with create table, create keys
	 * 		and create data commands must be stored in directory /mymodule/sql/
	 *		This function is called by this->init.
	 *
	 * 		@return		int		<=0 if KO, >0 if OK
	 */
	function load_tables()
	{
		return $this->_load_tables('/management/sql/');
	}

	function getVersion()
	{
		global $langs, $conf;
		$currentversion = $this->version;
		
		if ($conf->global->PATASMONKEY_SKIP_CHECKVERSION == 1)
			return $currentversion;

		if ($this->disabled)
		{
			$newversion= $langs->trans("DolibarrMinVersionRequiered")." : ".$this->dolibarrminversion;
			$currentversion="<font color=red><b>".img_error($newversion).$currentversion."</b></font>";
			return $currentversion;
		}

		$context  = stream_context_create(array('http' => array('header' => 'Accept: application/xml')));
		$changelog = @file_get_contents(str_replace("www","dlbdemo",$this->editor_web).'/htdocs/custom/'.$this->name.'/changelog.xml',false,$context);
		//$htmlversion = @file_get_contents($this->editor_web.$this->editor_version_folder.$this->name.'/');

		if($htmlversion === false)	// not connected
			return $currentversion;
		else
		{
			$sxelast = simplexml_load_string(nl2br ($changelog));
			if ($sxelast === false) 
			{
				return $currentversion;
			}
			else
				$tblversionslast=$sxelast->Version;

			$lastversion = $tblversionslast[count($tblversionslast)-1]->attributes()->Number;

			if ($lastversion != (string) $this->version)
			{
				if ($lastversion > (string) $this->version)	
				{
					$newversion= $langs->trans("NewVersionAviable")." : ".$lastversion;
					$currentversion="<font title='".$newversion."' color=orange><b>".$currentversion."</b></font>";
				}
				else
					$currentversion="<font title='Version Pilote' color=red><b>".$currentversion."</b></font>";
			}
		}
		return $currentversion;
	}

	function getLocalVersion()
	{
		global $langs;
		$context  = stream_context_create(array('http' => array('header' => 'Accept: application/xml')));
		$changelog = @file_get_contents(dol_buildpath($this->name,0).'/changelog.xml',false,$context);
		$sxelast = simplexml_load_string(nl2br ($changelog));
		if ($sxelast === false) 
			return $langs->trans("ChangelogXMLError");
		else
		{
			$tblversionslast=$sxelast->Version;
			$currentversion = $tblversionslast[count($tblversionslast)-1]->attributes()->Number;
			$tblDolibarr=$sxelast->Dolibarr;
			$MinversionDolibarr=$tblDolibarr->attributes()->minVersion;
			if (DOL_VERSION < $MinversionDolibarr)
			{
				$this->dolibarrminversion=$MinversionDolibarr;
				$this->disabled = true;
			}
		}
		return $currentversion;
	}

}
?>
