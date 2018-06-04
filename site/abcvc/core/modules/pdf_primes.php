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
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/modules/project/modules_project.php';
// require_once DOL_DOCUMENT_ROOT.SUPP_PATH.'/js/abcvc.js';

// if (! empty($conf->propal->enabled))      require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
// if (! empty($conf->facture->enabled))     require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
// if (! empty($conf->facture->enabled))     require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture-rec.class.php';
// if (! empty($conf->commande->enabled))    require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
// if (! empty($conf->fournisseur->enabled)) require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
// if (! empty($conf->fournisseur->enabled)) require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
// if (! empty($conf->contrat->enabled))     require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
// if (! empty($conf->ficheinter->enabled))  require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
// if (! empty($conf->deplacement->enabled)) require_once DOL_DOCUMENT_ROOT.'/compta/deplacement/class/deplacement.class.php';
// if (! empty($conf->agenda->enabled))      require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';


/**
 *	Classe permettant de generer les projets au modele Baleine
 */

class pdf_primes extends ModelePDFProjects
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
        // var_dump("HELLO");
        // exit();
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


    // FUNCTION TO TRANSFORM SECONDS IN hh:mm:ss
    function format_time($t,$f=':') {
        return sprintf("%02d%s%02d%s%02d", floor($t/3600), $f, ($t/60)%60, $f, $t%60);
    }
        

	/**
	 *	Fonction generant le projet sur le disque
	 *
	 *	@param	Project		$object   		Object project a generer
	 *	@param	Translate	$outputlangs	Lang output object
	 *	@return	int         				1 if OK, <=0 if KO
	 */
    //write_file($getalltime,$langs,$perioduser,$from,$to,$prix_panier);
	function write_file($outputlangs,$perioduser,$from,$to,$prix_panier)
	{

        // var_dump($getalltime,$perioduser,$from,$to,$prix_panier);
        // exit();
        // var_dump($select_month);
        // exit();
    	global $user,$langs,$conf;
        // Load object if id or ref is provided as parameter
        $objproj = new ProjectABCVC($this->db);
        $objtask = new TaskABCVC($this->db);
        // FUNCTION TO TRANSFORM SECONDS IN hh:mm:ss
        // function format_time($t,$f=':') {
        //     return sprintf("%02d%s%02d%s%02d", floor($t/3600), $f, ($t/60)%60, $f, $t%60);
        // }
        //echo format_time(685); // 00:11:25
        // var_dump($timetree,$outputlangs,$getcontact,$stt_from,$stt_to);
        // exit();
        // echo $this->format_time(3600,":");

        $pdf=pdf_getInstance($this->format);
        $default_font_size = pdf_getPDFFontSize($outputlangs);	// Must be after pdf_getInstance
        $heightforinfotot = 50;	// Height reserved to output the info and total part
        $heightforfreetext= (isset($conf->global->MAIN_PDF_FREETEXT_HEIGHT)?$conf->global->MAIN_PDF_FREETEXT_HEIGHT:5);	// Height reserved to output the free text on last page
        $heightforfooter = $this->marge_basse + 8;	// Height reserved to output the footer (value include bottom margin)
        $pdf->SetAutoPageBreak(true,0);

        if (class_exists('TCPDF'))
        {
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
        }
        $pdf->SetFont(pdf_getPDFFont($outputlangs));

    	$pdf->Open();
    	$pagenb=0;
    	$pdf->SetDrawColor(128,128,128);

    	$pdf->SetTitle($outputlangs->convToOutputCharset("Test"));
    	$pdf->SetSubject($outputlangs->transnoentities("Project"));
    	$pdf->SetCreator("Dolibarr ".DOL_VERSION);
    	$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
    	$pdf->SetKeyWords($outputlangs->convToOutputCharset("test")." ".$outputlangs->transnoentities("Project"));
    	if (! empty($conf->global->MAIN_DISABLE_PDF_COMPRESSION)) $pdf->SetCompression(false);

    	$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);   // Left, Top, Right

    	// New page
    	$pdf->AddPage('L', 'A4');
    	$pagenb++;
    	//$this->_pagehead($pdf, $object, 1, $outputlangs);
    	$pdf->SetFont('','', $default_font_size - 1);
    	$pdf->MultiCell(0, 3, '');		// Set interline to 3
    	$pdf->SetTextColor(0,0,0);

    	$tab_top = 50;
    	$tab_height = 200;
    	$tab_top_newpage = 40;
        $tab_height_newpage = 210;

    	// Affiche notes
    		$height_note=0;

    	$iniY = $tab_top + 7;
    	$curY = $tab_top + 7;
    	$nexY = $tab_top + 7;

        /* *******************************************************************************************************************************************************************

                INFORMATION GENERATE TO PDF FILE 

        ********************************************************************************************************************************************************************/
        //-----------------------------------------------------------------------
        //-----------------------------------------------------------------------
        //
        //  calculs calendrier dates...
        //
        //-----------------------------------------------------------------------
        //-----------------------------------------------------------------------
        //windows @#!!
        setlocale(LC_ALL, 'french');
        //linussee
        //setlocale(LC_ALL, 'fr_FR');
        /*
        *
        *
        *   TRANSFORM DATES IN STRTOTIME AND WHEN WE INPUT A DATE GENERATE TABLE BY MONTH 
        *
        */
        $ok_calendrier = true;
        if($from != "") // IF "FROM" IS EMPTY SHOW CURENT DATE
        { 
            //CONVERT FRANCE DATE TO SQL DATE
            $tmp_date0 = explode(' ',$from);
            $tmp_date = explode('/',$tmp_date0[0]);
            $new_from = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0];
            $stt_from = strtotime($new_from);
        } 
        else
        {
            $stt_from = time();
            //var_dump("from",$stt_from);
        } 
        if($to != "") // IF "TO" IS EMPTY SHOW CURENT DATE
        { 
            //CONVERT FRANCE DATE TO SQL DATE   
            $tmp_date0 = explode(' ',$to);
            $tmp_date = explode('/',$tmp_date0[0]);
            $new_to = $tmp_date[2].'-'.$tmp_date[1].'-'.$tmp_date[0];
            //var_dump($new_to);
            $stt_to = strtotime($new_to);
        } 
        else
        {
            $ok_calendrier = false;
        }
        //here we extract TIME TREE getTimeTree($userid,$from,$to)
        $getalltime = $objtask->getTimeTree($perioduser,$new_from,$new_to);
        // var_dump($getalltime);
        // exit;
        $getcontact = $objtask->getContact($perioduser);
        /*
            var_dump($getcontact);
            array (size=1)
              0 => 
                object(stdClass)[138]
                  public 'rowid' => string '2' (length=1)
                  public 'lastname' => string 'Wandebrouck' (length=11)
                  public 'firstname' => string 'Eric' (length=4)
        */      

        //-----------------------------------------------------------------------
        //-----------------------------------------------------------------------
        //
        //  calculs calendrier dates...
        //
        //-----------------------------------------------------------------------
        //-----------------------------------------------------------------------

        //windows @#!!
        setlocale(LC_ALL, 'french');
        //linussee
        //setlocale(LC_ALL, 'fr_FR');
        
        //SELECT ALL MONTHS BETWEEN "$FROM" AND "$TO"
            $start = $month = $stt_from;
            $end = $stt_to;
            $select_month = array(); // ALL THE MONTHS SELECTED
            if($stt_to == $stt_from) {
                $select_month[ date('n',$month) ] = utf8_encode( ucfirst(strftime('%B', $month)) ); //date("F", $month);
            } else {        
                while($month <= $end) { 
                    // echo date('F', $month), PHP_EOL;
                    
                    $select_month[date('n',$month)] = utf8_encode( ucfirst(strftime('%B', $month)) ); //    date('F', $month);
                    $month = strtotime("+1 month", $month);
                }
            }
            //var_dump("CE TREBUIE AFISAT",$select_month);
            $year_selected = date('Y', $stt_from);
            $nbWeekYear = date('W', mktime(0,0,0,12,28,$year_selected) ); 
            //OK real nb
            //var_dump($nbWeekYear);
            //var_dump($stt_from,$stt_to);
            //var_dump($year_selected);
            //var_dump($select_month);
            function year2array($year) {
                $res = $year >= 1970;
                if ($res) {
                  // this line gets and sets same timezone, don't ask why :)
                  date_default_timezone_set(date_default_timezone_get());

                  $dt = strtotime("-1 day", strtotime("$year-01-01 00:00:00"));
                  $res = array();
                  $week = array_fill(1, 7, false);
                  $last_month = 1;
                  $w = 1;
                  do {
                    $dt = strtotime('+1 day', $dt);
                    $dta = getdate($dt);
                    $wday = $dta['wday'] == 0 ? 7 : $dta['wday'];
                    if (($dta['mon'] != $last_month) || ($wday == 1)) {
                      if ($week[1] || $week[7]) $res[$last_month][] = $week;
                      $week = array_fill(1, 7, false);
                      $last_month = $dta['mon'];
                      }
                    $week[$wday] = $dta['mday'];
                    }
                  while ($dta['year'] == $year);
                  }
                return $res;
            }
            function month2table($month, $calendar_array) {
                $ca = 'align="center"';
                $res = "<table cellpadding=\"2\" cellspacing=\"1\" style=\"border:solid 1px #000000;font-family:tahoma;font-size:12px;background-color:#ababab\"><tr><td $ca>Mo</td><td $ca>Tu</td><td $ca>We</td><td $ca>Th</td><td $ca>Fr</td><td $ca>Sa</td><td $ca>Su</td></tr>";
                foreach ($calendar_array[$month] as $month=>$week) {
                  $res .= '<tr>';
                  foreach ($week as $day) {
                    $res .= '<td align="right" width="20" bgcolor="#ffffff">' . ($day ? $day : '&nbsp;') . '</td>';
                    }
                  $res .= '</tr>';
                  }
                $res .= '</table>';
                return $res;
            }
            $year_array = year2array($year_selected);
            //OK temp tb compact
            //echo month2table(10, $year_array); // January
            // echo month2table(2, $year_array); // February
            // echo month2table(12, $year_array); // December
            //var_dump($year_array[1]);
        ob_start();
        ?>



        <style>
            .table{
                font-size: 8px;
            }
            .table .td_day{
                font-size: 6px;
            }
            .show_hours{
                font-size: 5px;
            }
            .show_projects{
                font-size: 5px;
            }
            .show_icons{
                font-size: 5px;
            }
            .show_postes{
                font-size: 5px;
            }
        </style>
            <?php 

            //total periode
            //---------------------------
            $nbpanierPeriode = 0;
            $nbtrajetPeriode = 0;
            $nbtrajetGDPeriode = 0;
            $sumWorkPeriode = 0;
            $sumTravailPeriode = 0;
            $sumSAVPeriode = 0;
            $sumCongesPeriode = 0;
            $sumFeriePeriode = 0;
            $sumMaladiePeriode = 0;
            $sumRecupPeriode = 0;
            $sumEcolePeriode = 0;
            $trajets_periode = array();
            $trajetsGD_periode = array();
            //boucle mois
            //---------------------------
            /***************************************************************************************************************************************
            *
            *
            * Calculate values to make PDF_HEADER
            * 
            * 
            ****************************************************************************************************************************************/
                for ($month=1; $month <=12 ; $month++) : 
                    $nbpanierMonth = 0;
                    $nbtrajetMonth = 0;
                    $nbtrajetGDMonth = 0;
                    $sumWorkMonth = 0;
                    $sumTravailMonth = 0;
                    $sumSAVMonth = 0;
                    $sumCongesMonth = 0;
                    $sumFerieMonth = 0;
                    $sumMaladieMonth = 0;
                    $sumRecupMonth = 0;
                    $sumEcoleMonth = 0;
                    $trajets_month = array();
                    $trajetsGD_month = array();
                    $month_potential_works_hours = 0;
                    $sumSupMonth = 0;
                    //FALSE GO TO NEXT MONTH
                    if(!array_key_exists($month,$select_month) ){
                        continue;
                    }
                    // var_dump("Inceput luna");
                    ?>
                    <?php
                    foreach ($year_array[$month] as $idx_week=>$week) : 
                        $nbpanierWeek = 0;
                        $nbtrajetWeek = 0;
                        $nbtrajetGDWeek = 0;
                        $ar_trajetGDWeek = array();
                        $sumWorkWeek = 0;
                        $sumTravailWeek = 0;
                        $sumSAVWeek = 0;
                        $sumCongesWeek = 0;
                        $sumFerieWeek = 0;
                        $sumMaladieWeek = 0;
                        $sumRecupWeek = 0;
                        $sumEcoleWeek = 0;
                        $sumSupWeek = 0;    
                        $week_potential_works_hours = 0;
                        foreach ($week as $idx_day=>$day) { 
                            $ar_trajetGDWeek[$idx_day] = 0;
                            $timespentday = $getalltime[$month][$day][0];
                            if($timespentday->gd==1){
                                $ar_trajetGDWeek[$idx_day] = 1;
                            }
                        }                                               
                        foreach ($ar_trajetGDWeek as $idx_day => $trajetGD) {
                            if($idx_day>1){
                                if( ($trajetGD==0) ){
                                    if( $ar_trajetGDWeek[$idx_day-1] == 1 ){
                                        $ar_trajetGDWeek[$idx_day-1] = 0;
                                    }
                                }
                            }
                        }
                        foreach ($week as $idx_day=>$day) : ?>
                        <?php 
                            $sumWorkDay = 0;
                            $daydate = mktime(0,0,0,$month,$day,$year_selected);
                            if(!$day){
                             
                            }
                            else
                            {
                                if(date('N',$daydate) < 6) {
                                    $week_potential_works_hours +=7; 
                                }?>
                                <?php if(isset($getalltime[$month][$day])) : ?>
                                <?php
                                // HEURES saisies + calcul panier/trajet,etc
                                // -------------------------------------------------------------
                                //TODO CAS MULTIPLE POSTE par jour...
                                
                                foreach ($getalltime[$month][$day] as $timespentday) {
                                    if( $timespentday->task_type == 0 ) $sumTravailWeek += ($timespentday->task_duration);  //Travail 
                                    if( $timespentday->task_type == 6 ) $sumSAVWeek += ($timespentday->task_duration);      //MES / SAV 
                                    if( $timespentday->task_type == 5 ) $sumEcoleWeek += ($timespentday->task_duration);    //Ecole                                             
                                    if( $timespentday->task_type == 1 ) $sumCongesWeek += ($timespentday->task_duration);   //Conges
                                    if( $timespentday->task_type == 2 ) $sumFerieWeek += ($timespentday->task_duration);    //Ferie
                                    if( $timespentday->task_type == 3 ) $sumMaladieWeek += ($timespentday->task_duration);  //Maladie
                                    if( $timespentday->task_type == 4 ) $sumRecupWeek += ($timespentday->task_duration);    //Recup
                                    //if( $timespentday->task_type == 0 )
                                    $sumWorkWeek += ($timespentday->task_duration);
                                    $sumWorkDay += ($timespentday->task_duration);
                                    }
                                    if($sumWorkDay>25200){
                                        $sumSupDay = $sumWorkDay - 25200;                                                               
                                        $sumWorkDay = 25200;
                                    } 
                                    else 
                                    {
                                        $sumSupDay = 0;
                                    }
                                    $sumSupWeek += $sumSupDay;  
                                    //Ecole : heures à exclure des calculs de panier et trajet
                                    if( ($timespentday->task_type == 0) || ($timespentday->task_type == 6) ): ?>
                                        <?php if ($ar_trajetGDWeek[$idx_day]==1) :
                                            $nbtrajetGDWeek++;
                                            $trajetsGD_month[$timespentday->zone]+=$timespentday->zone_price;
                                        ?>
                                        <?php elseif ($timespentday->gd==0):
                                            $nbtrajetWeek++;
                                            $trajets_month[$timespentday->zone]+=$timespentday->zone_price;                                                         
                                        ?>
                                        <?php endif;?>
                                        <?php if( $timespentday->task_duration >= 18000 ) : 
                                            //panier > 5h  / 18000sec
                                            $nbpanierWeek++;
                                        ?>
                                        <?php endif; ?>
                                    <?php endif; ?>                                     
                                <?php endif; ?>                         
                            <?php } ?>                         
                        <?php endforeach; ?>
                        <?php
                            $nbpanierMonth += $nbpanierWeek; 
                            //Nbr heures ("travail"+ "feries"+"maladie")
                            $sumWorkMonth += $sumTravailWeek + $sumSAVWeek + $sumEcoleWeek + $sumMaladieWeek + $sumFerieWeek; //$sumWorkWeek;
                            $sumTravailMonth += $sumTravailWeek;
                            $sumSAVMonth += $sumSAVWeek; 
                            $sumEcoleMonth += $sumEcoleWeek;
                            $sumCongesMonth += $sumCongesWeek;
                            $sumFerieMonth += $sumFerieWeek;
                            $sumMaladieMonth += $sumMaladieWeek;
                            $sumRecupMonth += $sumRecupWeek;
                            $nbtrajetMonth += $nbtrajetWeek;
                            $nbtrajetGDMonth += $nbtrajetGDWeek;
                            $sumSupMonth += $sumSupWeek;
                            $month_potential_works_hours += $week_potential_works_hours;
                            // var_dump($week_potential_works_hours);
                            $week_potential_works_hours = 0; ?>
                    <?php endforeach; ?> 
                    <?php
                    //var_dump($trajets_month);
                    //var_dump($trajetsGD_month);
                    $ok_labelcost_trajets_month = '';
                    if(count($trajets_month)>0){
                        $ok_labelcost_trajets_month = ' ( ';
                        $labelcost_trajets_month = array();
                        foreach ($trajets_month as $label => $cost) {
                            $labelcost_trajets_month[] = $label.': <b>'.price($cost).' €</b>';
                        }
                        $ok_labelcost_trajets_month .= implode(',  ',$labelcost_trajets_month);
                        $ok_labelcost_trajets_month .= ' )';
                    }
                    $ok_labelcost_trajetsGD_month = '';
                    if(count($trajetsGD_month)>0){
                        $ok_labelcost_trajetsGD_month = ' ( ';
                        $labelcost_trajetsGD_month = array();
                        foreach ($trajetsGD_month as $label => $cost) {
                            $labelcost_trajetsGD_month[] = $label.': <b>'.price($cost).' €</b>';
                        }
                        $ok_labelcost_trajetsGD_month .= implode(',  ',$labelcost_trajetsGD_month);
                        $ok_labelcost_trajetsGD_month .= ' )';
                    }
                    ?>
                                    <?php 
                                    $month_potential_works_secondes = $month_potential_works_hours*60*60;
                                    if($sumWorkMonth>$month_potential_works_secondes){
                                        $heuresDues_Month = 0;
                                    } else {
                                        $heuresDues_Month = $month_potential_works_secondes-$sumWorkMonth;
                                    }
                                    $matrice_work_Month[$month] = array(
                                        'potential_works'=>$month_potential_works_hours*60*60,
                                        'sumWorkMonth'=>$sumWorkMonth,

                                        'heuresSup_Month'=>$sumSupMonth,
                                        'heuresDues_Month'=>$heuresDues_Month
                                    );
                                    ?>
                    <?php 
                        $sumWorkPeriode += $sumWorkMonth;
                        $sumTravailPeriode += $sumTravailMonth;
                        $sumSAVPeriode  += $sumSAVMonth;
                        $sumCongesPeriode += $sumCongesMonth;
                        $sumFeriePeriode += $sumFerieMonth;
                        $sumMaladiePeriode += $sumMaladieMonth;
                        $sumRecupPeriode += $sumRecupMonth;
                        $sumEcolePeriode += $sumEcoleMonth; 
                        $nbpanierPeriode += $nbpanierMonth;
                        $nbtrajetPeriode += $nbtrajetMonth;
                        $nbtrajetGDPeriode += $nbtrajetGDMonth;                         
                        foreach ($trajets_month as  $label => $cost) {
                            $trajets_periode[$label]+=$cost;
                        }
                        foreach ($trajetsGD_month as $label => $cost) {
                            $trajetsGD_periode[$label]+=$cost;
                        }
                    ?>
                    <?php
                endfor;
                // var_dump($matrice_work_Month);
                // exit();
                //-----------------------------------------------------------------------
                //-----------------------------------------------------------------------
                //var_dump($trajets_periode);
                //var_dump($trajetsGD_periode);
                $ok_labelcost_trajets_periode = '';
                if(count($trajets_periode)>0){
                    $ok_labelcost_trajets_periode = ' ( ';
                    $labelcost_trajets_periode = array();
                    foreach ($trajets_periode as $label => $cost) {
                        $labelcost_trajets_periode[] = $label.': <b>'.price($cost).' €</b>';
                    }
                    $ok_labelcost_trajets_periode .= implode(',  ',$labelcost_trajets_periode);
                    $ok_labelcost_trajets_periode .= ' )';
                }
                
                $ok_labelcost_trajetsGD_periode = '';
                if(count($trajetsGD_periode)>0){
                    $ok_labelcost_trajetsGD_periode = ' ( ';
                    $labelcost_trajetsGD_periode = array();
                    foreach ($trajetsGD_periode as $label => $cost) {
                        $labelcost_trajetsGD_periode[] = $label.': <b>'.price($cost).' €</b>';
                    }
                    $ok_labelcost_trajetsGD_periode .= implode(',  ',$labelcost_trajetsGD_periode);
                    $ok_labelcost_trajetsGD_periode .= ' )';
                }



                // exit;
            /***************************************************************************************************************************************
            *
            *
            * FINISH HEADER
            * 
            * 
            ****************************************************************************************************************************************/
            ?>
            <table width="100%" class="table table-bordxered" id="header_presences">
                <tbody>
                    <tr>
                        <td colspan="2" bgcolor="" align="center" >
                            <?php foreach ($getcontact as $contact ) {
                                echo $contact->firstname."&nbsp;".$contact->lastname;
                            }
                            //pc.email, pc.thm, pc.salary, pc.weeklyhours  
                            ?> - <b><?php echo $contact->job;?></b> -
                            <img src="<?php echo DOL_DOCUMENT_ROOT;?>/abcvc/img/primes-phone.png" width="7" height="7"><?php echo $contact->user_mobile;?> - 
                            <img src="<?php echo DOL_DOCUMENT_ROOT;?>/abcvc/img/primes-mail.png" width="8" height="6">&nbsp;<?php echo $contact->email;?> - 
                            [ Salaire: <?php echo price($contact->salary);?>€, salaire horaire moyen: <?php echo price($contact->thm);?>€, Heures de travail hebdomadaires: <?php echo (float)($contact->weeklyhours);?> ]</td>
                    </tr>
                    <tr> 
                        <td width="83%" bgcolor="#cce6ff">   Periode du <b><?php echo date('d/m/Y',$stt_from)."</b> au <b>".date('d/m/Y',$stt_to); ?></b><br>
                            <table width="100%" class="table small" cellspacing="3" cellpadding="3" style="background-color:#F8F8FF;">
                                <tbody>
                                    <tr>
                                        <td>Travail: <?php echo format_time($sumWorkPeriode); ?> </td>
                                        <td align="center"><div class="" style="color:#fff; padding:2px; background-color:#5cb85c;"><?php echo format_time($sumTravailPeriode); ?></div></td>
                                        <td align="center"><div class="" style="color:#fff; padding:2px; background-color:#185a18;"><?php echo format_time($sumSAVPeriode); ?></div></td>
                                        <td align="center"><div class="" style="color:#fff; padding:2px; background-color:#00ffbf"><?php echo format_time($sumEcolePeriode); ?></div></td>                              
                                        <td align="center"><div class="" style="color:#fff; padding:2px; background-color:#00ff80;"><?php echo format_time($sumFeriePeriode); ?></div></td>
                                        <td align="center"><div class="" style="color:#fff; padding:2px; background-color:#8000ff"><?php echo format_time($sumMaladiePeriode); ?></div></td>                                
                                        <td align="center"><div class="" style="color:#fff; padding:2px; background-color:#ffbf00;"><?php echo format_time($sumCongesPeriode); ?></div></td>
                                        <td align="center"><div class="" style="color:#fff; padding:2px; background-color:#ff0000"><?php echo format_time($sumRecupPeriode);  ?></div></td>
                                    </tr>
                                    <tr>
                                        <td width="100%" colspan="8"><img src="<?php echo DOL_DOCUMENT_ROOT;?>/abcvc/img/primes-panier.png" width="7" height="7">  Total paniers: <?php echo $nbpanierPeriode; ?> ( <b><?php echo price($nbpanierPeriode*$prix_panier);?> €</b> )
                                            &nbsp;&nbsp;&nbsp;&nbsp;
                                            <img src="<?php echo DOL_DOCUMENT_ROOT;?>/abcvc/img/primes-road.png" width="7" height="6">  Total trajets: <?php echo $nbtrajetPeriode; ?> <?php echo $ok_labelcost_trajets_periode; ?> 
                                            &nbsp;&nbsp;&nbsp;&nbsp;
                                            <img src="<?php echo DOL_DOCUMENT_ROOT;?>/abcvc/img/primes-night.png" width="7" height="7">  Total trajets GD: <?php echo $nbtrajetGDPeriode; ?> <?php echo $ok_labelcost_trajetsGD_periode; ?> 
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td width="17%" bgcolor="#cce6ff">
                            <?php 
                            //var_dump($matrice_work_Month); 
                            /*RTT [x]<br />
                            ABCVC doit / Collab doit : 
                            Nbr heures ("travail"+ "feries"+"maladie") - ("conges" + "recup")  annualisés (35h/Semaine) - de 51 à 52 semaines par an - 5 semaines de CP 
                            ---------------------------

                            //var_dump($matrice_work_Month); 
                            /*  10 => 
                                array (size=4)
                                  'potential_works' => int 554400
                                  'sumWorkMonth' => int 158400
                                  'heuresSup_Month' => int 7200
                                  'heuresDues_Month' => int 396000*/
                            $matrice_work_Periode = array();
                            foreach ($matrice_work_Month as $work_Month) {
                                $matrice_work_Periode['potential_works'] += $work_Month['potential_works'];
                                $matrice_work_Periode['sumWork'] += $work_Month['sumWorkMonth'];
                                $matrice_work_Periode['heuresSup'] += $work_Month['heuresSup_Month'];
                                $matrice_work_Periode['heuresDues'] += $work_Month['heuresDues_Month'];
                            } 
                            if($matrice_work_Periode['heuresDues']>0){
                                $abcvc_doit = 0;
                                $collaborateur_doit = $matrice_work_Periode['heuresDues'];
                            } else {
                                $abcvc_doit = $matrice_work_Periode['sumWork'] - $matrice_work_Periode['potential_works'];
                                $collaborateur_doit = 0;
                            } 
                            // var_dump($matrice_work_Month);  
                            ?>
                            <table class="table small" cellspacing="5" cellpadding="5">
                                <tbody>
                                    <tr>
                                        <td style="background-color:#F8F8FF;">Total heures: <span class="pull-right"><?php echo format_time($matrice_work_Periode['potential_works']); ?></span> <br />
                                            Travail: <span class="pull-right"><?php echo format_time($matrice_work_Periode['sumWork']); ?></span> <br />
                                            Heures sup.: <span class="pull-right"><?php echo format_time($matrice_work_Periode['heuresSup']); ?></span> <br />
                                            Collaborateur doit: <span class="pull-right"><?php echo format_time($collaborateur_doit); ?></span> <br /> 
                                            ABCVC doit: <span class="pull-right"><?php echo format_time($abcvc_doit); ?></span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>  
            <?php
            // exit;
            for ($month=1; $month <=12 ; $month++) : 
                $nbpanierMonth = 0;
                $nbtrajetMonth = 0;
                $nbtrajetGDMonth = 0;
                $sumWorkMonth = 0;
                $sumTravailMonth = 0;
                $sumSAVMonth = 0;
                $sumCongesMonth = 0;
                $sumFerieMonth = 0;
                $sumMaladieMonth = 0;
                $sumRecupMonth = 0;
                $sumEcoleMonth = 0;
                $trajets_month = array();
                $trajetsGD_month = array();
                $month_potential_works_hours = 0;
                $sumSupMonth = 0;
                //FALSE GO TO NEXT MONTH
                if(!array_key_exists($month,$select_month) ){
                    continue;
                }
                ?>
                <br><br><table class="table table-bordexred">
                    <tbody>
                        <tr>
                            <td bgcolor="#cce6ff" width="100%" align="center" colspan="<?php echo count($year_array[$month]); ?>"><?php echo $select_month[$month].'  '.$year_selected; ?></td>
                        </tr>
                        <tr>
                                <?php
                                foreach ($year_array[$month] as $idx_week=>$week) : 
                                    $nbpanierWeek = 0;
                                    $nbtrajetWeek = 0;
                                    $nbtrajetGDWeek = 0;
                                    $ar_trajetGDWeek = array();
                                    $sumWorkWeek = 0;
                                    $sumTravailWeek = 0;
                                    $sumSAVWeek = 0;
                                    $sumCongesWeek = 0;
                                    $sumFerieWeek = 0;
                                    $sumMaladieWeek = 0;
                                    $sumRecupWeek = 0;
                                    $sumEcoleWeek = 0;
                                    $sumSupWeek = 0;    
                                    $weekWidth = "20%";
                                    if(count($year_array[$month])==5) $weekWidth = "20%"; //18.6%
                                    if(count($year_array[$month])==4) $weekWidth = "25%"; 
                                    if(count($year_array[$month])==6) $weekWidth = "16.67%";
                                    //working normal hours 7h/day of work
                                    $week_potential_works_hours = 0;
                                    ?>
                                    <td width="<?php echo $weekWidth; ?>">
                                        <br><br><table class="table table-borxdered small table-hover" border="0.2">
                                            <tbody>
                                                <?php 
                                                //var_dump($week_potential_works_hours);
                                                //precalcul GD...
                                                //------------------------------------------
                                                foreach ($week as $idx_day=>$day) { 
                                                    $ar_trajetGDWeek[$idx_day] = 0;
                                                    $timespentday = $getalltime[$month][$day][0];
                                                    if($timespentday->gd==1){
                                                        $ar_trajetGDWeek[$idx_day] = 1;
                                                    }
                                                }
                                                //var_dump( $ar_trajetGDWeek );
                                               
                                                foreach ($ar_trajetGDWeek as $idx_day => $trajetGD) {
                                                    if($idx_day>1){
                                                        if( ($trajetGD==0) ){
                                                            if( $ar_trajetGDWeek[$idx_day-1] == 1 ){
                                                                $ar_trajetGDWeek[$idx_day-1] = 0;
                                                            }
                                                        }
                                                    }
                                                }
                                                ?>
                                                <?php foreach ($week as $idx_day=>$day) : 
                                                    $sumWorkDay = 0;
                                                    $daydate = mktime(0,0,0,$month,$day,$year_selected);
                                                ?>
                                                    <?php if(!$day): ?>
                                                        <tr>
                                                            <td bgcolor="#ccc" colspan="2" >&nbsp;</td>
                                                        </tr>   
                                                    <?php else: ?>      
                                                        <tr>
                                                            <td bgcolor="#cce6ff" width="40%" class="td_day" ><?php echo $day;?>     <?php echo ucfirst(strftime('%A',$daydate));?></td>
                                                            <?php 
                                                                //if not WE +7h
                                                                if(date('N',$daydate) < 6) {
                                                                    $week_potential_works_hours +=7; 
                                                                }
                                                            ?>
                                                            <?php if(isset($getalltime[$month][$day])) : 
                                                                // HEURES saisies + calcul panier/trajet,etc
                                                                // -------------------------------------------------------------
                                                                
                                                                //TODO CAS MULTIPLE POSTE par jour...
                                                                foreach ($getalltime[$month][$day] as $timespentday) {
                                                                    if( $timespentday->task_type == 0 ) $sumTravailWeek += ($timespentday->task_duration);  //Travail 
                                                                    if( $timespentday->task_type == 6 ) $sumSAVWeek += ($timespentday->task_duration);      //MES / SAV 
                                                                    if( $timespentday->task_type == 5 ) $sumEcoleWeek += ($timespentday->task_duration);    //Ecole                                             
                                                                    if( $timespentday->task_type == 1 ) $sumCongesWeek += ($timespentday->task_duration);   //Conges
                                                                    if( $timespentday->task_type == 2 ) $sumFerieWeek += ($timespentday->task_duration);    //Ferie
                                                                    if( $timespentday->task_type == 3 ) $sumMaladieWeek += ($timespentday->task_duration);  //Maladie
                                                                    if( $timespentday->task_type == 4 ) $sumRecupWeek += ($timespentday->task_duration);    //Recup
                                                                    //if( $timespentday->task_type == 0 )
                                                                        $sumWorkWeek += ($timespentday->task_duration);
                                                                    $sumWorkDay += ($timespentday->task_duration);
                                                                }                                           
                                                                    //type timespent
                                                                    //----------------------------------------------------------
                                                                    if( $timespentday->task_type == 0 ) $bgcolor="#5cb85c";   //Travail 
                                                                    if( $timespentday->task_type == 6 ) $bgcolor="#185a18";   //MES / SAV 
                                                                    if( $timespentday->task_type == 1 ) $bgcolor="#ffbf00";   //Conges
                                                                    if( $timespentday->task_type == 2 ) $bgcolor="#00ff80";   //Ferie
                                                                    if( $timespentday->task_type == 3 ) $bgcolor="#8000ff";   //Maladie
                                                                    if( $timespentday->task_type == 4 ) $bgcolor="#ff0000";   //Recup
                                                                    if( $timespentday->task_type == 5 ) $bgcolor="#00ffbf";   //Ecole
                                                                    ?>
                                                                    <td style="background-color:<?php echo $bgcolor;?>" width="60%" ><table width="100%">
                                                                                <tr>
                                                                                    <td width="50%" class="show_hours" align="left">
                                                                                    <?php 
                                                                                        //heures - heures sup (7h/jour = 25200sec)
                                                                                        if($sumWorkDay>25200){
                                                                                            $sumSupDay = $sumWorkDay - 25200;                                                               
                                                                                            $sumWorkDay = 25200;
                                                                                        } else {
                                                                                            $sumSupDay = 0;
                                                                                        }
                                                                                        $sumSupWeek += $sumSupDay;  
                                                                                            echo format_time($sumWorkDay,$f=':'); // t = seconds, f = separator 
                                                                                            if($sumSupDay > 0) echo "<span class='small'> (+".format_time($sumSupDay).")</span>";
                                                                                        ?>
                                                                                    </td>
                                                                                    <td width="50%" class="show_icons" align="right">
                                                                                         <?php //Ecole : heures à exclure des calculs de panier et trajet
                                                                                        if( ($timespentday->task_type == 0) || ($timespentday->task_type == 6) ): ?>
                                                                                            <?php 
                                                                                            //trajet
                                                                                            //price($timespentday->zone_price)
                                                                                            $label_trajet = "Trajet (".$timespentday->zone."): <b>".price($timespentday->zone_price)." €</b>";
                                                                                            ?>
                                                                                            <?php
                                                                                             ?>
                                                                                                <?php if ($ar_trajetGDWeek[$idx_day]==1) :
                                                                                                    $nbtrajetGDWeek++;
                                                                                                    $trajetsGD_month[$timespentday->zone]+=$timespentday->zone_price;
                                                                                                ?>
                                                                                                    <img src="<?php echo DOL_DOCUMENT_ROOT;?>/abcvc/img/primes-night.png" width="5" height="4"> 
                                                                                                <?php elseif ($timespentday->gd==0):
                                                                                                    $nbtrajetWeek++;
                                                                                                    $trajets_month[$timespentday->zone]+=$timespentday->zone_price;                                                         
                                                                                                ?>
                                                                                                    <img src="<?php echo DOL_DOCUMENT_ROOT;?>/abcvc/img/primes-road.png" width="5" height="4">
                                                                                                <?php endif;?>
                                                                                            <?php if( $timespentday->task_duration >= 18000 ) : 
                                                                                                //panier > 7h  / 18000sec
                                                                                                $nbpanierWeek++;
                                                                                                $label_panier = "Panier: <b>".price($prix_panier)." €</b>";
                                                                                                ?>
                                                                                                <img src="<?php echo DOL_DOCUMENT_ROOT;?>/abcvc/img/primes-panier.png" width="5" height="5">
                                                                                            <?php endif;  ?>
                                                                                        <?php endif; ?> 
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td width="50%" class="show_projects" align="left" ><?php echo $timespentday->projet_ref;?></td>
                                                                                    <td width="50%" class="show_postes" align="right">Poste: <?php echo $timespentday->task_ref;?></td>
                                                                                </tr>
                                                                        </table>
                                                                    </td>
                                                            <?php else: ?>      
                                                                <td width="60%" align="center"> - </td>
                                                            <?php endif; ?>     
                                                        </tr>
                                                    <?php endif; ?>                         
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table><br>
                                    </td>
                                    <?php
                                    //var_dump($week_potential_works_hours);
                                    ?>
                                <?php
                                    $nbpanierMonth += $nbpanierWeek; 
                                    //Nbr heures ("travail"+ "feries"+"maladie")
                                    $sumWorkMonth += $sumTravailWeek + $sumSAVWeek + $sumEcoleWeek + $sumMaladieWeek + $sumFerieWeek; //$sumWorkWeek;
                                    
                                    $sumTravailMonth += $sumTravailWeek;
                                    $sumSAVMonth += $sumSAVWeek; 
                                    $sumEcoleMonth += $sumEcoleWeek;
                                    $sumCongesMonth += $sumCongesWeek;
                                    $sumFerieMonth += $sumFerieWeek;
                                    $sumMaladieMonth += $sumMaladieWeek;
                                    $sumRecupMonth += $sumRecupWeek;
                                    $nbtrajetMonth += $nbtrajetWeek;
                                    $nbtrajetGDMonth += $nbtrajetGDWeek;
                                    $sumSupMonth += $sumSupWeek;
                                    $month_potential_works_hours += $week_potential_works_hours;
                                    $week_potential_works_hours = 0;
                                endforeach; 
                                //var_dump($trajets_month);
                                //var_dump($trajetsGD_month);
                                $ok_labelcost_trajets_month = '';
                                if(count($trajets_month)>0){
                                    $ok_labelcost_trajets_month = ' ( ';
                                    $labelcost_trajets_month = array();
                                    foreach ($trajets_month as $label => $cost) {
                                        $labelcost_trajets_month[] = $label.': <b>'.price($cost).' €</b>';
                                    }
                                    $ok_labelcost_trajets_month .= implode(',  ',$labelcost_trajets_month);
                                    $ok_labelcost_trajets_month .= ' )';
                                }
                                $ok_labelcost_trajetsGD_month = '';
                                if(count($trajetsGD_month)>0){
                                    $ok_labelcost_trajetsGD_month = ' ( ';
                                    $labelcost_trajetsGD_month = array();
                                    foreach ($trajetsGD_month as $label => $cost) {
                                        $labelcost_trajetsGD_month[] = $label.': <b>'.price($cost).' €</b>';
                                    }
                                    $ok_labelcost_trajetsGD_month .= implode(',  ',$labelcost_trajetsGD_month);
                                    $ok_labelcost_trajetsGD_month .= ' )';
                                }
                                ?>
                        </tr>
                        <!-- total cumule mois -->
                        <tr>
                            <td bgcolor="#cce6ff" width="100%"  colspan="<?php echo count($year_array[$month]); ?>">
                                <table class="table small"  cellspacing="2" cellpadding="2">
                                    <tbody>
                                        <tr>
                                            <td style="background-color:#F8F8FF;" width="390">Total heures mois: 
                                                <?php 
                                                $month_potential_works_secondes = $month_potential_works_hours*60*60;
                                                if($sumWorkMonth>$month_potential_works_secondes){
                                                    //$heuresSup_Month = $sumWorkMonth - $month_potential_works_secondes;
                                                    $heuresDues_Month = 0;
                                                } else {
                                                    //$heuresSup_Month = 0;
                                                    $heuresDues_Month = $month_potential_works_secondes-$sumWorkMonth;
                                                }
                                                $matrice_work_Month[$month] = array(
                                                    'potential_works'=>$month_potential_works_hours*60*60,
                                                    'sumWorkMonth'=>$sumWorkMonth,
                                                    'heuresSup_Month'=>$sumSupMonth,
                                                    'heuresDues_Month'=>$heuresDues_Month
                                                );
                                                echo format_time($month_potential_works_secondes); 
                                                ?> 
                                                ( 
                                                travail : <?php echo format_time($sumWorkMonth); ?>, heures sup: <?php echo format_time($sumSupMonth); ?>
                                                , heures dûes: <?php echo format_time($heuresDues_Month); ?>
                                                )
                                            </td>
                                            <td width="50" align="center"><div class="" style="color:#fff; float: right; background-color:#5cb85c;"><?php echo format_time($sumTravailMonth); ?></div></td>
                                            <td width="50" align="center"><div class="" style="color:#fff; float: right; background-color:#185a18;"><?php echo format_time($sumSAVMonth); ?></div></td>
                                            <td width="50" align="center"><div class="" style="color:#fff; float: right; background-color:#00ffbf"><?php echo format_time($sumEcoleMonth); ?></div></td>                                
                                            <td width="50" align="center"><div class="" style="color:#fff; float: right; background-color:#00ff80;"><?php echo format_time($sumFerieMonth); ?></div></td>
                                            <td width="50" align="center"><div class="" style="color:#fff; float: right; background-color:#8000ff"><?php echo format_time($sumMaladieMonth); ?></div></td>                              
                                            <td width="50" align="center"><div class="" style="color:#fff; float: right; background-color:#ffbf00;"><?php echo format_time($sumCongesMonth); ?></div></td>
                                            <td width="50" align="center"><div class="" style="color:#fff; float: right; background-color:#ff0000"><?php echo format_time($sumRecupMonth); ?></div></td>
                                        </tr>
                                        <tr>
                                            <td style="background-color:#F8F8FF;" width="390" colspan="1"><img src="<?php echo DOL_DOCUMENT_ROOT;?>/abcvc/img/primes-panier.png" width="7" height="7">  Total paniers: <?php echo $nbpanierMonth; ?> ( <b><?php echo price($nbpanierMonth*$prix_panier);?> €</b> )
                                                &nbsp;&nbsp;&nbsp;&nbsp;
                                                <img src="<?php echo DOL_DOCUMENT_ROOT;?>/abcvc/img/primes-road.png" width="7" height="7">  Total trajets: <?php echo $nbtrajetMonth; ?> <?php echo $ok_labelcost_trajets_month;?> 
                                                &nbsp;&nbsp;&nbsp;&nbsp;
                                                <img src="<?php echo DOL_DOCUMENT_ROOT;?>/abcvc/img/primes-night.png" width="7" height="7">Total trajets GD: <?php echo $nbtrajetGDMonth; ?> <?php echo $ok_labelcost_trajetsGD_month;?> 
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <?php 
                                    $sumWorkPeriode += $sumWorkMonth;
                                    $sumTravailPeriode += $sumTravailMonth;
                                    $sumSAVPeriode  += $sumSAVMonth;
                                    $sumCongesPeriode += $sumCongesMonth;
                                    $sumFeriePeriode += $sumFerieMonth;
                                    $sumMaladiePeriode += $sumMaladieMonth;
                                    $sumRecupPeriode += $sumRecupMonth;
                                    $sumEcolePeriode += $sumEcoleMonth; 
                                    $nbpanierPeriode += $nbpanierMonth;
                                    $nbtrajetPeriode += $nbtrajetMonth;
                                    $nbtrajetGDPeriode += $nbtrajetGDMonth;                         
                                    foreach ($trajets_month as  $label => $cost) {
                                        $trajets_periode[$label]+=$cost;
                                    }
                                    foreach ($trajetsGD_month as $label => $cost) {
                                        $trajetsGD_periode[$label]+=$cost;
                                    }
                                ?>
                            </td>
                        </tr>               
                    </tbody>
                </table>
            <?php
            endfor;
            //var_dump($year_array);
            //exit();
            //-----------------------------------------------------------------------
            //-----------------------------------------------------------------------
                //var_dump($trajets_periode);
                //var_dump($trajetsGD_periode);
                $ok_labelcost_trajets_periode = '';
                if(count($trajets_periode)>0){
                    $ok_labelcost_trajets_periode = ' ( ';
                    $labelcost_trajets_periode = array();
                    foreach ($trajets_periode as $label => $cost) {
                        $labelcost_trajets_periode[] = $label.': <b>'.price($cost).' €</b>';
                    }
                    $ok_labelcost_trajets_periode .= implode(',  ',$labelcost_trajets_periode);
                    $ok_labelcost_trajets_periode .= ' )';
                }
                
                $ok_labelcost_trajetsGD_periode = '';
                if(count($trajetsGD_periode)>0){
                    $ok_labelcost_trajetsGD_periode = ' ( ';
                    $labelcost_trajetsGD_periode = array();
                    foreach ($trajetsGD_periode as $label => $cost) {
                        $labelcost_trajetsGD_periode[] = $label.': <b>'.price($cost).' €</b>';
                    }
                    $ok_labelcost_trajetsGD_periode .= implode(',  ',$labelcost_trajetsGD_periode);
                    $ok_labelcost_trajetsGD_periode .= ' )';
                }
            ?>
            <!-- BOTTOM TABLE ( LEGEND ) -->
            <table class="table small" cellspacing="4" cellpadding="4">
                <tbody>
                    <tr>
                        <td>Legende: </td>
                        <td align="center"><div class="" style="color:#fff; padding:2px; background-color:#5cb85c;">Travail</div></td>
                        <td align="center"><div class="" style="color:#fff; padding:2px; background-color:#185a18;">MES/SAV</div></td>
                        <td align="center"><div class="" style="color:#fff; padding:2px; background-color:#00ffbf">Ecole</div></td>                             
                        <td align="center"><div class="" style="color:#fff; padding:2px; background-color:#00ff80;">Ferie</div></td>
                        <td align="center"><div class="" style="color:#fff; padding:2px; background-color:#8000ff">Maladie</div></td>                               
                        <td align="center"><div class="" style="color:#fff; padding:2px; background-color:#ffbf00;">Conges</div></td>
                        <td align="center"><div class="" style="color:#fff; padding:2px; background-color:#ff0000">Recup</div></td>
                    </tr>
                </tbody>
            </table>        
        <?php 
        // exit();
        //put buffer in variable html
        $html = ob_get_clean();
        // var_dump($html);
        // exit();
        // output the HTML content
        $pdf->writeHTML($html, true, false, false, false, '');
        $pdf->Close();
        $filename = "Presence_".$contact->lastname."_".$contact->firstname."_periode_".date('d-m-Y',$stt_from)."_".date('d-m-Y',$stt_to);
        $pdf->Output($filename,'D');
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
	function _pagehead(&$pdf, $timetree, $showaddress, $outputlangs)
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
	function _pagefoot(&$pdf,$timetree,$outputlangs,$hidefreetext=0)
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





/* *******************************************************************************************************************************************************************

    FIRST MONTH GENERATOR IN PDF

*******************************************************************************************************************************************************************       <style type="text/css">
            .small_text{
                font-size: 6pt;
            }  
           </style>
            
            <!-- TOP TABLE ( WITH NAME, TOTAL H, etc ...) -->
                <table>
                    <tbody>
                        <tr>
                            <td colspan = "3" bgcolor="#0080ff" align="center" height="20px" >
                                <?php foreach ($getcontact as $contact ) 
                                {
                                    echo $contact->firstname."&nbsp;".$contact->lastname;
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td bgcolor="#cce6ff">Periode  du <?php echo date('d/m/Y',$stt_from)." au ".date('d/m/Y',$stt_to); ?></td>
                            <td bgcolor="#cce6ff">RTT&nbsp;&nbsp;--</td>
                            <td bgcolor="#cce6ff">Brossier doit&nbsp;&nbsp;--</td>
                        </tr>
                        <tr>
                        <!-- FOREACH TO GET ALL TIMES PER YEAR ADN SHOW IT TO TOP OF CALENDAR  -->
                            <?php 
                            $fullyear= array(
                                'January'=>array(
                                    '1'=>array(),
                                    '2'=>array(),
                                    '3'=>array(),
                                    '4'=>array()
                                ),
                                'February'=>array(
                                    '4'=>array(),
                                    '5'=>array(),
                                    '6'=>array(),
                                    '7'=>array(),
                                    '8'=>array()
                                ),
                                'March'=>array(
                                    '9'=>array(),
                                    '10'=>array(),
                                    '11'=>array(),
                                    '14'=>array()
                                ),
                                'April'=>array(
                                    '15'=>array(),
                                    '16'=>array(),
                                    '17'=>array(),
                                    '18'=>array()
                                ),
                                'May'=>array(
                                    '19'=>array(),
                                    '20'=>array(),
                                    '21'=>array(),
                                    '22'=>array(),
                                    '23'=>array()
                                ),
                                'June'=>array(
                                    '24'=>array(),
                                    '25'=>array(),
                                    '26'=>array(),
                                    '27'=>array()
                                ),
                                'July'=>array(
                                    '28'=>array(),
                                    '29'=>array(),
                                    '30'=>array(),
                                    '31'=>array()
                                ),
                                'August'=>array(
                                    '32'=>array(),
                                    '33'=>array(),
                                    '34'=>array(),
                                    '35'=>array(),
                                    '36'=>array()
                                ),
                                'September'=>array(
                                    '37'=>array(),
                                    '38'=>array(),
                                    '39'=>array(),
                                    '40'=>array(),
                                    '41'=>array()
                                ),
                                'October'=>array(
                                    '42'=>array(),
                                    '43'=>array(),
                                    '44'=>array(),
                                    '45'=>array()
                                ),
                                'November'=>array(
                                    '46'=>array(),
                                    '47'=>array(),
                                    '48'=>array(),
                                    '49'=>array(),
                                    '50'=>array()
                                ),
                                'December'=>array(
                                    '51'=>array(),
                                    '52'=>array(),
                                    '53'=>array(),
                                ),
                            );
                            $total_year = 0; 
                            foreach ($fullyear as $month => $weeks) : 
                                $total_month = 0;
                                if(!in_array($month,$select_month) ){
                                    continue;}                            ?>
                                    <?php foreach ($weeks as $week => $days) : ?>
                                        <?php 
                                        $sumL = 0;
                                        if(isset($getalltime[$month][$week]['Mon'])){
                                            foreach ($getalltime[$month][$week]['Mon'] as $time) {
                                                $sumL += ($time->task_duration);
                                            }
                                        }    
                                         
                                        $sumMA = 0;
                                        if(isset($getalltime[$month][$week]['Tue'])){                                    
                                            foreach ($getalltime[$month][$week]['Tue'] as $time) {
                                                $sumMA += ($time->task_duration);
                                            }
                                        }
                                        
                                        $sumME = 0;
                                        if(isset($getalltime[$month][$week]['Wed'])){
                                            foreach ($getalltime[$month][$week]['Wed'] as $time) {
                                                $sumME += ($time->task_duration);
                                            }
                                        }
                                         
                                        $sumJ = 0;
                                        if(isset($getalltime[$month][$week]['Thu'])){
                                            foreach ($getalltime[$month][$week]['Thu'] as $time) {
                                                $sumJ += ($time->task_duration);
                                            }
                                        }
                                         
                                        $sumV = 0;
                                        if(isset($getalltime[$month][$week]['Fri'])){
                                            foreach ($getalltime[$month][$week]['Fri'] as $time) {
                                                $sumV += ($time->task_duration);
                                            }
                                        }
                                        
                                        $sumS = 0;
                                        if(isset($getalltime[$month][$week]['Sat'])){
                                            foreach ($getalltime[$month][$week]['Sat'] as $time) {
                                                $sumS += ($time->task_duration);
                                            }
                                        }
                                         
                                        $sumD = 0;
                                        if(isset($getalltime[$month][$week]['Sun'])){
                                            foreach ($getalltime[$month][$week]['Sun'] as $time) {
                                                $sumD += ($time->task_duration);
                                            }
                                        }
                                        $total_week = $sumL+$sumMA+$sumME+$sumJ+$sumV+$sumS+$sumD; 
                                        $total_month += $total_week;
                                        ?>
                                    <?php endforeach; $total_year += $total_month; ?>
                                <?php endforeach; ?>
                            <td bgcolor="#cce6ff" >TOTAL Heures: &nbsp;<?php echo $this->format_time($total_year,$f=':'); ?>&nbsp;</td>
                            <td bgcolor="#cce6ff">Heures supp&nbsp;&nbsp;--</td>
                            <td bgcolor="#cce6ff">ABCVC doit&nbsp;&nbsp;--</td>
                        </tr>
                    </tbody>
                </table>
    <br>
    <br>

            <!-- MIDDLE TABLE ( WITH ALL MONTH(S) GENERATED ) -->
                <?php 
                $fullyear= array(
                    'January'=>array(
                        '1'=>array(),
                        '2'=>array(),
                        '3'=>array(),
                        '4'=>array()
                    ),
                    'February'=>array(
                        '4'=>array(),
                        '5'=>array(),
                        '6'=>array(),
                        '7'=>array(),
                        '8'=>array()
                    ),
                    'March'=>array(
                        '9'=>array(),
                        '10'=>array(),
                        '11'=>array(),
                        '14'=>array()
                    ),
                    'April'=>array(
                        '15'=>array(),
                        '16'=>array(),
                        '17'=>array(),
                        '18'=>array()
                    ),
                    'May'=>array(
                        '19'=>array(),
                        '20'=>array(),
                        '21'=>array(),
                        '22'=>array(),
                        '23'=>array()
                    ),
                    'June'=>array(
                        '24'=>array(),
                        '25'=>array(),
                        '26'=>array(),
                        '27'=>array()
                    ),
                    'July'=>array(
                        '28'=>array(),
                        '29'=>array(),
                        '30'=>array(),
                        '31'=>array()
                    ),
                    'August'=>array(
                        '32'=>array(),
                        '33'=>array(),
                        '34'=>array(),
                        '35'=>array(),
                        '36'=>array()
                    ),
                    'September'=>array(
                        '37'=>array(),
                        '38'=>array(),
                        '39'=>array(),
                        '40'=>array(),
                        '41'=>array()
                    ),
                    'October'=>array(
                        '42'=>array(),
                        '43'=>array(),
                        '44'=>array(),
                        '45'=>array()
                    ),
                    'November'=>array(
                        '46'=>array(),
                        '47'=>array(),
                        '48'=>array(),
                        '49'=>array(),
                        '50'=>array()
                    ),
                    'December'=>array(
                        '51'=>array(),
                        '52'=>array(),
                        '53'=>array(),
                    ),
                );

                $total_year = 0; //TOTAL TIME PER YEAR
                $count_month = 0; // i chose to count the month to show 2 months per page
                foreach ($fullyear as $month => $weeks) : 
                    $total_month = 0; //TOTAL TIME PER MONTH
                    //var_dump($getalltime);
                    if(!in_array($month,$select_month) ){
                        //FALSE GO TO NEXT MONTH
                        continue;}
                    $count_month = $count_month + count($month); //
                    ?>

                <table border="1" style="font-size: 7pt">
                    <tr>
                        <td bgcolor="#0080ff" align="center" width="5%" >
                            <?php echo $month; ?>
                        </td>
                        <?php $table_size = 95/count($weeks);?>
                        <?php foreach ($weeks as $week => $days) : ?>
                            <td width="<?php echo $table_size; ?>% ">
                                <table cellspacing="0" cellpadding="1" border="1" style="font-size: 7pt" >
                                    <tr>
                                        <td colspan="4" bgcolor="#0080ff">SEMAINE <?php echo $week; ?></td>                  
                                    </tr>
                                    <tr>
                                        <td bgcolor="#cce6ff" class="small_text" >LUNDI</td>
                                        <td bgcolor="#00bfff" >
                                            <?php 
                                            $sumL = 0;
                                            //calcul sumL foreach
                                            if(isset($getalltime[$month][$week]['Mon'])){
                                                foreach ($getalltime[$month][$week]['Mon'] as $time) {
                                                    $sumL += ($time->task_duration);
                                                }
                                            }
                                            //echo date("H:i", $sumL);
                                            //var_dump($sumL);
                                            echo $this->format_time($sumL,$f=':'); // t = seconds, f = separator 
                                            ?>
                                        </td>
                                        <td bgcolor="#00bfff" >
                                            <?php 
                                            echo $getalltime[$month][$week]['Mon'][0]->chantier;
                                            ?>
                                        </td>
                                        <td bgcolor="#ff00ff" >
                                            <?php 
                                            echo $getalltime[$month][$week]['Mon'][0]->zone;
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td bgcolor="#cce6ff" class="small_text">MARDI</td>
                                        <td bgcolor="#00bfff" >
                                            <?php 
                                            $sumMA = 0;
                                            if(isset($getalltime[$month][$week]['Tue'])){
                                                foreach ($getalltime[$month][$week]['Tue'] as $time) {
                                                    $sumMA += ($time->task_duration);
                                                }
                                            }
                                            echo  $this->format_time($sumMA,$f=':'); // t = seconds, f = separator 
                                            ?>
                                        </td>
                                        <td bgcolor="#00bfff" >
                                            <?php 
                                            echo $getalltime[$month][$week]['Tue'][0]->chantier;
                                            ?>
                                        </td>
                                        <td bgcolor="#ff00ff" >
                                            <?php 
                                            echo $getalltime[$month][$week]['Tue'][0]->zone;
                                            ?>
                                                
                                        </td>
                                    </tr>                                                           
                                    <tr>
                                        <td bgcolor="#cce6ff" class="small_text">MERCREDI</td>
                                        <td bgcolor="#00bfff" >
                                            <?php 
                                            $sumME = 0;
                                            //calcul sumME
                                            if(isset($getalltime[$month][$week]['Wed'])){
                                                foreach ($getalltime[$month][$week]['Wed'] as $time) {
                                                    $sumME += ($time->task_duration);
                                                }
                                            }
                                            echo  $this->format_time($sumME,$f=':'); // t = seconds, f = separator 
                                            ?>
                                        </td>
                                        <td bgcolor="#00bfff" >
                                            <?php 
                                            echo $getalltime[$month][$week]['Wed'][0]->chantier;
                                            ?>
                                        </td>
                                        <td bgcolor="#ff00ff" >
                                            <?php 
                                            echo $getalltime[$month][$week]['Wed'][0]->zone;
                                            ?>
                                            
                                        </td>
                                    </tr>                                                           
                                    <tr>
                                        <td bgcolor="#cce6ff" class="small_text">JEUDI</td>
                                        <td bgcolor="#00bfff" >
                                            <?php 
                                            $sumJ = 0;
                                            //calcul sumJ
                                            if(isset($getalltime[$month][$week]['Thu'])){
                                                foreach ($getalltime[$month][$week]['Thu'] as $time) {
                                                    $sumJ += ($time->task_duration);
                                                }
                                            }
                                            echo  $this->format_time($sumJ,$f=':'); // t = seconds, f = separator 
                                            ?>
                                        </td>
                                        <td bgcolor="#00bfff" >
                                            <?php 
                                            echo $getalltime[$month][$week]['Thu'][0]->chantier;
                                            ?>
                                        </td>   
                                        <td bgcolor="#ff00ff" >
                                            <?php 
                                            echo $getalltime[$month][$week]['Thu'][0]->zone;
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>    
                                        <td bgcolor="#cce6ff" class="small_text">VENDREDI</td>
                                        <td bgcolor="#00bfff" >
                                            <?php 
                                            $sumV = 0;
                                            //calcul sumV
                                            if(isset($getalltime[$month][$week]['Fri'])){
                                                foreach ($getalltime[$month][$week]['Fri'] as $time) {
                                                    $sumV += ($time->task_duration);
                                                }
                                            }
                                            echo  $this->format_time($sumV,$f=':'); // t = seconds, f = separator 
                                            ?>
                                        </td>
                                        <td bgcolor="#00bfff" >
                                            <?php 
                                            echo $getalltime[$month][$week]['Fri'][0]->chantier;
                                            ?>
                                        </td>   
                                        <td bgcolor="#ff00ff" ><?php 
                                            echo $getalltime[$month][$week]['Fri'][0]->zone;
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>    
                                        <td bgcolor="#cce6ff" class="small_text">SAMEDI</td>
                                        <td bgcolor="#00bfff" >
                                            <?php 
                                            $sumS = 0;
                                            //calcul sumS
                                            if(isset($getalltime[$month][$week]['Sat'])){
                                                foreach ($getalltime[$month][$week]['Sat'] as $time) {
                                                    $sumS += ($time->task_duration);
                                                }
                                            }
                                            echo  $this->format_time($sumS,$f=':'); // t = seconds, f = separator 
                                            ?>
                                        </td>
                                        
                                        <td bgcolor="#00bfff" >
                                            <?php 
                                            echo $getalltime[$month][$week]['Sat'][0]->chantier;
                                            ?>
                                            
                                        </td>
                                        <td bgcolor="#ff00ff" >
                                            <?php 
                                            echo $getalltime[$month][$week]['Sat'][0]->zone;
                                            ?>
                                            
                                        </td>
                                    </tr>                                                       
                                    <tr>    
                                        <td bgcolor="#cce6ff" class="small_text">DIMANCHE</td>
                                        <td bgcolor="#00bfff" >
                                            <?php 
                                            $sumD = 0;
                                            if(isset($getalltime[$month][$week]['Sun'])){
                                                foreach ($getalltime[$month][$week]['Sun'] as $time) {
                                                    $sumD += ($time->task_duration);
                                                }
                                            }
                                            echo  $this->format_time($sumD,$f=':'); // t = seconds, f = separator 
                                            ?>
                                        </td>
                                        <td bgcolor="#00bfff">
                                            <?php 
                                            echo $getalltime[$month][$week]['Sun'][0]->chantier;
                                            ?>  
                                        </td> 
                                        <td bgcolor="#ff00ff">
                                            <?php 
                                            echo $getalltime[$month][$week]['Sun'][0]->zone;
                                            ?>
                                        </td> 
                                    </tr>                                                   
                                    <tr>
                                        <td  bgcolor="#cce6ff" >TOTAL</td>
                                        <td colspan="3" bgcolor="#cce6ff" >
                                            <?php    
                                                $total_week = $sumL+$sumMA+$sumME+$sumJ+$sumV+$sumS+$sumD; 
                                                $total_month += $total_week;
                                                echo  $this->format_time($total_week,$f=':');
                                            ?>               
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        <?php endforeach; ?>       
                    </tr>
                    <tr>
                        <td bgcolor="#0080ff" rowspan="3" align="center">TOTAL per MOIS</td> 
                        <td bgcolor="#0080ff" colspan="<?php echo count($weeks); ?>">
                            Total heures <?php echo $month; ?> : <?php 
                                                                    echo  $this->format_time($total_month,$f=':'); 
                                                                    $total_year += $total_month  ;
                                                                ?> <br>
                            Total panniers: <br>
                            Total trajets:  
                        </td>
                    </tr>
                </table>
                <br>
                <br>
                <?php if( $count_month % 3 == 0 ){
                    // $pdf->SetAutoPageBreak(True, PDF_MARGIN_BOTTOM);
                    ?><br pagebreak="true" />
                 <?php
                }
                ?>
                <?php endforeach; ?>
    <br>
    <br>

            <!-- BOTTOM TABLE ( LEGEND ) -->
                <table cellpadding="1" cellspacing="1" >
                    <tbody>
                        <tr>
                            <td rowspan = "2" >LEGEND</td>
                            <td bgcolor="#ff4000">PANNIERS</td>
                            <td bgcolor="#ff00ff">TRAJETS</td>
                            <td bgcolor="#00ffff">PANNIERS + TRAJETS</td>
                            <td bgcolor="#ffff00">GRAND DEPLACEMENT</td>
                            <td bgcolor="#ffbf00">CONGES</td>
                        </tr>
                        <tr>
                            <td bgcolor="#00ff80">FERIE</td>
                            <td bgcolor="#8000ff">MALADIE</td>
                            <td bgcolor="#ff0000">Recup</td>
                            <td bgcolor="#00ffbf">ECOLE</td>
                            <td >ADD ANOTHER LEGEND</td>
                        </tr>
                    </tbody>
                </table>


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