<?php

$servername = "localhost";
$username = "root";
$bd_password = "";

$con = mysqli_connect($servername, $username, $bd_password);

if(!$con){
    die("Erro ao conectar ao MySQL: " . mysqli_connect_error());
}

$escolheBD = mysqli_select_db($con,"gestao_utilizadores");

if(!$escolheBD){
    echo "Erro: não foi possível aceder à Base de Dados!";
    exit();
}

?>