<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Controller\Adminhtml\Field;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Throwable;
use Rgd\CustomerCompliance\Api\Data\FieldInterface;
use Rgd\CustomerCompliance\Api\Data\FieldInterfaceFactory;
use Rgd\CustomerCompliance\Api\FieldRepositoryInterface;

/**
 * Persists a Field from the admin edit form.
 */
class Save extends Action
{
    public const ADMIN_RESOURCE = 'Rgd_CustomerCompliance::field';

    /**
     * Field types that require at least one option to be data-consistent.
     */
    private const OPTION_REQUIRING_TYPES = ['dropdown', 'radio', 'checkbox'];

    /**
     * @param Context $context
     * @param FieldRepositoryInterface $fieldRepository
     * @param FieldInterfaceFactory $fieldFactory
     */
    public function __construct(
        Context $context,
        private readonly FieldRepositoryInterface $fieldRepository,
        private readonly FieldInterfaceFactory $fieldFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Save a compliance field definition.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $data = $this->getRequest()->getParams();
        $fieldId = (int)($data['field_id'] ?? 0);
        $configId = (int)($data['config_id'] ?? 0);
        $fieldType = (string)($data['field_type'] ?? '');
        $options = $data['options'] ?? [];

        // Safety net: the admin form always posts an explicit "field_code" now (see
        // view/adminhtml/ui_component/field_form.xml), but any other caller (e.g. a future REST
        // consumer) that omits it would otherwise save with an empty field_code, colliding with
        // the "config_id + field_code + version" unique constraint on the second field created
        // under the same config. Auto-derive one from the label when absent.
        if (empty(trim((string)($data['field_code'] ?? '')))) {
            $data['field_code'] = $this->slugifyFieldCode((string)($data['label'] ?? ''), $fieldId);
        }

        // Design doc A4 resolution: block save entirely if a choice-type field has zero options,
        // rather than allowing a data-integrity gap where the field renders with no choices.
        if (in_array($fieldType, self::OPTION_REQUIRING_TYPES, true) && empty($options)) {
            $this->messageManager->addErrorMessage(
                __('Fields of type "%1" require at least one option.', $fieldType)
            );

            return $resultRedirect->setPath(
                '*/*/edit',
                ['field_id' => $fieldId ?: null, 'config_id' => $configId]
            );
        }

        try {
            if ($fieldId) {
                try {
                    $field = $this->fieldRepository->getById($fieldId);
                } catch (NoSuchEntityException $e) {
                    $this->messageManager->addErrorMessage(__('This field no longer exists.'));
                    return $resultRedirect->setPath('*/*/index', ['config_id' => $configId]);
                }
            } else {
                /** @var FieldInterface $field */
                $field = $this->fieldFactory->create();
            }

            // Explicit, type-cast assignment per field - replaces a previous generic
            // "reflection setter loop" (derive setX from each raw $_POST key and call it if a
            // matching method exists) that was found to be broken under this module's
            // `declare(strict_types=1)` files: PHP applies the CALLING file's strict_types
            // setting to the call, so passing raw (always-string) request values to any
            // int/nullable-int-typed setter (setConfigId, setFieldId, setSortOrder,
            // setMaxFileSizeKb) threw a TypeError, and the loop's key-to-setter-name transform
            // never matched "is_required" to the real setter (setRequired, not setIsRequired) at
            // all, silently dropping that value. Net effect of both bugs together: saving any
            // field from the admin form always failed outright, and even a hypothetically-fixed
            // save would have silently ignored the "Required" checkbox.
            $field->setConfigId($configId);
            $field->setFieldCode((string)$data['field_code']);
            $field->setFieldType($fieldType);
            $field->setLabel((string)($data['label'] ?? ''));
            $field->setRequired(!empty($data['is_required']));
            $field->setSortOrder((int)($data['sort_order'] ?? 0));
            $field->setOptions($options);
            $field->setAllowedExtensions(
                isset($data['allowed_extensions']) && $data['allowed_extensions'] !== ''
                    ? (string)$data['allowed_extensions']
                    : null
            );
            $field->setMaxFileSizeKb(
                isset($data['max_file_size_kb']) && $data['max_file_size_kb'] !== ''
                    ? (int)$data['max_file_size_kb']
                    : null
            );

            $this->fieldRepository->save($field);

            $this->messageManager->addSuccessMessage(__('The field has been saved.'));

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath(
                    '*/*/edit',
                    ['field_id' => $field->getFieldId(), 'config_id' => $configId]
                );
            }

            return $resultRedirect->setPath('*/*/index', ['config_id' => $configId]);
        } catch (Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Something went wrong while saving the field: %1', $e->getMessage())
            );

            return $resultRedirect->setPath(
                '*/*/edit',
                ['field_id' => $fieldId ?: null, 'config_id' => $configId]
            );
        }
    }

    /**
     * Derive a "field_code"-safe slug from a human label when none was explicitly posted.
     *
     * Lowercases, replaces anything that isn't a letter/number with an underscore, collapses
     * repeats, and trims leading/trailing underscores. Falls back to a field-id/timestamp-based
     * placeholder if the label itself has no usable characters (e.g. an all-emoji label),
     * so this can never itself produce an empty string and re-trip the NOT NULL constraint.
     *
     * @param string $label
     * @param int $fieldId
     * @return string
     */
    private function slugifyFieldCode(string $label, int $fieldId): string
    {
        $slug = strtolower(trim($label));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? '';
        $slug = trim($slug, '_');

        if ($slug === '') {
            $slug = 'field_' . ($fieldId ?: uniqid());
        }

        return $slug;
    }
}
