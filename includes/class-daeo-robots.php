<?php
/**
 * The robots.txt additions: per-bot AI crawler policy and the sitemap line.
 *
 * @package DemfilzAISEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Filters the virtual robots.txt WordPress serves.
 */
class DAEO_Robots {

	/**
	 * Hook the robots.txt filter.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'robots_txt', array( __CLASS__, 'filter' ), 20, 2 );
	}

	/**
	 * The known AI crawlers the plugin can allow or block.
	 *
	 * Keys are option keys; agent = the User-agent token; type groups the
	 * bot for the settings screen.
	 *
	 * @return array
	 */
	public static function bots() {
		return array(
			'gptbot'             => array(
				'agent' => 'GPTBot',
				'label' => 'OpenAI GPTBot',
				'type'  => 'training',
			),
			'oai_searchbot'      => array(
				'agent' => 'OAI-SearchBot',
				'label' => 'OpenAI SearchBot (ChatGPT search)',
				'type'  => 'search',
			),
			'chatgpt_user'       => array(
				'agent' => 'ChatGPT-User',
				'label' => 'ChatGPT-User (on-demand visits)',
				'type'  => 'assistant',
			),
			'claudebot'          => array(
				'agent' => 'ClaudeBot',
				'label' => 'Anthropic ClaudeBot',
				'type'  => 'training',
			),
			'claude_web'         => array(
				'agent' => 'Claude-Web',
				'label' => 'Claude-Web (on-demand visits)',
				'type'  => 'assistant',
			),
			'anthropic_ai'       => array(
				'agent' => 'anthropic-ai',
				'label' => 'anthropic-ai (legacy)',
				'type'  => 'training',
			),
			'perplexitybot'      => array(
				'agent' => 'PerplexityBot',
				'label' => 'PerplexityBot',
				'type'  => 'search',
			),
			'perplexity_user'    => array(
				'agent' => 'Perplexity-User',
				'label' => 'Perplexity-User (on-demand visits)',
				'type'  => 'assistant',
			),
			'google_extended'    => array(
				'agent' => 'Google-Extended',
				'label' => 'Google-Extended (Gemini training)',
				'type'  => 'training',
			),
			'applebot_extended'  => array(
				'agent' => 'Applebot-Extended',
				'label' => 'Applebot-Extended (Apple AI training)',
				'type'  => 'training',
			),
			'ccbot'              => array(
				'agent' => 'CCBot',
				'label' => 'Common Crawl CCBot',
				'type'  => 'training',
			),
			'bytespider'         => array(
				'agent' => 'Bytespider',
				'label' => 'ByteDance Bytespider',
				'type'  => 'training',
			),
			'meta_externalagent' => array(
				'agent' => 'meta-externalagent',
				'label' => 'Meta external agent',
				'type'  => 'training',
			),
			'amazonbot'          => array(
				'agent' => 'Amazonbot',
				'label' => 'Amazonbot',
				'type'  => 'training',
			),
			'cohere_ai'          => array(
				'agent' => 'cohere-ai',
				'label' => 'Cohere',
				'type'  => 'training',
			),
		);
	}

	/**
	 * The stored policy for a bot: '' (default, no line), allow or block.
	 *
	 * @param string $key Bot option key.
	 * @return string
	 */
	public static function policy( $key ) {
		$bots  = (array) DAEO_Settings::get( 'bots', array() );
		$value = isset( $bots[ $key ] ) ? (string) $bots[ $key ] : '';
		return in_array( $value, array( 'allow', 'block' ), true ) ? $value : '';
	}

	/**
	 * Append the AI crawler policy and sitemap line to robots.txt.
	 *
	 * @param string $output    Current robots.txt body.
	 * @param bool   $is_public Whether the site is public.
	 * @return string
	 */
	public static function filter( $output, $is_public ) {
		if ( ! $is_public ) {
			return $output;
		}

		$lines = array();
		foreach ( self::bots() as $key => $bot ) {
			$policy = self::policy( $key );
			if ( 'block' === $policy ) {
				$lines[] = 'User-agent: ' . $bot['agent'];
				$lines[] = 'Disallow: /';
				$lines[] = '';
			} elseif ( 'allow' === $policy ) {
				$lines[] = 'User-agent: ' . $bot['agent'];
				$lines[] = 'Allow: /';
				$lines[] = '';
			}
		}

		if ( ! empty( $lines ) ) {
			$output .= "\n# AI crawler policy (Demfilz AI SEO)\n" . implode( "\n", $lines );
		}

		if ( DAEO_Settings::module_on( 'sitemap' ) ) {
			$output .= "\nSitemap: " . home_url( '/sitemap.xml' ) . "\n";
		}
		if ( DAEO_Settings::module_on( 'llms' ) ) {
			$output .= "\n# LLM guidance file\n# " . home_url( '/llms.txt' ) . "\n";
		}

		return $output;
	}
}
