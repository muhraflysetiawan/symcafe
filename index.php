<?php
require_once 'config/config.php';
require_once 'includes/tutorial.php';

// Function to adjust color brightness
function adjustBrightness($hex, $percent) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $r = max(0, min(255, $r + ($r * $percent / 100)));
    $g = max(0, min(255, $g + ($g * $percent / 100)));
    $b = max(0, min(255, $b + ($b * $percent / 100)));
    return '#' . str_pad(dechex(round($r)), 2, '0', STR_PAD_LEFT) . 
                 str_pad(dechex(round($g)), 2, '0', STR_PAD_LEFT) . 
                 str_pad(dechex(round($b)), 2, '0', STR_PAD_LEFT);
}

// Function to convert hex to rgba
function hexToRgba($hex, $alpha = 1) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "rgba($r, $g, $b, $alpha)";
}

$db = new Database();
$conn = $db->getConnection();
$is_customer = isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'customer';
$user_role = isLoggedIn() && isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;

// Get theme colors - try to get from logged-in user's cafe, or use first cafe, or defaults
$theme_colors = [
    'primary' => '#FFFFFF',
    'secondary' => '#252525',
    'accent' => '#3A3A3A'
];

try {
    $cafe_id = null;
    if (isLoggedIn() && function_exists('getCafeId')) {
        $cafe_id = getCafeId();
    }
    
    // If no cafe_id from logged in user, get first cafe's theme
    if (!$cafe_id) {
        $stmt = $conn->prepare("SELECT cafe_id FROM cafes ORDER BY cafe_id LIMIT 1");
        $stmt->execute();
        $first_cafe = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($first_cafe) {
            $cafe_id = $first_cafe['cafe_id'];
        }
    }
    
    if ($cafe_id) {
        $stmt = $conn->prepare("SELECT primary_color, secondary_color, accent_color FROM cafe_settings WHERE cafe_id = ?");
        $stmt->execute([$cafe_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($settings) {
            $theme_colors = [
                'primary' => $settings['primary_color'],
                'secondary' => $settings['secondary_color'],
                'accent' => $settings['accent_color']
            ];
        }
    }
} catch (Exception $e) {
    // Use defaults if error
}

// Create gradient colors from theme with opacity
$gradient_start = hexToRgba($theme_colors['secondary'], 0.85);
$gradient_mid1 = hexToRgba(adjustBrightness($theme_colors['accent'], 10), 0.8);
$gradient_mid2 = hexToRgba(adjustBrightness($theme_colors['accent'], 20), 0.75);
$gradient_mid3 = hexToRgba(adjustBrightness($theme_colors['accent'], 30), 0.7);
$gradient_end = hexToRgba(adjustBrightness($theme_colors['primary'], -20), 0.65);

// Get tutorial steps for logged-in users
$tutorial_steps = [];
if ($user_role && in_array($user_role, ['owner', 'cashier'])) {
    $tutorial_steps = getTutorialSteps($user_role);
}

// Handle contact form submission
$contact_error = '';
$contact_success = '';
$contact_name = '';
$contact_email = '';
$contact_subject = '';
$contact_message = '';
$web_owner_email = 'admin@symcafe.com'; // Change this to your actual web owner email

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['contact_submit'])) {
    $contact_name = sanitizeInput($_POST['contact_name'] ?? '');
    $contact_email = sanitizeInput($_POST['contact_email'] ?? '');
    $contact_subject = sanitizeInput($_POST['contact_subject'] ?? '');
    $contact_message = sanitizeInput($_POST['contact_message'] ?? '');
    
    if (empty($contact_name) || empty($contact_email) || empty($contact_subject) || empty($contact_message)) {
        $contact_error = 'Please fill in all fields';
    } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $contact_error = 'Please enter a valid email address';
    } else {
        // Prepare email
        $email_subject = "Contact Form: " . $contact_subject;
        $email_body = "You have received a new message from the SYMCAFE contact form.\n\n";
        $email_body .= "Name: " . $contact_name . "\n";
        $email_body .= "Email: " . $contact_email . "\n";
        $email_body .= "Subject: " . $contact_subject . "\n\n";
        $email_body .= "Message:\n" . $contact_message . "\n";
        
        $email_headers = "From: " . $contact_email . "\r\n";
        $email_headers .= "Reply-To: " . $contact_email . "\r\n";
        $email_headers .= "X-Mailer: PHP/" . phpversion();
        
        // Send email
        if (mail($web_owner_email, $email_subject, $email_body, $email_headers)) {
            $contact_success = 'Thank you! Your message has been sent successfully.';
            // Clear form fields
            $contact_name = $contact_email = $contact_subject = $contact_message = '';
        } else {
            $contact_error = 'Sorry, there was an error sending your message. Please try again later.';
        }
    }
}

$page_title = 'Home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Brewed to Elevate Your Day</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            overflow-x: hidden;
        }

        /* Background with Image and Gradient Overlay */
        .landing-page {
            min-height: 100vh;
            position: relative;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 25%, #334155 50%, #475569 75%, #64748b 100%);
            background-attachment: fixed;
        }

        .landing-page::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 40% 20%, rgba(236, 72, 153, 0.1) 0%, transparent 50%);
            z-index: 0;
            pointer-events: none;
            animation: gradientShift 15s ease infinite;
        }

        @keyframes gradientShift {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.8;
            }
        }

        /* Dashboard-style Animated Header */
        .landing-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            padding: 16px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            border-bottom: 2px solid rgba(99, 102, 241, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            animation: slideDown 0.6s ease-out;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .landing-nav.scrolled {
            padding: 12px 40px;
            background: rgba(15, 23, 42, 0.95);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
        }

        .landing-nav h1 {
            color: white;
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            position: relative;
        }

        .landing-nav h1::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 3px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            transition: width 0.5s ease;
            border-radius: 2px;
        }

        .landing-nav h1:hover::after {
            width: 100%;
        }

        .landing-nav h1 .logo-img {
            height: 60px;
            width: auto;
            object-fit: contain;
            filter: drop-shadow(0 2px 10px rgba(0, 0, 0, 0.2));
        }

        .landing-nav-links {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .landing-nav-links a, .learn-btn {
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            font-size: 15px;
            border: 2px solid rgba(99, 102, 241, 0.3);
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(139, 92, 246, 0.2) 100%);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }

        .landing-nav-links a::before, .learn-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .landing-nav-links a:hover::before, .learn-btn:hover::before {
            left: 100%;
        }

        .landing-nav-links a:hover, .learn-btn:hover {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.4) 0%, rgba(139, 92, 246, 0.4) 100%);
            border-color: rgba(99, 102, 241, 0.6);
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
        }

        .learn-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.4);
        }

        /* Hero Section */
        .hero-section {
            position: relative;
            z-index: 1;
            padding: 180px 40px 100px;
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .hero-content {
            text-align: left;
            margin-bottom: 0;
        }

        .hero-content h1 {
            font-size: 64px;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff 0%, #e0e7ff 50%, #c7d2fe 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 24px;
            line-height: 1.2;
            letter-spacing: -2px;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            animation: fadeInUp 0.8s ease-out;
        }

        .hero-illustration {
            position: relative;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            animation: fadeInRight 1s ease-out;
        }

        .hero-illustration img {
            width: 100%;
            height: 500px;
            object-fit: cover;
            display: block;
        }

        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Typing Animation */
        .typing-container {
            min-height: 80px;
            margin-bottom: 40px;
        }

        .typing-text {
            font-size: 24px;
            color: rgba(255, 255, 255, 0.95);
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            min-height: 70px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .typing-text .typing-cursor {
            display: inline-block;
            width: 3px;
            height: 28px;
            background: white;
            margin-left: 4px;
            animation: blink 1s infinite;
            vertical-align: middle;
        }

        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0; }
        }

        .typing-text.typing-complete .typing-cursor {
            animation: none;
            opacity: 0;
        }

        .hero-cta {
            display: flex;
            gap: 16px;
            justify-content: flex-start;
            flex-wrap: wrap;
            margin-top: 40px;
        }

        .btn-explore {
            padding: 18px 40px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            backdrop-filter: blur(10px);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
            position: relative;
            overflow: hidden;
        }

        .btn-explore::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-explore:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-explore:hover {
            background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 32px rgba(99, 102, 241, 0.6);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .btn-explore span {
            position: relative;
            z-index: 1;
        }

        /* Features Section */
        .features-section {
            position: relative;
            z-index: 1;
            padding: 100px 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .features-section h2 {
            text-align: center;
            font-size: 48px;
            font-weight: 700;
            color: white;
            margin-bottom: 16px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .features-section > p {
            text-align: center;
            font-size: 20px;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 60px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.2);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            margin-top: 60px;
        }

        .feature-card .feature-icon {
            position: relative;
            z-index: 1;
        }

        .feature-card h3, .feature-card p {
            position: relative;
            z-index: 1;
        }

        .feature-card {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px 32px;
            text-align: center;
            border: 2px solid rgba(99, 102, 241, 0.2);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .feature-card:hover::before {
            opacity: 1;
        }

        .feature-card:hover {
            transform: translateY(-10px) scale(1.05);
            box-shadow: 0 20px 50px rgba(99, 102, 241, 0.4);
            border-color: rgba(99, 102, 241, 0.5);
            background: rgba(30, 41, 59, 0.8);
        }

        .feature-icon {
            font-size: 56px;
            color: white;
            margin-bottom: 20px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .feature-card h3 {
            font-size: 24px;
            font-weight: 700;
            color: white;
            margin-bottom: 16px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .feature-card p {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.7;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.2);
        }

        /* About Section */
        .about-section {
            position: relative;
            z-index: 1;
            padding: 100px 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .about-section h2 {
            text-align: center;
            font-size: 48px;
            font-weight: 700;
            color: white;
            margin-bottom: 40px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .about-content {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 50px;
            border: 2px solid rgba(99, 102, 241, 0.2);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }

        .about-illustration {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
        }

        .about-illustration img {
            width: 100%;
            height: 350px;
            object-fit: cover;
            display: block;
        }

        .about-content p {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.95);
            line-height: 1.8;
            margin-bottom: 20px;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.2);
        }

        .about-content p:last-child {
            margin-bottom: 0;
        }

        /* Contact Section */
        .contact-section {
            position: relative;
            z-index: 1;
            padding: 100px 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .contact-section h2 {
            text-align: center;
            font-size: 48px;
            font-weight: 700;
            color: white;
            margin-bottom: 16px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .contact-section > p {
            text-align: center;
            font-size: 20px;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 60px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.2);
        }

        .contact-form-container {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 50px;
            border: 2px solid rgba(99, 102, 241, 0.2);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            max-width: 700px;
            margin: 0 auto;
        }

        .contact-form-group {
            margin-bottom: 24px;
        }

        .contact-form-group label {
            display: block;
            color: white;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.2);
        }

        .contact-form-group input,
        .contact-form-group textarea {
            width: 100%;
            padding: 14px 18px;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(99, 102, 241, 0.2);
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .contact-form-group input::placeholder,
        .contact-form-group textarea::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .contact-form-group input:focus,
        .contact-form-group textarea:focus {
            outline: none;
            background: rgba(15, 23, 42, 0.7);
            border-color: rgba(99, 102, 241, 0.5);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .contact-form-group textarea {
            min-height: 150px;
            resize: vertical;
        }

        .contact-submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            backdrop-filter: blur(10px);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
        }

        .contact-submit-btn:hover {
            background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 12px 32px rgba(99, 102, 241, 0.6);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .contact-alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            text-align: center;
            font-weight: 500;
        }

        .contact-alert.error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fee2e2;
        }

        .contact-alert.success {
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid rgba(34, 197, 94, 0.4);
            color: #dcfce7;
        }

        /* Footer */
        .footer-landing {
            position: relative;
            z-index: 1;
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(20px);
            padding: 40px;
            text-align: center;
            color: rgba(255, 255, 255, 0.9);
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            margin-top: 60px;
        }

        /* Responsive */
        @media (max-width: 968px) {
            .hero-section {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .hero-content {
                text-align: center;
            }

            .hero-cta {
                justify-content: center;
            }

            .about-content {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .hero-content h1 {
                font-size: 42px;
            }

            .typing-text {
                font-size: 18px;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .about-content {
                padding: 30px 20px;
            }

            .about-content p {
                font-size: 16px;
            }

            .contact-form-container {
                padding: 30px 20px;
            }

            .contact-section h2,
            .about-section h2 {
                font-size: 36px;
            }

            .landing-nav {
                padding: 12px 20px;
                flex-direction: column;
                gap: 12px;
            }

            .landing-nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body class="landing-page">
    <!-- Glass Navigation Bar -->
    <nav class="landing-nav">
        <h1>
            <img src="<?php echo BASE_URL; ?>assets/logo.png" alt="Symcafe Logo" class="logo-img">
            <?php echo APP_NAME; ?>
        </h1>
        <div class="landing-nav-links">
            <?php if (isLoggedIn()): ?>
                <?php if ($user_role == 'customer'): ?>
                    <a href="index.php">Home</a>
                    <a href="customer_orders.php">My Orders</a>
                <?php else: ?>
                    <a href="dashboard.php">Dashboard</a>
                    <?php if (!empty($tutorial_steps)): ?>
                        <button class="learn-btn" onclick="openTutorial()">
                            <i class="fas fa-graduation-cap"></i> Learn
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1>Brewed to Elevate Your Day</h1>
            <div class="typing-container">
                <div class="typing-text" id="typingText">
                    <span id="typingContent"></span>
                    <span class="typing-cursor"></span>
                </div>
            </div>
            <div class="hero-cta">
                <a href="explore_cafes.php" class="btn-explore">
                    <span><i class="fas fa-compass"></i> Explore Cafés</span>
                </a>
            </div>
        </div>
        <div class="hero-illustration">
            <img src="<?php echo BASE_URL; ?>assets/illustration/1764677692974.jpg" alt="Cafe Experience">
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <h2>Why Choose <?php echo APP_NAME; ?>?</h2>
        <p>Experience the perfect blend of innovation and simplicity</p>
        <div class="features-grid">
            <div class="feature-card">
                <i class="fas fa-coins feature-icon"></i>
                <h3>Easy Sales Management</h3>
                <p>Process transactions quickly with our intuitive POS interface. Support for multiple payment methods and voucher systems.</p>
            </div>
            
            <div class="feature-card">
                <i class="fas fa-chart-line feature-icon"></i>
                <h3>Real-time Reports</h3>
                <p>Track your sales performance with detailed monthly reports, including graphs and analytics to help you make informed decisions.</p>
            </div>
            
            <div class="feature-card">
                <i class="fas fa-boxes feature-icon"></i>
                <h3>Inventory Control</h3>
                <p>Manage your product stock levels automatically. Get alerts when items are running low or out of stock.</p>
            </div>
            
            <div class="feature-card">
                <i class="fas fa-users feature-icon"></i>
                <h3>Multi-user Support</h3>
                <p>Add cashiers and manage staff access. Each user has their own account with appropriate permissions.</p>
            </div>
            
            <div class="feature-card">
                <i class="fas fa-ticket-alt feature-icon"></i>
                <h3>Voucher System</h3>
                <p>Create custom discount vouchers with flexible conditions to attract and retain customers.</p>
            </div>
            
            <div class="feature-card">
                <i class="fas fa-palette feature-icon"></i>
                <h3>Customizable</h3>
                <p>Personalize your café's branding with custom logos and theme colors to match your business identity.</p>
            </div>
        </div>
    </section>

    <!-- Tutorial Modal -->
    <?php if (!empty($tutorial_steps)): ?>
        <div class="tutorial-modal" id="tutorialModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.9); backdrop-filter: blur(10px); z-index: 10000; overflow-y: auto;">
            <div style="background: rgba(30, 41, 59, 0.95); backdrop-filter: blur(20px); border-radius: 30px; padding: 40px; max-width: 900px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5); border: 2px solid rgba(99, 102, 241, 0.3); margin: 40px auto;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid rgba(99, 102, 241, 0.3);">
                    <h2 style="color: white; font-size: 32px; font-weight: 700; margin: 0; background: linear-gradient(135deg, #ffffff 0%, #e0e7ff 50%, #c7d2fe 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"><i class="fas fa-graduation-cap"></i> Feature Tutorial</h2>
                    <button onclick="closeTutorial()" style="background: rgba(99, 102, 241, 0.2); border: 2px solid rgba(99, 102, 241, 0.3); color: white; font-size: 32px; cursor: pointer; padding: 0; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.3s;">&times;</button>
                </div>
                
                <?php foreach ($tutorial_steps as $category): ?>
                    <div style="margin-bottom: 40px;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                            <span style="font-size: 32px;"><?php echo $category['icon']; ?></span>
                            <h3 style="color: #1F2937; font-size: 24px; font-weight: 600; margin: 0;"><?php echo htmlspecialchars($category['title']); ?></h3>
                        </div>
                        <p style="color: rgba(255, 255, 255, 0.9); margin-bottom: 24px; font-size: 16px;"><?php echo htmlspecialchars($category['description']); ?></p>
                        <div style="display: grid; gap: 16px;">
                            <?php foreach ($category['steps'] as $step): ?>
                                <div style="background: rgba(15, 23, 42, 0.5); padding: 20px; border-radius: 20px; border-left: 4px solid #6366f1; border: 2px solid rgba(99, 102, 241, 0.2);">
                                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                        <span style="font-size: 24px;"><?php echo htmlspecialchars($step['icon']); ?></span>
                                        <h4 style="color: white; font-size: 18px; font-weight: 600; margin: 0;"><?php echo htmlspecialchars($step['title']); ?></h4>
                                    </div>
                                    <p style="color: rgba(255, 255, 255, 0.8); font-size: 14px; line-height: 1.6; margin: 0 0 8px 0;"><?php echo htmlspecialchars($step['description']); ?></p>
                                    <?php if (isset($step['link'])): ?>
                                        <a href="<?php echo htmlspecialchars($step['link']); ?>" style="display: inline-block; color: #a855f7; text-decoration: none; font-size: 14px; font-weight: 500; margin-top: 8px; transition: all 0.3s;" target="_blank" onmouseover="this.style.color='#c7d2fe'" onmouseout="this.style.color='#a855f7'">
                                            Open Feature <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- About Section -->
    <section class="about-section">
        <h2>About <?php echo APP_NAME; ?></h2>
        <div class="about-content">
            <div>
                <p>
                    <?php echo APP_NAME; ?> is a comprehensive café management system designed to help café owners streamline their operations, manage their inventory, and grow their business. We combine modern technology with user-friendly design to create the perfect solution for café management.
                </p>
                <p>
                    Our platform offers a complete suite of tools including point-of-sale (POS) systems, inventory management, sales analytics, customer order tracking, and voucher management. Whether you're running a small local café or managing multiple locations, <?php echo APP_NAME; ?> provides the tools you need to succeed.
                </p>
                <p>
                    At <?php echo APP_NAME; ?>, we believe that great coffee deserves great management. That's why we've built a system that's both powerful and easy to use, allowing you to focus on what you do best - creating exceptional experiences for your customers.
                </p>
                <p>
                    Join hundreds of café owners who trust <?php echo APP_NAME; ?> to manage their daily operations, track their sales, and grow their business. Experience the perfect blend of innovation and simplicity.
                </p>
            </div>
            <div class="about-illustration">
                <img src="<?php echo BASE_URL; ?>assets/illustration/1764678180164.jpg" alt="About SYMCAFE">
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section">
        <h2>Contact Us</h2>
        <p>Have questions or feedback? We'd love to hear from you!</p>
        <div class="contact-form-container">
            <?php if ($contact_error): ?>
                <div class="contact-alert error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($contact_error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($contact_success): ?>
                <div class="contact-alert success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($contact_success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="contact-form-group">
                    <label for="contact_name"><i class="fas fa-user"></i> Your Name</label>
                    <input type="text" id="contact_name" name="contact_name" placeholder="Enter your name" value="<?php echo htmlspecialchars($contact_name); ?>" required>
                </div>
                
                <div class="contact-form-group">
                    <label for="contact_email"><i class="fas fa-envelope"></i> Your Email</label>
                    <input type="email" id="contact_email" name="contact_email" placeholder="Enter your email" value="<?php echo htmlspecialchars($contact_email); ?>" required>
                </div>
                
                <div class="contact-form-group">
                    <label for="contact_subject"><i class="fas fa-tag"></i> Subject</label>
                    <input type="text" id="contact_subject" name="contact_subject" placeholder="What is this about?" value="<?php echo htmlspecialchars($contact_subject); ?>" required>
                </div>
                
                <div class="contact-form-group">
                    <label for="contact_message"><i class="fas fa-comment"></i> Message</label>
                    <textarea id="contact_message" name="contact_message" placeholder="Tell us what's on your mind..." required><?php echo htmlspecialchars($contact_message); ?></textarea>
                </div>
                
                <button type="submit" name="contact_submit" class="contact-submit-btn">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer-landing">
        <p style="margin: 0; font-size: 16px;">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        <p style="margin-top: 10px; font-size: 14px;">Brewed with ❤️ for café lovers</p>
    </footer>

    <script>
        function openTutorial() {
            const modal = document.getElementById('tutorialModal');
            if (modal) {
                modal.style.display = 'flex';
                modal.style.alignItems = 'flex-start';
                modal.style.justifyContent = 'center';
                modal.style.padding = '40px 20px';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeTutorial() {
            const modal = document.getElementById('tutorialModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        }

        document.addEventListener('click', function(e) {
            const modal = document.getElementById('tutorialModal');
            if (modal && e.target === modal) {
                closeTutorial();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeTutorial();
            }
        });

        // Typing Animation
        const typingSentences = [
            "Discover exceptional cafés and savor handcrafted beverages that transform every moment into a delightful experience.",
            "Experience the perfect blend of innovation and simplicity in café management.",
            "From bean to cup, we bring you the finest café experience with modern technology.",
            "Join thousands of café owners who trust us to elevate their business every day."
        ];

        let currentSentenceIndex = 0;
        let currentCharIndex = 0;
        let isDeleting = false;
        let typingSpeed = 50;
        let deletingSpeed = 30;
        let pauseTime = 2000;

        function typeText() {
            const typingElement = document.getElementById('typingContent');
            const typingTextElement = document.getElementById('typingText');
            const currentSentence = typingSentences[currentSentenceIndex];

            if (!typingElement) return;

            if (isDeleting) {
                typingElement.textContent = currentSentence.substring(0, currentCharIndex - 1);
                currentCharIndex--;
                typingSpeed = deletingSpeed;
            } else {
                typingElement.textContent = currentSentence.substring(0, currentCharIndex + 1);
                currentCharIndex++;
                typingSpeed = 50;
            }

            if (!isDeleting && currentCharIndex === currentSentence.length) {
                typingTextElement.classList.add('typing-complete');
                setTimeout(() => {
                    isDeleting = true;
                    typingTextElement.classList.remove('typing-complete');
                    typingSpeed = deletingSpeed;
                }, pauseTime);
            } else if (isDeleting && currentCharIndex === 0) {
                isDeleting = false;
                currentSentenceIndex = (currentSentenceIndex + 1) % typingSentences.length;
                typingSpeed = 50;
            }

            setTimeout(typeText, typingSpeed);
        }

        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(typeText, 1000);

            // Navbar scroll animation
            const navbar = document.querySelector('.landing-nav');
            if (navbar) {
                window.addEventListener('scroll', function() {
                    const currentScroll = window.pageYOffset;

                    if (currentScroll > 50) {
                        navbar.classList.add('scrolled');
                    } else {
                        navbar.classList.remove('scrolled');
                    }
                });
            }
        });
    </script>
</body>
</html>
