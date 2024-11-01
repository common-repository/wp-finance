<?php
class wpf_configure {

  private $identifier = "configure";
  private $title = "Configure";
  private $permissions = array('administrator');
  private $order = 20;
  private $hook;
  private $domain;

  // Default methods -----------------------------------------------------------

  public function getIdentifier()
  {
    return $this->identifier;
  }

  public function getTitle()
  {
    return $this->title;
  }

  public function getPermissions()
  {
    return $this->permissions;
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
    $this->title = __("Configure", $this->domain);
  }

  // Views ---------------------------------------------------------------------

  public function viewIndex($action = '')
  {
    global $wpdb, $wp_roles;
    $output = '';
    $url = 'admin.php?page='.$_GET['page'];
    
    $currencies = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."finance_currencies WHERE 1 ORDER BY name ASC", ARRAY_A); // default USD
    $display = array(
      'none' => __('None', $this->domain),  // default
      'iso_code' => __('ISO code', $this->domain),
      'symbol' => __('Currency symbol', $this->domain)
    );
    $position = array(
      'infront' => __('In front of the number', $this->domain),
      'after' => __('After the number', $this->domain)  // default
    );
    $balance = array(
      'no' => __('No', $this->domain),  // default
      'yes' => __('Yes', $this->domain)
    );
    $accounting = array(
      'single' => __('Shared account', $this->domain),
      'individual' => __('Separate account for each user', $this->domain)
    );    
    $roles = apply_filters('editable_roles', $wp_roles->roles);
    $username = array(
      'no' => __('No', $this->domain),  // default
      'yes' => __('Yes', $this->domain)
    );
    /* Pringing settings */
    $format = array(
      'standard' => __('Standard', $this->domain),
      'columns' => __('Side by side', $this->domain),
      'single' => __('Single sheet', $this->domain)
    );
    $print_description = array(
      'no' => __('No', $this->domain),  // default
      'yes' => __('Yes', $this->domain)
    );
    $print_username = array(
      'no' => __('No', $this->domain),  // default
      'yes' => __('Yes', $this->domain)
    );
    
    if ($action == 'save') {
      $check_currency = (preg_match("/^([A-Z]{3}){1}$/sim", $_REQUEST['currency']))?1:0;
      $check_display = (preg_match("/^(none|iso_code|symbol)$/sim", $_REQUEST['display']))?1:0;
      $check_position = (preg_match("/^(infront|after)$/sim", $_REQUEST['position']))?1:0;
      $check_balance = (preg_match("/^(yes|no)$/sim", $_REQUEST['balance']))?1:0;
      $check_accounting = (preg_match("/^(single|individual)$/sim", $_REQUEST['accounting']))?1:0;
      $check_username = (preg_match("/^(yes|no)$/sim", $_REQUEST['username']))?1:0;
      $check_format = (preg_match("/^(standard|columns|single)$/sim", $_REQUEST['format']))?1:0;
      $check_print_description = (preg_match("/^(yes|no)$/sim", $_REQUEST['print_description']))?1:0;
      $check_print_username = (preg_match("/^(yes|no)$/sim", $_REQUEST['print_username']))?1:0;
    
      $roles_array = array('administrator');
      if (count($_REQUEST['role']) > 0) { 
        foreach ($_REQUEST['role'] as $key => $value) $roles_array[] = $value;
      }
    
      if ($check_currency && $check_display && $check_position && $check_balance && $check_accounting && $check_username
          && $check_format && $check_print_description && $check_print_username) {
        
        update_option('wpf_message', 'updated:'.__("Settings were updated successfully!", $this->domain));
        update_option('wpf_currency', $_REQUEST['currency']);
        update_option('wpf_display', $_REQUEST['display']);
        update_option('wpf_position', $_REQUEST['position']);
        update_option('wpf_balance', $_REQUEST['balance']);
        update_option('wpf_accounting', $_REQUEST['accounting']);
        update_option('wpf_username', $_REQUEST['username']);
        update_option('wpf_format', $_REQUEST['format']);
        update_option('wpf_print_description', $_REQUEST['print_description']);
        update_option('wpf_print_username', $_REQUEST['print_username']);
        
        /* update roles */
        foreach($roles as $key => $value) {
          if (in_array($key, $roles_array)) {
            update_option('wpf_role_'.$key, 1);
          } else {
            update_option('wpf_role_'.$key, 0);
          }
        }
        
        wp_redirect(admin_url($url), 301);
      } else {
        update_option('wpf_message', 'error:'.__("Settings were not updated! Please check configuration!", $this->domain)); 
        wp_redirect(admin_url($url.'&menu='.$_GET['menu']), 301);
      }
   }

    $output .= '<form method="POST" action="'.admin_url($url.'&menu=configure/index/save&noheader=true').'">'.
      '<h3>'.__('General settings', $this->domain).'</h3>'.
      '<table class="form-table"><tbody>'.
        '<tr valign="top">'.
          '<th scope="row"><label for="currency">'.__('Currency', $this->domain).': </label></th>'.
          '<td><select id="currency" name="currency">';
      foreach($currencies as $currency) {
        $selected = (get_option('wpf_currency', 'USD') == $currency['iso_code'])?' selected="selected"':'';
        $output .= '<option value="'.$currency['iso_code'].'"'.$selected.'>'.$currency['name'].'</option>';
      }

    $output .= '</select></td>'.
        '</tr>'.
        '<tr valign="top">'.
          '<th scope="row"><label for="display">'.__('Currency representation', $this->domain).': </label></th>'.
          '<td><select id="display" name="display">';     
      foreach ($display as $key => $value) {
        $selected = (get_option('wpf_display', 'none') == $key)?' selected="selected"':'';
        $output .= '<option value="'.$key.'"'.$selected.'>'.$value.'</option>';        
      }
    $output .= '</select><p class="description">'.__('Not all currency symbols are available in this version of WP Finance!', $this->domain).'</p>'. 
          '</td>'.
        '</tr>'.
        '<tr valign="top">'.
          '<th scope="row"><label for="position">'.__('Currency sign position', $this->domain).': </label></th>'.
          '<td><select id="position" name="position">';
      foreach ($position as $key => $value) {
        $selected = (get_option('wpf_position', 'after') == $key)?' selected="selected"':'';
        $output .= '<option value="'.$key.'"'.$selected.'>'.$value.'</option>';        
      }
    $output .= '</select></td>'.
        '</tr>'.
        '<tr valign="top">'.
          '<th scope="row"><label for="balance">'.__('Show balance of previous periods', $this->domain).': </label></th>'.
          '<td><select id="balance" name="balance">';
      foreach ($balance as $key => $value) {
        $selected = (get_option('wpf_balance', 'no') == $key)?' selected="selected"':'';
        $output .= '<option value="'.$key.'"'.$selected.'>'.$value.'</option>';        
      }            
    $output .= '</select></td>'.
        '</tr>'.
      '</tbody>'.
      '</table>';
      
      $output .= '<h3>'.__('Role settings', $this->domain).'</h3>';
        
      $output .= '<table class="form-table"><tbody>'.
      
      '<tr valign="top">'.
          '<th scope="row"><label for="accounting">'.__('Accounting', $this->domain).': </label></th>'.
          '<td><select id="accounting" name="accounting">';
      foreach ($accounting as $key => $value) {
        $selected = (get_option('wpf_accounting', 'single') == $key)?' selected="selected"':'';
        $output .= '<option value="'.$key.'"'.$selected.'>'.$value.'</option>';        
      }
      $output .= '</select></td>'.
      '</tr>'.
      
      '<tr valign="top">'.
        '<th scope="row"><label for="roles">'.__('Role premissions', $this->domain).': </label></th>'.
        '<td>'.
          '<fieldset>';
          
          foreach ($roles as $key => $value) {
            $administrator = ($key == 'administrator')?' checked="checked" disabled="disabled"':'';
            $role = (get_option('wpf_role_'.$key, '0') == 1 && $key != 'administrator')?' checked':'';
            $output .= '<label>'.
              '<input type="checkbox" name="role[]" value="'.$key.'"'.$role.$administrator.'>'.
              ' '.$value['name'].
              '</label><br/>';
          }
          
      $output .= '</fieldset>'.
        '<p class="description">'.__('Allows selected roles to view and edit financial statements.', $this->domain).'</p>'.
        '</td>'.
      '</tr>'.

      '<tr valign="top">'.
          '<th scope="row"><label for="username">'.__('Show username in overview', $this->domain).': </label></th>'.
          '<td><select id="username" name="username">';
          foreach ($username as $key => $value) {
            $selected = (get_option('wpf_username', 'no') == $key)?' selected="selected"':'';
            $output .= '<option value="'.$key.'"'.$selected.'>'.$value.'</option>';        
          }            
          $output .= '</select></td>'.
      '</tr>'.
      '</tbody>'.
      '</table>';
      
      $output .= '<h3>'.__('Printing settings', $this->domain).'</h3>';
        
      $output .= '<table class="form-table"><tbody>'.
      
      '<tr valign="top">'.
          '<th scope="row"><label for="format">'.__('Report format', $this->domain).': </label></th>'.
          '<td><select id="format" name="format">';
      foreach ($format as $key => $value) {
        $selected = (get_option('wpf_format', 'standard') == $key)?' selected="selected"':'';
        $output .= '<option value="'.$key.'"'.$selected.'>'.$value.'</option>';        
      }
      $output .= '</select></td>'.
      '</tr>'.

      '<tr valign="top">'.
          '<th scope="row"><label for="print_description">'.__('Include description', $this->domain).': </label></th>'.
          '<td><select id="print_description" name="print_description">';
      foreach ($print_description as $key => $value) {
        $selected = (get_option('wpf_print_description', 'no') == $key)?' selected="selected"':'';
        $output .= '<option value="'.$key.'"'.$selected.'>'.$value.'</option>';        
      }
      $output .= '</select></td>'.
      '</tr>'.

      '<tr valign="top">'.
          '<th scope="row"><label for="print_username">'.__('Include username', $this->domain).': </label></th>'.
          '<td><select id="print_username" name="print_username">';
      foreach ($print_description as $key => $value) {
        $selected = (get_option('wpf_print_username', 'no') == $key)?' selected="selected"':'';
        $output .= '<option value="'.$key.'"'.$selected.'>'.$value.'</option>';        
      }
      $output .= '</select></td>'.
      '</tr>'.
      
      '<tr><td colspan="2">'.
        '<input type="submit" value="'.__('Save Settings', $this->domain).'" class="button-primary"/> '.
        '<a href="'.$url.'" title="'.__('Cancel', $this->domain).'" class="button-secondary">'.__('Cancel', $this->domain).'</a>'.
      '</td></tr>'.
      '</table>'.
    '</form>';

    return $output;
  }
}