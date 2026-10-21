<?php
namespace Fbp\ImageEffects;

require_once __DIR__ . '/ImageAiRouting.php';

/** Server-side PHP adapter. Codex workers must never use this API adapter. */
final class OpenAiImageEditor
{
    public function __construct(private string $apiKey, private string $model = 'gpt-image-2')
    {
        if (trim($apiKey) === '' || preg_match('/[\r\n]/', $apiKey)) throw new \InvalidArgumentException('An OpenAI API key is required.');
        if (!preg_match('/^gpt-image-[a-zA-Z0-9.-]+$/D', $model)) throw new \InvalidArgumentException('A GPT Image model name is required.');
    }

    public function edit(string $input, string $mask, string $prompt): string
    {
        ImageAiRouting::requireApi();
        if (!extension_loaded('curl')) throw new \RuntimeException('PHP cURL is required for the OpenAI image API.');
        foreach ([$input, $mask] as $path) {
            if (str_contains($path, '://') || !is_file($path) || filesize($path) > 40 * 1024 * 1024) throw new \InvalidArgumentException('A local PNG up to 40MB is required.');
            $info = @getimagesize($path);
            if (!$info || $info[2] !== IMAGETYPE_PNG) throw new \InvalidArgumentException('Prepared PNG input and alpha mask required.');
        }
        $curl = curl_init('https://api.openai.com/v1/images/edits');
        $body = '';
        try {
            curl_setopt_array($curl, [
                CURLOPT_POST => true, CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 360,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->apiKey],
                CURLOPT_POSTFIELDS => [
                    'model' => $this->model, 'prompt' => $prompt,
                    'image' => new \CURLFile($input, 'image/png', 'input.png'),
                    'mask' => new \CURLFile($mask, 'image/png', 'mask.png'),
                    'output_format' => 'png', 'size' => 'auto', 'n' => '1',
                ],
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > 80 * 1024 * 1024) return 0;
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            if (curl_exec($curl) === false) throw new \RuntimeException('OpenAI image API transport failed (cURL ' . curl_errno($curl) . '); no automatic retry.');
            return self::decodeResponse((int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), $body);
        } finally {
            curl_close($curl);
        }
    }

    /** No response body or credentials are copied into error messages. */
    public static function decodeResponse(int $status, string $body): string
    {
        if ($status < 200 || $status >= 300) throw new \RuntimeException('OpenAI image API returned HTTP ' . $status . '.');
        try {
            $json = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('OpenAI image API returned invalid JSON.');
        }
        $encoded = $json['data'][0]['b64_json'] ?? null;
        if (!is_string($encoded)) throw new \RuntimeException('OpenAI image API returned no image.');
        $bytes = base64_decode($encoded, true);
        if ($bytes === false || strlen($bytes) > 40 * 1024 * 1024 || !str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) throw new \RuntimeException('OpenAI image API returned an invalid PNG.');
        return $bytes;
    }
}
