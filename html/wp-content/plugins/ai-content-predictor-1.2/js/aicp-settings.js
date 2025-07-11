jQuery(document).ready(function($) {
    const rangeInputs = ['#aicp_detect_threshold', '#plagiarism_detect_threshold'];
    rangeInputs.forEach( function(selector, index) {
        $(selector).on('input', function () {
            $(this).next('output').val($(this).val());
        })
    });
});
