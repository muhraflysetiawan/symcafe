<?php
require_once 'config/config.php';
requireLogin();
requireCafeSetup();
requireRole(['owner']);

$db = new Database();
$conn = $db->getConnection();
$cafe_id = getCafeId();

$error = '';
$success = '';

// Check for success message from redirect
if (isset($_SESSION['theme_success'])) {
    $success = $_SESSION['theme_success'];
    unset($_SESSION['theme_success']);
}


// Get current settings
$stmt = $conn->prepare("SELECT * FROM cafe_settings WHERE cafe_id = ?");
$stmt->execute([$cafe_id]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// If no settings exist, create default (matching index/home colors)
if (!$settings) {
    $stmt = $conn->prepare("INSERT INTO cafe_settings (cafe_id, primary_color, secondary_color, accent_color) VALUES (?, '#FFFFFF', '#0f172a', '#6366f1')");
    $stmt->execute([$cafe_id]);
    $stmt = $conn->prepare("SELECT * FROM cafe_settings WHERE cafe_id = ?");
    $stmt->execute([$cafe_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $primary_color = sanitizeInput($_POST['primary_color'] ?? '#FFFFFF');
    $secondary_color = sanitizeInput($_POST['secondary_color'] ?? '#0f172a');
    $accent_color = sanitizeInput($_POST['accent_color'] ?? '#6366f1');
    
    // Validate hex colors
    if (!preg_match('/^#[a-fA-F0-9]{6}$/', $primary_color) || 
        !preg_match('/^#[a-fA-F0-9]{6}$/', $secondary_color) || 
        !preg_match('/^#[a-fA-F0-9]{6}$/', $accent_color)) {
        $error = 'Invalid color format. Please use hex format (e.g., #FFFFFF)';
    } else {
        $stmt = $conn->prepare("UPDATE cafe_settings SET primary_color = ?, secondary_color = ?, accent_color = ? WHERE cafe_id = ?");
        if ($stmt->execute([$primary_color, $secondary_color, $accent_color, $cafe_id])) {
            // Clear session cache so new theme colors are loaded
            unset($_SESSION['theme_colors']);
            unset($_SESSION['css_version']); // Also clear CSS cache
            
            // Reload settings
            $stmt = $conn->prepare("SELECT * FROM cafe_settings WHERE cafe_id = ?");
            $stmt->execute([$cafe_id]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Redirect to reload page with new theme
            $_SESSION['theme_success'] = 'Theme settings updated successfully!';
            header('Location: theme_settings.php');
            exit();
        } else {
            $error = 'Failed to update theme settings';
        }
    }
}

$page_title = 'Theme Settings';
include 'includes/header.php';
?>

<h2 style="color: var(--primary-white); margin-bottom: 20px;">Theme Settings</h2>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="form-container">
    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label for="primary_color">Primary Color (White/Text)</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="color" id="primary_color" name="primary_color" value="<?php echo htmlspecialchars($settings['primary_color']); ?>" style="width: 80px; height: 40px; border: none; cursor: pointer;">
                    <input type="text" id="primary_color_text" value="<?php echo htmlspecialchars($settings['primary_color']); ?>" style="flex: 1;" readonly>
                </div>
                <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">Main text and light elements color</p>
            </div>
            
            <div class="form-group">
                <label for="secondary_color">Secondary Color (Black/Background)</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="color" id="secondary_color" name="secondary_color" value="<?php echo htmlspecialchars($settings['secondary_color']); ?>" style="width: 80px; height: 40px; border: none; cursor: pointer;">
                    <input type="text" id="secondary_color_text" value="<?php echo htmlspecialchars($settings['secondary_color']); ?>" style="flex: 1;" readonly>
                </div>
                <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">Main background color</p>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="accent_color">Accent Color (Gray/Cards)</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="color" id="accent_color" name="accent_color" value="<?php echo htmlspecialchars($settings['accent_color']); ?>" style="width: 80px; height: 40px; border: none; cursor: pointer;">
                    <input type="text" id="accent_color_text" value="<?php echo htmlspecialchars($settings['accent_color']); ?>" style="flex: 1;" readonly>
                </div>
                <p style="color: var(--text-gray); font-size: 12px; margin-top: 5px;">Card backgrounds and secondary elements</p>
            </div>
        </div>
        
        <div id="preview-container" style="background: var(--accent-gray); padding: 20px; border-radius: 5px; margin-top: 20px; border: 1px solid var(--border-gray);">
            <h3 style="color: var(--primary-white); margin-bottom: 15px;">Preview</h3>
            <div id="preview-background" style="padding: 20px; border-radius: 5px; border: 1px solid;">
                <div id="preview-card" style="padding: 15px; border-radius: 5px; margin-bottom: 10px;">
                    <p id="preview-text" style="margin: 0;">This is how your theme will look</p>
                </div>
                <button type="button" id="preview-button" style="border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: 500;">Sample Button</button>
            </div>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" class="btn btn-primary">Save Theme Settings</button>
            <button type="button" class="btn btn-secondary" onclick="resetColors()">Reset to Default</button>
            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
// Sync color picker with text input
document.getElementById('primary_color').addEventListener('input', function() {
    document.getElementById('primary_color_text').value = this.value;
    updatePreview();
});

document.getElementById('secondary_color').addEventListener('input', function() {
    document.getElementById('secondary_color_text').value = this.value;
    updatePreview();
});

document.getElementById('accent_color').addEventListener('input', function() {
    document.getElementById('accent_color_text').value = this.value;
    updatePreview();
});


function updatePreview() {
    const primary = document.getElementById('primary_color').value;
    const secondary = document.getElementById('secondary_color').value;
    const accent = document.getElementById('accent_color').value;
    
    // Update preview background (main background)
    const previewBackground = document.getElementById('preview-background');
    if (previewBackground) {
        previewBackground.style.background = secondary;
        previewBackground.style.borderColor = accent;
    }
    
    // Update preview card (accent color)
    const previewCard = document.getElementById('preview-card');
    if (previewCard) {
        previewCard.style.background = accent;
    }
    
    // Update preview text (primary color)
    const previewText = document.getElementById('preview-text');
    if (previewText) {
        previewText.style.color = primary;
    }
    
    // Update preview button
    const previewButton = document.getElementById('preview-button');
    if (previewButton) {
        previewButton.style.background = primary;
        previewButton.style.color = secondary;
    }
    
    // Update preview container border
    const previewContainer = document.getElementById('preview-container');
    if (previewContainer) {
        previewContainer.style.borderColor = accent;
    }
}

function resetColors() {
    if (confirm('Reset to default index/home theme colors?')) {
        document.getElementById('primary_color').value = '#FFFFFF';
        document.getElementById('primary_color_text').value = '#FFFFFF';
        document.getElementById('secondary_color').value = '#0f172a';
        document.getElementById('secondary_color_text').value = '#0f172a';
        document.getElementById('accent_color').value = '#6366f1';
        document.getElementById('accent_color_text').value = '#6366f1';
        updatePreview();
    }
}

// Initialize preview
updatePreview();
</script>

<?php include 'includes/footer.php'; ?>

