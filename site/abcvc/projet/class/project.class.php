<?php
/* Copyright (C) 2002-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2016 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2010 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2013      Florian Henry        <florian.henry@open-concept.pro>
 * Copyright (C) 2014-2017 Marcos García        <marcosgdf@gmail.com>
 * Copyright (C) 2017      Ferran Marcet        <fmarcet@2byte.es>
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
 *      \file       htdocs/projet/class/project.class.php
 *      \ingroup    projet
 *      \brief      File of class to manage projects
 */
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/task.class.php';

/**
 *  Class to manage projects
 */
class ProjectABCVC extends CommonObject
{

    public $element = 'projectabcvc';    //!< Id that identify managed objects
    public $table_element = 'abcvc_projet';  //!< Name of table without prefix where object is stored
    public $table_element_line = 'projet_task';
    public $fk_element = 'fk_projet';
    protected $ismultientitymanaged = 1;  // 0=No test on entity, 1=Test with field entity, 2=Test with link by societe
    public $picto = 'projectpub';
    
    /**
     * {@inheritdoc}
     */
    protected $table_ref_field = 'ref';

    var $description;
    /**
     * @var string
     * @deprecated
     * @see title
     */
    public $titre;
    var $title;
    var $date_start;
    var $date_end;
    var $date_close;

    var $socid;             // To store id of thirdparty
    var $thirdparty_name;   // To store name of thirdparty (defined only in some cases)

    var $user_author_id;    //!< Id of project creator. Not defined if shared project.
    var $user_close_id;
    var $public;      //!< Tell if this is a public or private project
    var $budget_amount;

    var $statuts_short;
    var $statuts_long;

    var $statut;            // 0=draft, 1=opened, 2=closed
    var $opp_status;        // opportunity status, into table llx_c_lead_status
    var $opp_percent;       // opportunity probability

    var $oldcopy;

    var $weekWorkLoad;          // Used to store workload details of a projet
    var $weekWorkLoadPerTask;   // Used to store workload details of tasks of a projet

    /**
     * @var int Creation date
     * @deprecated
     * @see date_c
     */
    public $datec;
    /**
     * @var int Creation date
     */
    public $date_c;
    /**
     * @var int Modification date
     * @deprecated
     * @see date_m
     */
    public $datem;
    /**
     * @var int Modification date
     */
    public $date_m;

    /**
     * @var Task[]
     */
    public $lines;
    

    /**
     *  Constructor
     *
     *  @param      DoliDB      $db      Database handler
     */
    function __construct($db)
    {
        $this->db = $db;

        $this->statuts_short = array(0 => 'Draft', 1 => 'Opened', 2 => 'Closed');
        $this->statuts_long = array(0 => 'Draft', 1 => 'Opened', 2 => 'Closed');
    }

    /**
     *    Create a project into database
     *
     *    @param    User    $user           User making creation
     *    @param    int     $notrigger      Disable triggers
     *    @return   int                     <0 if KO, id of created project if OK
     */
    function create($user, $notrigger=0)
    {
        global $conf, $langs;

        $error = 0;
        $ret = 0;

        $now=dol_now();

        // Check parameters
        if (!trim($this->ref))
        {
            $this->error = 'ErrorFieldsRequired';
            dol_syslog(get_class($this)."::create error -1 ref null", LOG_ERR);
            return -1;
        }

        $this->db->begin();

        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "abcvc_projet (";
        $sql.= "ref";
        $sql.= ", fk_zones";
        $sql.= ", title";
        $sql.= ", description";
        $sql.= ", fk_soc";
        $sql.= ", address";
        $sql.= ", postal_code";
        $sql.= ", city";
        $sql.= ", fk_user_creat";
        $sql.= ", fk_statut";
        $sql.= ", fk_opp_status";
        $sql.= ", opp_percent";
        $sql.= ", public";
        $sql.= ", datec";
        $sql.= ", dateo";
        $sql.= ", datee";
        $sql.= ", opp_amount";
        $sql.= ", budget_amount";
        $sql.= ", entity";
        $sql.= ", chargesfixe";        
        $sql.= ") VALUES (";
        $sql.= "'" . $this->db->escape($this->ref) . "'";
        $sql.= ", '" . $this->db->escape($this->fk_zones) . "'";
        $sql.= ", '" . $this->db->escape($this->title) . "'";
        $sql.= ", '" . $this->db->escape($this->description) . "'";
        $sql.= ", " . ($this->socid > 0 ? $this->socid : "null");
        $sql.= ", '" . $this->db->escape($this->address) . "'";
        $sql.= ", '" . $this->db->escape($this->postal_code) . "'";
        $sql.= ", '" . $this->db->escape($this->city) . "'";
        $sql.= ", " . $user->id;
        $sql.= ", ".(is_numeric($this->statut) ? $this->statut : '1');
        $sql.= ", ".(is_numeric($this->opp_status) ? $this->opp_status : 'NULL');
        $sql.= ", ".(is_numeric($this->opp_percent) ? $this->opp_percent : 'NULL');
        $sql.= ", " . ($this->public ? 1 : 0);
        $sql.= ", '".$this->db->idate($now)."'";
        $sql.= ", " . ($this->date_start != '' ? "'".$this->db->idate($this->date_start)."'" : 'null');
        $sql.= ", " . ($this->date_end != '' ? "'".$this->db->idate($this->date_end)."'" : 'null');
        $sql.= ", " . (strcmp($this->opp_amount,'') ? price2num($this->opp_amount) : 'null');
        $sql.= ", " . (strcmp($this->budget_amount,'') ? price2num($this->budget_amount) : 'null');
        $sql.= ", ".$conf->entity;
        $sql.= ", " . (strcmp($this->chargesfixe,'') ? price2num($this->chargesfixe) : 'null');        
        $sql.= ")";


        dol_syslog(get_class($this)."::create", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql)
        {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX . "abcvc_projet");
            $ret = $this->id;

            if (!$notrigger)
            {
                // Call trigger
                $result=$this->call_trigger('PROJECT_CREATE',$user);
                if ($result < 0) { $error++; }
                // End call triggers
            }
        }
        else
        {
            $this->error = $this->db->lasterror();
            $this->errno = $this->db->lasterrno();
            $error++;
        }

        // Update extrafield
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

        if (!$error && !empty($conf->global->MAIN_DISABLEDRAFTSTATUS))
        {
            $res = $this->setValid($user);
            if ($res < 0) $error++;
        }

        if (!$error)
        {
            $this->db->commit();
            return $ret;
        }
        else
        {
            $this->db->rollback();
            return -1;
        }
    }

    /**
     * Update a project
     *
     * @param  User     $user       User object of making update
     * @param  int      $notrigger  1=Disable all triggers
     * @return int                  <=0 if KO, >0 if OK
     */
    function update($user, $notrigger=0)
    {
        global $langs, $conf;

        $error=0;

        // Clean parameters
        $this->title = trim($this->title);
        $this->description = trim($this->description);
        if ($this->opp_amount < 0) $this->opp_amount='';
        if ($this->opp_percent < 0) $this->opp_percent='';
        if ($this->date_end && $this->date_end < $this->date_start)
        {
            $this->error = $langs->trans("ErrorDateEndLowerThanDateStart");
            $this->errors[] = $this->error;
            $this->db->rollback();
            dol_syslog(get_class($this)."::update error -3 " . $this->error, LOG_ERR);
            return -3;
        }
        
        if (dol_strlen(trim($this->ref)) > 0)
        {
            $this->db->begin();

            $sql = "UPDATE " . MAIN_DB_PREFIX . "abcvc_projet SET";
            $sql.= " ref='" . $this->db->escape($this->ref) . "'";
            $sql.= ", fk_zones='" . $this->db->escape($this->fk_zones) . "'";
            $sql.= ", address='" . $this->db->escape($this->address) . "'";
            $sql.= ", postal_code='" . $this->db->escape($this->postal_code) . "'";
            $sql.= ", city='" . $this->db->escape($this->city) . "'";
            $sql.= ", title = '" . $this->db->escape($this->title) . "'";
            $sql.= ", description = '" . $this->db->escape($this->description) . "'";
            $sql.= ", fk_soc = " . ($this->socid > 0 ? $this->socid : "null");
            $sql.= ", fk_statut = " . $this->statut;
            $sql.= ", fk_opp_status = " . ((is_numeric($this->opp_status) && $this->opp_status > 0) ? $this->opp_status : 'null');
            $sql.= ", opp_percent = " . ((is_numeric($this->opp_percent) && $this->opp_percent != '') ? $this->opp_percent : 'null');
            $sql.= ", public = " . ($this->public ? 1 : 0);
            $sql.= ", datec=" . ($this->date_c != '' ? "'".$this->db->idate($this->date_c)."'" : 'null');
            $sql.= ", dateo=" . ($this->date_start != '' ? "'".$this->db->idate($this->date_start)."'" : 'null');
            $sql.= ", datee=" . ($this->date_end != '' ? "'".$this->db->idate($this->date_end)."'" : 'null');
            $sql.= ", date_close=" . ($this->date_close != '' ? "'".$this->db->idate($this->date_close)."'" : 'null');
            $sql.= ", fk_user_close=" . ($this->fk_user_close > 0 ? $this->fk_user_close : "null");
            $sql.= ", opp_amount = " . (strcmp($this->opp_amount, '') ? price2num($this->opp_amount) : "null");
            $sql.= ", budget_amount = " . (strcmp($this->budget_amount, '')  ? price2num($this->budget_amount) : "null");
            $sql.= ", chargesfixe = " . (strcmp($this->chargesfixe, '')  ? price2num($this->chargesfixe) : "null");
            $sql.= " WHERE rowid = " . $this->id;
            // var_dump($sql);
            dol_syslog(get_class($this)."::update", LOG_DEBUG);
            $resql=$this->db->query($sql);
            if ($resql)
            {
                if (!$notrigger)
                {
                    // Call trigger
                    $result=$this->call_trigger('PROJECT_MODIFY',$user);
                    if ($result < 0) { $error++; }
                    // End call triggers
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

                if (! $error && (is_object($this->oldcopy) && $this->oldcopy->ref !== $this->ref))
                {
                    // We remove directory
                    if ($conf->projet->dir_output)
                    {
                        $olddir = $conf->projet->dir_output . "/" . dol_sanitizeFileName($this->oldcopy->ref);
                        $newdir = $conf->projet->dir_output . "/" . dol_sanitizeFileName($this->ref);
                        if (file_exists($olddir))
                        {
                            include_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
                            $res=dol_move($olddir, $newdir);
                            if (! $res)
                            {
                                $langs->load("errors");
                                $this->error=$langs->trans('ErrorFailToRenameDir',$olddir,$newdir);
                                $error++;
                            }
                        }
                    }
                }
                if (! $error )
                {
                    $this->db->commit();
                    $result = 1;
                }
                else
              {
                    $this->db->rollback();
                    $result = -1;
                }
            }
            else
            {
                $this->error = $this->db->lasterror();
                $this->errors[] = $this->error;
                $this->db->rollback();
                if ($this->db->lasterrno() == 'DB_ERROR_RECORD_ALREADY_EXISTS')
                {
                    $result = -4;
                }
                else
                {
                    $result = -2;
                }
                dol_syslog(get_class($this)."::update error " . $result . " " . $this->error, LOG_ERR);
            }
        }
        else
        {
            dol_syslog(get_class($this)."::update ref null");
            $result = -1;
        }

        return $result;
    }

    /**
     *  Get object from database
     *
     *  @param      int     $id         Id of object to load
     *  @param      string  $ref        Ref of project
     *  @return     int                 >0 if OK, 0 if not found, <0 if KO
     */
    function fetch($id, $ref='')
    {
        if (empty($id) && empty($ref)) return -1;

        $sql = "SELECT rowid, ref, title, description, public, datec, opp_amount, budget_amount,fk_zones, address, postal_code, city, chargesfixe,";
        $sql.= " tms, dateo, datee, date_close, fk_soc, fk_user_creat, fk_user_close, fk_statut, fk_opp_status, opp_percent, note_private, note_public, model_pdf";
        $sql.= " FROM " . MAIN_DB_PREFIX . "abcvc_projet";
        if (! empty($id))
        {
            $sql.= " WHERE rowid=".$id;
        }
        else if (! empty($ref))
        {
            $sql.= " WHERE ref='".$this->db->escape($ref)."'";
            $sql.= " AND entity IN (".getEntity('project',1).")";
        }
        // var_dump($sql);
        dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

                $this->id = $obj->rowid;
                $this->ref = $obj->ref;
                $this->title = $obj->title;
                $this->titre = $obj->title; // TODO deprecated
                $this->description = $obj->description;
                $this->date_c = $this->db->jdate($obj->datec);
                $this->datec = $this->db->jdate($obj->datec); // TODO deprecated
                $this->date_m = $this->db->jdate($obj->tms);
                $this->datem = $this->db->jdate($obj->tms);  // TODO deprecated
                $this->date_start = $this->db->jdate($obj->dateo);
                $this->date_end = $this->db->jdate($obj->datee);
                $this->date_close = $this->db->jdate($obj->date_close);
                $this->note_private = $obj->note_private;
                $this->note_public = $obj->note_public;
                $this->socid = $obj->fk_soc;
                $this->user_author_id = $obj->fk_user_creat;
                $this->user_close_id = $obj->fk_user_close;
                $this->public = $obj->public;
                $this->statut = $obj->fk_statut;
                $this->opp_status = $obj->fk_opp_status;
                $this->opp_amount   = $obj->opp_amount;
                $this->opp_percent  = $obj->opp_percent;
                $this->budget_amount    = $obj->budget_amount;
                $this->modelpdf = $obj->model_pdf;
                $this->fk_zones = $obj->fk_zones;
                $this->address = $obj->address;
                $this->postal_code = $obj->postal_code;
                $this->city = $obj->city;
                $this->chargesfixe = $obj->chargesfixe;

                $this->db->free($resql);

                return 1;
            }
            else
            {
                return 0;
            }
        }
        else
        {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     *  Return list of projects
     *
     *  @param      int     $socid      To filter on a particular third party
     *  @return     array               List of projects
     */
    function liste_array($socid='')
    {
        global $conf;

        $projects = array();

        $sql = "SELECT rowid, title";
        $sql.= " FROM " . MAIN_DB_PREFIX . "abcvc_projet";
        $sql.= " WHERE entity = " . $conf->entity;
        if (! empty($socid)) $sql.= " AND fk_soc = " . $socid;

        $resql = $this->db->query($sql);
        if ($resql)
        {
            $nump = $this->db->num_rows($resql);

            if ($nump)
            {
                $i = 0;
                while ($i < $nump)
                {
                    $obj = $this->db->fetch_object($resql);

                    $projects[$obj->rowid] = $obj->title;
                    $i++;
                }
            }
            return $projects;
        }
        else
        {
            print $this->db->lasterror();
        }
    }

    /**
     *  Return list of elements for type, linked to project
     *
     *  @param      string      $type           'propal','order','invoice','order_supplier','invoice_supplier',...
     *  @param      string      $tablename      name of table associated of the type
     *  @param      string      $datefieldname  name of date field for filter
     *  @param      string      $dates          Start date (ex 00:00:00)
     *  @param      string      $datee          End date (ex 23:59:59)
     *  @return     mixed                       Array list of object ids linked to project, < 0 or string if error
     */
    function get_element_list($type, $tablename, $datefieldname='', $dates='', $datee='')
    {
        $elements = array();

        if ($this->id <= 0) return $elements;

        if ($type == 'agenda')
        {
            $sql = "SELECT id as rowid FROM " . MAIN_DB_PREFIX . "actioncomm WHERE fk_project=" . $this->id;
        }
        elseif ($type == 'expensereport')
        {
            $sql = "SELECT ed.rowid FROM " . MAIN_DB_PREFIX . "expensereport as e, " . MAIN_DB_PREFIX . "expensereport_det as ed WHERE e.rowid = ed.fk_expensereport AND ed.fk_projet=" . $this->id;
        }
        elseif ($type == 'project_task')
        {
            $sql = "SELECT DISTINCT pt.rowid FROM " . MAIN_DB_PREFIX . "abcvc_projet_task as pt, " . MAIN_DB_PREFIX . "abcvc_projet_task_time as ptt WHERE pt.rowid = ptt.fk_task AND pt.fk_projet=" . $this->id;
        }
        elseif ($type == 'project_task_time')   // Case we want to duplicate line foreach user
        {
            $sql = "SELECT DISTINCT pt.rowid, ptt.fk_user FROM " . MAIN_DB_PREFIX . "abcvc_projet_task as pt, " . MAIN_DB_PREFIX . "abcvc_projet_task_time as ptt WHERE pt.rowid = ptt.fk_task AND pt.fk_projet=" . $this->id;
        }
        else
        {
            $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . $tablename." WHERE fk_projet=" . $this->id;
        }

        if ($dates > 0)
        {
            if (empty($datefieldname) && ! empty($this->table_element_date)) $datefieldname=$this->table_element_date;
            if (empty($datefieldname)) return 'Error this object has no date field defined';
            $sql.=" AND (".$datefieldname." >= '".$this->db->idate($dates)."' OR ".$datefieldname." IS NULL)";
        }
        if ($datee > 0)
        {
            if (empty($datefieldname) && ! empty($this->table_element_date)) $datefieldname=$this->table_element_date;
            if (empty($datefieldname)) return 'Error this object has no date field defined';
            $sql.=" AND (".$datefieldname." <= '".$this->db->idate($datee)."' OR ".$datefieldname." IS NULL)";
        }
        if (! $sql) return -1;

        //print $sql;
        dol_syslog(get_class($this)."::get_element_list", LOG_DEBUG);
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

                    $elements[$i] = $obj->rowid.(empty($obj->fk_user)?'':'_'.$obj->fk_user);

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

    /**
     *    Delete a project from database
     *
     *    @param       User     $user            User
     *    @param       int      $notrigger       Disable triggers
     *    @return      int                        <0 if KO, 0 if not possible, >0 if OK
     */
    function delete($user, $notrigger=0)
    {
        global $langs, $conf;
        require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

        $error = 0;

        $this->db->begin();

        if (!$error)
        {
            // Delete linked contacts
            $res = $this->delete_linked_contact();
            if ($res < 0)
            {
                $this->error = 'ErrorFailToDeleteLinkedContact';
                //$error++;
                $this->db->rollback();
                return 0;
            }
        }

        // Set fk_projet into elements to null
        $listoftables=array(
                'facture'=>'fk_projet','propal'=>'fk_projet','commande'=>'fk_projet','facture_fourn'=>'fk_projet','commande_fournisseur'=>'fk_projet',
                'expensereport_det'=>'fk_projet','contrat'=>'fk_projet','fichinter'=>'fk_projet','don'=>'fk_projet'
                );
        foreach($listoftables as $key => $value)
        {
            $sql = "UPDATE " . MAIN_DB_PREFIX . $key . " SET ".$value." = NULL where ".$value." = ". $this->id;
            $resql = $this->db->query($sql);
            if (!$resql)
            {
                $this->errors[] = $this->db->lasterror();
                $error++;
                break;
            }
        }

        // Delete tasks
        if (! $error)
        {
            $sql = "DELETE FROM " . MAIN_DB_PREFIX . "abcvc_projet_task_time";
            $sql.= " WHERE fk_task IN (SELECT rowid FROM " . MAIN_DB_PREFIX . "abcvc_projet_task WHERE fk_projet=" . $this->id . ")";

            $resql = $this->db->query($sql);
            if (!$resql)
            {
                $this->errors[] = $this->db->lasterror();
                $error++;
            }
        }

        if (! $error)
        {
            $sql = "DELETE FROM " . MAIN_DB_PREFIX . "abcvc_projet_task_extrafields";
            $sql.= " WHERE fk_object IN (SELECT rowid FROM " . MAIN_DB_PREFIX . "abcvc_projet_task WHERE fk_projet=" . $this->id . ")";

            $resql = $this->db->query($sql);
            if (!$resql)
            {
                $this->errors[] = $this->db->lasterror();
                $error++;
            }
        }

        if (! $error)
        {
            $sql = "DELETE FROM " . MAIN_DB_PREFIX . "abcvc_projet_task";
            $sql.= " WHERE fk_projet=" . $this->id;

            $resql = $this->db->query($sql);
            if (!$resql)
            {
                $this->errors[] = $this->db->lasterror();
                $error++;
            }
        }

        // Delete project
        if (! $error)
        {
            $sql = "DELETE FROM " . MAIN_DB_PREFIX . "abcvc_projet";
            $sql.= " WHERE rowid=" . $this->id;

            $resql = $this->db->query($sql);
            if (!$resql)
            {
                $this->errors[] = $langs->trans("CantRemoveProject");
                $error++;
            }
        }

        if (! $error)
        {
            $sql = "DELETE FROM " . MAIN_DB_PREFIX . "abcvc_projet_extrafields";
            $sql.= " WHERE fk_object=" . $this->id;

            $resql = $this->db->query($sql);
            if (! $resql)
            {
                $this->errors[] = $this->db->lasterror();
                $error++;
            }
        }

        if (empty($error))
        {
            // We remove directory
            $projectref = dol_sanitizeFileName($this->ref);
            if ($conf->projet->dir_output)
            {
                $dir = $conf->projet->dir_output . "/" . $projectref;
                if (file_exists($dir))
                {
                    $res = @dol_delete_dir_recursive($dir);
                    if (!$res)
                    {
                        $this->errors[] = 'ErrorFailToDeleteDir';
                        $error++;
                    }
                }
            }

            if (!$notrigger)
            {
                // Call trigger
                $result=$this->call_trigger('PROJECT_DELETE',$user);

                if ($result < 0) {
                    $error++;
                }
                // End call triggers
            }
        }

        if (empty($error))
        {
            $this->db->commit();
            return 1;
        }
        else
       {
            foreach ( $this->errors as $errmsg )
            {
                dol_syslog(get_class($this) . "::delete " . $errmsg, LOG_ERR);
                $this->error .= ($this->error ? ', ' . $errmsg : $errmsg);
            }
            dol_syslog(get_class($this) . "::delete " . $this->error, LOG_ERR);
            $this->db->rollback();
            return -1;
        }
    }

    /**
     *      Validate a project
     *
     *      @param      User    $user          User that validate
     *      @param      int     $notrigger     1=Disable triggers
     *      @return     int                    <0 if KO, >0 if OK
     */
    function setValid($user, $notrigger=0)
    {
        global $langs, $conf;

        $error=0;

        if ($this->statut != 1)
        {
            // Check parameters
            if (preg_match('/^'.preg_quote($langs->trans("CopyOf").' ').'/', $this->title))
            {
                $this->error=$langs->trans("ErrorFieldFormat",$langs->transnoentities("Label")).'. '.$langs->trans('RemoveString',$langs->transnoentitiesnoconv("CopyOf"));
                return -1;
            }
            
            $this->db->begin();

            $sql = "UPDATE " . MAIN_DB_PREFIX . "abcvc_projet";
            $sql.= " SET fk_statut = 1";
            $sql.= " WHERE rowid = " . $this->id;
            $sql.= " AND entity = " . $conf->entity;

            dol_syslog(get_class($this)."::setValid", LOG_DEBUG);
            $resql = $this->db->query($sql);
            if ($resql)
            {
                // Call trigger
                if (empty($notrigger))
                {
                    $result=$this->call_trigger('PROJECT_VALIDATE',$user);
                    if ($result < 0) { $error++; }
                    // End call triggers
                }
                
                if (!$error)
                {
                    $this->statut=1;
                    $this->db->commit();
                    return 1;
                }
                else
                {
                    $this->db->rollback();
                    $this->error = join(',', $this->errors);
                    dol_syslog(get_class($this)."::setValid " . $this->error, LOG_ERR);
                    return -1;
                }
            }
            else
            {
                $this->db->rollback();
                $this->error = $this->db->lasterror();
                return -1;
            }
        }
    }

    /**
     *      Close a project
     *
     *      @param      User    $user       User that close project
     *      @return     int                 <0 if KO, 0 if already closed, >0 if OK
     */
    function setClose($user)
    {
        global $langs, $conf;

        $now = dol_now();

        $error=0;

        if ($this->statut != 2)
        {
            $this->db->begin();

            $sql = "UPDATE " . MAIN_DB_PREFIX . "abcvc_projet";
            $sql.= " SET fk_statut = 2, fk_user_close = ".$user->id.", date_close = '".$this->db->idate($now)."'";
            $sql.= " WHERE rowid = " . $this->id;
            $sql.= " AND entity = " . $conf->entity;
            $sql.= " AND fk_statut = 1";

            if (! empty($conf->global->PROJECT_USE_OPPORTUNITIES))
            {
                // TODO What to do if fk_opp_status is not code 'WON' or 'LOST'
            }

            dol_syslog(get_class($this)."::setClose", LOG_DEBUG);
            $resql = $this->db->query($sql);
            if ($resql)
            {
                // Call trigger
                $result=$this->call_trigger('PROJECT_CLOSE',$user);
                if ($result < 0) { $error++; }
                // End call triggers

                if (!$error)
                {
                    $this->statut = 2;
                    $this->db->commit();
                    return 1;
                }
                else
                {
                    $this->db->rollback();
                    $this->error = join(',', $this->errors);
                    dol_syslog(get_class($this)."::setClose " . $this->error, LOG_ERR);
                    return -1;
                }
            }
            else
            {
                $this->db->rollback();
                $this->error = $this->db->lasterror();
                return -1;
            }
        }
        
        return 0;
    }

    /**
     *  Return status label of object
     *
     *  @param  int         $mode       0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto
     *  @return string                  Label
     */
    function getLibStatut($mode=0)
    {
        return $this->LibStatut($this->statut, $mode);
    }

    /**
     *  Renvoi status label for a status
     *
     *  @param  int     $statut     id statut
     *  @param  int     $mode       0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto
     *  @return string              Label
     */
    function LibStatut($statut, $mode=0)
    {
        global $langs;

        if ($mode == 0)
        {
            return $langs->trans($this->statuts_long[$statut]);
        }
        if ($mode == 1)
        {
            return $langs->trans($this->statuts_short[$statut]);
        }
        if ($mode == 2)
        {
            if ($statut == 0)
                return img_picto($langs->trans($this->statuts_long[$statut]), 'statut0') . ' ' . $langs->trans($this->statuts_short[$statut]);
            if ($statut == 1)
                return img_picto($langs->trans($this->statuts_long[$statut]), 'statut4') . ' ' . $langs->trans($this->statuts_short[$statut]);
            if ($statut == 2)
                return img_picto($langs->trans($this->statuts_long[$statut]), 'statut6') . ' ' . $langs->trans($this->statuts_short[$statut]);
        }
        if ($mode == 3)
        {
            if ($statut == 0)
                return img_picto($langs->trans($this->statuts_long[$statut]), 'statut0');
            if ($statut == 1)
                return img_picto($langs->trans($this->statuts_long[$statut]), 'statut4');
            if ($statut == 2)
                return img_picto($langs->trans($this->statuts_long[$statut]), 'statut6');
        }
        if ($mode == 4)
        {
            if ($statut == 0)
                return img_picto($langs->trans($this->statuts_long[$statut]), 'statut0') . ' ' . $langs->trans($this->statuts_long[$statut]);
            if ($statut == 1)
                return img_picto($langs->trans($this->statuts_long[$statut]), 'statut4') . ' ' . $langs->trans($this->statuts_long[$statut]);
            if ($statut == 2)
                return img_picto($langs->trans($this->statuts_long[$statut]), 'statut6') . ' ' . $langs->trans($this->statuts_long[$statut]);
        }
        if ($mode == 5)
        {
            if ($statut == 0)
                return $langs->trans($this->statuts_short[$statut]) . ' ' . img_picto($langs->trans($this->statuts_long[$statut]), 'statut0');
            if ($statut == 1)
                return $langs->trans($this->statuts_short[$statut]) . ' ' . img_picto($langs->trans($this->statuts_long[$statut]), 'statut4');
            if ($statut == 2)
                return $langs->trans($this->statuts_short[$statut]) . ' ' . img_picto($langs->trans($this->statuts_long[$statut]), 'statut6');
        }
    }

    /**
     *  Return clicable name (with picto eventually)
     *
     *  @param  int     $withpicto      0=No picto, 1=Include picto into link, 2=Only picto
     *  @param  string  $option         Variant ('', 'nolink')
     *  @param  int     $addlabel       0=Default, 1=Add label into string, >1=Add first chars into string
     *  @param  string  $moreinpopup    Text to add into popup
     *  @param  string  $sep            Separator between ref and label if option addlabel is set
     *  @param  int     $notooltip      1=Disable tooltip
     *  @return string                  Chaine avec URL
     */
    function getNomUrl($withpicto=0, $option='', $addlabel=0, $moreinpopup='', $sep=' - ', $notooltip=0)
    {
        global $conf, $langs, $user;

        if (! empty($conf->dol_no_mouse_hover)) $notooltip=1;   // Force disable tooltips
        
        $result = '';
        
        $label='';
        if ($option != 'nolink') $label = '<u>' . $langs->trans("ShowProject") . '</u>';
        if (! empty($this->ref))
            $label .= ($label?'<br>':'').'<b>' . $langs->trans('Ref') . ': </b>' . $this->ref;  // The space must be after the : to not being explode when showing the title in img_picto
        if (! empty($this->title))
            $label .= ($label?'<br>':'').'<b>' . $langs->trans('Label') . ': </b>' . $this->title;  // The space must be after the : to not being explode when showing the title in img_picto
        if (! empty($this->thirdparty_name))
            $label .= ($label?'<br>':'').'<b>' . $langs->trans('ThirdParty') . ': </b>' . $this->thirdparty_name;   // The space must be after the : to not being explode when showing the title in img_picto
        if (! empty($this->dateo))
            $label .= ($label?'<br>':'').'<b>' . $langs->trans('DateStart') . ': </b>' . dol_print_date($this->dateo, 'day');   // The space must be after the : to not being explode when showing the title in img_picto
        if (! empty($this->datee))
            $label .= ($label?'<br>':'').'<b>' . $langs->trans('DateEnd') . ': </b>' . dol_print_date($this->datee, 'day'); // The space must be after the : to not being explode when showing the title in img_picto
        if ($moreinpopup) $label.='<br>'.$moreinpopup;

        if ($option != 'nolink')
        {
            if (preg_match('/\.php$/',$option)) {
                $url = dol_buildpath($option,1) . '?id=' . $this->id;
            }
            else if ($option == 'task')
            {
                $url = DOL_URL_ROOT . SUPP_PATH.'/projet/tasks.php?id=' . $this->id;
            }
            else
            {
                $url = DOL_URL_ROOT . SUPP_PATH.'/projet/card.php?id=' . $this->id;
            }
        } else {
            $url = '#';
        }
        
        $linkclose='';
        if (empty($notooltip) && $user->rights->propal->lire)
        {
            if (! empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER))
            {
                $label=$langs->trans("ShowProject");
                $linkclose.=' alt="'.dol_escape_htmltag($label, 1).'"';
            }
            $linkclose.=' title="'.dol_escape_htmltag($label, 1).'"';
            $linkclose.=' class="classfortooltip"';
        }

        $picto = 'projectpub';
        if (!$this->public) $picto = 'project';

        $linkstart = '<a href="'.$url.'"';
        $linkstart.=$linkclose.'>';
        $linkend='</a>';
        
        if ($withpicto) $result.=($linkstart . img_object(($notooltip?'':$label), $picto, ($notooltip?'':'class="classfortooltip"'), 0, 0, $notooltip?0:1) . $linkend);
        if ($withpicto && $withpicto != 2) $result.=' ';
        if ($withpicto != 2) $result.=$linkstart . $this->ref . $linkend . (($addlabel && $this->title) ? $sep . dol_trunc($this->title, ($addlabel > 1 ? $addlabel : 0)) : '');
        return $result;
    }

    function getKNomUrl($withpicto=0, $option='', $addlabel=0, $moreinpopup='', $sep=' - ', $notooltip=0)
    {
        global $conf, $langs, $user;

        if (! empty($conf->dol_no_mouse_hover)) $notooltip=1;   // Force disable tooltips
        
        $result = '';
        
        $label='';
        if ($option != 'nolink') $label = '<u>' . $langs->trans("ShowProject") . '</u>';
        if (! empty($this->ref))
            $label .= ($label?'<br>':'').'<b>' . $langs->trans('Ref') . ': </b>' . $this->ref;  // The space must be after the : to not being explode when showing the title in img_picto
        if (! empty($this->title))
            $label .= ($label?'<br>':'').'<b>' . $langs->trans('Label') . ': </b>' . $this->title;  // The space must be after the : to not being explode when showing the title in img_picto
        if (! empty($this->thirdparty_name))
            $label .= ($label?'<br>':'').'<b>' . $langs->trans('ThirdParty') . ': </b>' . $this->thirdparty_name;   // The space must be after the : to not being explode when showing the title in img_picto
        if (! empty($this->dateo))
            $label .= ($label?'<br>':'').'<b>' . $langs->trans('DateStart') . ': </b>' . dol_print_date($this->dateo, 'day');   // The space must be after the : to not being explode when showing the title in img_picto
        if (! empty($this->datee))
            $label .= ($label?'<br>':'').'<b>' . $langs->trans('DateEnd') . ': </b>' . dol_print_date($this->datee, 'day'); // The space must be after the : to not being explode when showing the title in img_picto
        if ($moreinpopup) $label.='<br>'.$moreinpopup;

        if ($option != 'nolink')
        {
            if (preg_match('/\.php$/',$option)) {
                $url = dol_buildpath($option,1) . '?id=' . $this->id.'&mainmenu=abcvc&leftmenu=';
                ///abcvc/projet/card.php?id=13&mainmenu=abcvc&leftmenu=
            }
            else if ($option == 'task')
            {
                $url = DOL_URL_ROOT . SUPP_PATH.'/projet/tasks.php?id=' . $this->id;
            }
            else
            {
                $url = DOL_URL_ROOT . SUPP_PATH.'/projet/card.php?id=' . $this->id.'&mainmenu=abcvc&leftmenu=';
            }
        } else {
            $url = '#';
        }

        $linkclose='';
        if (empty($notooltip) && $user->rights->propal->lire)
        {
            if (! empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER))
            {
                $label=$langs->trans("ShowProject");
                $linkclose.=' alt="'.dol_escape_htmltag($label, 1).'"';
            }
            $linkclose.=' title="'.dol_escape_htmltag($label, 1).'"';
            $linkclose.=' class="classfortooltip" ';
        }

        $picto = 'projectpub';
        if (!$this->public) $picto = 'project';

        $linkstart = '<a href="'.$url.'"'; //'<span '; //
        $linkstart.= $linkclose.'>';
        $linkend ='</a>'; //</span>'; //
        
        if ($withpicto) $result.=($linkstart . img_object(($notooltip?'':$label), $picto, ($notooltip?'':'class="classfortooltip"'), 0, 0, $notooltip?0:1) . $linkend);
        if ($withpicto && $withpicto != 2) $result.=' ';
        if ($withpicto != 2) $result.=$linkstart . $this->ref . $linkend . (($addlabel && $this->title) ? $sep . dol_trunc($this->title, ($addlabel > 1 ? $addlabel : 0)) : '');
        return $result;
    }





    /**
     *  Initialise an instance with random values.
     *  Used to build previews or test instances.
     *  id must be 0 if object instance is a specimen.
     *
     *  @return void
     */
    function initAsSpecimen()
    {
        global $user, $langs, $conf;

        $now=dol_now();

        // Initialise parameters
        $this->id = 0;
        $this->ref = 'SPECIMEN';
        $this->specimen = 1;
        $this->socid = 1;
        $this->date_c = $now;
        $this->date_m = $now;
        $this->date_start = $now;
        $this->date_end = $now + (3600 * 24 * 365);
        $this->note_public = 'SPECIMEN';
        $this->fk_ele = 20000;
        $this->opp_amount = 20000;
        $this->budget_amount = 10000;

        /*
        $nbp = mt_rand(1, 9);
        $xnbp = 0;
        while ($xnbp < $nbp)
        {
            $line = new Task($this->db);
            $line->fk_project = 0;
            $line->label = $langs->trans("Label") . " " . $xnbp;
            $line->description = $langs->trans("Description") . " " . $xnbp;

            $this->lines[]=$line;
            $xnbp++;
        }
        */
    }

    /**
     *  Check if user has permission on current project
     *
     *  @param  User    $user       Object user to evaluate
     *  @param  string  $mode       Type of permission we want to know: 'read', 'write'
     *  @return int                 >0 if user has permission, <0 if user has no permission
     */
    function restrictedProjectArea($user, $mode='read')
    {
        // To verify role of users
        $userAccess = 0;
        if (($mode == 'read' && ! empty($user->rights->projet->all->lire)) || ($mode == 'write' && ! empty($user->rights->projet->all->creer)) || ($mode == 'delete' && ! empty($user->rights->projet->all->supprimer)))
        {
            $userAccess = 1;
        }
        else if ($this->public && (($mode == 'read' && ! empty($user->rights->projet->lire)) || ($mode == 'write' && ! empty($user->rights->projet->creer)) || ($mode == 'delete' && ! empty($user->rights->projet->supprimer))))
        {
            $userAccess = 1;
        }
        else
        {
            foreach (array('internal', 'external') as $source)
            {
                $userRole = $this->liste_contact(4, $source);
                $num = count($userRole);

                $nblinks = 0;
                while ($nblinks < $num)
                {
                    if ($source == 'internal' && preg_match('/^PROJECT/', $userRole[$nblinks]['code']) && $user->id == $userRole[$nblinks]['id'])
                    {
                        if ($mode == 'read'   && $user->rights->projet->lire)      $userAccess++;
                        if ($mode == 'write'  && $user->rights->projet->creer)     $userAccess++;
                        if ($mode == 'delete' && $user->rights->projet->supprimer) $userAccess++;
                    }
                    $nblinks++;
                }
            }
            //if (empty($nblinks))  // If nobody has permission, we grant creator
            //{
            //  if ((!empty($this->user_author_id) && $this->user_author_id == $user->id))
            //  {
            //      $userAccess = 1;
            //  }
            //}
        }

        return ($userAccess?$userAccess:-1);
    }

    /**
     * Return array of projects a user has permission on, is affected to, or all projects
     *
     * @param   User    $user           User object
     * @param   int     $mode           0=All project I have permission on (assigned to me and public), 1=Projects assigned to me only, 2=Will return list of all projects with no test on contacts
     * @param   int     $list           0=Return array,1=Return string list
     * @param   int     $socid          0=No filter on third party, id of third party
     * @return  array or string         Array of projects id, or string with projects id separated with ","
     */
    function getProjectsAuthorizedForUser($user, $mode=0, $list=0, $socid=0)
    {
        $projects = array();
        $temp = array();


        $sql = "SELECT ".(($mode == 0 || $mode == 1 || $mode == 3) ? "DISTINCT " : "")."p.rowid, p.ref";
        $sql.= " FROM " . MAIN_DB_PREFIX . "abcvc_projet as p";
        if ($mode == 0 || $mode == 1 )
        {
            $sql.= ", " . MAIN_DB_PREFIX . "element_contact as ec";
        }


        if( $mode == 3 ) {
             $sql.= " LEFT JOIN llx_abcvc_projet_task as pt ON (pt.fk_projet = p.rowid)
                      LEFT JOIN llx_element_contact as ec ON (ec.element_id = pt.rowid)";
        }



        $sql.= " WHERE p.entity IN (".getEntity('project',1).")";
        // Internal users must see project he is contact to even if project linked to a third party he can't see.
        //if ($socid || ! $user->rights->societe->client->voir) $sql.= " AND (p.fk_soc IS NULL OR p.fk_soc = 0 OR p.fk_soc = ".$socid.")";
        if ($socid > 0) $sql.= " AND (p.fk_soc IS NULL OR p.fk_soc = 0 OR p.fk_soc = " . $socid . ")";




        // Get id of types of contacts for projects (This list never contains a lot of elements)
        $listofprojectcontacttype=array();
        $sql2 = "SELECT ctc.rowid, ctc.code FROM ".MAIN_DB_PREFIX."c_type_contact as ctc";
        $sql2.= " WHERE ctc.element = '" . $this->element . "'";
        $sql2.= " AND ctc.source = 'internal'";
        $resql = $this->db->query($sql2);

        //print $sql2;

        if ($resql)
        {
            while($obj = $this->db->fetch_object($resql))
            {
                $listofprojectcontacttype[$obj->rowid]=$obj->code;
            }
        }
        else dol_print_error($this->db);
        if (count($listofprojectcontacttype) == 0) $listofprojectcontacttype[0]='0';    // To avoid syntax error if not found

        if ($mode == 0)
        {
            $sql.= " AND ec.element_id = p.rowid";
            $sql.= " AND ( p.public = 1";
            $sql.= " OR ( ec.fk_c_type_contact IN (".join(',', array_keys($listofprojectcontacttype)).")";
            $sql.= " AND ec.fk_socpeople = ".$user->id.")";
            $sql.= " )";
        }
        if ($mode == 1)
        {
            $sql.= " AND ec.element_id = p.rowid";
            $sql.= " AND (";
            $sql.= "  ( ec.fk_c_type_contact IN (".join(',', array_keys($listofprojectcontacttype)).")";
            $sql.= " AND ec.fk_socpeople = ".$user->id.")";
            $sql.= " )";
        }
        if ($mode == 2)
        {
            // No filter. Use this if user has permission to see all project
        }

        if ($mode == 3)
        {
            // AFFECTE A UNE TASK 
            // 196, TASKEXECUTIVE, Responsable
            // 197, TASKCONTRIBUTOR, Intervenant
            $sql.= " AND (";
            $sql.= "  ( ec.fk_c_type_contact IN (196,197)";
            $sql.= " AND ec.fk_socpeople = ".$user->id.")";
            $sql.= " )" ;
        }

        //print $sql;
        //exit();
        $resql = $this->db->query($sql);
        if ($resql)
        {
            $num = $this->db->num_rows($resql);
            $i = 0;
            while ($i < $num)
            {
                $row = $this->db->fetch_row($resql);
                $projects[$row[0]] = $row[1];
                $temp[] = $row[0];
                $i++;
            }

            $this->db->free($resql);

            if ($list)
            {
                if (empty($temp)) return '0';
                $result = implode(',', $temp);
                return $result;
            }
        }
        else
        {
            dol_print_error($this->db);
        }

        return $projects;
    }

     /**
      * Load an object from its id and create a new one in database
      *
      * @param  int     $fromid         Id of object to clone
      * @param  bool    $clone_contact  Clone contact of project
      * @param  bool    $clone_task     Clone task of project
      * @param  bool    $clone_project_file     Clone file of project
      * @param  bool    $clone_task_file        Clone file of task (if task are copied)
      * @param  bool    $clone_note     Clone note of project
      * @param  bool    $move_date      Move task date on clone
      * @param  integer $notrigger      No trigger flag
      * @param  int     $newthirdpartyid  New thirdparty id
      * @return int                     New id of clone
      */
    function createFromClone($fromid,$clone_contact=false,$clone_task=true,$clone_project_file=false,$clone_task_file=false,$clone_note=true,$move_date=true,$notrigger=0,$newthirdpartyid=0)
    {
        global $user,$langs,$conf;

        $error=0;

        dol_syslog("createFromClone clone_contact=".$clone_contact." clone_task=".$clone_task." clone_project_file=".$clone_project_file." clone_note=".$clone_note." move_date=".$move_date,LOG_DEBUG);

        $now = dol_mktime(0,0,0,idate('m',dol_now()),idate('d',dol_now()),idate('Y',dol_now()));

        $clone_project=new Project($this->db);

        $clone_project->context['createfromclone']='createfromclone';

        $this->db->begin();

        // Load source object
        $clone_project->fetch($fromid);
        $clone_project->fetch_optionals();
        if ($newthirdpartyid > 0) $clone_project->socid = $newthirdpartyid;
        $clone_project->fetch_thirdparty();

        $orign_dt_start=$clone_project->date_start;
        $orign_project_ref=$clone_project->ref;

        $clone_project->id=0;
        if ($move_date) {
            $clone_project->date_start = $now;
            if (!(empty($clone_project->date_end)))
            {
                $clone_project->date_end = $clone_project->date_end + ($now - $orign_dt_start);
            }
        }

        $clone_project->datec = $now;

        if (! $clone_note)
        {
                $clone_project->note_private='';
                $clone_project->note_public='';
        }

        //Generate next ref
        $defaultref='';
        $obj = empty($conf->global->PROJECT_ADDON)?'mod_project_simple':$conf->global->PROJECT_ADDON;
        // Search template files
        $file=''; $classname=''; $filefound=0;
        $dirmodels=array_merge(array('/'),(array) $conf->modules_parts['models']);
        foreach($dirmodels as $reldir)
        {
            $file=dol_buildpath($reldir."core/modules/project/".$obj.'.php',0);
            if (file_exists($file))
            {
                $filefound=1;
                dol_include_once($reldir."core/modules/project/".$obj.'.php');
                $modProject = new $obj;
                $defaultref = $modProject->getNextValue(is_object($clone_project->thirdparty)?$clone_project->thirdparty:null, $clone_project);
                break;
            }
        }
        if (is_numeric($defaultref) && $defaultref <= 0) $defaultref='';

        $clone_project->ref=$defaultref;
        $clone_project->title=$langs->trans("CopyOf").' '.$clone_project->title;

        // Create clone
        $result=$clone_project->create($user,$notrigger);

        // Other options
        if ($result < 0)
        {
            $this->error.=$clone_project->error;
            $error++;
        }

        if (! $error)
        {
            //Get the new project id
            $clone_project_id=$clone_project->id;

            //Note Update
            if (!$clone_note)
            {
                $clone_project->note_private='';
                $clone_project->note_public='';
            }
            else
            {
                $this->db->begin();
                $res=$clone_project->update_note(dol_html_entity_decode($clone_project->note_public, ENT_QUOTES),'_public');
                if ($res < 0)
                {
                    $this->error.=$clone_project->error;
                    $error++;
                    $this->db->rollback();
                }
                else
                {
                    $this->db->commit();
                }

                $this->db->begin();
                $res=$clone_project->update_note(dol_html_entity_decode($clone_project->note_private, ENT_QUOTES), '_private');
                if ($res < 0)
                {
                    $this->error.=$clone_project->error;
                    $error++;
                    $this->db->rollback();
                }
                else
                {
                    $this->db->commit();
                }
            }

            //Duplicate contact
            if ($clone_contact)
            {
                $origin_project = new Project($this->db);
                $origin_project->fetch($fromid);

                foreach(array('internal','external') as $source)
                {
                    $tab = $origin_project->liste_contact(-1,$source);

                    foreach ($tab as $contacttoadd)
                    {
                        $clone_project->add_contact($contacttoadd['id'], $contacttoadd['code'], $contacttoadd['source'],$notrigger);
                        if ($clone_project->error == 'DB_ERROR_RECORD_ALREADY_EXISTS')
                        {
                            $langs->load("errors");
                            $this->error.=$langs->trans("ErrorThisContactIsAlreadyDefinedAsThisType");
                            $error++;
                        }
                        else
                        {
                            if ($clone_project->error!='')
                            {
                                $this->error.=$clone_project->error;
                                $error++;
                            }
                        }
                    }
                }
            }

            //Duplicate file
            if ($clone_project_file)
            {
                require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

                $clone_project_dir = $conf->projet->dir_output . "/" . dol_sanitizeFileName($defaultref);
                $ori_project_dir = $conf->projet->dir_output . "/" . dol_sanitizeFileName($orign_project_ref);

                if (dol_mkdir($clone_project_dir) >= 0)
                {
                    $filearray=dol_dir_list($ori_project_dir,"files",0,'','(\.meta|_preview\.png)$','',SORT_ASC,1);
                    foreach($filearray as $key => $file)
                    {
                        $rescopy = dol_copy($ori_project_dir . '/' . $file['name'], $clone_project_dir . '/' . $file['name'],0,1);
                        if (is_numeric($rescopy) && $rescopy < 0)
                        {
                            $this->error.=$langs->trans("ErrorFailToCopyFile",$ori_project_dir . '/' . $file['name'],$clone_project_dir . '/' . $file['name']);
                            $error++;
                        }
                    }
                }
                else
                {
                    $this->error.=$langs->trans('ErrorInternalErrorDetected').':dol_mkdir';
                    $error++;
                }
            }

            //Duplicate task
            if ($clone_task)
            {
                require_once DOL_DOCUMENT_ROOT . SUPP_PATH.'/projet/class/task.class.php';

                $taskstatic = new Task($this->db);

                // Security check
                $socid=0;
                if ($user->societe_id > 0) $socid = $user->societe_id;

                $tasksarray=$taskstatic->getTasksArray(0, 0, $fromid, $socid, 0);

                $tab_conv_child_parent=array();

                // Loop on each task, to clone it
                foreach ($tasksarray as $tasktoclone)
                {
                    $result_clone = $taskstatic->createFromClone($tasktoclone->id,$clone_project_id,$tasktoclone->fk_parent,$move_date,true,false,$clone_task_file,true,false);
                    if ($result_clone <= 0)
                    {
                        $this->error.=$result_clone->error;
                        $error++;
                    }
                    else
                    {
                        $new_task_id=$result_clone;
                        $taskstatic->fetch($tasktoclone->id);

                        //manage new parent clone task id
                        // if the current task has child we store the original task id and the equivalent clone task id
                        if (($taskstatic->hasChildren()) && !array_key_exists($tasktoclone->id,$tab_conv_child_parent))
                        {
                            $tab_conv_child_parent[$tasktoclone->id] =  $new_task_id;
                        }
                    }

                }

                //Parse all clone node to be sure to update new parent
                $tasksarray=$taskstatic->getTasksArray(0, 0, $clone_project_id, $socid, 0);
                foreach ($tasksarray as $task_cloned)
                {
                    $taskstatic->fetch($task_cloned->id);
                    if ($taskstatic->fk_task_parent!=0)
                    {
                        $taskstatic->fk_task_parent=$tab_conv_child_parent[$taskstatic->fk_task_parent];
                    }
                    $res=$taskstatic->update($user,$notrigger);
                    if ($result_clone <= 0)
                    {
                        $this->error.=$taskstatic->error;
                        $error++;
                    }
                }
            }
        }

        unset($clone_project->context['createfromclone']);

        if (! $error)
        {
            $this->db->commit();
            return $clone_project_id;
        }
        else
        {
            $this->db->rollback();
            dol_syslog(get_class($this)."::createFromClone nbError: ".$error." error : " . $this->error, LOG_ERR);
            return -1;
        }
    }


     /**
      *    Shift project task date from current date to delta
      *
      *    @param   timestamp       $old_project_dt_start   old project start date
      *    @return  int             1 if OK or < 0 if KO
      */
    function shiftTaskDate($old_project_dt_start)
    {
        global $user,$langs,$conf;

        $error=0;

        $taskstatic = new Task($this->db);

        // Security check
        $socid=0;
        if ($user->societe_id > 0) $socid = $user->societe_id;

        $tasksarray=$taskstatic->getTasksArray(0, 0, $this->id, $socid, 0);

        foreach ($tasksarray as $tasktoshiftdate)
        {
            $to_update=false;
            // Fetch only if update of date will be made
            if ((!empty($tasktoshiftdate->date_start)) || (!empty($tasktoshiftdate->date_end)))
            {
                //dol_syslog(get_class($this)."::shiftTaskDate to_update", LOG_DEBUG);
                $to_update=true;
                $task = new Task($this->db);
                $result = $task->fetch($tasktoshiftdate->id);
                if (!$result)
                {
                    $error++;
                    $this->error.=$task->error;
                }
            }
            //print "$this->date_start + $tasktoshiftdate->date_start - $old_project_dt_start";exit;

            //Calcultate new task start date with difference between old proj start date and origin task start date
            if (!empty($tasktoshiftdate->date_start))
            {
                $task->date_start           = $this->date_start + ($tasktoshiftdate->date_start - $old_project_dt_start);
            }

            //Calcultate new task end date with difference between origin proj end date and origin task end date
            if (!empty($tasktoshiftdate->date_end))
            {
                $task->date_end             = $this->date_start + ($tasktoshiftdate->date_end - $old_project_dt_start);
            }

            if ($to_update)
            {
                $result = $task->update($user);
                if (!$result)
                {
                    $error++;
                    $this->error.=$task->error;
                }
            }
        }
        if ($error!=0)
        {
            return -1;
        }
        return $result;
    }


     /**
      *    Associate element to a project
      *
      *    @param   string  $tableName          Table of the element to update
      *    @param   int     $elementSelectId    Key-rowid of the line of the element to update
      *    @return  int                         1 if OK or < 0 if KO
      */
    function update_element($tableName, $elementSelectId)
    {
        $sql="UPDATE ".MAIN_DB_PREFIX.$tableName;

        if ($tableName == "actioncomm")
        {
            $sql.= " SET fk_project=".$this->id;
            $sql.= " WHERE id=".$elementSelectId;
        }
        else
        {
            $sql.= " SET fk_projet=".$this->id;
            $sql.= " WHERE rowid=".$elementSelectId;
        }

        dol_syslog(get_class($this)."::update_element", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if (!$resql) {
            $this->error=$this->db->lasterror();
            return -1;
        }else {
            return 1;
        }

    }

    /**
     *    Associate element to a project
     *
     *    @param    string  $tableName          Table of the element to update
     *    @param    int     $elementSelectId    Key-rowid of the line of the element to update
     *    @return   int                         1 if OK or < 0 if KO
     */
    function remove_element($tableName, $elementSelectId)
    {
        $sql="UPDATE ".MAIN_DB_PREFIX.$tableName;

        if ($TableName=="actioncomm")
        {
            $sql.= " SET fk_project=NULL";
            $sql.= " WHERE id=".$elementSelectId;
        }
        else
        {
            $sql.= " SET fk_projet=NULL";
            $sql.= " WHERE rowid=".$elementSelectId;
        }

        dol_syslog(get_class($this)."::remove_element", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if (!$resql) {
            $this->error=$this->db->lasterror();
            return -1;
        }else {
            return 1;
        }

    }

    /**
     *  Create an intervention document on disk using template defined into PROJECT_ADDON_PDF
     *
     *  @param  string      $modele         Force template to use ('' by default)
     *  @param  Translate   $outputlangs    Objet lang to use for translation
     *  @param  int         $hidedetails    Hide details of lines
     *  @param  int         $hidedesc       Hide description
     *  @param  int         $hideref        Hide ref
     *  @return int                         0 if KO, 1 if OK
     */
    public function generateDocument($modele, $outputlangs, $hidedetails=0, $hidedesc=0, $hideref=0)
    {
        global $conf,$langs;

        $langs->load("projects");

        // Positionne modele sur le nom du modele de projet a utiliser
        /*if (! dol_strlen($modele))
        {
            if (! empty($conf->global->PROJECT_ADDON_PDF))
            {
                $modele = $conf->global->PROJECT_ADDON_PDF;
            }
            else
            {
                $modele='baleine';
            }
        }*/
        $modele='abcvc';

        $modelpath = "core/modules/projectAbcvc/doc/";

        return $this->commonGenerateDocument($modelpath, $modele, $outputlangs, $hidedetails, $hidedesc, $hideref);
    }


    /**
     * Load time spent into this->weekWorkLoad and this->weekWorkLoadPerTask for all day of a week of project
     *
     * @param   int     $datestart      First day of week (use dol_get_first_day to find this date)
     * @param   int     $taskid         Filter on a task id
     * @param   int     $userid         Time spent by a particular user
     * @return  int                     <0 if OK, >0 if KO
     */
    public function loadTimeSpent($datestart,$taskid=0,$userid=0)
    {
        $error=0;

        if (empty($datestart)) dol_print_error('','Error datestart parameter is empty');

        $sql = "SELECT ptt.rowid as taskid, ptt.task_duration, ptt.task_date, ptt.fk_task, ptt.note";
        $sql.= " FROM ".MAIN_DB_PREFIX."abcvc_projet_task_time AS ptt, ".MAIN_DB_PREFIX."abcvc_projet_task as pt";
        $sql.= " WHERE ptt.fk_task = pt.rowid";
        $sql.= " AND pt.fk_projet = ".$this->id;
        $sql.= " AND (ptt.task_date >= '".$this->db->idate($datestart)."' ";
        $sql.= " AND ptt.task_date <= '".$this->db->idate($datestart + (7 * 24 * 3600) - 1)."')";
        if ($task_id) $sql.= " AND ptt.fk_task=".$taskid;
        if (is_numeric($userid)) $sql.= " AND ptt.fk_user=".$userid;

        //print $sql;
        $resql=$this->db->query($sql);
        if ($resql)
        {

                $num = $this->db->num_rows($resql);
                $i = 0;
                // Loop on each record found, so each couple (project id, task id)
                 while ($i < $num)
                {
                        $obj=$this->db->fetch_object($resql);
                        $day=$this->db->jdate($obj->task_date);
                        $this->weekWorkLoad[$day] +=  $obj->task_duration;
                        $this->weekWorkLoadPerTask[$day][$obj->fk_task] = $obj->task_duration;
                        $this->weekNotePerTask[$day][$obj->fk_task] = $obj->note;
                        $i++;
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





/* ************************************************************************************************************************************************

Recuperation timespent dates/ task/user

************************************************************************************************************************************************ */

    public function loadTimeSpentAll($date_sql_de, $date_sql_a, $taskid=0,$userid=0)
    {
        $error=0;

        //if (empty($datestart)) dol_print_error('','Error datestart parameter is empty');

        $sql = "SELECT ptt.rowid as taskid, ptt.task_duration, ptt.task_date, ptt.fk_task, ptt.note, ptt.task_datehour, ptt.task_type";
        $sql.= " FROM ".MAIN_DB_PREFIX."abcvc_projet_task_time AS ptt, ".MAIN_DB_PREFIX."abcvc_projet_task as pt";
        $sql.= " WHERE ptt.fk_task = pt.rowid";
        $sql.= " AND pt.fk_projet = ".$this->id;
        $sql.= " AND (ptt.task_date >= '".$date_sql_de."' ";
        $sql.= " AND ptt.task_date <= '".$date_sql_a."')";
        if ($task_id) $sql.= " AND ptt.fk_task=".$taskid;
        if ($userid!=0) $sql.= " AND ptt.fk_user=".$userid;
        
        $sql.= " GROUP BY ptt.task_date";
        //print $sql;
        //exit();

        $resql=$this->db->query($sql);
        if ($resql)
        {

                $num = $this->db->num_rows($resql);
                $i = 0;
                // Loop on each record found, so each couple (project id, task id)
                 while ($i < $num)
                {
                        $obj=$this->db->fetch_object($resql);
                        $day=$this->db->jdate($obj->task_date);

                        $duration= $obj->task_duration;
                        $task_datehour= $obj->task_datehour;
                        $task_type= $obj->task_type;
                        if(is_null($task_type)) $task_type = 0;



                        $H = explode(' ',$task_datehour);
                        $H2 = explode(':',$H[1]);
                        $debut = $H2[0].':'.$H2[1];
                        $debut_sec = $H2[1]*60 + ($H2[0]*60*60);
                         //+5h / 18000 sec ->1h repas ?
                        if($duration>=18000){
                            $task_datehourfin = date('Y-m-d H:i:s', strtotime($task_datehour) + $duration + 3600);
                        } else {
                            $task_datehourfin = date('Y-m-d H:i:s', strtotime($task_datehour) + $duration);
                        }
                        
                        $H = explode(' ',$task_datehourfin);
                        $H2 = explode(':',$H[1]);
                        $fin = $H2[0].':'.$H2[1];
                        $fin_sec = $H2[1]*60 + ($H2[0]*60*60);

                        $taskInfos =  array(
                            'date_debut'=>$task_datehour,
                            'debut'=>$debut,
                            'debut_sec'=>$debut_sec,

                            'date_fin'=>$task_datehourfin,
                            'fin'=>$fin,
                            'fin_sec'=>$fin_sec,

                            'duration_sec'=>$duration,
                            'type'=>$task_type,
                            'note'=>$obj->note,
                            'timespentid'=>$obj->taskid
                        );
                    //var_dump($taskInfos);
                    //exit();

                        $this->weekWorkLoad[$day] +=  $obj->task_duration;
                        $this->weekWorkLoadPerTask[$day][$obj->fk_task] = $taskInfos; //$obj->task_duration;
                        $this->weekNotePerTask[$day][$obj->fk_task] = $obj->note;
                        $i++;
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



    /**
     * Load indicators for dashboard (this->nbtodo and this->nbtodolate)
     *
     * @param   User    $user   Objet user
     * @return WorkboardResponse|int <0 if KO, WorkboardResponse if OK
     */
    function load_board($user)
    {
        global $conf, $langs;

        $mine=0; $socid=$user->societe_id;

        $projectsListId = $this->getProjectsAuthorizedForUser($user,$mine?$mine:($user->rights->projet->all->lire?2:0),1,$socid);

        $sql = "SELECT p.rowid, p.fk_statut as status, p.fk_opp_status, p.datee as datee";
        $sql.= " FROM (".MAIN_DB_PREFIX."projet as p";
        $sql.= ")";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s on p.fk_soc = s.rowid";
        if (! $user->rights->societe->client->voir && ! $socid) $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON sc.fk_soc = s.rowid";
        $sql.= " WHERE p.fk_statut = 1";
        $sql.= " AND p.entity IN (".getEntity('project').')';
        if ($mine || ! $user->rights->projet->all->lire) $sql.= " AND p.rowid IN (".$projectsListId.")";
        // No need to check company, as filtering of projects must be done by getProjectsAuthorizedForUser
        //if ($socid || ! $user->rights->societe->client->voir) $sql.= "  AND (p.fk_soc IS NULL OR p.fk_soc = 0 OR p.fk_soc = ".$socid.")";
        if ($socid) $sql.= "  AND (p.fk_soc IS NULL OR p.fk_soc = 0 OR p.fk_soc = ".$socid.")";
        if (! $user->rights->societe->client->voir && ! $socid) $sql.= " AND ((s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id.") OR (s.rowid IS NULL))";

        $resql=$this->db->query($sql);
        if ($resql)
        {
            $project_static = new ProjectABCVC($this->db);

            $response = new WorkboardResponse();
            $response->warning_delay = $conf->projet->warning_delay/60/60/24;
            $response->label = $langs->trans("OpenedProjects");
            if ($user->rights->projet->all->lire) $response->url = DOL_URL_ROOT.SUPP_PATH.'/projet/list.php?search_status=1&mainmenu=project';
            else $response->url = DOL_URL_ROOT.SUPP_PATH.'/projet/list.php?mode=mine&search_status=1&mainmenu=project';
            $response->img = img_object($langs->trans("Projects"),"project");

            // This assignment in condition is not a bug. It allows walking the results.
            while ($obj=$this->db->fetch_object($resql))
            {
                $response->nbtodo++;

                $project_static->statut = $obj->status;
                $project_static->opp_status = $obj->opp_status;
                $project_static->datee = $this->db->jdate($obj->datee);

                if ($project_static->hasDelay()) {
                    $response->nbtodolate++;
                }
            }

            return $response;
        }
        else
        {
            $this->error=$this->db->error();
            return -1;
        }
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
            'projet'
        );

        return CommonObject::commonReplaceThirdparty($db, $origin_id, $dest_id, $tables);
    }


    /**
     *      Charge indicateurs this->nb pour le tableau de bord
     *
     *      @return     int         <0 if KO, >0 if OK
     */
    function load_state_board()
    {
        global $user;

        $this->nb=array();

        $sql = "SELECT count(p.rowid) as nb";
        $sql.= " FROM ".MAIN_DB_PREFIX."projet as p";
        $sql.= " WHERE";
        $sql.= " p.entity IN (".getEntity('projet', 1).")";
        if (! $user->rights->projet->all->lire) 
        {
            $projectsListId = $this->getProjectsAuthorizedForUser($user,0,1);
            $sql .= "AND p.rowid IN (".$projectsListId.")";
        }

        $resql=$this->db->query($sql);
        if ($resql)
        {
            while ($obj=$this->db->fetch_object($resql))
            {
                $this->nb["projects"]=$obj->nb;
            }
            $this->db->free($resql);
            return 1;
        }
        else
        {
            dol_print_error($this->db);
            $this->error=$this->db->error();
            return -1;
        }
    }


    /**
     * Is the project delayed?
     *
     * @return bool
     */
    public function hasDelay()
    {
        global $conf;

        if (! ($this->statut == 1)) return false;
        if (! $this->datee && ! $this->date_end) return false;

        $now = dol_now();

        return ($this->datee ? $this->datee : $this->date_end) < ($now - $conf->projet->warning_delay);
    }   


    /**
     *  Charge les informations d'ordre info dans l'objet commande
     *
     *  @param  int     $id       Id of order
     *  @return void
     */
    function info($id)
    {
        $sql = 'SELECT c.rowid, datec as datec, tms as datem,';
        $sql.= ' date_close as datecloture,';
        $sql.= ' fk_user_creat as fk_user_author, fk_user_close as fk_use_cloture';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'projet as c';
        $sql.= ' WHERE c.rowid = '.$id;
        $result=$this->db->query($sql);
        if ($result)
        {
            if ($this->db->num_rows($result))
            {
                $obj = $this->db->fetch_object($result);
                $this->id = $obj->rowid;
                if ($obj->fk_user_author)
                {
                    $cuser = new User($this->db);
                    $cuser->fetch($obj->fk_user_author);
                    $this->user_creation   = $cuser;
                }

                if ($obj->fk_user_cloture)
                {
                    $cluser = new User($this->db);
                    $cluser->fetch($obj->fk_user_cloture);
                    $this->user_cloture   = $cluser;
                }

                $this->date_creation     = $this->db->jdate($obj->datec);
                $this->date_modification = $this->db->jdate($obj->datem);
                $this->date_cloture      = $this->db->jdate($obj->datecloture);
            }

            $this->db->free($result);

        }
        else
        {
            dol_print_error($this->db);
        }
    }

    /**
     * Sets object to supplied categories.
     *
     * Deletes object from existing categories not supplied.
     * Adds it to non existing supplied categories.
     * Existing categories are left untouch.
     *
     * @param int[]|int $categories Category or categories IDs
     */
    public function setCategories($categories)
    {
        // Decode type
        $type_id = Categorie::TYPE_PROJECT;
        $type_text = 'project';


        // Handle single category
        if (!is_array($categories)) {
            $categories = array($categories);
        }

        // Get current categories
        require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
        $c = new Categorie($this->db);
        $existing = $c->containing($this->id, $type_id, 'id');

        // Diff
        if (is_array($existing)) {
            $to_del = array_diff($existing, $categories);
            $to_add = array_diff($categories, $existing);
        } else {
            $to_del = array(); // Nothing to delete
            $to_add = $categories;
        }

        // Process
        foreach ($to_del as $del) {
            if ($c->fetch($del) > 0) {
                $result=$c->del_type($this, $type_text);
                if ($result<0) {
                    $this->errors=$c->errors;
                    $this->error=$c->error;
                    return -1;
                }
            }
        }
        foreach ($to_add as $add) {
            if ($c->fetch($add) > 0) {
                $result=$c->add_type($this, $type_text);
                if ($result<0) {
                    $this->errors=$c->errors;
                    $this->error=$c->error;
                    return -1;
                }
            }
        }

        return 1;
    }

    
    /**
     *  Create an array of tasks of current project
     * 
     *  @param  User   $user       Object user we want project allowed to
     *  @return int                >0 if OK, <0 if KO
     */
    function getLinesArray($user)
    {
        require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/task.class.php';
        $taskstatic = new Task($this->db);

        $this->lines = $taskstatic->getTasksArray(0, $user, $this->id, 0, 0);
    }



    function selectProjectTasks($selectedtask='', $projectid=0, $htmlname='task_parent', $modeproject=0, $modetask=0, $mode=0, $useempty=0, $disablechildoftaskid=0)
    {
        global $user, $langs;

        require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/task.class.php';

        //print $modeproject.'-'.$modetask;
        $task=new TaskABCVC($this->db);
        $tasksarray=$task->getTasksArray($modetask?$user:0, $modeproject?$user:0, $projectid, 0, $mode);
        if ($tasksarray)
        {
            print '<select class="flat" name="'.$htmlname.'">';
            if ($useempty) print '<option value="0">&nbsp;</option>';
            $j=0;
            $level=0;
            $this->_pLineSelect($j, 0, $tasksarray, $level, $selectedtask, $projectid, $disablechildoftaskid);
            print '</select>';
        }
        else
        {
            print '<div class="warning">'.$langs->trans("NoProject").'</div>';
        }
    }

    /**
     * Write lines of a project (all lines of a project if parent = 0)
     *
     * @param   int     $inc                    Cursor counter
     * @param   int     $parent                 Id of parent task we want to see
     * @param   array   $lines                  Array of task lines
     * @param   int     $level                  Level
     * @param   int     $selectedtask           Id selected task
     * @param   int     $selectedproject        Id selected project
     * @param   int     $disablechildoftaskid   1=Disable task that are child of the provided task id
     * @return  void
     */
    private function _pLineSelect(&$inc, $parent, $lines, $level=0, $selectedtask=0, $selectedproject=0, $disablechildoftaskid=0)
    {
        global $langs, $user, $conf;

        $lastprojectid=0;

        $numlines=count($lines);
        for ($i = 0 ; $i < $numlines ; $i++)
        {
            if ($lines[$i]->fk_parent == $parent)
            {
                $var = !$var;

                //var_dump($selectedproject."--".$selectedtask."--".$lines[$i]->fk_project."_".$lines[$i]->id);     // $lines[$i]->id may be empty if project has no lines

                // Break on a new project
                if ($parent == 0)   // We are on a task at first level
                {
                    if ($lines[$i]->fk_project != $lastprojectid)   // Break found on project
                    {
                        if ($i > 0) print '<option value="0" disabled>----------</option>';
                        print '<option value="'.$lines[$i]->fk_project.'_0"';
                        if ($selectedproject == $lines[$i]->fk_project) print ' selected';
                        print '>';  // Project -> Task
                        print $langs->trans("Project").' '.$lines[$i]->projectref;
                        if (empty($lines[$i]->public))
                        {
                            print ' ('.$langs->trans("Visibility").': '.$langs->trans("PrivateProject").')';
                        }
                        else
                        {
                            print ' ('.$langs->trans("Visibility").': '.$langs->trans("SharedProject").')';
                        }
                        //print '-'.$parent.'-'.$lines[$i]->fk_project.'-'.$lastprojectid;
                        print "</option>\n";

                        $lastprojectid=$lines[$i]->fk_project;
                        $inc++;
                    }
                }

                $newdisablechildoftaskid=$disablechildoftaskid;

                // Print task
                if (isset($lines[$i]->id))      // We use isset because $lines[$i]->id may be null if project has no task and are on root project (tasks may be caught by a left join). We enter here only if '0' or >0
                {
                    // Check if we must disable entry
                    $disabled=0;
                    if ($disablechildoftaskid && (($lines[$i]->id == $disablechildoftaskid || $lines[$i]->fk_parent == $disablechildoftaskid)))
                    {
                        $disabled++;
                        if ($lines[$i]->fk_parent == $disablechildoftaskid) $newdisablechildoftaskid=$lines[$i]->id;    // If task is child of a disabled parent, we will propagate id to disable next child too
                    }

                    print '<option value="'.$lines[$i]->fk_project.'_'.$lines[$i]->id.'"';
                    if (($lines[$i]->id == $selectedtask) || ($lines[$i]->fk_project.'_'.$lines[$i]->id == $selectedtask)) print ' selected';
                    if ($disabled) print ' disabled';
                    print '>';
                    print $langs->trans("Project").' '.$lines[$i]->projectref;
                    if (empty($lines[$i]->public))
                    {
                        print ' ('.$langs->trans("Visibility").': '.$langs->trans("PrivateProject").')';
                    }
                    else
                    {
                        print ' ('.$langs->trans("Visibility").': '.$langs->trans("SharedProject").')';
                    }
                    if ($lines[$i]->id) print ' > ';
                    for ($k = 0 ; $k < $level ; $k++)
                    {
                        print "&nbsp;&nbsp;&nbsp;";
                    }
                    print $lines[$i]->ref.' '.$lines[$i]->label."</option>\n";
                    $inc++;
                }

                $level++;
                if ($lines[$i]->id) $this->_pLineSelect($inc, $lines[$i]->id, $lines, $level, $selectedtask, $selectedproject, $newdisablechildoftaskid);
                $level--;
            }
        }
    }







    /**
     * getABCVCHeader
     *
     * @param int $id_project
     * @param string $screen
     */
    public function getABCVCHeader($id_project, $screen) {
        ob_start();
        //**************************************************************************************************************
        // $object = new ProjectABCVC($this->db);

        //var_dump($id_project);
        if(!is_null($id_project)){
        ?>

        <div class="container-fluid">
            <div class="row">

                <div class="col-xs-12 btn-group ">
                    <!-- PROJECTS -->
                            <a href="/abcvc/projet/card.php?id=<?php echo $id_project?>" class="btn btn-default">Proiect</a>
                            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="caret"></span>
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a href="/abcvc/projet/card.php?leftmenu=abcvc&action=create">Proiect nou</a></li>
                                <li><a href="/abcvc/projet/card.php?id=<?php echo $id_project?>&action=edit">Modifică</a></li>
                                <li><a href="/abcvc/projet/card.php?id=<?php echo $id_project?>&action=close">Închide</a></li>
                                <li><a href="/abcvc/projet/card.php?id=<?php echo $id_project?>&action=validate">Activează</a></li>
                                <li><a href="/abcvc/projet/card.php?id=<?php echo $id_project?>&action=delete">Șterge</a></li>
                                <!--
                                <li><a href="/abcvc/projet/list.php?idmenu=89&mainmenu=abcvc&leftmenu=">Liste</a></li>
                                -->
                                <?php /*if($this->statut == 0) : ?>
                                    <li><a href="/abcvc/projet/card.php?id=14&action=validate">Validate</a></li>
                                <?php endif;*/ ?>
                            </ul>
                            <a href="/abcvc/projet/contact.php?id=<?php echo $id_project?>" class="btn btn-default">Contacte</a>
                            <a href="/abcvc/projet/element.php?id=<?php echo $id_project?>" class="btn btn-default">Sinteză</a>
                            <!-- <a href="/abcvc/projet/note.php?id=<?php echo $id_project?>" class="btn btn-default">Notițe</a> -->
                            <a href="/abcvc/projet/document.php?id=<?php echo $id_project?>" class="btn btn-default">Fișiere similare</a>
                        <!--

                            <a href="/abcvc/projet/tasks.php?id=<?php echo $id_project?>" class="btn btn-default">Tasks</a>
                            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="caret"></span>
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a href="/abcvc/projet/tasks.php?id=<?php echo $id_project?>&action=create">Create task</a></li>
                                <li role="separator" class="divider"></li>
                                <li><a href="/abcvc/projet/tasks/time.php?projectid=<?php echo $id_project?>&withproject=1">List of time consumed</a></li>


                            </ul>
                        </div>
                        -->
                            <a href="/abcvc/projet/ganttview.php?id=<?php echo $id_project?>" class="btn btn-default">Program</a>
                            <a href="/abcvc/projet/info.php?id=<?php echo $id_project?>" class="btn btn-default">Jurnal</a>
                            <!--
                            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="caret"></span>
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a href="/comm/action/card.php?action=create&projectid=<?php echo $id_project?>&backtopage=/abcvc/projet/info.php?id=<?php echo $id_project?>">Nouveau évenement</a></li>
                            </ul>
                            -->
                </div>
            </div>  
            <hr/>
        </div>
        <?php  
        }                     
        //**************************************************************************************************************
        $output = ob_get_clean();
        return $output;
    }


    // custom banner...
    public function dol_banner_tab($object, $paramid, $morehtml='', $shownav=1, $fieldid='rowid', $fieldref='ref', $morehtmlref='', $moreparam='', $nodbprefix=0, $morehtmlleft='', $morehtmlstatus='', $onlybanner=0, $morehtmlright='')
    {
        global $conf, $form, $user, $langs;

        //var_dump($object);

        $maxvisiblephotos=1;
        $showimage=1;
        $showbarcode=empty($conf->barcode->enabled)?0:($object->barcode?1:0);
        if (! empty($conf->global->MAIN_USE_ADVANCED_PERMS) && empty($user->rights->barcode->lire_advance)) $showbarcode=0;
        $modulepart='unknown';
        if ($object->element == 'societe')      $modulepart='societe';
        if ($object->element == 'contact')      $modulepart='contact';
        if ($object->element == 'member')       $modulepart='memberphoto';
        if ($object->element == 'user')         $modulepart='userphoto';
        if ($object->element == 'product')      $modulepart='product';
        
        if ($object->element == 'product')
        {
            $width=80; $cssclass='photoref';
            $showimage=$object->is_photo_available($conf->product->multidir_output[$object->entity]);
            $maxvisiblephotos=(isset($conf->global->PRODUCT_MAX_VISIBLE_PHOTO)?$conf->global->PRODUCT_MAX_VISIBLE_PHOTO:5);
            if ($conf->browser->phone) $maxvisiblephotos=1;
            if ($showimage) $morehtmlleft.='<div class="floatleft inline-block valignmiddle divphotoref">'.$object->show_photos($conf->product->multidir_output[$object->entity],'small',$maxvisiblephotos,0,0,0,$width,0).'</div>';
            else 
            {
                if (!empty($conf->global->PRODUCT_NODISPLAYIFNOPHOTO)) {
                    $nophoto='';
                    $morehtmlleft.='<div class="floatleft inline-block valignmiddle divphotoref"></div>';
                }
                elseif ($conf->browser->layout != 'phone') {    // Show no photo link
                    $nophoto='/public/theme/common/nophoto.png';
                    $morehtmlleft.='<div class="floatleft inline-block valignmiddle divphotoref"><img class="photo'.$modulepart.($cssclass?' '.$cssclass:'').'" alt="No photo" border="0"'.($width?' width="'.$width.'"':'').($height?' height="'.$height.'"':'').' src="'.DOL_URL_ROOT.$nophoto.'"></div>';
                }
            }
        }
        else 
        {
            /*if ($showimage) 
            {
                if ($modulepart != 'unknown') 
                {
                    $phototoshow = $form->showphoto($modulepart,$object,0,0,0,'photoref','small',1,0,$maxvisiblephotos);
                    if ($phototoshow)
                    {
                        $morehtmlleft.='<div class="floatleft inline-block valignmiddle divphotoref">';
                        $morehtmlleft.=$phototoshow;
                        $morehtmlleft.='</div>';
                    }
                }
                else if ($conf->browser->layout != 'phone')      // Show No photo link (picto of pbject)
                {
                    $morehtmlleft.='<div class="floatleft inline-block valignmiddle divphotoref">xxx';
                    if ($object->element == 'action') 
                    {
                        $cssclass='photorefcenter';
                        $nophoto=img_picto('', 'title_agenda', '', false, 1);
                        $morehtmlleft.='<div class="floatleft inline-block valignmiddle divphotoref"><div class="photoref"><img class="photo'.$modulepart.($cssclass?' '.$cssclass:'').'" alt="No photo" border="0"'.($width?' width="'.$width.'"':'').($height?' height="'.$height.'"':'').' src="'.$nophoto.'"></div></div>';
                    }
                    else
                    {
                        $width=14; $cssclass='photorefcenter';
                        $picto = $object->picto;
                        if ($object->element == 'project' && ! $object->public) $picto = 'project'; // instead of projectpub
                        $nophoto=img_picto('', 'object_'.$picto, '', false, 1);
                        $morehtmlleft.='<div class="floatleft inline-block valignmiddle divphotoref"><div class="photoref"><img class="photo'.$modulepart.($cssclass?' '.$cssclass:'').'" alt="No photo" border="0"'.($width?' width="'.$width.'"':'').($height?' height="'.$height.'"':'').' src="'.$nophoto.'"></div></div>';
                    }
                    $morehtmlleft.='</div>';
                }
            }*/
        }
        if ($showbarcode) $morehtmlleft.='<div class="floatleft inline-block valignmiddle divphotoref">'.$form->showbarcode($object).'</div>';
        if ($object->element == 'societe' && ! empty($conf->use_javascript_ajax) && $user->rights->societe->creer && ! empty($conf->global->MAIN_DIRECT_STATUS_UPDATE)) {
            $morehtmlstatus.=ajax_object_onoff($object, 'status', 'status', 'InActivity', 'ActivityCeased');
        } 
        elseif ($object->element == 'product')
        {
            //$morehtmlstatus.=$langs->trans("Status").' ('.$langs->trans("Sell").') ';
            if (! empty($conf->use_javascript_ajax) && $user->rights->produit->creer && ! empty($conf->global->MAIN_DIRECT_STATUS_UPDATE)) {
                $morehtmlstatus.=ajax_object_onoff($object, 'status', 'tosell', 'ProductStatusOnSell', 'ProductStatusNotOnSell');
            } else {
                $morehtmlstatus.=$object->getLibStatut(5,0);
            }
            $morehtmlstatus.=' &nbsp; ';
            //$morehtmlstatus.=$langs->trans("Status").' ('.$langs->trans("Buy").') ';
            if (! empty($conf->use_javascript_ajax) && $user->rights->produit->creer && ! empty($conf->global->MAIN_DIRECT_STATUS_UPDATE)) {
                $morehtmlstatus.=ajax_object_onoff($object, 'status_buy', 'tobuy', 'ProductStatusOnBuy', 'ProductStatusNotOnBuy');
            } else {
                $morehtmlstatus.=$object->getLibStatut(5,1);
            }
        }
        elseif ($object->element == 'facture' || $object->element == 'invoice' || $object->element == 'invoice_supplier')
        {
            $tmptxt=$object->getLibStatut(6, $object->totalpaye);
            if (empty($tmptxt) || $tmptxt == $object->getLibStatut(3) || $conf->browser->layout=='phone') $tmptxt=$object->getLibStatut(5, $object->totalpaye); 
            $morehtmlstatus.=$tmptxt;
        }
        elseif ($object->element == 'chargesociales')
        {
            $tmptxt=$object->getLibStatut(6, $object->totalpaye);
            if (empty($tmptxt) || $tmptxt == $object->getLibStatut(3) || $conf->browser->layout=='phone') $tmptxt=$object->getLibStatut(5, $object->totalpaye); 
            $morehtmlstatus.=$tmptxt;
        }
        elseif ($object->element == 'loan')
        {
            $tmptxt=$object->getLibStatut(6, $object->totalpaye);
            if (empty($tmptxt) || $tmptxt == $object->getLibStatut(3) || $conf->browser->layout=='phone') $tmptxt=$object->getLibStatut(5, $object->totalpaye); 
            $morehtmlstatus.=$tmptxt;
        }
        elseif ($object->element == 'contrat') 
        {
            if ($object->statut==0) $morehtmlstatus.=$object->getLibStatut(2);
            else $morehtmlstatus.=$object->getLibStatut(4);
        }
        else { // Generic case
            $tmptxt=$object->getLibStatut(6);
            if (empty($tmptxt) || $tmptxt == $object->getLibStatut(3) || $conf->browser->layout=='phone') $tmptxt=$object->getLibStatut(5); 
            $morehtmlstatus.=$tmptxt;
        }
        if (! empty($object->name_alias)) $morehtmlref.='<div class="refidno">'.$object->name_alias.'</div>';      // For thirdparty
        
        if ($object->element == 'product' || $object->element == 'bank_account')
        {
            if(! empty($object->label)) $morehtmlref.='<div class="refidno">'.$object->label.'</div>';
        }

        if ($object->element != 'product' && $object->element != 'bookmark') 
        {
            $morehtmlref.='<div class="refidno">';
            $morehtmlref.=$object->getBannerAddress('refaddress',$object);
            $morehtmlref.='</div>';
        }
        if (! empty($conf->global->MAIN_SHOW_TECHNICAL_ID) && in_array($object->element, array('societe', 'contact', 'member', 'product')))
        {
            $morehtmlref.='<div style="clear: both;"></div><div class="refidno">';
            $morehtmlref.=$langs->trans("TechnicalID").': '.$object->id;
            $morehtmlref.='</div>';
        }
        
        print '<div class="'.($onlybanner?'arearefnobottom ':'arearef ').'heightref valignmiddle" width="100%">';
        print $form->showrefnav($object, $paramid, $morehtml, $shownav, $fieldid, $fieldref, $morehtmlref, $moreparam, $nodbprefix, $morehtmlleft, $morehtmlstatus, $morehtmlright);
        //print '<div class="pull-right">[TODO others status/flag]</div>';
        
        print '</div>';



        print '<div class="underrefbanner clearboth"></div>';
    }




    /**
     *      CUSTOM override
     *      Show html area with actions (done or not, ignore the name of function)
     *
     *      @param  Conf               $conf           Object conf
     *      @param  Translate          $langs          Object langs
     *      @param  DoliDB             $db             Object db
     *      @param  Adherent|Societe|Project   $filterobj      Object third party or member or project
     *      @param  Contact            $objcon         Object contact
     *      @param  int                $noprint        Return string but does not output it
     *      @param  string             $actioncode     Filter on actioncode
     *      @param  string             $donetodo       Filter on event 'done' or 'todo' or ''=nofilter (all).
     *      @param  array              $filters        Filter on other fields
     *      @param  string             $sortfield      Sort field
     *      @param  string             $sortorder      Sort order
     *      @return mixed                              Return html part or void if noprint is 1
     *      TODO change function to be able to list event linked to an object.
     */
    public function show_actions_done($conf, $langs, $db, $filterobj, $objcon='', $noprint=0, $actioncode='', $donetodo='done', $filters=array(), $sortfield='a.datep,a.id', $sortorder='DESC')
    {
        global $bc,$user,$conf;
        global $form;

        global $param;
        
        // Check parameters
        if (! is_object($filterobj)) dol_print_error('','BadParameter');

        //var_dump(get_class($filterobj));

        $out='';
        $histo=array();
        $numaction = 0 ;
        $now=dol_now('tzuser');

        if (! empty($conf->agenda->enabled))
        {
            // Recherche histo sur actioncomm
            $sql = "SELECT a.id, a.label,";
            $sql.= " a.datep as dp,";
            $sql.= " a.datep2 as dp2,";
            $sql.= " a.note, a.percent,";
            $sql.= " a.fk_element, a.elementtype,";
            $sql.= " a.fk_user_author, a.fk_contact,";
            $sql.= " c.code as acode, c.libelle as alabel, c.picto as apicto,";
            $sql.= " u.login, u.rowid as user_id";
            if (get_class($filterobj) == 'Societe')  $sql.= ", sp.lastname, sp.firstname";
            if (get_class($filterobj) == 'Adherent') $sql.= ", m.lastname, m.firstname";
            if (get_class($filterobj) == 'CommandeFournisseur') $sql.= ", o.ref";
            
            $sql.= " FROM ".MAIN_DB_PREFIX."user as u, ".MAIN_DB_PREFIX."actioncomm as a";
            $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_actioncomm as c ON a.fk_action = c.id";
            if (get_class($filterobj) == 'Societe')  $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON a.fk_contact = sp.rowid";
            if (get_class($filterobj) == 'Adherent') $sql.= ", ".MAIN_DB_PREFIX."adherent as m";
            if (get_class($filterobj) == 'CommandeFournisseur') $sql.= ", ".MAIN_DB_PREFIX."commande_fournisseur as o";
           

            $sql.= " WHERE u.rowid = a.fk_user_author";
            $sql.= " AND a.entity IN (".getEntity('agenda', 1).")";
            if (get_class($filterobj) == 'Societe'  && $filterobj->id) $sql.= " AND a.fk_soc = ".$filterobj->id;
            
            if (get_class($filterobj) == 'ProjectABCVC' && $filterobj->id) $sql.= " AND a.fk_project = ".$filterobj->id;
            
            if (get_class($filterobj) == 'Adherent') 
            {
                $sql.= " AND a.fk_element = m.rowid AND a.elementtype = 'member'";
                if ($filterobj->id) $sql.= " AND a.fk_element = ".$filterobj->id;
            }
            if (get_class($filterobj) == 'CommandeFournisseur')
            {
                $sql.= " AND a.fk_element = o.rowid AND a.elementtype = 'order_supplier'";
                if ($filterobj->id) $sql.= " AND a.fk_element = ".$filterobj->id;
            }
            if (is_object($objcon) && $objcon->id) $sql.= " AND a.fk_contact = ".$objcon->id;
            // Condition on actioncode
            if (! empty($actioncode))
            {
                if (empty($conf->global->AGENDA_USE_EVENT_TYPE))
                {
                    if ($actioncode == 'AC_NON_AUTO') $sql.= " AND c.type != 'systemauto'";
                    elseif ($actioncode == 'AC_ALL_AUTO') $sql.= " AND c.type = 'systemauto'";
                    else 
                    {
                        if ($actioncode == 'AC_OTH') $sql.= " AND c.type != 'systemauto'";
                        if ($actioncode == 'AC_OTH_AUTO') $sql.= " AND c.type = 'systemauto'";
                    }
                }
                else
                {
                    if ($actioncode == 'AC_NON_AUTO') $sql.= " AND c.type != 'systemauto'";
                    elseif ($actioncode == 'AC_ALL_AUTO') $sql.= " AND c.type = 'systemauto'";
                    else $sql.= " AND c.code = '".$db->escape($actioncode)."'";
                }
            }
            if ($donetodo == 'todo') $sql.= " AND ((a.percent >= 0 AND a.percent < 100) OR (a.percent = -1 AND a.datep > '".$db->idate($now)."'))";
            if ($donetodo == 'done') $sql.= " AND (a.percent = 100 OR (a.percent = -1 AND a.datep <= '".$db->idate($now)."'))";
            if (is_array($filters) && $filters['search_agenda_label']) $sql.= natural_search('a.label', $filters['search_agenda_label']);
            $sql.= $db->order($sortfield, $sortorder);

            dol_syslog("company.lib::show_actions_done", LOG_DEBUG);
            $resql=$db->query($sql);
            if ($resql)
            {
                $i = 0 ;
                $num = $db->num_rows($resql);
                $var=true;
                while ($i < $num)
                {
                    $obj = $db->fetch_object($resql);
                    
                    //if ($donetodo == 'todo') $sql.= " AND ((a.percent >= 0 AND a.percent < 100) OR (a.percent = -1 AND a.datep > '".$db->idate($now)."'))";
                    //if ($donetodo == 'done') $sql.= " AND (a.percent = 100 OR (a.percent = -1 AND a.datep <= '".$db->idate($now)."'))";
                    $tododone='';
                    if (($obj->percent >= 0 and $obj->percent < 100) || ($obj->percent == -1 && $obj->datep > $now)) $tododone='todo';

                    $histo[$numaction]=array(
                        'type'=>'action',
                        'tododone'=>$tododone,
                        'id'=>$obj->id,
                        'datestart'=>$db->jdate($obj->dp),
                        'dateend'=>$db->jdate($obj->dp2),
                        'note'=>$obj->label,
                        'percent'=>$obj->percent,
                        'userid'=>$obj->user_id,
                        'login'=>$obj->login,
                        'contact_id'=>$obj->fk_contact,
                        'lastname'=>$obj->lastname,
                        'firstname'=>$obj->firstname,
                        'fk_element'=>$obj->fk_element,
                        'elementtype'=>$obj->elementtype,
                        // Type of event
                        'acode'=>$obj->acode,
                        'alabel'=>$obj->alabel,
                        'libelle'=>$obj->alabel,    // deprecated
                        'apicto'=>$obj->apicto
                    );
                    
                    $numaction++;
                    $i++;
                }
            }
            else
            {
                dol_print_error($db);
            }
        }

        // Add also event from emailings. FIXME This should be replaced by an automatic event
        if (! empty($conf->mailing->enabled) && ! empty($objcon->email))
        {
            $langs->load("mails");

            $sql = "SELECT m.rowid as id, mc.date_envoi as da, m.titre as note, '100' as percentage,";
            $sql.= " 'AC_EMAILING' as acode,";
            $sql.= " u.rowid as user_id, u.login";  // User that valid action
            $sql.= " FROM ".MAIN_DB_PREFIX."mailing as m, ".MAIN_DB_PREFIX."mailing_cibles as mc, ".MAIN_DB_PREFIX."user as u";
            $sql.= " WHERE mc.email = '".$db->escape($objcon->email)."'";   // Search is done on email.
            $sql.= " AND mc.statut = 1";
            $sql.= " AND u.rowid = m.fk_user_valid";
            $sql.= " AND mc.fk_mailing=m.rowid";
            $sql.= " ORDER BY mc.date_envoi DESC, m.rowid DESC";

            dol_syslog("company.lib::show_actions_done", LOG_DEBUG);
            $resql=$db->query($sql);
            if ($resql)
            {
                $i = 0 ;
                $num = $db->num_rows($resql);
                $var=true;
                while ($i < $num)
                {
                    $obj = $db->fetch_object($resql);
                    $histo[$numaction]=array(
                            'type'=>'mailing',
                            'tododone'=>'done',
                            'id'=>$obj->id,
                            'datestart'=>$db->jdate($obj->da),
                            'dateend'=>$db->jdate($obj->da),
                            'note'=>$obj->note,
                            'percent'=>$obj->percentage,
                            'acode'=>$obj->acode,
                            'userid'=>$obj->user_id,
                            'login'=>$obj->login
                    );
                    $numaction++;
                    $i++;
                }
                $db->free($resql);
            }
            else
            {
                dol_print_error($db);
            }
        }


        if (! empty($conf->agenda->enabled) || (! empty($conf->mailing->enabled) && ! empty($objcon->email)))
        {
            $delay_warning=$conf->global->MAIN_DELAY_ACTIONS_TODO*24*60*60;
            
            require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
            require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
            require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
            require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
            require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
            require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';

            $formactions=new FormActions($db);
            
            $actionstatic=new ActionComm($db);
            $userstatic=new User($db);
            $contactstatic = new Contact($db);

            // TODO mutualize/uniformize
            $propalstatic=new Propal($db);
            $orderstatic=new Commande($db);
            $supplierorderstatic=new CommandeFournisseur($db);
            $facturestatic=new Facture($db);

            $out.='<form name="listactionsfilter" class="listactionsfilter" action="' . $_SERVER["PHP_SELF"] . '" method="POST">';
            if ($objcon && get_class($objcon) == 'Contact' && get_class($filterobj) == 'Societe')
            {
                $out.='<input type="hidden" name="id" value="'.$objcon->id.'" />';
            }
            else
            {
                $out.='<input type="hidden" name="id" value="'.$filterobj->id.'" />';
            }
            if (get_class($filterobj) == 'Societe') $out.='<input type="hidden" name="socid" value="'.$filterobj->id.'" />';
            
            $out.="\n";
            
            $out.='<div class="div-table-responsive-no-min">';
            $out.='<table class="noborder" width="100%">';
            $out.='<tr class="liste_titre">';
            if ($donetodo)
            {
                $out.='<td>';
                if (get_class($filterobj) == 'Societe') $out.='<a href="'.DOL_URL_ROOT.'/comm/action/listactions.php?socid='.$filterobj->id.'&amp;status=done">';
                $out.=($donetodo != 'done' ? $langs->trans("ActionsToDoShort") : '');
                $out.=($donetodo != 'done' && $donetodo != 'todo' ? ' / ' : '');
                $out.=($donetodo != 'todo' ? $langs->trans("ActionsDoneShort") : '');
                //$out.=$langs->trans("ActionsToDoShort").' / '.$langs->trans("ActionsDoneShort");
                if (get_class($filterobj) == 'Societe') $out.='</a>';
                $out.='</td>';
            }
            $out.=getTitleFieldOfList($langs->trans("Ref"), 0, $_SERVER["PHP_SELF"], 'a.id', '', $param, '', $sortfield, $sortorder);
            $out.='<td class="maxwidth100onsmartphone">'.$langs->trans("Label").'</td>';
            $out.=getTitleFieldOfList($langs->trans("Date"), 0, $_SERVER["PHP_SELF"], 'a.datep,a.id', '', $param, '', $sortfield, $sortorder);
            $out.='<td>'.$langs->trans("Type").'</td>';
            $out.='<td></td>';
            $out.='<td></td>';
            $out.='<td>'.$langs->trans("Owner").'</td>';
            $out.=getTitleFieldOfList($langs->trans("Status"), 0, $_SERVER["PHP_SELF"], 'a.percent', '', $param, 'align="center"', $sortfield, $sortorder);
            $out.='<td class="maxwidthsearch">';
            //TODO Add selection of fields
            $out.='</td>';
            $out.='</tr>';

            
            $out.='<tr class="liste_titre">';
            if ($donetodo)
            {
                $out.='<td class="liste_titre"></td>';
            }
            $out.='<td class="liste_titre"></td>';
            $out.='<td class="liste_titre maxwidth100onsmartphone"><input type="text" class="maxwidth100onsmartphone" name="search_agenda_label" value="'.$filters['search_agenda_label'].'"></td>';
            $out.='<td class="liste_titre"></td>';
            $out.='<td class="liste_titre">';
            $out.=$formactions->select_type_actions($actioncode, "actioncode", '', empty($conf->global->AGENDA_USE_EVENT_TYPE)?1:-1, 0, 0, 1);
            $out.='</td>';
            $out.='<td class="liste_titre"></td>';
            $out.='<td class="liste_titre"></td>';
            $out.='<td class="liste_titre"></td>';
            $out.='<td class="liste_titre"></td>';
            // Action column
            $out.='<td class="liste_titre" align="middle">';
            $searchpitco=$form->showFilterAndCheckAddButtons($massactionbutton?1:0, 'checkforselect', 1);
            $out.=$searchpitco;
            $out.='</td>';
            $out.='</tr>';
            
            foreach ($histo as $key=>$value)
            {
                $var=!$var;
                $actionstatic->fetch($histo[$key]['id']);    // TODO Do we need this, we already have a lot of data of line into $histo

                $out.="<tr ".$bc[$var].">";
                
                // Done or todo
                if ($donetodo)
                {
                    $out.='<td class="nowrap">';
                    $out.='</td>';
                }
                
                // Ref
                $out.='<td class="nowrap">';
                $out.=$actionstatic->getNomUrl(1, -1);
                $out.='</td>';
                
                // Title
                $out.='<td>';
                if (isset($histo[$key]['type']) && $histo[$key]['type']=='action')
                {
                    $actionstatic->type_code=$histo[$key]['acode'];
                    $transcode=$langs->trans("Action".$histo[$key]['acode']);
                    $libelle=($transcode!="Action".$histo[$key]['acode']?$transcode:$histo[$key]['alabel']);
                    //$actionstatic->libelle=$libelle;
                    $libelle=$histo[$key]['note'];
                    $actionstatic->id=$histo[$key]['id'];
                    $out.=dol_trunc($libelle,120);
                }
                if (isset($histo[$key]['type']) && $histo[$key]['type']=='mailing')
                {
                    $out.='<a href="'.DOL_URL_ROOT.'/comm/mailing/card.php?id='.$histo[$key]['id'].'">'.img_object($langs->trans("ShowEMailing"),"email").' ';
                    $transcode=$langs->trans("Action".$histo[$key]['acode']);
                    $libelle=($transcode!="Action".$histo[$key]['acode']?$transcode:'Send mass mailing');
                    $out.=dol_trunc($libelle,120);
                }
                $out.='</td>';
                
                // Date
                $out.='<td class="nowrap">';
                $out.=dol_print_date($histo[$key]['datestart'],'dayhour');
                if ($histo[$key]['dateend'] && $histo[$key]['dateend'] != $histo[$key]['datestart'])
                {
                    $tmpa=dol_getdate($histo[$key]['datestart'],true);
                    $tmpb=dol_getdate($histo[$key]['dateend'],true);
                    if ($tmpa['mday'] == $tmpb['mday'] && $tmpa['mon'] == $tmpb['mon'] && $tmpa['year'] == $tmpb['year']) $out.='-'.dol_print_date($histo[$key]['dateend'],'hour');
                    else $out.='-'.dol_print_date($histo[$key]['dateend'],'dayhour');
                }
                $late=0;
                if ($histo[$key]['percent'] == 0 && $histo[$key]['datestart'] && $db->jdate($histo[$key]['datestart']) < ($now - $delay_warning)) $late=1;
                if ($histo[$key]['percent'] == 0 && ! $histo[$key]['datestart'] && $histo[$key]['dateend'] && $db->jdate($histo[$key]['datestart']) < ($now - $delay_warning)) $late=1;
                if ($histo[$key]['percent'] > 0 && $histo[$key]['percent'] < 100 && $histo[$key]['dateend'] && $db->jdate($histo[$key]['dateend']) < ($now - $delay_warning)) $late=1;
                if ($histo[$key]['percent'] > 0 && $histo[$key]['percent'] < 100 && ! $histo[$key]['dateend'] && $histo[$key]['datestart'] && $db->jdate($histo[$key]['datestart']) < ($now - $delay_warning)) $late=1;
                if ($late) $out.=img_warning($langs->trans("Late")).' ';
                $out.="</td>\n";
                
                // Type
                $out.='<td>';
                if (! empty($conf->global->AGENDA_USE_EVENT_TYPE))
                {
                    if ($histo[$key]['apicto']) $out.=img_picto('', $histo[$key]['apicto']);
                    else {
                        if ($histo[$key]['acode'] == 'AC_TEL')   $out.=img_picto('', 'object_phoning').' ';
                        if ($histo[$key]['acode'] == 'AC_FAX')   $out.=img_picto('', 'object_phoning_fax').' ';
                        if ($histo[$key]['acode'] == 'AC_EMAIL') $out.=img_picto('', 'object_email').' ';
                    }
                    $out.=$actionstatic->type;
                }
                else {
                    $typelabel = $actionstatic->type;
                    if ($histo[$key]['acode'] != 'AC_OTH_AUTO') $typelabel = $langs->trans("ActionAC_MANUAL"); 
                    $out.=$typelabel;
                }
                $out.='</td>';

                // Title of event
                //$out.='<td>'.dol_trunc($histo[$key]['note'], 40).'</td>';

                // Objet lie
                // TODO mutualize/uniformize
                $out.='<td>';
                //var_dump($histo[$key]['elementtype']);
                if (isset($histo[$key]['elementtype']))
                {
                    if ($histo[$key]['elementtype'] == 'propal' && ! empty($conf->propal->enabled))
                    {
                        //$propalstatic->ref=$langs->trans("ProposalShort");
                        //$propalstatic->id=$histo[$key]['fk_element'];
                        if ($propalstatic->fetch($histo[$key]['fk_element'])>0) {
                            $propalstatic->type=$histo[$key]['ftype'];
                            $out.=$propalstatic->getNomUrl(1);
                        } else {
                            $out.= $langs->trans("ProposalDeleted");
                        }
                    }
                    elseif (($histo[$key]['elementtype'] == 'order' || $histo[$key]['elementtype'] == 'commande') && ! empty($conf->commande->enabled))
                    {
                        //$orderstatic->ref=$langs->trans("Order");
                        //$orderstatic->id=$histo[$key]['fk_element'];
                        if ($orderstatic->fetch($histo[$key]['fk_element'])>0) {
                            $orderstatic->type=$histo[$key]['ftype'];
                            $out.=$orderstatic->getNomUrl(1);
                        } else {
                            $out.= $langs->trans("OrderDeleted");
                        }
                    }
                    elseif (($histo[$key]['elementtype'] == 'invoice' || $histo[$key]['elementtype'] == 'facture') && ! empty($conf->facture->enabled))
                    {
                        //$facturestatic->ref=$langs->trans("Invoice");
                        //$facturestatic->id=$histo[$key]['fk_element'];
                        if ($facturestatic->fetch($histo[$key]['fk_element'])>0) {
                            $facturestatic->type=$histo[$key]['ftype'];
                            $out.=$facturestatic->getNomUrl(1,'compta');
                        } else {
                            $out.= $langs->trans("InvoiceDeleted");
                        }
                    }
                    else $out.='&nbsp;';
                }
                else $out.='&nbsp;';
                $out.='</td>';

                // Contact pour cette action
                if (! empty($objcon->id) && isset($histo[$key]['contact_id']) && $histo[$key]['contact_id'] > 0)
                {
                    $contactstatic->lastname=$histo[$key]['lastname'];
                    $contactstatic->firstname=$histo[$key]['firstname'];
                    $contactstatic->id=$histo[$key]['contact_id'];
                    $out.='<td width="120">'.$contactstatic->getNomUrl(1,'',10).'</td>';
                }
                else
                {
                    $out.='<td>&nbsp;</td>';
                }

                // Auteur
                $out.='<td class="nowrap" width="80">';
                //$userstatic->id=$histo[$key]['userid'];
                //$userstatic->login=$histo[$key]['login'];
                //$out.=$userstatic->getLoginUrl(1);
                $userstatic->fetch($histo[$key]['userid']);
                $out.=$userstatic->getNomUrl(1);
                $out.='</td>';

                // Statut
                $out.='<td class="nowrap" align="center">'.$actionstatic->LibStatut($histo[$key]['percent'],3,1,$histo[$key]['datestart']).'</td>';

                // Actions
                $out.='<td></td>';
                
                $out.="</tr>\n";
                $i++;
            }
            $out.="</table>\n";
            $out.="</div>\n";
        }

        $out.='</form>';
        
        if ($noprint) return $out;
        else print $out;
    }
























    //**************************************************************************************************************
    //
    //
    // LOTS
    // 
    // 
    //**************************************************************************************************************


    /**
     *    Create lot
     *
     *    @param    User    $user           User making creation
     *    @param    int     $notrigger      Disable triggers
     *    @return   int                     <0 if KO, id of created project if OK
     */
    function create_lot($user, $row, $notrigger=0)
    {
        global $conf, $langs;

        $error = 0;
        $ret = 0;

        $now=dol_now();


        //test si code/ref existe deja?
        $sql = "
        SELECT rowid 
        FROM llx_abcvc_projet_lots
        WHERE fk_projet = ".$row['id_projet']." and ref = '".$this->db->escape($row['code'])."'";
        $resql = $this->db->query($sql);
        $obj = $this->db->fetch_object($resql);
        //hmm deja pris !
        if(!is_null($obj)){
            return 'Cette référence est déja utilisée';
        }


        $this->db->begin();

        //todo calc
        $ordering = 0;

        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "abcvc_projet_lots (";
        $sql.= "ref";
        $sql.= ", entity";
        $sql.= ", fk_projet";
        $sql.= ", label";
        $sql.= ", description";

        $sql.= ", datec";

        $sql.= ", ordering";        

        $sql.= ", fk_user_creat";
        $sql.= ", fk_statut";

        $sql.= ") VALUES (";
        $sql.= " '" . $this->db->escape($row['code']) . "'";
        $sql.= ", ".$conf->entity;
        $sql.= ", '" . $this->db->escape($row['id_projet']) . "'";
        $sql.= ", '" . $this->db->escape($row['label']) . "'";
        $sql.= ", '" . $this->db->escape($row['description']) . "'";

        $sql.= ", '".$this->db->idate($now)."'";
        $sql.= ", '".$ordering."'";
        $sql.= ", " . $user->id;
        $sql.= ", 1";

        $sql.= ")";
        //var_dump($sql);
        //exit();

        dol_syslog(get_class($this)."::create", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX . "abcvc_projet_lots");
            $ret = $this->id;

            if (!$notrigger) {
                // Call trigger
                $result=$this->call_trigger('PROJECT_LOT_CREATE',$user);
                if ($result < 0) { $error++; }
                // End call triggers
            }
        } else {
            $this->error = $this->db->lasterror();
            $this->errno = $this->db->lasterrno();
            $error++;
        }

        if (!$error) {
            $this->db->commit();
            return $ret;
        } else {
            $this->db->rollback();
            return $this->error;
        }
    }

    /**
     * Update lot
     *
     * @param  User     $user       User object of making update
     * @param  int      $notrigger  1=Disable all triggers
     * @return int                  <=0 if KO, >0 if OK
     */
    function update_lot($user, $row, $notrigger=0)
    {

        global $langs, $conf;

        $error=0;
        /*      TODO map
                $sql.= ", label";
                $sql.= ", description";

                $sql.= ", datec";

                $sql.= ", ordering";        

                $sql.= ", fk_user_creat";
                $sql.= ", fk_statut";
                'label'=>$label,            
                'ref'=>$ref,
                'description'=>$description,
                'id_projet'=>$id_projet,
                'id'=>$id_lot
        */

        $this->db->begin();

            $sql = "UPDATE " . MAIN_DB_PREFIX . "abcvc_projet_lots SET";

            $sql.= " ref = '" . $this->db->escape($row["ref"]) . "'";
            $sql.= ", label = '" . $this->db->escape($row["label"]) . "'";
            $sql.= ", description = '" . $this->db->escape($row["description"]) . "'"; 
            $sql.= " WHERE rowid = " . $row["id"];


        dol_syslog(get_class($this)."::update", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if (!$notrigger)
            {
                // Call trigger
                $result=$this->call_trigger('PROJECT_OFFER_MODIFY',$user);
                if ($result < 0) { $error++; }
                // End call triggers
            }
            if (! $error )
            {
                $this->db->commit();
                $result = 1;
            }
            else
          {
                $this->db->rollback();
                $result = -1;
            }
        }
        else
        {
            $this->error = $this->db->lasterror();
            $this->errors[] = $this->error;
            $this->db->rollback();
            if ($this->db->lasterrno() == 'DB_ERROR_RECORD_ALREADY_EXISTS')
            {
                $result = -4;
            }
            else
            {
                $result = -2;
            }
            dol_syslog(get_class($this)."::update error " . $result . " " . $this->error, LOG_ERR);
        }


        return $result;
    }


    /**
     * delete lot
     *
     * @param  User     $user       User object of making update
     * @param  int      $notrigger  1=Disable all triggers
     * @return int                  <=0 if KO, >0 if OK
     */
    function delete_lot($user, $row, $notrigger=0)
    {

        global $langs, $conf;

        $error=0;
        /*  
            'id_projet'=>$id_projet,
            'id_lot'=>$id_lot
        */
        
        //childs ?
        //------------------
        $sql = "SELECT group_concat(c.rowid) as ids_cat
        FROM llx_abcvc_projet_categories as c
        WHERE c.fk_lot = ".$row['id_lot'];
        $resql=$this->db->query($sql);
        $childs = $this->db->fetch_object($resql);
        $ids_cat_childs = array();
        if(!is_null($childs->ids_cat)){
            $ids_cat_childs = explode(',',$childs->ids_cat);
        }

        if(count($ids_cat_childs)>0){
            $sql = "SELECT 
            t.rowid as id_poste,
            ( select GROUP_CONCAT(rowid) from llx_abcvc_projet_task where fk_task_parent = t.rowid) as ids_subposte,
            ( select GROUP_CONCAT(rowid) from llx_abcvc_projet_task where FIND_IN_SET(fk_task_parent, ids_subposte) ) as ids_subsubposte
            FROM llx_abcvc_projet_task as t
            WHERE t.fk_categorie IN (".implode(',',$ids_cat_childs).")";
            $resql=$this->db->query($sql);
            $nb_childs = $this->db->num_rows($resql);
            $ids_task_childs = array();
            for ($i=0; $i < $nb_childs ; $i++) { 
                $childs = $this->db->fetch_object($resql);
                //var_dump($childs);
                $ids_task_childs[]=$childs->id_poste;
                if(!is_null($childs->ids_subposte)){
                    $ar_ids = explode(',',$childs->ids_subposte);
                    $ids_task_childs = array_merge($ids_task_childs,$ar_ids);
                }
                if(!is_null($childs->ids_subsubposte)){
                    $ar_ids = explode(',',$childs->ids_subsubposte);
                    $ids_task_childs = array_merge($ids_task_childs,$ar_ids);
                }            
            }

            // var_dump($ids_task_childs);
            // exit();
            if(count($ids_task_childs)>0){
                $this->delete_tasks($ids_task_childs);
            }

            //couic cats
            $sql = "
            DELETE FROM llx_abcvc_projet_categories 
            WHERE rowid IN (".implode(',',$ids_cat_childs).")";
            $resql=$this->db->query($sql);
        }

        //couic lot
        $sql = "
        DELETE FROM llx_abcvc_projet_lots 
        WHERE rowid = ".$row['id_lot'];
        $resql=$this->db->query($sql);  

        if($resql){
            return 1;
        } else {
            return $this->db->lasterror();
        }
       
    }



    //**************************************************************************************************************
    //
    //
    // CATEGORIES
    // 
    // 
    //**************************************************************************************************************


    /**
     *    Create categories
     *
     *    @param    User    $user           User making creation
     *    @param    int     $notrigger      Disable triggers
     *    @return   int                     <0 if KO, id of created project if OK
     */
    function create_category($user, $row, $notrigger=0)
    {
        global $conf, $langs;

        $error = 0;
        $ret = 0;

        $now=dol_now();

        $this->db->begin();


        //todo calc
        $ordering = 0;

        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "abcvc_projet_categories (";
        $sql.= "ref";
        $sql.= ", entity";
        $sql.= ", fk_projet";
        $sql.= ", fk_lot";
        $sql.= ", label";
        $sql.= ", description";

        $sql.= ", datec";

        $sql.= ", ordering";        

        $sql.= ", fk_user_creat";
        $sql.= ", fk_statut";

        $sql.= ") VALUES (";
        $sql.= " '" . $this->db->escape($row['code']) . "'";
        $sql.= ", ".$conf->entity;
        $sql.= ", '" . $this->db->escape($row['id_projet']) . "'";
        $sql.= ", '" . $this->db->escape($row['id_lot']) . "'";
        $sql.= ", '" . $this->db->escape($row['label']) . "'";
        $sql.= ", '" . $this->db->escape($row['description']) . "'";

        $sql.= ", '".$this->db->idate($now)."'";
        $sql.= ", '".$ordering."'";
        $sql.= ", " . $user->id;
        $sql.= ", 1";

        $sql.= ")";
        //var_dump($sql);
        //exit();

        dol_syslog(get_class($this)."::create", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX . "abcvc_projet_categories");
            $ret = $this->id;

            if (!$notrigger) {
                // Call trigger
                $result=$this->call_trigger('PROJECT_CATEGORY_CREATE',$user);
                if ($result < 0) { $error++; }
                // End call triggers
            }
        } else {
            $this->error = $this->db->lasterror();
            $this->errno = $this->db->lasterrno();
            $error++;
        }

        if (!$error) {
            $this->db->commit();
            return $ret;
        } else {
            $this->db->rollback();
            return $this->error;
        }
    }
  
    function update_category($user, $row, $notrigger=0)
    {

        global $langs, $conf;

        $error=0;

        /*      TODO map
                $sql.= ", label";
                $sql.= ", description";

                $sql.= ", datec";

                $sql.= ", ordering";        

                $sql.= ", fk_user_creat";
                $sql.= ", fk_statut";
                'label'=>$label,            
                'ref'=>$ref,
                'description'=>$description,
                'id_projet'=>$id_projet,
                'id'=>$id_lot
        */

        $this->db->begin();

            $sql = "UPDATE " . MAIN_DB_PREFIX . "abcvc_projet_categories SET";

            $sql.= " ref = '" . $this->db->escape($row["ref"]) . "'";
            $sql.= ", label = '" . $this->db->escape($row["label"]) . "'";
            $sql.= ", description = '" . $this->db->escape($row["description"]) . "'"; 
            $sql.= " WHERE rowid = " . $row["id"];


        dol_syslog(get_class($this)."::update", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if (!$notrigger)
            {
                // Call trigger
                $result=$this->call_trigger('PROJECT_OFFER_MODIFY',$user);
                if ($result < 0) { $error++; }
                // End call triggers
            }
            if (! $error )
            {
                $this->db->commit();
                $result = 1;
            }
            else
          {
                $this->db->rollback();
                $result = -1;
            }
        }
        else
        {
            $this->error = $this->db->lasterror();
            $this->errors[] = $this->error;
            $this->db->rollback();
            if ($this->db->lasterrno() == 'DB_ERROR_RECORD_ALREADY_EXISTS')
            {
                $result = -4;
            }
            else
            {
                $result = -2;
            }
            dol_syslog(get_class($this)."::update error " . $result . " " . $this->error, LOG_ERR);
        }


        return $result;
    }

    /**
     * delete category
     *
     * @param  User     $user       User object of making update
     * @param  int      $notrigger  1=Disable all triggers
     * @return int                  <=0 if KO, >0 if OK
     */
    function delete_category($user, $row, $notrigger=0)
    {

        global $langs, $conf;

        $error=0;
        /*  
            'id_projet'=>$id_projet,
            'id_category'=>$id_category
        */
        //childs ?
        //------------------
        $sql = "SELECT 
        t.rowid as id_poste,
        ( select GROUP_CONCAT(rowid) from llx_abcvc_projet_task where fk_task_parent = t.rowid) as ids_subposte,
        ( select GROUP_CONCAT(rowid) from llx_abcvc_projet_task where FIND_IN_SET(fk_task_parent, ids_subposte) ) as ids_subsubposte
        FROM llx_abcvc_projet_task as t
        WHERE t.fk_categorie = ".$row['id_category'];
        $resql=$this->db->query($sql);
        $nb_childs = $this->db->num_rows($resql);
        $ids_task_childs = array();
        for ($i=0; $i < $nb_childs ; $i++) { 
            $childs = $this->db->fetch_object($resql);
            //var_dump($childs);
            $ids_task_childs[]=$childs->id_poste;
            if(!is_null($childs->ids_subposte)){
                $ar_ids = explode(',',$childs->ids_subposte);
                $ids_task_childs = array_merge($ids_task_childs,$ar_ids);
            }
            if(!is_null($childs->ids_subsubposte)){
                $ar_ids = explode(',',$childs->ids_subsubposte);
                $ids_task_childs = array_merge($ids_task_childs,$ar_ids);
            }            
        }
        // var_dump($ids_task_childs);
        // exit();
        if(count($ids_task_childs)>0){
            $this->delete_tasks($ids_task_childs);
        }
        //couic
        $sql = "
        DELETE FROM llx_abcvc_projet_categories 
        WHERE rowid = ".$row['id_category'];
        $resql=$this->db->query($sql);  

        if($resql){
            return 1;
        } else {
            return $this->db->lasterror();
        }
       
    }

    //**************************************************************************************************************
    //
    //
    // POSTES
    // 
    // 
    //**************************************************************************************************************
    //
    function create_poste( $user, $row, $notrigger = 0 ) 
    {
            global $conf, $langs;

            $error = 0;
            $ret = 0;
            $objtask=new TaskABCVC($this->db);
            $now = dol_now();
            $this->db->begin();
            //var_dump($row);
            //exit;
            if($row['start_date'] != ''){
                $tmp_date0 = explode(' ',$row['start_date']);
                $tmp_date = explode('/',$tmp_date0[0]);
                $ok_start_date = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' '.$tmp_date0[1];
            } else {
                $ok_start_date = '';
            }    
            if($row['end_date'] != ''){
                $tmp_date0 = explode(' ',$row['end_date']);
                $tmp_date = explode('/', $tmp_date0[0]);
                $ok_end_date = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' '.$tmp_date0[1];
            } else {
                $ok_end_date = '';
            }    

            
            $ordering = 0;
            $poste_factfourn = implode(",",$row['add_factfourn']);    
            // var_dump($poste_factfourn);        
            $planned_workload_s = $row['plannedworkload_poste'] / 0.000277777778 ; 

            $sql = "INSERT INTO " . MAIN_DB_PREFIX . "abcvc_projet_task (";
            $sql.= "ref";
            $sql.= ", fk_projet";
            $sql.= ", fk_zone";
            $sql.= ", datec ";
            $sql.= ", label";
            $sql.= ", description";
            $sql.= ", fk_categorie";
            $sql.= ", dateo";        
            $sql.= ", datee";
            $sql.= ", planned_workload";
            $sql.= ", fk_user_creat";
            $sql.= ", progress";
            $sql.= ", cost";
            $sql.= ", cost_mo";
            $sql.= ", progress_estimated";
            $sql.= ", fact_fourn";
            $sql.= ") VALUES (";
            $sql.= " '" . $this->db->escape($row['code_poste']) . "'";
            $sql.=", '" . $this->db->escape($row['id_projet']) . "'";
            $sql.=", '" . $this->db->escape($row['id_zone']) . "'";
            $sql.=", '" . $this->db->idate($now)."'";        
            $sql.=", '" . $this->db->escape($row['label']) . "'";
            $sql.=", '" . $this->db->escape($row['description']) . "'";
            $sql.=", '" . $this->db->escape($row['id_category']) . "'";
            $sql.= ", " . ( ($ok_start_date!='')?"'".$this->db->escape($ok_start_date)."'":'NULL');
            $sql.= ", " . ( ($ok_end_date!='')?"'".$this->db->escape($ok_end_date)."'":'NULL'); 
            $sql.=", '" . $this->db->escape($planned_workload_s) . "'";
            $sql.=", " . $user->id;
            $sql.=", '" . $this->db->escape($row['declared_progress']) . "'";
            $sql.=", '" . $this->db->escape($row['price']) . "'";
            $sql.=", '" . $this->db->escape($row['cost_mo']) . "'";
            $sql.=", '" . $this->db->escape($row['estimated_progress']) . "'";
            $sql.=", '" . $this->db->escape($poste_factfourn) . "'";
            //$sql.= " WHERE rowid = " . $row["id"];
            $sql.= ")";
            // var_dump($sql);
            // exit();

            dol_syslog(get_class($this)."::create", LOG_DEBUG);
            $resql = $this->db->query($sql);
            if ($resql) {
                $objtask->id = $this->db->last_insert_id(MAIN_DB_PREFIX . "abcvc_projet_task");
                $ret = $objtask->id;

                if (!$notrigger) {
                    // Call trigger
                    $result=$this->call_trigger('PROJECT_CATEGORY_CREATE',$user);
                    if ($result < 0) { $error++; }
                    // End call triggers
                }
            } else {
                $this->error = $this->db->lasterror();
                $this->errno = $this->db->lasterrno();
                $error++;
            }

            if (!$error) {
                $this->db->commit();

                //INSERT CONTACT IN DATABASE
                //$objtask->add_contact($fk_socpeople, $type_contact)
                //
                foreach ($row["executive"] as $contact_executive) {
                    //var_dump($contact_executive);
                    $objtask->add_contact($contact_executive, "TASKEXECUTIVE");
                }
                foreach ($row["contributor"] as $contact_contributor) {
                    //var_dump($contact_contributor);
                    $objtask->add_contact($contact_contributor, "TASKCONTRIBUTOR");
                }

                return $ret;
            } else {
                $this->db->rollback();
                return $this->error;  
            }
    }

    function update_poste($user, $row, $notrigger = 0) 
    {
            global $langs, $conf;


            //var_dump($row);
            //exit();

            $objtask=new TaskABCVC($this->db);

            $error=0;

            $this->db->begin();

            if($row['start_date'] != ''){
                $tmp_date0 = explode(' ',$row['start_date']);
                $tmp_date = explode('/',$tmp_date0[0]);
                $ok_start_date = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' '.$tmp_date0[1];
            } else {
                $ok_start_date = '';
            } 
            if($row['end_date'] != ''){
                $tmp_date0 = explode(' ',$row['end_date']);
                $tmp_date = explode('/',$tmp_date0[0]);
                $ok_end_date = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' '.$tmp_date0[1];
            } else {
                $ok_end_date = '';
            }
            $factfourn_todelete = implode(",",$row['factfourn_todelete']); 

            // var_dump("FACTURILE ELIMINATE".$factfourn_todelete);    
            // exit;
            
            $poste_factfourn = implode(",",$row['factfourn']);            
            $planned_workload_h = $row["planned_work_h"] / 0.000277777778; 

            $sql = "UPDATE " . MAIN_DB_PREFIX . "abcvc_projet_task SET";
            $sql.= " ref = '" . $this->db->escape($row["code_poste"]) . "'";
            $sql.= ", label = '" . $this->db->escape($row["label"]) . "'";
            $sql.= ", fk_zone = 0"; //  '" . $this->db->escape($row["zone"]) . "'";
            $sql.= ", cost = '" . $this->db->escape($row["poste_price"]) . "'";
            $sql.= ", tx_tva = " . ($this->db->escape($row["tx_tva"]) != '' ? "'" .$this->db->escape($row["tx_tva"]) . "'" : '20');
            $sql.= ", progress_estimated = '" . $this->db->escape($row["progress_estimated"]) . "'";
            $sql.= ", fk_projet = '" . $this->db->escape($row["id_projet"]) . "'";
            $sql.= ", description = '" . $this->db->escape($row["description"]) . "'";
            $sql.= ", fk_categorie = '" . $this->db->escape($row["id_category"]) . "'";
            $sql.= ", dateo = " . ( ($ok_start_date!='')?"'".$this->db->escape($ok_start_date)."'":'NULL');
            $sql.= ", datee = " . ( ($ok_end_date!='')?"'".$this->db->escape($ok_end_date)."'":'NULL');  
            //$sql.= ", dateo = '" . $this->db->escape($ok_start_date) . "'";
            //$sql.= ", datee = '" . $this->db->escape($ok_end_date) . "'";
            $sql.= ", planned_workload = '" . $this->db->escape($planned_workload_h) . "'";
            $sql.= ", progress = '" . $this->db->escape($row["declared_progress"]) . "'";
            $sql.= ", fact_fourn = '". $this->db->escape($poste_factfourn) . "'";
            $sql.= ", poste_pv = ". ( ($row["poste_pv"]!='')?$this->db->escape($row["poste_pv"]):'\'0\''); //             $this->db->escape($row["poste_pv"]). "'";
            $sql.= " WHERE rowid = " . $row["id_poste"];


            //WHEN WE DESELECT A fact_fourn IN POSTE WE WANT TO DESELECT THAT fact_Fourn_ids FROM llx_facture_fourn
            if($factfourn_todelete != '' ){
                $sql_fft = "UPDATE " . MAIN_DB_PREFIX . "facture_fourn SET";
                $sql_fft.= " fk_projet = null";
                $sql_fft.= " WHERE rowid IN (".$factfourn_todelete.")"; 
                // var_dump("a intrat in delete?");
                dol_syslog(get_class($this)."::update", LOG_DEBUG);
                $resql=$this->db->query($sql_fft);
                if ($resql)
                {
                    if (!$notrigger)
                    {
                        // Call trigger
                        $result=$this->call_trigger('PROJECT_OFFER_MODIFY',$user);
                        if ($result < 0) { $error++; }
                        // End call triggers
                    }
                    if (! $error )
                    {
                        $this->db->commit();
                    }else
                    {
                        $this->db->rollback();
                        $result = -1;
                    }
                }else{
                    
                    $this->error = $this->db->lasterror();
                    $this->errors[] = $this->error;
                    $this->db->rollback();
                    if ($this->db->lasterrno() == 'DB_ERROR_RECORD_ALREADY_EXISTS')
                    {
                        $result = -4;
                    }
                    else
                    {
                        $result = -2;
                    }
                    dol_syslog(get_class($this)."::update error " . $result . " " . $this->error, LOG_ERR);
                }
            }

            //IF WE ADD A NEW FAC FOURN TO POSTE WE UPDATE IT IN DB 
            if($poste_factfourn != '' ){
                $sql_pff = "UPDATE " . MAIN_DB_PREFIX . "facture_fourn SET";
                $sql_pff.= " fk_projet = ".$row["id_projet"]."";
                $sql_pff.= " WHERE rowid IN (".$poste_factfourn.")"; 
                // var_dump($sql_pff);
                // exit;
                dol_syslog(get_class($this)."::update", LOG_DEBUG);
                $resql=$this->db->query($sql_pff);
                if ($resql)
                {
                    if (!$notrigger)
                    {
                        // Call trigger
                        $result=$this->call_trigger('PROJECT_OFFER_MODIFY',$user);
                        if ($result < 0) { $error++; }
                        // End call triggers
                    }
                    if (! $error )
                    {
                        $this->db->commit();
                    }else
                    {
                        $this->db->rollback();
                        $result = -1;
                    }
                }else{

                    $this->error = $this->db->lasterror();
                    $this->errors[] = $this->error;
                    $this->db->rollback();
                    if ($this->db->lasterrno() == 'DB_ERROR_RECORD_ALREADY_EXISTS')
                    {
                        $result = -4;
                    }
                    else
                    {
                        $result = -2;
                    }
                    dol_syslog(get_class($this)."::update error " . $result . " " . $this->error, LOG_ERR);
                }
            }
            // var_dump($sql_fft);
            // var_dump($sql_pff);
            // exit;
            //var_dump($sql_ff);
            //var_dump($poste_factfourn);
            //exit();
            //$objtask->add_contact($contacts_executive, "TASKEXECUTIVE");
            //var_dump($row['contacts_contributor']);   
            //$objtask->add_contact($contacts_contributor, "TASKEXECUTIVE");
            
            dol_syslog(get_class($this)."::update", LOG_DEBUG);
            $resql=$this->db->query($sql);
            if ($resql)
            {
                if (!$notrigger)
                {
                    // Call trigger
                    $result=$this->call_trigger('PROJECT_OFFER_MODIFY',$user);
                    if ($result < 0) { $error++; }
                    // End call triggers
                }
                if (! $error )
                {
                    $this->db->commit();


                    //INSERT NEW CONTACTS TO DB ( If the contacts which exist are skiped and add new contacts )
                    $objtask->id = $row["id_poste"];
                    foreach ($row['contacts_executive'] as $execut) {
                        //var_dump($execut);
                        $objtask->add_contact($execut,"TASKEXECUTIVE");
                    }

                    foreach ($row['contacts_contributor'] as $contrib) {
                        //var_dump($contrib);
                        $objtask->add_contact($contrib,"TASKCONTRIBUTOR");
                    }

                    foreach ($row['contacts_executive_todelete'] as $delete) {
                        //var_dump("ID-ul executive pentru delete",$delete);
                        $objtask->delete_abcvc_contact($delete,$row["id_poste"],196);
                        # code...
                    }

                    foreach ($row['contacts_contributor_todelete'] as $delete) {
                        //var_dump("ID-ul contributor pentru delete",$delete);
                        $objtask->delete_abcvc_contact($delete,$row["id_poste"],197);
                        # code...
                    }

                    $result = 1;
                }
                else
                {
                    $this->db->rollback();
                    $result = -1;
                }
            }
            else
            {
                $this->error = $this->db->lasterror();
                $this->errors[] = $this->error;
                $this->db->rollback();
                if ($this->db->lasterrno() == 'DB_ERROR_RECORD_ALREADY_EXISTS')
                {
                    $result = -4;
                }
                else
                {
                    $result = -2;
                }
                dol_syslog(get_class($this)."::update error " . $result . " " . $this->error, LOG_ERR);
            }


            return $result;
    }

    /**
     * delete poste
     *
     * @param  User     $user       User object of making update
     * @param  int      $notrigger  1=Disable all triggers
     * @return int                  <=0 if KO, >0 if OK
     */
    function delete_poste($user, $row, $notrigger=0)
    {

        global $langs, $conf;

        $error=0;
        /*  
            'id_projet'=>$id_projet,
            'id_poste'=>$id_poste
        */
        //childs ?
        //------------------
        $sql = "SELECT 
        t.rowid as id_poste,
        ( select GROUP_CONCAT(rowid) from llx_abcvc_projet_task where fk_task_parent = t.rowid) as ids_subposte,
        ( select GROUP_CONCAT(rowid) from llx_abcvc_projet_task where FIND_IN_SET(fk_task_parent, ids_subposte) ) as ids_subsubposte
        FROM llx_abcvc_projet_task as t
        WHERE t.rowid = ".$row['id_poste'];
        $resql=$this->db->query($sql);
        $nb_childs = $this->db->num_rows($resql);
        $ids_task_childs = array();
        for ($i=0; $i < $nb_childs ; $i++) { 
            $childs = $this->db->fetch_object($resql);
            //var_dump($childs);
            $ids_task_childs[]=$childs->id_poste;
            if(!is_null($childs->ids_subposte)){
                $ar_ids = explode(',',$childs->ids_subposte);
                $ids_task_childs = array_merge($ids_task_childs,$ar_ids);
            }
            if(!is_null($childs->ids_subsubposte)){
                $ar_ids = explode(',',$childs->ids_subsubposte);
                $ids_task_childs = array_merge($ids_task_childs,$ar_ids);
            }            
        }
        // var_dump($ids_task_childs);
        // exit();
        if(count($ids_task_childs)>0){
          $return =  $this->delete_tasks($ids_task_childs);
        }
        
        if($return){
            return 1;
        } else {
            return $this->db->lasterror();
        }
       
    }


    //**************************************************************************************************************
    //
    //
    // SUBPOSTES
    // 
    // 
    //**************************************************************************************************************
    //
    function create_subposte($user, $row, $notrigger=0)
    {
        global $conf, $langs;
        //var_dump($row);
        //exit();
        
        $error = 0;
        $ret = 0;
        $objtask=new TaskABCVC($this->db);
        $now=dol_now();

        $this->db->begin();


        if($row['start_date'] != ''){
            $tmp_date0 = explode(' ',$row['start_date']);
            $tmp_date = explode('/',$tmp_date0[0]);
            $ok_start_date = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' '.$tmp_date0[1];
        } else {
            $ok_start_date = '';
        }    
        if($row['end_date'] != ''){
            $tmp_date0 = explode(' ',$row['end_date']);
            $tmp_date = explode('/', $tmp_date0[0]);
            $ok_end_date = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' '.$tmp_date0[1];
        } else {
            $ok_end_date = '';
        } 

        if($row['declared_progress']=='') $row['declared_progress'] = 0;
        if($row['estimated_progress']=='') $row['estimated_progress'] = 0;
        if($row['price']=='') $row['price'] = 0;


        $subposte_factfourn = implode(",",$row['add_factfourn']);            

        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "abcvc_projet_task (";
        $sql.= "ref";
        $sql.= ", entity";
        $sql.= ", fk_projet";
        $sql.= ", fk_task_parent";
        $sql.= ", datec";        
        $sql.= ", dateo";
        $sql.= ", datee";
        $sql.= ", label";
        $sql.= ", description";
        $sql.= ", progress";        
        $sql.= ", planned_workload";
        $sql.= ", fk_user_creat";
        $sql.= ", fk_statut";
        $sql.= ", cost";
        $sql.= ", progress_estimated";
        $sql.= ", fk_zone";
        $sql.= ", fact_fourn";
        $sql.= ", quantite";
        $sql.= ", unite";

        $sql.= ") VALUES (";

        $sql.= " '" . $this->db->escape($row['code']) . "'";
        $sql.= ", ".$conf->entity;
        $sql.= ", '" . $this->db->escape($row['id_projet']) . "'";
        $sql.= ", '" . $this->db->escape($row['child']) . "'";
        $sql.= ", '".$this->db->idate($now)."'";        

        $sql.= ", " . ( ($ok_start_date!='')?"'".$this->db->escape($ok_start_date)."'":'NULL');
        $sql.= ", " . ( ($ok_end_date!='')?"'".$this->db->escape($ok_end_date)."'":'NULL');  

        $sql.= ", '" . $this->db->escape($row['label']) . "'";
        $sql.= ", '" . $this->db->escape($row['description']) . "'";
        $sql.= ", '" . $this->db->escape($row['declared_progress']) . "'";        
        $sql.= ", '" . $this->db->escape($row['planned_workload']) . "'";
        $sql.= ", " . $user->id;
        $sql.= ", 1";
        $sql.= ", '" . $this->db->escape($row['price']) . "'";
        $sql.= ", '" . $this->db->escape($row['estimated_progress']) . "'";
        $sql.= ", '" . $this->db->escape($row['id_zone']) . "'";
        $sql.=", '" . $this->db->escape($subposte_factfourn) . "'";
        $sql.= ", '" . $this->db->escape($row['sousposte_add_unite']) . "'";
        $sql.= ", '" . $this->db->escape($row['sousposte_select_unite']) . "'";
        $sql.= ")";

        //var_dump($sql);
        //exit();

        dol_syslog(get_class($this)."::create", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql) {
            $objtask->id = $this->db->last_insert_id(MAIN_DB_PREFIX . "abcvc_projet_task");
            $ret = $objtask->id;
            //$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX . "abcvc_projet_task");
            //$ret = $this->id;

            if (!$notrigger) {
                // Call trigger
                $result=$this->call_trigger('PROJECT_CATEGORY_CREATE',$user);
                if ($result < 0) { $error++; }
                // End call triggers
            }
        } else {
            $this->error = $this->db->lasterror();
            $this->errno = $this->db->lasterrno();
            $error++;
        }

        if (!$error) {
            $this->db->commit();

            //INSERT CONTACT IN DATABASE
            //$objtask->add_contact($fk_socpeople, $type_contact)
            /*$row["executive"] = explode(",",$row["executive"]);
            $row["contributor"] = explode(",",$row["contributor"]);
            foreach ($row["executive"] as $contact_executive) {
                //var_dump($contact_executive);
                $objtask->add_contact($contact_executive, "TASKEXECUTIVE");
            }
            foreach ($row["contributor"] as $contact_contributor) {
                //var_dump($contact_contributor);
                $objtask->add_contact($contact_contributor, "TASKCONTRIBUTOR");
            }*/

            return $ret;
            //exit();
        } else {
            $this->db->rollback();
            return $this->error;
        }
    }

    function update_subposte($user, $row, $notrigger=0)
    {
        global $langs, $conf;
        $objtask=new TaskABCVC($this->db);
        $error=0;

        if($row['start_date'] != ''){
            $tmp_date0 = explode(' ',$row['start_date']);
            $tmp_date = explode('/',$tmp_date0[0]);
            $ok_start_date = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' '.$tmp_date0[1];
        } else {
            $ok_start_date = '';
        } 
        if($row['end_date'] != ''){
            $tmp_date0 = explode(' ',$row['end_date']);
            $tmp_date = explode('/',$tmp_date0[0]);
            $ok_end_date = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' '.$tmp_date0[1];
        } else {
            $ok_end_date = '';
        }

        $subposte_factfourn = implode(",",$row['factfourn']);            

        $this->db->begin();

            $sql = "UPDATE " . MAIN_DB_PREFIX . "abcvc_projet_task SET";

            $sql.= " ref = '" . $this->db->escape($row["code"]) . "'";
            $sql.= ", fk_task_parent = '" . $this->db->escape($row["child"]) . "'";

            $sql.= ", dateo = " . ( ($ok_start_date!='')?"'".$this->db->escape($ok_start_date)."'":'NULL');
            $sql.= ", datee = " . ( ($ok_end_date!='')?"'".$this->db->escape($ok_end_date)."'":'NULL');  

            $sql.= ", label = '" . $this->db->escape($row["label"]) . "'";
            $sql.= ", description = '" . $this->db->escape($row["description"]) . "'";
            $sql.= ", progress = '" . $this->db->escape($row["declared_progress"]) . "'";
            $sql.= ", planned_workload = '" . $this->db->escape($row["planned_workload"]) . "'";
            $sql.= ", progress_estimated = '" . $this->db->escape($row["progress_estimated"]) . "'";
            $sql.= ", cost = '" . $this->db->escape($row["subposte_price"]) . "'";
            $sql.= ", fact_fourn = '". $this->db->escape($subposte_factfourn) . "'";
            $sql.= ", poste_pv = ". ( ($row["poste_pv"]!='')?$this->db->escape($row["poste_pv"]):'\'0\''); //             $this->db->escape($row["poste_pv"]). "'";      
            $sql.= ", unite = '" . $this->db->escape($row["sousposte_edit_select_unite"]) . "'";
            $sql.= ", quantite = '" . $this->db->escape($row["sousposte_edit_unite"]) . "'";
            $sql.= " WHERE rowid = " . $row["rowid"];

            // var_dump($sql);
            // exit();


        dol_syslog(get_class($this)."::update", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if (!$notrigger)
            {
                // Call trigger
                $result=$this->call_trigger('PROJECT_OFFER_MODIFY',$user);
                if ($result < 0) { $error++; }
                // End call triggers
            }
            if (! $error )
            {
                $this->db->commit();

                //INSERT NEW CONTACTS TO DB ( If the contacts which exist already is skiped and add new contacts )
                $objtask->id = $row["rowid"];
                foreach ($row['contacts_executive'] as $execut) {
                    //var_dump($execut);
                    $objtask->add_contact($execut,"TASKEXECUTIVE");
                }

                foreach ($row['contacts_contributor'] as $contrib) {
                    //var_dump($contrib);
                    $objtask->add_contact($contrib,"TASKCONTRIBUTOR");
                }

                foreach ($row['contacts_executive_todelete'] as $delete) {
                    //var_dump("ID-ul executive pentru delete",$delete);
                    $objtask->delete_abcvc_contact($delete,$row["rowid"],196);
                    # code...
                }

                foreach ($row['contacts_contributor_todelete'] as $delete) {
                    //var_dump("ID-ul contributor pentru delete",$delete);
                    $objtask->delete_abcvc_contact($delete,$row["rowid"],197);
                    # code...
                }

                $result = 1;
            }
            else
          {
                $this->db->rollback();
                $result = -1;
            }
        }
        else
        {
            $this->error = $this->db->lasterror();
            $this->errors[] = $this->error;
            $this->db->rollback();
            if ($this->db->lasterrno() == 'DB_ERROR_RECORD_ALREADY_EXISTS')
            {
                $result = -4;
            }
            else
            {
                $result = -2;
            }
            dol_syslog(get_class($this)."::update error " . $result . " " . $this->error, LOG_ERR);
        }


        return $result;
    }


    /**
     * delete subposte
     *
     * @param  User     $user       User object of making update
     * @param  int      $notrigger  1=Disable all triggers
     * @return int                  <=0 if KO, >0 if OK
     */
    function delete_subposte($user, $row, $notrigger=0)
    {

        global $langs, $conf;

        $error=0;
        /*  
            'id_projet'=>$id_projet,
            'id_subposte'=>$id_subposte
        */
        //childs ?
        //------------------
        $sql = "SELECT 
        t.rowid as id_subposte,
        ( select GROUP_CONCAT(rowid) from llx_abcvc_projet_task where fk_task_parent = t.rowid) as ids_subsubposte
        FROM llx_abcvc_projet_task as t
        WHERE t.rowid = ".$row['id_subposte'];

        $resql=$this->db->query($sql);
        $nb_childs = $this->db->num_rows($resql);
        $ids_task_childs = array();
        for ($i=0; $i < $nb_childs ; $i++) { 
            $childs = $this->db->fetch_object($resql);
            //var_dump($childs);
            $ids_task_childs[]=$childs->id_subposte;
            if(!is_null($childs->ids_subsubposte)){
                $ar_ids = explode(',',$childs->ids_subsubposte);
                $ids_task_childs = array_merge($ids_task_childs,$ar_ids);
            }            
        }
        // var_dump($ids_task_childs);
        // exit();
        if(count($ids_task_childs)>0){
          $return =  $this->delete_tasks($ids_task_childs);
        }               

        if($return){
            return 1;
        } else {
            return $this->db->lasterror();
        }
       
    }

    //**************************************************************************************************************
    //
    //
    // SUBSUBPOSTES
    // 
    // 
    //**************************************************************************************************************
    //
    function create_subsubposte($user, $row, $notrigger=0)
    {
        global $conf, $langs;

        $error = 0;
        $ret = 0;
        $objtask=new TaskABCVC($this->db);
        $now=dol_now();

        $this->db->begin();
        // var_dump($row);
        // exit();

        if($row['start_date'] != ''){
            $tmp_date0 = explode(' ',$row['start_date']);
            $tmp_date = explode('/',$tmp_date0[0]);
            $ok_start_date = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' '.$tmp_date0[1];
        } else {
            $ok_start_date = '';
        }    
        if($row['end_date'] != ''){
            $tmp_date0 = explode(' ',$row['end_date']);
            $tmp_date = explode('/', $tmp_date0[0]);
            $ok_end_date = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' '.$tmp_date0[1];
        } else {
            $ok_end_date = '';
        } 

        $subsubposte_factfourn = implode(",",$row['add_factfourn']);            


        if($row['declared_progress']=='') $row['declared_progress'] = 0;
        if($row['estimated_progress']=='') $row['estimated_progress'] = 0;
        if($row['price']=='') $row['price'] = 0;

        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "abcvc_projet_task (";
        $sql.= "ref";
        $sql.= ", entity";
        $sql.= ", fk_projet";
        $sql.= ", fk_task_parent";
        $sql.= ", datec";        
        $sql.= ", dateo";
        $sql.= ", datee";
        $sql.= ", label";
        $sql.= ", description";
        $sql.= ", progress";        
        $sql.= ", planned_workload";
        $sql.= ", fk_user_creat";
        $sql.= ", cost";
        $sql.= ", progress_estimated";
        $sql.= ", fk_zone";
        $sql.= ", fk_statut";
        $sql.= ", fact_fourn";
        $sql.= ", quantite";
        $sql.= ", unite";


        $sql.= ") VALUES (";
        $sql.= " '" . $this->db->escape($row['code']) . "'";
        $sql.= ", ".$conf->entity;
        $sql.= ", '" . $this->db->escape($row['id_projet']) . "'";
        $sql.= ", '" . $this->db->escape($row['child']) . "'";
        $sql.= ", '".$this->db->idate($now)."'";        

        $sql.= ", " . ( ($ok_start_date!='')?"'".$this->db->escape($ok_start_date)."'":'NULL');
        $sql.= ", " . ( ($ok_end_date!='')?"'".$this->db->escape($ok_end_date)."'":'NULL');  

        $sql.= ", '" . $this->db->escape($row['label']) . "'";
        $sql.= ", '" . $this->db->escape($row['description']) . "'";
        $sql.= ", '" . $this->db->escape($row['declared_progress']) . "'";        
        $sql.= ", '" . $this->db->escape($row['planned_workload']) . "'";
        $sql.= ",  " . $user->id;
        $sql.= ", '" . $this->db->escape($row['price']) . "'";
        $sql.= ", '" . $this->db->escape($row['estimated_progress']) . "'";
        $sql.= ", '" . $this->db->escape($row['id_zone']) . "'";
        $sql.= ", 1";
        $sql.= ", '" . $this->db->escape($subsubposte_factfourn) . "'";
        $sql.= ", '" . $this->db->escape($row['soussousposte_add_unite']) . "'";
        $sql.= ", '" . $this->db->escape($row['soussousposte_select_unite']) . "'";


        $sql.= ")";

        //var_dump($sql);
        //exit();

        dol_syslog(get_class($this)."::create", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql) {
            $objtask->id = $this->db->last_insert_id(MAIN_DB_PREFIX . "abcvc_projet_task");
            $ret = $objtask->id;
            //$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX . "abcvc_projet_task");
            //$ret = $this->id;

            if (!$notrigger) {
                // Call trigger
                $result=$this->call_trigger('PROJECT_CATEGORY_CREATE',$user);
                if ($result < 0) { $error++; }
                // End call triggers
            }
        } else {
            $this->error = $this->db->lasterror();
            $this->errno = $this->db->lasterrno();
            $error++;
        }

        if (!$error) {
            $this->db->commit();

            return $ret;
        } else {
            $this->db->rollback();
            return $this->error;
        }
    }

    function update_subsubposte($user, $row, $notrigger=0)
    {


        global $langs, $conf;
        $objtask=new TaskABCVC($this->db);
        $error=0;

        if($row['start_date'] != ''){
            $tmp_date0 = explode(' ',$row['start_date']);
            $tmp_date = explode('/',$tmp_date0[0]);
            $ok_start_date = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' '.$tmp_date0[1];
        } else {
            $ok_start_date = '';
        } 
        if($row['end_date'] != ''){
            $tmp_date0 = explode(' ',$row['end_date']);
            $tmp_date = explode('/',$tmp_date0[0]);
            $ok_end_date = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0].' '.$tmp_date0[1];
        } else {
            $ok_end_date = '';
        }


        $subsubposte_factfourn = implode(",",$row['factfourn']);            

        $this->db->begin();

            $sql = "UPDATE " . MAIN_DB_PREFIX . "abcvc_projet_task SET";
            $sql.= " fk_task_parent = '" . $this->db->escape($row["child"]) . "'";

            $sql.= ", dateo = " . ( ($ok_start_date!='')?"'".$this->db->escape($ok_start_date)."'":'NULL');
            $sql.= ", datee = " . ( ($ok_end_date!='')?"'".$this->db->escape($ok_end_date)."'":'NULL');  

            $sql.= ", label = '" . $this->db->escape($row["label"]) . "'";
            $sql.= ", description = '" . $this->db->escape($row["description"]) . "'";
            $sql.= ", progress = '" . $this->db->escape($row["declared_progress"]) . "'";
            $sql.= ", planned_workload = '" . $this->db->escape($row["planned_workload"]) . "'";
            $sql.= ", cost = '" . $this->db->escape($row["subsubposte_price"]) . "'";
            $sql.= ", progress_estimated = '" . $this->db->escape($row["progress_estimated"]) . "'";
            $sql.= ", unite = '" . $this->db->escape($row["soussousposte_edit_select_unite"]) . "'";
            $sql.= ", quantite = '" . $this->db->escape($row["soussousposte_edit_unite"]) . "'";
            $sql.= ", fact_fourn = '". $this->db->escape($subsubposte_factfourn) . "'";
            $sql.= ", poste_pv = ". ( ($row["poste_pv"]!='')?$this->db->escape($row["poste_pv"]):'\'0\''); //             $this->db->escape($row["poste_pv"]). "'";            
            $sql.= " WHERE rowid = " . $row["rowid"];

            // var_dump($sql);
            // exit();


        dol_syslog(get_class($this)."::update", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if (!$notrigger)
            {
                // Call trigger
                $result=$this->call_trigger('PROJECT_OFFER_MODIFY',$user);
                if ($result < 0) { $error++; }
                // End call triggers
            }
            if (! $error )
            {
                $this->db->commit();
                //INSERT NEW CONTACTS TO DB 
                $objtask->id = $row["rowid"];
                foreach ($row['contacts_executive'] as $execut) {
                    //var_dump($execut);
                    $objtask->add_contact($execut,"TASKEXECUTIVE");
                }
                foreach ($row['contacts_contributor'] as $contrib) {
                    //var_dump($contrib);
                    $objtask->add_contact($contrib,"TASKCONTRIBUTOR");
                }

                //DELETE CONTACTS FROM DB 
                foreach ($row['contacts_executive_todelete'] as $delete) {
                    //var_dump("ID-ul executive pentru delete",$delete);
                    $objtask->delete_abcvc_contact($delete,$row["rowid"],196);
                    # code...
                }
                foreach ($row['contacts_contributor_todelete'] as $delete) {
                    //var_dump("ID-ul contributor pentru delete",$delete);
                    $objtask->delete_abcvc_contact($delete,$row["rowid"],197);
                    # code...
                }

                $result = 1;
            }
            else
          {
                $this->db->rollback();
                $result = -1;
            }
        }
        else
        {
            $this->error = $this->db->lasterror();
            $this->errors[] = $this->error;
            $this->db->rollback();
            if ($this->db->lasterrno() == 'DB_ERROR_RECORD_ALREADY_EXISTS')
            {
                $result = -4;
            }
            else
            {
                $result = -2;
            }
            dol_syslog(get_class($this)."::update error " . $result . " " . $this->error, LOG_ERR);
        }

        return $result;
    }

    /**
     * delete subsubposte
     *
     * @param  User     $user       User object of making update
     * @param  int      $notrigger  1=Disable all triggers
     * @return int                  <=0 if KO, >0 if OK
     */
    function delete_subsubposte($user, $row, $notrigger=0)
    {

        global $langs, $conf;

        $error=0;
        /*  
            'id_projet'=>$id_projet,
            'id_subsubposte'=>$id_subsubposte
        */
        $sql = "
        DELETE FROM llx_abcvc_projet_task 
        WHERE rowid = ".$row['id_subsubposte'];
        $resql=$this->db->query($sql);  

        if($resql){
            return 1;
        } else {
            return $this->db->lasterror();
        }
    }


    //delete tasks
    //-----------------------------------------
    function delete_tasks($ids_tasks){
        //delete tasks
        $sql = "
        DELETE FROM llx_abcvc_projet_task 
        WHERE rowid IN(".implode(',',$ids_tasks).")";            
        $resql=$this->db->query($sql);


        //TODO delete related elements ?

        return true; 
    }









    //**************************************************************************************************************
    //
    //
    // STRUCTURES PROJET
    // 
    // 
    //**************************************************************************************************************


    //project structure tree
    public function getProjectTree($id_project, $user, $full = true) {

        global $conf, $langs;
        
        $error = 0;
        $ret = 0;
        $objtask=new TaskABCVC($this->db);
        $now=dol_now();

        if (empty($id_project) ) return -1;

        //lots
        $sql = "
        SELECT pl.rowid, pl.ref, pl.fk_projet, pl.label, pl.description, pl.datec, pl.ordering, pl.fk_user_creat, pl.fk_statut
        FROM " . MAIN_DB_PREFIX . "abcvc_projet_lots as pl
        WHERE pl.fk_projet=".$id_project." 
        ORDER BY pl.ordering ASC";

        dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
        $resql = $this->db->query($sql);
        
        $projectLots = array();
        $nb_lots = 0;
        $nb_categories = 0;
        $nb_postes = 0;
        $nb_subpostes = 0;
        $nb_subsubpostes = 0;
        $nb_contacts = 0;

        if ($resql) {
            $nb_lots = $this->db->num_rows($resql);
            if ($nb_lots) {

                $i = 0;
                while ($i < $nb_lots)  { 
                    $obj = $this->db->fetch_object($resql);
                    //$obj->cost_lot = 0;
                    $projectLots[]=$obj;
                    $i++;
                }    

                $this->db->free($resql);
            } 
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }

        //CATEGORIES
        //----------------------------------------------------------------------------------
        foreach ($projectLots as $key => $lot) {

            $lot->categories = array();
            
            $lot->pv_lot = 0;
            $lot->marge = 0;

            //categories
            $sql = "
            SELECT pc.rowid, pc.ref, pc.fk_projet, pc.fk_lot, pc.label, pc.description, pc.datec, pc.ordering, pc.fk_user_creat, pc.fk_statut
            FROM " . MAIN_DB_PREFIX . "abcvc_projet_categories as pc
            WHERE 
                pc.fk_projet=".$id_project."
                AND pc.fk_lot=".$lot->rowid."
            ORDER BY pc.ordering ASC";

            $resql = $this->db->query($sql);
            
            if ($resql) {
                $nb_cat = $this->db->num_rows($resql);

                $lot->nb_child = $nb_cat;

                $nb_categories += $nb_cat;
                if ($nb_cat) {

                    $i = 0;
                    while ($i < $nb_cat)  { 

                        $obj = $this->db->fetch_object($resql);
                        $obj->cost_categorie = 0;
                        $lot->categories[]=$obj;
                        $i++;
                    }

                    $this->db->free($resql);
                } 
            } else {
                $this->error = $this->db->lasterror();
                return -1;
            }

            //POSTES
            //----------------------------------------------------------------------------------
            foreach ($lot->categories as $key => $categorie) {

                $categorie->postes = array();
                
                $categorie_cost = 0;
                $categorie_cost_calculated = 0;
                $categorie_marge = 0;
                $categorie_pv = 0;
                $categorie_marge = 0;

                $sql = "
                SELECT pc.rowid, pc.ref, pc.fk_projet, pc.fk_categorie, pc.fk_task_parent, pc.label, pc.description, pc.datec, pc.fk_user_creat, pc.fk_statut, pc.dateo, pc.datee, pc.planned_workload, pc.progress, pc.fk_zone, pc.cost, pc.progress_estimated, pc.fact_fourn, pc.poste_pv, pc.tx_tva
                FROM " . MAIN_DB_PREFIX . "abcvc_projet_task as pc
                WHERE 
                    pc.fk_projet=".$id_project."
                    AND pc.fk_categorie=".$categorie->rowid."
                ";
                //$sql.= " ORDER BY pc.ordering ASC";
                //var_dump($sql);
                //exit();
                $resql = $this->db->query($sql);
                
                if ($resql) {
                    $nb_post = $this->db->num_rows($resql);

                    $categorie->nb_child = $nb_post;

                    $nb_postes += $nb_post;
                    if ($nb_post) {

                        $i = 0;
                        while ($i < $nb_post)  { 
                            $obj = $this->db->fetch_object($resql);
                                
                                //add CONTACT TO POSTE 
                                $objtask->id = $obj->rowid;

                                if( $full ){
                                    $contacts = $objtask->liste_contact(4,'internal',1,'TASKEXECUTIVE');
                                }else{
                                    $contacts = $objtask->liste_contact(4,'internal',0,'TASKEXECUTIVE');
                                }
                                $obj->contacts_executive = $contacts;     

                                if( $full ){
                                    $contacts = $objtask->liste_contact(4,'internal',1,'TASKCONTRIBUTOR');
                                }else{
                                    $contacts = $objtask->liste_contact(4,'internal',0,'TASKCONTRIBUTOR');
                                }
                                $obj->contacts_contributor = $contacts; 

                                //arrays of id contact
                                $ids_contact = array_merge($obj->contacts_executive,$obj->contacts_contributor);
                                
                                //manuel estime
                                $obj->cost = round($obj->cost,2);

                                //return COST_MO
                                $obj->cost_mo = round($objtask->getCostByUser($ids_contact,$obj->planned_workload),2);
                                // var_dump("Array of contacts",$ids_contact);
                                // var_dump("Timpul in secunde",$obj->planned_workload * 0.00027777777777778);
                                // var_dump("COSTUL FINAL",$obj->costs_mo);

                                //return COST_MO_CALCULATED
                                //$obj->cost_mo_calculated = round($objtask->getCostByUserByTimespent($ids_contact,$obj->planned_workload),2);
                                /*array (size=3)
                                  'amount' => string '0' (length=1)
                                  'nbseconds' => string '14400' (length=5)
                                */
                                if(!$full){
                                    $ids_contact = array();
                                }
                                $timespent_task = $objtask->getSumOfAmount($ids_contact);

                                /*if($obj->rowid == 46){
                                    var_dump($timespent_task);
                                    exit();
                                }*/
                                $obj->calculated_workload = (int)$timespent_task['nbseconds'];
                                $obj->cost_mo_calculated = round($timespent_task['amount'],2);

                                if($obj->planned_workload>0){
                                    $obj->progress_estimated = round($obj->calculated_workload * 100 / $obj->planned_workload,2);
                                } else {
                                    if($obj->calculated_workload>0){
                                        $obj->progress_estimated = 101;
                                    } else {
                                        $obj->progress_estimated = 0;                                        
                                    }
                                }

                                //return COST_FOURN
                                $obj->cost_fourn = round($objtask->getCostByTask($obj),2);

                                //INSERT FINAL COST hmmmm zarbi mais client roi => il faut faire : prix de vente-(couts estimes-charges calculées) 
                                //OOOKAYYY fff on a une regle "realiste" !
                                //si pas de cout calcule -> marge = poste_pv - cost
                                //si cout calcule -> marge = poste_pv - (cost_mo + cost_fourn)
                                //---------------------------------------------------
                                if( ($obj->cost_mo_calculated>0) || ($obj->cost_fourn>0)  ){
                                    $cost_mo_final = $obj->cost_mo_calculated; //($obj->cost_mo_calculated>0?$obj->cost_mo_calculated:$obj->cost_mo);
                                    $obj->cost_final = ( $cost_mo_final + $obj->cost_fourn );
                                } else {
                                    $cost_mo_final=0;//$obj->cost_mo;
                                    $obj->cost_final = 0;$obj->cost;
                                }

                                $obj->poste_pv = round($obj->poste_pv,2);
                                
                                //SUM FOR CATEGORIE
                                //---------------------------
                                $categorie_cost_calculated += $cost_mo_final + $obj->cost_fourn;
                                $categorie_marge += $obj->poste_pv - $obj->cost_final;
                                $categorie_cost += $obj->cost;    
                                $categorie_pv += $obj->poste_pv; 

                            $categorie->postes[]=$obj;
                            $i++;
                        }

                        $this->db->free($resql);
                    } 
                } else {
                    $this->error = $this->db->lasterror();
                    return -1;
                }

                //SubPOSTES
                //----------------------------------------------------------------------------------
                foreach ($categorie->postes as $key => $poste) {

                    $poste->subpostes = array();


                    $sql = "
                    SELECT pc.rowid, pc.ref, pc.fk_projet, pc.fk_categorie, pc.fk_task_parent, pc.label, pc.description, pc.datec, pc.fk_user_creat, pc.fk_statut, pc.dateo, pc.datee, pc.planned_workload, pc.progress, pc.cost, pc.fk_zone, pc.progress_estimated, pc.fact_fourn, pc.poste_pv, pc.unite, pc.quantite, pc.tx_tva

                    FROM " . MAIN_DB_PREFIX . "abcvc_projet_task as pc
                    
                    WHERE 
                        pc.fk_projet=".$id_project."
                         AND pc.fk_task_parent=".$poste->rowid."
                         
                    ";

                   //$sql.= " ORDER BY pc.ordering ASC";
                   // var_dump($sql);
                    //exit();
                    //
                    //
                    $resql = $this->db->query($sql);
                    
                    if ($resql) {
                        $nb_subpost = $this->db->num_rows($resql);

                        $poste->nb_child = $nb_subpost;

                        $nb_subpostes += $nb_subpost;
                        if ($nb_subpost) {

                            $i = 0;
                            while ($i < $nb_subpost)  { 

                                $obj = $this->db->fetch_object($resql);
                                    //
                                    //INSERT CONTACT TO SUBPOSTE
                                    //                                                                                                                                       
                                    $objtask->id=$obj->rowid;
                                    if( $full ){
                                        $contacts = $objtask->liste_contact(4,'internal',1,'TASKEXECUTIVE');
                                    }else{
                                        $contacts = $objtask->liste_contact(4,'internal',0,'TASKEXECUTIVE');
                                    }
                                    $obj->contacts_executive = $contacts; 


                                    if( $full ){
                                        $contacts = $objtask->liste_contact(4,'internal',1,'TASKCONTRIBUTOR');
                                    }else{
                                        $contacts = $objtask->liste_contact(4,'internal',0,'TASKCONTRIBUTOR');
                                    }
                                    $obj->contacts_contributor = $contacts;

                                    $obj->planned_workload = $poste->planned_workload / $nb_subpost ;
                                    $obj->fact_fourn = $poste->fact_fourn;
 
                                    $obj->cost_final = round($poste->cost_final / $nb_subpost,2);
                                    $obj->cost_mo = round($poste->cost_mo / $nb_subpost,2);

                                    $obj->cost_fourn = round($poste->cost_fourn / $nb_subpost,2);
                                    $obj->cost = round($poste->cost / $nb_subpost,2);
                                    
                                    if( $obj->poste_pv ==0) {
                                        $obj->poste_pv = round($poste->poste_pv / $nb_subpost,2);
                                    } else {
                                        $obj->poste_pv = round($obj->poste_pv,2);
                                    }  

                                $poste->subpostes[]=$obj;
                                $i++;
                            }
                            
                            $this->db->free($resql);
                        } 
                    } else {
                        $this->error = $this->db->lasterror();
                        return -1;
                    }
                

                    //SubSubPOSTES
                    //----------------------------------------------------------------------------------
                    foreach ($poste->subpostes as $key => $subposte) {

                        $subposte->subsubpostes = array();


                        $sql = "
                        SELECT pc.rowid, pc.ref, pc.fk_projet, pc.fk_categorie, pc.fk_task_parent, pc.label, pc.description, pc.datec, pc.fk_user_creat, pc.fk_statut, pc.dateo, pc.datee, pc.planned_workload, pc.progress, pc.cost, pc.fk_zone, pc.progress_estimated, pc.fact_fourn, pc.poste_pv, pc.unite, pc.quantite

                        FROM " . MAIN_DB_PREFIX . "abcvc_projet_task as pc
                        
                        WHERE 
                            pc.fk_projet=".$id_project."
                             AND pc.fk_task_parent=".$subposte->rowid."
                        ";

                       //$sql.= " ORDER BY pc.ordering ASC";
                        //var_dump($sql);
                        //exit();
                        //
                        //
                        $resql = $this->db->query($sql);
                        
                        if ($resql) {
                            $nb_subsubpost = $this->db->num_rows($resql);
                            
                            $subposte->nb_child = $nb_subsubpost;

                            $nb_subsubpostes += $nb_subsubpost;
                            if ($nb_subsubpost) {

                                $i = 0;
                                while ($i < $nb_subsubpost)  { 

                                    $obj = $this->db->fetch_object($resql);
                                        //
                                        //INSERT CONTACT TO SUBSUBPOSTE
                                        //                                                                                                                                
                                        $objtask->id=$obj->rowid;

                                        if( $full ){
                                            $contacts = $objtask->liste_contact(4,'internal',1,'TASKEXECUTIVE');
                                        }else{
                                            $contacts = $objtask->liste_contact(4,'internal',0,'TASKEXECUTIVE');
                                        }
                                        $obj->contacts_executive = $contacts; 

                                        if( $full ){
                                            $contacts = $objtask->liste_contact(4,'internal',1,'TASKCONTRIBUTOR');
                                        }else{
                                            $contacts = $objtask->liste_contact(4,'internal',0,'TASKCONTRIBUTOR');
                                        }
                                        $obj->contacts_contributor = $contacts; 

                                        $obj->planned_workload = $subposte->planned_workload / $nb_subsubpost ;
                                        $obj->fact_fourn = $poste->fact_fourn;

                         
                                        $obj->cost_final = round($subposte->cost_final / $nb_subsubpost,2);
                                        $obj->cost_mo = round($subposte->cost_mo / $nb_subsubpost,2);

                                        $obj->cost_fourn = round($subposte->cost_fourn / $nb_subsubpost,2);
                                        $obj->cost = round($subposte->cost / $nb_subsubpost,2);

                                        if( $obj->poste_pv ==0) {
                                            $obj->poste_pv = round($subposte->poste_pv / $nb_subsubpost,2);
                                        } else {
                                            $obj->poste_pv = round($obj->poste_pv,2);
                                        }                                       

                                    $subposte->subsubpostes[]=$obj;
                                    $i++;
                                }

                                $this->db->free($resql);
                            } 
                        } else {
                            $this->error = $this->db->lasterror();
                            return -1;
                        }
                    }

                    
                }
                //RETURNING SUM FOR ALL TASKS (POSTE/SUBPOSTE/SUBSUBPOSTE)
                $categorie->cost = $categorie_cost;
                $categorie->cost_calculated = $categorie_cost_calculated;
                $categorie->pv_categorie = $categorie_pv;
                $categorie->marge_categorie = $categorie_marge;//round($categorie->pv_categorie - $categorie->cost_categorie,2);
                
                $lot->cost_calculated += $categorie_cost_calculated;
                $lot->cost += $categorie_cost;
                $lot->pv_lot += $categorie_pv;
                $lot->marge += $categorie_marge;
                
                //var_dump($categorie);
            }
            //$lot->marge_lot = round($lot->pv_lot - $lot->cost_lot,2);
       } 


        //$projectTree = array();
        //var_dump($projectTree);

        $stats=array(
            'lots'=> $nb_lots,
            'categories' => $nb_categories,
            'postes' => $nb_postes,
            'subpostes' => $nb_subpostes,
            'subsubpostes' => $nb_subsubpostes
        );

        $projectFullTree=array(
            'stats'=>$stats,
            'tree'=>$projectLots
        );

        return $projectFullTree;

    }


    //**************************************************************************************************************
    //
    //
    // STRUCTURES PROJETS
    // 
    // 
    //**************************************************************************************************************


    //projects structure tree
    public function getProjectsTree($user, $filterproject=1, $full = true) {

        global $conf, $langs;
        
        $error = 0;
        $ret = 0;
        $objtask=new TaskABCVC($this->db);
        $now=dol_now();

        //projects actifs
        $sql = "
        SELECT p.rowid, p.ref, p.fk_soc, s.nom, s.name_alias, s.code_client, p.fk_zones, p.datec, p.title, p.fk_statut, p.chargesfixe
        FROM " . MAIN_DB_PREFIX . "abcvc_projet as p
        LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON s.rowid = p.fk_soc";
        
        if($filterproject!='*'){
            $sql .= " WHERE p.fk_statut= ".$filterproject;
        }

        $sql .= " ORDER BY p.datec DESC";
        //var_dump($sql);
        //exit();

        $resql = $this->db->query($sql);
        
        $projects = array();
        $nb_lots = 0;
        $nb_categories = 0;
        $nb_postes = 0;
        $nb_contacts = 0;

        if ($resql) {
            $nb_projets = $this->db->num_rows($resql);
            if ($nb_projets) {
                $i = 0;
                while ($i < $nb_projets)  { 
                    $obj = $this->db->fetch_object($resql);
                    //$obj->cost_lot = 0;
                    $projects[]=$obj;
                    $i++;
                }    

                $this->db->free($resql);
            } 
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }

        //LOTS
        //----------------------------------------------------------------------------------
        foreach ($projects as $key => $project) {
            
            $project->cost_calculated = 0;
            $project->cost = 0;
            $project->pv = 0;
            $project->marge = 0;

            $project->progress = 0;
            $project->progress_estimated = 0;

            $project->nb_postes = 0;


            //lots
            $sql = "
            SELECT pl.rowid, pl.ref,pl.label, pl.datec, pl.ordering, pl.fk_user_creat, pl.fk_statut
            FROM " . MAIN_DB_PREFIX . "abcvc_projet_lots as pl
            WHERE pl.fk_projet=".$project->rowid." 
            ORDER BY pl.ordering ASC";

            $resql = $this->db->query($sql);
            
            if ($resql) {
                $nb_lots = $this->db->num_rows($resql);
                if ($nb_lots) {

                    $i = 0;
                    while ($i < $nb_lots)  { 
                        $obj = $this->db->fetch_object($resql);
                        //$obj->cost_lot = 0;
                        $project->lots[]=$obj;
                        $i++;
                    }    

                    $this->db->free($resql);
                } 
            } else {
                $this->error = $this->db->lasterror();
                return -1;
            }


            //CATEGORIES
            //----------------------------------------------------------------------------------
            foreach ($project->lots as $key => $lot) {

                $lot->categories = array();
                
                $lot->pv_lot = 0;
                $lot->marge = 0;

                //categories
                $sql = "
                SELECT pc.rowid, pc.ref, pc.fk_lot, pc.label, pc.datec, pc.ordering, pc.fk_user_creat, pc.fk_statut
                FROM " . MAIN_DB_PREFIX . "abcvc_projet_categories as pc
                WHERE 
                    pc.fk_projet=".$project->rowid."
                    AND pc.fk_lot=".$lot->rowid."
                ORDER BY pc.ordering ASC";

                $resql = $this->db->query($sql);
                
                if ($resql) {
                    $nb_cat = $this->db->num_rows($resql);

                    $lot->nb_child = $nb_cat;

                    $nb_categories += $nb_cat;
                    if ($nb_cat) {

                        $i = 0;
                        while ($i < $nb_cat)  { 

                            $obj = $this->db->fetch_object($resql);
                            $obj->cost_categorie = 0;
                            $lot->categories[]=$obj;
                            $i++;
                        }

                        $this->db->free($resql);
                    } 
                } else {
                    $this->error = $this->db->lasterror();
                    return -1;
                }

                //POSTES
                //----------------------------------------------------------------------------------
                foreach ($lot->categories as $key => $categorie) {

                    $categorie->postes = array();
                    
                    $categorie_cost = 0;
                    $categorie_cost_calculated = 0;
                    $categorie_marge = 0;
                    $categorie_pv = 0;
                    $categorie_marge = 0;

                    $sql = "
                    SELECT pc.rowid, pc.ref, pc.fk_categorie, pc.fk_task_parent, pc.label, pc.datec, pc.fk_user_creat, pc.fk_statut, pc.planned_workload, pc.progress,  pc.cost, pc.progress_estimated, pc.fact_fourn, pc.poste_pv, pc.tx_tva
                    FROM " . MAIN_DB_PREFIX . "abcvc_projet_task as pc
                    WHERE 
                        pc.fk_projet=".$project->rowid."
                        AND pc.fk_categorie=".$categorie->rowid."
                    ";
                    //$sql.= " ORDER BY pc.ordering ASC";
                    //var_dump($sql);
                    //exit();
                    $resql = $this->db->query($sql);
                    
                    if ($resql) {
                        $nb_post = $this->db->num_rows($resql);

                        $categorie->nb_child = $nb_post;

                        $project->nb_postes += $nb_post;
                        if ($nb_post) {

                            $i = 0;
                            while ($i < $nb_post)  { 
                                $obj = $this->db->fetch_object($resql);

                                    //add CONTACT TO POSTE 
                                    $objtask->id = $obj->rowid;

                                    if( $full ){
                                        $contacts = $objtask->liste_contact(4,'internal',1,'TASKEXECUTIVE');
                                    }else{
                                        $contacts = $objtask->liste_contact(4,'internal',0,'TASKEXECUTIVE');
                                    }
                                    $obj->contacts_executive = $contacts;     

                                    if( $full ){
                                        $contacts = $objtask->liste_contact(4,'internal',1,'TASKCONTRIBUTOR');
                                    }else{
                                        $contacts = $objtask->liste_contact(4,'internal',0,'TASKCONTRIBUTOR');
                                    }
                                    $obj->contacts_contributor = $contacts; 

                                    //arrays of id contact
                                    $ids_contact = array_merge($obj->contacts_executive,$obj->contacts_contributor);
                                   //var_dump($ids_contact);
                                    
                                    //manuel estime
                                    $obj->cost = round($obj->cost,2);

                                    //return COST_MO
                                    $obj->cost_mo = round($objtask->getCostByUser($ids_contact,$obj->planned_workload),2);

                                    //return COST_MO_CALCULATED
                                    // array (size=3)
                                    //   'amount' => string '0' (length=1)
                                    //   'nbseconds' => string '14400' (length=5)
                                   

                                   //exit();
                                    $timespent_task = $objtask->getSumOfAmount($ids_contact);
                                    //if($obj->rowid == 46){
                                    //    var_dump($timespent_task);
                                    //    exit();
                                    //}
                                    $obj->calculated_workload = (int)$timespent_task['nbseconds'];
                                    $obj->cost_mo_calculated = round($timespent_task['amount'],2);

                                    if($obj->planned_workload>0){
                                        $obj->progress_estimated = round($obj->calculated_workload * 100 / $obj->planned_workload,2);
                                    } else {
                                        $obj->progress_estimated = 0;
                                    }

                                    //return COST_FOURN
                                    $obj->cost_fourn = round($objtask->getCostByTask($obj),2);

                                    //INSERT FINAL COST hmmmm zarbi mais client roi => il faut faire : prix de vente-(couts estimes-charges calculées) 
                                    //OOOKAYYY fff on a une regle "realiste" !
                                    //si pas de cout calcule -> marge = poste_pv - cost
                                    //si cout calcule -> marge = poste_pv - (cost_mo + cost_fourn)
                                    //---------------------------------------------------
                                    if( ($obj->cost_mo_calculated>0) || ($obj->cost_fourn>0)  ){
                                        $cost_mo_final = $obj->cost_mo_calculated; //($obj->cost_mo_calculated>0?$obj->cost_mo_calculated:$obj->cost_mo);
                                        $obj->cost_final = ( $cost_mo_final + $obj->cost_fourn );
                                    } else {
                                        $cost_mo_final=0;//$obj->cost_mo;
                                        $obj->cost_final = 0;$obj->cost;
                                    }

                                    $obj->poste_pv = round($obj->poste_pv,2);
                                    
                                    //SUM FOR CATEGORIE
                                    //---------------------------
                                    $categorie_cost_calculated += $cost_mo_final + $obj->cost_fourn;
                                    $categorie_marge += $obj->poste_pv - $obj->cost_final;
                                    $categorie_cost += $obj->cost;    
                                    $categorie_pv += $obj->poste_pv; 

                                    //sum progress projet
                                    $project->progress_estimated += $obj->progress_estimated;
                                    $project->progress += $obj->progress;


                                $categorie->postes[]=$obj;
                                $i++;
                            }

                            $this->db->free($resql);
                        } 
                    } else {
                        $this->error = $this->db->lasterror();
                        return -1;
                    }


                    //RETURNING SUM FOR ALL TASKS (POSTE/SUBPOSTE/SUBSUBPOSTE)
                    $categorie->cost = $categorie_cost;
                    $categorie->cost_calculated = $categorie_cost_calculated;
                    $categorie->pv_categorie = $categorie_pv;
                    $categorie->marge_categorie = $categorie_marge; //round($categorie->pv_categorie - $categorie->cost_categorie,2);
                    
                    $lot->cost_calculated += $categorie_cost_calculated;
                    $lot->cost += $categorie_cost;
                    $lot->pv_lot += $categorie_pv;
                    $lot->marge += $categorie_marge;
                }

                $project->cost_calculated += $lot->cost_calculated;
                $project->cost += $lot->cost;
                $project->pv += $lot->pv_lot;
                $project->marge += $lot->marge; 
            }
            //injection charges fixes
            $project->cost += $project->chargesfixe;
            $project->cost_calculated += $project->chargesfixe;
            $project->marge = $project->pv - $project->cost_calculated; 

            //calcul progression moyenne
            if($project->nb_postes>0){
                $project->progress_estimated = round( (100*$project->progress_estimated)/($project->nb_postes*100),2);
                $project->progress = round( (100*$project->progress)/($project->nb_postes*100),2);
            } else {
                $project->progress_estimated = 0;
                $project->progress = 0;
            }

            //test
            //$project->cost_calculated = 36000;
            //$project->marge = $project->pv - $project->cost_calculated;
        }  

        // var_dump($projects);
        // exit();          
        
        return $projects;
    }



    //**************************************************************************************************************
    //
    //
    // STRUCTURES TIMESPENT PROJETS
    // 
    // 
    //**************************************************************************************************************


    //projects structure tree
    public function getTimespentProjectsTree($id_projet, $id_user, $date_de, $date_a, $full = true) {

        global $conf, $langs;
        
        $error = 0;
        $ret = 0;
        $objtask=new TaskABCVC($this->db);
        $now=dol_now();

     
        $postes = array();

        //POSTES
        //----------------------------------------------------------------------------------
        $sql = "
        SELECT pc.rowid, pc.ref, pc.fk_categorie, pc.fk_task_parent, pc.label, pc.datec, pc.fk_user_creat, pc.fk_statut, pc.planned_workload, pc.progress,  pc.cost, pc.progress_estimated, pc.fact_fourn, pc.poste_pv
        FROM " . MAIN_DB_PREFIX . "abcvc_projet_task as pc
        WHERE 
            pc.fk_projet=".$id_projet."
            AND fk_task_parent = 0
        ";
        //                AND pc.fk_categorie=".$categorie->rowid."
        //$sql.= " ORDER BY pc.ref ASC";
        //var_dump($sql);
        //exit();
        $resql = $this->db->query($sql);
        if ($resql) {
            $nb_post = $this->db->num_rows($resql);
            if ($nb_post) {
                $i = 0;
                while ($i < $nb_post)  { 
                    $obj = $this->db->fetch_object($resql);
                    
                    //add CONTACT TO POSTE 
                    $objtask->id = $obj->rowid;
                    $contacts = $objtask->liste_contact(4,'internal',1,'TASKEXECUTIVE');
                    $obj->contacts_executive = $contacts;     

                    $contacts = $objtask->liste_contact(4,'internal',1,'TASKCONTRIBUTOR');
                    $obj->contacts_contributor = $contacts; 

                    //arrays of id contact
                    $ids_contact = array_merge($obj->contacts_executive,$obj->contacts_contributor);
                    //var_dump($ids_contact);
                    //exit();
                    $timespent_task = $objtask->getSumOfAmount($ids_contact);

                    $obj->calculated_workload = (int)$timespent_task['nbseconds'];
                    if($obj->planned_workload!=0){
                        $obj->progress_estimated = round($obj->calculated_workload * 100 / $obj->planned_workload,2);
                    } else {
                        if($obj->calculated_workload>0){
                            $obj->progress_estimated = 101;
                        } else {
                            $obj->progress_estimated = 0;                                        
                        }                        
                    }

                    $postes[]=$obj;
                    $i++;
                }

                $this->db->free($resql);
            } 
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }

        //
        //----------------------------------------------------------------------------------
        foreach ($postes as $poste) {
            
            //timespent
            $sql = "
            SELECT t.*
            FROM " . MAIN_DB_PREFIX . "abcvc_projet_task_time as t
            WHERE t.fk_task=".$poste->rowid." AND t.fk_user = ".$id_user."
             AND t.task_datehour>='".$date_de."' AND t.task_datehour<='".$date_a."'
             group by ( t.task_date )
            ORDER BY t.task_date ASC";
            //var_dump($sql);

            $resql = $this->db->query($sql);
            if ($resql) {
                $nb_timespent = $this->db->num_rows($resql);
                if ($nb_timespent) {
                    $i = 0;
                    while ($i < $nb_timespent)  { 
                        $obj = $this->db->fetch_object($resql);
              
                        $poste->timespent[]=$obj;
                        $i++;
                    }

                    $this->db->free($resql);
                } 
            } else {
                $this->error = $this->db->lasterror();
                return -1;
            }




        }


       // var_dump($postes);
       // exit();          
        
        return $postes;
    }


    //**************************************************************************************************************
    //
    //
    // STRUCTURES TIMESPENT PROJETS
    // 
    // 
    //**************************************************************************************************************


    public function getProjectBillingHistory($idproject, $user)
    {
        $projectBills =array();
        $facturesraw =array();

        //liste factures / postes
        $sql = '
        SELECT
        po.rowid as poste_id, po.poste_pv, 
        fa.rowid as fact_id, fa.facnumber,fa.datec, fa.fk_statut,
        fd.rowid as line_id, fd.description, fd.tva_tx, fd.qty, fd.subprice, fd.total_ht, fd.total_ttc, fd.import_key

        FROM llx_facturedet as fd
        LEFT JOIN llx_facture as fa on (fa.rowid = fd.fk_facture)

        LEFT JOIN llx_abcvc_projet_task as po on (po.ref = SUBSTR(fd.description,1,LOCATE(" ",fd.description)) and po.fk_projet = fa.fk_projet)

        WHERE fa.fk_projet=' . $idproject  .' AND fd.description<>"" 
        ORDER BY fa.datec  asc';
        //var_dump($sql);
        //exit();


        // exit;
        $sum = 0;
        $resql = $this->db->query($sql);
        if ($resql) { 
            $nbfactures = $this->db->num_rows($resql);
            if ($nbfactures) {
                $i = 0;
                while ($i < $nbfactures)  { 
                    $obj = $this->db->fetch_object($resql);
                    $facturesraw[]=$obj;
                    $i++;
                } 
            }
            $this->db->free($resql);    
            /*
            var_dump($facturesraw);
            exit();
            array (size=23)
              0 => 
                object(stdClass)[134]
                  public 'poste_id' => string '44' (length=2)
                  public 'fact_id' => string '13' (length=2)
                  public 'facnumber' => string 'FA1711-0001' (length=11)
                  public 'datec' => string '2017-11-03 13:09:10' (length=19)
                  public 'fk_statut' => string '1' (length=1)
                  public 'line_id' => string '73' (length=2)
                  public 'description' => string '1.1.1 Installation de chantier suivant CCTP' (length=43)
                  public 'tva_tx' => string '20.000' (length=6)
                  public 'qty' => string '1' (length=1)
                  public 'subprice' => string '900.00000000' (length=12)
                  public 'total_ht' => string '900.00000000' (length=12)
                  public 'total_ttc' => string '1080.00000000' (length=13)
                  public 'import_key' => string '50' (length=2)
            */      


            foreach ($facturesraw as $factureraw) {

                $projectBills[$factureraw->poste_id][$factureraw->facnumber]=array(
                    'facnumber'=>$factureraw->facnumber,
                    'datec'=>$factureraw->datec,
                    'description'=>$factureraw->description,

                    'poste_id'=>$factureraw->poste_id,
                    'fact_id'=>$factureraw->fact_id,
                    'line_id'=>$factureraw->line_id,

                    'poste_pv'=>$factureraw->poste_pv,
                    'subprice'=>$factureraw->subprice,
                    'total_ht'=>$factureraw->total_ht,
                    'total_ttc'=>$factureraw->total_ttc,
                    'import_key'=>$factureraw->import_key
                );
            }
            //var_dump($projectBills);
            //exit();
           

            
        } else {
            $this->error = $this->db->lasterror();
        }

        return $projectBills;
    }












    function getAllUnites (){
        global $langs;
         $sql = "
            SELECT rowid,code,label,short_label,active
            FROM " . MAIN_DB_PREFIX . "c_units";
            //var_dump($sql);
            $unite = array();
            $resql = $this->db->query($sql);
            if ($resql) {
                $unit = $this->db->num_rows($resql);
                if ($unit) {
                    $i = 0;
                    while ($i < $unit)  { 
                        $obj = $this->db->fetch_object($resql);
                        $unite[] = $obj;
                        $i++;
                    }
                    $this->db->free($resql);
                } 
            } else {
                $this->error = $this->db->lasterror();
                return -1;
            }
            // var_dump($unite);
            // exit;
            return $unite;

    }
    function form_project($page, $socid, $selected='', $htmlname='projectid', $discard_closed=0, $maxlength=20, $forcefocus=0, $nooutput=0)
    {
        global $langs;

        require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/core/lib/project.lib.php';
        require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/html.formprojet.class.php';

        $out='';
        
        $formproject=new FormProjets($this->db);

        $langs->load("project");
        if ($htmlname != "none")
        {
            $out.="\n";
            $out.='<form method="post" action="'.$page.'">';
            $out.='<input type="hidden" name="action" value="classin">';
            $out.='<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
            $out.=$formproject->select_projects($socid, $selected, $htmlname, $maxlength, 0, 1, $discard_closed, $forcefocus, 0, 0, '', 1);
            $out.='<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
            $out.='</form>';
        }
        else
        {
            if ($selected)
            {
                $projet = new ProjectABCVC($this->db);
                $projet->fetch($selected);
                //print '<a href="'.DOL_URL_ROOT.SUPP_PATH.'/projet/card.php?id='.$selected.'">'.$projet->title.'</a>';
                $out.=$projet->getNomUrl(0,'',1);
            }
            else
            {
                $out.="&nbsp;";
            }
        }
        
        if (empty($nooutput)) 
        {
            print $out;
            return '';
        }
        return $out;
    }

    //This function is used to select all S Poste and S S Postes by REF's and fk_project
    //this function is called to generate facture pdf's and to extract Unite and Quantite  
    function getAllTasksForPDF($ref,$fk_project){
        global $langs;
        $sql = "    SELECT unite,quantite FROM abcvc.llx_abcvc_projet_task
                    WHERE ref IN ('".$ref."') and fk_projet = ".$fk_project.""; 
                    // var_dump($sql);
                    // exit;
        $unite_quantites = array();
        $resql = $this->db->query($sql);
        if($resql){
            $unite_quantite = $this->db->num_rows($resql);
            if($unite_quantite){
                $i=0;
                while ( $i < $unite_quantite ) {
                    $obj = $this->db->fetch_object($resql);
                    if($obj->quantite == 0.00 && $obj->unite == 0  )
                    {
                        $unite_quantites[] = " ";
                    }else{
                        $unite_quantites[] = "<br><small><i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".$obj->quantite." (".$obj->unite.") </i></small>";
                    }
                    $i++; 
                }
                $this->db->free($resql);
            }
        }else{
            $this->error = $this->db->lasterror();
            return -1;
        }
        return $unite_quantites;
    }

    function get_vat()
    {
        global $conf;

        $countryInfo = preg_split("/:/", $conf->global->MAIN_INFO_SOCIETE_COUNTRY);
        $countryId = $countryInfo[0];

        $sql = "SELECT taux, note";
        $sql.= " FROM " . MAIN_DB_PREFIX . "c_tva";
        $sql.= " WHERE fk_pays = " . $countryId . " AND active = 1";

        $resql = $this->db->query($sql);
        if ($resql)
        {
            $nump = $this->db->num_rows($resql);
            if ($nump)
            {
                $i = 0;
                while ($i < $nump)
                {
                    $obj = $this->db->fetch_object($resql);
                    $projects[$obj->taux] = $obj->note;
                    $i++;
                }
            }
            return $projects;
        }
        else
        {
            print $this->db->lasterror();
        }
    }
}