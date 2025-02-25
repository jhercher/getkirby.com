<?php

namespace Buy;

use Exception;
use Kirby\Toolkit\A;

class Price
{
	public readonly string $currency;
	public readonly float $rate;

	public function __construct(
		public readonly Product $product,
		string|null $currency = null,
		float|null $rate = null
	) {
		if ($currency === 'EUR') {
			// allow missing rate as it's always 1
			if ($rate === null) {
				$rate = 1.0;
			}

			// every other rate is invalid
			// because of hardcoded EUR prices
			if ($rate !== 1.0) {
				throw new Exception('Conversion rate for EUR must be 1.0');
			}
		}

		// currency and rate always need to both not be passed
		// or passed together, one alone won't work
		if ($currency === null xor $rate === null) {
			throw new Exception('Passing just one of currency or rate is not supported');
		}

		// dynamically determine from Paddle
		if ($rate === null) {
			$visitor = Paddle::visitor();

			$currency = $visitor->currency();
			$rate     = $visitor->rate();
		}

		$this->currency = $currency;
		$this->rate     = $rate;
	}

	/**
	 * Converts a price from EUR to the given currency
	 * and rounds it to the nearest pretty price
	 */
	public function convert(int $price): int
	{
		$price *= $this->rate;
		return $this->round($price);
	}

	/**
	 * Rounds a price to the nearest pretty price
	 * (ending in -5 or -9)
	 */
	public function round(float $price): int
	{
		// if the currency is strong, only round
		// the price to full units as "pretty"
		// rounding would change the price a lot
		if ($this->rate < 0.5) {
			return round($price);
		}

		$step = $this->step();

		// apply "pretty" rounding so that for EUR
		// the rounding is applied on the last place
		// and for a currency with 10x higher prices
		// on the second-last place
		$price = round($price / 5 / $step) * 5 * $step;

		if ($price % max($step, 10) === 0) {
			$price -= 1;
		}

		return max(0, $price);
	}

	/**
	 * Gets the regular price for the product
	 */
	public function regular(): int
	{
		return $this->convert($this->product->rawPrice());
	}

	/**
	 * Gets the sale price for the product
	 * (regular price if no sale is active)
	 */
	public function sale(): int
	{
		$sale  = new Sale();
		$price = $this->regular();

		if ($sale->isActive() === true) {
			$price *= 1 - $sale->discount() / 100;

			// use the pretty rounding if possible, but ensure that
			// the price is never higher than the mathematical sale
			// price from the discount percentage we promise
			return min($this->round($price), floor($price));
		}

		return $price;
	}

	/**
	 * Calculates the order of magnitude of
	 * the currency compared to EUR
	 */
	public function step(): int
	{
		$length     = strlen((int)$this->rate);
		$firstDigit = ((string)$this->rate)[0];

		return pow(10, $firstDigit >= 5 ? $length : $length - 1);
	}

	/**
	 * Gets the upgrade prices object
	 */
	public function upgrade(): Upgrade
	{
		return new Upgrade($this);
	}

	/**
	 * Gets the price for a single license
	 * at the given volume
	 */
	public function volume(int $volume): float
	{
		$price = $this->sale();

		// sort discounts by largest package first
		$discounts = option('buy.volume');
		krsort($discounts);

		// find highest possible discount for given volume
		$discount = A::find(
			$discounts,
			fn ($discount, $count) => $count <= $volume
		);

		$price *= 1 - $discount / 100;

		return round($price, 2);
	}
}
