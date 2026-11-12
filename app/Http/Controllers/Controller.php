<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class Controller
{
    /**
     * Registra un evento de auditoría en la tabla `registros` (Single Source of Truth).
     */
    protected function registrar(Request $request, string $accion, array $detalle): void
    {
        $request->user()?->registros()->create([
            'accion' => $accion,
            'detalle' => json_encode($detalle, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Sanitiza celdas de texto contra CSV Formula Injection (=, +, -, @, tab, CR).
     */
    public static function sanitizeCsvCell(mixed $value): string
    {
        $str = (string) ($value ?? '');
        if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$str;
        }

        return $str;
    }

    /**
     * Genera una descarga CSV UTF-8 compatible con Excel aplicando sanitización automática.
     */
    protected function streamCsv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                $sanitized = array_map(
                    fn ($cell) => is_int($cell) || is_float($cell) ? $cell : self::sanitizeCsvCell($cell),
                    $row,
                );
                fputcsv($handle, $sanitized);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
