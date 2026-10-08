<?php

$admin_page_title = "Manage Survey Templates";


if (!isset($conn)) {
    require_once __DIR__ . '/../includes/db_connect.php';
}


$action = isset($_GET['action']) ? $_GET['action'] : 'list'; 
$template_id_to_edit = isset($_GET['id']) ? (int)$_GET['id'] : null;


$template_name = '';
$template_description = '';
$form_errors = [];


if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $template_name = trim($_POST['template_name']);
    $template_description = trim($_POST['template_description']);
    $created_by_user_id = $_SESSION['user_id']; 

    
    if (empty($template_name)) {
        $form_errors['template_name'] = "Template name is required.";
    } elseif (strlen($template_name) > 255) {
        $form_errors['template_name'] = "Template name cannot exceed 255 characters.";
    } else {
        
        $stmt_check = $conn->prepare("SELECT template_id FROM survey_templates WHERE template_name = ?");
        $stmt_check->bind_param("s", $template_name);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $form_errors['template_name'] = "A template with this name already exists.";
        }
        $stmt_check->close();
    }

    
    if (empty($form_errors)) {
        $sql_insert = "INSERT INTO survey_templates (template_name, description, created_by_user_id) VALUES (?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        if ($stmt_insert) {
            $stmt_insert->bind_param("ssi", $template_name, $template_description, $created_by_user_id);
            if ($stmt_insert->execute()) {
                $_SESSION['message'] = "Survey template '" . htmlspecialchars($template_name) . "' added successfully!";
                $_SESSION['message_type'] = "success";
                header("Location: " . BASE_URL . "admin/index.php?page=templates");
                exit();
            } else {
                $form_errors['general'] = "Error adding template: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        } else {
            $form_errors['general'] = "Error preparing statement: " . $conn->error;
        }
    }
}


if ($action === 'delete' && $template_id_to_edit) {
    
    

    $sql_delete = "DELETE FROM survey_templates WHERE template_id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    if ($stmt_delete) {
        $stmt_delete->bind_param("i", $template_id_to_edit);
        if ($stmt_delete->execute()) {
            $_SESSION['message'] = "Survey template deleted successfully.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting template: " . $stmt_delete->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt_delete->close();
    } else {
        $_SESSION['message'] = "Error preparing delete statement: " . $conn->error;
        $_SESSION['message_type'] = "danger";
    }
    header("Location: " . BASE_URL . "admin/index.php?page=templates");
    exit();
}




if ($action === 'add' || ($action === 'edit' && $template_id_to_edit)) :
    
    if ($action === 'edit' && $template_id_to_edit) {
        $admin_page_title = "Edit Survey Template";
        $stmt_fetch = $conn->prepare("SELECT template_name, description FROM survey_templates WHERE template_id = ?");
        $stmt_fetch->bind_param("i", $template_id_to_edit);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        if ($result_fetch->num_rows === 1) {
            $template_data = $result_fetch->fetch_assoc();
            $template_name = $template_data['template_name'];
            $template_description = $template_data['description'];
        } else {
            $_SESSION['message'] = "Template not found.";
            $_SESSION['message_type'] = "danger";
            header("Location: " . BASE_URL . "admin/index.php?page=templates");
            exit();
        }
        $stmt_fetch->close();
        
        echo "<h2>Edit Survey Template (Update logic not fully implemented)</h2>";
        $form_action_url = BASE_URL . "admin/index.php?page=templates&action=edit&id=" . $template_id_to_edit;
        $button_text = "Update Template";
    } else { 
        $admin_page_title = "Add New Survey Template";
        echo "<h2>Add New Survey Template</h2>";
        $form_action_url = BASE_URL . "admin/index.php?page=templates&action=add";
        $button_text = "Add Template";
    }
?>
    <?php if (!empty($form_errors['general'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($form_errors['general']); ?></div>
    <?php endif; ?>

    <form action="<?php echo $form_action_url; ?>" method="POST" class="admin-form">
        <div class="form-group">
            <label for="template_name">Template Name:</label>
            <input type="text" name="template_name" id="template_name" value="<?php echo htmlspecialchars($template_name); ?>" required>
            <?php if (isset($form_errors['template_name'])): ?><small class="error-text"><?php echo htmlspecialchars($form_errors['template_name']); ?></small><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="template_description">Description (Optional):</label>
            <textarea name="template_description" id="template_description" rows="4"><?php echo htmlspecialchars($template_description); ?></textarea>
        </div>
        <div class="form-group">
            <button type="submit" class="button admin-button-primary"><?php echo $button_text; ?></button>
            <a href="<?php echo BASE_URL; ?>admin/index.php?page=templates" class="button admin-button-secondary">Cancel</a>
        </div>
    </form>
    <?php if (!isset($form_errors['general']) && !empty($form_errors)) echo "<style>.error-text { color: red; font-size: 0.9em; display: block; margin-top: 5px; }</style>"; ?>

<?php
else: 
?>
    <div class="manage-users-header"> 
        <h2>Manage Survey Templates</h2>
        <a href="<?php echo BASE_URL; ?>admin/index.php?page=templates&action=add" class="button add-user-button">Add New Template</a>
    </div>
    <p>Create and manage reusable survey templates that survey creators can use as a starting point.</p>

    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Template Name</th>
                <th>Description</th>
                <th>Created By</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            
            $sql_list = "SELECT st.*, u.username as creator_username
                         FROM survey_templates st
                         LEFT JOIN users u ON st.created_by_user_id = u.user_id
                         ORDER BY st.template_id DESC";
            $stmt_list = $conn->prepare($sql_list);

            if ($stmt_list) {
                $stmt_list->execute();
                $result_list = $stmt_list->get_result();
                if ($result_list->num_rows > 0) {
                    while ($row = $result_list->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['template_id']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['template_name']) . "</td>";
                        echo "<td>" . nl2br(htmlspecialchars(substr($row['description'] ?? '', 0, 100))) . (strlen($row['description'] ?? '') > 100 ? '...' : '') . "</td>";
                        echo "<td>" . htmlspecialchars($row['creator_username'] ?? 'N/A') . "</td>";
                        echo "<td>" . date("M d, Y H:i", strtotime($row['created_at'])) . "</td>";
                        echo "<td class='action-links'>";
                        echo "<a href='" . BASE_URL . "admin/index.php?page=template_questions&template_id=" . $row['template_id'] . "' class='view-link'>Manage Questions</a> "; 
                        echo "<a href='" . BASE_URL . "admin/index.php?page=templates&action=edit&id=" . $row['template_id'] . "' class='edit-link'>Edit</a> ";
                        echo "<a href='" . BASE_URL . "admin/index.php?page=templates&action=delete&id=" . $row['template_id'] . "' class='delete-link' onclick='return confirm(\"Are you sure you want to delete this template? This may affect surveys based on it.\")'>Delete</a>";
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='6' style='text-align:center;'>No survey templates found.</td></tr>";
                }
                $stmt_list->close();
            } else {
                echo "<tr><td colspan='6' style='text-align:center;'>Error preparing statement: " . $conn->error . "</td></tr>";
            }
            ?>
        </tbody>
    </table>

<?php
endif; 
?>