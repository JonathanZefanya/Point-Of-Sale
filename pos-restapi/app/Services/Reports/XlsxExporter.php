<?php

namespace App\Services\Reports;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Ekspor laporan ke Excel (.xlsx). Angka uang ditulis sebagai angka (bukan teks) agar bisa dijumlah di Excel. */
class XlsxExporter
{
    /**
     * @param  list<string>  $titleLines  baris judul di atas tabel (nama laporan, outlet, periode)
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     */
    public function download(string $filename, array $titleLines, array $headers, array $rows): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'report').'.xlsx';
        $bold = (new Style)->withFontBold(true);

        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($titleLines as $i => $line) {
            $writer->addRow($i === 0 ? Row::fromValuesWithStyle([$line], $bold) : Row::fromValues([$line]));
        }
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValuesWithStyle($headers, $bold));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
