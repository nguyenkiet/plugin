<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
* See https://www.x-cart.com/license-agreement.html for license details.
*/

namespace XLite\Module\TargetPay\Creditcard\Model;

class Creditcard extends \XLite\Model\Payment\Base\WebBased
{
	public function getSettingsWidget()
	{
		return 'modules/TargetPay/Creditcard/config.twig';
	}

	public function isTestMode(\XLite\Model\Payment\Method $method)
	{
		return $method->getSetting('mode') != 'live';
	}

	public function isConfigured(\XLite\Model\Payment\Method $method)
	{
		return parent::isConfigured($method)
		&& $method->getSetting('rtlo');
	}

	protected function getFormURL()
	{
		return \XLite::getInstance()->getShopURL() . 'payment.php';
	}

	protected function getFormFields()
	{
		return array(
				'transactionID' => $this->getTransactionId(),
				'returnURL' => $this->getReturnURL(null, true),
				'invoice_description' => $this->getInvoiceDescription(),
		);
	}

	public function processReturn(\XLite\Model\Payment\Transaction $transaction)
	{
		parent::processReturn($transaction);

		$request = \XLite\Core\Request::getInstance();

		$status = '';
		$notes = array();
		if ($request->status == 'Paid') {
			$status = $transaction::STATUS_SUCCESS;
			$this->setDetail('Status', $request->status, 'Result');
			$this->setDetail('TxnNum', $request->transactionID, 'Transaction number');
		} else {
			$status = $transaction::STATUS_FAILED;
			$notes[] = 'Payment Failed';
		}

		$this->transaction->setStatus($status);
		$this->transaction->setNote(implode('. ', $notes));
	}

	/**
	 * Get payment method admin zone icon URL
	 *
	 * @param \XLite\Model\Payment\Method $method Payment method
	 *
	 * @return string
	 */
	public function getAdminIconURL(\XLite\Model\Payment\Method $method)
	{
		return true;
	}
	
}
