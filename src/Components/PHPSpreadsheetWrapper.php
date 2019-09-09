<?php

namespace DreamFactory\Core\Excel\Components;

use PhpOffice\PhpSpreadsheet\IOFactory;
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Exceptions\NotFoundException;


class PHPSpreadsheetWrapper
{
    /**
     * Response of storage container GET folder.
     *
     * @type array
     */
    public $storageContainerFiles = [];

    /**
     * Storage service name.
     *
     * @type string
     */
    public $serviceName = '';

    /**
     * Storage container path.
     *
     * @type string
     */
    public $storageContainer = '';

    /**
     * Name of the spreadsheet.
     *
     * @type string
     */
    public $spreadsheetName = '';

    /**
     * Does first row contain headers.
     *
     * @type boolean
     */
    public $firstRowHeaders = '';

    /**
     * @param $storageContainerFiles
     * @param $serviceName
     * @param $storageContainer
     * @param $spreadsheetName
     * @param $firstRowHeaders
     */
    public function __construct($storageContainerFiles, $serviceName, $storageContainer, $spreadsheetName, $firstRowHeaders)
    {
        $this->storageContainerFiles= $storageContainerFiles;
        $this->serviceName = $serviceName;
        $this->storageContainer = $storageContainer;
        $this->spreadsheetName = $spreadsheetName;
        $this->firstRowHeaders = $firstRowHeaders;
    }

    /**
     * Get all spreadsheet data as JSON
     *
     * @return array
     */
    public function getSpreadsheet()
    {
        if (!self::doesSpreadsheetExist($this->spreadsheetName, $this->storageContainerFiles)) {
            throw new NotFoundException("Spreadsheet '{$this->spreadsheetName}' not found.");
        };
        $content = [];
        $spreadsheetFile = self::getSpreadsheetFile();
        $spreadsheet = IOFactory::load($spreadsheetFile);
        foreach ($spreadsheet->getSheetNames() as $worksheetName) {
            $records = [];
            $headers = [];
            foreach ($spreadsheet->getSheetByName($worksheetName)->getRowIterator() as $key => $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(TRUE);
                $row_values = [];
                foreach ($cellIterator as $cell) {
                    $row_values[] = $cell->getValue();
                }
                if ($this->firstRowHeaders && $key === 1) {
                    $headers = $row_values;
                    continue;
                }
                $records[] = self::mapRowContent($headers, $row_values);
            }
            $content[$worksheetName] = $records;
        };
        return $content;
    }

    /**
     * Get all spreadsheet data as JSON
     *
     * @return array
     */
    public function getSpreadsheetTab()
    {
        return $this->storageContainerFiles;
    }

    /**
     * Map spreadsheet content
     *
     * @param $headers
     * @param $data
     * @return object
     */
    protected function mapRowContent($headers, $data)
    {
        $result = [];

        foreach ($data as $key => $cellValue) {
            $header = isset($headers[$key]) ? $headers[$key] : (string) $key;
            $result[$header] = $cellValue;
        }

        return (object)$result;
    }


    /**
     * Does spreadsheet exists in the given container
     *
     * @param $list
     * @param $spreadsheetName
     * @return bool
     */
    protected function doesSpreadsheetExist($spreadsheetName, $list = [])
    {
        // TODO: maybe use file service's fileExists method ?
        if (isset($list->getContent()['resource'])) {
            $list = $list->getContent()['resource'];
        } else {
            $list = $list->getContent();
        }
        if ($list) {
            foreach ($list as $file) {
                if (isset($file['name']) && $file['name'] === $spreadsheetName) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get Spreadsheet using file storage service
     *
     * @return bool|string
     */
    protected function getSpreadsheetFile()
    {
        $result = \ServiceManager::handleRequest(
            $this->serviceName,
            Verbs::GET,
            $this->storageContainer . $this->spreadsheetName,
            ['download' => 1, 'content' => 1, 'include_properties' => 1]
        );
        $tmpFile = tempnam(sys_get_temp_dir(), 'tempexcel');
        file_put_contents($tmpFile, base64_decode($result->getContent()['content']));
        return $tmpFile;
    }
}