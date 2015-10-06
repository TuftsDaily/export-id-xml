jQuery(document).ready(function($) {

    $('form#post').submit(function() {

        var status = $('select#post_status').val();
        var print_date = $('#export_id_xml_print-date').val();

        if (status == "ready-to-post" && print_date == '') {
            alert('Please set the print date under "Print Options".');
            return false;
        }

    });

})