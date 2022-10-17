# pgnparser

.pgn file parser in raw self-contained php with pagination using php streams and generator in less than 200loc.

## WHY?
For the challenge. I love PHP and chess is an interesting challenge for a developer.
A long time ago I started working on a chess engine in php, again, to learn how it works and mainly for the challenge. Not finished yet as it is quite some work for a side project like this. This .pgn file parser is one step of many that will be later tidied up and integrated in the chess engine using OOP best pratices. But for this specific file ``` pgnparser.php  ``` the constraint were:
 - The whole code should be less than 200loc
 - The file should be self-contained
 - Should have pagination (show only which games given a clamped window e.g. 10 games from the 3rd)
 - Should handle huge files (In this case almost 1GB file with over 1 Million games)

## WHAT?

.pgn file format stands for "portable game notation" is it used to import/export chess games representation in a format readable by humans and easily parseable by machines

## HOW?

For this challenge I chose to use a combination of functions available in php and features like streams and generators. The streams had a hidden feature I discovered while working with it allowing read stream in non-blocking mode. A little boost in speed when reading. Another tip I found while reading php documentation is using generator for efficient huge file reading. Reading line by line using yield rather than 1 GB at once in memory which might reach php's memory limit pretty quickly, if not instantanously.
One thing that I found that might be useful to you to understand how it works is using yield to send a special string line 80: ``` yield 'END_CURRENT_PARSE';  ``` in order to know when 1 game has been red. Then counting how many times I get this string from the generator to then do a simple comparaison line 150: ``` (($countEndCurrentParse >= $startPage) && ($countEndCurrentParse < ($startPage + $perPage)))  ```. That's how the pagination works.


## USAGE:
- Clone this repo
- Go to https://database.nikonoel.fr/ and download than extract one the elite games database or use your own .pgn file
- At the beginning of ``` pgnparser.php ``` file change the ``` $startPage ``` and/or ```  $perPage  ```
- Execute the script using the command-line ``` php phpparser.php ``` or using a web server like Apache or NginX
