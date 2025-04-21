<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "exam_system");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Vérifier si l'exam_id est fourni
if (!isset($_GET['exam_id'])) {
    header("Location: view_exams.php");
    exit();
}

$exam_id = $_GET['exam_id'];
$student_id = $_SESSION['user_id'];

// Récupérer les détails de l'examen
$exam_query = "SELECT e.*, es.start_time, es.end_time 
               FROM exams e 
               LEFT JOIN exam_students es ON e.id = es.exam_id AND es.student_id = ? 
               WHERE e.id = ?";
$exam_stmt = $conn->prepare($exam_query);
$exam_stmt->bind_param("ii", $student_id, $exam_id);
$exam_stmt->execute();
$exam_result = $exam_stmt->get_result();
$exam = $exam_result->fetch_assoc();

// Vérifier si l'examen existe
if (!$exam) {
    header("Location: view_exams.php");
    exit();
}

// Récupérer la durée de l'examen
$duration = $exam['duration'];

// Récupérer toutes les questions pour cet examen
$questions_query = "SELECT * FROM questions WHERE exam_id = ? ORDER BY id";
$questions_stmt = $conn->prepare($questions_query);
$questions_stmt->bind_param("i", $exam_id);
$questions_stmt->execute();
$questions_result = $questions_stmt->get_result();

// Stocker les questions dans un tableau
$questions = [];
while ($question = $questions_result->fetch_assoc()) {
    // Si c'est une question QCM, récupérer les choix
    if ($question['question_type'] == 'qcm') {
        $choices_query = "SELECT * FROM choices WHERE question_id = ? ORDER BY id";
        $choices_stmt = $conn->prepare($choices_query);
        $choices_stmt->bind_param("i", $question['id']);
        $choices_stmt->execute();
        $choices_result = $choices_stmt->get_result();
        
        // Récupérer tous les choix
        $choices = [];
        while ($choice = $choices_result->fetch_assoc()) {
            $choices[] = $choice;
        }
        
        // Ajouter les choix à la question
        $question['choices'] = $choices;
    } else {
        // Pour les questions non QCM, initialiser un tableau vide
        $question['choices'] = [];
    }
    
    // Ajouter la question au tableau
    $questions[] = $question;
}

// Vérifier si l'étudiant a déjà commencé l'examen
//$check_started = "SELECT * FROM exam_students WHERE exam_id = ? AND student_id = ?";
$started_stmt = $conn->prepare($check_started);
$started_stmt->bind_param("ii", $exam_id, $student_id);
$started_stmt->execute();
$started_result = $started_stmt->get_result();
$check_started=$choice->get_result();
$choices_result->current_field()>var_dump

// Si l'étudiant n'a pas encore commencé l'examen, enregistrer l'heure de début if ($started_result->num_rows == 0) {
    $start_exam = "INSERT INTO exam_students (exam_id, student_id, start_time, status) VALUES (?, ?, NOW(), 'pending')";
    $start_stmt = $conn->prepare($start_exam);
    $start_stmt->bind_param("ii", $exam_id, $student_id);
    $start_stmt->execute();
} else {
    $exam_status = $started_result->fetch_assoc();
    // Si l'examen est déjà terminé, rediriger
    if ($exam_status['status'] == 'completed') {
        header("Location: student_dashboard.php?message=exam_already_completed");
        exit();
    }
}
// si l etudient ('none ') do not have any exame if else do you have exame 
?>guieujncnnejkkwhkr j;sd;nhhfbd
<div class="accordion" id="accordion #34495e "name ="acordion" accesskey="label #1">
<div class="accordion-item">
    <input type="text" value="name ">
    <php> 
        <form action="post">
            <label for="type #1"></label>
        </form>
    </php>
</div>
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingOne">
            <button
                class="accordion-button"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#collapseOne"
                aria-expanded="true"
                aria-controls="collapseOne"
            >
                Accordion Item #1
            </button>
        </h2>
        <div
            id="collapseOne"
            class="accordion-collapse collapse show"
            aria-labelledby="headingOne"
            data-bs-parent="#accordion #34495e "name ="acordion" accesskey="label #1"

            >
            <div class="accordion-body">
                This is the first item's accordion body.
            </div>
        </div>
    </div>
    
</div>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questions d'examen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #34495e;
            --background-color: #f4f6f7;
        }

        body {
            background-color: var(--background-color);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .exam-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .exam-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .exam-header {
            background: linear-gradient(135deg, var(--primary-color), #6a11cb);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .timer {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            display: flex;
            align-items: center;
        }

        .timer i {
            margin-right: 10px;
        }

        .question-card {
            margin-bottom: 1.5rem;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .question-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .question-header {
            background-color: var(--secondary-color);
            color: white;
            padding: 1rem;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-finish {
            background: linear-gradient(135deg, var(--primary-color), #6a11cb);
            border: none;
            padding: 12px 30px;
            font-weight: bold;
            letter-spacing: 1px;
            transition: transform 0.2s;
        }

        .btn-finish:hover {
            transform: scale(1.05);
            background: linear-gradient(135deg, #6a11cb, var(--primary-color));
        }

        .no-questions-alert {
            background-color: #e9ecef;
            border-left: 5px solid var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="container exam-container">
        <div class="row">
            <div class="col-12">
                <div class="card exam-card">
                    <div class="exam-header">
                        <h3 class="card-title mb-0">
                            <i class="fas fa-file-alt me-2"></i>
                            Examen: <?php echo htmlspecialchars($exam['title']); ?>
                        </h3>
                        <div class="timer" id="timer">
                            <i class="fas fa-clock"></i>
                            <span>Temps restant: <?php echo $duration; ?>m 0s</span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <form action="correct_exam.php" method="POST" id="examForm">
                            <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                            
                            <?php 
                            if (!empty($questions)):
                                foreach ($questions as $index => $question): 
                            ?>
                                <div class="card question-card">
                                    <div class="question-header">
                                        <h4 class="mb-0">Question <?php echo $index + 1; ?></h4>
                                    </div>
                                    <div class="card-body">
                                        <p class="lead"><?php echo htmlspecialchars($question['question_text']); ?></p>
                                        
                                        <?php if ($question['question_type'] === 'qcm'): ?>
                                            <?php if (!empty($question['choices'])): ?>
                                                <?php foreach ($question['choices'] as $choice): ?>
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" 
                                                               type="radio" 
                                                               name="answers[<?php echo $question['id']; ?>]" 
                                                               id="choice_<?php echo $choice['id']; ?>"
                                                               value="<?php echo $choice['id']; ?>" 
                                                               required>
                                                        <label class="form-check-label" for="choice_<?php echo $choice['id']; ?>">
                                                            <?php echo htmlspecialchars($choice['choice_text']); ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    Aucune option n'a été trouvée pour cette question.
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <textarea class="form-control" 
                                                      name="answers[<?php echo $question['id']; ?>]" 
                                                      rows="3" 
                                                      required></textarea>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php 
                                endforeach;
                            else: 
                            ?>
                                <div class="alert no-questions-alert">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Aucune question n'a été trouvée pour cet examen.
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($questions)): ?>
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-finish">
                                        <i class="fas fa-paper-plane me-2"></i>
                                        Terminer l'examen
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Timer pour l'examen
        const duration = <?php echo $duration; ?>;
        const totalTime = duration * 60;
        let timeLeft = totalTime;

        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;

            const timerElement = document.getElementById('timer');
            timerElement.innerHTML = `
                <i class="fas fa-clock"></i>
                <span>Temps restant: ${minutes}m ${seconds}s</span>
            `;

            if (timeLeft <= 0) {
                alert("Le temps est écoulé !");
                document.getElementById('examForm').submit();
            } else {
                timeLeft--;
                setTimeout(updateTimer, 1000);
            }
        }

        // Démarrer le timer
        updateTimer();

        // Empêcher la fermeture accidentelle de la page
        window.addEventListener('beforeunload', function(e) {
            e.preventDefault();
            e.returnValue = 'Vous êtes sur le point de quitter l\'examen. Vos réponses pourraient ne pas être enregistrées.';
        });
    </script>
</body>
</html>
</head>
<body>
    