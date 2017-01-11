<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
* See https://www.x-cart.com/license-agreement.html for license details.
*/

namespace XLite\Module\TargetPay\Payment\Model;

/**
 * TargetPay Sale model
 *
 * @Entity
 * @Table  (name="targetpay_sales",
 *      indexes={
 *          @Index (name="idx_targetpay_sales", columns={"order_id", "method"})
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
	 * @Column (type="string", length=64)
	 */
	protected $order_id;


	/**
	 * @Column (type="string", length=10, nullable=true)
	 */
	protected $method;

	/**
	 * @Column (type="integer", nullable=true)
	 */
	protected $amount;

	/**
	 * @Column (type="string", length=64, nullable=true)
	 */
	protected $targetpay_txid;

	/**
	 * @Column (type="string", length=128, nullable=true)
	 */
	protected $targetpay_response;

	/**
	 * @Column (type="datetime", nullable=true)
	 */
	protected $paid;
}
