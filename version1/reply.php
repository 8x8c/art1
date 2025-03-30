<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.txt');
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/config.php';

$articleDir = trim($_POST['article_dir'] ?? '');
$replyText = trim($_POST['reply_text'] ?? '');
if ($articleDir === '' || $replyText === '') {
    error_log("Invalid reply submission: article_dir or reply_text missing.");
    die("Invalid reply submission.");
}
$replyTextEsc = nl2br(htmlspecialchars($replyText, ENT_QUOTES, 'UTF-8'));

// Determine the article file path
$articlePath = __DIR__ . '/articles/' . $articleDir;
$articleFile = $articlePath . '/index.html';

if (!file_exists($articleFile)) {
    error_log("Article file not found at {$articleFile}");
    die("Article not found.");
}

$fileContents = file_get_contents($articleFile);
if ($fileContents === false) {
    error_log("Failed to read article file at {$articleFile}");
    die("Internal error.");
}

$replyHtml = <<<HTML
<div class="reply">
  <p>{$replyTextEsc}</p>
  <hr>
</div>
HTML;

$marker = '<!-- REPLIES END -->';
if (strpos($fileContents, $marker) === false) {
    error_log("Reply marker not found in article file at {$articleFile}");
    die("Reply section not found.");
}

$newFileContents = str_replace($marker, $replyHtml . $marker, $fileContents);

$fileHandle = fopen($articleFile, 'c+');
if (!$fileHandle) {
    error_log("Failed to open article file for writing: {$articleFile}");
    die("Internal error.");
}
if (flock($fileHandle, LOCK_EX)) {
    ftruncate($fileHandle, 0);
    fwrite($fileHandle, $newFileContents);
    fflush($fileHandle);
    flock($fileHandle, LOCK_UN);
} else {
    error_log("Could not lock article file: {$articleFile}");
    fclose($fileHandle);
    die("Internal error.");
}
fclose($fileHandle);

// Update the article's updated_at timestamp in MySQL
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    error_log("MySQL Connection error in reply.php: " . $e->getMessage());
    die("Database error.");
}

$now = date('Y-m-d H:i:s');
$updateStmt = $pdo->prepare("UPDATE articles SET updated_at = :updated WHERE dir = :dir");
$updateStmt->bindValue(':updated', $now, PDO::PARAM_STR);
$updateStmt->bindValue(':dir', (int)$articleDir, PDO::PARAM_INT);
$updateStmt->execute();

header("Location: /articles/{$articleDir}/index.html");
exit;
?>
