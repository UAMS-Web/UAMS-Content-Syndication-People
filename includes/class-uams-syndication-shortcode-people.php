<?php

class UAMS_Syndication_Shortcode_People extends UAMS_Syndication_Shortcode_Base {
	/**
	 * @var array A list of defaults specific to people that will override the
	 *            base defaults set for all syndicate shortcodes.
	 */
	public $local_default_atts = array(
		'output' => 'basic',
		'host'   => 'people.dev', // Production 'people.uams.edu'
		'query'  => 'people',
		'profile_type'  => 'academic',
		'locations' => '',
		'conditions' => '',
		'specialty' => '',
		'colleges' => '',
		'academic_positions'  => '',
		'alpha' => '', // To be added
	);

	/**
	 * @var string Shortcode name.
	 */
	public $shortcode_name = 'uamswp_people';

	public function __construct() {
		parent::construct();
	}

	public function add_shortcode() {
		add_shortcode( 'uamswp_people', array( $this, 'display_shortcode' ) );
	}

	/**
	 * Display people from people.uams.edu in a structured format using the
	 * WP REST API.
	 *
	 * @param array $atts {
	 *     Attributes passed with the shortcode.
	 *
	 *     @type string $object                   The name of the JSON object to use when output is set to json.
	 *     @type string $output                   The type of output to display.
	 *                              - json           Output a JSON object to be used with custom Javascript.
	 *                              - list      	 Display an unordered list of headlines.
	 *                              - standard       Display only excerpt information in an box list.
	 *								- columns        Display only excerpt information in an column boxes.
	 *                              - full           Display full content for each item.
	 *     @type string $host                     The hostname to pull items from. Defaults to people.uams.edu.
	 *     @type string $profile_type			  The slug of a profile type (academic or physician) Defaults to academic.
	 *     -Clinical Options
	 *     @type string $location			      The slug of a clinical location. Defaults to empty.
 	 *     @type string $conditions			      The slug of a medical conditions. Defaults to empty.
 	 *     @type string $specialties			  The slug of a medical specialties. Defaults to empty.
	 *     -Academic Options
	 *     @type string $college                  The slug of a college. Defaults to empty.
	 *     @type string $positions                The slug of a acadmeic position (ex. faculty, leadership). Defaults to empty.
	 *     @type int    $count                    The number of items to pull from a feed. Defaults to the
	 *     @type int    $alpha                    The letter(s) to limit the last name. Defaults to empty.
	 *     @type string $date_format              PHP Date format for the output of the item's date.
	 *     @type int    $offset                   The number of items to offset when displaying. Used with multiple
	 *                                            shortcode instances where one may pull in an excerpt and another
	 *                                            may pull in the rest of the feed as headlines.
	 *     @type string $cache_bust               Any change to this value will clear the cache and pull fresh data.
	 * }
	 *
	 * @return string Content to display in place of the shortcode.
	 */
	public function display_shortcode( $atts ) {
		$atts = $this->process_attributes( $atts );

		$site_url = $this->get_request_url( $atts );
		if ( ! $site_url ) {
			return '<!-- uamswp_people ERROR - an empty host was supplied -->';
		}

		$content = $this->get_content_cache( $atts, 'uamswp_people' );
		if ( $content ) {
			return $content;
		}

		$request_url = esc_url( $site_url['host'] . $site_url['path'] . $this->default_path ) . $atts['query'];
		$request_url = $this->build_taxonomy_filters( $atts, $request_url );

		if ( $atts['count'] ) {
			$count = ( 100 < absint( $atts['count'] ) ) ? 100 : $atts['count'];
			$request_url = add_query_arg( array(
				'per_page' => absint( $count ),
			), $request_url );
		}

		if ( ! empty( $atts['profile_type'] ) ) {
			$terms = $this->sanitized_terms( $atts['profile_type'] );
			$request_url = add_query_arg( array(
				'filter[profile_type]' => $terms,
			), $request_url );
		}

		if ( ! empty( $atts['locations'] ) ) {
			$terms = $this->sanitized_terms( $atts['locations'] );
			$request_url = add_query_arg( array(
				'filter[meta_key]=physician_locations&filter[meta_compare]=LIKE&filter[meta_value]' => $terms,
			), $request_url );
		}

		if ( ! empty( $atts['conditions'] ) ) {
			$terms = $this->sanitized_terms( $atts['conditions'] );
			$request_url = add_query_arg( array(
				'filter[condition]' => $terms,
			), $request_url );
		}

		if ( ! empty( $atts['specialty'] ) ) {
			$terms = $this->sanitized_terms( $atts['specialty'] );
			$request_url = add_query_arg( array(
				'filter[specialty]' => $terms,
			), $request_url );
		}

		if ( ! empty( $atts['colleges'] ) ) {
			$terms = $this->sanitized_terms( $atts['colleges'] );
			$request_url = add_query_arg( array(
				'filter[academic_colleges]' => $terms,
			), $request_url );
		}

		if ( ! empty( $atts['academic_positions'] ) ) {
			$terms = $this->sanitized_terms( $atts['academic_positions'] );
			$request_url = add_query_arg( array(
				'filter[academic_positions]' => $terms,
			), $request_url );
		}

/*
		if ( ! empty( $atts['alpha'] ) ) {
			$terms = $this->sanitized_terms( $atts['alpha'] );
			$request_url = add_query_arg( array(
				'filter[alpha]' => $terms,
			), $request_url );
		}
*/

		$response = wp_remote_get( $request_url );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$data = wp_remote_retrieve_body( $response );

		if ( empty( $data ) ) {
			return '';
		}

		$content = '<div class="uamswp-people-wrapper">';

		$people = json_decode( $data );

		$people = apply_filters( 'uamswp_people_sort_items', $people, $atts );

		foreach ( $people as $person ) {
			$content .= $this->generate_item_html( $person, $atts['output'] );
		}

		$content .= '</div><!-- end uamswp-people-wrapper -->';

		$this->set_content_cache( $atts, 'uamswp_people', $content );

		return $content;
	}

	/**
	 * Generate the HTML used for individual people when called with the shortcode.
	 *
	 * @since 1.0.0 Pulled from WSUWP Content Syndicate
	 *
	 * @param stdClass $person Data returned from the WP REST API.
	 * @param string   $type   The type of output expected.
	 *
	 * @return string The generated HTML for an individual person.
	 */
	private function generate_item_html( $person, $type ) {
		if ( 'basic' === $type ) {
			ob_start();
			?>
			<div class="uamswp-person-container row" style="border: 1px solid #eee; margin-bottom: 0.5em; padding-top: 0.5em;">
				<div class="col-md-4">
					<?php if ( isset( $person->person_meta->pphoto ) ) : ?>
					<figure class="uamswp-person-photo " style="max-width: 180px;">
						<img src="<?php echo esc_url( $person->person_meta->pphoto ); ?>" alt="<?php echo esc_html( $person->person_meta->physician_title ); ?>" style="padding-bottom: 0.5em;" />
					</figure>
					<div class="uamswp-person-profile"><a class="uams-btn btn-blue btn-sm" target="_self" title="View Profile" href="<?php echo esc_html( $person->person_meta->physician_youtube_link ); ?>">View Profile</a></div>
<!-- 					<div class="uamswp-person-youtube"><a class="uams-btn btn-red btn-play btn-sm" target="_self" title="View Physician Video" href="<?php the_field('physician_youtube_link'); ?>">View Video</a></div> -->
					<?php endif; ?>
				</div>
				<div class="col-md-6">
					<div class="uamswp-person-name"><a href="<?php echo esc_html( $person->link ); ?>"><?php echo esc_html( $person->title->rendered ); ?></a></div>
					<div class="uamswp-person-position"><?php echo esc_html( $person->person_meta->physician_title ); ?></div>
 					<div class="uamswp-person-bio"><?php echo esc_html( $person->person_meta->physician_short_clinical_bio ); ?></div>
					<?php $specialties = $person->person_meta->medical_specialties;
						if ( $specialties && ! is_wp_error( $specialties ) ) :
						 	$out = "<h3>Specialties</h3><ul>";

						    foreach ( $specialties as $specialty ) {
						       $out .= sprintf( '<li><a href="http://people.dev/specialties/%s">%s</a></li>',$specialty->slug, $specialty->name);
						    }

						    $out .= "</ul>";
						    ?>
						    <div class="uamswp-person-specialties"><?php echo $out; ?></div>
					<?php
						endif;
					?>
				</div>
			</div>
			<?php
			$html = ob_get_contents();
			ob_end_clean();

			return $html;
		}

		return apply_filters( 'uamswp_people_item_html', '', $person, $type );
	}
}
