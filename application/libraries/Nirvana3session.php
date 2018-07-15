<?php
class Nirvana3session {
	public function __construct() {
		$this->CI = &get_instance();
	}
	
	//需要保存至nirvana2的数据,结构和nirvana2保持基本统一
	public function set_userdata($data, $value = null) {
		isset($_REQUEST['connect_id']) && $_REQUEST['connect_id'] && session_id(htmlspecialchars($_REQUEST['connect_id']));
		
		session_start();
		$this->CI->load->library('Nirvana2session', array('session_id' => session_id()));
// 		if (isset($_REQUEST['connect_id'])) session_id(htmlspecialchars($_REQUEST['connect_id']));
		
		if (is_string($value)) $data = array($data => $value);
		if (is_array($data) > 0) {
			foreach ($data as $k => $v) {
// 				$_SESSION[$k] = $v;
				$_SESSION['user_detail'][$k] = $v;//@TODO,冗余一份数据给nirvana2迁移nirvana3时部分代码的问题
			}
			$this->CI->nirvana2session->set($data);//同时将session保存至nirvana2
		}
		
		session_write_close();
		return session_id();
	}
	
	public function userdata($key = null) {
		isset($_REQUEST['connect_id']) && $_REQUEST['connect_id'] && session_id(htmlspecialchars($_REQUEST['connect_id']));
		
		session_start();
		if (isset($key)) {
// 			$result = isset($_SESSION[$key]) ? $_SESSION[$key] : false;
			$result = isset($_SESSION['user_detail'][$key]) ? $_SESSION['user_detail'][$key] : false;
		} else {
			$result = $_SESSION['user_detail'];
		}
		session_write_close();
		return $result;
	}
	
	//销毁session
	public function destory() {
		isset($_REQUEST['connect_id']) && $_REQUEST['connect_id'] && session_id(htmlspecialchars($_REQUEST['connect_id']));
		session_start();
		$this->CI->load->library('Nirvana2session', array('session_id' => session_id()));
		$this->CI->nirvana2session->destory();
		session_destroy();
		session_write_close();
	}

	//@TODO
	public function get($key) {
		if (isset($_REQUEST['connect_id'])) session_id(htmlspecialchars($_REQUEST['connect_id']));
		return $_SESSION[$key];
	}
	
	//@TODO
	public function session($key, $value = null) {
		if (isset($_REQUEST['connect_id'])) session_id(htmlspecialchars($_REQUEST['connect_id']));

		session_start();
		$this->load->library('Nirvana2session', array('session_id' => session_id()));
		$result = '';//初始化返回内容
		if (isset($value)) {
			$_SESSION[$key] = $value;
			$this->nirvana2session->set(array($key => $value));
		} else {
			$result = $_SESSION[$key];
			$this->nirvana2session->get($key);
		}
		session_write_close();
		return $result;
	}
	
	//@TODO
	public function set($data, $value = null) {
		if (!$data) exit('miss key');
		$session_data = unserialize($this->mem->get($this->session_id));
		if (!$session_data) $session_data = $this->session_data;
		$userdata = &$session_data['user_data'];
		$tmp = unserialize($userdata);
		if (is_array($data)) {
			foreach ($data as $k => $v) $tmp[$k] = $v;
		} else {
			$tmp[$data] = $value;
		}
		$userdata = serialize($tmp);
		$this->mem->set($this->session_id, serialize($session_data));
	}
}