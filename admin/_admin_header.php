<?php






if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    $_SESSION['message'] = "Access Denied: You do not have permission to view the admin area.";
    $_SESSION['message_type'] = "danger";
    
    if (!defined('BASE_URL')) { die("CRITICAL CONFIGURATION ERROR: BASE_URL not defined in admin header. Cannot redirect."); }
    header("Location: " . htmlspecialchars(BASE_URL . "login.php?access_denied=true"));
    exit();
}


if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    $db_conn_error_msg_admin = isset($conn) && $conn->connect_error ? $conn->connect_error : "Connection object is invalid or not set.";
    error_log("Admin Section CRITICAL Error: Database connection problem in _admin_header.php - " . $db_conn_error_msg_admin);
    
    
    
    
}



$current_page_title_for_tab = isset($admin_page_title) ? htmlspecialchars($admin_page_title) : 'Admin Panel';
$current_admin_page_param = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $current_page_title_for_tab . ' | ' . htmlspecialchars(SITE_NAME ?? 'AMU Surveys'); ?></title>

    <link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL); ?>css/style.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL); ?>admin/css/admin_style.css?v=<?php echo time(); ?>">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    
    <?php echo isset($admin_page_specific_head) ? $admin_page_specific_head : ''; ?>
</head>
<body class="admin-page">
    <header class="admin-main-header"> <h1>AMU  Satisfaction Survey System- <i>Administrator Panel</i></h1></header>
    <nav class="admin-main-nav">
        <div class="admin-nav-left">
            <a href="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=dashboard"); ?>" class="nav-item <?php echo ($current_admin_page_param === 'dashboard') ? 'active' : ''; ?>">Dashboard</a>
            <a href="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=users"); ?>" class="nav-item <?php echo ($current_admin_page_param === 'users') ? 'active' : ''; ?>">Users</a>
            
           
          <a href="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=create_survey_admin_view"); ?>" 
   class="nav-item <?php echo ($current_admin_page_param === 'create_survey_admin_view') ? 'active' : ''; ?>">Create Survey</a>
           
            <a href="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=surveys"); ?>" class="nav-item <?php echo ($current_admin_page_param === 'surveys') ? 'active' : ''; ?>">Manage Surveys</a>
          
            <a href="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=reports"); ?>" class="nav-item <?php echo (in_array($current_admin_page_param, ['reports', 'survey_responses', 'survey_report_detail'])) ? 'active' : ''; ?>">Reports</a>
            <a href="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=support_tickets"); ?>" class="nav-item <?php echo ($current_admin_page_param === 'support_tickets') ? 'active' : ''; ?>">Support</a>
        </div>
        <div class="admin-nav-right">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>!</span>
            <a href="<?php echo htmlspecialchars(BASE_URL . "logout.php"); ?>" class="logout-button button button-small">Logout</a>
        </div>
    </nav>
    <div class="admin-container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert <?php echo 'alert-' . htmlspecialchars($_SESSION['message_type'] ?? 'info'); ?>"><?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); unset($_SESSION['message_type']); ?></div>
        <?php endif; ?>