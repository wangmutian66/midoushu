<?php
namespace app\applet\controller;
use think\Config;
use WxLogin\ErrorCode;
use WxLogin\WXBizDataCrypt;
use WxLogin\PKCS7Encoder;
use WxLogin\Prpcrypt;


class WXLoginHelper {

    //默认配置
    protected $config = [
        'url' => "https://api.weixin.qq.com/sns/jscode2session", //微信获取session_key接口url
        'appid' => 'wxbf199f3c5090e682', // APPId
        'secret' => '7388dde63b207a2b1f9b042fa1441616', // 秘钥
        'grant_type' => 'authorization_code', // grant_type，一般情况下固定的
    ];


    /**
     * 构造函数
     * WXLoginHelper constructor.
     */
    public function __construct() {
        //可设置配置项 wxmini, 此配置项为数组。
        if ($wx = Config::get('wx')) {
            $this->config = array_merge($this->config, $wx);
        }
    }

    public function index(){

        $code= $_REQUEST['code'];
        $url="https://api.weixin.qq.com/sns/jscode2session";
        $params = [
                    'appid' => $this->config['appid'],
                    'secret' => $this->config['secret'],
                    'js_code' => $code,
                    'grant_type' => $this->config['grant_type']
                ];
         $res= $this->makeRequest($url,$params);
         $json_obj = json_decode($res['result'], true);
        
         $openid = $json_obj["openid"];
         $session_key = $json_obj['session_key'];
         // 生成session3rd
         // 此处为方便演示直接以时间戳代替，实际应用可自定义方法生成随机字符串
         $session3rd           = time();
         $value['openid']      = $openid;
         $value['session_key'] = $session_key;
         // 缓存session3rd 并设置一个过期时间
         S($session3rd, $value, 3600);
         // 返回信息
         $result['session3rd'] = $session3rd;
         if($openid){
             $user  = M('users')->where(['openid_xcx '=>$openid])->find();
             if(!$user){
                $result['user_id'] ='';
                return formt($result,200,'user_id不存在');
              }else{
                $result['user_id'] = $user['user_id'];
                $result['token'] =$user['token'];
                return formt($result,200,'登录成功');
              }
         }else{
            $result['user_id'] ='';
            return formt($result,200,'user_id为空');
         }

    }

    public function check_unionid(){
        $rawData = input("rawData", '', 'htmlspecialchars_decode');
        $signature = input("signature", '', 'htmlspecialchars_decode');
        $encryptedData = input("encryptedData", '', 'htmlspecialchars_decode');
        $session3rd    = I('post.session3rd');
        $iv = input("iv", '', 'htmlspecialchars_decode');

        //判断session3rd是否过期
        if (!S($session3rd)) {
            return formt('',201,'session3rd不存在');
        }
        $session3rd = S($session3rd);
        $signature2 = sha1($rawData . $session3rd['session_key']);
        if ($signature2 !== $signature) {
            return formt($userinfo,201,'微信数据签名校验错误,用户信息错误');
        }
        
        $pc = new WXBizDataCrypt($this->config['appid'], $session3rd['session_key']);
        $errCode = $pc->decryptData($encryptedData, $iv, $data );
        
        if ($errCode !== 0) {
            return formt($userinfo,201,'解密信息错误,用户信息错误');
        }
        $arr = json_decode($data, true);
        $obj['openid_xcx'] = $arr['openId'];
        $obj['unionid'] = $arr['unionId'];
        $result_info = M('users')->where(['unionid'=>$arr['unionId']])->find();
        if($result_info){
                $result['token'] = $result_info['token'];
                $result['user_id'] = $result_info['user_id'];
                return formt($result,200,'成功');
        }else{
                $result['unionid'] ='';
                return formt($result,200,'unionid为空');
        }

    }



    public function checkLogin() {
        /**
         * 4.server调用微信提供的jsoncode2session接口获取openid, session_key, 调用失败应给予客户端反馈
         * , 微信侧返回错误则可判断为恶意请求, 可以不返回. 微信文档链接
         * 这是一个 HTTP 接口，开发者服务器使用登录凭证 code 获取 session_key 和 openid。其中 session_key 是对用户数据进行加密签名的密钥。
         * 为了自身应用安全，session_key 不应该在网络上传输。
         * 接口地址："https://api.weixin.qq.com/sns/jscode2session?appid=APPID&secret=SECRET&js_code=JSCODE&grant_type=authorization_code"
         */
        // $code = input("code", '', 'htmlspecialchars_decode');
        $rawData = input("rawData", '', 'htmlspecialchars_decode');
        $signature = input("signature", '', 'htmlspecialchars_decode');
        $encryptedData = input("encryptedData", '', 'htmlspecialchars_decode');
        $session3rd    = I('post.session3rd');
        $iv = input("iv", '', 'htmlspecialchars_decode');

        //判断session3rd是否过期
        if (!S($session3rd)) {
            return formt('',201,'session3rd不存在');
        }

        //要绑定的用户信息
        $mobile = input("mobile", '', 'htmlspecialchars_decode');
        $password = input("password", '', 'htmlspecialchars_decode');
        $md5password = md5(C("AUTH_CODE").$password);
        if(empty($mobile)){
            return formt('0',201,'手机号码错误');
        }
        if(empty($password)){
            return formt('0',201,'密码错误');
        }
        $where['mobile'] = $mobile;
        $where['password'] = $md5password;
        $userinfo = M('users')->where($where)->find();
        if(!$userinfo){
            return formt($userinfo,201,'绑定失败,用户信息错误');
        }
        // $params = [
        //     'appid' => $this->config['appid'],
        //     'secret' => $this->config['secret'],
        //     'js_code' => $code,
        //     'grant_type' => $this->config['grant_type']
        // ];

        // $res = $this->makeRequest($this->config['url'], $params);
      
        // if ($res['code'] !== 200 || !isset($res['result']) || !isset($res['result'])) {
        //     return ['code'=>ErrorCode::$RequestTokenFailed, 'message'=>'请求Token失败'];
        // }
        // $reqData = json_decode($res['result'], true);
        
        // if (!isset($reqData['session_key'])) {
        //     return ['code'=>ErrorCode::$RequestTokenFailed, 'message'=>'请求Token失败'];
        // }
       
        //$sessionKey = $reqData['session_key'];
        /**
         * 5.server计算signature, 并与小程序传入的signature比较, 校验signature的合法性, 不匹配则返回signature不匹配的错误. 不匹配的场景可判断为恶意请求, 可以不返回.
         * 通过调用接口（如 wx.getUserInfo）获取敏感数据时，接口会同时返回 rawData、signature，其中 signature = sha1( rawData + session_key )
         *
         * 将 signature、rawData、以及用户登录态发送给开发者服务器，开发者在数据库中找到该用户对应的 session-key
         * ，使用相同的算法计算出签名 signature2 ，比对 signature 与 signature2 即可校验数据的可信度。
         */
             //数据签名校验
        $session3rd = S($session3rd);
        $signature2 = sha1($rawData . $session3rd['session_key']);
        if ($signature2 !== $signature) {
            return formt($userinfo,201,'微信数据签名校验错误,用户信息错误');
        }
        /**
         *
         * 6.使用第4步返回的session_key解密encryptData, 将解得的信息与rawData中信息进行比较, 需要完全匹配,
         * 解得的信息中也包括openid, 也需要与第4步返回的openid匹配. 解密失败或不匹配应该返回客户相应错误.
         * （使用官方提供的方法即可）
         */
        $pc = new WXBizDataCrypt($this->config['appid'], $session3rd['session_key']);
        $errCode = $pc->decryptData($encryptedData, $iv, $data );
        
        if ($errCode !== 0) {
            return formt($userinfo,201,'解密信息错误,用户信息错误');
        }

        /**
         * 7.生成第三方3rd_session，用于第三方服务器和小程序之间做登录态校验。为了保证安全性，3rd_session应该满足：
         * a.长度足够长。建议有2^128种组合，即长度为16B
         * b.避免使用srand（当前时间）然后rand()的方法，而是采用操作系统提供的真正随机数机制，比如Linux下面读取/dev/urandom设备
         * c.设置一定有效时间，对于过期的3rd_session视为不合法
         *
         * 以 $session3rd 为key，sessionKey+openId为value，写入memcached
         */
        $arr = json_decode($data, true);
        $obj['openid_xcx'] = $arr['openId'];
        $obj['unionid'] = $arr['unionId'];
        $bangding = M('users')->where(['user_id'=>$userinfo['user_id']])->save($obj);
        if($bangding){
            // $r_user = M('users')->where('user_id'=>$userinfo['user_id'])->field('user_id,token')->find();
            $result['user_id'] = $userinfo['user_id'];
            $result['token'] = $userinfo['token'];
            //    $session3rd = $this->randomFromDev(16);
            //    $data['session3rd'] = $session3rd;
            //    cache($session3rd, $data['openId'] . $sessionKey);
            return formt($result,200,'绑定成功');
        }else{
            return formt('',201,'绑定失败');
        }
        
    }


    /**
     * 发起http请求
     * @param string $url 访问路径
     * @param array $params 参数，该数组多于1个，表示为POST
     * @param int $expire 请求超时时间
     * @param array $extend 请求伪造包头参数
     * @param string $hostIp HOST的地址
     * @return array    返回的为一个请求状态，一个内容
     */
    public function makeRequest($url, $params = array(), $expire = 0, $extend = array(), $hostIp = '')
    {
        if (empty($url)) {
            return array('code' => '100');
        }

        $_curl = curl_init();
        $_header = array(
            'Accept-Language: zh-CN',
            'Connection: Keep-Alive',
            'Cache-Control: no-cache'
        );
        // 方便直接访问要设置host的地址
        if (!empty($hostIp)) {
            $urlInfo = parse_url($url);
            if (empty($urlInfo['host'])) {
                $urlInfo['host'] = substr(DOMAIN, 7, -1);
                $url = "http://{$hostIp}{$url}";
            } else {
                $url = str_replace($urlInfo['host'], $hostIp, $url);
            }
            $_header[] = "Host: {$urlInfo['host']}";
        }

        // 只要第二个参数传了值之后，就是POST的
        if (!empty($params)) {
            curl_setopt($_curl, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($_curl, CURLOPT_POST, true);
        }

        if (substr($url, 0, 8) == 'https://') {
            curl_setopt($_curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($_curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        }
        curl_setopt($_curl, CURLOPT_URL, $url);
        curl_setopt($_curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($_curl, CURLOPT_USERAGENT, 'API PHP CURL');
        curl_setopt($_curl, CURLOPT_HTTPHEADER, $_header);

        if ($expire > 0) {
            curl_setopt($_curl, CURLOPT_TIMEOUT, $expire); // 处理超时时间
            curl_setopt($_curl, CURLOPT_CONNECTTIMEOUT, $expire); // 建立连接超时时间
        }

        // 额外的配置
        if (!empty($extend)) {
            curl_setopt_array($_curl, $extend);
        }

        $result['result'] = curl_exec($_curl);
        $result['code'] = curl_getinfo($_curl, CURLINFO_HTTP_CODE);
        $result['info'] = curl_getinfo($_curl);
        if ($result['result'] === false) {
            $result['result'] = curl_error($_curl);
            $result['code'] = -curl_errno($_curl);
        }

        curl_close($_curl);
        return $result;
    }

    /**
     * 读取/dev/urandom获取随机数
     * @param $len
     * @return mixed|string
     */
    public function randomFromDev($len) {
        $fp = @fopen('/dev/urandom','rb');
        $result = '';
        if ($fp !== FALSE) {
            $result .= @fread($fp, $len);
            @fclose($fp);
        }
        else
        {
            trigger_error('Can not open /dev/urandom.');
        }
        // convert from binary to string
        $result = base64_encode($result);
        // remove none url chars
        $result = strtr($result, '+/', '-_');

        return substr($result, 0, $len);
    }
}