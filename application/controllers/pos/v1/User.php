<?php
class User extends CI_Controller {
	private $source, $version, $response;

	public function __construct() {
		parent::__construct();
		$this->load->config('service');
		$this->load->library('restclient');
		$this->load->helper('public');
		$this->load->library('fruit_log');
		$this->request_id =  uniqid('POS_',true);//用于记录日志用
		define('CURRENT_VERSION_USER_API', $this->config->item('user', 'service'));
		$this->source = $this->input->get_post('source');
		$this->version = $this->input->get_post('version');
	}

	public function __destruct() {
		if($this->response['code'] != '200'){
			$this->rollback();
		}
		echo json_encode($this->response);
		if (!function_exists("fastcgi_finish_request")) {
			function fastcgi_finish_request() { }//为windows兼容
		}
		$this->fruit_log->track('INFO', json_encode($this->response));//@TODO,统一收集日志,实验性质,可能包含敏感信息,待处理
		fastcgi_finish_request();
		$this->fruit_log->save();
	}

	public function getByQrcode(){
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$this->fruit_log->track('INFO', "parameters : ".json_encode(array_merge($this->input->get(),$this->input->post())));
		$query['uid'] = $this->input->get_post('uid');
        $query['token_time'] = $this->input->get_post('token_time');
        $query['token'] = $this->input->get_post('token');
        $url = CURRENT_VERSION_USER_API . '/v1/user/' .'getIDQrcode' ;

        $service_request = http_build_query($query);
		$result = $this->restclient->post($url, $service_request);
		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true);
		$code_first = substr($code, 0, 1);
		if ($code_first == 5 || !$service_response) {
			$log_tag = 'ERROR';
			$log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
			exit;
		}elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= $service_response['msg'];
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2 && $service_response) {
			$uid = $service_response['uid'];
		}
		if(!$uid or  $uid != $this->input->get_post('uid')){
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= '会员ID错误';
			$this->response = $gateway_response;
			exit;
		}
		$url = CURRENT_VERSION_USER_API . '/v1/user/' .'posUser';
		$service_request = http_build_query($this->input->post());
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
			$gateway_response['code'] = '200';
			$gateway_response['msg'] = '获取会员信息成功';
			$gateway_response['data'] = $this->formatPosUser($service_response);
			$this->response = $gateway_response;
		}
	}

	public function getByMobile(){
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$this->fruit_log->track('INFO', "parameters : ".json_encode(array_merge($this->input->get(),$this->input->post())));
		$query = array();
		$query['mobile'] = $this->input->get_post('mobile');
        $url = CURRENT_VERSION_USER_API . '/v1/user/' .'getByMobile' ;

        $service_request = http_build_query($query);
		$result = $this->restclient->post($url, $service_request);
		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true);
		$code_first = substr($code, 0, 1);
		$gateway_response = array();
		if ($code_first == 5 || !$service_response) {
			$log_tag = 'ERROR';
			$log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
			exit;
		}elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= $service_response['msg'];
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2 && $service_response) {
			$gateway_response['data']['uid'] = $service_response['user']['id'];
			$rank = bcsub($service_response['user']['user_rank'] , 1);
			$rank = ($rank >= 0) ? $rank : 0;
			$gateway_response['data']['rank'] = 'V'.$rank;
			$gateway_response['code']	= '200';
			$gateway_response['msg']	= '';
			$this->response = $gateway_response;
			exit;
		}
	}

	private function formatPosUser($user){
		$format_user = array();
		$format_user['id'] = $user['id'];
		$format_user['mobile'] = $user['mobile'];
		$format_user['money'] = $user['money']?$user['money']:0.00;
		$format_user['score'] = $user['jf']?$user['jf']:0;
		$format_user['score_money'] = bcdiv(intval($user['jf']), 100, 2);
		$rank = bcsub($user['user_rank'] , 1);
		$rank = ($rank >= 0) ? $rank : 0;
		$format_user['rank'] = 'V'.$rank;
		$format_user['card_list'] = array();
		if($user['card_list']){
			foreach ($user['card_list'] as $card) {
				// $card_one = array();
				// $card_one['card_number'] = $card['card_number'];
				// $card_one['card_money'] = $card['card_money'];
				// $card_one['remarks'] = $card['remarks'];
				// $card_one['order_money_limit'] = $card['order_money_limit'];
				// $card_one['use_range'] = $card['use_range']?$card['use_range']:'全场通用';
				// $card_one['use_product_no'] = isset($card['use_product_no']) ? $card['use_product_no'] : array();
				$card_one = $this->format_card($card);
				$format_user['card_list'][] = $card_one;
			}
		}
		return $format_user;
	}

	public function cutScoreMoney(){
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$this->fruit_log->track('INFO', "parameters : ".json_encode(array_merge($this->input->get(),$this->input->post())));
		$query['uid'] = $this->input->get_post('uid');
        $query['token_time'] = $this->input->get_post('token_time');
        $query['token'] = $this->input->get_post('token');
        $query['is_pay'] = 1;
        $jf_money = $this->input->get_post('score_money');
        $jf = bcmul($jf_money, 100, 0);
        $money = $this->input->get_post('money');
        $url = CURRENT_VERSION_USER_API . '/v1/user/' .'getIDQrcode' ;
        $service_request = http_build_query($query);
		$result = $this->restclient->post($url, $service_request);
		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true);
		$code_first = substr($code, 0, 1);
		$order_name = $this->input->get_post('order_name');
		if ($code_first == 5 || !$service_response) {
			// $log_tag = 'ERROR';
			// $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
			exit;
		}elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= $service_response['msg'];
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2 && $service_response) {
			$uid = $service_response['uid'];
		}
		if(!$uid or  $uid != $this->input->get_post('uid')){
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= '会员ID错误';
			$this->response = $gateway_response;
			exit;
		}
        $msg = '';
        if($jf > 0){
            $cut_jf_query = array();
            $cut_jf_query['uid'] = $uid;
            $cut_jf_query['jf'] = $jf;
            $cut_jf_query['reason'] = "POS订单".$order_name."消费积分".$jf;
            $cut_jf_query['type'] = '消费';
            $cut_jf_query['request_id'] = $this->request_id;
            $url = CURRENT_VERSION_USER_API . '/v1/user/' .'cutUserJf';
            $service_request = http_build_query($cut_jf_query);
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
				$msg = '积分';
			}
        }

        if($money > 0){
        	$cut_money_query = array();
        	$cut_money_query['uid'] = $uid;
            $cut_money_query['order_name'] = $order_name;
            $cut_money_query['reason'] = "POS支出涉及订单号" . $order_name;
            $cut_money_query['money'] = $money;
            $cut_money_query['request_id'] = $this->request_id;
            $cut_money_query['source'] = 'POS';
            $url = CURRENT_VERSION_USER_API . '/v1/user/' .'cutUserMoney';
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
        $url = CURRENT_VERSION_USER_API . '/v1/user/' .'posUser';
		$service_request = http_build_query($this->input->post());
		$result = $this->restclient->post($url,$service_request);
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
			$user_info = $this->formatPosUser($service_response);
			$gateway_response['data']['mobile'] = $user_info['mobile'];
			$gateway_response['data']['money'] = $user_info['money'];
			$gateway_response['data']['score'] = $user_info['score'];
			$gateway_response['data']['score_money'] = $user_info['score_money'];
			$gateway_response['data']['card_list'] = $user_info['card_list'];
		}
        $gateway_response['code'] = '200';
		$gateway_response['msg'] = $msg.'扣除成功';
		// if($money > 0){
		// 	$this->disableQrcode($query['uid'] ,$query['token_time'] ,$query['token']);
		// }

		$this->load->library('notify');
		if($jf>0 || $money>0){
			$sms_content = "您于".date('Y-m-d H:i:s')."消费";
			if($jf>0){
	            $sms_content .= '积分'.$jf."点";
			}
			if($money>0){
	            $sms_content .= '余额'.$money."元";
			}
			$sms_content = $sms_content.'。';
	        $params = array(
	            'uid' => (string) $uid,
	            'title' => '天天果园通知',
	            'message' => $sms_content,
	            "tabType"=>     "",
	            "type"=>         ""
	        );
			$this->notify->send('app','send',$params);
			if(isset($user_info['mobile']) && $user_info['mobile']){
				$params = array(
		            'mobile' => $user_info['mobile'],
		            'message' => $sms_content,
		        );
				$this->notify->send('sms','send',$params);
			}
		}
		$this->response = $gateway_response;
	}

	public function useCard(){
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$this->fruit_log->track('INFO', "parameters : ".json_encode(array_merge($this->input->get(),$this->input->post())));
		$query['uid'] = $this->input->get_post('uid');
        $query['token_time'] = $this->input->get_post('token_time');
        $query['token'] = $this->input->get_post('token');
        $query['is_pay'] = 1;
        $card_number = $this->input->get_post('card_number');
        $url = CURRENT_VERSION_USER_API . '/v1/user/' .'getIDQrcode' ;
        $service_request = http_build_query($query);
		$result = $this->restclient->post($url, $service_request);
		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true);
		$code_first = substr($code, 0, 1);
		$order_name = $this->input->get_post('order_name');
		if ($code_first == 5 || !$service_response) {
			// $log_tag = 'ERROR';
			// $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
			exit;
		}elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= $service_response['msg'];
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2 && $service_response) {
			$uid = $service_response['uid'];
		}
		if(!$uid or  $uid != $this->input->get_post('uid')){
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= '会员ID错误';
			$this->response = $gateway_response;
			exit;
		}
        $msg = '';
        $url = CURRENT_VERSION_USER_API . '/v1/card/' .'posUseCard';
        $service_request = http_build_query($this->input->post());
		$result = $this->restclient->post($url,$service_request);
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
			$msg = '积分';
		}
        $url = CURRENT_VERSION_USER_API . '/v1/user/' .'posUser';
		$service_request = http_build_query($this->input->post());
		$result = $this->restclient->post($url,$service_request);
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
			$user_info = $this->formatPosUser($service_response);
			$gateway_response['data']['mobile'] = $user_info['mobile'];
			$gateway_response['data']['money'] = $user_info['money'];
			$gateway_response['data']['score'] = $user_info['score'];
			$gateway_response['data']['score_money'] = $user_info['score_money'];
			$gateway_response['data']['card_list'] = $user_info['card_list'];
		}
        $gateway_response['code'] = '200';
		$gateway_response['msg'] = '抵扣成功';
		$this->response = $gateway_response;
	}

	private function rollback(){
        $rollback_url = CURRENT_VERSION_USER_API.'/v1/user/'.'rollback' ;
        $request['request_id'] = $this->request_id;
        $service_request = http_build_query($request);
        $this->restclient->post($rollback_url,$service_request);
	}

	private function disableQrcode($uid,$token_time,$token){
		$query['uid'] = $uid;
        $query['token_time'] = $token_time;
        $query['token'] = $token;
        $url = CURRENT_VERSION_USER_API . '/v1/user/' .'disableQrcode' ;
        $service_request = http_build_query($query);
		$result = $this->restclient->post($url, $service_request);
	}

	public function resetQrcode(){
		$query['uid'] = $this->input->get_post('uid');
        $query['token_time'] = $this->input->get_post('token_time');
        $query['token'] = $this->input->get_post('token');
		$this->disableQrcode($query['uid'] ,$query['token_time'] ,$query['token']);
		$gateway_response['code'] = '200';
		$gateway_response['msg'] = 'succ';
		$this->response = $gateway_response;
	}

	public function cardInfo(){
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$this->fruit_log->track('INFO', "parameters : ".json_encode(array_merge($this->input->get(),$this->input->post())));


        $url = CURRENT_VERSION_USER_API . '/v1/card/' .'getCardInfo' ;

        $service_request = http_build_query($this->input->get_post());
		$result = $this->restclient->post($url, $service_request);
		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true);
		$code_first = substr($code, 0, 1);
		$order_name = $this->input->get_post('order_name');
		$cardInfo = array();
		if ($code_first == 5 || !$service_response) {
			// $log_tag = 'ERROR';
			// $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
			exit;
		}elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= $service_response['msg'];
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2 && $service_response) {
			$cardInfo = $service_response;
		}
		if(empty($cardInfo)){
			$gateway_response['code']	= '300';
			$gateway_response['msg']	= '券卡不存在';
			$this->response = $gateway_response;
			exit;
		}
		$this->load->model('card_model');
		$cardInfo_arr = $this->card_model->data_format(array($cardInfo));
		$cardInfo = $cardInfo_arr[0];
		$gateway_response['code']	= '200';
		$gateway_response['msg'] = '获取成功';
		$gateway_response['data'] = $this->format_card($cardInfo);
		$this->response = $gateway_response;
	}

	private function format_card($card){
		$this->load->model('card_model');
		$card_one = array();
		$card_one['card_number'] = $card['card_number'];
		$card_one['card_money'] = $card['card_money'];
		$card_one['remarks'] = $card['remarks'];
		$card_one['order_money_limit'] = $card['order_money_limit'];
		$card_one['use_range'] = $card['use_range']?$card['use_range']:'全场通用';
		$card_one['use_product_no'] = isset($card['use_product_no']) ? $card['use_product_no'] : array();
        list($can_use,$reason) = $this->card_model->cardBaseInfoCanUse($card);
    	$card_one['is_can_use'] = $can_use;
    	$card_one['disable_reason'] = $reason;
		return $card_one;
	}

	public function cardBlackList(){
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$this->fruit_log->track('INFO', "parameters : ".json_encode(array_merge($this->input->get(),$this->input->post())));
		$query = array();
		$query['mobile'] = $this->input->get_post('mobile');
        $url = CURRENT_VERSION_USER_API . '/v1/card/' .'getCardBlackList' ;

        $service_request = http_build_query($query);
		$result = $this->restclient->post($url, $service_request);
		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true);
		$code_first = substr($code, 0, 1);
		$gateway_response = array();
		if ($code_first == 5 || !$service_response) {
			$log_tag = 'ERROR';
			$log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
			exit;
		}elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= $service_response['msg'];
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2 && $service_response) {
			$gateway_response['data'] = $service_response['data'];
			$gateway_response['code']	= '200';
			$gateway_response['msg']	= $service_response['msg'] ? $service_response['msg'] : '成功';
			$this->response = $gateway_response;
			exit;
		}
	}
}
