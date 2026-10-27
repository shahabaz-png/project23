<?php
/** db.php - returns one shared PDO connection (prepared statements, real exceptions). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $ex) {
            error_log('DB connection failed: ' . $ex->getMessage());
            http_response_code(500);
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Database error</title></head>'
               . '<body style="font-family:sans-serif;padding:2rem"><h2>Database connection failed</h2>'
               . '<p>Please make sure MySQL is running in XAMPP, that <code>database.sql</code> was imported, '
               . 'and that the credentials in <code>config.php</code> are correct.</p></body></html>';
            exit;
        }
    }
    return $pdo;
}
