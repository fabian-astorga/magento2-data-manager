<?php

namespace Services\GlitterInserts\Model\Import;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\ImportExport\Helper\Data as ImportHelper;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\Entity\AbstractEntity;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\ImportExport\Model\ResourceModel\Helper;
use Magento\ImportExport\Model\ResourceModel\Import\Data;

/**
 * Class GlitterInserts
 */
class GlitterInserts extends AbstractEntity
{
    const ENTITY_CODE          = 'glitter_inserts';
    const TABLE                = 'glitter_inserts';
    const ENTITY_ID_COLUMN     = 'id';
    const SKU                  = 'sku';
    const IMG                  = 'img';
    const NAME                 = 'name';
    const TYPE                 = 'type';

    /**
     * If we should check column names
     */
    protected $needColumnCheck = true;

    /**
     * Need to log in import history
     */
    protected $logInHistory = true;

    /**
     * Permanent entity columns.
     */
    protected $_permanentAttributes = [
        self::ENTITY_ID_COLUMN
    ];

    /**
     * Valid column names
     */
    protected $validColumnNames = [
        self::ENTITY_ID_COLUMN,
        self::SKU,
        self::IMG,
        self::NAME,
        self::TYPE
    ];

    /**
     * @var AdapterInterface
     */
    protected $connection;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var \Services\GlitterInserts\Api\GlitterInsertsRepositoryInterface
     */
    protected $_glitterInsertsRepository;

    protected $_logger;

    /**
     * Constructor.
     *
     * @param JsonHelper $jsonHelper
     * @param ImportHelper $importExportData
     * @param Data $importData
     * @param ResourceConnection $resource
     * @param Helper $resourceHelper
     * @param ProcessingErrorAggregatorInterface $errorAggregator
     * @param \Services\GlitterInserts\Api\GlitterInsertsRepositoryInterface $glitterInsertsRepository
     */
    public function __construct(
        JsonHelper $jsonHelper,
        ImportHelper $importExportData,
        Data $importData,
        ResourceConnection $resource,
        Helper $resourceHelper,
        ProcessingErrorAggregatorInterface $errorAggregator,
        \Services\GlitterInserts\Api\GlitterInsertsRepositoryInterface $glitterInsertsRepository,
        \File\CustomLog\Logger\Logger $logger
    ) {
        $this->jsonHelper                  = $jsonHelper;
        $this->_importExportData           = $importExportData;
        $this->_resourceHelper             = $resourceHelper;
        $this->_dataSourceModel            = $importData;
        $this->resource                    = $resource;
        $this->connection                  = $resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $this->errorAggregator             = $errorAggregator;
        $this->_glitterInsertsRepository   = $glitterInsertsRepository;
        $this->_logger                     = $logger;
        $this->initMessageTemplates();
    }

    /**
     * Init Error Messages
     */
    private function initMessageTemplates()
    {
        $this->addMessageTemplate(
            'SkuIsRequired',
            __('The sku cannot be empty.')
        );
        $this->addMessageTemplate(
            'NameIsRequired',
            __('The name cannot be empty.')
        );
    }

    /**
     * Entity type code getter.
     *
     * @return string
     */
    public function getEntityTypeCode()
    {
        return static::ENTITY_CODE;
    }

    /**
     * Get available columns
     *
     * @return array
     */
    public function getValidColumnNames(): array
    {
        return $this->validColumnNames;
    }

    /**
     * Row validation
     *
     * @param array $rowData
     * @param int $rowNum
     *
     * @return bool
     */
    public function validateRow(array $rowData, $rowNum): bool
    {
        $sku  = $rowData[self::SKU] ?? '';
        $name = $rowData[self::NAME] ?? '';

        if (!$sku) {
            $this->addRowError('SkuIsRequired', $rowNum);
        }

        if (!$name) {
            $this->addRowError('NameIsRequired', $rowNum);
        }

        if (isset($this->_validatedRows[$rowNum])) {
            return !$this->getErrorAggregator()->isRowInvalid($rowNum);
        }

        $this->_validatedRows[$rowNum] = true;

        return !($this->getErrorAggregator()->isRowInvalid($rowNum) );
    }

    /**
     * Import data
     *
     * @return bool
     */
    protected function _importData(): bool
    {
        switch ($this->getBehavior() ) {
            case Import::BEHAVIOR_DELETE:
                $this->deleteEntity();
                break;
            case Import::BEHAVIOR_REPLACE:
                $this->saveAndReplaceEntity();
                break;
            case Import::BEHAVIOR_APPEND:
                $this->saveAndReplaceEntity();
                break;
        }

        return true;
    }

    /**
     * Delete entities
     *
     * @return bool
     */
    private function deleteEntity(): bool
    {
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $entityList = [];

            foreach ($bunch as $rowNum => $row) {
                if (!$this->validateRow($row, $rowNum)) {
                    continue;
                }

                if ($this->getErrorAggregator()->hasToBeTerminated()) {
                    $this->getErrorAggregator()->addRowToSkip($rowNum);

                    continue;
                }

                $columnValues = [];

                foreach ($this->getAvailableColumns() as $columnKey) {
                    $columnValues[$columnKey] = $row[$columnKey];
                }
                $this->countItemsDeleted += 1;
                $entityList[] = $columnValues;
            }
            if ($entityList) {
                $this->deleteEntities();
            }
        }

        return false;
    }

    /**
     * Save and replace entities
     *
     * @return void
     */
    private function saveAndReplaceEntity()
    {
        $behavior = $this->getBehavior();
        $rows = [];
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $entityList = [];

            foreach ($bunch as $rowNum => $row) {
                if (!$this->validateRow($row, $rowNum)) {
                    continue;
                }

                if ($this->getErrorAggregator()->hasToBeTerminated()) {
                    $this->getErrorAggregator()->addRowToSkip($rowNum);

                    continue;
                }

                $columnValues = [];

                foreach ($this->getAvailableColumns() as $columnKey) {
                    $columnValues[$columnKey] = $row[$columnKey];
                }

                $entityList[] = $columnValues;
                $this->countItemsCreated += (int) !(isset($row[static::ENTITY_ID_COLUMN]));
                $this->countItemsUpdated += (int) isset($row[static::ENTITY_ID_COLUMN]);
            }

            if (Import::BEHAVIOR_REPLACE === $behavior) 
            {
                $this->deleteEntities();
                $this->saveEntities($entityList);
            } 
            elseif (Import::BEHAVIOR_APPEND === $behavior) 
            {
                $this->saveEntities($entityList);
            }
        }
    }

    /**
     * Get available columns
     *
     * @return array
     */
    private function getAvailableColumns(): array
    {
        return $this->validColumnNames;
    }

    /**
     * Save entities which are stored in csv file
     * 
     * @param array $entityList
     * @return bool
     */
    private function saveEntities($entityList)
    {
        foreach ($entityList as $glitterInsert) 
        {                 
            $this->_glitterInsertsRepository->save(
                $glitterInsert[self::ENTITY_ID_COLUMN],
                $glitterInsert[self::SKU],
                $glitterInsert[self::NAME],
                $glitterInsert[self::IMG],
                $glitterInsert[self::TYPE]
            );
        }
        
        return true;
    }

    /**
     * Delete entities which are stored in csv file
     * 
     * @return array
     */
    private function deleteEntities()
    { 
        $this->_glitterInsertsRepository->delete(); 
    }
}