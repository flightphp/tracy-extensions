<?php
declare(strict_types=1);

namespace flight\debug\tracy;

use flight\Engine;

class FlightPanelExtension extends ExtensionBase implements \Tracy\IBarPanel {

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
		// get all the vars
		$flight_var_data = $this->app->get();
		$current_route = $this->app->router()->current();
		$methods = '';
		$params = '';
		$pattern = '';
		$alias = '';
		$regex = '';
		$splat = '';
		if($current_route) {
			$methods = implode(', ', $current_route->methods);
			$params = $current_route->params ? print_r($current_route->params, true) : '';
			$pattern = $current_route->pattern;
			$alias = $current_route->alias;
			$regex = $current_route->regex;
			$splat = $current_route->splat;
		}
		$flight_var_data['Current Route'] = <<<TEXT
			Pattern: {$pattern}
			Methods: {$methods}
			Params:  {$params}
			Alias:   {$alias}
			Regex:   {$regex}
			Splat:   {$splat}
			TEXT;
		foreach($flight_var_data as $key => &$data) {
			if(is_object($data) === true) {
				$data = get_class($data).' Class';
			}
		}
		ksort($flight_var_data, SORT_NATURAL);
		$table_tr_html = '';
		foreach($flight_var_data as $key => $value) {
			$table_tr_html .= '<tr><td>'.$key.'</td><td>'.$this->handleLongStrings($value).'</td></tr>'."\n";
		}
		$html = <<<EOT
			<h1>Flight Data</h1> 
			<div class="tracy-inner" style="max-height: 400px; overflow: auto;">
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
		return <<<EOT
			<span title="Flight Vars">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="indigo" class="bi bi-file-zip-fill" viewBox="0 0 16 16">
					<path d="M8.5 9.438V8.5h-1v.938a1 1 0 0 1-.03.243l-.4 1.598.93.62.93-.62-.4-1.598a1 1 0 0 1-.03-.243z"/>
					<path d="M4 0h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2zm2.5 8.5v.938l-.4 1.599a1 1 0 0 0 .416 1.074l.93.62a1 1 0 0 0 1.109 0l.93-.62a1 1 0 0 0 .415-1.074l-.4-1.599V8.5a1 1 0 0 0-1-1h-1a1 1 0 0 0-1 1zm1-5.5h-1v1h1v1h-1v1h1v1H9V6H8V5h1V4H8V3h1V2H8V1H6.5v1h1v1z"/>
				</svg>
				<span class="tracy-label">Flight</span>
			</span>
			EOT;
	}

}
