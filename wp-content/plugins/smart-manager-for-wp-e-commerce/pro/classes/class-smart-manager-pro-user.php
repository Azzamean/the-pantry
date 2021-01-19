<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Pro_User' ) ) {
	class Smart_Manager_Pro_User extends Smart_Manager_Pro_Base {
		public $dashboard_key = '', $usermeta_ignored_cols = '';

		protected static $_instance = null;

		public static function instance($dashboard_key) {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self($dashboard_key);
			}
			return self::$_instance;
		}

		function __construct($dashboard_key) {
			parent::__construct($dashboard_key);
			self::actions();

			$this->dashboard_key = $dashboard_key;
			$this->post_type = $dashboard_key;
			$this->req_params  	= (!empty($_REQUEST)) ? $_REQUEST : array();

			$this->usermeta_ignored_cols = apply_filters('sm_usermeta_ignored_cols', array('session_tokens', 'wp_woocommerce_product_import_mapping', 'wp_product_import_error_log'));

			add_filter( 'sm_default_dashboard_model',array(&$this,'default_user_dashboard_model') );
			add_filter( 'sm_beta_load_default_data_model',array(&$this,'load_default_data_model') );
			add_filter( 'sm_beta_default_inline_update',array(&$this,'default_inline_update') );
			add_filter( 'sm_inline_update_post',array(&$this,'user_inline_update'), 10, 2 );
			add_filter( 'sm_data_model',array(&$this,'generate_data_model'), 10, 2 );
			add_filter( 'sm_deleter', array( &$this, 'user_deleter' ), 10, 2 );
			add_filter( 'sm_beta_delete_records_ids', array( $this, 'users_delete_record_ids' ), 10, 2 );
		}

		public static function actions() {
			// add_filter('sm_beta_batch_update_entire_store_ids_query', __CLASS__. '::users_batch_update_entire_store_ids_query', 10, 1);
			add_filter( 'sm_beta_background_entire_store_ids_from', __CLASS__. '::users_batch_update_entire_store_ids_from', 10, 2 );
			add_filter( 'sm_beta_background_entire_store_ids_where', __CLASS__. '::users_batch_update_entire_store_ids_where', 10, 2 );
			add_filter( 'sm_beta_batch_update_prev_value', __CLASS__. '::users_batch_update_prev_value', 10, 2 );
			add_filter( 'sm_default_batch_update_db_updates',  __CLASS__. '::users_default_batch_update_db_updates' );
			add_filter( 'sm_post_batch_update_db_updates', __CLASS__. '::users_post_batch_update_db_updates', 10, 2 );
		}

		public function get_batch_update_copy_from_record_ids( $args = array() ) {

			global $wpdb;
			$data = array();

			$is_ajax = ( isset( $args['is_ajax'] )  ) ? $args['is_ajax'] : true;

			$search_term = ( ! empty( $this->req_params['search_term'] ) ) ? $this->req_params['search_term'] : ( ( ! empty( $args['search_term'] ) ) ? $args['search_term'] : '' );
			$select = apply_filters( 'sm_batch_update_copy_from_user_ids_select', "SELECT ID AS id, user_login AS title", $args );
			$search_cond = ( ! empty( $search_term ) ) ? " AND ( id LIKE '%".$search_term."%' OR user_login LIKE '%".$search_term."%' OR user_email LIKE '%".$search_term."%' ) " : '';
			$search_cond_ids = ( !empty( $args['search_ids'] ) ) ? " AND id IN ( ". implode(",", $args['search_ids']) ." ) " : '';
			$results = $wpdb->get_results( $select . " FROM {$wpdb->prefix}users WHERE 1=1 ". $search_cond ." ". $search_cond_ids, 'ARRAY_A' );

			if( count( $results ) > 0 ) {
				foreach( $results as $result ) {
					$data[ $result['id'] ] = trim($result['title']);
				}
			}

			$data = apply_filters( 'sm_batch_update_copy_from_user_ids', $data );
			
			if( $is_ajax ){
				wp_send_json( $data );
			} else {
				return $data;
			}
		}

		public function default_user_dashboard_model ($dashboard_model) {

			global $wpdb, $current_user, $_wp_admin_css_colors;

			$col_model = array();

			$default_hidden_cols = apply_filters( 'sm_users_default_hidden_cols', array( 'user_url', 'user_activation_key', 'user_status' ) );
			$default_non_editable_cols = apply_filters( 'sm_users_default_non_editable_cols', array( 'ID', 'user_login' ) );
			$default_ignored_cols = apply_filters( 'sm_users_default_ignored_cols', array( 'user_activation_key', 'user_status' ) );

			$query_users_col = "SHOW COLUMNS FROM {$wpdb->users}";
			$results_users_col = $wpdb->get_results($query_users_col, 'ARRAY_A');
			$users_num_rows = $wpdb->num_rows;

			if ($users_num_rows > 0) {
				foreach ($results_users_col as $col) {
					
					$temp = array();
					$field_nm = (!empty($col['Field'])) ? $col['Field'] : '';

					if( in_array($field_nm, $default_ignored_cols) ) {
						continue;
					}

					$temp ['src'] = 'users/'.$field_nm;
					$temp ['data'] = sanitize_title(str_replace('/', '_', $temp ['src'])); // generate slug using the wordpress function if not given 
					$temp ['name'] = __(ucwords(str_replace('_', ' ', $field_nm)), 'smart-manager-for-wp-e-commerce');

					$temp ['table_name'] = $wpdb->prefix.'users';
					$temp ['col_name'] = $field_nm;

					$temp ['key'] = $temp ['name']; //for advanced search

					$type = 'text';
					$temp ['width'] = 100;
					$temp ['align'] = 'left';

					if (!empty($col['Type'])) {
						$type_strpos = strrpos($col['Type'],'(');
						if ($type_strpos !== false) {
							$type = substr($col['Type'], 0, $type_strpos);
						} else {
							$types = explode( " ", $col['Type'] ); // for handling types with attributes (biginit unsigned)
							$type = ( ! empty( $types ) ) ? $types[0] : $col['Type']; 
						}

						if (substr($type,-3) == 'int') {
							$type = 'numeric';
							$temp ['min'] = 0;
							$temp ['width'] = 50;
							$temp ['align'] = 'right';
						} else if ($type == 'text') {
							$temp ['width'] = 130;
							$type = 'text';
						} else if (substr($type,-4) == 'char' || substr($type,-4) == 'text') {
							if ($type == 'longtext') {
								$type = 'sm.longstring';
								$temp ['width'] = 150;
							} else {
								$type = 'text';
							}
						} else if (substr($type,-4) == 'blob') {
							$type = 'sm.longstring';
						} else if ($type == 'datetime' || $type == 'timestamp') {
							$type = 'sm.datetime';
							$temp ['width'] = 102;
						} else if ($type == 'date' || $type == 'year') {
							$type = 'date';
						} else if ($type == 'decimal' || $type == 'float' || $type == 'double' || $type == 'real') {
							$type = 'numeric';
							$temp ['min'] = 0;
							$temp ['width'] = 50;
							$temp ['align'] = 'right';
						} else if ($type == 'boolean') {
							$type = 'checkbox';
							$temp ['width'] = 30;
						}

					}

					$temp ['hidden']			= false;
					$temp ['editable']			= true;
					$temp ['batch_editable']	= true; // flag for enabling the batch edit for the column
					$temp ['sortable']			= true;
					$temp ['resizable']			= true;

					//For disabling frozen
					$temp ['frozen']			= false;

					$temp ['allow_showhide']	= true;
					$temp ['exportable']		= true; //default true. flag for enabling the column in export
					$temp ['searchable']		= true;

					if( $field_nm == 'user_registered' ) {
						$temp ['searchable']		= false;	
					}
					
					$temp ['placeholder'] = ''; //for advanced search

					//Code for handling the positioning of the columns
					if ($field_nm == 'ID') {
						$temp ['position'] = 1;
						$temp ['align'] = 'left';
					} else if ($field_nm == 'user_login') {
						$temp ['position'] = 2;
					} else if ($field_nm == 'user_pass') {
						$temp ['position'] = 3;
						$temp ['searchable'] = false;
						$temp ['placeholder'] = 'Click to change';
						$type = 'text';
					} else if ($field_nm == 'user_nicename') {
						$temp ['name'] = __('Nickname', 'smart-manager-for-wp-e-commerce');
						$temp ['key'] = $temp ['name'];
					}

					if( !empty( $default_non_editable_cols ) && in_array( $field_nm, $default_non_editable_cols ) ) {
						$temp ['editable'] = false;
						$temp ['batch_editable'] = false;
					}

					if( $field_nm == 'user_pass' ){
						$temp ['type'] = 'password';
					} else {
						$temp ['type'] = $type;
					}

					$temp ['values'] = array();
					$temp ['hidden'] = ( in_array($field_nm, $default_hidden_cols) ) ? true : false;
					$temp ['category'] = ''; //for advanced search
					$col_model [] = $temp;
				}
			}

			$default_um_visible_cols = apply_filters('sm_usermeta_visible_cols', array('first_name', 'last_name', 'description', 'rich_editing', 'billing_first_name', 'billing_last_name', 'billing_company', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode', 'billing_country', 'billing_email', 'billing_phone'));

			$default_um_disabled_cols = apply_filters('sm_usermeta_disabled_cols', array('billing_country', 'billing_state'));

			//code for getting the meta cols
			$results_usermeta_col = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT(meta_key) as meta_key,
																meta_value
															FROM {$wpdb->usermeta}
															WHERE meta_key NOT IN ( '". implode("','", $this->usermeta_ignored_cols) ."' )
																AND 1=%d
															GROUP BY meta_key", 1), 'ARRAY_A');
			$um_num_rows = $wpdb->num_rows;

			if ($um_num_rows > 0) {

				$meta_keys = array();

				foreach ($results_usermeta_col as $key => $usermeta_col) {
					if (empty($usermeta_col['meta_value'])) {
						$meta_keys [] = $usermeta_col['meta_key']; //TODO: if possible store in db instead of using an array
					}

					unset($results_usermeta_col[$key]);
					$results_usermeta_col[$usermeta_col['meta_key']] = $usermeta_col;
				}

				if (!empty($meta_keys)) {
					$results_um_meta_value = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT(meta_key) as meta_key,
																				meta_value
																			FROM {$wpdb->usermeta}
																			WHERE meta_key IN ( '". implode("','", $meta_keys) ."' )
																				AND meta_value != %s
																			GROUP BY meta_key", ''), 'ARRAY_A');
					$num_rows_meta_value = $wpdb->num_rows;

					if ($num_rows_meta_value > 0) {
						foreach ($results_um_meta_value as $result_meta_value) {
							if (isset($results_usermeta_col [$result_meta_value['meta_key']])) {
								$results_usermeta_col [$result_meta_value['meta_key']]['meta_value'] = $result_meta_value['meta_value'];
							}
						}
					}
				}

				$index = count($col_model);

				$src = '';

				if( ! empty( $col_model [$index]['src'] ) ) {
					$src = str_replace('/', '_', $col_model [$index]['src']);
				}
				$user_id = 'user id';

				$col_model [$index] = array();
				$col_model [$index]['src'] = 'usermeta/user_id';
				$col_model [$index]['data'] = sanitize_title($src); // generate slug using the WordPress function if not given 
				$col_model [$index]['name'] = __(ucwords($user_id), 'smart-manager-for-wp-e-commerce');
				$col_model [$index]['key'] = $col_model [$index]['name']; //for advanced search
				$col_model [$index]['type'] = 'numeric';
				$col_model [$index]['hidden']	= true;
				$col_model [$index]['allow_showhide'] = false;
				$col_model [$index]['editable']	= false;
				$col_model [$index]['batch_editable']	= false;
				$col_model [$index]['exportable']		= true; //default true. flag for enabling the column in export
				$col_model [$index]['searchable']		= true;

				$col_model [$index]['table_name'] = $wpdb->prefix.'usermeta';
				$col_model [$index]['col_name'] = 'user_id';

				$col_model [$index] ['category'] = ''; //for advanced search
				$col_model [$index] ['placeholder'] = ''; //for advanced search

				$index++;

				$src = '';
				if( ! empty( $col_model [$index]['src'] ) ){
					$src = str_replace('/', '_', $col_model [$index]['src']);
				}
				$role = 'role';

				$col_model [$index] = array();
				$col_model [$index]['src'] = 'usermeta/role';
				$col_model [$index]['data'] = sanitize_title($src); // generate slug using the wordpress function if not given 
				$col_model [$index]['name'] = __(ucwords($role), 'smart-manager-for-wp-e-commerce');
				$col_model [$index]['key'] = $col_model [$index]['name']; //for advanced search
				$col_model [$index]['type'] = 'dropdown';
				$col_model [$index]['strict'] = true;
				$col_model [$index]['allowInvalid'] = false;
				$col_model [$index]['hidden']	= false;
				$col_model [$index]['allow_showhide'] = true;
				$col_model [$index]['editable']	= true;
				$col_model [$index]['position'] = 2;
				$col_model [$index]['batch_editable']	= true; // flag for enabling the batch edit for the column
				$col_model [$index]['exportable']		= true; //default true. flag for enabling the column in export
				$col_model [$index]['searchable']		= true;

				$all_roles = array();
				$col_model [$index]['values'] = array();

				if( function_exists('get_editable_roles') ) {
					$all_roles = get_editable_roles();	
				}

				if( !empty( $all_roles ) ) {

					$col_model [$index]['search_values'] = array();

					foreach ( $all_roles as $role => $details) {
                		$name = translate_user_role( $details['name'] );
                		$col_model [$index]['values'][$role] = $name;
                		$col_model [$index]['search_values'][] = array('key' => $role, 'value' => $name);
                	}
				}

				$col_model [$index] ['editor'] = 'select';
				$col_model [$index] ['selectOptions'] = $col_model [$index]['values'];
				$col_model [$index] ['renderer'] = 'selectValueRenderer';

				$col_model [$index]['table_name'] = $wpdb->prefix.'usermeta';
				$col_model [$index]['col_name'] = $wpdb->prefix.'capabilities';

				$col_model [$index] ['category'] = ''; //for advanced search
				$col_model [$index] ['placeholder'] = ''; //for advanced search


				$index++;

				$custom_cols = array('last_order_date', 'last_order_total', 'orders_count', 'orders_total');

				foreach( $custom_cols as $col ) {
					$col_model [$index] = array();
					$col_model [$index]['src'] = 'custom/'.$col;
					$col_model [$index]['data'] = sanitize_title(str_replace('/', '_', $col_model [$index]['src'])); // generate slug using the wordpress function if not given 
					$col_model [$index]['name'] = __(ucwords(str_replace('_', ' ', $col)), 'smart-manager-for-wp-e-commerce');
					$col_model [$index]['key'] = $col_model [$index]['name']; //for advanced search
					$col_model [$index]['type'] = 'text';
					$col_model [$index]['hidden']	= false;
					$col_model [$index]['allow_showhide'] = true;
					$col_model [$index]['editable']	= false;
					$col_model [$index]['sortable']	= false;

					$col_model [$index]['table_name'] = $wpdb->prefix.'usermeta';
					$col_model [$index]['col_name'] = 'user_id';
					$col_model [$index]['exportable'] = true; //default true. flag for enabling the column in export
					$col_model [$index]['searchable'] = true;

					$col_model [$index] ['category'] = ''; //for advanced search
					$col_model [$index] ['placeholder'] = ''; //for advanced search

					$index++;
				}

				foreach ($results_usermeta_col as $usermeta_col) {

					$temp = array();
					$type = 'text';

					$meta_key = ( !empty( $usermeta_col['meta_key'] ) ) ? $usermeta_col['meta_key'] : '';
					$meta_value = ( !empty( $usermeta_col['meta_value'] ) || $usermeta_col['meta_value'] == 0 ) ? $usermeta_col['meta_value'] : '';

					$temp ['src'] = 'usermeta/meta_key='.$meta_key.'/meta_value='.$meta_key;
					$temp ['data'] = sanitize_title(str_replace(array('/','='), '_', $temp ['src'])); // generate slug using the wordpress function if not given 
					$temp ['name'] = __(ucwords(str_replace('_', ' ', $meta_key)), 'smart-manager-for-wp-e-commerce');
					$temp ['key'] = $temp ['name']; //for advanced search

					$temp ['table_name'] = $wpdb->prefix.'usermeta';
					$temp ['col_name'] = $meta_key;

					$temp ['width'] = 100;
					$temp ['align'] = 'left';

					if ( $meta_value == 'yes' || $meta_value == 'no' || $meta_value == 'true' || $meta_value == 'false' || ( is_numeric($meta_value) && ( $meta_value == 0 || $meta_value == 1 ) ) ) {
						$type = 'checkbox';

						if( $meta_value == 'yes' || $meta_value == 'no' ) {
							$temp ['checkedTemplate'] = 'yes';
      						$temp ['uncheckedTemplate'] = 'no';
						} else if( is_int( $meta_value ) && ( $meta_value == 0 || $meta_value == 1 ) ) {
							$temp ['checkedTemplate'] = 0;
      						$temp ['uncheckedTemplate'] = 1;
						}

						$temp ['width'] = 30;
					} else if( is_numeric( $meta_value ) ) {

						if( function_exists('isTimestamp') ) {
							if( isTimestamp( $meta_value ) ) {
								$type = 'sm.datetime';
								$temp ['width'] = 102;
								$temp ['date_type'] = 'timestamp';
							}
						} 

						if( $type != 'sm.datetime' ) {
							$type = 'numeric';
							$temp ['min'] = 0;	
							$temp ['width'] = 50;
							$temp ['align'] = 'right';	
						}
					} else if( is_serialized( $meta_value ) === true ) {
						$type = 'sm.longstring';
						$temp ['width'] = 200;
					}

					$temp ['type'] = $type;
					$temp ['values'] = array();

					if( $meta_key == 'admin_color' ) {

						$temp ['search_values'] = array();
						
						$themes = array_keys($_wp_admin_css_colors);
						foreach( $themes as $theme ) {
							$name = ( !empty($_wp_admin_css_colors[$theme]) ) ? $_wp_admin_css_colors[$theme]->name : ucwords($theme);
							$temp ['values'][$theme] = $name;
                			$temp ['search_values'][] = array('key' => $theme, 'value' => $name);
						}
					}

					$temp ['hidden'] = ( !empty($default_um_visible_cols) && in_array($meta_key, $default_um_visible_cols) ) ? false : true;
					$hidden_col_array = array('_edit_lock','_edit_last');

					if (array_search($meta_key,$hidden_col_array) !== false ) {
						$temp ['hidden'] = true;	
					}

					
					$temp ['editable']			= ( !empty($default_um_disabled_cols) && in_array($meta_key, $default_um_disabled_cols) ) ? false : true;
					$temp ['batch_editable']	= true; // flag for enabling the batch edit for the column
					$temp ['sortable']			= true;
					$temp ['resizable']			= true;
					$temp ['frozen']			= false;
					$temp ['allow_showhide']	= true;
					$temp ['exportable']		= true; //default true. flag for enabling the column in export
					$temp ['searchable']		= true;

					$temp ['category'] = ''; //for advanced search
					$temp ['placeholder'] = ''; //for advanced search

					$col_model [] = $temp;
				}

			}

			$dashboard_model[$this->dashboard_key]['columns'] = $col_model;

			return $dashboard_model;			
		}

		//function to avoid generation of the default data model
		public function load_default_data_model ($flag) {
			return false;
		}


		public function process_user_search_cond($params = array()) {

			global $wpdb;


			if( empty($params) || empty($params['search_query']) ) {
				return;
			}

			$rule_groups = ( ! empty( $params['search_query'] ) ) ? $params['search_query'][0]['rules'] : array();

			if( empty( $rule_groups ) ) {
				return;
			}

			$wpdb->query("DELETE FROM {$wpdb->base_prefix}sm_advanced_search_temp"); // query to reset advanced search temp table

            $advanced_search_query = array();
            $i = 0;

            $search_cols_type = ( ! empty( $params['search_cols_type'] ) ) ? $params['search_cols_type'] : array();

            foreach ($rule_groups as $rule_group) {

                if (is_array($rule_group)) {

                		// START FROM HERE

                        $advanced_search_query[$i] = array();
                        $advanced_search_query[$i]['cond_users'] = '';
                        $advanced_search_query[$i]['cond_usermeta'] = '';

                        $advanced_search_query[$i]['cond_usermeta_col_name'] = '';
                        $advanced_search_query[$i]['cond_usermeta_col_value'] = '';
                        $advanced_search_query[$i]['cond_usermeta_operator'] = '';

                        $search_value_is_array = 0; //flag for array of search_values

						$rule_group = apply_filters('sm_user_before_search_string_process', $rule_group);
						$rules = ( ! empty( $rule_group['rules'] ) ) ? $rule_group['rules'] : array();

                        foreach( $rules as $rule ) {

							if( ! empty( $rule['type'] ) ) {
								$field = explode( '.', $rule['type'] );
								$rule['table_name'] = ( ! empty( $field[0] ) ) ? $field[0] : '';
								$rule['col_name'] = ( ! empty( $field[1] ) ) ? $field[1] : '';
							}

                            $search_col = (!empty($rule['col_name'])) ? $rule['col_name'] : '';
							$search_operator = (!empty($rule['operator'])) ? $rule['operator'] : '';
							$search_operator = ( ! empty( $this->advance_search_operators[$search_operator] ) ) ? $this->advance_search_operators[$search_operator] : $search_operator;
                            $search_data_type = ( ! empty( $search_cols_type[$rule['type']] ) ) ? $search_cols_type[$rule['type']] : 'text';
                            $search_value = (!empty($rule['value']) && $rule['value'] != "''") ? $rule['value'] : (($search_data_type == "number") ? '0' : '');

                            $search_params = array('search_string' => $rule,
													'search_col' => $search_col,
													'search_operator' => $search_operator, 
													'search_data_type' => $search_data_type, 
													'search_value' => $search_value,
													'SM_IS_WOO30' => (!empty($params['SM_IS_WOO30'])) ? $params['SM_IS_WOO30'] : '');

                           	if( !empty( $params['data_col_params'] ) ) {
                            	$search_value = ( in_array($search_col, $params['data_col_params']['data_cols_timestamp']) ) ? strtotime($search_value) : $search_value;
                            }

                            if (!empty($rule['table_name']) && $rule['table_name'] == $wpdb->prefix.'users') {

                            	$search_col = apply_filters('sm_search_format_query_users_col_name', $search_col, $search_params);
                                $search_value = apply_filters('sm_search_format_query_users_col_value', $search_value, $search_params);

                                if ($search_data_type == "number") {
                                    $users_cond = $rule['table_name'].".".$search_col . " ". $search_operator ." " . $search_value;
                                } else if ( $search_data_type == "date" || $search_data_type == "sm.datetime" ) {
                                	$users_cond = $rule['table_name'].".".$search_col . " ". $search_operator ." '" . $search_value ."' ";
                                } else {
                                    if ($search_operator == 'is') {
                                        $users_cond = $rule['table_name'].".".$search_col . " LIKE '" . $search_value . "'";
                                    } else if ($search_operator == 'is not') {
                                        $users_cond = $rule['table_name'].".".$search_col . " NOT LIKE '" . $search_value . "'";
                                    } else {
                                        $users_cond = $rule['table_name'].".".$search_col . " ". $search_operator ."'%" . $search_value . "%'";
                                    }
                                }

                                $users_cond = apply_filters('sm_search_users_cond', $users_cond, $search_params);

                                $advanced_search_query[$i]['cond_users'] .= $users_cond ." AND ";

                            } else if (!empty($rule['table_name']) && $rule['table_name'] == $wpdb->prefix.'usermeta') {

                                $advanced_search_query[$i]['cond_usermeta_col_name'] .= $search_col;
                                $advanced_search_query[$i]['cond_usermeta_col_value'] .= $search_value;

                                $search_col = apply_filters('sm_search_format_query_usermeta_col_name', $search_col, $search_params);
                                $search_value = apply_filters('sm_search_format_query_usermeta_col_value', $search_value, $search_params);

                                if ($search_data_type == "number") {
                                    $postmeta_cond = " ( ". $rule['table_name'].".meta_key LIKE '". $search_col . "' AND ". $rule['table_name'] .".meta_value ". $search_operator ." " . $search_value . " )";
                                    $advanced_search_query[$i]['cond_usermeta_operator'] .= $search_operator;
                                } else if ( $search_data_type == "date" || $search_data_type == "sm.datetime" ) {
                                	$postmeta_cond = " ( ". $rule['table_name'].".meta_key LIKE '". $search_col . "' AND ". $rule['table_name'] .".meta_value ". $search_operator ." '" . $search_value . "' )";
                                    $advanced_search_query[$i]['cond_usermeta_operator'] .= $search_operator;
                                } else {
                                    if( $search_operator == 'is' ) {

                                    	if( $search_key == 'Role' ) {
                                    		$search_value = '%'. $search_value .'%';
                                    	}

                                        $advanced_search_query[$i]['cond_usermeta_operator'] .= 'LIKE';
                                        $postmeta_cond = " ( ". $rule['table_name'].".meta_key LIKE '". $search_col . "' AND ". $rule['table_name'] .".meta_value LIKE '" . $search_value . "'" . " )";

                                        
                                    } else if( $search_operator == 'is not' ) {

                                    	if( $search_key == 'Role' ) {
                                    		$search_value = '%'. $search_value .'%';
                                    	}

                                        $advanced_search_query[$i]['cond_usermeta_operator'] .= 'NOT LIKE';
                                        $postmeta_cond = " ( ". $rule['table_name'].".meta_key LIKE '". $search_col . "' AND ". $rule['table_name'] .".meta_value NOT LIKE '" . $search_value . "'" . " )";

                                    } else {

                                        $advanced_search_query[$i]['cond_usermeta_operator'] .= $search_operator;
                                        $postmeta_cond = " ( ". $rule['table_name'].".meta_key LIKE '". $search_col . "' AND ". $rule['table_name'] .".meta_value ". $search_operator ." '%" . $search_value . "%'" . " )";
                                    }
                                    
                                }

                                $postmeta_cond = apply_filters('sm_search_usermeta_cond', $postmeta_cond, $search_params);

                                $advanced_search_query[$i]['cond_usermeta'] .= $postmeta_cond ." AND ";
                                $advanced_search_query[$i]['cond_usermeta_col_name'] .= " AND ";
                                $advanced_search_query[$i]['cond_usermeta_col_value'] .= " AND ";
                                $advanced_search_query[$i]['cond_usermeta_operator'] .= " AND ";

                            }

                            $advanced_search_query[$i] = apply_filters('sm_user_search_query_formatted', $advanced_search_query[$i], $search_params);
                        }

                        $advanced_search_query[$i]['cond_users'] = (!empty($advanced_search_query[$i]['cond_users'])) ? substr( $advanced_search_query[$i]['cond_users'], 0, -4 ) : '';
                        $advanced_search_query[$i]['cond_usermeta'] = (!empty($advanced_search_query[$i]['cond_usermeta'])) ? substr( $advanced_search_query[$i]['cond_usermeta'], 0, -4 ) : '';

                        $advanced_search_query[$i]['cond_usermeta_col_name'] = (!empty($advanced_search_query[$i]['cond_usermeta_col_name'])) ? substr( $advanced_search_query[$i]['cond_usermeta_col_name'], 0, -4 ) : '';
                        $advanced_search_query[$i]['cond_usermeta_col_value'] = (!empty($advanced_search_query[$i]['cond_usermeta_col_value'])) ? substr( $advanced_search_query[$i]['cond_usermeta_col_value'], 0, -4 ) : '';
                        $advanced_search_query[$i]['cond_usermeta_operator'] = (!empty($advanced_search_query[$i]['cond_usermeta_operator'])) ? substr( $advanced_search_query[$i]['cond_usermeta_operator'], 0, -4 ) : '';

                    }

                    $i++;
				}
				
                //Code for handling advanced search conditions
		        if (!empty($advanced_search_query)) {

		            $index_search_string = 1; // index to keep a track of flags in the advanced search temp 
		            $search_params = array();

		            foreach ($advanced_search_query as &$advanced_search_query_string) {

		                //Cond for usermeta
		                if (!empty($advanced_search_query_string['cond_usermeta'])) {

		                    $cond_usermeta_array = explode(" AND  ",$advanced_search_query_string['cond_usermeta']);

		                    $cond_usermeta_col_name = (!empty($advanced_search_query_string['cond_usermeta_col_name'])) ? explode(" AND ",$advanced_search_query_string['cond_usermeta_col_name']) : '';
		                    $cond_usermeta_col_value = (!empty($advanced_search_query_string['cond_usermeta_col_value'])) ? explode(" AND ",$advanced_search_query_string['cond_usermeta_col_value']) : '';
		                    $cond_usermeta_operator = (!empty($advanced_search_query_string['cond_usermeta_operator'])) ? explode(" AND ",$advanced_search_query_string['cond_usermeta_operator']) : '';

		                    $index = 0;
		                    $cond_usermeta_post_ids = '';
		                    $result_usermeta_search = '';

		                    foreach ($cond_usermeta_array as $cond_usermeta) {

		                        $usermeta_search_result_flag = ( $index == (sizeof($cond_usermeta_array) - 1) ) ? ', '.$index_search_string : ', 0';

		                        $cond_usermeta_col_name1 = (!empty($cond_usermeta_col_name[$index])) ? trim($cond_usermeta_col_name[$index]) : '';
		                        $cond_usermeta_col_value1 = (!empty($cond_usermeta_col_value[$index])) ? trim($cond_usermeta_col_value[$index]) : '';
		                        $cond_usermeta_operator1 = (!empty($cond_usermeta_operator[$index])) ? trim($cond_usermeta_operator[$index]) : '';

		                        $search_params = array('cond_usermeta_col_name' => $cond_usermeta_col_name1,
		                    							'cond_usermeta_col_value' => $cond_usermeta_col_value1,
		                    							'cond_usermeta_operator' => $cond_usermeta_operator1,
		                    							'SM_IS_WOO30' => (!empty($params['SM_IS_WOO30'])) ? $params['SM_IS_WOO30'] : '');

		                        $cond_usermeta = apply_filters('sm_search_usermeta_condition_start', $cond_usermeta, $search_params);

		                        $search_params['cond_usermeta'] = $cond_usermeta;

		                        $usermeta_advanced_search_select = 'SELECT DISTINCT '.$wpdb->prefix.'usermeta.user_id '. $usermeta_search_result_flag .' ,0 ';
		                        $usermeta_advanced_search_from = 'FROM '.$wpdb->prefix.'usermeta ';
		                        $usermeta_advanced_search_where = 'WHERE '.$cond_usermeta;

		                        $usermeta_advanced_search_select = apply_filters('sm_search_query_usermeta_select', $usermeta_advanced_search_select, $search_params);
								$usermeta_advanced_search_from	= apply_filters('sm_search_query_usermeta_from', $usermeta_advanced_search_from, $search_params);
								$usermeta_advanced_search_where	= apply_filters('sm_search_query_usermeta_where', $usermeta_advanced_search_where, $search_params);

		                        //Query to find if there are any previous conditions
		                        $count_temp_previous_cond = $wpdb->query("UPDATE {$wpdb->base_prefix}sm_advanced_search_temp 
		                                                                    SET flag = 0
		                                                                    WHERE flag = ". $index_search_string);

		                        //Code to handle condition if the ids of previous cond are present in temp table
		                        if (($index == 0 && $count_temp_previous_cond > 0) || (!empty($result_usermeta_search))) {
		                            $usermeta_advanced_search_from .= " JOIN ".$wpdb->base_prefix."sm_advanced_search_temp
		                                                                ON (".$wpdb->base_prefix."sm_advanced_search_temp.product_id = {$wpdb->usermeta}.user_id)";

		                            $usermeta_advanced_search_where .= " AND ".$wpdb->base_prefix."sm_advanced_search_temp.flag = 0";
		                        }

		                        $result_usermeta_search = array();

		                        if (!empty($usermeta_advanced_search_select ) && !empty($usermeta_advanced_search_from ) && !empty($usermeta_advanced_search_where )) {
			                        $query_usermeta_search = "REPLACE INTO {$wpdb->base_prefix}sm_advanced_search_temp
			                                                        (". $usermeta_advanced_search_select ."
			                                                        ". $usermeta_advanced_search_from ."
			                                                        ".$usermeta_advanced_search_where.")";
			                        $result_usermeta_search = $wpdb->query ( $query_usermeta_search );
			                    }

			                    do_action('sm_search_usermeta_condition_complete',$result_usermeta_search,$search_params);

		                        $index++;
		                    }

		                    do_action('sm_search_usermeta_conditions_array_complete',$search_params);

		                    //Query to delete the unwanted post_ids
		                    $wpdb->query("DELETE FROM {$wpdb->base_prefix}sm_advanced_search_temp WHERE flag = 0");
		                }

		                //Cond for users
		                if (!empty($advanced_search_query_string['cond_users'])) {

		                    $cond_users_array = explode(" AND ",$advanced_search_query_string['cond_users']);

		                    $index = 0;
		                    $cond_users_post_ids = '';
		                    $result_users_search = '';

		                    foreach ( $cond_users_array as $cond_users ) {

		                        $users_search_result_flag = ( $index == (sizeof($cond_users_array) - 1) ) ? ', '.$index_search_string : ', 0';

		                        $cond_users = apply_filters('sm_search_users_condition_start', $cond_users);

		                        $search_params = array('cond_users' => $cond_users,
		                    							'SM_IS_WOO30' => (!empty($params['SM_IS_WOO30'])) ? $params['SM_IS_WOO30'] : '');

		                        $users_advanced_search_select = "SELECT DISTINCT {$wpdb->users}.id ". $users_search_result_flag ." ,0 ";
		                        $users_advanced_search_from = " FROM {$wpdb->users} ";
		                        $users_advanced_search_where = " WHERE ". $cond_users ." ";

		                        $users_advanced_search_select = apply_filters('sm_search_query_users_select', $users_advanced_search_select, $search_params);
								$users_advanced_search_from	= apply_filters('sm_search_query_users_from', $users_advanced_search_from, $search_params);
								$users_advanced_search_where	= apply_filters('sm_search_query_users_where', $users_advanced_search_where, $search_params);

		                        //Query to find if there are any previous conditions
		                        $count_temp_previous_cond = $wpdb->query("UPDATE {$wpdb->base_prefix}sm_advanced_search_temp 
		                                                                    SET flag = 0
		                                                                    WHERE flag = ". $index_search_string);


		                        //Code to handle condition if the ids of previous cond are present in temp table
		                        if ( ($index == 0 && $count_temp_previous_cond > 0) || (!empty($result_users_search)) ) {
		                            $users_advanced_search_from .= " JOIN ".$wpdb->base_prefix."sm_advanced_search_temp
		                                                                ON (".$wpdb->base_prefix."sm_advanced_search_temp.product_id = {$wpdb->users}.id) ";

		                            $users_advanced_search_where .= " AND ".$wpdb->base_prefix."sm_advanced_search_temp.flag = 0 ";
		                        }

		                        $result_users_search = array();

		                        if (!empty($users_advanced_search_select ) && !empty($users_advanced_search_from ) && !empty($users_advanced_search_where )) {
			                        $query_users_search = "REPLACE INTO {$wpdb->base_prefix}sm_advanced_search_temp
			                                                        ( ". $users_advanced_search_select ."
			                                                        ". $users_advanced_search_from ."
			                                                        ". $users_advanced_search_where .")";
			                        $result_users_search = $wpdb->query ( $query_users_search );
			                    }
		                        
			                    do_action('sm_search_users_condition_complete',$result_users_search,$search_params);

		                        $index++;
		                    }

		                    do_action('sm_search_users_conditions_array_complete',$search_params);

		                    //Query to delete the unwanted post_ids
		                    $wpdb->query("DELETE FROM {$wpdb->base_prefix}sm_advanced_search_temp WHERE flag = 0");

		                }
		                $index_search_string++;
		            }
		        }
		}

		//function to generate data model
		public function generate_data_model ($data_model, $data_col_params) {
			global $wpdb, $current_user;

			$items = array();
			$index = 0;

			$join = $where = '';
			$order_by = " ORDER BY {$wpdb->users}.id DESC ";

			$start = (!empty($this->req_params['start'])) ? $this->req_params['start'] : 0;
			$limit = (!empty($this->req_params['sm_limit'])) ? $this->req_params['sm_limit'] : 50;
			$current_page = (!empty($this->req_params['sm_page'])) ? $this->req_params['sm_page'] : '1';
			$start_offset = ($current_page > 1) ? (($current_page - 1) * $limit) : $start;

			$current_store_model = get_transient( 'sa_sm_'.$this->dashboard_key );
			$col_model = (!empty($current_store_model['columns'])) ? $current_store_model['columns'] : array();

			$search_cols_type = array(); //array for col & its type for advanced search

			if (!empty($col_model)) {
				foreach ($col_model as $col) {
					if( ! empty( $col['table_name'] ) && ! empty( $col['col_name'] ) ){
						$search_cols_type[ $col['table_name'] .'.'. $col['col_name'] ] = $col['type'];
					}
				}
			}

			//Code to clear the advanced search temp table
	        if ( empty($this->req_params['advanced_search_query']) || $this->req_params['advanced_search_query'] == '[]') {
	            $wpdb->query("DELETE FROM {$wpdb->base_prefix}sm_advanced_search_temp");
	            delete_option('sm_advanced_search_query');
	        }        

	        // if( !empty($this->req_params['date_filter_query']) && ( defined('SMPRO') && true === SMPRO ) ) {

	        // 	if( empty($this->req_params['search_query']) ) {
	        // 		$this->req_params['search_query'] = array( $this->req_params['date_filter_query'] );
	        // 	} else {

	        // 		$date_filter_array = json_decode(stripslashes($this->req_params['date_filter_query']),true);

	        // 		foreach( $this->req_params['search_query'] as $key => $search_string_array ) {
	        // 			$search_string_array = json_decode(stripslashes($search_string_array),true);

	        // 			foreach( $date_filter_array as $date_filter ) {
			// 				$search_string_array[] = $date_filter;		
	        // 			}

	        // 			$this->req_params['search_query'][$key] = addslashes(json_encode($search_string_array));
	        // 		}
	        // 	}
	        // }

	        $sm_advanced_search_results_persistent = 0; //flag to handle persistent search results

	        //Code fo handling advanced search functionality
	        if( !empty( $this->req_params['advanced_search_query'] ) && $this->req_params['advanced_search_query'] != '[]' ) {

				$this->req_params['advanced_search_query'] = json_decode(stripslashes($this->req_params['advanced_search_query']), true);

	            if (!empty($this->req_params['advanced_search_query'])) {

					$this->process_user_search_cond(array( 'search_query' => (!empty($this->req_params['advanced_search_query'])) ? $this->req_params['advanced_search_query'] : array(),
														'SM_IS_WOO30' => (!empty($this->req_params['SM_IS_WOO30'])) ? $this->req_params['SM_IS_WOO30'] : '',
														'search_cols_type' => $search_cols_type	
													)
												);

	            }

	            $join = " JOIN {$wpdb->base_prefix}sm_advanced_search_temp
                            	ON ({$wpdb->base_prefix}sm_advanced_search_temp.product_id = {$wpdb->users}.id)";

                $where = " AND {$wpdb->base_prefix}sm_advanced_search_temp.flag > 0";

	        }

	        //Code to handle simple search functionality
	        if( !empty( $this->req_params['search_text'] ) ) {


	        	$user_where_cond = array();

	        	$search_text = $wpdb->_real_escape( $this->req_params['search_text'] );

	        	$join = " JOIN {$wpdb->usermeta} 
	        				ON ({$wpdb->usermeta}.user_id = {$wpdb->users}.id)";
	        				
	        	//Code for getting users table condition
	        	if( !empty( $col_model ) ) {
	        		foreach( $col_model as $col ) {
	        			if (empty($col['src'])) continue;

						$src_exploded = explode("/",$col['src']);

						$ignored_cols = array('user_pass');
	        			$simple_search_ignored_cols = apply_filters('sm_simple_search_ignored_users_columns', $ignored_cols, $col_model);

						if( !empty( $src_exploded[0] ) && $src_exploded[0] == 'users' && !in_array($src_exploded[1], $simple_search_ignored_cols) ) {
							$user_where_cond[] = "( {$wpdb->users}.".$src_exploded[1]." LIKE '%".$search_text."%' )";
						}
	        		}
	        	}

				$where = " AND (({$wpdb->usermeta}.meta_value LIKE '%".$search_text."%') ";
				$where .= ( ( !empty( $user_where_cond ) ) ? ' OR '. implode(" OR ", $user_where_cond) : '' )." )";
			}

			if( !empty( $this->req_params['sort_params'] ) ) {
	        	if( !empty( $this->req_params['sort_params']['column'] ) && !empty( $this->req_params['sort_params']['sortOrder'] ) ) {

	        		$usermeta_cols = $numeric_usermeta_cols = array();

	        		foreach( $col_model as $col ) {
	        			if (empty($col['src'])) continue;

						$src_exploded = explode("/",$col['src']);

						if (empty($col_exploded)) continue;

						if ( sizeof($col_exploded) > 2) {
							$col_meta = explode("=",$col_exploded[1]);
							$col_nm = $col_meta[1];
						} else {
							$col_nm = $col_exploded[1];
						}

						if( !empty( $col_exploded[0] ) && $col_exploded[0] == 'usermeta' && $col_nm != 'user_id' ) {
							$usermeta_cols[] = $col_nm;

							if( $type == 'number' || $type == 'numeric' ) {
								$numeric_usermeta_cols[] = $col_nm;
							}
						}
					}

					if( false !== strpos($this->req_params['sort_params']['column'], "/") ) {
		        		$col_exploded = explode( "/", $this->req_params['sort_params']['column'] );
		        		$table_nm = $col_exploded[0];

		        		if ( sizeof($col_exploded) > 2) {
							$col_meta = explode("=",$col_exploded[1]);
							$this->req_params['sort_params']['column_nm'] = $col_meta[0];

							if( $this->req_params['sort_params']['column_nm'] == 'meta_key' ) {
								$this->req_params['sort_params']['sort_by_meta_key'] = $col_meta[1];
								$this->req_params['sort_params']['column_nm'] = ( !empty( $numeric_usermeta_cols ) && in_array( $col_meta[1], $numeric_usermeta_cols ) ) ? 'meta_value+0' : 'meta_value';
							}
						} else {
							$this->req_params['sort_params']['column_nm'] = $col_exploded[1];
						}

						$this->req_params['sort_params']['sortOrder'] = strtoupper( $this->req_params['sort_params']['sortOrder'] );

						$join = " JOIN {$wpdb->usermeta} 
		        					ON ({$wpdb->usermeta}.user_id = {$wpdb->users}.id)";

		        		if( !empty( $this->req_params['sort_params']['sort_by_meta_key'] ) ) {
		        			$where .= " AND ( ".$wpdb->prefix."".$table_nm.".meta_key ='".$this->req_params['sort_params']['sort_by_meta_key'] ."' ) ";
		        		}

						$order_by = " ORDER BY ".$wpdb->prefix."".$table_nm.".".$this->req_params['sort_params']['column_nm']." ".$this->req_params['sort_params']['sortOrder']." ";
	        		}
	        		
	        	}
	        }

	        $query_limit_str = ( !empty( $this->req_params['cmd'] ) && $this->req_params['cmd'] == 'get_export_csv' ) ? '' : 'LIMIT '.$start_offset.', '.$limit;


			//code to fetch data from users table
			$user_ids = $wpdb->get_col( $wpdb->prepare("SELECT DISTINCT {$wpdb->users}.id 
														FROM {$wpdb->users}
														". $join ."
														WHERE 1=%d 
														".$where, 1));
			$users_total_count = $wpdb->num_rows;

			//Code for saving the post_ids in case of simple search
			if( ( defined('SMPRO') && true === SMPRO ) && !empty( $this->req_params['search_text'] ) || (!empty($this->req_params['advanced_search_query']) && $this->req_params['advanced_search_query'] != '[]') ) {
				$user_ids_imploded = implode( ",",$user_ids );
				set_transient( 'sa_sm_search_post_ids', $user_ids_imploded , WEEK_IN_SECONDS );
			}

			$users_results = $wpdb->get_results( $wpdb->prepare("SELECT {$wpdb->users}.* 
																FROM {$wpdb->users}
																". $join ." 
																WHERE 1=%d 
																". $where ." 
																GROUP BY {$wpdb->users}.id 
																". $order_by ."
																". $query_limit_str, 1), ARRAY_A );

			$total_pages = 1;

        	if( $users_total_count > $limit && $this->req_params['cmd'] != 'get_export_csv' ) {
        		$total_pages = ceil($users_total_count/$limit);
        	}

			if( !empty( $users_results ) ) {

				$user_ids = array();

				foreach( $users_results as $user ) {
					if( !empty($user['ID']) ) {
						$user_ids[] = $user['ID'];
					}
				}

				//code to get the usermeta data
				$um_results = $wpdb->get_results( $wpdb->prepare("SELECT user_id,
																		meta_key,
																		meta_value 
																		FROM {$wpdb->usermeta} 
																		WHERE 1=%d 
																			AND user_id IN (". implode(",", $user_ids) .") 
																			AND meta_key NOT IN ('". implode("','",$this->usermeta_ignored_cols) ."')
																		GROUP BY user_id, meta_key", 1), ARRAY_A );



				if( count($um_results) > 0 ) {

					$records_meta = array();

					foreach ($um_results as $meta_data) {
	                    $key = preg_replace('/[^A-Za-z0-9\-_]/', '', $meta_data['meta_key']); //for formatting meta keys of custom keys
	                    $records_meta[$meta_data['user_id']][$key] = $meta_data['meta_value'];
	                }
				}

				$customer_ids = array();

				foreach( $users_results as $user ) {

					$user_id = (!empty( $user['ID'] )) ? $user['ID'] : 0;

					foreach( $user as $key => $value ) {
						if( is_array( $data_col_params['data_cols'] ) && !empty( $data_col_params['data_cols'] ) ) {
							if( array_search( $key, $data_col_params['data_cols'] ) === false ) {
	    				 		continue; //cond for checking col in col model
							}	
						}
						
						if( is_array( $data_col_params['data_cols_checkbox'] ) && !empty( $data_col_params['data_cols_checkbox'] ) && !empty( $data_col_params['data_cols_unchecked_template'] ) && is_array( $data_col_params['data_cols_unchecked_template'] ) ) {

	    					if( array_search( $key, $data_col_params['data_cols_checkbox'] ) !== false && $value == '' ) { //added for bad_value checkbox
        						$value = $data_col_params['data_cols_unchecked_template'][$key];
        					}
        				}

	    				$key_mod = 'users_'.strtolower(str_replace(' ', '_', $key));
	    				$items [$index][$key_mod] = ( $key != 'user_pass' ) ? $value : '';
	    			}


	    			if( !empty( $records_meta[$user_id] ) ) {

	    				foreach( $records_meta[$user_id] as $key => $value ) {

	    					if (array_search($key, $data_col_params['data_cols']) === false) continue; //cond for checking col in col model

	    					//Code for handling serialized data
        					if (array_search($key, $data_col_params['data_cols_serialized']) !== false) {
								$value = maybe_unserialize($value);
								if ( !empty( $value ) ) {
									$value = json_encode($value);
								}
								
	        				} else if( array_search($key, $data_col_params['data_cols_checkbox']) !== false && $value == '' ) { //added for bad_value checkbox
	        					$value = $data_col_params['data_cols_unchecked_template'][$key];
	        				} else if( is_array( $data_col_params['data_cols_timestamp'] ) && !empty( $data_col_params['data_cols_timestamp'] ) ) {
        						if( in_array( $key, $data_col_params['data_cols_timestamp'] ) && !empty( $value ) && is_numeric( $value ) ) {
        							$date = new DateTime("@".$value);
									$value = $date->format('Y-m-d H:i:s');
        						}
        					}

	        				$key_mod = 'usermeta_meta_key_'.$key.'_meta_value_'.$key;
	        				$items [$index][$key_mod] = (!empty($value)) ? $value : '';
	    				}

	    				
	    				if( ( defined('SMPRO') && true === SMPRO ) ) {
	    					$items [$index]['custom_last_order_date'] = '-';
		    				$items [$index]['custom_last_order_total'] = '-';
		    				$items [$index]['custom_orders_count'] = '-';
		    				$items [$index]['custom_orders_total'] = '-';	
	    				} else {
	    					$items [$index]['custom_last_order_date'] = '<a href="https://www.storeapps.org/product/smart-manager/" target = \'_blank\' style=\'color:#0073aa !important;\'> Pro only </a>';
		    				$items [$index]['custom_last_order_total'] = '<a href="https://www.storeapps.org/product/smart-manager/" target = \'_blank\' style=\'color:#0073aa !important;\'> Pro only </a>';
		    				$items [$index]['custom_orders_count'] =  '<a href="https://www.storeapps.org/product/smart-manager/" target = \'_blank\' style=\'color:#0073aa !important;\'> Pro only </a>';
		    				$items [$index]['custom_orders_total'] =  '<a href="https://www.storeapps.org/product/smart-manager/" target = \'_blank\' style=\'color:#0073aa !important;\'> Pro only </a>';
	    				}
	    				
	    				$cap_key = $wpdb->prefix.'capabilities';

	    				if( !empty($records_meta[$user_id][$cap_key]) ) {

			    			$caps = maybe_unserialize($records_meta[$user_id][$cap_key]);
			    			$role = array_keys($caps);
			    			$items [$index]['usermeta_role'] = ( !empty($role[0]) ) ? $role[0] : '';

			    			if( !empty( $items [$index]['usermeta_role'] ) && $items [$index]['usermeta_role'] == 'customer' ) {
			    				$customer_ids[$user_id] = $index;
			    			}
			    		}

	    			}

	    			$index++;
	    		}

	    		if( !empty( $customer_ids ) && ( defined('SMPRO') && true === SMPRO ) ) {

	    			$cust_ids = array_keys( $customer_ids );

		    		$customers_order_meta = $wpdb->get_results( $wpdb->prepare( "SELECT pm.meta_value as cust_id,
		    																		GROUP_CONCAT(distinct pm.post_ID 
									 				                                    ORDER BY p.post_date DESC SEPARATOR ',' ) AS all_id,
												                                    date_format(max(p.post_date), '%%Y-%%m-%%d, %%r') AS date,
												                           count(pm.post_id) as count
												                           FROM {$wpdb->prefix}postmeta AS pm
												                                    JOIN {$wpdb->prefix}posts AS p 
												                                    	ON (p.ID = pm.post_id
												                                    		AND p.post_type = 'shop_order'
												                                    		AND p.post_status IN ('wc-completed','wc-processing')
												                                    		AND pm.meta_key = %s)
												                           WHERE pm.meta_value IN (" . implode(",",$cust_ids) . ")                           
												                           GROUP BY pm.meta_value
												                           ORDER BY date", '_customer_user' ), 'ARRAY_A' );



		    		if( !empty( $customers_order_meta ) ) {

		    			$order_ids = array();
		    			$max_oid = array();

		    			foreach( $customers_order_meta as $customer_order_meta ) {

		    				$oids = ( !empty( $customer_order_meta['all_id'] ) ) ? explode( ",", $customer_order_meta['all_id'] ) : array('');

		    				foreach( $oids as $oid ) {
		    					$order_ids[$oid] = $customer_order_meta['cust_id'];
		    				}

		    				$max_oid[ $oids[0] ] = $customer_order_meta['cust_id'];

		    				$index = $customer_ids[$customer_order_meta['cust_id']];
		    				$items [$index]['custom_last_order_date'] = ( !empty( $customer_order_meta['date'] ) ) ? $customer_order_meta['date'] : '-';
		    				$items [$index]['custom_orders_count'] = ( !empty( $customer_order_meta['count'] ) ) ? $customer_order_meta['count'] : 0;
		    			}

		    			if( !empty( $order_ids ) ) {

		    				$cust_order_ids = array_keys( $order_ids );

		    				$query =  $wpdb->prepare( "SELECT post_id AS order_id,
	    																				meta_value AS order_total
		    																		FROM {$wpdb->prefix}postmeta
		    																		WHERE meta_key = %s
		    																			AND post_id IN ( ". implode( ",", $cust_order_ids ) ." )", '_order_total' );

		    				$customer_totals = $wpdb->get_results( $wpdb->prepare( "SELECT post_id AS order_id,
	    																				meta_value AS order_total
		    																		FROM {$wpdb->prefix}postmeta
		    																		WHERE meta_key = %s
		    																			AND post_id IN ( ". implode( ",", $cust_order_ids ) ." )", '_order_total' ), 'ARRAY_A' );

		    				if( !empty( $customer_totals ) ) {
		    					foreach( $customer_totals as $customer_total ) {
		    						$order_id = ( !empty( $customer_total['order_id'] ) ) ? $customer_total['order_id'] : '';
		    						$order_total = ( !empty( $customer_total['order_total'] ) ) ? $customer_total['order_total'] : 0;

		    						if( empty( $order_id ) ) {
		    							return;
		    						}

		    						if( !empty( $max_oid[ $order_id ] ) ) {
		    							$index = $customer_ids[$max_oid[ $order_id ]];
		    							$items [$index]['custom_last_order_total'] = $order_total;
		    						}

		    						if( !empty( $order_ids[ $order_id ] ) ) {
		    							$index = $customer_ids[$order_ids[ $order_id ]];
		    							$items [$index]['custom_orders_total'] += $order_total;
		    						}
		    					}
		    				}
		    			}
		    		}
	    		}
			}

			$data_model ['items'] = (!empty($items)) ? $items : '';
        	$data_model ['start'] = $start+$limit;
        	$data_model ['page'] = $current_page;
        	$data_model ['total_pages'] = $total_pages;
        	$data_model ['total_count'] = $users_total_count;

			return $data_model;

		}

		//function to avoid default inline update
		public function default_inline_update ($flag) {
			return false;
		}

		//function for modifying edited data before updating
		public function user_inline_update($edited_data, $params) {
			if (empty($edited_data)) return $edited_data;

			global $wpdb;


			$default_user_keys = array( 'ID', 'user_pass', 'user_login', 'user_nicename', 'user_url', 'user_email', 'display_name', 'nickname', 'first_name', 
										'last_name', 'description', 'rich_editing', 'syntax_highlighting', 'comment_shortcuts', 'admin_color', 'use_ssl',
										'user_registered', 'show_admin_bar_front', 'role', 'locale' );

			foreach ($edited_data as $id => $edited_row) {

				if( empty( $id ) ) {
					continue;
				}

				$default_insert_users = array();
				$insert_usermeta = array();

				foreach( $edited_row as $key => $value ) {
					$edited_value_exploded = explode("/", $key);
					
					if( empty( $edited_value_exploded ) ) continue;

					$update_table = $edited_value_exploded[0];
					$update_column = $edited_value_exploded[1];

					if ( sizeof( $edited_value_exploded ) <= 2) {
						if( ( ($update_table == 'users') || ($update_table == 'usermeta' && $update_column == 'role') ) ) {

							if( $update_table == 'usermeta' && $update_column == 'role' && (!empty( $params['data_cols_list_val'][$update_column][$value] )) ) {
								$default_insert_users [$update_column] = $value;
							} else if ( $update_column != 'role' ) {
								$default_insert_users [$update_column] = $value;
							}
						}
					} else if ( sizeof( $edited_value_exploded ) > 2) {
						$cond = explode("=",$edited_value_exploded[1]);

						$update_column_exploded = explode("=",$edited_value_exploded[2]);
						$update_column = $update_column_exploded[1];

						if( in_array( $update_column, $params['data_cols_timestamp'] ) ) {
    						$value = strtotime($value);
    					}

						if( $update_table == 'usermeta' && in_array( $update_column, $default_user_keys ) ) {

							if( $update_column == 'use_ssl' ) {
								$value = ( $value == 'yes' ) ? 0 : 1;
							}

							$default_insert_users [$update_column] = $value;
						} else if( $update_table == 'usermeta' && in_array( $update_column, $default_user_keys ) === false ) {
							$insert_usermeta [$update_column] = $value;
						}
					}
				}

				if( !empty( $default_insert_users ) ) {
					$default_insert_users['ID'] = (int) $id;
					$id = wp_update_user( $default_insert_users );
				}

				if( !is_wp_error( $id ) ) {
					
					if( !empty( $insert_usermeta ) ) {

						foreach( $insert_usermeta as $key => $value ) {
							update_user_meta( $id, $key, $value );
						}
					}
				}

			}

		}

		public static function users_batch_update_entire_store_ids_from( $from, $params ) {
			$from = str_replace('posts', 'users', $from);
			return $from;
		}


		public static function users_batch_update_entire_store_ids_where( $where, $params ) {
			global $wpdb;

			$search_cond_pos = strpos( $where, "AND {$wpdb->base_prefix}sm_advanced_search_temp" );
			if( !empty( $search_cond_pos ) ) {
				$where = 'WHERE '. substr( $where, ($search_cond_pos + 3) );
			} else {
				$where = 'WHERE 1=1 ';
			}

			return $where;
		}


		public static function users_batch_update_entire_store_ids_query( $query ) {

			global $wpdb;

			$query = $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE 1=%d", 1 );
			return $query;
		}

		public static function users_batch_update_prev_value($prev_val = '', $args) {

			global $wpdb;

			if( $args['table_nm'] == 'users' ) {
				$prev_val = $wpdb->get_var( $wpdb->prepare( "SELECT ". $args['col_nm'] ." FROM $wpdb->users WHERE ID = %d", $args['id'] ) );
			} else if( $args['table_nm'] == 'usermeta' ) {
				$prev_val = get_user_meta($args['id'], $args['col_nm'], true);
			}

			return $prev_val;
		}

		public static function users_default_batch_update_db_updates($flag) {
			return false;
		}

		public static function users_post_batch_update_db_updates($update_flag = false, $args) {

			$default_user_keys = array( 'ID', 'user_pass', 'user_login', 'user_nicename', 'user_url', 'user_email', 'display_name', 'nickname', 'first_name', 
										'last_name', 'description', 'rich_editing', 'syntax_highlighting', 'comment_shortcuts', 'admin_color', 'use_ssl',
										'user_registered', 'show_admin_bar_front', 'role', 'locale' );

			$id = ( !empty( $args['id'] ) ) ? $args['id'] : 0;
			$col_nm = ( !empty( $args['col_nm'] ) ) ? $args['col_nm'] : ''; 
			$value = ( !empty( $args['value'] ) ) ? $args['value'] : '';

			if( !empty( $col_nm ) && in_array( $col_nm, $default_user_keys ) ) {
				$id = wp_update_user( array( 'ID' => $id, $args['col_nm'] => $value ) );

				if( !is_wp_error( $id ) ) {
					$update_flag = true;
				}

			} else if ( !empty( $args['table_nm'] ) && $args['table_nm'] == 'usermeta' ) {
				update_user_meta( $id, $col_nm, $value );
				$update_flag = true;
			}

			return $update_flag;
		}

		/**
		 * Function to provide deleter for the user
		 *
		 * @param  mixed $deleter The deleter.
		 * @param  array $args    Additional arguments.
		 * @return mixed $deleter The modified deleter
		 */
		public function user_deleter( $deleter = null, $args = array() ) {

			if ( ! empty( $args['source']->req_params['active_module'] ) && 'user' === $args['source']->req_params['active_module'] ) {
				global $wpdb;

				$deleter = array(
					'callback' => 'wp_delete_user'
				);

				$delete_ids = (!empty($args['source']->req_params['ids'])) ? json_decode(stripslashes($args['source']->req_params['ids']), true) : array();

				$ignore_user_ids   = self::get_admin_user_ids();
				$ignore_user_ids[] = get_current_user_id();
				$ignore_user_ids   = array_unique( $ignore_user_ids );

				$valid_delete_ids = array();
				if ( ! empty( $ignore_user_ids ) ) {
					$valid_delete_ids = array_diff( $delete_ids, $ignore_user_ids );
				}

				if ( ! empty( $valid_delete_ids ) ) {
					$deleter['delete_ids'] = $valid_delete_ids;
				}

			}

			return $deleter;
		}

		/**
		 * Function to get user ids of administrator of the website
		 *
		 * @return array $admin_user_ids The found ids
		 */
		public static function get_admin_user_ids() {

			$args = array(
				'role'    => 'administrator',
				'orderby' => 'ID',
				'order'   => 'ASC',
				'fields'  => 'ID',
			);
			
			$admin_user_ids = get_users( $args );

			return $admin_user_ids;
		}


		/**
		 * Handle user ids to be deleted
		 * 
		 * @param  array $ids  The user ids.
		 * @param  array $args Additional arguments.
		 * @return array $ids
		 */
		public function users_delete_record_ids( $ids = array(), $args = array() ) {

			if ( !empty( $ids ) ) {
				$ignore_user_ids   = self::get_admin_user_ids();
				$ignore_user_ids[] = get_current_user_id();
				$ignore_user_ids   = array_unique( $ignore_user_ids );
				$ids               = array_diff( $ids, $ignore_user_ids );
			}

			return $ids;
		}

		/**
		 * Function to handle delete of a single record
		 *
		 * @param  integer $deleting_id The ID of the record to be deleted.
		 * @return boolean
		 */
		public static function process_delete_record( $params ) {

			$deleting_id = ( !empty( $params['id'] ) ) ? $params['id'] : '';

			do_action('sm_beta_pre_process_delete_users', array( 'deleting_id' => $deleting_id, 'source' => __CLASS__ ) );

			//code for processing logic for duplicate records
			if( empty( $deleting_id ) ) {
				return false;
			}

			$result = wp_delete_user( $deleting_id );

			do_action( 'sm_beta_post_process_delete_users', array( 'deleting_id' => $deleting_id, 'source' => __CLASS__ ) );
			
			if( empty( $result ) ) {
				return false;
			} else {
				return true;
			}

		}

	}
}
