<?php
$admin_page_title = "Manage Users";
if (!isset($conn)) {
    require_once __DIR__ . '/../includes/db_connect.php';
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    exit('Database connection failed.');
}


$action = isset($_GET['action']) ? trim($_GET['action']) : 'list'; 
$user_id_to_action = isset($_GET['id']) ? (int)$_GET['id'] : null;

$username_val = '';
$email_val = '';
$full_name_val = '';
$role_val = 'respondent';       
$respondent_type_val = 'other'; 
$is_active_val = 1;             
$form_errors = [];              


if ($action === 'delete' && $user_id_to_action > 0) {

    if ($user_id_to_action === $_SESSION['user_id']) {
        $_SESSION['message'] = "Error: You cannot delete your own account.";
        $_SESSION['message_type'] = "danger";
    } else {
        
        
        
        
        

        $stmt_delete = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        if ($stmt_delete) {
            $stmt_delete->bind_param("i", $user_id_to_action);
            if ($stmt_delete->execute()) {
                if ($stmt_delete->affected_rows > 0) {
                    $_SESSION['message'] = "User (ID: " . $user_id_to_action . ") deleted successfully.";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "User (ID: " . $user_id_to_action . ") not found or already deleted.";
                    $_SESSION['message_type'] = "warning";
                }
            } else {
                $_SESSION['message'] = "Error deleting user: " . htmlspecialchars($stmt_delete->error) . ". This may be due to related records in other tables (e.g., surveys created, responses submitted).";
                $_SESSION['message_type'] = "danger";
                error_log("Admin Users - Delete Execute Error: " . $stmt_delete->error . " for user_id: " . $user_id_to_action);
            }
            $stmt_delete->close();
        } else {
            $_SESSION['message'] = "Error preparing delete statement: " . htmlspecialchars($conn->error);
            $_SESSION['message_type'] = "danger";
            error_log("Admin Users - Delete Prepare Error: " . $conn->error);
        }
    }
    header("Location: " . htmlspecialchars(BASE_URL . "admin/index.php?page=users"));
    exit();
}

if (($action === 'add' || ($action === 'edit' && $user_id_to_action > 0)) && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $username_val = trim($_POST['username']);
    $email_val = trim($_POST['email']);
    $full_name_val = trim($_POST['full_name']);
    $role_val = $_POST['role'];
    $respondent_type_val = ($role_val === 'respondent' && isset($_POST['respondent_type'])) ? $_POST['respondent_type'] : 'other';
    if ($role_val !== 'respondent') { $respondent_type_val = 'other'; } 
    $is_active_val = isset($_POST['is_active']) ? 1 : 0;
    $change_password = ($action === 'edit' && isset($_POST['change_password_checkbox']));
    $password = $_POST['password'] ?? ''; 
    $confirm_password = $_POST['confirm_password'] ?? '';
    if (empty($username_val)) { $form_errors['username'] = "Username is required."; }
    elseif (strlen($username_val) < 3 || strlen($username_val) > 50) { $form_errors['username'] = "Username: 3-50 chars."; }
    elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username_val)) { $form_errors['username'] = "Username: letters, numbers, underscores only."; }
    else {
        $sql_check_username = ($action === 'add') ? "SELECT user_id FROM users WHERE username = ?" : "SELECT user_id FROM users WHERE username = ? AND user_id != ?";
        $stmt_check_u = $conn->prepare($sql_check_username);
        if($stmt_check_u){
            if($action === 'add') $stmt_check_u->bind_param("s", $username_val);
            else $stmt_check_u->bind_param("si", $username_val, $user_id_to_action);
            $stmt_check_u->execute();
            if ($stmt_check_u->get_result()->num_rows > 0) { $form_errors['username'] = "Username already taken."; }
            $stmt_check_u->close();
        } else { $form_errors['general'] = "DB error checking username."; }
    
    }

    if (empty($email_val)) { $form_errors['email'] = "Email is required."; }
    elseif (!filter_var($email_val, FILTER_VALIDATE_EMAIL)) { $form_errors['email'] = "Invalid email format."; }
    else { 
        $sql_check_email = ($action === 'add') ? "SELECT user_id FROM users WHERE email = ?" : "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
        $stmt_check_e = $conn->prepare($sql_check_email);
        if($stmt_check_e){
            if($action === 'add') $stmt_check_e->bind_param("s", $email_val);
            else $stmt_check_e->bind_param("si", $email_val, $user_id_to_action);
            $stmt_check_e->execute();
            if ($stmt_check_e->get_result()->num_rows > 0) { $form_errors['email'] = "Email already registered."; }
            $stmt_check_e->close();
        } else { $form_errors['general'] = "DB error checking email."; }
    }

    if ($action === 'add' || $change_password) {
        if (empty($password)) { $form_errors['password'] = "Password is required."; }
        elseif (strlen($password) < 8) { $form_errors['password'] = "Password: min 8 characters."; }
        if ($password !== $confirm_password) { $form_errors['confirm_password'] = "Passwords do not match."; }
    }

    $allowed_system_roles = ['administrator', 'survey_creator', 'respondent'];
    if (!in_array($role_val, $allowed_system_roles)) { $form_errors['role'] = "Invalid system role selected."; }

    $allowed_respondent_types = ['student', 'faculty', 'staff', 'community', 'other'];
    if ($role_val === 'respondent' && !in_array($respondent_type_val, $allowed_respondent_types)) {
        $form_errors['respondent_type'] = "Invalid respondent type selected.";
    }

   
    if (empty($form_errors)) {
        if ($action === 'add') {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, email, password_hash, full_name, role, respondent_type, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssssssi", $username_val, $email_val, $password_hash, $full_name_val, $role_val, $respondent_type_val, $is_active_val);
                if ($stmt->execute()) {
                    $_SESSION['message'] = "User '".htmlspecialchars($username_val)."' added successfully!"; $_SESSION['message_type'] = "success";
                    header("Location: ".htmlspecialchars(BASE_URL."admin/index.php?page=users")); exit();
                } else { $form_errors['general'] = "Error adding user: ".$stmt->error; }
                $stmt->close();
            } else { $form_errors['general'] = "DB prepare error (add): ".$conn->error; }
        } elseif ($action === 'edit') {
            if ($change_password) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET username=?, email=?, full_name=?, role=?, respondent_type=?, is_active=?, password_hash=? WHERE user_id=?";
                $stmt = $conn->prepare($sql);
                if($stmt) $stmt->bind_param("sssssssi", $username_val, $email_val, $full_name_val, $role_val, $respondent_type_val, $is_active_val, $password_hash, $user_id_to_action);
            } else {
                $sql = "UPDATE users SET username=?, email=?, full_name=?, role=?, respondent_type=?, is_active=? WHERE user_id=?";
                $stmt = $conn->prepare($sql);
                if($stmt) $stmt->bind_param("sssssii", $username_val, $email_val, $full_name_val, $role_val, $respondent_type_val, $is_active_val, $user_id_to_action);
            }
            if ($stmt) {
                if ($stmt->execute()) {
                    $_SESSION['message'] = "User '".htmlspecialchars($username_val)."' updated!"; $_SESSION['message_type'] = "success";
                    header("Location: ".htmlspecialchars(BASE_URL."admin/index.php?page=users")); exit();
                } else { $form_errors['general'] = "Error updating user: ".$stmt->error; }
                $stmt->close();
            } else { $form_errors['general'] = "DB prepare error (edit): ".$conn->error; }
        }
    }
}

if ($action === 'add' || ($action === 'edit' && $user_id_to_action > 0)) {
    
    $form_title = ($action === 'add') ? "Add New User" : "Edit User";
    $form_action_url = htmlspecialchars(BASE_URL."admin/index.php?page=users&action=".$action.($action === 'edit' ? "&id=".$user_id_to_action : ""));
    $submit_button_text = ($action === 'add') ? "Add User" : "Update User";
    $admin_page_title = $form_title; 

    if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $stmt_fetch_user = $conn->prepare("SELECT username, email, full_name, role, respondent_type, is_active FROM users WHERE user_id = ?");
        if ($stmt_fetch_user) {
            $stmt_fetch_user->bind_param("i", $user_id_to_action);
            $stmt_fetch_user->execute();
            $result_user_data = $stmt_fetch_user->get_result();
            if ($user_to_edit = $result_user_data->fetch_assoc()) {
                $username_val = $user_to_edit['username'];
                $email_val = $user_to_edit['email'];
                $full_name_val = $user_to_edit['full_name'];
                $role_val = $user_to_edit['role'];
                $respondent_type_val = $user_to_edit['respondent_type'];
                $is_active_val = $user_to_edit['is_active'];
            } else { $_SESSION['message']="User not found (ID: $user_id_to_action)."; $_SESSION['message_type']="danger"; header("Location: ".htmlspecialchars(BASE_URL."admin/index.php?page=users")); exit(); }
            $stmt_fetch_user->close();
        } else { echo "<div class='alert alert-danger'>Error fetching user for edit.</div>"; error_log("Admin User Edit - Fetch Prepare Error: ".$conn->error); }
    }
?>
    <h2><?php echo $form_title; ?></h2>
    <?php if (!empty($form_errors['general'])): ?><div class="alert alert-danger"><?php echo htmlspecialchars($form_errors['general']); ?></div><?php endif; ?>

    <form action="<?php echo $form_action_url; ?>" method="POST" class="admin-form">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username_val); ?>" required>
            <?php if (isset($form_errors['username'])): ?><small class="error-text"><?php echo htmlspecialchars($form_errors['username']); ?></small><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email_val); ?>" required>
            <?php if (isset($form_errors['email'])): ?><small class="error-text"><?php echo htmlspecialchars($form_errors['email']); ?></small><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="full_name">Full Name (Optional):</label>
            <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($full_name_val); ?>">
        </div>

        <?php if ($action === 'edit'): ?>
        <div class="form-group"><label><input type="checkbox" name="change_password_checkbox" id="change_password_cb_id" value="1"> Change Password?</label></div>
        <?php endif; ?>
        <div class="form-group password-fields-container" <?php if ($action === 'edit') echo 'style="display:none;"'; ?>>
            <label for="password">Password: <?php if ($action === 'add') echo '(Required, min 8 chars)'; elseif($action === 'edit') echo '(Leave blank to keep current)'; ?></label>
            <input type="password" name="password" id="password" <?php if ($action === 'add') echo 'required minlength="8"'; ?>>
            <?php if (isset($form_errors['password'])): ?><small class="error-text"><?php echo htmlspecialchars($form_errors['password']); ?></small><?php endif; ?>
        </div>
        <div class="form-group password-fields-container" <?php if ($action === 'edit') echo 'style="display:none;"'; ?>>
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" name="confirm_password" id="confirm_password" <?php if ($action === 'add') echo 'required minlength="8"'; ?>>
            <?php if (isset($form_errors['confirm_password'])): ?><small class="error-text"><?php echo htmlspecialchars($form_errors['confirm_password']); ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="role">System Role:</label>
            <select name="role" id="role_select_id" required onchange="toggleRespondentTypeField(this.value)">
                <option value="respondent" <?php echo ($role_val === 'respondent' ? 'selected' : ''); ?>>Respondent</option>
                <option value="survey_creator" <?php echo ($role_val === 'survey_creator' ? 'selected' : ''); ?>>Survey Creator</option>
                <option value="administrator" <?php echo ($role_val === 'administrator' ? 'selected' : ''); ?>>Administrator</option>
            </select>
            <?php if (isset($form_errors['role'])): ?><small class="error-text"><?php echo htmlspecialchars($form_errors['role']); ?></small><?php endif; ?>
        </div>

        <div class="form-group" id="respondent_type_field_group" style="<?php echo ($role_val === 'respondent' ? 'display:block;' : 'display:none;'); ?>">
            <label for="respondent_type">Respondent Type (if Role is Respondent):</label>
            <select name="respondent_type" id="respondent_type_select_id">
                <option value="student" <?php echo ($respondent_type_val === 'student' ? 'selected' : ''); ?>>Student</option>
                <option value="faculty" <?php echo ($respondent_type_val === 'faculty' ? 'selected' : ''); ?>>Faculty</option>
                <option value="staff" <?php echo ($respondent_type_val === 'staff' ? 'selected' : ''); ?>>Staff</option>
                <option value="community" <?php echo ($respondent_type_val === 'community' ? 'selected' : ''); ?>>Community Member</option>
                <option value="other" <?php echo ($respondent_type_val === 'other' ? 'selected' : ''); ?>>Other/General</option>
            </select>
            <?php if (isset($form_errors['respondent_type'])): ?><small class="error-text"><?php echo htmlspecialchars($form_errors['respondent_type']); ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label><input type="checkbox" name="is_active" id="is_active" value="1" <?php echo ($is_active_val ? 'checked' : ''); ?>> Account Active</label>
        </div>
        <div class="form-group">
            <button type="submit" class="button admin-button-primary"><?php echo $submit_button_text; ?></button>
            <a href="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=users"); ?>" class="button admin-button-secondary">Cancel</a>
        </div>
    </form>
    <script>
        const changePassCb = document.getElementById('change_password_cb_id');
        const passFieldsContainer = document.querySelectorAll('.password-fields-container');
        const roleSelect = document.getElementById('role_select_id');
        const respondentTypeGroup = document.getElementById('respondent_type_field_group');
        const respondentTypeSelect = document.getElementById('respondent_type_select_id');

        function togglePasswordFields(show) {
            passFieldsContainer.forEach(field => {
                field.style.display = show ? 'block' : 'none';
                field.querySelectorAll('input[type="password"]').forEach(input => input.required = show);
            });
        }
        function toggleRespondentTypeField(selectedRole) {
            if (respondentTypeGroup && respondentTypeSelect) {
                const showRespondentType = (selectedRole === 'respondent');
                respondentTypeGroup.style.display = showRespondentType ? 'block' : 'none';
                respondentTypeSelect.required = showRespondentType;
                 if(!showRespondentType) respondentTypeSelect.value = 'other'; 
            }
        }
        if (changePassCb) {
            changePassCb.addEventListener('change', function() { togglePasswordFields(this.checked); });
            if (changePassCb.checked) togglePasswordFields(true);
        }
        if(roleSelect){
            roleSelect.addEventListener('change', function() { toggleRespondentTypeField(this.value); });
            
            document.addEventListener('DOMContentLoaded', function(){ toggleRespondentTypeField(roleSelect.value); });
        }
    </script>
    <style>.error-text{color:red;font-size:0.9em;display:block;margin-top:5px;}</style>

<?php
} else { 
    $admin_page_title = "Manage Users";
?>
    <div class="manage-users-header">
        <h2>Manage User Accounts</h2>
        <a href="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=users&action=add"); ?>" class="button add-user-button">Add New User</a>
    </div>
    <p>Oversee user accounts, assign roles, and manage permissions within the system.</p>

    <table class="admin-table users-list-table">
        <thead>
            <tr>
                <th>ID</th><th>Username</th><th>Email</th><th>Full Name</th>
                <th>System Role</th><th>Respondent Type</th><th>Status</th><th>Created At</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt_list_users = $conn->prepare("SELECT user_id, username, email, full_name, role, respondent_type, is_active, created_at FROM users ORDER BY user_id DESC");
            if ($stmt_list_users) {
                $stmt_list_users->execute(); $result_users = $stmt_list_users->get_result();
                if ($result_users->num_rows > 0) {
                    while ($user_row = $result_users->fetch_assoc()) { 
                        echo "<tr>";
                        echo "<td>".htmlspecialchars($user_row['user_id'])."</td>";
                        echo "<td>".htmlspecialchars($user_row['username'])."</td>";
                        echo "<td>".htmlspecialchars($user_row['email'])."</td>";
                        echo "<td>".htmlspecialchars($user_row['full_name'] ?? 'N/A')."</td>";
                        echo "<td>".htmlspecialchars(ucfirst(str_replace('_',' ',$user_row['role'])))."</td>";
                        echo "<td>".($user_row['role'] === 'respondent' ? htmlspecialchars(ucfirst($user_row['respondent_type'])) : 'N/A')."</td>";
                        echo "<td>".($user_row['is_active'] ? '<span style="color:green;">Active</span>':'<span style="color:red;">Inactive</span>')."</td>";
                        echo "<td>".date("M d, Y H:i", strtotime($user_row['created_at']))."</td>";
                        echo "<td class='action-links'>";
                        echo "<a href='".htmlspecialchars(BASE_URL."admin/index.php?page=users&action=edit&id=".$user_row['user_id'])."' class='edit-link'>Edit</a> ";
                        if ($user_row['user_id'] !== $_SESSION['user_id']) {
                            echo "<a href='".htmlspecialchars(BASE_URL."admin/index.php?page=users&action=delete&id=".$user_row['user_id'])."' class='delete-link' onclick='return confirm(\"Delete user ".htmlspecialchars(addslashes($user_row['username']))."? This is permanent.\")'>Delete</a>";
                        } else { echo "<span style='color:#999;'><i>(Self)</i></span>"; }
                        echo "</td></tr>";
                    }
                } else { echo "<tr><td colspan='9'>No users found.</td></tr>"; } 
                $stmt_list_users->close();
            } else { echo "<tr><td colspan='9'>Error preparing user list: ".htmlspecialchars($conn->error)."</td></tr>"; error_log("Admin Users - List Prepare Error: ".$conn->error); }
            ?>
        </tbody>
    </table>
<?php
} 
?>