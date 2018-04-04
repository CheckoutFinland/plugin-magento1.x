<?php
class Piimega_CheckoutFinland_Helper_Api extends Mage_Core_Helper_Abstract
{
    const PAYMENT_VERSION = "0001";
    const PAYMENT_COUNTRY = "FIN";
    const PAYMENT_TYPE = "0";
    const PAYMENT_ALGORITHM = "3";
    const STORE_NAME_CONFIG_PATH = "general/store_information/name";
    const DEVICE_XML_CODE = 10;
    const DEVICE_HTML_CODE = 1;
    const ADULT_CONTENT_CODE = 2;
    const NORMAL_CONTENT_CODE = 1;

    protected $returnUrl;
    protected $entityObject;
    protected $billingAddress;
    protected $deviceCodes = array();
    protected $contentCodes = array();

    public function __construct()
    {
        $this->returnUrl = Mage::getUrl('checkoutfinland/checkoutfinland/return');
        $this->deviceCodes = array(
            'XML' => self::DEVICE_XML_CODE,
            'HTML' => self::DEVICE_HTML_CODE
        );
        $this->contentCodes = array(
            'ADULT' => self::ADULT_CONTENT_CODE,
            'NORMAL' => self::NORMAL_CONTENT_CODE
        );
    }

    public function getBanks()
    {
        $response = Mage::helper('piimega_checkoutfinland')->sendPost($this->getCheckoutObject());
        if ($response !== false) {
            return $response->payments->payment->banks;
        }
        return array(); // Throw exception?
    }

    protected function _checkEntityObject(Varien_Object $object = null)
    {
        if (is_null($object)) {
            $object = $this->entityObject;
        }

        if (!$object instanceof Mage_Sales_Model_Quote && !$object instanceof Mage_Sales_Model_Order) {
            throw new Exception('Entityobject instance is of wrong type! Should be either Mage_Sales_Model_Quote or Mage_Sales_Model_Order');
        }
    }

    /*
     *  Mage_Sales_Model_Order | Mage_Sales_Model_Quote
     */
    public function setEntityObject(Varien_Object $object)
    {
        $this->_checkEntityObject($object);
        $this->entityObject = $object;
        $this->billingAddress = null;
        return $this;
    }

    public function getCheckoutObject()
    {
        $data = array();
        $data['VERSION']		= $this->_getVersion();
        $data['STAMP']			= $this->_getStamp();
        $data['AMOUNT']			= $this->_getPaymentAmount();
        $data['REFERENCE']		= $this->_getReference();
        $data['MESSAGE']		= $this->_getMessage();
        $data['LANGUAGE']		= $this->_getLanguage();
        $data['MERCHANT']		= $this->_getMerchantId();
        $data['RETURN']			= $this->_getReturnUrl();
        $data['CANCEL']			= $this->_getCancelUrl();
        $data['REJECT']			= $this->_getRejectUrl();
        $data['DELAYED']		= $this->_getDelayedUrl();
        $data['COUNTRY']		= $this->_getPaymentCountry();
        $data['CURRENCY']		= $this->_getPaymentCurrency();
        $data['DEVICE']			= $this->getDevice();
        $data['CONTENT']		= $this->_getContent();
        $data['TYPE']			= $this->_getPaymentType();
        $data['ALGORITHM']		= $this->_getPaymentAlgorithm();
        $data['DELIVERY_DATE']  = $this->_getDeliveryDate();
        $data['FIRSTNAME']		= $this->_getFirstname();
        $data['FAMILYNAME']		= $this->_getFamilyname();
        $data['ADDRESS']		= $this->_getAddress();
        $data['POSTCODE']		= $this->_getPostcode();
        $data['POSTOFFICE']		= $this->_getPostoffice();
        $data['MAC']			= $this->_calculateMac($data);

        $data['EMAIL']			= $this->_getCustomerEmail();
        $data['PHONE']			= $this->_getPhone();

        return $data;

    }

    protected function _getStamp()
    {
        if ($this->entityObject instanceof Mage_Sales_Model_Order) {
            return $this->entityObject->getCheckoutfinlandStamp();
        }
        return time();
    }

    protected function _getVersion()
    {
        return self::PAYMENT_VERSION;
    }

    protected function _getReturnUrl()
    {
        return $this->returnUrl;
    }

    protected function _getCancelUrl()
    {
        return $this->returnUrl;
    }

    protected function _getRejectUrl()
    {
        return $this->returnUrl;
    }

    protected function _getDelayedUrl()
    {
        return $this->returnUrl;
    }

    protected function _getPaymentAmount()
    {
        return intval(floatval($this->entityObject->getGrandTotal()) * 100);
    }

    protected function _getReference()
    {
        if ($this->entityObject instanceof Mage_Sales_Model_Order) {
            return $this->entityObject->getIncrementId();
        }
        return $this->entityObject->getId();
    }

    protected function _getMessage()
    {
        $entityName = $this->entityObject instanceof Mage_Sales_Model_Order ? 'Order' : 'Quote';
        $reference = $this->_getReference();
        $storeName = trim(Mage::getStoreConfig(self::STORE_NAME_CONFIG_PATH, $this->entityObject->getStoreId()));
        $message = $entityName . ' ' . $reference;
        if (!empty($storeName)) {
           $message = $message . ', ' . $storeName;
        }
        return $message;
    }

    protected function _getLanguage()
    {
        $lang = explode('_', (string)Mage::getStoreConfig('general/locale/code'));
        return strtoupper($lang[0]);
    }

    protected function _getMerchantId()
    {
        return $this->_getConfigData('merchant_id');
    }

    protected function _getPaymentCountry()
    {
        return self::PAYMENT_COUNTRY;
    }

    protected function _getPaymentCurrency()
    {
        $fieldName = $this->entityObject instanceof Mage_Sales_Model_Order ? 'order_currency_code' : 'quote_currency_code';
        return $this->entityObject->getData($fieldName);
    }

    public function getDevice()
    {
        if ($this->_getConfigData("use_bank_select") || $this->_getConfigData('use_xml_mode')) {
            return $this->deviceCodes['XML'];
        }
        return $this->deviceCodes['HTML'];
    }

    protected function _getContent()
    {
        if ($this->_getConfigData("may_have_adult_content")) {
            return $this->contentCodes['ADULT'];
        }
        return $this->contentCodes['NORMAL'];
    }

    protected function _getPaymentType()
    {
        return self::PAYMENT_TYPE;
    }

    protected function _getPaymentAlgorithm()
    {
        return self::PAYMENT_ALGORITHM;
    }

    protected function _getDeliveryDate()
    {
        $date = (string)$this->entityObject->getDeliveryDate();
        if (!empty($date)) {
            return date('Ymd', strtotime($date));
        }
        return date('Ymd');
    }

    protected function _getFirstname()
    {
        return substr($this->_getBillingAddress()->getFirstname(), 0, 40);
    }

    protected function _getFamilyname()
    {
        return substr($this->_getBillingAddress()->getLastname(), 0, 40);
    }

    protected function _getAddress()
    {
        $addr = $this->_getBillingAddress();
        $fields = array($addr->getStreet(1), $addr->getStreet(2));
        return substr(implode(' ', $fields), 0, 40);
    }

    protected function _getPostcode()
    {
        return substr((string)$this->_getBillingAddress()->getPostcode(), 0, 14);
    }

    protected function _getPostoffice()
    {
        return substr($this->_getBillingAddress()->getCity(), 0, 18);
    }

    protected function _getPhone()
    {
        return $this->_getBillingAddress()->getTelephone();
    }

    protected function _getCustomerEmail()
    {
        return $this->entityObject->getData('customer_email');
    }

    protected function _calculateMac(array $fields)
    {
        return strtoupper(md5(implode('+', $fields) ."+" .$this->_getPassword()));
    }

    protected function _getPassword()
    {
        return Mage::helper('core')->decrypt($this->_getConfigData('password'));
    }

    protected function _getConfigData($fieldName, $storeId = null)
    {
        if (is_null($storeId)) {
            $storeId = $this->entityObject->getStoreId();
        }
        $prefix = 'payment/piimega_checkoutfinland';
        return Mage::getStoreConfig($prefix . '/' . $fieldName, $storeId);
    }

    protected function _getBillingAddress()
    {
        if (is_null($this->billingAddress)) {
            $this->billingAddress = $this->entityObject->getBillingAddress();
        }
        return $this->billingAddress;
    }
}