<?php

$admin_page_title = "Survey Report Summary"; 


if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    
    
    $db_err_srd = isset($conn) && $conn->connect_error ? $conn->connect_error : "DB object invalid.";
    error_log("AdminSurveyReportDetail CRITICAL DB Error: " . $db_err_srd);
    
    if(!headers_sent()){
        include __DIR__ . '/../includes/header.php'; 
        echo "<div class='alert alert-danger'>Critical Database Connection Error. Cannot load report details.</div>";
        include __DIR__ . '/../includes/footer.php';
        exit();
    } else {
        die("Critical Database Connection Error. Report details cannot be loaded.");
    }
}


$survey_id = isset($_GET['survey_id']) ? (int)$_GET['survey_id'] : 0;

if ($survey_id <= 0) {
    $_SESSION['message'] = "Invalid Survey ID provided for report.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . htmlspecialchars(BASE_URL . "admin/index.php?page=reports"));
    exit();
}


$survey = null;
$stmt_survey = $conn->prepare("SELECT s.*, u.username as creator_username 
                               FROM surveys s 
                               LEFT JOIN users u ON s.created_by_user_id = u.user_id 
                               WHERE s.survey_id = ?");
if ($stmt_survey) {
    $stmt_survey->bind_param("i", $survey_id);
    $stmt_survey->execute();
    $result_survey = $stmt_survey->get_result();
    if ($result_survey) $survey = $result_survey->fetch_assoc();
    else error_log("AdminReportDetail - GetResult Survey Failed: ".$conn->error);
    $stmt_survey->close();
} else { error_log("AdminReportDetail - Prepare Survey Failed: ".$conn->error); }

if (!$survey) {
    $_SESSION['message'] = "Survey (ID: $survey_id) not found."; $_SESSION['message_type'] = "warning";
    header("Location: " . htmlspecialchars(BASE_URL . "admin/index.php?page=reports")); exit();
}




$total_completed_responses = '0'; 
$stmt_responses_count = $conn->prepare("SELECT COUNT(DISTINCT response_summary_id) as total_completed 
                                        FROM survey_responses_summary 
                                        WHERE survey_id = ? AND response_status = 'completed'");
if ($stmt_responses_count) {
    $stmt_responses_count->bind_param("i", $survey_id);
    $stmt_responses_count->execute();
    $res_count = $stmt_responses_count->get_result();
    if($res_count) $total_completed_responses = $res_count->fetch_assoc()['total_completed'] ?? '0';
    else $total_completed_responses = 'Err';
    $stmt_responses_count->close();
} else { $total_completed_responses = 'Err'; error_log("AdminReportDetail - CountResponses PrepFailed: ".$conn->error); }


$questions_for_report = [];
$sql_questions = "SELECT question_id, question_text, question_type, order_in_survey 
                  FROM questions WHERE survey_id = ? ORDER BY order_in_survey ASC, question_id ASC";
$stmt_q_list = $conn->prepare($sql_questions);
if($stmt_q_list){
    $stmt_q_list->bind_param("i", $survey_id); $stmt_q_list->execute();
    $result_q_list = $stmt_q_list->get_result();
    if($result_q_list) { while($q_row = $result_q_list->fetch_assoc()){ $questions_for_report[] = $q_row; }}
    else { error_log("AdminReportDetail - GetResult Questions Failed: ".$conn->error); }
    $stmt_q_list->close();
} else { error_log("AdminReportDetail - Prepare Questions Failed: ".$conn->error); }

?>
<div class="survey-report-detail-header">
    <h2>Report Summary: <?php echo htmlspecialchars($survey['title']); ?></h2>
    <p class="survey-meta">
        <strong>Status:</strong> <span class="status-<?php echo htmlspecialchars($survey['status']); ?>"><?php echo htmlspecialchars(ucfirst($survey['status'])); ?></span> |
        <strong>Creator:</strong> <?php echo htmlspecialchars($survey['creator_username'] ?? 'N/A'); ?> |
        <strong>Total Completed Responses:</strong> <?php echo htmlspecialchars($total_completed_responses); ?>
    </p>
    <div class="report-actions" style="margin-top:15px; margin-bottom: 20px;">
        <a href="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=reports"); ?>" class="button button-secondary">← Back to Reports Overview</a>
        <?php if (is_numeric($total_completed_responses) && $total_completed_responses > 0): ?>
            <a href="<?php echo htmlspecialchars(BASE_URL . "admin/generate_report.php?survey_id=" . $survey_id . "&format=pdf"); ?>" 
               class="button button-primary3" target="_blank" style="margin-left:10px;">Download PDF Summary</a>
            
            <a href="<?php echo htmlspecialchars(BASE_URL . "admin/generate_report.php?survey_id=" . $survey_id . "&format=csv"); ?>" 
               class="button button-primary" target="_blank" style="margin-left:10px; background-color: #28a745; border-color:#28a745;">Download CSV Data</a>
            
            <a href="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=survey_responses&survey_id=" . $survey_id); ?>"
               class="button button-view-raw" style="margin-left:10px; background-color: #6c757d;">View Raw Responses</a>
        <?php endif; ?>
    </div>
</div>

<hr style="margin: 20px 0;">

<?php if (is_numeric($total_completed_responses) && $total_completed_responses > 0 && !empty($questions_for_report)): ?>
    <h3>Aggregated Response Data by Question:</h3>
    <?php foreach ($questions_for_report as $question_idx => $question): ?>
        <div class="question-summary-block"> 
            <h4><?php echo htmlspecialchars(($question_idx + 1) . '. ' . $question['question_text']); ?></h4>
            <p class="question-type-info"><em>Type: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $question['question_type']))); ?></em></p>

            <div class="aggregated-answers">
            <?php
            
            switch ($question['question_type']) {
                case 'mcq_single': case 'likert_scale': case 'rating_scale':
                    $sql_opt_sum = "SELECT qo.option_id, qo.option_text, COUNT(DISTINCT a.response_summary_id) as response_count FROM question_options qo LEFT JOIN answers a ON qo.option_id = a.selected_option_id AND a.question_id = ? LEFT JOIN survey_responses_summary rs ON a.response_summary_id = rs.response_summary_id AND rs.survey_id = ? AND rs.response_status = 'completed' WHERE qo.question_id = ? GROUP BY qo.option_id, qo.option_text ORDER BY qo.order_in_question ASC, qo.option_id ASC";
                    $stmt_opt = $conn->prepare($sql_opt_sum);
                    if ($stmt_opt) { $stmt_opt->bind_param("iii", $question['question_id'], $survey_id, $question['question_id']); $stmt_opt->execute(); $res_opt = $stmt_opt->get_result();
                        if ($res_opt->num_rows > 0) { echo "<ul class='options-summary-list'>"; while ($opt_r = $res_opt->fetch_assoc()) { $perc = ($total_completed_responses > 0 && $opt_r['response_count'] > 0) ? round(($opt_r['response_count'] / $total_completed_responses) * 100, 1) : 0; echo "<li>".htmlspecialchars($opt_r['option_text']).": <strong>".$opt_r['response_count']."</strong> (<em>".$perc."%</em>)<div class='percentage-bar-container'><div class='percentage-bar' style='width:".$perc."%;'></div></div></li>"; } echo "</ul>";
                        } else { echo "<p><em>No responses for options.</em></p>"; } $stmt_opt->close();
                    } else { echo "<p class='alert-danger'>Err loading options.</p>"; error_log("SRD OptSumPrep:".$conn->error); }
                    break;
                case 'mcq_multiple':
                    $sql_mcq_sum = "SELECT qo.option_id, qo.option_text, COUNT(DISTINCT a_parent.response_summary_id) as response_count FROM question_options qo LEFT JOIN answers_mcq_multiple am ON qo.option_id = am.selected_option_id LEFT JOIN answers a_parent ON am.answer_id = a_parent.answer_id AND a_parent.question_id = ? LEFT JOIN survey_responses_summary rs ON a_parent.response_summary_id = rs.response_summary_id AND rs.survey_id = ? AND rs.response_status = 'completed' WHERE qo.question_id = ? GROUP BY qo.option_id, qo.option_text ORDER BY qo.order_in_question ASC, qo.option_id ASC";
                    $stmt_mcq = $conn->prepare($sql_mcq_sum);
                     if ($stmt_mcq) { $stmt_mcq->bind_param("iii", $question['question_id'], $survey_id, $question['question_id']); $stmt_mcq->execute(); $res_mcq = $stmt_mcq->get_result();
                        if ($res_mcq->num_rows > 0) { echo "<ul class='options-summary-list'>"; while ($opt_r = $res_mcq->fetch_assoc()) { $perc = ($total_completed_responses > 0 && $opt_r['response_count'] > 0) ? round(($opt_r['response_count'] / $total_completed_responses) * 100, 1) : 0; echo "<li>".htmlspecialchars($opt_r['option_text']).": <strong>".$opt_r['response_count']."</strong> (<em>".$perc."% selected</em>)<div class='percentage-bar-container'><div class='percentage-bar' style='width:".$perc."%;'></div></div></li>"; } echo "</ul>";
                        } else { echo "<p><em>No responses for options.</em></p>"; } $stmt_mcq->close();
                    } else { echo "<p class='alert-danger'>Err loading multi-options.</p>"; error_log("SRD MCQSumPrep:".$conn->error); }
                    break;
                case 'open_ended_short': case 'open_ended_long':
                    $sql_open = "SELECT a.answer_text FROM answers a JOIN survey_responses_summary rs ON a.response_summary_id = rs.response_summary_id WHERE a.question_id = ? AND rs.survey_id = ? AND rs.response_status = 'completed' AND a.answer_text IS NOT NULL AND a.answer_text != '' ORDER BY RAND() LIMIT 5";
                    $stmt_open = $conn->prepare($sql_open);
                    if($stmt_open){ $stmt_open->bind_param("ii", $question['question_id'], $survey_id); $stmt_open->execute(); $res_open = $stmt_open->get_result();
                        if ($res_open->num_rows > 0) { echo "<ul class='open-ended-samples-list'>"; while ($ans_r = $res_open->fetch_assoc()) { echo "<li>".nl2br(htmlspecialchars(substr($ans_r['answer_text'],0,150))).(strlen($ans_r['answer_text'])>150?'...':'')."</li>"; } echo "</ul>"; if($res_open->num_rows>=5) echo "<p><small><em>Sample of responses shown.</em></small></p>"; }
                        else { echo "<p><em>No text responses.</em></p>"; } $stmt_open->close();
                    } else { echo "<p class='alert-danger'>Err loading text samples.</p>"; error_log("SRD TxtSamplePrep:".$conn->error); }
                    break;
                default: echo "<p><em>Aggregated report for type '".htmlspecialchars($question['question_type'])."' not yet implemented.</em></p>"; break;
            }
            ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php elseif (is_numeric($total_completed_responses) && $total_completed_responses == 0 && $survey): ?>
    <div class="alert alert-info">There are no completed responses for this survey yet.</div>
<?php else: ?>
    <div class="alert alert-warning">Could not load questions or response data for this survey's report.</div>
<?php endif; ?>


<style>
    .survey-report-detail-header .survey-meta { font-size: 0.9em; color: #555; margin-bottom: 10px; }
    .question-summary-block { margin-bottom:30px; padding:20px; background-color:#f9f9f9; border:1px solid #e0e0e0; border-radius:6px;}
    .question-summary-block h4 { margin-top:0; color:#00447c; font-size:1.3em; }
    .question-summary-block .question-type-info { font-size:0.85em; color:#777; margin-top:-5px; margin-bottom:15px; display:block; }
    .options-summary-list { list-style:none; padding-left:0; }
    .options-summary-list li { margin-bottom:10px; padding-bottom:8px; border-bottom:1px dotted #eee; }
    .options-summary-list li:last-child { border-bottom:none; }
    .percentage-bar-container { height:18px; background-color:#e9ecef; border-radius:4px; margin-top:5px; overflow:hidden; }
    .percentage-bar { height:100%; background-color:#3498db; border-radius:4px; transition:width 0.5s ease; text-align:right; color:white; font-size:0.8em; line-height:18px; padding-right:5px; box-sizing:border-box;}
    .open-ended-samples-list { list-style:disc; padding-left:20px; font-size:0.9em; color:#454545; }
    .open-ended-samples-list li { margin-bottom:5px; }
    .button-view-raw { background-color: #6c757d; color:white; }
    .button-view-raw:hover { background-color: #5a6268; }
</style>