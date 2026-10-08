<?php


$admin_page_title = "Admin Dashboard"; 


require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/_admin_header.php';     


$page_to_load = isset($_GET['page']) ? trim($_GET['page']) : 'dashboard';


$total_users = 'N/A'; $total_surveys = 'N/A'; $published_surveys = 'N/A';
$total_responses = 'N/A'; $recent_surveys_admin = []; $pending_support_requests = 0;
$error_loading_admin_dashboard_data = false; 

if ($page_to_load === 'dashboard') {
    $admin_page_title = "Administrator Dashboard";

    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        
        $result = $conn->query("SELECT COUNT(*) as count FROM users");
        if ($result) { $total_users = $result->fetch_assoc()['count'] ?? '0'; $result->free(); }
        else { $error_loading_admin_dashboard_data = true; error_log("AdminDash: UserCountFail: ".$conn->error); }

        
        $result = $conn->query("SELECT COUNT(*) as count FROM surveys");
        if ($result) { $total_surveys = $result->fetch_assoc()['count'] ?? '0'; $result->free(); }
        else { $error_loading_admin_dashboard_data = true; error_log("AdminDash: SurveyCountFail: ".$conn->error); }

        
        $result = $conn->query("SELECT COUNT(*) as count FROM surveys WHERE status = 'published'");
        if ($result) { $published_surveys = $result->fetch_assoc()['count'] ?? '0'; $result->free(); }
        else { $error_loading_admin_dashboard_data = true; error_log("AdminDash: PublishedSurveyFail: ".$conn->error); }

        
        $result = $conn->query("SELECT COUNT(DISTINCT response_summary_id) as count FROM survey_responses_summary WHERE response_status = 'completed'");
        if ($result) { $total_responses = $result->fetch_assoc()['count'] ?? '0'; $result->free(); }
        else { $error_loading_admin_dashboard_data = true; error_log("AdminDash: TotalResponsesFail: ".$conn->error); }

        
        $sql_recent_admin = "SELECT s.survey_id, s.title, s.status, s.updated_at, u.username as creator_username,
                       (SELECT COUNT(DISTINCT srs.response_summary_id) FROM survey_responses_summary srs WHERE srs.survey_id = s.survey_id AND srs.response_status = 'completed') as response_count 
                       FROM surveys s LEFT JOIN users u ON s.created_by_user_id = u.user_id
                       WHERE s.status IN ('published', 'closed') ORDER BY s.updated_at DESC, s.created_at DESC LIMIT 5";
        $result_recent_admin_q = $conn->query($sql_recent_admin);
        if ($result_recent_admin_q) { while ($row = $result_recent_admin_q->fetch_assoc()) { $recent_surveys_admin[] = $row; } $result_recent_admin_q->free();
        } else { $error_loading_admin_dashboard_data = true; error_log("AdminDash: RecentSurveysFail: " . $conn->error); }

    } else {
        $error_loading_admin_dashboard_data = true;
        $db_err_msg_admin_idx = isset($conn) ? $conn->connect_error : "Conn obj not init in admin/index.";
        error_log("Admin Dashboard - DB Conn Err: " . $db_err_msg_admin_idx);
        if(!isset($_SESSION['message'])) { $_SESSION['message'] = "DB conn err. Stats not loaded."; $_SESSION['message_type'] = "danger"; }
        $total_users = $total_surveys = $published_surveys = $total_responses = 'DB Err';
    }
}

if (!function_exists('append_admin_context_param_for_admin_pages')) {
    function append_admin_context_param_for_admin_pages($url) {
        
        
        
        return $url . (strpos($url, '?') === false ? '?' : '&') . 'from_admin_panel=true';
    }
}



switch ($page_to_load) {
    case 'users':               $admin_page_title = "Manage Users"; include __DIR__ . '/users.php'; break;
    case 'templates':           $admin_page_title = "Manage Templates"; include __DIR__ . '/templates.php'; break;
    case 'template_questions':  include __DIR__ . '/template_questions.php'; break;
    case 'surveys':             $admin_page_title = "All Surveys"; include __DIR__ . '/surveys.php'; break;
    case 'survey_responses':    include __DIR__ . '/survey_responses.php'; break;
    case 'reports':             $admin_page_title = "Reports Overview"; include __DIR__ . '/reports.php'; break;
    case 'survey_report_detail':include __DIR__ . '/survey_report_detail.php'; break;
    case 'support_tickets':     $admin_page_title = "Support Tickets"; include __DIR__ . '/support_tickets.php'; break;

    case 'create_survey_admin_view':
        $admin_page_title = "Create/Manage Your Surveys (Admin)";
        $is_admin_in_creator_context = true; 
        $creator_page_title = $admin_page_title; 

        echo "<div class='admin-embedded-creator-interface'>";
        include __DIR__ . '/../survey_creator/index.php'; 
        echo "</div>";
        break;

    case 'dashboard':
    default:
?>
        <h2>Admin Dashboard Overview</h2>
        <p>Welcome! Manage system components and monitor key metrics.</p>
        
        <div class="admin-stats-cards-container">
            <div class="stat-card"><div class="stat-card-icon"><span></span></div><div class="stat-card-info"><h4>Total Users</h4><p class="stat-number"><?php echo htmlspecialchars($total_users); ?></p></div><a href="<?php echo htmlspecialchars(BASE_URL."admin/index.php?page=users");?>" class="stat-card-link">Manage →</a></div>
            <div class="stat-card"><div class="stat-card-icon"><span></span></div><div class="stat-card-info"><h4>Total Surveys</h4><p class="stat-number"><?php echo htmlspecialchars($total_surveys); ?></p></div><a href="<?php echo htmlspecialchars(BASE_URL."admin/index.php?page=surveys");?>" class="stat-card-link">Manage →</a></div>
            <div class="stat-card"><div class="stat-card-icon"><span></span></div><div class="stat-card-info"><h4>Published Surveys</h4><p class="stat-number"><?php echo htmlspecialchars($published_surveys); ?></p></div><a href="<?php echo htmlspecialchars(BASE_URL."admin/index.php?page=surveys&filter_status=published");?>" class="stat-card-link">View →</a></div>
            <div class="stat-card"><div class="stat-card-icon"><span></span></div><div class="stat-card-info"><h4>Completed Responses</h4><p class="stat-number"><?php echo htmlspecialchars($total_responses); ?></p></div><a href="<?php echo htmlspecialchars(BASE_URL."admin/index.php?page=reports");?>" class="stat-card-link">Reports →</a></div>
            <?php if (is_numeric($pending_support_requests) && $pending_support_requests > 0): ?>
            <div class="stat-card urgent-card"><div class="stat-card-icon"><span></span></div><div class="stat-card-info"><h4>Pending Support</h4><p class="stat-number"><?php echo htmlspecialchars($pending_support_requests);?></p></div><a href="<?php echo htmlspecialchars(BASE_URL."admin/index.php?page=support_tickets");?>" class="stat-card-link">View →</a></div>
            <?php endif; ?>
        </div>
        <div class="admin-dashboard-section">
            <h3>Recently Active/Updated Surveys (System-Wide)</h3>
            <?php if ($error_loading_admin_dashboard_data && empty($recent_surveys_admin)): ?><p class="alert alert-warning">Could not load recent surveys data.</p>
            <?php elseif (!empty($recent_surveys_admin)): ?>
                <table class="admin-table recent-surveys-table">
                    <thead><tr><th>ID</th><th>Title</th><th>Creator</th><th>Status</th><th>Responses</th><th>Last Updated</th><th>Actions</th></tr></thead>
                    <tbody><?php foreach ($recent_surveys_admin as $rs): ?><tr>
                        <td><?php echo htmlspecialchars($rs['survey_id']); ?></td>
                        <td><a href="<?php echo htmlspecialchars((BASE_URL."survey_creator/edit_survey.php?id=".$rs['survey_id'])); ?>" title="Manage: <?php echo htmlspecialchars($rs['title']); ?>"><?php echo htmlspecialchars(substr($rs['title'],0,40)).(strlen($rs['title'])>40?'...':''); ?></a></td>
                        <td><?php echo htmlspecialchars($rs['creator_username'] ?? 'N/A'); ?></td>
                        <td><span class="status-<?php echo htmlspecialchars($rs['status']); ?>"><?php echo htmlspecialchars(ucfirst($rs['status'])); ?></span></td>
                        <td><?php echo htmlspecialchars($rs['response_count']); ?></td>
                        <td><?php echo date("M d, Y H:i", strtotime($rs['updated_at'])); ?></td>
                        <td class="action-links">
                            <a href="<?php echo htmlspecialchars(BASE_URL."admin/index.php?page=survey_responses&survey_id=".$rs['survey_id']); ?>" class="button-small view-link">Responses</a>
                            <a href="<?php echo htmlspecialchars((BASE_URL."survey_creator/edit_survey.php?id=".$rs['survey_id'])); ?>" class="button-small edit-link">Manage</a>
                        </td></tr><?php endforeach; ?></tbody></table>
            <?php else: ?><p>No recently active or updated surveys found in the system.</p><?php endif; ?>
        </div>
<?php
        break;
}

require_once __DIR__ . '/_admin_footer.php';
?>