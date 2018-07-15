<?php
class User extends CI_Controller {
	public function __construct() {
        parent::__construct();
        $this->load->config('service');
        $this->load->helper('http');
        $this->load->library('restclient');
        define('CURRENT_VERSION_ORDER_API', $this->config->item('order', 'service'));
        define('CURRENT_VERSION_USER_API', $this->config->item('user', 'service'));
    }

    public function __destruct() {
        echo json_encode($this->response);
    }

    public function sendDelayCard(){
    	$url = CURRENT_VERSION_ORDER_API . '/v1'.'/order' .'/orderInfo';
    	$service_request = http_build_query($this->input->get_post());
        $result = $this->restclient->post($url,$service_request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true); 
        $code_first = substr($code, 0, 1);
        $gateway_response = array();
        if ($code_first == 2) {
            $order_info = $service_response['order_info'];
        }else{
        	$gateway_response['code']    = '300';
            $gateway_response['msg']    = $service_response['msg']?$service_response['msg']:'订单错误';
            $this->response = $gateway_response;
            exit;
        }
        if(empty($order_info) || empty($order_info['uid'])){
            $gateway_response['code']    = '300';
            $gateway_response['msg']    = '订单错误或无帐号信息';
            $this->response = $gateway_response;
            exit;
        }
        $uid = $order_info['uid'];
        $url = CURRENT_VERSION_USER_API . '/v1'.'/card' .'/sendDelayCard';
        $service_request = http_build_query(array_merge($this->input->get_post(),array('uid'=>$uid)));
        $result = $this->restclient->post($url,$service_request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true); 
        $code_first = substr($code, 0, 1);
        if ($code_first == 2) {
            $gateway_response['code'] = 200;
            $gateway_response['msg'] = $service_response['msg']?$service_response['msg']:'发放成功';  
        }else{
        	$gateway_response['code']    = '300';
            $gateway_response['msg']    = $service_response['msg']?$service_response['msg']:'发放失败';
            $this->response = $gateway_response;
            exit;
        }
        $this->response = $gateway_response;
    }
}