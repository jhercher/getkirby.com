<?php

use Kirby\Cms\App;
use Kirby\Cms\Page;

return function (App $kirby, Page $page) {
	$related = page('plugins')
		->grandChildren()
		->filterBy('category', $page->category()->value())
		->not($page);

	if ($page->subcategory()->isNotEmpty()) {
		$related = $related->filterBy(
			'subcategory',
			$page->subcategory()->value()
		);
	}

	return [
		'categories'      => $kirby->option('plugins.categories'),
		'currentCategory' => $page->category(),
		'download'        => $page->download(),
		'author'          => $page->parent(),
		'authorPlugins'   => $page->siblings(false),
		'relatedPlugins'  => $related
	];
};
