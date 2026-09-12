<script>
(function () {
    var form = $("#additional_form_{$timestamp}");
    var singleTables = {$single_record_tables_json nofilter};
    var updatePlaces = function () {
        var single = singleTables.indexOf(form.find('[name="tb_name"]').val()) !== -1;
        var places = form.find('[name="place"]');
        places.find('option').each(function () {
            var unavailable = single && this.value !== '0';
            $(this).prop('disabled', unavailable).prop('hidden', unavailable);
        });
        if (single) { places.val('0'); }
    };
    form.find('[name="tb_name"]').on('change', updatePlaces);
    updatePlaces();
})();
</script>
