<?php
/**
 * Author: liasica
 * Email: magicrolan@qq.com
 * CreateTime: 2016/10/12 下午9:18
 */
namespace liasica\qywechat;

use liasica\qywechat\base\BaseQyWechat;
use liasica\qywechat\crypt\WXBizMsgCrypt;
use Yii;
use yii\web\HttpException;

class Qywechat extends BaseQyWechat
{
    public $corpid;
    public $secret;
    public $msgAESKey;
    public $msgToken;
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
        return $this->cachePrefix . '_' . $this->corpid . '_' . $name;
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

    /**
     * 校验回调URL
     */
    public function verifyURL()
    {
        $echostr = '';
        $wxcpt   = new WXBizMsgCrypt($this->msgToken, $this->msgAESKey, $this->corpid);
        $errCode = $wxcpt->VerifyURL($_GET['msg_signature'], $_GET['timestamp'], $_GET['nonce'], $_GET['echostr'], $echostr);
        if ($errCode == 0) {
            echo $echostr;
            Yii::$app->end();
        }
    }

    /**
     * @return bool|string
     */
    public function decryptMsg()
    {
        $sMsg    = '';
        $body    = Yii::$app->request->getRawBody();
        $wxcpt   = new WXBizMsgCrypt($this->msgToken, $this->msgAESKey, $this->corpid);
        $errCode = $wxcpt->DecryptMsg($_GET['msg_signature'], $_GET['timestamp'], $_GET['nonce'], $body, $sMsg);
        if ($errCode == 0) {
            // 解密成功，sMsg即为xml格式的明文
            // 对明文的处理
            // $xml = new \DOMDocument();
            // $xml->loadXML($sMsg);
            // $content = $xml->getElementsByTagName('Content')->item(0)->nodeValue;
            // print("content: " . $content . "\n\n");
            return $sMsg;
        } else {
            return false;
        }
    }

    /**
     * js api ticket 获取
     */
    const QY_JSAPI_TICKET = 'cgi-bin/get_jsapi_ticket';

    /**
     * 请求服务器jsapi_ticket
     * @return mixed
     */
    public function requestJsApiTicket()
    {
        return $this->get(self::QY_JSAPI_TICKET, [
            'access_token' => $this->getAccessToken(),
        ]);
    }

    /**
     * 生成js 必需的config
     * 只需在视图文件输出JS代码:
     *  wx.config(<?= json_encode($wehcat->jsApiConfig()) ?>); // 默认全权限
     *  wx.config(<?= json_encode($wehcat->jsApiConfig([ // 只允许使用分享到朋友圈功能
     *      'jsApiList' => [
     *          'onMenuShareTimeline'
     *      ]
     *  ])) ?>);
     * @param array $config
     * @return array
     * @throws HttpException
     */
    public function jsApiConfig(array $config = [])
    {
        $data = [
            'jsapi_ticket' => $this->getJsApiTicket(),
            'noncestr'     => Yii::$app->security->generateRandomString(16),
            'timestamp'    => $_SERVER['REQUEST_TIME'],
            'url'          => explode('#', Yii::$app->request->getAbsoluteUrl())[0],
        ];
        return array_merge([
            'debug'     => false,
            'appId'     => $this->corpid,
            'timestamp' => $data['timestamp'],
            'nonceStr'  => $data['noncestr'],
            'signature' => sha1(urldecode(http_build_query($data))),
            'jsApiList' => [
                'onMenuShareTimeline',
                'onMenuShareAppMessage',
                'onMenuShareQQ',
                'onMenuShareWeibo',
                'startRecord',
                'stopRecord',
                'onVoiceRecordEnd',
                'playVoice',
                'pauseVoice',
                'stopVoice',
                'onVoicePlayEnd',
                'uploadVoice',
                'downloadVoice',
                'chooseImage',
                'previewImage',
                'uploadImage',
                'downloadImage',
                'translateVoice',
                'getNetworkType',
                'openLocation',
                'getLocation',
                'hideOptionMenu',
                'showOptionMenu',
                'hideMenuItems',
                'showMenuItems',
                'hideAllNonBaseMenuItem',
                'showAllNonBaseMenuItem',
                'closeWindow',
                'scanQRCode',
            ],
        ], $config);
    }
}