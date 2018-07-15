<?php
class User extends CI_Controller {
	private $source, $version, $response;
	
	public function __construct() {
		parent::__construct();
		$this->load->config('service');
		$this->load->library('restclient');
		$this->load->library('aes',null,'encrypt_aes');
		$this->load->helper('public');
		$this->load->helper('output');
		$this->request_id =  uniqid('OMS_',true);//用于记录日志用
		define('CURRENT_VERSION_USER_API', $this->config->item('user', 'service'));
		$this->source = $this->input->get_post('source');
		$this->version = $this->input->get_post('version');
	}
	
	public function __destruct() {
		if($this->response['code'] != '200'){
			$this->rollback();
		}
		oms_output($this->response);
	}

	public function returnGift(){
		$data = $this->input->get_post('data');
		$data = str_replace(' ','+',trim($data));
		$data = $this->ci->encrypt_aes->AesDecrypt($data);
		$data = json_decode($data,true);
		$user_gift_id = $data['freeProdId'];
		$order_name = $data['orderNo'];

		$query = array();
        $query['user_gift_id'] = $user_gift_id;
        $query['order_name'] = $order_name;
        $query['request_id'] = $this->request_id;
        $url = CURRENT_VERSION_USER_API . '/' .'user_gift/return_gift';
        $service_request = http_build_query($query);
		$result = $this->restclient->post($url,$service_request);
		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true); 
		$code_first = substr($code, 0, 1);
		$jf = 0;
		if ($code_first == 5 || !$service_response) {
			exit;
		}elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= $service_response['msg'];
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2 && $service_response) {
			$gateway_response['code']	= '200';
			$gateway_response['msg']	= $service_response['msg'];
			$this->response = $gateway_response;
			exit;
		}
	}




	private function rollback(){
        $rollback_url = CURRENT_VERSION_USER_API.'/'.'rollback' ;
        $request['request_id'] = $this->request_id;
        $service_request = http_build_query($request);
        $this->restclient->post($rollback_url,$service_request);
	}
}