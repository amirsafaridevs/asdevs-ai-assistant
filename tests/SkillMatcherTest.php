<?php
/**
 * Ranking tests for the skill matcher.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Tests;

use ASDevs\AIAssistant\Services\Skills\SkillMatcher;
use PHPUnit\Framework\TestCase;

/**
 * The assistant picks its own skills from these scores, so the order matters
 * more than any single number does.
 */
final class SkillMatcherTest extends TestCase {

	/**
	 * Subject.
	 *
	 * @var SkillMatcher
	 */
	private SkillMatcher $matcher;

	/**
	 * Set up.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->matcher = new SkillMatcher();
	}

	/**
	 * A skill whose trigger line names the job wins over one that merely mentions it.
	 */
	public function test_when_to_use_outranks_a_passing_mention(): void {
		$ranked = $this->matcher->rank(
			array(
				$this->skill(
					'weekly-report',
					'Weekly report',
					'Write the Monday traffic report.',
					'When the person asks for the weekly report.'
				),
				$this->skill(
					'house-style',
					'House style',
					'Tone rules for everything, including the weekly report.',
					'When writing any copy.'
				),
			),
			'can you do the weekly report'
		);

		$this->assertNotSame( array(), $ranked );
		$this->assertSame( 'weekly-report', $ranked[0]['slug'] );
	}

	/**
	 * Nothing relevant means nothing returned — an unrelated skill is worse than none.
	 */
	public function test_unrelated_query_matches_nothing(): void {
		$ranked = $this->matcher->rank(
			array( $this->skill( 'weekly-report', 'Weekly report', 'Write the Monday traffic report.', 'When the person asks for the weekly report.' ) ),
			'reset the smtp password'
		);

		$this->assertSame( array(), $ranked );
	}

	/**
	 * Persian queries match Persian skills, including the Arabic ye/kaf people type.
	 */
	public function test_persian_query_matches_persian_skill(): void {
		$ranked = $this->matcher->rank(
			array(
				$this->skill(
					'product-copy',
					'توضیحات محصول',
					'نوشتن توضیحات محصول برای فروشگاه.',
					'وقتی کاربر توضیحات محصول می‌خواهد.'
				),
				$this->skill( 'seo-audit', 'SEO audit', 'Check titles and meta.', 'When asked about SEO.' ),
			),
			'يك توضيحات محصول بنويس'
		);

		$this->assertNotSame( array(), $ranked );
		$this->assertSame( 'product-copy', $ranked[0]['slug'] );
	}

	/**
	 * Keywords are how an author teaches the matcher their own vocabulary.
	 */
	public function test_keywords_bring_in_synonyms(): void {
		$ranked = $this->matcher->rank(
			array(
				$this->skill(
					'product-copy',
					'Product descriptions',
					'Write shop copy.',
					'When writing for the shop.',
					'woocommerce, listing, sku'
				),
			),
			'fix the sku listing text'
		);

		$this->assertNotSame( array(), $ranked );
		$this->assertContains( 'keywords', $ranked[0]['matched_on'] );
	}

	/**
	 * A word every skill uses cannot pick one of them, in any language, and the
	 * matcher works that out from the skills rather than from a stop-word list.
	 */
	public function test_a_word_common_to_every_skill_does_not_decide_the_order(): void {
		$skills = array(
			$this->skill( 'faktura', 'Faktura erstellen', 'Erstelle eine Rechnung für den Kunden.', 'Wenn der Kunde eine Rechnung braucht.' ),
			$this->skill( 'versand', 'Versand prüfen', 'Prüfe den Versand für den Kunden.', 'Wenn der Kunde nach dem Versand fragt.' ),
			$this->skill( 'newsletter', 'Newsletter schreiben', 'Schreibe den Newsletter für den Kunden.', 'Wenn der Kunde einen Newsletter will.' ),
		);

		$ranked = $this->matcher->rank( $skills, 'wenn der kunde den versand braucht' );

		$this->assertNotSame( array(), $ranked );
		$this->assertSame( 'versand', $ranked[0]['slug'] );
	}

	/**
	 * Brushing against one throwaway word is not a match worth suggesting.
	 */
	public function test_a_single_shared_filler_word_is_not_a_match(): void {
		$ranked = $this->matcher->rank(
			array(
				$this->skill( 'weekly-report', 'Weekly report', 'Write the Monday traffic report.', 'When the person asks for the weekly report.' ),
			),
			'reset the smtp password for the mail server'
		);

		$this->assertSame( array(), $ranked );
	}

	/**
	 * Marks that hang off a letter are optional to the person typing, in every
	 * script that has them, so they are optional to the matcher too.
	 */
	public function test_accents_and_marks_are_optional_on_either_side(): void {
		$skills = array(
			$this->skill( 'resume', 'Résumé prüfen', 'Prüfe den Lebenslauf.', 'Wenn jemand einen Lebenslauf schickt.' ),
			$this->skill( 'versand', 'Versand', 'Sendungen.', 'Beim Versand.' ),
		);

		$ranked = $this->matcher->rank( $skills, 'resume prufen' );

		$this->assertNotSame( array(), $ranked );
		$this->assertSame( 'resume', $ranked[0]['slug'] );
	}

	/**
	 * A number is the same number whichever numerals it was typed in.
	 */
	public function test_digits_match_across_numeral_systems(): void {
		if ( ! class_exists( '\IntlChar' ) ) {
			$this->markTestSkipped( 'Digit folding needs ext-intl.' );
		}

		$ranked = $this->matcher->rank(
			array(
				$this->skill( 'top-10', 'Top 10 posts', 'List the ten best posts.', 'When someone asks for the top 10 posts.' ),
				$this->skill( 'top-20', 'Top 20 posts', 'List the twenty best posts.', 'When someone asks for the top 20 posts.' ),
			),
			'top ۲۰ posts'
		);

		$this->assertNotSame( array(), $ranked );
		$this->assertSame( 'top-20', $ranked[0]['slug'] );
	}

	/**
	 * An empty query returns the list as given, so the model can still browse.
	 */
	public function test_empty_query_returns_the_head_of_the_list(): void {
		$skills = array(
			$this->skill( 'one', 'One', 'First.', '' ),
			$this->skill( 'two', 'Two', 'Second.', '' ),
		);

		$ranked = $this->matcher->rank( $skills, '   ', 1 );

		$this->assertCount( 1, $ranked );
		$this->assertSame( 'one', $ranked[0]['slug'] );
	}

	/**
	 * Build a skill row.
	 *
	 * @param string $slug     Slug.
	 * @param string $title    Title.
	 * @param string $prompt   Prompt body.
	 * @param string $when     When-to-use line.
	 * @param string $keywords Keywords.
	 *
	 * @return array<string, mixed>
	 */
	private function skill( string $slug, string $title, string $prompt, string $when, string $keywords = '' ): array {
		return array(
			'slug'        => $slug,
			'title'       => $title,
			'prompt'      => $prompt,
			'description' => '',
			'when_to_use' => $when,
			'keywords'    => $keywords,
		);
	}
}
