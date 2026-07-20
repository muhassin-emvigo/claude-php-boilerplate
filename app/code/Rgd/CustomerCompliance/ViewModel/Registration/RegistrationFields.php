<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\ViewModel\Registration;

use Magento\Framework\Escaper;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Rgd\CustomerCompliance\Api\FieldConfigProviderInterface;

/**
 * Assembles the eligible-group / dynamic-field configuration for the customer registration
 * page and exposes it as pre-serialized JSON for the Knockout-driven field reveal widget.
 */
class RegistrationFields implements ArgumentInterface
{
    /**
     * @param FieldConfigProviderInterface $fieldConfigProvider
     * @param Json $jsonSerializer
     * @param Escaper $escaper
     */
    public function __construct(
        private readonly FieldConfigProviderInterface $fieldConfigProvider,
        private readonly Json $jsonSerializer,
        private readonly Escaper $escaper
    ) {
    }

    /**
     * Whether there is at least one customer group eligible for compliance configuration.
     *
     * Per the Design doc's empty-state rule, the template uses this to decide whether to
     * render the compliance section AT ALL (zero eligible groups => omit the section).
     *
     * @return bool
     */
    public function hasEligibleGroups(): bool
    {
        return count($this->fieldConfigProvider->getEligibleGroups()) > 0;
    }

    /**
     * Build the serialized JSON payload describing every eligible group and its fields.
     *
     * String VALUES are HTML-escaped before serialization (labels/group labels are rendered
     * as text by the Knockout templates); the resulting JSON string itself must NOT be
     * html-escaped again when embedded in the page (it is embedded via @noEscape as the
     * argument to an x-magento-init block).
     *
     * @return string
     */
    public function getEligibleGroupsJson(): string
    {
        $groups = [];

        foreach ($this->fieldConfigProvider->getEligibleGroups() as $group) {
            $fields = [];

            foreach ($this->fieldConfigProvider->getFieldsForGroup($group->getCustomerGroupId()) as $field) {
                $fields[] = [
                    'fieldId' => (int)$field->getFieldId(),
                    'fieldCode' => $field->getFieldCode(),
                    'fieldType' => $field->getFieldType(),
                    'label' => $this->escaper->escapeHtml($field->getLabel()),
                    'isRequired' => $field->isRequired(),
                    'sortOrder' => $field->getSortOrder(),
                    'options' => $this->escapeOptions($field->getOptions()),
                    'allowedExtensions' => $field->getAllowedExtensions(),
                    'maxFileSizeKb' => $field->getMaxFileSizeKb(),
                ];
            }

            usort($fields, static fn (array $a, array $b): int => $a['sortOrder'] <=> $b['sortOrder']);

            $groups[] = [
                'groupId' => $group->getCustomerGroupId(),
                'groupLabel' => $this->escaper->escapeHtml($group->getGroupLabel()),
                'isApprovalRequired' => $group->isApprovalRequired(),
                'fields' => $fields,
            ];
        }

        return $this->jsonSerializer->serialize($groups);
    }

    /**
     * Escape the string values (labels) inside a field's options array, if present.
     *
     * @param array|null $options
     * @return array|null
     */
    private function escapeOptions(?array $options): ?array
    {
        if ($options === null) {
            return null;
        }

        $escaped = [];
        foreach ($options as $key => $value) {
            if (is_array($value)) {
                $escaped[$key] = $this->escapeOptions($value);
                continue;
            }

            $escaped[$key] = is_string($value) ? $this->escaper->escapeHtml($value) : $value;
        }

        return $escaped;
    }
}
