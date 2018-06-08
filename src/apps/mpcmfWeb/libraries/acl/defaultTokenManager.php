<?php

namespace mpcmf\apps\mpcmfWeb\libraries\acl;

use mpcmf\modules\authex\models\tokenModel;
use mpcmf\system\cache\cache;
use mpcmf\system\helper\io\response;
use mpcmf\system\token\exception\tokenManagerException;
use mpcmf\system\token\tokenManagerInterface;

/**
 * Default token manager class
 *
 * @author Gregory Ostrovsky <greevex@gmail.com>
 */
class defaultTokenManager
    implements tokenManagerInterface
{
    use response;

    const CODE_FORBIDDEN = 403;

    const VALIDATION_EXPIRE = 86400;
    const SOME_PASSWORD = 'You_really_must_write_your_own_token_manager!';
    const ENCRYPT_METHOD = 'aes128';

    public function validateToken($tokenString, $checkLimits = true)
    {
        if(empty($tokenString)) {
            return self::error([
                'errors' => [
                    'access_token required'
                ]
            ], self::CODE_FORBIDDEN);
        }

        $tokenData = null;

        if(($tokenResult = cache::getCached("token/{$tokenString}")) === false) {
            $tokenData = $this->decode($tokenString);
            $tokenResult = is_array($tokenData);
            cache::setCached("token/{$tokenString}", (int)$tokenResult, self::VALIDATION_EXPIRE);
        }

        if($tokenResult === false) {
            return self::error([
                'errors' => [
                    'Invalid access_token given'
                ]
            ], self::CODE_FORBIDDEN);
        }

        if($tokenData === null) {
            $tokenData = $this->decode($tokenString);
        }

        return self::success($tokenData);
    }

    /**
     * @param tokenModel $tokenModel
     *
     * @return string
     * @throws tokenManagerException
     */
    public function generateToken($tokenModel)
    {
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
            $tokenData = openssl_decrypt($token, self::ENCRYPT_METHOD, self::SOME_PASSWORD, 0, hex2bin($ivHex));
        } catch(\Exception $opensslException) {

            return null;
        }

        return json_decode($tokenData, true);
    }

    public function encode($something)
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::ENCRYPT_METHOD));

        return bin2hex($iv) . '.' . openssl_encrypt(json_encode($something), self::ENCRYPT_METHOD, self::SOME_PASSWORD, 0, $iv);
    }
}
