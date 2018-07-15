<?php

class Banner extends CI_Controller
{
    private $bannerServiceUrl;
    private $userServiceUrl;
    private $requestId;

    public function __construct()
    {
        parent::__construct();

        $this->load->config('service');
        $this->bannerServiceUrl = $this->config->item('banner', 'service') . '/v1';
        $this->userServiceUrl = $this->config->item('user', 'service') . '/v1';

        $this->load->library('restclient');
        $this->load->library('fruit_log');
        $this->load->helper('public');
        $this->load->library('api_process');

        // 用于记录日志用
        $this->requestId = date('Y_m_d_H_i_s') . '_' . md5($this->input->get_post('device_id') . mt_rand(10000, 99999));
    }

    public function __destruct()
    {
        if (isset($this->response) && $this->response) echo json_encode($this->response, JSON_UNESCAPED_UNICODE);
        if (!function_exists("fastcgi_finish_request")) {
            function fastcgi_finish_request() {
            }//为windows兼容
        }
        fastcgi_finish_request();
        $this->fruit_log->save();
    }

    /**
     * @api {post} / Mobile 首页广告
     * @apiDescription 获取移动端首页广告Tab、分组和图片信息
     * @apiGroup banner
     * @apiName mobileHomepage
     *
     * @apiParam {Number} type 请求的情况类型
     * @apiParam {String} [connect_id] 登录标识
     * @apiParam {String} [lonlat] 经纬度，type=0|2 时必须
     * @apiParam {String} [district_code] 区域代码，type=0|2 时必须
     * @apiParam {String} [store_id_list] 门店ID，多个用英文逗号分隔，type=3 时必须
     * @apiParam {Number} [tab_id=1] TabID
     *
     * @apiSampleRequest /app/v2?source=app&timestamp=&platform=IOS&service=banner.mobileHomepage&version=5.5.0
     */
    public function mobileHomepage()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $params['url'] = $url = $this->bannerServiceUrl . '/banner/mobileHomepage';
        $params['data'] = array_merge($this->getUserInfo(), $this->input->get(), $this->input->post());
        $params['method'] = 'post';
        $response = $this->api_process->process($params);
        $result = $this->api_process->get('result');
        $code = $result->info->http_code;

        $gatewayResponse = [];
        if ($code == 200) {
            $gatewayResponse['code']    = '200';
            $gatewayResponse['msg']     = '';
            $gatewayResponse['data']    = $response;
        } else {
            $gatewayResponse['code']    = '300';
            $gatewayResponse['msg']     = $response['msg'];
        }

        $this->response = $gatewayResponse;
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

    /**
     * @api {post} / 获取某个位置的广告
     * @apiDescription 获取某个位置的广告
     * @apiGroup banner
     * @apiName getPositionBanner
     *
     * @apiParam {String} store_id_list 门店ID，多个用英文逗号分隔
     * @apiParam {String} position 位置ID
     * @apiParam {String} [user_rank] 用户等级
     * @apiParam {String} source 来源
     * @apiParam {String} platform 平台
     *
     * @apiSampleRequest /app/v2?source=app&timestamp=&platform=IOS&service=banner.getPositionBanner&version=5.5.0&user_rank=1
     */
    public function getPositionBanner()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $params['url'] = $url = $this->bannerServiceUrl . '/banner/getPositionBanner';
        $params['data'] = array_merge($this->getUserInfo(), $this->input->get(), $this->input->post());
        $params['method'] = 'post';
        $response = $this->api_process->process($params);
        $result = $this->api_process->get('result');
        $code = $result->info->http_code;

        $gatewayResponse = [];
        if ($code == 200) {
            $gatewayResponse['code']    = '200';
            $gatewayResponse['msg']     = '';
            $gatewayResponse['data']    = $response;
        } else {
            $gatewayResponse['code']    = '300';
            $gatewayResponse['msg']     = $response['msg'];
        }

        $this->response = $gatewayResponse;
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
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
     * @apiSampleRequest /app/v2?service=banner.getTopSeller
     */
    public function getTopSeller()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $params['url'] = $url = $this->bannerServiceUrl . '/banner/getTopSeller';
        $params['data'] = array_merge($this->getUserInfo(), $this->input->get(), $this->input->post());
        $params['method'] = 'post';
        $response = $this->api_process->process($params);
        $result = $this->api_process->get('result');
        $code = $result->info->http_code;

        $gatewayResponse = [];
        if ($code == 200) {
            $gatewayResponse['code']    = '200';
            $gatewayResponse['msg']     = '';
            $gatewayResponse['data']    = $response;
        } else {
            $gatewayResponse['code']    = '300';
            $gatewayResponse['msg']     = $response['msg'];
        }

        $this->response = $gatewayResponse;
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

    /**
     * @api {post} / 用户推荐产品
     * @apiDescription 从 BI 获取用户的推荐产品
     * @apiGroup banner
     * @apiName setUserRecdSkus
     *
     * @apiParam {String} [connect_id] 登录标识
     *
     * @apiSampleRequest /app/v2?service=banner.setUserRecdSkus
     */
    public function setUserRecdSkus()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $params['url'] = $url = $this->bannerServiceUrl . '/banner/setUserRecdSkus';
        $params['data'] = array_merge($this->getUserInfo(), $this->input->get(), $this->input->post());
        $params['method'] = 'post';
        $response = $this->api_process->process($params);
        $result = $this->api_process->get('result');
        $code = $result->info->http_code;

        $gatewayResponse = [];
        if ($code == 200) {
            $gatewayResponse['code']    = '200';
            $gatewayResponse['msg']     = '';
            $gatewayResponse['data']    = $response;
        } else {
            $gatewayResponse['code']    = '300';
            $gatewayResponse['msg']     = $response['msg'];
        }

        $this->response = $gatewayResponse;
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

    private function getUserInfo()
    {
        static $userInfo = null;
        if (isset($userInfo)) {
            return $userInfo;
        }

        $userInfo = ['utype' => 'unregistered', 'uid' => 0];
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
                    $userInfo['utype'] = ($response['is_new'] == '1') ? 'register_no_buy' : ('v' . ($response['user_rank'] - 1));
                }
            }
        }

        return $userInfo;
    }

    /**
     * @api {post} / 获取用户推荐商品广告
     * @apiDescription 获取用户推荐商品广告
     * @apiGroup banner
     * @apiName getBiBanner
     *
     * @apiParam {String} connect_id 登录标识
     * @apiParam {String} store_id_list 门店ID，多个用英文逗号分隔
     *
     * @apiSampleRequest /app/v2?service=banner.getBiBanner&source=app&timestamp=&platform=IOS&version=5.9.3
     */
    public function getBiBanner()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $params['url'] = $url = $this->bannerServiceUrl . '/banner/getBiBanner';
        $params['data'] = array_merge(['uid' => get_uid()], $this->input->get(), $this->input->post());
        $params['method'] = 'post';
        $response = $this->api_process->process($params);
        $result = $this->api_process->get('result');
        $code = $result->info->http_code;

        $gatewayResponse = [];
        if ($code == 200) {
            $gatewayResponse['code']    = '200';
            $gatewayResponse['msg']     = '';
            $gatewayResponse['data']    = $response;
        } else {
            $gatewayResponse['code']    = '300';
            $gatewayResponse['msg']     = $response['msg'];
        }

        $this->response = $gatewayResponse;
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

    /**
     * @api {post}  /   获取GPS地址
     * @apiDescription  获取GPS地址？
     * @apiGroup    banner
     * @apiName     get_gps_address
     *
     * @apiParam    {String}    connect_id  登录标识
     * @apiParam    {String}    lonlat      经纬度
     *
     * @apiSampleRequest /app/v2?service=banner.get_gps_address
     */
    public function get_gps_address() {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);
        $url = $this->bannerServiceUrl . '/deliver/getGpsAddress?request_id=' . $this->requestId;
        $query['uid']       = get_uid();
        $query['lonlat']    = require_request('lonlat');
        $params['url']      = $url;
        $params['data']     = $query;
        $params['method']   = 'get';
        $service_response = $this->api_process->process($params);
        $response_result = $this->api_process->get('result');
        if ($response_result->info->http_code == 200) {
            $api_gateway_response['code']   = '200';
            $api_gateway_response['data']   = $service_response;
        } else {
            $api_gateway_response['code']   = '300';
            $api_gateway_response['msg']    = $service_response['msg'];
        }
        $this->response = $api_gateway_response;
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }
}
