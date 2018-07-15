<?php

class Order extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->config('service');
        $this->orderServiceUrl = $this->config->item('order', 'service') . '/v1';
        $this->userServiceUrl = $this->config->item('user', 'service') . '/v1';

        $this->load->library('restclient');
        $this->load->library('fruit_log');
        $this->load->library('api_process');
        $this->load->helper('public');

        // 用于记录日志用
        $this->requestId = date('Y_m_d_H_i_s') . '_' . md5($this->input->get_post('device_id') . mt_rand(10000, 99999));
    }

    /**
     * @api {post} / 取消合并补开发票
     * @apiDescription   取消合并补开发票
     * @apiGroup         order
     * @apiName          cancelUnitInvoice
     *
     * @apiParam {String} connect_id    登录成功后返回的connect_id
     * @apiParam {String} invoice_id    发票ID号
     *
     * @apiSampleRequest /app/v2?service=order.cancelUnitInvoice
     */
    public function cancelUnitInvoice() {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);
        $service_order_query['invoice_id']  = require_request('invoice_id');
        $service_order_query['uid']         = get_uid();
        $params['url'] = $this->config->item('order', 'service') . '/v1/invoice/cancel_unit_invoice?request_id=' . $this->requestId;
        $params['data']	= $service_order_query;
        $params['method'] = 'get';
        $service_order_response = $this->api_process->process($params);
        
        $response_result = $this->api_process->get('result');
        if ($response_result->info->http_code == 200) {
            $api_gateway_response['code']   = '200';
            $api_gateway_response['msg']    = '取消合并补开票成功';
        } else {
            $api_gateway_response['code']   = '300';
            $api_gateway_response['msg']    = $service_order_response['msg'];
        }
        $this->response = $api_gateway_response;
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }
    
    /**
     * @api              {post} / 获取配送中订单
     * @apiDescription   获取配送中订单
     * @apiGroup         order
     * @apiName          deliverOrder
     *
     * @apiParam {String} [connect_id] connect_id
     *
     * @apiSampleRequest /app/v2?service=order.deliverOrder&source=app
     */
    public function deliverOrder()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/deliverOrder';
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
            $gatewayResponse['msg'] =$response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

    public function fpCommunalData()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);
        $uid = $this->getUserInfo();
        if(empty($uid)) {
            $this->response = ['code' => 300, 'data' => (object)array(), 'msg' => '登录超时'];
        }
        $url = $this->orderServiceUrl . '/invoice/fpCommunalData';
        $request = http_build_query(array_merge($uid,$this->input->get(), $this->input->post()));
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
            $gatewayResponse['msg'] =$response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

    public function getCanApplyInvoiceList()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);
        $uid = $this->getUserInfo();
        if(empty($uid)) {
            $this->response = ['code' => 300, 'data' => (object)array(), 'msg' => '登录超时'];
        }
        $url = $this->orderServiceUrl . '/invoice/getCanApplyInvoiceList';
        $request = http_build_query(array_merge($uid,$this->input->get(), $this->input->post()));
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
            $gatewayResponse['msg'] =$response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

    public function replenishmentCheck()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);
        $uid = $this->getUserInfo();
        if(empty($uid)) {
            $this->response = ['code' => 300, 'data' => (object)array(), 'msg' => '登录超时'];
        }
        $url = $this->orderServiceUrl . '/invoice/replenishmentCheck';
        $request = http_build_query(array_merge($uid,$this->input->get(), $this->input->post()));
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
            $gatewayResponse['msg'] =$response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }




    public function invoiceDetail()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);
        $uid = $this->getUserInfo();
        if(empty($uid)) {
            $this->response = ['code' => 300, 'data' => (object)array(), 'msg' => '登录超时'];
        }
        $url = $this->orderServiceUrl . '/invoice/invoiceDetail';
        $request = http_build_query(array_merge($uid,$this->input->get(), $this->input->post()));
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
            $gatewayResponse['msg'] =$response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

    public function invoiceHistory()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);
        $uid = $this->getUserInfo();
        if(empty($uid)) {
            $this->response = ['code' => 300, 'data' => (object)array(), 'msg' => '登录超时'];
        }
        $url = $this->orderServiceUrl . '/invoice/invoiceHistory';
        $request = http_build_query(array_merge($uid,$this->input->get(), $this->input->post()));
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
            $gatewayResponse['msg'] =$response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

    public function useUnitedInvoice()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);
        $uid = $this->getUserInfo();
        if(empty($uid)) {
            $this->response = ['code' => 300, 'data' => (object)array(), 'msg' => '登录超时'];
        }
        $url = $this->orderServiceUrl . '/invoice/useUnitedInvoice';
        $request = http_build_query(array_merge($uid,$this->input->get(), $this->input->post()));
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
            $gatewayResponse['msg'] =$response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }


    public function createAddedTaxQualification()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);
        $uid = $this->getUserInfo();
        if(empty($uid)) {
            $this->response = ['code' => 300, 'data' => (object)array(), 'msg' => '登录超时'];
        }
        $url = $this->orderServiceUrl . '/invoice/createAddedTaxQualification';
        $request = http_build_query(array_merge($uid,$this->input->get(), $this->input->post()));
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
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }


    public function getAddedTaxInvoiceQualification()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);
        $uid = $this->getUserInfo();
        if(empty($uid)) {
            $this->response = ['code' => 300, 'data' => (object)array(), 'msg' => '登录超时'];
        }
        $url = $this->orderServiceUrl . '/invoice/getAddedTaxInvoiceQualification';
        $request = http_build_query(array_merge($uid,$this->input->get(), $this->input->post()));
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
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }


    public function createAddedTaxInvoice()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);
        $uid = $this->getUserInfo();
        if(empty($uid)) {
            $this->response = ['code' => 300, 'data' => (object)array(), 'msg' => '登录超时'];
        }
        $url = $this->orderServiceUrl . '/invoice/createAddedTaxInvoice';
        $request = http_build_query(array_merge($uid,$this->input->get(), $this->input->post()));
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
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

    public function delAddedTaxInvoice()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);
        $uid = $this->getUserInfo();
        if(empty($uid)) {
            $this->response = ['code' => 300, 'data' => (object)array(), 'msg' => '登录超时'];
        }
        $url = $this->orderServiceUrl . '/invoice/delAddedTaxInvoice';
        $request = http_build_query(array_merge($uid,$this->input->get(), $this->input->post()));
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
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

    public function updateAddedTaxQualification()
    {
        if (class_exists('CURLFile')) {
            $ch = curl_init($this->orderServiceUrl . '/invoice/updateAddedTaxQualification');

            foreach ($_FILES as $fl) {
                $curlFl = new CURLFile(realpath($fl['tmp_name']));
            }

            $params = array_merge($this->input->get(),
                $this->input->post(),
                [
                    'images' => $curlFl, //副本只有1张
                ]
                , $this->getUserInfo()
            );
            curl_setopt_array($ch, [CURLOPT_POST => 1, CURLOPT_POSTFIELDS => $params, CURLOPT_RETURNTRANSFER => true]);
            $o_response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $response = [];
            if ($o_response) {
                $response = json_decode($o_response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $response = $o_response;
                }
            }
            if ($http_code != 200) {
                if (is_array($response) && !empty($response['code'])) {
                    $this->response = ['code' => $response['code'], 'msg' => $response['msg'] ?: '服务器异常,请重试!', 'data' => []];
                } else {
                    $this->response = ['code' => 300, 'msg' => $response ?: '服务器异常,请重试!', 'data' => []];
                }
                return;
            }
            $this->response = ['code' => 200, 'data' => $response, 'msg' => ''];
            return;

        }
    }


    public function getVatInvoiceDetail()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);
        $uid = $this->getUserInfo();
        if(empty($uid)) {
            $this->response = ['code' => 300, 'data' => (object)array(), 'msg' => '登录超时'];
        }
        $url = $this->orderServiceUrl . '/invoice/getVatInvoiceDetail';
        $request = http_build_query(array_merge($uid,$this->input->get(), $this->input->post()));
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
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

    public function vatInvoiceHistory()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);
        $uid = $this->getUserInfo();
        if(empty($uid)) {
            $this->response = ['code' => 300, 'data' => (object)array(), 'msg' => '登录超时'];
        }
        $url = $this->orderServiceUrl . '/invoice/vatInvoiceHistory';
        $request = http_build_query(array_merge($uid,$this->input->get(), $this->input->post()));
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
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }


    public function __destruct() {
        if (isset($this->response) && $this->response) echo json_encode($this->response);
        if (!function_exists("fastcgi_finish_request")) {
            function fastcgi_finish_request() { }//为windows兼容
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

    /**
     * @api              {post} / 删除订单
     * @apiDescription   删除订单
     * @apiGroup         order
     * @apiName          orderHide
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [order_name] order_name
     *
     * @apiSampleRequest /app/v2?service=order.orderHide&source=app
     */
    public function orderHide()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/orderHide';
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
            $gatewayResponse['msg']  = $response;
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
     * @api              {post} / 设置订单状态
     * @apiDescription   设置订单状态
     * @apiGroup         order
     * @apiName          orderPayed
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [order_name] order_name
     *
     * @apiSampleRequest /app/v2?service=order.orderPayed&source=app
     */
    public function orderPayed()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/orderPayed';
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
            $gatewayResponse['msg']  = $response;
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
     * @api              {post} / 再来一单
     * @apiDescription   再来一单
     * @apiGroup         order
     * @apiName          replace
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [order_name] order_name 订单号
     * @apiParam {String} [store_id_list] store_id_list 门店id
     *
     * @apiSampleRequest /app/v2?service=order.replace&source=app
     */
    public function replace()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/replace';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     * @api              {get} / 订单修改地址列表
     * @apiDescription   订单修改地址列表
     * @apiGroup         order
     * @apiName          sendAddrList
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [order_name] order_name 订单号
     *
     * @apiSampleRequest /app/v2?service=order.sendAddrList&source=app
     */
    public function sendAddrList()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/sendAddrList';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     * @api              {post} / 修改订单地址
     * @apiDescription   修改订单地址
     * @apiGroup         order
     * @apiName          changeSendAddr
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [order_name] order_name 订单号
     * @apiParam {String} [address_id] 用户地址编号
     *
     * @apiSampleRequest /app/v2?service=order.changeSendAddr&source=app
     */
    public function changeSendAddr()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/changeSendAddr';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     * @api              {get} / 订单列表
     * @apiDescription   订单列表
     * @apiGroup         order
     * @apiName          orderNewList
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [order_status] 订单状态 －0全部1待付款2待发货3待收货4待评价
     * @apiParam {String} [ctime] 当前时间
     * @apiParam {String} [page] 页码
     * @apiParam {String} [limit] 数量
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.orderNewList&source=app
     */
    public function orderNewList()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/orderNewList';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     * @api              {get} / 查询订单商品
     * @apiDescription   查询订单商品
     * @apiGroup         order
     * @apiName          searchProductOrder
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [product_name] 商品名称
     * @apiParam {String} [ctime] 当前时间
     * @apiParam {String} [page] 页码
     * @apiParam {String} [limit] 数量
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.searchProductOrder&source=app
     */
    public function searchProductOrder()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/searchProductOrder';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     * @api              {post} / 订单物流评价
     * @apiDescription   订单物流评价
     * @apiGroup         order
     * @apiName          orderEval
     *
     * 
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [order_name] 订单编号
     * @apiParam {String} [remark] 评价内容
     * @apiParam {String} [star_word] 整体服务标签 - json格式
     * @apiParam {String} [package_star_word] 包装服务标签 - json格式
     * @apiParam {String} [express_star_word] 物流服务标签 - json格式
     * @apiParam {String} [score_ensemble] 整体服务星级
     * @apiParam {String} [score_package]  包装服务星级
     * @apiParam {String} [score_express]  物流服务星级
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.orderEval&source=app
     */
    public function orderEval()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/orderEval';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     * @api              {post} / 订单商品评论
     * @apiDescription   订单商品评论
     * @apiGroup         order
     * @apiName          doNewComment
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [order_id] 订单id
     * @apiParam {String} [product_id] 商品id
     * @apiParam {String} [star_eat] 口感
     * @apiParam {String} [star_show] 颜值
     * @apiParam {String} [content] 评价内容
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.doNewComment&source=app
     */
    public function doNewComment()
    {
        if (class_exists('CURLFile') && !empty($_FILES)) {
            $ch = curl_init($this->orderServiceUrl . '/order/doNewComment');

            $images = array();
            foreach ($_FILES as $fl) {
                $curlFl = new CURLFile(realpath($fl['tmp_name']), $fl['type'], $fl['name']);
                array_push($images,$curlFl);
                //$curlFl = new CURLFile(realpath($fl['tmp_name']));
            }

            $params = array_merge($this->input->get(),
                $this->input->post(),$this->getUserInfo()
            );
            foreach($images as $k=>$v)
            {
                $key = $k+1;
                $params['images'.$key] = $v;
            }
            curl_setopt_array($ch, [CURLOPT_POST => 1, CURLOPT_POSTFIELDS => $params, CURLOPT_RETURNTRANSFER => true]);
            $o_response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $response = [];
            if ($o_response) {
                $response = json_decode($o_response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $response = $o_response;
                }
            }
            if ($http_code != 200) {
                if (is_array($response) && !empty($response['code'])) {
                    $this->response = ['code' => $response['code'], 'msg' => $response['msg'] ?: '服务器异常,请重试!', 'data' => []];
                } else {
                    $this->response = ['code' => 300, 'msg' => $response ?: '服务器异常,请重试!', 'data' => []];
                }
                return;
            }
            $this->response = ['code' => 200, 'data' => $response, 'msg' => ''];
            return;

        }

        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);
        $url = $this->orderServiceUrl . '/order/doNewComment';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     * @api              {get} / 订单配送时间列表
     * @apiDescription   订单配送时间列表
     * @apiGroup         order
     * @apiName          sendTimeList
     *
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [order_name] 订单编号
     * @apiParam {String} [package_id] 包裹序号
     * @apiParam {String} [source] 来源 app/wap/pc
     *
     * @apiSampleRequest /app/v2?service=order.sendTimeList&source=app
     */
    public function sendTimeList()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/sendTimeList';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     * @api              {post} / 修改订单配送时间
     * @apiDescription   修改订单配送时间
     * @apiGroup         order
     * @apiName          changeSendTime
     *
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [order_name] 订单编号
     * @apiParam {String} [package_id] 包裹序号
     * @apiParam {String} [package_send_times] 包裹时间 - json
     * @apiParam {String} [source] 来源 app/wap/pc
     *
     * @apiSampleRequest /app/v2?service=order.changeSendTime&source=app
     */
    public function changeSendTime()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/changeSendTime';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     * @api              {get} / 获取退款进度
     * @apiDescription   获取退款进度
     * @apiGroup         order
     * @apiName          refundInfo
     *
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [order_name] 订单编号
     * @apiParam {String} [source] 来源 app/wap/pc
     *
     * @apiSampleRequest /app/v2?service=order.refundInfo&source=app
     */
    public function refundInfo()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/refundInfo';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     * @api              {post} / 索取充值单发票
     * @apiDescription   索取充值单发票
     * @apiGroup         order
     * @apiName          useTradeInvoice
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [lonlat] 定位
     * @apiParam {String} [fp] 发票抬头
     * @apiParam {String} [fp_dz] 发票地址
     * @apiParam {String} [fp_id_no] 税号
     * @apiParam {String} [invoice_username] 用户名
     * @apiParam {String} [invoice_mobile] 手机号码
     * @apiParam {String} [invoice_province] 省份
     * @apiParam {String} [invoice_city] 城市
     * @apiParam {String} [invoice_area] 地区
     * @apiParam {String} [trade_number] 充值订单号
     * @apiParam {String} [kp_type] 发票类型
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.useTradeInvoice&source=app
     */
    public function useTradeInvoice()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/useTradeInvoice';
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
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
            $gatewayResponse['data'] = '';
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     * @api              {post} / 使用积分
     * @apiDescription   使用积分
     * @apiGroup         order
     * @apiName          usejf
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [jf] 积分
     * @apiParam {String} [lonlat] 定位
     * @apiParam {String} [area_adcode] 定位地区编码
     * @apiParam {String} [store_id_list] 门店id列表
     * @apiParam {String} [delivery_code] 仓储id
     * @apiParam {String} [self_pick] 自提
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.usejf&source=app
     */
    public function usejf()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/usejf';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     *
     * @api              {post} / 取消积分使用
     * @apiDescription   取消积分使用
     * @apiGroup         order
     * @apiName          cancelUsejf
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [lonlat] 定位
     * @apiParam {String} [area_adcode] 定位地区编码
     * @apiParam {String} [store_id_list] 门店id列表
     * @apiParam {String} [delivery_code] 仓储id
     * @apiParam {String} [self_pick] 自提
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.cancelUsejf&source=app
     */
    public function cancelUsejf()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/cancelUsejf';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     *
     * @api              {post} / 使用积点
     * @apiDescription   使用积点
     * @apiGroup         order
     * @apiName          usejd
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [lonlat] 定位
     * @apiParam {String} [area_adcode] 定位地区编码
     * @apiParam {String} [store_id_list] 门店id列表
     * @apiParam {String} [delivery_code] 仓储id
     * @apiParam {String} [self_pick] 自提
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.usejd&source=app
     */
    public function usejd()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/usejd';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     *
     * @api              {post} / 取消使用积点
     * @apiDescription   取消使用积点
     * @apiGroup         order
     * @apiName          cancelUsejd
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [lonlat] 定位
     * @apiParam {String} [area_adcode] 定位地区编码
     * @apiParam {String} [store_id_list] 门店id列表
     * @apiParam {String} [delivery_code] 仓储id
     * @apiParam {String} [self_pick] 自提
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.cancelUsejd&source=app
     */
    public function cancelUsejd()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/cancelUsejd';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     *
     * @api              {post} / 使用自提
     * @apiDescription   使用自提
     * @apiGroup         order
     * @apiName          useSelfPick
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [lonlat] 定位
     * @apiParam {String} [area_adcode] 定位地区编码
     * @apiParam {String} [store_id_list] 门店id列表
     * @apiParam {String} [delivery_code] 仓储id
     * @apiParam {String} [self_pick] 自提
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.useSelfPick&source=app
     */
    public function useSelfPick()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/useSelfPick';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     *
     * @api              {post} / 取消使用自提
     * @apiDescription   取消使用自提
     * @apiGroup         order
     * @apiName          cancelSelfPick
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [lonlat] 定位
     * @apiParam {String} [area_adcode] 定位地区编码
     * @apiParam {String} [store_id_list] 门店id列表
     * @apiParam {String} [delivery_code] 仓储id
     * @apiParam {String} [self_pick] 自提
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.cancelSelfPick&source=app
     */
    public function cancelSelfPick()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/cancelSelfPick';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     *
     * @api              {post} / 使用优惠券
     * @apiDescription   使用优惠券
     * @apiGroup         order
     * @apiName          useCard
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [card] 优惠券码
     * @apiParam {String} [lonlat] 定位
     * @apiParam {String} [area_adcode] 定位地区编码
     * @apiParam {String} [store_id_list] 门店id列表
     * @apiParam {String} [delivery_code] 仓储id
     * @apiParam {String} [self_pick] 自提
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.useCard&source=app
     */
    public function useCard()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/useCard';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     *
     * @api              {post} / 取消使用优惠券
     * @apiDescription   取消使用优惠券
     * @apiGroup         order
     * @apiName          cancelUseCard
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [lonlat] 定位
     * @apiParam {String} [area_adcode] 定位地区编码
     * @apiParam {String} [store_id_list] 门店id列表
     * @apiParam {String} [delivery_code] 仓储id
     * @apiParam {String} [self_pick] 自提
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.cancelUseCard&source=app
     */
    public function cancelUseCard()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/cancelUseCard';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     *
     * @api              {post} / 订单确认收货
     * @apiDescription   订单确认收货
     * @apiGroup         order
     * @apiName          confirmReceive
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [order_name] 订单编号
     *
     * @apiSampleRequest /app/v2?service=order.confirmReceive&source=app
     */
    public function confirmReceive()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/confirmReceive';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     *
     * @api              {post} / 订单物流进度
     * @apiDescription   订单物流进度
     * @apiGroup         order
     * @apiName          logisticTrace
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [order_name] 订单编号
     *
     * @apiSampleRequest /app/v2?service=order.logisticTrace&source=app
     */
    public function logisticTrace()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/logisticTrace';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     *
     * @api              {get} / 申诉详情
     * @apiDescription   申诉详情
     * @apiGroup         order
     * @apiName          complaintsDetail
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [id] 申诉id
     *
     * @apiSampleRequest /app/v2?service=order.complaintsDetail&source=app
     */
    public function complaintsDetail()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/complaintsDetail';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     *
     * @api              {post} / 客户评价申诉
     * @apiDescription   客户评价申诉
     * @apiGroup         order
     * @apiName          complaintsFeedback
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [qcid] 申诉id
     * @apiParam {String} [stars] 评价星级
     *
     * @apiSampleRequest /app/v2?service=order.complaintsFeedback&source=app
     */
    public function complaintsFeedback()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/complaintsFeedback';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     *
     * @api              {get} / 订单商品评价列表
     * @apiDescription   订单商品评价列表
     * @apiGroup         order
     * @apiName          commentNewList
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [order_name] 订单编号
     *
     * @apiSampleRequest /app/v2?service=order.commentNewList&source=app
     */
    public function commentNewList()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/commentNewList';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     *
     * @api              {get} / 申诉列表
     * @apiDescription   申诉列表
     * @apiGroup         order
     * @apiName          complaintsListNew
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [status] 申请状态
     * @apiParam {String} [page] 分页
     * @apiParam {String} [pagesize] 分页展示数量
     *
     * @apiSampleRequest /app/v2?service=order.complaintsListNew&source=app
     */
    public function complaintsListNew()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/complaintsListNew';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }



    /**
     *
     * @api              {post} / 索取发票
     * @apiDescription   索取发票
     * @apiGroup         order
     * @apiName          useInvoice
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [lonlat] 定位
     * @apiParam {String} [fp] 发票抬头
     * @apiParam {String} [fp_dz] 发票地址
     * @apiParam {String} [fp_id_no] 税号
     * @apiParam {String} [invoice_username] 用户名
     * @apiParam {String} [invoice_mobile] 手机号码
     * @apiParam {String} [invoice_province] 省份
     * @apiParam {String} [invoice_city] 城市
     * @apiParam {String} [invoice_area] 地区
     * @apiParam {String} [area_adcode] 地区code
     * @apiParam {String} [store_id_list] 门店id
     * @apiParam {String} [delivery_code] 配送仓
     * @apiParam {String} [invoice_type] 发票类型 电子/纸质
     * @apiParam {String} [kp_type] 发票类型
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.useInvoice&source=app
     */
    public function useInvoice()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/useInvoice';
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
            $gatewayResponse['msg'] = $response['msg'];
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
     *
     * @api              {post} / 取消索取发票
     * @apiDescription   取消索取发票
     * @apiGroup         order
     * @apiName          cancelInvoice
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [lonlat] 定位
     * @apiParam {String} [area_adcode] 地区code
     * @apiParam {String} [store_id_list] 门店id
     * @apiParam {String} [delivery_code] 配送仓
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.cancelInvoice&source=app
     */
    public function cancelInvoice()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/cancelInvoice';
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
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     *
     * @api              {get} / 质量申诉列表
     * @apiDescription   质量申诉列表
     * @apiGroup         order
     * @apiName          appealList
     *
     * @apiParam {String} [connect_id] connect_id
     *
     * @apiSampleRequest /app/v2?service=order.appealList&source=app
     */
    public function appealList()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/appealList';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     * @api              {post} / 质量申诉
     * @apiDescription   质量申诉
     * @apiGroup         order
     * @apiName          doAppeal
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [information]	手机号
     * @apiParam {String} [description]	问题描述
     * @apiParam {String} [ordername]	订单号
     * @apiParam {String} [product_id]	商品id
     * @apiParam {String} [product_no]	商品编码
     * @apiParam {String} [productname]	商品名称
     * @apiParam {String} [quest_ratio]	问题占比
     *
     * @apiSampleRequest /app/v2?service=order.doAppeal&source=app
     */
    public function doAppeal()
    {
        if (class_exists('CURLFile') && !empty($_FILES)) {
            $ch = curl_init($this->orderServiceUrl . '/order/doAppeal');

            $images = array();
            foreach ($_FILES as $fl) {
                $curlFl = new CURLFile(realpath($fl['tmp_name']), $fl['type'], $fl['name']);
                array_push($images,$curlFl);
                //$curlFl = new CURLFile(realpath($fl['tmp_name']));
            }

            $params = array_merge($this->input->get(),
                $this->input->post(),$this->getUserInfo()
            );
            foreach($images as $k=>$v)
            {
                $key = $k+1;
                $params['images'.$key] = $v;
            }
            curl_setopt_array($ch, [CURLOPT_POST => 1, CURLOPT_POSTFIELDS => $params, CURLOPT_RETURNTRANSFER => true]);
            $o_response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $response = [];
            if ($o_response) {
                $response = json_decode($o_response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $response = $o_response;
                }
            }
            if ($http_code != 200) {
                if (is_array($response) && !empty($response['code'])) {
                    $this->response = ['code' => $response['code'], 'msg' => $response['msg'] ?: '服务器异常,请重试!', 'data' => []];
                } else {
                    $this->response = ['code' => 300, 'msg' => $response ?: '服务器异常,请重试!', 'data' => []];
                }
                return;
            }
            $this->response = ['code' => 200, 'data' => $response, 'msg' => ''];
            return;

        }

        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);
        $url = $this->orderServiceUrl . '/order/doAppeal';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     *
     * @api              {post} / 初始化订单
     * @apiDescription   初始化订单
     * @apiGroup         order
     * @apiName          orderInit
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [lonlat] 定位
     * @apiParam {String} [address_id] 用户地址
     * @apiParam {String} [area_adcode] 地区code
     * @apiParam {String} [store_id_list] 门店id
     * @apiParam {String} [delivery_code] 配送仓
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.orderInit&source=app
     */
    public function orderInit()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/orderInit';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = isset($response['msg']) ? $response['msg'] : $response;
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
     *
     * @api              {post} / 创建订单
     * @apiDescription   创建订单
     * @apiGroup         order
     * @apiName          createOrder
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [lonlat] 定位
     * @apiParam {String} [package_send_times] 选择包裹时间 - json
     * @apiParam {String} [area_adcode] 地区code
     * @apiParam {String} [store_id_list] 门店id
     * @apiParam {String} [delivery_code] 配送仓
     * @apiParam {String} [device_id] 设备号
     * @apiParam {String} [sheet_show_price] 隐藏价格
     * @apiParam {String} [no_stock] 缺货商品
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.createOrder&source=app
     */
    public function createOrder()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/createOrder';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = isset($response['msg']) ? $response['msg'] : $response;
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
     *
     * @api              {post} / 订单详情
     * @apiDescription   订单详情
     * @apiGroup         order
     * @apiName          orderInfo
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [order_name] 订单编号
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.orderInfo&source=app
     */
    public function orderInfo()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $service_order_query = array_merge($this->getUserInfo(), $this->input->post());
        $params['url']          = $this->orderServiceUrl . '/order/orderDetail';;
        $params['data']         = $service_order_query;
        $params['method']       = 'get';
        $service_order_response = $this->api_process->process($params);
        
        if (is_array($service_order_response)) {
            $gateway_response['code']   = '200';
            $gateway_response['data']   = $service_order_response;
            $gateway_response['data']['is_full_balance_pay'] = $service_order_response['pay_name'] == '账户余额支付' ? 1 : 0;
            $gateway_response['msg']    = is_array($service_order_response) ? ($service_order_response['msg'] ? $service_order_response['msg'] : '') : $service_order_response;
        } else {
            $gateway_response['code']   = '300';
            $gateway_response['msg']    = '订单信息错误';
        }
        $this->response = $gateway_response;
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }


    public function batchAddCart()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/batchAddCart';
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
            if(isset($response['msg']))
            {
                $gatewayResponse['msg'] = $response['msg'];
                $gatewayResponse['data'] = $response['data'];
            }
            else
            {
                $gatewayResponse['msg'] = '';
                $gatewayResponse['data'] = $response;
            }
            $logTag = 'INFO';
        } elseif ($codeFirst == 3 || $codeFirst == 4) {
            $gatewayResponse['code'] = $code;
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     *
     * @api              {post} / 订单取消
     * @apiDescription   订单取消
     * @apiGroup         order
     * @apiName          orderCancel
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [order_name] 订单编号
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.orderCancel&source=app
     */
    public function orderCancel()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/orderCancel';
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
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
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
     *
     * @api              {get} / 门店订单列表
     * @apiDescription   门店订单列表
     * @apiGroup         order
     * @apiName          storeOrderList
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [page] 页码
     * @apiParam {String} [limit] 数量
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.storeOrderList&source=app
     */
    public function storeOrderList()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/storeOrderList';
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
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' =>array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }


    /**
     *
     * @api              {get} / 门店订单详情
     * @apiDescription   门店订单详情
     * @apiGroup         order
     * @apiName          storeOrderInfo
     *
     * @apiParam {String} [order_name] 订单编号
     * @apiParam {String} [source] 来源
     * @apiParam {String} [version] app版本
     *
     * @apiSampleRequest /app/v2?service=order.storeOrderInfo&source=app
     */
    public function storeOrderInfo()
    {
        $gatewayResponse = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->requestId);

        $url = $this->orderServiceUrl . '/order/storeOrderInfo';
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
            $gatewayResponse['msg'] = is_array($response)?($response['msg']?$response['msg']:''):$response;
            $logTag = 'INFO';
        }
        $this->fruit_log->track($logTag, json_encode($logContent));
        $this->response = $gatewayResponse;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' =>array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->requestId);
    }

}
