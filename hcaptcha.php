<?php

$secret = yourls_get_option( 'rikodev_hcaptcha_private' );

// The response from reCAPTCHA
$resp = NULL;

if ($secret == "") {

    die("To use hCaptcha you must get an API key.");

} elseif (isset($_POST['h-captcha-response']) && !empty($_POST['h-captcha-response'])) {

    $verify = file_get_contents('https://hcaptcha.com/siteverify?secret='.$secret.'&response='.$_POST['g-recaptcha-response'].'&remoteip='.$_SERVER['REMOTE_ADDR']);
    $data = json_decode($verify);

    if ($data->success) $resp = true;

}

?>