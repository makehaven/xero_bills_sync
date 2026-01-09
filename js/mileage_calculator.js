(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.xeroMileageCalculator = {
    attach: function (context, settings) {
      var $accountSelect = $('select[name="field_xero_account_id_reimburse"]', context);
      var $milesWrapper = $('.miles-input-wrapper', context);
      var $milesInput = $('input[name="miles_input"]', context);
      var $amountInput = $('input[name^="field_amount"]', context);
      var rate = drupalSettings.xero_bills_sync && drupalSettings.xero_bills_sync.mileage_rate ? drupalSettings.xero_bills_sync.mileage_rate : 0.67;

      function checkVisibility() {
        var selectedAccount = $accountSelect.val();
        // 6048 is Automobile Expenses
        if (selectedAccount === '6048') {
          $milesWrapper.slideDown(200);
          // Optional: Make amount readonly when using calculator?
          // $amountInput.prop('readonly', true);
        } else {
          $milesWrapper.slideUp(200);
          // $amountInput.prop('readonly', false);
        }
      }

      function calculateAmount() {
        var miles = parseFloat($milesInput.val());
        if (!isNaN(miles)) {
          var total = (miles * rate).toFixed(2);
          $amountInput.val(total);
        }
      }

      if ($accountSelect.length && $milesInput.length) {
        // Initial check
        checkVisibility();
        $accountSelect.on('change', checkVisibility);
        $milesInput.on('keyup change input', calculateAmount);
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
