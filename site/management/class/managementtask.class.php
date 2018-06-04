<?php
/* Copyright (C) 2012-2016	Charlie BENKE	<charlie@patas-monkey.com>
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
 *	  \file		management/class/managementtask.class.php
 *	  \ingroup	management
 *	  \brief	This file is a extention of the native class file for Task 
 */

require_once DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php";
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';



class ManagementTask extends Task
{
	public $element='management_managementtask';
	var $average_thm;
	var $fk_product;
	var $billingmode;
	var $tobill;

    var $lines = array();

	/**
	 *  Constructor
	 *
	 *  @param	  DoliDB		$db	  Database handler
	 */
	function __construct($db)
	{
		$this->db = $db;
	}

    /**
     *  Load object in memory from database
     *
     *  @param	int		$id			Id object
     *  @param	int		$ref		ref object
     *  @return int 		        <0 if KO, >0 if OK
     */
    function fetchMT($id, $ref='')
    {
		global $langs;
		
		$sql = "SELECT";
		$sql.= " t.average_thm,";
		$sql.= " t.fk_product,";
		$sql.= " t.billingmode";
		
		$sql.= " FROM ".MAIN_DB_PREFIX."projet_task as t";
		$sql.= " WHERE ";
		if (!empty($ref))
			$sql.="t.ref = '".$ref."'";
		else
			$sql.="t.rowid = ".$id;

		dol_syslog(get_class($this)."::fetchMT sql=".$sql, LOG_DEBUG);
		$resql=$this->db->query($sql);
		if ($resql)
		{
			if ($this->db->num_rows($resql))
			{
				$obj = $this->db->fetch_object($resql);

				$this->average_thm	= $obj->average_thm;
				$this->fk_product	= $obj->fk_product;
				$this->billingmode	= $obj->billingmode;
				
			}

			$this->db->free($resql);
			return 1;
		}
		else
		{
			$this->error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);
			return -1;
		}
	}

	function fetch_lines()
	{
		$this->fetchMT($this->id);


		$this->lines=array();
		
		$line = new ManagementTask($this->db);

		$line->date_start		= $this->db->jdate($this->dateo);
		$line->date_end			= $this->db->jdate($this->datee);
		$line->fk_product		= $this->fk_product;
		$line->desc				= $this->ref;
		$line->qty				= round($this->planned_workload/3600,2);
		// il faut récupérer le thm moyen calculé
		if ($this->planned_workload != 0)
			$line->pa_ht 			= (($this->duration_effective/3600)*$this->average_thm)/($this->planned_workload/3600);
		else
			$line->pa_ht=0;

		// is product associated to the task
		if ($this->fk_product > 0)
		{
			$productstatic = new Product($this->db);
			$productstatic->fetch($this->fk_product);
			$line->ref				= $productstatic->ref;
			$line->fk_product_type	= $productstatic->type;
			$line->product_label	= $productstatic->label;
			$line->tva_tx 			= $productstatic->tva_tx;
			$line->subprice			= $productstatic->price;
		}

		$this->lines[0] = $line;
		return $this->lines;
	}

	function setproduct($fk_product)
	{
		global $conf;
		
		$sql = "UPDATE " . MAIN_DB_PREFIX . "projet_task";
		$sql.= " SET fk_product = ".($fk_product?$fk_product:"null");
		$sql.= " WHERE rowid = " . $this->id;
		$sql.= " AND entity = " . $conf->entity;

		$resql = $this->db->query($sql);
	}

	function setbillingmode($billingmode)
	{
		global $conf;
		
		$sql = "UPDATE " . MAIN_DB_PREFIX . "projet_task";
		$sql.= " SET billingmode = ".($billingmode?$billingmode:"0");
		$sql.= " WHERE rowid = " . $this->id;
		$sql.= " AND entity = " . $conf->entity;

		$resql = $this->db->query($sql);
	}

	function setaveragethm($averagethm)
	{
		global $conf;
		
		$sql = "UPDATE " . MAIN_DB_PREFIX . "projet_task";
		$sql.= " SET average_thm = ".($averagethm?$averagethm:"null");
		$sql.= " WHERE rowid = " . $this->id;
		$sql.= " AND entity = " . $conf->entity;

		$resql = $this->db->query($sql);
	}

	function get_thm()
	{
		global $conf;
		
		$moyenne=0;
		
		$sql = "SELECT sum(thm*task_duration)/sum(task_duration) as moyenne from " . MAIN_DB_PREFIX . "projet_task_time";
		$sql.= " WHERE fk_task = " . $this->id;

		$resql = $this->db->query($sql);
//print $sql."<br>";
		dol_syslog(get_class($this)."::get_thm sql=".$sql, LOG_DEBUG);
		$resql=$this->db->query($sql);
		if ($resql)
		{
			if ($this->db->num_rows($resql))
			{
				$obj = $this->db->fetch_object($resql);
				$moyenne	= $obj->moyenne;
			}
			$this->db->free($resql);
		}
		else
		{
			$this->error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::get_thm ".$this->error, LOG_ERR);

		}
		return $moyenne;
	}

	/**
	 *  Create into database
	 *
	 *  @param	User	$user			User that create
	 *  @param 	int		$notrigger		0=launch triggers after, 1=disable triggers
	 *  @return int 					<0 if KO, Id of created object if OK
	 */
	function create($user, $notrigger=0)
	{
		global $conf, $langs;

		$error=0;

		// Clean parameters
		$this->label = trim($this->label);
		$this->description = trim($this->description);

		// Check parameters
		// Put here code to add control on parameters values
		if ($this->progress==0)
		{
			if ($this->planned_workload == 0)
			{	$this->fk_statut=0; }
			else
			{	$this->fk_statut=1; }
		}
		elseif ($this->progress!=100)
		{	$this->fk_statut=2; }
		else
		{	$this->fk_statut=3; }

		if ($this->total_ht!='')
			$total_ht=price2num($this->total_ht);
		else
			$total_ht=price2num($this->subprice*$this->qty);

		// Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."projet_task (";
		$sql.= "fk_projet";
		$sql.= ", ref";
		$sql.= ", fk_task_parent";
		$sql.= ", label";
		$sql.= ", description";
		$sql.= ", datec";
		$sql.= ", fk_user_creat";
		$sql.= ", dateo";
		$sql.= ", datee";
		$sql.= ", planned_workload";
		$sql.= ", progress";
		$sql.= ", total_ht";
		$sql.= ", qty";
		$sql.= ", subprice";
		$sql.= ", fk_statut";
		$sql.= ") VALUES (";
		$sql.= $this->fk_project;
		$sql.= ", ".(!empty($this->ref)?"'".$this->db->escape($this->ref)."'":'null');
		$sql.= ", ".$this->fk_task_parent;
		$sql.= ", '".$this->db->escape($this->label)."'";
		$sql.= ", '".$this->db->escape($this->description)."'";
		$sql.= ", '".$this->db->idate($this->date_c)."'";
		$sql.= ", ".$user->id;
		$sql.= ", ".($this->date_start!=''?"'".$this->db->idate($this->date_start)."'":'null');
		$sql.= ", ".($this->date_end!=''?"'".$this->db->idate($this->date_end)."'":'null');
		$sql.= ", ".($this->planned_workload!=''?$this->planned_workload:0);
		$sql.= ", ".($this->progress!=''?$this->progress:0);
		$sql.= ", ".$total_ht;
		$sql.= ", ".($this->qty!=''?$this->qty:0);
		$sql.= ", ".($this->subprice!=''?price2num($this->subprice):'null');
		$sql.= ", ".$this->fk_statut;
		$sql.= ")";

		$this->db->begin();

		dol_syslog(get_class($this)."::create sql=".$sql, LOG_DEBUG);
		$resql=$this->db->query($sql);
		if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
		{
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."projet_task");

			if (! $notrigger)
			{
				// Call triggers
				include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
				$interface=new Interfaces($this->db);
				$result=$interface->run_triggers('TASK_CREATE',$this,$user,$langs,$conf);
				if ($result < 0) { $error++; $this->errors=$interface->errors; }
				// End call triggers
			}
		}

		//Update extrafield
		if (!$error) {
			if (empty($conf->global->MAIN_EXTRAFIELDS_DISABLED)) // For avoid conflicts if trigger used
			{
				$result=$this->insertExtraFields();
				if ($result < 0)
				{
					$error++;
				}
			}
		}

		// Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
				dol_syslog(get_class($this)."::create ".$errmsg, LOG_ERR);
				$this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
			
		}
		else
		{
			$this->db->commit();
			return $this->id;
		}
	}


	/**
	 *  Update database
	 *
	 *  @param	User	$user			User that modify
	 *  @param  int		$notrigger		0=launch triggers after, 1=disable triggers
	 *  @return int					 	<0 if KO, >0 if OK
	 */
	function update($user=0, $notrigger=0)
	{
		global $conf, $langs;
		$error=0;

		// Clean parameters
		if (isset($this->fk_project)) $this->fk_project=trim($this->fk_project);
		if (isset($this->ref)) $this->ref=trim($this->ref);
		if (isset($this->fk_task_parent)) $this->fk_task_parent=trim($this->fk_task_parent);
		if (isset($this->label)) $this->label=trim($this->label);
		if (isset($this->description)) $this->description=trim($this->description);
		if (isset($this->duration_effective)) $this->duration_effective=trim($this->duration_effective);
		if (isset($this->planned_workload)) $this->planned_workload=trim($this->planned_workload);
		
		// Check parameters
		// Put here code to add control on parameters values

		//change status value
		if ($this->progress==0)
		{
			if ($this->planned_workload == 0)
			{	$this->fk_statut=0;	}
			else
			{	$this->fk_statut=1; }
		}
		elseif ($this->progress!=100)
		{
			$this->fk_statut=2;
		}
		else
		{	// progress = 100
			if ($this->fk_statut!=4)
				$this->fk_statut=3;
		}

		// Update request
		$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task SET";
		$sql.= " fk_projet=".(isset($this->fk_project)?$this->fk_project:"null").",";
		$sql.= " ref=".(isset($this->ref)?"'".$this->db->escape($this->ref)."'":"'".$this->id."'").",";
		$sql.= " fk_task_parent=".(isset($this->fk_task_parent)?$this->fk_task_parent:"null").",";
		$sql.= " label=".(isset($this->label)?"'".$this->db->escape($this->label)."'":"null").",";
		$sql.= " description=".(isset($this->description)?"'".$this->db->escape($this->description)."'":"null").",";
        $sql.= " duration_effective=".(isset($this->duration_effective)?$this->duration_effective:"null").",";
        $sql.= " planned_workload=".(isset($this->planned_workload)?$this->planned_workload:"0").",";
		$sql.= " dateo=".($this->date_start!=''?$this->db->idate($this->date_start):'null').",";
		$sql.= " datee=".($this->date_end!=''?$this->db->idate($this->date_end):'null').",";
		$sql.= " progress=".$this->progress.",";
        $sql.= " rang=".((!empty($this->rang))?$this->rang:"0").",";
		$sql.= " subprice=".($this->subprice!=''?price2num($this->subprice):"null").",";
		$sql.= " qty=".($this->qty!=''?price2num($this->qty):"null").",";
		$sql.= " total_ht=".($this->total_ht!=''?price2num($this->total_ht):"null").",";
		$sql.= " fk_statut=".$this->fk_statut;
		$sql.= " WHERE rowid=".$this->id;

		$this->db->begin();

		dol_syslog(get_class($this)."::update sql=".$sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
		{
			if (! $notrigger)
			{
				// Call triggers
				include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
				$interface=new Interfaces($this->db);
				$result=$interface->run_triggers('TASK_MODIFY',$this,$user,$langs,$conf);
				if ($result < 0) { $error++; $this->errors=$interface->errors; }
				// End call triggers
			}
		}

		//Update extrafield
		if (!$error) {
			if (empty($conf->global->MAIN_EXTRAFIELDS_DISABLED)) // For avoid conflicts if trigger used
			{
				$result=$this->insertExtraFields();
				if ($result < 0)
				{
					$error++;
				}
			}
		}

		// Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
				dol_syslog(get_class($this)."::update ".$errmsg, LOG_ERR);
				$this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return 1;
		}
	}

	/**
	 * Return list of tasks for all projects or for one particular project
	 * Sort order is on project, then of position of task, and last on title of first level task
	 *
	 * @param	User	$usert				Object user to limit tasks affected to a particular user
	 * @param	User	$userp				Object user to limit projects of a particular user and public projects
	 * @param	int		$projectid			Project id
	 * @param	int		$socid				Third party id
	 * @param	int		$mode				0=Return list of tasks and their projects, 1=Return projects and tasks if exists
	 * @param	string	$filteronprojref	Filter on project ref
     * @param	string	$filteronprojstatus	Filter on project status
	 * @return 	array						Array of tasks
	 */
	function getTasksArray($usert=0, $userp=0, $projectid=0, $socid=0, $mode=0, $filteronprojref='', $filteronprojstatus=-1, $filteronDateCreate='')
	{
		global $conf;

		$tasks = array();

		//print $usert.'-'.$userp.'-'.$projectid.'-'.$socid.'-'.$mode.'<br>';

		// List of tasks (does not care about permissions. Filtering will be done later)
		$sql = "SELECT p.rowid as projectid, p.ref, p.title as plabel, p.public,";
		$sql.= " t.rowid as taskid, t.label, t.description, t.fk_task_parent, t.duration_effective, t.progress,";
		$sql.= " t.dateo as date_start, t.datee as date_end, t.planned_workload, t.ref as ref_task, t.rang,";
		$sql.= " t.total_ht, t.qty, t.subprice, t.fk_statut";
		if ($mode == 0)
		{
			$sql.= " FROM ".MAIN_DB_PREFIX."projet as p";
			$sql.= ", ".MAIN_DB_PREFIX."projet_task as t";
			$sql.= " WHERE t.fk_projet = p.rowid";
			$sql.= " AND p.entity = ".$conf->entity;
			if ($socid)	$sql.= " AND p.fk_soc = ".$socid;
			if ($projectid) $sql.= " AND p.rowid in (".$projectid.")";
		}
		if ($mode == 1)
		{
			$sql.= " FROM ".MAIN_DB_PREFIX."projet as p";
			$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."projet_task as t on t.fk_projet = p.rowid";
			$sql.= " WHERE p.entity = ".$conf->entity;
			if ($socid)	$sql.= " AND p.fk_soc = ".$socid;
			if ($projectid) $sql.= " AND p.rowid in (".$projectid.")";
		}
		if ($filteronprojref) $sql.= " AND p.ref LIKE '%".$filteronprojref."%'";
		if ($filteronprojstatus > -1) $sql.= " AND t.fk_statut = ".$filteronprojstatus;
		if ($filteronDateCreate)
		{
			if (strpos($filteronDateCreate, "+") > 0)
			{
				// mode plage
				$DateCreateArray = explode("+", $filteronDateCreate);
				$sql.=" AND (".$this->conditionDate('t.dateo',$DateCreateArray[0],">=");
				$sql.=" AND ".$this->conditionDate('t.dateo',$DateCreateArray[1],"<=").")";
			}
			else
			{
				if (is_numeric(substr($filteronDateCreate,0,1)))
					$sql.=" AND ".$this->conditionDate('t.dateo',$filteronDateCreate,"=");
				else
					$sql.=" AND ".$this->conditionDate('t.dateo',substr($filteronDateCreate,1),substr($filteronDateCreate,0,1));
			}

			$sql.= " ORDER BY  t.rang, t.dateo, t.label";
		}
		else
			$sql.= " ORDER BY p.ref, t.rang, t.dateo, t.label";

		//print $sql;
		dol_syslog(get_class($this)."::getTasksArray sql=".$sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql)
		{
			$num = $this->db->num_rows($resql);
			$i = 0;
			// Loop on each record found, so each couple (project id, task id)
			while ($i < $num)
			{
				$error=0;

				$obj = $this->db->fetch_object($resql);

				if ((! $obj->public) && (is_object($userp)))	// If not public project and we ask a filter on project owned by a user
				{
					if (! $this->getUserRolesForProjectsOrTasks($userp, 0, $obj->projectid, 0))
					{
						$error++;
					}
				}
				if (is_object($usert))							// If we ask a filter on a user affected to a task
				{
					if (! $this->getUserRolesForProjectsOrTasks(0, $usert, $obj->projectid, $obj->taskid))
					{
						$error++;
					}
				}

				if (! $error)
				{
					$tasks[$i] 					= new Task($db);
					$tasks[$i]->id		   		= $obj->taskid;
					$tasks[$i]->ref		  		= $obj->ref_task;
					$tasks[$i]->fk_project  	= $obj->projectid;
					$tasks[$i]->projectref  	= $obj->ref;
					$tasks[$i]->projectlabel 	= $obj->plabel;
					$tasks[$i]->label			= $obj->label;
					$tasks[$i]->description 	= $obj->description;
					$tasks[$i]->fk_parent		= $obj->fk_task_parent;
					$tasks[$i]->duration		= $obj->duration_effective;
					$tasks[$i]->planned_workload= $obj->planned_workload;
					$tasks[$i]->progress	 	= $obj->progress;
					$tasks[$i]->public	   		= $obj->public;
					$tasks[$i]->date_start   	= $this->db->jdate($obj->date_start);
					$tasks[$i]->date_end	 	= $this->db->jdate($obj->date_end);
					$tasks[$i]->rang			= $obj->rang;
					$tasks[$i]->subprice	 	= $obj->subprice;
					$tasks[$i]->qty	 			= $obj->qty;
					$tasks[$i]->total_ht	 	= $obj->total_ht;
					$tasks[$i]->status	   		= $obj->fk_statut;
				}

				$i++;
			}
			$this->db->free($resql);
		}
		else
		{
			dol_print_error($this->db);
		}

		return $tasks;
	}

	function updateprogress()
	{
		$localid=$this->id;
		$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task";
		$sql.= " SET progress = (duration_effective / planned_workload)*100";
		$sql.= " , fk_statut=2"; 
		$sql.= " WHERE rowid = ".$this->id;
		$sql.= " and planned_workload > 0";
		dol_syslog(get_class($this)."::updateprogress sql=".$sql, LOG_DEBUG);
		if (! $this->db->query($sql) )
		{
			$this->error=$this->db->lasterror();
			dol_syslog(get_class($this)."::updateprogress error -2 ".$this->error, LOG_ERR);
			$ret = -2;
		}		
		
		$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task";
		$sql.= " SET progress = 0";
		$sql.= " , fk_statut=1"; 
		$sql.= " WHERE rowid = ".$localid;
		$sql.= " and duration_effective =0";

		dol_syslog(get_class($this)."::updateprogress sql=".$sql, LOG_DEBUG);
		if (! $this->db->query($sql) )
		{
			$this->error=$this->db->lasterror();
			dol_syslog(get_class($this)."::updateprogress error -2 ".$this->error, LOG_ERR);
			$ret = -2;
		}
	}

	/**
	 *	\brief	  return time spent of the task
	 *	\param	  id		  id object
	 *	\return	 int		 duration Spent of the task
	 */
	function getTaskTimeSpent($id)
	{
		$res=0;
		
		$sql = "SELECT sum(t.task_duration) as total_duration";
		$sql.= " FROM ".MAIN_DB_PREFIX."projet_task_time as t";
		$sql.= " WHERE t.fk_task = ".$id;
		
		dol_syslog(get_class($this)."::getTaskTimeSpent sql=".$sql, LOG_DEBUG);
		$resql=$this->db->query($sql);
		if ($resql)
		{
			if ($this->db->num_rows($resql))
			{
				$obj = $this->db->fetch_object($resql);
				$res = $obj->total_duration;
			}
			$this->db->free($resql);
			
		}
		return $res;
	}

	/**
	 *	Update time spent
	 *
	 *  @param	User	$user			User id
	 *  @param  int		$notrigger		0=launch triggers after, 1=disable triggers
	 *  @return	int						<0 if KO, >0 if OK
	 */
	function updateTimeSpent($user, $notrigger=0)
	{
		global $conf,$langs;

		$error=0;
		$ret = 0;

		// Clean parameters
		if (isset($this->timespent_note)) $this->timespent_note = trim($this->timespent_note);

		$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task_time SET";
		$sql.= " task_date = '".$this->db->idate($this->timespent_date)."',";
		$sql.= " task_duration = ".$this->timespent_duration.",";
		$sql.= " fk_user = ".$this->timespent_fk_user.",";
		$sql.= " note = ".(isset($this->timespent_note)?"'".$this->db->escape($this->timespent_note)."'":"null");
		$sql.= " WHERE rowid = ".$this->timespent_id;

		dol_syslog(get_class($this)."::updateTimeSpent sql=".$sql, LOG_DEBUG);
		if ($this->db->query($sql) )
		{
			if (! $notrigger)
			{
				// Call triggers
				include_once DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php";
				$interface=new Interfaces($this->db);
				$result=$interface->run_triggers('TASK_TIMESPENT_MODIFY',$this,$user,$langs,$conf);
				if ($result < 0) { $error++; $this->errors=$interface->errors; }
				// End call triggers
			}
			$ret = 1;
		}
		else
		{
			$this->error=$this->db->lasterror();
			dol_syslog(get_class($this)."::updateTimeSpent error -1 ".$this->error,LOG_ERR);
			$ret = -1;
		}

		if ($ret == 1 && ($this->timespent_old_duration != $this->timespent_duration))
		{
			$newDuration = $this->timespent_duration - $this->timespent_old_duration;

			$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task";
			$sql.= " SET duration_effective = duration_effective + '".$newDuration."'";
			$sql.= " WHERE rowid = ".$this->id;

			dol_syslog(get_class($this)."::updateTimeSpent sql=".$sql, LOG_DEBUG);
			if (! $this->db->query($sql) )
			{
				$this->error=$this->db->lasterror();
				dol_syslog(get_class($this)."::addTimeSpent error -2 ".$this->error, LOG_ERR);
				$ret = -2;
			}
			else
				$this->updateprogress();
		}

		return $ret;
	}

	/**
	 *  Delete time spent
	 *
	 *  @param	User	$user			User that delete
	 *  @param  int		$notrigger		0=launch triggers after, 1=disable triggers
	 *  @return	int						<0 if KO, >0 if OK
	 */
	function delTimeSpent($user, $notrigger=0)
	{
		global $conf, $langs;

		$error=0;

		$this->db->begin();

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."projet_task_time";
		$sql.= " WHERE rowid = ".$this->timespent_id;

		dol_syslog(get_class($this)."::delTimeSpent sql=".$sql);
		$resql = $this->db->query($sql);
		if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
		{
			if (! $notrigger)
			{
				// Call triggers
				include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
				$interface=new Interfaces($this->db);
				$result=$interface->run_triggers('TASK_TIMESPENT_DELETE',$this,$user,$langs,$conf);
				if ($result < 0) { $error++; $this->errors=$interface->errors; }
				// End call triggers
			}
		}

		if (! $error)
		{
			$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task";
			$sql.= " SET duration_effective = duration_effective - '".$this->timespent_duration."'";
			$sql.= " WHERE rowid = ".$this->id;

			dol_syslog(get_class($this)."::delTimeSpent sql=".$sql, LOG_DEBUG);
			if ($this->db->query($sql) )
			{
				$result = 0;
			}
			else
			{
				$this->error=$this->db->lasterror();
				dol_syslog(get_class($this)."::addTimeSpent error -3 ".$this->error, LOG_ERR);
				$result = -2;
			}
		}
		
		$this->updateprogress();
		
		// Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
				dol_syslog(get_class($this)."::delTimeSpent ".$errmsg, LOG_ERR);
				$this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return 1;
		}
	}

	/**	Load an object from its id and create a new one in database
	 *
	 *	@param	int		$fromid     			Id of object to clone
	 *  @param	int		$project_id				Id of project to attach clone task
	 *  @param	int		$parent_task_id			Id of task to attach clone task
	 *  @param	bool	$clone_change_dt		recalculate date of task regarding new project start date
	 *	@param	bool	$clone_affectation		clone affectation of project
	 *	@param	bool	$clone_time				clone time of project
	 *	@param	bool	$clone_file				clone file of project
	 *  @param	bool	$clone_note				clone note of project
	 *	@param	bool	$clone_prog				clone progress of project
	 * 	@return	int								New id of clone
	 */
	function createFromClone($fromid,$project_id,$parent_task_id,$clone_change_dt=false,$clone_affectation=false,$clone_time=false,$clone_file=false,$clone_note=false,$clone_prog=false)
	{
		global $user,$langs,$conf;

		$error=0;

		$now=dol_now();

		$datec = $now;

		$clone_task=new Task($this->db);
		$origin_task=new Task($this->db);
		
		$this->db->begin();

		// Load source object
		$clone_task->fetch($fromid);
		$origin_task->fetch($fromid);
		
		$defaultref='';
		$obj = empty($conf->global->PROJECT_TASK_ADDON)?'mod_task_simple':$conf->global->PROJECT_TASK_ADDON;
		if (! empty($conf->global->PROJECT_TASK_ADDON) && is_readable(DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.".php"))
		{
			require_once DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.'.php';
			$modTask = new $obj;
			$defaultref = $modTask->getNextValue(0,$clone_task);
		}
		
		$ori_project_id					= $clone_task->fk_project;

		$clone_task->id					= 0;
		$clone_task->ref				= $defaultref;
		$clone_task->fk_project			= $project_id;
		$clone_task->fk_task_parent		= $parent_task_id;
		$clone_task->date_c				= $datec;
        $clone_task->planned_workload	= $origin_task->planned_workload;
		$clone_task->rang				= $origin_task->rang;

// CFB AJOUTER LKS CHAMPS EN PLUS

		//Manage Task Date
		if ($clone_change_dt)
		{
			$projectstatic=new Project($this->db);
			$projectstatic->fetch($ori_project_id);

			//Origin project strat date
			$orign_project_dt_start = $projectstatic->date_start;

			//Calcultate new task start date with difference between origin proj start date and origin task start date
			if (!empty($clone_task->date_start))
			{
				$clone_task->date_start		= $now + $clone_task->date_start - $orign_project_dt_start;
			}

			//Calcultate new task end date with difference between origin proj end date and origin task end date
			if (!empty($clone_task->date_end))
			{
				$clone_task->date_end		= $now + $clone_task->date_end - $orign_project_dt_start;
			}

		}

		if (!$clone_prog)
		{
			$clone_task->progress=0;
		}

		// Create clone
		$result=$clone_task->create($user);

		// Other options
		if ($result < 0)
		{
			$this->error=$clone_task->error;
			$error++;
		}

		// End
		if (! $error)
		{
			$this->db->commit();

			$clone_task_id=$clone_task->id;
			$clone_task_ref = $clone_task->ref;
			
			//Note Update
			if (!$clone_note)
			{
				$clone_task->note_private='';
				$clone_task->note_public='';
			}
			else
			{
				$this->db->begin();
				$res=$clone_task->update_note(dol_html_entity_decode($clone_task->note_public, ENT_QUOTES),'_public');
				if ($res < 0)
				{
					$this->error.=$clone_task->error;
					$error++;
					$this->db->rollback();
				}
				else
				{
					$this->db->commit();
				}

				$this->db->begin();
				$res=$clone_task->update_note(dol_html_entity_decode($clone_task->note_private, ENT_QUOTES), '_private');
				if ($res < 0)
				{
					$this->error.=$clone_task->error;
					$error++;
					$this->db->rollback();
				}
				else
				{
					$this->db->commit();
				}
			}

			//Duplicate file
			if ($clone_file)
			{
				require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

				//retreive project origin ref to know folder to copy
				$projectstatic=new Project($this->db);
				$projectstatic->fetch($ori_project_id);
				$ori_project_ref=$projectstatic->ref;
			
				if ($ori_project_id!=$project_id)
				{
					$projectstatic->fetch($project_id);
					$clone_project_ref=$projectstatic->ref;
				}
				else
				{
					$clone_project_ref=$ori_project_ref;
				}

				$clone_task_dir = $conf->projet->dir_output . "/" . dol_sanitizeFileName($clone_project_ref). "/" . dol_sanitizeFileName($clone_task_id);
				$ori_task_dir = $conf->projet->dir_output . "/" . dol_sanitizeFileName($ori_project_ref). "/" . dol_sanitizeFileName($fromid);

				$filearray=dol_dir_list($ori_task_dir,"files",0,'','\.meta$','',SORT_ASC,1);
				foreach($filearray as $key => $file)
				{
					if (!file_exists($clone_task_dir))
					{
						if (dol_mkdir($clone_task_dir) < 0)
						{
							$this->error.=$langs->trans('ErrorInternalErrorDetected').':dol_mkdir';
							$error++;
						}
					}

					$rescopy = dol_copy($ori_task_dir . '/' . $file['name'], $clone_task_dir . '/' . $file['name'],0,1);
					if (is_numeric($rescopy) && $rescopy < 0)
					{
						$this->error.=$langs->trans("ErrorFailToCopyFile",$ori_task_dir . '/' . $file['name'],$clone_task_dir . '/' . $file['name']);
						$error++;
					}
				}
			}

			// clone affectation
			if ($clone_affectation)
			{
				$origin_task = new Task($this->db);
				$origin_task->fetch($fromid);

				foreach(array('internal','external') as $source)
				{
					$tab = $origin_task->liste_contact(-1,$source);
					$num=count($tab);
					$i = 0;
					while ($i < $num)
					{
						$clone_task->add_contact($tab[$i]['id'], $tab[$i]['code'], $tab[$i]['source']);
						if ($clone_task->error == 'DB_ERROR_RECORD_ALREADY_EXISTS')
						{
							$langs->load("errors");
							$this->error.=$langs->trans("ErrorThisContactIsAlreadyDefinedAsThisType");
							$error++;
						}
						else
						{
							if ($clone_task->error!='')
							{
								$this->error.=$clone_task->error;
								$error++;
							}
						}
						$i++;
					}
				}
			}

			if($clone_time)
			{
				//TODO clone time of affectation
			}

			if (! $error)
			{
				return $clone_task_id;
			}
			else
			{
				dol_syslog(get_class($this)."::createFromClone nbError: ".$error." error : " . $this->error, LOG_ERR);
				return -1;
			}
		}
		else
		{
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 *	conditionDate
	 *
	 *  @param 	string	$Field		Field operand 1
	 *  @param 	string	$Value		Value operand 2
	 *  @param 	string	$Sens		Comparison operator
	 *  @return string
	 */
	function conditionDate($Field, $Value, $Sens)
	{
		// FIXME date_format is forbidden, not performant and no portable. Use instead BETWEEN
		if (strlen($Value)==4) $Condition=" date_format(".$Field.",'%Y') ".$Sens." ".$Value;
		elseif (strlen($Value)==6) $Condition=" date_format(".$Field.",'%Y%m') ".$Sens." '".$Value."'";
		else  $Condition=" date_format(".$Field.",'%Y%m%d') ".$Sens." ".$Value;
		return $Condition;
	}
}
?>
