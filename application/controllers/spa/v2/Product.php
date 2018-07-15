<?php
class Product extends CI_Controller {

	public function __construct() {
		parent::__construct();
		$this->load->config('service');
		$this->product_service_url = $this->config->item('product', 'service') . '/v2';
		$this->load->library('api_process');
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

	/**
	 * @api	{get}	/	分类商品列表(新)
	 * @apiDescription	获取分类下的商品(新)
	 * @apiGroup		spa/product
	 * @apiName			getClassProductNew
	 *
	 * @apiParam {String} store_id_list 门店id,逗号分隔
	 * @apiParam {Number} class2_id 二级分类id
	 * @apiParam {Number} class3_id 三级分类id
	 * @apiParam {Number} [sort_type] 1:综合 2:销量 3:价格正序 4:价格逆序
	 * @apiParam {Number} tms_region_type 年轮
	 * @apiParam {String} source	渠道
	 *
	 * @apiSampleRequest /spa/v2?service=product.getClassProductNew
	 */
	public function getClassProductNew() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = $this->product_service_url . '/product/getClassProductNew';
		$query['store_id_list']		= require_request('store_id_list');
		$query['class2_id']			= require_request('class2_id');
		$query['class3_id']			= require_request('class3_id');
	 	$query['sort_type']			= $this->input->get_post('sort_type');
		$query['tms_region_type']	= require_request('tms_region_type');
		$query['source']			= require_request('source');
		 
		$params['url']		= $url;
		$params['data']		= $query;
		$params['method']	= 'get';
		$service_response	= $this->api_process->process($params);
		 
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200) {
			$this->response['code'] = '200';
			$this->response['data']	= $service_response;
		} else {
			$this->response['code']	= '300';
			$this->response['msg']	= isset($service_response['msg']) ? $service_response['msg'] : '未知错误';
		}
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}


	/**
	 * @api	{post} / 分类列表(新)
	 * @apiDescription	获取分类列表(新)
	 * @apiGroup	spa/product
	 * @apiName		getClassListNew
	 *
	 * @apiParam {String} store_id_list 门店id,逗号分隔
	 * @apiParam {Number} [class_id] 选定的一级分类id, 传空则取第一个
	 * @apiParam {Number} tms_region_type 年轮
	 *
	 * @apiSampleRequest /spa/v2?service=product.getClassListNew
	 */
	public function getClassListNew() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = $this->product_service_url . '/product/getClassListNew';
		$query['store_id_list']		= require_request('store_id_list');
		$query['class_id']			= $this->input->get_post('class_id');
		$query['tms_region_type']	= require_request('tms_region_type');
		
		$params['url']		= $url;
		$params['data']		= $query;
		$params['method']	= 'get';
		$service_response	= $this->api_process->process($params);
		
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200) {
			$this->response['code'] = '200';
			$this->response['data']	= $service_response;
		} else {
			$this->response['code']	= '300';
			$this->response['msg']	= isset($service_response['msg']) ? $service_response['msg'] : '未知错误';
		}
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api	{post}	/	已关注商品的相关商品
	 * @apiDescription	已关注商品的相关商品
	 * @apiGroup		spa/product
	 * @apiName			markedProducts
	 *
	 * @apiParam {String} connect_id	登录成功后返回的串
	 * @apiParam {String} store_id_list	门店id,逗号分隔
	 * @apiParam {Number} product_id	产品id
	 * @apiParam {Number} tms_region_type	年轮
	 * @apiParam {Number} [page=1]		第几页
	 * @apiParam {Number} [limit=10]	每页显示几条
	 *
	 * @apiSampleRequest /spa/v2?service=product.markedProducts
	 */
	public function markedProducts() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = $this->product_service_url . '/product/markedProducts';
		$query['uid']				= get_uid();
		$query['store_id_list']		= require_request('store_id_list');
		$query['product_id']		= require_request('product_id');
		$query['tms_region_type']	= require_request('tms_region_type');
		$query['page_id']			= intval($this->input->get_post('page_id'));
		$query['limit']				= intval($this->input->get_post('limit'));
		
		$params['url']		= $url;
		$params['data']		= $query;
		$params['method']	= 'get';
		$service_response	= $this->api_process->process($params);
		
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200) {
			$this->response['code'] = '200';
			$this->response['data']	= $service_response;
		} else {
			$this->response['code']	= '300';
			$this->response['msg']	= isset($service_response['msg']) ? $service_response['msg'] : '未知错误';
		}
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api	{post}	/	促销商品列表
	 * @apiDescription	促销商品列表
	 * @apiGroup		spa/product
	 * @apiName			promotionList_v550
	 *
	 * @apiParam {String} store_id_list 门店id,逗号分隔
	 * @apiParam {Number} pmt_id 促销id
	 * @apiParam {Number} [tms_region_type] 年轮
	 *
	 * @apiSampleRequest /spa/v2?service=product.promotionList_v550
	 */
	public function promotionList_v550() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = $this->product_service_url . '/product/promotionList_v550';
		$query['store_id_list']		= require_request('store_id_list');
		$query['pmt_id']			= require_request('pmt_id');
		$query['tms_region_type']	= $this->input->get_post('tms_region_type');
		
		$params['url']		= $url;
		$params['data']		= $query;
		$params['method']	= 'get';
		$service_response	= $this->api_process->process($params);
		
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200) {
			$this->response['code'] = '200';
			$this->response['data']	= $service_response;
		} else {
			$this->response['code']	= '300';
			$this->response['msg']	= isset($service_response['msg']) ? $service_response['msg'] : '未知错误';
		}
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api	{post}	/spa/v2/	商品详情
	 * @apiDescription	商品详情
	 * @apiGroup		spa/product
	 * @apiName			getProductInfo
	 * @apiVersion		2.0.0
	 *
	 * @apiParam {String} store_id_list 门店id,逗号分隔
	 * @apiParam {Number} product_id 产品id
	 * @apiParam {Number} [store_id] 指定门店id(非必填)
	 * @apiParam {Number} tms_region_type 年轮
	 *
	 * @apiSampleRequest /spa/v2/?service=product.getProductInfo
	 **/
	public function getProductInfo(){
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = $this->product_service_url . '/productDetail';
		$query['store_id_list']		= require_request('store_id_list');
		$query['product_id']		= require_request('product_id');
		$query['store_id']			= $this->input->get_post('store_id');
		$query['tms_region_type']	= require_request('tms_region_type');
		 
		$params['url']		= $url;
		$params['data']		= $query;
		$params['method']	= 'get';
		$service_response	= $this->api_process->process($params);
		 
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200) {
			$this->response['code'] = '200';
			$this->response['data']	= $service_response;
			isset($service_response['sale']) && $this->response['sale'] = $service_response['sale'];
		} else {
			$this->response['code']	= '300';
			$this->response['msg']	= isset($service_response['msg']) ? $service_response['msg'] : '未知错误';
		}
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api	{get}	/spa/v2/	赠品详情
	 * @apiDescription	赠品详情
	 * @apiGroup		spa/product
	 * @apiName			productDetailGift
	 * @apiVersion		2.0.0
	 *
	 * @apiParam {String} store_id_list 门店id,逗号分隔
	 * @apiParam {Number} tms_region_type 年轮
	 * @apiParam {Number} product_id 产品id
	 * @apiParam {Number} [store_id] 指定门店id(非必填)
	 *
	 * @apiSampleRequest /spa/v2/?service=product.getGiftInfo
	 **/
	public function getGiftInfo(){
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = $this->product_service_url . '/productDetail/gift/';
		$query['store_id_list']		= require_request('store_id_list');
		$query['product_id']		= require_request('product_id');
		$query['store_id']			= $this->input->get_post('store_id');
		$query['tms_region_type']	= require_request('tms_region_type');
			
		$params['url']		= $url;
		$params['data']		= $query;
		$params['method']	= 'get';
		$service_response	= $this->api_process->process($params);
			
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200) {
			$this->response['code'] = '200';
			$this->response['data']	= $service_response;
			isset($service_response['sale']) && $this->response['sale'] = $service_response['sale'];
		} else {
			$this->response['code']	= '300';
			$this->response['msg']	= isset($service_response['msg']) ? $service_response['msg'] : '未知错误';
		}
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api	{get}	/spa/v2/	评论
	 * @apiDescription  评论
	 * @apiGroup		spa/product
	 * @apiName			comments
	 *
	 * @apiParam {Number} id 商品id
	 *
	 * @apiSampleRequest /spa/v2/?service=product.comments
	 **/
	public function comments() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = $this->product_service_url . '/comment';
		$query['id']		= require_request('id');
			
		$params['url']		= $url;
		$params['data']		= $query;
		$params['method']	= 'get';
		$service_response	= $this->api_process->process($params);

		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200) {
			$this->response['code'] = '200';
			$this->response['data']	= $service_response;
		} else {
			$this->response['code']	= '300';
			$this->response['msg']	= isset($service_response['msg']) ? $service_response['msg'] : '未知错误';
		}
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api	{get}	/app/v2/	评论比例
	 * @apiDescription	评论比例
	 * @apiGroup	spa/product
	 * @apiName		commentsRate
	 *
	 * @apiParam {Number} id 商品id
	 *
	 * @apiSampleRequest /spa/v2/?service=product.commentsRate
	 */
	public function commentsRate(){
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = $this->product_service_url . '/comment/commentsRate';
		$query['id']		= require_request('id');
			
		$params['url']		= $url;
		$params['data']		= $query;
		$params['method']	= 'get';
		$service_response	= $this->api_process->process($params);
		
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200) {
			$this->response['code'] = '200';
			$this->response['data']	= $service_response;
		} else {
			$this->response['code']	= '300';
			$this->response['msg']	= isset($service_response['msg']) ? $service_response['msg'] : '未知错误';
		}
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api			  {get} /app/v2/ 搜索商品
	 * @apiDescription   商品搜索
	 * @apiGroup		 product
	 * @apiName		  search
	 *
	 * @apiParam {String} keyword 搜索关键字
	 * @apiParam {String} [store_id_list] 门店ID列表
	 * @apiParam {int} [tms_region_type] 区域类型
	 * @apiParam {String} [page_size] 分页行数
	 * @apiParam {String} [curr_page] 分页页数
	 * @apiParam {String} [channel] 渠道
	 *
	 * @apiSampleRequest /spa/v2/?service=product.search&source=app
	 */
	public function search() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = $this->product_service_url . '/search';
		$query['keyword']			= require_request('keyword');
		$query['store_id_list']		= $this->input->get_post('store_id_list');
		$query['tms_region_type']	= $this->input->get_post('tms_region_type');
		$query['page_size']			= intval($this->input->get_post('page_size'));
		$query['curr_page']			= intval($this->input->get_post('curr_page'));
		$query['channel']			= $this->input->get_post('channel');
			
		$params['url']		= $url;
		$params['data']		= $query;
		$params['method']	= 'get';
		$service_response	= $this->api_process->process($params);
		
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200) {
			$this->response['code'] = '200';
			$this->response['data']	= $service_response;
		} else {
			$this->response['code']	= '300';
			$this->response['msg']	= isset($service_response['msg']) ? $service_response['msg'] : '未知错误';
		}
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}

	/**
	 * @api	{get} /app/v2/ 热搜关键词
	 * @apiDescription	商品搜索
	 * @apiGroup	spa/product
	 * @apiName		getHotKeyword
	 *
	 * @apiParam {String} store_id_list 门店ID列表
	 *
	 * @apiSampleRequest /spa/v2/?service=product.getHotKeyword
	 */
	public function getHotKeyword() {
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = $this->product_service_url . '/search/getHotKeyword';
		$query['store_id_list']		= require_request('store_id_list');
			
		$params['url']		= $url;
		$params['data']		= $query;
		$params['method']	= 'get';
		$service_response	= $this->api_process->process($params);
		
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200) {
			$this->response['code'] = '200';
			$this->response['data']	= $service_response;
		} else {
			$this->response['code']	= '300';
			$this->response['msg']	= isset($service_response['msg']) ? $service_response['msg'] : '未知错误';
		}
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}


	/**
	 * @api	{get}	/spa/v2/	搜索关键字
	 * @apiDescription	关键字补全
	 * @apiGroup		spa/product
	 * @apiName			getKeyword
	 *
	 * @apiParam {String} [keyword] 搜索关键字
	 *
	 * @apiSampleRequest /app/v2/?service=product.getKeyword&source=app
	 **/
	public function getKeyword(){
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||start||request_id:' . $this->request_id);
		$url = $this->product_service_url . '/search/getKeyword';
		$query['keyword']		= require_request('keyword');

		$params['url']		= $url;
		$params['data']		= $query;
		$params['method']	= 'get';
		$service_response	= $this->api_process->process($params);
		
		$response_result = $this->api_process->get('result');
		if ($response_result->info->http_code == 200) {
			$this->response['code'] = '200';
			$this->response['data']	= $service_response;
		} else {
			$this->response['code']	= '300';
			$this->response['msg']	= isset($service_response['msg']) ? $service_response['msg'] : '未知错误';
		}
		$this->fruit_log->track('INFO', date('H:i:s') . ';controller-method:' . __METHOD__ . '||end||request_id:' . $this->request_id);
	}
}