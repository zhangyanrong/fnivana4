<?php
class User extends CI_Controller {
	private $response;

	public function __construct() {
		parent::__construct();
		$this->load->config('service');
		$this->load->library('restclient');
		$this->load->helper('public');
		$this->load->library('fruit_log');
		$this->load->library('api_process');
		$this->source = 'app';
		$this->version = '5.9.0';
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

//     private function getUid(){
//         static $uid = null;
//         if(isset($uid)){
//             return $uid;
//         }
//         $connect_id = $this->input->get_post('connect_id');
//         $uid = 0;
//         if($connect_id){
//             session_id($connect_id);
//             session_start();
//             $uid = isset($_SESSION['user_detail']['id']) ? $_SESSION['user_detail']['id'] : 0;
//             session_write_close();
//         }
//         return (int)$uid;
//     }

    private function doUserServer($url, $method='GET', $parameters=[], $options = [])
    {
        $this->restclient->set_option('base_url', $this->config->item('user', 'service'));
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
    
    /**
     * @api {post} /v1/user/card_gift_alert 首页优惠券赠品提示
     * @apiDescription  首页优惠券赠品提示
     * @apiGroup    spa/user
     * @apiName     card_gift_alert
     * @apiParam    {String}    connect_id  登录Token
     * @apiSampleRequest /spa/v2/?service=user.card_gift_alert
     **/
    public function card_gift_alert(){
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $url = $this->config->item('user', 'service') . '/v1' . '/user/cardGiftAlert';
        $uid = get_uid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid, 'source' => $this->source)));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;
        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2) {
            if($service_response['card_list']){
                $this->load->model('card_model');
                $service_response['card_list'] = $this->card_model->data_format($service_response['card_list']);
                $cardlist = array();
                foreach ($service_response['card_list'] as $key => $card) {
                    $cardlist[$key]['card_money'] = $card['card_money'];
                    $cardlist[$key]['promotion_type'] = $card['promotion_type'];
                    $cardlist[$key]['use_range'] = $card['use_range'];
                    $cardlist[$key]['time'] = $card['time'];
                    $cardlist[$key]['to_date'] = $card['to_date'];
                    $cardlist[$key]['order_money_limit'] = $card['order_money_limit'];
                    $cardlist[$key]['product_id'] = $cardlist[$key]['card_product_id'] = $card['product_id'];
                }
                $service_response['card_list'] = array_values($cardlist);
            }
            if($service_response['gift_list']){
                $this->load->model('user_gift_model');
                $gift_list = array();
                $service_response['gift_list'] = $this->user_gift_model->data_format($service_response['gift_list']);
                foreach ($service_response['gift_list'] as $key => $value) {
                    $gift_list[$key]['user_gift_id'] = $value['user_gift_id'];
                    $gift_list[$key]['product_name'] = $value['product']['product_name'];
                    $gift_list[$key]['photo'] = $value['product']['photo']['big'];
                    $gift_list[$key]['gg_name'] = $value['product']['gg_name'];
                    $gift_list[$key]['qty'] = $value['qty'];
                }
                $service_response['gift_list'] = array_values($gift_list);
            }
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = $service_response;
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if($this->response == null){
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }
    
    /**
     * @api {get}   /spa/v2 卡券提示
     * @apiDescription  卡券领取提示？
     * @apiGroup        spa/user
     * @apiName         cardtips
     * @apiParam    {String}    connect_id      登录成功后返回的connect_id;
     *
     * @apiSampleRequest    /spa/v2?service=user.cardtips
     */
    public function cardtips(){
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        
        $url = $this->config->item('user', 'service') . '/v1' . '/card/cardtips';
        $uid = get_uid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;
        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if($this->response == null){
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }
    
    /**
     * @api {post} /v1/user/change_card_code 口令红包
     * @apiDescription  口令红包
     * @apiGroup        spa/user
     * @apiName         change_card_code
     * @apiParam    {String}    connect_id  登录Token
     * @apiParam    {String}    code        口令
     * @apiSampleRequest /spa/v2/?service=user.change_card_code
     **/
    public function change_card_code(){
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $url = $this->config->item('user', 'service') . '/v1' . '/card/changeCardCode';
        $uid = get_uid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;
        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2) {
            $gateway_response['data'] = array();
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = isset($service_response['msg']) ? $service_response['msg'] : '成功';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg']) ? $service_response['msg'] : '失败';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if($this->response == null){
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    /**
     * @api              {get} /spa/v2/ 有效积点明细
     * @apiDescription   有效积点明细
     * @apiGroup         spa/fuli
     * @apiName          pointDetail
     *
     * @apiParam {String} [connect_id] connect_id
     *
     * @apiSampleRequest /spa/v2/?service=user.fuliHome&source=app
     **/
    public function fuliHome(){
        $this->doUserServer(sprintf("v1/user_fuli/%s/", __FUNCTION__));
    }

    /**
     * @api              {get} /spa/v2/ 积点记录
     * @apiDescription   积点记录
     * @apiGroup         spa/fuli
     * @apiName          getPointTradeList
     *
     * @apiParam {String} [connect_id] connect_id
     *
     * @apiSampleRequest /spa/v2/?service=user.getPointTradeList&source=app
     **/
    public function getPointTradeList(){
        $this->doUserServer(sprintf("v1/user_fuli/%s/", __FUNCTION__));
    }

    public function getHot()
    {
        $this->doUserServer(sprintf("v1/CustomerService/%s/", __FUNCTION__));
    }

    public function getClassList()
    {
        $this->doUserServer(sprintf("v1/CustomerService/%s/", __FUNCTION__));
    }

    public function search()
    {
        $this->doUserServer(sprintf("v1/CustomerService/%s/", __FUNCTION__));
    }
    
    /**
     * @api {get} /spa/v2 赠品列表
     * @apiDescription	会员中心的赠品(礼品)列表;
     * @apiGroup spa/user
     * @apiName gift_list
     * @apiParam {String} connect_id	登录成功后返回的connect_id;
     * @apiParam {String} store_id_list 门店id
     * 
     * @apiSampleRequest /spa/v2?service=user.gift_list
     */
    public function gift_list() {
    	$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
    	$params['url']				= $this->config->item('product', 'service') . '/v2' . '/product/giftsGetNew';
    	$params['method']			= 'get';
    	$params['data']['uid']			= get_uid($this->input->get_post('connect_id'));
    	$params['data']['store_id_list']= $this->input->get_post('store_id_list');
    	$service_response  = $this->api_process->process($params);
    	foreach ($service_response as $k => $v) {
    		if (!is_array($v)) continue;
	    	foreach ($v as $kk => $vv ) {
	    		$service_response[$k][$kk]['start_time']  = str_replace(' +86', '', $vv['start_time']);
	    		$service_response[$k][$kk]['end_time']    = str_replace(' +86', '', $vv['end_time']);
	    	}
    	}
    	$gateway_response['code'] = '200';
    	$gateway_response['msg'] = '';
    	$gateway_response['data'] = $service_response;
    	$this->response = $gateway_response;
    	$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }
    
    /**
     * @api {post} / 会员二维码
     * @apiDescription 会员二维码
     * @apiGroup spa/user
     * @apiName qrcode
     *
     * @apiParam {String} connect_id 登录Token
     *
     * @apiSampleRequest /spa/v2?service=user.qrcode
     */
    public function qrcode(){
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        
        $params['url']      = $this->config->item('user', 'service') . '/v1/user/qrcode/' . get_uid();
        $params['method']   = 'get';
        $params['data']['request_id']   = $this->request_id;
        $service_response = $this->api_process->process($params);
        $response_result = $this->api_process->get('result');
        if ($response_result->info->http_code == 200) {
            $this->load->library('QRcode');
            ob_start();
            $this->qrcode->png($service_response['qr_str'], false, 'L', '10');
            $img = base64_encode(ob_get_contents());
            ob_end_clean();
            header('content-type:application/json;charset=utf8');
            $api_gateway_response['code']	= '200';
            $api_gateway_response['msg']    = 'success';
            $api_gateway_response['data']	= $img;
        } else {
            $api_gateway_response['code']   = '300';
            $api_gateway_response['msg']    = $service_cart_response['msg'];
        }
        $this->response = $api_gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }
    
    /**
     * @api {post} /spa/v2 会员登录
     * @apiDescription	常规账号密码登录
     * @apiGroup		spa/user
     * @apiName			signin
     * @apiParam {String} mobile	登录成功后返回的connect_id;
     * @apiParam {String} password	门店id
     *
     * @apiSampleRequest /spa/v2?service=user.signin
     */
    public function signin() {
    	$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
    	$query['mobile']	= require_request('mobile');
    	$query['password']	= md5(require_request('password'));//@TODO
    	$params['url']		= $this->config->item('user', 'service') . '/v1' . '/user/signin';
    	$params['method']	= 'get';
    	$params['data']		= $query;
    	$service_response  = $this->api_process->process($params);
    	$response_result = $this->api_process->get('result');
    	if ($response_result->info->http_code == 200) {
    		$api_gateway_response['code']	= '200';
    		$api_gateway_response['data']	= $service_response;
    	} else {
    		$api_gateway_response['code']	= '300';
    		$api_gateway_response['msg']	= $service_response;
    	}
    	$this->response = $api_gateway_response;
    	self::passportLogin();
    	$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }
    
    /**
     * @api	{get}	/spa/v2/	用户验证码
     * @apiDescription	用户验证码
     * @apiGroup		spa/user
     * @apiName			send_vercode
     *
     * @apiParam {String} mobile	手机号
     * @apiParam {String} use_case	使用场景; 登录场景:'mobileLogin'; 重置密码场景: 'reset'; 订单支付场景: 'order';
     *
     * @apiSampleRequest /spa/v2/?service=user.send_vercode
     **/
    public function send_vercode() {
    	$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
    	$query['mobile']	= require_request('mobile');
    	$query['use_case']	= require_request('use_case');
    	$params['url']		= $this->config->item('user', 'service') . '/v1' . '/user/sendVerCode';
    	$params['method']	= 'get';
    	$params['data']		= $query;
    	$service_response  = $this->api_process->process($params);
    	$response_result = $this->api_process->get('result');
    	if ($response_result->info->http_code == 200) {
    		$api_gateway_response['code']	= '200';
    		$api_gateway_response['msg']	= '发送成功';
//     		$api_gateway_response['data']	= $service_response;
    	} else {
    		$api_gateway_response['code']	= '300';
    		$api_gateway_response['msg']	= $service_response;
    	}
    	$this->response = $api_gateway_response;
    	$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }
    
    /**
     * @api	{get}	/spa/v2/	手机快捷登录
     * @apiDescription	手机快捷登录
     * @apiGroup		spa/user
     * @apiName			mobile_login
     *
     * @apiParam {String} mobile 手机号
     * @apiParam {String} register_verification_code 注册手机验证码
     *
     * @apiSampleRequest /spa/v2/?service=user.mobile_login
     **/
    public function mobile_login(){
    	$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
    	$query['mobile']	= require_request('mobile');
    	$query['register_verification_code']	= require_request('register_verification_code');
    	
    	$params['url']		= $this->config->item('user', 'service') . '/v1' . '/user/mobileLogin';
    	$params['method']	= 'get';
    	$params['data']		= $query;
		$service_response  = $this->api_process->process($params);
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200) {
			$api_gateway_response['code']	= '200';
			$api_gateway_response['msg']	= '登录成功';
	 		$api_gateway_response['data']	= $service_response;
		} else {
			$api_gateway_response['code']	= '300';
			$api_gateway_response['msg']	= $service_response;
		}
		$this->response = $api_gateway_response;
		self::passportLogin();
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
     * @api              {post} /post/v2/ 密码找回
     * @apiDescription   密码找回
     * @apiGroup         spa/user
     * @apiName          forget_passwd
     *
     * @apiParam {String} mobile 手机号
     * @apiParam {String} password	密码
     * @apiParam {String} verification_code 短信验证码
     *
     * @apiSampleRequest /spa/v2/?service=user.forget_passwd
     **/
	public function forget_passwd() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$query['mobile']	= require_request('mobile');
		$query['password']	= md5(require_request('password'));//@TODO
		$query['re_password']	= md5(require_request('password'));//@TODO
		$query['verification_code']	= require_request('verification_code');
		$params['url']		= $this->config->item('user', 'service') . '/v1' . '/user/forgetPasswd';
		$params['method']	= 'get';
		$params['data']		= $query;
		$service_response  = $this->api_process->process($params);
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200) {
			$api_gateway_response['code']	= '200';
	 		$api_gateway_response['msg']	= $service_response;
		} else {
			$api_gateway_response['code']	= '300';
			$api_gateway_response['msg']	= $service_response;
		}
		$this->response = $api_gateway_response;
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api	{get}	/spa/v2/	 退出登陆
	 * @apiDescription	退出登陆
	 * @apiGroup		spa/user
	 * @apiName			signout
	 *
	 * @apiParam {String} connect_id api_gateway返回的session_id
	 *
	 * @apiSampleRequest /spa/v2/?service=user.signout
	 **/
	public function signout() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$connect_id = require_request('connect_id');//@TODO
		$this->load->library('Nirvana3session');
		$this->nirvana3session->destory();
		$this->response = array('code' => 200, 'data' => '', 'msg' => '退出成功');
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api    {post}  /   优惠券列表(下单时使用）
	 * @apiDescription 用户优惠券列表(下单时使用)
	 * @apiGroup       spa/user
	 * @apiName        userCouponList
	 *
	 * @apiParam   {String}    connect_id      api_gateway返回的session_id
	 * @apiParam   {String}    goods_money     商品总金额
	 * @apiParam   {String}    pay_discount    折扣金额
	 * @apiParam   {String}    store_id_list   收货地址对应的门店列表
	 *
	 * @apiSampleRequest /spa/v2/?service=user.userCouponList
	 **/
	public function userCouponList(){
	    $gateway_response = array();
	    $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
	    $gateway_response = array();
	    $url = $this->config->item('user', 'service') . '/v1' . '/card/cardlist';
	    $uid = get_uid();
	    $goods_money = require_request('goods_money');
	    $source = $this->source;
	    $version = $this->version;
	    $pay_discount = require_request('pay_discount');
	    $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid, 'limit' => 100)));
	    $result = $this->restclient->get($url, $request);
	    $code = $result->info->http_code;
	    $service_response = json_decode($result->response, true);
	    $log_content['id'] = $this->request_id;
	    $log_content['request']['url'] = $url;
	    $log_content['request']['content'] = $request;
	    
	    $log_content['response']['code'] = $code;
	    $log_content['response']['content'] = $service_response;
	    $code_first = substr($code, 0, 1);
	    $cart_pro_ids = array();
	    if ($code_first == 5 || !$service_response) {
	        $log_tag = 'ERROR';
	        $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
	    } elseif ($code_first == 2 && $service_response) {
	        $cardlist = $service_response['cards'];
	        
	        $url = $this->config->item('cart', 'service') . '/v3/cart' . '/' . $uid . '/order?request_id=' . $this->request_id;;
	        $service_cart_query = array();
	        $service_cart_query['source']           = $source;
	        $service_cart_query['source_version']   = $version;
	        
	        $temp = changeStoreId($this->input->post('store_id_list'));
	        $this->store_id_lists = $temp['store_id_list'];
	        $this->tms_region_type = $temp['tms_region_type'];
	        $this->tms_region_time = $temp['tms_region_time'];
	        $service_cart_query['stores']           = $this->store_id_lists;//门店列表,多个用逗号分隔
	        $service_cart_query['range']            = $this->tms_region_type;//门店配送范围?
	        $service_cart_query['uid']              = $uid;
	        $request = http_build_query($service_cart_query);
	        $result = $this->restclient->get($url, $request);
	        $code = $result->info->http_code;
	        $service_cart_response = json_decode($result->response, true);
	        $cart_products = $service_cart_response['products'];
	        
	        $this->load->model('card_model');
	        $card_pros = array();
	        $discount_upto_goods_money = $goods_money + $pay_discount;
	        foreach ($cardlist as $key => $value) {
	            if($value['product_id']){
	                $c_ps = explode(',', $value['product_id']);
	                $card_pros = array_merge($card_pros,$c_ps);
	            }
	            $info = $this->card_model->card_can_use($value, $uid, $discount_upto_goods_money, $source, 0, $pay_discount, 0, $cart_products);
	            if($info[0] == 0){
	                $cardlist[$key]['can_not_use'] = 1;
	                $cardlist[$key]['can_not_use_reason'] = $info[1] ? $info[1] : "不可使用";
	            }else{
	                $cardlist[$key]['can_not_use'] = 0;
	                $cardlist[$key]['can_not_use_reason'] = '';
	            }
	        }
	        $card_pros = array_filter(array_unique($card_pros));
	        $p_infos = array();
	        
	        if($card_pros){
	            $url = $this->config->item('product', 'service') . '/v2' . '/product/productBaseInfo';
	            $request = http_build_query(array('product_id' => $card_pros));
	            $result = $this->restclient->post($url, $request);
	            $code = $result->info->http_code;
	            $service_response = json_decode($result->response, true);
	            $code_first = substr($code, 0, 1);
	            if ($code_first == 5 || !$service_response) {
	                $log_tag = 'ERROR';
	                $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
	            } elseif ($code_first == 2 && $service_response) {
	                $product_list = $service_response;
	                foreach ($product_list as $key => $value) {
	                    $p_infos[$value['id']] = $value['product_name'];
	                }
	                
	            } elseif ($code_first == 3 || $code_first == 4) {
	                $gateway_response['code'] = '300';
	                $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
	                $log_tag = 'INFO';
	            }
	        }
	        
	        $sort_array = array();
	        foreach ($cardlist as $key => &$value) {
	            if(empty($value['product_id'])){
	                $value['use_range'] = "全站通用(个别商品除外)";
	            }else{
	                $value['card_product_id'] = $value['product_id'];
	                $c_ps = explode(',', $value['product_id']);
	                $curr_range = array();
	                foreach ($c_ps as $v) {
	                    $curr_range[] = isset($p_infos[$v])?$p_infos[$v]:'';
	                }
	                $value['use_range'] = "仅限" . join(",", $curr_range) . "使用";
	            }
	            if ($value['order_money_limit'] > 0)
	                $value['use_range'] .="满" . floatval($value['order_money_limit']) . "使用";
	                
	                if(!empty($value['direction'])){
	                    $value['use_range'] = $value['direction'];
	                }
	                if ($value['to_date'] < date("Y-m-d")) {
	                    $value['is_expired'] = 1;
	                } else {
	                    $value['is_expired'] = 0;
	                }
	                $sort_array[] = $value['card_money'];
	        }
	        
	        $sort_array and array_multisort($sort_array,SORT_DESC,$cardlist);
	        
	        $can_use_list = array();
	        $can_not_use_list = array();
	        foreach ($cardlist as $card) {
	            if($card['can_not_use'] == 1){
	                $can_not_use_list[] = $card;
	            }else{
	                $can_use_list[] = $card;
	            }
	        }
	        $cardlist_result = array_merge($can_use_list,$can_not_use_list);
	        $gateway_response['code'] = '200';
	        $gateway_response['msg'] = '获取成功';
	        $gateway_response['data'] = $cardlist_result;
	        $log_tag = 'INFO';
	    } elseif ($code_first == 3 || $code_first == 4) {
	        $gateway_response['code'] = '300';
	        $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
	        $log_tag = 'INFO';
	    }
	    
	    $this->fruit_log->track($log_tag, json_encode($log_content));
	    $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
	    if($this->response == null){
	        $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
	    }
	    $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	/**
	 * @api    {get}   /spa/v2/    优惠券列表
	 * @apiDescription 用户优惠券列表
	 * @apiGroup       spa/user
	 * @apiName        userCouponNewList
	 *
	 * @apiParam   {String}    connect_id  api_gateway返回的session_id
	 *
	 * @apiSampleRequest /spa/v2/?service=user.userCouponNewList
	 **/
	public function userCouponNewList(){
	    $gateway_response = array();
	    $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
	    $url = $this->config->item('user', 'service') . '/v1' . '/card/cardlistnew';
	    $uid = get_uid();
	    $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
	    $result = $this->restclient->get($url, $request);
	    $code = $result->info->http_code;
	    $service_response = json_decode($result->response, true);
	    $log_content['id'] = $this->request_id;
	    $log_content['request']['url'] = $url;
	    $log_content['request']['content'] = $request;
	    
	    $log_content['response']['code'] = $code;
	    $log_content['response']['content'] = $service_response;
	    $code_first = substr($code, 0, 1);
	    if ($code_first == 5 || !$service_response) {
	        $log_tag = 'ERROR';
	        $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
	    } elseif ($code_first == 2 && $service_response) {
	        $cardlist = $service_response;
	        $card_pros = array();
	        foreach ($cardlist['notused'] as $key => $value) {
	            if($value['product_id']){
	                $c_ps = explode(',', $value['product_id']);
	                $card_pros = array_merge($card_pros,$c_ps);
	            }
	        }
	        foreach ($cardlist['used'] as $key => $value) {
	            if($value['product_id']){
	                $c_ps = explode(',', $value['product_id']);
	                $card_pros = array_merge($card_pros,$c_ps);
	            }
	        }
	        foreach ($cardlist['overdue'] as $key => $value) {
	            if($value['product_id']){
	                $c_ps = explode(',', $value['product_id']);
	                $card_pros = array_merge($card_pros,$c_ps);
	            }
	        }
	        $card_pros = array_filter(array_unique($card_pros));
	        $p_infos = array();
	        if($card_pros){
	            $url = $this->config->item('product', 'service') . '/v2' . '/product/productBaseInfo';
	            $request = http_build_query(array('product_id' => $card_pros));
	            $result = $this->restclient->post($url, $request);
	            $code = $result->info->http_code;
	            $code_first = substr($code, 0, 1);
	            $service_response = json_decode($result->response, true);
	            if ($code_first == 5 || !$service_response) {
	                $log_tag = 'ERROR';
	                $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
	            } elseif ($code_first == 2 && $service_response) {
	                $product_list = $service_response;
	                foreach ($product_list as $key => $value) {
	                    $p_infos[$value['id']] = $value['product_name'];
	                }
	            } elseif ($code_first == 3 || $code_first == 4) {
	                $gateway_response['code'] = '300';
	                $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
	                $log_tag = 'INFO';
	            }
	        }
	        foreach ($cardlist['notused'] as $key => &$value) {
	            if(empty($value['product_id'])){
	                $value['use_range'] = "全站通用(个别商品除外)";
	            }else{
	                $value['card_product_id'] = $value['product_id'];
	                $c_ps = explode(',', $value['product_id']);
	                $curr_range = array();
	                foreach ($c_ps as $v) {
	                    $curr_range[] = isset($p_infos[$v])?$p_infos[$v]:'';
	                }
	                $value['use_range'] = "仅限" . join(",", $curr_range) . "使用";
	            }
	            if ($value['order_money_limit'] > 0)
	                $value['use_range'] .="满" . floatval($value['order_money_limit']) . "使用";
	                
	                if(!empty($value['direction'])){
	                    $value['use_range'] = $value['direction'];
	                }
	        }
	        foreach ($cardlist['used'] as $key => &$value) {
	            if(empty($value['product_id'])){
	                $value['use_range'] = "全站通用(个别商品除外)";
	            }else{
	                $value['card_product_id'] = $value['product_id'];
	                $c_ps = explode(',', $value['product_id']);
	                $curr_range = array();
	                foreach ($c_ps as $v) {
	                    $curr_range[] = isset($p_infos[$v])?$p_infos[$v]:'';
	                }
	                $value['use_range'] = "仅限" . join(",", $curr_range) . "使用";
	            }
	            if ($value['order_money_limit'] > 0)
	                $value['use_range'] .="满" . floatval($value['order_money_limit']) . "使用";
	                
	                if(!empty($value['direction'])){
	                    $value['use_range'] = $value['direction'];
	                }
	        }
	        foreach ($cardlist['overdue'] as $key => &$value) {
	            if(empty($value['product_id'])){
	                $value['use_range'] = "全站通用(个别商品除外)";
	            }else{
	                $value['card_product_id'] = $value['product_id'];
	                $c_ps = explode(',', $value['product_id']);
	                $curr_range = array();
	                foreach ($c_ps as $v) {
	                    $curr_range[] = isset($p_infos[$v])?$p_infos[$v]:'';
	                }
	                $value['use_range'] = "仅限" . join(",", $curr_range) . "使用";
	            }
	            if ($value['order_money_limit'] > 0)
	                $value['use_range'] .="满" . $value['order_money_limit'] . "使用";
	                
	                if(!empty($value['direction'])){
	                    $value['use_range'] = $value['direction'];
	                }
	        }
	        $gateway_response['data'] = $cardlist;
	        $gateway_response['code'] = '200';
	        $gateway_response['msg'] = '';
	        $log_tag = 'INFO';
	        
	    } elseif ($code_first == 3 || $code_first == 4) {
	        $gateway_response['code'] = '300';
	        $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
	        $log_tag = 'INFO';
	    }
	    
	    $this->fruit_log->track($log_tag, json_encode($log_content));
	    $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
	    if($this->response == null){
	        $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
	    }
	    $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
	
	private function passportLogin() {
		if(is_array($this->response) && $this->response['code'] == 200 && isset($this->response['data'])){
			$this->load->library('Nirvana3session');
			$this->nirvana3session->set_userdata($this->response['data']['userinfo']);
			$this->response['data']['connect_id'] = session_id();
		}
	}
}