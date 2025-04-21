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

// Récupérer les groupes disponibles - Simplifié pour trouver tous les groupes
$teacher_id = $_SESSION['user_id'];
$groups_query = "SELECT * FROM groups"; // Récupère tous les groupes pour simplifier

// Pour une requête plus ciblée plus tard, on pourrait utiliser:
// $groups_query = "SELECT g.* FROM groups g 
//                LEFT JOIN user_groups ug ON g.id = ug.group_id 
//                WHERE ug.user_id = ? OR g.id IN (SELECT group_id FROM user_groups WHERE user_id IN 
//                    (SELECT user_id FROM teacher_subjects WHERE teacher_id = ?))";

$stmt_groups = $conn->prepare($groups_query);
// $stmt_groups->bind_param("ii", $teacher_id, $teacher_id); // Pour la requête ciblée
$stmt_groups->execute();
$result_groups = $stmt_groups->get_result();

// Vérifier si des groupes existent
$groups_exist = $result_groups->num_rows > 0;

// Debug - Afficher les groupes disponibles
$debug_groups = [];
if ($groups_exist) {
    $result_groups_copy = $result_groups; // Copie pour ne pas affecter l'original
    while ($group = $result_groups_copy->fetch_assoc()) {
        $debug_groups[] = $group;
    }
    // Réinitialiser le pointeur de résultat pour l'utilisation ultérieure
    $result_groups->data_seek(0);
}

// Si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'] ?? '';
    $duration = $_POST['duration'];
    $attempts = $_POST['attempts'];
    $total_points = $_POST['total_points'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $teacher_id = $_SESSION['user_id'];
    $group_id = $_POST['group_id']; // Récupérer l'ID du groupe

    // Vérifier que la colonne group_id existe dans la table exams
    $column_check = $conn->query("SHOW COLUMNS FROM exams LIKE 'group_id'");
    $column_exists = $column_check->num_rows > 0;

    if (!$column_exists) {
        $error = "La colonne group_id n'existe pas dans la table exams. Veuillez mettre à jour votre structure de base de données.";
    } else {
        // Insertion de l'examen dans la base de données
        $stmt = $conn->prepare("INSERT INTO exams (title, description, teacher_id, start_time, end_time, duration, attempts, total_points, group_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissiiii", $title, $description, $teacher_id, $start_date, $end_date, $duration, $attempts, $total_points, $group_id);

        if ($stmt->execute()) {
            // Récupérer l'ID de l'examen créé
            $exam_id = $stmt->insert_id;

            // Insérer les questions
            if (!empty($_POST['questions'])) {
                $questions = $_POST['questions'];
                $question_points = $_POST['question_points'];
                $question_types = $_POST['question_type'];

                for ($i = 0; $i < count($questions); $i++) {
                    $stmt_question = $conn->prepare("INSERT INTO questions (exam_id, question_text, question_type) VALUES (?, ?, ?)");
                    $stmt_question->bind_param("iss", $exam_id, $questions[$i], $question_types[$i]);
                    $stmt_question->execute();
                    $question_id = $stmt_question->insert_id;
                    if (isset($question_points[$i]) && $question_points[$i] > 0) {
                        $stmt_points = $conn->prepare("INSERT INTO question_points (question_id, points) VALUES (?, ?)");
                        $stmt_points->bind_param("ii", $question_id, $question_points[$i]);
                        $stmt_points->execute();
                    }

                    // Si c'est un QCM, insérer les options
                    if ($question_types[$i] === 'qcm' && isset($_POST['options'][$i]['text'])) {
                        $options = $_POST['options'][$i]['text'];
                        $correct = isset($_POST['options'][$i]['correct']) ? $_POST['options'][$i]['correct'] : [];

                        for ($j = 0; $j < count($options); $j++) {
                            if (!empty($options[$j])) {
                                $is_correct = isset($correct[$j]) ? 1 : 0;
                                $stmt_option = $conn->prepare("INSERT INTO choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
                                $stmt_option->bind_param("isi", $question_id, $options[$j], $is_correct);
                                $stmt_option->execute();
                            }
                        }
                    }
                }
            }

            // Ajouter automatiquement les étudiants du groupe à l'examen
            if ($group_id) {
                $stmt_students = $conn->prepare("SELECT user_id FROM user_groups WHERE group_id = ? AND user_id IN (SELECT id FROM users WHERE role = 'student')");
                $stmt_students->bind_param("i", $group_id);
                $stmt_students->execute();
                $result_students = $stmt_students->get_result();
                
                while ($student = $result_students->fetch_assoc()) {
                    $student_id = $student['user_id'];
                    $stmt_enroll = $conn->prepare("INSERT INTO exam_students (exam_id, student_id, status) VALUES (?, ?, 'pending')");
                    $stmt_enroll->bind_param("ii", $exam_id, $student_id);
                    $stmt_enroll->execute();
                }
            }

            // Rediriger vers la page des examens
            header("Location: Teacher_dashboard.php");
            exit();
        } else {
            $error = "Erreur lors de la création de l'examen: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un Examen</title>
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
        }

        .btn-back {
            background: transparent;
            border: 2px solid var(--accent-color);
            color: var(--accent-color);
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 212, 255, 0.3);
        }

        .exam-container {
            width: 100%;
            max-width: 900px;
            margin: 100px auto 40px;
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

        h2 {
            font-size: 2.25rem;
            font-weight: 800;
            margin-bottom: 2.5rem;
            background: linear-gradient(to right, var(--text-primary), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        input[type="text"],
        input[type="number"],
        input[type="datetime-local"],
        select {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid var(--border);
            background-color: var(--input-bg);
            font-size: 1rem;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }

        button,
        input[type="submit"] {
            background: linear-gradient(to right, var(--accent-color), var(--primary-color));
            color: white;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        button:hover,
        input[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 70, 229, 0.3);
        }

        #questions-container {
            margin-top: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        #questions-container h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        #add-question-btn {
            margin-top: 1rem;
        }

        .question {
            background-color: var(--input-bg);
            border: 1px solid var(--border);
            padding: 1.5rem;
            border-radius: 0.75rem;
            display: grid;
            gap: 1rem;
            position: relative;
            overflow: hidden;
        }

        .question::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, var(--accent-color), var(--primary-color));
        }

        .options-container {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }

        .option {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 1rem;
            align-items: center;
        }

        .option button {
            background-color: var(--error-color);
            padding: 0.5rem 1rem;
        }

        .option button:hover {
            background-color: #b91c1c;
        }

        .add-option-btn {
            background: linear-gradient(to right, var(--success-color), #059669);
            margin-top: 1rem;
        }

        .add-option-btn:hover {
            background: linear-gradient(to right, #059669, var(--success-color));
        }

        .btn-danger {
            background: linear-gradient(to right, var(--error-color), #b91c1c);
        }

        .error-message {
            color: var(--error-color);
            font-size: 0.875rem;
            margin-top: 0.5rem;
            padding: 0.75rem;
            background-color: rgba(220, 38, 38, 0.1);
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .warning-message {
            color: var(--warning-color);
            font-size: 0.875rem;
            margin-top: 0.5rem;
            padding: 0.75rem;
            background-color: rgba(245, 158, 11, 0.1);
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
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

            .exam-container {
                padding: 1.5rem;
                margin-top: 150px;
            }

            .option {
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
            <a href="listexam.php">Examens</a>
            <a href="#">Groupes</a>
        </div>
        <div class="auth-buttons">
            <button id="themeToggle" class="theme-toggle">🌙</button>
            <a href="Teacher_dashboard.php" class="btn btn-back">Retour</a>
        </div>
    </nav>

    <!-- Floating Shapes -->
    <div class="floating-shape shape-1"></div>
    <div class="floating-shape shape-2"></div>

    <!-- Main Content -->
    <div class="exam-container">
        <div class="container-glow"></div>
        <h2>Créer un Examen</h2>
    <?php if (isset($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!$column_check = $conn->query("SHOW COLUMNS FROM exams LIKE 'group_id'") || $column_check->num_rows == 0): ?>
        <div class="error-message">
            <strong>Erreur de structure de base de données :</strong> La colonne 'group_id' n'existe pas dans la table 'exams'.<br>
            Veuillez exécuter la requête SQL suivante pour ajouter cette colonne :<br>
            <code>ALTER TABLE exams ADD COLUMN group_id INT, ADD FOREIGN KEY (group_id) REFERENCES groups(id);</code>
        </div>
    <?php endif; ?>

    <?php if (!$groups_exist): ?>
        <div class="warning-message">
            <strong>Attention :</strong> Aucun groupe n'est disponible. Veuillez d'abord créer des groupes dans la section de gestion des groupes.
        </div>
    <?php endif; ?>
    
    <form method="POST" action="create_exam.php" id="exam-form">
        <!-- Titre de l'examen -->
        <div class="form-group">
            <label for="title">Titre de l'examen:</label>
            <input type="text" name="title" id="title" required placeholder="Titre de l'examen">
        </div>

        <!-- Description de l'examen (optionnelle) -->
        <div class="form-group">
            <label for="description">Description (optionnelle):</label>
            <input type="text" name="description" id="description" placeholder="Description de l'examen">
        </div>

        <!-- Groupe d'étudiants auquel l'examen est destiné -->
        <div class="form-group">
            <label for="group_id">Groupe d'étudiants:</label>
            <select name="group_id" id="group_id" required <?php echo !$groups_exist ? 'disabled' : ''; ?>>
                <option value="">Sélectionnez un groupe</option>
                <?php if ($groups_exist): while ($group = $result_groups->fetch_assoc()): ?>
                    <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                <?php endwhile; endif; ?>
            </select>
            <?php if (!$groups_exist): ?>
                <span class="error-message">Aucun groupe disponible</span>
            <?php endif; ?>
        </div>

        <!-- Durée de l'examen -->
        <div class="form-group">
            <label for="duration">Durée (en minutes):</label>
            <input type="number" name="duration" id="duration" required placeholder="Durée de l'examen">
        </div>

        <!-- Nombre de tentatives autorisées -->
        <div class="form-group">
            <label for="attempts">Nombre de tentatives autorisées:</label>
            <input type="number" name="attempts" id="attempts" required placeholder="Tentatives autorisées">
        </div>

        <!-- Total des points de l'examen -->
        <div class="form-group">
            <label for="total_points">Total des points de l'examen:</label>
            <input type="number" name="total_points" id="total_points" required placeholder="Total des points de l'examen">
        </div>

        <!-- Date de début de l'examen -->
        <div class="form-group">
            <label for="start_date">Date de début:</label>
            <input type="datetime-local" name="start_date" id="start_date" required>
        </div>

        <!-- Date de fin de l'examen -->
        <div class="form-group">
            <label for="end_date">Date de fin:</label>
            <input type="datetime-local" name="end_date" id="end_date" required>
        </div>

        <!-- Section pour ajouter des questions -->
        <div id="questions-container">
            <h3>Questions de l'examen</h3>
            <button type="button" id="add-question-btn">Ajouter une question</button>
        </div>

        <!-- Bouton pour soumettre l'examen -->
        <div class="form-group" style="margin-top: 2rem;">
            <input type="submit" value="Créer l'examen" <?php echo !$groups_exist ? 'disabled' : ''; ?>>
        </div>
    </form>
</div>

<script>
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const form = document.getElementById('exam-form');
    let questionCounter = 0;

    // Vérification des dates
    function validateDates() {
        const startDate = new Date(startDateInput.value);
        const endDate = new Date(endDateInput.value);

        if (endDate <= startDate) {
            alert('La date de fin doit être après la date de début.');
            endDateInput.value = '';
        }
    }

    // Vérification globale lors de la soumission du formulaire
    form.addEventListener('submit', function (e) {
        const startDate = new Date(startDateInput.value);
        const endDate = new Date(endDateInput.value);

        if (endDate <= startDate) {
            e.preventDefault();
            alert('La date de fin doit être après la date de début.');
            return;
        }

        // Vérifier qu'au moins une question existe
        if (questionCounter === 0) {
            e.preventDefault();
            alert('Vous devez ajouter au moins une question à l\'examen.');
            return;
        }

        // Pour les questions QCM, s'assurer qu'il y a au moins une option
        const qcmQuestions = document.querySelectorAll('select[name="question_type[]"]');
        for (let i = 0; i < qcmQuestions.length; i++) {
            if (qcmQuestions[i].value === 'qcm') {
                const optionsContainer = qcmQuestions[i].closest('.question').querySelector('.options-container');
                if (!optionsContainer || optionsContainer.children.length === 0) {
                    e.preventDefault();
                    alert('Chaque question QCM doit avoir au moins une option.');
                    return;
                }
            }
        }
        
        // Vérifier qu'un groupe a été sélectionné
        if (!document.getElementById('group_id').value) {
            e.preventDefault();
            alert('Vous devez sélectionner un groupe pour cet examen.');
            return;
        }
    });

    startDateInput.addEventListener('change', validateDates);
    endDateInput.addEventListener('change', validateDates);

    // Ajout de nouvelles questions
    document.getElementById('add-question-btn').addEventListener('click', function () {
        questionCounter++;
        const questionId = Date.now(); // Utiliser un timestamp comme identifiant unique
        
        const questionSection = document.createElement('div');
        questionSection.classList.add('question');

        // Texte de la question
        const questionTextLabel = document.createElement('label');
        questionTextLabel.textContent = 'Question:';
        questionTextLabel.setAttribute('for', 'question_' + questionId);
        
        const questionText = document.createElement('input');
        questionText.setAttribute('type', 'text');
        questionText.setAttribute('name', 'questions[]');
        questionText.setAttribute('id', 'question_' + questionId);
        questionText.setAttribute('placeholder', 'Entrez votre question ici');
        questionText.required = true;

        // Points pour chaque question
        const questionPointsLabel = document.createElement('label');
        questionPointsLabel.textContent = 'Points:';
        questionPointsLabel.setAttribute('for', 'points_' + questionId);
        
        const questionPoints = document.createElement('input');
        questionPoints.setAttribute('type', 'number');
        questionPoints.setAttribute('name', 'question_points[]');
        questionPoints.setAttribute('id', 'points_' + questionId);
        questionPoints.setAttribute('placeholder', 'Points pour cette question');
        questionPoints.setAttribute('min', '1');
        questionPoints.required = true;
        questionPoints.addEventListener('input', validateTotalPoints);

        // Type de question
        const questionTypeLabel = document.createElement('label');
        questionTypeLabel.textContent = 'Type de question:';
        questionTypeLabel.setAttribute('for', 'type_' + questionId);
        
        const questionType = document.createElement('select');
        questionType.setAttribute('name', 'question_type[]');
        questionType.setAttribute('id', 'type_' + questionId);
        
        const option1 = document.createElement('option');
        option1.value = 'open';
        option1.textContent = 'Question ouverte';
        
        const option2 = document.createElement('option');
        option2.value = 'qcm';
        option2.textContent = 'QCM';
        
        const option3 = document.createElement('option');
        option3.value = 'short';
        option3.textContent = 'Réponse courte';
        
        questionType.appendChild(option1);
        questionType.appendChild(option2);
        questionType.appendChild(option3);

        // Bouton pour supprimer la question
        const deleteQuestionBtn = document.createElement('button');
        deleteQuestionBtn.type = 'button';
        deleteQuestionBtn.textContent = 'Supprimer cette question';
        deleteQuestionBtn.classList.add('btn-danger');
        deleteQuestionBtn.style.backgroundColor = 'var(--danger)';
        deleteQuestionBtn.style.marginTop = '1rem';
        deleteQuestionBtn.addEventListener('click', function() {
            questionSection.remove();
            questionCounter--;
            validateTotalPoints();
        });

        // Conteneur pour les options QCM
        const optionsContainer = document.createElement('div');
        optionsContainer.classList.add('options-container');
        optionsContainer.style.display = 'none';
        optionsContainer.dataset.questionId = questionId;

        // Bouton pour ajouter une option
        const addOptionBtn = document.createElement('button');
        addOptionBtn.type = 'button';
        addOptionBtn.textContent = 'Ajouter une option';
        addOptionBtn.classList.add('add-option-btn');
        addOptionBtn.style.display = 'none';

        // Fonction pour ajouter une option
        addOptionBtn.addEventListener('click', function () {
            const optionId = Date.now(); // Un autre timestamp unique
            
            const optionDiv = document.createElement('div');
            optionDiv.classList.add('option');
            
            const optionTextInput = document.createElement('input');
            optionTextInput.type = 'text';
            optionTextInput.name = `options[${questionCounter-1}][text][]`;
            optionTextInput.placeholder = "Texte de l'option";
            optionTextInput.required = true;
            
            const optionCorrectLabel = document.createElement('label');
            optionCorrectLabel.innerHTML = 'Correct: ';
            
            const optionCorrectCheckbox = document.createElement('input');
            optionCorrectCheckbox.type = 'checkbox';
            optionCorrectCheckbox.name = `options[${questionCounter-1}][correct][]`;
            
            optionCorrectLabel.appendChild(optionCorrectCheckbox);
            
            const removeOptionBtn = document.createElement('button');
            removeOptionBtn.type = 'button';
            removeOptionBtn.textContent = 'Supprimer';
            removeOptionBtn.addEventListener('click', function() {
                optionDiv.remove();
            });
            
            optionDiv.appendChild(optionTextInput);
            optionDiv.appendChild(optionCorrectLabel);
            optionDiv.appendChild(removeOptionBtn);
            
            optionsContainer.appendChild(optionDiv);
        });

        // Gérer le changement de type de question
        questionType.addEventListener('change', function() {
            if (this.value === 'qcm') {
                optionsContainer.style.display = 'block';
                addOptionBtn.style.display = 'block';
                // Ajouter automatiquement une première option
                addOptionBtn.click();
            } else {
                optionsContainer.style.display = 'none';
                addOptionBtn.style.display = 'none';
                // Vider le conteneur d'options
                while (optionsContainer.firstChild) {
                    optionsContainer.removeChild(optionsContainer.firstChild);
                }
            }
        });

        // Assembler la question
        questionSection.appendChild(questionTextLabel);
        questionSection.appendChild(questionText);
        questionSection.appendChild(questionPointsLabel);
        questionSection.appendChild(questionPoints);
        questionSection.appendChild(questionTypeLabel);
        questionSection.appendChild(questionType);
        questionSection.appendChild(optionsContainer);
        questionSection.appendChild(addOptionBtn);
        questionSection.appendChild(deleteQuestionBtn);

        // Ajouter au conteneur de questions
        document.getElementById('questions-container').appendChild(questionSection);
    });

    // Fonction pour valider que les points des questions ne dépassent pas le total
    function validateTotalPoints() {
        const totalPoints = parseInt(document.getElementById('total_points').value) || 0;
        const questionPoints = document.querySelectorAll('input[name="question_points[]"]');
        let sum = 0;
        
        questionPoints.forEach(input => {
            sum += parseInt(input.value) || 0;
        });
        
        // Si le total des points des questions dépasse le total de l'examen
        if (sum > totalPoints && totalPoints > 0) {
            alert(`Le total des points des questions (${sum}) dépasse le total de l'examen (${totalPoints}).`);
        }
    }

    // Écouter les changements sur le total des points de l'examen
    document.getElementById('total_points').addEventListener('input', validateTotalPoints);
</script>

</body>
</html>