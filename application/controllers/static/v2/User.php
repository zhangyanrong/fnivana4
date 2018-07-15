<?php
class User extends CI_Controller {
	private $response;

	public function __construct() {
		parent::__construct();
		$this->load->config('service');
		$this->load->library('restclient');
		$this->load->helper('public');
	}

	public function __destruct() {
		echo json_encode($this->response);
		if (!function_exists("fastcgi_finish_request")) {
			function fastcgi_finish_request() { }//为windows兼容
		}
		fastcgi_finish_request();
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
            session_write_close();
        }
        return (int)$uid;
    }

    private function doUserServer($url, $method='GET', $parameters=[], $options = [])
    {
        $this->restclient->set_option('base_url', $this->config->item('user', 'service'));
        $this->restclient->set_option('curl_options', $options);
        $data = $this->restclient->execute($url, $method, array_merge($this->input->get(), $this->input->post(), ['uid' => (int)$this->getUid()], $parameters));
        $response = [];
        if($data->response){
            $response = json_decode($data->response, true);
            if(json_last_error() !== JSON_ERROR_NONE){
                $response = $data->response;
            }
        }

        if ($data->info->http_code != 200) {
            if(is_array($response) && !empty($response['code'])){
                $this->response = ['code' => $response['code'], 'msg' => $response['msg'] ?: '服务器异常,请重试!', 'data' => []];
            }else{
                $this->response = ['code' => 300, 'msg' => $response ?: '服务器异常,请重试!', 'data' => []];
            }
            return;
        }
        if(is_array($response)){
            $this->response = ['code' => 200, 'data' => $response, 'msg' => ''];
        }else{
            $this->response = ['code' => 200, 'data' => [], 'msg' => $response];
        }

    }

    /**
     * @api              {get} /static/v2/ 有效积点明细
     * @apiDescription   有效积点明细
     * @apiGroup         fuli
     * @apiName          pointDetail
     *
     * @apiParam {String} [connect_id] connect_id
     *
     * @apiSampleRequest /static/v2/?service=user.fuliHome&source=app
     **/
    public function fuliHome(){
        $this->doUserServer(sprintf("v1/user_fuli/%s/", __FUNCTION__));
    }

    /**
     * @api              {get} /static/v2/ 积点记录
     * @apiDescription   积点记录
     * @apiGroup         fuli
     * @apiName          getPointTradeList
     *
     * @apiParam {String} [connect_id] connect_id
     *
     * @apiSampleRequest /static/v2/?service=user.getPointTradeList&source=app
     **/
    public function getPointTradeList(){
        $this->doUserServer(sprintf("v1/user_fuli/%s/", __FUNCTION__));
    }

    public function getHot()
    {
        $this->doUserServer(sprintf("v1/CustomerService/%s/", __FUNCTION__));
    }

    public function getClassList()
    {
        $this->doUserServer(sprintf("v1/CustomerService/%s/", __FUNCTION__));
    }

    public function search()
    {
        $this->doUserServer(sprintf("v1/CustomerService/%s/", __FUNCTION__));
    }
}