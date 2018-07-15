<?php
class Sns extends CI_Controller {
    private $response;

    public function __construct() {
        parent::__construct();
        $this->load->config('service');
        $this->load->library('restclient');
        $this->load->helper('public');
        $this->load->library('fruit_log');
        $this->load->library('api_process');
        $this->request_id = date('Y_m_d_H_i_s') . '_' . md5($this->input->get_post('device_id') . mt_rand(10000, 99999));//用于记录日志用
    }

    public function __destruct() {
        echo json_encode($this->response);
        if (!function_exists("fastcgi_finish_request")) {
            function fastcgi_finish_request() {
            }//为windows兼容
        }
        fastcgi_finish_request();
        $this->fruit_log->save();
    }

    private function doSnsServer($url, $method='GET', $parameters=[], $options = [])
    {
        $this->restclient->set_option('base_url', $this->config->item('sns', 'service'));
        $this->restclient->set_option('curl_options', $options);
        $uid = $this->input->get_post('connect_id') ? (int)get_uid($this->input->get_post('connect_id')) : 0;//解决有可能不传connect_id导致的BUG
        $data = $this->restclient->execute($url, $method, array_merge($this->input->get(), $this->input->post(), ['uid' => $uid], $parameters));
        $response = [];
        if($data->response){
            $response = json_decode($data->response, true);
            if(json_last_error() !== JSON_ERROR_NONE){
                $response = $data->response;
            }
        }

        if ($data->info->http_code != 200) {
            if(is_array($response) && !empty($response['code'])){
                $this->response = ['code' => $response['code'], 'msg' => $response['msg'] ?: '服务器异常,请重试!', 'data' => []];
            }else{
                $this->response = ['code' => 300, 'msg' => $response ?: '服务器异常,请重试!', 'data' => []];
            }
            return;
        }
        if(is_array($response)){
            $this->response = ['code' => 200, 'data' => $response, 'msg' => ''];
        }else{
            $this->response = ['code' => 200, 'data' => [], 'msg' => $response];
        }

    }

    public function getDetail()
    {
        $this->doSnsServer("api/", 'POST', array('service'=>'article.getDetail'));
    }
}