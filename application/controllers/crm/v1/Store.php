<?php
class Store extends CI_Controller {
    private $source, $version, $response;

    public function __construct() {
        parent::__construct();
        $this->load->config('service');
        $this->load->library('restclient');
        $this->load->helper('public');
        $this->load->helper('output');
        $this->request_id = uniqid('CRM_', true);//用于记录日志用
        define('CURRENT_VERSION_PRODUCT_API', $this->config->item('product', 'service'));
        $this->source = $this->input->get_post('source');
        $this->version = $this->input->get_post('version');
    }

    public function __destruct() {
        if ($this->response['code'] != '200') {
            //$this->rollback();
        }
        crm_output($this->response);
    }


    public function getStoreIdByCode(){
        $data = $this->input->get_post('codeGroup');
        $url = CURRENT_VERSION_PRODUCT_API . '/v1/store/getStoreIdByCode';
        $service_request = http_build_query(array('codeGroup' => $data));
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
            $gateway_response['msg']	= '';
            $gateway_response['data']	= $service_response;
            $this->response = $gateway_response;
            exit;
        }
    }
}