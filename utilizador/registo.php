<?php

session_start();


if(!$_POST["botaoInserir"]  ){
    header("Location: ../index1.html");
}
if(isset($_SESSION["utilizador"])){
    header("Location: ../index2.html");
}

require "../ligabd.php";

$sql_procurarmail = "SELECT * FROM utilizadores WHERE email='".$_POST["email"]."'";
$resultado= mysqli_query($con, $sql_procurarmail);
if(mysqli_num_rows($resultado)>0){
    $_SESSION["erro"] ="email já em uso";
    header("Location: ../registop2.php");
    exit();
}

$sql_inserir = "INSERT INTO utilizadores VALUES
(null, '" . $_POST["utilizador"] . "' , password('" . $_POST["password"] . "'), '" .
$_POST["email"] . "' , '" . 1 . "')";


$resultado = mysqli_query($con,$sql_inserir);


if (!$resultado) {
    $_SESSION["erro"] ="Não foi possivel inserir o utilizador.";
    header("Location: ../index3.php");
    exit();
}
    header("Location: ../index.php"); 
?>
