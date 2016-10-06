<?php

require_once( AMP__DIR__ . '/includes/sanitizers/class-amp-base-sanitizer.php' );

class AMP_Audio_Sanitizer extends AMP_Base_Sanitizer {
	public static $tag = 'audio';

	private static $script_slug = 'amp-audio';
	private static $script_src = 'https://cdn.ampproject.org/v0/amp-audio-0.1.js';

	protected $DEFAULT_ARGS = array(
		'require_https_src' => true,
	);

	public function get_scripts() {
		if ( ! $this->did_convert_elements ) {
			return array();
		}

		return array( self::$script_slug => self::$script_src );
	}

	public function sanitize() {
		$nodes = $this->dom->getElementsByTagName( self::$tag );
		$num_nodes = $nodes->length;
		if ( 0 === $num_nodes ) {
			return;
		}

		for ( $i = $num_nodes - 1; $i >= 0; $i-- ) {
			$orig_src = array();

			$node = $nodes->item( $i );
			$old_attributes = AMP_DOM_Utils::get_node_attributes_as_assoc_array( $node );

			if ( isset( $old_attributes['src'] ) ) {
				$orig_src[] = $old_attributes['src'];
			}

			$new_attributes = $this->filter_attributes( $old_attributes );

			$new_node = AMP_DOM_Utils::create_node( $this->dom, 'amp-audio', $new_attributes );

			// TODO: `source` does not have closing tag, and DOMDocument doesn't handle it well.
			foreach ( $node->childNodes as $child_node ) {
				$new_child_node = $child_node->cloneNode( true );
				$old_child_attributes = AMP_DOM_Utils::get_node_attributes_as_assoc_array( $new_child_node );

				if ( isset( $old_child_attributes['src'] ) ) {
					$orig_src[] = $old_child_attributes['src'];
				}

				$new_child_attributes = $this->filter_attributes( $old_child_attributes );

				// Only append source tags with a valid src attribute
				if ( ! empty( $new_child_attributes['src'] ) && 'source' === $new_child_node->tagName ) {
					AMP_DOM_Utils::add_attributes_to_node( $new_child_node, $new_child_attributes );
					$new_node->appendChild( $new_child_node );
				}
			}

			// If the node has at least one valid source, replace the old node with it.
			// If the node has no valid sources, but at least one invalid (http) one, add a fallback element.
			// Otherwise, just remove the node.
			if ( 0 === $new_node->childNodes->length && empty( $new_attributes['src'] ) ) {
				if ( ! empty( $orig_src ) ) {
					$fallback_node = $this->create_fallback_node(
						sprintf(
							wp_kses( __( 'Could not load <a href="%s">audio</a>.', 'amp' ), array( 'a' => array( 'href' => true ) ) ),
							esc_url( array_shift( $orig_src ) )
						)
					);

					$node->parentNode->replaceChild( $fallback_node, $node );
				} else {
					$node->parentNode->removeChild( $node );
				}
			} else {
				$node->parentNode->replaceChild( $new_node, $node );
			}

			$this->did_convert_elements = true;
		}
	}

	private function filter_attributes( $attributes ) {
		$out = array();

		foreach ( $attributes as $name => $value ) {
			switch ( $name ) {
				case 'src':
					$out[ $name ] = $this->maybe_enforce_https_src( $value );
					break;

				case 'width':
				case 'height':
					$out[ $name ] = $this->sanitize_dimension( $value, $name );
					break;

				case 'class':
					$out[ $name ] = $value;
					break;
				case 'loop':
				case 'muted':
				case 'autoplay':
					if ( 'false' !== $value ) {
						$out[ $name ] = '';
					}
					break;

				default;
					break;
			}
		}

		return $out;
	}
}
