<?php
    session_start();

    if(!isset($_POST["botaoGravar"]) ||!isset($_SESSION["utilizador"]) 
     || $_SESSION["id_tipos_utilizador"] != 0){
        header("Location: index.php");
        exit();
    }

    if($_POST["utilizador"] == "admin" 
    && $_SESSION["utilizador"] != "admin"){
        header("Location: index.php");
        exit();
    }

    require "ligabd.php";

    $g_utilizador =htmlspecialchars($_POST["utilizador"]);
    $g_password =htmlspecialchars($_POST["password"]);
    $g_email =htmlspecialchars($_POST["email"]);
    $g_id_tipos_utilizador =htmlspecialchars($_POST["id_tipos_utilizador"]);


    $sql_gravar = 
    

    $sql_gravar = "UPDATE utilizadores SET password=password('".$_POST["password"]."')
    , email='".$_POST["email"]. "', id_tipos_utilizador ='".$POST["id_tipos_utilizador"]."' WHERE utilizador= '".$_POST["utilizador"]."'";
?>