<?php
class Citybox_device extends CI_Controller {
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
     * @api {post} / 获取设备营销活动
     * @apiDescription 获取设备营销活动
     * @apiGroup spa/citybox
     * @apiName device.activity_list
     *
     * @apiParam    {String}    connect_id  登录Token
     * @apiParam    {String}    device_id   设备ID号;staging测试用:00D051AC0005
     * @apiParam    {String}    source      渠道;app、spa
     * @apiParam    {String}    version     接口版本号;例:5.7.0
     *
     * @apiSampleRequest /spa/v2?service=citybox_device.activity_list
     */
    public function activity_list() {
        $data = self::cityboxSystemParam();
        $data['timestamp']      = time();
        $data['device_id']      = require_request('device_id');
        $data['open_id']        = $this->active->encrypt((string)get_uid(), CITYBOX_CRYPT_SECRET);
        $data['sign']           = sign_citybox($data);
        
        $url = $this->base_url . '/api/openapi/list_device_activity';//@TODO
        $params['url']      = $url;
        $params['data']     = & $data;
        $params['method']   = 'post';
        $service_response = $this->api_process->process($params);
        $response['code']   = $service_response['code'] == '400' ? '300' : $service_response['code'];
        if ($response['code'] == 200) $response['data']   = $service_response['body'];
        if (isset($service_response['body']['msg'])) $response['msg']    = $service_response['body']['msg'];
        $this->response = & $response;
    }
    
    /**
     * @api {post} / 获取关门广告
     * @apiDescription 获取关门广告
     * @apiGroup spa/citybox
     * @apiName device.close_ad
     *
     * @apiParam    {String}    open_log_id 设备开门接口返回的open_log_id
     * @apiParam    {String}    source      渠道;app、spa
     * @apiParam    {String}    version     接口版本号;例:5.7.0
     *
     * @apiSampleRequest /spa/v2?service=citybox_device.close_ad
     */
    public function close_ad() {
        $data = self::cityboxSystemParam();
        $data['timestamp']      = time();
        $data['open_log_id']    = require_request('open_log_id');
        $data['sign']           = sign_citybox($data);
        
        $url = $this->base_url . '/api/openapi/get_order_ad';//@TODO
        $params['url']      = $url;
        $params['data']     = & $data;
        $params['method']   = 'post';
        $service_response = $this->api_process->process($params);
        $response['code']   = $service_response['code'];
        if ($response['code'] == 200) $response['data']   = $service_response['body'];
        if (isset($service_response['body']['msg'])) $response['msg']    = $service_response['body']['msg'];
        $this->response = & $response;
    }
    
    /**
     * @api {post} / 设备开门
     * @apiDescription 设备开门
     * @apiGroup spa/citybox
     * @apiName device.open
     *
     * @apiParam    {String}    connect_id  登录Token
     * @apiParam    {String}    device_id   设备ID号;staging测试用:00D051AC0005
     * @apiParam    {String}    source      渠道;app、spa
     * @apiParam    {String}    version     接口版本号;例:5.7.0
     *
     * @apiSampleRequest /spa/v2?service=citybox_device.open
     */
    public function open() {
        $data = self::cityboxSystemParam();
        $data['timestamp']      = time();
        $data['device_id']      = require_request('device_id');
        $data['open_id']        = $this->active->encrypt((string)get_uid(), CITYBOX_CRYPT_SECRET);
        $data['open_source']    = 'fruitday_wechat';
        $data['sign']           = sign_citybox($data);
        
        $url = $this->base_url . '/api/openapi/open_device';//@TODO
        $params['url']      = $url;
        $params['data']     = & $data;
        $params['method']   = 'post';
        $service_response = $this->api_process->process($params);
        $response['code']   = $service_response['code'];
        if ($response['code'] == 200) $response['data']   = $service_response['body'];
        if (isset($service_response['body']['msg'])) $response['msg']    = $service_response['body']['msg'];
        $this->response = & $response;
    }
    
    /**
     * @api {post} / 获取设备状态
     * @apiDescription 获取设备状态
     * @apiGroup spa/citybox
     * @apiName device.status
     *
     * @apiParam    {String}    connect_id  登录Token
     * @apiParam    {String}    open_log_id 设备开门接口返回的open_log_id
     * @apiParam    {String}    source      渠道;app、spa
     * @apiParam    {String}    version     接口版本号;例:5.7.0
     *
     * @apiSampleRequest /spa/v2?service=citybox_device.status
     */
    public function status() {
        $data = self::cityboxSystemParam();
        $data['timestamp']      = time();
        $data['open_log_id']    = require_request('open_log_id');
        $data['open_id']        = $this->active->encrypt((string)get_uid(), CITYBOX_CRYPT_SECRET);
        $data['sign']           = sign_citybox($data);
        
        $url = $this->base_url . '/api/openapi/get_device_status';//@TODO
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
