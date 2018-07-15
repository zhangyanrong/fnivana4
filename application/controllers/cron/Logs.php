<?php
use Aws\S3\S3Client;
class Logs extends CI_Controller {
	public function __construct() {
		parent::__construct();
		if (!is_cli()) exit('请以cli模式运行该程序');
// 		pr(memory_get_usage());
	}

	public function __destruct() {
// 		pr(memory_get_usage());
	}
	
	public function upload_log2S3($date_string = '') {
		$this->base_path = LOG_PATH . '/' . SITE_URL;
		$version_list = self::get_version_list();
		
		$tag_arr = array('INFO', 'ERROR', 'DEBUG');
		$transfer_file_list = array();
		$choose_date = $date_string ? date('Y_m_d', strtotime($date_string)) : date('Y_m_d', strtotime('-1 days'));
		
		//根据版本列出理论存在的日志清单
		foreach ($version_list as $v) {
			$temp_list = array();
			$file_path = $this->base_path . '/' . $v . '/' . $choose_date;
			if (is_dir($file_path)) $temp_list = scandir($file_path);
			foreach ($temp_list as $vv) {
				if (stristr($vv, '.gz')) $transfer_file_list[] = $file_path . '/' . $vv;
			}
		}
		//转储日志文件
		foreach ($transfer_file_list as $file) {
			self::begin_transfer_log($file);
		}
	}
	
	private function get_version_list() {
		$file_list = scandir($this->base_path);
		foreach ($file_list as $v) {
			if (in_array($v, array('.', '..'))) continue;
			$version_list[] = $v;
		}
		return $version_list;
	}
	
	private function begin_transfer_log($file) {
		$bucket = 'fdaycdn.fruitday.com';
		$file_info_arr = explode('/', $file);
		$s3_key = SITE_URL . '/' . $_SERVER['HOSTNAME'] . '/' . end($file_info_arr);//@TODO
		
		require_once APPPATH.'plugins/aws/aws-autoloader.php';
		$client = S3Client::factory ( array (
				'region'=>'cn-north-1',
				'version'=>'latest',
				'key' => 'AKIAPFZ5G3A3XR6EETHQ',
				'secret' => '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'
		) );
		
		$result = $client->putObject(array(
				'Bucket' => $bucket,
				'Key' => $s3_key,
				'SourceFile' => $file,
		));
		
		$log_content = 'path_transfer:' . $file . ' => https://s3.cn-north-1.amazonaws.com.cn/' . $bucket . '/' . $s3_key . "\r\n";//@TODO,因为返回结果是私有的，暂时自己拼接
		$transfer_log_file = $this->base_path . '/transfer_log_file';
		file_put_contents($transfer_log_file , $log_content . "\r\n", FILE_APPEND);
		echo 'see transfer log in:' . $transfer_log_file . "\r\n";
		unlink($file);
	}
}
?>