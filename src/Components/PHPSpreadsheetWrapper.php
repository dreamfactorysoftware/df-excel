<?php

namespace DreamFactory\Core\Excel\Components;

use PhpOffice\PhpSpreadsheet\IOFactory;
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Exceptions\NotFoundException;
use Illuminate\Support\Arr;


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
     */
    public function getSpreadsheetData()
    {
        $content = [];
        $withWorksheetNames = array_get_bool($this->parameters, 'with_worksheet_names', true);

        foreach ($this->spreadsheet->getSheetNames() as $worksheetName) {
            if ($withWorksheetNames) {
                $content[$worksheetName] = $this->getWorksheetData($worksheetName);
            } else {
                $content = array_merge($content, $this->getWorksheetData($worksheetName));
            }
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
        $skipEmptyRows = filter_var(Arr::get($this->parameters, 'skip_empty_rows', false), FILTER_VALIDATE_BOOLEAN);
        $calculateFormulas = filter_var(Arr::get($this->parameters, 'calculate_formulas', false), FILTER_VALIDATE_BOOLEAN);
        $formattedValues = filter_var(Arr::get($this->parameters, 'formatted_values', true), FILTER_VALIDATE_BOOLEAN);
        $firstRowHeaders = filter_var(Arr::get($this->parameters, 'first_row_headers', true), FILTER_VALIDATE_BOOLEAN);

        if (!$this->spreadsheet->sheetNameExists($worksheetName)) {
            throw new NotFoundException("Worksheet '{$worksheetName}' does not exist in '{$this->spreadsheetName}'.");
        };

        $worksheet = $this->spreadsheet->getSheetByName($worksheetName);
        $maxCell = $worksheet->getHighestRowAndColumn();
        $firstColumn = $worksheet->getCell('A1')->getCoordinate();
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
        $neutralizeFormulas = filter_var(
            Arr::get($this->parameters, 'neutralize_formulas', true),
            FILTER_VALIDATE_BOOLEAN
        );

        $result = [];

        foreach ($data as $key => $cellValue) {
            $header = isset($headers[$key]) ? $headers[$key] : (string)$key;
            if (empty($header) && empty($cellValue)) continue;
            $result[$header] = $neutralizeFormulas ? self::neutralizeFormula($cellValue) : $cellValue;
        }

        return (object)$result;
    }

    /**
     * Defang spreadsheet/CSV formula injection: cell values that begin with
     * =, +, -, @, TAB, or CR are read by Excel/Numbers/LibreOffice as formulas
     * when re-emitted by the consumer. Prefix a single quote so the consumer
     * sees the literal text.
     *
     * Plain numbers are never touched: non-string values (int/float/bool/null)
     * are returned as-is, and so are strings that are only a number, such as
     * "-12.5", "+1", "-1e5" or a formatted value like "-1,234.50" / "-$1,234.50"
     * (the default formatted_values=true returns numeric cells as strings).
     * A number cannot carry a formula payload, and prefixing it would turn
     * every negative value into text.
     */
    public static function neutralizeFormula($value)
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }
        $first = $value[0];
        if ($first === '=' || $first === '+' || $first === '-' || $first === '@' || $first === "\t" || $first === "\r") {
            if (self::isNumericString($value)) {
                return $value;
            }
            return "'" . $value;
        }
        return $value;
    }

    /**
     * True when the string is nothing but a (possibly signed, possibly
     * formatted) number: no letters, no operators beyond a single leading
     * sign, no parentheses, pipes or quotes, so it cannot express a formula.
     */
    protected static function isNumericString(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }
        if (is_numeric($trimmed)) {
            return true;
        }

        // Formatted numbers: optional sign, optional leading or trailing
        // currency symbol ($ € £ ¥, space or NBSP allowed around it), digits
        // with thousands/decimal separators, optional exponent and percent.
        // Covers e.g. "-$1,234.50", "-1,234.50 €" (FORMAT_CURRENCY_EUR),
        // "-1.234,50\u{00A0}€" and "-12%".
        //
        // Deliberately conservative: anything else that starts with a sign is
        // still prefixed with an apostrophe, including negative fractions
        // ("-1 1/2"), durations ("-1:30:00"), unit suffixes ("-1.00 kg") and
        // letter currency codes ("-1,234.50 zł"). Letters are never allowed.
        return preg_match(
            '/^[+-]?[ \x{00A0}]?[$\x{20AC}\x{00A3}\x{00A5}]?[ \x{00A0}]?\d[\d,. \x{00A0}]*(?:[eE][+-]?\d+)?[ \x{00A0}]?[%$\x{20AC}\x{00A3}\x{00A5}]?$/u',
            $trimmed
        ) === 1;
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
