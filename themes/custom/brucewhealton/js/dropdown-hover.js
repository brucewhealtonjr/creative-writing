(function ($, Drupal) {
  Drupal.behaviors.dropdownHover = {
    attach: function (context, settings) {
      $('.dropdown', context).hover(
        function () {
          $(this).addClass('show');
          $(this).find('.dropdown-menu').addClass('show');
        },
        function () {
          $(this).removeClass('show');
          $(this).find('.dropdown-menu').removeClass('show');
        }
      );
    }
  };
})(jQuery, Drupal);
