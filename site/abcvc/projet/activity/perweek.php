	<?php
	/* Copyright (C) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
	 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
	 * Copyright (C) 2005-2010 Regis Houssin        <regis.houssin@capnetworks.com>
	 * Copyright (C) 2010      François Legastelois <flegastelois@teclib.com>
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
	 *	\file       htdocs/projet/activity/perweek.php
	 *	\ingroup    projet
	 *	\brief      List activities of tasks (per week entry)
	 */

	require ("../../../main.inc.php");
	require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/project.class.php';
	require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/task.class.php';
	require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/lib/project.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';


	//var_dump($_POST);
	//exit();

	$langs->load('projects');
	$langs->load('users');

	$action=GETPOST('action');
	$mode=GETPOST("mode");
	$id=GETPOST('id','int');
	$taskid=GETPOST('taskid');


	$mine=0;
	if ($mode == 'mine') $mine=1;

	$projectid='';
	$projectid=isset($_GET["id"])?$_GET["id"]:$_POST["projectid"];

	// Security check
	$socid=0;
	if ($user->societe_id > 0) $socid=$user->societe_id;
	$result = restrictedArea($user, 'projet', $projectid);

	$now=dol_now();
	$nowtmp=dol_getdate($now);
	$nowday=$nowtmp['mday'];
	$nowmonth=$nowtmp['mon'];
	$nowyear=$nowtmp['year'];
	$year=GETPOST('reyear')?GETPOST('reyear','int'):(GETPOST("year")?GETPOST("year","int"):date("Y"));
	$month=GETPOST('remonth')?GETPOST('remonth','int'):(GETPOST("month")?GETPOST("month","int"):date("m"));
	$day=GETPOST('reday')?GETPOST('reday','int'):(GETPOST("day")?GETPOST("day","int"):date("d"));
	$day = (int) $day;
	$week=GETPOST("week","int")?GETPOST("week","int"):date("W");
	$search_task_ref=GETPOST('search_task_ref', 'alpha');
	$search_task_label=GETPOST('search_task_label', 'alpha');
	$search_project_ref=GETPOST('search_project_ref', 'alpha');
	$search_thirdparty=GETPOST('search_thirdparty', 'alpha');

	$startdayarray=dol_get_first_day_week($day, $month, $year);

	$prev = $startdayarray;
	$prev_year  = $prev['prev_year'];
	$prev_month = $prev['prev_month'];
	$prev_day   = $prev['prev_day'];
	$first_day  = $prev['first_day'];
	$first_month= $prev['first_month'];
	$first_year = $prev['first_year'];
	$week = $prev['week'];

	$next = dol_get_next_week($first_day, $week, $first_month, $first_year);
	$next_year  = $next['year'];
	$next_month = $next['month'];
	$next_day   = $next['day'];

	// Define firstdaytoshow and lastdaytoshow (warning: lastdaytoshow is last second to show + 1)
	$firstdaytoshow=dol_mktime(0,0,0,$first_month,$first_day,$first_year);
	$lastdaytoshow=dol_time_plus_duree($firstdaytoshow, 7, 'd');

	$usertoprocess=$user;

	$object = new TaskABCVC($db);
// var_dump($object);
// exit();

	/*
	 * Actions
	 */

	// Purge criteria
	if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter.x") || GETPOST("button_removefilter")) // Both test are required to be compatible with all browsers
	{
	    $action = '';
	    $search_task_ref = '';
	    $search_task_label = '';
	    $search_project_ref = '';
	    $search_thirdparty = '';
	}
	if (GETPOST("button_search_x") || GETPOST("button_search.x") || GETPOST("button_search"))
	{
	    $action = '';
	}

	if (GETPOST('submitdateselect'))
	{
		$daytoparse = dol_mktime(0, 0, 0, GETPOST('remonth'), GETPOST('reday'), GETPOST('reyear'));

		$action = '';
	}

	// if ($action == 'addtime' && $user->rights->projet->lire && GETPOST('assigntask'))
		// {
		//     $action = 'assigntask';

		//     if ($taskid > 0)
		//     {
		// 		$result = $object->fetch($taskid, $ref);
		// 		if ($result < 0) $error++;
		//     }
		//     else
		//     {
		//     	setEventMessages($langs->transnoentitiesnoconv("ErrorFieldRequired", $langs->transnoentitiesnoconv("Task")), '', 'errors');
		//     	$error++;
		//     }
		//     if (! GETPOST('type'))
		//     {
		//     	setEventMessages($langs->transnoentitiesnoconv("ErrorFieldRequired", $langs->transnoentitiesnoconv("Type")), '', 'errors');
		//     	$error++;
		//     }
		    
		//     if (! $error)
		//     {
		//     	$idfortaskuser=$user->id;
		//     	$result = $object->add_contact($idfortaskuser, GETPOST("type"), 'internal');

		//     	if (! $result || $result == -2)	// Contact add ok or already contact of task
		//     	{
		//     		// Test if we are already contact of the project (should be rare but sometimes we can add as task contact without being contact of project, like when admin user has been removed from contact of project)
		//     		$sql='SELECT ec.rowid FROM '.MAIN_DB_PREFIX.'element_contact as ec, '.MAIN_DB_PREFIX.'c_type_contact as tc WHERE tc.rowid = ec.fk_c_type_contact';
		//     		$sql.=' AND ec.fk_socpeople = '.$idfortaskuser." AND ec.element_id = '.$object->fk_project.' AND tc.element = 'project' AND source = 'internal'";
		//     		$resql=$db->query($sql);
		//     		if ($resql)
		//     		{
		//     			$obj=$db->fetch_object($resql);
		//     			if (! $obj)	// User is not already linked to project, so we will create link to first type
		//     			{
		//     				$project = new ProjectABCVC($db);
		//     				$project->fetch($object->fk_project);
		//     				// Get type
		//     				$listofprojcontact=$project->liste_type_contact('internal');
		    				
		//     				if (count($listofprojcontact))
		//     				{
		//     					$typeforprojectcontact=reset(array_keys($listofprojcontact));
		//     					$result = $project->add_contact($idfortaskuser, $typeforprojectcontact, 'internal');
		//     				}
		//     			}
		//     		}
		//     		else 
		//     		{
		//     			dol_print_error($db);
		//     		}
		//     	}
		//     }

		// 	if ($result < 0)
		// 	{
		// 		$error++;
		// 		if ($object->error == 'DB_ERROR_RECORD_ALREADY_EXISTS')
		// 		{
		// 			$langs->load("errors");
		// 			setEventMessages($langs->trans("ErrorTaskAlreadyAssigned"), null, 'warnings');
		// 		}
		// 		else
		// 		{
		// 			setEventMessages($object->error, $object->errors, 'errors');
		// 		}
		// 	}

		// 	if (! $error)
		// 	{
		// 		setEventMessages("TaskAssignedToEnterTime", null);
		// 	}

		// 	$action='';
		// }

	if ($action == 'addtime' && $user->rights->projet->lire)
	{
	    $timetoadd=$_POST['task'];
	    $notetoadd=$_POST['note'];
	    // var_dump($timetoadd);
	    // var_dump($notetoadd);
		if (empty($timetoadd))
		{
		    setEventMessages($langs->trans("ErrorTimeSpentIsEmpty"), null, 'errors');
	    }
		else
		{
			foreach($timetoadd as $taskid => $value)     // Loop on each task
		    {
		        $updateoftaskdone=0;
		        //var_dump($taskid, $value);
				foreach($value as $key => $val)          // Loop on each day
				{
					$amountoadd=$timetoadd[$taskid][$key];
					$commentoadd=$notetoadd[$taskid][$key];
					
			    	if (! empty($amountoadd))
			        {
			        	$tmpduration=explode(':',$amountoadd);
			        	$newduration=0;
						if (! empty($tmpduration[0])) $newduration+=($tmpduration[0] * 3600);
						if (! empty($tmpduration[1])) $newduration+=($tmpduration[1] * 60);
						if (! empty($tmpduration[2])) $newduration+=($tmpduration[2]);
						// var_dump($amountoadd);
						// var_dump($commentoadd);
						// var_dump($newduration);
						// exit();
			        	if ($newduration > 0)
			        	{
			       	        $object->fetch($taskid);
			       	        $object->progress = GETPOST($taskid . 'progress', 'int');
					        $object->timespent_duration = $newduration;
					        $object->timespent_fk_user = $usertoprocess->id;
				        	$object->timespent_date = dol_time_plus_duree($firstdaytoshow, $key, 'd');
				        	$object->timespent_note = $commentoadd;
				        	//var_dump($object);exit();
							$result=$object->addTimeSpent($user);
							if ($result < 0)
							{
								setEventMessages($object->error, $object->errors, 'errors');
								$error++;
								break;
							}
							
							$updateoftaskdone++;
			        	}
			        }
				}
				
				if (! $updateoftaskdone)  // Check to update progress if no update were done on task.
				{
				    $object->fetch($taskid);
	                //var_dump($object->progress);var_dump(GETPOST($taskid . 'progress', 'int')); exit;			    
				    if ($object->progress != GETPOST($taskid . 'progress', 'int'))
				    {
				        $object->progress = GETPOST($taskid . 'progress', 'int');
				        $result=$object->update($user);
				        if ($result < 0)
				        {
				            setEventMessages($object->error, $object->errors, 'errors');
				            $error++;
				            break;
				        }
				    }
				}
		    }

		   	if (! $error)
		   	{
		    	setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');

		   	    // Redirect to avoid submit twice on back
		       	header('Location: '.$_SERVER["PHP_SELF"].($projectid?'?id='.$projectid:'?').($mode?'&mode='.$mode:'').($day?'&day='.$day:'').($month?'&month='.$month:'').($year?'&year='.$year:''));
		       	exit;
		   	}
		}
	}



	/*
	 * View
	 */

	$form=new Form($db);
	$formother=new FormOther($db);
	$formcompany=new FormCompany($db);
	$formproject=new FormProjets($db);
	$projectstatic=new ProjectABCVC($db);
	$project = new ProjectABCVC($db);
	$taskstatic = new TaskABCVC($db);
	$thirdpartystatic = new Societe($db);

	$title=$langs->trans("TimeSpent");
	if ($mine) $title=$langs->trans("MyTimeSpent");

	$projectsListId = $projectstatic->getProjectsAuthorizedForUser($usertoprocess,0,1);  // Return all project i have permission on (assigned to me+public). I want my tasks and some of my task may be on a public projet that is not my project
	//var_dump($projectsListId);
	if ($id)
	{
	    $project->fetch($id);
	    $project->fetch_thirdparty();
	}

	$onlyopenedproject=1;	// or -1
	$morewherefilter='';
	if ($search_task_ref) $morewherefilter.=natural_search("t.ref", $search_task_ref);
	if ($search_task_label) $morewherefilter.=natural_search("t.label", $search_task_label);
	if ($search_thirdparty) $morewherefilter.=natural_search("s.nom", $search_thirdparty);
	$tasksarray=$taskstatic->getTasksArray(0, 0, ($project->id?$project->id:0), $socid, 0, $search_project_ref, $onlyopenedproject, $morewherefilter);   
	// We want to see all task of opened project i am allowed to see, not only mine. Later only mine will be editable later.
	$projectsrole=$taskstatic->getUserRolesForProjectsOrTasks($usertoprocess, 0, ($project->id?$project->id:0), 0, $onlyopenedproject);
	$tasksrole=$taskstatic->getUserRolesForProjectsOrTasks(0, $usertoprocess, ($project->id?$project->id:0), 0, $onlyopenedproject);
	//var_dump($tasksarray);
	//var_dump($projectsrole);
	//var_dump($taskrole);
	//exit();

	llxHeader("",$title,"",'','','',array('/core/js/timesheet.js'));

	//print_barre_liste($title, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $num, '', 'title_project');

	$param='';
	$param.=($mode?'&amp;mode='.$mode:'');
	$param.=($search_project_ref?'&amp;search_project_ref='.$search_project_ref:'');

	// Show navigation bar
	$nav ='<a class="inline-block valignmiddle" href="?year='.$prev_year."&amp;month=".$prev_month."&amp;day=".$prev_day.$param.'">'.img_previous($langs->trans("Previous"))."</a>\n";
	$nav.=" <span id=\"month_name\">".dol_print_date(dol_mktime(0,0,0,$first_month,$first_day,$first_year),"%Y").", ".$langs->trans("WeekShort")." ".$week." </span>\n";
	$nav.='<a class="inline-block valignmiddle" href="?year='.$next_year."&amp;month=".$next_month."&amp;day=".$next_day.$param.'">'.img_next($langs->trans("Next"))."</a>\n";
	$nav.=" &nbsp; (<a href=\"?year=".$nowyear."&amp;month=".$nowmonth."&amp;day=".$nowday.$param."\">".$langs->trans("Today")."</a>)";
	$nav.='<br>'.$form->select_date(-1,'',0,0,2,"addtime",1,0,1).' ';
	$nav.=' <input type="submit" name="submitdateselect" class="button" value="'.$langs->trans("Refresh").'">';

	$picto='calendarweek';
	?>
	
	
	<?php
	// BOOTSTRAP 3 + css + js custom
	require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/abcvc_js_css.php';
	?>

<form name="addtime" method="POST" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
	<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">
	<input type="hidden" name="action" value="addtime">
	<input type="hidden" name="mode" value="<?php echo $mode; ?>">
	<input type="hidden" name="day" value="<?php echo $day; ?>">
	<input type="hidden" name="month" value="<?php echo $month; ?>">
	<input type="hidden" name="year" value="<?php echo $year; ?>">
	<div class="container-fluid">
		<div class="row">
			<div class="panel panel-primary filterable">
	            <div class="panel-heading">
	                <?php print_barre_liste($title, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $num, '', 'title_project'); ?>
					<div class="container-fluid">
				        <div class="row">
				        <?php
				       		// Show description of content
				              if ($mine)
				              	{
				              		echo $langs->trans("MyTasksDesc").($onlyopenedproject?' '.$langs->trans("OnlyOpenedProject"):''); ?><br><?php
				          		}
								else
								{
									if ($user->rights->projet->all->lire && ! $socid) echo $langs->trans("ProjectsDesc").($onlyopenedproject?' '.$langs->trans("OnlyOpenedProject"):'');
									
									else echo $langs->trans("ProjectsPublicTaskDesc").($onlyopenedproject?' '.$langs->trans("AlsoOnlyOpenedProject"):''); ?><br><?php
								}  
								if ($mine)
								{
									echo $langs->trans("OnlyYourTaskAreVisible"); ?><br><?php
								}
								else
								{
									echo $langs->trans("AllTaskVisibleButEditIfYouAreAssigned"); ?><br><?php
								}
							?>    
				        </div>  
				    </div>  
	            </div>

	           	<div class="div-table-responsive">
		           	<table class="table table-responsive tagtable liste<?php echo ($moreforfilter?" listwithfilterbefore":""); ?>" id="tablelines3">
						<?php echo "\n"; ?>
						<?php
							$head=project_timesheet_prepare_head($mode);
							dol_fiche_head($head, 'inputperweek', '', 0, 'task');
							dol_fiche_end();
						?>
						<div class="floatright"><?php echo $nav; ?></div>   
							<?php // We move this before the assign to components so, the default submit button is not the assign to. ?>
							<div class="clearboth" style="padding-bottom: 8px;"></div>
								<tr class="liste_titre">
									<td><?php echo $langs->trans("RefTask"); ?></td>
									<td><?php echo $langs->trans("LabelTask"); ?></td>
									<td><?php echo $langs->trans("ProjectRef"); ?></td>
									<?php
										if (! empty($conf->global->PROJECT_LINES_PERWEEK_SHOW_THIRDPARTY))
										{
											?>
										    <td><?php echo $langs->trans("ThirdParty"); ?></td>
										    <?php
										}
									?>
									<td align="right" class="maxwidth75"><?php echo $langs->trans("PlannedWorkload"); ?></td>
									<td align="right" class="maxwidth75"><?php echo $langs->trans("ProgressDeclared"); ?></td>
									<td align="right" class="maxwidth75"><?php echo $langs->trans("TimeSpent"); ?></td>
									<?php
										if ($usertoprocess->id == $user->id) {
									?>
									<td align="right" class="maxwidth75"><?php echo $langs->trans("TimeSpentByYou"); ?></td>
									<?php
										}else {
									?>
									<td align="right" class="maxwidth75"><?php echo $langs->trans("TimeSpentByUser"); ?></td>
									<?php
									}
										$startday=dol_mktime(12, 0, 0, $startdayarray['first_month'], $startdayarray['first_day'], $startdayarray['first_year']);
										for($i=0;$i<7;$i++)
										{
										    ?>
										    <td width="6%" align="center" class="hide<?php echo $i; ?>">
										    <?php echo dol_print_date($startday + ($i * 3600 * 24), '%a'); ?>
										    <br>
										    <?php echo dol_print_date($startday + ($i * 3600 * 24), 'dayreduceformat'); ?>
										    </td>
										    <?php
										}
									?>
									<td></td>
								</tr>

								<?php echo "\n";?>

								<tr class="liste_titre">
									<td class="liste_titre"><input type="text" size="4" name="search_task_ref" value="<?php  echo dol_escape_htmltag($search_task_ref); ?>"></td>
									<td class="liste_titre"><input type="text" size="4" name="search_task_label" value="<?php  echo dol_escape_htmltag($search_task_label); ?>"></td>
									<td class="liste_titre"><input type="text" size="4" name="search_project_ref" value="<?php  echo dol_escape_htmltag($search_project_ref); ?>"></td>
									<?php 
										if (! empty($conf->global->PROJECT_LINES_PERWEEK_SHOW_THIRDPARTY)) 
									?>
									<td class="liste_titre" align="right"><input type="text" size="4" name="search_thirdparty" value="<?php echo dol_escape_htmltag($search_thirdparty); ?>"></td>
									<td class="liste_titre"></td>
									<td class="liste_titre"></td>
									<td class="liste_titre"></td>
									<td class="liste_titre"></td>
									<?php
										for($i=0;$i<7;$i++)
										{
									?>
									<td class="liste_titre"></td>
									    <?php
										}
									// Action column
										?>
									<td class="liste_titre nowrap" align="right">
									<?php 
										$searchpitco=$form->showFilterAndCheckAddButtons(0);
										echo $searchpitco;
									?>
									</td>
								</tr>

									<?php echo "\n";
									// By default, we can edit only tasks we are assigned to
									$restrictviewformytask=(empty($conf->global->PROJECT_TIME_SHOW_TASK_NOT_ASSIGNED)?1:0); ?>
								<?php
									if (count($tasksarray) > 0)
									{
									    //var_dump($tasksarray);
									    //exit();
									    //var_dump($tasksrole);
										$j=0;
										$level=0;
										// projectLinesPerWeek(&$inc, $firstdaytoshow, $fuser, $parent, $lines, &$level, &$projectsrole, &$tasksrole, $mine, $restricteditformytask=1, $var=false)
										//reindex array
										$tasksarray = array_values($tasksarray);
										projectLinesPerWeek($j, $firstdaytoshow, $usertoprocess, 0, $tasksarray, $level, $projectsrole, $tasksrole, 1, $restrictviewformytask);
										$colspan=7;
										if (! empty($conf->global->PROJECT_LINES_PERWEEK_SHOW_THIRDPARTY)) $colspan++;
								?>
								<tr class="liste_total">
					                <td class="liste_total" colspan="<?php echo $colspan; ?>" align="right"><?php echo $langs->trans("Total"); ?></td>
					                <td class="liste_total hide0" align="center"><div id="totalDay[0]">&nbsp;</div></td>
					                <td class="liste_total hide1" align="center"><div id="totalDay[1]">&nbsp;</div></td>
					                <td class="liste_total hide2" align="center"><div id="totalDay[2]">&nbsp;</div></td>
					                <td class="liste_total hide3" align="center"><div id="totalDay[3]">&nbsp;</div></td>
					                <td class="liste_total hide4" align="center"><div id="totalDay[4]">&nbsp;</div></td>
					                <td class="liste_total hide5" align="center"><div id="totalDay[5]">&nbsp;</div></td>
					                <td class="liste_total hide6" align="center"><div id="totalDay[6]">&nbsp;</div></td>
					                <td class="liste_total"></td>
							    </tr>
						    	<?php
									}
									else
									{
										?><tr><td colspan="11"><?php echo $langs->trans("NoTasks"); ?></td></tr><?php
									}
								?>
					</table>
					<input type="hidden" name="timestamp" value="1425423513"/><?php echo "\n";?>
					<input type="hidden" id="numberOfLines" name="numberOfLines" value="<?php echo count($tasksarray); ?>"/><?php echo "\n";?>
					<div class="center">
						<input type="submit" class="button" name="save" value="<?php echo dol_escape_htmltag($langs->trans("Save")); ?>">
					</div>
				</div>
			</div>
		</div>
	</div>
</form>

<!--
//**************************************************************************************************************
//
//
// MODAL Note(Task comment)
// 
// 
//**************************************************************************************************************
-->
<div class="modal fade" id="modal_task_comment">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4>New/Edit comment</h4>
			</div>
			
			<div class="modal-body">
				<form class="form-horizontal">	
					<div class="form-group">
						<label class="col-sm-3 control-label" for="exampleInputEmail1">Comment </label>
						<div class="col-sm-9">
							<textarea class="form-control" id="comment_c"></textarea>
						</div>	
					</div>
				</form>

			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-primary" id="bt_save_comment">Save</button>
				<button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<?php echo "\n\n";?>
	<?php

	$modeinput='hours';

	?>
	<script type="text/javascript">
		<?php
			print "jQuery(document).ready(function () {\n";
			print '		jQuery(".timesheetalreadyrecorded").tipTip({ maxWidth: "600px", edgeOffset: 10, delay: 50, fadeIn: 50, fadeOut: 50, content: \''.dol_escape_js($langs->trans("TimeAlreadyRecorded", $user->getFullName($langs))).'\'});';
			$i=0;
			while ($i < 7)
			{
				print '    updateTotal('.$i.',\''.$modeinput.'\');';
				$i++;
			}
			print "});";
		?>
	</script>

	<?php
	llxFooter();

	$db->close();
