<?php
/*
Plugin Name: WordPress Playground
Plugin URI: https://github.com/WordPress/playground-tools/tree/trunk/packages/playground
Description: Packages your WordPress install and sends it to Playground.
Author: WordPress Contributors
Version: 0.0.1
*/

namespace WordPress\Playground;

defined('ABSPATH') || exit;

const PLAYGROUND_ADMIN_PAGE_SLUG = 'playground';
const TRANSLATE_DOMAIN = 'playground';
const ADMIN_PAGE_CAPABILITY = 'manage_options';

global $wp_version;

define('PLAYGROUND_VERSION', $wp_version);
define('PLAYGROUND_WP_VERSION', $wp_version);
define('PLAYGROUND_PHP_VERSION', implode('.', sscanf(phpversion(), '%d.%d')));

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/playground-zip.php';

add_action('admin_menu', __NAMESPACE__ . '\plugin_menu');
add_action('admin_init', __NAMESPACE__ . '\init');
add_action('plugins_loaded', __NAMESPACE__ . '\plugins_loaded');

/**
 * Initialize the plugin.
 */
function init()
{
	add_action('admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_scripts');
	add_filter('plugin_install_action_links', __NAMESPACE__ . '\plugin_install_action_links', 10, 2);
}

/**
 * Enqueue scripts and styles for the plugin.
 *
 * @param string $current_screen_id The current screen ID.
 */
function enqueue_scripts($current_screen_id)
{
	if ($current_screen_id !== 'tools_page_' . PLAYGROUND_ADMIN_PAGE_SLUG) {
		return;
	}
	wp_enqueue_style('playground', plugin_dir_url(__FILE__) . 'assets/css/playground.css', [], PLAYGROUND_VERSION);
	wp_register_script('playground', plugin_dir_url(__FILE__) . 'assets/js/playground.js', [], PLAYGROUND_VERSION, true);
	wp_localize_script('playground', 'playground', [
		'zipUrl' => esc_url(get_download_page_url()),
		'wpVersion' => PLAYGROUND_WP_VERSION,
		'phpVersion' => PLAYGROUND_PHP_VERSION,
		'playgroundPackageUrl' => apply_filters(
			'playground_package_url',
			esc_url('https://playground.wordpress.net/client/index.js'),
		),
		'playgroundRemoteUrl' => apply_filters(
			'playground_remote_url',
			esc_url('https://playground.wordpress.net/remote.html'),
		),
		'pluginSlug' => isset($_GET['pluginSlug']) ? $_GET['pluginSlug'] : false,
	]);
	wp_enqueue_script('playground');
}

/**
 * Get the URL to download the playground package.
 *
 * @return string The URL to download the playground package.
 */
function get_download_page_url()
{
	return add_query_arg(
		[
			'download' => 1,
		],
		admin_url('admin.php?page=' . PLAYGROUND_ADMIN_PAGE_SLUG)
	);
}

/**
 * Collect the WordPress package and package it into a zip file.
 */
function plugins_loaded()
{
	if (!is_admin()) {
		return;
	}
	if (!current_user_can(ADMIN_PAGE_CAPABILITY)) {
		return;
	}

	global $pagenow;
	if ('admin.php' !== $pagenow) {
		return;
	}

	if (!isset($_GET['page']) || PLAYGROUND_ADMIN_PAGE_SLUG !== $_GET['page']) {
		return;
	}
	if (!isset($_GET['download'])) {
		return;
	}

	zip_collect();
	wp_die();
}

/**
 * Add the WordPress Playground page to the Tools menu.
 */
function plugin_menu()
{
	add_submenu_page(
		'tools.php',
		'WordPress Playground',
		'Sandbox Site',
		ADMIN_PAGE_CAPABILITY,
		PLAYGROUND_ADMIN_PAGE_SLUG,
		__NAMESPACE__ . '\render_playground_page',
		NULL
	);
}

/**
 * Render the WordPress Playground page.
 */
function render_playground_page()
{
	if (isset($_GET['download'])) {
		return;
	}
	include __DIR__ . '/templates/playground-page.php';
}

/**
 * Add a "Preview Now" button to the plugin install screen.
 *
 * @param array $action_links An array of plugin action links.
 * @param array $plugin The plugin data.
 * @return array The modified array of plugin action links.
 */
function plugin_install_action_links($action_links, $plugin)
{
	$retUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . urlencode('?' . http_build_query($_GET));

	$preview_url = add_query_arg(
		[
			'pluginSlug' => esc_attr($plugin['slug']),
			'returnUrl' => esc_attr($retUrl),
		],
		admin_url('admin.php?page=' . PLAYGROUND_ADMIN_PAGE_SLUG)
	);

	$preview_button = sprintf(
		'<a class="preview-now button" data-slug="%s" href="%s" aria-label="%s" data-name="%s">%s</a>',
		esc_attr($plugin['slug']),
		$preview_url,
		esc_attr(
			sprintf(
				/* translators: %s: Plugin name. */
				_x('Preview %s now', 'plugin'),
				$plugin['name']
			)
		),
		esc_attr($plugin['name']),
		__('Preview Now', TRANSLATE_DOMAIN)
	);

	array_unshift($action_links, $preview_button);

	return $action_links;
}
