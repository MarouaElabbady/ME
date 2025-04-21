<?php
session_start();

// Vérifier que l'utilisateur est connecté et qu'il est un enseignant
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "exam_system");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Vérifier que les paramètres nécessaires sont présents
if (!isset($_GET['exam_id']) || !isset($_GET['student_id'])) {
    header("Location: teacher_dashboard.php");
    exit();
}

$exam_id = $_GET['exam_id'];
$student_id = $_GET['student_id'];

// Récupérer les informations sur l'examen
$exam_query = "SELECT e.title, e.description, u.name as student_name, e.total_points
               FROM exams e
               JOIN users u ON u.id = ?
               WHERE e.id = ?";
$exam_stmt = $conn->prepare($exam_query);
$exam_stmt->bind_param("ii", $student_id, $exam_id);
$exam_stmt->execute();
$exam_result = $exam_stmt->get_result();

if ($exam_result->num_rows === 0) {
    header("Location: teacher_dashboard.php");
    exit();
}

$exam_info = $exam_result->fetch_assoc();

// Récupérer le score actuel de l'étudiant
$score_query = "SELECT score FROM exam_students 
                WHERE exam_id = ? AND student_id = ? AND status = 'completed'";
$score_stmt = $conn->prepare($score_query);
$score_stmt->bind_param("ii", $exam_id, $student_id);
$score_stmt->execute();
$score_result = $score_stmt->get_result();
$student_score = $score_result->fetch_assoc();

// Récupérer les notes individuelles des questions si elles existent déjà
$question_scores_query = "SELECT question_id, score FROM question_scores 
                          WHERE exam_id = ? AND student_id = ?";
$question_scores_stmt = $conn->prepare($question_scores_query);
$question_scores_stmt->bind_param("ii", $exam_id, $student_id);
$question_scores_stmt->execute();
$question_scores_result = $question_scores_stmt->get_result();

$question_scores = [];
while ($row = $question_scores_result->fetch_assoc()) {
    $question_scores[$row['question_id']] = $row['score'];
}

// Traiter la mise à jour des scores si un formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_scores'])) {
    // Commencer une transaction
    $conn->begin_transaction();
    
    try {
        $total_score = 0;
        $total_possible = 0;
        
        // Parcourir toutes les questions et leurs scores attribués
        foreach ($_POST['question_score'] as $question_id => $score) {
            $points = $_POST['question_points'][$question_id];
            $total_possible += $points;
            
            // Valider le score
            if (!is_numeric($score) || $score < 0 || $score > $points) {
                throw new Exception("Score invalide pour la question ID $question_id. Doit être entre 0 et $points.");
            }
            
            $total_score += $score;
            
            // Vérifier si une note existe déjà pour cette question
            $check_query = "SELECT id FROM question_scores 
                            WHERE exam_id = ? AND student_id = ? AND question_id = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("iii", $exam_id, $student_id, $question_id);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->num_rows > 0;
            
            if ($exists) {
                // Mettre à jour le score existant
                $update_query = "UPDATE question_scores SET score = ? 
                                WHERE exam_id = ? AND student_id = ? AND question_id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("diii", $score, $exam_id, $student_id, $question_id);
                $update_stmt->execute();
            } else {
                // Insérer un nouveau score
                $insert_query = "INSERT INTO question_scores (exam_id, student_id, question_id, score) 
                                VALUES (?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("iiid", $exam_id, $student_id, $question_id, $score);
                $insert_stmt->execute();
            }
        }
        
        // Calculer le pourcentage
        $percentage_score = ($total_score / $total_possible) * 100;
        
        // Mettre à jour le score global dans exam_students
        $update_total_query = "UPDATE exam_students SET score = ? 
                              WHERE exam_id = ? AND student_id = ?";
        $update_total_stmt = $conn->prepare($update_total_query);
        $update_total_stmt->bind_param("dii", $percentage_score, $exam_id, $student_id);
        $update_total_stmt->execute();
        
        // Tout s'est bien passé, on valide la transaction
        $conn->commit();
        $success_message = "Évaluation enregistrée avec succès. Score total: " . number_format($percentage_score, 1) . "%";
        $student_score['score'] = $percentage_score;
        
        // Mettre à jour le tableau des scores
        $question_scores_stmt->execute();
        $question_scores_result = $question_scores_stmt->get_result();
        $question_scores = [];
        while ($row = $question_scores_result->fetch_assoc()) {
            $question_scores[$row['question_id']] = $row['score'];
        }
        
    } catch (Exception $e) {
        // En cas d'erreur, on annule la transaction
        $conn->rollback();
        $error_message = "Erreur: " . $e->getMessage();
    }
}

// Récupérer toutes les questions de l'examen avec les réponses de l'étudiant
$questions_query = "SELECT q.id, q.question_text, q.question_type, qp.points, 
                   GROUP_CONCAT(DISTINCT sa.answer) as student_answers
                   FROM questions q
                   LEFT JOIN question_points qp ON q.id = qp.question_id
                   LEFT JOIN student_answers sa ON q.id = sa.question_id AND sa.student_id = ? AND sa.exam_id = ?
                   WHERE q.exam_id = ?
                   GROUP BY q.id
                   ORDER BY q.id";
$questions_stmt = $conn->prepare($questions_query);
$questions_stmt->bind_param("iii", $student_id, $exam_id, $exam_id);
$questions_stmt->execute();
$questions_result = $questions_stmt->get_result();

// Tableau pour stocker toutes les questions et réponses
$questions = [];
$total_points = 0;

while ($question = $questions_result->fetch_assoc()) {
    // Ajouter le score actuel de cette question si disponible
    $question['current_score'] = isset($question_scores[$question['id']]) ? $question_scores[$question['id']] : 0;
    
    // Ajouter le nombre de points au total
    $total_points += $question['points'];
    
    // Pour les questions QCM, récupérer tous les choix
    if ($question['question_type'] === 'qcm') {
        $choices_query = "SELECT c.id, c.choice_text, c.is_correct 
                         FROM choices c 
                         WHERE c.question_id = ?
                         ORDER BY c.id";
        $choices_stmt = $conn->prepare($choices_query);
        $choices_stmt->bind_param("i", $question['id']);
        $choices_stmt->execute();
        $choices_result = $choices_stmt->get_result();
        
        $choices = [];
        while ($choice = $choices_result->fetch_assoc()) {
            $choices[] = $choice;
        }
        
        $question['choices'] = $choices;
        
        // Convertir la chaîne des réponses en tableau
        if ($question['student_answers']) {
            $question['student_answers'] = explode(',', $question['student_answers']);
        } else {
            $question['student_answers'] = [];
        }
    }
    
    $questions[] = $question;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Évaluation des réponses de l'étudiant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .correct-answer {
            color: #28a745;
            font-weight: bold;
        }
        .incorrect-answer {
            color: #dc3545;
            text-decoration: line-through;
        }
        .student-answer {
            background-color: #f8f9fa;
            border-left: 4px solid #6c757d;
            padding: 10px 15px;
            margin: 10px 0;
        }
        .student-answer.correct {
            border-left-color: #28a745;
        }
        .student-answer.incorrect {
            border-left-color: #dc3545;
        }
        .question-card {
            margin-bottom: 1.5rem;
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .question-header {
            background-color: #34495e;
            color: white;
            padding: 12px 15px;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .badge-points {
            font-size: 14px;
            background-color: rgba(255,255,255,0.2);
            padding: 5px 10px;
            border-radius: 20px;
        }
        .score-input {
            max-width: 100px;
        }
        .score-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #17a2b8;
        }
        .total-score {
            font-size: 1.5rem;
            font-weight: bold;
            color: #17a2b8;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2>
                    <i class="fas fa-clipboard-check me-2"></i>
                    Évaluation des réponses
                </h2>
                <h5 class="text-muted">
                    <?php echo htmlspecialchars($exam_info['title']); ?> - 
                    <?php echo htmlspecialchars($exam_info['student_name']); ?>
                </h5>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="correct_exam.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Retour aux résultats
                </a>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="card-title">Score actuel</h5>
                        <h3 id="current-percentage"><?php echo isset($student_score['score']) ? number_format($student_score['score'], 1) : "0"; ?>%</h3>
                        <p class="text-muted">
                            <span id="total-obtained">0</span> sur <span id="total-possible"><?php echo $total_points; ?></span> points
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" id="evaluation-form">
            <?php foreach ($questions as $index => $question): ?>
            <div class="card question-card">
                <div class="question-header d-flex justify-content-between">
                    <h5 class="mb-0">Question <?php echo $index + 1; ?></h5>
                    <span class="badge-points">
                        <i class="fas fa-star me-1"></i>
                        <?php echo isset($question['points']) ? $question['points'] : 0; ?> points
                    </span>
                </div>
                <div class="card-body">
                    <div class="question-text mb-3">
                        <strong>Question:</strong>
                        <p><?php echo htmlspecialchars($question['question_text']); ?></p>
                    </div>

                    <?php if ($question['question_type'] === 'qcm'): ?>
                        <div class="choices-section mb-3">
                            <strong>Choix disponibles:</strong>
                            <ul class="list-group mt-2">
                                <?php foreach ($question['choices'] as $choice): ?>
                                    <li class="list-group-item <?php echo $choice['is_correct'] ? 'list-group-item-success' : ''; ?>">
                                        <?php if ($choice['is_correct']): ?>
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                        <?php else: ?>
                                            <i class="fas fa-circle me-2 text-secondary"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($choice['choice_text']); ?>
                                        
                                        <?php if (in_array($choice['id'], $question['student_answers'])): ?>
                                            <span class="float-end badge bg-primary">Sélectionné par l'étudiant</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <div class="student-answer mb-3 <?php 
                            $all_correct = true;
                            $student_correct_count = 0;
                            $total_correct_count = 0;
                            
                            foreach ($question['choices'] as $choice) {
                                if ($choice['is_correct']) {
                                    $total_correct_count++;
                                    if (in_array($choice['id'], $question['student_answers'])) {
                                        $student_correct_count++;
                                    } else {
                                        $all_correct = false;
                                    }
                                } else if (in_array($choice['id'], $question['student_answers'])) {
                                    $all_correct = false;
                                }
                            }
                            
                            // Vérifie si la réponse est correcte ou partiellement correcte
                            echo ($all_correct && $student_correct_count == $total_correct_count) ? 'correct' : 'incorrect';
                        ?>">
                            <strong>Évaluation:</strong>
                            <?php if ($all_correct && $student_correct_count == $total_correct_count): ?>
                                <div class="text-success mt-2">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Réponse correcte
                                </div>
                            <?php elseif ($student_correct_count > 0): ?>
                                <div class="text-warning mt-2">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Réponse partiellement correcte (<?php echo $student_correct_count; ?> sur <?php echo $total_correct_count; ?> bonnes réponses)
                                </div>
                            <?php else: ?>
                                <div class="text-danger mt-2">
                                    <i class="fas fa-times-circle me-2"></i>
                                    Réponse incorrecte
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="open-answer mb-3">
                            <strong>Réponse de l'étudiant:</strong>
                            <div class="student-answer mt-2">
                                <?php 
                                if (isset($question['student_answers']) && !empty($question['student_answers'])) {
                                    echo nl2br(htmlspecialchars($question['student_answers']));
                                } else {
                                    echo '<em class="text-muted">Aucune réponse fournie</em>';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Commentaire de l'enseignant:</strong>
                            <textarea name="comment[<?php echo $question['id']; ?>]" class="form-control mt-2" rows="3" placeholder="Ajouter un commentaire sur cette réponse..."></textarea>
                        </div>
                    <?php endif; ?>
                    
                    <div class="score-container">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <strong>Attribution de points:</strong>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input 
                                        type="number" 
                                        name="question_score[<?php echo $question['id']; ?>]" 
                                        class="form-control score-input question-score" 
                                        value="<?php echo $question['current_score']; ?>" 
                                        min="0" 
                                        max="<?php echo $question['points']; ?>" 
                                        step="0.5" 
                                        data-max="<?php echo $question['points']; ?>"
                                        data-question-id="<?php echo $question['id']; ?>"
                                        required
                                    >
                                    <span class="input-group-text">/ <?php echo $question['points']; ?></span>
                                    <input type="hidden" name="question_points[<?php echo $question['id']; ?>]" value="<?php echo $question['points']; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="d-flex justify-content-between mt-4">
                <a href="correct_exam.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Retour aux résultats
                </a>
                <button type="submit" name="save_scores" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Enregistrer l'évaluation
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const scoreInputs = document.querySelectorAll('.question-score');
        const totalObtainedEl = document.getElementById('total-obtained');
        const totalPossibleEl = document.getElementById('total-possible');
        const currentPercentageEl = document.getElementById('current-percentage');
        
        // Calculer le score total
        function calculateTotalScore() {
            let totalObtained = 0;
            let totalPossible = 0;
            
            scoreInputs.forEach(input => {
                const score = parseFloat(input.value) || 0;
                const maxScore = parseFloat(input.dataset.max) || 0;
                
                totalObtained += score;
                totalPossible += maxScore;
            });
            
            totalObtainedEl.textContent = totalObtained.toFixed(1);
            totalPossibleEl.textContent = totalPossible.toFixed(1);
            
            // Calculer le pourcentage
            if (totalPossible > 0) {
                const percentage = (totalObtained / totalPossible) * 100;
                currentPercentageEl.textContent = percentage.toFixed(1) + '%';
            } else {
                currentPercentageEl.textContent = '0%';
            }
        }
        
        // Ajouter des écouteurs d'événements pour recalculer le score
        scoreInputs.forEach(input => {
            input.addEventListener('input', function() {
                // Vérifier que la valeur est dans les limites
                const value = parseFloat(this.value) || 0;
                const max = parseFloat(this.dataset.max) || 0;
                
                if (value < 0) {
                    this.value = 0;
                } else if (value > max) {
                    this.value = max;
                }
                
                calculateTotalScore();
            });
        });
        
        // Calculer le score initial
        calculateTotalScore();
        
        // Valider le formulaire avant soumission
        document.getElementById('evaluation-form').addEventListener('submit', function(e) {
            let isValid = true;
            
            // Vérifier que tous les scores sont valides
            scoreInputs.forEach(input => {
                const value = parseFloat(input.value);
                const max = parseFloat(input.dataset.max);
                
                if (isNaN(value) || value < 0 || value > max) {
                    isValid = false;
                    input.classList.add('is-invalid');
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Veuillez vérifier les scores attribués. Tous les scores doivent être compris entre 0 et le maximum de points pour chaque question.');
            }
        });
    });
    </script>
</body>
</html>