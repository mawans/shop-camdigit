<?php

class WhmcsApiService
{
    private static $apiUrl = 'https://newpolygon.com/billing/includes/api.php';
    private static $identifier = 'GbkGonujne3Yy7nsWG7MVdAoYPNlv6uc';
    private static $secret = 'l6LedAINAlOqqx4VpqpZIDPXdqXfGFse';

    public static function call($action, $params = [])
    {
        $postData = array_merge([
            'action' => $action,
            'identifier' => self::$identifier,
            'secret' => self::$secret,
            'responsetype' => 'json',
        ], $params);

        $ch = curl_init(self::$apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}
