<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Options source for the compliance Field "field_type" attribute.
 *
 * Backs both the field_type filter/column on the Compliance Fields grid and the field_type
 * select element on the Field edit form. The values here must match whatever
 * Rgd\CustomerCompliance\Api\Data\FieldInterface::setFieldType()/validation elsewhere in the
 * module expects (text/textarea/number/email/phone/date/dropdown/radio/checkbox/file/image).
 */
class FieldType implements OptionSourceInterface
{
    /**
     * Field types that render as a repeatable set of options (dropdown/radio/checkbox) and so
     * need the options_json dynamicRows fieldset on the Field edit form.
     */
    public const OPTION_BEARING_TYPES = ['dropdown', 'radio', 'checkbox'];

    /**
     * Field types that accept an uploaded file and so need the allowed_extensions /
     * max_file_size_kb fields on the Field edit form.
     */
    public const FILE_TYPES = ['file', 'image'];

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'text', 'label' => __('Text')],
            ['value' => 'textarea', 'label' => __('Text Area')],
            ['value' => 'number', 'label' => __('Number')],
            ['value' => 'email', 'label' => __('Email')],
            ['value' => 'phone', 'label' => __('Phone')],
            ['value' => 'date', 'label' => __('Date')],
            ['value' => 'dropdown', 'label' => __('Dropdown')],
            ['value' => 'radio', 'label' => __('Radio Buttons')],
            ['value' => 'checkbox', 'label' => __('Checkbox Group')],
            ['value' => 'file', 'label' => __('File Upload')],
            ['value' => 'image', 'label' => __('Image Upload')],
        ];
    }
}
