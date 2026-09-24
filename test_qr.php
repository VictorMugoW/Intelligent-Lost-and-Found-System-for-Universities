<?php
require_once 'libraries/phpqrcode-master/qrlib.php';

$text = 'TEST-QR-CODE-12345';
$output_file = 'qrcodes/test_qr.png';

QRcode::png($text, $output_file, QR_ECLEVEL_L, 10);

echo "QR code generated!<br>";
echo "<img src='$output_file' alt='QR Code'>";
?>