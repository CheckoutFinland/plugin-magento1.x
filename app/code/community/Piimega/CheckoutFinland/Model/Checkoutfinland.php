<?php

class Piimega_CheckoutFinland_Model_Checkoutfinland extends Mage_Payment_Model_Method_Abstract
{
    const PRESELECTED_METHOD_PARAM       = 'checkoutfinland_preselected_method';
    const PRESELECTED_METHOD_TITLE_PARAM =  'checkoutfinland_preselected_method_title';

    protected $_code                    = 'piimega_checkoutfinland';
    protected $_isGateway = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = true;
    protected $_canSaveCc               = true;

    protected $_allowedCurrencies       = array('EUR');

    protected $_infoBlockType           = 'piimega_checkoutfinland/info';
    protected $_formBlockType           = 'piimega_checkoutfinland/form';

    protected $_checkoutUrl             = 'https://payment.checkout.fi';

    protected $_currentOrder = null;


    public function getRedirectFormFields()
    {
        $order = $this->getOrder();
        if(!is_object($order) || !$order->getId()){
            Mage::getSingleton('core/session')->addError(Mage::helper('piimega_checkoutfinland')->__('Cannot find order'));
            $url = Mage::getUrl('checkout/onepage/failure');
            $response = Mage::app()->getFrontController()->getResponse();
            $response->setRedirect($url);
        }
        return Mage::helper('piimega_checkoutfinland/api')->setEntityObject($order)->getCheckoutObject();
    }

    public function getOrder()
    {
        if (!$this->_currentOrder) {
            $this->_currentOrder = Mage::getModel('sales/order');
            $this->_currentOrder->load(Mage::getSingleton('checkout/session')->getLastOrderId());
        }
        return $this->_currentOrder;
    }

    /*
     *  @TODO Remove and use through api helper
     */
    public function getDevice()
    {
        if($this->getConfigData("use_bank_select") || $this->getConfigData('use_xml_mode')){
            return Piimega_CheckoutFinland_Helper_Api::DEVICE_XML_CODE;
        }
        return Piimega_CheckoutFinland_Helper_Api::DEVICE_HTML_CODE;
    }

    /*
     *  @TODO Remove and use through api helper
     */
    public function getUseXmlMode()
    {
        return $this->getConfigData('use_xml_mode');
    }

    /*
     *  @TODO Remove and use through api helper
     */
    public function getPassword()
    {
        return Mage::helper('core')->decrypt($this->getConfigData("password"));
    }

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('checkoutfinland/checkoutfinland/redirect');
    }

    public function canUseForCurrency($currencyCode)
    {
        return in_array($currencyCode, $this->_allowedCurrencies);
    }

    public function setCurrentOrder($order)
    {
        $this->_currentOrder = $order;
    }

    public function authorize(Varien_Object $payment, $amount)
    {
        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {
        return $this;
    }

    public function getNewPaymentStatus()
    {
        $status = $this->getConfigData("new_order_status");
        if (!$status) {
            $status = $this->getOrder()->getConfig()->getStateDefaultStatus(Mage_Sales_Model_Order::STATE_NEW);
        }
        return $status;
    }

    public function getApprovedPaymentStatus()
    {
        $status = $this->getConfigData("approved_order_status");
        if (!$status) {
            $status = $this->getOrder()->getConfig()->getStateDefaultStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
        }
        return $status;
    }

    public function getDelayedPaymentStatus()
    {
        $status = $this->getConfigData("delayed_order_status");
        if (!$status) {
            $status = $this->getOrder()->getConfig()->getStateDefaultStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        }
        return $status;
    }

    public function getRejectedPaymentStatus()
    {
        $status = $this->getConfigData("rejected_order_status");
        if (!$status) {
            $status = $this->getOrder()->getConfig()->getStateDefaultStatus(Mage_Sales_Model_Order::STATE_CANCELED);
        }
        return $status;
    }

    public function getCanceledPaymentStatus()
    {
        return $this->getOrder()->getConfig()->getStateDefaultStatus(Mage_Sales_Model_Order::STATE_CANCELED);
    }

    public function getUseBankSelect()
    {
        return $this->getConfigData("use_bank_select");
    }

    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $this->getInfoInstance()->setAdditionalInformation(self::PRESELECTED_METHOD_PARAM, $data->getData(self::PRESELECTED_METHOD_PARAM));
        $this->getInfoInstance()->setAdditionalInformation(self::PRESELECTED_METHOD_TITLE_PARAM, $data->getData(self::PRESELECTED_METHOD_TITLE_PARAM));
        return $this;
    }

    public function createFormBlock($name)
    {
        $block = $this->getLayout()->createBlock('piimega_checkoutfinland/form', $name)
            ->setMethod('piimega_checkoutfinland')
            ->setPayment($this->getPayment())
            ->setTemplate('piimega/checkoutfinland/form.phtml');
        return $block;
    }

    public function getCheckoutUrl()
    {
        return $this->_checkoutUrl;
    }
}
