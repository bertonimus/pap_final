<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css" />
  <link rel="stylesheet" href="style.css" />
  <title>página de login e registo</title>
</head>

<body>
  <div class="container">
    <div class="forms">
      <div class="form forgot">
        <span class="title">o que deseja recuperar?</span>

        <form action="utilizador/login.php" method="POST">
          <div class="input-field">
            <input type="email" name="email" placeholder="Escreva o seu email" required />
            <i class="uil uil-envelope icon"></i>
          </div>
          <div class="checkbox-text">
          </div>

          <div class="input-field button">
            <input type="submit" name="botaoLogin" value="Entrar">
          </div>
          
        </form>
        </div>
      </div>
    </div>
</html>