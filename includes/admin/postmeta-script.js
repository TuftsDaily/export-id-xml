jQuery(document).ready(function($) {

    // This is disabled for now

	var export_id_xml_check_categories = function() {

        /* script to show metabox on category ids 2,3 and 4 (write the category ids in the if condition below on line 14)*/
        $('#categorychecklist input[type="checkbox"]').each(function(i,e)
        {
            var id = $(this).attr('id').match(/-([0-9]*)$/i);

            id = (id && id[1]) ? parseInt(id[1]) : null ;

            if ($.inArray(id, [22,23]) > -1 && $(this).is(':checked')) {
                $('#my-meta-box').show();
            }
        });
        
    }

    //$('#categorychecklist input[type="checkbox"]').live('click', export_id_xml_check_categories); // calls the function on click of category checkbox
    //export_id_xml_check_categories(); // calls the function on load

})