<?php
class User extends CI_Controller {
	private $source, $version, $response;
    private $secret = "afsvq2mqwc7j0i69uzvukqexrzd1jq6h";  //支付中心密钥
    private $id2payment = array(
        1=>'支付宝',
        7=>'微信支付',
        9=>'微信公众号支付'
    );
    private $secret_cityboxapi = '48eU7IeTJ6zKKDd1';

    public function __construct() {
        parent::__construct();
        $this->load->config('service');
        $this->load->library('restclient');
        $this->load->library('active');
        $this->load->helper('public');
        $this->load->library('api_process');
        $this->request_id =  uniqid('CITYBOX_',true);//用于记录日志用
        define('CURRENT_VERSION_USER_API', $this->config->item('user', 'service') . '/v1/user');
        define('CURRENT_VERSION_CITYBOX_API', $this->config->item('citybox', 'service'));
        $this->source = $this->input->get_post('source');
        $this->version = $this->input->get_post('version');
	}

	private function getUserId($uid){
        $source = $this->input->get_post('source');
        if($source=='wap'){
            $uid = $this->active->decrypt($uid, CITYBOX_CRYPT_SECRET);
            return intval($uid);
        }else{
            session_id($uid);
            session_start();
            return $_SESSION['user_detail']['id'];
        }
    }
	
	public function __destruct() {
		if($this->response['code'] != '200'){
			$this->rollback();
		}
		echo json_encode($this->response);
		// if (!function_exists("fastcgi_finish_request")) {
		// 	function fastcgi_finish_request() { }//为windows兼容
		// }
		// fastcgi_finish_request();
		// $this->fruit_log->save();
	}
	
	public function getUserInfo(){
        $uid = $this->input->get_post('uid');
        $uid = $this->getUserId($uid);

        if(!$uid){
            $gateway_response['code']	= '300';
            $gateway_response['msg']	= '登录信息已过期，请重新登录';
            $this->response = $gateway_response;
            exit;
        }
        //解密uid
//        $uid = $this->active->encrypt($uid, CITYBOX_CRYPT_SECRET);
//        echo $uid;

        $url = CURRENT_VERSION_USER_API . '/' .'get'. '/' . $uid;//todo
		$result = $this->restclient->get($url);

		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true); 
		$code_first = substr($code, 0, 1);
		if ($code_first == 5 || !$service_response) {
			exit;
			// $log_tag = 'ERROR';
			// $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
		}elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= $service_response['msg'];
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2 && $service_response) {
			$gateway_response['code'] = '200';
			$gateway_response['msg'] = '获取会员信息成功';
			$gateway_response['data'] = $this->formatCityboxUser($service_response);
			$this->response = $gateway_response;
		}
	}

	private function formatCityboxUser($user){
		$format_user = array();
		$format_user['id'] = $this->active->encrypt($user['id'], CITYBOX_CRYPT_SECRET);
		$format_user['mobile'] = $user['mobile'];
		$format_user['money'] = $user['money'];
		$format_user['username'] = $user['username'];

        $user_head = unserialize($user['user_head']);
        $userface = $user_head['middle'];
        if ($user['is_pic_tmp'] == 1) {
            $format_user['avatar'] = empty($userface) ? PIC_URL . "up_images/default_userpic.png" : PIC_URL_TMP . $userface;
        } else {
            $format_user['avatar'] = empty($userface) ? PIC_URL . "up_images/default_userpic.png" : PIC_URL . $userface;
        }
		$format_user['gender'] = $user['sex'];//'0-未知1-男2-女',
		return $format_user;
	}

	public function cutMoney(){
        $uid = $this->input->get_post('uid');
//        $uid = $this->getUserId($uid);
        $uid = intval($this->active->decrypt($uid, CITYBOX_CRYPT_SECRET));

        $money = $this->input->get_post('money');
		$order_name = $this->input->get_post('order_name');

//		$order_name = '17051031373283';
		$order_info = $this->getOrderInfo($order_name);

		if(!$uid){
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= '会员ID错误';
			$this->response = $gateway_response;
			exit;
		}
        if(!$order_info['money']||$money!=$order_info['money']){
            $gateway_response['code']	= '300';
            $gateway_response['msg']	= '订单金额有误';
            $this->response = $gateway_response;
            exit;
        }

        $msg = '';

        if($money > 0){
        	$cut_money_query = array();
        	$cut_money_query['uid'] = $uid;
            $cut_money_query['order_name'] = $order_name;
            $cut_money_query['reason'] = "魔盒Citybox支出涉及订单号" . $order_name;
            $cut_money_query['money'] = $money;
            $cut_money_query['request_id'] = $this->request_id;
            $cut_money_query['source'] = 'CITYBOX';
            $url = CURRENT_VERSION_USER_API . '/' .'cutUserMoney';
            $service_request = http_build_query($cut_money_query);
			$result = $this->restclient->post($url,$service_request);
			$code = $result->info->http_code;
			$service_response = json_decode($result->response, true); 
			$code_first = substr($code, 0, 1);
			if ($code_first == 5 || !$service_response) {
				exit;
				// $log_tag = 'ERROR';
				// $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
			}elseif ($code_first == 3 || $code_first == 4) {
	            $gateway_response['code']	= '300';
				$gateway_response['msg']	= $service_response['msg'];
				$this->response = $gateway_response;
				exit;
			}elseif ($code_first == 2 && $service_response) {
				$gateway_response['data'] = $service_response;
				$msg .= '余额';
			}
        }
        $url = CURRENT_VERSION_USER_API . '/' .'get'. '/' . $uid;
		$result = $this->restclient->get($url);
		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true); 
		$code_first = substr($code, 0, 1);
		if ($code_first == 5 || !$service_response) {
            exit;
		}elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= $service_response['msg'];
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2 && $service_response) {
			$user_info = $this->formatCityboxUser($service_response);
			$gateway_response['data']['mobile'] = $user_info['mobile'];
			$gateway_response['data']['money'] = $user_info['money'];
            $gateway_response['data']['use_money'] = $money;
		}
        $gateway_response['code'] = '200';
		$gateway_response['msg'] = $msg.'扣除成功';
		// $this->load->library('notify');
		// if($money>0){
		// 	$sms_content = "您于".date('Y-m-d H:i:s')."消费";
		// 	if($money>0){
	 //            $sms_content .= '余额'.$money."元";
		// 	}
		// 	//短信需要发吗  wap app区分短信和弹窗？  弹窗会屏蔽的吧
		// 	$sms_content = $sms_content.'。';
	 //        $params = array(
	 //            'uid' => (string) $uid,
	 //            'title' => '天天果园通知',
	 //            'message' => $sms_content,
	 //            "tabType"=>     "",
	 //            "type"=>         ""
	 //        );
		// 	$this->notify->send('app','send',$params);
		// }
		$this->response = $gateway_response;
	}
	
    /**
     * @api {post} / 小程序支付-免密
     * @apiDescription 小程序支付-免密
     * @apiGroup citybox/user
     * @apiName free_secret_pay
     *
     * @apiParam    {String}    money           订单金额
     * @apiParam    {String}    order_name      订单号
     * @apiParam    {String}    source          来源(系统入参);
     * @apiParam    {Number}    timestamp       时间戳(系统入参);
     * @apiParam    {String}    uid             用户ID号
     *
     * @apiSampleRequest /citybox/v1?service=user.free_secret_pay
     */
	public function free_secret_pay()
	{
	    try {
	        $uid = $this->active->decrypt($this->input->get_post('uid'), CITYBOX_CRYPT_SECRET);
	        $money = require_request('money');
	        $order_name = require_request('order_name');
	        $order_info = $this->getOrderInfo($order_name);
	        
	        if (!$uid) throw new Exception('会员ID错误');
	        if (!$order_info['money'] || ($money != $order_info['money'])) throw new Exception('订单金额有误');
	        
	        if ($money > 0) {
	            $wechat_detail_params['url']       = $this->config->item('user', 'service') . '/v1/wechat/detail_by_uid';
	            $wechat_detail_params['method']    = 'get';
	            $wechat_detail_params['data']['uid']       = $uid;
	            $wechat_detail_params['data']['terminal']  = 'fine';
	            $wechat_detail_response = $this->api_process->process($wechat_detail_params);
	            if ($this->api_process->get('result')->info->http_code != 200) throw new Exception($wechat_detail_response['msg']);
	            
	            $data['uid']       = $uid;
	            $data['openid']    = $wechat_detail_response['data']['openid'];
	            $data['order_name']= $order_name;
	            $data['money']     = abs($order_info['money']);
	            $data['terminal']  = 'fine-wechat';//@TODO
	            $url = $this->config->item('order', 'service') . '/v1/MiniProgramPay/free_secret';
	            $params['url']      = $url;
	            $params['data']     = $data;
	            $params['method']   = 'post';
	            $service_response = $this->api_process->process($params);
	            if (($this->api_process->get('result')->info->http_code != 200)) throw new Exception($service_response['msg']);
	            $gateway_response['code']  = 200;
	            $gateway_response['msg']   = 'success';
	        }
	    } catch (Exception $e) {
	        $gateway_response['code']   = 300;
	        $gateway_response['msg']    = $e->getMessage();
	    }
	    $this->response = $gateway_response;
	}
	
	/**
	 * @api {post} / 申请退款
	 * @apiDescription 用户申请退款
	 * @apiGroup citybox/user
	 * @apiName apply_refund
	 *
	 * @apiParam   {String}    order_name      订单号
	 * @apiParam   {String}    out_refund_id   外部单号
	 * @apiParam   {String}    source          来源(系统入参);
	 * @apiParam   {Number}    timestamp       时间戳(系统入参);
	 * @apiParam   {String}    refund_money    退款金额,单位为分;
	 *
	 * @apiSampleRequest /citybox/v1?service=user.apply_refund
	 */
	public function apply_refund() {
	    try {
	        $refund_money = require_request('refund_money');
	        $order_name = require_request('order_name');
	        $order_info = $this->getOrderInfo($order_name);
	        $out_refund_id = require_request('out_refund_id');
	        
	        if ($refund_money <= 0 || !$order_info['money'] || ($refund_money > $order_info['money'] * 100)) throw new Exception('退款金额有误');
	        
            $data['money']          = abs($order_info['money'] * 100);
            $data['order_name']     = $order_name;
            $data['out_refund_id']  = $out_refund_id;
            $data['refund_fee']     = $refund_money;
            $url = $this->config->item('order', 'service') . '/v1/MiniProgramPay/apply_refund';
            $params['url']          = $url;
            $params['data']         = $data;
            $params['method']       = 'get';
            $service_response = $this->api_process->process($params);
            if (($this->api_process->get('result')->info->http_code != 200)) throw new Exception($service_response['msg']);
            $gateway_response = $service_response;
	    } catch (Exception $e) {
	        $gateway_response['code']   = 300;
	        $gateway_response['msg']    = $e->getMessage();
	    }
	    $this->response = $gateway_response;
	}

	private function rollback(){
        $rollback_url = CURRENT_VERSION_USER_API.'/'.'rollback' ;
        $request['request_id'] = $this->request_id;
        $service_request = http_build_query($request);
        $this->restclient->post($rollback_url,$service_request);
	}

    public function addTrade(){
        $uid = $this->input->get_post('uid');
        $uid = intval($this->active->decrypt($uid, CITYBOX_CRYPT_SECRET));
//        $uid = $this->getUserId($uid);

        $money = $this->input->get_post('money');
        $payment_id = $this->input->get_post('payment_id');
        $return_url = $this->input->get_post('return_url');

        $payment_id2trade_id = array(
            1=>30,    //支付宝支付
            7=>38,   //浏览器发起微信支付
            9=>35   //微信内部发起微信支付
        );

        $msg = '魔都盒子充值';

        if(!in_array($payment_id,array(1,7,9))){
            $gateway_response['code']	= '300';
            $gateway_response['msg']	= '充值方式入参有误';
            $this->response = $gateway_response;
            exit;
        }
        $payment = $this->id2payment[$payment_id];
        if($money > 0){
            $addTrade_query = array();
            $addTrade_query['uid'] = $uid;
            $addTrade_query['payment'] = $payment;
            $addTrade_query['money'] = $money;
            $addTrade_query['request_id'] = $this->request_id;
            $addTrade_query['msg'] = $msg;
            $url = CURRENT_VERSION_USER_API . '/' .'addIncomeTrade';
//            print_r($addTrade_query);
            $service_request = http_build_query($addTrade_query);
            $result = $this->restclient->post($url,$service_request);
            $code = $result->info->http_code;
            $service_response = json_decode($result->response, true);
            $code_first = substr($code, 0, 1);
            if ($code_first == 5 || !$service_response) {
                exit;
                // $log_tag = 'ERROR';
                // $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
            }elseif ($code_first == 3 || $code_first == 4) {
                $gateway_response['code']	= '300';
                $gateway_response['msg']	= $service_response['msg'];
                $this->response = $gateway_response;
                exit;
            }elseif ($code_first == 2 && $service_response) {
                $gateway_response['data'] = $service_response;
            }
        }

        $payment_id = $payment_id2trade_id[$payment_id]; //支付中心的payment_id转换
        $trade_number = $gateway_response['data']['trade_number'];
        $sign = $this->create_pay_sign($trade_number,$payment_id,$money);
        $return_url = urlencode($return_url);
        $gateway_response['redirect_url'] = 'http://pay.fruitday.com/api/wap/?order_id='.$trade_number.'&price='.$money.'&payment_id='.$payment_id.'&sign='.$sign.'&notify_url=http://m.fruitday.com&return_url='.$return_url;
        $gateway_response['code'] = '200';
        $gateway_response['msg'] = '支付发起成功';


        $this->response = $gateway_response;

    }

    private function create_pay_sign($order_id,$payment_id,$money){
        $sign = md5('order_id='.$order_id.'&payment_id='.$payment_id.'&price='.$money.$this->secret);
        return $sign;
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
        $url = CURRENT_VERSION_CITYBOX_API . '/api/fruitday/get_detail';
        $service_request = http_build_query($params);
        $result = $this->restclient->get($url,$service_request,$headers);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        return $service_response;
    }

    public function getUidByToken(){
        $token = $this->input->get_post('token');

        $url = CURRENT_VERSION_USER_API . '/' .'getUidByToken'. '/' . $token;
        $result = $this->restclient->get($url);

        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $code_first = substr($code, 0, 1);
        if ($code_first == 5 || !$service_response) {
            exit;
            // $log_tag = 'ERROR';
            // $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        }elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
            $gateway_response['msg']	= $service_response['msg'];
            $this->response = $gateway_response;
            exit;
        }elseif ($code_first == 2 && $service_response) {
//            $gateway_response['uid'] = $service_response['uid'];
            $uid = $this->getUserId($service_response['uid']);
            if(!$uid){
                $gateway_response['code']	= '300';
                $gateway_response['msg']	= '登录信息已过期，请重新登录';
                $this->response = $gateway_response;
                exit;
            }
            //解密uid
//        $uid = $this->active->encrypt($uid, CITYBOX_CRYPT_SECRET);
//        echo $uid;

            $url = CURRENT_VERSION_USER_API . '/' .'get'. '/' . $uid;//todo
            $result = $this->restclient->get($url);

            $code = $result->info->http_code;
            $service_response = json_decode($result->response, true);
            $code_first = substr($code, 0, 1);
            if ($code_first == 5 || !$service_response) {
                exit;
                // $log_tag = 'ERROR';
                // $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
            }elseif ($code_first == 3 || $code_first == 4) {
                $gateway_response['code']	= '300';
                $gateway_response['msg']	= $service_response['msg'];
                $this->response = $gateway_response;
                exit;
            }elseif ($code_first == 2 && $service_response) {
                $gateway_response['data'] = $this->formatCityboxUser($service_response);
            }
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '获取会员uid成功';
            $this->response = $gateway_response;
        }
    }

    //退款
    public function addMoney(){
        $uid = $this->input->get_post('uid');
//        $uid = $this->getUserId($uid);
        $uid = intval($this->active->decrypt($uid, CITYBOX_CRYPT_SECRET));

        $money = $this->input->get_post('money');
        $order_name = $this->input->get_post('order_name');

//		$order_name = '17051031373283';
        $order_info = $this->getOrderInfo($order_name);

        if(!$uid){
            $gateway_response['code']	= '300';
            $gateway_response['msg']	= '会员ID错误';
            $this->response = $gateway_response;
            exit;
        }
        if(!$order_info['money']||$money>$order_info['money']){
            $gateway_response['code']	= '300';
            $gateway_response['msg']	= '退款金额不能大于订单金额';
            $this->response = $gateway_response;
            exit;
        }

        $msg = '';

        if($money > 0){
            $cut_money_query = array();
            $cut_money_query['uid'] = $uid;
            $cut_money_query['order_name'] = $order_name;
            $cut_money_query['reason'] = "魔盒Citybox退款涉及订单号" . $order_name;
            $cut_money_query['money'] = $money;
            $cut_money_query['request_id'] = $this->request_id;
            $cut_money_query['source'] = 'CITYBOX';
            $url = CURRENT_VERSION_USER_API . '/' .'addUserMoney';
            $service_request = http_build_query($cut_money_query);
            $result = $this->restclient->post($url,$service_request);
            $code = $result->info->http_code;
            $service_response = json_decode($result->response, true);
            $code_first = substr($code, 0, 1);
            if ($code_first == 5 || !$service_response) {
                exit;
                // $log_tag = 'ERROR';
                // $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
            }elseif ($code_first == 3 || $code_first == 4) {
                $gateway_response['code']	= '300';
                $gateway_response['msg']	= $service_response['msg'];
                $this->response = $gateway_response;
                exit;
            }elseif ($code_first == 2 && $service_response) {
                $gateway_response['data'] = $service_response;
                $msg .= '余额';
            }
        }
        $url = CURRENT_VERSION_USER_API . '/' .'get'. '/' . $uid;
        $result = $this->restclient->get($url);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $code_first = substr($code, 0, 1);
        if ($code_first == 5 || !$service_response) {
            exit;
        }elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
            $gateway_response['msg']	= $service_response['msg'];
            $this->response = $gateway_response;
            exit;
        }elseif ($code_first == 2 && $service_response) {
            $user_info = $this->formatCityboxUser($service_response);
            $gateway_response['data']['mobile'] = $user_info['mobile'];
            $gateway_response['data']['money'] = $user_info['money'];
            $gateway_response['data']['use_money'] = $money;
        }
        $gateway_response['code'] = '200';
        $gateway_response['msg'] = $msg.'退款成功';
        $this->load->library('notify');
        if($money>0){
            $sms_content = "您于".date('Y-m-d H:i:s')."退回";
            if($money>0){
                $sms_content .= '余额'.$money."元";
            }
            //短信需要发吗  wap app区分短信和弹窗？  弹窗会屏蔽的吧
            $sms_content = $sms_content.'。';
            $params = array(
                'uid' => (string) $uid,
                'title' => '天天果园通知',
                'message' => $sms_content,
                "tabType"=>     "",
                "type"=>         ""
            );
            $this->notify->send('app','send',$params);
        }
        $this->response = $gateway_response;
    }
}