<?php
/**
 * @package	Hikashop product order history Plugin
 * @version	1.0.1
 * @author	HikaShop
 * @copyright	(C) 2010-2023 HIKARI SOFTWARE. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?><?php
class plgHikashopProductorderListing extends JPlugin
{
	function __construct(&$subject, $config) {
		$this->paramBase = 'hikashop_product_order';
		parent::__construct($subject, $config);
	}

	function getOrdersListing($product_id, $variants = false) {
		$db = JFactory::getDBO();
		$config =& hikashop_config();
		$currencyHelper = hikashop_get('class.currency');
		jimport('joomla.html.pagination');
		$manage = hikashop_isAllowed($config->get('acl_order_manage','all'));

		if(empty($product_id))
			return;

		// check if product have variants :
		$query_variants = 'SELECT `product_id` FROM '.hikashop_table('product').' WHERE `product_parent_id` = '. $product_id;
		$db->setQuery($query_variants);
		$var_ids = $db->loadObjectList();

		$product_ids = ''; 
		$prod_ids_array = array();
		if (!empty($var_ids)) {
			foreach ($var_ids as $k => $v) {
				// String variand Ids :
				$product_ids .= $v->product_id.', ';
				$prod_ids_array[$k] = $v->product_id;
			}
		}
		// Add main product id to variant id :
		$product_ids .= $product_id;
		$prod_ids_array['main'] = $product_id;

		// Check if url have params
		$path = hikashop_currentURL();

		// Check if the URL is for the API
		$apiStart = '&params=';
		// keyword find or not in the Url? if not process set params by default :
		if(strpos($path, $apiStart) === false) {
			// Default :
			$start = 0;
		}
		else {
			// Get params from current URl :
			preg_match('#params=([^&]+)#i', $path, $matches);
			$params = $matches[1];
			$start = $params;
		}

		// Order general query :
		$query = " FROM ".hikashop_table('order')." AS b INNER JOIN ".hikashop_table('order_product')." AS ".
		"d ON b.order_id = d.order_id LEFT JOIN ".hikashop_table('user')." AS a ON b.order_user_id=a.user_id LEFT JOIN ".
		hikashop_table('users',false)." AS c ON a.user_cms_id=c.id WHERE d.product_id IN (".$product_ids.") ORDER BY b.order_created DESC";
		$db->setQuery('SELECT DISTINCT b.order_id, a.*,b.*,c.*'.$query,$start,11);
		$rows = $db->loadObjectList();

		if (!empty($rows)) {
			// Count quantity per product (AND variants !) : 
			// Get product quantity
			$array_order_id = '';
			foreach($rows as $k => $v) {
				$array_order_id .= $v->order_id.', ';
			}
			// Remove last "," :
			$array_order_id = rtrim($array_order_id, ", ");

			// Get quantity via sql query :
			$qty_query = "SELECT * FROM ".hikashop_table('order_product')." WHERE `order_id` IN (".$array_order_id.")";
			$db->setQuery($qty_query);
			$qty_rows = $db->loadObjectList();

			// Count qtity PER order by combining Main AND variant : 
			$qty_per_prod = array();
			// Count per products (& variants) quantity :
			$qty_count = array();

			foreach ($qty_rows as $k => $v) {
				// Count concerned product & variant PER order :
				$prod_id = (int)$v->product_id;
				if(!isset($qty_count[$v->order_id]))
					$qty_count[$v->order_id] = 0;

				$check = array_search($prod_id, $prod_ids_array);
				if ($check !== false)
					$qty_count[$v->order_id] = (int)$qty_count[$v->order_id] + (int)$v->order_product_quantity; 
			}
			// Only 10 order in DataBase per listing VIEW : 
			// plg's "Pagination" building :
			$link = hikashop_completeLink('product&task=edit&cid[]='.$product_id);
			// default OR first page :
			if ($start == 0) {
				$prev_stats = 'disabled ';
				$btn_prev = ''.
				'<span class="page-link" aria-hidden="true">'.
					'<span class="fas fa-angle-left" aria-hidden="true" style="width:100%;text-align:center;"></span>'.
					'<div style="text-align:center;">'.JText::_('HIKA_PREVIOUS').'</div>'.
				'</span>';
				$next_stats = '';
				$btn_next = ''.
				'<a href="'.$link.'&params=10#ord-list" class="pagenav_next_chevron page-link">'.
					'<span class="fas fa-angle-right" aria-hidden="true" style="width:100%;text-align:center;"></span>'.
					'<div style="text-align:center;">'.JText::_('NEXT').'</div>'.
				'</a>';
			}
			// Beyound first page :
			else {
				$prev_params = $start - 10;
				$next_params = $start + 10;

				$prev_stats = '';
				$next_stats = '';
				$btn_prev = ''.
				'<a href="'.$link.'&params='.$prev_params.'#ord-list" class="pagenav_prev_chevron page-link">'.
					'<span class="fas fa-angle-left" aria-hidden="true" style="width:100%;text-align:center;"></span>'.
					'<div style="text-align:center;">'.JText::_('HIKA_PREVIOUS').'</div>'.
				'</a>';
				$btn_next = ''.
				'<a href="'.$link.'&params='.$next_params.'#ord-list" class="pagenav_next_chevron page-link">'.
					'<span class="fas fa-angle-right" aria-hidden="true" style="width:100%;text-align:center;"></span>'.
					'<div style="text-align:center;">'.JText::_('NEXT').'</div>'.
				'</a>';
			}
			// Less than 10 orders in the DataBase in current listing :
			if (count($rows)<=10) {
				$next_stats = 'disabled ';
				$btn_next = ''.
				'<span class="page-link" aria-hidden="true">'.
					'<span class="fas fa-angle-right" aria-hidden="true" style="width:100%;text-align:center;"></span>'.
					'<div style="text-align:center;">'.JText::_('NEXT').'</div>'.
				'</span>';
			}
			// Total pagination html tag :
			$li_style= 'width:95px; font-weight:bold; list-style: none; display:inline-block;';
			$btn_html = ''.
			'<div style="margin-top: 10px;">'.
				'<ul class="pagination hikashop_pagination">'.
					'<li class="'.$prev_stats.' page-item" style="'.$li_style.'">'.
						$btn_prev.
					'</li>'.
					'<li class="'.$next_stats.' page-item" style="'.$li_style.'">'.
						$btn_next.
					'</li>'.
				'</ul>'.
			'</div>';

			$ret = ''.
			'<div class="hkc-xl-12 hkc-lg-12 hikashop_product_block hikashop_product_listing_order_plg hikashop_product_edit_orders_listing">'.
			'<div><a name="ord-list"></a>'.
			'<div class="hikashop_product_part_title hikashop_product_listing_order_title">'.JText::_('HIKA_STATS_LAST_ORDERS').'</div>'.
			'<table id="hikashop_product_order_listing_plg" class="adminlist table table-striped" cellpadding="1" style="margin:0px;">'.
				'<thead>'.
					'<tr>'.
						'<th class="hikashop_order_num_title title titlenum" style="text-align:center;">'.JText::_( 'HIKA_NUM' ).'</th>'.
						'<th class="hikashop_order_number_title title" style="text-align: center;">'.JText::_('ORDER_NUMBER').'</th>'.
						'<th class="hikashop_order_customer_title title">'.JText::_('CUSTOMER').'</th>'.
						'<th class="hikashop_order_date_title title">'.JText::_('DATE').'</th>'.
						'<th class="hikashop_order_modified_title title">'.JText::_('HIKA_LAST_MODIFIED').'</th>'.
						'<th class="hikashop_order_item_quantity_title title" style="text-align: center;">'.JText::_('PRODUCT_QUANTITY').'</th>'.
						'<th class="hikashop_order_status_title title">'.JText::_('ORDER_STATUS').'</th>'.
						'<th class="hikashop_order_total_title title">'.JText::_('HIKASHOP_TOTAL').'</th>'.
						'<th class="hikashop_order_id_title title">'.JText::_('ID').'</th>'.
					'</tr>'.
				'</thead>'.
				'<tfoot style="display:none;">'.
					'<tr>'.
						'<td></td>'.
					'</tr>'.
				'</tfoot>'.
				'<tbody>';

			$k = 0;
			$row_count = 0;

			if ($start == 0)
				$start = 1;
			else
				$row_count = 1;

			$display_orders = array();
			for($i = 0,$a = 10;$i<$a;$i++){
				$row =& $rows[$i];

				if (isset($rows[$i])) {	
					// Already displayed check : 
					if (!array_search($row->order_id, $display_orders)) { 
						$nb_ref = $row_count + $start;

						// Get color status :
						$orderstatusClass = hikashop_get('class.orderstatus');
						$this->orderStatuses = $orderstatusClass->getList();
						$attributes = '';
						if(!empty($this->orderStatuses[$row->order_status]->orderstatus_color))
							$attributes .= ' style="background-color:'.$this->orderStatuses[$row->order_status]->orderstatus_color.';"';

						$ret .= '
						<tr class="row'.$k.'" '.$attributes.'>'.
							'<td class="hikashop_order_num_value" style="height:74px;text-align:center;">'. $nb_ref.'</td>'.
							'<td class="hikashop_order_number_value" style="height:74px; text-align:center;">';
						if($manage){
							$ret .= '<a href="'.hikashop_completeLink('order&task=edit&cid[]='.$row->order_id.'&cancel_redirect='.urlencode(base64_encode(hikashop_currentURL()))).'" target="_blank">'.$row->order_number.'</a>';
						} else {
							$ret .= $row->order_number;
						}
						$ret .= '</td>
								<td class="hikashop_order_customer_value" style="height:74px;">';
						if(!empty($row->username))
							$ret .= $row->name.' ( '.$row->username.' )</a><br/>';

						$url = hikashop_completeLink('user&task=edit&cid[]='.$row->user_id);

						if(hikashop_isAllowed($config->get('acl_user_manage','all')))
							$ret .= $row->user_email.'<a href="'.$url.'" target="_blank"><img src="'.HIKASHOP_IMAGES.'edit.png" alt="edit"/></a>';

						$ret .= '</td>'.
							'<td class="hikashop_order_date_value" style="height:74px;">'.
								hikashop_getDate($row->order_created,'%Y-%m-%d %H:%M').
							'</td>'.
							'<td class="hikashop_order_modified_value" style="height:74px;">'.
								hikashop_getDate($row->order_modified,'%Y-%m-%d %H:%M').
							'</td>'.
							'<td class="hikashop_order_quantity_value" style="height:74px; text-align:center;">'.
								$qty_count[$row->order_id]
							.'</td>'.
							'<td class="hikashop_order_status_value" style="height:74px;">'.
								$row->order_status.
							'</td>'.
							'<td class="hikashop_order_total_value" style="height:74px;">'.
								$currencyHelper->format($row->order_full_price,$row->order_currency_id).
							'</td>'.
							'<td class="hikashop_order_id_value" style="height:74px;">'.
								$row->order_id.
							'</td>'.
						'</tr>';
						$k = 1-$k;

						// Already displayed orders :
						$display_orders[] = $row->order_id;

						$row_count++;
					}
				}
			}
			$ret .= ''.
						'</tbody>'.
					'</table>'.
					$btn_html.
				'</div>'.
			'</div>';

			return $ret;
		}
	}

	/**
	 *
	 * @param object $product
	 * @param array $html
	 */
	function onProductBlocksDisplay(&$product, &$html) {
		$ret = '';

		if(empty($product->product_id))
			return;
		$ret = $this->getOrdersListing($product->product_id);
		if(!empty($ret)) {
			$html[] = $ret;
		}
	}
}
