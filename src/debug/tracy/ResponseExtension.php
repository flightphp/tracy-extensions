<?php
declare(strict_types=1);

namespace flight\debug\tracy;

use flight\Engine;

class ResponseExtension extends ExtensionBase implements \Tracy\IBarPanel {

	/** @var Engine $app */
	protected Engine $app;

	/**
	 * Construct
	 *
	 * @param Engine $app the $app variable
	 */
	public function __construct(Engine $app) {
		$this->app = $app;
	}

	/**
	 * Gets the panel
	 *
	 * @return string
	 */
	public function getPanel() {
		$response = $this->app->response();
		$response_data = [
			'Body' => $response->getBody(),
			'Headers' => $response->getHeaders(),
			'Status Code' => $response->status(),
		];
		ksort($response_data, SORT_NATURAL);
		$table_tr_html = '';
		foreach($response_data as $key => $value) {
			$table_tr_html .= '<tr><td>'.htmlspecialchars($key).'</td><td>'.$this->handleLongStrings($value).'</td></tr>'."\n";
		}
		$html = <<<EOT
			<h1>Response</h1> 
			<div class="tracy-inner" style="max-height: 400px;">
				<table>
					<tbody>
						{$table_tr_html}
					</tbody>
				</table>
			</div>
			EOT;
		return $html;
	}

	/**
	 * Gets the tab
	 *
	 * @return string
	 */
	public function getTab() {
		$status_code = $this->app->response()->status();
		return <<<EOT
			<span title="Response">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="orange" class="bi bi-box-seam-fill" viewBox="0 0 16 16">
					<path fill-rule="evenodd" d="M15.528 2.973a.75.75 0 0 1 .472.696v8.662a.75.75 0 0 1-.472.696l-7.25 2.9a.75.75 0 0 1-.557 0l-7.25-2.9A.75.75 0 0 1 0 12.331V3.669a.75.75 0 0 1 .471-.696L7.443.184l.01-.003.268-.108a.75.75 0 0 1 .558 0l.269.108.01.003zM10.404 2 4.25 4.461 1.846 3.5 1 3.839v.4l6.5 2.6v7.922l.5.2.5-.2V6.84l6.5-2.6v-.4l-.846-.339L8 5.961 5.596 5l6.154-2.461z"/>
				</svg>
				<span class="tracy-label">{$status_code}</span>
			</span>
			EOT;
	}

}
