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

dol_include_once('/expedition/class/expedition.class.php');

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
$id_client = GETPOST('id_client', 'int');
$action = GETPOST('action', 'alpha');
$cancel = GETPOST('cancel','alpha');
$mode = GETPOST('mode', 'alpha');


$id_poid = GETPOST('id_poid', 'int');


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


// Access control
if ($user->socid > 0) {
	// External user
	accessforbidden();
}


/*
 **************************************************************************************************************
 *
 * ACTIONS
 *
 **************************************************************************************************************
 */
//var_dump($_POST);
/*array (size=7)
  'token' => string 'c07ac0830c35e9781443d71f15dc465c' (length=32)
  'rowid' => string '2' (length=1)
  'action' => string 'update' (length=6)
  'label' => string 'Argent' (length=6)
  'active' => string '1' (length=1)
  'description' => string '' (length=0)
  'cancel' => string 'Annuler' (length=7)*/



/*
	//products
	//--------------------------------------------------------
	$sql = "SELECT ";
	$sql.= " t.rowid, t.ref, t.label";
	$sql.= " FROM " . MAIN_DB_PREFIX . "product as t";
	//$sql.= " WHERE t.label = '" . $this->label. "'";
	$result = $db->query($sql);
*/


//temp debug action
//---------------------------
if ($action == 'test_calculatePoids') {

	$retour_calcul = $obj_comptepoid->calculPoids(105, 'expedition',1);


	var_dump($retour_calcul);
	exit();
}



if ($action == 'calculALLfactures') {

	$retour_calcul = $obj_comptepoid->calculALLfactures();

	var_dump($retour_calcul);
	exit();
}



if ($action == 'calculALLexpeditions') {

	$retour_calcul = $obj_comptepoid->calculALLexpeditions();

	var_dump($retour_calcul);
	exit();
}


if ($action == 'initALLClients') {

	$retour_calcul = $obj_comptepoid->initALLClients();

	var_dump($retour_calcul);
	exit();
}

if ($action == 'generatePDF') {
	// /abcvc/comptepoids.php?action=generatePDF&id_client=105&datePDFde=.. &datePDFa=..

	$datePDFde = GETPOST('datePDFde','alpha');
	$date0 = explode(' ',$datePDFde);
	$date1 = explode('/',$date0[0]);
	$datePDFde_sql = $date1[2].'-'.$date1[1].'-'.$date1[0];//.' '.$date0[1];

	$datePDFa = GETPOST('datePDFa','alpha');
	$date0 = explode(' ',$datePDFa);
	$date1 = explode('/',$date0[0]);
	$datePDFa_sql = $date1[2].'-'.$date1[1].'-'.$date1[0];//.' '.$date0[1];
	//var_dump($id_client, $datePDFde_sql, $datePDFa_sql);
	//exit();

	$obj_comptepoid->id_client = $id_client;
	$obj_comptepoid->datePDFde_sql = $datePDFde_sql;
	$obj_comptepoid->datePDFa_sql = $datePDFa_sql;
	$obj_comptepoid->datePDFde = $datePDFde;
	$obj_comptepoid->datePDFa = $datePDFa;

	$obj_comptepoid->generateDocument($id_client);
}









//ajax recalculer poids
//---------------------------
if ($action == 'ajax_calculPoids') {
	// /abcvc/comptepoids.php?action=ajax_calculPoids&id_poid=1

	$obj_comptepoid->fetch($id_poid);
	//var_dump($obj_comptepoid);
	//exit();

	$retour_calcul = $obj_comptepoid->calculPoids($obj_comptepoid->fk_soc, $obj_comptepoid->type,  $obj_comptepoid->id_type);
	//var_dump($retour_calcul);

	$return = array(
		'statut'=>'ok',
		'message'=>''
	);
	echo json_encode($return);
	exit;
}

//ajax add poids
//---------------------------
if ($action == 'ajax_addPoids') {
	// /abcvc/comptepoids.php?action=ajax_addPoids
	
	$id_client = GETPOST('id_client', 'int');

	$date = GETPOST('date','alpha');
	$date0 = explode(' ',$date);
	$date1 = explode('/',$date0[0]);
	$date_sql = $date1[2].'-'.$date1[1].'-'.$date1[0].' '.$date0[1];

	$description = GETPOST('description', 'alpha');

	$matieres = GETPOST('matieres', 'array');

	$matieres_log=array();
	$matieres_add=array();	
	foreach ($matieres as $matiere) {
		$matieres_log[] = $matieres_all[$matiere['id_matiere']].': '.$matiere['qty'];
		$matieres_add[$matiere['id_matiere']] = $matiere['qty'];
	}

	$obj_comptepoid->ref = 'a';
	$obj_comptepoid->fk_soc = $id_client;
	$obj_comptepoid->date_creation = $date_sql;
	$obj_comptepoid->type = 'manuel';
	$obj_comptepoid->ref_type = 'manuel';
	$obj_comptepoid->id_type = '0';
	$obj_comptepoid->description = $description;
	$obj_comptepoid->structure = json_encode($matieres_log);

	$obj_comptepoid->mvt = $matieres_add;

	$obj_comptepoid->create($user,1);
	//var_dump($obj_comptepoid);
	//exit();



	$return = array(
		'statut'=>'ok',
		'message'=>'id:'.$obj_comptepoid->rowid
	);
	echo json_encode($return);
	exit;
}

//ajax del poids
//---------------------------
if ($action == 'ajax_delPoids') {
	// /abcvc/comptepoids.php?action=ajax_calculPoids&id_poid=1

	$obj_comptepoid->fetch($id_poid);
	$obj_comptepoid->delete($user,1);

	$return = array(
		'statut'=>'ok',
		'message'=>''
	);
	echo json_encode($return);
	exit;
}


//ajax recup BL depuis facture
//---------------------------
if ($action == 'ajax_getBL') {
	// /abcvc/comptepoids.php?action=ajax_getBL
	//  id_client / datede / datea

	$id_client = GETPOST('id_client', 'int');
	$datede = GETPOST('datede','alpha');
	$date1 = explode('/',$datede);
	$datede = $date1[2].'-'.$date1[1].'-'.$date1[0];

	$datea = GETPOST('datea','alpha');
	$date1 = explode('/',$datea);
	$datea = $date1[2].'-'.$date1[1].'-'.$date1[0];


	$listeBL = array();
	$sql = " 
	SELECT m.rowid, m.ref, m.fk_soc, m.fk_statut, m.date_creation ,
	el.fk_source as id_commande, c.ref as ref_commande
	FROM ".MAIN_DB_PREFIX."expedition as m
	LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (s.rowid = m.fk_soc)
	LEFT JOIN ".MAIN_DB_PREFIX."element_element as el ON (el.fk_target = m.rowid AND el.targettype = 'shipping')
	LEFT JOIN ".MAIN_DB_PREFIX."commande as c ON (c.rowid = el.fk_source)
	WHERE m.fk_statut = 1
	AND m.fk_soc = ".$id_client." 
	AND (m.date_creation >= '".$datede."')
	AND (m.date_creation <= '".$datea."')
	ORDER BY m.date_creation desc "; 
	$result = $db->query($sql);
	if ($result) {
		$num = $db->num_rows($result);
		$j = 0;	
		while ($j < $num) {
			$objBL = $db->fetch_object($result);

			$listeBL[] = $objBL;
			$j++;
		}
	}	
	//var_dump($listeBL);
	//exit();

	//déja facturé ?... (présent dans le detail d'une facture)
	$liste_facturedet = array();
	$sql = " 
	SELECT rowid, fk_facture, description 
	FROM ".MAIN_DB_PREFIX."facturedet 
	WHERE fk_product is null and product_type = 0";
	$result = $db->query($sql);
	if ($result) {
		$num = $db->num_rows($result);
		$j = 0;	
		while ($j < $num) {
			$objBL = $db->fetch_object($result);
			$liste_facturedet[] = $objBL;
			$j++;
		}
	}
	//ex  BL: SH1703-0002 du 12/03/2017 14:16:56 (ref Commande: CO1703-0003)
	foreach ($listeBL as $key => $BL) {
		foreach ($liste_facturedet as $facturedet) {
			$search = 'BL: '.$BL->ref;
			if( strpos($facturedet->description,$search)!==false ){
				unset($listeBL[$key]);
			}	
		}
	}

	//injection frais de livraison éventuel
	foreach ($listeBL as $key => $BL) {
		$objexp = new Expedition($db);
		$objexp->fetch($BL->rowid);
		$objexp->fetch_delivery_methods();

		$BL->shipping_method_id = $objexp->shipping_method_id;
		$BL->shipping_label = trim($objexp->meths[$BL->shipping_method_id]);
		$BL->shipping_cost = number_format( floatval( @$objexp->array_options['options_fraisexp'] ) ,2);

		/*if ( ($shipping_method_id>0) && ($shipping_cost>0) ) {
		{"rowid":"12","ref":"SH1704-0007","fk_soc":"105","fk_statut":"1","date_creation":"2017-03-29 19:36:36","id_commande":"7","ref_commande":"CO1703-0006","shipping_method_id":"11","shipping_label":"Lettre Max suivi 500g","shipping_cost":"5.50",
		*/
	}



	//récuperations produits "expedié"
	foreach ($listeBL as $BL) {

		$sql = "
		SELECT ed.rowid as line_id, ed.qty, ed.fk_origin_line, 
		cd.fk_product, cd.tva_tx, cd.price,
		p.ref as product_ref, p.label as product_label, e.perte
		FROM ".MAIN_DB_PREFIX."expeditiondet as ed
		LEFT JOIN ".MAIN_DB_PREFIX."commandedet as cd ON ed.fk_origin_line = cd.rowid			
		LEFT JOIN ".MAIN_DB_PREFIX."product as p ON cd.fk_product = p.rowid
		LEFT JOIN ".MAIN_DB_PREFIX."product_extrafields as e ON e.fk_object = p.rowid
		WHERE ed.fk_expedition = ".$BL->rowid."
		AND cd.product_type = 0	
		ORDER BY cd.rang, ed.fk_origin_line";
		//var_dump($sql);
		//exit();
		$produits_raw = array();

		$result = $db->query($sql);
		if ($result) {
			$num = $db->num_rows($result);
			$i = 0;
			while ($i < $num) {
				$objproduit = $db->fetch_object($result);
				$produits_raw[]=$objproduit;
				$i++;	
			}
		}
		$BL->produits = $produits_raw;
		
		//calcul prix réél expédiés
		$totalHT = 0;
		$totalTVA= 0;
		$totalTTC= 0;
		foreach ($produits_raw as $produit_raw) {
			$totalHT +=  $produit_raw->price * $produit_raw->qty ;
			$totalTVA += ($produit_raw->price * $produit_raw->qty) * ($produit_raw->tva_tx/100) ;
			$totalTTC += ($produit_raw->price * $produit_raw->qty) + ( ($produit_raw->price * $produit_raw->qty) * ($produit_raw->tva_tx/100) ) ;
		}

		// + frais expedition ?
		if ( ($BL->shipping_method_id>0) && ($BL->shipping_cost>0) ) {
			
			$totalHT += $BL->shipping_cost;
			$totalTVA += $BL->shipping_cost*0.2;
			$totalTTC += $BL->shipping_cost*1.2;
		}


		$BL->total = array(
			'totalHT' =>  number_format($totalHT,2),
			'totalTVA' =>  number_format($totalTVA,2),
			'totalTTC' =>  number_format($totalTTC,2)
		);

	}


	//construction tableau HTML
	//---------------------------------------
	ob_start();
	?>
	<?php if(count($listeBL)>0) :?>
		<table class="table table-condensed table-hover" width="100%">
			<thead>
				<tr class="liste_titre nodrag nodrop">
					<td>Ref.</td>
					<td>Ref. Commande</td>
					<td>Date</td>

					<td>Total HT</td>
					<td>Total TVA</td>
					<td>Total TTC</td>

					<td><input type="checkbox" class="BLcheck_all" value="0"></td>
					<td>&nbsp;</td>
				</tr>
			</thead>
			<tbody>
			<?php 
				$idx=0;
				foreach ($listeBL as $key => $BL) : ?>
				<tr data-idx="<?php echo $key; ?>">
					<td class="td_bl_ref"><a href="/expedition/card.php?id=<?php echo $BL->rowid;?>"><?php echo $BL->ref;?></a></td>
					<td class="td_bl_refcommande"><a href="/commande/card.php?id=<?php echo $BL->id_commande;?>"><?php echo $BL->ref_commande;?></a></td>
					<td class="td_bl_date"><?php echo date('d/m/Y H:i:s',strtotime($BL->date_creation));?></td>

					<td class="td_bl_totalHT"><?php echo $BL->total['totalHT'];?></td>
					<td class="td_bl_totalTVA"><?php echo $BL->total['totalTVA'];?></td>
					<td class="td_bl_totalTTC"><?php echo $BL->total['totalTTC'];?></td>
																	
					<td><input type="checkbox" class="BLcheck" value="0"></td>

					<?php if($idx==0) :?>
					<td rowspan="<?php echo count($listeBL);?>">
						<input class="button" value="Ajouter" name="addBLline" id="addBLline" >
					</td>
				<?php endif; ?>
				</tr>
			<?php 
				$idx++;
				endforeach; ?>	
			</tbody>
		</table>
	<?php else: ?>	
		Aucun Bon de livraison (non déja facturé) disponible pour ce client sur la période choisie.
	<?php endif; ?>	
	<?php 
	$tableHTML = ob_get_clean();

	$return = array(
		'statut'=>'ok',
		'id_client'=>$id_client,
		'datede'=>$datede,
		'datea'=>$datea,
		'listeBL'=>$listeBL,
		'tableHTML'=>$tableHTML,
		'message'=>''
	);
	echo json_encode($return);
	exit;
}





































// Default action
if (empty($action) && empty($id) && empty($ref)) {
	$action='list';
}





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



<script type="text/javascript">

	var concentrations = null;

	var matieres_actives = <?php echo json_encode($matieres_actives);?>;

	var id_client = '<?php echo $id_client;?>';
	
	$(document).ready(function(){

		$.timepicker.regional['fr'] = {
				timeOnlyTitle: 'Choisir une heure',
				timeText: 'Heure',
				hourText: 'Heures',
				minuteText: 'Minutes',
				secondText: 'Secondes',
				millisecText: 'Millisecondes',
				microsecText: 'Microsecondes',
				timezoneText: 'Fuseau horaire',
				currentText: 'Maintenant',
				closeText: 'Terminé',
				timeFormat: 'HH:mm',
				timeSuffix: '',
				amNames: ['AM', 'A'],
				pmNames: ['PM', 'P'],
				isRTL: false
			};
		$.timepicker.setDefaults($.timepicker.regional['fr']);


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



	    $('.tb_detail').dialog({
	      modal: true,
	      height: 500,
	      width: 400,
	      autoOpen: false,
	      /*buttons: {
	        Ok: function() {
	          $( this ).dialog( "close" );
	        }
	      }*/
	    });
		$('.detail_mvt').on('click',function(e){
			e.preventDefault();
			var id = $(this).data('id');
		    $('#tb_detail_'+id).dialog( "open" );

		});


		$('.recalcul_poid').on('click',function(e){
			e.preventDefault();
			if(confirm('Confirmez-vous le recalcul des poids de ce document comptable ?')){
				var id = $(this).data('id');
			    //console.log('id:'+id);

				$.ajax({ 
					url: '/abcvc/comptepoids.php?action=ajax_calculPoids', 
					dataType: 'json',
					type: 'POST',
					data : {
					  'id_poid' : id
					},
					success: function(data){
						//console.log(data);
						if(data.statut=="ok"){
							document.formfilter.submit();
						} else {
							alert(data.message);
						}	
					}
				});

			}
		});


		$('.del_poid').on('click',function(e){
			e.preventDefault();
			if(confirm('Confirmez-vous la supression de cette opération ?')){
				var id = $(this).data('id');

				$.ajax({ 
					url: '/abcvc/comptepoids.php?action=ajax_delPoids', 
					dataType: 'json',
					type: 'POST',
					data : {
						id_poid:id
					},
					success: function(data){
						//console.log(data);
						if(data.statut=="ok"){
							document.formfilter.submit();
						} else {
							alert(data.message);
						}	
					}
				});

			}
		});

		//obsolete
		$('.modifier_poid').on('click',function(e){
			e.preventDefault();
			//if(confirm('Confirmez-vous le recalcul des poids de ce document comptable ?')){
				var id = $(this).data('id');
			    console.log('id:'+id);

			//}
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


		$('.pdf_poid').on('click',function(e){
			e.preventDefault();

			var datede = $( "#search_datede" ).val();
			var datea = $( "#search_datea" ).val();

			if ( (datede=='') && (datea=='') ){
				var message = "Confirmez-vous la génération du relevé poids de ce client depuis l'origine ?";
			} else if ( (datede=='') && (datea!='') ){
				var message = "Confirmez-vous la génération du relevé poids de ce client jusqu'au "+datea+" ?";
			} else if ( (datede!='') && (datea=='') ){
				var message = "Confirmez-vous la génération du relevé poids de ce client depuis le "+datede+" ?";
			} else {
				var message = "Confirmez-vous la génération du relevé poids de ce client du "+datede+" au "+datea+" ?";
			}
			//console.log(datede, datea);

			if( confirm(message) ){
				var url = "/abcvc/comptepoids.php?action=generatePDF&id_client="+id_client+"&datePDFde="+datede+"&datePDFa="+datea;
				//document.location = url;
				window.open(url);
			}
		});


	});
	//-------------------------------------------------------


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



</script>

<style type="text/css" media="screen">
	.bloc_concentration_liste{
		font-size: 12px;
		font-style: italic;

	}
	.tb_detail{
		max-height: 500px;
	}

	.pagination {
		margin: 4px 0;
	}	

	.recalcul_poid, .modifier_poid{
		margin-right: 2px;

	}
</style>

<?php						
//**************************************************************************************************************
$output = ob_get_clean();
print $output;



/* Liste mode                                                              */
/* ************************************************************************** */

	$filters = array(
		'search_nom' => $search_nom,
		'search_datede' => $search_datede,
		'search_datea' => $search_datea,

		'limit' => $limit,
		'offset' => $offset
	);
	//var_dump($filters);
	
	$retour = $obj_comptepoid->soldePoids($id_client,$filters);
	//var_dump($retour);
	$solde_poids = $retour['soldePoids'];
	//var_dump($solde_poids);



	$nom_client = $solde_poids[0]['nom_client'];
	$code_client = $solde_poids[0]['code_client'];
	$code_fournisseur = $solde_poids[0]['code_fournisseur'];

	if(!is_null($code_client)){
		$code = $code_client;
	} elseif(!is_null($code_fournisseur)){
		$code = $code_fournisseur;
	} else {
		$code = '?';
	}

	print load_fiche_titre('Compte poids: '.$nom_client.' ('.$code.')');	


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

	$retour = $obj_comptepoid->fetchAll($id_client, $filters);
	$comptes_poids = $retour['comptes_poids'];
	//var_dump($comptes_poids);
	//
	$pagination = $retour['pagination'];

	$param='';
	//if (! empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param.='&contextpage='.$contextpage;
	if ($limit > 0 && $limit != $conf->liste_limit) $param.='&limit='.$limit;
	if ($search_all != '') $param = "&amp;sall=".urlencode($search_all);
	if ($sall != '') $param .= "&amp;sall=".urlencode($sall);

	$param .= "&amp;id_client=".$id_client;

	//exit();
	/*
	    object(stdClass)[132]
	      public 'rowid' => string '2' (length=1)
	      public 'ref' => string 'a2' (length=2)
	      public 'ref_ext' => null
	      public 'fk_soc' => string '106' (length=3)
	      public 'date_creation' => string '2017-03-08 18:00:00' (length=19)
	      public 'type' => string 'manuel' (length=6)
	      public 'ref_type' => string '' (length=0)
	      public 'id_type' => string '0' (length=1)
	      public 'description' => string 'test manuel' (length=11)
	      public 'structure' => null
	      public 'nom_client' => string 'BIJOUTERIE MU' (length=13)
	      public 'code_client' => string 'CL00093' (length=7)
	      public 'mvt' => 
	        array (size=1)
	          0 => 
	            object(stdClass)[125]
	              public 'rowid' => string '3' (length=1)
	              public 'id_comptepoid' => string '2' (length=1)
	              public 'fk_soc' => string '106' (length=3)
	              public 'date_mvt' => string '2017-03-08 18:00:00' (length=19)
	              public 'ref_matiere' => string '3' (length=1)
	              public 'qty' => string '20' (length=2)

	facture	FA1701-0984	186	test depuis facture
	*/


ob_start();
	?>


		<!-- solde final -->
		<table class="noborder" width="100%">

			<tr class="liste_titre">
				<td>Client</td>
				<?php foreach ($matieres_actives as $id_matiere => $matiere_actives) : ?>
					<td colspan="2" align="center"><?php echo $matiere_actives; ?></td>
				<?php endforeach; ?>
				
				<td align="right">M.à.j.</td>
			</tr>

			<tr class="liste_titre">
				<td>
				</td>

				<?php foreach ($matieres_actives as $id_matiere => $matiere_actives) : ?>
					<td align="center">Débit</td>
					<td align="center">Crédit</td>
				<?php endforeach; ?>

				<td align="right">
				</td>			
			</tr>		


			<?php foreach ($solde_poids as $solde_client) : ?>
			<tr>
				<td>
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
				</td>

				<?php 
				foreach ($solde_client['soldes'] as $id_matiere => $matiere_solde) : 
					// que pour les matieres actives
					if( array_key_exists($id_matiere,$matieres_actives) ) :
						//var_dump($matiere_solde['credit']);
						//var_dump(abs($matiere_solde['credit']));
				?>
					<td align="right"> <?php echo (empty($matiere_solde['debit']))?'':abs($matiere_solde['debit']) ;?>  </td>
					<td align="right"> <?php echo (empty($matiere_solde['credit']))?'':abs($matiere_solde['credit']);?>	</td>
				<?php 
					endif;
				endforeach; ?>


				<td align="right"> 
					<?php 
						//echo date('d/m/Y H:i:s',strtotime($solde_client['maj']));
			
						if ( ($search_datede=='') && ($search_datea=='') ){
							$message = "Solde du ".date('d/m/Y H:i:s',strtotime($solde_client['maj']));
						} else if ( ($search_datede=='') && ($search_datea!='') ){
							$message = "Mouvements jusqu'au ".$search_datea;
						} else if ( ($search_datede!='') && ($search_datea=='') ){
							$message = "Mouvements depuis le ".$search_datede;
						} else {
							$message = "Mouvements du ".$search_datede." au ".$search_datea;
						}
						//$message = "Solde du ".date('d/m/Y H:i:s',strtotime($solde_client['maj']));
						echo $message;
					?> 
				</td>
			</tr>
			<?php endforeach; ?>
		</table>

		<!-- filters -->
		<form name="formfilter" method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
		<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken'];?>">
		<input type="hidden" name="id_client" value="<?php echo $id_client;?>">
		<?php 
			print_barre_liste('Détails opérations', $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $pagination['num'], $pagination['nbtotalofrecords'], 'title_generic.png', 0, '', '', $limit);
		?>	

		<!-- actions -->
		<div class=" btn btn-xs btn-primary pdf_poid pull-left" 
		title="Relevé Compte poids"  
		data-id="<?php echo $compte_poids->rowid;?>"><i class="fa fa-file-pdf-o" aria-hidden="true"></i> Relevé Compte poids</div>
		

		<div class=" btn btn-xs btn-success add_poid pull-right" 
		title="Ajouter une opération"  
		data-id="<?php echo $compte_poids->rowid;?>"><i class="fa fa-plus-square" aria-hidden="true"></i> Ajouter une opération</div>

		<!-- details -->
		<table class="noborder table table-hover" width="100%">

			<tr class="liste_titre">
				<td>Date opération</td>

				<td>Origine</td>

				<?php foreach ($matieres_actives as $id_matiere => $matiere_actives) : ?>
					<td colspan="2" align="center"><?php echo $matiere_actives; ?></td>
				<?php endforeach; ?>
				
			</tr>

			<tr class="liste_titre">
				<td >
				De <input class="flat searchstring " name="search_datede" size="7" value="<?php echo $search_datede;?>" type="text" id="search_datede"> 
				A <input class="flat searchstring " name="search_datea" size="7" value="<?php echo $search_datea;?>" type="text" id="search_datea"> 
				</td>

				<td>
					<input class="flat searchstring" name="search_nom" id="search_nom" size="10" value="<?php echo $search_nom;?>" type="text">
				</td>

				<?php foreach ($matieres_actives as $id_matiere => $matiere_actives) : ?>
					<td align="center">Débit</td>
					<td align="center">Crédit</td>
				<?php endforeach; ?>

			</tr>		


			<?php foreach ($comptes_poids as $compte_poids) : 
				//var_dump($compte_poids);		
				$type = $compte_poids->type;			//facture/manuel/...
				$ref_type = $compte_poids->ref_type;
				$id_type = $compte_poids->id_type;

				$solde_abcvc = array();
				foreach ($matieres_actives as $id_matiere => $matiere_actives) {
					$solde_abcvc[$id_matiere] = array(
						'debit'=>0,
						'credit'=>0
					);
				}
			?>
			<tr>

				<td align=""> 
					<?php echo date('d/m/Y H:i:s',strtotime($compte_poids->date_creation));?> 
				</td>

				<td>
					<a href="#" class="detail_mvt" style="" data-id="<?php echo $compte_poids->rowid;?>">[Détails]</a>
					<?php 
						$details = json_decode($compte_poids->structure);
						/*var_dump($details);
						  public 'PR-0004' => 
						    array (size=6)
						      0 => string 'Or: 63.46 ( 80.48*(751/1000)*(1+(5/100)) )' (length=42)
						      1 => string 'Argent: 10.99 ( 80.48*(130/1000)*(1+(5/100)) )' (length=46)
						      2 => string 'Cuivre: 10.99 ( 80.48*(130/1000)*(1+(5/100)) )' (length=46)
						      3 => string 'Or: 82.96 ( 105.2*(751/1000)*(1+(5/100)) )' (length=42)
						      4 => string 'Argent: 14.36 ( 105.2*(130/1000)*(1+(5/100)) )' (length=46)
						      5 => string 'Cuivre: 14.36 ( 105.2*(130/1000)*(1+(5/100)) )' (length=46)*/
					?>
					<?php if(!is_null($details)) :?>
						<?php if( ($type=='facture') || ($type=='expedition') ) :?>

							<div class="tb_detail"  id="tb_detail_<?php echo $compte_poids->rowid;?>" title="<?php echo $ref_type;?>  Détail calcul par produit">
								<table class="table table-hover small " >
								<thead>
									<tr>
										<td>Produit</td>
										<td>Détail</td>
									</tr>
								</thead>
								<tbody>
								<?php foreach ($details as $ref_produit => $abcvc) :?>	
									<tr>
										<td><?php echo $ref_produit; ?></td>
										<td><?php echo implode('<br />',$abcvc); ?></td>
									</tr>
								<?php endforeach; ?>	
								</tbody>
								</table>
							</div>

						<?php elseif($type=='manuel') :?>
							<div class="tb_detail"  id="tb_detail_<?php echo $compte_poids->rowid;?>" title="Détail opération">
								<p><?php echo nl2br($compte_poids->description); ?></p>
								<p>&nbsp;</p>
								<table class="table table-hover small " >
								<thead>
									<tr>
										<td>Détail</td>
									</tr>
								</thead>
								<tbody>
								<?php foreach ($details as $abcvc) :?>	
									<tr>
										<td><?php echo $abcvc; ?></td>
									</tr>
								<?php endforeach; ?>	
						
								</tbody>
								</table>
							</div>

						<?php endif; ?>	
						
					<?php endif; ?>


					<div class=" btn btn-xs btn-danger del_poid pull-right" title="Supprimer cette opération" data-id="<?php echo $compte_poids->rowid;?>"><i class="fa fa-trash-o" aria-hidden="true"></i> </div>&nbsp;

					<?php if($type=='facture') :?>
						<a href="/compta/facture.php?mainmenu=accountancy&facid=<?php echo $id_type;?>" class=""><?php echo $ref_type;?></a> 
						<div class=" btn btn-xs btn-success recalcul_poid pull-right" title="Recalculer ce document"  data-id="<?php echo $compte_poids->rowid;?>"><i class="fa fa fa-refresh" aria-hidden="true"></i> </div>

					<?php elseif($type=='expedition') :?>


						<a href="/expedition/card.php?id=<?php echo $id_type;?>" class=""><?php echo $ref_type;?></a> 
						<div class=" btn btn-xs btn-success recalcul_poid pull-right" title="Recalculer ce document"  data-id="<?php echo $compte_poids->rowid;?>"><i class="fa fa fa-refresh" aria-hidden="true"></i> </div>

					<?php else: ?>
						-Manuel- 
						<!--<div class=" btn btn-xs btn-success modifier_poid pull-right" title="Modifier cette opération" data-id="<?php echo $compte_poids->rowid;?>"><i class="fa fa-pencil-square-o" aria-hidden="true"></i> </div>-->
					<?php endif; ?>	
				

				</td>

				<?php foreach ($compte_poids->mvt as $mvt_matiere) : 
						// que pour les matieres actives
						if( array_key_exists($mvt_matiere->ref_matiere,$matieres_actives) ) {  
							if( $mvt_matiere->qty<0 ){	
								$solde_abcvc[$mvt_matiere->ref_matiere]['debit'] += $mvt_matiere->qty;
							} else {
								$solde_abcvc[$mvt_matiere->ref_matiere]['credit'] += $mvt_matiere->qty;
							}	
						}
				?>
				<?php endforeach; ?>

				<?php foreach ($solde_abcvc as $id_matiere => $matiere_solde) : 
					$debit = number_format($matiere_solde['debit'],2) ;
					$credit = number_format($matiere_solde['credit'],2) ;

					//argh
					$debit = str_replace('-','',$debit);
					$credit = str_replace('-','',$credit);

					//$debit = $matiere_solde['debit'];
					//$credit = $matiere_solde['credit'];
				?>
					<td align="right"> <?php echo ($debit==0)?'': $debit ;?> </td>
					<td align="right"> <?php echo ($credit==0)?'': $credit ;?>	</td>

				<?php endforeach; ?>

			</tr>
			<?php endforeach; ?>
		</table>	
		</form>


		<!-- dialog add mvt -->
		<div id="add-form" title="Ajout opération manuelle">
			<p class="">Indiquez une date, les quantités de matières(+/-) ainsi qu'une description de l'opération.</p>
		 
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