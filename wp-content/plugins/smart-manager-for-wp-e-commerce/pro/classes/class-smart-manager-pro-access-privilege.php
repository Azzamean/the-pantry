<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_Access_Privilege' ) ) {
	class Smart_Manager_Pro_Access_Privilege {

		protected static $_instance = null;
		public static $access_privilege_option_start = 'sm_beta_';
		public static $access_privilege_option_end = '_accessible_dashboards';	

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		function __construct() {
			add_filter( 'sm_active_dashboards', array( $this, 'sm_beta_get_accessible_dashboards' ) );
			add_action( 'wp_ajax_smart_manager_save_settings', array( $this, 'save_settings' ) );
		}

		public function save_settings() {

			if ( empty($_POST) || !wp_verify_nonce($_POST['smart-manager-security'],'smart_manager_save_settings') ) {
			    echo 'Security Check Failed';
			    die();
			}

			global $wpdb;

			$msg = 'Settings Saving Failed!!!';

			//Company logo update code
	        $company_logo = ( !empty( $_POST['smart_manager_company_logo'] ) ? $_POST['smart_manager_company_logo'] : '' );
	        update_option('smart_manager_company_logo', $company_logo);

	        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}options 
	        							WHERE option_name LIKE %s
	        								AND option_name LIKE %s", '%' . $wpdb->esc_like(self::$access_privilege_option_start) . '%', '%' . $wpdb->esc_like(self::$access_privilege_option_end) . '%' ) );

	        if( !empty( $_POST['user_role_dashboards'] ) ) {

	        	$update_values = array();

	        	foreach( $_POST['user_role_dashboards'] as $user_role => $dashboards ) {
	        		$update_values[] = "( '".self::$access_privilege_option_start."".$user_role."".self::$access_privilege_option_end."', '". implode(",", $dashboards) ."', 'no' )";
	        	}

	        	if( !empty( $update_values ) ) {
	        		$query = "INSERT INTO {$wpdb->prefix}options ( option_name, option_value, autoload) VALUES ". implode(", ",$update_values) ." ON DUPLICATE KEY UPDATE option_value = VALUES ( option_value )";
	        		$result = $wpdb->query( $query );

	        		if( !empty( $result ) ) {
	        			$msg = 'Settings Saved Successfully!!!';
	        		}
	        	}

	        }

			echo json_encode(array('msg' => $msg));
	        exit();
		}

		public static function get_all_privileges() {
			global $wpdb;

			$user_role_dashboard_privileges = array();

			$results = $wpdb->get_results( $wpdb->prepare( "SELECT LEFT(SUBSTR(option_name, %d), LOCATE(%s, SUBSTR(option_name, %d)) -1) as user_role,
																option_value as dashboards
															FROM {$wpdb->prefix}options 
															WHERE option_name LIKE %s 
																AND option_name LIKE %s", strlen(self::$access_privilege_option_start)+1, self::$access_privilege_option_end, strlen(self::$access_privilege_option_start)+1, '%' . $wpdb->esc_like(self::$access_privilege_option_start) . '%', '%' . $wpdb->esc_like(self::$access_privilege_option_end) . '%' ), 'ARRAY_A' );

			if( !empty( $results ) ) {
				foreach( $results as $result ) {
					$role = ( !empty( $result['user_role'] ) ) ? $result['user_role'] : '';
					$dashboards = ( !empty( $result['dashboards'] ) ) ? explode( ",", $result['dashboards'] ) : '';
					$user_role_dashboard_privileges[ $role ] = $dashboards;
				}
			}

			return json_encode($user_role_dashboard_privileges);
		}

		//Function to render the render the access privilege settings
		public static function render_access_privilege_settings() {

			$user_role_dashboard_privileges = self::get_all_privileges();

			?>

			<style type="text/css">
				table#sm_access_privilege_settings {
				  width: 90%;
				  background-color: #FFFFFF;
				  border-collapse: collapse;
				  border-width: 0px !important;
				  border-color: #3892D3 !important;
				  border-style: solid;
				  color: #656161;
				  border-radius: 0.3em !important;
				}

				table#sm_access_privilege_settings th {
					background-color: #3892D3 !important;
					color: #FFFFFF !important;
					border-color: #3892D3 !important;
				}

				table#sm_access_privilege_settings td, table#sm_access_privilege_settings th {
				  border-width: 1px;
				  border-color: #3892D3;
				  border-style: solid;
				  padding: 15px;
				}

				table#sm_access_privilege_settings thead {
				  background-color: #3892D3;
				}

				.sm_access_privilege_dashboards_select2 {
					color:#656161;
				}

			</style>

			<script type="text/javascript">
				let smIsJson = function(str) {
					try {
						return (JSON.parse(str) && !!str);
					} catch (e) {
						return false;
					}
				}

				jQuery(document).ready(function() {

					let allUserRoleDashboardPrivileges = '<?php echo $user_role_dashboard_privileges; ?>';

					allUserRoleDashboardPrivileges = ( smIsJson( allUserRoleDashboardPrivileges ) ) ? JSON.parse(allUserRoleDashboardPrivileges) : {};

					jQuery('#toplevel_page_smart-manager').find('.wp-first-item').closest('li').removeClass('current');
					jQuery('#toplevel_page_smart-manager').find('a[href$=sm-settings]').closest('li').addClass('current');

					jQuery(".sm_access_privilege_dashboards").select2({ width: '50%', dropdownCssClass: 'sm_access_privilege_dashboards_select2', placeholder: "Select Dashboards" });

					jQuery('.sm_access_privilege_dashboards').each(function() { //Code for setting the 'name' attribute for each of the select2 box
						let parentID = jQuery(this).parents('tr').attr('id');
						let str = 'user_role_dashboards['+parentID+']'
						jQuery(this).attr('name', str+'[]');

						if( allUserRoleDashboardPrivileges.hasOwnProperty(parentID) ) {
							jQuery(this).val(allUserRoleDashboardPrivileges[parentID]).trigger('change');
						}
						
					});

					jQuery('#smart_manager_settings_form').on('submit', function(e) {
						e.preventDefault();
						let $form = jQuery(this);
						jQuery.post($form.attr('action'), $form.serialize(), function(data) {
							if( typeof data['msg'] != 'undefined' ) {
								alert(data['msg']);
							}
						}, 'json');
					});

				});
			</script>

			<br/>
			<span class="sm-h2">
			<?php
					echo 'Smart Manager ';
					echo '<sup style="vertical-align: super;background-color: #EC8F1C;font-size: 0.6em !important;color: white;padding: 2px 3px;border-radius: 2px;font-weight: 600;"><span>'.((SMPRO === true) ? 'PRO' : 'LITE').'</span></sup> ';
					_e('Settings', 'smart-manager-for-wp-e-commerce');
			?>
			</span>

			<form id="smart_manager_settings_form" action="<?php echo admin_url('admin-ajax.php'); ?>" method="post">

			<?php
				global $current_user;

				wp_nonce_field('smart_manager_save_settings','smart-manager-security');

				if (!function_exists('wp_get_current_user')) {
					require_once (ABSPATH . 'wp-includes/pluggable.php'); // Sometimes conflict with SB-Welcome Email Editor
				}

				$current_user = wp_get_current_user(); // Sometimes conflict with SB-Welcome Email Editor

				if ( !isset( $current_user->roles[0] ) ) {
					$roles = array_values( $current_user->roles );
				} else {
					$roles = $current_user->roles;
				}

				//Fix for the client
				if ( !empty( $current_user->caps ) ) {
					$caps = array_keys($current_user->caps);
					$current_user_caps = $roles[0] = (!empty($caps)) ? $caps[0] : '';
				}

				if( ( !empty( $current_user->roles[0] ) && $current_user->roles[0] == 'administrator' ) || ( !empty( $current_user_caps ) && $current_user_caps == 'administrator') ) {

			?>

					<h3 style="font-size:120%;color:#656161"><?php _e('Privileges for Smart Manager for different System Roles', 'smart-manager-for-wp-e-commerce' );?></h3><br/>

					<table id="sm_access_privilege_settings" name="sm_access_privilege_settings" class="form-table">
						<tbody>
							<tr>
								<th><b>Roles</b></th>
								<th><b>Dashboards</b></th>
							</tr>
							
							<?php
								$all_roles = get_editable_roles();

								if ( !empty( $all_roles ) ) {

									if( isset( $all_roles['administrator'] ) ){
							            unset( $all_roles['administrator']);
							        }

							        $dashboard_select_options = '<select class="sm_access_privilege_dashboards" multiple="multiple" style="min-width:130px !important;">';

							        if ( defined( 'SM_BETA_ALL_DASHBOARDS' ) ) {
										$all_dashboards = json_decode(SM_BETA_ALL_DASHBOARDS, true);

										if( !empty( $all_dashboards ) ) {
											$dashboard_select_options .= '<optgroup label="All post types">';
											foreach( $all_dashboards as $dashboard => $title ) {
												$dashboard_select_options .= '<option value="'. $dashboard .'">'. $title .'</option>';
											}
											$dashboard_select_options .= '</optgroup>';
										}
									}

									// Code for fetching all views
									if( class_exists( 'Smart_Manager_Pro_Views' ) ) {
										$view_obj = Smart_Manager_Pro_Views::get_instance();
										if( is_callable( array( $view_obj, 'get' ) ) ){
											$views = $view_obj->get();
											if( ! empty( $views ) ) {
												$dashboard_select_options .= '<optgroup label="All saved views">';
												foreach( $views as $view ) {
													$dashboard_select_options .= '<option value="'. $view['slug'] .'">'. $view['title'] .'</option>';
												}
												$dashboard_select_options .= '</optgroup>';
											}
										}
									}

									$dashboard_select_options .= '</select>';

									foreach( $all_roles as $user_role => $user_role_obj ) {
										echo '<tr id="'. $user_role .'">'.
												'<td>'. ( ( !empty( $user_role_obj['name'] ) ) ? $user_role_obj['name'] : ucwords( $user_role ) ) .'</td>'.
												'<td>'. $dashboard_select_options .'</td>'.
											'</tr>';
									}
								}
							?>					
						</tbody>
					</table>
			<?php
				}
			?>

			<br/><br/>

			<?php self::render_company_logo_settings() ?>

			<p class="submit">
				<input type="submit" name="smart_manager_save_settings" id="smart_manager_save_settings" class="button-primary" value="Apply">
				<a href="<?php echo admin_url('admin.php?page=smart-manager'); ?>"> <?php _e('Back to Smart Manager', 'smart-manager-for-wp-e-commerce') ?> </a>
			</p>

			<input name="action" value="smart_manager_save_settings" type="hidden">

			</form>
			<?php
		}

		public static function render_company_logo_settings() {

			?>

			<script type="text/javascript">

				jQuery(document).ready(function($) {

					  let file_frame;
					  
					  jQuery(document).on('click','#upload_image_button', function( event ){

					    event.preventDefault();

					    // If the media frame already exists, reopen it.
					    if ( file_frame ) {
					      file_frame.open();
					      return;
					    }

					    // Create the media frame.
					    file_frame = wp.media.frames.file_frame = wp.media({
					      title: jQuery( this ).data( 'uploader_title' ),
					      button: {
					        text: jQuery( this ).data( 'uploader_button_text' ),
					      },
					      multiple: false  // Set to true to allow multiple files to be selected
					    });

					    // When an image is selected, run a callback.
					    file_frame.on( 'select', function() {
					      // We set multiple to false so only get one image from the uploader
					      attachment = file_frame.state().get('selection').first().toJSON();

					      // Send the value of attachment.url back to PIP settings form
					      jQuery('#smart_manager_company_logo').val(attachment.url);
					    });

					    // Finally, open the modal
					    file_frame.open();
					  });
				});

				function myMediaPopupHandler()
				{
				        window.send_to_editor = function(html) {
				            imgurl = jQuery('img',html).attr('src');
				            jQuery('#smart_manager_company_logo').val(imgurl);
				            tb_remove();
				        }

				        formfield = jQuery('#smart_manager_company_logo').attr('name');
				        tb_show('', '<?php echo admin_url(); ?>media-upload.php?type=image&tab=upload&TB_iframe=true');
				        return false;
				}
			</script>

			<h3 style="font-size:120%;color:#656161"><?php _e( 'Print Order Settings','smart-manager-for-wp-e-commerce' );?></h3>

			<table cellspacing="15" cellpadding="5">
    			<tr>
        			<th style="background-color: inherit !important;"> 
        				<label for="smart_manager_company_logo"><b><?php _e( 'Company logo:', 'smart-manager-for-wp-e-commerce' ); ?></b></label> 
        			</th>
    				<td>
    					<input id="smart_manager_company_logo" type="text" style="margin:1px; padding:3px;" size="36" name="smart_manager_company_logo" value="<?php echo get_option( 'smart_manager_company_logo' ); ?>" />
        				<input id="upload_image_button" type="button" style="margin:1px; padding:3px; cursor: pointer;" value="<?php _e( 'Upload Image', 'smart-manager-for-wp-e-commerce' ); ?>" />
                    </td>
                </tr>
            </table>

           	<?php
		}

		//function to get current user wp_role object
		public static function getRoles( $role ) {
	        global $wp_roles;

	        $current_user_role_obj = array();
	        
	        if (function_exists('wp_roles')) {
	            $roles = wp_roles();
	        } elseif(isset($wp_roles)) {
	            $roles = $wp_roles;
	        } else {
	            $roles = new WP_Roles();
	        }

	        if( !empty( $roles->roles ) ) {
	        	$current_user_role_obj = ( !empty( $roles->roles[$role] ) ) ? $roles->roles[$role] : array();
	        }
	        
	        return $current_user_role_obj;
	    }

	    public static function is_dashboard_valid( $role, $dashboard ) {

	    	$singular_cap = array('edit_', 'read_', 'delete_');
        	$plural_cap = array('edit_','edit_others_','publish_','read_private_','delete_','delete_private_','delete_published_','delete_others_','edit_private_','edit_published_');

        	$current_user_role_obj = self::getRoles( $role );
	        $current_user_role_caps = ( !empty( $current_user_role_obj['capabilities'] ) ) ? $current_user_role_obj['capabilities'] : array();

        	$valid = array( 'custom_cap_isset' => false,
        					'dashboard_valid' => false );

        	if( $dashboard != 'post' && $dashboard != 'page' ) {
        		foreach( $singular_cap as $singular ) {

	        		$cap = $singular.''.$dashboard;

	        		if( isset( $current_user_role_caps[$cap] ) ) {

	        			$valid['custom_cap_isset'] = true;
	        			$valid['dashboard_valid'] = true;

	        			if( empty( $current_user_role_caps[$cap] ) ) {
	        				$valid['dashboard_valid'] = false;
	        				break;
	        			}
	        		}
	        	}
        	}

        	foreach( $plural_cap as $plural ) {

        		$cap = $plural.''.$dashboard.'s';

        		if( isset( $current_user_role_caps[$cap] ) ) {

        			$valid['custom_cap_isset'] = true;
	        		$valid['dashboard_valid'] = true;

        			if( empty( $current_user_role_caps[$cap] ) ) {
        				$valid['dashboard_valid'] = false;
        				break;
        			}
        		}
        	}

        	return $valid;
	    }

		public static function get_current_user_access_privilege_settings(){
			global $current_user;
			$current_user_caps = '';
			$accessible_dashboards = array();

			if (!function_exists('wp_get_current_user')) {
				require_once (ABSPATH . 'wp-includes/pluggable.php'); // Sometimes conflict with SB-Welcome Email Editor
			}

			$current_user = wp_get_current_user(); // Sometimes conflict with SB-Welcome Email Editor

	        if ( !isset( $current_user->roles[0] ) ) {
	            $roles = array_values( $current_user->roles );
	        } else {
	            $roles = $current_user->roles;
	        }

	        //Fix for the client
			if ( !empty( $current_user->caps ) ) {
	        	$caps = array_keys($current_user->caps);
	        	$current_user_caps = $roles[0] = (!empty($caps)) ? $caps[0] : '';
			}
			
			if( !( ( !empty( $current_user->roles[0] ) && $current_user->roles[0] == 'administrator' ) || (!empty($current_user_caps) && $current_user_caps == 'administrator') ) ) {
	        	$role = ( !empty( $roles[0] ) ) ? $roles[0] : $current_user_caps;
	        	$accessible_dashboards = explode( ",", get_option( self::$access_privilege_option_start.''.$role.''.self::$access_privilege_option_end, '' ) );
			}
			
			return $accessible_dashboards;
		}

		public function sm_beta_get_accessible_dashboards( $dashboards ) {

			$accessible_dashboards = self::get_current_user_access_privilege_settings();

	        if( ! empty( $accessible_dashboards ) ) {

	        	foreach( $dashboards as $key => $dashboard ) {

	        		if( !in_array( $key, $accessible_dashboards ) ) {
	        			unset( $dashboards[$key] );
	        		}
	        	}
	        }


	        if( empty($dashboards) && !defined('SM_BETA_ACCESS') ){
	        	define('SM_BETA_ACCESS', false);
	        } else if( !empty($dashboards) && !defined('SM_BETA_ACCESS') ){
	        	define('SM_BETA_ACCESS', true);
	        }

			return $dashboards;

		}

	}

}

$GLOBALS['smart_manager_pro_access_privilege'] = Smart_Manager_Pro_Access_Privilege::instance();
