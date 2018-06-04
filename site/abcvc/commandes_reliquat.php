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
$search_article=trim(GETPOST("search_article"));
$search_type=trim(GETPOST("search_type"));
if($search_type==''){
	$search_type = 1;
}

$search_datede=trim(GETPOST("search_datede"));
$search_datea=trim(GETPOST("search_datea"));

//obsolete..
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

	//liste article
	//--------------------------------------------------------
	$sql = "SELECT ";
	$sql.= " p.rowid, p.ref, p.label";
	$sql.= " FROM llx_product as p";
	$sql.= " WHERE p.fk_product_type = 0"; 
	$sql.= " ORDER BY p.ref ASC"; 
	$result = $db->query($sql);

	$list_article = array();
	if ($result) {
		$num = $db->num_rows($result);
		$i = 0;
		while ($i < $num) {
			$objp = $db->fetch_object($result);
			$list_article[] = $objp;
			$i++;
		}
	}

/*
 **************************************************************************************************************
 *
 * VIEW
 *
 **************************************************************************************************************
 */

llxHeader('', $langs->trans('ABCVC - Commandes & expéditions'), '');

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
		$('#search_type, #search_article').on( "change", function(event) { 
			document.formfilter.submit();
		});	



		$('#search_article, #search_type').select2({
		    dir: 'ltr',
			width: 'resolve',		/* off or resolve */
			minimumInputLength: 0
		});



		//init DataTable
		$('#KTable').DataTable({
			"language": {
                "url": "/abcvc/js/datatables.fr.js"
            },
            "columnDefs": [
			    { "orderable": false, "targets": 2 }
			],
			"searching": false,
			"dom": 'rt<"bottom"ilp><"clear">'
		});


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
	.tr_reliquat{
		background-color: #f006;
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
		'search_article' => $search_article,
		'search_type' => $search_type,
		'search_datede' => $search_datede,
		'search_datea' => $search_datea,

		'limit' => $limit,
		'offset' => $offset
	);
	//var_dump($filters);


	//$search_article = 129;
	//$search_type = 1;

	$commandes = array();
	$commandes_reliquat = array();

	$sql = " 	
	SELECT distinct c.rowid, c.ref, c.fk_soc, s.nom, s.code_client, s.code_fournisseur,  c.date_creation, c.date_valid, c.date_cloture
	FROM llx_commande as c
	LEFT JOIN llx_societe as s ON (s.rowid = c.fk_soc)";
	if($search_article != 0){
		$sql .= "LEFT JOIN llx_commandedet as cd ON (c.rowid = cd.fk_commande)";
	}
	
	$sql .= "WHERE c.entity IN (1) "; //c.rowid = cd.fk_commande AND c.fk_soc = s.rowid AND

	//+filtre date
	//+filtre client

	if($search_nom != ''){
		$sql .= " AND s.nom LIKE '%".$search_nom."%'";
	};

	if($search_datede != ''){
		$datede = explode('/',$search_datede);
		$sqldatede = $datede[2].'-'.$datede[1].'-'.$datede[0].' 00:00:00';
		$sql .= " AND c.date_valid >= '".$sqldatede."'";
	};

	if($search_datea != ''){
		$datea = explode('/',$search_datea);
		$sqldatea = $datea[2].'-'.$datea[1].'-'.$datea[0].' 23:59:59';
		$sql .= " AND c.date_valid <= '".$sqldatea."'";
	};


	if($search_article != 0){
		//llx_commandedet as cd, 
		$sql .= " AND ( ".(int)$search_article." IN (cd.fk_product) )";
	};
	//var_dump($sql);

	$result = $db->query($sql);
	if ($result) {
		$num = $db->num_rows($result);
		$j = 0;	
		while ($j < $num) {
			$objBL = $db->fetch_object($result);
			$commandes[] = $objBL;
			$j++;
		}
	}	
	//var_dump($commandes);
	//exit();
	/*
	    object(stdClass)[124]
	      public 'rowid' => string '123' (length=3)
	      public 'ref' => string 'CO1704-0110' (length=11)
	      public 'fk_soc' => string '109' (length=3)
	      public 'nom' => string 'LUCAS LUCOR' (length=11)
	      public 'code_client' => string 'CL00096' (length=7)
	      public 'code_fournisseur' => null
	      public 'date_creation' => string '2017-04-18 15:56:26' (length=19)
	      public 'date_valid' => string '2017-04-18 15:59:20' (length=19)
	      public 'date_cloture' => string '2017-04-18 16:05:26' (length=19)
	      public 'qty' => string '3' (length=1)
	*/

	//determination expeditions
	foreach ($commandes as $idx_commande => $commande) {

		$articles = array();
		$sql = " 	
		SELECT cd.rowid, cd.fk_product, p.ref, cd.qty 
		FROM llx_commandedet as cd
		LEFT JOIN llx_product as p ON (p.rowid = cd.fk_product)
		WHERE cd.fk_commande = ".$commande->rowid;

		if($search_article != 0){
			$sql .= " AND p.rowid = ".$search_article;   //." AND p.finished = 1";
		}
		//var_dump($sql);
		//exit();
		//cd.product_type ???
		//recup nature produit (manufacturé 1 / Matière première 0)

		$result = $db->query($sql);
		if ($result) {
			$num = $db->num_rows($result);
			$j = 0;	
			while ($j < $num) {
				$objBL = $db->fetch_object($result);
				$articles[$objBL->rowid] = $objBL;
				$j++;
			}
		}	
		//var_dump($articles);
		//exit();
		$commande->articles = $articles;

		
		$expeditions = array();
		$sql = " 	
		SELECT e.rowid, e.ref, e.date_creation, e.date_valid, e.fk_statut 
		FROM llx_expedition as e
	    LEFT JOIN llx_element_element as el on (el.fk_target = e.rowid AND el.targettype = 'shipping')
		WHERE e.fk_statut = 1 AND el.fk_source = ".$commande->rowid." ";
		//var_dump($sql);
		//exit();

		$result = $db->query($sql);
		if ($result) {
			$num = $db->num_rows($result);
			$j = 0;	
			while ($j < $num) {
				$objBL = $db->fetch_object($result);
				$expeditions[] = $objBL;
				$j++;
			}
		}	


		//expeditions par BL
		foreach ($expeditions as $idx_expedition => $expedition) {
			$expedition_lines = array();
			$sql = " 	
				SELECT ed.fk_origin_line, cd.fk_product, ed.qty
				FROM llx_expeditiondet as ed
			    LEFT JOIN llx_commandedet as cd on (cd.rowid = ed.fk_origin_line)
				WHERE ed.fk_expedition = ".$expedition->rowid." ";

			if($search_article != 0){
				$sql .= " AND cd.fk_product = ".$search_article;   //." AND p.finished = 1";
			}

			$result = $db->query($sql);
			if ($result) {
				$num = $db->num_rows($result);

				if($num==0){
					unset( $expeditions[$idx_expedition]);
					continue;
				}

				$j = 0;	
				while ($j < $num) {
					$objBL = $db->fetch_object($result);
					$expedition_lines[$objBL->fk_origin_line] = $objBL->qty;
					$j++;
				}
			}	

			$expedition->articles = $expedition_lines;
		}

		//var_dump($expeditions);
		//exit();
		$commande->expeditions = $expeditions;


		//filtre commande avec reliquat
		if($search_type == 1){
			
			$article_avec_reliquat = false;
			foreach ($commande->articles as $idx_article => $article) {
				$qty_commande = $article->qty;
				$qty_reliquat = $qty_commande;

				foreach ($commande->expeditions as $expedition) {
					if( isset($expedition->articles[$idx_article]) ) {
						$qty_reliquat -= $expedition->articles[$idx_article];
					}
				}				

				if($qty_reliquat>0){
					$article_avec_reliquat = true;
				}
			}

			//ok pas de reliquat, pouf
			if(!$article_avec_reliquat){
				unset($commandes[$idx_commande]);
			}

		}


	}
	//var_dump($commandes);
	//exit();
	/*
	    object(stdClass)[124]
	      public 'rowid' => string '123' (length=3)
	      public 'ref' => string 'CO1704-0110' (length=11)
	      public 'fk_soc' => string '109' (length=3)
	      public 'nom' => string 'LUCAS LUCOR' (length=11)
	      public 'code_client' => string 'CL00096' (length=7)
	      public 'code_fournisseur' => null
	      public 'date_creation' => string '2017-04-18 15:56:26' (length=19)
	      public 'date_valid' => string '2017-04-18 15:59:20' (length=19)
	      public 'date_cloture' => string '2017-04-18 16:05:26' (length=19)
	      public 'articles' => 
	        array (size=1)
	          0 => 
	            object(stdClass)[436]
	              public 'fk_product' => string '129' (length=3)
	              public 'ref' => string 'PR-0105' (length=7)
	              public 'qty' => string '3' (length=1)
	      public 'expeditions' => 
	        array (size=1)
	          0 => 
	            object(stdClass)[454]
	              public 'rowid' => string '147' (length=3)
	              public 'ref' => string 'SH1704-0102' (length=11)
	              public 'date_creation' => string '2017-04-18 15:59:43' (length=19)
	              public 'date_valid' => string '2017-04-18 15:59:51' (length=19)
	              public 'fk_statut' => string '1' (length=1)
	              public 'articles' => 
	                array (size=1)
	                  129 => string '3' (length=1)

			  public 'articles' => 
			                array (size=3)
			                  '81_0' => string '460' (length=3)
			                  '84_1' => string '120' (length=3)
			                  '81_2' => string '100' (length=3)

	*/                  


$param='';
//if (! empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param.='&contextpage='.$contextpage;
if ($limit > 0 && $limit != $conf->liste_limit) $param.='&limit='.$limit;
if ($search_all != '') $param = "&amp;sall=".urlencode($search_all);
if ($sall != '') $param .= "&amp;sall=".urlencode($sall);



ob_start();
?>
	<form name="formfilter" method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
	<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken'];?>">
	
	<table class="notopnoleftnoright" style="margin-bottom: 6px;" width="100%" border="0">
		<tbody>
			<tr>
				<td class="nobordernopadding hideonsmartphone" valign="middle" width="40" align="left">
					<img src="/theme/eldy/img/title_generic.png" alt="" title="" id="pictotitle" border="0">
				</td>
				<td class="nobordernopadding">
					<div class="titre">Commandes & détail des expéditions</div>
				</td>
			</tr>
		</tbody>
	</table>

	<table class="noborder table table-hover" width="100%">
		<tr class="liste_titre">
			<td width="20%">
				<input class="flat searchstring" name="search_nom" id="search_nom" size="20" value="<?php echo $search_nom;?>" type="text" placeholder="Client...">
			</td>
			<td width="20%">
				<select name="search_article" id="search_article"  >
					<option value="">Tous les produits</option>
				<?php foreach ($list_article as $article) : ?>
					<option value="<?php echo $article->rowid;?>" <?php echo ($search_article==$article->rowid)?'selected=""':''; ?>  ><?php echo $article->ref.' '.$article->label;?></option>
				<?php endforeach; ?>	
				</select>
				<?php /*	
				<input class="flat searchstring" name="search_article" id="search_article" size="20" value="<?php echo $search_article;?>" type="text" placeholder="Article...">
				*/ ?>
			</td>
			<td width="30%">
				<select id="search_type" name="search_type" >
					<option value="1" <?php echo ($search_type=="1")?'selected=""':''; ?>>Commandes avec reliquats</option>
					<option value="0" <?php echo ($search_type=="0")?'selected=""':''; ?>>Toutes les commandes </option>
				</select>
			</td>

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

			<td>
				Commandes
			</td>

			<td>
				Produits commandés / expédiés 
			</td>
		</tr>
		</thead>

		<tbody>
		<?php foreach ($commandes as $commande) : ?>
		<tr>
			<td width="30%">
				<a href="/societe/soc.php?mainmenu=companies&socid=<?php echo $commande->fk_soc;?>" class=""><?php echo $commande->nom;?> (<?php 
				if(!is_null($commande->code_client)){
					$code = $commande->code_client;
				} elseif(!is_null($commande->code_fournisseur)){
					$code = $commande->code_fournisseur;
				} else {
					$code = '?';
				}
				echo $code; //$commande->code_client;
				?>)</a>
			</td>		

			<td width="10%">
				<a href="/commande/card.php?id=<?php echo $commande->rowid;?>" class=""><?php echo $commande->ref;?></a>
				<div class="small"><?php echo date('d/m/Y',strtotime($commande->date_valid)) ;?> </div>
			</td>

			<td width="40%">

				<table class="table">
					<thead>
						<tr>
							<th>Produit / Quantité </th>

							<?php if (count($commande->expeditions)==0) : ?>
								<th>Aucune expédition </th>
							<?php endif; ?>	

							<?php foreach ($commande->expeditions as $expedition) : ?>
								<th>
									<a href="/expedition/card.php?id=<?php echo $expedition->rowid;?>" class=""><?php echo $expedition->ref;?></a> 
								</th>
							<?php endforeach; ?>

							<th>Reliquat </th>	
						</tr>
					</thead>
					<tbody>

							<?php foreach ($commande->articles as $idx_article => $article) : 
								/*
								      public 'articles' => 
								        array (size=1)
								          0 => 
								            object(stdClass)[436]
								              public 'fk_product' => string '129' (length=3)
								              public 'ref' => string 'PR-0105' (length=7)
								              public 'qty' => string '3' (length=1)
								*/
								$qty_commande = $article->qty;
								$qty_reliquat = $qty_commande;
	
									foreach ($commande->expeditions as $expedition) : 
										if( isset($expedition->articles[$idx_article]) ) {
											$qty_reliquat -= $expedition->articles[$idx_article];
										}
									?>	
									<?php endforeach; ?>				
								<tr class="<?php echo ( ($qty_reliquat<0) || ($qty_reliquat>0) )?'tr_reliquat':'';?>">
									<td>
										<a href="/product/card.php?id=<?php echo $article->fk_product;?>"><?php echo $article->ref; ?></a> / <?php echo $article->qty;?>	
									</td>

									<?php if (count($commande->expeditions)==0) : ?>
										<td align="center"> - </td>
									
									<?php else: ?>

										<?php foreach ($commande->expeditions as $expedition) : ?>

											<td align="center">
												<?php echo @$expedition->articles[$idx_article];?>
											</td>											
										<?php endforeach; ?>										

									<?php endif; ?>

									<td align="center">
										<?php echo $qty_reliquat;?>
									</td>
								</tr>
							<?php endforeach; ?>
						
					</tbody>
				</table>

	
			</td>

			
		<?php endforeach; ?>

		</tbody>
	</table>


	</form>	



<?php
$html = ob_get_clean();

print $html;






// End of page
llxFooter();

$db->close();