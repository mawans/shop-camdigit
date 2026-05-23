<?php 
function whmcs_api_call($action, $params = []) 
{ 
    $apiUrl = 'https://newpolygon.com/billing/includes/api.php'; 
    $identifier = '9Db6IxbryGtbkVuhKNe5X2jIYqcdy3HZ'; 
    $secret = 'n4c6X5H5bWapfyIPNKfoIKIl0LZAS8Pn';

    $postData = array_merge([ 'action' => $action, 'identifier' => $identifier, 'secret' => $secret, 'responsetype' => 'json', ], $params); 
    $ch = curl_init($apiUrl); curl_setopt_array($ch, [ CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($postData), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, ]); 
    $response = curl_exec($ch); if ($response === false) { $error = curl_error($ch); curl_close($ch); 
    return ['error' => true, 'message' => $error]; } curl_close($ch); return json_decode($response, true); 

}