<?php
/**
 * Created by PhpStorm.
 * User: mark
 * Date: 2019-01-25
 * Time: 10:19
 */

namespace App\WebSocket;


use App\Utility\Pool\RedisPool;
use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\Component\Singleton;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\ServerManager;
use Swoole\Coroutine\Channel;

class WebsocketClient
{
    use Singleton;

    const REDIS_REGISTER_KEY = 'easy_swoole:registers';
    const REDIS_REGISTER_USER_KEY = 'easy_swoole:register_users';
    const REDIS_MSG_HISTORY_KEY = 'easy_swoole:msg_history:';
    const REDIS_ROOM_MSG_HISTORY_KEY = 'easy_swoole:room:msg_history:';
    const REDIS_SEND_MSG_KEY = 'easy_swoole:send_msg';
    const REDIS_ROOM_MEMBERS_KEY = 'easy_swoole:room_members:';
    const REDIS_USER_UNPUSH_MSG_KEY = 'easy_swoole:user_unpush:msg:';

    private $msgChan;

    private function __construct(...$args)
    {
        $this->msgChan = new Channel();
    }

    public function demo()
    {
        $redis = PoolManager::getInstance()->getPool(RedisPool::class)->getObj(Config::getInstance()->getConf('REDIS.POOL_TIME_OUT'));
        PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($redis);
    }

    /**
     * 用户上线
     * @param string $userId
     * @param string $fd
     * @return bool
     */
    public function userOnline(string $userId, string $fd): bool
    {
        $redis = PoolManager::getInstance()->getPool(RedisPool::class)->getObj(Config::getInstance()->getConf('REDIS.POOL_TIME_OUT'));
        $redis->multi();
        $redis->hSet(self::REDIS_REGISTER_KEY, $fd, $userId);
        $redis->hSet(self::REDIS_REGISTER_USER_KEY, $userId, $fd);
        $redis->exec();
        PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($redis);

        return true;
    }

    /**
     * 用户下线
     * @param $fd
     * @return bool
     */
    public function userOffline($fd): bool
    {
        $redis = PoolManager::getInstance()->getPool(RedisPool::class)->getObj(Config::getInstance()->getConf('REDIS.POOL_TIME_OUT'));
        $user = $redis->hGet(self::REDIS_REGISTER_KEY, $fd);
        $redis->multi();
        $redis->hDel(self::REDIS_REGISTER_KEY, $fd);
        $redis->hDel(self::REDIS_REGISTER_USER_KEY, $user);
        $redis->exec();
        PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($redis);

        return true;
    }

    /**
     * 获取在线用户列表
     * @return array
     */
    public function getOnlineUsers(): array
    {
        $redis = PoolManager::getInstance()->getPool(RedisPool::class)->getObj(Config::getInstance()->getConf('REDIS.POOL_TIME_OUT'));
        $users = $redis->hKeys(self::REDIS_REGISTER_USER_KEY);
        PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($redis);

        return $users;
    }

    /**
     * 获取用户的websocket连接
     * @param string $userId
     * @return string|null
     */
    public function getUserFd(string $userId)
    {
        $redis = PoolManager::getInstance()->getPool(RedisPool::class)->getObj(Config::getInstance()->getConf('REDIS.POOL_TIME_OUT'));
        $fd = $redis->hGet(self::REDIS_REGISTER_USER_KEY, $userId);
        PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($redis);

        return $fd;
    }

    /**
     * 保存消息记录
     * @param string $from
     * @param string $to
     * @param string $data
     * @return bool
     */
    public function saveHistory(string $from, string $to, string $data): bool
    {
        $redis = PoolManager::getInstance()->getPool(RedisPool::class)->getObj(Config::getInstance()->getConf('REDIS.POOL_TIME_OUT'));
        $redis->rPush(self::REDIS_MSG_HISTORY_KEY . (strcmp($from, $to) > 0 ? $from . '_' . $to : $to . '_' . $from), $data);
        PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($redis);

        return true;
    }

    public function saveRoomHistory(int $chatRoomId, string $userId, string $data): bool
    {
        $redis = PoolManager::getInstance()->getPool(RedisPool::class)->getObj(Config::getInstance()->getConf('REDIS.POOL_TIME_OUT'));
        $redis->rPush(self::REDIS_ROOM_MSG_HISTORY_KEY.$chatRoomId, $data);
        PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($redis);

        return true;
    }

    /**
     * 获取聊天记录
     * @param string $from
     * @param string $to
     * @return array
     */
    public function getHistory(string $from, string $to): array
    {
        $redis = PoolManager::getInstance()->getPool(RedisPool::class)->getObj(Config::getInstance()->getConf('REDIS.POOL_TIME_OUT'));
        $list = $redis->lRange(self::REDIS_MSG_HISTORY_KEY . (strcmp($from, $to) > 0 ? $from . '_' . $to : $to . '_' . $from), 0, 10);
        PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($redis);

        return $list;
    }

    public function getRoomHistory(int $chatRoomId): array
    {
        $redis = PoolManager::getInstance()->getPool(RedisPool::class)->getObj(Config::getInstance()->getConf('REDIS.POOL_TIME_OUT'));
        $list = $redis->lRange(self::REDIS_ROOM_MSG_HISTORY_KEY.$chatRoomId, 0, 10);
        PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($redis);

        return $list;
    }

    /**
     * 重启服务，删除在线用户
     */
    public function delAllRegisters(): bool
    {
        var_dump('删除所有在线用户');
        $redis = PoolManager::getInstance()->getPool(RedisPool::class)->getObj(Config::getInstance()->getConf('REDIS.POOL_TIME_OUT'));
        $redis->multi();
        $redis->del(self::REDIS_REGISTER_KEY);
        $redis->del(self::REDIS_REGISTER_USER_KEY);
        $redis->exec();
        PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($redis);
        return true;
    }

    public function startSendLoop()
    {
        var_dump('开启发送消息协程');
        go(function () {
            while (1) {
                $redis = PoolManager::getInstance()->getPool(RedisPool::class)->getObj(Config::getInstance()->getConf('REDIS.POOL_TIME_OUT'));
                $data = $redis->brPop(self::REDIS_SEND_MSG_KEY, 10);
                PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($redis);
                if (is_null($data))
                    continue;
                $msg = $data[1];
                go(function () use ($msg) {
                    $server = ServerManager::getInstance()->getSwooleServer();
                    $decodeData = json_decode($msg, true);
                    $fd = $this->getUserFd($decodeData['to']);

                    // 在线就直接发送给用户
                    if ($fd) {
                        $server->push($fd, $msg);
                        $this->saveHistory($decodeData['from'], $decodeData['to'], $msg);
                    } else {
                        // 不在线，则推入未推送的队列，等待用户上线,继续推
                        $redis = PoolManager::getInstance()->getPool(RedisPool::class)->getObj(Config::getInstance()->getConf('REDIS.POOL_TIME_OUT'));
                        $redis->lPush(self::REDIS_USER_UNPUSH_MSG_KEY.$decodeData['to'], $msg);
                        PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($redis);
                    }
                });
            }
        });
    }

    public function sendMsg(array $data)
    {
        var_dump('发消息:' . json_encode($data));
        $redis = PoolManager::getInstance()->getPool(RedisPool::class)->getObj(Config::getInstance()->getConf('REDIS.POOL_TIME_OUT'));
        $redis->lPush(self::REDIS_SEND_MSG_KEY, json_encode($data));
        PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($redis);
    }

    public function sendRoomMsg(array $data)
    {
        var_dump('聊天室发消息:' . json_encode($data));
        $this->broadcastToRoom($data['data'], intval($data['to']), $data['from']);
    }

    public function broadcast(string $content)
    {
        var_dump('广播消息');
        go(function () use ($content) {
            $users = $this->getOnlineUsers();
            foreach ($users as $user) {
                go(function () use ($user, $content) {
                    $data = [
                        'from'   => 'server',
                        'to'     => $user,
                        'data'   => $content,
                        'action' => 'chat',
                    ];
                    $this->sendMsg($data);
                });
            }
        });
    }

    public function broadcastToRoom(string $content, int $chatRoomId, string $userId='')
    {
        var_dump('聊天室广播消息');
        go(function () use ($content, $chatRoomId, $userId) {
            $data = [
                'from'   => $userId,
                'to'     => $chatRoomId,
                'data'   => $content,
                'action' => 'chatRoom',
                'broadcast' => 1,
            ];
            $this->saveRoomHistory($chatRoomId, $userId, json_encode($data));
            $users = $this->getRoomMembers($chatRoomId);
            foreach ($users as $user) {
                if ($userId == $user)
                    continue;
                go(function () use ($user, $content, $chatRoomId, $userId) {
                    $data = [
                        'from'   => $userId,
                        'to'     => $user,
                        'data'   => $content,
                        'action' => 'chat',
                        'broadcast' => 1,
                    ];
                    $this->sendMsg($data);
                });
            }
        });
    }

    public function joinRoom(string $userId, int $chatRoomId)
    {
        var_dump('加入聊天室: userId:' . $userId . ', chatRoomId:' . $chatRoomId);
        $redis = PoolManager::getInstance()->getPool(RedisPool::class)->getObj(Config::getInstance()->getConf('REDIS.POOL_TIME_OUT'));
        $redis->sadd(self::REDIS_ROOM_MEMBERS_KEY . $chatRoomId, $userId);
        PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($redis);

        // 通知聊天室内的所有人，有人加入聊天室
        $this->broadcastToRoom($userId . ' 加入聊天室', $chatRoomId, $userId);
    }

    public function getRoomMembers(int $chatRoomId)
    {
        $redis = PoolManager::getInstance()->getPool(RedisPool::class)->getObj(Config::getInstance()->getConf('REDIS.POOL_TIME_OUT'));
        $users = $redis->sMembers(self::REDIS_ROOM_MEMBERS_KEY.$chatRoomId);
        PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($redis);
        return $users;
    }

    public function pushUnPushMsg(string $userId)
    {
        go(function() use($userId) {
            $redis = PoolManager::getInstance()->getPool(RedisPool::class)->getObj(Config::getInstance()->getConf('REDIS.POOL_TIME_OUT'));
            while (1) {
                $data = $redis->rPop(self::REDIS_USER_UNPUSH_MSG_KEY.$userId);
                if (is_null($data))
                    break;
                $this->sendMsg(json_decode($data, true));
            }
            PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($redis);
        });
    }
}