<?php
declare(strict_types=1);

namespace flight\debug\tracy;

use flight\Engine;

class RequestExtension extends ExtensionBase implements \Tracy\IBarPanel {

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
		$app = $this->app;
		$request_data  = $_SERVER;
		ksort($request_data, SORT_NATURAL);
		$table_tr_html = '';
		foreach($request_data as $key => $value) {
			$table_tr_html .= '<tr><td>'.$key.'</td><td>'.$this->handleLongStrings($value).'</td></tr>'."\n";
		}
		$get_html = $this->handleLongStrings($_GET);
		$post_html = $this->handleLongStrings($_POST);
		$files_html = $this->handleLongStrings($_FILES);

		$request_method = $request_data['REQUEST_METHOD'] ?? '';
		$request_uri = $request_data['REQUEST_URI'] ?? '';
		$ip_address = $request_data['REMOTE_ADDR'] ?? '';
		$proxy_ip_address = $app->request()->getProxyIpAddress();
		$host = $request_data['HTTP_HOST'] ?? '';
		$php_self = $request_data['PHP_SELF'] ?? '';
		$user = $request_data['USER'] ?? '';


		$html = <<<EOT
			<h1>Request</h1> 
			<div class="tracy-inner" style="max-height: 400px;">
				<table>
					<tbody>
						<tr><td colspan="2" style="background-color: #EEE;"><b>Common</b></td></tr>
						<tr><td>REQUEST_METHOD</td><td>{$request_method}</td></tr>
						<tr><td>REQUEST_URI</td><td>{$request_uri}</td></tr>
						<tr><td>REMOTE_ADDR</td><td>{$ip_address}</td></tr>
						<tr><td>PROXY_IP_ADDRESS</td><td>{$proxy_ip_address}</td></tr>
						<tr><td>HTTP_HOST</td><td>{$host}</td></tr>
						<tr><td>PHP_SELF</td><td>{$php_self}</td></tr>
						<tr><td>USER</td><td>{$user}</td></tr>
						<tr><td colspan="2" style="background-color: #EEE;"><b>Payload</b></td></tr>
						<tr><td>GET</td><td>{$get_html}</td></tr>
						<tr><td>POST</td><td>{$post_html}</td></tr>
						<tr><td>FILES</td><td>{$files_html}</td></tr>
						<tr><td colspan="2" style="background-color: #EEE;"><b>All</b></td></tr>
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
		$uri = explode('?', $_SERVER['REQUEST_URI'])[0];
		return <<<EOT
			<span title="Request">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="green" class="bi bi-caret-right-square-fill" viewBox="0 0 16 16">
					<path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2zm5.5 10a.5.5 0 0 0 .832.374l4.5-4a.5.5 0 0 0 0-.748l-4.5-4A.5.5 0 0 0 5.5 4v8z"/>
				</svg>
				<span class="tracy-label">{$uri}</span>
			</span>
			EOT;
	}

}
