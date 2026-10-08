<?php

$admin_page_title = "Survey Responses"; 


if (!isset($conn)) {
    
    require_once __DIR__ . '/../includes/db_connect.php';
}

$survey_id = isset($_GET['survey_id']) ? (int)$_GET['survey_id'] : 0;

if ($survey_id <= 0) {
    $_SESSION['message'] = "Invalid Survey ID provided to view responses.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . htmlspecialchars(BASE_URL . "admin/index.php?page=surveys"));
    exit();
}


$stmt_survey = $conn->prepare("SELECT survey_id, title FROM surveys WHERE survey_id = ?");
if (!$stmt_survey) {  die("Error preparing survey fetch."); }
$stmt_survey->bind_param("i", $survey_id);
$stmt_survey->execute();
$result_survey = $stmt_survey->get_result();
$survey = $result_survey->fetch_assoc();
$stmt_survey->close();

if (!$survey) {
    $_SESSION['message'] = "Survey (ID: $survey_id) not found.";
    $_SESSION['message_type'] = "warning";
    header("Location: " . htmlspecialchars(BASE_URL . "admin/index.php?page=surveys"));
    exit();
}
$admin_page_title = "Responses for: " . htmlspecialchars($survey['title']);


$questions_map = []; 
$sql_questions = "SELECT question_id, question_text, question_type FROM questions WHERE survey_id = ? ORDER BY order_in_survey ASC, question_id ASC";
$stmt_q_list = $conn->prepare($sql_questions);
if($stmt_q_list){
    $stmt_q_list->bind_param("i", $survey_id);
    $stmt_q_list->execute();
    $result_q_list = $stmt_q_list->get_result();
    while($q_row = $result_q_list->fetch_assoc()){
        $questions_map[$q_row['question_id']] = $q_row; 
    }
    $stmt_q_list->close();
} else {
    error_log("Admin Survey Responses - Error preparing question list: " . $conn->error);
    
}



$responses_summary_data = [];
$sql_responses = "SELECT rs.*, u.username as respondent_username
                  FROM survey_responses_summary rs
                  LEFT JOIN users u ON rs.respondent_user_id = u.user_id
                  WHERE rs.survey_id = ? AND rs.response_status = 'completed'
                  ORDER BY rs.submitted_at DESC, rs.response_summary_id DESC";
$stmt_responses = $conn->prepare($sql_responses);

if ($stmt_responses) {
    $stmt_responses->bind_param("i", $survey_id);
    $stmt_responses->execute();
    $result_responses = $stmt_responses->get_result();
    while ($row = $result_responses->fetch_assoc()) {
        
        $row['answers'] = [];
        $sql_answers = "SELECT a.question_id, a.selected_option_id, a.answer_text, qo.option_text as selected_option_text
                        FROM answers a
                        LEFT JOIN question_options qo ON a.selected_option_id = qo.option_id
                        WHERE a.response_summary_id = ?
                        ORDER BY a.question_id ASC"; 
                        
        $stmt_ans = $conn->prepare($sql_answers);
        if($stmt_ans){
            $stmt_ans->bind_param("i", $row['response_summary_id']);
            $stmt_ans->execute();
            $result_ans = $stmt_ans->get_result();
            while($ans_row = $result_ans->fetch_assoc()){
                
                if (isset($questions_map[$ans_row['question_id']]) && $questions_map[$ans_row['question_id']]['question_type'] === 'mcq_multiple') {
                    $ans_row['mcq_multiple_options'] = [];
                    $sql_mcq_multi = "SELECT amo.selected_option_id, qo_multi.option_text
                                      FROM answers_mcq_multiple amo
                                      JOIN question_options qo_multi ON amo.selected_option_id = qo_multi.option_id
                                      WHERE amo.answer_id = (SELECT answer_id FROM answers WHERE response_summary_id = ? AND question_id = ? LIMIT 1)";
                                      
                    $stmt_mcq_m = $conn->prepare($sql_mcq_multi);
                    if($stmt_mcq_m){
                        $stmt_mc_m_ans_id = $ans_row['answer_id'] ?? null; 
                        
                        
                        
                        
                        
                    }
                }
                $row['answers'][$ans_row['question_id']] = $ans_row;
            }
            $stmt_ans->close();
        }
        $responses_summary_data[] = $row;
    }
    $stmt_responses->close();
} else {
    echo "<div class='alert alert-danger'>Error preparing to fetch responses: " . htmlspecialchars($conn->error) . "</div>";
    error_log("Admin Survey Responses - Error preparing response list: " . $conn->error);
}

?>
<div class="manage-users-header">
    <h2>Responses for Survey: "<?php echo htmlspecialchars($survey['title']); ?>"</h2>
    <a href="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=surveys"); ?>" class="button button-secondary">← Back to All Surveys</a>
</div>

<?php if (!empty($responses_summary_data)): ?>
    <p>Total Completed Responses: <?php echo count($responses_summary_data); ?></p>
    <div class="responses-table-container" style="overflow-x: auto;">
        <table class="admin-table survey-responses-table">
            <thead>
                <tr>
                    <th>Resp. ID</th>
                    <th>Respondent</th>
                    <th>Submitted At</th>
                    <th>Is Anonymous</th>
                    <?php foreach ($questions_map as $q_id => $q_info): ?>
                        <th title="<?php echo htmlspecialchars($q_info['question_text']); ?>">
                            <?php echo htmlspecialchars(substr($q_info['question_text'], 0, 30)) . (strlen($q_info['question_text']) > 30 ? '...' : ''); ?>
                        </th>
                    <?php endforeach; ?>
                    
                </tr>
            </thead>
            <tbody>
                <?php foreach ($responses_summary_data as $response): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($response['response_summary_id']); ?></td>
                        <td><?php echo $response['is_anonymous'] ? 'Anonymous' : htmlspecialchars($response['respondent_username'] ?? 'User ID: ' . $response['respondent_user_id']); ?></td>
                        <td><?php echo date("M d, Y H:i", strtotime($response['submitted_at'])); ?></td>
                        <td><?php echo $response['is_anonymous'] ? 'Yes' : 'No'; ?></td>
                        <?php foreach ($questions_map as $q_id => $q_info): ?>
                            <td>
                                <?php
                                $answer_detail = $response['answers'][$q_id] ?? null;
                                if ($answer_detail) {
                                    if (!empty($answer_detail['selected_option_text'])) {
                                        echo htmlspecialchars($answer_detail['selected_option_text']);
                                    } elseif (!empty($answer_detail['answer_text'])) {
                                        echo nl2br(htmlspecialchars($answer_detail['answer_text']));
                                    } elseif ($q_info['question_type'] === 'mcq_multiple') {
                                        
                                        $mcq_multi_options_texts = [];
                                        
                                        
                                        
                                        
                                        $stmt_mcq_ans = $conn->prepare("SELECT ao.option_text FROM answers_mcq_multiple am JOIN question_options ao ON am.selected_option_id = ao.option_id WHERE am.answer_id = (SELECT answer_id FROM answers WHERE response_summary_id = ? AND question_id = ? LIMIT 1)");
                                        if($stmt_mcq_ans){
                                            $stmt_mcq_ans->bind_param("ii", $response['response_summary_id'], $q_id);
                                            $stmt_mcq_ans->execute();
                                            $res_mcq_ans = $stmt_mcq_ans->get_result();
                                            while($mcq_opt_row = $res_mcq_ans->fetch_assoc()){
                                                $mcq_multi_options_texts[] = htmlspecialchars($mcq_opt_row['option_text']);
                                            }
                                            $stmt_mcq_ans->close();
                                            echo !empty($mcq_multi_options_texts) ? implode(', ', $mcq_multi_options_texts) : 'N/A';
                                        } else { echo "Error loading multi-options"; }
                                    } else {
                                        echo "<span style='color:#999;'>N/A</span>"; 
                                    }
                                } else {
                                    echo "<span style='color:#999;'>N/A</span>";
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                        
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-info" style="margin-top: 20px;">No completed responses found for this survey yet.</div>
<?php endif; ?>

<style>
    .survey-responses-table th, .survey-responses-table td {
        font-size: 0.9em;
        white-space: nowrap; 
    }
    .survey-responses-table td {
        max-width: 200px; 
        overflow: hidden;
        text-overflow: ellipsis; 
    }
     .survey-responses-table td:hover { 
        white-space: normal;
        overflow: visible;
        max-width: none;
    }
</style>

<?php

?>