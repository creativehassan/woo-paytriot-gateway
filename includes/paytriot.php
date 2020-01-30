<?php
class WPG_Merchant_Api {

    private $_key;
    private $_secret;
    private $_3des_key;
    private $_serviceUrl;
    private $_version = '1.0';
    private $_test_mode = 0;
    private $_verbose = false;

    private $_lastUri;
    private $_lastRequest;
    private $_lastResponse;
    private $_lastCurlInfo;
    private $_lastCurlError;

    public function getVersion() { return $this->_version; }

    public function __construct($service_url, $key, $secret, $verboseMode = false, $des_key)
    {
        $this->_serviceUrl = $service_url;
        $this->_key = $key;
        $this->_secret = $secret;
        $this->_verbose = $verboseMode;
        $this->_3des_key = $des_key;
    }

    private function _sign($params)
    {
        $strToSign = '';
        $params['key'] = $this->_key;
        $params['ts'] = time();
        foreach ($params as $k => $v)
            if($v !== NULL)
                $strToSign .= "$k:$v:";
        $strToSign .= $this->_secret;

        $params['sign'] = md5($strToSign);
        return $params;
    }

    public function encrypt3DES($data)
    {
        $len = strlen($this->_3des_key);
        $key = $len < 24 ? $this->_3des_key.substr($this->_3des_key, 0, 24 - $len) : $this->_3des_key;

        return openssl_encrypt($data, 'des-ede3-cbc', $key, false, substr($this->_3des_key, 0, 8));
    }

    private function _request($servicename, $params)
    {
        ini_set('max_execution_time', 300);

        $uri = $this->_serviceUrl . '/v/' . $this->_version .'/function/'. $servicename ;
        $this->_lastUri = $uri;

        if($this->_test_mode)
        {
            $params['test'] = 1;
        }
		$str = json_encode($params);
        
        $final_data = array(
		    'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
		    'body'        => $str,
		    'method'      => 'POST',
		    'data_format' => 'body',
		);
        $this->_lastRequest = $str;
		
		$response_data = wp_remote_post( $uri, $final_data );
		$response = wp_remote_retrieve_body( $response_data );

        $this->_lastResponse = $response;
        $this->_lastCurlInfo = curl_getinfo($ch);
        if(curl_errno($ch)){
            $this->_lastCurlError = curl_error($ch);
//            var_dump(curl_errno($ch));
        } else
            $this->_lastCurlError = null;

        curl_close($ch);

        if($this->_verbose)
            echo  '<br>URL: '. $uri . '<br>REQ: '. $str . '<br>RSP: ' . $response .'<br>';

        return $response;
    }

    public function getLastUri() {
        return $this->_lastUri;
    }
    public function getLastRequest() {
        return $this->_lastRequest;
    }
    public function getLastResponse() {
        return $this->_lastResponse;
    }
    public function getLastCurlInfo() {
        return $this->_lastCurlInfo;
    }
    public function getLastCurlError() {
        return $this->_lastCurlError;
    }


    // custom action to use array parameters instead of each one
    public function customAction($action, $params) {
        $params = $this->_sign($params);
        $response = $this->_request($action, $params);
        return json_decode($response, true);
    }

    public function createPaymentRequestLink($type, $amount, $account_id, $currency, $url_user_on_success = null, $url_user_on_fail = null, $url_api_on_success = null, $url_api_on_fail = null, $no_expiration = 0, $deposit_category)
    {
        $params = $this->_sign(compact('type', 'amount', 'account_id', 'currency', 'url_user_on_success', 'url_user_on_fail', 'url_api_on_success', 'url_api_on_fail', 'no_expiration', 'deposit_category'));
        $response = $this->_request('create_payment_request_link', $params);

        return json_decode($response, true);
    }
}