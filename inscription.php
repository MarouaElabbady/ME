<?php
session_start();
$servername = "localhost";
$username = "root";
$password = ""; // Remplacez par le mot de passe de votre base de données
$dbname = "exam_system";

// Connexion à la base de données
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error_message = "";
$success_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $conn->real_escape_string($_POST['role']);

    // Vérification si l'e-mail existe déjà
    $check_email = "SELECT * FROM users WHERE email = '$email'";
    $result = $conn->query($check_email);

    if ($result->num_rows > 0) {
        $error_message = "Cet e-mail est déjà utilisé.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Les mots de passe ne correspondent pas.";
    } else {
        // Hashage du mot de passe
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insertion dans la base de données
        $sql = "INSERT INTO users (name, email, password, role) VALUES ('$name', '$email', '$hashed_password', '$role')";
        
        if ($conn->query($sql) === TRUE) {
            $success_message = "Inscription réussie ! Vous pouvez maintenant vous connecter.";
        } else {
            $error_message = "Erreur: " . $sql . "<br>" . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam+ Inscription</title>
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #4338ca;
            --accent-color: #00d4ff;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --error-color: #ef4444;
            --success-color: #10b981;
            --body-bg: linear-gradient(135deg, #f6f8fc, #eef2ff);
            --card-shadow: 0 25px 45px rgba(0, 0, 0, 0.1);
            --input-bg: rgba(255, 255, 255, 0.9);
            --input-text: #1e293b;
            --button-shadow: 0 5px 15px rgba(79, 70, 229, 0.3);
        }

        [data-theme="dark"] {
            --primary-color: #4f46e5;
            --secondary-color: #4338ca;
            --accent-color: #00d4ff;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --error-color: #ef4444;
            --success-color: #10b981;
            --body-bg: linear-gradient(135deg, #0f172a, #1e1b4b);
            --card-shadow: 0 25px 45px rgba(0, 0, 0, 0.3);
            --input-bg: rgba(30, 41, 59, 0.8);
            --input-text: #ffffff;
            --button-shadow: 0 5px 15px rgba(0, 212, 255, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: var(--body-bg);
            padding: 2rem;
        }

        .container {
            display: flex;
            width: 100%;
            max-width: 1000px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .welcome-section {
            flex: 1;
            padding: 4rem;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.2), rgba(67, 56, 202, 0.2));
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--accent-color), var(--primary-color));
            opacity: 0.1;
            transform: translateX(-100%);
            animation: lightPass 8s infinite;
        }

        @keyframes lightPass {
            0%, 100% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
        }

        .welcome-section h1 {
            font-size: 2.5rem;
            color: var(--text-primary);
            margin-bottom: 1rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .welcome-section p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            max-width: 80%;
            line-height: 1.6;
        }

        .register-section {
            flex: 1;
            padding: 3rem;
            background: var(--card-bg);
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        .theme-toggle {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: transparent;
            border: none;
            color: var(--accent-color);
            font-size: 1.5rem;
            cursor: pointer;
            z-index: 10;
        }

        .register-section h2 {
            color: var(--accent-color);
            font-size: 2rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .input-box {
            position: relative;
            margin-bottom: 1.2rem;
        }

        .input-box input, .input-box select {
            width: 100%;
            padding: 0.8rem 1rem 0.8rem 3rem;
            background: var(--input-bg);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            color: var(--input-text);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .input-box span {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--accent-color);
            font-size: 1.2rem;
            pointer-events: none;
        }

        .input-box input:focus, .input-box select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 15px rgba(0, 212, 255, 0.2);
            outline: none;
        }

        button {
            background: linear-gradient(to right, var(--accent-color), var(--primary-color));
            color: var(--text-primary);
            padding: 1rem;
            border: none;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
            box-shadow: var(--button-shadow);
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 212, 255, 0.4);
        }

        .links {
            margin-top: 1.5rem;
            text-align: center;
        }

        .links a {
            color: var(--accent-color);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .links a:hover {
            color: var(--text-primary);
        }

        .error-message {
            color: var(--error-color);
            text-align: center;
            margin-top: 1rem;
            font-size: 0.9rem;
        }

        .success-message {
            color: var(--success-color);
            text-align: center;
            margin-top: 1rem;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .welcome-section,
            .register-section {
                padding: 2rem;
            }

            .welcome-section h1 {
                font-size: 2rem;
            }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .welcome-section h1,
        .welcome-section p {
            animation: float 4s ease-in-out infinite;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="welcome-section">
            <h1>Rejoignez Exam+</h1>
            <p>Créez votre compte pour accéder à la plateforme d'examens en ligne</p>
        </div>

        <div class="register-section">
            <button class="theme-toggle" id="themeToggle">🌙</button>
            <h2>Inscription</h2>
            <form method="POST" action="">
                <div class="input-box">
                    <span>👤</span>
                    <input type="text" name="name" placeholder="Entrez votre nom complet" required>
                </div>
                <div class="input-box">
                    <span>✉</span>
                    <input type="email" name="email" placeholder="Entrez votre email" required>
                </div>
                <div class="input-box">
                    <span>🔒</span>
                    <input type="password" name="password" placeholder="Créez un mot de passe" required>
                </div>
                <div class="input-box">
                    <span>🔒</span>
                    <input type="password" name="confirm_password" placeholder="Confirmez votre mot de passe" required>
                </div>
                <div class="input-box">
                    <span>👨‍🎓</span>
                    <select name="role" required>
                        <option value="">Sélectionnez votre rôle</option>
                        <option value="student">Étudiant</option>
                        <option value="teacher">Enseignant</option>
                    </select>
                </div>
                <button type="submit">S'inscrire</button>
            </form>
            <div class="links">
                <a href="login.php">Déjà inscrit? Connectez-vous</a>
            </div>
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>
            <?php if (!empty($success_message)): ?>
                <div class="success-message"><?php echo $success_message; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Script pour basculer entre le mode sombre et clair
        const themeToggle = document.getElementById('themeToggle');
        const htmlElement = document.documentElement;
        
        // Vérifier le thème enregistré dans localStorage
        const savedTheme = localStorage.getItem('theme') || 'light';
        htmlElement.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);
        
        themeToggle.addEventListener('click', function() {
            const currentTheme = htmlElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            htmlElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            updateThemeIcon(newTheme);
        });
        
        function updateThemeIcon(theme) {
            themeToggle.textContent = theme === 'light' ? '🌙' : '☀️';
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>