<?php

require_once('lib/WebToPay.php');

defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

/**
 *
 * @author EVP International
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (C) 2004-2008 soeren - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.org
 */
if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

class plgVMPaymentPaysera extends vmPSPlugin {


    public static $_this = false;

    function __construct(& $subject, $config) {

        parent::__construct($subject, $config);

        $this->_loggable   = true;
        $this->tableFields = array_keys($this->getTableSQLFields());

        //admin config fields
        $varsToPush = array(
            'module_name'            => array('paysera', 'char'),
            'paysera_merchant_email' => array('', 'char'),
            'paysera_project_id'     => array(0, 'int'),
            'paysera_project_pass'   => array(0, 'int'),
            'sandbox'                => array(0, 'int'),
            'status_pending'         => array('', 'char'),
            'status_success'         => array('', 'char'),
            'status_canceled'        => array('', 'char'),
            'payment_logos'          => array('', 'char'), //required
            'tax_id'                 => array(0, 'int'), //required
        );

        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    protected function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('Payment Paysera Table');
    }

    function getTableSQLFields() {
        $SQLfields = array(
            'id'                  => ' tinyint(1) unsigned NOT NULL AUTO_INCREMENT ',
            'virtuemart_order_id' => ' int(11) UNSIGNED DEFAULT NULL',
            'payment_name'        => 'varchar(5000)',
            'payment_method_id'   => ' mediumint(1) UNSIGNED DEFAULT NULL',
            'paysera_custom'      => ' varchar(255)  ',
        );
        return $SQLfields;
    }

    function plgVmConfirmedOrder($cart, $order) {
        $method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id);

        if (!($method)) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $lang = JFactory::getLanguage();
        $lang->load('plg_vmpayment_paysera', JPATH_ADMINISTRATOR);

        if (!class_exists('VirtueMartModelOrders'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        if (!class_exists('VirtueMartModelCurrency'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');

        $currencyModel = new VirtueMartModelCurrency();
        $currency      = $currencyModel->getCurrency($order['details']['BT']->user_currency_id);

        $orderID         = $order['details']['BT']->virtuemart_order_id;
        $paymentMethodID = $order['details']['BT']->virtuemart_paymentmethod_id;

        $lang     =& JFactory::getLanguage();
        $language = $lang->getLocale();

        try {
            $request = WebToPay::buildRequest(array(
                'projectid'     => $method->paysera_project_id,
                'sign_password' => $method->paysera_project_pass,

                'orderid'       => $orderID,
                'amount'        => intval(number_format($order['details']['BT']->order_total, 2, '', '')),
                'currency'      => $currency->currency_code_3,
                'lang'          => (in_array('en', $language)) ? 'ENG' : 'LTU',

                'accepturl'     => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=' . $paymentMethodID),
                'cancelurl'     => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=cart'),
                'callbackurl'   => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&pm=' . $paymentMethodID),
                'payment'       => '',
                'country'       => 'LT',

                'logo'          => '',
                'p_firstname'   => $order['details']['BT']->first_name,
                'p_lastname'    => $order['details']['BT']->last_name,
                'p_email'       => $order['details']['BT']->email,
                'p_street'      => $order['details']['BT']->address_1,
                'p_city'        => $order['details']['BT']->city,
                'p_state'       => '',
                'p_zip'         => $order['details']['BT']->zip,
                'test'          => $method->sandbox,
            ));
        } catch (WebToPayException $e) {
            echo get_class($e) . ': ' . $e->getMessage();
        }

        $html = '<form action="' . WebToPay::getPaymentUrl(strtoupper($language[4])) . '" method="post" name="vm_paysera_form" >';
        foreach ($request as $name => $value) {
            $html .= '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($value) . '" />';
        }
        $html .= '</form>';
        $html .= '<script type="text/javascript">document.vm_paysera_form.submit();</script>';

        // Prepare data that should be stored in the database
        $dbValues                        = array();
        $dbValues['virtuemart_order_id'] = $orderID;
        $dbValues['payment_method_id']   = $order['details']['BT']->virtuemart_paymentmethod_id;
        $dbValues['payment_name']        = $this->renderPluginName($method, $order);
        $dbValues['paysera_custom']      = '';
        $dbValues['order_number']        = '';

        $this->storePSPluginInternalData($dbValues);

        // 	2 = don't delete the cart, don't send email and don't redirect
        return $this->processConfirmedOrderPaymentResponse(2, $cart, $order, $html, '');
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        $this->getPaymentCurrency($method);
        $paymentCurrencyId = $method->payment_currency;
    }

    function plgVmOnPaymentResponseReceived(&$html) {
        $method = $this->getVmPluginMethod(JRequest::getInt('pm', 0));
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        if (!class_exists('VirtueMartCart'))
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');

        // get the correct cart / session
        $cart = VirtueMartCart::getCart();
        $cart->emptyCart();

        return true;
    }


    function plgVmOnUserPaymentCancel() {
        $data = JRequest::get('get');

        $method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        if (!class_exists('VirtueMartModelOrders'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');

        $this->handlePaymentUserCancel($data['oid']);

        return true;
    }

    function plgVmOnPaymentNotification() {
        $callbackData = JRequest::get('get');

        $method = $this->getVmPluginMethod($callbackData['pm']);
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        if (!class_exists('VirtueMartModelCurrency'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');

        if (!class_exists('VirtueMartModelOrders'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');

        $method = $this->getVmPluginMethod($callbackData['pm']);

        if ($callbackData[WebToPay::PREFIX . 'status'] != '1') {
            exit('Status not accepted: ' . $callbackData[WebToPay::PREFIX . 'status']);
        }

        $orderID      = $callbackData[WebToPay::PREFIX . 'orderid'];
        $Order        = new VirtueMartModelOrders();
        $orderDetails = $Order->getOrder($orderID);

        $currencyModel = new VirtueMartModelCurrency();
        $currency      = $currencyModel->getCurrency($orderDetails['details']['BT']->user_currency_id);

        if ($callbackData[WebToPay::PREFIX . 'amount'] != ($orderDetails['details']['BT']->order_total * 100)) {
            exit('Bad amount: ' . $callbackData[WebToPay::PREFIX . 'amount']);
        }
        if ($callbackData[WebToPay::PREFIX . 'currency'] != $currency->currency_code_3) {
            exit('Bad currency: ' . $callbackData[WebToPay::PREFIX . 'currency']);
        }

        try {
            WebToPay::toggleSS2(true);
            $response = WebToPay::checkResponse($callbackData, array(
                'projectid'     => $method->paysera_project_id,
                'sign_password' => $method->paysera_project_pass,
            ));
        } catch (Exception $e) {
            exit(get_class($e) . ': ' . $e->getMessage());
        };

        echo 'OK ';

        $modelOrder                   = new VirtueMartModelOrders();
        $order['order_status']        = $method->status_success;
        $order['virtuemart_order_id'] = $orderID;
        $order['customer_notified']   = 1; //still not working
        $order['comments']            = '';
        $modelOrder->updateStatusForOneOrder($orderID, $order, true);
        exit();
    }

    function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
        return 0;
    }

    protected function checkConditions($cart, $method, $cart_prices) {
        return true;
    }

    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) {
        return $this->OnSelectCheck($cart);
    }

    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array()) {
        return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    function plgVmonShowOrderPrintPayment($order_number, $method_id) {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPayment($name, $id, &$data) {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    /**
    public function plgVmOnCheckoutCheckDataPayment($psType, VirtueMartCart $cart) {
    return null;
    }
     */

    /**
    public function plgVmOnUpdateOrderPayment(  $_formData) {
    return null;
    }
     */

    /**
    public function plgVmOnUpdateOrderLine(  $_formData) {
    return null;
    }
     */

    /**
    public function plgVmOnEditOrderLineBE(  $_orderId, $_lineId) {
    return null;
    }
     */

    /**
    public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
    return null;
    }
     */

}


