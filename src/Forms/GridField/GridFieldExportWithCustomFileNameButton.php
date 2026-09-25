<?php

namespace Sunnysideup\EcommerceCustomProductLists\Forms\GridField;

use Override;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Control\HTTPRequest;

class GridFieldExportWithCustomFileNameButton extends GridFieldExportButton
{
    protected const string DEFAULT_FILE_NAME_TEMPLATE = "export-{now}.csv";
    protected $fileNameTemplate = self::DEFAULT_FILE_NAME_TEMPLATE;

    /**
     * Set the file name template for the export file.
     *
     * @param string|null $template '{now}' will be replaced with the current datetime
     * @return $this
     */
    public function setFileNameTemplate(?string $template): self
    {
        if (!$template) {
            $template = self::DEFAULT_FILE_NAME_TEMPLATE;
        } else {
            $template = preg_replace('/[\/\\\?%*:|"<>\s]/', '_', $template);
            $template = preg_replace('/_+/', '_', $template);
            $template = trim($template);
        }
        $this->fileNameTemplate = $template;
        return $this;
    }

    #[Override]
    public function handleExport($gridField, $request = null)
    {
        $now = date("d-m-Y-H-i");
        $fileName = str_replace('{now}', $now, $this->fileNameTemplate);

        if ($fileData = $this->generateExportFileData($gridField)) {
            return HTTPRequest::send_file($fileData, $fileName, 'text/csv');
        }
        return null;
    }
}
