<?php
class User extends CI_Controller {
	private $source, $version, $response;
	
	public function __construct() {
		parent::__construct();
		$this->load->config('service');
		$this->load->library('restclient');
		$this->load->helper('public');
		$this->load->helper('output');
		$this->request_id =  uniqid('CRM_',true);//用于记录日志用
		define('CURRENT_VERSION_USER_API', $this->config->item('user', 'service'));
		define('CURRENT_VERSION_ORDER_API', $this->config->item('order', 'service'));
		$this->source = $this->input->get_post('source');
		$this->version = $this->input->get_post('version');
	}
	
	public function __destruct() {
		if($this->response['code'] != '200'){
			//$this->rollback();
		}
		crm_output($this->response);
	}


	public function delayGiftCard(){
		$query['card_number'] = $this->input->get_post('card_number');
        $query['to_date'] = $this->input->get_post('to_date');
        $url = CURRENT_VERSION_USER_API . '/v1/gift_cards/' .'cardInfo' ;
        $service_request = http_build_query($query);
		$result = $this->restclient->post($url, $service_request);
		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true);
		$code_first = substr($code, 0, 1);
		if ($code_first == 5 || !$service_response) {
			exit;
		}elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= $service_response['msg'];
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2 && $service_response) {

		}
        $url = CURRENT_VERSION_USER_API . '/v1/gift_cards/' .'delayGiftCard' ;
        $service_request = http_build_query($query);
		$result = $this->restclient->post($url, $service_request);
		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true);
		$code_first = substr($code, 0, 1);
		if ($code_first == 5 || !$service_response) {
			exit;
		}elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= $service_response['msg'];
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2 && $service_response) {
			$gateway_response['code'] = '200';
			$gateway_response['msg'] = '延期成功';
			$gateway_response['data'] = array();
			$this->response = $gateway_response;
		}
	}

	public function returnGift(){
		$user_gift_id = $this->input->get_post('freeProdId');
		$order_name = $this->input->get_post('orderNo');
        
        $query = array();
        $query['order_name'] = $order_name;
        $query['request_id'] = $this->request_id;
        $url = CURRENT_VERSION_ORDER_API . '/v1/' .'order/orderInfo';
        $service_request = http_build_query($query);
		$result = $this->restclient->post($url,$service_request);
		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true); 
		$code_first = substr($code, 0, 1);
		$order_id = 0;
		if ($code_first == 5 || !$service_response) {
			exit;
		}elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= $service_response['msg'];
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2 && $service_response) {
			$order_id	= $service_response['order_info']['order_id'];
		}

		$query = array();
        $query['user_gift_id'] = $user_gift_id;
        $query['order_id'] = $order_id;
        $query['request_id'] = $this->request_id;
        $url = CURRENT_VERSION_USER_API . '/v1/' .'user_gift/return_gift';
        $service_request = http_build_query($query);
		$result = $this->restclient->post($url,$service_request);
		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true); 
		$code_first = substr($code, 0, 1);
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

	public function userRechargeList(){
		$mobile = $this->input->get_post('mobile');
		$query = array();
        $query['mobile'] = $mobile;
        $query['request_id'] = $this->request_id;
        $url = CURRENT_VERSION_USER_API . '/v1/' .'user/getByMobile';
        $service_request = http_build_query($query);
		$result = $this->restclient->post($url,$service_request);
		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true); 
		$code_first = substr($code, 0, 1);
		$order_id = 0;
		if ($code_first == 5 || !$service_response) {
			exit;
		}elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= $service_response['msg'];
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2 && $service_response) {
			$user_info	= $service_response['user'];
		}
		$customerId = $user_info['id'];
		$query = array();
        $query['uid'] = $customerId;
        $filter = array();
        $filter['time>='] = date('Y-01-01');
        $filter['type'] = 'income';
        $filter['status'] = '已充值';
        $filter['payment !='] = '天天果园充值卡';
        $query['filter'] = $filter;
        $query['request_id'] = $this->request_id;
        $url = CURRENT_VERSION_USER_API . '/v1/' .'trade/tradeList';
        $service_request = http_build_query($query);
		$result = $this->restclient->post($url,$service_request);
		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true); 
		$code_first = substr($code, 0, 1);
		$order_id = 0;
		if ($code_first == 5 || !$service_response) {
			exit;
		}elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= $service_response['msg'];
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2 && $service_response) {
			$gateway_response['code']	= '200';
			$trade_info	= $service_response['trades'];
			$result = array();
			foreach ($trade_info as $trade) {
				$trade_one = array();
				$trade_one['trade_number'] = $trade['trade_number'];
				$trade_one['money'] = $trade['money'];
				$trade_one['has_invoice'] = $trade['invoice']?1:0;
				$trade_one['uid'] = $trade['uid'];
				$result[] = $trade_one;
			}
			$gateway_response['data'] = $result;
			$gateway_response['msg']  = '';
			$this->response = $gateway_response;
		}
	}

	public function addTradeInvoice(){
        $query = array();
        $query['request_id'] = $this->request_id;
        $url = CURRENT_VERSION_USER_API . '/v1/' .'invoice/addTradeInovice';
        $service_request = http_build_query($this->input->get_post());
		$result = $this->restclient->post($url,$service_request);
		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true); 
		$code_first = substr($code, 0, 1);
		$order_id = 0;
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
		}
	}

	public function userAddressList(){
		$mobile = $this->input->get_post('mobile');
		$query = array();
        $query['mobile'] = $mobile;
        $query['request_id'] = $this->request_id;
        $url = CURRENT_VERSION_USER_API . '/v1/' .'user/getByMobile';
        $service_request = http_build_query($query);
		$result = $this->restclient->post($url,$service_request);
		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true); 
		$code_first = substr($code, 0, 1);
		$order_id = 0;
		if ($code_first == 5) {
			exit;
		}elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= $service_response['msg'];
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2) {
			$user_info	= $service_response['user'];
		}
		$customerId = $user_info['id'];
		$query = array();
        $query['request_id'] = $this->request_id;
        $query['uid'] = $customerId;
        $url = CURRENT_VERSION_USER_API . '/v1/' .'user/userAddress';
        $service_request = http_build_query($query);
		$result = $this->restclient->post($url,$service_request);
		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true); 
		$code_first = substr($code, 0, 1);
		$order_id = 0;
		if ($code_first == 5) {
			exit;
		}elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= $service_response['msg'];
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2) {
			$gateway_response['code']	= '200';
			$gateway_response['data']['address_list'] = $service_response;
			$gateway_response['data']['uid'] = $customerId;
			$this->response = $gateway_response;
		}
	}

    public function setAutoQa(){
        $data = $this->input->get_post('data');
        $url = CURRENT_VERSION_USER_API . '/v1/customerService/setAutoQa';
        $service_request = http_build_query(array('data' => $data));
        $result = $this->restclient->post($url,$service_request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $code_first = substr($code, 0, 1);
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

}