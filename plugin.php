<?php

/*
Plugin Name: Yourls-hCaptcha
Plugin URI: https://github.com/RikoDEV/yourls-hcaptcha.git
Description: Protect your admin dashboard with hCaptcha!
Version: 1.0
Author: RikoDEV
Author URI: https://riko.dev
*/

if (!defined('YOURLS_ABSPATH')) die();

yourls_add_action('pre_login_username_password', 'rikodev_hcaptcha_validate');

// Validate hCaptcha response
function rikodev_hcaptcha_validate()
{
    // Check if hCaptcha is configured before enforcing validation
    $secret = yourls_get_option('rikodev_hcaptcha_private', '');
    $sitekey = yourls_get_option('rikodev_hcaptcha_site', '');
    
    // If keys are not configured, skip validation (allow plugin to be activated without blocking login)
    if (empty($secret) || empty($sitekey) || trim($secret) == '' || trim($sitekey) == '') {
        // Keys not configured yet, skip validation but log warning if debugging
        if (defined('YOURLS_DEBUG') && YOURLS_DEBUG) {
            error_log('hCaptcha plugin is active but keys are not configured. Please configure keys in Admin hCaptcha Settings.');
        }
        return true; // Allow login to proceed if not configured
    }
    
    // Only validate hCaptcha if this is a POST request with username/password
    // Don't validate on cookie-based authentication or API requests
    if (!isset($_POST['username']) || !isset($_POST['password']) || 
        empty($_POST['username']) || empty($_POST['password'])) {
        // Not a login form submission, skip hCaptcha validation
        return true;
    }
    
    // Check if hCaptcha response is present
    if (!isset($_POST['h-captcha-response']) || empty(trim($_POST['h-captcha-response']))) {
        // hCaptcha response missing - show error but don't break login flow
        yourls_do_action('login_failed');
        yourls_login_screen('Please complete the hCaptcha challenge before logging in.');
        die();
        return false;
    }
    
    // Keys are configured, enforce validation
    include('hcaptcha.php');

    if ($resp === true) {
        return true;
    } else {
        yourls_do_action('login_failed');
        // Use the error message from hcaptcha.php, or fallback to default
        $error_msg = isset($error_message) && !empty($error_message) 
            ? $error_message 
            : 'hCaptcha validation failed. Please complete the hCaptcha challenge and try again.';
        yourls_login_screen($error_msg);
        die();
        return false;
    }
}

// Register plugin on admin page
yourls_add_action('plugins_loaded', 'rikodev_hcaptcha_load');

function rikodev_hcaptcha_load()
{
    yourls_register_plugin_page('admin_hcaptcha', 'Admin hCaptcha Settings', 'admin_hcaptcha_config_page');
    // Handle POST requests early via admin_init hook (before YOURLS processes them)
    yourls_add_action('admin_init', 'rikodev_hcaptcha_handle_post_early');
    // Fix login redirect URL to prevent nonce errors after successful login
    yourls_add_filter('redirect_location', 'rikodev_hcaptcha_fix_login_redirect', 10, 2);
}


// Fix login redirect URL to prevent nonce errors
function rikodev_hcaptcha_fix_login_redirect($location, $code)
{
    // Only fix redirects after login (when redirecting to admin area)
    if (strpos($location, '/admin') !== false) {
        // Simple approach: clean up the URL string directly
        // Remove trailing ? and empty query strings
        $location = rtrim($location, '?');
        // Remove .php extension for clean URL
        $location = str_replace('/admin/index.php', '/admin', $location);
        $location = str_replace('/admin/index.php?', '/admin', $location);
        // Ensure no double slashes (except after protocol)
        $location = preg_replace('#([^:])//+#', '$1/', $location);
        // If URL ends with just /admin or /admin/, ensure it's /admin (no trailing slash)
        if (preg_match('#/admin/?$#', $location) && !preg_match('#/admin/[^/]+#', $location)) {
            $location = preg_replace('#/admin/?$#', '/admin', $location);
        }
    }
    return $location;
}

// Handle POST requests before YOURLS processes them
function rikodev_hcaptcha_handle_post_early()
{
    // Only process if this is a POST request for our plugin page
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
        isset($_POST['rikodev_hcaptcha_publickey']) &&
        ((isset($_GET['page']) && $_GET['page'] === 'admin_hcaptcha') || 
         (isset($_POST['page']) && $_POST['page'] === 'admin_hcaptcha'))) {
        
        // Verify nonce
        $nonce_value = isset($_POST['nonce']) ? $_POST['nonce'] : false;
        
        if ($nonce_value) {
            $action = 'admin_hcaptcha_config_nonce';
            $user = defined('YOURLS_USER') ? YOURLS_USER : '-1';
            $expected_nonce = yourls_create_nonce($action, $user);
            
            if ($nonce_value === $expected_nonce) {
                // Save settings and redirect - this will exit
                admin_hcaptcha_save();
                // Should never reach here due to exit in admin_hcaptcha_save()
            } else {
                // Nonce invalid - redirect with error parameter
                $redirect_url = yourls_admin_url('plugins.php?page=admin_hcaptcha&error=invalid_nonce');
                $redirect_url = str_replace('/plugins.php', '/plugins', $redirect_url);
                yourls_redirect($redirect_url, 302);
                exit;
            }
        } else {
            // No nonce - redirect with error parameter
            $redirect_url = yourls_admin_url('plugins.php?page=admin_hcaptcha&error=missing_nonce');
            $redirect_url = str_replace('/plugins.php', '/plugins', $redirect_url);
            yourls_redirect($redirect_url, 302);
            exit;
        }
    }
}

// The function that will draw the config page
function admin_hcaptcha_config_page()
{
    // POST requests are handled by rikodev_hcaptcha_handle_post_early() via admin_init hook
    // This function only displays the page
    
    // Display error message if redirected with error parameter
    if (isset($_GET['error'])) {
        if ($_GET['error'] === 'invalid_nonce') {
            echo '<div class="notice notice-error"><p><strong>Error:</strong> Invalid or expired security token. Please refresh the page and try again.</p></div>';
        } elseif ($_GET['error'] === 'missing_nonce') {
            echo '<div class="notice notice-error"><p><strong>Error:</strong> Security token missing. Please refresh the page and try again.</p></div>';
        }
    }

    $nonce = yourls_create_nonce('admin_hcaptcha_config_nonce');
    $pubkey = yourls_get_option('rikodev_hcaptcha_site', "");
    $privkey = yourls_get_option('rikodev_hcaptcha_private', "");

    // Show success message if redirected after save
    if (isset($_GET['saved']) && $_GET['saved'] == '1') {
        echo '<div class="notice notice-success"><p><strong>hCaptcha settings saved successfully!</strong></p></div>';
    }

    echo '<h2>Admin hCaptcha Settings</h2>';
    
    // Show current configuration status
    if (!empty($pubkey)) {
        // Use colors that work in both light and dark modes
        $bg_color = 'rgba(40, 167, 69, 0.15)';
        $border_color = '#28a745';
        $text_color = '#28a745';
        echo '<div style="margin-bottom: 20px; padding: 15px; background-color: ' . $bg_color . '; border-left: 4px solid ' . $border_color . '; border-radius: 4px; color: inherit;">';
        echo '<style>
            .hcaptcha-status code {
                background-color: rgba(0, 0, 0, 0.1);
                padding: 2px 6px;
                border-radius: 3px;
                font-family: monospace;
            }
            @media (prefers-color-scheme: dark) {
                .hcaptcha-status code {
                    background-color: rgba(255, 255, 255, 0.15);
                }
            }
            body.dark .hcaptcha-status code,
            body[class*="dark"] .hcaptcha-status code {
                background-color: rgba(255, 255, 255, 0.15) !important;
            }
        </style>';
        echo '<div class="hcaptcha-status">';
        echo '<strong>Current Site Key:</strong> <code>' . htmlspecialchars($pubkey, ENT_QUOTES, 'UTF-8') . '</code><br>';
        echo '<strong style="color: ' . $text_color . ';">✓ Site key is configured!</strong>';
        echo '</div>';
        echo '</div>';
    }
    
    // Get current plugin page URL for form action
    $form_action = yourls_admin_url('plugins.php?page=admin_hcaptcha');
    // Remove .php extension for clean URL
    $form_action = str_replace('/plugins.php', '/plugins', $form_action);
    
    echo '<form method="post" action="' . htmlspecialchars($form_action, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="page" value="admin_hcaptcha" />';
    echo '<input type="hidden" name="nonce" value="' . $nonce . '" />';
    echo '<p><label for="rikodev_hcaptcha_publickey">hCaptcha site key:</label>';
    echo '<input type="text" id="rikodev_hcaptcha_publickey" name="rikodev_hcaptcha_publickey" value="' . htmlspecialchars($pubkey, ENT_QUOTES, 'UTF-8') . '" style="width: 100%; max-width: 500px;" placeholder="Enter your hCaptcha site key">';
    echo '<br><small style="color: #666; opacity: 0.8;">Get your site key from <a href="https://dashboard.hcaptcha.com/" target="_blank">hCaptcha dashboard</a></small></p>';
    echo '<p><label for="rikodev_hcaptcha_privatekey">hCaptcha secret key:</label>';
    echo '<input type="password" id="rikodev_hcaptcha_privatekey" name="rikodev_hcaptcha_privatekey" value="' . htmlspecialchars($privkey, ENT_QUOTES, 'UTF-8') . '" style="width: 100%; max-width: 500px;"></p>';
    echo '<input type="submit" value="Save Changes"/>';
    echo '</form>';
    echo '<div style="margin-top: 20px; padding: 15px; background-color: rgba(0, 115, 170, 0.1); border-left: 4px solid #0073aa; border-radius: 4px; color: inherit;">';
    echo '<style>
        .hcaptcha-notes {
            color: inherit !important;
        }
        .hcaptcha-notes h3 {
            color: inherit !important;
            margin-top: 0;
        }
        .hcaptcha-notes ul,
        .hcaptcha-notes li {
            color: inherit !important;
        }
        .hcaptcha-notes code {
            background-color: rgba(0, 0, 0, 0.2);
            padding: 2px 6px;
            border-radius: 3px;
            color: #0073aa;
            font-family: monospace;
        }
        .hcaptcha-notes a {
            color: #0073aa;
            text-decoration: underline;
        }
        .hcaptcha-notes a:hover {
            color: #005a87;
        }
        /* Dark mode specific adjustments */
        @media (prefers-color-scheme: dark) {
            .hcaptcha-notes {
                background-color: rgba(0, 115, 170, 0.15) !important;
                color: #e0e0e0 !important;
            }
            .hcaptcha-notes code {
                background-color: rgba(255, 255, 255, 0.15);
                color: #4fc3f7;
            }
            .hcaptcha-notes a {
                color: #4fc3f7;
            }
            .hcaptcha-notes a:hover {
                color: #81d4fa;
            }
        }
        /* Sleeky dark theme detection */
        body.dark .hcaptcha-notes,
        body[class*="dark"] .hcaptcha-notes,
        .dark .hcaptcha-notes {
            background-color: rgba(0, 115, 170, 0.15) !important;
            color: #e0e0e0 !important;
        }
        body.dark .hcaptcha-notes code,
        body[class*="dark"] .hcaptcha-notes code,
        .dark .hcaptcha-notes code {
            background-color: rgba(255, 255, 255, 0.15) !important;
            color: #4fc3f7 !important;
        }
        body.dark .hcaptcha-notes a,
        body[class*="dark"] .hcaptcha-notes a,
        .dark .hcaptcha-notes a {
            color: #4fc3f7 !important;
        }
        body.dark .hcaptcha-notes a:hover,
        body[class*="dark"] .hcaptcha-notes a:hover,
        .dark .hcaptcha-notes a:hover {
            color: #81d4fa !important;
        }
    </style>';
    echo '<div class="hcaptcha-notes">';
    echo '<h3 style="margin-top: 0;">Important Notes:</h3>';
    echo '<ul style="margin-bottom: 0;">';
    echo '<li><strong>Domain Configuration:</strong> Make sure your hCaptcha site key is configured for the domain <code>' . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'yourls.newstargeted.com', ENT_QUOTES, 'UTF-8') . '</code> in your <a href="https://dashboard.hcaptcha.com/" target="_blank">hCaptcha dashboard</a>.</li>';
    echo '<li><strong>Rate Limiting:</strong> If you see "Rate limited or network error" messages, it may be due to:</li>';
    echo '<ul>';
    echo '<li>Too many requests from the same IP address</li>';
    echo '<li>Invalid or misconfigured site key</li>';
    echo '<li>Domain not properly registered with hCaptcha</li>';
    echo '<li>Temporary hCaptcha service issues</li>';
    echo '</ul>';
    echo '<li><strong>Troubleshooting:</strong> If issues persist, verify your site key and secret key in the <a href="https://dashboard.hcaptcha.com/" target="_blank">hCaptcha dashboard</a> and ensure your domain is listed in the allowed domains.</li>';
    echo '</ul>';
    echo '</div>';
    echo '</div>';
}

// Save hCaptcha keys in database
function admin_hcaptcha_save()
{
    // Sanitize input values
    $pubkey = isset($_POST['rikodev_hcaptcha_publickey']) ? trim($_POST['rikodev_hcaptcha_publickey']) : '';
    $privkey = isset($_POST['rikodev_hcaptcha_privatekey']) ? trim($_POST['rikodev_hcaptcha_privatekey']) : '';
    
    // Use YOURLS sanitization function if available, otherwise use basic sanitization
    if (function_exists('yourls_sanitize_string')) {
        $pubkey = yourls_sanitize_string($pubkey);
        $privkey = yourls_sanitize_string($privkey);
    } else {
        // Basic sanitization: strip tags and trim
        $pubkey = strip_tags($pubkey);
        $privkey = strip_tags($privkey);
    }

    // Save or update site key
    if (yourls_get_option('rikodev_hcaptcha_site') !== false) {
        $site_result = yourls_update_option('rikodev_hcaptcha_site', $pubkey);
    } else {
        $site_result = yourls_add_option('rikodev_hcaptcha_site', $pubkey);
    }

    // Save or update private key
    if (yourls_get_option('rikodev_hcaptcha_private') !== false) {
        $private_result = yourls_update_option('rikodev_hcaptcha_private', $privkey);
    } else {
        $private_result = yourls_add_option('rikodev_hcaptcha_private', $privkey);
    }

    // Provide feedback on save status and redirect to clean URL
    if ($site_result && $private_result) {
        // Redirect to clean URL to prevent form resubmission
        // Use same format as other plugins (like telegram-notifier)
        $redirect_url = yourls_admin_url('plugins.php?page=admin_hcaptcha&saved=1');
        // Remove .php extension for clean URL (only if present)
        if (strpos($redirect_url, '/plugins.php') !== false) {
            $redirect_url = str_replace('/plugins.php', '/plugins', $redirect_url);
        }
        // Ensure URL is valid and not empty
        if (empty($redirect_url) || !filter_var($redirect_url, FILTER_VALIDATE_URL)) {
            // Fallback: construct URL manually
            $base = yourls_get_yourls_site();
            $redirect_url = rtrim($base, '/') . '/admin/plugins?page=admin_hcaptcha&saved=1';
            if (yourls_is_ssl() || yourls_needs_ssl()) {
                $redirect_url = yourls_set_url_scheme($redirect_url, 'https');
            }
        }
        // Perform redirect - this will exit
        yourls_redirect($redirect_url, 302);
        exit;
    } else {
        echo '<div class="notice notice-error"><p><strong>Error saving hCaptcha settings. Please try again.</strong></p></div>';
    }
}

// Add the JavaScript for hCaptcha widget
yourls_add_action('html_head', 'rikodev_hcaptcha_loadjs');

function rikodev_hcaptcha_loadjs()
{
    $key = yourls_get_option('rikodev_hcaptcha_site', '');
    
    // Only load hCaptcha if site key is configured
    if (empty($key) || trim($key) == '') {
        return;
    }
    
    $sitekey = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
?>
    <script>
        (function() {
            // Suppress MetaMask warning (not related to hCaptcha)
            if (typeof console !== 'undefined' && console.warn) {
                var originalWarn = console.warn;
                console.warn = function() {
                    if (arguments[0] && typeof arguments[0] === 'string' && arguments[0].indexOf('MetaMask') !== -1) {
                        return; // Suppress MetaMask warnings
                    }
                    originalWarn.apply(console, arguments);
                };
            }
            
            // Track if hCaptcha is already initialized to prevent multiple renders
            var hcaptchaInitialized = false;
            
            function initHCaptcha() {
                // Prevent multiple initializations
                if (hcaptchaInitialized) {
                    return;
                }
                
                var logindiv = document.getElementById('login');
                if (!logindiv) {
                    return; // Not on login page
                }
                
                // Check if widget already exists and is rendered
                var existingWidget = logindiv.querySelector('.h-captcha');
                if (existingWidget && existingWidget.getAttribute('data-hcaptcha-widget-id')) {
                    hcaptchaInitialized = true;
                    return; // Widget already rendered
                }
                
                var sitekey = <?php echo json_encode($sitekey, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
                
                if (!sitekey || sitekey.trim() === '') {
                    return; // No site key configured
                }
                
                // Check if hCaptcha script is already loaded
                var existingScript = document.querySelector('script[src*="hcaptcha.com"]');
                if (existingScript) {
                    // Script already exists, just insert widget
                    if (typeof hcaptcha !== 'undefined') {
                        insertHCaptchaWidget(logindiv, sitekey);
                        // Render if needed
                        setTimeout(function() {
                            var captchaElement = logindiv.querySelector('.h-captcha');
                            if (captchaElement && typeof hcaptcha !== 'undefined' && hcaptcha.render && !captchaElement.getAttribute('data-hcaptcha-widget-id')) {
                                try {
                                    var widgetId = hcaptcha.render(captchaElement, {
                                        'sitekey': sitekey,
                                        'theme': 'light',
                                        'size': 'normal',
                                        'error-callback': function(error) {
                                            handleHCaptchaError(error, logindiv);
                                        },
                                        'expired-callback': function() {
                                            // Captcha expired, user needs to solve again
                                            showHCaptchaMessage(logindiv, 'hCaptcha challenge expired. Please solve it again.', 'warning');
                                        }
                                    });
                                    if (widgetId) {
                                        captchaElement.setAttribute('data-hcaptcha-widget-id', widgetId);
                                        hcaptchaInitialized = true;
                                    }
                                } catch (e) {
                                    console.error('hCaptcha render error:', e);
                                }
                            }
                        }, 200);
                    } else {
                        // Wait for script to load
                        existingScript.addEventListener('load', function() {
                            insertHCaptchaWidget(logindiv, sitekey);
                        });
                    }
                    return;
                }
                
                // Load hCaptcha API script with onload callback
                var script = document.createElement('script');
                script.src = 'https://js.hcaptcha.com/1/api.js?onload=onHCaptchaLoad&render=explicit';
                script.async = true;
                script.defer = true;
                script.onerror = function() {
                    console.error('hCaptcha: Failed to load API script');
                    // Show user-friendly error message
                    var errorDiv = document.createElement('div');
                    errorDiv.style.cssText = 'color: #ff0000; padding: 10px; margin: 10px 0; border: 1px solid #ff0000; background-color: #ffe6e6; border-radius: 4px;';
                    errorDiv.textContent = 'Failed to load hCaptcha security check. Please refresh the page and try again.';
                    var form = logindiv.querySelector('form');
                    if (form) {
                        form.insertBefore(errorDiv, form.firstChild);
                    } else {
                        logindiv.insertBefore(errorDiv, logindiv.firstChild);
                    }
                };
                
                // Global callback for when hCaptcha loads
                window.onHCaptchaLoad = function() {
                    if (hcaptchaInitialized) {
                        return; // Already initialized
                    }
                    // Insert widget first, then render
                    insertHCaptchaWidget(logindiv, sitekey);
                    // Small delay to ensure hCaptcha is fully initialized, then render
                    setTimeout(function() {
                        var captchaElement = logindiv.querySelector('.h-captcha');
                        if (captchaElement && typeof hcaptcha !== 'undefined' && hcaptcha.render && !captchaElement.getAttribute('data-hcaptcha-widget-id')) {
                            try {
                                var widgetId = hcaptcha.render(captchaElement, {
                                    'sitekey': sitekey,
                                    'theme': 'light',
                                    'size': 'normal',
                                    'error-callback': function(error) {
                                        handleHCaptchaError(error, logindiv);
                                    },
                                    'expired-callback': function() {
                                        // Captcha expired, user needs to solve again
                                        showHCaptchaMessage(logindiv, 'hCaptcha challenge expired. Please solve it again.', 'warning');
                                    }
                                });
                                if (widgetId) {
                                    captchaElement.setAttribute('data-hcaptcha-widget-id', widgetId);
                                    hcaptchaInitialized = true;
                                }
                            } catch (e) {
                                console.error('hCaptcha render error:', e);
                            }
                        }
                    }, 200);
                };
                
                document.head.appendChild(script);
            }
            
            function insertHCaptchaWidget(logindiv, sitekey) {
                try {
                    // Check if widget already exists
                    if (logindiv.querySelector('.h-captcha')) {
                        return; // Widget already inserted
                    }
                    
                    // Find the form element
                    var form = logindiv.querySelector('form');
                    if (!form) {
                        console.error('hCaptcha: Login form not found');
                        return;
                    }
                    
                    // Find the submit button or password field to insert before
                    var submitButton = form.querySelector('input[type="submit"], button[type="submit"]');
                    var passwordField = form.querySelector('input[type="password"]');
                    
                    // Create hCaptcha container
                    var captchaDiv = document.createElement('div');
                    captchaDiv.className = 'h-captcha';
                    captchaDiv.setAttribute('data-sitekey', sitekey);
                    captchaDiv.setAttribute('data-theme', 'light');
                    captchaDiv.setAttribute('data-size', 'normal');
                    captchaDiv.style.cssText = 'margin: 15px 0;';
                    
                    // Insert before submit button, or after password field, or at end of form
                    if (submitButton && submitButton.parentNode) {
                        submitButton.parentNode.insertBefore(captchaDiv, submitButton);
                    } else if (passwordField && passwordField.parentNode) {
                        // Insert after password field's parent container
                        var passwordContainer = passwordField.closest('p, div, label') || passwordField.parentNode;
                        if (passwordContainer.nextSibling) {
                            passwordContainer.parentNode.insertBefore(captchaDiv, passwordContainer.nextSibling);
                        } else {
                            passwordContainer.parentNode.appendChild(captchaDiv);
                        }
                    } else {
                        // Last resort: append to form
                        form.appendChild(captchaDiv);
                    }
                    
                    // Widget will be rendered by hcaptcha.render() call
                } catch (error) {
                    console.error('Error inserting hCaptcha widget:', error);
                }
            }
            
            function handleHCaptchaError(error, logindiv, widgetId) {
                console.error('hCaptcha error:', error);
                
                // Check if it's a rate limit or network error
                var errorMessage = 'hCaptcha error occurred. ';
                if (error && typeof error === 'string') {
                    if (error.indexOf('rate') !== -1 || error.indexOf('limit') !== -1) {
                        errorMessage = 'Rate limited or network error. Please wait a moment and try again.';
                    } else if (error.indexOf('network') !== -1 || error.indexOf('400') !== -1 || error.indexOf('Bad Request') !== -1) {
                        errorMessage = 'Network error or rate limit. Please wait a few moments and refresh the page.';
                    } else {
                        errorMessage += error;
                    }
                } else {
                    errorMessage = 'Rate limited or network error. Please wait a moment and try again.';
                }
                
                showHCaptchaMessage(logindiv, errorMessage, 'error');
                
                // Try to reset the widget after a delay
                setTimeout(function() {
                    var captchaElement = logindiv.querySelector('.h-captcha');
                    if (captchaElement && typeof hcaptcha !== 'undefined' && hcaptcha.reset) {
                        try {
                            if (widgetId) {
                                hcaptcha.reset(widgetId);
                            } else {
                                var storedWidgetId = captchaElement.getAttribute('data-hcaptcha-widget-id');
                                if (storedWidgetId) {
                                    hcaptcha.reset(storedWidgetId);
                                } else {
                                    // If no widget ID, try to reset by element
                                    hcaptcha.reset(captchaElement);
                                }
                            }
                        } catch (resetError) {
                            console.error('Failed to reset hCaptcha:', resetError);
                        }
                    }
                }, 5000);
            }
            
            function checkHCaptchaStatus(captchaElement, logindiv, widgetId) {
                // Check if the captcha iframe is loaded and visible
                var iframe = captchaElement.querySelector('iframe');
                if (!iframe || iframe.style.display === 'none' || iframe.offsetHeight === 0) {
                    // Captcha might not have loaded properly
                    // Check console for errors (we can't directly access iframe errors, but we can check visibility)
                    var hasError = logindiv.querySelector('.hcaptcha-message.error');
                    if (!hasError) {
                        // Show a helpful message
                        showHCaptchaMessage(logindiv, 'hCaptcha is taking longer than usual to load. If this persists, please refresh the page.', 'warning');
                    }
                }
            }
            
            function showHCaptchaMessage(logindiv, message, type) {
                // Remove existing messages
                var existingMsg = logindiv.querySelector('.hcaptcha-message');
                if (existingMsg) {
                    existingMsg.remove();
                }
                
                var messageDiv = document.createElement('div');
                messageDiv.className = 'hcaptcha-message';
                var bgColor = type === 'error' ? '#ffe6e6' : '#fff3cd';
                var borderColor = type === 'error' ? '#ff0000' : '#ffc107';
                var textColor = type === 'error' ? '#ff0000' : '#856404';
                messageDiv.style.cssText = 'color: ' + textColor + '; padding: 10px; margin: 10px 0; border: 1px solid ' + borderColor + '; background-color: ' + bgColor + '; border-radius: 4px;';
                messageDiv.textContent = message;
                
                var form = logindiv.querySelector('form');
                var captchaElement = logindiv.querySelector('.h-captcha');
                if (captchaElement && captchaElement.parentNode) {
                    captchaElement.parentNode.insertBefore(messageDiv, captchaElement);
                } else if (form) {
                    form.insertBefore(messageDiv, form.firstChild);
                } else {
                    logindiv.insertBefore(messageDiv, logindiv.firstChild);
                }
                
                // Auto-hide after 10 seconds for warnings
                if (type === 'warning') {
                    setTimeout(function() {
                        if (messageDiv.parentNode) {
                            messageDiv.remove();
                        }
                    }, 10000);
                }
            }
            
            // Initialize when DOM is ready - use single initialization to prevent multiple renders
            function runInit() {
                if (!hcaptchaInitialized) {
                    initHCaptcha();
                }
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', runInit);
            } else {
                // DOM already loaded, run immediately
                runInit();
            }
            
            // Fallback initialization after a short delay (only if not already initialized)
            setTimeout(function() {
                if (!hcaptchaInitialized) {
                    runInit();
                }
            }, 500);
        })();
    </script>
<?php
}
?>