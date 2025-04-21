<?php
session_start();

// Vérification de la connexion
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "exam_system");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Vérifier si l'ID de l'examen est fourni
if (!isset($_GET['exam_id'])) {
    header("Location: view_exams.php");
    exit();
}

$exam_id = $_GET['exam_id'];
$student_id = $_SESSION['user_id'];

// Récupérer les informations de l'examen
$exam_query = "SELECT * FROM exams WHERE id = ?";
$exam_stmt = $conn->prepare($exam_query);
$exam_stmt->bind_param("i", $exam_id);
$exam_stmt->execute();
$exam_result = $exam_stmt->get_result();
$exam = $exam_result->fetch_assoc();

if (!$exam) {
    header("Location: view_exams.php");
    exit();
}

// Vérifier si l'étudiant a déjà commencé cet examen
$attempt_query = "SELECT * FROM exam_students 
                 WHERE exam_id = ? AND student_id = ?";
$attempt_stmt = $conn->prepare($attempt_query);
$attempt_stmt->bind_param("ii", $exam_id, $student_id);
$attempt_stmt->execute();
$attempt_result = $attempt_stmt->get_result();

// Si c'est un nouveau départ d'examen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_exam'])) {
    $start_time = date('Y-m-d H:i:s');
    $end_time = date('Y-m-d H:i:s', strtotime("+{$exam['duration']} minutes"));
    
    $insert_query = "INSERT INTO exam_students (exam_id, student_id, start_time, end_time) 
                     VALUES (?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("iiss", $exam_id, $student_id, $start_time, $end_time);
    $insert_stmt->execute();
    
    header("Location: exam_questions.php?exam_id=" . $exam_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commencer l'examen</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #6a11cb;
            --secondary-color: #2575fc;
            --light-bg: #f4f6f9;
            --text-color: #333;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .exam-start-container {
            max-width: 600px;
            width: 100%;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
            text-align: center;
        }

        .card-header h3 {
            margin-bottom: 0;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .card-body {
            padding: 2rem;
        }

        .exam-info {
            background-color: rgba(106, 17, 203, 0.05);
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .exam-info p {
            margin-bottom: 0.5rem;
            color: var(--text-color);
        }

        .alert-info {
            background-color: rgba(106, 17, 203, 0.1);
            border-color: var(--primary-color);
            color: var(--text-color);
            border-radius: 10px;
        }

        .alert-info h4 {
            color: var(--primary-color);
        }

        .alert-info ul {
            padding-left: 1.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(106, 17, 203, 0.3);
        }

        @media (max-width: 768px) {
            .card {
                margin: 1rem;
            }
            .card-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="exam-start-container">
        <div class="card shadow">
            <div class="card-header">
                <h3><?php echo htmlspecialchars($exam['title']); ?></h3>
            </div>
            <div class="card-body">
                <div class="exam-info">
                    <p><i class="bi bi-info-circle me-2 text-primary"></i><strong>Description:</strong> <?php echo htmlspecialchars($exam['description']); ?></p>
                    <p><i class="bi bi-clock me-2 text-primary"></i><strong>Durée:</strong> <?php echo htmlspecialchars($exam['duration']); ?> minutes</p>
                    <p><i class="bi bi-trophy me-2 text-primary"></i><strong>Points totaux:</strong> <?php echo htmlspecialchars($exam['total_points']); ?></p>
                </div>

                <div class="alert alert-info">
                    <h4 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>Instructions:</h4>
                    <ul>
                        <li>Vous avez <?php echo htmlspecialchars($exam['duration']); ?> minutes pour compléter l'examen</li>
                        <li>Une fois commencé, le chronomètre ne peut pas être arrêté</li>
                        <li>Assurez-vous d'avoir une connexion internet stable</li>
                        <li>Ne fermez pas votre navigateur pendant l'examen</li>
                    </ul>
                </div>

                <form method="POST" class="text-center">
                    <input type="hidden" name="start_exam" value="1">
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-play-circle me-2"></i>Commencer l'examen
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>