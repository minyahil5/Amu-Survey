<?php



ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost'; 
    
    $script_name_parts = explode('/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $project_folder_name = 'amu_survey_system'; 
    $base_path = '';
    $found_root = false;
    foreach($script_name_parts as $part){
        if(!empty($part)){
            $base_path .= '/' . $part;
            if($part == $project_folder_name){
                $found_root = true;
                break;
            }
        }
    }
    if (!$found_root && strpos(dirname(__FILE__, 2), $project_folder_name) !== false) { 
         $base_path = '/' . $project_folder_name;
    } elseif (!$found_root) {
        $base_path = '/' . $project_folder_name; 
        
        
    }
    define('BASE_URL', rtrim($protocol . $host . $base_path, '/') . '/');
}

if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'AMU Satisfaction Survey System');
}


if (!defined('DB_SERVER')) define('DB_SERVER', 'localhost');
if (!defined('DB_USERNAME')) define('DB_USERNAME', 'root');    
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', '');        
if (!defined('DB_NAME')) define('DB_NAME', 'amu_survey_db'); 



if (!isset($conn) || !($conn instanceof mysqli)) {
    
    require_once __DIR__ . '/../includes/db_connect.php'; 

    
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
        $db_connection_error_msg = isset($conn) && $conn->connect_error ? $conn->connect_error : "mysqli object not created or connect_error not set.";
        error_log("CRITICAL DB CONNECTION ERROR in config.php after including db_connect.php: " . $db_connection_error_msg);
        
        
    }
}


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


if (!defined('SURVEY_ATTACHMENT_UPLOAD_DIR_ABS')) { 
    define('SURVEY_ATTACHMENT_UPLOAD_DIR_ABS', dirname(__DIR__) . '/uploads/survey_attachments/'); 
}







?>