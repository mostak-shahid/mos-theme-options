<?php
/*
Plugin Name: Mos Theme Options
Plugin URI: http://mdmostakshahid.com/plugins/mos-theme-options/
Description: Mos FAQs plugin that lets you easily create, order and publicize FAQs using shortcodes.
Version: 0.0.1
Author: Md. Mostak Shahid
Author URI: http://mdmostakshahid.com/
License: GPL2
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


require_once ( plugin_dir_path( __FILE__ ) . 'MOS_Options_Panel.php' );
require_once ( plugin_dir_path( __FILE__ ) . 'sample/sample-config.php' );
function mos_theme_options_admin_enqueue_scripts(){
    /*Editor*/
    wp_enqueue_script( 'ace', plugins_url( 'plugins/jquery-ace/ace/ace.js', __FILE__ ), array('jquery') );
    wp_enqueue_script( 'theme-twilight', plugins_url( 'plugins/jquery-ace/ace/theme-twilight.js', __FILE__ ), array('jquery') );
    wp_enqueue_script( 'mode-html', plugins_url( 'plugins/jquery-ace/ace/mode-html.js', __FILE__ ), array('jquery') );
    wp_enqueue_script( 'mode-css', plugins_url( 'plugins/jquery-ace/ace/mode-css.js', __FILE__ ), array('jquery') );
    wp_enqueue_script( 'mode-javascript', plugins_url( 'plugins/jquery-ace/ace/mode-javascript.js', __FILE__ ), array('jquery') );
    wp_enqueue_script( 'jquery-ace.min', plugins_url( 'plugins/jquery-ace/jquery-ace.js', __FILE__ ), array('jquery') );
    /*Editor*/


    wp_enqueue_style( 'bootstrap-grid.min', plugins_url( 'css/bootstrap-grid.min.css', __FILE__ ) );
    wp_enqueue_style( 'mos-theme-options', plugins_url( 'css/mos-theme-options.css', __FILE__ ) );
    wp_enqueue_script( 'mos-theme-options', plugins_url( 'js/mos-theme-options.js', __FILE__ ), array('jquery'));
	wp_localize_script( 'mos-theme-options', 'mos_theme_options_ajax_object',
		array( 
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'mos_theme_options_admin_enqueue_scripts' );

/*
// Define Mos_Theme_Options_FILE.
if ( ! defined( 'Mos_Theme_Options_FILE' ) ) {
	define( 'Mos_Theme_Options_FILE', __FILE__ );
}
// Define Mos_Theme_Options_SETTINGS.
if ( ! defined( 'Mos_Theme_Options_SETTINGS' ) ) {
  //define( 'Mos_Theme_Options_SETTINGS', admin_url('/edit.php?post_type=qa&page=faq_settings') );
  //define( 'Mos_Theme_Options_SETTINGS', admin_url('/options-general.php?page=Mos_Theme_Options_settings') );
}
$plugin = plugin_basename(Mos_Theme_Options_FILE); 
register_activation_hook(Mos_Theme_Options_FILE, 'Mos_Theme_Options_activate');
add_action('admin_init', 'Mos_Theme_Options_redirect');
 
function Mos_Theme_Options_activate() {
    $Mos_Theme_Options_option = get_option( 'Mos_Theme_Options_option' );
	if (!$Mos_Theme_Options_option) {
		$Mos_Theme_Options_option = array(
			'Mos_Theme_Options_body_pbg' => 'rgba(0, 0, 0, 0.1)',
			'Mos_Theme_Options_body_measurements_padding' => '2px',
			'Mos_Theme_Options_body_measurements_margin' => '0 0 5px 0',
			'Mos_Theme_Options_body_border_width' => '1',
			'Mos_Theme_Options_body_border_style' => 'solid',
			'Mos_Theme_Options_body_border_color' => 'rgba(0, 0, 0, 0.2)',
			'Mos_Theme_Options_body_border_radius' => '5',
			'Mos_Theme_Options_heading_pbg' => 'rgba(0, 0, 0, 0.3)',
			'Mos_Theme_Options_heading_measurements_padding' => '10px 15px',
			'Mos_Theme_Options_heading_border_width' => '1',
			'Mos_Theme_Options_heading_border_style' => 'solid',
			'Mos_Theme_Options_heading_border_color' => 'rgba(0, 0, 0, 0.4)',
			'Mos_Theme_Options_heading_border_radius' => '3',
			'Mos_Theme_Options_icon_border_style' => 'solid',
			'Mos_Theme_Options_content_pbg' => 'rgba(0, 0, 0, 0.2)',
			'Mos_Theme_Options_content_measurements_padding' => '10px 15px',
			'Mos_Theme_Options_content_measurements_margin' => '2px 0 0',
			'Mos_Theme_Options_content_border_width' => '1',
			'Mos_Theme_Options_content_border_style' => 'solid',
			'Mos_Theme_Options_content_border_color' => 'rgba(0, 0, 0, 0.3)',
			'Mos_Theme_Options_content_border_radius' => '3',
		);		
		update_option( 'Mos_Theme_Options_option', $Mos_Theme_Options_option, false );
	}
    add_option('Mos_Theme_Options_do_activation_redirect', true);
}
 
function Mos_Theme_Options_redirect() {
    if (get_option('Mos_Theme_Options_do_activation_redirect', false)) {
        delete_option('Mos_Theme_Options_do_activation_redirect');
        if(!isset($_GET['activate-multi'])){
            wp_safe_redirect(Mos_Theme_Options_SETTINGS);
        }
    }
}

// Add settings link on plugin page
function Mos_Theme_Options_settings_link($links) { 
  $settings_link = '<a href="'.Mos_Theme_Options_SETTINGS.'">Settings</a>'; 
  array_unshift($links, $settings_link); 
  return $links; 
} 
add_filter("plugin_action_links_$plugin", 'Mos_Theme_Options_settings_link' );*/
function mos_theme_options_hook_css() {
    ?>
        <style id="mos-theme-options-css">
            <?php do_action( 'mos_theme_options_generate_css' );?>
        </style>
    <?php
}
add_action('wp_head', 'mos_theme_options_hook_css');

function mos_theme_options_generate_css_callback(){
    //echo 'test';
	$optionname = get_option('mos-theme-option-css-output-name');
	if (@$optionname) {
		$data = get_option($optionname);
		if (@$data && is_array($data)) {
			foreach($data as $key => $value) {
				foreach($value as $selector => $val ) {					
						//echo $val["type"];
						if ($val["type"] == "spacing") {
							echo $selector.'{';
							foreach($val["data"] as $attr => $property) {
								if ($property) echo $val["mood"].'-'.$attr.':'.$property.';';
							}
							echo '}';
						} 
						if ($val["type"] == "typography") {
							echo $selector.'{';
							foreach($val["data"] as $attr => $property) {
								if ($property) {
									if ($attr == 'family') echo 'font-family: ' . $property .';';
									if ($attr == 'weight') echo 'font-weight: ' . $property .';';
									if ($attr == 'alignment') echo 'text-align: ' . $property .';';
									if ($attr == 'size') echo 'font-size: ' . $property .';';
									if ($attr == 'height') echo 'line-height: ' . $property .';';
									if ($attr == 'color') echo 'color: ' . $property .';';
								}
							}
							echo '}';
						}
						if ($val["type"] == "border") {
							echo $selector.'{';
							//var_dump($val["data"]);
							foreach($val["data"] as $attr => $property) {
								if ($property) {
									if ($attr == 'style') echo 'border-style: ' . $property .';';
									elseif ($attr == 'color') echo 'border-color: ' . $property .';';
									else echo 'border-'.$attr.': ' . $property .';';
								}
							}
							echo '}';
						} 
						if ($val["type"] == "dimensions") {
							echo $selector.'{';
							//var_dump($val["data"]);
							foreach($val["data"] as $attr => $property) {
								if ($property) echo $attr.':'.$property.';';
							}
							echo '}';
						} 
						if ($val["type"] == "background") {
							echo $selector.'{';
							//var_dump($val["data"]);
							foreach($val["data"] as $attr => $property) {
								if ($property) {
									if ($attr == 'image') echo 'background-'.$attr.': url(' . $property .');';
									else echo 'background-'.$attr.': ' . $property .';';
								};
							}
							echo '}';
						} 
						if ($val["type"] == "color") {
							echo $selector.'{';
							//var_dump($val["data"]);
							if ($property) echo $val["mood"].':'.$property.';';
							/*foreach($val["data"] as $attr => $property) {
								if ($property) {
									if ($attr == 'image') echo 'background-'.$attr.': url(' . $property .');';
									else echo 'background-'.$attr.': ' . $property .';';
								};
							}*/
							echo '}';
						} 
						if ($val["type"] == "link_color") {
							
							if(@$val["data"]["base"]) {
								echo $selector.'{';
								echo 'color:' . $val["data"]["base"].';';
								echo '}';
							}
							if(@$val["data"]["hover"]) {
								echo $selector.':hover{';
								echo 'color:' . $val["data"]["hover"].';';
								echo '}';
							}
							if(@$val["data"]["active"]) {
								echo $selector.':active{';
								echo 'color:' . $val["data"]["active"].';';
								echo '}';
							}
							/*foreach($val["data"] as $attr => $property) {
								if ($property) {
									if ($attr == 'image') echo 'background-'.$attr.': url(' . $property .');';
									else echo 'background-'.$attr.': ' . $property .';';
								};
							}*/
							
						} 
						if ($val["type"] == "gradient_color") {
							echo $selector.'{';
							//var_dump($val["data"]);
							if ($val["data"]["type"] == 'linear') {
								echo 'background: linear-gradient('.$val["data"]["angle"].'deg, '.$val["data"]["start"].' 0%, '.$val["data"]["end"].' 100%);';
							} else {
								echo 'background: radial-gradient(circle, '.$val["data"]["start"].' 0%, '.$val["data"]["end"].' 100%);';
							}
							if ($val["mood"] == 'color') {
								echo '
									-webkit-background-clip: text;
									-webkit-text-fill-color: transparent;';
							}
							echo '}';
						} 
						//var_dump($val);
				}
			}
		}
		//print_r(get_option($optionname));
	}
}
add_action( 'mos_theme_options_generate_css', 'mos_theme_options_generate_css_callback' );