<?php
class Delivery extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->config('service');
        $this->load->helper('http');
        $this->load->library('restclient');
        define('CURRENT_VERSION_BANNER_API', $this->config->item('banner', 'service'));
    }

    public function __destruct() {
        echo json_encode($this->response);
    }

    public function deliveryCapacity(){
        $url = CURRENT_VERSION_BANNER_API . '/v1'.'/warehouse' .'/deliveryCapacity';
        $service_request = http_build_query($this->input->get_post());
        $result = $this->restclient->post($url,$service_request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true); 
        $code_first = substr($code, 0, 1);
        $gateway_response = array();
        if ($code_first == 5 || !$service_response) {
            exit;
            // $log_tag = 'ERROR';
            // $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        }elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']    = '300';
            $gateway_response['msg']    = $service_response['msg'];
            $this->response = $gateway_response;
            exit;
        }elseif ($code_first == 2 && $service_response) {
        	$gateway_response['code'] = 200;
            $gateway_response['data'] = $service_response;  
        }
        $this->response = $gateway_response;
    }

}
