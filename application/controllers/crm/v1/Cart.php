<?php
class Cart extends CI_Controller {
	private $source, $version, $device_id, $connect_id, $response, $cart_id;

	public function __construct() {
		parent::__construct();
		$this->load->config('service');
		define('CURRENT_VERSION_CART_API', $this->config->item('cart', 'service') . '/v3/cart');//@TODO
		$this->load->library('restclient');
		$this->load->library('fruit_log');
		$this->load->helper('public');
		$this->load->helper('output');
		$this->request_id = date('Y_m_d_H_i_s') . '_' . md5($this->input->get_post('device_id') . mt_rand(10000, 99999));//用于记录日志用

		$this->source = $this->input->get_post('source');
		$this->version = $this->input->get_post('version');
		$this->device_id = $this->input->get_post('device_id');
		$this->area_adcode = $this->input->get_post('area_adcode');

		$temp = changeStoreId($this->input->post('store_id_list'));
		$this->store_id_lists = $temp['store_id_list'];
		$this->tms_region_type = $temp['tms_region_type'];
		$this->tms_region_time = $temp['tms_region_time'];
		$this->uid = $this->input->get_post('uid');
// 		$this->url = '';//@TODO,用来请求的底层接口的URL地址
	}

	public function __destruct() {
		crm_output($this->response);
// 		echo json_encode($this->response);
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
		if ($this->uid != '') $service_cart_query['uid']= $this->uid;
		return $service_cart_query;
	}
	
	/**
	 * @api {post} / 模拟换购专区
	 * @apiDescription 模拟换购专区
	 * @apiGroup crm/cart
	 * @apiName exchange_block
	 *
	 * @apiParam {String} uid			用户ID号
	 * @apiParam {String} store_id_list 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode] 三级地区编码
	 *
	 * @apiSampleRequest /crm/v1?service=cart.exchange_block&source=crm
	 */
	public function exchange_block() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		
		$uid = $this->input->get_post('uid');
		if (!$uid) throw new \Exception('uid is missing!');
		$this->cart_id = $uid;
		$url = CURRENT_VERSION_CART_API . '/' . $this->cart_id . '/exchangeblock' . '?request_id=' . $this->request_id;;
	
		$service_cart_query = $this->basic_query_data();
		$service_cart_query['stores']	= $this->store_id_lists;
		$service_cart_query['source']	= 'app';//@TODO,这个应该为crm渠道
	
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
			$gateway_response['msg']	= '换购区加载失败,请稍后再试';
			$log_tag = 'ERROR';
			$log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
		} elseif ($code_first == 2 && $service_cart_response) {
			$filter_product = self::filter($service_cart_response);
			$returnParam['response'] = $filter_product;
	
			$gateway_response['code']		= '200';
			$gateway_response['msg']		= '换购区加载成功';
			$gateway_response['data']		= $filter_product['products'];
			$log_tag = 'INFO';
		} elseif ($code_first == 3 || $code_first == 4) {
			$gateway_response['code']	= '300';
			$gateway_response['msg']	= '换购区加载失败,请稍后再试';
			$log_tag = 'INFO';
		}
		$this->fruit_log->track($log_tag, json_encode($log_content));
		$this->response = $gateway_response;
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {post} / 模拟获取购物车
	 * @apiDescription 模拟获取购物车里的所有item
	 * @apiGroup crm/cart
	 * @apiName virtualget
	 *
	 * @apiParam {String} uid=100		用户ID
	 * @apiParam {String} store_id_list=1,21T1T1 门店列表，多个用逗号分隔
	 * @apiParam {String} [area_adcode=310000] 三级地区编码
	 * @apiParam {String} version=5.4.0	客户端版本号;例:5.4.0;
	 * @apiParam {String} source=crm	客户端渠道;
	 *
	 * @apiSampleRequest /crm/v1?service=cart.virtualget&source=crm
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
			$log_tag = 'ERROR';
			$log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
			$api_gateway_response['code']	= '500';
			$api_gateway_response['msg']	= '购物车获取失败';
			$return_bool = false;
		} elseif ($code_first == 2 && $service_cart_response) {
			$filter_product = self::filter($service_cart_response);
			$returnParam['response'] = $filter_product;
	
			$api_gateway_response['code']		= '200';
			$api_gateway_response['msg']		= '购物车获取成功';
			$api_gateway_response['data']['products']	= $filter_product['products'];
			$api_gateway_response['data']['total']		= $filter_product['total'];
			$api_gateway_response['data']['count']		= $filter_product['count'];
			$api_gateway_response['data']['promotions']	= $service_cart_response['promotions'];
			$api_gateway_response['data']['errors']		= $filter_product['errors'];
			$api_gateway_response['data']['alerts']		= $service_cart_response['alerts'];
			$return_bool = true;
			$log_tag = 'INFO';
		} elseif ($code_first == 3 || $code_first == 4) {
			$api_gateway_response['code']	= '500';
			$api_gateway_response['msg']	= '购物车获取失败';
			$return_bool = false;
		}
		$this->fruit_log->track($log_tag, json_encode($log_content));
		// 		$this->fruit_log->track('INFO', json_encode($log_content));
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
// 			if (strtoupper($this->input->get_post('platform')) != 'IOS' && $v['product']['has_webp']) {
// 				$products['products'][$k]['photo']			= str_replace('.jpg', '.webp', $products['products'][$k]['photo']);//@TODO,最好放外面当参数传递
// 			}
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
}