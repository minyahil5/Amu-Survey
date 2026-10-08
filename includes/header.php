<?php



if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


if (!defined('BASE_URL')) {
    @include_once __DIR__ . '/../config/config.php';
    if (!defined('BASE_URL')) { 
        
        
        
        
        
        die("Critical Error: BASE_URL is not defined. Please check your config.php setup.");
    }
}


if (!isset($conn)) {
    @include_once __DIR__ . '/db_connect.php';
    if (!isset($conn) && basename($_SERVER['PHP_SELF']) !== 'login.php' && basename($_SERVER['PHP_SELF']) !== 'register.php') {
        
        
        
    }
}


$body_classes_value = isset($body_class) ? htmlspecialchars($body_class) : '';
$container_classes_value = isset($page_container_class) ? htmlspecialchars($page_container_class) : '';



$is_admin_section = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/admin/') !== false);
$is_creator_section = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/survey_creator/') !== false);




$is_subsection_header = (isset($is_admin_specific_header) && $is_admin_specific_header === true) ||
                        (isset($is_creator_specific_header) && $is_creator_specific_header === true);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | ' : ''; echo htmlspecialchars(SITE_NAME ?? 'Survey System'); ?></title>

<link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL); ?>css/style.css">


<?php if ($is_admin_section && file_exists(__DIR__ . '/../admin/css/admin_style.css')): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL); ?>admin/css/admin_style.css">
<?php elseif ($is_creator_section && file_exists(__DIR__ . '/../survey_creator/css/creator_style.css')): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL); ?>survey_creator/css/creator_style.css">
<?php endif; ?>



<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">


<?php echo isset($page_specific_head) ? $page_specific_head : ''; ?>
</head>
<body class="<?php echo $body_classes_value; ?>">
<?php




if (!$is_subsection_header && !$is_admin_section && !$is_creator_section):
?>
<header class="site-public-header"> 
    <div class="header-content container"> 
        <h1><a<?php echo htmlspecialchars(BASE_URL); ?> class="fuc"><?php echo htmlspecialchars(SITE_NAME ?? 'AMU Surveys'); ?> </a></h1>
        <nav class="public-main-nav">
            <ul>
                <li><a href="<?php echo htmlspecialchars(BASE_URL); ?>">Home</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="<?php echo htmlspecialchars(BASE_URL . 'dashboard.php'); ?>">My Dashboard</a></li>
                    <li><a href="<?php echo htmlspecialchars(BASE_URL . 'logout.php'); ?>">Logout (<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>)</a></li>
                <?php else: ?>
                    <li><a href="<?php echo htmlspecialchars(BASE_URL . 'respondent/available_surveys.php'); ?>">Available Surveys</a></li>
                    <li><a href="<?php echo htmlspecialchars(BASE_URL . 'login.php'); ?>">Login</a></li>
                    <li><a href="<?php echo htmlspecialchars(BASE_URL . 'register.php'); ?>">Register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>
<?php
endif; 
?>

<main class="container <?php echo $container_classes_value; ?>" style="padding-top: 20px; padding-bottom: 20px;"> 
    <?php
    
    if (isset($_SESSION['message'])): ?>
        <div class="alert <?php echo isset($_SESSION['message_type']) ? 'alert-' . htmlspecialchars($_SESSION['message_type']) : 'alert-info'; ?>">
            <?php
            echo htmlspecialchars($_SESSION['message']);
            unset($_SESSION['message']); 
            unset($_SESSION['message_type']);
            ?>
        </div>
    <?php endif; ?>
    