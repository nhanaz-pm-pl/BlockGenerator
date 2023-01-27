<?php

declare(strict_types=1);

namespace NhanAZ\BlockGenerator\utils;

use pocketmine\block\Block;
use pocketmine\item\ItemBlock;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\item\LegacyStringToItemParserException;
use pocketmine\item\StringToItemParser;

class StringToBlock {

	public static function parse(string $string): Block {
		try {
			$item = StringToItemParser::getInstance()->parse($string) ?? LegacyStringToItemParser::getInstance()->parse($string);
		} catch (LegacyStringToItemParserException $e) {
			throw new \Exception($e->getMessage());
		}
		if (!$item instanceof ItemBlock) {
			throw new \Exception("\"{$string}\" is not a block!");
		}
		return $item->getBlock();
	}
}
