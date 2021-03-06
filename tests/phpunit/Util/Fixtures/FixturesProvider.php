<?php

namespace SMW\Tests\Util\Fixtures;

use SMW\Tests\Util\UtilityFactory;
use SMW\Tests\Util\Fixtures\Properties\AreaProperty;
use SMW\Tests\Util\Fixtures\Properties\PopulationDensityProperty;
use SMW\Tests\Util\Fixtures\Properties\CapitalOfProperty;
use SMW\Tests\Util\Fixtures\Properties\StatusProperty;
use SMW\Tests\Util\Fixtures\Properties\PopulationProperty;
use SMW\Tests\Util\Fixtures\Properties\FoundedProperty;
use SMW\Tests\Util\Fixtures\Properties\LocatedInProperty;
use SMW\Tests\Util\Fixtures\Properties\CityCategory;

use SMW\Tests\Util\Fixtures\Facts\BerlinFactsheet;
use SMW\Tests\Util\Fixtures\Facts\ParisFactsheet;
use SMW\Tests\Util\Fixtures\Facts\FranceFactsheet;

use SMW\Store;

use RuntimeException;

/**
 * @license GNU GPL v2+
 * @since 2.1
 *
 * @author mwjames
 */
class FixturesProvider {

	private $factsheets = null;
	private $properties = null;
	private $categories = null;

	/**
	 * @since 2.1
	 */
	public function setupDependencies( Store $store ) {

		// This needs to happen before access to a property object is granted

		// $pageCreator = UtilityFactory::getInstance()->newPageCreator();

		foreach ( $this->getListOfPropertyInstances() as $propertyInstance ) {
			$store->updateData( $propertyInstance->getDependencies() );
		}
	}

	/**
	 * @since 2.1
	 *
	 * @return array
	 */
	public function getListOfFactsheetInstances() {
		return array(
			'berlin' => new BerlinFactsheet(),
			'paris'  => new ParisFactsheet(),
			'france'  => new FranceFactsheet()
		);
	}

	/**
	 * @since 2.1
	 *
	 * @return array
	 */
	public function getListOfPropertyInstances() {
		return array(
			'area' => new AreaProperty(),
			'populationdensity' => new PopulationDensityProperty(),
			'capitalof' => new CapitalOfProperty(),
			'status' => new StatusProperty(),
			'population' => new PopulationProperty(),
			'founded' => new FoundedProperty(),
			'locatedin' => new LocatedInProperty()
		);
	}

	/**
	 * @since 2.1
	 *
	 * @return array
	 */
	public function getListOfCategoryInstances() {
		return array(
			'city' => new CityCategory()
		);
	}

	/**
	 * @since 2.1
	 *
	 * @return DIProperty
	 * @throws RuntimeException
	 */
	public function getProperty( $id ) {

		$id = strtolower( $id );

		if ( $this->properties === null ) {
			$this->properties = $this->getListOfPropertyInstances();;
		};

		if ( isset( $this->properties[ $id ] ) ) {
			return $this->properties[ $id ]->getProperty();
		}

		throw new RuntimeException( "$id is an unknown requested property" );
	}

	/**
	 * @since 2.1
	 *
	 * @return DIProperty
	 * @throws RuntimeException
	 */
	public function getCategory( $id ) {

		$id = strtolower( $id );

		if ( $this->categories === null ) {
			$this->categories = $this->getListOfCategoryInstances();;
		};

		if ( isset( $this->categories[ $id ] ) ) {
			return $this->categories[ $id ];
		}

		throw new RuntimeException( "$id is an unknown requested property" );
	}

	/**
	 * @since 2.1
	 *
	 * @return Factsheet
	 * @throws RuntimeException
	 */
	public function getFactsheet( $id ) {

		$id = strtolower( $id );

		if ( $this->factsheets === null ) {
			$this->factsheets = $this->getListOfFactsheetInstances();;
		};

		if ( isset( $this->factsheets[ $id ] ) ) {
			return $this->factsheets[ $id ];
		}

		throw new RuntimeException( "$id is an unknown requested fact" );
	}

	/**
	 * @since 2.1
	 *
	 * @return FixturesCleaner
	 */
	public function getCleaner() {
		return new FixturesCleaner();
	}

}
