<?php 
define('NIRVANA_SECRET_KEY', 'caa21c26dfc990c7a534425ec87a111c');//@TODO
class Logistics extends CI_Controller {
	public function __construct() {
		parent::__construct();
		$this->load->config('service');
		$this->load->library('api_process');
		$this->load->helper('public');
		$this->source = 'app';
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
	 * @api {get} / 物流详情
	 * @apiDescription 获取物流详情
	 * @apiGroup spa/logistics
	 * @apiName detail
	 *
	 * @apiParam {String} [connect_id]		登录成功后返回的connect_id;
	 * @apiParam {String} order_name		订单编号
	 * 
	 * @apiSampleRequest /spa/v2?service=logistics.detail
	 */
	public function detail() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = $this->config->item('nirvana', 'service') . '/api?request_id=' . $this->request_id;
		$query['connect_id']	= $this->input->get_post('connect_id');
		$query['order_name']	= require_request('order_name');
		$query['timestamp']		= time();
		$query['service']		= 'order.logisticTrace';
		$query['version']		= '5.7.0';
		$query['source']		= $this->source;
		$query['sign']			= self::Sign($query);
		
		$params['url'] = $url;
		$params['data']	= $query;
		$params['method'] = 'post';
		$service_response = $this->api_process->process($params);
		
		$response_arr['code']	= '200';
		$response_arr['data']	= $service_response;
		
		$this->response = &$response_arr;
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
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
}
?>
