<?php

$admin_page_title = "View Support Tickets";

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    
    $_SESSION['message'] = "Database connection not available for support tickets page. Please contact support.";
    $_SESSION['message_type'] = "danger";
    
    
    
    
    $support_tickets = []; 
    $error_loading_tickets = true; 
} else {
    $error_loading_tickets = false;
}

/** @var mysqli $conn */




$form_action = isset($_POST['form_action']) ? $_POST['form_action'] : null; 
$request_id_to_update = isset($_POST['request_id']) ? (int)$_POST['request_id'] : null; 

if (!$error_loading_tickets && $form_action === 'update_ticket_status' && $request_id_to_update > 0 && isset($_POST['new_ticket_status'])) {
    

    $new_ticket_status_val = trim($_POST['new_ticket_status']);
    $allowed_ticket_statuses = ['new', 'pending', 'resolved', 'closed']; 

    if (in_array($new_ticket_status_val, $allowed_ticket_statuses)) {
        $sql_update_ticket = "UPDATE support_requests SET status = ?";
        $params_types = "s";
        $params_values = [$new_ticket_status_val];

        
        if (in_array($new_ticket_status_val, ['resolved', 'closed'])) {
            $sql_update_ticket .= ", resolved_at = NOW()";
        } else {
            $sql_update_ticket .= ", resolved_at = NULL"; 
        }
        $sql_update_ticket .= " WHERE request_id = ?";
        $params_types .= "i";
        $params_values[] = $request_id_to_update;
        
        $stmt_update = $conn->prepare($sql_update_ticket);
        if ($stmt_update) {
            $stmt_update->bind_param($params_types, ...$params_values); 
            if ($stmt_update->execute()) {
                if ($stmt_update->affected_rows > 0) {
                    $_SESSION['message'] = "Support ticket #{$request_id_to_update} status updated to '" . htmlspecialchars(ucfirst($new_ticket_status_val)) . "'.";
                    $_SESSION['message_type'] = "success";
                } else {
                     $_SESSION['message'] = "Ticket #{$request_id_to_update} status was already '".htmlspecialchars(ucfirst($new_ticket_status_val))."' or ticket not found.";
                     $_SESSION['message_type'] = "info";
                }
            } else {
                $_SESSION['message'] = "Error updating ticket status: " . htmlspecialchars($stmt_update->error);
                $_SESSION['message_type'] = "danger";
                error_log("Admin SupportTickets - Update Status Execute Error: " . $stmt_update->error);
            }
            $stmt_update->close();
        } else {
            $_SESSION['message'] = "DB error preparing status update: " . htmlspecialchars($conn->error);
            $_SESSION['message_type'] = "danger";
            error_log("Admin SupportTickets - Update Status Prepare Error: " . $conn->error . " | SQL: " . $sql_update_ticket);
        }
    } else {
        $_SESSION['message'] = "Invalid status provided for ticket update.";
        $_SESSION['message_type'] = "warning";
    }
    
    $redirect_filter = isset($_GET['filter_status']) ? "&filter_status=" . urlencode($_GET['filter_status']) : "";
    header("Location: " . htmlspecialchars(BASE_URL . "admin/index.php?page=support_tickets" . $redirect_filter));
    exit();
}




$filter_status_get = isset($_GET['filter_status']) ? trim($_GET['filter_status']) : 'all';
$support_tickets = [];
$sql_fetch_tickets = "SELECT sr.*, u.username as submitter_username 
                      FROM support_requests sr 
                      LEFT JOIN users u ON sr.user_id = u.user_id"; 

$where_clauses_fetch = [];
$bind_types_fetch = "";
$bind_params_fetch = [];

if ($filter_status_get !== 'all' && in_array($filter_status_get, ['new', 'pending', 'resolved', 'closed'])) {
    $where_clauses_fetch[] = "sr.status = ?";
    $bind_types_fetch .= "s";
    $bind_params_fetch[] = $filter_status_get;
}
if (!empty($where_clauses_fetch)) {
    $sql_fetch_tickets .= " WHERE " . implode(" AND ", $where_clauses_fetch);
}
$sql_fetch_tickets .= " ORDER BY sr.submitted_at DESC, sr.request_id DESC";

if (!$error_loading_tickets) { 
    $stmt_fetch = $conn->prepare($sql_fetch_tickets);
    if ($stmt_fetch) {
        if (!empty($bind_params_fetch)) {
            $stmt_fetch->bind_param($bind_types_fetch, ...$bind_params_fetch);
        }
        if ($stmt_fetch->execute()) {
            $result = $stmt_fetch->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $support_tickets[] = $row;
                }
            } else { $error_loading_tickets = true; error_log("Admin ST - GetResult Fail: ".$conn->error); }
        } else { $error_loading_tickets = true; error_log("Admin ST - Exec Fail: ".$stmt_fetch->error); }
        $stmt_fetch->close();
    } else {
        $error_loading_tickets = true;
        error_log("Admin ST - Prep Fail: ".$conn->error." | SQL: ".$sql_fetch_tickets);
    }
}
?>

<div class="manage-users-header"> 
    <h2>Support Tickets / Contact Messages</h2>
    <div class="filter-controls">
        <form action="<?php echo htmlspecialchars(BASE_URL . "admin/index.php"); ?>" method="GET" style="display:inline-block;">
            <input type="hidden" name="page" value="support_tickets">
            <label for="filter_status_select">Filter by status:</label>
            <select name="filter_status" id="filter_status_select" onchange="this.form.submit()" class="form-control" style="display:inline-block; width:auto;">
                <option value="all" <?php if ($filter_status_get === 'all') echo 'selected'; ?>>All Tickets</option>
                <option value="new" <?php if ($filter_status_get === 'new') echo 'selected'; ?>>New</option>
                <option value="pending" <?php if ($filter_status_get === 'pending') echo 'selected'; ?>>Pending</option>
                <option value="resolved" <?php if ($filter_status_get === 'resolved') echo 'selected'; ?>>Resolved</option>
                <option value="closed" <?php if ($filter_status_get === 'closed') echo 'selected'; ?>>Closed</option>
            </select>
            
        </form>
    </div>
</div>
<p>Review and manage support requests submitted through the contact form.</p>

<?php if ($error_loading_tickets): ?>
    <div class="alert alert-danger">Could not load support tickets due to a technical issue. Please check server logs.</div>
<?php elseif (!empty($support_tickets)): ?>
    <table class="admin-table support-tickets-table">
        <thead>
            <tr>
                <th>ID</th><th>Submitted By</th><th>Email</th><th>Subject</th>
                <th>Date Submitted</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($support_tickets as $ticket): ?>
                <tr>
                    <td><?php echo htmlspecialchars($ticket['request_id']); ?></td>
                    <td><?php echo htmlspecialchars($ticket['name']); ?>
                        <?php if ($ticket['username'] || $ticket['user_id']): ?>
                            <br><small style="color:#555;">(User: <?php echo htmlspecialchars($ticket['submitter_username'] ?? ($ticket['username'] ?? 'ID: '.$ticket['user_id'])); ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td><a href="mailto:<?php echo htmlspecialchars($ticket['email']); ?>"><?php echo htmlspecialchars($ticket['email']); ?></a></td>
                    <td>
                        <a href="#" class="view-message-link" 
                           data-message="<?php echo htmlspecialchars($ticket['message']); ?>" 
                           data-subject="<?php echo htmlspecialchars($ticket['subject']); ?>" 
                           title="Click to view full message">
                            <?php echo htmlspecialchars(substr($ticket['subject'], 0, 45)) . (strlen($ticket['subject']) > 45 ? '...' : ''); ?>
                        </a>
                    </td>
                    <td><?php echo date("M d, Y H:i", strtotime($ticket['submitted_at'])); ?></td>
                    <td>
                        
                        <form action="<?php echo htmlspecialchars(BASE_URL . "admin/index.php?page=support_tickets"); ?>" method="POST" class="status-change-form-inline">
                            <input type="hidden" name="form_action" value="update_ticket_status"> 
                            <input type="hidden" name="request_id" value="<?php echo $ticket['request_id']; ?>">
                             <?php if(isset($_GET['filter_status'])): ?>
                                <input type="hidden" name="filter_status_persist" value="<?php echo htmlspecialchars($_GET['filter_status']); ?>">
                             <?php endif; ?>
                            <?php ?>
                            <select name="new_ticket_status" onchange="this.form.submit()" title="Change ticket status" class="form-control" style="padding:4px 6px; font-size:0.9em; width: auto;">
                                <option value="new" <?php if ($ticket['status'] === 'new') echo 'selected'; ?>>New</option>
                                <option value="pending" <?php if ($ticket['status'] === 'pending') echo 'selected'; ?>>Pending</option>
                                <option value="resolved" <?php if ($ticket['status'] === 'resolved') echo 'selected'; ?>>Resolved</option>
                                <option value="closed" <?php if ($ticket['status'] === 'closed') echo 'selected'; ?>>Closed</option>
                            </select>
                        </form>
                    </td>
                    <td class="action-links">
                        <button type="button" class="view-message-link button button-small" 
                               data-message="<?php echo htmlspecialchars($ticket['message']); ?>" 
                               data-subject="<?php echo htmlspecialchars($ticket['subject']); ?>">View Full Message</button>
                        
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <div class="alert alert-info" style="margin-top: 20px;">
        No support tickets found<?php echo ($filter_status_get !== 'all' ? ' with status: <strong>' . htmlspecialchars(ucfirst($filter_status_get)) . '</strong>' : ''); ?>.
    </div>
<?php endif; ?>


<div id="messageModal" class="modal" style="display:none;">  </div>
<style>  </style>
<script>  </script>

<?php

?>