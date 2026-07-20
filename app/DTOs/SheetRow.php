<?php

namespace App\DTOs;

readonly class SheetRow
{
    public function __construct(
        public string $folio,
        public string $device,
        public string $clientName,
        public string $clientPhone,
        public ?string $receivedAt,
        public string $description,
        public string $deviceSerial,
        public string $devicePassword,
        public string $observations,
        public string $hash,
    ) {}

    /**
     * @param  array<int, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $folio = self::normalizeFolio(trim(self::cellToString($row[0] ?? '')));

        return new self(
            folio: $folio,
            device: trim(self::cellToString($row[1] ?? '')),
            clientName: trim(self::cellToString($row[2] ?? '')),
            clientPhone: trim(self::cellToString($row[3] ?? '')),
            receivedAt: self::parseDate(trim(self::cellToString($row[4] ?? ''))),
            description: trim(self::cellToString($row[5] ?? '')),
            deviceSerial: trim(self::cellToString($row[6] ?? '')),
            devicePassword: trim(self::cellToString($row[7] ?? '')),
            observations: trim(self::cellToString($row[8] ?? '')),
            hash: md5(implode('|', array_map(
                fn (mixed $cell): string => trim(self::cellToString($cell)),
                $row
            ))),
        );
    }

    /**
     * Google Sheets cell values arrive as scalars (string/int/float/bool) or null.
     */
    private static function cellToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    public function isEmpty(): bool
    {
        return $this->folio === '' && $this->device === '';
    }

    private static function normalizeFolio(string $folio): string
    {
        $stripped = str_replace('.', '', $folio);

        if ($stripped !== '' && ctype_digit($stripped)) {
            return $stripped;
        }

        return $folio;
    }

    private static function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $parts = explode('/', $value);
        if (count($parts) === 3) {
            $timestamp = mktime(0, 0, 0, (int) $parts[1], (int) $parts[0], (int) $parts[2]);

            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
    }
}
