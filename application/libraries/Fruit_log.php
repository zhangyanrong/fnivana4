<?php
class fruit_log {
	private $save_data = array();
	private $allow_tags = array('INFO', 'DEBUG', 'ERROR');
	
	public function __construct() {
		foreach ($this->allow_tags as $v) $data[$v] = array();
		$this->save_data = $data;
	}
	
	/**
	 * @method 直接保存日志方法
	 * @param string $tag
	 * @param string $contents
	 * @return bool
	 * 
	 * @example
	 * $tag = 'INFO';//INFO|DEBUG|ERROR
	 * $content = 'sdisisksks';//
	 */
	public function log($tag, $contents, &$returnParam = array()) {
		$tag = strtoupper($tag);
		try {
			if (!defined('API_VERSION_FOLDER')) throw new \Exception('未定义API版本对应的目录');
			if (!in_array($tag, $this->allow_tags)) throw new \Exception('tag参数错误');
			$file_path = LOG_PATH . '/' . SITE_URL . '/' . API_VERSION_FOLDER;
			file_exists($file_path) OR mkdir($file_path, 0755, TRUE);
			$file_name = $file_path . '/' . date('Y_m_d') . '_' . $tag;
			$result = file_put_contents($file_name, $contents . "\r\n", FILE_APPEND);//@TODO,可能存在效率问题
			if (!$result) throw new \Exception('保存失败');
			return true;
		} catch (\Exception $e) {
			$returnParam['error_msg'] = $e->getMessage();
			return false;
		}
	}
	
	/**
	 * @method 记录埋点信息,需配套本类save()方法使用
	 */
	public function track($tag, $contents) {
		if (!in_array(strtoupper($tag), $this->allow_tags)) throw new \Exception('tag参数错误');
		$save_data = &$this->save_data;
		array_push($save_data[strtoupper($tag)], $contents);
		return true;
	}
	
	/**
	 * @method 程序析构函数执行是批量保存日志方法
	 */
	public function save() {
		foreach ($this->save_data as $tag => $content_arr) {
			$file_path = LOG_PATH . '/' . SITE_URL . '/' . API_VERSION_FOLDER;
			file_exists($file_path) OR mkdir($file_path, 0755, TRUE);
			$file_path_date = $file_path . '/' . date('Y_m_d'); 
			file_exists($file_path_date) OR mkdir($file_path_date, 0755, TRUE);
			$file_name = $file_path_date . '/' . date('Y_m_d-H') . '_' . $tag;
			if (!$content_arr) continue;
			foreach ($content_arr as $content) {
				$result = file_put_contents($file_name, $content . "\r\n", FILE_APPEND);//@TODO,可能存在效率问题
			}
		}
	}
}