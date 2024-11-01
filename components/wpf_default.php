<?php
class wpf_default {

  private $identifier = "default";
  private $title = "Overview";
  private $order = 1;
  private $hook;
  private $domain;

  // Default methods -----------------------------------------------------------

  public function load_meta_boxes()
  {
    add_meta_box('side-filter', __('Filter', $this->domain), array(&$this, 'side_filter_metabox'), $this->hook, 'side', 'core');
    add_meta_box('side-grand-total', __('Overview', $this->domain), array(&$this, 'side_grand_total_metabox'), $this->hook, 'side', 'core');
  }

  public function getIdentifier()
  {
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
    $this->title = __("Overview", $this->domain);
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

  function getCurrencyString()
  {
    global $wpdb;
    
    $iso = get_option('wpf_currency', 'USD');
    $currency_data = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."finance_currencies WHERE iso_code='".$iso."' LIMIT 1", ARRAY_A);
    $currency = $currency_data['iso_code'];
    if (get_option('wpf_display', 'none') == 'none') {
      $currency = '';
    } elseif (get_option('wpf_display', 'none') == 'symbol' && strlen($currency_data['symbol'])>0) {
      $currency = $currency_data['symbol'];
    }
    
    return $currency;
  }
  
  function getCurrencyWithString($sum, $currency)
  {
    $result = (get_option('wpf_position') == 'infront')?$currency.' ':'';
    $result .= number_format($sum, 2);
    $result .= (get_option('wpf_position', 'after') == 'after')?' '.$currency:'';
    return $result;
  }
  
  // Views ---------------------------------------------------------------------
  public function viewIndex()
  {
    global $wpdb;
    $output = '';
    $url = 'admin.php?page='.$_GET['page'];
    $user = $this->wp_user = get_userdata(get_current_user_id());
    $currency = $this->getCurrencyString();

    $date1 = ($_POST['date1'])?$_POST['date1']:$_GET['date1'];
    $date2 = ($_POST['date2'])?$_POST['date2']:$_GET['date2'];
    $date1 = ($date1)?$date1:date("Y-m-d", strtotime("-".(date("d")-1)." days"));
    $date2 = ($date2)?$date2:date("Y-m-d");

    $accounting = (get_option('wpf_accounting', 'single') == 'individual' && !in_array('administrator', $user->roles))?" AND user_id='".$user->data->ID."'":"";
    $username = (get_option('wpf_username', 'no') == 'yes')?' | added by %username%':'';
    
    $income = $wpdb->get_results("SELECT f.*, u.user_nicename FROM ".$wpdb->prefix."finance AS f LEFT JOIN ".$wpdb->base_prefix."users AS u ON f.user_id=u.ID WHERE status='1' AND `date` >= '".$date1."' AND `date` <= '".$date2."'".$accounting." ORDER BY date DESC", ARRAY_A);
    $expenses = $wpdb->get_results("SELECT f.*, u.user_nicename FROM ".$wpdb->prefix."finance AS f LEFT JOIN ".$wpdb->base_prefix."users AS u ON f.user_id=u.ID WHERE status='2' AND `date` >= '".$date1."' AND `date` <= '".$date2."'".$accounting." ORDER BY date DESC", ARRAY_A);

    $rows = (count($income) > count($expenses))?count($income):count($expenses);
    
    $output .=
      '<div id="metaboxes-general">'.
        '<form action="'.admin_url($url.'&date1='.$date1.'&date2='.$date2).'" method="post">';

    $output .=
      '<div id="poststuff" class="metabox-holder has-right-sidebar" style="padding-top: 0px;">'.
        '<div id="side-info-column" class="inner-sidebar">'.$this->do_meta_boxes($this->hook, 'side', $data).'</div>'.
        '<div id="post-body" class="has-sidebar">'.
          '<div id="post-body-content" class="has-sidebar-content">'.
            '<table class="widefat" style="margin-bottom: 20px;">'.
              '<thead>'.
                '<tr>'.
                  '<th><span>'.__('Income', $this->domain).'</span><span style="float: right;"><a href="'.admin_url($url.'&menu=default/addIncome').'" class="button">'.__('Add', $this->domain).'</a></span></th>'.
                  '<th><span>'.__('Expenses', $this->domain).'</span><span style="float: right;"><a href="'.admin_url($url.'&menu=default/addExpense').'" class="button">'.__('Add', $this->domain).'</a></span></th>'.      
                '</tr>'.
              '</thead>'.
              '<tfoot>'.
                '<tr><th>'.__('Income', $this->domain).'</th><th>'.__('Expenses', $this->domain).'</th></tr>'.
              '</tfoot>'.
              '</tbody>';
    
    if ($rows > 0) {
      for ($i=0; $i<$rows; $i++) {
        $output .= '<tr id="finances-'.($i+1).'" class="format-default iedit" valign="top">'.
                    '<td class="income column-income">';

        if ($i < count($income)) {        
          $output .= '<span>'.
                        '<a href="'.admin_url($url.'&menu=default/addIncome&finance_id='.$income[$i]['finance_id']).'" title="'.$income[$i]['line'].'">'.$income[$i]['line'].'</a>';
          $output .= (!empty($income[$i]['invoice']))?' ('.__('Invoice #:', $this->domain).' '.$income[$i]['invoice'].')<br/>':'<br/>';
          $output .= '<span style="font-size: 10px;">'.$income[$i]['date'].str_replace('%username%', $income[$i]['user_nicename'], $username).'</span>';
          $output .= '</span>';
          $output .= '<span style="float: right">';
          $output .= (get_option('wpf_position') == 'infront')?$currency.' ':'';
          $output .= number_format($income[$i]['amount'], 2);
          $output .= (get_option('wpf_position', 'after') == 'after')?' '.$currency:'';
          $output .= '</span>';
        }
        
        $output .= '</td><td>';

        if ($i < count($expenses)) {
          $output .= '<span>'.
                        '<a href="'.admin_url($url.'&menu=default/addExpense&finance_id='.$expenses[$i]['finance_id']).'" title="'.$expenses[$i]['line'].'">'.$expenses[$i]['line'].'</a>';
          $output .= (!empty($expenses[$i]['invoice']))?' ('.__('Invoice #:', $this->domain).' '.$expenses[$i]['invoice'].')<br/>':'<br/>';
          $output .= '<span style="font-size: 10px;">'.$expenses[$i]['date'].str_replace('%username%', $income[$i]['user_nicename'], $username).'</span>';
          $output .= '</span>';
          $output .= '<span style="float: right">';
          $output .= (get_option('wpf_position') == 'infront')?$currency.' ':'';
          $output .= number_format($expenses[$i]['amount'], 2);
          $output .= (get_option('wpf_position', 'after') == 'after')?' '.$currency:'';
          $output .= '</span>';
        }

        $output .= '</td></tr>';
      }
    } else {
      $output .= '<tr><td colspan="2">'.__('No data.', $this->domain).'</td></tr>';
    }
      $output .= '</tbody></table>'.
                        $this->do_meta_boxes($this->hook, 'normal', $data).
                      '</div>'.
                  '</div>'.
                  '<br class="clear"/>'.
                 '</div>';

    $output .= '</form>';  
    $output .= '</div>';

    return $output;
  }

  public function viewAddIncome($action = '')
  {
    global $wpdb;
    $output = '';
    $url = 'admin.php?page='.$_GET['page'];    
    $user = $this->wp_user = get_userdata(get_current_user_id());
    
    if ($action == 'save') {
      $check_date = (preg_match("/^(\d{4}-\d{2}-\d{2})$/sim", $_REQUEST['date']))?1:0;
      $check_title = (strlen($_REQUEST['title'])>1)?1:0;
      $check_amount = (preg_match("/^(\d+(\.\d+)?)?$/sim", $_REQUEST['amount']))?1:0;

      if ($check_date == 1 && $check_title == 1 && $check_amount == 1) {
        $date = date("Y-m-d", strtotime($_REQUEST['date']));
        if (preg_match("/^([0-9]+)+$/sim",$_REQUEST['finance_id'])) {
          // Update record
          $wpdb->update(
            $wpdb->prefix.'finance', 
            array(
              'line' => $_REQUEST['title'], 'description' => $_REQUEST['description'],
              'date' => $date, 'amount' => $_REQUEST['amount'], 'status' => 1,
              'invoice' => $_REQUEST['invoice']
            ),
            array('finance_id' => $_REQUEST['finance_id']),
            array('%s', '%s', '%s', '%f', '%s', '%s'), array('%d')
          );
          update_option('wpf_message', 'updated:'.__('Income record was updated successfully', $this->domain));
        } else {
          // Insert record
          $wpdb->insert(
            $wpdb->prefix.'finance', 
            array(
              'line' => $_REQUEST['title'], 'description' => $_REQUEST['description'],
              'date' => $date, 'amount' => $_REQUEST['amount'], 'status' => 1,
              'invoice' => $_REQUEST['invoice'], 'user_id' => $user->data->ID
            ), 
            array('%s', '%s', '%s', '%f', '%s', '%s', '%d') 
          );
          update_option('wpf_message', 'updated:'.__('New income record was added successfully', $this->domain));
        }
        wp_redirect(admin_url($url), 301);
      } else {
        $message = ($check_amount !=1 )?__('Incorrect date format', $this->domain):'';
        $message = ($check_title !=1 )?__('Please specify income title', $this->domain):$message;
        $message = ($check_date !=1 )?__('Amount format is incorrect!', $this->domain):$message;

        $data_string = '&date='.$_REQUEST['date'].'&title='.$_REQUEST['title'].
                        '&amount='.$_REQUEST['amount'].'&invoice='.$_REQUEST['invoice'].
                        '&description='.$_REQUEST['description'];

        update_option('wpf_message', 'error:'.$message); 
        wp_redirect(admin_url($url.'&menu=default/addIncome'.$data_string), 301);
      }
    }
    
    $edit_query = (!in_array('administrator', $user->roles))?" AND user_id='".$user->data->ID."'":"";
    $edit = $wpdb->get_row("SELECT f.*, u.user_nicename FROM ".$wpdb->prefix."finance AS f LEFT JOIN ".$wpdb->base_prefix."users AS u ON f.user_id = u.ID WHERE finance_id='".$_REQUEST['finance_id']."'".$edit_query." LIMIT 1", ARRAY_A);
    
    $data['date'] = ($_REQUEST['date'])?$_REQUEST['date']:$edit['date'];
    $data['title'] = ($_REQUEST['title'])?$_REQUEST['title']:$edit['line'];
    $data['amount'] = ($_REQUEST['amount'])?$_REQUEST['amount']:$edit['amount'];
    $data['invoice'] = ($_REQUEST['invoice'])?$_REQUEST['invoice']:$edit['invoice'];
    $data['description'] = ($_REQUEST['description'])?$_REQUEST['description']:$edit['description'];
    
    $output .= '<h3>'.__('Add Income', $this->domain).'</h3>';

    $output .=
      '<form method="POST" action="'.admin_url($url.'&menu=default/addIncome/save&noheader=true').'">'.
        '<input type="hidden" name="finance_id" value="'.$edit['finance_id'].'"/>'.
        '<table class="form-table"><tbody>'.
          '<tr valign="top">'.
            '<th scope="row"><label for="date">'.__('Date', $this->domain).'<span> *</span>: </label></th>'.
            '<td><input id="date" type="text" name="date" value="'.(($data['date'])?$data['date']:date("Y-m-d")).'" class="date-picker regular-text" required="true"/></td>'.
          '</tr>'.
          '<tr valign="top">'.
            '<th scope="row"><label for="title">'.__('Income title', $this->domain).'<span> *</span>: </label></th>'.
            '<td><input id="title" name="title" value="'.$data['title'].'" type="text" class="regular-text" required="true"/></td>'.
          '</tr>'.
          '<tr valign="top">'.
            '<th scope="row"><label for="amount">'.__('Amount', $this->domain).'<span> *</span>: </label></th>'.
            '<td>'.
              '<input id="amount" name="amount" value="'.$data['amount'].'" type="text" class="regular-text" required="true"/>'.
              '<p class="description">'.__('Input amount only. For example: 12.34', $this->domain).'</p>'.
            '</td>'.
          '</tr>'.
          '<tr valign="top">'.
            '<th scope="row"><label for="invoice">'.__('Invoice #', $this->domain).': </label></th>'.
            '<td><input id="invoice" name="invoice" value="'.$data['invoice'].'" type="text" class="regular-text"/></td>'.
          '</tr>'.
          '<tr valign="top">'.
            '<th scope="row"><label for="description">'.__('Description', $this->domain).': </label></th>'.
            '<td><textarea id="description" name="description" class="large-text code">'.$data['description'].'</textarea></td>'.
          '</tr>'.
          '<tr valign="top">'.
            '<th scope="row"><label for="user">'.__('Added by', $this->domain).': </label></th>'.
            '<td><input id="user" name="user" value="'.(($edit['finance_id']>0)?$edit['user_nicename']:$user->data->user_nicename).'" type="text" class="regular-text" readonly/></td>'.
          '</tr>'.
        '</tbody>'.
        '<tr><td colspan="2">'.
          '<input type="submit" value="'.__('Save Income', $this->domain).'" class="button-primary"/> '.
          '<a href="'.$url.'" title="'.__('Cancel', $this->domain).'" class="button-secondary">'.__('Cancel', $this->domain).'</a>'.
          '<span style="float:right;">'.
            (($edit['finance_id']>0)?'<a href="'.admin_url($url.'&menu=default/removeRecord&finance_id='.$edit['finance_id'].'&noheader=true').'" class="button-secondary">'.__('Remove record', $this->domain).'</a>':'').
          '</span>'.
        '</td></tr>'.
        '</table>'.
      '</form>';

    $output .=
      '<script type="text/javascript">'.
        'jQuery(function($) {$(".date-picker").datepicker({dateFormat: \'yy-mm-dd\'});});'.
      '</script>';

    return $output;
  }

  public function viewAddExpense($action = '')
  {
    global $wpdb;
    $output = '';
    $url = 'admin.php?page='.$_GET['page'];
    $user = $this->wp_user = get_userdata(get_current_user_id());

    if ($action == 'save') {
      $check_date = (preg_match("/^(\d{4}-\d{2}-\d{2})$/sim", $_REQUEST['date']))?1:0;
      $check_title = (strlen($_REQUEST['title'])>1)?1:0;
      $check_amount = (preg_match("/^(\d+(\.\d+)?)?$/sim", $_REQUEST['amount']))?1:0;

      if ($check_date == 1 && $check_title == 1 && $check_amount == 1) {
        $date = date("Y-m-d", strtotime($_REQUEST['date']));
        if (preg_match("/^([0-9]+)+$/sim",$_REQUEST['finance_id'])) {
          // Update record
          $wpdb->update(
            $wpdb->prefix.'finance', 
            array(
              'line' => $_REQUEST['title'], 'description' => $_REQUEST['description'],
              'date' => $date, 'amount' => $_REQUEST['amount'], 'status' => 2,
              'invoice' => $_REQUEST['invoice']
            ),
            array('finance_id' => $_REQUEST['finance_id']),
            array('%s', '%s', '%s', '%f', '%s', '%s'), array('%d')
          );
          update_option('wpf_message', 'updated:'.__('Expense record was updated successfully', $this->domain));
        } else {
          // Insert record
          $wpdb->insert(
            $wpdb->prefix.'finance', 
            array(
              'line' => $_REQUEST['title'], 'description' => $_REQUEST['description'],
              'date' => $date, 'amount' => $_REQUEST['amount'], 'status' => 2,
              'invoice' => $_REQUEST['invoice'], 'user_id' => $user->data->ID
            ), 
            array('%s', '%s', '%s', '%f', '%s', '%s', '%d') 
          );
          update_option('wpf_message', 'updated:'.__('New expense record was added successfully', $this->domain));
        }
        wp_redirect(admin_url($url), 301);
      } else {
        $message = ($check_amount !=1 )?__('Incorrect date format', $this->domain):'';
        $message = ($check_title !=1 )?__('Please specify income title', $this->domain):$message;
        $message = ($check_date !=1 )?__('Amount format is incorrect!', $this->domain):$message;

        $data_string = '&date='.$_REQUEST['date'].'&title='.$_REQUEST['title'].
                        '&amount='.$_REQUEST['amount'].'&invoice='.$_REQUEST['invoice'].
                        '&description='.$_REQUEST['description'];

        update_option('wpf_message', 'error:'.$message); 
        wp_redirect(admin_url($url.'&menu=default/addExpense'.$data_string), 301);
      }
    }
    
    $edit_query = (!in_array('administrator', $user->roles))?" AND user_id='".$user->data->ID."'":"";
    $edit = $wpdb->get_row("SELECT f.*, u.user_nicename FROM ".$wpdb->prefix."finance AS f LEFT JOIN ".$wpdb->base_prefix."users AS u ON f.user_id = u.ID WHERE finance_id='".$_REQUEST['finance_id']."'".$edit_query." LIMIT 1", ARRAY_A);

    $data['date'] = ($_REQUEST['date'])?$_REQUEST['date']:$edit['date'];
    $data['title'] = ($_REQUEST['title'])?$_REQUEST['title']:$edit['line'];
    $data['amount'] = ($_REQUEST['amount'])?$_REQUEST['amount']:$edit['amount'];
    $data['invoice'] = ($_REQUEST['invoice'])?$_REQUEST['invoice']:$edit['invoice'];
    $data['description'] = ($_REQUEST['description'])?$_REQUEST['description']:$edit['description'];

    $output .= '<h3>'.__('Add Expense', $this->domain).'</h3>';

    $output .= '<form method="POST" action="'.admin_url($url.'&menu=default/addExpense/save&noheader=true').'">'.
      '<input type="hidden" name="finance_id" value="'.$edit['finance_id'].'"/>'.
      '<table class="form-table"><tbody>'.
        '<tr valign="top">'.
          '<th scope="row"><label for="date">'.__('Date', $this->domain).'<span> *</span>: </label></th>'.
          '<td><input id="date" type="text" name="date" value="'.(($data['date'])?$data['date']:date("Y-m-d")).'" class="date-picker regular-text" required="true"/></td>'.
        '</tr>'.
        '<tr valign="top">'.
          '<th scope="row"><label for="title">'.__('Expense title', $this->domain).'<span> *</span>: </label></th>'.
          '<td><input id="title" name="title" value="'.$data['title'].'" type="text" class="regular-text" required="true"/></td>'.
        '</tr>'.
        '<tr valign="top">'.
          '<th scope="row"><label for="amount">'.__('Amount', $this->domain).'<span> *</span>: </label></th>'.
          '<td>'.
            '<input id="amount" name="amount" value="'.$data['amount'].'" type="text" class="regular-text" required="true"/>'.
            '<p class="description">'.__('Input amount only. For example: 12.34', $this->domain).'</p>'.
          '</td>'.
        '</tr>'.
        '<tr valign="top">'.
          '<th scope="row"><label for="invoice">'.__('Invoice #', $this->domain).': </label></th>'.
          '<td><input id="invoice" name="invoice" value="'.$data['invoice'].'" type="text" class="regular-text"/></td>'.
        '</tr>'.
        '<tr valign="top">'.
          '<th scope="row"><label for="description">'.__('Description', $this->domain).': </label></th>'.
          '<td><textarea id="description" name="description" class="large-text code">'.$data['description'].'</textarea></td>'.
        '</tr>'.
        '<tr valign="top">'.
          '<th scope="row"><label for="user">'.__('Added by', $this->domain).': </label></th>'.
          '<td><input id="user" name="user" value="'.(($edit['finance_id']>0)?$edit['user_nicename']:$user->data->user_nicename).'" type="text" class="regular-text" readonly/></td>'.
        '</tr>'.
      '</tbody>'.
      '<tr><td colspan="2">'.
        '<input type="submit" value="'.__('Save Expense', $this->domain).'" class="button-primary"/> '.
        '<a href="'.$url.'" title="'.__('Cancel', $this->domain).'" class="button-secondary">'.__('Cancel', $this->domain).'</a>'.
        '<span style="float:right;">'.
          (($edit['finance_id']>0)?'<a href="'.admin_url($url.'&menu=default/removeRecord&finance_id='.$edit['finance_id'].'&noheader=true').'" class="button-secondary">'.__('Remove record', $this->domain).'</a>':'').
        '</span>'.
      '</td></tr>'.
      '</table>'.
    '</form>';

    $output .= '<script type="text/javascript">';
    $output .= 'jQuery(function($) {$(".date-picker").datepicker({dateFormat: \'yy-mm-dd\'});});';
    $output .= '</script>';

    return $output;
  }

  public function viewRemoveRecord()
  {
    global $wpdb;
    $user = $this->wp_user = get_userdata(get_current_user_id());
    $url = 'admin.php?page='.$_GET['page'];

    if (preg_match("/^([0-9]+)+$/sim",$_REQUEST['finance_id'])) {
      $remove_query = (!in_array('administrator', $user->roles))?" AND user_id='".$user->data->ID."'":"";
      $wpdb->query("DELETE FROM ".$wpdb->prefix."finance WHERE finance_id='".$_REQUEST['finance_id']."'".$remove_query." LIMIT 1");
        update_option('wpf_message', 'error:'.__('Record was successfuly removed!', $this->domain)); 
        wp_redirect(admin_url($url), 301);
    }
  }

  public function viewPrint()
  {
    global $wpdb;
    $url = 'admin.php?page='.$_GET['page'];
    $user = $this->wp_user = get_userdata(get_current_user_id());
    $currency = $this->getCurrencyString();
    $income = $expenses = $tatal_records = 0;
    $output = '';
    $grand_total_output = '';

    $date1 = ($_GET['date1'])?$_GET['date1']:date("Y-m-d", strtotime("-".(date("d")-1)." days"));
    $date2 = ($_GET['date2'])?$_GET['date2']:date("Y-m-d");
    
    $format = get_option('wpf_format', 'standard');
    $accounting = (get_option('wpf_accounting', 'single') == 'individual' && !in_array('administrator', $user->roles))?" AND user_id='".$user->data->ID."'":"";
    $user_query = (!in_array('administrator', $user->roles))?" AND user_id='".$user->data->ID."'":"";
    
    if ($format == "single") {
      $report = $wpdb->get_results("SELECT f.*, u.user_nicename FROM ".$wpdb->prefix."finance AS f LEFT JOIN ".$wpdb->base_prefix."users AS u ON f.user_id=u.ID WHERE `date` >= '".$date1."' AND `date` <= '".$date2."'".$accounting.$user_query." ORDER BY date ASC", ARRAY_A);     
      foreach ($report as $line) {
        $first = ($tatal_records == 0)?' first':'';
        $description = (get_option('wpf_print_description', 'no') == 'yes')?'<br/><span class="description">'.$line['description']."</span>":"";
        $invoice = ($line['invoice'])?' ('.__('Invoice #:', $this->domain).' '.$line['invoice'].')':'';
        $username = (get_option('wpf_print_username', 'no') == 'yes')?'%username% / ':'';
        
        $output .=
          '<div class="record'.$first.'">'.
            '<div class="name">'.str_replace('%username%', $line['user_nicename'], $username).$line['line'].$invoice.$description.'</div>'.
            '<div class="amount">'.(($line['status']==2)?'-':'').$this->getCurrencyWithString($line['amount'], $currency).'</div>'.
          '</div>';
        
        if ($line['status'] == 1) $income += $line['amount']; else $expenses += $line['amount'];
        $tatal_records++;
      }
      $output .= '<div class="balance"><span>'.__('Balance', $this->domain).': '.$this->getCurrencyWithString(($income-$expenses), $currency).'</span></div>';
    } else {
      $col1 = $col2 = '';
      
      /* Income */
      $report_income = $wpdb->get_results("SELECT f.*, u.user_nicename FROM ".$wpdb->prefix."finance AS f LEFT JOIN ".$wpdb->base_prefix."users AS u ON f.user_id=u.ID WHERE status='1' AND `date` >= '".$date1."' AND `date` <= '".$date2."'".$accounting.$user_query." ORDER BY date ASC", ARRAY_A);
      foreach($report_income as $line) {
        $description = (get_option('wpf_print_description', 'no') == 'yes')?'<br/><span class="description">'.$line['description']."</span>":"";
        $invoice = ($line['invoice'])?' ('.__('Invoice #:', $this->domain).' '.$line['invoice'].')':'';
        $username = (get_option('wpf_print_username', 'no') == 'yes')?'%username% / ':'';
        
        $col1 .=
          '<div class="record">'.
            '<div class="name">'.str_replace('%username%', $line['user_nicename'], $username).$line['line'].$invoice.$description.'</div>'.
            '<div class="amount">'.$this->getCurrencyWithString($line['amount'], $currency).'</div>'.
          '</div>';
          
        $income += $line['amount'];
      }
      $income_output .= '<div class="balance"><span>'.__('Total', $this->domain).': '.$this->getCurrencyWithString($income, $currency).'</span></div>';

      /* Expenses */
      $report_expenses = $wpdb->get_results("SELECT f.*, u.user_nicename FROM ".$wpdb->prefix."finance AS f LEFT JOIN ".$wpdb->base_prefix."users AS u ON f.user_id=u.ID WHERE status='2' AND `date` >= '".$date1."' AND `date` <= '".$date2."'".$accounting.$user_query." ORDER BY date ASC", ARRAY_A);   
      foreach($report_expenses as $line) {
        $description = (get_option('wpf_print_description', 'no') == 'yes')?'<br/><span class="description">'.$line['description']."</span>":"";
        $invoice = ($line['invoice'])?' ('.__('Invoice #:', $this->domain).' '.$line['invoice'].')':'';
        $username = (get_option('wpf_print_username', 'no') == 'yes')?'%username% / ':'';
        
        $col2 .=
          '<div class="record">'.
            '<div class="name">'.str_replace('%username%', $line['user_nicename'], $username).$line['line'].$invoice.$description.'</div>'.
            '<div class="amount">'.$this->getCurrencyWithString($line['amount'], $currency).'</div>'.
          '</div>';
          
        $expenses += $line['amount'];
      }
      $expenses_output .= '<div class="balance"><span>'.__('Total', $this->domain).': '.$this->getCurrencyWithString($expenses, $currency).'</span></div>';

      $output .=
        '<div class="columns">'.
          '<div class="column border"><h2>'.__('Income', $this->domain).'</h2>'.$col1.$income_output.'</div>'.
          '<div class="column"><h2>'.__('Expenses', $this->domain).'</h2>'.$col2.$expenses_output.'</div>'.
        '</div>';
    }
    
    $grand_total_output .=
      '<div class="totals">'.
        '<div><span class="title">'.__('Total income', $this->domain).':</span> <span class="value">'.$this->getCurrencyWithString($income, $currency).'</span></div>'.
        '<div><span class="title">'.__('Total expenses', $this->domain).':</span> <span class="value">'.$this->getCurrencyWithString($expenses, $currency).'</span></div>'.
        '<div><span class="title-bold">'.__('Grand total', $this->domain).':</span> <span class="value-bold">'.$this->getCurrencyWithString(($income - $expenses), $currency).'</span></div>'.
      '</div>';
    
    echo
      '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">'.
      '<html lang="en">'.
        '<head>'.
          '<meta http-equiv="content-type" content="text/html; charset=utf-8">'.
          '<title>'.__('Financial report', $this->domain).'</title>'.
          '<link rel="stylesheet" type="text/css" href="'.plugins_url('/css/report-'.$format.'.css', dirname(__FILE__)).'">'.
        '</head>'.
        '<body>'.
          '<h1>'.__('Financial report', $this->domain).'</h1>'.
          '<p>'.__('Period', $this->domain).': '.$date1.' '.__('to', $this->domain).' '.$date2.'</p>'.
          $output.
          $grand_total_output.
        '</body>'.
      '</html>';

    exit;
  }
  
  // Metaboxes -----------------------------------------------------------------

  function side_filter_metabox($data)
  {
    $url = 'admin.php?page='.$_GET['page'];

    $date1 = ($_REQUEST['date1'])?$_REQUEST['date1']:date("Y-m-d", strtotime("-".(date("d")-1)." days"));
    $date2 = ($_REQUEST['date2'])?$_REQUEST['date2']:date("Y-m-d");
    $m1 = '&date1='.date("Y-m-d", strtotime("-1 month")).'&date2='.date("Y-m-d");
    $m3 = '&date1='.date("Y-m-d", strtotime("-3 month")).'&date2='.date("Y-m-d");
    $m6 = '&date1='.date("Y-m-d", strtotime("-6 month")).'&date2='.date("Y-m-d");
    $y1 = '&date1='.date("Y-m-d", strtotime("-1 year")).'&date2='.date("Y-m-d");

    $output .=
      '<div class="wpf-date-filter">'.
        '<div class="misc-pub-section">'.      
          __("From", $this->domain).'&nbsp;'.
          '<input class="start-date date-picker" id="date1" name="date1" type="text" value="'.$date1.'"/>&nbsp;'.
          __("to", $this->domain).'&nbsp;'.
          '<input class="start-date date-picker" id="date2" name="date2" type="text" value="'.$date2.'"/>'.
          '<div>&nbsp;</div>'.
          __('Previous periods', $this->domain).': '.
          '<ul>'.
            '<li><a href="'.admin_url($url.$m1).'" title="'.__("1m", $this->domain).'">'.__("1m", $this->domain).'</a> | </li>'.
            '<li><a href="'.admin_url($url.$m3).'" title="'.__("3m", $this->domain).'">'.__("3m", $this->domain).'</a> | </li>'.
            '<li><a href="'.admin_url($url.$m6).'" title="'.__("6m", $this->domain).'">'.__("6m", $this->domain).'</a> | </li>'.
            '<li><a href="'.admin_url($url.$y1).'" title="'.__("1y", $this->domain).'">'.__("1y", $this->domain).'</a></li>'.
          '<ul>'.
        '</div>'.
        '<div id="wpf-update-actions">'.
          '<input type="submit" value="'.__('Update', $this->domain).'" accesskey="p" tabindex="5" id="publish" class="button-primary" name="save">'.
        '</div>'.
      '</div>';

    $output .=
      '<script type="text/javascript">'.
        'jQuery(function($) {$(".date-picker").datepicker({dateFormat: \'yy-mm-dd\'});});'.
      '</script>';

    return $output;
  }

  function side_grand_total_metabox($data)
  {
    global $wpdb;
    $user = $this->wp_user = get_userdata(get_current_user_id());
    $currency = $this->getCurrencyString();
  
    $date1 = ($_REQUEST['date1'])?$_REQUEST['date1']:date("Y-m-d", strtotime("-".(date("d")-1)." days"));
    $date2 = ($_REQUEST['date2'])?$_REQUEST['date2']:date("Y-m-d");

    $accounting = (get_option('wpf_accounting', 'single') == 'individual' && !in_array('administrator', $user->roles))?" AND user_id='".$user->data->ID."'":"";
    
    $total_income = $wpdb->get_var("SELECT SUM(amount) FROM ".$wpdb->prefix."finance WHERE status='1' AND `date` >= '".$date1."' AND `date` <= '".$date2."'".$accounting);
    $total_expenses = $wpdb->get_var("SELECT SUM(amount) FROM ".$wpdb->prefix."finance WHERE status='2' AND `date` >= '".$date1."' AND `date` <= '".$date2."'".$accounting);

    $output .= '<div class="wpf-grand-total">';

    if (get_option('wpf_balance', 'no') == 'yes') {
      $blanace_income = $wpdb->get_var("SELECT SUM(amount) FROM ".$wpdb->prefix."finance WHERE status='1' AND `date` < '".$date1."' AND `date` < '".$date2."'".$accounting);
      $blanace_expenses = $wpdb->get_var("SELECT SUM(amount) FROM ".$wpdb->prefix."finance WHERE status='2' AND `date` < '".$date1."' AND `date` < '".$date2."'".$accounting);
      $balance = $blanace_income - $blanace_expenses;
      
      $output .=
        '<div class="misc-pub-section">'.      
          __('Balance from<br/>previouse periods:', $this->domain).
          '<span style="float: right;">'.
            $this->getCurrencyWithString($balance, $currency).
          '</span>'.
        '</div>';
    }

    $output .=
      '<div class="misc-pub-section">'.      
        __('Total income:', $this->domain).
        '<span style="float: right;">'.
          $this->getCurrencyWithString($total_income, $currency).    
        '</span>'.
      '</div>'.
      '<div class="misc-pub-section">'.      
        __('Total expenses:', $this->domain).
        '<span style="float: right;">'.
          $this->getCurrencyWithString($total_expenses, $currency).
        '</span>'.
      '</div>'.
      '<div class="misc-pub-section" style="border-bottom: 0px none;">'.      
        '<b>'.__('Grand total:', $this->domain).'</b>'.
        '<span style="float: right;"><b>'.
          $this->getCurrencyWithString(($total_income-$total_expenses+$balance), $currency).
        '</b></span>'.
      '</div>'.
    '</div>';

    return $output;
  }
}