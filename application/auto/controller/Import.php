<?php
/**
 * Created by PhpStorm.
 * User: xujiantong
 * Email: 314783087@qq.com
 * Date: 2018/1/12
 * Time: 16:49
 */
namespace app\auto\controller;

use think\Controller;
use think\Loader;

use PHPExcel;
use PHPExcel_IOFactory;
use PHPExcel_Cell;
class Import extends Controller
{
    function index()
    {
        if(request()->isPost()) {

            Loader::import('PHPExcel.PHPExcel');
            Loader::import('PHPExcel.PHPExcel.PHPExcel_IOFactory');
            Loader::import('PHPExcel.PHPExcel.PHPExcel_Cell');
            //实例化PHPExcel
            $objPHPExcel = new \PHPExcel();
            $file = request()->file('excel');

            if ($file) {

                $file_types = explode(".", $_FILES ['excel'] ['name']); // ["name"] => string(25) "excel文件名.xls"
                $file_type = $file_types [count($file_types) - 1];//xls后缀
                $file_name = $file_types [count($file_types) - 2];//xls去后缀的文件名
                /*判别是不是.xls文件，判别是不是excel文件*/
                if (strtolower($file_type) != "xls" && strtolower($file_type) != "xlsx") {
                    echo '不是Excel文件，重新上传';
                    die;
                }

                $info = $file->move(ROOT_PATH . 'public' . DS . 'excel');//上传位置
                $path = ROOT_PATH . 'public' . DS . 'excel' . DS;
                $file_path = $path . $info->getSaveName();//上传后的EXCEL路径
                //echo $file_path;//文件路径

                //获取上传的excel表格的数据，形成数组
                $re = $this->actionRead($file_path, 'utf-8');
                array_splice($re, 1, 0);
                $is_midou = I('get.is_midou/d',1);
             #   unset($re[0]);
                if($is_midou == 1){
                    $lastgoods_id = db('goods_red')->order('goods_id desc')->getField('goods_id');
                    $lastgoods_id++ ;
                    foreach ($re as $key => $value) {
                        if($value['9'] == '是'){
                            $is_z_change = 1;
                            $midou_use_percent = 100;
                        }else{
                            $is_z_change = 0;
                            $midou_use_percent = $value['10'];
                        }
                        if($value['11'] == '是'){
                            $is_z_back = 1;
                            $midou_back_percent = 100;
                        }else{
                            $is_z_back = 0;
                            $midou_back_percent = $value['12'];
                        }
                        if($value[19] == '是'){
                            $is_free_shipping = 1;
                        }else{
                            $is_free_shipping = 0;
                        }
                     #   $cat_name = $value[7];
                      
                        $cat_id = db('goods_red_category')->where("name = '{$value[8]}'")->getField('id');
                        $insert_data[]   =   ['goods_name'=>$value[1],
                                        'goods_remark'=>$value[6],
                                        'is_z_change'=>$is_z_change,
                                        'midou_use_percent'=>$midou_use_percent,
                                        'is_z_back' =>$is_z_back,
                                        'midou_back_percent'=>$midou_back_percent,
                                        'shop_price'=>$value[14],
                                        'market_price'=>$value[15],
                                        'cost_price'=>$value[16],
                                        'cost_operating'=>$value[17],
                                        'is_free_shipping'=>$is_free_shipping,
                                        'store_count'=>$value[21],
                                        'keywords'=>$value[22],
                                        'cat_id'=>$cat_id,
                                        'goods_sn'=>'MDS_00'.$lastgoods_id,
                                        ];
                        $lastgoods_id++ ;
                    }
                    model('goods_red')->insertAll($insert_data);
                }else{
                    $lastgoods_id = db('goods')->order('goods_id desc')->getField('goods_id');
                    $lastgoods_id++ ;

                    foreach ($re as $key => $value) {
                        if($value[17] == '是'){
                            $is_free_shipping = 1;
                        }else{
                            $is_free_shipping = 0;
                        }
                        if($value[9] == '是'){
                            $is_z_back = 1;
                            $midou_back_percent = 100;
                        }else{
                            $is_z_back = 0;
                            $midou_back_percent = $value['10'];
                        }

                        $cat_id = db('goods_category')->where("name = '{$value[8]}'")->getField('id');
                        $insert_data[]   =   ['goods_name'=>$value[1],
                                        'goods_remark'=>$value[6],
                                        'is_z_back' =>$is_z_back,
                                        'midou_back_percent'=>$midou_back_percent,
                                        'shop_price'=>$value[12],
                                        'market_price'=>$value[13],
                                        'cost_price'=>$value[14],
                                        'cost_operating'=>$value[15],
                                        'is_free_shipping'=>$is_free_shipping,
                                        'store_count'=>$value[19],
                                        'keywords'=>$value[20],
                                        'cat_id'=>$cat_id,
                                        'goods_sn'=>'MDS_00'.$lastgoods_id,
                                        ];
                        $lastgoods_id++ ;
                    }
                    model('goods')->insertAll($insert_data);
                }
                
                return $this->fetch('goods_list');
                exit();
            }
        }
        return $this->fetch();
    }


    

    public function actionRead($filename,$encode='utf-8'){ 
        $objReader = \PHPExcel_IOFactory::createReader('Excel2007'); 
        $objReader->setReadDataOnly(true); 
        $objPHPExcel = $objReader->load($filename); 
        $objWorksheet = $objPHPExcel->getActiveSheet(); 
        $highestRow = $objWorksheet->getHighestRow(); //    return $highestRow; 
        $highestColumn = $objWorksheet->getHighestColumn(); 
        $highestColumnIndex = \PHPExcel_Cell::columnIndexFromString($highestColumn); 
        //var_dump($highestColumnIndex); 
        $excelData = array(); 
        for($row = 1; $row <= $highestRow; $row++) { 
            for ($col = 0; $col < $highestColumnIndex; $col++) { 
                $excelData[$row][]=(string)$objWorksheet->getCellByColumnAndRow($col, $row)->getValue(); 
            } 
        } 
        return $excelData;
    }
}