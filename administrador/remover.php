<?php

session_start();

if(!isset($_POST["botaoRemover"]) || !isset($_SESSION["utilizador"]) || $_SESSION["id_tipos_utilizador"] !=0)
{
    header("Location: index.php");
    exit();
}

if($_POST["utilizador"] == "admin"){
    header("Location: index.php");
    exit();
}


require "../ligabd.php";
$sql_remover = "DELETE FROM utilizadores WHERE utilizador= '".$_POST["utilizador"]. "'";

$resultado = mysqli_query($con,$sql_remover);

if(!$resultado){
$_SESSION["erro"] = "não foi possivel remover o utilizador.";
header("Location: index.php");
exit();

}

header("Location: editar_utilizadores.php")
?>