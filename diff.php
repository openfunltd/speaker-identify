<?php

include(__DIR__ . "/vendor/autoload.php");

use Jfcherng\Diff\Differ;
use Jfcherng\Diff\DiffHelper;
use Jfcherng\Diff\Factory\RendererFactory;
use Jfcherng\Diff\Renderer\RendererConstant;
if (!($_SERVER['argc'] == 3)) {
    echo "Usage: php diff.php whisper.txt origin.txt\n";
    exit(1);
}
$whisper_file = $_SERVER['argv'][1];
$origin_file = $_SERVER['argv'][2];

$whisper_array = [];
$fp = fopen($whisper_file, "r");
while ($line = fgets($fp)) {
    if (!preg_match('#^\[([^\]]+)\] (.*)#', $line, $matches)) {
        continue;
    }
    $whisper_array[] = $matches[1];
    $whisper_array = array_merge($whisper_array, mb_str_split($matches[2], 1, 'UTF-8'));
}
fclose($fp);

$origin_array = [];
$fp = fopen($origin_file, "r");
while ($line = fgets($fp)) {
    $pos = strpos($line, '：');
    if (false !== $pos and $pos < 50) {
        $speaker = explode('：', $line)[0] . "：";
        if (strpos($speaker, '、') !== false) {
        } elseif ($speaker == '：') {
        } elseif (strpos($speaker, '(') === 0) {
        } elseif (preg_match('#^[0-9]#', $speaker)) {
        } elseif (strpos($speaker, '通過') === 0){
        } elseif (in_array($speaker, [
            '決議', '連署人', '提案人', '列席官員', '主席宣告', '時　　間', '地　　點',
            '立法院書面資料',
        ])) {
        } else {
            $origin_array[] = $speaker;
            $line = explode('：', $line)[1];
        }
    }
    $origin_array = array_merge($origin_array, mb_str_split($line, 1, 'UTF-8'));
}

$differOptions = [
	// show how many neighbor lines
	// Differ::CONTEXT_ALL can be used to show the whole file
	'context' => Differ::CONTEXT_ALL,
	// ignore case difference
	'ignoreCase' => true,
	// ignore line ending difference
	'ignoreLineEnding' => true,
	// ignore whitespace difference
	'ignoreWhitespace' => true,
	// if the input sequence is too long, it will just gives up (especially for char-level diff)
	'lengthLimit' => 2000000,
	// if truthy, when inputs are identical, the whole inputs will be rendered in the output
	'fullContextIfIdentical' => false,
];

$rendererOptions = [
	// how detailed the rendered HTML in-line diff is? (none, line, word, char)
	'detailLevel' => 'char',
	// renderer language: eng, cht, chs, jpn, ...
	// or an array which has the same keys with a language file
	// check the "Custom Language" section in the readme for more advanced usage
	'language' => 'cht',
	// show line numbers in HTML renderers
	'lineNumbers' => true,
	// show a separator between different diff hunks in HTML renderers
	'separateBlock' => true,
	// show the (table) header
	'showHeader' => true,
	// the frontend HTML could use CSS "white-space: pre;" to visualize consecutive whitespaces
	// but if you want to visualize them in the backend with "&nbsp;", you can set this to true
	'spacesToNbsp' => false,
	// HTML renderer tab width (negative = do not convert into spaces)
	'tabSize' => 4,
	// this option is currently only for the Combined renderer.
	// it determines whether a replace-type block should be merged or not
	// depending on the content changed ratio, which values between 0 and 1.
	'mergeThreshold' => 0.8,
	// this option is currently only for the Unified and the Context renderers.
	// RendererConstant::CLI_COLOR_AUTO = colorize the output if possible (default)
	// RendererConstant::CLI_COLOR_ENABLE = force to colorize the output
	// RendererConstant::CLI_COLOR_DISABLE = force not to colorize the output
	'cliColorization' => RendererConstant::CLI_COLOR_AUTO,
	// this option is currently only for the Json renderer.
	// internally, ops (tags) are all int type but this is not good for human reading.
	// set this to "true" to convert them into string form before outputting.
	'outputTagAsString' => false,
	// this option is currently only for the Json renderer.
	// it controls how the output JSON is formatted.
	// see available options on https://www.php.net/manual/en/function.json-encode.php
	'jsonEncodeFlags' => \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
	// this option is currently effective when the "detailLevel" is "word"
	// characters listed in this array can be used to make diff segments into a whole
	// for example, making "<del>good</del>-<del>looking</del>" into "<del>good-looking</del>"
	// this should bring better readability but set this to empty array if you do not want it
	'wordGlues' => [' ', '-'],
	// change this value to a string as the returned diff if the two input strings are identical
	'resultForIdenticals' => null,
	// extra HTML classes added to the DOM of the diff container
	'wrapperClasses' => ['diff-wrapper'],
];

$differ = new Differ($origin_array, $whisper_array, $differOptions);
$renderer = RendererFactory::make('Json', $rendererOptions);
$result = $renderer->render($differ);
$result = json_decode($result);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
