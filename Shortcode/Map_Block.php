<?php
/**
 * The `[cn-mapblock]` shortcode.
 *
 * @since 1.0
 *
 * @category   WordPress\Plugin
 * @package    Connections Business Directory
 * @subpackage Connections\Extension\Map_Block\Shortcode
 * @author     Steven A. Zahm
 * @license    GPL-2.0+
 * @copyright  Copyright (c) 2023, Steven A. Zahm
 * @link       https://connections-pro.com/
 */

namespace Connections_Directory\Extension\Shortcode;

use cnOptions;
use cnSettingsAPI as Option;
use cnCoordinates as Coordinates;
use Connections_Directory\Map\Control\Layer\Layer_Control;
use Connections_Directory\Map\Map;
use Connections_Directory\Map\UI\Popup;
use Connections_Directory\Map\UI\Marker;
use Connections_Directory\Map\Layer\Group\Layer_Group;
use Connections_Directory\Shortcode\Do_Shortcode;
use Connections_Directory\Utility\_format;
use Connections_Directory\Map\Layer\Raster\Provider;

final class Map_Block {

	use Do_Shortcode;

	/**
	 * The shortcode tag.
	 *
	 * @since 1.0
	 */
	const TAG = 'cn-mapblock';

	/**
	 * @var Map
	 */
	private $map;

	/**
	 * @var Layer_Control
	 */
	private $layerControl;

	/**
	 * Register the shortcode.
	 *
	 * @since 1.0
	 */
	public static function add() {

		add_filter( 'pre_do_shortcode_tag', array( __CLASS__, 'maybeDoShortcode' ), 10, 4 );
		add_shortcode( self::TAG, array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Callback for `add_shortcode()`
	 *
	 * @param array  $atts
	 * @param string $content
	 * @param string $tag
	 *
	 * @return string
	 */
	public static function shortcode( array $atts, string $content = '', string $tag = self::TAG ): string {

		return new self( $atts, $content, $tag );
	}

	/**
	 * mapBlock constructor.
	 *
	 * @param array  $atts
	 * @param string $content
	 * @param string $tag
	 */
	public function __construct( array $atts, string $content = '', string $tag = self::TAG ) {

		$atts = $this->parseAtts( $atts, $tag );

		$this->map = Map::create(
			$atts['id'],
			array(
				'center' => new Coordinates( $atts['latitude'], $atts['longitude'] ),
				'zoom'   => $atts['zoom'],
			)
		);

		$this->layerControl = Layer_Control::create( 'layerControl' )->setCollapsed( false );

		$googleMapsAPIBrowserKey = Option::get(
			'connections',
			'google_maps_geocoding_api',
			'browser_key'
		);

		// Strings to be used for setting the Leaflet maps `attribution`.
		$leaflet  = '<a href="https://leafletjs.com/" target="_blank" title="Leaflet">Leaflet</a>';
		$backlink = '<a href="https://connections-pro.com/" target="_blank" title="Connections Business Directory plugin for WordPress">Connections Business Directory</a> | ' . $leaflet;

		$attribution = array( $backlink );

		if ( 0 < strlen( $googleMapsAPIBrowserKey ) ) {

			$roadMap = Provider\Google_Maps::create( 'roadmap' );

			$roadMap->setAttribution( implode( ' | ', $attribution ) )
					->setOption( 'name', 'Roadmap' );

			$this->layerControl->addBaseLayer( $roadMap );

			$hybrid = Provider\Google_Maps::create( 'hybrid' );

			$hybrid->setAttribution( implode( ' | ', $attribution ) )
				   ->setOption( 'name', 'Satellite' );

			$this->layerControl->addBaseLayer( $hybrid );

		} else {

			$baseMap = Provider\Wikimedia::create();

			$attribution[] = $baseMap->getAttribution();

			$baseMap->setAttribution( implode( ' | ', $attribution ) );

			$this->map->addLayer( $baseMap );
		}

		$this->map->setHeight( $atts['height'] )
				  ->setWidth( $atts['width'] )
				  ->setCenter( new Coordinates( $this->getDefaults()['latitude'], $this->getDefaults()['longitude'] ) )
				  ->addLayers( $this->layerControl->getBaseLayers() )
				  ->addControl( $this->layerControl );

		$content = $this->parseLayers( $content );
		$content = $this->parseMarkers( $content );

		// $this->map->addLayers( $this->layerControl->getBaseLayers() );
		$this->map->addLayers( $this->layerControl->getOverlays() );

		if ( $atts['marker'] ) {

			$coordinates = Coordinates::create( $atts['latitude'], $atts['longitude'] );

			if ( ! is_wp_error( $coordinates ) ) {

				$marker = Marker::create( 'default', $coordinates );

				if ( 0 < strlen( $content ) ) {

					$marker->bindPopup( Popup::create( 'default', $content ) );
				}

				$marker->addTo( $this->map );
			}
		}

	}

	/**
	 * @param array  $atts
	 * @param string $content
	 * @param string $tag
	 *
	 * @return self
	 */
	public static function create( array $atts, string $content = '', string $tag = self::TAG ): self {

		return new self( $atts, $content, $tag );
	}

	/**
	 * The shortcode defaults.
	 *
	 * @return array
	 */
	private function getDefaults(): array {

		$geo = cnOptions::getBaseGeoCoordinates();

		return array(
			'id'        => 'cn-map-' . uniqid(),
			'latitude'  => $geo['latitude'],
			'longitude' => $geo['longitude'],
			'zoom'      => 16,
			'height'    => '400px',
			'width'     => '100%',
			'marker'    => true,
		);
	}

	/**
	 * Parse the user supplied atts.
	 *
	 * @param array  $atts
	 * @param string $tag
	 *
	 * @return array
	 */
	private function parseAtts( array $atts, string $tag ): array {

		$defaults = $this->getDefaults();
		$atts     = shortcode_atts( $defaults, $atts, $tag );

		_format::toBoolean( $atts['marker'] );

		return $atts;
	}

	/**
	 * Parse the shortcode content for layers and their markers.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	private function parseLayers( string $content ): string {

		$pattern = get_shortcode_regex( array( 'maplayer' ) );

		$content = preg_replace_callback(
			"/$pattern/",
			function( $match ) {

				// If there is no content, then there are no markers to parse, return.
				if ( 0 == strlen( $match[5] ) ) {
					return '';
				}

				$defaults = array(
					'id'      => 'layer',
					'name'    => '',
					'control' => false,
				);

				$atts = $this->parseShortcodeAtts( $match[3] );
				$atts = shortcode_atts( $defaults, $atts );

				_format::toBoolean( $atts['control'] );

				$layerGroup = Layer_Group::create( $atts['id'] );

				if ( 0 < strlen( $atts['name'] ) ) {

					$layerGroup->setOption( 'name', $atts['name'] );
				}

				$this->parseMarkers( $match[5], $layerGroup );

				if ( $atts['control'] ) {

					$this->layerControl->addOverlay( $layerGroup );

				} else {

					$layerGroup->addTo( $this->map );
				}

				return '';
			},
			$content
		);

		return trim( $content );
	}

	/**
	 * Parse supplied content for markers.
	 *
	 * @param string      $content
	 * @param Layer_Group $layer
	 *
	 * @return string
	 */
	private function parseMarkers( string $content, $layer = null ): string {

		$pattern = get_shortcode_regex( array( 'mapmarker' ) );

		$content = preg_replace_callback(
			"/$pattern/",
			function( $match ) use ( $layer ) {

				$defaults = array(
					'id'        => 'marker',
					'latitude'  => null,
					'longitude' => null,
				);

				$atts = shortcode_parse_atts( $match[3] );
				$atts = shortcode_atts( $defaults, $atts );

				$coordinates = Coordinates::create( $atts['latitude'], $atts['longitude'] );

				if ( ! is_wp_error( $coordinates ) ) {

					$marker = Marker::create( $atts['id'], $coordinates );

					if ( 0 < strlen( $match[5] ) ) {

						$marker->bindPopup( Popup::create( 'default', $match[5] ) );
					}

					if ( $layer instanceof Layer_Group ) {

						$layer->addLayer( $marker );

					} else {

						$marker->addTo( $this->map );
					}

				}

				return '';
			},
			$content
		);

		return trim( $content );
	}

	/**
	 * Wrapper function for core WP `shortcode_parse_atts()`.
	 * Decodes selected HTML entities.
	 *
	 * @param string $text
	 *
	 * @return array|string
	 */
	private function parseShortcodeAtts( string $text ) {

		$text = str_replace(
			array(
				'&#8220;',
				'&Prime;',
				'&#8221;',
				'&#8243;',
				'&#8217;',
				'&#8242;',
				'&nbsp;&raquo;',
				'&#187;',
				'&quot;',
			),
			array( '"', '"', '"', '"', '\'', '\'', '"', '"', '"' ),
			$text
		);

		return shortcode_parse_atts( $text );
	}

	/**
	 * @return string
	 */
	public function __toString() {

		return (string) $this->map;
	}
}
