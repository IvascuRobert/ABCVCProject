<?php
/* Copyright (C) 2014-2016	Charlie BENKE <charlie@patas-monkey.com>
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
 *   	\file       htdocs/management/admin/trigger.php
 *		\ingroup    management
 *		\brief      Page to setup the trigger in management
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
if ($action == 'setselecttermvalue')
{
	// save the setting
	dolibarr_set_const($db, "CONTRAT_SELECTERM_MODE", GETPOST('termMode'),'chaine',0,'',$conf->entity);
	dolibarr_set_const($db, "CONTRAT_SELECTERM_DURATION", GETPOST('termduration','int'),'chaine',0,'',$conf->entity);
	
	$mesg = "<font class='ok'>".$langs->trans("SetupSaved")."</font>";
}
if ($action == 'setselecttermdefault')
{
	// save the setting
	dolibarr_set_const($db, "CONTRAT_DEFAULTTERM_MODE", GETPOST('termdefaultMode'),'chaine',0,'',$conf->entity);
	dolibarr_set_const($db, "CONTRAT_DEFAULTTERM_DURATION", GETPOST('termdefaultduration','int'),'chaine',0,'',$conf->entity);
	
	$mesg = "<font class='ok'>".$langs->trans("SetupSaved")."</font>";
}
elseif ($action == 'sethideemptyduration')
{
	// save the setting
	dolibarr_set_const($db, "FICHINTER_HIDE_EMPTY_DURATION", GETPOST('hideemptyduration','int'),'chaine',0,'',$conf->entity);
	
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
$termMode=$conf->global->CONTRAT_SELECTERM_MODE;
$termduration=$conf->global->CONTRAT_SELECTERM_DURATION;

$termdefaultMode=$conf->global->CONTRAT_DEFAULTTERM_MODE;
$termdefaultduration=$conf->global->CONTRAT_DEFAULTTERM_DURATION;

$stopduration=$conf->global->MANAGEMENT_STOP_DURATION;
$typeMode=$conf->global->MANAGEMENT_STOP_MODE;

$addTimeOne=$conf->global->MANAGEMENT_ADD_TIME_ONE;
$addTimeTwo=$conf->global->MANAGEMENT_ADD_TIME_TWO;

$hideemptyduration=$conf->global->FICHINTER_HIDE_EMPTY_DURATION;


/*
 * View
 */

$form = new Form($db);

$help_url='EN:Module_Management|FR:Module_Management|ES:M&oacute;dulo_Management';

llxHeader('',$langs->trans("ManagementSetup"),$help_url);


$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans("ManagementSetup"),$linkback,'setup');


$head = management_admin_prepare_head();

dol_fiche_head($head, 'trigger', $langs->trans("Management"), 0, 'management@management');


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


print '<tr class="liste_titre">';
print '<td width="200px">'.$langs->trans("FichInterManagementSetting").'</td>';
print '<td>'.$langs->trans("Description").'</td>';
print '<td nowrap >'.$langs->trans("Value").'</td>';
print '</tr>'."\n";

print '<tr >';
print '<td align=left valign=top>'.$langs->trans("FichinterHideEmptyDuration").'</td>';
print '<td align=left valign=top>'.$langs->trans("InfoFichinterHideEmptyDuration").'</td>';
print '<td  align=right>';
print '<form method="post" action="admin.php">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="sethideemptyduration">';
print '<input type=text size=3 value="'.$hideemptyduration.'" name=hideemptyduration>';
print '&nbsp;&nbsp;';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td>';
print '</tr>'."\n";

print '<tr class="liste_titre">';
print '<td width="200px">'.$langs->trans("ProjectManagementSetting").'</td>';
print '<td>'.$langs->trans("Description").'</td>';
print '<td nowrap >'.$langs->trans("Value").'</td>';
print '</tr>'."\n";
print '<tr >';
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

print '<tr >';
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

print '<tr >';
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
print '<tr >';
print '<td colspan=3><hr></td>';
print '</tr>'."\n";
print '<tr >';
print '<td align=left>'.$langs->trans("EnableBillMadeMode").'</td>';
print '<td align=left>'.$langs->trans("InfoEnableBillMadeMode").'</td>';
print '<td align=center >';
if ($conf->global->MANAGEMENT_BILLMADE_MODE =="1")
	print '<a href="'.$_SERVER["PHP_SELF"].'?action=setbillmademode&amp;value=0">'.img_picto($langs->trans("Activated"),'switch_on').'</a>';
else
	print '<a href="'.$_SERVER["PHP_SELF"].'?action=setbillmademode&amp;value=1">'.img_picto($langs->trans("Disabled"),'switch_off').'</a>';
print '</td>';
print '</tr>';
print '<tr >';
print '<td align=left>'.$langs->trans("EnableBillPlannedMode").'</td>';
print '<td align=left>'.$langs->trans("InfoEnableBillPlannedMode").'</td>';
print '<td align=center >';
if ($conf->global->MANAGEMENT_BILLPLANNED_MODE =="1")
	print '<a href="'.$_SERVER["PHP_SELF"].'?action=setbillplannedmode&amp;value=0">'.img_picto($langs->trans("Activated"),'switch_on').'</a>';
else
	print '<a href="'.$_SERVER["PHP_SELF"].'?action=setbillplannedmode&amp;value=1">'.img_picto($langs->trans("Disabled"),'switch_off').'</a>';
print '</td>';
print '</tr>';
print '<tr >';
print '<td align=left>'.$langs->trans("EnableBillLeftMode").'</td>';
print '<td align=left>'.$langs->trans("InfoEnableBillLeftMode").'</td>';
print '<td align=center >';
if ($conf->global->MANAGEMENT_BILLLEFT_MODE =="1")
	print '<a href="'.$_SERVER["PHP_SELF"].'?action=setbillleftmode&amp;value=0">'.img_picto($langs->trans("Activated"),'switch_on').'</a>';
else
	print '<a href="'.$_SERVER["PHP_SELF"].'?action=setbillleftmode&amp;value=1">'.img_picto($langs->trans("Disabled"),'switch_off').'</a>';
print '</td>';
print '</tr>';

print '</table>';
print '<br>';

dol_fiche_end();

llxFooter();
$db->close();
?>