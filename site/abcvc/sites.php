<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) <year>  <name of author>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    abcvc.php
 * \ingroup abcvc
 * \brief   abcvc
 *
 * Put detailed description here.
 */



// Load Dolibarr environment
if (false === (@include '../main.inc.php')) {  // From htdocs directory
	require '../../../main.inc.php'; // From "custom" directory
}

global $db, $langs, $user;


dol_include_once('/abcvc/class/abcvcSites.class.php');


// Load translation files required by the page
$langs->load("abcvc@abcvc");

// Get parameters
$rowid = GETPOST('rowid', 'int');
$action = GETPOST('action', 'alpha');
$cancel = GETPOST('cancel','alpha');

$label = GETPOST('label','alpha');
$id_zone = GETPOST('id_zone','int');
$active = GETPOST('active',"int");
$description = GETPOST('description');


//tri / pagination
$sortfield	= GETPOST('sortfield','alpha');
$sortorder	= GETPOST('sortorder','alpha');
$page		= GETPOST('page','int');
if ($page == -1) { $page = 0 ; }
$offset = $conf->liste_limit * $page ;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortorder) {  $sortorder="DESC"; }
if (! $sortfield) {  $sortfield="d.lastname"; }


// Access control
if ($user->socid > 0) {
	// External user
	accessforbidden();
}

//ACTIONS
//--------------------------------------------------------

//var_dump($_POST);
//exit;
/*array (size=7)
  'token' => string 'c07ac0830c35e9781443d71f15dc465c' (length=32)
  'rowid' => string '2' (length=1)
  'action' => string 'update' (length=6)
  'label' => string 'Argent' (length=6)
  'active' => string '1' (length=1)
  'description' => string '' (length=0)
  'cancel' => string 'Annuler' (length=7)*/




// Default action
if (empty($action) && empty($id) && empty($ref)) {
	$action='list';
}

//action cancel ??? fff retour en mode liste...
if ( $cancel == "Annuler" ) {
	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}
// add
if ($action == 'add') {

	if (! $cancel) {	
		$myobject = new abcvcSites($db);
		$myobject->label = trim($label);
		$myobject->active = $active;		
		$myobject->id_zone = $id_zone;
		$myobject->description = trim($description);
		$result = $myobject->create($user);
		if ($result > 0) {
			// Creation OK
			header("Location: ".$_SERVER["PHP_SELF"]);
			exit;
		} else {
			// Creation KO
			$mesg = $myobject->error;
			$action = 'create';
		}
	}	
}

// update
if ($action == 'update') {
	if (! $cancel) {
		$object = new abcvcSites($db);
		$object->rowid     = $rowid;
		$object->label     = trim($label);
		$object->active  	= $active;
		$object->id_zone = $id_zone;
		$object->description  = trim($description);
		$object->update($user);

		header("Location: ".$_SERVER["PHP_SELF"]."?rowid=".$_POST["rowid"]);
		exit;
	}
}

// delete
if ($action == 'delete') {
	$object = new abcvcSites($db);
	$object->rowid     = $rowid;	
	$object->delete($user);
	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}



// Load object if id or ref is provided as parameter
$object = new abcvcSites($db);
if (($rowid > 0 || ! empty($ref)) && $action != 'add') {
	$result = $object->fetch($rowid, $ref);
	if ($result < 0) {
		dol_print_error($db);
	}
}


/*
 * VIEW
 *
 * Put here all code to build page*/
llxHeader('', $langs->trans('ABCVC - Sites'), '');

$form = new Form($db);


// BOOTSTRAP 3 + css + js custom
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/abcvc_js_css.php';
?>


<div class="container-fluid">
	<div class="row">


		<?php
		//******************************************************************************************************
		// 
		// Liste mode
		// 
		//******************************************************************************************************
		if (!$rowid && $action != 'create' && $action != 'edit') {

			$sql = 'SELECT d.*,z.label as zone_label, z.price,z.kilometers
					FROM llx_abcvc_sites as d 
					LEFT JOIN llx_abcvc_zones as z ON (d.id_zone = z.rowid)
					ORDER BY d.label ASC';

			$result = $db->query($sql);
			if ($result) {
				$num = $db->num_rows($result);
				$i = 0;

				?>

				<div class="panel panel-primary filterable">
			            <div class="panel-heading">
			                <h1 class="panel-title">Construction sites list</h1>
							<div class="container-fluid">
						        <div class="row">
				                    <div class="pull-left">
				                        <a href="<?php echo $_SERVER["PHP_SELF"] ?>?action=create" style="color:black" class="btn btn-default btn-sm"><span class="glyphicon glyphicon-plus"></span>New site</a>
				                    </div>

				                    <div class="pull-left">
					                    <button class="btn btn-default btn-sm btn-filter"><span class="glyphicon glyphicon-filter"></span>Filter</button>
					                </div>
						        </div>  
						    </div>  
			            </div>

			            <table class="table table-responsive">
			                <thead>
			                    <tr class="filters">
				                    <th><input type="text" class="form-control" placeholder=<?php echo $langs->trans("Ref"); ?> disabled></th>
									<th><input type="text" class="form-control" placeholder=<?php echo $langs->trans("Label"); ?> disabled></th>
									<th><input type="text" class="form-control" placeholder=<?php echo $langs->trans("Zone"); ?> disabled></th>
									<th><input type="text" class="form-control" placeholder=<?php echo $langs->trans("Price / Kilometers"); ?> disabled></th>
									<th><input type="text" class="form-control" placeholder=<?php echo $langs->trans("Actif"); ?> disabled></th>
			                   	</tr>
			                </thead>
			                <tbody>
								<?php 
									$var=True;
									while ($i < $num) {
										$objp = $db->fetch_object($result);
										$var=!$var; 
										?>

					                    <tr <?php echo $bc[$var]; ?> >
					                        <td><a href="<?php echo $_SERVER["PHP_SELF"];?>?rowid=<?php echo $objp->rowid ?>"> <?php echo img_object('ref','product').$objp->rowid; ?> </a></td>
											<td><?php echo dol_escape_htmltag($objp->label); ?> </td>
											<td><?php echo dol_escape_htmltag($objp->zone_label); ?> </td>
											<td><?php echo dol_escape_htmltag($objp->price); ?> / <?php echo dol_escape_htmltag($objp->kilometers); ?> </td>
											<td><?php echo yn($objp->active); ?> </td>
					                    </tr>	    

										<?php 
										$i++; 
									} 
								?>
							</tbody>						
			            </table>
				</div>


			<?php						
		    } else {
			    echo dol_print_error($db);
			} 

		}
		


		//******************************************************************************************************
		//
		// creation mode                                    
		// 
		//******************************************************************************************************
		if ($action == 'create'){ ?>

			<form method="post" action="<?php echo $_SERVER["PHP_SELF"] ?>">

				<div class="panel panel-primary filterable">
		            <div class="panel-heading">
		                <h3 class="panel-title">New Site</h3>
						<div class="container-fluid">
					        <div class="row">
								<div class="pull-left">
									<input type="submit" class="button btn btn-success" value= "Save"><?php $langs->trans("Save"); ?>
									<input name="cancel" class="button btn btn-warning" value="Annuler" type="submit"> <!-- onclick="history.go(-1)" -->
								</div>
					        </div>  
					    </div>  
		            </div>

					<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken'] ?>">
					<input type="hidden" name="action" value="add">

					<table class="border" width="100%">

					<tr><td width="15%"><?php echo $langs->trans("Ref"); ?></td><td> - </td></tr>

					<tr><td><?php echo $langs->trans("Label"); ?></td><td><input type="text" name="label" size="40" value=""></td></tr>

					<tr><td><?php echo $langs->trans("Actif"); ?></td><td>
					<?php echo $form->selectyesno("active",$_POST['active'],1);?>
					</td></tr>

					<tr><td><?php echo $langs->trans("Description"); ?></td><td><textarea name="description" wrap="soft" class="centpercent" rows="3"></textarea></td></tr>

					<tr><td><?php echo $langs->trans("Select zones"); ?></td><td><?php 
					// GET ZONES
					$zones_db = $object->getZones();	
					//var_dump($zones_db);
					?>

					<select name="id_zone" >

						<?php foreach ($zones_db as $key => $zone_db): ?>
							
							<option value="<?php echo $zone_db->rowid ;?>"><?php echo $zone_db->label.' ('.$zone_db->kilometers.')' ;?></option>	

						<?php endforeach ?>

					</select></td></tr>

					

					</table>

					
				</div>

			</form>

			<?php						
		}


		//******************************************************************************************************
		//
		// fiche mode                                                                  
		// 
		//******************************************************************************************************
		if ($rowid > 0) { 

			$object = new abcvcSites($db);
			$object->rowid = $rowid;
			$object->fetch($rowid); ?>


			<form method="post" action="<?php echo $_SERVER["PHP_SELF"];?>?rowid=<?php echo $rowid; ?>">
				<div class="panel panel-primary filterable">
		            <div class="panel-heading">
		                <h3 class="panel-title">Edit site</h3>
						<div class="container-fluid">
			        		<div class="row">
								<div class="pull-left">
									<input  type="submit" href="http://abcvc.robert.ro/abcvc/zones.php?idmenu=87&mainmenu=abcvc&leftmenu=" class="button btn btn-success btn-sm" value="Save"><?php $langs->trans("Save"); ?> 
								
									<input name="cancel" class="button btn btn btn-warning btn-sm" value="Annuler" type="submit"> <!--//onclick="history.go(-1)" --> 
								
									<a class="btn btn-danger btn-sm bt_delete" href="<?php echo $_SERVER["PHP_SELF"];?>?action=delete&amp;rowid=<?php echo $rowid; ?>"><span class="glyphicon glyphicon-remove"></span>Supprimer</a>
								</div>
				    		</div>  
				    	</div>
		            </div>
					<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">
					<input type="hidden" name="rowid" value="<?php echo $rowid; ?>">
					<input type="hidden" name="action" value="update">
					

					<table class="border" width="100%">

						<tr><td width="15%"><?php echo $langs->trans("Ref"); ?> </td><td><?php echo $object->rowid; ?></td></tr>

						<tr><td><?php echo $langs->trans("Label");?></td><td><input type="text" name="label" size="40" value="<?php echo dol_escape_htmltag($object->label);?>"></td></tr>

						<tr><td><?php echo $langs->trans("Actif");?></td><td>
						<?php echo $form->selectyesno("active",$object->active,1); ?>
						</td></tr>

						<tr><td><?php echo $langs->trans("Description");?></td><td><textarea name="description" wrap="soft" class="centpercent" rows="3"><?php echo $object->description; ?></textarea></td></tr>

						<tr><td><?php echo $langs->trans("Select zones");?></td><td><?php 
						// GET ZONES
						$zones_db = $object->getZones();	
						//var_dump($zones_db);
						?>

						<select name="id_zone" >

							<?php foreach ($zones_db as $key => $zone_db): ?>

								<option value="<?php echo $zone_db->rowid ;?>" <?php echo ( $zone_db->rowid == $object->id_zone)?" selected=''":"";?>><?php echo $zone_db->label.' ('.$zone_db->kilometers.')' ;?></option>	

							<?php endforeach ?>

						</select></td></tr>

					</table>

				</div>	
			</form>

			<?php						
			//**************************************************************************************************************

		} 
		?>
	</div>
</div>

<?php
// End of page
llxFooter();

$db->close();