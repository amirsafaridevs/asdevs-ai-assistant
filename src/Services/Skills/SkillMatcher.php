<?php
/**
 * Ranks skills against a natural-language query.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Services\Skills;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A small lexical ranker, on purpose.
 *
 * The assistant does not need embeddings to pick a skill out of a list a site
 * owner wrote by hand — a hundred rows at most. What it needs is a ranking that
 * works in whatever language the person writes in, so nothing here is taught
 * about any particular language: tokens are split on unicode letter boundaries
 * rather than on English word rules, and a word counts for as much as it can
 * tell the skills apart. A word every skill uses says nothing about which one
 * was meant, whether it is "the" or "برای" or a word in a language nobody here
 * has heard of; the matcher learns that from the skills themselves instead of
 * from a stop-word list it would have to carry per language.
 */
final class SkillMatcher {

	/**
	 * Field weights, highest intent first.
	 */
	private const WEIGHTS = array(
		'when_to_use' => 6.0,
		'title'       => 5.0,
		'keywords'    => 4.5,
		'description' => 3.0,
		'slug'        => 3.0,
		'prompt'      => 1.0,
	);

	/**
	 * Tokens shorter than this only count on an exact match.
	 */
	private const PREFIX_MIN = 3;

	/**
	 * Below this a match is noise, not a suggestion.
	 */
	private const MIN_SCORE = 1.5;

	/**
	 * Share of the query a skill has to account for before it is a candidate.
	 *
	 * With a handful of skills there is not enough corpus yet for frequency to
	 * mean much, and a query can still brush against a skill through one
	 * throwaway word. Asking for a third of the query's weight is the part of
	 * the filter that holds up on day one, before the list has grown.
	 */
	private const MIN_COVERAGE = 0.34;

	/**
	 * Rank skills against a query.
	 *
	 * @param array<int, array<string, mixed>> $skills Skill rows from the store.
	 * @param string                           $query  What the person asked for.
	 * @param int                              $limit  How many rows to return.
	 *
	 * @return array<int, array<string, mixed>> Matching rows with `score` and `matched_on`.
	 */
	public function rank( array $skills, string $query, int $limit = 5 ): array {
		$limit  = max( 1, min( 20, $limit ) );
		$tokens = $this->tokenize( $query );

		if ( array() === $tokens ) {
			return array_slice( $skills, 0, $limit );
		}

		$skills    = array_values( $skills );
		$documents = array_map( array( $this, 'document' ), $skills );
		$weights   = $this->token_weights( $documents, $tokens );
		$total     = array_sum( $weights );

		if ( $total <= 0.0 ) {
			return array();
		}

		$phrase = $this->normalize( $query );
		$scored = array();

		foreach ( $skills as $index => $skill ) {
			$result = $this->score( $documents[ $index ], $tokens, $weights, $total, $phrase );

			if ( $result['score'] < self::MIN_SCORE ) {
				continue;
			}

			$skill['score']      = round( $result['score'], 2 );
			$skill['matched_on'] = $result['fields'];

			$scored[] = $skill;
		}

		usort(
			$scored,
			static function ( array $left, array $right ): int {
				return (float) $right['score'] <=> (float) $left['score'];
			}
		);

		return array_slice( $scored, 0, $limit );
	}

	/**
	 * How much each query token is worth, judged against this set of skills.
	 *
	 * A token that turns up in every skill cannot point at one of them, so its
	 * weight decays as it spreads; a token no skill uses keeps full weight, which
	 * is what makes an off-topic query fail to reach the coverage floor rather
	 * than scraping through on its one common word.
	 *
	 * @param array<int, array<string, string>> $documents Normalized skill text by field.
	 * @param array<int, string>                $tokens    Query tokens.
	 *
	 * @return array<string, float>
	 */
	private function token_weights( array $documents, array $tokens ): array {
		$count   = max( 1, count( $documents ) );
		$weights = array();

		foreach ( $tokens as $token ) {
			$spread = 0;

			foreach ( $documents as $fields ) {
				foreach ( $fields as $haystack ) {
					if ( $this->contains( $haystack, $token ) ) {
						++$spread;
						break;
					}
				}
			}

			$weights[ $token ] = log( 1 + $count / max( 1, $spread ) );
		}

		return $weights;
	}

	/**
	 * Score one skill.
	 *
	 * @param array<string, string> $document Normalized skill text by field.
	 * @param array<int, string>    $tokens   Query tokens.
	 * @param array<string, float>  $weights  Weight per token.
	 * @param float                 $total    Weight of the whole query.
	 * @param string                $phrase   Whole normalized query.
	 *
	 * @return array{score: float, fields: array<int, string>}
	 */
	private function score( array $document, array $tokens, array $weights, float $total, string $phrase ): array {
		$score   = 0.0;
		$fields  = array();
		$matched = array();

		foreach ( self::WEIGHTS as $field => $weight ) {
			$haystack = $document[ $field ] ?? '';

			if ( '' === $haystack ) {
				continue;
			}

			$hit = 0.0;

			foreach ( $tokens as $token ) {
				if ( $this->contains( $haystack, $token ) ) {
					$hit              += $weights[ $token ];
					$matched[ $token ] = $weights[ $token ];
				}
			}

			if ( $hit <= 0.0 ) {
				continue;
			}

			$fields[] = $field;

			// Every extra term that lands in the same field is stronger evidence
			// than the first one was, so coverage counts more than repetition.
			$coverage = $hit / $total;
			$score   += $weight * $coverage * ( 1 + $coverage );

			// The author's exact phrase showing up verbatim is the clearest signal there is.
			if ( '' !== $phrase && false !== strpos( $haystack, $phrase ) ) {
				$score += $weight;
			}
		}

		// One incidental word shared with the query is not a match, however
		// heavily the field it landed in is weighted.
		if ( array_sum( $matched ) / $total < self::MIN_COVERAGE ) {
			return array(
				'score'  => 0.0,
				'fields' => array(),
			);
		}

		return array(
			'score'  => $score,
			'fields' => $fields,
		);
	}

	/**
	 * Normalized text of every searchable field, built once per skill.
	 *
	 * @param array<string, mixed> $skill Skill row.
	 *
	 * @return array<string, string>
	 */
	private function document( array $skill ): array {
		$document = array();

		foreach ( array_keys( self::WEIGHTS ) as $field ) {
			$document[ $field ] = $this->normalize( $this->field_text( $skill, $field ) );
		}

		return $document;
	}

	/**
	 * Text of one searchable field.
	 *
	 * @param array<string, mixed> $skill Skill row.
	 * @param string               $field Field name.
	 */
	private function field_text( array $skill, string $field ): string {
		$value = $skill[ $field ] ?? '';

		if ( is_array( $value ) ) {
			return implode( ' ', array_map( 'strval', $value ) );
		}

		return (string) $value;
	}

	/**
	 * Does the haystack hold this token — as a whole word, or as a prefix for longer ones?
	 *
	 * @param string $haystack Normalized text.
	 * @param string $token    Normalized token.
	 */
	private function contains( string $haystack, string $token ): bool {
		if ( false === strpos( $haystack, $token ) ) {
			return false;
		}

		if ( mb_strlen( $token ) >= self::PREFIX_MIN ) {
			return true;
		}

		// Two-letter tokens match too much; require a word of their own.
		return 1 === preg_match( '/(?:^|\s)' . preg_quote( $token, '/' ) . '(?:\s|$)/u', $haystack );
	}

	/**
	 * Split text into comparable tokens, in any script.
	 *
	 * @param string $text Raw text.
	 *
	 * @return array<int, string>
	 */
	private function tokenize( string $text ): array {
		$parts = preg_split( '/[^\p{L}\p{N}]+/u', $this->normalize( $text ) );

		if ( ! is_array( $parts ) ) {
			return array();
		}

		$tokens = array();

		foreach ( $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}

			$tokens[ $part ] = true;
		}

		// A token of digits alone comes back from array_keys() as an int.
		return array_map( 'strval', array_slice( array_keys( $tokens ), 0, 24 ) );
	}

	/**
	 * Reduce text to the form two people typing the same thing would agree on.
	 *
	 * Nobody types a word twice the same way. They skip the accent, leave the
	 * harakat off, paste a full-width character, type a digit in their own
	 * numerals. Unicode already describes all of that, so the rules here are the
	 * Unicode ones and they hold in every script: decompose, drop the marks that
	 * hang off a letter, drop the characters that were never meant to be seen,
	 * fold digits to their value. None of it needs to know which language it is
	 * looking at.
	 *
	 * @param string $text Raw text.
	 */
	private function normalize( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = $this->decompose( $text );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );

		// Marks that hang off a letter: Latin accents, Arabic harakat, Hebrew
		// niqqud, Vietnamese tones. Optional to the reader, so optional here.
		$text = preg_replace( '/\p{Mn}+/u', '', $text ) ?? $text;

		// Characters with no glyph of their own — zero-width joiners, bidi marks,
		// soft hyphens, and the Arabic tatweel that only stretches a word. At most
		// they mark where one word ends.
		$text = preg_replace( '/[\p{Cf}\x{0640}]+/u', ' ', $text ) ?? $text;

		$text = $this->fold_digits( $text );
		$text = strtr( $text, $this->foldings() );
		$text = $this->recompose( $text );

		return trim( preg_replace( '/\s+/u', ' ', $text ) ?? '' );
	}

	/**
	 * Letters Unicode keeps apart that a keyboard does not.
	 *
	 * What survives the rules above is the short list of cases where one letter
	 * has two codepoints because two keyboard layouts disagree — an Arabic
	 * layout and a Persian one put different characters under the same key, and
	 * a person switching between them means the same letter both times. There is
	 * no general rule for that, only pairs, so this is a table other scripts can
	 * add their own pairs to rather than something the matcher pretends to know.
	 *
	 * @return array<string, string>
	 */
	private function foldings(): array {
		$foldings = array(
			'ي' => 'ی',
			'ى' => 'ی',
			'ك' => 'ک',
			'ة' => 'ه',
		);

		/**
		 * Filter the characters the skill matcher treats as the same letter.
		 *
		 * @param array<string, string> $foldings Map of character to replacement.
		 */
		$filtered = apply_filters( 'asdevs_ai_assistant_skill_foldings', $foldings );

		return is_array( $filtered ) ? $filtered : $foldings;
	}

	/**
	 * Write every decimal digit in ASCII, whatever numerals it arrived in.
	 *
	 * @param string $text Text.
	 */
	private function fold_digits( string $text ): string {
		if ( ! class_exists( '\IntlChar' ) || 1 !== preg_match( '/\p{Nd}/u', $text ) ) {
			return $text;
		}

		$folded = preg_replace_callback(
			'/\p{Nd}/u',
			static function ( array $digit ): string {
				$value = \IntlChar::charDigitValue( $digit[0] );

				return -1 === $value ? $digit[0] : (string) $value;
			},
			$text
		);

		return is_string( $folded ) ? $folded : $text;
	}

	/**
	 * Split characters into base letters and their marks, so the marks can go.
	 *
	 * @param string $text Text.
	 */
	private function decompose( string $text ): string {
		if ( ! class_exists( '\Normalizer' ) ) {
			return $text;
		}

		$decomposed = \Normalizer::normalize( $text, \Normalizer::FORM_KD );

		return is_string( $decomposed ) ? $decomposed : $text;
	}

	/**
	 * Put the base letters back together, so equal strings compare equal.
	 *
	 * @param string $text Text.
	 */
	private function recompose( string $text ): string {
		if ( ! class_exists( '\Normalizer' ) ) {
			return $text;
		}

		$composed = \Normalizer::normalize( $text, \Normalizer::FORM_C );

		return is_string( $composed ) ? $composed : $text;
	}
}
