<?php
class Citybox_user extends CI_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->config('service');
        $this->load->library('api_process');
        $this->load->library('fruit_log');
        $this->load->helper('public');
        $this->load->library('active');
        $this->base_url = $this->config->item('citybox', 'service');
        $this->request_id = date('Y_m_d_H_i_s') . '_' . md5($this->input->get_post('device_id') . mt_rand(10000, 99999));//用于记录日志用
    }
    
    public function __destruct() {
        if (isset($this->response) && $this->response) echo json_encode($this->response);
        if (!function_exists("fastcgi_finish_request")) {
            function fastcgi_finish_request() { }//为windows兼容
        }
        fastcgi_finish_request();
        $this->fruit_log->save();
    }
    
    /**
     * @method 获取citybox系统入参
     * @return array
     */
    private function cityboxSystemParam() {
        $this->load->library('citybox');
        $data['app_id']         = CITYBOX_APP_ID;
        $data['access_token']   = $this->citybox->access_token();//@TODO
        return $data;
    }
    
    /**
     * @api {post} / 用户最近使用设备
     * @apiDescription 用户最近使用的设备
     * @apiGroup spa/citybox
     * @apiName user.list_recent_device
     *
     * @apiParam    {String}    connect_id  登录Token
     * @apiParam    {String}    source      渠道;app、spa
     * @apiParam    {String}    version     接口版本号;例:5.7.0
     *
     * @apiSampleRequest /spa/v2?service=citybox_user.list_recent_device
     */
    public function list_recent_device() {
        $data = self::cityboxSystemParam();
        $data['timestamp']      = time();
        $data['open_id']        = $this->active->encrypt((string)get_uid(), CITYBOX_CRYPT_SECRET);
        $data['sign']           = sign_citybox($data);
        
        $url = $this->base_url . '/api/openapi/list_recent_device';//@TODO
        $params['url']      = $url;
        $params['data']     = & $data;
        $params['method']   = 'post';
        $service_response = $this->api_process->process($params);
        $response['code']   = $service_response['code'];
        if ($response['code'] == 200) $response['data']   = $service_response['body'];
        if (isset($service_response['body']['msg'])) $response['msg']    = $service_response['body']['msg'];
        $this->response = & $response;
    }
}
