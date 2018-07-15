<?php
define('NIRVANA_SECRET_KEY', 'caa21c26dfc990c7a534425ec87a111c');//@TODO
define('NEW_PAY_KEY', 'afsvq2mqwc7j0i69uzvukqexrzd1jq6h');
class Pay extends CI_Controller {
    private $secret_cityboxapi = '48eU7IeTJ6zKKDd1';//从citybox/v1/user 中直接取出
	public function __construct() {
		parent::__construct();
		$this->load->config('service');
		define("NIRVANA_URL", $this->config->item('nirvana', 'service') . '/api');//@TODO
		define("NIRVANA2_URL", $this->config->item('nirvana2', 'service') . '/api');//@TODO

		$this->load->library('api_process');
		$this->load->library('fruit_log');
		$this->load->helper('public');
		$this->request_id = date('Y_m_d_H_i_s') . '_' . md5($this->input->get_post('device_id') . mt_rand(10000, 99999));//用于记录日志用

		$this->source = 'wap';
		$this->version = '5.5.0';//@TODO
		$this->connect_id = require_request('connect_id');
		$this->log_uid = $this->input->get_post('log_uid');
// 		$this->log_uid = require_request('log_uid');//spa中有个接口是给wap_v3调用,wap_v3没有log_uid
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
	 * @api {get} /		初始化支付
	 * @apiDescription	初始化支付并获取相关支付信息
	 * @apiGroup		spa/pay
	 * @apiName			init
	 *
	 * @apiParam {Number} log_uid			用户最近一次登录的ID
	 * @apiParam {String} connect_id		登录成功后返回的connect_id
	 * @apiParam {String} order_name		订单编号
	 * @apiParam {String} [browser_type]	浏览器类型;wechat;alipay;
	 *
	 * @apiSampleRequest /spa/v2?service=pay.init
	 */
	public function init() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['source']		= $this->source;//@TODO
		$query['channel']		= $this->input->get('browser_type');//暂时只有微信浏览器需要这个参数
		$query['connect_id']	= $this->connect_id;
		$query['uid']			= $this->log_uid;
		$query['order_name']	= require_request('order_name');
		$query['service']		= 'paydesk.init';
		$query['timestamp']		= time();
		$query['version']		= $this->version;
		$query['sign']			= self::Sign($query);
		
		$params['url']		= NIRVANA2_URL;
		$params['data']		= $query;
		$params['method']	= 'post';
		$this->response = $this->api_process->process($params);
		$result_arr = & $this->response;
		if (! (isset($result_arr['data']['pro_card_money']) && $result_arr['data']['pro_card_money'] > 0)) {//提货券的情况下,不用合并
			$result_arr['data']['payments']['online']['pays'] = array_merge($result_arr['data']['payments']['online']['pays'], $result_arr['data']['payments']['bank']['pays']);
		}
		unset($result_arr['data']['payments']['bank']['pays']);
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {get} /		切换支付通道
	 * @apiDescription	切换第三方支付通道
	 * @apiGroup		spa/pay
	 * @apiName			switch_channel
	 *
	 * @apiParam {Number} log_uid			用户最近一次登录的ID
	 * @apiParam {String} connect_id		登录成功后返回的connect_id
	 * @apiParam {String} order_name		订单编号
	 * @apiParam {String} pay_id			pay->init 接口中返回的 payments->online->pays中的 pay_id 节点;
	 * @apiParam {String} pay_parent_id		pay->init 接口中返回的 payments->online->pays中的 pay_parent_id 节点;
	 *
	 * @apiSampleRequest /spa/v2?service=pay.switch_channel
	 */
	public function switch_channel() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['source']		= $this->source;
		$query['connect_id']	= $this->connect_id;
		$query['uid']			= $this->log_uid;
		$query['order_name']	= require_request('order_name');
		$query['service']		= 'paydesk.choseCostPayment';
		$query['pay_id']		= require_request('pay_id');
		$query['pay_parent_id']	= require_request('pay_parent_id');
		$query['timestamp']		= time();
		$query['version']		= $this->version;
		$query['sign']			= self::Sign($query);

		$params['url']		= NIRVANA2_URL;
		$params['data']		= $query;
		$params['method']	= 'post';
		$this->response = $this->api_process->process($params);
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api {get} /		是否启用余额付
	 * @apiDescription	启用/取消余额抵扣
	 * @apiGroup		spa/pay
	 * @apiName			balance_deduction
	 *
	 * @apiParam {Number} log_uid		用户最近一次登录的ID
	 * @apiParam {String} connect_id	登录成功后返回的connect_id
	 * @apiParam {String} order_name	订单号
	 * @apiParam {String} use_flag		是否使用余额抵扣标识; 1.使用余额抵扣; 0.取消使用余额抵扣
	 *
	 * @apiSampleRequest /spa/v2?service=pay.balance_deduction
	 */
	public function balance_deduction() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['source']		= $this->source;
		$query['connect_id']	= $this->connect_id;
		$query['uid']			= $this->log_uid;
		$use_flag = htmlspecialchars($this->input->get('use_flag'));
		$query['order_name']	= require_request('order_name');
		$query['service']		= ($use_flag == 1) ? 'paydesk.useBalance' : 'paydesk.cancelUseBalance';
		$query['timestamp']		= time();
		$query['version']		= $this->version;
		$query['sign']			= self::Sign($query);
		
		$params['url']		= NIRVANA2_URL;
		$params['data']		= $query;
		$params['method']	= 'post';
		$this->response = $this->api_process->process($params);
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	//正常余额支付成功
	//user.sendVerCode
	//paydesk.checkBalanceCode
	/**
	 * @api {get} /		余额支付提交
	 * @apiDescription	校验余额支付验证码并获取跳转地址
	 * @apiGroup		spa/pay
	 * @apiName			balance_pay
	 * 
	 * @apiParam {Number} log_uid			用户最近一次登录的ID
	 * @apiParam {String} connect_id		登录成功后返回的connect_id
	 * @apiParam {String} order_name		订单号
	 * @apiParam {String} need_online_pay	需要在线支付的金额,启用余额支付之后返回的参数
	 * @apiParam {String} money				订单总金额
	 * @apiParam {String} [mobile]			手机号
	 * @apiParam {String} [ver_code]		手机短信验证码
	 *
	 * @apiSampleRequest /spa/v2?service=pay.balance_pay
	 */
	public function balance_pay() {
		try {
			if (self::validateVerCode(array(), $returnParam) == false) throw new \Exception(json_encode($returnParam));
			if (self::check(array(), $returnParam) == false) throw new \Exception(json_encode($returnParam));
			if (self::balanceRealPay(array(), $returnParam) == false) throw new \Exception(json_encode($returnParam));
			exit(json_encode($returnParam));
			// 			exit(json_encode(array('code' => '200', 'data' => $get_jump_url)));
		} catch (\Exception $e) {
			exit($e->getMessage());
		}
	}

	/**
	 * @method 校验短信验证码
	 * @param array $param
	 * &@param array $returnParam
	 * @return bool
	 */
	private function validateVerCode($param = array(), &$returnParam = array()) {
		$return_bool = true;
		$mobile = htmlspecialchars($this->input->get('mobile'));
		
		if ($mobile != '') {
			$query['source']				= $this->source;
			$query['uid']					= $this->log_uid;
			$query['connect_id']			= $this->connect_id;
			$query['version']				= $this->version;
			$query['order_name']			= require_request('order_name');
			$query['ver_code_connect_id']	= require_request('connect_id');
			$query['verification_code']		= require_request('ver_code');
			$query['need_online_pay']		= require_request('need_online_pay');
			$query['timestamp']				= time();
			$query['service']				= 'paydesk.checkBalanceCode';
			$query['sign']					= self::Sign($query);

			$params['url']		= NIRVANA2_URL;
			$params['data']		= $query;
			$params['method']	= 'post';
			$response_arr = $this->api_process->process($params);
			$return_bool = $response_arr['code'] == '200' ? true : false;
			$returnParam = $response_arr;
		}
		return $return_bool;
	}

	/**
	 * @method 余额支付
	 * @param array $param
	 * &@param array $returnParam
	 * @return bool
	 */
	private function balanceRealPay($param = array(), &$returnParam = array()) {
		$this->load->library('spa/paycenter');
		$this->load->library('restclient');
		$this->paycenter->order_name	= htmlspecialchars($this->input->get('order_name'));
		// 		$this->paycenter->pay_id		= '0';
		$this->paycenter->pay_parent_id	= '5';//余额支付
		// 		$this->paycenter->money			= htmlspecialchars($this->input->get('need_online_pay');
		$this->paycenter->money			= htmlspecialchars($this->input->get('money'));//余额全额支付特殊处理
		$this->paycenter->web_url		= SPA_SITE_URL;//@TODO,本站地址
		$this->paycenter->set_parm();
		$get_str = http_build_query($this->paycenter->param);
		// 		return htmlspecialchars(PAY_API_URL . '?' . $get_str . '&sign=' . $this->paycenter->sign);
		// 		return PAY_API_URL . '?' . $get_str . '&sign=' . $this->paycenter->sign;
		$pay_url = $this->config->item('pay', 'service') . '/api/wap?' . $get_str . '&sign=' . $this->paycenter->sign;
		$result = $this->restclient->get($pay_url);
		$response_arr = json_decode($result->response, true);
		if ($response_arr['code'] == '200') {
			$redirect_url = SPA_SITE_URL . '/main/pay-success.html?order_name=' . htmlspecialchars($_GET['order_name']);
			if ($this->input->get('redirect_url') != '') $redirect_url = self::validateRedirectUrl(htmlspecialchars(base64_decode($this->input->get('redirect_url'))));
			$return_bool = true;
			$returnParam['code']	= '200';
			$returnParam['data']	= $redirect_url;
			$returnParam['msg']		= 'success';
		} else {
			$redirect_url = SPA_SITE_URL . '/main/pay-success.html';//@TODO
			$return_bool = false;
			$returnParam['code']	= '300';
			$returnParam['data']	= $redirect_url;
			$returnParam['msg']		= 'fail';
		}
		return $return_bool;
	}

	//成功付款统一回调地址
	public function success() {
		$order_name = explode('/', $_GET['order_name']);//过滤掉支付宝那边回退会多/1问题;
		$redirect_url = SPA_SITE_URL . '/main/pay-success.html?order_name=' . htmlspecialchars($order_name[0]);
		if ($this->input->get('redirect_url') != '') $redirect_url = self::validateRedirectUrl(htmlspecialchars(base64_decode($this->input->get('redirect_url'))));//@TODO,跳转URL参数待校验
		exit(header('Location: ' . $redirect_url));
	}

	//余额支付成功同步回调地址
	public function balance_success() {
		$redirect_url = SPA_SITE_URL . '/main/pay-success.html';//@TODO
		if ($this->input->get('redirect_url') != '') $redirect_url = self::validateRedirectUrl(htmlspecialchars(base64_decode($this->input->get('redirect_url'))));//@TODO,跳转URL参数待校验
		exit(header('Location: ' . $redirect_url));
	}

	//正常余额支付成功
	//paydesk.checkPay;order.orderInfo;
	//微信支付
	//paydesk.init;
	//paydesk.checkPay;
	//paydesk.orderSuccess;//支付成功后的同步回调
	//order.orderPayed;??
	/**
	* @method 检查支付信息
	*/
	private function check($param = array(), &$returnParam) {
		$query['source']			= $this->source;
		$query['connect_id']		= $this->connect_id;
		$query['uid']				= $this->log_uid;
		$query['order_name']		= require_request('order_name');
		$query['need_online_pay']	= require_request('need_online_pay');
		$query['service']			= 'paydesk.checkPay';
		$query['timestamp']			= time();
		$query['version']			= $this->version;
		$query['sign']				= self::Sign($query);
		
		$params['url']		= NIRVANA2_URL;
		$params['data']		= $query;
		$params['method']	= 'post';
		$response_arr	= $this->api_process->process($params);
		$return_bool = $response_arr['code'] == '200' ? true : false;
		$returnParam = $response_arr;
		return $return_bool;
	}

	/**
	 * @api {get} /		获取支付网关
	 * @apiDescription	生成并获取第三方支付网关地址
	 * @apiGroup		spa/pay
	 * @apiName			gateway_url
	 *
	 * @apiParam {Number} log_uid			用户最近一次登录的ID
	 * @apiParam {String} connect_id		登录成功后返回的connect_id
	 * @apiParam {String} order_name		订单编号
	 * @apiParam {String} need_online_pay	需要在线支付的金额
	 * @apiParam {String} pay_id			pay->init 接口中返回的 payments->online->pays中的 pay_id 节点;
	 * @apiParam {String} pay_parent_id		pay->init 接口中返回的 payments->online->pays中的 pay_parent_id 节点;
	 * @apiParam {String} [browser_type]	浏览器类型;"wechat"或者不传
	 *
	 * @apiSampleRequest /spa/v2?service=pay.gateway_url
	 */
	public function gateway_url() {
		if (self::check(array(), $returnParam)) {
			//@TODO,检查是否正确;
			$gate_url = self::gatewayUrl();
			//非P订单的走特殊流程
			if (( !stristr($this->input->get('order_name'), 'P') || $this->input->get('browser_type') == 'wechat') && $this->input->get('pay_parent_id') == 7) {
				$response['code'] = '200';
				$response['data'] = $gate_url;
			} else {
				$this->load->library('restclient');
				$result = $this->restclient->get($gate_url);
				$result_arr = json_decode($result->response, true);
				$response['code'] = $result_arr['code'];
				$response['data'] = $result_arr['msg'];
			}
			exit(json_encode($response));
		} else {
			exit(json_encode($returnParam));
		}
	}

	/**
	 * @method 获取网关支付地址
	 */
	private function gatewayUrl() {
		$this->load->library('spa/paycenter');
		$this->paycenter->order_name	= htmlspecialchars($this->input->get('order_name'));
		$this->paycenter->pay_id		= htmlspecialchars($this->input->get('pay_id'));
		$this->paycenter->pay_parent_id	= htmlspecialchars($this->input->get('pay_parent_id'));
		$this->paycenter->money			= htmlspecialchars($this->input->get('need_online_pay'));
		$this->paycenter->browser_type	= htmlspecialchars($this->input->get('browser_type'));
		$this->paycenter->web_url		= SPA_SITE_URL;//@TODO,本站地址
		$this->paycenter->redirect_url	= $this->input->get('redirect_url');//支付成功后的重定向地址,前端过来是base64编码
		$this->paycenter->set_parm();
		$get_str = http_build_query($this->paycenter->param);
		return $this->config->item('pay', 'service') . '/api/wap?' . $get_str . '&sign=' . $this->paycenter->sign;
	}

	public function gateway_return_success() {
		//@TODO,修改订单状态为某个中间状态
		//wap.fruitday.com/main/pay-success.html
		$url = SPA_SITE_URL . '/main/pay-success.html';
		if ($this->input->get('redirect_url') != '') $url = self::validateRedirectUrl(htmlspecialchars(base64_decode($this->input->get('redirect_url'))));//@TODO,跳转URL参数待校验
		exit(header('Location: ' . $url));
	}

	/**
	 * @api {get} /		混合支付
	 * @apiDescription	混合支付(余额+第三方支付)
	 * @apiGroup		spa/pay
	 * @apiName			mix
	 *
	 * @apiParam {Number} log_uid			用户最近一次登录的ID
	 * @apiParam {String} connect_id		登录成功后返回的connect_id
	 * @apiParam {String} order_name		订单编号
	 * @apiParam {String} need_online_pay	需要在线支付的金额
	 * @apiParam {String} pay_id			pay->init 接口中返回的 payments->online->pays中的 pay_id 节点;
	 * @apiParam {String} pay_parent_id		pay->init 接口中返回的 payments->online->pays中的 pay_parent_id 节点;
	 * @apiParam {String} [mobile]			手机号
	 * @apiParam {String} [ver_code]		手机短信验证码
	 * @apiParam {String} [browser_type]	浏览器类型;"wechat"或者不传
	 *
	 * @apiSampleRequest /spa/v2?service=pay.mix
	 */
	public function mix() {
		try {
			if (self::check(array(), $returnParam) == false) throw new \Exception(json_encode($returnParam));
			if (self::validateVerCode(array(), $returnParam) == false) throw new \Exception(json_encode($returnParam));
			$gate_url = self::gatewayUrl();
			if ($this->input->get('browser_type') == 'wechat' && $this->input->get('pay_parent_id') == 7) {
				$response['code'] = '200';
				$response['data'] = $gate_url;
			} else {
				$this->load->library('restclient');
				$result = $this->restclient->get($gate_url);
				$response_arr = json_decode($result->response, true);
				$response_arr['data']	= $response_arr['msg'];
				unset($response_arr['msg']);
			}
			exit(json_encode($response_arr));
		} catch (\Exception $e) {
			exit(json_encode($returnParam));
		}
	}

	/**
	 * @method 校验重定向url地址
	 * @param string $url
	 * @return string
	 */
	private function validateRedirectUrl($url) {
		$parse_url = parse_url($url);
		$validated = stristr($parse_url['host'], 'fruitday.com');
		if ($validated == false) $url = SPA_SITE_URL;
		return $url;
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
	
	private function requireRequest($key) {
		if (isset($_REQUEST[$key]) && $_REQUEST[$key]) {
			return htmlspecialchars($_REQUEST[$key]);
		} else {
			$this->code = '300';
			$this->response = array('code' => '300', 'msg' => $key . ' is empty');
			exit(json_encode($this->response));
		}
	}
	
	/**
	 * @api {get} /	微信小程序送礼支付
	 * @apiDescription	微信小程序送礼支付
	 * @apiGroup		spa/pay
	 * @apiName			wechat_applet_pay
	 *
	 * @apiParam {String} connect_id   登录成功后返回的connect_id
	 * @apiParam {String} [is_citybox] 是否是盒子渠道;1:是; 0:否; 缺省:0;
	 * @apiParam {Number} log_uid      用户最近一次登录的ID
	 * @apiParam {String} openid       微信的openid
	 * @apiParam {String} order_name   订单号
	 * @apiParam {String} [terminal]   渠道？fine-wechat或者gift-wechat
	 *
	 * @apiSampleRequest /spa/v2?service=pay.wechat_applet_pay
	 */
	public function wechat_applet_pay() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$method = ($this->input->get_post('is_citybox') == 1) ? 'citybox_pay' : 'doPay';
		$url = $this->config->item('order', 'service') . '/v1/MiniProgramPay/' . $method . '?request_id=' . $this->request_id;
		//require_request('log_uid');//@TODO,校验必填参数用,wap_v3上需要调用该接口,暂时去掉必填参数
		$terminal = $this->input->get_post('terminal');
		if ($method == 'citybox_pay') {
		    $order_detail = $this->getOrderInfo(require_request('order_name'));
		    $query['money']   = $order_detail['money'];
		}
		
		$query['uid']         = get_uid();
		$query['openid']      = require_request('openid');
		$query['order_name']  = require_request('order_name');
		$query['terminal']    = ($terminal == 'fine-wechat') ? 'fine-wechat' : 'gift-wechat';
		
		$params['url'] = $url;
		$params['data']	= $query;
		$params['method'] = 'get';
		$service_response = $this->api_process->process($params);
		
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200) {
			$api_gateway_response['code']	= '200';
			$api_gateway_response['data']	= $service_response['data'];//@TODO
			$api_gateway_response['msg']	= $service_response['msg'];//@TODO
		} else {
			$api_gateway_response['code']	= '300';
			$api_gateway_response['msg']	= $service_response['msg'];
		}
		$this->response = $api_gateway_response;
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api {post}   / 小程序支付-免密签约查询
	 * @apiDescription  小程序免密签约查询
	 * @apiGroup    spa/pay
	 * @apiName     wechat_free_secret_query
	 *
	 * @apiParam   {String}    connect_id  登录成功后返回的connect_id
	 * @apiParam   {String}    terminal    渠道
	 * @apiParam   {String}    openid      微信的OPENID
	 *
	 * @apiSampleRequest /spa/v2?service=pay.wechat_free_secret_query
	 */
	public function wechat_free_secret_query() {
	    get_uid();
	    $pay_service = $this->config->item('pay', 'service');
	    $url = $pay_service . '/wechat/doQuery';
	    $query['terminal']= require_post('terminal') . '-wechat';
	    $query['openid']  = require_post('openid');
	    ksort($query);
	    $md5_sign = md5(http_build_query($query) . NEW_PAY_KEY);
	    $query['sign']    = $md5_sign;
	    $params['url'] = $url;
	    $params['data']	= $query;
	    $params['method'] = 'get';
	    $response_arr = $this->api_process->process($params);
	    $response['code']   = $response_arr['code'];
	    $response['msg']    = $response_arr['msg'];
	    if (isset($response_arr['data'])) $response['data']   = $response_arr['data'];
	    $this->response = $response;
	}
	
	/**
	 * @api {post}  /   小程序支付-免密签约
	 * @apiDescription 小程序免密签约
	 * @apiGroup       spa/pay
	 * @apiName        wechat_free_secret_sign
	 *
	 * @apiParam   {String}    connect_id  登录成功后返回的connect_id
	 * @apiParam   {String}    terminal    渠道
	 * @apiParam   {String}    nickname    微信昵称
	 * @apiParam   {String}    openid      微信的OPENID
	 *
	 * @apiSampleRequest /spa/v2?service=pay.wechat_free_secret_sign
	 */
	public function wechat_free_secret_sign() {
	    get_uid();
	    $pay_service = $this->config->item('pay', 'service');
	    $url = $pay_service . '/wechat/doSign';
	    $query['terminal']   = require_post('terminal') . '-wechat';
	    $query['nickname']   = require_post('nickname');
	    $query['openid']     = require_post('openid');
	    ksort($query);
	    $md5_sign = md5(http_build_query($query) . NEW_PAY_KEY);
	    $query['sign']    = $md5_sign;
	    $params['url'] = $url;
	    $params['data']	= $query;
	    $params['method'] = 'get';
	    $response_arr = $this->api_process->process($params);
	    $response['code']   = $response_arr['code'];
	    $response['msg']    = $response_arr['msg'];
	    if (isset($response_arr['extraData'])) $response['data']   = $response_arr['extraData'];
	    $this->response = $response;
	}
	
	private function create_sign_cbapi($params){
	    ksort($params);
	    $query = '';
	    foreach ($params as $k => $v) {
	        $query .= $k . '=' . $v . '&';
	    }
	    $sign = md5(substr(md5($query . $this->secret_cityboxapi), 0, -1) . 'w');
	    return $sign;
	}
	
	private function getOrderInfo($order_name){
	    $params = array(
	        'order_name'=> $order_name,
	    );
	    $sign = $this->create_sign_cbapi($params);
	    $headers = array("sign"=>$sign,"platform"=>"admin");
	    $url = $this->config->item('citybox', 'service') . '/api/fruitday/get_detail';
	    $service_request = http_build_query($params);
	    $result = $this->restclient->get($url,$service_request,$headers);
	    $code = $result->info->http_code;
	    $service_response = json_decode($result->response, true);
	    return $service_response;
	}
}