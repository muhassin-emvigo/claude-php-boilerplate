<?php

declare(strict_types=1);

namespace Rgd\CustomerCompliance\Controller\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Rgd\CustomerCompliance\Api\Data\UploadedFileInterfaceFactory;
use Rgd\CustomerCompliance\Api\DocumentResubmissionServiceInterface;
use Rgd\CustomerCompliance\Exception\BusinessRuleException;
use Throwable;

/**
 * Handles the POST from the resubmission form: parses uploaded files off the request (same
 * `$_FILES`-shape normalization approach as the registration observer) and hands them to
 * {@see DocumentResubmissionServiceInterface::resubmit()}.
 */
class ResubmitPost extends Action implements HttpPostActionInterface
{
    private const PARAM_FILES = 'customercompliance_files';

    /**
     * @param Context $context
     * @param CustomerSession $customerSession
     * @param DocumentResubmissionServiceInterface $documentResubmissionService
     * @param UploadedFileInterfaceFactory $uploadedFileFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly DocumentResubmissionServiceInterface $documentResubmissionService,
        private readonly UploadedFileInterfaceFactory $uploadedFileFactory,
        private readonly ManagerInterface $messageManager
    ) {
        parent::__construct($context);
    }

    /**
     * Resubmit compliance documents for a held order.
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            /** @var Redirect $redirect */
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $redirect->setPath('customer/account/login');
        }

        $orderId = (int)$this->getRequest()->getParam('order_id');

        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $uploadedFiles = $this->buildUploadedFiles();
            $this->documentResubmissionService->resubmit($orderId, $uploadedFiles);
            $this->messageManager->addSuccessMessage(
                __('Your documents have been resubmitted for review.')
            );

            return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
        } catch (BusinessRuleException | LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Something went wrong resubmitting your documents. Please try again.')
            );
        }

        return $redirect->setPath('customercompliance/account/resubmit', ['order_id' => $orderId]);
    }

    /**
     * Build UploadedFileInterface DTOs, keyed by field code.
     *
     * Built from the raw $_FILES-shape array on the request. Mirrors
     * PersistRegistrationComplianceObserver::buildUploadedFiles().
     *
     * @return array
     */
    private function buildUploadedFiles(): array
    {
        $uploadedFiles = [];

        $request = $this->getRequest();
        if (!$request instanceof HttpRequest) {
            return $uploadedFiles;
        }

        $files = $request->getFiles(self::PARAM_FILES);
        if (!$files) {
            return $uploadedFiles;
        }

        $filesArray = is_object($files) && method_exists($files, 'toArray') ? $files->toArray() : (array)$files;

        foreach ($filesArray as $fieldCode => $fileData) {
            if (!is_array($fileData)) {
                continue;
            }

            $errorCode = $fileData['error'] ?? null;
            if ($errorCode !== null && (int)$errorCode !== UPLOAD_ERR_OK) {
                continue;
            }

            if (empty($fileData['tmp_name'])) {
                continue;
            }

            /** @var \Rgd\CustomerCompliance\Api\Data\UploadedFileInterface $uploadedFile */
            $uploadedFile = $this->uploadedFileFactory->create();
            $uploadedFile->setFieldCode((string)$fieldCode);
            $uploadedFile->setTmpName((string)$fileData['tmp_name']);
            $uploadedFile->setOriginalName((string)($fileData['name'] ?? ''));
            $uploadedFile->setMimeType((string)($fileData['type'] ?? ''));
            $uploadedFile->setSize((int)($fileData['size'] ?? 0));

            $uploadedFiles[(string)$fieldCode] = $uploadedFile;
        }

        return $uploadedFiles;
    }
}
