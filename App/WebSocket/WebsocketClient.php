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

class WebsocketClient
{
    use Singleton;

    const REDIS_REGISTER_KEY = 'easy_swoole:registers';
    const REDIS_REGISTER_USER_KEY = 'easy_swoole:register_users';
    const REDIS_MSG_HISTORY_KEY = 'easy_swoole:msg_history:';

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

    /**
     * 重启服务，删除在线用户
     */
    public function delAllRegisters(): bool
    {
        $redis = PoolManager::getInstance()->getPool(RedisPool::class)->getObj(Config::getInstance()->getConf('REDIS.POOL_TIME_OUT'));
        $redis->multi();
        $redis->del(self::REDIS_REGISTER_KEY);
        $redis->del(self::REDIS_REGISTER_USER_KEY);
        $redis->exec();

        return true;
    }
}