<?php
session_start();
// Verify if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "exam_system";

// Connect to database
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Success message
$success_message = "";
$error_message = "";

// Function to sanitize input
function sanitize_input($data) {
    global $conn;
    return $conn->real_escape_string(trim($data));
}

// Add new user (teacher or student)
if (isset($_POST['add_user'])) {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $role = sanitize_input($_POST['role']);
    $plain_password = sanitize_input($_POST['password']);
    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
    
    // Check if email already exists
    $check_email = "SELECT * FROM users WHERE email = '$email'";
    $result = $conn->query($check_email);
    
    if ($result->num_rows > 0) {
        $error_message = "Cet email existe déjà dans le système.";
    } else {
        $sql = "INSERT INTO users (name, email, password, role) VALUES ('$name', '$email', '$hashed_password', '$role')";
        
        if ($conn->query($sql) === TRUE) {
            $user_id = $conn->insert_id;
            
            // Add user to group if selected
            if (isset($_POST['group_id']) && !empty($_POST['group_id'])) {
                $group_id = sanitize_input($_POST['group_id']);
                $group_sql = "INSERT INTO user_groups (user_id, group_id) VALUES ($user_id, $group_id)";
                $conn->query($group_sql);
            }
            
            // Add subject to teacher if applicable
            if ($role === 'teacher' && isset($_POST['subject_id']) && !empty($_POST['subject_id'])) {
                $subject_id = sanitize_input($_POST['subject_id']);
                $subject_sql = "INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES ($user_id, $subject_id)";
                $conn->query($subject_sql);
            }
            
            $success_message = ucfirst($role) . " ajouté avec succès.";
        } else {
            $error_message = "Erreur: " . $conn->error;
        }
    }
}

// Update user password
if (isset($_POST['update_password'])) {
    $user_id = sanitize_input($_POST['user_id']);
    $new_password = sanitize_input($_POST['new_password']);
    $confirm_password = sanitize_input($_POST['confirm_password']);
    
    if ($new_password !== $confirm_password) {
        $error_message = "Les mots de passe ne correspondent pas.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = '$hashed_password' WHERE id = $user_id";
        
        if ($conn->query($sql) === TRUE) {
            $success_message = "Mot de passe mis à jour avec succès.";
        } else {
            $error_message = "Erreur: " . $conn->error;
        }
    }
}

// Add new group
if (isset($_POST['add_group'])) {
    $group_name = sanitize_input($_POST['group_name']);
    $group_desc = sanitize_input($_POST['group_description']);
    
    $sql = "INSERT INTO groups (name, description) VALUES ('$group_name', '$group_desc')";
    
    if ($conn->query($sql) === TRUE) {
        $success_message = "Groupe ajouté avec succès.";
    } else {
        $error_message = "Erreur: " . $conn->error;
    }
}

// Add new subject
if (isset($_POST['add_subject'])) {
    $subject_name = sanitize_input($_POST['subject_name']);
    $subject_desc = sanitize_input($_POST['subject_description']);
    
    $sql = "INSERT INTO subjects (name, description) VALUES ('$subject_name', '$subject_desc')";
    
    if ($conn->query($sql) === TRUE) {
        $success_message = "Matière ajoutée avec succès.";
    } else {
        $error_message = "Erreur: " . $conn->error;
    }
}

// Delete user
if (isset($_POST['delete_user'])) {
    $user_id = sanitize_input($_POST['user_id']);
    
    $sql = "DELETE FROM users WHERE id = $user_id AND role != 'admin'";
    
    if ($conn->query($sql) === TRUE) {
        $success_message = "Utilisateur supprimé avec succès.";
    } else {
        $error_message = "Erreur: " . $conn->error;
    }
}

// Delete group
if (isset($_POST['delete_group'])) {
    $group_id = sanitize_input($_POST['group_id']);
    
    $sql = "DELETE FROM groups WHERE id = $group_id";
    
    if ($conn->query($sql) === TRUE) {
        $success_message = "Groupe supprimé avec succès.";
    } else {
        $error_message = "Erreur: " . $conn->error;
    }
}

// Delete subject
if (isset($_POST['delete_subject'])) {
    $subject_id = sanitize_input($_POST['subject_id']);
    
    $sql = "DELETE FROM subjects WHERE id = $subject_id";
    
    if ($conn->query($sql) === TRUE) {
        $success_message = "Matière supprimée avec succès.";
    } else {
        $error_message = "Erreur: " . $conn->error;
    }
}

// Get all groups for dropdown
$groups_query = "SELECT * FROM groups ORDER BY name";
$groups_result = $conn->query($groups_query);

// Get all subjects for dropdown
$subjects_query = "SELECT * FROM subjects ORDER BY name";
$subjects_result = $conn->query($subjects_query);

// Get all users
$users_query = "SELECT u.id, u.name, u.email, u.role, u.created_at, 
               GROUP_CONCAT(DISTINCT g.name SEPARATOR ', ') as groups,
               GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as subjects
               FROM users u
               LEFT JOIN user_groups ug ON u.id = ug.user_id
               LEFT JOIN groups g ON ug.group_id = g.id
               LEFT JOIN teacher_subjects ts ON u.id = ts.teacher_id
               LEFT JOIN subjects s ON ts.subject_id = s.id
               WHERE u.role != 'admin'
               GROUP BY u.id
               ORDER BY u.created_at DESC";
$users_result = $conn->query($users_query);

// Get all groups
$all_groups_query = "SELECT g.*, COUNT(ug.user_id) as user_count 
                    FROM groups g
                    LEFT JOIN user_groups ug ON g.id = ug.group_id
                    GROUP BY g.id
                    ORDER BY g.name";
$all_groups_result = $conn->query($all_groups_query);

// Get all subjects
$all_subjects_query = "SELECT s.*, COUNT(ts.teacher_id) as teacher_count 
                      FROM subjects s
                      LEFT JOIN teacher_subjects ts ON s.id = ts.subject_id
                      GROUP BY s.id
                      ORDER BY s.name";
$all_subjects_result = $conn->query($all_subjects_query);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Exam+</title>
    <style>
    :root {
    --primary-color: #4f46e5;
    --primary-dark: #3730a3;
    --secondary-color: #4338ca;
    --accent-color: #00d4ff;
    --accent-dark: #00a3c4;
    --dark-bg: #0f172a;
    --card-bg: #1e293b;
    --card-bg-hover: #283548;
    --text-primary: #ffffff;
    --text-secondary: #94a3b8;
    --error-color: #ef4444;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --border-color: rgba(255, 255, 255, 0.1);
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', 'Poppins', sans-serif;
}

body {
    min-height: 100vh;
    background: linear-gradient(135deg, var(--dark-bg), #131f38);
    color: var(--text-primary);
    display: flex;
    flex-direction: column;
    line-height: 1.5;
}

.navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 2rem;
    background: var(--card-bg);
    box-shadow: var(--shadow);
    position: sticky;
    top: 0;
    z-index: 100;
}

.navbar .logo {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--accent-color);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.navbar .logo:before {
    content: "⚡";
    font-size: 1.2rem;
}

.navbar .user-info {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.navbar .user-info .user-name {
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.navbar .user-info .user-name:before {
    content: "👤";
    font-size: 1.2rem;
}

.navbar .user-info .logout-btn {
    color: var(--text-primary);
    background: rgba(239, 68, 68, 0.2);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.3s ease;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.navbar .user-info .logout-btn:before {
    content: "🚪";
    font-size: 1rem;
}

.navbar .user-info .logout-btn:hover {
    background: rgba(239, 68, 68, 0.4);
    transform: translateY(-1px);
}

.container {
    flex: 1;
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
    width: 100%;
}

.dashboard-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.dashboard-header h1 {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    font-weight: 700;
    background: linear-gradient(to right, var(--accent-color), var(--primary-color));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.dashboard-header p {
    color: var(--text-secondary);
    font-size: 1.1rem;
}

.tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 1rem;
    flex-wrap: wrap;
}

.tab-btn {
    background: transparent;
    color: var(--text-secondary);
    border: none;
    padding: 0.75rem 1.25rem;
    font-size: 0.95rem;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.2s ease;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tab-btn:before {
    font-size: 1rem;
}

.tab-btn[data-tab="users"]:before { content: "👥"; }
.tab-btn[data-tab="add-user"]:before { content: "➕👤"; }
.tab-btn[data-tab="change-password"]:before { content: "🔐"; }
.tab-btn[data-tab="groups"]:before { content: "🔄"; }
.tab-btn[data-tab="add-group"]:before { content: "➕🔄"; }
.tab-btn[data-tab="subjects"]:before { content: "📚"; }
.tab-btn[data-tab="add-subject"]:before { content: "➕📚"; }

.tab-btn.active {
    background: var(--primary-color);
    color: var(--text-primary);
    box-shadow: var(--shadow-sm);
}

.tab-btn:hover:not(.active) {
    background: rgba(79, 70, 229, 0.1);
    color: var(--text-primary);
    transform: translateY(-1px);
}

.tab-content {
    display: none;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.tab-content.active {
    display: block;
}

.card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow);
    transition: all 0.3s ease;
    border: 1px solid var(--border-color);
}

.card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.15);
}

.card h2 {
    margin-bottom: 1.5rem;
    font-size: 1.5rem;
    color: var(--accent-color);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 600;
}

#users h2:before { content: "👥"; }
#add-user h2:before { content: "➕👤"; }
#change-password h2:before { content: "🔐"; }
#groups h2:before { content: "🔄"; }
#add-group h2:before { content: "➕🔄"; }
#subjects h2:before { content: "📚"; }
#add-subject h2:before { content: "➕📚"; }

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.75rem;
    color: var(--text-secondary);
    font-weight: 500;
    font-size: 0.95rem;
}

.form-group input, 
.form-group select, 
.form-group textarea {
    width: 100%;
    padding: 0.85rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-group input:focus, 
.form-group select:focus, 
.form-group textarea:focus {
    outline: none;
    border-color: var(--accent-color);
    box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.15);
}

.form-group input:hover, 
.form-group select:hover, 
.form-group textarea:hover {
    border-color: rgba(255, 255, 255, 0.2);
}

.form-row {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1rem;
}

.form-row .form-group {
    flex: 1;
}

button[type="submit"] {
    background: linear-gradient(to right, var(--accent-color), var(--primary-color));
    color: var(--text-primary);
    border: none;
    padding: 0.85rem 1.75rem;
    border-radius: 8px;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
    box-shadow: var(--shadow-sm);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

button[type="submit"]:before {
    content: "💾";
    font-size: 1.1rem;
}

button[type="submit"]:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
    background: linear-gradient(to right, var(--accent-dark), var(--primary-dark));
}

.alert {
    padding: 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    animation: slideInDown 0.4s ease;
}

@keyframes slideInDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert-success {
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: var(--success-color);
}

.alert-success:before {
    content: "✅";
    font-size: 1.2rem;
}

.alert-error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: var(--error-color);
}

.alert-error:before {
    content: "❌";
    font-size: 1.2rem;
}

.table-container {
    overflow-x: auto;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: rgba(255, 255, 255, 0.02);
}

table {
    width: 100%;
    border-collapse: collapse;
}

table th, table td {
    padding: 1.25rem 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

table th {
    color: var(--accent-color);
    font-weight: 600;
    background: rgba(0, 212, 255, 0.05);
    position: sticky;
    top: 0;
    z-index: 10;
}

table tr:last-child td {
    border-bottom: none;
}

table tr {
    transition: all 0.2s ease;
}

table tr:hover {
    background: var(--card-bg-hover);
}

.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.75rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.2s ease;
}

.badge-teacher {
    background: rgba(79, 70, 229, 0.15);
    color: var(--primary-color);
}

.badge-teacher:before {
    content: "👨‍🏫";
    margin-right: 0.3rem;
    font-size: 0.9rem;
}

.badge-student {
    background: rgba(16, 185, 129, 0.15);
    color: var(--success-color);
}

.badge-student:before {
    content: "👨‍🎓";
    margin-right: 0.3rem;
    font-size: 0.9rem;
}

.badge-count {
    background: rgba(245, 158, 11, 0.15);
    color: var(--warning-color);
    margin-left: 0.5rem;
}

.delete-btn {
    background: rgba(239, 68, 68, 0.2);
    color: var(--error-color);
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.edit-btn {
    background: rgba(79, 70, 229, 0.2);
    color: var(--primary-color);
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.edit-btn:before {
    content: "✏️";  /* Icône crayon */
    font-size: 1rem;
}

.edit-btn:hover {
    background: rgba(79, 70, 229, 0.4);
    transform: translateY(-1px);
}

/* Style pour la colonne d'actions */
.actions-cell {
    display: flex;
    flex-direction: column;
}
.delete-btn:before {
    content: "🗑️";
    font-size: 1rem;
}

.delete-btn:hover {
    background: rgba(239, 68, 68, 0.4);
    transform: translateY(-1px);
}

.edit-btn {
    background: rgba(79, 70, 229, 0.2);
    color: var(--primary-color);
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.edit-btn:before {
    content: "🔑";
    font-size: 1rem;
}

.edit-btn:hover {
    background: rgba(79, 70, 229, 0.4);
    transform: translateY(-1px);
}

.actions-cell {
    display: flex;
    flex-direction: column;
}

.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-backdrop.active {
    opacity: 1;
    visibility: visible;
}

.modal {
    background: var(--card-bg);
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    box-shadow: var(--shadow-lg);
    padding: 2rem;
    transform: translateY(-20px);
    transition: all 0.3s ease;
    border: 1px solid var(--border-color);
}

.modal-backdrop.active .modal {
    transform: translateY(0);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
    color: var(--accent-color);
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-header h3:before {
    content: "🔐";
    font-size: 1.1rem;
}

.close-modal {
    background: transparent;
    border: none;
    color: var(--text-secondary);
    font-size: 1.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.close-modal:hover {
    color: var(--error-color);
    transform: scale(1.1);
}

.password-strength {
    margin-top: 0.5rem;
    height: 5px;
    border-radius: 5px;
    background: #333;
    overflow: hidden;
}

.password-strength-bar {
    height: 100%;
    width: 0;
    transition: width 0.3s ease;
}

.password-strength-text {
    font-size: 0.8rem;
    margin-top: 0.25rem;
}

@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .navbar {
        padding: 1rem;
        flex-direction: column;
        gap: 1rem;
    }
    
    .tabs {
        overflow-x: auto;
        padding-bottom: 0.5rem;
        justify-content: flex-start;
        gap: 0.25rem;
    }
    
    .tab-btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
        white-space: nowrap;
    }
    
    .card {
        padding: 1.5rem;
    }
    
    .dashboard-header h1 {
        font-size: 1.75rem;
    }
}

/* Customized Scrollbar */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--dark-bg);
}

::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--accent-color);
}

/* Additional animations */
.table-container {
    animation: fadeInUp 0.5s ease;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Enhance focus states for accessibility */
button:focus, a:focus, input:focus, select:focus, textarea:focus {
    outline: 2px solid var(--accent-color);
    outline-offset: 2px;
}

/* Add pulse animation to success message */
.alert-success {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
</style>
</head>
<body>
    <div class="navbar">
        <div class="logo">Exam+</div>
        <div class="user-info">
            <span class="user-name">Admin: <?php echo $_SESSION['name']; ?></span>
            <a href="logout.php" class="logout-btn">Déconnexion</a>
        </div>
    </div>

    <div class="container">
        <div class="dashboard-header">
            <h1>Tableau de bord administrateur</h1>
            <p>Gérez vos utilisateurs, groupes et matières</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn active" data-tab="users">Utilisateurs</button>
            <button class="tab-btn" data-tab="add-user">Ajouter Utilisateur</button>
            <button class="tab-btn" data-tab="change-password">Modifier Mot de passe</button>
            <button class="tab-btn" data-tab="groups">Groupes</button>
            <button class="tab-btn" data-tab="add-group">Ajouter Groupe</button>
            <button class="tab-btn" data-tab="subjects">Matières</button>
            <button class="tab-btn" data-tab="add-subject">Ajouter Matière</button>
        </div>

        <!-- Users Tab -->
        <div class="tab-content active" id="users">
            <div class="card">
                <h2>Liste des Utilisateurs</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Groupes</th>
                                <th>Matières</th>
                                <th>Date de création</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users_result->num_rows > 0): ?>
                                <?php while($user = $users_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $user['name']; ?></td>
                                        <td><?php echo $user['email']; ?></td>
                                        <td>
                                            <?php if ($user['role'] == 'teacher'): ?>
                                                
                                                <span class="badge badge-teacher">Enseignant</span>
                                            <?php else: ?>
                                                <span class="badge badge-student">Étudiant</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $user['groups'] ? $user['groups'] : '-'; ?></td>
                                        <td><?php echo ($user['role'] == 'teacher' && $user['subjects']) ? $user['subjects'] : '-'; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                        <td class="actions-cell">
    <!-- Ajout du bouton Modifier -->
    <button type="button" class="edit-btn user-edit-btn" 
            data-id="<?php echo $user['id']; ?>"
            data-name="<?php echo $user['name']; ?>"
            data-email="<?php echo $user['email']; ?>"
            data-role="<?php echo $user['role']; ?>">
        Modifier
    </button>
    <form method="POST" action="" onsubmit="return confirmDelete('utilisateur')">
        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
        <button type="submit" name="delete_user" class="delete-btn">Supprimer</button>
    </form>
</td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">Aucun utilisateur trouvé</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add User Tab -->
        <div class="tab-content" id="add-user">
            <div class="card">
                <h2>Ajouter un Utilisateur</h2>
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Nom complet</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Mot de passe</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="role">Rôle</label>
                            <select id="role" name="role" required>
                                <option value="">Sélectionnez un rôle</option>
                                <option value="teacher">Enseignant</option>
                                <option value="student">Étudiant</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="group_id">Groupe (optionnel)</label>
                            <select id="group_id" name="group_id">
                                <option value="">Sélectionnez un groupe</option>
                                <?php if ($groups_result->num_rows > 0): ?>
                                    <?php while($group = $groups_result->fetch_assoc()): ?>
                                        <option value="<?php echo $group['id']; ?>"><?php echo $group['name']; ?></option>
                                    <?php endwhile; ?>
                                    <?php $groups_result->data_seek(0); // Reset result pointer ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="subject_id">Matière (pour enseignants)</label>
                            <select id="subject_id" name="subject_id">
                                <option value="">Sélectionnez une matière</option>
                                <?php if ($subjects_result->num_rows > 0): ?>
                                    <?php while($subject = $subjects_result->fetch_assoc()): ?>
                                        <option value="<?php echo $subject['id']; ?>"><?php echo $subject['name']; ?></option>
                                    <?php endwhile; ?>
                                    <?php $subjects_result->data_seek(0); // Reset result pointer ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="add_user">Ajouter l'utilisateur</button>
                </form>
            </div>
        </div>
        <!-- Change Password Tab -->
<div class="tab-content" id="change-password">
    <div class="card">
        <h2>Modifier le mot de passe</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="user_id_password">Sélectionner l'utilisateur</label>
                <select id="user_id_password" name="user_id" required>
                    <option value="">Sélectionnez un utilisateur</option>
                    <?php 
                    // Reset the users result pointer
                    $users_result->data_seek(0);
                    while($user = $users_result->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $user['id']; ?>"><?php echo $user['name']; ?> (<?php echo $user['email']; ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="new_password">Nouveau mot de passe</label>
                <input type="password" id="new_password" name="new_password" required>
                <div class="password-strength">
                    <div class="password-strength-bar"></div>
                </div>
                <div class="password-strength-text"></div>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" name="update_password">Mettre à jour le mot de passe</button>
        </form>
    </div>
</div>
        <!-- Groups Tab -->
        <div class="tab-content" id="groups">
            <div class="card">
                <h2>Liste des Groupes</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Description</th>
                                <th>Nombre d'étudiants</th>
                                <th>Date de création</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($all_groups_result->num_rows > 0): ?>
                                <?php while($group = $all_groups_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $group['name']; ?></td>
                                        <td><?php echo $group['description']; ?></td>
                                        <td>
                                            <?php echo $group['user_count']; ?>
                                            <span class="badge badge-count"><?php echo $group['user_count']; ?></span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($group['created_at'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">Aucun groupe trouvé</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add Group Tab -->
        <div class="tab-content" id="add-group">
            <div class="card">
                <h2>Ajouter un Groupe</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="group_name">Nom du groupe</label>
                        <input type="text" id="group_name" name="group_name" required>
                    </div>
                    <div class="form-group">
                        <label for="group_description">Description</label>
                        <textarea id="group_description" name="group_description" rows="3"></textarea>
                    </div>
                    <button type="submit" name="add_group">Ajouter le groupe</button>
                </form>
            </div>
        </div>

        <!-- Subjects Tab -->
        <div class="tab-content" id="subjects">
            <div class="card">
                <h2>Liste des Matières</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Description</th>
                                <th>Nombre d'enseignants</th>
                                <th>Date de création</th>
                                <th>Actions</th>
                                
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($all_subjects_result->num_rows > 0): ?>
                                <?php while($subject = $all_subjects_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $subject['name']; ?></td>
                                        <td><?php echo $subject['description']; ?></td>
                                        <td>
                                            <?php echo $subject['teacher_count']; ?>
                                            <span class="badge badge-count"><?php echo $subject['teacher_count']; ?></span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($subject['created_at'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">Aucune matière trouvée</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add Subject Tab -->
        <div class="tab-content" id="add-subject">
            <div class="card">
                <h2>Ajouter une Matière</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="subject_name">Nom de la matière</label>
                        <input type="text" id="subject_name" name="subject_name" required>
                    </div>
                    <div class="form-group">
                        <label for="subject_description">Description</label>
                        <textarea id="subject_description" name="subject_description" rows="3"></textarea>
                    </div>
                    <button type="submit" name="add_subject">Ajouter la matière</button>
                </form>
            </div>
        </div>
    </div>
    <div class="modal-backdrop" id="editUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Modifier l'utilisateur</h3>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" id="edit_user_id" name="edit_user_id" value="">
            <div class="form-group">
                <label for="edit_name">Nom complet</label>
                <input type="text" id="edit_name" name="edit_name" required>
            </div>
            <div class="form-group">
                <label for="edit_email">Email</label>
                <input type="email" id="edit_email" name="edit_email" required>
            </div>
            <div class="form-group">
                <label for="edit_role">Rôle</label>
                <select id="edit_role" name="edit_role" required>
                    <option value="teacher">Enseignant</option>
                    <option value="student">Étudiant</option>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_group_id">Groupe (optionnel)</label>
                <select id="edit_group_id" name="edit_group_id">
                    <option value="">Sélectionnez un groupe</option>
                    <?php 
                    // Reset the groups result pointer
                    $groups_result->data_seek(0);
                    while($group = $groups_result->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $group['id']; ?>"><?php echo $group['name']; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group" id="edit_subject_container">
                <label for="edit_subject_id">Matière (pour enseignants)</label>
                <select id="edit_subject_id" name="edit_subject_id">
                    <option value="">Sélectionnez une matière</option>
                    <?php 
                    // Reset the subjects result pointer
                    $subjects_result->data_seek(0);
                    while($subject = $subjects_result->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $subject['id']; ?>"><?php echo $subject['name']; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" name="update_user">Mettre à jour l'utilisateur</button>
        </form>
    </div>
</div>

    <script>
        // Tab switching functionality
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.getAttribute('data-tab');
                
                // Remove active class from all buttons and contents
                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                // Add active class to current button and content
                btn.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Role dependent fields
        const roleSelect = document.getElementById('role');
        const subjectField = document.getElementById('subject_id');

        roleSelect.addEventListener('change', () => {
            if (roleSelect.value === 'teacher') {
                subjectField.parentElement.style.display = 'block';
            } else {
                subjectField.parentElement.style.display = 'none';
                subjectField.value = '';
            }
        });

        // Initialize on page load
        if (roleSelect.value !== 'teacher') {
            subjectField.parentElement.style.display = 'none';
        }
        // Password strength checker
const newPasswordInput = document.getElementById('new_password');
const confirmPasswordInput = document.getElementById('confirm_password');
const strengthBar = document.querySelector('.password-strength-bar');
const strengthText = document.querySelector('.password-strength-text');

newPasswordInput.addEventListener('input', () => {
    const password = newPasswordInput.value;
    let strength = 0;
    
    // Check length
    if (password.length >= 8) strength += 25;
    
    // Check for numbers
    if (/\d/.test(password)) strength += 25;
    
    // Check for lowercase letters
    if (/[a-z]/.test(password)) strength += 25;
    
    // Check for uppercase letters or special characters
    if (/[A-Z]/.test(password) || /[^A-Za-z0-9]/.test(password)) strength += 25;
    
    // Update UI
    strengthBar.style.width = strength + '%';
    
    if (strength < 25) {
        strengthBar.style.backgroundColor = '#ef4444';
        strengthText.textContent = 'Très faible';
        strengthText.style.color = '#ef4444';
    } else if (strength < 50) {
        strengthBar.style.backgroundColor = '#f59e0b';
        strengthText.textContent = 'Faible';
        strengthText.style.color = '#f59e0b';
    } else if (strength < 75) {
        strengthBar.style.backgroundColor = '#3b82f6';
        strengthText.textContent = 'Moyen';
        strengthText.style.color = '#3b82f6';
    } else {
        strengthBar.style.backgroundColor = '#10b981';
        strengthText.textContent = 'Fort';
        strengthText.style.color = '#10b981';
    }
});

// Check if passwords match
confirmPasswordInput.addEventListener('input', () => {
    if (newPasswordInput.value !== confirmPasswordInput.value) {
        confirmPasswordInput.style.borderColor = '#ef4444';
    } else {
        confirmPasswordInput.style.borderColor = '#10b981';
    }
});

// Add password visibility toggle
function addPasswordToggle() {
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    
    passwordInputs.forEach(input => {
        const container = input.parentElement;
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'password-toggle';
        toggleBtn.innerHTML = '👁️';
        toggleBtn.style.position = 'absolute';
        toggleBtn.style.right = '15px';
        toggleBtn.style.top = '40px';
        toggleBtn.style.background = 'transparent';
        toggleBtn.style.border = 'none';
        toggleBtn.style.cursor = 'pointer';
        toggleBtn.style.color = 'var(--text-secondary)';
        
        // Make parent relative for absolute positioning
        container.style.position = 'relative';
        
        container.appendChild(toggleBtn);
        
        toggleBtn.addEventListener('click', () => {
            if (input.type === 'password') {
                input.type = 'text';
                toggleBtn.style.color = 'var(--accent-color)';
            } else {
                input.type = 'password';
                toggleBtn.style.color = 'var(--text-secondary)';
            }
        });
    });
}

// Initialize password toggles
addPasswordToggle();

// Confirm delete
function confirmDelete(type) {
    return confirm(`Êtes-vous sûr de vouloir supprimer cet ${type} ?`);
}// Modal functionality for editing users
const editUserModal = document.getElementById('editUserModal');
const editUserBtns = document.querySelectorAll('.user-edit-btn');
const closeModalBtn = document.querySelector('.close-modal');
const editRoleSelect = document.getElementById('edit_role');
const editSubjectContainer = document.getElementById('edit_subject_container');

// Handle edit button clicks
editUserBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        const userId = btn.getAttribute('data-id');
        const userName = btn.getAttribute('data-name');
        const userEmail = btn.getAttribute('data-email');
        const userRole = btn.getAttribute('data-role');
        
        document.getElementById('edit_user_id').value = userId;
        document.getElementById('edit_name').value = userName;
        document.getElementById('edit_email').value = userEmail;
        document.getElementById('edit_role').value = userRole;
        
        // Show/hide subject field based on role
        if (userRole === 'teacher') {
            editSubjectContainer.style.display = 'block';
        } else {
            editSubjectContainer.style.display = 'none';
        }
        
        // Open modal
        editUserModal.classList.add('active');
    });
});

// Close modal when clicking the X button
closeModalBtn.addEventListener('click', () => {
    editUserModal.classList.remove('active');
});

// Close modal when clicking outside
editUserModal.addEventListener('click', (e) => {
    if (e.target === editUserModal) {
        editUserModal.classList.remove('active');
    }
});

// Handle role change in edit form
editRoleSelect.addEventListener('change', () => {
    if (editRoleSelect.value === 'teacher') {
        editSubjectContainer.style.display = 'block';
    } else {
        editSubjectContainer.style.display = 'none';
        document.getElementById('edit_subject_id').value = '';
    }
});

<!-- 4. Ajoutez ce code PHP au début du fichier, après les autres traitements de formulaire -->

// Update user information
if (isset($_POST['update_user'])) {
    $user_id = sanitize_input($_POST['edit_user_id']);
    $name = sanitize_input($_POST['edit_name']);
    $email = sanitize_input($_POST['edit_email']);
    $role = sanitize_input($_POST['edit_role']);
    
    // Check if email already exists for another user
    $check_email = "SELECT * FROM users WHERE email = '$email' AND id != $user_id";
    $result = $conn->query($check_email);
    
    if ($result->num_rows > 0) {
        $error_message = "Cet email existe déjà pour un autre utilisateur.";
    } else {
        $sql = "UPDATE users SET name = '$name', email = '$email', role = '$role' WHERE id = $user_id";
        
        if ($conn->query($sql) === TRUE) {
            // Update group if selected
            if (isset($_POST['edit_group_id'])) {
                // First delete existing group associations
                $conn->query("DELETE FROM user_groups WHERE user_id = $user_id");
                
                // Add new group if one is selected
                if (!empty($_POST['edit_group_id'])) {
                    $group_id = sanitize_input($_POST['edit_group_id']);
                    $group_sql = "INSERT INTO user_groups (user_id, group_id) VALUES ($user_id, $group_id)";
                    $conn->query($group_sql);
                }
            }
            
            // Update subject for teacher if applicable
            if ($role === 'teacher' && isset($_POST['edit_subject_id'])) {
                // First delete existing subject associations
                $conn->query("DELETE FROM teacher_subjects WHERE teacher_id = $user_id");
                
                // Add new subject if one is selected
                if (!empty($_POST['edit_subject_id'])) {
                    $subject_id = sanitize_input($_POST['edit_subject_id']);
                    $subject_sql = "INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES ($user_id, $subject_id)";
                    $conn->query($subject_sql);
                }
            }
            
            $success_message = "Utilisateur mis à jour avec succès.";
        } else {
            $error_message = "Erreur: " . $conn->error;
        }
    }
}
    </script>
    
</body>
</html>

<?php
$conn->close();
?>