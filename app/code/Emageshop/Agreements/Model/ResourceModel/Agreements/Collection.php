<?php
namespace Emageshop\Agreements\Model\ResourceModel\Agreements;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Emageshop\Agreements\Model\ResourceModel\Agreements;

class Collection extends AbstractCollection
{
    /**
     * @type string
     */
    protected $_idFieldName = 'agreement_id';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Emageshop\Agreements\Model\Agreements::class, Agreements::class);
    }
}
