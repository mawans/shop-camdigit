
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

function whmcs_api_call($action, $params = [])
{
    $apiUrl = 'https://newpolygon.com/billing/includes/api.php';
    $identifier = '9Db6IxbryGtbkVuhKNe5X2jIYqcdy3HZ';
    $secret = 'n4c6X5H5bWapfyIPNKfoIKIl0LZAS8Pn';

    $postData = [
        'action' => $action,
        'identifier' => $identifier,
        'secret' => $secret,
        'responsetype' => 'json',
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);

    if ($response === false) {
        die('CURL ERROR: ' . curl_error($ch));
    }

    curl_close($ch);

    echo '<pre>';
    var_dump($response);
    echo '</pre>';
}

whmcs_api_call('GetProducts');
