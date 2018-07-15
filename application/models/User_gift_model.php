<?php
class User_gift_model extends CI_Model {
    public function __construct() {
        parent::__construct();
        $this->load->config('service');
        $this->load->library('restclient');
        $this->load->helper('public');
        defined('CURRENT_VERSION_USER_API') or define('CURRENT_VERSION_USER_API', $this->config->item('user', 'service') . '/v1/user');
        defined('CURRENT_VERSION_PRODUCT_API') or define('CURRENT_VERSION_PRODUCT_API', $this->config->item('product', 'service'));
    }

    public function data_format($gift_list){
    	$product_ids = array();
        foreach ($gift_list as $value) {
        	$product_ids[] = $value['product_id'];
        }
        $url = CURRENT_VERSION_PRODUCT_API . '/v2' . '/product/productBaseInfo';
        $request = http_build_query(array('product_id' => $product_ids));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $code_first = substr($code, 0, 1);
        $products = array();
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $product_list = $service_response;
            foreach ($product_list as $key => $value) {
            	$products[$value['id']] = $value;
            }
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }
        foreach ($gift_list as &$gift_one) {
        	$gift_one['product_id'] and $gift_one['product'] = $products[$gift_one['product_id']];
        }
        return $gift_list;
    }
}