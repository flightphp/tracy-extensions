<?php
declare(strict_types=1);

namespace flight\debug\tracy;

use flight\debug\database\PdoQueryCapture;

class DatabaseExtension extends ExtensionBase implements \Tracy\IBarPanel {

	protected const LONG_QUERY_TIME = 0.5;

	/**
	 * Gets the panel
	 *
	 * @return string
	 */
	public function getPanel() {
		$html = <<<EOT
<div class="tracy-database-panel">
	<div class="tracy-database-heading">
		<h1>Database Queries</h1> 
	</div>
	<div class="tracy-database-controls">
		<div class="tracy-database-controls-header tracy-inner" style="max-height: 400px;">
			<table class="tracy-sortable">
				<thead>
					<tr>
						<th>Time</th>
						<th>SQL</th>
						<th>Backtrace</th>
						<th>Rows</th>
					</tr>
				</thead>
				<tbody>
EOT;
		$query_data = PdoQueryCapture::$query_data;
		if(!empty($query_data)) {
			foreach($query_data as $data) {
				$time		    = round($data['execution_time'] + ($data['prepare_time'] ?? 0), 4);
				$sql 		    = $this->handleLongStrings(($data['query'] ?? ''));
				$backtrace      = $this->handleLongStrings(($data['backtrace'] ?? ''));
				$rows           = $data['rows'] ?? 0;
				$long_query_row = $time > self::LONG_QUERY_TIME ? ' style="background-color: coral;"' : '';
				$html          .= <<<EOT
						<tr{$long_query_row}>
							<td>{$time}</td>
							<td>{$sql}</td>
							<td>{$backtrace}</td>
							<td>{$rows}</td>
						</tr>
						EOT;
			}
		} else {
			$html .= <<<EOT
						<tr>
							<td colspan="4">No queries were run or you did not create the PdoQueryCapture Database correctly. Please see the documentation for assistance.</td>
						</tr>
						EOT;
		}
		$html .= <<<EOT
				</tbody>
			</table>
		</div>
	</div>
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
		$total_time       = 0;
		$long_query_count = 0;
		$query_count      = 0;
		$query_data = PdoQueryCapture::$query_data;
		if(!empty($query_data)) {
			foreach($query_data as $data) {
				$time = $data['execution_time'] + ($data['prepare_time'] ?? 0);
				$total_time += $time;
				if($time > self::LONG_QUERY_TIME) {
					++$long_query_count;
				}
				++$query_count;
			}
		}
		$total_time = round($total_time, 4);
		$long_query_html = '';
		if($long_query_count > 0) {
			$long_query_html = '<span class="text-danger fw-bold">'.$long_query_count.' long queries!</span>';
		}
		return <<<EOT
<span title="Database Queries">
	<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="darkTurquoise" class="bi bi-server" viewBox="0 0 16 16">
		<path d="M1.333 2.667C1.333 1.194 4.318 0 8 0s6.667 1.194 6.667 2.667V4c0 1.473-2.985 2.667-6.667 2.667S1.333 5.473 1.333 4V2.667z"/>
		<path d="M1.333 6.334v3C1.333 10.805 4.318 12 8 12s6.667-1.194 6.667-2.667V6.334a6.51 6.51 0 0 1-1.458.79C11.81 7.684 9.967 8 8 8c-1.966 0-3.809-.317-5.208-.876a6.508 6.508 0 0 1-1.458-.79z"/>
		<path d="M14.667 11.668a6.51 6.51 0 0 1-1.458.789c-1.4.56-3.242.876-5.21.876-1.966 0-3.809-.316-5.208-.876a6.51 6.51 0 0 1-1.458-.79v1.666C1.333 14.806 4.318 16 8 16s6.667-1.194 6.667-2.667v-1.665z"/>
	</svg>
	<span class="tracy-label">{$total_time} / {$query_count} {$long_query_html}</span>
</span>
EOT;
	}
}
