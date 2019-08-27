<?php 
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
use \Workerman\Worker;
use \Workerman\WebServer;
use \GatewayWorker\Gateway;
use \GatewayWorker\BusinessWorker;
use \Workerman\Autoloader;

// 自动加载类
require_once __DIR__ . '/../../vendor/autoload.php';

// gateway 进程，这里使用Text协议，可以用telnet测试
$context = array(
    'ssl' => array( //   privkey.pem
        'local_cert'    => '/etc/letsencrypt/live/www.midoushu.com/fullchain.pem', 
        'local_pk'      => '/etc/letsencrypt/live/www.midoushu.com/privkey.pem',
        'verify_peer'   => false
    )
);
$gateway = new Gateway("websocket://0.0.0.0:8282",$context);
$gateway->transport = 'ssl';
$gateway->registerAddress = '0.0.0.0:1238';

/*$gateway = new Gateway("websocket://127.0.0.1:8282");
// 服务注册地址
$gateway->registerAddress = '127.0.0.1:1238';*/
// gateway名称，status方便查看
$gateway->name = 'YourAppGateway';
// gateway进程数
$gateway->count = 1;
// 本机ip，分布式部署时使用内网ip
$gateway->lanIp = '127.0.0.1';
// 内部通讯起始端口，假如$gateway->count=4，起始端口为4000
// 则一般会使用4000 4001 4002 4003 4个端口作为内部通讯端口 

$gateway->startPort = 2900;



// 心跳间隔
$gateway->pingInterval = 55;
#其中pingNotResponseLimit = 0 代表服务端允许客户端不发送心跳，服务端不会因为客户端长时间没发送数据而断开连接。
#如果pingNotResponseLimit=1，则代表客户端必须定时发送心跳给服务端，否则pingNotResponseLimit*pingInterval=55秒内没有任何数据发来则关闭对应连接，并触发onClose。

$gateway->pingNotResponseLimit = 1;

// 心跳数据
$gateway->pingData = '{"type":"ping"}';

 
// 当客户端连接上来时，设置连接的onWebSocketConnect，即在websocket握手时的回调
$gateway->onConnect = function($connection)
{
    $connection->onWebSocketConnect = function($connection , $http_header)
    {
        // 可以在这里判断连接来源是否合法，不合法就关掉连接
        // $_SERVER['HTTP_ORIGIN']标识来自哪个站点的页面发起的websocket链接
        if($_SERVER['HTTP_ORIGIN'] != 'https://www.midoushu.com')
        {
           
            $connection->close();
        }
        // onWebSocketConnect 里面$_GET $_SERVER是可用的
        // var_dump($_GET, $_SERVER);
    };
}; 


// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}

