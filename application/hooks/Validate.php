<?php
class Validate extends CI_Controller {
	public function connect_id() {
		parent::__construct();
		//@TODO,前置判断用户的connect_id是否还有效
		try {
			$service = $this->input->get_post('service');
			//service参数中带有user.都不强制验证，因为登录操作、注册操作都包含 connect_id 会导致逻辑出错
			if (!(stristr($service, 'user.') || stristr($service, 'passport.')) && $this->input->get_post('connect_id')) {
				$connect_id = $this->input->get_post('connect_id');
				session_id($connect_id);
				session_start();
				if (!(isset($_SESSION['user_detail']) && isset($_SESSION['user_detail']['id']))) throw new \Exception('400|登录信息已过期,请重新登录');
				session_write_close();
			}
		} catch (\Exception $e) {
			session_write_close();
			$error = explode('|', $e->getMessage());
			exit(json_encode(array('code' => $error[0], 'msg' => $error[1])));
		}
	}
}