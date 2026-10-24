<?php

declare(strict_types=1);

namespace Local\WhatsAppCheckout\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddPhoneNumber implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerSetupFactory $customerSetupFactory
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $setup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $setup->addAttribute(Customer::ENTITY, 'phone_number', [
            'type' => 'varchar',
            'label' => 'WhatsApp phone number',
            'input' => 'text',
            'required' => false,
            'visible' => true,
            'system' => false,
            'position' => 150,
        ]);
        $attribute = $setup->getEavConfig()->getAttribute(Customer::ENTITY, 'phone_number');
        $attribute->setData('used_in_forms', ['customer_account_create', 'adminhtml_customer']);
        $attribute->save();
        $this->moduleDataSetup->getConnection()->endSetup();
        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
