<?php
/**
 * Database Configuration & Connection (PHP PDO)
 * FoundU - Lost & Found Management System
 * Compatible with XAMPP (Apache + MySQL / MariaDB)
 */

if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: '3306');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'lost_and_found_db1');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO database connection instance.
 *
 * @return PDO
 */
function get_db_connection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Friendly error message for XAMPP users if database has not been imported yet
            die(
                '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 25px; border: 1px solid #f5c2c7; border-radius: 8px; background: #f8d7da; color: #842029;">' .
                '<h2 style="margin-top: 0;">Database Connection Error</h2>' .
                '<p>Could not connect to MySQL database <strong>' . htmlspecialchars(DB_NAME) . '</strong> on <strong>' . htmlspecialchars(DB_HOST) . '</strong>.</p>' .
                '<p><strong>Troubleshooting for XAMPP:</strong></p>' .
                '<ol style="line-height: 1.6;">' .
                '<li>Ensure the <strong>MySQL</strong> module is running in your XAMPP Control Panel.</li>' .
                '<li>Open <a href="http://localhost/phpmyadmin" target="_blank" style="color: #0d6efd;">phpMyAdmin</a> and create the database named <code>' . htmlspecialchars(DB_NAME) . '</code>.</li>' .
                '<li>Import the file <code>database/lost_and_found_db.sql</code> into phpMyAdmin.</li>' .
                '<li>Verify database credentials in <code>config/database.php</code>.</li>' .
                '</ol>' .
                '<p><small>Error details: ' . htmlspecialchars($e->getMessage()) . '</small></p>' .
                '</div>'
            );
        }
    }

    return $pdo;
}
