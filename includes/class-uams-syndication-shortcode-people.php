<?php

class UAMS_Syndication_Shortcode_People extends UAMS_Syndication_Shortcode_Base {
	/**
	 * @var array A list of defaults specific to people that will extend the
	 *            base defaults set for all syndicate shortcodes.
	 */
	public $local_extended_atts = array(
		'profile_type' => 'academic', // Added for people
		'locations' => '',
		'conditions' => '',
		'specialty' => '',
		'colleges' => '',
		'academic_positions'  => '',
		'alpha' => '', // To be added
	);
	/**
	 * @var array A list of defaults specific to people that will override the
	 *            base defaults set for all syndicate shortcodes.
	 */
	public $local_default_atts = array(
		'output' => 'basic',
		'host'   => 'people.dev', // Production 'people.uams.edu'
		'query'  => 'people',
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
	 *                              - list      	 Display an unordered list of headlines.
	 *                              - basic       	 Display only excerpt information in an box list. Default.
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

		//$content = '<div class="uamswp-people-wrapper">';

		$people = json_decode( $data );

		$people = apply_filters( 'uamswp_people_sort_items', $people, $atts );

		ob_start();
		// By default, we output a JSON object that can then be used by a script.
		if ( 'json' === $atts['output'] ) {
			echo '<script>var ' . esc_js( $atts['object'] ) . ' = ' . wp_json_encode( $new_data ) . ';</script>';
		} elseif ( 'list' === $atts['output'] ) {
			?>
			<div class="uamswp-people-wrapper">
				<ul class="uamswp-person-list">
					<?php
					$offset_x = 0;
					foreach ( $people as $person ) {
						if ( $offset_x < absint( $atts['offset'] ) ) {
							$offset_x++;
							continue;
						}
						?><li class="uamswp_people_item"><a href="<?php echo esc_url( $person->link ); ?>"><?php echo esc_html( $person->title->rendered ); ?></a></li><?php
					}
					?>
				</ul>
			</div>
			<?php
		} elseif ( 'basic' === $atts['output'] ) {
			?>
			<div class="uamswp-people-wrapper">
				<?php
					$offset_x = 0;
					foreach ( $people as $person ) {
						if ( $offset_x < absint( $atts['offset'] ) ) {
							$offset_x++;
							continue;
						}
					?>
				<div class="uamswp-person-container row" style="border: 1px solid #eee; margin-bottom: 0.5em; padding-top: 0.5em;">
					<div class="col-md-4">
						<?php if ( isset( $person->person_meta->pphoto ) ) : ?>
						<figure class="uamswp-person-photo " style="max-width: 180px;">
							<img src="<?php echo esc_url( $person->person_meta->pphoto ); ?>" alt="<?php echo esc_html( $person->person_meta->physician_title ); ?>" style="padding-bottom: 0.5em;" />
						</figure>
						<div class="uamswp-person-profile"><a class="uams-btn btn-blue btn-sm" target="_self" title="View Profile" href="<?php echo esc_html( $person->link ); ?>">View Profile</a></div>
	<!-- 					<div class="uamswp-person-youtube"><a class="uams-btn btn-red btn-play btn-sm" target="_self" title="View Physician Video" href="<?php the_field( $person->person_meta->physician_youtube_link ); ?>">View Video</a></div> -->
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
				}
				?>
			</div><!-- end uamswp-people-wrapper -->
			<?php
		} elseif ( 'columns' === $atts['output'] ) {
			?>
			<div class="uamswp-people-wrapper">
				<ul class="uamswp-person-column">
					<?php
					$offset_x = 0;
					foreach ( $people as $person ) {
						if ( $offset_x < absint( $atts['offset'] ) ) {
							$offset_x++;
							continue;
						}
						?>
						<li class="uamswp-content-syndication-item">
							<span class="content-item-thumbnail"><?php if ( $person->person_meta->pphoto ) : ?><img src="<?php echo esc_url( $person->person_meta->pphoto ); ?>"><?php endif; ?></span>
							<span class="content-item-title"><a href="<?php echo esc_url( $person->link ); ?>"><?php echo esc_html( $person->title ); ?></a></span>
						</li>
						<?php
					}
					?>
				</ul>
			</div>
			<?php
		} elseif ( 'full' === $atts['output'] ) {
			?>
			<div class="uamswp-people-wrapper">
				<div class="uamswp-person-container">
					<?php
					$offset_x = 0;
					foreach ( $people as $person ) {
						if ( $offset_x < absint( $atts['offset'] ) ) {
							$offset_x++;
							continue;
						}
						?>
						<div class="uamswp-content-syndication-full">
						        <div class="row">
							        <div class="col-md-8">
						                <h1 class="title-heading-left" data-fontsize="34" data-lineheight="48"><?php echo esc_html( $person->person_meta->person_first_name ); ?> <?php echo (esc_html( $person->person_meta->person_middle_name ) ? esc_html( $person->person_meta->person_middle_name ) : ''); ?> <?php esc_html( $person->person_meta->person_last_name ); ?><?php echo (esc_html( $person->person_meta->person_degree ) ? ', ' . esc_html( $person->person_meta->person_degree ) : ''); ?></h1>
						                    <?php echo (esc_html( $person->person_meta->physician_title ) ? '<h4>' . esc_html( $person->person_meta->physician_title ) .'</h4>' : ''); ?>
						            </div>
									<div class="col-md-4" style="padding-top: 42px;">
						                <div class="fusion-button-wrapper">
						                    <a class="uams-btn btn-lg" target="_self" title="Make an Appointment" href="<?php echo esc_html( $person->person_meta->physician_appointment_link ); ?>">Make an Appointment</a>
						                </div>
						            </div>
						        </div>
								<div class="row">
									<div class="col-md-3">
						                <div style="padding-bottom: 1em;">
						                    <img src="<?php echo esc_url( $person->person_meta->pphoto ); ?>" alt="<?php echo esc_url( $person->title->rendered ); ?>">
						                </div>
					<?php if( esc_url( $person->person_meta->physician_youtube_link ) ) { ?>
						                <div>
						                    <a class="uams-btn btn-red btn-play btn-md" target="_self" title="Watch Video" href="<?php echo esc_url( $person->person_meta->physician_youtube_link ); ?>">Watch Video</a>
						                </div>
					<?php } ?>
						                <div class="fusion-button-wrapper">
						                    <a class="uams-btn btn-blue btn-plus btn-md" target="_self" title="Visit MyChart" href="https://mychart.uamshealth.com/">MyChart</a>
						                </div>
						                <?php if( $person->person_meta->physician_npi ) { ?>
										<div class="ds-summary" data-ds-id="<?php echo esc_url( $person->person_meta->physician_npi ); ?>"></div>
										<?php } ?>
						                <div class="fusion-sep-clear"></div>

						                <div class="fusion-separator fusion-full-width-sep sep-single" style="border-color:#e0dede;border-top-width:1px;margin-left: auto;margin-right: auto;margin-top:10px;margin-bottom:10px;"></div>
											<?php

											$locations = $person->person_meta->physician_locations;

											?>
											<?php if( $locations ): ?>
											<h3 data-fontsize="16" data-lineheight="24"><i class="fa fa-medkit"></i> Clinic(s)</h3>
												<ul>
												<?php foreach( $locations as $location ): ?>
													<li>
														<a href="<?php echo esc_url($location->link); ?>">
															<?php echo esc_html($location->title); ?>
														</a>
													</li>
												<?php endforeach; ?>
												</ul>
											<?php endif; ?>
						            </div>
									<div class="col-md-9">
						                <div class="js-tabs tabs__uams">
							                <ul class="js-tablist tabs__uams_ul" data-hx="h2">
						                    	<li class="js-tablist__item tabs__uams__li">
						                    		<a href="#tab-overview" id="label_tab-overview" class="js-tablist__link tabs__uams__a" data-toggle="tab">
						                            	Overview
						                            </a>
						                        </li>
						                        <li class="js-tablist__item tabs__uams__li">
						                            <a href="#tab-academics" id="label_tab-academics" class="js-tablist__link tabs__uams__a" data-toggle="tab">
							                            Academics
							                        </a>
						                        </li>
						                        <li class="js-tablist__item tabs__uams__li">
						                            <a href="#tab-location" id="label_tab-location" class="js-tablist__link tabs__uams__a" data-toggle="tab">
							                            Location
							                        </a>
						                        </li>
						                    <?php if(($person->person_meta->person_researcher_bio)||($person->person_meta->person_research_interests)): ?>
						                    	<li class="js-tablist__item tabs__uams__li">
						                            <a href="#tab-research" id="label_tab-research" class="js-tablist__link tabs__uams__a" data-toggle="tab">
							                            Research
							                        </a>
						                        </li>
						                    <?php endif; ?>
						                    <?php if(( $person->person_meta->person_awards )||( $person->person_meta->person_additional_info)): ?>
						                    	<li class="js-tablist__item tabs__uams__li">
						                            <a href="#tab-info" id="label_tab-info" class="js-tablist__link tabs__uams__a" data-toggle="tab">
							                            Additional Info
							                        </a>
						                        </li>
						                    <?php endif; ?>
					                        </ul>
					                        <div class="uams-tab-content">
						                        <div id="tab-overview" class="js-tabcontent tabs__uams__tabcontent">
								                    <?php echo esc_html($person->person_meta->physician_clinical_bio); ?>
								                    <?php // load all 'specialties' terms for the post
														$specialties = $person->person_meta->medical_specialties;

														// we will use the first term to load ACF data from
														if( $specialties ):
															$out = "<h3>Specialties</h3><ul>";

															    foreach ( $specialties as $specialty ) {
															       $out .= sprintf( '<li><a href="http://people.dev/specialties/%s">%s</a></li>',$specialty->slug, $specialty->name);
															    }

															    $out .= "</ul>";
															    ?>
															    <div class="uamswp-person-specialties"><?php echo $out; ?></div>
													<?php endif; ?>
													<?php // load all 'specialties' terms for the post
														$conditions = $person->person_meta->physician_conditions;

														// we will use the first term to load ACF data from
														if( $conditions ):
														$out = "<h3>Conditions/Diseases Treated</h3><p><em>UAMS doctors treat a broad range of conditions some of which may not be listed below.</em></p><ul>";

															    foreach ( $conditions as $condition ) {
															       $out .= sprintf( '<li><a href="http://people.dev/conditions/%s">%s</a></li>',$condition->slug, $condition->name);
															    }

															    $out .= "</ul>";
															    ?>
															    <div class="uamswp-physician-conditions"><?php echo $out; ?></div>
													<?php endif; ?>
													<?php // load all 'specialties' terms for the post
														$procedures = $person->person_meta->medical_procedures;

														// we will use the first term to load ACF data from
														if( $procedures ):
														$out = "<h3>Tests/Procedures Performed</h3><p><em>The following list represents some, but not all, of the procedures offered by this doctor.</em></p><ul>";

															    foreach ( $procedures as $procedure ) {
															       $out .= sprintf( '<li><a href="http://people.dev/procedures/%s">%s</a></li>',$procedure->slug, $procedure->name);
															    }

															    $out .= "</ul>";
															    ?>
															    <div class="uamswp-medical-procedures"><?php echo $out; ?></div>
													<?php endif; ?>
													<?php if($person->person_meta->physician_npi) { ?>
													<div id="div_PatientRatings">
					                                	<h2 id="PatientRatings">Patient Ratings & Reviews</h2>
														<div class="ds-breakdown"></div>
														<div class="ds-comments" data-ds-pagesize="10"></div>
					                            	</div>
													<?php } ?>
												</div>
											<?php if(($person->person_meta->person_academic_appointment)||($person->person_meta->person_education)||($person->person_meta->physician_boards)||($person->person_meta->person_publications)||($person->person_meta->person_pubmed_author_id)): ?>
						                        <div id="tab-academics" class="js-tabcontent tabs__uams__tabcontent">
						                            <?php if( $person->person_meta->person_academic_appointment ): ?>
						                            	<h3>Academic Appointments</h3>
													    <ul>
													    <?php foreach( $person->person_meta->person_academic_appointment as $appointment): ?>
													        <li><?php echo $appointment; ?></li>
													    <?php endforeach; ?>
													    </ul>
													<?php endif; ?>
													<?php // load all 'education' terms for the post
														$educations = $person->person_meta->person_education;

														// we will use the first term to load ACF data from
														if( $educations ): 
														$out = "<h3>Education</h3><ul>";

															    foreach ( $educations as $education ) {
															       $out .= sprintf( '<li>%s</li>', $education);
															    }

															    $out .= "</ul>";
															    ?>
															    <div class="uamswp-person-education"><?php echo $out; ?></div>
													<?php endif; ?>
													<?php // load all 'education' terms for the post
														$boards = $person->person_meta->physician_board_name;

														// we will use the first term to load ACF data from
														if( $boards ):
														$out = "<h3>Professional Certifications</h3><ul>";

															    foreach ( $boards as $board ) {
															       $out .= sprintf( '<li>%s</li>', $board);
															    }

															    $out .= "</ul>";
															    ?>
															    <div class="uamswp-physician-boards"><?php echo $out; ?></div>
													<?php endif; ?>
													<?php // load all 'education' terms for the post
														$publications = $person->person_meta->publication_pubmed_info;

														// we will use the first term to load ACF data from
														if( $publications ):
														$out = "<h3>Selected Publications</h3><ul>";

															    foreach ( $publications as $publication ) {
															       $out .= sprintf( '<li>%s</li>', $publication);
															    }

															    $out .= "</ul>";
															    ?>
															    <div class="uamswp-person-publications"><?php echo $out; ?></div>
													<?php endif; ?>
													<?php //if( get_field('person_pubmed_author_id') ): ?>
														<?php//
															//$pubmedid = trim(get_field('person_pubmed_author_id'));
															//$pubmedcount = (get_field('pubmed_author_number') ? get_field('pubmed_author_number') : '3')

														?>
						                            	<!-- <h3>Latest Publications</h3> -->
													    <?php// echo do_shortcode( '[pubmed terms="' . urlencode($pubmedid) .'%5BAuthor%5D" count="' . $pubmedcount .'"]' ); ?>
													<?php// endif; ?>
													<?php if( $person->person_meta->person_research_profiles_link ): ?>
						                            	More information is available on <a href="<?php echo esc_url($person->person_meta->person_research_profiles_link); ?>">UAMS Profile Page</a>
													<?php endif; ?>
						                        </div>
						                    <?php endif; ?>
						                        <div id="tab-location" class="js-tabcontent tabs__uams__tabcontent">
						                            <?php

													$physician_locations = $person->person_meta->physician_locations;

													if( $physician_locations ): ?>
					                                    <ol>
					                                        <?php foreach ($physician_locations as $physician_location): ?>
					                                        <li>
													        <h3><a href="<?php echo esc_url($physician_location->link); ?>"><?php echo esc_html($physician_location->title); ?></a></h3>
													            <!-- <p><?php the_field('location_address_1'); ?><br/>
													            <?php echo ( get_field('location_address_2') ? the_field('location_address_2') . '<br/>' : ''); ?>
													            <?php the_field('location_city'); ?>, <?php the_field('location_state'); ?> <?php the_field('location_zip'); ?><br/>
													            <?php the_field('location_phone'); ?>
													            <?php echo ( get_field('location_fax') ? '<br/>Fax: ' . the_field('location_fax') . '' : ''); ?>
													            <?php echo ( get_field('location_email') ? '<br/><a href="mailto:"' .the_field('location_email') . '">' . the_field('location_email') . '</a>' : ''); ?>
													            <?php echo ( get_field('location_web_name') ? '<br/><a href="' . get_field( 'location_url') . '">' . get_field('location_web_name') . '</a>' : ''); ?>
													            <br /><a href="https://www.google.com/maps/dir/Current+Location/<?php echo $map['lat'] ?>,<?php echo $map['lng'] ?>" target="_blank">Directions</a> [Opens in New Window]
													        </p> -->
					                                        </li>
													    <?php endforeach; ?>
					                                    </ol>
													<?php endif; ?>
						                        </div>
						                        <?php if(( $person->person_meta->person_researcher_bio ) || ($person->person_meta->person_research_interests) ): ?>
						                        <div id="tab-research" class="js-tabcontent tabs__uams__tabcontent">
						                            <?php echo ($person->person_meta->person_researcher_bio ? esc_html($person->person_meta->person_researcher_bio) : ''); ?>
													<?php
														if($person->person_meta->person_research_interests)
														{ ?>
														<h3>Research Interests</h3>
														<?php	echo esc_html($person->person_meta->person_research_interests);
														}
													?>
						                        </div>
						                    <?php endif; ?>
											<?php if(($person->person_meta->person_awards)||($person->person_meta->person_additional_info)): ?>
						                        <div id="tab-info" class="js-tabcontent tabs__uams__tabcontent">
						                            <?php if( $person->person_meta->person_awards ): ?>
						                            	<h3>Awards</h3>
													    <ul>
													    <?php foreach ( $person->person_meta->person_awards as $award ) {
															       $out .= sprintf( '<li>%s</li>', $award);
															    } ?>
													    </ul>
													<?php endif; ?>
													<?php
														if($person->person_meta->person_additional_info)
														{
															echo esc_html($person->person_meta->person_additional_info);
														}
													?>
						                        </div>
						                    <?php endif; ?>
						            		</div><!-- uams-tab-content -->
						                </div><!-- js-tabs -->
						    		</div><!-- col-md-9 -->
						    		<?php if($person->person_meta->physician_npi) { ?>
						    		<script src="https://www.docscores.com/widget/v2/uams/npi/<?php echo esc_html( $person->person_meta->physician_npi ); ?>/lotw.js" async=""></script>
						    		<?php } ?>
						    		<script src="<?php echo get_template_directory_uri(); ?>/js/uams.tabs.min.js" type="text/javascript"></script>
						    		<script type="text/javascript">
										(function ($, window) {

											var intervals = {};
											var removeListener = function(selector) {

												if (intervals[selector]) {

													window.clearInterval(intervals[selector]);
													intervals[selector] = null;
												}
											};
											var found = 'waitUntilExists.found';

											/**
											 * @function
											 * @property {object} jQuery plugin which runs handler function once specified
											 *           element is inserted into the DOM
											 * @param {function|string} handler
											 *            A function to execute at the time when the element is inserted or
											 *            string "remove" to remove the listener from the given selector
											 * @param {bool} shouldRunHandlerOnce
											 *            Optional: if true, handler is unbound after its first invocation
											 * @example jQuery(selector).waitUntilExists(function);
											 */

											$.fn.waitUntilExists = function(handler, shouldRunHandlerOnce, isChild) {

												var selector = this.selector;
												var $this = $(selector);
												var $elements = $this.not(function() { return $(this).data(found); });

												if (handler === 'remove') {

													// Hijack and remove interval immediately if the code requests
													removeListener(selector);
												}
												else {

													// Run the handler on all found elements and mark as found
													$elements.each(handler).data(found, true);

													if (shouldRunHandlerOnce && $this.length) {

														// Element was found, implying the handler already ran for all
														// matched elements
														removeListener(selector);
													}
													else if (!isChild) {

														// If this is a recurring search or if the target has not yet been
														// found, create an interval to continue searching for the target
														intervals[selector] = window.setInterval(function () {

															$this.waitUntilExists(handler, shouldRunHandlerOnce, true);
														}, 500);
													}
												}

												return $this;
											};

											}(jQuery, window));
										(function($) {
											$(document).ready(function(){
												$('.ds-average').waitUntilExists( function(){

													$('.ds-average').attr('itemprop', 'ratingValue');
													$('.ds-ratingcount').attr('itemprop', 'ratingCount');
													$('.ds-summary').attr('itemtype', 'http://schema.org/AggregateRating');
													$('.ds-summary').attr('itemprop', 'aggregateRating');
													$('.ds-comments').wrapInner('<a href="#PatientRatings"></a>');
												});
											});
										})(jQuery);
							    	</script>
								</div>
							
						</div>
						<?php
					}
					?>
				</div>
			</div><!-- end uamswp-people-wrapper -->
			<?php
		} // End if().
		$content = ob_get_contents();
		ob_end_clean();

		// Store the built content in cache for repeated use.
		$this->set_content_cache( $atts, 'uamswp_people', $content );

		$content = apply_filters( 'uamswp_people_item_html', $content, $atts );

		return $content;


		// foreach ( $people as $person ) {
		// 	$content .= $this->generate_item_html( $person, $atts['output'] );
		// }

		//$content .= '</div><!-- end uamswp-people-wrapper -->';

		//$this->set_content_cache( $atts, 'uamswp_people', $content );

		//return $content;
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
					<div class="uamswp-person-profile"><a class="uams-btn btn-blue btn-sm" target="_self" title="View Profile" href="<?php echo esc_html( $person->link ); ?>">View Profile</a></div>
<!-- 					<div class="uamswp-person-youtube"><a class="uams-btn btn-red btn-play btn-sm" target="_self" title="View Physician Video" href="<?php the_field( $person->person_meta->physician_youtube_link ); ?>">View Video</a></div> -->
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
