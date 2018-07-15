<?php

class Express extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->library('restclient');
        $this->load->library('fruit_log');
        $this->load->helper('public');

        $this->load->config('service');
        $this->orderServiceUrl = $this->config->item('order', 'service') . '/v1';
        $this->userServiceUrl = $this->config->item('user', 'service') . '/v1';

        // 用于记录日志用
        $this->requestId = date('Y_m_d_H_i_s') . '_' . md5($this->input->get_post('device_id') . mt_rand(10000, 99999));
    }

    /**
     * @api              {post} / 创建运费订单
     * @apiDescription   创建运费订单
     * @apiGroup         express
     * @apiName          createOrder
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [invoice_id] 发票id
     * @apiParam {String} [pay_invoice_type] 发票类型 1-非增值税发票 2-增值税发票
     *
     * @apiSampleRequest /app/v2?service=express.createOrder&source=app
     */
    public function createOrder()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/express/createOrder';
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
}
