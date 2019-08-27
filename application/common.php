<?php
/**
 * tpshop
 * ============================================================================
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * 为兼容以前的Thinkphp3.2老用户习惯, 用TP5助手函数实现 M( ) D( ) U( ) S( )等单字母函数
 */
use think\Db;
use app\admin\logic\StaffLogic;
#use think\Cache;
/*use app\home\model\Users;
use app\home\model\Recharge;
use app\home\model\PreviousLog;
use app\home\model\AccountLog;
*/


/**
 * tpshop检验登陆
 * @param
 * @return bool
 */
function is_login(){
    if(isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0){
        return $_SESSION['admin_id'];
    }else{
        return false;
    }
}
/**
 * 获取用户信息
 * @param $user_id_or_name  用户id 邮箱 手机 第三方id
 * @param int $type  类型 0 user_id查找 1 邮箱查找 2 手机查找 3 第三方唯一标识查找
 * @param string $oauth  第三方来源
 * @return mixed
 */
function get_user_info($user_id_or_name, $type = 0, $oauth = '')
{
   
    $map = array();
    if ($type == 0)
        $map['user_id'] = $user_id_or_name;
    if ($type == 1)
        $map['email'] = $user_id_or_name;
    if ($type == 2)
        $map['mobile'] = $user_id_or_name;
    
    if ($type == 3 || $type == 4) {
            //获取用户信息
            $column = ($type ==3) ? 'openid' : 'unionid';
            $thirdUser = M('OauthUsers')->where([$column=>$user_id_or_name, 'oauth'=>$oauth])->find();
            $map['user_id'] = $thirdUser['user_id'];
     }    
    $user = M('users')->where($map)->find();
    return $user;
}

/**
 * 更新会员等级,折扣，消费总额
 * @param $user_id  用户ID
 * @return boolean
 */
function update_user_level($user_id){
    $level_info = M('user_level')->order('level_id')->select();
    $total_amount = M('order')->where("user_id=:user_id AND pay_status=1 and order_status not in (3,5)")->bind(['user_id'=>$user_id])->sum('order_amount+user_money');
    if($level_info){
        foreach($level_info as $k=>$v){
            if($total_amount >= $v['amount']){
                $level = $level_info[$k]['level_id'];
                $discount = $level_info[$k]['discount']/100;
            }
        }
        $user = session('user');
        $updata['total_amount'] = $total_amount;//更新累计修复额度
        //累计额度达到新等级，更新会员折扣
        if(isset($level) && $level>$user['level']){
            $updata['level']    = $level;
            $updata['discount'] = $discount;
        }
        M('users')->where("user_id", $user_id)->save($updata);
    }
}


/**
 * 更新会员等级,折扣，消费总额
 * @param $user_id  用户ID
 * @return boolean
 */
function update_user_level_red($user_id){
    $level_info = M('user_level')->order('level_id')->select();
    $total_amount = M('order_red')->where("user_id=:user_id AND pay_status=1 and order_status not in (3,5)")->bind(['user_id'=>$user_id])->sum('order_amount+user_money');
    if($level_info){
        foreach($level_info as $k=>$v){
            if($total_amount >= $v['amount']){
                $level = $level_info[$k]['level_id'];
                $discount = $level_info[$k]['discount']/100;
            }
        }
        $user = session('user');
        $updata['total_amount'] = $total_amount;//更新累计修复额度
        //累计额度达到新等级，更新会员折扣
        if(isset($level) && $level>$user['level']){
            $updata['level'] = $level;
            $updata['discount'] = $discount;
        }
        M('users')->where("user_id", $user_id)->save($updata);
    }
}

/**
 * 更新会员等级,折扣，消费总额
 * @param $user_id  用户ID
 * @return boolean
 */
function update_user_level_yxyp($user_id){
    $level_info = M('user_level')->order('level_id')->select();
    $total_amount = M('order_yxyp')->where("user_id=:user_id AND pay_status=1 and order_status not in (3,5)")->bind(['user_id'=>$user_id])->sum('order_amount+user_money');
    if($level_info){
        foreach($level_info as $k=>$v){
            if($total_amount >= $v['amount']){
                $level = $level_info[$k]['level_id'];
                $discount = $level_info[$k]['discount']/100;
            }
        }
        $user = session('user');
        $updata['total_amount'] = $total_amount;//更新累计修复额度
        //累计额度达到新等级，更新会员折扣
        if(isset($level) && $level>$user['level']){
            $updata['level'] = $level;
            $updata['discount'] = $discount;
        }
        M('users')->where("user_id", $user_id)->save($updata);
    }
}
/**
 *  商品缩略图 给于标签调用 拿出商品表的 original_img 原始图来裁切出来的
 * @param type $goods_id  商品id
 * @param type $width     生成缩略图的宽度
 * @param type $height    生成缩略图的高度
 */
function goods_thum_images($goods_id, $width, $height, $type='')
{

    if (empty($goods_id)) return '';
    //判断缩略图是否存在
    if($type == 'red'){
        $them = 'GoodsRed';
        $path = "public/upload/goods_red/thumb/$goods_id/";
        $goods_thumb_name = "goods_red_thumb_{$goods_id}_{$width}_{$height}";
    } else {
        $them = 'Goods';
        $path = "public/upload/goods/thumb/$goods_id/";
        $goods_thumb_name = "goods_thumb_{$goods_id}_{$width}_{$height}";
    }

    // 这个商品 已经生成过这个比例的图片就直接返回了
    /*if (is_file($path . $goods_thumb_name . '.jpg')) return '/' . $path . $goods_thumb_name . '.jpg';
    if (is_file($path . $goods_thumb_name . '.jpeg')) return '/' . $path . $goods_thumb_name . '.jpeg';
    if (is_file($path . $goods_thumb_name . '.gif')) return '/' . $path . $goods_thumb_name . '.gif';
    if (is_file($path . $goods_thumb_name . '.png')) return '/' . $path . $goods_thumb_name . '.png';*/

    $original_imgs = M($them)->cache(true, 3600)->where("goods_id", $goods_id)->getField('original_img');

    if (empty($original_imgs)) {
        return '/public/images/icon_goods_thumb_empty_300.png';
    }


    $original_path = dirname($original_imgs); //获取图片存储的路径
    $original_img = '.' . $original_imgs; // 相对路径

    $urlimg = config('qiniu.DOMAIN').$original_imgs;
    return $urlimg."?imageView2/1/w/{$width}/h/{$height}";
  
    /*if (!is_file($original_img)) {
        if($_SERVER['HTTP_HOST'] != 'www.midoushu.com' && $_SERVER['HTTP_HOST'] != 'midoushu.com'){
            return '/public/images/icon_goods_thumb_empty_300.png';
        }
        $urlimg = config('qiniu.DOMAIN').$original_imgs;

        $result = getcurl($urlimg);
        $result = json_decode($result,true);
        //判断是否有远程图片
        if(!isset($result['error'])){
            download($urlimg,$original_path);
        }else{
            return '/public/images/icon_goods_thumb_empty_300.png';
        }
    }

    try {
        vendor('topthink.think-image.src.Image');
        if(strstr(strtolower($original_img),'.gif'))
        {
                vendor('topthink.think-image.src.image.gif.Encoder');
                vendor('topthink.think-image.src.image.gif.Decoder');
                vendor('topthink.think-image.src.image.gif.Gif');				
        }	        
        $image = \think\Image::open($original_img);

        $goods_thumb_name = $goods_thumb_name . '.' . $image->type();
        // 生成缩略图
        !is_dir($path) && mkdir($path, 0777, true);
        // 参考文章 http://www.mb5u.com/biancheng/php/php_84533.html  改动参考 http://www.thinkphp.cn/topic/13542.html
        $image->thumb($width, $height, 2)->save($path . $goods_thumb_name, NULL, 100); //按照原图的比例生成一个最大为$width*$height的缩略图并保存
        //图片水印处理
        $water = tpCache('water');
        if ($water['is_mark'] == 1) {
            $imgresource = './' . $path . $goods_thumb_name;
            if ($width > $water['mark_width'] && $height > $water['mark_height']) {
                if ($water['mark_type'] == 'img') {
                    //检查水印图片是否存在
                    $waterPath = "." . $water['mark_img'];
                    if (is_file($waterPath)) {
                        $quality = $water['mark_quality'] ?: 80;
                        $waterTempPath = dirname($waterPath).'/temp_'.basename($waterPath);
                        $image->open($waterPath)->save($waterTempPath, null, $quality);
                        $image->open($imgresource)->water($waterTempPath, $water['sel'], $water['mark_degree'])->save($imgresource);
                        @unlink($waterTempPath);
                    }
                } else {
                    //检查字体文件是否存在,注意是否有字体文件
                    $ttf = './hgzb.ttf';
                    if (file_exists($ttf)) {
                        $size = $water['mark_txt_size'] ?: 30;
                        $color = $water['mark_txt_color'] ?: '#000000';
                        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                            $color = '#000000';
                        }
                        $transparency = intval((100 - $water['mark_degree']) * (127/100));
                        $color .= dechex($transparency);
                        $image->open($imgresource)->text($water['mark_txt'], $ttf, $size, $color, $water['sel'])->save($imgresource);
                    }
                }
            }
        }
        $img_url = '/' . $path . $goods_thumb_name;
        return $img_url;
    } catch (think\Exception $e) {
        return $original_img;
    }*/
}

function get_qiniu_imgsurl($imgurl,$width=0,$height=0){

    $urlimg = config('qiniu.DOMAIN').$imgurl;

    $result = getcurl($urlimg);
    $result = json_decode($result,true);

    //判断是否有远程图片
    if(!isset($result['error'])){
        $return_imgurl = $urlimg;
    }else{
        $res = qiniuupload($imgurl);
        if($res['err'] == 0){
            $return_imgurl = $res['data'];
        }else {
            $return_imgurl = $imgurl;
        }
    }

    if($width == 0 || $height == 0){
        return $return_imgurl;
    }else{
        return $return_imgurl."?imageView2/1/w/{$width}/h/{$height}";
    }
}

/**
 * 图片上传至七牛云
 * @return String 图片的完整URL
 */
function qiniuupload($filePath)
{

    vendor('Qiniu.Auth');
    vendor('Qiniu.Storage.UploadManager');
    vendor('Qiniu.Storage.FormUploader');


    // 需要填写你的 Access Key 和 Secret Key
    $accessKey = config('qiniu.ACCESSKEY');
    $secretKey = config('qiniu.SECRETKEY');

    // 构建鉴权对象
    $auth = new \Qiniu\Auth($accessKey, $secretKey);
    // 要上传的空间
    $bucket = config('qiniu.BUCKET');
    $domain = config('qiniu.DOMAIN');
    $token = $auth->uploadToken($bucket);
    // 初始化 UploadManager 对象并进行文件的上传

    $uploadMgr = new \Qiniu\Storage\UploadManager();

    // 调用 UploadManager 的 putFile 方法进行文件的上传
    list($ret, $err) = $uploadMgr->putFile($token, substr($filePath,1), ".".$filePath);
    //file_put_contents('upload.txt',$ret['key']);
    if ($err !== null) {
        return ["err"=>1,"msg"=>$err,"data"=>""];
    } else {
        //返回图片的完整URL
        return ["err"=>0,"msg"=>"上传完成","data"=>($domain ."/". $ret['key'])];
    }
}

function getcurl($imgurl){
    //初始化
    $curl = curl_init();
    //设置抓取的url
    curl_setopt($curl, CURLOPT_URL, $imgurl);
    //设置头文件的信息作为数据流输出
    curl_setopt($curl, CURLOPT_HEADER, 0);
    //设置获取的信息以文件流的形式返回，而不是直接输出。
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
    //执行命令
    $data = curl_exec($curl);
    //关闭URL请求
    curl_close($curl);
    //显示获得的数据
    return $data;
}

/*
 * [下载远程图片]
 *@$url string 远程图片地址
 *@$path string 目录，可选 ，默认当前目录（相对路径）
 */
function download($url, $path = '')
{
//    $ch = curl_init();
//    curl_setopt($ch, CURLOPT_URL, $url);
//    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
//    $file = curl_exec($ch);
//    curl_close($ch);
    $file = getcurl($url);
    $filename = pathinfo($url, PATHINFO_BASENAME);
    $dir_path = '.'.$path;
    !is_dir($dir_path) && mkdir($dir_path, 0777, true);
  //  echo ".".$path ."/". $filename, 'a';die;
    $resource = fopen(".".$path ."/". $filename, 'a');
    fwrite($resource, $file);
    fclose($resource);
}

/**
 *  文章缩略图
 * @param type $article_id  文章id
 * @param type $width       生成缩略图的宽度
 * @param type $height      生成缩略图的高度
 */
function article_thum_images($article_id, $width, $height)
{
    if (empty($article_id)) return '';
    
    //判断缩略图是否存在
    $path = "public/upload/article/thumb/$article_id/";
    $article_thumb_name = "article_thumb_{$article_id}_{$width}_{$height}";

    // 这个商品 已经生成过这个比例的图片就直接返回了
    if (is_file($path . $article_thumb_name . '.jpg')) return '/' . $path . $article_thumb_name . '.jpg';
    if (is_file($path . $article_thumb_name . '.jpeg')) return '/' . $path . $article_thumb_name . '.jpeg';
    if (is_file($path . $article_thumb_name . '.gif')) return '/' . $path . $article_thumb_name . '.gif';
    if (is_file($path . $article_thumb_name . '.png')) return '/' . $path . $article_thumb_name . '.png';

    $original_img = M('article')->cache(true, 3600)->where("article_id", $article_id)->getField('thumb');
    if (empty($original_img)) {
        return '/public/images/icon_article_thumb_empty_300.png';
    }
    
    $ossClient = new \app\common\logic\OssLogic;
    if (($ossUrl = $ossClient->getArticleThumbImageUrl($original_img, $width, $height))) {
        return $ossUrl;
    }

    $original_img = '.' . $original_img; // 相对路径
    if (!is_file($original_img)) {
        return '/public/images/icon_article_thumb_empty_300.png';
    }

    try {
        vendor('topthink.think-image.src.Image');
        if(strstr(strtolower($original_img),'.gif'))
        {
                vendor('topthink.think-image.src.image.gif.Encoder');
                vendor('topthink.think-image.src.image.gif.Decoder');
                vendor('topthink.think-image.src.image.gif.Gif');               
        }           
        $image = \think\Image::open($original_img);

        $goods_thumb_name = $goods_thumb_name . '.' . $image->type();
        // 生成缩略图
        !is_dir($path) && mkdir($path, 0777, true);
        // 参考文章 http://www.mb5u.com/biancheng/php/php_84533.html  改动参考 http://www.thinkphp.cn/topic/13542.html
        $image->thumb($width, $height, 2)->save($path . $goods_thumb_name, NULL, 100); //按照原图的比例生成一个最大为$width*$height的缩略图并保存
        //图片水印处理
        $water = tpCache('water');
        if ($water['is_mark'] == 1) {
            $imgresource = './' . $path . $goods_thumb_name;
            if ($width > $water['mark_width'] && $height > $water['mark_height']) {
                if ($water['mark_type'] == 'img') {
                    //检查水印图片是否存在
                    $waterPath = "." . $water['mark_img'];
                    if (is_file($waterPath)) {
                        $quality = $water['mark_quality'] ?: 80;
                        $waterTempPath = dirname($waterPath).'/temp_'.basename($waterPath);
                        $image->open($waterPath)->save($waterTempPath, null, $quality);
                        $image->open($imgresource)->water($waterTempPath, $water['sel'], $water['mark_degree'])->save($imgresource);
                        @unlink($waterTempPath);
                    }
                } else {
                    //检查字体文件是否存在,注意是否有字体文件
                    $ttf = './hgzb.ttf';
                    if (file_exists($ttf)) {
                        $size = $water['mark_txt_size'] ?: 30;
                        $color = $water['mark_txt_color'] ?: '#000000';
                        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                            $color = '#000000';
                        }
                        $transparency = intval((100 - $water['mark_degree']) * (127/100));
                        $color .= dechex($transparency);
                        $image->open($imgresource)->text($water['mark_txt'], $ttf, $size, $color, $water['sel'])->save($imgresource);
                    }
                }
            }
        }
        $img_url = '/' . $path . $goods_thumb_name;

        return $img_url;
    } catch (think\Exception $e) {

        return $original_img;
    }
}


/**
 * 商品相册缩略图
 */
function get_sub_images($sub_img, $goods_id, $width, $height, $type="")
{
    //判断缩略图是否存在
    if($type == 'red'){
        $path = "public/upload/goods_red/thumb/$goods_id/";
    } else {
        $path = "public/upload/goods/thumb/$goods_id/";
    } 

    $goods_thumb_name = "goods_sub_thumb_{$sub_img['img_id']}_{$width}_{$height}";
    
    //这个缩略图 已经生成过这个比例的图片就直接返回了
    if (is_file($path . $goods_thumb_name . '.jpg')) return '/' . $path . $goods_thumb_name . '.jpg';
    if (is_file($path . $goods_thumb_name . '.jpeg')) return '/' . $path . $goods_thumb_name . '.jpeg';
    if (is_file($path . $goods_thumb_name . '.gif')) return '/' . $path . $goods_thumb_name . '.gif';
    if (is_file($path . $goods_thumb_name . '.png')) return '/' . $path . $goods_thumb_name . '.png';

    $ossClient = new \app\common\logic\OssLogic;
    if (($ossUrl = $ossClient->getGoodsAlbumThumbUrl($sub_img['image_url'], $width, $height))) {
        return $ossUrl;
    }
    
    $original_img = '.' . $sub_img['image_url']; //相对路径
    if (!is_file($original_img)) {
        return '/public/images/icon_goods_thumb_empty_300.png';
    }

    try {
        vendor('topthink.think-image.src.Image');
        if(strstr(strtolower($original_img),'.gif'))
        {
            vendor('topthink.think-image.src.image.gif.Encoder');
            vendor('topthink.think-image.src.image.gif.Decoder');
            vendor('topthink.think-image.src.image.gif.Gif');
        }
        $image = \think\Image::open($original_img);

        $goods_thumb_name = $goods_thumb_name . '.' . $image->type();
        // 生成缩略图
        !is_dir($path) && mkdir($path, 0777, true);
        // 参考文章 http://www.mb5u.com/biancheng/php/php_84533.html  改动参考 http://www.thinkphp.cn/topic/13542.html
        $image->thumb($width, $height, 2)->save($path . $goods_thumb_name, NULL, 100); //按照原图的比例生成一个最大为$width*$height的缩略图并保存
        //图片水印处理
        $water = tpCache('water');
        if ($water['is_mark'] == 1) {
            $imgresource = './' . $path . $goods_thumb_name;
            if ($width > $water['mark_width'] && $height > $water['mark_height']) {
                if ($water['mark_type'] == 'img') {
                    //检查水印图片是否存在
                    $waterPath = "." . $water['mark_img'];
                    if (is_file($waterPath)) {
                        $quality = $water['mark_quality'] ?: 80;
                        $waterTempPath = dirname($waterPath).'/temp_'.basename($waterPath);
                        $image->open($waterPath)->save($waterTempPath, null, $quality);
                        $image->open($imgresource)->water($waterTempPath, $water['sel'], $water['mark_degree'])->save($imgresource);
                        @unlink($waterTempPath);
                    }
                } else {
                    //检查字体文件是否存在,注意是否有字体文件
                    $ttf = './hgzb.ttf';
                    if (file_exists($ttf)) {
                        $size = $water['mark_txt_size'] ?: 30;
                        $color = $water['mark_txt_color'] ?: '#000000';
                        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                            $color = '#000000';
                        }
                        $transparency = intval((100 - $water['mark_degree']) * (127/100));
                        $color .= dechex($transparency);
                        $image->open($imgresource)->text($water['mark_txt'], $ttf, $size, $color, $water['sel'])->save($imgresource);
                    }
                }
            }
        }
        $img_url = '/' . $path . $goods_thumb_name;
        return $img_url;
    } catch (think\Exception $e) {
        return $original_img;
    }
}

/**
 * 刷新商品库存, 如果商品有设置规格库存, 则商品总库存 等于 所有规格库存相加
 * @param type $goods_id  商品id
 */
function refresh_stock($goods_id){
    $count = M("SpecGoodsPrice")->where("goods_id", $goods_id)->count();
    if($count == 0) return false; // 没有使用规格方式 没必要更改总库存
    $store_count = M("SpecGoodsPrice")->where("goods_id", $goods_id)->sum('store_count');
    M("Goods")->where("goods_id", $goods_id)->save(array('store_count'=>$store_count)); // 更新商品的总库存
}

/**
    红包商城
 * 刷新商品库存, 如果商品有设置规格库存, 则商品总库存 等于 所有规格库存相加
 * @param type $goods_id  商品id
 */
function refresh_stock_red($goods_id){
    $count = M("SpecRedGoodsPrice")->where("goods_id", $goods_id)->count();
    if($count == 0) return false; // 没有使用规格方式 没必要更改总库存
    $store_count = M("SpecRedGoodsPrice")->where("goods_id", $goods_id)->sum('store_count');
    M("GoodsRed")->where("goods_id", $goods_id)->save(array('store_count'=>$store_count)); // 更新商品的总库存
}
/**
    一乡一品
 * 刷新商品库存, 如果商品有设置规格库存, 则商品总库存 等于 所有规格库存相加
 * @param type $goods_id  商品id
 */
function refresh_stock_yxyp($goods_id){
    $count = M("SpecGoodsPrice")->where("goods_id", $goods_id)->count();
    if($count == 0) return false; // 没有使用规格方式 没必要更改总库存
    $store_count = M("SpecGoodsPrice")->where("goods_id", $goods_id)->sum('store_count');
    M("Goods")->where("goods_id", $goods_id)->save(array('store_count'=>$store_count)); // 更新商品的总库存
}

/**
 * 根据 order_goods 表扣除商品库存
 * @param $order|订单对象或者数组
 * @throws \think\Exception
 */
function minus_stock($order){
    $orderGoodsArr = M('OrderGoods')->where("order_id", $order['order_id'])->select();
    foreach($orderGoodsArr as $key => $val)
    {
        // 有选择规格的商品
        if(!empty($val['spec_key']))
        {   // 先到规格表里面扣除数量 再重新刷新一个 这件商品的总数量
            $SpecGoodsPrice = new \app\common\model\SpecGoodsPrice();
            $specGoodsPrice = $SpecGoodsPrice::get(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']]);

            if($specGoodsPrice['store_count'] < $val['goods_num']){
                $res['status']  =   0;
                $res['info']    =   $val['goods_name'].' '.$specGoodsPrice['key_name'] . '库存不足，只剩' . $specGoodsPrice['store_count'];;
                return $res; 
                exit();
            }
            $tkwhere['goods_id']  =   ['eq',$val['goods_id']];
            $tkwhere['key']   =   ['eq',$val['spec_key']];
            $tkwhere['store_count']   =   ['egt',$val['goods_num']];
            $r = M('spec_goods_price')->where($tkwhere)->setDec('store_count', $val['goods_num']);
            if($r == 0){
                $res['status']  =   0;
                $res['info']    =   $val['goods_name'].' '.$specGoodsPrice['key_name'] . '库存不足，只剩' . $specGoodsPrice['store_count'];;
                return $res; 
                exit();
            }
            refresh_stock($val['goods_id']);
        }else{
            $specGoodsPrice = null;
            $now_goods = M('Goods')->where("goods_id = {$val['goods_id']}")->field('store_count,goods_name')->find();
            if($now_goods['store_count'] < $val['goods_num']){
                $res['status']  =   0;
                $res['info']    =   $val['goods_name'] . '库存不足，只剩' . $now_goods['store_count'];
                return $res; 
                exit();
            }
            $tkwhere['goods_id']  =   ['eq',$val['goods_id']];
            $tkwhere['store_count']   =   ['egt',$val['goods_num']];
            $r = M('Goods')->where($tkwhere)->setDec('store_count',$val['goods_num']); // 直接扣除商品总数量
            if($r == 0){
                $res['status']  =   0;
                $res['info']    =   $val['goods_name'] . '库存不足，只剩' . $now_goods['store_count'];
                return $res; 
                exit();
            }
        }
        M('Goods')->where("goods_id", $val['goods_id'])->setInc('sales_sum',$val['goods_num']); // 增加商品销售量
        //更新活动商品购买量

        if ($val['prom_type'] == 1 || $val['prom_type'] == 2) {
            $GoodsPromFactory = new \app\common\logic\GoodsPromFactory();
            $goodsPromLogic = $GoodsPromFactory->makeModule($val, $specGoodsPrice);
            $prom = $goodsPromLogic->getPromModel();
            if ($prom['is_end'] == 0) {
                $tb = $val['prom_type'] == 1 ? 'flash_sale' : 'group_buy';
                M($tb)->where("id", $val['prom_id'])->setInc('buy_num', $val['goods_num']);
                M($tb)->where("id", $val['prom_id'])->setInc('order_num');
            }
        }
    }
    return ['status'=>1];
}

/**
 
 * 根据 order_red_goods 表扣除商品库存  (红包商城)
 * @param $order|订单对象或者数组
 * @throws \think\Exception
 */
function minus_stock_red($order){
    $orderGoodsArr = M('OrderRedGoods')->where("order_id", $order['order_id'])->select();
    foreach($orderGoodsArr as $key => $val)
    {
        // 有选择规格的商品
        if(!empty($val['spec_key']))
        {   // 先到规格表里面扣除数量 再重新刷新一个 这件商品的总数量
            $SpecGoodsPrice = new \app\common\model\SpecRedGoodsPrice();
            $specGoodsPrice = $SpecGoodsPrice::get(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']]);
            if($specGoodsPrice['store_count'] < $val['goods_num']){
                $res['status']  =   0;
                $res['info']    =   $val['goods_name'].' '.$specGoodsPrice['key_name'] . '库存不足，只剩' . $specGoodsPrice['store_count'];
                return $res; 
                exit();
            }
            $specGoodsPrice->where(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']])->setDec('store_count', $val['goods_num']);
            refresh_stock($val['goods_id']);
        }else{
            $specGoodsPrice = null;
            $now_goods = M('Goods_red')->where("goods_id = {$val['goods_id']}")->field('store_count,goods_name')->find();
            if($now_goods['store_count'] < $val['goods_num']){
                $res['status']  =   0;
                $res['info']    =   $val['goods_name'] . '库存不足，只剩' . $now_goods['store_count'];
                return $res; 
                exit();
            }
            M('GoodsRed')->where("goods_id", $val['goods_id'])->setDec('store_count',$val['goods_num']); // 直接扣除商品总数量
        }
        M('GoodsRed')->where("goods_id", $val['goods_id'])->setInc('sales_sum',$val['goods_num']); // 增加商品销售量
        //更新活动商品购买量
        if ($val['prom_type'] == 1 || $val['prom_type'] == 2) {
            $GoodsPromFactory = new \app\common\logic\RedGoodsPromFactory();
            $goodsPromLogic = $GoodsPromFactory->makeModule($val, $specGoodsPrice);
            $prom = $goodsPromLogic->getPromModel();
            if ($prom['is_end'] == 0) {
                $tb = $val['prom_type'] == 1 ? 'flash_sale' : 'group_buy';
                M($tb)->where("id", $val['prom_id'])->setInc('buy_num', $val['goods_num']);
                M($tb)->where("id", $val['prom_id'])->setInc('order_num');
            }
        }
    }
}

/**
 一乡一品
 * 根据 order_red_goods 表扣除商品库存  (红包商城)
 * @param $order|订单对象或者数组
 * @throws \think\Exception
 */
function minus_stock_yxyp($order){
    $orderGoodsArr = M('OrderYxypGoods')->where("order_id", $order['order_id'])->select();
    foreach($orderGoodsArr as $key => $val)
    {
        // 有选择规格的商品
        if(!empty($val['spec_key']))
        {   // 先到规格表里面扣除数量 再重新刷新一个 这件商品的总数量
            $SpecGoodsPrice = new \app\common\model\SpecGoodsPrice();
            $specGoodsPrice = $SpecGoodsPrice::get(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']]);
            if($specGoodsPrice['store_count'] < $val['goods_num']){
                $res['status']  =   0;
                $res['info']    =   $val['goods_name'].' '.$specGoodsPrice['key_name'] . '库存不足，只剩' . $specGoodsPrice['store_count'];
                return $res; 
                exit();
            }
            $specGoodsPrice->where(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']])->setDec('store_count', $val['goods_num']);
            refresh_stock($val['goods_id']);
        }else{
            $specGoodsPrice = null;
            $now_goods = M('Goods')->where("goods_id = {$val['goods_id']}")->field('store_count,goods_name')->find();
            if($now_goods['store_count'] < $val['goods_num']){
                $res['status']  =   0;
                $res['info']    =   $val['goods_name'] . '库存不足，只剩' . $now_goods['store_count'];
                return $res; 
                exit();
            }
            M('Goods')->where("goods_id", $val['goods_id'])->setDec('store_count',$val['goods_num']); // 直接扣除商品总数量
        }
        M('Goods')->where("goods_id", $val['goods_id'])->setInc('sales_sum',$val['goods_num']); // 增加商品销售量
        //更新活动商品购买量
        if ($val['prom_type'] == 1 || $val['prom_type'] == 2) {
            $GoodsPromFactory = new \app\common\logic\GoodsPromFactory();
            $goodsPromLogic = $GoodsPromFactory->makeModule($val, $specGoodsPrice);
            $prom = $goodsPromLogic->getPromModel();
            if ($prom['is_end'] == 0) {
                $tb = $val['prom_type'] == 1 ? 'flash_sale' : 'group_buy';
                M($tb)->where("id", $val['prom_id'])->setInc('buy_num', $val['goods_num']);
                M($tb)->where("id", $val['prom_id'])->setInc('order_num');
            }
        }
    }
}
/**
 * [根据 order_red_goods 表扣除商品库存  (红包商城 - 实体店)]
 * @author 王牧田
 * @date 2018-09-29
 * @param $order
 * @param $store_id
 * @return mixed
 * @throws \think\Exception
 */
function store_minus_stock_red($order,$store_id){
    $orderGoodsArr = M('OrderRedGoods')->where("order_id", $order['order_id'])->select();

    foreach($orderGoodsArr as $key => $val)
    {
        $store_goods_stock = db('store_goods_stock');
        // 有选择规格的商品
        if(!empty($val['spec_key']))
        {   // 先到规格表里面扣除数量 再重新刷新一个 这件商品的总数量
            $SpecGoodsPrice = new \app\common\model\SpecRedGoodsPrice();
            $specGoodsPrice = $SpecGoodsPrice::get(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']]);
            $stock = $store_goods_stock->where(['store_id'=>$store_id,'goods_id'=>$val['goods_id'],'item_id'=>$specGoodsPrice['item_id']])->value("stock");


            if($stock < $val['goods_num']){
                $res['status']  =   0;
                $res['info']    =   $val['goods_name'].' '.$specGoodsPrice['key_name'] . '库存不足，只剩' . $stock;
                return $res;
                exit();
            }
            $store_goods_stock->where(['store_id'=>$store_id,'goods_id'=>$val['goods_id'],'item_id'=>$specGoodsPrice['item_id']])->setDec('stock', $val['goods_num']);
        }else{
            $specGoodsPrice = null;
            $now_goods = M('Goods_red')->where("goods_id = {$val['goods_id']}")->field('store_count,goods_name')->find();
            $stock = $store_goods_stock->where(['store_id'=>$store_id,'goods_id'=>$val['goods_id'],'item_id'=>0])->value("stock");

            if($stock < $val['goods_num']){
                $res['status']  =   0;
                $res['info']    =   $val['goods_name'] . '库存不足，只剩' . $now_goods['store_count'];
                return $res;
                exit();
            }
            $store_goods_stock->where(['store_id'=>$store_id,'goods_id'=>$val['goods_id'],'item_id'=>0])->setDec('stock', $val['goods_num']);// 直接扣除商品总数量
        }
        M('GoodsRed')->where("goods_id", $val['goods_id'])->setInc('sales_sum',$val['goods_num']); // 增加商品销售量
    }
}
/**
*一乡一品
 * [根据 order_red_goods 表扣除商品库存  (红包商城 - 实体店)]
 * @author 王牧田
 * @date 2018-09-29
 * @param $order
 * @param $store_id
 * @return mixed
 * @throws \think\Exception
 */
function store_minus_stock_yxyp($order,$store_id){
    $orderGoodsArr = M('OrderYxypGoods')->where("order_id", $order['order_id'])->select();

    foreach($orderGoodsArr as $key => $val)
    {
        $store_goods_stock = db('store_goods_stock');
        // 有选择规格的商品
        if(!empty($val['spec_key']))
        {   // 先到规格表里面扣除数量 再重新刷新一个 这件商品的总数量
            $SpecGoodsPrice = new \app\common\model\SpecYxypGoodsPrice();
            $specGoodsPrice = $SpecGoodsPrice::get(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']]);
            $stock = $store_goods_stock->where(['store_id'=>$store_id,'goods_id'=>$val['goods_id'],'item_id'=>$specGoodsPrice['item_id']])->value("stock");
            if($stock < $val['goods_num']){
                $res['status']  =   0;
                $res['info']    =   $val['goods_name'].' '.$specGoodsPrice['key_name'] . '库存不足，只剩' . $stock;
                return $res;
                exit();
            }
            $store_goods_stock->where(['store_id'=>$store_id,'goods_id'=>$val['goods_id'],'item_id'=>$specGoodsPrice['item_id']])->setDec('stock', $val['goods_num']);
        }else{
            $specGoodsPrice = null;
            $now_goods = M('Goods_yxyp')->where("goods_id = {$val['goods_id']}")->field('store_count,goods_name')->find();
            $stock = $store_goods_stock->where(['store_id'=>$store_id,'goods_id'=>$val['goods_id'],'item_id'=>0])->value("stock");
            if($stock < $val['goods_num']){
                $res['status']  =   0;
                $res['info']    =   $val['goods_name'] . '库存不足，只剩' . $now_goods['store_count'];
                return $res;
                exit();
            }
            $store_goods_stock->where(['store_id'=>$store_id,'goods_id'=>$val['goods_id'],'item_id'=>0])->setDec('stock', $val['goods_num']);// 直接扣除商品总数量
        }
        M('GoodsYxyp')->where("goods_id", $val['goods_id'])->setInc('sales_sum',$val['goods_num']); // 增加商品销售量
    }
}

/**
 * 邮件发送
 * @param $to    接收人
 * @param string $subject   邮件标题
 * @param string $content   邮件内容(html模板渲染后的内容)
 * @throws Exception
 * @throws phpmailerException
 */
function send_email($to,$subject='',$content=''){
    vendor('phpmailer.PHPMailerAutoload'); ////require_once vendor/phpmailer/PHPMailerAutoload.php';
    //判断openssl是否开启
    $openssl_funcs = get_extension_funcs('openssl');
    if(!$openssl_funcs){
        return array('status'=>-1 , 'msg'=>'请先开启openssl扩展');
    }
    $mail = new PHPMailer;
    $config = tpCache('smtp');
    $mail->CharSet  = 'UTF-8'; //设定邮件编码，默认ISO-8859-1，如果发中文此项必须设置，否则乱码
    $mail->isSMTP();
    //Enable SMTP debugging
    // 0 = off (for production use)
    // 1 = client messages
    // 2 = client and server messages
    $mail->SMTPDebug = 0;
    //调试输出格式
    //$mail->Debugoutput = 'html';
    //smtp服务器
    $mail->Host = $config['smtp_server'];
    //端口 - likely to be 25, 465 or 587
    $mail->Port = $config['smtp_port'];

    if($mail->Port == 465) $mail->SMTPSecure = 'ssl';// 使用安全协议
    //Whether to use SMTP authentication
    $mail->SMTPAuth = true;
    //用户名
    $mail->Username = $config['smtp_user'];
    //密码
    $mail->Password = $config['smtp_pwd'];
    //Set who the message is to be sent from
    $mail->setFrom($config['smtp_user']);
    //回复地址
    //$mail->addReplyTo('replyto@example.com', 'First Last');
    //接收邮件方
    if(is_array($to)){
        foreach ($to as $v){
            $mail->addAddress($v);
        }
    }else{
        $mail->addAddress($to);
    }

    $mail->isHTML(true);// send as HTML
    //标题
    $mail->Subject = $subject;
    //HTML内容转换
    $mail->msgHTML($content);
    //Replace the plain text body with one created manually
    //$mail->AltBody = 'This is a plain-text message body';
    //添加附件
    //$mail->addAttachment('images/phpmailer_mini.png');
    //send the message, check for errors
    if (!$mail->send()) {
        return array('status'=>-1 , 'msg'=>'发送失败: '.$mail->ErrorInfo);
    } else {
        return array('status'=>1 , 'msg'=>'发送成功');
    }
}

/**
 * 检测是否能够发送短信
 * @param unknown $scene
 * @return multitype:number string
 */
function checkEnableSendSms($scene)
{

    $scenes = C('SEND_SCENE');
    $sceneItem = $scenes[$scene];
    if (!$sceneItem) {
        return array("status" => -1, "msg" => "场景参数'scene'错误!");
    }
    $key = $sceneItem[2];
    $sceneName = $sceneItem[0];
    $config = tpCache('sms');
    $smsEnable = $config[$key];

    if (!$smsEnable) {
        return array("status" => -1, "msg" => "['$sceneName']发送短信被关闭'");
    }
    //判断是否添加"注册模板"
    $size = M('sms_template')->where("send_scene", $scene)->count('tpl_id');

    if (!$size) {
        return array("status" => -1, "msg" => "请先添加['$sceneName']短信模板");
    }

    return array("status"=>1,"msg"=>"可以发送短信");
}

/**
 * 发送短信逻辑
 * @param unknown $scene
 */
function sendSms($scene, $sender, $params,$unique_id=0)
{
    $smsLogic = new \app\common\logic\SmsLogic;
    return $smsLogic->sendSms($scene, $sender, $params, $unique_id);
}

/**
 * 查询快递
 * @param $postcom  快递公司编码
 * @param $getNu  快递单号
 * @return array  物流跟踪信息数组
 */
function queryExpress($postcom , $getNu) {
    /*    $url = "http://wap.kuaidi100.com/wap_result.jsp?rand=".time()."&id={$postcom}&fromWeb=null&postid={$getNu}";
        //$resp = httpRequest($url,'GET');
        $resp = file_get_contents($url);
        if (empty($resp)) {
            return array('status'=>0, 'message'=>'物流公司网络异常，请稍后查询');
        }
        preg_match_all('/\\<p\\>&middot;(.*)\\<\\/p\\>/U', $resp, $arr);
        if (!isset($arr[1])) {
            return array( 'status'=>0, 'message'=>'查询失败，参数有误' );
        }else{
            foreach ($arr[1] as $key => $value) {
                $a = array();
                $a = explode('<br /> ', $value);
                $data[$key]['time'] = $a[0];
                $data[$key]['context'] = $a[1];
            }
            return array( 'status'=>1, 'message'=>'1','data'=> array_reverse($data));
        }*/
    $arr = explode('_', $postcom);
    $postcom = $arr[0];
    $url = "https://m.kuaidi100.com/query?type=".$postcom."&postid=".$getNu."&id=1&valicode=&temp=0.49738534969422676";
    $resp = httpRequest($url,"GET");
    return json_decode($resp,true);
}

/**
 * 获取某个商品分类的 儿子 孙子  重子重孙 的 id
 * @param type $cat_id
 */
function getCatGrandson ($cat_id,$category_table_name='GoodsCategory')
{
    $GLOBALS['catGrandson'] = array();
    $GLOBALS['category_id_arr'] = array();
    // 先把自己的id 保存起来
    $GLOBALS['catGrandson'][] = $cat_id;
    // 把整张表找出来
    $GLOBALS['category_id_arr'] = M($category_table_name)->cache(true,TPSHOP_CACHE_TIME)->getField('id,parent_id');
    // 先把所有儿子找出来
    $son_id_arr = M($category_table_name)->where(["parent_id"=>$cat_id])->cache(true,TPSHOP_CACHE_TIME)->getField('id',true);
    foreach($son_id_arr as $k => $v)
    {
        getCatGrandson2($v);
    }
    return $GLOBALS['catGrandson'];
}


/**
红包商城
 * 获取某个商品分类的 儿子 孙子  重子重孙 的 id
 * @param type $cat_id
 */
function getCatGrandsonRed ($cat_id)
{
    $GLOBALS['catGrandsonRed'] = array();
    $GLOBALS['category_id_arrRed'] = array();
    // 先把自己的id 保存起来
    $GLOBALS['catGrandsonRed'][] = $cat_id;
    // 把整张表找出来
    $GLOBALS['category_id_arrRed'] = M('GoodsRedCategory')->cache(true,TPSHOP_CACHE_TIME)->getField('id,parent_id');
    // 先把所有儿子找出来
    $son_id_arr = M('GoodsRedCategory')->where(["parent_id"=>$cat_id])->cache(true,TPSHOP_CACHE_TIME)->getField('id',true);
    foreach($son_id_arr as $k => $v)
    {
        getCatGrandson2Red($v);
    }
    return $GLOBALS['catGrandsonRed'];
}
/**
一乡一品
 * 获取某个商品分类的 儿子 孙子  重子重孙 的 id
 * @param type $cat_id
 */
function getCatGrandsonRYxyp ($cat_id)
{
    $GLOBALS['catGrandsonYxyp'] = array();
    $GLOBALS['category_id_arrYxyp'] = array();
    // 先把自己的id 保存起来
    $GLOBALS['catGrandsonYxyp'][] = $cat_id;
    // 把整张表找出来
    $GLOBALS['category_id_arrYxyp'] = M('GoodsYxypCategory')->cache(true,TPSHOP_CACHE_TIME)->getField('id,parent_id');
    // 先把所有儿子找出来
    $son_id_arr = M('GoodsYxypCategory')->where(["parent_id"=>$cat_id])->cache(true,TPSHOP_CACHE_TIME)->getField('id',true);
    foreach($son_id_arr as $k => $v)
    {
        getCatGrandson2Yxyp($v);
    }
    return $GLOBALS['catGrandsonYxyp'];
}
/**
 * 获取子公司 实体店 的 id
 * @param type $cat_id
 */
function get_Company_Store ($cid)
{
    $GLOBALS['get_Company_Store'] = array();
    $GLOBALS['get_Company_Store_id_arr'] = array();
    // 先把自己的id 保存起来
    $GLOBALS['get_Company_Store'][] = $cat_id;
    // 把整张表找出来
    $GLOBALS['get_Company_Store_id_arr'] = M('Company')->cache(true,TPSHOP_CACHE_TIME)->getField('cid,parent_id');
    // 先把所有儿子找出来
    $son_id_arr = M('Company')->where("parent_id", $cid)->cache(true,TPSHOP_CACHE_TIME)->getField('cid',true);
    foreach($son_id_arr as $k => $v)
    {
        get_Company_Store2($v);
    }
    return $GLOBALS['get_Company_Store'];
}

/**
 * 递归调用找到 重子重孙
 * @param type $cat_id
 */
function get_Company_Store2($cid)
{
    $GLOBALS['get_Company_Store'][] = $cid;
    foreach($GLOBALS['get_Company_Store_id_arr'] as $k => $v)
    {
        // 找到孙子
        if($v == $cid)
        {
            get_Company_Store2($k); // 继续找孙子
        }
    }
}

/**
 * 获取某个文章分类的 儿子 孙子  重子重孙 的 id
 * @param type $cat_id
 */
function getArticleCatGrandson ($cat_id)
{
    $GLOBALS['ArticleCatGrandson'] = array();
    $GLOBALS['cat_id_arr'] = array();
    // 先把自己的id 保存起来
    $GLOBALS['ArticleCatGrandson'][] = $cat_id;
    // 把整张表找出来
    $GLOBALS['cat_id_arr'] = M('ArticleCat')->getField('cat_id,parent_id');
    // 先把所有儿子找出来
    $son_id_arr = M('ArticleCat')->where("parent_id", $cat_id)->getField('cat_id',true);
    foreach($son_id_arr as $k => $v)
    {
        getArticleCatGrandson2($v);
    }
    return $GLOBALS['ArticleCatGrandson'];
}

/**
 * 递归调用找到 重子重孙
 * @param type $cat_id
 */
function getCatGrandson2($cat_id)
{
    $GLOBALS['catGrandson'][] = $cat_id;
    foreach($GLOBALS['category_id_arr'] as $k => $v)
    {
        // 找到孙子
        if($v == $cat_id)
        {
            getCatGrandson2($k); // 继续找孙子
        }
    }
}

/**
 * 递归调用找到 重子重孙
 * @param type $cat_id
 */
function getCatGrandson2Red($cat_id)
{
    $GLOBALS['catGrandsonRed'][] = $cat_id;
    foreach($GLOBALS['category_id_arrRed'] as $k => $v)
    {
        // 找到孙子
        if($v == $cat_id)
        {
            getCatGrandson2Red($k); // 继续找孙子
        }
    }
}

/**
*一乡一品
 * 递归调用找到 重子重孙
 * @param type $cat_id
 */
function getCatGrandson2Yxyp($cat_id)
{
    $GLOBALS['catGrandsonYxyp'][] = $cat_id;
    foreach($GLOBALS['category_id_arrYxyp'] as $k => $v)
    {
        // 找到孙子
        if($v == $cat_id)
        {
            getCatGrandson2Yxyp($k); // 继续找孙子
        }
    }
}
/**
 * 递归调用找到 重子重孙
 * @param type $cat_id
 */
function getArticleCatGrandson2($cat_id)
{
    $GLOBALS['ArticleCatGrandson'][] = $cat_id;
    foreach($GLOBALS['cat_id_arr'] as $k => $v)
    {
        // 找到孙子
        if($v == $cat_id)
        {
            getArticleCatGrandson2($k); // 继续找孙子
        }
    }
}

/**
 * 查看某个用户购物车中商品的数量
 * @param type $user_id
 * @param type $session_id
 * @return type 购买数量
 */
function cart_goods_num($user_id = 0,$session_id = '')
{
//    $where = " session_id = '$session_id' ";
//    $user_id && $where .= " or user_id = $user_id ";
    // 查找购物车数量
//    $cart_count =  M('Cart')->where($where)->sum('goods_num');
    $cart_count = Db::name('cart')->where(function ($query) use ($user_id, $session_id) {
        $query->where('session_id', $session_id);
        if ($user_id) {
            $query->whereOr('user_id', $user_id);
        }
    })->sum('goods_num');
    $cart_count = $cart_count ? $cart_count : 0;
    return $cart_count;
}

/**
 * 获取商品库存
 * @param type $goods_id 商品id
 * @param type $key  库存 key
 */
function getGoodNum($goods_id,$key)
{
     if (!empty($key)){
        return M("SpecGoodsPrice")
                        ->alias("s")
                        ->join('_Goods_ g ','s.goods_id = g.goods_id','LEFT')
                        ->where(['g.goods_id' => $goods_id, 'key' => $key ,"is_on_sale"=>1])->getField('s.store_count');
    }else{ 
        return M("Goods")->where(array("goods_id"=>$goods_id , "is_on_sale"=>1))->getField('store_count');
    }
}

/**
 * 获取商品库存
 * @param type $goods_id 商品id
 * @param type $key  库存 key
 */
function getGoodNumRed($goods_id,$key)
{
     if (!empty($key)){
        return M("SpecRedGoodsPrice")
                        ->alias("s")
                        ->join('_GoodsRed_ g ','s.goods_id = g.goods_id','LEFT')
                        ->where(['g.goods_id' => $goods_id, 'key' => $key ,"is_on_sale"=>1])->getField('s.store_count');
    }else{ 
        return M("GoodsRed")->where(array("goods_id"=>$goods_id , "is_on_sale"=>1))->getField('store_count');
    }
}

/**
*一乡一品
 * 获取商品库存
 * @param type $goods_id 商品id
 * @param type $key  库存 key
 */
function getGoodNumYxyp($goods_id,$key)
{
     if (!empty($key)){
        return M("SpecYxypGoodsPrice")
                        ->alias("s")
                        ->join('_GoodsYxyp_ g ','s.goods_id = g.goods_id','LEFT')
                        ->where(['g.goods_id' => $goods_id, 'key' => $key ,"is_on_sale"=>1])->getField('s.store_count');
    }else{ 
        return M("GoodsYxyp")->where(array("goods_id"=>$goods_id , "is_on_sale"=>1))->getField('store_count');
    }
}
/**
 * 获取缓存或者更新缓存
 * @param string $config_key 缓存文件名称
 * @param array $data 缓存数据  array('k1'=>'v1','k2'=>'v3')
 * @return array or string or bool
 */
function tpCache($config_key,$data = array()){
    $param = explode('.', $config_key);
    if(empty($data)){
        //如$config_key=shop_info则获取网站信息数组
        //如$config_key=shop_info.logo则获取网站logo字符串
        $config = F($param[0],'',TEMP_PATH);//直接获取缓存文件
        if(empty($config)){
            //缓存文件不存在就读取数据库
            $res = D('config')->where("inc_type",$param[0])->select();
            if($res){
                foreach($res as $k=>$val){
                    $config[$val['name']] = $val['value'];
                }
                F($param[0],$config,TEMP_PATH);
            }
        }
        if(count($param)>1){
            return $config[$param[1]];
        }else{
            return $config;
        }
    }else{
        //更新缓存
        $result =  D('config')->where("inc_type", $param[0])->select();
        if($result){
            foreach($result as $val){
                $temp[$val['name']] = $val['value'];
            }
            foreach ($data as $k=>$v){
                $newArr = array('name'=>$k,'value'=>trim($v),'inc_type'=>$param[0]);
                if(!isset($temp[$k])){
                    M('config')->add($newArr);//新key数据插入数据库
                }else{
                    if($v!=$temp[$k])
                        M('config')->where("name", $k)->save($newArr);//缓存key存在且值有变更新此项
                }
            }
            //更新后的数据库记录
            $newRes = D('config')->where("inc_type", $param[0])->select();
            foreach ($newRes as $rs){
                $newData[$rs['name']] = $rs['value'];
            }
        }else{
            foreach($data as $k=>$v){
                $newArr[] = array('name'=>$k,'value'=>trim($v),'inc_type'=>$param[0]);
            }
            M('config')->insertAll($newArr);
            $newData = $data;
        }
        return F($param[0],$newData,TEMP_PATH);
    }
}

/**
 * 记录会员帐户变动
 * @param   int     $user_id        用户id
 * @param   float   $user_money     可用余额变动
 * @param   int     $pay_points     消费积分变动
 * @param   string  $desc    变动说明
 * @param   float   distribut_money 分佣金额
 * @param int $order_id 订单id
 * @param string $order_sn 订单sn
 * @return  bool
 */
function accountLog($user_id, $user_money = 0, $pay_midou = 0, $pay_points = 0, $desc = '', $midou_all = 0, $distribut_money = 0, $order_id = 0, $order_sn = ''){
    /* 插入帐户变动记录 */
    $account_log = array(
        'user_id'       => $user_id,
        'user_money'    => $user_money,
        'midou'         => $pay_midou,
        'midou_all'     => $midou_all,
        'pay_points'    => $pay_points,
        'change_time'   => time(),
        'desc'          => $desc,
        'order_id'      => $order_id,
        'order_sn'      => $order_sn,
        'is_red'        => 1,
    );

    if($midou_all < 0) $midou_all = 0;
    /* 更新用户信息 */
    // $sql = "UPDATE __PREFIX__users SET user_money = user_money + $user_money," .
    // " pay_points = pay_points + $pay_points, distribut_money = distribut_money + $distribut_money WHERE user_id = $user_id";
    $update_data = array(
        'user_money'        => ['exp','user_money+'.$user_money],
        'midou'             => ['exp','midou+'.$pay_midou],
        'midou_all'         => ['exp','midou_all+'.$midou_all],
        'pay_points'        => ['exp','pay_points+'.$pay_points],
        'distribut_money'   => ['exp','distribut_money+'.$distribut_money],
        'midou_all'         => ['exp','midou_all+'.$midou_all],
    );

    if(($user_money+$pay_midou+$pay_points+$midou_all+$distribut_money) == 0) return false;
     
    $update = M('users')->where("user_id = {$user_id}")->update($update_data);

    if($update){
        M('account_log')->add($account_log);
        return true;
    }else{
        return false;
    }
}


/**
 * 记录供货商帐户变动
 * @param   int     $suppliers_id        用户id
 * @param   float   $suppliers_money     可用余额变动
 * @param   int     $pay_points          消费积分变动
 * @param   string  $desc                变动说明
 * @param   float   distribut_money      分佣金额
 * @param   int $order_id 订单id
 * @param   string $order_sn 订单sn
 * @return  bool
 */

function suppliers_accountLog($suppliers_id, $suppliers_money = 0,$pay_points = 0, $desc = '',$distribut_money = 0,$order_id = 0 ,$order_sn = ''){
    /* 插入帐户变动记录 */
    $account_log = array(
        'suppliers_id'    => $suppliers_id,
        'suppliers_money' => $suppliers_money,
        'pay_points'      => $pay_points,
        'change_time'     => time(),
        'desc'            => $desc,
        'order_id'        => $order_id,
        'order_sn'        => $order_sn
    );
    /* 更新用户信息 */
    $update_data = array(
        'suppliers_money' => ['exp','suppliers_money+'.$suppliers_money],
        'pay_points'      => ['exp','pay_points+'.$pay_points],
        'distribut_money' => ['exp','distribut_money+'.$distribut_money],
    );

    if(($suppliers_money+$pay_points+$distribut_money) == 0) return false;
    $update = Db::name('suppliers')->where('suppliers_id',$suppliers_id)->update($update_data);
    if($update){
        M('suppliers_account_log')->add($account_log);
        return true;
    }else{
        return false;
    }
}


/**
 * 订单操作日志
 * 参数示例
 * @param type $order_id  订单id
 * @param type $action_note 操作备注
 * @param type $status_desc 操作状态  提交订单, 付款成功, 取消, 等待收货, 完成
 * @param type $user_id  用户id 默认为管理员
 * @return boolean
 */
function logOrder($order_id,$action_note,$status_desc,$user_id = 0)
{
    $status_desc_arr = array('提交订单', '付款成功', '取消', '等待收货', '完成','退货');
    // if(!in_array($status_desc, $status_desc_arr))
    // return false;

    $order = M('order')->where("order_id", $order_id)->find();
    $action_info = array(
        'order_id'        =>$order_id,
        'action_user'     =>0,
        'order_status'    =>$order['order_status'],
        'shipping_status' =>$order['shipping_status'],
        'pay_status'      =>$order['pay_status'],
        'action_note'     => $action_note,
        'status_desc'     =>$status_desc, //''
        'log_time'        =>time(),
    );
    return M('order_action')->add($action_info);
}


/**
 * 红包商城 - 订单操作日志
    TK modify
 * 参数示例
 * @param type $order_id  订单id
 * @param type $action_note 操作备注
 * @param type $status_desc 操作状态  提交订单, 付款成功, 取消, 等待收货, 完成
 * @param type $user_id  用户id 默认为管理员
 * @return boolean
 */
function logOrderRed($order_id,$action_note,$status_desc,$user_id = 0)
{
    $status_desc_arr = array('提交订单', '付款成功', '取消', '等待收货', '完成','退货');
    // if(!in_array($status_desc, $status_desc_arr))
    // return false;

    $order = M('order_red')->where("order_id", $order_id)->find();
    $action_info = array(
        'order_id'        =>$order_id,
        'action_user'     =>0,
        'order_status'    =>$order['order_status'],
        'shipping_status' =>$order['shipping_status'],
        'pay_status'      =>$order['pay_status'],
        'action_note'     => $action_note,
        'status_desc'     =>$status_desc, //''
        'log_time'        =>time(),
    );
    return M('order_red_action')->add($action_info);
}

/**
 * 一乡一品 - 订单操作日志
    TK modify
 * 参数示例
 * @param type $order_id  订单id
 * @param type $action_note 操作备注
 * @param type $status_desc 操作状态  提交订单, 付款成功, 取消, 等待收货, 完成
 * @param type $user_id  用户id 默认为管理员
 * @return boolean
 */
function logOrderYxyp($order_id,$action_note,$status_desc,$user_id = 0)
{
    $status_desc_arr = array('提交订单', '付款成功', '取消', '等待收货', '完成','退货');
    // if(!in_array($status_desc, $status_desc_arr))
    // return false;

    $order = M('order_yxyp')->where("order_id", $order_id)->find();
    $action_info = array(
        'order_id'        =>$order_id,
        'action_user'     =>0,
        'order_status'    =>$order['order_status'],
        'shipping_status' =>$order['shipping_status'],
        'pay_status'      =>$order['pay_status'],
        'action_note'     => $action_note,
        'status_desc'     =>$status_desc, //''
        'log_time'        =>time(),
    );
    return M('order_yxyp_action')->add($action_info);
}

/*
 * 获取地区列表
 */
function get_region_list(){
    return M('region')->cache(true)->getField('id,name');
}
/*
 * 获取用户地址列表
 */
function get_user_address_list($user_id){
    $lists = M('user_address')->where(array('user_id'=>$user_id))->select();
    return $lists;
}
function wechat_get_region_list($id){
    $lists = M('region')->where(array('id'=>$id))->getField('name');
    return $lists;
}
/*
/*
 * 获取指定地址信息
 */
function get_user_address_info($user_id,$address_id){
    $data = M('user_address')->where(array('user_id'=>$user_id,'address_id'=>$address_id))->find();
    return $data;
}
/*
 * 获取用户默认收货地址
 */
function get_user_default_address($user_id){
    $data = M('user_address')->where(array('user_id'=>$user_id,'is_default'=>1))->find();
    return $data;
}
/**
 * 获取订单状态的 中文描述名称
 * @param type $order_id  订单id
 * @param type $order     订单数组
 * @return string
 */
function orderStatusDesc($order_id = 0, $order = array())
{
    if(empty($order))
        $order = M('Order')->where("order_id", $order_id)->find();

    // 货到付款
    if($order['pay_code'] == 'cod')
    {
        if(in_array($order['order_status'],array(0,1)) && $order['shipping_status'] == 0)
            return 'WAITSEND'; //'待发货',
    }
    else // 非货到付款
    {
        if($order['pay_status'] == 0 && $order['order_status'] == 0)
            return 'WAITPAY';  //'待支付',
        if($order['pay_status'] == 1 &&  in_array($order['order_status'],array(0,1)) && $order['shipping_status'] == 0)
            return 'WAITSEND'; //'待发货',
        if($order['pay_status'] == 1 &&  $order['shipping_status'] == 2 && $order['order_status'] == 1)
            return 'PORTIONSEND'; //'部分发货',
    }
    if(($order['shipping_status'] == 1) && ($order['order_status'] == 1))
        return 'WAITRECEIVE'; //'待收货',
    if($order['order_status'] == 2)
        return 'WAITCCOMMENT'; //'待评价',
    if($order['order_status'] == 3)
        return 'CANCEL'; //'已取消',
    if($order['order_status'] == 4)
        return 'FINISH'; //'已完成',
    if($order['order_status'] == 5)
        return 'CANCELLED'; //'已作废',
    if($order['refuse_status'] == 2)
        return 'REFUSE';    //'供货商拒绝发货',
    return 'OTHER';
}

/**
 * 获取订单状态的 显示按钮
 * @param type $order_id  订单id
 * @param type $order     订单数组
 * @return array()
 */
function orderBtn($order_id = 0, $order = array())
{
    if(empty($order))
        $order = M('Order')->where("order_id", $order_id)->find();
    /**
     *  订单用户端显示按钮
    去支付     AND pay_status=0 AND order_status=0 AND pay_code ! ="cod"
    取消按钮  AND pay_status=0 AND shipping_status=0 AND order_status=0
    确认收货  AND shipping_status=1 AND order_status=0
    评价      AND order_status=1
    查看物流  if(!empty(物流单号))
     */
    $btn_arr = array(
        'pay_btn'      => 0, // 去支付按钮
        'cancel_btn'   => 0, // 取消按钮
        'receive_btn'  => 0, // 确认收货
        'comment_btn'  => 0, // 评价按钮
        'shipping_btn' => 0, // 查看物流
        'return_btn'   => 0, // 退货按钮 (联系客服)
        'refuse_btn'   => 0, // 拒绝
    );

    if($order['refuse_status'] > 0 && $order['refuse_status'] < 3 ){
        $btn_arr['cancel_btn'] = 1; // 取消按钮
        if($order['refuse_status'] == 2) $btn_arr['refuse_btn'] = 2; // 拒绝发货按钮
    } else {
        
        if($order['refuse_status']!=2)
        {
            if($order['pay_status'] == 0 && $order['order_status'] == 0) // 待支付
            {
                $btn_arr['pay_btn']    = 1; // 去支付按钮
                $btn_arr['cancel_btn'] = 1; // 取消按钮
            }
            if($order['pay_status'] == 1 && in_array($order['order_status'],[0,1]) && $order['shipping_status'] == 0) // 待发货
            {

                // $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
                $btn_arr['cancel_btn'] = 1; // 取消按钮
            }
            if($order['pay_status'] == 1 && $order['order_status'] == 1  && $order['shipping_status'] == 1) //待收货
            {
                $btn_arr['receive_btn'] = 1;  // 确认收货
                // $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
            }
 
           
        }
        
        if($order['order_status'] == 2)
        {
            $btn_arr['comment_btn'] = 1;  // 评价按钮
        }
        if($order['order_status'] < 2)
        {
            $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
        }
        if($order['shipping_status'] != 0 && in_array($order['order_status'], [1,2,4]))
        {
            $btn_arr['shipping_btn'] = 1; // 查看物流
        }
        if($order['shipping_status'] == 2  && $order['order_status'] == 1) // 部分发货
        {
            // $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
        }

        if($order['pay_status'] == 1  && $order['shipping_status'] == 1 && $order['order_status'] < 2) // 已完成(已支付, 已发货 , 未完成)
        {
            $btn_arr['return_btn'] = 1; // 退货按钮
        }

        if($order['order_status'] == 3 && ($order['pay_status'] == 1 || $order['pay_status'] == 4)){
        	$btn_arr['cancel_info'] = 1; // 取消订单详情
        }
    }
    return $btn_arr;
}

/**
 * 给订单数组添加属性  包括按钮显示属性 和 订单状态显示属性
 * @param type $order
 */
function set_btn_order_status($order)
{
    $order_status_arr = C('ORDER_STATUS_DESC');
    $order['order_status_code'] = $order_status_code = orderStatusDesc(0, $order); // 订单状态显示给用户看的
    $order['order_status_desc'] = $order_status_arr[$order_status_code];
    $orderBtnArr = orderBtn(0, $order);
    return array_merge($order,$orderBtnArr); // 订单该显示的按钮
}

/**
 * 支付完成修改订单
 * @param $order_sn 订单号
 * @param array $ext 额外参数
 * @return bool|void
 */
function update_one_pay_status($order_sn,$ext=array())
{
    if(stripos($order_sn,'recharge') !== false){
        //用户在线充值
        $order = M('recharge')->where(['order_sn' => $order_sn, 'pay_status' => 0])->find();
        if (!$order) return false;// 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        M('recharge')->where("order_sn",$order_sn)->save(array('pay_status'=>1,'pay_time'=>time()));
        accountLog($order['user_id'],$order['account'],0,0,'会员在线充值');
    }elseif(stripos($order_sn,'staff_paid') !== false){
        $order = M('staff_paid')->where(['paid_sn' => $order_sn, 'pay_status' => 0])->find();
        if (!$order) return false;// 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        M('staff_paid')->where("paid_sn",$order_sn)->save(array('pay_status'=>1,'pay_time'=>NOW_TIME));
        M('transfer_log')->where("paid_sn",$order_sn)->save(array('pay_status'=>1,'pay_time'=>NOW_TIME));
        red_back_start($order);
        tk_store_money($order);
        feiePrint($order);
        //代付记录  。。 。。 。 ，， ， 
    }elseif(stripos($order_sn,'mypays') !== false){
        $order = M('staff_mypays')->where(['paid_sn' => $order_sn, 'pay_status' => 0])->find();
        if (!$order) return false;// 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        M('staff_mypays')->where("paid_sn",$order_sn)->save(array('pay_status'=>1,'pay_time'=>NOW_TIME,'transaction_id'=>$ext['transaction_id']));
        M('transfer_log')->where("paid_sn",$order_sn)->save(array('pay_status'=>1,'pay_time'=>NOW_TIME));
        red_back_start($order);
        tk_store_money($order);
        feiePrint($order);
        //扫码支付记录  。。 。。 。 ，， ， 
    }elseif(stripos($order_sn,'midou') !== false){
        update_one_pay_status_red($order_sn,$ext);// update_pay_status_red($order_sn,$ext);
    }else{
        // 如果这笔订单已经处理过了
        $count = M('order')->where("order_sn = :order_sn and pay_status = 0 OR pay_status = 2")->bind(['order_sn'=>$order_sn])->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        if($count == 0) return false;
        // 找出对应的订单
        $order = M('order')->where("order_sn",$order_sn)->find();
        //预售订单
        if ($order['order_prom_type'] == 4) {
            $orderGoodsArr = M('OrderGoods')->where(array('order_id'=>$order['order_id']))->find();
            // 预付款支付 有订金支付 修改支付状态  部分支付
            if($order['total_amount'] != $order['order_amount'] && $order['pay_status'] == 0){
                //支付订金
                M('order')->where("order_sn", $order_sn)->save(array('order_sn'=> date('YmdHis').mt_rand(1000,9999) ,'pay_status' => 2, 'pay_time' => time(),'paid_money'=>$order['order_amount']));
                M('goods_activity')->where(array('act_id'=>$order['order_prom_id']))->setInc('act_count',$orderGoodsArr['goods_num']);
            }else{
                //全额支付 无订金支付 支付尾款
                M('order')->where("order_sn", $order_sn)->save(array('pay_status' => 1, 'pay_time' => time()));
                $pre_sell = M('goods_activity')->where(array('act_id'=>$order['order_prom_id']))->find();
                $ext_info = unserialize($pre_sell['ext_info']);
                //全额支付 活动人数加一
                if(empty($ext_info['deposit'])){
                    M('goods_activity')->where(array('act_id'=>$order['order_prom_id']))->setInc('act_count',$orderGoodsArr['goods_num']);
                }
            }
        } else {
            // 修改支付状态  已支付
            //2018-9-25 王牧田修改  订单提交支付后直接确认
            $updata = array('pay_status'=>1,'pay_time'=>time(),'order_status'=>1);
            if(isset($ext['transaction_id'])) $updata['transaction_id'] = $ext['transaction_id'];
            M('order')->where("order_sn", $order_sn)->save($updata);
//             if(is_weixin()){
//              $wx_user = M('wx_user')->find();
//              $jssdk = new \app\common\logic\JssdkLogic($wx_user['appid'],$wx_user['appsecret']);
//              $order['goods_name'] = M('order_goods')->where(array('order_id'=>$order['order_id']))->getField('goods_name');
//              $jssdk->send_template_message($order);//发送微信模板消息提醒
//             }
        }

        // 减少对应商品的库存.注：拼团类型为抽奖团的，先不减库存
        if(tpCache('shopping.reduce') == 2) {
            if ($order['order_prom_type'] == 6) {
                $team = \app\common\model\TeamActivity::get($order['order_prom_id']);
                if ($team['team_type'] != 2) {
                    minus_stock($order);
                }
            } else {
                minus_stock($order);
            }
        }
        // 给他升级, 根据order表查看消费记录 给他会员等级升级 修改他的折扣 和 总金额
        update_user_level($order['user_id']);
        // 记录订单操作日志
        if(array_key_exists('admin_id',$ext)){
            logOrder($order['order_id'],$ext['note'],'付款成功',$ext['admin_id']);
        }else{
            logOrder($order['order_id'],'订单付款成功','付款成功',$order['user_id']);
        }
        //分销设置
        M('rebate_log')->where("order_id" ,$order['order_id'])->save(array('status'=>1));
        // 成为分销商条件
        $distribut_condition = tpCache('distribut.condition');
        if($distribut_condition == 1)  // 购买商品付款才可以成为分销商
            M('users')->where("user_id", $order['user_id'])->save(array('is_distribut'=>1));
        //虚拟服务类商品支付
        if($order['order_prom_type'] == 5){
            $OrderLogic = new \app\common\logic\OrderLogic();
            $OrderLogic->make_virtual_code($order);
        }
        if ($order['order_prom_type'] == 6) {
            $TeamOrderLogic = new \app\common\logic\TeamOrderLogic();
            $team = \app\common\model\TeamActivity::get($order['order_prom_id']);
            $TeamOrderLogic->setTeam($team);
            $TeamOrderLogic->doOrderPayAfter($order);
        }
         //发票生成
  /*      $Invoice = new \app\admin\logic\InvoiceLogic();
        $Invoice->create_Invoice($order);*/
        
        //用户支付, 发送短信给商家
        $res = checkEnableSendSms("4");
        if(!$res || $res['status'] !=1) return ;

        $sender = tpCache("shop_info.mobile");
        if(empty($sender))return;
        $params = array('order_id'=>$order['order_id']);
        sendSms("4", $sender, $params);
    }

}
/*给相应的实体店返款*/
function tk_store_money($order){
    $order_sn = $order['paid_sn'];
    $r = db('staff staff')->where("staff.id = {$order['staff_id']}")->join('company store ',"store.cid = staff.store_id")->Field('dbystore,store_id,is_auto_pay,is_bank')->cache(false)->find();
   
    if($r){
        if ($r['dbystore']=='0') {
            $r['dbystore']='1|0';
        }else{
            $r['dbystore']=$r['dbystore'];
        }
            $temp_array = explode('|', $r['dbystore']);

        $dby_money = bcmul($temp_array[0],$order['money'],9);
        $store_money = bcmul($temp_array[1],$order['money'],9);
        if(stripos($order_sn,'staff_paid') !== false){
            $table_name = 'staff_paid';
        }else{
            $table_name = 'staff_mypays';
        }
        if($store_money > 0){
            //更改订单中的分红记录
            db($table_name)->where('paid_sn',$order_sn)->update(['dby_money'=>$dby_money,'store_money'=>$store_money]);
            // 自动打款到客户那里
            auto_pay_store($r,$order,$store_money);
        }else{
            db($table_name)->where('paid_sn',$order_sn)->update(['dby_money'=>$dby_money]);
        }
    }
}

function query_bank_status($stauts){
    $arr['PROCESSING'] = '处理中'; //处理中，如有明确失败，则返回额外失败原因；否则没有错误原因
    $arr['SUCCESS'] = '付款成功';   
    $arr['FAILED'] = '付款失败,需要替换付款单号重新发起付款';
    $arr['BANK_FAIL'] = '银行退票'; //订单状态由付款成功流转至退票,退票时付款金额和手续费会自动退还
    return $arr[$stauts];
}
function query_change_status($status){
    $arr['PROCESSING'] = '处理中'; //处理中，如有明确失败，则返回额外失败原因；否则没有错误原因
    $arr['SUCCESS'] = '转账成功';   
    $arr['FAILED'] = '转账失败';
    return $arr[$status];
}
/*
    TK
    2018年10月1日16:41:13
    自动打款到客户实体店
*/
function auto_pay_store($r,$order,$store_money){
    if($r['is_auto_pay'] == 1 && $store_money >= 1){
        $store_info = db('company')->find($r['store_id']);
        $StoreWithdrawProject = new \Withdraw\StoreWithdraw();
        $StoreWithdrawProject->setPayMoney($store_money);
        $StoreWithdrawProject->setIsCard($r['is_bank']);
        $StoreWithdrawProject->setStoreInfo($store_info);
        $res = $StoreWithdrawProject->withdrawPay();
    }elseif($r['is_auto_pay'] == 2){
        $staff_info =   db('staff')->field('store_id,company_id')->find($order['staff_id']);
        $save_data['paid_sn']   =   $order['paid_sn'];
        $save_data['create_time']   =   NOW_TIME;
        $save_data['store_id']   =   $staff_info['store_id'];
        $save_data['company_id']   =   $staff_info['company_id'];
        $save_data['settlement_amount']   =   $store_money;
        db('store_settlement')->insert($save_data);
    }else{
        M('company')->where('cid',$r['store_id'])->setInc('store_money',$store_money);
    }
}

/**
 * 支付完成修改订单
 * @param $order_sn 订单号
 * @param array $ext 额外参数
 * @return bool|void
 */
function update_pay_status($order_sn,$ext=array())
{
    if(stripos($order_sn,'recharge') !== false){
        //用户在线充值
        $order = M('recharge')->where(['order_sn' => $order_sn, 'pay_status' => 0])->find();
        if (!$order) return false;// 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        M('recharge')->where("order_sn",$order_sn)->save(array('pay_status'=>1,'pay_time'=>time()));
        accountLog($order['user_id'],$order['account'],0,0,'会员在线充值');
    }elseif(stripos($order_sn,'staff_paid') !== false){
        $order = M('staff_paid')->where(['paid_sn' => $order_sn, 'pay_status' => 0])->find();
        if (!$order) return false;// 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        M('staff_paid')->where("paid_sn",$order_sn)->save(array('pay_status'=>1,'pay_time'=>NOW_TIME));
        M('transfer_log')->where("paid_sn",$order_sn)->save(array('pay_status'=>1,'pay_time'=>NOW_TIME));
        red_back_start($order);
        tk_store_money($order);
        feiePrint($order);
        //代付记录  。。 。。 。 ，， ， 
    }elseif(stripos($order_sn,'mypays') !== false){
        $order = M('staff_mypays')->where(['paid_sn' => $order_sn, 'pay_status' => 0])->find();
        if (!$order) return false;// 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        M('staff_mypays')->where("paid_sn",$order_sn)->save(array('pay_status'=>1,'pay_time'=>NOW_TIME,'transaction_id'=>$ext['transaction_id']));
        M('transfer_log')->where("paid_sn",$order_sn)->save(array('pay_status'=>1,'pay_time'=>NOW_TIME));
        red_back_start($order);
        tk_store_money($order);
        feiePrint($order);
        //代付记录  。。 。。 。 ，， ， 
    }elseif(stripos($order_sn,'midou') !== false){
        update_pay_status_red($order_sn,$ext);
    }else{
        // 如果这笔订单已经处理过了
        if($ext['order_num'] && $ext['order_num'] == 1)
            $count = M('order')->where("order_sn = '".$order_sn."' and pay_status = 0 OR pay_status = 2")->count(); 
        else
            $count = M('order')->where("(order_sn = '".$order_sn."' OR parent_sn = '".$order_sn."' ) and pay_status = 0 OR pay_status = 2")->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        if($count == 0) return false;
        // 找出对应的订单
        if($ext['order_num'] && $ext['order_num'] == 1)
            $order = M('order')->where('order_sn ="'.$order_sn.'"')->select();
        else 
            $order = M('order')->where('order_sn ="'.$order_sn.'" OR parent_sn ="'.$order_sn.'"')->select();
        foreach ($order as $key => $val) {
            //预售订单
            if ($val['order_prom_type'] == 4) {
                $orderGoodsArr = M('OrderGoods')->where(array('order_id'=>$val['order_id']))->find();
                // 预付款支付 有订金支付 修改支付状态  部分支付
                if($val['total_amount'] != $val['order_amount'] && $val['pay_status'] == 0){
                    //支付订金
                    M('order')->where("order_sn", $val['order_sn'])->save(array('order_sn'=> date('YmdHis').mt_rand(1000,9999) ,'pay_status' => 2, 'pay_time' => time(),'paid_money'=>$val['order_amount']));
                    M('goods_activity')->where(array('act_id'=>$val['order_prom_id']))->setInc('act_count',$orderGoodsArr['goods_num']);
                }else{
                    //全额支付 无订金支付 支付尾款
                    M('order')->where("order_sn", $val['order_sn'])->save(array('pay_status' => 1, 'pay_time' => time()));
                    $pre_sell = M('goods_activity')->where(array('act_id'=>$val['order_prom_id']))->find();
                    $ext_info = unserialize($pre_sell['ext_info']);
                    //全额支付 活动人数加一
                    if(empty($ext_info['deposit'])){
                        M('goods_activity')->where(array('act_id'=>$val['order_prom_id']))->setInc('act_count',$orderGoodsArr['goods_num']);
                    }
                }
            } else {
                // 修改支付状态  已支付
                //2018-9-25 王牧田修改  订单提交支付后直接确认
                $updata = array('pay_status'=>1,'pay_time'=>time(),'order_status'=>1);
                if(isset($ext['transaction_id'])) $updata['transaction_id'] = $ext['transaction_id'];
                if($ext['order_num'] && $ext['order_num'] == 1)
                    M('order')->where('order_sn ="'.$val['order_sn'].'"')->save($updata);
                else 
                    M('order')->where('order_sn ="'.$val['order_sn'].'" OR parent_sn ="'.$val['order_sn'].'"')->save($updata);

                //2018-10-12 张洪凯 支付成功发送微信模板消息提醒
                $val['table_name'] = 'order';
                $wechat = new \app\common\logic\WxLogic;
                $wechat->sendTemplateMsgOnPaySuccess($val);
                if($val['suppliers_id'] > 0){
                    $wechat->sendTemplateMsgOnSuppliersOrder($val);
                }

            }

            // 减少对应商品的库存.注：拼团类型为抽奖团的，先不减库存
            if(tpCache('shopping.reduce') == 2) {
                if ($val['order_prom_type'] == 6) {
                    $team = \app\common\model\TeamActivity::get($val['order_prom_id']);
                    if ($team['team_type'] != 2) {
                        minus_stock($val);
                    }
                } else {
                    minus_stock($val);
                }
            }
            // 给他升级, 根据order表查看消费记录 给他会员等级升级 修改他的折扣 和 总金额
            update_user_level($val['user_id']);
            // 记录订单操作日志
            if(array_key_exists('admin_id',$ext)){
                logOrder($val['order_id'],$ext['note'],'付款成功',$ext['admin_id']);
            }else{
                logOrder($val['order_id'],'订单付款成功','付款成功',$val['user_id']);
            }
            //分销设置
            M('rebate_log')->where("order_id" ,$val['order_id'])->save(array('status'=>1));
            // 成为分销商条件
            $distribut_condition = tpCache('distribut.condition');
            if($distribut_condition == 1)  // 购买商品付款才可以成为分销商
                M('users')->where("user_id", $val['user_id'])->save(array('is_distribut'=>1));
            //虚拟服务类商品支付
            if($val['order_prom_type'] == 5){
                $OrderLogic = new \app\common\logic\OrderLogic();
                $OrderLogic->make_virtual_code($val);
            }
            if ($val['order_prom_type'] == 6) {
                $TeamOrderLogic = new \app\common\logic\TeamOrderLogic();
                $team = \app\common\model\TeamActivity::get($val['order_prom_id']);
                $TeamOrderLogic->setTeam($team);
                $TeamOrderLogic->doOrderPayAfter($val);
            }
             //发票生成
            $Invoice = new \app\admin\logic\InvoiceLogic();
            $Invoice->create_Invoice($val);

            //用户支付, 发送短信给商家
            $res = checkEnableSendSms("4");
            if(!$res || $res['status'] !=1) return ;

            $sender = tpCache("shop_info.mobile");
            if(empty($sender))return;
            $params = array('order_id'=>$val['order_id']);
            sendSms("4", $sender, $params);

        }
        
    }

}


/**
 * 支付完成修改订单
 * @param $order_sn 订单号
 * @param array $ext 额外参数
 * @return bool|void
 */
function update_one_pay_status_red($order_sn,$ext=array())
{
    // 如果这笔订单已经处理过了
    if($ext['order_num'] && $ext['order_num'] == 1)
        $count = M('order_red')->where("order_sn = '".$order_sn."' and pay_status = 0 OR pay_status = 2")->bind(['order_sn'=>$order_sn])->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
    else
        $count = M('order_red')->where("(order_sn = '".$order_sn."' OR parent_sn = '".$order_sn."' ) and pay_status = 0 OR pay_status = 2")->bind(['order_sn'=>$order_sn])->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
    if($count == 0) return false;
    // 找出对应的订单
    if($ext['order_num'] && $ext['order_num'] == 1)
        $order = M('order_red')->where('order_sn ="'.$order_sn.'"')->select();
    else
        $order = M('order_red')->where('order_sn ="'.$order_sn.'" OR parent_sn ="'.$order_sn.'"')->select();

    foreach ($order as $key => $val) {
        $val['midou'] = -1*$val['midou'];
        //预售订单
        if ($val['order_prom_type'] == 4) {
            $orderGoodsArr = M('OrderRedGoods')->where(array('order_id'=>$val['order_id']))->find();
            // 预付款支付 有订金支付 修改支付状态  部分支付
            if($val['total_amount'] != $val['order_amount'] && $val['pay_status'] == 0){
                //支付订金
                M('order_red')->where("order_sn", $val['order_sn'])->save(array('order_sn'=> date('YmdHis').mt_rand(1000,9999) ,'pay_status' => 2, 'pay_time' => time(),'paid_money'=>$val['order_amount']));
                M('goods_red_activity')->where(array('act_id'=>$val['order_prom_id']))->setInc('act_count',$orderGoodsArr['goods_num']);
            }else{
                //全额支付 无订金支付 支付尾款
                M('order_red')->where("order_sn", $val['order_sn'])->save(array('pay_status' => 1, 'pay_time' => time()));
                $pre_sell = M('goods_red_activity')->where(array('act_id'=>$val['order_prom_id']))->find();
                $ext_info = unserialize($pre_sell['ext_info']);
                //全额支付 活动人数加一
                if(empty($ext_info['deposit'])){
                    M('goods_red_activity')->where(array('act_id'=>$val['order_prom_id']))->setInc('act_count',$orderGoodsArr['goods_num']);
                    accountLog($val['user_id'], $val['user_money'], $val['midou'], $val['integral'], $desc = '米豆专区商品兑换');
                    change_midou($val,'米豆商城消费');
                }
            }
        } else {
            // 修改支付状态  已支付
            //2018-9-25 王牧田修改  订单提交支付后直接确认
            $updata = array('pay_status'=>1,'pay_time'=>time(),'order_status'=>1);
            if(isset($ext['transaction_id'])) $updata['transaction_id'] = $ext['transaction_id'];

            if($ext['order_num'] && $ext['order_num'] == 1)
                M('order_red')->where("order_sn='".$val['order_sn']."'")->save($updata);
            else
                M('order_red')->where('order_sn ="'.$val['order_sn'].'" OR parent_sn ="'.$val['order_sn'].'"')->save($updata);
            
            accountLog($val['user_id'], $val['user_money'], $val['midou'], $val['integral'], $desc = '米豆专区商品兑换');
            change_midou($val,'米豆商城消费');
            /*if(is_weixin()){
              $wx_user = M('wx_user')->find();
              $jssdk = new \app\common\logic\JssdkLogic($wx_user['appid'],$wx_user['appsecret']);
              $val['goods_name'] = M('order_goods_red')->where(array('order_id'=>$val['order_id']))->getField('goods_name');
              $jssdk->send_template_message($val);//发送微信模板消息提醒
            }*/

            //2018-12-6 张洪凯 支付成功发送微信模板消息提醒


            $val['table_name'] = 'order_red';
            $wechat = new \app\common\logic\WxLogic;
            $wechat->sendTemplateMsgOnPaySuccess($val);
            if($val['suppliers_id'] > 0){
                $val['red'] = 1;
                $wechat->sendTemplateMsgOnSuppliersOrder($val);
            }

        }

        // 减少对应商品的库存.注：拼团类型为抽奖团的，先不减库存
        if(tpCache('shopping.reduce') == 2) {
            if ($val['order_prom_type'] == 6) {
                $team = \app\common\model\TeamRedActivity::get($val['order_prom_id']);
                if ($team['team_type'] != 2) {
                    minus_stock_red($val);
                }
            } else {
                minus_stock_red($val);
            }
        }
        // 给他升级, 根据order表查看消费记录 给他会员等级升级 修改他的折扣 和 总金额
        update_user_level($val['user_id']);
        // 记录订单操作日志
        if(array_key_exists('admin_id',$ext)){
            logOrderRed($val['order_id'],$ext['note'],'付款成功',$ext['admin_id']);
        }else{
            logOrderRed($val['order_id'],'订单付款成功','付款成功',$val['user_id']);
        }
        //分销设置
        # M('rebate_log')->where("order_id" ,$order['order_id'])->save(array('status'=>1));
        // 成为分销商条件
        $distribut_condition = tpCache('distribut.condition');
        if($distribut_condition == 1)  // 购买商品付款才可以成为分销商
            M('users')->where("user_id", $val['user_id'])->save(array('is_distribut'=>1));
        //虚拟服务类商品支付
        if($val['order_prom_type'] == 5){
            $OrderLogic = new \app\common\logic\RedOrderLogic();
            $OrderLogic->make_virtual_code($val);
        }
        if ($val['order_prom_type'] == 6) {
            $TeamOrderLogic = new \app\common\logic\RedTeamOrderLogic();
            $team = \app\common\model\TeamRedActivity::get($val['order_prom_id']);
            $TeamOrderLogic->setTeam($team);
            $TeamOrderLogic->doOrderPayAfter($val);
        }
         //发票生成
        /* $Invoice = new \app\admin\logic\InvoiceLogic();
        $Invoice->create_Invoice($order);*/
        
        //用户支付, 发送短信给商家
        $res = checkEnableSendSms("4");
        if(!$res || $res['status'] !=1) return ;

        $sender = tpCache("shop_info.mobile");
        if(empty($sender))return;
        $params = array('order_id'=>$val['order_id']);
        sendSms("4", $sender, $params);

    }

}

/**
*一乡一品
 * 支付完成修改订单
 * @param $order_sn 订单号
 * @param array $ext 额外参数
 * @return bool|void
 */
function update_one_pay_status_yxyp($order_sn,$ext=array())
{
    // 如果这笔订单已经处理过了
    if($ext['order_num'] && $ext['order_num'] == 1)
        $count = M('order_yxyp')->where("order_sn = '".$order_sn."' and pay_status = 0 OR pay_status = 2")->bind(['order_sn'=>$order_sn])->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
    else
        $count = M('order_yxyp')->where("(order_sn = '".$order_sn."' OR parent_sn = '".$order_sn."' ) and pay_status = 0 OR pay_status = 2")->bind(['order_sn'=>$order_sn])->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
    if($count == 0) return false;
    // 找出对应的订单
    if($ext['order_num'] && $ext['order_num'] == 1)
        $order = M('order_yxyp')->where('order_sn ="'.$order_sn.'"')->select();
    else
        $order = M('order_yxyp')->where('order_sn ="'.$order_sn.'" OR parent_sn ="'.$order_sn.'"')->select();

    foreach ($order as $key => $val) {
        $val['midou'] = -1*$val['midou'];
        //预售订单
        if ($val['order_prom_type'] == 4) {
            $orderGoodsArr = M('OrderYxypGoods')->where(array('order_id'=>$val['order_id']))->find();
            // 预付款支付 有订金支付 修改支付状态  部分支付
            if($val['total_amount'] != $val['order_amount'] && $val['pay_status'] == 0){
                //支付订金
                M('order_yxyp')->where("order_sn", $val['order_sn'])->save(array('order_sn'=> date('YmdHis').mt_rand(1000,9999) ,'pay_status' => 2, 'pay_time' => time(),'paid_money'=>$val['order_amount']));
                M('goods_yxyp_activity')->where(array('act_id'=>$val['order_prom_id']))->setInc('act_count',$orderGoodsArr['goods_num']);
            }else{
                //全额支付 无订金支付 支付尾款
                M('order_yxyp')->where("order_sn", $val['order_sn'])->save(array('pay_status' => 1, 'pay_time' => time()));
                $pre_sell = M('goods_yxyp_activity')->where(array('act_id'=>$val['order_prom_id']))->find();
                $ext_info = unserialize($pre_sell['ext_info']);
                //全额支付 活动人数加一
                if(empty($ext_info['deposit'])){
                    M('goods_yxyp_activity')->where(array('act_id'=>$val['order_prom_id']))->setInc('act_count',$orderGoodsArr['goods_num']);
                    accountLog($val['user_id'], $val['user_money'], $val['midou'], $val['integral'], $desc = '米豆专区商品兑换');
                    change_midou($val,'米豆商城消费');
                }
            }
        } else {
            // 修改支付状态  已支付
            //2018-9-25 王牧田修改  订单提交支付后直接确认
            $updata = array('pay_status'=>1,'pay_time'=>time(),'order_status'=>1);
            if(isset($ext['transaction_id'])) $updata['transaction_id'] = $ext['transaction_id'];

            if($ext['order_num'] && $ext['order_num'] == 1)
                M('order_yxyp')->where("order_sn='".$val['order_sn']."'")->save($updata);
            else
                M('order_yxyp')->where('order_sn ="'.$val['order_sn'].'" OR parent_sn ="'.$val['order_sn'].'"')->save($updata);
            
            accountLog($val['user_id'], $val['user_money'], $val['midou'], $val['integral'], $desc = '米豆专区商品兑换');
            change_midou($val,'米豆商城消费');
            /*if(is_weixin()){
              $wx_user = M('wx_user')->find();
              $jssdk = new \app\common\logic\JssdkLogic($wx_user['appid'],$wx_user['appsecret']);
              $val['goods_name'] = M('order_goods_red')->where(array('order_id'=>$val['order_id']))->getField('goods_name');
              $jssdk->send_template_message($val);//发送微信模板消息提醒
            }*/

            //2018-10-12 张洪凯 支付成功发送微信模板消息提醒
            $val['table_name'] = 'order_yxyp';
            $wechat = new \app\common\logic\WxLogic;
            $wechat->sendTemplateMsgOnPaySuccess($val);

        }

        // 减少对应商品的库存.注：拼团类型为抽奖团的，先不减库存
        if(tpCache('shopping.reduce') == 2) {
            if ($val['order_prom_type'] == 6) {
                $team = \app\common\model\TeamYxypActivity::get($val['order_prom_id']);
                if ($team['team_type'] != 2) {
                    minus_stock_red($val);
                }
            } else {
                minus_stock_red($val);
            }
        }
        // 给他升级, 根据order表查看消费记录 给他会员等级升级 修改他的折扣 和 总金额
        update_user_level($val['user_id']);
        // 记录订单操作日志
        if(array_key_exists('admin_id',$ext)){
            logOrderRed($val['order_id'],$ext['note'],'付款成功',$ext['admin_id']);
        }else{
            logOrderRed($val['order_id'],'订单付款成功','付款成功',$val['user_id']);
        }
        //分销设置
        # M('rebate_log')->where("order_id" ,$order['order_id'])->save(array('status'=>1));
        // 成为分销商条件
        $distribut_condition = tpCache('distribut.condition');
        if($distribut_condition == 1)  // 购买商品付款才可以成为分销商
            M('users')->where("user_id", $val['user_id'])->save(array('is_distribut'=>1));
        //虚拟服务类商品支付
        if($val['order_prom_type'] == 5){
            $OrderLogic = new \app\common\logic\YxypOrderLogic();
            $OrderLogic->make_virtual_code($val);
        }
        if ($val['order_prom_type'] == 6) {
            $TeamOrderLogic = new \app\common\logic\YxypTeamOrderLogic();
            $team = \app\common\model\TeamYxypActivity::get($val['order_prom_id']);
            $TeamOrderLogic->setTeam($team);
            $TeamOrderLogic->doOrderPayAfter($val);
        }
         //发票生成
        /* $Invoice = new \app\admin\logic\InvoiceLogic();
        $Invoice->create_Invoice($order);*/
        
        //用户支付, 发送短信给商家
        $res = checkEnableSendSms("4");
        if(!$res || $res['status'] !=1) return ;

        $sender = tpCache("shop_info.mobile");
        if(empty($sender))return;
        $params = array('order_id'=>$val['order_id']);
        sendSms("4", $sender, $params);

    }

}
/**
 * 支付完成修改订单
 * @param $order_sn 订单号
 * @param array $ext 额外参数
 * @return bool|void
 */
function update_pay_status_red($order_sn,$ext=array(),$store_id=0)
{
    // 如果这笔订单已经处理过了
    $count = M('order_red')->where("(order_sn ='".$order_sn."' OR parent_sn = '".$order_sn."' ) and pay_status = 0 OR pay_status = 2")->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
    if($count == 0) return false;
    // 找出对应的订单
    $order = M('order_red')->where('order_sn ="'.$order_sn.'" OR parent_sn ="'.$order_sn.'"')->select();
    foreach ($order as $key => $val) {
        $b = $val;
        $b['midou'] = -1*$val['midou'];
       
        //预售订单
        if ($val['order_prom_type'] == 4) {
            $orderGoodsArr = M('OrderRedGoods')->where(array('order_id'=>$val['order_id']))->find();
            // 预付款支付 有订金支付 修改支付状态  部分支付
            if($val['total_amount'] != $val['order_amount'] && $val['pay_status'] == 0){
                //支付订金
                M('order_red')->where("order_sn", $val['order_sn'])->save(array('order_sn'=> date('YmdHis').mt_rand(1000,9999) ,'pay_status' => 2, 'pay_time' => time(),'paid_money'=>$val['order_amount']));
                M('goods_red_activity')->where(array('act_id'=>$val['order_prom_id']))->setInc('act_count',$orderGoodsArr['goods_num']);
            }else{
                //全额支付 无订金支付 支付尾款
                M('order_red')->where("order_sn", $val['order_sn'])->save(array('pay_status' => 1, 'pay_time' => time()));
                $pre_sell = M('goods_red_activity')->where(array('act_id'=>$val['order_prom_id']))->find();
                $ext_info = unserialize($pre_sell['ext_info']);
                //全额支付 活动人数加一
                if(empty($ext_info['deposit'])){
                    M('goods_red_activity')->where(array('act_id'=>$val['order_prom_id']))->setInc('act_count',$orderGoodsArr['goods_num']);
             //       accountLog($val['user_id'], $val['user_money'], $val['midou'], $val['integral'], $desc = '米豆专区商品兑换',0,0,$val['order_id'],$val['order_sn']);
                    change_midou($b,'米豆商城消费');
                }
            }
        } else {

            // 修改支付状态  已支付
            //2018-11-15 王牧田修改  session("store_id") 改成 url 传值

            if($val["is_store"] != 0){
                $updata = array('pay_status'=>1,'pay_time'=>time(),'order_status'=>1,'shipping_status'=>1);
            }else{
                $updata = array('pay_status'=>1,'pay_time'=>time(),'order_status'=>1);
            }

            if(isset($ext['transaction_id'])) $updata['transaction_id'] = $ext['transaction_id'];
       #    M('order_red')->where('order_sn ="'.$val['order_sn'].'" OR parent_sn ="'.$val['order_sn'].'"')->save($updata);
            M('order_red')->where('order_sn',$val['order_sn'])->save($updata);
            accountLog($val['user_id'], $val['user_money'], $b['midou'], $val['integral'], $desc = '米豆专区商品兑换',0,0,$val['order_id'],$val['order_sn']);
            change_midou($b,"米豆商城消费");

            $val['table_name'] = 'order_red';
            $wechat = new \app\common\logic\WxLogic;
            if ($val['store_id']=='' || $val['store_id']=='0') {
                $wechat->sendTemplateMsgOnPaySuccess($val);

                if($val['suppliers_id'] > 0){
                    $val['red'] = 1;
                    $wechat->sendTemplateMsgOnSuppliersOrder($val);
                }

            }else{
                $wechat->sendTemplateMsgOnPaySuccessstore($val);
            }


        }

        // 减少对应商品的库存.注：拼团类型为抽奖团的，先不减库存
        if(tpCache('shopping.reduce') == 2) {
            if ($val['order_prom_type'] == 6) {
                $team = \app\common\model\TeamRedActivity::get($val['order_prom_id']);
                if ($team['team_type'] != 2) {
                    minus_stock_red($val);
                }
            } else {
                minus_stock_red($val);
            }
        }
        // 给他升级, 根据order表查看消费记录 给他会员等级升级 修改他的折扣 和 总金额
        update_user_level($val['user_id']);
        // 记录订单操作日志
        if(array_key_exists('admin_id',$ext)){
            logOrderRed($val['order_id'],$ext['note'],'付款成功',$ext['admin_id']);
        }else{
            logOrderRed($val['order_id'],'订单付款成功','付款成功',$val['user_id']);
        }
        //分销设置
        M('rebate_log')->where("order_id" ,$val['order_id'])->save(array('status'=>1));
        // 成为分销商条件
        $distribut_condition = tpCache('distribut.condition');
        if($distribut_condition == 1)  // 购买商品付款才可以成为分销商
            M('users')->where("user_id", $val['user_id'])->save(array('is_distribut'=>1));
        //虚拟服务类商品支付
        if($val['order_prom_type'] == 5){
            $OrderLogic = new \app\common\logic\RedOrderLogic();
            $OrderLogic->make_virtual_code($val);
        }
        if ($val['order_prom_type'] == 6) {
            $TeamOrderLogic = new \app\common\logic\RedTeamOrderLogic();
            $team = \app\common\model\TeamRedActivity::get($val['order_prom_id']);
            $TeamOrderLogic->setTeam($team);
            $TeamOrderLogic->doOrderPayAfter($val);
        }
         //发票生成
        $Invoice = new \app\admin\logic\InvoiceLogic();
        $Invoice->create_Invoice($val);

        //用户支付, 发送短信给商家
        $res = checkEnableSendSms("4");
        if(!$res || $res['status'] !=1) return ;

        $sender = tpCache("shop_info.mobile");
        if(empty($sender))return;
        $params = array('order_id'=>$val['order_id']);
        sendSms("4", $sender, $params);

    } 

}
/**
 * 支付完成修改订单
 *一乡一品
 * @param $order_sn 订单号
 * @param array $ext 额外参数
 * @return bool|void
 */
function update_pay_status_yxyp($order_sn,$ext=array())
{
    // 如果这笔订单已经处理过了
    $count = M('order_yxyp')->where("(order_sn ='".$order_sn."' OR parent_sn = '".$order_sn."' ) and pay_status = 0 OR pay_status = 2")->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
    if($count == 0) return false;
    // 找出对应的订单
    $order = M('order_yxyp')->where('order_sn ="'.$order_sn.'" OR parent_sn ="'.$order_sn.'"')->select();
    foreach ($order as $key => $val) {
        $b = $val;
        $b['midou'] = -1*$val['midou'];
       
        //预售订单
        if ($val['order_prom_type'] == 4) {
            $orderGoodsArr = M('OrderYxypGoods')->where(array('order_id'=>$val['order_id']))->find();
            // 预付款支付 有订金支付 修改支付状态  部分支付
            if($val['total_amount'] != $val['order_amount'] && $val['pay_status'] == 0){
                //支付订金
                M('order_yxyp')->where("order_sn", $val['order_sn'])->save(array('order_sn'=> date('YmdHis').mt_rand(1000,9999) ,'pay_status' => 2, 'pay_time' => time(),'paid_money'=>$val['order_amount']));
                M('goods_yxyp_activity')->where(array('act_id'=>$val['order_prom_id']))->setInc('act_count',$orderGoodsArr['goods_num']);
            }else{
                //全额支付 无订金支付 支付尾款
                M('order_yxyp')->where("order_sn", $val['order_sn'])->save(array('pay_status' => 1, 'pay_time' => time()));
                $pre_sell = M('goods_yxyp_activity')->where(array('act_id'=>$val['order_prom_id']))->find();
                $ext_info = unserialize($pre_sell['ext_info']);
                //全额支付 活动人数加一
                if(empty($ext_info['deposit'])){
                    M('goods_yxyp_activity')->where(array('act_id'=>$val['order_prom_id']))->setInc('act_count',$orderGoodsArr['goods_num']);
             //       accountLog($val['user_id'], $val['user_money'], $val['midou'], $val['integral'], $desc = '米豆专区商品兑换',0,0,$val['order_id'],$val['order_sn']);
                    change_midou($b,'米豆商城消费');
                }
            }
        } else {
            // 修改支付状态  已支付
            //2018-9-25 王牧田修改  订单提交支付后直接确认
            $updata = array('pay_status'=>1,'pay_time'=>time(),'order_status'=>1);
            if(isset($ext['transaction_id'])) $updata['transaction_id'] = $ext['transaction_id'];
            M('order_yxyp')->where('order_sn ="'.$val['order_sn'].'" OR parent_sn ="'.$val['order_sn'].'"')->save($updata);
            accountLog($val['user_id'], $val['user_money'], $b['midou'], $val['integral'], $desc = '米豆专区商品兑换',0,0,$val['order_id'],$val['order_sn']);
            change_midou($b,"米豆商城消费");
            // if(is_weixin()){
            //    $wx_user = M('wx_user')->find();
            //    $jssdk = new \app\common\logic\JssdkLogic($wx_user['appid'],$wx_user['appsecret']);
            //    $order['goods_name'] = M('order_goods')->where(array('order_id'=>$val['order_id']))->getField('goods_name');
            //    $jssdk->send_template_message($val);//发送微信模板消息提醒
            // }

            //2018-10-12 张洪凯 支付成功发送微信模板消息提醒
            $val['table_name'] = 'order_yxyp';
            $wechat = new \app\common\logic\WxLogic;
            $wechat->sendTemplateMsgOnPaySuccess($val);
        }

        // 减少对应商品的库存.注：拼团类型为抽奖团的，先不减库存
        if(tpCache('shopping.reduce') == 2) {
            if ($val['order_prom_type'] == 6) {
                $team = \app\common\model\TeamYxypActivity::get($val['order_prom_id']);
                if ($team['team_type'] != 2) {
                    minus_stock_red($val);
                }
            } else {
                minus_stock_red($val);
            }
        }
        // 给他升级, 根据order表查看消费记录 给他会员等级升级 修改他的折扣 和 总金额
        update_user_level($val['user_id']);
        // 记录订单操作日志
        if(array_key_exists('admin_id',$ext)){
            logOrderRed($val['order_id'],$ext['note'],'付款成功',$ext['admin_id']);
        }else{
            logOrderRed($val['order_id'],'订单付款成功','付款成功',$val['user_id']);
        }
        //分销设置
        M('rebate_log')->where("order_id" ,$val['order_id'])->save(array('status'=>1));
        // 成为分销商条件
        $distribut_condition = tpCache('distribut.condition');
        if($distribut_condition == 1)  // 购买商品付款才可以成为分销商
            M('users')->where("user_id", $val['user_id'])->save(array('is_distribut'=>1));
        //虚拟服务类商品支付
        if($val['order_prom_type'] == 5){
            $OrderLogic = new \app\common\logic\YxypOrderLogic();
            $OrderLogic->make_virtual_code($val);
        }
        if ($val['order_prom_type'] == 6) {
            $TeamOrderLogic = new \app\common\logic\YxypTeamOrderLogic();
            $team = \app\common\model\TeamYxypActivity::get($val['order_prom_id']);
            $TeamOrderLogic->setTeam($team);
            $TeamOrderLogic->doOrderPayAfter($val);
        }
         //发票生成
        $Invoice = new \app\admin\logic\InvoiceLogic();
        $Invoice->create_Invoice($val);

        //用户支付, 发送短信给商家
        $res = checkEnableSendSms("4");
        if(!$res || $res['status'] !=1) return ;

        $sender = tpCache("shop_info.mobile");
        if(empty($sender))return;
        $params = array('order_id'=>$val['order_id']);
        sendSms("4", $sender, $params);

    } 

}

/**
 * 订单确认收货
 * @param $id 订单id
 * @param int $user_id
 * @return array
 */
function confirm_order($id,$user_id = 0){
    $where['order_id'] = $id;
    if($user_id){
        $where['user_id'] = $user_id;
    }
    $order = M('order')->where($where)->find();
    if($order['order_status'] != 1)
        return array('status'=>-1,'msg'=>'该订单不能收货确认');
    if(empty($order['pay_time']) || ($order['pay_status'] != 1 && $order['pay_status'] != 4)){
        return array('status'=>-1,'msg'=>'商家未确定付款，该订单暂不能确定收货');
    }

    $res = red_back_start($order);
    if(isset($res)){
        if($res != false){
            return $res;
        }
    }
   /* print_r($order);
    die;*/
    $data['order_status'] = 2; // 已收货
    $data['pay_status'] = 1; // 已付款
    $data['confirm_time'] = time(); // 收货确认时间
    if($order['pay_code'] == 'cod'){
        $data['pay_time'] = time();
    }
    $row = M('order')->where(array('order_id'=>$id))->save($data);
    if(!$row)
        return array('status'=>-3,'msg'=>'操作失败');

    
    //order_give($order);// 调用送礼物方法, 给下单这个人赠送相应的礼物
    //分销设置
    //M('rebate_log')->where("order_id", $id)->save(array('status'=>2,'confirm'=>time()));

    #推广员申请购买商品金额判断  老张
    confirm_tgy_apply($order['user_id']);

    return array('status'=>1,'msg'=>'操作成功','url'=>U('Order/order_detail',['id'=>$id]));
}

#老张
function confirm_tgy_apply($user_id){
    #确认收货统计推广员申请是否达到购买金额或件数
    #如果达到，则设置审核通过，同时添加到员工表tp_staff
    $res = db('apply_promoters a')
        ->field("a.*,u.password,s.store_id,s.company_id")
        ->where("status = 0 and u.user_id ={$user_id}")
        ->join("users u","u.user_id = a.user_id")
        ->join("staff s","s.id = a.staff_id")
        ->find();
    if($res){

        //推广员审核限制配置信息  goods_unit 1 金额  2件数
        $goods_area = tpCache('basic.goods_area');
        $goods_unit = tpCache('basic.goods_unit');
        $goods_unit_con = tpCache('basic.goods_unit_con');


        //获取已完成订单的购买总金额或总件数
        $order_where['order_status'] = ['in', '2,4'];  //已完成的订单
        $order_where['o.user_id'] = ['eq', $user_id];
        $order_where['og.tg_ok'] = ['eq', 0];
        $order_where['og.is_tg'] = ['eq', 1];
        #提取现金区购买的商品记录
        $order_list1 = Db::name('order')
            ->alias('o')
            ->field('o.order_id,og.goods_id,og.goods_num,og.goods_price')
            ->join('order_goods og','o.order_id=og.order_id','left')
            ->where($order_where)
            ->select();
        #提取一乡一品区购买的商品记录
        $order_list2 = Db::name('order_yxyp')
            ->alias('o')
            ->field('o.order_id,og.goods_id,og.goods_num,og.goods_price')
            ->join('order_yxyp_goods og','o.order_id=og.order_id','left')
            ->where($order_where)
            ->select();

        $total_moeny = 0;
        $total_num = 0;

        $order_goods_update1 = array();
        $order_goods_update2 = array();
        foreach ($order_list1 as $order) {
            if ($goods_unit == 1) {
                //金额
                $total_moeny += $order['goods_num'] * $order['goods_price'];
            } else {
                //件数
                $total_num += $order['goods_num'];
            }

            #推广申请审核通过，将购买过的商品进行标识
            $order_goods_update1[] = ['rec_id'=>$order['rec_id'],
                'tg_ok'=>1
            ];
        }
        foreach ($order_list2 as $order) {
            if ($goods_unit == 1) {
                //金额
                $total_moeny += $order['goods_num'] * $order['goods_price'];
            } else {
                //件数
                $total_num += $order['goods_num'];
            }

            #推广申请审核通过，将购买过的商品进行标识
            $order_goods_update2[] = ['rec_id'=>$order['rec_id'],
                'tg_ok'=>1
            ];
        }

        $ok_flag = false;
        if ($goods_unit == 1) {
            //如果设置的是金额
            if ($total_moeny >= $goods_unit_con) {
                $ok_flag = true;
            }

        } else {
            //如果设置的是件数
            if ($total_num >= $goods_unit_con) {
                $ok_flag = true;
            }

        }

        //推广员审核通过，插入员工表
        if($ok_flag == true){
            $data['uname']  =   $res['contact'];
            $data['tkpsw']  =   $res['psw'];
            $data['phone']  =   $res['mobile'];
            $data['create_time']  =   NOW_TIME;
            $data['real_name']  =   $res['contact'];
            $data['store_id']  =   $res['store_id'];
            $data['company_id']  =   $res['company_id'];
            $data['is_lock']  =   0;
            $data['type']  =   1;
            $data['parent_id']  =   $res['staff_id'];
            $data['invite_code']    =  judge_invite_code(get_rand_str(10,0,1));
            $staff_obj    = new \app\admin\logic\StaffLogic();
            $r            = $staff_obj->addStaff($data);

            if($r['status'] == 1) {

                db('apply_promoters')->where("id=" . $res['id'])->update(['status' => 3, 'update_time' => NOW_TIME]);

                #推广申请审核通过，将购买过的商品进行标识
                if($order_goods_update1){
                    model('order_goods')->saveAll($order_goods_update1);
                }
                if($order_goods_update2){
                    model('order_yxyp_goods')->saveAll($order_goods_update2);
                }

            }

        }

    }
}


/**
 米豆专区
 * 订单确认收货
 * @param $id 订单id
 * @param int $user_id
 * @return array
 */
function confirm_order_red($id,$user_id = 0){
    $where['order_id'] = $id;
    if($user_id){
        $where['user_id'] = $user_id;
    }
    $order = M('order_red')->where($where)->find();
    if($order['order_status'] != 1)
        return array('status'=>-1,'msg'=>'该订单不能收货确认');
    if(empty($order['pay_time']) || ($order['pay_status'] != 1 && $order['pay_status'] != 4)){
        return array('status'=>-1,'msg'=>'商家未确定付款，该订单暂不能确定收货');
    }

   /* $res = red_back_start($order,1);
    if(isset($res)){
        if($res != false){
            return $res;
        }
    }*/


    // die;
    $data['order_status'] = 2; // 已收货
    $data['pay_status'] = 1; // 已付款
    $data['confirm_time'] = NOW_TIME; // 收货确认时间
    if($order['pay_code'] == 'cod'){
        $data['pay_time'] = NOW_TIME;
    }
    $row = M('order_red')->where(array('order_id'=>$id))->save($data);
    if(!$row)
        return array('status'=>-3,'msg'=>'操作失败');

    //order_give($order);// 调用送礼物方法, 给下单这个人赠送相应的礼物
    //分销设置
    //M('rebate_red_log')->where("order_id", $id)->save(array('status'=>2,'confirm'=>time()));
    return array('status'=>1,'msg'=>'操作成功','url'=>U('Order/order_detail',['id'=>$id]));
}
/**
 一乡一品
 * 订单确认收货
 * @param $id 订单id
 * @param int $user_id
 * @return array
 */
function confirm_order_yxyp($id,$user_id = 0){
    $where['order_id'] = $id;
    if($user_id){
        $where['user_id'] = $user_id;
    }
    $order = M('order_yxyp')->where($where)->find();
    if($order['order_status'] != 1)
        return array('status'=>-1,'msg'=>'该订单不能收货确认');
    if(empty($order['pay_time']) || ($order['pay_status'] != 1 && $order['pay_status'] != 4)){
        return array('status'=>-1,'msg'=>'商家未确定付款，该订单暂不能确定收货');
    }
    yxyp_back_start($order,1);
    // die;
    $data['order_status'] = 2; // 已收货
    $data['pay_status'] = 1; // 已付款
    $data['confirm_time'] = time(); // 收货确认时间
    if($order['pay_code'] == 'cod'){
        $data['pay_time'] = time();
    }
    $row = M('order_yxyp')->where(array('order_id'=>$id))->save($data);
    if(!$row)
        return array('status'=>-3,'msg'=>'操作失败');

    //order_give($order);// 调用送礼物方法, 给下单这个人赠送相应的礼物
    //分销设置
    //M('rebate_red_log')->where("order_id", $id)->save(array('status'=>2,'confirm'=>time()));

    #推广员申请购买商品金额判断  老张
    confirm_tgy_apply($order['user_id']);

    return array('status'=>1,'msg'=>'操作成功','url'=>U('Order/order_detail',['id'=>$id]));
}


// 全返 laowang
function red_back_start($order,$is_red=0){
    #红包商城
    $midou = 0;
    if($is_red == 1){
        if($order['midou_money']<=0){
            return false;
        }
        $price = $order['midou_money'];
        $red_data['red_name']    = '米豆商城订单返米豆';
        $order_goods = M('order_red_goods')->where('order_id ='.$order['order_id'])->select();

        $ids_list = get_arr_column($order_goods,'goods_id');
        $return_goods = db::name('return_red_goods')->field('goods_id,status')->where('goods_id','in',$ids_list)->where('order_sn',$order['order_sn'])->select_key('goods_id');

        foreach ($order_goods as $k => $val) {
            if(isset($return_goods[$val['goods_id']])){
                if($return_goods[$val['goods_id']]['status'] == 3){
                    continue;
                }elseif($return_goods[$val['goods_id']]['status'] >= 0){
                    $res['status']  =   -1;
                    $res['msg'] =   '尚有售后信息未完结，无法收货';
                    return $res;
                }
            }
            $goodsInfo = M('goods_red')->where('goods_id ='.$val['goods_id'])->find();
            if( $goodsInfo['is_z_back'] == 1) $midou_back_percent = tpCache('shoppingred.midou_back_percent');
            else $midou_back_percent = $goodsInfo['midou_back_percent'];
            $goods_price = $val['goods_num']*$val['midou_money']*$midou_back_percent/100;
            $md = $goods_price/tpCache('shoppingred.midou_rate');
            $midou += num_float2($md);
            //$midou += bcdiv($goods_price,tpCache('shoppingred.midou_rate'),3);     #相除
        }

    }elseif(isset($order['paid_sn'])){
        $out_trade_no = $order['paid_sn'];
        $store_info = db('staff')->alias('staff')->where("staff.id = {$order['staff_id']}")->field('store.is_z_back,store.midou_back_percent')->join('company store','store.cid = staff.store_id')->find();
        $tk_midou_back_percent = tpCache('shoppingred.midou_back_percent_unline');
        if($store_info['is_z_back'] == 0){
            $price = bcdiv(bcmul($order['money'],$store_info['midou_back_percent'],9),100,3);
        }else{
            $price = bcdiv(bcmul($order['money'],$tk_midou_back_percent,9),100,3);
        }

        $midou = bcdiv($price,tpCache('shoppingred.midou_rate'),3);
        $order['order_id']  =   $order['id'];
        $order['order_sn']  =   $order['paid_sn'];
        if(stripos($out_trade_no,'staff_paid')!==false){
            $red_data['red_name']    = '代付订单返米豆';
            $source = "代付订单{$out_trade_no}返米豆";
        }else{
            $red_data['red_name']    = '扫码支付返米豆';
            $source = "扫码在线订单{$out_trade_no}返米豆";
            if($midou > 0){
                //做冗余记录
                db('staff_mypays')->where('paid_sn',$order['order_sn'])->setField('return_midou',$midou);    
            }
        }

    }else{
        $price = bcsub($order['order_amount'],$order['shipping_price'],2);      #相减
        $red_data['red_name']    = '现金商城订单返米豆';
        $order_goods = M('order_goods')->where('order_id ='.$order['order_id'])->select();
        $ids_list = get_arr_column($order_goods,'goods_id');

        $return_goods_where['goods_id'] =   ['in',$ids_list];
        $return_goods_where['order_sn'] =   ['eq',$order['order_sn']];
        $return_goods = db::name('return_goods')->field('goods_id,status')->where($return_goods_where)->select_key('goods_id');
        foreach ($order_goods as $k => $val) {
            if(isset($return_goods[$val['goods_id']])){
                if($return_goods[$val['goods_id']]['status'] == 3){
                    continue;
                }elseif($return_goods[$val['goods_id']]['status'] >= 0){
                    $res['status']  =   -1;
                    $res['msg'] =   '尚有售后信息未完结，无法收货';
                    return $res;
                }
            }
            $goodsInfo = M('goods')->where('goods_id ='.$val['goods_id'])->cache("goods_{$val['goods_id']}")->find();
            if( $goodsInfo['is_z_back'] == 1) {
                $midou_back_percent = tpCache('shoppingred.midou_back_percent');
            }else {
                $midou_back_percent = $goodsInfo['midou_back_percent'];
            }
            $goods_price = $val['goods_num']*$val['member_goods_price']*$midou_back_percent/100;
            $md = $goods_price/tpCache('shoppingred.midou_rate');
            //判断是不是促销

            if($goodsInfo['prom_type'] == 0){
                $midou += num_float2($md);
            }
            //$midou += bcdiv($goods_price,tpCache('shoppingred.midou_rate'),3);     #相除
        }
    }
    if($midou >0){
        if(empty($source)){
            $source = "订单{$order['order_sn']}消费返米豆";
        }
        $red_data['create_time'] = NOW_TIME;
        $red_data['source']      = $source;
        $red_data['money']       = $midou;
        $red_data['user_id']     = $order['user_id'];         
        $red_data['order_id']    = $order['order_id'];
        $red_data['order_sn']   =   $order['order_sn'];
        $red_data['is_red'] =   $is_red;
        db('red_envelope')->add($red_data);
        $data['midou'] = ['exp',"midou + {$midou}"];
        $data['midou_all'] = ['exp',"midou_all + {$midou}"];
        db('users')->where("user_id = {$order['user_id']}")->update($data);
    }
    
    #红包返利结束
}
// 全返 laowang 一乡一品
function yxyp_back_start($order,$is_red=0){
    #红包商城
    if($is_red == 1){
     
        $red_data['red_name']    = '一乡一品商城订单返米豆';
        $order_goods = M('order_yxyp_goods')->where('order_id ='.$order['order_id'])->select();

        foreach ($order_goods as $k => $val) {
            $goodsInfo = M('goods')->where('goods_id ='.$val['goods_id'])->find();
            if( $goodsInfo['is_z_back'] == 1) {
                $midou_back_percent = tpCache('shoppingred.midou_back_percent');
            }else {
                $midou_back_percent = $goodsInfo['midou_back_percent'];
            }
            $goods_price = $val['goods_num']*$val['member_goods_price']*$midou_back_percent/100;
            $md = $goods_price/tpCache('shoppingred.midou_rate');
            //$midou += bcdiv($goods_price,tpCache('shoppingred.midou_rate'),3);     #相除
            if($goodsInfo['prom_type'] == 0){
                $midou += num_float2($md);

            }

        }
    }
    //$midou = bcdiv($price,tpCache('shoppingred.midou_rate'),4);     #相除
    # $midou = tk_money_format($midou, 3);
    if(empty($source)){
        $source = "订单{$order['order_sn']}消费返米豆";
    }
    $red_data['create_time'] = NOW_TIME;
    $red_data['source']      = $source;
    $red_data['money']       = $midou;
    $red_data['user_id']     = $order['user_id'];         
    $red_data['order_id']    = $order['order_id'];
    $red_data['order_sn']   =   $order['order_sn'];
    $red_data['is_red'] =   $is_red;
    db('red_envelope')->add($red_data);

    $data['midou'] = ['exp',"midou + {$midou}"];
    $data['midou_all'] = ['exp',"midou_all + {$midou}"];

    db('users')->where("user_id = {$order['user_id']}")->update($data);
    #红包返利结束
}
/**
 * 米豆明细记录
 */
function change_midou($order,$red_name = '订单返米豆'){
    $data['red_name']     = $red_name;
    $data['create_time']  = NOW_TIME;
    $data['money']        = $order['midou'];
    $data['source']       = "订单{$order['order_sn']}消费使用米豆";;
    $data['user_id']      = $order['user_id'];         
    $data['order_id']     = $order['order_id'];
    if (stripos($order['order_sn'], 'midou') !== false){
        $is_red = 1;
    }else{
        $is_red = 0;
    }
    $data['is_red'] =   $is_red;
    $res = db('red_envelope')->add($data);
   /* if($res == 1){
        $updata['midou'] = ['exp',"midou + ".$data['money']];
        db('users')->where("user_id = ".$order['user_id'])->save($updata);
    }*/
}

/**
 * 退款返回米豆明细记录
 */
function change_min_midou($order,$red_name='退款返回米豆'){
    $data['red_name']     = $red_name;
    $data['create_time']  = NOW_TIME;
    $data['money']        = tk_money_format($order['midou'], 3);
    $data['source']       = "订单{$order['order_sn']}，退款返回米豆";;
    $data['user_id']      = $order['user_id'];         

    $data['order_id']     = $order['order_id'];  
    if (stripos($order['order_sn'], 'midou') !== false){
        $is_red = 1;
    }else{
        $is_red = 0;
    }
    $data['is_red'] =   $is_red;
 //   dump($data);die;
    $res = db('red_envelope')->add($data);
    if($res >= 0){
        $updata['midou'] = ['exp',"midou + ".$data['money']];
        M('users')->where("user_id = ".$order['user_id'])->save($updata);
    }
}
/**
 * 单纯记录
 */
function tk_midou_log($user_id,$midou,$info='退款返回米豆',$order_id,$order_sn){
    $data['red_name']     = $info;
    $data['create_time']  = NOW_TIME;
    $data['money']        = $midou;
    $data['source']       = $info;
    $data['user_id']      = $user_id;         
    $data['order_id']     = $order_id;  
    if (stripos($order_sn, 'midou') !== false){
        $is_red = 1;
    }else{
        $is_red = 0;
    }
    $data['is_red'] =   $is_red;
    $res = db('red_envelope')->add($data);
}
/**
 * 记录员工帐户变动
 * @param   int     $staff_id        用户id
 * @param   float   $staff_money     冻结余额变动
 * @param   string  $desc    变动说明
 * @param   int $order_id 订单id
 * @param   string $order_sn 订单sn
 * @return  bool
 */

function staff_accountLog($staff_id, $staff_money = 0, $desc = ''){

    /* 插入帐户变动记录 */
    $account_log = array(
        'staff_id'    => $staff_id,
        'staff_money' => $staff_money,
        'create_time'     => time(),
        'desc'            => $desc,
    );
    if($staff_money == 0) return false;
    if(M('staff_account_log')->add($account_log)){
        return true;
    }else{
        return false;
    }
}


/**
 * 记录成员帐户变动
 * @param   int     $staff_id        用户id
 * @param   float   $staff_money     冻结余额变动
 * @param   string  $desc    变动说明
 * @param   int $order_id 订单id
 * @param   string $order_sn 订单sn
 * @return  bool
 */

function member_accountLog($member_id, $money = 0, $desc = ''){

    /* 插入帐户变动记录 */
    $account_log = array(
        'member_id'    => $member_id,
        'member_money' => $money,
        'create_time'     => time(),
        'desc'            => $desc,
    );
    if($money == 0) return false;
    if(M('member_account_log')->add($account_log)){
        return true;
    }else{
        return false;
    }
}

#佣金 返利记录
function staff_commission_log($staff_id,$staff_money,$desc,$buy_user_id){
    #
    /* 插入帐户变动记录 */
    $account_log = array(
        'staff_id'    => $staff_id,
        'staff_money' => $staff_money,
        'create_time'     => time(),
        'desc'            => $desc,
        'buy_id'       =>$buy_user_id,
    );
    if($staff_money == 0) return false;
    if(M('staff_commission')->add($account_log)){
        return true;
    }else{
        return false;
    }
}


/**
 * 下单赠送活动：优惠券，积分
 * @param $order|订单数组
 */
function order_give($order)
{
    //促销优惠订单商品
    $prom_order_goods = M('order_goods')->where(['order_id' => $order['order_id'], 'prom_type' => 3])->select();
    //获取用户会员等级
    // $user_level = M('users')->where(['user_id' => $order['user_id']])->getField('level');
    foreach ($prom_order_goods as $goods) {
        //查找购买商品送优惠券活动
        $prom_goods = M('prom_goods')->where(['id' => $goods['prom_id'], 'type' => 3])->find();
        if ($prom_goods) {
            //查找购买商品送优惠券模板
            $goods_coupon = M('coupon')->where(['id' => $prom_goods['expression']])->find();
            // if ($goods_coupon && !empty($prom_goods['group'])) {
                if ($goods_coupon) {
                    // 用户会员等级是否符合送优惠券活动
                    // if (in_array($user_level, explode(',', $prom_goods['group']))) {
                    //优惠券发放数量验证，0为无限制。发放数量-已领取数量>0
                    if ($goods_coupon['createnum'] == 0 ||
                            ($goods_coupon['createnum'] > 0 && ($goods_coupon['createnum'] - $goods_coupon['send_num']) > 0)
                    ) {
                        $data = array('cid' => $goods_coupon['id'], 'get_order_id'=>$order['order_id'],'type' => $goods_coupon['type'], 'uid' => $order['user_id'], 'send_time' => time());
                        M('coupon_list')->add($data);
                        // 优惠券领取数量加一
                        M('Coupon')->where("id", $goods_coupon['id'])->setInc('send_num');
                    }
            //  }
            }
        }
    }
    //查找订单满额促销活动
    $prom_order_where = [
        'type' => ['gt', 1],
        'end_time' => ['gt', $order['pay_time']],
        'start_time' => ['lt', $order['pay_time']],
        'money' => ['elt', $order['goods_price']]
    ];
    $prom_orders = M('prom_order')->where($prom_order_where)->order('money desc')->select();
    $prom_order_count = count($prom_orders);
    // 用户会员等级是否符合送优惠券活动
    for ($i = 0; $i < $prom_order_count; $i++) {
    //  if (in_array($user_level, explode(',', $prom_orders[$i]['group']))) {
            $prom_order = $prom_orders[$i];
            if ($prom_order['type'] == 3) {
                //查找订单送优惠券模板
                $order_coupon = M('coupon')->where("id", $prom_order['expression'])->find();
                if ($order_coupon) {
                    //优惠券发放数量验证，0为无限制。发放数量-已领取数量>0
                    if ($order_coupon['createnum'] == 0 ||
                        ($order_coupon['createnum'] > 0 && ($order_coupon['createnum'] - $order_coupon['send_num']) > 0)
                    ) {
                        $data = array('cid' => $order_coupon['id'], 'get_order_id'=>$order['order_id'],'type' => $order_coupon['type'], 'uid' => $order['user_id'], 'send_time' => time());
                        M('coupon_list')->add($data);
                        M('Coupon')->where("id", $order_coupon['id'])->setInc('send_num'); // 优惠券领取数量加一
                    }
                }
            }
            //购买商品送积分
            if ($prom_order['type'] == 2) {
                accountLog($order['user_id'], 0,0, $prom_order['expression'], "订单活动赠送积分");
            }
            break;
    //  }
    }
    $points = M('order_goods')->where("order_id", $order['order_id'])->sum("give_integral * goods_num");
    $points && accountLog($order['user_id'], 0,0, $points, "下单赠送积分", 0, $order['order_id'], $order['order_sn']);
}


/**
 * 查看订单是否满足条件参加活动
 * @param $order_amount
 * @return array
 */
function get_order_promotion($order_amount)
{
//    $parse_type = array('0'=>'满额打折','1'=>'满额优惠金额','2'=>'满额送倍数积分','3'=>'满额送优惠券','4'=>'满额免运费');
    $now = time();
    $prom = M('prom_order')->where("type<2 and end_time>$now and start_time<$now and money<=$order_amount")->order('money desc')->find();
    $res = array('order_amount' => $order_amount, 'order_prom_id' => 0, 'order_prom_amount' => 0);
    if ($prom) {
        if ($prom['type'] == 0) {
            $res['order_amount'] = round($order_amount * $prom['expression'] / 100, 2);//满额打折
            $res['order_prom_amount'] = round($order_amount - $res['order_amount'], 2);
            $res['order_prom_id'] = $prom['id'];
        } elseif ($prom['type'] == 1) {
            $res['order_amount'] = $order_amount - $prom['expression'];//满额优惠金额
            $res['order_prom_amount'] = $prom['expression'];
            $res['order_prom_id'] = $prom['id'];
        }
    }
    return $res;
}

/**
 * TK 红包商城  2018年4月23日09:05:57
 * 查看订单是否满足条件参加活动
 * @param $order_amount
 * @return array
 */
function get_order_promotion_red($order_amount)
{
//    $parse_type = array('0'=>'满额打折','1'=>'满额优惠金额','2'=>'满额送倍数积分','3'=>'满额送优惠券','4'=>'满额免运费');
    $now = time();
    $prom = M('prom_red_order')->where("type<2 and end_time>$now and start_time<$now and money<=$order_amount")->order('money desc')->find();
    $res = array('order_amount' => $order_amount, 'order_prom_id' => 0, 'order_prom_amount' => 0);
    if ($prom) {
        if ($prom['type'] == 0) {
            $res['order_amount'] = round($order_amount * $prom['expression'] / 100, 2);//满额打折
            $res['order_prom_amount'] = round($order_amount - $res['order_amount'], 2);
            $res['order_prom_id'] = $prom['id'];
        } elseif ($prom['type'] == 1) {
            $res['order_amount'] = $order_amount - $prom['expression'];//满额优惠金额
            $res['order_prom_amount'] = $prom['expression'];
            $res['order_prom_id'] = $prom['id'];
        }
    }
    return $res;
}
/**
 * LX 一乡一品  2018年10月9日11:51:50
 * 查看订单是否满足条件参加活动
 * @param $order_amount
 * @return array
 */
function get_order_promotion_yxyp($order_amount)
{
//    $parse_type = array('0'=>'满额打折','1'=>'满额优惠金额','2'=>'满额送倍数积分','3'=>'满额送优惠券','4'=>'满额免运费');
    $now = time();
    $prom = M('prom_yxyp_order')->where("type<2 and end_time>$now and start_time<$now and money<=$order_amount")->order('money desc')->find();
    $res = array('order_amount' => $order_amount, 'order_prom_id' => 0, 'order_prom_amount' => 0);
    if ($prom) {
        if ($prom['type'] == 0) {
            $res['order_amount'] = round($order_amount * $prom['expression'] / 100, 2);//满额打折
            $res['order_prom_amount'] = round($order_amount - $res['order_amount'], 2);
            $res['order_prom_id'] = $prom['id'];
        } elseif ($prom['type'] == 1) {
            $res['order_amount'] = $order_amount - $prom['expression'];//满额优惠金额
            $res['order_prom_amount'] = $prom['expression'];
            $res['order_prom_id'] = $prom['id'];
        }
    }
    return $res;
}


/**
 * 计算订单金额
 * @param int $user_id 用户id
 * @param $order_goods 购买的商品
 * @param string $shipping_code 物流code
 * @param int $shipping_price 物流费用, 如果传递了物流费用 就不在计算物流费
 * @param int $province 省份
 * @param int $city 城市
 * @param int $district 县
 * @param int $pay_points 积分
 * @param int $user_money 余额
 * @param int $coupon_id 优惠券
 * @return array
 */
function calculate_price($user_id = 0, $order_goods, $shipping_code = '', $shipping_price = 0, $province = 0, $city = 0, $district = 0, $pay_points = 0, $user_money = 0, $coupon_id = 0,$red_envelope_id=0)
{


    $couponLogic = new \app\common\logic\CouponLogic();
    $goodsLogic = new app\common\logic\GoodsLogic();
    $user = M('users')->where("user_id", $user_id)->find();// 找出这个用户
    $result=[];
    if (empty($order_goods)){
        return array('status' => -9, 'msg' => '商品列表不能为空', 'result' => '');
    }
    $use_percent_point = tpCache('shopping.point_use_percent') / 100;     //最大使用限制: 最大使用积分比例, 例如: 为50时, 未50% , 那么积分支付抵扣金额不能超过应付金额的50%
    /*判断能否使用积分
     1..积分低于point_min_limit时,不可使用
     2.在不使用积分的情况下, 计算商品应付金额
     3.原则上, 积分支付不能超过商品应付金额的50%, 该值可在平台设置
     @{ */
   
    $point_rate = tpCache('shopping.point_rate'); //兑换比例: 如果拥有的积分小于该值, 不可使用
    $min_use_limit_point = tpCache('shopping.point_min_limit'); //最低使用额度: 如果拥有的积分小于该值, 不可使用
    
    if ($min_use_limit_point > 0 && $pay_points > 0 && $pay_points < $min_use_limit_point) {
        return array('status' => -1, 'msg' => "您使用的积分必须大于{$min_use_limit_point}才可以使用", 'result' => ''); // 返回结果状态
    }
    // 计算该笔订单最多使用多少积分
    if(($use_percent_point !=1 ) && $pay_points > $result['order_integral']) {
        return array('status'=>-1,'msg'=>"该笔订单, 您使用的积分不能大于{$result['order_integral']}",'result'=>'积分'); // 返回结果状态
    }

    if(($pay_points > 0 && $use_percent_point == 0) ||  ($pay_points >0 && $result['order_integral']==0)){
        return array('status' => -1, 'msg' => "该笔订单不能使用积分", 'result' => '积分'); // 返回结果状态
    }

    if ($pay_points && ($pay_points > $user['pay_points']))
        return array('status' => -5, 'msg' => "你的账户可用积分为:" . $user['pay_points'], 'result' => ''); // 返回结果状态



    if ($user_money && ($user_money > $user['user_money']))
        return array('status' => -6, 'msg' => "你的账户可用余额为:" . $user['user_money'], 'result' => ''); // 返回结果状态

    $goods_id_arr = get_arr_column($order_goods, 'goods_id');
    $goods_arr = M('goods')->where("goods_id in(" . implode(',', $goods_id_arr) . ")")->cache(true,TPSHOP_CACHE_TIME)
        ->getField('goods_id,weight,market_price,is_free_shipping,exchange_integral,shop_price'); // 商品id 和重量对应的键值对

    $tk_cost_operating=$tk_cost_price=$goods_weight=$goods_price=$cut_fee=$anum=$coupon_price= 0;  //定义一些变量

    #记录优惠促销的商品信息
    #2018-11-15 张洪凯
    $prom_arr = array();

    foreach ($order_goods as $key => $val) {
        // 如果传递过来的商品列表没有定义会员价
        if (!array_key_exists('member_goods_price', $val)) {
            $user['discount'] = $user['discount'] ? $user['discount'] : 1; // 会员折扣 不能为 0
            $order_goods[$key]['member_goods_price'] = $val['member_goods_price'] = $val['goods_price'] * $user['discount'];
        }
        //如果商品不是包邮的
        if ($goods_arr[$val['goods_id']]['is_free_shipping'] == 0){
            //累积商品重量 每种商品的重量 * 数量
            $goods_weight += $goods_arr[$val['goods_id']]['weight'] * $val['goods_num'];

            #2018-11-15 张洪凯
            #将属于同一促销活动的商品重量累计到一起
            if($val['prom_id'] > 0 && $val['prom_type'] > 0){
                $prom_arr[$val['prom_id']]['goods_weight'] += $goods_weight;
                $prom_arr[$val['prom_id']]['prom_type'] = $val['prom_type'];

                #将参加同一优惠活动的商品价格累加到一起，好进行计算是否符合活动条件
                $prom_arr[$val['prom_id']]['goods_price'] += $val['goods_num'] * $val['member_goods_price'];
            }

        }

        //计算订单可用积分
        if($goods_arr[$val['goods_id']]['exchange_integral']>0){
            //商品设置了积分兑换就用商品本身的积分。
            $result['order_integral'] +=  $goods_arr[$val['goods_id']]['exchange_integral'];
        }else{
            //没有就按照会员价与平台设置的比例来计算。
            $result['order_integral'] +=  ceil($order_goods[$key]['member_goods_price'] * $use_percent_point);
        }
        $order_goods[$key]['goods_fee'] = $val['goods_num'] * $val['member_goods_price'];    // 小计
        $order_goods[$key]['store_count'] = getGoodNum($val['goods_id'], $val['spec_key']); // 最多可购买的库存数量
        if ($order_goods[$key]['store_count'] <= 0 || $order_goods[$key]['store_count'] < $order_goods[$key]['goods_num'])
            return array('status' => -10, 'msg' => $order_goods[$key]['goods_name'] .','.$val['spec_key_name']. "库存不足,请重新下单", 'result' => '');

        $goods_price += $order_goods[$key]['goods_fee']; // 商品总价
        $cut_fee += $val['goods_num'] * $val['market_price'] - $val['goods_num'] * $val['member_goods_price']; // 共节约
        $anum += $val['goods_num']; // 购买数量

        #计算商品成本价
        if(is_numeric($val['cost_price']) && $val['cost_price'] > 0){
            $tk_cost_price += $val['goods_num'] * $val['cost_price'];
        }else{
            if(is_numeric($val['goods']['cost_price']) && $val['goods']['cost_price'] > 0){
                $tk_cost_price += $val['goods_num'] * $val['cost_price'];
            }else{
                $tk_cost_price += $val['goods_num'] * $val['goods_price'];
            }
        }

        if(is_numeric($val['cost_operating']) && $val['cost_operating'] > 0){
            $tk_cost_operating += $val['goods_num'] * $val['cost_operating'];
        }else{
            if(is_numeric($val['goods']['cost_operating']) && $val['goods']['cost_operating'] > 0){
                $tk_ccost_operating += $val['goods_num'] * $val['cost_operating'];
            }else{
                $tk_cost_operating += 0;
            }
        }
        #计算商品价格结束
    }


    // 优惠券处理操作
    if ($coupon_id && $user_id) {
        $coupon_price = $couponLogic->getCouponMoney($user_id, $coupon_id); // 下拉框方式选择优惠券
    }

    ###处理物流###
    if ($shipping_price == 0) {
        $freight_free = tpCache('shopping.freight_free'); // 全场满多少免运费
        if ($freight_free > 0 && $goods_price >= $freight_free) {
            $shipping_price = 0;
        } else {
            $shipping_price = $goodsLogic->getFreight($shipping_code, $province, $city, $district, $goods_weight);
        }
    }

    #处理物流，统计出参加满包邮活动的商品的运费
    #2018-11-15 张洪凯
    foreach($prom_arr as $prom_id=>$prom){
        $prom_arr[$prom_id]['shipping_price'] =  $goodsLogic->getFreight($shipping_code, $province, $city, $district, $prom['goods_weight']);
    }


    $order_amount = $goods_price + $shipping_price - $coupon_price - $red_envelope_price; // 应付金额 = 商品价格 + 物流费 - 优惠券
    $user_money = ($user_money > $order_amount) ? $order_amount : $user_money;  // 余额支付余额不能大于应付金额，原理等同于积分
    $order_amount = $order_amount - $user_money;  //余额支付抵应付金额 （如果未付完，剩余多少没付）

    // 积分支付 100 积分等于 1块钱
    if($pay_points  > floor($order_amount * $point_rate)){
        $pay_points = floor($order_amount * $point_rate);
    }

    $integral_money = ($pay_points / $point_rate);
    $order_amount = $order_amount - $integral_money; //  积分抵消应付金额 （如果未付完，剩余多少没付）

    $total_amount = $goods_price + $shipping_price;  //订单总价

    // 订单满额优惠活动
    //$order_prom = get_order_promotion($goods_price);
    //订单总价  应付金额  物流费  商品总价 节约金额 共多少件商品 积分  余额  优惠券
    #满包邮  2018-11-15 张洪凯
    if($prom_arr){
        $order_prom = get_order_prom($prom_arr);
    }else{
        $order_prom = get_order_prom($goods_price);
    }


    $result = array(
        'total_amount'      => $total_amount, // 订单总价
        'order_amount'      => round($order_amount-$order_prom['order_prom_amount'], 2), // 应付金额(要减去优惠的钱)
        'shipping_price'    => $shipping_price,    // 物流费
        'goods_price'       => $goods_price,       // 商品总价
        'cut_fee'           => $cut_fee,           // 共节约多少钱
        'anum'              => $anum,              // 商品总共数量
        'integral_money'    => $integral_money,    // 积分抵消金额
        'user_money'        => $user_money,        // 使用余额
        'coupon_price'      => $coupon_price,      // 优惠券抵消金额
        'red_envelope_price'=>$red_envelope_price,
        'order_prom_id'     => $order_prom['order_prom_id'],
        'order_prom_amount' => $order_prom['order_prom_amount'],
        'order_goods'       => $order_goods,       // 商品列表 多加几个字段原样返回
        'tk_cost_price'     => $tk_cost_price,     //商品成本价格
        'tk_cost_operating' => $tk_cost_operating, //商品运营成本价格
    );
    return array('status' => 1, 'msg' => "计算价钱成功", 'result' => $result); // 返回结果状态
}

/**
 * 查看订单是否满足满包邮
 * @author 张洪凯
 * @param $order_amount
 * @return array
 */
function get_order_prom($order_prom)
{
    $now = time();
    if(is_array($order_prom) && !empty($order_prom)){
        foreach($order_prom as $prom_id=>$value){

            if($value['prom_type'] == 5){
                $prom = M('prom_goods')->where("id=$prom_id and end_time>=$now and start_time<=$now and expression<=".$value['goods_price'])->find();
                if($prom){
                    $res['order_amount'] += $value['goods_price'];
                    $res['order_prom_amount'] += $value['shipping_price'];
                    $res['order_prom_id'] = $prom['id'];
                }
                else{
                    $res['order_amount'] += 0;
                    $res['order_prom_amount'] += 0;
                    $res['order_prom_id'] = 0;
                }
            }

        }
    }else{
        $res = array('order_amount' => $order_prom, 'order_prom_id' => 0, 'order_prom_amount' => 0);
    }
    return $res;

}


/**
 红包商城
 * 计算订单金额
 * @param int $user_id 用户id
 * @param $order_goods 购买的商品
 * @param string $shipping_code 物流code
 * @param int $shipping_price 物流费用, 如果传递了物流费用 就不在计算物流费
 * @param int $province 省份
 * @param int $city 城市
 * @param int $district 县
 * @param int $pay_points 积分
 * @param int $user_money 余额
 * @param int $coupon_id 优惠券
 * @return array
 */
function calculate_price_red($user_id = 0, $order_goods, $shipping_code = '', $shipping_price = 0, $province = 0, $city = 0, $district = 0, $pay_points = 0, $user_money = 0, $coupon_id = 0, $red_envelope_id = 0, $midou = array(), $midou_money = array(),$store_id = 0)
{

    $couponLogic = new \app\common\logic\RedCouponLogic();
    $goodsLogic = new app\common\logic\RedGoodsLogic();
    $user = M('users')->where("user_id", $user_id)->find();// 找出这个用户
    $result=[];
    if (empty($order_goods)){
        return array('status' => -9, 'msg' => '商品列表不能为空', 'result' => '');
    }
    $use_percent_point = tpCache('shopping.point_use_percent') / 100;     //最大使用限制: 最大使用积分比例, 例如: 为50时, 未50% , 那么积分支付抵扣金额不能超过应付金额的50%
    /*判断能否使用积分
     1..积分低于point_min_limit时,不可使用
     2.在不使用积分的情况下, 计算商品应付金额
     3.原则上, 积分支付不能超过商品应付金额的50%, 该值可在平台设置
     @{ */
    $point_rate = tpCache('shopping.point_rate'); //兑换比例: 如果拥有的积分小于该值, 不可使用
    $min_use_limit_point = tpCache('shopping.point_min_limit'); //最低使用额度: 如果拥有的积分小于该值, 不可使用
    
    if ($min_use_limit_point > 0 && $pay_points > 0 && $pay_points < $min_use_limit_point) {
        return array('status' => -1, 'msg' => "您使用的积分必须大于{$min_use_limit_point}才可以使用", 'result' => ''); // 返回结果状态
    }
    // 计算该笔订单最多使用多少积分
    if(($use_percent_point !=1 ) && $pay_points > $result['order_integral']) {
        return array('status'=>-1,'msg'=>"该笔订单, 您使用的积分不能大于{$result['order_integral']}",'result'=>'积分'); // 返回结果状态
    }

    if(($pay_points > 0 && $use_percent_point == 0) ||  ($pay_points >0 && $result['order_integral']==0)){
        return array('status' => -1, 'msg' => "该笔订单不能使用积分", 'result' => '积分'); // 返回结果状态
    }

    if ($pay_points && ($pay_points > $user['pay_points']))
        return array('status' => -5, 'msg' => "你的账户可用积分为:" . $user['pay_points'], 'result' => ''); // 返回结果状态
    if ($user_money && ($user_money > $user['user_money']))
        return array('status' => -6, 'msg' => "你的账户可用余额为:" . $user['user_money'], 'result' => ''); // 返回结果状态



    $goods_id_arr = get_arr_column($order_goods, 'goods_id');
    $goods_arr = M('goods_red')->where("goods_id in(" . implode(',', $goods_id_arr) . ")")->cache(true,TPSHOP_CACHE_TIME)
        ->getField('goods_id,weight,market_price,is_free_shipping,exchange_integral,shop_price,is_z_change,midou_use_percent'); // 商品id 和重量对应的键值对
    
    $tk_cost_operating = $tk_cost_price = $goods_weight = $goods_price = $cut_fee = $anum = $coupon_price = $order_midou = $order_midou_money = $order_max_midou = 0;  //定义一些变量
    
    $midou_use_percent = tpCache('shoppingred.midou_use_percent'); // 购买商品 使用米豆 比率
    $midou_rate        = tpCache('shoppingred.midou_rate');        // 米豆兑换比

    foreach ($order_goods as $key => $val) {
        // 如果传递过来的商品列表没有定义会员价
        /*if (!array_key_exists('member_goods_price', $val)) {
            $user['discount'] = $user['discount'] ? $user['discount'] : 1; // 会员折扣 不能为 0
            $order_goods[$key]['member_goods_price'] = $val['member_goods_price'] = $val['goods_price'] * $user['discount'];
        }*/
        //如果商品不是包邮的
        if ($goods_arr[$val['goods_id']]['is_free_shipping'] == 0)
            $goods_weight += $goods_arr[$val['goods_id']]['weight'] * $val['goods_num']; //累积商品重量 每种商品的重量 * 数量
        //计算订单可用积分
        /*if($goods_arr[$val['goods_id']]['exchange_integral']>0){
            //商品设置了积分兑换就用商品本身的积分。
            $result['order_integral'] +=  $goods_arr[$val['goods_id']]['exchange_integral'];
        }else{
            //没有就按照会员价与平台设置的比例来计算。
            $result['order_integral'] +=  ceil($order_goods[$key]['member_goods_price'] * $use_percent_point);
        }*/

        $order_goods[$key]['goods_fee'] = $val['goods_num'] * $val['member_goods_price'];       // 小计
        $order_goods[$key]['store_count'] = getGoodNumRed($val['goods_id'], $val['spec_key']);  // 最多可购买的库存数量
        if ($order_goods[$key]['store_count'] <= 0 || $order_goods[$key]['store_count'] < $order_goods[$key]['goods_num']){
//            $store_id = session('store_id');
//            if(empty($store_id)){
//                return array('status' => -10, 'msg' => $order_goods[$key]['goods_name'] .','.$val['spec_key_name']. "库存不足,请重新下单", 'result' => '');
//            }
        }
        
        // 购买商品 使用米豆 比率
        if($goods_arr[$val['goods_id']]['is_z_change'] != 1) $midou_use_percent = $goods_arr[$val['goods_id']]['midou_use_percent'];  
        
        if(empty($midou)) 
            $order_midou       += $val['midou'] * $val['goods_num'];
        else 
            $order_midou       += $midou[$val['goods_id']][$val['item_id']] * $val['goods_num'];

        if(empty($midou_money))
            $order_midou_money += $val['midou_money'] * $val['goods_num'];
        else
            $order_midou_money += $midou_money[$val['goods_id']][$val['item_id']] * $val['goods_num'];

        $order_max_midou   += $val['max_midou'] * $val['goods_num'];
        $goods_price       += $order_goods[$key]['goods_fee']; // 商品总价
        $cut_fee           += $val['goods_num'] * $val['market_price'] - $val['goods_num'] * $val['member_goods_price']; // 共节约
        $anum              += $val['goods_num']; // 购买数量

        #计算商品成本价
        if(is_numeric($val['cost_price']) && $val['cost_price'] > 0){
            $tk_cost_price += $val['goods_num'] * $val['cost_price'];
        }else{
            if(is_numeric($val['goods']['cost_price']) && $val['goods']['cost_price'] > 0){
                $tk_cost_price += $val['goods_num'] * $val['cost_price'];
            }else{
                $tk_cost_price += $val['goods_num'] * $val['goods_price'];
            }
        }

        if(is_numeric($val['cost_operating']) && $val['cost_operating'] > 0){
            $tk_cost_operating += $val['goods_num'] * $val['cost_operating'];
        }else{
            if(is_numeric($val['goods']['cost_operating']) && $val['goods']['cost_operating'] > 0){
                $tk_cost_operating += $val['goods_num'] * $val['cost_operating'];
            }else{
                $tk_cost_operating += 0;
            }
        }
        #计算商品价格结束

    }


    // 优惠券处理操作
    if ($coupon_id && $user_id) {
        $coupon_price = $couponLogic->getCouponMoney($user_id, $coupon_id); // 下拉框方式选择优惠券
    }
    // 处理物流
    if ($shipping_price == 0) {
        $freight_free = tpCache('shopping.freight_free'); // 全场满多少免运费
        if ($freight_free > 0 && $goods_price >= $freight_free) {
            $shipping_price = 0;
        } else {
            $shipping_price = $goodsLogic->getFreight($shipping_code, $province, $city, $district, $goods_weight);
        }
    }

    $order_amount = $order_midou_money + $shipping_price - $coupon_price - $red_envelope_price; // 应付金额 = 商品价格 + 物流费 - 优惠券
    $user_money   = ($user_money > $order_amount) ? $order_amount : $user_money;  // 余额支付余额不能大于应付金额，原理等同于积分
    $order_amount = $order_amount - $user_money;  //余额支付抵应付金额 （如果未付完，剩余多少没付）

    // 积分支付 100 积分等于 1块钱
    if($pay_points  > floor($order_amount * $point_rate)){
        $pay_points = floor($order_amount * $point_rate);
    }

    $integral_money = ($pay_points / $point_rate);
    $order_amount   = $order_amount - $integral_money; //  积分抵消应付金额 （如果未付完，剩余多少没付）

    $total_amount   = $goods_price + $shipping_price;  //订单总价
    // 订单满额优惠活动
    $order_prom = get_order_promotion_red($goods_price);
    //订单总价  应付金额  物流费  商品总价 节约金额 共多少件商品 积分  余额  优惠券
    $result = array(
        'total_amount'       => $total_amount,    // 订单总价
        'order_amount'       => round($order_amount-$order_prom['order_prom_amount'], 2), // 应付金额(要减去优惠的钱)
        'shipping_price'     => $shipping_price,  // 物流费
        'goods_price'        => $goods_price,     // 商品总价
        'cut_fee'            => $cut_fee,         // 共节约多少钱
        'anum'               => $anum,            // 商品总共数量
        'integral_money'     => $integral_money,  // 积分抵消金额
        'user_money'         => $user_money,      // 使用余额
        'coupon_price'       => $coupon_price,    // 优惠券抵消金额
        'red_envelope_price' => $red_envelope_price,
        'order_prom_id'      => $order_prom['order_prom_id'],
        'order_prom_amount'  => $order_prom['order_prom_amount'],
        'order_goods'        => $order_goods,     // 商品列表 多加几个字段原样返回
        'order_midou'        => $order_midou,
        'order_midou_money'  => $order_midou_money,
        'order_max_midou'    => $order_max_midou,
        'tk_cost_price'      =>$tk_cost_price,     //商品成本价格
        'tk_cost_operating'  =>$tk_cost_operating, //商品成本价格
    );
    return array('status' => 1, 'msg' => "计算价钱成功", 'result' => $result); // 返回结果状态
}


/**
 一乡一品
 * 计算订单金额
 * @param int $user_id 用户id
 * @param $order_goods 购买的商品
 * @param string $shipping_code 物流code
 * @param int $shipping_price 物流费用, 如果传递了物流费用 就不在计算物流费
 * @param int $province 省份
 * @param int $city 城市
 * @param int $district 县
 * @param int $pay_points 积分
 * @param int $user_money 余额
 * @param int $coupon_id 优惠券
 * @return array
 */
function calculate_price_yxyp($user_id = 0, $order_goods, $shipping_code = '', $shipping_price = 0, $province = 0, $city = 0, $district = 0, $pay_points = 0, $user_money = 0, $coupon_id = 0, $red_envelope_id = 0)
{

    $couponLogic = new \app\common\logic\YxypCouponLogic();
    $goodsLogic = new app\common\logic\YxypGoodsLogic();
    $user = M('users')->where("user_id", $user_id)->find();// 找出这个用户
    $result=[];
    if (empty($order_goods)){
        return array('status' => -9, 'msg' => '商品列表不能为空', 'result' => '');
    }
    $use_percent_point = tpCache('shopping.point_use_percent') / 100;     //最大使用限制: 最大使用积分比例, 例如: 为50时, 未50% , 那么积分支付抵扣金额不能超过应付金额的50%
    /*判断能否使用积分
     1..积分低于point_min_limit时,不可使用
     2.在不使用积分的情况下, 计算商品应付金额
     3.原则上, 积分支付不能超过商品应付金额的50%, 该值可在平台设置
     @{ */
    $point_rate = tpCache('shopping.point_rate'); //兑换比例: 如果拥有的积分小于该值, 不可使用
    $min_use_limit_point = tpCache('shopping.point_min_limit'); //最低使用额度: 如果拥有的积分小于该值, 不可使用
    
    if ($min_use_limit_point > 0 && $pay_points > 0 && $pay_points < $min_use_limit_point) {
        return array('status' => -1, 'msg' => "您使用的积分必须大于{$min_use_limit_point}才可以使用", 'result' => ''); // 返回结果状态
    }
    // 计算该笔订单最多使用多少积分
    if(($use_percent_point !=1 ) && $pay_points > $result['order_integral']) {
        return array('status'=>-1,'msg'=>"该笔订单, 您使用的积分不能大于{$result['order_integral']}",'result'=>'积分'); // 返回结果状态
    }

    if(($pay_points > 0 && $use_percent_point == 0) ||  ($pay_points >0 && $result['order_integral']==0)){
        return array('status' => -1, 'msg' => "该笔订单不能使用积分", 'result' => '积分'); // 返回结果状态
    }

    if ($pay_points && ($pay_points > $user['pay_points']))
        return array('status' => -5, 'msg' => "你的账户可用积分为:" . $user['pay_points'], 'result' => ''); // 返回结果状态
    if ($user_money && ($user_money > $user['user_money']))
        return array('status' => -6, 'msg' => "你的账户可用余额为:" . $user['user_money'], 'result' => ''); // 返回结果状态



    $goods_id_arr = get_arr_column($order_goods, 'goods_id');
    $goods_arr = M('goods_yxyp')->where("goods_id in(" . implode(',', $goods_id_arr) . ")")->cache(true,TPSHOP_CACHE_TIME)
        ->getField('goods_id,weight,market_price,is_free_shipping,exchange_integral,shop_price,is_z_change,midou_use_percent'); // 商品id 和重量对应的键值对
    
    $tk_cost_operating = $tk_cost_price = $goods_weight = $goods_price = $cut_fee = $anum = $coupon_price = $order_midou = $order_midou_money = $order_max_midou = 0;  //定义一些变量
  

    foreach ($order_goods as $key => $val) {
        // 如果传递过来的商品列表没有定义会员价
        /*if (!array_key_exists('member_goods_price', $val)) {
            $user['discount'] = $user['discount'] ? $user['discount'] : 1; // 会员折扣 不能为 0
            $order_goods[$key]['member_goods_price'] = $val['member_goods_price'] = $val['goods_price'] * $user['discount'];
        }*/
        //如果商品不是包邮的
        if ($goods_arr[$val['goods_id']]['is_free_shipping'] == 0)
            $goods_weight += $goods_arr[$val['goods_id']]['weight'] * $val['goods_num']; //累积商品重量 每种商品的重量 * 数量
        //计算订单可用积分
        /*if($goods_arr[$val['goods_id']]['exchange_integral']>0){
            //商品设置了积分兑换就用商品本身的积分。
            $result['order_integral'] +=  $goods_arr[$val['goods_id']]['exchange_integral'];
        }else{
            //没有就按照会员价与平台设置的比例来计算。
            $result['order_integral'] +=  ceil($order_goods[$key]['member_goods_price'] * $use_percent_point);
        }*/

        $order_goods[$key]['goods_fee'] = $val['goods_num'] * $val['member_goods_price'];       // 小计
        $order_goods[$key]['store_count'] = getGoodNumYxyp($val['goods_id'], $val['spec_key']);  // 最多可购买的库存数量

        
        // 购买商品 使用米豆 比率
       
        $goods_price       += $order_goods[$key]['goods_fee']; // 商品总价
        $cut_fee           += $val['goods_num'] * $val['market_price'] - $val['goods_num'] * $val['member_goods_price']; // 共节约
        $anum              += $val['goods_num']; // 购买数量

        #计算商品成本价
        if(is_numeric($val['cost_price']) && $val['cost_price'] > 0){
            $tk_cost_price += $val['goods_num'] * $val['cost_price'];
        }else{
            if(is_numeric($val['goods']['cost_price']) && $val['goods']['cost_price'] > 0){
                $tk_cost_price += $val['goods_num'] * $val['cost_price'];
            }else{
                $tk_cost_price += $val['goods_num'] * $val['goods_price'];
            }
        }

        if(is_numeric($val['cost_operating']) && $val['cost_operating'] > 0){
            $tk_cost_operating += $val['goods_num'] * $val['cost_operating'];
        }else{
            if(is_numeric($val['goods']['cost_operating']) && $val['goods']['cost_operating'] > 0){
                $tk_cost_operating += $val['goods_num'] * $val['cost_operating'];
            }else{
                $tk_cost_operating += 0;
            }
        }
        #计算商品价格结束

    }


    // 优惠券处理操作
    if ($coupon_id && $user_id) {
        $coupon_price = $couponLogic->getCouponMoney($user_id, $coupon_id); // 下拉框方式选择优惠券
    }
    // 处理物流
    if ($shipping_price == 0) {
        $freight_free = tpCache('shopping.freight_free'); // 全场满多少免运费
        if ($freight_free > 0 && $goods_price >= $freight_free) {
            $shipping_price = 0;
        } else {
            $shipping_price = $goodsLogic->getFreight($shipping_code, $province, $city, $district, $goods_weight);
        }
    }

    // $order_amount = $order_midou_money + $shipping_price - $coupon_price - $red_envelope_price; // 应付金额 = 商品价格 + 物流费 - 优惠券
    $user_money   = ($user_money > $order_amount) ? $order_amount : $user_money;  // 余额支付余额不能大于应付金额，原理等同于积分
    $order_amount = $order_amount - $user_money;  //余额支付抵应付金额 （如果未付完，剩余多少没付）

    // 积分支付 100 积分等于 1块钱
    if($pay_points  > floor($order_amount * $point_rate)){
        $pay_points = floor($order_amount * $point_rate);
    }

    $integral_money = ($pay_points / $point_rate);
    $order_amount   = $order_amount - $integral_money; //  积分抵消应付金额 （如果未付完，剩余多少没付）

    $total_amount   = $goods_price + $shipping_price;  //订单总价
    // 订单满额优惠活动
    $order_prom = get_order_promotion_yxyp($goods_price);
    //订单总价  应付金额  物流费  商品总价 节约金额 共多少件商品 积分  余额  优惠券
    $result = array(
        'total_amount'       => $total_amount,    // 订单总价
        'order_amount'       => round($order_amount-$order_prom['order_prom_amount'], 2), // 应付金额(要减去优惠的钱)
        'shipping_price'     => $shipping_price,  // 物流费
        'goods_price'        => $goods_price,     // 商品总价
        'cut_fee'            => $cut_fee,         // 共节约多少钱
        'anum'               => $anum,            // 商品总共数量
        'integral_money'     => $integral_money,  // 积分抵消金额
        'user_money'         => $user_money,      // 使用余额
        'coupon_price'       => $coupon_price,    // 优惠券抵消金额
        'red_envelope_price' => $red_envelope_price,
        'order_prom_id'      => $order_prom['order_prom_id'],
        'order_prom_amount'  => $order_prom['order_prom_amount'],
        'order_goods'        => $order_goods,     // 商品列表 多加几个字段原样返回
        // 'order_midou'        => $order_midou,
        // 'order_midou_money'  => $order_midou_money,
        // 'order_max_midou'    => $order_max_midou,
        'tk_cost_price'      =>$tk_cost_price,     //商品成本价格
        'tk_cost_operating'  =>$tk_cost_operating, //商品成本价格
    );
    return array('status' => 1, 'msg' => "计算价钱成功", 'result' => $result); // 返回结果状态
}
/**
 * 获取商品一二三级分类
 * @return type
 */
function get_goods_category_tree(){
    $tree = $arr = $result = array();
    $cat_list = M('goods_category')->cache(true)->where("is_show = 1")->order('sort_order')->select();//所有分类
    if($cat_list){
        foreach ($cat_list as $val){
            if($val['level'] == 2){
                $arr[$val['parent_id']][] = $val;
            }
            if($val['level'] == 3){
                $crr[$val['parent_id']][] = $val;
            }
            if($val['level'] == 1){
                $tree[] = $val;
            }
        }

        foreach ($arr as $k=>$v){
            foreach ($v as $kk=>$vv){
                $arr[$k][$kk]['sub_menu'] = empty($crr[$vv['id']]) ? array() : $crr[$vv['id']];
            }
        }

        foreach ($tree as $val){
            $val['tmenu'] = empty($arr[$val['id']]) ? array() : $arr[$val['id']];
            $result[$val['id']] = $val;
        }
    }
    return $result;
}

// 全返分类
function get_goods_return_category_tree(){
    $tree = $arr = $result = array();
    // and is_allreturn = 1
    $cat_list = M('goods_category')->cache(true)->where("is_show = 1")->order('sort_order')->select();//所有分类
    if($cat_list){
        foreach ($cat_list as $val){
            if($val['level'] == 2){
                $arr[$val['parent_id']][] = $val;
            }
            if($val['level'] == 3){
                $crr[$val['parent_id']][] = $val;
            }
            if($val['level'] == 1){
                $tree[] = $val;
            }
        }

        foreach ($arr as $k=>$v){
            foreach ($v as $kk=>$vv){
                $arr[$k][$kk]['sub_menu'] = empty($crr[$vv['id']]) ? array() : $crr[$vv['id']];
            }
        }

        foreach ($tree as $val){
            $val['tmenu'] = empty($arr[$val['id']]) ? array() : $arr[$val['id']];
            $result[$val['id']] = $val;
        }
    }
    return $result;
}


/**
 红包商城
 * 获取商品一二三级分类
 * @return type
 */
function get_goods_red_category_tree(){
    $tree = $arr = $result = array();
    $cat_list = M('goods_red_category')->cache(true)->where("is_show = 1")->order('sort_order')->select();//所有分类
    if($cat_list){
        foreach ($cat_list as $val){
            if($val['level'] == 2){
                $arr[$val['parent_id']][] = $val;
            }
            if($val['level'] == 3){
                $crr[$val['parent_id']][] = $val;
            }
            if($val['level'] == 1){
                $tree[] = $val;
            }
        }

        foreach ($arr as $k=>$v){
            foreach ($v as $kk=>$vv){
                $arr[$k][$kk]['sub_menu'] = empty($crr[$vv['id']]) ? array() : $crr[$vv['id']];
            }
        }

        foreach ($tree as $val){
            $val['tmenu'] = empty($arr[$val['id']]) ? array() : $arr[$val['id']];
            $result[$val['id']] = $val;
        }
    }
    return $result;
}

/**
 一乡一品
 * 获取商品一二三级分类
 * @return type
 */
function get_goods_yxyp_category_tree(){
    $tree = $arr = $result = array();
    $cat_list = M('goods_yxyp_category')->cache(true)->where("is_show = 1")->order('sort_order')->select();//所有分类
    if($cat_list){
        foreach ($cat_list as $val){
            if($val['level'] == 2){
                $arr[$val['parent_id']][] = $val;
            }
            if($val['level'] == 3){
                $crr[$val['parent_id']][] = $val;
            }
            if($val['level'] == 1){
                $tree[] = $val;
            }
        }

        foreach ($arr as $k=>$v){
            foreach ($v as $kk=>$vv){
                $arr[$k][$kk]['sub_menu'] = empty($crr[$vv['id']]) ? array() : $crr[$vv['id']];
            }
        }

        foreach ($tree as $val){
            $val['tmenu'] = empty($arr[$val['id']]) ? array() : $arr[$val['id']];
            $result[$val['id']] = $val;
        }
    }
    return $result;
}


/**
 * 写入静态页面缓存
 */
function write_html_cache($html){
    $html_cache_arr = C('HTML_CACHE_ARR');
    $request = think\Request::instance();
    $m_c_a_str = $request->module().'_'.$request->controller().'_'.$request->action(); // 模块_控制器_方法
    $m_c_a_str = strtolower($m_c_a_str);
    //exit('write_html_cache写入缓存<br/>');
    foreach($html_cache_arr as $key=>$val)
    {
        $val['mca'] = strtolower($val['mca']);
        if($val['mca'] != $m_c_a_str) //不是当前 模块 控制器 方法 直接跳过
            continue;

        //if(!is_dir(RUNTIME_PATH.'html'))
            //mkdir(RUNTIME_PATH.'html');
        //$filename =  RUNTIME_PATH.'html'.DIRECTORY_SEPARATOR.$m_c_a_str;
        $filename =  $m_c_a_str;
        // 组合参数  
        if(isset($val['p']))
        {
            foreach($val['p'] as $k=>$v)
                $filename.='_'.$_GET[$v];
        }
        $filename.= '.html';
        \think\Cache::set($filename,$html);
        //file_put_contents($filename, $html);
    }
}

/**
 * 读取静态页面缓存
 */
function read_html_cache(){
    $html_cache_arr = C('HTML_CACHE_ARR');
    $request = think\Request::instance();
    $m_c_a_str = $request->module().'_'.$request->controller().'_'.$request->action(); // 模块_控制器_方法
    $m_c_a_str = strtolower($m_c_a_str);
    //exit('read_html_cache读取缓存<br/>');
    foreach($html_cache_arr as $key=>$val)
    {
        $val['mca'] = strtolower($val['mca']);
        if($val['mca'] != $m_c_a_str) //不是当前 模块 控制器 方法 直接跳过
            continue;

        //$filename =  RUNTIME_PATH.'html'.DIRECTORY_SEPARATOR.$m_c_a_str;
        $filename =  $m_c_a_str;
        // 组合参数        
        if(isset($val['p']))
        {
            foreach($val['p'] as $k=>$v)
                $filename.='_'.$_GET[$v];
        }
        $filename.= '.html';
        $html = \think\Cache::get($filename);
        if($html)
        {
            //echo file_get_contents($filename);
            echo \think\Cache::get($filename);
            exit();
        }
    }
}

/**
 * 获取完整地址
 */
function getTotalAddress($province_id=0, $city_id=0, $district_id=0, $twon_id=0, $address='')
{
    static $regions = null;
    if (!$regions) {
        $regions = M('region')->cache(true)->getField('id,name');
    }
    $total_address = "";
    if($province_id) $total_address .= $regions[$province_id] ?: '';
    if($city_id)     $total_address .= $regions[$city_id]     ?: '';
    if($district_id) $total_address .= $regions[$district_id] ?: '';
    if($twon_id)     $total_address .= $regions[$twon_id]     ?: '';
    if($province_id) $total_address .= $address               ?: '';
    return $total_address;
}

/**
 * 商品库存操作日志
 * @param int $muid 操作 用户ID
 * @param int $stock 更改库存数
 * @param array $goods 库存商品
 * @param string $order_sn 订单编号
 */
function update_stock_log($muid, $stock = 1, $goods, $order_sn = '')
{
    $data['ctime'] = time();
    $data['stock'] = $stock;//库存
    $data['muid'] = $muid;
    $data['goods_id'] = $goods['goods_id'];
    $data['goods_name'] = $goods['goods_name'];
    $data['goods_spec'] = empty($goods['spec_key_name']) ? '' : $goods['spec_key_name'];
    $data['order_sn'] = $order_sn;
    M('stock_log')->add($data);
}


/**
 红包商城
 * 商品库存操作日志
 * @param int $muid 操作 用户ID
 * @param int $stock 更改库存数
 * @param array $goods 库存商品
 * @param string $order_sn 订单编号
 */
function update_stock_log_red($muid, $stock = 1, $goods, $order_sn = '')
{
    $data['ctime'] = time();
    $data['stock'] = $stock;
    $data['muid'] = $muid;
    $data['goods_id'] = $goods['goods_id'];
    $data['goods_name'] = $goods['goods_name'];
    $data['goods_spec'] = empty($goods['spec_key_name']) ? '' : $goods['spec_key_name'];
    $data['order_sn'] = $order_sn;
    M('stock_red_log')->add($data);
}

/**
 一乡一品
 * 商品库存操作日志
 * @param int $muid 操作 用户ID
 * @param int $stock 更改库存数
 * @param array $goods 库存商品
 * @param string $order_sn 订单编号
 */
function update_stock_log_yxyp($muid, $stock = 1, $goods, $order_sn = '')
{
    $data['ctime'] = time();
    $data['stock'] = $stock;
    $data['muid'] = $muid;
    $data['goods_id'] = $goods['goods_id'];
    $data['goods_name'] = $goods['goods_name'];
    $data['goods_spec'] = empty($goods['spec_key_name']) ? '' : $goods['spec_key_name'];
    $data['order_sn'] = $order_sn;
    M('stock_yxyp_log')->add($data);
}

/**
 * 订单支付时, 获取订单商品名称
 * @param unknown $order_id
 * @return string|Ambigous <string, unknown>
 */
function getPayBody($oids){

    if(empty($oids))return "订单ID参数错误";
    $goodsNames =  M('order_goods')->where('order_id','in',$oids)->column('goods_name');
    $gns = implode($goodsNames, ',');
    $payBody = getSubstr($gns, 0, 18);
    return $payBody;
}

function getPayBodyRed($oids){

    if(empty($oids))return "订单ID参数错误";
    $goodsNames =  M('order_red_goods')->where('order_id','in',$oids)->column('goods_name');
    $gns = implode($goodsNames, ',');
    $payBody = getSubstr($gns, 0, 18);
    return $payBody;
}
function getPayBodyYxyp($oids){

    if(empty($oids))return "订单ID参数错误";
    $goodsNames =  M('order_yxyp_goods')->where('order_id','in',$oids)->column('goods_name');
    $gns = implode($goodsNames, ',');
    $payBody = getSubstr($gns, 0, 18);
    return $payBody;
}
/*
* 根据管理员的ID返回管理员的用户名昵称等信息
 * @param unknown $order_id
 * @return string|Ambigous <string, unknown>
*/
function get_admin($admin_id){
    if(empty($admin_id)) return '';
    $r = db('admin')->field('user_name')->cache('admin_data')->find($admin_id);
    return $r['user_name'];
}

// 获取用户米豆
function get_user_midou($uid){
    $midou = 0;
    if(empty($uid)) return '';
    $r = db('users')->field('midou')->cache("user_{$uid}")->find($uid);
    $midou =  $r['midou'];
    return $midou;
}

/*根据数据获取用户昵称*/
function get_user_nickname($uid,$isadmin=0){
    if(empty($uid)) return '';
    if($isadmin == 1){
        //如果是管理员
        $r = db('admin')->field('user_name')->cache("admin_{$uid}")->find($uid);
        $str = "用户名：".$r['user_name'];
    }else{
        $r = db('users')->field('nickname,mobile')->cache("user_{$uid}")->find($uid);
        $str =  "昵称：".$r['nickname'] .'  手机： '. $r['mobile'];
    }
    return 'ID：' .$uid . '   ' . $str;
}
/*获取供货商信息
@author: liyi
@date:2018.04.16
*/
function get_suppliers_info($suppliers_id){
    if(empty($suppliers_id)) return '';
    $suppliers = M('suppliers')->where('suppliers_id = '.$suppliers_id)->find();
    return $suppliers;
}

function get_suppliers_info_uid($user_id){
    if(empty($user_id)) return '';
    $suppliers = M('suppliers')->where('user_id = '.$user_id)->find();
    return $suppliers;
}

/*获取供货商名称
@author: liyi
@date:2018.04.16
*/
function get_suppliers_name($suppliers_id){
    if(empty($suppliers_id)) return '自营';
    $suppliers = M('suppliers')->field('suppliers_name')->find($suppliers_id);
    return $suppliers['suppliers_name'];
}




/*获取供货商会员信息
@author: liyi
@date:2018.04.16
*/
function get_suppliers_user_info($suppliers_id){
    if(empty($suppliers_id)) return '';
    $suppliers = M('suppliers')->where('suppliers_id = '.$suppliers_id)->find();
    $user = get_user_info($suppliers['user_id']);
    return $user;
}

/*获取供货商会员昵称
@author: liyi
@date:2018.04.16
*/
function get_suppliers_user_name($suppliers_id){
    if(empty($suppliers_id)) return '';
    $suppliers = M('suppliers')->field('user_id')->find($suppliers_id);
    $user = get_user_info($suppliers['user_id']);
    return $user['nickname'];
}

/*获取员工用户名
@author: liyi
@date:2018.04.16
*/
function get_staff_username($staff_id){
    if(empty($staff_id)) return '';
    $staff = M('staff')->field('id,uname')->cache(true)->find($staff_id);
    return $staff['uname'];
}

/*判断邀请码
@author: liyi
@date:2018.04.24
*/
function judge_invite_code($invite_code){
    if(empty($invite_code)) return '';
    $staff_num = M('staff')->where('invite_code', $invite_code)->count();
    if( $staff_num > 0){
        $new_invite_code = get_rand_str(10,0,1);
        judge_invite_code($new_invite_code);
    }
    else{
        return $invite_code;
    }
}


/*获取邀请码
@author: liyi
@date:2018.04.24
*/
function get_invite_code($id){
    if(empty($id)) return '';
    $staff = M('staff')->field('invite_code')->where('id', $id)->find();
    if( $staff )
        return $staff['invite_code'];
    else
        return false;
}


/*
用于金额格式化  不能进行四舍五入 
作者：王文凯
2018年4月16日10:10:41
*/
function tk_money_format($money,$lenght=2){
    /*if($money <= 0.01 && $money > 0){
        return 0.01;
    } */
    $multiplier = 0;
    switch ($lenght) {
        case 1:
            $multiplier = 10;
            break;
        case 2:
            $multiplier = 100;
            break;
        case 3:
            $multiplier = 1000;
            break;
        case 4:
            $multiplier = 10000;
            break;
        case 5:
            $multiplier = 100000;
            break;
        case 6:
            $multiplier = 1000000;
            break;
        case 7:
            $multiplier = 10000000;
            break;
        case 8:
            $multiplier = 100000000;
            break;
        case 9:
            $multiplier = 1000000000;
            break;
        default:
            $multiplier = 100;
            break;
    }
    return bcdiv(bcmul($money,$multiplier,$lenght),$multiplier,$lenght);
   # return number_format(intval($money*$multiplier)/$multiplier,$lenght);
}

/*
    根据人员ID 获取 返回需要的列
    王文凯
    2018年4月18日14:13:56
*/

function get_staff_row($id,$return='uname'){
    if(!$id)    return ;
    $row =  db('staff')->field($return)->cache("staff_{$id}")->find($id);
    return $row[$return];
}


/*将小数转换成百分比*/
function percentage($number){
    return $number * 100 . '%';
}





/**
 *  获取执行时间
 *  例如:$t1 = ExecTime();
 *       在一段内容处理之后:
 *       $t2 = ExecTime();
 *  我们可以将2个时间的差值输出:echo $t2-$t1;
 *
 *  @return    int
 */
if ( ! function_exists('ExecTime'))
{
    function ExecTime()
    {
        $time = explode(" ", microtime());
        $usec = (double)$time[0];
        $sec = (double)$time[1];
        return $sec + $usec;
    }
}



/**
 *  短消息函数,可以在某个动作处理后友好的提示信息
 *
 * @param     string  $msg      消息提示信息
 * @param     string  $gourl    跳转地址
 * @param     int     $onlymsg  仅显示信息
 * @param     int     $limittime  限制时间
 * @return    void
 */
function ShowMsg($msg, $gourl, $onlymsg=0, $limittime=0)
{
    header("Content-type:text/html;charset=utf-8");

    $htmlhead  = "<html>\r\n<head>\r\n<title>TK提示信息</title>\r\n<meta http-equiv=\"Content-Type\" content=\"text/html; charset=gb2312\" />\r\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no\">\r\n<meta name=\"renderer\" content=\"webkit\">\r\n<meta http-equiv=\"Cache-Control\" content=\"no-siteapp\" />";
    $htmlhead .= "<base target='_self'/>\r\n<style>div{line-height:160%;}</style></head>\r\n<body leftmargin='0' topmargin='0' bgcolor='#FFFFFF'>".(isset($GLOBALS['ucsynlogin']) ? $GLOBALS['ucsynlogin'] : '')."\r\n<center>\r\n<script>\r\n";
    $htmlfoot  = "</script>\r\n</center>\r\n</body>\r\n</html>\r\n";

    $litime = ($limittime==0 ? 1000 : $limittime);
    $func = '';

    if($gourl=='-1')
    {
        if($limittime==0) $litime = 5000;
        $gourl = "javascript:history.go(-1);";
    }

    if($gourl=='' || $onlymsg==1)
    {
        $msg = "<script>alert(\"".str_replace("\"","“",$msg)."\");</script>";
    }
    else
    {
        //当网址为:close::objname 时, 关闭父框架的id=objname元素
        if(preg_match('/close::/',$gourl))
        {
            $tgobj = trim(preg_replace('/close::/', '', $gourl));
            $gourl = 'javascript:;';
            $func .= "window.parent.document.getElementById('{$tgobj}').style.display='none';\r\n";
        }

        $func .= "      var pgo=0;
      function JumpUrl(){
        if(pgo==0){ location='$gourl'; pgo=1; }
      }\r\n";
        $rmsg = $func;
        $rmsg .= "document.write(\"<br /><div style='width:450px;padding:0px;border:1px solid #DADADA;'>";
        $rmsg .= "<div style='padding:6px;font-size:12px;border-bottom:1px solid #DADADA;background:#DBEEBD url({$GLOBALS['cfg_plus_dir']}/img/wbg.gif)';'><b>提示信息！</b></div>\");\r\n";
        $rmsg .= "document.write(\"<div style='height:130px;font-size:10pt;background:#ffffff'><br />\");\r\n";
        $rmsg .= "document.write(\"".str_replace("\"","“",$msg)."\");\r\n";
        $rmsg .= "document.write(\"";

        if($onlymsg==0)
        {
            if( $gourl != 'javascript:;' && $gourl != '')
            {
                $rmsg .= "<br /><a href='{$gourl}'>如果你的浏览器没反应，请点击这里...</a>";
                $rmsg .= "<br/></div>\");\r\n";
                $rmsg .= "setTimeout('JumpUrl()',$litime);";
            }
            else
            {
                $rmsg .= "<br/></div>\");\r\n";
            }
        }
        else
        {
            $rmsg .= "<br/><br/></div>\");\r\n";
        }
        $msg  = $htmlhead.$rmsg.$htmlfoot;
    }
    echo $msg;
}



/*
TK 2018年4月27日15:36:10
查询子公司下方所有实体店*/
function TK_get_company_store($cid){
    if(empty($cid)){return ;}
    $store_list = S('company_store_list_'.$cid);
    if(empty($store_list)){
        $where['parent_id']  =   ['eq',$cid];
        $store_list = M('company')->field('cid,cname,is_lock,contact,parent_id,parent_id_path,remark,update_time')->where($where)->select();
        S('company_store_list_'.$cid,$store_list);
    }
    return $store_list;
}


/*
TK 2018年4月27日15:36:16
获取当前公司下的所有层级*/
function TK_get_company_level($cid){
    if(empty($cid)) return ;
    $company_level_list = S('company_level_'.$cid);
    if(empty($company_level_list)){
        $where['c_parent_id_path']  =   ['like',"0_{$cid}%"];
        $company_level_list = M('CompanyLevel')->where($where)->select();
        S('company_level_'.$store_list,$company_level_list);
    }
    return $company_level_list;
}



/*付款状态*/

function check_withdrawal($status){
    #2删除作废-1审核失败0申请中1审核通过2付款成功3付款失败
    switch ($status) {
        case -2:
            return '删除作废';
            break;
        case -1:
            return '审核失败';
            break;
        case 0:
            return '申请中';
            break;
        case 1:
            return '审核通过';
            break;
        case 2:
            return '转账成功';
            break;
        case 3:
            return '转账失败';
            break;
    }
}



/**
 * 判断促销商品
 * @return num
 * @author: liyi
 * @date:2018.04.16
 */
function panPromotionGoods($goods_id)
{
    $goods_where['p.start_time'] = array('egt',time());
    $goods_where['p.end_time']   = array('elt',time());
    $goods_where['p.is_end']     = 0;
    $goods_where['g.goods_id']   = $goods_id;
    $goods_where['g.prom_type']  = 3;
    $goods_where['g.is_on_sale'] = 1;

    $count = Db::name('goods')
        ->alias('g')
        ->field('g.*,p.end_time,s.item_id')
        ->join('__PROM_GOODS__ p', 'g.prom_id = p.id')
        ->join('__SPEC_GOODS_PRICE__ s','g.prom_id = s.prom_id AND s.goods_id = g.goods_id','LEFT')
        ->group('g.goods_id')
        ->where($goods_where)
        ->count();
    return $count;
}
/**
 * 判断抢购商品
 * @return num
 * @author: liyi
 * @date:2018.05.21
 */
function panFlashGoods($goods_id)
{
    $where = array(
        'fl.goods_id'  =>$goods_id,
        'fl.start_time'=>array('elt',time()),
        'fl.end_time'  =>array('egt',time()),
        'g.is_on_sale' =>1
    );
    $count = Db::name('flash_sale')->alias('fl')->join('__GOODS__ g', 'g.goods_id = fl.goods_id')
        ->field('*,100*(FORMAT(buy_num/goods_num,2)) as percent')
        ->where($where)
        ->count();
    return $count;
}


/**
 红包商城
 * 判断促销商品
 * @return num
 * @author: liyi
 * @date:2018.04.16
 */
function panPromotionGoodsRed($goods_id)
{
    $goods_where['p.start_time'] = array('egt',time());
    $goods_where['p.end_time']   = array('elt',time());
    $goods_where['p.is_end']     = 0;
    $goods_where['g.goods_id']   = $goods_id;
    $goods_where['g.prom_type']  = 3;
    $goods_where['g.is_on_sale'] = 1;

    $count = Db::name('goods_red')
        ->alias('g')
        ->field('g.*,p.end_time,s.item_id')
        ->join('__PROM_RED_GOODS__ p', 'g.prom_id = p.id')
        ->join('__SPEC_RED_GOODS_PRICE__ s','g.prom_id = s.prom_id AND s.goods_id = g.goods_id','LEFT')
        ->group('g.goods_id')
        ->where($goods_where)
        ->count();
    return $count;
}
/**
 红包商城
 * 判断抢购商品
 * @return num
 * @author: liyi
 * @date:2018.05.21
 */
function panFlashGoodsRed($goods_id)
{
    $where = array(
        'fl.goods_id'  =>$goods_id,
        'fl.start_time'=>array('elt',time()),
        'fl.end_time'  =>array('egt',time()),
        'g.is_on_sale' =>1
    );
    $count = Db::name('flash_red_sale')->alias('fl')->join('__GOODS_RED__ g', 'g.goods_id = fl.goods_id')
        ->field('*,100*(FORMAT(buy_num/goods_num,2)) as percent')
        ->where($where)
        ->count();
    return $count;
}

/**
 一乡一品
 * 判断促销商品
 * @return num
 * @author: liyi
 * @date:2018.04.16
 */
function panPromotionGoodsYxyp($goods_id)
{
    $goods_where['p.start_time'] = array('egt',time());
    $goods_where['p.end_time']   = array('elt',time());
    $goods_where['p.is_end']     = 0;
    $goods_where['g.goods_id']   = $goods_id;
    $goods_where['g.prom_type']  = 3;
    $goods_where['g.is_on_sale'] = 1;

    $count = Db::name('goods_yxyp')
        ->alias('g')
        ->field('g.*,p.end_time,s.item_id')
        ->join('__PROM_YXYP_GOODS__ p', 'g.prom_id = p.id')
        ->join('__SPEC_YXYP_GOODS_PRICE__ s','g.prom_id = s.prom_id AND s.goods_id = g.goods_id','LEFT')
        ->group('g.goods_id')
        ->where($goods_where)
        ->count();
    return $count;
}
/**
 一乡一品
 * 判断抢购商品
 * @return num
 * @author: liyi
 * @date:2018.05.21
 */
function panFlashGoodsYxyp($goods_id)
{
    $where = array(
        'fl.goods_id'  =>$goods_id,
        'fl.start_time'=>array('elt',time()),
        'fl.end_time'  =>array('egt',time()),
        'g.is_on_sale' =>1
    );
    $count = Db::name('flash_yxyp_sale')->alias('fl')->join('__GOODS_YXYP__ g', 'g.goods_id = fl.goods_id')
        ->field('*,100*(FORMAT(buy_num/goods_num,2)) as percent')
        ->where($where)
        ->count();
    return $count;
}

/**
 米豆商城
 * 换算米豆价
 */
function getMidou($goods_id,$item_id=0){
    $midouInfo = S("get_midou_{$goods_id}_{$item_id}");
    if($midouInfo){
        return $midouInfo;
    }
    $midou_use_percent = tpCache('shoppingred.midou_use_percent'); // 购买商品 使用米豆 比率
    $midou_rate        = tpCache('shoppingred.midou_rate');        // 米豆兑换比
    $goodsInfo = M('goods_red')->where('goods_id',$goods_id)->find();

    if(!$goodsInfo) return '';

    if($goodsInfo['is_z_change'] != 1) $midou_use_percent = $goodsInfo['midou_use_percent'];  // 购买商品 使用米豆 比率

    $price = $goodsInfo['shop_price'];

    if($item_id){
        $spec_goods = M('spec_red_goods_price')->where("goods_id = ".$goods_id." AND item_id = ".$item_id)->find();
        if($spec_goods) {
            $price = $spec_goods['price'];
        }
    }
    $midou_price = $price * $midou_use_percent/100;  // 可使用 米豆兑换 金额           
    $midouInfo['midou']       = num_flaot3(($midou_price/$midou_rate)); // 兑换后的米豆
    $midouInfo['midou_money'] = $price - $midou_price;              // 剩余使用现金部分 
    $midouInfo['midou_index'] = num_flaot3(($price/$midou_rate));   // 显示的米豆
    S("get_midou_{$goods_id}_{$item_id}",$midouInfo);
    return $midouInfo;
}


/*
* 可返米豆数量
*/
function returnMidou($goods_id,$item_id=0){
    $midouInfo = S("return_midou_{$goods_id}_{$item_id}");
    if($midouInfo){
        return $midouInfo;
    }
    $midou_back_percent = tpCache('shoppingred.midou_back_percent'); // 购买商品 可返米豆 比率
    $midou_rate         = tpCache('shoppingred.midou_rate');         // 米豆兑换比
    $goodsInfo = M('goods')->where('goods_id',$goods_id)->cache(true)->find();
    if(!$goodsInfo) return '';

    if($goodsInfo['is_z_back'] != 1) $midou_back_percent = $goodsInfo['midou_back_percent'];  // 购买商品 使用米豆 比率

    if($item_id){
        $spec_goods = M('spec_goods_price')->where("goods_id = ".$goods_id." AND item_id = ".$item_id)->cache(true)->find();
        if(!$spec_goods) return '';

        $midou_price = $spec_goods['price']*$midou_back_percent/100;   // 可返 米豆兑换 金额
        $midou       = num_flaot3(($midou_price/$midou_rate));         // 兑换后的米豆
        $midouInfo['midou']       = $midou;

    } else {
        $midou_price = $goodsInfo['shop_price']*$midou_back_percent/100;   // 可返 米豆兑换 金额
        $midou       = num_flaot3(($midou_price/$midou_rate));             // 兑换后的米豆
        $midouInfo['midou']       = $midou;
    }
    S("return_midou_{$goods_id}_{$item_id}",$midouInfo);
    return $midouInfo;
}

/*
* 一乡一品可返米豆数量
*/
function returnyxypMidou($goods_id,$item_id=0){
    $midouInfo = S("midou_{$goods_id}_{$item_id}");
    if($midouInfo){
        return $midouInfo;
    }
    $midou_back_percent = tpCache('shoppingred.midou_back_percent'); // 购买商品 可返米豆 比率
    $midou_rate         = tpCache('shoppingred.midou_rate');         // 米豆兑换比
    $goodsInfo = M('goods')->where('goods_id',$goods_id)->cache(true)->find();
    if(!$goodsInfo) return '';

    if($goodsInfo['is_z_back'] != 1) $midou_back_percent = $goodsInfo['midou_back_percent'];  // 购买商品 使用米豆 比率

    if($item_id){
        $spec_goods = M('spec_goods_price')->where("goods_id = ".$goods_id." AND item_id = ".$item_id)->cache(true)->find();
        if(!$spec_goods) return '';

        $midou_price = $spec_goods['price']*$midou_back_percent/100;   // 可返 米豆兑换 金额
        $midou       = num_flaot3(($midou_price/$midou_rate));         // 兑换后的米豆
        $midouInfo['midou']       = $midou;

    } else {
        $midou_price = $goodsInfo['shop_price']*$midou_back_percent/100;   // 可返 米豆兑换 金额
        $midou       = num_flaot3(($midou_price/$midou_rate));             // 兑换后的米豆
        $midouInfo['midou']       = $midou;
    }
    S("midou_yxyp_{$goods_id}_{$item_id}",$midouInfo);
    return $midouInfo;
}

#隐藏手机中间四位
function hide_mobile($mobile){
    if($mobile) {
        return substr_replace($mobile, '****', 3, 4);
    }else{
        return '***********';
    }
}

#银行列表
#员工中心，提现列表， 成员中心，提现列表
function tk_bank_list(){
    return [//'微信',
            '支付宝',
            '华夏银行',
            '中国人民银行',
            '工商银行',
            '邮政储蓄',
            ];
}


/*扫码支付，代付款 订单编码
    不谢参数就是 代付，
    写了参数就是扫码支付
*/
function get_paid_sn($is_paid=0){
    $sn = null;
    while(true) {
        $random = rand(1000,9999);
        if($is_paid != 0){
            $sn = 'mypays_' . NOW_TIME . $random;
            $table_name = 'staff_mypays';
        }else{
            $sn = 'staff_paid_' . NOW_TIME . $random;
            $table_name = 'staff_paid';
        }        
        $sn_count = db($table_name)->where("paid_sn = '{$sn}'")->count();
        if($sn_count == 0)
            break;
    } 
    return $sn;
}


/*余额支付*/
function ak_get_pays($user,$is_midou=0){
    $paypwd = I('post.paypsw/s');
    if ($user['is_lock'] == 1) {
        $res['status']  =   0;
        $res['info']    =   '账号异常已被锁定，不能使用余额支付！';
        exit(json_encode($res));
        // 用户被冻结不能使用余额支付
    }
    if (empty($user['paypwd'])) {
        $res['status']  =   0;
        $res['info']    =   '请先设置支付密码';
        exit(json_encode($res));
    }
    if (empty($paypwd)) {
        $res['status']  =   0;
        $res['info']    =   '请输入支付密码';
        exit(json_encode($res));
    }
    if (encrypt($paypwd) !== $user['paypwd']) {
        $res['status']  =   0;
        $res['info']    =   '支付密码错误 ';
        exit(json_encode($res));
    }

    $is_xxpay  = I('is_xxpay/d',0);
    $order_id  = I('order_id/d',0);
    $order_sn  = I('order_sn','');
    $order_num = I('order_num/d',1);
    if($is_xxpay == 1){
        if($pays_id = I('pays_id/d')){
            $where['id']    =   ['eq',$pays_id];
            $pay_order = M('staff_mypays')->where($where)->find();
            if($pay_order['pay_status'] == 1){
                $res['status']  =   0;
                $res['info']    =   '此订单，已完成支付!';
                exit(json_encode($res));
            }
            $pay_money = $pay_order['money'];
            M('staff_mypays')->where($where)->save(array('pay_code'=>'yu`e','pay_name'=>'余额支付'));
            $user_info = M('users')->where("user_id", $user['user_id'])->find();// 找出这个用户

            if ($pay_money && ($pay_money > $user_info['user_money'])){
                $res['status']  =   0;
                $res['info']    =   "你的账户可用余额为:" . tk_money_format($user_info['user_money']);
                exit(json_encode($res));
            }
            accountLog($user_info['user_id'],($pay_money * -1),0,0,"线下扫码消费",0,0);
            $save_data['pay_time']  =   NOW_TIME;
            $save_data['pay_status']    =   1;
            M('staff_mypays')->where($where)->update($save_data);
            red_back_start($pay_order);
            tk_store_money($pay_order);
            feiePrint($pay_order);
            $res['status']  =   1;
            $res['info']    =   '余额支付成功！';
            exit(json_encode($res));
        }

    }elseif($order_id || $order_sn ){
        if($order_sn)
            $where['order_sn'] = ['eq',$order_sn];
        else
            $where['order_id'] = ['eq',$order_id];

        $midou = 0;
        $user_info = M('users')->where("user_id", $user['user_id'])->find();// 找出这个用户
        if($is_midou == 0){
            // 订单数据表
            $table_name = 'order';
            // 订单数量大于1
            if($order_num > 1){
                // 如果不存在 订单号
                if(!$order_sn)$order_sn = M($table_name)->where($where)->getField('order_sn');
                $where_or['parent_sn']  = ['eq',$order_sn];
                // 获取全部订单列表
                $order_list = M($table_name)->where($where)->whereOr($where_or)->select();                
            } else {
                $order_list = M($table_name)->where($where)->select();
            }
            // dump($order_list);die();
            # echo M($table_name)->getlastsql();
            foreach ($order_list as $key => $value) {
                if($value['pay_status'] == 1){
                    $res['status'] = 0;
                    $res['info']   = '该订单已经支付';
                    exit(json_encode($res));
                }
                $user_money += $value['order_amount'];
            }

            if($order_num > 1){
                M($table_name)->where($where)->whereOr($where_or)->save(array('pay_code'=>'yu`e','pay_name'=>'余额支付'));
            } else {
                M($table_name)->where($where)->save(array('pay_code'=>'yu`e','pay_name'=>'余额支付'));
            }


            if ($user_money && ($user_money > $user_info['user_money'])){
                $res['status']  =   0;
                $res['info']    =   "你的账户可用余额为:" . tk_money_format($user_info['user_money']);
                exit(json_encode($res));
            }
            
            # model('order')->saveAll($order_update_sql);
        }else if ($is_midou ==2){

            $table_name = 'order_yxyp';
            if($order_num > 1){
                if(!$order_sn)$order_sn = M($table_name)->where($where)->getField('order_sn');
                $where_or['parent_sn']  = ['eq',$order_sn];
                $order_list = M($table_name)->where($where)->whereOr($where_or)->select();                
            } else {
                $order_list = M($table_name)->where($where)->select();
            }
            foreach ($order_list as $key => $value) {
                $user_money += $value['order_amount'];
                $midou += $value['midou'];
            }

            if($order_num > 1){
                M($table_name)->where($where)->whereOr($where_or)->save(array('pay_code'=>'yu`e','pay_name'=>'余额支付'));
            } else {
                M($table_name)->where($where)->save(array('pay_code'=>'yu`e','pay_name'=>'余额支付'));
            }
            
            if($midou > $user_info['midou']){
                $res['status']  =   0;
                $res['info']    =   "米豆余额不足，可用余额为：" . $user_info['midou'];
                exit(json_encode($res));
            }
            if ($user_money && ($user_money > $user_info['user_money'])){
                $res['status']  =   0;
                $res['info']    =   "用户余额不足，可用余额为:" . tk_money_format($user_info['user_money']);
                exit(json_encode($res));
            }
        }else{

            $table_name = 'order_red';
            if($order_num > 1){
                if(!$order_sn)$order_sn = M($table_name)->where($where)->getField('order_sn');
                $where_or['parent_sn']  = ['eq',$order_sn];
                $order_list = M($table_name)->where($where)->whereOr($where_or)->select();                
            } else {
                $order_list = M($table_name)->where($where)->select();
            }
            foreach ($order_list as $key => $value) {
                $user_money += $value['order_amount'];
                $midou += $value['midou'];
            }

            if($order_num > 1){
                M($table_name)->where($where)->whereOr($where_or)->save(array('pay_code'=>'yu`e','pay_name'=>'余额支付'));
            } else {
                M($table_name)->where($where)->save(array('pay_code'=>'yu`e','pay_name'=>'余额支付'));
            }
            
            if($midou > $user_info['midou']){
                $res['status']  =   0;
                $res['info']    =   "米豆余额不足，可用余额为：" . $user_info['midou'];
                exit(json_encode($res));
            }
            if ($user_money && ($user_money > $user_info['user_money'])){
                $res['status']  =   0;
                $res['info']    =   "用户余额不足，可用余额为:" . tk_money_format($user_info['user_money']);
                exit(json_encode($res));
            }
        }

        foreach ($order_list as $key => $value) {
    
            if(tpCache('shopping.reduce') == 2) {

                if ($value['order_prom_type'] == 6) {
                    $team = \app\common\model\TeamActivity::get($value['order_prom_id']);
                    if ($team['team_type'] != 2) {             
                        if($is_midou == 0){
                            $res = minus_stock($value);
                        }else{
                            $res = minus_stock_red($value);
                        }
                    }
                } else {
                    if($is_midou == 0){
                        $res = minus_stock($value);
                    }else{
                        $res = minus_stock_red($value);
                    }
                }
            }
            if(isset($res) && $res['status'] == 0){
                exit(json_encode($res));
            }
            $c = $value;
            if(isset($c['midou'])){
                $c['midou'] =   $c['midou'] *   -1;
                change_midou($c,'米豆商城下单消费');
            }

            accountLog($user_info['user_id'],($value['order_amount'] * -1),($midou * -1),0,"现金商城下单消费",0,0,$value['order_id'],$value['order_sn']);
            //2018-9-25 王牧田修改  订单提交支付后直接确认（余额付款）
            $order_update_sql[] =   ['order_id'=>$value['order_id'],'pay_time'=>NOW_TIME,'pay_status'=>1,'order_status'=>1];
        }

        # dump($order_update_sql);die;
        model($table_name)->saveAll($order_update_sql);
        $res['status']  =   1;
        $res['info']    =   '余额支付成功！';
        exit(json_encode($res));

    }
}

function feiePrint($pay_order,$pay_name = '余额支付'){
    if(empty($pay_order['pay_name'])){
        $pay_order['pay_name']  =   $pay_name;
    }
    $feieProject = new \Feie\FeieService();
    $feieProject->setOrder($pay_order);
    $feieProject->wp_print();
}


/**
 * 功能：生成二维码
 * @param string $qrData 手机扫描后要跳转的网址
 * @param string $qrLevel 默认纠错比例 分为L、M、Q、H四个等级，H代表最高纠错能力
 * @param string $qrSize 二维码图大小，1－10可选，数字越大图片尺寸越大
 * @param string $savePath 图片存储路径
 * @param string $savePrefix 图片名称前缀
 */
function createQRcode($savePath, $qrData = 'PHP QR Code :)', $qrLevel = 'L', $qrSize = 4, $savePrefix = '')
{
    if (!isset($savePath)) return '';
    vendor("phpqrcode.phpqrcode");
    //设置生成png图片的路径
    $PNG_TEMP_DIR = $savePath;

    //检测并创建生成文件夹
    if (!file_exists($PNG_TEMP_DIR)) {
        mkdir($PNG_TEMP_DIR,0777,true);
    }
    $filename = $PNG_TEMP_DIR . 'test.png';
    $errorCorrectionLevel = 'L';
    if (isset($qrLevel) && in_array($qrLevel, ['L', 'M', 'Q', 'H'])) {
        $errorCorrectionLevel = $qrLevel;
    }
    $matrixPointSize = 4;
    if (isset($qrSize)) {
        $matrixPointSize = min(max((int)$qrSize, 1), 10);
    }
    $QRcode = new \QRcode();
    if (isset($qrData)) {
        if (trim($qrData) == '') {
            die('data cannot be empty!');
        }
        //生成文件名 文件路径+图片名字前缀+md5(名称)+.png
        $filename = $PNG_TEMP_DIR . $savePrefix . md5($qrData . '|' . $errorCorrectionLevel . '|' . $matrixPointSize) . '.png';
        //开始生成
        $QRcode->png($qrData, $filename, $errorCorrectionLevel, $matrixPointSize, 2);
    } else {
        //默认生成
        $QRcode->png('PHP QR Code :)', $filename, $errorCorrectionLevel, $matrixPointSize, 2);
    }
    if (file_exists($PNG_TEMP_DIR . basename($filename)))
        return basename($filename);
    else
        return FALSE;
}