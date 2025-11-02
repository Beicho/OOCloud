<?php
namespace lib;
/**
 * Linux Do OAuth2 登录集成
 */

class LinuxDoOauth {
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $authUrl = 'https://connect.linux.do/oauth2/authorize';
    private $tokenUrl = 'https://connect.linux.do/oauth2/token';
    private $userInfoUrl = 'https://connect.linux.do/api/user';

    function __construct($clientId, $clientSecret, $redirectUri) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
    }

    /**
     * 生成授权登录URL
     */
    public function getAuthUrl() {
        $state = md5(uniqid(rand(), TRUE));
        $_SESSION['LinuxDo_state'] = $state;

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'user',
            'state' => $state
        ];

        return $this->authUrl . '?' . http_build_query($params);
    }

    /**
     * 使用授权码获取访问令牌
     */
    private function getAccessToken($code) {
        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code'
        ];

        // 使用 curl 发送 POST 请求
        $ch = curl_init($this->tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if (!isset($result['access_token'])) {
            // 添加调试信息
            return [
                'error' => '获取访问令牌失败',
                'details' => $result,
                'http_code' => $httpCode,
                'redirect_uri_used' => $this->redirectUri,
                'response_raw' => $response
            ];
        }

        return $result;
    }

    /**
     * 使用访问令牌获取用户信息
     */
    private function getUserInfo($accessToken) {
        $ch = curl_init($this->userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    /**
     * 处理OAuth2回调，返回用户信息
     */
    public function callback($code) {
        // 获取访问令牌
        $tokenData = $this->getAccessToken($code);

        if (isset($tokenData['error'])) {
            return $tokenData;
        }

        // 获取用户信息
        $userInfo = $this->getUserInfo($tokenData['access_token']);

        if (!isset($userInfo['id'])) {
            return ['error' => '获取用户信息失败', 'details' => $userInfo];
        }

        // 处理头像URL
        $avatar = '';
        if (isset($userInfo['avatar_template'])) {
            // avatar_template 格式: /user_avatar/connect.linux.do/{username}/{size}/xxx.png
            // 替换 {size} 为实际尺寸，例如 120
            $avatar = 'https://connect.linux.do' . str_replace('{size}', '120', $userInfo['avatar_template']);
        }

        // 返回标准化的用户信息
        return [
            'code' => 0,
            'social_uid' => $userInfo['id'],
            'nickname' => isset($userInfo['name']) ? $userInfo['name'] : (isset($userInfo['username']) ? $userInfo['username'] : 'Linux Do用户'),
            'faceimg' => $avatar,
            'username' => isset($userInfo['username']) ? $userInfo['username'] : '',
            'trust_level' => isset($userInfo['trust_level']) ? $userInfo['trust_level'] : 0
        ];
    }
}
