<?php






if (!defined('DB_SERVER') || !defined('DB_USERNAME') || !defined('DB_PASSWORD') || !defined('DB_NAME')) {
    error_log("Database configuration constants (DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME) are not defined. Check config.php.");
    
    $conn = null; 
    
    return; 
}


$conn = @new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);


if ($conn->connect_error) {
    
    
    
} else {
    
    if (!$conn->set_charset("utf8mb4")) {
        error_log("Error loading character set utf8mb4 for database connection: " . $conn->error);
    }
}

/** @var mysqli $conn */


?>