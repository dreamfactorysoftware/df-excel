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
     * Response of storage container GET folder.
     *
     * @type PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    public $spreadsheet;

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
     * @type array
     */
    public $parameters = [];

    /**
     * @param $storageContainerFiles
     * @param $serviceName
     * @param $storageContainer
     * @param $spreadsheetName
     * @param $parameters
     * @throws NotFoundException
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function __construct($storageContainerFiles, $serviceName, $storageContainer, $spreadsheetName, $parameters)
    {
        $this->storageContainerFiles = $storageContainerFiles;
        $this->serviceName = $serviceName;
        $this->storageContainer = $storageContainer;
        $this->spreadsheetName = $spreadsheetName;
        $this->parameters = $parameters;

        if (!$this->doesSpreadsheetExist($this->spreadsheetName, $this->storageContainerFiles)) {
            throw new NotFoundException("Spreadsheet '{$this->spreadsheetName}' not found.");
        };

        if (isset($this->parameters['memory_limit'])) {
            ini_set('memory_limit', $this->parameters['memory_limit']);
        }

        $spreadsheetFile = $this->getSpreadsheetFile();
        $inputFileType = IOFactory::identify($spreadsheetFile);
        $reader = IOFactory::createReader($inputFileType);
        $this->spreadsheet = $reader->load($spreadsheetFile);
    }

    /**
     * Get all spreadsheet data
     *
     * @return array
     * @throws NotFoundException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function getSpreadsheetData()
    {
        $content = [];

        foreach ($this->spreadsheet->getSheetNames() as $worksheetName) {
            $content[$worksheetName] = $this->getWorksheetData($worksheetName);
        };

        return $content;
    }

    /**
     * Get worksheet data
     *
     * @param string $worksheetName
     * @return array
     * @throws NotFoundException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function getWorksheetData($worksheetName = '')
    {
        $content = [];
        $headers = [];
        $iterateExistingCells = isset($this->parameters['iterate_only_existing_cells']) && filter_var($this->parameters['iterate_only_existing_cells'], FILTER_VALIDATE_BOOLEAN);
        $formattedValues = isset($this->parameters['formatted_values']) && filter_var($this->parameters['formatted_values'], FILTER_VALIDATE_BOOLEAN);
        $firstRowHeaders = isset($this->parameters['first_row_headers']) && filter_var($this->parameters['first_row_headers'], FILTER_VALIDATE_BOOLEAN);

        if (!$this->spreadsheet->sheetNameExists($worksheetName)) {
            throw new NotFoundException("Worksheet '{$worksheetName}' does not exist in '{$this->spreadsheetName}'.");
        };

        foreach ($this->spreadsheet->getSheetByName($worksheetName)->getRowIterator() as $key => $row) {
            $row_values = [];
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells($iterateExistingCells);

            foreach ($cellIterator as $cellKey => $cell) {
                $cellValue = $formattedValues ? $cell->getFormattedValue() : $cell->getValue();
                $row_values[$cellKey] = $cellValue;
            }
            if ($firstRowHeaders && $key === 1) {
                $headers = $row_values;
                continue;
            }

            $content[] = $this->mapRowContent($headers, $row_values);
        }

        return $content;
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
            $header = isset($headers[$key]) ? $headers[$key] : (string)$key;
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