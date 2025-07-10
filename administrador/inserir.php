<?php



if(!$_POST["botaoInserir"] || $_SESSION["utilizador"]){
    header("Location: ../index.php");
}

require "../ligabd.php";

$sql_inserir = "INSERT INTO utilizadores VALUES
(null, '" . $_POST["utilizador"] . "' , password('" . $_POST["password"] . "'), '" .
$_POST["email"] . "' , '" . $_POST["id_tipos_utilizador"] . "')";


$resultado = mysqli_query($con,$sql_inserir);


if (!$resultado) {
    $_SESSION["erro"] ="Não foi possivel inserir o utilizador.";
    header("Location: ../index.php");
    exit();
}
    header("Location: editar_utilizadores.php"); 
?>
