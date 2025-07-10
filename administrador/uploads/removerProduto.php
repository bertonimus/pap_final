<?php

session_start();

if(!isset($_POST["btnRemover"]) || !isset($_SESSION["nome"]) || $_SESSION["id"] !=0)
{
    header("Location: index.php");
    exit();
}
require "../ligabd.php";
$sql_remover = "DELETE FROM produtos WHERE nome= '".$_POST["nome"]. "'";

$resultado = mysqli_query($con,$sql_remover);

if(!$resultado){
$_SESSION["erro"] = "não foi possivel remover o produto.";
header("Location: gestao_produtos.php");
exit();

}

header("Location: gestao_produtos.php");
?>