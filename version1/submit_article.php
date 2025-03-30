<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.txt');
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_AUTOCOMMIT => false,
    ]);
} catch (PDOException $e) {
    error_log("MySQL Connection error: " . $e->getMessage());
    die("Database error.");
}

// Ensure the articles directory exists
$articlesDir = __DIR__ . '/articles';
if (!is_dir($articlesDir)) {
    if (!mkdir($articlesDir, 0755, true)) {
        error_log("Failed to create articles directory at {$articlesDir}");
        die("Internal error.");
    }
}

// Validate inputs
$subject = trim($_POST['subject'] ?? '');
$content = trim($_POST['content'] ?? '');
if ($subject === '' || $content === '') {
    error_log("Missing subject or content.");
    die("Subject and content are required.");
}
$subjectEsc = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
$contentEsc = nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));

// Begin an exclusive transaction
$pdo->beginTransaction();
try {
    // Determine the next sequential directory number (start at 1)
    $stmt = $pdo->query("SELECT MAX(dir) AS maxDir FROM articles");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $newDirNumber = ($row && $row['maxDir']) ? (int)$row['maxDir'] + 1 : 1;
    
    // Check if directory exists; if so, increment until free.
    while (is_dir($articlesDir . '/' . $newDirNumber)) {
        $newDirNumber++;
    }
    
    // Create article directory
    $articlePath = $articlesDir . '/' . $newDirNumber;
    if (!mkdir($articlePath, 0755, true)) {
        throw new Exception("Failed to create article directory: {$articlePath}");
    }
    
    // Handle file upload if present
    $mediaHtml = '';
    if (isset($_FILES['media']) && $_FILES['media']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['media']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error: " . $_FILES['media']['error']);
        }
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'video/mp4'  => 'mp4',
            'video/webm' => 'webm'
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['media']['tmp_name']);
        if (!array_key_exists($mimeType, $allowedTypes)) {
            throw new Exception("Unsupported file type: " . $mimeType);
        }
        $extension = $allowedTypes[$mimeType];
        $filename = 'media.' . $extension;
        $destination = $articlePath . '/' . $filename;
        if (!move_uploaded_file($_FILES['media']['tmp_name'], $destination)) {
            throw new Exception("Failed to move uploaded file to {$destination}");
        }
        if (strpos($mimeType, 'image') !== false) {
            $mediaHtml = '<div class="media"><img src="'.$filename.'" alt="Media"></div>';
        } elseif (strpos($mimeType, 'video') !== false) {
            $mediaHtml = '<div class="media"><video controls src="'.$filename.'"></video></div>';
        }
    }
    
    // Generate static article page content
    $articleHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$subjectEsc}</title>
  <link id="theme-stylesheet" rel="stylesheet" type="text/css" href="/css/dark.css">
  <script>
    window.onload = function() {
      var storedTheme = localStorage.getItem('theme');
      if (storedTheme) {
        document.getElementById('theme-stylesheet').href = '/css/' + storedTheme + '.css';
      }
    };
    function toggleReplyForm() {
      var formDiv = document.getElementById('replyForm');
      if (formDiv.style.display === 'none' || formDiv.style.display === '') {
          formDiv.style.display = 'block';
      } else {
          formDiv.style.display = 'none';
      }
    }
  </script>
</head>
<body>
  <div class="top-bar">
    <a href="/index.php" class="back-button">&lt;&lt; Back</a>
  </div>
  <div class="article-container">
    <h1 style="text-align: center;">{$subjectEsc}</h1>
    {$mediaHtml}
    <div class="content">
      {$contentEsc}
    </div>
    <hr>
    <div class="replies">
      <h3>Replies</h3>
      <div id="replyForm">
        <form action="/reply.php" method="post">
          <input type="hidden" name="article_dir" value="{$newDirNumber}">
          <textarea name="reply_text" rows="4" cols="50" required></textarea><br>
          <button type="submit">Submit Reply</button>
        </form>
      </div>
      <div id="replyList">
        <!-- REPLIES START -->
        <!-- REPLIES END -->
      </div>
    </div>
  </div>
</body>
</html>
HTML;
    
    $articleFile = $articlePath . '/index.html';
    if (file_put_contents($articleFile, $articleHtml) === false) {
        throw new Exception("Failed to write article file at {$articleFile}");
    }
    
    // Create per-article JSON metadata file
    $metadata = [
        'dir'         => $newDirNumber,
        'subject'     => $subject,
        'created_at'  => date('Y-m-d H:i:s'),
        'updated_at'  => date('Y-m-d H:i:s')
    ];
    $metadataJson = json_encode($metadata, JSON_PRETTY_PRINT);
    $metadataFile = $articlePath . '/metadata.json';
    if (file_put_contents($metadataFile, $metadataJson) === false) {
        throw new Exception("Failed to write metadata file at {$metadataFile}");
    }
    
    // Insert record into MySQL
    $now = date('Y-m-d H:i:s');
    $insertStmt = $pdo->prepare("INSERT INTO articles (dir, subject, created_at, updated_at) VALUES (:dir, :subject, :created, :updated)");
    $insertStmt->bindValue(':dir', $newDirNumber, PDO::PARAM_INT);
    $insertStmt->bindValue(':subject', $subject, PDO::PARAM_STR);
    $insertStmt->bindValue(':created', $now, PDO::PARAM_STR);
    $insertStmt->bindValue(':updated', $now, PDO::PARAM_STR);
    $insertStmt->execute();
    
    // Commit transaction
    $pdo->commit();
    
    header("Location: /index.php");
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error in submit_article.php: " . $e->getMessage());
    die("Internal error.");
}
?>
