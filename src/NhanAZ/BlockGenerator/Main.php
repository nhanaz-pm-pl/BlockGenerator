<?php

declare(strict_types=1);

namespace NhanAZ\BlockGenerator;

use NhanAZ\BlockGenerator\utils\StringToBlock;
use pocketmine\block\Block;
use pocketmine\block\Fence;
use pocketmine\block\Liquid;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Water;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\Position;
use pocketmine\world\sound\FizzSound;

class Main extends PluginBase implements Listener {

	/** @var array<Block> $blocks */
	private $blocks = [];

	protected function onEnable(): void {
		$this->saveDefaultConfig();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->generalCheck();
		$this->buildBlocks();
	}

	private function generalCheck(): void {
		$blocks = $this->getConfig()->get("blocks");
		if (!is_array($blocks)) {
			throw new \Exception("The value of the `blocks` field must be an array!");
		}
		$totalPercentage = 0;
		foreach ($blocks as $block => $percentage) {
			$totalPercentage += $percentage;
			StringToBlock::parse($block);
			if (!is_numeric($percentage)) {
				throw new \Exception("The percentage of \"$block\" must be numeric!");
			}
			if ($percentage <= 0) {
				throw new \Exception("The percentage of \"$block\" must be > 0!");
			}
			if ($percentage < 0.1 and $this->getConfig()->get("percentageWarning")) {
				$this->getLogger()->warning("The percentage of \"$block: $percentage%\" should be > 0.1%!");
			}
		}
		if ($totalPercentage != 100) {
			throw new \Exception("Invalid total percentage. The total of the percentages of the blocks must be 100%!");
		}
		if ($this->getMinValueFromAssociativeArray($blocks) == PHP_INT_MAX) {
			throw new \Exception("Something is wrong with the percentage of blocks!");
		}
		if ($this->getConfig()->get("delayTime") < 0) {
			throw new \Exception("Delay time (seconds) for a new block to be produced must be > 0!");
		}
	}

	/**
	 * Get the minimum value from an associative array.
	 *
	 * @param array<string, float> $array The input associative array.
	 *
	 * @return float The minimum value from the associative array.
	 */
	private function getMinValueFromAssociativeArray(array $array): float {
		$min = PHP_INT_MAX;
		foreach ($array as $value) {
			$min = min($min, $value);
		}
		return $min;
	}

	/**
	 * Convert a float to the hacking format.
	 * Example: 0.9 return 10, 0.98 return 100, 0.987 return 1000,...
	 * If !($number > 0 and $number < 1) return 1.
	 * */
	private function floatToHachkingFormat(float $number): float {
		if ($number > 0 and $number < 1) {
			/** Convert to string, get the decimal part and calculate its length */
			$number = strval($number);
			$number = explode(".", $number);
			$number = $number[1];
			$number = strlen($number);
			/** Repeat zeroes as many times as the decimal part length */
			$number = str_repeat("0", $number);
			/** Concatenate the zeroes to 1 and convert back to int */
			$number = "1" . $number;
			$number = intval($number);
			return $number;
		}
		/** Return 1 if float is already in hachking format */
		return 1;
	}

	private function buildBlocks(): void {
		$blocks = $this->getConfig()->get("blocks");
		if (!is_array($blocks)) {
			throw new \Exception("The value of the `blocks` field must be an array!");
		}
		$min = $this->getMinValueFromAssociativeArray($blocks);
		$min = $this->floatToHachkingFormat($min);
		foreach ($blocks as $block => $percentage) {
			$numberOfElements = round($percentage * $min);
			for ($i = 0; $i < $numberOfElements; $i++) {
				array_push($this->blocks, $block);
			}
		}
		shuffle($this->blocks);
	}

	private function setBlock(Position $blockPos): void {
		$block = strval($this->blocks[array_rand($this->blocks)]);
		$block = StringToBlock::parse($block);
		$blockPos->getWorld()->setBlock($blockPos, $block, false);
	}

	private function playFizzSound(Position $blockPos): void {
		if ($this->getConfig()->get("produceSound")) {
			$blockPos->getWorld()->addSound($blockPos->add(0.5, 0.5, 0.5), new FizzSound());
		}
	}

	public function onBlockUpdate(BlockUpdateEvent $event): void {
		$block = $event->getBlock();
		if ($block instanceof Fence) {
			$mode = $this->getConfig()->get("generatorMode");
			$checkSource = $this->getConfig()->get("checkSource");
			$delayTime = $this->getConfig()->get("delayTime");
			if ($mode == "nonInteract") {
				foreach ($block->getAllSides() as $facing => $block) {
					$side = $block->getSide($facing);
					if ($side instanceof Water or $block instanceof Water) {
						if ($checkSource) {
							if ($block instanceof Liquid) {
								if ($block->isSource()) {
									continue;
								}
							}
						}
						if ($block->isSameType(VanillaBlocks::AIR()) or $block->isSameType(VanillaBlocks::WATER())) {
							if ($delayTime > 0) {
								$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($block): void {
									$this->setBlock($block->getPosition());
									$this->playFizzSound($block->getPosition());
								}), intval($delayTime) * 20);
							} elseif ($delayTime == 0) {
								$this->setBlock($block->getPosition());
								$this->playFizzSound($block->getPosition());
							}
						}
					}
				}
				return;
			}

			if ($mode == "interact") {
				foreach ($block->getAllSides() as $block) {
					if ($block instanceof Water) {
						if ($checkSource) {
							if ($block instanceof Liquid) {
								if ($block->isSource()) {
									continue;
								}
							}
						}
						if ($block->isSameType(VanillaBlocks::AIR()) or $block->isSameType(VanillaBlocks::WATER())) {
							if ($delayTime > 0) {
								$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($block): void {
									$this->setBlock($block->getPosition());
									$this->playFizzSound($block->getPosition());
								}), intval($delayTime) * 20);
							} elseif ($delayTime == 0) {
								$this->setBlock($block->getPosition());
								$this->playFizzSound($block->getPosition());
							}
						}
					}
				}
				return;
			}
		}
	}
}
