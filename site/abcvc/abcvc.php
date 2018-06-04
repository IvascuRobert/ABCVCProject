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

//if (! defined('NOREQUIREUSER'))	define('NOREQUIREUSER','1');
//if (! defined('NOREQUIREDB'))		define('NOREQUIREDB','1');
//if (! defined('NOREQUIRESOC'))	define('NOREQUIRESOC','1');
//if (! defined('NOREQUIRETRAN'))	define('NOREQUIRETRAN','1');
// Do not check anti CSRF attack test
//if (! defined('NOCSRFCHECK'))		define('NOCSRFCHECK','1');
// Do not check style html tag into posted data
//if (! defined('NOSTYLECHECK'))   define('NOSTYLECHECK','1');
// Do not check anti POST attack test
//if (! defined('NOTOKENRENEWAL'))	define('NOTOKENRENEWAL','1');
// If there is no need to load and show top and left menu
//if (! defined('NOREQUIREMENU'))	define('NOREQUIREMENU','1');
// If we don't need to load the html.form.class.php
//if (! defined('NOREQUIREHTML'))	define('NOREQUIREHTML','1');
//if (! defined('NOREQUIREAJAX'))	define('NOREQUIREAJAX','1');
// If this page is public (can be called outside logged session)
//if (! defined("NOLOGIN"))			define("NOLOGIN",'1');


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
		$myobject->description = trim($description);
		$result = $myobject->create($user);
		if ($result > 0) {
			// Creation OK
			header("Location: ".$_SERVER["PHP_SELF"]);
			exit;
		} {
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
 * Put here all code to build page
 */

llxHeader('', $langs->trans('ABCVC - Métal'), '');

$form = new Form($db);


/* Liste mode                                                              */
/* ************************************************************************** */
if (! $rowid && $action != 'create' && $action != 'edit') {
	//echo 'Mode liste ....';
	
	
	echo load_fiche_titre('Liste des métaux');	
	

	$sql = "SELECT d.*";
	$sql.= " FROM ".MAIN_DB_PREFIX."abcvc_sites as d";

	$result = $db->query($sql);
	if ($result) 
	{
		$num = $db->num_rows($result);
		$i = 0;
		

		ob_start();
		?>

		<table class="noborder" width="100%">

			<tr class="liste_titre">
			<td><?php echo $langs->trans("Ref"); ?></td>
			<td><?php echo $langs->trans("Label"); ?></td>
			<td align="center"><?php echo $langs->trans("Actif"); ?></td>
			<td>&nbsp;</td>
			</tr>

			<?php

			$var=True;
			while ($i < $num) 
			{
				$objp = $db->fetch_object($result);
				$var=!$var;
				?>
				<tr <?php echo $bc[$var]; ?> >
				<td><a href="<?php echo $_SERVER['PHP_SELF']; ?>?rowid=<?php echo $objp->rowid; ?>"> <?php echo img_object('ref','product').$objp->rowid; ?></a></td>
				<td><?php echo dol_escape_htmltag($objp->label); ?></td>
				<td align="center"><?php echo yn($objp->active); ?></td>

				
					<!--if ($user->rights->adherent->configurer)
						print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=edit&rowid='.$objp->rowid.'">'.img_edit().'</a></td>';
					else-->
				<td align="right">&nbsp;</td>

				</tr>
				<?php
				$i++;
			}
			?>
		</table>

		<?php

		$output=ob_get_clean(); 
		echo $output;

		
	} else {
		dol_print_error($db);
	}	
}



/* Creation mode                                                              */
/* ************************************************************************** */
if ($action == 'create') {

	ob_start();
	?>
	<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?> ">
	<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?> ">
	<input type="hidden" name="action" value="add">
	
	<?php
	echo load_fiche_titre('Nouveau métal');	
	?>

	<table class="border" width="100%">

	<tr><td width="15%"><?php echo $langs->trans("Ref"); ?></td><td> - </td></tr>

	<tr><td><?php echo $langs->trans("Label"); ?></td><td><input type="text" name="label" size="40" value=""></td></tr>

	<tr><td><?php echo $langs->trans("Actif"); ?></td><td>
	
	<?php
	echo $form->selectyesno("active",$_POST['active'],1);
	/*
	?>

	<select name="taskOption">
 	<option value="1">Yes</option>
  	<option value="0">No</option>
    </select>
    
    <?php
    $selectyesno = $_POST['taskOption'];*/
    ?>

	</td></tr>


	<tr><td valign="top"><?php echo $langs->trans("Description"); ?></td><td>
	<textarea name="description" wrap="soft" class="centpercent" rows="3"></textarea></td></tr>

	</table>

	<div class="center">
	<input type="submit" class="button" value="Save"><?php $langs->trans("Save"); ?>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<input name="cancel" class="button" value="Annuler" type="submit"> <!--onclick="history.go(-1)-->

	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<button onclick="history.go(-1)">Click me for listing</button>

	</div>

	</form>

	<?php
	
	$output=ob_get_clean(); 
	echo $output;
}

/* fiche mode                                                                  */
/* ************************************************************************** */
if ($rowid > 0) {
	//echo 'Mode fiche ....';

	//if ($action != 'edit') {
	//}
	//if ($action == 'edit') {
	//}
	

	$object = new abcvcSites($db);
	$object->rowid = $rowid;
	$object->fetch($rowid);
	//var_dump($object);

	ob_start();
	?>
	<form method="post" action="<?php echo $_SERVER['PHP_SELF']?> ?rowid= <?php $rowid; ?>">
	<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>"> 
	<input type="hidden" name="rowid" value="<?php echo $rowid; ?>"> 
	<input type="hidden" name="action" value="update">

	<?php echo load_fiche_titre('Métal');	
	?>


	<table class="border" width="100%">'

	<tr><td width="15%"><?php echo $langs->trans("Ref"); ?> </td><td><?php echo $object->rowid?></td></tr>

	<tr><td><?php echo $langs->trans("Label"); ?> </td><td> <input type="text" name="label" size="40" value="<?php echo dol_escape_htmltag($object->label)?>"></td></tr>

	<tr><td><?php echo $langs->trans("Actif"); ?></td><td>
	
	<?php
	echo $form->selectyesno("active",$object->active,1);
	?>

	</td></tr>


	<tr><td valign="top"><?php echo $langs->trans("Description"); ?></td><td>
	<textarea name="description" wrap="soft" class="centpercent" rows="3"><?php echo $object->description ?></textarea></td></tr>

	</table>

	<div class="center">
	<input type="submit" class="button" value="<?php echo $langs->trans("Save"); ?> ">
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

	<input name="cancel" class="button" value="Annuler" type="submit"><!--onclick="history.go(-1)"-->

	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<a class="butActionDelete" href="<?php echo $_SERVER["PHP_SELF"]; ?>?action=delete&amp;rowid=<?php echo $rowid; ?>">Supprimer</a>

	</div>

	</form>
	

	<?php

	$output=ob_get_clean(); 
	echo $output;

}




// End of page
llxFooter();

$db->close();