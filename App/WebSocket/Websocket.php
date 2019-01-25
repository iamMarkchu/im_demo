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
        $str = json_encode($args);
        $to = $args['to'] ?? '';
        $from = $args['from'] ?? '';
        $fd = WebsocketClient::getInstance()->getUserFd($to);
        WebsocketClient::getInstance()->saveHistory($from, $to, $str);
        if ($fd) {
            $flag = ServerManager::getInstance()->getSwooleServer()->push($fd, $str);
            echo "flag: {$flag}".PHP_EOL;
        }
    }

    /**
     * 处理聊天室消息
     */
    public function chatInRoom()
    {

    }

    public function who()
    {
        $this->response()->setMessage('your fd is ' . $this->caller()->getClient()->getFd());
    }

    function delay()
    {
        $this->response()->setMessage('this is delay action');
        $client = $this->caller()->getClient();
        // 异步推送, 这里直接 use fd也是可以的
        // TaskManager::async 回调参数中的代码是在 task 进程中执行的 默认不含连接池 需要注意可能出现 getPool null的情况
        TaskManager::async(function () use ($client) {
            $server = ServerManager::getInstance()->getSwooleServer();
            $i = 0;
            while ($i < 5) {
                sleep(1);
                $server->push($client->getFd(), 'push in http at ' . time());
                $i++;
            }
        });
    }
}