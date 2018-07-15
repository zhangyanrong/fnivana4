<?php
Class Paycenter
{
    var $order_name;

	var $money;

	var $web_url;

	var $pay_id;

	var $pay_parent_id;

	var $form_url = 'https://pay.fruitday.com/api/wap/';

    var $mer_key;

	var $type = false;

    var $mobile;
    
    var $browser_type = '';
    
    var $redirect_url = '';

	function __construct()
	{
		header("Content-type:text/html; charset=utf-8");
	}

    function get_sign( $param , $mer_key ){
    	ksort($param);
    	reset($param);
    	$mac= "";
    	$arr = array('region_id', 'sign', 'sign_type', 'subject', 'x', 'y', 'mobile', 'realpay', 'openid', '_r', 'notify_url', 'return_url', 'client_ip');
    	foreach($param as $k=>$v){
    		if (in_array($k, $arr)) continue;
    		$mac .= "&{$k}={$v}";
    	}
    	$mac = substr($mac,1);
    	$mac = md5($mac.$mer_key);
    	return $mac;
    }

    public function set_parm(){

		//商户号
		$param['partner'] = "2088002003028751";

		//商户证书：登陆http://merchant.ips.com.cn/商户后台下载的商户证书内容
		$this->mer_key = PAY_SECRET;

		//商户订单编号
		$param['order_id'] = $this->order_name;

		//订单金额(保留2位小数)
		$param['price'] = number_format($this->money, 2, '.', '');

		//订单日期
		$param['pay_date'] = date('Ymd',time());

		//币种 0代表RMB
		$param['currency'] = "0";
		
		//微信支付需要传递客户端IP
		$param['client_ip'] = $this->getClientIp();

		//发送页面
		$param['notify_url'] = $this->web_url . "/v3/pay/success?order_name=" . $this->order_name . '&redirect_url=' . $this->redirect_url;//@TODO

		//支付结果失败返回的商户URL
		$param['return_url'] = $this->web_url . "/v3/pay/success?order_name=" . $this->order_name . '&redirect_url=' . $this->redirect_url;//@TODO

		//支付银行 1 代表 招商银行
		$param['payment_id'] = $this->get_payment_id( $this->pay_id , $this->pay_parent_id );

		$this->param = $param;

		$this->subject = '官网支付';

		$this->sign = $this->get_sign($param,$this->mer_key);
    }

	function get_payment_id( $pay_id , $pay_parent_id ){
        //支付宝
		if( $pay_parent_id == 1 ){
			$realPay = '30';
		}

        if( $pay_parent_id == 5 ){
            $realPay = '29';
        }

        //微信h5支付
        if( $pay_parent_id == 7 ){
        	if ($this->browser_type == 'wechat') {
	            $realPay = '35';
        	} else {
	            $realPay = '38';
        	}
        }

        //pay_parenet_id 为3的情况下，可直接使用pay_id作为payment_id
        if ($pay_parent_id == 3) {
	        $realPay = $pay_id;
        }
		return $realPay;
	}

	public function  get_form($isBalance = false){
	    $this->set_parm();

        if($isBalance == true){
            return array('url' => $this->form_url, 'params' => array('order_id' => $this->param['order_id'],
                                                                    'payment_id' => $this->param['payment_id'],
                                                                    'price' => $this->param['price'],
                                                                    'partner'=>$this->param['partner'],
                                                                    'currency'=>$this->param['currency'],
                                                                    'pay_date'=>$this->param['pay_date'],
                                                                    'mobile'=>$this->mobile,
                                                                    'subject'=>$this->subject,
                                                                    'notify_url' => $this->param['notify_url'],
                                                                    'return_url' => $this->param['return_url']),
                                                    'data' => array()
            );
        }
		$form = '<div><form target="_self" action="'.$this->form_url.'" method="post" id="frm1" name="frm1">
				  <input type="hidden" name="partner" value="'.$this->param['partner'].'">
				  <input type="hidden" name="order_id" value="'.$this->param['order_id'].'" >
				  <input type="hidden" name="price" value="'.$this->param['price'].'">
				  <input type="hidden" name="currency" value="'.$this->param['currency'].'">
				  <input type="hidden" name="notify_url" value="'.$this->param['notify_url'].'">
				  <input type="hidden" name="return_url" value="'.$this->param['return_url'].'">
				  <input type="hidden" name="payment_id" value="'.$this->param['payment_id'].'">
				  <input type="hidden" name="pay_date" value="'.$this->param['pay_date'].'">
				  <input type="hidden" name="mobile" value="'.$this->mobile.'">
				  <input type="hidden" name="subject" value="'.$this->subject.'">
				  <input type="hidden" name="sign" value="'.$this->sign.'">
				  </form></div>
				  ';
		return $form;
	}

    /*
     * weixin img
     */
    public function get_img(){
        $this->set_parm();

        $url = $this->form_url.'?';
        $url .='partner='.$this->param['partner'];
        $url .='&order_id='.$this->param['order_id'];
        $url .='&price='.$this->param['price'];
        $url .='&currency='.$this->param['currency'];
        $url .='&notify_url='.$this->param['notify_url'];
        $url .='&return_url='.$this->param['return_url'];
        $url .='&payment_id='.$this->param['payment_id'];
        $url .='&pay_date='.$this->param['pay_date'];
        $url .='&mobile='.'18917588301';
        $url .='&subject='.$this->subject;
        $url .='&sign='.$this->sign;

        return $url;
    }

    /*
    * weixin state
    */
    public function get_state()
    {
        $this->set_parm();

        $key ='9861551cf408727f906f2078bda93579';
        $wx_sign = md5( $this->param['order_id'] . $key );

        $url = 'http://pay.fruitday.com/notify/checkState?';
        $url .='order_id='.$this->param['order_id'];
        $url .='&sign='.$wx_sign;

        return $url;
    }
    
    /**
     * 获取客户端IP
     *
     */
    function getClientIp()
    {
    	$ip = '';
    	if (isset($_SERVER["HTTP_X_FORWARDED_FOR"]) && $_SERVER["HTTP_X_FORWARDED_FOR"]){
    		$ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
    	} elseif (isset($_SERVER["HTTP_CLIENT_IP"]) && $_SERVER["HTTP_CLIENT_IP"]){
    		$ip = $_SERVER["HTTP_CLIENT_IP"];
    	} elseif (isset($_SERVER["REMOTE_ADDR"]) && $_SERVER["REMOTE_ADDR"]){
    		$ip = $_SERVER["REMOTE_ADDR"];
    	} elseif (getenv("HTTP_X_FORWARDED_FOR")){
    		$ip = getenv("HTTP_X_FORWARDED_FOR");
    	} elseif (getenv("HTTP_CLIENT_IP")){
    		$ip = getenv("HTTP_CLIENT_IP");
    	} elseif (getenv("REMOTE_ADDR")){
    		$ip = getenv("REMOTE_ADDR");
    	} else{
    		$ip = "127.0.0.1";
    	}
    
    	if(strpos($ip, ',') !== FALSE){
    		$ips = explode(',', $ip);
    		return $ips[0];
    	}
    
    	return $ip;
    }

}
