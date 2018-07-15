<?php
class Passport extends CI_Controller {
	public function __construct() {
		parent::__construct();
		$this->load->config('service');
		$this->load->library('api_process');
		$this->load->library('fruit_log');
		$this->load->helper('public_helper');
		$this->request_id = date('Y_m_d_H_i_s') . '_' . md5($this->input->get_post('device_id') . mt_rand(10000, 99999));//用于记录日志用
		$this->url = $this->config->item('user', 'service');
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
	 * @api              {get} /spa/v2/ 手机快捷登录
	 * @apiDescription   手机快捷登录
	 * @apiGroup         app/passport
	 * @apiName          mobile_vercode_login
	 *
	 * @apiParam   {String}    mobile          手机号
	 * @apiParam   {String}    [connect_id]    connect_id
	 * @apiParam   {String}    [register_verification_code] register_verification_code;验证码
	 * @apiParam   {String}    [city]          城市
	 * @apiParam   {String}    [country]       国家
	 * @apiParam   {String}    [headimgurl]    头像
	 * @apiParam   {String}    [language]      语言
	 * @apiParam   {String}    [nickname]      微信昵称
	 * @apiParam   {String}    [openid]        微信的openid
	 * @apiParam   {String}    [province]      省份
	 * @apiParam   {String}    [sex]           性别
	 * @apiParam   {String}    [terminal]      渠道?
	 * @apiParam   {String}    [unionid]       微信的unionid
	 *
	 * @apiSampleRequest /app/v2/?service=passport.mobile_vercode_login
	 **/
	public function mobile_vercode_login() {
	    $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
	    $url = $this->url . '/v1/user/mobileLogin';
	    $service_query['mobile']     = $this->input->get_post('mobile');
	    $service_query['connect_id'] = $this->input->get_post('connect_id');
	    $service_query['register_verification_code'] = $this->input->get_post('register_verification_code');
	    
	    $wechat_user_info['city']         = $this->input->get_post('city');
	    $wechat_user_info['country']      = $this->input->get_post('country');
	    $wechat_user_info['headimgurl']   = $this->input->get_post('headimgurl');
	    $wechat_user_info['language']     = $this->input->get_post('language');
	    $wechat_user_info['nickname']     = $this->input->get_post('nickname');
	    $wechat_user_info['openid']       = $this->input->get_post('openid');
	    $wechat_user_info['province']     = $this->input->get_post('province');
	    $wechat_user_info['sex']          = $this->input->get_post('sex');
	    $wechat_user_info['terminal']     = $this->input->get_post('terminal');
	    $wechat_user_info['unionid']      = $this->input->get_post('unionid');
	    $service_query['wechat_user_info']   = json_encode($wechat_user_info);
	    
	    $params['url']		= $url;
	    $params['data']		= $service_query;
	    $params['method']	= 'get';
	    $response_arr = $this->api_process->process($params);
	    $response_result = $this->api_process->get('result');
	    if ($response_result->info->http_code == 200) {
	        $this->response['code'] = '200';
	        $data['id'] = $response_arr['userinfo']['id'];
	        $this->load->library('Nirvana3session');
	        $this->nirvana3session->set_userdata($data);
	        $this->response['data']['connect_id']	= session_id();
	        $this->response['data']['userinfo']['id']	= $response_arr['userinfo']['id'];
	        
	        $wechat_param['uid']       = $response_arr['userinfo']['id'];;
	        $wechat_param['openid']    = $wechat_user_info['openid'];
	        self::syncWechatCard($wechat_param);
	        self::wechatInfoCollect($wechat_user_info);
	    } else {
	        $this->response['code'] = 300;
	        $this->response['msg']	= $response_arr;
	    }
	    $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {get} / 微信登录
	 * @apiDescription 微信unionid登录
	 * @apiGroup app/passport
	 * @apiName wechat_login
	 *
	 * @apiParam {String} unionid  微信的unionid
	 * @apiParam {String} openid   微信的openid
	 * @apiParam {String} terminal 渠道?
	 *
	 * @apiSampleRequest /app/v2?service=passport.wechat_login
	 */
	public function wechat_login() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = $this->url . '/v1/wechat/detail';
		$service_query['unionid']     = $this->input->get_post('unionid');
		$service_query['openid']      = $this->input->get_post('openid');
		$service_query['terminal']    = $this->input->get_post('terminal');
		$params['url']		= $url;
		$params['data']		= $service_query;
		$params['method']	= 'get';
		$response_arr = $this->api_process->process($params);
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200) {
			$this->response['code'] = '200';
			$data['id'] = $response_arr['data']['uid'];
			$this->load->library('Nirvana3session');
			$this->nirvana3session->set_userdata($data);
			$this->response['data']['connect_id']	= session_id();
			$this->response['data']['uid']			= $response_arr['data']['uid'];
			
			$wechat_param['uid']     = $response_arr['data']['uid'];
			$wechat_param['openid']  = $response_arr['data']['openid'];
			self::syncWechatCard($wechat_param);
			self::wechatInfoCollect($service_query);
		} else {
		    $this->response['code'] = $response_result->info->http_code;
			$this->response['msg']	= $response_arr['msg'];
		}
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {post} / 微信手机号强绑
	 * @apiDescription 微信unionid与手机号强绑接口
	 * @apiGroup app/passport
	 * @apiName wechat_force_bind
	 *
	 * @apiParam {String} mobile       微信手机号
	 * @apiParam {String} unionid      微信unionid
	 * @apiParam {String} openid       微信openid
	 * @apiParam {String} [nickname]   微信昵称
	 * @apiParam {String} [sex]        微信性别
	 * @apiParam {String} [headimgurl] 微信头像
	 * @apiParam {String} [terminal]   渠道?
	 *
	 * @apiSampleRequest /app/v2?service=passport.wechat_force_bind
	 */
	public function wechat_force_bind() {
	    $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
	    $url = $this->url . '/v1/wechat/force_bind';
	    $service_query['mobile']       = require_request('mobile');
	    $service_query['openid']       = require_request('openid');
	    $service_query['unionid']      = require_request('unionid');
	    $service_query['nickname']     = $this->input->get_post('nickname');
	    $service_query['sex']          = $this->input->get_post('sex');
	    $service_query['language']     = $this->input->get_post('language');
	    $service_query['city']         = $this->input->get_post('city');
	    $service_query['province']     = $this->input->get_post('province');
	    $service_query['country']      = $this->input->get_post('country');
	    $service_query['headimgurl']   = $this->input->get_post('headimgurl');
	    
	    $params['url']		= $url;
	    $params['data']		= $service_query;
	    $params['method']	= 'get';
	    $response_arr = $this->api_process->process($params);
	    $response_result = $this->api_process->get('result');
	    if ($response_arr['code'] == 200) {
	        self::wechat_login();//@TODO
	    } else {
	        $this->response['code'] = 300;
	        $this->response['msg']	= $response_arr['msg'];
	    }
	    $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {post} /   收集微信渠道信息用
	 * @apiDescription 收集微信渠道信息用
	 * @apiGroup app/passport
	 * @apiName wechat_info_collect
	 *
	 * @apiParam {String} unionid      微信unionid
	 * @apiParam {String} openid       微信openid
	 * @apiParam {String} terminal     渠道?
	 *
	 * @apiSampleRequest /app/v2?service=passport.wechat_info_collect
	 */
	public function wechat_info_collect() {
	    $param['unionid']      = require_request('unionid');
	    $param['openid']       = require_request('openid');
	    $param['terminal']     = require_request('terminal');
	    self::wechatInfoCollect($param);
	    $this->response['code']    = 200;
	    $this->response['msg']     = 'success';
	}
	
	/**
	 * @method 同步微信卡券
	 * @param array $param
	 * &@param array $returnParam
	 * @return bool
	 * 
	 * @example
	 * $param['uid']		//用户ID
	 * $param['openid']		//openid
	 */
	private function syncWechatCard($param, &$returnParam = array()) {
		try {
			if (!isset($param['uid'])) throw new \Exception('uid is empty');
			if (!isset($param['openid'])) throw new \Exception('openid is empty');
			$url = $this->url . '/v1/user/syncWechatCard';
			$params['url']		= $url;
			$params['data']		= $param;
			$params['method']	= 'get';
			$service_response = $this->api_process->process($params);//不用处理结果数据
			return true;			
		} catch (\Exception $e) {
			$returnParam['code']	= 300;
			$returnParam['msg']		= $e->getMessage();
			return false;
		}
	}
	
	/**
	 * @method 收集微信用户信息
	 * @param array $param
	 * &@param array $returnParam
	 * @return bool
	 */
	private function wechatInfoCollect($param, &$returnParam = array()) {
	    try {
	        if (!isset($param['unionid'])) throw new \Exception('unionid is empty');
	        if (!isset($param['openid'])) throw new \Exception('openid is empty');
	        if (!isset($param['terminal'])) throw new \Exception('terminal is empty');
	        $url = $this->url . '/v1/wechat/info_collect';
	        $params['url']		= $url;
	        $params['data']		= $param;
	        $params['method']	= 'get';
	        $service_response = $this->api_process->process($params);//不用处理结果数据
	        return true;
	    } catch (\Exception $e) {
	        $returnParam['code']	= 300;
	        $returnParam['msg']		= $e->getMessage();
	        return false;
	    }
	}
}