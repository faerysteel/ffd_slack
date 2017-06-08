<?php
/*
Plugin Name: FFD Slack Login
Plugin URI:  github.com/freeflowdigital/ffds_slack
Description: Enable Wordpress logins via slack
Version:     20170509
Author:      Freeflowdigital.com
Author URI:  https://freeflowdigital.com/
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: wporg
Domain Path: /languages
*/
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

add_action( 'admin_menu', 'ffds_add_admin_menu' );
add_action( 'admin_init', 'ffds_settings_init' );


function ffds_add_admin_menu(  ) {

	add_options_page( 'Slack Login', 'Slack Login', 'manage_options', 'ffd-slack-login', 'ffds_options_page' );

}


function ffds_settings_init(  ) {

	register_setting( 'pluginPage', 'ffds_settings', 'ffds_settings_validate');

	add_settings_section(
		'ffds_pluginPage_section',
		__( 'Slack Application Settings', 'wordpress' ),
		'ffds_settings_section_callback',
		'pluginPage'
	);

	add_settings_field(
		'ffds_client_id',
		__( 'Client ID', 'wordpress' ),
		'ffds_client_id_render',
		'pluginPage',
		'ffds_pluginPage_section'
	);

	add_settings_field(
		'ffds_secret',
		__( 'Client Secret', 'wordpress' ),
		'ffds_secret_render',
		'pluginPage',
		'ffds_pluginPage_section'
	);

	add_settings_field(
		'ffds_register',
		__( 'Allow Registrations', 'wordpress' ),
		'ffds_registration_render',
		'pluginPage',
		'ffds_pluginPage_section'
	);

	add_settings_field(
		'ffds_roles',
		__( 'Add New Users to Role', 'wordpress' ),
		'ffds_roles_render',
		'pluginPage',
		'ffds_pluginPage_section'
	);

}

function ffds_settings_validate($input) {
    /*
    TODO: discover rules to validate client id and client secret
    */
    if(!isset($input['ffds_registration'])){
	    $input['ffds_registration'] = 0;
    }
//    echo print_r($input, true);
    return $input;
}


function ffds_client_id_render(  ) {

	$options = get_option( 'ffds_settings' );
	?>
	<input type='text' name='ffds_settings[ffds_client_id]' value='<?php echo $options['ffds_client_id']; ?>'>
	<?php

}


function ffds_secret_render(  ) {

	$options = get_option( 'ffds_settings' );
	?>
	<input type='text' name='ffds_settings[ffds_secret]' value='<?php echo $options['ffds_secret']; ?>'>
	<?php

}

function ffds_registration_render(  ) {

	$options = get_option( 'ffds_settings' );
	?>
	<input type='checkbox' name='ffds_settings[ffds_registration]' <?php checked( $options['ffds_registration'], 1 ); ?> value='1'>
	<?php

}

function ffds_roles_render() {

	$options = get_option( 'ffds_settings' );
	// set default
	if (!isset($options['ffds_roles'])){
	    $options['ffds_roles'] = get_option('default_role');
    }
    $roles = new WP_Roles;
    $role_list = $roles->get_names();
    ?>
    <ul>
    <?php foreach ($role_list as $role_key => $role){ ?>
        <li><input type='radio' name='ffds_settings[ffds_roles]' id='ffds_roles_<?php echo $role;?>' <?php checked( $options['ffds_roles'], $role_key); ?> value='<?php echo $role_key; ?>'><label for='ffds_roles_<?php echo $role;?>'><?php echo $role;?></label></li>
    <?php    } ?>
    </ul>
    <?php
}



function ffds_settings_section_callback(  ) {

	echo __( 'lorem ipsum', 'wordpress' );

}


function ffds_options_page(  ) {

	?>
	<form action='options.php' method='post'>

		<h2>Login Via Slack</h2>

		<?php
		settings_fields( 'pluginPage' );
		do_settings_sections( 'pluginPage' );
		submit_button();
		?>

	</form>
	<?php

}

$slack  = new FfdSlack();


class FfdSlack {

	const _SLACK_AUTHORIZE_URL = "https://slack.com/oauth/authorize";

	const _SLACK_ACCESS_URL = "https://slack.com/api/oauth.access";

	public $slack_client_id;

	public $slack_secret;

	public $allow_registrations;

	public $register_role;


	public function __construct() {

		//get the slack application options
		$options = get_option('ffds_settings');
		if ($options) {
			$this->slack_client_id      = $options['ffds_client_id'];
			$this->slack_secret         = $options['ffds_secret'];
			if (isset($options['ffds_team'])) {
				$this->slack_team = $options['ffds_team'];
			}
			if (isset($options['ffds_registration'])){
				$this->allow_registrations  = $options['ffds_registration'];
			}
			if (isset($options['ffds_roles'])){
				$this->register_role  = $options['ffds_roles'];
			}

			// Add Login with Slack to login form
			add_action( 'login_form', array( $this, 'display_login_button' ) );

			add_action( 'init', array( $this, 'process_slack' ) );
		}
	}

	public function display_login_button() {

		$url = self::_SLACK_AUTHORIZE_URL . "?scope=identity.basic,identity.email";
        $url .=  "&redirect_uri=" . urlencode(wp_login_url());
		$url .= "&client_id=" . $this->slack_client_id;
		$url .= "&state=slack_login";

		// User is not logged in, display login button
		echo "<a href=\"$url\">
				<img alt=\"Sign in with Slack\" height=\"40\" width=\"172\" src=\"https://platform.slack-edge.com/img/sign_in_with_slack.png\" srcset=\"https://platform.slack-edge.com/img/sign_in_with_slack.png 1x, https://platform.slack-edge.com/img/sign_in_with_slack@2x.png 2x\" />
				</a>";
	}

	public function process_slack(){

		// Dont run our code if not needed
		if (!isset($_REQUEST['state']) || $_REQUEST['state'] != 'slack_login'){
			return false;
		}
		// verify we got a response code
		if (!isset($_REQUEST['code'])){
			return false;
		}
		$code = $_REQUEST['code'];

		if (isset($code)) {
		    // create access URL
            $url = self::_SLACK_ACCESS_URL;
            $url .= '?client_id=' . $this->slack_client_id;
            $url .= '&client_secret=' . $this->slack_secret;
            $url .= '&code=' .$code;
            $url .= '&redirect_uri=' . urlencode(wp_login_url());

			$response_json = wp_remote_retrieve_body( wp_remote_get( $url));
			$response = json_decode($response_json, true);


			$user_id = $this->get_slack_user_id($response, $this->allow_registrations, $this->register_role);
			if($user_id) {
				// Signon user by ID
				wp_set_auth_cookie( $user_id );
			    // Set current WP user so that authentication takes immediate effect without waiting for cookie
				wp_set_current_user( $user_id );
				wp_redirect( admin_url() );
			}
		}
	}

	private function get_slack_user_id($slack_response, $register = FALSE, $role = ''){

		if (TRUE !== $slack_response['ok']){
			return FALSE;
		}
		$user = get_users(array(
			'meta_key' => 'slack_id',
			'meta_value' => $slack_response['user']['id'],
			'number' => 1,
			'count_total' => FALSE,
			'fields' => 'ids',
		));

		if(!$user){
			$user = get_users(array(
				'search' => $slack_response['user']['email'],
				'number' => 1,
				'count_total' => FALSE,
				'fields' => 'ids',
			));
			if ($user){
				//add slack id to meta
				add_user_meta($user[0], 'slack_id', $slack_response['user']['id'], TRUE);
				add_user_meta($user[0], 'slack_team', $slack_response['team']['id'], TRUE);
			}
		}
		if($user) {
			update_user_meta( $user[0], 'slack_token', $slack_response['access_token']);
			return $user[0];
		}
		elseif($register){
			//register the user
			$random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );
			$user_id = wp_create_user( str_replace(' ', '_', $slack_response['user']['name']), $random_password, $slack_response['user']['email'] );
            if (!empty($role)){
                $user = get_user_by('id', $user_id);
                $user->set_role($role);
            }
			add_user_meta($user_id, 'slack_id', $slack_response['user']['id'], TRUE);
			add_user_meta($user_id, 'slack_team', $slack_response['team']['id'], TRUE);
			add_user_meta( $user_id, 'slack_token', $slack_response['access_token'], TRUE );
			return $user_id;

		}
		// registration not allowed and no user found
		return FALSE;
	}
}
