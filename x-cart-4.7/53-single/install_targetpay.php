<?php

require_once 'top.inc.php';

$paths = [		
		'classes/XLite/Module/TargetPay/iDEAL/install.yaml',
		'classes/XLite/Module/TargetPay/Paysafe/install.yaml',
		'classes/XLite/Module/TargetPay/Bancontact/install.yaml',
		'classes/XLite/Module/TargetPay/Sofort/install.yaml',
		'classes/XLite/Module/TargetPay/Creditcard/install.yaml',
];

foreach ($paths as $path){
	\XLite\Core\Database::getInstance()->loadFixturesFromYaml($path);
}
echo "Done!";
