<?php
    session_start();
?>
<!DOCTYPE html>
<html lang="pt">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Document</title>
        <link rel="stylesheet" href="../styles/gestao.css">    </head>
    <body>
    <div class="sidebar">
        <h2>Menu</h2>
        <button onclick="window.location.href='editar_utilizadores.php'">Gerir Utilizadores</button>
        <button onclick="window.location.href='adicionar_produto.php'">Adicionar produto</button>
        <button onclick="window.location.href='gestao_produtos.php'">Gerir Produtos</button>
        <button onclick="window.location.href='adicionar_serviço.php'">Adicionar Serviço</button>
        <button onclick="window.location.href='gestao_serviços.php'">Gerir Serviços</button>
        <button onclick="window.location.href='../admin_disputes.php'">Gerir Disputas</button>
        <button onclick="window.location.href='../index.php'">Sair</button>
        

    </div>

    <!-- Conteúdo principal -->
   


    <?php


        // reencaminhar para index.php, se não existir sessão iniciada
        // ou, existindo, se o utilizador não for um administrador
        if ( !isset($_SESSION["utilizador"]) || $_SESSION["id_tipos_utilizador"]!=0) {
            header("Location: index.php");
            exit();
        }


        require "../ligabd.php";


        $sql = "SELECT * FROM utilizadores, tipos_utilizador WHERE utilizadores.id_tipos_utilizador=tipos_utilizador.id_tipos_utilizador";


        $resultado = mysqli_query($con, $sql);
       
        if (!$resultado ) {
            $_SESSION["erro"] = "Não foi possível obter os dados dos utilizadores";
            header("Location: index.php");
            exit();
        }
    ?>


        <script>


            var botaoAcao="";


            function remover(idForm) {
                document.getElementById(idForm).action = "remover.php";
                botaoAcao = "remover";
            }


            function gravar(idForm) {
                document.getElementById(idForm).action = "gravar.php";
                botaoAcao = "gravar";
            }


            function acao() {
                if (botaoAcao == "remover") {
                    botaoAcao = "";
                    return true;
                }
                else if (botaoAcao == "gravar") {
                    botaoAcao = "";
                    return true;
                }
                else {
                    // impedir a submissão da form
                    return false;
                }
            }


            function inserir() {
                // para o tratamento de erros no valores dos campos de edição,
                // colocar aqui código que retorna false para impedir o submit
                // da form
                return true;
            }






        </script>




        <table>
            <tr>
                <th>Utilizador</th>
                <th>Palavra-passe</th>
                <th>Email</th>
                <th>Tipo de utilizador</th>
            </tr>
       
        <?php
            while ( $registo = mysqli_fetch_array( $resultado ) ) {
                // não gerar o registo de "admin", se o utlizador
                // com sessão iniciada não for o próprio "admin"
                if ( $registo["utilizador"] == "admin" && $_SESSION["utilizador"] != "admin") {
                    continue; // passar para a próxima iteração do ciclo
                }
       
                echo "
                <form id='form" . $registo["id_utilizadores"] . "' action='' method='post' onsubmit='return acao()'>
                    <tr>
                        <td hidden>
                            <input name='id_utilizadores' type='text' value='" . $registo["id_utilizadores"] . "'>
                        </td>


                        <td>
                            <input readonly name='utilizador' type='text' value='" . $registo["utilizador"] . "'>
                        </td>
                       
                        <td>
                            <input name='password' type='password'>
                        </td>
                       
                        <td>
                            <input name='email' type='text' value='" . $registo["email"] . "'>
                        </td>


                        <td>
                            <select name='id_tipos_utilizador'>
                                <option value='0' " . (($registo["id_tipos_utilizador"]=='0') ? " selected " : "") ."> administrador </option>
                                <option value='1' " . (($registo["id_tipos_utilizador"]=='1') ? " selected " : "") . "> utilizador </option>
                             </select>
                        </td>
                       
                        <!-- botão escondido (primeiro da form)
                            para lhe ser associada automaticamente
                            a ação 'Enter' na form -->


                        <td hidden> <button></button> </td>


                        <td>
                            <button name='botaoRemover' onclick='remover(\"form" . $registo["id_utilizadores"] . "\")' " . ( ($registo["utilizador"]=="admin") ? " disabled " : "" ) . ">
                            Remover </button>
                        </td>
                       
                        <td>
                            <button name='botaoGravar' onclick='gravar(\"form" . $registo["id_utilizadores"] . "\")'                    >
                            Gravar </button>
                        </td>
                    </tr>
                </form>";
            }
        ?>


        </table>


        <p></p>


        <table>
            <tr>
                <th>Utilizador</th>
                <th>Palavra-passe</th>
                <th>Email</th>
                <th>Tipo de utilizador</th>
            </tr>
            <tr>
                <form id="formInserir" action='inserir.php' method='post' onsubmit='inserir()'>
                <tr>
                    <td> <input name='utilizador' type='text'></td>
                    <td> <input name='password' type='password'></td>
                    <td> <input name='email' type='text'></td>
                    <td> <select name='id_tipos_utilizador'>
                            <option value='0'> administrador </option>
                            <option value='1'> utilizador </option>
                        </select>
                    </td>
                    <td>
                        <button name='botaoInserir'> Inserir </button>
                    </td>
                </tr>
            </form>
        </table>


        <br></br>


        <div id="erro" style="color:red"></div>


        <?php
            // verificar se houve erro na edição de utilizadores
            if ( isset($_SESSION["erro"]) ) {
                echo "<script>document.getElementById('erro').innerHTML = '" . $_SESSION["erro"] . "'</script>";
                unset($_SESSION["erro"]);
            }
            ?>


            <br></br>


            


       
    </body>
</html>
