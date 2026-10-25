<?php
/**
 * Small HTML to Markdown converter used for the LLM markdown mirrors.
 *
 * @package DemfilzAISEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Converts rendered post HTML into readable Markdown.
 */
class DAEO_Markdown {

	/**
	 * Convert an HTML fragment to Markdown.
	 *
	 * @param string $html HTML fragment.
	 * @return string
	 */
	public static function convert( $html ) {
		$html = (string) $html;
		if ( '' === trim( $html ) ) {
			return '';
		}

		if ( ! class_exists( 'DOMDocument' ) ) {
			return trim( wp_strip_all_tags( $html ) );
		}

		$doc      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded   = $doc->loadHTML(
			'<?xml encoding="UTF-8"><div id="daeo-md-root">' . $html . '</div>',
			LIBXML_NOWARNING | LIBXML_NOERROR
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return trim( wp_strip_all_tags( $html ) );
		}

		$root = $doc->getElementById( 'daeo-md-root' );
		if ( ! $root ) {
			return trim( wp_strip_all_tags( $html ) );
		}

		$markdown = self::children( $root, 0 );
		$markdown = preg_replace( "/\n{3,}/", "\n\n", $markdown );
		return trim( $markdown );
	}

	/**
	 * Render the children of a node.
	 *
	 * @param DOMNode $node  Parent node.
	 * @param int     $depth List nesting depth.
	 * @return string
	 */
	private static function children( $node, $depth ) {
		$out = '';
		foreach ( $node->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
			$out .= self::node( $child, $depth );
		}
		return $out;
	}

	/**
	 * Render a single node.
	 *
	 * @param DOMNode $node  Node.
	 * @param int     $depth List nesting depth.
	 * @return string
	 */
	private static function node( $node, $depth ) {
		if ( XML_TEXT_NODE === $node->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
			return preg_replace( '/\s+/', ' ', $node->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
		}
		if ( XML_ELEMENT_NODE !== $node->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
			return '';
		}

		$tag = strtolower( $node->nodeName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.

		switch ( $tag ) {
			case 'script':
			case 'style':
			case 'noscript':
			case 'iframe':
			case 'form':
			case 'svg':
				return '';

			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$level = (int) substr( $tag, 1 );
				return "\n\n" . str_repeat( '#', $level ) . ' ' . trim( self::children( $node, $depth ) ) . "\n\n";

			case 'p':
				$text = trim( self::children( $node, $depth ) );
				return '' === $text ? '' : "\n\n" . $text . "\n\n";

			case 'br':
				return "\n";

			case 'hr':
				return "\n\n---\n\n";

			case 'strong':
			case 'b':
				$text = trim( self::children( $node, $depth ) );
				return '' === $text ? '' : '**' . $text . '**';

			case 'em':
			case 'i':
				$text = trim( self::children( $node, $depth ) );
				return '' === $text ? '' : '*' . $text . '*';

			case 'code':
				return '`' . trim( $node->textContent ) . '`'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.

			case 'pre':
				return "\n\n```\n" . trim( $node->textContent ) . "\n```\n\n"; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.

			case 'blockquote':
				$text  = trim( self::children( $node, $depth ) );
				$lines = array();
				foreach ( explode( "\n", $text ) as $line ) {
					$line = trim( $line );
					if ( '' !== $line ) {
						$lines[] = '> ' . $line;
					}
				}
				return empty( $lines ) ? '' : "\n\n" . implode( "\n", $lines ) . "\n\n";

			case 'a':
				$href = trim( (string) $node->getAttribute( 'href' ) );
				$text = trim( self::children( $node, $depth ) );
				if ( '' === $text ) {
					return '';
				}
				if ( '' === $href || '#' === substr( $href, 0, 1 ) ) {
					return $text;
				}
				return '[' . $text . '](' . $href . ')';

			case 'img':
				$src = trim( (string) $node->getAttribute( 'src' ) );
				$alt = trim( (string) $node->getAttribute( 'alt' ) );
				return '' === $src ? '' : '![' . $alt . '](' . $src . ')';

			case 'ul':
			case 'ol':
				return "\n\n" . self::list_items( $node, $tag, $depth ) . "\n\n";

			case 'li':
				// Handled by list_items(); a stray li renders as its children.
				return self::children( $node, $depth );

			case 'table':
				return self::table( $node, $depth );

			default:
				return self::children( $node, $depth );
		}
	}

	/**
	 * Render the items of a ul/ol.
	 *
	 * @param DOMNode $node  List node.
	 * @param string  $tag   ul or ol.
	 * @param int     $depth Nesting depth.
	 * @return string
	 */
	private static function list_items( $node, $tag, $depth ) {
		$out = array();
		$i   = 1;
		foreach ( $node->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
			if ( XML_ELEMENT_NODE !== $child->nodeType || 'li' !== strtolower( $child->nodeName ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
				continue;
			}
			$marker = 'ol' === $tag ? $i . '. ' : '- ';
			$text   = trim( self::children( $child, $depth + 1 ) );
			$text   = preg_replace( "/\n+/", "\n" . str_repeat( '  ', $depth + 1 ), $text );
			$out[]  = str_repeat( '  ', $depth ) . $marker . $text;
			++$i;
		}
		return implode( "\n", $out );
	}

	/**
	 * Render a simple table.
	 *
	 * @param DOMNode $node  Table node.
	 * @param int     $depth Nesting depth.
	 * @return string
	 */
	private static function table( $node, $depth ) {
		$rows = array();
		foreach ( $node->getElementsByTagName( 'tr' ) as $tr ) {
			$cells = array();
			foreach ( $tr->childNodes as $cell ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
				if ( XML_ELEMENT_NODE !== $cell->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
					continue;
				}
				$cell_tag = strtolower( $cell->nodeName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
				if ( 'td' === $cell_tag || 'th' === $cell_tag ) {
					$cells[] = trim( preg_replace( '/\s+/', ' ', self::children( $cell, $depth ) ) );
				}
			}
			if ( ! empty( $cells ) ) {
				$rows[] = '| ' . implode( ' | ', $cells ) . ' |';
			}
		}
		if ( empty( $rows ) ) {
			return '';
		}
		if ( count( $rows ) > 1 ) {
			$columns = substr_count( $rows[0], '|' ) - 1;
			array_splice( $rows, 1, 0, '|' . str_repeat( ' --- |', max( 1, $columns ) ) );
		}
		return "\n\n" . implode( "\n", $rows ) . "\n\n";
	}
}
