<?php
/*
Plugin Name: simpleSAMLphp Authentication
Version: 0.2.1
Plugin URI: http://grid.ie/wiki/WordPress_simpleSAMLphp_authentication
Description: Authenticate users using <a href="http://rnd.feide.no/simplesamlphp">simpleSAMLphp</a>.
Author: David O'Callaghan
Author URI: http://www.cs.tcd.ie/David.OCallaghan/
*/

/* Copyright (C) 2009 David O'Callaghan (david.ocallaghan {} cs <> tcd <> ie)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA */


add_action('admin_menu', 'simplesaml_authentication_add_options_page');

$simplesaml_authentication_opt = get_option('simplesaml_authentication_options');

$simplesaml_configured = true;

// try to configure the simpleSAMLphp client
if ($simplesaml_authentication_opt['include_path'] == '') {
  $simplesaml_configured = false;
} else { 
  $include_file = $simplesaml_authentication_opt['include_path']."/www/_include.php";
  if (!include_once($include_file))
    $simplesaml_configured = false;
}

if ($simplesaml_configured)
  $config = SimpleSAML_Configuration::getInstance();

// for wp_create_user function on line 120
require_once (ABSPATH . WPINC . '/registration.php');

// plugin hooks into authentication system
add_action('wp_authenticate', array('SimpleSAMLAuthentication', 'authenticate'), 10, 2);
add_action('wp_logout', array('SimpleSAMLAuthentication', 'logout'));
add_action('lost_password', array('SimpleSAMLAuthentication', 'disable_function'));
add_action('retrieve_password', array('SimpleSAMLAuthentication', 'disable_function'));
add_action('password_reset', array('SimpleSAMLAuthentication', 'disable_function'));
add_filter('show_password_fields', array('SimpleSAMLAuthentication', 'show_password_fields'));

if (!class_exists('SimpleSAMLAuthentication')) {
  class SimpleSAMLAuthentication {

    // password used by the plugin
    function passwordRoot() {
      return 'Authenticated through SimpleSAML';
    }    
    
    /*
     We call simpleSAMLphp to authenticate the user at the appropriate time 
     If the user has not logged in previously, we create an accout for them
    */
    function authenticate(&$username, &$password) {
      global $simplesaml_authentication_opt, $simplesaml_configured, $config;

      if (!$simplesaml_configured)
        die("simplesaml-authentication plugin not configured");

      // Reset values from input ($_POST and $_COOKIE)
      $username = $password = '';		
      
      $session = SimpleSAML_Session::getInstance();
      $sp_type = $simplesaml_authentication_opt['sp_type'];
	
      /* Check if valid local session exists. */
      if (!$session->isValid($sp_type) ) {
        /* Redirect to the IdP for authentication. */
	      SimpleSAML_Utilities::redirect(
            '/' . $config->getBaseURL() . $sp_type . '/sp/initSSO.php',
            array('RelayState' => SimpleSAML_Utilities::selfURL())
            );
      }

      if (!$session->isAuthenticated() ) {
        exit();
      }

      $attributes = $session->getAttributes();
      
      $username = $attributes['uid'][0];
      $password = md5(SimpleSAMLAuthentication::passwordRoot());

      if (!function_exists('get_userdatabylogin'))
        die("Could not load user data");
      $user = get_userdatabylogin($username);

      if ($user) {
        // user already exists
        return true;
      } else {
        // first time logging in

        if ($simplesaml_authentication_opt['new_user'] == 1) {
          // auto-registration is enabled

          // User is not in the WordPress database
          // they passed SimpleSAML and so are authorized
          // add them to the database

          // User must have an email address to register
          $user_email = '';
          if($attributes['email']) {
            // Try to get email address from attributes
            $user_email = $attributes['email'][0];
          } else {
            // Otherwise use default email suffix
            if ($simplesaml_authentication_opt['email_suffix'] != '')
              $user_email = $username . '@' . $simplesaml_authentication_opt['email_suffix'];
          }

          $user_info = array();
          $user_info['user_login'] = $username;
          $user_info['user_pass'] = $password;
          $user_info['user_email'] = $user_email;

          if($attributes['gn'])
            $user_info['first_name'] = $attributes['gn'][0];
          if($attributes['sn'])
            $user_info['last_name'] = $attributes['sn'][0];

          // Set user role based on eduPersonEntitlement
          if($simplesaml_authentication_opt['admin_entitlement'] != '' &&
	     $attributes['eduPersonEntitlement'] &&
             in_array($simplesaml_authentication_opt['admin_entitlement'],
                $attributes['eduPersonEntitlement'])) {
            $user_info['role'] = "administrator";
          }

          $wp_uid = wp_insert_user($user_info);
          // Update user to set role: it cannot be set when inserting new user.
          wp_update_user(array('ID'=>$wp_uid, 'role'=>$user_info['role']));
        }

        else {
          // auto-registration is disabled

          $error = sprintf(__('<p><strong>ERROR</strong>: %s is not registered with this blog. Please contact the <a href="mailto:%s">blog administrator</a> to create a new account!</p>'), $username, get_option('admin_email'));
          $errors['registerfail'] = $error;
          print($error);
          print('<p><a href="/wp-login.php?action=logout">Log out</a> of SimpleSAML.</p>');
          exit();
        }
      }
    }
    
    
    function logout() {
      global $simplesaml_authentication_opt, $simplesaml_configured, $config;
      if (!$simplesaml_configured)
        die("simplesaml-authentication not configured");

      $sp_type = $simplesaml_authentication_opt['sp_type'];
	
      SimpleSAML_Utilities::redirect(
          '/' . $config->getBaseURL() . $sp_type . '/sp/initSLO.php',
          array('RelayState' => wp_get_referer()
		)
          );
    }
    
    /*
     Don't show password fields on user profile page.
    */
    function show_password_fields($show_password_fields) {
      return false;
    }
    
    
    function disable_function() {
      die('Disabled');
    }
    
  }
 }

//----------------------------------------------------------------------------
//		ADMIN OPTION PAGE FUNCTIONS
//----------------------------------------------------------------------------

function simplesaml_authentication_add_options_page() {
  if (function_exists('add_options_page')) {
    add_options_page('simpleSAMLphp Authentication', 'simpleSAMLphp Authentication', 8, basename(__FILE__), 'simplesaml_authentication_options_page');
  }
} 

function simplesaml_authentication_options_page() {
  global $wpdb;
  
  // Setup Default Options Array
  $optionarray_def = array(
			   'new_user' => FALSE,
			   'redirect_url' => '',
			   'email_suffix' => 'example.com',
			   'sp_type' => 'saml2',
			   'include_path' => '/var/simplesamlphp',
			   'admin_entitlement' => '',
			   );
  
  if (isset($_POST['submit']) ) {    
    // Options Array Update
    $optionarray_update = array (
				 'new_user' => $_POST['new_user'],
				 'redirect_url' => $_POST['redirect_url'],
				 'email_suffix' => $_POST['email_suffix'],
				 'include_path' => $_POST['include_path'],
				 'sp_type' => $_POST['sp_type'],
				 'admin_entitlement' => $_POST['admin_entitlement'],
				 );
    
    
    update_option('simplesaml_authentication_options', $optionarray_update);
  }
  
  // Get Options
  $optionarray_def = get_option('simplesaml_authentication_options');
  
  ?>
	<div class="wrap">
	<h2>simpleSAMLphp Authentication Options</h2>
	<form method="post" action="<?php echo $_SERVER['PHP_SELF'] . '?page=' . basename(__FILE__); ?>&updated=true">
	<fieldset class="options">

     <h3>User registration options</h3>

	<table class="form-table">
	   <tr valign="top">
		<th scope="row">User registration</th>
		<td><label for="new_user">
		<input name="new_user" type="checkbox" id="new_user_inp" value="1" <?php checked('1', $optionarray_def['new_user']); ?> />
Automatically register new users</label>
		<span class="setting-description">(Users will be registered with the role of Subscriber.)</span></td>
		</tr>
<!--		<tr>
		<th><label for="email_suffix"> Default email domain</label></th>
		<td>
	   	<input type="text" name="email_suffix" id="email_suffix_inp" value="<?php echo $optionarray_def['email_suffix']; ?>" size="35" />
		<span class="setting-description">If an email address is not availble from the <acronym title="Identity Provider">IdP</acronym> <strong>username@domain</strong> will be used.</td>
</tr>-->
		<tr>
		<th> <label for="admin_entitlement">Administrator Entitlement URI</label></th>
		<td>
		<input type="text" name="admin_entitlement" id="admin_entitlement_inp" value="<?php echo $optionarray_def['admin_entitlement']; ?>" size="40" />
		<span class="setting-description">An <a href="http://rnd.feide.no/node/1022">eduPersonEntitlement</a> URI to be mapped to the Administrator role.</span>
		</td>
		</tr>
	</table>

    <h3>simpleSAMLphp options</h3>
    <p><em>Note:</em> Once you fill in these options, WordPress authentication will happen through <a href="http://rnd.feide.no/simplesamlphp">simpleSAMLphp</a>, even if you misconfigure it. To avoid being locked out of WordPress, use a second browser to check your settings before you end this session as Administrator. If you get an error in the other browser, correct your settings here. If you can not resolve the issue, disable this plug-in.</p>

	<table class="form-table">
	   <tr valign="top">
		<th scope="row"><label for="include_path">Path to simpleSAMLphp</label></th>
		<td><input type="text" name="include_path" id="include_path_inp" value="<?php echo $optionarray_def['include_path']; ?>" size="35" />
		<span class="setting-description">simpleSAMLphp suggested location is <tt>/var/simplesamlphp</tt>.</span> 
		</td>
		</tr>
    
	   <tr valign="top">
	   <th scope="row"><label for="sp_type">Service provider type</label></th> 
	   <td><select name="sp_type" id="sp_type_inp">
            	<option value="saml2" <?php echo ($optionarray_def['sp_type'] == 'saml2')?'selected':''; ?>>SAML 2.0</option>
            	<option value="shib13" <?php echo ($optionarray_def['sp_type'] == 'shib13')?'selected':''; ?>>Shibboleth 1.3</option>
		</select>
		<span class="setting-description">simpleSAMLphp default is SAML 2.0.</span> 
             </td>
	     </tr>
	</table>
	</fieldset>
	<p />
	<div class="submit">
		<input type="submit" name="submit" value="<?php _e('Update Options') ?> &raquo;" />
	</div>
	</form>
<?php
}
?>
