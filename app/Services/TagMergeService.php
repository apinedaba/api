<?php
// app/Services/TagMergeService.php
namespace App\Services;

class TagMergeService {
  public function merge(string $html, array $data, array $requiredKeys = []): array {
    $missing = [];
    foreach ($requiredKeys as $key) {
      if ($this->getDot($data, $key) === null) $missing[] = $key;
    }
    if (!empty($missing)) return [false, $html, $missing];

    $out = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\.\-]+)\s*\}\}/', function($m) use ($data) {
      $val = $this->getDot($data, $m[1]);
      return is_null($val) ? $m[0] : e((string)$val);
    }, $html);

    return [true, $out, []];
  }

  private function getDot(array $data, string $path){
    $parts = explode('.', $path); $ref = $data;
    foreach ($parts as $p) {
      if (!is_array($ref) || !array_key_exists($p,$ref)) return null;
      $ref = $ref[$p];
    }
    return $ref;
  }
}
