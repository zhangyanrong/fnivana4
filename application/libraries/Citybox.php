<?php
class Citybox {
    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->library('api_process');
        $this->CI->load->library('fruit_log');
        $this->CI->load->helper('public');
        $this->CI->load->library('active');
        $this->CI->load->config('service');
        $this->base_url = $this->CI->config->item('citybox', 'service');
    }
    
    public function access_token() {
        $this->CI->config->load('memcached');
        $mem_host = $this->CI->config->item('hostname', 'nirvana3');
        $mem_port = $this->CI->config->item('port', 'nirvana3');
        $mem = new Memcached();
        $mem->addServer($mem_host, $mem_port);
        
        $key = 'CITYBOX_ACCESS_TOKEN';
        $citybox_access_token = $mem->get($key);
        
        if (!$citybox_access_token) {
            $data['app_id']         = CITYBOX_APP_ID;
            $data['timestamp']      = time();
            $data['sign']           = sign_citybox($data);
            
            $url = $this->base_url . '/api/openapi/get_access_token';//@TODO
            $params['url']      = $url;
            $params['data']     = & $data;
            $params['method']   = 'post';
            $service_response = $this->CI->api_process->process($params);
            $citybox_access_token = $service_response['body']['access_token'];
            
            $mem->set($key, $citybox_access_token, 600);
        }
        return $citybox_access_token;
    }
}