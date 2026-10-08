<?php








$is_being_included_by_admin = (isset($is_admin_in_creator_context) && $is_admin_in_creator_context === true);



if (!$is_being_included_by_admin) {
    $creator_page_title = "My Survey Dashboard";
    $active_nav_item = 'my_surveys_dashboard'; 
    require_once __DIR__ . '/_creator_header.php';
    
}




$current_user_id_for_this_view = $_SESSION['user_id'] ?? null;


$error_loading_this_user_dashboard_data = false; 

if (!$current_user_id_for_this_view) {
    error_log("FATAL in survey_creator/index.php: current_user_id not found in session.");
    if (!$is_being_included_by_admin) {
        $_SESSION['message'] = "User session error. Please log in again."; $_SESSION['message_type'] = "danger";
        header("Location: " . htmlspecialchars(BASE_URL . "login.php")); exit();
    }
    $error_loading_this_user_dashboard_data = true;
}
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    $db_conn_error_msg_sc_idx = isset($conn) && $conn->connect_error ? $conn->connect_error : "DB object invalid.";
    error_log("survey_creator/index.php: DB connection issue - " . $db_conn_err_msg_sc_idx);
    if (!$is_being_included_by_admin && !isset($_SESSION['message'])) {
         $_SESSION['message'] = "Database connection error. Cannot load dashboard."; $_SESSION['message_type'] = "danger";
    }
    $error_loading_this_user_dashboard_data = true;
}



$creator_page_action_param_key = $is_being_included_by_admin ? 'creator_page_action' : 'action';
$creator_page_survey_id_param_key = $is_being_included_by_admin ? 'creator_page_survey_id' : 'id';


$action_from_url = isset($_GET[$creator_page_action_param_key]) ? trim($_GET[$creator_page_action_param_key]) : null;
$survey_id_for_action = isset($_GET[$creator_page_survey_id_param_key]) ? (int)$_GET[$creator_page_survey_id_param_key] : null;

if (!$error_loading_this_user_dashboard_data && $action_from_url === 'delete_draft' && $survey_id_for_action > 0) {
    
    $stmt_check_owner = $conn->prepare("SELECT survey_id, title FROM surveys WHERE survey_id = ? AND created_by_user_id = ? AND status = 'draft'");
    if ($stmt_check_owner) {
        $stmt_check_owner->bind_param("ii", $survey_id_for_action, $current_user_id_for_this_view);
        $stmt_check_owner->execute();
        $result_check_owner = $stmt_check_owner->get_result();
        if ($result_check_owner && $result_check_owner->num_rows === 1) {
            $survey_to_delete_title = $result_check_owner->fetch_assoc()['title'] ?? "ID: ".$survey_id_for_action;
            $stmt_delete = $conn->prepare("DELETE FROM surveys WHERE survey_id = ?");
            if ($stmt_delete) {
                $stmt_delete->bind_param("i", $survey_id_for_action);
                if ($stmt_delete->execute() && $stmt_delete->affected_rows > 0) {
                    $_SESSION['message'] = "Draft survey '".htmlspecialchars($survey_to_delete_title)."' deleted successfully."; $_SESSION['message_type'] = "success";
                } else { $_SESSION['message'] = "Error deleting draft: ".($stmt_delete->error?:"Not found."); $_SESSION['message_type']="danger"; error_log("CreatorDash DeleteDraft ExecErr: ".($stmt_delete->error?:"NoRows")." SID:".$survey_id_for_action);}
                $stmt_delete->close();
            } else { $_SESSION['message']="DB Err(DelP_cr)"; $_SESSION['message_type']="danger"; error_log("CreatorDash DeleteDraft PrepErr: ".$conn->error); }
        } else { $_SESSION['message'] = "Cannot delete: Not your draft/not found."; $_SESSION['message_type']="warning"; }
        $stmt_check_owner->close();
    } else { $_SESSION['message']="DB Err(DelChkP_cr)"; $_SESSION['message_type']="danger"; error_log("CreatorDash DeleteDraft CheckPrepErr: ".$conn->error); }
    $redirect_url = $is_being_included_by_admin ? htmlspecialchars(BASE_URL."admin/index.php?page=create_survey_admin_view") : htmlspecialchars(BASE_URL."survey_creator/index.php");
    header("Location: ".$redirect_url); exit();
}



$stats_for_this_user = ['total_my_surveys'=>'0', 'my_draft_surveys'=>'0', 'my_published_surveys'=>'0', 'total_my_responses'=>'0'];
$recent_surveys_for_this_user = [];

if (!$error_loading_this_user_dashboard_data) { 
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM surveys WHERE created_by_user_id = ?");
    if($stmt){ $stmt->bind_param("i", $current_user_id_for_this_view); $stmt->execute(); $res=$stmt->get_result();
        if($res) $stats_for_this_user['total_my_surveys']=$res->fetch_assoc()['count'] ?? '0'; else{$error_loading_this_user_dashboard_data=true; $stats_for_this_user['total_my_surveys']='Err';}
        $stmt->close();
    } else { $error_loading_this_user_dashboard_data = true; $stats_for_this_user['total_my_surveys']='Err'; error_log("SC Dash TotalS PrepErr:".$conn->error); }

    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM surveys WHERE created_by_user_id = ? AND status = 'draft'");
    if($stmt){ $stmt->bind_param("i", $current_user_id_for_this_view); $stmt->execute(); $res=$stmt->get_result();
        if($res) $stats_for_this_user['my_draft_surveys']=$res->fetch_assoc()['count'] ?? '0'; else{$error_loading_this_user_dashboard_data=true; $stats_for_this_user['my_draft_surveys']='Err';}
        $stmt->close();
    } else { $error_loading_this_user_dashboard_data = true; $stats_for_this_user['my_draft_surveys']='Err'; error_log("SC Dash DraftS PrepErr:".$conn->error); }

    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM surveys WHERE created_by_user_id = ? AND status = 'published'");
    if($stmt){ $stmt->bind_param("i", $current_user_id_for_this_view); $stmt->execute(); $res=$stmt->get_result();
        if($res) $stats_for_this_user['my_published_surveys']=$res->fetch_assoc()['count'] ?? '0'; else{$error_loading_this_user_dashboard_data=true; $stats_for_this_user['my_published_surveys']='Err';}
        $stmt->close();
    } else { $error_loading_this_user_dashboard_data = true; $stats_for_this_user['my_published_surveys']='Err'; error_log("SC Dash PubS PrepErr:".$conn->error); }

    
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT rs.response_summary_id) as count FROM survey_responses_summary rs JOIN surveys s ON rs.survey_id = s.survey_id WHERE s.created_by_user_id = ? AND rs.response_status = 'completed' AND s.status IN ('published', 'closed')");
    if($stmt){ $stmt->bind_param("i", $current_user_id_for_this_view); $stmt->execute(); $res=$stmt->get_result();
        if($res) $stats_for_this_user['total_my_responses']=$res->fetch_assoc()['count'] ?? '0'; else{$error_loading_this_user_dashboard_data=true; $stats_for_this_user['total_my_responses']='Err';}
        $stmt->close();
    } else { $error_loading_this_user_dashboard_data = true; $stats_for_this_user['total_my_responses']='Err'; error_log("SC Dash TotalR PrepErr:".$conn->error); }

    
    $sql_my_recent = "SELECT s.survey_id, s.title, s.status, s.start_date, s.end_date, s.updated_at, s.created_at, (SELECT COUNT(DISTINCT srs.response_summary_id) FROM survey_responses_summary srs WHERE srs.survey_id = s.survey_id AND srs.response_status = 'completed') as total_responses FROM surveys s WHERE s.created_by_user_id = ? ORDER BY s.updated_at DESC, s.created_at DESC LIMIT 5";
    $stmt_my_recent_q = $conn->prepare($sql_my_recent);
    if ($stmt_my_recent_q) { $stmt_my_recent_q->bind_param("i", $current_user_id_for_this_view);
        if ($stmt_my_recent_q->execute()) { $res_mr_q = $stmt_my_recent_q->get_result();
            if ($res_mr_q) { while($row_mr = $res_mr_q->fetch_assoc()){ $recent_surveys_for_this_user[] = $row_mr; }}
            else { $error_loading_this_user_dashboard_data = true; error_log("CDash RecentS GetResErr: ".$conn->error); }
        } else { $error_loading_this_user_dashboard_data = true; error_log("CDash RecentS ExecErr: ".$stmt_my_recent_q->error); }
        $stmt_my_recent_q->close();
    } else { $error_loading_this_user_dashboard_data = true; error_log("CDash RecentS PrepErr: ".$conn->error); }
} 


if (!function_exists('append_admin_context_param_for_embedded_creator')) {
    function append_admin_context_param_for_embedded_creator($url, $is_admin_context_flag_local) {
        if (!$is_admin_context_flag_local) return $url;
        return $url . (strpos($url, '?') === false ? '?' : '&') . 'from_admin_panel=true';
    }
}
?>

<?php if ($is_being_included_by_admin): ?>
<div class="alert alert-secondary admin-context-message" style="text-align:left; margin-bottom: 20px; border-left: 4px solid #6c757d; padding: 15px;">
    <h4 style="margin-top:0; color:#383d41; font-size:1.1em;">Survey Tools (Admin Context)</h4>
    Managing surveys for your Administrator account (ID: <?php echo htmlspecialchars($current_user_id_for_this_view); ?>). <br>
    Surveys created via this interface will be assigned to your admin account.
</div>
<?php endif; ?>

<div class="creator-dashboard-header-area" style="<?php if($is_being_included_by_admin) echo 'padding-top:0; margin-top:0; border-top:none; text-align:left;';?>">
    <?php if (!$is_being_included_by_admin): ?><h2>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Creator');?>!</h2><p>Your survey creation and management hub.</p><?php endif; ?>
    <a href="<?php echo htmlspecialchars(append_admin_context_param_for_embedded_creator(BASE_URL . "survey_creator/create_survey.php", $is_being_included_by_admin)); ?>"
       class="button button-primary create-new-survey-main-btn"> 
        + Create New Survey
    </a>
</div>

<div class="creator-stats-cards-container">
    <div class="stat-card-creator"><div class="stat-card-icon-creator"></div><div class="stat-card-info-creator"><h4>Surveys Created</h4><p class="stat-number-creator"><?php echo htmlspecialchars($stats_for_this_user['total_my_surveys']); ?></p></div></div>
    <div class="stat-card-creator"><div class="stat-card-icon-creator"></div><div class="stat-card-info-creator"><h4>Drafts</h4><p class="stat-number-creator"><?php echo htmlspecialchars($stats_for_this_user['my_draft_surveys']); ?></p></div></div>
    <div class="stat-card-creator"><div class="stat-card-icon-creator"></div><div class="stat-card-info-creator"><h4>Published</h4><p class="stat-number-creator"><?php echo htmlspecialchars($stats_for_this_user['my_published_surveys']); ?></p></div></div>
    <div class="stat-card-creator"><div class="stat-card-icon-creator"></div><div class="stat-card-info-creator"><h4>Responses Received</h4><p class="stat-number-creator"><?php echo htmlspecialchars($stats_for_this_user['total_my_responses']); ?></p></div></div>
</div>

<hr style="margin: 35px 0;">

<div class="my-surveys-list-section creator-dashboard-section">
    <div class="section-header-flex">
        <h3><?php echo $is_being_included_by_admin ? "Recent Surveys Created by You (Admin)" : "My Surveys (Recently Updated)"; ?></h3>
        <?php
        $total_my_surveys_numeric_for_user = is_numeric($stats_for_this_user['total_my_surveys']) ? (int)$stats_for_this_user['total_my_surveys'] : 0;
        if (!$error_loading_this_user_dashboard_data && $total_my_surveys_numeric_for_user > count($recent_surveys_for_this_user) && count($recent_surveys_for_this_user) >=0 ):
            $all_surveys_link_for_this_view = $is_being_included_by_admin ?
                BASE_URL . "admin/index.php?page=surveys&filter_creator_id=" . $current_user_id_for_this_view : 
                BASE_URL . "survey_creator/my_surveys_all.php"; 
        ?>
            <a href="<?php echo htmlspecialchars(append_admin_context_param_for_embedded_creator($all_surveys_link_for_this_view, $is_being_included_by_admin && $is_being_included_by_admin ? false : $is_being_included_by_admin));  ?>" class="button button-small button-secondary">View All (<?php echo $total_my_surveys_numeric_for_user; ?>) →</a>
        <?php endif; ?>
    </div>
    <p>Manage your most recently updated surveys.</p>

    <table class="creator-surveys-table">
        <thead><tr><th>ID</th><th>Title</th><th>Status</th><th>Responses</th><th>Start Date</th><th>End Date</th><th>Last Updated</th><th>Actions</th></tr></thead>
        <tbody>
            <?php
            if ($error_loading_this_user_dashboard_data && empty($recent_surveys_for_this_user)): ?>
                <tr><td colspan="8" class="text-center text-danger">Could not load your recent surveys.</td></tr>
            <?php
            elseif (!empty($recent_surveys_for_this_user)):
                foreach ($recent_surveys_for_this_user as $survey_item):
                    $edit_link_item = append_admin_context_param_for_embedded_creator(BASE_URL."survey_creator/edit_survey.php?id=".$survey_item['survey_id'], $is_being_included_by_admin);
                    $view_design_link_item = append_admin_context_param_for_embedded_creator(BASE_URL."survey_creator/view_survey_detail.php?id=".$survey_item['survey_id'], $is_being_included_by_admin);
                    $view_report_link_item = append_admin_context_param_for_embedded_creator(BASE_URL."survey_creator/view_report.php?id=".$survey_item['survey_id'], $is_being_included_by_admin);
                    
                    $delete_base_url_for_action = $is_being_included_by_admin ? BASE_URL."admin/index.php?page=create_survey_admin_view" : BASE_URL."survey_creator/index.php";
                    $delete_draft_link_for_item = htmlspecialchars($delete_base_url_for_action . "&".$creator_page_action_param_key."=delete_draft&".$creator_page_survey_id_param_key."=" . $survey_item['survey_id']);
                ?>
                <tr>
                    <td data-label='ID'><?php echo htmlspecialchars($survey_item['survey_id']); ?></td>
                    <td data-label='Title'><a href="<?php echo htmlspecialchars($edit_link_item); ?>" title="Manage survey"><?php echo htmlspecialchars(substr($survey_item['title'],0,40)).(strlen($survey_item['title'])>40?'...':''); ?></a></td>
                    <td data-label='Status'><span class='status-<?php echo htmlspecialchars($survey_item['status']); ?>'><?php echo htmlspecialchars(ucfirst($survey_item['status'])); ?></span></td>
                    <td data-label='Responses'><?php echo htmlspecialchars($survey_item['total_responses']); ?></td>
                    <td data-label='Start Date'><?php echo isset($survey_item['start_date']) && $survey_item['start_date'] ? date("M d, Y H:i", strtotime($survey_item['start_date'])) : 'N/A'; ?></td>
                    <td data-label='End Date'><?php echo isset($survey_item['end_date']) && $survey_item['end_date'] ? date("M d, Y H:i", strtotime($survey_item['end_date'])) : 'N/A'; ?></td>
                    <td data-label='Last Updated'><?php echo date("M d, Y H:i", strtotime($survey_item['updated_at'])); ?></td>
                    <td data-label='Actions' class='action-links'>
                        <a href='<?php echo htmlspecialchars($view_design_link_item); ?>' class='button-small view-link'>Design</a>
                        <a href='<?php echo htmlspecialchars($edit_link_item); ?>' class='button-small edit-link'>Manage</a>
                        <?php if(is_numeric($survey_item['total_responses'])&&(int)$survey_item['total_responses']>0||$survey_item['status']==='closed'):?><a href='<?php echo htmlspecialchars($view_report_link_item); ?>' class='button-small view-link'>Report</a><?php endif; ?>
                        <?php if($survey_item['status']==='draft'):?><a href='<?php echo $delete_draft_link_for_item; ?>' class='button-small delete-link' onclick='return confirm("Are you sure you want to delete this draft survey?")'>Del Draft</a><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="8" style="text-align:center;">No surveys created by you yet. <a href="<?php echo htmlspecialchars(append_admin_context_param_for_embedded_creator(BASE_URL."survey_creator/create_survey.php",$is_being_included_by_admin)); ?>">Create your first survey!</a></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
if (!$is_being_included_by_admin) {
    require_once __DIR__ . '/_creator_footer.php';
}
?>