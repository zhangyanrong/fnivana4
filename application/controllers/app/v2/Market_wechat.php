<?php
class Market_wechat extends CI_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->config('service');
        $this->load->library('api_process');//@TODO
        $this->load->library('fruit_log');
        $this->load->helper('public');
        $this->request_id = date('Y_m_d_H_i_s') . '_' . md5($this->input->get_post('device_id') . mt_rand(10000, 99999));//用于记录日志用
        
        $this->source = $this->input->get_post('source');
        $this->version = $this->input->get_post('version');
        $this->url = $this->config->item('user', 'service');//@TODO,用来请求的底层接口的URL地址
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
     * @api {post} / 获取微信基本信息
     * @apiDescription 获取微信基本信息
     * @apiGroup market_wechat
     * @apiName detail
     *
     * @apiParam {String} unionid       微信用户的unionid
     * 
     * @apiSampleRequest /app/v2?service=market_wechat.detail&source=app
     */
    public function detail() {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $url = $this->url . '/v1/market_wechat/detail?request_id=' . $this->request_id;
        $query['unionid']       = require_request('unionid');
        $params['url'] = $url;
        $params['data']	= $query;
        $params['method'] = 'get';
        $service_response = $this->api_process->process($params);
        $this->response = & $service_response;
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }
    
    /**
     * @api {post} / 记录微信基本信息
     * @apiDescription 记录微信基本信息
     * @apiGroup market_wechat
     * @apiName record_detail
     *
     * @apiParam {String} unionid       微信用户的unionid
     * @apiParam {String} headimgurl    微信用户的头像
     * @apiParam {String} nickname      微信用户的昵称
     * @apiParam {String} sex           微信用户的性别(0:未知; 1:男性； 2:女性)
     * 
     * @apiSampleRequest /app/v2?service=market_wechat.record_detail&source=app
     */
    public function record_detail() {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $url = $this->url . '/v1/market_wechat/record_detail?request_id=' . $this->request_id;
        $query['unionid']       = require_request('unionid');
        $query['headimgurl']    = require_request('headimgurl');
        $query['nickname']      = require_request('nickname');
        $query['sex']           = require_request('sex');
        $params['url'] = $url;
        $params['data']	= $query;
        $params['method'] = 'get';
        $service_response = $this->api_process->process($params);
        $this->response = & $service_response;
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }
}
?>