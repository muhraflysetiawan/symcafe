<?php
if (!isset($page_title)) {
    $page_title = APP_NAME;
}

// Development mode: Prevent caching (set to false in production)
if (!defined('DEV_MODE')) {
    define('DEV_MODE', true);
}

// Prevent caching in development mode (commented out for better performance)
// Uncomment only when actively debugging
// if (defined('DEV_MODE') && DEV_MODE && !headers_sent()) {
//     header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
//     header('Pragma: no-cache');
//     header('Expires: 0');
// }

// Function to adjust color brightness
function adjustBrightness($hex, $percent) {
    // Remove # if present
    $hex = ltrim($hex, '#');
    
    // Convert to RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Adjust brightness
    $r = max(0, min(255, $r + ($r * $percent / 100)));
    $g = max(0, min(255, $g + ($g * $percent / 100)));
    $b = max(0, min(255, $b + ($b * $percent / 100)));
    
    // Convert back to hex
    return '#' . str_pad(dechex(round($r)), 2, '0', STR_PAD_LEFT) . 
                 str_pad(dechex(round($g)), 2, '0', STR_PAD_LEFT) . 
                 str_pad(dechex(round($b)), 2, '0', STR_PAD_LEFT);
}

// Load theme settings - optimized: cache in session to avoid repeated queries
$theme_colors = [
    'primary' => '#FFFFFF',
    'secondary' => '#252525',
    'accent' => '#3A3A3A'
];

if (isLoggedIn() && function_exists('getCafeId')) {
    // Use session cache to avoid repeated database queries
    if (!isset($_SESSION['theme_colors'])) {
        try {
            $cafe_id = getCafeId();
            if ($cafe_id) {
                $db = new Database();
                $conn = $db->getConnection();
                $stmt = $conn->prepare("SELECT primary_color, secondary_color, accent_color FROM cafe_settings WHERE cafe_id = ?");
                $stmt->execute([$cafe_id]);
                $settings = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($settings) {
                    $_SESSION['theme_colors'] = [
                        'primary' => $settings['primary_color'],
                        'secondary' => $settings['secondary_color'],
                        'accent' => $settings['accent_color']
                    ];
                } else {
                    $_SESSION['theme_colors'] = $theme_colors;
                }
            } else {
                $_SESSION['theme_colors'] = $theme_colors;
            }
        } catch (Exception $e) {
            $_SESSION['theme_colors'] = $theme_colors;
        }
    }
    $theme_colors = $_SESSION['theme_colors'];
}

// Cache-busting for CSS: Use cached version to avoid filesystem calls
if (!isset($_SESSION['css_version'])) {
    $css_file = __DIR__ . '/../assets/css/style.css';
    $_SESSION['css_version'] = file_exists($css_file) ? filemtime($css_file) : time();
}
$css_version = $_SESSION['css_version'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . APP_NAME; ?></title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo $css_version; ?>">
    <style>
        :root {
            --primary-black: <?php echo $theme_colors['secondary']; ?>;
            --primary-white: <?php echo $theme_colors['primary']; ?>;
            --accent-gray: <?php echo $theme_colors['accent']; ?>;
            --sidebar-gradient-start: <?php echo $theme_colors['secondary']; ?>;
            --sidebar-gradient-end: <?php echo $theme_colors['accent']; ?>;
            --header-gradient-start: <?php echo $theme_colors['secondary']; ?>;
            --header-gradient-end: <?php echo $theme_colors['accent']; ?>;
            
            /* Card colors - derived from theme colors with variations */
            --card-primary-start: <?php echo adjustBrightness($theme_colors['accent'], -20); ?>;
            --card-primary-end: <?php echo adjustBrightness($theme_colors['accent'], -40); ?>;
            --card-success-start: <?php echo adjustBrightness($theme_colors['accent'], -15); ?>;
            --card-success-end: <?php echo adjustBrightness($theme_colors['accent'], -35); ?>;
            --card-warning-start: <?php echo adjustBrightness($theme_colors['accent'], -10); ?>;
            --card-warning-end: <?php echo adjustBrightness($theme_colors['accent'], -30); ?>;
            --card-danger-start: <?php echo adjustBrightness($theme_colors['accent'], -25); ?>;
            --card-danger-end: <?php echo adjustBrightness($theme_colors['accent'], -45); ?>;
            --card-info-start: <?php echo adjustBrightness($theme_colors['accent'], -18); ?>;
            --card-info-end: <?php echo adjustBrightness($theme_colors['accent'], -38); ?>;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <div class="main-content">
            <?php include __DIR__ . '/topnav.php'; ?>
            <div class="content">

