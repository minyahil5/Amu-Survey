<?php




if (session_status() == PHP_SESSION_NONE) {
    session_start();
}




if (!defined('BASE_URL')) {
    
    @include_once __DIR__ . '/../config/config.php';
    if (!defined('BASE_URL')) {
        die("CRITICAL ERROR: BASE_URL not defined in _creator_header.php. Check include order.");
    }
}
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'AMU Survey System'); 
}


if (!isset($conn) || !($conn instanceof mysqli)) {
    @include_once __DIR__ . '/../includes/db_connect.php'; 
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
        $db_err = isset($conn) && $conn->connect_error ? $conn->connect_error : "Connection object invalid.";
        error_log("SurveyCreator Header: DB Connection Error - " . $db_err);
        
        
    }
}



if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['survey_creator', 'administrator'])) {
    $_SESSION['message'] = "Access Denied: You do not have permission to access the survey creator tools.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . htmlspecialchars(BASE_URL . "login.php?access_denied=true"));
    exit();
}


$is_admin_in_creator_context = (
    $_SESSION['role'] === 'administrator' &&
    isset($_GET['from_admin_panel']) && $_GET['from_admin_panel'] === 'true'
);


$current_script_name_creator = basename($_SERVER['PHP_SELF']);
$active_nav_item_creator = ''; 

if ($is_admin_in_creator_context) {
    
    if ($current_script_name_creator === 'create_survey.php') {
        $active_nav_item_creator = 'create_survey_admin'; 
    } elseif ($current_script_name_creator === 'edit_survey.php' || $current_script_name_creator === 'view_survey_detail.php' || $current_script_name_creator === 'view_report.php') {
        $active_nav_item_creator = 'manage_all_surveys_admin'; 
    } else { 
         $active_nav_item_creator = 'manage_all_surveys_admin';
    }
} else { 
    if ($current_script_name_creator === 'index.php') {
        $active_nav_item_creator = 'my_surveys';
    } elseif ($current_script_name_creator === 'create_survey.php') {
        $active_nav_item_creator = 'create_survey';
    } elseif ($current_script_name_creator === 'edit_survey.php' || $current_script_name_creator === 'view_survey_detail.php' || $current_script_name_creator === 'view_report.php') {
        $active_nav_item_creator = 'my_surveys'; 
    }
    
}


$current_page_title_creator_tab = isset($creator_page_title) ? htmlspecialchars($creator_page_title) : 'Survey Management';


function append_admin_context_to_url($url, $is_admin_context_active) {
    if (!$is_admin_context_active) return $url;
    return $url . (strpos($url, '?') === false ? '?' : '&') . 'from_admin_panel=true';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $current_page_title_creator_tab . ' | ' . htmlspecialchars(SITE_NAME); ?></title>

    <link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL); ?>css/style.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL); ?>survey_creator/css/creator_style.css?v=<?php echo time(); ?>">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    
    <?php echo isset($creator_page_specific_head) ? $creator_page_specific_head : ''; ?>
</head>
<body class="survey-creator-page <?php if ($is_admin_in_creator_context) echo 'admin-viewing-creator-tools'; ?>">

    <header class="creator-main-header"> 
        <h1><?php echo htmlspecialchars(SITE_NAME); ?> - <span>
            <?php echo $is_admin_in_creator_context ? 'Survey Management (Admin Context)' : 'Survey Creator Panel'; ?>
        </span></h1>
    </header>

    <nav class="creator-main-nav"> 
        <div class="creator-nav-left">
            <?php if ($is_admin_in_creator_context): ?>
                <a href="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=dashboard"); ?>" class="nav-item">← Back to Admin Dashboard</a>
                <a href="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=surveys"); ?>" 
                   class="nav-item <?php echo ($active_nav_item_creator === 'manage_all_surveys_admin') ? 'active' : ''; ?>">
                   Admin: All Surveys
                </a>
            <?php else: ?>
                <a href="<?php echo htmlspecialchars(BASE_URL . "survey_creator/index.php"); ?>" 
                   class="nav-item <?php echo ($active_nav_item_creator === 'my_surveys') ? 'active' : ''; ?>">My Surveys</a>
            <?php endif; ?>

            <a href="<?php echo htmlspecialchars(append_admin_context_to_url(BASE_URL . "survey_creator/create_survey.php", $is_admin_in_creator_context)); ?>"
               class="nav-item <?php echo ($active_nav_item_creator === ($is_admin_in_creator_context ? 'create_survey_admin' : 'create_survey') ) ? 'active' : ''; ?>">
               Create New Survey
            </a>
            
            <?php
            
            
            if (isset($survey_id) && $survey_id > 0 && basename($_SERVER['PHP_SELF']) !== 'create_survey.php'):
                $edit_link_url = BASE_URL . "survey_creator/edit_survey.php?id=" . $survey_id;
                $is_current_edit_page_active = ($current_script_name_creator === 'edit_survey.php' && !$is_admin_in_creator_context && $active_nav_item_creator === 'my_surveys'); 
            ?>
                
            <?php endif; ?>
        </div>
        
        <div class="creator-nav-right">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>! 
                (<?php echo htmlspecialchars(ucfirst($_SESSION['role'])); 
                if ($_SESSION['role'] === 'respondent' && isset($_SESSION['respondent_type'])) { echo '/'.ucfirst(htmlspecialchars($_SESSION['respondent_type']));} ?>)
            </span>
            <a href="<?php echo htmlspecialchars(BASE_URL . "logout.php"); ?>" class="logout-button button button-small">Logout</a>
        </div>
    </nav>

    <div class="creator-container"> 
        <?php
        
        if (isset($_SESSION['message'])): ?>
            <div class="alert <?php echo 'alert-' . htmlspecialchars($_SESSION['message_type'] ?? 'info'); ?>">
                <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
            </div>
        <?php endif; ?>
        