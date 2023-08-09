<?php

namespace Drupal\commerce_license\Element;

use Drupal\Core\Render\Element\StatusReport as StatusReportCore;

/**
 * Creates commerce license status report element.
 *
 * @RenderElement("commerce_license_status_report")
 */
class StatusReport extends StatusReportCore {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#theme' => 'commerce_license_status_report_grouped',
      '#pre_render' => [
        [$class, 'preRenderGroupRequirements'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function preRenderGroupRequirements($element) {
    $severity_map = [
      REQUIREMENT_INFO => 'checked',
      REQUIREMENT_OK => 'checked',
      REQUIREMENT_WARNING => 'warning',
      REQUIREMENT_ERROR => 'error',
    ];
    $grouped_requirements = [];
    $grouped_requirements[0]['title'] = t('Checked', [], ['context' => 'Examined']);
    $grouped_requirements[0]['type'] = 'checked';

    $requirements = $element['#requirements'];
    foreach ($requirements as $key => $requirement) {
      $requirements[$key]['type'] = $severity_map[$requirement['severity']] ?? 'checked';
    }
    $grouped_requirements[0]['items'] = $requirements;
    $element['#grouped_requirements'] = $grouped_requirements;

    return $element;
  }

}
