<?php

namespace SMW\Tests\Util\Validators;

/**
 * @license GNU GPL v2+
 * @since   2.1
 *
 * @author mwjames
 */
class StringValidator extends \PHPUnit_Framework_Assert {

	/**
	 * @since 2.1
	 *
	 * @param mixed $expected
	 * @param string $actual
	 */
	public function assertThatStringContains( $expected, $actual, $message = '' ) {

		if ( !is_array( $expected ) ) {
			$expected = array( $expected );
		}

		$expected = array_filter( $expected, 'strlen' );

		if ( $expected === array() ) {
			return self::assertTrue( true, $message );
		}

		self::assertInternalType(
			'string',
			$actual
		);

		$expectedToCount = count( $expected );
		$actualCounted = 0;

		foreach ( $expected as $key => $string ) {
			if ( strpos( $actual, $string ) !== false ) {
				$actualCounted++;
				unset( $expected[ $key ] );
			}
		}

		self::assertEquals(
			$expectedToCount,
			$actualCounted,
			"Failed asserting that $actual contains " . $this->toString( $expected )
		);
	}

	private function toString( $expected ) {
		return "[ " . ( is_array( $expected ) ? implode( ', ', $expected ) : $expected ) . " ]";
	}

}
