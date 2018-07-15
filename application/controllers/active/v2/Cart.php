<?php
class Cart extends CI_Controller {
	private $source, $version, $device_id, $connect_id, $response, $cart_id;

	public function __construct() {
		parent::__construct();
		$this->load->config('service');
		define('CURRENT_VERSION_CART_API', $this->config->item('cart', 'service') . '/v3/cart');//@TODO
		define('CURRENT_VERSION_PRODUCT_API', $this->config->item('product', 'service') . '/v1/product');//@TODO

		$this->load->library('api_process');//@TODO

		$this->load->library('restclient');
		$this->load->library('fruit_log');
		$this->load->helper('public');
		$this->request_id = date('Y_m_d_H_i_s') . '_' . md5($this->input->get_post('device_id') . mt_rand(10000, 99999));//用于记录日志用

		$this->source = $this->input->get_post('source');
		$this->version = $this->input->get_post('version');
		$this->device_id = $this->input->get_post('device_id');
		$this->connect_id = $this->input->get_post('connect_id');
		$this->area_adcode = $this->input->get_post('area_adcode');

		$temp = changeStoreId($this->input->post('store_id_list'));
		$this->store_id_lists = $temp['store_id_list'];
		$this->tms_region_type = $temp['tms_region_type'];
		$this->tms_region_time = $temp['tms_region_time'];
		self::init_cart_id();
		$this->url = '';//@TODO,用来请求的底层接口的URL地址
	}

	public function __destruct() {
		echo json_encode($this->response);
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
		// 		$service_cart_query['device_id']		= $this->device_id;//登录Token
		// 		$service_cart_query['connect_id']		= $this->connect_id;//登录Token
		// 		$service_cart_query['request_id']		= $this->request_id;//请求id
		$service_cart_query['stores']			= $this->store_id_lists;//门店列表,多个用逗号分隔
		$service_cart_query['range']			= $this->tms_region_type;//门店配送范围?
		$service_cart_query['area_adcode']		= $this->area_adcode;//三级地区码
		if (isset($this->uid)) $service_cart_query['uid']= $this->uid;
		return $service_cart_query;
	}

	private function init_cart_id() {
		try {
			$connect_id = $this->input->get_post('connect_id');
			if ($connect_id) {
				$this->uid = get_uid($connect_id);
				$this->load->library('Nirvana2session', array('session_id' => $connect_id));
				if ($this->uid != $this->nirvana2session->get('id')) throw new \Exception('登录信息已过期,请重新登录!CART');
			}
			$this->cart_id = isset($this->uid) ? $this->uid : $this->input->get_post('device_id');
		} catch (\Exception $e) {
			$this->response['code'] = '400';
			$this->response['msg'] = $e->getMessage();
			exit;
		}
	}

	/**
	 * @api {post} / 添加商品
	 * @apiDescription 添加一个/多个item
	 * @apiGroup active/cart
	 * @apiName add
	 *
	 * @apiParam {String} [connect_id] 登录Token
	 * @apiParam {String} [device_id=5aee7f491ffe2a6edc44bbf94a4cb8cc] 登录Token
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiParam {String} pid=13922 商品id(product_id)，多个用逗号分隔
	 * @apiParam {String} sid=1 商店id，多个用逗号分隔
	 * @apiParam {String} type=normal 商品类型(normal/gift/exchange/user_gift)，多个用逗号分隔
	 * @apiParam {String} [gift_send_id] 加入会员赠品时必填
	 * @apiParam {String} [gift_active_type] 加入会员赠品时必填
	 * @apiParam {String} [user_gift_id] 加入会员赠品时必填
	 * @apiParam {String} [pmt_id] 加入换购商品时必填
	 * @apiParam {Number} [user_gift_mutex] 是否与其他会员赠品互斥:1.互斥;0.不互斥 或者不传;
	 * @apiParam {Number} [mutex]			同上,为了解决app参数传错的问题
	 *
	 * @apiSampleRequest /active/v2?service=cart.add&source=app
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
		// 		$pmt_ids			= explode(',', $this->input->post('pmt_id'));
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
					$api_gateway_response = $service_cart_response;
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
	 * @apiGroup active/cart
	 * @apiName count
	 *
	 * @apiParam {String} [connect_id] 登录Token
	 * @apiParam {String} [device_id=5aee7f491ffe2a6edc44bbf94a4cb8cc] 登录Token
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiSampleRequest /active/v2?service=cart.count&source=app
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
		if ($response_result->info->http_code == 200) {
			$api_gateway_response['code']	= '200';
			$api_gateway_response['msg']	= '购物车统计成功';
			$api_gateway_response['count']	= $service_cart_response['count'];
		} else {
			$api_gateway_response = $service_cart_response;
		}
		$this->response = $api_gateway_response;
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api {post} / 获取购物车
	 * @apiDescription 获取购物车里的所有item
	 * @apiGroup active/cart
	 * @apiName get
	 *
	 * @apiParam {String} [connect_id] 登录Token
	 * @apiParam {String} [device_id=5aee7f491ffe2a6edc44bbf94a4cb8cc] 登录Token
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiSampleRequest /active/v2?service=cart.get&source=app
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
	 * @apiGroup active/cart
	 * @apiName remove
	 *
	 * @apiParam {String} [connect_id] 登录Token
	 * @apiParam {String} [device_id=5aee7f491ffe2a6edc44bbf94a4cb8cc] 登录Token
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiParam {String} item_id 需要删除的商品，多个用逗号分隔
	 *
	 * @apiSampleRequest /active/v2?service=cart.remove&source=app
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
			if ($response_result->info->http_code == 200) {
				$api_gateway_response['code']	= '200';
				$api_gateway_response['msg']	= '购物车删除成功';
			} else {
				$api_gateway_response = $service_cart_response;
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
	 * @apiGroup active/cart
	 * @apiName clear
	 *
	 * @apiParam {String} [connect_id] 登录Token
	 * @apiParam {String} [device_id=5aee7f491ffe2a6edc44bbf94a4cb8cc] 登录Token
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiSampleRequest /active/v2?service=cart.clear&source=app
	 */
	public function clear() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$service_cart_query = $this->basic_query_data();
		$params['url']		= CURRENT_VERSION_CART_API . '/' . $this->cart_id . '?request_id=' . $this->request_id;
		$params['data']		= $service_cart_query;
		$params['method']	= 'delete';
		$service_cart_response = $this->api_process->process($params);
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200) {
			$api_gateway_response['code']	= '200';
			$api_gateway_response['msg']	= '购物车清空成功';
		} else {
			$api_gateway_response = $service_cart_response;
		}
		self::get_cart_last_data();
		$this->response = array_merge($this->response, $api_gateway_response);//框架中有析构函数，为避免可能失效不使用exit
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api {post} / 增加商品数量
	 * @apiDescription 增加某个item的数量
	 * @apiGroup active/cart
	 * @apiName increase
	 *
	 * @apiParam {String} [connect_id] 登录Token
	 * @apiParam {String} [device_id=5aee7f491ffe2a6edc44bbf94a4cb8cc] 登录Token
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiParam {Number} item_id Item ID
	 *
	 * @apiSampleRequest /active/v2?service=cart.increase&source=app
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
		if ($response_result->info->http_code == 200) {
			$api_gateway_response['code']	= '200';
			$api_gateway_response['msg']	= '购物车增加数量成功';
		} else {
			$api_gateway_response = $service_cart_response;
		}
		self::get_cart_last_data();
		$this->response = array_merge($this->response, $api_gateway_response);//框架中有析构函数，为避免可能失效不使用exit
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api {post} / 减少商品数量
	 * @apiDescription 减少某个item的数量
	 * @apiGroup active/cart
	 * @apiName decrease
	 *
	 * @apiParam {String} [connect_id] 登录Token
	 * @apiParam {String} [device_id=5aee7f491ffe2a6edc44bbf94a4cb8cc] 登录Token
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiParam {Number} item_id Item ID
	 *
	 * @apiSampleRequest /active/v2?service=cart.decrease&source=app
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
		if ($response_result->info->http_code == 200) {
			$api_gateway_response['code']	= '200';
			$api_gateway_response['msg']	= '购物车减少数量成功';
		} else {
			$api_gateway_response = $service_cart_response;
		}
		self::get_cart_last_data();
		$this->response = array_merge($this->response, $api_gateway_response);//框架中有析构函数，为避免可能失效不使用exit
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}


	/**
	 * @api {post} / 选中某个商品
	 * @apiDescription 勾选某个item
	 * @apiGroup active/cart
	 * @apiName select
	 *
	 * @apiParam {String} [connect_id] 登录Token
	 * @apiParam {String} [device_id=5aee7f491ffe2a6edc44bbf94a4cb8cc] 登录Token
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiParam {Number} item_id Item ID
	 *
	 * @apiSampleRequest /active/v2?service=cart.select&source=app
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
			$api_gateway_response = $service_cart_response;
		}
		self::get_cart_last_data();
		$this->response = array_merge($this->response, $api_gateway_response);//框架中有析构函数，为避免可能失效不使用exit
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api {post} / 反选某个商品
	 * @apiDescription 反选某个item
	 * @apiGroup active/cart
	 * @apiName unselect
	 *
	 * @apiParam {String} [connect_id] 登录Token
	 * @apiParam {String} [device_id=5aee7f491ffe2a6edc44bbf94a4cb8cc] 登录Token
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiParam {Number} item_id Item ID
	 *
	 * @apiSampleRequest /active/v2?service=cart.unselect&source=app
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
			$api_gateway_response = $service_cart_response;
		}
		self::get_cart_last_data();
		$this->response = array_merge($this->response, $api_gateway_response);//框架中有析构函数，为避免可能失效不使用exit
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api {post} / 勾选所有商品
	 * @apiDescription 勾选所有item
	 * @apiGroup active/cart
	 * @apiName selectall
	 *
	 * @apiParam {String} [connect_id] 登录Token
	 * @apiParam {String} [device_id=5aee7f491ffe2a6edc44bbf94a4cb8cc] 登录Token
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiSampleRequest /active/v2?service=cart.selectall&source=app
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
	 * @apiGroup active/cart
	 * @apiName unselectall
	 *
	 * @apiParam {String} [connect_id] 登录Token
	 * @apiParam {String} [device_id=5aee7f491ffe2a6edc44bbf94a4cb8cc] 登录Token
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiSampleRequest /active/v2?service=cart.unselectall&source=app
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
	 * @api {post} / 合并购物车
	 * @apiDescription 合并临时购物车(基于device_id)和用户购物车(基于connect_id/user_id)，临时购物车会被清空
	 * @apiGroup active/cart
	 * @apiName merge
	 *
	 * @apiParam {String} [connect_id] 登录Token
	 * @apiParam {String} [device_id=5aee7f491ffe2a6edc44bbf94a4cb8cc] 登录Token
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiSampleRequest /active/v2?service=cart.merge&source=app
	 */
	public function merge() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

		//初始化成功信息
		$api_gateway_response['code']	= '200';
		$api_gateway_response['msg']	= '购物车合并成功';
		//获取临时购物车
		$this->cart_id = $this->input->get_post('device_id');
		if (self::get_cart_last_data($cart_data) && $cart_data['response']['products']) {
			self::init_cart_id();//重置购物车
			$url = CURRENT_VERSION_CART_API . '/' . $this->cart_id . '?request_id=' . $this->request_id;;
			foreach ($cart_data['response']['products'] as $k => $v) {
				if ($v['type'] == 'gift') continue;//普通赠品过滤
				$service_cart_query = $this->basic_query_data();
				$service_cart_query['product_id']	= $v['product_id'];//商品id(product_id)，多个用逗号分隔
				$service_cart_query['type']			= $v['type'];//商品类型(normal/gift/exchange/user_gift)，多个用逗号分隔
				$service_cart_query['qty']			= $v['qty'];
				$params['url']		= $url;
				$params['data']		= $service_cart_query;
				$params['method']	= 'post';
				$service_cart_response = $this->api_process->process($params);
				$response_result = $this->api_process->get('result');
				if ($response_result->info->http_code == 200 || $response_result->info->http_code == 201) $clear_cart = true;
			}
			//执行清空临时购物车
			if (isset($clear_cart) && $clear_cart == true) {
				$this->cart_id = $this->input->get_post('device_id');//变更为临时购物车id
				$url = CURRENT_VERSION_CART_API . '/' . $this->cart_id . '?request_id=' . $this->request_id;
				$service_cart_query = $this->basic_query_data();
				// 				$service_cart_query['item_id'] = $item_id;
				$params['url']		= $url;
				$params['data']		= $service_cart_query;
				$params['method']	= 'delete';
				$service_cart_response = $this->api_process->process($params);
					
				$response_result = $this->api_process->get('result');
				if ($response_result->info->http_code == 200 || $response_result->info->http_code == 201) {
					$api_gateway_response['code']	= '200';
					$api_gateway_response['msg']	= '购物车合并成功';
				} else {
					$api_gateway_response['code']	= '300';
					$api_gateway_response['msg']	= '购物车合并失败,请稍后再试';
				}
				$this->response = $api_gateway_response;
			}
		}
		self::init_cart_id();//重置购物id为用户ID
		self::get_cart_last_data();
		$this->response = array_merge($this->response, $api_gateway_response);//框架中有析构函数，为避免可能失效不使用exit
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api {post} / 换购列表
	 * @apiDescription 换购列表
	 * @apiGroup active/cart
	 * @apiName exchange
	 *
	 * @apiParam {String} [connect_id] 登录Token
	 * @apiParam {String} [device_id=5aee7f491ffe2a6edc44bbf94a4cb8cc] 登录Token
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiParam {Number} pmt_id 优惠策略id
	 * @apiParam {Number} store_id 商店id
	 *
	 * @apiSampleRequest /active/v2?service=cart.exchange&source=app
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
		if ($response_result->info->http_code == 200) {
			$filter_product = self::filter($service_cart_response);
			$returnParam['response'] = $filter_product;
				
			$api_gateway_response['code']		= '200';
			$api_gateway_response['msg']		= '换购列表加载成功';
			$api_gateway_response['pmt_id']		= $service_cart_response['pmt_id'];
			$api_gateway_response['msg']		= $service_cart_response['add_money'];
			$api_gateway_response['products']	= $filter_product['products'];
		} else {
			$api_gateway_response = $service_cart_response;
		}
		$this->response = $api_gateway_response;
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api {post} / 换购专区
	 * @apiDescription 换购专区
	 * @apiGroup active/cart
	 * @apiName exchangeBlock
	 *
	 * @apiParam {String} [connect_id] 登录Token
	 * @apiParam {String} [device_id=5aee7f491ffe2a6edc44bbf94a4cb8cc] 登录Token
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiSampleRequest /active/v2?service=cart.exchangeBlock&source=app
	 */
	public function exchangeBlock() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = CURRENT_VERSION_CART_API . '/' . $this->cart_id . '/exchangeblock' . '?request_id=' . $this->request_id;;
		$service_cart_query = $this->basic_query_data();
		$service_cart_query['stores']	= $this->store_id_lists;
		$params['url']		= $url;
		$params['data']		= $service_cart_query;
		$params['method']	= 'get';
		$service_cart_response = $this->api_process->process($params);
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200) {
			$filter_product = self::filter($service_cart_response);
			$returnParam['response'] = $filter_product;
				
			$api_gateway_response['code']		= '200';
			$api_gateway_response['msg']		= '换购列表加载成功';
			$api_gateway_response['products']	= $filter_product['products'];
		} else {
			$api_gateway_response = $service_cart_response;
		}
		$this->response = $api_gateway_response;
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api {post} / 验证商品
	 * @apiDescription 验证item
	 * @apiGroup active/cart
	 * @apiName validate
	 *
	 * @apiParam {String} [connect_id] 登录Token
	 * @apiParam {String} [device_id=5aee7f491ffe2a6edc44bbf94a4cb8cc] 登录Token
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiParam {String} item_id 需要提示失效的商品，多个用逗号分隔
	 *
	 * @apiSampleRequest /active/v2?service=cart.validate&source=app
	 */
	public function validate() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		self::get_cart_last_data($returnParam);//获取购物车最新数据

		// 需要报错的商品
		$items = explode(',', $this->input->get_post('item_id'));

		foreach($returnParam['response']['errors'] as $k => $v) {
			if (in_array($v['item_id'], $items)) $error_array[] = $v['product_name'] . ', ' . $v['msg'];
		}

		if (isset($error_array)) $error_string = implode($error_array, "\n");

		if (isset($error_string)) {
			$response['code']	= '304';
			$response['msg']	= $error_string;
			$response['action']	= '返回购物车查看';
			$response['cart']	= $returnParam['response'];
		} else {
			$response['code']	= '200';
			$response['msg']	= '校验商品成功';
			$response['action']	= '跳转到结算页';
			$response['cart']	= $returnParam['response'];

		}
		$this->response = $response;
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api {post} / 收藏商品
	 * @apiDescription 收藏单个/多个商品
	 * @apiGroup active/cart
	 * @apiName mark
	 *
	 * @apiParam {String} [connect_id] 登录Token
	 * @apiParam {String} [device_id=5aee7f491ffe2a6edc44bbf94a4cb8cc] 登录Token
	 * @apiParam {String} store_id_list 商店id列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiParam {Number} product_id 商品id，多个用逗号分隔
	 *
	 * @apiSampleRequest /active/v2?service=cart.mark&source=app
	 */
	public function mark() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		try {
			if (!$this->uid) throw new \Exception('请登录后操作');
			$uid = $this->uid;

			$product_ids = $this->input->get_post('product_id');
			if (!$product_ids) throw new \Exception('产品ID错误');

			$product_ids = explode(',', $product_ids);
			$mark_success = false;//关注成功状态
			foreach ($product_ids as $k => $v) {
				$url = CURRENT_VERSION_PRODUCT_API . '/' . $v . '/user/' . $uid . '/mark';
				$params['url']		= $url;
				$params['data']		= array();
				$params['method']	= 'post';
				$service_cart_response = $this->api_process->process($params);
				$response_result = $this->api_process->get('result');
				if ($response_result->info->http_code == 200) $mark_success = true;
			}
			if ($mark_success != true) throw new \Exception('购物车商品关注失败');
			$api_gateway_response['code']	= '200';
			$api_gateway_response['msg']	= '购物车商品关注成功';
		} catch (\Exception $e) {
			$api_gateway_response['code']	= '300';
			$api_gateway_response['msg']	= $e->getMessage();
		}
		$this->response = $api_gateway_response;

		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api {post} / 获取所有有效的优惠规则
	 * @apiDescription 获取所有有效的优惠规则
	 * @apiGroup active/cart
	 * @apiName promotion
	 *
	 * @apiParam {String} source 渠道
	 * @apiParam {String} stores 商店id列表，多个用逗号分隔
	 * @apiParam {String} member 用户等级(范围1~6)
	 *
	 * @apiSampleRequest /active/v2?service=cart.promotion&source=app
	 */
	public function promotion() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = $this->config->item('cart', 'service') . '/v3/promotion?request_id=' . $this->request_id;

		$service_cart_query = $this->basic_query_data();
		$service_cart_query['source']	= $this->source;
		$service_cart_query['member']	= $this->input->get_post('member');
		// 		$service_cart_query['stores']	= $this->store_id_lists;
		$service_cart_query['stores']	= $this->input->get_post('stores');

		$request = http_build_query($service_cart_query);
		$result = $this->restclient->get($url, $request);
		$code = $result->info->http_code;
		$service_cart_response = json_decode($result->response, true);

		$log_content['request_id']		= $this->request_id;
		$log_content['request']['url']		= $url;
		$log_content['request']['method']	= 'GET';
		$log_content['request']['content']	= $request;
		$log_content['response']['code']	= $code;
		$log_content['response']['content'] = $service_cart_response;

		$code_first = substr($code, 0, 1);
		if ($code_first == 5 || !$service_cart_response) {
			$gateway_response['code']	= '300';
			$gateway_response['msg']	= '获取所有有效的优惠规则失败';
			$log_tag = 'ERROR';
			$log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
		} elseif ($code_first == 2 && $service_cart_response) {
			$gateway_response['code']		= '200';
			$gateway_response['msg']		= '获取所有有效的优惠规则成功';
			$gateway_response['data']		= $service_cart_response;
			$log_tag = 'INFO';
		} elseif ($code_first == 3 || $code_first == 4) {
			$gateway_response['code']	= '300';
			$gateway_response['msg']	= '获取所有有效的优惠规则失败,请稍后再试';
			$log_tag = 'INFO';
		}
		$this->fruit_log->track($log_tag, json_encode($log_content));
		$this->response = $gateway_response;
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api {post} / 凑单接口
	 * @apiDescription 凑单接口
	 * @apiGroup active/cart
	 * @apiName add_on
	 *
	 * @apiParam {String} [connect_id] 登录Token
	 * @apiParam {String} [device_id=5aee7f491ffe2a6edc44bbf94a4cb8cc] 登录Token
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiParam {String} pmt_id 优惠策略id
	 *
	 * @apiSampleRequest /active/v2?service=cart.add_on&source=app
	 */
	public function add_on() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = $this->config->item('cart', 'service') . '/v3/cart/' . $this->cart_id . '/addmore?request_id=' . $this->request_id;
		$service_cart_query = $this->basic_query_data();
		$service_cart_query['pmt_id']	= self::requireRequest('pmt_id');
		$service_cart_query['uid']		= $this->uid;

		$params['url']		= $url;
		$params['data']		= $service_cart_query;
		$params['method']	= 'get';
		$service_cart_response = $this->api_process->process($params);
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200) {
			$api_gateway_response['code']		= '200';
			$api_gateway_response['msg']		= '获取凑单信息加载成功';
			unset($service_cart_response['msg']);
			$service_cart_response['promotionMsg'] = $service_cart_response['name'];
			foreach ($service_cart_response['products'] as $k => $v) {
				$service_cart_response['products'][$k]['product_name']	= $v['name'];
				$service_cart_response['products'][$k]['photo']			= $v['product']['photo'];
				$service_cart_response['products'][$k]['stock']			= 1;//@TODO
				$service_cart_response['products'][$k]['id']			= $v['product_id'];
				$service_cart_response['products'][$k]['unit']			= $v['sku']['unit'];
				$service_cart_response['products'][$k]['volume']		= $v['sku']['volume'];
				$service_cart_response['products'][$k]['setAlert']		= -1;//@TODO
				if (isset($v['delivery_tag'])) $service_cart_response['products'][$k]['deliverType']= $v['delivery_tag'];
				$service_cart_response['products'][$k]['product_desc']	= '';//@TODO,$v['product_desc']
			}
			$api_gateway_response['data']		= $service_cart_response;
		} else {
			$api_gateway_response = $service_cart_response;
		}
		$this->response = $api_gateway_response;
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api {post} / 模拟获取购物车
	 * @apiDescription 模拟获取购物车里的所有item
	 * @apiGroup active/cart
	 * @apiName virtualget
	 *
	 * @apiParam {String} uid=100		用户ID
	 * @apiParam {String} store_id_list=1,21T1T1 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode=310000] 三级地区编码
	 * @apiParam {String} version=5.4.0	客户端版本号;例:5.4.0;
	 * @apiParam {String} source=app	客户端渠道;例:app、wap、pc;
	 *
	 * @apiSampleRequest /active/v2?service=cart.virtualget&source=app
	 */
	public function virtualget() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		try {
			$uid = $this->input->get_post('uid');
			if (!$uid) throw new \Exception('uid is missing!');
			$this->cart_id = $uid;
			$this->url = CURRENT_VERSION_CART_API . '/' . $this->cart_id . '/virtual?request_id=' . $this->request_id;;;//@TODO
			self::get_cart_last_data();//获取购物车最新数据
		} catch (\Exception $e) {
			$this->response['code']	= '300';
			$this->response['msg'] = $e->getMessage();
		}
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
		if ($response_result->info->http_code == 200) {
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
			$api_gateway_response = $service_cart_response;
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