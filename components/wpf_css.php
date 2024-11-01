<?php
class wpf_css {

  private $identifier = "css";
  private $title = "CSS Editor";
  private $permissions = array('administrator');
  private $order = 40;
  private $hook, $domain;

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
    $this->title = __("CSS Editor", $this->domain);
  }

  // Views ---------------------------------------------------------------------

  public function viewIndex($action = '')
  {
    $url = 'admin.php?page='.$_GET['page'];
    $url_redirect = 'admin.php?page=wpfinance&menu=css';
    $output = '';
    $custom_file = dirname(__FILE__).'/../css/custom.css';
    
    if ($action == 'save') {
      /* Save custom CSS */
      $fp = fopen($custom_file, 'w');
      fwrite($fp, $_REQUEST['stylesheet']);
      fclose($fp);      

      $check_css = (preg_match("/^(custom|default|light)$/sim", $_REQUEST['css']))?1:0;
      if ($check_css) {
        update_option('wpf_css', $_REQUEST['css']);
      }
      
      update_option('wpf_message', 'updated:'.__('CSS stylesheets were updated successfully.', $this->domain));
      wp_redirect(admin_url($url_redirect), 301);
    }
    
    $css = array(
      'custom' => __('Custom', $this->domain),  // default
      'default' => __('Default', $this->domain),
      'light' => __('Extra Light', $this->domain)
    );
  
    $custom_css = (file_exists($custom_file))?file_get_contents($custom_file):'';
     
    $output .=
      '<form method="POST" action="'.admin_url($url.'&menu=css/index/save&noheader=true').'">'.
      '<table class="form-table"><tbody>'.
        '<tr valign="top">'.
          '<th scope="row"><label for="css">'.__('CSS Theme', $this->domain).': </label></th>'.
            '<td><select id="css" name="css">';
            foreach($css as $key => $value) {
              $selected = (get_option('wpf_css', 'custom') == $key)?' selected="selected"':'';
              $output .= '<option value="'.$key.'"'.$selected.'>'.$value.'</option>';
            }
            $output .= '</select><p class="description">To use your custom style, please, select Custom as CSS theme and add your stylesheet code in the box bellow.</p></td>'.
        '</tr>'.
        '<tr valign="top">'.
          '<th scope="row"><label for="stylesheet">'.__('Custom stylesheet', $this->domain).': </label></th>'.
          '<td><textarea id="stylesheet" name="stylesheet" class="large-text code" style="height: 350px;">'.$custom_css.'</textarea></td>'.
        '</tr>'.
        '<tr><td colspan="2">'.
          '<input type="submit" value="'.__('Save', $this->domain).'" class="button-primary"/> '.
        '</td></tr>'.
      '</table>'.
      '</form>';
    
    return $output;
  }
}
?>