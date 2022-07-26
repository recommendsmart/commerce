<?php

namespace Drupal\commerce_file\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\Plugin\Field\FieldFormatter\DescriptionAwareFileFormatterBase;

/**
 * Plugin implementation of the 'commerce_file_download_link' formatter.
 *
 * @FieldFormatter(
 *   id = "commerce_file_download_link",
 *   label = @Translation("Commerce File download link"),
 *   field_types = {
 *     "file",
 *   },
 * )
 */
class FileDownloadLinkFormatter extends DescriptionAwareFileFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    // This is very similar to the "file_default" formatter provided by
    // the core file module, except that it outputs a url to our file download
    // route.
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      $item = $file->_referringItem;
      $elements[$delta] = [
        '#theme' => 'commerce_file_download_link',
        '#file' => $file,
        '#description' => $this->getSetting('use_description_as_link_text') ? $item->description : NULL,
        '#cache' => [
          'tags' => $file->getCacheTags(),
        ],
      ];
      // Pass field item attributes to the theme function.
      if (isset($item->_attributes)) {
        $elements[$delta] += ['#attributes' => []];
        $elements[$delta]['#attributes'] += $item->_attributes;
        // Unset field item attributes since they have been included in the
        // formatter output and should not be rendered in the field template.
        unset($item->_attributes);
      }
    }

    return $elements;
  }

}
