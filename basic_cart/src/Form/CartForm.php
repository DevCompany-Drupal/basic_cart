<?php

namespace Drupal\basic_cart\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\basic_cart\Utility;
use Drupal\Core\Url;

/**
 * Cart page form.
 */
class CartForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {

    return 'basic_cart_cart_form';

  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::config('basic_cart.settings');
    $form['#theme'] = 'cart_form';
    $cart = Utility::getCart();
    $config = Utility::cartSettings();

    // And now the form.
    $form['cartcontents'] = [
      // Make the returned array come back in tree form.
      '#tree' => TRUE,
    ];
    // Cart elements.
    foreach ($cart['cart_quantity'] as $nid => $quantity) {
      $variable = Utility::quantityPrefixData($nid);

      $form['cartcontents'][$nid] = [
        '#type' => $config->get('quantity_status') ? 'textfield' : 'markup',
        '#size' => 2,
        '#quantity_id'  => $nid,
        "#suffix" => Utility::render('basic-cart-quantity-suffix.html.twig', $variable),
        "#prefix" => Utility::render('basic-cart-quantity-prefix.html.twig', $variable),
        '#default_value' => $quantity,
      ];
    }
    $form['total_price'] = [
      '#markup' => Utility::render('total-price-markup.html.twig', Utility::getTotalPriceMarkupData()),
    ];

    // Buttons.
    $form['buttons'] = [
      '#tree' => TRUE,
    ];

    if ($config->get('order_status')) {
      $form['buttons']['checkout'] = [
        '#type' => 'link',
        '#title' => $this->t('Checkout'),
        '#url' => Url::fromRoute('basic_cart.checkout'),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => 700,
          ]),
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = Utility::cartSettings();
    if ($config->get('quantity_status')) {
      foreach ($form_state->getValue('cartcontents') as $nid => $value) {
        $quantity = (int) $value;
        if ($quantity > 0) {
          $_SESSION['basic_cart']['cart_quantity'][$nid] = $quantity;
        }
        // If the quantity is zero, we just remove the node from the cart.
        elseif ($quantity == 0) {
          unset($_SESSION['basic_cart']['cart'][$nid]);
          unset($_SESSION['basic_cart']['cart_quantity'][$nid]);
        }
      }
      Utility::cartUpdatedMessage();
    }
    $config = Utility::cartSettings();
    if ($config->get('order_status') && $form_state->getValue('checkout')) {
      $url = new Url('basic_cart.checkout');
      $form_state->setRedirectUrl($url);
    }
  }

}
