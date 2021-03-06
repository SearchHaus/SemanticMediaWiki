<?php

namespace SMW\Tests\Util;

use Title;
use TextContent;

use UnexpectedValueException;

/**
 * @license GNU GPL v2+
 * @since 2.1
 *
 * @author mwjames
 */
class PageReader {

	/**
	 * @var WikiPage
	 */
	private $page = null;

	/**
	 * @since 2.1
	 *
	 * @return WikiPage
	 * @throws UnexpectedValueException
	 */
	public function getPage() {

		if ( $this->page instanceof \WikiPage ) {
			return $this->page;
		}

		throw new UnexpectedValueException( 'Expected a WikiPage instance, use createPage first' );
	}

	/**
	 * @since 2.1
	 *
	 * @param Title $title
	 *
	 * @return text
	 */
	public function getContentAsText( Title $title ) {

		$this->page = new \WikiPage( $title );

		if ( method_exists( $this->page, 'getContent' ) ) {
			$content = $this->page->getContent();

			if ( $content instanceof TextContent ) {
				return $content->getNativeData();
			} else {
				return '';
			}
		}

		return $this->page->getText();
	}

}
