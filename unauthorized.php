<?php
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>Access Denied</h1>
                <p>You do not have permission to access this page</p>
            </div>

            <div class="alert alert-error">
                <strong>Error 403:</strong> Access denied. Please contact the administrator for appropriate access.
            </div>

            <div class="form-group">
                <a href="dashboard.php" class="btn btn-primary btn-full">Back to Dashboard</a>
            </div>

            <div class="form-group">
                <a href="logout.php" class="btn btn-secondary btn-full">Logout</a>
            </div>
        </div>
    </div>
</body>
</html>
