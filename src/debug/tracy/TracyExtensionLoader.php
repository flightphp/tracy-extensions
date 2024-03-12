<?php
declare(strict_types=1);

namespace flight\debug\tracy;

use Exception;
use Flight;
use flight\debug\database\PdoQueryCapture;
use flight\Engine;
use Throwable;
use Tracy\Debugger;

class TracyExtensionLoader {

	protected array $config;

	/**
	 * Construct
	 *
	 * @param Engine|null $app    Flight engine instance
	 * @param array       $config Additional configuration options
	 */
	public function __construct(Engine $app = null, array $config = []) {
		if(Debugger::isEnabled() === false) {
			throw new Exception('You need to enable Tracy\Debugger before using this extension!');
		}

		if($app === null) {
			$app = Flight::app();
		}

		// This is to make double sure that the errors are handled by Tracy
		$app->set('flight.handle_errors', false);

		$this->config = $config;

		$this->loadExtensions($app);
	}

	protected function loadExtensions(Engine $app): void {
		Debugger::getBar()->addPanel(new FlightPanelExtension($app));
		Debugger::getBar()->addPanel(new RequestExtension($app));
		Debugger::getBar()->addPanel(new ResponseExtension($app));

		if(isset(PdoQueryCapture::$query_data)) {
			Debugger::getBar()->addPanel(new DatabaseExtension);
		}

		// if there's no session data, then don't show the panel
		if(session_status() === PHP_SESSION_ACTIVE || !empty($this->config['session_data'])) {
			$session_data = $this->config['session_data'] ?? $_SESSION;
			Debugger::getBar()->addPanel(new SessionExtension($session_data));
		}

		Debugger::getBlueScreen()->addPanel(function(?Throwable $e) use ($app) {
			$FlightPanelExtension = new FlightPanelExtension($app);
			$FlightPanelExtension->setValueWidth(800);
			if($e instanceof Throwable && $e->getMessage()) {
				return [];
			}
			return [
				'tab' => 'Flight Variables',
				'panel' => $FlightPanelExtension->getPanel(),
				'bottom' => true,
			];
		});
	}
} 
