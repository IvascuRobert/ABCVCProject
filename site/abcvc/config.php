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


dol_include_once('/abcvc/class/abcvcConfig.class.php');


// Load translation files required by the page
$langs->load("abcvc@abcvc");

// Get parameters
$rowid = GETPOST('rowid', 'int');
$action = GETPOST('action', 'alpha');
$cancel = GETPOST('cancel','alpha');

$label = GETPOST('label','alpha');
$value = GETPOST('value');

$active = GETPOST('active',"int");
//var_dump($rowid);

//tri / pagination
$sortfield	= GETPOST('sortfield','alpha');
$sortorder	= GETPOST('sortorder','alpha');
$page		= (int)GETPOST('page','int');

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
// Default action
if (empty($action) && empty($id) && empty($ref)) {
	$action='list';
}
//action cancel ??? fff retour en mode liste...
if ( $cancel == "Annuler" ) {
	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}
// update
if ($action == 'update') {
	if (! $cancel) {
		$object = new abcvcConfig($db);
		$object->rowid = $rowid;

		$object->label = trim($label);
		$object->value = $value;
		$object->active = $active;

		$object->update($user);

		header("Location: ".$_SERVER["PHP_SELF"]."?rowid=".$_POST["rowid"]);
		exit;
	}
}

// Load object if id or ref is provided as parameter
$object = new abcvcConfig($db);
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
llxHeader('', $langs->trans('ABCVC - Configuration'), '');
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

			$sql = "SELECT d.* FROM ".MAIN_DB_PREFIX."abcvc_config as d ORDER BY d.label ASC";
			$result = $db->query($sql);
			if ($result) {
				$num = $db->num_rows($result);
				$i = 0;
				?>
				<div class="panel panel-info filterable">
		            <div class="panel-heading">
		                <h1 class="panel-title">
							Configurare
							<div class="pull-right">
								<button class="btn btn-link btn-lg btn-filter"><span class="glyphicon glyphicon-filter"></span>Filtre</button>
							</div>
						</h1>
		            </div>
					<div class="panel-body">
						<p>Vizualizarea beneficiilor pe care le pot avea angajații interni.</p>
					</div>
		            <table class="table table-hover table-responsive">
		                <thead>
		                    <tr class="filters">
			                    <th><input type="text" class="form-control" placeholder=<?php echo $langs->trans("Ref interne"); ?> disabled></th>
								<th><input type="text" class="form-control" placeholder=<?php echo $langs->trans("Label"); ?> disabled></th>
								<th><input type="text" class="form-control" placeholder=<?php echo $langs->trans("Price"); ?> disabled></th>
								<th><input type="text" class="form-control" placeholder=<?php echo $langs->trans("Active"); ?> disabled></th>
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
				                        <td><a href="<?php echo $_SERVER["PHP_SELF"];?>?rowid=<?php echo $objp->rowid ?>"> <?php echo img_object('ref','bookmark').$objp->rowid; ?> </a></td>
										<td><?php echo dol_escape_htmltag($objp->label); ?> </td>
										<td><?php echo price($objp->value); ?> €</td>
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
		//  EDIT VIEW , fiche mode                                                                  
		// 
		//******************************************************************************************************
		if ($rowid > 0) { 
			$object = new abcvcConfig($db);
			$object->rowid = $rowid;
			$object->fetch($rowid); 
			?>
			<form method="post" action="<?php echo $_SERVER["PHP_SELF"];?>?rowid=<?php echo $rowid; ?>">
				<div class="panel panel-info filterable">
		            <div class="panel-heading">
		                <h3 class="panel-title">Modifică Configurația
							<div class="pull-right">
									<input  type="submit" href="/abcvc/zones.php?idmenu=87&mainmenu=abcvc&leftmenu=" class="button btn btn-link btn-lg" value="Enregistrer">
									<input name="cancel" class="button btn btn-link btn-lg" value="Annuler" type="submit"> <!--//onclick="history.go(-1)" --> 
							</div>
						</h3>
		            </div>
					<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">
					<input type="hidden" name="rowid" value="<?php echo $rowid; ?>">
					<input type="hidden" name="action" value="update">
					<table class="border"  width="100%">
						<tr><td width="15%"><?php echo $langs->trans("Ref"); ?> </td><td><?php echo $object->rowid; ?></td></tr>
						<tr><td><?php echo $langs->trans("Label");?></td><td><input type="text" name="label" size="40" value="<?php echo dol_escape_htmltag($object->label);?>"></td></tr>
						<tr><td><?php echo $langs->trans("Price");?></td><td><input type="text" name="value" size="40" value="<?php echo dol_escape_htmltag($object->value);?>"></td></tr>
					</table>
				</div>	
			</form>
			<?php	
		} 
		?>
	</div>
</div>

<?php
// End of page
llxFooter();

$db->close();