<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\Setup\Patch\Data;

use Magento\Customer\Model\ResourceModel\Attribute as AttributeResource;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Registers the static customer_entity columns (created by db_schema.xml) as customer EAV attributes.
 * Only phone_number and phone_verified are shown in the admin customer form.
 */
class AddPhoneCustomerAttributes implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerSetupFactory $customerSetupFactory,
        private readonly AttributeResource $attributeResource
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $customerSetup->addAttribute('customer', 'phone_number', [
            'type' => 'static',
            'label' => 'Phone Number',
            'input' => 'text',
            'required' => false,
            'visible' => true,
            'user_defined' => true,
            'system' => false,
            'position' => 200,
            'sort_order' => 200,
        ]);

        $customerSetup->addAttribute('customer', 'phone_verified', [
            'type' => 'static',
            'label' => 'Phone Verified',
            'input' => 'boolean',
            'source' => Boolean::class,
            'required' => false,
            'visible' => true,
            'user_defined' => true,
            'system' => false,
            'default' => '0',
            'position' => 210,
            'sort_order' => 210,
        ]);

        // Hidden helper attribute so the model can persist the timestamp.
        $customerSetup->addAttribute('customer', 'phone_verified_at', [
            'type' => 'static',
            'label' => 'Phone Verified At',
            'input' => 'text',
            'required' => false,
            'visible' => false,
            'user_defined' => true,
            'system' => false,
            'position' => 220,
            'sort_order' => 220,
        ]);

        $forms = [
            'phone_number' => ['adminhtml_customer'],
            'phone_verified' => ['adminhtml_customer'],
            'phone_verified_at' => [],
        ];

        foreach ($forms as $code => $usedInForms) {
            $attribute = $customerSetup->getEavConfig()->getAttribute('customer', $code);
            $attribute->setData('used_in_forms', $usedInForms);
            $this->attributeResource->save($attribute);
        }

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
