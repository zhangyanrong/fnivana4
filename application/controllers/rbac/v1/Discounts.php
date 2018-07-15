<?php
class Discounts extends CI_Controller {

	public function __construct() {
		parent::__construct();
		$this->load->helper('http');
		$this->load->library('restclient');
		$this->restclient->set_option('base_url', 'http://caiyunchen.cart.guantest.fruitday.com');
	}

	function get() {
		$result = $this->restclient->get("/v3/discounts", $_GET);
		send($result->info->http_code, json_decode($result->response));
	}

	function remove() {
		$id = params('id');

		$result = $this->restclient->delete("/v3/discounts/{$id}");
		send($result->info->http_code, json_decode($result->response));
	}

	function add() {
		$result = $this->restclient->post("/v3/discounts", $_REQUEST);
		send($result->info->http_code, json_decode($result->response));
	}

}
