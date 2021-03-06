<?php

namespace SMW\Tests\Integration\Query;

use SMW\Tests\MwDBaseUnitTestCase;
use SMW\Tests\Util\UtilityFactory;

use SMW\Query\Language\ThingDescription;
use SMW\Query\Language\SomeProperty;

use SMW\DIWikiPage;
use SMW\DIProperty;
use SMW\DataValueFactory;

use SMWQuery as Query;
use SMWQueryResult as QueryResult;
use SMWPrintRequest as PrintRequest;
use SMWPropertyValue as PropertyValue;

/**
 * @group SMW
 * @group SMWExtension
 *
 * @group semantic-mediawiki-integration
 * @group semantic-mediawiki-query
 *
 * @group mediawiki-database
 * @group medium
 *
 * @license GNU GPL v2+
 * @since 2.0
 *
 * @author mwjames
 */
class GeneralQueryDBIntegrationTest extends MwDBaseUnitTestCase {

	private $subjectsToBeCleared = array();
	private $subject;

	private $dataValueFactory;
	private $queryResultValidator;

	protected function setUp() {
		parent::setUp();

		$this->dataValueFactory = DataValueFactory::getInstance();
		$this->queryResultValidator = UtilityFactory::getInstance()->newValidatorFactory()->newQueryResultValidator();
		$this->semanticDataFactory = UtilityFactory::getInstance()->newSemanticDataFactory();
	}

	protected function tearDown() {

		foreach ( $this->subjectsToBeCleared as $subject ) {
			$this->getStore()->deleteSubject( $subject->getTitle() );
		}

		parent::tearDown();
	}

	public function testPropertyBeforeAfterDataRemoval() {

		$property = new DIProperty( 'SomePagePropertyBeforeAfter' );
		$property->setPropertyTypeId( '_wpg' );

		$this->assertEmpty(
			$this->searchForResultsThatCompareEqualToOnlySingularPropertyOf( $property )->getResults()
		);

		$semanticData = $this->semanticDataFactory->newEmptySemanticData( __METHOD__ );

		$semanticData->addDataValue(
			$this->dataValueFactory->newDataItemValue( $semanticData->getSubject(), $property )
		);

		$this->getStore()->updateData( $semanticData );

		$this->queryResultValidator->assertThatQueryResultContains(
			$semanticData->getSubject(),
			$this->searchForResultsThatCompareEqualToOnlySingularPropertyOf( $property )
		);

		$this->assertNotEmpty(
			$this->searchForResultsThatCompareEqualToOnlySingularPropertyOf( $property )->getResults()
		);

		$this->getStore()->clearData( $semanticData->getSubject() );

		$this->assertEmpty(
			$this->searchForResultsThatCompareEqualToOnlySingularPropertyOf( $property )->getResults()
		);

		$this->subjectsToBeCleared = array(
			$semanticData->getSubject()
		);
	}

	public function testUserDefinedPropertyUsedForInvalidValueAssignment() {

		$property = new DIProperty( 'SomePropertyWithInvalidValueAssignment' );
		$property->setPropertyTypeId( '_tem' );

		$dataValue = $this->dataValueFactory->newPropertyObjectValue( $property, '1 Jan 1970' );

		$semanticData = $this->semanticDataFactory->newEmptySemanticData( __METHOD__ );
		$semanticData->addDataValue( $dataValue );

		$this->getStore()->updateData( $semanticData );

		$this->assertEquals(
			0,
			$this->searchForResultsThatCompareEqualToOnlySingularPropertyOf( $property )->getCount()
		);

		$this->subjectsToBeCleared = array(
			$semanticData->getSubject()
		);
	}

	private function searchForResultsThatCompareEqualToOnlySingularPropertyOf( DIProperty $property ) {

		$propertyValue = new PropertyValue( '__pro' );
		$propertyValue->setDataItem( $property );

		$description = new SomeProperty(
			$property,
			new ThingDescription()
		);

		$description->addPrintRequest(
			new PrintRequest( PrintRequest::PRINT_PROP, null, $propertyValue )
		);

		$query = new Query(
			$description,
			false,
			false
		);

		$query->querymode = Query::MODE_INSTANCES;

		return $this->getStore()->getQueryResult( $query );
	}

}
