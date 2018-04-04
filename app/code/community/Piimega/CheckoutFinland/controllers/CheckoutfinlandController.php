<?php

class Piimega_CheckoutFinland_CheckoutfinlandController extends Mage_Core_Controller_Front_Action
{
    const STATUS_SUCCESS    = 'success';
    const STATUS_DELAYED    = 'delayed';
    const STATUS_REJECTED   = 'rejected';
    const STATUS_CANCELED   = 'canceled';
    const STATUS_FAILURE    = 'failure';

    const DEFAULT_CANCELED_STATUS_CODE = -1;

    protected $_statuses = array(
        'success'   => array(2, 5, 6, 8, 9, 10),
        'delayed'   => array(3, 4, 7),
        'rejected'  => array(-2, -3, -4, -10),
        'canceled'  => array(-1)
    );

    protected $_data = array();
    protected $_order = null;

    // Redirect to payment page
    public function redirectAction(){

        $order = $this->_getSessionOrder();
        if(!$order){
            $this->_redirectUrl(Mage::getBaseUrl());
            return;
        }

        $payment = Mage::getModel('piimega_checkoutfinland/checkoutfinland');
        $helper = Mage::helper('piimega_checkoutfinland');

        $order->addStatusToHistory($payment->getNewPaymentStatus(), $helper->__('Customer was redirected to Checkout Finland'));
        $order->setCheckoutfinlandStamp(time());
        $order->save();

        $block = $this->getLayout()->createBlock('piimega_checkoutfinland/redirect');
        $data = $payment->getRedirectFormFields();

        if($payment->getDevice() == Piimega_CheckoutFinland_Helper_Api::DEVICE_XML_CODE){
            $response = Mage::helper('piimega_checkoutfinland')->sendPost($data, true);
            if($payment->getUseBankSelect()){
                $method = $helper->getSavedPaymentMethod($order->getPayment());
                if($method){
                    if(isset($response['payments']['payment']['banks'])){
                        $banks = $response['payments']['payment']['banks'];
                        foreach($banks as $code => $bank){
                            if($code == $method){
                                if($block->setBankFormData($bank)){
                                    $this->getResponse()->setBody($block->toHtml());
                                    return;
                                }
                            }
                        }
                    }
                }
            }
            if($payment->getUseXmlMode()){
                $this->loadLayout();
                $layout = $this->getLayout();
                $layout->getBlock('root')->setTemplate('page/1column.phtml');

                $banksBlock = $layout->createBlock('core/template', 'checkoutfinland_banks_block')
                    ->setTemplate('piimega/checkoutfinland/banks.phtml')
                    ->setData('payment_info', $response);

                $layout->getBlock('content')->append($banksBlock);
                $this->renderLayout();
                return;
            }
        }

        $block->setUrl($payment->getCheckoutUrl())->setFields($data);
        $this->getResponse()->setBody($block->toHtml());
    }

    // Data returned from Checkout Finland
    public function returnAction(){

        if(!$this->_validateRequest()){
            $this->_redirect('*/*/failure');
            return;
        }

        $statusData = $this->_getOrderStatusDataByPaymentStatus($this->_data['STATUS']);

        if($statusData['status'] == self::STATUS_FAILURE){
            $this->_redirect('*/*/failure');
            return;
        }

        $order = $this->_getOrder();
        $order->getPayment()->setData('checkoutfinland_transaction_id', $this->_data['TRANSACTION_ID']);

        $this->_processPayment($statusData);
    }

    // Payment canceled locally on Magento side
    public function cancelAction(){

        $order = $this->_getSessionOrder();
        if($order){
            $this->_order = $order;
            $statusData = $this->_getOrderStatusDataByPaymentStatus(self::DEFAULT_CANCELED_STATUS_CODE);
            $this->_processPayment($statusData);
            return;
        }
        $this->_redirect('*/*/failure');
    }

    // Error. Invalid data from Checkout Finland or cannot find order
    public function failureAction() {
        $order = $this->_getOrder();
        if($order == null){
            $this->_getSessionOrder();
        }

        if(is_object($order) && $order->getId()){
            $order->addStatusToHistory('canceled', Mage::helper('piimega_checkoutfinland')->__('An error occurred in the process of payment'), false);
            if($this->_cancelOrder($order)){
                $this->_restoreQuote($order);
            }
        }
        $this->_redirect('checkout/onepage/failure');
    }

    // Process data from Checkout Finland or local payment cancellation
    protected function _processPayment($statusData){
        $order = $this->_getOrder();
        $payment = Mage::getModel('piimega_checkoutfinland/checkoutfinland');
        $order->addStatusToHistory($statusData['order_status'], $statusData['message'], true);

        if($statusData['status'] == self::STATUS_SUCCESS && $order->canInvoice() && $payment->getConfigData('create_invoice')){
            $invoice = $order->prepareInvoice();
            $invoice->register()->capture();
            $order->addRelatedObject($invoice);
        }

        if(($statusData['status'] == self::STATUS_SUCCESS || $statusData['status'] == self::STATUS_DELAYED)  && !$order->getEmailSent()){
            try{
                $order->sendNewOrderEmail();
                $order->setEmailSent(true);
            } catch(Exception $e){
                Mage::logException($e);
            }
        }

        if($statusData['status'] == self::STATUS_REJECTED || $statusData['status'] == self::STATUS_CANCELED){
            Mage::getSingleton('checkout/session')->addError($statusData['message']);
        }

        if($statusData['status'] == $payment->getCanceledPaymentStatus()){
            if($this->_cancelOrder($order)){
                $this->_restoreQuote($order);
            }
        }
        $order->save();

        $this->_redirect($statusData['redirect']);
    }

    protected function _getOrderStatusDataByPaymentStatus($status){
        $payment = Mage::getModel('piimega_checkoutfinland/checkoutfinland');
        $helper = Mage::helper('piimega_checkoutfinland');

        if(in_array($status, $this->_statuses[self::STATUS_SUCCESS])){
            return array(
                'status' => self::STATUS_SUCCESS,
                'order_status' => $payment->getApprovedPaymentStatus(),
                'message' => $helper->__('Payment approved'),
                'redirect' => 'checkout/onepage/success'
            );
        } else if(in_array($status, $this->_statuses[self::STATUS_DELAYED])){
            return array(
                'status' => self::STATUS_DELAYED,
                'order_status' => $payment->getDelayedPaymentStatus(),
                'message' => $helper->__('Payment is being processed by service provider'),
                'redirect' => 'checkout/onepage/success'
            );
        } else if(in_array($status, $this->_statuses[self::STATUS_REJECTED])){
            return array(
                'status' => self::STATUS_REJECTED,
                'order_status' => $payment->getRejectedPaymentStatus(),
                'message' => $helper->__('Payment was rejected by service provider'),
                'redirect' => 'checkout/onepage/failure'
            );
        } else if(in_array($status, $this->_statuses[self::STATUS_CANCELED])){
            return array(
                'status' => self::STATUS_CANCELED,
                'order_status' => $payment->getCanceledPaymentStatus(),
                'message' => $helper->__('Order was canceled by user'),
                'redirect' => $this->_getCanceledOrderRedirectUrl()
            );
        }
        return array(
            'status' => self::STATUS_FAILURE,
            'order_status' => $payment->getCanceledPaymentStatus(),
            'message' => $helper->__('Invalid payment status received from Checkout Finland'),
            'redirect' => 'checkout/onepage/failure'
        );
    }

    protected function _getCanceledOrderRedirectUrl(){
        $redirectUrl = Mage::getModel('piimega_checkoutfinland/checkoutfinland')->getConfigData('canceled_order_redirect_url');
        if(!empty($redirectUrl)){
            return $redirectUrl;
        }
        return 'checkout/cart';
    }

    protected function _collectRequestData(){
        $this->_data = array();
        $this->_data['VERSION']        = isset($_REQUEST['VERSION'])   ?   $_REQUEST['VERSION']    : '';
        $this->_data['STAMP']          = isset($_REQUEST['STAMP'])     ?   $_REQUEST['STAMP']      : '';
        $this->_data['ORDER_ID']       = isset($_REQUEST['REFERENCE']) ?   $_REQUEST['REFERENCE']  : 0;
        $this->_data['TRANSACTION_ID'] = isset($_REQUEST['PAYMENT'])   ?   $_REQUEST['PAYMENT']    : 0;
        $this->_data['STATUS']         = isset($_REQUEST['STATUS'])    ?   $_REQUEST['STATUS']     : 0;
        $this->_data['ALGORITHM']      = isset($_REQUEST['ALGORITHM']) ?   $_REQUEST['ALGORITHM']  : 0;
        return $this->_data;
    }

    protected function _validateRequest(){
        if(empty($this->_data)){
            $this->_collectRequestData();
        }

        $error = false;
        $helper = Mage::helper('piimega_checkoutfinland');

        $generatedMac = strtoupper(hash_hmac("sha256", implode('&', $this->_data), Mage::getModel('piimega_checkoutfinland/checkoutfinland')->getPassword()));

        if ($_REQUEST['MAC'] != $generatedMac) {
            $this->_data['MAC_ORIG'] = $_REQUEST['MAC'];
            $this->_data['MAC_GEN'] = $generatedMac;
            $helper->logError("Error: signatures do not match");
            $error = true;
        }

        if($this->_data['VERSION'] != Piimega_CheckoutFinland_Helper_Api::PAYMENT_VERSION){
            $helper->logError("Error: received invalid payment version");
            $error = true;
        }

        if($this->_data['ALGORITHM'] != Piimega_CheckoutFinland_Helper_Api::PAYMENT_ALGORITHM){
            $helper->logError("Error: received invalid payment algorithm");
            $error = true;
        }

        if($this->_data['TRANSACTION_ID'] <= 0){
            $helper->logError("Error: receiced empty transaction id");
            $error = true;
        }

        $orderId = $this->_data['ORDER_ID'];
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);

        if(is_object($order) && $order->getId()){
            $this->_order = $order;
            if($this->_data['STAMP'] != $order->getCheckoutfinlandStamp()){
                $helper->logError("Error: stamps do not match");
                $error = true;
            }
        } else {
            $helper->logError("Error: failed to find order by increment id");
            $error = true;
        }

        if($error){
            $helper->logError($this->_data);
            return false;
        }
        return true;
    }

    protected function _getSessionOrder(){
        $session = Mage::getSingleton('checkout/session');
        $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
        if ($order->getState() == Mage_Sales_Model_Order::STATE_NEW) {
            return $order;
        }
        return false;
    }

    protected function _getOrder(){
        if($this->_order == null) {
            if (!empty($this->_data) && $this->_data['ORDER_ID'] > 0) {
                $order = Mage::getModel('sales/order')->loadByIncrementId($this->_data['ORDER_ID']);
                if (is_object($order) && $order->getId()) {
                    $this->_order = $order;
                }
            }
        }
        return $this->_order;
    }

    protected function _restoreQuote($order = null){
        if($order == null){
            $order = $this->_getOrder();
        }
        if (is_object($order) && $order->getId()) {
            $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
            if (is_object($quote) && $quote->getId()) {
                $session = Mage::getSingleton('checkout/session');
                $quote->setIsActive(true)->setReservedOrderId(null)->save();
                $session->replaceQuote($quote)->unsLastRealOrderId();
                return true;
            }
        }
        return false;
    }

    protected function _cancelOrder($order = false, $status = false){
        if($order == null){
            $order = $this->_getOrder();
        }
        if(is_object($order) && $order->getId() && $order->canCancel()){
            $order->cancel();

            if(!$status){
                $status = $order->getConfig()->getStateDefaultStatus(Mage_Sales_Model_Order::STATE_CANCELED);
            }
            $order->setStatus($status);
            $order->save();
            return true;
        }
        return false;
    }
}

