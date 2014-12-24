<?php
/**
 * Implements acf command.
 */
class YAACF_Command extends WP_CLI_Command {

    function assign_page_to_field_group($fgrp_id, $page_id) {
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
        $meta_rule_id = $this->assign_page_to_field_group($post_id, $assoc_array['page_id']); 
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
      $meta_values = get_post_meta($fgrp_id, 'rule');
      $page_id = $meta_values[0][value];
      error_log("_dbg meta_values: " . json_encode($meta_values));
      $postmeta_id = add_post_meta($page_id, '_' . $f_name, $f_meta_key);
      error_log("_dbg ref id: " . $postmeta_id);
      $postmeta_id = add_post_meta($page_id, $f_name, $f_value);
      error_log("_dbg value id: " . $postmeta_id);
    }

    function handle_field_create($assoc_args) {

      //$meta_values = get_post_meta( $post_id, $key, $single );
      $post_id = $assoc_args['field_group_id'];
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

      $field_name = strtolower($assoc_args['field_label']);
      $field_name = str_replace(' ', '-', $field_name);
      $field_arr['name'] = $assoc_args['field_name'];

      //error_log("_dbg key: " . $meta_value['key']);
      //wp post meta delete 116 field_tim
      $postmeta_id = add_post_meta($post_id, $meta_key, serialize($field_arr));
      if($postmeta_id) {
        $this->assign_value_to_field($post_meta_id, $meta_key, 'section_1_header', 'test value');
        WP_CLI::success('acf field created with postmeta id: ' . $postmeta_id);
      } else {
        WP_CLI::error('failure creating acf field');
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
     *     wp yaacf field create --field_type='text' --field_label='Section 1 Header' --field_order_no=4 --field_group_id=118
     *
     * @synopsis <command> [--field_type=<field_type>] [--field_label=<field_label>] [--field_order_no=<field_order_no>] [--field_group_id=<field_group_id>] [--field_value=<field_value>]
     */
    function field( $args, $assoc_args ) {
        $cmd = $args[0];

        switch($cmd) {
          case 'create':
            $this->handle_field_create($assoc_args);
          break;
          default:
            WP_CLI::error('Unknown command: ' . $cmd);
          break;
        } 

    }

}

WP_CLI::add_command('yaacf', 'YAACF_Command');
