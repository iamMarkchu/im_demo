<?php
/**
 * Created by PhpStorm.
 * User: Mark Chu
 * Date: 2019/01/25
 * Time: 09:30
 */

namespace App\WebSocket;

use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use EasySwoole\Socket\AbstractInterface\Controller;

class Websocket extends Controller
{
    /**
     * 处理单聊消息
     */
    public function chat()
    {
        $args = $this->caller()->getArgs();
        WebsocketClient::getInstance()->sendMsg($args);
    }

    /**
     * 处理聊天室消息
     */
    public function chatInRoom()
    {
        $args = $this->caller()->getArgs();
        WebsocketClient::getInstance()->sendRoomMsg($args);
    }
}