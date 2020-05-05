<?php
namespace DistributorTools\StockArtLibrary\Plugin;

use Magento\Framework\Exception\CouldNotSaveException;

class StockArtCoverSave
{
    protected $_stockArtCoverExtensionFactory;
    protected $_stockArtImagesAttributeFactory;
    protected $_logger;

    public function __construct (
        \DistributorTools\StockArtLibrary\Api\Data\StockArtCoverExtensionFactory $stockArtCoverExtensionFactory,
        \DistributorTools\StockArtLibrary\Model\StockArtImagesAttributeFactory $stockArtImagesAttributeFactory,
        \File\CustomLog\Logger\Logger $logger
    ) 
    {
        $this->_stockArtCoverExtensionFactory = $stockArtCoverExtensionFactory;
        $this->_stockArtImagesAttributeFactory = $stockArtImagesAttributeFactory;
        $this->_logger = $logger;
    }

    public function afterSave (
        \DistributorTools\StockArtLibrary\Api\StockArtCoverRepositoryInterface $subject,
        \DistributorTools\StockArtLibrary\Api\Data\StockArtCoverInterface $resultStockArtCover
    )
    {
        $resultStockArtCover = $this->saveStockArtImagesAttribute($resultStockArtCover);
        return $resultStockArtCover;
    }

    private function saveStockArtImagesAttribute(\DistributorTools\StockArtLibrary\Api\Data\StockArtCoverInterface $stockArtCover )
    {
        $this->_logger->addDebug("--------AFTER SAVE STOCK ART COVER ------------");
        $imageId = $stockArtCover->getCoverId();
        $title = 'This is a stock art image title';
        $urlImage = 'www.blablablabla.com/image';

        $stockArtImagesAttribute = $this->_stockArtImagesAttributeFactory->create();        
        $stockArtImagesAttribute->setImageId( $imageId );
        $stockArtImagesAttribute->setTitle( $title );
        $stockArtImagesAttribute->setUrlImage( $urlImage );
        $stockArtImagesAttribute->save();
       
        return $stockArtCover;
    }
}