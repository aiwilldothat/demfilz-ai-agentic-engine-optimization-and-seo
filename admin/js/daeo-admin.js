/**
 * Demfilz AI Agentic Engine Optimization and SEO - admin behaviour.
 * Character counters, snippet preview, FAQ repeater and bot presets.
 */
( function ( $ ) {
	'use strict';

	// ---- Character counters + snippet preview -----------------------------

	function updateCounter( $counter ) {
		var targetId = $counter.data( 'daeo-counter-for' );
		var max = parseInt( $counter.data( 'daeo-max' ), 10 ) || 0;
		var $field = $( '#' + targetId );
		if ( ! $field.length ) {
			return;
		}
		var len = String( $field.val() || '' ).length;
		$counter.text( len + ' / ' + max );
		$counter.toggleClass( 'daeo-counter-over', max > 0 && len > max );
	}

	function bindCounters() {
		$( '.daeo-counter' ).each( function () {
			var $counter = $( this );
			var targetId = $counter.data( 'daeo-counter-for' );
			var $field = $( '#' + targetId );
			if ( ! $field.length ) {
				return;
			}
			updateCounter( $counter );
			$field.on( 'input change', function () {
				updateCounter( $counter );
			} );
		} );
	}

	function bindSnippet() {
		var $title = $( '#daeo-title' );
		var $desc = $( '#daeo-desc' );
		var $previewTitle = $( '#daeo-snippet-title' );
		var $previewDesc = $( '#daeo-snippet-desc' );
		if ( ! $title.length || ! $previewTitle.length ) {
			return;
		}
		var fallbackTitle = $previewTitle.text();
		var fallbackDesc = $previewDesc.text();
		$title.on( 'input change', function () {
			var value = String( $title.val() || '' ).trim();
			$previewTitle.text( value !== '' ? value : fallbackTitle );
		} );
		$desc.on( 'input change', function () {
			var value = String( $desc.val() || '' ).trim();
			$previewDesc.text( value !== '' ? value : fallbackDesc );
		} );
	}

	// ---- FAQ repeater -----------------------------------------------------

	var faqIndex = 1000;

	function addFaqRow( question, answer ) {
		var template = ( window.DAEO_ADMIN && window.DAEO_ADMIN.faqRowTemplate ) || '';
		if ( ! template ) {
			return null;
		}
		faqIndex += 1;
		var $row = $( template.split( '__i__' ).join( String( faqIndex ) ) );
		if ( question ) {
			$row.find( '.daeo-faq-q' ).val( question );
		}
		if ( answer ) {
			$row.find( '.daeo-faq-a' ).val( answer );
		}
		$( '#daeo-faq-rows' ).append( $row );
		$( '#daeo-faq-rows .daeo-faq-empty' ).remove();
		return $row;
	}

	// Exposed so the Pro add-on can insert AI-generated rows.
	window.daeoAddFaqRow = addFaqRow;

	function bindFaqBox() {
		var $box = $( '#daeo-faq-box' );
		if ( ! $box.length ) {
			return;
		}
		$( '#daeo-faq-add' ).on( 'click', function () {
			var $row = addFaqRow( '', '' );
			if ( $row ) {
				$row.find( '.daeo-faq-q' ).trigger( 'focus' );
			}
		} );
		$box.on( 'click', '.daeo-faq-remove', function () {
			var message = ( window.DAEO_ADMIN && window.DAEO_ADMIN.confirmRemove ) || 'Remove?';
			if ( window.confirm( message ) ) {
				$( this ).closest( '.daeo-faq-row' ).remove();
			}
		} );
	}

	// ---- Bot policy presets ----------------------------------------------

	function bindBotButtons() {
		$( '#daeo-block-training' ).on( 'click', function () {
			$( '.daeo-bots-table select[data-daeo-bot-type="training"]' ).val( 'block' );
		} );
		$( '#daeo-reset-bots' ).on( 'click', function () {
			$( '.daeo-bots-table select' ).val( '' );
		} );
	}

	$( function () {
		bindCounters();
		bindSnippet();
		bindFaqBox();
		bindBotButtons();
	} );
}( jQuery ) );
