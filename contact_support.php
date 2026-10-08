<?php

$page_title = "Contact Support";

require_once __DIR__ . '/config/config.php';

require_once __DIR__ . '/includes/header.php';


$name_val = isset($_SESSION['username']) ? $_SESSION['username'] : ''; 
$email_val = isset($_SESSION['email']) ? $_SESSION['email'] : '';   
$subject_val = '';
$message_val = '';

$form_errors = [];
$feedback_message = '';
$submission_success = null; 
$form_submitted_flag = false; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_support_request'])) {
    $form_submitted_flag = true;
    

    
    $name_val = trim($_POST['name'] ?? '');
    $email_val = trim($_POST['email'] ?? '');
    $subject_val = trim($_POST['subject'] ?? '');
    $message_val = trim($_POST['message'] ?? '');

    
    $logged_in_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $logged_in_username = isset($_SESSION['username']) ? $_SESSION['username'] : null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

    
    if (empty($name_val)) { $form_errors['name'] = "Your name is required."; }
    if (empty($email_val)) { $form_errors['email'] = "Your email address is required."; }
    elseif (!filter_var($email_val, FILTER_VALIDATE_EMAIL)) { $form_errors['email'] = "Please enter a valid email address."; }
    if (empty($subject_val)) { $form_errors['subject'] = "Subject is required."; }
    elseif (strlen($subject_val) < 5) { $form_errors['subject'] = "Subject should be at least 5 characters long.";}
    if (empty($message_val)) { $form_errors['message'] = "Message is required."; }
    elseif (strlen($message_val) < 10) { $form_errors['message'] = "Your message is too short. Please provide more details.";}

    
    if (empty($form_errors)) {
        $conn->begin_transaction(); 
        try {
            
            $sql_insert_request = "INSERT INTO support_requests 
                                   (name, email, subject, message, user_id, username, ip_address, submitted_at, status)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'new')";
            $stmt_insert = $conn->prepare($sql_insert_request);
            if (!$stmt_insert) {
                throw new Exception("Database prepare error for support request: " . $conn->error);
            }
            $stmt_insert->bind_param("ssssiss", 
                $name_val, $email_val, $subject_val, $message_val, 
                $logged_in_user_id, $logged_in_username, $ip_address
            );

            if (!$stmt_insert->execute()) {
                throw new Exception("Database execute error for support request: " . $stmt_insert->error);
            }
            $new_request_id = $stmt_insert->insert_id;
            $stmt_insert->close();

            
            $email_notification_sent = false;
            $support_team_email = "survey.support@amu.ac.in"; 
            $email_subject_notification = "New Survey Support Request (#{$new_request_id}): " . htmlspecialchars($subject_val);
            
            $email_body_notification = "A new support request has been submitted to the AMU Survey System.\n\n";
            $email_body_notification .= "Request ID: {$new_request_id}\n";
            $email_body_notification .= "Name: " . htmlspecialchars($name_val) . "\n";
            $email_body_notification .= "Email: " . htmlspecialchars($email_val) . "\n";
            if ($logged_in_username) {
                 $email_body_notification .= "Logged-in Username: " . htmlspecialchars($logged_in_username) . " (ID: " . $logged_in_user_id . ")\n";
            }
            $email_body_notification .= "Subject: " . htmlspecialchars($subject_val) . "\n\n";
            $email_body_notification .= "Message:\n" . wordwrap(htmlspecialchars($message_val), 70, "\r\n") . "\n\n";
            $email_body_notification .= "--------------------------------------\n";
            $email_body_notification .= "Submitted at: " . date("Y-m-d H:i:s") . "\n";
            $email_body_notification .= "IP Address: " . ($ip_address ?? 'N/A') . "\n";

            $headers = "From: AMU Survey System <no-reply@" . ($_SERVER['SERVER_NAME'] ?? 'amu.ac.in') . ">\r\n";
            $headers .= "Reply-To: " . htmlspecialchars($name_val) . " <" . htmlspecialchars($email_val) . ">\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            
            if (@mail($support_team_email, $email_subject_notification, $email_body_notification, $headers)) {
                $email_notification_sent = true;
            } else {
                
                error_log("Contact Support - Email notification to support team FAILED for request ID: {$new_request_id}. To: $support_team_email, Subject: $subject_val");
            }

            $conn->commit(); 
            $submission_success = true;
            $feedback_message = "Your support request (ID: #{$new_request_id}) has been successfully submitted. ";
            $feedback_message .= $email_notification_sent ? "An email notification has been sent." : "We will process it shortly.";
            
            
            $name_val = $email_val = $subject_val = $message_val = '';

        } catch (Exception $e) {
            $conn->rollback(); 
            $submission_success = false;
            $feedback_message = "A critical error occurred while processing your request. Please try again.";
            error_log("Contact Support - DB Transaction Error: " . $e->getMessage());
        }
    } else {
        
        $submission_success = false;
        
    }
}
?>

<div class="static-page-content contact-support-page">
    <h1>Contact Support</h1>
    <p>If you are experiencing technical difficulties with the AMU Survey System, have questions about a specific survey, or require any assistance, please fill out the form below. Our dedicated support team will review your request and get back to you as soon as possible.</p>

    <?php if ($form_submitted_flag && $submission_success !== null): ?>
        <div class="alert <?php echo ($submission_success === true) ? 'alert-success' : 'alert-danger'; ?>">
            <?php echo htmlspecialchars($feedback_message); ?>
            <?php if ($submission_success === false && !empty($form_errors)): ?>
                <ul style="margin-top:10px; text-align:left; padding-left: 20px;">
                    <?php foreach ($form_errors as $field => $err_msg): ?>
                        <li><?php echo htmlspecialchars($err_msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($submission_success !== true): ?>
    <form action="<?php echo htmlspecialchars(BASE_URL . 'contact_support.php'); ?>" method="POST" class="support-form" novalidate>
        <?php ?>
        
        <div class="form-group">
            <label for="contact_name">Your Name:</label>
            <input type="text" name="name" id="contact_name" class="form-control" value="<?php echo htmlspecialchars($name_val); ?>" required>
            <?php if (isset($form_errors['name'])): ?><small class="error-text"><?php echo htmlspecialchars($form_errors['name']); ?></small><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="contact_email">Your Email Address:</label>
            <input type="email" name="email" id="contact_email" class="form-control" value="<?php echo htmlspecialchars($email_val); ?>" required>
            <?php if (isset($form_errors['email'])): ?><small class="error-text"><?php echo htmlspecialchars($form_errors['email']); ?></small><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="contact_subject">Subject:</label>
            <input type="text" name="subject" id="contact_subject" class="form-control" value="<?php echo htmlspecialchars($subject_val); ?>" required minlength="5">
             <?php if (isset($form_errors['subject'])): ?><small class="error-text"><?php echo htmlspecialchars($form_errors['subject']); ?></small><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="contact_message">Message / Issue Description:</label>
            <textarea name="message" id="contact_message" rows="7" class="form-control" required minlength="10"><?php echo htmlspecialchars($message_val); ?></textarea>
            <?php if (isset($form_errors['message'])): ?><small class="error-text"><?php echo htmlspecialchars($form_errors['message']); ?></small><?php endif; ?>
        </div>
        <div class="form-group">
            <button type="submit" name="submit_support_request" class="button button-primary">Send Support Request</button>
        </div>
    </form>
    <?php endif; ?>

    <hr style="margin: 40px 0 30px;">
    <p><strong>Alternatively, for urgent matters, you can contact the AMU IT Support Desk directly:</strong></p>
    <p class="b"><b>Email:</b> <a href="mailto:itsupport@amu.edu.et" class="pria">itsupport@amu.edu.et</a> (amu@gmail.ccom)</p>
    <p class="b"><b>Phone:</b> +251900000000 (0468745423)</p>
    
</div>
<style> 
    .support-form .form-group { margin-bottom: 20px; }
    .error-text { color: #dc3545; font-size: 0.875em; display: block; margin-top: .25rem; }
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
?>