<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css" />
  <link rel="stylesheet" href="style.css" />
   <link rel="icon" type="image/png" href="../berto.png" />
  <title>página de login e registo</title>
</head>

<body>
  <div style="position: absolute; top: 20px; left: 30px; z-index: 10;">
    <span
      style="font-size: 2rem; font-weight: bold; color:rgb(0, 0, 0); font-family: 'Segoe UI', Arial, sans-serif; letter-spacing: 2px;">
      Berto
    </span>
  </div>
  <div class="container">
    <div class="forms">
      <div class="form login">
        <span class="title">Login</span>

        <form action="utilizador/login.php" method="POST">
          <div class="input-field">
            <input type="email" name="email" placeholder="Escreva o seu email" required />
            <i class="uil uil-envelope icon"></i>
          </div>
          <div class="input-field">
            <input type="password" class="password" id="senhaPersonal" name="senha" placeholder="Password"
              placeholder="Escreva sua palavra-pass" required />
            <i class="uil uil-lock icon"></i>
            <i class="uil uil-eye-slash showHidePw"></i>
          </div>

          <div class="checkbox-text">
           

            <a href="enviar_codigo.php" class="text">Esqueceu a password?</a>
          </div>

          <div class="input-field button">
            <input type="submit" name="botaoLogin" value="Entrar">
          </div>
        </form>

        <div class="login-signup">
          <span class="text">Não tem conta?
            <a href="#" class="text signup-link">Registar conta</a>
          </span>
        </div>
      </div>

      <!-- Registration Form -->
      <div class="form signup">
        <span class="title">Registo</span>

        <form action="utilizador/registo.php" method="POST">
          <div class="input-field">
            <input type="utilizador" name="utilizador" placeholder="Insira o seu nome" required required />
            <i class="uil uil-user"></i>
          </div>
          <div class="input-field">
            <input type="email" name="email" placeholder="Escreva seu email" required />
            <i class="uil uil-envelope icon"></i>
          </div>
          <div class="input-field">
            <input type="password" id="signupPassword" name="password" placeholder="escolha password" required />
            <i class="uil uil-lock icon"></i>
          </div>
          <?php
          if (isset($_SESSION['erro'])) {
            echo "<script> document.getElementById('erro').textContent = '" . $_SESSION['erro'] . "'; </script>";
            unset($_SESSION['erro']); // Limpa a mensagem da sessão após exibir
          }
          ?>

          <div class="input-field button">
            <input type="submit" name="botaoInserir" value="Criar conta pessoal">
          </div>

        </form>

        <div class="login-signup">
          <span class="text">Já tem conta?
            <a href="#" class="text login-link">Entre agora</a>
          </span>
        </div>
      </div>
    </div>
  </div>

  <script src="script.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const passwordInput = document.getElementById('signupPassword');
      const form = passwordInput.closest('form');

      function validatePassword(password) {
        const minLength = /.{8,}/;
        const upper = /[A-Z]/;
        const lower = /[a-z]/;
        const number = /[0-9]/;
        const special = /[^A-Za-z0-9]/;
        let messages = [];
        if (!minLength.test(password)) messages.push("Pelo menos 8 caracteres");
        if (!upper.test(password)) messages.push("Uma letra maiúscula");
        if (!lower.test(password)) messages.push("Uma letra minúscula");
        if (!number.test(password)) messages.push("Um número");
        if (!special.test(password)) messages.push("Um símbolo");
        return messages;
      }

      form.addEventListener('submit', function (e) {
        const messages = validatePassword(passwordInput.value);
        if (messages.length > 0) {
          e.preventDefault();
          alert("A password deve conter:\n" + messages.join("\n"));
          passwordInput.focus();
        }
      });
    });
  </script>
</body>

</html>