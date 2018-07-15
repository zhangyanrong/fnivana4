<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class Poolhash {

    public function create_sign($params, $secret){
        $sign = '';
        if(!empty($params) && is_array($params)){
            ksort($params);
            foreach($params as $k => $v){
                $sign .= $k . $v;
            }
            $sign = md5($secret . md5($sign) . $secret);
        }

        return $sign;
    }

    public function check_sign($params, $sign, $secret)
    {
        return $sign == $this->create_sign($params, $secret);
    }
}



