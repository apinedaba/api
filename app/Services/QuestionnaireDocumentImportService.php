<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Converts an uploaded clinical questionnaire into the structure understood by
 * the questionnaire builder. It is deliberately deterministic: no document
 * content is sent to an AI service or any third party.
 */
class QuestionnaireDocumentImportService
{
    public function import(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $text = match ($extension) {
            'pdf' => $this->extractPdfText($file),
            'docx' => $this->extractDocxText($file),
            'doc' => $this->extractLegacyWordText($file),
            default => throw new RuntimeException('El formato del archivo no es compatible.'),
        };

        $draft = $this->structureFromText($text, pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        return [
            'questionnaire' => $draft,
            'source' => [
                'filename' => $file->getClientOriginalName(),
                'format' => $extension,
                'processor' => in_array($extension, ['doc', 'docx'], true) ? 'lector de Word local' : 'lector local del servidor',
            ],
            'warnings' => [
                'Revisa el borrador antes de guardarlo: los documentos con tablas, columnas o marcas manuscritas pueden requerir ajustes.',
            ],
        ];
    }

    private function extractPdfText(UploadedFile $file): string
    {
        $process = new Process(['pdftotext', '-layout', $file->getRealPath(), '-']);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('No fue posible leer este PDF. Intenta con un PDF no protegido.');
        }

        $text = $this->cleanText($process->getOutput());
        if (mb_strlen($text) < 20) {
            return $this->extractScannedPdfText($file);
        }

        return $text;
    }

    private function extractScannedPdfText(UploadedFile $file): string
    {
        if (! $this->hasTesseract()) {
            throw new RuntimeException('Este PDF parece ser un escaneo. Instala Tesseract y los datos de idioma español para usar OCR local en el servidor.');
        }

        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mindmeet-questionnaire-'.Str::uuid();
        mkdir($directory, 0700, true);
        try {
            $prefix = $directory.DIRECTORY_SEPARATOR.'page';
            $render = new Process(['pdftoppm', '-png', '-r', '200', $file->getRealPath(), $prefix]);
            $render->setTimeout(90);
            $render->run();
            $pages = glob($prefix.'-*.png') ?: [];
            if (! $render->isSuccessful() || $pages === []) {
                throw new RuntimeException('No fue posible preparar el PDF escaneado para su lectura.');
            }

            return $this->cleanText(implode("\n", array_map(fn ($page) => $this->extractOcrFromPath($page), $pages)));
        } finally {
            foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $temporaryFile) {
                @unlink($temporaryFile);
            }
            @rmdir($directory);
        }
    }

    private function extractDocxText(UploadedFile $file): string
    {
        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            throw new RuntimeException('No fue posible abrir el archivo de Word.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) {
            throw new RuntimeException('El archivo de Word no contiene texto legible.');
        }

        $document = new \DOMDocument();
        $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $paragraphs = [];
        foreach ($xpath->query('//w:p') as $paragraph) {
            $line = '';
            foreach ($xpath->query('.//w:t|.//w:tab|.//w:br', $paragraph) as $node) {
                $line .= $node->localName === 't' ? $node->textContent : ' ';
            }
            if (trim($line) !== '') {
                $paragraphs[] = trim($line);
            }
        }

        return $this->cleanText(implode("\n", $paragraphs));
    }

    private function extractLegacyWordText(UploadedFile $file): string
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mindmeet-questionnaire-'.Str::uuid();
        mkdir($directory, 0700, true);
        try {
            $process = new Process(['libreoffice', '--headless', '--convert-to', 'txt:Text', '--outdir', $directory, $file->getRealPath()]);
            $process->setTimeout(45);
            $process->run();
            $output = glob($directory.DIRECTORY_SEPARATOR.'*.txt') ?: [];
            if (! $process->isSuccessful() || $output === []) {
                throw new RuntimeException('No fue posible leer este archivo de Word. Intenta guardarlo como .docx.');
            }

            return $this->cleanText((string) file_get_contents($output[0]));
        } finally {
            foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $temporaryFile) {
                @unlink($temporaryFile);
            }
            @rmdir($directory);
        }
    }

    private function extractOcrFromPath(string $path): string
    {
        $process = new Process(['tesseract', $path, 'stdout', '-l', 'spa+eng']);
        $process->setTimeout(45);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('No fue posible leer el texto de la imagen. Usa una imagen nítida, de frente y con buena iluminación.');
        }

        return $this->cleanText($process->getOutput());
    }

    private function hasTesseract(): bool
    {
        $process = new Process(['tesseract', '--version']);
        $process->setTimeout(5);
        $process->run();

        return $process->isSuccessful();
    }

    private function structureFromText(string $text, string $fallbackTitle): array
    {
        $lines = array_values(array_filter(array_map(
            fn (string $line) => trim(preg_replace('/\s+/u', ' ', $line)),
            preg_split('/\R/u', $text) ?: []
        )));

        if (count($lines) < 2) {
            throw new RuntimeException('No encontramos suficientes preguntas en el documento.');
        }

        $title = $this->stripNumbering($lines[0]) ?: $fallbackTitle ?: 'Cuestionario importado';
        $structure = [];
        $currentQuestion = null;
        $context = [];
        $collectingContext = null;
        $hasQuestion = false;
        $choiceOptions = [];
        $pendingQuestionContinuation = false;

        foreach (array_slice($lines, 1) as $line) {
            $value = $this->stripNumbering($line);
            if ($value === '') {
                continue;
            }

            if ($this->isTableHeader($line)) {
                $choiceOptions = $this->optionsFromTableHeader($line);
                continue;
            }

            if ($this->isAdministrativeField($line) || $this->isResponseBlank($line)) {
                continue;
            }

            if ($this->isContextLabel($line)) {
                $collectingContext = $this->contextLabel($line);
                $context[$collectingContext] = $this->contextValue($line);
                continue;
            }

            if ($this->isScoringHeading($line)) {
                $collectingContext = 'Puntuación orientativa';
                $context[$collectingContext] = $value;
                continue;
            }

            if ($collectingContext !== null && $this->isContextContinuation($line)) {
                $context[$collectingContext] .= ' '.$value;
                continue;
            }

            if ($this->isScaleLegend($line)) {
                if (isset($context['Instrucciones'])) {
                    $context['Instrucciones'] .= ' '.$line;
                }
                continue;
            }

            if ($this->isNumericScale($line) && $currentQuestion !== null) {
                $currentQuestion['type'] = 'linear_scale';
                $currentQuestion['scale'] = $this->scaleFromLine($line, $context['Instrucciones'] ?? '');
                unset($currentQuestion['options']);
                $structure[array_key_last($structure)] = $currentQuestion;
                $collectingContext = null;
                continue;
            }

            if ($this->isQuestionNumberMarker($line) && $currentQuestion !== null) {
                $currentQuestion = $this->asChoiceQuestion($currentQuestion, $choiceOptions);
                $structure[array_key_last($structure)] = $currentQuestion;
                $pendingQuestionContinuation = true;
                $collectingContext = null;
                continue;
            }

            if ($pendingQuestionContinuation && $currentQuestion !== null && $this->isQuestionContinuation($line)) {
                $currentQuestion['title'] = trim($currentQuestion['title'].' '.$this->removeChoiceMarkers($value));
                $structure[array_key_last($structure)] = $currentQuestion;
                $pendingQuestionContinuation = false;
                continue;
            }

            if (! $hasQuestion && $this->isHeaderDescription($line)) {
                $context['Descripción'] = trim(($context['Descripción'] ?? '').' '.$value);
                $collectingContext = 'Descripción';
                continue;
            }

            $collectingContext = null;
            $isOption = $this->isOption($line);
            if ($isOption && $currentQuestion !== null) {
                $currentQuestion['options'][] = $this->option($value);
                $currentQuestion['type'] = 'multiple_choice';
                $structure[array_key_last($structure)] = $currentQuestion;
                continue;
            }

            $containsChoiceMarkers = $this->containsChoiceMarkers($line);
            $value = $this->removeChoiceMarkers($value);

            if ($this->isSection($line, $value)) {
                $structure[] = $this->field('section', rtrim($value, ':'));
                $currentQuestion = null;
                continue;
            }

            $type = $this->detectType($value);
            $currentQuestion = $this->field($type, $value);
            if ($containsChoiceMarkers) {
                $currentQuestion = $this->asChoiceQuestion($currentQuestion, $choiceOptions);
            }
            $structure[] = $currentQuestion;
            $hasQuestion = true;
        }

        if (! collect($structure)->contains(fn (array $item) => $item['type'] !== 'section')) {
            throw new RuntimeException('No encontramos preguntas reconocibles en el documento.');
        }

        return [
            'id' => $this->id('form'),
            'title' => Str::limit($title, 255, ''),
            'description' => $this->descriptionFromContext($context),
            'structure' => $structure,
        ];
    }

    private function detectType(string $value): string
    {
        if (preg_match('/\b(?:0\s*(?:a|al|[-–])\s*10|1\s*(?:a|al|[-–])\s*[5-9])\b/ui', $value)) {
            return 'linear_scale';
        }

        return preg_match('/\?|\b(?:describa|explique|cuént|mencione|observaciones)\b/ui', $value)
            ? 'long_text'
            : 'short_text';
    }

    private function isSection(string $original, string $value): bool
    {
        return str_ends_with($value, ':')
            || str_starts_with($original, '#')
            || ($value === mb_strtoupper($value, 'UTF-8') && mb_strlen($value) > 3 && mb_strlen($value) < 90);
    }

    private function stripNumbering(string $value): string
    {
        return trim(preg_replace('/^(?:#+\s*|(?:\d+|[a-zA-Z]|[IVX]+)[.)]\s*|\d+\s+(?=¿)|[-*•◦□☐]\s*)/u', '', $value));
    }

    private function field(string $type, string $title): array
    {
        $field = array_filter([
            'id' => $this->id($type === 'section' ? 'section' : 'q'),
            'type' => $type,
            'title' => $title,
            'description' => '',
            'required' => false,
            'options' => in_array($type, ['multiple_choice', 'checkboxes', 'dropdown'], true) ? [] : null,
        ], fn ($value) => $value !== null);

        if ($type === 'linear_scale') {
            $field['scale'] = ['min' => 1, 'max' => 5, 'leftLabel' => 'Bajo', 'rightLabel' => 'Alto'];
        }

        return $field;
    }

    private function isAdministrativeField(string $line): bool
    {
        return preg_match('/^(?:nombre|edad|fecha|tel[eé]fono|correo(?: electr[oó]nico)?|email|direcci[oó]n)\s*:/ui', $line) === 1;
    }

    private function isResponseBlank(string $line): bool
    {
        return preg_match('/^[_\.\-\s]{8,}$/u', $line) === 1;
    }

    private function isContextLabel(string $line): bool
    {
        return preg_match('/^(?:objetivo|prop[oó]sito|instrucciones|aviso importante|nota|advertencia)\s*:??\s*$/ui', $line) === 1
            || preg_match('/^(?:objetivo|prop[oó]sito|instrucciones|aviso importante|nota|advertencia)\s*:/ui', $line) === 1;
    }

    private function contextLabel(string $line): string
    {
        preg_match('/^([^:]+):/u', $line, $matches);
        $label = trim($matches[1] ?? rtrim($line, ':'));

        return Str::ucfirst(Str::lower($label ?: 'Nota'));
    }

    private function contextValue(string $line): string
    {
        return str_contains($line, ':')
            ? trim((string) preg_replace('/^[^:]+:\s*/u', '', $line))
            : '';
    }

    private function isContextContinuation(string $line): bool
    {
        return ! $this->isQuestionStart($line)
            && ! $this->isOption($line)
            && ! $this->isNumericScale($line)
            && ! $this->isAdministrativeField($line)
            && ! $this->isResponseBlank($line)
            && ! $this->isSection($line, $this->stripNumbering($line));
    }

    private function isTableHeader(string $line): bool
    {
        return preg_match('/\bpregunta\b.*\bs[ií]\b.*\bno\b/ui', $line) === 1;
    }

    private function optionsFromTableHeader(string $line): array
    {
        return preg_match('/\bs[ií]\b.*\bno\b/ui', $line) === 1
            ? ['Sí', 'No']
            : [];
    }

    private function isQuestionNumberMarker(string $line): bool
    {
        return preg_match('/^\d+\s+(?:[■□▪]\s*)+$/u', trim($line)) === 1;
    }

    private function containsChoiceMarkers(string $line): bool
    {
        return preg_match('/[■□▪]/u', $line) === 1;
    }

    private function removeChoiceMarkers(string $value): string
    {
        return trim((string) preg_replace('/[■□▪]+/u', '', $value));
    }

    private function asChoiceQuestion(array $question, array $labels): array
    {
        $question['type'] = 'multiple_choice';
        $question['options'] = array_map(fn (string $label) => $this->option($label), $labels ?: ['Sí', 'No']);

        return $question;
    }

    private function isQuestionContinuation(string $line): bool
    {
        return ! $this->isQuestionStart($line)
            && ! $this->isContextLabel($line)
            && ! $this->isSection($line, $this->stripNumbering($line))
            && ! $this->isScoringHeading($line)
            && ! $this->isTableHeader($line);
    }

    private function isHeaderDescription(string $line): bool
    {
        return ! $this->isQuestionStart($line)
            && ! $this->isSection($line, $this->stripNumbering($line))
            && ! $this->isAdministrativeField($line);
    }

    private function isScoringHeading(string $line): bool
    {
        return preg_match('/^puntuaci[oó]n orientativa$/ui', trim($line)) === 1;
    }

    private function isQuestionLine(string $line): bool
    {
        return preg_match('/^\d+[.)]\s+.+/u', $line) === 1;
    }

    private function isQuestionStart(string $line): bool
    {
        return $this->isQuestionLine($line) || str_starts_with(trim($line), '¿');
    }

    private function isOption(string $line): bool
    {
        return preg_match('/^(?:[-*•◦□☐]|\(?[a-zA-Z]\)|\(?\d+\))\s+/u', $line) === 1;
    }

    private function isScaleLegend(string $line): bool
    {
        return preg_match('/^\d+\s*=\s*.+/u', $line) === 1;
    }

    private function isNumericScale(string $line): bool
    {
        return preg_match('/^\d+(?:\s+\d+){2,}$/u', trim($line)) === 1;
    }

    private function scaleFromLine(string $line, string $instructions): array
    {
        $values = array_map('intval', preg_split('/\s+/', trim($line)));
        $min = min($values);
        $max = max($values);
        $labels = [];
        preg_match_all('/(\d+)\s*=\s*([^\d]+?)(?=\s+\d+\s*=|$)/u', $instructions, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $labels[(int) $match[1]] = trim($match[2]);
        }

        return [
            'min' => $min,
            'max' => $max,
            'leftLabel' => $labels[$min] ?? 'Mínimo',
            'rightLabel' => $labels[$max] ?? 'Máximo',
        ];
    }

    private function descriptionFromContext(array $context): string
    {
        $parts = [];
        foreach (['Descripción', 'Objetivo', 'Propósito', 'Instrucciones', 'Aviso importante', 'Nota', 'Advertencia'] as $label) {
            if (! empty($context[$label])) {
                $parts[] = $label.': '.$context[$label];
            }
        }

        return $parts !== []
            ? Str::limit(implode("\n\n", $parts), 5000, '')
            : 'Cuestionario importado. Revísalo antes de guardarlo.';
    }

    private function option(string $label): array
    {
        return ['id' => $this->id('opt'), 'label' => $label];
    }

    private function id(string $prefix): string
    {
        return $prefix.'_'.Str::lower(Str::random(12));
    }

    private function cleanText(string $text): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $text));
    }
}
