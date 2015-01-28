<?php

class StopXmlRpcAttackTest extends WP_UnitTestCase {

	/**
	 * FILTER METHODS WE NEED FOR TESTING:
	 */

	function returnEmptyArrayFilter($data) {
		return array();
	}

	/**
	 * THE TESTS
	 */

	function testClassIsInstanciated() {
		$this->assertTrue(is_object($GLOBALS['plugin_stop_xmlrpc_attack']));
	}

	function testHtaccessPath() {
		global $plugin_stop_xmlrpc_attack;
		$this->assertEquals(rtrim(ABSPATH, '/') . '/.htaccess', $plugin_stop_xmlrpc_attack->htaccessFile);
	}

	function testWritingToHtaccessAndThenRemovingFromHtaccess() {
		global $plugin_stop_xmlrpc_attack;
		if (!file_exists($plugin_stop_xmlrpc_attack->htaccessFile)) touch($plugin_stop_xmlrpc_attack->htaccessFile);
		add_filter('stop_xmlrpc_attack_whitelist_arin_organizations', array($this, 'returnEmptyArrayFilter'));
		do_action( 'stop_xmlrpc_attack_remove_htaccess' );
		$data = file_get_contents($plugin_stop_xmlrpc_attack->htaccessFile);
		$this->assertFalse(strpos(file_get_contents($plugin_stop_xmlrpc_attack->htaccessFile), '# BEGIN WORDPRESS PLUGIN stop_xmlrpc_attack'));
		$this->assertFalse(strpos(file_get_contents($plugin_stop_xmlrpc_attack->htaccessFile), '# END WORDPRESS PLUGIN stop_xmlrpc_attack'));
		do_action( 'stop_xmlrpc_attack_generate_htaccess' );
		$this->assertNotFalse(strpos(file_get_contents($plugin_stop_xmlrpc_attack->htaccessFile), '# BEGIN WORDPRESS PLUGIN stop_xmlrpc_attack'));
		$this->assertNotFalse(strpos(file_get_contents($plugin_stop_xmlrpc_attack->htaccessFile), '# END WORDPRESS PLUGIN stop_xmlrpc_attack'));
		do_action( 'stop_xmlrpc_attack_remove_htaccess' );
		$data = file_get_contents($plugin_stop_xmlrpc_attack->htaccessFile);
		$this->assertFalse(strpos(file_get_contents($plugin_stop_xmlrpc_attack->htaccessFile), '# BEGIN WORDPRESS PLUGIN stop_xmlrpc_attack'));
		$this->assertFalse(strpos(file_get_contents($plugin_stop_xmlrpc_attack->htaccessFile), '# END WORDPRESS PLUGIN stop_xmlrpc_attack'));
		remove_filter('stop_xmlrpc_attack_whitelist_arin_organizations', array($this, 'returnEmptyArrayFilter'));
	}

	function testRange2CIDR() {

		$this->assertEquals($GLOBALS['plugin_stop_xmlrpc_attack']->range2cidr('66.155.38.0', '66.155.38.255'), '66.155.38.0/24'); // test a /24 net.
		$this->assertEquals($GLOBALS['plugin_stop_xmlrpc_attack']->range2cidr('66.135.48.128', '66.135.48.255'), '66.135.48.128/25'); // test a /25 net.
		$this->assertEquals($GLOBALS['plugin_stop_xmlrpc_attack']->range2cidr('216.151.209.64', '216.151.209.127'), '216.151.209.64/26'); // test a /26 net.

	}

}

