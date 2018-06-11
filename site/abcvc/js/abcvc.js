/*
ABCVC JS
*/

//=========================================================================
//DOM READY
$(document).ready(function(){

    //active tooltip
    $('[data-toggle="tooltip"]').tooltip({
        'html':true
    });


    //GENERIC FILTERS
    //------------------------------------------------
    $('.filterable .btn-filter').click(function(){
        var $panel = $(this).parents('.filterable'),
        $filters = $panel.find('.filters input'),
        $tbody = $panel.find('.table tbody');
        if ($filters.prop('disabled') == true) {
            $filters.prop('disabled', false);
            $filters.first().focus();
        } else {
            $filters.val('').prop('disabled', true);
            $tbody.find('.no-result').remove();
            $tbody.find('tr').show();
        }
    });
    $('.filterable .filters input').keyup(function(e){
        /* Ignore tab key */
        var code = e.keyCode || e.which;
        if (code == '9') return;
        /* Useful DOM data and selectors */
        var $input = $(this),
        inputContent = $input.val().toLowerCase(),
        $panel = $input.parents('.filterable'),
        column = $panel.find('.filters th').index($input.parents('th')),
        $table = $panel.find('.table'),
        $rows = $table.find('tbody tr');
        /* Dirtiest filter function ever ;) */
        var $filteredRows = $rows.filter(function(){
            var value = $(this).find('td').eq(column).text().toLowerCase();
            return value.indexOf(inputContent) === -1;
        });
        /* Clean previous no-result if exist */
        $table.find('tbody .no-result').remove();
        /* Show all rows, hide filtered ones (never do that outside of a demo ! xD) */
        $rows.show();
        $filteredRows.hide();
        /* Prepend no-result row if all rows are filtered */
        if ($filteredRows.length === $rows.length) {
            $table.find('tbody').prepend($('<tr class="no-result text-center"><td colspan="'+ $table.find('.filters th').length +'">No result found</td></tr>'));
        }
    });
    $('.bt_delete').on('click',function(e){
        e.preventDefault();
        var ok = confirm(" Doriți să ștergeți acest șantier ? ");
        var url = $(this).attr("href");
        if(ok)
        {
            window.location.href = url;            
        }
    });

    //modal save bt
    //----------------------------------------------
    //take information and show them in modal
    $('#bt_new_lot').on('click',function(e){
        e.preventDefault();
        
        //Get information  
        var nb_lot = $(this).data('nb_lot');

        //Set infoarmation in modal 
        $("#code_lot").val(nb_lot+1);
                       
        //Show modal 
        $("#lotModal").modal('show');
    });


    $('#bt_save_lot').on('click',function(e){
        add_lot($(this));
    });
    $('#bt_edit_lot').on('click',function(e){
        edit_lot($(this));
    });
    $('#bt_save_category').on('click',function(e){
        add_category($(this));
    });
    $('#bt_edit_category').on('click',function(e){
        edit_category($(this));
    });

    $('#bt_save_poste').on('click', function(e) {
        add_poste($(this));
    });

    $('#bt_edit_poste').on('click', function(e) {
        edit_poste($(this));
    });

    $('#bt_save_subposte').on('click', function(e) {
        add_subposte($(this));
    });

    $('#bt_edit_subposte').on('click', function(e) {
        edit_subposte($(this));
    });
    $('#bt_save_subsubposte').on('click', function(e) {
        add_subsubposte($(this));
    });

    $('#bt_edit_subsubposte').on('click', function(e) {
        edit_subsubposte($(this));
    });
    

    //Save button for note's pop`up
    $('#bt_save_comment').on('click',function(e){
        var id_task = $(this).data('task');
        var id_day = $(this).data('day');

        //Get text timespent note

        var notetoshow = $('#comment_c').val();

        //Set text to table

        $("#"+id_task+"_"+id_day).val(notetoshow);

        $("#comment_c").val('');

        $("#modal_task_comment").modal('hide');
    });

    //MODAL LOT Save button
    //take information and show them in modal
    $('.link_edit_lot').on('click',function(e){
        e.preventDefault();

        //Get information  
        var id_lot = $(this).data('id');

        var label_lot = $(this).data('label');

        var desc_lot = decodeURI($(this).data('desc'));

        var ref_lot = $(this).data('ref');

        var nbchild = $(this).data('nbchild');
        $("#bt_delete_lot").data('nbchild',nbchild);
        

        //Set infoarmation in modal 
        $("#edit_ref_lot").val(ref_lot);
        $("#edit_label_lot").val(label_lot);
        $("#edit_description_lot").val(desc_lot);
        $("#edit_id_lot").val(id_lot);
        $("#edit_header_lot_code span").text(ref_lot);
        
        //Show modal 
        $("#edit_lot_Modal").modal('show');

    });

    //MODAL Category Save button
    //take information and show them in modal
    $('.link_edit_category').on('click',function(e){
        e.preventDefault();

        //Get information  
        var id_category = $(this).data('id');

        var label_category = $(this).data('label');

        var desc_category = decodeURI($(this).data('desc'));

        var ref_category = $(this).data('ref');

        var nbchild = $(this).data('nbchild');
        $("#bt_delete_category").data('nbchild',nbchild);

        //Set infoarmation in modal 
        $("#edit_ref_category").val(ref_category);
        $("#edit_label_category").val(label_category);
        $("#edit_description_category").val(desc_category);
        $("#edit_id_category").val(id_category);
        $("#edit_header_category_code span").text(ref_category);
        
        //Show modal 
        $("#edit_category_Modal").modal('show');

    });

    //MODAL POSTES Save button
    //take information and show them in modal
    $('.link_edit_poste').on('click', function(e) {
        e.preventDefault();

        //Get information
        //--------------------------------------
        var id_poste = $(this).data('id');
        var code_poste = $(this).data('ref');
        var label = $(this).data('label');
        var description = $(this).data('desc');
        var id_category = $(this).data('category');

        var start_date = $(this).data('startdate');
        var end_date = $(this).data('enddate');

        var planned_work_h = $(this).data('plannedworkload');
        var calculated_work_h = $(this).data('calculatedworkload');

        var declared_progress = $(this).data('progress');
        var progress_estimated = $(this).data('progress_estimated');        
       
        //collect dates and transform it in array
        var contacts_executive = $(this).data('contacts_executive').toString();
        $( "#poste_edit_executive_initial").val(contacts_executive);
        contacts_executive = contacts_executive.split(','); 

        var contacts_contributor = $(this).data('contacts_contributor').toString();
        $( "#poste_edit_contributor_initial").val(contacts_contributor);
        contacts_contributor = contacts_contributor.split(',');     



        var zone = $(this).data('zone');

        var factfourn = $(this).data('factfourn').toString();
        $( "#poste_edit_factfourn_initial").val(factfourn);
        factfourn = factfourn.split(',');     

        //desactivare fact except NON affectat
        //------------------------------------
            var factNONaffectat = $( "#poste_edit_factfourn_activat").val();
            factNONaffectat = factNONaffectat.split(','); 
            $( "#poste_edit_factfourn option").each(function(i,el){
                var optionvalue = $(el).val();
                if($.inArray(optionvalue, factNONaffectat) === -1){
                    $( "#poste_edit_factfourn option[value='"+optionvalue+"']" ).attr('disabled',true);
                }    
            });  

        //reactivare fact affected    
        //------------------------------------        
            $.each(factfourn,function(i,el){
                if(el!=''){
                    $( "#poste_edit_factfourn option[value='"+el+"']" ).attr('disabled',false);
                }    
            });  


        var cost_mo = $(this).data('cost_mo');
        var cost_mo_calculated = $(this).data('cost_mo_calculated');
        

        var cost_manual = $(this).data('cost');
        var cost_fourn = $(this).data('cost_fourn');

        var cost_final = $(this).data('cost_final');
        var poste_pv = $(this).data('poste_pv');

        var tx_tva = $(this).data('tx_tva');

        var nbchild = $(this).data('nbchild');
        $("#bt_delete_poste").data('nbchild',nbchild);      

        //Set information in modal
        //---------------------------------------
        $( "#edit_id_poste" ).val( id_poste );
        $( "#edit_code_poste" ).val( code_poste );
        $( "#edit_label_poste" ).val( label );
        $( "#edit_description_poste" ).val( description );
        $( "#edit_id_category_poste" ).val( id_category );
        $( "#edit_start_date_poste" ).val( start_date );
        $( "#edit_end_date_poste" ).val( end_date );

        $( "#edit_planned_work_h_poste" ).val( (planned_work_h * 0.000277777778).toFixed(2) );
        $( "#edit_calculated_work_h_poste" ).val( (calculated_work_h * 0.000277777778).toFixed(2) );        
        
        // $( "#edit_declared_progress_poste" ).data("slider-value",declared_progress);
        $( "#edit_declared_progress_poste" ).bootstrapSlider('setValue',declared_progress);

        if(progress_estimated<80){
            var progress_color = 'progress-bar-success';
        } else if(progress_estimated<100){
            var progress_color = 'progress-bar-warning';
        } else if(progress_estimated ==100){
            var progress_color = 'progress-bar-info';
        } else {
            var progress_color = 'progress-bar-danger';
        }
        //$( "#edit_estimated_progress_poste" ).bootstrapSlider('setValue',progress_estimated);
        $( "#edit_estimated_progress_poste" ).addClass(progress_color);
        //aria-valuenow ?
        if(progress_estimated<=100){
            $( "#edit_estimated_progress_poste" ).css('width',progress_estimated+'%');
        } else {
            $( "#edit_estimated_progress_poste" ).css('width','100%');
        }
        $( "#edit_estimated_progress_poste span" ).text(progress_estimated);

        $( "#id_edit_zone").val(zone);
        $( "#edit_header_poste_code span").text( code_poste );

        //FUNCTION TO SHOW INFORMATION IN EDIT POSTE
        $( "#poste_edit_executive" ).select2("val",contacts_executive);
        $( "#poste_edit_contributor" ).select2("val",contacts_contributor); 
        $( "#poste_edit_factfourn" ).select2("val",factfourn); 



        $( "#edit_poste_price").val(cost_manual);
        $( "#edit_poste_cost_mo").val(cost_mo);
        $( "#edit_poste_cost_mo_calculated").val(cost_mo_calculated);
        
        $( "#edit_poste_cost_fourn").val(cost_fourn);

        //tb costs/pv
        //$( "#TD_edit_poste_cost_mo").text(cost_mo);
        $( "#TD_edit_poste_price").text(cost_manual);
        $( "#TD_edit_poste_price_calculated").text(cost_final);


        $( "#edit_poste_pv").val(poste_pv);

        $( "#edit_poste_tva" ).val(tx_tva);

        //Show modal
        //------------------------------------------
        $( '#posteModal_edit' ).modal("show");

    });

    //MODAL SUBPOSTES Save button
    //take information and show them in modal
    $('.link_edit_subposte').on('click', function(e) {
        e.preventDefault();

        //Get information  
        var id_subposte = $(this).data('id');

        var ref_subposte = $(this).data('ref');

        var taskparent_subposte = $(this).data('taskparent');

        var label_subposte = $(this).data('label');

        var desc_subposte = $(this).data('desc');

        var datec_subposte = $(this).data('datec');

        var usercreat_subposte = $(this).data('usercreat');

        var status_subposte = $(this).data('status');

        var dateo_subposte = $(this).data('dateo');

        var datee_subposte = $(this).data('datee');

        // 
        // TRANFORM SECONDS(PlannedWorklod[seconds] FROM DATABASE ) IN HOURS AND MINUTES 
        // 
        var plannedworkload_subposte = $(this).data('plannedworkload');

        var prog_subposte = $(this).data('prog');

        //collect dates and transform it in array
        var subposte_contacts_executive = $(this).data('subposte_contacts_executive').toString();
        $( "#subposte_edit_executive_initial").val(subposte_contacts_executive);
        subposte_contacts_executive = subposte_contacts_executive.split(','); 

        var subposte_contacts_contributor = $(this).data('subposte_contacts_contributor').toString();
        $( "#subposte_edit_contributor_initial").val(subposte_contacts_contributor);
        subposte_contacts_contributor = subposte_contacts_contributor.split(',');     

        var progress_estimated = $(this).data('progress_estimated');

        var id_zone = $(this).data('id_zone');

        var sousposte_edit_select_unite = $(this).data('unite');

        var sousposte_edit_unite = $(this).data('quantite');

        var factfourn = $(this).data('factfourn').toString();
        $( "#sousposte_edit_factfourn_initial").val(factfourn);
        factfourn = factfourn.split(',');     

        //desactivare fact except NON affectat
        //------------------------------------
            var factNONaffectat = $( "#sousposte_edit_factfourn_activat").val();
            factNONaffectat = factNONaffectat.split(','); 
            $( "#sousposte_edit_factfourn option").each(function(i,el){
                var optionvalue = $(el).val();
                if($.inArray(optionvalue, factNONaffectat) === -1){
                    $( "#sousposte_edit_factfourn option[value='"+optionvalue+"']" ).attr('disabled',true);
                }    
            });  

        //reactivare fact affected    
        //------------------------------------        
            $.each(factfourn,function(i,el){
                if(el!=''){
                    $( "#sousposte_edit_factfourn option[value='"+el+"']" ).attr('disabled',false);
                }    
            });


        var cost_mo = $(this).data('cost_mo');
        var cost_manual = $(this).data('cost');
        var cost_fourn = $(this).data('cost_fourn');
        var cost_final = $(this).data('cost_final');
        var poste_pv = $(this).data('poste_pv');

        var nbchild = $(this).data('nbchild');
        $("#bt_delete_subposte").data('nbchild',nbchild);  

        //Set infoarmation in modal 
        $("#edit_code_subposte").val(ref_subposte);
        $("#edit_label_subposte").val(label_subposte);
        $("#edit_child_subposte").val(taskparent_subposte);
        $("#edit_startDate_subposte").val(dateo_subposte);
        $("#edit_endDate_subposte").val(datee_subposte);
        $("#declaredProgress_subposte" ).bootstrapSlider('setValue',prog_subposte);
        $("#edit_desc_subposte").val(desc_subposte);
        $("#edit_id_subposte").val(id_subposte);
        $("#subposte_edit_executive").val(null).trigger("change");
        $("#subposte_edit_contributor").val(null).trigger("change");
        $("#subposte_id_edit_zone").val(id_zone);
        $("#edit_estimated_progress_subposte").val(progress_estimated);
       

        $("#edit_header_subposte_code span").text( ref_subposte );
        $("#edit_subposte_charge_preveu").val((plannedworkload_subposte * 0.000277777778).toFixed(2));

        //FUNCTION TO SHOW INFORMATION IN EDIT POSTE
        $( "#subposte_edit_executive" ).select2("val",subposte_contacts_executive);
        $( "#subposte_edit_contributor" ).select2("val",subposte_contacts_contributor);
        $( "#sousposte_edit_factfourn" ).select2("val",factfourn); 

        $( "#edit_subposte_price").val(cost_manual);
        $( "#edit_subposte_cost_mo").val(cost_mo);
        $( "#edit_subposte_cost_fourn").val(cost_fourn);

        $( "#sousposte_edit_select_unite").val(sousposte_edit_select_unite);
        $( "#sousposte_edit_unite").val(sousposte_edit_unite);

        //tb costs/pv
        //$( "#TD_edit_subposte_cost_mo").text(cost_mo);
        $( "#TD_edit_subposte_price").text(cost_final);

        //$( "#TD_edit_subposte_pv").text(poste_pv);
        $( "#edit_subposte_pv").val(poste_pv);


        //Show modal 
        $("#edit_subposte_Modal").modal('show');
    });

    //MODAL SUBSUBPOSTES Save button
    //take information and show them in modal
    $('.link_edit_subsubposte').on('click', function(e) {
        e.preventDefault();

        //Get information  
        var id_subsubposte = $(this).data('id');

        var ref_subsubposte = $(this).data('ref');

        var taskparent_subsubposte = $(this).data('taskparent');

        var label_subsubposte = $(this).data('label');

        var desc_subsubposte = $(this).data('desc');

        var datec_subsubposte = $(this).data('datec');

        var usercreat_subsubposte = $(this).data('usercreat');

        var status_subsubposte = $(this).data('status');

        var dateo_subsubposte = $(this).data('dateo');

        var datee_subsubposte = $(this).data('datee');

        var subsubposte_price = $(this).data('subsubposte_price');

        // 
        // TRANFORM SECONDS(PlannedWorklod[seconds] FROM DATABASE ) IN HOURS AND MINUTES 
        // 
        var plannedworkload_subsubposte = $(this).data('plannedworkload');
        var prog_subsubposte = $(this).data('prog');

        //collect dates and transform it in array
        var subsubposte_contacts_executive = $(this).data('subsubposte_contacts_executive').toString();
        $( "#subsubposte_edit_executive_initial").val(subsubposte_contacts_executive);
        subsubposte_contacts_executive = subsubposte_contacts_executive.split(','); 

        var subsubposte_contacts_contributor = $(this).data('subsubposte_contacts_contributor').toString();
        $( "#subsubposte_edit_contributor_initial").val(subsubposte_contacts_contributor);
        subsubposte_contacts_contributor = subsubposte_contacts_contributor.split(',');     

        var progress_estimated = $(this).data('progress_estimated');


        var id_zone = $(this).data('id_zone');

        var unite = $(this).data('unite');

        var quantite = $(this).data('quantite');

        var factfourn = $(this).data('factfourn').toString();
        $( "#soussousposte_edit_factfourn_initial").val(factfourn);
        factfourn = factfourn.split(',');     

        //desactivare fact except NON affectat
        //------------------------------------
            var factNONaffectat = $( "#soussousposte_edit_factfourn_activat").val();
            factNONaffectat = factNONaffectat.split(','); 
            $( "#soussousposte_edit_factfourn option").each(function(i,el){
                var optionvalue = $(el).val();
                if($.inArray(optionvalue, factNONaffectat) === -1){
                //if( $.inArray( optionvalue,factNONaffectat) ){
                    $( "#soussousposte_edit_factfourn option[value='"+optionvalue+"']" ).attr('disabled',true);
                }    
            });  

        //reactivare fact affected    
        //------------------------------------        
            $.each(factfourn,function(i,el){
                if(el!=''){
                    $( "#soussousposte_edit_factfourn option[value='"+el+"']" ).attr('disabled',false);
                }    
            });



        var cost_mo = $(this).data('cost_mo');
        var cost_manual = $(this).data('cost');
        var cost_fourn = $(this).data('cost_fourn');
        var cost_final = $(this).data('cost_final');
        var poste_pv = $(this).data('poste_pv');

        var nbchild = $(this).data('nbchild');
        $("#bt_delete_subsubposte").data('nbchild',nbchild);  

        //Set infoarmation in modal 
        $("#edit_code_subsubposte").val(ref_subsubposte);
        $("#edit_label_subsubposte").val(label_subsubposte);
        $("#edit_child_subsubposte").val(taskparent_subsubposte);
        $("#edit_startDate_subsubposte").val(dateo_subsubposte);
        $("#edit_endDate_subsubposte").val(datee_subsubposte);
        $("#declaredProgress_subsubposte" ).bootstrapSlider('setValue',prog_subsubposte);
        //FOR ESTIMEE
        $("#edit_desc_subsubposte").val(desc_subsubposte);
        $("#edit_id_subsubposte").val(id_subsubposte);
        $("#subsubposte_edit_executive").val(null).trigger("change");
        $("#subsubposte_edit_contributor").val(null).trigger("change");
        $("#subsubposte_id_edit_zone").val(id_zone);
        $("#edit_estimated_progress_subsubposte").val(progress_estimated);
        if(factfourn == ""){
            $("#edit_subsubposte_price").val(subsubposte_price);
        }else{
            $("#edit_subsubposte_price").val(cost_final);
        }
        $("#edit_header_subsubposte_code span").text( ref_subsubposte );
        $("#edit_subsubposte_charge_preveu").val((plannedworkload_subsubposte * 0.000277777778).toFixed(2));
        //FUNCTION TO SHOW INFORMATION IN EDIT POSTE
        $( "#subsubposte_edit_executive" ).select2("val",subsubposte_contacts_executive);
        $( "#subsubposte_edit_contributor" ).select2("val",subsubposte_contacts_contributor);
        $( "#soussousposte_edit_factfourn" ).select2("val",factfourn); 
       

        $( "#edit_subsubposte_price").val(cost_manual);
        $( "#edit_subsubposte_cost_mo").val(cost_mo);
        $( "#edit_subsubposte_cost_fourn").val(cost_fourn);

        $( "#soussousposte_edit_select_unite").val(unite);
        $( "#soussousposte_edit_unite").val(quantite);

        //tb costs/pv
        //$( "#TD_edit_subsubposte_cost_mo").text(cost_mo);
        $( "#TD_edit_subsubposte_price").text(cost_final);

        //$( "#TD_edit_subsubposte_pv").text(poste_pv);
        $( "#edit_subsubposte_pv").val(poste_pv);

        //Show modal 
        $("#edit_subsubposte_Modal").modal('show');
    });


    //DELETE project tree elements
    //-----------------------------------------------
    $('#bt_delete_lot').on('click', function(e) {
        e.preventDefault();
        bt_delete_lot($(this));
    });
    $('#bt_delete_category').on('click', function(e) {
        e.preventDefault();
        bt_delete_category($(this));
    });
    $('#bt_delete_poste').on('click', function(e) {
        e.preventDefault();
        bt_delete_poste($(this));
    });
    $('#bt_delete_subposte').on('click', function(e) {
        e.preventDefault();
        bt_delete_subposte($(this));
    });
    $('#bt_delete_subsubposte').on('click', function(e) {
        e.preventDefault();
        bt_delete_subsubposte($(this));
    });


 








    //INIT select2
    $(".js-example-basic-multiple").select2();

    //**************************************************************************************************************
    //
    //
    //                                              ALL DATE PICKER
    // 
    // 
    //**************************************************************************************************************

        // DATE PICKER FOR POSTE        
        // datetimepciker() --> Date + Time; datepicker() --> Date only;
            
            var now = new Date();

            $("#startDate_poste").datepicker({
                dateFormat: "dd/mm/yy"
            }).on( "change", function() {
                $("#endDate_poste").datepicker( "option", "minDate", getDate(this)  );
            });
            $("#endDate_poste").datepicker({
                dateFormat: "dd/mm/yy"
            }).on( "change", function() {
                $("#startDate_poste").datepicker( "option", "maxDate", getDate(this)  );
            });


        // DATE PICKER FOR EDIT_POSTE
        // datetimepciker() --> Date + Time; datepicker() --> Date only;
        // 
            $("#edit_start_date_poste").datepicker({
                dateFormat: "dd/mm/yy"
            }).on( "change", function() {
                $("#edit_end_date_poste").datepicker( "option", "minDate", getDate(this)  );
            });
            $("#edit_end_date_poste").datepicker({
                dateFormat: "dd/mm/yy"
            }).on( "change", function() {
                $("#edit_start_date_poste").datepicker( "option", "maxDate", getDate(this)  );
            });

        // DATE PICKER FOR SUBPOSTE
        // datetimepciker() --> Date + Time; datepicker() --> Date only;
        // 

            $("#startDate_subposte").datepicker({ dateFormat: "dd/mm/yy"}).on( "change", function() {
                $("#endDate_subposte").datepicker( "option", "minDate", getDate(this)  );
            });
            $("#endDate_subposte").datepicker({ dateFormat: "dd/mm/yy"}).on( "change", function() {
                $("#startDate_subposte").datepicker( "option", "maxDate", getDate(this)  );
            });


        // DATE PICKER FOR EDIT_SUBPOSTE
        // datetimepciker() --> Date + Time; datepicker() --> Date only;
        // 

            $("#edit_startDate_subposte").datepicker({ dateFormat: "dd/mm/yy"}).on( "change", function() {
                $("#edit_endDate_subposte").datepicker( "option", "minDate", getDate(this)  );
            });
            $("#edit_endDate_subposte").datepicker({ dateFormat: "dd/mm/yy"}).on( "change", function() {
                $("#edit_startDate_subposte").datepicker( "option", "maxDate", getDate(this)  );
            });

        // DATE PICKER FOR SUBSUBPOSTE
        // datetimepciker() --> Date + Time; datepicker() --> Date only;
        // 

            $("#startDate_subsubposte").datepicker({ dateFormat: "dd/mm/yy"}).on( "change", function() {
                $("#endDate_subsubposte").datepicker( "option", "minDate", getDate(this)  );
            });
            $("#endDate_subsubposte").datepicker({ dateFormat: "dd/mm/yy"}).on( "change", function() {
                $("#startDate_subsubposte").datepicker( "option", "maxDate", getDate(this)  );
            });

        // DATE PICKER FOR EDIT_SUBSUBPOSTE
        // datetimepciker() --> Date + Time; datepicker() --> Date only;
        // 
            $("#edit_startDate_subsubposte").datepicker({ dateFormat: "dd/mm/yy"}).on( "change", function() {
                $("#edit_endDate_subsubposte").datepicker( "option", "minDate", getDate(this)  );
            });
            $("#edit_endDate_subsubposte").datepicker({ dateFormat: "dd/mm/yy"}).on( "change", function() {
                $("#edit_startDate_subsubposte").datepicker( "option", "maxDate", getDate(this)  );
            });


        // DATE PICKER FOR primes.php 
        // 
            $("#prime_from").datepicker({
                dateFormat: "dd/mm/yy",
                changeMonth: true,
                changeYear: true,
                showButtonPanel: true,
                beforeShow: function(input, inst) {
                    $(inst.dpDiv[0]).addClass('calendar_prime');
                },
                onClose: function(dateText, inst) {
                    var prime_from_date =  new Date(inst.selectedYear , inst.selectedMonth , 1);
                    $(this).datepicker('setDate',prime_from_date);
                    $("#prime_to").datepicker( "option", "minDate", prime_from_date );
                }
            }).on( "change", function() {
            });

            $("#prime_to").datepicker({
                dateFormat: "dd/mm/yy",
                changeMonth: true,
                changeYear: true,
                showButtonPanel: true,
                beforeShow: function(input, inst) {
                    $(inst.dpDiv[0]).addClass('calendar_prime');
                },
                onClose: function(dateText, inst) { 
                    // var lastDay = new  Date(inst.selectedYear, inst.selectedMonth + 1, 0);
                    var prime_to_date =  new Date(inst.selectedYear, inst.selectedMonth + 1, 0);
                    $(this).datepicker('setDate', prime_to_date);
                    $("#prime_from").datepicker( "option", "maxDate", prime_to_date );
                }
            }).on( "change", function() {
            });




    //DATA TABLE FOR Contact /abcvc/projet/contact.php?id=1

    $('#table_contacts').DataTable({
        responsive: true,
        "language": {
                    "sProcessing":     "Tratament in curs...",
                    "sSearch":         "Caută&nbsp;:",
                    "sLoadingRecords": "Se încarcă înregistrările...",
                    "oPaginate": {
                        "sFirst":      "Prima",
                        "sPrevious":   "Înapoi",
                        "sNext":       "Următoarea",
                        "sLast":       "Ultima"
                    },
                    "oAria": {
                        "sSortAscending":  ": Sortează crescător",
                        "sSortDescending": ": Sortează descrescător"
                    }
        }
    });

    //modal task  timespent notes 
    $('.bt_timespent_note').on('click',function(e){
        e.preventDefault();

        //recup data attr...

            var id_task = $(this).data('task');
            var id_day = $(this).data('day');

            var isnote = $(this).data('isnote');
            
            //empty
            $("#comment_c").val('');

            //new note
            if(isnote==0){

            //edit note    
            } else {
                var note = $("#"+id_task+"_"+id_day).val();

                //insert note
                $("#comment_c").val(note);
            }


        //Set data attribute

            $("#bt_save_comment").data('task',id_task); 
            $("#bt_save_comment").data('day',id_day);

        //Show modal 
        $("#modal_task_comment").modal('show');
    });

    // PROGRESS BAR BOOTSTRAP TO SELECT AVANCEMENT 
        // ADD POSTE
        /*    $("#declared_progress_poste").bootstrapSlider({
            tooltip: 'always',
            ticks: [0, 25, 50, 75, 100],
            ticks_positions: [0, 25, 50, 75, 100],
            ticks_labels: ['0 %', '25 %', '50 %', '75 %', '100 %']
            });

            $("#estimated_progress_poste").bootstrapSlider({
            tooltip: 'always',
            ticks: [0, 25, 50, 75, 100],
            ticks_positions: [0, 25, 50, 75, 100],
            ticks_labels: ['0 %', '25 %', '50 %', '75 %', '100 %']
            });
        */    

        // EDIT POSTE
            $("#edit_declared_progress_poste").bootstrapSlider({
            tooltip: 'show',
            ticks: [0, 25, 50, 75, 100],
            ticks_positions: [0, 25, 50, 75, 100],
            ticks_labels: ['0 %', '25 %', '50 %', '75 %', '100 %']
            });

            /*$("#edit_estimated_progress_poste").bootstrapSlider({
            tooltip: 'hide',
            ticks: [0, 25, 50, 75, 100],
            ticks_positions: [0, 25, 50, 75, 100],
            ticks_labels: ['0 %', '25 %', '50 %', '75 %', '100 %']
            }); */ 

        // EDIT SUBPOSTE
        /*    $("#declaredProgress_subposte").bootstrapSlider({
            tooltip: 'always',
            ticks: [0, 25, 50, 75, 100],
            ticks_positions: [0, 25, 50, 75, 100],
            ticks_labels: ['0 %', '25 %', '50 %', '75 %', '100 %']
            });

            $("#edit_estimated_progress_subposte").bootstrapSlider({
            tooltip: 'always',
            ticks: [0, 25, 50, 75, 100],
            ticks_positions: [0, 25, 50, 75, 100],
            ticks_labels: ['0 %', '25 %', '50 %', '75 %', '100 %']
            });

        // EDIT SUBSUBPOSTE
            $("#declaredProgress_subsubposte").bootstrapSlider({
            tooltip: 'always',
            ticks: [0, 25, 50, 75, 100],
            ticks_positions: [0, 25, 50, 75, 100],
            ticks_labels: ['0 %', '25 %', '50 %', '75 %', '100 %']
            });

            $("#edit_estimated_progress_subsubposte").bootstrapSlider({
            tooltip: 'always',
            ticks: [0, 25, 50, 75, 100],
            ticks_positions: [0, 25, 50, 75, 100],
            ticks_labels: ['0 %', '25 %', '50 %', '75 %', '100 %']
            });
        */    

    //SYNC TIME PER YEAR 
    $('#total_prime_year').text($('#total_prime_year_bottom').text());

    //------------------------------------------------------------------------------------------
    //MODAL timespent /abcvc/projet/monthlyinput.php
    //------------------------------------------------------------------------------------------
    $('.timespent_per_task_day').on('click',function(e){
        e.preventDefault();
        
        //Get information  
        var task_id = $(this).data('task_id');
        var task_day = $(this).data('day');
        var task_ref = $(this).data('task_ref');
        var task_label = $(this).data('task_label');                
        var task_date_start = $(this).data('date_start');
        var task_debut = $(this).data('debut');
        var task_debut_sec = $(this).data('debut_sec');
        
        var task_fin = $(this).data('fin');
        var task_fin_sec = $(this).data('fin_sec');

        var task_duration_sec = $(this).data('duration_sec');

        var task_type = $(this).data('type');
        var user = $(this).data('user');

        var mode = $(this).data('mode');
        var timespentid = $(this).data('timespentid');
        
        //Set infoarmation in modal 
        $("#timespent_header_code div.ref_task").text(task_ref);
        $("#timespent_header_code div.label_task").text(task_label);

        if(task_debut_sec!='') {
            $('#edit_tempspasse_de').val(task_debut);
            //var heure_de = task_debut_sec + task_duration_sec;
            //var hours = Math.floor(heure_de / 3600) < 10 ? ("00" + Math.floor(heure_de / 3600)).slice(-2) : Math.floor(heure_de / 3600);
            //var minutes = ("00" + Math.floor((heure_de % 3600) / 60)).slice(-2);
            $('#edit_tempspasse_a').val(task_fin);
        } else {
            $('#edit_tempspasse_de').val('7:00');
            $('#edit_tempspasse_a').val('15:00');
        }

        $('#edit_desc_tempspasse').val( $('#txt-'+task_id+'-'+task_day).val() );

        $('#edit_type_tempspasse').val( task_type );

        $('#qui_tempspasse').text( $('#perioduser option:selected').text() );        


        var d2 = new Date(task_date_start*1000);
        $('#jour_tempspasse').text( ("00" + d2.getDate()).slice(-2) + '/' + ("00" + (d2.getMonth()+1)).slice(-2) + '/' + d2.getFullYear());

        //injection bt save
        $('#bt_save_timespent').data('task_id',task_id);
        $('#bt_save_timespent').data('task_day',task_id+'-'+task_day);
        $('#bt_save_timespent').data('task_timestamp',task_date_start);
        $('#bt_save_timespent').data('user',user);
        $('#bt_save_timespent').data('mode',mode);
        $('#bt_save_timespent').data('timespentid',timespentid);
        
        
        

        var ok_h_m = '';
        if( (edit_tempspasse_de.val()!='') && (edit_tempspasse_a.val()!='') ){
            ok_h_m = calcul_interval_TO_h_m_hsup(edit_tempspasse_de, edit_tempspasse_a);
        }
        $("#preview_tempspasse").html(ok_h_m);

        
        //Show modal 
        $("#timespent_Modal").modal('show');

    });


    var edit_tempspasse_de = $('#edit_tempspasse_de');
    var edit_tempspasse_a = $('#edit_tempspasse_a');
    if(edit_tempspasse_de.length>0){
        $.timepicker.timeRange(
            edit_tempspasse_de,
            edit_tempspasse_a,
            {
                minInterval: (1000*60*60), // 1hr
                timeFormat: 'HH:mm',
                start: {},  // start picker options
                end: {},    // end picker options
                hourMin: 7,
                hourMax: 21
            }
        );  
    }    
      
     
   /*$("#edit_estimated_progress_task").bootstrapSlider({
        tooltip: 'always',
        ticks: [0, 25, 50, 75, 100],
        ticks_positions: [0, 25, 50, 75, 100],
        ticks_labels: ['0 %', '25 %', '50 %', '75 %', '100 %']
    });*/


    $('#edit_tempspasse_de, #edit_tempspasse_a').on('change', function(e) {
        if( (edit_tempspasse_de.val()!='') && (edit_tempspasse_a.val()!='') ){
            var ok_h_m = calcul_interval_TO_h_m_hsup(edit_tempspasse_de, edit_tempspasse_a);
            $("#preview_tempspasse").html(ok_h_m);
        }
    });


    $('#bt_save_timespent').on('click', function(e) {
        e.preventDefault();

        //Get information  
        var heure_de = $('#edit_tempspasse_de').val();
        var heure_a = $('#edit_tempspasse_a').val();
        var task_day = $(this).data('task_day');
        var mode = $(this).data('mode');
        var timespentid = $(this).data('timespentid');
        var task_type =  $('#edit_type_tempspasse').val();
        if( (task_type== null) || (heure_de=='') || (heure_a=='') ){
            alert('Pentru a putea înregistra timpul trebuie să completați câmpurile obligatorii')
            return true;
        }

        var task_id = $(this).data('task_id');
        var user = $(this).data('user');
        var task_timestamp = $(this).data('task_timestamp');

        var de_minutes_calc = heure_de.split(':');
        de_minutes_calc = parseInt(de_minutes_calc[0])*60 + parseInt(de_minutes_calc[1]);

        var a_minutes_calc = heure_a.split(':');
        a_minutes_calc = parseInt(a_minutes_calc[0])*60 + parseInt(a_minutes_calc[1]);

        var ok_duration_min = a_minutes_calc - de_minutes_calc;

        //+5h ->1h repas ?
        if(ok_duration_min>=300){
            ok_duration_min -= 60;
        }
        var ok_duration_sec = ok_duration_min*60; 

        var h = Math.floor(ok_duration_min / 60);
        var m = ok_duration_min % 60;
        h = h < 10 ? '0' + h : h;
        m = m < 10 ? '0' + m : m;
        
        $('#hrs-'+task_day).val(h+':'+m);
        $('#txt-'+task_day).val( $('#edit_desc_tempspasse').val());

        var data = {
            'fk_task' : task_id,
            'task_date' : task_timestamp,
            'heure_de' : heure_de,
            'task_datehour' : task_timestamp,
            'task_date_withhour' : 0,
            'task_duration' : ok_duration_sec,
            'task_type' : task_type,
            'fk_user' : user,
            
            'thm' : 0,
            'note' : $('#edit_desc_tempspasse').val(),
            'mode' : mode,
            'timespentid' : timespentid
        };

        $.ajax({ 
            url: '/abcvc/projet/monthlyinput.php?action=ajax_add_time', 
            dataType: 'json',
            type: 'POST',
            data : data,
            success: function(data){
                if(data.statut=="ok"){
                    //$("#timespent_Modal").modal('hide');
                    // post page 
                    document.selectperiod.submit();
                } else {
                    alert(data.message);
                }   
            }
        });
       
    });   

    $('.bt_removetimespent').on('click', function(e) {
        e.preventDefault();
        if(confirm('Confirmez-vous la supression de ce temps affecté ?')){

            var timespentid = $(this).data('timespentid');
            var data = {
                'timespentid' : timespentid
            };

            $.ajax({ 
                url: '/abcvc/projet/monthlyinput.php?action=ajax_del_time', 
                dataType: 'json',
                type: 'POST',
                data : data,
                success: function(data){
                    if(data.statut=="ok"){
                        //$("#timespent_Modal").modal('hide');
                        // post page 
                        document.selectperiod.submit();
                    } else {
                        alert(data.message);
                    }   
                }
            });
        }
    });



    // ---------------------------------------------------------------------------
    // TEST controle input monetaire
    // ---------------------------------------------------------------------------
    $('.currency').on('keydown', function(e) {
      if (this.selectionStart || this.selectionStart == 0) {
        // selectionStart won't work in IE < 9
        
        var key = e.which;
        var prevDefault = true;
        
        var thouSep = " ";  // your seperator for thousands
        var deciSep = ",";  // your seperator for decimals
        var deciNumber = 2; // how many numbers after the comma
        
        var thouReg = new RegExp(thouSep,"g");
        var deciReg = new RegExp(deciSep,"g");
        
        function spaceCaretPos(val, cPos) {
            /// get the right caret position without the spaces
            
            if (cPos > 0 && val.substring((cPos-1),cPos) == thouSep)
              cPos = cPos-1;
            
            if (val.substring(0,cPos).indexOf(thouSep) >= 0) {
              cPos = cPos - val.substring(0,cPos).match(thouReg).length;
            }
            
            return cPos;
        }
        
        function spaceFormat(val, pos) {
            /// add spaces for thousands
            
            if (val.indexOf(deciSep) >= 0) {
                var comPos = val.indexOf(deciSep);
                var int = val.substring(0,comPos);
                var dec = val.substring(comPos);
            } else{
                var int = val;
                var dec = "";
            }
            var ret = [val, pos];
            
            if (int.length > 3) {
                
                var newInt = "";
                var spaceIndex = int.length;
                
                while (spaceIndex > 3) {
                    spaceIndex = spaceIndex - 3;
                    newInt = thouSep+int.substring(spaceIndex,spaceIndex+3)+newInt;
                    if (pos > spaceIndex) pos++;
                }
                ret = [int.substring(0,spaceIndex) + newInt + dec, pos];
            }
            return ret;
        }
        
        $(this).on('keyup', function(ev) {
            
            if (ev.which == 8) {
                // reformat the thousands after backspace keyup
                
                var value = this.value;
                var caretPos = this.selectionStart;
                
                caretPos = spaceCaretPos(value, caretPos);
                value = value.replace(thouReg, '');
                
                var newValues = spaceFormat(value, caretPos);
                this.value = newValues[0];
                this.selectionStart = newValues[1];
                this.selectionEnd   = newValues[1];
            }
        });
        
        if ((e.ctrlKey && (key == 65 || key == 67 || key == 86 || key == 88 || key == 89 || key == 90)) ||
           (e.shiftKey && key == 9)) // You don't want to disable your shortcuts!
            prevDefault = false;
        
        if ((key < 37 || key > 40) && key != 8 && key != 9 && prevDefault) {
            e.preventDefault();
            
            if (!e.altKey && !e.shiftKey && !e.ctrlKey) {
            
                var value = this.value;
                if ((key > 95 && key < 106)||(key > 47 && key < 58) ||
                  (deciNumber > 0 && (key == 110 || key == 188 || key == 190))) {
                    
                    var keys = { // reformat the keyCode
              48: 0, 49: 1, 50: 2, 51: 3,  52: 4,  53: 5,  54: 6,  55: 7,  56: 8,  57: 9,
              96: 0, 97: 1, 98: 2, 99: 3, 100: 4, 101: 5, 102: 6, 103: 7, 104: 8, 105: 9,
              110: deciSep, 188: deciSep, 190: deciSep
                    };
                    
                    var caretPos = this.selectionStart;
                    var caretEnd = this.selectionEnd;
                    
                    if (caretPos != caretEnd) // remove selected text
                    value = value.substring(0,caretPos) + value.substring(caretEnd);
                    
                    caretPos = spaceCaretPos(value, caretPos);
                    
                    value = value.replace(thouReg, '');
                    
                    var before = value.substring(0,caretPos);
                    var after = value.substring(caretPos);
                    var newPos = caretPos+1;
                    
                    if (keys[key] == deciSep && value.indexOf(deciSep) >= 0) {
                        if (before.indexOf(deciSep) >= 0) newPos--;
                        before = before.replace(deciReg, '');
                        after = after.replace(deciReg, '');
                    }
                    var newValue = before + keys[key] + after;
                    
                    if (newValue.substring(0,1) == deciSep) {
                        newValue = "0"+newValue;
                        newPos++;
                    }
                    
                    while (newValue.length > 1 && newValue.substring(0,1) == "0" && newValue.substring(1,2) != deciSep) {
                        newValue = newValue.substring(1);
                        newPos--;
                    }
                    
                    if (newValue.indexOf(deciSep) >= 0) {
                        var newLength = newValue.indexOf(deciSep)+deciNumber+1;
                        if (newValue.length > newLength) {
                          newValue = newValue.substring(0,newLength);
                        }
                    }
                    
                    newValues = spaceFormat(newValue, newPos);
                    
                    this.value = newValues[0];
                    this.selectionStart = newValues[1];
                    this.selectionEnd   = newValues[1];
                }
            }
        }
        
        $(this).on('blur', function(e) {
            
            if (deciNumber > 0) {
                var value = this.value;
                
                var noDec = "";
                for (var i = 0; i < deciNumber; i++) noDec += "0";
                
                if (value == "0" + deciSep + noDec) {
            this.value = ""; //<-- put your default value here
          } else if(value.length > 0) {
                    if (value.indexOf(deciSep) >= 0) {
                        var newLength = value.indexOf(deciSep) + deciNumber + 1;
                        if (value.length < newLength) {
                          while (value.length < newLength) value = value + "0";
                          this.value = value.substring(0,newLength);
                        }
                    }
                    else this.value = value + deciSep + noDec;
                }
            }
        });
      }
      
    });

    //move header
    //--------------------
    jQuery('#container_header_presences_bottom #header_presences').detach().appendTo(jQuery('#container_header_presences_top'));

});
//DOM READY
//=========================================================================



//**************************************************************************************************************
//
//
//                                                  temps passes
// 
// 
//**************************************************************************************************************

    function calcul_interval_TO_h_m_hsup(edit_tempspasse_de, edit_tempspasse_a) {

        var nb_min = edit_tempspasse_de.val().split(':');
        var nb_min_de = parseInt(nb_min[0])*60 + parseInt(nb_min[1]);
        var nb_min = edit_tempspasse_a.val().split(':');
        var nb_min_a = parseInt(nb_min[0])*60 + parseInt(nb_min[1]);

        var delta_min = nb_min_a - nb_min_de;
        
        //+7h legales, heures sup ?
        var delta_heuresup = 0;
        var hsup = 0;
        var msup = 0;

        var delta_heurerepas = '';
        //+5h ->1h repas ?
        var hrepas = 0;
        if(delta_min>=300){
            delta_heurerepas = '1h pauză / ';
            var hrepas = 60;
            delta_min -= 60;
        }


        //420 min -> 7h -> 6h+1h repas
        //480 8h - 7h / 1h repas
        if(delta_min >= (480) ){
            delta_heuresup = delta_min-(480 - hrepas);
            delta_min = 420;// - hrepas);
            hsup = Math.floor(delta_heuresup / 60);
            msup = delta_heuresup % 60;
            hsup = hsup < 10 ? '0' + hsup : hsup;
            msup = msup < 10 ? '0' + msup : msup;
        }            
        var h = Math.floor(delta_min / 60);
        var m = delta_min % 60;
        h = h < 10 ? '0' + h : h;
        m = m < 10 ? '0' + m : m;
        var ok_h_m = '&nbsp;&nbsp; Ore de lucru: '+ h+':'+m;

        if( (delta_heurerepas!='') || (delta_heuresup>0) ){
            ok_h_m += ' ( ';
        }
        if(delta_heurerepas!=''){
            ok_h_m += delta_heurerepas;
        }
        if(delta_heuresup>0){
            ok_h_m += 'Ore suplimentare: ' + hsup + ':' + msup;
        }
        if( (delta_heurerepas!='') || (delta_heuresup>0) ){
            ok_h_m += ' ) ';
        }

        return ok_h_m;
    }







//**************************************************************************************************************
//
//
//                                                  utilitaires
// 
// 
//**************************************************************************************************************


    function LastDayOfMonth(Year, Month) {
        return new Date( (new Date(Year, Month,1))-1 );
    }
    function getDate( element ) {
        var date;
        try {
            date = $.datepicker.parseDate( "dd/mm/yy", element.value );
        } catch( error ) {
            date = null;
        }
        return date;
    }  

        

//**************************************************************************************************************
//
//
//                                                  ADD
// 
// 
//**************************************************************************************************************

    //ADD LOT
    //----------------------------------------------
    function add_lot(bt){
        // hide bt
        $(bt).hide();

        var valid = true;
        
        var description = $( "#description_lot" ).val();
        var code = $( "#code_lot" ).val();
        var label = $( "#label_lot" ).val();

        if( (label=='') || (code=='') ){
            valid = false;
        }

        if(!valid){
            alert('Please fill all required field.');
            $(bt).show();
        } else {
            var data_add = {
                'id_projet':id_projet,
                'label':label,
                'code':code,
                'description':description
            };  

            if ( valid ) {
                $.ajax({ 
                    url: '/abcvc/projet/card.php?actionajax=ajax_add_lot', 
                    dataType: 'json',
                    type: 'POST',
                    data : data_add,
                    success: function(data){
                        if(data.statut=="ok"){
                            $('#lotModal').modal("hide");
                            // refresh page 
                            document.location.reload();
                        } else {
                            //show button
                            $(bt).show();

                            alert(data.message);
                        }   
                    }
                });
            }
       
        }




        return valid;
    }

    //ADD CATEGORY
    //----------------------------------------------
    function add_category(bt){
        // hide bt
        $(bt).hide();


        var valid = true;
        
        var description = $( "#description_category" ).val();
        //var code = $( "#code_category" ).val();
        var lot = $( "#lot_category" ).val();
        var label = $( "#label_category" ).val();
        if( (label == '') || (lot == '') ){
            valid = false;
        }

        //calculate code correct
        var option_el = $( "#lot_category option[value='"+lot+"']" );
        var ref_lot = option_el.data('code_lot');
        var nb_categorie = option_el.data('nb_categorie');

        //final
        var code = ref_lot+'.'+(nb_categorie+1);
        //console.log(ref_lot,nb_categories,code);


        if(!valid){
            alert( "Please fill all the required fields!" );
            $(bt).show();
        } else {
            var data_add = {
                'id_projet':id_projet,
                'label':label,
                'code':code,
                'lot':lot,
                'description':description
            };  
            console.log(data_add);
            
            if ( valid ) {
                $.ajax({ 
                    url: '/abcvc/projet/card.php?actionajax=ajax_add_category', 
                    dataType: 'json',
                    type: 'POST',
                    data : data_add,
                    success: function(data){
                        console.log(data);
                        if(data.statut=="ok"){
                            $('#categoryModal').modal("hide");
                            console.log("Added");
                            
                            //TODO inject new / refresh page ?
                            document.location.reload();
                        } else {
                            //show button
                            $(bt).show();

                            alert(data.message);
                        }   
                    }
                });
            }
        
        }  
    }

    //ADD POSTE
    function add_poste(bt) {
        //hide button
        //$(bt).hide();

        var valid = true;
        //var code_poste = $("#code_poste").val();
        var label = $( "#label_poste" ).val();
        var id_category = $( "#id_category" ).val();
        var start_date = $( "#startDate_poste" ).val();
        var end_date = $( "#endDate_poste" ).val();
        var plannedworkload_poste = $( "#planned_work_poste_h" ).val();
        var declared_progress = $( "#declared_progress_poste" ).val();
        var description = $( "#desc_poste" ).val();
        var executive = $( "#poste_add_executive" ).val();
        var contributor = $( "#poste_add_contributor" ).val();
        var id_zone = 0;//$("#id_zone").val();
        var price = $("#poste_price").val();
        var estimated_progress = $("#estimated_progress_poste").val();
        var add_factfourn = $("#poste_add_factfourn").val();

        if ( ( label == '' ) || ( id_category == '' ) || ( plannedworkload_poste == '' ) /*|| ( id_zone == '' )*/ ) {
            valid = false;
        }


        //calculate code correct
        var option_el = $( "#id_category option[value='"+id_category+"']" );
        var ref_lot = option_el.data('code_lot');
        var ref_categorie = option_el.data('code_categorie');
        var nb_poste = option_el.data('nb_poste');

        //final
        var code_poste = ref_categorie+'.'+(nb_poste+1);

         // if(add_factfourn == ''){
         //     $("#poste_price").attr('disabled',false);
         
         // }else{
         //     $("#poste_price").attr('disabled',true);
         // }

        var cost_mo = $("#poste_add_price_main").val();
        console.log("Cost manufactura, INITIAL E NIMIC:"+cost_mo);


        if ( !valid ) {
            alert( "Please fill all the required fields!" ); 
            console.log("intra aici");
            $( bt ).show();
        } else {
            var data_add = {
                'code_poste' : code_poste,
                'id_projet' : id_projet,
                'label' : label,
                'id_category' : id_category, 
                'start_date' : start_date,
                'end_date' : end_date,
                'plannedworkload_poste' : plannedworkload_poste,
                'declared_progress' : declared_progress,
                'executive' : executive,
                'contributor' : contributor,
                'description' : description,
                'id_zone' : id_zone,
                'price' : price,
                'estimated_progress' : estimated_progress,
                'add_factfourn' : add_factfourn,
                'cost_mo' : cost_mo,
            }
            console.log(data_add);

            if( valid ) {
                $.ajax( {
                    url:'/abcvc/projet/card.php?actionajax=ajax_add_poste',
                    dataType: 'json',
                    type: 'POST',
                    data: data_add,
                    success: function( data ) {
                        if( data.statut == "ok" ) {
                            $("#posteModal").modal("hide");

                            document.location.reload();
                        } else {
                            //show button
                            $(bt).show();

                            alert( data.message );
                        }
                    }
                });
            }
           
        }
    }

    //ADD SUBPOSTE
    function add_subposte(bt) {
            //hide button
            $(bt).hide();

            var valid = true;

            //var code = $( "#code_subposte" ).val();
            var label = $( "#label_subposte" ).val();
            var child = $( "#child_subposte" ).val();
            var start_date = $( "#startDate_subposte" ).val();
            var end_date = $( "#endDate_subposte" ).val();
            var planned_work_h = $( "#plannedWork_subposte_h" ).val();
            var planned_work_m = $( "#plannedWork_subposte_m" ).val();
            var declared_progress = $( "#declaredProgress_subposte" ).val();
            var description = $( "#desc_subposte" ).val();


            // var executive = $( "#subposte_add_executive" ).val();
            // var contributor = $( "#subposte_add_contributor" ).val();
            //extract CC AND CE
            //
            var executive = $( "#child_subposte option[value='"+child+"']" ).data('contacts_executive');
            //var id_zone = $("#id_zone").val(id_zone);
            var contributor = $( "#child_subposte option[value='"+child+"']" ).data('contacts_contributor');
            //var id_zone = $("#id_zone").val(id_zone);


            var price = $("#subposte_price").val();
            var estimated_progress = $("#estimated_progress_subposte").val();
            var add_factfourn = $("#sousposte_add_factfourn").val();

            //extract id_zone
            var id_zone = $( "#child_subposte option[value='"+child+"']" ).data('id_zone');
            //var id_zone = $("#id_zone").val(id_zone);

            var sousposte_add_unite = $("#sousposte_add_unite").val();

            var sousposte_select_unite = $("#sousposte_select_unite").val();



            if ( ( label == '' ) || ( child == '' ) || ( planned_work_h == '' ) ) {
                valid = false;
            }


            //calculate code correct
            var option_el = $( "#child_subposte option[value='"+child+"']" );
            var ref_lot = option_el.data('code_lot');
            var ref_categorie = option_el.data('code_categorie');
            var ref_poste = option_el.data('code_poste');
            var nb_subposte = option_el.data('nb_subposte');

            //final
            var code = ref_poste+'.'+(nb_subposte+1);

            if ( !valid ) {
                alert( "Please fill all the required fields!" ); 
                $( bt ).show();
            } else {
                var data_add = {
                    'id_projet' : id_projet,
                    'code' : code,
                    'label' : label,
                    'child' : child, 
                    'start_date' : start_date,
                    'end_date' : end_date,
                    'planned_work_h' : planned_work_h,
                    'planned_work_m' : planned_work_m,
                    'declared_progress' : declared_progress,
                    'description' : description,
                    'executive' : executive,
                    'contributor' : contributor,
                    //'price' : price,
                    'estimated_progress' : estimated_progress,
                    'id_zone' : id_zone,
                    'add_factfourn' : add_factfourn,
                    'sousposte_add_unite' : sousposte_add_unite,
                    'sousposte_select_unite' : sousposte_select_unite
                }

                if( valid ) {
                    $.ajax( {
                        url:'/abcvc/projet/card.php?actionajax=ajax_add_subposte',
                        dataType: 'json',
                        type: 'POST',
                        data: data_add,
                        success: function( data ) {
                            if( data.statut == "ok" ) {
                                $("#subposteModal").modal("hide");

                                document.location.reload();
                            } else {
                                //show button
                                $(bt).show();

                                alert( data.message );
                            }
                        }
                    });
                }
            }
    }

    //ADD SUB-SUB POSTE
    function add_subsubposte(bt) {
            //hide button
            $(bt).hide();

            var valid = true;
            //var code = $( "#code_subsubposte" ).val();
            var label = $( "#label_subsubposte" ).val();
            var child = $( "#child_subsubposte" ).val();
            var start_date = $( "#startDate_subsubposte" ).val();
            var end_date = $( "#endDate_subsubposte" ).val();
            var planned_work_h = $( "#plannedWork_subsubposte_h" ).val();
            var planned_work_m = $( "#plannedWork_subsubposte_m" ).val();
            var declared_progress = $( "#declaredProgress_subsubposte" ).val();
            var description = $( "#desc_subsubposte" ).val();



            // var executive = $( "#subsubposte_add_executive" ).val();
            // var contributor = $( "#subsubposte_add_contributor" ).val();
            // EXTRACT CC AND CE
            var executive = $( "#child_subsubposte option[value='"+child+"']" ).data('contacts_executive');
            //var id_zone = $("#id_zone").val(id_zone);
            var contributor = $( "#child_subsubposte option[value='"+child+"']" ).data('contacts_contributor');
            //var id_zone = $("#id_zone").val(id_zone);



            var price = $("#subsubposte_price").val();
            var estimated_progress = $("#estimated_progress_subsubposte").val();
            var add_factfourn = $("#soussousposte_add_factfourn").val();
            //extract id_zone
            //
            var id_zone = $( "#child_subsubposte option[value='"+child+"']" ).data('id_zone');
            //var id_zone = $("#id_zone").val(id_zone);

            var soussousposte_add_unite = $("#soussousposte_add_unite").val();

            var soussousposte_select_unite = $("#soussousposte_select_unite").val();

            if ( ( label == '' ) || ( child == '' ) || ( planned_work_h == '' )) {
                valid = false;
            }


            //calculate code correct
            var option_el = $( "#child_subsubposte option[value='"+child+"']" );
            var ref_lot = option_el.data('code_lot');
            var ref_categorie = option_el.data('code_categorie');
            var ref_poste = option_el.data('code_poste');
            var ref_subposte = option_el.data('code_subposte');
            var nb_subsubposte = option_el.data('nb_subsubposte');

            //final
            var code = ref_subposte+'.'+(nb_subsubposte+1);

            if ( !valid ) {
                alert( "Please fill all the required fields!" ); 
                $( bt ).show();
            } else {
                var data_add = {
                    'id_projet' : id_projet,
                    'code' : code,
                    'label' : label,
                    'child' : child, 
                    'start_date' : start_date,
                    'end_date' : end_date,
                    'planned_work_h' : planned_work_h,
                    'planned_work_m' : planned_work_m,
                    'declared_progress' : declared_progress,
                    'description' : description,
                    'executive' : executive,
                    'contributor' : contributor,
                    'price' : price,
                    'estimated_progress' : estimated_progress,
                    'id_zone' : id_zone,
                    'add_factfourn' : add_factfourn,
                    'soussousposte_add_unite' : soussousposte_add_unite,
                    'soussousposte_select_unite' : soussousposte_select_unite

                }
                console.log(data_add);

                if( valid ) {
                    $.ajax( {
                        url:'/abcvc/projet/card.php?actionajax=ajax_add_subsubposte',
                        dataType: 'json',
                        type: 'POST',
                        data: data_add,
                        success: function( data ) {
                            console.log(data);
                            if( data.statut == "ok" ) {
                                $("#subsubposteModal").modal("hide");
                                console.log("Added");

                                document.location.reload();
                            } else {
                                //show button
                                $(bt).show();

                                alert( data.message );
                            }
                        }
                    });
                }
            }
    }


//**************************************************************************************************************
//
//
//                                               EDIT & UPDATE 
// 
// 
//**************************************************************************************************************

    //EDIT&UPDATE LOT
    //----------------------------------------------
    function edit_lot(bt){
        // hide bt
        $(bt).hide();

        var valid = true;
        
        var description = $( "#edit_description_lot" ).val();
        var ref = $( "#edit_ref_lot" ).val();
        var label = $( "#edit_label_lot" ).val();
        var id_lot = $( "#edit_id_lot" ).val();

        if( (label=='') || (ref=='') ){
            valid = false;
        }

        if(!valid){
            alert('Please fill all required field.');
            $(bt).show();

        } else {
            var data_add = {
                'id_projet':id_projet,
                'id_lot':id_lot,
                'label':label,
                'ref':ref,
                'description':description
            };  
            console.log("Aici este de la .js",data_add);

            if ( valid ) {
                $.ajax({ 
                    url: '/abcvc/projet/card.php?actionajax=ajax_edit_lot', 
                    dataType: 'json',
                    type: 'POST',
                    data : data_add,
                    success: function(data){
                        console.log(data);
                        if(data.statut=="ok"){
                            $('#edit_lot_Modal').modal("hide");
                            
                            //TODO inject new lot / refresh page ?
                            document.location.reload();
                        } else {
                            //show button
                            $(bt).show();

                            alert(data.message);
                        }   
                    }
                });
            }
        
        }
        return valid;
    }

    //EDIT&UPDATE CATEGORY
    //----------------------------------------------
    function edit_category(bt){
        // hide bt
        $(bt).hide();

        var valid = true;
        
        var description = $( "#edit_description_category" ).val();
        var ref = $( "#edit_ref_category" ).val();
        var label = $( "#edit_label_category" ).val();
        var id_lot = $( "#edit_id_category" ).val();

        if( (label=='') || (ref=='') ){
            valid = false;
        }

        if(!valid){
            alert('Please fill all required field.');
            $(bt).show();

        } else {
            var data_add = {
                'id_projet':id_projet,
                'id_lot':id_lot,
                'label':label,
                'ref':ref,
                'description':description
            };  
            console.log("Aici este de la .js",data_add);

            if ( valid ) {
                $.ajax({ 
                    url: '/abcvc/projet/card.php?actionajax=ajax_edit_category', 
                    dataType: 'json',
                    type: 'POST',
                    data : data_add,
                    success: function(data){
                        console.log(data);
                        if(data.statut=="ok"){
                            $('#edit_category_Modal').modal("hide");
                            
                            //TODO inject new lot / refresh page ?
                            document.location.reload();
                        } else {
                            //show button
                            $(bt).show();

                            alert(data.message);
                        }   
                    }
                });
            }
        
        }
        return valid;
    }

    //EDIT&UPDATE POSTE
    //-----------------
    function edit_poste(bt) {
        //hide bt
        $( bt ).hide();

        var valid = true;

        var code_poste = $("#edit_code_poste").val();
        var id_poste = $("#edit_id_poste").val();
        var label = $( "#edit_label_poste" ).val();
        var id_category = $( "#edit_id_category_poste" ).val();
        var start_date = $( "#edit_start_date_poste" ).val();
        var end_date = $( "#edit_end_date_poste" ).val();
        
        var planned_work_h = $( "#edit_planned_work_h_poste" ).val() ;
        var calculated_work_h = $( "#edit_calculated_work_h_poste" ).val() ;

        var declared_progress = $( "#edit_declared_progress_poste" ).val();
        var description = $( "#edit_description_poste" ).val();
        var contacts_executive = $('#poste_edit_executive').val();
        var contacts_contributor = $('#poste_edit_contributor').val();
        var contacts_executive_initial = $('#poste_edit_executive_initial').val();
        contacts_executive_initial = contacts_executive_initial.split(',');

        var contacts_contributor_initial = $('#poste_edit_contributor_initial').val();
        contacts_contributor_initial = contacts_contributor_initial.split(',');

        var progress_estimated = 0; //$( "#edit_estimated_progress_poste").val();

        var zone = $( "#id_edit_zone").val();

        var poste_price = $( "#edit_poste_price").val();

        var factfourn = $('#poste_edit_factfourn').val();

        var factfourn_initial = $('#poste_edit_factfourn_initial').val();
        factfourn_initial = factfourn_initial.split(',');

        var poste_pv = $('#edit_poste_pv').val();

        var tx_tva = $("#edit_poste_tva").val();

        if ( ( label == '' ) || ( id_category == '' ) || ( planned_work_h == '' )) {
            valid = false;
        }

        if ( !valid ) {
            alert( "Please fill all the required fields!" ); 
            $( bt ).show();
        
        } else {

            var contacts_executive_todelete = [];
            var contacts_contributor_todelete = [];
            $.each(contacts_executive_initial,function(i,el){
                if( ($.inArray(el,contacts_executive)) == -1 ){
                    contacts_executive_todelete.push(el);
                }
            });
            $.each(contacts_contributor_initial,function(i,el){
                if( ($.inArray(el,contacts_contributor)) == -1 ){
                    contacts_contributor_todelete.push(el);
                }
            });

            var factfourn_todelete = [];
            $.each(factfourn_initial,function(i,el){
                if( ($.inArray(el,factfourn)) == -1 ){
                    factfourn_todelete.push(el);
                }
            });

            var data_add = {
                'code_poste' : code_poste,
                'id_poste' : id_poste,
                'id_projet' : id_projet,
                'label' : label,
                'id_category' : id_category, 
                'start_date' : start_date,
                'end_date' : end_date,
                'planned_work_h' : planned_work_h,
                'calculated_work_h' : calculated_work_h,
                'declared_progress' : declared_progress,
                'description' : description,
                'contacts_executive' : contacts_executive,
                'contacts_contributor' : contacts_contributor,
                'contacts_executive_todelete' : contacts_executive_todelete,
                'contacts_contributor_todelete' : contacts_contributor_todelete,
                'progress_estimated' : progress_estimated,
                'zone' : zone,
                'poste_price' : poste_price,
                'factfourn' : factfourn,
                'factfourn_todelete' : factfourn_todelete,
                'tx_tva' : tx_tva,
                'poste_pv' : poste_pv
            }

            if( valid ) {
                $.ajax( {
                    url:'/abcvc/projet/card.php?actionajax=ajax_edit_poste',
                    dataType: 'json',
                    type: 'POST',
                    data: data_add,
                    success: function( data ) {
                        console.log(data);
                        if( data.statut == "ok" ) {
                            $("#posteModal_edit").modal("hide");
                            console.log("Added");

                            document.location.reload();
                        } else {
                            //show button
                            $(bt).show();

                            alert( data.message );
                        }
                    }
                });
            }
           
        }

        return valid;
    }


    //EDIT&UPDATE SUBPOSTE
    //
    function edit_subposte(bt){
        //hide button
        $(bt).hide();

        var valid = true;
        
        /*

         */
            var code = $( "#edit_code_subposte" ).val();
            var label = $( "#edit_label_subposte" ).val();
            var child = $( "#edit_child_subposte" ).val();
            var start_date = $( "#edit_startDate_subposte" ).val();
            var end_date = $( "#edit_endDate_subposte" ).val();
            var planned_work_h = $( "#edit_plannedWork_subposte_h" ).val();
            var planned_work_m = $( "#edit_plannedWork_subposte_m" ).val();
            var declared_progress = $( "#id_declaredProgress_subposte" ).val();
            var description = $( "#edit_desc_subposte" ).val();
            var id_subposte = $( "#edit_id_subposte" ).val();
            var contacts_executive = $('#subposte_edit_executive').val();
            var contacts_contributor = $('#subposte_edit_contributor').val();
            var contacts_executive_initial = $('#subposte_edit_executive_initial').val();
            contacts_executive_initial = contacts_executive_initial.split(',');

            var contacts_contributor_initial = $('#subposte_edit_contributor_initial').val();
            contacts_contributor_initial = contacts_contributor_initial.split(',');

            var progress_estimated = $( "#edit_estimated_progress_subposte").val();
            var subposte_price = $( "#edit_subposte_price").val();
            var factfourn = $('#sousposte_edit_factfourn').val();
            var factfourn_initial = $('#sousposte_edit_factfourn_initial').val();
            factfourn_initial = factfourn_initial.split(',');

            var poste_pv = $('#edit_subposte_pv').val();
            var sousposte_edit_select_unite = $('#sousposte_edit_select_unite').val();
            var sousposte_edit_unite = $('#sousposte_edit_unite').val();


            if ( ( label == '' ) || ( child == '' ) || ( planned_work_h == '' ) ) {
                valid = false;
            }

            if ( !valid ) {
                alert( "Please fill all the required fields!" ); 
                $( bt ).show();
            } else {

                var contacts_executive_todelete = [];
                var contacts_contributor_todelete = [];

                $.each(contacts_executive_initial,function(i,el){
                if( ($.inArray(el,contacts_executive)) == -1 ){
                contacts_executive_todelete.push(el);
                }
                });
                $.each(contacts_contributor_initial,function(i,el){
                if( ($.inArray(el,contacts_contributor)) == -1 ){
                contacts_contributor_todelete.push(el);
                }
                });

                var factfourn_todelete = [];

                $.each(factfourn_initial,function(i,el){
                    if( ($.inArray(el,factfourn)) == -1 ){
                        factfourn_todelete.push(el);
                    }
                });


                var data_add = {
                    'id_projet' : id_projet,
                    'code' : code,
                    'label' : label,
                    'child' : child, 
                    'start_date' : start_date,
                    'end_date' : end_date,
                    'planned_work_h' : planned_work_h,
                    'planned_work_m' : planned_work_m,
                    'declared_progress' : declared_progress,
                    'description' : description,
                    'rowid' : id_subposte,
                    'contacts_executive' : contacts_executive,
                    'contacts_contributor' : contacts_contributor,
                    'contacts_executive_todelete' : contacts_executive_todelete,
                    'contacts_contributor_todelete' : contacts_contributor_todelete,
                    'progress_estimated' : progress_estimated,
                    'subposte_price' : subposte_price,
                    'factfourn' : factfourn,
                    'factfourn_todelete' : factfourn_todelete,
                    'poste_pv' : poste_pv,
                    'sousposte_edit_select_unite' : sousposte_edit_select_unite,
                    'sousposte_edit_unite' : sousposte_edit_unite
                }
                console.log(data_add);
                
                if( valid ) {
                    $.ajax( {
                        url:'/abcvc/projet/card.php?actionajax=ajax_edit_subposte',
                        dataType: 'json',
                        type: 'POST',
                        data: data_add,
                        success: function( data ) {
                            if( data.statut == "ok" ) {
                                $("#edit_subposte_Modal").modal("hide");

                                document.location.reload();
                            } else {
                                //show button
                                $(bt).show();

                                alert( data.message );
                            }
                        }
                    });
                }
                
            }  

        return valid;
    }


    //EDIT&UPDATE SUBSUBPOSTE
    //
    function edit_subsubposte(bt){
        //hide button
        $(bt).hide();

        var valid = true;
        
        /*

         */
            var code = $( "#edit_code_subsubposte" ).val();
            var label = $( "#edit_label_subsubposte" ).val();
            var child = $( "#edit_child_subsubposte" ).val();
            var start_date = $( "#edit_startDate_subsubposte" ).val();
            var end_date = $( "#edit_endDate_subsubposte" ).val();
            var planned_work_h = $( "#edit_plannedWork_subsubposte_h" ).val();
            var planned_work_m = $( "#edit_plannedWork_subsubposte_m" ).val();
            var declared_progress = $( "#id_declaredProgress_subsubposte" ).val();
            var description = $( "#edit_desc_subsubposte" ).val();
            var id_subsubposte = $( "#edit_id_subsubposte" ).val();
            var contacts_executive = $('#subsubposte_edit_executive').val();
            var contacts_contributor = $('#subsubposte_edit_contributor').val();
            var contacts_executive_initial = $('#subsubposte_edit_executive_initial').val();
            contacts_executive_initial = contacts_executive_initial.split(',');

            var contacts_contributor_initial = $('#subsubposte_edit_contributor_initial').val();
            contacts_contributor_initial = contacts_contributor_initial.split(',');

            var progress_estimated = $( "#edit_estimated_progress_subsubposte").val();
            var subsubposte_price = $( "#edit_subsubposte_price").val();
            var factfourn = $('#soussousposte_edit_factfourn').val();
            var factfourn_initial = $('#soussousposte_edit_factfourn_initial').val();
            factfourn_initial = factfourn_initial.split(',');

            var poste_pv = $('#edit_subsubposte_pv').val();
            var soussousposte_edit_select_unite = $('#soussousposte_edit_select_unite').val();
            var soussousposte_edit_unite = $('#soussousposte_edit_unite').val();
            if ( ( label == '' ) || ( child == '' ) || ( planned_work_h == '' ) ) {
                valid = false;
            }

            if ( !valid ) {
                //alert( "Please fill all the required fields!" ); 
                $( bt ).show();
            } else {

                var contacts_executive_todelete = [];
                var contacts_contributor_todelete = [];

                $.each(contacts_executive_initial,function(i,el){
                if( ($.inArray(el,contacts_executive)) == -1 ){
                contacts_executive_todelete.push(el);
                }
                });
                
                $.each(contacts_contributor_initial,function(i,el){
                if( ($.inArray(el,contacts_contributor)) == -1 ){
                contacts_contributor_todelete.push(el);
                }
                });

                var factfourn_todelete = [];
                $.each(factfourn_initial,function(i,el){
                    if( ($.inArray(el,factfourn)) == -1 ){
                        factfourn_todelete.push(el);
                    }
                });

                var data_add = {
                    'id_projet' : id_projet,
                    'code' : code,
                    'label' : label,
                    'child' : child, 
                    'start_date' : start_date,
                    'end_date' : end_date,
                    'planned_work_h' : planned_work_h,
                    'planned_work_m' : planned_work_m,
                    'declared_progress' : declared_progress,
                    'description' : description,
                    'rowid' : id_subsubposte,
                    'contacts_executive' : contacts_executive,
                    'contacts_contributor' : contacts_contributor,
                    'contacts_executive_todelete' : contacts_executive_todelete,
                    'contacts_contributor_todelete' : contacts_contributor_todelete,
                    'progress_estimated' : progress_estimated,
                    'subsubposte_price' : subsubposte_price,
                    'factfourn' : factfourn,
                    'factfourn_todelete' : factfourn_todelete,
                    'poste_pv' : poste_pv,
                    'soussousposte_edit_select_unite' : soussousposte_edit_select_unite,
                    'soussousposte_edit_unite' : soussousposte_edit_unite

                }

                if( valid ) {
                    $.ajax( {
                        url:'/abcvc/projet/card.php?actionajax=ajax_edit_subsubposte',
                        dataType: 'json',
                        type: 'POST',
                        data: data_add,
                        success: function( data ) {
                            if( data.statut == "ok" ) {
                                $("#edit_subsubposte_Modal").modal("hide");

                                document.location.reload();
                            } else {
                                //show button
                                $(bt).show();

                                alert( data.message );
                                }
                            }
                        });
                    }
                }

        return valid;
    }



    //delete LOT
    //----------------------------------------------
    function bt_delete_lot(bt){
        var id_lot = $( "#edit_id_lot" ).val();

        var nbchild = $( "#bt_delete_lot" ).data('nbchild');
 
        if( confirm('Confirmez-vous la suppression de ce lot ?') ){
            //test child.. 
            var okdel = true;
            if(nbchild>0){
                 if( confirm("! Attention !\nCeci va également supprimer la structure dépendante.\nC'est une opération irréversible.\nEtes-vous sur ?") ){
                    okdel = true;
                 } else {
                    okdel = false;
                 }
            }
            if( okdel ){
                var data_del = {
                    'id_projet':id_projet,
                    'id_lot':id_lot
                };  
                $.ajax({ 
                    url: '/abcvc/projet/card.php?actionajax=ajax_delete_lot', 
                    dataType: 'json',
                    type: 'POST',
                    data : data_del,
                    success: function(data){
                        if(data.statut=="ok"){
                            // refresh page
                            document.location.reload();
                        } else {
                            alert(data.message);
                        }   
                    }
                });
            }    
        } 

        return true;
    }

    //delete category
    //----------------------------------------------
    function bt_delete_category(bt){
        var id_category = $( "#edit_id_category" ).val();

        var nbchild = $( "#bt_delete_category" ).data('nbchild');
 
        if( confirm('Confirmez-vous la suppression de cette catégorie ?') ){
            //test child.. 
            var okdel = true;
            if(nbchild>0){
                 if( confirm("! Attention !\nCeci va également supprimer la structure dépendante.\nC'est une opération irréversible.\nEtes-vous sur ?") ){
                    okdel = true;
                 } else {
                    okdel = false;
                 }
            }
            if( okdel ){
                var data_del = {
                    'id_projet':id_projet,
                    'id_category':id_category
                };  
                $.ajax({ 
                    url: '/abcvc/projet/card.php?actionajax=ajax_delete_category', 
                    dataType: 'json',
                    type: 'POST',
                    data : data_del,
                    success: function(data){
                        if(data.statut=="ok"){
                            // refresh page
                            document.location.reload();
                        } else {
                            alert(data.message);
                        }   
                    }
                });
            }    
        } 

        return true;
    }

    //delete poste
    //----------------------------------------------
    function bt_delete_poste(bt){
        var id_poste = $( "#edit_id_poste" ).val();

        var nbchild = $( "#bt_delete_poste" ).data('nbchild');
 
        if( confirm('Confirmez-vous la suppression de poste ?') ){
            //test child.. 
            var okdel = true;
            if(nbchild>0){
                 if( confirm("! Attention !\nCeci va également supprimer la structure dépendante.\nC'est une opération irréversible.\nEtes-vous sur ?") ){
                    okdel = true;
                 } else {
                    okdel = false;
                 }
            }
            if( okdel ){
                var data_del = {
                    'id_projet':id_projet,
                    'id_poste':id_poste
                };  
                $.ajax({ 
                    url: '/abcvc/projet/card.php?actionajax=ajax_delete_poste', 
                    dataType: 'json',
                    type: 'POST',
                    data : data_del,
                    success: function(data){
                        if(data.statut=="ok"){
                            // refresh page
                            document.location.reload();
                        } else {
                            alert(data.message);
                        }   
                    }
                });
            }    
        } 

        return true;
    }

    //delete subposte
    //----------------------------------------------
    function bt_delete_subposte(bt){
        var id_subposte = $( "#edit_id_subposte" ).val();

        var nbchild = $( "#bt_delete_subposte" ).data('nbchild');
 
        if( confirm('Confirmez-vous la suppression de sous poste ?') ){
            //test child.. 
            var okdel = true;
            if(nbchild>0){
                 if( confirm("! Attention !\nCeci va également supprimer la structure dépendante.\nC'est une opération irréversible.\nEtes-vous sur ?") ){
                    okdel = true;
                 } else {
                    okdel = false;
                 }
            }
            if( okdel ){
                var data_del = {
                    'id_projet':id_projet,
                    'id_subposte':id_subposte
                };  
                $.ajax({ 
                    url: '/abcvc/projet/card.php?actionajax=ajax_delete_subposte', 
                    dataType: 'json',
                    type: 'POST',
                    data : data_del,
                    success: function(data){
                        if(data.statut=="ok"){
                            // refresh page
                            document.location.reload();
                        } else {
                            alert(data.message);
                        }   
                    }
                });
            }    
        } 

        return true;
    }    

    //delete subsubposte
    //----------------------------------------------
    function bt_delete_subsubposte(bt){
        var id_subsubposte = $( "#edit_id_subsubposte" ).val();

        var nbchild = $( "#bt_delete_subsubposte" ).data('nbchild');
 
        if( confirm('Confirmez-vous la suppression de ce sous sous poste ?') ){
            //test child.. 
            var okdel = true;
            if(nbchild>0){
                 if( confirm("! Attention !\nCeci va également supprimer la structure dépendante.\nC'est une opération irréversible.\nEtes-vous sur ?") ){
                    okdel = true;
                 } else {
                    okdel = false;
                 }
            }
            if( okdel ){
                var data_del = {
                    'id_projet':id_projet,
                    'id_subsubposte':id_subsubposte
                };  
                $.ajax({ 
                    url: '/abcvc/projet/card.php?actionajax=ajax_delete_subsubposte', 
                    dataType: 'json',
                    type: 'POST',
                    data : data_del,
                    success: function(data){
                        if(data.statut=="ok"){
                            // refresh page
                            document.location.reload();
                        } else {
                            alert(data.message);
                        }   
                    }
                });
            }    
        } 

        return true;
    }   


/****************POPUP FOR SUPPRIMER BUTTON****************/
