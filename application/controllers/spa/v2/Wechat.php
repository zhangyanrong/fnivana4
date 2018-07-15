<?php
defined('NIRVANA_SECRET_KEY') OR define('NIRVANA_SECRET_KEY', 'caa21c26dfc990c7a534425ec87a111c');//@TODO
class Wechat extends CI_Controller {
	public function __construct() {
		parent::__construct();
		$this->load->config('service');
		$this->load->library('api_process');
		$this->load->library('restclient');
		$this->load->helper('public');
		$this->request_id = date('Y_m_d_H_i_s') . '_' . md5($this->input->get_post('device_id') . mt_rand(10000, 99999));//用于记录日志用
 		$this->source = 'wap';//@TODO
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
	 * @api	{get}	/	通过openid获取用户微信详情
	 * @apiDescription	通过openid获取用户微信详情
	 * @apiGroup	spa/wechat
	 * @apiName		detail_by_openid
	 * @apiParam {String} openid 微信的openid
	 * @apiSampleRequest /spa/v2?service=wechat.detail_by_openid
	 */
	public function detail_by_openid() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		
		$query['timestamp']	= time();
		$query['openid']	= require_request('openid');
		$query['source']	= 'wap';//@TODO
		$query['version']	= '5.8.0';
		$query['service']	= 'weixin.user-getWeixinUserInfoByOpenid';
		$query['sign']		= self::Sign($query);

		$params['url']			= $this->config->item('nirvana', 'service') . '/api';
		$params['data']			= $query;
		$params['method']		= 'post';
		$service_response	= $this->api_process->process($params);
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200) {
			$api_gateway_response['code']	= '200';
			$api_gateway_response['data']	= $service_response;
		} else {
			$api_gateway_response['code']	= '300';
			$api_gateway_response['msg']	= $service_response['msg'];
		}
		$this->response = $api_gateway_response;
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	public function index() {
		$detail = self::getDetail();
		if (!isset($detail) || !is_array($detail)) exit('请在微信中打开');
		//$_GET['opt'],  bind:普通新客绑定授权流程; card:微信卡券领券流程; user:微信菜单【我的账户】; pay_code：支付二维码; gift:送礼授权流程，此时，opt_code的值为经过base64编码过的活动页url，
		if ($_GET['opt'] == 'gift') {
			setcookie('wechat_nickname', $detail['nickname'], time() + 3600 * 24 * 30, '/', 'fruitday.com');
			setcookie('wechat_unionid', $detail['unionid'], time() + 3600 * 24 * 30, '/', 'fruitday.com');
			setcookie('wechat_headimgurl', $detail['headimgurl'], time() + 3600 * 24 * 30, '/', 'fruitday.com');
			$redirect_url = htmlspecialchars(base64_decode($_GET['opt_code']));
			exit(header('Location: ' . $redirect_url));
		}
	
		$is_bind = self::checkBind($detail);
		if ($is_bind) {
			self::wechatLogin($detail);
		} else {
			session_start();
			$_SESSION['wechat_detail']	= $detail;
			$_SESSION['opt']			= htmlspecialchars($_GET['opt']);
			$_SESSION['opt_code']		= htmlspecialchars($_GET['opt_code']);//统计用
			session_write_close();
			$redirect_url = SITE_URL . '/me/login.html?utm_souce=wechat_ofcl_acct';
			if ($_GET['opt'] == 'active') $redirect_url .= '&redirect_url=' . urlencode(htmlspecialchars(base64_decode($_GET['opt_code'])));
			exit(header('Location: ' . $redirect_url));
		}
	}
	
	/**
	 * @api	{get}	/	获取分享用参数
	 * @apiDescription	获取分享用参数
	 * @apiGroup       spa/wechat
	 * @apiName        share_param
	 * 
	 * @apiSampleRequest /spa/v2?service=wechat.share_param
	 */
	public function share_param() {
	    $data = array(
	        'appId' => WXAPP_APPID,
	        'timeStamp' => time(),
	        'nonceStr' => self::createNoncestr( 32 ),
	    );
	    ksort($data);
	    $signature = md5(http_build_query($data) . '&key=' . WXAPP_SECRET);
	    $data['signature']   = $signature;
	    
	    $this->response['code']    = '200';
	    $this->response['data']    = $data;
	}
	
	private function wechatLogin($detail) {
		$query['service']               = 'passport.wechat_login';
		$query['version']               = '5.2.0';
		$query['timestamp']             = time();
		$query['code']                  = require_request('code');
		$query['source']                = $this->source;
		$query['unionid'] = $detail['unionid'];
		$query['sign']                  = self::Sign($query);
		$params['url']                  = $this->config->item('api_gateway', 'service') . '/app/v2';
		$params['data']                 = $query;
		$params['method']               = 'post';
		$result = $this->api_process->process($params);
		$response_arr = &$result;
		if ($response_arr['code'] == '200') {
			setcookie('connect_id', $response_arr['data']['connect_id'], time() + 3600 * 24 * 30, '/', 'fruitday.com');
			$jump_url = SITE_URL;//@TODO,这个地方的跳转地址需要在对接的时候和周赢确认
			if ($this->input->get('opt') == 'user') $jump_url .= '/me/index.html';
			elseif ($this->input->get('opt') == 'pay_code') $jump_url .= '/me/member-scan.html';
			exit(header('Location: ' . $jump_url));
		} else {
			exit('login error');
		}
	}
	
	private function getDetail() {
		$query['service']		= 'user.getWechatUserInfo';
		$query['version']		= '5.2.0';
		$query['timestamp']		= time();
		$query['code']			= require_request('code');
		$query['source']		= $this->source;
		$query['sign']			= self::Sign($query);
		$params['url']			= $this->config->item('nirvana', 'service') . '/api';
		$params['data']			= $query;
		$params['method']		= 'post';
		$result = $this->api_process->process($params);
		return $result;
	}
	
	private function checkBind($param) {
		$query['service']		= 'user.isBind';
		$query['version']		= '5.2.0';
		$query['timestamp']		= time();
		$query['code']			= require_request('code');
		$params['url']			= $this->config->item('nirvana', 'service');
		$params['data']			= $query;
		$params['method']		= 'post';
		$result = $this->api_process->process($params);
		$response_arr = json_encode($result, true);
		return $response_arr;
	}
	
	private function Sign($data) {
		$api_secret_key = NIRVANA_SECRET_KEY;//@TODO
		$post_param = $data;
		ksort($post_param);
		$query = '';
		foreach($post_param as $k => $v) {
			if ($v === null) continue;
			$query .= $k . '=' . $v . '&';
		}
		$validate_sign1 = md5(substr(md5($query.$api_secret_key), 0,-1).'w');//@TODO
		// 		$validate_sign2 = md5(substr(md5($query.NIRVANA2_PRO_SECRET), 0,-1).'w');//@TODO
		return $validate_sign1;
	}
	
	private function createNoncestr( $length = 16 )
	{
	    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	    $str ="";
	    for ( $i = 0; $i < $length; $i++ )  {
	        $str.= substr($chars, mt_rand(0, strlen($chars)-1), 1);
	    }
	    return $str;
	}
}