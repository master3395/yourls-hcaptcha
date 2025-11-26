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
    
    // Keys are configured, enforce validation
    include('hcaptcha.php');

    if ($resp === true) {
        return true;
    } else {
        yourls_do_action('login_failed');
        yourls_login_screen($error_msg = 'hCaptcha validation failed. Please complete the hCaptcha challenge and try again.');
        die();
        return false;
    }
}

// Register plugin on admin page
yourls_add_action('plugins_loaded', 'rikodev_hcaptcha_load');

function rikodev_hcaptcha_load()
{
    yourls_register_plugin_page('admin_hcaptcha', 'Admin hCaptcha Settings', 'admin_hcaptcha_config_page');
}

// The function that will draw the config page
function admin_hcaptcha_config_page()
{
    if (isset($_POST['rikodev_hcaptcha_publickey'])) {
        yourls_verify_nonce('admin_hcaptcha_config_nonce');
        admin_hcaptcha_save();
    }

    $nonce = yourls_create_nonce('admin_hcaptcha_config_nonce');
    $pubkey = yourls_get_option('rikodev_hcaptcha_site', "");
    $privkey = yourls_get_option('rikodev_hcaptcha_private', "");

    echo '<h2>Admin hCaptcha Settings</h2>';
    echo '<form method="post">';
    echo '<input type="hidden" name="nonce" value="' . $nonce . '" />';
    echo '<p><label for="rikodev_hcaptcha_publickey">hCaptcha site key:</label>';
    echo '<input type="text" id="rikodev_hcaptcha_publickey" name="rikodev_hcaptcha_publickey" value="' . htmlspecialchars($pubkey, ENT_QUOTES, 'UTF-8') . '" style="width: 100%; max-width: 500px;"></p>';
    echo '<p><label for="rikodev_hcaptcha_privatekey">hCaptcha secret key:</label>';
    echo '<input type="password" id="rikodev_hcaptcha_privatekey" name="rikodev_hcaptcha_privatekey" value="' . htmlspecialchars($privkey, ENT_QUOTES, 'UTF-8') . '" style="width: 100%; max-width: 500px;"></p>';
    echo '<input type="submit" value="Save Changes"/>';
    echo '</form>';
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

    // Provide feedback on save status
    if ($site_result && $private_result) {
        echo '<div class="notice notice-success"><p><strong>hCaptcha settings saved successfully!</strong></p></div>';
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
?>
    <script>
        $(document).ready(function() {
            var logindiv = document.getElementById('login');
            if (logindiv) { //check if we are on login screen
                var sitekey = <?php echo json_encode($key, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                
                if (sitekey) {
                    $.getScript("https://www.hCaptcha.com/1/api.js");
                    var form = logindiv.innerHTML;
                    var index = form.indexOf('<p style="text-align: right;">'); //finding tag before which hCaptcha widget should appear
                    if (index !== -1) {
                        document.getElementById('login').innerHTML = form.slice(0, index) + '<div class="h-captcha" data-sitekey="' + sitekey + '"></div>' + form.slice(index);
                    }
                }
            }
        });
    </script>
<?php
}
?>