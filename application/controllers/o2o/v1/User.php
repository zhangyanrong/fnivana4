<?php
class User extends CI_Controller {
	private $source, $version, $response;
	
	public function __construct() {
		parent::__construct();
		$this->load->config('service');
		$this->load->library('restclient');
		$this->load->library('aes',null,'encrypt_aes');
		$this->load->helper('public');
		$this->load->helper('output');
		$this->request_id =  uniqid('O2O_',true);//用于记录日志用
		define('CURRENT_VERSION_USER_API', $this->config->item('user', 'service'));
		define('CURRENT_VERSION_ORDER_USER_API', $this->config->item('order', 'service'));
		$this->source = $this->input->get_post('source');
		$this->version = $this->input->get_post('version');
	}
	
	public function __destruct() {
		if($this->response['code'] != '200'){
			$this->rollback();
		}
		o2o_output($this->response);
	}

	public function addPosScore(){
        $data = $this->input->get_post('data');
        $data = urldecode($data);
        $data = $this->encrypt_aes->AesDecrypt($data, base64_decode(POOL_O2O_AES_KEY));
        $data = json_decode($data,true);
        $money = abs($data['money']);
        $uid = $data['uid'];
        $order_name = $data['order_name'];
        if(empty($order_name)){
        	$gateway_response['code']	= '300';
			$gateway_response['msg']	= '订单号不能为空';
			$this->response = $gateway_response;
			exit;
        }
        $query = array();
        $query['uid'] = $uid;
        $query['request_id'] = $this->request_id;
        $url = CURRENT_VERSION_ORDER_USER_API  . '/v1/user'. '/' .'first_buy/'.$uid;
        $service_request = http_build_query($query);
		$result = $this->restclient->get($url,$service_request);
		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true); 
		$code_first = substr($code, 0, 1);
		$is_first = false;
		if ($code_first == 5) {
			exit;
		}elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= $service_response['msg'];
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2 && $service_response) {
			$is_first = $service_response;
		}

		$query = array();
        $query['uid'] = $uid;
        $query['is_first'] = false; //线下订单不能判断是否收购
        $query['money'] = bcdiv($money, 10,2);
        $query['request_id'] = $this->request_id;
        $url = CURRENT_VERSION_USER_API . '/v1/user'. '/' .'calculateScore';
        $service_request = http_build_query($query);
		$result = $this->restclient->post($url,$service_request);
		$code = $result->info->http_code;
		$service_response = json_decode($result->response, true); 
		$code_first = substr($code, 0, 1);
		$jf = 0;
		if ($code_first == 5 || !$service_response) {
			exit;
		}elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']	= '300';
			$gateway_response['msg']	= $service_response['msg'];
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2 && $service_response) {
			$jf = $service_response['score'];
		}

        if($jf == 0){
            $gateway_response['code']	= '200';
			$gateway_response['msg']	= '积分为0';
			$this->response = $gateway_response;
        }else{
            $add_jf_query = array();
            $add_jf_query['uid'] = $uid;
            $add_jf_query['jf'] = $jf;
            $add_jf_query['reason'] = $order_name.'订单完成赠送'.$jf.'积分';
            $add_jf_query['type'] = 'POS订单完成';
            $add_jf_query['request_id'] = $this->request_id;
            $url = CURRENT_VERSION_USER_API . '/v1/user'. '/' .'addUserJf';
            $service_request = http_build_query($add_jf_query);
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
				$gateway_response['msg'] = '积分添加成功';
				$gateway_response['data'] = $service_response;
				$this->response = $gateway_response;
			}
        }
	}

    //POS订单退款
	public function refundPosMoneyScore(){
		$gateway_response = array();
        $data = $this->input->get_post('data');
        $data = urldecode($data);
        $data = $this->encrypt_aes->AesDecrypt($data, base64_decode(POOL_O2O_AES_KEY));
        $data = json_decode($data,true);
        $ordermoney = abs($data['ordermoney']);
        $orderjf_money = abs($data['orderscore_money']);
        $refundjf_money = abs($data['refundscore_money']);
        $refundmoney = abs($data['refundmoney']);
        $source = 'POS';
        $uid = $data['uid'];
        $ordername = $data['ordername'];
        if(empty($ordername)){
        	$gateway_response['code']	= '300';
			$gateway_response['msg']	= '订单号不能为空';
			$this->response = $gateway_response;
			exit;
        }
        $query = array();
        $query['uid'] = $uid;
        $query['ordername'] = $ordername;
        $query['orderjf'] = bcmul($orderjf_money, 100, 0);
        $query['ordermoney'] = $ordermoney;
        $query['refundjf'] = bcmul($refundjf_money, 100, 0);
        $query['refundmoney'] = $refundmoney;
        $query['source'] = $source;
        $query['request_id'] = $this->request_id;
        $url = CURRENT_VERSION_USER_API . '/v1/user'. '/' .'refundMoneyScore';
        $service_request = http_build_query($query);
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
			$gateway_response['data'] = '';
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2 && $service_response) {
			$gateway_response['code'] = '200';
			$gateway_response['msg'] = '退款成功';
			$gateway_response['data'] = '';
			$this->response = $gateway_response;
		}
	}

    //POS重置优惠券
    public function returnCard(){
        $gateway_response = array();
        $data = $this->input->get_post('data');
        $data = urldecode($data);
        $data = $this->encrypt_aes->AesDecrypt($data, base64_decode(POOL_O2O_AES_KEY));
        $data = json_decode($data,true);
        $card_number = $data['card_number'];
        $uid = $data['uid'];
        $query = array();
        $source = 'POS';
        $query['uid'] = $uid;
        $query['card_number'] = $card_number;
        $query['source'] = $source;
        $query['request_id'] = $this->request_id;
        $url = CURRENT_VERSION_USER_API . '/v1/card'. '/' .'returnPosCard';
        $service_request = http_build_query($query);
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
			$gateway_response['data'] = '';
			$this->response = $gateway_response;
			exit;
		}elseif ($code_first == 2 && $service_response) {
			$gateway_response['code'] = '200';
			$gateway_response['msg'] = '重置成功';
			$gateway_response['data'] = '';
			$this->response = $gateway_response;
		}
    }

    public function checkCardActiveStatus(){
    	$data = $this->input->get_post('data');
        $data = urldecode($data);
        $data = $this->encrypt_aes->AesDecrypt($data, base64_decode(POOL_O2O_AES_KEY));
        $data = json_decode($data,true);
        $succ_cards = array();
        $error_cards = array();
        $gift_cards = array();  //充值卡
        $pro_cards = array();   //提货券
        foreach ($data['cards'] as $key => $value) {
        	if($value['type'] == 21){
        		$gift_cards[] = $value['card_number'];
        	}elseif($value['type'] == 20){
        		$pro_cards[] = $value['card_number'];
        	}else{
        		$error_cards[] = array('card_number'=>$value['card_number'],'errorMsg'=>'无此券卡类型');
        	}
        }
        if($gift_cards){
        	$query = array();
	        $query['card_numbers'] = $gift_cards;
	        $query['request_id'] = $this->request_id;
	        $url = CURRENT_VERSION_USER_API . '/' .'v1/gift_cards/'.'cardsInfo';
	        $service_request = http_build_query($query);
	        $result = $this->restclient->post($url,$service_request);
			$code = $result->info->http_code;
			$service_response = json_decode($result->response, true); 
			$code_first = substr($code, 0, 1);
			if ($code_first == 5 || !$service_response) {
				exit;
			}elseif ($code_first == 3 || $code_first == 4) {
	            foreach ($gift_cards as $card_number) {
	            	$error_cards[] = array('card_number'=>$card_number,'errorMsg'=>'券卡不存在');
	            }
			}elseif ($code_first == 2 && $service_response) {
				$g_cards = $service_response['cards'];
				$g_isset_cards = array();
				foreach ($g_cards as $key => $value) {
					$g_isset_cards[] = $value['card_number'];
					if ($value['is_used'] != '0') {
	                    $error_cards[] = array('card_number'=>$value['card_number'],'errorMsg'=>'该卡已被使用');
	                    continue;
	                }
	                if ($value['is_freeze'] != '0') {
	                    $error_cards[] = array('card_number'=>$value['card_number'],'errorMsg'=>'该卡已冻结');
	                    continue;
	                }
	                if (strtotime($value['to_date']) < time()) {
	                   $error_cards[] = array('card_number'=>$value['card_number'],'errorMsg'=>'该卡已过期');
	                   continue;
	                }
	                if ($value['activation'] != '0') {
	                    $error_cards[] = array('card_number'=>$value['card_number'],'errorMsg'=>'该卡已激活');
	                    continue;
	                }
	                $succ_cards[] = $value['card_number'];
				}
				$g_diff_cards = array_diff($gift_cards, $g_isset_cards);
				if($g_diff_cards){
					foreach ($g_diff_cards as $key => $value) {
						$error_cards[] = array('card_number'=>$value['card_number'],'errorMsg'=>'券卡不存在');
					}
				}
			}
        }
        if($pro_cards){
        	$query = array();
	        $query['card_numbers'] = $pro_cards;
	        $query['request_id'] = $this->request_id;
	        $url = CURRENT_VERSION_USER_API . '/' .'v1/pro_card/'.'cardsInfo';
	        $service_request = http_build_query($query);
	        $result = $this->restclient->post($url,$service_request);
			$code = $result->info->http_code;
			$service_response = json_decode($result->response, true); 
			$code_first = substr($code, 0, 1);
			if ($code_first == 5 || !$service_response) {
				exit;
			}elseif ($code_first == 3 || $code_first == 4) {
	            foreach ($pro_cards as $card_number) {
	            	$error_cards[] = array('card_number'=>$card_number,'errorMsg'=>'券卡不存在');
	            }
			}elseif ($code_first == 2 && $service_response) {
				$p_cards = $service_response['cards'];
				$p_isset_cards = array();
				foreach ($p_cards as $key => $value) {
					$p_isset_cards[] = $value['card_number'];
					if ($value['is_used'] != '0') {
	                    $error_cards[] = array('card_number'=>$value['card_number'],'errorMsg'=>'该卡已被使用');
	                    continue;
	                }
	                if ($value['is_delete'] != '0') {
	                    $error_cards[] = array('card_number'=>$value['card_number'],'errorMsg'=>'该卡已作废');
	                    continue;
	                }
	                if ($value['is_freeze'] != '0') {
	                    $error_cards[] = array('card_number'=>$value['card_number'],'errorMsg'=>'该卡已冻结');
	                    continue;
	                }
	                if (strtotime($value['to_date']) < time()) {
	                   $error_cards[] = array('card_number'=>$value['card_number'],'errorMsg'=>'该卡已过期');
	                   continue;
	                }
	                if ($value['is_sent'] != '0') {
	                    $error_cards[] = array('card_number'=>$value['card_number'],'errorMsg'=>'该卡已激活');
	                    continue;
	                }
	                $succ_cards[] = $value['card_number'];
				}
				$p_diff_cards = array_diff($pro_cards, $p_isset_cards);
				if($p_diff_cards){
					foreach ($p_diff_cards as $key => $value) {
						$error_cards[] = array('card_number'=>$value['card_number'],'errorMsg'=>'券卡不存在');
					}
				}
			}
        }
        $response['succ_cards'] = $succ_cards;
        $response['error_cards'] = $error_cards;
        $gateway_response['code'] = '200';
		$gateway_response['msg'] = '';
		$gateway_response['data'] = $response;
        $this->response = $gateway_response;
    }

	private function rollback(){
        $rollback_url = CURRENT_VERSION_USER_API.'/v1/user'. '/'.'rollback' ;
        $request['request_id'] = $this->request_id;
        $service_request = http_build_query($request);
        $this->restclient->post($rollback_url,$service_request);
	}
}