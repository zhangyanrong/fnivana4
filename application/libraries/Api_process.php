<?php
class Api_process {
	private $result;//restclient 返回的结果
	private $show_response;//用于前台展现给用户看的数据
	private $request_url;//请求下层的服务端地址
	private $request_method;//请求方法
	private $request_data;//请求数据
	private $error_code;//错误码,展现在客户端中用来定位问题
	
	public function __construct() {
		$this->CI =& get_instance();
		$this->CI->load->library('restclient');
		$this->CI->load->library('fruit_log');
// 		$this->CI->load->config('service');
	}
	
	public function __destruct() {
		
	}
	
	public function get($var) {
		if (isset($this->$var)) return $this->$var;
		else return '';
	}
	
	/**
	 * @method 内网api调用处理方法
	 * @param array $params
	 * @return mixed
	 * @example
	 * $params['url']			= 'http://apiuser.fruitday.com';//请求接口的资源地址
	 * $params['method']		= 'get';//请求方法
	 * $params['data']['uid']	= '123';//请求的数据
	 * $params['data']['cart_id']= '2312312';
	 */
	public function process($params) {
		self::reset();
		$api_url = $params['url'];
		$this->request_method = $params['method'];
// 		if (strtolower($params['method']) == 'get') {
// 			$this->request_url = $api_url . '?' . http_build_query($params['data']);
// 		} else {
// 			$this->request_url = $api_url;
// 		}
		$this->request_url = $api_url;
		$this->request_data = http_build_query($params['data']);
		$this->result = $this->CI->restclient->execute($this->request_url, $this->request_method, $this->request_data);
		self::responseValidate();
		self::logCollect();
		return $this->show_response;
// 		return $this->result->response;
	}
	
	/**
	 * @method 日志收集方法
	 */
	private function logCollect() {
		$http_code = $this->result->info->http_code;
		$response = $http_code != 500 ? $this->result->response : htmlspecialchars($this->result->response);
		$log_tag = $http_code == '200' && $response != '' ? 'INFO' : 'ERROR';
		
		$parse_url = parse_url($this->request_url);
		if (isset($parse_url['query'])) {
			parse_str($parse_url['query']);
			$log_content['request_id']			= $request_id;//@TODO,请求下层接口不传的话有报notice错误的可能性
		} else {
			$parse_url['query'] = '';
		}
		$log_content['request']['url']		= $parse_url['path'];
		$log_content['request']['get_str']	= $parse_url['query'];
		$log_content['request']['method']	= $this->request_method;
		$log_content['request']['data']		= $this->request_data;
		$log_content['response']['http_code']	= $http_code;
		$log_content['response']['content']		= $response;
		$this->CI->fruit_log->track($log_tag, json_encode($log_content));
	}
	
	/**
	 * @method 返回结果校验
	 */
	public function responseValidate() {
		if ($this->result->info->http_code == 500) {
			$this->error_code = md5($this->request_url . $this->request_data . $this->result->response);
			$this->show_response = array();
			$this->show_response['code']	= '500';
			$this->show_response['msg']		= '服务器异常! 错误码:' . $this->error_code;
		} else {
			$this->show_response = json_decode($this->result->response, true);
		}
	}
	
	/**
	 * @method 清除上次的数据
	 */
	private function reset() {
		$this->result = null;
		$this->show_response = null;
		$this->request_url = null;
		$this->request_method = null;
		$this->request_data = null;
		$this->error_code = null;
	}
}
?>