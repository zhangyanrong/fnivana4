<?php
class Cart extends CI_Controller {
	private $source, $version, $device_id, $connect_id, $response, $cart_id;

	public function __construct() {
		parent::__construct();
		$this->load->config('service');
		define('CURRENT_VERSION_CART_API', $this->config->item('cart', 'service') . '/v3/cart');//@TODO
		define('CURRENT_VERSION_PRODUCT_API', $this->config->item('product', 'service') . '/v1/product');//@TODO

		$this->load->library('api_process');
		$this->load->library('fruit_log');
		$this->load->helper('public');
		$this->request_id = date('Y_m_d_H_i_s') . '_' . md5($this->input->get_post('device_id') . mt_rand(10000, 99999));//用于记录日志用

		$this->source = require_request('source');
		$this->version = require_request('version');
		$this->connect_id = require_request('connect_id');
		$this->area_adcode = $this->input->get_post('area_adcode');

		$temp = changeStoreId($this->input->post('store_id_list'));
		$this->store_id_lists = $temp['store_id_list'];
		$this->tms_region_type = $temp['tms_region_type'];
		$this->tms_region_time = $temp['tms_region_time'];
		self::init_cart_id();
		$this->url = '';//@TODO,用来请求的底层接口的URL地址
	}

	public function __destruct() {
		if (isset($this->response) && $this->response) echo json_encode($this->response);
		if (!function_exists("fastcgi_finish_request")) {
			function fastcgi_finish_request() { }//为windows兼容
		}
		fastcgi_finish_request();
		$this->fruit_log->save();
	}

	//获取基本查询数据
	private function basic_query_data() {
		$service_cart_query['source']			= $this->source;
		$service_cart_query['source_version']	= $this->version;
		$service_cart_query['stores']			= $this->store_id_lists;//门店列表,多个用逗号分隔
		$service_cart_query['range']			= $this->tms_region_type;//门店配送范围?
		$service_cart_query['area_adcode']		= $this->area_adcode;//三级地区码
		$service_cart_query['uid']				= require_request('log_uid');
		if (isset($this->uid)) $service_cart_query['uid']= $this->uid;
		return $service_cart_query;
	}

	private function init_cart_id() {
		try {
			$connect_id = $this->input->get_post('connect_id');
			$this->uid = get_uid($connect_id);
			$this->load->library('Nirvana2session', array('session_id' => $connect_id));
			if ($this->uid != $this->nirvana2session->get('id')) throw new \Exception('登录信息已过期,请重新登录!CART');
			$this->cart_id = $this->uid;
		} catch (\Exception $e) {
			$this->response['code'] = '400';
			$this->response['msg'] = $e->getMessage();
			exit;
		}
	}

	/**
	 * @api {post} / 添加商品
	 * @apiDescription 添加一个/多个item
	 * @apiGroup spa/cart
	 * @apiName add
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录Token
	 * @apiParam {String} source		渠道;app、spa
	 * @apiParam {String} version		接口版本号;例:5.7.0
	 * @apiParam {String} store_id_list	门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode]	三级地区编码
	 *
	 * @apiParam {String} pid=13922				商品id(product_id)，多个用逗号分隔
	 * @apiParam {String} sid=1					商店id，多个用逗号分隔
	 * @apiParam {String} type=normal			商品类型(normal/gift/exchange/user_gift)，多个用逗号分隔
	 * @apiParam {String} [gift_send_id]		加入会员赠品时必填
	 * @apiParam {String} [gift_active_type]	加入会员赠品时必填
	 * @apiParam {String} [user_gift_id]		加入会员赠品时必填
	 * @apiParam {String} [pmt_id]				加入换购商品时必填
	 * @apiParam {Number} [user_gift_mutex] 	是否与其他会员赠品互斥:1.互斥;0.不互斥 或者不传;
	 * @apiParam {Number} [mutex]				同上,为了解决app参数传错的问题
	 *
	 * @apiSampleRequest /spa/v2?service=cart.add
	 */
	public function add() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = CURRENT_VERSION_CART_API . '/' . $this->cart_id . '?request_id=' . $this->request_id;
		if ($this->input->post('type') == 'exchange') $pmt_id = self::requireRequest('pmt_id');
		if ($this->input->post('type') == 'user_gift') {
			$gift_send_ids		= explode(',', self::requireRequest('gift_send_id'));
			$gift_active_types	= explode(',', self::requireRequest('gift_active_type'));
			$user_gift_ids		= explode(',', self::requireRequest('user_gift_id'));
		}
		$pids			= explode(',', $this->input->post('pid'));
		$types			= explode(',', $this->input->post('type'));
		if ($this->input->get_post('user_gift_mutex')) {
			$user_gift_mutex	= explode(',', $this->input->get_post('user_gift_mutex'));
		} else {
			$user_gift_mutex	= explode(',', $this->input->get_post('mutex'));
		}

		$count_pids = count($pids);
		$count_types = count($types);

		if ($count_pids != $count_types) {
			$gateway_response['code']	= '300';
			$gateway_response['msg']	= '参数长度不匹配';
		} else {
			$success_response = false;
			foreach ($pids as $k => $v) {
				$service_cart_query = $this->basic_query_data();
				$service_cart_query['product_id']		= $v;//商品id(product_id)，多个用逗号分隔
				// 				$service_cart_query['sid']				= $sids[$k];//商店id，多个用逗号分隔
				$service_cart_query['type']				= $types[$k];//商品类型(normal/gift/exchange/user_gift)，多个用逗号分隔
				if (isset($gift_send_ids[$k]))		$service_cart_query['gift_send_id']		= $gift_send_ids[$k];
				if (isset($gift_active_types[$k]))	$service_cart_query['gift_active_type']	= $gift_active_types[$k];
				if (isset($user_gift_ids[$k]))		$service_cart_query['user_gift_id']		= $user_gift_ids[$k];
				if (isset($user_gift_mutex[$k]))	$service_cart_query['user_gift_mutex']	= $user_gift_mutex[$k];
				if (isset($pmt_id))					$service_cart_query['pmt_id']			= $pmt_id;

				$params['url'] = $url;
				$params['data']	= $service_cart_query;
				$params['method'] = 'post';
				$service_cart_response = $this->api_process->process($params);

				$response_result = $this->api_process->get('result');
				if ($response_result->info->http_code == 200 || $response_result->info->http_code == 201) {
					$api_gateway_response['code']	= '200';
					$api_gateway_response['msg']	= '购物车添加成功';
					$success_response = true;
				} else {
					$api_gateway_response['code']	= '300';
					$api_gateway_response['msg']	= $service_cart_response['msg'];
				}
			}

			if ($success_response == false) {
				$api_gateway_response['code']	= '300';
				$api_gateway_response['msg']	= $service_cart_response['msg'];
			}
		}

		self::get_cart_last_data();
		$this->response = array_merge($this->response, $api_gateway_response);//框架中有析构函数，为避免可能失效不使用exit
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {post} / 统计购物车
	 * @apiDescription 统计购物车
	 * @apiGroup spa/cart
	 * @apiName count
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录Token
	 * @apiParam {String} source		资源;app、spa 
	 * @apiParam {String} version		接口版本号;例:5.7.0
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiSampleRequest /spa/v2?service=cart.count
	 */
	public function count() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = CURRENT_VERSION_CART_API . '/' . $this->cart_id . '/count' . '?request_id=' . $this->request_id;
		$service_cart_query = $this->basic_query_data();
		$params['url'] = $url;
		$params['data']	= $service_cart_query;
		$params['method'] = 'get';
		$service_cart_response = $this->api_process->process($params);
	
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200 || $response_result->info->http_code == 201) {
			$api_gateway_response['code']	= '200';
			$api_gateway_response['msg']	= '购物车统计成功';
			$api_gateway_response['count']	= $service_cart_response['count'];
		} else {
			$api_gateway_response['code']	= '300';
			$api_gateway_response['msg']	= $service_cart_response['msg'];
		}
		$this->response = $api_gateway_response;
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {post} / 获取购物车
	 * @apiDescription 获取购物车里的所有item
	 * @apiGroup spa/cart
	 * @apiName get
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录Token
	 * @apiParam {String} source		资源;app、spa
	 * @apiParam {String} version		接口版本号;例:5.7.0
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiSampleRequest /spa/v2?service=cart.get
	 */
	public function get() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		self::get_cart_last_data();//获取购物车最新数据
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
		// 		$this->output->enable_profiler(TRUE);
	}
	
	/**
	 * @api {post} / 删除商品|删除购物车
	 * @apiDescription 删除一某个/多个item
	 * @apiGroup spa/cart
	 * @apiName remove
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录Token
	 * @apiParam {String} source		资源;app、spa 
	 * @apiParam {String} version		接口版本号;例:5.7.0
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiParam {String} item_id 需要删除的商品，多个用逗号分隔
	 *
	 * @apiSampleRequest /spa/v2?service=cart.remove
	 */
	public function remove() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = CURRENT_VERSION_CART_API . '/' . $this->cart_id . '?request_id=' . $this->request_id;
		$service_cart_query = $this->basic_query_data();
		// 		$service_cart_query['item_id']	= $this->input->post('item_id');//需要删除的商品，多个用逗号分隔
		$item_id_arr = explode(',', $this->input->post('item_id'));//需要删除的商品，多个用逗号分隔
		foreach ($item_id_arr as $item_id) {
			$service_cart_query['item_id'] = $item_id;
			$params['url'] = $url;
			$params['data']	= $service_cart_query;
			$params['method'] = 'delete';
			$service_cart_response = $this->api_process->process($params);
				
			$response_result = $this->api_process->get('result');
			if ($response_result->info->http_code == 200 || $response_result->info->http_code == 201) {
				$api_gateway_response['code']	= '200';
				$api_gateway_response['msg']	= '购物车删除成功';
			} else {
				$api_gateway_response['code']	= '300';
				$api_gateway_response['msg']	= $service_cart_response['msg'];
			}
			$this->response = $api_gateway_response;
		}
	
		self::get_cart_last_data();
		$this->response = array_merge($this->response, $api_gateway_response);//框架中有析构函数，为避免可能失效不使用exit
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {post} / 清空购物车
	 * @apiDescription 清空购物车(删除购物车所有items)
	 * @apiGroup spa/cart
	 * @apiName clear
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录Token
	 * @apiParam {String} source		资源;app、spa
	 * @apiParam {String} version		接口版本号;例:5.7.0
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiSampleRequest /spa/v2?service=cart.clear
	 */
	public function clear() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$service_cart_query = $this->basic_query_data();
		$params['url']		= CURRENT_VERSION_CART_API . '/' . $this->cart_id . '?request_id=' . $this->request_id;
		$params['data']		= $service_cart_query;
		$params['method']	= 'delete';
		$service_cart_response = $this->api_process->process($params);
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200 || $response_result->info->http_code == 201) {
			$api_gateway_response['code']	= '200';
			$api_gateway_response['msg']	= '购物车清空成功';
		} else {
			$api_gateway_response['code']	= '300';
			$api_gateway_response['msg']	= $service_cart_response['msg'];
		}
		self::get_cart_last_data();
		$this->response = array_merge($this->response, $api_gateway_response);//框架中有析构函数，为避免可能失效不使用exit
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {post} / 增加商品数量
	 * @apiDescription 增加某个item的数量
	 * @apiGroup spa/cart
	 * @apiName increase
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录Token
	 * @apiParam {String} source		资源;app、spa
	 * @apiParam {String} version		接口版本号;例:5.7.0
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiParam {Number} item_id Item ID
	 *
	 * @apiSampleRequest /spa/v2?service=cart.increase
	 */
	public function increase() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$item_id = self::requireRequest('item_id');
		$service_cart_query = $this->basic_query_data();
		$params['url']		= CURRENT_VERSION_CART_API . '/' . $this->cart_id . '/item/' . $item_id . '/qty' . '?request_id=' . $this->request_id;
		$params['data']		= $service_cart_query;
		$params['method']	= 'post';
		$service_cart_response = $this->api_process->process($params);
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200 || $response_result->info->http_code == 201) {
			$api_gateway_response['code']	= '200';
			$api_gateway_response['msg']	= '购物车增加数量成功';
		} else {
			$api_gateway_response['code']	= '300';
			$api_gateway_response['msg']	= $service_cart_response['msg'];
		}
		self::get_cart_last_data();
		$this->response = array_merge($this->response, $api_gateway_response);//框架中有析构函数，为避免可能失效不使用exit
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {post} / 减少商品数量
	 * @apiDescription 减少某个item的数量
	 * @apiGroup spa/cart
	 * @apiName decrease
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录Token
	 * @apiParam {String} source		资源;app、spa
	 * @apiParam {String} version		接口版本号;例:5.7.0
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiParam {Number} item_id Item ID
	 *
	 * @apiSampleRequest /spa/v2?service=cart.decrease
	 */
	public function decrease() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$item_id = self::requireRequest('item_id');
		$url = CURRENT_VERSION_CART_API . '/' . $this->cart_id . '/item/' . $item_id . '/qty' . '?request_id=' . $this->request_id;;
		$service_cart_query = $this->basic_query_data();
		$params['url']		= $url;
		$params['data']		= $service_cart_query;
		$params['method']	= 'delete';
		$service_cart_response = $this->api_process->process($params);
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200 || $response_result->info->http_code == 201) {
			$api_gateway_response['code']	= '200';
			$api_gateway_response['msg']	= '购物车减少数量成功';
		} else {
			$api_gateway_response['code']	= '300';
			$api_gateway_response['msg']	= $service_cart_response['msg'];
		}
		self::get_cart_last_data();
		$this->response = array_merge($this->response, $api_gateway_response);//框架中有析构函数，为避免可能失效不使用exit
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	
	/**
	 * @api {post} / 选中某个商品
	 * @apiDescription 勾选某个item
	 * @apiGroup spa/cart
	 * @apiName select
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id 	登录Token
	 * @apiParam {String} source		资源;app、spa
	 * @apiParam {String} version		接口版本号;例:5.7.0
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiParam {Number} item_id Item ID
	 *
	 * @apiSampleRequest /spa/v2?service=cart.select
	 */
	public function select() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$item_id = self::requireRequest('item_id');
		$service_cart_query = $this->basic_query_data();
		$params['url']		= CURRENT_VERSION_CART_API . '/' . $this->cart_id . '/item/' . $item_id . '/selected' . '?request_id=' . $this->request_id;;
		$params['data']		= $service_cart_query;
		$params['method']	= 'post';
		$service_cart_response = $this->api_process->process($params);
			
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200 || $response_result->info->http_code == 201) {
			$api_gateway_response['code']	= '200';
			$api_gateway_response['msg']	= '购物车勾选成功';
		} else {
			$api_gateway_response['code']	= '300';
			$api_gateway_response['msg']	= $service_cart_response['msg'];
		}
		self::get_cart_last_data();
		$this->response = array_merge($this->response, $api_gateway_response);//框架中有析构函数，为避免可能失效不使用exit
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {post} / 反选某个商品
	 * @apiDescription 反选某个item
	 * @apiGroup spa/cart
	 * @apiName unselect
	 * 
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录Token
	 * @apiParam {String} source		资源;app、spa
	 * @apiParam {String} version		接口版本号;例:5.7.0
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiParam {Number} item_id Item ID
	 *
	 * @apiSampleRequest /spa/v2?service=cart.unselect
	 */
	public function unselect() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$item_id = self::requireRequest('item_id');
		$url = CURRENT_VERSION_CART_API . '/' . $this->cart_id . '/item/' . $item_id . '/selected' . '?request_id=' . $this->request_id;;
		$service_cart_query = $this->basic_query_data();
		$params['url']		= $url;
		$params['data']		= $service_cart_query;
		$params['method']	= 'delete';
		$service_cart_response = $this->api_process->process($params);
			
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200 || $response_result->info->http_code == 201) {
			$api_gateway_response['code']	= '200';
			$api_gateway_response['msg']	= '购物车反选成功';
		} else {
			$api_gateway_response['code']	= '300';
			$api_gateway_response['msg']	= $service_cart_response['msg'];
		}
		self::get_cart_last_data();
		$this->response = array_merge($this->response, $api_gateway_response);//框架中有析构函数，为避免可能失效不使用exit
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {post} / 勾选所有商品
	 * @apiDescription 勾选所有item
	 * @apiGroup spa/cart
	 * @apiName selectall
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录Token
	 * @apiParam {String} source		资源;app、spa
	 * @apiParam {String} version		接口版本号;例:5.7.0
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiSampleRequest /spa/v2?service=cart.selectall
	 */
	public function selectall() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
	
		if (self::get_cart_last_data($cart_data)) {
			// 			pr($cart_data);//@TODO,处理方式
		}
		$service_cart_query = $this->basic_query_data();
		$request = http_build_query($service_cart_query);
	
		$success_response = true;//返回前台结果标志
		foreach ($cart_data['response']['products'] as $k => $v) {
			if ($v['type'] == 'gift') continue;//购物车赠品不参与购物车选择，由购物车服务提供自动增减
			$url = CURRENT_VERSION_CART_API . '/' . $this->cart_id . '/item/' . $v['item_id'] . '/selected' . '?request_id=' . $this->request_id;
			$params['url']		= $url;
			$params['data']		= $service_cart_query;
			$params['method']	= 'post';
			$service_cart_response = $this->api_process->process($params);
	
			$response_result = $this->api_process->get('result');
			if ($response_result->info->http_code == 200 || $response_result->info->http_code == 201) {
				// 				$api_gateway_response['code']	= '200';
				// 				$api_gateway_response['msg']	= '购物车勾选成功';
			} else {
				$success_response = false;
				// 				$api_gateway_response = $service_cart_response;
			}
		}
		if ($success_response == true) {
			$api_gateway_response['code']	= '200';
			$api_gateway_response['msg']	= '购物车全选成功';
		} else {
			$api_gateway_response['code']	= '300';
			$api_gateway_response['msg']	= '购物车全选失败，请稍后再试';
		}
		self::get_cart_last_data();
		$this->response = array_merge($this->response, $api_gateway_response);//框架中有析构函数，为避免可能失效不使用exit
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {post} / 反选所有商品
	 * @apiDescription 反选所有item
	 * @apiGroup spa/cart
	 * @apiName unselectall
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录Token
	 * @apiParam {String} source		资源;app、spa
	 * @apiParam {String} version		接口版本号;例:5.7.0
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiSampleRequest /spa/v2?service=cart.unselectall
	 */
	public function unselectall() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
	
		if (self::get_cart_last_data($cart_data)) {
			// 			pr($cart_data);//@TODO,处理方式
		}
		$service_cart_query = $this->basic_query_data();
		$request = http_build_query($service_cart_query);
	
		$success_response = true;//返回前台结果标志
		foreach ($cart_data['response']['products'] as $k => $v) {
			if ($v['type'] == 'gift') continue;//购物车赠品不参与购物车反选，由购物车服务提供自动取消
			$service_cart_query = $this->basic_query_data();
			$params['url']		= CURRENT_VERSION_CART_API . '/' . $this->cart_id . '/item/' . $v['item_id'] . '/selected' . '?request_id=' . $this->request_id;
			$params['data']		= $service_cart_query;
			$params['method']	= 'delete';
			$service_cart_response = $this->api_process->process($params);
	
			$response_result = $this->api_process->get('result');
			if ($response_result->info->http_code == 200 || $response_result->info->http_code == 201) {
				// 				$api_gateway_response['code']	= '200';
				// 				$api_gateway_response['msg']	= '购物车全反选成功';
			} else {
				$success_response = false;
				// 				$api_gateway_response = $service_cart_response;
			}
		}
		if ($success_response == true) {
			$api_gateway_response['code']	= '200';
			$api_gateway_response['msg']	= '购物车全反选成功';
		} else {
			$api_gateway_response['code']	= '300';
			$api_gateway_response['msg']	= '购物车全反选失败，请稍后再试';
		}
		self::get_cart_last_data();
		$this->response = array_merge($this->response, $api_gateway_response);//框架中有析构函数，为避免可能失效不使用exit
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {post} / 换购列表
	 * @apiDescription 换购列表
	 * @apiGroup spa/cart
	 * @apiName exchange
	 *
	 * @apiParam {Number} log_uid		最近一次登录的用户ID
	 * @apiParam {String} connect_id	登录Token
	 * @apiParam {String} source		资源;app、spa
	 * @apiParam {String} version		接口版本号;例:5.7.0
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiParam {Number} pmt_id 优惠策略id
	 * @apiParam {Number} store_id 商店id
	 *
	 * @apiSampleRequest /spa/v2?service=cart.exchange
	 */
	public function exchange() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = CURRENT_VERSION_CART_API . '/' . $this->cart_id . '/exchangelist' . '?request_id=' . $this->request_id;;
		$service_cart_query = $this->basic_query_data();
		$service_cart_query['pmt_id']	= $this->input->post('pmt_id');
		$service_cart_query['store_id']	= $this->input->post('store_id');
		$params['url']		= $url;
		$params['data']		= $service_cart_query;
		$params['method']	= 'get';
		$service_cart_response = $this->api_process->process($params);
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200 || $response_result->info->http_code == 201) {
			$filter_product = self::filter($service_cart_response);
			$returnParam['response'] = $filter_product;
				
			$api_gateway_response['code']		= '200';
			$api_gateway_response['msg']		= '换购列表加载成功';
			$api_gateway_response['pmt_id']		= $service_cart_response['pmt_id'];
			$api_gateway_response['msg']		= $service_cart_response['add_money'];
			$api_gateway_response['products']	= $filter_product['products'];
		} else {
			$api_gateway_response['code']	= '300';
			$api_gateway_response['msg']	= $service_cart_response['msg'];
		}
		$this->response = $api_gateway_response;
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @method 获取最新购物车数据
	 */
	private function get_cart_last_data(&$returnParam = array()) {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = CURRENT_VERSION_CART_API . '/' . $this->cart_id . '?request_id=' . $this->request_id;
		if ($this->url) $url = $this->url;

		$service_cart_query = $this->basic_query_data();
		$service_cart_query['stores']	= $this->store_id_lists;
		$service_cart_query['range']	= $this->tms_region_type;

		$params['url'] = $url;
		$params['data']	= $service_cart_query;
		$params['method'] = 'get';
		$service_cart_response = $this->api_process->process($params);

		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200 || $response_result->info->http_code == 201) {
			$filter_product = self::filter($service_cart_response);
			$returnParam['response'] = $filter_product;
				
			$api_gateway_response['code']		= '200';
			$api_gateway_response['msg']		= '购物车获取成功';
			$api_gateway_response['cart']['products']	= $filter_product['products'];
			$api_gateway_response['cart']['total']		= $filter_product['total'];
			$api_gateway_response['cart']['count']		= $filter_product['count'];
			$api_gateway_response['cart']['promotions']	= $service_cart_response['promotions'];
			$api_gateway_response['cart']['errors']		= $filter_product['errors'];
			$api_gateway_response['cart']['alerts']		= $service_cart_response['alerts'];
			$return_bool = true;
		} else {
			$api_gateway_response['code']	= '300';
			$api_gateway_response['msg']	= $service_cart_response['msg'];
			$return_bool = false;
		}
		$this->response = $api_gateway_response;
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
		return $return_bool;
	}

	//调整商品详情输出结构
	private function filter($products) {
		$errors_count = 0;
		$products['errors'] = array();
		foreach ($products['products'] as $k => $v) {
			$products['products'][$k]['name']			= isset($v['name']) ? $v['name'] : '';
			$products['products'][$k]['type']			= isset($v['type']) ? $v['type'] : '';
			$products['products'][$k]['price']			= isset($v['price']) ? $v['price'] : '';
			$products['products'][$k]['qty']			= isset($v['qty']) ? $v['qty'] : '';
			$products['products'][$k]['selected']		= isset($v['selected']) ? $v['selected'] : '';
			$products['products'][$k]['valid']			= isset($v['valid']) ? $v['valid'] : '';
			$products['products'][$k]['photo']			= isset($v['product']['photo']) ? $v['product']['photo'] : '';
			if (strtoupper($this->input->get_post('platform')) != 'IOS' && $v['product']['has_webp']) {
				$products['products'][$k]['photo']			= str_replace('.jpg', '.webp', $products['products'][$k]['photo']);//@TODO,最好放外面当参数传递
			}
			$products['products'][$k]['item_id']		= isset($v['item_id']) ? $v['item_id'] : '';
			$products['products'][$k]['store_id']		= isset($v['store_id']) ? $v['store_id'] : '';
			$products['products'][$k]['cart_tag']		= isset($v['cart_tag']) ? $v['cart_tag'] : '';
			$products['products'][$k]['reminder_tag']	= isset($v['reminder_tag']) ? $v['reminder_tag'] : '';
			$products['products'][$k]['delivery_tag']	= isset($v['delivery_tag']) ? $v['delivery_tag'] : '';
			$products['products'][$k]['class_id']		= isset($v['product']['class_id']) ? $v['product']['class_id'] : '';
			$products['products'][$k]['weight']			= isset($v['sku']['weight']) ? $v['sku']['weight'] : '';
			$products['products'][$k]['volume']			= isset($v['sku']['volume']) ? $v['sku']['volume'] : '';
			$products['products'][$k]['unit']			= isset($v['sku']['unit']) ? $v['sku']['unit'] : '';
			$products['products'][$k]['product_no']		= isset($v['sku']['product_no']) ? $v['sku']['product_no'] : '';
			$products['products'][$k]['qty_limit']		= isset($v['qty_limit']) ? $v['qty_limit'] : '';
			$products['products'][$k]['isTodayDeliver']	= isset($v['isTodayDeliver']) ? $v['isTodayDeliver'] : '';

			unset($products['products'][$k]['product'], $products['products'][$k]['sku']);

			if (isset($products['products'][$k]['error']) && $products['products'][$k]['error']) {
				$products['errors'][$errors_count]['code']			= '300';
				$products['errors'][$errors_count]['msg']			= $products['products'][$k]['error'];
				$products['errors'][$errors_count]['product_name']	= $products['products'][$k]['name'];
				$products['errors'][$errors_count]['item_id']		= $products['products'][$k]['item_id'];
				$products['errors'][$errors_count]['action']		= '请购买其他商品';
				$errors_count++;
			}
		}
		// 		$products['products'] = array_reverse($products['products']);
		return $products;
	}

	private function requireRequest($key) {
		if (isset($_REQUEST[$key]) && $_REQUEST[$key]) {
			return htmlspecialchars($_REQUEST[$key]);
		} else {
			$this->code = '300';
			$this->response = array('code' => '300', 'msg' => $key . ' is empty');
			exit;
		}
	}
}