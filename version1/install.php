<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

/**
 * Recursively scans the articles directory for subdirectories
 * that contain a metadata.json file and imports them into the database.
 *
 * @param PDO    $pdo         The PDO connection.
 * @param string $articlesDir The path to the articles directory.
 */
function importMetadata(PDO $pdo, string $articlesDir): void {
    $dirHandle = opendir($articlesDir);
    if (!$dirHandle) {
        echo "Failed to open articles directory for import.<br>";
        return;
    }
    
    // Prepare the insert statement once
    $insertStmt = $pdo->prepare("INSERT INTO articles (dir, subject, created_at, updated_at) VALUES (:dir, :subject, :created, :updated)");
    
    while (($entry = readdir($dirHandle)) !== false) {
        $subdir = $articlesDir . DIRECTORY_SEPARATOR . $entry;
        // We'll consider only directories whose names are numeric
        if (is_dir($subdir) && is_numeric($entry)) {
            $metadataFile = $subdir . DIRECTORY_SEPARATOR . 'metadata.json';
            if (file_exists($metadataFile)) {
                $jsonData = file_get_contents($metadataFile);
                $metadata = json_decode($jsonData, true);
                if (is_array($metadata)) {
                    $insertStmt->bindValue(':dir', (int)$metadata['dir'], PDO::PARAM_INT);
                    $insertStmt->bindValue(':subject', $metadata['subject'], PDO::PARAM_STR);
                    $insertStmt->bindValue(':created', $metadata['created_at'], PDO::PARAM_STR);
                    $insertStmt->bindValue(':updated', $metadata['updated_at'], PDO::PARAM_STR);
                    try {
                        $insertStmt->execute();
                    } catch (PDOException $e) {
                        error_log("Error importing metadata for directory $entry: " . $e->getMessage());
                    }
                }
            }
        }
    }
    closedir($dirHandle);
    echo "Metadata imported successfully.<br>";
}

// -------------------------
// 1. Reset the Database Table
// -------------------------
try {
    // Connect to the database using DSN from config.php
    $dsnNew = "mysql:host=localhost;dbname=articles_db;charset=utf8mb4";
    $pdo = new PDO($dsnNew, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("MySQL Connection error: " . $e->getMessage());
}

// Drop the articles table if it exists
$pdo->exec("DROP TABLE IF EXISTS articles");

// Create the articles table
$createTableQuery = "CREATE TABLE articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dir INT UNIQUE,
    subject VARCHAR(255),
    created_at DATETIME,
    updated_at DATETIME
)";
if ($pdo->exec($createTableQuery) !== false) {
    echo "Database table reset successfully.<br>";
} else {
    echo "Error creating articles table.<br>";
}

// -------------------------
// 2. Check for Articles Directory and Import Metadata
// -------------------------
$articlesDir = __DIR__ . '/articles';
if (is_dir($articlesDir)) {
    echo "Articles directory exists; scanning for metadata...<br>";
    importMetadata($pdo, $articlesDir);
} else {
    echo "Articles directory does not exist; please create it.<br>";
}

// -------------------------
// 3. Report Installation Success
// -------------------------
echo "<strong>Installation complete. Database table has been reset and metadata imported (if available).</strong>";
?>

