<?php
/**
 * Created by PhpStorm.
 * User: mark
 * Date: 2019-01-25
 * Time: 09:54
 */

namespace App\HttpController;

use App\WebSocket\WebsocketClient;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\Http\Message\Status;
use EasySwoole\Validate\Validate;

class Chat extends Controller
{
    public function index()
    {
        $this->response()->sendFile(__DIR__ . '/websocket2.html');
    }

    /**
     * 获取在线用户列表
     * GET /chat/users
     */
    public function users()
    {
        $users = WebsocketClient::getInstance()->getOnlineUsers();
        $this->writeJson(200, $users, '获取在线用户列表成功!');
    }

    /**
     * 获取聊天消息历史
     * GET /chat/history
     */
    public function history()
    {
        $from = $this->request()->getRequestParam('from');
        $to = $this->request()->getRequestParam('to');
        if (is_null($from) || is_null($to))
            $this->writeJson(500, [], '请提供from,to参数!');
        $list = WebsocketClient::getInstance()->getHistory($from, $to);
        $this->writeJson(200, $list, '获取聊天消息历史成功!');
    }

    /**
     * 加入聊天室
     * GET /chat/joinRoom
     */
    public function joinRoom()
    {
        $chatRoomId = $this->request()->getRequestParam('chatRoomId');
        $userId = $this->request()->getRequestParam('userId');
        WebsocketClient::getInstance()->joinRoom($userId, $chatRoomId);
        $this->writeJson(200, 'ok', '加入聊天室成功!');
    }

    /**
     * 获取聊天室消息历史
     */
    public function chatRoomHistory()
    {
        $chatRoomId = $this->request()->getRequestParam('chatRoomId');
        $list = WebsocketClient::getInstance()->getRoomHistory($chatRoomId);
        $this->writeJson(200, $list, '获取聊天室消息历史成功!');
    }

    /**
     * 发送消息
     */
    public function send()
    {
        $validate = new Validate();
        $validate->addColumn('from')->required('from:用户名必填!');
        $validate->addColumn('to')->required('to:用户名必填!');
        $validate->addColumn('content')->required('发送正文必须!');
        if ($this->validate($validate)) {
            $data = [
                'from'   => $this->request()->getRequestParam('from'),
                'to'     => $this->request()->getRequestParam('to'),
                'action' => 'chat',
                'data'   => $this->request()->getRequestParam('content'),
            ];
            $msgType = $this->request()->getRequestParam('msgType');
            if (!$msgType)
                $msgType = 'single';
            if ($msgType == 'single') {
                WebsocketClient::getInstance()->sendMsg($data);
            } else {
                WebsocketClient::getInstance()->sendRoomMsg($data);
            }
            $this->writeJson(Status::CODE_OK, null, 'success');
        } else {
            $this->writeJson(Status::CODE_BAD_REQUEST, $validate->getError()->__toString(), 'fail');
        }
    }
}