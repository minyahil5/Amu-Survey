<?php

$page_title = "Dashboard";
require_once __DIR__ . '/includes/header.php'; 


if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) { 
    $_SESSION['message'] = "You must log in to view this page.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User';
$role = $_SESSION['role']; 





switch ($role) {
    case 'administrator':
        
        header("Location: " . BASE_URL . "admin/index.php");
        exit();
        
        
        break;
    case 'survey_creator':
        
        header("Location: " . BASE_URL . "survey_creator/index.php");
        exit();
        
        
        break;
    case 'respondent':
        
        
        
        $dashboard_content_for_role = "respondent";
        break;
    default:
        
        $_SESSION['message'] = "Your user role is not recognized. Please contact support.";
        $_SESSION['message_type'] = "warning";
        
        
        header("Location: " . BASE_URL . "login.php");
        exit();
}


?>

<div class="dashboard-container" style="text-align: center; padding-top: 30px;">
    <h2>Welcome, <?php echo $username; ?>!</h2>
    <p style="font-size: 1.1em; color: #555;">You are logged in as a: <strong><?php echo ucfirst(str_replace('_', ' ', $role)); ?></strong></p>

    <div class="dashboard-actions" style="margin-top: 30px; display: flex; flex-direction: column; align-items: center; gap: 15px;">
        <?php
        
        if ($dashboard_content_for_role === 'administrator'):
        ?>
            <p>From here, you can manage the entire survey system.</p>
            <a href="<?php echo BASE_URL; ?>admin/index.php?page=users" class="button button-primary" style="min-width: 200px;">Manage Users</a>
            <a href="<?php echo BASE_URL; ?>admin/index.php?page=templates" class="button button-primary" style="min-width: 200px;">Manage Templates</a>
            <a href="<?php echo BASE_URL; ?>admin/index.php?page=surveys" class="button button-primary" style="min-width: 200px;">Manage All Surveys</a>
            <a href="<?php echo BASE_URL; ?>admin/index.php?page=reports" class="button button-primary" style="min-width: 200px;">View System Reports</a>
            <a href="<?php echo BASE_URL; ?>admin/index.php" class="button button-secondary" style="min-width: 200px;">Go to Admin Dashboard</a>

        <?php
        elseif ($dashboard_content_for_role === 'survey_creator'):
        ?>
            <p>Manage your surveys, create new ones, and view your reports.</p>
            <a href="<?php echo BASE_URL; ?>survey_creator/create_survey.php" class="button button-primary" style="min-width: 220px;">Create New Survey</a>
            <a href="<?php echo BASE_URL; ?>survey_creator/index.php" class="button button-primary" style="min-width: 220px;">View My Surveys</a>
            
            <a href="<?php echo BASE_URL; ?>survey_creator/index.php" class="button button-secondary" style="min-width: 220px;">Go to Creator Dashboard</a>

        <?php
        elseif ($dashboard_content_for_role === 'respondent'):
        ?>
            <p>Thank you for participating! Here are surveys available for you.</p>
            <a href="<?php echo BASE_URL; ?>respondent/available_surveys.php" class="button button-primary" style="font-size:1.2em; padding: 15px 30px;">View Available Surveys</a>
            
        <?php
        endif;
        ?>
    </div>

    <p style="margin-top: 40px;">
        <a href="<?php echo BASE_URL; ?>logout.php" class="button button-secondary" style="background-color: #777; color:white;">Logout</a>
    </p>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>