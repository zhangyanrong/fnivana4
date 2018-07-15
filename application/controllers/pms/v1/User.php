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
		$this->request_id =  uniqid('PMS_',true);//用于记录日志用
		define('CURRENT_VERSION_USER_API', $this->config->item('user', 'service'));
		$this->source = $this->input->get_post('source');
		$this->version = $this->input->get_post('version');
	}

	public function __destruct() {
		// if($this->response['code'] != '200'){
		// 	$this->rollback();
		// }
		pms_output($this->response);
	}

	public function test(){
		$data = $this->decodeData();
		$gateway_response['code']	= '200';
		$gateway_response['msg']	= 'HELLO';
		$this->response = $gateway_response;
	}

	public function sendCard(){
		$data = $this->decodeData();
		$gateway_response = array();
		$url = CURRENT_VERSION_USER_API . '/v1' . '/card/sendPlatformCards';
		$request = http_build_query($data);
		$result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $code_first = substr($code, 0, 1);
        $service_response = json_decode($result->response, true);
        if ($code_first == 5) {
        	exit;
        } elseif ($code_first == 2) {
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = isset($service_response['msg']) ? $service_response['msg'] : '成功';
            $gateway_response['data'] = array();
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '400';
            $gateway_response['msg'] = isset($service_response['msg']) ? $service_response['msg'] : '失败';
            $gateway_response['data'] = array();
        }
        $this->response = $gateway_response;
	}

	public function countCards(){
		$data = $this->decodeData();
		$gateway_response = array();
		$url = CURRENT_VERSION_USER_API . '/v1' . '/card/platformCardCount';
		$request = http_build_query($data);
		$result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $code_first = substr($code, 0, 1);
        $service_response = json_decode($result->response, true);
        if ($code_first == 5) {
        	exit;
        } elseif ($code_first == 2) {
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = isset($service_response['msg']) ? $service_response['msg'] : '成功';
            $gateway_response['data'] = $service_response['data'];
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '400';
            $gateway_response['msg'] = isset($service_response['msg']) ? $service_response['msg'] : '失败';
            $gateway_response['data'] = array();
        }
        $this->response = $gateway_response;
	}

	public function batchCountCards(){
		$data = $this->decodeData();
		$gateway_response = array();
		$url = CURRENT_VERSION_USER_API . '/v1' . '/card/batchPlatformCardCount';
		$request = http_build_query($data);
		$result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $code_first = substr($code, 0, 1);
        $service_response = json_decode($result->response, true);
        if ($code_first == 5) {
        	exit;
        } elseif ($code_first == 2) {
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = isset($service_response['msg']) ? $service_response['msg'] : '成功';
            $gateway_response['data'] = $service_response['data'];
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '400';
            $gateway_response['msg'] = isset($service_response['msg']) ? $service_response['msg'] : '失败';
            $gateway_response['data'] = array();
        }
        $this->response = $gateway_response;
	}

	public function cardList(){
		$data = $this->decodeData();
		$gateway_response = array();
		$this->load->model('card_model');
		$url = CURRENT_VERSION_USER_API . '/v1' . '/card/platformCardList';
		$request = http_build_query($data);
		$result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $code_first = substr($code, 0, 1);
        $service_response = json_decode($result->response, true);
        $card_list = array();
        if ($code_first == 5) {
        	exit;
        } elseif ($code_first == 2) {
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = isset($service_response['msg']) ? $service_response['msg'] : '成功';
            $card_list = $service_response['data'];
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '400';
            $gateway_response['msg'] = isset($service_response['msg']) ? $service_response['msg'] : '失败';
            $gateway_response['data'] = array();
        }
        if($card_list){
        	$card_list = $this->card_model->data_format($card_list);
        }
        $gateway_response['data'] = $card_list;
        $this->response = $gateway_response;
	}

	public function getSentRight(){
		$data = $this->decodeData();
		$gateway_response = array();
		$url = CURRENT_VERSION_USER_API . '/v1' . '/card/platformSendCardRight';
		$request = http_build_query($data);
		$result = $this->restclient->post($url, $request);
		$code = $result->info->http_code;
		$code_first = substr($code, 0, 1);
		$service_response = json_decode($result->response, true);
		$card_list = array();
		if ($code_first == 5) {
			exit;
		} elseif ($code_first == 2) {
			$gateway_response['code'] = '200';
			$gateway_response['msg'] = isset($service_response['msg']) ? $service_response['msg'] : '成功';
			$gateway_response['data'] = $service_response['data'];
		} elseif ($code_first == 3 || $code_first == 4) {
			$gateway_response['code'] = '400';
			$gateway_response['msg'] = isset($service_response['msg']) ? $service_response['msg'] : '失败';
			$gateway_response['data'] = array();
		}
		$this->response = $gateway_response;
	}

	private function decodeData(){
		$data = $this->input->get_post('data');
		$data = str_replace(' ','+',trim($data));
		$data = $this->encrypt_aes->AesDecrypt($data);
		$data = json_decode($data,true);
		return $data;
	}

	private function rollback(){
        $rollback_url = CURRENT_VERSION_USER_API.'/'.'rollback' ;
        $request['request_id'] = $this->request_id;
        $service_request = http_build_query($request);
        $this->restclient->post($rollback_url,$service_request);
	}
}
