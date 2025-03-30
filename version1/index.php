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
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    error_log("MySQL Connection error: " . $e->getMessage());
    die("Database error.");
}

// Retrieve articles ordered by latest update
$stmt = $pdo->query("SELECT * FROM articles ORDER BY updated_at DESC");
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: output article count in an HTML comment (view source to check)
// echo "<!-- Articles count: " . count($articles) . " -->";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Articles App</title>
  <link id="theme-stylesheet" rel="stylesheet" type="text/css" href="css/dark.css">
  <style>
    /* Force article links to be green */
    ul li a {
      color: green !important;
      text-decoration: none;
    }
    ul li a:visited {
      color: green !important;
    }
    /* Top bar styling */
    .top-bar {
      padding: 10px;
      background-color: #1f1f1f;
    }
    .top-bar-inner {
      display: flex;
      justify-content: center;
      align-items: center;
    }
    .top-bar-inner a {
      margin: 0 10px;
      color: #e0e0e0;
      text-decoration: none;
      font-weight: bold;
    }
    .top-bar-inner a:hover {
      text-decoration: underline;
    }
    /* Center the articles list */
    ul {
      list-style-type: none;
      padding: 0;
      text-align: center;
    }
    ul li {
      margin: 5px 0;
    }
  </style>
  <script>
    function setTheme(newTheme) {
      document.getElementById('theme-stylesheet').href = 'css/' + newTheme + '.css';
      localStorage.setItem('theme', newTheme);
    }
    window.onload = function() {
      var storedTheme = localStorage.getItem('theme');
      if (storedTheme) {
        document.getElementById('theme-stylesheet').href = 'css/' + storedTheme + '.css';
      }
    };
  </script>
</head>
<body>
  <!-- Top bar with three links: [D] [L] and a static label for posting -->
  <div class="top-bar">
    <div class="top-bar-inner">
      <a href="javascript:void(0);" onclick="setTheme('dark');">[D]</a>
      <a href="javascript:void(0);" onclick="setTheme('light');">[L]</a>
      <span style="margin: 0 10px; color: #e0e0e0; font-weight: bold;">[Post Article]</span>
    </div>
  </div>

  <!-- Post form always displayed -->
  <div id="newArticleForm" style="display:block; text-align: center; margin-top: 10px;">
    <form action="submit_article.php" method="post" enctype="multipart/form-data">
      <input type="text" name="subject" placeholder="Subject:" required maxlength="20"><br>
      <textarea name="content" rows="10" cols="50" placeholder="Article Text:" required></textarea><br>
      <input type="file" name="media"><br>
      <button type="submit">Submit Article</button>
    </form>
  </div>
  <hr>
  <h2 style="text-align: center;">Articles</h2>
  <ul>
    <?php foreach ($articles as $article): ?>
      <li>
        <a href="articles/<?php echo htmlspecialchars((string)$article['dir'], ENT_QUOTES, 'UTF-8'); ?>/index.html">
          <?php echo htmlspecialchars($article['subject'], ENT_QUOTES, 'UTF-8'); ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</body>
</html>
