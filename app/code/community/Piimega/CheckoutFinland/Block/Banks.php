<?php

class Piimega_CheckoutFinland_Block_Banks extends Mage_Core_Block_Abstract
{
    protected $_formData = array();

    public function setFormData($data){
        if(!is_array($data)){
            $data = (array)$data;
        }
        $this->_formData = $data;
    }

    public function getFormData(){
        return $this->_formData;
    }
}
