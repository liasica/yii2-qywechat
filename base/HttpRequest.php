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

    public function build_url($url, array $params)
    {
    }

    public function curl_post()
    {
    }

    public function curl_get()
    {
    }
}