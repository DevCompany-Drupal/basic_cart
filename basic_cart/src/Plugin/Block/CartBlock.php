<?php

namespace Drupal\basic_cart\Plugin\Block;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Block\BlockBase;
use Drupal\basic_cart\Utility;
use Drupal\Core\Url;

/**
 * Provides a 'Basic Cart' block.
 *
 * @Block(
 *   id = "basic_cart_cartblock",
 *   admin_label = @Translation("Basic Cart Block")
 * )
 */
class CartBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = Utility::cartSettings();

    return [
      [
        '#type' => 'markup',
        '#title' => $config->get('cart_block_title'),
        '#markup' => Utility::render(),
        '#cache' => ['max-age' => 0],
      ],
      [
        '#type' => 'link',
        '#title' => $config->get('cart_block_title'),
        '#url' => Url::fromRoute('basic_cart.cart'),
        '#options' => [
          'attributes' => [
            'class' => ['use-ajax', 'button'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => Json::encode([
              'width' => 700,
            ]),
          ],
        ],
        '#attached' => ['library' => ['core/drupal.dialog.ajax']],
      ],
    ];

  }

}
