<?php

$secret = yourls_get_option( 'rikodev_hcaptcha_private' );

// The response from hCaptcha
$resp = NULL;
$error_message = NULL;
$error_codes = array();

// Check if secret key is configured
if (empty($secret) || trim($secret) == "") {
    // Secret not configured, validation will fail
    $resp = false;
    $error_message = 'hCaptcha is not properly configured. Please contact the administrator.';
} elseif (!isset($_POST['h-captcha-response']) || empty($_POST['h-captcha-response'])) {
    // hCaptcha response not provided
    $resp = false;
    $error_message = 'Please complete the hCaptcha challenge.';
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
            'timeout' => 15,
            'method' => 'GET',
            'ignore_errors' => true
        )
    ));
    
    $verify_response = @file_get_contents($verify_url, false, $context);
    
    if ($verify_response === false) {
        // API call failed - network error
        $resp = false;
        $error_message = 'Network error. Please check your internet connection and try again.';
        if (defined('YOURLS_DEBUG') && YOURLS_DEBUG) {
            error_log('hCaptcha API call failed: Unable to connect to hCaptcha service');
        }
    } else {
        // Decode JSON response
        $data = json_decode($verify_response, true);
        
        // Validate JSON decode success and response structure
        if (json_last_error() === JSON_ERROR_NONE && is_array($data) && isset($data['success'])) {
            $resp = ($data['success'] === true);
            
            // Check for error codes and provide user-friendly messages
            if (!$resp && isset($data['error-codes']) && is_array($data['error-codes'])) {
                $error_codes = $data['error-codes'];
                
                // Map hCaptcha error codes to user-friendly messages
                foreach ($error_codes as $error_code) {
                    switch ($error_code) {
                        case 'missing-input-secret':
                        case 'invalid-input-secret':
                            $error_message = 'hCaptcha configuration error. Please contact the administrator.';
                            break;
                        case 'missing-input-response':
                            $error_message = 'Please complete the hCaptcha challenge.';
                            break;
                        case 'invalid-input-response':
                            $error_message = 'Invalid hCaptcha response. Please try again.';
                            break;
                        case 'bad-request':
                            $error_message = 'Invalid request. Please try again.';
                            break;
                        case 'invalid-or-already-seen-response':
                            $error_message = 'This hCaptcha response has already been used. Please complete a new challenge.';
                            break;
                        case 'not-using-dummy-passcode':
                            // This is usually not shown to users
                            break;
                        case 'sitekey-secret-mismatch':
                            $error_message = 'hCaptcha configuration error. Please contact the administrator.';
                            break;
                        default:
                            // For unknown errors, provide a generic message
                            if (empty($error_message)) {
                                $error_message = 'hCaptcha validation failed. Please try again.';
                            }
                            break;
                    }
                }
                
                // Log error codes if debugging is enabled
                if (defined('YOURLS_DEBUG') && YOURLS_DEBUG) {
                    error_log('hCaptcha validation failed with errors: ' . implode(', ', $error_codes));
                }
            } elseif (!$resp) {
                // Success is false but no error codes provided
                $error_message = 'hCaptcha validation failed. Please try again.';
            }
        } else {
            // Invalid JSON response
            $resp = false;
            $error_message = 'Invalid response from hCaptcha service. Please try again.';
            if (defined('YOURLS_DEBUG') && YOURLS_DEBUG) {
                error_log('hCaptcha API returned invalid JSON: ' . json_last_error_msg());
            }
        }
    }
}

// Set default error message if none was set
if ($resp === false && empty($error_message)) {
    $error_message = 'hCaptcha validation failed. Please try again.';
}

?>