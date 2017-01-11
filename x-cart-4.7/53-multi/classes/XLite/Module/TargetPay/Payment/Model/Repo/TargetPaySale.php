<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

namespace XLite\Module\TargetPay\Payment\Model\Repo;


class TargetPaySale extends \XLite\Model\Repo\ARepo
{
    const ID_TARGET_PAY_TXID = 'targetpay_txid';
    
    /**
     * Search the data by targetpay id
     * @param unknown $targetpay_txid
     * @return \Doctrine\ORM\PersistentCollection|number
     */
	protected function findByTargetPayId($targetpay_txid)
	{
		$cnd = new \XLite\Core\CommonCell();
		$cnd->{static::ID_TARGET_PAY_TXID} = $targetpay_txid;
	
		return $this->search($cnd);
	}
}