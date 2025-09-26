<?php
// app/Services/ContractPdfService.php
namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class ContractPdfService
{
  public function renderAndUpload(string $finalHtml, ?string $signatureUrl = null, array $options = []): array
  {
    if ($signatureUrl) {
      $finalHtml = preg_replace(
        '/<div\s+data-signature-slot="patient"\s*><\/div>/',
        '<div data-signature-slot="patient" class="sign-block"><img src="'.$signatureUrl.'" style="max-height:120px"/></div>',
        $finalHtml,
        1
      );
    }

    $opts = new Options();
    $opts->set('isRemoteEnabled', true);
    $opts->set('isHtml5ParserEnabled', true);
    $opts->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($opts);
    $dompdf->setPaper('A4', 'portrait');

    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<style>
  @page { margin: 12mm; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
  .sign-block img { max-height: 120px; }
</style>
</head>
<body>
$finalHtml
</body>
</html>
HTML;

    $dompdf->loadHtml($html);
    $dompdf->render();

    $output = $dompdf->output();
    $sha256 = hash('sha256', $output);

    $tmpPdf = tempnam(sys_get_temp_dir(), 'mm_pdf_').'.pdf';
    file_put_contents($tmpPdf, $output);

    $upload = Cloudinary::uploadFile($tmpPdf, [
      'folder' => $options['folder'] ?? 'mindmeet/contracts/signed',
      'resource_type' => 'raw',
      'format' => 'pdf',
      'use_filename' => true,
      'unique_filename' => true,
      'overwrite' => false,
    ]);

    @unlink($tmpPdf);

    return [
      'public_id' => $upload->getPublicId(),
      'secure_url' => $upload->getSecurePath(),
      'sha256'    => $sha256,
    ];
  }

  public function uploadPdfFile(string $localPath, string $folder = 'mindmeet/contracts/uploads'): array
  {
    $upload = Cloudinary::uploadFile($localPath, [
      'folder' => $folder,
      'resource_type' => 'raw',
      'format' => 'pdf',
      'use_filename' => true,
      'unique_filename' => true,
      'overwrite' => false,
    ]);
    return ['public_id'=>$upload->getPublicId(), 'secure_url'=>$upload->getSecurePath()];
  }

  public function uploadSignatureDataUrl(string $dataUrl, string $folder = 'mindmeet/contracts/signatures'): array
  {
    $tmp = tempnam(sys_get_temp_dir(), 'mm_sig_').'.png';
    $raw = preg_replace('#^data:image/\w+;base64,#i', '', $dataUrl);
    file_put_contents($tmp, base64_decode($raw));

    $upload = Cloudinary::uploadFile($tmp, [
      'folder'=>$folder, 'resource_type'=>'image', 'format'=>'png',
      'use_filename'=>true, 'unique_filename'=>true, 'overwrite'=>false,
    ]);
    @unlink($tmp);
    return ['public_id'=>$upload->getPublicId(), 'secure_url'=>$upload->getSecurePath()];
  }
}
