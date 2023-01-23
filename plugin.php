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
    include('hcaptcha.php');

    if ($resp) {
        return true;
    } else {
        yourls_do_action('login_failed');
        yourls_login_screen($error_msg = 'hCaptcha validation failed');
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

    echo '<h2>Admin reCaptcha plugin settings</h2>';
    echo '<form method="post">';
    echo '<input type="hidden" name="nonce" value="' . $nonce . '" />';
    echo '<p><label for="rikodev_hcaptcha_publickey">hCaptcha site key:</label>';
    echo '<input type="text" id="rikodev_hcaptcha_publickey" name="rikodev_hcaptcha_publickey" value="' . $pubkey . '"></p>';
    echo '<p><label for="rikodev_hcaptcha_privatekey">hCaptcha secret key:</label>';
    echo '<input type="text" id="rikodev_hcaptcha_privatekey" name="rikodev_hcaptcha_privatekey" value="' . $privkey . '"></p>';
    echo '<input type="submit" value="Save Changes"/>';
    echo '</form>';
}

// Save reCaptcha keys in database
function admin_hcaptcha_save()
{
    $pubkey = $_POST['rikodev_hcaptcha_publickey'];
    $privkey = $_POST['rikodev_hcaptcha_privatekey'];

    if (yourls_get_option('rikodev_hcaptcha_site') !== false) {
        yourls_update_option('rikodev_hcaptcha_site', $pubkey);
    } else {
        yourls_add_option('rikodev_hcaptcha_site', $pubkey);
    }

    if (yourls_get_option('rikodev_hcaptcha_private') !== false) {
        yourls_update_option('rikodev_hcaptcha_private', $privkey);
    } else {
        yourls_add_option('rikodev_hcaptcha_private', $privkey);
    }

    echo "Saved";
}

// Add the JavaScript for reCaptcha widget
yourls_add_action('html_head', 'rikodev_hcaptcha_loadjs');

function rikodev_hcaptcha_loadjs()
{
    $key = yourls_get_option('rikodev_hcaptcha_site');
?>
    <script>
        $(document).ready(function() {
            var logindiv = document.getElementById('login');
            if (logindiv) { //check if we are on login screen

                $.getScript("https://www.hCaptcha.com/1/api.js");
                var form = logindiv.innerHTML;
                var index = form.indexOf('<p style="text-align: right;">'); //finding tag before which reCaptcha widget should appear
                document.getElementById('login').innerHTML = form.slice(0, index) + '<div class="h-captcha" data-sitekey="<?php echo $key ?>"></div>' + form.slice(index);

            }
        });
    </script>
<?php
}
?>