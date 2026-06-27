<?php

namespace App\Services\Convention;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Étape 5 — convertit un .docx en PDF via LibreOffice headless.
 *
 *   soffice --headless --convert-to pdf --outdir <dir> <fichier.docx>
 *
 * Best-effort : si LibreOffice est absent (ex. poste local sans installation),
 * on log et on retourne null — la génération du .docx n'est jamais bloquée.
 */
class PdfConverter
{
    public function isEnabled(): bool
    {
        return (bool) config('conventions.pdf.enabled', true);
    }

    /**
     * Convertit $docxAbsolute en PDF dans le même dossier. Retourne le chemin
     * absolu du PDF, ou null en cas d'échec.
     */
    public function toPdf(string $docxAbsolute): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }
        if (!is_file($docxAbsolute)) {
            Log::warning('pdf.convert_missing_source', ['path' => $docxAbsolute]);
            return null;
        }

        $binary  = (string) config('conventions.pdf.binary', 'soffice');
        $outDir  = dirname($docxAbsolute);
        // Profil utilisateur isolé (évite les conflits si LibreOffice tourne déjà,
        // et les soucis de HOME sur un VPS en service). URI de fichier valide :
        //   Linux  : file:///var/www/...      Windows : file:///C:/...
        $profile = storage_path('app/tmp/lo-profile');
        @mkdir($profile, 0775, true);
        $profileUri = 'file:///' . ltrim(str_replace('\\', '/', $profile), '/');

        try {
            $result = Process::timeout(120)->run([
                $binary,
                '--headless',
                '--norestore',
                '--convert-to', 'pdf',
                '--outdir', $outDir,
                '-env:UserInstallation=' . $profileUri,
                $docxAbsolute,
            ]);
        } catch (\Throwable $e) {
            Log::warning('pdf.convert_exception', ['message' => $e->getMessage()]);
            return null;
        }

        $pdfPath = preg_replace('/\.docx$/i', '.pdf', $docxAbsolute);

        if (!$result->successful() || !is_file($pdfPath)) {
            Log::warning('pdf.convert_failed', [
                'exit'   => $result->exitCode(),
                'output' => substr($result->errorOutput() . $result->output(), 0, 500),
                'binary' => $binary,
            ]);
            return is_file($pdfPath) ? $pdfPath : null;
        }

        return $pdfPath;
    }
}
