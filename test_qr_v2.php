<?php
require_once 'lib/GoogleAuthenticator.php';

$g = new GoogleAuthenticator();
$secret = 'JBSWY3DPEHPK3PXP';
$username = 'john.doe';
$issuer = 'TouristGuideSystem';
$label = $issuer . ':' . $username;

$url = $g->getQRCodeGoogleUrl($label, $secret, $issuer);
echo "Generated URL: " . $url . "\n";

parse_str(parse_url($url, PHP_URL_QUERY), $params);
echo "QR Content: " . $params['data'] . "\n";
?>
