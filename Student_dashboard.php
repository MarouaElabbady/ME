<?php 
session_start(); 
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'student') { 
    header("Location: login.php");
    exit();
}

// Établir la connexion à la base de données
$conn = new mysqli("localhost", "root", "", "exam_system");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Récupérer l'ID de l'étudiant connecté
$student_id = $_SESSION['user_id'];

// Récupérer les résultats des examens de l'étudiant avec points réels
$query = "SELECT e.id, e.title, es.score, es.status, es.end_time, e.total_points,
          (SELECT SUM(qs.score) FROM question_scores qs WHERE qs.exam_id = e.id AND qs.student_id = es.student_id) as actual_score
          FROM exam_students es 
          JOIN exams e ON es.exam_id = e.id 
          WHERE es.student_id = ? 
          ORDER BY es.end_time DESC 
          LIMIT 5";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

// Stocker les résultats dans un tableau
$exam_results = [];
while ($row = $result->fetch_assoc()) {
    $exam_results[] = $row;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Étudiant</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: #f4f7fa;
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            width: 250px;
            background-color: white;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: linear-gradient(to right, #4e54c8, #8f94fb);
            color: white;
        }

        .logo {
            font-size: 20px;
            font-weight: bold;
        }

        .user-profile {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .user-icon {
            width: 50px;
            height: 50px;
            background-color: #6a5acd;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-weight: bold;
            margin-right: 15px;
        }

        .user-info h2 {
            font-size: 16px;
            margin-bottom: 5px;
        }

        .user-info p {
            font-size: 12px;
            color: rgba(0,0,0,0.5);
        }

        .menu-list {
            list-style: none;
            flex-grow: 1;
        }

        .menu-list li {
            border-bottom: 1px solid #f0f0f0;
        }

        .menu-list li:last-child {
            border-bottom: none;
        }

        .menu-list a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            text-decoration: none;
            color: #333;
            transition: background-color 0.3s ease;
        }

        .menu-list a:hover {
            background-color: #f4f7fa;
        }

        .menu-list a i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
            color: #4e54c8;
        }

        .logout-btn {
            padding: 15px;
            background: linear-gradient(to right, #4e54c8, #8f94fb);
            color: white;
            text-align: center;
            font-weight: bold;
            text-decoration: none;
            display: block;
            transition: opacity 0.3s ease;
        }

        .logout-btn:hover {
            opacity: 0.9;
        }

        .main-content {
            flex-grow: 1;
            background-color: #f4f7fa;
            padding: 20px;
            overflow-y: auto;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .dashboard-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
        }

        .card-title {
            font-size: 18px;
            margin-bottom: 15px;
            color: #4e54c8;
        }
        
        /* Styles simplifiés pour les résultats */
        .results-list {
            margin-top: 10px;
        }
        
        .result-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }
        
        .result-item:last-child {
            border-bottom: none;
        }
        
        .result-title {
            font-weight: 500;
            color: #333;
        }
        
        .result-content {
            display: none;
            margin-top: 8px;
        }
        
        .result-note {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 14px;
            color: white;
        }
        
        .note-good {
            background-color: #4CAF50;
        }
        
        .note-average {
            background-color: #FFC107;
        }
        
        .note-poor {
            background-color: #F44336;
        }
        
        .note-pending {
            background-color: #9E9E9E;
        }
        
        .empty-results {
            color: #888;
            font-style: italic;
            text-align: center;
            padding: 10px 0;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">ExamEnLigne</div>
        </div>

        <div class="user-profile">
            <div class="user-icon">
                <?php 
                $initials = strtoupper(substr($_SESSION['name'], 0, 1)); 
                echo htmlspecialchars($initials); 
                ?>
            </div>
            <div class="user-info">
                <h2><?php echo htmlspecialchars($_SESSION['name']); ?></h2>
                <p>Étudiant</p>
            </div>
        </div>
        <ul class="menu-list">
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Tableau de bord</a></li>
            <li><a href="view_exams.php"><i class="fas fa-file-alt"></i> Examens</a></li>
            <li><a href="quiz.php"><i class="fas fa-question-circle"></i> Quiz</a></li>
        </ul>

        <a href="logout.php" class="logout-btn">Se déconnecter</a>
    </div>

    <div class="main-content">
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h2 class="card-title">Mes Examens</h2>
                <p>Consultez vos examens à venir et passés</p>
            </div>
            <div class="dashboard-card">
                <h2 class="card-title">Mes Cours</h2>
                <p>Suivez votre progression académique</p>
            </div>
            
            <div class="dashboard-card">
                <h2 class="card-title">Résultats</h2>
                <?php if (count($exam_results) > 0): ?>
                    <div class="results-list">
                        <?php foreach ($exam_results as $result): ?>
                            <div class="result-item" onclick="toggleResult(<?php echo $result['id']; ?>)">
                                <div class="result-title"><?php echo htmlspecialchars($result['title']); ?></div>
                                <div id="result-content-<?php echo $result['id']; ?>" class="result-content">
                                    <?php if ($result['status'] == 'completed'): ?>
                                        <?php 
                                        $actual_score = $result['actual_score'] ?: 0; // Utiliser 0 si null
                                        $total_points = $result['total_points'];
                                        
                                        $percentage = ($actual_score / $total_points) * 100;
                                        $note_class = 'note-poor';
                                        if ($percentage >= 80) {
                                            $note_class = 'note-good';
                                        } elseif ($percentage >= 60) {
                                            $note_class = 'note-average';
                                        }
                                        ?>
                                        <span>Note: </span>
                                        <span class="result-note <?php echo $note_class; ?>">
                                            <?php echo number_format($actual_score, 1); ?>/<?php echo $total_points; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="result-note note-pending">En attente</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-results">Aucun résultat disponible</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Simple function to toggle result details
        function toggleResult(id) {
            const content = document.getElementById(`result-content-${id}`);
            if (content.style.display === 'block') {
                content.style.display = 'none';
            } else {
                content.style.display = 'block';
            }
        }
    </script>
</body>
</html>