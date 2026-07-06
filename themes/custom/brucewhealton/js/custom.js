/**
 * @file
 * Global utilities.
 *
 */
(function($, Drupal) {

  'use strict';

  Drupal.behaviors.bootstrap_sass = {
    attach: function(context, settings) {

      // Custom code here
      $('.navbar-toggler').on('click', function() {
        $(this).closest('.navbar').toggleClass('open');
      });

    }
  };

})(jQuery, Drupal);