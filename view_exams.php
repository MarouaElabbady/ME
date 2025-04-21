<?php
session_start();

// Check if user is logged in as student
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "exam_system");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get current date and time
$current_datetime = date('Y-m-d H:i:s');

// Get student ID from session
$student_id = $_SESSION['user_id'];

// Get available exams for the student's group(s)
$query = "SELECT e.*, u.name as teacher_name 
          FROM exams e 
          JOIN users u ON e.teacher_id = u.id 
          JOIN user_groups ug ON e.group_id = ug.group_id 
          WHERE e.start_time <= ? 
          AND e.end_time >= ? 
          AND ug.user_id = ?
          ORDER BY e.start_time ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("ssi", $current_datetime, $current_datetime, $student_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examens Disponibles</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
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
        }

        .container {
            max-width: 1200px;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .page-header h1 {
            font-weight: 700;
            letter-spacing: -1px;
        }

        .breadcrumb {
            background: rgba(255,255,255,0.1);
            padding: 0.5rem 1rem;
            border-radius: 5px;
        }

        .breadcrumb-item a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
        }

        .exam-card {
            border: none;
            border-radius: 15px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }

        .exam-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        .exam-card .card-body {
            padding: 1.5rem;
        }

        .exam-card .card-title {
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .exam-card .list-unstyled li {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }

        .exam-card .list-unstyled i {
            margin-right: 10px;
            color: var(--secondary-color);
            font-size: 1.1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(106,17,203,0.3);
        }

        .alert-info {
            background-color: rgba(106,17,203,0.1);
            border-color: var(--primary-color);
            color: var(--primary-color);
            border-radius: 10px;
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 1rem 0;
            }
            .page-header h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="container">
            <div class="row mb-4">
                <div class="col">
                    <h1 class="display-4">Examens Disponibles</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                            <li class="breadcrumb-item active text-white">Examens Disponibles</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-4">
        <div class="row">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($exam = $result->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card exam-card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($exam['title']); ?></h5>
                                <p class="card-text text-muted">
                                    <?php if (!empty($exam['description'])): ?>
                                        <?php echo htmlspecialchars($exam['description']); ?>
                                    <?php endif; ?>
                                </p>
                                <ul class="list-unstyled">
                                    <li><i class="bi bi-clock"></i> Durée: <?php echo htmlspecialchars($exam['duration']); ?> minutes</li>
                                    <li><i class="bi bi-trophy"></i> Points: <?php echo htmlspecialchars($exam['total_points']); ?></li>
                                    <li><i class="bi bi-person"></i> Professeur: <?php echo htmlspecialchars($exam['teacher_name']); ?></li>
                                    <li><i class="bi bi-calendar-check"></i> Début: <?php echo date('d/m/Y H:i', strtotime($exam['start_time'])); ?></li>
                                    <li><i class="bi bi-calendar-x"></i> Fin: <?php echo date('d/m/Y H:i', strtotime($exam['end_time'])); ?></li>
                                </ul>
                                
                                <?php
                                // Check if student has already attempted this exam
                                $attempt_query = "SELECT COUNT(*) as attempts FROM exam_students 
                                                WHERE exam_id = ? AND student_id = ?";
                                $attempt_stmt = $conn->prepare($attempt_query);
                                $student_id = $_SESSION['user_id'];
                                $attempt_stmt->bind_param("ii", $exam['id'], $student_id);
                                $attempt_stmt->execute();
                                $attempt_result = $attempt_stmt->get_result();
                                $attempts = $attempt_result->fetch_assoc()['attempts'];
                                ?>

                                <?php if ($attempts < $exam['attempts']): ?>
                                    <a href="take_exam.php?exam_id=<?php echo $exam['id']; ?>" 
                                       class="btn btn-primary mt-3 w-100">
                                        Commencer l'examen
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary mt-3 w-100" disabled>
                                        Nombre maximum de tentatives atteint
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col">
                    <div class="alert alert-info" role="alert">
                        Aucun examen n'est disponible pour le moment.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>