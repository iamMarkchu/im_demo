<?php
/**
 * Created by PhpStorm.
 * User: mark
 * Date: 2019-01-24
 * Time: 21:51
 */

namespace App\HttpController;

use App\Utility\Pool\RedisPool;
use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\EasySwoole\Config;
use EasySwoole\Http\AbstractInterface\Controller;

/**
 * Class Api
 * @package App\HttpController
 */
class Api extends Controller
{
    public function index()
    {
        $redis = PoolManager::getInstance()->getPool(RedisPool::class)->getObj(Config::getInstance()->getConf('REDIS.POOL_TIME_OUT'));
        $data = $redis->lRange('swoole:msg_history:chukui_111', 0, -1);
        PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($redis);
        $this->writeJson(200, $data, '获取成功!');
    }
}