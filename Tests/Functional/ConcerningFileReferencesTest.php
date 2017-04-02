<?php
declare(strict_types=1);

namespace T3G\AgencyPack\FileVariants\Tests\Functional;
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
use TYPO3\CMS\Backend\Controller\File\FileController;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConcerningFileReferencesTest extends FunctionalTestCase
{

    /**
     * @var string
     */
    protected $scenarioDataSetDirectory = 'typo3conf/ext/file_variants/Tests/Functional/DataSet/ConcerningFileReferences/Initial/';

    /**
     * @var string
     */
    protected $assertionDataSetDirectory = 'typo3conf/ext/file_variants/Tests/Functional/DataSet/ConcerningFileReferences/AfterOperation/';


    /**
     * @test
     */
    public function deleteTranslatedMetadataResetsConsumingReferencesToDefaultFile()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['file_variants'] = serialize(['variantsStorageUid' => 2, 'variantsFolder' => 'languageVariants']);
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['doNotCheckReferer'] = true;

        $scenarioName = 'deleteMetadata';
        $this->importCsvScenario($scenarioName);
        $this->setUpFrontendRootPage(1);

        copy(PATH_site . 'typo3conf/ext/file_variants/Tests/Functional/Fixture/TestFiles/cat_3.jpg', PATH_site . 'languageVariants/languageVariants/cat_3.jpg');
        $file = ResourceFactory::getInstance()->getFileObject(12);

        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/index.php';
        $_GET = ['file' => [
            'delete' => [
                [
                    'data' =>
                        $file->getUid()
                ]
            ]
        ]
        ];
        $request = ServerRequestFactory::fromGlobals();
        $response = GeneralUtility::makeInstance(Response::class);
        /** @var FileController $fileController */
        $fileController = GeneralUtility::makeInstance(FileController::class);
        $fileController->mainAction($request, $response);

        $this->importAssertCSVScenario($scenarioName);
    }

    /**
     * @test
     */
    public function translateMetadataUpdatesConsumingReferences()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['file_variants'] = serialize(['variantsStorageUid' => 2, 'variantsFolder' => 'languageVariants']);
        $scenarioName = 'translateMetadata';
        $this->importCsvScenario($scenarioName);
        $this->setUpFrontendRootPage(1);

        copy(PATH_site . 'typo3conf/ext/file_variants/Tests/Functional/Fixture/TestFiles/cat_1.jpg', PATH_site . 'fileadmin/cat_1.jpg');
        $this->actionService->localizeRecord('sys_file_metadata', 11, 1);

        $this->importAssertCSVScenario($scenarioName);
    }
}
