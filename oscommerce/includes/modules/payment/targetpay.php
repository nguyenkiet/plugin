<?php
/**
 * TargetPay Payment Module for osCommerce
*
* @copyright Copyright 2013-2014 Yellow Melon
* @copyright Portions Copyright 2013 Paul Mathot
* @copyright Portions Copyright 2003 osCommerce
* @license   see LICENSE.TXT
*/

require_once"targetpay/TargetPayIdeal.class.php";

$ywincludefile = realpath(dirname(__FILE__).'/../../extra_datafiles/targetpay.php');
require_once $ywincludefile;

$ywincludefile = realpath(dirname(__FILE__).'/../../languages/dutch/modules/payment/targetpay.php');
require_once $ywincludefile;

$ywincludefile = realpath(dirname(__FILE__).'/targetpay/targetpay.class.php');
require_once $ywincludefile;

class targetpay
{
	var $code, $title, $description, $enabled;

	var $rtlo;

	var $passwordKey;

	var $merchantReturnURL;
	var $expirationPeriod;
	var $transactionDescription;
	var $transactionDescriptionText;

	var $returnURL;
	var $reportURL;

	var $transactionID;
	var $purchaseID;
	var $directoryUpdateFrequency;

	var $error;
	var $bankUrl;

	var $targetpaymodule;

	/**
	 * @method targetpay inits the module
	 */
	function targetpay()
	{
		global $order;

		$this->code = 'targetpay';
		$this->title = MODULE_PAYMENT_TARGETPAY_TEXT_TITLE;
		$this->description = MODULE_PAYMENT_TARGETPAY_TEXT_DESCRIPTION;
		$this->sort_order = MODULE_PAYMENT_TARGETPAY_SORT_ORDER;
		$this->enabled = ((MODULE_PAYMENT_TARGETPAY_STATUS == 'True') ? true : false);

		$this->rtlo = MODULE_PAYMENT_TARGETPAY_TARGETPAY_RTLO;

		$this->transactionDescription = MODULE_PAYMENT_TARGETPAY_TRANSACTION_DESCRIPTION;


		$this->targetpaymodule = new TargetPayIdealOld($this->rtlo);
		if(MODULE_PAYMENT_TARGETPAY_REPAIR_ORDER === true) {
			if($_GET['targetpay_transaction_id']) {
				$_SESSION['targetpay_repair_transaction_id'] = tep_db_input($_GET['targetpay_transaction_id']);
				//$this->transactionID = $_SESSION['ideal_repair_transaction_id'];
			}
			$this->transactionID = $_SESSION['targetpay_repair_transaction_id'];
		}
	}
	/**
	 * @desc update module status
	 */
	function update_status()
	{
		global $order, $db;

		if (($this->enabled == true) && ((int)MODULE_PAYMENT_TARGETPAY_ZONE > 0) ) {
			$check_flag = false;
			$check = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_TARGETPAY_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");

			while (!$check->EOF)
			{
				if ($check->fields['zone_id'] < 1) {
					$check_flag = true;
					break;
				}
				elseif ($check->fields['zone_id'] == $order->billing['zone_id']) {
					$check_flag = true;
					break;
				}
				$check->MoveNext();
			}
			if ($check_flag == false) {
				$this->enabled = false;
			}
		}
	}

	function javascript_validation()
	{
		return false;
	}

	/**
	 * @desc get bank directory
	 */
	function getDirectory()
	{
		$issuerList = array();

		$objTargetpay = new TargetPayCore("AUTO", $this->rtlo);

		$bankList = $objTargetpay->getBankList();
		//var_dump($bankList);
		foreach($bankList AS $issuerID => $issuerName ) {
			$i = new stdClass();
			$i->issuerID = $issuerID;
			$i->issuerName = $issuerName;
			$i->issuerList = 'short';
			array_push($issuerList, $i);
		}
		return $issuerList;
	}

	/**
	 * Compare 2 issuer for ordering
	 * @param unknown $iu1
	 * @param unknown $iu2
	 */
	public static function compareIssuer($iu1, $iu2)
	{
		return strcasecmp($iu1["text"], $iu2["text"]);
	}
	/**
	 * @desc make bank selection field
	 */
	function selection()
	{
		global $order;

		$directory = $this->getDirectory();

		if(!is_null($directory)) {
			$issuers = array();
			$issuerType = "Short";

			$issuers[] = array('id' => "-1", 'text' => MODULE_PAYMENT_TARGETPAY_TEXT_ISSUER_SELECTION);

			foreach ($directory as $issuer)
			{
				if($issuer->issuerList != $issuerType) {
					//$issuers[] = array('id' => "-1", 'text' => MODULE_PAYMENT_TARGETPAY_TEXT_ISSUER_SELECTION_SEPERATOR);
					$issuerType = $issuer->issuerList;
				}
				$issuers[] = array('id' => $issuer->issuerID, 'text' => $issuer->issuerName);
			}
			// Add VISA/MASTER card
			$issuers[] = array('id' => 'CC', 'text' => 'Creditcard: Visa/Master card');
			$issuers[] = array('id' => 'WAL', 'text' => 'Paysafecard: Paysafe card');
			// Sort payment methods
			usort($issuers, "targetpay::compareIssuer");

			$selection = array( 'id' => $this->code,
					'module' => $this->title, // $this->title . " ".MODULE_PAYMENT_TARGETPAY_TEXT_INFO
					'fields' => array(  array(  'title' => tep_image('images/icons/targetpay.png', '', '', '', 'align=absmiddle'), // .MODULE_PAYMENT_TARGETPAY_TEXT_ISSUER_SELECTION
							'field' => tep_draw_pull_down_menu('bankID', $issuers, '', 'onChange="check_targetpay()"'))));
		}
		else
		{
			$selection = array( 'id' => $this->code,
					'module' => $this->title . MODULE_PAYMENT_TARGETPAY_TEXT_INFO,
					'fields' => array(  array(  'title' => MODULE_PAYMENT_TARGETPAY_TEXT_ISSUER_SELECTION,
							'field' => "Could not get banks. ".$this->targetpaymodule->getErrorMessage())));
		}
		return $selection;
	}

	function pre_confirmation_check()
	{
		global $cartID, $cart;

		if (empty($cart->cartID)) {
			$cartID = $cart->cartID = $cart->generate_cart_id();
		}

		if (!tep_session_is_registered('cartID')) {
			tep_session_register('cartID');
		}
	}

	/**
	 * @desc prepare the transaction and send user back on error or forward to bank
	 */
	function prepareTransaction()
	{
		global $order, $currencies, $customer_id, $db, $messageStack, $order_totals, $cart_TargetPay_ID;

		list($void,$customOrderId) = explode("-", $cart_TargetPay_ID);

		if(!isset($_POST['bankID']) || ($_POST['bankID'] < 0)) {
			$messageStack->add_session('checkout_payment', MODULE_PAYMENT_TARGETPAY_ERROR_TEXT_NO_ISSUER_SELECTED);
			tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
		}
		$ideal_issuerID = tep_db_input($_POST['bankID']); //bank
		$ideal_purchaseID = time();
		$ideal_currency = "EUR"; //future use
		$ideal_language = "nl"; //future use
		$ideal_amount = round($order->info['total'] * 100, 0);
		$ideal_entranceCode = tep_session_id();
		if((strtolower($this->transactionDescription) == 'automatic')&&(count($order->products) == 1)) {
			$product = $order->products[0];
			$ideal_description = $product['name'];
		}else{
			$ideal_description = 'Order:'.$customOrderId.' '.$this->transactionDescriptionText;
		}
		$ideal_description = trim(strip_tags($ideal_description));
		//This function has been DEPRECATED as of PHP 5.3.0. Relying on this feature is highly discouraged.
		//$ideal_description = ereg_replace("[^( ,[:alnum:])]", '*', $ideal_description);
		$ideal_description = preg_replace("/[^a-zA-Z0-9\s]/", '', $ideal_description);
		$ideal_description = substr($ideal_description, 0, 31); /* Max. 32 characters */
		if(empty($ideal_description)) {
			$ideal_description = 'nvt';
		}

		if($this->targetpaymodule->setIdealAmount($ideal_amount) === false) {
			$messageStack->add_session('checkout_payment', MODULE_PAYMENT_TARGETPAY_ERROR_TEXT_ERROR_OCCURRED_PROCESSING."<br/>".$this->targetpaymodule->getError());
			tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
		}
		else
		{
			$appId = '622ad10ec3f17bd437f01cf8eaaa779c';
			$objTargetpay = new TargetPayCore("AUTO", $this->rtlo, $appId);
			$objTargetpay->setBankId($ideal_issuerID);
			$objTargetpay->setAmount($ideal_amount);
			$objTargetpay->setDescription($ideal_description);
			$objTargetpay->setReturnUrl(tep_href_link('ext/modules/payment/targetpay/checkout.php', '', 'SSL'));
			$objTargetpay->setReportUrl(tep_href_link('ext/modules/payment/targetpay/callback.php', '', 'SSL'));


			$result = @$objTargetpay->startPayment();


			if($result === false) {
				$messageStack->add_session('checkout_payment', MODULE_PAYMENT_TARGETPAY_ERROR_TEXT_ERROR_OCCURRED_PROCESSING . "<br/>".$objTargetpay->getErrorMessage());
				tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
			}

			$this->transactionID = $objTargetpay->getTransactionId();

			if(!is_numeric($this->transactionID)) {
				$messageStack->add_session('checkout_payment', MODULE_PAYMENT_TARGETPAY_ERROR_TEXT_ERROR_OCCURRED_PROCESSING. "<br/>".$objTargetpay->getErrorMessage());
				tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
			}

			$this->bankUrl = $objTargetpay->getBankUrl();

			if(MODULE_PAYMENT_TARGETPAY_EMAIL_ORDER_INIT == 'True') {
				$email_text = 'Er is zojuist een Targetpay iDeal bestelling opgestart' . "\n\n";
				$email_text .= 'Details:' . "\n";
				$email_text .= 'customer_id: ' . $_SESSION['customer_id'] . "\n";
				$email_text .= 'customer_first_name: ' . $_SESSION['customer_first_name'] . "\n";
				$email_text .= 'TargetPay transaction_id: ' . $this->transactionID . "\n";
				$email_text .= 'bedrag: ' . $ideal_amount . ' (' . $ideal_currency . 'x100)' . "\n";
				$max_orders_id = tep_db_query("select max(orders_id) orders_id from " . TABLE_ORDERS);
				$new_order_id = $max_orders_id->fields['orders_id'] +1;
				$email_text .= 'order_id: ' . $new_order_id . ' (verwacht indien de bestelling wordt voltooid, kan ook hoger zijn)' . "\n";
				$email_text .= "\n\n";
				$email_text .= 'Targetpay transactions lookup: ' . HTTP_SERVER_TARGETPAY_ADMIN . FILENAME_TARGETPAY_TRANSACTIONS . '?action=lookup&transactionID=' . $this->transactionID . "\n";

				tep_mail('', STORE_OWNER_EMAIL_ADDRESS, '[iDeal bestelling opgestart] #' . $new_order_id . ' (?)', $email_text, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
			}

			tep_db_query(
					"INSERT INTO " . TABLE_TARGETPAY_TRANSACTIONS . "
	    		(
	    		`transaction_id`,
	    		`rtlo`,
	    		`purchase_id`,
	    		`issuer_id`,
	    		`transaction_status`,
	    		`datetimestamp`,
	    		`customer_id`,
	    		`amount`,
	    		`currency`,
	    		`session_id`,
	    		`ideal_session_data`,
	    		`order_id`
	    		) VALUES (
	    		'".$this->transactionID."',
	    		'".$this->rtlo."',
	    		'".$ideal_purchaseID."',
	    		'".$ideal_issuerID."',
	    		'open',
	    		NOW( ),
	    		'".$_SESSION['customer_id']."',
	    		'".$ideal_amount."',
	    		'".$ideal_currency."',
	    		'".tep_db_input(tep_session_id())."',
	    		'".base64_encode(serialize($_SESSION))."',
	    		'".$customOrderId."'
	    		);"
					);
			tep_redirect(html_entity_decode($this->bankUrl));
		}
	}

	/**
	 * @return false
	 */
	function confirmation()
	{
		global $cartID, $cart_TargetPay_ID, $customer_id, $languages_id, $order, $order_total_modules;
		if (tep_session_is_registered('cartID')) {
			$insert_order = false;

			if (tep_session_is_registered('cart_TargetPay_ID')) {
				$order_id = substr($cart_TargetPay_ID, strpos($cart_TargetPay_ID, '-')+1);

				$curr_check = tep_db_query("select currency from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");
				$curr = tep_db_fetch_array($curr_check);

				if (($curr['currency'] != $order->info['currency']) || ($cartID != substr($cart_TargetPay_ID, 0, strlen($cartID))) ) {
					$check_query = tep_db_query('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '" limit 1');

					if (tep_db_num_rows($check_query) < 1) {
						tep_db_query('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int)$order_id . '"');
						tep_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int)$order_id . '"');
						tep_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '"');
						tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$order_id . '"');
						tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int)$order_id . '"');
						tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int)$order_id . '"');
					}
					$insert_order = true;
				}
			} else {
				$insert_order = true;
			}

			if ($insert_order == true) {
				$order_totals = array();
				if (is_array($order_total_modules->modules)) {
					reset($order_total_modules->modules);
					while (list(, $value) = each($order_total_modules->modules)) {
						$class = substr($value, 0, strrpos($value, '.'));
						if ($GLOBALS[$class]->enabled) {
							for ($i=0, $n=sizeof($GLOBALS[$class]->output); $i<$n; $i++) {
								if (tep_not_null($GLOBALS[$class]->output[$i]['title']) && tep_not_null($GLOBALS[$class]->output[$i]['text'])) {
									$order_totals[] = array(
											'code' => $GLOBALS[$class]->code,
											'title' => $GLOBALS[$class]->output[$i]['title'],
											'text' => $GLOBALS[$class]->output[$i]['text'],
											'value' => $GLOBALS[$class]->output[$i]['value'],
											'sort_order' => $GLOBALS[$class]->sort_order
									);
								}
							}
						}
					}
				}

				$sql_data_array = array(
						'customers_id' => $customer_id,
						'customers_name' => $order->customer['firstname'] . ' ' . $order->customer['lastname'],
						'customers_company' => $order->customer['company'],
						'customers_street_address' => $order->customer['street_address'],
						'customers_suburb' => $order->customer['suburb'],
						'customers_city' => $order->customer['city'],
						'customers_postcode' => $order->customer['postcode'],
						'customers_state' => $order->customer['state'],
						'customers_country' => $order->customer['country']['title'],
						'customers_telephone' => $order->customer['telephone'],
						'customers_email_address' => $order->customer['email_address'],
						'customers_address_format_id' => $order->customer['format_id'],
						'delivery_name' => $order->delivery['firstname'] . ' ' . $order->delivery['lastname'],
						'delivery_company' => $order->delivery['company'],
						'delivery_street_address' => $order->delivery['street_address'],
						'delivery_suburb' => $order->delivery['suburb'],
						'delivery_city' => $order->delivery['city'],
						'delivery_postcode' => $order->delivery['postcode'],
						'delivery_state' => $order->delivery['state'],
						'delivery_country' => $order->delivery['country']['title'],
						'delivery_address_format_id' => $order->delivery['format_id'],
						'billing_name' => $order->billing['firstname'] . ' ' . $order->billing['lastname'],
						'billing_company' => $order->billing['company'],
						'billing_street_address' => $order->billing['street_address'],
						'billing_suburb' => $order->billing['suburb'],
						'billing_city' => $order->billing['city'],
						'billing_postcode' => $order->billing['postcode'],
						'billing_state' => $order->billing['state'],
						'billing_country' => $order->billing['country']['title'],
						'billing_address_format_id' => $order->billing['format_id'],
						'payment_method' => $order->info['payment_method'],
						'cc_type' => $order->info['cc_type'],
						'cc_owner' => $order->info['cc_owner'],
						'cc_number' => $order->info['cc_number'],
						'cc_expires' => $order->info['cc_expires'],
						'date_purchased' => 'now()',
						'orders_status' => $order->info['order_status'],
						'currency' => $order->info['currency'],
						'currency_value' => $order->info['currency_value']
				);

				tep_db_perform(TABLE_ORDERS, $sql_data_array);

				$insert_id = tep_db_insert_id();

				for ($i=0, $n=sizeof($order_totals); $i<$n; $i++) {
					$sql_data_array = array(
							'orders_id' => $insert_id,
							'title' => $order_totals[$i]['title'],
							'text' => $order_totals[$i]['text'],
							'value' => $order_totals[$i]['value'],
							'class' => $order_totals[$i]['code'],
							'sort_order' => $order_totals[$i]['sort_order']
					);
					tep_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);
				}

				for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
					$sql_data_array = array(
							'orders_id' => $insert_id,
							'products_id' => tep_get_prid($order->products[$i]['id']),
							'products_model' => $order->products[$i]['model'],
							'products_name' => $order->products[$i]['name'],
							'products_price' => $order->products[$i]['price'],
							'final_price' => $order->products[$i]['final_price'],
							'products_tax' => $order->products[$i]['tax'],
							'products_quantity' => $order->products[$i]['qty']
					);

					tep_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);

					$order_products_id = tep_db_insert_id();
					$attributes_exist = '0';
					if (isset($order->products[$i]['attributes'])) {
						$attributes_exist = '1';
						for ($j=0, $n2=sizeof($order->products[$i]['attributes']); $j<$n2; $j++) {
							if (DOWNLOAD_ENABLED == 'true') {
								$attributes_query = "select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename
														from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
														left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
														on pa.products_attributes_id=pad.products_attributes_id
														where pa.products_id = '" . $order->products[$i]['id'] . "'
														and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "'
														and pa.options_id = popt.products_options_id
														and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "'
														and pa.options_values_id = poval.products_options_values_id
														and popt.language_id = '" . $languages_id . "'
														and poval.language_id = '" . $languages_id . "'";
								$attributes = tep_db_query($attributes_query);
							} else {
								$attributes = tep_db_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . $order->products[$i]['id'] . "' and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . $languages_id . "' and poval.language_id = '" . $languages_id . "'");
							}
							$attributes_values = tep_db_fetch_array($attributes);

							$sql_data_array = array(
									'orders_id' => $insert_id,
									'orders_products_id' => $order_products_id,
									'products_options' => $attributes_values['products_options_name'],
									'products_options_values' => $attributes_values['products_options_values_name'],
									'options_values_price' => $attributes_values['options_values_price'],
									'price_prefix' => $attributes_values['price_prefix']
							);

							tep_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array);

							if ((DOWNLOAD_ENABLED == 'true') && isset($attributes_values['products_attributes_filename']) && tep_not_null($attributes_values['products_attributes_filename'])) {
								$sql_data_array = array(
										'orders_id' => $insert_id,
										'orders_products_id' => $order_products_id,
										'orders_products_filename' => $attributes_values['products_attributes_filename'],
										'download_maxdays' => $attributes_values['products_attributes_maxdays'],
										'download_count' => $attributes_values['products_attributes_maxcount']
								);

								tep_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);
							}
						}
					}
				}

				$cart_TargetPay_ID = $cartID . '-' . $insert_id;
				tep_session_register('cart_TargetPay_ID');
			}
		}
		return false;
	}

	/**
	 * @desc make hidden value for payment system
	 */
	function process_button()
	{
		$process_button = tep_draw_hidden_field('bankID', $_POST['bankID']) . MODULE_PAYMENT_TARGETPAY_EXPRESS_TEXT;

		if(defined('BUTTON_CHECKOUT_TARGETPAY_ALT')) {
			$process_button .= tep_image_submit('targetpay.gif', BUTTON_CHECKOUT_TARGETPAY_ALT);
		}
		return  $process_button;
	}

	/**
	 * @desc before process check status or prepare transaction
	 */
	function before_process()
	{
		if(MODULE_PAYMENT_TARGETPAY_REPAIR_ORDER === true) {
			global $order;
			// when repairing iDeal the transaction status is succes, set order status accordingly
			$order->info['order_status'] = MODULE_PAYMENT_TARGETPAY_ORDER_STATUS_ID;
			return false;
		}
		if(isset($_GET['action']) && $_GET['action'] == "process") {
			$this->checkStatus();
		}
		else {
			$this->prepareTransaction();
		}
	}

	/**
	 * @desc check payment status
	 */
	function checkStatus()
	{
		global $order, $db, $messageStack;

		if(MODULE_PAYMENT_TARGETPAY_REPAIR_ORDER === true) { return false;
		}
		$this->transactionID = tep_db_input($_GET['trxid']);
		$method = tep_db_input($_GET['method']);

		if($this->transactionID == "") {
			$messageStack->add_session('checkout_payment', MODULE_PAYMENT_TARGETPAY_ERROR_TEXT_ERROR_OCCURRED_PROCESSING);
			tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
		}

		$iTest = (MODULE_PAYMENT_TARGETPAY_TESTACCOUNT == "True")?1:0;

		$objTargetpay = new TargetPayCore($method, $this->rtlo);
		$status = $objTargetpay->checkPayment($this->transactionID);

		if($objTargetpay->getPaidStatus()) {
			$realstatus = "success";
		} else {
			$realstatus = "open";
		}

		$customerInfo = $objTargetpay->getConsumerInfo();
		$consumerAccount = (((isset($customerInfo->consumerInfo["bankaccount"]) && !empty($customerInfo->consumerInfo["bankaccount"])) ? $customerInfo->consumerInfo["bankaccount"] : ""));
		$consumerName = (((isset($customerInfo->consumerInfo["name"]) && !empty($customerInfo->consumerInfo["name"])) ? $customerInfo->consumerInfo["name"] : ""));
		$consumerCity = (((isset($customerInfo->consumerInfo["city"]) && !empty($customerInfo->consumerInfo["city"])) ? $customerInfo->consumerInfo["city"] : ""));

		tep_db_query("UPDATE " . TABLE_TARGETPAY_TRANSACTIONS . " SET `transaction_status` = '".$realstatus."',`datetimestamp` = NOW( ) ,`consumer_name` = '".$consumerName."',`consumer_account_number` = '".$consumerAccount."',`consumer_city` = '".$consumerCity."' WHERE `transaction_id` = '".$this->transactionID."' LIMIT 1");

		switch ($realstatus)
		{
			case "success":
				$order->info['order_status'] = MODULE_PAYMENT_TARGETPAY_ORDER_STATUS_ID;
				break;
			case "open":
				$messageStack->add_session('checkout_payment', MODULE_PAYMENT_TARGETPAY_ERROR_TEXT_TRANSACTION_OPEN);
				tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
				break;
			default:
				$messageStack->add_session('checkout_payment', MODULE_PAYMENT_TARGETPAY_ERROR_TEXT_TRANSACTION_OPEN);
				tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
				break;
		}
	}

	/**
	 * @desc after order create set value in database
	 * @param $zf_order_id
	 */
	function after_order_create($zf_order_id)
	{
		tep_db_query("UPDATE " . TABLE_TARGETPAY_TRANSACTIONS . " SET `order_id` = '".$zf_order_id."', `ideal_session_data` = '' WHERE `transaction_id` = '".$this->transactionID."' LIMIT 1 ;");
		if(isset($_SESSION['targetpay_repair_transaction_id'])) {
			unset($_SESSION['targetpay_repair_transaction_id']);
		}
	}

	/**
	 * @desc after process function
	 * @return false
	 */
	function after_process()
	{
		echo 'after process komt hier';
		return false;
	}

	/**
	 * @desc checks installation of module
	 */
	function check()
	{
		if (!isset($this->_check)) {
			$check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_TARGETPAY_STATUS'");
			$this->_check = tep_db_num_rows($check_query);
		}
		return $this->_check;
	}

	/**
	 * @desc install values in database
	 */
	function install()
	{
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Targetpay payment module', 'MODULE_PAYMENT_TARGETPAY_STATUS', 'True', 'Do you want to accept Targetpay payments?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sortorder', 'MODULE_PAYMENT_TARGETPAY_SORT_ORDER', '0', 'Sort order of payment methods in list. Lowest is displayed first.', '6', '2', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment zone', 'MODULE_PAYMENT_TARGETPAY_ZONE', '0', 'If a zone is selected, enable this payment method for that zone only.', '6', '3', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");

		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Order status - confirmed', 'MODULE_PAYMENT_TARGETPAY_ORDER_STATUS_ID', '" . DEFAULT_ORDERS_STATUS_ID .  "', 'The status of orders that where successfully confirmed. (Recommended: <strong>processing</strong>)', '6', '4', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Order status - open', 'MODULE_PAYMENT_TARGETPAY_ORDER_STATUS_ID_OPEN', '" . DEFAULT_ORDERS_STATUS_ID .  "', 'The status of orders of which payment could not be confirmed. (Recommended: <strong>pending</strong>)', '6', '5', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");

		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction description', 'MODULE_PAYMENT_TARGETPAY_TRANSACTION_DESCRIPTION', 'Automatic', 'Select automatic for product name as description, or manual to use the text you supply below.', '6', '8', 'tep_cfg_select_option(array(\'Automatic\',\'Manual\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Transaction description text', 'MODULE_PAYMENT_TARGETPAY_MERCHANT_TRANSACTION_DESCRIPTION_TEXT', '" . TITLE . "', 'Description of transactions from this webshop. <strong>Should not be empty!</strong>.', '6', '8', now())");

		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Targetpay ID', 'MODULE_PAYMENT_TARGETPAY_TARGETPAY_RTLO', '93929', 'The Targetpay RTLO', '6', '4', now())");// Default TargetPay

		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Testaccount?', 'MODULE_PAYMENT_TARGETPAY_TESTACCOUNT', 'False', 'Enable testaccount (only for validation)?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");


		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('IP address', 'MODULE_PAYMENT_TARGETPAY_REPAIR_IP', '" . $_SERVER['REMOTE_ADDR'] . "', 'The IP address of the user (administrator) that is allowed to complete open ideal orders (if empty everyone will be allowed, which is not recommended!).', '6', '8', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable pre order emails', 'MODULE_PAYMENT_TARGETPAY_EMAIL_ORDER_INIT', 'False', 'Do you want emails to be sent to the store owner whenever an Targetpay order is being initiated? The default is <strong>False</strong>.', '6', '17', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");

		tep_db_query("CREATE TABLE " . TABLE_TARGETPAY_DIRECTORY . " (`issuer_id` VARCHAR( 4 ) NOT NULL ,`issuer_name` VARCHAR( 30 ) NOT NULL ,`issuer_issuerlist` VARCHAR( 5 ) NOT NULL ,`timestamp` DATETIME NOT NULL ,PRIMARY KEY ( `issuer_id` ) );");

		if(TARGETPAY_OLD_MYSQL_VERSION_COMP == 'true') {
			tep_db_query("CREATE TABLE IF NOT EXISTS " . TABLE_TARGETPAY_TRANSACTIONS . " (`transaction_id` VARCHAR( 30 ) NOT NULL ,`rtlo` VARCHAR( 7 ) NOT NULL ,`purchase_id` VARCHAR( 30 ) NOT NULL , `issuer_id` VARCHAR( 25 ) NOT NULL , `session_id` VARCHAR( 128 ) NOT NULL ,`ideal_session_data`  MEDIUMBLOB NOT NULL ,`order_id` INT( 11 ),`transaction_status` VARCHAR( 10 ) ,`datetimestamp` DATETIME, `consumer_name` VARCHAR( 50 ) ,`consumer_account_number` VARCHAR( 20 ) ,`consumer_city` VARCHAR( 50 ), `customer_id` INT( 11 ), `amount` DECIMAL( 15, 4 ), `currency` CHAR( 3 ), `batch_id` VARCHAR( 30 ), PRIMARY KEY ( `transaction_id` ));");
		}else{
			tep_db_query("CREATE TABLE IF NOT EXISTS " . TABLE_TARGETPAY_TRANSACTIONS . " (`transaction_id` VARCHAR( 30 ) NOT NULL ,`rtlo` VARCHAR( 7 ) NOT NULL ,`purchase_id` VARCHAR( 30 ) NOT NULL , `issuer_id` VARCHAR( 25 ) NOT NULL , `session_id` VARCHAR( 128 ) NOT NULL ,`ideal_session_data`  MEDIUMBLOB NOT NULL ,`order_id` INT( 11 ),`transaction_status` VARCHAR( 10 ) ,`datetimestamp` DATETIME, `last_modified` TIMESTAMP NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP, `consumer_name` VARCHAR( 50 ) ,`consumer_account_number` VARCHAR( 20 ) ,`consumer_city` VARCHAR( 50 ), `customer_id` INT( 11 ), `amount` DECIMAL( 15, 4 ), `currency` CHAR( 3 ), `batch_id` VARCHAR( 30 ), PRIMARY KEY ( `transaction_id` ));");
		}

		$sql = "select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS;
		$status_query = tep_db_query($sql);

		$status = tep_db_fetch_array($status_query);
		$status_id = $status['status_id']+1;
		$cancel = $status['status_id']+2;
		$error = $status['status_id']+3;

		$languages = tep_get_languages();
		for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
			tep_db_query("insert into " . TABLE_ORDERS_STATUS . "(orders_status_id, language_id, orders_status_name) values ('" . $status_id . "', '" . $languages[$i]['id'] . "', 'Payment Paid [targetpay]')");
			tep_db_query("insert into " . TABLE_ORDERS_STATUS . "(orders_status_id, language_id, orders_status_name) values ('" . $cancel . "', '" . $languages[$i]['id'] . "', 'Payment canceled [targetpay]')");
			tep_db_query("insert into " . TABLE_ORDERS_STATUS . "(orders_status_id, language_id, orders_status_name) values ('" . $error . "', '" . $languages[$i]['id'] . "', 'Payment error [targetpay]')");
		}

		tep_db_query(
				"insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
		values ('Set Paid Order Status', 'MODULE_PAYMENT_TARGETPAY_PREPARE_ORDER_STATUS_ID', '" . $status_id . "', 'Set the status of prepared orders to success', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())"
				);
		tep_db_query(
				"insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
		values ('Set Paid Order Status', 'MODULE_PAYMENT_TARGETPAY_PAYMENT_CANCELLED', '" . $cancel . "', 'The payment is cancelled by the enduser', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())"
				);
		tep_db_query(
				"insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
		values ('Set Paid Order Status', 'MODULE_PAYMENT_TARGETPAY_PAYMENT_ERROR', '" . $error . "', 'The payment is cancelled by the enduser', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())"
				);
	}

	function remove()
	{
		tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
		//tep_db_query("DROP TABLE IF EXISTS " . TABLE_TARGETPAY_TRANSACTIONS);
		tep_db_query("DROP TABLE IF EXISTS " . TABLE_TARGETPAY_DIRECTORY);
	}

	function keys()
	{
		return array('MODULE_PAYMENT_TARGETPAY_PAYMENT_ERROR','MODULE_PAYMENT_TARGETPAY_PREPARE_ORDER_STATUS_ID','MODULE_PAYMENT_TARGETPAY_PAYMENT_CANCELLED','MODULE_PAYMENT_TARGETPAY_STATUS','MODULE_PAYMENT_TARGETPAY_SORT_ORDER','MODULE_PAYMENT_TARGETPAY_ZONE','MODULE_PAYMENT_TARGETPAY_ORDER_STATUS_ID','MODULE_PAYMENT_TARGETPAY_TESTACCOUNT','MODULE_PAYMENT_TARGETPAY_ORDER_STATUS_ID_OPEN','MODULE_PAYMENT_TARGETPAY_TARGETPAY_RTLO' , 'MODULE_PAYMENT_TARGETPAY_TRANSACTION_DESCRIPTION', 'MODULE_PAYMENT_TARGETPAY_MERCHANT_TRANSACTION_DESCRIPTION_TEXT',  'MODULE_PAYMENT_TARGETPAY_REPORT_URL' , 'MODULE_PAYMENT_TARGETPAY_RETURN_URL', 'MODULE_PAYMENT_TARGETPAY_EMAIL_ORDER_INIT','MODULE_PAYMENT_TARGETPAY_REPAIR_IP');
	}
}

?>
