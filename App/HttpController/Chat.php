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

class Chat extends Controller
{
    public function index()
    {
        $this->response()->sendFile(__DIR__.'/websocket2.html');
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
        $this->writeJson(200, 'ok', '加入聊天室成功!');
    }

    /**
     * 获取聊天室消息历史
     */
    public function chatRoomHistory()
    {
        $this->writeJson(200, 'ok', '获取聊天室消息历史成功!');
    }
}