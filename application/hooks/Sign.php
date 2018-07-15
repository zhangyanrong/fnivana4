<?php
class Sign extends CI_Controller{
	public function validate() {
		$post_param = $this->input->post();
		$source = $this->input->get_post('source');
		$source_arr = explode('/', $source);
		$service_arr = explode('.', $this->input->get_post('service'));
		$method = $service_arr[1];
		$route_info = explode('_', $service_arr[0]);
		$class = $route_info[0];
		$api_version = $route_info[1];
		defined('API_VERSION_FOLDER') OR define('API_VERSION_FOLDER', $api_version);//@TODO

		/*为api/test提供, start */
		if (stristr($this->uri->uri_string, '/test')) {
			// dev和staging环境才可以访问
			$server_name     = php_uname("n");
			$allowed_servers = ['ip-10-0-1-236', 'ip-10-0-1-55'];
			if( !in_array($server_name, $allowed_servers) ) {
				die("apidoc machine not allowed");
			} else {
				return true;
			}
		}
		/* 为api/test提供, end */
		$request_sign = isset($post_param['sign']) ? $post_param['sign'] : '';
		unset($post_param['sign']);
		ksort($post_param);
		$query = '';
		foreach($post_param as $k => $v) {
			if ($v == '') continue;
			$query .= $k . '=' . $v . '&';
		}
// 		$validate_sign = md5(substr(md5($query.API_SECRET), 0,-1).'w');
		$validate_sign = md5(substr(md5($query.PRO_SECRET), 0,-1).'w');//@TODO
		$bool = false;
		$response['code']	= '300';//@TODO
		$response['msg']	= '签名错误';
		$this->response = array('code' => '300', 'msg' => '签名错误');
		if ($validate_sign == $request_sign) {
			$bool = true;
		} else {
			exit(json_encode($response));//@TODO
		}
		return $bool;
	}
}