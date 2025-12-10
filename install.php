<?php
// Script instalasi database untuk BEANDESK
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Instalasi Database - BEANDESK</h1>";

// Konfigurasi database
$host = 'localhost';
$db_name = 'beandesk_pos';
$username = 'root';
$password = '';

try {
    // Koneksi tanpa database (untuk membuat database)
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p>✅ Koneksi ke MySQL berhasil!</p>";
    
    // Buat database jika belum ada
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name`");
    echo "<p>✅ Database '$db_name' berhasil dibuat!</p>";
    
    // Pilih database
    $pdo->exec("USE `$db_name`");
    
    // Baca dan eksekusi schema SQL
    $schema = file_get_contents('database/schema.sql');
    
    // Split per statement
    $statements = explode(';', $schema);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Skip error untuk statement yang sudah ada
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "<p>⚠️ Warning: " . $e->getMessage() . "</p>";
                }
            }
        }
    }
    
    echo "<p>✅ Schema database berhasil diimport!</p>";
    
    // Test koneksi dengan database yang baru
    $test_pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test query
    $stmt = $test_pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p>✅ Test koneksi ke database berhasil!</p>";
    echo "<p>✅ Jumlah user default: " . $result['count'] . "</p>";
    
    echo "<h2>🎉 Instalasi Berhasil!</h2>";
    echo "<p><strong>Akun default:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> username: admin, password: password</li>";
    echo "<li><strong>Kasir:</strong> username: kasir1, password: password</li>";
    echo "<li><strong>Gudang:</strong> username: gudang1, password: password</li>";
    echo "</ul>";
    echo "<p><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Mulai Menggunakan Sistem</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p><strong>Troubleshooting:</strong></p>";
    echo "<ul>";
    echo "<li>Pastikan MySQL service sudah running</li>";
    echo "<li>Check username dan password di file install.php</li>";
    echo "<li>Pastikan user MySQL memiliki privilege untuk membuat database</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p><small>File ini dapat dihapus setelah instalasi selesai.</small></p>";
?>
