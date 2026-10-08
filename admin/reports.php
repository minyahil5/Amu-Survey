<?php

$admin_page_title = "Survey Reports";


if (!isset($conn)) {
    require_once __DIR__ . '/../includes/db_connect.php';
}


?>
<div class="manage-users-header"> 
    <h2>Survey Reports Overview</h2>
</div>
<p>Select a survey from the list below to view its response summary and detailed report.</p>

<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Survey Title</th>
            <th>Status</th>
            <th>Total Responses</th>
            <th>Created By</th>
            <th>Closed/Ended On</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php
        
        
        $sql_list = "SELECT s.*, u.username as creator_username,
                        (SELECT COUNT(DISTINCT rs.response_summary_id) FROM survey_responses_summary rs WHERE rs.survey_id = s.survey_id AND rs.response_status = 'completed') as total_responses
                     FROM surveys s
                     LEFT JOIN users u ON s.created_by_user_id = u.user_id
                     WHERE s.status IN ('closed', 'published') -- Or just 'closed', depending on requirements
                     ORDER BY s.survey_id DESC";
        $stmt_list = $conn->prepare($sql_list);

        if ($stmt_list) {
            $stmt_list->execute();
            $result_list = $stmt_list->get_result();
            if ($result_list->num_rows > 0) {
                while ($row = $result_list->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['survey_id']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['title']) . "</td>";
                    echo "<td>" . htmlspecialchars(ucfirst($row['status'])) . "</td>";
                    echo "<td>" . htmlspecialchars($row['total_responses']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['creator_username'] ?? 'N/A') . "</td>";
                    echo "<td>" . ($row['end_date'] ? date("M d, Y", strtotime($row['end_date'])) : ($row['status'] === 'closed' ? 'Manually Closed' : 'Ongoing')) . "</td>";
                    echo "<td class='action-links'>";
                    if ($row['total_responses'] > 0) {
                        echo "<a href='" . BASE_URL . "admin/index.php?page=survey_report_detail&survey_id=" . $row['survey_id'] . "' class='view-link'>View Report</a> ";
                    } else {
                        echo "<span style='color:#777; font-style:italic;'>No responses yet</span>";
                    }
                    
                    
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='7' style='text-align:center;'>No surveys available for reporting or no responses yet.</td></tr>";
            }
            $stmt_list->close();
        } else {
            echo "<tr><td colspan='7' style='text-align:center;'>Error preparing statement: " . $conn->error . "</td></tr>";
        }
        ?>
    </tbody>
</table>