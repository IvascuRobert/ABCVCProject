<?php
/* Copyright (C) 2010-2012  Regis Houssin  <regis.houssin@capnetworks.com>
 * Copyright (C) 2015       Charlie Benke  <charlie@patas-monkey.com>

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
 *  \file       htdocs/core/modules/project/doc/pdf_abcvc.modules.php
 *  \ingroup    project
 *  \brief      Fichier de la classe permettant de generer les projets au modele abcvc
 *  \author     Charlie Benke
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
 *  Classe permettant de generer les projets au modele Baleine
 */

class pdf_abcvc extends ModelePDFProjects
{
    var $emetteur;  // Objet societe qui emet

    /**
     *  Constructor
     *
     *  @param      DoliDB      $db      Database handler
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
     *  Fonction generant le projet sur le disque
     *
     *  @param  Project     $object         Object project a generer
     *  @param  Translate   $outputlangs    Lang output object
     *  @return int                         1 if OK, <=0 if KO
     */
    function write_file($object,$outputlangs)
    {

        // var_dump($object);
        //exit();

        global $user,$langs,$conf;

        $formproject = new FormProjets($this->db);

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
                $default_font_size = pdf_getPDFFontSize($outputlangs);  // Must be after pdf_getInstance
                $heightforinfotot = 50; // Height reserved to output the info and total part
                $heightforfreetext= (isset($conf->global->MAIN_PDF_FREETEXT_HEIGHT)?$conf->global->MAIN_PDF_FREETEXT_HEIGHT:5); // Height reserved to output the free text on last page
                $heightforfooter = $this->marge_basse + 8;  // Height reserved to output the footer (value include bottom margin)
                $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);



                $pdf->SetAutoPageBreak(1,$this->marge_basse);

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

                $pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite, $this->marge_basse );   // Left, Top, Right

                // New page
                $pdf->AddPage();
                $pagenb++;
                $this->_pagehead($pdf, $object, 1, $outputlangs);
                $pdf->SetFont('','', $default_font_size - 1);
                $pdf->MultiCell(0, 3, '');      // Set interline to 3
                $pdf->SetTextColor(0,0,0);

                $tab_top = 50;
                $tab_height = 200;
                $tab_top_newpage = 40;
                $tab_height_newpage = 210;

                // // Affiche notes
                // if (! empty($object->note_public))
                // {
                //  $pdf->SetFont('','', $default_font_size - 1);
                //  $pdf->writeHTMLCell(190, 3, $this->posxref-1, $tab_top-2, dol_htmlentitiesbr($object->note_public), 0, 1);
                //  $nexY = $pdf->GetY();
                //  $height_note=$nexY-($tab_top-2);

                //  // Rect prend une longueur en 3eme param
                //  $pdf->SetDrawColor(192,192,192);
                //  $pdf->Rect($this->marge_gauche, $tab_top-3, $this->page_largeur-$this->marge_gauche-$this->marge_droite, $height_note+1);

                //  $tab_height = $tab_height - $height_note;
                //  $tab_top = $nexY+6;
                // }
                // else
                // {
                //  $height_note=0;
                // }

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
                    <br>
                    <p><i>&nbsp;&nbsp;&nbsp;&nbsp;Description: <?php print nl2br($object->description); ?></i></p>
                    <?php  
                            $vente = 0;
                            $total_marge = 0;
                            $couts_estimes = 0;
                            $couts_calcules = 0;?>


                            <!-- CSS goes in the document HEAD or added to your external stylesheet -->
                            <style type="text/css">
                            table.imagetable {
                                font-family: verdana,arial,sans-serif;
                                font-size:6px;
                                color:#333333;
                            }
                            table.imagetable th {
                                border-color: black #ffffff black black;
                                background-color:#000000;
                                padding: 8px;
                                color:#ffffff;
                            }
                            table.imagetable td {
                                background-color:#ffffff;
                                padding: 8px;
                            }
                            </style>
                    <table class="imagetable" border="0.01" cellspacing="0" cellpadding="1">
                        <thead>
                            <tr nobr="true">
                                <th align="center"  width="10%"  ><b>Code</b></th>
                                <th align="center"  width="20%"  ><b>Libellé</b></th>
                                <th align="center"  width="10%"  ><b>Coûts estimés</b></th>
                                <th align="center" width="10%" ><b>Coûts calculés</b></th>
                                <th align="center" width="10%" ><b>Vente</b></th>
                                <th align="center" width="10%" ><b>Marge</b></th>
                                <th align="center" width="15%" ><b>Avancement estimé</b></th>
                                <th align="center" width="15%" ><b>Avancement réél</b></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $projectTree['tree'] as $key => $lot) : ?>
                            <?php   $couts_calcules +=price2num($lot->cost_calculated);
                                    $vente += price2num($lot->pv_lot); 
                                    $couts_estimes += price2num($lot->cost,2);
                                    $total_marge += price2num($lot->marge); ?>
                            <tr nobr="true">
                                <td width="10%"><b><?php echo $lot->ref;?></b></td>
                                <td align="center" width="20%"><b><?php echo $lot->label; ?></b></td>
                                <td align="right" width="10%"><b><?php echo price($lot->cost,2); ?>€</b></td>
                                <td align="right" width="10%"><b><?php echo price($lot->cost_calculated,2); ?>€</b></td>
                                <td align="right" width="10%"><b><?php echo price($lot->pv_lot,2); ?>€</b></td>
                                <td align="right" width="10%"><b><?php 
                                                                    if( $lot->marge>0){
                                                                        echo price( $lot->marge,2).'€'; 
                                                                    } else {
                                                                        echo '<span style="color:red;">'.price( $lot->marge,2).'€</span>'; 
                                                                    }
                                                                ?></b></td>
                                <td align="center" width="15%"></td>
                                <td align="center" width="15%"></td>
                            </tr >
                                <?php foreach ( $lot->categories as $key => $categorie) : ?>
                                    <tr nobr="true">
                                        <td><?php echo $categorie->ref; ?></td>
                                        <td><?php echo $categorie->label; ?></td>
                                        <td align="right"><?php echo price($categorie->cost,2); ?>€</td>
                                        <td align="right" ><?php echo price($categorie->cost_calculated,2); ?>€</td>
                                        <td align="right" ><?php echo price($categorie->pv_categorie,2); ?>€</td>
                                        <td align="right" ><?php 
                                                            if( $categorie->marge_categorie>0){
                                                                echo price( $categorie->marge_categorie,2).'€'; 
                                                            } else {
                                                                echo '<span style="color:red;">'.price( $categorie->marge_categorie,2).'€</span>'; 
                                                            }
                                                        ?></td>
                                        <td align="center"></td>
                                        <td align="center"></td>
                                    </tr>
                                    <?php foreach ( $categorie->postes as $key => $poste) : ?>  
                                        <tr nobr="true">
                                            <td><?php echo $poste->ref; ?></td>
                                            <td><?php echo $poste->label; ?></td>
                                            <td align="right"><?php echo  price($poste->cost); ?>€</td>
                                            <td align="right" ><?php echo price($poste->cost_final,2); ?>€</td>
                                            <td align="right" ><?php echo price($poste->poste_pv,2); ?>€</td>
                                            <td align="right" ><?php 
                                                                    $marge = $poste->poste_pv - $poste->cost_final;
                                                                    if($marge>0){
                                                                        echo price($marge,2).'€'; 
                                                                    } else {
                                                                        echo '<span style="color:red;">'.price($marge,2).'€</span>'; 
                                                                    }
                                                                ?>   
                                            </td>
                                            <td>
                                                <table width="100%">
                                                    <?php 
                                                        if($poste->progress_estimated<80){
                                                            $progress_estimated_color = "background-color: #5cb85c;";
                                                        } elseif($poste->progress_estimated<100){
                                                            $progress_estimated_color = "background-color: #f0ad4e;";
                                                        } elseif($poste->progress_estimated ==100){
                                                            $progress_estimated_color = "background-color: #5bc0de;";
                                                        } else {
                                                            $progress_estimated_color = "background-color: #dc3545;";
                                                        }
                                                        if($poste->progress_estimated>0){
                                                            if($poste->progress_estimated>=100){
                                                                $progress_estimated_value = "100%";
                                                            } else {
                                                                $progress_estimated_value = $poste->progress_estimated."%";
                                                            }
                                                        }else{ 
                                                            $progress_estimated_value = "1%";
                                                        }
                                                        
                                                    ?>
                                                    <tr>
                                                        <td align="center" width="35%" style="font-size:5px;"><?php echo $poste->progress_estimated; ?>%</td>
                                                        <td width="65%"><table width="100%">
                                                        <tr nobr="true">
                                                            <td bgcolor="#ccc" width="100%" ><table width="<?php echo $progress_estimated_value; ?>">
                                                                        <tr>
                                                                            <td style="<?php echo $progress_estimated_color;?>" ></td></tr>
                                                                </table></td>
                                                        </tr>
                                                    </table></td>
                                                    </tr>
                                                </table>
                                            </td>
                                            <td>
                                                <table width="100%">
                                                    <?php 
                                                    if($poste->progress<80){
                                                        $progress_color = "background-color: #5cb85c;";
                                                    } elseif($poste->progress<100){
                                                        $progress_color = "background-color: #f0ad4e;";
                                                    } elseif($poste->progress ==100){
                                                        $progress_color = "background-color: #5bc0de;";
                                                    } else {
                                                        $progress_color = "background-color: #dc3545;";
                                                    }
                                                    if($poste->progress>0){
                                                        $progress_value = $poste->progress."%";
                                                    }else{ 
                                                        $progress_value = "1%";
                                                    }

                                                    ?>
                                                    <tr>
                                                        <td align="center" width="35%" style="font-size:5px;" ><?php echo $poste->progress; ?>%</td>
                                                        <td width="65%"><table width="100%">
                                                        <tr nobr="true">
                                                            <td bgcolor="#ccc" width="100%" ><table width="<?php echo $progress_value; ?>">
                                                                        <tr>
                                                                            <td style="<?php echo $progress_color;?>" ></td></tr>
                                                                </table></td>
                                                        </tr>
                                                    </table></td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                        <?php 
                                        // !!! somme subposte/subsubposte PV <= $poste->poste_pv !!!
                                        // --------------------------------------------------------------
                                        $sum_subpostes_pv = 0;
                                        foreach ( $poste->subpostes as $key => $subposte) {
                                            $sum_subpostes_pv += $subposte->poste_pv;
                                            unset($subposte);
                                        }
                                        $delta_sum_pv = round(($sum_subpostes_pv - $poste->poste_pv),2);
                                        //0.05 ecart accepte...
                                        if( ($delta_sum_pv == 0) || ( ($delta_sum_pv > -0.05) && ($delta_sum_pv < 0.05) ) ){
                                            $info_pv_detail = 'equ';
                                        } elseif( $delta_sum_pv > 0.05 ){
                                            $info_pv_detail = 'sup';
                                        } elseif( $delta_sum_pv < -0.05 ){
                                            $info_pv_detail = 'inf';
                                        } 
                                        ?>                                        
                                        <?php foreach ( $poste->subpostes as $key => $subposte) : ?>
                                            <tr nobr="true">
                                                <td><?php echo $subposte->ref; ?></td>
                                                <td><?php echo $subposte->label; ?><?php if($subposte->quantite == 0.00 && $subposte->unite == 0){?></td>
                                                        <?php }else{ ?>
                                                            <br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<em><?php echo $subposte->quantite." (".$subposte->unite.")"; ?></em></td>
                                                        <?php } ?>
                                                        
                                                <td align="right"><?php echo price($subposte->cost); ?>€</td>
                                                <td align="right" ><?php echo price($subposte->cost_final,2); ?>€</td>
                                                <td align="right" >
                                                <?php if($info_pv_detail == 'sup'):?>
                                                    <i><span style="color:red;"><?php echo price($subposte->poste_pv); ?>€</span></i>
                                                <?php elseif($info_pv_detail == 'inf'):?>
                                                    <i><span style="color:#f0ad4e;"><?php echo price($subposte->poste_pv); ?>€</span></i>
                                                <?php else: ?>
                                                    <i><?php echo price($subposte->poste_pv); ?>€</i>
                                                <?php endif; ?>
                                                </td>
                                                <td align="right" ><?php 
                                                                        $marge = $subposte->poste_pv - $subposte->cost_final;
                                                                        if( ($marge>0) && ($info_pv_detail == 'equ') ){
                                                                            echo price($marge,2).'€'; 
                                                                        } else {
                                                                            echo '<span style="color:red;">'.price($marge,2).'€</span>'; 
                                                                        }
                                                                    ?></td>
                                                <td align="center"></td>
                                                <td align="center"></td>
                                            </tr>
                                            <?php 
                                                // !!! somme subposte/subsubposte PV <= $subposte->poste_pv !!!
                                                // --------------------------------------------------------------
                                                $sum_subsubpostes_pv = 0;
                                                foreach ( $subposte->subsubpostes as $key => $subsubposte ) {
                                                    $sum_subsubpostes_pv += round($subsubposte->poste_pv,2);
                                                }
                                                unset($subsubposte);

                                                $delta_sum_subpv = round(($sum_subsubpostes_pv - $subposte->poste_pv),2);
                                                //0.05 ecart accepte...
                                                if( ($delta_sum_subpv == 0) || ( ($delta_sum_subpv > -0.05) && ($delta_sum_subpv < 0.05) ) ){
                                                    $info_subpv_detail = 'equ';
                                                } elseif( $delta_sum_subpv > 0.05 ){
                                                    $info_subpv_detail = 'sup';
                                                } elseif( $delta_sum_subpv < -0.05 ){
                                                    $info_subpv_detail = 'inf';
                                                } 


                                            ?>
                                            <?php foreach ( $subposte->subsubpostes as $key => $subsubposte) : ?>
                                                <tr nobr="true">
                                                    <td><?php echo $subsubposte->ref; ?></td>
                                                    <td><?php echo $subsubposte->label; ?><?php if($subsubposte->quantite == 0.00 && $subsubposte->unite == 0){?></td>
                                                        <?php }else{ ?>
                                                            <br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<em><?php echo $subsubposte->quantite." (".$subsubposte->unite.")"; ?></em></td>
                                                        <?php } ?>
                                                    <td align="right"><?php echo price($subsubposte->cost); ?>€</td>
                                                    <td align="right" ><?php echo price($subsubposte->cost_final,2); ?>€</td>
                                                    <td align="right" >
                                                    <?php if($info_subpv_detail == 'sup'):?>
                                                        <i><span style="color:red;"><?php echo price($subsubposte->poste_pv); ?>€</span></i>
                                                    <?php elseif($info_subpv_detail == 'inf'):?>
                                                        <i><span style="color:#f0ad4e;"><?php echo price($subsubposte->poste_pv); ?>€</span></i>
                                                    <?php else: ?>
                                                        <i><?php echo price($subsubposte->poste_pv); ?>€</i>
                                                    <?php endif; ?>
                                                    </td>
                                                    <td align="right" ><?php 
                                                                            $marge = $subsubposte->poste_pv - $subsubposte->cost_final;
                                                                            if( ($marge>0) && ($info_subpv_detail == 'equ') ){
                                                                                echo price($marge,2).'€'; 
                                                                            } else {
                                                                                echo '<span style="color:red;">'.price($marge,2).'€</span>'; 
                                                                            }
                                                                        ?></td>
                                                    <td align="center"></td>
                                                    <td align="center"></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            <tr nobr="true">
                                <td ></td>
                                <td align="center" ><b>Charges fixes projet</b></td>
                                <td align="right" ><b><?php echo price($object->chargesfixe); ?>€</b></td>
                                <td align="right"><b><?php echo price($object->chargesfixe); ?>€</b></td>
                                <td align="center"></td>
                                <td align="right"></td>
                                <td align="center"></td>
                                <td align="center"></td>                                
                            </tr>
                            <tr nobr="true">
                                <td ></td>
                                <td align="center" ><b> Total projet </b></td>
                                <td align="right" ><b><?php echo price($couts_estimes)?>€</b></td>
                                <td align="right"><b><?php echo price($couts_calcules)?>€</b></td>
                                <td align="center"><b><?php echo price($vente)?>€</b></td>
                                <td align="right"><?php 
                                    $marge = $vente-$couts_calcules;
                                    if($marge>0){
                                        echo '<b>'.price($marge,2).'€</b>'; 
                                    } else {
                                        echo '<span style="color:red;">'.price($marge).'€</span>'; 
                                    }
                                ?></td>
                                
                                <td align="center"></td>
                                <td align="center"></td>                                
                            </tr>
                        </tbody>
                    </table>
                    
                    <?php 
                    //put buffer in variable html
                    $html = ob_get_clean();
                    //OLD
                                // output the HTML content
                                // $pdf->writeHTML($html, true, false, true, false, '');
                                // $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
                                // $posthtmly = $pdf->GetY();
                                // var_dump($posthtmly);
                                // exit;  
                                // Boucle sur les lignes
                                // for ($i = 0 ; $i < $nblignes ; $i++)
                                // {
                                //     $curY = $nexY;

                                //     // Description of ligne
                                //     $ref=$object->lines[$i]->ref;
                                //     $libelleline=$object->lines[$i]->label;
                                //     $progress=$object->lines[$i]->progress.'%';
                                //     $datestart=dol_print_date($object->lines[$i]->date_start,'day');
                                //     $dateend=dol_print_date($object->lines[$i]->date_end,'day');
                                //     $planned_workload=convertSecondToTime((int) $object->lines[$i]->planned_workload,'allhourmin');

                                //     $pdf->SetFont('','', $default_font_size - 1);   // Dans boucle pour gerer multi-page

                                //     $pdf->SetXY($this->posxref, $curY);
                                //     $pdf->MultiCell($this->posxlabel-$this->posxref, 3, $outputlangs->convToOutputCharset($ref), 0, 'L');
                                //     $pdf->SetXY($this->posxlabel, $curY);
                                //     $pdf->MultiCell($this->posxworkload-$this->posxlabel, 3, $outputlangs->convToOutputCharset($libelleline), 0, 'L');
                                //     $pdf->SetXY($this->posxworkload, $curY);
                                //     $pdf->MultiCell($this->posxprogress-$this->posxworkload, 3, $planned_workload, 0, 'R');
                                //     $pdf->SetXY($this->posxprogress, $curY);
                                //     $pdf->MultiCell($this->posxdatestart-$this->posxprogress, 3, $progress, 0, 'R');

                                //     $pdf->SetXY($this->posxdatestart, $curY);
                                //     $pdf->MultiCell($this->posxdateend-$this->posxdatestart, 3, $datestart, 0, 'C');
                                //     $pdf->SetXY($this->posxdateend, $curY);
                                //     $pdf->MultiCell($this->page_largeur-$this->marge_droite-$this->posxdateend, 3, $dateend, 0, 'C');

                                //     $pageposafter=$pdf->getPage();

                                //     $pdf->SetFont('','', $default_font_size - 1);   // On repositionne la police par defaut
                                //     $nexY = $pdf->GetY();

                                //     // Add line
                                //     if (! empty($conf->global->MAIN_PDF_DASH_BETWEEN_LINES) && $i < ($nblignes - 1))
                                //     {
                                //         $pdf->setPage($pageposafter);
                                //         $pdf->SetLineStyle(array('dash'=>'1,1','color'=>array(80,80,80)));
                                //         //$pdf->SetDrawColor(190,190,200);
                                //         $pdf->line($this->marge_gauche, $nexY+1, $this->page_largeur - $this->marge_droite, $nexY+1);
                                //         $pdf->SetLineStyle(array('dash'=>0));
                                //     }

                                //     $nexY+=2;    // Passe espace entre les lignes

                                //     // Detect if some page were added automatically and output _tableau for past pages
                                //     while ($pagenb < $pageposafter)
                                //     {
                                //         $pdf->setPage($pagenb);
                                //         if ($pagenb == 1)
                                //         {
                                //             $this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, 0, 1);
                                //         }
                                //         else
                                //         {
                                //             $this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1);
                                //         }
                                //         $this->_pagefoot($pdf,$object,$outputlangs,1);
                                //         $pagenb++;
                                //         $pdf->setPage($pagenb);
                                //         $pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.
                                //     }
                                //     if (isset($object->lines[$i+1]->pagebreak) && $object->lines[$i+1]->pagebreak)
                                //     {
                                //         if ($pagenb == 1)
                                //         {
                                //             $this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, 0, 1);
                                //         }
                                //         else
                                //         {
                                //             $this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1);
                                //         }
                                //         $this->_pagefoot($pdf,$object,$outputlangs,1);
                                //         // New page
                                //         $pdf->AddPage();
                                //         if (! empty($tplidx)) $pdf->useTemplate($tplidx);
                                //         $pagenb++;
                                //     }
                                // }

                // Show square
                if ($pagenb == 1 )
                {
                    $pdf->writeHTML($html, true, false, true, false, '');
                    // $pdf->writeHTMLCell(190, 3, $this->posxref-1, $tab_top-2 , $html , 1 , 0, false, true,'', true);
                    // $this->_tableau($html, $tab_top, $this->page_hauteur - $tab_top - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 0, 0);
                    $bottomlasttab=$this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
                }
                else
                {
                    $pdf->writeHTML($html, true, false, true, false, '');
                    // $pdf->writeHTMLCell(190, 3, $this->posxref-1, $tab_top-2 , $html , 1 , 0, false, true,'', true);
                    //writeHTMLCell($w, $h, $x, $y, $html='', $border=0, $ln=0, $fill=false, $reseth=true, $align='', $autopadding=true)
                    // $this->_tableau($html, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 1, 0);
                    $bottomlasttab=$this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
                }
                    // 
                    /*
                     * Pied de page
                     */
                    // if($posthtmly < 50 )
                    // {
                    //     $this->_pagefoot($pdf,$object,$outputlangs);
                    //     if (method_exists($pdf,'AliasNbPages')) $pdf->AliasNbPages();

                    // }else{
                    //     $pdf->AddPage('','',true);
                    //     $this->_pagefoot($pdf,$object,$outputlangs);
                    //     if (method_exists($pdf,'AliasNbPages')) $pdf->AliasNbPages();
                    // }
                    
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
     *   @param     PDF         $pdf            Object PDF
     *   @param     string      $tab_top        Top position of table
     *   @param     string      $tab_height     Height of table (rectangle)
     *   @param     int         $nexY           Y
     *   @param     Translate   $outputlangs    Langs object
     *   @param     int         $hidetop        Hide top bar of array
     *   @param     int         $hidebottom     Hide bottom bar of array
     *   @return    void
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
     *  @param  PDF         $pdf            Object PDF
     *  @param  Project     $object         Object to show
     *  @param  int         $showaddress    0=no, 1=yes
     *  @param  Translate   $outputlangs    Object lang for output
     *  @return void
     */
    function _pagehead(&$pdf, $object, $showaddress, $outputlangs)
    {
        global $langs,$conf,$mysoc;

        $default_font_size = pdf_getPDFFontSize($outputlangs);

        pdf_pagehead($pdf,$outputlangs,$this->page_hauteur);

        $pdf->SetTextColor(0,0,60);
        $pdf->SetFont('','B', $default_font_size + 3);

        $posx=$this->page_largeur-$this->marge_droite-160;
        $posy=$this->marge_haute;

        $pdf->SetXY($this->marge_gauche,$posy);

        // Logo
        $logo=$conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
        if ($mysoc->logo)
        {
            if (is_readable($logo))
            {
                $height=pdf_getHeightForLogo($logo);
                $pdf->Image($logo, $this->marge_gauche, $posy, 0, $height); // width=0 (auto)
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
        $pdf->MultiCell(160, 4, $outputlangs->transnoentities("Project")." ".$outputlangs->convToOutputCharset($object->ref), '', 'R');
        
        $pdf->SetFont('','', $default_font_size + 1);

        $posy+=6;
        $pdf->SetXY($posx,$posy);
        $pdf->SetTextColor(0,0,60);
        $pdf->MultiCell(160, 4, $outputlangs->transnoentities("Client")." : " .$outputlangs->convToOutputCharset($object->thirdparty->nom)." (" .$outputlangs->convToOutputCharset($object->thirdparty->name_alias).")", '', 'R');
        

        $pdf->SetFont('','italic', $default_font_size + 1);
        $posy+=6;
        $pdf->SetXY($posx,$posy);
        $pdf->MultiCell(160, 4, $outputlangs->convToOutputCharset($object->titre), '', 'R');

        $pdf->SetTextColor(0,0,60);

    }

    /**
     *      Show footer of page. Need this->emetteur object
     *
     *      @param  PDF         $pdf                PDF
     *      @param  Project     $object             Object to show
     *      @param  Translate   $outputlangs        Object lang for output
     *      @param  int         $hidefreetext       1=Hide free text
     *      @return integer
     */
    function _pagefoot(&$pdf,$object,$outputlangs,$hidefreetext=0)
    {
        global $conf;
        $showdetails=$conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS;
        return pdf_pagefoot($pdf,$outputlangs,'PROJECT_FREE_TEXT',$this->emetteur,12,$this->marge_gauche,$this->page_hauteur,$object,$showdetails,$hidefreetext);
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