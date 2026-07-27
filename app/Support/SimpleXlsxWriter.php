<?php

namespace App\Support;

/**
 * Minimal uncompressed XLSX writer (no ext-zip / PhpSpreadsheet required).
 */
class SimpleXlsxWriter
{
    /**
     * @param  list<array{name: string, rows: list<list<scalar|null>>}>  $sheets
     */
    public function build(array $sheets): string
    {
        if ($sheets === []) {
            $sheets = [['name' => 'Sheet1', 'rows' => [['No data']]]];
        }

        $files = [];
        $files['[Content_Types].xml'] = $this->contentTypes(count($sheets));
        $files['_rels/.rels'] = $this->rootRels();
        $files['xl/workbook.xml'] = $this->workbook($sheets);
        $files['xl/_rels/workbook.xml.rels'] = $this->workbookRels(count($sheets));
        $files['xl/styles.xml'] = $this->styles();

        foreach ($sheets as $index => $sheet) {
            $sheetId = $index + 1;
            $files["xl/worksheets/sheet{$sheetId}.xml"] = $this->worksheet($sheet['rows'] ?? []);
        }

        return $this->zipStore($files);
    }

    /**
     * @param  list<list<scalar|null>>  $rows
     */
    private function worksheet(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetData>';

        foreach ($rows as $rIndex => $row) {
            $rowNum = $rIndex + 1;
            $xml .= '<row r="'.$rowNum.'">';
            foreach (array_values($row) as $cIndex => $value) {
                $cell = $this->columnName($cIndex).$rowNum;
                $xml .= $this->cellXml($cell, $value, $rIndex === 0);
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    private function cellXml(string $ref, mixed $value, bool $isHeader): string
    {
        if ($value === null || $value === '') {
            return '<c r="'.$ref.'"'.($isHeader ? ' s="1"' : '').'/>';
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value) && ! preg_match('/^0\d+/', $value))) {
            return '<c r="'.$ref.'"'.($isHeader ? ' s="1"' : '').'><v>'.$this->xml(sprintf('%s', $value)).'</v></c>';
        }

        $text = $this->xml((string) $value);

        return '<c r="'.$ref.'" t="inlineStr"'.($isHeader ? ' s="1"' : '').'><is><t>'.$text.'</t></is></c>';
    }

    private function columnName(int $index): string
    {
        $name = '';
        $n = $index;
        do {
            $name = chr(65 + ($n % 26)).$name;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return $name;
    }

    /**
     * @param  list<array{name: string, rows: list<list<scalar|null>>}>  $sheets
     */
    private function workbook(array $sheets): string
    {
        $sheetsXml = '';
        foreach ($sheets as $index => $sheet) {
            $id = $index + 1;
            $name = $this->xml($this->safeSheetName((string) ($sheet['name'] ?? "Sheet{$id}")));
            $sheetsXml .= '<sheet name="'.$name.'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheetsXml.'</sheets></workbook>';
    }

    private function workbookRels(int $count): string
    {
        $rels = '';
        for ($i = 1; $i <= $count; $i++) {
            $rels .= '<Relationship Id="rId'.$i.'"'
                .' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                .' Target="worksheets/sheet'.$i.'.xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels
            .'<Relationship Id="rIdStyles"'
            .' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
            .' Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function contentTypes(int $sheetCount): string
    {
        $overrides = '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';

        for ($i = 1; $i <= $sheetCount; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .$overrides
            .'</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1"'
            .' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"'
            .' Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            .'<cellXfs count="2">'
            .'<xf fontId="0" fillId="0" borderId="0"/>'
            .'<xf fontId="1" fillId="0" borderId="0" applyFont="1"/>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    private function safeSheetName(string $name): string
    {
        $name = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $name) ?: 'Sheet';

        return mb_substr($name, 0, 31);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * @param  array<string, string>  $files
     */
    private function zipStore(array $files): string
    {
        $offset = 0;
        $local = '';
        $central = '';
        $count = 0;

        foreach ($files as $name => $content) {
            $name = str_replace('\\', '/', $name);
            $nameBytes = $name;
            $data = $content;
            $size = strlen($data);
            $crc = crc32($data);
            // PHP crc32 returns signed on some platforms; force unsigned 32-bit
            $crc = unpack('N', pack('N', $crc))[1];

            $localHeader = pack('VvvvvvVVVvv',
                0x04034b50, // local file header signature
                20,         // version needed
                0,          // flags
                0,          // compression: store
                0,          // mod time
                0,          // mod date
                $crc,
                $size,
                $size,
                strlen($nameBytes),
                0           // extra length
            ).$nameBytes;

            $central .= pack('VvvvvvvVVVvvvvvVV',
                0x02014b50, // central file header signature
                20,         // version made by
                20,         // version needed
                0,          // flags
                0,          // compression
                0,          // mod time
                0,          // mod date
                $crc,
                $size,
                $size,
                strlen($nameBytes),
                0,          // extra
                0,          // comment
                0,          // disk start
                0,          // int attr
                0,          // ext attr
                $offset
            ).$nameBytes;

            $local .= $localHeader.$data;
            $offset += strlen($localHeader) + $size;
            $count++;
        }

        $end = pack('VvvvvVVv',
            0x06054b50,
            0,
            0,
            $count,
            $count,
            strlen($central),
            strlen($local),
            0
        );

        return $local.$central.$end;
    }
}
