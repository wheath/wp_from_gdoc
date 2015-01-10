<?php
/**
 * Implements acf command.
 */
class YAACF_Command extends WP_CLI_Command {
    var $testrun = FALSE;
    var $debugacf = FALSE;

    function get_field_name($field_label) {
      $field_name = strtolower($field_label);
      $field_name = str_replace(' ', '_', $field_name);
      return $field_name;
    }

    function assign_page_to_field_group($fgrp_id, $page_id) {
      error_log("_dbg in assign_page_to_field_group");
      error_log("_dbg  page_id: " . $page_id);
      $rule_json = '{"param":"page","operator":"==","value":"111","order_no":0,"group_no":0}';
      $rule_arr = json_decode($rule_json, true);
      $rule_arr['value'] = $page_id;
      error_log("_dbg rule_arr: " . json_encode($rule_arr));
      $rule_meta_id = add_post_meta($fgrp_id, 'rule', serialize($rule_arr));

      return $rule_meta_id; 
    }

    function handle_field_group_create($assoc_args) {
      $post_title = $assoc_args['post_title'];
      if(isset($assoc_args['post_status'])) {
        error_log("_dbg post status isset");
        $post_status = $assoc_args['post_status'];
      } else {
        error_log("_dbg post status is not isset");
        $post_status = 'publish';
      }

      $post_name = strtolower($post_title);
      $post_name = str_replace(' ', '-', $post_name);
      $post_name = 'acf_' . $post_name;
      //error_log("post_name: " . $post_name);
      $args = array(
	      'post_type' => 'acf',
	      'post_title' => $post_title,
	      'post_status' => $post_status,
	      'post_author' => false,
	      'post_parent' => 0,
	      'post_name' => $post_name,
	      'post_date' => current_time( 'mysql' ),
	      'post_content' => ''
      );
      //error_log("args: " . json_encode($args));

      $post_id = wp_insert_post( $args, true );
      if(is_wp_error($post_id)) {
        WP_CLI::warning($post_id);
      } else {
        $meta_rule_id = $this->assign_page_to_field_group($post_id, $assoc_args['page_id']); 
        if($meta_rule_id) {
          WP_CLI::success('acf field group created with post id: ' . $post_id);
        } else {
          WP_CLI::error('error creating acf field group');
        }
      }
    }

    /**
     * Creates an advanced custom field field group
     * 
     * ## OPTIONS
     * 
     * <command>
     * : The name of the command
     * 
     * ## EXAMPLES
     * 
     *     wp yaacf field_group create --post_title='field group name' --post_status=future
     *
     * @synopsis <command> [--post_title=<post_title>] [--post_status=<post_status>] [--page_id=<page_id>] 
     */
    function field_group( $args, $assoc_args ) {
        $cmd = $args[0];

        switch($cmd) {
          case 'create':
            $this->handle_field_group_create($assoc_args);
          break;
          default:
            WP_CLI::error('Unknown field_group command: ' . $cmd);
          break;
        } 

    }

    function assign_value_to_field($fgrp_id, $f_meta_key, $f_name, $f_value) {
      $page_id = $this->find_acf_group_page_id($fgrp_id);
      $postmeta_id = add_post_meta($page_id, '_' . $f_name, $f_meta_key);
      error_log("_dbg ref id: " . $postmeta_id);
      $postmeta_id = add_post_meta($page_id, $f_name, $f_value);
      error_log("_dbg value id: " . $postmeta_id);
    }


    function handle_field_create($assoc_args) {

      //$meta_values = get_post_meta( $post_id, $key, $single );
      $post_id = $assoc_args['field_group_id'];
      $page_id = $this->find_acf_group_page_id($post_id);
      if(!$page_id) {
        echo "unable to locate page id for acf group id " . $post_id . " exiting...";
      } else {
        echo "_dbg page_id found for acf group id: " . $post_id . " is " . $page_id;
      }
      /*
      $meta_values = get_post_meta(114, 'field_54935e382ec8d');
      $meta_value = $meta_values[0];
      error_log("_dbg meta_value: " . json_encode($meta_value));
      */
      /* select * from wp_postmeta where post_id = 114\G; */
      $json_field = '{"key":"field_54935e382ec8d","label":"Section 1 Header","name":"section_1_header","type":"text","instructions":"","required":"1","default_value":"","placeholder":"","prepend":"","append":"","formatting":"html","maxlength":"","conditional_logic":{"status":"0","rules":[{"field":"null","operator":"=="}],"allorany":"all"},"order_no":0}';
      $field_arr = json_decode($json_field, true);
      $meta_key = 'field_' . time();
      //error_log("_dbg key: " . $meta_value['key']);
      $field_arr['key'] = $meta_key; 
      $field_arr['label'] = $assoc_args['field_label'];
      $field_arr['type'] = $assoc_args['field_type'];
      $field_arr['order_no'] = $assoc_args['field_order_no'];

      $field_arr['name'] = $this->get_field_name($assoc_args['field_label']);

      //error_log("_dbg key: " . $meta_value['key']);
      //wp post meta delete 116 field_tim
      $postmeta_id = add_post_meta($post_id, $meta_key, serialize($field_arr));
      if($postmeta_id) {
        $this->assign_value_to_field($post_id, $meta_key, $field_arr['name'], $assoc_args['field_value']);
        WP_CLI::success('acf field created with postmeta id: ' . $postmeta_id);
      } else {
        WP_CLI::error('failure creating acf field');
      }

    }

    function handle_field_create_json_file($assoc_args) {
      $fgrp_id = $assoc_args['field_group_id'];
      $page_id = $this->find_acf_group_page_id($fgrp_id);
      if(!$page_id) {
        echo "unable to locate page id for acf group id " . $post_id . " exiting...\n";
        exit(1);
      } else {
        echo "_dbg page_id found for acf group id: " . $post_id . " is " . $page_id;
      }
      $json_file_name = $assoc_args['field_json_file'];
      $json_str = file_get_contents($json_file_name);
      $wp_fields = json_decode($json_str, true);
      error_log("_dbg fields for page: " . $wp_fields['page_name']);
      $order_cnt = 0;
      foreach ($wp_fields['sections'] as $section) {
	foreach ($section as $field_key => $field_value) {
	  error_log("_dbg field key: " . $field_key);
	  error_log("_dbg field value: " . $field_value);
          $assoc_args['field_label'] = $field_key;
          $assoc_args['field_order_no'] = $order_cnt++;
          $assoc_args['field_value'] = $field_value;
          $assoc_args['field_type'] = 'text';
          $pos = strpos($field_key, 'Content');
          if ($pos !== false) {
            $assoc_args['field_type'] = 'textarea';
          }
          //FIXME: needed because meta_key is using time withe field + time()
          // won't be unique without waiting one second
          // would be better to use an auto incrementing key
          sleep(1);
          $this->handle_field_create($assoc_args);
          #if($order_cnt == 3) {
            #exit(1);
          #}
        
	}
      }
    }

    function update_post_id($from_post_id, $to_post_id, $meta_key) {
      global $wpdb;
      $sql = $wpdb->prepare("UPDATE `wp_postmeta` SET `post_id` = %s WHERE `meta_key` = %s AND `post_id` = %d", $to_post_id, $meta_key, $from_post_id);

      if($this->testrun) {
        error_log("_dbg  update_post_id sql: " . $sql);
      } else {
        $wpdb->query($sql);
      }
    }

    function update_mk($post_id, $from_mk, $to_mk) {
      global $wpdb;
      $sql = $wpdb->prepare("UPDATE `wp_postmeta` SET `meta_key` = %s WHERE `meta_key` = %s AND `post_id` = %d", $to_mk, $from_mk, $post_id);

      if($this->testrun) {
        error_log("_dbg update_mk sql: " . $sql);
      } else {
        $wpdb->query($sql);
      }
    }

    function find_acf_group_page_id($acf_group_id) {
      $meta_values = get_post_meta($acf_group_id);
      $acf_group_page_id = 0;
      foreach ($meta_values as $meta_key => $meta_value) {
        if ($meta_key == 'rule') {
          error_log("_dbg meta_value: " . json_encode($meta_value));
          $rule = unserialize($meta_value[0]);
          if(!is_array($rule)) {
            $rule = unserialize($rule);
          }
          if($rule['param'] === 'page') {
            error_log("_dbg rule value: " . $rule['value']);
            $acf_group_page_id = $rule['value'];
          }
        }
      }

      return $acf_group_page_id;
    }

    function find_field_meta_key($acf_group_id, $field_label) {
      $meta_values = get_post_meta($acf_group_id);
      $found_meta_key = '';
      foreach ($meta_values as $meta_key => $meta_value) {
        if (preg_match("/^field_/", $meta_key)) {
          error_log("_dbg field_ found");
          $f = unserialize($meta_value[0]);
          if ($f['label'] === $field_label) {
            $found_meta_key = $meta_key;
            return $found_meta_key;   
          }
        }
      }

      return $found_meta_key;
    }

    function get_max_order_num($acf_group_id) {
      if($this->testrun) {
        error_log("_dbg in get_max_order_num");
      }
      $meta_values = get_post_meta($acf_group_id);
      $max_order_num = 0;
      foreach ($meta_values as $meta_key => $meta_value) {
        if (preg_match("/^field_/", $meta_key)) {
          $f = unserialize($meta_value[0]);
	  if($this->testrun) {
	    error_log("_dbg field order number: " . $f['order_no']);
	  }
          if($f['order_no'] > $max_order_num) {
            $max_order_num = $f['order_no'];
          }
        }
      }
      return $max_order_num;
    }

    function get_max_section_num($acf_group_id) {
      $meta_values = get_post_meta($acf_group_id);
      $max_section_num = 0;
      foreach ($meta_values as $meta_key => $meta_value) {
        if (preg_match("/^field_/", $meta_key)) {
          $f = unserialize($meta_value[0]);
          if (preg_match("/Section (\\d)+/", $f['label'], $matches)) {
            if($max_section_num < $matches[1]) {
              $max_section_num = $matches[1];
            }
          }
        }
      }
      return $max_section_num;
    }

    function move_field_value($from_page_id, $to_page_id, $field_label) {
      $field_name = $this->get_field_name($field_label);
      $this->update_post_id($from_page_id, $to_page_id, $field_name); 
      $this->update_post_id($from_page_id, $to_page_id, '_' . $field_name); 
    }

    function handle_field_move($assoc_args) {
      $success=FALSE;

      $from_post_id = $assoc_args['field_from_group_id'];
      $to_post_id = $assoc_args['field_to_group_id'];
      $field_label = $assoc_args['field_label'];
      

      $f_mk = $this->find_field_meta_key($from_post_id, $field_label);
      if ($f_mk) {
	$success = $this->update_post_id($from_post_id, $to_post_id, $f_mk); 
	$from_page_id = $this->find_acf_group_page_id($from_post_id); 
	$to_page_id = $this->find_acf_group_page_id($to_post_id); 
	if($from_page_id && $to_page_id && $from_page_id !== $to_page_id) {
	  $this->move_field_value($from_page_id, $to_page_id, $field_label);
	}
      }

      if ($success) {
        WP_CLI::success('acf field moved');
      } else {
        WP_CLI::error('failure moving acf field');
      }
    }

    function append_field_value($from_page_id, $to_page_id, $from_mk, $to_mk) {
      $link_from_mk = '_' . $from_mk;
      $link_to_mk = '_' . $to_mk;

      if($this->debugacf || $this->testrun) {
        error_log("_dbg in append_field_value");
      }
      $meta_values = get_post_meta($from_page_id);
      foreach ($meta_values as $meta_key => $meta_value) {
        if($this->debugacf || $this->testrun) {
          error_log("_dbg processing field with mk: " . $meta_key);
        }
        if($from_mk == $meta_key) { 
	  $this->update_mk($from_page_id, $from_mk, $to_mk); 
	  $this->update_post_id($from_page_id, $to_page_id, $to_mk); 
        } 
        if($link_from_mk == $meta_key) {
          if($this->debugacf || $this->testrun) {
            error_log("_dbg field _mk found " . $meta_key);
          }
	  $this->update_mk($from_page_id, $link_from_mk, $link_to_mk); 
	  $this->update_post_id($from_page_id, $to_page_id, $link_to_mk); 
        }
      }
    }

    function append_fields($from_post_id, $to_post_id, $max_sec_num, $max_ord_num) {
      if($this->debugacf) {
        error_log("_dbg in append_fields");
        error_log("_dbg max_sec_num: " . $max_sec_num);
      }
      $meta_values = get_post_meta($from_post_id);
      if($this->testrun) {
        error_log("_dbg # of meta_values found: " . count($meta_values));
      }
      $cur_from_sec_num = 0; 
      $cur_to_sec_num = 0;

      $from_page_id = $this->find_acf_group_page_id($from_post_id);
      $to_page_id = $this->find_acf_group_page_id($to_post_id);
      if($this->testrun) {
        error_log("_dbg from page id: " . $from_page_id);
        error_log("_dbg to page id: " . $to_page_id);
      }

      if($max_sec_num) {
        $cur_to_sec_num = $max_sec_num + 1;
      }

      foreach ($meta_values as $meta_key => $meta_value) {
        if (preg_match("/^field_/", $meta_key)) {
          if($this->testrun) {
            error_log("_dbg appending field with mk: " . $meta_key); 
          }
          $f = unserialize($meta_value[0]);
          if (preg_match("/Section (\\d)+/", $f['label'], $matches)) {
            if($this->testrun) {
              error_log("_dbg field with Section found: " . $f['label']); 
              error_log("_dbg cur_to_sec_num: " . $cur_to_sec_num);
            }
            if($cur_to_sec_num) {
	      if(!$cur_from_sec_num) {
		$cur_from_sec_num = $matches[1];
	      }

	      if($matches[1] > $cur_from_sec_num) {
		$cur_to_sec_num++;  
                $cur_from_sec_num = $matches[1];
	      }

              $old_name = $this->get_field_name($f['label']);
              $f['label'] = str_replace($matches[1], $cur_to_sec_num, $f['label']);
              $f['name'] = $this->get_field_name($f['label']);
              $max_ord_num++;
              $f['order_no'] = $max_ord_num;
              if($this->testrun) {
                error_log("_dbg going to update field with: " . json_encode($f));
              } else {
                update_post_meta($from_post_id, $meta_key, serialize($f));
              }
	      $this->update_post_id($from_post_id, $to_post_id, $meta_key); 
              $this->append_field_value($from_page_id, $to_page_id, $old_name, $f['name']); 
            }
          }
        }
      }
      return TRUE;    
    }

    function handle_field_appendall($assoc_args) {
      $success=FALSE;
      error_log("_dbg in handle_field_appendall");

      $from_post_id = $assoc_args['field_from_group_id'];
      $to_post_id = $assoc_args['field_to_group_id'];

      $max_order_num = $this->get_max_order_num($from_post_id); 
      $max_section_num = $this->get_max_section_num($to_post_id); 
      if($this->testrun) {
        error_log("_dbg max order num: " . $max_order_num);
        error_log("_dbg max section num: " . $max_section_num);
      }
      
      $success = $this->append_fields($from_post_id, $to_post_id, 
                                             $max_section_num, $max_order_num); 

      if ($success) {
        WP_CLI::success('acf fields appended');
      } else {
        WP_CLI::error('failure appending acf fields');
      }
    }

    /**
     * Creates an advanced custom field field group
     * 
     * ## OPTIONS
     * 
     * <command>
     * : The name of the command
     * 
     * ## EXAMPLES
     * 
     *     wp yaacf field create --field_type='text' --field_label='Section 1 Header' --field_order_no=4 --field_group_id=118 --testrun
     *
     * @synopsis <command> [--field_type=<field_type>] [--field_label=<field_label>] [--field_order_no=<field_order_no>] [--field_group_id=<field_group_id>] [--field_value=<field_value>] [--field_from_group_id=<field_from_group_id>] [--field_to_group_id=<field_to_group_id>] [--testrun] [--field_json_file=<field_json_file>]
     */
    function field( $args, $assoc_args ) {
        $cmd = $args[0];

        if($assoc_args['testrun']) {
          $this->testrun = TRUE;
        }
        if($assoc_args['debug']) {
          $this->debugacf = TRUE;
        }

        switch($cmd) {
          case 'create':
            if($assoc_args['field_json_file']) {
              $this->handle_field_create_json_file($assoc_args); 
            } else {
              $this->handle_field_create($assoc_args);
            }
          break;
          case 'move':
            $this->handle_field_move($assoc_args);
          break;
          case 'appendall':
            $this->handle_field_appendall($assoc_args);
          break;
          default:
            WP_CLI::error('Unknown command: ' . $cmd);
          break;
        } 

    }

}

WP_CLI::add_command('yaacf', 'YAACF_Command');
