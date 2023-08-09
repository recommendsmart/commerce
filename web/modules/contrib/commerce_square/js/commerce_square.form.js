/**
 * @file
 * Defines behaviors for the Square payment method form.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  let squareInitialized = false;

  /**
   * Attaches the commerceSquareForm behavior.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the commerceSquareForm behavior.
   *
   * @see Drupal.commerceSquare
   */
  Drupal.behaviors.commerceSquareForm = {
    attach: function (context) {
      const settings = drupalSettings.commerceSquare;
      // Ensure we initialize the script only once.
      if (typeof settings !== 'undefined' && !squareInitialized) {
        squareInitialized = true;
        let script = document.createElement('script');

        const scriptHostname = settings.apiMode === 'sandbox' ? 'sandbox.web.squarecdn.com' : 'web.squarecdn.com';
        script.src = 'https://' + scriptHostname + '/v1/square.js';
        script.type = 'text/javascript';
        document.getElementsByTagName('head')[0].appendChild(script);
      }

      const $form = $(once('square-attach', $('.square-form', context).closest('form'), context));
        if ($form.length === 0) {
        return;
      }
      const waitForSdk = setInterval(function () {
        if (typeof Square !== 'undefined') {
          Drupal.commerceSquare($form, drupalSettings.commerceSquare);
          //$form.data('square', commerceSquare);
          clearInterval(waitForSdk);
        }
      }, 100);
    },
    detach: function (context, settings, trigger) {
      // Detaching on the wrong trigger will clear the Square form
      // on #ajax (after changing the address country, for example).
      if (trigger !== 'unload') {
        return;
      }
      const $form = $('.square-form', context).closest('form');
      if ($form.length === 0) {
        return;
      }

      $form.closest('form').find('[name="op"]').prop('disabled', false);
      once.remove('square-attach', $form);
      const $formSubmit = $form.find('[name="op"]');
      $formSubmit.off("click.squareToken");
    }
  };

  /**
   * Creates a square card form with Commerce-specific logic.
   *
   * @constructor
   */
  Drupal.commerceSquare = function ($squareForm, settings) {
    const $formSubmit = $squareForm.find(':input.button--primary');
    $formSubmit.on("click.squareToken", requestCardToken);
    let card;
    getSquareCard($squareForm, settings).then(cardForm => {
      card = cardForm;
      card.attach('.square-form');
    });

    /**
     * Creates a square card form object.
     */
    async function getSquareCard($squareForm, settings) {
      const payments = Square.payments(settings.applicationId, settings.locationId);
      return await payments.card({});
    }

    /**
     * Requests a square card token.
     */
    async function requestCardToken(event) {
      event.preventDefault();
      let tokenResult = await card.tokenize();
      if (tokenResult.status === 'OK') {
        let cardData = tokenResult.details.card;
        $squareForm.find('.square-payment-token').val(tokenResult.token);
        $squareForm.find('.square-card-type').val(cardData.brand);
        $squareForm.find('.square-last4').val(cardData.last4);
        $squareForm.find('.square-exp-month').val(cardData.expMonth);
        $squareForm.find('.square-exp-year').val(cardData.expYear);
        $squareForm.submit();
      }
    }

    return this;
  };

})(jQuery, Drupal, drupalSettings);
