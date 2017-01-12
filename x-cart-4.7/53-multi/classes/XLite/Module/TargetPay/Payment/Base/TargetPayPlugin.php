<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * @file     	Provides support for TargetPay iDEAL, Mister Cash, Sofort Banking, Credit and Paysafe
* @author     	Yellow Melon B.V.
* @url         http://www.idealplugins.nl
*/

namespace XLite\Module\TargetPay\Payment\Base;

use XLite\Module\TargetPay\Payment\Base\TargetPayCore;
use XLite\Module\TargetPay\Payment\Model\TargetPaySale;

class TargetPayPlugin extends \XLite\Model\Payment\Base\WebBased
{

	protected $targetPayCore = null;

	protected $payMethod 	= null;
	protected $appId		= null;
	protected $language		= null;
	protected $bankId		= null;
	protected $allow_nobank = false;

	/**
	 * Check if test mode is enabled
	 * {@inheritDoc}
	 * @see \XLite\Model\Payment\Base\Processor::isTestMode()
	 */
	public function isTestMode(\XLite\Model\Payment\Method $method)
	{
		return $method->getSetting('mode') != 'live';
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
	/**
	 * Check payment is configured or not
	 * {@inheritDoc}
	 * @see \XLite\Model\Payment\Base\Processor::isConfigured()
	 */
	public function isConfigured(\XLite\Model\Payment\Method $method)
	{
		return parent::isConfigured($method) && $method->getSetting('rtlo');
	}
	/***
	 * Init the targetPayment
	 * @return \TargetPayCore
	 */
	protected function initTargetPayment()
	{
		if($this->targetPayCore != null){
			return $this->targetPayCore;
		}
		$this->targetPayCore = new TargetPayCore($this->payMethod, $this->getRTLO(), $this->appId, $this->language, $this->isTestMode($this->transaction->getPaymentMethod()));
		$this->targetPayCore->setBankId($this->bankId);
		$this->targetPayCore->setAmount($this->transaction->getCurrency()->roundValue($this->transaction->getValue()));
		$this->targetPayCore->setCancelUrl($this->getReturnURL(null, true, true));
		$this->targetPayCore->setReturnUrl($this->getReturnURL(null, true));
		$this->targetPayCore->setReportUrl($this->getReturnURL(null, true) . "&type=report");
		$this->targetPayCore->setDescription($this->getTransactionDescription());
		return $this->targetPayCore;
	}
	/**
	 * Check if the setting is OK
	 * {@inheritDoc}
	 * @see \XLite\Model\Payment\Base\Processor::isApplicable()
	 */
	public function isApplicable(\XLite\Model\Order $order, \XLite\Model\Payment\Method $method)
	{
		// Check method
		if (empty($this->payMethod) || empty($this->appId)) {
			return false;
		}
		return true;
	}
	/**
	 * Get RTLO setting
	 */
	protected function getRTLO()
	{
		if(!empty($this->rtlo)) return $this->rtlo;
			
		$method = $this->transaction->getPaymentMethod();
		$this->rtlo = $method->getSetting('rtlo');
		return $this->rtlo;
	}
	/**
	 * get the description of payment
	 */
	protected function getTransactionDescription()
	{
		return "Order #" . $this->getOrder()->order_id;
	}
	/**
	 * Get the client host name
	 * @return unknown
	 */
	protected function getClientHost()
	{
		return $_SERVER["HTTP_HOST"];
	}

	/**
	 * The payment URL
	 * {@inheritDoc}
	 * @see \XLite\Model\Payment\Base\WebBased::getFormURL()
	 */
	protected function getFormURL()
	{
		$this->initTargetPayment();
		// Check the payment URL
		if(!empty($this->targetPayCore->getBankUrl())) {
			return $this->validateBankUrl($this->targetPayCore->getBankUrl());
		}
		// init transaction from Targetpay before redirect to bank
		if($this->targetPayCore->startPayment($this->allow_nobank)){
			// Insert order to targetpay sale report
			$sale = new TargetPaySale();
			$sale->order_id = $this->getOrder()->order_id;
			$sale->method = $this->targetPayCore->getPayMethod();
			$sale->amount = $this->targetPayCore->getAmount();
			$sale->targetpay_txid = $this->targetPayCore->getTransactionId();
			//$sale->paid = new \DateTime("now");
			\XLite\Core\Database::getRepo('\XLite\Module\TargetPay\Payment\Model\TargetPaySale')->insert($sale);
			// Return the URL
			return $this->validateBankUrl($this->targetPayCore->getBankUrl());
		}
		\XLite\Core\TopMessage::addError($this->targetPayCore->getErrorMessage());
		return false;
	}
	/***
	 * validate and remove dirty tag
	 * @param unknown $location
	 * @return unknown
	 */
	protected function validateBankUrl($location)
	{
		$location = preg_replace('/[\x00-\x1f].*$/sm', '', $location);
		$location = str_replace(array('"', "'", '<', '>'), array('&quot;','&#039;', '&lt;', '&gt;'), $this->convert_amp($location));
		return $location;
	}
	/**
	 * validate and remove dirty tag
	 * @param unknown $str
	 * @return unknown
	 */
	protected function convert_amp($str)
	{
		// Do not convert html entities like &thetasym; &Omicron; &euro; &#8364; &#8218;
		return preg_replace('/&(?![a-zA-Z0-9#]{1,8};)/Ss', '&amp;', $str);
	}
	/**
	 * Don't pass parame to form
	 * {@inheritDoc}
	 * @see \XLite\Model\Payment\Base\WebBased::getFormFields()
	 */
	protected function getFormFields()
	{
		return ['paymethod'		=> $this->payMethod];
	}
	/**
	 * Process return data
	 * @param \XLite\Model\Payment\Transaction $transaction
	 */
	protected function handlePaymentResult(\XLite\Model\Payment\Transaction $transaction, $is_callback)
	{
		$request = \XLite\Core\Request::getInstance();
		// Check if user cancel the transaction
		if ($request->cancel) {
			$this->setDetail(
					'status',
					'Customer has canceled checkout before completing their payments',
					'Status'
					);
			$this->transaction->setNote('Customer has canceled checkout before completing their payments');
		} else {
			if($request->isGet() && !empty($request->trxid)){
				// Check the local transaction
				$sale = \XLite\Core\Database::getRepo('\XLite\Module\TargetPay\Payment\Model\TargetPaySale')->find($request->trxid);
				if($sale == null || empty($sale)){
					\XLite\Core\TopMessage::addError('Error, no entry found with targetpay id: ' . htmlspecialchars($request->trxid));
					return;
				}
				// Check payment with Targetpay
				$this->initTargetPayment();
				$paid = $this->targetPayCore->checkPayment($request->trxid);
				if ($paid) {
					$status = $transaction::STATUS_SUCCESS;
					// Update local as paid
					$sale->paid = new \DateTime("now");
					\XLite\Core\Database::getRepo('\XLite\Module\TargetPay\Payment\Model\TargetPaySale')->update($sale);
				} elseif($is_callback){
					$status = $transaction::STATUS_INPROGRESS;
				}else {
					$status = $transaction::STATUS_PENDING;
					$this->markCallbackRequestAsInvalid($this->targetPayCore->getErrorMessage());
				}
				// Update transaction status
				$this->transaction->setStatus($status);
			}
		}
	}
}
