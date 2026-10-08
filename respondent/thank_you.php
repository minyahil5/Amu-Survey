<?php

$page_title = "Thank You";
require_once __DIR__ . '/../includes/header.php'; 

$survey_id = isset($_GET['survey_id']) ? (int)$_GET['survey_id'] : 0;


$survey_title = 'the survey';
if ($survey_id > 0) {
    $stmt = $conn->prepare("SELECT title FROM surveys WHERE survey_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $survey_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $survey_title = '"' . htmlspecialchars($row['title']) . '"';
        }
        $stmt->close();
    }
}

?>

<div class="thank-you-message" style="text-align: center; padding: 40px 15px;">
    <h2 style="color: #27ae60;">Thank You!</h2>
    <p style="font-size: 1.2em; margin-top: 15px;">Your responses for <?php echo $survey_title; ?> have been successfully submitted.</p>
    <p style="margin-top: 30px;">We appreciate your valuable feedback.</p>
    <p style="margin-top: 30px;">
        <a href="<?php echo BASE_URL; ?>respondent/available_surveys.php" class="button button-secondary">← Back to Available Surveys</a>
    </p>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php'; 
?>