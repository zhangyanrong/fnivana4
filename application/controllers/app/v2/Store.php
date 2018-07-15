<?php

class Store extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->config('service');
        $this->bannerServiceUrl = $this->config->item('banner', 'service') . '/v2';

        $this->load->library('restclient');
        $this->load->library('fruit_log');
        $this->load->helper('public');

        // 用于记录日志用
        $this->requestId = date('Y_m_d_H_i_s') . '_' . md5($this->input->get_post('device_id') . mt_rand(10000, 99999));
    }

    /**
     * @api {post} / 获取开启的门店信息列表
     * @apiDescription 获取开启的门店信息列表
     * @apiGroup store
     * @apiName getList
     *
     * @apiSampleRequest /app/v2?service=store.getList
     */
    public function getList()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->bannerServiceUrl . '/store/getList';
        $request = http_build_query(array_merge($this->input->get(), $this->input->post()));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $response = json_decode($result->response, true);

        $codeFirst = substr($code, 0, 1);
        $logContent['id'] = $this->requestId;
        $logContent['request']['url'] = $url;
        $logContent['request']['content'] = $request;
        $logContent['response']['code'] = $code;
        $logContent['response']['content'] = $response;
        $gatewayResponse = [];
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
            $gatewayResponse['msg'] = isset($response['msg']) ? $response['msg'] : '获取失败';
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
     * @api {post} / 获取门店配送区域
     * @apiDescription 获取门店配送区域
     * @apiGroup store
     * @apiName getArea
     *
     * @apiParam {String} store_id 门店ID
     *
     * @apiSampleRequest /app/v2?service=store.getArea
     */
    public function getArea()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->bannerServiceUrl . '/store/getArea';
        $request = http_build_query(array_merge($this->input->get(), $this->input->post()));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $response = json_decode($result->response, true);

        $codeFirst = substr($code, 0, 1);
        $logContent['id'] = $this->requestId;
        $logContent['request']['url'] = $url;
        $logContent['request']['content'] = $request;
        $logContent['response']['code'] = $code;
        $logContent['response']['content'] = $response;
        $gatewayResponse = [];
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
            $gatewayResponse['msg'] = isset($response['msg']) ? $response['msg'] : '获取失败';
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
}
