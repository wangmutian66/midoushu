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

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

use \GatewayWorker\Lib\Gateway;
#use \GatewayWorker\Lib\Db;
//require_once 'Connection.php';
/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{
  /**
     * 新建一个类的静态成员，用来保存数据库实例
     */
    public static $db = null;

    /**
     * 进程启动后初始化数据库连接
     */
    public static function onWorkerStart($worker)
    {
      //  self::$db = new \Workerman\MySQL\Connection('127.0.0.1', '3306', 'midoushu', 'mdstk2018', 'midoushu');
     //   self::$db = new \Workerman\MySQL\Connection('127.0.0.1', '3306', 'root', 'root', 'midoushu');
    }
    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     * 
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {
     //   global $num;
        // 向当前client_id发送数据
        //Gateway::sendToClient($client_id, "Hello $client_id\r\n");
        // 向所有人发送
        // Gateway::sendToAll("$client_id login\r\n");
        Gateway::sendToClient($client_id,json_encode([
            'type'=>'init',
            'client_id'=>$client_id
        ]));

    }
  
    /**
    * 当客户端发来消息时触发
    * @param int $client_id 连接id
    * @param mixed $message 具体消息
    */
    public static function onMessage($client_id, $message)
    {
        // 使用数据库实例
    //    echo "+++++".$message .'--------';
        // 向所有人发送
        $message_data = json_decode($message,true);
        if(!$message_data){
           return;
        }
        $fromid = isset($message_data['fromid']) ? $message_data['fromid'] : 0;
        $toid = isset($message_data['toid']) ? $message_data['toid'] : 0 ;
        $uuid = isset($message_data['uuid']) ? $message_data['uuid'] : '';
        $fromip = isset($message_data['fromip']) ? $message_data['fromip'] : '';
        switch($message_data['type']){
           case "bind":
                $_SESSION['customer_uid'] = $fromid;
                Gateway::bindUid($client_id, $fromid);
                /*if($fromid < 10000000){
                    self::$db->query("UPDATE `tp_users` SET `is_line` = 1 where user_id = {$fromid}");  
                }*/
                return;
           case "say":
                $text = htmlspecialchars($message_data['data']);
                $date=[
                   'type'=>'text',
                   'data'=>$text,
                   'fromid'=>$fromid,
                   'toid'=>$toid,
                   'time'=>time(),
                   'fromip'=>$fromip,
                   'uuid'=>$uuid,
                   'isread'=>0,
                ];
                if(Gateway::isUidOnline($toid)){
                  //如果用户还在线，则发送信息;
                   Gateway::sendToUid($toid, json_encode($date));
                }
                return;
           case "send_file":

               $date=[
                   'type'=>'send_file',
                   'fromid'=>$fromid,
                   'toid'=>$toid,
                   'data'=>$message_data['data'],
                   'uuid'=>$uuid,
                   'time'=>time()
               ];
               Gateway::sendToUid($toid,json_encode($date));
               return;
            case "file":
               $date=[
                   'type'=>'file',
                   'fromid'=>$fromid,
                   'toid'=>$toid,
                   'data'=>$message_data['data'],
                   'uuid'=>$uuid,
                   'time'=>time()
               ];
               Gateway::sendToUid($toid,json_encode($date));
               return;

             case "online":
                $status =  GateWay::isUidOnline($toid);
                Gateway::sendToUid($fromid,json_encode(['type'=>'online','status'=>$status])); 
               return;
            case "send_goods":
                $text = $message_data['data'];
                $date=[
                   'type'=>'send_goods',
                   'data'=>$text,
                   'fromid'=>$fromid,
                   'toid'=>$toid,
                   'time'=>time(),
                   'fromip'=>$fromip,
                ];
                if(Gateway::isUidOnline($toid)){
                  Gateway::sendToUid($toid,json_encode($date));
                }
               return;
            case 'other_user':
                $date = ['type'=>'other_user',
                   'fromid'=>$fromid,
                   'toid'=>$toid,
                   'data'=>htmlspecialchars($message_data['data']),
                   'time'=>time(),
                 ];
                if(Gateway::isUidOnline($toid)){
                   Gateway::sendToUid($toid,json_encode($date));
                }
                return;
            case 'delete_massage':
                $date = ['type'=>'delete_massage',
                   'fromid'=>$fromid,
                   'toid'=>$toid,
                   'id'=>$message_data['id'],
                   'time'=>time(),
                 ];
                if(Gateway::isUidOnline($toid)){
                   Gateway::sendToUid($toid,json_encode($date));
                }
            case "ping":
               return;
        }
    }




   /**
    * 当用户断开连接时触发
    * @param int $client_id 连接id
    */
   public static function onClose($client_id)
   {
  
  /*  $fromid = $_SESSION['customer_uid'];
      if($fromid < 10000000){
          self::$db->query("UPDATE `tp_users` SET `is_line` = 0 where user_id = {$fromid}");  
      }
      unset($_SESSION['customer_uid']);
    */
        
   }
}
