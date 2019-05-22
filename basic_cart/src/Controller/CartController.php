<?php

namespace Drupal\basic_cart\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\node\Entity\NodeType;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\basic_cart\Utility;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Contains the cart controller.
 */
class CartController extends ControllerBase {

  /**
   * Get title of cart page.
   *
   * @return text
   *   Return the title
   */
  public function getCartPageTitle() {
    $config = Utility::cartSettings();
    $message = $config->get('cart_page_title');
    return $this->t($message);
  }

  /**
   * Cart Page.
   *
   * @return array
   *   Returns Drupal cart form or null
   */
  public function cart() {

    \Drupal::service('page_cache_kill_switch')->trigger();
    $utility = new Utility();
    $cart = $utility::getCart();
    $config = $utility::cartSettings();
    $request = \Drupal::request();

    if ($route = $request->attributes->get(RouteObjectInterface::ROUTE_OBJECT)) {
      $route->setDefault('_title', t($config->get('cart_page_title')));
    }

    return !empty($cart['cart']) ? \Drupal::formBuilder()->getForm('\Drupal\basic_cart\Form\CartForm') : array('#type' => 'markup', '#markup' => t($config->get('empty_cart')));

  }

  /**
   * Remove node from cart.
   *
   * @param int $nid
   *   Node id of the cart content.
   *
   * @return Object
   *   Redirect to HTTP_REFERER
   */
  public function removeFromCart($nid) {
    \Drupal::service('page_cache_kill_switch')->trigger();
    $response = new AjaxResponse();
    Utility::removeFromCart($nid);
    $quantity = Utility::cartCount();

    $selector = '.basic-cart-cart-form';
    $selector2 = '.basic_cart-grid';
    $selector3 = '.basic_cart-circles';
    $rebuild_data = $this->cart();
    $rebuild_data2 = Utility::render('basic-cart-cart-template.html.twig');
    $rebuild_data3 = "<span class='basic_cart-circles basic_cart-circle-32-26'>$quantity</span>";
    $response->addCommand(new ReplaceCommand($selector3, $rebuild_data3, $settings = NULL));
    $response->addCommand(new ReplaceCommand($selector2, $rebuild_data2, $settings = NULL));
    $response->addCommand(new ReplaceCommand($selector, $rebuild_data, $settings = NULL));
    return $response;
  }

  public function plusItemToCart($nid) {
    \Drupal::service('page_cache_kill_switch')->trigger();
    $query = \Drupal::request()->query;
    $param['entitytype'] = $query->get('entitytype') ? $query->get('entitytype') : "node";
    $param['quantity'] = $query->get('quantity') ? (is_numeric($query->get('quantity')) ? (int) $query->get('quantity') : 1) : 1;
    Utility::addToCart($nid, $param);

    $quantity = Utility::cartCount();

    $response = new AjaxResponse();
    $selector = '.basic-cart-cart-form';
    $selector2 = '.basic_cart-circles';
    $selector3 = '.basic_cart-grid';
    $rebuild_data = $this->cart();
    $rebuild_data2 = "<span class='basic_cart-circles basic_cart-circle-32-26'>$quantity</span>";
    $rebuild_data3 = Utility::render('basic-cart-cart-template.html.twig');
    $response->addCommand(new ReplaceCommand($selector3, $rebuild_data3, $settings = NULL));
    $response->addCommand(new ReplaceCommand($selector, $rebuild_data, $settings = NULL));
    $response->addCommand(new ReplaceCommand($selector2, $rebuild_data2, $settings = NULL));
    return $response;
  }

  public function minItemToCart($nid) {
    \Drupal::service('page_cache_kill_switch')->trigger();
    $quantity = NULL;
    if (isset($_SESSION['basic_cart']['cart_quantity'][$nid])) {
      $quantity = $_SESSION['basic_cart']['cart_quantity'][$nid];
      if ($quantity > 1) {
        $_SESSION['basic_cart']['cart_quantity'][$nid] = $quantity - 1;
      } else {
        Utility::removeFromCart($nid);
      }
    }

    $quantity = Utility::cartCount();

    $response = new AjaxResponse();
    $selector = '.basic-cart-cart-form';
    $selector2 = '.basic_cart-circles';
    $selector3 = '.basic_cart-grid';
    $rebuild_data = $this->cart();
    $rebuild_data2 = "<span class='basic_cart-circles basic_cart-circle-32-26'>$quantity</span>";
    $rebuild_data3 = Utility::render('basic-cart-cart-template.html.twig');
    $response->addCommand(new ReplaceCommand($selector3, $rebuild_data3, $settings = NULL));
    $response->addCommand(new ReplaceCommand($selector, $rebuild_data, $settings = NULL));
    $response->addCommand(new ReplaceCommand($selector2, $rebuild_data2, $settings = NULL));
    return $response;
  }

  /**
   * Add node to cart.
   *
   * @param int $nid
   *   Node id of the cart content.
   *
   * @return Object
   *   Json Object response with html div text
   **/
  public function addToCart($nid) {
    \Drupal::service('page_cache_kill_switch')->trigger();
    $query = \Drupal::request()->query;
    $config = Utility::cartSettings();
    $param['entitytype'] = $query->get('entitytype') ? $query->get('entitytype') : "node";
    $param['quantity'] = $query->get('quantity') ? (is_numeric($query->get('quantity')) ? (int) $query->get('quantity') : 1) : 1;
    Utility::addToCart($nid, $param);
    if ($config->get('add_to_cart_redirect') != "<none>" && trim($config->get('add_to_cart_redirect'))) {

    }
    else {
      \Drupal::messenger()->messagesByType('status');
      $response = new \stdClass();
      $response->status = TRUE;
      $response->count = Utility::cartCount();
      $response->text = '<p class="messages messages--status">' . t($config->get('added_to_cart_message')) . '</p>';
      $response->id = 'ajax-addtocart-message-' . $nid;
      $response->block = Utility::render();
      return new JsonResponse($response);
    }

  }

  /**
   * Checkout Page.
   *
   * @return array
   *   Returns Drupal checkout form or redirect
   */
  public function checkout() {
    $utility = new Utility();
    $cart = $utility::getCart();
    if (isset($cart['cart']) && !empty($cart['cart'])) {
      $type = NodeType::load("basic_cart_order");
      $node = $this->entityTypeManager()->getStorage('node')->create(array(
        'type' => $type->id(),
      ));

      $node_create_form = $this->entityFormBuilder()->getForm($node);

      return array(
        '#type' => 'markup',
        '#markup' => render($node_create_form),
      );
    }
    else {

      $url = new Url('basic_cart.cart');
      return new RedirectResponse($url->toString());
    }
  }

  /**
   * Order create page with form.
   *
   * @return array
   *   Returns Drupal create form of order content type
   */
  public function orderCreate() {
    $type = NodeType::load("basic_cart_order");
    $node = $this->entityTypeManager()->getStorage('node')->create(array(
      'type' => $type->id(),
    ));

    $node_create_form = $this->entityFormBuilder()->getForm($node);

    return array(
      '#type' => 'markup',
      '#markup' => render($node_create_form),
    );
  }

  /**
   * Add node to cart.
   *
   * @param int $nid
   *   Node id of the cart content.
   *
   * @return Object
   *   Redirect Object response
   */
  public function addToCartNoRedirect($nid) {
    \Drupal::service('page_cache_kill_switch')->trigger();
    $query = \Drupal::request()->query;
    $config = Utility::cartSettings();
    $param['entitytype'] = $query->get('entitytype') ? $query->get('entitytype') : "node";
    $param['quantity'] = $query->get('quantity') ? (is_numeric($query->get('quantity')) ? (int) $query->get('quantity') : 1) : 1;
    Utility::addToCart($nid, $param);
    return new RedirectResponse(Url::fromUserInput("/" . trim($config->get('add_to_cart_redirect'), '/'))->toString());
  }

  /**
   * Get title of thank you page.
   *
   * @return text
   *   Return the title
   */
  public function getThankyouPageTitle() {
    $utility = new Utility();
    $config = $utility->checkoutSettings();
    $message = $config->get('thankyou')['title'];
    return $this->t($message);
  }

  /**
   * Thankyou Page.
   *
   * @return array
   *   Returns Drupal markup
   */
  public function thankYouPage() {
    $utility = new Utility();
    $config = $utility->checkoutSettings();
    return array(
      '#type' => 'markup',
      '#theme' => 'basic_cart_thank_you',
      '#basic_cart' => ['title' => $config->get('thankyou')['title'], 'text' => $config->get('thankyou')['text']],
    );
  }

}
