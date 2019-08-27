<?php

function init(){

    $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
    $url =  $http_type . $_SERVER['HTTP_HOST'];
    define("URL", $url);
}
init();
/*接口格式*/
function formt($data = array(),$code=200,$message = '操作成功'){
    if (!is_numeric($code)){
        return '错误';
    }
    // $message = UnicodeEncode($message);
    // $data = UnicodeEncode($data);
    $result = array(
        'code' => $code,
        'message' => $message,
        'data' => $data
    );
    echo json_encode($result);
    exit;
}

function UnicodeEncode($str){
    preg_match_all('/./u',$str,$matches);
    $unicodeStr = "";
    foreach($matches[0] as $m){
        $unicodeStr .= "&#".base_convert(bin2hex(iconv('UTF-8',"UCS-4",$m)),16,10);
    }
    return $unicodeStr;
}

function advurl($data = array()){
    foreach ($data as $key => $value) {
        $imagesurl[$key]=$value;
        // $imagesurl[$key]['ad_link']='/pages/search/search';
        if (!empty($value['ad_link'])) {
            # code...
            if (strpos($value['ad_link'],'index.php')) {
                $str= cut_str($value['ad_link'],'/',6);
                if (strpos($str,'.') !== false) {
                    $strurl = substr($str,0,strpos($str, '.'));
                }else{
                    $strurl = $str;
                }
                if ($strurl=='search') {
                    $str= cut_str($value['ad_link'],'/',-1);
                    if (strpos($str,'q=') !== false) {
                        $strs=cut_str($str,'?',1);
                    }
                    $imagesurl[$key]['ad_link']= '/pages/classlist/classlist?'.$strs;
                }else if($strurl=='goodsList'){
                    $strs=trim(strrchr($value['ad_link'], '/'),'/');
                    $strs=substr($strs,0,strpos($strs, '.'));
                    $imagesurl[$key]['ad_link']= '/pages/goodsList/goodsList?id='.$strs;
                }else if($strurl=='goodsInfo'){
                    $strs=trim(strrchr($value['ad_link'], '/'),'/');
                    $strs=substr($strs,0,strpos($strs, '.'));
                    $imagesurl[$key]['ad_link']= '/pages/goodsdetail/goodsdetail?id='.$strs;
                }else if($strurl=='detail'){
                    $strs=trim(strrchr($value['ad_link'], '/'),'/');
                    $strs=substr($strs,0,strpos($strs, '.'));
                    $imagesurl[$key]['ad_link']= '/pages/help/help?article_id='.$strs;
                }else{
                    
                    $imagesurl[$key]['ad_link']= '';
                }
            }else{
                $str= cut_str($value['ad_link'],'/',5);
                if (strpos($str,'.') !== false) {
                    $strurl = substr($str,0,strpos($str, '.'));
                }else{
                    $strurl = $str;
                }

                if ($strurl=='search') {
                    $str= cut_str($value['ad_link'],'/',-1);
                    if (strpos($str,'q=') !== false) {
                        $strs=cut_str($str,'?',1);
                    }
                    $imagesurl[$key]['ad_link']= '/pages/classlist/classlist?'.$strs;
                }else if($strurl=='goodsList'){
                    $strs=trim(strrchr($value['ad_link'], '/'),'/');
                    $strs=substr($strs,0,strpos($strs, '.'));
                    $imagesurl[$key]['ad_link']= '/pages/goodsList/goodsList?id='.$strs;
                }else if($strurl=='goodsInfo'){
                    $strs=trim(strrchr($value['ad_link'], '/'),'/');
                    $strs=substr($strs,0,strpos($strs, '.'));
                    $imagesurl[$key]['ad_link']= '/pages/goodsdetail/goodsdetail?id='.$strs;
                }else if($strurl=='detail'){
                    $strs=trim(strrchr($value['ad_link'], '/'),'/');
                    $strs=substr($strs,0,strpos($strs, '.'));
                    $imagesurl[$key]['ad_link']= '/pages/help/help?article_id='.$strs;
                }else{
                     $imagesurl[$key]['ad_link']= '';
                }
            }
            // dump($strs.'   '.$value['ad_id']);
            // dump($str);
            // $imagesurl[$key]['ad_links']= $str;
        }else{
            $imagesurl[$key]['ad_link']= '';
        }
        $imagesurl[$key]['ad_code']=URL.$value['ad_code'];
    }
    return $imagesurl;
}

function goodsimgurl($data = array()){
    foreach ($data as $key => $value) {
        $imagesurl[$key]=$value;
        $str=$value['goods_content'];
        preg_match_all('/src=&quot;(.+?)&quot;/', $str, $matches);
        foreach ($matches['1'] as $k => $v) {
            if (strstr($v,'http')) {
                $goods_content[$k]=$v;
            }else{
                $goods_content[$k]=URL.$v;
            }
        }
        $goodsimg = M('goods_images')->where('goods_id='.$value['goods_id'])->field('image_url')->select();
        foreach ($goodsimg as $keys => $values) {
           
            if (file_exists($values['image_url'])==false) {
                if (strstr(goods_thum_images($value['goods_id'],400,400),'http')) {
                     $goodsimg[$keys]=goods_thum_images($value['goods_id'],400,400);
                }else{
                     $goodsimg[$keys]=URL.goods_thum_images($value['goods_id'],400,400);
                }
            }else{
                if (strstr($values['image_url'],'http')) {
                    $goodsimg[$keys]= $values['image_url'];
                }else{
                    $goodsimg[$keys]= URL.$values['image_url'];
                }
            }

        }
        $imagesurl[$key]['goods_img']=$goodsimg;
        if ($goods_content) {
            $imagesurl[$key]['goods_content']=$goods_content;
        }
        if ($value['original_img']) {
            $imagesurl[$key]['original_img']=URL.$value['original_img'];
        }
    }
    return $imagesurl;
}
function noticeimgurl($data = array()){
    foreach ($data as $key => $value) {
        $imagesurl[$key]=$value;
        $str=$value['content'];
        preg_match_all('/src=&quot;(.+?)&quot;/', $str, $matches);
        foreach ($matches['1'] as $k => $v) {
            // $goods_content[$k]=URL.$v;
            if (strstr($v,'http')) {
                $goods_content[$k]=$v;
            }else{
                $goods_content[$k]=URL.$v;
            }
        }
        if (strstr(article_thum_images($value['article_id'],420,400),'http')) {
             $imagesurl[$key]['article_thum_images']=article_thum_images($value['article_id'],420,400);
        }else{
             $imagesurl[$key]['article_thum_images']=URL.article_thum_images($value['article_id'],420,400);
        }
        if ($goods_content) {
            $imagesurl[$key]['content']=$goods_content;
        }
        
    }
    return $imagesurl;
}
function notimgurl($data = array()){
        $str=$data['content'];
        $imagesurl=$data;

        if (strstr($str,'src')) {
            preg_match_all('/src=&quot;(.+?)&quot;/', $str, $matches);
            foreach ($matches['1'] as $k => $v) {
                if (strstr($v,'http')) {
                    $goods_content[$k]=$v;
                }else{
                    $goods_content[$k]=URL.$v;
                }
            }
            $imagesurl['content_img']=$goods_content;
            unset($imagesurl['content']);
        }else{
            $html_string = htmlspecialchars_decode($str);
            //将空格替换成空
            $content = str_replace(" ", "", $html_string);
            $content = str_replace("&nbsp;", "", $content);
            //函数剥去字符串中的 HTML、XML 以及 PHP 的标签,获取纯文本内容
            $goods_content = strip_tags($content);
            $imagesurl['content']=$goods_content;
        }
        if (strstr($data['thumb'],'http')) {
            $imagesurl['thumb']=$data['thumb'];
        }else{
            $imagesurl['thumb']=URL.$data['thumb'];
        }
        
        
       
        // if ($goods_content) {
        //     $imagesurl['content']=$goods_content;
        // }
    return $imagesurl;
}
function goodsinfoimgurl($data = array()){
        $imagesurl=$data;
        $str=$data['goods_content'];
        preg_match_all('/src=&quot;(.+?)&quot;/', $str, $matches);
        foreach ($matches['1'] as $k => $v) {
            $goods_content[$k]=URL.$v;
        }

        $goodsimg = M('goods_images')->where('goods_id='.$data['goods_id'])->field('image_url')->select();
        foreach ($goodsimg as $keys => $values) {
           
            // if (file_exists($values['image_url'])==false) {
            //     $goodsimg[$keys]=URL.goods_thum_images($data['goods_id'],400,400);
            // }else{
            //      $goodsimg[$keys]= URL.$values['image_url'];
            // }
             if (file_exists($values['image_url'])==false) {
                if (strstr(goods_thum_images($data['goods_id'],400,400),'http')) {
                     $goodsimg[$keys]=goods_thum_images($data['goods_id'],400,400);
                }else{
                     $goodsimg[$keys]=URL.goods_thum_images($data['goods_id'],400,400);
                }
            }else{
                if (strstr($values['image_url'],'http')) {
                    $goodsimg[$keys]= $values['image_url'];
                }else{
                    $goodsimg[$keys]= URL.$values['image_url'];
                }
            }

        }
        $imagesurl['goods_img']=$goodsimg;
        if ($goods_content) {
            $imagesurl['goods_content']=$goods_content;
        }
        if ($data['original_img']) {
            $imagesurl['original_img']=URL.$data['original_img'];
        }
    return $imagesurl;
}

function cut_str($str,$sign,$number){
    $array=explode($sign, $str);
    $length=count($array);
    if($number<0){
        $new_array=array_reverse($array);
        $abs_number=abs($number);
        if($abs_number>$length){
            return 'error';
        }else{
            return $new_array[$abs_number-1];
        }
    }else{
        if($number>=$length){
            return 'error';
        }else{
            return $array[$number];
        }
    }
}


function object_to_array($obj) {
    $obj = (array)$obj;
    foreach ($obj as $k => $v) {
        if (gettype($v) == 'resource') {
            return;
        }
        if (gettype($v) == 'object' || gettype($v) == 'array') {
            $obj[$k] = (array)object_to_array($v);
        }
    }
 
    return $obj;

}

function curl_get_https($url){
    $curl = curl_init(); // 启动一个CURL会话
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, true);  // 从证书中检查SSL加密算法是否存在
    $tmpInfo = curl_exec($curl);     //返回api的json对象
    //关闭URL请求
    curl_close($curl);
    return $tmpInfo;    //返回json对象
}

 /**
     * 获取商品规格
     * @param $goods_id|商品id
     * @return array
     */
    function get_spec($goods_id)
    {
        //商品规格 价钱 库存表 找出 所有 规格项id
        $keys = M('SpecGoodsPrice')->where("goods_id", $goods_id)->getField("GROUP_CONCAT(`key` ORDER BY store_count desc SEPARATOR '_') ");
        $filter_spec = array();
        if ($keys) {
            $specImage = M('SpecImage')->where(['goods_id'=>$goods_id,'src'=>['<>','']])->getField("spec_image_id,src");// 规格对应的 图片表， 例如颜色
            $keys = str_replace('_', ',', $keys);
            $sql = "SELECT a.name,a.order,b.* FROM __PREFIX__spec AS a INNER JOIN __PREFIX__spec_item AS b ON a.id = b.spec_id WHERE b.id IN($keys) ORDER BY b.id";
            $filter_spec2 = \think\Db::query($sql);
            foreach ($filter_spec2 as $key => $val) {
                $filter_spec[$val['name']][] = array(
                    'item_id' => $val['id'],
                    'item' => $val['item'],
                    'src' => URL.$specImage[$val['id']],
                );
            }
            $filter_specs = array();
            foreach ($filter_spec as $keys => $vals) {
                $filter_specs[$keys]['name'] = $keys;
                $filter_specs[$keys]['items']= $vals;
            }
            $filter_spec_list = array();
            foreach ($filter_specs as $keyss => $valss) {
                $filter_spec_list[] =$valss;
            }
//            foreach ($filter_spec2 as $key => $val) {
//                $filter_spec[] = array(
//                    'item_id' => $val['id'],
//                    'item' => $val['item'],
//                    'src' => URL.$specImage[$val['id']],
//                );
//            }
        }
        return $filter_spec_list;
    }

    /**
 * 实体店电话
 */
function suppliersphone($id)
{
    $map = array();
    $map['suppliers_id'] = $id;
    // db('suppliers')->where(["suppliers_id"=>$row['suppliers_id']])->value("suppliers_phone")
    $chengyuan = db('suppliers')->where($map)->value("suppliers_phone");
    return $chengyuan;
}

function commentimg($img){
    if (!empty($img)) {
         if (strstr($img,'http')) {
                 $img = $img;
            }else{
                 $img = URL.$img;
            }
    }
    return $img;
}

    /**
 * 实体店电话
 */
function isreturn($id)
{
    $map = array();
    $map['suppliers_id'] = $id;
    // db('suppliers')->where(["suppliers_id"=>$row['suppliers_id']])->value("suppliers_phone")
    $chengyuan = db('return_goods')->where($map)->value("suppliers_phone");
    return $chengyuan;
}

function contentURL($data = array()){
    $noticle = $data;
    $str = $data['content'];
    $noticle['content']= str_replace("img src=&quot;","img src=&quot;".URL,$str);
    return $noticle;
}

function goodContentURL($data = array()){
    $list = $data;
    $str = $data['goods_content'];
    $list['goods_content']= str_replace("img src=&quot;","img src=&quot;".URL,$str);
    return $list;
}
