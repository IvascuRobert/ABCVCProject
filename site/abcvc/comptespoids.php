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



dol_include_once('/abcvc/class/abcvcMatiere.class.php');
dol_include_once('/abcvc/class/abcvcConcentration.class.php');

dol_include_once('/abcvc/class/comptepoids.class.php');
$obj_comptepoid = new ComptePoids($db);


$obj_matiere = new abcvcMatiere($db);
$matieres_actives = $obj_matiere->getabcvc();
$matieres_all = $obj_matiere->getabcvc(0);
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

//tri / filtre / pagination
$search_nom=trim(GETPOST("search_nom"));
$search_datede=trim(GETPOST("search_datede"));
$search_datea=trim(GETPOST("search_datea"));

$limit = GETPOST("limit")?GETPOST("limit","int"):$conf->liste_limit;

$sortfield=GETPOST("sortfield",'alpha');
$sortorder=GETPOST("sortorder",'alpha');
$page=GETPOST("page",'int');
if (! $sortorder) $sortorder="DESC";
if (! $sortfield) $sortfield="m.date_mvt";
if ($page == -1) { $page = 0 ; }
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;


$page = 0;
$limit = 0;
$offset = 0;



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
if ($action == 'synchro_concentration_categorie') {


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


	//liste clients
	//--------------------------------------------------------
	$sql = "SELECT ";
	$sql.= " s.rowid, s.nom, s.code_client, s.code_fournisseur";
	$sql.= " FROM " . MAIN_DB_PREFIX . "societe as s";
	$sql.= " ORDER BY s.nom ASC"; //WHERE s.code_client is NOT NULL
	$result = $db->query($sql);

	$list_clients = array();
	if ($result) {
		$num = $db->num_rows($result);
		$i = 0;
		while ($i < $num) {
			$objp = $db->fetch_object($result);
			$list_clients[] = $objp;
			$i++;
		}
	}
	/*
	var_dump($list_clients);
	exit();
	  0 => 
	    object(stdClass)[125]
	      public 'rowid' => string '69' (length=2)
	      public 'nom' => string '377' (length=3)
	      public 'code_client' => string 'CL00056' (length=7)
	  1 => 
	    object(stdClass)[126]
	      public 'rowid' => string '70' (length=2)
	      public 'nom' => string 'ABC GRAVURE' (length=11)
	      public 'code_client' => string 'CL00057' (length=7)
	*/

/*
 **************************************************************************************************************
 *
 * VIEW
 *
 **************************************************************************************************************
 */

llxHeader('', $langs->trans('ABCVC - Comptes poids'), '');

$form = new Form($db);


ob_start();
//**************************************************************************************************************
// injection CSS/JS contexte
//**************************************************************************************************************
?>

<link rel="stylesheet" href="/abcvc/css/font-awesome-4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="/abcvc/js/bootstrap-3.3.7/css/bootstrap.min.css">
<link rel="stylesheet" href="/abcvc/css/jquery-ui-timepicker-addon.css">


<script src="/abcvc/js/bootstrap-3.3.7/js/bootstrap.min.js" type="text/javascript" charset="utf-8" async defer></script>
<script src="/abcvc/js/jquery-ui-timepicker-addon.js" type="text/javascript" charset="utf-8" async defer></script>


<link rel="stylesheet" type="text/css" href="/abcvc/js/datatables.min.css"/>
 
<script type="text/javascript" src="/abcvc/js/datatables.min.js"></script>



<script type="text/javascript">

	var concentrations = null;

	var matieres_actives = <?php echo json_encode($matieres_actives);?>;

	var id_matieres_actives = <?php echo json_encode(array_keys($matieres_actives));?>;
	
	$(document).ready(function(){


		//init dates
		$( "#search_datede" ).datepicker().on( "change", function() {
          $( "#search_datea" ).datepicker( "option", "minDate", getDate( this ) );
        });
		$( "#search_datea" ).datepicker().on( "change", function() {
          $( "#search_datede" ).datepicker( "option", "maxDate", getDate( this ) );
        });


		$('#search_nom, #search_datede, #search_datea').on( "keydown", function(event) { 
			if(event.which == 13) {
				document.formfilter.submit();
			}
		});	

		$( "#add-form .addmatiere_date" ).datetimepicker({
		});

		$( "#add-form" ).dialog({
			autoOpen: false,
			height: 480,
			width: 350,
			modal: true,
			buttons: {
			  "Ajouter": add_mvt,
			  "Annuler": function() {
			    $(this).dialog( "close" );
			  }
			},
			close: function() {
				$( "#add-form .add_matiere_description" ).val('');
				$( "#add-form input[type='number']" ).each(function(i,el){
					$(el).val('');
				});
			}
		});
		$('.add_poid').on('click',function(e){
			e.preventDefault();
			//grrr
			$( "#add-form .addmatiere_date" ).datetimepicker('setDate', (new Date()) );
			$('#add-form').dialog( "open" );
		});



		//init DataTable
		$('#KTable').DataTable({
			"language": {
                "url": "/abcvc/js/datatables.fr.js"
            },
			"searching": false,
			"dom": 'rt<"bottom"ilp><"clear">'
		});

		$('#KTable').on( 'draw.dt', function () {
	    	calcul_soldes();
		} );

	});


    function getDate( element ) {
      var date;
      try {
        date = $.datepicker.parseDate( "dd/mm/yy", element.value );
      } catch( error ) {
        date = null;
      }
      return date;
    }

	function add_mvt(){
		var valid = true;
		var description = $( "#add-form .addmatiere_description" ).val();
		var date = $( "#add-form .addmatiere_date" ).val();
		if( date=='' ){
			valid = false;
		}
		var id_client = $( "#add-form .addmatiere_client" ).val();
		if( id_client=='' ){
			valid = false;
		}
		
		var matieres = [];
		$( "#add-form input[type='number']" ).each(function(i,el){
			var id_matiere = $(el).attr('id').split('_')[1];
			var qty = $(el).val();
			if(qty!=''){
				matieres.push({
					id_matiere : id_matiere,
					qty : qty
				});
			}	
		});
		if( matieres.length==0 ){
			valid = false;
		}

		var data_add = {
			'id_client':id_client,
			'date':date,
			'description':description,
			'matieres':matieres
		};
		//console.log(data_add);

		if ( valid ) {
			$.ajax({ 
				url: '/abcvc/comptepoids.php?action=ajax_addPoids', 
				dataType: 'json',
				type: 'POST',
				data : data_add,
				success: function(data){
					//console.log(data);
					if(data.statut=="ok"){
						$('#add-form').dialog( "close" );
						document.formfilter.submit();
					} else {
						alert(data.message);
					}	
				}
			});
		}


		return valid;
	}


	function calcul_soldes() {
		var krows = $('#KTable tbody tr');


		var totaux = [];

		//id_matieres_actives = [1,2,3,5];
		$.each(id_matieres_actives,function(i,id_matiere){
			var total_debit = 0;
			var total_credit = 0;

			$.each(krows,function(i,row){
				var debit_text = parseFloat($('.td_debit_'+id_matiere, row).text());
				var credit_text = parseFloat($('.td_credit_'+id_matiere, row).text());

				if( !isNaN(debit_text) ){
					total_debit += debit_text;
				}
				if( !isNaN(credit_text) ){
					total_credit += credit_text;
				}
			});			

			totaux.push({
				"id_matiere": id_matiere,
				"debit": total_debit.toFixed(2),
				"credit": total_credit.toFixed(2)
			});
		});
		//console.log(totaux);


		//purge totaux



		//injecter les totaux
		$.each(totaux,function(i,total_matiere){

			$('#td_total_debit_'+total_matiere.id_matiere).text('');
			$('#td_total_credit_'+total_matiere.id_matiere).text('');
			$('#td_solde_debit_'+total_matiere.id_matiere).text('');
			$('#td_solde_credit_'+total_matiere.id_matiere).text('');


			if(total_matiere.debit!=0){
				$('#td_total_debit_'+total_matiere.id_matiere).text(total_matiere.debit);
			}
			if(total_matiere.credit!=0){
				$('#td_total_credit_'+total_matiere.id_matiere).text(total_matiere.credit);
			}

			var solde_matiere = parseFloat(total_matiere.credit) - parseFloat(total_matiere.debit);
			if(solde_matiere!=0){
				if(solde_matiere>0){
					$('#td_solde_credit_'+total_matiere.id_matiere).text(solde_matiere.toFixed(2));

				} else {
					$('#td_solde_debit_'+total_matiere.id_matiere).text(Math.abs(solde_matiere).toFixed(2));				
				}				
			}

		});



	}


</script>

<style type="text/css" media="screen">
	.bloc_concentration_liste{
		font-size: 12px;
		font-style: italic;

	}
	#KTable_paginate {
		text-align: center;
	}
	#KTable_paginate .paginate_button, #KTable_paginate .paginate_active {
		border: none !important;
		padding: 0 !important;
	}
	#KTable_wrapper .sorting_asc, #KTable_wrapper .sorting_desc {
		background: none !important;
	}	
</style>

<?php						
//**************************************************************************************************************
$output = ob_get_clean();
print $output;



/* Liste mode                                                              */
/* ************************************************************************** */

	//print load_fiche_titre('Liste des comptes poids');	

	$filters = array(
		'search_nom' => $search_nom,
		'search_datede' => $search_datede,
		'search_datea' => $search_datea,

		'limit' => $limit,
		'offset' => $offset
	);

	//$comptes_poids = $obj_comptepoid->fetchAll();
	//var_dump($comptes_poids);
	//exit();

	$id_client = 0; //TOUS
	$retour = $obj_comptepoid->soldePoids($id_client,$filters);

	$solde_poids = $retour['soldePoids'];
//var_dump($solde_poids);
/* 
	array (size=6)
	  'soldes' => 
	    array (size=4)
	      1 => 
	        array (size=2)
	          'debit' => float -80.48
	          'credit' => int 0
	      2 => 
	        array (size=2)
	          'debit' => float -10.78
	          'credit' => int 0
	      3 => 
	        array (size=2)
	          'debit' => int 0
	          'credit' => int 0
	      4 => 
	        array (size=2)
	          'debit' => int 0
	          'credit' => int 0
	  'id_client' => int 105
	  'nom_client' => string 'LAOLINE' (length=7)
	  'code_client' => string 'CL00092' (length=7)
	  'date_solde' => null
	  'maj' => string '2017-03-02 18:00:00' (length=19)
  */
	$pagination = $retour['pagination'];
//var_dump($pagination);
/* 
array (size=2)
  'nbtotalofrecords' => int 162
  'num' => int 1	  
  */


$param='';
//if (! empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param.='&contextpage='.$contextpage;
if ($limit > 0 && $limit != $conf->liste_limit) $param.='&limit='.$limit;
if ($search_all != '') $param = "&amp;sall=".urlencode($search_all);
if ($sall != '') $param .= "&amp;sall=".urlencode($sall);


//$num = 2;
//$nbtotalofrecords = 2;


ob_start();
?>
	<form name="formfilter" method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
	<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken'];?>">
	
	<?php 
		//print_barre_liste('Solde des comptes poids', $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $pagination['num'], $pagination['nbtotalofrecords'], 'title_generic.png', 0, '', '', $limit);
	?>	

	<table class="notopnoleftnoright" style="margin-bottom: 6px;" width="100%" border="0">
		<tbody>
			<tr>
				<td class="nobordernopadding hideonsmartphone" valign="middle" width="40" align="left">
					<img src="/theme/eldy/img/title_generic.png" alt="" title="" id="pictotitle" border="0">
				</td>
				<td class="nobordernopadding">
					<div class="titre">Solde des comptes poids </div>
				</td>
			</tr>
		</tbody>
	</table>

	<div class=" btn btn-xs btn-success add_poid pull-right" 
	title="Ajouter une opération pour un client"  
	data-id=""><i class="fa fa-plus-square" aria-hidden="true"></i> Ajouter une opération pour un client</div>

	<table class="noborder table table-hover" width="100%">

		<tr class="liste_titre">
			<td width="30%">
				<input class="flat searchstring" name="search_nom" id="search_nom" size="10" value="<?php echo $search_nom;?>" type="text" placeolder="Client...">
			</td>

			<?php foreach ($matieres_actives as $id_matiere => $matiere_actives) : 
				$pc_matiere = number_format( 40/count($matieres_actives) ,2);
			?>
				<td colspan="2" align="center" width="<?php echo $pc_matiere;?>%"><?php echo $matiere_actives; ?></td>
			<?php endforeach; ?>
			
			<td width="30%" align="right">
				De <input class="flat searchstring" name="search_datede" size="7" value="<?php echo $search_datede;?>" type="text" id="search_datede"> 
				A <input class="flat searchstring" name="search_datea" size="7" value="<?php echo $search_datea;?>" type="text" id="search_datea"> 
			</td>
		</tr>

	</table>			

	<table id="KTable" class="noxborder table table-hover" width="100%">
 		<thead>
		<tr class="liste_titre">
			<td width="30%">
				Client
			</td>

			<?php foreach ($matieres_actives as $id_matiere => $matiere_actives) : ?>
				<?php if( array_key_exists($id_matiere,$matieres_actives) ) : 
					$pc_matiere_mvt = number_format( $pc_matiere/2 ,2);
				?>
					<td align="center" width="<?php echo $pc_matiere_mvt;?>%">Débit&nbsp;&nbsp;</td>
					<td align="center" width="<?php echo $pc_matiere_mvt;?>%">Crédit&nbsp;&nbsp;</td>
				<?php endif; ?>	
			<?php endforeach; ?>

			<td align="right" width="30%">
			M.à.j.&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			</td>			
		</tr>
		</thead>

		<tbody>
		<?php foreach ($solde_poids as $id_client => $solde_client) : ?>
		<tr>
			<td width="30%">
				<a href="/societe/soc.php?mainmenu=companies&socid=<?php echo $solde_client['id_client'];?>" class=""><?php echo $solde_client['nom_client'];?> (<?php 
				if(!is_null($solde_client['code_client'])){
					$code = $solde_client['code_client'];
				} elseif(!is_null($solde_client['code_fournisseur'])){
					$code = $solde_client['code_fournisseur'];
				} else {
					$code = '?';
				}
				echo $code; //$solde_client['code_client'];
				?>)</a>

				<a href="/abcvc/comptepoids.php?idmenu=50&mainmenu=abcvc&id_client=<?php echo $solde_client['id_client'];?>" class="" style="float: right;">[Historique]</a>
			</td>

			<?php foreach ($solde_client['soldes'] as $id_matiere => $matiere_solde) : ?>
				<?php if( array_key_exists($id_matiere,$matieres_actives) ) : 
					$pc_matiere_mvt = number_format( $pc_matiere/2 ,2);

					$class_debit = "td_debit_$id_matiere";
					$class_credit = "td_credit_$id_matiere";
				?>

					<td align="right" class="<?php echo $class_debit;?>" width="<?php echo $pc_matiere_mvt;?>%"> <?php echo (empty($matiere_solde['debit']))?'':number_format(abs($matiere_solde['debit']),2) ;?> </td>
					<td align="right" class="<?php echo $class_credit;?>" width="<?php echo $pc_matiere_mvt;?>%"> <?php echo (empty($matiere_solde['credit']))?'':number_format(abs($matiere_solde['credit']),2) ;?> </td>

				<?php endif; ?>		
			<?php endforeach; ?>


			<td width="30%" align="right"> <?php echo date('d/m/Y H:i:s',strtotime($solde_client['maj']));?> </td>
		</tr>
		<?php endforeach; ?>
		</tbody>
	</table>



	<table id="KTable_totaux" class=" table table-hover dataTable" width="100%">

		<tr role="row" class="odd">
			<td class="sorting_1" width="30%" align="right">
				Totaux
			</td>

			<?php foreach ($matieres_actives as $id_matiere => $matiere_actives) : ?>
				<?php if( array_key_exists($id_matiere,$matieres_actives) ) : 
					$pc_matiere_mvt = number_format( $pc_matiere/2 ,2);
				?>
					<td align="right" width="<?php echo $pc_matiere_mvt;?>%" id="td_total_debit_<?php echo $id_matiere;?>" >&nbsp;</td>
					<td align="right" width="<?php echo $pc_matiere_mvt;?>%" id="td_total_credit_<?php echo $id_matiere;?>" >&nbsp;</td>
				<?php endif; ?>	
			<?php endforeach; ?>

			<td align="right" width="30%"> &nbsp; </td>
		</tr>

		<tr role="row" class="odd">
			<td class="sorting_1" width="30%" align="right">
				Solde
			</td>

			<?php foreach ($matieres_actives as $id_matiere => $matiere_actives) : ?>
				<?php if( array_key_exists($id_matiere,$matieres_actives) ) : 
					$pc_matiere_mvt = number_format( $pc_matiere/2 ,2);
				?>
					<td align="right" width="<?php echo $pc_matiere_mvt;?>%" id="td_solde_debit_<?php echo $id_matiere;?>" >&nbsp;</td>
					<td align="right" width="<?php echo $pc_matiere_mvt;?>%" id="td_solde_credit_<?php echo $id_matiere;?>" >&nbsp;</td>
				<?php endif; ?>	
			<?php endforeach; ?>

			<td align="right" width="30%"> &nbsp; </td>
		</tr>		

	</table>	


	</form>	



	<!-- dialog add mvt -->
	<div id="add-form" title="Ajout opération manuelle">
		<p class="">Indiquez une client, une date, les quantités de matières(+/-) ainsi qu'une description de l'opération.</p>

		<select name="addmatiere_client" class="addmatiere_client col-xs-12 required" required="">
			<option value="">Séléctionner un client</option>
			<?php foreach ($list_clients as $client) : ?>
				<option value="<?php echo $client->rowid;?>"><?php 
				if(!is_null($client->code_client)){
					$code = $client->code_client;
				} elseif(!is_null($client->code_fournisseur)){
					$code = $client->code_fournisseur;
				} else {
					$code = '?';
				}
				echo $client->nom.' ('.$code.')';
				?></option>
			<?php endforeach; ?>
		</select>
	 
	 	<input class="flat addmatiere_date col-xs-12 required" required=""  name="addmatiere_date" size="7" value="" type="text" placeholder="d/m/Y h:m">

		<table class="table table-condensed table-hover small" >
			<thead>
				<tr>
					<th width="70%">
					Métal
					</th>
					<th width="30%">
					Quantité
					</th>
				</tr>	
			</thead>
			<tbody>
	  		<?php foreach ($matieres_all as $id_matiere => $matiere) : ?>
				<tr data-row="">
					<td><?php echo $matiere;?></td>
					<td>
						<input type="number" step="0.01" value="" id="addmatiere_<?php echo $id_matiere;?>">
					</td>
				</tr>
	  		<?php endforeach; ?>
	  			
	  			<tr data-row="">
					<td colspan="2">
						<textarea class="col-xs-12 addmatiere_description" rows="4" placeholder="Description optionnelle de l'opération"></textarea>
					</td>	
				</tr>	
			</td>	
		</table>

	</div>


<?php
$html = ob_get_clean();

print $html;






// End of page
llxFooter();

$db->close();



/*



*/