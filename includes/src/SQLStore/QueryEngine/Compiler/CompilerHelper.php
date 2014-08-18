<?php

namespace SMW\SQLStore\QueryEngine\Compiler;

use SMW\Query\Language\ValueDescription;

use RuntimeException;

/**
 * @license GNU GPL v2+
 * @since 2.1
 *
 * @author mwjames
 */
class CompilerHelper {

	/**
	 * @since 2.1
	 *
	 * @param ValueDescription $description
	 * @param string &$value
	 *
	 * @return string
	 * @throws RuntimeException
	 */
	public function getSQLComparatorToValue( ValueDescription $description, &$value ) {

		$comparatorMap = array(
			SMW_CMP_EQ   => '=',
			SMW_CMP_LESS => '<',
			SMW_CMP_GRTR => '>',
			SMW_CMP_LEQ  => '<=',
			SMW_CMP_GEQ  => '>=',
			SMW_CMP_NEQ  => '!=',
			SMW_CMP_LIKE => ' LIKE ',
			SMW_CMP_NLKE => ' NOT LIKE '
		);

		$comparator = $description->getComparator();

		if ( !isset( $comparatorMap[ $comparator ] ) ) {
			throw new RuntimeException( "Unsupported comparator '" . $comparator . "' in value description." );
		}

		if ( $comparator === SMW_CMP_LIKE || $comparator === SMW_CMP_NLKE ) {
			// Escape to prepare string matching:
			$value = str_replace( array( '%', '_', '*', '?' ), array( '\%', '\_', '%', '_' ), $value );

		}

		return $comparatorMap[ $comparator ];
	}

}
