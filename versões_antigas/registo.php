<?php
session_start();

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta</title>
    <link rel="icon" type="image/x-icon" href="berto.jpg">
    <link rel="stylesheet" type="text/css" href="styles/conta.css">
</head>

<body>
    <div class="container">
        <h2>Criar uma conta</h2>
        <div class="switch">
            <button id="personal" class="active">Pessoal</button>
            <button id="professional">Profissional</button>
        </div>

        <!-- Formulário Pessoal -->

        <form class="signup-form" id="personal-form" action="utilizador/registo.php" method="POST">
            <div class="input-group">
                <input type="utilizador" name="utilizador" placeholder="Nome" required>
                <input type="text" name="sobrenome" placeholder="Sobrenome" required>
            </div>
            <input type="email" name="email" placeholder="E-mail" required>
            <input type="date" id="dataNascimento" name="data_nascimento" placeholder="Data de Nascimento" required>
            <input type="password" id="senhaPersonal" name="password" placeholder="Senha" required oncopy="return false"
                onpaste="return false" oncut="return false"> <!-- Desabilita copiar/colar/cortar -->
            <input type="hidden" name="tipo" value="pessoal">

            <div id="erro" class="erro"></div>

            <input type="submit" name="botaoInserir" value="Criar conta pessoal">
        </form>

        <!-- Formulário Profissional -->
        <form class="signup-form" style="display: none;" id="professional-form" action="utilizador/registo.php"
            method="POST">
            <div class="input-group">
                <input type="utilizador" name="utilizador" placeholder="Nome Completo" required>
                <input type="text" name="cargo" placeholder="Cargo" required>
            </div>
            <input type="email" name="email" placeholder="E-mail Profissional" required>
            <select name="empresa" required>
                <option value="" disabled selected>Selecione o seu tipo de empresa</option>
                <option value="empresa propria">Empresa Própria</option>
                <option value="em nome de empresa">Em nome de Empresa</option>
            </select>
            <input type="password" id="senhaProfessional" name="password" placeholder="Senha" required
                oncopy="return false" onpaste="return false" oncut="return false">
            <!-- Desabilita copiar/colar/cortar -->
            <input type="hidden" name="tipo" value="profissional">

            <div id="erro" class="erro"></div>

            <input type="submit" name="botaoInserir" value="Criar conta profissional">
        </form>

        <p>Ou continuar com</p>
        <div class="social-login">
            <button>
                <img src="google.png" alt="google" style="width: 20px; height: 20px; vertical-align: middle;">
            </button>
            <button>
                <img src="facebook.png" alt="facebook" style="width: 20px; height: 20px; vertical-align: middle;">
            </button>
            <button>
                <img src="apple.png" alt="apple" style="width: 20px; height: 25px; vertical-align: middle;">
            </button>
        </div>
        <div id="erro"></div>
    </div>

    <script>
        // Define limites de data de nascimento
        function setDateLimits() {
            const today = new Date();
            const minAge = 18;
            const maxAge = 90;

            // Calcula a data mínima (18 anos atrás) e a data máxima (90 anos atrás)
            const minDate = new Date(today.getFullYear() - maxAge, today.getMonth(), today.getDate());
            const maxDate = new Date(today.getFullYear() - minAge, today.getMonth(), today.getDate());

            // Ajusta os limites no campo de data
            const birthInput = document.getElementById('dataNascimento');
            birthInput.min = minDate.toISOString().split('T')[0];  // Formata para yyyy-mm-dd
            birthInput.max = maxDate.toISOString().split('T')[0];  // Formata para yyyy-mm-dd
        }

        window.onload = setDateLimits;

        function validateForm(formType) {
            // Validação da data de nascimento
            const birthDate = new Date(document.getElementById('dataNascimento').value);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const month = today.getMonth() - birthDate.getMonth();
            const day = today.getDate() - birthDate.getDate();

            if (month < 0 || (month === 0 && day < 0)) {
                age--;
            }

            if (age < 18) {
                alert("Você precisa ter no mínimo 18 anos para criar uma conta.");
                return false;
            }

            // Validação da senha
            let senha;
            if (formType === 'personal') {
                senha = document.getElementById('senhaPersonal').value;
            } else if (formType === 'professional') {
                senha = document.getElementById('senhaProfessional').value;
            }

            const senhaRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;

            if (!senhaRegex.test(senha)) {
                alert("A senha deve ter no mínimo 8 caracteres, incluindo pelo menos uma letra maiúscula, uma letra minúscula, um número e um caractere especial.");
                return false;
            }

            return true; // Se todas as validações forem atendidas
        }

        // Alternar entre formulário Pessoal e Profissional
        const personalBtn = document.getElementById('personal');
        const professionalBtn = document.getElementById('professional');
        const personalForm = document.getElementById('personal-form');
        const professionalForm = document.getElementById('professional-form');

        function toggleForm(buttonActive, buttonInactive, formToShow, formToHide) {
            buttonActive.classList.add('active');
            buttonInactive.classList.remove('active');
            formToShow.style.display = 'flex';
            formToHide.style.display = 'none';
        }

        personalBtn.addEventListener('click', () => {
            toggleForm(personalBtn, professionalBtn, personalForm, professionalForm);
        });

        professionalBtn.addEventListener('click', () => {
            toggleForm(professionalBtn, personalBtn, professionalForm, personalForm);
        });
    </script>
</body>

</html>

<?php

if (isset($_SESSION['erro'])) {
    echo "<script> document.getElementById('erro').textContent = '" . $_SESSION['erro'] . "'; </script>";
    unset($_SESSION['erro']); // Limpa a mensagem da sessão após exibir
}

?>