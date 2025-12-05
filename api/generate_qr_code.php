<?php
/**
 * API Endpoint: Generate QR Code Image
 * GET /api/generate_qr_code.php?data=CODE&size=200
 */
header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');

require_once __DIR__ . '/../config/config.php';

$data = isset($_GET['data']) ? $_GET['data'] : '';
$size = isset($_GET['size']) ? (int)$_GET['size'] : 200;

if (empty($data)) {
    // Return a 1x1 transparent PNG if no data
    $img = imagecreate(1, 1);
    imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagepng($img);
    imagedestroy($img);
    exit();
}

// Use a simple QR code generation approach
// Since we don't have a PHP QR library, we'll use a free API service
// Or we can use a simple approach with GD library

// Option 1: Use a free QR code API (most reliable)
$qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($data);

// Get the QR code image with timeout
$context = stream_context_create([
    'http' => [
        'timeout' => 5,
        'ignore_errors' => true
    ]
]);

$qr_image = @file_get_contents($qr_url, false, $context);

if ($qr_image === false || strlen($qr_image) < 100) {
    // Try alternative API
    $qr_url_alt = 'https://chart.googleapis.com/chart?chs=' . $size . 'x' . $size . '&cht=qr&chl=' . urlencode($data);
    $qr_image = @file_get_contents($qr_url_alt, false, $context);
}

if ($qr_image === false || strlen($qr_image) < 100) {
    // Fallback: Create a simple text-based representation
    $img = imagecreate($size, $size);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    imagefilledrectangle($img, 0, 0, $size, $size, $white);
    
    // Add text
    $font_size = 3;
    $text = substr($data, 0, 10);
    imagestring($img, $font_size, 10, $size/2, $text, $black);
    
    imagepng($img);
    imagedestroy($img);
} else {
    // Output the QR code image
    header('Content-Type: image/png');
    echo $qr_image;
}

