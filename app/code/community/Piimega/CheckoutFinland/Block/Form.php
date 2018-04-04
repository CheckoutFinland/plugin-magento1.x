<?php

class Piimega_CheckoutFinland_Block_Form extends Mage_Payment_Block_Form
{
    protected $banks;

    protected function _construct()
    {
        parent::_construct();

        $payment = Mage::getModel('piimega_checkoutfinland/checkoutfinland');
        if (!$payment->getUseBankSelect()) {
            $this->setTemplate('piimega/checkoutfinland/form.phtml');
        } else {
            $this->setTemplate('piimega/checkoutfinland/form_select.phtml');
            $quote = Mage::helper('checkout/cart')->getQuote();
            $this->banks = Mage::helper('piimega_checkoutfinland/api')->setEntityObject($quote)->getBanks();
        }
    }

    public function getPaymentMethods()
    {
        $methods = array();
        foreach ($this->banks as $bankX) {
            foreach ($bankX as $bank => $fields) {
                $methods[] = array(
                    'code' => $bank,
                    'icon' => (string)$fields['icon'],
                    'name' => (string)$fields['name']
                );
            }
        }
        return $methods;
    }

    public function getPreselectMethodParam()
    {
        return Piimega_CheckoutFinland_Model_Checkoutfinland::PRESELECTED_METHOD_PARAM;
    }

    public function getPreselectMethodTitleParam()
    {
        return Piimega_CheckoutFinland_Model_Checkoutfinland::PRESELECTED_METHOD_TITLE_PARAM;
    }
}
