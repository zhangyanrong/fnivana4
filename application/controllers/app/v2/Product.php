<?php
class Product extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->config('service');
        $this->product_service_url = $this->config->item('product', 'service') . '/v2';
        $this->load->library('restclient');
        $this->load->library('fruit_log');
        $this->load->helper('public');
        $this->request_id = date('Y_m_d_H_i_s') . '_' . md5($this->input->get_post('device_id') . mt_rand(10000, 99999));//用于记录日志用

        $this->load->library('api_process');
    }

    public function __destruct() {
        if (isset($this->response) && $this->response) echo json_encode($this->response, JSON_UNESCAPED_UNICODE);
        if (!function_exists("fastcgi_finish_request")) {
            function fastcgi_finish_request() {
            }//为windows兼容
        }
        fastcgi_finish_request();
        $this->fruit_log->save();
    }

    private function getUid(){
        static $uid = null;
        if(isset($uid)){
            return $uid;
        }
        $connect_id = $this->input->get_post('connect_id');
        $uid = 0;
        if($connect_id){
            session_id($connect_id);
            session_start();
            $uid = isset($_SESSION['user_detail']['id']) ? $_SESSION['user_detail']['id'] : 0;
            session_write_close();//增加使用完session后释放session资源
        }
        return (int)$uid;
    }


    /**
     * @api              {post} / 分类商品列表(新)
     * @apiDescription   获取分类下的商品(新)
     * @apiGroup         product
     * @apiName          getClassProductNew
     *
     * @apiParam {String} store_id_list 门店id,逗号分隔
     * @apiParam {Number} class2_id 二级分类id
     * @apiParam {Number} class3_id 三级分类id
     * @apiParam {Number} sort_type 1:综合 2:销量 3:价格正序 4:价格逆序
     * @apiParam {Number} tms_region_type 年轮
     *
     * @apiSampleRequest /app/v2?service=product.getClassProductNew&source=app
     */
    public function getClassProductNew() {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $url = $this->product_service_url . '/product/getClassProductNew';
        $request = http_build_query(array_merge(array('uid'=>$this->getUid()), $this->input->post()));

        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        $gateway_response = [];
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
            $gateway_response['msg'] = '获取失败';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if($this->response == null){
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }


    /**
     * @api              {post} / 分类列表(新)
     * @apiDescription   获取分类列表(新)
     * @apiGroup         product
     * @apiName          getClassListNew
     *
     * @apiParam {String} store_id_list 门店id,逗号分隔
     * @apiParam {Number} class_id 选定的一级分类id, 传空则取第一个
     * @apiParam {Number} tms_region_type 年轮
     *
     * @apiSampleRequest /app/v2?service=product.getClassListNew&source=app
     */
    public function getClassListNew() {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $url = $this->product_service_url . '/product/getClassListNew';
        $request = http_build_query(array_merge(array('uid'=>$this->getUid()), $this->input->post()));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        $gateway_response = [];
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
            $gateway_response['msg'] = '获取失败';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if($this->response == null){
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    /**
     * @api              {post} / 已关注商品的相关商品
     * @apiDescription   已关注商品的相关商品
     * @apiGroup         product
     * @apiName          markedProducts
     *
     * @apiParam {String} store_id_list 门店id,逗号分隔
     * @apiParam {Number} product_id 产品id
     * @apiParam {Number} tms_region_type 年轮
     * @apiParam {Number} [page=1] 第几页
     * @apiParam {Number} [limit=10] 每页显示几条
     *
     * @apiSampleRequest /app/v2?service=product.markedProducts&source=app
     */
    function markedProducts() {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $url = $this->product_service_url . '/product/markedProducts';
        $request = http_build_query(array_merge(array('uid'=>$this->getUid()), $this->input->post()));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $gateway_response = [];
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
            $gateway_response['msg'] = '获取失败';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = isset($gateway_response) ? $gateway_response : null;//框架中有析构函数，为避免可能失效不使用exit
        if($this->response == null){
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }



    /**
     * @api              {post} / 已关注商品
     * @apiDescription   已关注商品
     * @apiGroup         product
     * @apiName          markedList
     *
     * @apiParam {String} store_id_list 门店id,逗号分隔
     * @apiParam {Number} product_id 产品id
     * @apiParam {Number} tms_region_type 年轮
     * @apiParam {Number} [page=1] 第几页
     * @apiParam {Number} [limit=10] 每页显示几条
     *
     * @apiSampleRequest /app/v2?service=product.markedList&source=app
     */
    function markedList() {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $url = $this->product_service_url . '/product/markedList';
        $request = http_build_query(array_merge(array('uid'=>$this->getUid()), $this->input->post()));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        $gateway_response = [];
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
            $gateway_response['msg'] = '获取失败';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if($this->response == null){
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    /**
     * @api              {post} / 根据商品id获取列表
     * @apiDescription   根据商品id获取列表
     * @apiGroup         product
     * @apiName          productList
     *
     * @apiParam {String} store_id_list 门店id,逗号分隔
     * @apiParam {String} product_id_list 产品id,逗号分隔
     * @apiParam {Number} tms_region_type 年轮
     * @apiParam {Number} [page=1] 第几页
     * @apiParam {Number} [limit=10] 每页显示几条
     * @apiParam {Number} [show_tuan] 过滤参数
     * @apiParam {String} [channel] 过滤参数
     *
     *
     * @apiSampleRequest /app/v2?service=product.productList&source=app
     */
    function productList() {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $url = $this->product_service_url . '/product/productList';
        $request = http_build_query(array_merge(array('uid'=>$this->getUid()), $this->input->post()));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        $gateway_response = [];
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
            $gateway_response['msg'] = '获取失败';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if($this->response == null){
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    /**
     * @api              {post} / 促销商品列表
     * @apiDescription   促销商品列表
     * @apiGroup         product
     * @apiName          promotionList_v550
     *
     * @apiParam {String} store_id_list 门店id,逗号分隔
     * @apiParam {Number} pmt_id 促销id
     * @apiParam {Number} tms_region_type 年轮
     *
     * @apiSampleRequest /app/v2?service=product.promotionList_v550&source=app
     */
    function promotionList_v550() {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $url = $this->product_service_url . '/product/promotionList_v550';
        $request = http_build_query(array_merge(array('uid'=>$this->getUid()), $this->input->post()));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        $gateway_response = [];
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
            $gateway_response['msg'] = '获取失败';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if($this->response == null){
            $this->response = ['code' => 200, 'data' => (object)array(), 'msg' => ''];
        }

        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }

    /**
     * @api              {post} / 促销商品列表
     * @apiDescription   促销商品列表
     * @apiGroup         product
     * @apiName          promotionList_v550
     *
     * @apiParam {String} store_id_list 门店id,逗号分隔
     * @apiParam {String} product_no_group ProductNo数组
     * @apiParam {Number} tms_region_type 年轮
     *
     * @apiSampleRequest /app/v2?service=product.getCartRecommendation&source=app
     */
    function getCartRecommendation() {
    	$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
    	$url = $this->product_service_url . '/product/getCartRecommendation?request_id=' . $this->request_id;
    	$service_query = $this->input->post();
    	$service_query['uid']	= $this->getUid();
    	$params['url'] = $url;
    	$params['data']	= $service_query;
    	$params['method'] = 'get';
    	$service_response = $this->api_process->process($params);

    	$response_result = $this->api_process->get('result');
    	if ($response_result->info->http_code == 200) {
    		$api_gateway_response['code']	= '200';
    		$api_gateway_response['msg']	= '获取成功';
    		$api_gateway_response['data']	= $service_response;
    	} else {
    		$api_gateway_response['code']	= '300';
    		$api_gateway_response['msg']	= $service_response['msg'];
    	}
    	$this->response = $api_gateway_response;
    	$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }



    /**
     * @api              {post} /app/v2/ 商品详情
     * @apiDescription   商品详情
     * @apiGroup         product
     * @apiName          getProductInfo
     * @apiVersion      2.0.0
     *
     * @apiParam {String} store_id_list 门店id,逗号分隔
     * @apiParam {Number} product_id 产品id
     * @apiParam {Number} store_id 指定门店id(非必填)
     * @apiParam {Number} tms_region_type 年轮
     *
     * @apiSampleRequest /app/v2/?service=product.getProductInfo&source=app
     **/
    public function getProductInfo(){
        $this->restclient->set_option('base_url', $this->config->item('product', 'service'));
        $user = $this->restclient->get("v2/productDetail/", ['uid' => $this->getUid()] + array_merge($this->input->get(), $this->input->post()));
//         $platform = $this->input->get_post('platform');
//         $version = $this->input->get_post('version');
//         if (strtoupper($platform) == 'ANDROID' || (strtoupper($platform) == 'IOS' && version_compare($version, '5.9.1') > 0)) {
//             $user->response = str_replace('.jpg', '.webp', $user->response);
//         }
        $user->response = $user->response ? json_decode($user->response, true) : '';
        if ($user->info->http_code != 200) {
            $this->response = ['code' => 300, 'msg' => $user->response ?: '服务器异常,请重试!', 'data' => []];
            return;
        }

        if(!empty($user->response['sale'])){
            $user->response['sale'] = (object)$user->response['sale'];
        }
        $this->response = ['code' => 200, 'data' => $user->response, 'msg' => ''];
    }

    /**
     * @api              {get} /app/v2/ 赠品详情
     * @apiDescription   赠品详情
     * @apiGroup         product
     * @apiName          productDetailGift
     * @apiVersion      2.0.0
     *
     * @apiParam {String} store_id_list 门店id,逗号分隔
     * @apiParam {Number} tms_region_type 年轮
     * @apiParam {Number} product_id 产品id
     * @apiParam {Number} store_id 指定门店id(非必填)
     *
     * @apiSampleRequest /app/v2/?service=product.getGiftInfo&source=app
     **/
    public function getGiftInfo(){
        $this->restclient->set_option('base_url', $this->config->item('product', 'service'));
        $user = $this->restclient->get("v2/productDetail/gift/", ['uid' => $this->getUid()] + array_merge($this->input->get(), $this->input->post()));
        $user->response = $user->response ? json_decode($user->response, true) : '';
        if ($user->info->http_code != 200) {
            $this->response = ['code' => 300, 'msg' => $user->response ?: '服务器异常,请重试!', 'data' => []];
            return;
        }

        $this->response = ['code' => 200, 'data' => $user->response, 'msg' => ''];
    }

    /**
     * @api              {get} /app/v2/ 评论
     * @apiDescription   评论
     * @apiGroup         product
     * @apiName          comments
     *
     * @apiParam {Number} id 商品id
     *
     * @apiSampleRequest /app/v2/?service=product.comments&source=app
     **/
    public function comments(){
        $this->restclient->set_option('base_url', $this->config->item('product', 'service'));
        $user = $this->restclient->get("v2/comment/", array_merge($this->input->get(), $this->input->post()));
        $user->response = $user->response ? json_decode($user->response, true) : '';
        if ($user->info->http_code != 200) {
            $this->response = ['code' => 300, 'msg' => $user->response ?: '服务器异常,请重试!', 'data' => []];
            return;
        }
        if(!empty($user->response)){
            $user->response = array_map(function ($item){
                $item['reply'] = $item['reply'] ?: new stdClass();
                return $item;
            }, $user->response);
        }
        $this->response = ['code' => 200, 'data' => $user->response, 'msg' => ''];
    }

    /**
     * @api              {get} /app/v2/ 评论比例
     * @apiDescription   评论比例
     * @apiGroup         product
     * @apiName          commentsRate
     *
     * @apiParam {Number} id 商品id
     *
     * @apiSampleRequest /app/v2/?service=product.commentsRate&source=app
     */
    public function commentsRate(){
        $this->restclient->set_option('base_url', $this->config->item('product', 'service'));
        $user = $this->restclient->get("v2/comment/commentsRate", array_merge($this->input->get(), $this->input->post()));
        $user->response = $user->response ? json_decode($user->response, true) : '';
        if ($user->info->http_code != 200) {
            $this->response = ['code' => 300, 'msg' => $user->response ?: '服务器异常,请重试!', 'data' => []];
            return;
        }

        $this->response = ['code' => 200, 'data' => $user->response, 'msg' => ''];
    }

    /**
     * @api              {get} /app/v2/ 搜索商品
     * @apiDescription   商品搜索
     * @apiGroup         product
     * @apiName          search
     *
     * @apiParam {String} [keyword] 搜索关键字
     * @apiParam {String} [store_id_list] 门店ID列表
     * @apiParam {int} [tms_region_type] 区域类型
     * @apiParam {String} [page_size] 分页行数
     * @apiParam {String} [curr_page] 分页页数
     * @apiParam {String} [channel] 渠道
     *
     * @apiSampleRequest /app/v2/?service=product.search&source=app
     */
    public function search(){
        $this->restclient->set_option('base_url', $this->config->item('product', 'service'));
        $user = $this->restclient->get("v2/search/", ['uid' => $this->getUid()] + array_merge($this->input->get(), $this->input->post()));
        $user->response = $user->response ? json_decode($user->response, true) : '';
        if ($user->info->http_code != 200) {
            $this->response = ['code' => 300, 'msg' => $user->response ?: '服务器异常,请重试!', 'data' => []];
            return;
        }

        $this->response = ['code' => 200, 'data' => $user->response, 'msg' => ''];
    }

    /**
     * @api              {get} /app/v2/ 热搜关键词
     * @apiDescription   商品搜索
     * @apiGroup         product
     * @apiName          getHotKeyword
     *
     * @apiParam {String} [store_id_list] 门店ID列表
     *
     * @apiSampleRequest /app/v2/?service=product.getHotKeyword&source=app
     */
    public function getHotKeyword(){
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $url = $this->product_service_url . '/search/getHotKeyword';
        $request = http_build_query(array_merge(array('uid'=>$this->getUid()), $this->input->post()));
        $result = $this->restclient->get($url, $request);
        $code = $result->info->http_code;
        $service_response = json_decode($result->response, true);
        $log_content['id'] = $this->request_id;
        $log_content['request']['url'] = $url;
        $log_content['request']['content'] = $request;

        $log_content['response']['code'] = $code;
        $log_content['response']['content'] = $service_response;
        $code_first = substr($code, 0, 1);
        $gateway_response = [];
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
            $gateway_response['msg'] = '获取失败';
            $log_tag = 'INFO';
        }
        $this->fruit_log->track($log_tag, json_encode($log_content));
        $this->response = array();
        $this->response = $gateway_response;//框架中有析构函数，为避免可能失效不使用exit
        if($this->response == null){
            $this->response = ['code' => 200, 'data' => array(), 'msg' => ''];
        }

        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }


    /**
     * @api              {get} /app/v2/ 搜索关键字
     * @apiDescription   关键字补全
     * @apiGroup         product
     * @apiName          getKeyword
     *
     * @apiParam {String} [keyword] 搜索关键字
     *
     * @apiSampleRequest /app/v2/?service=product.getKeyword&source=app
     **/
    public function getKeyword(){
        $this->restclient->set_option('base_url', $this->config->item('product', 'service'));
        $user = $this->restclient->get("v2/search/getKeyword/", array_merge($this->input->get(), $this->input->post()));
        $user->response = $user->response ? json_decode($user->response, true) : '';
        if ($user->info->http_code != 200) {
            $this->response = ['code' => 300, 'msg' => $user->response ?: '服务器异常,请重试!', 'data' => []];
            return;
        }

        $this->response = ['code' => 200, 'data' => $user->response, 'msg' => ''];
    }

    /**
     * @api              {get} 根据 StoreId、商品编码来获取商品列表
     * @apiDescription   根据 StoreId、商品编码来获取商品列表
     * @apiGroup         product
     * @apiName          getProductByStore
     *
     * @apiParam {String} store_id_list 门店ID，多个用英文逗号分隔
     * @apiParam {String} field_key 商品编码类型，指明 field_val 字符串的类型。值为：'id' 或 'code'
     * @apiParam {String} field_val 商品编码列表，多个用英文逗号分隔。值为：商品ID字符串 或 外部编码字符串
     *
     * @apiSampleRequest /app/v2/?service=product.getProductByStore&source=app
     **/
    function getProductByStore()
    {
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
        $url = $this->product_service_url . '/product/getProductByStore';
        $service_query = array_merge($this->input->get(), $this->input->post(), ['uid' => get_uid()]);
        $params['url'] = $url;
        $params['data']	= $service_query;
        $params['method'] = 'get';
        $service_response = $this->api_process->process($params);

        $response_result = $this->api_process->get('result');
        if ($response_result->info->http_code == 200) {
            $api_gateway_response['code']	= '200';
            $api_gateway_response['msg']	= '获取成功';
            $api_gateway_response['data']	= $service_response;
        } else {
            $api_gateway_response['code']	= '300';
            $api_gateway_response['msg']	= $service_response;
        }
        $this->response = $api_gateway_response;
        $this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
    }
}