<?php

$creator_page_title = "Manage Survey"; 
$active_nav_item = 'my_surveys';    
require_once __DIR__ . '/_creator_header.php'; 


$survey_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];


if ($survey_id <= 0) {
    $_SESSION['message'] = "Invalid Survey ID provided."; $_SESSION['message_type'] = "danger";
    header("Location: " . htmlspecialchars(BASE_URL . "survey_creator/index.php")); exit();
}


$survey = null;
$sql_fetch_survey = "SELECT s.*, u.username as creator_username FROM surveys s LEFT JOIN users u ON s.created_by_user_id = u.user_id WHERE s.survey_id = ?";
$stmt_survey = $conn->prepare($sql_fetch_survey);
if (!$stmt_survey) { $_SESSION['message'] = "DB Error (ES_S1)."; $_SESSION['message_type']="danger"; error_log("EditSurvey - Prepare survey fetch failed: " . $conn->error); header("Location: ".htmlspecialchars(BASE_URL."survey_creator/index.php")); exit(); }
$stmt_survey->bind_param("i", $survey_id);
if (!$stmt_survey->execute()) { $_SESSION['message'] = "DB Error (ES_S2)."; $_SESSION['message_type']="danger"; error_log("EditSurvey - Execute survey fetch failed: " . $stmt_survey->error); $stmt_survey->close(); header("Location: ".htmlspecialchars(BASE_URL."survey_creator/index.php")); exit(); }
$result_survey = $stmt_survey->get_result();
if ($result_survey) { $survey = $result_survey->fetch_assoc(); }
else { $_SESSION['message'] = "DB Error (ES_S3)."; $_SESSION['message_type'] = "danger"; error_log("EditSurvey - Get_result survey fetch failed: " . $conn->error); $stmt_survey->close(); header("Location: ".htmlspecialchars(BASE_URL."survey_creator/index.php")); exit(); }
$stmt_survey->close();


if (!$survey) {
    $_SESSION['message'] = "Survey not found (ID: " . $survey_id . "). It may have been deleted, or the ID is incorrect.";
    $_SESSION['message_type'] = "warning";
    header("Location: " . htmlspecialchars(BASE_URL . "survey_creator/index.php"));
    exit(); 
}
if ($survey['created_by_user_id'] != $current_user_id && $current_user_role !== 'administrator') {
    $_SESSION['message'] = "You do not have permission to manage this survey (ID: " . $survey_id . ").";
    $_SESSION['message_type'] = "danger";
    header("Location: " . htmlspecialchars(BASE_URL . "survey_creator/index.php"));
    exit(); 
}
$creator_page_title = "Manage: " . htmlspecialchars($survey['title']);



$form_action = isset($_POST['form_action']) ? trim($_POST['form_action']) : null;


if ($form_action === 'publish_survey' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if ($survey['status'] === 'draft') {
        $stmt_publish = $conn->prepare("UPDATE surveys SET status = 'published' WHERE survey_id = ? AND created_by_user_id = ?");
        if($stmt_publish){ $stmt_publish->bind_param("ii", $survey_id, $current_user_id);
            if ($stmt_publish->execute() && $stmt_publish->affected_rows > 0) { $_SESSION['message'] = "Survey '" . htmlspecialchars($survey['title']) . "' successfully published!"; $_SESSION['message_type'] = "success"; $survey['status'] = 'published';} 
            else { $_SESSION['message'] = "Error publishing survey: ".($stmt_publish->error ?: "No changes made or permission issue."); $_SESSION['message_type'] = "danger"; error_log("Publish Survey Execute Error: ".$stmt_publish->error." for SID: ".$survey_id);}
            $stmt_publish->close();
        } else { $_SESSION['message'] = "Database error preparing publish action."; $_SESSION['message_type'] = "danger"; error_log("Publish Survey Prepare Error: ".$conn->error);}
    } else { $_SESSION['message'] = "Survey is not in 'draft' status and cannot be published directly."; $_SESSION['message_type'] = "warning"; }
    header("Location: " . htmlspecialchars(BASE_URL . "survey_creator/edit_survey.php?id=" . $survey_id)); exit();
}


if ($form_action === 'revert_to_draft' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (in_array($survey['status'], ['published', 'closed'])) {
        $stmt_revert = $conn->prepare("UPDATE surveys SET status = 'draft' WHERE survey_id = ? AND created_by_user_id = ?");
        if($stmt_revert){ $stmt_revert->bind_param("ii", $survey_id, $current_user_id);
            if ($stmt_revert->execute() && $stmt_revert->affected_rows > 0) { $_SESSION['message'] = "Survey successfully reverted to draft."; $_SESSION['message_type'] = "success"; $survey['status'] = 'draft';} 
            else { $_SESSION['message'] = "Error reverting survey: ".($stmt_revert->error ?: "No changes made or permission issue."); $_SESSION['message_type'] = "danger"; error_log("Revert Survey Execute Error: ".$stmt_revert->error." for SID: ".$survey_id);}
            $stmt_revert->close();
        } else { $_SESSION['message'] = "Database error preparing revert action."; $_SESSION['message_type'] = "danger"; error_log("Revert Survey Prepare Error: ".$conn->error);}
    } else { $_SESSION['message'] = "Survey cannot be reverted from its current status ('".htmlspecialchars($survey['status'])."')."; $_SESSION['message_type'] = "warning"; }
    header("Location: " . htmlspecialchars(BASE_URL . "survey_creator/edit_survey.php?id=" . $survey_id)); exit();
}


if ($form_action === 'add_batch_questions' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $new_questions_data = isset($_POST['new_question']) && is_array($_POST['new_question']) ? $_POST['new_question'] : [];
    $all_questions_valid = true;
    $questions_to_insert = [];

    if (empty($new_questions_data)) {
        $_SESSION['message'] = "No question data submitted to add."; $_SESSION['message_type'] = "warning"; $all_questions_valid = false;
    } elseif (!in_array($survey['status'], ['draft', 'published'])) { 
        $_SESSION['message'] = "Questions can only be added if survey is 'draft' or 'published'. Current: " . htmlspecialchars($survey['status']); $_SESSION['message_type'] = "warning"; $all_questions_valid = false;
    } else {
        foreach ($new_questions_data as $index => $q_data) {
            $q_text = trim($q_data['text'] ?? ''); $q_type = trim($q_data['type'] ?? '');
            $q_is_required = isset($q_data['is_required']) ? 1 : 0;
            $q_options_input_raw = $q_data['options'] ?? [];
            $q_options_input = is_array($q_options_input_raw) ? array_map('trim', array_filter($q_options_input_raw, 'trim')) : [];

            if (empty($q_text)) { $_SESSION['message'] = "Error in question #".($index+1).": Question text is required."; $_SESSION['message_type'] = "danger"; $all_questions_valid = false; break; }
            if (empty($q_type)) { $_SESSION['message'] = "Error in question #".($index+1).": Question type is required."; $_SESSION['message_type'] = "danger"; $all_questions_valid = false; break; }
            if (in_array($q_type, ['mcq_single', 'mcq_multiple', 'likert_scale', 'rating_scale']) && empty($q_options_input)) {
                 $_SESSION['message'] = "Error in question #".($index+1).": At least one non-empty option is required for type '{$q_type}'."; $_SESSION['message_type'] = "danger"; $all_questions_valid = false; break;
            }
            $questions_to_insert[] = ['text' => $q_text, 'type' => $q_type, 'is_required' => $q_is_required, 'options' => $q_options_input];
        }
    }

    if ($all_questions_valid && !empty($questions_to_insert)) {
        $conn->begin_transaction();
        try {
            $stmt_max_order = $conn->prepare("SELECT MAX(order_in_survey) as max_order FROM questions WHERE survey_id = ?");
            if(!$stmt_max_order) throw new Exception("DB Error (QMO): ".$conn->error);
            $stmt_max_order->bind_param("i", $survey_id); $stmt_max_order->execute();
            $max_order_result = $stmt_max_order->get_result()->fetch_assoc();
            $current_max_order = ($max_order_result['max_order'] ?? 0);
            $stmt_max_order->close();

            $sql_add_q = "INSERT INTO questions (survey_id, question_text, question_type, is_required, order_in_survey) VALUES (?, ?, ?, ?, ?)";
            $stmt_add_q = $conn->prepare($sql_add_q);
            if (!$stmt_add_q) throw new Exception("DB Error (PAQ): " . $conn->error);

            $sql_add_opt = "INSERT INTO question_options (question_id, option_text, order_in_question) VALUES (?, ?, ?)";
            $stmt_add_opt = $conn->prepare($sql_add_opt);
            if (!$stmt_add_opt) throw new Exception("DB Error (PAO): " . $conn->error);

            foreach ($questions_to_insert as $q_to_insert) {
                $current_max_order++;
                $stmt_add_q->bind_param("issii", $survey_id, $q_to_insert['text'], $q_to_insert['type'], $q_to_insert['is_required'], $current_max_order);
                if (!$stmt_add_q->execute()) throw new Exception("DB Error (EAQ '".substr($q_to_insert['text'],0,20)."'): " . $stmt_add_q->error);
                $new_question_id = $stmt_add_q->insert_id;
                if (!$new_question_id) throw new Exception("No QID for question '".substr($q_to_insert['text'],0,20)."'.");

                if (in_array($q_to_insert['type'], ['mcq_single', 'mcq_multiple', 'likert_scale', 'rating_scale']) && !empty($q_to_insert['options'])) {
                    $opt_order = 1;
                    foreach ($q_to_insert['options'] as $opt_text) {
                        $stmt_add_opt->bind_param("isi", $new_question_id, $opt_text, $opt_order);
                        if (!$stmt_add_opt->execute()) throw new Exception("DB Error (EAO '{$opt_text}'): " . $stmt_add_opt->error);
                        $opt_order++;
                    }
                }
            }
            $stmt_add_q->close(); $stmt_add_opt->close();
            $conn->commit();
            $_SESSION['message'] = count($questions_to_insert) . " new question(s) added successfully."; $_SESSION['message_type'] = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Error adding questions: " . $e->getMessage(); $_SESSION['message_type'] = "danger";
            error_log("BatchAddQ Error (SurveyID:$survey_id): ".$e->getMessage());
        }
    } elseif ($all_questions_valid && empty($questions_to_insert) && !isset($_SESSION['message'])) {
         $_SESSION['message'] = "No new questions were processed. Ensure fields are filled correctly."; $_SESSION['message_type'] = "info";
    }
    header("Location: " . htmlspecialchars(BASE_URL . "survey_creator/edit_survey.php?id=" . $survey_id . "#add-questions-panel")); exit();
}


if ($form_action === 'delete_question' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $question_id_to_delete = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
    if ($question_id_to_delete > 0 && $survey['status'] === 'draft') { 
        $stmt_check_q = $conn->prepare("SELECT q.question_id FROM questions q WHERE q.question_id = ? AND q.survey_id = ?");
        if($stmt_check_q){ $stmt_check_q->bind_param("ii", $question_id_to_delete, $survey_id); $stmt_check_q->execute();
            if($stmt_check_q->get_result()->num_rows === 1){
                
                $stmt_del_q = $conn->prepare("DELETE FROM questions WHERE question_id = ?");
                if($stmt_del_q){ $stmt_del_q->bind_param("i", $question_id_to_delete);
                    if($stmt_del_q->execute() && $stmt_del_q->affected_rows > 0){ $_SESSION['message'] = "Question (ID: $question_id_to_delete) deleted."; $_SESSION['message_type'] = "success"; }
                    else { $_SESSION['message'] = "Error deleting question: ".($stmt_del_q->error ?: "Not found or no change."); $_SESSION['message_type'] = "danger"; error_log("Delete Question Execute Error: ".$stmt_del_q->error); }
                    $stmt_del_q->close();
                } else { $_SESSION['message'] = "DB Error preparing delete Q (P_DQ1): ".$conn->error; $_SESSION['message_type'] = "danger"; error_log("Delete Question Prepare Error (del_q): ".$conn->error); }
            } else { $_SESSION['message'] = "Question not found for this survey or delete not permitted."; $_SESSION['message_type'] = "warning"; }
            $stmt_check_q->close();
        } else { $_SESSION['message'] = "DB Error preparing check Q (P_DQ2): ".$conn->error; $_SESSION['message_type'] = "danger"; error_log("Delete Question Check Prepare Error: ".$conn->error); }
    } else { $_SESSION['message'] = "Invalid request or survey not in draft status to delete questions."; $_SESSION['message_type'] = "warning"; }
    header("Location: " . htmlspecialchars(BASE_URL . "survey_creator/edit_survey.php?id=" . $survey_id . "#question-list-area")); exit();
}


$questions_data = [];
$sql_fetch_q = "SELECT * FROM questions WHERE survey_id = ? ORDER BY order_in_survey ASC, question_id ASC";
$stmt_fetch_q = $conn->prepare($sql_fetch_q);
if ($stmt_fetch_q) {
    $stmt_fetch_q->bind_param("i", $survey_id); $stmt_fetch_q->execute(); $result_fetch_q = $stmt_fetch_q->get_result();
    while ($q_row = $result_fetch_q->fetch_assoc()) { $q_row['options'] = [];
        if (in_array($q_row['question_type'], ['mcq_single','mcq_multiple','likert_scale','rating_scale'])) {
            $stmt_fetch_o = $conn->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY order_in_question ASC, option_id ASC");
            if ($stmt_fetch_o) { $stmt_fetch_o->bind_param("i", $q_row['question_id']); $stmt_fetch_o->execute(); $result_fetch_o = $stmt_fetch_o->get_result();
                while ($opt_row = $result_fetch_o->fetch_assoc()) { $q_row['options'][] = $opt_row; } $stmt_fetch_o->close();
            } else { error_log("EditSurvey OptFetchPrep Fail QID " . $q_row['question_id'] . ": " . $conn->error); }
        } $questions_data[] = $q_row;
    } $stmt_fetch_q->close();
} else { error_log("EditSurvey QFetchPrep Fail SID $survey_id: " . $conn->error); echo "<div class='alert alert-danger'>Error loading existing questions.</div>";}


$survey_public_link = ($survey['status'] === 'published') ? rtrim(BASE_URL, '/') . "/take_survey.php?id=" . $survey_id : null;
?>


<div class="survey-management-header">
    <h2>Manage Survey: <?php echo htmlspecialchars($survey['title']); ?></h2>
    <div>
        <a href="<?php echo htmlspecialchars(BASE_URL . "survey_creator/view_survey_detail.php?id=" . $survey_id); ?>" class="button button-view-design" style="margin-right:10px;">View Design</a>
        <a href="<?php echo htmlspecialchars(BASE_URL . "survey_creator/index.php"); ?>" class="button button-secondary">← Back to My Surveys</a>
    </div>
</div>
<p style="margin-bottom: 20px;">Current Status: <strong><span class="status-<?php echo htmlspecialchars($survey['status']); ?>"><?php echo htmlspecialchars(ucfirst($survey['status'])); ?></span></strong></p>


<div id="survey-actions-link-section">
    <h4>Survey Actions & Shareable Link</h4>
    <div class="actions-group">
        <?php if ($survey['status'] === 'draft'): ?>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . "?id=" . $survey_id); ?>" method="POST" style="display:inline-block;"><input type="hidden" name="form_action" value="publish_survey"><button type="submit" class="button button-publish" onclick="return confirm('Publish this survey? It will become live.');">Publish Survey</button></form>
        <?php elseif (in_array($survey['status'], ['published', 'closed'])): ?>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . "?id=" . $survey_id); ?>" method="POST" style="display:inline-block;"><input type="hidden" name="form_action" value="revert_to_draft"><button type="submit" class="button button-revert" onclick="return confirm('Revert to draft? This will unpublish and allow edits.');">Revert to Draft</button></form>
        <?php endif; ?>
    </div>
    <?php if ($survey_public_link): ?>
        <div class="share-link-group"><label for="surveyLinkInput">Public Link:</label><input type="text" id="surveyLinkInput" value="<?php echo htmlspecialchars($survey_public_link); ?>" readonly><button type="button" onclick="copySurveyLink(event)" class="button button-copy" id="copyLinkButtonID">Copy</button></div>
        <p class="link-status-message">Link is active.</p>
    <?php else: ?> <p class="link-status-message"><?php echo ($survey['status'] === 'draft' ? 'Publish survey to activate link.' : "Survey is ".htmlspecialchars($survey['status'])."; link not active."); ?></p> <?php endif; ?>
</div>
<hr style="margin: 30px 0;">


<div class="survey-questions-editor-container">
    <h3>Questions Editor</h3>
    <?php if ($survey['status'] === 'published'): ?><p class="alert alert-warning"><strong>Caution:</strong> Survey published. Add new questions below. Modifying/deleting existing questions disabled. Revert to draft for major edits.</p>
    <?php elseif (in_array($survey['status'], ['closed', 'archived'])): ?><p class="alert alert-info">Survey is '<?php echo htmlspecialchars($survey['status']); ?>'. Revert to draft to edit questions.</p><?php endif; ?>

    <div id="question-list-area">
        <?php if (!empty($questions_data)): foreach ($questions_data as $index => $q): ?>
            <div class="question-card" id="q-card-<?php echo $q['question_id']; ?>">
                <div class="question-card-header">
                    <span class="question-order-handle"><?php echo ($index + 1); ?>.</span>
                    <div class="question-text-display-editable"><?php echo htmlspecialchars($q['question_text']); ?><?php if ($q['is_required']) echo '<span class="required-star">*</span>'; ?></div>
                    <span class="question-type-chip"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $q['question_type']))); ?></span>
                </div>
                <?php if (!empty($q['options'])): ?> <div class="options-display-container"><strong>Options:</strong><ul class="options-preview-list"><?php foreach ($q['options'] as $opt): ?><li><?php echo htmlspecialchars($opt['option_text']); ?></li><?php endforeach; ?></ul></div>
                <?php elseif (in_array($q['question_type'], ['open_ended_short','open_ended_long'])): ?> <div class="input-preview <?php echo str_replace('_','-',$q['question_type']); ?>-preview"><?php echo ucfirst(str_replace('_', ' ', $q['question_type'])); ?> input</div> <?php endif; ?>
                <?php if ($survey['status'] === 'draft'): ?>
                <div class="question-card-actions">
                    <button class="button-icon edit-question-btn" data-question-id="<?php echo $q['question_id']; ?>" title="Edit Question (Advanced JS Feature)">✎</button>
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . "?id=" . $survey_id); ?>" method="POST" style="display:inline;">
                        <input type="hidden" name="form_action" value="delete_question"><input type="hidden" name="question_id" value="<?php echo $q['question_id']; ?>">
                        <button type="submit" class="button-icon delete-question-btn" title="Delete Question" onclick="return confirm('Delete this question?');">✖</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; else: ?><p class="no-questions-message"><em>No questions added yet.</em></p><?php endif; ?>
    </div>

    <?php if (in_array($survey['status'], ['draft', 'published'])): ?>
    <div id="add-questions-panel" class="question-editor-item add-new-question-form">
        <h4>Add New Questions</h4>
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . "?id=" . $survey_id); ?>" method="POST" id="add-batch-questions-form-tag">
            <input type="hidden" name="form_action" value="add_batch_questions">
            <div id="new-questions-container-dynamic"></div>
            <button type="button" id="add-another-question-field-btn" class="button button-secondary" style="margin-top:15px; margin-bottom: 20px;">+ Add Another Question Field</button>
            <div class="form-group"><button type="submit" class="button button-primary">Save All New Questions</button></div>
        </form>
    </div>
    <?php endif; ?>
</div>


<template id="question-block-template">
    <div class="new-question-block question-editor-item" style="border-style:dashed; margin-top:15px; padding:15px; border-radius:6px; background-color:#f9f9f9;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <span class="dynamic-question-number" style="font-weight:bold;">New Question</span>
            <button type="button" class="remove-new-question-btn button-danger button-small" title="Remove this question field">Remove</button>
        </div>
        <div class="form-group"><label for="new_question_text_JS_ID_PLACEHOLDER">Question Text:</label><textarea name="new_question[PHP_ARRAY_INDEX][text]" id="new_question_text_JS_ID_PLACEHOLDER" rows="2" class="form-control new_question_text_input" required></textarea></div>
        <div class="form-group type-and-required"><label for="new_question_type_JS_ID_PLACEHOLDER">Type:</label><select name="new_question[PHP_ARRAY_INDEX][type]" id="new_question_type_JS_ID_PLACEHOLDER" class="form-control new-q-type-select-dynamic" required><option value="">--Select--</option><option value="open_ended_short">Short Answer</option><option value="open_ended_long">Paragraph</option><option value="mcq_single">Multiple choice</option><option value="mcq_multiple">Checkboxes</option><option value="likert_scale">Likert Scale</option><option value="rating_scale">Rating Scale</option></select><label class="is-required-label"><input type="checkbox" name="new_question[PHP_ARRAY_INDEX][is_required]" value="1"> Req</label></div>
        <div class="new-options-area-dynamic" style="display:none;"><label>Options:</label><div class="options-list-dynamic"></div><button type="button" class="add-option-to-question-btn button-add-option">Add Opt</button><small>For Multiple Choice, Likert, or Rating.</small></div>
    </div>
</template>

<script>
    
    document.addEventListener('DOMContentLoaded', function() {
        console.log("Edit Survey JS Initialized");

        window.copySurveyLink = function(event) {
            console.log("copySurveyLink function called.");
            const copyText = document.getElementById("surveyLinkInput");
            const button = event ? event.target : document.getElementById('copyLinkButtonID'); 
            if (!copyText) { console.error("COPYLINK ERROR: Input 'surveyLinkInput' not found!"); alert("Error: Link field missing."); return; }
            copyText.select(); copyText.setSelectionRange(0, 99999);
            try {
                let successful = false;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(copyText.value)
                        .then(() => {
                            console.log('Link copied successfully via navigator.clipboard.'); successful = true;
                            if (button) { const o = button.innerHTML; button.innerHTML = 'Copied!'; button.disabled = true; setTimeout(() => { button.innerHTML = o; button.disabled = false; }, 1500); }
                            else { alert('Link copied!'); }
                        })
                        .catch(err => {
                            console.warn('Async Clipboard API failed, trying execCommand: ', err);
                            successful = document.execCommand('copy'); // Fallback
                            if(successful && button) { const o = button.innerHTML; button.innerHTML = 'Copied!'; button.disabled = true; setTimeout(() => { button.innerHTML = o; button.disabled = false; }, 1500); }
                            else if (successful) { alert('Link copied!'); }
                            else { alert('Failed to copy. Please copy manually.'); }
                        });
                } else { // Fallback for older browsers
                    successful = document.execCommand('copy');
                    if (successful) {
                         if (button) { const o = button.innerHTML; button.innerHTML = 'Copied!'; button.disabled = true; setTimeout(() => { button.innerHTML = o; button.disabled = false; }, 1500); }
                         else { alert('Link copied!'); }
                    } else { alert('Failed to copy automatically. Please copy manually.'); }
                }
            } catch (err) { console.error('Error copying: ', err); alert('Oops, unable to copy automatically.'); }
            if (window.getSelection) { window.getSelection().removeAllRanges(); } else if (document.selection) { document.selection.empty(); }
        };
        const copyBtnElement = document.getElementById('copyLinkButtonID');
        if (copyBtnElement) { copyBtnElement.addEventListener('click', window.copySurveyLink); }


        const questionsContainer = document.getElementById('new-questions-container-dynamic');
        const addQuestionBtn = document.getElementById('add-another-question-field-btn');
        const questionTemplate = document.getElementById('question-block-template');
        let questionCounter = 0;
        const OPTION_TYPES = ['mcq_single', 'mcq_multiple', 'likert_scale', 'rating_scale'];

        function updateAllDynamicQuestionNumbers() {
            if (!questionsContainer) return;
            const questionBlocks = questionsContainer.querySelectorAll('.new-question-block .dynamic-question-number');
            questionBlocks.forEach((span, index) => {
                span.textContent = `New Question ${index + 1}`;
            });
        }

        function createOptionElement(listElement, questionPhpIndex) {
            const optionIndex = listElement.children.length;
            const optionGroup = document.createElement('div');
            optionGroup.className = 'option-input-group'; // For styling option input + remove button
            
            const input = document.createElement('input');
            input.type = 'text';
            input.name = `new_question[${questionPhpIndex}][options][]`;
            input.className = 'form-control option-input';
            input.placeholder = `Option ${optionIndex + 1}`;
            input.required = true; // Options are required if this section is visible
            optionGroup.appendChild(input);

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'remove-option-btn-dynamic'; // Style this button
            removeBtn.innerHTML = '×';
            removeBtn.title = 'Remove Option';
            removeBtn.onclick = () => {
                optionGroup.remove();
                const remainingOptions = listElement.querySelectorAll('.option-input-group input.option-input');
                remainingOptions.forEach((opt, idx) => {
                    opt.placeholder = `Option ${idx + 1}`;
                });
            };
            optionGroup.appendChild(removeBtn);
            listElement.appendChild(optionGroup);
        }

        function toggleOptionsArea(selectElement) {
            const questionBlock = selectElement.closest('.new-question-block');
            if (!questionBlock) return;
            const optionsArea = questionBlock.querySelector('.new-options-area-dynamic');
            const optionsList = optionsArea.querySelector('.options-list-dynamic');
            const questionPhpIndex = selectElement.name.match(/\[(\d+)\]/)[1];

            if (OPTION_TYPES.includes(selectElement.value)) {
                optionsArea.style.display = 'block';
                if (optionsList.children.length === 0) { // Add first option if none exist
                    createOptionElement(optionsList, questionPhpIndex);
                }
                optionsList.querySelectorAll('input.option-input').forEach(inp => inp.required = true);
            } else {
                optionsArea.style.display = 'none';
                optionsList.innerHTML = ''; // Clear options if type changes
            }
        }

        function addQuestionField() {
            if (!questionTemplate || !questionTemplate.content) { console.error("Template missing!"); return; }
            if (!questionsContainer) { console.error("Questions container missing!"); return; }

            questionCounter++; // This is for the PHP array index: new_question[1], new_question[2]
            const jsIdSuffix = Date.now() + "_" + questionCounter; // For unique HTML element IDs

            const clone = questionTemplate.content.cloneNode(true);
            const newBlock = clone.querySelector('.new-question-block');
            if (!newBlock) { console.error("Cloned '.new-question-block' missing!"); return; }
            newBlock.querySelectorAll('[name*="[PHP_ARRAY_INDEX]"]').forEach(el => el.name = el.name.replace('PHP_ARRAY_INDEX', questionCounter));
            newBlock.querySelectorAll('[id*="_JS_ID_PLACEHOLDER"]').forEach(el => el.id = el.id.replace('JS_ID_PLACEHOLDER', jsIdSuffix));
            newBlock.querySelectorAll('label[for*="_JS_ID_PLACEHOLDER"]').forEach(el => el.htmlFor = el.htmlFor.replace('JS_ID_PLACEHOLDER', jsIdSuffix));
            
            questionsContainer.appendChild(newBlock);
            updateAllDynamicQuestionNumbers();

            const typeSelect = newBlock.querySelector('.new-q-type-select-dynamic');
            if(typeSelect) typeSelect.addEventListener('change', () => toggleOptionsArea(typeSelect));
            
            const addOptionButton = newBlock.querySelector('.add-option-to-question-btn');
            const optionsListDiv = newBlock.querySelector('.options-list-dynamic');
            if(addOptionButton && optionsListDiv) addOptionButton.addEventListener('click', () => createOptionElement(optionsListDiv, questionCounter));

            const removeQuestionButton = newBlock.querySelector('.remove-new-question-btn');
            if(removeQuestionButton) removeQuestionButton.addEventListener('click', () => { newBlock.remove(); updateAllDynamicQuestionNumbers(); });
            
            if(typeSelect) toggleOptionsArea(typeSelect); // Initial call to set options visibility
            console.log("Added question field with PHP index: " + questionCounter);
        }

        if (addQuestionBtn && questionsContainer && questionTemplate && questionTemplate.content) {
            addQuestionBtn.addEventListener('click', addQuestionField);
            const addQuestionsPanelEl = document.getElementById('add-questions-panel');
            if (addQuestionsPanelEl && window.getComputedStyle(addQuestionsPanelEl).display !== 'none') {
                addQuestionField(); // Add one by default if panel is visible
            }
        } else {
            console.error("Critical element(s) for 'Add Question' functionality are missing from the page.");
            if(addQuestionBtn) addQuestionBtn.disabled = true; // Disable button if setup is incomplete
        }
        
    });
</script>

<?php
require_once __DIR__ . '/_creator_footer.php';
?>