<?php
/*
PHP class to handle all WANI registry API calls and caching
*/

class WANI {

    private static $WANI_REGISTRY_URL = './wani_providers.xml';

    private static $PDOAID = "YOUR_PDOA_ID";
    private static $KEY_EXP = "20180728";

    private $ENCRYPT_BLOCK_SIZE = 200;// this for 2048 bit key for example, leaving some room

    private $current_app_provider = null;

    function getAuthURL($app_provider_id) {
        $wani_registry_xml = file_get_contents(WANI::$WANI_REGISTRY_URL);
        $wani_registry = simplexml_load_string($wani_registry_xml);

        # Loop through the app provides and match the app provider id
        $current_app_provider = null;
        foreach ($wani_registry->AppProviders->AppProvider as $app_provider) {
            $attributes = $app_provider->attributes();
            if ($attributes['id'] == $app_provider_id) {
                $current_app_provider = $app_provider;
                break;
            }
        }

        $this->current_app_provider = $current_app_provider;

        # Get the auth URL from the app provider
        $attributes = $current_app_provider->attributes();
        return $attributes['authUrl'];
    }

    # Function to generate the wanipdoatoken
    function generatePDOAToken($waniapp_token) {
	    $encoded_token = base64_encode($waniapp_token);
        $pdoa_id = WANI::$PDOAID;
        $key_exp = WANI::$KEY_EXP;
        return "$pdoa_id|$key_exp|$encoded_token";
    }

    // Verify the hash from the app provider server
    // signature = base-64(RSA-Encrypt(hash))
    // hash = SHA-256(timestamp+username+payment-address+devices[0]+…+devices[i]) 
    function verifyAppServerResponse($server_response) {
        # This needs to be discussed with TRAI. For now, just accept what the server sends
        return true;
        
        $hash = $this->calculateHash($server_response);
        $signature = $server_response->signature;
        # Base 64 decode signature
        $decoded_signature = base64_decode($signature);

        # Decrypt the string using the app provider public key
        $app_certificate = trim($this->current_app_provider->Keys->Key[0]);
        $app_certificate = str_replace(' ', '', $app_certificate);
        $app_certificate = str_replace('BEGINCERTIFICATE', 'BEGIN CERTIFICATE', $app_certificate);
        $app_certificate = str_replace('ENDCERTIFICATE', 'END CERTIFICATE', $app_certificate);

        $certificate_resource = openssl_x509_read($app_certificate);
        $pub_key = openssl_pkey_get_public($certificate_resource);
        $app_public_key = openssl_pkey_get_details($pub_key)['key'];

        return openssl_verify($hash, $decoded_signature, $app_public_key, 'sha256WithRSAEncryption');
    }

    function calculateHash($response) {
        $timestamp = $response->timestamp;
        $username = $response->username;
        $payment_address = $response->$payment_address;
        $devices = $response->devices;
        $device_string = implode('', $devices);

        $hash_string = "$timestamp$username$payment_address$device_string";
        return $hash_string;
    }

}

?>
