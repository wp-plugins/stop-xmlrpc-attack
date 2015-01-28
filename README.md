# Stop XML-RPC Attack #

**Contributors:** alfreddatakillen  
**Tags:** xmlrpc, ddos, dos, jetpack  
**Requires at least:** 4.0  
**Tested up to:** 4.1  
**Stable tag:** 1.0.1  
**License:** GPLv3 or later  
**License URI:** http://www.gnu.org/licenses/gpl-3.0.html  

Block all access to your xmlrpc.php, except for JetPack and Automattic. Will poll ARIN for Automattic's subnets and update your .htaccess.

## Description ##

Do you get a lot of brute force attacks, DOS/DDOS and spam, targeting the XML-RPC interface in WordPress? You could just block xmlrpc.php access in your .htaccess file, but that will also cause much of Jetpack to stop functioning as expected. Jetpack is based off a two-way communication between your server and Automattic's servers, and that requires your xmlrpc.php to be accessible from Automattic's end.

This WordPress plugin will block access to xmlrpc.php from everywhere, except the JetPack/Automattic's subnets. On a regular basis, the plugin will poll ARIN and update your .htaccess to allow the subnets that belongs to AUTOM-93 (which is Automattic, Inc.).

## Installation ##

Make sure your .htaccess file is writable by the web server. Then just install the plugin, and it will live out of the box.

## Frequently Asked Questions ##

### I can not find the plugin's admin page. ###

In multisite environments, it is in your network admin. You have to be super admin to access it.

### Where do I report bugs, or express my concerns? ###

At [the GitHub issue tracker](https://github.com/alfreddatakillen/stop-xmlrpc-attack/issues "the GitHub issue tracker").

### Where do I contribute with code, bug fixes, etc.? ###

At [GitHub](https://github.com/alfreddatakillen/stop-xmlrpc-attack "GitHub").

And, plz, use tabs for indenting! :)

## Screenshots ##

###1. This is what your .htaccess might look like.###
[missing image]


## Changelog ##

### 1.0.1 ###

Bugfix: Admin page was not visible in network menu.

### 1.0.0 ###

First public release.

## Actions and filters ##

Use the following actions and filters to alter the plugin's functionality.
You can easily allow access for other organizations and IP addresses, using the filters.

### action: stop_xmlrpc_attack_generate_htaccess ###

Trigger this action to generate a new .htaccess file.
This action is triggered at plugin activation, by WordPress cron,
and when pushing the "update" button in admin.

	do_action( 'stop_xmlrpc_attack_generate_htaccess' );


### action: stop_xmlrpc_attack_remove_htaccess ###

Trigger this action to remove our block from the .htaccess file.
This action is triggered at plugin deactivation.

	do_action( 'stop_xmlrpc_attack_remove_htaccess' );


### action: stop_xmlrpc_attack_flush_cache ###

Data from ARIN is cached for 24 hours. This will flush the cache,
forcing fresh data on next .htaccess re-generation.

To force a new .htaccess with the latest ARIN data, do:

	do_action( 'stop_xmlrpc_attack_flush_cache' );
	do_action( 'stop_xmlrpc_attack_generate_htaccess' );

### filter: stop_xmlrpc_attack_on_file ###

Array of files to block in .htaccess. Defaults to array('xmlrpc.php');

### filter: stop_xmlrpc_attack_whitelist_arin_organizations ###

Array of ARIN organizations. Defaults to array('AUTOM-93');

### filter: stop_xmlrpc_attack_whitelist_cidrs ###

Array of all CIDRs to be whitelisted. Defauls to all subnets we got from ARIN, plus loopback (127.0.0.1) and private networks (10.0.0.0/8, 172.16.0.0/12 and 192.168.0.0/16).

### filter: stop_xmlrpc_attack_begin_block ###

The string which marks the beginning of our block in .htaccess.

### filter: stop_xmlrpc_attack_end_block ###

The string which marks the ending of our block in .htaccess.

### filter: stop_xmlrpc_attack_htaccess_block ###

The very content in our .htaccess block.

