<?php
/**
 * Author: liasica
 * Email: magicrolan@qq.com
 * CreateTime: 2016/10/12 下午9:18
 */
namespace liasica\qywechat;

use liasica\qywechat\base\BaseQyWechat;
use Yii;
use yii\web\HttpException;

class Qywechat extends BaseQyWechat
{
    public $corpid;
    public $secret;
    public $cachePrefix = 'cache_prefix_liasica_qywechat';

    public function __construct($config = [])
    {
        if (!empty($config)) {
            $this->corpid = $config['corpid'];
            $this->secret = $config['secret'];
        }
    }

    /**
     * 解析微信请求响应内容
     * @param callable          $callable    Http请求主体函数
     * @param string            $url         Api地址
     * @param array|string|null $postOptions Api地址一般所需要的post参数
     * @param bool              $force       强制更新access_token
     * @return array|bool
     * @throws \yii\web\HttpException
     */
    public function parseRequest(callable $callable, $url, $postOptions = null, $force = true)
    {
        $result = call_user_func_array($callable, [$url, $postOptions]);
        Yii::info([
            'result' => $result,
        ], __METHOD__);
        if (isset($result['errcode']) && $result['errcode']) {
            $this->setLastError($result);
            Yii::error([
                'url'         => $url,
                'result'      => $result,
                'postOptions' => $postOptions,
            ], __METHOD__);
            switch ($result['errcode']) {
                case 40014: //access_token 失效,强制更新access_token, 并更新地址重新执行请求
                    if ($force) {
                        $url    = preg_replace_callback("/access_token=([^&]*)/i", function () {
                            return 'access_token=' . $this->getAccessToken(true);
                        }, $url);
                        $result = $this->parseRequest($callable, $url, $postOptions, false); // 仅重新获取一次,否则容易死循环
                    } else {
                        throw new HttpException(500, '更新access_token失败');
                    }
                    break;
            }
        }
        return $result;
    }

    /**
     * 微信数据缓存基本键值
     * @param $name
     * @return string
     */
    protected function getCacheKey($name)
    {
        return $this->cachePrefix . '_' . $this->corpid . '_';
    }

    /**
     * 获取微信凭据
     * @return bool|mixed
     */
    function requestAccessToken()
    {
        $result = $this->get(self::QY_ACCESS_TOKEN_URI, [
            'corpid'     => $this->corpid,
            'corpsecret' => $this->secret,
        ]);
        return isset($result['access_token']) ? $result : false;
    }

    /**
     * 获取认证URL / 企业获取code
     * @param        $redirect_uri
     * @param string $state
     * @return string
     */
    public function getAuthorizeUrl($redirect_uri, $state = 'authorize')
    {
        return $this->buildHttpUrl(self::QY_AUTHORIZE_URI, [
                'appid'         => $this->corpid,
                'redirect_uri'  => $redirect_uri,
                'response_type' => 'code',
                'scope'         => 'snsapi_base',
                'state'         => $state,
            ]) . '#wechat_redirect';
    }
}