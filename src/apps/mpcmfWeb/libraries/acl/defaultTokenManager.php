<?php

namespace mpcmf\apps\mpcmfWeb\libraries\acl;

use mpcmf\modules\authex\mappers\tokenMapper;
use mpcmf\modules\authex\models\tokenModel;
use mpcmf\modules\moduleBase\exceptions\mapperException;
use mpcmf\modules\moduleBase\exceptions\modelException;
use mpcmf\system\configuration\config;
use mpcmf\system\configuration\environment;
use mpcmf\system\helper\io\log;
use mpcmf\system\helper\io\response;
use mpcmf\system\token\exception\tokenManagerException;
use mpcmf\system\token\tokenManager;
use mpcmf\system\token\tokenManagerInterface;

/**
 * Default token manager class
 *
 * A token is trusted only if it matches, byte for byte, the string stored in the `token`
 * collection: the cipher key is configurable but public by default, so the decrypted payload
 * alone proves nothing. Expiration is read from the stored record (0 = never expires).
 *
 * @author Gregory Ostrovsky <greevex@gmail.com>
 */
class defaultTokenManager
    implements tokenManagerInterface
{
    use response, log;

    const CODE_FORBIDDEN = 403;

    const VALIDATION_EXPIRE = 86400;
    const SOME_PASSWORD = 'You_really_must_write_your_own_token_manager!';
    const ENCRYPT_METHOD = 'aes128';

    /** @var string */
    private $secret;

    public function __construct()
    {
        $config = config::getConfig(tokenManager::class);
        $this->secret = empty($config['secret']) ? self::SOME_PASSWORD : (string)$config['secret'];
    }

    public function validateToken($tokenString, $checkLimits = true)
    {
        if (empty($tokenString)) {
            return self::error([
                'errors' => ['access_token required']
            ], self::CODE_FORBIDDEN);
        }

        $tokenData = $this->decode($tokenString);

        if (!is_array($tokenData)) {
            return self::error([
                'errors' => ['Invalid access_token given']
            ], self::CODE_FORBIDDEN);
        }

        $tokenModel = $this->loadStoredToken($tokenData, $tokenString);
        if ($tokenModel === null) {
            return self::error([
                'errors' => ['Invalid access_token given']
            ], self::CODE_FORBIDDEN);
        }

        $expire = (int)$tokenModel->getExpire();
        if ($expire > 0 && $expire < time()) {
            return self::error([
                'errors' => ['access_token expired']
            ], self::CODE_FORBIDDEN);
        }
        $tokenData['e'] = $expire;

        return self::success($tokenData);
    }

    /**
     * Stored token record matching the given string, or null if there is none
     *
     * @param array  $tokenData   Decoded payload
     * @param string $tokenString Token as received from the client
     *
     * @return tokenModel|null
     */
    protected function loadStoredToken(array $tokenData, $tokenString)
    {
        $tokenId = isset($tokenData['i']) ? $tokenData['i'] : null;
        // `i` goes straight into a Mongo query: anything but a plain id must be rejected here
        if (!is_string($tokenId) || !preg_match('/^[0-9a-f]{24}$/', $tokenId)) {
            return null;
        }

        try {
            /** @var tokenModel $tokenModel */
            $tokenModel = tokenMapper::getInstance()->getById($tokenId);
        } catch (mapperException $mapperException) {
            return null;
        } catch (modelException $modelException) {
            return null;
        }

        if (!hash_equals((string)$tokenModel->getToken(), (string)$tokenString)) {
            return null;
        }

        return $tokenModel;
    }

    /**
     * @param tokenModel $tokenModel
     *
     * @return string
     * @throws tokenManagerException
     */
    public function generateToken($tokenModel)
    {
        if ($this->secret === self::SOME_PASSWORD && environment::getCurrentEnvironment() === environment::ENV_PRODUCTION) {
            self::log()->addAlert('Token cipher key is the framework default: set `secret` in ' . tokenManager::class . ' config');
        }

        $tokenString = $this->encode($tokenModel->minify());
        $tokenModel->setToken($tokenString);
        try {
            $tokenModel->getMapper()->save($tokenModel);
        } catch(\Exception $e) {

            throw new tokenManagerException('Unable to generate token, because sub exception was caught', 0, $e);
        }

        return $tokenString;
    }

    public function decode($tokenString)
    {
        if(strpos($tokenString, '.') === false) {

            return null;
        }

        list($ivHex, $token) = explode('.', $tokenString);

        if(empty($token) || strlen($ivHex) !== 32) {

            return null;
        }

        try {
            $tokenData = openssl_decrypt($token, self::ENCRYPT_METHOD, $this->secret, 0, hex2bin($ivHex));
        } catch(\Exception $opensslException) {

            return null;
        }

        return json_decode($tokenData, true);
    }

    public function encode($something)
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::ENCRYPT_METHOD));

        return bin2hex($iv) . '.' . openssl_encrypt(json_encode($something), self::ENCRYPT_METHOD, $this->secret, 0, $iv);
    }
}
