<?php
/**
 * Alt text generation and cleanup.
 *
 * @package Post_Link_Drop
 */

namespace Post_Link_Drop;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Alt Text class.
 */
class Alt_Text {

	/**
	 * Maximum alt text length.
	 */
	const MAX_LENGTH = 125;

	/**
	 * Generate alt text from caption.
	 *
	 * @param string $caption   Raw caption from Instagram.
	 * @param string $timestamp Optional timestamp for fallback.
	 * @return string Generated alt text.
	 */
	public function generate( $caption, $timestamp = '' ) {
		if ( empty( $caption ) ) {
			return $this->get_fallback( $timestamp );
		}

		$text = $caption;

		// Remove URLs.
		$text = preg_replace( '/https?:\/\/[^\s]+/i', '', $text );

		// Remove hashtags.
		$text = preg_replace( '/#[\w\-]+/u', '', $text );

		// Remove mentions.
		$text = preg_replace( '/@[\w\-\.]+/u', '', $text );

		// Optionally remove emojis (keep them minimal for accessibility).
		$text = $this->strip_emojis( $text );

		// Normalize whitespace.
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		// If empty after cleanup, use fallback.
		if ( empty( $text ) ) {
			return $this->get_fallback( $timestamp );
		}

		// Truncate without cutting words.
		$text = $this->truncate( $text, self::MAX_LENGTH );

		return $text;
	}

	/**
	 * Strip emojis from text.
	 *
	 * @param string $text Input text.
	 * @return string Text without emojis.
	 */
	private function strip_emojis( $text ) {
		// Remove most emoji ranges.
		$emoji_pattern = '/[\x{1F600}-\x{1F64F}' . // Emoticons
			'\x{1F300}-\x{1F5FF}' . // Misc symbols and pictographs
			'\x{1F680}-\x{1F6FF}' . // Transport and map symbols
			'\x{1F1E0}-\x{1F1FF}' . // Flags
			'\x{2600}-\x{26FF}' .   // Misc symbols
			'\x{2700}-\x{27BF}' .   // Dingbats
			'\x{FE00}-\x{FE0F}' .   // Variation selectors
			'\x{1F900}-\x{1F9FF}' . // Supplemental symbols
			'\x{1FA00}-\x{1FA6F}' . // Chess symbols
			'\x{1FA70}-\x{1FAFF}' . // Symbols and pictographs extended
			'\x{231A}\x{231B}' .    // Watch, hourglass
			'\x{23E9}-\x{23F3}' .   // Various symbols
			'\x{23F8}-\x{23FA}' .   // Various symbols
			'\x{25AA}\x{25AB}\x{25B6}\x{25C0}\x{25FB}-\x{25FE}' . // Geometric shapes
			'\x{2614}\x{2615}' .    // Umbrella, hot beverage
			'\x{2648}-\x{2653}' .   // Zodiac
			'\x{267F}' .            // Wheelchair
			'\x{2693}' .            // Anchor
			'\x{26A1}' .            // High voltage
			'\x{26AA}\x{26AB}' .    // Circles
			'\x{26BD}\x{26BE}' .    // Soccer, baseball
			'\x{26C4}\x{26C5}' .    // Snowman, sun
			'\x{26CE}' .            // Ophiuchus
			'\x{26D4}' .            // No entry
			'\x{26EA}' .            // Church
			'\x{26F2}\x{26F3}' .    // Fountain, golf
			'\x{26F5}' .            // Sailboat
			'\x{26FA}' .            // Tent
			'\x{26FD}' .            // Fuel pump
			'\x{2702}' .            // Scissors
			'\x{2705}' .            // Check mark
			'\x{2708}-\x{270D}' .   // Various
			'\x{270F}' .            // Pencil
			'\x{2712}' .            // Black nib
			'\x{2714}' .            // Check mark
			'\x{2716}' .            // X mark
			'\x{271D}' .            // Latin cross
			'\x{2721}' .            // Star of David
			'\x{2728}' .            // Sparkles
			'\x{2733}\x{2734}' .    // Eight spoked asterisk
			'\x{2744}' .            // Snowflake
			'\x{2747}' .            // Sparkle
			'\x{274C}' .            // Cross mark
			'\x{274E}' .            // Cross mark
			'\x{2753}-\x{2755}' .   // Question marks
			'\x{2757}' .            // Exclamation mark
			'\x{2763}\x{2764}' .    // Heart
			'\x{2795}-\x{2797}' .   // Plus, minus, division
			'\x{27A1}' .            // Right arrow
			'\x{27B0}' .            // Curly loop
			'\x{27BF}' .            // Double curly loop
			'\x{2934}\x{2935}' .    // Arrows
			'\x{2B05}-\x{2B07}' .   // Arrows
			'\x{2B1B}\x{2B1C}' .    // Squares
			'\x{2B50}' .            // Star
			'\x{2B55}' .            // Circle
			'\x{3030}' .            // Wavy dash
			'\x{303D}' .            // Part alternation mark
			'\x{3297}' .            // Circled ideograph congratulation
			'\x{3299}' .            // Circled ideograph secret
			']/u';

		return preg_replace( $emoji_pattern, '', $text );
	}

	/**
	 * Truncate text without cutting words.
	 *
	 * @param string $text      Text to truncate.
	 * @param int    $max_length Maximum length.
	 * @param string $suffix    Suffix to add if truncated.
	 * @return string Truncated text.
	 */
	private function truncate( $text, $max_length, $suffix = '...' ) {
		if ( mb_strlen( $text ) <= $max_length ) {
			return $text;
		}

		// Find the last space within the limit.
		$truncated = mb_substr( $text, 0, $max_length - mb_strlen( $suffix ) );
		$last_space = mb_strrpos( $truncated, ' ' );

		if ( false !== $last_space ) {
			$truncated = mb_substr( $truncated, 0, $last_space );
		}

		// Remove trailing punctuation.
		$truncated = rtrim( $truncated, '.,;:!?' );

		return $truncated . $suffix;
	}

	/**
	 * Get fallback alt text.
	 *
	 * @param string $timestamp Optional timestamp.
	 * @return string Fallback text.
	 */
	private function get_fallback( $timestamp = '' ) {
		if ( ! empty( $timestamp ) ) {
			$date = date_i18n( 'Y-m-d', strtotime( $timestamp ) );
			/* translators: %s: date */
			return sprintf( __( 'Instagram post from %s', 'post-link-drop' ), $date );
		}

		return __( 'Instagram post', 'post-link-drop' );
	}

	/**
	 * Clean and improve existing alt text.
	 *
	 * @param string $alt_text Existing alt text.
	 * @return string Cleaned alt text.
	 */
	public function clean( $alt_text ) {
		$text = trim( $alt_text );

		// Normalize whitespace.
		$text = preg_replace( '/\s+/', ' ', $text );

		// Truncate if needed.
		$text = $this->truncate( $text, self::MAX_LENGTH );

		return $text;
	}
}
