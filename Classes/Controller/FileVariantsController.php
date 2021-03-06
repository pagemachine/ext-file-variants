<?php
declare(strict_types=1);

/*
 * This file is part of the package t3g/file_variants.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace T3G\AgencyPack\FileVariants\Controller;

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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Backend\Form\FormResultCompiler;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\ReferenceIndex;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FileVariantsController
{
    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function ajaxResetFileVariant(ServerRequestInterface $request): ResponseInterface
    {
        $uid = (int)$request->getQueryParams()['uid'];

        $formDataGroup = GeneralUtility::makeInstance(TcaDatabaseRecord::class);
        $formDataCompiler = GeneralUtility::makeInstance(FormDataCompiler::class, $formDataGroup);
        $nodeFactory = GeneralUtility::makeInstance(NodeFactory::class);
        /** @var FormResultCompiler $formResultCompiler */
        $formDataCompilerInput = [
            'tableName' => 'sys_file_metadata',
            'vanillaUid' => $uid,
            'command' => 'edit',
        ];
        $formData = $formDataCompiler->compile($formDataCompilerInput);
        $formData['renderType'] = 'fileInfo';

        $fileUid = (int)$formData['databaseRow']['file'][0];
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file');
        $queryBuilder->select('l10n_parent')->from('sys_file')->where(
            $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($fileUid, \PDO::PARAM_INT))
        );

        $fileRecord = $queryBuilder->execute()->fetch();
        $defaultFileObject = GeneralUtility::makeInstance(ResourceFactory::class)->getFileObject((int)$fileRecord['l10n_parent']);
        $copy = $defaultFileObject->copyTo($defaultFileObject->getParentFolder());
        // this record will be stale after the replace, remove it right away
        $sysFileRecordToBeDeleted = $copy->getUid();
        $path = $this->getAbsolutePathToFile($copy);

        $file = GeneralUtility::makeInstance(ResourceFactory::class)->getFileObject($fileUid);
        $file->getStorage()->replaceFile($file, $path);
        $file->rename($defaultFileObject->getName(), DuplicationBehavior::RENAME);
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file');
        $queryBuilder->delete('sys_file')->where(
            $queryBuilder->expr()
                ->eq('uid', $queryBuilder->createNamedParameter($sysFileRecordToBeDeleted, \PDO::PARAM_INT))
        )->execute();

        // metadata records for copied file are not needed, either
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_file_metadata');
        $queryBuilder->select('uid')->from('sys_file_metadata')->where(
            $queryBuilder->expr()->eq(
                'file',
                $queryBuilder->createNamedParameter($sysFileRecordToBeDeleted, \PDO::PARAM_INT)
            )
        );
        $metadataRecordsToBeDeleted = $queryBuilder->execute()->fetchAll(\PDO::FETCH_COLUMN);
        if (count($metadataRecordsToBeDeleted) > 0) {
            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('sys_file_metadata');
            $queryBuilder->delete('sys_file_metadata')->where(
                $queryBuilder->expr()
                    ->in('uid', $metadataRecordsToBeDeleted, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY)
            )->execute();
        }

//        /** @var $refIndexObj ReferenceIndex */
//        $refIndexObj = GeneralUtility::makeInstance(ReferenceIndex::class);
//        $refIndexObj->updateIndex(false);

        $formResult = $nodeFactory->create($formData)->render();
        $response = new HtmlResponse($formResult['html']);

        return $response;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function ajaxReplaceFileVariant(ServerRequestInterface $request): ResponseInterface
    {
        return $this->ajaxUploadFileVariant($request);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function ajaxUploadFileVariant(ServerRequestInterface $request): ResponseInterface
    {
        $uploadedFileUid = (int)$request->getQueryParams()['file'];
        $metadataUid = (int)$request->getQueryParams()['uid'];

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_metadata');
        $queryBuilder->select('file')->from('sys_file_metadata')->where(
            $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($metadataUid, \PDO::PARAM_INT))
        );
        $currentFileUid = $queryBuilder->execute()->fetchColumn();

        $currentFile = GeneralUtility::makeInstance(ResourceFactory::class)->getFileObject($currentFileUid);
        $uploadedFile = GeneralUtility::makeInstance(ResourceFactory::class)->getFileObject($uploadedFileUid);
        $currentFile->getStorage()->replaceFile($currentFile, $this->getAbsolutePathToFile($uploadedFile));
        $currentFile->rename($uploadedFile->getName(), DuplicationBehavior::RENAME);

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file');
        $queryBuilder->delete('sys_file')->where(
            $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uploadedFileUid, \PDO::PARAM_INT))
        )->execute();

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_metadata');
        $queryBuilder->delete('sys_file_metadata')->where(
            $queryBuilder->expr()->eq('file', $queryBuilder->createNamedParameter($uploadedFileUid, \PDO::PARAM_INT))
        )->execute();

        /** @var $refIndexObj ReferenceIndex */
//        $refIndexObj = GeneralUtility::makeInstance(ReferenceIndex::class);
//        $refIndexObj->updateIndex(false);

        $formDataGroup = GeneralUtility::makeInstance(TcaDatabaseRecord::class);
        $formDataCompiler = GeneralUtility::makeInstance(FormDataCompiler::class, $formDataGroup);
        $nodeFactory = GeneralUtility::makeInstance(NodeFactory::class);
        /** @var FormResultCompiler $formResultCompiler */
        $formDataCompilerInput = [
            'tableName' => 'sys_file_metadata',
            'vanillaUid' => $metadataUid,
            'command' => 'edit',
        ];
        $formData = $formDataCompiler->compile($formDataCompilerInput);
        $formData['renderType'] = 'fileInfo';

        $formResult = $nodeFactory->create($formData)->render();
        $response = new HtmlResponse($formResult['html']);

        return $response;
    }

    /**
     * Returns an absolute file path to the given File resource
     *
     * @param File $file
     * @return string
     */
    protected function getAbsolutePathToFile(File $file): string
    {
        $storage = $file->getStorage();
        if (!$storage->isPublic()) {
            // manually create a possibly valid file path from the storage configuration
            $storageConfiguration = $storage->getConfiguration();
            if ($storageConfiguration['pathType'] == 'absolute') {
                $path = realpath($storageConfiguration['basePath']) . $file->getIdentifier();
            } else {
                $path = realpath(Environment::getPublicPath() . '/' . $storageConfiguration['basePath']) . $file->getIdentifier();
            }
        } else {
            $path = Environment::getPublicPath() . '/' . $file->getPublicUrl();
        }

        return $path;
    }
}
