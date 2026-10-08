<?php

$creator_page_title = "Survey Report"; 
require_once __DIR__ . '/_creator_header.php'; 

$survey_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];

if ($survey_id <= 0) {
    $_SESSION['message'] = "Invalid Survey ID provided for report.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . BASE_URL . "survey_creator/index.php");
    exit();
}


$sql_survey = "SELECT s.*, u.username as creator_username
               FROM surveys s
               LEFT JOIN users u ON s.created_by_user_id = u.user_id
               WHERE s.survey_id = ?";
$stmt_survey = $conn->prepare($sql_survey);
if (!$stmt_survey) {
    error_log("Prepare failed (survey fetch): " . $conn->error);
    $_SESSION['message'] = "Error preparing to fetch survey details.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . BASE_URL . "survey_creator/index.php");
    exit();
}
$stmt_survey->bind_param("i", $survey_id);
$stmt_survey->execute();
$result_survey = $stmt_survey->get_result();
$survey = $result_survey->fetch_assoc();
$stmt_survey->close();

if (!$survey) {
    $_SESSION['message'] = "Survey not found (ID: $survey_id).";
    $_SESSION['message_type'] = "danger";
    header("Location: " . BASE_URL . "survey_creator/index.php");
    exit();
}


if ($survey['created_by_user_id'] !== $current_user_id && $current_user_role !== 'administrator') {
    $_SESSION['message'] = "You do not have permission to view this survey report.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . BASE_URL . "survey_creator/index.php");
    exit();
}

$creator_page_title = "Report: " . htmlspecialchars($survey['title']); 


$stmt_responses_count = $conn->prepare("SELECT COUNT(*) as total_completed FROM survey_responses_summary WHERE survey_id = ? AND response_status = 'completed'");
if (!$stmt_responses_count) {
    error_log("Prepare failed (responses count): " . $conn->error);
    
    $total_completed_responses = "Error";
} else {
    $stmt_responses_count->bind_param("i", $survey_id);
    $stmt_responses_count->execute();
    $total_completed_responses = $stmt_responses_count->get_result()->fetch_assoc()['total_completed'] ?? 0;
    $stmt_responses_count->close();
}



$stmt_questions = $conn->prepare("SELECT * FROM questions WHERE survey_id = ? ORDER BY order_in_survey ASC");
$questions = [];
if ($stmt_questions) {
    $stmt_questions->bind_param("i", $survey_id);
    $stmt_questions->execute();
    $result_questions = $stmt_questions->get_result();
    while ($q_row = $result_questions->fetch_assoc()) {
        $questions[] = $q_row;
    }
    $stmt_questions->close();
} else {
    error_log("Prepare failed (questions fetch): " . $conn->error);
    
}

?>
<div class="survey-report-header" style="padding-bottom:15px; border-bottom: 1px solid #e0e0e0; margin-bottom:25px;">
    <h2>Report for: <?php echo htmlspecialchars($survey['title']); ?></h2>
    <p style="font-size: 0.95em; color: #555;">
        <strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($survey['status'])); ?> |
        <?php if ($current_user_role === 'administrator' && $survey['created_by_user_id'] !== $current_user_id): ?>
            <strong>Creator:</strong> <?php echo htmlspecialchars($survey['creator_username'] ?? 'N/A'); ?> |
        <?php endif; ?>
        <strong>Total Completed Responses:</strong> <?php echo $total_completed_responses; ?>
    </p>
    <p style="margin-top:15px;">
        <a href="<?php echo BASE_URL; ?>survey_creator/index.php" class="button button-secondary">← Back to My Surveys</a>
        
    </p>
</div>


<?php if ($total_completed_responses > 0 && !empty($questions)): ?>
    <h3>Response Summary by Question:</h3>
    <?php foreach ($questions as $question_idx => $question): ?>
        <div class="question-report-block" style="margin-bottom: 30px; padding:20px; border: 1px solid #e9ecef; border-radius:6px; background-color:#fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <h4><?php echo htmlspecialchars(($question_idx + 1) . '. ' . $question['question_text']); ?></h4>
            <p style="font-size:0.9em; color:#777; margin-top:-5px; margin-bottom:15px;"><em>Type: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $question['question_type']))); ?></em></p>

            <?php
            
            switch ($question['question_type']) {
                case 'mcq_single':
                case 'mcq_multiple':
                case 'likert_scale':
                case 'rating_scale':
                    
                    $sql_options = "SELECT qo.option_id, qo.option_text, qo.option_value, COUNT(a.answer_id) as response_count
                                    FROM question_options qo
                                    LEFT JOIN answers a ON qo.option_id = a.selected_option_id
                                        AND a.question_id = ?
                                        AND a.response_summary_id IN (SELECT rs.response_summary_id FROM survey_responses_summary rs WHERE rs.survey_id = ? AND rs.response_status = 'completed')
                                    WHERE qo.question_id = ?
                                    GROUP BY qo.option_id, qo.option_text, qo.option_value
                                    ORDER BY qo.order_in_question ASC";
                    $stmt_q_options = $conn->prepare($sql_options);
                    if ($stmt_q_options) {
                        $stmt_q_options->bind_param("iii", $question['question_id'], $survey_id, $question['question_id']);
                        $stmt_q_options->execute();
                        $result_q_options = $stmt_q_options->get_result();

                        if ($result_q_options->num_rows > 0) {
                            echo "<ul style='list-style-type: none; padding-left: 0;'>";
                            while ($opt_row = $result_q_options->fetch_assoc()) {
                                $percentage = ($total_completed_responses > 0 && $opt_row['response_count'] > 0) ? round(($opt_row['response_count'] / $total_completed_responses) * 100, 1) : 0;
                                
                                
                                echo "<li style='margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px dashed #f0f0f0;'>";
                                echo htmlspecialchars($opt_row['option_text']) . ": ";
                                echo "<strong>" . $opt_row['response_count'] . "</strong> ";
                                echo "(<em>" . $percentage . "%</em>)";
                                
                                echo "<div style='height: 10px; background-color: #e9ecef; border-radius: 5px; margin-top:4px; overflow:hidden;'>
                                        <div style='width: " . $percentage . "%; height: 100%; background-color: #3498db; border-radius: 5px;'></div>
                                      </div>";
                                echo "</li>";
                            }
                            echo "</ul>";
                        } else {
                            echo "<p><em>No predefined options for this question or no responses matching options.</em></p>";
                        }
                        $stmt_q_options->close();
                    } else {
                        echo "<p><em>Error preparing options query: " . htmlspecialchars($conn->error) . "</em></p>";
                    }
                    break;

                case 'open_ended_short':
                case 'open_ended_long':
                    $sql_open_ended = "SELECT a.answer_text
                                       FROM answers a
                                       JOIN survey_responses_summary rs ON a.response_summary_id = rs.response_summary_id
                                       WHERE a.question_id = ? AND rs.survey_id = ? AND rs.response_status = 'completed' AND a.answer_text IS NOT NULL AND a.answer_text != ''
                                       ORDER BY RAND() LIMIT 10"; 
                    $stmt_open_ended = $conn->prepare($sql_open_ended);
                    if ($stmt_open_ended) {
                        $stmt_open_ended->bind_param("ii", $question['question_id'], $survey_id);
                        $stmt_open_ended->execute();
                        $result_open_ended = $stmt_open_ended->get_result();

                        if ($result_open_ended->num_rows > 0) {
                            echo "<ul class='open-ended-responses' style='list-style-type: none; padding-left: 0; max-height: 250px; overflow-y: auto;'>";
                            while ($ans_row = $result_open_ended->fetch_assoc()) {
                                echo "<li style='padding: 8px; margin-bottom: 5px; background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; font-size:0.9em;'>" . nl2br(htmlspecialchars($ans_row['answer_text'])) . "</li>";
                            }
                            echo "</ul>";
                            if ($result_open_ended->num_rows >= 10) { 
                                echo "<p style='font-size:0.85em; font-style:italic; color:#777;'>Showing a random sample of 10 responses. Full data would require export.</p>";
                            }
                        } else {
                            echo "<p><em>No open-ended responses submitted for this question.</em></p>";
                        }
                        $stmt_open_ended->close();
                    } else {
                        echo "<p><em>Error preparing open-ended query: " . htmlspecialchars($conn->error) . "</em></p>";
                    }
                    break;

                default:
                    echo "<p><em>Reporting for this question type ('" . htmlspecialchars($question['question_type']) . "') is not yet available.</em></p>";
                    break;
            }
            ?>
        </div>
    <?php endforeach; ?>
<?php elseif ($total_completed_responses == 0 && $survey): ?>
    <div class="alert alert-info">There are no completed responses for this survey yet.</div>
<?php else: ?>
    <div class="alert alert-warning">Could not load survey data or questions for the report. Please try again or contact support.</div>
<?php endif; ?>

<?php
require_once __DIR__ . '/_creator_footer.php';
?>