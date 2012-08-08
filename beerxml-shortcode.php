<?php
/*
Plugin Name: BeerXML Shortcode
Plugin URI: http://automattic.com/
Description: Automatically insert/display beer recipes by linking to a BeerXML document.
Author: Derek Springer
Version: 0.1
Author URI: http://flavors.me/derekspringer
*/

class BeerXMLShortcode {

	/**
	 * A simple call to init when constructed
	 */
	function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * BeerXML initialization routines
	 */
	function init() {
		// I18n
		load_plugin_textdomain(
			'beerxml-shortcode',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		if ( ! defined( 'BEERXML_URL' ) ) {
			define( 'BEERXML_URL', plugin_dir_url( __FILE__ ) );
		}

		if ( ! defined( 'BEERXML_PATH' ) ) {
			define( 'BEERXML_PATH', plugin_dir_path( __FILE__ ) );
		}

		require_once( BEERXML_PATH . '/includes/classes.php' );

		add_shortcode( 'beerxml', array( $this, 'beerxml_shortcode' ) );
	}

	/**
	 * [beer_xml_shortcode description]
	 * @param  array $atts shortcode attributes
	 * @return string HTML to be inserted in shortcode's place
	 */
	function beerxml_shortcode( $atts ) {
		global $post;

		if ( ! is_array( $atts ) ) {
			return '<!-- BeerXML shortcode passed invalid attributes -->';
		}

		if ( ! isset( $atts['recipe'] ) && ! isset( $atts[0] ) ) {
			return '<!-- BeerXML shortcode source not set -->';
		}

		extract( shortcode_atts( array(
			'recipe' => null,
			'cache'  => 60*60*12,
			'metric' => false
		), $atts ) );

		if ( ! isset( $recipe ) ) {
			$recipe = $atts[0];
		}

		$recipe = esc_url_raw( $recipe );
		$recipe_filename = pathinfo( $recipe, PATHINFO_FILENAME );
		$recipe_id = "beerxml_shortcode_recipe-{$post->ID}_$recipe_filename";

		$cache  = intval( esc_attr( $cache ) );
		if ( -1 == $cache ) {
			delete_transient( $recipe_id );
			$cache = 0;
		}

		$metric = (boolean) esc_attr( $metric );

		if ( ! $cache || false === ( $beer_xml = get_transient( $recipe_id ) ) ) {
			$beer_xml = new BeerXML( $recipe );
			if ( $cache && $beer_xml->recipes ) {
				set_transient( $recipe_id, $beer_xml, $cache );
			}
		}

		if ( ! $beer_xml->recipes ) {
			return '<!-- Error parsing BeerXML document -->';
		}

		$fermentables = '';
		foreach ( $beer_xml->recipes[0]->fermentables as $fermentable ) {
			$fermentables .= $this->build_fermentable( $fermentable, $metric );
		}

		$t_fermentables = __( 'Fermentables', 'beerxml-shortcode' );
		$t_name = __( 'Name', 'beerxml-shortcode' );
		$t_amount = __( 'Amount', 'beerxml-shortcode' );
		$fermentables = <<<FERMENTABLES
		<div id='fermentables'>
			<h2>$t_fermentables</h2>
			<table>
				<thead>
					<tr>
						<th>$t_name</th>
						<th>$t_amount</th>
					</tr>
					$fermentables
				</thead>
			</table>
		</div>
FERMENTABLES;

		$hops = '';
		foreach ( $beer_xml->recipes[0]->hops as $hop ) {
			$hops .= $this->build_hop( $hop, $metric );
		}

		$t_hops  = __( 'Hops', 'beerxml-shortcode' );
		$t_time  = __( 'Time', 'beerxml-shortcode' );
		$t_use   = __( 'Use', 'beerxml-shortcode' );
		$t_form  = __( 'Form', 'beerxml-shortcode' );
		$t_alpha = __( 'Alpha %', 'beerxml-shortcode' );
		$hops = <<<HOPS
		<div id='hops'>
			<h2>$t_hops</h2>
			<table>
				<thead>
					<tr>
						<th>$t_name</th>
						<th>$t_amount</th>
						<th>$t_time</th>
						<th>$t_use</th>
						<th>$t_form</th>
						<th>$t_alpha</th>
					</tr>
					$hops
				</thead>
			</table>
		</div>
HOPS;

		$yeasts = '';
		foreach ( $beer_xml->recipes[0]->yeasts as $yeast ) {
			$yeasts .= $this->build_yeast( $yeast, $metric );
		}

		$t_yeast       = __( 'Yeast', 'beerxml-shortcode' );
		$t_lab         = __( 'Lab', 'beerxml-shortcode' );
		$t_attenuation = __( 'Attenuation', 'beerxml-shortcode' );
		$t_temperature = __( 'Temperature', 'beerxml-shortcode' );
		$yeasts = <<<YEASTS
		<div id='yeasts'>
			<h2>$t_yeast</h2>
			<table>
				<thead>
					<tr>
						<th>$t_name</th>
						<th>$t_lab</th>
						<th>$t_attenuation</th>
						<th>$t_temperature</th>
					</tr>
					$yeasts
				</thead>
			</table>
		</div>
YEASTS;

		$html = <<<HTML
		<div id='beerxml-recipe'>
			$fermentables
			$hops
			$yeasts
		</div>
HTML;

		return $html;
	}

	function build_fermentable( $fermentable, $metric = false ) {
		if ( $metric ) {
			$fermentable->amount = round( $fermentable->amount, 3 );
			$t_weight = __( 'kg', 'beerxml-shortcode' );
		} else {
			$fermentable->amount = round( $fermentable->amount * 2.20462, 2 );
			$t_weight = __( 'lbs', 'beerxml-shortcode' );
		}

		return <<<FERMENTABLE
		<tr>
			<td>$fermentable->name</td>
			<td>$fermentable->amount $t_weight</td>
		</tr>
FERMENTABLE;
	}

	function build_hop( $hop, $metric = false ) {
		if ( $metric ) {
			$hop->amount = round( $hop->amount * 1000, 1 );
			$t_weight = __( 'g', 'beerxml-shortcode' );
		} else {
			$hop->amount = round( $hop->amount * 35.274, 2 );
			$t_weight = __( 'oz', 'beerxml-shortcode' );
		}

		if ( $hop->time >= 1440 ) {
			$hop->time = round( $hop->time / 1440, 1);
			$t_time = _n( 'day', 'days', $hop->time, 'beerxml-shortcode' );
		} else {
			$hop->time = round( $hop->time );
			$t_time = __( 'min', 'beerxml-shortcode' );
		}

		$hop->alpha = round( $hop->alpha, 1 );

		return <<<FERMENTABLE
		<tr>
			<td>$hop->name</td>
			<td>$hop->amount $t_weight</td>
			<td>$hop->time $t_time</td>
			<td>$hop->use</td>
			<td>$hop->form</td>
			<td>$hop->alpha</td>
		</tr>
FERMENTABLE;
	}

	function build_yeast( $yeast, $metric = false ) {
		if ( $metric ) {
			$yeast->min_temperature = round( $yeast->min_temperature, 2 );
			$yeast->max_temperature = round( $yeast->max_temperature, 2 );
			$t_temp = __( 'C', 'beerxml-shortcode' );
		} else {
			$yeast->min_temperature = round( ( $yeast->min_temperature * (9/5) ) + 32, 1 );
			$yeast->max_temperature = round( ( $yeast->max_temperature * (9/5) ) + 32, 1 );
			$t_temp = __( 'F', 'beerxml-shortcode' );
		}

		$yeast->attenuation = round( $yeast->attenuation );

		return <<<YEAST
		<tr>
			<td>$yeast->name</td>
			<td>$yeast->laboratory</td>
			<td>{$yeast->attenuation}%</td>
			<td>{$yeast->min_temperature}°$t_temp - {$yeast->max_temperature}°$t_temp</td>
		</tr>
YEAST;
	}
}

// The fun starts here!
new BeerXMLShortcode();
