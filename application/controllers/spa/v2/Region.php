<?php
define('NIRVANA_SECRET_KEY', 'caa21c26dfc990c7a534425ec87a111c');//@TODO
class Region extends CI_Controller {
	public function __construct() {
		parent::__construct();
		$this->load->config('service');
		define('CURRENT_VERSION_CART_API', $this->config->item('cart', 'service') . '/v3/cart');//@TODO
		define('CURRENT_VERSION_PRODUCT_API', $this->config->item('product', 'service') . '/v1/product');//@TODO
 		define("NIRVANA_URL", $this->config->item('nirvana', 'service') . '/api');//@TODO
		define("NIRVANA2_URL", $this->config->item('nirvana2', 'service') . '/api');//@TODO

		$this->load->library('api_process');
		$this->load->library('fruit_log');
		$this->load->helper('public');
		$this->request_id = date('Y_m_d_H_i_s') . '_' . md5($this->input->get_post('device_id') . mt_rand(10000, 99999));//用于记录日志用

		$this->source = 'app';
		$this->version = '9.9.0';//@TODO
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
	 * @api {get} /		获取地区分站列表
	 * @apiDescription	获取地区分站列表
	 * @apiGroup		spa/region
	 * @apiName			region_site_list
	 *
	 * @apiSampleRequest /spa/v2?service=region.region_site_list
	 */
	public function region_site_list() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['source']		= $this->source;//@TODO
		$query['service']		= 'region.regionSiteList';
		$query['timestamp']		= time();
		$query['version']		= $this->version;
		$query['sign']			= self::Sign($query);
		
		$params['url']		= NIRVANA2_URL;
		$params['data']		= $query;
		$params['method']	= 'post';
		$result = $this->api_process->process($params);
		$response['code']	= "200";
		$response['data']	= $result;
		$this->response = & $response;
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {get} /	获取地区信息
	 * @apiDescription	获取地区信息
	 * @apiGroup		spa/region
	 * @apiName			get_region
	 * 
	 * @apiParam {String} area_pid	地区父ID
	 *
	 * @apiSampleRequest /spa/v2?service=region.get_region
	 */
	public function get_region() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['source']		= $this->source;
		$query['service']		= 'region.getRegion';
		$query['timestamp']		= time();
		$query['version']		= $this->version;
		$query['area_pid']		= require_request('area_pid');
		$query['sign']			= self::Sign($query);
		
		$params['url']		= NIRVANA2_URL;
		$params['data']		= $query;
		$params['method']	= 'post';
		$result = $this->api_process->process($params);
		$response['code']	= "200";
		$response['data']	= $result;
		$this->response = & $response;
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {get} /	获取投递时间
	 * @apiDescription	获取投递时间
	 * @apiGroup		spa/region
	 * @apiName			get_send_time
	 *
	 * @apiParam {String} area_id		地区ID(例:106093)
	 * @apiParam {String} region_id		地区ID(例:106092)
	 *
	 * @apiSampleRequest /spa/v2?service=region.get_send_time
	 */
	public function get_send_time() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['source']                = 'wap';//@TODO
		$query['service']               = 'region.getSendTime';
		$query['timestamp']             = time();
		$query['version']               = $this->version;
		$query['area_id']               = require_request('area_id');
		$query['region_id']             = require_request('region_id');
		$query['sign']                  = self::Sign($query);
		
		$params['url']          = NIRVANA_URL;
		$params['data']         = $query;
		$params['method']       = 'post';
		$result = $this->api_process->process($params);
		if (isset($result['msg']) && $result['msg']) {
			$api_gateway_response   = $result;
		} else {
			$api_gateway_response['code']   = '200';
			$api_gateway_response['data']   = $result;
		}
		$this->response = & $api_gateway_response;
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	private function Sign($data) {
		$api_secret_key = NIRVANA_SECRET_KEY;//@TODO
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
}