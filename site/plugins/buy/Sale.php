<?php

namespace Buy;

class Sale
{
	public readonly string $start;
	public readonly string $end;
	public readonly int $discount;

	public function __construct()
	{
		$options = option('buy.sale', []);

		// calculate timestamps in UTC, even if the server uses a different timezone
		$this->start    = strtotime(($options['start'] ?? '1970-01-01') . ' 00:00Z');
		$this->end      = strtotime(($options['end'] ?? '9999-01-01') . ' 24:00Z');
		$this->discount = $options['discount'] ?? 0;
	}

	/**
	 * Returns the current sale discount (0 = no sale / 100 = for free)
	 */
	public function discount(): int
	{
		return $this->isActive() ? $this->discount : 0;
	}

	/**
	 * Returns the end date as formatted string
	 */
	public function ends(): string
	{
		return match (true) {
			$this->end - time() < 24 * 60 * 60
				=> '<strong>today</strong> (midnight UTC)',
			default
			// the end date is inclusive (midnight of next day),
			// subtract one to show the correct date
			=> date('M jS', $this->end - 1),
		};
	}

	/**
	 * Sets the cache expiration date
	 * to make sure the sale banner is updated and not
	 * cached beyond the end of sale
	 */
	public function expires(): void
	{
		$expires = null;

		// the cache will expire once the sale will start
		if ($this->start > time()) {
			$expires = $this->start;
		}

		// if a banner is currently active, the cache
		// will also expire when the active banner ends
		if ($this->isActive() === true) {
			// expire one day before the end date,
			// so the text can change on the last day
			$end     = $this->end - 24 * 60 * 60;
			$expires = match ($expires) {
				null    => $end,
				default => min($expires, $end),
			};
		}

		kirby()->response()->expires($expires);
	}

	/**
	 * Whether Kirby is currently in a sale
	 */
	public function isActive(): bool
	{
		return time() >= $this->start && time() <= $this->end;
	}

	/**
	 * Returns the text for the sale banner
	 */
	public function text(): string
	{
		return '🛍 &nbsp; <strong>Save  ' . $this->discount() . '%</strong> until ' . $this->ends();
	}
}
