<?php
define('API_KEY', 'KWblPK2BFHzg2UFU5TgYgVZaU3iP7MzZ');

/**
 * Send request to the MultiParcels API
 *
 * @param string $endpoint
 * @param string $method
 * @param array $data
 *
 * @return mixed
 * @throws Exception
 */
function request($endpoint, $method, $data = [])
{
    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . API_KEY,
    ];

    $ch  = curl_init();
    $url = sprintf("https://api.multiparcels.com/v1/%s", $endpoint);
    curl_setopt($ch, CURLOPT_URL, $url);

    if ($method == 'POST') {
        $headers[] = 'Content-Type: application/json';

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $output = curl_exec($ch);

    if ($output === false) {
        throw new Exception(curl_error($ch));
    }

    $response = json_decode($output, true);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode == 400) {
        throw new Exception(sprintf("Bad request. Error: %s", $response['message']));
    }

    if ($httpCode == 401) {
        throw new Exception("Authentication failed. Wrong API KEY.");
    }

    if ($httpCode == 422) {
        throw new Exception(sprintf("Validation errors: %s", json_encode($response['errors'])));
    }

    curl_close($ch);

    return $response;
}

$shipmentData = [
    'identifier' => 'test-1',
    'sender'     => [
        'name'         => 'Name LastName',
        'street'       => 'Medvėgalio gatvė 10-1',
        'city'         => 'Kaunas',
        'postal_code'  => '44444',
        'country_code' => 'LT',
        'phone_number' => '+37061234567',
        'email'        => 'name@kaunas.lt',
    ],
    'receiver'   => [
        'name'         => 'Name2 LastName2',
        'street'       => 'A. Voldemaro gatvė',
        'house'        => '5',
        'apartment'    => '1',
        'city'         => 'Vilnius',
        'postal_code'  => '11111',
        'country_code' => 'LT',
        'phone_number' => '+37061234567',
        'email'        => 'name@vilnius.lt',
    ],
    'pickup'     => [
        'type'          => 'hands',
        'packages'      => 1,
        'package_sizes' => [
            'small',
        ],
        'weight'        => 1,
    ],
    'delivery'   => [
        'type'    => 'hands',
        'courier' => 'lp_express',
    ],
];

// 1. Create the shipment as a draft
$shipmentCreateResponse = request('shipments', 'POST', $shipmentData);
$shipment               = $shipmentCreateResponse['data'];


// 2. Confirm the shipment
$confirmUrl      = sprintf('shipments/%s/confirm', $shipment['id']);
$confirmResponse = request($confirmUrl, 'POST');
$trackingCodes   = $confirmResponse['tracking_codes'];


// 3. Download and save labels
$downloadUrl            = sprintf('shipments/%s/labels', $shipment['id']);
$downloadLabelsResponse = request($downloadUrl, 'GET');
file_put_contents('labels.pdf', base64_decode($downloadLabelsResponse['content']));


// 4. Create a manifest (and call the courier for pickup if required)
$addShipmentsToManifest = [
    'shipments' => [
        $shipment['id'],
    ],
];

$manifestCreateResponse = request('manifests', 'POST', $addShipmentsToManifest);
$manifest               = $manifestCreateResponse['data'];


// 5. Download and save manifest
$downloadManifestUrl      = sprintf('manifests/%s/download', $manifest['id']);
$downloadManifestResponse = request($downloadManifestUrl, 'GET');
file_put_contents('manifest.pdf', base64_decode($downloadManifestResponse['content']));



// Wait for the courier to pickup the shipment :)



echo sprintf("Shipment ID: %s \n", $shipment['id']);
echo sprintf("Tracking codes: %s \n", implode(', ', $trackingCodes));
echo sprintf("Manifest ID: %s \n", $manifest['id']);
