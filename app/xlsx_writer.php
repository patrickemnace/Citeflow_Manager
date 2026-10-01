<?php

declare(strict_types=1);

final class MiniZipWriter
{
    /** @var array<int, array{path:string, content:string}> */
    private array $files = [];

    public function addFile(string $path, string $content): void
    {
        $this->files[] = ['path' => $path, 'content' => $content];
    }

    public function output(): string
    {
        [$dosTime, $dosDate] = $this->dosDateTime();

        $localParts = '';
        $central = '';
        $offset = 0;

        foreach ($this->files as $file) {
            $path = $file['path'];
            $content = $file['content'];
            $crc = crc32($content);
            $uncompressedSize = strlen($content);
            $compressed = (string)gzdeflate($content, 6);
            $compressedSize = strlen($compressed);
            $nameLen = strlen($path);

            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0x0000,
                8,
                $dosTime,
                $dosDate,
                $crc,
                $compressedSize,
                $uncompressedSize,
                $nameLen,
                0
            ) . $path;

            $localParts .= $localHeader . $compressed;

            $centralHeader = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0x0000,
                8,
                $dosTime,
                $dosDate,
                $crc,
                $compressedSize,
                $uncompressedSize,
                $nameLen,
                0,
                0,
                0,
                0,
                0,
                $offset
            ) . $path;

            $central .= $centralHeader;
            $offset += strlen($localHeader) + $compressedSize;
        }

        $centralOffset = $offset;
        $centralSize = strlen($central);
        $count = count($this->files);

        $eocd = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $count,
            $count,
            $centralSize,
            $centralOffset,
            0
        );

        return $localParts . $central . $eocd;
    }

    /** @return array{0:int,1:int} */
    private function dosDateTime(): array
    {
        $d = getdate();
        $dosTime = ($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2);
        $dosDate = (($d['year'] - 1980) << 9) | ($d['mon'] << 5) | $d['mday'];
        return [$dosTime, $dosDate];
    }
}

final class XlsxWriter
{
    /** @var array<int,string> */
    private array $fonts = [];
    /** @var array<int,string> */
    private array $fills = [];
    /** @var array<int,string> */
    private array $borders = [];
    /** @var array<int,string> */
    private array $cellXfs = [];
    /** @var array<int,array<string,mixed>> */
    private array $sheets = [];
    /** @var array<int,array{content:string,ext:string}> */
    private array $images = [];

    public function __construct()
    {
        $this->fonts[] = $this->fontXml([]);
        $this->fills[] = '<fill><patternFill patternType="none"/></fill>';
        $this->fills[] = '<fill><patternFill patternType="gray125"/></fill>';
        $this->borders[] = '<border><left/><right/><top/><bottom/><diagonal/></border>';
        $this->cellXfs[] = '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>';
    }

    /** @param array{bold?:bool,italic?:bool,underline?:bool,size?:int,color?:string} $opts */
    public function addFont(array $opts): int
    {
        $this->fonts[] = $this->fontXml($opts);
        return count($this->fonts) - 1;
    }

    /** @param array{bold?:bool,italic?:bool,underline?:bool,size?:int,color?:string} $opts */
    private function fontXml(array $opts): string
    {
        $bold = !empty($opts['bold']) ? '<b/>' : '';
        $italic = !empty($opts['italic']) ? '<i/>' : '';
        $underline = !empty($opts['underline']) ? '<u/>' : '';
        $size = (int)($opts['size'] ?? 11);
        $color = (string)($opts['color'] ?? 'FF000000');

        return '<font>' . $bold . $italic . $underline . '<sz val="' . $size . '"/><color rgb="' . $color . '"/><name val="Calibri"/><family val="2"/></font>';
    }

    public function addFill(string $rgb): int
    {
        $this->fills[] = '<fill><patternFill patternType="solid"><fgColor rgb="' . $rgb . '"/><bgColor indexed="64"/></patternFill></fill>';
        return count($this->fills) - 1;
    }

    public function addBorder(): int
    {
        $side = '<color rgb="FFD1D5DB"/>';
        $this->borders[] = '<border><left style="thin">' . $side . '</left><right style="thin">' . $side . '</right><top style="thin">' . $side . '</top><bottom style="thin">' . $side . '</bottom><diagonal/></border>';
        return count($this->borders) - 1;
    }

    public function addXf(int $fontId, int $fillId, int $borderId, string $halign = 'left', bool $wrap = false): int
    {
        $align = '<alignment horizontal="' . $halign . '" vertical="center"' . ($wrap ? ' wrapText="1"' : '') . '/>';
        $this->cellXfs[] = '<xf numFmtId="0" fontId="' . $fontId . '" fillId="' . $fillId . '" borderId="' . $borderId . '" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $align . '</xf>';
        return count($this->cellXfs) - 1;
    }

    /** @param array<int,int|float> $colWidths */
    public function newSheet(string $name, array $colWidths): int
    {
        $this->sheets[] = [
            'name' => $name,
            'rows' => [],
            'colWidths' => $colWidths,
            'nextRow' => 1,
            'freeze' => false,
            'autofilter' => false,
            'maxCol' => count($colWidths),
            'picture' => null,
        ];

        return count($this->sheets) - 1;
    }

    public function addImage(string $content, string $ext): int
    {
        $this->images[] = ['content' => $content, 'ext' => strtolower($ext)];
        return count($this->images) - 1;
    }

    public function setPicture(int $sheetIdx, int $imageId, int $col, int $row, int $widthPx, int $heightPx): void
    {
        $this->sheets[$sheetIdx]['picture'] = [
            'imageId' => $imageId,
            'col' => $col,
            'row' => $row,
            'widthPx' => $widthPx,
            'heightPx' => $heightPx,
        ];
    }

    /** @param array<int,array{value:string,style?:int,type?:string,hyperlink?:string}> $cells */
    public function addRow(int $sheetIdx, array $cells, ?float $height = null): void
    {
        $rowNum = (int)$this->sheets[$sheetIdx]['nextRow'];
        $this->sheets[$sheetIdx]['rows'][$rowNum] = ['cells' => $cells, 'height' => $height];
        $this->sheets[$sheetIdx]['nextRow'] = $rowNum + 1;
    }

    public function freezeHeader(int $sheetIdx): void
    {
        $this->sheets[$sheetIdx]['freeze'] = true;
    }

    public function enableAutoFilter(int $sheetIdx): void
    {
        $this->sheets[$sheetIdx]['autofilter'] = true;
    }

    private static function colLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $rem = ($index - 1) % 26;
            $letter = chr(65 + $rem) . $letter;
            $index = intdiv($index - 1, 26);
        }

        return $letter;
    }

    private static function xmlEscape(string $s): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
        $clean = $clean === null ? $s : $clean;
        return htmlspecialchars($clean, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function buildStylesXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<fonts count="' . count($this->fonts) . '">' . implode('', $this->fonts) . '</fonts>';
        $xml .= '<fills count="' . count($this->fills) . '">' . implode('', $this->fills) . '</fills>';
        $xml .= '<borders count="' . count($this->borders) . '">' . implode('', $this->borders) . '</borders>';
        $xml .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
        $xml .= '<cellXfs count="' . count($this->cellXfs) . '">' . implode('', $this->cellXfs) . '</cellXfs>';
        $xml .= '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>';
        $xml .= '</styleSheet>';

        return $xml;
    }

    /** @return array{0:string,1:?string} */
    private function buildSheetXml(int $sheetIdx, ?int $drawingNum): array
    {
        $sheet = $this->sheets[$sheetIdx];
        $rows = $sheet['rows'];
        $maxCol = (int)$sheet['maxCol'];
        $maxRow = max(1, (int)$sheet['nextRow'] - 1);
        $lastColLetter = self::colLetter(max(0, $maxCol - 1));

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<dimension ref="A1:' . $lastColLetter . $maxRow . '"/>';

        if ($sheet['freeze']) {
            $xml .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft"/></sheetView></sheetViews>';
        } else {
            $xml .= '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
        }

        $xml .= '<cols>';
        foreach ($sheet['colWidths'] as $i => $w) {
            $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        $xml .= '</cols>';

        $xml .= '<sheetData>';
        $hyperlinks = [];
        $relId = 1;

        foreach ($rows as $rowNum => $rowDef) {
            $heightAttr = $rowDef['height'] ? ' ht="' . $rowDef['height'] . '" customHeight="1"' : '';
            $xml .= '<row r="' . $rowNum . '"' . $heightAttr . '>';

            foreach ($rowDef['cells'] as $colIdx => $cell) {
                $ref = self::colLetter($colIdx) . $rowNum;
                $style = (int)($cell['style'] ?? 0);
                $type = (string)($cell['type'] ?? 'string');

                if ($type === 'number') {
                    $num = (string)$cell['value'];
                    $xml .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $num . '</v></c>';
                } else {
                    $value = self::xmlEscape((string)($cell['value'] ?? ''));
                    $xml .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . $value . '</t></is></c>';
                }

                if (!empty($cell['hyperlink'])) {
                    $hyperlinks[] = ['ref' => $ref, 'url' => (string)$cell['hyperlink'], 'rid' => 'rId' . $relId];
                    $relId++;
                }
            }

            $xml .= '</row>';
        }
        $xml .= '</sheetData>';

        if ($sheet['autofilter']) {
            $xml .= '<autoFilter ref="A1:' . $lastColLetter . $maxRow . '"/>';
        }

        if (!empty($hyperlinks)) {
            $xml .= '<hyperlinks>';
            foreach ($hyperlinks as $h) {
                $xml .= '<hyperlink ref="' . $h['ref'] . '" r:id="' . $h['rid'] . '"/>';
            }
            $xml .= '</hyperlinks>';
        }

        $drawingRid = null;
        if ($drawingNum !== null) {
            $drawingRid = 'rId' . $relId;
            $relId++;
            $xml .= '<drawing r:id="' . $drawingRid . '"/>';
        }

        $xml .= '</worksheet>';

        $relsXml = null;
        if (!empty($hyperlinks) || $drawingRid !== null) {
            $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
            $relsXml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
            foreach ($hyperlinks as $h) {
                $relsXml .= '<Relationship Id="' . $h['rid'] . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="' . self::xmlEscape($h['url']) . '" TargetMode="External"/>';
            }
            if ($drawingRid !== null) {
                $relsXml .= '<Relationship Id="' . $drawingRid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing' . $drawingNum . '.xml"/>';
            }
            $relsXml .= '</Relationships>';
        }

        return [$xml, $relsXml];
    }

    private function buildDrawingXml(int $imageRelIndex, int $col, int $row, int $widthPx, int $heightPx): string
    {
        $cx = $widthPx * 9525;
        $cy = $heightPx * 9525;

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">';
        $xml .= '<xdr:oneCellAnchor>';
        $xml .= '<xdr:from><xdr:col>' . $col . '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' . $row . '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>';
        $xml .= '<xdr:ext cx="' . $cx . '" cy="' . $cy . '"/>';
        $xml .= '<xdr:pic>';
        $xml .= '<xdr:nvPicPr><xdr:cNvPr id="1" name="Logo"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>';
        $xml .= '<xdr:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rId' . $imageRelIndex . '"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>';
        $xml .= '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>';
        $xml .= '</xdr:pic>';
        $xml .= '<xdr:clientData/>';
        $xml .= '</xdr:oneCellAnchor>';
        $xml .= '</xdr:wsDr>';

        return $xml;
    }

    public function output(): string
    {
        $zip = new MiniZipWriter();

        $sheetDrawingNum = [];
        $drawingCounter = 0;
        foreach ($this->sheets as $idx => $sheet) {
            if (!empty($sheet['picture'])) {
                $drawingCounter++;
                $sheetDrawingNum[$idx] = $drawingCounter;
            }
        }

        $imageExts = [];
        foreach ($this->images as $image) {
            $imageExts[$image['ext']] = true;
        }

        $zip->addFile('[Content_Types].xml', $this->buildContentTypesXml($sheetDrawingNum, array_keys($imageExts)));
        $zip->addFile('_rels/.rels', $this->buildRootRelsXml());
        $zip->addFile('xl/workbook.xml', $this->buildWorkbookXml());
        $zip->addFile('xl/_rels/workbook.xml.rels', $this->buildWorkbookRelsXml());
        $zip->addFile('xl/styles.xml', $this->buildStylesXml());

        foreach ($this->sheets as $idx => $sheet) {
            $drawingNum = $sheetDrawingNum[$idx] ?? null;
            [$sheetXml, $relsXml] = $this->buildSheetXml($idx, $drawingNum);
            $sheetNum = $idx + 1;
            $zip->addFile('xl/worksheets/sheet' . $sheetNum . '.xml', $sheetXml);
            if ($relsXml !== null) {
                $zip->addFile('xl/worksheets/_rels/sheet' . $sheetNum . '.xml.rels', $relsXml);
            }

            if ($drawingNum !== null) {
                $picture = $sheet['picture'];
                $image = $this->images[$picture['imageId']];
                $mediaName = 'image' . $drawingNum . '.' . $image['ext'];

                $zip->addFile('xl/media/' . $mediaName, $image['content']);
                $zip->addFile('xl/drawings/drawing' . $drawingNum . '.xml', $this->buildDrawingXml(1, $picture['col'], $picture['row'], $picture['widthPx'], $picture['heightPx']));

                $drawingRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
                $drawingRels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
                $drawingRels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/' . $mediaName . '"/>';
                $drawingRels .= '</Relationships>';
                $zip->addFile('xl/drawings/_rels/drawing' . $drawingNum . '.xml.rels', $drawingRels);
            }
        }

        return $zip->output();
    }

    /**
     * @param array<int,int> $sheetDrawingNum
     * @param array<int,string> $imageExts
     */
    private function buildContentTypesXml(array $sheetDrawingNum, array $imageExts): string
    {
        $extToMime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif'];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $xml .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $xml .= '<Default Extension="xml" ContentType="application/xml"/>';
        foreach ($imageExts as $ext) {
            $mime = $extToMime[$ext] ?? 'application/octet-stream';
            $xml .= '<Default Extension="' . $ext . '" ContentType="' . $mime . '"/>';
        }
        $xml .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        foreach ($this->sheets as $idx => $sheet) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . ($idx + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        foreach ($sheetDrawingNum as $drawingNum) {
            $xml .= '<Override PartName="/xl/drawings/drawing' . $drawingNum . '.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
        }
        $xml .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $xml .= '</Types>';

        return $xml;
    }

    private function buildRootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function buildWorkbookXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheets>';
        foreach ($this->sheets as $idx => $sheet) {
            $sheetNum = $idx + 1;
            $xml .= '<sheet name="' . self::xmlEscape((string)$sheet['name']) . '" sheetId="' . $sheetNum . '" r:id="rId' . $sheetNum . '"/>';
        }
        $xml .= '</sheets>';
        $xml .= '</workbook>';

        return $xml;
    }

    private function buildWorkbookRelsXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $relId = 1;
        foreach ($this->sheets as $idx => $sheet) {
            $sheetNum = $idx + 1;
            $xml .= '<Relationship Id="rId' . $relId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetNum . '.xml"/>';
            $relId++;
        }
        $xml .= '<Relationship Id="rId' . $relId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $xml .= '</Relationships>';

        return $xml;
    }
}
