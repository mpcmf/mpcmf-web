<?php
/**
 * @author greevex
 * @date   : 11/16/12 5:11 PM
 */

// Slim 2 still names mcrypt constants in its defaults; mcrypt is gone since PHP 7.2 and the
// constants are undefined (a warning on 7.x, a fatal error on 8). Their real values are kept here.
// Slim itself does not encrypt without mcrypt: the session cookie is encrypted by aclManager.
defined('MCRYPT_RIJNDAEL_256') || define('MCRYPT_RIJNDAEL_256', 'rijndael-256');
defined('MCRYPT_MODE_CBC') || define('MCRYPT_MODE_CBC', 'cbc');

\mpcmf\system\configuration\config::setConfig(__FILE__, [
    'name' => 'Web application base',
    'slim' => [
        // Debugging
        'debug' => true,
        // Logging
        'log.enabled' => true,
        // View
        'templates.path' => null,
        'view' => '\\mpcmf\\system\\view\\smartyDriver',
        // Cookies
        'cookies.encrypt' => true,
        'cookies.lifetime' => '60 minutes',
        'cookies.path' => '/',
        'cookies.domain' => null,
        'cookies.secure' => false,
        'cookies.httponly' => true,
        // Encryption: override the secret in the application config,
        // with the default one the production log gets an alert
        'cookies.secret_key' => 'CHANGE_THIS_SECRET_KEY',
        'cookies.cipher' => MCRYPT_RIJNDAEL_256,
        'cookies.cipher_mode' => MCRYPT_MODE_CBC,
        // HTTP
        'http.version' => '1.1',
    ]
]);