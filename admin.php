<?php

// Setup the plugin.
add_action( 'plugins_loaded', 'wpex_replace_setup' );
function wpex_replace_setup()
{
  // Load translation
	load_plugin_textdomain( 'wpex-replace', false, 'wpex-replace/languages' );
}

//
// load JS and CSS
//

add_action( 'admin_enqueue_scripts', 'wpex_replace_load_js_and_css' );
function wpex_replace_load_js_and_css() {
	global $hook_suffix;

	if ( in_array( $hook_suffix, array( 
		'tools_page_wpex_replace_string_tool',
	) ) )
	{
		wp_enqueue_style('jquery-ui', WPEX_REPLACE_PLUGIN_URL.'/css/jquery/jquery-ui.css', false, '1.9.2', false);
		wp_enqueue_script("jquery");
		wp_enqueue_script("jquery-ui-core");
		wp_enqueue_script("jquery-ui-tabs");

		wp_register_style( 'wpex-replace.css', WPEX_REPLACE_PLUGIN_URL . 'css/wpex-replace.css' );
		wp_enqueue_style( 'wpex-replace.css' );
	
		wp_register_script( 'wpex-replace.js', WPEX_REPLACE_PLUGIN_URL . 'js/wpex-replace.js' );
		wp_enqueue_script( 'wpex-replace.js' );
		wp_localize_script( 'wpex-replace.js', 'wpxe_replace_i18n', array(
      'ajax_url' => home_url().'/wp-admin/admin-ajax.php',
			'really_replace' => __("Do you really want to replace the selected urls?", 'wpex-replace'),
			'error_occured' => __("Please check your input!", 'wpex-replace'),
			'invalid_url' => __("Invalid url '{url}'", 'wpex-replace'),
			'same_url' => __("Identical url '{url}'", 'wpex-replace'),
      'empty_url' => __("{which} url for {url} must not be empty.", 'wpex-replace'),
			'no_urls_replaced' => __('No urls replaced.', 'wpex-replace'),
			'choose_url' => __('You have to activate the url which you want to replace.', 'wpex-replace'),
		));

    //echo '<script type="text/javascript">var the_ip = "'.$_SERVER["SERVER_ADDR"].'";</script>';
	}
}

//
// Admin Menu
//

add_action('admin_menu', 'wpex_replace_admin_menu');
function wpex_replace_admin_menu()
{
	$hook = add_submenu_page(
		'tools.php',
		__( 'Replace Url', 'wpex-replace' ),
		__( 'Replace Urls', 'wpex-replace' ),
		'create_users',
		'wpex_replace_string_tool',
    'wpex_replace_string_display_tool'
	);
	
	add_action( "load-$hook", 'wpex_replace_add_options' );
}

//
// Admin Options
//

function wpex_replace_add_options()
{
	$option = 'per_page';
	$args = array(
		'label' => __('Urls', 'wpex-replace'),
		'default' => 20,
		'option' => 'urls_per_page'
	);
	add_screen_option($option, $args);
	WPEXReplaceTable();
}

add_filter('set-screen-option', 'wpex_replace_table_set_option', 10, 3);
function wpex_replace_table_set_option($status, $option, $value)
{
  return $value;
}

//
// Show Tools -> Replace Urls
//

function wpex_replace_string_display_tool()
{
	WPEXReplaceTable()->prepare_items();
?>
  <div id="wpex-replace-string-tools" class="wrap">
		<div id="icon-tools" class="icon32"><br/></div>
		<h2><?php _e('Replace Urls', 'wpex-replace'); ?></h2>

		<div class="error"><p><?php _e('<b>CAUTION:</b> The changes are written directly into the database. Be sure to make a backup before running the replacement!', 'wpex-replace'); ?></p></div>

		<?php if (isset($_SESSION['wpex-replace-result'])): ?>
			<?php echo $_SESSION['wpex-replace-result']; unset($_SESSION['wpex-replace-result']); ?>
		<?php endif; ?>
		
		<p>
			<?php _e('Here you can replace a URL with another. This is sometimes necessary, for example when you change the domain name.', 'wpex-replace'); ?><br/>
			<?php _e('Only URLs with a new valid URL will be replaced.', 'wpex-replace'); ?>
		</p>
		
		<?php /* --- Search URLs --- */ ?>
		<div class="tab-row">
			<form method="post">
				<span class="message-search-urls">
					<?php _e('If you want to get only a part of the urls, enter your filter. Press the Search button to get the list.', 'wpex-replace'); ?><br/>
					<?php _e('When you have many plugins installed, it can take a long time. So don\'t panik ;-)', 'wpex-replace'); ?>
				</span>
				<?php //WPEXReplaceTable()->search_box( __('Search'), 'search_id' ); ?>
				<input id="search_id-search-input" type="search" value="<?php echo isset($_POST['s']) ? $_POST['s'] : ''; ?>" name="s">
				<input id="btn-search-urls" name="btn-search-urls" type="submit" class="button button-primary" value="<?php _e('Search', 'wpex-replace'); ?>" />
				<div class="loader"><img src="<?php echo WPEX_REPLACE_PLUGIN_URL; ?>/images/loader-16.gif" /></div>
				<div class="list-search-urls"><?php WPEXReplaceTable()->display(); ?></div>
			</form>
		</div>
	</div>
<?php		
}
