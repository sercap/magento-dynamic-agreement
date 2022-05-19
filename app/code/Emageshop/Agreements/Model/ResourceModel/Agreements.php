<?php
namespace Emageshop\Agreements\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Agreements extends AbstractDb
{
    /**
     * @inheritdoc
     */
    public function _construct()
    {
        $this->_init('dynamic_agreements', 'agreement_id');
    }
}
