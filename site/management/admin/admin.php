<?php
/* Copyright (C) 2014-2017	Charlie BENKE <charlie@patas-monkey.com>
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
 *   	\file       htdocs/management/admin/admin.php
 *		\ingroup    management
 *		\brief      Page to setup the module management
 */

// Dolibarr environment
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");		// For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");	// For "custom" directory


require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

dol_include_once('/management/core/lib/management.lib.php');


$langs->load("admin");
$langs->load("management@management");

if (! $user->admin) accessforbidden();


$type=array('yesno','texte','chaine');

$action = GETPOST('action','alpha');


/*
 * Actions
 */

if ($action == 'setmodestopvalue')
{
	// save the setting
	dolibarr_set_const($db, "MANAGEMENT_STOP_MODE", GETPOST('typeMode'),'chaine',0,'',$conf->entity);
	dolibarr_set_const($db, "MANAGEMENT_STOP_DURATION", GETPOST('stopduration','int'),'chaine',0,'',$conf->entity);
	
	$mesg = "<font class='ok'>".$langs->trans("SetupSaved")."</font>";
}

if ($action == 'setdefaultservicetask')
{
	// save the setting
	dolibarr_set_const($db, "MANAGEMENT_DEFAULTSERVICETASK", GETPOST('defaultservicetask'),'chaine',0,'',$conf->entity);	
	$mesg = "<font class='ok'>".$langs->trans("SetupSaved")."</font>";
}
if ($action == 'setdefaultthmprice')
{
	// save the setting
	dolibarr_set_const($db, "MANAGEMENT_DEFAULTTHMPRICE", GETPOST('defaultthmprice'),'chaine',0,'',$conf->entity);	
	$mesg = "<font class='ok'>".$langs->trans("SetupSaved")."</font>";
}


if ($action == 'setselecttermvalue')
{
	// save the setting
	dolibarr_set_const($db, "CONTRAT_SELECTERM_MODE", GETPOST('termMode'),'chaine',0,'',$conf->entity);
	dolibarr_set_const($db, "CONTRAT_SELECTERM_DURATION", GETPOST('termduration','int'),'chaine',0,'',$conf->entity);
	
	$mesg = "<font class='ok'>".$langs->trans("SetupSaved")."</font>";
}
if ($action == 'setnbhoursonquarterday')
{
	// save the setting
	dolibarr_set_const($db, "MANAGEMENT_NBHOURS_ON_QUARTER_DAY", GETPOST('nbhoursonquarterday'),'chaine',0,'',$conf->entity);	
	$mesg = "<font class='ok'>".$langs->trans("SetupSaved")."</font>";
}
if ($action == 'setprojectstatutdefault')
{
	// save the setting
	dolibarr_set_const($db, "MANAGEMENT_DEFAULT_STATUT_NEW_PROJET", GETPOST('projectstatutdefault'),'chaine',0,'',$conf->entity);	
	$mesg = "<font class='ok'>".$langs->trans("SetupSaved")."</font>";
}



if ($action == 'setselecttermdefault')
{
	// save the setting
	dolibarr_set_const($db, "CONTRAT_DEFAULTTERM_MODE", GETPOST('termdefaultMode'),'chaine',0,'',$conf->entity);
	dolibarr_set_const($db, "CONTRAT_DEFAULTTERM_DURATION", GETPOST('termdefaultduration','int'),'chaine',0,'',$conf->entity);
	
	$mesg = "<font class='ok'>".$langs->trans("SetupSaved")."</font>";
}
elseif ($action == 'hideemptyduration')
{
	// save the setting
	dolibarr_set_const($db, "FICHINTER_HIDE_EMPTY_DURATION", GETPOST('value','int'),'chaine',0,'',$conf->entity);
	
	$mesg = "<font class='ok'>".$langs->trans("SetupSaved")."</font>";
}
elseif ($action == 'generateprojectfromproposal')
{
	// save the setting
	dolibarr_set_const($db, "MANAGEMENT_GENERATE_PROJECT_FROM_PROPOSAL", GETPOST('value','int'),'chaine',0,'',$conf->entity);

	$mesg = "<font class='ok'>".$langs->trans("SetupSaved")."</font>";
}
elseif ($action == 'displaytasklabelinsteadtaskref')
{
	// save the setting
	dolibarr_set_const($db, "MANAGEMENT_DISPLAY_TASKLABEL_INSTEAD_TASKREF", GETPOST('value','int'),'chaine',0,'',$conf->entity);
	$mesg = "<font class='ok'>".$langs->trans("SetupSaved")."</font>";
}


elseif ($action == 'onlyreportbilled')
{
	// save the setting
	dolibarr_set_const($db, "FICHINTER_ONLY_REPORT_BILLED", GETPOST('value','int'),'chaine',0,'',$conf->entity);
	
	$mesg = "<font class='ok'>".$langs->trans("SetupSaved")."</font>";
}


elseif ($action == 'setaddtimeone')
{
	// save the setting
	$duration = convertTime2Seconds(GETPOST('durationhour','int'), GETPOST('durationmin','int'));
	dolibarr_set_const($db, "MANAGEMENT_ADD_TIME_ONE", $duration,'chaine',0,'',$conf->entity);
	
	$mesg = "<font class='ok'>".$langs->trans("SetupSaved")."</font>";
}
elseif ($action == 'setaddtimetwo')
{
	// save the setting
		$duration = convertTime2Seconds(GETPOST('durationhour','int'), GETPOST('durationmin','int'));
	dolibarr_set_const($db, "MANAGEMENT_ADD_TIME_TWO", $duration,'chaine',0,'',$conf->entity);
	
	$mesg = "<font class='ok'>".$langs->trans("SetupSaved")."</font>";
}


// Get setting 

$defaultservicetask=$conf->global->MANAGEMENT_DEFAULTSERVICETASK;
$defaultthmprice=$conf->global->MANAGEMENT_DEFAULTTHMPRICE;

$termMode=$conf->global->CONTRAT_SELECTERM_MODE;
$termduration=$conf->global->CONTRAT_SELECTERM_DURATION;

$termdefaultMode=$conf->global->CONTRAT_DEFAULTTERM_MODE;
$termdefaultduration=$conf->global->CONTRAT_DEFAULTTERM_DURATION;

$stopduration=$conf->global->MANAGEMENT_STOP_DURATION;
$typeMode=$conf->global->MANAGEMENT_STOP_MODE;

$addTimeOne=$conf->global->MANAGEMENT_ADD_TIME_ONE;
$addTimeTwo=$conf->global->MANAGEMENT_ADD_TIME_TWO;

$nbhoursonquarterday=$conf->global->MANAGEMENT_NBHOURS_ON_QUARTER_DAY;

$hideemptyduration = $conf->global->FICHINTER_HIDE_EMPTY_DURATION;
$onlyreportbilled = $conf->global->FICHINTER_ONLY_REPORT_BILLED;

$generateprojectfromproposal = $conf->global->MANAGEMENT_GENERATE_PROJECT_FROM_PROPOSAL;
$projectstatutdefault = $conf->global->MANAGEMENT_DEFAULT_STATUT_NEW_PROJET;

$displaytasklabelinsteadtaskref= $conf->global->MANAGEMENT_DISPLAY_TASKLABEL_INSTEAD_TASKREF;


/*
 * View
 */

$form = new Form($db);
$page_name = $langs->trans("ManagementSetup")." - ".$langs->trans('GeneralSetup');

$help_url='EN:Module_Management|FR:Module_Management|ES:M&oacute;dulo_Management';

llxHeader('',$page_name,$help_url);


$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($page_name, $linkback, 'title_setup');


$head = management_admin_prepare_head();

dol_fiche_head($head, 'admin', $langs->trans("Management"), 0, 'management@management');


dol_htmloutput_mesg($mesg);

// la sélection des status à suivre dans le process commercial

print '<table class="noborder" >';
print '<tr class="liste_titre">';
print '<td width="200px">'.$langs->trans("ContractManagementSetting").'</td>';
print '<td>'.$langs->trans("Description").'</td>';
print '<td nowrap >'.$langs->trans("Value").'</td>';
print '</tr>'."\n";
print '<tr >';
print '<td align=left valign=top>'.$langs->trans("SelectDefaultTermDuration").'</td>';
print '<td align=left valign=top>'.$langs->trans("InfoSelectDefaultTermDuration").'</td>';
print '<td  align=right>';
print '<form method="post" action="admin.php">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="setselecttermdefault">';
print '<input type=text size=3 value="'.$termdefaultduration.'" name=termdefaultduration>';
print '&nbsp;&nbsp;';
print '<select name=termdefaultMode>';
print '<option value="0" >'.$langs->trans("None").'</option>';
print '<option value="DAY" '.($termdefaultMode=="DAY"?'selected':'').' >'.$langs->trans("Days").'</option>';
print '<option value="WEEK" '.($termdefaultMode=="WEEK"?'selected':'').' >'.$langs->trans("Week").'</option>';
print '<option value="MONTH" '.($termdefaultMode=="MONTH"?'selected':'').' >'.$langs->trans("Month").'</option>';
print '<option value="QUARTER" '.($termdefaultMode=="QUARTER"?'selected':'').' >'.$langs->trans("Quarter").'</option>';
print '<option value="SEMESTER" '.($termdefaultMode=="SEMESTER"?'selected':'').' >'.$langs->trans("Semester").'</option>';
print '<option value="YEAR" '.($termdefaultMode=="YEAR"?'selected':'').' >'.$langs->trans("Year").'</option>';
print '</select>';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td>';
print '</tr>'."\n";

print '<tr >';
print '<td align=left valign=top>'.$langs->trans("SelectTermMode").'</td>';
print '<td align=left valign=top>'.$langs->trans("InfoSelectTermMode").'</td>';
print '<td  align=right>';
print '<form method="post" action="admin.php">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="setselecttermvalue">';
print '<input type=text size=3 value="'.$termduration.'" name=termduration>';
print '&nbsp;&nbsp;';
print '<select name=termMode>';
print '<option value="0" >'.$langs->trans("None").'</option>';
print '<option value="DAY" '.($termMode=="DAY"?'selected':'').' >'.$langs->trans("Days").'</option>';
print '<option value="WEEK" '.($termMode=="WEEK"?'selected':'').' >'.$langs->trans("Week").'</option>';
print '<option value="MONTH" '.($termMode=="MONTH"?'selected':'').' >'.$langs->trans("Month").'</option>';
print '<option value="QUARTER" '.($termMode=="QUARTER"?'selected':'').' >'.$langs->trans("Quarter").'</option>';
print '<option value="SEMESTER" '.($termMode=="SEMESTER"?'selected':'').' >'.$langs->trans("Semester").'</option>';
print '<option value="YEAR" '.($termMode=="YEAR"?'selected':'').' >'.$langs->trans("Year").'</option>';
print '</select>';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td>';
print '</tr>'."\n";
print '</table>';
print '<table class="noborder" >';
print '<tr class="liste_titre">';
print '<td width="200px">'.$langs->trans("FichInterManagementSetting").'</td>';
print '<td>'.$langs->trans("Description").'</td>';
print '<td nowrap >'.$langs->trans("Enable").'</td>';
print '</tr>'."\n";

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td valign=top>'.$langs->trans("FichinterHideEmptyDuration").'</td>';
print '<td>'.$langs->trans("InfoFichinterHideEmptyDuration").'</td>';
print '<td align=right >';
if ($hideemptyduration =="1")
	print '<a href="'.$_SERVER["PHP_SELF"].'?action=hideemptyduration&amp;value=0">'.img_picto($langs->trans("Activated"),'switch_on').'</a>';
else
	print '<a href="'.$_SERVER["PHP_SELF"].'?action=hideemptyduration&amp;value=1">'.img_picto($langs->trans("Disabled"),'switch_off').'</a>';
print '</td>';
print '</tr>'."\n";

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td valign=top>'.$langs->trans("FichinterBillOnlyReportLine").'</td>';
print '<td>'.$langs->trans("InfoFichinterBillOnlyReportLine").'</td>';
print '<td align=right >';
if ($onlyreportbilled =="1")
	print '<a href="'.$_SERVER["PHP_SELF"].'?action=onlyreportbilled&amp;value=0">'.img_picto($langs->trans("Activated"),'switch_on').'</a>';
else
	print '<a href="'.$_SERVER["PHP_SELF"].'?action=onlyreportbilled&amp;value=1">'.img_picto($langs->trans("Disabled"),'switch_off').'</a>';
print '</td>';
print '</tr>'."\n";

print '</table>';
print '<table class="noborder" >';

print '<tr class="liste_titre">';
print '<td width="200px">'.$langs->trans("ProjectManagementSetting").'</td>';
print '<td>'.$langs->trans("Description").'</td>';
print '<td nowrap >'.$langs->trans("Value").'</td>';
print '</tr>'."\n";

$var=true;
print '<tr '.$bc[$var].'>';
print '<td align=left>'.$langs->trans("DefaultAssociatedServiceOnTask").'</td>';
print '<td align=left>'.$langs->trans("InfoDefaultAssociatedServiceOnTask").'</td>';
print '<td  align=right>';
print '<form method="post" action="admin.php">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="setdefaultservicetask">';
$form->select_produits($defaultservicetask, 'defaultservicetask', 1,$conf->product->limit_size, "", 1, 2, '', 1, array(),0);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td>';
print '</tr>'."\n";

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td align=left>'.$langs->trans("DefaultTHMPrice").'</td>';
print '<td align=left>'.$langs->trans("InfoDefaultTHMPrice").'</td>';
print '<td  align=right>';
print '<form method="post" action="admin.php">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="setdefaultthmprice">';
print '<input type=text size=3 value="'.$defaultthmprice.'" name=defaultthmprice>';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td>';
print '</tr>'."\n";

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td align=left>'.$langs->trans("FlyTimeAutomaticStopMode").'</td>';
print '<td align=left>'.$langs->trans("InfoFlyTimeAutomaticStopMode").'</td>';
print '<td  align=right>';
print '<form method="post" action="admin.php">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="setmodestopvalue">';
print '<input type=text size=3 value="'.$stopduration.'" name=stopduration>';
print '&nbsp;&nbsp;';
print '<select name=typeMode>';
print '<option value="0" >'.$langs->trans("None").'</option>';
print '<option value="HOUR" '.($typeMode=="HOUR"?'selected':'').' >'.$langs->trans("Hours").'</option>';
print '<option value="DAY" '.($typeMode=="DAY"?'selected':'').' >'.$langs->trans("Days").'</option>';
print '<option value="WEEK" '.($typeMode=="WEEK"?'selected':'').' >'.$langs->trans("Week").'</option>';
print '<option value="MONTH" '.($typeMode=="MONTH"?'selected':'').' >'.$langs->trans("Month").'</option>';
print '</select>';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td>';
print '</tr>'."\n";

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td align=left>'.$langs->trans("FlyTimeTimerOne").'</td>';
print '<td align=left>'.$langs->trans("InfoFlyTimeTimerOne").'</td>';
print '<td  align=right>';
print '<form method="post" action="admin.php">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="setaddtimeone">';
$form->select_duration('duration',$addTimeOne);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td>';
print '</tr>'."\n";

//$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td align=left>'.$langs->trans("FlyTimeTimerTwo").'</td>';
print '<td align=left>'.$langs->trans("InfoFlyTimeTimerTwo").'</td>';
print '<td  align=right>';
print '<form method="post" action="admin.php">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="setaddtimetwo">';
$form->select_duration('duration',$addTimeTwo);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td>';
print '</tr>'."\n";

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td valign=top>'.$langs->trans("DisplayTaskLabelInsteadTaskRef").'</td>';
print '<td>'.$langs->trans("InfoDisplayTaskLabelInsteadTaskRef").'</td>';
print '<td align=right >';
if ($displaytasklabelinsteadtaskref =="1")
	print '<a href="'.$_SERVER["PHP_SELF"].'?action=displaytasklabelinsteadtaskref&amp;value=0">'.img_picto($langs->trans("Activated"),'switch_on').'</a>';
else
	print '<a href="'.$_SERVER["PHP_SELF"].'?action=displaytasklabelinsteadtaskref&amp;value=1">'.img_picto($langs->trans("Disabled"),'switch_off').'</a>';
print '</td>';
print '</tr>'."\n";



$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td align=left>'.$langs->trans("NbHoursOnAQuarterDay").'</td>';
print '<td align=left>'.$langs->trans("InfoNbHoursOnAQuarterDay").'</td>';
print '<td  align=right>';
print '<form method="post" action="admin.php">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="setnbhoursonquarterday">';
print '<input type=text size=3 value="'.$nbhoursonquarterday.'" name=nbhoursonquarterday>';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td>';
print '</tr>'."\n";

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td valign=top>'.$langs->trans("GenerateProjectTaskFromProposale").'</td>';
print '<td>'.$langs->trans("InfoGenerateProjectTaskFromProposale").'</td>';
print '<td align=right >';
if ($generateprojectfromproposal =="1")
	print '<a href="'.$_SERVER["PHP_SELF"].'?action=generateprojectfromproposal&amp;value=0">'.img_picto($langs->trans("Activated"),'switch_on').'</a>';
else
	print '<a href="'.$_SERVER["PHP_SELF"].'?action=generateprojectfromproposal&amp;value=1">'.img_picto($langs->trans("Disabled"),'switch_off').'</a>';
print '</td>';
print '</tr>'."\n";


$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td align=left>'.$langs->trans("DefaultProjectStatutOnCreate").'</td>';
print '<td align=left>'.$langs->trans("InfoDefaultProjectStatutOnCreate").'</td>';
print '<td  align=right>';
print '<form method="post" action="admin.php">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="setprojectstatutdefault">';
print '<select name=projectstatutdefault>';
print '<option value="0" '.($projectstatutdefault=="0"?'selected':'').' >'.$langs->trans("Draft").'</option>';
print '<option value="1" '.($projectstatutdefault=="1"?'selected':'').' >'.$langs->trans("Enabled").'</option>';
print '<option value="2" '.($projectstatutdefault=="2"?'selected':'').' >'.$langs->trans("Closed").'</option>';
print '</select>';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td>';
print '</tr>'."\n";


print '</table>';


/*
 *  Infos pour le support
 */
print '<br>';
libxml_use_internal_errors(true);
$sxe = simplexml_load_string(nl2br (file_get_contents('../changelog.xml')));
if ($sxe === false) 
{
	echo "Erreur lors du chargement du XML\n";
	foreach(libxml_get_errors() as $error) 
		print $error->message;
	exit;
}
else
	$tblversions=$sxe->Version;

$currentversion = $tblversions[count($tblversions)-1];

print '<table class="noborder" width="100%">'."\n";
print '<tr class="liste_titre">'."\n";
print '<td width=20%>'.$langs->trans("SupportModuleInformation").'</td>'."\n";
print '<td>'.$langs->trans("Value").'</td>'."\n";
print "</tr>\n";
print '<tr '.$bc[false].'><td >'.$langs->trans("DolibarrVersion").'</td><td>'.DOL_VERSION.'</td></tr>'."\n";
print '<tr '.$bc[true].'><td >'.$langs->trans("ModuleVersion").'</td><td>'.$currentversion->attributes()->Number." (".$currentversion->attributes()->MonthVersion.')</td></tr>'."\n";
print '<tr '.$bc[false].'><td >'.$langs->trans("PHPVersion").'</td><td>'.version_php().'</td></tr>'."\n";
print '<tr '.$bc[true].'><td >'.$langs->trans("DatabaseVersion").'</td><td>'.$db::LABEL." ".$db->getVersion().'</td></tr>'."\n";
print '<tr '.$bc[false].'><td >'.$langs->trans("WebServerVersion").'</td><td>'.$_SERVER["SERVER_SOFTWARE"].'</td></tr>'."\n";
print '<tr>'."\n";
print '<td colspan="2">'.$langs->trans("SupportModuleInformationDesc").'</td></tr>'."\n";
print "</table>\n";



// Show messages
dol_htmloutput_mesg($object->mesg,'','ok');

// Footer
llxFooter();
$db->close();