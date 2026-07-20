<?php

namespace App\Integrations\GoogleSheets;

use App\DTOs\SheetRow;
use Illuminate\Support\Collection;
use Revolution\Google\Sheets\Facades\Sheets;

class GoogleSheetsClient
{
    /**
     * Fetch all data rows from the configured spreadsheet as DTOs.
     * Skips the first 4 rows (filter row, headers, 2 blank rows).
     *
     * @return Collection<int, SheetRow>
     */
    public function fetchRows(): Collection
    {
        $spreadsheetId = config('google.sheets.spreadsheet_id');
        $spreadsheetId = is_string($spreadsheetId) ? $spreadsheetId : '';

        $sheetName = config('google.sheets.sheet_name', 'Hoja 1');
        $sheetName = is_string($sheetName) ? $sheetName : 'Hoja 1';

        $rows = Sheets::spreadsheet($spreadsheetId)
            ->sheet($sheetName)
            ->get();

        /** @var Collection<int, array<int, mixed>> $rows */
        return $rows->slice(4)->values()
            ->map(fn (array $row): SheetRow => SheetRow::fromArray($row));
    }
}
