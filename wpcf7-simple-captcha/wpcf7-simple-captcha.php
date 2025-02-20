<?php
/**
* Plugin Name:  Simple CAPTCHA for Contact Form 7
* Description:  An addon for CF7 that enables a non-js and non-data based CAPTCHA solution, by using a "nonce" and a hidden field.
* Version:      1.0.0
* Author:       Daniel McAssey
* Author URI:   https://glokon.me
* License:      GPLv2 or later
* License URI:  https://www.gnu.org/licenses/gpl-2.0.html
* Contributors: dmcassey
* Requires at least: 5.1
*
* Text Domain: wpcf7-simple-captcha
* Domain Path: /lang
*
* WordPress Available:  yes
* Requires License:    no
*
* @package Simple CAPTCHA for Contact Form 7
* @category Contact Form 7 Add-on
* @author Daniel McAssey
*/

define('WPCF7_SIMPLE_CAPTCHA_PLUGIN_VERSION', '1.0.0');
define('WPCF7_SIMPLE_CAPTCHA_PATH', plugin_dir_path(__FILE__));
define('WPCF7_BASENAME', basename(WPCF7_SIMPLE_CAPTCHA_PATH));

require_once 'service.php';

add_action( 'wpcf7_init', 'wpcf7_simple_captcha_register_service', 40, 0 );

/**
 * Registers the Simple CAPTCHA service.
 */
function wpcf7_simple_captcha_register_service() {
    $integration = WPCF7_Integration::get_instance();

    $integration->add_service('simple-captcha',
        WPCF7_SIMPLE_CAPTCHA::get_instance()
    );
}


add_filter('wpcf7_form_elements', 'wpcf7_simple_captcha_add_form_elements', 100, 1);

/**
 * Adds human hidden, but bot visible form fields for Simple CAPTCHA.
 */
function wpcf7_simple_captcha_add_form_elements($elements) {
    $service = WPCF7_SIMPLE_CAPTCHA::get_instance();

    if (!$service->is_active()) {
        return $elements;
    }

    return ($elements . $service->generate_human_hidden_fields());
}


add_filter('wpcf7_form_hidden_fields', 'wpcf7_simple_captcha_add_hidden_fields', 100, 1);

/**
 * Adds hidden form field for Simple CAPTCHA.
 */
function wpcf7_simple_captcha_add_hidden_fields($fields) {
    $service = WPCF7_SIMPLE_CAPTCHA::get_instance();

    if (!$service->is_active()) {
        return $fields;
    }

    if (in_the_loop()) {
        return array_merge($fields, $service->generate_hidden_fields(intval(get_the_ID())));
    } else {
        return array_merge($fields, $service->generate_hidden_fields());
    }
}


add_filter('wpcf7_spam', 'wpcf7_simple_captcha_verify_response', 9, 2);

/**
 * Verifies Simple CAPTCHA submission on the server side.
 */
function wpcf7_simple_captcha_verify_response($spam, $submission) {
    if ($spam) {
        return $spam;
    }

    $service = WPCF7_SIMPLE_CAPTCHA::get_instance();

    if (!$service->is_active() ) {
        return $spam;
    }

    $nonceField = $service->get_nonce_field();
    $captchaFields = $service->get_captcha_fields();

    $formId = intval(trim($_POST['_wpcf7_container_post'] ?? ''));
    $nonce = trim($_POST[$nonceField] ?? '');
    $captcha = '';
    foreach ($captchaFields as $captchaField) {
        $captcha .= trim($_POST[$captchaField] ?? '');
    }

    if ($service->verify($nonce, $captcha, $formId)) {
        $spam = false; // Human
    } else {
        $spam = true; // Bot

        if (empty($nonce)) {
            $submission->add_spam_log([
                'agent' => 'simple-captcha',
                'reason' => __(
                    'Simple CAPTCHA response nonce is empty.',
                    'contact-form-7'
                ),
            ]);
        } else if (!empty($captcha)) {
            $submission->add_spam_log([
                'agent' => 'simple-captcha',
                'reason' => __(
                    'Simple CAPTCHA response CAPTCHA fields have been set.',
                    'contact-form-7'
                ),
            ]);
        } else {
            $submission->add_spam_log([
                'agent' => 'simple-captcha',
                'reason' => __(
                    'Simple CAPTCHA response nonce could not be verified.',
                    'contact-form-7'
                ),
            ]);
        }
    }

    return $spam;
}


add_action('wpcf7_init', 'wpcf7_simple_captcha_add_form_tag_simple_captcha', 10, 0);

/**
 * Registers form-tag types for Simple CAPTCHA.
 */
function wpcf7_simple_captcha_add_form_tag_simple_captcha() {
    $service = WPCF7_SIMPLE_CAPTCHA::get_instance();

    if (!$service->is_active() ) {
        return;
    }

    wpcf7_add_form_tag('simple-captcha',
        '__return_empty_string', // no output
        ['display-block' => true]
    );
}

