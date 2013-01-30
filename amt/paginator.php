<?php

namespace AMWhalen\ArchiveMyTweets;

class Paginator {

	/**
	 * Returns the HTML to display pagination links.
	 *
	 * @param string $urlTemplate The URL template for links with a page number.
	 * @param int $total The total number of items across all pages.
	 * @param int $currentPage The current page to be displayed.
	 * @param int $perPage The total tweets per page.
	 * @return string The pagination links HTML.
	 */
	public function paginate($baseUrl, $total, $currentPage=1, $perPage=100) {
		
		$numPages = ceil($total / $perPage);
		
		$html = '<div class="amt-pagination"><ul class="pager">';
		
		if ($currentPage > 1) {
			$html .= '<li class="previous"><a href="' . $baseUrl . 'page/' . ($currentPage - 1) . '">&larr; Newer Tweets</a></li>';
		}
		
		if ($currentPage < $numPages) {
			$html .= '<li class="next"><a href="' . $baseUrl . 'page/' . ($currentPage + 1) . '">Older Tweets &rarr;</a></li>';
		}
		
		$html .= '</ul>';
		
		$html .= '<div class="pages">Page ' . $currentPage . ' of ' . $numPages . '</div>';

		$html .= '</div>';
		
		return $html;

	}

}