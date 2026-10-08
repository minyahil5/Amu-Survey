<?php

$page_title = "User Login";


require_once __DIR__ . '/includes/header.php';


?>


<style>
    .login-page-content .login-container .registration-prompt {
        text-align: center;
        margin-top: 25px;
        padding: 18px 20px;
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        line-height: 1.7;
        font-size: 0.95em;
        color: #495057;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .login-page-content .login-container .registration-prompt strong {
        color: #00447c;
        font-weight: 600;
    }
    .login-page-content .login-container .registration-prompt a {
        color: #0056b3;
        font-weight: bold;
        text-decoration: none;
        border-bottom: 1px dashed #0056b3;
        padding-bottom: 1px;
        transition: color 0.2s ease, border-bottom-color 0.2s ease;
    }
    .login-page-content .login-container .registration-prompt a:hover {
        color: #003d80;
        border-bottom-color: #003d80;
    }
</style>

<?php

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $redirect_url_if_logged_in = BASE_URL . "dashboard.php";
    switch ($_SESSION['role']) {
        case 'administrator': $redirect_url_if_logged_in = BASE_URL . "admin/index.php"; break;
        case 'survey_creator': $redirect_url_if_logged_in = BASE_URL . "survey_creator/index.php"; break;
        case 'respondent':
            $user_respondent_type = $_SESSION['respondent_type'] ?? 'other';
            switch ($user_respondent_type) {
                case 'student': $redirect_url_if_logged_in = BASE_URL . "respondent/student_dashboard.php"; break;
                case 'faculty': $redirect_url_if_logged_in = BASE_URL . "respondent/faculty_dashboard.php"; break;
                case 'staff': $redirect_url_if_logged_in = BASE_URL . "respondent/staff_dashboard.php"; break;
                case 'community': $redirect_url_if_logged_in = BASE_URL . "respondent/community_dashboard.php"; break;
                default: $redirect_url_if_logged_in = BASE_URL . "respondent/available_surveys.php"; break;
            } break;
    }
    header("Location: " . htmlspecialchars($redirect_url_if_logged_in)); exit();
}


$error_message = '';
$username_or_email_value = '';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($conn) || !$conn || $conn->connect_error) {
        $db_error = $conn ? $conn->connect_error : "Connection object not available.";
        $error_message = "Database connection error. Please try again later.";
        error_log("Login Page Error: DB connection issue - " . $db_error);
    } else {
        $username_or_email = trim($_POST['username_or_email']);
        $password = $_POST['password'];
        $username_or_email_value = htmlspecialchars($username_or_email);

        if (empty($username_or_email) || empty($password)) {
            $error_message = "Please enter both username/email and password.";
        } else {
            $sql = "SELECT user_id, username, email, password_hash, role, respondent_type, is_active FROM users WHERE (username = ? OR email = ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ss", $username_or_email, $username_or_email);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if ($user['is_active']) {
                        if (password_verify($password, $user['password_hash'])) {
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = $user['user_id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['role'] = $user['role'];
                            $_SESSION['respondent_type'] = $user['respondent_type'] ?? 'other';

                            $redirect_url_on_success = BASE_URL . "dashboard.php"; 
                            if ($user['role'] === 'administrator') { $redirect_url_on_success = BASE_URL . "admin/index.php"; }
                            elseif ($user['role'] === 'survey_creator') { $redirect_url_on_success = BASE_URL . "survey_creator/index.php"; }
                            elseif ($user['role'] === 'respondent') {
                                switch ($user['respondent_type']) {
                                    case 'student': $redirect_url_on_success = BASE_URL . "index.php"; break;
                                    case 'faculty': $redirect_url_on_success = BASE_URL . "index.php"; break;
                                    case 'staff':   $redirect_url_on_success = BASE_URL . "index.php";   break;
                                    case 'community':$redirect_url_on_success = BASE_URL . "index.php";break;
                                    default:        $redirect_url_on_success = BASE_URL . "respondent/available_surveys.php"; break;
                                }
                            }
                            header("Location: " . htmlspecialchars($redirect_url_on_success)); exit();
                        } else { $error_message = "Invalid username/email or password."; }
                    } else { $error_message = "Your account is inactive. Please contact an administrator."; }
                } else { $error_message = "Invalid username/email or password."; }
                $stmt->close();
            } else { $error_message = "Login error. Please try again."; error_log("Login Page - SQL Prepare Error: " . $conn->error); }
        }
    }
}
?>




<div class="login-page-content"> 
    <div class="login-container"> 
        <h2>User Login</h2>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php 
        if (isset($_GET['logged_out']) && $_GET['logged_out'] === 'true'): ?>
            <div class="alert alert-success">You have been successfully logged out.</div>
        <?php elseif (isset($_GET['registration']) && $_GET['registration'] === 'success'): ?>
            <div class="alert alert-success">Registration successful! You can now log in.</div>
        <?php elseif (isset($_GET['access_denied']) && $_GET['access_denied'] === 'true'): ?>
            <div class="alert alert-warning">You need to be logged in to access the requested page.</div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars(BASE_URL . "login.php"); ?>" method="POST" novalidate>
            <div class="form-group">
                <label for="username_or_email">Username or Email:</label>
                <input type="text" name="username_or_email" id="username_or_email" class="form-control"
                       value="<?php echo $username_or_email_value; ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            <div class="form-group">
                <button type="submit" class="button button-primary" style="width: 100%;">Login</button>
            </div>
        </form>

        
        <div class="registration-prompt">
            If you are part of the <strong>AMU community</strong> (Student, Faculty, Staff) and do not have an account,
            or if you are an external community member invited to participate, <br>
            <a href="<?php echo htmlspecialchars(BASE_URL . "register.php"); ?>">Please register here</a>.
        </div>
    </div>
</div>

<?php

require_once __DIR__ . '/includes/footer.php';
?>