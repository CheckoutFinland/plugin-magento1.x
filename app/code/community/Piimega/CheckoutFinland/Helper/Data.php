<?php

class Piimega_CheckoutFinland_Helper_Data extends Mage_Core_Helper_Abstract
{
	const ERROR_LOG_FILENAME = 'checkoutfinland_error.log';
	const DEBUG_LOG_FILENAME = 'checkoutfinland_debug.log';

	public function log($data, $error = false){
		if($error){
			Mage::log($data, Zend_Log::ERR, self::ERROR_LOG_FILENAME);
		} else {
			Mage::log($data, Zend_Log::DEBUG, self::DEBUG_LOG_FILENAME);
		}
	}

	public function logError($data){
		$this->log($data, true);
	}

	public function sendPost($post, $resultAsArray) {
		$url = Mage::getModel('piimega_checkoutfinland/checkoutfinland')->getCheckoutUrl();

		$options = array(
			CURLOPT_POST 			=> 1,
			CURLOPT_HEADER 			=> 0,
			CURLOPT_URL 			=> $url,
			CURLOPT_FRESH_CONNECT 	=> 1,
			CURLOPT_RETURNTRANSFER 	=> 1,
			CURLOPT_FORBID_REUSE 	=> 1,
			CURLOPT_TIMEOUT 		=> 20,
			CURLOPT_POSTFIELDS 		=> http_build_query($post)
		);

		$ch = curl_init();
		curl_setopt_array($ch, $options);
		$result = curl_exec($ch);
		curl_close($ch);

		$result = simplexml_load_string($result);
		if($resultAsArray){
			$result = json_encode($result);
			$result = json_decode($result, true);
		}

		return $result;
	}

	public function getSavedPaymentMethod($payment)
    {
        return $this->_getAdditionalInformationField($payment, Piimega_CheckoutFinland_Model_Checkoutfinland::PRESELECTED_METHOD_PARAM);
	}

    public function getSavedPaymentMethodTitle($payment)
    {
        return $this->_getAdditionalInformationField($payment, Piimega_CheckoutFinland_Model_Checkoutfinland::PRESELECTED_METHOD_TITLE_PARAM);
    }

    protected function _getAdditionalInformationField(Varien_Object $payment, $param)
    {
        if ($payment instanceof Mage_Sales_Model_Quote_Payment || $payment instanceof Mage_Sales_Model_Order_Payment) {
            $data = $payment->getAdditionalInformation();
            if (is_array($data) && isset($data[$param]) && !empty($data[$param])) {
                return $data[$param];
            }
        }
        return false;
    }
}

