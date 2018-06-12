<?php
// This is also paypal refund api, but it is call only once , it authorized by username, password and signature, which is send as header request, we have to also send necessary param like refund_type, amount, currency_code, amount, etc.
	//CreatedBy:- Md Abshar Alam
include('PayPal.php');
	public function refundPaypal(){
		$data = $this->input->post();
		$request_headers = [];
		foreach (getallheaders() as $name => $value) {
			if($name == "username" || $name == "password" || $name == "signature")
		     	$request_headers["$name"] = $value;
		}
		$intializeData = array(
                              	'username'	=> $request_headers['username'],
                              	'password'	=> $request_headers['password'],
                              	'signature'	=> $request_headers['signature'],
                              	'mode'		=> $data['mode'],  //'live'
                              );
        $aryData['transactionID'] = $data['transaction_id'];
        $aryData['refundType'] = $data['refund_type']; //Partial or Full
        $aryData['currencyCode'] = $data['currency_code'];
        $aryData['amount'] = $data['amount'];   //$data['amount'];
        $aryData['memo'] = isset($data['notes'])?$data['notes']:'';


        // Paypal Refund API Call From Library PayPalRefund.php
        $paypalRefund = new Paypal($intializeData);
        $response = $paypalRefund->refundAmount($aryData);
        echo json_encode($response);
        exit;
	}






  // This is refundpaypalApi using rest api of paypal and it will take client id and secret as parameter to authorized and get token . and using that token again call refund api using our necesaary paramaeter like refunc type, amount, currency, transaction_id
  // Created BY:- Md Abshar Alam
  public function paypalRefund(){
    $params = $this->input->post();
    $request_headers = [];
    foreach (getallheaders() as $name => $value) {
      if($name == "client" || $name == "secret")
          $request_headers["$name"] = $value;
    }
    //validation code//
    if(!isset($request_headers['client']) || $request_headers['client'] == ''){
      $validationError = [
        'status'    => false,
        'error_code'  => 404,
        'error_message' => "client is not included in header or may be empty"
      ];
      echo json_encode($validationError);
      exit;
    }
    if(!isset($request_headers['secret']) || $request_headers['secret'] == ''){
      $validationError = [
        'status'    => false,
        'error_code'  => 404,
        'error_message' => "secret is not included in header or may be empty"
      ];
      echo json_encode($validationError);
      exit;
    }
    if(!isset($params['refund_type']) || $params['refund_type'] == ''){
      $validationError = [
        'status'    => false,
        'error_code'  => 404,
        'error_message' => "refund_type parameter missing or empty"
      ];
      echo json_encode($validationError);
      exit;
    }
    elseif($params['refund_type'] == 'partial'){
      if(!isset($params['refund_amount']) || $params['refund_amount'] == '' || $params['refund_amount'] == 0){
        $validationError = [
          'status'    => false,
          'error_code'  => 404,
          'error_message' => "refund_amount parameter missing or invalid"
        ];
        echo json_encode($validationError);
        exit;
      }
      if(!isset($params['currency']) || $params['currency'] == ''){
        $validationError = [
          'status'    => false,
          'error_code'  => 404,
          'error_message' => "currency parameter missing or empty"
        ];
        echo json_encode($validationError);
        exit;
      }
    }
    //validation end//
    
    // Get Access Token from paypal //
    $reqAccess = [
        "url"   => "https://api.sandbox.paypal.com/v1/oauth2/token",
        "data"    => "grant_type=client_credentials",
        "auth"    => "auth",
        "user_pass" => $request_headers['client'] . ":" . $request_headers['secret']
    ];
    $headers = array();
    $headers[] = "Accept: application/json";
    $headers[] = "Accept-Language: en_US";
    $headers[] = "Content-Type: application/x-www-form-urlencoded";
    $accessToken = $this->curlRequest($reqAccess, $headers);
    $accessToken = json_decode($accessToken);
    // end get access token //

    // start refund //
    $reqRefund = array();
    if($params['refund_type'] == "full"){
      $reqRefund = [
          "url"   => "https://api.sandbox.paypal.com/v1/payments/sale/".$params['transaction_id']."/refund"
      ];      
    }elseif($params['refund_type'] == "partial"){
      $data['amount'] = [ 
          "total"   => $params['refund_amount'],
          "currency"  => $params['currency']  
      ];
      $data['invoice_number'] = $params['invoice_number'];
      $reqRefund = [
          "url"   => "https://api.sandbox.paypal.com/v1/payments/sale/".$params['transaction_id']."/refund",
          "data"    => json_encode($data)
      ];  
    }
    $headers = array();
    $headers[] = "Content-Type: application/json";
    $headers[] = "Authorization: Bearer ".$accessToken->access_token;
    $response = $this->curlRequest($reqRefund, $headers);
    //end refund //
    $response = [
      'status'  => true,
      'code'    => 200,
      'message'   => json_decode($response)
    ];
    echo json_encode($response);
    exit;
  }
  protected function curlRequest($requestArr, $headers){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $requestArr['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if(isset($requestArr['data']))
      curl_setopt($ch, CURLOPT_POSTFIELDS, $requestArr['data']);
    else
      curl_setopt($ch, CURLOPT_POSTFIELDS, "{}");
    curl_setopt($ch, CURLOPT_POST, 1);
    if(isset($requestArr['auth']) && $requestArr['auth'] = 'auth')
      curl_setopt($ch, CURLOPT_USERPWD, $requestArr['user_pass']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    curl_close ($ch);
    return $result; 
  }
