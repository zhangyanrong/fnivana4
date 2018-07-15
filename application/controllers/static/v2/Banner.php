<?php

class Banner extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->config('service');
        $this->bannerServiceUrl = $this->config->item('banner', 'service') . '/v1';

        $this->load->library('restclient');
        $this->load->library('fruit_log');
        $this->load->helper('public');

        // 用于记录日志用
        $this->requestId = date('Y_m_d_H_i_s') . '_' . md5(json_encode($_REQUEST) . mt_rand(10000, 99999));
    }
    /**
     * @api {post} / 热销商品
     * @apiDescription 获取当前所在位置门店的热销商品
     * @apiGroup banner
     * @apiName getTopSeller
     *
     * @apiParam {String} [store_id_list] 门店ID，多个用英文逗号分隔，type=3 时必须
     * @apiParam {String} [tms_region_type] 配送类型
     *
     * @apiSampleRequest /static/v2?service=banner.getTopSeller
     */
    public function getTopSeller()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->bannerServiceUrl . '/banner/getTopSeller';
        $request = http_build_query(array_merge($this->getUserInfo(), $this->input->get(), $this->input->post()));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $response = json_decode($result->response, true);
//        $this->response = ['code' => 200, 'data' => [$url, $this->input->get(), $this->input->post(), $code, $response], 'msg' => ''];exit;

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
            $gatewayResponse['code'] = '300';
            $gatewayResponse['msg'] = '获取失败';
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
