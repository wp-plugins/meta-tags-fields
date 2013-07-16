<?php
/*
Plugin Name: Meta Tags fields
Description: The plugin adds Meta Title+Description+Keywords fields to the post/page.
Version: 1.1
Author: Plugin is based on the work of Hotscot and Hitoshi Omagari
Author URI: http://www.warna.info/
Plugin URI: http://www.warna.info/

Copyright 2011 Hotscot  (email : support@hotscot.net)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
    
*/
global $wpdb;

class Meta_Manager {
	var $default = array(
		'includes_taxonomies'		=> array(),
		'excerpt_as_description'	=> true,
		'include_term'				=> true,
	);
	
	var $setting;
	var $term_keywords;
	var $term_description;

function __construct() {
	if ( is_admin() ) {
		add_action( 'add_meta_boxes'					, array( &$this, 'add_post_meta_box' ), 10, 2 );
		add_action( 'wp_insert_post'					, array( &$this, 'update_post_meta' ) );
		add_action( 'admin_print_styles-settings_page_meta-manager', array( &$this, 'print_icon_style' ) );
		add_action( 'plugins_loaded'					, array( &$this, 'update_settings' ) );
		add_filter( 'plugin_action_links'				, array( &$this, 'plugin_action_links' ), 10, 2 );
		add_action( 'admin_print_styles-post.php'		, array( &$this, 'print_metabox_styles' ) );
		add_action( 'admin_print_styles-post-new.php'	, array( &$this, 'print_metabox_styles' ) );
		register_deactivation_hook( __FILE__ , array( &$this, 'deactivation' ) );
	}

	add_action( 'wp_loaded',	array( &$this, 'taxonomy_update_hooks' ), 9999 );
	add_action( 'wp_head',		array( &$this, 'output_meta' ), 0 );

	$this->term_keywords = get_option( 'term_keywords' );
	$this->term_description = get_option( 'term_description' );
	if ( ! $this->setting = get_option( 'meta_manager_settings' ) ) {
		$this->setting = $this->default;
	}
	
}


function taxonomy_update_hooks() {
	$taxonomies = get_taxonomies( array( 'public' => true, 'show_ui' => true ) );
	if ( ! empty( $taxonomies ) ) {
		foreach ( $taxonomies as $taxonomy ) {
			add_action( 'created_' . $taxonomy, array( &$this, 'update_term_meta' ) );
			add_action( 'edited_' . $taxonomy, array( &$this, 'update_term_meta' ) );
			add_action( 'delete_' . $taxonomy, array( &$this, 'delete_term_meta' ) );
		}
	}
}


function plugin_action_links( $links, $file ) {
	$status = get_query_var( 'status' ) ? get_query_var( 'status' ) : 'all';
	$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
	$s = get_query_var( 's' );
	$this_plugin = plugin_basename(__FILE__);
	if ( $file == $this_plugin ) {
		$link = trailingslashit( get_bloginfo( 'wpurl' ) ) . 'wp-admin/options-general.php?page=meta-manager.php';
		$tax_regist_link = '<a href="' . $link . '">' . __( 'Settings' ) . '</a>';
		array_unshift( $links, $tax_regist_link ); // before other links
		$link = wp_nonce_url( 'plugins.php?action=deactivate&amp;plugin=' . $this_plugin . '&amp;plugin_status=' . $status . '&amp;paged=' . $paged . '&amp;deloption=1&amp;s=' . $s, 'deactivate-plugin_' . $this_plugin );
		$del_setting_deactivation_link = '<a href="' . $link . '">Remove the setting down</a>';
		array_push( $links, $del_setting_deactivation_link );
	}
	return $links;
}



function deactivation() {
	if ( isset( $_GET['deloption'] ) && $_GET['deloption'] ) {
		delete_option( 'meta_keywords' );
		delete_option( 'meta_description' );
		delete_option( 'meta_manager_settings' );
		delete_post_meta_by_key( '_keywords' );
		delete_post_meta_by_key( '_description' );
	}
}





function update_term_meta( $term_id ) {
	$post_keywords = stripslashes_deep( $_POST['meta_keywords'] );
	$post_keywords = $this->get_unique_keywords( $post_keywords );
	$post_description = stripslashes_deep( $_POST['meta_description'] );
	
	if ( ! isset( $this->term_keywords[$term_id] ) || $this->term_keywords[$term_id] != $post_keywords ) {
		$this->term_keywords[$term_id] = $post_keywords;
		update_option( 'term_keywords', $this->term_keywords );
	}
	if ( ! isset( $this->term_description[$term_id] ) || $this->term_description[$term_id] != $post_description ) {
		$this->term_description[$term_id] = $post_description;
		update_option( 'term_description', $this->term_description );
	}
}


function add_post_meta_box(  $post_type, $post ) {
	add_meta_box( 'post_meta_box', '__', array( &$this, 'post_meta_box' ), $post_type, 'normal', 'high');
}


function post_meta_box() {
	global $post;
	$post_keywords = get_post_meta( $post->ID, '_keywords', true ) ? get_post_meta( $post->ID, '_keywords', true ) : '';
	$post_description = get_post_meta( $post->ID, '_description', true ) ? get_post_meta( $post->ID, '_description', true ) : '';
?>
<dl>
	<dt>Meta keywords</dt>
	<dd><input type="text" name="_keywords" id="post_keywords" size="100" value="<?php echo esc_html( $post_keywords ); ?>" /></dd>
	<dt>Meta description</dt>
	<dd><textarea name="_description" id="post_description" cols="100" rows="3"><?php echo esc_html( $post_description ); ?></textarea></dd>
</dl>
<?php
}


function print_metabox_styles() {
?>
<style type="text/css" charset="utf-8">
#post_keywords,
#post_description {
	width: 98%;
}
</style>
<?php
}


function update_post_meta( $post_ID ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( isset( $_POST['_keywords'] ) ) {
		$post_keywords = stripslashes_deep( $_POST['_keywords'] );
		$post_keywords = $this->get_unique_keywords( $post_keywords );
		update_post_meta( $post_ID, '_keywords', $post_keywords );
	}
	if ( isset( $_POST['_description'] ) ) {
		$post_keywords = stripslashes_deep( $_POST['_description'] );
		update_post_meta( $post_ID, '_description', $post_keywords );
	}
}


function output_meta() {
	$meta = $this->get_meta();
	$output = '';
	if ( $meta['keywords'] ) {
		$output .= '<meta name="keywords" content="' . esc_attr( $meta['keywords'] ) . '" />' . "\n";
	}
	if ( $meta['description'] ) {
		$output .= '<meta name="description" content="' . esc_attr( $meta['description'] ) . '..." />' . "\n";
	}
	echo $output;
}


private function get_meta() {
	$meta = array();
	$option = array();
	$meta['keywords'] = get_option( 'meta_keywords' ) ? get_option( 'meta_keywords' ) : '';
	$meta['description'] = get_option( 'meta_description' ) ? get_option( 'meta_description' ) : '';
	if ( is_singular() ) {
		$option = $this->get_post_meta();
	} elseif ( is_tax() || is_category() || is_tag() ) {
		$option = $this->get_term_meta();
	}

	if ( ! empty( $option ) && $option['keywords'] ) {
		$meta['keywords'] = $this->get_unique_keywords( $option['keywords'], $meta['keywords'] );
	} else {
		$meta['keywords'] = $this->get_unique_keywords( $meta['keywords'] );
	}
	
	if ( ! empty( $option ) && $option['description'] ) {
		$meta['description'] = $option['description'];
	}
	$meta['description'] = mb_substr( $meta['description'], 0, 120, 'UTF-8' );
	return $meta;
}

private function get_post_meta() {
	global $post;
	$post_meta = array();
	$post_meta['keywords'] = get_post_meta( $post->ID, '_keywords', true ) ? get_post_meta( $post->ID, '_keywords', true ) : '';
	if ( ! empty( $this->setting['includes_taxonomies'] ) ) {
		foreach ( $this->setting['includes_taxonomies'] as $taxonomy ) {
			$taxonomy = get_taxonomy( $taxonomy );
			if ( in_array( $post->post_type, $taxonomy->object_type ) ) {
				$terms = get_the_terms( $post->ID, $taxonomy->name );
				if ( $terms ) {
					$add_keywords = array();
					foreach ( $terms as $term ) {
						$add_keywords[] = $term->name;
					}
					$add_keywords = implode( ',', $add_keywords );
					if ( $post_meta['keywords'] ) {
						$post_meta['keywords'] .= ',' . $add_keywords;
					} else {
						$post_meta['keywords'] = $add_keywords;
					}
				}
			}
		}
	}
	$post_meta['description'] = get_post_meta( $post->ID, '_description', true ) ? get_post_meta( $post->ID, '_description', true ) : '';
	if ( $this->setting['excerpt_as_description'] && ! $post_meta['description'] ) {
		if ( trim( $post->post_excerpt ) ) {
			$post_meta['description'] = $post->post_excerpt;
		} else {
			$excerpt = apply_filters( 'the_content', $post->post_content );
			$excerpt = strip_shortcodes( $excerpt );
			$excerpt = str_replace( ']]>', ']]&gt;', $excerpt );
			$excerpt = strip_tags( $excerpt );
			$post_meta['description'] = trim( preg_replace( '/[\n\r\t ]+/', ' ', $excerpt), ' ' );
		}
	}
	return $post_meta;
}


private function get_term_meta() {
	$term_meta = array();
	if ( is_tax() ) {
		$taxonomy = get_query_var( 'taxonomy' );
		$slug = get_query_var( 'term' );
		$term = get_term_by( 'slug', $slug, $taxonomy );
		$term_id = $term->term_id;
	} elseif ( is_category() ) {
		$term_id = get_query_var( 'cat' );
		$term = get_category( $term_id );
	} elseif ( is_tag() ) {
		$slug = get_query_var( 'tag' );
		$term = get_term_by( 'slug', $slug, 'post_tag' );
		$term_id = $term->term_id;
	}

	$term_meta['keywords'] = isset( $this->term_keywords[$term_id] ) ? $this->term_keywords[$term_id] : '';
	if ( $this->setting['include_term'] ) {
		$term_meta['keywords'] = $term->name . ',' . $term_meta['keywords'];
	}
	$term_meta['description'] = isset( $this->term_description[$term_id] ) ? $this->term_description[$term_id] : '';
	return $term_meta;
}


private function get_unique_keywords() {
	$args = func_get_args();
	$keywords = array();
	if ( ! empty( $args ) ) {
		foreach ( $args as $arg ) {
			if ( is_string( $arg ) ) {
				$keywords[] = trim( $arg, ', ' );
			}
		}
		$keywords = implode( ',', $keywords );
		$keywords = preg_replace( '/[, ]*,[, ]*/', ',', $keywords );
		$keywords = explode( ',', $keywords );
		$keywords = array_map( 'trim', $keywords );
		$keywords = array_unique( $keywords );
	}
	$keywords = implode( ',', $keywords );
	return $keywords;
}


function update_settings() {
	if ( isset( $_POST['meta_manager_update'] ) ) {
		$post_data = stripslashes_deep( $_POST );
		check_admin_referer( 'meta_manager' );
		$setting = array();
		foreach ( $this->default as $key => $def ) {
			if ( ! isset( $post_data[$key] ) ) {
				if ( $key == 'includes_taxonomies' ) {
					$setting['includes_taxonomies'] = array();
				} else {
					$setting[$key] = false;
				}
			} else {
				if ( $key == 'includes_taxonomies' ) {
					$setting['includes_taxonomies'] = $post_data['includes_taxonomies'];
				} else {
					$setting[$key] = true;
				}
			}
		}
		$meta_keywords = $this->get_unique_keywords( $post_data['meta_keywords'] );
		update_option( 'meta_keywords', $meta_keywords );
		update_option( 'meta_description', $post_data['meta_description'] );
		update_option( 'meta_manager_settings', $setting );
		$this->setting = $setting;
	}
}


function print_icon_style() {
	$url = preg_replace( '/^https?:/', '', plugin_dir_url( __FILE__ ) ) . 'images/icon32.png';
?>
<style type="text/css" charset="utf-8">
#icon-meta_manager-icon32 {
	background: url( <?php echo esc_url( $url ); ?> ) no-repeat center;
}
#developper_information {
	margin: 20px 30px 10px;
}
#developper_information .content {
	padding: 10px 20px;
}
#poweredby {
	text-align: right;
}
</style>
<?php
}


} // class end
$meta_manager = new Meta_Manager;














if (!class_exists("sc_simple_meta_tags")) 
	{

	class sc_simple_meta_tags
	{
		//the constructor that initializes the class
		function sc_simple_meta_tags() 
		{
		}
		
		function sc_save_wonderful_metas($post_id) {
			// verify if this is an auto save routine. If it is our form has not been submitted, so we dont want
			// to do anything
			if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return $post_id;
			
			if (isset($_POST['scmetatitle']))  {
			add_post_meta($post_id, '_sc_m_title', $_POST['scmetatitle'], true) or update_post_meta($post_id, '_sc_m_title', $_POST['scmetatitle']);
			}
		}
		
		
	
	}
	
	//initialize the class to a variable
	$sc_meta_var = new sc_simple_meta_tags();
	
	function sc_create_wonder_form(){
		global $post;
		?>
		<b>Meta Title</b><br />
		<i style="color:red;">Note: If your theme or other plugin generated the titles, then they will be abolished, and this plugin will set the title. (if you leave this field empty, then the title will be automatically set according to the post title).</i>
		

		<input type="text" size="100" id="scmetatitle" name="scmetatitle" value="<?php echo get_post_meta($post->ID, '_sc_m_title', true); ?>" />
		<br /><br />
		<?php
	}
	
		
//       START OF TITLECEATOR BLOCK	


				
		function titll()
		{
			//if (!strstr(ob_get_contents(), '</title>') || strstr(ob_get_contents(), '<!--<title>') )
			//lets turn on the title function in any case :)
			if ('a'=='a')
			{
						//lets remove the current, whatever title is inside the site already
						
						//clear & change the output
						$startt= preg_replace('/\<title\>(.*?)\<\/title\>/s','',ob_get_contents());
						ob_get_clean();	
						
						echo $startt;
						
						echo '<title>';
							if		(is_home())			{echo bloginfo('name');}
							elseif	(is_single())		{echo the_title();}
							elseif	(is_page())			{echo the_title();}
							elseif	(is_category())		{echo single_cat_title( $prefix, $display );}
							elseif	(is_404())			{echo the_title();}
							else						{echo the_title().' - '. bloginfo('name');}
						echo '</title>';
						//then the site continues other output

			} 
		}	
		
		//       END OF TITLECEATOR BLOCK
		
		
		
		
	//Actions and Filters	
	if (isset($sc_meta_var)) {
		//Actions
		add_action("save_post",array(&$sc_meta_var,'sc_save_wonderful_metas'));
		add_action("wp_head",'titll');
		add_action('admin_init', 'register_default_meta_settings' );
		add_action('admin_menu', 'sc_add_wonder_box');
		
		function sc_add_wonder_box() {						
		    if( function_exists( 'add_meta_box' )) {
				add_meta_box( 'MetaTagsPlugin', '__', 'sc_create_wonder_form', 'page', 'advanced', 'high' );
				add_meta_box( 'MetaTagsPlugin', '__', 'sc_create_wonder_form', 'post', 'advanced', 'high' );
		    }else{
				add_action('dbx_post_advanced', 'sc_create_wonder_form' );
				add_action('dbx_page_advanced', 'sc_create_wonder_form' );
		    }
		}
	}
	
	/**
	 * register_default_meta_settings
	 *
	 * Is run when the plugin is first installed.  It adds options into the
	 * wp-options 
	 */
	function register_default_meta_settings()
	{
		register_setting( 'meta-tag-settings', 'smt-init-v11' );	
		register_setting( 'meta-tag-settings', 'page_meta_title' );		

		register_setting( 'meta-tag-settings', 'post_meta_title' );		
		
		register_setting( 'meta-tag-settings', 'use_pages_meta_data' );		
		register_setting( 'meta-tag-settings', 'use_posts_meta_data' );
		
		if(get_option('smt-init-v11')!='done'){
			update_option('use_pages_meta_data','on');
			update_option('use_posts_meta_data','on');
			update_option('smt-init-v11','done');
			
			if(get_option('meta_title')!=''){
				update_option('page_meta_title',get_option('meta_title'));
				update_option('post_meta_title',get_option('meta_title'));
				update_option('meta_title','');
			}
		}
	}
}
?>