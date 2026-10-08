<?php

$page_title = "User Registration";
require_once __DIR__ . '/includes/header.php'; 


if (isset($_SESSION['user_id'])) {
    header("Location: " . htmlspecialchars(BASE_URL . "dashboard.php"));
    exit();
}


$username_val = '';
$email_val = '';
$full_name_val = '';
$password_val = ''; 
$confirm_password_val = ''; 
$respondent_type_val = 'other'; 
$form_errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $username_val = trim($_POST['username']);
    $email_val = trim($_POST['email']);
    $full_name_val = trim($_POST['full_name']);
    $password = $_POST['password']; 
    $confirm_password = $_POST['confirm_password'];
    $respondent_type_val = isset($_POST['respondent_type']) ? trim($_POST['respondent_type']) : 'other';

    
    
    if (empty($username_val)) {
        $form_errors['username'] = "Username is required.";
    } elseif (strlen($username_val) < 3 || strlen($username_val) > 50) {
        $form_errors['username'] = "Username must be between 3 and 50 characters.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username_val)) {
        $form_errors['username'] = "Username can only contain letters, numbers, and underscores.";
    } else {
        $stmt_check_username = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        if ($stmt_check_username) {
            $stmt_check_username->bind_param("s", $username_val);
            $stmt_check_username->execute();
            if ($stmt_check_username->get_result()->num_rows > 0) {
                $form_errors['username'] = "Username already taken. Please choose another.";
            }
            $stmt_check_username->close();
        } else { $form_errors['general'] = "Database error checking username."; error_log("Register - Username check prepare failed: ".$conn->error); }
    }

    
    if (empty($email_val)) {
        $form_errors['email'] = "Email is required.";
    } elseif (!filter_var($email_val, FILTER_VALIDATE_EMAIL)) {
        $form_errors['email'] = "Invalid email format.";
    } else {
        $stmt_check_email = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
         if ($stmt_check_email) {
            $stmt_check_email->bind_param("s", $email_val);
            $stmt_check_email->execute();
            if ($stmt_check_email->get_result()->num_rows > 0) {
                $form_errors['email'] = "This email address is already registered.";
            }
            $stmt_check_email->close();
        } else { $form_errors['general'] = "Database error checking email."; error_log("Register - Email check prepare failed: ".$conn->error); }
    }

    
    if (empty($password)) {
        $form_errors['password'] = "Password is required.";
    } elseif (strlen($password) < 8) { 
        $form_errors['password'] = "Password must be at least 8 characters long.";
    }
    
    


    if ($password !== $confirm_password) {
        $form_errors['confirm_password'] = "Passwords do not match.";
    }

    
    $allowed_respondent_types = ['student', 'faculty', 'staff', 'community', 'other'];
    if (!in_array($respondent_type_val, $allowed_respondent_types)) {
        $form_errors['respondent_type'] = "Invalid respondent type selected.";
    }

    
    if (empty($form_errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $default_system_role = 'respondent'; 
        $default_is_active = 1; 

        $sql_insert_user = "INSERT INTO users (username, email, password_hash, full_name, role, respondent_type, is_active, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt_insert_user = $conn->prepare($sql_insert_user);

        if ($stmt_insert_user) {
            $stmt_insert_user->bind_param("ssssssi",
                $username_val,
                $email_val,
                $password_hash,
                $full_name_val,
                $default_system_role,
                $respondent_type_val,
                $default_is_active
            );

            if ($stmt_insert_user->execute()) {
                
                
                
                
                
                

                $_SESSION['message'] = "Registration successful! You can now log in with your new account.";
                $_SESSION['message_type'] = "success";
                header("Location: " . htmlspecialchars(BASE_URL . "login.php?registration=success"));
                exit();
            } else {
                $form_errors['general'] = "An error occurred during registration. Please try again.";
                error_log("User Registration Execute Error: " . $stmt_insert_user->error);
            }
            $stmt_insert_user->close();
        } else {
            $form_errors['general'] = "A server error occurred. Please try again later.";
            error_log("User Registration Prepare Error: " . $conn->error);
        }
    }
}
?>

<div class="registration-container" style="max-width: 550px; margin: 30px auto; padding: 20px;">
    <h2>User Registration</h2>
    <p>Create an account to participate in surveys and provide valuable feedback.</p>

    <?php if (!empty($form_errors['general'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($form_errors['general']); ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars(BASE_URL . "register.php"); ?>" method="POST" class="admin-form creator-form" novalidate> 
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username_val); ?>" required minlength="3" maxlength="50" pattern="^[a-zA-Z0-9_]+$">
            <?php if (isset($form_errors['username'])): ?><small class="error-text"><?php echo htmlspecialchars($form_errors['username']); ?></small><?php endif; ?>
            <small class="form-text text-muted">3-50 characters. Letters, numbers, and underscores only.</small>
        </div>

        <div class="form-group">
            <label for="email">Email Address:</label>
            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email_val); ?>" required>
            <?php if (isset($form_errors['email'])): ?><small class="error-text"><?php echo htmlspecialchars($form_errors['email']); ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="full_name">Full Name (Optional):</label>
            <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($full_name_val); ?>">
        </div>

        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" name="password" id="password" required minlength="8">
            <?php if (isset($form_errors['password'])): ?><small class="error-text"><?php echo htmlspecialchars($form_errors['password']); ?></small><?php endif; ?>
            <small class="form-text text-muted">Minimum 8 characters.</small>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" name="confirm_password" id="confirm_password" required minlength="8">
            <?php if (isset($form_errors['confirm_password'])): ?><small class="error-text"><?php echo htmlspecialchars($form_errors['confirm_password']); ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="respondent_type">I am a:</label>
            <select name="respondent_type" id="respondent_type" required>
                <option value="student" <?php echo ($respondent_type_val === 'student' ? 'selected' : ''); ?>>Student</option>
                <option value="faculty" <?php echo ($respondent_type_val === 'faculty' ? 'selected' : ''); ?>>Faculty Member</option>
                <option value="staff" <?php echo ($respondent_type_val === 'staff' ? 'selected' : ''); ?>>Staff Member</option>
                <option value="community" <?php echo ($respondent_type_val === 'community' ? 'selected' : ''); ?>>Community Member (External)</option>
                <option value="other" <?php echo ($respondent_type_val === 'other' ? 'selected' : ''); ?>>Other</option>
            </select>
            <?php if (isset($form_errors['respondent_type'])): ?><small class="error-text"><?php echo htmlspecialchars($form_errors['respondent_type']); ?></small><?php endif; ?>
        </div>
        
        
        

        <div class="form-group">
            <button type="submit" class="button button-primary" style="width: 100%;">Register Account</button>
        </div>
    </form>
    <p style="text-align: center; margin-top: 20px;">
        Already have an account? <a href="<?php echo htmlspecialchars(BASE_URL . "login.php"); ?>" class="fu">Login here</a>.
    </p>
</div>
<style>
    .error-text { color: #dc3545; font-size: 0.875em; display: block; margin-top: .25rem; }
    .text-muted { color: #6c757d !important; font-size: 0.875em; }
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
?>