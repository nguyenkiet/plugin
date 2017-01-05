<?php
/**
 * TargetPay Payment Module for osCommerce
 *
 * @copyright Copyright 2013-2014 Yellow Melon
 * @copyright Portions Copyright 2013 Paul Mathot
 * @copyright Portions Copyright 2003 osCommerce
 * @license see LICENSE.TXT
 */
	
// or use $autoLoadConfig[71][] for example? = right after init_sessions.php
  $autoLoadConfig[1000][] = array('autoType'=>'init_script',
                                 'loadFile'=> 'init_targetpay_repair.php');
?>