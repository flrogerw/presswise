<?php 
/**


 */

$options = array(
		CURLOPT_URL => 'https://successories.mypresswise.com/o/order.php?orderID=10528',
		CURLOPT_TIMEOUT => 30,
		CURLOPT_POST => 1,
		CURLOPT_CUSTOMREQUEST => "POST",
		//CURLOPT_POSTFIELDS => $sPayload,
		//CURLOPT_HTTPHEADER => $this->setAuthHeader( strlen($sPayload) ),
		CURLOPT_RETURNTRANSFER => true );

$cURL = curl_init();
curl_setopt_array( $cURL, $options );
$curl_result = curl_exec($cURL);


var_dump( $curl_result );

var_dump(curl_error( $cURL ));

