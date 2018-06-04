<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2016 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2010 Regis Houssin        <regis.houssin@capnetworks.com>
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
 *       \file       htdocs/projet/index.php
 *       \ingroup    projet
 *       \brief      Main project home page
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/class/dolgraph.class.php';


$langs->load("projects");
$langs->load("companies");

$mine = GETPOST('mode')=='mine' ? 1 : 0;

//var_dump($_POST);

// Security check
$socid=0;
//if ($user->societe_id > 0) $socid = $user->societe_id;    // For external user, no check is done on company because readability is managed by public status of project and assignement.
if (!$user->rights->projet->lire) accessforbidden();

$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');


$project_filter = GETPOST("project_filter",'alpha');
if($project_filter==''){
	$project_filter = 1;
}
//var_dump($project_filter);
/*
 * View
 */

$socstatic=new Societe($db);
$projectstatic=new ProjectABCVC($db);
$userstatic=new User($db);
$form=new Form($db);

$projectsListId = $projectstatic->getProjectsAuthorizedForUser($user,($mine?$mine:(empty($user->rights->projet->all->lire)?0:2)),1);
//var_dump($projectsListId);

llxHeader("",$langs->trans("Projects"),"EN:Module_Projects|FR:Module_Projets|ES:M&oacute;dulo_Proyectos");

// BOOTSTRAP 3 + css + js custom
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/abcvc_js_css.php';

$text=$langs->trans("ProjectsArea");
if ($mine) $text=$langs->trans("MyProjectsArea");

print load_fiche_titre($text,'','title_project.png');

?>
<div class="pull-right">
	<a class="btn btn-primary" style="color:#fff;" href="/abcvc/projet/card.php?leftmenu=abcvc&action=create">Nouveau projet</a>
</div>
<?php
// Show description of content
if ($mine) print $langs->trans("MyProjectsDesc").'<br><br>';
else {
	if (! empty($user->rights->projet->all->lire) && ! $socid) print $langs->trans("ProjectsDesc").'<br><br>';
	else print $langs->trans("ProjectsPublicDesc").'<br><br>';
}
?>


<?php 
//structure projets detaillé/precalculé
//----------------------------------------------------
$projectsTree = $projectstatic->getProjectsTree($user,$project_filter);
//var_dump($projectsTree);
//exit();


/*
 * Statistics
 */
?>
<div class="row-fluid">
	<?php 

	?>			
	<div class="col-md-6 ">
		<?php 

			include DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/graph_progression.inc.php';
		?>
	</div>

	<div class="col-md-6 ">
		<?php 
					
			include DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/graph_rentabilite.inc.php';
		?>
	</div>

</div>

<form class="form-horizontal" action="<?php echo $_SERVER["PHP_SELF"];?>" method="POST" name="formproject">
<div class="row-fluid">

	<div class="col-md-12 ">

		<table class="noborder nohover centpercent">
			<tbody>
				<tr class="liste_titre">
					<td >Projets  
						
						<select class="" name="project_filter" onchange="formproject.submit();">
							<option <?php echo ($project_filter==1)?' selected=""':''; ?> value="1">Ouverts</option>
							<option <?php echo ($project_filter==2)?' selected=""':''; ?> value="2">Cloturés</option>
							<option <?php echo ($project_filter=='*')?' selected=""':''; ?> value="*">Tous</option>

							<?php /*foreach ($allfactfourn as $key => $factfourn) : ?>
								<option value="<?php echo $factfourn->rowid; ?>" <?php echo (!in_array($factfourn->rowid,$allfactfourNONaffected))?' disabled="true" ':'' ?> ><?php echo $factfourn->ref; ?>&nbsp;<?php echo $factfourn->nom; ?></option>		          					
          					<?php endforeach;*/ ?>
						</select>
						
					</td>
				</tr>
				<tr class="impair">
					<td class="">
						
						<table class="table table-hover">
							<caption>
								<?php if ($project_filter==1):?>
									Synthèse des projets ouverts avec calculs rentabilité & progression
								<?php endif;?>
								<?php if ($project_filter==2):?>
									Synthèse des projets cloturés avec calculs rentabilité & progression
								<?php endif;?>
								<?php if ($project_filter=='*'):?>
									Synthèse de tout les projets avec calculs rentabilité & progression
								<?php endif;?>
							</caption>
							<?php 
								$total = 0; 
								$total_calculated = 0;
								$total_mo = 0; 
								$total_fact = 0; 
								$total_vente = 0;
								$total_marge = 0;
							?>
							<thead>
								<tr>
									<th width="24%">Projets</th>
									<th width="24%">Clients</th>
									<th width="8%" align="right" style="text-align: right;">Coûts estimés</th>
									<th width="8%" align="right" style="text-align: right;">Coûts calculés</th>
									<th width="8%" align="right" style="text-align: right;">Vente</th>
									<th width="8%" align="right" style="text-align: right;">Marge</th>
									<th width="10%"  style="text-align: right;">Avancement estimé</th>
									<th width="10%"  style="text-align: right;">Avancement réél</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ( $projectsTree as $key => $project) : ?>
									<?php  
										$total += $project->cost; 
										$total_calculated += $project->cost_calculated;
										$total_marge += $project->marge;
										$total_vente += $project->pv;
									?>

									<tr class="tr_poste">
										<td>
										<a 
										href = "/abcvc/projet/card.php?id=<?php echo $project->rowid;?>&mainmenu=abcvc&leftmenu="
										
										class = "link_edit_projet" 
										data-id = "<?php echo $project->rowid; ?>" 
										data-ref = "<?php echo $project->ref; ?>"
										data-title = "<?php echo $project->title; ?>" 

										data-plannedworkload = "<?php echo $project->planned_workload;?>"
										data-calculatedworkload = "<?php echo $project->calculated_workload;?>"

										data-zone = "<?php echo $project->fk_zones; ?>"

										data-progress_estimated = "<?php echo $project->progress_estimated; ?>"
										data-progress="<?php echo $project->progress; ?>"

										><?php echo $project->ref; ?><br /><?php echo $project->title; ?></a>				
										</td>

										<td align="">
											<a href="/comm/card.php?socid=<?php echo $project->fk_soc;?>">
											<?php echo $project->code_client; ?><br />
											<?php echo $project->nom; ?><?php echo ($project->name_alias!='')?' ('.$project->name_alias.')':'';?>
											</a>
										</td>	

										<td align="right"><?php echo price($project->cost); ?>€</td>						
										<td align="right">
											<b> <?php echo price($project->cost_calculated); ?>€</b>
										</td>

										<td align="right"><?php echo price($project->pv); ?>€</td>

										<td align="right"><?php 
												if($project->marge>0){
												echo price($project->marge).'€'; 
											} else {
												echo '<span style="color:red;">'.price($project->marge).'€</span>'; 
											}
										?></td>

										<td align="right">
											<div class="progress">
												<?php 
												if($project->progress_estimated<80){
													$progress_color = 'progress-bar-success';
												} elseif($project->progress_estimated<100){
													$progress_color = 'progress-bar-warning';
												} elseif($project->progress_estimated ==100){
													$progress_color = 'progress-bar-info';
												} else {
													$progress_color = 'progress-bar-danger';
												}
												?>
											  	<div class="progress-bar <?php echo $progress_color;?>" role="progressbar" aria-valuenow="<?php echo $project->progress_estimated; ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo ($project->progress_estimated<=100)?$project->progress_estimated:'100'; ?>%;">
											    	<?php echo $project->progress_estimated; ?>%
											  	</div>
											</div>
										</td>
										<td align="right">
											<div class="progress">
												<?php 
												if($project->progress<80){
													$progress_color = 'progress-bar-success';
												} elseif($project->progress<100){
													$progress_color = 'progress-bar-warning';
												} elseif($project->progress ==100){
													$progress_color = 'progress-bar-info';
												} else {
													$progress_color = 'progress-bar-danger';
												}
												?>
									  			<div class="progress-bar <?php echo $progress_color;?>" role="progressbar" aria-valuenow="<?php echo $project->progress; ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo ($project->progress<=100)?$project->progress:'100'; ?>%;">
									    			<?php echo $project->progress; ?>%
									  			</div>
											</div>
										</td>
									</tr>

							<?php endforeach; ?>
							
								<tr>
							        <td colspan="2"><b> Total projets </b></td>
							        <td align="right"><b><?php echo price($total)?>€</b></td>
							        <td align="right"><b><?php echo price($total_calculated)?>€</b></td>
							        <td align="right"><b><?php echo price($total_vente)?>€</b></td>
							        <td align="right"><?php 
										//$marge_total = $total_vente-$total;
										if($total_marge>0){
											echo '<b>'.price($total_marge).'€</b>'; 
										} else {
											echo '<span style="color:red;">'.price($total_marge).'€</span>'; 
										}
									?></td>
							        <td  align="right"></td>
									<td  align="right"></td>
							    </tr>
							</tbody>
						</table>

					</td>
				</tr>
			</tbody>
		</table>

	</div>

</div>	
</form>


<?php
llxFooter();

$db->close();