<?php

$creator_page_title = "View Survey Design"; 
$active_nav_item = 'my_surveys'; 
require_once __DIR__ . '/_creator_header.php';

$survey_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];

if ($survey_id <= 0) {
    $_SESSION['message'] = "Invalid Survey ID provided.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . htmlspecialchars(BASE_URL . "survey_creator/index.php"));
    exit();
}


$sql_survey = "SELECT s.*, u.username as creator_username, t.template_name
               FROM surveys s
               LEFT JOIN users u ON s.created_by_user_id = u.user_id
               LEFT JOIN survey_templates t ON s.template_id = t.template_id
               WHERE s.survey_id = ?";
$stmt_survey = $conn->prepare($sql_survey);
if (!$stmt_survey) {
    error_log("Prepare failed (survey fetch for view_survey_detail): " . $conn->error);
    $_SESSION['message'] = "Error preparing to fetch survey details.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . htmlspecialchars(BASE_URL . "survey_creator/index.php"));
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
    header("Location: " . htmlspecialchars(BASE_URL . "survey_creator/index.php"));
    exit();
}


if ($survey['created_by_user_id'] !== $current_user_id && $current_user_role !== 'administrator') {
    $_SESSION['message'] = "You do not have permission to view this survey design.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . htmlspecialchars(BASE_URL . "survey_creator/index.php"));
    exit();
}

$creator_page_title = "View Design: " . htmlspecialchars($survey['title']);


$stmt_questions = $conn->prepare("SELECT * FROM questions WHERE survey_id = ? ORDER BY order_in_survey ASC, question_id ASC");
$questions_data = [];
if ($stmt_questions) {
    $stmt_questions->bind_param("i", $survey_id);
    $stmt_questions->execute();
    $result_questions = $stmt_questions->get_result();
    while ($q_row = $result_questions->fetch_assoc()) {
        $q_row['options'] = [];
        if (in_array($q_row['question_type'], ['mcq_single', 'mcq_multiple', 'likert_scale', 'rating_scale'])) {
            $stmt_options = $conn->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY order_in_question ASC, option_id ASC");
            if ($stmt_options) {
                $stmt_options->bind_param("i", $q_row['question_id']);
                $stmt_options->execute();
                $result_options = $stmt_options->get_result();
                while ($opt_row = $result_options->fetch_assoc()) {
                    $q_row['options'][] = $opt_row;
                }
                $stmt_options->close();
            } else { error_log("ViewSurveyDetail: Options prepare failed for QID ".$q_row['question_id'].": ".$conn->error); }
        }
        $questions_data[] = $q_row;
    }
    $stmt_questions->close();
} else {
    error_log("Prepare failed (questions fetch for view_survey_detail): " . $conn->error);
    
}

?>

<div class="survey-detail-header">
    <h2><?php echo htmlspecialchars($survey['title']); ?></h2>
    <p class="survey-meta">
        <strong>Status:</strong> <span class="status-<?php echo htmlspecialchars($survey['status']); ?>"><?php echo htmlspecialchars(ucfirst($survey['status'])); ?></span> |
        <strong>Anonymous:</strong> <?php echo $survey['allow_anonymous'] ? 'Yes' : 'No'; ?> |
        <strong>Created:</strong> <?php echo date("M d, Y H:i", strtotime($survey['created_at'])); ?>
        <?php if ($survey['start_date']): ?>
            | <strong>Starts:</strong> <?php echo date("M d, Y H:i", strtotime($survey['start_date'])); ?>
        <?php endif; ?>
        <?php if ($survey['end_date']): ?>
            | <strong>Ends:</strong> <?php echo date("M d, Y H:i", strtotime($survey['end_date'])); ?>
        <?php endif; ?>
        <?php if (!empty($survey['template_name'])): ?>
            | <span title="This survey was created using this template.">Based on Template: <?php echo htmlspecialchars($survey['template_name']); ?></span>
        <?php endif; ?>
         <?php if ($current_user_role === 'administrator' && $survey['creator_username']): ?>
            | <strong>Creator:</strong> <?php echo htmlspecialchars($survey['creator_username']); ?>
        <?php endif; ?>
    </p>
    <?php if (!empty($survey['description'])): ?>
        <div class="survey-description-view">
            <strong>Description:</strong>
            <p><?php echo nl2br(htmlspecialchars($survey['description'])); ?></p>
        </div>
    <?php endif; ?>
    <p style="margin-top:20px;">
        <a href="<?php echo htmlspecialchars(BASE_URL . "survey_creator/index.php"); ?>" class="button button-secondary">← Back to My Surveys</a>
        <a href="<?php echo htmlspecialchars(BASE_URL . "survey_creator/edit_survey.php?id=" . $survey_id); ?>" class="button button-primary" style="margin-left:10px;">Edit This Survey</a>
        
    </p>
</div>

<hr style="margin: 25px 0;">

<?php if (!empty($questions_data)): ?>
    <h3>Survey Questions:</h3>
    <div class="survey-questions-view-list">
        <?php foreach ($questions_data as $index => $question): ?>
            <div class="question-view-item">
                <p class="question-text-view">
                    <strong><?php echo ($index + 1); ?>. <?php echo htmlspecialchars($question['question_text']); ?></strong>
                    <?php if ($question['is_required']): ?> <span class="required-indicator" title="This question is required">*</span><?php endif; ?>
                </p>
                <p class="question-type-view"><em>Type: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $question['question_type']))); ?></em></p>

                <?php if (!empty($question['options'])): ?>
                    <ul class="question-options-view">
                        <?php foreach ($question['options'] as $option): ?>
                            <li>
                                <?php echo htmlspecialchars($option['option_text']); ?>
                                <?php if (isset($option['option_value']) && $option['option_value'] !== '' && $option['option_value'] !== $option['option_text']): ?>
                                    <span style="font-size:0.85em; color:#777;">(Value: <?php echo htmlspecialchars($option['option_value']); ?>)</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif (in_array($question['question_type'], ['open_ended_short', 'open_ended_long'])): ?>
                    <div class="open-ended-placeholder">[ Text input area for respondent ]</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php elseif ($survey): ?>
    <div class="alert alert-info">This survey does not have any questions defined yet. <a href="<?php echo htmlspecialchars(BASE_URL . "survey_creator/edit_survey.php?id=" . $survey_id); ?>">Add questions now</a>.</div>
<?php endif; ?>


<?php
require_once __DIR__ . '/_creator_footer.php';
?>