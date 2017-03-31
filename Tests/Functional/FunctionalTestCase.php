<?php
declare(strict_types=1);
namespace T3G\AgencyPack\Tests\Functional;

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
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\Security\FileMetadataPermissionsAspect;
use TYPO3\CMS\Core\Tests\Functional\DataHandling\Framework\ActionService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
  * Description
  */
class FunctionalTestCase extends \TYPO3\TestingFramework\Core\Functional\FunctionalTestCase {
    protected $assertionDataSetDirectory;
    protected $scenarioDataSetDirectory;

    /**
     * @var int
     */
    protected $expectedErrorLogEntries = 0;
    /**
     * @var \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
     */
    protected $backendUser;
    /**
     * @var ActionService
     */
    protected $actionService;

    protected function setUp()
    {
        $this->testExtensionsToLoad[] = 'typo3conf/ext/file_variants';
        $this->testExtensionsToLoad[] = 'typo3conf/ext/news';

        parent::setUp();

        // make sure there are no leftover files from earlier tests
        // done in setup because teardown is called only once per file
        if (file_exists(PATH_site . 'languageVariants')) {
            system('rm -rf ' . escapeshellarg(PATH_site . 'languageVariants'));
        }

        Bootstrap::getInstance()->initializeLanguageObject();

        $this->backendUser = $this->setUpBackendUserFromFixture(1);
        $fileMetadataPermissionAspect = $this->prophesize(FileMetadataPermissionsAspect::class);
        GeneralUtility::setSingletonInstance(FileMetadataPermissionsAspect::class,
            $fileMetadataPermissionAspect->reveal());

        $this->actionService = new ActionService();

        // done to prevent an error during processing
        // it makes no difference here whether file filters apply to the data set
        unset($GLOBALS['TCA']['tt_content']['columns']['image']['config']['filter']);

        // set up the second file storage
        mkdir(PATH_site . 'languageVariants/languageVariants', 0777, true);
        mkdir(PATH_site . 'languageVariants/_processed_', 0777, true);

        $this->initializeFeatureConfig();
    }

    protected function tearDown()
    {
        $this->cleanUpFilesAndRelatedRecords();
        unset($this->actionService);
        $this->assertErrorLogEntries();
        parent::tearDown();
    }

    /**
     * remove files and related records (sys_file, sys_file_metadata) from environment
     */
    protected function cleanUpFilesAndRelatedRecords()
    {
        // find files in storage
        $storage = ResourceFactory::getInstance()->getStorageObject(2);
        $recordsToDelete = ['sys_file' => [], 'sys_file_metadata' => []];
        try {
            $folder = $storage->getFolder('languageVariants');
            $files = $storage->getFilesInFolder($folder);
            foreach ($files as $file) {
                $storage->deleteFile($file);
                $recordsToDelete['sys_file'][] = $file->getUid();
                $metadata = $file->_getMetaData();
                $recordsToDelete['sys_file_metadata'][] = (int)$metadata['uid'];
            }
        } catch (\Exception $exception) {
            // sometimes, there is no folder to empty. Let's ignore that.
        }
        $this->actionService->deleteRecords($recordsToDelete);
    }

    /**
     * Asserts correct number of warning and error log entries.
     *
     * @return void
     */
    protected function assertErrorLogEntries()
    {
        if ($this->expectedErrorLogEntries === null) {
            return;
        }
        $queryBuilder = $this->getConnectionPool()
            ->getQueryBuilderForTable('sys_log');
        $queryBuilder->getRestrictions()->removeAll();
        $statement = $queryBuilder
            ->select('*')
            ->from('sys_log')
            ->where(
                $queryBuilder->expr()->in(
                    'error',
                    $queryBuilder->createNamedParameter([1, 2], Connection::PARAM_INT_ARRAY)
                )
            )
            ->execute();

        $actualErrorLogEntries = $statement->rowCount();
        if ($actualErrorLogEntries === $this->expectedErrorLogEntries) {
            $this->assertSame($this->expectedErrorLogEntries, $actualErrorLogEntries);
        } else {
            $failureMessage = 'Expected ' . $this->expectedErrorLogEntries . ' entries in sys_log, but got ' . $actualErrorLogEntries . LF;
            while ($entry = $statement->fetch()) {
                $entryData = unserialize($entry['log_data']);
                $entryMessage = vsprintf($entry['details'], $entryData);
                $failureMessage .= '* ' . $entryMessage . LF;
            }
            $this->fail($failureMessage);
        }
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

}