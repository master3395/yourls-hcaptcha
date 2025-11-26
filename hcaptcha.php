<?php

$secret = yourls_get_option( 'rikodev_hcaptcha_private' );

// The response from hCaptcha
$resp = NULL;

// Check if secret key is configured
if (empty($secret) || trim($secret) == "") {
    // Secret not configured, validation will fail
    $resp = false;
} elseif (!isset($_POST['h-captcha-response']) || empty($_POST['h-captcha-response'])) {
    // hCaptcha response not provided
    $resp = false;
} else {
    
    // Get user IP using YOURLS function for proper handling
    $user_ip = function_exists('yourls_get_IP') ? yourls_get_IP() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    
    // Prepare API parameters with proper URL encoding
    $api_params = array(
        'secret' => $secret,
        'response' => $_POST['h-captcha-response'],
        'remoteip' => $user_ip
    );
    
    // Build verification URL with proper encoding
    $verify_url = 'https://hcaptcha.com/siteverify?' . http_build_query($api_params);
    
    // Make API call with error handling and timeout
    $context = stream_context_create(array(
        'http' => array(
            'timeout' => 10,
            'method' => 'GET'
        )
    ));
    
    $verify_response = @file_get_contents($verify_url, false, $context);
    
    if ($verify_response === false) {
        // API call failed, log error if debugging is enabled
        if (defined('YOURLS_DEBUG') && YOURLS_DEBUG) {
            error_log('hCaptcha API call failed: Unable to connect to hCaptcha service');
        }
        $resp = false;
    } else {
        // Decode JSON response
        $data = json_decode($verify_response, true);
        
        // Validate JSON decode success and response structure
        if (json_last_error() === JSON_ERROR_NONE && is_array($data) && isset($data['success'])) {
            $resp = ($data['success'] === true);
            
            // Log error codes if validation failed and debugging is enabled
            if (!$resp && defined('YOURLS_DEBUG') && YOURLS_DEBUG && isset($data['error-codes']) && is_array($data['error-codes'])) {
                error_log('hCaptcha validation failed with errors: ' . implode(', ', $data['error-codes']));
            }
        } else {
            // Invalid JSON response
            if (defined('YOURLS_DEBUG') && YOURLS_DEBUG) {
                error_log('hCaptcha API returned invalid JSON: ' . json_last_error_msg());
            }
            $resp = false;
        }
    }
}

?>