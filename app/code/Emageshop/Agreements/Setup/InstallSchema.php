<?php

namespace Emageshop\Agreements\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Zend_Db_Exception;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * @throws Zend_Db_Exception
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        $table = $installer->getConnection()
            ->newTable($installer->getTable('dynamic_agreements'))
            ->addColumn('agreement_id', Table::TYPE_INTEGER, null, [
                'identity' => true,
                'nullable' => false,
                'primary'  => true,
                'unsigned' => true,
            ], 'Agreement ID')
            ->addColumn('customer_id', Table::TYPE_INTEGER, null, ['nullable' => false], 'Customer Id')
            ->addColumn('quote_id', Table::TYPE_INTEGER, null, ['nullable' => false], 'Quote Id')
            ->addColumn('order_id', Table::TYPE_INTEGER, null, ['nullable' => false], 'Order Id')
            ->addColumn('agreement_code', Table::TYPE_TEXT, 1000, ['nullable' => false], 'Agreement Code')
            ->addColumn('agreement_content', Table::TYPE_TEXT, '64k', [], 'Agreement Content')
            ->addColumn('created_at', Table::TYPE_TIMESTAMP, null, ['nullable' => false], 'Created At')
            ->addIndex($setup->getIdxName('dynamic_agreements', ['customer_id', 'quote_id', 'order_id']), ['customer_id', 'quote_id', 'order_id']);

        $installer->getConnection()->createTable($table);

        $installer->endSetup();
    }
}
