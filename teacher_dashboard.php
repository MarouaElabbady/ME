<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'teacher') {
    header("Location: login.php");
    exit();
}

// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "exam_system");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Récupérer les examens complétés qui nécessitent une correction
$query = "SELECT DISTINCT e.id, e.title, e.start_time, e.end_time,
          COUNT(DISTINCT es.student_id) as submitted_count,
          COUNT(DISTINCT q.id) as question_count
          FROM exams e
          JOIN exam_students es ON e.id = es.exam_id
          LEFT JOIN questions q ON e.id = q.exam_id
          WHERE es.status = 'completed'
          AND e.teacher_id = ?
          GROUP BY e.id
          ORDER BY e.end_time DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$exams_result = $stmt->get_result();

// Compter le nombre total d'étudiants
$student_query = "SELECT COUNT(*) as student_count FROM users WHERE role = 'student'";
$student_result = $conn->query($student_query);
$student_data = $student_result->fetch_assoc();
$student_count = $student_data['student_count'];

// Compter le nombre total de professeurs
$teacher_query = "SELECT COUNT(*) as teacher_count FROM users WHERE role = 'teacher'";
$teacher_result = $conn->query($teacher_query);
$teacher_data = $teacher_result->fetch_assoc();
$teacher_count = $teacher_data['teacher_count'];

// Compter le nombre total d'examens
$exam_query = "SELECT COUNT(*) as exam_count FROM exams";
$exam_result = $conn->query($exam_query);
$exam_data = $exam_result->fetch_assoc();
$exam_count = $exam_data['exam_count'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Formateur</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Light theme (default) */
            --primary-color: #4f46e5;
            --secondary-color: #4338ca;
            --accent-color: #00d4ff;
            --danger-color: #dc2626;
            --warning-color: #f59e0b;
            --success-color: #10b981;
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --sidebar-bg: linear-gradient(to bottom, #4f46e5, #4338ca);
            --box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
            --hover-bg: rgba(0, 0, 0, 0.05);
            --card-border: rgba(0, 0, 0, 0.1);
            --icon-opacity: 0.1;
            --sidebar-hover: rgba(255, 255, 255, 0.15);
            --sidebar-active: rgba(255, 255, 255, 0.2);
            --sidebar-text: #ffffff;
            --sidebar-width: 280px;
            --transition-speed: 0.3s;
        }

        [data-theme="dark"] {
            /* Dark theme */
            --primary-color: #6366f1;
            --secondary-color: #4f46e5;
            --accent-color: #38bdf8;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --success-color: #10b981;
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --border-color: #334155;
            --sidebar-bg: linear-gradient(to bottom, #312e81, #1e1b4b);
            --box-shadow: 0 10px 15px rgba(0, 0, 0, 0.3);
            --hover-bg: rgba(255, 255, 255, 0.05);
            --card-border: rgba(255, 255, 255, 0.1);
            --icon-opacity: 0.2;
            --sidebar-hover: rgba(255, 255, 255, 0.1);
            --sidebar-active: rgba(255, 255, 255, 0.15);
            --sidebar-text: #f1f5f9;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            transition: background-color 0.3s, color 0.3s, border-color 0.3s, box-shadow 0.3s;
        }

        body {
            margin: 0;
            padding: 0;
            background-color: var(--bg-color);
            color: var(--text-primary);
            overflow-x: hidden;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            align-items: start;
            padding: 2rem 1rem;
            box-sizing: border-box;
            box-shadow: var(--box-shadow);
            z-index: 100;
        }

        .logo-container {
            width: 100%;
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            padding: 0 1.5rem;
        }

        .logo {
            color: var(--sidebar-text);
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .menu-item {
            width: 100%;
            margin: 0.5rem 0;
            padding: 1rem 1.5rem;
            color: var(--sidebar-text);
            text-decoration: none;
            border-radius: 0.75rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all var(--transition-speed) ease;
            opacity: 0.85;
        }

        .menu-item.active {
            background-color: var(--sidebar-active);
            opacity: 1;
            font-weight: 600;
        }

        .menu-item:hover {
            background-color: var(--sidebar-hover);
            opacity: 1;
            transform: translateX(5px);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            width: calc(100% - var(--sidebar-width));
            transition: margin-left var(--transition-speed);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .welcome-message {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .user-welcome {
            font-weight: 400;
            color: var(--text-secondary);
        }

        .theme-switch-container {
            display: flex;
            align-items: center;
            gap: 10px;
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
            font-size: 1.2rem;
            color: var(--text-primary);
        }

        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .card {
            background: var(--card-bg);
            border-radius: 1rem;
            padding: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            transition: all 0.3s ease;
        }

        .card-blue::before {
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }

        .card-red::before {
            background: linear-gradient(90deg, var(--danger-color), #f87171);
        }

        .card-orange::before {
            background: linear-gradient(90deg, var(--warning-color), #fbbf24);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .card h3 {
            color: var(--text-secondary);
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .card p {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 3rem;
            opacity: var(--icon-opacity);
            color: var(--text-primary);
        }

        .exams-card {
            background: var(--card-bg);
            border-radius: 1rem;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: var(--box-shadow);
            border: 1px solid var(--border-color);
            display: none;
        }

        .exams-card h2 {
            color: var(--text-primary);
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .exams-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .exams-table th,
        .exams-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .exams-table th {
            font-weight: 600;
            color: var(--text-primary);
            background-color: var(--hover-bg);
        }

        .exams-table tr:hover {
            background-color: var(--hover-bg);
        }

        .exam-btn {
            background-color: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .exam-btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .delete-btn {
            background-color: var(--danger-color);
            margin-left: 10px;
        }

        .delete-btn:hover {
            background-color: #b91c1c;
        }

        .exam-count {
            background-color: rgba(79, 70, 229, 0.1);
            color: var(--primary-color);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 500;
        }

        .no-exams {
            text-align: center; 
            padding: 2rem; 
            color: var(--text-secondary);
        }

        .menu-toggle {
            display: none;
            cursor: pointer;
            font-size: 1.5rem;
            color: var(--text-primary);
        }

        @media (max-width: 992px) {
            .menu-toggle {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .cards-container {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .theme-switch-container {
                align-self: flex-end;
            }

            .exams-table {
                display: block;
                overflow-x: auto;
            }
        }

        .user-actions {
            margin-top: auto;
            width: 100%;
            padding-top: 2rem;
            border-top: 1px solid var(--sidebar-hover);
        }

        .logout-btn {
            color: var(--sidebar-text);
            opacity: 0.8;
        }

        .logout-btn:hover {
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar" id="sidebar">
            <div class="logo-container">
                <div class="logo"><i class="fas fa-graduation-cap"></i> Exam+</div>
            </div>
            <a href="teacher_dashboard.php" class="menu-item active">
                <i class="fas fa-chart-line"></i>
                <span>Tableau de bord</span>
            </a>
            <a href="create_exam.php" class="menu-item">
                <i class="fas fa-file-alt"></i>
                <span>Créer un Examen</span>
            </a>
            <a href="add_students.php" class="menu-item">
                <i class="fas fa-user-plus"></i>
                <span>Ajouter des Étudiants</span>
            </a>
            <a href="#" class="menu-item" id="correctExamsLink">
                <i class="fas fa-pencil-alt"></i>
                <span>Corriger les Examens</span>
            </a>
            <div class="user-actions">
                <a href="logout.php" class="menu-item logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </div>

        <div class="main-content">
            <div class="header">
                <div>
                    <span class="menu-toggle" id="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </span>
                    <h1 class="welcome-message">Bienvenue, <span class="user-welcome"><?php echo htmlspecialchars($_SESSION['name']); ?></span></h1>
                </div>
                <div class="theme-switch-container">
                    <span class="theme-icon" id="theme-icon">☀️</span>
                    <label class="theme-switch">
                        <input type="checkbox" id="theme-toggle">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="cards-container">
                <div class="card card-blue">
                    <i class="fas fa-users card-icon"></i>
                    <h3>Nombre d'étudiants</h3>
                    <p class="counter" data-target="<?php echo $student_count; ?>">0</p>
                </div>

                <div class="card card-red">
                    <i class="fas fa-chalkboard-teacher card-icon"></i>
                    <h3>Nombre de professeurs</h3>
                    <p class="counter" data-target="<?php echo $teacher_count; ?>">0</p>
                </div>

                <div class="card card-orange">
                    <i class="fas fa-file-alt card-icon"></i>
                    <h3>Nombre d'examens</h3>
                    <p class="counter" data-target="<?php echo $exam_count; ?>">0</p>
                </div>
            </div>

            <div class="exams-card">
                <h2><i class="fas fa-tasks"></i> Examens à Corriger</h2>
                <?php if ($exams_result->num_rows > 0): ?>
                <table class="exams-table">
                    <thead>
                        <tr>
                            <th>Titre de l'examen</th>
                            <th>Date de fin</th>
                            <th>Copies rendues</th>
                            <th>Questions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($exam = $exams_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($exam['title']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($exam['end_time'])); ?></td>
                            <td><span class="exam-count"><?php echo $exam['submitted_count']; ?> copies</span></td>
                            <td><span class="exam-count"><?php echo $exam['question_count']; ?> questions</span></td>
                            <td>
                                <a href="correct_exam.php?exam_id=<?php echo $exam['id']; ?>" class="exam-btn">
                                    <i class="fas fa-check-circle"></i> Corriger
                                </a>
                                <a href="delete_exam.php?exam_id=<?php echo $exam['id']; ?>" class="exam-btn delete-btn" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet examen ?');">
                                    <i class="fas fa-trash-alt"></i> Supprimer
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-exams">
                    <i class="fas fa-clipboard-check fa-3x" style="margin-bottom: 1rem;"></i>
                    <p>Aucun examen à corriger pour le moment.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Animation pour les compteurs
        const counters = document.querySelectorAll('.counter');
        const speed = 200;

        counters.forEach(counter => {
            const animate = () => {
                const value = +counter.getAttribute('data-target');
                const data = +counter.innerText;
                
                const time = value / speed;
                
                if (data < value) {
                    counter.innerText = Math.ceil(data + time);
                    setTimeout(animate, 1);
                } else {
                    counter.innerText = value;
                }
            }
            animate();
        });

        // Afficher/masquer la section des examens
        document.getElementById('correctExamsLink').addEventListener('click', function(e) {
            e.preventDefault();
            const examsCard = document.querySelector('.exams-card');
            examsCard.style.display = examsCard.style.display === 'none' || examsCard.style.display === '' ? 'block' : 'none';
        });

        // Toggle sidebar on mobile
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

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