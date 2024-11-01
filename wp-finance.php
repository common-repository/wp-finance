<?php
/*
Plugin Name: WP Finance
Plugin URI: http://mindomobile.com
Description: This plugin allows you to manage your financial records using Wordpress.
Version: 1.3.6
Author: MindoMobile
License: GPL2
*/

if (!function_exists ('is_admin')) {
   header('Status: 403 Forbidden');
   header('HTTP/1.1 403 Forbidden');
   exit();
}

class WPFinancePlugin
{
   var $name = "WP Finance";
   var $ver = "1.3.6";
   var $domain = "wpfinance";
   var $c_path = "components/";        // components dir
   var $components = array();
   var $hook;
   var $page, $view, $action;
   var $wp_user;

   function WPFinancePlugin ()
   {
      $this->loadComponents();
      add_action('admin_menu', array(&$this, 'admin_menu'));
      add_action('admin_init', array(&$this, 'admin_install'));

      add_shortcode('wpfinance', array(&$this, 'getShortcode'));

      $menu = explode("/", $_GET['menu']);
      $this->page = (preg_match("/^([a-z\-]+)+$/sim", $menu[0]))?$menu[0]:'default';
      $this->view = (preg_match("/^([a-z\-]+)+$/sim", $menu[1]))?$menu[1]:'index';
      $this->action = (preg_match("/^([a-z\-]+)+$/sim", $menu[2]))?$menu[2]:'';

      load_plugin_textdomain($this->domain, false, basename(dirname( __FILE__ )).'/languages');
   }

   function admin_install()
   {
      global $wpdb;

      // Check if installed
      if (get_option('wpf_version') < $this->ver) {
         $wp_finance_table = $wpdb->get_col("SHOW COLUMNS FROM ".$wpdb->prefix."finance");
         
         /* retrieve and format sql */
         $sql_file = file_get_contents('wpfinance.sql', true);
         $sql_file = str_replace("[finance]", $wpdb->prefix."finance", $sql_file);
         $sql_file = str_replace('[finance_currencies]', $wpdb->prefix.'finance_currencies', $sql_file);
         $sql_bits = explode(";\n", $sql_file);

         if (!$wp_finance_table) {
            // No tables found
            foreach($sql_bits as $bit) $wpdb->query($bit);
         } else {
            // Update is required
            $data = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."finance WHERE 1", ARRAY_A);
            foreach($sql_bits as $bit) $wpdb->query($bit);

            // Insert values
            foreach ($data as $item) {
               $wpdb->insert($wpdb->prefix.'finance',
                  array( 
                     'finance_id' => $item['finance_id'], 
                     'line' => $item['line'],
                     'description' => $item['description'],
                     'date' => $item['date'],
                     'amount' => $item['amount'],
                     'status' => $item['status'],
                     'invoice' => $item['invoice'],
                     'currency' => $item['currency'],
                     'user_id' => $item['user_id']
                  ), 
                  array('%d','%s','%s','%s','%f','%d','%s','%s','%d')
               );
            }
         }

         update_option('wpf_version', $this->ver);
      }
   }

   function admin_menu()
   {
      $this->wp_user = get_userdata(get_current_user_id());
      $this->hook = add_menu_page($this->name, $this->name, $this->getPluginPermissionStatus(), $this->domain, array(&$this, 'admin_index'));
      add_action('load-'.$this->hook, array(&$this, 'on_load_page'));
      wp_enqueue_script('jquery-ui-datepicker', plugins_url('/js/ui.datepicker.min.js', __FILE__), array('jquery','jquery-ui-core'));
      wp_enqueue_style($this->domain.'-style', plugins_url('css/style.css', __FILE__));
   }

   function on_load_page() {
      wp_enqueue_script('common');
      wp_enqueue_script('wp-lists');
      wp_enqueue_script('postbox');

      /* load metaboxes */
      foreach ($this->components as $item) {
         $item->setHook($this->hook);        // Set hook
         $item->setDomain($this->domain);    // Set domain
         if (method_exists($item, "load_meta_boxes")) {
            $item->load_meta_boxes();
         }
      }
   }

   function admin_index()
   {
      $page_title = $this->getPageTitle($this->page);
      $message = get_option('wpf_message', '');
      $output = "";
      if ($message) {
         list ($type, $msg) = explode(":", $message);
         $message = '<div id="message" class="'.$type.'">'.
            '<p>'.$msg.'</p>'.
         '</div>';
         update_option('wpf_message', '');
      }

      $output .= '<div class="wrap">'.
         '<div id="icon-edit" class="icon32"><br></div>'.
         '<h2>'.$page_title.'</h2>'.
         $message.
         $this->createMenu($this->page);

      /* load page, view */
      if (array_key_exists($this->page, $this->components)) {

         $permission_error = 0;
         if (method_exists($this->components[$this->page], 'getPermissions')) {
            $class_at_hand = $this->components[$this->page];
            if (!$this->getPermissionStatus($this->wp_user->roles, $class_at_hand->getPermissions()))
               $permission_error = 1;
         }
         
         if (method_exists($this->components[$this->page], "view".ucfirst($this->view)) && $permission_error == 0) {
            $output .= call_user_func(array($this->components[$this->page], "view".ucfirst($this->view)), $this->action);
         } else {
            $output .= __("<b>Error:</b> View is not found!", $this->domain);
         }
         
      } else {
         $output .= __("<b>Error:</b> Component is not found!", $this->domain);
      }
      
      $output .= '</div>';

      // Print output
      echo $output;
   }

   function getShortcode($atts, $content = null) {
      /*
       atts:
          from="[yyyy-mm-dd]" starting date for report
          to="[yyyy-mm-dd]" end date for report
          totals="true" show grand total table
      */

      global $wpdb;        
      
      $css_file = get_option("wpf_css", 'custom');
      wp_enqueue_style($css_file.'-style', plugins_url( '/css/'.$css_file.'.css', __FILE__ ));
        
      $start_date = (preg_match("/^([0-9]{4}\-[0-9]{2}-[0-9]{2})?$/sim", $atts['from']))?" AND date >= '".$atts['from']."'":"";
      $end_date = (preg_match("/^([0-9]{4}\-[0-9]{2}-[0-9]{2})?$/sim", $atts['to']))?" AND date <= '".$atts['to']."'":"";

      if (get_option("wpf_balance", 'no') == 'yes') {
         $blance_income = $wpdb->get_var("SELECT IF(SUM(amount),SUM(amount),0) FROM ".$wpdb->prefix."finance WHERE date < '".$atts['from']."' AND status='1'");
         $blance_expense = $wpdb->get_var("SELECT IF(SUM(amount),SUM(amount),0) FROM ".$wpdb->prefix."finance WHERE date < '".$atts['from']."' AND status='2'");
         $balance = $blance_income - $blance_expense;
      }
      
      $currency_data = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."finance_currencies WHERE iso_code='".get_option('wpf_currency', 'USD')."' LIMIT 1", ARRAY_A);  
      $currency = $currency_data['iso_code'];
      if (get_option('wpf_display', 'none') == 'none') {
         $currency = '';
      } elseif (get_option('wpf_display', 'none') == 'symbol' && strlen($currency_data['symbol'])>0) {
         $currency = $currency_data['symbol'];
      }
    
      $records = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."finance WHERE 1".$start_date.$end_date, ARRAY_A);

      $income = $expenses = "";
      $income_sum = $expenses_sum = 0;

      for ($i=0; $i<count($records); $i++) {
         if ($records[$i]['status'] == 1) {
            $income .= '<div class="finance-row">'.$records[$i]['line'].'<span>'.
               ((get_option('wpf_position') == 'infront')?$currency.' ':'').
               number_format($records[$i]['amount'], 2).
               ((get_option('wpf_position', 'after') == 'after')?$currency.' ':'').
            '</span></div>';
            $income_sum += $records[$i]['amount'];
         } else {
            $expenses .= '<div class="finance-row">'.$records[$i]['line'].'<span>'.
               ((get_option('wpf_position') == 'infront')?$currency.' ':'').
               number_format($records[$i]['amount'], 2).
               ((get_option('wpf_position', 'after') == 'after')?$currency.' ':'').
            '</span></div>';
            $expenses_sum += $records[$i]['amount'];
         }
      }

      if (get_option("wpf_balance", 'no') == 'yes') {
         $line = '<div class="total-row">'.__("Balance from previous periods:", "wpfinance").'<span>';
         $line .= ((get_option('wpf_position') == 'infront')?$currency.' ':'');  
         $line .= number_format($balance, 2);
         $line .= ((get_option('wpf_position', 'after') == 'after')?$currency.' ':'');
         $line .= '</span></div>';
      }

      // Grand total
      $grand_total = ((get_option('wpf_position') == 'infront')?$currency.' ':'').
         number_format(($income_sum - $expenses_sum + $balance), 2).
         ((get_option('wpf_position', 'after') == 'after')?$currency.' ':'');

      // Income sum
      $income_sum = ((get_option('wpf_position') == 'infront')?$currency.' ':'').
         number_format($income_sum, 2).
         ((get_option('wpf_position', 'after') == 'after')?$currency.' ':'');

      // Expenses sum
      $expenses_sum = ((get_option('wpf_position') == 'infront')?$currency.' ':'').
         number_format($expenses_sum, 2).
         ((get_option('wpf_position', 'after') == 'after')?$currency.' ':'');

      $incomeTXT = __("Income:", $this->domain);
      $expensesTXT = __("Expenses:", $this->domain);
      $grandtotalTXT = __("Grand Total:", $this->domain);

      $grand_totals = ($atts['totals'] == "true")?'
         <div class="financial-totals">
             '.$line.'
             <div class="total-row">'.$incomeTXT.'<span>'.$income_sum.'</span></div>
             <div class="total-row">'.$expensesTXT.'<span>'.$expenses_sum.'</span></div>
             <div class="total-row-bold">'.$grandtotalTXT.'<span>'.$grand_total.'</span></div>
         </div>':'';
        
      $incomeTXT2 = __("Income", $this->domain);
      $expensesTXT2 = __("Expenses", $this->domain);

      $output .= '
         <div id="financial-report" class="finance-report">
            <div class="finance-table">
               <div class="finance-income">
                 <b>'.$incomeTXT2.'</b>
                 '.$income.'
               </div>
               <div class="finance-expenses">
                 <b>'.$expensesTXT2.'</b>
                 '.$expenses.'
               </div>
            </div>
            '.$grand_totals.'
         </div>';

      return $output;
   }

   // --------------------------------------------------------------------------

   function loadComponents()
   {
      $files = scandir(dirname( __FILE__ )."/".$this->c_path);
      foreach ($files as $file) {
         if ($file != "." && $file != "..") {
            include_once $this->c_path.$file;
            $class_name = substr($file, 0, -4);
            $class = new $class_name;
            $this->components[$class->getIdentifier()] = $class;
         }
      }
   }

   function createMenu($selected = 'default')
   {
      $url_print = 'admin.php?page='.$_GET['page'];
      $selected = ($selected == '')?'default':$selected;
      $count = 1;
      
      $date1 = ($_POST['date1'])?$_POST['date1']:$_GET['date1'];
      $date2 = ($_POST['date2'])?$_POST['date2']:$_GET['date2'];
      
      /* order menu first */
      $ordered = array();      
      foreach ($this->components as $item) {
         if (method_exists($item, 'getPermissions')) {
            if ($this->getPermissionStatus($this->wp_user->roles, $item->getPermissions()))
               $ordered[$item->getOrder()] = $item;
         } else {
            $ordered[$item->getOrder()] = $item;
         }
      }
      ksort($ordered);

      /* create menu */
      $menu = '<div class="wpf_menu"><ul>';
      foreach ($ordered as $item) {
         $separator = ($count < count($ordered))?' | ':'';
         
         if ($item->getIdentifier() == $selected) {
            $menu .= '<li><strong>'.$item->getTitle().'</strong>'.$separator.'</li>';
         } else {
            $url = 'admin.php?page='.$this->domain;
            $url .= ($item->getIdentifier() != 'default')?'&menu='.$item->getIdentifier():'';
            $menu .= '<li><a href="'.$url.'" title="'.$item->getTitle().'">'.$item->getTitle().'</a>'.$separator.'</li>';
         }
         
         $count++;
      }
      $menu .= '</ul>';
      $menu .= ($selected == 'default' && !$_GET['menu'])?'<div class="wpf_print"><a href="'.admin_url($url_print.'&menu=default/print&noheader=true&date1='.$date1.'&date2='.$date2).'" target="_blank" title="'.__("Print report", $this->domain).'">'.__("Print", $this->domain).'</a></div>':'';
      $menu .= '</div>';

      return $menu;
   }

   function getPageTitle($identifier)
   {
      $title = '';
      if (array_key_exists($identifier, $this->components) && $identifier != 'default') 
         $title = ': '.$this->components[$identifier]->getTitle();

      return $this->name.$title;
   }

   function getScript()
   {

   }

   function getPermissionStatus($user_roles, $role_haystack)
   {
      foreach ($user_roles as $role) {
         if (in_array($role, $role_haystack))
            return true;
      }
      
      return false;
   }

   function getPluginPermissionStatus()
   {
      foreach ($this->wp_user->roles as $role) {
         if (get_option('wpf_role_'.$role, 0) == 1) {
            return $role;
         }
      }
      
      return 'administrator';
   }
   
}

$wpfinanceplugin = new WPFinancePlugin();
?>
