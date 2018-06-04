<?php
/* Copyright (C) 2010-2012	Regis Houssin  <regis.houssin@capnetworks.com>
 * Copyright (C) 2015		Charlie Benke  <charlie@patas-monkey.com>

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
 * or see http://www.gnu.org/
 */

/**
 *	\file       htdocs/core/modules/project/doc/pdf_abcvc.modules.php
 *	\ingroup    project
 *	\brief      Fichier de la classe permettant de generer les projets au modele abcvc
 *	\author	    Charlie Benke
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/projectAbcvc/modules_projectAbcvc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/abcvc/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/abcvc/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/abcvc/lib/project.lib.php';

if (! empty($conf->propal->enabled))      require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
if (! empty($conf->facture->enabled))     require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
if (! empty($conf->facture->enabled))     require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture-rec.class.php';
if (! empty($conf->commande->enabled))    require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
if (! empty($conf->fournisseur->enabled)) require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
if (! empty($conf->fournisseur->enabled)) require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
if (! empty($conf->contrat->enabled))     require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
if (! empty($conf->ficheinter->enabled))  require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
if (! empty($conf->deplacement->enabled)) require_once DOL_DOCUMENT_ROOT.'/compta/deplacement/class/deplacement.class.php';
if (! empty($conf->agenda->enabled))      require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';



/**
 *	Classe permettant de generer les projets au modele Baleine
 */

class pdf_abcvc extends ModelePDFProjects
{
	var $emetteur;	// Objet societe qui emet

	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	function __construct($db)
	{
		global $conf,$langs,$mysoc;

		$langs->load("main");
		$langs->load("projects");
		$langs->load("companies");

		$this->db = $db;
		$this->name = "abcvc";
		$this->description = $langs->trans("DocumentModelabcvc");

		// Dimension page pour format A4
		$this->type = 'pdf';
		$formatarray=pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur,$this->page_hauteur);
		$this->marge_gauche=isset($conf->global->MAIN_PDF_MARGIN_LEFT)?$conf->global->MAIN_PDF_MARGIN_LEFT:10;
		$this->marge_droite=isset($conf->global->MAIN_PDF_MARGIN_RIGHT)?$conf->global->MAIN_PDF_MARGIN_RIGHT:10;
		$this->marge_haute =isset($conf->global->MAIN_PDF_MARGIN_TOP)?$conf->global->MAIN_PDF_MARGIN_TOP:10;
		$this->marge_basse =isset($conf->global->MAIN_PDF_MARGIN_BOTTOM)?$conf->global->MAIN_PDF_MARGIN_BOTTOM:10;

		$this->option_logo = 1;                    // Affiche logo FAC_PDF_LOGO
		$this->option_tva = 1;                     // Gere option tva FACTURE_TVAOPTION
		$this->option_codeproduitservice = 1;      // Affiche code produit-service

		// Recupere emmetteur
		$this->emetteur=$mysoc;
		if (! $this->emetteur->country_code) $this->emetteur->country_code=substr($langs->defaultlang,-2);    // By default if not defined

		// Defini position des colonnes
		$this->posxref=$this->marge_gauche+1;
		$this->posxdate=$this->marge_gauche+25;
		$this->posxsociety=$this->marge_gauche+45;
		$this->posxamountht=$this->marge_gauche+110;
		$this->posxamountttc=$this->marge_gauche+135;
		$this->posxstatut=$this->marge_gauche+165;
	}


	/**
	 *	Fonction generant le projet sur le disque
	 *
	 *	@param	Project		$object   		Object project a generer
	 *	@param	Translate	$outputlangs	Lang output object
	 *	@return	int         				1 if OK, <=0 if KO
	 */
	function write_file($object,$outputlangs)
	{

        // var_dump($object);
        //exit();

		global $user,$langs,$conf;

        $formproject=new FormProjets($this->db);

		if (! is_object($outputlangs)) $outputlangs=$langs;
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (! empty($conf->global->MAIN_USE_FPDF)) $outputlangs->charset_output='ISO-8859-1';

		$outputlangs->load("main");
		$outputlangs->load("dict");
		$outputlangs->load("companies");
		$outputlangs->load("projects");

		if ($conf->projet->dir_output)
		{
			//$nblignes = count($object->lines);  // This is set later with array of tasks

			$objectref = dol_sanitizeFileName($object->ref);
			$dir = $conf->projet->dir_output;
			if (! preg_match('/specimen/i',$objectref)) $dir.= "/" . $objectref;
			$file = $dir . "/" . $objectref . ".pdf";

			if (! file_exists($dir))
			{
				if (dol_mkdir($dir) < 0)
				{
					$this->error=$langs->transnoentities("ErrorCanNotCreateDir",$dir);
					return 0;
				}
			}

			if (file_exists($dir))
			{
				// Add pdfgeneration hook
				if (! is_object($hookmanager))
				{
					include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
					$hookmanager=new HookManager($this->db);
				}
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters=array('file'=>$file,'object'=>$object,'outputlangs'=>$outputlangs);
				global $action;
				$reshook=$hookmanager->executeHooks('beforePDFCreation',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks

                $pdf=pdf_getInstance($this->format);
                $default_font_size = pdf_getPDFFontSize($outputlangs);	// Must be after pdf_getInstance
                $heightforinfotot = 50;	// Height reserved to output the info and total part
		        $heightforfreetext= (isset($conf->global->MAIN_PDF_FREETEXT_HEIGHT)?$conf->global->MAIN_PDF_FREETEXT_HEIGHT:5);	// Height reserved to output the free text on last page
	            $heightforfooter = $this->marge_basse + 8;	// Height reserved to output the footer (value include bottom margin)
                $pdf->SetAutoPageBreak(1,0);

                if (class_exists('TCPDF'))
                {
                    $pdf->setPrintHeader(false);
                    $pdf->setPrintFooter(false);
                }
                $pdf->SetFont(pdf_getPDFFont($outputlangs));

				// Complete object by loading several other informations
				$task = new TaskABCVC($this->db);
				$tasksarray = $task->getTasksArray(0,0,$object->id);

                //var_dump($tasksarray);
                //exit();


				$object->lines=$tasksarray;
				$nblignes=count($object->lines);

				$pdf->Open();
				$pagenb=0;
				$pdf->SetDrawColor(128,128,128);

				$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
				$pdf->SetSubject($outputlangs->transnoentities("Project"));
				$pdf->SetCreator("Dolibarr ".DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("Project"));
				if (! empty($conf->global->MAIN_DISABLE_PDF_COMPRESSION)) $pdf->SetCompression(false);

				$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);   // Left, Top, Right

				// New page
				$pdf->AddPage();
				$pagenb++;
				$this->_pagehead($pdf, $object, 1, $outputlangs);
				$pdf->SetFont('','', $default_font_size - 1);
				$pdf->MultiCell(0, 3, '');		// Set interline to 3
				$pdf->SetTextColor(0,0,0);

				$tab_top = 50;
				$tab_height = 200;
				$tab_top_newpage = 40;
                $tab_height_newpage = 210;

				// Affiche notes
				if (! empty($object->note_public))
				{
					$pdf->SetFont('','', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3, $this->posxref-1, $tab_top-2, dol_htmlentitiesbr($object->note_public), 0, 1);
					$nexY = $pdf->GetY();
					$height_note=$nexY-($tab_top-2);

					// Rect prend une longueur en 3eme param
					$pdf->SetDrawColor(192,192,192);
					$pdf->Rect($this->marge_gauche, $tab_top-3, $this->page_largeur-$this->marge_gauche-$this->marge_droite, $height_note+1);

					$tab_height = $tab_height - $height_note;
					$tab_top = $nexY+6;
				}
				else
				{
					$height_note=0;
				}

				$iniY = $tab_top + 7;
				$curY = $tab_top + 7;
				$nexY = $tab_top + 7;


/* *******************************************************************************************************************************************************************

        INFORMATION GENERATE TO PDF FILE 

********************************************************************************************************************************************************************/

//get project tree
$projectTree = $object->getProjectTree($object->id, $user);

//start a buffer
ob_start();
?>

<h2></h2>

<table>
    <thead>
        <tr>
            <th><b>Projet:&nbsp;</b><?php print nl2br($object->titre); ?></th>
            <th><b>Description</b></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><b>Client:&nbsp;</b><?php print nl2br($object->thirdparty->nom); ?>&nbsp;(<?php print nl2br($object->thirdparty->name_alias); ?>)<br><b>Budget:</b>&nbsp;<?php print price2num($object->budget_amount); ?>&nbsp;€</td>
            <td><?php print nl2br($object->description); ?></td>
        </tr>
    </tbody>
</table>

<h2></h2>

<?php $total = 0;?>
<table cellspacing="0" cellpadding="1" border="0.5" width="100%">
    <thead >
        <tr style="background-color:#FFFF00;color:#000000;" >
            <th align="left"  width="15%" height="20"  ><b>Code</b></th>
            <th align="left" height="20" width="30%" ><b>Libellé</b></th>
            <th align="center" width="15%" height="20" ><b>Couts</b></th>
            <th align="center" width="20%" height="20" ><b>Avancement estimé</b></th>
            <th align="center" width="20%" height="20" ><b>Avancement réél</b></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $projectTree['tree'] as $key => $lot) : ?>
        <?php  $total +=price2num($lot->cost_lot); ?>
        <tr >
            <td width="15%"><b><?php echo $lot->ref;?></b></td>
            <td align="center" width="30%"><b><?php echo $lot->label; ?></b></td>
            <td align="right" width="15%"><b><?php echo price2num($lot->cost_lot); ?>&nbsp;€</b></td>
            <td align="center" width="20%">--</td>
            <td align="center" width="20%">--</td>
        </tr>
            <?php foreach ( $lot->categories as $key => $categorie) : ?>
                <tr>
                    <td ><?php echo $categorie->ref; ?></td>
                    <td><?php echo $categorie->label; ?></td>
                    <td align="right" ><?php echo price2num($categorie->cost_categorie); ?>&nbsp;€</td>
                    <td align="center">--</td>
                    <td align="center">--</td>
                </tr>

                <?php foreach ( $categorie->postes as $key => $poste) : ?>  
                    <tr>
                        <td ><?php echo $poste->ref; ?></td>
                        <td><?php echo $poste->label; ?></td>
                        <td align="right" ><?php echo price2num($poste->cost_final); ?>&nbsp;€</td>
                        <td align="center">--</td>
                        <td align="center">--</td>
                    </tr>

                    <?php foreach ( $poste->subpostes as $key => $subposte) : ?>
                        <tr>
                            <td ><?php echo $subposte->ref; ?></td>
                            <td><?php echo $subposte->label; ?></td>
                            <td align="right" ><?php echo price2num($subposte->cost_final); ?>&nbsp;€</td>
                            <td align="center">--</td>
                            <td align="center">--</td>
                        </tr>

                        <?php foreach ( $subposte->subsubpostes as $key => $subsubposte) : ?>
                            <tr>
                                <td ><?php echo $subsubposte->ref; ?></td>
                                <td><?php echo $subsubposte->label; ?></td>
                                <td align="right" ><?php echo price2num($subsubposte->cost_final); ?>&nbsp;€</td>
                                <td align="center">--</td>
                                <td align="center">--</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <tr>
            <td ></td>
            <td align="center" ><b> Sous Total </b></td>
            <td align="right" ><b><?php echo price2num($total)?>&nbsp;€</b></td>
            <td align="center"></td>
            <td align="center"></td>
        </tr>
    </tbody>
</table>
<h2></h2>

<!-- TABLE TO SHOW ANOTHER DETAILS ABOUT FACTURE [TODO DE FACUT ANUMITE CALCULE PENTRU A LE INTRODUCE IN TABELA]  -->
<table cellspacing="0" cellpadding="1">
    <tr >
        <td border="1"><b>Montant total avancement groupement</b></td>
        <td align="center" border="1">--&nbsp;€</td>
    </tr>
    <tr>
        <td border="1"><b>Montant situations précédentes</b></td>
        <td align="center" border="1">--&nbsp;€</td>
    </tr>
    <tr>
        <td border="1"><b>Montant situation du mois</b></td>
        <td align="center" border="1">--&nbsp;€</td>
    </tr>
    <tr>
        <td></td>
        <td></td>
    </tr>
    <tr style="background-color:#FFFF00;color:#000000;" >
        <td border="1"><b>Montant HT situation du Mois De [TODO output SEASON ] ABCVC</b></td>
        <td align="center" border="1">--&nbsp;€</td>
    </tr>
    <tr style="background-color:#FFFF00;color:#000000;">
        <td border="1"><b>TVA 20%</b></td>
        <td align="center" border="1">--&nbsp;€</td>
    </tr>
    <tr style="background-color:#FFFF00;color:#000000;">
        <td border="1"><b>Montant situation TTC du mois ABCVC</b></td>
        <td align="center" border="1">--&nbsp;€</td>
    </tr>
    <tr>
        <td></td>
        <td></td>
    </tr>
    <tr>
        <td></td>
        <td></td>
    </tr>
    <tr style="background-color:#188FD2;color:#000000;">
        <td border="1"><b>SITUATION [TODO output SEASON ] TMA </b></td>
        <td align="center" border="1">--&nbsp;€</td>
    </tr>
    <tr style="background-color:#0FAC03;color:#000000;">
        <td border="1"><b>SITUATION [TODO output SEASON ] CPCM</b></td>
        <td align="center" border="1">--&nbsp;€</td>
    </tr>
    <tr>
        <td></td>
        <td></td>
    </tr>
    <tr style="background-color:#FFFF00;color:#000000;" >
        <td border="1"><b>Montant Règlemenent TTC du mois de [TODO output SEASON ] ABCVC</b></td>
        <td align="center" border="1">--&nbsp;€</td>
    </tr>
    <tr>
        <td></td>
        <td></td>
    </tr>
</table>

<table>
    <thead>
        <tr style="color:#AC0309;">
            <td align="center"><b>VALEUR EN VOTRE AIMABLE REGLEMENT T.T.C.</b></td>
        </tr>
    </thead>
</table>

<?php 
//put buffer in variable html
$html = ob_get_clean();


// output the HTML content
$pdf->writeHTML($html, true, false, true, false, '');

/* *******************************************************************************************************************************************************************

        OLD INFORMATION GENERATOR

********************************************************************************************************************************************************************/
    /*                       
                    $listofreferent=array(
                        'propal'=>array(
                        	'name'=>"Proposals",
                        	'title'=>"ListProposalsAssociatedProject",
                        	'class'=>'Propal',
                        	'table'=>'propal',
                            'datefieldname'=>'datep',
                        	'test'=>$conf->propal->enabled && $user->rights->propale->lire,
                            'lang'=>'propal'),
                        'order'=>array(
                        	'name'=>"CustomersOrders",
                        	'title'=>"ListOrdersAssociatedProject",
                        	'class'=>'Commande',
                        	'table'=>'commande',
                        	'datefieldname'=>'date_commande',
                        	'test'=>$conf->commande->enabled && $user->rights->commande->lire,
                            'lang'=>'order'),
                        'invoice'=>array(
                        	'name'=>"CustomersInvoices",
                        	'title'=>"ListInvoicesAssociatedProject",
                        	'class'=>'Facture',
                        	'margin'=>'add',
                        	'table'=>'facture',
                        	'datefieldname'=>'datef',
                        	'test'=>$conf->facture->enabled && $user->rights->facture->lire,
                            'lang'=>'bills'),
                        'invoice_predefined'=>array(
                        	'name'=>"PredefinedInvoices",
                        	'title'=>"ListPredefinedInvoicesAssociatedProject",
                        	'class'=>'FactureRec',
                        	'table'=>'facture_rec',
                        	'datefieldname'=>'datec',
                        	'test'=>$conf->facture->enabled && $user->rights->facture->lire,
                            'lang'=>'bills'),
                        'order_supplier'=>array(
                        	'name'=>"SuppliersOrders",
                        	'title'=>"ListSupplierOrdersAssociatedProject",
                        	'class'=>'CommandeFournisseur',
                        	'table'=>'commande_fournisseur',
                        	'datefieldname'=>'date_commande',
                        	'test'=>$conf->fournisseur->enabled && $user->rights->fournisseur->commande->lire,
                            'lang'=>'orders'),
                        'invoice_supplier'=>array(
                        	'name'=>"BillsSuppliers",
                        	'title'=>"ListSupplierInvoicesAssociatedProject",
                        	'class'=>'FactureFournisseur',
                        	'margin'=>'minus',
                        	'table'=>'facture_fourn',
                        	'datefieldname'=>'datef',
                        	'test'=>$conf->fournisseur->enabled && $user->rights->fournisseur->facture->lire,
                            'lang'=>'bills'),
                        'contract'=>array(
                        	'name'=>"Contracts",
                        	'title'=>"ListContractAssociatedProject",
                        	'class'=>'Contrat',
                        	'table'=>'contrat',
                        	'datefieldname'=>'date_contrat',
                        	'test'=>$conf->contrat->enabled && $user->rights->contrat->lire,
                            'lang'=>'contract'),
                        'intervention'=>array(
                        	'name'=>"Interventions",
                        	'title'=>"ListFichinterAssociatedProject",
                        	'class'=>'Fichinter',
                        	'table'=>'fichinter',
                        	'datefieldname'=>'date_valid',
                        	'disableamount'=>1,
                        	'test'=>$conf->ficheinter->enabled && $user->rights->ficheinter->lire,
                            'lang'=>'interventions'),
                        'trip'=>array(
                        	'name'=>"TripsAndExpenses",
                        	'title'=>"ListExpenseReportsAssociatedProject",
                        	'class'=>'Deplacement',
                        	'table'=>'deplacement',
                        	'datefieldname'=>'dated',
                        	'margin'=>'minus',
                        	'disableamount'=>1,
                        	'test'=>$conf->deplacement->enabled && $user->rights->deplacement->lire,
                            'lang'=>'trip'),
                        'expensereport'=>array(
                        	'name'=>"ExpensesReports",
                        	'title'=>"ListExpenseReportsAssociatedProject",
                        	'class'=>'ExpenseReport',
                        	'table'=>'expensereport',
                        	'datefieldname'=>'dated',
                        	'margin'=>'minus',
                        	'disableamount'=>1,
                        	'test'=>$conf->expensereport->enabled && $user->rights->expensereport->lire,
                            'lang'=>'trip'),                    
                        'agenda'=>array(
                        	'name'=>"Agenda",
                        	'title'=>"ListActionsAssociatedProject",
                        	'class'=>'ActionComm',
                        	'table'=>'actioncomm',
                        	'datefieldname'=>'datep',
                        	'disableamount'=>1,
                        	'test'=>$conf->agenda->enabled && $user->rights->agenda->allactions->read,
                            'lang'=>'agenda')
                    );
                    
                    
                    foreach ($listofreferent as $key => $value)
                    {
                    	$title=$value['title'];
                    	$classname=$value['class'];
                    	$tablename=$value['table'];
                    	$datefieldname=$value['datefieldname'];
                    	$qualified=$value['test'];
                    	$langstoload=$value['lang'];
                    	$langs->load($langstoload);
                    	
                        if ($qualified)
                        {
                            //var_dump("$key, $tablename, $datefieldname, $dates, $datee");
                            $elementarray = $object->get_element_list($key, $tablename, $datefieldname, $dates, $datee);
                            //var_dump($elementarray);
                            
                            $num = count($elementarray);
                            if ($num >= 0)
                            {
                                $nexY = $pdf->GetY() + 5;
                                $curY = $nexY;
                                $pdf->SetXY($this->posxref, $curY);
                                $pdf->MultiCell($this->posxstatut - $this->posxref, 3, $outputlangs->transnoentities($title), 0, 'L');
                                
                                $selectList = $formproject->select_element($tablename, $project->thirdparty->id);
                                $nexY = $pdf->GetY() + 1;
                                $curY = $nexY;
                                $pdf->SetXY($this->posxref, $curY);
                                $pdf->MultiCell($this->posxdate - $this->posxref, 3, $outputlangs->transnoentities("Ref"), 1, 'L');
                                $pdf->SetXY($this->posxdate, $curY);
                                $pdf->MultiCell($this->posxsociety - $this->posxdate, 3, $outputlangs->transnoentities("Date"), 1, 'C');
                                $pdf->SetXY($this->posxsociety, $curY);
                                $pdf->MultiCell($this->posxamountht - $this->posxsociety, 3, $outputlangs->transnoentities("ThirdParty"), 1, 'L');
                                if (empty($value['disableamount'])) {
                                    $pdf->SetXY($this->posxamountht, $curY);
                                    $pdf->MultiCell($this->posxamountttc - $this->posxamountht, 3, $outputlangs->transnoentities("AmountHTShort"), 1, 'R');
                                    $pdf->SetXY($this->posxamountttc, $curY);
                                    $pdf->MultiCell($this->posxstatut - $this->posxamountttc, 3, $outputlangs->transnoentities("AmountTTCShort"), 1, 'R');
                                } else {
                                    $pdf->SetXY($this->posxamountht, $curY);
                                    $pdf->MultiCell($this->posxstatut - $this->posxamountht, 3, "", 1, 'R');
                                }
                                $pdf->SetXY($this->posxstatut, $curY);
                                $pdf->MultiCell($this->page_largeur - $this->marge_droite - $this->posxstatut, 3, $outputlangs->transnoentities("Statut"), 1, 'R');
                                
                                if (is_array($elementarray) && count($elementarray) > 0)
                                {
                                    $nexY = $pdf->GetY();
                                    $curY = $nexY;
                                    
                                    $total_ht = 0;
                                    $total_ttc = 0;
                                    $num = count($elementarray);
                                    for ($i = 0; $i < $num; $i ++) {
                                        $element = new $classname($this->db);
                                        $element->fetch($elementarray[$i]);
                                        $element->fetch_thirdparty();
                                        // print $classname;
                                        
                                        $qualifiedfortotal = true;
                                        if ($key == 'invoice') {
                                            if ($element->close_code == 'replaced')
                                                $qualifiedfortotal = false; // Replacement invoice
                                        }
                                        
                                        $pdf->SetXY($this->posxref, $curY);
                                        $pdf->MultiCell($this->posxdate - $this->posxref, 3, $element->ref, 1, 'L');
                                        
                                        // Date
                                        if ($tablename == 'commande_fournisseur' || $tablename == 'supplier_order')
                                            $date = $element->date_commande;
                                        else {
                                            $date = $element->date;
                                            if (empty($date))
                                                $date = $element->datep;
                                            if (empty($date))
                                                $date = $element->date_contrat;
                                            if (empty($date))
                                                $date = $element->datev; // Fiche inter
                                        }
                                        
                                        $pdf->SetXY($this->posxdate, $curY);
                                        $pdf->MultiCell($this->posxsociety - $this->posxdate, 3, dol_print_date($date, 'day'), 1, 'C');
                                        
                                        $pdf->SetXY($this->posxsociety, $curY);
                                        if (is_object($element->thirdparty))
                                            $pdf->MultiCell($this->posxamountht - $this->posxsociety, 3, $element->thirdparty->name, 1, 'L');
                                            
                                            // Amount without tax
                                        if (empty($value['disableamount'])) {
                                            $pdf->SetXY($this->posxamountht, $curY);
                                            $pdf->MultiCell($this->posxamountttc - $this->posxamountht, 3, (isset($element->total_ht) ? price($element->total_ht) : '&nbsp;'), 1, 'R');
                                            $pdf->SetXY($this->posxamountttc, $curY);
                                            $pdf->MultiCell($this->posxstatut - $this->posxamountttc, 3, (isset($element->total_ttc) ? price($element->total_ttc) : '&nbsp;'), 1, 'R');
                                        } else {
                                            $pdf->SetXY($this->posxamountht, $curY);
                                            $pdf->MultiCell($this->posxstatut - $this->posxamountht, 3, "", 1, 'R');
                                        }
                                        
                                        // Status
                                        if ($element instanceof CommonInvoice) {
                                            // This applies for Facture and FactureFournisseur
                                            $outputstatut = $element->getLibStatut(1, $element->getSommePaiement());
                                        } else {
                                            $outputstatut = $element->getLibStatut(1);
                                        }
                                        $pdf->SetXY($this->posxstatut, $curY);
                                        $pdf->MultiCell($this->page_largeur - $this->marge_droite - $this->posxstatut, 3, $outputstatut, 1, 'R', false, 1, '', '', true, 0, true);
                                        
                                        if ($qualifiedfortotal) {
                                            $total_ht = $total_ht + $element->total_ht;
                                            $total_ttc = $total_ttc + $element->total_ttc;
                                        }
                                        $nexY = $pdf->GetY();
                                        $curY = $nexY;
                                    }
                                    
                                    if (empty($value['disableamount'])) {
                                        $curY = $nexY;
                                        $pdf->SetXY($this->posxref, $curY);
                                        $pdf->MultiCell($this->posxamountttc - $this->posxref, 3, "TOTAL", 1, 'L');
                                        $pdf->SetXY($this->posxamountht, $curY);
                                        $pdf->MultiCell($this->posxamountttc - $this->posxamountht, 3, (isset($element->total_ht) ? price($total_ht) : '&nbsp;'), 1, 'R');
                                        $pdf->SetXY($this->posxamountttc, $curY);
                                        $pdf->MultiCell($this->posxstatut - $this->posxamountttc, 3, (isset($element->total_ttc) ? price($total_ttc) : '&nbsp;'), 1, 'R');
                                        $pdf->SetXY($this->posxstatut, $curY);
                                        $pdf->MultiCell($this->page_largeur - $this->marge_droite - $this->posxstatut, 3, $outputlangs->transnoentities("Nb") . " " . $num, 1, 'L');
                                    }
                                    $nexY = $pdf->GetY() + 5;
                                    $curY = $nexY;
                                }
                            }
                        }
                    }
    */
    				/*
    				 * Pied de page
    				 */
    				$this->_pagefoot($pdf,$object,$outputlangs);
    				if (method_exists($pdf,'AliasNbPages')) $pdf->AliasNbPages();

    				$pdf->Close();

    				$pdf->Output($file,'F');

    				// Add pdfgeneration hook
    				if (! is_object($hookmanager))
    				{
    					include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
    					$hookmanager=new HookManager($this->db);
    				}
    				$hookmanager->initHooks(array('pdfgeneration'));
    				$parameters=array('file'=>$file,'object'=>$object,'outputlangs'=>$outputlangs);
    				global $action;
    				$reshook=$hookmanager->executeHooks('afterPDFCreation',$parameters,$this,$action);    // Note that $action and $object may have been modified by some hooks

    				if (! empty($conf->global->MAIN_UMASK))
    				@chmod($file, octdec($conf->global->MAIN_UMASK));

    				return 1;   // Pas d'erreur
    			}
    			else
    			{
    				$this->error=$langs->transnoentities("ErrorCanNotCreateDir",$dir);
    				return 0;
    			}
    		}

    		$this->error=$langs->transnoentities("ErrorConstantNotDefined","LIVRAISON_OUTPUTDIR");
    		return 0;
    	}


	/**
	 *   Show table for lines
	 *
	 *   @param		PDF			$pdf     		Object PDF
	 *   @param		string		$tab_top		Top position of table
	 *   @param		string		$tab_height		Height of table (rectangle)
	 *   @param		int			$nexY			Y
	 *   @param		Translate	$outputlangs	Langs object
	 *   @param		int			$hidetop		Hide top bar of array
	 *   @param		int			$hidebottom		Hide bottom bar of array
	 *   @return	void
	 */
	function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop=0, $hidebottom=0)
	{
		global $conf,$mysoc;

        $default_font_size = pdf_getPDFFontSize($outputlangs);

		$pdf->SetDrawColor(128,128,128);

		// Rect prend une longueur en 3eme param
		$pdf->Rect($this->marge_gauche, $tab_top, $this->page_largeur-$this->marge_gauche-$this->marge_droite, $tab_height);
		// line prend une position y en 3eme param
		$pdf->line($this->marge_gauche, $tab_top+6, $this->page_largeur-$this->marge_droite, $tab_top+6);

		$pdf->SetTextColor(0,0,0);
		$pdf->SetFont('','', $default_font_size);

		$pdf->SetXY($this->posxref, $tab_top+1);
		$pdf->MultiCell($this->posxlabel-$this->posxref,3, $outputlangs->transnoentities("Tasks"),'','L');

		$pdf->SetXY($this->posxlabel, $tab_top+1);
		$pdf->MultiCell($this->posxworkload-$this->posxlabel, 3, $outputlangs->transnoentities("Description"), 0, 'L');

		$pdf->SetXY($this->posxworkload, $tab_top+1);
		$pdf->MultiCell($this->posxprogress-$this->posxworkload, 3, $outputlangs->transnoentities("PlannedWorkloadShort"), 0, 'R');

		$pdf->SetXY($this->posxprogress, $tab_top+1);
		$pdf->MultiCell($this->posxdatestart-$this->posxprogress, 3, '%', 0, 'R');

		$pdf->SetXY($this->posxdatestart, $tab_top+1);
		$pdf->MultiCell($this->posxdateend-$this->posxdatestart, 3, '', 0, 'C');

		$pdf->SetXY($this->posxdateend, $tab_top+1);
		$pdf->MultiCell($this->page_largeur - $this->marge_droite - $this->posxdatestart, 3, '', 0, 'C');

	}

	/**
	 *  Show top header of page.
	 *
	 *  @param	PDF			$pdf     		Object PDF
	 *  @param  Project		$object     	Object to show
	 *  @param  int	    	$showaddress    0=no, 1=yes
	 *  @param  Translate	$outputlangs	Object lang for output
	 *  @return	void
	 */
	function _pagehead(&$pdf, $object, $showaddress, $outputlangs)
	{
		global $langs,$conf,$mysoc;

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		pdf_pagehead($pdf,$outputlangs,$this->page_hauteur);

		$pdf->SetTextColor(0,0,60);
		$pdf->SetFont('','B', $default_font_size + 3);

        $posx=$this->page_largeur-$this->marge_droite-100;
		$posy=$this->marge_haute;

		$pdf->SetXY($this->marge_gauche,$posy);

		// Logo
		$logo=$conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
		if ($mysoc->logo)
		{
			if (is_readable($logo))
			{
			    $height=pdf_getHeightForLogo($logo);
			    $pdf->Image($logo, $this->marge_gauche, $posy, 0, $height);	// width=0 (auto)
			}
			else
			{
				$pdf->SetTextColor(200,0,0);
				$pdf->SetFont('','B', $default_font_size - 2);
				$pdf->MultiCell(100, 3, $langs->transnoentities("ErrorLogoFileNotFound",$logo), 0, 'L');
				$pdf->MultiCell(100, 3, $langs->transnoentities("ErrorGoToModuleSetup"), 0, 'L');
			}
		}
		else $pdf->MultiCell(100, 4, $outputlangs->transnoentities($this->emetteur->name), 0, 'L');

		$pdf->SetFont('','B', $default_font_size + 3);
		$pdf->SetXY($posx,$posy);
		$pdf->SetTextColor(0,0,60);
		$pdf->MultiCell(100, 4, $outputlangs->transnoentities("Project")." ".$outputlangs->convToOutputCharset($object->ref), '', 'R');
		$pdf->SetFont('','', $default_font_size + 2);

		$posy+=6;
		$pdf->SetXY($posx,$posy);
		$pdf->SetTextColor(0,0,60);
		$pdf->MultiCell(100, 4, $outputlangs->transnoentities("DateStart")." : " . dol_print_date($object->date_start,'day',false,$outputlangs,true), '', 'R');
		$posy+=6;
		$pdf->SetXY($posx,$posy);
		$pdf->MultiCell(100, 4, $outputlangs->transnoentities("DateEnd")." : " . dol_print_date($object->date_end,'day',false,$outputlangs,true), '', 'R');

		$pdf->SetTextColor(0,0,60);

	}

	/**
	 *   	Show footer of page. Need this->emetteur object
     *
	 *   	@param	PDF			$pdf     			PDF
	 * 		@param	Project		$object				Object to show
	 *      @param	Translate	$outputlangs		Object lang for output
	 *      @param	int			$hidefreetext		1=Hide free text
	 *      @return	integer
	 */
	function _pagefoot(&$pdf,$object,$outputlangs,$hidefreetext=0)
	{
		global $conf;
		$showdetails=$conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS;
		return pdf_pagefoot($pdf,$outputlangs,'PROJECT_FREE_TEXT',$this->emetteur,$this->marge_basse,$this->marge_gauche,$this->page_hauteur,$object,$showdetails,$hidefreetext);
	}
}



/* *******************************************************************************************************************************************************************

OBJECT ABCVC PROJET

*******************************************************************************************************************************************************************

object(ProjectABCVC)[128]
  public 'element' => string 'projectabcvc' (length=12)
  public 'table_element' => string 'abcvc_projet' (length=12)
  public 'table_element_line' => string 'projet_task' (length=11)
  public 'fk_element' => string 'fk_projet' (length=9)
  protected 'ismultientitymanaged' => int 1
  public 'picto' => string 'projectpub' (length=10)
  protected 'table_ref_field' => string 'ref' (length=3)
  public 'description' => string 'awefmkawmjgfakwjgm;aw m;m aw; maw;m awf;m awefmkawmjgfakwjgm;aw m;m aw; maw;m awf;m awefmkawmjgfakwjgm;aw m;m aw; maw;m awf;m awefmkawmjgfakwjgm;aw m;m aw; maw;m awf;m awefmkawmjgfakwjgm;aw m;m aw; maw;m awf;m awefmkawmjgfakwjgm;aw m;m aw; maw;m awf;m awefmkawmjgfakwjgm;aw m;m aw; maw;m awf;m awefmkawmjgfakwjgm;aw m;m aw; maw;m awf;m awefmkawmjgfakwjgm;aw m;m aw; maw;m awf;m' (length=377)
  public 'titre' => string 'Robert' (length=6)
  public 'title' => string 'Robert' (length=6)
  public 'date_start' => int 1498597200
  public 'date_end' => int 1559422800
  public 'date_close' => string '' (length=0)
  public 'socid' => string '17' (length=2)
  public 'thirdparty_name' => null
  public 'user_author_id' => string '2' (length=1)
  public 'user_close_id' => null
  public 'public' => string '1' (length=1)
  public 'budget_amount' => string '21313.00000000' (length=14)
  public 'statuts_short' => 
    array (size=3)
      0 => string 'Draft' (length=5)
      1 => string 'Opened' (length=6)
      2 => string 'Closed' (length=6)
  public 'statuts_long' => 
    array (size=3)
      0 => string 'Draft' (length=5)
      1 => string 'Opened' (length=6)
      2 => string 'Closed' (length=6)
  public 'statut' => string '1' (length=1)
  public 'opp_status' => string '1' (length=1)
  public 'opp_percent' => string '421.00' (length=6)
  public 'oldcopy' => null
  public 'weekWorkLoad' => null
  public 'weekWorkLoadPerTask' => null
  public 'datec' => int 1498597200
  public 'date_c' => int 1498597200
  public 'datem' => int 1501748388
  public 'date_m' => int 1501748388
  public 'lines' => null

  public 'id' => string '1' (length=1)
  public 'error' => null
  public 'errors' => 
    array (size=0)
      empty
  public 'import_key' => null
  public 'array_options' => 
    array (size=0)
      empty
  public 'linkedObjectsIds' => null
  public 'linkedObjects' => null
  public 'context' => 
    array (size=0)
      empty
  public 'canvas' => null
  public 'project' => null
  public 'fk_project' => null
  public 'projet' => null
  public 'contact' => null
  public 'contact_id' => null
  public 'thirdparty' => 
    object(Societe)[132]
      public 'element' => string 'societe' (length=7)
      public 'table_element' => string 'societe' (length=7)
      public 'fk_element' => string 'fk_soc' (length=6)
      protected 'childtables' => 
        array (size=9)
          0 => string 'supplier_proposal' (length=17)
          1 => string 'propal' (length=6)
          2 => string 'commande' (length=8)
          3 => string 'facture' (length=7)
          4 => string 'contrat' (length=7)
          5 => string 'facture_fourn' (length=13)
          6 => string 'commande_fournisseur' (length=20)
          7 => string 'projet' (length=6)
          8 => string 'expedition' (length=10)
      protected 'ismultientitymanaged' => int 1
      public 'entity' => string '1' (length=1)
      public 'nom' => string 'AUER' (length=4)
      public 'name_alias' => string 'AUER Packaging France' (length=21)
      public 'particulier' => null
      public 'address' => string '18 Rue Pasquier' (length=15)
      public 'zip' => string '74008' (length=5)
      public 'town' => string 'PARIS' (length=5)
      public 'status' => string '1' (length=1)
      public 'state_id' => string '82' (length=2)
      public 'state_code' => string '75' (length=2)
      public 'state' => string 'Paris' (length=5)
      public 'departement_code' => null
      public 'departement' => null
      public 'pays' => null
      public 'phone' => string '0800911666' (length=10)
      public 'fax' => string '0800911664' (length=10)
      public 'email' => string 'info@auer-packaging.fr' (length=22)
      public 'skype' => null
      public 'url' => string 'www.auer-packaging.fr' (length=21)
      public 'barcode' => null
      public 'idprof1' => string '' (length=0)
      public 'idprof2' => string '' (length=0)
      public 'idprof3' => string '' (length=0)
      public 'idprof4' => string '' (length=0)
      public 'idprof5' => string '' (length=0)
      public 'idprof6' => string '' (length=0)
      public 'prefix_comm' => null
      public 'tva_assuj' => string '1' (length=1)
      public 'tva_intra' => string '' (length=0)
      public 'localtax1_assuj' => null
      public 'localtax1_value' => string '0.000' (length=5)
      public 'localtax2_assuj' => null
      public 'localtax2_value' => string '0.000' (length=5)
      public 'managers' => null
      public 'capital' => null
      public 'typent_id' => string '0' (length=1)
      public 'typent_code' => string 'TE_UNKNOWN' (length=10)
      public 'effectif' => string '' (length=0)
      public 'effectif_id' => null
      public 'forme_juridique_code' => null
      public 'forme_juridique' => string '' (length=0)
      public 'remise_percent' => string '0' (length=1)
      public 'mode_reglement_supplier_id' => null
      public 'cond_reglement_supplier_id' => null
      public 'fk_prospectlevel' => string '' (length=0)
      public 'name_bis' => null
      public 'date_modification' => int 1486032464
      public 'user_modification' => null
      public 'date_creation' => int 1486028864
      public 'user_creation' => null
      public 'specimen' => null
      public 'client' => string '0' (length=1)
      public 'prospect' => int 0
      public 'fournisseur' => string '1' (length=1)
      public 'code_client' => null
      public 'code_fournisseur' => string 'SU1702-0015' (length=11)
      public 'code_compta' => null
      public 'code_compta_fournisseur' => null
      public 'note' => null
      public 'note_private' => null
      public 'note_public' => null
      public 'stcomm_id' => string '0' (length=1)
      public 'statut_commercial' => string 'Never contacted' (length=15)
      public 'price_level' => null
      public 'outstanding_limit' => null
      public 'commercial_id' => null
      public 'parent' => null
      public 'default_lang' => null
      public 'ref' => string '17' (length=2)
      public 'ref_int' => null
      public 'ref_ext' => null
      public 'import_key' => null
      public 'webservices_url' => null
      public 'webservices_key' => null
      public 'logo' => null
      public 'logo_small' => null
      public 'logo_mini' => null
      public 'array_options' => null
      public 'fk_incoterms' => string '0' (length=1)
      public 'location_incoterms' => null
      public 'libelle_incoterms' => null
      public 'fk_multicurrency' => string '0' (length=1)
      public 'multicurrency_code' => string '' (length=0)
      public 'oldcopy' => null

      public 'id' => string '17' (length=2)
      public 'error' => null
      public 'errors' => 
        array (size=0)
          empty
      public 'linkedObjectsIds' => null
      public 'linkedObjects' => null
      protected 'table_ref_field' => string '' (length=0)
      public 'context' => 
        array (size=0)
          empty
      public 'canvas' => null
      public 'project' => null
      public 'fk_project' => null
      public 'projet' => null
      public 'contact' => null
      public 'contact_id' => null
      public 'thirdparty' => null
      public 'user' => null
      public 'origin' => null
      public 'origin_id' => null
      public 'ref_previous' => null
      public 'ref_next' => null
      public 'table_element_line' => null
      public 'statut' => null
      public 'country' => string 'France' (length=6)
      public 'country_id' => string '1' (length=1)
      public 'country_code' => string 'FR' (length=2)
      public 'barcode_type' => null
      public 'barcode_type_code' => null
      public 'barcode_type_label' => null
      public 'barcode_type_coder' => null
      public 'mode_reglement_id' => null
      public 'cond_reglement_id' => null
      public 'cond_reglement' => null
      public 'fk_delivery_address' => null
      public 'shipping_method_id' => null
      public 'modelpdf' => null
      public 'fk_account' => null
      public 'total_ht' => null
      public 'total_tva' => null
      public 'total_localtax1' => null
      public 'total_localtax2' => null
      public 'total_ttc' => null
      public 'lines' => null
      public 'name' => string 'AUER' (length=4)
      public 'lastname' => null
      public 'firstname' => null
      public 'civility_id' => null
  public 'user' => null
  public 'origin' => null
  public 'origin_id' => null
  public 'ref' => string 'PJ1706-0004' (length=11)
  public 'ref_previous' => null
  public 'ref_next' => null
  public 'ref_ext' => null
  public 'country' => null
  public 'country_id' => null
  public 'country_code' => null
  public 'barcode_type' => null
  public 'barcode_type_code' => null
  public 'barcode_type_label' => null
  public 'barcode_type_coder' => null
  public 'mode_reglement_id' => null
  public 'cond_reglement_id' => null
  public 'cond_reglement' => null
  public 'fk_delivery_address' => null
  public 'shipping_method_id' => null
  public 'modelpdf' => string 'beluga' (length=6)
  public 'fk_account' => null
  public 'note_public' => null
  public 'note_private' => null
  public 'note' => null
  public 'total_ht' => null
  public 'total_tva' => null
  public 'total_localtax1' => null
  public 'total_localtax2' => null
  public 'total_ttc' => null
  public 'fk_incoterms' => null
  public 'libelle_incoterms' => null
  public 'location_incoterms' => null
  public 'name' => null
  public 'lastname' => null
  public 'firstname' => null
  public 'civility_id' => null
  public 'opp_amount' => string '231.00000000' (length=12)


















OBJECT TASKS PROJET

*******************************************************************************************************************************************************************

array (size=12)
  0 => 
    object(TaskABCVC)[141]
      public 'element' => string 'projectabcvc_task' (length=17)
      public 'table_element' => string 'abcvc_projet_task' (length=17)
      public 'fk_element' => string 'fk_task' (length=7)
      public 'picto' => string 'task' (length=4)
      protected 'childtables' => 
        array (size=1)
          0 => string 'abcvc_projet_task_time' (length=22)
      public 'fk_task_parent' => string '0' (length=1)
      public 'label' => string 'Poste' (length=5)
      public 'description' => string 'fwa faw awf' (length=11)
      public 'duration_effective' => null
      public 'planned_workload' => string '120780' (length=6)
      public 'date_c' => null
      public 'date_start' => int 1501662720
      public 'date_end' => int 1501662720
      public 'progress' => string '40' (length=2)
      public 'fk_statut' => string '0' (length=1)
      public 'priority' => null
      public 'fk_user_creat' => null
      public 'fk_user_valid' => null
      public 'rang' => string '0' (length=1)
      public 'timespent_min_date' => null
      public 'timespent_max_date' => null
      public 'timespent_total_duration' => null
      public 'timespent_total_amount' => null
      public 'timespent_nblinesnull' => null
      public 'timespent_nblines' => null
      public 'timespent_id' => null
      public 'timespent_duration' => null
      public 'timespent_old_duration' => null
      public 'timespent_date' => null
      public 'timespent_datehour' => null
      public 'timespent_withhour' => null
      public 'timespent_fk_user' => null
      public 'timespent_note' => null
      public 'oldcopy' => null
      public 'id' => string '166' (length=3)
      public 'error' => null
      public 'errors' => 
        array (size=0)
          empty
      public 'import_key' => null
      public 'array_options' => 
        array (size=0)
          empty
      public 'linkedObjectsIds' => null
      public 'linkedObjects' => null
      protected 'table_ref_field' => string '' (length=0)
      public 'context' => 
        array (size=0)
          empty
      public 'canvas' => null
      public 'project' => null
      public 'fk_project' => string '1' (length=1)
      public 'projet' => null
      public 'contact' => null
      public 'contact_id' => null
      public 'thirdparty' => null
      public 'user' => null
      public 'origin' => null
      public 'origin_id' => null
      public 'ref' => string '1.1.1' (length=5)
      public 'ref_previous' => null
      public 'ref_next' => null
      public 'ref_ext' => null
      public 'table_element_line' => null
      public 'statut' => null
      public 'country' => null
      public 'country_id' => null
      public 'country_code' => null
      public 'barcode_type' => null
      public 'barcode_type_code' => null
      public 'barcode_type_label' => null
      public 'barcode_type_coder' => null
      public 'mode_reglement_id' => null
      public 'cond_reglement_id' => null
      public 'cond_reglement' => null
      public 'fk_delivery_address' => null
      public 'shipping_method_id' => null
      public 'modelpdf' => null
      public 'fk_account' => null
      public 'note_public' => null
      public 'note_private' => null
      public 'note' => null
      public 'total_ht' => null
      public 'total_tva' => null
      public 'total_localtax1' => null
      public 'total_localtax2' => null
      public 'total_ttc' => null
      public 'lines' => null
      public 'fk_incoterms' => null
      public 'libelle_incoterms' => null
      public 'location_incoterms' => null
      public 'name' => null
      public 'lastname' => null
      public 'firstname' => null
      public 'civility_id' => null
      public 'projectref' => string 'PJ1706-0004' (length=11)
      public 'projectlabel' => string 'Robert' (length=6)
      public 'projectstatus' => string '1' (length=1)
      public 'fk_parent' => string '0' (length=1)
      public 'duration' => string '0' (length=1)
      public 'public' => string '1' (length=1)
      public 'thirdparty_id' => string '17' (length=2)
      public 'thirdparty_name' => string 'AUER' (length=4)







*/