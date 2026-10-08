<?php



ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



$page_title = "Take Survey"; 
$body_class = "survey-taking-page-body";
$page_container_class = "survey-taking-container";


require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db_connect.php';


if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    $db_error_msg = isset($conn) && $conn->connect_error ? $conn->connect_error : "Connection object not properly initialized.";
    error_log("FATAL ERROR in take_survey.php: Database connection failed - " . $db_error_msg);
    echo "<!DOCTYPE html><html><head><title>Database Error</title><style>body{font-family:sans-serif;padding:20px;background:#f0f0f0;color:#333;} .error-box{background:white;padding:20px;border:1px solid red;border-radius:5px;}</style></head><body>";
    echo "<div class='error-box'><h1>Database Connection Error</h1><p>A critical error occurred. Please contact the administrator. Details logged.</p></div>";
    echo "</body></html>";
    exit();
}


require_once __DIR__ . '/../includes/header.php';


$current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$current_user_role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$current_user_is_admin_or_creator = ($current_user_role && in_array($current_user_role, ['administrator', 'survey_creator']));


$survey_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($survey_id <= 0) {
    echo "<h2 class='survey-title-error'>Invalid Survey Link</h2>";
    echo "<div class='alert alert-danger'>The survey link is invalid or incomplete. Please check the URL.</div>";
    echo "<p><a href='" . htmlspecialchars(BASE_URL) . "' class='button button-secondary'>← Go to Homepage</a></p>";
    require_once __DIR__ . '/../includes/footer.php';
    exit();
}


$survey = null;
$sql_fetch_survey = "SELECT * FROM surveys WHERE survey_id = ?";
$stmt_survey = $conn->prepare($sql_fetch_survey);
if ($stmt_survey) {
    $stmt_survey->bind_param("i", $survey_id); $stmt_survey->execute();
    $result_survey = $stmt_survey->get_result();
    if ($result_survey) { $survey = $result_survey->fetch_assoc(); }
    else { error_log("TakeSurvey - GetResultFail survey $survey_id: " . $conn->error); }
    $stmt_survey->close();
} else { error_log("TakeSurvey - PrepFail survey $survey_id: " . $conn->error); }


$error_message_page = null; $can_take_survey = false; $is_preview_mode_active = false;

if (!$survey) {
    $error_message_page = "The survey you are trying to access (ID: $survey_id) could not be found.";
} else {
    
    if ($current_user_is_admin_or_creator && isset($survey['created_by_user_id'])) {
        if ($survey['created_by_user_id'] == $current_user_id || $current_user_role === 'administrator') {
            if ((isset($_GET['preview']) && $_GET['preview'] == '1') || $survey['status'] === 'draft') {
                $is_preview_mode_active = true;
                $can_take_survey = true; 
            }
        }
    }

    if (!$is_preview_mode_active) { 
        if ($survey['status'] !== 'published') {
            $error_message_page = "This survey is not currently published or accepting responses.";
        } else {
            $can_take_survey = true; $current_time = time();
            if (!empty($survey['start_date']) && ($sTime = strtotime($survey['start_date'])) !== false && $sTime > $current_time) { $error_message_page = "This survey starts on " . date("F j, Y, g:i a T", $sTime) . "."; $can_take_survey = false; }
            if ($can_take_survey && !empty($survey['end_date']) && ($eTime = strtotime($survey['end_date'])) !== false && $eTime < $current_time) { $error_message_page = "This survey ended on " . date("F j, Y, g:i a T", $eTime) . "."; $can_take_survey = false; }
            if ($can_take_survey && !$survey['allow_anonymous'] && !$current_user_id) {
                $_SESSION['message']="Login required for this survey."; $_SESSION['message_type']="info"; $_SESSION['redirect_to_survey_id']=$survey_id; header("Location: ".htmlspecialchars(BASE_URL."login.php")); exit();
            }
            if ($can_take_survey && $current_user_id) { 
                 
                $stmt_cc = $conn->prepare("SELECT 1 FROM survey_responses_summary WHERE survey_id=? AND respondent_user_id=? AND response_status='completed'");
                if($stmt_cc){ $stmt_cc->bind_param("ii", $survey_id, $current_user_id); $stmt_cc->execute();
                    if ($stmt_cc->get_result()->num_rows > 0) { $error_message_page = "Thank you, but records show you have already completed this survey."; $can_take_survey = false; }
                    $stmt_cc->close();
                } else { $error_message_page = "Error checking completion status."; $can_take_survey = false; error_log("TakeSurvey - PrepComplFail: " . $conn->error); }
            }
        }
    }
}
if ($survey) { echo "<script>document.title = ".json_encode(htmlspecialchars($survey['title'])." | Take Survey | ".htmlspecialchars(SITE_NAME)).";</script>"; }


if ((!$can_take_survey && !$is_preview_mode_active) || !$survey) {
    if(empty($error_message_page) && !$survey) $error_message_page = "Survey not found or invalid.";
    elseif(empty($error_message_page) && !$can_take_survey) $error_message_page = "This survey is currently unavailable for you.";
    echo "<h2 class='survey-title-error'>" . ($survey ? htmlspecialchars($survey['title']) : "Survey Access Issue") . "</h2>";
    echo "<div class='alert alert-warning'>" . htmlspecialchars($error_message_page) . "</div>";
    echo "<p><a href='" . htmlspecialchars(BASE_URL . "respondent/available_surveys.php") . "' class='button button-secondary'>← View Available Surveys</a></p>";
    require_once __DIR__ . '/../includes/footer.php'; exit();
}


$form_errors_take_survey = [];
$submitted_answers_take_survey = $_POST['question'] ?? ($_SESSION['submitted_form_data']['question'] ?? []);
if(isset($_SESSION['submitted_form_data'])) unset($_SESSION['submitted_form_data']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($is_preview_mode_active) {
        $_SESSION['message']="Preview mode: Responses are not saved."; $_SESSION['message_type']="info";
        header("Location: ".htmlspecialchars(BASE_URL."respondent/thank_you.php?survey_id=".$survey_id."&preview=1")); exit();
    }
    

    $submitted_answers_take_survey = isset($_POST['question']) && is_array($_POST['question']) ? $_POST['question'] : [];

    
    $sql_req = "SELECT question_id FROM questions WHERE survey_id = ? AND is_required = 1";
    $stmt_req = $conn->prepare($sql_req);
    if ($stmt_req) {
        $stmt_req->bind_param("i", $survey_id); $stmt_req->execute(); $result_req = $stmt_req->get_result();
        while ($req_row = $result_req->fetch_assoc()) {
            $req_id = $req_row['question_id']; $answer_provided = false;
            if (isset($submitted_answers_take_survey[$req_id])) {
                if (is_array($submitted_answers_take_survey[$req_id])) { if (!empty(array_filter($submitted_answers_take_survey[$req_id], function($value){ return trim((string)$value)!=='';}))) { $answer_provided = true; }}
                elseif (trim((string)$submitted_answers_take_survey[$req_id]) !== '') { $answer_provided = true; }
            } if (!$answer_provided) { $form_errors_take_survey['required_' . $req_id] = "This question is required."; }
        } $stmt_req->close();
    } else { $form_errors_take_survey['general'] = "Error validating submission."; error_log("TakeSurvey - ReqCheckPrep: ".$conn->error); }

    if (empty($form_errors_take_survey)) {
        $conn->begin_transaction();
        try {
            $user_id_for_summary_db = null; $db_is_anonymous_flag = 0;
            if ($current_user_id) { $user_id_for_summary_db = $current_user_id; if ($survey['allow_anonymous']==1) $db_is_anonymous_flag = 1;}
            else { if ($survey['allow_anonymous']==1) $db_is_anonymous_flag = 1; else throw new Exception("Login required.");}
            $ip_address_db = $_SERVER['REMOTE_ADDR'] ?? null; $user_agent_db = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $sql_ins_summary = "INSERT INTO survey_responses_summary (survey_id,respondent_user_id,is_anonymous,response_status,submitted_at,ip_address,user_agent,started_at) VALUES (?,?,?, 'completed',NOW(),?,?,NOW())";
            $stmt_summary = $conn->prepare($sql_ins_summary); if(!$stmt_summary) throw new Exception("PrepSumm:".$conn->error);
            $stmt_summary->bind_param("iiiss", $survey_id, $user_id_for_summary_db, $db_is_anonymous_flag, $ip_address_db, $user_agent_db);
            if(!$stmt_summary->execute()) throw new Exception("ExecSumm:".$stmt_summary->error);
            $response_summary_id = $stmt_summary->insert_id; $stmt_summary->close(); if(!$response_summary_id) throw new Exception("No RSID.");

            $sql_ins_ans = "INSERT INTO answers (response_summary_id,question_id,selected_option_id,answer_text) VALUES (?,?,?,?)"; $stmt_ans=$conn->prepare($sql_ins_ans); if(!$stmt_ans) throw new Exception("PrepAns:".$conn->error);
            $sql_ins_mcq_multi = "INSERT INTO answers_mcq_multiple (answer_id,selected_option_id) VALUES (?,?)"; $stmt_mcq_multi=$conn->prepare($sql_ins_mcq_multi); if(!$stmt_mcq_multi) throw new Exception("PrepMCQM:".$conn->error);

            $question_types_map=[]; $stmt_qt=$conn->prepare("SELECT question_id,question_type FROM questions WHERE survey_id=?");
            if($stmt_qt){ $stmt_qt->bind_param("i",$survey_id); $stmt_qt->execute(); $res_qt=$stmt_qt->get_result(); while($r=$res_qt->fetch_assoc()){$question_types_map[$r['question_id']]=$r['question_type'];} $stmt_qt->close();}
            else { throw new Exception("Failed to prepare QTypesMap: ".$conn->error); }

            foreach($submitted_answers_take_survey as $q_id_str => $ans_val){ $q_id=(int)$q_id_str; $opt_id_db=null; $ans_text_db=null;
                $current_q_type=$question_types_map[$q_id] ?? null;
                if (!$current_q_type && $q_id > 0) { error_log("TakeSurvey Subm: QID {$q_id} from POST not in QMap for SID {$survey_id}."); continue; }

                if(is_array($ans_val)){ $filtered_opts=array_filter($ans_val,'is_numeric'); if(!empty($filtered_opts)){
                    $stmt_ans->bind_param("iiis",$response_summary_id,$q_id,$opt_id_db,$ans_text_db); if(!$stmt_ans->execute())throw new Exception("ExecParentMCQ QID$q_id:".$stmt_ans->error);
                    $parent_ans_id=$stmt_ans->insert_id; if(!$parent_ans_id)throw new Exception("No ParentAnsID QID$q_id");
                    foreach($filtered_opts as $sel_opt_id){ $stmt_mcq_multi->bind_param("ii",$parent_ans_id,(int)$sel_opt_id); if(!$stmt_mcq_multi->execute())throw new Exception("ExecMCQOpt QID$q_id,OptID$sel_opt_id:".$stmt_mcq_multi->error);}}}
                else{ $trimmed_ans=trim((string)$ans_val); if($trimmed_ans!==''){
                    if(in_array($current_q_type,['mcq_single','likert_scale','rating_scale'])&&is_numeric($trimmed_ans)&&(int)$trimmed_ans>=0){$opt_id_db=(int)$trimmed_ans;}else{$ans_text_db=$trimmed_ans;}
                    $stmt_ans->bind_param("iiis",$response_summary_id,$q_id,$opt_id_db,$ans_text_db); if(!$stmt_ans->execute())throw new Exception("ExecSingleAns QID$q_id:".$stmt_ans->error);}}}
            $stmt_ans->close(); $stmt_mcq_multi->close();
            $conn->commit();
            header("Location: ".htmlspecialchars(BASE_URL."respondent/thank_you.php?survey_id=".$survey_id)); exit();
        } catch(Exception $e){ $conn->rollback(); $form_errors_take_survey['general']="Tech error submitting. Not saved. Try again."; error_log("TakeSurveySubmDBErr(SID:$survey_id): ".$e->getMessage()." | Trace: ".$e->getTraceAsString());}
    }
}


$questions_data = [];
if ($can_take_survey && $survey) {
    $sql_q_disp = "SELECT * FROM questions WHERE survey_id = ? ORDER BY order_in_survey ASC, question_id ASC";
    $stmt_q_disp = $conn->prepare($sql_q_disp);
    if($stmt_q_disp){ $stmt_q_disp->bind_param("i", $survey_id); $stmt_q_disp->execute(); $res_q_disp = $stmt_q_disp->get_result();
        while($q_r_disp = $res_q_disp->fetch_assoc()){ $q_r_disp['options'] = [];
            if(in_array($q_r_disp['question_type'], ['mcq_single','mcq_multiple','likert_scale','rating_scale'])){
                $stmt_o_disp = $conn->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY order_in_question ASC, option_id ASC");
                if($stmt_o_disp){ $stmt_o_disp->bind_param("i", $q_r_disp['question_id']); $stmt_o_disp->execute(); $res_o_disp = $stmt_o_disp->get_result();
                    while($opt_r_disp = $res_o_disp->fetch_assoc()){ $q_r_disp['options'][] = $opt_r_disp; } $stmt_o_disp->close();
                } else { error_log("TakeSurvey OptFetchDispPrep Fail: " . $conn->error); }
            } $questions_data[] = $q_r_disp;
        } $stmt_q_disp->close();
    } else { $questions_data = false; error_log("TakeSurvey QFetchDispPrep Fail: " . $conn->error); }
}
?>


<?php if ($can_take_survey && $survey) { ?>
    <?php if ($is_preview_mode_active) { ?>
        <div class='alert alert-info text-center preview-mode-banner'><strong>PREVIEW MODE:</strong> Responses will NOT be saved.</div>
    <?php } ?>
    <h1 class="survey-title"><?php echo htmlspecialchars($survey['title']); ?></h1>
    <?php if (!empty($survey['description'])) { ?>
        <p class="survey-description"><?php echo nl2br(htmlspecialchars($survey['description'])); ?></p>
    <?php } ?>

    <?php 
    if (!empty($survey['attachment_filename']) && !empty($survey['attachment_original_name'])) {
        
        $attachment_url = BASE_URL . 'uploads/survey_attachments/' . rawurlencode($survey['attachment_filename']);
        
        
    ?>
        <div class="survey-main-attachment-info">
            <h4 class="survey-attachment-title">Supporting Document:</h4>
            <p class="survey-attachment-link">
                <a href="<?php echo htmlspecialchars($attachment_url); ?>" target="_blank" title="Download: <?php echo htmlspecialchars($survey['attachment_original_name']); ?>">
                    <span class="attachment-icon"></span> 
                    <?php echo htmlspecialchars($survey['attachment_original_name']); ?>
                </a>
            </p>
        </div>
    <?php } ?>


    <?php if (!empty($form_errors_take_survey['general'])) { ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($form_errors_take_survey['general']); ?></div>
    <?php } ?>

    <?php if ($questions_data !== false && !empty($questions_data)) { ?>
    <form action="<?php echo htmlspecialchars(BASE_URL . "respondent/take_survey.php?id=" . $survey_id); ?>" method="POST" id="survey-form" novalidate>
        <?php ?>
        <?php foreach ($questions_data as $index => $question) { ?>
            <?php $question_id_html = $question['question_id']; ?>
            <fieldset class="form-group question-block">
                <legend class="question-label">
                    <?php echo ($index + 1) . ". " . htmlspecialchars($question['question_text']); ?>
                    <?php if ($question['is_required']) { ?> <span class="required-asterisk" title="Required">*</span><?php } ?>
                </legend>
                <?php if (isset($form_errors_take_survey['required_' . $question_id_html])) { ?>
                    <p class="error-text"><?php echo htmlspecialchars($form_errors_take_survey['required_' . $question_id_html]); ?></p>
                <?php } ?>
                <?php
                $current_submitted_value = $submitted_answers_take_survey[$question_id_html] ?? null;
                switch ($question['question_type']) {
                    case 'mcq_single': case 'likert_scale': case 'rating_scale':
                        if (!empty($question['options'])) { echo "<ul class='options-list options-list-radio'>"; foreach ($question['options'] as $option) { $option_id_html = $option['option_id']; $checked_html = ($current_submitted_value !== null && (string)$current_submitted_value === (string)$option_id_html) ? 'checked' : ''; echo "<li><label><input type='radio' name='question[{$question_id_html}]' value='{$option_id_html}' " . ($question['is_required'] ? 'required' : '') . " {$checked_html}> <span>" . htmlspecialchars($option['option_text']) . "</span></label></li>"; } echo "</ul>"; } else { echo "<p><em>(No options.)</em></p>"; } break;
                    case 'mcq_multiple':
                        if (!empty($question['options'])) { echo "<ul class='options-list options-list-checkbox'>"; foreach ($question['options'] as $option) { $option_id_html = $option['option_id']; $checked_html = (is_array($current_submitted_value) && in_array((string)$option_id_html, array_map('strval', $current_submitted_value))) ? 'checked' : ''; echo "<li><label><input type='checkbox' name='question[{$question_id_html}][]' value='{$option_id_html}' {$checked_html}> <span>" . htmlspecialchars($option['option_text']) . "</span></label></li>"; } echo "</ul>"; if ($question['is_required']) { echo '<small class="required-note">(Min 1 option)</small>'; } } else { echo "<p><em>(No options.)</em></p>"; } break;
                    case 'open_ended_short': echo "<input type='text' name='question[{$question_id_html}]' class='form-control' value='" . htmlspecialchars((string)($current_submitted_value ?? '')) . "' " . ($question['is_required'] ? 'required' : '') . " maxlength='255'>"; break;
                    case 'open_ended_long': echo "<textarea name='question[{$question_id_html}]' class='form-control' rows='5' " . ($question['is_required'] ? 'required' : '') . ">" . htmlspecialchars((string)($current_submitted_value ?? '')) . "</textarea>"; break;
                    default: echo "<p><em>Unsupported question type: ".htmlspecialchars($question['question_type']).".</em></p>";
                } 
                ?>
            </fieldset>
        <?php } ?>
        <div class="form-group text-center submit-button-container">
            <?php if (!$is_preview_mode_active) { ?> <button type="submit" class="button button-primary button-submit-survey">Submit Responses</button>
            <?php } else { ?> <button type="button" class="button button-primary" disabled title="Preview Mode">Submit (Preview)</button> <?php } ?>
        </div>
    </form>
    <?php } elseif($questions_data !== false && $survey) { ?><div class="alert alert-info">No questions in this survey.</div><p><a href="<?php echo htmlspecialchars(BASE_URL . "respondent/available_surveys.php"); ?>" class="button">← Available Surveys</a></p>
    <?php } elseif($survey) { ?><div class="alert alert-danger">Could not load survey questions.</div><?php } ?>
<?php } ?>

<?php

require_once __DIR__ . '/../includes/footer.php';
?>