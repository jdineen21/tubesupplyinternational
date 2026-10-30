<?php
// Securimage settings for the contact form captcha.
// These match the site's look with the old Securimage 2 (see the old
// securimage_show.php): small white 180x36 image, no lines, no noise.

// same session cookie settings as common/settings.php, for when the captcha
// is what starts the session (on the contact page the site has started it)
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
        ini_set('session.cookie_secure', '1');
    }
}

return array(
    'image_width'         => 180,
    'image_height'        => 36,
    'code_length'         => 6,
    'charset'             => 'ABCDEFGHKLMNPRSTUVWYZabcdefghklmnprstuvwyz23456789',
    'image_bg_color'      => '#ffffff',
    'text_color'          => '#3d3d3d',
    'num_lines'           => 0,
    'noise_level'         => 0,
    'perturbation'        => 0.9,
    'use_text_angles'     => false,
    'use_random_spaces'   => false,
    'use_random_baseline' => false,
    'expiry_time'         => 900,
);
