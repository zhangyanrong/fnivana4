<?php
define('NIRVANA_SECRET_KEY', 'caa21c26dfc990c7a534425ec87a111c');//@TODO
class Order extends CI_Controller {
	public function __construct() {
		parent::__construct();
		$this->load->config('service');
		$this->load->library('api_process');
		$this->load->library('fruit_log');
		$this->load->helper('public');
		$this->request_id = date('Y_m_d_H_i_s') . '_' . md5($this->input->get_post('device_id') . mt_rand(10000, 99999));//用于记录日志用
		
		$this->source = 'app';
		$this->version = '5.9.0';
 		define("NIRVANA_URL", $this->config->item('nirvana', 'service') . '/api');//@TODO
 		define("NIRVANA2_URL", $this->config->item('nirvana2', 'service') . '/api');//@TODO
		$this->url = NIRVANA_URL;//@TODO
		$this->load->library('fruit_log');
		$this->load->helper('public');
		$this->request_id = date('Y_m_d_H_i_s') . '_' . md5($this->input->get_post('device_id') . mt_rand(10000, 99999));//用于记录日志用
	}
	
	public function __destruct() {
		if (isset($this->response) && $this->response) echo json_encode($this->response);
		if (!function_exists("fastcgi_finish_request")) {
			function fastcgi_finish_request() { }//为windows兼容
		}
		fastcgi_finish_request();
		$this->fruit_log->save();
	}
	
	/**
	 * @api {get} / 退换货列表
	 * @apiDescription	获取退换货列表
	 * @apiGroup		spa/order
	 * @apiName			compliant_list
	 *
	 * @apiParam {String} connect_id	登录Token
	 * @apiParam {String} status		1:已申请; 3:可申请;
	 * @apiParam {Number} [page]		页码
	 * @apiParam {Number} [pagesize]	每页数量
	 * 
	 * @apiSampleRequest /spa/v2?service=order.complaint_list
	 */
	public function complaint_list() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['timestamp']	= time();
		$query['connect_id']= self::requireRequest('connect_id');
		$query['source']	= $this->source;
		$query['version']	= $this->version;
		$query['service']	= 'order.complaintsListNew';
		$query['status']	= self::requireRequest('status');
		
		$query['page']		= $this->input->get_post('page') ? $this->input->get_post('page') : '1';//@TODO
		$query['pagesize']	= $this->input->get_post('pagesize') ? $this->input->get_post('pagesize') : '15';//@TODO
		
		$query['sign']		= self::Sign($query);
		$params['url']		= $this->url;
		$params['data']		= $query;
		$params['method']	= 'post';
		$service_cart_response	= $this->api_process->process($params);
		
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
		echo json_encode($service_cart_response);
	}
	
	/**
	 * @api {get} / 退换货详情
	 * @apiDescription	获取退换货详情
	 * @apiGroup		spa/order
	 * @apiName			compliant_detail
	 *
	 * @apiParam {String} connect_id	登录Token
	 * @apiParam {String} id			已申请退货的ID号
	 *
	 * @apiSampleRequest /spa/v2?service=order.complaint_detail
	 */
	public function complaint_detail() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['timestamp']	= time();
		$query['connect_id']= self::requireRequest('connect_id');
		$query['source']	= $this->source;
		$query['version']	= $this->version;
		$query['service']	= 'order.complaintsDetail';
		$query['id']		= self::requireRequest('id');//158346
		
		$query['sign']		= self::Sign($query);
		$params['url']			= $this->url;
		$params['data']			= $query;
		$params['method']		= 'post';
		$service_cart_response	= $this->api_process->process($params);
		
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
		echo json_encode($service_cart_response);
	}
	
	/**
	 * @api {post} / 申请售后
	 * @apiDescription	申请售后/退换货
	 * @apiGroup		spa/order
	 * @apiName			apply_return_exchange
	 *
	 * @apiParam {String} connect_id		登录Token
	 * @apiParam {String} order_name		订单编号(经过拆分的子订单号)
	 * @apiParam {String} complaint_type	退换货类型; 1:退货; 2:换货
	 * @apiParam {String} product_id		商品ID号
	 * @apiParam {String} product_no		商品编号?
	 * @apiParam {String} product_name		商品名
	 * @apiParam {String} mobile			手机号码/联系方式
	 * @apiParam {String} apply_reason		申请退换货缘由
	 * @apiParam {String} problem_product_ratio	问题产品比例;30:少量;50:一半;100:全部;
	 * @apiParam {String} base64_image		图片举证,多个时传数组; base64编码,需要带mime类型标识; 例:"data:image/png;base64,iVBORw0K......ErkJggg=="
	 *
	 * @apiSampleRequest /spa/v2?service=order.apply_return_exchange
	 */
	public function apply_return_exchange() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$delimiter = '-------------' . uniqid();
		
		$base64_image = $_POST['base64_image'];
		foreach (is_array($base64_image) ? $base64_image : [$base64_image] as $k => $v) {
			if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $v, $result)) {
				$type = $result[2];
				$fileFields[$k]['name']		= uniqid() . '.' . $type;
				$fileFields[$k]['type']		= 'image/' . $type;
				$fileFields[$k]['content']	= base64_decode(str_replace($result[1], '', $v));
			}
		}
		
		$postFields['timestamp']		= time();
		$postFields['connect_id']		= self::requireRequest('connect_id');
		$postFields['ordername']		= self::requireRequest('order_name');
		$postFields['source']			= $this->source;
		$postFields['version']			= $this->version;
		$postFields['service']			= 'order.doAppeal';
		$postFields['complaint_type']	= self::requireRequest('complaint_type');//1:退货/款; 2:换货
		$postFields['product_id']		= self::requireRequest('product_id');
		$postFields['product_no']		= self::requireRequest('product_no');
		$postFields['productname']		= self::requireRequest('product_name');
		$postFields['information']		= self::requireRequest('mobile');
		$postFields['description']		= self::requireRequest('apply_reason');
		$postFields['quest_ratio']		= self::requireRequest('problem_product_ratio');//30:少量; 50:一半; 100:全部
		$postFields['sign']				= self::Sign($postFields);
		
		$data = '';
		
		//先将post的普通数据生成主体字符串
		foreach ($postFields as $name => $content) {
			$data .= "--" . $delimiter . "\r\n";
			$data .= 'Content-Disposition: form-data; name="' . $name . '"';
			$data .= "\r\n\r\n" . $content . "\r\n";
		}
		//将上传的文件生成主体字符串
		if (isset($fileFields) && is_array($fileFields)) {
			foreach ($fileFields as $name => $file) {
				$data .= "--" . $delimiter . "\r\n";
				$data .= 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $file['name'] . "\" \r\n";
				$data .= 'Content-Type: ' . $file['type'] . "\r\n\r\n";//多了个文档类型
			
				$data .= $file['content'] . "\r\n";
			}
		}
		//主体结束的分隔符
		$data .= "--" . $delimiter . "--";

		$handle = curl_init($this->url);
		curl_setopt($handle, CURLOPT_POST, true);
		curl_setopt($handle, CURLOPT_TIMEOUT, 5);
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($handle, CURLOPT_HTTPHEADER , array(
				'Content-Type: multipart/form-data; boundary=' . $delimiter,
				'Content-Length: ' . strlen($data))
				);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
		$result = curl_exec($handle);
		curl_close($handle);
		
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
		echo $result;
	}
	
	/**
	 * @api {get} / 获取收货地址列表
	 * @apiDescription	获取用户收货地址列表
	 * @apiGroup		spa/address
	 * @apiName			get_addr_list
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录Token
	 *
	 * @apiSampleRequest /spa/v2?service=order.get_addr_list
	 */
	public function get_addr_list() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['timestamp']	= time();
		$query['uid']		= require_request('log_uid');
		$query['connect_id']= require_request('connect_id');
		$query['source']	= $this->source;
		$query['version']	= $this->version;
		$query['service']	= 'order.getAddrList';
		
		$query['sign']		= self::Sign($query);
		$params['url']		= NIRVANA2_URL;//@TODO
		$params['data']		= $query;
		$params['method']	= 'post';
		$this->response	= $this->api_process->process($params);
		
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {get} / 获取收货地址详情
	 * @apiDescription	获取收货地址详情
	 * @apiGroup		spa/address
	 * @apiName			address_detail
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录Token
	 * @apiParam {String} address_id	从接口address->lists返回参数中获取的address_id
	 *
	 * @apiSampleRequest /spa/v2?service=order.address_detail
	 */
	public function address_detail() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['timestamp']	= time();
		$query['uid']		= require_request('log_uid');
		$query['connect_id']= require_request('connect_id');
		$query['source']	= $this->source;
		$query['version']	= $this->version;
		$query['service']	= 'order.getAddrList';
		$query['sign']		= self::Sign($query);
		$address_id = require_request('address_id');
		
		$params['url']		= NIRVANA2_URL;//@TODO
		$params['data']		= $query;
		$params['method']	= 'post';
		$result_arr	= $this->api_process->process($params);
		
		$is_correct_address_id = false;
		foreach ($result_arr['data'] as $k => $v) {
			if ($v['id'] == $address_id) {
				$result_arr['data'] = $v;
				$is_correct_address_id = true;
				break;
			}
		}
		
		if ($is_correct_address_id == true) {
			$this->response = $result_arr;
		} else {
			$this->response = array('code' => '300', 'msg' => 'wrong address_id');
		}
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {post} / 添加收货地址
	 * @apiDescription	添加收货地址
	 * @apiGroup		spa/address
	 * @apiName			add_addr
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录Token
	 * @apiParam {String} name			收货人姓名
	 * @apiParam {String} mobile		收货人手机
	 * @apiParam {String} lonlat		收货地址坐标;例:121.587166,31.270868
	 * @apiParam {String} province_name	省名称;例:上海市
	 * @apiParam {String} area_adcode	区行政编码;例:310115
	 * @apiParam {String} area_name		区名称;例:浦东新区
	 * @apiParam {String} address_name	地址标志建筑名称;例:AAA(文峰广场)
	 * @apiParam {String} address		详细地址名称;例:张杨北路801号文峰广场F2层 3333333
	 * @apiParam {String} [flag]		标记、备注;例:公司/家
	 *
	 * @apiSampleRequest /spa/v2?service=order.add_addr
	 */
	public function add_addr() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['timestamp']	= time();
		$query['uid']		= require_request('log_uid');
		$query['connect_id']= require_request('connect_id');
		$query['name']		= require_request('name');
		$query['mobile']	= require_request('mobile');
		$query['lonlat']	= require_request('lonlat');
		$query['province_name']= require_request('province_name');
		$query['area_adcode']= require_request('area_adcode');
		$query['area_name']	= require_request('area_name');
		$query['address_name']= require_request('address_name');
		$query['address']	= require_request('address');
		$query['flag']		= $this->input->get_post('flag');
		$query['source']	= $this->source;
		$query['version']	= $this->version;
		$query['service']	= 'order.addAddr';
		
		$query['sign']		= self::Sign($query);
		$params['url']		= NIRVANA2_URL;//@TODO
		$params['data']		= $query;
		$params['method']	= 'post';
		$this->response	= $this->api_process->process($params);
		
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {get} / 删除收货地址
	 * @apiDescription	删除收货地址
	 * @apiGroup		spa/address
	 * @apiName			delete_addr
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录Token
	 * @apiParam {String} address_id	收货地址ID;从收货地址列表中获取 data->id;
	 *
	 * @apiSampleRequest /spa/v2?service=order.delete_addr
	 */
	public function delete_addr() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['timestamp']	= time();
		$query['uid']		= require_request('log_uid');
		$query['connect_id']= require_request('connect_id');
		$query['address_id']= require_request('address_id');
		$query['source']	= $this->source;
		$query['version']	= $this->version;
		$query['service']	= 'order.deleteAddr';
		
		$query['sign']		= self::Sign($query);
		$params['url']		= NIRVANA2_URL;//@TODO
		$params['data']		= $query;
		$params['method']	= 'post';
		$this->response	= $this->api_process->process($params);
		
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {post} / 修改收货地址
	 * @apiDescription	修改收货地址
	 * @apiGroup		spa/address
	 * @apiName			udpate_addr
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录Token
	 * @apiParam {String} address_id	收货地址id
	 * @apiParam {String} name			收货人姓名
	 * @apiParam {String} mobile		收货人手机
	 * @apiParam {String} lonlat		收货地址坐标;例:121.587166,31.270868
	 * @apiParam {String} province_name	省名称;例:上海市
	 * @apiParam {String} area_adcode	区行政编码;例:310115
	 * @apiParam {String} area_name		区名称;例:浦东新区
	 * @apiParam {String} address_name	地址标志建筑名称;例:AAA(文峰广场)
	 * @apiParam {String} address		详细地址名称;例:张杨北路801号文峰广场F2层 3333333
	 * @apiParam {String} [flag]		标记、备注;例:公司/家
	 *
	 * @apiSampleRequest /spa/v2?service=order.update_addr
	 */
	public function update_addr() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['timestamp']	= time();
		$query['uid']		= require_request('log_uid');
		$query['connect_id']= require_request('connect_id');
		$query['address_id']= require_request('address_id');
		$query['name']		= require_request('name');
		$query['mobile']	= require_request('mobile');
		$query['lonlat']	= require_request('lonlat');
		$query['province_name']= require_request('province_name');
		$query['area_adcode']= require_request('area_adcode');
		$query['area_name']	= require_request('area_name');
		$query['address_name']= require_request('address_name');
		$query['address']	= require_request('address');
		$query['flag']		= $this->input->get_post('flag');
		$query['source']	= $this->source;
		$query['version']	= $this->version;
		$query['service']	= 'order.updateAddr';
		
		$query['sign']		= self::Sign($query);
		$params['url']		= NIRVANA2_URL;//@TODO
		$params['data']		= $query;
		$params['method']	= 'post';
		$this->response	= $this->api_process->process($params);
		
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {get} / 购物车提交初始化订单
	 * @apiDescription	购物车中数据同步至订单系统中
	 * @apiGroup		spa/order
	 * @apiName			order_init
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录成功后返回的connect_id;	(7-5有效:0b5a783527eda79d9bf7cfb55ae9779f)
	 * @apiParam {String} store_id_list 门店id;					(7-5有效:1,23T1T1)
	 *
	 * @apiParam {String} address_id	地址ID(1.需要在首页中以登录状态选定我的收货地址时保存address_id; 2.购物车中选择收货地址时触发获取收货地址后保存 address_id) (7-5有效:7158505)
	 * @apiParam {String} area_adcode	地区码(例:310105)
	 * @apiParam {String} delivery_code	投递代码?(1.从 tms_store->lists_by_lonlat 中获取; 2.从首页广告位接口返回的"deliverId"获取); (7-5有效:51)
	 *
	 * @apiSampleRequest /spa/v2?service=order.order_init
	 */
	public function order_init() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['timestamp']		= time();
		$query['uid']			= require_request('log_uid');
		$query['connect_id']	= require_request('connect_id');
		$query['store_id_list']	= require_request('store_id_list');
		$query['address_id']	= require_request('address_id');
		$query['area_adcode']	= require_request('area_adcode');
		$query['delivery_code']	= require_request('delivery_code');
		$query['source']		= $this->source;
		$query['version']		= $this->version;
		$query['service']		= 'order.orderInit';
		
		$query['sign']		= self::Sign($query);
		$params['url']		= NIRVANA2_URL;//@TODO
		$params['data']		= $query;
		$params['method']	= 'post';
		$this->response	= $this->api_process->process($params);
		
		$response_arr = & $this->response;
		$weekarray=array("日","一","二","三","四","五","六");
		if (isset($response_arr['data']['package'])) {
			foreach ($response_arr['data']['package'] as $k => $v) {
				foreach ($v['send_time'] as $kk => $vv) {
					$time_str = strtotime($kk);
					$show_date = date('m月d日', $time_str) . '|周' . $weekarray[date('w', $time_str)];
					$response_arr['data']['package'][$k]['send_time_h5'][$show_date] = $vv;
				}
		
				if ($v['default_send_time']['shtime']) {
					$default_send_time = strtotime($v['default_send_time']['shtime']);
					$response_arr['data']['package'][$k]['default_send_time_h5']['shtime'] = date('m月d日', $default_send_time) . '|周' . $weekarray[date('w', $default_send_time)];
					$response_arr['data']['package'][$k]['default_send_time_h5']['stime'] = $response_arr['data']['package'][$k]['default_send_time']['stime'];
				}
			}
		}
		
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {get} / 创建订单
	 * @apiDescription	创建订单
	 * @apiGroup		spa/order
	 * @apiName			create_order
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录成功后返回的connect_id;
	 * @apiParam {String} store_id_list 门店id;
	 *
	 * @apiParam {String} area_adcode	地区码(例:310105)
	 * @apiParam {String} delivery_code	投递代码?(1.从 tms_store->lists_by_lonlat 中获取; 2.从首页广告位接口返回的"deliverId"获取); (7-5有效:51)
	 * @apiParam {String} send_date		送货日期;order->init接口中send_time节点或者default_send_time中的"日期"时间节点;格式:20170707;
	 * @apiParam {String} send_time		送货时间区间;order->init接口中send_time节点或者default_send_time中的"时分秒"时间节点;格式:09:00-18:00
	 * @apiParam {String} tag			order->init接口返回的 tag 节点;格式:2-0-2
	 * @apiParam {String} is_flash		order->init接口返回的send_time节点中的子节点:is_flash,与disable平级;没有就是false;
	 * @apiParam {String} sheet_show_price	签收单不显示金额; 传0为不显示;传其他或者不传都为显示;
	 *
	 * @apiSampleRequest /spa/v2?service=order.create_order
	 */
	public function create_order() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['timestamp']		= time();
		$query['uid']			= require_request('log_uid');
		$query['connect_id']	= require_request('connect_id');
		$query['store_id_list']	= require_request('store_id_list');
		$query['area_adcode']	= require_request('area_adcode');
		$query['delivery_code']	= require_request('delivery_code');
		$send_date				= require_request('send_date');
		$send_time				= require_request('send_time');
		$tag					= require_request('tag');
		$is_flash				= require_request('is_flash');
		$query['sheet_show_price']	= require_request('sheet_show_price');
		$query['channel']		= 'wap';//M站创建订单需要该参数给底层接口识别渠道
		$query['source']		= $this->source;
		$query['version']		= $this->version;
		$query['service']		= 'order.create_order';
		
		foreach ($is_flash as $k => $v) {
			$package_send_times[$k]['is_flash']	= $is_flash[$k] == true ? true : false;
			$package_send_times[$k]['stime']	= $send_time[$k];
			$package_send_times[$k]['tag']		= $tag[$k];
			$package_send_times[$k]['shtime']	= $send_date[$k];
		}
		$query['package_send_times']	= json_encode($package_send_times);
		
		
		$query['sign']		= self::Sign($query);
		$params['url']		= NIRVANA2_URL;//@TODO
		$params['data']		= $query;
		$params['method']	= 'post';
		$this->response	= $this->api_process->process($params);
		
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {get} / 取消订单
	 * @apiDescription	取消订单
	 * @apiGroup		spa/order
	 * @apiName			order_cancel
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录成功后返回的connect_id;
	 * @apiParam {String} order_name	订单编号(order->list中返回的参数)
	 *
	 * @apiSampleRequest /spa/v2?service=order.order_cancel
	 */
	public function order_cancel() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['timestamp']		= time();
		$query['uid']			= require_request('log_uid');
		$query['connect_id']	= require_request('connect_id');
		$query['order_name']	= require_request('order_name');
		$query['source']		= $this->source;
		$query['version']		= $this->version;
		$query['service']		= 'order.orderCancel';
		
		$query['sign']		= self::Sign($query);
		$params['url']		= NIRVANA2_URL;//@TODO
		$params['data']		= $query;
		$params['method']	= 'post';
		$this->response	= $this->api_process->process($params);
		
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {get} / 取消送礼订单
	 * @apiDescription	取消送礼订单
	 * @apiGroup		spa/order
	 * @apiName			gift_order_cancel
	 *
	 * @apiParam {Number} [log_uid]		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录成功后返回的connect_id;
	 * @apiParam {String} order_name	订单编号(order->list中返回的参数)
	 *
	 * @apiSampleRequest /spa/v2?service=order.gift_order_cancel
	 */
	public function gift_order_cancel() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['timestamp']		= time();
		$query['connect_id']	= require_request('connect_id');
		$query['order_name']	= require_request('order_name');
		$query['source']		= $this->source;
		$query['version']		= $this->version;
		$query['service']		= is_numeric($query['order_name']) ? 'refund.send' : 'refund.pordersend';//子订单打前面接口，父订单打后面接口
		
		$query['sign']		= self::Sign($query);
		$params['url']		= NIRVANA_URL;
		$params['data']		= $query;
		$params['method']	= 'post';
		$response = $this->api_process->process($params);
		if ($response['code'] == '200') {
			$this->response['code']	= '200';
			$this->response['msg']	= '取消订单成功';
		} else {
			$this->response = & $response;
		}
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {get} / 获取订单列表
	 * @apiDescription 获取订单列表
	 * @apiGroup		spa/order
	 * @apiName			order_list
	 *
	 * @apiParam {Number} log_uid			最近一次登录的用户ID
	 * @apiParam {String} connect_id		登录成功后返回的connect_id;
	 * @apiParam {String} order_status		订单状态; 0:全部;1:待付款;2:待发货;3:待收货;4:待评价;
	 * @apiParam {String} [current_page]	当前页码
	 * @apiParam {String} [num_per_page]	每页数量
	 *
	 * @apiSampleRequest /spa/v2?service=order.order_list
	 */
	public function order_list() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['timestamp']		= time();
		$query['uid']			= require_request('log_uid');
		$query['connect_id']	= require_request('connect_id');
		if (in_array($this->input->get_post('order_status'), array(0, 1, 2, 3, 4))) {
			$query['order_status']	= $this->input->get_post('order_status');
		} else {
			exit(json_encode(array('code' => '300', 'msg' => 'wrong order_status')));
		}
		$query['current_page']	= $this->input->get_post('current_page');
		$query['num_per_page']	= $this->input->get_post('num_per_page');
		$query['source']		= $this->source;
		$query['version']		= $this->version;
		$query['service']		= 'order.orderNewList';
		
		$query['sign']		= self::Sign($query);
		$params['url']		= NIRVANA2_URL;//@TODO
		$params['data']		= $query;
		$params['method']	= 'post';
		$this->response	= $this->api_process->process($params);
		$response_arr = & $this->response;
		if (isset($response_arr['list'])) {
			$response_arr['data'] = $response_arr['list'];
			unset($response_arr['list']);
			foreach ($response_arr['data'] as $k => $v) {
				$response_arr['data'][$k]['total_qty'] = 0;
				foreach ($v['item'] as $kk => $vv) {
					$response_arr['data'][$k]['total_qty'] += $vv['qty'];
				}
			}
		}
		
		if (isset($response_arr['data']['package'])) {
			$method_money = isset($response_arr['data']['method_money']) ? $response_arr['data']['method_money'] : 0;
			$response_arr['data']['method_money'] = number_format($method_money, 2);
			foreach ($response_arr['data']['package'] as $k => $v) {
				$total_qty = 0;
				foreach ($v['item'] as $vv) {
					$total_qty += $vv['qty'];
				}
				$response_arr['data']['package'][$k]['total_qty'] = $total_qty;//统计每个包裹中的商品总件数
			}
		}
		
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {get} / 获取订单详情
	 * @apiDescription	获取订单详情
	 * @apiGroup		spa/order
	 * @apiName			order_info
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录成功后返回的connect_id;
	 * @apiParam {String} order_name	订单编号(order->list中返回的参数)
	 *
	 * @apiSampleRequest /spa/v2?service=order.order_info
	 */
	public function order_info() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['timestamp']		= time();
		$query['uid']			= require_request('log_uid');
		$query['connect_id']	= require_request('connect_id');
		$query['order_name']	= require_request('order_name');
		$query['source']		= $this->source;
		$query['version']		= $this->version;
		$query['service']		= 'order.orderInfo';
		
		$query['sign']		= self::Sign($query);
		$params['url']		= NIRVANA2_URL;//@TODO
		$params['data']		= $query;
		$params['method']	= 'post';
		$this->response	= $this->api_process->process($params);
		$response_arr = & $this->response;
		
		if (isset($response_arr['data']['package'])) {
			$method_money = isset($response_arr['data']['method_money']) ? $response_arr['data']['method_money'] : 0;
			$response_arr['data']['method_money'] = number_format($method_money, 2);
			foreach ($response_arr['data']['package'] as $k => $v) {
				$total_qty = 0;
				foreach ($v['item'] as $vv) {
					$total_qty += $vv['qty'];
				}
				$response_arr['data']['package'][$k]['total_qty'] = $total_qty;//统计每个包裹中的商品总件数
			}
		}
		
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {get} /		订单积分抵扣
	 * @apiDescription	订单积分抵扣
	 * @apiGroup		spa/order
	 * @apiName			usejf
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录成功后返回的connect_id;
	 * @apiParam {String} jf			使用的积分抵扣金额;(整数,order->init 返回的 order_jf_limit 参数)
	 *
	 * @apiParam {String} store_id_list 门店id;
	 * @apiParam {String} area_adcode	地区码(例:310105)
	 * @apiParam {String} delivery_code	投递代码?(1.从 tms_store->lists_by_lonlat 中获取; 2.从首页广告位接口返回的"deliverId"获取); (7-5有效:51)
	 *
	 * @apiSampleRequest /spa/v2?service=order.usejf
	 */
	public function usejf() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['timestamp']		= time();
		$query['uid']			= require_request('log_uid');
		$query['connect_id']	= require_request('connect_id');
		$query['jf']			= require_request('jf');
		$query['store_id_list']	= require_request('store_id_list');
		$query['area_adcode']	= require_request('area_adcode');
		$query['delivery_code']	= require_request('delivery_code');
		$query['source']		= $this->source;
		$query['version']		= $this->version;
		$query['service']		= 'order.usejf';
		
		$query['sign']		= self::Sign($query);
		$params['url']		= NIRVANA2_URL;//@TODO
		$params['data']		= $query;
		$params['method']	= 'post';
		$this->response	= $this->api_process->process($params);
		
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {get} /	取消订单积分抵扣
	 * @apiDescription	取消订单积分抵扣
	 * @apiGroup		spa/order
	 * @apiName			cancel_usejf
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录成功后返回的connect_id;
	 *
	 * @apiParam {String} store_id_list 门店id;
	 * @apiParam {String} area_adcode	地区码(例:310105)
	 * @apiParam {String} delivery_code	投递代码?(1.从 tms_store->lists_by_lonlat 中获取; 2.从首页广告位接口返回的"deliverId"获取); (7-5有效:51)
	 *
	 * @apiSampleRequest /spa/v2?service=order.cancel_usejf
	 */
	public function cancel_usejf() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['timestamp']		= time();
		$query['uid']			= require_request('log_uid');
		$query['connect_id']	= require_request('connect_id');
		$query['store_id_list']	= require_request('store_id_list');
		$query['area_adcode']	= require_request('area_adcode');
		$query['delivery_code']	= require_request('delivery_code');
		$query['source']		= $this->source;
		$query['version']		= $this->version;
		$query['service']		= 'order.cancelUsejf';
		
		$query['sign']		= self::Sign($query);
		$params['url']		= NIRVANA2_URL;//@TODO
		$params['data']		= $query;
		$params['method']	= 'post';
		$this->response	= $this->api_process->process($params);
		
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {get} /		索取发票
	 * @apiDescription	索取发票
	 * @apiGroup		spa/order
	 * @apiName			get_invoice
	 *
	 * @apiParam {Number} log_uid			最近一次登录的用户ID
	 * @apiParam {String} connect_id		登录成功后返回的connect_id;
	 * @apiParam {String} area_adcode		地区id(收货地址接口 area->id)
	 * @apiParam {String} store_id_list		门店
	 * @apiParam {String} [title]			发票抬头; 1.不传、传空为个人; 2.填入公司完整名称
	 * @apiParam {String} invoice_type		发票类型; 1.电子; 2.纸质;
	 * @apiParam {String} kp_type			开票类型;1:水果;3:明细;2:暂时不清楚;
	 * @apiParam {String} [fp_id_no]		纳税人识别号
	 * @apiParam {String} [fp_dz]			收货地址;
	 * @apiParam {String} [invoice_mobile]	手机号;
	 * @apiParam {String} [invoice_area]	地区id(收货地址接口 area->id)
	 * @apiParam {String} [invoice_city]	城市id(收货地址接口 city->id)
	 * @apiParam {String} [invoice_province]	省id(收货地址接口 province->id)
	 * @apiParam {String} [invoice_username]	收件人姓名
	 * @apiParam {String} [delivery_code]	投递代码?(1.从 tms_store->lists_by_lonlat 中获取; 2.从首页广告位接口返回的"deliverId"获取);
	 *
	 * @apiSampleRequest /spa/v2?service=order.use_invoice
	 */
	public function use_invoice() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['timestamp']		= time();
		$query['uid']			= require_request('log_uid');
		$query['connect_id']	= require_request('connect_id');
		$query['area_adcode']	= require_request('area_adcode');
		$query['store_id_list']	= require_request('store_id_list');
		
		$title = $this->input->get_post('title') ? htmlspecialchars($this->input->get_post('title')): '个人';
		$query['fp']			= $title;
		$query['kp_type']		= '1';
		$query['invoice_type']	= require_request('invoice_type');
		
		if ($title != '个人') {
			$query['fp_id_no']	= htmlspecialchars($this->input->get('fp_id_no'));//纳税人识别号
		}
		
		if ($this->input->get_post('fp_dz')) {
			$query['fp_dz']				= require_request('fp_dz');
			$query['invoice_mobile']	= require_request('invoice_mobile');
			$query['invoice_area']		= require_request('invoice_area');
			$query['invoice_city']		= require_request('invoice_city');
			$query['invoice_province']	= require_request('province_id');
			$query['invoice_username']	= require_request('invoice_username');
			$query['store_id_list']		= require_request('store_id_list');//老接口必要
			$query['delivery_code']		= require_request('delivery_code');//老接口必要
		}
		$query['source']		= $this->source;//@TODO
		$query['version']		= $this->version;//@TODO
		$query['service']		= 'order.cancelUsejf';
		
		$query['sign']		= self::Sign($query);
		$params['url']		= NIRVANA2_URL;//@TODO
		$params['data']		= $query;
		$params['method']	= 'post';
		$this->response	= $this->api_process->process($params);
		
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {get} /		取消发票
	 * @apiDescription	取消发票
	 * @apiGroup		spa/order
	 * @apiName			cancel_invoice
	 *
	 * @apiParam {Number} log_uid			最近一次登录的用户ID
	 * @apiParam {String} connect_id		登录成功后返回的connect_id;
	 * @apiParam {String} area_adcode		地区id(收货地址接口 area->id)
	 * @apiParam {String} store_id_list		门店
	 * @apiParam {String} delivery_code		投递代码?(1.从 tms_store->lists_by_lonlat 中获取; 2.从首页广告位接口返回的"deliverId"获取);
	 *
	 * @apiSampleRequest /spa/v2?service=order.cancel_invoice
	 */
	public function cancel_invoice() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['timestamp']		= time();
		$query['uid']			= require_request('log_uid');
		$query['connect_id']	= require_request('connect_id');
		$query['area_adcode']	= require_request('area_adcode');
		$query['store_id_list']	= require_request('store_id_list');
		$query['delivery_code']	= require_request('delivery_code');
		$query['source']		= $this->source;
		$query['version']		= $this->version;
		$query['service']		= 'order.cancelInvoice';
		
		$query['sign']		= self::Sign($query);
		$params['url']		= NIRVANA2_URL;//@TODO
		$params['data']		= $query;
		$params['method']	= 'post';
		$this->response	= $this->api_process->process($params);
		
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {get} /	删除(永久隐藏)订单
	 * @apiDescription	删除(永久隐藏)订单
	 * @apiGroup		spa/order
	 * @apiName			order_hide
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录成功后返回的connect_id;
	 * @apiParam {String} order_name	订单编号(order->list中返回的参数)
	 *
	 * @apiSampleRequest /spa/v2?service=order.order_hide
	 */
	public function order_hide() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['timestamp']		= time();
		$query['uid']			= require_request('log_uid');
		$query['connect_id']	= require_request('connect_id');
		$query['delivery_code']	= require_request('delivery_code');
		$query['source']		= $this->source;
		$query['version']		= $this->version;
		$query['service']		= 'order.orderHide';
		
		$query['sign']		= self::Sign($query);
		$params['url']		= NIRVANA2_URL;//@TODO
		$params['data']		= $query;
		$params['method']	= 'post';
		$this->response	= $this->api_process->process($params);
		
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	
    /**
     * @api              {post} / 订单中使用优惠券
     * @apiDescription   订单下单过程中使用优惠券
     * @apiGroup         spa/order
     * @apiName          use_card
     *
     * @apiParam {String} area_adcode      定位地区编码
     * @apiParam {String} card_number      优惠券码,列表中返回的 card_number
     * @apiParam {String} connect_id       登录成功后返回的connect_id
     * @apiParam {String} delivery_code    仓储id
     * @apiParam {String} lonlat           定位地址;经纬度
     * @apiParam {String} store_id_list    门店id列表
     *
     * @apiSampleRequest /spa/v2?service=order.use_card
     */
    public function use_card() {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $query['uid']           = get_uid();
        $query['card']          = require_request('card_number');
        $query['lonlat']        = require_request('lonlat');
        $query['area_adcode']   = require_request('area_adcode');
        $query['store_id_list'] = require_request('store_id_list');
        $query['delivery_code'] = require_request('delivery_code');
        $query['source']        = $this->source;
        $query['version']       = $this->version;
        
        $params['url']      = $this->config->item('order', 'service') . '/v1/order/useCard';
        $params['data']     = $query;
        $params['method']   = 'get';
        $service_order_response    = $this->api_process->process($params);
        
        $response_result = $this->api_process->get('result');
        if ($response_result->info->http_code == 200 || $response_result->info->http_code == 201) {
            $api_gateway_response['code']   = '200';
            $api_gateway_response['msg']    = 'success';
            $api_gateway_response['data']   = $service_order_response;
        } else {
            $api_gateway_response['code']   = '300';
            $api_gateway_response['msg']    = isset($service_order_response['msg']) ? $service_order_response['msg'] : $service_order_response;
        }
        $this->response = & $api_gateway_response;
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api              {post} / 取消使用优惠券
	 * @apiDescription   取消使用优惠券
	 * @apiGroup         spa/order
	 * @apiName          cancel_use_card
	 *
	 * @apiParam {String} area_adcode      定位地区编码
	 * @apiParam {String} connect_id       登录成功后返回的connect_id
	 * @apiParam {String} delivery_code    仓储id
	 * @apiParam {String} lonlat           定位地址;经纬度
	 * @apiParam {String} store_id_list    门店id列表
	 *
	 * @apiSampleRequest /spa/v2?service=order.cancel_use_card
	 */
	public function cancel_use_card()
	{
	    $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
	    $query['uid']           = get_uid();
	    $query['lonlat']        = require_request('lonlat');
	    $query['area_adcode']   = require_request('area_adcode');
	    $query['store_id_list'] = require_request('store_id_list');
	    $query['delivery_code'] = require_request('delivery_code');
	    $query['source']        = $this->source;
	    $query['version']       = $this->version;
	    
	    $params['url']      = $this->config->item('order', 'service') . '/v1/order/cancelUseCard';
	    $params['data']     = $query;
	    $params['method']   = 'get';
	    $service_order_response    = $this->api_process->process($params);
	    
	    $response_result = $this->api_process->get('result');
	    if ($response_result->info->http_code == 200) {
	        $api_gateway_response['code']   = '200';
	        $api_gateway_response['msg']    = 'success';
	        $api_gateway_response['data']   = $service_order_response;
	    } else {
	        $api_gateway_response['code']   = '300';
	        $api_gateway_response['msg']    = isset($service_order_response['msg']) ? $service_order_response['msg'] : 'fail';
	    }
	    $this->response = & $api_gateway_response;
	    $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @method / (无单退款)取消订单
	 * @param array $param
	 * @return array
	 */
	private function orderCancel($param) {
		$query['timestamp']		= time();
		$query['connect_id']	= require_request('connect_id');
		$query['order_name']	= $param['order_name'];
		$query['source']		= $this->source;
		$query['version']		= $this->version;
		$query['service']		= 'refund.send';
	
		$query['sign']		= self::Sign($query);
		$params['url']		= NIRVANA_URL;//@TODO
		$params['data']		= $query;
		$params['method']	= 'post';
		$response = $this->api_process->process($params);
		return $response;
	}
	
	private function Sign($data) {
		$api_secret_key = NIRVANA_SECRET_KEY;
		$post_param = $data;
		ksort($post_param);
		$query = '';
		foreach($post_param as $k => $v) {
			if ($v === null) continue;
			$query .= $k . '=' . $v . '&';
		}
		$validate_sign1 = md5(substr(md5($query.$api_secret_key), 0,-1).'w');//@TODO
// 		$validate_sign2 = md5(substr(md5($query.NIRVANA2_PRO_SECRET), 0,-1).'w');//@TODO
		return $validate_sign1;
	}
	
	private function requireRequest($key) {
		if (isset($_REQUEST[$key]) && $_REQUEST[$key]) {
			return htmlspecialchars($_REQUEST[$key]);
		} else {
			$this->code = '300';
			$this->response = array('code' => '300', 'msg' => $key . ' is empty');
			exit(json_encode($this->response));
		}
	}
}
