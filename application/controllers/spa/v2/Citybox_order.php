<?php
class Citybox_order extends CI_Controller {
    
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
     * @api {post} / 订单详情
     * @apiDescription 订单详情
     * @apiGroup spa/citybox
     * @apiName order.detail
     *
     * @apiParam    {String}    connect_id  登录Token
     * @apiParam    {String}    order_name  订单号
     * @apiParam    {String}    source      渠道;app、spa
     * @apiParam    {String}    version     接口版本号;例:5.7.0
     *
     * @apiSampleRequest /spa/v2?service=citybox_order.detail
     */
    public function detail() {
        $data = self::cityboxSystemParam();
        $data['timestamp']      = time();
        $data['order_name']     = require_request('order_name');
        $data['open_id']        = $this->active->encrypt((string)get_uid(), CITYBOX_CRYPT_SECRET);
        $data['sign']           = sign_citybox($data);
        
        $url = $this->base_url . '/api/openapi/get_order_detail';//@TODO
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
     * @api {post} / 订单历史
     * @apiDescription 订单历史列表
     * @apiGroup spa/citybox
     * @apiName order.history_list
     *
     * @apiParam    {String}    connect_id  登录Token
     * @apiParam    {String}    [page]      页码；初始 1
     * @apiParam    {String}    [page_size] 分页条数,默认是10条
     * @apiParam    {String}    status      订单状态 -1 全部订单 0未支付
     * @apiParam    {String}    source      渠道;app、spa
     * @apiParam    {String}    version     接口版本号;例:5.7.0
     *
     * @apiSampleRequest /spa/v2?service=citybox_order.history_list
     */
    public function history_list() {
        $data = self::cityboxSystemParam();
        $data['timestamp']      = time();
        $data['open_id']        = $this->active->encrypt((string)get_uid(), CITYBOX_CRYPT_SECRET);
        $data['page']           = $this->input->get_post('page');
        $data['page_size']      = $this->input->get_post('page_size');
        $data['status']         = require_request('status');
        $data['sign']           = sign_citybox($data);
        
        $url = $this->base_url . '/api/openapi/list_order';//@TODO
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
     * @api {post} / 申请退款
     * @apiDescription 申请退款
     * @apiGroup spa/citybox
     * @apiName order.refund
     *
     * @apiParam    {String}    connect_id      登录Token
     * @apiParam    {String}    order_name      订单号
     * @apiParam    {String}    production_info 退款商品与数量;商品id:商品数量，多个商品使用分号分隔，例: "1:1;1000:2"
     * @apiParam    {Number}    reason          退款类型:1:订单结算错误 2：商品质量问题
     * @apiParam    {String}    reason_detail   退款原因
     * @apiParam    {String}    refund_money    退款金额
     * @apiParam    {String}    source          渠道;app、spa
     * @apiParam    {String}    version         接口版本号;例:5.7.0
     *
     * @apiSampleRequest /spa/v2?service=citybox_order.refund
     */
    public function refund() {
        $data = self::cityboxSystemParam();
        $data['timestamp']      = time();
        $data['order_name']     = require_request('order_name');
        $data['open_id']        = $this->active->encrypt((string)get_uid(), CITYBOX_CRYPT_SECRET);
        $data['reason']         = require_request('reason');
        $data['reason_detail']  = require_request('reason_detail');
        $data['refund_money']   = require_request('refund_money');
        $data['product_info']   = require_request('production_info');
        $data['sign']           = sign_citybox($data);
        
        $url = $this->base_url . '/api/openapi/refund_order';//@TODO
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
