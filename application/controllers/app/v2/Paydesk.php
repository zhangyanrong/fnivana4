<?php

class Paydesk extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->config('service');
        $this->orderServiceUrl = $this->config->item('order', 'service') . '/v1';
        $this->userServiceUrl = $this->config->item('user', 'service') . '/v1';

        $this->load->library('restclient');
        $this->load->library('fruit_log');
        $this->load->helper('public');

        // 用于记录日志用
        $this->requestId = date('Y_m_d_H_i_s') . '_' . md5($this->input->get_post('device_id') . mt_rand(10000, 99999));
    }

    /**
     * @api              {post} / 初始化收银台
     * @apiDescription   初始化收银台
     * @apiGroup         paydesk
     * @apiName          init
     *
     * @apiParam {String} connect_id    登录TOKEN
     * @apiParam {String} order_name    订单号
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] 版本
     * @apiParam {String} [platform] 平台
     * @apiParam {String} [channel] 渠道 wechat/alipay
     *
     * @apiSampleRequest /app/v2?service=paydesk.init&source=app
     */
    public function init()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/paydesk/init';
        $request = http_build_query(array_merge($this->getUserInfo(),$this->input->get(), $this->input->post()));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $response = json_decode($result->response, true);

        $codeFirst = substr($code, 0, 1);
        $logContent['id'] = $this->requestId;
        $logContent['request']['url'] = $url;
        $logContent['request']['content'] = $request;
        $logContent['response']['code'] = $code;
        $logContent['response']['content'] = $response;
        if ($codeFirst == 5 || !$response) {
            $logTag = 'ERROR';
            $logContent['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($codeFirst == 2 && $response) {
            $gatewayResponse['code'] = '200';
            $gatewayResponse['msg'] = '';
            $gatewayResponse['data'] = $response;
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = $response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

    /**
     * @api              {post} / 使用余额
     * @apiDescription   使用余额
     * @apiGroup         paydesk
     * @apiName          useBalance
     *
     * @apiParam {String} [uid] 用户编号
     * @apiParam {String} [order_name] 订单号
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] 版本
     * @apiParam {String} [platform] 平台
     * @apiParam {String} [channel] 渠道 wechat/alipay
     *
     * @apiSampleRequest /app/v2?service=paydesk.useBalance&source=app
     */
    public function useBalance()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/paydesk/useBalance';
        $request = http_build_query(array_merge($this->getUserInfo(),$this->input->get(), $this->input->post()));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $response = json_decode($result->response, true);

        $codeFirst = substr($code, 0, 1);
        $logContent['id'] = $this->requestId;
        $logContent['request']['url'] = $url;
        $logContent['request']['content'] = $request;
        $logContent['response']['code'] = $code;
        $logContent['response']['content'] = $response;
        if ($codeFirst == 5 || !$response) {
            $logTag = 'ERROR';
            $logContent['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($codeFirst == 2 && $response) {
            $gatewayResponse['code'] = '200';
            $gatewayResponse['msg'] = '';
            $gatewayResponse['data'] = $response;
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = $response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

    /**
     * @api              {post} / 取消使用余额
     * @apiDescription   取消使用余额
     * @apiGroup         paydesk
     * @apiName          cancelUseBalance
     *
     * @apiParam {String} [uid] 用户编号
     * @apiParam {String} [order_name] 订单号
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] 版本
     * @apiParam {String} [platform] 平台
     * @apiParam {String} [channel] 渠道 wechat/alipay
     *
     * @apiSampleRequest /app/v2?service=paydesk.cancelUseBalance&source=app
     */
    public function cancelUseBalance()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/paydesk/cancelUseBalance';
        $request = http_build_query(array_merge($this->getUserInfo(),$this->input->get(), $this->input->post()));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $response = json_decode($result->response, true);

        $codeFirst = substr($code, 0, 1);
        $logContent['id'] = $this->requestId;
        $logContent['request']['url'] = $url;
        $logContent['request']['content'] = $request;
        $logContent['response']['code'] = $code;
        $logContent['response']['content'] = $response;
        if ($codeFirst == 5 || !$response) {
            $logTag = 'ERROR';
            $logContent['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($codeFirst == 2 && $response) {
            $gatewayResponse['code'] = '200';
            $gatewayResponse['msg'] = '';
            $gatewayResponse['data'] = $response;
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = $response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

    /**
     * @api              {post} / 选择支付方式
     * @apiDescription   选择支付方式
     * @apiGroup         paydesk
     * @apiName          choseCostPayment
     *
     * @apiParam {String} [uid] 用户编号
     * @apiParam {String} [order_name] 订单号
     * @apiParam {String} [pay_parent_id] 支付方式
     * @apiParam {String} [pay_id] 支付方式
     * @apiParam {String} [ispc] 是否为官网
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] 版本
     * @apiParam {String} [platform] 平台
     * @apiParam {String} [channel] 渠道 wechat/alipay
     *
     * @apiSampleRequest /app/v2?service=paydesk.choseCostPayment&source=app
     */
    public function choseCostPayment()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/paydesk/choseCostPayment';
        $request = http_build_query(array_merge($this->getUserInfo(),$this->input->get(), $this->input->post()));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $response = json_decode($result->response, true);

        $codeFirst = substr($code, 0, 1);
        $logContent['id'] = $this->requestId;
        $logContent['request']['url'] = $url;
        $logContent['request']['content'] = $request;
        $logContent['response']['code'] = $code;
        $logContent['response']['content'] = $response;
        if ($codeFirst == 5 || !$response) {
            $logTag = 'ERROR';
            $logContent['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($codeFirst == 2 && $response) {
            $gatewayResponse['code'] = '200';
            $gatewayResponse['msg'] = '';
            $gatewayResponse['data'] = $response;
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = $response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

    /**
     * @api              {post} / 验证支付
     * @apiDescription   验证支付
     * @apiGroup         paydesk
     * @apiName          checkPay
     *
     * @apiParam {String} [uid] 用户编号
     * @apiParam {String} [order_name] 订单号
     * @apiParam {String} [need_online_pay] 线上支付金额
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] 版本
     * @apiParam {String} [platform] 平台
     * @apiParam {String} [channel] 渠道 wechat/alipay
     *
     * @apiSampleRequest /app/v2?service=paydesk.checkPay&source=app
     */
    public function checkPay()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/paydesk/checkPay';
        $request = http_build_query(array_merge($this->getUserInfo(),$this->input->get(), $this->input->post()));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $response = json_decode($result->response, true);

        $codeFirst = substr($code, 0, 1);
        $logContent['id'] = $this->requestId;
        $logContent['request']['url'] = $url;
        $logContent['request']['content'] = $request;
        $logContent['response']['code'] = $code;
        $logContent['response']['content'] = $response;
        if ($codeFirst == 5 || !$response) {
            $logTag = 'ERROR';
            $logContent['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($codeFirst == 2 && $response) {
            $gatewayResponse['code'] = '200';
            $gatewayResponse['msg'] = '';
            $gatewayResponse['data'] = $response;
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = $response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

    /**
     * @api              {post} / 余额支付验证码
     * @apiDescription   余额支付验证码
     * @apiGroup         paydesk
     * @apiName          checkBalanceCode
     *
     * @apiParam {String} [uid] 用户编号
     * @apiParam {String} [order_name] 订单号
     * @apiParam {String} [verification_code] 验证码
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] 版本
     * @apiParam {String} [platform] 平台
     * @apiParam {String} [channel] 渠道 wechat/alipay
     *
     * @apiSampleRequest /app/v2?service=paydesk.checkBalanceCode&source=app
     */
    public function checkBalanceCode()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/paydesk/checkBalanceCode';
        $request = http_build_query(array_merge($this->getUserInfo(),$this->input->get(), $this->input->post()));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $response = json_decode($result->response, true);

        $codeFirst = substr($code, 0, 1);
        $logContent['id'] = $this->requestId;
        $logContent['request']['url'] = $url;
        $logContent['request']['content'] = $request;
        $logContent['response']['code'] = $code;
        $logContent['response']['content'] = $response;
        if ($codeFirst == 5 || !$response) {
            $logTag = 'ERROR';
            $logContent['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($codeFirst == 2 && $response) {
            $gatewayResponse['code'] = '200';
            $gatewayResponse['msg'] = '';
            $gatewayResponse['data'] = $response;
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = $response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

    /**
     * @api              {post} / 支付成功
     * @apiDescription   支付成功
     * @apiGroup         paydesk
     * @apiName          orderSuccess
     *
     * @apiParam {String} [uid] 用户编号
     * @apiParam {String} [order_name] 订单号
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] 版本
     * @apiParam {String} [platform] 平台
     * @apiParam {String} [channel] 渠道 wechat/alipay
     *
     * @apiSampleRequest /app/v2?service=paydesk.orderSuccess&source=app
     */
    public function orderSuccess()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/paydesk/orderSuccess';
        $request = http_build_query(array_merge($this->getUserInfo(),$this->input->get(), $this->input->post()));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $response = json_decode($result->response, true);

        $codeFirst = substr($code, 0, 1);
        $logContent['id'] = $this->requestId;
        $logContent['request']['url'] = $url;
        $logContent['request']['content'] = $request;
        $logContent['response']['code'] = $code;
        $logContent['response']['content'] = $response;
        if ($codeFirst == 5 || !$response) {
            $logTag = 'ERROR';
            $logContent['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($codeFirst == 2 && $response) {
            $gatewayResponse['code'] = '200';
            $gatewayResponse['msg'] = '';
            $gatewayResponse['data'] = $response;
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = $response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

    public function __destruct()
    {
        echo json_encode($this->response, JSON_UNESCAPED_UNICODE);
        if (!function_exists("fastcgi_finish_request")) {
            function fastcgi_finish_request() {
            }//为windows兼容
        }
        fastcgi_finish_request();
        $this->fruit_log->save();
    }

    private function getUserInfo()
    {
        static $userInfo = null;
        if (isset($userInfo)) {
            return $userInfo;
        }

        $userInfo = ['utype' => 'v0', 'uid' => 0];
        $connectId = $this->input->get_post('connect_id');
        if ($connectId) {
            session_id($connectId);
            session_start();
//            $userInfo['utype'] = isset($_SESSION['user_detail']['user_rank']) ? 'v' . ($_SESSION['user_detail']['user_rank'] - 1) : 'v0';
            $uid = isset($_SESSION['user_detail']['id']) ? $_SESSION['user_detail']['id'] : 0;
            session_write_close();//增加使用完session后释放session资源

            if ($uid) {
                $url = $this->userServiceUrl . '/user/' . $uid;
                $result = $this->restclient->get($url);
                $code = $result->info->http_code;
                $response = json_decode($result->response, true);
                if (200 == $code) {
                    $userInfo['uid'] = $response['id'];
                    $userInfo['utype'] = 'v' . ($response['user_rank'] - 1);
                }
            }
        }

        return $userInfo;
    }
}
