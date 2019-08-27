<?php
return	array(	
	'system'=>array('name'=>'系统','child'=>array(
				array('name' => '设置','child' => array(
						array('name'=>'商城设置','act'=>'index','op'=>'System'),
						//array('name'=>'支付方式','act'=>'index1','op'=>'System'),
						array('name'=>'地区&配送','act'=>'region','op'=>'Tools'),
						array('name'=>'短信模板','act'=>'index','op'=>'SmsTemplate'),
						//array('name'=>'接口对接','act'=>'index3','op'=>'System'),
						//array('name'=>'验证码设置','act'=>'index4','op'=>'System'),
						array('name'=>'自定义导航栏','act'=>'navigationList','op'=>'System'),
						array('name'=>'友情链接','act'=>'linkList','op'=>'Article'),
						array('name'=>'清除缓存','act'=>'cleanCache','op'=>'System'),
						array('name'=>'自提点','act'=>'index','op'=>'Pickup'),
						array('name'=>'资金池管理','act'=>'index','op'=>'CapitalPool'),
						array('name'=>'公益池管理','act'=>'index','op'=>'CelfarePool'),
						
                        
				)),
				array('name' => '会员','child'=>array(
						array('name'=>'会员列表','act'=>'index','op'=>'User'),
						array('name'=>'会员等级','act'=>'levelList','op'=>'User'),
						array('name'=>'充值额度','act'=>'rechargecofig','op'=>'User'),
						array('name'=>'充值记录','act'=>'recharge','op'=>'User'),
						array('name'=>'提现申请','act'=>'withdrawals','op'=>'User'),
						array('name'=>'汇款记录','act'=>'remittance','op'=>'User'),
						//array('name'=>'会员整合','act'=>'integrate','op'=>'User'),
						//array('name'=>'会员签到','act'=>'signList','op'=>'User'),
						array('name'=>'福利提现','act'=>'tocash','op'=>'User'),
						array('name'=>'会员卡管理','act'=>'club_card_group','op'=>'User'),

				)),
                array('name' => '客服','child'=>array(
                    array('name'=>'分组列表','act'=>'chat_group','op'=>'Chat'),
                    array('name'=>'快捷回复','act'=>'chat_reply','op'=>'Chat'),
                    array('name'=>'用户留言','act'=>'chat_message','op'=>'Chat'),
                    array('name'=>'常见问题','act'=>'chat_question','op'=>'Chat'),
                    array('name'=>'自动回复','act'=>'chat_autoreply','op'=>'Chat'),
                )),
				array('name' => '广告','child' => array(
						array('name'=>'广告列表','act'=>'adList','op'=>'Ad'),
						array('name'=>'广告位置','act'=>'positionList','op'=>'Ad'),
						/*array('name'=>'广告分类','act'=>'categoryList','op'=>'Ad'),*/
				)),
				array('name' => '文章','child'=>array(
						array('name' => '文章列表', 'act'=>'articleList', 'op'=>'Article'),
						array('name' => '文章分类', 'act'=>'categoryList', 'op'=>'Article'),
						//array('name' => '帮助管理', 'act'=>'help_list', 'op'=>'Article'),
						//array('name'=>'友情链接','act'=>'linkList','op'=>'Article'),
						array('name' => '公告管理', 'act'=>'notice_list', 'op'=>'Article'),
						//array('name' => '专题列表', 'act'=>'topicList', 'op'=>'Topic'),
				)),
				array('name' => '权限','child'=>array(
						array('name' => '管理员列表', 'act'=>'index', 'op'=>'Admin'),
						array('name' => '角色管理', 'act'=>'role', 'op'=>'Admin'),
						array('name' => '权限资源列表','act'=>'right_list','op'=>'System'),
						array('name' => '管理员日志', 'act'=>'log', 'op'=>'Admin'),
						//array('name' => '供应商列表', 'act'=>'supplier', 'op'=>'Admin'),
				)),
			
				/*array('name' => '模板','child'=>array(
						array('name' => '模板设置', 'act'=>'templateList', 'op'=>'Template'),
						array('name' => '手机首页', 'act'=>'mobile_index', 'op'=>'Template'),
				)),*/
				/*array('name' => '数据','child'=>array(
						array('name' => '数据备份', 'act'=>'index', 'op'=>'Tools'),
						array('name' => '数据还原', 'act'=>'restore', 'op'=>'Tools'),
						array('name' => 'ecshop数据导入', 'act'=>'ecshop', 'op'=>'Tools'),
						array('name' => '淘宝csv导入', 'act'=>'taobao', 'op'=>'Tools'),
						array('name' => 'SQL查询', 'act'=>'log', 'op'=>'Admin'),
				))*/
	)),
		
	'shop'=>array('name'=>'现金商城','child'=>array(
				array('name' => '现金商城','child' => array(
				    array('name' => '商品列表', 'act'=>'goodsList', 'op'=>'Goods'),
					array('name' => '商品分类', 'act'=>'categoryList', 'op'=>'Goods'),
					array('name' => '库存日志', 'act'=>'stock_list', 'op'=>'Goods'),
					array('name' => '商品模型', 'act'=>'goodsTypeList', 'op'=>'Goods'),
					array('name' => '商品规格', 'act' =>'specList', 'op' => 'Goods'),
					//array('name' => '品牌列表', 'act'=>'brandList', 'op'=>'Goods'),
					array('name' => '商品属性', 'act'=>'goodsAttributeList', 'op'=>'Goods'),
					array('name' => '评论列表', 'act'=>'index', 'op'=>'Comment'),
					//array('name' => '商品咨询', 'act'=>'ask_list', 'op'=>'Comment'),
                                    
			)),
			
			array('name' => '供货商','child' => array(
					array('name' => '供货商申请列表', 'act'=>'index', 'op'=>'Suppliers'),
					array('name' => '供货商列表',     'act'=>'supplierList', 'op'=>'Suppliers'),
					array('name' => '供货商商品列表', 'act'=>'goodsList', 'op'=>'Goods', 'attr1'=>'sp', 'attr2'=>'1'),
					array('name' => '供货商库存日志', 'act'=>'stock_list', 'op'=>'Goods', 'attr1'=>'sp', 'attr2'=>'1'),
					array('name' => '供货商评论列表', 'act'=>'index', 'op'=>'Comment', 'attr1'=>'sp', 'attr2'=>'1'),
					//array('name' => '供货商商品咨询', 'act'=>'ask_list', 'op'=>'Comment', 'attr1'=>'sp', 'attr2'=>'1'),
					array('name' => '供货商等级',     'act'=>'levelList','op'=>'Suppliers'),
					array('name' => '供货商结款',     'act'=>'settlement','op'=>'Suppliers'),
					array('name' => '供货商米豆结款', 'act'=>'settlementred','op'=>'Suppliers'),
					array('name' => '供货商提现申请', 'act'=>'withdrawals','op'=>'Suppliers'),
					array('name' => '供货商汇款记录', 'act'=>'remittance','op'=>'Suppliers'),

			)),
			array('name' => '订单','child'=>array(
					array('name' => '订单列表', 'act'=>'index', 'op'=>'Order'),
					//array('name' => '虚拟订单', 'act'=>'virtual_list', 'op'=>'Order'),
					array('name' => '发货单', 'act'=>'delivery_list', 'op'=>'Order'),
					array('name' => '退款单', 'act'=>'refund_order_list', 'op'=>'Order'),
					array('name' => '退换货', 'act'=>'return_list', 'op'=>'Order'),
					array('name' => '添加订单', 'act'=>'add_order', 'op'=>'Order'),
					array('name' => '订单日志','act'=>'order_log','op'=>'Order'),
					array('name' => '快递推送','act'=>'index','op'=>'Poll'),
					//array('name' => '发票管理','act'=>'index', 'op'=>'Invoice'),
			        //array('name' => '拼团列表','act'=>'team_list','op'=>'Team'),
			        //array('name' => '拼团订单','act'=>'order_list','op'=>'Team'),
			)),
			array('name' => '促销','child' => array(
					array('name' => '抢购管理', 'act'=>'flash_sale', 'op'=>'Promotion'),
					//array('name' => '团购管理', 'act'=>'group_buy_list', 'op'=>'Promotion'),
					array('name' => '优惠促销', 'act'=>'prom_goods_list', 'op'=>'Promotion'),
					array('name' => '订单促销', 'act'=>'prom_order_list', 'op'=>'Promotion'),
					array('name' => '特别推荐', 'act'=>'elite_goods_list', 'op'=>'Promotion'),
					//array('name' => '优惠券','act'=>'index', 'op'=>'Coupon'),
					//array('name' => '预售管理','act'=>'pre_sell_list', 'op'=>'Promotion'),
					//array('name' => '拼团管理','act'=>'index', 'op'=>'Team'),
			)),
			
			/*array('name' => '分销','child' => array(
					array('name' => '分销商品列表', 'act'=>'goods_list', 'op'=>'Distribut'),
					array('name' => '分销商列表', 'act'=>'distributor_list', 'op'=>'Distribut'),
					array('name' => '分销关系', 'act'=>'tree', 'op'=>'Distribut'),
					array('name' => '分销商等级', 'act'=>'grade_list', 'op'=>'Distribut'),
					array('name' => '分成日志', 'act'=>'rebate_log', 'op'=>'Distribut'),
			)),*/
	     
    	    array('name' => '微信','child' => array(
    	        array('name' => '公众号配置', 'act'=>'index', 'op'=>'Wechat'),
    	        array('name' => '微信菜单管理', 'act'=>'menu', 'op'=>'Wechat'),
    	        array('name' => '文本回复', 'act'=>'text', 'op'=>'Wechat'),
    	        array('name' => '图文回复', 'act'=>'img', 'op'=>'Wechat'),
                array('name' => '模板消息', 'act'=>'template_msg', 'op'=>'Wechat'),
            //    array('name' => '模板消息', 'act'=>'index', 'op'=>'Wechat'),
    	      /*  array('name' => '自动回复', 'act'=>'auto_reply', 'op'=>'Wechat'),
                array('name' => '粉丝列表', 'act'=>'fans_list', 'op'=>'Wechat'),

                array('name' => '素材管理', 'act'=>'materials', 'op'=>'Wechat'),*/
    	    )),

			array('name' => '全返','child' => array(
    	        array('name' => '选择日期', 'act'=>'index', 'op'=>'back_date_limit'),
    	        array('name'=>'福利提现','act'=>'tocash','op'=>'back_date_limit'),
    	    )),

			array('name' => '统计','child' => array(
				array('name' => '销售概况', 'act'=>'index', 'op'=>'Report'),
				array('name' => '充值概况', 'act'=>'recharge', 'op'=>'Report'),
				array('name' => '销售排行', 'act'=>'saleTop', 'op'=>'Report'),
				array('name' => '供货商排行', 'act'=>'supplierTop', 'op'=>'Report'),
				array('name' => '会员排行', 'act'=>'userTop', 'op'=>'Report'),
				array('name' => '销售明细', 'act'=>'saleList', 'op'=>'Report'),
				array('name' => '会员统计', 'act'=>'user', 'op'=>'Report'),
				array('name' => '运营概览', 'act'=>'finance', 'op'=>'Report'),
				array('name' => '平台支出记录','act'=>'expense_log','op'=>'Report'),
			)),

            array('name'=>'铺货','child'=>array(
                array('name' => '供货商供货审核', 'act'=>'supplyaudit','op'=>'Suppliers'),
                array('name' => '申请供货市场审核', 'act'=>'supplygonghuo','op'=>'Suppliers'),
                array('name' => '实体店申请铺货审核', 'act'=>'supplyapply','op'=>'Suppliers'),
                array('name' => '实体店主推铺货审核', 'act'=>'supplytop','op'=>'Suppliers'),
                array('name' => '实体店供货明细', 'act'=>'stock_goods_list','op'=>'Suppliers'),
                array('name' => '实体店供货记录', 'act'=>'stock_goods_log','op'=>'Suppliers'),
            ))



	)),

	'shop_red'=>array('name'=>'米豆商城','child'=>array(
			array('name' => '米豆商城','child' => array(
				array('name' => '商品列表', 'act'=>'goodsList', 'op'=>'GoodsRed'),
				array('name' => '商品分类', 'act'=>'categoryList', 'op'=>'GoodsRed'),
				array('name' => '库存日志', 'act'=>'stock_list', 'op'=>'GoodsRed'),
				array('name' => '商品模型', 'act'=>'goodsTypeList', 'op'=>'GoodsRed'),
				array('name' => '商品规格', 'act' =>'specList', 'op' => 'GoodsRed'),
				// array('name' => '品牌列表', 'act'=>'brandList', 'op'=>'GoodsRed'),
				array('name' => '商品属性', 'act'=>'goodsAttributeList', 'op'=>'GoodsRed'),
				array('name' => '评论列表', 'act'=>'index', 'op'=>'CommentRed'),
				array('name' => '商品咨询', 'act'=>'ask_list', 'op'=>'CommentRed'),
			)),
			
			array('name' => '订单','child'=>array(
					array('name' => '订单列表', 'act'=>'index', 'op'=>'OrderRed'),
					array('name' => '发货单', 'act'=>'delivery_list', 'op'=>'OrderRed'),
					array('name' => '退款单', 'act'=>'refund_order_list', 'op'=>'OrderRed'),
					array('name' => '退换货', 'act'=>'return_list', 'op'=>'OrderRed'),
					array('name' => '添加订单', 'act'=>'add_order', 'op'=>'OrderRed'),
					array('name' => '订单日志','act'=>'order_log','op'=>'OrderRed'),
                    array('name' => '快递推送','act'=>'index','op'=>'PollRed'),
					//array('name' => '虚拟订单', 'act'=>'virtual_list', 'op'=>'Order'),
					//array('name' => '发票管理','act'=>'index', 'op'=>'Invoice'),
			        //array('name' => '拼团列表','act'=>'team_list','op'=>'Team'),
			        //array('name' => '拼团订单','act'=>'order_list','op'=>'Team'),
			)),
			array('name' => '统计','child' => array(
				array('name' => '销售概况', 'act'=>'index', 'op'=>'ReportRed'),
				//array('name' => '充值概况', 'act'=>'recharge', 'op'=>'ReportRed'),
				array('name' => '销售排行', 'act'=>'saleTop', 'op'=>'ReportRed'),
				array('name' => '供货商排行', 'act'=>'supplierTop', 'op'=>'ReportRed'),
				array('name' => '会员排行', 'act'=>'userTop', 'op'=>'ReportRed'),
				array('name' => '销售明细', 'act'=>'saleList', 'op'=>'ReportRed'),
				array('name' => '会员统计', 'act'=>'user', 'op'=>'ReportRed'),
				array('name' => '运营概览', 'act'=>'finance', 'op'=>'ReportRed'),
				array('name' => '平台支出记录','act'=>'expense_log','op'=>'ReportRed'),
			)),

	)),
	'yxyp'=>array('name'=>'一乡一品','child'=>array(
			// array('name' => '一乡一品','child' => array(
			// 	array('name' => '商品列表', 'act'=>'goodsList', 'op'=>'GoodsYxyp'),
			// 	array('name' => '商品分类', 'act'=>'categoryList', 'op'=>'GoodsYxyp'),
			// 	array('name' => '库存日志', 'act'=>'stock_list', 'op'=>'GoodsYxyp'),
			// 	array('name' => '商品模型', 'act'=>'goodsTypeList', 'op'=>'GoodsYxyp'),
			// 	array('name' => '商品规格', 'act' =>'specList', 'op' => 'GoodsYxyp'),
			// 	// array('name' => '品牌列表', 'act'=>'brandList', 'op'=>'GoodsYxyp'),
			// 	array('name' => '商品属性', 'act'=>'goodsAttributeList', 'op'=>'GoodsYxyp'),
			// 	array('name' => '评论列表', 'act'=>'index', 'op'=>'CommentYxyp'),
			// 	array('name' => '商品咨询', 'act'=>'ask_list', 'op'=>'CommentYxyp'),
			// )),
			
			array('name' => '订单','child'=>array(
					array('name' => '订单列表', 'act'=>'index', 'op'=>'OrderYxyp'),
					array('name' => '发货单', 'act'=>'delivery_list', 'op'=>'OrderYxyp'),
					array('name' => '退款单', 'act'=>'refund_order_list', 'op'=>'OrderYxyp'),
					array('name' => '退换货', 'act'=>'return_list', 'op'=>'OrderYxyp'),
					array('name' => '添加订单', 'act'=>'add_order', 'op'=>'OrderYxyp'),
					array('name' => '订单日志','act'=>'order_log','op'=>'OrderYxyp'),
					//array('name' => '虚拟订单', 'act'=>'virtual_list', 'op'=>'Order'),
					//array('name' => '发票管理','act'=>'index', 'op'=>'Invoice'),
			        //array('name' => '拼团列表','act'=>'team_list','op'=>'Team'),
			        //array('name' => '拼团订单','act'=>'order_list','op'=>'Team'),
			)),
			// array('name' => '统计','child' => array(
			// 	array('name' => '销售概况', 'act'=>'index', 'op'=>'ReportYxyp'),
			// 	//array('name' => '充值概况', 'act'=>'recharge', 'op'=>'ReportYxyp'),
			// 	array('name' => '销售排行', 'act'=>'saleTop', 'op'=>'ReportYxyp'),
			// 	array('name' => '供货商排行', 'act'=>'supplierTop', 'op'=>'ReportYxyp'),
			// 	array('name' => '会员排行', 'act'=>'userTop', 'op'=>'ReportYxyp'),
			// 	array('name' => '销售明细', 'act'=>'saleList', 'op'=>'ReportYxyp'),
			// 	array('name' => '会员统计', 'act'=>'user', 'op'=>'ReportYxyp'),
			// 	array('name' => '运营概览', 'act'=>'finance', 'op'=>'ReportYxyp'),
			// 	array('name' => '平台支出记录','act'=>'expense_log','op'=>'ReportYxyp'),
			// )),

	)),

	'mobile'=>array('name'=>'模板','child'=>array(
			array('name' => '设置','child' => array(
				array('name' => '模板设置', 'act'=>'templateList', 'op'=>'Template'),
				array('name' => '手机支付', 'act'=>'templateList', 'op'=>'Template'),
				array('name' => '微信二维码', 'act'=>'templateList', 'op'=>'Template'),
				array('name' => '第三方登录', 'act'=>'templateList', 'op'=>'Template'),
				array('name' => '导航管理', 'act'=>'finance', 'op'=>'Report'),
				array('name' => '广告管理', 'act'=>'finance', 'op'=>'Report'),
				array('name' => '广告位管理', 'act'=>'finance', 'op'=>'Report'),
			)),
	)),
	
	'company'=>array('name'=>'子公司','child'=>array(
			array('name' => '子公司','child' => array(
				array('name' => '子公司列表', 'act'=>'index', 'op'=>'Company'),
				array('name' => '实体店列表', 'act'=>'index', 'op'=>'Pstore'),
				array('name' => '等级管理', 'act'=>'index', 'op'=>'CompanyLevel'),
                array('name' => '推荐子公司管理', 'act'=>'index', 'op'=>'CompanySign'),
                array('name' => '成员管理', 'act'=>'index', 'op'=>'Member'),
				array('name' => '员工管理', 'act'=>'index', 'op'=>'Staff'),
				array('name' => '创业推广员管理', 'act'=>'index', 'op'=>'Staff', 'attr1'=>'t', 'attr2'=>'1'),
				array('name' => '创业推广员申请', 'act'=>'tk_apply', 'op'=>'Staff'),
                array('name' => '实体店员工申请', 'act'=>'staff_apply', 'op'=>'Staff'),
				array('name' => '树状图', 'act'=>'tree', 'op'=>'Company'),
				
                array('name' => '公司类目', 'act'=>'category', 'op'=>'Company'),
			)),
			array('name' => '统计','child' => array(
				array('name' => '成员分红', 'act'=>'rebate', 'op'=>'Tongji'),
				array('name' => '员工分红', 'act'=>'staff_rebate', 'op'=>'Tongji'),
				# array('name' => '创业合伙人', 'act'=>'promote', 'op'=>'Tongji'),
				# array('name' => '资金池', 'act'=>'pool', 'op'=>'Tongji'),
				array('name' => '订单福利', 'act'=>'allreturn', 'op'=>'Tongji'),
				array('name' => '现金订单分红信息', 'act'=>'return_percentage', 'op'=>'Tongji'),
				array('name' => '米豆订单分红信息', 'act'=>'return_percentage&is_red=1', 'op'=>'Tongji'),
				array('name' => '序号列表初始化', 'act'=>'initialize_order', 'op'=>'Tongji'),
				array('name' => '福利列表序号', 'act'=>'xh', 'op'=>'Tongji'),
				array('name' => '实体店排行榜', 'act'=>'ranking', 'op'=>'Tongji'),
				
				
			)),
			array('name' => '记录','child' => array(
				array('name'=>'订单全返记录','act'=>'index','op'=>'PreviousLog'),
				array('name' => '流水管理', 'act'=>'index', 'op'=>'CompanyProfit'),
				array('name' => '提现管理', 'act'=>'index', 'op'=>'Withdrawals'),
				array('name' => '员工汇款', 'act'=>'remittance', 'op'=>'Withdrawals'),
				array('name' => '成员汇款', 'act'=>'remittance&is_staff=2', 'op'=>'Withdrawals'),
				array('name' => '子公司收款明细', 'act'=>'transfer_log', 'op'=>'Company'),
				array('name' => '线下流水明细', 'act'=>'offline_detail', 'op'=>'Company'),
				array('name' => '现金分红信息', 'act'=>'error_order', 'op'=>'Tongji'),
				array('name' => '米豆分红信息', 'act'=>'error_order&is_red=1', 'op'=>'Tongji'),
				array('name'=>'提现记录','act'=>'index','op'=>'WithdrawLog'),
				array('name'=>'对公账户记录','act'=>'index','op'=>'PublicAccounts'),
				array('name' => '新版线下流水', 'act'=>'running_water', 'op'=>'Offline'),
                /*array('name'=>'员工奖励记录','act'=>'index','op'=>'RewardLog'),*/
			)),
			
	)),
	'resource'=>array('name'=>'插件','child'=>array(
			array('name' => '云服务','child' => array(
				array('name' => '插件库', 'act'=>'index', 'op'=>'Plugin'),
				//array('name' => '数据备份', 'act'=>'index', 'op'=>'Tools'),
				//array('name' => '数据还原', 'act'=>'restore', 'op'=>'Tools'),
			))/*,
            array('name' => 'App','child' => array(
				array('name' => '安卓APP管理', 'act'=>'index', 'op'=>'MobileApp'),
                array('name' => '苹果APP管理', 'act'=>'ios_audit', 'op'=>'MobileApp'),
			))*/
	)),
);