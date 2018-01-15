<?php
include('./wani.php');

if (!isset($_GET['waniapptoken'])) {
    return;
}

$wani_app_token = trim($_GET['waniapptoken']);
$app_provider_id = explode('|', $wani_app_token)[0];

$wani = new WANI();
$app_auth_url = $wani->getAuthURL($app_provider_id);
# Sign the waniapptoken using the provider private key
$wanipdoatoken = $wani->generatePDOAToken($wani_app_token);


# Send it to the app auth url
$url = "$app_auth_url?wanipdoatoken=$wanipdoatoken";

$app_backend_response = file_get_contents($url);
$response_json = json_decode($app_backend_response);

header('Content-Type: application/json');

# verify the app backend response
if ($wani->verifyAppServerResponse($response_json)) {
	http_response_code(200);
	$portal_response = array(
		"paymentUrl" => "https://YOUR_PAYMENT_URL/"
	);
	echo json_encode($portal_response);
	return;
}

http_response_code(400);
echo $app_backend_response;
