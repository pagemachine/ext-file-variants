<?php
declare(strict_types=1);
namespace T3G\AgencyPack\FileVariants\Tests\Functional\DataHandler;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
use TYPO3\CMS\Core\Resource\Security\FileMetadataPermissionsAspect;
use TYPO3\CMS\Core\Tests\Functional\DataHandling\Framework\ActionService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
  * Description
  */
class DataHandlerHookTest extends FunctionalTestCase {


    /**
     * @var ActionService
     */
    protected $actionService;

    /**
     * @var \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
     */
    protected $backendUser;

    protected $testExtensionsToLoad = ['typo3conf/ext/file_variants'];

    /**
     * @var string
     */
    protected $scenarioDataSetDirectory = 'typo3conf/ext/file_variants/Tests/Functional/DataHandler/DataSet/';

    /**
     * @var string
     */
    protected $assertionDataSetDirectory = 'typo3conf/ext/file_variants/Tests/Functional/DataHandler/Modify/DataSet/SysFileTranslatable/';

    protected function setUp()
    {
        parent::setUp();
        \TYPO3\CMS\Core\Core\Bootstrap::getInstance()->initializeLanguageObject();

        $this->backendUser = $this->setUpBackendUserFromFixture(1);
        $fileMetadataPermissionAspect = $this->prophesize(FileMetadataPermissionsAspect::class);
        GeneralUtility::setSingletonInstance(FileMetadataPermissionsAspect::class, $fileMetadataPermissionAspect->reveal());

        $this->importCsvScenario('initialSetup');
        $this->setUpFrontendRootPage(1);

        $this->actionService = new ActionService();

        // done to prevent an error during processing
        // it makes no difference here whether file filters apply to the data set
        unset($GLOBALS['TCA']['tt_content']['columns']['image']['config']['filter']);
    }

    protected function tearDown()
    {
        unset($this->actionService);
        parent::tearDown();
    }

    /**
     * @param string $scenarioName
     */
    protected function importCsvScenario(string $scenarioName = '')
    {
        $scenarioFileName = $this->scenarioDataSetDirectory . $scenarioName . '.csv';
        $scenarioFileName = GeneralUtility::getFileAbsFileName($scenarioFileName);
        $this->importCSVDataSet($scenarioFileName);
    }

    /**
     * @param string $scenarioName
     */
    protected function importAssertCSVScenario(string $scenarioName = '')
    {
        $scenarioFileName = $this->assertionDataSetDirectory . $scenarioName . '.csv';
        $scenarioFileName = GeneralUtility::getFileAbsFileName($scenarioFileName);
        $this->assertCSVDataSet($scenarioFileName);
    }

    /**
     * @test
     */
    public function translationOfMetadataWithoutNewFileVariantCopiesAndRelatesDefaultFile()
    {
        $this->actionService->localizeRecord('sys_file_metadata', 1, 1);
        $this->importAssertCSVScenario('metadataTranslationWithoutVariantUpload');
    }

    /**
     * @test
     */
    public function translationOfMetaDataCreatesTranslatedSysFileRecord () {
        $this->actionService->localizeRecord('sys_file_metadata', 1, 1);
        //@todo simulate upload of new file into translated metadata record (will end up in sys_file)
        $this->importAssertCSVScenario('metadataTranslationWithVariantUpload');
    }

    /**
     * @test
     */
    public function changingFileVariantInTranslatedMetadataRecordReplacesFormerVariant()
    {
        $this->actionService->localizeRecord('sys_file_metadata', 1, 1);
        //@todo simulate upload of new file into translated metadata record (will end up in sys_file)
        $this->actionService->modifyRecord('sys_file_metadata', 2, []);
        $this->importAssertCSVScenario('metadataTranslationReplacedVariantUpload');
    }

    /**
     * @test
     */
    public function providingFileVariantInTranslatedMetadataRecordCreatesVariant()
    {
        $this->actionService->localizeRecord('sys_file_metadata', 1, 1);
        //@todo simulate upload of new file into translated metadata record (will end up in sys_file)
        $this->actionService->modifyRecord('sys_file_metadata', 2, []);
        $this->importAssertCSVScenario('metadataTranslationCreatesVariantUpload');
    }

    /**
     * @test
     */
    public function translatedReferenceInConnectedModeRelatesToFileVariant()
    {
        $this->actionService->localizeRecord('sys_file', 1, 1);
        $this->actionService->localizeRecord('sys_file_metadata', 1 ,1);
        $this->actionService->localizeRecord('tt_content', 1, 1);
        $this->importAssertCSVScenario('ttContentTranslatedConnectedMode');
    }

    /**
     * @test
     */
    public function translatedReferenceInConnectedModeRelatesToDefaultFileIfNoVariantExists()
    {
        $this->actionService->localizeRecord('tt_content', 1, 1);
        $this->importAssertCSVScenario('ttContentTranslatedConnectedModeNoFileVariant');
    }

    /**
     * @test
     */
    public function translatedReferenceInFreeModeRelatesToDefaultFile()
    {
        $this->actionService->localizeRecord('sys_file', 1, 1);
        $this->actionService->localizeRecord('sys_file_metadata', 1 ,1);
        $this->actionService->copyRecordToLanguage('tt_content', 1, 1);
        $this->importAssertCSVScenario('ttContentTranslatedFreeMode');
    }

    public function providingFileVariantCausesUpdateOfAllConsumersInConnectedMode()
    {

    }

    public function providingFileVariantDoesNotTouchAllConsumersInFreeMode()
    {

    }

    public function deletionOfFileVariantResetsAllConsumersInConnectedModeToDefaultFile()
    {

    }

    public function deletionOfFileVariantDoesNotTouchAllConsumersInConnectedMode()
    {

    }

    public function deletionOfDefaultFileCausesResetToDefaultFileForAllTranslations()
    {
        // remove default file -> remove variants -> update consumers to relate to default file
        // leads to broken relations, this is the case already before the change.
    }
}
