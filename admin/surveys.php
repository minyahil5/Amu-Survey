<?php

$admin_page_title = "Manage All Surveys";


if (!isset($conn)) {
    
    require_once __DIR__ . '/../includes/db_connect.php';
}


$action = isset($_GET['action']) ? trim($_GET['action']) : null;
$survey_id_to_action = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : null; 


if ($action === 'change_status' && $survey_id_to_action > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = isset($_POST['new_status']) ? trim($_POST['new_status']) : null;
    $allowed_statuses = ['draft', 'published', 'closed', 'archived']; 

    if ($new_status && in_array($new_status, $allowed_statuses)) {
        
        $stmt_update_status = $conn->prepare("UPDATE surveys SET status = ? WHERE survey_id = ?");
        if ($stmt_update_status) {
            $stmt_update_status->bind_param("si", $new_status, $survey_id_to_action);
            if ($stmt_update_status->execute() && $stmt_update_status->affected_rows > 0) {
                $_SESSION['message'] = "Survey (ID: $survey_id_to_action) status updated to '" . htmlspecialchars(ucfirst($new_status)) . "'.";
                $_SESSION['message_type'] = "success";
            } elseif ($stmt_update_status->affected_rows === 0) {
                 $_SESSION['message'] = "Survey status was already '" . htmlspecialchars(ucfirst($new_status)) . "' or survey not found.";
                 $_SESSION['message_type'] = "info";
            } else {
                $_SESSION['message'] = "Error updating survey status: " . htmlspecialchars($stmt_update_status->error);
                $_SESSION['message_type'] = "danger";
                error_log("Admin Surveys - Change Status Execute Error: " . $stmt_update_status->error);
            }
            $stmt_update_status->close();
        } else {
            $_SESSION['message'] = "Error preparing status update: " . htmlspecialchars($conn->error);
            $_SESSION['message_type'] = "danger";
            error_log("Admin Surveys - Change Status Prepare Error: " . $conn->error);
        }
    } else {
        $_SESSION['message'] = "Invalid status provided for update.";
        $_SESSION['message_type'] = "warning";
    }
    
    header("Location: " . htmlspecialchars(BASE_URL . "admin/index.php?page=surveys"));
    exit();
}


if ($action === 'delete' && $survey_id_to_action > 0) {
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
        
        
        $stmt_delete_survey = $conn->prepare("DELETE FROM surveys WHERE survey_id = ?");
        if ($stmt_delete_survey) {
            $stmt_delete_survey->bind_param("i", $survey_id_to_action);
            if ($stmt_delete_survey->execute() && $stmt_delete_survey->affected_rows > 0) {
                $_SESSION['message'] = "Survey (ID: $survey_id_to_action) and all its associated data deleted successfully.";
                $_SESSION['message_type'] = "success";
            } elseif($stmt_delete_survey->affected_rows === 0) {
                 $_SESSION['message'] = "Survey (ID: $survey_id_to_action) not found or already deleted.";
                 $_SESSION['message_type'] = "warning";
            } else {
                $_SESSION['message'] = "Error deleting survey: " . htmlspecialchars($stmt_delete_survey->error);
                $_SESSION['message_type'] = "danger";
                error_log("Admin Surveys - Delete Survey Execute Error: " . $stmt_delete_survey->error);
            }
            $stmt_delete_survey->close();
        } else {
            $_SESSION['message'] = "Error preparing delete statement: " . htmlspecialchars($conn->error);
            $_SESSION['message_type'] = "danger";
            error_log("Admin Surveys - Delete Survey Prepare Error: " . $conn->error);
        }
        header("Location: " . htmlspecialchars(BASE_URL . "admin/index.php?page=surveys"));
        exit();
    } else { 
        $stmt_survey_title = $conn->prepare("SELECT title FROM surveys WHERE survey_id = ?");
        if($stmt_survey_title){ $stmt_survey_title->bind_param("i", $survey_id_to_action); $stmt_survey_title->execute(); $res_title = $stmt_survey_title->get_result(); $survey_to_del = $res_title->fetch_assoc(); $stmt_survey_title->close(); }

        if (isset($survey_to_del) && $survey_to_del):
            $admin_page_title = "Confirm Delete Survey";
        ?>
            <h2>Confirm Deletion</h2>
            <div class="alert alert-danger">
                <p>Are you sure you want to permanently delete the survey titled "<strong><?php echo htmlspecialchars($survey_to_del['title']); ?></strong>" (ID: <?php echo $survey_id_to_action; ?>)?</p>
                <p><strong>This action cannot be undone and will delete all associated questions, responses, and reports.</strong></p>
            </div>
            <form action="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=surveys&action=delete&id=" . $survey_id_to_action); ?>" method="POST" style="display: inline-block; margin-right: 10px;">
                <input type="hidden" name="confirm_delete" value="yes">
                <button type="submit" class="button button-danger">Yes, Delete This Survey</button>
            </form>
            <a href="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=surveys"); ?>" class="button button-secondary">Cancel</a>
        <?php
            
            require_once __DIR__ . '/_admin_footer.php'; 
            exit();
        else:
            $_SESSION['message'] = "Survey not found for deletion (ID: $survey_id_to_action)."; $_SESSION['message_type'] = "warning";
            header("Location: " . htmlspecialchars(BASE_URL . "admin/index.php?page=surveys")); exit();
        endif;
    }
}

?>

<div class="manage-users-header"> 
    <h2>Manage All Surveys</h2>
    
    
</div>
<p>Oversee all surveys in the system. You can change their status, view individual responses, access aggregated reports, and manage their lifecycle.</p>

<table class="admin-table all-surveys-table"> 
    <thead>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Status</th>
            <th>Creator</th>
            <th>Responses</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Target Audience</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $sql_list_all_surveys = "SELECT s.*, u.username as creator_username,
                                  (SELECT COUNT(DISTINCT rs.response_summary_id) 
                                   FROM survey_responses_summary rs 
                                   WHERE rs.survey_id = s.survey_id AND rs.response_status = 'completed') as total_responses
                               FROM surveys s
                               LEFT JOIN users u ON s.created_by_user_id = u.user_id
                               ORDER BY s.created_at DESC, s.survey_id DESC";
        $stmt_list_all = $conn->prepare($sql_list_all_surveys);
        $surveys_list = [];
        if ($stmt_list_all) {
            $stmt_list_all->execute();
            $result_list_all = $stmt_list_all->get_result();
            if($result_list_all) {
                while ($row = $result_list_all->fetch_assoc()) { $surveys_list[] = $row; }
            } else { error_log("Admin Surveys - List Get Result Error: " . $conn->error); }
            $stmt_list_all->close();
        } else {
            error_log("Admin Surveys - List Prepare Error: " . $conn->error . " | SQL: " . $sql_list_all_surveys);
        }

        if (!empty($surveys_list)):
            foreach ($surveys_list as $survey_row):
                
                $targets = [];
                if ($survey_row['target_all_users']) $targets[] = 'All Users';
                else {
                    if ($survey_row['target_student']) $targets[] = 'Students';
                    if ($survey_row['target_faculty']) $targets[] = 'Faculty';
                    if ($survey_row['target_staff']) $targets[] = 'Staff';
                    if ($survey_row['target_community']) $targets[] = 'Community';
                }
                $target_audience_str = !empty($targets) ? implode(', ', $targets) : 'None Specified';
        ?>
            <tr>
                <td><?php echo htmlspecialchars($survey_row['survey_id']); ?></td>
                <td><?php echo htmlspecialchars($survey_row['title']); ?></td>
                <td>
                    <form action="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=surveys&action=change_status&id=" . $survey_row['survey_id']); ?>" method="POST" class="status-change-form">
                        <select name="new_status" onchange="this.form.submit()" title="Change survey status">
                            <?php
                            $statuses = ['draft' => 'Draft', 'published' => 'Published', 'closed' => 'Closed', 'archived' => 'Archived'];
                            foreach ($statuses as $status_val => $status_text): ?>
                                <option value="<?php echo $status_val; ?>" <?php echo ($survey_row['status'] === $status_val) ? 'selected' : ''; ?>>
                                    <?php echo $status_text; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                    </form>
                </td>
                <td><?php echo htmlspecialchars($survey_row['creator_username'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($survey_row['total_responses']); ?></td>
                <td><?php echo $survey_row['start_date'] ? date("M d, Y H:i", strtotime($survey_row['start_date'])) : 'N/A'; ?></td>
                <td><?php echo $survey_row['end_date'] ? date("M d, Y H:i", strtotime($survey_row['end_date'])) : 'N/A'; ?></td>
                <td title="<?php echo htmlspecialchars($target_audience_str); ?>">
                    <?php echo htmlspecialchars(substr($target_audience_str, 0, 25)) . (strlen($target_audience_str) > 25 ? '...' : ''); ?>
                </td>
                <td><?php echo date("M d, Y", strtotime($survey_row['created_at'])); ?></td>
                <td class='action-links'>
                    <a href="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=survey_responses&survey_id=" . $survey_row['survey_id']); ?>" class='view-link action-button-view' title="View individual responses">View Responses</a>
                    <a href="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=survey_report_detail&survey_id=" . $survey_row['survey_id']); ?>" class='view-link action-button-report' title="View aggregated report">Agg. Report</a>
                    <a href="<?php echo htmlspecialchars(BASE_URL . "survey_creator/edit_survey.php?id=" . $survey_row['survey_id']); ?>" class='edit-link action-button-edit' title="Edit survey structure (questions, etc.)">Edit Structure</a>
                    <a href="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=surveys&action=delete&id=" . $survey_row['survey_id']); ?>" class='delete-link action-button-delete' title="Delete this survey">Delete</a>
                </td>
            </tr>
        <?php
            endforeach;
        else: 
            if (empty($conn->error) && empty($stmt_list_all->error)) { 
                echo "<tr><td colspan='10' style='text-align:center;'>No surveys found in the system.</td></tr>";
            } else {
                 echo "<tr><td colspan='10' style='text-align:center;'>Could not load surveys. Check error logs.</td></tr>";
            }
        endif;
        ?>
    </tbody>
</table>
<style>
    .status-change-form select { padding: 5px; border-radius: 4px; border: 1px solid #ccc; font-size:0.9em; }
    .all-surveys-table th, .all-surveys-table td { font-size: 0.9em; }
    .all-surveys-table .action-links a { margin-bottom: 3px; display: inline-block; } 
</style>

<?php

?>