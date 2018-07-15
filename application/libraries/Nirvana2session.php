<?php
//将 apigateway 中的 session 写入niravana2中
class Nirvana2session {
	public function __construct($arr) {
		$this->CI =& get_instance();
		if (!isset($arr['session_id'])) exit('miss session_id');
		$this->CI->config->load('memcached');
		$mem_host = $this->CI->config->item('hostname', 'nirvana2');
		$mem_port = $this->CI->config->item('port', 'nirvana2');
		$this->session_id = $arr['session_id'];
		$mem = new Memcached();
		$mem->addServer($mem_host, $mem_port);//@TODO
		$this->mem = $mem;
		$this->session_data = array();
		self::base_sess_data();
	}
	
	/**
	 * @method 基础session数据
	 */
	private function base_sess_data() {
		$session_data = &$this->session_data;
		$session_data['session_id']		= $this->session_id;
		$session_data['user_agent']		= substr($this->CI->input->user_agent(), 0, 120);//与nirvana2保持一致此处属于不更新数据
		$session_data['ip_address']		= $this->CI->input->ip_address();//与nirvana2保持一致此处属于不更新数据
		$session_data['last_activity']	= time();//与nirvana2保持一致此处属于不更新数据
		$session_data['user_data']		= '';
	}
	
	public function __destruct() {
		$this->mem->quit();
	}
	
	public function get($key = '') {
		$tmp_data = unserialize($this->mem->get($this->session_id));
		
		$return_data = unserialize($tmp_data['user_data']);
		if ($key) $return_data = $return_data[$key];
		return $return_data;
	}
	
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
	
	//主动释放
	public function close() {
		$this->mem->quit();
	}
	
	//@TODO,删除某个session
	public function destory() {
		$this->mem->delete($this->session_id);
	}
	
	//@TODO,同步session至ci_sessions表
	public function sync() {
		
	}
	
	//@TODO,异步同步session至ci_sessions表
	public function async() {
		
	}
}
