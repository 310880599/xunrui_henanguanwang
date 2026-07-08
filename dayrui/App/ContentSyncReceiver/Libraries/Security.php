<?php namespace Phpcmf\Library\ContentSyncReceiver;

class Security
{
    /**
     * 获取请求头中的 API KEY
     */
    public function get_request_api_key() {
        $key = '';
        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            $key = $_SERVER['HTTP_X_API_KEY'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_X_API_KEY'])) {
            $key = $_SERVER['REDIRECT_HTTP_X_API_KEY'];
        }
        return trim((string)$key);
    }

    /**
     * 校验 API KEY
     */
    public function is_valid_api_key($requestKey, $configKey) {
        $requestKey = trim((string)$requestKey);
        $configKey = trim((string)$configKey);
        return $configKey && hash_equals($configKey, $requestKey);
    }
}
