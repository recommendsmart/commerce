(function($, Drupal, drupalSettings) {

  Drupal.commerceAvatax = {
    isValidating: false,
    submitForm($form) {
      this.isValidating = false;
      $form.find(":input.button--primary").click();
    },
    getConfirmationDialog(content, title, buttons) {
      return Drupal.dialog($('<div class="address-suggestions">' + content + '</div>'), {
        title: title,
        dialogClass: 'address-format-modal',
        resizable: false,
        buttons: buttons,
        closeOnEscape: false,
        maxWidth: "80%",
        draggable: false,
        close(event) {
          this.isValidating = false;
          Drupal.dialog(event.target).close();
          Drupal.detachBehaviors(event.target, null, 'unload');
          $(event.target).remove();
        }
      });
    }
  };

  Drupal.behaviors.commerceAvatax = {
    attach(context) {
      if (!drupalSettings.commerceAvatax ||
        !drupalSettings.commerceAvatax.address ||
        !drupalSettings.commerceAvatax.country) {
        return;
      }
      const $form = $('.avatax-form', context).closest('form');
      if (!$form.attr('id')) {
        return;
      }
      $(once($form.attr('id'), 'form')).on('submit.commerce_avatax',() => {
        let allowFormSubmit = true;
        // Get data from module.
        let address = drupalSettings.commerceAvatax.address;
        const $inlineForm = $('#' + drupalSettings.commerceAvatax.inline_id);
        const $addressSuggestionEl = $inlineForm.find('[name*="address_suggestion"]')

        // We have some value, continue with from submit.
        if ($addressSuggestionEl.val().length > 0) {
          return allowFormSubmit;
        }

        // Check if this submit handler is already performing validation and
        // triggered again by another module.
        if (Drupal.commerceAvatax.isValidating) {
          return false;
        }

        // If we don't have country code or we adding or editing address,
        // pickup new values.
        if (address.country_code === null || !drupalSettings.commerceAvatax.rendered) {
          drupalSettings.commerceAvatax.fields.forEach((i) => {
            address[i] = $inlineForm.find('[name*="' + i + ']"]').val()
          });
        }

        // Stop here if the address doesn't match countries that we're supposed
        // to validate.
        if (!drupalSettings.commerceAvatax.countries.hasOwnProperty(address.country_code) ||
          $('.address-format-modal').length > 0) {
          return allowFormSubmit;
        }
        // Do not submit form automatically, we need to attempt address validation.
        allowFormSubmit = false;
        // Start validating field.
        Drupal.commerceAvatax.isValidating = true;

        $.ajax({
          async: true,
          url: drupalSettings.commerceAvatax.endpoint,
          type: 'POST',
          data: JSON.stringify(address),
          dataType: 'json',
          success(response) {
            if (!response.output) {
              // The address had no suggestions.
              $addressSuggestionEl.val('original');
              allowFormSubmit = true;
              Drupal.commerceAvatax.submitForm($form);
              return;
            }
            let actions = [{
              text: Drupal.t('Let me change the address'),
              class: 'button button--primary',
              id: 'button-again',
              click() {
                Drupal.commerceAvatax.isValidating = false;
                confirmationDialog.close();
              }
            }, {
              text: Drupal.t('Use the address anyway'),
              class: 'button',
              id: 'button-entered',
              click() {
                $addressSuggestionEl.val('original');
                confirmationDialog.close();
                Drupal.commerceAvatax.submitForm($form);
              }
            }];

            // If we have proper suggestion.
            if (response.payload) {
              actions = [{
                text: Drupal.t('Use recommended'),
                class: 'button button--primary',
                id: 'button-recommended',
                click() {
                  $addressSuggestionEl.val(response.payload);
                  // Even we sent payload, still pre-fill fields,
                  // if something else on the other panes fail,
                  // address should be there.
                  // We could also split by rendered flag how we fill
                  // data with rendered variable, but we still can't
                  // But we still can't mark address as validated
                  // without using submit handler in inline form.
                  drupalSettings.commerceAvatax.fields.map((i) => {
                    $inlineForm.find('[name*="' + i + ']"]').val(response.suggestion[i]);
                  });
                  confirmationDialog.close();
                  Drupal.commerceAvatax.submitForm($form);
                }
              }, {
                text: Drupal.t('Use as entered'),
                class: 'button',
                id: 'button-entered',
                click() {
                  $addressSuggestionEl.val('original');
                  confirmationDialog.close();
                  Drupal.commerceAvatax.submitForm($form);
                }
              },
              {
                text: Drupal.t('Enter again'),
                class: 'button',
                id: 'button-again',
                click(event) {
                  Drupal.commerceAvatax.isValidating = false;
                  confirmationDialog.close();
                }
              }];
            }

            const confirmationDialog = Drupal.commerceAvatax.getConfirmationDialog(response.output, Drupal.t('Confirm your shipping address'), actions);
            confirmationDialog.showModal();
          },
          error() {
            const actions = [{
              text: Drupal.t('Let me change the address'),
              class: 'button button--primary',
              id: 'button-again',
              click() {
                confirmationDialog.close()
              }
            }, {
              text: Drupal.t('Use the address anyway'),
              class: 'button',
              id: 'button-entered',
              click(event) {
                $addressSuggestionEl.val('original');
                confirmationDialog.close()
                Drupal.commerceAvatax.submitForm($form);
              }
            }];
            const confirmationDialog = Drupal.commerceAvatax.getConfirmationDialog(
              Drupal.t('We could not validate the address entered. Please check that you have entered the correct address'),
              Drupal.t('Confirm your shipping address'),
              actions
            );
            confirmationDialog.showModal();
          },
        });

        return allowFormSubmit;
      });
    },

    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }

      const $form = $('.avatax-form', context).closest('form');
      if ($form.length === 0) {
        return;
      }

      $form.off('submit.commerce_avatax');
    },
  };
})(jQuery, Drupal, drupalSettings);
