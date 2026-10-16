<?php

namespace App\Services\Products;

use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Format file import/export produk (2.10). Kolom export = kolom template, sehingga
 * file hasil export bisa diedit lalu di-import kembali.
 */
class ProductSpreadsheet
{
    /** Kolom (header baris 1). Kolom yang tidak dikenal diabaikan. */
    public const COLUMNS = ['nama', 'kategori', 'sku', 'barcode', 'harga_jual', 'harga_modal', 'satuan', 'stok_minimum', 'lacak_stok', 'stok_awal'];

    public const INSTRUCTIONS = [
        ['Kolom', 'Keterangan'],
        ['nama', 'Wajib. Nama produk, maks. 150 karakter.'],
        ['kategori', 'Nama kategori. Dibuat otomatis bila belum ada. Kosongkan bila tanpa kategori.'],
        ['sku', 'Kosongkan untuk SKU otomatis. Bila SKU sudah ada, produk tersebut DIPERBARUI (bukan dibuat baru).'],
        ['barcode', 'Opsional. Tidak boleh sama dengan produk lain.'],
        ['harga_jual', 'Wajib. Angka rupiah tanpa titik/koma, mis. 15500.'],
        ['harga_modal', 'Opsional. Angka rupiah, mis. 9000.'],
        ['satuan', 'Opsional, default pcs. Mis. pcs, botol, porsi.'],
        ['stok_minimum', 'Opsional. Peringatan stok menipis.'],
        ['lacak_stok', 'ya / tidak. Default ya.'],
        ['stok_awal', 'Hanya untuk produk baru, masuk ke outlet yang dipilih saat import. Diabaikan untuk produk yang sudah ada (pakai Penyesuaian Stok).'],
    ];

    /** @param list<list<mixed>> $rows */
    public function download(string $filename, array $rows, bool $withInstructions = true): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'products').'.xlsx';
        $bold = (new Style)->withFontBold(true);

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Produk');
        $writer->addRow(Row::fromValuesWithStyle(self::COLUMNS, $bold));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        if ($withInstructions) {
            $writer->addNewSheetAndMakeItCurrent()->setName('Petunjuk');
            foreach (self::INSTRUCTIONS as $i => $line) {
                $writer->addRow($i === 0 ? Row::fromValuesWithStyle($line, $bold) : Row::fromValues($line));
            }
        }
        $writer->close();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * Baca sheet pertama: baris 1 = header, baris berikutnya = data. Baris kosong dilewati.
     *
     * @return array<int, array<string, mixed>> nomor baris Excel → [kolom => nilai]
     */
    public function read(UploadedFile $file): array
    {
        $isCsv = in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'txt'], true);
        $reader = $isCsv
            ? new CsvReader(new CsvOptions(SHOULD_PRESERVE_EMPTY_ROWS: true, FIELD_DELIMITER: $this->csvDelimiter($file->getRealPath())))
            : new XlsxReader(new XlsxOptions(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $reader->open($file->getRealPath());

        $rows = [];
        $header = null;
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $i => $row) {
                $values = $row->toArray();
                if ($header === null) {
                    $header = array_map(fn ($h) => strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', (string) $h), '_')), $values);

                    continue;
                }
                if (! array_filter($values, fn ($v) => trim((string) $v) !== '')) {
                    continue;
                }
                $assoc = [];
                foreach ($header as $col => $name) {
                    if (in_array($name, self::COLUMNS, true)) {
                        $assoc[$name] = $values[$col] ?? null;
                    }
                }
                $rows[$i] = $assoc;
            }
            break; // hanya sheet pertama
        }
        $reader->close();

        return $rows;
    }

    /** CSV dari Excel berbahasa Indonesia biasanya memakai titik koma. */
    private function csvDelimiter(string $path): string
    {
        $first = (string) fgets(fopen($path, 'r'));

        return substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    }
}
