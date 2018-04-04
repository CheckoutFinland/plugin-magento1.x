<?php

class Piimega_CheckoutFinland_Block_Redirect extends Mage_Core_Block_Abstract
{
    protected $_url;
    protected $_fields = array();

    public function setBankFormData($data){
        if(!is_array($data) || empty($data) || !isset($data['@attributes']['url'])){
            return false;
        }

        $this->_url = $data['@attributes']['url'];
        unset($data['@attributes']);

        $this->_fields = $data;
        return true;
    }

    public function setUrl($url){
        $this->_url = $url;
        return $this;
    }

    public function setFields($fields){
        $this->_fields = $fields;
        return $this;
    }

    protected function _toHtml(){
        $form = new Varien_Data_Form();
        $form->setAction($this->_url)
            ->setId('piimega_checkoutfinland')
            ->setName('piimega_checkoutfinland')
            ->setMethod('POST')
            ->setUseContainer(true);

        unset($this->_fields['@attributes']);
        foreach ($this->_fields as $key => $value) {
            $form->addField($key, 'hidden', array(
                'name' => $key,

                // Avoid logging warnings if value is an empty array (some params of banks form)
                'value' => (is_array($value) && empty($value)) ? '' : $value
            ));
        }


        $html = '<html><body>';
        $html.= $this->__('You will be redirected to service provider in a few seconds...');
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementById("piimega_checkoutfinland").submit();</script>';
        $html.= '</body></html>';

        return $html;
    }
}
