=== Plugin Name ===
Contributors: davidoc, fkooman
Tags: authentication, saml, simpleSAMLphp
Requires at least: 3.0.0
Tested up to: 3.1.3
Stable tag: 0.5.1

Authenticate users using simpleSAMLphp (http://rnd.feide.no/simplesamlphp).

== Description ==

simpleSAMLphp is a simple application written in native PHP that deals with
authentication. SimpleSAMLphp supports several federation protocols,
authentication mechanisms and can be used both for local authentication, as a
service provider or as an identity provider

This plugin uses some hooks in WordPress's authentication system to bypass the
normal login screen and authenticate using a simpleSAMLphp Service Provider
(SP) instead.  Note that logged-in state is still maintained in cookies, and
user entries are created in the local database.


== Installation ==

1. Download <a href="http://rnd.feide.no/simplesamlphp">simpleSAMLphp</a> version 1.5 or higher on your web server and configure it <a href="http://rnd.feide.no/content/using-simplesamlphp-service-provider" title="Using simpleSAMLphp as a service provider - Feide RnD">as a service provider</a>.
2. Upload simplesaml-authentication.php to the wp-content/plugins/ directory of your WordPress installation.
3. Log in as administrator and activate the plugin.  Go to the Options tab and configure the plugin. If applicable, configure an eduPersonEntitlement that will be mapped to the Administrator role. STAY LOGGED IN to your original administrator account.  You won't be able to log back in once you log out.
4. Open a different browser, or on another computer.  Log in to your blog to make sure that it works.
5. In the first browser window, make the newly created user an Administrator.  You can log out now. (Alternately, you can change some entries in the wp_usermeta table to make a new user the admin)
6. Disable Options -> General -> Anyone can register (they won't be able to)
7. Make sure you enable administration over SSL (see http://codex.wordpress.org/Administration_Over_SSL)

== Frequently Asked Questions ==

= What version of simpleSAMLphp is needed? =
Starting from version 0.3.0 the plugin requires simpleSAMLphp 1.5 or higher. Use version 0.2.x of this plugin for simpleSAMLphp < 1.5 support.

== Changelog ==

= 0.5.1 =
* Tested up to 3.1.4 alpha (svn18146)
* Tested with simpleSAMLphp 1.8

= 0.5.0 = 
* Upgrade plugin to support Wordpress 3.1
* Tested with simpleSAMLphp 1.7
* Fix logout, returns to home page now
* Modify attributes to map with default LDAP attributes (for differerent attribute names please use (or update!) the attibute mapping in the simpleSAMLphp SP configuration)

= 0.4.0 =
* Make it work again with latest WP (thanks to Ivo Jansch)

= 0.3.0 =
* Use simpleSAMLphp 1.5 API

= 0.2.1 =

== Upgrade Notice ==
Version 0.5.0 modified the preconfigured attributes in this plugin and now uses default LDAP attribute names. Please use the simpleSAMLphp configuration to 
match the SAML attributes to the default LDAP attributes to keep it working.

== Who made this? ==

Thanks to <a href="http://wordpress.org/extend/plugins/profile/sms225">Stephen
Schwink</a> who developed the the <a
href="http://wordpress.org/extend/plugins/cas-authentication/">CAS
Authentication</a> plugin on which this plugin is heavily based.
