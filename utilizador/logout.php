<?php
session_start();

if(isset($_SESSION["utilizador"]) && isset( $_POST["botaoLogout"])) {
    session_start();
    session_unset();
    session_destroy();
}

header("Location:../index.php");
exit();
?>