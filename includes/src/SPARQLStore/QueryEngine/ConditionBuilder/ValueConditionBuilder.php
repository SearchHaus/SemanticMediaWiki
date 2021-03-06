<?php

namespace SMW\SPARQLStore\QueryEngine\ConditionBuilder;

use SMW\SPARQLStore\QueryEngine\CompoundConditionBuilder;
use SMW\SPARQLStore\QueryEngine\Condition\SingletonCondition;
use SMW\SPARQLStore\QueryEngine\Condition\FilterCondition;
use SMW\SPARQLStore\QueryEngine\Condition\FalseCondition;

use SMW\Query\Language\Description;
use SMW\Query\Language\ValueDescription;

use SMW\DIWikiPage;
use SMW\DIProperty;

use SMWExporter as Exporter;
use SMWExpNsResource as ExpNsResource;
use SMWTurtleSerializer as TurtleSerializer;
use SMWDIBlob as DIBlob;

/**
 * @license GNU GPL v2+
 * @since 2.1
 *
 * @author Markus Krötzsch
 * @author mwjames
 */
class ValueConditionBuilder implements ConditionBuilder {

	/**
	 * @var CompoundConditionBuilder
	 */
	private $compoundConditionBuilder;

	/**
	 * @var Exporter
	 */
	private $exporter;

	/**
	 * @since 2.1
	 *
	 * @param CompoundConditionBuilder|null $compoundConditionBuilder
	 */
	public function __construct( CompoundConditionBuilder $compoundConditionBuilder = null ) {
		$this->compoundConditionBuilder = $compoundConditionBuilder;
		$this->exporter = Exporter::getInstance();
	}

	/**
	 * @since 2.1
	 *
	 * @param Description $description
	 *
	 * @return boolean
	 */
	public function canBuildConditionFor( Description $description ) {
		return $description instanceOf ValueDescription;
	}

	/**
	 * @since 2.1
	 *
	 * @param CompoundConditionBuilder $compoundConditionBuilder
	 *
	 * @return self
	 */
	public function setCompoundConditionBuilder( CompoundConditionBuilder $compoundConditionBuilder ) {
		$this->compoundConditionBuilder = $compoundConditionBuilder;
		return $this;
	}

	/**
	 * Create an Condition from an ValueDescription.
	 *
	 * @param ValueDescription $description
	 * @param string $joinVariable
	 * @param DIProperty|null $orderByProperty
	 *
	 * @return Condition
	 */
	public function buildCondition( Description $description, $joinVariable, $orderByProperty = null ) {

		$dataItem = $description->getDataItem();

		switch ( $description->getComparator() ) {
			case SMW_CMP_EQ:   $comparator = '='; break;
			case SMW_CMP_LESS: $comparator = '<'; break;
			case SMW_CMP_GRTR: $comparator = '>'; break;
			case SMW_CMP_LEQ:  $comparator = '<='; break;
			case SMW_CMP_GEQ:  $comparator = '>='; break;
			case SMW_CMP_NEQ:  $comparator = '!='; break;
			case SMW_CMP_LIKE: $comparator = 'regex'; break;
			case SMW_CMP_NLKE: $comparator = '!regex'; break;
			default:           $comparator = ''; // unkown, unsupported
		}

		if ( $comparator === '' ) {
			return $this->createConditionForEmptyComparator( $joinVariable, $orderByProperty );
		} elseif ( $comparator == '=' ) {
			return $this->createConditionForEqualityComparator( $dataItem, $joinVariable, $orderByProperty );
		} elseif ( $comparator == 'regex' || $comparator == '!regex' ) {
			return $this->createConditionForRegexComparator( $dataItem, $joinVariable, $orderByProperty, $comparator );
		}

		return $this->createConditionForAnyOtherComparator(
			$dataItem,
			$joinVariable,
			$orderByProperty,
			$comparator
		);
	}

	private function createConditionForEmptyComparator( $joinVariable, $orderByProperty ) {
		return $this->compoundConditionBuilder->buildTrueCondition( $joinVariable, $orderByProperty );
	}

	private function createConditionForEqualityComparator( $dataItem, $joinVariable, $orderByProperty ) {

		$expElement = $this->exporter->getDataItemHelperExpElement( $dataItem );

		if ( $expElement === null ) {
			$expElement = $this->exporter->getDataItemExpElement( $dataItem );
		}

		if ( $expElement === null ) {
			return new FalseCondition();
		}

		$result = new SingletonCondition( $expElement );

		$this->compoundConditionBuilder->addOrderByDataForProperty(
			$result,
			$joinVariable,
			$orderByProperty,
			$dataItem->getDIType()
		);

		return $result;
	}

	private function createConditionForRegexComparator( $dataItem, $joinVariable, $orderByProperty, $comparator ) {

		if ( !$dataItem instanceof DIBlob ) {
			return $this->compoundConditionBuilder->buildTrueCondition( $joinVariable, $orderByProperty );
		}

		$pattern = '^' . str_replace( array( '^', '.', '\\', '+', '{', '}', '(', ')', '|', '^', '$', '[', ']', '*', '?' ),
		                              array( '\^', '\.', '\\\\', '\+', '\{', '\}', '\(', '\)', '\|', '\^', '\$', '\[', '\]', '.*', '.' ),
		                              $dataItem->getString() ) . '$';

		$result = new FilterCondition( "$comparator( ?$joinVariable, \"$pattern\", \"s\")", array() );

		$this->compoundConditionBuilder->addOrderByDataForProperty(
			$result,
			$joinVariable,
			$orderByProperty,
			$dataItem->getDIType()
		);

		return $result;
	}

	private function createConditionForAnyOtherComparator( $dataItem, $joinVariable, $orderByProperty, $comparator ) {

		$result = new FilterCondition( '', array() );

		$this->compoundConditionBuilder->addOrderByData(
			$result,
			$joinVariable,
			$dataItem->getDIType()
		);

		$orderByVariable = $result->orderByVariable;

		if ( $dataItem instanceof DIWikiPage ) {
			$expElement = $this->exporter->getDataItemExpElement( $dataItem->getSortKeyDataItem() );
		} else {
			$expElement = $this->exporter->getDataItemHelperExpElement( $dataItem );
			if ( is_null( $expElement ) ) {
				$expElement = $this->exporter->getDataItemExpElement( $dataItem );
			}
		}

		$valueName = TurtleSerializer::getTurtleNameForExpElement( $expElement );

		if ( $expElement instanceof ExpNsResource ) {
			$result->namespaces[$expElement->getNamespaceId()] = $expElement->getNamespace();
		}

		$result->filter = "?$orderByVariable $comparator $valueName";

		return $result;
	}

}
