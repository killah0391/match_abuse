(function ($, Drupal) {

  /**
   * Defines the 'showBootstrapToast' AJAX command.
   */
  Drupal.AjaxCommands.prototype.showBootstrapToast = function (ajax, response, status) {
    // 1. Check if Bootstrap 5 Toast is available.
    if (typeof bootstrap === 'undefined' || typeof bootstrap.Toast === 'undefined') {
      console.warn('Bootstrap 5 Toast not available. Falling back to Drupal.announce().');
      Drupal.announce(response.message);
      return;
    }

    // 2. Find or create the toast container.
    let toastContainer = document.getElementById('toast-container-match-abuse');
    if (!toastContainer) {
      toastContainer = document.createElement('div');
      toastContainer.id = 'toast-container-match-abuse';
      // Standard Bootstrap toast container positioning.
      toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
      toastContainer.style.zIndex = '1100'; // Ensure it's above most elements.
      document.body.appendChild(toastContainer);
    }

    // 3. Create the toast HTML.
    const toastId = 'toast-' + Date.now();
    // Basic Bootstrap 5 Toast HTML Structure.
    const toastHtml = `
      <div id="${toastId}" class="toast ${response.type || 'status'}" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
          <strong class="me-auto">${response.title || Drupal.t('Notification')}</strong>
          <small>${response.type || 'status'}</small>
          <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
          ${response.message}
        </div>
      </div>`;

    // 4. Add the new toast HTML to the container.
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);

    // 5. Initialize and show the toast.
    const toastElement = document.getElementById(toastId);
    if (toastElement) {
      const toast = new bootstrap.Toast(toastElement, {
        delay: 5000, // Show for 5 seconds.
        autohide: true,
      });
      toast.show();

      // 6. Optional: Remove the toast element from DOM after it's hidden.
      toastElement.addEventListener('hidden.bs.toast', function () {
        toastElement.remove();
      });
    }
  };

  // --- MODAL LOGIC START ---
  let confirmModalInstance; // To store the Bootstrap Modal instance

  /**
   * Ensures the confirmation modal HTML is in the DOM and returns a Bootstrap Modal instance.
   */
  function ensureModalAndGetInstance() {
    const modalId = 'matchAbuseConfirmModal';
    let modalElement = document.getElementById(modalId);
    if (!modalElement) {
      const modalHtml = `
        <div class="modal fade" id="${modalId}" tabindex="-1" aria-labelledby="${modalId}Label" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="${modalId}Label">${Drupal.t('Confirm Action')}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="${Drupal.t('Close')}"></button>
              </div>
              <div class="modal-body">
                <p id="${modalId}BodyText"></p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${Drupal.t('Cancel')}</button>
                <button type="button" class="btn btn-primary" id="${modalId}ConfirmButton">${Drupal.t('Confirm')}</button>
              </div>
            </div>
          </div>
        </div>`;
      document.body.insertAdjacentHTML('beforeend', modalHtml);
      modalElement = document.getElementById(modalId);
    }
    // Ensure Bootstrap Modal JS is available
    if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
      return new bootstrap.Modal(modalElement);
    } else {
      console.error('Bootstrap Modal JS not available.');
      return null;
    }
  }

  $(document).ready(function () {
    confirmModalInstance = ensureModalAndGetInstance();
    if (!confirmModalInstance) return; // Stop if modal couldn't be initialized

    $('body').on('click', '.js-match-abuse-confirm-action', function (e) {
      e.preventDefault();
      const $link = $(this);
      const ajaxUrl = $link.data('ajax-url');
      const username = $link.data('username');
      const actionType = $link.data('action-type'); // "block" or "unblock"
      const originalLinkElement = this; // The DOM element that was clicked

      let modalTitle = '';
      let modalBodyText = '';
      let confirmButtonClass = 'btn-primary';
      let confirmButtonText = Drupal.t('Confirm');

      if (actionType === 'block') {
        modalTitle = Drupal.t('Confirm Block User');
        modalBodyText = Drupal.t('Are you sure you want to block @username?', { '@username': username });
        confirmButtonClass = 'btn-danger';
      } else if (actionType === 'unblock') {
        modalTitle = Drupal.t('Confirm Unblock User');
        modalBodyText = Drupal.t('Are you sure you want to unblock @username?', { '@username': username });
        confirmButtonClass = 'btn-success';
      } else {
        console.error('Unknown action type for confirmation modal:', actionType);
        return;
      }

      $('#matchAbuseConfirmModalLabel').text(modalTitle);
      $('#matchAbuseConfirmModalBodyText').text(modalBodyText);
      const $confirmButton = $('#matchAbuseConfirmModalConfirmButton');

      $confirmButton.text(confirmButtonText);
      $confirmButton.removeClass('btn-danger btn-success btn-primary').addClass(confirmButtonClass);

      // Store data on the confirm button to be used when it's clicked
      $confirmButton.data('ajax-url', ajaxUrl);
      $confirmButton.data('original-link-element', originalLinkElement);

      confirmModalInstance.show();
    });

    $('#matchAbuseConfirmModalConfirmButton').on('click', function () {
      const $confirmButton = $(this);
      const ajaxUrl = $confirmButton.data('ajax-url');
      const originalLinkElement = $confirmButton.data('original-link-element'); // DOM element

      if (ajaxUrl && originalLinkElement && originalLinkElement.id) {
        const elementSettings = {
          url: ajaxUrl,
          event: 'match_abuse_confirmed_action', // Custom event name
          progress: { type: 'throbber', message: Drupal.t('Processing...') }
        };

        // base can be the element ID or the element itself.
        // element is the triggering element.
        const ajaxInstance = new Drupal.Ajax(originalLinkElement.id, originalLinkElement, elementSettings);
        ajaxInstance.execute(); // This performs the AJAX request and Drupal handles commands.

        confirmModalInstance.hide();
      } else {
        console.error('Missing data for AJAX call from confirmation modal.', { ajaxUrl, originalLinkElement });
        if (!originalLinkElement || !originalLinkElement.id) {
          console.error("The original link element must have a unique ID for Drupal.Ajax to work correctly.");
        }
        confirmModalInstance.hide();
        Drupal.announce(Drupal.t('Could not proceed with the action. Required data is missing.'), 'error');
      }
    });
  });
  // --- MODAL LOGIC END ---

})(jQuery, Drupal);
