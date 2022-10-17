<?php
declare(strict_types=1);

/**
 * .pgn file parser in raw self-contained php with pagination using php streams and generator in less than 200loc
 * @author        Alexandre ELISÉ <contact@alexandree.io>
 * @copyright (c) 2009 - present. Alexandre ELISÉ. All rights reserved.
 * @license       GPL-2.0-and-later GNU General Public License v2.0 or later
 * @link          https://alexandree.io
 */

// Public data
// Source of Elite lichess database: https://database.nikonoel.fr/
// Lichess elite database with 1 026 924 games October 2021 924.5MB uncompressed
$dataSourceUrl = __DIR__ . '/lichess_elite_2021-10.pgn';
//pagination
$startPage     = 100;
$perPage       = 1000;
$isCli         = PHP_SAPI === 'cli';
defined('CUSTOM_LINE_END') || define('CUSTOM_LINE_END', ($isCli ? PHP_EOL : '<br>'));
if (!file_exists($dataSourceUrl))
{
	die('datasource does not exists');
}

// PHP Generator to efficiently read the datasource file
$enhancedReadDataSource = function (string $url, callable $specCompliance, callable $parseHeader, callable $parseGame): Generator {
	try
	{
		
		if (empty($url))
		{
			throw new RuntimeException('Url MUST NOT be empty', 422);
		}
		
		$resource = fopen($url, 'r');
		
		if ($resource === false)
		{
			throw new RuntimeException('Could not read datasource file', 500);
		}
		
		if (($offset < 0) || ($limit < 0))
		{
			throw new RuntimeException('Offset or limit MUST be positive integers', 422);
		}
		
		// NON-BLOCKING I/O (Does not wait before processing next line.)
		stream_set_blocking($resource, false);
		$countLine       = 0;
		$countEmptyLines = 0;
		do
		{
			$currentLine = stream_get_line(
				$resource,
				0,
				"\n"
			);
			if ($currentLine === false)
			{
				continue;
			}
			++$countLine;
			if (empty($currentLine))
			{
				++$countEmptyLines;
			}
			$compliantLine = $specCompliance($currentLine);
			if ($countEmptyLines === 0)
			{
				yield $parseHeader($compliantLine, true);
			}
			if ($countEmptyLines === 1)
			{
				yield $parseGame($compliantLine);
			}
			if ($countEmptyLines === 2)
			{
				// Reset empty line counter used to separate multiple games
				$countEmptyLines = 0;
				yield 'END_CURRENT_PARSE';
			}
		} while (!feof($resource));
	}
	catch (Throwable $exception)
	{
		echo $exception->getMessage() . CUSTOM_LINE_END;
	} finally
	{
		fclose($resource);
	}
};
// Spec rules for .pgn file format
$compliance = function ($currentLine, $isExport = false): string {
	// MUST be ISO-8859-1 (latin 1) character encoding
	$transformed = iconv('utf-8', 'iso-8859-1', $currentLine);
	if ($transformed === false)
	{
		$transformed = $currentLine;
	}
	// Allowed length for export 80 characters. Otherwise don't limit length
	if ($isExport)
	{
		$transformed = substr($transformed, 0, 80);
	}
	
	// Allowed characters in iso-8859-1
	$splittedToChars = mb_str_split($transformed);
	if ($splittedToChars === false)
	{
		return $transformed;
	}
	$transformed = implode('', array_values(array_filter($splittedToChars, function ($char) {
		$currentOrd = ord($char);
		
		return (($currentOrd === 9) || ($currentOrd === 10))
			|| ($currentOrd >= 32 && $currentOrd <= 126)
			|| ($currentOrd >= 192 && $currentOrd <= 255);
	})));
	
	return $transformed;
};
// Parse .pgn file header part
$headerParser = function (string $item, bool $onlyRequiredHeaders = false): string {
	if (preg_match('/^\[(?P<headerKey>[A-Z][A-Za-z]+)\s\"(?P<headerValue>[^\"]+)\"]$/su', $item, $headerMatches) > 0)
	{
		$output = array_intersect_key($headerMatches, ['headerKey' => 1, 'headerValue' => 1]);
		
		return implode(' ', $onlyRequiredHeaders ? array_splice($output, 0, 7) : $output);
	}
	
	return '';
};
//TODO: Parse player moves
$gameParser = function (string $item): string {
	return $item;
};
// Read datasource in a PHP Generator using streams in non-blocking I/O mode
try
{
	$streamDataSource     = $enhancedReadDataSource($dataSourceUrl, $compliance, $headerParser, $gameParser);
	$countEndCurrentParse = 0;
	foreach ($streamDataSource as $dataKey => $dataValue)
	{
		if (is_string($dataValue))
		{
			if ($dataValue === 'END_CURRENT_PARSE')
			{
				++$countEndCurrentParse;
			}
			elseif ((($countEndCurrentParse >= $startPage) && ($countEndCurrentParse < ($startPage + $perPage))))
			{
				echo $dataValue . CUSTOM_LINE_END;
			}
			elseif ($countEndCurrentParse > ($startPage + $perPage))
			{
				$streamDataSource->throw(new DomainException('Limit reached. Stopping here.', 429));
			}
		}
	}
}
catch (Throwable $e)
{
	echo $e->getMessage() . CUSTOM_LINE_END;
}
