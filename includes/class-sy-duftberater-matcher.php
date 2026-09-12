<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SY_Duftberater_Matcher {

	protected $plugin;

	/**
	 * Die Summe ist bewusst genau 100.
	 * 100 % darf nur entstehen, wenn alle relevanten Fragen wirklich passen.
	 */
	protected $weights = array(
		'gender'     => 20,
		'age'        => 10,
		'season'     => 10,
		'usage'      => 15,
		'smoker'     => 5,
		'directions' => 20,
		'notes'      => 20,
	);

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	public function get_results( $answers ) {
		$parfums = $this->plugin->get_parfums();
		$scored  = array();

		foreach ( $parfums as $parfum ) {
			if ( empty( $parfum['name'] ) ) {
				continue;
			}

			$item = $this->score_parfum( $parfum, $answers );
			if ( ! empty( $item['excluded'] ) ) {
				continue;
			}

			$scored[] = $item;
		}

		usort(
			$scored,
			function( $a, $b ) {
				if ( (int) $b['percentage'] === (int) $a['percentage'] ) {
					if ( (float) $b['score'] === (float) $a['score'] ) {
						if ( (float) ( $b['tie_breaker'] ?? 0 ) === (float) ( $a['tie_breaker'] ?? 0 ) ) {
							$prio_a = isset( $a['parfum']['priority_order'] ) ? (int) $a['parfum']['priority_order'] : 999999;
							$prio_b = isset( $b['parfum']['priority_order'] ) ? (int) $b['parfum']['priority_order'] : 999999;
							if ( $prio_a === $prio_b ) {
								return strcasecmp( (string) ( $a['parfum']['name'] ?? '' ), (string) ( $b['parfum']['name'] ?? '' ) );
							}
							return $prio_a <=> $prio_b;
						}
						return (float) ( $b['tie_breaker'] ?? 0 ) <=> (float) ( $a['tie_breaker'] ?? 0 );
					}
					return (float) $b['score'] <=> (float) $a['score'];
				}
				return (int) $b['percentage'] <=> (int) $a['percentage'];
			}
		);

		return array_slice( $scored, 0, 3 );
	}

	protected function score_parfum( $parfum, $answers ) {
		$score      = 0.0;
		$max_score  = (float) array_sum( $this->weights );
		$reasons    = array();
		$highlights = array();
		$full_parts = array(
			'gender'     => false,
			'age'        => false,
			'season'     => false,
			'usage'      => false,
			'smoker'     => false,
			'directions' => false,
			'notes'      => false,
		);

		$gender = $this->normalize_gender_key( $answers['geschlecht'] ?? '' );
		if ( $gender ) {
			$parfum_gender = $this->normalize_gender_list( $parfum['geschlecht'] ?? array() );
			if ( ! $this->is_gender_compatible( $gender, $parfum_gender ) ) {
				return array(
					'parfum'      => $parfum,
					'score'       => 0,
					'percentage'  => 0,
					'tie_breaker' => 0,
					'reason'      => 'Ausgeschlossen, weil das Geschlecht nicht zur Auswahl passt.',
					'excluded'    => true,
				);
			}

			$score += $this->weights['gender'];
			$full_parts['gender'] = true;
			if ( 'unisex' === $gender ) {
				$highlights[] = 'als Unisex-Duft zur Auswahl passt';
			} elseif ( in_array( $gender, $parfum_gender, true ) ) {
				$highlights[] = sprintf( 'für %s ausgelegt ist', strtolower( $this->plugin->get_option_label( 'geschlecht', $gender ) ) );
			} else {
				$highlights[] = 'zur gewählten Geschlechtskategorie passt';
			}
		}

		$age = sanitize_key( $answers['alter'] ?? '' );
		if ( $age ) {
			$parfum_age = $this->normalize_key_list( $parfum['alter'] ?? array() );
			if ( in_array( $age, $parfum_age, true ) ) {
				$score += $this->weights['age'];
				$full_parts['age'] = true;
				$reasons[] = 'zur gewählten Altersgruppe harmonisch wirkt';
			}
		}

		$season = sanitize_key( $answers['jahreszeit'] ?? '' );
		if ( $season ) {
			$parfum_seasons = $this->normalize_key_list( $parfum['jahreszeit'] ?? array() );
			if ( in_array( $season, $parfum_seasons, true ) ) {
				$score += $this->weights['season'];
				$full_parts['season'] = true;
				$highlights[] = sprintf( 'im %s besonders stimmig wirkt', strtolower( $this->plugin->get_option_label( 'jahreszeit', $season ) ) );
			}
		}

		$usage = sanitize_key( $answers['verwendungsbereich'] ?? '' );
		if ( $usage ) {
			$parfum_usage = $this->normalize_key_list( $parfum['verwendungsbereich'] ?? array() );
			if ( in_array( $usage, $parfum_usage, true ) ) {
				$score += $this->weights['usage'];
				$full_parts['usage'] = true;
				$highlights[] = sprintf( 'zu %s sehr gut passt', strtolower( $this->plugin->get_option_label( 'verwendungsbereich', $usage ) ) );
			}
		}

		$smoker = sanitize_key( $answers['raucher'] ?? '' );
		if ( $smoker ) {
			if ( 'ja' === $smoker ) {
				if ( ! empty( $parfum['raucher_geeignet'] ) ) {
					$score += $this->weights['smoker'];
					$full_parts['smoker'] = true;
					$reasons[] = 'auch neben Rauch besser bestehen kann';
				}
			} elseif ( 'nein' === $smoker ) {
				// „Nein“ bedeutet: Raucher-Eignung ist nicht nötig. Nicht-rauchige Profile passen am saubersten,
				// rauchergeeignete Düfte bleiben aber weiterhin möglich und werden nur leicht niedriger bewertet.
				if ( empty( $parfum['raucher_geeignet'] ) ) {
					$score += $this->weights['smoker'];
					$full_parts['smoker'] = true;
				} else {
					$score += $this->weights['smoker'] * 0.75;
				}
			}
		}

		$user_families   = $this->normalize_key_list( $answers['duftrichtungen'] ?? array() );
		$parfum_families = $this->normalize_key_list( $parfum['duftrichtungen'] ?? array() );
		$family_metrics  = $this->get_bidirectional_match_metrics( $user_families, $parfum_families );
		if ( $family_metrics['ratio'] > 0 ) {
			$score += $this->weights['directions'] * $family_metrics['ratio'];
			$full_parts['directions'] = $family_metrics['is_exact'];
			$family_labels = $this->get_labels_from_values( 'duftrichtungen', $family_metrics['matches'], 3 );
			if ( ! empty( $family_labels ) ) {
				$highlights[] = implode( ' und ', $family_labels ) . 'e Facetten mitbringt';
			}
		}

		$user_notes   = $this->normalize_key_list( $answers['duftnoten'] ?? array() );
		$parfum_notes = $this->normalize_key_list( $parfum['duftnoten'] ?? array() );
		$note_metrics = $this->get_bidirectional_match_metrics( $user_notes, $parfum_notes );
		if ( $note_metrics['ratio'] > 0 ) {
			$score += $this->weights['notes'] * $note_metrics['ratio'];
			$full_parts['notes'] = $note_metrics['is_exact'];
			$note_labels = $this->get_labels_from_values( 'duftnoten', $note_metrics['matches'], 4 );
			if ( ! empty( $note_labels ) ) {
				if ( $note_metrics['is_exact'] ) {
					$reasons[] = 'alle gepflegten Duftnoten-Gruppen vollständig getroffen werden';
				} else {
					$reasons[] = 'mit passenden Duftnoten wie ' . implode( ', ', $note_labels ) . ' übereinstimmt';
				}
			}
		}

		$perfect_match = $this->is_perfect_profile_match( $full_parts, $answers );

		$score      = max( 0.0, min( $score, $max_score ) );
		$percentage = $max_score > 0 ? (int) round( ( $score / $max_score ) * 100 ) : 0;

		// 100 % ist reserviert für einen echten Volltreffer über alle relevanten Fragen.
		if ( $percentage >= 100 && ! $perfect_match ) {
			$percentage = 99;
		}

		// Wenn Duftnoten nur teilweise getroffen sind, wird die Anzeige zusätzlich gedeckelt.
		// Dadurch reicht eine einzige passende Note nicht mehr für sehr hohe Ergebnisse.
		if ( ! empty( $user_notes ) && ! empty( $parfum_notes ) && ! $note_metrics['is_exact'] ) {
			$note_cap = (int) floor( 76 + ( 18 * $note_metrics['coverage'] ) + ( 5 * $note_metrics['precision'] ) );
			$percentage = min( $percentage, max( 60, min( 94, $note_cap ) ) );
		}

		// Wenn Duftfamilien nur teilweise passen, soll ebenfalls kein künstlicher Volltreffer entstehen.
		if ( ! empty( $user_families ) && ! empty( $parfum_families ) && ! $family_metrics['is_exact'] ) {
			$percentage = min( $percentage, 96 );
		}

		// Keine passenden Duftnoten gewählt/getroffen: trotzdem same-gender-Fallback, aber nicht zu hoch.
		if ( ! empty( $user_notes ) && $note_metrics['ratio'] <= 0 ) {
			$percentage = min( $percentage, 74 );
			$reasons[] = 'bei den gewünschten Duftnoten nur als Ersatzvorschlag dient';
		}

		$tie_breaker = $this->calculate_tie_breaker( $parfum, $family_metrics, $note_metrics, $full_parts );

		return array(
			'parfum'      => $parfum,
			'score'       => round( $score, 2 ),
			'percentage'  => max( 0, min( 100, $percentage ) ),
			'tie_breaker' => round( $tie_breaker, 4 ),
			'reason'      => $this->generate_reason( $answers, $highlights, $reasons ),
			'excluded'    => false,
		);
	}

	protected function is_perfect_profile_match( $full_parts, $answers ) {
		$required_map = array(
			'geschlecht'         => 'gender',
			'alter'              => 'age',
			'jahreszeit'         => 'season',
			'verwendungsbereich' => 'usage',
			'raucher'            => 'smoker',
			'duftrichtungen'     => 'directions',
			'duftnoten'          => 'notes',
		);

		foreach ( $required_map as $answer_key => $part_key ) {
			$value = $answers[ $answer_key ] ?? '';
			if ( is_array( $value ) ) {
				$has_answer = ! empty( array_filter( $value ) );
			} else {
				$has_answer = '' !== trim( (string) $value );
			}

			if ( $has_answer && empty( $full_parts[ $part_key ] ) ) {
				return false;
			}
		}

		return true;
	}

	protected function get_bidirectional_match_metrics( $selected, $profile ) {
		$selected = $this->normalize_key_list( $selected );
		$profile  = $this->normalize_key_list( $profile );
		$matches  = array_values( array_intersect( $selected, $profile ) );

		if ( empty( $selected ) || empty( $profile ) ) {
			return array(
				'matches'   => array(),
				'coverage'  => 0,
				'precision' => 0,
				'ratio'     => 0,
				'is_exact'  => false,
			);
		}

		$coverage  = count( $matches ) / max( 1, count( $profile ) );
		$precision = count( $matches ) / max( 1, count( $selected ) );

		// Coverage ist wichtiger: Ein Parfum mit 4 gepflegten Noten soll nicht voll treffen,
		// wenn der Nutzer nur 1 davon auswählt. Precision bestraft zusätzliche Fremdnoten.
		$ratio = ( $coverage * 0.78 ) + ( $precision * 0.22 );

		return array(
			'matches'   => $matches,
			'coverage'  => max( 0, min( 1, $coverage ) ),
			'precision' => max( 0, min( 1, $precision ) ),
			'ratio'     => max( 0, min( 1, $ratio ) ),
			'is_exact'  => ( count( $matches ) === count( $profile ) && count( $matches ) === count( $selected ) ),
		);
	}

	protected function calculate_tie_breaker( $parfum, $family_metrics, $note_metrics, $full_parts ) {
		$full_count = 0;
		foreach ( (array) $full_parts as $is_full ) {
			if ( $is_full ) {
				$full_count++;
			}
		}

		$profile_width = count( $this->normalize_key_list( $parfum['duftrichtungen'] ?? array() ) ) + count( $this->normalize_key_list( $parfum['duftnoten'] ?? array() ) );
		$compact_bonus = $profile_width > 0 ? min( 1, 1 / $profile_width ) : 0;

		return ( $full_count * 10 ) + ( $note_metrics['coverage'] * 4 ) + ( $note_metrics['precision'] * 2 ) + ( $family_metrics['coverage'] * 2 ) + $compact_bonus;
	}

	protected function normalize_key_list( $values ) {
		$out = array();
		foreach ( (array) $values as $value ) {
			$parts = preg_split( '/[,;|\/]+/', (string) $value );
			foreach ( (array) $parts as $part ) {
				$key = sanitize_key( trim( (string) $part ) );
				if ( '' !== $key ) {
					$out[] = $key;
				}
			}
		}
		return array_values( array_unique( array_filter( $out ) ) );
	}

	protected function normalize_gender_key( $value ) {
		$value = remove_accents( (string) $value );
		$value = strtolower( trim( $value ) );
		$value = str_replace( array( '/', '+', '&', '-' ), ' ', $value );
		$value = preg_replace( '/[^a-z0-9]+/', '_', $value );
		$value = trim( $value, '_' );
		$value = sanitize_key( $value );

		$aliases = array(
			'mann'        => 'herren',
			'maenner'     => 'herren',
			'manner'      => 'herren',
			'maennlich'   => 'herren',
			'mannlich'    => 'herren',
			'male'        => 'herren',
			'men'         => 'herren',
			'herren'      => 'herren',
			'fuer_herren' => 'herren',
			'fur_herren'  => 'herren',
			'homme'       => 'herren',
			'dame'        => 'damen',
			'damen'       => 'damen',
			'fuer_damen'  => 'damen',
			'fur_damen'   => 'damen',
			'frau'        => 'damen',
			'frauen'      => 'damen',
			'weiblich'    => 'damen',
			'female'      => 'damen',
			'women'       => 'damen',
			'woman'       => 'damen',
			'femme'       => 'damen',
			'unisex'      => 'unisex',
			'universal'   => 'unisex',
			'alle'        => 'unisex',
		);

		return $aliases[ $value ] ?? $value;
	}

	protected function normalize_gender_list( $values ) {
		$normalized = array();

		foreach ( (array) $values as $value ) {
			$parts = preg_split( '/[,;|\/]+/', (string) $value );
			foreach ( (array) $parts as $part ) {
				$key = $this->normalize_gender_key( $part );
				if ( in_array( $key, array( 'herren', 'damen', 'unisex' ), true ) ) {
					$normalized[] = $key;
				}
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	protected function is_gender_compatible( $selected_gender, $parfum_genders ) {
		$selected_gender = $this->normalize_gender_key( $selected_gender );
		$parfum_genders  = $this->normalize_gender_list( $parfum_genders );

		if ( ! $selected_gender ) {
			return true;
		}

		if ( ! in_array( $selected_gender, array( 'herren', 'damen', 'unisex' ), true ) || empty( $parfum_genders ) ) {
			return false;
		}

		// Strikte Geschlechts-Regel:
		// Unisex-Parfums dürfen nur erscheinen, wenn der Besucher wirklich Unisex auswählt.
		// Bei Herren/Damen werden Unisex-Parfums sicher ausgeschlossen.
		if ( 'unisex' === $selected_gender ) {
			return in_array( 'unisex', $parfum_genders, true );
		}

		if ( in_array( 'unisex', $parfum_genders, true ) ) {
			return false;
		}

		return in_array( $selected_gender, $parfum_genders, true );
	}

	protected function get_labels_from_values( $question_key, $values, $limit = 2 ) {
		$labels = array();
		foreach ( array_slice( array_values( array_unique( (array) $values ) ), 0, $limit ) as $value ) {
			$labels[] = strtolower( $this->plugin->get_option_label( $question_key, $value ) );
		}
		return array_values( array_filter( $labels ) );
	}

	protected function generate_reason( $answers, $highlights, $reasons ) {
		$segments = array();
		if ( ! empty( $answers['jahreszeit'] ) ) {
			$segments[] = 'du für den ' . strtolower( $this->plugin->get_option_label( 'jahreszeit', $answers['jahreszeit'] ) ) . ' suchst';
		}
		if ( ! empty( $answers['verwendungsbereich'] ) ) {
			$segments[] = 'der Duft für ' . strtolower( $this->plugin->get_option_label( 'verwendungsbereich', $answers['verwendungsbereich'] ) ) . ' funktionieren soll';
		}
		$opening = ! empty( $segments ) ? 'Passt besonders gut, weil ' . implode( ' und ', $segments ) . '. ' : 'Passt besonders gut zu deinen Angaben. ';

		$detail_pool = array_values( array_unique( array_filter( array_merge( $highlights, $reasons ) ) ) );
		if ( empty( $detail_pool ) ) {
			return trim( $opening );
		}

		return trim( $opening . 'Dafür spricht vor allem, dass der Duft ' . implode( ', ', array_slice( $detail_pool, 0, 3 ) ) . '.' );
	}
}
