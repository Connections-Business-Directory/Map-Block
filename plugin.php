<?php
/**
 * Experimental map block shortcode for the Connections Business Directory plugin.
 *
 * @package   Connections Business Directory Extension - Map Block Shortcode
 * @category  Template
 * @author    Steven A. Zahm
 * @license   GPL-2.0+
 * @link      https://connections-pro.com
 * @copyright 2023 Steven A. Zahm
 *
 * @wordpress-plugin
 * Plugin Name:       Connections Business Directory Extension - Map Block Shortcode
 * Plugin URI:        https://connections-pro.com
 * Description:       Experimental map block shortcode for the Connections Business Directory plugin.
 * Version:           1.0
 * Requires at least: 5.6
 * Requires PHP:      7.0
 * Author:            Steven A. Zahm
 * Author URI:        https://connections-pro.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       cn_thumbnail_shortcodes
 * Domain Path:       /languages
 */

namespace Connections_Directory\Extension;

final class Map_Block {

	/**
	 * @var self
	 */
	private static $instance;

	/**
	 * @var string The absolute path this file.
	 *
	 * @since 1.0
	 */
	private $file = '';

	/**
	 * @var string The URL to the plugin's folder.
	 *
	 * @since 1.0
	 */
	private $url = '';

	/**
	 * @var string The absolute path to this plugin's folder.
	 *
	 * @since 1.0
	 */
	private $path = '';

	/**
	 * @var string The basename of the plugin.
	 *
	 * @since 1.0
	 */
	private $basename = '';

	public static function instance(): self {

		if ( ! self::$instance instanceof self ) {

			$self = new self();

			$self->file     = __FILE__;
			$self->url      = plugin_dir_url( $self->file );
			$self->path     = plugin_dir_path( $self->file );
			$self->basename = plugin_basename( $self->file );

			$self->includeDependencies();

			Shortcode\Map_Block::add();

			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueueStyles' ), 9999 );

			self::$instance = $self;
		}

		return self::$instance;
	}

	/**
	 * Include plugin dependencies.
	 *
	 * @since 1.0
	 */
	private function includeDependencies() {

		include_once 'Shortcode/Map_Block.php';
	}

	/**
	 * Whether to enqueue a registered CSS file.
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	private static function maybeEnqueueStyle() {

		$object = get_queried_object();

		if ( ! $object instanceof \WP_Post ) {

			return false;
		}

		return has_shortcode( $object->post_content, Shortcode\Map_Block::TAG );
	}

	/**
	 * Callback for the `wp_enqueue_scripts` action.
	 *
	 * Enqueues the Leaflet CSS on the frontend.
	 *
	 * @internal
	 * @since 1.0
	 */
	public static function enqueueStyles() {

		if ( self::maybeEnqueueStyle() ) {
			wp_enqueue_style( 'leaflet-control-geocoder' );
		}
	}
}

add_action(
	'Connections_Directory/Loaded',
	static function() {
		Map_Block::instance();
	}
);
