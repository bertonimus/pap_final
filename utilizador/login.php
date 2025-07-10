<?php
session_start();

include "../ligabd.php";

// Verifica se o utilizador já está logado
if (isset($_SESSION["utilizador"])) {
    header("Location: login1.html");
    exit();
}

$P_email = $_POST["email"];
$P_senha = $_POST["senha"];

$sql = "SELECT * FROM utilizadores WHERE email = '$P_email'";
$resultado = mysqli_query($con, $sql);

if (!$resultado) {
    $_SESSION["erro"] = "Não foi possível obter os dados do utilizador";
    header("Location: login2.html");
    exit();
}

$registo = mysqli_fetch_array($resultado);

if ($registo) {
    $sqlpass = "SELECT * FROM utilizadores WHERE email = '$P_email' && password=password('$P_senha')";
    $resultado_pass = mysqli_query($con, $sqlpass);

    if (!$resultado_pass) {
        $_SESSION["erro"] = "Não foi possível obter os dados do utilizador";
        header("Location: login3.html");
        exit();
    }

    $registo = mysqli_fetch_array($resultado_pass);

    if ($registo) {
        // Armazena dados importantes na sessão
        $_SESSION["utilizador"] = $registo["utilizador"];
        $_SESSION["id_tipos_utilizador"] = $registo["id_tipos_utilizador"];
        $_SESSION["id_utilizadores"] = $registo["id_utilizadores"]; // Adicionado ID do utilizador

        // Redireciona conforme o tipo de utilizador
        if ($_SESSION["id_tipos_utilizador"] == 0) {
            header("Location: ../index.php");
            exit();
        }
        header("Location: ../index.php");
        exit();
    }

    $_SESSION["erro"] = "Palavra-passe errada";
    header("Location: login4.html");
    exit();
} else {
    $_SESSION["erro"] = "O utilizador não existe";
    header("Location: login5.html");
    exit();
}
?>
