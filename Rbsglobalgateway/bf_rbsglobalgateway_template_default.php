<?php
/**
 * @package	Hikashop bf_rbsglobalgateway Plugin
 * @version	3.0.2
 * @author	HikaShop
 * @copyright	(C) 2010-2023 HIKARI SOFTWARE. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?><?php
	echo '<div class="rbs-wrapper">';
	echo '<table class="rbs-order">';
	$siteLogo = rbsglobalgateway_helper::getSiteLogo($params);
	if (!empty($siteLogo)) {
		echo '<tr>';
		echo '<td class="rbs-site_logo" colspan="4" style="text-align:center;">';
		$app = JFactory::getApplication();
		echo '<img src="' . $siteLogo . '" alt="' . htmlspecialchars($app->getCfg('sitename')) . '" />';
		echo '</td>';
		echo '</tr>';
	}
	echo '<tr>';
	echo '<tr class="rbs-order-header">';
	echo '<th class="rbs-product_code">Product Code</th>';
	echo '<th class="rbs-product_name">Product Name</th>';
	echo '<th class="rbs-product_quantity">Quantity</th>';
	echo '<th class="rbs-product_price">Total Price</th>';
	echo '</tr>';
	$row = 0;
	$tax_cart = 0;
	$config =& hikashop_config();
	$group = $config->get('group_options',0);
	foreach($order->cart->products as $product){
		if($group && $product->order_product_option_parent_id) continue;
		echo '<tr class="rbs-product-item rbs-product-row-' . $row . '">';
		echo '<td class="rbs-product_code">' . $product->order_product_code . '</td>';
		echo '<td class="rbs-product_name">' . $product->order_product_name . '</td>';
		echo '<td class="rbs-product_quantity">' . $product->order_product_quantity . '</td>';
		echo '<td class="rbs-product_price">' . rbsglobalgateway_helper::product_price($params, $product, $tax_cart) . '</td>';
		echo '</tr>';
		$row = ($row) ? 0 : 1;
	}
	if(!empty($order->order_shipping_price) || !empty($order->cart->shipping->shipping_name)){
		echo '<tr class="rbs-product-item rbs-product-row-' . $row . '">';
		echo '<td class="rbs-product_code">' . JText::_('HIKASHOP_SHIPPING') . '</td>';
		echo '<td class="rbs-product_name">' . rbsglobalgateway_helper::shipping_name($params, $order) . '</td>';
		echo '<td class="rbs-product_quantity">1</td>';
		echo '<td class="rbs-product_price">' . rbsglobalgateway_helper::shipping_price($params, $order, $tax_cart) . '</td>';
		echo '</tr>';
		$row = ($row) ? 0 : 1;
	}
	if(bccomp($tax_cart,0,5)) {
		echo '<tr class="rbs-product-item rbs-order-tax rbs-product-row-' . $row . '">';
		echo '<td class="rbs-product_code">&nbsp;</td>';
		echo '<td class="rbs-product_name">Tax</td>';
		echo '<td class="rbs-product_quantity">&nbsp;</td>';
		echo '<td class="rbs-product_price">' . rbsglobalgateway_helper::formatPrice($params, $tax_cart) . '</td>';
		echo '</tr>';
		$row = ($row) ? 0 : 1;
	}
	if(!empty($order->cart->coupon->discount_value)){
		echo '<tr class="rbs-product-item rbs-order-coupon rbs-product-row-' . $row . '">';
		echo '<td class="rbs-product_code">&nbsp;</td>';
		echo '<td class="rbs-product_name">Coupon</td>';
		echo '<td class="rbs-product_quantity">&nbsp;</td>';
		echo '<td class="rbs-product_price">' . rbsglobalgateway_helper::formatPrice($params, rbsglobalgateway_helper::roundPrice($params, $order->cart->coupon->discount_value)) . '</td>';
		echo '</tr>';
		 $row = ($row) ? 0 : 1;
	}
	echo '<tr class="rbs-product-item rbs-order-total rbs-product-row-' . $row . '">';
	echo '<td class="rbs-product_code">&nbsp;</td>';
	echo '<td class="rbs-product_name">Total Cost</td>';
	echo '<td class="rbs-product_quantity">&nbsp;</td>';
	echo '<td class="rbs-product_price">' . rbsglobalgateway_helper::formatPrice($params, rbsglobalgateway_helper::roundPrice($params, $order->order_full_price)) . '</td>';
	echo '</tr>';
	echo '<tr><td colspan="4">';
	rbsglobalgateway_helper::cancelButton($params, $order);
	echo '</td></tr>';
	if(!empty($params->address_type)) {
		switch ($params->address_type) {
			case 'billing';
				rbsglobalgateway_helper::loadAddress($order, 'billing');
				echo rbsglobalgateway_helper::contentAddress($params, $user, $order, 'billing_address', 4);
				break;
			case 'shipping';
				rbsglobalgateway_helper::loadAddress($order, 'shipping');
				echo rbsglobalgateway_helper::contentAddress($params, $user, $order, 'shipping_address', 4);
				break;
			case 'billing,shipping';
			case 'shipping,billing';
				rbsglobalgateway_helper::loadAddress($order, 'billing');
				echo rbsglobalgateway_helper::contentAddress($params, $user, $order, 'billing_address', 4);
				rbsglobalgateway_helper::loadAddress($order, 'shipping');
				echo rbsglobalgateway_helper::contentAddress($params, $user, $order, 'shipping_address', 4);
				break;
		}
	}
	echo rbsglobalgateway_helper::contentEmailAddress($user, $order, 4);
	$contactInformation = $params->contactInformation;
	if (!empty($contactInformation)) {
		$contactInformation = preg_replace("/[\n]|[\n\r]/", '<br />', htmlspecialchars($contactInformation));
		echo '<tr class="rbs-row-spacer"><td colspan="4" class="rbs-row-spacer">&nbsp;</td></tr>';
		echo '<tr class="rbs-contact-info-header"><td colspan="4" class="rbs-contact-info-header">Our Contact Information:</td></tr>';
		echo '<tr class="rbs-contact-info"><td colspan="4" class="rbs-contact-info">' . $contactInformation . '</td></tr>';
	}
	$billingNotice = $params->billingNotice;
	if (!empty($billingNotice)) {
		$billingNotice = preg_replace("/[\n]|[\n\r]/", '<br />', htmlspecialchars($billingNotice));
		echo '<tr class="rbs-row-spacer"><td colspan="4" class="rbs-row-spacer">&nbsp;</td></tr>';
		echo '<tr class="rbs-billing-notice-header"><td colspan="4" class="rbs-billing-notice-header">Billing Notice:</td></tr>';
		echo '<tr class="rbs-billing-notice"><td colspan="4" class="rbs-billing-notice">' . $billingNotice . '</td></tr>';
	}
	echo '</table>';
	rbsglobalgateway_helper::worldpayHeader();
	echo '</div>';
?>
