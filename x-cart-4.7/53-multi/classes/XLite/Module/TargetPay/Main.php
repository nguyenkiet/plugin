<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

namespace XLite\Module\TargetPay;

/**
 * Main module
 */
abstract class Main extends \XLite\Module\AModule
{

	const PP_METHOD_IDEAL  		= 'iDEAL';
	const PP_METHOD_BANCONTACT 	= 'Bancontact';
	const PP_METHOD_CREDITCARD  = 'Creditcard';
	const PP_METHOD_PAYSAFE  	= 'Paysafe';
	const PP_METHOD_SOFORT   	= 'Sofort';
	
    /**
     * Author name
     *
     * @return string
     */
    public static function getAuthorName()
    {
        return 'TargetPay';
    }

    /**
     * Module name
     *
     * @return string
     */
    public static function getModuleName()
    {
        return 'TargetPay';
    }

    /**
     * Module description
     *
     * @return string
     */
    public static function getDescription()
    {
        return 'Enables taking payments for your online store via TargetMedia\'s services.';
    }

    /**
     * Returns payment method
     *
     * @param string  $serviceName Service name
     * @param boolean $enabled     Enabled status OPTIONAL
     *
     * @return \XLite\Model\Payment\Method
     */
    public static function getPaymentMethod($serviceName, $enabled = null)
    {
    	if (!isset(static::$paymentMethod[$serviceName])) {
    		static::$paymentMethod[$serviceName] = \XLite\Core\Database::getRepo('XLite\Model\Payment\Method')
    		->findOneBy(array('service_name' => $serviceName));
    		if (!static::$paymentMethod[$serviceName]) {
    			static::$paymentMethod[$serviceName] = false;
    		}
    	}
    	return static::$paymentMethod[$serviceName]
    	&& (
    			is_null($enabled)
    			|| static::$paymentMethod[$serviceName]->getEnabled() === (bool) $enabled
    			)
    			? static::$paymentMethod[$serviceName]
    			: null;
    }
    /**
     * Get module major version
     *
     * @return string
     */
    public static function getMajorVersion()
    {
        return '5.3';
    }

    /**
     * Module version
     *
     * @return string
     */
    public static function getMinorVersion()
    {
        return '0';
    }

    /**
     * The module is defined as the payment module
     *
     * @return integer|null
     */
    public static function getModuleType()
    {
    	return static::MODULE_TYPE_PAYMENT;
    }
}
