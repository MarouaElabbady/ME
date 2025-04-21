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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email = '$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['name'] = $row['name'];

            // Add this after the existing login script, just before the redirection section
            // Modify the existing redirection code to include admin role
            if ($row['role'] == 'teacher') {
                header('Location: teacher_dashboard.php');
                exit();
            } elseif ($row['role'] == 'student') {
                header('Location: student_dashboard.php');
                exit();
            } elseif ($row['role'] == 'admin') {
                header('Location: admin_dashboard.php');
                exit();
            } else {
                $error_message = "Rôle utilisateur inconnu.";
            }
        } else {
            $error_message = "Mot de passe incorrect.";
        }
    } else {
        $error_message = "Utilisateur non trouvé.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam+ Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Light theme (default) */
            --primary-color: #4f46e5;
            --secondary-color: #4338ca;
            --accent-color: #00d4ff;
            --bg-gradient-from: #f8fafc;
            --bg-gradient-to: #e2e8f0;
            --card-bg: #ffffff;
            --welcome-bg: rgba(79, 70, 229, 0.1);
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --input-bg: #f1f5f9;
            --input-border: #cbd5e1;
            --error-color: #ef4444;
        }

        [data-theme="dark"] {
            /* Dark theme */
            --primary-color: #6366f1;
            --secondary-color: #4338ca;
            --accent-color: #38bdf8;
            --bg-gradient-from: #0f172a;
            --bg-gradient-to: #1e293b;
            --card-bg: #1e293b;
            --welcome-bg: rgba(79, 70, 229, 0.2);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --input-bg: #334155;
            --input-border: #475569;
            --error-color: #f87171;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            transition: background-color 0.3s, color 0.3s, border-color 0.3s;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, var(--bg-gradient-from), var(--bg-gradient-to));
            padding: 2rem;
        }

        .container {
            display: flex;
            width: 100%;
            max-width: 1000px;
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .welcome-section {
            flex: 1;
            padding: 4rem;
            background: var(--welcome-bg);
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
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .welcome-section p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            max-width: 80%;
            line-height: 1.6;
        }

        .login-section {
            flex: 1;
            padding: 4rem;
            background: var(--card-bg);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-section h2 {
            color: var(--primary-color);
            font-size: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .input-box {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .input-box input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            background: var(--input-bg);
            border: 2px solid var(--input-border);
            border-radius: 15px;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .input-box span {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            font-size: 1.2rem;
            pointer-events: none;
        }

        .input-box input:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 15px rgba(0, 212, 255, 0.2);
            outline: none;
        }

        button {
            background: linear-gradient(to right, var(--accent-color), var(--primary-color));
            color: white;
            padding: 1rem;
            border: none;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .links {
            margin-top: 1.5rem;
            text-align: center;
        }

        .links a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .links a:hover {
            color: var(--accent-color);
        }

        .error-message {
            color: var(--error-color);
            text-align: center;
            margin-top: 1rem;
            font-size: 0.9rem;
        }

        /* Theme switch */
        .theme-switch-container {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
        }

        .theme-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .theme-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--primary-color);
        }

        input:focus + .slider {
            box-shadow: 0 0 1px var(--primary-color);
        }

        input:checked + .slider:before {
            transform: translateX(30px);
        }

        .theme-icon {
            margin-left: 10px;
            font-size: 1.2rem;
            color: var(--text-primary);
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .welcome-section,
            .login-section {
                padding: 2rem;
            }

            .welcome-section h1 {
                font-size: 2rem;
            }
            
            .theme-switch-container {
                top: 10px;
                right: 10px;
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
            <h1>Welcome to Exam+</h1>
            <p>Your secure platform for managing exams and evaluations</p>
            <div class="theme-switch-container">
                <label class="theme-switch">
                    <input type="checkbox" id="theme-toggle">
                    <span class="slider"></span>
                </label>
                <span class="theme-icon" id="theme-icon">☀️</span>
            </div>
        </div>

        <div class="login-section">
            <h2>Sign In</h2>
            <form method="POST" action="">
                <div class="input-box">
                    <span>✉</span>
                    <input type="email" name="email" placeholder="Enter your email" required>
                </div>
                <div class="input-box">
                    <span>🔒</span>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>
                <button type="submit">Sign In</button>
            </form>
            <div class="links">
                <a href="#">Forgot Password?</a>
            </div>
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Theme switching functionality
        const themeToggle = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        
        // Check if user has a theme preference stored
        const currentTheme = localStorage.getItem('theme');
        
        // If the user has previously selected a theme
        if (currentTheme) {
            document.documentElement.setAttribute('data-theme', currentTheme);
            
            if (currentTheme === 'dark') {
                themeToggle.checked = true;
                themeIcon.textContent = '🌙';
            }
        }
        
        // When the user changes the theme
        themeToggle.addEventListener('change', function() {
            if (this.checked) {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                themeIcon.textContent = '🌙';
            } else {
                document.documentElement.setAttribute('data-theme', 'light');
                localStorage.setItem('theme', 'light');
                themeIcon.textContent = '☀️';
            }
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>