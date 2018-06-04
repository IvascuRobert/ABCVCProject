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



dol_include_once('/abcvc/class/abcvcMatiere.class.php');

dol_include_once('/abcvc/class/abcvcConcentration.class.php');

$obj_matiere = new abcvcMatiere($db);

$matieres_actives = $obj_matiere->getabcvc(0); //0 = matieres full, y compris non active

/*var_dump( $matieres_actives );
array (size=5)
  1 => string 'Or' (length=2)
  2 => string 'Argent' (length=6)
  3 => string 'Palladium' (length=9)
  4 => string 'Cuivre' (length=6)
  6 => string 'Platine' (length=7*/



// Load translation files required by the page
$langs->load("abcvc@abcvc");

// Get parameters
$rowid = GETPOST('rowid', 'int');
$action = GETPOST('action', 'alpha');
$cancel = GETPOST('cancel','alpha');

$label = GETPOST('label','alpha');
$id_categorie = GETPOST('id_categorie',"int");
$old_label = GETPOST('old_label','alpha');

$active = GETPOST('active',"int");
$structure = GETPOST('structure');
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


//--------------------------------------------------------
//ACTIONS
//--------------------------------------------------------

//var_dump($_POST);
/*array (size=7)
  'token' => string 'c07ac0830c35e9781443d71f15dc465c' (length=32)
  'rowid' => string '2' (length=1)
  'action' => string 'update' (length=6)
  'label' => string 'Argent' (length=6)
  'active' => string '1' (length=1)
  'description' => string '' (length=0)
  'cancel' => string 'Annuler' (length=7)*/






//temp action
//---------------------------
if ($action == 'test') {


	//products
	//--------------------------------------------------------
	$sql = "SELECT ";
	$sql.= " t.rowid, t.ref, t.label";
	$sql.= " FROM " . MAIN_DB_PREFIX . "product as t";
	//$sql.= " WHERE t.label = '" . $this->label. "'";
	$result = $db->query($sql);

	$list_products = array();
	$list_products_ok = array();
	$list_products_ko = array();
	$codes_concent = array();
	if ($result) {
		$num = $db->num_rows($result);
		$i = 0;
		while ($i < $num) {
			$objp = $db->fetch_object($result);
			$list_products[] = $objp;
			$i++;
		}
	}	

	$concents = array(
		'OJ ', 'OG ', 'AG ', 'Argent '
	);

	foreach ($list_products as $product) {
		$ok_code = false;
		foreach ($concents as $concent) {
			$idx = strpos($product->label, $concent);
			if($idx !== false){
				$code = substr($product->label,$idx);
				$ok_code = true;
				$codes_concent[$code]['product'][]=$product->rowid;
				$list_products_ok[] = $product;
			}
		}
		if(!$ok_code){
			$list_products_ko[] = $product;
		}
	}



	//création concentration
	foreach ($codes_concent as $code_concent => &$struct ) {
		
		$sql = "INSERT INTO " . MAIN_DB_PREFIX . "abcvc_concentrations (";
		$sql.= " label,";
		$sql.= " active";
		$sql.= ") VALUES (";
		$sql.= " '" . $code_concent . "',";
		$sql.= " '1'";
		$sql.= ")";
		$resql = $db->query($sql);

		//categorie associé...
		$sql = "INSERT INTO " . MAIN_DB_PREFIX . "categorie (";
		$sql.= " label,";
		$sql.= " type,";
		$sql.= " description";

		$sql.= ") VALUES (";
		$sql.= " '" . $code_concent . "',";
		$sql.= " '0',";
		$sql.= " '" . $code_concent . "'";
		$sql.= ")";
		$resql = $db->query($sql);

		$id_categorie = $db->last_insert_id(MAIN_DB_PREFIX . "categorie");

		$struct['id'] = $id_categorie;
	}
	unset($struct);


	//création affectation product - concent
	foreach ($codes_concent as $code_concent => $struct ) {
		$id_categorie = $struct['id'];
		foreach ($struct['product'] as $id_product) {

			$sql = "INSERT INTO " . MAIN_DB_PREFIX . "categorie_product (";
			$sql.= " fk_categorie,";
			$sql.= " fk_product";
			$sql.= ") VALUES (";
			$sql.= " '".$id_categorie."',";
			$sql.= " '".$id_product."'";
			$sql.= ")";
			$resql = $db->query($sql);

		}
	}


	//concents & assoc créées:
	var_dump($codes_concent);
	//var_dump($list_products_ok);
	//var_dump($list_products_ko);




	var_dump("test");
	exit();
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


// add
if ($action == 'add') {
	if (! $cancel) {	
		$myobject = new abcvcConcentration($db);
		$myobject->label = trim($label);
		$myobject->active = $active;		
		$myobject->structure = $structure;
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
		$object = new abcvcConcentration($db);
		$object->rowid     = $rowid;
		$object->label     = trim($label);
		$object->id_categorie     = $id_categorie;		
		$object->old_label     = $old_label;		
		$object->active  	= $active;
		$object->structure  	= $structure;
		$object->description  = trim($description);
		$object->update($user);

		header("Location: ".$_SERVER["PHP_SELF"]."?rowid=".$_POST["rowid"]);
		exit;
	}
}

// delete
if ($action == 'delete') {
	$object = new abcvcConcentration($db);
	$object->fetch($rowid);
	//$object->rowid     = $rowid;	
	$object->delete($user);
	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}



// Load object if id or ref is provided as parameter
$object = new abcvcConcentration($db);
if (($rowid > 0 || ! empty($ref)) && $action != 'add') {
	$result = $object->fetch($rowid, $ref);
	if ($result < 0) {
		dol_print_error($db);
	}
}




/*
 **************************************************************************************************************
 *
 * VIEW
 *
 **************************************************************************************************************
 */

llxHeader('', $langs->trans('ABCVC - Concentration'), '');

$form = new Form($db);


ob_start();
//**************************************************************************************************************
// injection CSS/JS contexte
//**************************************************************************************************************
?>

<link rel="stylesheet" href="/abcvc/css/font-awesome-4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="/abcvc/js/bootstrap-3.3.7/css/bootstrap.min.css">

<script src="/abcvc/js/bootstrap-3.3.7/js/bootstrap.min.js" type="text/javascript" charset="utf-8" async defer></script>


<script type="text/javascript">

	var concentrations = null;

	var matieres_actives = <?php echo json_encode($matieres_actives);?>;

	
	$(document).ready(function(){

		$('#bt_save_concentration').on('click',function(e){
			e.preventDefault();

			var tb_concentrations = [];
			var ids_matieres = [];
			$('#concentrations_list tbody tr').each(function(i,el){
				var id_matiere = $('.concentration_matiere',el).val();
				var montant = $('.concentration_montant',el).val();
				//console.log(id_matiere,montant );
				if( (id_matiere!=0) && (montant!='') ){
				
					//pas déja ajouté ?
					if( ids_matieres.indexOf(id_matiere) == -1 ){

						tb_concentrations.push({ 
							id_matiere : id_matiere,
							montant : montant
						}); 

						ids_matieres.push(id_matiere);
					}

				}	
				//[{"id_matiere":"3","montant":"300"},{"id_matiere":"1","montant":"100"}]
			}); 
			console.log( tb_concentrations );

			$('#structure_json').val( JSON.stringify(tb_concentrations, null, 2) );

			//gogogo
			$('#adminForm').submit();

		});


		// ajout concentration
		//------------------------------------------------------------
		$('#add_concentration').on('click',function(e){

			e.preventDefault();
			var idxnewRow = $('#concentrations_list tbody tr').length; 
			if (isNaN(idxnewRow)) idxnewRow = 0;

			var newRow = '';
			newRow += '<tr data-row="'+idxnewRow+'" data-id="0">';

			newRow += '<td width="60%">';

			newRow += '	<select name="concentrations[id_matiere][]" class="concentration_matiere input-block-level required ">';	
			newRow += '	 <option value="0">Choisir un métal</option>';
			$.each(matieres_actives, function(id_matiere,label_matiere) {
				newRow += '	 <option class="" value="'+id_matiere+'">'+label_matiere+'</option>';
			});
			newRow += '	</select>';

			newRow += '</td>';
			newRow += '<td width="30%">';
			newRow += '	<input name="concentrations[concentration][]" value="" class="concentration_montant input-block-level required " min="0" max="1000" type="number">	';
			newRow += '</td>';
			newRow += '<td width="10%" >';
			newRow += '	<div class="del_concentration btn btn-mini btn-danger"><i class="fa fa-trash" aria-hidden="true"></i> </div>';
			newRow += '</td>';

			newRow += '</tr>';
			$('#concentrations_list tbody').append(newRow);

			//event bt del
			$('#concentrations_list tr[data-row="'+idxnewRow+'"] .del_concentration').on('click',function(e){
				if( confirm('Confirmez-vous la suppression de cette concentration ?') ){
					$(this).parent().parent().remove();
				}
			});

		});



		$('.del_concentration').on('click',function(e){
			if( confirm('Confirmez-vous la suppression de cette concentration ?') ){
				$(this).parent().parent().remove();
			}
		});


		$("#bt_del_concentration").on('click',function(e){
			e.preventDefault();
			if( confirm('Confirmez-vous la suppression de ce set de concentration ?') ){
				//console.log( $(this).attr('href') );
				document.location = $(this).attr('href');
			}
		});


	});

</script>

<style type="text/css" media="screen">
	.bloc_concentration_liste{
		font-size: 12px;
		font-style: italic;

	}
</style>

<?php						
//**************************************************************************************************************
$output = ob_get_clean();
print $output;



/* Liste mode                                                              */
/* ************************************************************************** */
if (! $rowid && $action != 'create' && $action != 'edit') {
	//echo 'Mode liste ....';

	print load_fiche_titre('Liste des concentrations');	

	$sql = "SELECT d.*";
	$sql.= " FROM ".MAIN_DB_PREFIX."abcvc_concentrations as d";

	$result = $db->query($sql);
	if ($result) {
		$num = $db->num_rows($result);
		$i = 0;

		print '<table class="noborder" width="100%">';

		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("Ref").'</td>';
		print '<td>'.$langs->trans("Label").'</td>';
		print '<td>'.$langs->trans("Concentrations").'</td>';

		print '<td align="center">'.$langs->trans("Actif").'</td>';
		print '<td>&nbsp;</td>';
		print "</tr>\n";

		$var=True;
		while ($i < $num) {
			$objp = $db->fetch_object($result);
			$var=!$var;
			print "<tr ".$bc[$var].">";
			print '<td><a href="'.$_SERVER["PHP_SELF"].'?rowid='.$objp->rowid.'">'.img_object('ref','product').' '.$objp->rowid.'</a></td>';
			print '<td>'.dol_escape_htmltag($objp->label).'</td>';

			print '<td class="bloc_concentration_liste">';
			$concentrations = json_decode($objp->structure);
			if(!is_null($concentrations)){
				foreach ($concentrations as $concentration) {

					print $matieres_actives[$concentration->id_matiere].' (<strong>'.$concentration->montant.'</strong>/1000e) <br />';
				}
			} else {
				print " ? ";
			}

			print '</td>';

			print '</td>';
			

			print '<td align="center">'.yn($objp->active).'</td>';

			
			//if ($user->rights->adherent->configurer)
			//	print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=edit&rowid='.$objp->rowid.'">'.img_edit().'</a></td>';
			//else
				print '<td align="right">&nbsp;</td>';

			print "</tr>";
			$i++;
		}
		print "</table>";
	}
	else
	{
		dol_print_error($db);
	}

}

/* Creation mode                                                              */
/* ************************************************************************** */
if ($action == 'create') {

	print '<form id="adminForm" method="post" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="add">';

	print load_fiche_titre('Nouveau set de concentration');	


	print '<table class="border" width="100%">';

	print '<tr><td width="15%">'.$langs->trans("Ref").'</td><td> - </td></tr>';

	print '<tr><td>'.$langs->trans("Label").'</td><td><input type="text" name="label" size="40" value=""></td></tr>';

	print '<tr><td>'.$langs->trans("Actif").'</td><td>';
	print $form->selectyesno("active",1,1);
	print '</td></tr>';


	print '<tr><td valign="top">'.$langs->trans("Description").'</td><td>';
	print '<textarea name="description" wrap="soft" class="centpercent" rows="3"></textarea></td></tr>';


	print '<tr style="display:none;"><td valign="top">'.$langs->trans("Structure").'</td><td>';
	print '<textarea name="structure" wrap="soft" id="structure_json" class="centpercent" rows="3"></textarea></td></tr>';


	ob_start();
	?>
	<tr>
		<td valign="top">
			Concentrations
		</td>

		<td valign="top">

						
			<table class="table table-condensed table-hover small" id="concentrations_list">
				<thead>
					<tr>
						<th width="60%">
						Métal
						</th>
						<th width="30%">
						Concentration (x/1000e)
						</th>
						<th width="10%"> 
							<div class=" btn btn-mini btn-success " id="add_concentration" title="Ajouter une concentration"><i class="fa fa-plus-square" aria-hidden="true"></i> </div>
						</th>
					</tr>	
				</thead>

				<tbody>

				</tbody>
			</table>


		</td>
	</tr>

	<?php						
	$output = ob_get_clean();

	print $output;

	print '</table>';

	print '<div class="center">';
	print '<input type="submit" id="bt_save_concentration" class="button" value="'.$langs->trans("Save").'">';
	print '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	print '<input name="cancel" class="button" value="Annuler" type="submit">'; //onclick="history.go(-1)"

	print '</div>';

	print "</form>";

}

/* fiche mode                                                                  */
/* ************************************************************************** */
if ($rowid > 0) {
	//echo 'Mode fiche ....';

	//if ($action != 'edit') {
	//}
	//if ($action == 'edit') {
	//}

	$object = new abcvcConcentration($db);
	$object->rowid = $rowid;
	$object->fetch($rowid);
	//var_dump($object->structure);
	/* 
		array (size=2)
		  0 => 
		    object(stdClass)[135]
		      public 'id_matiere' => string '3' (length=1)
		      public 'montant' => string '300' (length=3)
		  1 => 
		    object(stdClass)[134]
		      public 'id_matiere' => string '1' (length=1)
		      public 'montant' => string '100' (length=3)

	  [{"id_matiere":"3","montant":"300"},{"id_matiere":"1","montant":"100"}]
	*/	

	print '<form id="adminForm" method="post" action="'.$_SERVER["PHP_SELF"].'?rowid='.$rowid.'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="rowid" value="'.$rowid.'">';
	print '<input type="hidden" name="action" value="update">';

	print '<input type="hidden" name="old_label" value="'.$object->old_label.'">';
	print '<input type="hidden" name="id_categorie" value="'.$object->id_categorie.'">';
	

	print load_fiche_titre('Concentration');	


	print '<table class="border" width="100%">';

	print '<tr><td width="15%">'.$langs->trans("Ref").'</td><td>'.$object->rowid.'</td></tr>';

	print '<tr><td>'.$langs->trans("Label").'</td><td><input type="text" name="label" size="40" value="'.dol_escape_htmltag($object->label).'"></td></tr>';

	print '<tr><td>'.$langs->trans("Actif").'</td><td>';
	print $form->selectyesno("active",$object->active,1);
	print '</td></tr>';


	print '<tr><td valign="top">'.$langs->trans("Description").'</td><td>';
	print '<textarea name="description" wrap="soft" class="centpercent" rows="3">'.$object->description.'</textarea></td></tr>';


	print '<tr style="display:none;"><td valign="top">'.$langs->trans("Structure").'</td><td>';
	print '<textarea name="structure" wrap="soft" id="structure_json" class="centpercent" rows="3">'.json_encode($object->structure).'</textarea></td></tr>';


	ob_start();
	?>
	<tr>
		<td valign="top">
			Concentrations
		</td>

		<td valign="top">

						
			<table class="table table-condensed table-hover small" id="concentrations_list">
				<thead>
					<tr>
						<th width="60%">
						Métal
						</th>
						<th width="30%">
						Concentration (x/1000e)
						</th>
						<th width="10%"> 
							<div class=" btn btn-mini btn-success " id="add_concentration" title="Ajouter une concentration"><i class="fa fa-plus-square" aria-hidden="true"></i> </div>
						</th>
					</tr>	
				</thead>

				<tbody>
				<?php if ( !is_null($object->structure) ) : ?>
				<?php foreach ($object->structure as $concentration) : ?>
					<tr data-row="">
						<td width="60%">

						<select name="concentrations[id_matiere][]" class="concentration_matiere input-block-level required ">	
							<option value="0">Choisir un métal</option>
							<?php foreach ($matieres_actives as $id_matiere => $label_matiere) : ?>
								<option class="" <?php echo ($id_matiere==$concentration->id_matiere)?'selected="selected"':'';?>  value="<?php echo $id_matiere;?>"><?php echo $label_matiere;?></option>
							<?php endforeach;  ?>	
						</select>

						</td>
						<td width="30%">
							<input name="concentrations[concentration][]" value="<?php echo $concentration->montant;?>" class="concentration_montant input-block-level required " min="0" max="1000" type="number">	
						</td>	
						<td width="10%" >  
							<div class="del_concentration btn btn-mini btn-danger"><i class="fa fa-trash" aria-hidden="true"></i> </div>	
						</td>
					</tr>

				<?php endforeach;  ?>
				<?php endif; ?>

				</tbody>
			</table>


		</td>
	</tr>

	<?php						


	$output = ob_get_clean();

	print $output;


	print '</table>';

	print '<div class="center">';
	print '<input type="submit" class="button" id="bt_save_concentration" value="'.$langs->trans("Save").'">';
	print '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	print '<input name="cancel" class="button" value="Annuler" type="submit">'; //onclick="history.go(-1)"

	print '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	print '<a class="butActionDelete"  id="bt_del_concentration" href="'.$_SERVER["PHP_SELF"].'?action=delete&amp;rowid='.$rowid.'">Supprimer</a>';

	print '</div>';

	print "</form>";	

}




// End of page
llxFooter();

$db->close();



/*
function getabcvc() {
	global $db;

	$sql = "SELECT";
	$sql.= " t.rowid,";
	$sql.= " t.label";
	$sql.= " FROM " . MAIN_DB_PREFIX . "abcvc_matiere as t";
	$sql.= " WHERE t.active = 1";
	dol_syslog(__METHOD__ . " sql=" . $sql, LOG_DEBUG);
	$resql = $db->query($sql);

	$matieres = array();
	if ($resql) {
		$i = 0;
		$num  = $db->num_rows($resql);
		while ($i < $num) {
			$matiere = $db->fetch_object($result);
			$matieres[$matiere->rowid] = $matiere->label;
			$i++;
		}

		$db->free($resql);

		return $matieres;
	} else {
		$this->error = "Error " . $db->lasterror();
		dol_syslog(__METHOD__ . " " . $this->error, LOG_ERR);

		return $matieres;
	}

}
*/