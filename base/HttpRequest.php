<?php
/**
 * Author: liasica
 * Email: magicrolan@qq.com
 * CreateTime: 2016/10/12 下午9:24
 */
namespace liasica\qywechat\base;

trait HttpRequest
{
    public $data;
    public $headers;
    public $url;

    /**
     * 构建URL
     * @param       $url
     * @param array $params
     * @return string
     */
    public function buildHttpUrl($url, array $params)
    {
        if (!empty($options)) {
            $url .= (stripos($url, '?') === null ? '&' : '?') . http_build_query($options);
        }
        return $url;
    }

    public function request($method, $data, $headers)
    {
    }
}