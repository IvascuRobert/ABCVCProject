<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2016 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005      Marc Bariley / Ocebo <marc@ocebo.com>
 * Copyright (C) 2005-2010 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2013      Cédric Salvador      <csalvador@gpcsolutions.fr>
 * Copyright (C) 2015 	   Claudio Aschieri     <c.aschieri@19.coop>
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
 *	\file       htdocs/projet/list.php
 *	\ingroup    projet
 *	\brief      Page to list projects
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';

require_once DOL_DOCUMENT_ROOT.'/core/class/stats.class.php';

require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/projectstats.class.php';


// BOOTSTRAP 3 + css + js custom
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/abcvc_js_css.php';

$langs->load('projects');
$langs->load('companies');
$langs->load('commercial');

$title = $langs->trans("Projects");

// Security check
$socid = (is_numeric($_GET["socid"]) ? $_GET["socid"] : 0 );
//if ($user->societe_id > 0) $socid = $user->societe_id;    // For external user, no check is done on company because readability is managed by public status of project and assignement.
if ($socid > 0)
{
	$soc = new Societe($db);
	$soc->fetch($socid);
	$title .= ' (<a href="list.php">'.$soc->name.'</a>)';
}
if (!$user->rights->projet->lire) accessforbidden();


$limit = GETPOST("limit")?GETPOST("limit","int"):$conf->liste_limit;
$sortfield = GETPOST("sortfield","alpha");
$sortorder = GETPOST("sortorder");
$page = GETPOST("page");
$page = is_numeric($page) ? $page : 0;
$page = $page == -1 ? 0 : $page;
if (! $sortfield) $sortfield="p.ref";
if (! $sortorder) $sortorder="ASC";
$offset = $limit * $page ;
$pageprev = $page - 1;
$pagenext = $page + 1;

$search_all=GETPOST("search_all");
$search_categ=GETPOST("search_categ",'alpha');
$search_ref=GETPOST("search_ref");
$search_label=GETPOST("search_label");
$search_societe=GETPOST("search_societe");
$search_year=GETPOST("search_year");
$search_all=GETPOST("search_all");
$search_status=GETPOST("search_status",'int');
$search_opp_status=GETPOST("search_opp_status",'alpha');
$search_opp_percent=GETPOST("search_opp_percent",'alpha');
$search_opp_amount=GETPOST("search_opp_amount",'alpha');
$search_budget_amount=GETPOST("search_budget_amount",'alpha');
$search_public=GETPOST("search_public",'int');
$search_user=GETPOST('search_user','int');
$search_sale=GETPOST('search_sale','int');
$optioncss = GETPOST('optioncss','alpha');

$mine = $_REQUEST['mode']=='mine' ? 1 : 0;
if ($mine) { $search_user = $user->id; $mine=0; }

$sday	= GETPOST('sday','int');
$smonth	= GETPOST('smonth','int');
$syear	= GETPOST('syear','int');
$day	= GETPOST('day','int');
$month	= GETPOST('month','int');
$year	= GETPOST('year','int');

if ($search_status == '') $search_status=-1;	// -1 or 1


// Initialize context for list
$contextpage=GETPOST('contextpage','aZ')?GETPOST('contextpage','aZ'):'projectlist';

// Initialize technical object to manage hooks of thirdparties. Note that conf->hooks_modules contains array array
$hookmanager->initHooks(array($contextpage));
$extrafields = new ExtraFields($db);

// fetch optionals attributes and labels
$extralabels = $extrafields->fetch_name_optionals_label('projet');
$search_array_options=$extrafields->getOptionalsFromPost($extralabels,'','search_');

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array(
	'p.ref'=>"Ref",
	'p.title'=>"Label",
	's.nom'=>"ThirdPartyName",
    "p.note_public"=>"NotePublic"
);
if (empty($user->socid)) $fieldstosearchall["p.note_private"]="NotePrivate";

$arrayfields=array(
    'p.ref'=>array('label'=>$langs->trans("Ref"), 'checked'=>1),
    'p.title'=>array('label'=>$langs->trans("Label"), 'checked'=>1),
    's.nom'=>array('label'=>$langs->trans("ThirdParty"), 'checked'=>1, 'enabled'=>$conf->societe->enabled),
    'commercial'=>array('label'=>$langs->trans("SalesRepresentative"), 'checked'=>1),
	'p.dateo'=>array('label'=>$langs->trans("DateStart"), 'checked'=>1, 'position'=>100),
    'p.datee'=>array('label'=>$langs->trans("DateEnd"), 'checked'=>1, 'position'=>101),
    'p.public'=>array('label'=>$langs->trans("Visibility"), 'checked'=>1, 'position'=>102),
    'p.opp_amount'=>array('label'=>$langs->trans("OpportunityAmountShort"), 'checked'=>1, 'enabled'=>($conf->global->PROJECT_USE_OPPORTUNITIES?1:0), 'position'=>103),
    'p.fk_opp_status'=>array('label'=>$langs->trans("OpportunityStatusShort"), 'checked'=>1, 'enabled'=>($conf->global->PROJECT_USE_OPPORTUNITIES?1:0), 'position'=>104),
    'p.opp_percent'=>array('label'=>$langs->trans("OpportunityProbabilityShort"), 'checked'=>1, 'enabled'=>($conf->global->PROJECT_USE_OPPORTUNITIES?1:0), 'position'=>105),
    'p.budget_amount'=>array('label'=>$langs->trans("Budget"), 'checked'=>0, 'position'=>110),
    'p.datec'=>array('label'=>$langs->trans("DateCreationShort"), 'checked'=>0, 'position'=>500),
    'p.tms'=>array('label'=>$langs->trans("DateModificationShort"), 'checked'=>0, 'position'=>500),
    'p.fk_statut'=>array('label'=>$langs->trans("Status"), 'checked'=>1, 'position'=>1000),
);
// Extra fields
if (is_array($extrafields->attribute_label) && count($extrafields->attribute_label))
{
   foreach($extrafields->attribute_label as $key => $val) 
   {
       $arrayfields["ef.".$key]=array('label'=>$extrafields->attribute_label[$key], 'checked'=>$extrafields->attribute_list[$key], 'position'=>$extrafields->attribute_pos[$key], 'enabled'=>$extrafields->attribute_perms[$key]);
   }
}


/*
 * Actions
 */

if (GETPOST('cancel')) { $action='list'; $massaction=''; }
if (! GETPOST('confirmmassaction') && $massaction != 'presend' && $massaction != 'confirm_presend' && $massaction != 'confirm_createbills') { $massaction=''; }

$parameters=array('socid'=>$socid);
$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{
    // Selection of new fields
    include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

    // Purge search criteria
    if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter.x") || GETPOST("button_removefilter")) // Both test are required to be compatible with all browsers
    {
    	$search_all='';
    	$search_categ='';
    	$search_ref="";
    	$search_label="";
    	$search_societe="";
    	$search_year="";
    	$search_status=-1;
    	$search_opp_status=-1;
    	$search_opp_amount='';
    	$search_opp_percent='';
    	$search_budget_amount='';
    	$search_public="";
    	$search_sale="";
    	$search_user='';
    	$sday="";
    	$smonth="";
    	$syear="";
    	$day="";
    	$month="";
    	$year="";
    	$search_array_options=array();
    }
}


/*
 * View
 */

$projectstatic = new ProjectABCVC($db);
$socstatic = new Societe($db);
$form = new Form($db);
$formother = new FormOther($db);
$formproject = new FormProjets($db);

$title=$langs->trans("Projects");
if ($search_user == $user->id) $title=$langs->trans("MyProjects");

// Get list of project id allowed to user (in a string list separated by coma)
$projectsListId='';
if (! $user->rights->projet->all->lire) $projectsListId = $projectstatic->getProjectsAuthorizedForUser($user,0,1,$socid);

// Get id of types of contacts for projects (This list never contains a lot of elements)
$listofprojectcontacttype=array();
$sql = "SELECT ctc.rowid, ctc.code FROM ".MAIN_DB_PREFIX."c_type_contact as ctc";
$sql.= " WHERE ctc.element = '" . $projectstatic->element . "'";
$sql.= " AND ctc.source = 'internal'";
$resql = $db->query($sql);
if ($resql)
{
    while($obj = $db->fetch_object($resql))
    {
        $listofprojectcontacttype[$obj->rowid]=$obj->code;
    }
}
else dol_print_error($db);
if (count($listofprojectcontacttype) == 0) $listofprojectcontacttype[0]='0';    // To avoid sql syntax error if not found


$distinct='DISTINCT';   // We add distinct until we are added a protection to be sure a contact of a project and task is only once.
$sql = "SELECT ".$distinct." p.rowid as projectid, p.ref, p.title, p.fk_statut, p.fk_opp_status, p.public, p.fk_user_creat";
$sql.= ", p.datec as date_creation, p.dateo as date_start, p.datee as date_end, p.opp_amount, p.opp_percent, p.tms as date_update, p.budget_amount";
$sql.= ", s.nom as name, s.rowid as socid";
$sql.= ", cls.code as opp_status_code";
// We'll need these fields in order to filter by categ
if ($search_categ) $sql .= ", cs.fk_categorie, cs.fk_project";
// Add fields for extrafields
foreach ($extrafields->attribute_label as $key => $val) $sql.=($extrafields->attribute_type[$key] != 'separate' ? ",ef.".$key.' as options_'.$key : '');
// Add fields from hooks
$parameters=array();
$reshook=$hookmanager->executeHooks('printFieldListSelect',$parameters);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;
$sql.= " FROM ".MAIN_DB_PREFIX."abcvc_projet as p";
if (is_array($extrafields->attribute_label) && count($extrafields->attribute_label)) $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."abcvc_projet_extrafields as ef on (p.rowid = ef.fk_object)";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s on p.fk_soc = s.rowid";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_lead_status as cls on p.fk_opp_status = cls.rowid";
// We'll need this table joined to the select in order to filter by categ
if (! empty($search_categ)) $sql.= ' LEFT JOIN '.MAIN_DB_PREFIX."categorie_project as cs ON p.rowid = cs.fk_project"; // We'll need this table joined to the select in order to filter by categ
// We'll need this table joined to the select in order to filter by sale
// For external user, no check is done on company permission because readability is managed by public status of project and assignement.
//if ($search_sale > 0 || (! $user->rights->societe->client->voir && ! $socid)) $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON sc.fk_soc = s.rowid";
if ($search_sale > 0) $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON sc.fk_soc = s.rowid";
if ($search_user > 0)
{
	$sql.=", ".MAIN_DB_PREFIX."element_contact as ecp";
}
$sql.= " WHERE p.entity IN (".getEntity('project',1).')';
if (! $user->rights->projet->all->lire) $sql.= " AND p.rowid IN (".$projectsListId.")";     // public and assigned to, or restricted to company for external users
// No need to check company, as filtering of projects must be done by getProjectsAuthorizedForUser
if ($socid) $sql.= " AND (p.fk_soc IS NULL OR p.fk_soc = 0 OR p.fk_soc = ".$socid.")";
if ($search_categ > 0)    $sql.= " AND cs.fk_categorie = ".$db->escape($search_categ);
if ($search_categ == -2)  $sql.= " AND cs.fk_categorie IS NULL";
if ($search_ref) $sql .= natural_search('p.ref', $search_ref);
if ($search_label) $sql .= natural_search('p.title', $search_label);
if ($search_societe) $sql .= natural_search('s.nom', $search_societe);
if ($search_opp_amount) $sql .= natural_search('p.opp_amount', $search_opp_amount, 1);
if ($search_opp_percent) $sql .= natural_search('p.opp_percent', $search_opp_percent, 1);
if ($smonth > 0)
{
    if ($syear > 0 && empty($sday))
    	$sql.= " AND p.dateo BETWEEN '".$db->idate(dol_get_first_day($syear,$smonth,false))."' AND '".$db->idate(dol_get_last_day($syear,$smonth,false))."'";
    else if ($syear > 0 && ! empty($sday))
    	$sql.= " AND p.dateo BETWEEN '".$db->idate(dol_mktime(0, 0, 0, $smonth, $sday, $syear))."' AND '".$db->idate(dol_mktime(23, 59, 59, $smonth, $sday, $syear))."'";
    else
    	$sql.= " AND date_format(p.dateo, '%m') = '".$smonth."'";
}
else if ($syear > 0)
{
    $sql.= " AND p.dateo BETWEEN '".$db->idate(dol_get_first_day($syear,1,false))."' AND '".$db->idate(dol_get_last_day($syear,12,false))."'";
}
if ($month > 0)
{
    if ($year > 0 && empty($day))
    	$sql.= " AND p.datee BETWEEN '".$db->idate(dol_get_first_day($year,$month,false))."' AND '".$db->idate(dol_get_last_day($year,$month,false))."'";
    else if ($year > 0 && ! empty($day))
    	$sql.= " AND p.datee BETWEEN '".$db->idate(dol_mktime(0, 0, 0, $month, $day, $year))."' AND '".$db->idate(dol_mktime(23, 59, 59, $month, $day, $year))."'";
    else
    	$sql.= " AND date_format(p.datee, '%m') = '".$month."'";
}
else if ($year > 0)
{
    $sql.= " AND p.datee BETWEEN '".$db->idate(dol_get_first_day($year,1,false))."' AND '".$db->idate(dol_get_last_day($year,12,false))."'";
}
if ($search_all) $sql .= natural_search(array_keys($fieldstosearchall), $search_all);
if ($search_status >= 0) 
{
    if ($search_status == 99) $sql .= " AND p.fk_statut <> 2";
    else $sql .= " AND p.fk_statut = ".$db->escape($search_status);
}
if ($search_opp_status) 
{
    if (is_numeric($search_opp_status) && $search_opp_status > 0) $sql .= " AND p.fk_opp_status = ".$db->escape($search_opp_status);
    if ($search_opp_status == 'all') $sql .= " AND p.fk_opp_status IS NOT NULL";
    if ($search_opp_status == 'openedopp') $sql .= " AND p.fk_opp_status IS NOT NULL AND p.fk_opp_status NOT IN (SELECT rowid FROM ".MAIN_DB_PREFIX."c_lead_status WHERE code IN ('WON','LOST'))";
    if ($search_opp_status == 'none') $sql .= " AND p.fk_opp_status IS NULL";
}
if ($search_public!='') $sql .= " AND p.public = ".$db->escape($search_public);
if ($search_sale > 0) $sql.= " AND sc.fk_user = " .$search_sale;
// For external user, no check is done on company permission because readability is managed by public status of project and assignement.
//if (! $user->rights->societe->client->voir && ! $socid) $sql.= " AND ((s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id.") OR (s.rowid IS NULL))";
if ($search_user > 0) $sql.= " AND ecp.fk_c_type_contact IN (".join(',',array_keys($listofprojectcontacttype)).") AND ecp.element_id = p.rowid AND ecp.fk_socpeople = ".$search_user; 
if ($search_opp_amount != '') $sql .= natural_search('p.opp_amount', $search_opp_amount, 1);
if ($search_budget_amount != '') $sql .= natural_search('p.budget_amount', $search_budget_amount, 1);
// Add where from extra fields
foreach ($search_array_options as $key => $val)
{
    $crit=$val;
    $tmpkey=preg_replace('/search_options_/','',$key);
    $typ=$extrafields->attribute_type[$tmpkey];
    $mode=0;
    if (in_array($typ, array('int','double'))) $mode=1;    // Search on a numeric
    if ($val && ( ($crit != '' && ! in_array($typ, array('select'))) || ! empty($crit))) 
    {
        $sql .= natural_search('ef.'.$tmpkey, $crit, $mode);
    }
}
// Add where from hooks
$parameters=array();
$reshook=$hookmanager->executeHooks('printFieldListWhere',$parameters);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;
$sql.= $db->order($sortfield,$sortorder);

$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST))
{
    $result = $db->query($sql);
    $nbtotalofrecords = $db->num_rows($result);
}

$sql.= $db->plimit($limit + 1,$offset);

//print $sql;
dol_syslog("list allowed project", LOG_DEBUG);
//print $sql;
$resql = $db->query($sql);
if (! $resql)
{
    dol_print_error($db);
    exit;
}

$var=true;
$num = $db->num_rows($resql);

if ($num == 1 && ! empty($conf->global->MAIN_SEARCH_DIRECT_OPEN_IF_ONLY_ONE) && $search_all)
{
    $obj = $db->fetch_object($resql);
    $id = $obj->projectid;
    header("Location: ".DOL_URL_ROOT.SUPP_PATH.'/projet/card.php?id='.$id);
    exit;
}

llxHeader("",$title,"EN:Module_Projects|FR:Module_Projets|ES:M&oacute;dulo_Proyectos");

$param='';
if (! empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param.='&contextpage='.$contextpage;
if ($limit > 0 && $limit != $conf->liste_limit) $param.='&limit='.$limit;
if ($sday)              		$param.='&sday='.$day;
if ($smonth)              		$param.='&smonth='.$smonth;
if ($syear)               		$param.='&syear=' .$syear;
if ($day)               		$param.='&day='.$day;
if ($month)              		$param.='&month='.$month;
if ($year)               		$param.='&year=' .$year;
if ($socid)				        $param.='&socid='.$socid;
if ($search_all != '') 			$param.='&search_all='.$search_all;
if ($search_ref != '') 			$param.='&search_ref='.$search_ref;
if ($search_label != '') 		$param.='&search_label='.$search_label;
if ($search_societe != '') 		$param.='&search_societe='.$search_societe;
if ($search_status >= 0) 		$param.='&search_status='.$search_status;
if ((is_numeric($search_opp_status) && $search_opp_status >= 0) || in_array($search_opp_status, array('all','openedopp','none'))) 	$param.='&search_opp_status='.urlencode($search_opp_status);
if ((is_numeric($search_opp_percent) && $search_opp_percent >= 0) || in_array($search_opp_percent, array('all','openedopp','none'))) 	$param.='&search_opp_percent='.urlencode($search_opp_percent);
if ($search_public != '') 		$param.='&search_public='.$search_public;
if ($search_user > 0)    		$param.='&search_user='.$search_user;
if ($search_sale > 0)    		$param.='&search_sale='.$search_sale;
if ($search_opp_amount != '')    $param.='&search_opp_amount='.$search_opp_amount;
if ($search_budget_amount != '') $param.='&search_budget_amount='.$search_budget_amount;
if ($optioncss != '') $param.='&optioncss='.$optioncss;
// Add $param from extra fields
foreach ($search_array_options as $key => $val)
{
    $crit=$val;
    $tmpkey=preg_replace('/search_options_/','',$key);
    if ($val != '') $param.='&search_options_'.$tmpkey.'='.urlencode($val);
}

$text=$langs->trans("StatsProjects");
if ($search_user == $user->id) $text=$langs->trans('MyProjects');

//------------------------------------------------------------------------------------------------------------------------------------------------
?>
<form method="POST" id="searchFormList" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
    <div class="panel panel-primary filterable">
            <div class="panel-heading">
                
                            
                                <?php
                                if ($optioncss != '') 
                                ?>
                                <input type="hidden" name="optioncss" value="<?php echo $optioncss; ?>">
                                <input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">
                                <input type="hidden" name="formfilteraction" id="formfilteraction" value="list">
                                <input type="hidden" name="action" value="list">
                                <input type="hidden" name="sortfield" value="<?php echo $sortfield; ?>">
                                <input type="hidden" name="sortorder" value="<?php echo $sortorder; ?>">
                                <input type="hidden" name="type" value="<?php echo $type; ?>">
                                <input type="hidden" name="contextpage" value="<?php echo $contextpage; ?>">

                                <?php
                                print_barre_liste($text, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, "", $num, $nbtotalofrecords, 'title_project', 0, '', '', $limit);
                                
                                // Show description of content
                                if ($search_user == $user->id) 
                                    {
                                    echo $langs->trans("MyProjectsDesc");
                                    ?>
                                    <br><br>
                                    <?php
                                    }
                                else
                                    {
                                        if ($user->rights->projet->all->lire && ! $socid) 
                                        {
                                        echo $langs->trans("ProjectsDesc ");
                                        ?>
                                        <br><br>
                                        <?php
                                        }
                                        else 
                                        {
                                            echo $langs->trans("ProjectsPublicDesc"); ?>
                                            <br><br>
                                            <?php
                                        }
                                    }


                                    if ($search_all)
                                    {
                                        foreach($fieldstosearchall as $key => $val) $fieldstosearchall[$key]=$langs->trans($val);
                                        print $langs->trans("FilterOnInto", $search_all) . join(', ',$fieldstosearchall);
                                    }
                                /*
                                ?>
                                <div class="container-fluid">
                                    <div class="row">
                                        <div class="pull-left">
                                            <a href="/abcvc/projet/card.php?leftmenu=abcvc&action=create" style="color:black" class="btn btn-default btn-sm"><span class="glyphicon glyphicon-plus"></span>New project</a>
                                        </div>

                                       <!--  <div class="pull-left">
                                            <button class="btn btn-default btn-sm btn-filter"><span class="glyphicon glyphicon-filter"></span>Filter</button>
                                        </div> -->
                                    </div>  
                                </div>  
                                */ ?>
            </div>

                <table class="table table-responsive">
                    
                    <tbody>
                        <?php 
                           // Filter on categories
                                    if (! empty($conf->categorie->enabled))
                                    {
                                        require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
                                        $moreforfilter.='<div class="divsearchfield">';
                                        $moreforfilter.=$langs->trans('Categories'). ': ';
                                        $moreforfilter.=$formother->select_categories('project',$search_categ,'search_categ',1);
                                        $moreforfilter.='</div>';
                                    }

                                    // If the user can view user other than himself
                                    $moreforfilter.='<div class="divsearchfield">';
                                    $moreforfilter.=$langs->trans('ProjectsWithThisUserAsContact'). ': ';
                                    $includeonly='';
                                    if (empty($user->rights->user->user->lire)) $includeonly=array($user->id);
                                    $moreforfilter.=$form->select_dolusers($search_user, 'search_user', 1, '', 0, $includeonly, '', 0, 0, 0, '', 0, '', 'maxwidth300');
                                    $moreforfilter.='</div>';

                                    // If the user can view thirdparties other than his'
                                    if ($user->rights->societe->client->voir || $socid)
                                    {
                                        $langs->load("commercial");
                                        $moreforfilter.='<div class="divsearchfield">';
                                        $moreforfilter.=$langs->trans('ThirdPartiesOfSaleRepresentative'). ': ';
                                        $moreforfilter.=$formother->select_salesrepresentatives($search_sale, 'search_sale', $user, 0, 1, 'maxwidth300');
                                        $moreforfilter.='</div>';
                                    }

                                    if (! empty($moreforfilter))
                                    {
                                        ?>
                                        <div class="liste_titre liste_titre_bydiv centpercent">
                                        <?php
                                        echo $moreforfilter;
                                        $parameters=array();
                                        $reshook=$hookmanager->executeHooks('printFieldPreListTitle',$parameters);    // Note that $action and $object may have been modified by hook
                                        echo $hookmanager->resPrint;
                                        ?>
                                        </div>
                                        <?php
                                    }

                                    $varpage=empty($contextpage)?$_SERVER["PHP_SELF"]:$contextpage;
                                    $selectedfields=$form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage);  // This also change content of $arrayfields

                                    ?>
                                    <div class="div-table-responsive">
                                    <table class="tagtable liste<?php echo ($moreforfilter?" listwithfilterbefore":""); ?>">
                                        <?php 
                                        echo "\n";
                                        ?>
                                        <tr class="liste_titre">
                                            <?php
                                            if (! empty($arrayfields['p.ref']['checked']))           
                                                print_liste_field_titre($arrayfields['p.ref']['label'],$_SERVER["PHP_SELF"],"p.ref","",$param,"",$sortfield,$sortorder);
                                            if (! empty($arrayfields['p.title']['checked']))         
                                                print_liste_field_titre($arrayfields['p.title']['label'],$_SERVER["PHP_SELF"],"p.title","",$param,"",$sortfield,$sortorder);
                                            if (! empty($arrayfields['s.nom']['checked']))           
                                                print_liste_field_titre($arrayfields['s.nom']['label'],$_SERVER["PHP_SELF"],"s.nom","",$param,"",$sortfield,$sortorder);
                                            if (! empty($arrayfields['commercial']['checked']))      
                                                print_liste_field_titre($arrayfields['commercial']['label'],$_SERVER["PHP_SELF"],"","",$param,"",$sortfield,$sortorder);
                                            if (! empty($arrayfields['p.dateo']['checked']))         
                                                print_liste_field_titre($arrayfields['p.dateo']['label'],$_SERVER["PHP_SELF"],"p.dateo","",$param,'align="center"',$sortfield,$sortorder);
                                            if (! empty($arrayfields['p.datee']['checked']))         
                                                print_liste_field_titre($arrayfields['p.datee']['label'],$_SERVER["PHP_SELF"],"p.datee","",$param,'align="center"',$sortfield,$sortorder);
                                            if (! empty($arrayfields['p.public']['checked']))        
                                                print_liste_field_titre($arrayfields['p.public']['label'],$_SERVER["PHP_SELF"],"p.public","",$param,"",$sortfield,$sortorder);
                                            if (! empty($arrayfields['p.opp_amount']['checked']))    
                                                print_liste_field_titre($arrayfields['p.opp_amount']['label'],$_SERVER["PHP_SELF"],'p.opp_amount',"",$param,'align="right"',$sortfield,$sortorder);
                                            if (! empty($arrayfields['p.fk_opp_status']['checked'])) 
                                                print_liste_field_titre($arrayfields['p.fk_opp_status']['label'],$_SERVER["PHP_SELF"],'p.fk_opp_status',"",$param,'align="center"',$sortfield,$sortorder);
                                            if (! empty($arrayfields['p.opp_percent']['checked']))   
                                                print_liste_field_titre($arrayfields['p.opp_percent']['label'],$_SERVER["PHP_SELF"],'p.opp_percent',"",$param,'align="right"',$sortfield,$sortorder);
                                            if (! empty($arrayfields['p.budget_amount']['checked'])) 
                                                print_liste_field_titre($arrayfields['p.budget_amount']['label'],$_SERVER["PHP_SELF"],'p.budget_amount',"",$param,'align="right"',$sortfield,$sortorder);
                                            // Extra fields
                                            if (is_array($extrafields->attribute_label) && count($extrafields->attribute_label))
                                            {
                                               foreach($extrafields->attribute_label as $key => $val) 
                                               {
                                                   if (! empty($arrayfields["ef.".$key]['checked'])) 
                                                   {
                                                        $align=$extrafields->getAlignFlag($key);
                                                        print_liste_field_titre($extralabels[$key],$_SERVER["PHP_SELF"],"ef.".$key,"",$param,($align?'align="'.$align.'"':''),$sortfield,$sortorder);
                                                   }
                                               }
                                            }
                                            // Hook fields
                                            $parameters=array('arrayfields'=>$arrayfields);
                                            $reshook=$hookmanager->executeHooks('printFieldListTitle',$parameters);    // Note that $action and $object may have been modified by hook
                                            print $hookmanager->resPrint;
                                            if (! empty($arrayfields['p.datec']['checked']))  print_liste_field_titre($arrayfields['p.datec']['label'],$_SERVER["PHP_SELF"],"p.datec","",$param,'align="center" class="nowrap"',$sortfield,$sortorder);
                                            if (! empty($arrayfields['p.tms']['checked']))    print_liste_field_titre($arrayfields['p.tms']['label'],$_SERVER["PHP_SELF"],"p.tms","",$param,'align="center" class="nowrap"',$sortfield,$sortorder);
                                            if (! empty($arrayfields['p.fk_statut']['checked'])) print_liste_field_titre($arrayfields['p.fk_statut']['label'],$_SERVER["PHP_SELF"],"p.fk_statut","",$param,'align="right"',$sortfield,$sortorder);
                                            print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"],"",'','','align="right"',$sortfield,$sortorder,'maxwidthsearch ');
                                            ?>
                                        </tr>
                                        <?php
                                        echo "\n";

                                        ?>
                                        <tr class="liste_titre">
                                        <?php
                                        if (! empty($arrayfields['p.ref']['checked']))
                                        {
                                            ?>
                                            <td class="liste_titre">
                                            <input type="text" class="flat" name="search_ref" value="<?php echo $search_ref; ?>" size="6">
                                            </td>
                                            <?php
                                        }
                                        if (! empty($arrayfields['p.title']['checked']))
                                        {
                                            ?>
                                            <td class="liste_titre">
                                            <input type="text" class="flat" name="search_label" size="8" value="<?php echo $search_label; ?>">
                                            </td>
                                            <?php
                                        }
                                        if (! empty($arrayfields['s.nom']['checked']))
                                        {
                                            ?>
                                            <td class="liste_titre">
                                            <input type="text" class="flat" name="search_societe" size="8" value="<?php echo $search_societe; ?>">
                                            </td>
                                            <?php
                                        }
                                        // Sale representative
                                        if (! empty($arrayfields['commercial']['checked']))
                                        {
                                            ?>
                                            <td class="liste_titre">&nbsp;</td>
                                            <?php
                                        }
                                        // Start date
                                        if (! empty($arrayfields['p.dateo']['checked']))
                                        {
                                            ?>
                                            <td class="liste_titre center">
                                            <?php
                                            if (! empty($conf->global->MAIN_LIST_FILTER_ON_DAY)) 
                                            ?>
                                            <input class="flat" type="text" size="1" maxlength="2" name="sday" value="<?php echo $sday; ?>">
                                            <input class="flat" type="text" size="1" maxlength="2" name="smonth" value="<?php echo $smonth; ?>">
                                            <?php
                                            $formother->select_year($syear?$syear:-1,'syear',1, 20, 5);
                                            ?>
                                            </td>
                                            <?php
                                        }
                                        // End date
                                        if (! empty($arrayfields['p.datee']['checked']))
                                        {
                                            ?>
                                            <td class="liste_titre center">
                                            <?php
                                            if (! empty($conf->global->MAIN_LIST_FILTER_ON_DAY)) 
                                            ?>
                                            <input class="flat" type="text" size="1" maxlength="2" name="day" value="<?php echo $day; ?>">
                                            <input class="flat" type="text" size="1" maxlength="2" name="month" value="<?php echo $month; ?>">
                                            <?php
                                            $formother->select_year($year?$year:-1,'year',1, 20, 5);
                                            ?>
                                            </td>
                                            <?php
                                        }
                                        if (! empty($arrayfields['p.public']['checked']))
                                        {
                                            ?>
                                            <td class="liste_titre">
                                            <?php
                                            $array=array(''=>'',0 => $langs->trans("PrivateProject"),1 => $langs->trans("SharedProject"));
                                            echo $form->selectarray('search_public',$array,$search_public);
                                            ?>
                                            </td>
                                            <?php
                                        }
                                        if (! empty($arrayfields['p.opp_amount']['checked']))
                                        {
                                            ?>
                                            <td class="liste_titre nowrap right">
                                            <input type="text" class="flat" name="search_opp_amount" size="3" value="<?php echo $search_opp_amount; ?>">
                                            </td>
                                            <?php
                                        }
                                        if (! empty($arrayfields['p.fk_opp_status']['checked']))
                                        {
                                            ?>
                                            <td class="liste_titre nowrap center">
                                            <?php
                                            echo $formproject->selectOpportunityStatus('search_opp_status',$search_opp_status,1,1,1);
                                            ?>
                                            </td>
                                            <?php
                                        }
                                        if (! empty($arrayfields['p.opp_percent']['checked']))
                                        {
                                            ?>
                                            <td class="liste_titre nowrap right">
                                            <input type="text" class="flat" name="search_opp_percent" size="2" value="<?php echo $search_opp_percent; ?>">
                                            </td>
                                            <?php
                                        }
                                        if (! empty($arrayfields['p.budget_amount']['checked']))
                                        {
                                            ?>
                                            <td class="liste_titre nowrap" align="right">
                                            <input type="text" class="flat" name="search_budget_amount" size="4" value="<?php echo $search_budget_amount; ?>">
                                            </td>
                                            <?php
                                        }
                                        // Extra fields
                                        if (is_array($extrafields->attribute_label) && count($extrafields->attribute_label))
                                        {
                                            foreach($extrafields->attribute_label as $key => $val)
                                            {
                                                if (! empty($arrayfields["ef.".$key]['checked']))
                                                {
                                                    $align=$extrafields->getAlignFlag($key);
                                                    $typeofextrafield=$extrafields->attribute_type[$key];
                                                    ?>
                                                    <td class="liste_titre<?php echo ($align?" $align":""); ?>">
                                                    <?php
                                                    if (in_array($typeofextrafield, array('varchar', 'int', 'double', 'select')))
                                                    {
                                                        $crit=$val;
                                                        $tmpkey=preg_replace('/search_options_/','',$key);
                                                        $searchclass='';
                                                        if (in_array($typeofextrafield, array('varchar', 'select'))) $searchclass='searchstring';
                                                        if (in_array($typeofextrafield, array('int', 'double'))) $searchclass='searchnum';
                                                        ?>
                                                        <input class="flat<?php echo ($searchclass?" $searchclass":""); ?>" size="4" type="text" name="search_options_<?php echo $tmpkey; ?>" value="<?php echo dol_escape_htmltag($search_array_options["search_options_".$tmpkey]); ?>">
                                                        <?php
                                                    }
                                                    ?>
                                                    </td>
                                                    <?php
                                                }
                                            }
                                        }
                                        // Fields from hook
                                        $parameters=array('arrayfields'=>$arrayfields);
                                        $reshook=$hookmanager->executeHooks('printFieldListOption',$parameters);    // Note that $action and $object may have been modified by hook
                                        echo $hookmanager->resPrint;
                                        if (! empty($arrayfields['p.datec']['checked']))
                                        {
                                            // Date creation
                                            ?>
                                            <td class="liste_titre">
                                            </td>
                                            <?php
                                        }
                                        if (! empty($arrayfields['p.tms']['checked']))
                                        {
                                            // Date modification
                                            ?>
                                            <td class="liste_titre">
                                            </td>
                                            <?php
                                        }
                                        if (! empty($arrayfields['p.fk_statut']['checked']))
                                        {
                                            ?>
                                            <td class="liste_titre nowrap" align="right">
                                            <?php
                                            $arrayofstatus = array();
                                            foreach($projectstatic->statuts_short as $key => $val) $arrayofstatus[$key]=$langs->trans($val);
                                            $arrayofstatus['99']=$langs->trans("NotClosed").' ('.$langs->trans('Draft').'+'.$langs->trans('Opened').')';
                                            echo $form->selectarray('search_status', $arrayofstatus, $search_status, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100');
                                            ?>
                                            </td>
                                            <?php
                                        }
                                        // Action column
                                        ?>
                                        <td class="liste_titre" align="right">
                                        <?php
                                        $searchpitco=$form->showFilterAndCheckAddButtons(0);
                                        echo $searchpitco;
                                        ?>
                                        </td>

                                        </tr>
                                        <?php
                                        echo "\n";

                                        $i=0;
                                        $var=true;
                                        $totalarray=array();
                                        while ($i < min($num,$limit))
                                        {
                                            $obj = $db->fetch_object($resql);

                                            $projectstatic->id = $obj->projectid;
                                            $projectstatic->user_author_id = $obj->fk_user_creat;
                                            $projectstatic->public = $obj->public;
                                            $projectstatic->ref = $obj->ref;
                                            $projectstatic->datee = $db->jdate($obj->date_end);
                                            $projectstatic->statut = $obj->fk_statut;
                                            $projectstatic->opp_status = $obj->fk_opp_status;
                                             
                                            $userAccess = $projectstatic->restrictedProjectArea($user);    // why this ?
                                            if ($userAccess >= 0)
                                            {
                                                $var=!$var;
                                                ?>
                                                <tr <?php echo $bc[$var]; ?> >
                                                <?php

                                                // Project url
                                                if (! empty($arrayfields['p.ref']['checked']))
                                                {
                                                    ?>
                                                    <td class="nowrap">
                                                    <?php
                                                    echo $projectstatic->getNomUrl(1);
                                                    if ($projectstatic->hasDelay()) print img_warning($langs->trans('Late'));
                                                    ?>
                                                    </td>
                                                    <?php
                                                    if (! $i) $totalarray['nbfield']++;
                                                }
                                                // Title
                                                if (! empty($arrayfields['p.title']['checked']))
                                                {
                                                    ?>
                                                    <td>
                                                    <?php
                                                    echo dol_trunc($obj->title,80);
                                                    ?>
                                                    </td>
                                                    <?php
                                                    if (! $i) $totalarray['nbfield']++;
                                                }
                                                // Company
                                                if (! empty($arrayfields['s.nom']['checked']))
                                                {
                                                    ?>
                                                    <td>
                                                    <?php
                                                    if ($obj->socid)
                                                    {
                                                        $socstatic->id=$obj->socid;
                                                        $socstatic->name=$obj->name;
                                                        echo $socstatic->getNomUrl(1);
                                                    }
                                                    else
                                                    {
                                                        ?>&nbsp;<?php
                                                    }
                                                    ?>
                                                        
                                                    </td>
                                                    <?php
                                                    if (! $i) $totalarray['nbfield']++;
                                                }
                                                // Sales Representatives
                                                if (! empty($arrayfields['commercial']['checked']))
                                                {
                                                    ?>
                                                    <td>
                                                    <?php
                                                    if ($obj->socid)
                                                    {
                                                        $socstatic->id=$obj->socid;
                                                        $socstatic->name=$obj->name;
                                                        $listsalesrepresentatives=$socstatic->getSalesRepresentatives($user);
                                                        $nbofsalesrepresentative=count($listsalesrepresentatives);
                                                        if ($nbofsalesrepresentative > 3)   // We print only number
                                                        {
                                                            ?>
                                                            <a href="'.DOL_URL_ROOT.'/societe/commerciaux.php?socid='.$socstatic->id.'">
                                                            <?php
                                                            echo $nbofsalesrepresentative;
                                                            ?>
                                                                
                                                            </a>
                                                            <?php
                                                        }
                                                        else if ($nbofsalesrepresentative > 0)
                                                        {
                                                            $userstatic=new User($db);
                                                            $j=0;
                                                            foreach($listsalesrepresentatives as $val)
                                                            {
                                                                $userstatic->id=$val['id'];
                                                                $userstatic->lastname=$val['lastname'];
                                                                $userstatic->firstname=$val['firstname'];
                                                                $userstatic->email=$val['email'];
                                                                $userstatic->statut=$val['statut'];
                                                                $userstatic->entity=$val['entity'];
                                                                echo $userstatic->getNomUrl(1);
                                                                $j++;
                                                                if ($j < $nbofsalesrepresentative) echo ', ';
                                                            }
                                                        }
                                                        //else print $langs->trans("NoSalesRepresentativeAffected");
                                                    }
                                                    else
                                                    {
                                                        ?>
                                                        &nbsp
                                                        <?php
                                                    }
                                                    ?>
                                                    </td>
                                                    <?php
                                                    if (! $i) $totalarray['nbfield']++;
                                                }
                                                // Date start
                                                if (! empty($arrayfields['p.dateo']['checked']))
                                                {
                                                    ?>
                                                    <td class="center">
                                                    <?php
                                                    echo dol_print_date($db->jdate($obj->date_start),'day');
                                                    ?>
                                                    </td>
                                                    <?php
                                                    if (! $i) $totalarray['nbfield']++;
                                                }
                                                // Date end
                                                if (! empty($arrayfields['p.datee']['checked']))
                                                {
                                                    ?>
                                                    <td class="center">
                                                    <?php
                                                    echo dol_print_date($db->jdate($obj->date_end),'day');
                                                    ?>
                                                    </td>
                                                    <?php
                                                    if (! $i) $totalarray['nbfield']++;
                                                }
                                                // Visibility
                                                if (! empty($arrayfields['p.public']['checked']))
                                                {
                                                    ?>
                                                    <td align="left">
                                                    <?php
                                                    if ($obj->public) echo $langs->trans('SharedProject');
                                                    else echo $langs->trans('PrivateProject');
                                                    ?>
                                                        
                                                    </td>
                                                    <?php
                                                    if (! $i) $totalarray['nbfield']++;
                                                }
                                                // Amount
                                                if (! empty($arrayfields['p.opp_amount']['checked']))
                                                {
                                                    ?>
                                                    <td align="right">
                                                    <?php
                                                    if ($obj->opp_status_code) 
                                                    {
                                                        echo price($obj->opp_amount, 1, '', 1, -1, -1, '');
                                                        $totalarray['totalopp'] += $obj->opp_amount;
                                                    }
                                                    ?>
                                                    </td>
                                                    <?php
                                                    if (! $i) $totalarray['nbfield']++;
                                                    if (! $i) $totalarray['totaloppfield']=$totalarray['nbfield'];
                                                }
                                                if (! empty($arrayfields['p.fk_opp_status']['checked']))
                                                {
                                                    ?>
                                                    <td align="middle">
                                                    <?php
                                                    if ($obj->opp_status_code) echo $langs->trans("OppStatusShort".$obj->opp_status_code);
                                                    ?>
                                                    </td>
                                                    <?php
                                                    if (! $i) $totalarray['nbfield']++;
                                                }
                                                if (! empty($arrayfields['p.opp_percent']['checked']))
                                                {
                                                    ?>
                                                    <td align="right">
                                                    <?php
                                                    if ($obj->opp_percent) echo price($obj->opp_percent, 1, '', 1, 0).'%';
                                                    ?>
                                                        
                                                    </td>
                                                    <?php
                                                    if (! $i) $totalarray['nbfield']++;
                                                }
                                                if (! empty($arrayfields['p.budget_amount']['checked']))
                                                {
                                                    ?>
                                                    <td align="right">
                                                    <?php
                                                    if ($obj->budget_amount != '') 
                                                    {
                                                        echo price($obj->budget_amount, 1, '', 1, -1, -1);
                                                        $totalarray['totalbudget'] += $obj->budget_amount;
                                                    }
                                                    ?>
                                                    </td>
                                                    <?php
                                                    if (! $i) $totalarray['nbfield']++;
                                                    if (! $i) $totalarray['totalbudgetfield']=$totalarray['nbfield'];
                                                }
                                                // Extra fields
                                                if (is_array($extrafields->attribute_label) && count($extrafields->attribute_label))
                                                {
                                                    foreach($extrafields->attribute_label as $key => $val)
                                                    {
                                                        if (! empty($arrayfields["ef.".$key]['checked']))
                                                        {
                                                            ?>
                                                            <td
                                                            <?php $align=$extrafields->getAlignFlag($key);
                                                            if ($align) ?>align=<?php echo $align; ?>
                                                            >
                                                            <?php
                                                            $tmpkey='options_'.$key;
                                                            echo $extrafields->showOutputField($key, $obj->$tmpkey, '', 1);
                                                            ?>
                                                            </td>
                                                            <?php
                                                        }
                                                    }
                                                    if (! $i) $totalarray['nbfield']++;
                                                }
                                                // Fields from hook
                                                $parameters=array('arrayfields'=>$arrayfields, 'obj'=>$obj);
                                                $reshook=$hookmanager->executeHooks('printFieldListValue',$parameters);    // Note that $action and $object may have been modified by hook
                                                echo $hookmanager->resPrint;
                                                // Date creation
                                                if (! empty($arrayfields['p.datec']['checked']))
                                                {
                                                    ?>
                                                    <td align="center">
                                                    <?php 
                                                    echo dol_print_date($db->jdate($obj->date_creation), 'dayhour');
                                                    ?>
                                                        
                                                    </td>
                                                    <?php
                                                    if (! $i) $totalarray['nbfield']++;
                                                }
                                                // Date modification
                                                if (! empty($arrayfields['p.tms']['checked']))
                                                {
                                                    ?>
                                                    <td align="center"><?php
                                                    echo dol_print_date($db->jdate($obj->date_update), 'dayhour');
                                                    ?>
                                                    </td>
                                                    <?php
                                                    if (! $i) $totalarray['nbfield']++;
                                                }
                                                // Status
                                                if (! empty($arrayfields['p.fk_statut']['checked']))
                                                {
                                                    $projectstatic->statut = $obj->fk_statut;
                                                    ?><td align="right"><?php echo $projectstatic->getLibStatut(5); ?></td>
                                                    <?php
                                                    if (! $i) $totalarray['nbfield']++;
                                                }
                                                // Action column
                                                ?>
                                                <td class="nowrap" align="center">
                                                <?php
                                                if ($massactionbutton || $massaction)   // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
                                                {
                                                    $selected=0;
                                                    if (in_array($obj->rowid, $arrayofselected)) $selected=1;
                                                    ?>
                                                    <input id="cb<?php echo $obj->rowid; ?>" class="flat checkforselect" type="checkbox" name="toselect[]" 
                                                    value="<?php echo $obj->rowid; ?>"<?php echo ($selected?' checked="checked"':''); ?> >
                                                    <?php
                                                }
                                                ?>
                                                </td>
                                                <?php
                                                if (! $i) $totalarray['nbfield']++;
                                                ?>
                                                </tr>
                                                <?php
                                                echo "\n";

                                            }

                                            $i++;

                                        }

                                        // Show total line
                                        if (isset($totalarray['totaloppfield']) || isset($totalarray['totalbudgetfield']))
                                        {
                                            ?>
                                            <tr class="liste_total">
                                            <?php
                                            $i=0;
                                            while ($i < $totalarray['nbfield'])
                                            {
                                                $i++;
                                                if ($i == 1)
                                                {
                                                    if ($num < $limit) 
                                                        {
                                                            ?>
                                                            <td align="left"><?php echo $langs->trans("Total"); ?></td>
                                                            <?php
                                                        }
                                                    else {
                                                        ?>
                                                        <td align="left"><?php echo $langs->trans("Totalforthispage"); ?></td>
                                                        <?php
                                                         } 
                                                }
                                                elseif ($totalarray['totaloppfield'] == $i) 
                                                    {
                                                        ?>
                                                        <td align="right"><?php echo price($totalarray['totalopp']); ?></td>
                                                        <?php
                                                    }
                                                elseif ($totalarray['totalbudgetfield'] == $i) 
                                                    {
                                                        ?>
                                                        <td align="right"><?php echo price($totalarray['totalbudget']); ?></td>
                                                        <?php
                                                    }
                                                else 
                                                    {
                                                        ?><td></td>
                                                        <?php
                                                    }
                                            }
                                            ?></tr><?php
                                        }

                                        $db->free($resql);

                                        $parameters=array('sql' => $sql);
                                        $reshook=$hookmanager->executeHooks('printFieldListFooter',$parameters);    // Note that $action and $object may have been modified by hook
                                        echo $hookmanager->resPrint;

                                        ?>
                                    </table>
                                    <?php

                                    echo "\n";
                                    ?>
                                    </div>
                                
                                <?php
                                echo "\n";
                    ?>
                    </tbody>                        
                </table>
    </div>
</form>
<?php echo "\n";?>

<?php

//The construction of a graph 

                                                                                                    

// Security check
if (! $user->rights->projet->lire)
	accessforbidden();


$WIDTH=DolGraph::getDefaultGraphSizeForStats('width');
$HEIGHT=DolGraph::getDefaultGraphSizeForStats('height');

$userid=GETPOST('userid','int');
$socid=GETPOST('socid','int');
// Security check
if ($user->societe_id > 0)
{
	$action = '';
	$socid = $user->societe_id;
}
$nowyear=strftime("%Y", dol_now());
$year = GETPOST('year')>0?GETPOST('year'):$nowyear;
//$startyear=$year-2;
$startyear=$year-1;
$endyear=$year;

$langs->load('companies');
$langs->load('projects');


/*
 * View
 */

$form=new Form($db);

$includeuserlist=array();

$dir=$conf->projet->dir_output.'/temp';


dol_mkdir($dir);


$stats_project= new ProjectStats($db);
if (!empty($userid) && $userid!=-1) $stats_project->userid=$userid;
if (!empty($socid)  && $socid!=-1) $stats_project->socid=$socid;
if (!empty($year)) $stats_project->year=$year;



if (! empty($conf->global->PROJECT_USE_OPPORTUNITIES))
    {
    	$data1 = $stats_project->getAllProjectByStatus();
    	if (!is_array($data1) && $data1<0) {
    		setEventMessages($stats_project->error, null, 'errors');
    	}
    	if (empty($data1))
    	{
    		$showpointvalue=0;
    		$nocolor=1;
    		$data1=array(array(0=>$langs->trans("None"),1=>1));
    	}

    	$filenamenb = $conf->project->dir_output . "/stats/projectbystatus.png";
    	$fileurlnb = DOL_URL_ROOT . '/viewimage.php?modulepart=projectstats&amp;file=projectbystatus.png';
    	$px = new DolGraph();
    	$mesg = $px->isGraphKo();
    	if (empty($mesg)) {
    		$i=0;$tot=count($data1);$legend=array();
    		while ($i <= $tot)
    		{
    			$data1[$i][0]=$data1[$i][0];	// Required to avoid error "Could not draw pie with labels contained inside canvas"
    			$legend[]=$data1[$i][0];
    			$i++;
    		}
    		$px->SetData($data1);
    		unset($data1);

    		if ($nocolor)
    			$px->SetDataColor(array (
    					array (
    							220,
    							220,
    							220
    					)
    			));
    			$px->SetPrecisionY(0);
    			$px->SetLegend($legend);
    			$px->setShowLegend(0);
    			$px->setShowPointValue($showpointvalue);
    			$px->setShowPercent(1);
    			$px->SetMaxValue($px->GetCeilMaxValue());
    			$px->SetWidth($WIDTH);
    			$px->SetHeight($HEIGHT);
    			$px->SetShading(3);
    			$px->SetHorizTickIncrement(1);
    			$px->SetCssPrefix("cssboxes");
    			$px->SetType(array (
    					'pie'
    			));
    			$px->SetTitle($langs->trans('OpportunitiesStatusForProjects'));
    			$result=$px->draw($filenamenb, $fileurlnb);
    			if ($result<0) {
    				setEventMessages($px->error, null, 'errors');
    			}
    	} else {
    		setEventMessages(null, $mesgs, 'errors');
    	}
    }


// Build graphic number of object
// $data = array(array('Lib',val1,val2,val3),...)
$data = $stats_project->getNbByMonthWithPrevYear($endyear,$startyear);
//var_dump($data);

$filenamenb = $conf->project->dir_output . "/stats/projectnbprevyear-".$year.".png";
$fileurlnb = DOL_URL_ROOT . '/viewimage.php?modulepart=projectstats&amp;file=projectnbprevyear-'.$year.'.png';

$px1 = new DolGraph();
$mesg = $px1->isGraphKo();
if (! $mesg)
    {
    	$px1->SetData($data);
    	$px1->SetPrecisionY(0);
    	$i=$startyear;$legend=array();
    	while ($i <= $endyear)
    	{
    		$legend[]=$i;
    		$i++;
    	}
    	$px1->SetLegend($legend);
    	$px1->SetMaxValue($px1->GetCeilMaxValue());
    	$px1->SetWidth($WIDTH);
    	$px1->SetHeight($HEIGHT);
    	$px1->SetYLabel($langs->trans("ProjectNbProject"));
    	$px1->SetShading(3);
    	$px1->SetHorizTickIncrement(1);
    	$px1->SetPrecisionY(0);
    	$px1->mode='depth';
    	$px1->SetTitle($langs->trans("ProjectNbProjectByMonth"));

    	$px1->draw($filenamenb,$fileurlnb);
    }


if (! empty($conf->global->PROJECT_USE_OPPORTUNITIES))
    {
    	// Build graphic amount of object
    	$data = $stats_project->getAmountByMonthWithPrevYear($endyear,$startyear);
    	//var_dump($data);
    	// $data = array(array('Lib',val1,val2,val3),...)

    	$filenamenb = $conf->project->dir_output . "/stats/projectamountprevyear-".$year.".png";
    	$fileurlnb = DOL_URL_ROOT . '/viewimage.php?modulepart=projectstats&amp;file=projectamountprevyear-'.$year.'.png';

    	$px2 = new DolGraph();
    	$mesg = $px2->isGraphKo();
    	if (! $mesg)
    	{
    		$px2->SetData($data);
    		$i=$startyear;$legend=array();
    		while ($i <= $endyear)
    		{
    			$legend[]=$i;
    			$i++;
    		}
    		$px2->SetLegend($legend);
    		$px2->SetMaxValue($px2->GetCeilMaxValue());
    		$px2->SetMinValue(min(0,$px2->GetFloorMinValue()));
    		$px2->SetWidth($WIDTH);
    		$px2->SetHeight($HEIGHT);
    		$px2->SetYLabel($langs->trans("ProjectOppAmountOfProjectsByMonth"));
    		$px2->SetShading(3);
    		$px2->SetHorizTickIncrement(1);
    		$px2->SetPrecisionY(0);
    		$px2->mode='depth';
    		$px2->SetTitle($langs->trans("ProjectOppAmountOfProjectsByMonth"));

    		$px2->draw($filenamenb,$fileurlnb);
    	}
    }

if (! empty($conf->global->PROJECT_USE_OPPORTUNITIES))
    {
    	// Build graphic with transformation rate
    	$data = $stats_project->getWeightedAmountByMonthWithPrevYear($endyear,$startyear, 0, 0);
    	//var_dump($data);
    	// $data = array(array('Lib',val1,val2,val3),...)

    	$filenamenb = $conf->project->dir_output . "/stats/projecttransrateprevyear-".$year.".png";
    	$fileurlnb = DOL_URL_ROOT . '/viewimage.php?modulepart=projectstats&amp;file=projecttransrateprevyear-'.$year.'.png';

    	$px3 = new DolGraph();
    	$mesg = $px3->isGraphKo();
    	if (! $mesg)
    	{
    		$px3->SetData($data);
    		$i=$startyear;$legend=array();
    		while ($i <= $endyear)
    		{
    			$legend[]=$i;
    			$i++;
    		}
    		$px3->SetLegend($legend);
    		$px3->SetMaxValue($px3->GetCeilMaxValue());
    		$px3->SetMinValue(min(0,$px3->GetFloorMinValue()));
    		$px3->SetWidth($WIDTH);
    		$px3->SetHeight($HEIGHT);
    		$px3->SetYLabel($langs->trans("ProjectWeightedOppAmountOfProjectsByMonth"));
    		$px3->SetShading(3);
    		$px3->SetHorizTickIncrement(1);
    		$px3->SetPrecisionY(0);
    		$px3->mode='depth';
    		$px3->SetTitle($langs->trans("ProjectWeightedOppAmountOfProjectsByMonth"));

    		$px3->draw($filenamenb,$fileurlnb);
    	}
    }


$data = $stats_project->getNbByMonthWithPrevYear($opp_amount,$opp_percent);
// var_dump($data);
// exit();

$filenamenb = $conf->project->dir_output . "/stats/projectnbprevyear-".$year.".png";
$fileurlnb = DOL_URL_ROOT . '/viewimage.php?modulepart=projectstats&amp;file=projectnbprevyear-'.$year.'.png';

$px1 = new DolGraph();
$mesg = $px1->isGraphKo();
if (! $mesg)
	{
		$px1->SetData($data);
		$px1->SetPrecisionY(0);
		$i=$opp_percent;$legend=array();
		while ($i <= $opp_amount)
		{
			$legend[]=$i;
			$i++;
		}
		$px1->SetLegend($legend);
		$px1->SetMaxValue($px1->GetCeilMaxValue());
		$px1->SetWidth($WIDTH);
		$px1->SetHeight($HEIGHT);
		$px1->SetYLabel($langs->trans("ProjectNbProject"));
		$px1->SetShading(3);
		$px1->SetHorizTickIncrement(1);
		$px1->SetPrecisionY(0);
		$px1->mode='depth';
		$px1->SetTitle($langs->trans("ProjectNbProjectByMonth"));

		$px1->draw($filenamenb,$fileurlnb);
	}
?>



        <div class="container-fluid">
            <div class="row">
                <div class="col-xs-12">
                    [ tableau de progression globale ]
                </div>    
            </div> 
        </div>    




        <div class="container-fluid">
            <div class="row">

                <div class="col-md-6">

                    <table class="border" width="100%">
                        <tr valign="top">
                            <td align="center">
                                <?php
                                if ($mesg) { echo $mesg; }
                                else {
                                    echo $px1->show();
                                    echo "<br>\n";
                                    if (! empty($conf->global->PROJECT_USE_OPPORTUNITIES))
                                    {
                                        echo $px->show();
                                        echo "<br>\n";
                                        
                                    }
                                }
                                ?>
                            </td>
                        </tr>
                    </table>

                </div>

                <div class="col-md-6">
                    <table class="border" width="100%">
                        <tr valign="top">
                            <td align="center">
                                <?php
                                if ($mesg) { echo $mesg; }
                                else {
                                    if (! empty($conf->global->PROJECT_USE_OPPORTUNITIES))
                                    {
                            
                                        echo $px2->show();
                                        echo "<br>\n";
                                        echo $px3->show();
                                    }
                                }
                                ?>
                            </td>
                        </tr>
                    </table>
   
                </div>
	       </div>
        </div>
<?php

llxFooter();
$db->close();
