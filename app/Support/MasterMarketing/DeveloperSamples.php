<?php

namespace App\Support\MasterMarketing;

/**
 * MASTER-ONLY. Builds the same API request in four languages (curl, PHP, Node, Python) from one description, so the samples in the developer docs can never
 * disagree with each other. The host placeholder `YOUR-DOMAIN` is swapped for this install's host when a page renders.
 */
final class DeveloperSamples
{
    public const BASE = 'https://YOUR-DOMAIN/api/v1';

    /** @return array<string,string> language => code */
    public static function request(string $method, string $path, ?array $body = null): array
    {
        $method = strtoupper($method);
        $url = self::BASE.$path;

        return [
            'curl' => self::curl($method, $url, $body),
            'php' => self::php($method, $path, $body),
            'node' => self::node($method, $url, $body),
            'python' => self::python($method, $url, $body),
        ];
    }

    private static function curl(string $method, string $url, ?array $body): string
    {
        $out = $method === 'GET' ? 'curl '.$url : 'curl -X '.$method.' '.$url;
        $out .= " \\\n  -H \"Authorization: Bearer \$NAARA_KEY\"";
        if ($body !== null) {
            $out .= " \\\n  -H \"Content-Type: application/json\" \\\n  -d '".json_encode($body, JSON_UNESCAPED_SLASHES)."'";
        }

        return $out;
    }

    private static function php(string $method, string $path, ?array $body): string
    {
        $opts = "    'headers' => ['Authorization' => 'Bearer ' . getenv('NAARA_KEY')],\n";
        if ($body !== null) {
            $opts .= "    'json' => ".self::phpArray($body, 1).",\n";
        }

        return "<?php\nrequire 'vendor/autoload.php';\n\n\$client = new GuzzleHttp\\Client(['base_uri' => '".self::BASE."/']);\n\n\$response = \$client->".strtolower($method)."('".ltrim($path, '/')."', [\n".$opts."]);\n\necho \$response->getBody();";
    }

    private static function node(string $method, string $url, ?array $body): string
    {
        $headers = "    Authorization: `Bearer \${process.env.NAARA_KEY}`,\n";
        $opts = '';
        if ($method !== 'GET') {
            $opts .= "  method: \"".$method."\",\n";
        }
        if ($body !== null) {
            $headers .= "    \"Content-Type\": \"application/json\",\n";
        }
        $opts .= "  headers: {\n".$headers."  },\n";
        if ($body !== null) {
            $opts .= '  body: JSON.stringify('.self::jsObject($body).'),'."\n";
        }

        return 'const res = await fetch("'.$url."\", {\n".$opts."});\nconsole.log(await res.json());";
    }

    private static function python(string $method, string $url, ?array $body): string
    {
        $call = 'requests.'.strtolower($method);
        $args = '    "'.$url."\",\n    headers={\"Authorization\": f\"Bearer {os.environ['NAARA_KEY']}\"},\n";
        if ($body !== null) {
            $args .= '    json='.self::pyDict($body).",\n";
        }

        return "import os\nimport requests\n\nres = ".$call."(\n".$args.")\nprint(res.json())";
    }

    private static function phpArray(array $a, int $depth): string
    {
        $parts = [];
        foreach ($a as $k => $v) {
            $parts[] = (is_int($k) ? '' : "'".$k."' => ").(is_array($v) ? self::phpArray($v, $depth + 1) : self::scalar($v, 'php'));
        }

        return '['.implode(', ', $parts).']';
    }

    private static function jsObject(array $a): string
    {
        $parts = [];
        foreach ($a as $k => $v) {
            $parts[] = $k.': '.(is_array($v) ? self::jsObject($v) : self::scalar($v, 'js'));
        }

        return '{ '.implode(', ', $parts).' }';
    }

    private static function pyDict(array $a): string
    {
        $parts = [];
        foreach ($a as $k => $v) {
            $parts[] = '"'.$k.'": '.(is_array($v) ? self::pyDict($v) : self::scalar($v, 'py'));
        }

        return '{'.implode(', ', $parts).'}';
    }

    private static function scalar(mixed $v, string $lang): string
    {
        return match (true) {
            is_bool($v) => $lang === 'py' ? ($v ? 'True' : 'False') : ($v ? 'true' : 'false'),
            is_int($v), is_float($v) => (string) $v,
            $v === null => $lang === 'py' ? 'None' : ($lang === 'php' ? 'null' : 'null'),
            default => ($lang === 'php' ? "'".addslashes((string) $v)."'" : '"'.addslashes((string) $v).'"'),
        };
    }
}
