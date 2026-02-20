<?php
require_once 'lib/GoogleAuthenticator.php';

$g = new GoogleAuthenticator();
$secret = 'JBSWY3DPEHPK3PXP'; // Example secret
$name = 'TouristGuideSystem';
$title = 'TouristGuideSystem';

$url = $g->getQRCodeGoogleUrl($name, $secret, $title);
echo "Generated URL: " . $url . "\n";

// Decode the data param to see what's in the QR code
parse_str(parse_url($url, PHP_URL_QUERY), $params);
echo "QR Content: " . $params['data'] . "\n";
?>
