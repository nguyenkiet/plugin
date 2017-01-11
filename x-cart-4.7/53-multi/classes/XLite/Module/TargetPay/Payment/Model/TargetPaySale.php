<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

namespace XLite\Module\TargetPay\Payment;

/**
 * TargetPay Sale model
 *
 * @Entity
 * @Table  (name="targetpay_sales",
 *      indexes={
 *          @Index (name="range", columns={"order_id", "method"})
 *      }
 * )
 */
class TargetPaySale extends \XLite\Model\AEntity
{
	/**
	 * @Id
	 * @GeneratedValue (strategy="AUTO")
	 * @Column         (type="integer", options={ "unsigned": true })
	 */
	protected $id;
	
	
	/**
	 * @Column (type="text", length=64)
	 */
	protected $order_id;
	
	
	/**
	 * @Column (type="text", length=10)
	 */
	protected $method = '';
	
	/**
	 * @Column (type="integer")
	 */
	protected $amount = 0;
	
	/**
	 * @Column (type="text", length=64)
	 */
	protected $targetpay_txid = '';
	
	/**
	 * @Column (type="text", length=128)
	 */
	protected $targetpay_response = '';
	
	/**
	 * @Column (type="datetime")
	 */
	protected $paid = '';
}
