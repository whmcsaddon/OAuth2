<?php

namespace tinymeng\OAuth2\Gateways;

use tinymeng\OAuth2\Connector\Gateway;
use tinymeng\OAuth2\Helper\ConstCode;
use tinymeng\OAuth2\Helper\Str;

class Twitter extends Gateway
{
    const API_BASE = 'https://api.twitter.com/';

    private $tokenSecret = '';

    /**
     * 得到跳转地址
     */
    public function getRedirectUrl()
    {
        //存储state
        $this->saveState();
        //登录参数
        $oauthToken = $this->call('oauth/request_token', ['oauth_callback' => $this->config['callback']], 'POST');
        return self::API_BASE . 'oauth/authenticate?oauth_token=' . $oauthToken['oauth_token'];
    }

    /**
     * 获取当前授权用户的openid标识
     */
    public function openid()
    {
        $data = $this->getUserInfo();
        return $data['id_str'];
    }

    /**
     * 获取格式化后的用户信息
     */
    public function userInfo()
    {
        $data = $this->getUserInfo();

        $return = [
            'openid'  => $data['id_str'],
            'channel' => ConstCode::TYPE_TWITTER,
            'nick'    => $data['name'],
            'gender'  => 'n', //twitter不返回用户性别
            'avatar'  => $data['profile_image_url_https'],
        ];
        return $return;
    }

    /**
     * 获取原始接口返回的用户信息
     */
    public function getUserInfo()
    {
        if (!$this->token) {
            $this->token = $this->getAccessToken();
            if (isset($this->token['oauth_token_secret'])) {
                $this->tokenSecret = $this->token['oauth_token_secret'];
            } else {
                throw new \Exception("获取Twitter ACCESS_TOKEN 出错：" . json_encode($this->token));
            }
        }

        return $this->call('1.1/account/verify_credentials.json', $this->token, 'GET', true);
    }

    /**
     * 发起请求
     *
     * @param string $api
     * @param array $params
     * @param string $method
     * @return array
     */
    private function call($api, $params = [], $method = 'GET', $isJson = false)
    {
        $method  = strtoupper($method);
        $request = [
            'method' => $method,
            'uri'    => self::API_BASE . $api,
        ];
        $oauthParams                    = $this->getOAuthParams($params);
        $oauthParams['oauth_signature'] = $this->signature($request, $oauthParams);

        $headers = ['Authorization' => $this->getAuthorizationHeader($oauthParams)];

        $data = $this->$method($request['uri'], $params, $headers);
        if ($isJson) {
            return json_decode($data, true);
        }
        parse_str($data, $data);
        return $data;
    }

    /**
     * 获取oauth参数
     *
     * @param array $params
     * @return array
     */
    private function getOAuthParams($params = [])
    {
        $_default = [
            'oauth_consumer_key'     => $this->config['app_id'],
            'oauth_nonce'            => Str::random(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => $this->timestamp,
            'oauth_token'            => '',
            'oauth_version'          => '1.0',
        ];
        return array_merge($_default, $params);
    }

    /**
     * 签名操作
     *
     * @param array $request
     * @param array $params
     * @return string
     */
    private function signature($request, $params = [])
    {
        ksort($params);
        $sign_str = Str::buildParams($params, true);
        $sign_str = $request['method'] . '&' . rawurlencode($request['uri']) . '&' . rawurlencode($sign_str);
        $sign_key = $this->config['app_secret'] . '&' . $this->tokenSecret;

        return rawurlencode(base64_encode(hash_hmac('sha1', $sign_str, $sign_key, true)));
    }

    /**
     * 获取请求附带的Header头信息
     *
     * @param array $params
     * @return string
     */
    private function getAuthorizationHeader($params)
    {
        $return = 'OAuth ';
        foreach ($params as $k => $param) {
            $return .= $k . '="' . $param . '", ';
        }
        return rtrim($return, ', ');
    }


    /**
     * Description:  getAccessToken
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     * @return array
     */
    protected function getAccessToken()
    {
        return $this->call('oauth/access_token', $_GET, 'POST');
    }
}
