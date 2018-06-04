<?php
/* Copyright (C) 2014-2017	Charlie BENKE 	<charlie@patas-monkey.com>
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
 * or see http://www.gnu.org/
 */

/**
 *		\file	 	management/core/lib/management.lib.php
 *		\brief	 	Functions used by management module
 *		\ingroup	management
 */

function management_admin_prepare_head ()
{
	global $langs, $conf, $user;
	
	$h = 0;
	$head = array();
	
	$head[$h][0] = 'admin.php';
	$head[$h][1] = $langs->trans("Setup");
	$head[$h][2] = 'admin';

//	$h++;
//	$head[$h][0] = 'trigger.php';
//	$head[$h][1] = $langs->trans("Triggers");
//	$head[$h][2] = 'trigger';

	$h++;
	$head[$h][0] = 'about.php';
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';

	return $head;
}


/**
 *  return time passed on a task for a day and a user
 *
 *  @param	int		$fk_task	project task id
 *  @param	date	$curday 	current day
 *  @param	int		$fk_user 	user id
 *  @return int					duration in seconds
 */
function fetchSumTimeSpent($fk_task, $curday, $fk_user=0, $displaymode=0)
{
	global $db;

	if ($displaymode==0)
	{
		$sql = "SELECT sum(t.task_duration) as total";
		$sql.= " FROM ".MAIN_DB_PREFIX."projet_task_time as t";
		$sql.= " WHERE 1=1";
	}
	elseif ($displaymode==1)
	{
		$sql = "SELECT sum((t.task_duration/3600)*t.thm) as total";
		$sql.= " FROM ".MAIN_DB_PREFIX."projet_task_time as t";
		$sql.= " WHERE 1=1";
	}
	elseif ($displaymode==2)
	{
		$sql = "SELECT sum((t.task_duration/3600)*p.price) as total";
		$sql.= " FROM ".MAIN_DB_PREFIX."projet_task_time as t, ".MAIN_DB_PREFIX."projet_task as pt, ".MAIN_DB_PREFIX."product as p" ;
		$sql.= " WHERE pt.rowid = t.fk_task";
		$sql.= " AND pt.fk_product = p.rowid";
	}
	elseif ($displaymode==3)
	{
		$sql = "SELECT sum((t.task_duration/3600)*(p.price-t.thm)) as total";
		$sql.= " FROM ".MAIN_DB_PREFIX."projet_task_time as t, ".MAIN_DB_PREFIX."projet_task as pt, ".MAIN_DB_PREFIX."product as p" ;
		$sql.= " WHERE pt.rowid = t.fk_task";
		$sql.= " AND pt.fk_product = p.rowid";
	}

	$sql.= " AND t.fk_task= ".$fk_task;
	if ($curday)
		$sql.= " AND t.task_date ='".$db->idate($curday)."'";
	if ($fk_user > 0)
		$sql.= " AND t.fk_user= ".$fk_user;

	dol_syslog("management.lib::fetchSumTimeSpent sql=".$sql, LOG_DEBUG);
	$resql=$db->query($sql);
	if ($resql)
	{
		if ($db->num_rows($resql))
		{
			$obj = $db->fetch_object($resql);
			$duration= $obj->total;
		}
		$db->free($resql);
		return $duration;
	}
	return 0;
}

/**
 *  return time passed on a task for a day and a user
 *
 *  @param	int		$fk_task	project task id
 *  @param	date	$curday 	current day
 *  @param	int		$fk_user 	user id
 *  @return int					duration in seconds
 */
function fetchSumTimePlanned($fk_task, $curday, $fk_user=0, $displaymode=0)
{
	global $db;

	if ($displaymode=="0P")
	{
		$sql = "SELECT sum(pt.planned_workload) as total";
		$sql.= " FROM ".MAIN_DB_PREFIX."projet_task as pt";
		$sql.= " WHERE 1=1";
	}
	elseif ($displaymode=="2P")
	{
		$sql = "SELECT sum((pt.planned_workload/3600)*p.price) as total";
		$sql.= " FROM ".MAIN_DB_PREFIX."projet_task as pt, ".MAIN_DB_PREFIX."product as p" ;
		$sql.= " WHERE pt.fk_product = p.rowid";
	}

	$sql.= " AND pt.rowid= ".$fk_task;
	if ($curday)
	{
		$sql.= " AND (pt.dateo <= '".$db->idate($curday)."'";
		$sql.= " OR (pt.datee >= '".$db->idate($curday)."')";
	}
	// le temps assigné à l'utilisateur
	if ($fk_user > 0)
		$sql.= " AND t.fk_user= ".$fk_user;

	dol_syslog("management.lib::fetchSumTimePanned sql=".$sql, LOG_DEBUG);
	$resql=$db->query($sql);
	if ($resql)
	{
		if ($db->num_rows($resql))
		{
			$obj = $db->fetch_object($resql);
			$duration= $obj->total;
		}
		$db->free($resql);
		return $duration;
	}
	return 0;
}


/**
 *  return time passed on a task for a day and a user
 *
 *  @param	int		$fk_task	project task id
 *  @param	date	$curday 	current day
 *  @param	int		$fk_user 	user id
 *  @return int					duration in seconds
 */
function fetchTextTimeSpent($fk_task, $curday, $fk_user)
{
	global $db;
	$note="";
	$sql = "SELECT t.note";
	$sql.= " FROM ".MAIN_DB_PREFIX."projet_task_time as t";
	$sql.= " WHERE t.fk_task= ".$fk_task;
	$sql.= " AND t.task_date ='".$db->idate($curday)."'";
	$sql.= " AND t.fk_user= ".$fk_user;

	dol_syslog("management.lib::fetchTextTimeSpent sql=".$sql, LOG_DEBUG);
	$resql=$db->query($sql);
	if ($resql)
	{
		if ($db->num_rows($resql))
		{
			$num = $db->num_rows($resql);
			$i = 0;
			// Loop on each record found, so each couple (project id, task id)
			while ($i < $num)
			{
				$obj = $db->fetch_object($resql);
				if ($obj->note)
					$note.= $obj->note."\n";
				$i++;
			}
		}
		$db->free($resql);
		return $note;
	}
	return 0;
}

/**
 *  return time passed on a task for amonth and a user
 *
 *  @param	int		$fk_task	project task id
 *  @param	date	$curday 	current day
 *  @param	int		$fk_user 	user id
 *  @return int					duration in seconds
 */
function fetchSumMonthTimeSpent($fk_task, $curmonth, $curyear, $fk_user=0, $displaymode=0)
{
	global $db;
	
	if ($displaymode==0)
	{
		$sql = "SELECT sum(t.task_duration) as total";
		$sql.= " FROM ".MAIN_DB_PREFIX."projet_task_time as t";
		$sql.= " WHERE 1=1";
	}
	elseif ($displaymode==1)
	{
		$sql = "SELECT sum((t.task_duration/3600)*t.thm) as total";
		$sql.= " FROM ".MAIN_DB_PREFIX."projet_task_time as t";
		$sql.= " WHERE 1=1";
	}
	elseif ($displaymode==2)
	{
		$sql = "SELECT sum((t.task_duration/3600)*p.price) as total";
		$sql.= " FROM ".MAIN_DB_PREFIX."projet_task_time as t, ".MAIN_DB_PREFIX."projet_task as pt, ".MAIN_DB_PREFIX."product as p" ;
		$sql.= " WHERE pt.rowid = t.fk_task";
		$sql.= " AND pt.fk_product = p.rowid";
	}
	elseif ($displaymode==3)
	{
		$sql = "SELECT sum(t.task_duration_billed) as total";
		$sql.= " FROM ".MAIN_DB_PREFIX."projet_task_billed as t" ;
		$sql.= " WHERE 1=1";
	}
	elseif ($displaymode==4)
	{
		$sql = "SELECT sum((t.task_duration/3600)*(p.price-t.thm)) as total";
		$sql.= " FROM ".MAIN_DB_PREFIX."projet_task_time as t, ".MAIN_DB_PREFIX."projet_task as pt, ".MAIN_DB_PREFIX."product as p" ;
		$sql.= " WHERE pt.rowid = t.fk_task";
		$sql.= " AND pt.fk_product = p.rowid";
	}	
	
	$sql.= " AND t.fk_task= ".$fk_task;
	if ($curmonth)
		$sql.= " AND month(task_date) ='".$curmonth."'";
	if ($curyear)
		$sql.= " AND year(task_date) ='".$curyear."'";
	if ($fk_user > 0)
		$sql.= " AND t.fk_user= ".$fk_user;

	dol_syslog("management.lib::fetchTaskTimeSpent sql=".$sql, LOG_DEBUG);

	$resql=$db->query($sql);
	if ($resql)
	{
		if ($db->num_rows($resql))
		{
			$obj = $db->fetch_object($resql);
			$duration= $obj->total; // on renvoie en seconde on gère sur les écrans ensuite
		}
		$db->free($resql);
		return $duration;
	}
	return 0;
}

/**
 *  return time billed on a task for a month and a year
 *
 *  @param	int		$fk_task	project task id
 *  @param	date	$curmonth 	current month
 *  @param	int		$curyear 	user year
 *  @return int					duration in seconds
 */
function fetchSumTimeBilled($fk_task, $curmonth='', $curyear='')
{
	global $db;
	
	$sql = "SELECT sum(t.task_duration_billed) as total";
	$sql.= " FROM ".MAIN_DB_PREFIX."projet_task_billed as t";
	$sql.= " WHERE t.fk_task= ".$fk_task;
	if ($curmonth)
		$sql.= " AND month(task_date) ='".$curmonth."'";
	if ($curyear)
		$sql.= " AND year(task_date) ='".$curyear."'";

	$sql.= " AND fk_facture > 0";

	dol_syslog("management.lib::fetchTaskTimeSpent sql=".$sql, LOG_DEBUG);

	$resql=$db->query($sql);
	if ($resql)
	{
		if ($db->num_rows($resql))
		{
			$obj = $db->fetch_object($resql);
			$duration= $obj->total; // on renvoie en seconde on gère sur les écrans ensuite
		}
		$db->free($resql);
		return $duration;
	}
	return 0;
}


function stoppausedtime()
{
	global $conf, $db;
	
	// on récupère le mode d'arret
	$stopduration=$conf->global->MANAGEMENT_STOP_DURATION;
	$typeMode=$conf->global->MANAGEMENT_STOP_MODE;

	// si l'arret est activé
	if ($typeMode !="")
	{
		// récupération des tasks en pause qui ont dépassé le temps
		$sql = "SELECT rowid, fk_user, note";
		$sql .= " FROM ".MAIN_DB_PREFIX."projet_task_time as t";
		$sql .= " WHERE TIMESTAMPDIFF(".$typeMode.",t.date_pause,now()) >= ".$stopduration;
		$sql .= " And t.date_end is null";

		dol_syslog("management.lib::stoppausedtime sql=".$sql, LOG_DEBUG);
		$resql=$db->query($sql);
		if ($resql)
		{
			$num = $db->num_rows($resql);
			$i = 0;
			// Loop on each record found, so each couple (project id, task id)
			while ($i < $num)
			{
				$error=0;
				$obj = $db->fetch_object($resql);
				stoptime( $obj->rowid, $obj->note, $obj->fk_user);
				$i++;
			}
			$db->free($resql);
		}
		return 0;
	}
}


function starttime($fk_task, $fk_task_time, $note, $perioduser)
{
	global $db;
	
	$error=0;
	$ret = 0;

	// Clean parameters
	if (isset($note)) $note = trim($note);

	$sql = "INSERT INTO ".MAIN_DB_PREFIX."projet_task_time (";
	$sql.= "fk_task";
	$sql.= ", task_date";
	$sql.= ", task_datehour";
	$sql.= ", task_duration";
	$sql.= ", fk_user";
	$sql.= ", note";
	$sql.= ", date_start";

	$sql.= ") VALUES (";
	$sql.= $fk_task;
	$sql.= ", now()";
	$sql.= ", now()";
	$sql.= ", null";
	$sql.= ", ".$perioduser;
	$sql.= ", ".(isset($note)?"'".$db->escape($note)."'":"null");
	$sql.= ", now()";
	$sql.= ")";
	dol_syslog("management.lib::starttime sql=".$sql, LOG_DEBUG);
	if ($db->query($sql) )
	{
		$tasktime_id = $db->last_insert_id(MAIN_DB_PREFIX."projet_task_time");
		$ret = $tasktime_id;
	}
	if ($ret >= 0)
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task_time";
		$sql.= " SET thm = (SELECT thm FROM ".MAIN_DB_PREFIX."user WHERE rowid = ".$perioduser.")";
		$sql.= " WHERE rowid = ".$tasktime_id;
		
		dol_syslog("management.lib::starttime sql=".$sql, LOG_DEBUG);
		if (! $db->query($sql) )
		{
			$error=$db->lasterror();
			dol_syslog("management.lib::starttime error -2 sql=".$sql, LOG_DEBUG);
			$ret = -2;
		}
	}
}

function stoptime( $fk_task_time, $note, $perioduser)
{
	global $db;

	$sql = "SELECT date_start, date_pause, task_duration, now() as currentdate";
	$sql .= " FROM ".MAIN_DB_PREFIX."projet_task_time as t";
	$sql .= " WHERE t.rowid =".$fk_task_time;
	$sql .= " And t.fk_user =".$perioduser;
	$sql .= " And t.date_end is null";
//print $sql;

	dol_syslog("management.lib::stoptime sql=".$sql, LOG_DEBUG);
	$resql = $db->query($sql);
	if ($resql)
	{
		if ($db->num_rows($resql))
		{
			$obj = $db->fetch_object($resql);
			$date_start		= $obj->date_start;
			$date_pause		= $obj->date_pause;
			$task_duration	= $obj->task_duration;
			$currentdate	= $obj->currentdate;
			// si il y a eu une pause
			if ($task_duration)
			{
				$nbsec= $task_duration;
				if (! $date_pause)	// si on a stoppé alors que l'on avait redémarré
					$nbsec+= strtotime($currentdate) - strtotime($date_start);
			}
			else
				$nbsec= strtotime($currentdate) - strtotime($date_start);
		}
		$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task_time";
		$sql.= " SET date_end = now()";
		$sql.= " ,   date_pause = null"; // dans le doute...
		$sql.= " ,   task_duration =".$nbsec;
		$sql.= " WHERE rowid = ".$fk_task_time;

		dol_syslog("management.lib::stoptime sql=".$sql, LOG_DEBUG);
		if (! $db->query($sql) )
		{
			$error=$db->lasterror();
			dol_syslog("management.lib::stoptime error -1 sql=".$sql, LOG_DEBUG);
			$ret = -1;
		}
	}
}

function pausetime( $fk_task_time, $note, $perioduser)
{
	global $db;

	$sql = "SELECT date_start, task_duration, now() as currentdate";
	$sql .= " FROM ".MAIN_DB_PREFIX."projet_task_time as t";
	$sql .= " WHERE t.rowid =".$fk_task_time;
	$sql .= " And t.fk_user =".$perioduser;
	$sql .= " And t.date_end is null";
//print $sql;

	dol_syslog("management.lib::pausetime sql=".$sql, LOG_DEBUG);
	$resql = $db->query($sql);
	if ($resql)
	{
		if ($db->num_rows($resql))
		{
			$obj = $db->fetch_object($resql);
			$date_start		= $obj->date_start;
			$task_duration	= $obj->task_duration;
			$currentdate	= $obj->currentdate;

			$nbsec= strtotime($currentdate) - strtotime($date_start);
			if ($task_duration)		// si il y a DEJA eu une pause, on rajoute le temps de la pause
				$nbsec+= $task_duration;
		}
		$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task_time";
		$sql.= " SET date_pause = now()";
		$sql.= " ,   task_duration =".$nbsec;
		$sql.= " WHERE rowid = ".$fk_task_time;

		dol_syslog("management.lib::pausetime sql=".$sql, LOG_DEBUG);
		if (! $db->query($sql) )
		{
			$error=$db->lasterror();
			dol_syslog("management.lib::pausetime error -1 sql=".$sql, LOG_DEBUG);
			$ret = -1;
		}
	}
}

function restarttime( $fk_task_time, $note, $perioduser)
{
	global $db;
	// on relance la tache
	$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task_time";
	$sql.= " SET date_start = now()";
	$sql.= " ,   date_pause = null";
	$sql.= " WHERE rowid = ".$fk_task_time;
//print $sql;

	dol_syslog("management.lib::restarttime sql=".$sql, LOG_DEBUG);
	if (! $db->query($sql) )
	{
		$error=$db->lasterror();
		dol_syslog("management.lib::restarttime error -1 sql=".$sql, LOG_DEBUG);
		$ret = -1;
	}
}

function set_thm($user_id, $thm_value)
{
	global $db;
	// on relance la tache
	$sql = "UPDATE ".MAIN_DB_PREFIX."user";
	$sql.= " SET thm = ".$thm_value;
	$sql.= " WHERE rowid = ".$user_id;
//print $sql;

	dol_syslog("management.lib::set_thm sql=".$sql, LOG_DEBUG);
	if (! $db->query($sql) )
	{
		$error=$db->lasterror();
		dol_syslog("management.lib::set_thm error -1 sql=".$sql, LOG_DEBUG);
		$ret = -1;
	}
}

function addtime($fk_task, $fk_task_time, $note, $perioduser, $duration)
{
	global $db;
	
	$error=0;
	$ret = 0;

	// Clean parameters
	if (isset($note)) $note = trim($note);

	$sql = "INSERT INTO ".MAIN_DB_PREFIX."projet_task_time (";
	$sql.= "fk_task";
	$sql.= ", task_date";
	$sql.= ", task_datehour";
	$sql.= ", task_duration";
	$sql.= ", fk_user";
	$sql.= ", note";
	$sql.= ", date_start";
	$sql.= ", date_end";
	$sql.= ") VALUES (";
	$sql.= $fk_task;
	$sql.= ", now()";
	$sql.= ", now()";
	$sql.= ", ". $duration;
	$sql.= ", ".$perioduser;
	$sql.= ", ".(isset($note)?"'".$db->escape($note)."'":"null");
	$sql.= ", now()";
	$sql.= ", now()";
	$sql.= ")";

	dol_syslog("management.lib::starttime sql=".$sql, LOG_DEBUG);
	if ($db->query($sql) )
	{
		$tasktime_id = $db->last_insert_id(MAIN_DB_PREFIX."projet_task_time");
		$ret = $tasktime_id;
	}
	if ($ret >= 0)
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task_time";
		$sql.= " SET thm = (SELECT thm FROM ".MAIN_DB_PREFIX."user WHERE rowid = ".$perioduser.")";
		$sql.= " WHERE rowid = ".$tasktime_id;
		
		dol_syslog("management.lib::starttime sql=".$sql, LOG_DEBUG);
		if (! $db->query($sql) )
		{
			$error=$db->lasterror();
			dol_syslog("management.lib::starttime error -2 sql=".$sql, LOG_DEBUG);
			$ret = -2;
		}
	}
}


function Contract_Transfer_FichInter($db, $contractid)
{
	global $conf, $langs, $user;
	// récupération des infos du projet associé à la tache (société nottament)
	$contrat = new Contrat($db);
	$result=$contrat->fetch($contractid);
	$socid=$contrat->socid;
	//$dateo = $contrat->date_start;
	//$datee = $contrat->date_end;
	$desc = $contrat->description;
	$note_public = $contrat->note_public;
	$note_private = $contrat->note_private;


	// récupération de la référence 
	if (! empty($conf->global->FICHEINTER_ADDON) && is_readable(DOL_DOCUMENT_ROOT ."/core/modules/fichinter/mod_".$conf->global->FICHEINTER_ADDON.".php"))
	{
		require_once(DOL_DOCUMENT_ROOT ."/core/modules/fichinter/mod_".$conf->global->FICHEINTER_ADDON.".php");
	}
	// création de la fiche d'intervention 
	require_once(DOL_DOCUMENT_ROOT ."/fichinter/class/fichinter.class.php");
	$object = new ManagementFichinter($db);
	$object->date = time();
	$obj = $conf->global->FICHEINTER_ADDON;
	$obj = "mod_".$obj;
	$modFicheinter = new $obj;

	$numpr =$modFicheinter->getNextValue($societe, $object);

	// création d'une nouvelle fiche d'intervention
	$object->socid			= $socid;
	$object->fk_contrat		= $contractid; // l'intervention est lié au contrat
	$object->note_public	= $note_public;
	$object->note_private	= $note_private;
	$object->dateo			= $dateo;
	$object->datee			= $datee;
	$object->author			= $user->id;
	$object->description	= $desc;
	$object->fulldayevent = 1;
	$object->statut=0; 	// fich inter en mode draft

	$object->ref=$numpr;
	$object->modelpdf=0; // à rien par défaut

	$result = $object->create($user);
	if ($result > 0)
	{ // on transfert les lignes du contrat en ligne d'intervention

		$sql = "SELECT cd.rowid, cd.fk_product, cd.qty, label, description, subprice, total_ht, tva_tx";
		$sql .= " FROM ".MAIN_DB_PREFIX."contratdet as cd";
		$sql .= " WHERE cd.fk_contrat=".$contractid;
		$sql .= " and statut <> 5";  // seulement les services actifs
		$var=true;
		$resql = $db->query($sql);
		if ($resql)
		{
			$num = $db->num_rows($resql);
			$i = 0;
			while ($i < $num)
			{
				$objp = $db->fetch_object($resql);

				if ($objp->fk_product)
				{
					$object->addlineRapport($result, $objp->label, $objp->subprice, $objp->qty, $objp->tva_tx,
						$txlocaltax1, $txlocaltax2, $objp->fk_product, $remise_percent, $info_bits, $fk_remise_except, "HT", $pu_ttc, 
						$date_start, $date_end, $type, $rang, $special_code, $fk_parent_line, $fk_fournprice, $pa_ht, $objp->description);
				}
				else
				{
					$object->addline(
						$result,
						$objp->description,
						"", // pas de date sur la ligne d'intervention
						$objp->qty,
						$objp->subprice,
						$objp->total_ht
					);
				}
				$i++;
			}
			$db->free($resql);
		}
		else
		{
			dol_print_error($db);
		}
	}
	else
	{
		$langs->load("errors");
		$mesg='<div class="error">'.$langs->trans($object->error).'</div>';
		$action = 'create';
	}
}
	
?>