<?php

if(!function_exists('twoeGetCrossPromoterUrl')) {
	function twoeGetCrossPromoterUrl($current) {
		$url = 'http://twoenough.com/crosspromoter.js.php?ver=2';
		
		$data = array();
		
		$tags = explode(',', twoenough_get_option('2e_installed_products'));
		
		$products = array();
		
		foreach($tags as $tag) {
			$products[$tag] = twoenough_get_option($tag . '_version');
		}
		
		$data['installed_products'] = $products;
		$data['activated_products'] = array();
		$data['current'] = $current;
		
		$url .= '&data=' . base64_encode(serialize($data));
		
		return $url;
	}
}

if(!function_exists('twoenough_get_option')) {
	function twoenough_get_option($key, $default = null) {
		if(twoenough_is_mu()) {
			return get_site_option($key, $default);
		}
		return get_option($key, $default);
	}
}

if(!function_exists('twoenough_is_mu')) {
	function twoenough_is_mu() {
		if(defined('MULTISITE') && MULTISITE) return true;
		return false;
	}
}

?>