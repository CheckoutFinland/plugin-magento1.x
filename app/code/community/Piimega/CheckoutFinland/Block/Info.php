<?php

class Piimega_CheckoutFinland_Block_Info extends Mage_Payment_Block_Info{

    protected function _construct(){
        parent::_construct();
        $this->setTemplate('piimega/checkoutfinland/info.phtml');
    }

    // Will be available only if used bank select in checkout
    public function getSavedPaymentMethodDescription(){
        $helper = Mage::helper('piimega_checkoutfinland');

        try{
            /*$savedMethod = $helper->getSavedPaymentMethod($this->getInfo());
            if($savedMethod){
                $methodsData = Mage::getModel('piimega_checkoutfinland/config_source_paymentmethods')->toOptionArray();
                foreach($methodsData as $methodData){
                    if($methodData['value'] == $savedMethod){
                        return $methodData['label'];
                    }
                }
            }*/
            return $helper->getSavedPaymentMethodTitle($this->getInfo());
        } catch(Exception $e){
            $helper->logError($e->getMessage());
        }

        return false;
    }
}
