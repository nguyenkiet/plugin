<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
* See https://www.x-cart.com/license-agreement.html for license details.
*/

namespace XLite\Module\TargetPay\Payment\Processor;

use XLite\Module\TargetPay\Payment\Base\TargetPayPlugin;

class iDEAL extends TargetPayPlugin
{
	/**
	 * The contructor
	 */
	public function __construct()
	{
		$this->payMethod = "IDE";
		$this->appId  	 = "382a92214fcbe76a32e22a30e1e9dd9f";
		$this->currency  = "EUR";
		$this->language  = 'nl';
		$this->allow_nobank = true;
	}
	
	/***
	 * The setting widget
	 * {@inheritDoc}
	 * @see \XLite\Model\Payment\Base\Processor::getSettingsWidget()
	 */
	public function getSettingsWidget()
	{
		return 'modules/TargetPay/iDEAL/config.twig';
	}

	/**
	 * return transaction process
	 * {@inheritDoc}
	 * @see \XLite\Model\Payment\Base\Online::processReturn()
	 */
	public function processReturn(\XLite\Model\Payment\Transaction $transaction)
	{
		parent::processReturn($transaction);
		$this->handlePaymentResult($transaction, false);
	}

	/**
	 * Callback message
	 * {@inheritDoc}
	 * @see \XLite\Model\Payment\Base\Online::processCallback()
	 */
	public function processCallback(\XLite\Model\Payment\Transaction $transaction)
	{
		parent::processCallback($transaction);
		$this->handlePaymentResult($transaction, true);
	}
}
