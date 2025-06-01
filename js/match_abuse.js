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
      <div id="${toastId}" class="toast ${response.type}" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
          <strong class="me-auto">${response.title}</strong>
          <small>${response.type}</small>
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

})(jQuery, Drupal);
