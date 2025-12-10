<?php
require_once 'config/config.php';

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

// Get theme colors - try to get from first cafe, or use defaults
$theme_colors = [
    'primary' => '#FFFFFF',
    'secondary' => '#252525',
    'accent' => '#3A3A3A'
];

try {
    $cafe_id = null;
    $stmt = $conn->prepare("SELECT cafe_id FROM cafes ORDER BY cafe_id LIMIT 1");
    $stmt->execute();
    $first_cafe = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($first_cafe) {
        $cafe_id = $first_cafe['cafe_id'];
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

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT user_id, username, password, role, name, email FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            
            // Get café ID (only for owners and cashiers)
            if ($user['role'] != 'customer') {
                $cafe_id = getCafeId();
                if ($cafe_id) {
                    $_SESSION['cafe_id'] = $cafe_id;
                }
            }
            // All users go to index after login
            header('Location: index.php');
            exit();
        } else {
            $error = 'Invalid username or password';
        }
    }
}

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* NO ANIMATIONS - All animations removed */

        /* Update background gradient to match index */
        .login-page {
            position: relative;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 25%, #334155 50%, #475569 75%, #64748b 100%);
            background-attachment: fixed;
        }

        .login-page::before {
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

        /* Minimized button styles */
        .btn-primary {
            padding: 10px 20px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.5);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .illustration-btn {
            padding: 8px 16px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.25) 0%, rgba(255, 255, 255, 0.15) 100%);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .illustration-btn:hover {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.35) 0%, rgba(255, 255, 255, 0.25) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.6);
        }

        .home-btn {
            padding: 8px 12px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.25) 0%, rgba(255, 255, 255, 0.15) 100%);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .home-btn:hover {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.35) 0%, rgba(255, 255, 255, 0.25) 100%);
            transform: translateY(-2px) scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.6);
        }

        .home-btn i {
            font-size: 18px;
        }

        /* Update login box to match index glass style */
        .login-box {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 2px solid rgba(99, 102, 241, 0.2);
        }

        /* Update input styles */
        .login-form input {
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 2px solid rgba(99, 102, 241, 0.2);
            color: white;
        }

        .login-form input:focus {
            background: rgba(15, 23, 42, 0.7);
            border-color: rgba(99, 102, 241, 0.5);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .login-form input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .login-form label {
            color: rgba(255, 255, 255, 0.95);
        }

        /* Update header text */
        .login-header h1 {
            color: rgba(255, 255, 255, 0.95);
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .login-header p {
            color: rgba(255, 255, 255, 0.8);
        }

        /* Update alert styles */
        .alert {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
        }

        .alert-error {
            border-left: 4px solid #ff6b6b;
            color: #ff6b6b;
        }

        .alert-success {
            border-left: 4px solid #51cf66;
            color: #51cf66;
        }

        /* Illustration Carousel Styles */
        .illustration-carousel {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
            border-radius: var(--radius-xl);
        }

        .carousel-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1s ease-in-out;
        }

        .carousel-slide.active {
            opacity: 1;
            z-index: 1;
        }

        .carousel-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .carousel-text-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10;
            text-align: center;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            padding: 12px 20px;
        }

        .carousel-text {
            color: white;
            font-size: 16px;
            font-weight: 600;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
            white-space: nowrap;
        }

        .illustration-overlay {
            position: absolute;
            bottom: 20px;
            right: 20px;
            z-index: 10;
            display: flex;
            gap: 10px;
            align-items: center;
        }
    </style>
    <script>
        // NO ANIMATIONS - All animation code removed
        document.addEventListener('DOMContentLoaded', function() {
            // Disable button on form submission to prevent double submission
            const loginForm = document.querySelector('.login-form');
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    const submitButton = this.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.style.opacity = '0.6';
                    }
                });
            }

            // Image carousel - change every 3 seconds
            const carousel = document.querySelector('.illustration-carousel');
            if (carousel) {
                const slides = carousel.querySelectorAll('.carousel-slide');
                const textOverlay = carousel.querySelector('.carousel-text-overlay .carousel-text');
                let currentIndex = 0;

                function showSlide(index) {
                    slides.forEach((slide, i) => {
                        slide.classList.remove('active');
                        if (i === index) {
                            slide.classList.add('active');
                            // Update text overlay
                            const text = slide.getAttribute('data-text');
                            if (textOverlay) {
                                textOverlay.textContent = text;
                            }
                        }
                    });
                }

                function nextSlide() {
                    currentIndex = (currentIndex + 1) % slides.length;
                    showSlide(currentIndex);
                }

                // Start carousel
                if (slides.length > 0) {
                    setInterval(nextSlide, 3000);
                }
            }
        });
    </script>
</head>
<body>
    <div class="login-page">
        <div class="auth-container auth-container-side">
            <div class="auth-form-section">
                <div class="login-box">
                    <div class="login-header">
                        <h1><?php echo APP_NAME; ?></h1>
                        <p>Point of Sale System</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" class="login-form">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required autofocus>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-full">Login</button>
                    </form>
                </div>
            </div>
            
            <div class="auth-illustration-section">
                <div class="illustration-wrapper">
                    <div class="illustration-carousel">
                        <div class="carousel-slide active" data-text='"Welcome to <?php echo APP_NAME; ?>"'>
                            <img src="assets/illustration/1764677692974.jpg" alt="Login Illustration">
                        </div>
                        <div class="carousel-slide" data-text='"Experience Premium Coffee"'>
                            <img src="assets/illustration/1764678180164.jpg" alt="Login Illustration">
                        </div>
                        <div class="carousel-slide" data-text='"Order Anytime, Anywhere"'>
                            <img src="assets/illustration/1764678220435.jpg" alt="Login Illustration">
                        </div>
                        <div class="carousel-slide" data-text='"Fast & Easy Ordering"'>
                            <img src="assets/illustration/1764678487332.jpg" alt="Login Illustration">
                        </div>
                        <div class="carousel-slide" data-text='"Quality You Can Trust"'>
                            <img src="assets/illustration/1764678492905.jpg" alt="Login Illustration">
                        </div>
                        <div class="carousel-slide" data-text='"Join Our Community"'>
                            <img src="assets/illustration/1764678565634.jpg" alt="Login Illustration">
                        </div>
                        <div class="carousel-slide" data-text='"Start Your Journey Today"'>
                            <img src="assets/illustration/1764678600957.jpg" alt="Login Illustration">
                        </div>
                            <div class="carousel-text-overlay">
                                <span class="carousel-text">"Welcome to <?php echo APP_NAME; ?>"</span>
                            </div>
                    </div>
                    <div class="illustration-overlay">
                        <a href="index.php" class="home-btn" title="Home">
                            <i class="fas fa-home"></i>
                        </a>
                        <a href="register.php" class="btn btn-secondary illustration-btn">Register</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
