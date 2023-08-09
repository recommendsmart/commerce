<?php

namespace Drupal\symfony_mailer\Plugin\EmailAdjuster;

use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\symfony_mailer\Processor\EmailAdjusterBase;
use Drupal\symfony_mailer\EmailInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

/**
 * Defines the Inline CSS Email Adjuster.
 *
 * @EmailAdjuster(
 *   id = "mailer_inline_css",
 *   label = @Translation("Inline CSS"),
 *   description = @Translation("Add inline CSS."),
 *   weight = 900,
 * )
 */
class InlineCssEmailAdjuster extends EmailAdjusterBase implements ContainerFactoryPluginInterface {

  /**
   * The asset resolver.
   *
   * @var \Drupal\Core\Asset\AssetResolverInterface
   */
  protected $assetResolver;

  /**
   * The CSS inliner.
   *
   * @var \TijsVerkoyen\CssToInlineStyles\CssToInlineStyles
   */
  protected $cssInliner;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Asset\AssetResolverInterface $asset_resolver
   *   The asset resolver.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AssetResolverInterface $asset_resolver) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->assetResolver = $asset_resolver;
    $this->cssInliner = new CssToInlineStyles();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('asset.resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function postRender(EmailInterface $email) {
    // Inline CSS. Request optimization so that the CssOptimizer performs
    // essential processing such as @import.
    $assets = (new AttachedAssets())->setLibraries($email->getLibraries());
    $css = '';
    foreach ($this->assetResolver->getCssAssets($assets, TRUE) as $file) {
      $css .= file_get_contents($file['data']);
    }

    if ($css) {
      $email->setHtmlBody($this->cssInliner->convert($email->getHtmlBody(), $css));
    }
  }

}
