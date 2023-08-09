 /**
 * @file
 * Bootstrap Basic Image Gallery - hovering over thumbnails changes main.
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.bootstrap_basic_image_gallery_hover_preview = {
    attach: function attach(context, settings) {

      // Find each thumbnail.
      once('hover_preview', '.bootstrap-basic-image-gallery .thumb' , context).forEach((item, index) => {
        // On hover, exchange the main image with the thumbnail image.
        $(item).on('mouseover', function(ev) {
          // Get the thumbnail being hovered.
          var thumbnailContainer = $(ev.currentTarget);
          // Find the main image div.
          var mainContainer = $(item).parent().parent().find('.main-image');
          //get the media id to show.
          const media_id = thumbnailContainer.find('img').data('main-media-id-src')

          $.each(mainContainer.find('[data-main-media-id]:not([data-main-media-id=' + media_id + ']'), function (index, item) {
            item.classList.add('d-none');
          })
          mainContainer.find('[data-main-media-id="' + media_id + '"]')[0].classList.remove('d-none');
          // Set the slide-to.
          mainContainer.attr('data-slide-to', thumbnailContainer.attr('data-slide-to'));
        });
      });
    }
  };
})(jQuery, Drupal, once);
