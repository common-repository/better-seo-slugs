<?php
/*
 * Plugin Name:   Better SEO Slugs
 * Plugin URI:    http://twoenough.com
 * Version:       1.0.0
 * Description:   The Better SEO Slugs makes your post and pages permalinks more SEO friendly. It removes common words that are unnecessary for search engine optimization from your post URL. It doesn't shorten your slug in a random manner. It only deletes the words which are not important in terms of SEO.
 * Author:        two enough
 * Author URI:    http://twoenough.com
 */
 
define('BSS_VERSION', '1.0.0');			// Current version of the Plugin

define('BSS_PLUGIN_DIR', dirname(__FILE__));
define('BSS_PLUGIN_FILE', BSS_PLUGIN_DIR . '/' . basename(__FILE__));

define('BSS_PLUGIN_URL', WP_PLUGIN_URL . '/' . str_replace(basename( __FILE__), "", plugin_basename(__FILE__)));

require_once('lib/twoenough.php');

if(!class_exists('BetterSEOSlugsPlugin')) {
class BetterSEOSlugsPlugin {

	private $path;
	
	private $options;
	
	private $skipwords_file = 'skip-words.txt';
	
	private $default_settings = array(
		'max_length'        => 30,
		'min_word_length'   => 4,
		'remove_short_word' => 0,
		'force_removal'     => 0,
		'keep_protected'    => 0,
	);

	public function __construct() { 

		register_activation_hook(BSS_PLUGIN_FILE, array(&$this, 'on_activation'));
		
		add_action('admin_menu', array(&$this, 'addMenu'));

		add_filter('name_save_pre', array(&$this, 'trimSlug')); 

		add_action('wp_ajax_bss', array(&$this, 'ajax') );
		
		if($_REQUEST['page'] == 'bss_options') {
			wp_enqueue_script('admin-widgets');
			wp_enqueue_style('widgets');
		}
		
		if( !$this->options = get_option('bss_options') ) {
			$this->options  = $this->default_settings;
		}
		
		if( !$this->skip_words = get_option('bss_skip_words') ) {
			$this->skip_words  = array();
		}
		
		if( !$this->protected_words = get_option('bss_protected_words') ) {
			$this->protected_words  = array();
		}
		
		foreach($this->skip_words as $key => $word) {
			$this->skip_words[$key] = strtolower($word);
		}
		
		foreach($this->protected_words as $key => $word) {
			$this->protected_words[$key] = strtolower($word);
		}
		
	}
	
	public function ajax() {
		$action = $_POST['bss_action'];
	
		switch($action) {
			default:
				update_option('bss_subscribed', 1);
		}
		
	}
	
	public function on_activation() {
		$skip_words = file(BSS_PLUGIN_DIR . '/data/' . $this->skipwords_file, FILE_IGNORE_NEW_LINES);

		foreach ( (array) $skip_words as $key => $word ) {
			$skip_words[$key] = trim(strtolower($word));
		}
		
		add_option('bss_options', $this->default_settings);
		add_option('bss_skip_words', $skip_words);
		
		return true;
	}
	
	public function trimSlug($slug) {
		global $wpdb;
		
		if ( !empty($slug) ) {
			return $slug;
		}
		
		$title = trim($_POST['post_title']);
		if ( (strlen($title) > $this->options['max_length']) || $this->options['force_removal'] ) {
			$title_new = $this->_trimSlug(sanitize_title(trim($title)));
			return sanitize_title(trim($title_new));
		} else {
			return '';
		}
	}
	
	private function _trimSlug($title) {
		$words = explode('-',$title);
		$title_new = '';
		$keep_protected_only = false;
		for ( $i = 0; $i < count($words); $i++ ) {
			$is_protected = $this->options['keep_protected'] && in_array(trim(strtolower($words[$i])), $this->protected_words);
			$is_skipped   = in_array(trim(strtolower($words[$i])), $this->skip_words);
			
			if( !$is_skipped || $is_protected) {
				if ( !$is_protected && $this->options['remove_short_word'] && (strlen(trim($words[$i])) <= $this->options['min_word_length']) ) {
					continue;
				}
				if($keep_protected_only && !$is_protected) {
					continue;
				}
				$title_new .= '-' . $words[$i];
				if ( strlen($title_new) >= $this->options['max_length'] ) {
					if($this->options['keep_protected']) {
						$keep_protected_only = true;
						continue;
					}
					break;
				}
			}
		}
		return $title_new;	 	
	}	
	
	public function addMenu() {
		add_options_page('Better SEO Slugs', 'Better SEO Slugs', 'manage_options', 'bss_options', array(&$this, 'options'));
	}
	
	private function header() {
		echo '<link rel="stylesheet" href="' . get_option('siteurl') . '/wp-includes/js/thickbox/thickbox.css" type="text/css" media="all" /> ';
		echo '<link rel="stylesheet" type="text/css" href="' . BSS_PLUGIN_URL . '/css/admin.css" />'."\r\n";

		echo '<div class="wrap">
		<div style="float:left;width:50%;"><h2>Better SEO Slugs</h2></div>
		<div style="clear:both;"></div>
		<div id="poststuff" class="metabox-holder">
		<div id="crosspromoter"></div><script src="' . twoeGetCrossPromoterUrl('bss') . '"></script>';
		
		if ( trim($msg_in_plugin) != '' ) echo $msg_in_plugin;

		echo '<div id="post-body" class="has-sidebar">';
		echo '<div id="post-body-content" class="has-sidebar-content">';

	}
	
	private function footer() {
		echo '</div></div>';
	}
	
	
	private function makeBoxStart($title, $div = '', $closed = false) {
		if (!isset($div)) $div = str_replace(' ', '', $title).'div';
		$html = '<div id="'.$div.'" class="postbox ';
		if ($closed) $html .= 'if-js-closed';
		$html .= '"><h3>'.$title.'</h3><div class="inside">';
		$html .= "\r\n";
		return $html;
	}
	
	private function makeBoxClose() {
		$html = '</div></div>';
		return $html;
	}
	
	private function makeCheckBox($field, $val, $title, $label, $help='') {
		if (strlen($title)) $title .= ': ';
		$html = '<tr valign="top"><td width=200>'.$title.'</td><td><label for="'.$field.'"><input name="'.$field.'" id="'.$field.'" type="checkbox" ';
		
		if ($val == true) {
			$html .= 'checked="checked" value="checked"';
		}
		
		$html .= '/>&nbsp;<span id=helper>'.$label."</span></label>";
		if (!empty($help)) $html .= '<br /><span id=helper>'.$help.'</span>';
			
		$html .= "</td></tr>\r\n";
		return $html;
	}
	
	public function options() {
		global $wpdb;
				
		$msg = '';
		
		$post_data = $_POST['bss'];
		if ( $post_data['save_options'] ) {
			$this->options['max_length']     = intval($post_data['max_length']);
			$this->options['force_removal']  = isset($post_data['force_removal']) ? 1 : 0;
			$this->options['keep_protected'] = isset($post_data['keep_protected']) ? 1 : 0;
			$the_skip_words = explode("\n", trim($post_data['skip_words']));
			
			$this->skip_words = array();
			
			foreach( (array) $the_skip_words as $key => $word ) {
				$this->skip_words[$key] = trim(stripslashes($word));
			}
			
			$the_protected_words = explode("\n", trim($post_data['protected_words']));
			foreach( (array) $the_protected_words as $key => $word ) {
				$this->protected_words[$key] = trim(stripslashes($word));
			}
			
			$this->options['remove_short_word'] = isset($post_data['remove_short_word']) ? 1 : 0;
			$this->options['min_word_length']   = $post_data['min_word_length'];
			
			update_option("bss_skip_words", $this->skip_words);
			update_option("bss_protected_words", $this->protected_words);
			update_option("bss_options", $this->options);
			
			$msg = "Options Saved.";
		}
		
		if ( trim($msg) !== '' ) {
			echo '<div id="message" class="updated fade"><p><strong>'.$msg.'</strong></p></div>';
		}
				
		$this->header();

echo '<div class="widget-liquid-left"><div id="widgets-left"> ';
		echo '	<div class="widgets-holder-wrap"> 
<form name="bss_form" method="post">';

		echo $this->makeBoxStart("Slugs");
		echo '<table id=settingsTable>';
 		echo $this->makeCheckBox('bss[remove_short_word]', $this->options['remove_short_word'], "Short Words", "Remove words shorter than <input type='text' name='bss[min_word_length]' value='" . $this->options['min_word_length']. "' style='width:25px' maxlength='7'> characters", '');

		echo '<tr><td>Slug Length</td><td id="helper">Up to <input type="text" name="bss[max_length]" value="' . $this->options['max_length'] . '" maxlength="7" style="width:25px" > characters</td></tr>';
		
		
		 		
		echo '</table>';
		echo $this->makeBoxClose();
		
		echo $this->makeBoxStart("Noise Words");
		echo '<table id=settingsTable>';



 		echo $this->makeCheckBox('bss[force_removal]', $this->options['force_removal'], "Remove 'noise' words", "Force removal of noise words from the slug", '');
 		
 		echo '<tr><td valign=top>Noise Words List</td><td><textarea name="bss[skip_words]" rows="12" cols="40" style="width:260px">' . trim(implode("\n", $this->skip_words)) . '</textarea></td>';
 		
		echo '</table>';
		echo $this->makeBoxClose();
		
		echo $this->makeBoxStart("Important Keywords");
		echo '<table id=settingsTable>';

 		echo $this->makeCheckBox('bss[keep_protected]', $this->options['keep_protected'], "Keep important keywords", "Never remove protected keywords from the slug", '');
 		
 		echo '<tr><td valign=top>Important Keywords<p><small>Single words ONLY, one per line</small></p></td><td><textarea name="bss[protected_words]" rows="12" cols="40" style="width:260px">' . trim(implode("\n", $this->protected_words)) . '</textarea></td>';
 		
		echo '</table>';
		echo $this->makeBoxClose();
		
		echo '<input type="submit" name="bss[save_options]" id="publish" value="Save Options" tabindex="4" class="button-primary" />';
		
		echo '</form>';
		$this->footer();
echo '</div></div></div>';		
		
		if(get_option('bss_subscribed')) {
			global $current_user;
			get_currentuserinfo();
	
			$user_name = '';
			
			if($current_user->nickname) {
				$user_name = $current_user->user_login;
			}
			
			if($current_user->nickname) {
				$user_name = $current_user->nickname;
			}
			
			if($current_user->first_name || $current_user->last_name) {
				$parts = array();
				if($current_user->first_name) {
					$parts[] = $current_user->first_name;
				}
				if($current_user->last_name) {
					$parts[] = $current_user->last_name;
				}
				$user_name = join(' ', $parts);
			}
			
			$user_email = $current_user->user_email;
?>

<script language="JavaScript">

jQuery(document).ready( function() { setTimeout(function() { jQuery('#show_hide_widget').trigger('click'); }, '100' ) } );

on_subscribe = function() {
	data = {
		action: 'bss',
		bss_action: '',
	};
	
	jQuery.post(ajaxurl, data, function(response) {});
}

</script>

<div class="widget-liquid-right"> 
<div id="widgets-right">
	<div class="widgets-holder-wrap"> 
	<div class="sidebar-name"> 
	<div class="sidebar-name-arrow"><br /></div> 
	<h3>Subscribe to get a free ebook! <span><img src="images/wpspin_dark.gif" class="ajax-feedback" title="" alt="" /></span></h3></div> 
	<div id='primary-widget-area' class='widgets-sortables'> 
<div class='sidebar-description'> 
	<p class='description'><img align="left" width="100" height="138" src="../wp-content/plugins/better-seo-slugs/css/report-100.jpg" style="margin-right:10px;margin-bottom:10px;">Everyone knows how important are backlinks for a site ranking. Discover free methods of building permanent backlinks from authority sites in your niche. Download a free copy of this report today!</p></div>
<div id='widget-14_search-2' class='widget'>	<div class="widget-top" style="cursor: default;"> 
	<div class="widget-title-action"> 
		<a class="widget-action hide-if-no-js" id="show_hide_widget" href="#available-widgets"></a> 
	</div> 
	<div style="padding: 5px 9p"><h4 style="margin: 5pt;line-height: 1.3;overflow: hidden;white-space: nowrap;">Subscribe<span styl="font-size: 11px;white-space: nowrap;"></span></h4></div> 
	</div> 
 
	<div class="widget-inside"> 
	<form action="http://www.aweber.com/scripts/addlead.pl" method="post" target="_blank" onsubmit="on_subscribe();return true;"> 
<input type="hidden" name="meta_web_form_id" value="316130603" />
<input type="hidden" name="meta_split_id" value="" />
<input type="hidden" name="listname" value="free-backlinks1" />
<input type="hidden" name="redirect" value="http://twoenough.com/confirmation-success" id="redirect_54c6e563242776afd129c56155625f97" />

<input type="hidden" name="meta_adtracking" value="My_Web_Form_Plugin" />
<input type="hidden" name="meta_message" value="1" />
<input type="hidden" name="meta_required" value="name,email" />

<input type="hidden" name="meta_tooltip" value="" />	
	<div class="widget-content"> 
		<p><label for="widget-subscribe-name">Name: <input class="widefat" id="widget-subscribe-name" name="name" type="text" value="<? echo htmlentities($user_name);?>" /></label></p> 
		<p><label for="widget-subscribe-email">E-mail: <input class="widefat" id="widget-subscribe-email" name="email" type="text" value="<? echo htmlentities($user_email);?>" /></label></p> 
	</div> 
 
	<div class="widget-control-actions"> 
<!--		<div class="alignleft"> 
		<a class="widget-control-remove" href="#remove">Delete</a> |
		<a class="widget-control-close" href="#close">Close</a> 
		</div> -->
		<div class="alignright"> 
		<img src="images/wpspin_light.gif" class="ajax-feedback " title="" alt="" /> 
		<input type="submit" class="button-primary" value="Subscribe" /> 
		</div> 
		<br class="clear" /> 
	</div> 
	</form> 
	</div> 

</div>


</div> 

	</div> 

</div> 
</div> 
<form action="" method="post"> 
<input type="hidden" id="_wpnonce_widgets" name="_wpnonce_widgets" value="9a2eabad7e" /></form> 
<br class="clear" /> 
</div>

<?php
		}
	}

}
}

$BetterSEOSlugsPlugin = new BetterSEOSlugsPlugin();
?>