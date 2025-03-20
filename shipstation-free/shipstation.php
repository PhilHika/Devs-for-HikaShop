<?php
defined('_JEXEC') or die('Restricted access');
?><?php
jimport('joomla.plugin.plugin');
class plgSystemShipstation extends JPlugin {

	var $name = 'shipstation';

	/**
	 * Plugin constructor
	 * Initialize the plugin parameters if required.
	 */
	public function __construct(&$subject, $config) {
		parent::__construct($subject, $config);

		if(isset($this->params))
			return;

		$plugin = JPluginHelper::getPlugin('system', 'shipstation');
		$this->params = new JRegistry(@$plugin->params);
	}

	/**
	 * Trigger redirection
	 */
	public function afterInitialise() {
		return $this->onAfterInitialise();
	}

	/**
	 * Trigger redirection
	 */
	public function afterRoute() {
		return $this->onAfterRoute();
	}

	/**
	 * Joomla Trigger : On After Initialise
	 */
	public function onAfterInitialise() {
		$app = JFactory::getApplication();

		if(version_compare(JVERSION,'4.0','>=') && $app->isClient('administrator'))
			return true;
		if(version_compare(JVERSION,'4.0','<') && $app->isAdmin())
			return true;

		// By default we process during the After Initialise.
		// If the option is desactivate, we exit the function so the "After Route" will check it.
		//
		if(!$this->params->get('after_init', 1))
			return;

		$this->process();
	}

	/**
	 * Joomla Trigger : On After Route
	 */
	public function onAfterRoute() {
		$app = JFactory::getApplication();

		if(version_compare(JVERSION,'4.0','>=') && $app->isClient('administrator'))
			return true;
		if(version_compare(JVERSION,'4.0','<') && $app->isAdmin())
			return true;

		// By default we process during the After Initialise.
		// If the option is activate, it means that it has been already processed so we do not need to do it twice !
		//
		if($this->params->get('after_init', 1))
			return;

		$this->process();
	}

	/**
	 * Processing function
	 */
	protected function process() {

		// Retrive the called URL
		$path = $this->currentURL();

		// Check if the URL is for the API
		$apiStart = '?option=com_hikashop&ctrl=shipstation';

		// keyword find or not in the Url? if not process end here
		if(strpos($path, $apiStart) === false)
			return;

		// Load HikaShop
		//
		$hikashopHelper = rtrim(JPATH_ADMINISTRATOR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_hikashop' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'helper.php';
		if(!file_exists($hikashopHelper) || !include_once($hikashopHelper))
			exit;

		// Get data from shipstation XML
		//
		$user = $this->params->get('user', '');
		$password = $this->params->get('pass', '');
		$debug = (int)$this->params->get('debug', 0);

		// Extract username and password from the URL
		preg_match('#SS-UserName=([^&]+)#i', $path, $matches);
		$shipUser = $matches[1];
		preg_match('#SS-Password=([^&]+)#i', $path, $matches);
		$shipPass = $matches[1];

		// Check that User and Password match
		//
		if($shipUser != $user || $shipPass != $password) {
			if($debug) {
				echo 'Unexpected username and password';

				$this->writeToLog(
					'Shipstation notification with unexpected username and password'."\r\n".
					'Shipstation Username: '.$shipUser."\r\n".
					'Shipstation Password: '.$shipPass."\r\n".
					$path
				);
			}

			// Quit Shipstation plugin
			exit;
		}

		// Manage the requested action
		//
		preg_match('#action=([^&]+)#i', $path, $matches);
		$shipstatus = $matches[1];

		switch($shipstatus) {
			// Going in export mode
			case 'export':
				$this->processExport($path);
				break;

			// Ship DB need an data unpdate
			case 'shipnotify':
				$this->processShipNotify($path);
				break;

			// Invalid or unknown action.
			default:
				if($debug)
					$this->writeToLog('Unknow action/status from shipstation: ' . $shipstatus);
				break;
		}

		// We don't want that Joomla or another plugin can display some content
		exit;
	}

	/**
	 *
	 */
	private function processExport($path) {
		$debug = (int)$this->params->get('debug', 0);
		$db = JFactory::getDBO();

		preg_match('#start_date=([^&]+)#i', $path, $start);
		preg_match('#end_date=([^&]+)#i', $path, $end);
		// Extract start and end date/hour from url :
		// format is available here : https://help.shipstation.com/hc/en-us/articles/360025856192-Custom-Store-Development-Guide#UUID-ddab1c59-ea2a-2497-723c-ce5984984a49
		$start_str = DateTime::createFromFormat('m/d/Y H:i', urldecode($start[1]), new DateTimeZone('UTC'));
		if ($start_str != false)
			$start_unix = $start_str->getTimestamp();
		else
			$start_unix = false;
		
		$end_str = DateTime::createFromFormat('m/d/Y H:i', urldecode($end[1]), new DateTimeZone('UTC'));
		if ($start_str != false)
			$end_unix = $end_str->getTimestamp();
		else
			$end_unix = false;
		
		// Invalid dates : no processing.
		//
		if($start_unix === false || $start_unix === -1 || $end_unix === false || $end_unix === -1) {
			if($debug)
				$this->writeToLog('Ship Station error: impossible to get start and end dates. ('.$path.') \n\n\n');

			exit;
		}

		//ORDER SQL REQUEST & TREATMENTS :
		//SQL Query in order data base :
		//Caution : for some test the SQL is : ..." WHERE `order_created`
		//AND for products mode : the SQL is : ..." WHERE `order_invoice_created`
		//$SqlOrder = "order_created" OR "order_invoice_created"

		$sql_order = $this->params->get('sqlorder', '');
		if(empty($sql_order))
			$sql_order = 'order_invoice_created';

		if(!HIKASHOP_J30)
			$sql_order = $db->nameQuote($sql_order);
		else
			$sql_order = $db->quoteName($sql_order);

		$query = 'SELECT * FROM ' . hikashop_table('order') .
			' WHERE (' . $sql_order . ' > ' . (int) $start_unix . ') AND (' . $sql_order . ' < ' . (int)$end_unix . ') AND (`order_shipping_address_id` != 0)';
		$db->setQuery($query);
		$orders = $db->loadObjectList('order_id');

		//
		//
		if(empty($orders)) {
			if($debug) {
				$this->writeToLog(
					'no orders database matched with this dates ('.$path.')'."\r\n".
					'Start Date: '.$start[1].' // End date: '.$end[1]
				);
			}

			echo '<'.'?xml version="1.0" encoding="utf-8"?'.'><Orders></Orders>';
			exit;
		}

		//
		//
		//
		$order_id = array_keys($orders);
		if(!function_exists('hikashop_toInteger'))
			JArrayHelper::toInteger($order_id);
		else
			hikashop_toInteger($order_id);

		$address_id = array();
		$user_id = array();

		foreach($orders as $k => $v) {
			//
			$i = (int)$v->order_shipping_address_id;
			if($i > 0)
				$address_id[$i] = $i;

			//
			$i = (int)$v->order_user_id;
			if($i > 0)
				$user_id[$i] = $i;

			//
			$orders[$k]->products = array();
		}

		//
		// Loading Order Products
		//
		$query = 'SELECT * FROM ' . hikashop_table('order_product') . ' WHERE `order_id` IN (' . implode(',', $order_id ) . ')';
		$db->setQuery($query);
		$order_products = $db->loadObjectList();

		if(empty($order_products)) {
			if($debug) {
				$this->writeToLog('Ship Station error: impossible to read order_product database ('.$path.')');
			}

			echo '<'.'?xml version="1.0" encoding="utf-8"?'.'><Orders></Orders>';
			exit;
		}

		//
		// Loading Addresses
		//
		$query = 'SELECT * FROM ' . hikashop_table('address') . ' WHERE `address_id` IN (' . implode(',', $address_id) . ')';
		$db->setQuery($query);
		$addresses = $db->loadObjectList('address_id');

		if(empty($addresses)) {
			if($debug) {
				$this->writeToLog('Ship Station error: impossible to read addresses database ('.$path.')');
			}

			echo '<'.'?xml version="1.0" encoding="utf-8"?'.'><Orders></Orders>';
			exit;
		}

		//
		// Loading Users
		// According to what is made later, there is no need to load the users in the database, we just need the user_id ; element that we already have in the order.
		//
		$query = 'SELECT * FROM ' . hikashop_table('user') . ' WHERE `user_id` IN (' . implode(',', $user_id ) . ')';
		$db->setQuery($query);
		$users = $db->loadObjectList('user_id');

		if(empty($users) && $debug) {
			$this->writeToLog("Ship Station error: impossible to read user database ('.$path.')");
		}

		// Add order_product data arrays data in orders
		//
		foreach($order_products as $k => $v) {
			$orders[ (int)$v->order_id]->products[] =& $order_products[$k];
		}

		// Initialize the XML data which will be send to Shipstation
		//
		$xml_data = '<'.'?xml version="1.0" encoding="utf-8"?'.'>
<Orders>';

		// Process all orders to generate the appropriate XML
		//
		foreach($orders as $order) {

			// Skip order without products (items), invalid address or invalid user.
			//
			if(!isset($order->products) || !isset($addresses[$order->order_shipping_address_id])  ) {
				continue;
			}

			// Get address for each order
			$address =& $addresses[$order->order_shipping_address_id];

			//Get zone codeS for each order:
			$countryCode =  $this->get_zone_code_2 ($address->address_country);

			// Manage states code letters
			// ShipStation Required only 2 letter code !
			$address_state = '';
			$stateCode = $this->get_state_code($address->address_state);

			if ((!empty($stateCode)) && (strlen($stateCode) == 2))
				$address_state = '<State><![CDATA[' . $stateCode . ']]></State>';

			//Get user for each order :
			$user =& $users[$order->order_user_id];

			$shipping = '';
			if(!empty($order->order_shipping_id)){
				$pluginsShipping = hikashop_get('type.plugins');
				$pluginsShipping->type='shipping';
				$shipping = '
			<ShippingMethod><![CDATA['.$pluginsShipping->getName($order->order_shipping_method, $order->order_shipping_id).']]></ShippingMethod>';
			}
			$payment = '';
			if(!empty($order->order_payment_id)){
				$pluginsPayment = hikashop_get('type.plugins');
				$pluginsPayment->type='payment';
				$payment = '
			<PaymentMethod><![CDATA['.$pluginsPayment->getName($order->order_payment_method, $order->order_payment_id).']]></PaymentMethod>';
			}

			// Products management :
			// List all items of the current order
			//
			$items = '
				<Items>';
			$product_tax_sum = 0;
			$config = hikashop_config();
			$round_calculations = $config->get('round_calculations');

			foreach($order->products as $product) {
				if(empty($round_calculations)){
					$product_price = $product->order_product_price;
					$product_tax = $product->order_product_tax;
				} else {
					$product_price = round( ($product->order_product_price), 2);
					$product_tax = round( ($product->order_product_tax), 2);
				}
				$items .= '
				<Item>
					<SKU><![CDATA[' . $product->order_product_code . ']]></SKU>
					<Name><![CDATA[' . (string)$product->order_product_name . ']]></Name>
					<Quantity>' . (int)$product->order_product_quantity . '</Quantity>
					<UnitPrice>' .((float) $product_price + (float)$product_tax) . '</UnitPrice>
				</Item>';
				$product_tax_sum += (float)$product_tax * (int)$product->order_product_quantity;

			}

			$items .= '
				</Items>
			';
			// Tax calculation :
			$taxes_calculation = 0;
			if (!empty($order->order_payment_tax))
				$taxes_calculation = $taxes_calculation + (float)($order->order_payment_tax);
			if (!empty($order->order_discount_tax))
				$taxes_calculation = $taxes_calculation + (float)($order->order_discount_tax);
			if (!empty($order->order_shipping_tax))
				$taxes_calculation = $taxes_calculation + (float)($order->order_shipping_tax);
			$taxes_calculation = $taxes_calculation + $product_tax_sum;

			if(!empty($round_calculations)) $taxes_calculation = round($taxes_calculation, 2);

			$taxes ='
				<TaxAmount>' . $taxes_calculation . '</TaxAmount>';
				
			// initialize dates
			// date format is available here: https://help.shipstation.com/hc/en-us/articles/360025856192-Custom-Store-Development-Guide#UUID-1bb7ab47-6cf4-c6cd-0e5b-dec61e91201b_UUID-2be5b452-acd6-9a90-0a1b-bcc4109c85bd
			$order_created = DateTime::createFromFormat('U', (int)$order->order_created, new DateTimeZone('UTC'));
			$order_modified = DateTime::createFromFormat('U', (int)$order->order_modified, new DateTimeZone('UTC'));
			
			$timeZone = 'UTC';
			// contrary to the ShipStation documentation, the dates provided need to use the user timezone so that the dates displayed by ShipStation on their interface are accurate.
			$jconfig = JFactory::getConfig();
			if(!HIKASHOP_J30){
				$timeZone = $jconfig->getValue('config.offset');
			} else {
				$timeZone = $jconfig->get('offset');
			}
			$order_created->setTimezone(new DateTimeZone($timeZone));
			$order_modified->setTimezone(new DateTimeZone($timeZone));

			// -- NOTICE --
			// The order number will be use by shipstation as order identifier.
			// Later Hikashop will need the order id to find the order BUT for shipnotify will only return the <OrderNumber>
			// That's why we switch these two data.

			// Generate the Order XML data
			//
			$xml_data .= '
	<Order>
		<OrderID><![CDATA[' . $order->order_number . ']]></OrderID>
		<OrderNumber><![CDATA[' . (int)$order->order_id . ']]></OrderNumber>
		<OrderDate>' . date('m/d/Y H:i:s', (int)$order->order_created) . '</OrderDate>
		<OrderStatus><![CDATA[' . $order->order_status . ']]></OrderStatus>
		<LastModified>' . date('m/d/Y H:i:s', (int)$order->order_modified) . '</LastModified>
		<OrderTotal>' . round($order->order_full_price, 2) . '</OrderTotal>'.
		$taxes.'
		<ShippingAmount>' . round($order->order_shipping_price, 2) . '</ShippingAmount>'.
		$shipping.
		$payment.'
		<Customer>
			<CustomerCode><![CDATA[' . (int)$order->order_user_id . ']]></CustomerCode>
			<BillTo>
				<Name><![CDATA[' . $address->address_firstname.' '. $address->address_lastname . ']]></Name>
				<Email><![CDATA[' . $user->user_email . ']]></Email>
			</BillTo>
			<ShipTo>
				<Name><![CDATA[' . $address->address_firstname.' '. $address->address_lastname . ']]></Name>'.
				(!empty($address->address_company) ? '<Company><![CDATA[' . $address->address_company . ']]></Company>' : '' ).'
				<Address1><![CDATA[' . $address->address_street . ']]></Address1>
				<Address2><![CDATA[' . $address->address_street2 . ']]></Address2>
				<City><![CDATA[' . $address->address_city . ']]></City>'.
					$address_state.'
				<PostalCode><![CDATA[' . $address->address_post_code . ']]></PostalCode>
				<Country><![CDATA[' . $countryCode . ']]></Country>'.
				(!empty($address->address_telephone) ? '<Phone><![CDATA[' . $address->address_telephone . ']]></Phone>' : '' ).'
			</ShipTo>
		</Customer>'.
		strval($items).
	'</Order>';
		}
		unset($address);
		unset($countryCode);

		// Finalize the XML content
		$xml_data .= '
</Orders>';

		// Store the XML data into the log file
		if($debug) {
			$this->writeToLog(
				'Sent XML to shipstation'."\r\n".
				htmlentities($xml_data)
			);
		}

		// Send to shipstation XML response:
		echo $xml_data;
	}

	/**
	 *
	 */
	private function processShipNotify($path) {
		$debug = (int)$this->params->get('debug', 0);

		//Get the XML :
		$postdata = file_get_contents('php://input');
		// Read the "order_number" in URL ; which will be the order_id in HikaShop
		//
		preg_match('#order_number=([^&]+)#i', $path, $matches);
		$order_id = (int)$matches[1];
		// Read the "orderID" in Shipstation data ; which will be the order_number in HikaShop
		//
		preg_match('#<orderID>([^<]+)#i', $postdata, $matches);
		$order_number = $matches[1];

		// Get the order with the order_number from shipstation (order_id for hikashop)
		//
		$orderClass = hikashop_get('class.order');
		$order = $orderClass->get( (int)$order_id );

		if(empty($order)) {
			if($debug) {
				$this->writeToLog(
					'No Order found link to order id from shipstation'."\r\n".
					'view on order id shipstation: '. (int)$order_id
				);
			}

			//
			// TODO : Are you sure that it should be displayed as content and not as header ?
			//
			echo 'HTTP/1.0 200 OK';
			exit;
		}

		//
		//
		preg_match('#<TrackingNumber>([^<]+)#i', $postdata, $matches);
		$TrackingNumber = trim($matches[1]);

		preg_match('#<Carrier>([^<]+)#i', $postdata, $matches);
		$Carrier = trim($matches[1]);

		preg_match('#<Service>([^<]+)#i', $postdata, $matches);
		$Service = trim($matches[1]);

		// Check if order number & customer name match with hikashop
		//
		if($order_number != $order->order_number) {
			$this->writeToLog(
				'Order number mismatch!'."\r\n".
				'from hikashop DB: '.$order->order_number."\r\n".
				'from shipstation: '.$order_number
			);
		}
		// Write result and details in log file :
		if($debug)
			$this->writeToLog('order No. : ' . $order->order_id . "\r\n" . $Carrier . '::' . $Service . '::' . $TrackingNumber . "\r\n");

		// Get existing order shipping params :
		$db = JFactory::getDBO();
		$query = 'SELECT order_shipping_params FROM ' . hikashop_table('order') . ' WHERE `order_id` = '.(int)$order->order_id;
		$db->setQuery($query);
		$object_order_shipping_params = $db->loadObjectList();
		$object = hikashop_unserialize ($object_order_shipping_params[0]->order_shipping_params);

		// Shipstation data already exist :
		$new_data = 0;
		if (isset($object->shipstation)) {
			// Compare existing Shipstation data :
			if ($object->shipstation->carrier != $Carrier) {
				$object->shipstation->carrier = $Carrier;
				$new_data++;
			}
			if ($object->shipstation->service != $Service) {
				$object->shipstation->service = $Service;
				$new_data++;
			}
			if ($object->shipstation->track_number != $TrackingNumber) {
				$object->shipstation->track_number = $TrackingNumber;
				$new_data++;
			}
		}
		// Update older Shipstation data :
		if ($new_data > 0) {
			$shipstation = $object->shipstation;
		}
		// Or not ? => Data creation :
		else {
			$shipstation = new stdClass;
			$shipstation->carrier = $Carrier;
			$shipstation->service = $Service;
			$shipstation->track_number = $TrackingNumber;
		}
		// No order shipping params returned :
		if ($object == FALSE) {
			// No shipping params => No object is return in $object
			$new_object = new stdClass();
			$new_object->shipstation = $shipstation;
		}
		// order_shipping_params already exist :
		else {
			$new_object = $object;
			$new_object->shipstation = $shipstation;
		}

		// Update or not ?
		if (isset($new_object)) {
			$update = new stdClass();
			$update->order_id = $order->order_id;
			$update->order_shipping_params = $new_object;
			$update->history = new stdClass();

			$order_status = trim($this->params->get('orderstatus', ''));
			$notify_customer = (int)$this->params->get('customermail', 0);
			if(!empty($order_status)) {
				$update->order_status = $order_status;
				$update->history->history_notified = $notify_customer;
			}
			// Update order data :
			$orderClass->save($update);

			if($debug) {
				$this->writeToLog(
					'Shipstation have updated Order with order id :'.(int)$order_id."\r\n".
					'carrier ='. $Carrier."\r\n".
					'service ='. $Service."\r\n".
					'track_number ='. $TrackingNumber."\r\n"
				);
			}
		}
		else {
			if($debug) {
				$this->writeToLog(
					'Shipstation was not able to updated Order with order id :'.(int)$order_id."\r\n".
					'For an unknow reason, impossible to get data to update order '."\r\n"
				);
			}
		}
		echo 'HTTP/1.0 200 OK';
	}

	/**
	 * sent data to customer email :
	 */
	public function onAfterOrderProductsListingDisplay(&$order, $view_params) {
		// Frontend Order/Show :
		if (isset($order->order_shipping_params->shipstation)) {
			$shipstation_params = $order->order_shipping_params->shipstation;
			// Build strings info :
			$shipstation_str =
				'<p class="hika_cpanel_order_shipstation" style="margin: 5px 0px 0px 0px;">'
					. '<span style="font-weight:bold;">'.JText::_('CARRIER').' : </span>'
					. '<span>'.$shipstation_params->carrier.'</span>'
				. '</p>'.
				'<p class="hika_cpanel_order_shipstation" style="margin: 0px;">'
					. '<span style="font-weight:bold;">'.JText::_('SERVICE').' : </span>'
					. '<span>'.$shipstation_params->service.'</span>'
				. '</p>'.
				'<p class="hika_cpanel_order_shipstation" style="margin: 0px;">'
					. '<span style="font-weight:bold;">'.JText::_('TRACKING_NUMBER').' : </span>'
					. '<span>'.$shipstation_params->track_number.'</span>'
				. '</p>';
		}else{
			$db = JFactory::getDBO();

			// Load the history data
			//
			// TODO : Because the order status is an option in the plugin, it should be the same here or there should be a link.
			// Loading only the first element might not be the perfect solution, specially if the admin change the order in the backend.
			//
			$query = 'SELECT history_data FROM ' . hikashop_table('history') .
				' WHERE history_new_status = '.$db->Quote('shipped').' AND history_data != '.$db->Quote('').' AND history_order_id = ' .(int)$order->order_id.
				' ORDER BY history_created ASC';
			$db->setQuery($query);
			$history = $db->loadResult();
			if(empty($history))
				return;
			list($Carrier, $Service, $TrackingNumber) = explode('::', $history, 3);
			$shipstation_str = JText::_('CARRIER').': '.$Carrier.' '.JText::_('SERVICE').': '.$Service.' '.JText::_('TRACKING_NUMBER').': '.$TrackingNumber.'<br />';
		}

		// New version display :
		if($view_params == 'email_notification_html' || $view_params == 'order_back_invoice' || $view_params == 'order_front_show')
			echo $shipstation_str;
	}

	/**
	 * For dynamic translation of email and back end orders histories data
	 */
	public function onHistoryDisplay(&$histories) {

		// Display only for email, if there is some data to display
		//
		$option = @$_REQUEST['option'];
		$ctrl = @$_REQUEST['ctrl'];
		$task = @$_REQUEST['task'];

		if(empty($histories) || $option != 'com_hikashop' || $ctrl != 'order' || $task != 'show')
			return;

		foreach($histories as $k => $history) {
			if(empty($history->history_data) || strpos($history->history_data, '::') === false)
				continue;

			// Construct string with shipstation data:
			list($Carrier, $Service, $TrackingNumber) = explode('::', $history->history_data, 3);
			$histories[$k]->history_data = JText::_('CARRIER').': '.$Carrier.'<br/>' .
				JText::_('SERVICE').': '.$Service.'<br/>' .
				JText::_('TRACKING_NUMBER').': '.$TrackingNumber.'<br />';
		}
	}

	/**
	 * Get the current url
	 * From HikaShop helper.php
	 */
	private function currentURL($safe = true) {
		if(!empty($_SERVER["REDIRECT_URL"]) && preg_match('#.*index\.php$#',$_SERVER["REDIRECT_URL"])
		  && empty($_SERVER['QUERY_STRING']) && (empty($_SERVER['REDIRECT_QUERY_STRING']) ||
		  strpos($_SERVER['REDIRECT_QUERY_STRING'],'&') === false) && !empty($_SERVER["REQUEST_URI"])) {

			$requestUri = $_SERVER["REQUEST_URI"];
			if (!empty($_SERVER['REDIRECT_QUERY_STRING'])) $requestUri = rtrim($requestUri,'/').'?'.$_SERVER['REDIRECT_QUERY_STRING'];
		}
		elseif(!empty($_SERVER["REDIRECT_URL"]) && (isset($_SERVER['QUERY_STRING']) || isset($_SERVER['REDIRECT_QUERY_STRING']))) {
			$requestUri = $_SERVER["REDIRECT_URL"];
			if(!empty($_SERVER['REDIRECT_QUERY_STRING']))
				$requestUri = rtrim($requestUri,'/').'?'.$_SERVER['REDIRECT_QUERY_STRING'];
			elseif (!empty($_SERVER['QUERY_STRING']))
				$requestUri = rtrim($requestUri,'/').'?'.$_SERVER['QUERY_STRING'];
		}
		elseif(isset($_SERVER["REQUEST_URI"])) {
			$requestUri = $_SERVER["REQUEST_URI"];
		}
		else {
			$requestUri = $_SERVER['PHP_SELF'];
			if(!empty($_SERVER['QUERY_STRING']))
				$requestUri = rtrim($requestUri,'/').'?'.$_SERVER['QUERY_STRING'];
		}

		$result = 'http://'.$_SERVER["HTTP_HOST"].$requestUri;
		if($safe)
			$result = str_replace(array('"',"'",'<','>',';'),array('%22','%27','%3C','%3E','%3B'),$result);
		return $result;
	}

	/**
	 * Add Order data through ExtraData on Cpanel order listing and order view
	 */
	public function onHikashopBeforeDisplayView(&$element) {
		$viewName = $element->getName();
		$layoutName = $element->getLayout();

		// Frontend user / cpanel_orders : $order->extraData->afterInfo
		if($viewName == 'user' || $layoutName == 'cpanel') {
			if(isset($element->cpanel_data->cpanel_orders)) {
				foreach ($element->cpanel_data->cpanel_orders as $order) {
					if(isset($order->order_shipping_params->shipstation)) {
						// Build string info :
						$shipstation_str = '<div class="hika_cpanel_order_shipstation">'.
							'<p style="display:inline-block;margin:0px;">'
								. '<span style="font-weight:bold;">'.JText::_('CARRIER').' : </span>'
								. '<span>'.$order->order_shipping_params->shipstation->carrier.'</span>'
							. '</p>'.
							'<p style="display:inline-block;margin:0px;"><span>'
								. '<span style="font-weight:bold;">'.JText::_('SERVICE').' : </span>'
								. '<span>'.$order->order_shipping_params->shipstation->service.'</span>'
							. '</p>'.
							'<p style="display:inline-block;margin:0px;">'
								. '<span style="font-weight:bold;">'.JText::_('TRACKING_NUMBER').' : </span>'
								. '<span>'.$order->order_shipping_params->shipstation->track_number.'</span>'
							. '</p>'.
						'</div>';

						if(!isset($order->extraData))
							$order->extraData = new stdClass();

						if(!isset($order->extraData->afterInfo))
							$order->extraData->afterInfo = array();
						$order->extraData->afterInfo[] = $shipstation_str;
					}
				}
			}
		}
		// Frontend order / show & order / listing
		if ($viewName == 'order') {
			// Listing order view :
			// $order->extraData->bottomLeft
			// $order->extraData->bottomMiddle
			// $order->extraData->bottomRight
			if ($layoutName == 'listing' && isset($element->rows) && isset($element->row)) {
				$row = $element->row;

				foreach($element->rows as $order) {
					if ($row->order_id == $order->order_id && isset($row->order_shipping_params->shipstation)) {
						// Build strings info :
						$shipstation_str_right =
							'<p class="hika_cpanel_order_shipstation" style="margin: 15px 0px 0px 0px;">'
								. '<p style="font-weight:bold;margin:0px;">'.JText::_('CARRIER').' : </p>'
								. '<span>'.$row->order_shipping_params->shipstation->carrier.'</span>'
							. '</p>';
						$shipstation_str_middle =
							'<p class="hika_cpanel_order_shipstation" style="margin: 15px 0px 0px 0px;"><span>'
								. '<p style="font-weight:bold;margin:0px;">'.JText::_('SERVICE').' : </p>'
								. '<span>'.$row->order_shipping_params->shipstation->service.'</span>'
							. '</p>';
						$shipstation_str_left =
							'<p class="hika_cpanel_order_shipstation" style="margin: 15px 0px 0px 0px;">'
								. '<p style="font-weight:bold;margin:0px;">'.JText::_('TRACKING_NUMBER').' : </p>'
								. '<span>'.$row->order_shipping_params->shipstation->track_number.'</span>'
							. '</p>';

						if(!isset($order->extraData))
							$order->extraData = new stdClass();
						if(!isset($order->extraData->bottomLeft))
							$order->extraData->bottomLeft = array();
						if(!isset($order->extraData->bottomMiddle))
							$order->extraData->bottomMiddle = array();
						if(!isset($order->extraData->bottomRight))
							$order->extraData->bottomRight = array();

						$order->extraData->bottomLeft[] = $shipstation_str_left;
						$order->extraData->bottomMiddle[] = $shipstation_str_middle;
						$order->extraData->bottomRight[] = $shipstation_str_right;
					}
				}
			}
		}
		// Backend order / show_additional :
		if($viewName == 'order' || $layoutName == 'show_additional')  {

			// Create an order to provide to show_additional
			if(isset($element->order->order_shipping_params->shipstation)) {
				$shipstation_data = array(
					'carrier_ship' => array(
						"title" => "CARRIER",
						"data" => $element->order->order_shipping_params->shipstation->carrier
					),
					'service_ship' => array(
						'title' => 'SERVICE',
						'data' => $element->order->order_shipping_params->shipstation->service
					),
					'track_number_ship' => array(
						'title' =>'TRACKING_NUMBER',
						'data' => $element->order->order_shipping_params->shipstation->track_number
					)
				);

				if(!isset($element->extra_data)) {
					$element->extra_data = array();
				}
				$element->extra_data['additional'] = $shipstation_data;
			}
		}
	}
	/**
	 * Add error data in the log files (debug)
	 * From HikaShop helper.php
	 */
	private function writeToLog($data = null) {
		$dbg = ($data === null) ? ob_get_clean() : $data;
		if(!empty($dbg)) {
			$dbg = '-- ' . date('m.d.y H:i:s') . ' --'. (empty($this->name) ? ('['.$this->name.']') : '') . "\r\n" . $dbg;

			jimport('joomla.filesystem.file');
			$config = hikashop_config();
			$file = $config->get('payment_log_file', '');
			$file = rtrim(JPath::clean(html_entity_decode($file)), DS . ' ');
			if(!preg_match('#^([A-Z]:)?/.*#',$file) && (!$file[0] == '/' || !file_exists($file)))
				$file = JPath::clean(HIKASHOP_ROOT . DS . trim($file, DS . ' '));
			if(!empty($file) && defined('FILE_APPEND')) {
				if(!file_exists(dirname($file))) {
					jimport('joomla.filesystem.folder');
					JFolder::create(dirname($file));
				}
				file_put_contents($file, $dbg, FILE_APPEND);
			}
		}
		if($data === null)
			ob_start();
	}

	function get_zone_code_2 ($address_country) {

		//Get country zone code:
		$class =hikashop_get('class.zone');
		$zone = $class->get($address_country);
		$zone = $zone->zone_code_2;

		return ($zone);
	}

	function get_state_code ($address_state) {

		//Get country zone code 2:
		$class =hikashop_get('class.zone');
		$zone = '';
		$zone_data = $class->get($address_state);
		if (!empty($zone_data))
			$zone = $zone_data->zone_code_2;

		if (empty($zone)) {
			//Get country zone code 3:
			$zone_data = $class->get($address_state);
			if (!empty($zone_data))
				$zone = $zone_data->zone_code_3;
		}
		return ($zone);
	}
}