<?php
class User extends CI_Controller
{
    private $source, $version, $device_id, $connect_id, $response, $cart_id;

    public function __construct()
    {
        parent::__construct();
        $this->load->config('service');
        $this->load->library('restclient');
        $this->load->library('fruit_log');
        $this->load->helper('public');
        $this->load->library('api_process');
        $this->request_id = date('Y_m_d_H_i_s') . '_' . md5(json_encode($_REQUEST) . mt_rand(10000, 99999));//用于记录日志用
        define('CURRENT_VERSION_USER_API', $this->config->item('user', 'service') . '/v1/user');
        define('CURRENT_VERSION_PRODUCT_API', $this->config->item('product', 'service'));
        $this->source = $this->input->get_post('source');
        $this->version = $this->input->get_post('version');
        $this->device_id = $this->input->get_post('device_id');
        $this->connect_id = $this->input->get_post('connect_id');
        $this->area_adcode = $this->input->get_post('area_adcode');
        $temp = changeStoreId($this->input->post('store_id_list'));
        $this->store_id_lists = $temp['store_id_list'];
        $this->tms_region_type = $temp['tms_region_type'];
        $this->tms_region_time = $temp['tms_region_time'];
        $this->init_user();
    }

    private function init_user()
    {
        if (!isset($_SESSION) && $this->input->get_post('connect_id')) {
            session_id($this->input->get_post('connect_id'));
            session_start();
            $is_session_on = true;
        }
        $this->uid = isset($_SESSION['user_detail']['id']) ? $_SESSION['user_detail']['id'] : '';
        session_write_close();
    }

    public function __destruct()
    {
        if ($this->response) {
            echo json_encode($this->response);
        }
        if (!function_exists("fastcgi_finish_request")) {
            function fastcgi_finish_request()
            {
            }//为windows兼容
        }
        $this->fruit_log->track('INFO', json_encode($this->response));//@TODO,统一收集日志,实验性质,可能包含敏感信息,待处理
        fastcgi_finish_request();
        $this->fruit_log->save();
    }

    //获取基本查询数据
    private function basic_query_data()
    {
        $service_user_query['source']           = $this->source;
        $service_user_query['source_version']   = $this->version;
        $service_user_query['request_id']       = $this->request_id;//请求id
        $service_user_query['uid']= $this->uid;
        return $service_user_query;
    }

    /**
     * @api {post} / 会员二维码
     * @apiDescription 会员二维码
     * @apiGroup app/user
     * @apiName qrcode
     *
     * @apiParam {String} [connect_id] 登录Token
     * @apiParam {String} [device_id=5aee7f491ffe2a6edc44bbf94a4cb8cc] 登录Token
     *
     * @apiSampleRequest /app/v2?service=user.qrcode&source=app
     */
    public function qrcode()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $url = CURRENT_VERSION_USER_API . '/' .'qrcode'. '/' . $this->uid;
        $service_user_query = $this->basic_query_data();
        $request = http_build_query($service_user_query);
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_user_response = json_decode($result->response, true);

        $log_content['id'] = $this->request_id;
        $log_content['request']['url']      = $url;
        $log_content['request']['content']  = $request;

        $log_content['response']['code']    = $code;
        $log_content['response']['content'] = $service_user_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5 || !$service_user_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_user_response) {
            $gateway_response['code']   = '200';
            $gateway_response['msg']    = '二维码生成成功';
            $gateway_response['qr_str'] = $service_user_response['qr_str'];
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code']   = '300';
            $gateway_response['msg']    = '二维码生成失败';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }


    /**
     * @api              {post} / 用户赠品
     * @apiDescription   用户赠品
     * @apiGroup         user
     * @apiName          giftsGetNew
     *
     * @apiParam {String} connect_id 用户登录状态
     * @apiParam {String} store_id_list 门店id,逗号分隔
     *
     * @apiSampleRequest /api/test?service=user.giftsGetNew&source=app
     */
    public function giftsGetNew()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $params['url']              = $this->config->item('product', 'service') . '/v2' . '/product/giftsGetNew';
        $params['method']           = 'get';
        $params['data']['uid']              = get_uid($this->input->get_post('connect_id'));
        $params['data']['store_id_list']    = $this->input->get_post('store_id_list');
        $service_response  = $this->api_process->process($params);
        $gateway_response['code'] = '200';
        $gateway_response['msg'] = '';
        $gateway_response['data'] = $service_response;
        $this->response = $gateway_response;
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    private function getUid()
    {
        static $uid = null;
        if (isset($uid)) {
            return $uid;
        }
        $connect_id = $this->input->get_post('connect_id');
        $uid = 0;
        if ($connect_id) {
            session_id($connect_id);
            session_start();
            $uid = isset($_SESSION['user_detail']['id']) ? $_SESSION['user_detail']['id'] : 0;
            session_write_close();
        }
        return (int)$uid;
    }


    /**
     * @method 保存uid和session对应关系
     * @param int $uid
     * @return string
     */
    private function setUid($uid)
    {
        $connect_id = $this->input->get_post('connect_id');
        if ($connect_id) {
            session_id($connect_id);
        }
        session_start();
        $_SESSION['user_detail']['id'] = $uid;
        session_write_close();
        return session_id();
    }

    /**
     * @method 销毁session信息
     * @param void
     * @return bool(true)
     */
    private function destroySession()
    {
        $this->load->library('Nirvana3session');
        $this->nirvana3session->destory();
//      $connect_id = $this->input->get_post('connect_id');
//      if ($connect_id) session_id($connect_id);
//      session_start();
//      session_destroy();
//      session_write_close();
        return true;
    }

    public function userScore()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $gateway_response = array();
        $url = $this->config->item('user', 'service') . '/v1' . '/score/scorelist';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    public function userCouponList()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $gateway_response = array();
        $url = $this->config->item('user', 'service') . '/v1' . '/card/cardlist';
        $uid = $this->getUid();
        $goods_money = $this->input->get_post('goods_money');
        $source = $this->input->get_post('source');
        $pay_discount = $this->input->get_post('pay_discount');
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        $cart_pro_ids = array();
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $cardlist = $service_response['cards'];

            $url = $this->config->item('cart', 'service') . '/v3/cart' . '/' . $uid . '/order?request_id=' . $this->request_id;
            ;
            $service_cart_query = array();
            $service_cart_query['source']           = $this->source;
            $service_cart_query['source_version']   = $this->version;
            $service_cart_query['stores']           = $this->store_id_lists;//门店列表,多个用逗号分隔
            $service_cart_query['range']            = $this->tms_region_type;//门店配送范围?
            $service_cart_query['area_adcode']      = $this->area_adcode;//三级地区码
            $service_cart_query['uid']              = $this->uid;
            $request = http_build_query($service_cart_query);
            $result = $this->restclient->get($url, $request);
            $code = $result->info->http_code;
            $service_cart_response = json_decode($result->response, true);
            $cart_products = $service_cart_response['products'];

            $this->load->model('card_model');
            $card_pros = array();
            $discount_upto_goods_money = $goods_money + $pay_discount;
            foreach ($cardlist as $key => $value) {
                if ($value['product_id']) {
                    $c_ps = explode(',', $value['product_id']);
                    $card_pros = array_merge($card_pros, $c_ps);
                }
                $info = $this->card_model->card_can_use($value, $uid, $discount_upto_goods_money, $source, 0, $pay_discount, 0, $cart_products);
                if ($info[0] == 0) {
                    $cardlist[$key]['can_not_use'] = 1;
                    $cardlist[$key]['can_not_use_reason'] = $info[1] ? $info[1] : "不可使用";
                } else {
                    $cardlist[$key]['can_not_use'] = 0;
                    $cardlist[$key]['can_not_use_reason'] = '';
                }
            }
            $card_pros = array_filter(array_unique($card_pros));
            $p_infos = array();

            if ($card_pros) {
                $url = $this->config->item('product', 'service') . '/v2' . '/product/productBaseInfo';
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
                    }
                } elseif ($code_first == 3 || $code_first == 4) {
                    $gateway_response['code'] = '300';
                    $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
                    $log_tag = 'INFO';
                }
            }
            
            $sort_array = array();
            foreach ($cardlist as $key => &$value) {
                if (empty($value['product_id'])) {
                    $value['use_range'] = "全站通用(个别商品除外)";
                } else {
                    $value['card_product_id'] = $value['product_id'];
                    $c_ps = explode(',', $value['product_id']);
                    $curr_range = array();
                    foreach ($c_ps as $v) {
                        $curr_range[] = isset($p_infos[$v])?$p_infos[$v]:'';
                    }
                    $value['use_range'] = "仅限" . join(",", $curr_range) . "使用";
                }
                if ($value['order_money_limit'] > 0) {
                    $value['use_range'] .="满" . floatval($value['order_money_limit']) . "使用";
                }

                if (!empty($value['direction'])) {
                    $value['use_range'] = $value['direction'];
                }
                if ($value['to_date'] < date("Y-m-d")) {
                    $value['is_expired'] = 1;
                } else {
                    $value['is_expired'] = 0;
                }
                $sort_array[] = $value['card_money'];
            }
            
            $sort_array and array_multisort($sort_array, SORT_DESC, $cardlist);

            $can_use_list = array();
            $can_not_use_list = array();
            foreach ($cardlist as $card) {
                if ($card['can_not_use'] == 1) {
                    $can_not_use_list[] = $card;
                } else {
                    $can_use_list[] = $card;
                }
            }
            $cardlist_result = array_merge($can_use_list, $can_not_use_list);
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '获取成功';
            $gateway_response['data'] = $cardlist_result;
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }

        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    public function privilegeList()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('user', 'service') . '/v1' . '/user/privilegelist';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        $privilegeList = array();
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $privilegeList = $service_response;
            $url = $this->config->item('banner', 'service') . '/v1' . '/app_banner/bannerList';
            $request = http_build_query(array_merge($this->input->post(), array('source'=>'app','position'=>62)));
            $result = $this->restclient->get($url, $request);
            $code = $result->info->http_code;
            $service_response = json_decode($result->response, true);
            $log_content['id'] = $this->request_id;
            $log_content['request']['url'] = $url;
            $log_content['request']['content'] = $request;
            $log_content['response']['code'] = $code;
            $log_content['response']['content'] = $service_response;
            $code_first = substr($code, 0, 1);
            if ($code_first == 5 || !$service_response) {
                $log_tag = 'ERROR';
                $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
            } elseif ($code_first == 2 && $service_response) {
                $bannerList = $service_response['bannerList'];
                foreach ($bannerList as $key => $value) {
                    switch ($value['type']) {
                        case '6': //链接的时候
                            $url = $value['page_url'].'connect_id='.$this->connect_id.'&store_id_list='.$this->store_id_lists;
                            break;
                        case '20':
                            $url = $value['page_url'];
                            break;
                        default:
                            $url=false;
                            break;
                    }
                    if ($url) {
                        $privilegeList[] = array(
                            'privilege_type' => 'web',
                            'active_banner' => $value['photo'],
                            'url' => $url,
                        );
                    }
                }
                $gateway_response['code'] = '200';
                $gateway_response['msg'] = '';
                $log_tag = 'INFO';
            } elseif ($code_first == 3 || $code_first == 4) {
                $gateway_response['code'] = '300';
                $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
                $log_tag = 'INFO';
            }
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }

        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;
        if ($privilegeList) {
            $this->response['data'] = $privilegeList;//框架中有析构函数，为避免可能失效不使用exit
        }
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    public function gcouponGet()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('user', 'service') . '/v1' . '/user_gift_card/giftsGet';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        $gifts = array();
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $gifts = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '兑换成功，请在我的赠品里查看';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        if ($gifts) {
            $url = $this->config->item('cart', 'service') . '/v3' . '/cart/' . $uid . '?request_id=' . $this->request_id;
            ;
            $query = array();
            $query['product_id']       = $gifts['giftsend']['product_id'];
            $query['type']             = 'user_gift';
            $query['qty']              = $gifts['giftsend']['qty'];
            $query['gift_send_id']     = $gifts['giftsend']['id'];
            $query['gift_active_type'] = 2;
            $query['user_gift_id']     = $gifts['giftsend']['user_gifts']['id'];
            $query['stores']           = $this->store_id_lists;//门店列表,多个用逗号分隔
            $query['range']            = $this->tms_region_type;//门店配送范围?
            $query['area_adcode']      = $this->area_adcode;//三级地区码
            $query['uid']              = $uid;
            $request = http_build_query($query);
            $result = $this->restclient->post($url, $request);
        }
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    public function getInvoice()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('user', 'service') . '/v1' . '/user/invoice';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    public function levelLog()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('user', 'service') . '/v1' . '/user/levelLog';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    public function checkRedIndicator()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('user', 'service') . '/v1' . '/user/checkRedIndicator';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    public function cancelRedIndicator()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('user', 'service') . '/v1' . '/user/cancelRedIndicator';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    public function tradeInvoiceHistory()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('user', 'service') . '/v1' . '/invoice/tradeInvoiceHistory';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        $gateway_response = array();
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    public function userCouponNewList()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $url = $this->config->item('user', 'service') . '/v1' . '/card/cardlistnew';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $cardlist = $service_response;
            $card_pros = array();
            foreach ($cardlist['notused'] as $key => $value) {
                if ($value['product_id']) {
                    $c_ps = explode(',', $value['product_id']);
                    $card_pros = array_merge($card_pros, $c_ps);
                }
            }
            foreach ($cardlist['used'] as $key => $value) {
                if ($value['product_id']) {
                    $c_ps = explode(',', $value['product_id']);
                    $card_pros = array_merge($card_pros, $c_ps);
                }
            }
            foreach ($cardlist['overdue'] as $key => $value) {
                if ($value['product_id']) {
                    $c_ps = explode(',', $value['product_id']);
                    $card_pros = array_merge($card_pros, $c_ps);
                }
            }
            $card_pros = array_filter(array_unique($card_pros));
            $p_infos = array();
            if ($card_pros) {
                $url = $this->config->item('product', 'service') . '/v2' . '/product/productBaseInfo';
                $request = http_build_query(array('product_id' => $card_pros));
                $result = $this->restclient->post($url, $request);
                $code = $result->info->http_code;
                $code_first = substr($code, 0, 1);
                $service_response = json_decode($result->response, true);
                if ($code_first == 5 || !$service_response) {
                    $log_tag = 'ERROR';
                    $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
                } elseif ($code_first == 2 && $service_response) {
                    $product_list = $service_response;
                    foreach ($product_list as $key => $value) {
                        $p_infos[$value['id']] = $value['product_name'];
                    }
                } elseif ($code_first == 3 || $code_first == 4) {
                    $gateway_response['code'] = '300';
                    $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
                    $log_tag = 'INFO';
                }
            }
            foreach ($cardlist['notused'] as $key => &$value) {
                if (empty($value['product_id'])) {
                    $value['use_range'] = "全站通用(个别商品除外)";
                } else {
                    $value['card_product_id'] = $value['product_id'];
                    $c_ps = explode(',', $value['product_id']);
                    $curr_range = array();
                    foreach ($c_ps as $v) {
                        $curr_range[] = isset($p_infos[$v])?$p_infos[$v]:'';
                    }
                    $value['use_range'] = "仅限" . join(",", $curr_range) . "使用";
                }
                if ($value['order_money_limit'] > 0) {
                    $value['use_range'] .="满" . floatval($value['order_money_limit']) . "使用";
                }

                if (!empty($value['direction'])) {
                    $value['use_range'] = $value['direction'];
                }
            }
            foreach ($cardlist['used'] as $key => &$value) {
                if (empty($value['product_id'])) {
                    $value['use_range'] = "全站通用(个别商品除外)";
                } else {
                    $value['card_product_id'] = $value['product_id'];
                    $c_ps = explode(',', $value['product_id']);
                    $curr_range = array();
                    foreach ($c_ps as $v) {
                        $curr_range[] = isset($p_infos[$v])?$p_infos[$v]:'';
                    }
                    $value['use_range'] = "仅限" . join(",", $curr_range) . "使用";
                }
                if ($value['order_money_limit'] > 0) {
                    $value['use_range'] .="满" . floatval($value['order_money_limit']) . "使用";
                }

                if (!empty($value['direction'])) {
                    $value['use_range'] = $value['direction'];
                }
            }
            foreach ($cardlist['overdue'] as $key => &$value) {
                if (empty($value['product_id'])) {
                    $value['use_range'] = "全站通用(个别商品除外)";
                } else {
                    $value['card_product_id'] = $value['product_id'];
                    $c_ps = explode(',', $value['product_id']);
                    $curr_range = array();
                    foreach ($c_ps as $v) {
                        $curr_range[] = isset($p_infos[$v])?$p_infos[$v]:'';
                    }
                    $value['use_range'] = "仅限" . join(",", $curr_range) . "使用";
                }
                if ($value['order_money_limit'] > 0) {
                    $value['use_range'] .="满" . $value['order_money_limit'] . "使用";
                }

                if (!empty($value['direction'])) {
                    $value['use_range'] = $value['direction'];
                }
            }
            $gateway_response['data'] = $cardlist;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }

        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    public function giftCardCharge()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('user', 'service') . '/v1' . '/gift_cards/giftCardCharge';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid,'c_region'=>1)));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }

        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    public function userCharge()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('user', 'service') . '/v1' . '/user/userCharge';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    public function collectMobileData()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('user', 'service') . '/v1' . '/user/collectMobileData';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }
    /**
     * @api              {get} /app/v2/ 会员登录
     * @apiDescription   会员登录
     * @apiGroup         user
     * @apiName          signin
     *
     * @apiParam {String} [mobile] 手机号
     * @apiParam {String} [password] 密码
     *
     * @apiSampleRequest /app/v2/?service=user.signin&source=app
     **/
    public function signin()
    {
        $this->doUserServer(sprintf("v1/user/%s/", __FUNCTION__));
        $this->afterLogin();
    }

    /**
     * @api              {get} /app/v2/ 联合登陆
     * @apiDescription   联合登陆
     * @apiGroup         user
     * @apiName          oAuthSignin
     *
     * @apiParam {String} [open_user_id] Open user id
     * @apiParam {String} [signin_channel] Signin channel
     *
     * @apiSampleRequest /app/v2/?service=user.oAuthSignin&source=app
     **/
    public function oAuthSignin()
    {
        $this->doUserServer(sprintf("v1/user/%s/", __FUNCTION__));
        $this->afterLogin();
    }

    /**
     * @api              {get} /app/v2/ 发送注册验证码
     * @apiDescription   发送注册验证码
     * @apiGroup         user
     * @apiName          sendPhoneTicket
     *
     * @apiParam {String} [mobile] 手机号
     * @apiParam {String} [valid_source]    校验渠道;api或api-voice
     *
     * @apiSampleRequest /app/v2/?service=user.sendPhoneTicket&source=app
     **/
    public function sendPhoneTicket()
    {
        $this->doUserServer(sprintf("v1/user/%s/", __FUNCTION__));
        $this->afterSendCode('register_verification_code');
    }

    /**
     * @api              {get} /app/v2/ 会员注册
     * @apiDescription   会员注册
     * @apiGroup         user
     * @apiName          register
     *
     * @apiParam {String} [mobile] 手机号
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [password] 密码
     *
     * @apiSampleRequest /app/v2/?service=user.register&source=app
     **/
    public function register()
    {
        $this->doUserServer(sprintf("v1/user/%s/", __FUNCTION__));
    }

    /**
     * @api              {get} /app/v2/ 退出登陆
     * @apiDescription   退出登陆
     * @apiGroup         user
     * @apiName          signout
     *
     * @apiParam {String} [connect_id] connect_id
     *
     * @apiSampleRequest /app/v2/?service=user.signout&source=app
     **/
    public function signout()
    {
        $connect_id = $this->input->get_post('connect_id');
        if (!empty($connect_id)) {
            $this->destroySession();
        }
        $this->response = ['code' => 200, 'data' => '', 'msg' => '退出成功'];
    }

    /**
     * @api              {get} /app/v2/ 密码修改
     * @apiDescription   密码修改
     * @apiGroup         user
     * @apiName          password
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [old_password] old_password
     * @apiParam {String} [password] password
     * @apiParam {String} [re_password] re_password
     *
     * @apiSampleRequest /app/v2/?service=user.password&source=app
     **/
    public function password()
    {
        $this->doUserServer(sprintf("v1/user/%s/", __FUNCTION__));
    }

    /**
     * @api              {get} /app/v2/ 用户验证码
     * @apiDescription   用户验证码
     * @apiGroup         user
     * @apiName          sendVerCode
     *
     * @apiParam {String} [mobile] 手机号
     * @apiParam {String} [use_case] use_case
     * @apiParam {String} [valid_source]    校验渠道;api或api-voice
     *
     * @apiSampleRequest /app/v2/?service=user.sendVerCode&source=app
     **/
    public function sendVerCode()
    {
        $this->doUserServer(sprintf("v1/user/%s/", __FUNCTION__));
        $this->afterSendCode('verification_code');
    }

    /**
     * @api              {get} /app/v2/ 用户验证码
     * @apiDescription   校验用户验证码
     * @apiGroup         user
     * @apiName          verifyVerCode
     *
     * @apiParam {String} [mobile] 手机号
     * @apiParam {String} [code] 验证码
     * @apiParam {String} [valid_source]    校验渠道;api或api-voice
     *
     * @apiSampleRequest /app/v2/?service=user.verifyVerCode&source=app
     **/
    public function verifyVerCode()
    {
        $this->doUserServer(sprintf("v1/user/%s/", __FUNCTION__));
    }

    /**
     * @api              {get} /app/v2/ 清空用户验证码
     * @apiDescription   清空用户验证码
     * @apiGroup         user
     * @apiName          clearVerCode
     *
     * @apiParam {String} [mobile] 手机号
     *
     * @apiSampleRequest /app/v2/?service=user.clearVerCode&source=app
     **/
    public function clearVerCode()
    {
        $this->doUserServer(sprintf("v1/user/%s/", __FUNCTION__));
    }

    /**
     * @api              {get} /app/v2/ 密码找回
     * @apiDescription   密码找回
     * @apiGroup         user
     * @apiName          forgetPasswd
     *
     * @apiParam {String} [mobile] 手机号
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [password] password
     * @apiParam {String} [re_password] re_password
     * @apiParam {String} [verification_code] verification_code
     *
     * @apiSampleRequest /app/v2/?service=user.forgetPasswd&source=app
     **/
    public function forgetPasswd()
    {
        $this->doUserServer(sprintf("v1/user/%s/", __FUNCTION__));
    }

    /**
     * @api              {get} /app/v2/ 手机绑定
     * @apiDescription   手机绑定
     * @apiGroup         user
     * @apiName          bindMobile
     *
     * @apiParam {String} [mobile] 手机号
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [password] password
     * @apiParam {String} [verification_code] verification_code
     *
     * @apiSampleRequest /app/v2/?service=user.bindMobile&source=app
     **/
    public function bindMobile()
    {
        $this->doUserServer(sprintf("v1/user/%s/", __FUNCTION__));
    }

    /**
     * @api              {get} /app/v2/ 修改用户信息
     * @apiDescription   修改用户信息
     * @apiGroup         user
     * @apiName          upUserInfo
     *
     * @apiParam {String} [connect_id] connect_id
     *
     * @apiSampleRequest /app/v2/?service=user.upUserInfo&source=app
     **/
    public function upUserInfo()
    {
        $file = isset($_FILES['photo']) ? $_FILES['photo'] : [];
        if (isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
            if (class_exists('CURLFile')) {
                $ch = curl_init(sprintf("%s/v1/user/%s/", $this->config->item('user', 'service'), __FUNCTION__));
                $params = array_merge(
                    $this->input->get(),
                    $this->input->post(),
                    [
                        'photo' => new CURLFile(realpath($file['tmp_name']), $file['type'], $file['name']),
                        'uid' => (int)$this->getUid()
                    ]
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
        $this->doUserServer(sprintf("v1/user/%s/", __FUNCTION__), 'POST');
    }

    /**
     * @api              {get} /app/v2/ 手机快捷登录
     * @apiDescription   手机快捷登录
     * @apiGroup         user
     * @apiName          mobileLogin
     *
     * @apiParam {String} [mobile] 手机号
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} [register_verification_code] register_verification_code
     *
     * @apiSampleRequest /app/v2/?service=user.mobileLogin&source=app
     **/
    public function mobileLogin()
    {
        $this->doUserServer(sprintf("v1/user/%s/", __FUNCTION__));
        $this->afterLogin();
    }

    /**
     * @api              {get} /app/v2/ 用户信息
     * @apiDescription   用户信息
     * @apiGroup         user
     * @apiName          userInfo
     *
     * @apiParam {String} [connect_id] connect_id
     *
     * @apiSampleRequest /app/v2/?service=user.userInfo&source=app
     **/
    public function userInfo()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $url = $this->config->item('user', 'service') . '/v1/user/userInfo' . '?request_id=' . $this->request_id;
        $service_cart_query['uid'] = get_uid();
        $params['url']      = $url;
        $params['data']     = $service_cart_query;
        $params['method']   = 'get';
        $service_cart_response = $this->api_process->process($params);

        $response_result = $this->api_process->get('result');
        if ($response_result->info->http_code == 200) {
            $api_gateway_response['code']   = '200';
            $api_gateway_response['data']   = $service_cart_response;
        } else {
            $api_gateway_response['code']   = $service_cart_response['code'];
            $api_gateway_response['msg']    = $service_cart_response['msg'];
        }
        $this->response = $api_gateway_response;
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    private function doUserServer($url, $method = 'GET', $parameters = [], $options = [])
    {
        $this->restclient->set_option('base_url', $this->config->item('user', 'service'));
        $this->restclient->set_option('curl_options', $options);
        $data = $this->restclient->execute($url, $method, array_merge($this->input->get(), $this->input->post(), ['uid' => (int)$this->getUid()], $parameters));
        $response = [];
        if ($data->response) {
            $response = json_decode($data->response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $response = $data->response;
            }
        }

        if ($data->info->http_code != 200) {
            if (is_array($response) && !empty($response['code'])) {
                $this->response = ['code' => $response['code'], 'msg' => $response['msg'] ?: '服务器异常,请重试!', 'data' => []];
            } else {
                $this->response = ['code' => 300, 'msg' => $response ?: '服务器异常,请重试!', 'data' => []];
            }
            return;
        }
        if (is_array($response)) {
            $this->response = ['code' => 200, 'data' => $response, 'msg' => ''];
        } else {
            $this->response = ['code' => 200, 'data' => [], 'msg' => $response];
        }
    }

    private function addConnIdToResp()
    {
        if (is_array($this->response) && $this->response['code'] == 200 && isset($this->response['data'])) {
            $this->response['data']['connect_id'] = session_id();
        }
    }

    private function afterLogin()
    {
        if (is_array($this->response) && $this->response['code'] == 200 && isset($this->response['data'])) {
            $this->load->library('Nirvana3session');
            $this->nirvana3session->set_userdata($this->response['data']['userinfo']);
        }
        $this->addConnIdToResp();
    }

    private function afterSendCode($key)
    {
        if (is_array($this->response) && $this->response['code'] == 200 && isset($this->response['data'])) {
            $this->load->library('Nirvana3session');
            $this->nirvana3session->set_userdata([$key => $this->response['data']['verification_code']]);
            unset($this->response['data']['verification_code']);
        }
        $this->addConnIdToResp();
    }

    public function userCenterConfig()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('user', 'service') . '/v1' . '/user/userCenterConfig';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    public function cardtips()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('user', 'service') . '/v1' . '/card/cardtips';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;
        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    public function cardReceive()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('user', 'service') . '/v1' . '/user/receive';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;
        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $gateway_response['data'] = array();
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    /**
     * @api              {post} /app/v2/ 到货提醒
     * @apiDescription   到货提醒
     * @apiGroup         user
     * @apiName          alertProduct
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {Number} product_id 商品ID
     * @apiParam {Number} store_id 门店ID
     * @apiParam {Number} set 取消设置
     *
     * @apiSampleRequest /app/v2/?service=user.alertProduct&source=app
     **/
    public function alertProduct()
    {
        $this->doUserServer(sprintf("v1/user/%s/", __FUNCTION__), 'POST');
    }

    /**
     * @api              {get} /app/v2/ 获取到货提醒状态
     * @apiDescription   获取到货提醒状态
     * @apiGroup         user
     * @apiName          alertList
     *
     * @apiParam {String} [connect_id] connect_id
     * @apiParam {String} product_id 商品列表ID
     * @apiParam {String} store_id 门店ID
     *
     * @apiSampleRequest /app/v2/?service=user.alertList&source=app
     **/
    public function alertList()
    {
        $this->doUserServer(sprintf("v1/user/%s/", __FUNCTION__));
    }

    public function notice()
    {
        $gateway_response = array();

        $uid = $this->getUid();
        $pay_num = 0;
        $comment_num = 0;
        $gift_num = 0;
        $privilege_num = 0;
        $foretaste_num = 0;
        $new_gift_alert = 0;
        $new_coupon_alert = 0;
        $new_jf_alert = 0;
        $order_paying = 0;
        $order_shipped = 0;
        $order_receipt = 0;
        $order_comment = 0;
        $cardtips_notice = 0;





        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('order', 'service') . '/v1' . '/order/orderList';
        $fields = 'id,order_name,operation_id,pay_status,had_comment,show_status';

        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid,'fields'=>$fields)));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;
        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        $orders = array();
        $notice_order_types = array('1','2','3','4','5','7','13','9','10');
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $orders = $service_response['data'];
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }
        if ($orders) {
            foreach ($orders as $order) {
                if ($order['pay_status'] == 0 && $order['operation_id'] != 5) {
                    $pay_num++;
                }
                if ($order['had_comment'] == 0 && in_array($order['operation_id'], array(3,9)) && $order['time'] > date("Y-m-d", strtotime('- 3 months'))) {
                    $comment_num++;
                }
                if ($order['pay_status'] == 0 && $order['pay_parent_id'] != 4 && $order['show_status'] == 1 && $order['operation_id'] == 0 && in_array($order['order_type'], $notice_order_types)) {
                    $order_paying ++;
                }
                if (($order['pay_status'] ==1 || $order['pay_parent_id'] ==4 ) && $order['show_status'] == 1 && in_array($order['operation_id'], array(0,1,4)) && in_array($order['order_type'], $notice_order_types)) {
                    $order_shipped ++;
                }
                if ($order['pay_status'] ==1 && $order['show_status'] == 1 && $order['operation_id'] == 2 && in_array($order['order_type'], $notice_order_types)) {
                    $order_receipt ++;
                }
                if ($order['pay_status'] ==1 && $order['had_comment'] == 0 && $order['time'] > date("Y-m-d", strtotime('- 3 months')) && $order['show_status'] == 1 && in_array($order['operation_id'], array(3,6,9)) && in_array($order['order_type'], $notice_order_types)) {
                    $order_comment ++;
                }
            }
        }
        $url = $this->config->item('user', 'service') . '/v1' . '/user/userNotice';
        $request = http_build_query(array('uid' => $uid));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $gift_num = $service_response['gift_num'];
            $foretaste_num = $service_response['foretaste_num'];
            $new_gift_alert = $service_response['new_gift_alert'];
            $new_coupon_alert = $service_response['new_coupon_alert'];
            $new_jf_alert = $service_response['new_jf_alert'];
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }

        $url = $this->config->item('user', 'service') . '/v1' . '/card/cardtips';
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $cardtips_notice = $service_response['notice'];
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }

        $result = array(
            'pay_num' => $pay_num,
            'comment_num' => $comment_num,
            'gift_num' => $gift_num,
            'privilege_num' => $privilege_num,
            'foretaste_num' => $foretaste_num ? $foretaste_num : 0,
            'new_gift_alert' => $new_gift_alert,
            'new_coupon_alert' => $new_coupon_alert,
            'new_jf_alert' => $new_jf_alert,
            'order_paying' => $order_paying,
            'order_shipped' => $order_shipped,
            'order_receipt' => $order_receipt,
            'order_comment' => $order_comment,
            'order_refund' => 0,
            'notice'       => $cardtips_notice,
//            'order_refund'=>$order_refund
        );
        $gateway_response['code'] = 200;
        $gateway_response['data'] = $result;
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    public function userShareActive()
    {
        $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
    }


    public function getHot()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $gateway_response = array();
        $url = $this->config->item('user', 'service') . '/v1' . '/customerService/getHot';
        $request = http_build_query($this->input->post());
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $gateway_response['data'] = $service_response;
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }


    public function getClassList()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $gateway_response = array();
        $url = $this->config->item('user', 'service') . '/v1' . '/CustomerService/getClassList';
        $request = http_build_query($this->input->post());
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $gateway_response['data'] = $service_response;
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }


    public function search()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $gateway_response = array();
        $url = $this->config->item('user', 'service') . '/v1' . '/CustomerService/search';
        $request = http_build_query($this->input->post());
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5 || !$service_response) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2 && $service_response) {
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $gateway_response['data'] = $service_response;
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg'])?$service_response['msg']:'';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    /**
     * @api              {post} /v1/user/changeOldMobileCode 发送原手机验证码
     * @apiDescription   发送原手机验证码
     * @apiGroup         user
     * @apiName          changeOldMobileCode
     *
     * @apiParam {String} [mobile] 手机号
     * @apiParam {String} [valid_source]    校验渠道;api或api-voice
     *
     * @apiSampleRequest /app/v2/?service=user.changeOldMobileCode&source=app
     **/
    public function changeOldMobileCode()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('user', 'service') . '/v1' . '/user/changeOldMobileCode';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;
        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = $service_response;
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    /**
     * @api              {get} /v1/user/checkChangeOldMobileCode 验证修改手机码验证
     * @apiDescription   验证修改手机码验证
     * @apiGroup         user
     * @apiName          checkChangeOldMobileCode
     *
     * @apiParam {String} [mobile] 手机号
     * @apiParam {String} [verification_code] 验证码
     *
     * @apiSampleRequest /app/v2/?service=user.checkChangeOldMobileCode&source=app
     **/
    public function checkChangeOldMobileCode()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('user', 'service') . '/v1' . '/user/checkChangeOldMobileCode';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;
        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = $service_response;
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

     /**
     * @api              {get} /v1/user/changeNewMobileCode 发送新手机验证码
     * @apiDescription   发送新手机验证码
     * @apiGroup         user
     * @apiName          changeNewMobileCode
     *
     * @apiParam {String} [mobile] 手机号
     * @apiParam {String} [token] token
     * @apiParam {String} [valid_source]    校验渠道;api或api-voice
     *
     * @apiSampleRequest /app/v2/?service=user.changeNewMobileCode&source=app
     **/
    public function changeNewMobileCode()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('user', 'service') . '/v1' . '/user/changeNewMobileCode';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;
        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            if (isset($service_response['code']) && $service_response['code']) {
                $gateway_response  = $service_response;
            } else {
                $gateway_response['code'] = '300';
                $gateway_response['msg'] = $service_response;
            }

            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

     /**
     * @api              {get} /v1/user/changeMobile 修改手机
     * @apiDescription   修改手机
     * @apiGroup         user
     * @apiName          changeMobile
     *
     * @apiParam {String} [mobile] 手机号
     * @apiParam {String} [verification_code] 验证码
     * @apiParam {token} [token] 验证码
     *
     * @apiSampleRequest /app/v2/?service=user.changeMobile&source=app
     **/
    public function changeMobile()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('user', 'service') . '/v1' . '/user/changeMobile';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;
        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '修改成功';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = $service_response;
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    /**
     * @api              {get} /v1/user/userTransaction 充值记录
     * @apiDescription   充值记录
     * @apiGroup         user
     * @apiName          userTransaction
     * @apiParam {int} [page] 页数
     * @apiParam {int} [limit] 每页
     * @apiSampleRequest /app/v2/?service=user.userTransaction&source=app
     **/
    public function userTransaction()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('user', 'service') . '/v1' . '/user/userTransaction';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;
        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = $service_response;
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    /**
     * @api              {get} /v1/user/expireScore 过期积分
     * @apiDescription   过期积分
     * @apiGroup         user
     * @apiName          expireScore
     * @apiParam {String} [connect_id] 登录Token
     * @apiSampleRequest /app/v2/?service=user.expireScore&source=app
     **/
    public function expireScore()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = $this->config->item('user', 'service') . '/v1' . '/score/expireScore';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;
        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = ($service_response['expireScore'] > 0) ? $service_response['expireScore']."积分将于".$service_response['expireDate']."过期，请尽快使用":"";
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = $service_response;
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    /**
     * @api              {get} /v1/user/cardGiftAlert 优惠券赠品提示
     * @apiDescription   优惠券赠品提示
     * @apiGroup         user
     * @apiName          cardGiftAlert
     * @apiParam {String} [connect_id] 登录Token
     * @apiSampleRequest /app/v2/?service=user.cardGiftAlert&source=app
     **/
    public function cardGiftAlert()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $url = $this->config->item('user', 'service') . '/v1' . '/user/cardGiftAlert';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;
        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2) {
            if ($service_response['card_list']) {
                $this->load->model('card_model');
                $service_response['card_list'] = $this->card_model->data_format($service_response['card_list']);
                $cardlist = array();
                foreach ($service_response['card_list'] as $key => $card) {
                    $cardlist[$key]['card_money'] = $card['card_money'];
                    $cardlist[$key]['promotion_type'] = $card['promotion_type'];
                    $cardlist[$key]['use_range'] = $card['use_range'];
                    $cardlist[$key]['time'] = $card['time'];
                    $cardlist[$key]['to_date'] = $card['to_date'];
                    $cardlist[$key]['order_money_limit'] = $card['order_money_limit'];
                    $cardlist[$key]['product_id'] = $cardlist[$key]['card_product_id'] = $card['product_id'];
                }
                $service_response['card_list'] = array_values($cardlist);
            }
            if ($service_response['gift_list']) {
                $this->load->model('user_gift_model');
                $gift_list = array();
                $service_response['gift_list'] = $this->user_gift_model->data_format($service_response['gift_list']);
                foreach ($service_response['gift_list'] as $key => $value) {
                    $gift_list[$key]['user_gift_id'] = $value['user_gift_id'];
                    $gift_list[$key]['product_name'] = $value['product']['product_name'];
                    $gift_list[$key]['photo'] = $value['product']['photo']['big'];
                    $gift_list[$key]['gg_name'] = $value['product']['gg_name'];
                    $gift_list[$key]['qty'] = $value['qty'];
                }
                $service_response['gift_list'] = array_values($gift_list);
            }
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = '';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = $service_response;
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    /**
     * @api {post} / 获取收货地址列表
     * @apiDescription 获取收货地址列表
     * @apiGroup user.address
     * @apiName getAddrList
     *
     * @apiParam {String} connect_id 登录Token
     * @apiParam {String} [address_id] 地址ID
     *
     * @apiSampleRequest /app/v2/?service=user.getAddrList&source=app
     */
    public function getAddrList()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $params['url'] = $url = CURRENT_VERSION_USER_API . '/getAddrList';
        $uid = $this->getUid();
        $params['data'] = array_merge(['uid' => $uid], $this->input->get(), $this->input->post());
        $params['method'] = 'get';
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
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    /**
     * @api {post} / 添加收货地址
     * @apiDescription 添加收货地址
     * @apiGroup user.address
     * @apiName addAddr
     *
     * @apiParam {String} connect_id 登录Token
     * @apiParam {String} name 收货人姓名
     * @apiParam {String} mobile 收货人手机
     * @apiParam {String} lonlat 收货地址坐标
     * @apiParam {String} area_adcode 区行政编码
     * @apiParam {String} province_name 省名称
     * @apiParam {String} [city_name] 市名称
     * @apiParam {String} [area_name] 区名称
     * @apiParam {String} address_name 地址名称
     * @apiParam {String} address 详细地址名称
     * @apiParam {String} [telepnone] 电话
     * @apiParam {String} [flag] 标记
     * @apiParam {String} [default] 是否默认
     *
     * @apiSampleRequest /app/v2/?service=user.addAddr&source=app
     */
    public function addAddr()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $params['url'] = $url = CURRENT_VERSION_USER_API . '/addAddr';
        $uid = $this->getUid();
        $params['data'] = array_merge(['uid' => $uid], $this->input->get(), $this->input->post());
        $params['method'] = 'get';
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
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    /**
     * @api {post} / 修改收货地址
     * @apiDescription 修改收货地址
     * @apiGroup user.address
     * @apiName updateAddr
     *
     * @apiParam {String} connect_id 登录Token
     * @apiParam {String} address_id 地址ID
     * @apiParam {String} name 收货人姓名
     * @apiParam {String} mobile 收货人手机
     * @apiParam {String} lonlat 收货地址坐标
     * @apiParam {String} area_adcode 区行政编码
     * @apiParam {String} province_name 省名称
     * @apiParam {String} [city_name] 市名称
     * @apiParam {String} [area_name] 区名称
     * @apiParam {String} address_name 地址名称
     * @apiParam {String} address 详细地址名称
     * @apiParam {String} [telepnone] 电话
     * @apiParam {String} [flag] 标记
     * @apiParam {String} [default] 是否默认
     *
     * @apiSampleRequest /app/v2/?service=user.updateAddr&source=app
     */
    public function updateAddr()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $params['url'] = $url = CURRENT_VERSION_USER_API . '/updateAddr';
        $uid = $this->getUid();
        $params['data'] = array_merge(['uid' => $uid], $this->input->get(), $this->input->post());
        $params['method'] = 'get';
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
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    /**
     * @api {post} / 删除收货地址
     * @apiDescription 删除收货地址
     * @apiGroup user.address
     * @apiName deleteAddr
     *
     * @apiParam {String} connect_id 登录Token
     * @apiParam {String} address_id 地址ID
     *
     * @apiSampleRequest /app/v2/?service=user.deleteAddr&source=app
     */
    public function deleteAddr()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $params['url'] = $url = CURRENT_VERSION_USER_API . '/deleteAddr';
        $uid = $this->getUid();
        $params['data'] = array_merge(['uid' => $uid], $this->input->get(), $this->input->post());
        $params['method'] = 'get';
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
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    /**
     * @api {post} / 生成充值单
     * @apiDescription 生成充值单
     * @apiGroup user.addTrade
     * @apiName addTrade
     *
     * @apiParam {String} connect_id 登录Token
     * @apiParam {String} money 金额
     *
     * @apiSampleRequest /app/v2/?service=user.addTrade&source=app
     */
    public function addTrade()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);

        $url = CURRENT_VERSION_USER_API . '/addIncomeTrade';
        $uid = $this->getUid();
        $request = http_build_query(array_merge(['uid' => $uid,'payment' => '微信支付', 'msg' => 'App充值单'], $this->input->get(), $this->input->post()));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $response = json_decode($result->response, true);

        $codeFirst = substr($code, 0, 1);
        $logContent['id'] = $this->request_id;
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
            $gatewayResponse['msg'] = 'succ';
            $gatewayResponse['data']['trade_no'] = $response['trade_number'];
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
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    /**
     * @api              {get} /v1/user/changeCardCode 口令红包
     * @apiDescription   口令红包
     * @apiGroup         user
     * @apiName          changeCardCode
     * @apiParam {String} [connect_id] 登录Token
     * @apiParam {String} [code] 口令
     * @apiSampleRequest /app/v2/?service=user.changeCardCode&source=app
     **/
    public function changeCardCode()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $url = $this->config->item('user', 'service') . '/v1' . '/card/changeCardCode';
        $uid = $this->getUid();
        $request = http_build_query(array_merge($this->input->post(), array('uid' => $uid)));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;
        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2) {
            $gateway_response['data'] = array();
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = isset($service_response['msg']) ? $service_response['msg'] : '成功';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg']) ? $service_response['msg'] : '失败';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if ($this->response == null) {
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    public function rechargeActive()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $url = $this->config->item('user', 'service') . '/v1' . '/trade/rechargeActive';
        $request = http_build_query($this->input->post());
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;
        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = isset($service_response['msg']) ? $service_response['msg'] : '成功';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg']) ? $service_response['msg'] : '失败';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    public function rechargeSuccess()
    {
        $gateway_response = array();
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $uid = $this->getUid();
        $url = $this->config->item('user', 'service') . '/v1' . '/trade/rechargeSuccess';
        $request = http_build_query(array_merge($this->input->post(), array('uid'=>$uid)));
        $result = $this->restclient->post($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;
        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        if ($code_first == 5) {
            $log_tag = 'ERROR';
            $log_content['response']['content'] = htmlspecialchars($result->response);//出现错误时的日志记录内容
        } elseif ($code_first == 2) {
            $gateway_response['data'] = $service_response;
            $gateway_response['code'] = '200';
            $gateway_response['msg'] = isset($service_response['msg']) ? $service_response['msg'] : '成功';
            $log_tag = 'INFO';
        } elseif ($code_first == 3 || $code_first == 4) {
            $gateway_response['code'] = '300';
            $gateway_response['msg'] = isset($service_response['msg']) ? $service_response['msg'] : '失败';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }
}
