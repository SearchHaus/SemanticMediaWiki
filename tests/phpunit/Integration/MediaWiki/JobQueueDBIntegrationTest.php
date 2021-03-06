<?php

namespace SMW\Tests\Integration\MediaWiki;

use SMW\Tests\Util\UtilityFactory;
use SMW\Tests\MwDBaseUnitTestCase;

use SMW\ApplicationFactory;
use SMW\DIWikiPage;

use Title;
use Job;

/**
 * @group SMW
 * @group SMWExtension
 *
 * @group semantic-mediawiki-integration
 * @group mediawiki-database
 *
 * @group medium
 *
 * @license GNU GPL v2+
 * @since 1.9.0.1
 *
 * @author mwjames
 */
class JobQueueDBIntegrationTest extends MwDBaseUnitTestCase {

	private $job = null;
	private $applicationFactory;

	private $deletePoolOfPages = array();
	private $runnerFactory;

	private $mwHooksHandler;
	private $semanticDataValidator;

	private $pageDeleter;
	private $pageCreator;

	private $jobQueueRunner;
	private $jobQueueLookup;

	protected function setUp() {
		parent::setUp();

		$utilityFactory = UtilityFactory::getInstance();

		$this->mwHooksHandler = $utilityFactory->newMwHooksHandler();

		$this->mwHooksHandler
			->deregisterListedHooks()
			->invokeHooksFromRegistry();

		$this->semanticDataValidator = $utilityFactory->newValidatorFactory()->newSemanticDataValidator();
		$this->pageDeleter = $utilityFactory->newPageDeleter();
		$this->pageCreator = $utilityFactory->newPageCreator();

		$this->applicationFactory = ApplicationFactory::getInstance();

		// FIXME Because of SQLStore::Writer::changeTitle
		$GLOBALS['smwgEnableUpdateJobs'] = true;

		$settings = array(
			'smwgEnableUpdateJobs' => true,
			'smwgDeleteSubjectAsDeferredJob' => true,
			'smwgDeleteSubjectWithAssociatesRefresh' => true
		);

		foreach ( $settings as $key => $value ) {
			$this->applicationFactory->getSettings()->set( $key, $value );
		}

		$this->jobQueueLookup = $this->applicationFactory
			->newMwCollaboratorFactory()
			->newJobQueueLookup( $this->getStore()->getConnection( 'mw.db' ) );

		$this->jobQueueRunner = $utilityFactory->newRunnerFactory()->newJobQueueRunner();

		$this->jobQueueRunner
			->setDBConnectionProvider( $this->getDBConnectionProvider() )
			->deleteAllJobs();
	}

	protected function tearDown() {

		$this->pageDeleter->doDeletePoolOfPages(
			$this->deletePoolOfPages
		);

		$this->applicationFactory->clear();
		$this->mwHooksHandler->restoreListedHooks();

		parent::tearDown();
	}

	/**
	 * @dataProvider titleProvider
	 */
	public function testPageDeleteTriggersDeleteSubjectJob( $source, $associate ) {

		$subject = DIWikiPage::newFromTitle( $source['title'] );

		$this->semanticDataValidator->assertThatSemanticDataIsEmpty(
			$this->getStore()->getSemanticData( $subject )
		);

		$this->pageCreator
			->createPage( $source['title'] )
			->doEdit( $source['edit'] );

		$this->pageCreator
			->createPage( $associate['title'] )
			->doEdit( $associate['edit'] );

		$this->semanticDataValidator->assertThatSemanticDataIsNotEmpty(
			$this->getStore()->getSemanticData( $subject )
		);

		$this->pageDeleter->deletePage( $source['title'] );

		$this->semanticDataValidator->assertThatSemanticDataIsEmpty(
			$this->getStore()->getSemanticData( $subject )
		);

		$this->assertJob( 'SMW\DeleteSubjectJob' );

		foreach ( array( 'withAssociates', 'asDeferredJob', 'semanticData' ) as $parameter ) {
			$this->assertTrue( $this->job->hasParameter( $parameter ) );
		}

		$this->pageDeleter->deletePage( $associate['title'] );
	}

	public function testPageMoveTriggersUpdateJob() {

		$oldTitle = Title::newFromText( __METHOD__ . '-old' );
		$newTitle = Title::newFromText( __METHOD__ . '-new' );

		$this->pageCreator
			->createPage( $oldTitle )
			->doEdit( '[[Has jobqueue test::UpdateJob]]' );

		$this->pageCreator
			->getPage()
			->getTitle()
			->moveTo( $newTitle, false, 'test', true );

		$this->assertJob( 'SMW\UpdateJob' );

		$this->assertContains(
			$this->job->getTitle()->getPrefixedText(),
			array( $oldTitle->getPrefixedText(), $newTitle->getPrefixedText() )
		);

		$this->pageDeleter->deletePage( $oldTitle );
	}

	public function testSQLStoreRefreshDataTriggersUpdateJob() {

		$index = 1; //pass-by-reference

		$this->getStore()->refreshData( $index, 1, false, true );
		$this->assertJob( 'SMW\UpdateJob' );
	}

	/**
	 * @dataProvider jobFactoryProvider
	 */
	public function testJobFactory( $jobName, $type ) {

		$job = Job::factory(
			$jobName,
			Title::newFromText( __METHOD__ . $jobName ),
			array()
		);

		$this->assertJob( $type, $job );
	}

	public function jobFactoryProvider() {

		$provider = array();

		$provider[] = array( 'SMW\UpdateJob', 'SMW\UpdateJob' );
		$provider[] = array( 'SMWUpdateJob', 'SMW\UpdateJob' );

		$provider[] = array( 'SMW\RefreshJob', 'SMW\RefreshJob' );
		$provider[] = array( 'SMWRefreshJob', 'SMW\RefreshJob' );

		return $provider;
	}

	public function titleProvider() {

		$provider = array();

		// #0 Simple property reference
		$provider[] = array( array(
				'title' => Title::newFromText( __METHOD__ . '-foo' ),
				'edit'  => '{{#set:|DeferredJobFoo=DeferredJobBar}}'
			), array(
				'title' => Title::newFromText( __METHOD__ . '-bar' ),
				'edit'  => '{{#set:|DeferredJobFoo=DeferredJobBar}}'
			)
		);

		// #1 Source page in-property reference
		$title = Title::newFromText( __METHOD__ . '-foo' );

		$provider[] = array( array(
				'title' => $title,
				'edit'  => ''
			), array(
				'title' => Title::newFromText( __METHOD__ . '-bar' ),
				'edit'  => '{{#set:|DeferredJobFoo=' . $title->getPrefixedText() . '}}'
			)
		);

		return $provider;
	}

	protected function assertJob( $type, Job &$job = null ) {

		if ( $job === null ) {
			$job = $this->jobQueueRunner->pop_type( $type );
		}

		if ( !$job ) {
			$this->markTestSkipped( "Required a {$type} JobQueue entry" );
		}

		$this->job = $job;

		$this->assertInstanceOf( 'Job', $job );
		$this->assertTrue( $job->run() );
	}

	/**
	 * Issue 617
	 */
	public function testNoInfiniteUpdateJobsForCircularRedirect() {

		$this->pageCreator
			->createPage( Title::newFromText( 'Foo-A' ) )
			->doEdit( '[[Foo-A::{{PAGENAME}}]] {{#ask: [[Foo-A::{{PAGENAME}}]] }}' )
			->doEdit( '#REDIRECT [[Foo-B]]' );

		$this->pageCreator
			->createPage( Title::newFromText( 'Foo-B' ) )
			->doEdit( '#REDIRECT [[Foo-C]]' );

		$this->pageCreator
			->createPage( Title::newFromText( 'Foo-C' ) )
			->doEdit( '#REDIRECT [[Foo-A]]' );

		$this->jobQueueRunner
			->setType( 'SMW\UpdateJob' )
			->run();

		foreach ( $this->jobQueueRunner->getStatus() as $status ) {
			$this->assertTrue( $status['status'] );
		}

		$this->deletePoolOfPages = array(
			Title::newFromText( 'Foo-A' ),
			Title::newFromText( 'Foo-B' ),
			Title::newFromText( 'Foo-C' )
		);
	}

	public function testPropertyTypeChangeToCreateUpdateJob() {

		$propertyPage = Title::newFromText( 'FooProperty', SMW_NS_PROPERTY );

		$this->pageCreator
			->createPage( $propertyPage )
			->doEdit( '[[Has type::Page]]' );

		$this->pageCreator
			->createPage( Title::newFromText( 'Foo' ), NS_MAIN )
			->doEdit( '[[FooProperty::SomePage]]' );

		$this->pageCreator
			->createPage( $propertyPage )
			->doEdit( '[[Has type::Number]]' );

		$this->assertGreaterThan(
			0,
			$this->jobQueueLookup->estimateJobCountFor( 'SMW\UpdateJob' )
		);

		$this->jobQueueRunner
			->setType( 'SMW\UpdateJob' )
			->run();

		foreach ( $this->jobQueueRunner->getStatus() as $status ) {
			$this->assertTrue( $status['status'] );
		}

		$this->deletePoolOfPages = array(
			$propertyPage,
			Title::newFromText( 'Foo' )
		);
	}

}
