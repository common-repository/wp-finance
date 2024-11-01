<?php
class wpf_help {

  private $identifier = "help";
  private $title = "Help";
  private $order = 90;
  private $hook, $domain;

  // Default methods -----------------------------------------------------------

  public function load_meta_boxes() {
    add_meta_box('about', __('About WP Finance', $this->domain), array(&$this, 'about_metabox'), $this->hook, 'normal', 'core');
    add_meta_box('shortcode', __('Shortcode', $this->domain), array(&$this, 'shortcode_metabox'), $this->hook, 'normal', 'core');
  }

  public function getIdentifier() {
    return $this->identifier;
  }

  public function getTitle()
  {
    return $this->title;
  }

  public function getOrder()
  {
    return $this->order;
  }

  public function setHook($hook)
  {
    $this->hook = $hook."-".$this->identifier;
  }

  public function setDomain($domain)
  {
    $this->domain = $domain;
    $this->title = __("Help", $this->domain);
  }

  /* local implementation of do_meta_boxes */
  function do_meta_boxes($page, $context, $object)
  { 
    global $wp_meta_boxes; 
    static $already_sorted = false; 
    $output = '';
 
    $hidden = get_hidden_meta_boxes($page); 

    $output .= "<div id='$context-sortables' class='meta-box-sortables'>\n"; 

    $i = 0; 
    do { 
      // Grab the ones the user has manually sorted. Pull them out of their previous context/priority and into the one the user chose 
      if ( !$already_sorted && $sorted = get_user_option( "meta-box-order_$page" ) ) { 
        foreach ( $sorted as $box_context => $ids ) 
          foreach ( explode(',', $ids) as $id ) 
            if ( $id ) 
              add_meta_box( $id, null, null, $page, $box_context, 'sorted' ); 
      } 

      $already_sorted = true; 
      if ( !isset($wp_meta_boxes) || !isset($wp_meta_boxes[$page]) || !isset($wp_meta_boxes[$page][$context]) ) 
        break; 

      foreach ( array('high', 'sorted', 'core', 'default', 'low') as $priority ) { 
        if ( isset($wp_meta_boxes[$page][$context][$priority]) ) { 
          foreach ( (array) $wp_meta_boxes[$page][$context][$priority] as $box ) { 
            if ( false == $box || ! $box['title'] ) 
              continue; 

            $i++;
            $style = ''; 
            if ( in_array($box['id'], $hidden) ) 
              $style = 'style="display:none;"'; 

            $output .= '<div id="' . $box['id'] . '" class="postbox ' . postbox_classes($box['id'], $page) . '" ' . $style . '>' . "\n"; 
            $output .= '<div class="handlediv" title="' . __('Click to toggle') . '"><br /></div>'; 
            $output .= "<h3 class='hndle'><span>{$box['title']}</span></h3>\n"; 
            $output .= '<div class="inside">' . "\n"; 
            $output .= call_user_func($box['callback'], $object, $box); 
            $output .= "</div>\n"; 
            $output .= "</div>\n"; 
          } 
        } 
      } 
    } while(0); 

    $output .= "</div>"; 
    
    return $output;
  }

  // Views ---------------------------------------------------------------------

  public function viewIndex() {
    $output .= '<div id="metaboxes-general" class="metabox-holder" style="padding-top: 0px;">';
    $output .= $this->do_meta_boxes($this->hook, 'normal', $data);
    $output .= '</div>';
    return $output;
  }

  // Metaboxes -----------------------------------------------------------------

  public function about_metabox ()
  {
    $output .= '<div style="line-height: 1.7;">';
    $output .= '<b>'.__('Plugin Name', $this->domain).':</b> WP Finance<br/>';
    $output .= '<b>'.__('Version', $this->domain).':</b> '.get_option('wpf_version').'<br/>';
    $output .= '<b>'.__('Website', $this->domain).':</b> <a href="http://mindomobile.com" target="_new">MindoMobile.com</a><br/>';
    $output .= '<b>'.__('Support email', $this->domain).':</b> wpfinance@mindomobile.com<br/>';
    $output .= '<b>'.__('Support forum', $this->domain).':</b> '.'<a href="http://wordpress.org/support/plugin/wp-finance" target="_new">wordpress.org forum</a><br/>';
    $output .= '<b>'.__('Donate', $this->domain).':</b> '.'<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=F46P9W3FSA9GN" target="_new">PayPal</a>, '.__('every little counts!', $this->domain).'<br/>';
    $output .= '<iframe src="http://www.facebook.com/plugins/like.php?href=http%3A%2F%2Fwww.facebook.com%2FMindoMobileSolutions&amp;width=400&amp;colorscheme=light&amp;show_faces=false&amp;border_color&amp;stream=false&amp;header=false&amp;height=25" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:400px; height:25px; padding-top: 4px;" allowTransparency="true"></iframe>';
    $output .= "</div>";
    
    return $output;      
  }

  public function shortcode_metabox()
  {
    $output .= '<p>';
    $output .= __('Financial report can be embedded into blog post or custom pages using shortcode to provide read-only financial information for your blog readers.',
      $this->domain);
    $output .= '</p>';
    $output .= '<p>';
    $output .= '<b>'.__('Shortcode', $this->domain).':</b> [wpfinance]<br/><br/>';
    $output .= '<b>'.__('Shortcode parameters', $this->domain).':</b><br>';
    $output .= '<i>from="yyyy-mm-dd"</i> '.__("start date for report", $this->domain).'<br/>'.
               '<i>to="yyyy-mm-dd"</i> '.__("end date for report", $this->domain).'<br/>'.
               '<i>totals="true"</i> '.__("show grand total table", $this->domain).'<br/><br/>';
    $output .= __('For example', $this->domain).' [wpfinance from="2011-01-01" to="2012-01-01" totals="true"]';
    $output .= "</p>";

    return $output;
  }
}
?>