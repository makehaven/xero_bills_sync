(function ($, Drupal) {
  Drupal.behaviors.xeroPaymentCalculator = {
    attach: function (context, settings) {
      // Selectors for the fields. Adjust if your machine names differ.
      // We look for inputs ending in [0][value] to match standard Drupal field naming.
      var $hoursInput = $('input[name^="field_hours"]', context);
      var $rateInput = $('input[name^="field_hourly_rate"]', context);
      var $amountInput = $('input[name^="field_amount"]', context);

      function calculateTotal() {
        var hours = parseFloat($hoursInput.val()) || 0;
        var rate = parseFloat($rateInput.val()) || 0;
        
        // Only calculate if both values are present (or at least valid numbers).
        // We round to 2 decimal places.
        var total = (hours * rate).toFixed(2);
        
        $amountInput.val(total);
      }

      // Attach change/keyup events.
      if ($hoursInput.length && $rateInput.length && $amountInput.length) {
        $hoursInput.on('keyup change', calculateTotal);
        $rateInput.on('keyup change', calculateTotal);
        
        // Optional: Make amount readonly if you want to force the calc.
        // $amountInput.attr('readonly', true); 
      }
    }
  };
})(jQuery, Drupal);
