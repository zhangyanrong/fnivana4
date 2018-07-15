<?php

class Delivery extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->config('service');
        $this->orderServiceUrl = $this->config->item('order', 'service') . '/v1';

        $this->load->library('restclient');
        $this->load->library('fruit_log');
        $this->load->helper('public');

        // 用于记录日志用
        $this->requestId = date('Y_m_d_H_i_s') . '_' . md5($this->input->get_post('device_id') . mt_rand(10000, 99999));
    }

    /**
     * @api              {post} / 获取配送信息
     * @apiDescription   根据订单号，获取配送信息
     * @apiGroup         delivery
     * @apiName          detail
     *
     * @apiParam {String} orderNo 订单号
     * @apiParam {Integer} [queryType] 查询类型，0 基础数据; 1 基础+扩展; 2 基础+GPS; 3 基础+扩展+GPS; 4 扩展信息; 5 GPS信息;
     * @apiParam {String} [empId] 配送员ID，queryType 4 或 5 时，必要参数
     *
     * @apiSampleRequest /app/v2?source=app&timestamp=1501724986&platform=IOS&service=delivery.detail&version=5.5.0
     */
    public function detail()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/delivery/detail';
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

    /**
     * @api              {post} / 配送评论
     * @apiDescription   配送评论
     * @apiGroup         delivery
     * @apiName          raisalSave
     *
     * @apiParam {String} [appraisePerson] 评论人
     * @apiParam {String} [appraiseTime] 评价日期
     * @apiParam {String} [empId] 配送员编号
     * @apiParam {String} empName 配送员姓名
     * @apiParam {String} [empNumber] 配送员工号
     * @apiParam {String} orderNo 外部订单号
     * @apiParam {String} [siteCode] 站点
     * @apiParam {String} starAppraisal 星级评价
     * @apiParam {String} [tagAppraisal]  标签评价，eg：服装整洁,提前联系
     * @apiParam {String} [textAppraisal] 文字评价
     *
     * @apiSampleRequest /app/v2?source=app&timestamp=1501724986&platform=IOS&service=delivery.raisalSave&version=5.5.0
     */
    public function raisalSave()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/delivery/raisalSave';
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
