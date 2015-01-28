<?php
/**
Plugin Name: Stop XML-RPC Attack
Plugin URI: http://wordpress.org/extend/plugins/stop-xmlrpc-attack/
Description: Plugin for blocking access to xmlrpc.php.
Version: 1.0.2
Author: alfreddatakillen
Author URI: http://nurd.nu/
License: GPLv3
 */

class Plugin_Stop_Xmlrpc_Attack {

/**

	TODO:

	* Support blocking more things than xml-rpc.
	* Support RIPE, APNIC, LACNIC and AFRINIC.
	* Support AS-numbers.
	* Support IPv6.


*/

	public $htaccessFile;
	private $beginHtaccessBlock = '# BEGIN WORDPRESS PLUGIN stop_xmlrpc_attack';
	private $endHtaccessBlock = '# END WORDPRESS PLUGIN stop_xmlrpc_attack';

	function __construct() {
		register_activation_hook( __FILE__, array( 'Plugin_Stop_Xmlrpc_Attack', 'activated_plugin' ) );
		register_deactivation_hook( __FILE__, array( 'Plugin_Stop_Xmlrpc_Attack', 'deactivated_plugin' ) );
		add_action('stop_xmlrpc_attack_generate_htaccess', array($this, 'generate_htaccess'));
		add_action('stop_xmlrpc_attack_remove_htaccess', array($this, 'remove_htaccess'));
		add_action('stop_xmlrpc_attack_cron', array($this, 'cron'));
		add_action('stop_xmlrpc_attack_flush_cache', array($this, 'flush_cache'));

		add_action('init', array($this, 'init'));
		add_action('init', array($this, 'admin_init'));
		add_action( 'admin_notices', array($this, 'admin_notice') );
		add_action( 'network_admin_notices', array($this, 'admin_notice') );

	}

	function init() {
		$this->htaccessFile = rtrim(ABSPATH, '/') . '/.htaccess';
		$this->beginHtaccessBlock = apply_filters('stop_xmlrpc_attack_begin_block', $this->beginHtaccessBlock);
		$this->endHtaccessBlock = apply_filters('stop_xmlrpc_attack_end_block', $this->endHtaccessBlock);
	}


	/**
	 * ACTIVATE/DEACTIVATE PLUGIN
	 */

	public static function activated_plugin() {
		do_action('stop_xmlrpc_attack_generate_htaccess');
		wp_schedule_event( time(), 'hourly', 'stop_xmlrpc_attack_cron' );
	}

	public static function deactivated_plugin() {
		wp_clear_scheduled_hook( 'stop_xmlrpc_attack_cron' );
		do_action('stop_xmlrpc_attack_remove_htaccess');
	}

	/**
	 * CRON YADA YADA
	 */

	function cron() {
		do_action('stop_xmlrpc_attack_generate_htaccess');
	}

	/**
	 * ADMIN PAGE YADA YADA
	 */

	function admin_init() {
		if ($this->is_trusted()) {
			if (is_multisite()) {
				add_action('network_admin_menu', array($this, 'network_admin_menu'));
			} else {
				add_action('admin_menu', array($this, 'admin_menu')); // Will add the settings menu.
			}
			add_action('wp_ajax_stop_xmlrpc_attack_regenerate', array($this, 'admin_post')); // Gets called from plugin admin page POST request.
		}
	}

	function admin_notice() {
		$err = get_site_option('stop_xmlrpc_attack_error', '');
		if ($err === '') return;
		?>
			<div class="error">
				<p><strong>Stop XML-RPC Attack Plugin Error:</strong> <?php echo($err); ?></p>
			</div>
		<?php
	}

	function admin_post() {
		do_action('stop_xmlrpc_attack_flush_cache');
		do_action('stop_xmlrpc_attack_generate_htaccess');
                if (is_multisite()) {
                        $url = admin_url('network/settings.php?page=stop_xmlrpc_attack&updated=true');
                } else {
                        $url = admin_url('options-general.php?page=stop_xmlrpc_attack&updated=true');
                }
                header('Location: ' . $url);
                exit();
	}

	function admin_menu() {
		if ($this->is_trusted()) {
			add_options_page('Stop XML-RPC Attack', 'Stop XML-RPC Attack', 'manage_options', 'stop_xmlrpc_attack', array($this, 'admin_form'));
		}
	}

	function is_trusted() {
		if (is_multisite()) {
			if (is_super_admin()) {
				return true;
			}
		} else {
			if (current_user_can('manage_options')) {
				return true;
			}
		}
		return false;
	}


	function network_admin_menu() {
		if ($this->is_trusted()) {
			add_submenu_page('settings.php', 'Stop XML-RPC Attack', 'Stop XML-RPC Attack', 'manage_options', 'stop_xmlrpc_attack', array($this, 'admin_form'));
		}
	}

	function admin_form() {
		if (!$this->is_trusted()) return false;
		?>
			<div class="wrap wpro-admin">
				<form method="post" action="<?php echo(admin_url('admin-ajax.php')); ?>">
					<h2>Stop XML-RPC Attack</h2>
					<input type="hidden" name="action" value="stop_xmlrpc_attack_regenerate" />
					<p>
						Daily, this plugin will poll ARIN and update your .htaccess.<br />
						To force a poll and update now, push this button.
					</p>
					<p class="submit">
						<input type="submit" name="submit" id="submit" class="button button-primary" value="Re-generate .htaccess now!">
					</p>

				</form>
			</div>
		<?php
	}

	/**
	 * THE VERY CORE FUNCTIONALITY BELOW
	 */

	function get_arin_organization_data($organization) {
		$data = get_site_transient('stop_xmlrpc_attack;arin_org;' . $organization);
		if (!is_array($data)) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 'http://whois.arin.net/rest/org/' . $organization . '/nets.json');
			curl_setopt($ch, CURLOPT_USERAGENT, 'stop-xmlrpc-attack - https://github.com/alfreddatakillen/stop-xmlrpc-attack');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($ch, CURLOPT_TIMEOUT, 5);
			$data = curl_exec($ch);
			$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			if ($httpcode >= 200 && $httpcode < 300) {
				$data = json_decode($data, true);
				if (!is_array($data)) {
					$data = array();
				}
			} else {
				$data = array();
			}
			$transient_expire = 60 * 60 * 24; // One day;
			if (count($data) == 0) {
				$transient_expire = 60 * 15; // 15 minutes
			}
			set_site_transient('stop_xmlrpc_attack;arin_org;' . $organization, $data, $transient_expire);
		}
		return $data;
	}

	function flush_cache() {
		$arin_orgs = apply_filters('stop_xmlrpc_attack_whitelist_arin_organizations', array('AUTOM-93'));
		foreach ($arin_orgs as $org) {
			delete_site_transient('stop_xmlrpc_attack;arin_org;' . $org);
		}
	}

	function get_current_htaccess() {
		$htaccess = array ('before' => '', 'block' => '', 'after' => '');
		$key = 'before';
		foreach (explode("\n", @file_get_contents($this->htaccessFile)) as $row) {
			if ($row == trim($this->beginHtaccessBlock)) {
				$key = 'block';
				$htaccess['block'] = '';
				continue;
			} else if ($row == trim($this->endHtaccessBlock)) {
				$key = 'after';
				$htaccess['after'] = '';
				continue;
			} else {
				$htaccess[$key] .= $row . "\n";
			}
		}

		// Our block should be wrapped with one empty row, before and after:
		$htaccess['before'] = trim($htaccess['before']);
		$htaccess['block'] = trim($htaccess['block']);
		$htaccess['after'] = trim($htaccess['after']);
		if ($htaccess['before'] !== '') {
			$htaccess['before'] .= "\n\n";
		}
		if ($htaccess['after'] !== '') {
			$htaccess['after'] = "\n" . $htaccess['after'];
		}
		return $htaccess;
	}

	function remove_htaccess() {
		$htaccess = $this->get_current_htaccess();
		if (trim($htaccess['block']) == '') return true; // Do nothing. There was no block in the .htaccess file.

		$newHtaccess = $htaccess['before'] . "\n\n" . $htaccess['after'] . "\n";
		$this->write_to_htaccess($newHtaccess);
	}

	function generate_htaccess() {
		$files = apply_filters('stop_xmlrpc_attack_on_file', array('xmlrpc.php'));
		$arin_orgs = apply_filters('stop_xmlrpc_attack_whitelist_arin_organizations', array('AUTOM-93'));
		$cidr = array('10.0.0.0/8', '127.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'); // Hard coded local nets and loopback.
		
		foreach ($arin_orgs as $arin_org) {
			$data = $this->get_arin_organization_data($arin_org);
			if (isset($data['nets']) && isset($data['nets']['netRef'])) {
				foreach ($data['nets']['netRef'] as $subnet) {
					$start = $subnet['@startAddress'];
					$end = $subnet['@endAddress'];
					if ($this->is_ipv4addr($start) && $this->is_ipv4addr($end)) {
						$c = $this->range2cidr($start, $end);
						if (!is_null($cidr)) {
							$cidr[] = $c;
						}
					}
				}
			}
		}

		natsort($cidr);
		$cidr = apply_filters('stop_xmlrpc_attack_whitelist_cidrs', array_values($cidr));

		$htaccess = $this->get_current_htaccess();

		// Old block
		$oldHtaccessBlock = $htaccess['block'];
		
		// Generate current block as it should be
		$newHtaccessBlock = '';
		foreach ($files as $file) {
			$newHtaccessBlock .= '<Files "' . $file . '">' . "\n";
			$newHtaccessBlock .= "order deny,allow\n";
			$newHtaccessBlock .= "deny from all\n";
			foreach ($cidr as $c) {
				$newHtaccessBlock .= 'allow from ' . $c . "\n";
			}
			$newHtaccessBlock .= "</Files>";
		}

		$newHtaccessBlock = trim(apply_filters('stop_xmlrpc_attack_htaccess_block', $newHtaccessBlock));

		// Overwrite .htaccess if old and new block does not match.
		if ($newHtaccessBlock !== $oldHtaccessBlock) {
			$newHtaccess = $htaccess['before'] . $this->beginHtaccessBlock . "\n" . $newHtaccessBlock . "\n" . $this->endHtaccessBlock . "\n" . $htaccess['after'] . "\n";
			$this->write_to_htaccess($newHtaccess);
		}
	}

	function write_to_htaccess($data) {
		if (@file_put_contents($this->htaccessFile, $data) === false) {
			update_site_option('stop_xmlrpc_attack_error', 'Could not write to .htaccess file. Check your file permissions and then re-generate your .htaccess from the plugin settings page.');
		} else {
			update_site_option('stop_xmlrpc_attack_error', '');
		}
	}

	function is_ipv4addr($addr) {
		if (filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) return false;
		return true;
	}

	/**
	 * SUBNET / NETMASK / CIDR / IP RANGE CALCULATION:
	 */

	function range2cidr($begin, $end) {
		if (!$this->is_ipv4addr($begin) || !$this->is_ipv4addr($end)) {
			return NULL;
		}

		$start_long = (int)ip2long($begin);
		$end_long = (int)ip2long($end);

		if ($start_long > 0 && $end_long < 0) {
			$delta = ($end_long + 4294967296) - $start_long;
		} else {
			$delta = $end_long - $start_long;
		}

		$netmask = str_pad(decbin($delta), 32, '0', STR_PAD_LEFT);

		if (ip2long($begin) == 0 && substr_count($netmask, '1') == 32) return '0.0.0.0/0';

		if ($delta < 0 || ($delta > 0 && $delta % 2 == 0)) return NULL;

		for ($mask = 0; $mask < 32; $mask++) {
			if ($netmask[$mask] == 1) {
				break;
			}
		}
		if (substr_count($netmask, '0') != $mask) {
			return NULL;
		}
		return $begin . '/' . $mask;
	}

	function cidr2range($cidr) {
		$start = strtok($cidr, '/');
		$n = 3 - substr_count($cidr, '.');
		if ($n > 0) {
			for ($i = $n; $i > 0; $i--) {
				$start .= '.0';
			}
		}
		$bits1 = str_pad(decbin(ip2long($start)), 32, '0', STR_PAD_LEFT);
		$cidr = pow(2, (32 - substr(strstr($cidr, '/'), 1))) - 1;
		$bits2 = str_pad(decbin($cidr), 32, '0', STR_PAD_LEFT);
		for ($i = 0; $i < 32; $i++) {
			if ($bits1[$i] == $bits2[$i]) {
				$end .= $bits1[$i];
			}
			if ($bits1[$i] == 1 && $bits2[$i] == 0) {
				$end .= $bits1[$i];
			}
			if ($bits1[$i] == 0 && $bits2[$i] == 1) {
				$end .= $bits2[$i];
			}
		}
		return array($start, long2ip(bindec($end)));
	}

}

$GLOBALS['plugin_stop_xmlrpc_attack'] = new Plugin_Stop_Xmlrpc_Attack();

