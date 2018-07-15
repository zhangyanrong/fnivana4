<?php
class Card_model extends CI_Model {
    var $card_channel = array('1'=>'官网','2'=>'APP','3'=>'WAP');
    public function __construct() {
        parent::__construct();
        $this->load->config('service');
        $this->load->library('restclient');
        $this->load->helper('public');

        defined('CURRENT_VERSION_USER_API') or define('CURRENT_VERSION_USER_API', $this->config->item('user', 'service') . '/v1/user');
        defined('CURRENT_VERSION_PRODUCT_API') or define('CURRENT_VERSION_PRODUCT_API', $this->config->item('product', 'service'));
    }
	
	function card_can_use($card,$uid,$goods_money,$source,$jf_money,$pay_discount,$is_o2o = 0, $cart_products = array()) {
        if (empty($card)) {
            return array(0, '卡号无效');
        }
         
        if($is_o2o == 0 && $card['maketing'] == 1){
            return array(0, '该卡是天天到家专用券卡，不能抵扣');
        }elseif($is_o2o == 1 && $card['maketing'] != 1){
            return array(0, '该卡不是天天到家专用券卡，不能抵扣');
        }

        // whether send
        if ($card['is_sent'] == 0) {
            return array(0, "卡号无效");
        }
        // check card denomination
        if($card['maketing']==6){
            if ($card->card_discount == 0.00) {
                return array(0, '该卡折扣为0，不能抵扣');
            }
        }else{
            if ($card['card_money'] <= 0) {
                return array(0, '该卡价值为0，不能抵扣');
            }
        }    

        if($cart_products){
            $dp_card_money = 0;  //可以使用优惠券的商品总金额
            $cart_pro_ids = array();    //可以使用优惠券的商品ID
            $c_ps = array();    //优惠券指定商品ID
            if($card['product_id']){
                $c_ps = explode(',', $card['product_id']);
            }
            foreach ($cart_products as $product) {
                if($product['card_limit'] == 1){
                    
                }else{
                    if(empty($c_ps) || (!empty($c_ps) && in_array($product['product_id'], $c_ps))){
                        $dp_card_money = bcadd($dp_card_money,bcsub($product['amount'],$product['discount'],2),2);
                        $cart_pro_ids[] = $product['product_id'];
                    }
                }
            }
            if(empty($cart_pro_ids)){
                return array(0,"没有可以使用此优惠券的商品");
            }
            if($dp_card_money < $card['card_money']){
                return array(0,"可用券的商品总金额低于优惠券抵扣金额");
            }
            if ($card['order_money_limit'] && $dp_card_money < $card['order_money_limit']) {
                return array(0, "可用券的商品总金额未满足优惠券使用条件");
            }
        }

        // $cart_pro_ids = array();
        // if($cart_products){
        //     $cart_product_amount = array();
        //     foreach ($cart_products as $product) {
        //         $cart_pro_ids[] = $product['product_id'];
        //         if($product['card_limit'] == 1){
        //             return array(0,"购买商品中有不可使用优惠券商品");
        //         }
        //         $cart_product_amount[$product['product_id']] = $product['amount'];
        //     }
        // }

        // if($card['product_id']){
        //     $c_ps = explode(',', $card['product_id']);
        //     if($c_ps){
        //         $_arr = array_intersect($cart_pro_ids,$c_ps);
        //         if(empty($_arr)){
        //             return array(0,"购买商品中无优惠券指定商品");
        //         }else{
        //             $dp_card_money = 0;
        //             foreach ($_arr as $card_pro_id) {
        //                 $dp_card_money = bcadd($dp_card_money, isset($cart_product_amount[$card_pro_id]) ? $cart_product_amount[$card_pro_id] : 0 , 2);
        //             }

        //             if($dp_card_money < $card['card_money']){
        //                 return array(0,"购物车中优惠券指定商品金额小于优惠券金额");
        //             }
        //         }
        //     }
        // }

        if(($goods_money-$jf_money-$pay_discount) < $card['card_money']){
            return array(0, "购买商品总金额低于优惠券金额");
        }
        
        if(!empty($card['channel'])){
            $channel = unserialize($card['channel']);
            $request_channel = 0;
            switch ($source) {
                case 'pc':
                    $request_channel = 1;
                    break;
                case 'app':
                    $request_channel = 2;
                    break;
                case 'wap':
                    $request_channel = 3;
                    break;
                default:
                    $request_channel = 0;
                    break;
            }
            if(!(is_array($channel) && count($channel) == 1 && $channel[0] == 0) && $request_channel!=0 && !in_array($request_channel,$channel)){
                $msg_str = "";
                foreach($channel as $val){
                    $msg_arr[]= $this->card_channel[$val];
                }
                $msg_str = join(",",$msg_arr);
                return array(0, '该优惠券仅限'.$msg_str.'使用');
            }
        }

        switch ($source) {
            case 'pc':
                if($card['promotion_type'] == 2){
                    return array(0, '该优惠券仅限线下使用');
                }
                break;
            case 'app':
                if($card['promotion_type'] == 2){
                    return array(0, '该优惠券仅限线下使用');
                }
                break;
            case 'wap':
                if($card['promotion_type'] == 2){
                    return array(0, '该优惠券仅限线下使用');
                }
                break;
            case 'pos':
                if($card['promotion_type'] == 1){
                    return array(0, '该优惠券仅限线上使用');
                }
                break;
            default:
                # code...
                break;
        }

        if ($card['order_money_limit'] && ($goods_money-$jf_money-$pay_discount) < $card['order_money_limit']) {
            return array(0, "订单满" . $card['order_money_limit'] . "元该优惠券才能使用");
        }
        // check used times
        if ($card['max_use_times']) {
            if (($card['used_times'] > 0) && ($card['used_times'] >= $card['max_use_times'])) {
                return array(0, "该卡已经被使用");
            } 
        }
        if ($card['is_used'] == 1) {
            return array(0, "该卡已经被使用");
        }
        // check card start date
         $exr_arr=array("双11官网红包","双11官网红包(满188使用)","双11官网红包(满258使用)","双11官网红包(满300使用仅限app)");
         if (strcmp($card['time'], date('Y-m-d')) > 0) {
            if(in_array($card['remarks'],$exr_arr)){
                return array(0, "此券仅可在双十一当天使用");
            }else
                return array(0, "该优惠券有效期为".$card['time']."至".$card['to_date']);
        } 
        // check expired date
        if (strcmp($card['to_date'], date('Y-m-d')) < 0){
            if(in_array($card['remarks'],$exr_arr)){
                return array(0, "此券仅可在双十一当天使用");
            }else
                return array(0, "该优惠券有效期为".$card['time']."至".$card['to_date']);
        }
        if($uid!=''){
            if($uid!=$card['uid'] && $card['uid']!='0' && $card['uid']!=''){
                return array(0,"您登录的帐号不能使用该抵扣码");
            }
        }else{
            return array(0,"请先登录您的帐号");
        }

        
        
        return array(1, '');
    }

    public function data_format($cardlist){
        $card_pros = array();
        foreach ($cardlist as $key => $value) {
            if($value['product_id']){
                $c_ps = explode(',', $value['product_id']);
                $card_pros = array_merge($card_pros,$c_ps);
            }
        }
        $card_pros = array_filter(array_unique($card_pros));
        $p_infos = array();

        if($card_pros){
            $url = CURRENT_VERSION_PRODUCT_API . '/v2' . '/product/productBaseInfo';
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
                    $p_nos[$value['id']] = $value['product_no'];
                }
            } elseif ($code_first == 3 || $code_first == 4) {
                $gateway_response['code'] = '300';
                $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
                $log_tag = 'INFO';
            }
        }
        foreach ($cardlist as $key => &$value) {
            if(empty($value['product_id'])){
                $value['use_range'] = "全站通用(个别商品除外)";
            }else{
                $value['card_product_id'] = $value['product_id'];
                $c_ps = explode(',', $value['product_id']);
                $curr_range = array();
                $curr_range_no = array();
                foreach ($c_ps as $v) {
                    $curr_range[] = isset($p_infos[$v])?$p_infos[$v]:'';
                    $curr_range_no[] = isset($p_nos[$v])?$p_nos[$v]:'';
                }
                $value['use_range'] = "仅限" . join(",", $curr_range) . "使用";
                $value['use_product_no'] = array_unique($curr_range_no);
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
        }
        return $cardlist;
    }

    public function cardBaseInfoCanUse($card,$source){
        if (empty($card)) {
            return array(0, '卡号无效');
        }
        if ($card['is_sent'] == 0) {
            return array(0, "卡号无效");
        }
        if($card['maketing']==6){
            if ($card['card_discount'] == 0.00) {
                return array(0, '该卡折扣为0，不能抵扣');
            }
        }else{
            if ($card['card_money'] <= 0) {
                return array(0, '该卡价值为0，不能抵扣');
            }
        }
        if(!empty($card['channel'])){
            $channel = unserialize($card['channel']);
            $request_channel = 0;
            switch ($source) {
                case 'pc':
                    $request_channel = 1;
                    break;
                case 'app':
                    $request_channel = 2;
                    break;
                case 'wap':
                    $request_channel = 3;
                    break;
                default:
                    $request_channel = 0;
                    break;
            }
            if(!(is_array($channel) && count($channel) == 1 && $channel[0] == 0) && $request_channel!=0 && !in_array($request_channel,$channel)){
                $msg_str = "";
                foreach($channel as $val){
                    $msg_arr[]= $this->card_channel[$val];
                }
                $msg_str = join(",",$msg_arr);
                return array(0, '该优惠券仅限'.$msg_str.'使用');
            }
        }
        switch ($source) {
            case 'pc':
                if($card['promotion_type'] == 2){
                    return array(0, '该优惠券仅限线下使用');
                }
                break;
            case 'app':
                if($card['promotion_type'] == 2){
                    return array(0, '该优惠券仅限线下使用');
                }
                break;
            case 'wap':
                if($card['promotion_type'] == 2){
                    return array(0, '该优惠券仅限线下使用');
                }
                break;
            case 'pos':
                if($card['promotion_type'] == 1){
                    return array(0, '该优惠券仅限线上使用');
                }
                break;
            default:
                # code...
                break;
        }
        if ($card['max_use_times']) {
            if (($card['used_times'] > 0) && ($card['used_times'] >= $card['max_use_times'])) {
                return array(0, "该卡已经被使用");
            } 
        }
        if ($card['is_used'] == 1) {
            return array(0, "该卡已经被使用");
        }

         if (strcmp($card['time'], date('Y-m-d')) > 0) {
            return array(0, "该优惠券有效期为".$card['time']."至".$card['to_date']);
        } 

        if (strcmp($card['to_date'], date('Y-m-d')) < 0){
            return array(0, "该优惠券有效期为".$card['time']."至".$card['to_date']);
        }
        return array(1,'');
    }
}