<?php

global $wpdb;

function get_helpful_page_links($post_id) {
  global $wpdb;
  $hpl = array();
  $hpl_meta_id = -1;

  $sql = $wpdb->prepare("SELECT * FROM `wp_postmeta` WHERE `meta_key` = 'helpful_page_links' and `post_id` = %d", $post_id);
  $acf_fields = $wpdb->get_results($sql);
  foreach($acf_fields as $acf_field) {
    $mvalue = $acf_field->meta_value;
    $hpl = unserialize($mvalue);
    if(!$hpl) {
      $hpl = array();
    } 
    $hpl_meta_id = $acf_field->meta_id;
  }

  return array($hpl, $hpl_meta_id);
}


  //$sql = "SELECT * FROM `wp_postmeta` WHERE `meta_key` = 'related_white_papers' and post_id = 373";
  $sql = "SELECT * FROM `wp_postmeta` WHERE `meta_key` = 'related_white_papers'";
  $acf_fields = $wpdb->get_results($sql);
  foreach($acf_fields as $acf_field) {
    $mvalue = $acf_field->meta_value;
    if(!$mvalue) {
      continue;
    }
    error_log("_dbg mvalue: $mvalue");
    $rwp = unserialize($mvalue);
    error_log("_dbg rwp: " . var_dump($rwp));
    $page_links = array(); 
    if(in_array('Cloud-Solutions-Made-Simple.pdf', $rwp)) {
      $page_links[] = 350;
      $key = array_search('Cloud-Solutions-Made-Simple.pdf', $rwp);
      unset($rwp[$key]);
      error_log("_dbg unsetting key: $key");
    }

    if(in_array('Why-the-Cloud.pdf', $rwp)) {
      $page_links[] = 177;
      $key = array_search('Why-the-Cloud.pdf', $rwp);
      unset($rwp[$key]);
      error_log("_dbg unsetting key: $key");
    }

    if($page_links) {
      $rwp_str = '';
      if($rwp) {
        $rwp_str = serialize($rwp);
      }
      $sql = $wpdb->prepare("UPDATE `wp_postmeta` SET `meta_value` = %s WHERE `meta_id` = %d", $rwp_str, $acf_field->meta_id);
      error_log("_dbg sql: " . $sql);
      $wpdb->query($sql);
    }

    $new_pls = array();
    list($pls, $hpl_meta_id) = get_helpful_page_links($acf_field->post_id);
      error_log("_dbg2 no hpl_meta_id found");
      error_log("_dbg2 add manually to post id: $acf_field->post_id with values: " . json_encode($page_links));
    error_log("_dbg pls: " . json_encode($pls));
    foreach($page_links as $page_link) {
      if(!in_array($page_link, $pls)) {
        $new_pls[] = $page_link;
      }
    }
    error_log("_dbg new pls: " . json_encode($new_pls));

    if($new_pls) {
      $final_pls = array_merge($pls, $new_pls);
      $final_pls_str = serialize($final_pls);
      
      if($hpl_meta_id<0) {
        sleep(1);
        $new_sql = "INSERT INTO `wp_postmeta` (`post_id`, `meta_key`, `meta_value`) VALUES (%d, %s, %s)";
        $n1 = $wpdb->prepare($new_sql, $acf_field->post_id, '_helpful_page_links', 'field_' . time());
        $wpdb->query($n1);
        error_log("_dbg n1: $n1");
        
        $n2 = $wpdb->prepare($new_sql, $acf_field->post_id, 'helpful_page_links', $final_pls_str);
        error_log("_dbg n2: $n2");
        $wpdb->query($n2);
      } else {
        $sql = $wpdb->prepare("UPDATE `wp_postmeta` SET `meta_value` = %s WHERE `meta_id` = %d", $final_pls_str, $hpl_meta_id);
        error_log("_dbg sql: " . $sql);
        $wpdb->query($sql);
      } 
    //exit(1);
  }
 } 
  
?>
