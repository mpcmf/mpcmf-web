<?php
/**
 * @author greevex
 * @date   : 11/16/12 5:11 PM
 */

\mpcmf\system\configuration\config::setConfig(__FILE__, [
    'token_class' => \mpcmf\apps\mpcmfWeb\libraries\acl\defaultTokenManager::class,
    // Cipher key used by defaultTokenManager. Override it in the application config:
    // with the default key the production log gets an alert on every generated token.
    'secret' => \mpcmf\apps\mpcmfWeb\libraries\acl\defaultTokenManager::SOME_PASSWORD,
]);
