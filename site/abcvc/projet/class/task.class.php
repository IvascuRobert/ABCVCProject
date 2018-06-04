<?php
/* Copyright (C) 2008-2014  Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) 2010-2012  Regis Houssin       <regis.houssin@capnetworks.com>
 * Copyright (C) 2014       Marcos GarcĂ­a       <marcosgdf@gmail.com>
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
 *      \file       htdocs/projet/class/task.class.php
 *      \ingroup    project
 *      \brief      This file is a CRUD class file for Task (Create/Read/Update/Delete)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';


/**
 *  Class to manage tasks
 */
class TaskABCVC extends CommonObject
{
    public $element='projectabcvc_task';     //!< Id that identify managed objects
    public $table_element='abcvc_projet_task';    //!< Name of table without prefix where object is stored
    public $fk_element='fk_task';
    public $picto = 'task';
    protected $childtables=array('abcvc_projet_task_time');    // To test if we can delete object
    
    var $fk_task_parent;
    var $label;
    var $description;
    var $duration_effective;        // total of time spent on this task
    var $planned_workload;
    var $date_c;
    var $date_start;
    var $date_end;
    var $progress;
    var $fk_statut;
    var $priority;
    var $fk_user_creat;
    var $fk_user_valid;
    var $rang;

    var $timespent_min_date;
    var $timespent_max_date;
    var $timespent_total_duration;
    var $timespent_total_amount;
    var $timespent_nblinesnull;
    var $timespent_nblines;
    // For detail of lines of timespent record, there is the property ->lines in common
    
    // Var used to call method addTimeSpent(). Bad practice.
    var $timespent_id;
    var $timespent_duration;
    var $timespent_old_duration;
    var $timespent_date;
    var $timespent_datehour;        // More accurate start date (same than timespent_date but includes hours, minutes and seconds)
    var $timespent_withhour;        // 1 = we entered also start hours for timesheet line
    var $timespent_fk_user;
    var $timespent_note;

    public $oldcopy;


    /**
     *  Constructor
     *
     *  @param      DoliDB      $db      Database handler
     */
    function __construct($db)
    {
        $this->db = $db;
    }


    /**
     *  Create into database
     *
     *  @param  User    $user           User that create
     *  @param  int     $notrigger      0=launch triggers after, 1=disable triggers
     *  @return int                     <0 if KO, Id of created object if OK
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

        // Insert request
        $sql = "INSERT INTO ".MAIN_DB_PREFIX . "abcvc_projet_task (";
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
        $sql.= ")";

        $this->db->begin();

        dol_syslog(get_class($this)."::create", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

        if (! $error)
        {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX . "abcvc_projet_task");

            if (! $notrigger)
            {
                // Call trigger
                $result=$this->call_trigger('TASK_CREATE',$user);
                if ($result < 0) { $error++; }
                // End call triggers
            }
        }

        // Update extrafield
        if (! $error)
        {
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
     *  Load object in memory from database
     *
     *  @param  int     $id         Id object
     *  @param  int     $ref        ref object
     *  @return int                 <0 if KO, 0 if not found, >0 if OK
     */
    function fetch($id,$ref='')
    {
        global $langs;

        $sql = "SELECT";
        $sql.= " t.rowid,";
        $sql.= " t.ref,";
        $sql.= " t.fk_projet,";
        $sql.= " t.fk_task_parent,";
        $sql.= " t.label,";
        $sql.= " t.description,";
        $sql.= " t.duration_effective,";
        $sql.= " t.planned_workload,";
        $sql.= " t.datec,";
        $sql.= " t.dateo,";
        $sql.= " t.datee,";
        $sql.= " t.fk_user_creat,";
        $sql.= " t.fk_user_valid,";
        $sql.= " t.fk_statut,";
        $sql.= " t.progress,";
        $sql.= " t.priority,";
        $sql.= " t.note_private,";
        $sql.= " t.note_public,";
        $sql.= " t.rang";
        $sql.= " FROM ".MAIN_DB_PREFIX . "abcvc_projet_task as t";
        $sql.= " WHERE ";
        if (!empty($ref)) {
            $sql.="t.ref = '".$this->db->escape($ref)."'";
        }else {
            $sql.="t.rowid = ".$id;
        }

        dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $num_rows = $this->db->num_rows($resql);
            
            if ($num_rows)
            {
                $obj = $this->db->fetch_object($resql);

                $this->id                   = $obj->rowid;
                $this->ref                  = $obj->ref;
                $this->fk_project           = $obj->fk_projet;
                $this->fk_task_parent       = $obj->fk_task_parent;
                $this->label                = $obj->label;
                $this->description          = $obj->description;
                $this->duration_effective   = $obj->duration_effective;
                $this->planned_workload     = $obj->planned_workload;
                $this->date_c               = $this->db->jdate($obj->datec);
                $this->date_start           = $this->db->jdate($obj->dateo);
                $this->date_end             = $this->db->jdate($obj->datee);
                $this->fk_user_creat        = $obj->fk_user_creat;
                $this->fk_user_valid        = $obj->fk_user_valid;
                $this->fk_statut            = $obj->fk_statut;
                $this->progress             = $obj->progress;
                $this->priority             = $obj->priority;
                $this->note_private         = $obj->note_private;
                $this->note_public          = $obj->note_public;
                $this->rang                 = $obj->rang;
            }

            $this->db->free($resql);

            if ($num_rows) return 1;
            else return 0;
        }
        else
        {
            $this->error="Error ".$this->db->lasterror();
            return -1;
        }
    }


    /**
     *  Update database
     *
     *  @param  User    $user           User that modify
     *  @param  int     $notrigger      0=launch triggers after, 1=disable triggers
     *  @return int                     <=0 if KO, >0 if OK
     */
    function update($user=null, $notrigger=0)
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

        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX . "abcvc_projet_task SET";
        $sql.= " fk_projet=".(isset($this->fk_project)?$this->fk_project:"null").",";
        $sql.= " ref=".(isset($this->ref)?"'".$this->db->escape($this->ref)."'":"'".$this->id."'").",";
        $sql.= " fk_task_parent=".(isset($this->fk_task_parent)?$this->fk_task_parent:"null").",";
        $sql.= " label=".(isset($this->label)?"'".$this->db->escape($this->label)."'":"null").",";
        $sql.= " description=".(isset($this->description)?"'".$this->db->escape($this->description)."'":"null").",";
        //$sql.= " duration_effective=".(isset($this->duration_effective)?$this->duration_effective:"null").",";
        $sql.= " planned_workload=".((isset($this->planned_workload) && $this->planned_workload != '')?$this->planned_workload:"null").",";
        $sql.= " dateo=".($this->date_start!=''?"'".$this->db->idate($this->date_start)."'":'null').",";
        $sql.= " datee=".($this->date_end!=''?"'".$this->db->idate($this->date_end)."'":'null').",";
        $sql.= " progress=".$this->progress.",";
        $sql.= " rang=".((!empty($this->rang))?$this->rang:"0");
        $sql.= " WHERE rowid=".$this->id;

        $this->db->begin();

        dol_syslog(get_class($this)."::update", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

        if (! $error)
        {
            if (! $notrigger)
            {
                // Call trigger
                $result=$this->call_trigger('TASK_MODIFY',$user);
                if ($result < 0) { $error++; }
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

        if (! $error && (is_object($this->oldcopy) && $this->oldcopy->ref !== $this->ref))
        {
            // We remove directory
            if ($conf->projet->dir_output)
            {
                $project = new Project($this->db);
                $project->fetch($this->fk_project);

                $olddir = $conf->projet->dir_output.'/'.dol_sanitizeFileName($project->ref).'/'.dol_sanitizeFileName($this->oldcopy->ref);
                $newdir = $conf->projet->dir_output.'/'.dol_sanitizeFileName($project->ref).'/'.dol_sanitizeFileName($this->ref);
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
     *  Delete task from database
     *
     *  @param  User    $user           User that delete
     *  @param  int     $notrigger      0=launch triggers after, 1=disable triggers
     *  @return int                     <0 if KO, >0 if OK
     */
    function delete($user, $notrigger=0)
    {

        global $conf, $langs;
        require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

        $error=0;

        $this->db->begin();

        if ($this->hasChildren() > 0)
        {
            dol_syslog(get_class($this)."::delete Can't delete record as it has some sub tasks", LOG_WARNING);
            $this->error='ErrorRecordHasSubTasks';
            $this->db->rollback();
            return 0;
        }

        $objectisused = $this->isObjectUsed($this->id);
        if (! empty($objectisused))
        {
            dol_syslog(get_class($this)."::delete Can't delete record as it has some child", LOG_WARNING);
            $this->error='ErrorRecordHasChildren';
            $this->db->rollback();
            return 0;
        }
        
        if (! $error)
        {
            // Delete linked contacts
            $res = $this->delete_linked_contact();
            if ($res < 0)
            {
                $this->error='ErrorFailToDeleteLinkedContact';
                //$error++;
                $this->db->rollback();
                return 0;
            }
        }

        if (! $error)
        {
            $sql = "DELETE FROM ".MAIN_DB_PREFIX . "abcvc_projet_task_time";
            $sql.= " WHERE fk_task=".$this->id;

            $resql = $this->db->query($sql);
            if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }
        }

        if (! $error)
        {
            $sql = "DELETE FROM ".MAIN_DB_PREFIX . "abcvc_projet_task_extrafields";
            $sql.= " WHERE fk_object=".$this->id;

            $resql = $this->db->query($sql);
            if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }
        }

        if (! $error)
        {
            $sql = "DELETE FROM ".MAIN_DB_PREFIX . "abcvc_projet_task";
            $sql.= " WHERE rowid=".$this->id;

            $resql = $this->db->query($sql);
            if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }
        }

        if (! $error)
        {
            if (! $notrigger)
            {
                // Call trigger
                $result=$this->call_trigger('TASK_DELETE',$user);
                if ($result < 0) { $error++; }
                // End call triggers
            }
        }

        // Commit or rollback
        if ($error)
        {
            foreach($this->errors as $errmsg)
            {
                dol_syslog(get_class($this)."::delete ".$errmsg, LOG_ERR);
                $this->error.=($this->error?', '.$errmsg:$errmsg);
            }
            $this->db->rollback();
            return -1*$error;
        }
        else
        {
            //Delete associated link file
            if ($conf->projet->dir_output)
            {
                $projectstatic=new Project($this->db);
                $projectstatic->fetch($this->fk_project);

                $dir = $conf->projet->dir_output . "/" . dol_sanitizeFileName($projectstatic->ref) . '/' . dol_sanitizeFileName($this->id);
                dol_syslog(get_class($this)."::delete dir=".$dir, LOG_DEBUG);
                if (file_exists($dir))
                {
                    require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
                    $res = @dol_delete_dir_recursive($dir);
                    if (!$res)
                    {
                        $this->error = 'ErrorFailToDeleteDir';
                        $this->db->rollback();
                        return 0;
                    }
                }
            }

            $this->db->commit();

            return 1;
        }
    }

    /**
     *  Return nb of children
     *
     *  @return int     <0 if KO, 0 if no children, >0 if OK
     */
    function hasChildren()
    {
        $error=0;
        $ret=0;

        $sql = "SELECT COUNT(*) as nb";
        $sql.= " FROM ".MAIN_DB_PREFIX . "abcvc_projet_task";
        $sql.= " WHERE fk_task_parent=".$this->id;

        dol_syslog(get_class($this)."::hasChildren", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }
        else
        {
            $obj=$this->db->fetch_object($resql);
            if ($obj) $ret=$obj->nb;
            $this->db->free($resql);
        }

        if (! $error)
        {
            return $ret;
        }
        else
        {
            return -1;
        }
    }


    /**
     *  Return clicable name (with picto eventually)
     *
     *  @param  int     $withpicto      0=No picto, 1=Include picto into link, 2=Only picto
     *  @param  string  $option         'withproject' or ''
     *  @param  string  $mode           Mode 'task', 'time', 'contact', 'note', document' define page to link to.
     *  @param  int     $addlabel       0=Default, 1=Add label into string, >1=Add first chars into string
     *  @param  string  $sep            Separator between ref and label if option addlabel is set
     *  @param  int     $notooltip      1=Disable tooltip
     *  @return string                  Chaine avec URL
     */
    function getNomUrl($withpicto=0,$option='',$mode='task', $addlabel=0, $sep=' - ', $notooltip=0)
    {
        global $conf, $langs, $user;

        if (! empty($conf->dol_no_mouse_hover)) $notooltip=1;   // Force disable tooltips
        
        $result='';
        $label = '<u>' . $langs->trans("ShowTask") . '</u>';
        if (! empty($this->ref))
            $label .= '<br><b>' . $langs->trans('Ref') . ':</b> ' . $this->ref;
        if (! empty($this->label))
            $label .= '<br><b>' . $langs->trans('LabelTask') . ':</b> ' . $this->label;
        if ($this->date_start || $this->date_end)
        {
            $label .= "<br>".get_date_range($this->date_start,$this->date_end,'',$langs,0);
        }
        
        $url = DOL_URL_ROOT.SUPP_PATH.'/projet/tasks/'.$mode.'.php?id='.$this->id.($option=='withproject'?'&withproject=1':'');

        $linkclose = '';
        if (empty($notooltip))
        {
            if (! empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER))
            {
                $label=$langs->trans("ShowTask");
                $linkclose.=' alt="'.dol_escape_htmltag($label, 1).'"';
            }
            $linkclose.= ' title="'.dol_escape_htmltag($label, 1).'"';
            $linkclose.=' class="classfortooltip"';
        }
        
        $linkstart = '<a href="#"'; //'.$url.'
        $linkstart.=$linkclose.'>';
        $linkend='</a>';
       
        
        $picto='projecttask';

        if ($withpicto) $result.=($linkstart.img_object(($notooltip?'':$label), $picto, ($notooltip?'':'class="classfortooltip"'), 0, 0, $notooltip?0:1).$linkend);
        if ($withpicto && $withpicto != 2) $result.=' ';
        if ($withpicto != 2) $result.=$linkstart.$this->ref.$linkend . (($addlabel && $this->label) ? $sep . dol_trunc($this->label, ($addlabel > 1 ? $addlabel : 0)) : '');
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
        $this->id=0;

        $this->fk_projet='';
        $this->ref='TK01';
        $this->fk_task_parent='';
        $this->label='Specimen task TK01';
        $this->duration_effective='';
        $this->fk_user_creat='';
        $this->progress='25';
        $this->fk_statut='';
        $this->note='This is a specimen task not';
    }

    /**
     * Return list of tasks for all projects or for one particular project
     * Sort order is on project, then on position of task, and last on start date of first level task
     *
     * @param   User    $usert              Object user to limit tasks affected to a particular user
     * @param   User    $userp              Object user to limit projects of a particular user and public projects
     * @param   int     $projectid          Project id
     * @param   int     $socid              Third party id
     * @param   int     $mode               0=Return list of tasks and their projects, 1=Return projects and tasks if exists
     * @param   string  $filteronprojref    Filter on project ref
     * @param   string  $filteronprojstatus Filter on project status
     * @param   string  $morewherefilter    Add more filter into where SQL request (must start with ' AND ...')
     * @param   string  $filteronprojuser   Filter on user that is a contact of project
     * @param   string  $filterontaskuser   Filter on user assigned to task
     * @return  array                       Array of tasks
     */
    function getTasksArray($usert=0, $userp=0, $projectid=0, $socid=0, $mode=0, $filteronprojref='', $filteronprojstatus=-1, $morewherefilter='',$filteronprojuser=0,$filterontaskuser=0)
    {
        global $conf;

        $tasks = array();

        //print $usert.'-'.$userp.'-'.$projectid.'-'.$socid.'-'.$mode.'<br>';

        // List of tasks (does not care about permissions. Filtering will be done later)
        $sql = "SELECT p.rowid as projectid, p.ref, p.title as plabel, p.public, p.fk_statut as projectstatus,";
        $sql.= " t.rowid as taskid, t.ref as taskref, t.label, t.description, t.fk_task_parent, t.duration_effective, t.progress, t.fk_statut as status,";
        $sql.= " t.dateo as date_start, t.datee as date_end, t.planned_workload, t.rang,";
        $sql.= " s.rowid as thirdparty_id, s.nom as thirdparty_name";
        $sql.= " FROM ".MAIN_DB_PREFIX . "abcvc_projet as p";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON p.fk_soc = s.rowid";
        if ($mode == 0)
        {
            if ($filteronprojuser > 0)
            {
                $sql.= ", ".MAIN_DB_PREFIX."element_contact as ec";
                $sql.= ", ".MAIN_DB_PREFIX."c_type_contact as ctc";
            }
            $sql.= ", ".MAIN_DB_PREFIX . "abcvc_projet_task as t";
            if ($filterontaskuser > 0)
            {
                $sql.= ", ".MAIN_DB_PREFIX."element_contact as ec2";
                $sql.= ", ".MAIN_DB_PREFIX."c_type_contact as ctc2";
            }
            $sql.= " WHERE p.entity IN (".getEntity('project',1).")";
            $sql.= " AND t.fk_projet = p.rowid";
        }
        elseif ($mode == 1)
        {
            if ($filteronprojuser > 0)
            {
                $sql.= ", ".MAIN_DB_PREFIX."element_contact as ec";
                $sql.= ", ".MAIN_DB_PREFIX."c_type_contact as ctc";
            }
            if ($filterontaskuser > 0)
            {
                $sql.= ", ".MAIN_DB_PREFIX . "abcvc_projet_task as t";
                $sql.= ", ".MAIN_DB_PREFIX."element_contact as ec2";
                $sql.= ", ".MAIN_DB_PREFIX."c_type_contact as ctc2";
            }
            else 
            {
                $sql.= " LEFT JOIN ".MAIN_DB_PREFIX . "abcvc_projet_task as t on t.fk_projet = p.rowid";
            }
            $sql.= " WHERE p.entity IN (".getEntity('project',1).")";
        }
        else return 'BadValueForParameterMode';

        if ($filteronprojuser > 0)
        {
            $sql.= " AND p.rowid = ec.element_id";
            $sql.= " AND ctc.rowid = ec.fk_c_type_contact";
            $sql.= " AND ctc.element = 'project'";
            $sql.= " AND ec.fk_socpeople = ".$filteronprojuser;
            $sql.= " AND ec.statut = 4";
            $sql.= " AND ctc.source = 'internal'";
        }
        if ($filterontaskuser > 0)
        {
            $sql.= " AND t.fk_projet = p.rowid";
            $sql.= " AND p.rowid = ec2.element_id";
            $sql.= " AND ctc2.rowid = ec2.fk_c_type_contact";
            $sql.= " AND ctc2.element = 'project_task'";
            $sql.= " AND ec2.fk_socpeople = ".$filterontaskuser;
            $sql.= " AND ec2.statut = 4";
            $sql.= " AND ctc2.source = 'internal'";
        }
        if ($socid) $sql.= " AND p.fk_soc = ".$socid;
        if ($projectid) $sql.= " AND p.rowid in (".$projectid.")";
        if ($filteronprojref) $sql.= " AND p.ref LIKE '%".$filteronprojref."%'";
        if ($filteronprojstatus > -1) $sql.= " AND p.fk_statut = ".$filteronprojstatus;
        if ($morewherefilter) $sql.=$morewherefilter;
        $sql.= " ORDER BY p.ref, t.ref, t.rang, t.dateo";

        //var_dump($sql);

        //print $sql;exit;
        dol_syslog(get_class($this)."::getTasksArray", LOG_DEBUG);
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

                if ((! $obj->public) && (is_object($userp)))    // If not public project and we ask a filter on project owned by a user
                {
                    if (! $this->getUserRolesForProjectsOrTasks($userp, 0, $obj->projectid, 0))
                    {
                        $error++;
                    }
                }
                if (is_object($usert))                          // If we ask a filter on a user affected to a task
                {
                    if (! $this->getUserRolesForProjectsOrTasks(0, $usert, $obj->projectid, $obj->taskid))
                    {
                        $error++;
                    }
                }

                if (! $error)
                {
                    $tasks[$i] = new TaskABCVC($this->db);
                    $tasks[$i]->id              = $obj->taskid;
                    $tasks[$i]->ref             = $obj->taskref;
                    $tasks[$i]->fk_project      = $obj->projectid;
                    $tasks[$i]->projectref      = $obj->ref;
                    $tasks[$i]->projectlabel    = $obj->plabel;
                    $tasks[$i]->projectstatus   = $obj->projectstatus;
                    $tasks[$i]->label           = $obj->label;
                    $tasks[$i]->description     = $obj->description;
                    $tasks[$i]->fk_parent       = $obj->fk_task_parent;      // deprecated
                    $tasks[$i]->fk_task_parent  = $obj->fk_task_parent;
                    $tasks[$i]->duration        = $obj->duration_effective;
                    $tasks[$i]->planned_workload= $obj->planned_workload;
                    $tasks[$i]->progress        = $obj->progress;
                    $tasks[$i]->fk_statut       = $obj->status;
                    $tasks[$i]->public          = $obj->public;
                    $tasks[$i]->date_start      = $this->db->jdate($obj->date_start);
                    $tasks[$i]->date_end        = $this->db->jdate($obj->date_end);
                    $tasks[$i]->rang            = $obj->rang;
                    
                    $tasks[$i]->thirdparty_id   = $obj->thirdparty_id;
                    $tasks[$i]->thirdparty_name = $obj->thirdparty_name;
                }

                $i++;
            }
            $this->db->free($resql);
        }
        else
        {
            dol_print_error($this->db);
        }
        //var_dump($sql);

        return $tasks;
    }

    /**
     * Return list of roles for a user for each projects or each tasks (or a particular project or a particular task).
     *
     * @param   User    $userp                Return roles on project for this internal user. If set, usert and taskid must not be defined.
     * @param   User    $usert                Return roles on task for this internal user. If set userp must NOT be defined. -1 means no filter.
     * @param   int     $projectid            Project id list separated with , to filter on project
     * @param   int     $taskid               Task id to filter on a task
     * @param   integer $filteronprojstatus   Filter on project status if userp is set. Not used if userp not defined.
     * @return  array                         Array (projectid => 'list of roles for project' or taskid => 'list of roles for task')
     */
    function getUserRolesForProjectsOrTasks($userp, $usert, $projectid='', $taskid=0, $filteronprojstatus=-1)
    {
        $arrayroles = array();

        dol_syslog(get_class($this)."::getUserRolesForProjectsOrTasks userp=".is_object($userp)." usert=".is_object($usert)." projectid=".$projectid." taskid=".$taskid);

        // We want role of user for a projet or role of user for a task. Both are not possible.
        if (empty($userp) && empty($usert))
        {
            $this->error="CallWithWrongParameters";
            return -1;
        }
        if (! empty($userp) && ! empty($usert))
        {
            $this->error="CallWithWrongParameters";
            return -1;
        }

        /* Liste des taches et role sur les projets ou taches */
        $sql = "SELECT pt.rowid as pid, ec.element_id, ctc.code, ctc.source";
        if ($userp) $sql.= " FROM ".MAIN_DB_PREFIX . "abcvc_projet as pt";
        if ($usert && $filteronprojstatus > -1) $sql.= " FROM ".MAIN_DB_PREFIX . "abcvc_projet as p, ".MAIN_DB_PREFIX . "abcvc_projet_task as pt";
        if ($usert && $filteronprojstatus <= -1) $sql.= " FROM ".MAIN_DB_PREFIX . "abcvc_projet_task as pt";
        $sql.= ", ".MAIN_DB_PREFIX."element_contact as ec";
        $sql.= ", ".MAIN_DB_PREFIX."c_type_contact as ctc";
        $sql.= " WHERE pt.rowid = ec.element_id";
        if ($userp && $filteronprojstatus > -1) $sql.= " AND pt.fk_statut = ".$filteronprojstatus;
        if ($usert && $filteronprojstatus > -1) $sql.= " AND pt.fk_projet = p.rowid AND p.fk_statut = ".$filteronprojstatus;
        if ($userp) $sql.= " AND ctc.element = 'projectabcvc_task'";
        if ($usert) $sql.= " AND ctc.element = 'projectabcvc_task'";
        $sql.= " AND ctc.rowid = ec.fk_c_type_contact";
        if ($userp) $sql.= " AND ec.fk_socpeople = ".$userp->id;
        if ($usert) $sql.= " AND ec.fk_socpeople = ".$usert->id;
        $sql.= " AND ec.statut = 4";
        $sql.= " AND ctc.source = 'internal'";
        if ($projectid)
        {
            if ($userp) $sql.= " AND pt.rowid in (".$projectid.")";
            if ($usert) $sql.= " AND pt.fk_projet in (".$projectid.")";
        }
        if ($taskid)
        {
            if ($userp) $sql.= " ERROR SHOULD NOT HAPPENS";
            if ($usert) $sql.= " AND pt.rowid = ".$taskid;
        }
        //print $sql;
        //var_dump($sql);
        dol_syslog(get_class($this)."::getUserRolesForProjectsOrTasks execute request", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql)
        {
            $num = $this->db->num_rows($resql);
            $i = 0;
            while ($i < $num)
            {
                $obj = $this->db->fetch_object($resql);
                //var_dump($resql);
                if (empty($arrayroles[$obj->pid]))
                        $arrayroles[$obj->pid] = $obj->code;

                
                else 
                        $arrayroles[$obj->pid].=','.$obj->code;
                $i++;
            }
            $this->db->free($resql);
       
        }
        else
        {
            dol_print_error($this->db);
        }
        //var_dump($arrayroles);
        return $arrayroles;
    }


    /**
     *  Return list of id of contacts of task
     *
     *  @param  string  $source     Source
     *  @return array               Array of id of contacts
     */
    function getListContactId($source='internal')
    {
        $contactAlreadySelected = array();
        $tab = $this->liste_contact(-1,$source);
        //var_dump($tab);
        $num=count($tab);
        $i = 0;
        while ($i < $num)
        {
            if ($source == 'thirdparty') $contactAlreadySelected[$i] = $tab[$i]['socid'];
            else  $contactAlreadySelected[$i] = $tab[$i]['id'];
            $i++;
        }
        return $contactAlreadySelected;
    }


    /**
     *  Add time spent
     *
     *  @param  User    $user           User object
     *  @param  int     $notrigger      0=launch triggers after, 1=disable triggers
     *  @return int                     <=0 if KO, >0 if OK
     */
    function addTimeSpent($data, $notrigger=0)
    {
        global $conf,$langs;

        dol_syslog(get_class($this)."::addTimeSpent", LOG_DEBUG);

        $ret = 0;

        
        //var_dump($data);
        //exit();
        /*  $data['fk_task'] <font color='#888a85'>=&gt;</font> <small>string</small> <font color='#cc0000'>'35'</font> <i>(length=2)</i>
          $data['task_date'] <font color='#888a85'>=&gt;</font> <small>string</small> <font color='#cc0000'>'1506902400'</font> <i>(length=10)</i>
          $data['task_datehour'] <font color='#888a85'>=&gt;</font> <small>string</small> <font color='#cc0000'>'1506902400'</font> <i>(length=10)</i>
          $data['task_date_withhour'] <font color='#888a85'>=&gt;</font> <small>int</small> <font color='#4e9a06'>0</font>
          $data['task_duration'] <font color='#888a85'>=&gt;</font> <small>string</small> <font color='#cc0000'>'25200'</font> <i>(length=5)</i>
          $data['task_type'] <font color='#888a85'>=&gt;</font> <small>string</small> <font color='#cc0000'>'5'</font> <i>(length=1)</i>
          $data['fk_user'] <font color='#888a85'>=&gt;</font> <small>string</small> <font color='#cc0000'>'2'</font> <i>(length=1)</i>
          $data['thm'] <font color='#888a85'>=&gt;</font> <small>int</small> <font color='#4e9a06'>0</font>
          $data['note'] <font color='#888a85'>=&gt;</font> <small>string</small> <font color='#cc0000'>'35'</font> <i>(length=2)</i>
          timespentID
        */

        // Clean / add parameters
        // ----------------------------------------------
        //if (isset($this->timespent_note)) $this->timespent_note = trim($this->timespent_note);
        //if (empty($this->timespent_datehour)) $this->timespent_datehour = $this->timespent_date;

        $task_date = date('Y-m-d',$data['task_date'] );
        $task_datehour = date('Y-m-d',$data['task_date'] ).' '.$data['heure_de'];

        if( $data['mode'] == 'update'){
        //update
        //-------------------------------------------------------------------

            $this->db->begin();

            $sql = "
            UPDATE llx_abcvc_projet_task_time
            SET
            task_date = '".$task_date."',
            task_datehour = '".$task_datehour."',
            task_date_withhour = ".$data['task_date_withhour'].",
            task_duration = ".$data['task_duration'].",
            task_type = ".$data['task_type'].",
            note = ".(isset($data['note'])?"'".$this->db->escape($data['note'])."'":"null")."
            WHERE rowid = ".$data['timespentid'];

            //var_dump($sql);exit();
            $resql=$this->db->query($sql);
            if ($resql)
            {
                $tasktime_id = $data['timespentid'];
                $ret = $tasktime_id;
                $this->timespent_id = $ret;
                
                if (! $notrigger)
                {
                    // Call trigger
                    $result=$this->call_trigger('TASK_TIMESPENT_UPDATE',$user);
                    if ($result < 0) { $ret=-1; }
                    // End call triggers
                }
            }
            else
            {
                $this->error=$this->db->lasterror();
                $ret = -1;
            }

        } else {
        //insert
        //-------------------------------------------------------------------    
            $sql = "SELECT thm FROM llx_user WHERE rowid=".$data['fk_user'];
            $resql=$this->db->query($sql);
            $obj_thm = $this->db->fetch_object($resql);
            $obj_thm = $obj_thm->thm;

            $this->db->begin();

            $sql = "INSERT INTO ".MAIN_DB_PREFIX . "abcvc_projet_task_time (";
            $sql.= "fk_task";
            $sql.= ", task_date";
            $sql.= ", task_datehour";
            $sql.= ", task_date_withhour";
            $sql.= ", task_duration";
            $sql.= ", fk_user";
            $sql.= ", note";
            $sql.= ", thm";
            $sql.= ", task_type";        
            $sql.= ") VALUES (";
            $sql.= $data['fk_task'];
            $sql.= ", '".$task_date."'";
            $sql.= ", '".$task_datehour."'";

            $sql.= ", ".$data['task_date_withhour'];
            $sql.= ", ".$data['task_duration'];
            $sql.= ", ".$data['fk_user'];
            $sql.= ", ".(isset($data['note'])?"'".$this->db->escape($data['note'])."'":"null");
            $sql.= ", '".$obj_thm."'";
            $sql.= ", ".$data['task_type'];
            
            $sql.= ")";
            //var_dump($sql);exit();
            $resql=$this->db->query($sql);
            if ($resql)
            {
                $tasktime_id = $this->db->last_insert_id(MAIN_DB_PREFIX . "abcvc_projet_task_time");
                $ret = $tasktime_id;
                $this->timespent_id = $ret;
                
                if (! $notrigger)
                {
                    // Call trigger
                    $result=$this->call_trigger('TASK_TIMESPENT_CREATE',$user);
                    if ($result < 0) { $ret=-1; }
                    // End call triggers
                }
            }
            else
            {
                $this->error=$this->db->lasterror();
                $ret = -1;
            }

        }

        if ($ret >0)
        {
            $this->db->commit();
        }
        else
        {
            $this->db->rollback();
        }
        return $ret;
    }


    function kdelTimeSpent($data, $notrigger=0)
    {
        global $conf,$langs;

        dol_syslog(get_class($this)."::addTimeSpent", LOG_DEBUG);

        $ret = 1;

        $sql = "
        DELETE FROM llx_abcvc_projet_task_time
        WHERE rowid = ".$data['timespentid'];

        //var_dump($sql);exit();
        $resql=$this->db->query($sql);

        return $ret;
    }    











    /**
     *  Calculate total of time spent for task
     *
     *  @param  int     $userid     Filter on user id. 0=No filter
     *  @return array               Array of info for task array('min_date', 'max_date', 'total_duration', 'total_amount', 'nblines', 'nblinesnull')
     */
    function getSummaryOfTimeSpent($userid=0)
    {
        global $langs;

        $id=$this->id;
        if (empty($id)) 
        {
            dol_syslog("getSummaryOfTimeSpent called on a not loaded task", LOG_ERR);
            return -1; 
        }

        $result=array();

        $sql = "SELECT";
        $sql.= " MIN(t.task_datehour) as min_date,";
        $sql.= " MAX(t.task_datehour) as max_date,";
        $sql.= " SUM(t.task_duration) as total_duration,";
        $sql.= " SUM(t.task_duration / 3600 * ".$this->db->ifsql("t.thm IS NULL", 0, "t.thm").") as total_amount,";
        $sql.= " COUNT(t.rowid) as nblines,";
        $sql.= " SUM(".$this->db->ifsql("t.thm IS NULL", 1, 0).") as nblinesnull";
        $sql.= " FROM ".MAIN_DB_PREFIX . "abcvc_projet_task_time as t";
        $sql.= " WHERE t.fk_task = ".$id;
        if ($userid > 0) $sql.=" AND t.fk_user = ".$userid;
        
        dol_syslog(get_class($this)."::getSummaryOfTimeSpent", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $obj = $this->db->fetch_object($resql);

            $result['min_date'] = $obj->min_date;               // deprecated. use the ->timespent_xxx instead
            $result['max_date'] = $obj->max_date;               // deprecated. use the ->timespent_xxx instead
            $result['total_duration'] = $obj->total_duration;   // deprecated. use the ->timespent_xxx instead
            
            $this->timespent_min_date=$this->db->jdate($obj->min_date);
            $this->timespent_max_date=$this->db->jdate($obj->max_date);
            $this->timespent_total_duration=$obj->total_duration;
            $this->timespent_total_amount=$obj->total_amount;
            $this->timespent_nblinesnull=($obj->nblinesnull?$obj->nblinesnull:0);
            $this->timespent_nblines=($obj->nblines?$obj->nblines:0);
            
            $this->db->free($resql);
        }
        else
        {
            dol_print_error($this->db);
        }
        return $result;
    }

    /**
     *  K OVERRIDE (ventilation users / cout reel )
     *  Calculate quantity and value of time consumed using the thm (hourly amount value of work for user entering time)
     *
     *  @param      User        $fuser      Filter on a dedicated user
     *  @param      string      $dates      Start date (ex 00:00:00)
     *  @param      string      $datee      End date (ex 23:59:59)
     *  @return     array                   Array of info for task array('amount','nbseconds','nblinesnull')
     */
    function getSumOfAmount($users=array(), $dates='', $datee='')
    {
        global $langs;


        $result=array();

        if(count($users)== 0){
            $result['amount'] =  0;
            $result['nbseconds'] =  0;
            return $result;            
        }


        $sql = "
        SELECT 
        fk_user,
        SUM(t.task_duration) as nbseconds,
        SUM(t.task_duration / 3600 * ".$this->db->ifsql("t.thm IS NULL", 0, "t.thm").") as amount
        FROM ".MAIN_DB_PREFIX . "abcvc_projet_task_time as t
        WHERE (t.task_type= 0 OR t.task_type= 6) AND t.fk_task = ".$this->id;

        if (count($users)> 0)
        {
            $sql.=" AND fk_user IN (".implode(',',$users).")";
        }
        if ($dates > 0)
        {
            $datefieldname="task_datehour";
            $sql.=" AND (".$datefieldname." >= '".$this->db->idate($dates)."' OR ".$datefieldname." IS NULL)";
        }
        if ($datee > 0)
        {
            $datefieldname="task_datehour";
            $sql.=" AND (".$datefieldname." <= '".$this->db->idate($datee)."' OR ".$datefieldname." IS NULL)";
        }
        $sql .= " GROUP BY fk_user";
        //print $sql;
        //exit();

        dol_syslog(get_class($this)."::getSumOfAmount", LOG_DEBUG);
        $resql=$this->db->query($sql);
        $timespentUsers = array();
        if ($resql) {
            $nb = $this->db->num_rows($resql);
            if ($nb) {
                $i = 0;
                while ($i < $nb)  { 
                    $obj = $this->db->fetch_object($resql);
                    $timespentUsers[]=$obj;
                    $i++;
                }    
                $this->db->free($resql);
            } 
            $total_amount = 0;
            $total_nbseconds = 0;
            foreach ($timespentUsers as $timespentUser) {
                $total_amount += $timespentUser->amount;
                $total_nbseconds += $timespentUser->nbseconds;
            }
            
            $result['amount'] =  $total_amount;
            $result['nbseconds'] =  $total_nbseconds;

            return $result;
        }
        else
        {
            dol_print_error($this->db);
            return $result;
        }
    }

    /**
     *  Load one record of time spent
     *
     *  @param  int     $id     Id object
     *  @return int             <0 if KO, >0 if OK
     */
    function fetchTimeSpent($id)
    {
        global $langs;

        $sql = "SELECT";
        $sql.= " t.rowid,";
        $sql.= " t.fk_task,";
        $sql.= " t.task_date,";
        $sql.= " t.task_datehour,";
        $sql.= " t.task_date_withhour,";
        $sql.= " t.task_duration,";
        $sql.= " t.fk_user,";
        $sql.= " t.note";
        $sql.= " FROM ".MAIN_DB_PREFIX . "abcvc_projet_task_time as t";
        $sql.= " WHERE t.rowid = ".$id;

        dol_syslog(get_class($this)."::fetchTimeSpent", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

                $this->timespent_id         = $obj->rowid;
                $this->id                   = $obj->fk_task;
                $this->timespent_date       = $this->db->jdate($obj->task_date);
                $this->timespent_datehour   = $this->db->jdate($obj->task_datehour);
                $this->timespent_withhour   = $obj->task_date_withhour;
                $this->timespent_duration   = $obj->task_duration;
                $this->timespent_fk_user    = $obj->fk_user;
                $this->timespent_note       = $obj->note;
            }

            $this->db->free($resql);

            return 1;
        }
        else
        {
            $this->error="Error ".$this->db->lasterror();
            return -1;
        }
    }

    /**
     *  Update time spent
     *
     *  @param  User    $user           User id
     *  @param  int     $notrigger      0=launch triggers after, 1=disable triggers
     *  @return int                     <0 if KO, >0 if OK
     */
    function updateTimeSpent($user, $notrigger=0)
    {
        global $conf,$langs;

        $ret = 0;

        // Clean parameters
        if (empty($this->timespent_datehour)) $this->timespent_datehour = $this->timespent_date;
        if (isset($this->timespent_note)) $this->timespent_note = trim($this->timespent_note);

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX . "abcvc_projet_task_time SET";
        $sql.= " task_date = '".$this->db->idate($this->timespent_date)."',";
        $sql.= " task_datehour = '".$this->db->idate($this->timespent_datehour)."',";
        $sql.= " task_date_withhour = ".(empty($this->timespent_withhour)?0:1).",";
        $sql.= " task_duration = ".$this->timespent_duration.",";
        $sql.= " fk_user = ".$this->timespent_fk_user.",";
        $sql.= " note = ".(isset($this->timespent_note)?"'".$this->db->escape($this->timespent_note)."'":"null");
        $sql.= " WHERE rowid = ".$this->timespent_id;

        dol_syslog(get_class($this)."::updateTimeSpent", LOG_DEBUG);
        if ($this->db->query($sql) )
        {
            if (! $notrigger)
            {
                // Call trigger
                $result=$this->call_trigger('TASK_TIMESPENT_MODIFY',$user);
                if ($result < 0)
                {
                    $this->db->rollback();
                    $ret = -1;
                }
                else $ret = 1;
                // End call triggers
            }
            else $ret = 1;
        }
        else
        {
            $this->error=$this->db->lasterror();
            $this->db->rollback();
            $ret = -1;
        }

        if ($ret == 1 && ($this->timespent_old_duration != $this->timespent_duration))
        {
            $newDuration = $this->timespent_duration - $this->timespent_old_duration;

            $sql = "UPDATE ".MAIN_DB_PREFIX . "abcvc_projet_task";
            $sql.= " SET duration_effective = (SELECT SUM(task_duration) FROM ".MAIN_DB_PREFIX . "abcvc_projet_task_time as ptt where ptt.fk_task = ".$this->id.")";
            $sql.= " WHERE rowid = ".$this->id;

            dol_syslog(get_class($this)."::updateTimeSpent", LOG_DEBUG);
            if (! $this->db->query($sql) )
            {
                $this->error=$this->db->lasterror();
                $this->db->rollback();
                $ret = -2;
            }
        }

        if ($ret >= 0) $this->db->commit();
        return $ret;
    }

    /**
     *  Delete time spent
     *
     *  @param  User    $user           User that delete
     *  @param  int     $notrigger      0=launch triggers after, 1=disable triggers
     *  @return int                     <0 if KO, >0 if OK
     */
    function delTimeSpent($user, $notrigger=0)
    {
        global $conf, $langs;

        $error=0;

        $this->db->begin();

        $sql = "DELETE FROM ".MAIN_DB_PREFIX . "abcvc_projet_task_time";
        $sql.= " WHERE rowid = ".$this->timespent_id;

        dol_syslog(get_class($this)."::delTimeSpent", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

        if (! $error)
        {
            if (! $notrigger)
            {
                // Call trigger
                $result=$this->call_trigger('TASK_TIMESPENT_DELETE',$user);
                if ($result < 0) { $error++; }
                // End call triggers
            }
        }

        if (! $error)
        {
            $sql = "UPDATE ".MAIN_DB_PREFIX . "abcvc_projet_task";
            $sql.= " SET duration_effective = duration_effective - '".$this->timespent_duration."'";
            $sql.= " WHERE rowid = ".$this->id;

            dol_syslog(get_class($this)."::delTimeSpent", LOG_DEBUG);
            if ($this->db->query($sql) )
            {
                $result = 0;
            }
            else
            {
                $this->error=$this->db->lasterror();
                $result = -2;
            }
        }

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

     /**    Load an object from its id and create a new one in database
     *
     *  @param  int     $fromid                 Id of object to clone
     *  @param  int     $project_id             Id of project to attach clone task
     *  @param  int     $parent_task_id         Id of task to attach clone task
     *  @param  bool    $clone_change_dt        recalculate date of task regarding new project start date
     *  @param  bool    $clone_affectation      clone affectation of project
     *  @param  bool    $clone_time             clone time of project
     *  @param  bool    $clone_file             clone file of project
     *  @param  bool    $clone_note             clone note of project
     *  @param  bool    $clone_prog             clone progress of project
     *  @return int                             New id of clone
     */
    function createFromClone($fromid,$project_id,$parent_task_id,$clone_change_dt=false,$clone_affectation=false,$clone_time=false,$clone_file=false,$clone_note=false,$clone_prog=false)
    {
        global $user,$langs,$conf;

        $error=0;

        //Use 00:00 of today if time is use on task.
        $now=dol_mktime(0,0,0,dol_print_date(dol_now(),'%m'),dol_print_date(dol_now(),'%d'),dol_print_date(dol_now(),'%Y'));

        $datec = $now;

        $clone_task=new Task($this->db);
        $origin_task=new Task($this->db);

        $clone_task->context['createfromclone']='createfromclone';

        $this->db->begin();

        // Load source object
        $clone_task->fetch($fromid);
        $clone_task->fetch_optionals();
        //var_dump($clone_task->array_options);exit;
        
        $origin_task->fetch($fromid);

        $defaultref='';
        $obj = empty($conf->global->PROJECT_TASK_ADDON)?'mod_task_simple':$conf->global->PROJECT_TASK_ADDON;
        if (! empty($conf->global->PROJECT_TASK_ADDON) && is_readable(DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.".php"))
        {
            require_once DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.'.php';
            $modTask = new $obj;
            $defaultref = $modTask->getNextValue(0,$clone_task);
        }

        $ori_project_id                 = $clone_task->fk_project;

        $clone_task->id                 = 0;
        $clone_task->ref                = $defaultref;
        $clone_task->fk_project         = $project_id;
        $clone_task->fk_task_parent     = $parent_task_id;
        $clone_task->date_c             = $datec;
        $clone_task->planned_workload   = $origin_task->planned_workload;
        $clone_task->rang               = $origin_task->rang;

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
                $clone_task->date_start         = $now + $clone_task->date_start - $orign_project_dt_start;
            }

            //Calcultate new task end date with difference between origin proj end date and origin task end date
            if (!empty($clone_task->date_end))
            {
                $clone_task->date_end           = $now + $clone_task->date_end - $orign_project_dt_start;
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

                $clone_task_dir = $conf->projet->dir_output . "/" . dol_sanitizeFileName($clone_project_ref). "/" . dol_sanitizeFileName($clone_task_ref);
                $ori_task_dir = $conf->projet->dir_output . "/" . dol_sanitizeFileName($ori_project_ref). "/" . dol_sanitizeFileName($fromid);

                $filearray=dol_dir_list($ori_task_dir,"files",0,'','(\.meta|_preview\.png)$','',SORT_ASC,1);
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
        }

        unset($clone_task->context['createfromclone']);

        if (! $error)
        {
            $this->db->commit();
            return $clone_task_id;
        }
        else
        {
            $this->db->rollback();
            dol_syslog(get_class($this)."::createFromClone nbError: ".$error." error : " . $this->error, LOG_ERR);
            return -1;
        }
    }


    /**
     *  Return status label of object
     *
     *  @param  integer $mode       0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto
     *  @return string              Label
     */
    function getLibStatut($mode=0)
    {
        return $this->LibStatut($this->fk_statut,$mode);
    }

    /**
     *  Return status label for an object
     *
     *  @param  int         $statut     Id statut
     *  @param  integer     $mode       0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto
     *  @return string                  Label
     */
    function LibStatut($statut,$mode=0)
    {
        // list of Statut of the task
        $this->statuts[0]='Draft';
        $this->statuts[1]='Validated';
        $this->statuts[2]='Running';
        $this->statuts[3]='Finish';
        $this->statuts[4]='Transfered';
        $this->statuts_short[0]='Draft';
        $this->statuts_short[1]='Validated';
        $this->statuts_short[2]='Running';
        $this->statuts_short[3]='Finish';
        $this->statuts_short[4]='Transfered';

        global $langs;

        if ($mode == 0)
        {
            return $langs->trans($this->statuts[$statut]);
        }
        if ($mode == 1)
        {
            return $langs->trans($this->statuts_short[$statut]);
        }
        if ($mode == 2)
        {
            if ($statut==0) return img_picto($langs->trans($this->statuts_short[$statut]),'statut0').' '.$langs->trans($this->statuts_short[$statut]);
            if ($statut==1) return img_picto($langs->trans($this->statuts_short[$statut]),'statut1').' '.$langs->trans($this->statuts_short[$statut]);
            if ($statut==2) return img_picto($langs->trans($this->statuts_short[$statut]),'statut3').' '.$langs->trans($this->statuts_short[$statut]);
            if ($statut==3) return img_picto($langs->trans($this->statuts_short[$statut]),'statut4').' '.$langs->trans($this->statuts_short[$statut]);
            if ($statut==4) return img_picto($langs->trans($this->statuts_short[$statut]),'statut6').' '.$langs->trans($this->statuts_short[$statut]);
            if ($statut==5) return img_picto($langs->trans($this->statuts_short[$statut]),'statut5').' '.$langs->trans($this->statuts_short[$statut]);
        }
        if ($mode == 3)
        {
            if ($statut==0) return img_picto($langs->trans($this->statuts_short[$statut]),'statut0');
            if ($statut==1) return img_picto($langs->trans($this->statuts_short[$statut]),'statut1');
            if ($statut==2) return img_picto($langs->trans($this->statuts_short[$statut]),'statut3');
            if ($statut==3) return img_picto($langs->trans($this->statuts_short[$statut]),'statut4');
            if ($statut==4) return img_picto($langs->trans($this->statuts_short[$statut]),'statut6');
            if ($statut==5) return img_picto($langs->trans($this->statuts_short[$statut]),'statut5');
        }
        if ($mode == 4)
        {
            if ($statut==0) return img_picto($langs->trans($this->statuts_short[$statut]),'statut0').' '.$langs->trans($this->statuts[$statut]);
            if ($statut==1) return img_picto($langs->trans($this->statuts_short[$statut]),'statut1').' '.$langs->trans($this->statuts[$statut]);
            if ($statut==2) return img_picto($langs->trans($this->statuts_short[$statut]),'statut3').' '.$langs->trans($this->statuts[$statut]);
            if ($statut==3) return img_picto($langs->trans($this->statuts_short[$statut]),'statut4').' '.$langs->trans($this->statuts[$statut]);
            if ($statut==4) return img_picto($langs->trans($this->statuts_short[$statut]),'statut6').' '.$langs->trans($this->statuts[$statut]);
            if ($statut==5) return img_picto($langs->trans($this->statuts_short[$statut]),'statut5').' '.$langs->trans($this->statuts[$statut]);
        }
        if ($mode == 5)
        {
            if ($statut==0) return $langs->trans($this->statuts_short[$statut]).' '.img_picto($langs->trans($this->statuts_short[$statut]),'statut0');
            if ($statut==1) return $langs->trans($this->statuts_short[$statut]).' '.img_picto($langs->trans($this->statuts_short[$statut]),'statut1');
            if ($statut==2) return $langs->trans($this->statuts_short[$statut]).' '.img_picto($langs->trans($this->statuts_short[$statut]),'statut3');
            if ($statut==3) return $langs->trans($this->statuts_short[$statut]).' '.img_picto($langs->trans($this->statuts_short[$statut]),'statut4');
            if ($statut==4) return $langs->trans($this->statuts_short[$statut]).' '.img_picto($langs->trans($this->statuts_short[$statut]),'statut6');
            if ($statut==5) return $langs->trans($this->statuts_short[$statut]).' '.img_picto($langs->trans($this->statuts_short[$statut]),'statut5');
        }
    }

    /**
     *  Create an intervention document on disk using template defined into PROJECT_TASK_ADDON_PDF
     *
     *  @param  string      $modele         force le modele a utiliser ('' par defaut)
     *  @param  Translate   $outputlangs    objet lang a utiliser pour traduction
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
        if (! dol_strlen($modele))
        {
            if (! empty($conf->global->PROJECT_TASK_ADDON_PDF))
            {
                $modele = $conf->global->PROJECT_TASK_ADDON_PDF;
            }
            else
            {
                $modele='nodefault';
            }
        }

        $modelpath = "core/modules/project/task/doc/";

        return $this->commonGenerateDocument($modelpath, $modele, $outputlangs, $hidedetails, $hidedesc, $hideref);
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
        
        $projectstatic = new Project($this->db);
        $projectsListId = $projectstatic->getProjectsAuthorizedForUser($user,$mine,1,$socid);
        
        // List of tasks (does not care about permissions. Filtering will be done later)
        $sql = "SELECT p.rowid as projectid, p.fk_statut as projectstatus,";
        $sql.= " t.rowid as taskid, t.progress as progress, t.fk_statut as status,";
        $sql.= " t.dateo as date_start, t.datee as datee";
        $sql.= " FROM ".MAIN_DB_PREFIX . "abcvc_projet as p";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s on p.fk_soc = s.rowid";
        if (! $user->rights->societe->client->voir && ! $socid) $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON sc.fk_soc = s.rowid";
        $sql.= ", ".MAIN_DB_PREFIX . "abcvc_projet_task as t";
        $sql.= " WHERE p.entity IN (".getEntity('project').')';
        $sql.= " AND p.fk_statut = 1";
        $sql.= " AND t.fk_projet = p.rowid";
        $sql.= " AND t.progress < 100";         // tasks to do
        if ($mine || ! $user->rights->projet->all->lire) $sql.= " AND p.rowid IN (".$projectsListId.")";
        // No need to check company, as filtering of projects must be done by getProjectsAuthorizedForUser
        //if ($socid || ! $user->rights->societe->client->voir) $sql.= "  AND (p.fk_soc IS NULL OR p.fk_soc = 0 OR p.fk_soc = ".$socid.")";
        if ($socid) $sql.= "  AND (p.fk_soc IS NULL OR p.fk_soc = 0 OR p.fk_soc = ".$socid.")";
        if (! $user->rights->societe->client->voir && ! $socid) $sql.= " AND ((s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id.") OR (s.rowid IS NULL))";
        //print $sql;
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $task_static = new Task($this->db);
    
            $response = new WorkboardResponse();
            $response->warning_delay = $conf->projet->task->warning_delay/60/60/24;
            $response->label = $langs->trans("OpenedTasks");
            if ($user->rights->projet->all->lire) $response->url = DOL_URL_ROOT.SUPP_PATH.'/projet/tasks/list.php?mainmenu=project';
            else $response->url = DOL_URL_ROOT.SUPP_PATH.'/projet/tasks/list.php?mode=mine&amp;mainmenu=project';
            $response->img = img_object($langs->trans("Tasks"),"task");
    
            // This assignment in condition is not a bug. It allows walking the results.
            while ($obj=$this->db->fetch_object($resql))
            {
                $response->nbtodo++;
    
                $task_static->projectstatus = $obj->projectstatus;
                $task_static->progress = $obj->progress;
                $task_static->fk_statut = $obj->status;
                $task_static->date_end = $this->db->jdate($obj->datee);
    
                if ($task_static->hasDelay()) {
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
     * Is the task delayed?
     *
     * @return bool
     */
    public function hasDelay()
    {
        global $conf;
    
        if (! ($this->progress >= 0 && $this->progress < 100)) {
            return false;
        }

        $now = dol_now();

        $datetouse = ($this->date_end > 0) ? $this->date_end : ($this->datee > 0 ? $this->datee : 0);

        return ($datetouse > 0 && ($datetouse < ($now - $conf->projet->task->warning_delay)));
    }   
























/* FUNCTION TO GET ALL CONTACTS */
    function getAllcontacts(){
    
        global $conf, $langs;

        $sql = "SELECT pc.rowid, pc.lastname, pc.firstname
                FROM " . MAIN_DB_PREFIX . "user as pc" ;

        $resql = $this->db->query($sql);
        $contacts = array();


        if ($resql) {
            $nb = $this->db->num_rows($resql);

            if ($nb) {

            $i = 0;

                while ($i < $nb)  { 
                    $obj = $this->db->fetch_object($resql);
                    $contacts[]=$obj;
                    $i++;
                }    

            $this->db->free($resql);
            } 
        } else {

        $this->error = $this->db->lasterror();
        return -1;
            }

        return $contacts;

        //if ($result->num_rows > 0) {
        //    // output data of each row
        //while($row = $result->fetch_assoc()) {
        //    echo "id: " . $row["pc.rowid"]. " - Name: " . $row["pc.lastname"]. " " . $row["pc.firstname"]. "<br>";
        //}
        //} else {
        //    echo "0 results";
        //}   
    }
/* FUNCTION TO GET CONTACT  */
    function getContact($userid){
    
        global $conf, $langs;

        $sql = "SELECT pc.rowid, pc.lastname, pc.firstname, pc.email, pc.thm, pc.salary, pc.weeklyhours, pc.job, pc.user_mobile
                FROM " . MAIN_DB_PREFIX . "user as pc WHERE pc.rowid=".$userid ;

        $resql = $this->db->query($sql);
        $contact = array();


        if ($resql) {
            $nb = $this->db->num_rows($resql);

            if ($nb) {

            $i = 0;

                while ($i < $nb)  { 
                    $obj = $this->db->fetch_object($resql);
                    $contact[]=$obj;
                    $i++;
                }    

            $this->db->free($resql);
            } 
        } else {

        $this->error = $this->db->lasterror();
        return -1;
            }

        return $contact;

        //if ($result->num_rows > 0) {
        //    // output data of each row
        //while($row = $result->fetch_assoc()) {
        //    echo "id: " . $row["pc.rowid"]. " - Name: " . $row["pc.lastname"]. " " . $row["pc.firstname"]. "<br>";
        //}
        //} else {
        //    echo "0 results";
        //}   
    }
/* FUNCTION TO GET ALL ZONES */
    function getAllzones(){
        global $conf, $langs;

        $sql = "SELECT rowid, label, price, kilometers FROM " . MAIN_DB_PREFIX . "abcvc_zones";

        $resql = $this->db->query($sql);
        $zone = array();


        if ($resql) {
            $nb = $this->db->num_rows($resql);

            if ($nb) {

            $i = 0;

                while ($i < $nb)  { 
                    $obj = $this->db->fetch_object($resql);
                    $zone[]=$obj;
                    $i++;
                }    

            $this->db->free($resql);
            } 
        } else {

        $this->error = $this->db->lasterror();
        return -1;
            }

        return $zone;
    }
/* FUNCTION TO GET ALL ZONES */
    function getAllsites(){
        global $conf, $langs;

        $sql = "SELECT rowid, label FROM " . MAIN_DB_PREFIX . "abcvc_sites";

        $resql = $this->db->query($sql);
        $site = array();


        if ($resql) {
            $nb = $this->db->num_rows($resql);

            if ($nb) {

            $i = 0;

                while ($i < $nb)  { 
                    $obj = $this->db->fetch_object($resql);
                    $site[]=$obj;
                    $i++;
                }    

            $this->db->free($resql);
            } 
        } else {

        $this->error = $this->db->lasterror();
        return -1;
            }

        return $site;
    }

/*  FUNCTION TO GET ALL NON AFECTED FACTURE FURNISOR */
    function getAllfactfournNONaffected($id_project=0){
        global $conf, $langs;

        //GET ID TASKS FOR ID PROJECT
        $sql = "SELECT pt.rowid
                FROM  llx_abcvc_projet_task AS pt
                WHERE pt.fk_projet=".$id_project;

        $resql = $this->db->query($sql);
        
        $id_tasks = array();
        if ($resql) {
            $nb = $this->db->num_rows($resql);
            if ($nb) {
            $i = 0;
                while ($i < $nb)  { 
                    $obj = $this->db->fetch_object($resql);
                    $id_tasks[]=$obj->rowid;
                    $i++;
                }    
            $this->db->free($resql);
            } 
        } else {
            $this->error = $this->db->lasterror();
            return array();
        }

        $string_tasks = implode(",",$id_tasks);

        //var_dump($string_tasks);


        if( empty($string_tasks) ){

        //IF POST NOT EXIST SHOW ALL FACT FOURN    
            $sql = "SELECT pt.rowid
                    FROM  llx_abcvc_projet_categories AS pt
                    WHERE pt.fk_projet=".$id_project;

            $resql = $this->db->query($sql);
            $id_tasks = array();
            if ($resql) {
                $nb = $this->db->num_rows($resql);
            if ($nb) {
                $i = 0;
                while ($i < $nb)  { 
                    $obj = $this->db->fetch_object($resql);
                    $id_tasks[]=$obj->rowid;
                    $i++;
                }    
            $this->db->free($resql);
                } 
                } else {
            $this->error = $this->db->lasterror();
            return array();
            }

            $string_tasks = implode(",",$id_tasks);
            //AND pt.rowid IN (".$string_tasks.") 
            $sql = "SELECT ff.rowid, (  select count(*) 
                                        FROM llx_abcvc_projet_task AS pt 
                                        WHERE ( FIND_IN_SET(ff.rowid, pt.fact_fourn))) as is_affected
                    FROM llx_facture_fourn AS ff
                    having is_affected = 0";

            $resql = $this->db->query($sql);
            $factfournsnonaffected = array();
            if ($resql) {
                $nb = $this->db->num_rows($resql);
                if ($nb) {
                    $i = 0;
                    while ($i < $nb)  { 
                        $obj = $this->db->fetch_object($resql);
                        $factfournsnonaffected[]=$obj->rowid;
                        $i++;
                    }    
                    $this->db->free($resql);
                } 
            } else {

            $this->error = $this->db->lasterror();
            return array();
        }

            
            return $factfournsnonaffected;

        }else{ 
            // AND pt.rowid IN (".$string_tasks.") 
            $sql = "SELECT ff.rowid, (  select count(*) 
                                        FROM llx_abcvc_projet_task AS pt 
                                        WHERE ( FIND_IN_SET(ff.rowid, pt.fact_fourn))) as is_affected
                    FROM llx_facture_fourn AS ff
                    having is_affected = 0";

            $resql = $this->db->query($sql);

            $factfournsnonaffected = array();
            if ($resql) {
                $nb = $this->db->num_rows($resql);
                if ($nb) {
                $i = 0;
                    while ($i < $nb)  { 
                        $obj = $this->db->fetch_object($resql);
                        $factfournsnonaffected[]=$obj->rowid;
                        $i++;
                    }    
                    $this->db->free($resql);
                } 
            } else {

                $this->error = $this->db->lasterror();
                return array();
            }
            return $factfournsnonaffected;
        }
    }


/*  FUNCTION TO GET ALL FACTURE FURNISOR */
    function getAllfactfourn(){
        global $conf, $langs;

        $sql = "SELECT ff.rowid,ff.ref, ff.fk_soc, ff.datef, ff.total_ttc, ff.fk_statut,s.nom
                FROM llx_facture_fourn AS ff
                INNER JOIN llx_societe as s ON ff.fk_soc=s.rowid";

        $resql = $this->db->query($sql);
        $factfourn = array();


        if ($resql) {
            $nb = $this->db->num_rows($resql);

            if ($nb) {

            $i = 0;

                while ($i < $nb)  { 
                    $obj = $this->db->fetch_object($resql);
                    $factfourn[]=$obj;
                    $i++;
                }    

            $this->db->free($resql);
            } 
        } else {

        $this->error = $this->db->lasterror();
        return -1;
            }

        return $factfourn;
    }

/*  FUNCTION TO GET SUM(FROM FACT_FOURN) IF EXIST FACT_FOURN */
    function getTasksCostsByProject($id_project=0){

        //GET ALL TASKS FROM id_project
        $sql = "SELECT rowid, ref ,label, cost, fact_fourn 
                FROM llx_abcvc_projet_task 
                WHERE fk_projet=".$id_project;
                $resql = $this->db->query($sql);
                $tasks = array();
                if ($resql) {
                    $nb = $this->db->num_rows($resql);
                    if ($nb) {
                    $i = 0;
                        while ($i < $nb)  { 
                            $obj = $this->db->fetch_object($resql);
                            $tasks[$obj->rowid]=$obj;
                            $i++;
                        }    
                    $this->db->free($resql);
                    } 
                } else {
                    $this->error = $this->db->lasterror();
                    return -1;
                }

        foreach ($tasks as $key => $task) {

            //task without facture
            if($task->fact_fourn == ''){
                $task->cost_final = $task->cost;

            //task with facture    
            }else{
                $sql = "SELECT SUM(total_ttc) as cost_final
                        FROM llx_facture_fourn
                        WHERE rowid IN (".$task->fact_fourn.")";
                $resql = $this->db->query($sql);
                //$total = array();
                //var_dump($sql);
                //var_dump($task->fact_fourn);
                if ($resql) {
                    $nb = $this->db->num_rows($resql);
                    if ($nb) {
                    $i = 0;
                        while ($i < $nb)  { 
                            $obj = $this->db->fetch_object($resql);
                            //$total[$task->rowid]=$obj;
                            $i++;
                        }    
                    $this->db->free($resql);
                    } 
                } else {
                    $this->error = $this->db->lasterror();
                    return -1;
                }
                //var_dump($obj);
                $task->cost_final = $obj->cost_final;
            }
        }
        return $tasks;


        /*
           object(stdClass)[173]
              public 'rowid' => string '151' (length=3)
              public 'ref' => string '1.1' (length=3)
              public 'label' => string 'Poste 3' (length=7)
              public 'cost_final' => string '32.00000000' (length=11)
        id task
          - label
          - costs (sum facturi SAU cost direct)
        */
    }

/*  FUNCTION TO GET FINAL FOURN COSTS WITH ID TASK */    
    function getCostByTask($task = NULL){
            //task without facture
            if($task->fact_fourn == ''){
                return 0;//$task->cost;

            //task with facture    
            }else{
                $sql = "SELECT SUM(total_ttc) as cost_final
                        FROM llx_facture_fourn
                        WHERE rowid IN (".$task->fact_fourn.")";
                $resql = $this->db->query($sql);
                if ($resql) {
                    $nb = $this->db->num_rows($resql);
                    if ($nb) {
                    $i = 0;
                        while ($i < $nb)  { 
                            $obj = $this->db->fetch_object($resql);
                            //$total[$task->rowid]=$obj;
                            $i++;
                        }    
                    $this->db->free($resql);
                    } 
                } else {
                    $this->error = $this->db->lasterror();
                    return -1;
                }
                return $obj->cost_final;
            }   
    }

    function getCostByCategorie($task = NULL){
            //task without facture
            if($task->fact_fourn == ''){
                return 0;//$task->cost;

            //task with facture    
            }else{
                $sql = "SELECT SUM(total_ttc) as cost_final
                        FROM llx_facture_fourn
                        WHERE rowid IN (".$task->fact_fourn.")";
                $resql = $this->db->query($sql);
                if ($resql) {
                    $nb = $this->db->num_rows($resql);
                    if ($nb) {
                    $i = 0;
                        while ($i < $nb)  { 
                            $obj = $this->db->fetch_object($resql);
                            //$total[$task->rowid]=$obj;
                            $i++;
                        }    
                    $this->db->free($resql);
                    } 
                } else {
                    $this->error = $this->db->lasterror();
                    return -1;
                }
                return $obj->cost_final;
            }   
    }


    /**
     *    Get array of all contacts for an object
     *
     *    @param    int         $statut     Status of links to get (-1=all)
     *    @param    string      $source     Source of contact: external or thirdparty (llx_socpeople) or internal (llx_user)
     *    @param    int         $list       0:Return array contains all properties, 1:Return array contains just id
     *    @param    string      $code       Filter on this code of contact type ('SHIPPING', 'BILLING', ...)
     *    @return   array                   Array of contacts
     */
    function liste_contact($statut=-1,$source='internal',$list=0,$code='')
    {
        
        global $langs;

        $tab=array();

        $sql = "SELECT ec.rowid, ec.statut as statuslink, ec.fk_socpeople as id, ec.fk_c_type_contact";    // This field contains id of llx_socpeople or id of llx_user
        if ($source == 'internal') $sql.=", '-1' as socid, t.statut as statuscontact";
        if ($source == 'external' || $source == 'thirdparty') $sql.=", t.fk_soc as socid, t.statut as statuscontact";
        $sql.= ", t.civility as civility, t.lastname as lastname, t.firstname, t.email";
        $sql.= ", tc.source, tc.element, tc.code, tc.libelle";
        $sql.= " FROM ".MAIN_DB_PREFIX."c_type_contact tc";
        $sql.= ", ".MAIN_DB_PREFIX."element_contact ec";
        if ($source == 'internal') $sql.=" LEFT JOIN ".MAIN_DB_PREFIX."user t on ec.fk_socpeople = t.rowid";
        if ($source == 'external'|| $source == 'thirdparty') $sql.=" LEFT JOIN ".MAIN_DB_PREFIX."socpeople t on ec.fk_socpeople = t.rowid";
        $sql.= " WHERE ec.element_id =".$this->id;
        $sql.= " AND ec.fk_c_type_contact=tc.rowid";
        $sql.= " AND tc.element='".$this->element."'";
        if ($code) $sql.= " AND tc.code = '".$this->db->escape($code)."'";
        if ($source == 'internal') $sql.= " AND tc.source = 'internal'";
        if ($source == 'external' || $source == 'thirdparty') $sql.= " AND tc.source = 'external'";
        $sql.= " AND tc.active=1";
        if ($statut >= 0) $sql.= " AND ec.statut = '".$statut."'";
        $sql.=" ORDER BY t.lastname ASC";

        //var_dump($sql);
        //exit();

        dol_syslog(get_class($this)."::liste_contact", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $num=$this->db->num_rows($resql);
            $i=0;
            while ($i < $num)
            {
                $obj = $this->db->fetch_object($resql);

                if (! $list)
                {
                    $transkey="TypeContact_".$obj->element."_".$obj->source."_".$obj->code;
                    $libelle_type=($langs->trans($transkey)!=$transkey ? $langs->trans($transkey) : $obj->libelle);
                    $tab[$i]=array('source'=>$obj->source,'socid'=>$obj->socid,'id'=>$obj->id,
                                   'nom'=>$obj->lastname,      // For backward compatibility
                                   'civility'=>$obj->civility, 'lastname'=>$obj->lastname, 'firstname'=>$obj->firstname, 'email'=>$obj->email, 'statuscontact'=>$obj->statuscontact,
                                   'rowid'=>$obj->rowid, 'code'=>$obj->code, 'libelle'=>$libelle_type, 'status'=>$obj->statuslink, 'fk_c_type_contact'=>$obj->fk_c_type_contact);
                }
                else
                {
                    $tab[$i]=$obj->id;
                }

                $i++;
            }

            return $tab;
        }
        else
        {
            $this->error=$this->db->lasterror();
            dol_print_error($this->db);
            return -1;
        }
    }

    /**
     *  Add a link between element $this->element and a contact
     *
     *  @param  int     $fk_socpeople       Id of thirdparty contact (if source = 'external') or id of user (if souce = 'internal') to link
     *  @param  int     $type_contact       Type of contact (code or id). Must be id or code found into table llx_c_type_contact. For example: SALESREPFOLL
     *  @param  string  $source             external=Contact extern (llx_socpeople), internal=Contact intern (llx_user)
     *  @param  int     $notrigger          Disable all triggers
     *  @return int                         <0 if KO, >0 if OK
     */
    function add_contact($fk_socpeople, $type_contact, $source='internal',$notrigger=0)
    {
        global $user,$langs;


        dol_syslog(get_class($this)."::add_contact $fk_socpeople, $type_contact, $source, $notrigger");

        // Check parameters
        if ($fk_socpeople <= 0)
        {
            $langs->load("errors");
            $this->error=$langs->trans("ErrorWrongValueForParameterX","1");
            dol_syslog(get_class($this)."::add_contact ".$this->error,LOG_ERR);
            return -1;
        }
        if (! $type_contact)
        {
            $langs->load("errors");
            $this->error=$langs->trans("ErrorWrongValueForParameterX","2");
            dol_syslog(get_class($this)."::add_contact ".$this->error,LOG_ERR);
            return -2;
        }

        $id_type_contact=0;
        if (is_numeric($type_contact))
        {
            $id_type_contact=$type_contact;
        }
        else
        {
            // On recherche id type_contact
            $sql = "SELECT tc.rowid";
            $sql.= " FROM ".MAIN_DB_PREFIX."c_type_contact as tc";
            $sql.= " WHERE tc.element='".$this->element."'";
            $sql.= " AND tc.source='".$source."'";
            $sql.= " AND tc.code='".$type_contact."' AND tc.active=1";
            //print $sql;
            $resql=$this->db->query($sql);
            if ($resql)
            {
                $obj = $this->db->fetch_object($resql);
                if ($obj) $id_type_contact=$obj->rowid;
            }
        }

        //var_dump($sql);
        //var_dump($id_type_contact);
        //exit();


        if ($id_type_contact == 0)
        {
            $this->error='CODE_NOT_VALID_FOR_THIS_ELEMENT';
            dol_syslog("CODE_NOT_VALID_FOR_THIS_ELEMENT");
            return -3;
        }
            
        $datecreate = dol_now();

        $this->db->begin();
        
        // Insertion dans la base
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."element_contact";
        $sql.= " (element_id, fk_socpeople, datecreate, statut, fk_c_type_contact) ";
        $sql.= " VALUES (".$this->id.", ".$fk_socpeople." , " ;
        $sql.= "'".$this->db->idate($datecreate)."'";
        $sql.= ", 4, ". $id_type_contact;
        $sql.= ")";
        //var_dump($sql);
        //exit();

        $resql=$this->db->query($sql);
        if ($resql)
        {
            if (! $notrigger)
            {
                $result=$this->call_trigger(strtoupper($this->element).'_ADD_CONTACT', $user);
                if ($result < 0)
                {
                    $this->db->rollback();
                    return -1;
                }
            }

            $this->db->commit();
            return 1;
        }
        else
        {
            if ($this->db->errno() == 'DB_ERROR_RECORD_ALREADY_EXISTS')
            {
                $this->error=$this->db->errno();
                $this->db->rollback();
                return -2;
            }
            else
            {
                $this->error=$this->db->error();
                $this->db->rollback();
                return -1;
            }
        }
    }

    /**
     *      Update a link to contact line
     *
     *      @param  int     $rowid              Id of line contact-element
     *      @param  int     $statut             New status of link
     *      @param  int     $type_contact_id    Id of contact type (not modified if 0)
     *      @param  int     $fk_socpeople       Id of soc_people to update (not modified if 0)
     *      @return int                         <0 if KO, >= 0 if OK
     */
    function update_contact($rowid, $statut, $type_contact_id=0, $fk_socpeople=0)
    {
        // Insertion dans la base
        $sql = "UPDATE ".MAIN_DB_PREFIX."element_contact set";
        $sql.= " statut = ".$statut;
        if ($type_contact_id) $sql.= ", fk_c_type_contact = '".$type_contact_id ."'";
        if ($fk_socpeople) $sql.= ", fk_socpeople = '".$fk_socpeople ."'";
        $sql.= " where rowid = ".$rowid;
        $resql=$this->db->query($sql);
        if ($resql)
        {
            return 0;
        }
        else
        {
            $this->error=$this->db->lasterror();
            return -1;
        }
    }

    /**
     *    Delete a link to contact line
     *
     *    @param    int     $rowid          Id of contact link line to delete
     *    @param    int     $notrigger      Disable all triggers
     *    @return   int                     >0 if OK, <0 if KO
     */
    function delete_abcvc_contact($id_people,$id_task,$type_contact,$notrigger=0)
    {
        global $user;


        $this->db->begin();
        $sql = "DELETE FROM llx_element_contact WHERE element_id = ".$id_task." AND fk_c_type_contact =".$type_contact." AND fk_socpeople =".$id_people;
        //$sql = "DELETE FROM ".MAIN_DB_PREFIX."element_contact WHERE fk_socpeople =".$rowid;
        //var_dump($sql);
        //exit();
        dol_syslog(get_class($this)."::delete_contact", LOG_DEBUG);
        if ($this->db->query($sql))
        {
            if (! $notrigger)
            {
                $result=$this->call_trigger(strtoupper($this->element).'_DELETE_CONTACT', $user);
                if ($result < 0) { $this->db->rollback(); return -1; }
            }

            $this->db->commit();
            return 1;
        }
        else
        {
            $this->error=$this->db->lasterror();
            $this->db->rollback();
            return -1;
        }
    }

    //function to retrieve all subtasks and subsubtasks from idtask
    function get_subtask($idtask){

        //Post childs
             $sql = "
                SELECT pl.rowid, pl.ref

                FROM " . MAIN_DB_PREFIX . "abcvc_projet_task as pl
                
                WHERE 
                        pl.fk_task_parent=".$idtask."
                       
                ";              

                dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
                $resql = $this->db->query($sql);
                
                $subposts = array();
                
                if ($resql) {
                    $nb = $this->db->num_rows($resql);
                    if ($nb) {

                        $i = 0;
                        while ($i < $nb)  { 
                            $obj = $this->db->fetch_object($resql);
                            $subposts[]=$obj;
                            $i++;
                        }    

                        $this->db->free($resql);
                    } 
                } else {
                    $this->error = $this->db->lasterror();
                    
                }
                
                //SubPost childs
                foreach ($subposts as $key => $subposte) {
                    
                    $subposte->subsubpostes = array();

                    $sql = "
                    SELECT pl.rowid, pl.ref

                    FROM " . MAIN_DB_PREFIX . "abcvc_projet_task as pl
                    
                    WHERE 
                        pl.fk_task_parent=".$subposte->rowid."
                      
                    ";
                    //var_dump($sql);

                    $resql = $this->db->query($sql);
            
                    if ($resql) {
                        $nb_s = $this->db->num_rows($resql);
                        $nb_subposte += $nb_s;
                        if ($nb_s) {

                            $i = 0;
                            while ($i < $nb_s)  { 

                                $obj = $this->db->fetch_object($resql);
                                //var_dump($obj);

                                $subposte->subsubpostes[]=$obj;
                                $i++;
                            }

                            $this->db->free($resql);
                        } 
                    } else {
                        $this->error = $this->db->lasterror();
                        //return -1;
                    }

                }

                return $subposts;
                // var_dump($subposts);
                // exit();
    }

    function get_projects($idstatut){
        $sql = "SELECT a.rowid, a.ref
        FROM " . MAIN_DB_PREFIX . "abcvc_projet as a
        WHERE a.fk_statut=".$idstatut." ";              
        dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
        $resql = $this->db->query($sql);         
    }

    /**
     * Load time spent into this->weekWorkLoad and this->weekWorkLoadPerTask for all day of a week of project
     *
     * @param   date    $from           filtre
     * @param   date    $to             filtre
     * @param   int     $userid         Time spent by a particular user
     */
    public function getTimeTree($userid,$from='1970-08-01',$to='2300-01-01')
    {
        $error=0;

        /*$sql = "
        SELECT ptt.rowid as taskid, ptt.task_duration, ptt.task_date, ptt.fk_task, ptt.note ,s.label as chantier,  z.label as zone
        FROM llx_abcvc_projet_task_time AS ptt, llx_abcvc_projet_task as pt  , llx_abcvc_projet as p, llx_abcvc_sites as s, llx_abcvc_zones as z
        WHERE ptt.fk_task = pt.rowid";
        if (is_numeric($userid)) $sql.= " AND ptt.fk_user=".$userid." AND pt.fk_projet = p.rowid AND p.fk_sites = s.rowid AND pt.fk_task_parent = 0 AND s.id_zone = z.rowid 
            AND ptt.task_date>='".$from."' AND ptt.task_date<='".$to."' order by ptt.task_date";*/

        $sql = "
        SELECT ptt.rowid as timeid, ptt.task_duration, ptt.task_date, ptt.note,  ptt.task_type,
        z.label as zone, z.price as zone_price, z.gd,
        p.rowid as fk_projet, p.ref as projet_ref , p.title as projet_title,
        ptt.fk_task, pt.ref as task_ref, pt.label as task_title  
        FROM llx_abcvc_projet_task_time AS ptt, llx_abcvc_projet_task as pt, llx_abcvc_projet as p, llx_abcvc_zones as z

        WHERE ptt.fk_task = pt.rowid
        AND ptt.fk_user=".$userid." 
        AND pt.fk_projet = p.rowid 
        AND p.fk_zones = z.rowid
        AND pt.fk_task_parent = 0 
        AND ptt.task_date>='".$from."' AND ptt.task_date<='".$to."' 
        group by ( ptt.task_date )
        order by ptt.task_date";

        //var_dump($sql);
       // exit();

        $resql=$this->db->query($sql);
        $rows = array();
        $timetree = array();
        if ($resql) {
                $num = $this->db->num_rows($resql);
                $i = 0;

                // Loop on each record found, so each couple (project id, task id)
                 while ($i < $num) {
                        /*$obj=$this->db->fetch_object($resql);
                        $day=$this->db->jdate($obj->task_date);
                        $this->weekWorkLoad[$day] +=  $obj->task_duration;
                        $this->weekWorkLoadPerTask[$day][$obj->fk_task] = $obj->task_duration;
                        $this->weekNotePerTask[$day][$obj->fk_task] = $obj->note;*/
                        $obj = $this->db->fetch_object($resql);
                        $rows[]=$obj;
                        $i++;
                }
                $this->db->free($resql);
                //var_dump($rows);
                //return $rows;
                foreach ($rows as $key => $row) {
                    $month = date("n",strtotime($row->task_date));
                    //$week = date("W",strtotime($row->task_date));
                    $day = date("j", strtotime($row->task_date));

                    $timetree[$month][$day][] = $row;
                }
                return $timetree;
                // var_dump($timetree);
        } else {
            $this->error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     * Return salary per hours from DB 
     * 
     * @param   array    $ids_users            
     */
    public function getCostByUser($ids_users,$planned_workload) 
    {
        $sum = 0;
        if(count($ids_users)>0){

            $ids_users = implode(",", $ids_users);
            $sql = " SELECT u.rowid,u.thm FROM llx_user as u WHERE u.rowid IN(".$ids_users.") ";
            
            // exit();
            $resql = $this->db->query($sql);
            $salary = array();
            $final_salary = array();

            $hours = round( 0.00027777777777778 * $planned_workload,2); //1 second is equal to 0.00027777777777778 hour
            if ($resql) {
                $nb = $this->db->num_rows($resql);

                if ($nb) {
                    $i = 0;
                    while ($i < $nb)  { 
                        $obj = $this->db->fetch_object($resql);
                        $salary[]=$obj;
                        $i++;
                    }    
                    $this->db->free($resql);
                    $arrayusers = count($salary); 
                }
                
            } else {
                $this->error = $this->db->lasterror();
                return -1;
            }
            
            for($i = 0; $i < $arrayusers; $i++) {
                $final_salary[] = ($hours / $arrayusers) * $salary[$i]->thm;
                $sum += $final_salary[$i];
                // var_dump($final_salary);
            }
        }

        return $sum;           
        //var_dump($hours);
    }
}
