<?php

$page_title = "Faculty Survey Dashboard";

require_once __DIR__ . '/../includes/header.php';


if (!isset($_SESSION['user_id']) || !isset($_SESSION['respondent_type'])) {
    
    $_SESSION['message'] = "Please log in to access your faculty dashboard.";
    $_SESSION['message_type'] = "warning";
    header("Location: " . htmlspecialchars(BASE_URL . "login.php"));
    exit();
}



if ($_SESSION['respondent_type'] !== 'faculty' && $_SESSION['role'] !== 'administrator') {
    $_SESSION['message'] = "Access Denied. This dashboard is specifically for faculty members.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . htmlspecialchars(BASE_URL . "dashboard.php")); 
    exit();
}

$current_user_id = $_SESSION['user_id']; 
?>

<div class="respondent-dashboard-header"> 
    <h2>Welcome, Faculty Member!</h2>
    <p>Your participation in these surveys is crucial for curriculum development, institutional research, and university improvement. Thank you for your valuable time and input.</p>
</div>

<div class="survey-list-container" style="margin-top: 20px;">
    <?php
    
    
    
    
    
    $sql_available_surveys = "
        SELECT s.survey_id, s.title, s.description
        FROM surveys s
        WHERE s.status = 'published'
          AND (s.start_date IS NULL OR s.start_date <= NOW())
          AND (s.end_date IS NULL OR s.end_date >= NOW())
          AND (s.target_faculty = TRUE OR s.target_all_users = TRUE) -- Key targeting condition for Faculty
          AND NOT EXISTS ( 
              SELECT 1 
              FROM survey_responses_summary rs 
              WHERE rs.survey_id = s.survey_id 
                AND rs.respondent_user_id = ? 
                AND rs.response_status = 'completed'
          )
        ORDER BY s.created_at DESC, s.survey_id DESC
    ";

    $stmt_available = $conn->prepare($sql_available_surveys);
    $available_surveys_list = [];
    $error_loading_surveys = false; 

    if ($stmt_available) {
        $stmt_available->bind_param("i", $current_user_id); 

        if ($stmt_available->execute()) {
            $result_available = $stmt_available->get_result();
            if ($result_available) {
                while ($row = $result_available->fetch_assoc()) {
                    $available_surveys_list[] = $row;
                }
            } else {
                
                $error_loading_surveys = true;
                error_log("Faculty Dashboard - Get Result failed: " . $conn->error . " | SQL: " . $sql_available_surveys);
            }
        } else {
            
            $error_loading_surveys = true;
            error_log("Faculty Dashboard - Execute failed: " . $stmt_available->error . " | SQL: " . $sql_available_surveys);
        }
        $stmt_available->close();
    } else {
        
        $error_loading_surveys = true;
        error_log("Faculty Dashboard - Prepare failed: " . $conn->error . " | SQL: " . $sql_available_surveys);
    }

    
    if ($error_loading_surveys) {
        echo "<div class='alert alert-danger'>We encountered a technical issue while trying to load available surveys. Please try again later or contact support.</div>";
    } elseif (!empty($available_surveys_list)) {
        foreach ($available_surveys_list as $survey) {
            ?>
            <div class="survey-card"> 
                <h3><?php echo htmlspecialchars($survey['title']); ?></h3>
                <?php if (!empty($survey['description'])): ?>
                    <p class="survey-card-description">
                        <?php 
                        
                        $desc_snippet = substr($survey['description'], 0, 200);
                        echo nl2br(htmlspecialchars($desc_snippet));
                        if (strlen($survey['description']) > 200) {
                            echo '...';
                        }
                        ?>
                    </p>
                <?php endif; ?>
                <p class="survey-card-action">
                    <a href="<?php echo htmlspecialchars(BASE_URL . "respondent/take_survey.php?id=" . $survey['survey_id']); ?>" class="button button-primary">Take Survey →</a>
                </p>
            </div>
            <?php
        }
    } else {
        
        echo "<div class='alert alert-info'>There are currently no new surveys available for faculty members, or you have completed all relevant surveys. Please check back later.</div>";
    }
    ?>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php'; 
?>