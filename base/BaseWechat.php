<?php
/**
 * Author: liasica
 * Email: magicrolan@qq.com
 * CreateTime: 2016/11/23 下午2:10
 */
namespace liasica\qywechat\base;

use Yii;
use yii\base\Component;

abstract class BaseWechat extends Component
{
    protected $cacheTime = 7200;
    private $lastError;

    /**
     * 构建URL
     * @param       $url
     * @param array $options
     * @return string
     */
    public function buildHttpUrl($url, $options = [])
    {
        if (!empty($options)) {
            $url .= (stripos($url, '?') === null ? '&' : '?') . http_build_query($options);
        }
        return $url;
    }

    /**
     * @param       $url
     * @param array $options
     * @return bool
     */
    public function request($url, $options = [])
    {
        $options = [
                       CURLOPT_URL            => $url,
                       CURLOPT_TIMEOUT        => 30,
                       CURLOPT_CONNECTTIMEOUT => 30,
                       CURLOPT_RETURNTRANSFER => true,
                   ] + (stripos($url, "https://") !== false ? [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSLVERSION     => CURL_SSLVERSION_TLSv1 // 微信官方屏蔽了ssl2和ssl3, 启用更高级的ssl
            ] : []) + $options;
        // CURL请求
        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $content = curl_exec($curl);
        $status  = curl_getinfo($curl);
        curl_close($curl);
        if (isset($status['http_code']) && $status['http_code'] == 200) {
            return json_decode($content, true) ?: false; // 正常加载应该是只返回json字符串
        }
        Yii::error([
            'result' => $content,
            'status' => $status,
        ], __METHOD__);
        return false;
    }

    /**
     * Http Get 请求
     * @param       $url
     * @param array $options
     * @return mixed
     */
    public function get($url, $options = [])
    {
        $ret = $this->parseRequest(function ($url) {
            return $this->request($url);
        }, $this->buildHttpUrl($url, $options));
        Yii::info([
            'url'     => $url,
            'options' => $options,
            'result'  => $ret,
        ], __METHOD__);
        return $ret;
    }

    /**
     * Http Post 请求
     * @param       $url
     * @param array $postOptions
     * @param array $options
     * @return mixed
     */
    public function post($url, array $postOptions, array $options = [])
    {
        $ret = $this->parseRequest(function ($url, $postOptions) {
            return $this->request($url, [
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => $postOptions,
            ]);
        }, $this->buildHttpUrl($url, $options), $postOptions);
        Yii::info([
            'url'         => $url,
            'postOptions' => $postOptions,
            'options'     => $options,
            'result'      => $ret,
        ], __METHOD__);
        return $ret;
    }

    /**
     * Http Raw数据 Post 请求
     * @param       $url
     * @param       $postOptions
     * @param array $options
     * @return mixed
     */
    public function rawPost($url, $postOptions, array $options = [])
    {
        $ret = $this->parseRequest(function ($url, $postOptions) {
            return $this->request($url, [
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => is_array($postOptions) ? json_encode($postOptions, JSON_UNESCAPED_UNICODE) : $postOptions,
            ]);
        }, $this->buildHttpUrl($url, $options), $postOptions);
        Yii::info([
            'url'         => $url,
            'postOptions' => $postOptions,
            'options'     => $options,
            'result'      => $ret,
        ], __METHOD__);
        return $ret;
    }

    /**
     * 解析微信请求响应内容
     * @param callable          $callable    Http请求主体函数
     * @param string            $url         Api地址
     * @param array|string|null $postOptions Api地址一般所需要的post参数
     * @return array|bool
     */
    abstract public function parseRequest(callable $callable, $url, $postOptions = null);

    /**
     * @return mixed
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * @param mixed $lastError
     */
    protected function setLastError($lastError)
    {
        $this->lastError = $lastError;
    }

    /**
     * 上传文件请使用该类来解决curl版本兼容问题
     * @param $filePath
     * @return \CURLFile|string
     */
    protected function uploadFile($filePath)
    {
        // php 5.5将抛弃@写法,引用CURLFile类来实现 @see http://segmentfault.com/a/1190000000725185
        return class_exists('\CURLFile') ? new \CURLFile($filePath) : '@' . $filePath;
    }

    /**
     * 微信数据缓存基本键值
     * @param $name
     * @return string
     */
    abstract protected function getCacheKey($name);

    /**
     * 缓存微信数据
     * @param      $name
     * @param      $value
     * @param null $duration
     * @return bool
     */
    protected function setCache($name, $value, $duration = null)
    {
        $duration === null && $duration = $this->cacheTime;
        return Yii::$app->cache->set($this->getCacheKey($name), $value, $duration);
    }

    /**
     * 获取微信缓存数据
     * @param $name
     * @return mixed
     */
    protected function getCache($name)
    {
        return Yii::$app->cache->get($this->getCacheKey($name));
    }

    /**
     * 获取微信access_token
     * @return mixed
     */
    abstract function requestAccessToken();
}