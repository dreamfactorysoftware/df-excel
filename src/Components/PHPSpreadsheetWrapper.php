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
     */
    public function getWorksheetData($worksheetName = '')
    {
        $content = [];
        $headers = [];
        $skipEmptyRows = filter_var(array_get($this->parameters, 'skip_empty_rows', false), FILTER_VALIDATE_BOOLEAN);
        $calculateFormulas = filter_var(array_get($this->parameters, 'calculate_formulas', false), FILTER_VALIDATE_BOOLEAN);
        $formattedValues = filter_var(array_get($this->parameters, 'formatted_values', true), FILTER_VALIDATE_BOOLEAN);
        $firstRowHeaders = filter_var(array_get($this->parameters, 'first_row_headers', true), FILTER_VALIDATE_BOOLEAN);

        if (!$this->spreadsheet->sheetNameExists($worksheetName)) {
            throw new NotFoundException("Worksheet '{$worksheetName}' does not exist in '{$this->spreadsheetName}'.");
        };

        $worksheet = $this->spreadsheet->getSheetByName($worksheetName);
        $maxCell = $worksheet->getHighestRowAndColumn();
        $firstColumn = $worksheet->getCellByColumnAndRow(1,1)->getCoordinate();
        $range = $worksheet->rangeToArray($firstColumn . ':' . $maxCell['column'] . $maxCell['row'],
            '', $calculateFormulas, $formattedValues, true);

        foreach ($range as $key => $row) {
            if ($skipEmptyRows && $this->isEmptyRow($row)) continue;
            if ($firstRowHeaders && $key === 1) {
                $headers = $row;
                continue;
            }

            $content[] = $this->mapRowContent($headers, $row);
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
     * Check if each cell is empty
     *
     * @param $row
     * @return bool
     */
    protected function isEmptyRow($row)
    {
        foreach ($row as $cell) {
            if (!empty($cell)) {
                return false;
            }
        }
        return true;
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