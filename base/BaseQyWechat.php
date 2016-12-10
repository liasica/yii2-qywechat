<?php
/**
 * Author: liasica
 * Email: magicrolan@qq.com
 * CreateTime: 2016/11/23 下午2:03
 */
namespace liasica\qywechat\base;

use yii\base\Event;
use yii\web\BadRequestHttpException;

abstract class BaseQyWechat extends BaseWechat
{
    const AFTER_UPDATE_ACCESS_TOKEN = 'afterUpdateAccessToekn'; // 更新全局access_token
    const QY_BASE_URL               = 'https://qyapi.weixin.qq.com/';

    /**
     * @var array 微信全局access_token
     */
    private $_accessToken;

    /**
     * 构建微信请求链接
     * @param       $url
     * @param array $options
     * @return string
     */
    public function buildHttpUrl($url, $options = [])
    {
        if (stripos($url, 'http://') === false && stripos($url, 'https://') === false) {
            $url = self::QY_BASE_URL . $url;
        }
        return parent::buildHttpUrl($url, $options);
    }

    const QY_ACCESS_TOKEN_URI = 'cgi-bin/gettoken';

    /**
     * @param bool $force
     * @return mixed
     * @throws \yii\web\BadRequestHttpException
     */
    public function getAccessToken($force = false)
    {
        $time = time();
        // 更新access_token
        if ($this->_accessToken == null || $this->_accessToken['expire'] < $time || $force) {
            $cache  = $this->getCache('access_token');
            $result = ($cache == null || $cache['expire'] < time()) || $force ? $this->requestAccessToken() : $cache;
            if (!$result) {
                throw new BadRequestHttpException('获取微信access_token失败');
            }
            $result['expire'] = $time + $result['expires_in'];
            $this->trigger(self::AFTER_UPDATE_ACCESS_TOKEN, new Event(['data' => $result]));
            $this->setCache('access_token', $result, $result['expires_in']);
            $this->setAccessToken($result);
        }
        return $this->_accessToken['access_token'];
    }

    /**
     * 设置access_token
     * @param array $accessToken
     */
    public function setAccessToken(array $accessToken)
    {
        if (!isset($accessToken['access_token'])) {
            throw new InvalidParamException('缺少access_token参数');
        } elseif (!isset($accessToken['expire'])) {
            throw new InvalidParamException('缺少expired参数');
        } elseif ($accessToken['expire'] < time()) {
            throw new InvalidParamException('expired不能小于当前时间');
        }
        $this->_accessToken = $accessToken;
    }

    const QY_AUTHORIZE_URI = 'https://open.weixin.qq.com/connect/oauth2/authorize';

    /**
     * 获取认证URL / 企业获取code
     * @param $redirect_uri 跳转链接
     * @return string
     */
    abstract public function getAuthorizeUrl($redirect_uri);

    const QY_USER_INFO_URI = 'cgi-bin/user/getuserinfo';

    /**
     * 根据code获取成员信息
     * @param $code
     * @return bool|mixed
     */
    public function getUserInfo($code)
    {
        $ret = $this->get(self::QY_USER_INFO_URI, [
            'access_token' => $this->getAccessToken(),
            'code'         => $code,
        ]);
        return isset($ret['errcode']) && $ret['errcode'] !== 0 ? false : $ret;
    }

    const QY_GET_USER_URI = 'cgi-bin/user/get';

    /**
     * 获取成员
     * @param $userid
     * @return bool|mixed
     */
    public function getUser($userid)
    {
        $ret = $this->get(self::QY_GET_USER_URI, [
            'access_token' => $this->getAccessToken(),
            'userid'       => $userid,
        ]);
        return isset($ret['errcode']) && $ret['errcode'] !== 0 ? false : $ret;
    }

    const QY_UPDATE_USER = 'cgi-bin/user/update';

    /**
     * 更新用户信息
     * @param       $userid
     * @param array $data
     * @return bool|mixed
     */
    public function updateUser($userid, array $data)
    {
        $ret           = $this->rawPost(self::QY_UPDATE_USER, array_merge($data, ['userid' => $userid]), ['access_token' => $this->getAccessToken()]);
        $this->lastRet = $ret;
        return isset($ret['errcode']) && $ret['errcode'] !== 0 ? false : $ret;
    }

    const QY_DEPARTMENT_LIST_URI = 'cgi-bin/department/list';

    /**
     * 获取部门列表
     * @param null $id
     * @return bool|mixed
     */
    public function getDepartmentList($id = null)
    {
        $ret = $this->get(self::QY_DEPARTMENT_LIST_URI, [
            'access_token' => $this->getAccessToken(),
            'id'           => $id,
        ]);
        return isset($ret['errcode']) && $ret['errcode'] !== 0 ? false : $ret;
    }

    const QY_USER_LIST_URI = 'cgi-bin/user/list';

    /**
     * 获取部门成员(详情)
     * @param     $department_id
     * @param int $fetch_child
     * @param int $status
     * @return bool|mixed
     */
    public function getUserList($department_id, $fetch_child = 0, $status = 0)
    {
        $ret = $this->get(self::QY_USER_LIST_URI, [
            'access_token'  => $this->getAccessToken(),
            'department_id' => $department_id,
            'fetch_child'   => $fetch_child,
            'status'        => $status,
        ]);
        return isset($ret['errcode']) && $ret['errcode'] !== 0 ? false : $ret;
    }

    const QY_CHAT_GET = 'cgi-bin/chat/get';

    /**
     * 获取会话
     * @param $chatid
     * @return bool|mixed
     */
    public function getChat($chatid)
    {
        $ret           = $this->get(self::QY_CHAT_GET, [
            'access_token' => $this->getAccessToken(),
            'chatid'       => $chatid,
        ]);
        $this->lastRet = $ret;
        return isset($ret['errcode']) && $ret['errcode'] !== 0 ? false : $ret;
    }

    const QY_CHAT_CREATE = 'cgi-bin/chat/create';

    /**
     * 创建会话
     * @param $chatid
     * @param $chatname
     * @param $chatowner
     * @param $userlist
     * @return bool|mixed
     */
    public function createChat($chatid, $chatname, $chatowner, $userlist)
    {
        $ret           = $this->rawPost(self::QY_CHAT_CREATE, [
            'chatid'   => (string)$chatid,
            'name'     => $chatname,
            'owner'    => $chatowner,
            'userlist' => $userlist,
        ], ['access_token' => $this->getAccessToken()]);
        $this->lastRet = $ret;
        return isset($ret['errcode']) && $ret['errcode'] !== 0 ? false : $ret;
    }

    const QY_CHAT_SEND = 'cgi-bin/chat/send';

    /**
     * 发送消息
     * @param        $chatid
     * @param        $sender
     * @param        $data
     * @param string $type
     * @param string $msgtype
     * @return bool|mixed
     */
    public function sendChat($chatid, $sender, $data, $type = 'group', $msgtype = 'text')
    {
        $ret           = $this->rawPost(self::QY_CHAT_SEND, array_merge([
            'receiver' => [
                'type' => $type,
                'id'   => $chatid,
            ],
            'sender'   => $sender,
            'msgtype'  => $msgtype,
        ], $data), ['access_token' => $this->getAccessToken()]);
        $this->lastRet = $ret;
        return isset($ret['errcode']) && $ret['errcode'] !== 0 ? false : $ret;
    }

    const QY_MSG_SEND = 'cgi-bin/message/send';

    /**
     * 发送消息通知
     * @param $data
     * @return bool|mixed
     */
    public function sendMessage($data)
    {
        $ret           = $this->rawPost(self::QY_MSG_SEND, $data, ['access_token' => $this->getAccessToken()]);
        $this->lastRet = $ret;
        return isset($ret['errcode']) && $ret['errcode'] !== 0 ? false : $ret;
    }

    const QY_MATERIAL_ADD = 'cgi-bin/material/add_material';

    /**
     * 上传其他类型永久素材
     * @param       $type
     * @param       $file
     * @param array $data
     * @return bool|mixed
     */
    public function addMaterial($type, $file, $data = [])
    {
        $ret           = $this->post(self::QY_MATERIAL_ADD, array_merge($data, [
            'media' => $this->uploadFile($file),
        ]), ['access_token' => $this->getAccessToken(), 'type' => $type]);
        $this->lastRet = $ret;
        return isset($ret['errcode']) && $ret['errcode'] !== 0 ? false : $ret;
    }
}