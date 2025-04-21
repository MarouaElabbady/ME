<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "exam_system");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Variables communes
$exam_id = isset($_POST['exam_id']) ? $_POST['exam_id'] : (isset($_GET['exam_id']) ? $_GET['exam_id'] : null);

if (!$exam_id) {
    header("Location: " . ($_SESSION['role'] === 'teacher' ? 'teacher_dashboard.php' : 'student_dashboard.php'));
    exit();
}

// Traitement différent selon le rôle
if ($_SESSION['role'] === 'student') {
    if (!isset($_POST['answers'])) {
        header("Location: view_exams.php");
        exit();
    }

    // Récupération explicite de l'ID étudiant depuis la session
    $student_id = $_SESSION['user_id'];
    $answers = $_POST['answers'];

    // Journalisation pour débogage
    error_log("Examen ID: " . $exam_id . ", Étudiant ID: " . $student_id);
    error_log("Réponses reçues: " . print_r($answers, true));
    
    // Calculer le score
    $score = 0;
    $total_questions = 0;
    
    foreach ($answers as $question_id => $answer) {
        // Vérifier si la question est à choix multiple
        $question_type_query = "SELECT question_type FROM questions WHERE id = ?";
        $type_stmt = $conn->prepare($question_type_query);
        $type_stmt->bind_param("i", $question_id);
        $type_stmt->execute();
        $question_type_result = $type_stmt->get_result();
        
        if ($question_type_result->num_rows > 0) {
            $question_type = $question_type_result->fetch_assoc()['question_type'];

            // Pour les questions à choix multiple
            if ($question_type === 'qcm') {
                // Vérifier si la réponse est un tableau (multiple choix)
                if (is_array($answer)) {
                    // Sauvegarder chaque réponse sélectionnée
                    foreach ($answer as $choice_id) {
                        $save_answer = "INSERT INTO student_answers (student_id, exam_id, question_id, answer) VALUES (?, ?, ?, ?)";
                        $stmt = $conn->prepare($save_answer);
                        $stmt->bind_param("iiis", $student_id, $exam_id, $question_id, $choice_id);
                        $stmt->execute();
                        
                        error_log("Sauvegarde QCM (multiple): étudiant=$student_id, examen=$exam_id, question=$question_id, choix=$choice_id");
                    }
                    
                    // Récupérer tous les choix corrects pour cette question
                    $correct_choices_query = "SELECT id FROM choices WHERE question_id = ? AND is_correct = 1";
                    $correct_stmt = $conn->prepare($correct_choices_query);
                    $correct_stmt->bind_param("i", $question_id);
                    $correct_stmt->execute();
                    $correct_result = $correct_stmt->get_result();
                    
                    // Récupérer les IDs des choix corrects
                    $correct_choices = [];
                    while ($row = $correct_result->fetch_assoc()) {
                        $correct_choices[] = $row['id'];
                    }
                    
                    // Calculer le score en fonction des choix de l'étudiant
                    $student_choices = $answer;
                    
                    // Vérifier si les choix de l'étudiant correspondent exactement aux choix corrects
                    $correct_count = count(array_intersect($student_choices, $correct_choices));
                    $incorrect_count = count(array_diff($student_choices, $correct_choices));
                    
                    // L'étudiant obtient un point uniquement si tous ses choix sont corrects
                    // et qu'il a sélectionné tous les choix corrects
                    if ($correct_count == count($correct_choices) && $incorrect_count == 0) {
                        $score++;
                        error_log("Point accordé pour question QCM $question_id");
                    }
                } else {
                    // Pour le cas où une seule réponse est envoyée (compatibilité avec l'ancienne version)
                    $save_answer = "INSERT INTO student_answers (student_id, exam_id, question_id, answer) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($save_answer);
                    $stmt->bind_param("iiis", $student_id, $exam_id, $question_id, $answer);
                    $result_save = $stmt->execute();
                    
                    error_log("Sauvegarde QCM (simple): étudiant=$student_id, examen=$exam_id, question=$question_id, réponse=$answer, résultat=" . ($result_save ? "succès" : "échec"));
                    
                    // Vérifier si la réponse est correcte
                    $check_answer = "SELECT is_correct FROM choices WHERE question_id = ? AND id = ?";
                    $check_stmt = $conn->prepare($check_answer);
                    $check_stmt->bind_param("ii", $question_id, $answer);
                    $check_stmt->execute();
                    $result = $check_stmt->get_result();
                    
                    if ($row = $result->fetch_assoc()) {
                        if ($row['is_correct']) {
                            $score++;
                            error_log("Point accordé pour question QCM simple $question_id");
                        }
                    }
                }
            } else {
                // Pour les questions à réponse ouverte
                $save_answer = "INSERT INTO student_answers (student_id, exam_id, question_id, answer) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($save_answer);
                $stmt->bind_param("iiis", $student_id, $exam_id, $question_id, $answer);
                $result_save = $stmt->execute();
                
                error_log("Sauvegarde question ouverte: étudiant=$student_id, examen=$exam_id, question=$question_id, résultat=" . ($result_save ? "succès" : "échec"));
                
                // Pour les questions ouvertes, pas de notation automatique
                // On peut implémenter un système où l'enseignant note manuellement ces réponses plus tard
            }
            $total_questions++;
        } else {
            error_log("Question ID $question_id non trouvée dans la base de données");
        }
    }

    // Calculer le pourcentage
    $percentage = ($total_questions > 0) ? ($score / $total_questions) * 100 : 0;
    error_log("Score final: $score/$total_questions = $percentage%");

    // Vérifier si l'élève existe déjà dans exam_students
    $check_entry = "SELECT id FROM exam_students WHERE exam_id = ? AND student_id = ?";
    $check_stmt = $conn->prepare($check_entry);
    $check_stmt->bind_param("ii", $exam_id, $student_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Mettre à jour le statut de l'examen
        $update_status = "UPDATE exam_students SET 
                        status = 'completed',
                        score = ?,
                        end_time = NOW()
                        WHERE exam_id = ? AND student_id = ?";
        $status_stmt = $conn->prepare($update_status);
        $status_stmt->bind_param("dii", $percentage, $exam_id, $student_id);
        $result_update = $status_stmt->execute();
        
        error_log("Mise à jour statut: étudiant=$student_id, examen=$exam_id, score=$percentage%, résultat=" . ($result_update ? "succès" : "échec"));
    } else {
        // Créer une nouvelle entrée
        $insert_status = "INSERT INTO exam_students (exam_id, student_id, status, score, end_time) 
                          VALUES (?, ?, 'completed', ?, NOW())";
        $insert_stmt = $conn->prepare($insert_status);
        $insert_stmt->bind_param("iid", $exam_id, $student_id, $percentage);
        $result_insert = $insert_stmt->execute();
        
        error_log("Insertion statut: étudiant=$student_id, examen=$exam_id, score=$percentage%, résultat=" . ($result_insert ? "succès" : "échec"));
    }

    // Afficher la page de succès
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Examen Terminé</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card shadow">
                        <div class="card-body text-center">
                            <div class="mb-4">
                                <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                            </div>
                            <h2 class="card-title mb-4">Examen Terminé avec Succès!</h2>
                      
                            <a href="student_dashboard.php" class="btn btn-primary">Retour au tableau de bord</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php

} else if ($_SESSION['role'] === 'teacher') {
    // Code pour les enseignants
    $query = "SELECT e.title, u.name as student_name, u.id as student_id, es.score, es.end_time,
              COUNT(q.id) as total_questions
              FROM exams e
              JOIN exam_students es ON e.id = es.exam_id
              JOIN users u ON es.student_id = u.id
              LEFT JOIN questions q ON e.id = q.exam_id
              WHERE e.id = ? AND es.status = 'completed'
              GROUP BY es.student_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Correction d'Examen</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container py-5">
            <h2 class="mb-4">Résultats de l'Examen</h2>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Étudiant</th>
                            <th>Score</th>
                            <th>Date de fin</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                            <td><?php echo number_format($row['score'], 1); ?>%</td>
                            <td><?php echo date('d/m/Y H:i', strtotime($row['end_time'])); ?></td>
                            <td>
                                <a href="view_student_answers.php?exam_id=<?php echo $exam_id; ?>&student_id=<?php echo $row['student_id']; ?>" 
                                   class="btn btn-sm btn-primary">
                                    Voir les réponses
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <a href="teacher_dashboard.php" class="btn btn-secondary mt-3">Retour au tableau de bord</a>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}

$conn->close();
?>