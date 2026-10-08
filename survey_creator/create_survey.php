<?php

$creator_page_title = "Create New Survey - Details";

require_once __DIR__ . '/_creator_header.php';


if (!defined('SURVEY_ATTACHMENT_UPLOAD_DIR')) { define('SURVEY_ATTACHMENT_UPLOAD_DIR', __DIR__ . '/../uploads/survey_attachments/'); }
if (!defined('MAX_FILE_SIZE')) { define('MAX_FILE_SIZE', 5 * 1024 * 1024); } 
$allowed_mime_types = $allowed_mime_types ?? ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
$allowed_extensions_display = $allowed_extensions_display ?? ".pdf, .jpg, .jpeg, .png, .doc, .docx, .txt, .xls, .xlsx";


$survey_title_val = $_SESSION['create_survey_form_data']['survey_title_val'] ?? '';
$survey_description_val = $_SESSION['create_survey_form_data']['survey_description_val'] ?? '';
$template_id_val = $_SESSION['create_survey_form_data']['template_id_val'] ?? ''; 
$start_date_input_val = $_SESSION['create_survey_form_data']['start_date_input_val'] ?? '';
$end_date_input_val = $_SESSION['create_survey_form_data']['end_date_input_val'] ?? '';
$allow_anonymous_val = isset($_SESSION['create_survey_form_data']['allow_anonymous_val']) ? (int)$_SESSION['create_survey_form_data']['allow_anonymous_val'] : 0;
$target_student_val = isset($_SESSION['create_survey_form_data']['target_student_val']) ? (int)$_SESSION['create_survey_form_data']['target_student_val'] : 0;
$target_faculty_val = isset($_SESSION['create_survey_form_data']['target_faculty_val']) ? (int)$_SESSION['create_survey_form_data']['target_faculty_val'] : 0;
$target_staff_val = isset($_SESSION['create_survey_form_data']['target_staff_val']) ? (int)$_SESSION['create_survey_form_data']['target_staff_val'] : 0;
$target_community_val = isset($_SESSION['create_survey_form_data']['target_community_val']) ? (int)$_SESSION['create_survey_form_data']['target_community_val'] : 0;
$target_all_users_val = isset($_SESSION['create_survey_form_data']['target_all_users_val']) ? (int)$_SESSION['create_survey_form_data']['target_all_users_val'] : 0;

$form_errors = $_SESSION['create_survey_errors'] ?? [];
$attachment_feedback_display = $_SESSION['create_survey_attachment_feedback'] ?? '';

unset($_SESSION['create_survey_errors'], $_SESSION['create_survey_form_data'], $_SESSION['create_survey_attachment_feedback']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $_SESSION['create_survey_form_data']['survey_title_val'] = trim($_POST['survey_title'] ?? '');
    $_SESSION['create_survey_form_data']['survey_description_val'] = trim($_POST['survey_description'] ?? '');
    $_SESSION['create_survey_form_data']['template_id_val'] = !empty($_POST['template_id']) ? (int)$_POST['template_id'] : null; 
    $_SESSION['create_survey_form_data']['start_date_input_val'] = trim($_POST['start_date'] ?? '');
    $_SESSION['create_survey_form_data']['end_date_input_val'] = trim($_POST['end_date'] ?? '');
    $_SESSION['create_survey_form_data']['allow_anonymous_val'] = isset($_POST['allow_anonymous']) ? 1 : 0;
    $_SESSION['create_survey_form_data']['target_student_val'] = isset($_POST['target_audience']['student']) ? 1 : 0;
    $_SESSION['create_survey_form_data']['target_faculty_val'] = isset($_POST['target_audience']['faculty']) ? 1 : 0;
    $_SESSION['create_survey_form_data']['target_staff_val'] = isset($_POST['target_audience']['staff']) ? 1 : 0;
    $_SESSION['create_survey_form_data']['target_community_val'] = isset($_POST['target_audience']['community']) ? 1 : 0;
    $_SESSION['create_survey_form_data']['target_all_users_val'] = isset($_POST['target_audience']['all']) ? 1 : 0;

    
    $survey_title_val = $_SESSION['create_survey_form_data']['survey_title_val'];
    $survey_description_val = $_SESSION['create_survey_form_data']['survey_description_val'];
    $template_id_val = $_SESSION['create_survey_form_data']['template_id_val'];
    $start_date_input_val = $_SESSION['create_survey_form_data']['start_date_input_val'];
    $end_date_input_val = $_SESSION['create_survey_form_data']['end_date_input_val'];
    $allow_anonymous_val = $_SESSION['create_survey_form_data']['allow_anonymous_val'];
    $target_student_val = $_SESSION['create_survey_form_data']['target_student_val'];
    $target_faculty_val = $_SESSION['create_survey_form_data']['target_faculty_val'];
    $target_staff_val = $_SESSION['create_survey_form_data']['target_staff_val'];
    $target_community_val = $_SESSION['create_survey_form_data']['target_community_val'];
    $target_all_users_val = $_SESSION['create_survey_form_data']['target_all_users_val'];
    $created_by_user_id = $_SESSION['user_id'];

    $form_errors = []; 
    $attachment_feedback = '';

    if ($target_all_users_val) { $target_student_val = $target_faculty_val = $target_staff_val = $target_community_val = 0; }
    

    
    if (empty($survey_title_val)) { $form_errors['survey_title'] = "Survey title is required."; }
    elseif (strlen($survey_title_val) > 255) { $form_errors['survey_title'] = "Title max 255 characters."; }
    $start_date_db = null; $end_date_db = null; $dt_start = null; $dt_end = null;
    if(!empty($start_date_input_val)){ try{$dt_start = new DateTime($start_date_input_val); $start_date_db = $dt_start->format('Y-m-d H:i:s');} catch(Exception $e){$form_errors['start_date']="Invalid start date.";}}
    if(!empty($end_date_input_val)){ try{$dt_end = new DateTime($end_date_input_val); $end_date_db = $dt_end->format('Y-m-d H:i:s');} catch(Exception $e){$form_errors['end_date']="Invalid end date.";}}
    if($dt_start && $dt_end && $dt_end <= $dt_start){ $form_errors['end_date']="End date must be after start."; }

    
    $db_attachment_filename = null; $db_original_attachment_name = null;
    if (isset($_FILES['survey_attachment']) && $_FILES['survey_attachment']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['survey_attachment']; $db_original_attachment_name = basename($file['name']);
        $file_tmp_path = $file['tmp_name']; $file_size = $file['size'];
        $finfo_mime = finfo_open(FILEINFO_MIME_TYPE); $file_mime_type = finfo_file($finfo_mime, $file_tmp_path); finfo_close($finfo_mime);
        if ($file_size > MAX_FILE_SIZE) { $form_errors['survey_attachment'] = "File too large. Max: " . (MAX_FILE_SIZE/1024/1024) . "MB."; }
        elseif (!in_array($file_mime_type, $allowed_mime_types)) { $form_errors['survey_attachment'] = "Invalid file type. Allowed: " . $allowed_extensions_display . ". Got: ".htmlspecialchars($file_mime_type); }
        else {
            $file_extension = strtolower(pathinfo($db_original_attachment_name, PATHINFO_EXTENSION));
            $safe_original_basename = preg_replace("/[^a-zA-Z0-9_.-]+/", "_", pathinfo($db_original_attachment_name, PATHINFO_FILENAME));
            $db_attachment_filename = "survey_new_" . $created_by_user_id . "_" . time() . "_" . uniqid('', true) . "." . $file_extension;
            $destination_path = rtrim(SURVEY_ATTACHMENT_UPLOAD_DIR, '/') . '/' . $db_attachment_filename;
            if (!is_dir(SURVEY_ATTACHMENT_UPLOAD_DIR) || !is_writable(SURVEY_ATTACHMENT_UPLOAD_DIR)) {
                 $form_errors['survey_attachment'] = "Server upload directory error."; error_log("Upload dir " . SURVEY_ATTACHMENT_UPLOAD_DIR . " not exist/writable.");
                 $db_attachment_filename = null; $db_original_attachment_name = null;
            } elseif (move_uploaded_file($file_tmp_path, $destination_path)) { $attachment_feedback = "Attachment '" . htmlspecialchars($db_original_attachment_name) . "' uploaded. ";
            } else { $form_errors['survey_attachment'] = "Failed to save uploaded file."; error_log("MoveUploadFile fail: " . $destination_path); $db_attachment_filename = null; $db_original_attachment_name = null;}
        }
    } elseif (isset($_FILES['survey_attachment']) && $_FILES['survey_attachment']['error'] != UPLOAD_ERR_NO_FILE) {
        $form_errors['survey_attachment'] = "File upload error code: " . $_FILES['survey_attachment']['error']; error_log("File Upload Sys Error: " . $_FILES['survey_attachment']['error']);
    }

    
    if (empty($form_errors)) {
        $conn->begin_transaction();
        try {
            $sql_insert_survey = "INSERT INTO surveys (title, description, created_by_user_id, status, start_date, end_date, allow_anonymous, template_id, target_student, target_faculty, target_staff, target_community, target_all_users, attachment_filename, attachment_original_name) VALUES (?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert_survey = $conn->prepare($sql_insert_survey);
            if (!$stmt_insert_survey) throw new Exception("DB Prepare survey insert: " . $conn->error);

            
            
            
            
            $stmt_insert_survey->bind_param("ssisssiiiiisss",
                $survey_title_val, $survey_description_val, $created_by_user_id,
                $start_date_db, $end_date_db, $allow_anonymous_val, $template_id_val,
                $target_student_val, $target_faculty_val, $target_staff_val,
                $target_community_val, $target_all_users_val,
                $db_attachment_filename, $db_original_attachment_name
            );
            if (!$stmt_insert_survey->execute()) throw new Exception("DB Execute survey insert: " . $stmt_insert_survey->error);
            $new_survey_id = $stmt_insert_survey->insert_id; $stmt_insert_survey->close();
            if (!$new_survey_id) throw new Exception("Failed to retrieve new survey ID after insert.");

            
            if ($template_id_val && $new_survey_id > 0) {
                $stmt_master = $conn->prepare("SELECT master_survey_id FROM survey_templates WHERE template_id = ?");
                if (!$stmt_master) throw new Exception("Prep tpl master fetch: " . $conn->error);
                $stmt_master->bind_param("i", $template_id_val); $stmt_master->execute();
                $res_master = $stmt_master->get_result(); $master_row = $res_master->fetch_assoc(); $stmt_master->close();
                if ($master_row && $master_row['master_survey_id']) {
                    $tpl_master_survey_id = $master_row['master_survey_id'];
                    $stmt_tpl_q = $conn->prepare("SELECT * FROM questions WHERE survey_id = ? ORDER BY order_in_survey ASC");
                    if(!$stmt_tpl_q) throw new Exception("Prep tpl_q fetch: ".$conn->error);
                    $stmt_tpl_q->bind_param("i", $tpl_master_survey_id); $stmt_tpl_q->execute(); $tpl_q_res = $stmt_tpl_q->get_result();

                    $sql_iq = "INSERT INTO questions (survey_id,question_text,question_type,is_required,order_in_survey) VALUES (?,?,?,?,?)"; $stmt_iq = $conn->prepare($sql_iq); if(!$stmt_iq) throw new Exception("PrepQCopy:".$conn->error);
                    $sql_io = "INSERT INTO question_options (question_id,option_text,option_value,order_in_question) VALUES (?,?,?,?)"; $stmt_io = $conn->prepare($sql_io); if(!$stmt_io) throw new Exception("PrepOptCopy:".$conn->error);

                    while ($tpl_q = $tpl_q_res->fetch_assoc()) {
                        $stmt_iq->bind_param("issii", $new_survey_id, $tpl_q['question_text'], $tpl_q['question_type'], $tpl_q['is_required'], $tpl_q['order_in_survey']); if(!$stmt_iq->execute()) throw new Exception("ExecQCopy:".$stmt_iq->error);
                        $new_q_id = $stmt_iq->insert_id; if(!$new_q_id) throw new Exception("NoNewQID");
                        if(in_array($tpl_q['question_type'], ['mcq_single','mcq_multiple','likert_scale','rating_scale'])) {
                            $stmt_tpl_o = $conn->prepare("SELECT * FROM question_options WHERE question_id=? ORDER BY order_in_question ASC"); if(!$stmt_tpl_o) throw new Exception("PrepTplOptFetch:".$conn->error);
                            $stmt_tpl_o->bind_param("i", $tpl_q['question_id']); $stmt_tpl_o->execute(); $tpl_o_res=$stmt_tpl_o->get_result();
                            while($tpl_o = $tpl_o_res->fetch_assoc()){ $stmt_io->bind_param("issi", $new_q_id, $tpl_o['option_text'], $tpl_o['option_value'], $tpl_o['order_in_question']); if(!$stmt_io->execute()) throw new Exception("ExecOptCopy:".$stmt_io->error); }
                            $stmt_tpl_o->close();
                        }
                    } $stmt_iq->close(); $stmt_io->close(); $stmt_tpl_q->close();
                }
            } 

            $conn->commit();
            unset($_SESSION['create_survey_form_data'], $_SESSION['create_survey_errors']); 
            $_SESSION['message'] = "Survey '" . htmlspecialchars($survey_title_val) . "' details saved! " . trim($attachment_feedback) . "Now add/manage its questions."; $_SESSION['message_type'] = "success";
            header("Location: " . htmlspecialchars(append_admin_context_to_url(BASE_URL . "survey_creator/edit_survey.php?id=" . $new_survey_id, $is_admin_in_creator_context ?? false))); exit();
        } catch (Exception $e) {
            $conn->rollback(); $form_errors['general'] = "Error during survey creation: " . $e->getMessage();
            if ($db_attachment_filename && file_exists(SURVEY_ATTACHMENT_UPLOAD_DIR . $db_attachment_filename)) { @unlink(SURVEY_ATTACHMENT_UPLOAD_DIR . $db_attachment_filename); error_log("Orphaned attachment " . $db_attachment_filename . " deleted after DB rollback.");}
            error_log("SurveyCreation Exception (UID:$created_by_user_id): " . $e->getMessage());
        }
    }
    
    if(!empty($form_errors)){
        $_SESSION['create_survey_errors'] = $form_errors;
        if(!empty($attachment_feedback)) $_SESSION['create_survey_attachment_feedback'] = $attachment_feedback; 
        header("Location: " . htmlspecialchars(append_admin_context_to_url(BASE_URL . "survey_creator/create_survey.php", $is_admin_in_creator_context ?? false))); exit();
    }
}


$templates_for_dropdown = [];
$stmt_tpl_list = $conn->query("SELECT template_id, template_name FROM survey_templates ORDER BY template_name ASC");
if($stmt_tpl_list){ while($r = $stmt_tpl_list->fetch_assoc()){ $templates_for_dropdown[] = $r; } $stmt_tpl_list->close(); }
?>

<h2>Create New Survey - Step 1: Survey Details</h2>
<p>Fill in basic details. After saving, you'll manage questions on the next page.</p>

<?php if (!empty($form_errors['general'])): ?> <div class="alert alert-danger"><?php echo htmlspecialchars($form_errors['general']); ?></div> <?php endif; ?>
<?php if (!empty($attachment_feedback_display) && empty($form_errors['survey_attachment']) ): ?> <div class="alert alert-info"><?php echo htmlspecialchars($attachment_feedback_display); ?></div> <?php endif; ?>

<form action="<?php echo htmlspecialchars(append_admin_context_to_url(BASE_URL . "survey_creator/create_survey.php", $is_admin_in_creator_context ?? false)); ?>" method="POST" class="admin-form creator-form" novalidate enctype="multipart/form-data">
    <div class="form-group"><label for="cs_title">Survey Title:</label><input type="text" name="survey_title" id="cs_title" value="<?php echo htmlspecialchars($survey_title_val); ?>" required><?php if(isset($form_errors['survey_title'])):?><small class="error-text"><?php echo htmlspecialchars($form_errors['survey_title']);?></small><?php endif; ?></div>
    <div class="form-group"><label for="cs_desc">Description (Optional):</label><textarea name="survey_description" id="cs_desc" rows="4"><?php echo htmlspecialchars($survey_description_val); ?></textarea></div>
    <?php if(!empty($templates_for_dropdown)):?><div class="form-group"><label for="cs_tpl">Template (Optional):</label><select name="template_id" id="cs_tpl"><option value="">-- No Template --</option><?php foreach($templates_for_dropdown as $tpl):?><option value="<?php echo $tpl['template_id'];?>"<?php if($template_id_val==$tpl['template_id'])echo ' selected';?>><?php echo htmlspecialchars($tpl['template_name']);?></option><?php endforeach;?></select></div><?php endif;?>
    <div class="form-group"><label for="cs_sdate">Start Date (Optional):</label><input type="datetime-local" name="start_date" id="cs_sdate" value="<?php echo htmlspecialchars($start_date_input_val); ?>"><?php if(isset($form_errors['start_date'])):?><small class="error-text"><?php echo htmlspecialchars($form_errors['start_date']);?></small><?php endif; ?></div>
    <div class="form-group"><label for="cs_edate">End Date (Optional):</label><input type="datetime-local" name="end_date" id="cs_edate" value="<?php echo htmlspecialchars($end_date_input_val); ?>"><?php if(isset($form_errors['end_date'])):?><small class="error-text"><?php echo $form_errors['end_date'];?></small><?php endif; ?></div>
    <div class="form-group"><label><input type="checkbox" name="allow_anonymous" value="1"<?php if($allow_anonymous_val)echo ' checked';?>> Allow Anonymous Responses</label></div>
    
    <div class="form-group"><label for="cs_attachment">Optional Attachment (Max <?php echo MAX_FILE_SIZE/1024/1024;?>MB):</label><input type="file" name="survey_attachment" id="cs_attachment" class="form-control-file"><small class="form-text text-muted">Allowed: <?php echo $allowed_extensions_display;?></small><?php if(isset($form_errors['survey_attachment'])):?><small class="error-text"><?php echo $form_errors['survey_attachment'];?></small><?php endif;?></div>
    <hr>
    <fieldset class="form-group"><legend>Target Audience:</legend>
        <div><label><input type="checkbox" name="target_audience[student]" value="1"<?php if($target_student_val)echo ' checked';?>> Students</label></div>
        <div><label><input type="checkbox" name="target_audience[faculty]" value="1"<?php if($target_faculty_val)echo ' checked';?>> Faculty</label></div>
        <div><label><input type="checkbox" name="target_audience[staff]" value="1"<?php if($target_staff_val)echo ' checked';?>> Staff</label></div>
        <div><label><input type="checkbox" name="target_audience[community]" value="1"<?php if($target_community_val)echo ' checked';?>> Community</label></div>
        <hr><div style="margin-top:5px;"><label><input type="checkbox" name="target_audience[all]" value="1"<?php if($target_all_users_val)echo ' checked';?> id="target_all_users_cb_creator_create"> All Users</label></div>
        <?php if(isset($form_errors['target_audience'])):?><small class="error-text"><?php echo htmlspecialchars($form_errors['target_audience']);?></small><?php endif;?>
    </fieldset>
    <script> // JS for target audience 'All' checkbox interaction (Same as before) </script>
    
    <div class="form-group"><button type="submit" class="button button-primary">Save & Add Questions →</button>
        <a href="<?php echo htmlspecialchars(append_admin_context_to_url(BASE_URL . "survey_creator/index.php", $is_admin_in_creator_context ?? false)); ?>" class="button button-secondary">Cancel</a>
    </div>
</form>

<?php
require_once __DIR__ . '/_creator_footer.php';
?>