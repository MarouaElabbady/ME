<?php
session_start();

// Vérifier si l'utilisateur est connecté et est un enseignant
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'teacher') {
    header("Location: login.php"); // Rediriger vers la page de login
    exit();
}

// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "exam_system");

if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

// Récupérer l'ID de l'enseignant connecté
$teacher_id = $_SESSION['user_id'];

// Récupérer tous les examens créés par cet enseignant
$exams_query = "SELECT e.*, g.name as group_name, 
                (SELECT COUNT(*) FROM exam_students WHERE exam_id = e.id) as student_count,
                (SELECT COUNT(*) FROM exam_students WHERE exam_id = e.id AND status = 'completed') as completed_count
                FROM exams e 
                LEFT JOIN groups g ON e.group_id = g.id
                WHERE e.teacher_id = ?
                ORDER BY e.start_time DESC";

$stmt = $conn->prepare($exams_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$exams_result = $stmt->get_result();

// Pour permettre de filtrer par groupe
$groups_query = "SELECT * FROM groups";
$groups_result = $conn->query($groups_query);
$groups = [];
while ($group = $groups_result->fetch_assoc()) {
    $groups[$group['id']] = $group['name'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Examens | Exam+</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #4338ca;
            --accent-color: #00d4ff;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --surface: #ffffff;
            --input-bg: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --error-color: #ef4444;
            --warning-color: #f59e0b;
            --success-color: #10b981;
            --border: #e2e8f0;
            --feature-card-bg: rgba(255, 255, 255, 0.05);
            --feature-card-border: rgba(255, 255, 255, 0.1);
            --navbar-bg: rgba(15, 23, 42, 0.8);
            --hero-overlay: linear-gradient(135deg, rgba(79, 70, 229, 0.7), rgba(15, 23, 42, 0.9));
        }

        [data-theme="dark"] {
            --primary-color: #4f46e5;
            --secondary-color: #4338ca;
            --accent-color: #00d4ff;
            --dark-bg: #0f172a;
            --card-bg: #1e293b;
            --surface: #1e293b;
            --input-bg: #0f172a;
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --error-color: #ef4444;
            --warning-color: #f59e0b;
            --success-color: #10b981;
            --border: #334155;
            --feature-card-bg: rgba(255, 255, 255, 0.03);
            --feature-card-border: rgba(255, 255, 255, 0.05);
            --navbar-bg: rgba(15, 23, 42, 0.95);
            --hero-overlay: linear-gradient(135deg, rgba(67, 56, 202, 0.8), rgba(15, 23, 42, 0.95));
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }

        body {
            min-height: 100vh;
            background-color: var(--dark-bg);
            color: var(--text-primary);
            position: relative;
            overflow-x: hidden;
        }

        .page-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('https://source.unsplash.com/random/1920x1080/?classroom,education,test');
            background-size: cover;
            background-position: center;
            filter: brightness(0.3);
            z-index: -2;
        }

        .page-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--hero-overlay);
            z-index: -1;
        }

        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 10;
            background: var(--navbar-bg);
            backdrop-filter: blur(10px);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent-color);
            text-decoration: none;
        }

        .logo span {
            color: var(--text-primary);
        }

        .nav-links {
            display: flex;
            gap: 2rem;
        }

        .nav-links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-links a:hover {
            color: var(--accent-color);
        }

        .auth-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .theme-toggle {
            background: transparent;
            border: none;
            color: var(--accent-color);
            font-size: 1.2rem;
            cursor: pointer;
            margin-right: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(to right, var(--accent-color), var(--primary-color));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 212, 255, 0.3);
        }

        .btn-secondary {
            background: transparent;
            border: 2px solid var(--accent-color);
            color: var(--accent-color);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 212, 255, 0.2);
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 100px auto 40px;
            padding: 0 1rem;
        }

        .content-container {
            background-color: var(--surface);
            padding: 2.5rem;
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
            border: 1px solid var(--feature-card-border);
        }

        .container-glow {
            position: absolute;
            width: 100%;
            height: 6px;
            background: linear-gradient(to right, var(--accent-color), var(--primary-color));
            top: 0;
            left: 0;
            border-radius: 1.5rem 1.5rem 0 0;
        }

        h2 {
            font-size: 2.25rem;
            font-weight: 800;
            margin-bottom: 2.5rem;
            background: linear-gradient(to right, var(--text-primary), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: center;
        }

        .floating-shape {
            position: absolute;
            background: linear-gradient(135deg, var(--accent-color), var(--primary-color));
            border-radius: 50%;
            filter: blur(50px);
            opacity: 0.1;
            z-index: -1;
        }

        .shape-1 {
            width: 300px;
            height: 300px;
            top: 10%;
            right: -150px;
            animation: float 20s infinite alternate ease-in-out;
        }

        .shape-2 {
            width: 200px;
            height: 200px;
            bottom: 10%;
            left: -100px;
            animation: float 15s infinite alternate-reverse ease-in-out;
        }

        @keyframes float {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(50px, 50px) rotate(180deg); }
        }

        .filters {
            display: flex;
            flex-direction: column;
            margin-bottom: 2rem;
            gap: 1rem;
        }

        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            width: 100%;
        }

        .filter-group select {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid var(--border);
            background-color: var(--input-bg);
            color: var(--text-primary);
            flex: 1;
            min-width: 150px;
        }

        .search-box {
            display: flex;
            align-items: center;
            background-color: var(--input-bg);
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            border: 1px solid var(--border);
            flex: 1;
        }

        .search-box input {
            background: transparent;
            border: none;
            padding: 0.5rem;
            color: var(--text-primary);
            width: 100%;
        }

        .search-box input:focus {
            outline: none;
        }

        .search-icon {
            color: var(--text-secondary);
            margin-right: 0.5rem;
        }

        .exams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .exam-card {
            background-color: var(--input-bg);
            border-radius: 1rem;
            overflow: hidden;
            position: relative;
            border: 1px solid var(--border);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .exam-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .exam-card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            position: relative;
        }

        .exam-card-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(to right, var(--accent-color), var(--primary-color));
        }

        .exam-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .exam-group {
            font-size: 0.875rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .exam-card-body {
            padding: 1.5rem;
        }

        .exam-dates, .exam-stats {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .date-item, .stat-item {
            display: flex;
            justify-content: space-between;
            font-size: 0.875rem;
        }

        .date-label, .stat-label {
            color: var(--text-secondary);
        }

        .date-value, .stat-value {
            color: var(--text-primary);
            font-weight: 500;
        }

        .exam-progress {
            margin-top: 1rem;
            position: relative;
        }

        .progress-bar {
            height: 8px;
            background-color: var(--border);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(to right, var(--accent-color), var(--primary-color));
            border-radius: 4px;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }

        .exam-card-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .status-upcoming {
            background-color: rgba(79, 70, 229, 0.1);
            color: var(--primary-color);
        }

        .status-completed {
            background-color: rgba(100, 116, 139, 0.1);
            color: var(--text-secondary);
        }

        .no-exams {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }

        .no-exams-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .no-exams-text {
            font-size: 1.25rem;
            margin-bottom: 2rem;
        }

        .action-btn {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--card-bg);
            color: var(--text-primary);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .exam-actions {
            display: flex;
            gap: 0.5rem;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
            gap: 0.5rem;
        }

        .page-item {
            min-width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
            background-color: var(--input-bg);
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1px solid var(--border);
        }

        .page-item.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .page-item:hover:not(.active) {
            background-color: var(--border);
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            .nav-links, .auth-buttons {
                flex-direction: column;
                gap: 0.5rem;
                width: 100%;
                align-items: center;
            }

            .content-container {
                padding: 1.5rem;
                margin-top: 150px;
            }

            .exams-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Background Elements -->
    <div class="page-bg"></div>
    <div class="page-overlay"></div>
    
    <!-- Navigation Bar -->
    <nav class="navbar">
        <a href="index.php" class="logo">Exam<span>+</span></a>
        <div class="nav-links">
            <a href="Teacher_dashboard.php">Tableau de bord</a>
            <a href="exams_list.php" class="active">Examens</a>
            <a href="groups_list.php">Groupes</a>
        </div>
        <div class="auth-buttons">
            <button id="themeToggle" class="theme-toggle">🌙</button>
            <a href="create_exam.php" class="btn btn-primary">+ Créer un examen</a>
        </div>
    </nav>

    <!-- Floating Shapes -->
    <div class="floating-shape shape-1"></div>
    <div class="floating-shape shape-2"></div>

    <!-- Main Content -->
    <div class="container">
        <div class="content-container">
            <div class="container-glow"></div>
            <h2>Liste des Examens</h2>

            <!-- Filters - Disposition améliorée -->
            <div class="filters">
                <div class="filter-group">
                    <select id="groupFilter">
                        <option value="">Tous les groupes</option>
                        <?php foreach ($groups as $id => $name): ?>
                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="statusFilter">
                        <option value="">Tous les statuts</option>
                        <option value="active">En cours</option>
                        <option value="upcoming">À venir</option>
                        <option value="completed">Terminé</option>
                    </select>
                </div>
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchExam" placeholder="Rechercher par titre, groupe...">
                </div>
            </div>

            <?php if ($exams_result->num_rows > 0): ?>
                <!-- Exams Grid -->
                <div class="exams-grid">
                    <?php 
                    $current_date = date('Y-m-d H:i:s');
                    while ($exam = $exams_result->fetch_assoc()): 
                        // Déterminer le statut de l'examen
                        $status = 'upcoming';
                        $status_text = 'À venir';
                        $status_class = 'status-upcoming';
                        
                        if ($exam['start_time'] <= $current_date && $exam['end_time'] >= $current_date) {
                            $status = 'active';
                            $status_text = 'En cours';
                            $status_class = 'status-active';
                        } elseif ($exam['end_time'] < $current_date) {
                            $status = 'completed';
                            $status_text = 'Terminé';
                            $status_class = 'status-completed';
                        }
                        
                        // Calculer le pourcentage de complétion
                        $completion_percent = 0;
                        if ($exam['student_count'] > 0) {
                            $completion_percent = ($exam['completed_count'] / $exam['student_count']) * 100;
                        }
                    ?>
                    <div class="exam-card" data-group="<?php echo $exam['group_id']; ?>" data-status="<?php echo $status; ?>" data-title="<?php echo htmlspecialchars($exam['title']); ?>" data-group-name="<?php echo htmlspecialchars($exam['group_name'] ?? 'Aucun groupe'); ?>">
                        <div class="exam-card-header">
                            <h3 class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></h3>
                            <div class="exam-group">
                                <i class="fas fa-users"></i>
                                <span><?php echo htmlspecialchars($exam['group_name'] ?? 'Aucun groupe'); ?></span>
                            </div>
                        </div>
                        <div class="exam-card-body">
                            <div class="exam-dates">
                                <div class="date-item">
                                    <span class="date-label">Début:</span>
                                    <span class="date-value"><?php echo date('d/m/Y H:i', strtotime($exam['start_time'])); ?></span>
                                </div>
                                <div class="date-item">
                                    <span class="date-label">Fin:</span>
                                    <span class="date-value"><?php echo date('d/m/Y H:i', strtotime($exam['end_time'])); ?></span>
                                </div>
                                <div class="date-item">
                                    <span class="date-label">Durée:</span>
                                    <span class="date-value"><?php echo $exam['duration']; ?> min</span>
                                </div>
                            </div>
                            <div class="exam-stats">
                                <div class="stat-item">
                                    <span class="stat-label">Points:</span>
                                    <span class="stat-value"><?php echo $exam['total_points']; ?> pts</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Tentatives:</span>
                                    <span class="stat-value"><?php echo $exam['attempts']; ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Participants:</span>
                                    <span class="stat-value"><?php echo $exam['student_count']; ?> étudiants</span>
                                </div>
                            </div>
                            <div class="exam-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $completion_percent; ?>%"></div>
                                </div>
                                <div class="progress-label">
                                    <span>Complété: <?php echo $exam['completed_count']; ?>/<?php echo $exam['student_count']; ?></span>
                                    <span><?php echo round($completion_percent); ?>%</span>
                                </div>
                            </div>
                        </div>
                        <div class="exam-card-footer">
                            <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                            <div class="exam-actions">
                                <a href="view_exam_results.php?id=<?php echo $exam['id']; ?>" class="action-btn" title="Voir les résultats">
                                    <i class="fas fa-chart-bar"></i>
                                </a>
                                <a href="edit_exam.php?id=<?php echo $exam['id']; ?>" class="action-btn" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="duplicate_exam.php?id=<?php echo $exam['id']; ?>" class="action-btn" title="Dupliquer">
                                    <i class="fas fa-copy"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                
                <!-- Pagination -->
                <div class="pagination">
                    <a href="#" class="page-item"><i class="fas fa-angle-double-left"></i></a>
                    <a href="#" class="page-item active">1</a>
                    <a href="#" class="page-item">2</a>
                    <a href="#" class="page-item">3</a>
                    <a href="#" class="page-item"><i class="fas fa-angle-double-right"></i></a>
                </div>
            <?php else: ?>
                <!-- No Exams Message -->
                <div class="no-exams">
                    <div class="no-exams-icon"><i class="fas fa-file-alt"></i></div>
                    <h3 class="no-exams-text">Vous n'avez pas encore créé d'examens</h3>
                    <a href="create_exam.php" class="btn btn-primary">Créer votre premier examen</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Filtrage des examens
        document.getElementById('groupFilter').addEventListener('change', filterExams);
        document.getElementById('statusFilter').addEventListener('change', filterExams);
        document.getElementById('searchExam').addEventListener('input', filterExams);

        function filterExams() {
            const groupFilter = document.getElementById('groupFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const searchFilter = document.getElementById('searchExam').value.toLowerCase();
            
            const examCards = document.querySelectorAll('.exam-card');
            
            examCards.forEach(card => {
                const group = card.dataset.group;
                const status = card.dataset.status;
                const title = card.dataset.title.toLowerCase();
                const groupName = card.dataset.groupName.toLowerCase();
                
                let isVisible = true;
                
                if (groupFilter && group !== groupFilter) {
                    isVisible = false;
                }
                
                if (statusFilter && status !== statusFilter) {
                    isVisible = false;
                }
                
                // Recherche plus complète (titre ou groupe)
                if (searchFilter && !(title.includes(searchFilter) || groupName.includes(searchFilter))) {
                    isVisible = false;
                }
                
                card.style.display = isVisible ? 'block' : 'none';
            });
            
            // Afficher un message si aucun résultat n'est trouvé
            const visibleCards = Array.from(document.querySelectorAll('.exam-card')).filter(card => card.style.display !== 'none');
            const noResultsMsg = document.getElementById('noResultsMessage');
            const examsGrid = document.querySelector('.exams-grid');
            
            if (visibleCards.length === 0) {
                if (!noResultsMsg) {
                    const message = document.createElement('div');
                    message.id = 'noResultsMessage';
                    message.className = 'no-exams';
                    message.innerHTML = `
                        <div class="no-exams-icon"><i class="fas fa-search"></i></div>
                        <h3 class="no-exams-text">Aucun examen ne correspond à vos critères</h3>
                        <button class="btn btn-secondary" onclick="resetFilters()">Réinitialiser les filtres</button>
                    `;
                    examsGrid.insertAdjacentElement('afterend', message);
                }
            } else if (noResultsMsg) {
                noResultsMsg.remove();
            }
        }
        
        // Fonction pour réinitialiser les filtres
        function resetFilters() {
            document.getElementById('groupFilter').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('searchExam').value = '';
            filterExams();
        }

        // Fonctionnalité de thème sombre/clair
        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('click', function() {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            themeToggle.innerHTML = newTheme === 'light' ? '🌙' : '☀️';
            
            // Enregistrer la préférence dans le localStorage
            localStorage.setItem('theme', newTheme);
        });

        // Appliquer le thème enregistré au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            themeToggle.innerHTML = savedTheme === 'light' ? '🌙' : '☀️';
        });
    </script
</body>
</html>