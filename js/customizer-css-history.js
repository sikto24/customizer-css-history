jQuery(document).ready(function ($) {
  $('.restore-button').on('click', function (e) {
    e.preventDefault();

    var restoreIndex = $(this).data('index');
    var cssContent = $(this).data('css');

    if (confirm('Do you want to restore this CSS?')) {
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
          action: 'restore_css_history',
          index: restoreIndex,
          css: cssContent,
          nonce: customizer_css_history.nonce // Corrected here
        },
        success: function (response) {
          if (response.success) {
            alert('Customizer CSS restored successfully.');
            location.reload();
          } else {
            alert('Failed to restore Customizer CSS: ' + response.data.message);
          }
        }
      });
    }
  });

  $('.view-css-button').on('click', function (e) {
    e.preventDefault();

    var cssContent = $(this).data('css');

    alert('CSS Content:\n\n' + cssContent);
  });
});
