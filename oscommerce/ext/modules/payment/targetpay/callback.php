<?php
/**
 * TargetPay Payment Module for osCommerce
 *
 * @copyright Copyright 2013-2014 Yellow Melon
 * @copyright Portions Copyright 2013 Paul Mathot
 * @copyright Portions Copyright 2003 osCommerce
 * @license   see LICENSE.TXT
 */

chdir('../../../../');
require 'includes/application_top.php';
require_once 'includes/classes/order.php';
require_once 'includes/classes/payment.php';

if (!defined('MODULE_PAYMENT_TARGETPAY_STATUS') || (MODULE_PAYMENT_TARGETPAY_STATUS  != 'True')) {
    exit;
}
$payment = new Payment('targetpay');

require_once 'includes/modules/payment/targetpay/targetpay.class.php';

require_once 'includes/extra_datafiles/targetpay.php';

//retrieve db-info from targetpay_transactions
$trxid = $_REQUEST["trxid"];
$sql = "select * from " . TABLE_TARGETPAY_TRANSACTIONS . " where `transaction_id` = '" . $trxid."'";
$targetpayDBValueQuery = tep_db_query($sql);
        
if (tep_db_num_rows($targetpayDBValueQuery) > 0) {
    $targetpayDBValues = tep_db_fetch_array($targetpayDBValueQuery);

    $order_id = $targetpayDBValues["order_id"];
            
    $sql = "select orders_status from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'";
    $check_query = tep_db_query($sql);
    if (tep_db_num_rows($check_query)) {
        $check = tep_db_fetch_array($check_query);

        if ($check['orders_status'] == 1) {
            $sql_data_array = array('orders_id' => $order_id,
                                          'orders_status_id' => '2',
                                          'date_added' => 'now()',
                                          'customer_notified' => '0',
                                          'comments' => 'callback.php');
            tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
        }
                
        $TargetPayCore = new TargetPayCore(strtoupper(substr($targetpayDBValues['issuer_id'], 0, 3)), $targetpayDBValues['rtlo']);
        $TargetPayCore->checkPayment($targetpayDBValues['transaction_id']);
                
        $order = new Order($order_id);
        //Check if the end-user is paid

        $paidStatus = $TargetPayCore->getPaidStatus();
        if (MODULE_PAYMENT_TARGETPAY_TESTACCOUNT == "True") {   // Always OK
            $paidStatus = true;                
        }

        if($paidStatus) {
                    
            tep_db_query("update " . TABLE_ORDERS . " set orders_status = '".MODULE_PAYMENT_TARGETPAY_PREPARE_ORDER_STATUS_ID."', last_modified = now() where orders_id = '" . (int)$order_id . "'");
                    
            $sql_data_array = array('orders_id' => $order_id,
                                          'orders_status_id' => MODULE_PAYMENT_TARGETPAY_PREPARE_ORDER_STATUS_ID,
                                          'date_added' => 'now()',
                                          'customer_notified' => '0',
                                          'comments' => 'Targetpay result ' . $comment_status);
            tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
                    
            for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
                // Stock Update - Joao Correia
                if (STOCK_LIMITED == 'true') {
                    if (DOWNLOAD_ENABLED == 'true') {
                        $stock_query_raw = "SELECT products_quantity, pad.products_attributes_filename
														FROM " . TABLE_PRODUCTS . " p
														LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " pa
														ON p.products_id=pa.products_id
														LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
														ON pa.products_attributes_id=pad.products_attributes_id
														WHERE p.products_id = '" . tep_get_prid($order->products[$i]['id']) . "'";
                        // Will work with only one option for downloadable products
                        // otherwise, we have to build the query dynamically with a loop
                        $products_attributes = $order->products[$i]['attributes'];
                        if (is_array($products_attributes)) {
                            $stock_query_raw .= " AND pa.options_id = '" . $products_attributes[0]['option_id'] . "' AND pa.options_values_id = '" . $products_attributes[0]['value_id'] . "'";
                        }
                        $stock_query = tep_db_query($stock_query_raw);
                    } else {
                        $stock_query = tep_db_query("select products_quantity from " . TABLE_PRODUCTS . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                    }
                    if (tep_db_num_rows($stock_query) > 0) {
                        $stock_values = tep_db_fetch_array($stock_query);
                        // do not decrement quantities if products_attributes_filename exists
                        if ((DOWNLOAD_ENABLED != 'true') || (!$stock_values['products_attributes_filename'])) {
                            $stock_left = $stock_values['products_quantity'] - $order->products[$i]['qty'];
                        } else {
                            $stock_left = $stock_values['products_quantity'];
                        }
                        tep_db_query("update " . TABLE_PRODUCTS . " set products_quantity = '" . $stock_left . "' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                        if (($stock_left < 1) && (STOCK_ALLOW_CHECKOUT == 'false') ) {
                            tep_db_query("update " . TABLE_PRODUCTS . " set products_status = '0' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                        }
                    }
                }

                // Update products_ordered (for bestsellers list)
                tep_db_query("update " . TABLE_PRODUCTS . " set products_ordered = products_ordered + " . sprintf('%d', $order->products[$i]['qty']) . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");

                //------insert customer choosen option to order--------
                $attributes_exist = '0';
                $products_ordered_attributes = '';
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

                        $products_ordered_attributes .= "\n\t" . $attributes_values['products_options_name'] . ' ' . $attributes_values['products_options_values_name'];
                    }
                }
                //------insert customer choosen option eof ----
                $total_weight += ($order->products[$i]['qty'] * $order->products[$i]['weight']);
                $total_tax += tep_calculate_tax($total_products_price, $products_tax) * $order->products[$i]['qty'];
                $total_cost += $total_products_price;

                $products_ordered .= $order->products[$i]['qty'] . ' x ' . $order->products[$i]['name'] . ' (' . $order->products[$i]['model'] . ') = ' . $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty']) . $products_ordered_attributes . "\n";

                //-------lets start with the email confirmation

                if (!defined('EMAIL_TEXT_SUBJECT')) {
                    define('EMAIL_TEXT_SUBJECT', 'Bevestiging van uw bestelling');
                    define('EMAIL_TEXT_ORDER_NUMBER', 'Order Nummer:');
                    define('EMAIL_TEXT_INVOICE_URL', 'Factuurspecificatie:');
                    define('EMAIL_TEXT_DATE_ORDERED', 'Besteldatum:');
                    define('EMAIL_TEXT_PRODUCTS', 'Producten');
                    define('EMAIL_TEXT_SUBTOTAL', 'Subtotaal:');
                    define('EMAIL_TEXT_TAX', 'Belasting:        ');
                    define('EMAIL_TEXT_SHIPPING', 'Verzendkosten: ');
                    define('EMAIL_TEXT_TOTAL', 'Totaal:    ');
                    define('EMAIL_TEXT_DELIVERY_ADDRESS', 'Afleveradres');
                    define('EMAIL_TEXT_BILLING_ADDRESS', 'Factuuradres');
                    define('EMAIL_TEXT_PAYMENT_METHOD', 'Betaalwijze');
                    define('EMAIL_SEPARATOR', '------------------------------------------------------');
                    define('TEXT_EMAIL_VIA', 'via');
                }

                $email_order = STORE_NAME . "\n" .
                EMAIL_SEPARATOR . "\n" .
                EMAIL_TEXT_ORDER_NUMBER . ' ' . $order_id . "\n" .
                EMAIL_TEXT_INVOICE_URL . ' ' . tep_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $order_id, 'SSL', false) . "\n" .
                EMAIL_TEXT_DATE_ORDERED . ' ' . strftime(DATE_FORMAT_LONG) . "\n\n";
                if ($order->info['comments']) {
                    $email_order .= tep_db_output($order->info['comments']) . "\n\n";
                }

                          $email_order .= EMAIL_TEXT_PRODUCTS . "\n" .
                EMAIL_SEPARATOR . "\n" .
                $products_ordered .
                EMAIL_SEPARATOR . "\n";

                for ($i=0, $n=sizeof($order_totals); $i<$n; $i++) {
                    $email_order .= strip_tags($order_totals[$i]['title']) . ' ' . strip_tags($order_totals[$i]['text']) . "\n";
                }

                if ($order->content_type != 'virtual') {
                    $email_order .= "\n" . EMAIL_TEXT_DELIVERY_ADDRESS . "\n" .
                    EMAIL_SEPARATOR . "\n" .
                    $order->delivery['name']."\n".
                    $order->delivery['company']."\n".
                    $order->delivery['street_address']."\n".
                    $order->delivery['postcode']." ".$delivery['city']."\n".
                    $order->delivery['state']."\n".
                    $order->delivery['country']['title']."\n\n";
                }

                $email_order .= "\n" . EMAIL_TEXT_BILLING_ADDRESS . "\n" .
                EMAIL_SEPARATOR . "\n" .
                $order->billing['name']."\n".
                $order->billing['company']."\n".
                $order->billing['street_address']."\n".
                $order->billing['postcode']." ".$billing['city']."\n".
                $order->billing['state']."\n".
                $order->billing['country']['title']."\n\n";

                $email_order .= EMAIL_TEXT_PAYMENT_METHOD . "\n" .
                                        EMAIL_SEPARATOR . "\n";
                $email_order .= $order->info['payment_method'] . "\n\n";
                if ($payment->email_footer) {
                    $email_order .= $payment->email_footer . "\n\n";
                }

                tep_mail($order->customer['firstname'] . ' ' . $order->customer['lastname'], $order->customer['email_address'], EMAIL_TEXT_SUBJECT, $email_order, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

                // send emails to other people
                if (SEND_EXTRA_ORDER_EMAILS_TO != '') {
                    tep_mail('', SEND_EXTRA_ORDER_EMAILS_TO, EMAIL_TEXT_SUBJECT, $email_order, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
                }

            }
        } else {
            //Pending
            //~ TP0010 Transactie is nog niet afgerond, probeer het later opnieuw
            //Cancelled
            //~ TP0011 Transactie is geannuleerd
            //Error
            //~ TP0012 Transactie is verlopen (max. 10 minuten)
            //~ TP0013 De transactie kon niet verwerkt worden
            //~ TP0020 Geen layoutcode opgegeven
            //~ TP0021 Geen transactieID opgegeven
            //~ TP0022 Geen transacie met dit ID gevonden
            //~ TP0023 Layoutcode matched niet met deze transactie
            //success
            //~ TP0014 Reeds ingewisseld
            $errorCode = substr($TargetPayCore->getErrorMessage(), 0, 6);
            switch($errorCode) { 
            case 'TP0010':
                $status = 1;
                break;
            case 'TP0011':
                $status = MODULE_PAYMENT_TARGETPAY_PAYMENT_CANCELLED;
                break;
            case 'TP0012':
            case 'TP0013':
            case 'TP0020':
            case 'TP0021':
            case 'TP0022':
            case 'TP0023':
                $status = MODULE_PAYMENT_TARGETPAY_PAYMENT_ERROR;
                break;
            case 'TP0014':
                $status = MODULE_PAYMENT_TARGETPAY_PREPARE_ORDER_STATUS_ID;
                break;
            }
                    
            $sql_data_array = array('orders_id' => $order_id,
                                          'orders_status_id' => $status,
                                          'date_added' => 'now()',
                                          'customer_notified' => '0',
                                          'comments' => 'Targetpay result ' . $TargetPayCore->getErrorMessage() .' used transaction id : '.$targetpayDBValues['transaction_id'] . ' | '. $TargetPayCore->getTransactionId() .'|'.$TargetPayCore->getPayMethod().'|');
            tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
        }                
    }
    
} else {
    die('transaction not found');
}
      
echo '45000';

require 'includes/application_bottom.php';
?>
