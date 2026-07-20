<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Exception;

/**
 * Thrown when a compliance business rule is violated (e.g. missing required field,
 * invalid submitted value, disallowed file type/size, invalid workflow transition).
 *
 * This is the 422-mapping vehicle called out in the Eng spec's Open Questions:
 * {@see \Magento\Framework\Webapi\ErrorProcessor} does not natively translate a
 * LocalizedException's message into a 422 Unprocessable Entity response, so the
 * `$httpCode` property and {@see getHttpCode()} accessor are added here to carry the
 * intended status code through to the webapi layer (e.g. via a custom exception
 * renderer/plugin). This mapping must be verified against actual
 * ErrorProcessor/webapi framework behavior during integration testing, as the
 * framework's default handling may need to be extended to honor it.
 */
class BusinessRuleException extends \Magento\Framework\Exception\LocalizedException
{
    /**
     * @var int
     */
    protected $httpCode = 422;

    /**
     * Get the HTTP status code this exception should be mapped to.
     *
     * @return int
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}
