<?php
/**
    TargetPay module class for Zencart
    (C) Copyright Yellow Melon B.V. 2013
*/
 
define('TARGETPAY_TABLE_HEADING_TRANSACTION_DETAILS', 'Transactiebijzonderheden');
define('TARGETPAY_TABLE_HEADING_TRANSACTION_ID', 'Transactie ID');
define('TARGETPAY_TABLE_HEADING_PURCHASE_ID', 'Aankoop ID');
define('TARGETPAY_TABLE_HEADING_ORDER_ID', 'Bestel ID');
define('TARGETPAY_TABLE_HEADING_TRANSACTION_STATUS', 'Transactiestatus');
define('TARGETPAY_TABLE_HEADING_ORDER_STATUS', 'Bestelstatus');
define('TARGETPAY_TABLE_HEADING_TRANSACTION_DATE_TIME', 'Transactie tijdstip');
define('TARGETPAY_TABLE_HEADING_LAST_MODIFIED', 'Laatst bewerkt');
define('TARGETPAY_TABLE_HEADING_SUGGESTED_ACTION', 'Aanbevolen handeling');

define('TARGETPAY_TEXT_SEARCH_TRANSACTION_ID', 'Transactie ID: ');
define('TARGETPAY_TEXT_SEARCH_ORDER_ID', 'Bestel ID: ');
define('TARGETPAY_TEXT_FILTER', 'Filter');
define('TARGETPAY_TEXT_FILTER_ALL', 'Alle');
define('TARGETPAY_TEXT_FILTER_INCOMPLETE', 'Onafgeronde');
define('TARGETPAY_TEXT_FILTER_COMPLETE', 'Afgeronde');
define('TARGETPAY_TEXT_FILTER_CLEANUP', 'Opruimen');
define('TARGETPAY_TEXT_RESTOCK_QUANTITY', 'Producten restocken tijdens het opruimen?');

define('TARGETPAY_TEXT_DETAILS', 'Details');
define('TARGETPAY_NAME_ZERO_STATUS_ORDER', 'Nul-status bestelling');
define('TARGETPAY_TEXT_CHECK_STATUS', 'Status peilen');
define('TARGETPAY_TEXT_REFUND', 'Transactie annuleren');
define('TARGETPAY_TEXT_CHANGE_STATUS', 'Bestelstatus wijzigen');
define('TARGETPAY_TEXT_NO_ACTION', '[Geen handeling]');


define('TARGETPAY_TEXT_CREATE_ORDER', 'Maak bestelling aan');

define('TARGETPAY_MESSAGE_SUCCESS_REFUND', 'Transactiestatus is op "refunded" gezet.');
define('TARGETPAY_MESSAGE_WARNING_REFUND', 'Het was niet mogelijk om de transactiestatus op "refunded" te zetten.');
define('TARGETPAY_MESSAGE_SUCCESS_STATUS', 'Transactiestatus opgehaald.');
define('TARGETPAY_MESSAGE_WARNING_STATUS', 'Transactiestatus kon niet opgehaald worden.');

define('TARGETPAY_HEADING_CUSTOMER_ID', 'Klant ID');
define('TARGETPAY_HEADING_TRANSACTION_AMOUNT', 'Transactiebedrag');
define('TARGETPAY_HEADING_CUSTOMER_ACCOUNT', 'Bankrekening klant');
define('TARGETPAY_HEADING_CUSTOMER_NAME', 'Naam klant');
define('TARGETPAY_HEADING_CUSTOMER_CITY', 'Stad klant');

define('TARGETPAY_HEADING_EXPLANATION', 'Uitleg');
define(
    'TARGETPAY_TEXT_EXPLANATION', 'Bovenstaande tabel geeft een overzicht van de TargetPay iDeal transacties 
                in het systeem. Aanvankelijk zijn alleen de onafgeronde transacties 
                vertoond; dit ziet u aan het bolletje voor ' . TARGETPAY_TEXT_FILTER_INCOMPLETE . 
    ' naast het knopje ' . TARGETPAY_TEXT_FILTER . '. 
                U kunt daar ook kiezen om ' . TARGETPAY_TEXT_FILTER_COMPLETE . ' of ' . 
    TARGETPAY_TEXT_FILTER_ALL . ' transacties te laten vertonen. 
                ' . TARGETPAY_TEXT_FILTER_COMPLETE . ' transacties zijn transacties die afgerond zijn. ' . 
    TARGETPAY_TEXT_FILTER_INCOMPLETE . ' 
                transacties zijn transacties die nog verder bewerkt moeten worden. 
                '
);
?>
